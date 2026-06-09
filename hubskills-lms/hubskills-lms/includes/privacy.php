<?php
/**
 * HubSkills LMS — LGPD / Privacidade
 *
 * - Consentimento obrigatório no cadastro (com registro de data e versão da política)
 * - Exportador de dados pessoais (Ferramentas → Exportar dados pessoais)
 * - Apagador de dados pessoais (Ferramentas → Apagar dados pessoais)
 * - Texto sugerido para a Política de Privacidade
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function hs_lms_privacy_policy_version(): string {
    return (string) apply_filters( 'hs_lms_privacy_policy_version', get_option( 'hs_lms_privacy_policy_version', '1.0' ) );
}

function hs_lms_privacy_policy_url(): string {
    $page_id = (int) get_option( 'wp_page_for_privacy_policy' );
    return $page_id ? (string) get_permalink( $page_id ) : home_url( '/politica-de-privacidade/' );
}

// ── Consentimento no cadastro ─────────────────────────────
add_action( 'register_form', 'hs_lms_privacy_consent_field' );
function hs_lms_privacy_consent_field() {
    ?>
    <p style="margin-bottom:16px">
        <label style="display:flex;gap:8px;align-items:flex-start;font-size:13px">
            <input type="checkbox" name="hs_privacy_consent" value="1" <?php checked( ! empty( $_POST['hs_privacy_consent'] ) ); ?> style="margin-top:3px">
            <span>
                <?php printf(
                    wp_kses(
                        /* translators: %s: URL da política de privacidade */
                        __( 'Li e concordo com a <a href="%s" target="_blank" rel="noopener">Política de Privacidade</a> e autorizo o tratamento dos meus dados pessoais para fins de matrícula, progresso de cursos e emissão de certificados (LGPD).', 'hubskills-lms' ),
                        [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ]
                    ),
                    esc_url( hs_lms_privacy_policy_url() )
                ); ?>
            </span>
        </label>
    </p>
    <?php
}

add_filter( 'registration_errors', 'hs_lms_privacy_consent_validate', 10, 1 );
function hs_lms_privacy_consent_validate( WP_Error $errors ) {
    if ( empty( $_POST['hs_privacy_consent'] ) ) {
        $errors->add( 'privacy_consent', __( '<strong>Erro:</strong> é necessário aceitar a Política de Privacidade para criar a conta.', 'hubskills-lms' ) );
    }
    return $errors;
}

add_action( 'user_register', 'hs_lms_privacy_consent_save', 10, 1 );
function hs_lms_privacy_consent_save( int $user_id ) {
    if ( ! empty( $_POST['hs_privacy_consent'] ) ) {
        update_user_meta( $user_id, 'hs_privacy_consent', [
            'accepted_at'    => current_time( 'mysql' ),
            'policy_version' => hs_lms_privacy_policy_version(),
        ] );
    }
}

// ── Exportador de dados pessoais ──────────────────────────
add_filter( 'wp_privacy_personal_data_exporters', 'hs_lms_register_data_exporter' );
function hs_lms_register_data_exporter( array $exporters ): array {
    $exporters['hubskills-lms'] = [
        'exporter_friendly_name' => __( 'HubSkills LMS', 'hubskills-lms' ),
        'callback'               => 'hs_lms_personal_data_exporter',
    ];
    return $exporters;
}

function hs_lms_personal_data_exporter( string $email, int $page = 1 ): array {
    $user = get_user_by( 'email', $email );
    if ( ! $user ) return [ 'data' => [], 'done' => true ];

    $user_id = (int) $user->ID;
    $export = [];

    // Consentimento
    $consent = get_user_meta( $user_id, 'hs_privacy_consent', true );
    if ( is_array( $consent ) && ! empty( $consent['accepted_at'] ) ) {
        $export[] = [
            'group_id'    => 'hs-consent',
            'group_label' => __( 'Consentimento LGPD', 'hubskills-lms' ),
            'item_id'     => 'hs-consent-' . $user_id,
            'data'        => [
                [ 'name' => __( 'Aceito em', 'hubskills-lms' ), 'value' => $consent['accepted_at'] ],
                [ 'name' => __( 'Versão da política', 'hubskills-lms' ), 'value' => $consent['policy_version'] ?? '' ],
            ],
        ];
    }

    // Matrículas e progresso
    $enrollments = get_user_meta( $user_id, 'hs_enrollments', true );
    foreach ( (array) ( is_array( $enrollments ) ? $enrollments : [] ) as $course_id => $info ) {
        $export[] = [
            'group_id'    => 'hs-enrollments',
            'group_label' => __( 'Matrículas e progresso', 'hubskills-lms' ),
            'item_id'     => 'hs-enrollment-' . $course_id,
            'data'        => [
                [ 'name' => __( 'Curso', 'hubskills-lms' ),        'value' => get_the_title( (int) $course_id ) ?: ( '#' . $course_id ) ],
                [ 'name' => __( 'Status', 'hubskills-lms' ),       'value' => (string) ( $info['status'] ?? '' ) ],
                [ 'name' => __( 'Progresso (%)', 'hubskills-lms' ),'value' => (string) ( $info['progress'] ?? 0 ) ],
                [ 'name' => __( 'Matriculado em', 'hubskills-lms' ),'value' => (string) ( $info['enrolled_at'] ?? '' ) ],
                [ 'name' => __( 'Concluído em', 'hubskills-lms' ), 'value' => (string) ( $info['completed_at'] ?? '' ) ],
            ],
        ];
    }

    // Resultados de quizzes
    $quiz_results = get_user_meta( $user_id, 'hs_quiz_results', true );
    foreach ( (array) ( is_array( $quiz_results ) ? $quiz_results : [] ) as $quiz_id => $result ) {
        $export[] = [
            'group_id'    => 'hs-quizzes',
            'group_label' => __( 'Resultados de quizzes', 'hubskills-lms' ),
            'item_id'     => 'hs-quiz-' . $quiz_id,
            'data'        => [
                [ 'name' => __( 'Quiz', 'hubskills-lms' ),  'value' => get_the_title( (int) $quiz_id ) ?: ( '#' . $quiz_id ) ],
                [ 'name' => __( 'Resultado', 'hubskills-lms' ), 'value' => wp_json_encode( $result, JSON_UNESCAPED_UNICODE ) ],
            ],
        ];
    }

    // Certificados
    foreach ( array_map( 'intval', (array) ( get_user_meta( $user_id, 'hs_certificates', true ) ?: [] ) ) as $course_id ) {
        $cert_id = function_exists( 'hs_certificate_id' ) ? hs_certificate_id( $user_id, $course_id ) : '';
        $export[] = [
            'group_id'    => 'hs-certificates',
            'group_label' => __( 'Certificados', 'hubskills-lms' ),
            'item_id'     => 'hs-certificate-' . $course_id,
            'data'        => [
                [ 'name' => __( 'Curso', 'hubskills-lms' ),       'value' => get_the_title( $course_id ) ?: ( '#' . $course_id ) ],
                [ 'name' => __( 'Código', 'hubskills-lms' ),      'value' => $cert_id ],
                [ 'name' => __( 'Emitido em', 'hubskills-lms' ),  'value' => (string) get_user_meta( $user_id, 'hs_cert_date_' . $course_id, true ) ],
            ],
        ];
    }

    // Gamificação
    $export[] = [
        'group_id'    => 'hs-gamification',
        'group_label' => __( 'Gamificação', 'hubskills-lms' ),
        'item_id'     => 'hs-gamification-' . $user_id,
        'data'        => [
            [ 'name' => __( 'XP', 'hubskills-lms' ),                'value' => (string) (int) get_user_meta( $user_id, 'hs_xp', true ) ],
            [ 'name' => __( 'Sequência (dias)', 'hubskills-lms' ),  'value' => (string) (int) get_user_meta( $user_id, 'hs_streak', true ) ],
            [ 'name' => __( 'Conquistas', 'hubskills-lms' ),        'value' => implode( ', ', (array) ( get_user_meta( $user_id, 'hs_badges', true ) ?: [] ) ) ],
            [ 'name' => __( 'Última atividade', 'hubskills-lms' ),  'value' => (string) get_user_meta( $user_id, 'hs_last_active', true ) ],
        ],
    ];

    // Anotações de aulas
    $notes = get_user_meta( $user_id, 'hs_lesson_notes', true );
    if ( is_array( $notes ) && $notes ) {
        $export[] = [
            'group_id'    => 'hs-notes',
            'group_label' => __( 'Anotações de aulas', 'hubskills-lms' ),
            'item_id'     => 'hs-notes-' . $user_id,
            'data'        => [
                [ 'name' => __( 'Anotações', 'hubskills-lms' ), 'value' => wp_json_encode( $notes, JSON_UNESCAPED_UNICODE ) ],
            ],
        ];
    }

    // Candidatura/perfil de instrutor
    foreach ( [ 'hs_instructor_application' => __( 'Candidatura de instrutor', 'hubskills-lms' ), 'hs_instructor_profile' => __( 'Perfil de instrutor', 'hubskills-lms' ) ] as $key => $label ) {
        $value = get_user_meta( $user_id, $key, true );
        if ( $value ) {
            $export[] = [
                'group_id'    => 'hs-instructor',
                'group_label' => __( 'Dados de instrutor', 'hubskills-lms' ),
                'item_id'     => 'hs-' . $key,
                'data'        => [ [ 'name' => $label, 'value' => wp_json_encode( $value, JSON_UNESCAPED_UNICODE ) ] ],
            ];
        }
    }

    // Pedidos
    if ( function_exists( 'hs_lms_user_orders' ) ) {
        foreach ( hs_lms_user_orders( $user_id ) as $order ) {
            $export[] = [
                'group_id'    => 'hs-orders',
                'group_label' => __( 'Pedidos', 'hubskills-lms' ),
                'item_id'     => 'hs-order-' . $order['id'],
                'data'        => [
                    [ 'name' => __( 'Pedido', 'hubskills-lms' ),  'value' => '#' . $order['id'] ],
                    [ 'name' => __( 'Curso', 'hubskills-lms' ),   'value' => get_the_title( (int) $order['course_id'] ) ],
                    [ 'name' => __( 'Valor', 'hubskills-lms' ),   'value' => 'R$ ' . number_format( (float) $order['amount'], 2, ',', '.' ) ],
                    [ 'name' => __( 'Status', 'hubskills-lms' ),  'value' => $order['status'] ],
                    [ 'name' => __( 'Data', 'hubskills-lms' ),    'value' => $order['created_at'] ],
                ],
            ];
        }
    }

    return [ 'data' => $export, 'done' => true ];
}

// ── Apagador de dados pessoais ────────────────────────────
add_filter( 'wp_privacy_personal_data_erasers', 'hs_lms_register_data_eraser' );
function hs_lms_register_data_eraser( array $erasers ): array {
    $erasers['hubskills-lms'] = [
        'eraser_friendly_name' => __( 'HubSkills LMS', 'hubskills-lms' ),
        'callback'             => 'hs_lms_personal_data_eraser',
    ];
    return $erasers;
}

function hs_lms_personal_data_eraser( string $email, int $page = 1 ): array {
    $user = get_user_by( 'email', $email );
    if ( ! $user ) {
        return [ 'items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true ];
    }

    $user_id = (int) $user->ID;
    $removed = false;

    // Dados de aprendizado e gamificação: apagados
    $erasable = [
        'hs_enrollments', 'hs_quiz_results', 'hs_lesson_notes', 'hs_xp', 'hs_streak',
        'hs_badges', 'hs_last_active', 'hs_activity', 'hs_weekly_minutes',
        'hs_instructor_application', 'hs_instructor_profile', 'hs_lms_temp_assets',
        'hs_suspend_reason', 'hs_suspended_at',
    ];
    foreach ( $erasable as $key ) {
        if ( metadata_exists( 'user', $user_id, $key ) ) {
            delete_user_meta( $user_id, $key );
            $removed = true;
        }
    }

    if ( function_exists( 'hs_audit_log' ) ) {
        hs_audit_log( get_current_user_id() ?: $user_id, 'privacy_erase', 'Dados LGPD apagados para usuário #' . $user_id );
    }

    // Retidos por obrigação legal / legítimo interesse
    $messages = [
        __( 'Pedidos de compra foram retidos por obrigação fiscal (art. 16, I, LGPD).', 'hubskills-lms' ),
        __( 'Certificados emitidos foram retidos para garantir a verificação pública de autenticidade.', 'hubskills-lms' ),
    ];

    return [
        'items_removed'  => $removed,
        'items_retained' => true,
        'messages'       => $messages,
        'done'           => true,
    ];
}

// ── Texto sugerido para a Política de Privacidade ─────────
add_action( 'admin_init', 'hs_lms_add_privacy_policy_content' );
function hs_lms_add_privacy_policy_content() {
    if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) return;
    $content = '<h2>' . __( 'HubSkills LMS', 'hubskills-lms' ) . '</h2>'
        . '<p>' . __( 'Ao criar uma conta e se matricular em cursos, coletamos e tratamos: nome, e-mail, matrículas, progresso de aulas, resultados de quizzes, certificados emitidos, dados de gamificação (XP, conquistas) e registros de pedidos/pagamentos.', 'hubskills-lms' ) . '</p>'
        . '<p>' . __( 'Finalidades: prestação do serviço educacional, emissão e verificação de certificados, processamento de pagamentos e cumprimento de obrigações legais.', 'hubskills-lms' ) . '</p>'
        . '<p>' . __( 'Você pode solicitar a exportação ou a exclusão dos seus dados pessoais a qualquer momento pelos canais de contato do site. Dados de pedidos são retidos pelo prazo fiscal legal; certificados são retidos para verificação pública de autenticidade.', 'hubskills-lms' ) . '</p>'
        . '<p>' . __( 'Pagamentos são processados por gateways terceiros (Stripe, PayPal, Mercado Pago, PagSeguro); consulte também as políticas de privacidade desses serviços.', 'hubskills-lms' ) . '</p>';
    wp_add_privacy_policy_content( 'HubSkills LMS', wp_kses_post( $content ) );
}
