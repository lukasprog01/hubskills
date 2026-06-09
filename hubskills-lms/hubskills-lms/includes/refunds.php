<?php
/**
 * HubSkills LMS — Workflow de reembolso
 *
 * Aluno solicita reembolso dentro da janela configurável (padrão 7 dias,
 * direito de arrependimento do CDC art. 49); admin aprova ou nega no
 * painel de Pedidos. Aprovação executa hs_refund_order() (desfaz a
 * matrícula) e ambas as decisões notificam o aluno por e-mail.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Configuração ──────────────────────────────────────────
function hs_lms_refund_window_days(): int {
    $days = (int) get_option( 'hs_lms_refund_window_days', 7 );
    return max( 0, (int) apply_filters( 'hs_lms_refund_window_days', $days ) );
}

// ── Consultas ─────────────────────────────────────────────
function hs_lms_user_orders( int $user_id ): array {
    if ( ! $user_id ) return [];
    $posts = get_posts( [
        'post_type'      => 'hs_order',
        'posts_per_page' => 100,
        'post_status'    => 'any',
        'meta_key'       => '_hs_order_user_id',
        'meta_value'     => $user_id,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ] );
    $orders = [];
    foreach ( $posts as $post ) {
        $order = function_exists( 'hs_get_order' ) ? hs_get_order( $post->ID ) : null;
        if ( $order ) $orders[] = $order;
    }
    return $orders;
}

function hs_lms_get_refund_request( int $order_id ): array {
    $request = get_post_meta( $order_id, '_hs_order_refund_request', true );
    return is_array( $request ) ? $request : [];
}

/**
 * Pedido é elegível para solicitação de reembolso pelo aluno?
 * Retorna true ou WP_Error com o motivo.
 */
function hs_lms_refund_eligibility( array $order ) {
    if ( ( $order['status'] ?? '' ) !== 'paid' ) {
        return new WP_Error( 'not_paid', __( 'Apenas pedidos pagos podem ser reembolsados.', 'hubskills-lms' ) );
    }
    $request = hs_lms_get_refund_request( (int) $order['id'] );
    if ( ! empty( $request['status'] ) ) {
        return new WP_Error( 'already_requested', __( 'Já existe uma solicitação de reembolso para este pedido.', 'hubskills-lms' ) );
    }

    $window = hs_lms_refund_window_days();
    if ( $window > 0 ) {
        $paid_at = get_post_meta( (int) $order['id'], '_hs_order_paid_at', true ) ?: ( $order['created_at'] ?? '' );
        $paid_ts = $paid_at ? strtotime( $paid_at ) : 0;
        if ( $paid_ts && ( $paid_ts + $window * DAY_IN_SECONDS ) < current_time( 'timestamp' ) ) {
            return new WP_Error( 'window_expired', sprintf(
                /* translators: %d: dias */
                __( 'O prazo de reembolso (%d dias após a compra) já expirou.', 'hubskills-lms' ),
                $window
            ) );
        }
    }
    return true;
}

// ── Aluno: solicitar reembolso ────────────────────────────
add_action( 'wp_ajax_hs_request_refund', 'hs_lms_ajax_request_refund' );
function hs_lms_ajax_request_refund() {
    if ( function_exists( 'hs_ajax_check' ) ) hs_ajax_check();
    else check_ajax_referer( 'hubskills_nonce', 'nonce' );

    $user_id  = get_current_user_id();
    $order_id = absint( $_POST['order_id'] ?? 0 );
    $reason   = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );

    $order = function_exists( 'hs_get_order' ) ? hs_get_order( $order_id ) : null;
    if ( ! $order || (int) $order['user_id'] !== $user_id ) {
        wp_send_json_error( [ 'message' => __( 'Pedido não encontrado.', 'hubskills-lms' ) ], 404 );
    }
    if ( mb_strlen( $reason ) < 10 ) {
        wp_send_json_error( [ 'message' => __( 'Descreva o motivo da solicitação (mínimo de 10 caracteres).', 'hubskills-lms' ) ], 400 );
    }

    $eligible = hs_lms_refund_eligibility( $order );
    if ( is_wp_error( $eligible ) ) {
        wp_send_json_error( [ 'message' => $eligible->get_error_message() ], 400 );
    }

    update_post_meta( $order_id, '_hs_order_refund_request', [
        'status'       => 'pending',
        'reason'       => $reason,
        'requested_at' => current_time( 'mysql' ),
        'requested_by' => $user_id,
    ] );

    if ( function_exists( 'hs_audit_log' ) ) {
        hs_audit_log( $user_id, 'request_refund', 'Reembolso solicitado para pedido #' . $order_id );
    }

    // Notifica o admin
    $course_title = get_the_title( (int) $order['course_id'] );
    wp_mail(
        get_option( 'admin_email' ),
        sprintf( __( '[%s] Nova solicitação de reembolso — pedido #%d', 'hubskills-lms' ), get_bloginfo( 'name' ), $order_id ),
        sprintf(
            __( "Aluno: %s\nCurso: %s\nValor: R$ %s\nMotivo:\n%s\n\nAnalise em: %s", 'hubskills-lms' ),
            wp_get_current_user()->display_name,
            $course_title,
            number_format( (float) $order['amount'], 2, ',', '.' ),
            $reason,
            admin_url( 'admin.php?page=hubskills-orders' )
        )
    );

    wp_send_json_success( [ 'message' => __( 'Solicitação enviada! Você receberá a resposta por e-mail.', 'hubskills-lms' ) ] );
}

// ── Admin: aprovar / negar ────────────────────────────────
add_action( 'wp_ajax_hs_admin_review_refund', 'hs_lms_ajax_admin_review_refund' );
function hs_lms_ajax_admin_review_refund() {
    if ( function_exists( 'hs_admin_ajax_check' ) ) hs_admin_ajax_check();
    else {
        check_ajax_referer( 'hs_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => __( 'Acesso negado.', 'hubskills-lms' ) ], 403 );
    }

    $order_id = absint( $_POST['order_id'] ?? 0 );
    $decision = sanitize_key( $_POST['decision'] ?? '' );
    $note     = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );

    if ( ! in_array( $decision, [ 'approve', 'deny' ], true ) ) {
        wp_send_json_error( [ 'message' => __( 'Decisão inválida.', 'hubskills-lms' ) ], 400 );
    }

    $request = hs_lms_get_refund_request( $order_id );
    if ( empty( $request ) || ( $request['status'] ?? '' ) !== 'pending' ) {
        wp_send_json_error( [ 'message' => __( 'Nenhuma solicitação pendente para este pedido.', 'hubskills-lms' ) ], 400 );
    }

    $order = function_exists( 'hs_get_order' ) ? hs_get_order( $order_id ) : null;
    if ( ! $order ) wp_send_json_error( [ 'message' => __( 'Pedido não encontrado.', 'hubskills-lms' ) ], 404 );

    if ( $decision === 'approve' ) {
        if ( ! function_exists( 'hs_refund_order' ) || ! hs_refund_order( $order_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Falha ao processar o reembolso.', 'hubskills-lms' ) ], 500 );
        }
        $request['status'] = 'approved';
        $student_subject = __( 'Seu reembolso foi aprovado', 'hubskills-lms' );
        $student_body    = __( "Olá!\n\nSua solicitação de reembolso do pedido #%1\$d (%2\$s) foi APROVADA.\nO estorno será processado pelo meio de pagamento original.\n\n%3\$s", 'hubskills-lms' );
        $audit_action    = 'approve_refund';
    } else {
        $request['status'] = 'denied';
        $student_subject = __( 'Atualização sobre sua solicitação de reembolso', 'hubskills-lms' );
        $student_body    = __( "Olá!\n\nSua solicitação de reembolso do pedido #%1\$d (%2\$s) foi analisada e NÃO foi aprovada.\n\n%3\$s", 'hubskills-lms' );
        $audit_action    = 'deny_refund';
    }

    $request['reviewed_at'] = current_time( 'mysql' );
    $request['reviewed_by'] = get_current_user_id();
    $request['admin_note']  = $note;
    update_post_meta( $order_id, '_hs_order_refund_request', $request );

    if ( function_exists( 'hs_audit_log' ) ) {
        hs_audit_log( get_current_user_id(), $audit_action, ucfirst( $decision ) . ' reembolso pedido #' . $order_id );
    }

    $student = get_userdata( (int) $order['user_id'] );
    if ( $student ) {
        $note_text = $note ? sprintf( __( "Observação da equipe:\n%s", 'hubskills-lms' ), $note ) : '';
        wp_mail(
            $student->user_email,
            sprintf( '[%s] %s', get_bloginfo( 'name' ), $student_subject ),
            sprintf( $student_body, $order_id, get_the_title( (int) $order['course_id'] ), $note_text )
        );
    }

    wp_send_json_success( [
        'message' => $decision === 'approve'
            ? __( 'Reembolso aprovado e matrícula cancelada.', 'hubskills-lms' )
            : __( 'Solicitação negada. O aluno foi notificado.', 'hubskills-lms' ),
    ] );
}

// ── "Meus pedidos" (frontend do aluno) ────────────────────
function hs_lms_render_my_orders(): string {
    if ( ! is_user_logged_in() ) return '';
    $orders = hs_lms_user_orders( get_current_user_id() );
    if ( ! $orders ) return '';

    $window = hs_lms_refund_window_days();
    $labels = [
        'pending'  => __( 'Aguardando pagamento', 'hubskills-lms' ),
        'paid'     => __( 'Pago', 'hubskills-lms' ),
        'refunded' => __( 'Reembolsado', 'hubskills-lms' ),
        'failed'   => __( 'Falhou', 'hubskills-lms' ),
        'canceled' => __( 'Cancelado', 'hubskills-lms' ),
    ];
    $req_labels = [
        'pending'  => __( 'Reembolso em análise', 'hubskills-lms' ),
        'approved' => __( 'Reembolso aprovado', 'hubskills-lms' ),
        'denied'   => __( 'Reembolso negado', 'hubskills-lms' ),
    ];

    ob_start(); ?>
    <div class="hs-my-orders" data-hs-my-orders>
        <h3><i class="fas fa-receipt"></i> <?php _e( 'Meus pedidos', 'hubskills-lms' ); ?></h3>
        <p class="hs-orders-hint"><?php printf( esc_html__( 'Você pode solicitar reembolso em até %d dias após a compra.', 'hubskills-lms' ), $window ); ?></p>
        <table class="hs-orders-table">
            <thead><tr>
                <th><?php _e( 'Pedido', 'hubskills-lms' ); ?></th>
                <th><?php _e( 'Curso', 'hubskills-lms' ); ?></th>
                <th><?php _e( 'Valor', 'hubskills-lms' ); ?></th>
                <th><?php _e( 'Status', 'hubskills-lms' ); ?></th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ( $orders as $order ) :
                $request  = hs_lms_get_refund_request( (int) $order['id'] );
                $eligible = hs_lms_refund_eligibility( $order );
            ?>
                <tr>
                    <td>#<?php echo (int) $order['id']; ?></td>
                    <td><?php echo esc_html( get_the_title( (int) $order['course_id'] ) ); ?></td>
                    <td>R$ <?php echo esc_html( number_format( (float) $order['amount'], 2, ',', '.' ) ); ?></td>
                    <td>
                        <span class="hs-order-status hs-order-<?php echo esc_attr( $order['status'] ); ?>"><?php echo esc_html( $labels[ $order['status'] ] ?? $order['status'] ); ?></span>
                        <?php if ( ! empty( $request['status'] ) ) : ?>
                            <span class="hs-refund-status hs-refund-<?php echo esc_attr( $request['status'] ); ?>"><?php echo esc_html( $req_labels[ $request['status'] ] ?? $request['status'] ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( true === $eligible ) : ?>
                            <button type="button" class="btn btn-ghost btn-sm hs-request-refund" data-order="<?php echo (int) $order['id']; ?>">
                                <?php _e( 'Solicitar reembolso', 'hubskills-lms' ); ?>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return trim( ob_get_clean() );
}

add_shortcode( 'hs_my_orders', 'hs_lms_render_my_orders' );
