<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Verify admin AJAX request nonce and capability.
 */
function hs_admin_ajax_check() {
    check_ajax_referer( 'hs_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => __( 'Acesso negado.', 'hubskills-lms' ) ], 403 );
    }
}

// ── Instructor Management ────────────────────────────────────────────────────

add_action( 'wp_ajax_hs_admin_approve_instructor', 'hs_ajax_admin_approve_instructor' );
function hs_ajax_admin_approve_instructor() {
    hs_admin_ajax_check();
    $user_id = (int)( $_POST['user_id'] ?? 0 );

    $result = hs_approve_instructor( $user_id, get_current_user_id() );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }
    wp_send_json_success( [ 'message' => __( 'Instrutor aprovado!', 'hubskills-lms' ) ] );
}

add_action( 'wp_ajax_hs_admin_reject_instructor', 'hs_ajax_admin_reject_instructor' );
function hs_ajax_admin_reject_instructor() {
    hs_admin_ajax_check();
    $user_id = (int)( $_POST['user_id'] ?? 0 );
    $reason  = sanitize_textarea_field( $_POST['reason'] ?? '' );

    $result = hs_reject_instructor( $user_id, $reason, get_current_user_id() );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }
    wp_send_json_success( [ 'message' => __( 'Candidatura rejeitada.', 'hubskills-lms' ) ] );
}

add_action( 'wp_ajax_hs_admin_save_commission', 'hs_ajax_admin_save_commission' );
function hs_ajax_admin_save_commission() {
    hs_admin_ajax_check();
    $user_id    = (int)( $_POST['user_id'] ?? 0 );
    $commission = max( 1, min( 99, (int)( $_POST['commission'] ?? 70 ) ) );

    update_user_meta( $user_id, 'hs_instructor_commission_rate', $commission );
    hs_audit_log( get_current_user_id(), 'update_commission', "Comissão do instrutor #{$user_id} alterada para {$commission}%" );
    wp_send_json_success( [ 'message' => __( 'Comissão atualizada!', 'hubskills-lms' ) ] );
}

// ── User Management ──────────────────────────────────────────────────────────

add_action( 'wp_ajax_hs_admin_suspend_user', 'hs_ajax_admin_suspend_user' );
function hs_ajax_admin_suspend_user() {
    hs_admin_ajax_check();
    $user_id = (int)( $_POST['user_id'] ?? 0 );
    $reason  = sanitize_textarea_field( $_POST['reason'] ?? '' );

    hs_suspend_user( $user_id, $reason, get_current_user_id() );
    wp_send_json_success( [ 'message' => __( 'Usuário suspenso.', 'hubskills-lms' ) ] );
}

add_action( 'wp_ajax_hs_admin_unsuspend_user', 'hs_ajax_admin_unsuspend_user' );
function hs_ajax_admin_unsuspend_user() {
    hs_admin_ajax_check();
    $user_id = (int)( $_POST['user_id'] ?? 0 );

    hs_unsuspend_user( $user_id, get_current_user_id() );
    wp_send_json_success( [ 'message' => __( 'Usuário reativado.', 'hubskills-lms' ) ] );
}

add_action( 'wp_ajax_hs_admin_reset_xp', 'hs_ajax_admin_reset_xp' );
function hs_ajax_admin_reset_xp() {
    hs_admin_ajax_check();
    $user_id = (int)( $_POST['user_id'] ?? 0 );

    update_user_meta( $user_id, 'hs_xp', 0 );
    hs_audit_log( get_current_user_id(), 'reset_xp', "XP do usuário #{$user_id} zerado." );
    wp_send_json_success( [ 'message' => __( 'XP zerado.', 'hubskills-lms' ) ] );
}

// ── Security ─────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_hs_admin_unblock_ip', 'hs_ajax_admin_unblock_ip' );
function hs_ajax_admin_unblock_ip() {
    hs_admin_ajax_check();
    $ip = sanitize_text_field( $_POST['ip'] ?? '' );

    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        wp_send_json_error( [ 'message' => __( 'IP inválido.', 'hubskills-lms' ) ] );
    }

    hs_unblock_ip( $ip, get_current_user_id() );
    wp_send_json_success( [ 'message' => sprintf( __( 'IP %s desbloqueado.', 'hubskills-lms' ), $ip ) ] );
}

// ── Orders ───────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_hs_admin_refund_order', 'hs_ajax_admin_refund_order' );
function hs_ajax_admin_refund_order() {
    hs_admin_ajax_check();
    $order_id = (int)( $_POST['order_id'] ?? 0 );

    $order = hs_get_order( $order_id );
    if ( ! $order ) {
        wp_send_json_error( [ 'message' => __( 'Pedido não encontrado.', 'hubskills-lms' ) ] );
    }

    $result = hs_refund_order( $order_id );
    hs_audit_log( get_current_user_id(), 'refund_order', "Pedido #{$order_id} reembolsado pelo admin." );

    wp_send_json_success( [ 'message' => __( 'Reembolso processado.', 'hubskills-lms' ) ] );
}

add_action( 'wp_ajax_hs_admin_mark_order_paid', 'hs_ajax_admin_mark_order_paid' );
function hs_ajax_admin_mark_order_paid() {
    hs_admin_ajax_check();
    $order_id = (int)( $_POST['order_id'] ?? 0 );

    $order = hs_get_order( $order_id );
    if ( ! $order ) {
        wp_send_json_error( [ 'message' => __( 'Pedido não encontrado.', 'hubskills-lms' ) ] );
    }

    if ( $order['status'] === 'paid' ) {
        wp_send_json_success( [ 'message' => __( 'Pedido já estava pago.', 'hubskills-lms' ) ] );
    }

    if ( ! hs_complete_order( $order_id ) ) {
        wp_send_json_error( [ 'message' => __( 'Não foi possível confirmar o pagamento.', 'hubskills-lms' ) ] );
    }

    if ( function_exists( 'hs_audit_log' ) ) {
        hs_audit_log( get_current_user_id(), 'mark_order_paid', "Pedido #{$order_id} marcado como pago pelo admin." );
    }

    wp_send_json_success( [ 'message' => __( 'Pagamento confirmado e matrícula liberada.', 'hubskills-lms' ) ] );
}

// ── CSV Export ───────────────────────────────────────────────────────────────

add_action( 'wp_ajax_hs_export_students_csv', 'hs_ajax_export_students_csv' );
function hs_ajax_export_students_csv() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Acesso negado.' );
    check_admin_referer( 'hs_admin_nonce', 'nonce' );

    $users = get_users( [ 'role' => 'hs_student', 'number' => -1 ] );

    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="alunos-hubskills-' . date('Y-m-d') . '.csv"' );
    header( 'Cache-Control: no-cache, no-store, must-revalidate' );

    $out = fopen( 'php://output', 'w' );
    // UTF-8 BOM for Excel
    fputs( $out, "\xEF\xBB\xBF" );

    fputcsv( $out, [ 'ID', 'Nome', 'Email', 'Cursos Matriculados', 'XP', 'Nível', 'Cadastro' ] );

    foreach ( $users as $u ) {
        $enrollments = (array)( get_user_meta( $u->ID, 'hs_enrollments', true ) ?: [] );
        $xp          = (int) get_user_meta( $u->ID, 'hs_xp', true );
        $level       = hs_get_level( $xp );
        fputcsv( $out, [
            $u->ID,
            $u->display_name,
            $u->user_email,
            count( $enrollments ),
            $xp,
            $level,
            $u->user_registered,
        ] );
    }

    fclose( $out );
    exit;
}

add_action( 'wp_ajax_hs_export_orders_csv', 'hs_ajax_export_orders_csv' );
function hs_ajax_export_orders_csv() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Acesso negado.' );
    check_admin_referer( 'hs_admin_nonce', 'nonce' );

    $orders = get_posts([
        'post_type'      => 'hs_order',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ]);

    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="pedidos-hubskills-' . date('Y-m-d') . '.csv"' );
    header( 'Cache-Control: no-cache, no-store, must-revalidate' );

    $out = fopen( 'php://output', 'w' );
    fputs( $out, "\xEF\xBB\xBF" );
    fputcsv( $out, [ 'Pedido #', 'Aluno', 'Email', 'Curso', 'Valor', 'Gateway', 'Status', 'Data' ] );

    foreach ( $orders as $order ) {
        $uid    = (int) get_post_meta( $order->ID, '_hs_order_user_id',   true );
        $cid    = (int) get_post_meta( $order->ID, '_hs_order_course_id', true );
        $amount = (float) get_post_meta( $order->ID, '_hs_order_amount',  true );
        $user   = get_userdata( $uid );

        fputcsv( $out, [
            $order->ID,
            $user ? $user->display_name : '#' . $uid,
            $user ? $user->user_email : '',
            get_the_title( $cid ),
            number_format( $amount, 2, ',', '.' ),
            get_post_meta( $order->ID, '_hs_order_gateway',    true ),
            get_post_meta( $order->ID, '_hs_order_status',     true ),
            get_post_meta( $order->ID, '_hs_order_created_at', true ),
        ] );
    }

    fclose( $out );
    exit;
}
