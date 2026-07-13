<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Security Headers ────────────────────────────────────────────────────────

add_action( 'send_headers', 'hs_send_security_headers' );
function hs_send_security_headers() {
    if ( ! is_admin() ) {
        header( 'X-Frame-Options: SAMEORIGIN' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-XSS-Protection: 1; mode=block' );
        header( 'Referrer-Policy: strict-origin-when-cross-origin' );
        header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
    }
}

add_filter( 'xmlrpc_enabled', '__return_false' );

// ── Audit Log ───────────────────────────────────────────────────────────────

/**
 * Create audit log table on activation.
 */
function hs_create_audit_table() {
    global $wpdb;
    $table   = $wpdb->prefix . 'hs_audit_log';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id     BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        action      VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        ip_address  VARCHAR(45) NOT NULL DEFAULT '',
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY action (action),
        KEY created_at (created_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

add_action( 'hs_lms_activate', 'hs_create_audit_table' );

/**
 * Write an entry to the audit log.
 */
function hs_audit_log( int $user_id, string $action, string $description ) {
    global $wpdb;

    $wpdb->insert(
        $wpdb->prefix . 'hs_audit_log',
        [
            'user_id'     => $user_id,
            'action'      => sanitize_key( $action ),
            'description' => sanitize_text_field( $description ),
            'ip_address'  => hs_get_client_ip(),
            'created_at'  => current_time( 'mysql' ),
        ],
        [ '%d', '%s', '%s', '%s', '%s' ]
    );
}

/**
 * Get audit log entries.
 */
function hs_get_audit_log( int $limit = 50, int $offset = 0, string $action = '' ) {
    global $wpdb;
    $table = $wpdb->prefix . 'hs_audit_log';

    $where = $action ? $wpdb->prepare( 'WHERE action = %s', $action ) : '';

    return $wpdb->get_results( $wpdb->prepare(
        "SELECT al.*, u.display_name
         FROM {$table} al
         LEFT JOIN {$wpdb->users} u ON u.ID = al.user_id
         {$where}
         ORDER BY al.created_at DESC
         LIMIT %d OFFSET %d",
        $limit, $offset
    ), ARRAY_A );
}

// ── Rate Limiting ───────────────────────────────────────────────────────────

/**
 * Check and increment rate limit counter. Returns true if limit exceeded.
 */
function hs_rate_limit( string $key, int $max = 10, int $window = 60 ) {
    $transient_key = 'hs_rl_' . md5( $key );
    $count = (int)( get_transient( $transient_key ) ?: 0 );

    if ( $count >= $max ) {
        return true;
    }

    if ( $count === 0 ) {
        set_transient( $transient_key, 1, $window );
    } else {
        set_transient( $transient_key, $count + 1, $window );
    }

    return false;
}

// ── Login Protection ────────────────────────────────────────────────────────

add_action( 'wp_login_failed', 'hs_track_login_failure' );
function hs_track_login_failure( string $username ) {
    $ip       = hs_get_client_ip();
    $max      = max( 3, (int) get_option( 'hs_max_login_attempts', 5 ) );
    $duration = max( 5, (int) get_option( 'hs_lockout_duration', 60 ) ) * MINUTE_IN_SECONDS;
    $key      = 'hs_login_fail_' . md5( $ip );
    $failures = (int)( get_transient( $key ) ?: 0 ) + 1;
    set_transient( $key, $failures, $duration );

    if ( $failures >= $max ) {
        set_transient( 'hs_blocked_ip_' . md5( $ip ), 1, $duration );
        $blocked = (array) get_option( 'hs_blocked_ips_log', [] );
        $blocked[ $ip ] = [
            'blocked_at' => current_time( 'mysql' ),
            'failures'   => $failures,
            'expires_at' => gmdate( 'Y-m-d H:i:s', time() + $duration ),
        ];
        update_option( 'hs_blocked_ips_log', $blocked, false );
        hs_audit_log( 0, 'ip_blocked', sprintf( 'IP bloqueado após %d falhas: %s', $failures, $ip ) );
    }
}

add_filter( 'authenticate', 'hs_check_ip_block', 1, 3 );
function hs_check_ip_block( $user, $username, $password ) {
    $ip = hs_get_client_ip();
    if ( get_transient( 'hs_blocked_ip_' . md5( $ip ) ) ) {
        return new WP_Error( 'ip_blocked', __( 'Seu IP foi temporariamente bloqueado. Tente novamente em 1 hora.', 'hubskills-lms' ) );
    }
    return $user;
}

/**
 * Manually unblock an IP address.
 */
function hs_unblock_ip( string $ip, int $admin_id = 0 ) {
    delete_transient( 'hs_blocked_ip_' . md5( $ip ) );
    delete_transient( 'hs_login_fail_' . md5( $ip ) );
    $blocked = (array) get_option( 'hs_blocked_ips_log', [] );
    unset( $blocked[ $ip ] );
    update_option( 'hs_blocked_ips_log', $blocked, false );
    hs_audit_log( $admin_id, 'ip_unblocked', 'IP desbloqueado: ' . $ip );
}

/**
 * Get list of currently blocked IPs (stored as options for persistence).
 */
function hs_get_blocked_ips() {
    return (array)( get_option( 'hs_blocked_ips_log', [] ) );
}

// Track blocks in a persistent list for the admin panel
add_action( 'hs_lms_activate', function() {
    if ( ! get_option( 'hs_blocked_ips_log' ) ) {
        add_option( 'hs_blocked_ips_log', [] );
    }
} );

// ── IP Helper ───────────────────────────────────────────────────────────────

function hs_get_client_ip() {
    $remote_addr = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
    if ( filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
        return $remote_addr;
    }

    if ( apply_filters( 'hs_trust_proxy_headers', false ) ) {
        foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR' ] as $key ) {
            if ( empty( $_SERVER[ $key ] ) ) {
                continue;
            }

            $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
            $ip = trim( strtok( $ip, ',' ) );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }
    }

    return '0.0.0.0';
}

// ── User Account Management ─────────────────────────────────────────────────

/**
 * Suspend a user account.
 */
function hs_suspend_user( int $user_id, string $reason = '', int $admin_id = 0 ) {
    update_user_meta( $user_id, 'hs_account_status', 'suspended' );
    update_user_meta( $user_id, 'hs_suspend_reason', sanitize_textarea_field( $reason ) );
    update_user_meta( $user_id, 'hs_suspended_at', current_time( 'mysql' ) );
    hs_audit_log( $admin_id, 'suspend_user', 'Usuário #' . $user_id . ' suspenso. Motivo: ' . $reason );
}

/**
 * Reactivate a suspended user account.
 */
function hs_unsuspend_user( int $user_id, int $admin_id = 0 ) {
    update_user_meta( $user_id, 'hs_account_status', 'active' );
    delete_user_meta( $user_id, 'hs_suspend_reason' );
    hs_audit_log( $admin_id, 'unsuspend_user', 'Usuário #' . $user_id . ' reativado.' );
}

/**
 * Check if user is suspended and block login.
 */
add_filter( 'authenticate', 'hs_check_suspended_user', 30, 3 );
function hs_check_suspended_user( $user, $username, $password ) {
    if ( ! is_wp_error( $user ) && $user instanceof WP_User ) {
        $status = get_user_meta( $user->ID, 'hs_account_status', true );
        if ( $status === 'suspended' ) {
            $reason = get_user_meta( $user->ID, 'hs_suspend_reason', true );
            return new WP_Error( 'account_suspended',
                __( 'Sua conta foi suspensa.', 'hubskills-lms' )
                . ( $reason ? ' ' . __( 'Motivo:', 'hubskills-lms' ) . ' ' . esc_html( $reason ) : '' )
            );
        }
    }
    return $user;
}

// ── Encryption for Sensitive Keys ───────────────────────────────────────────

/**
 * Encrypt a string using OpenSSL AES-256-CBC.
 */
function hs_encrypt( string $value ) {
    if ( $value === '' || substr( $value, 0, 7 ) === 'enc:v1:' || ! function_exists( 'openssl_encrypt' ) ) return $value;

    $key = hs_get_encryption_key();
    $iv  = random_bytes( 16 );
    $enc = openssl_encrypt( $value, 'AES-256-CBC', $key, 0, $iv );

    if ( $enc === false ) return $value;

    $mac = hash_hmac( 'sha256', $iv . $enc, $key, true );
    return 'enc:v1:' . base64_encode( $iv . $mac . $enc );
}

/**
 * Decrypt a string encrypted with hs_encrypt().
 */
function hs_decrypt( string $value ) {
    if ( substr( $value, 0, 7 ) !== 'enc:v1:' || ! function_exists( 'openssl_decrypt' ) ) return $value;

    $key     = hs_get_encryption_key();
    $decoded = base64_decode( substr( $value, 7 ), true );
    if ( ! $decoded || strlen( $decoded ) < 49 ) return $value;

    $iv  = substr( $decoded, 0, 16 );
    $mac = substr( $decoded, 16, 32 );
    $enc = substr( $decoded, 48 );
    $expected = hash_hmac( 'sha256', $iv . $enc, $key, true );

    if ( ! hash_equals( $expected, $mac ) ) return $value;

    $dec = openssl_decrypt( $enc, 'AES-256-CBC', $key, 0, $iv );

    return $dec !== false ? $dec : $value;
}

function hs_get_encryption_key() {
    $stored = get_option( 'hs_encryption_key' );
    if ( $stored ) return hash( 'sha256', $stored . wp_salt( 'auth' ), true );

    $key = wp_generate_password( 32, true, true );
    add_option( 'hs_encryption_key', $key );
    return hash( 'sha256', $key . wp_salt( 'auth' ), true );
}

// ── Nonce Verification Helper ────────────────────────────────────────────────

/**
 * Verify AJAX request nonce and login status. Dies on failure.
 */
function hs_verify_request( string $action = 'hubskills_nonce', bool $require_login = true ) {
    $nonce = sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ?? '' ) );

    if ( ! wp_verify_nonce( $nonce, $action ) ) {
        wp_send_json_error( [ 'message' => __( 'Requisição inválida.', 'hubskills-lms' ) ], 403 );
    }

    if ( $require_login && ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => __( 'Você precisa estar logado.', 'hubskills-lms' ) ], 401 );
    }
}

// ── Bloqueio do painel WordPress para alunos e instrutores ─────────────────
/**
 * Alunos e instrutores vivem 100% no dashboard frontend. Qualquer tentativa
 * de acessar o wp-admin redireciona para o dashboard. AJAX continua liberado
 * (o frontend depende do admin-ajax.php). Administradores não são afetados.
 */
function hs_lms_user_is_dashboard_only( int $user_id ): bool {
    if ( ! $user_id ) return false;
    if ( user_can( $user_id, 'manage_options' ) ) return false;
    if ( function_exists( 'hs_is_hs_admin' ) && hs_is_hs_admin( $user_id ) ) return false;
    $role = function_exists( 'hs_get_user_role' ) ? hs_get_user_role( $user_id ) : false;
    return in_array( $role, [ 'hs_student', 'hs_instructor' ], true );
}

/** Destino do redirecionamento conforme o papel (instrutor → painel; aluno → dashboard). */
function hs_lms_dashboard_only_destination( int $uid ): string {
    return ( function_exists( 'hs_is_instructor' ) && hs_is_instructor( $uid ) )
        ? home_url( '/painel-instrutor/' )
        : home_url( '/dashboard/' );
}

add_action( 'admin_init', 'hs_lms_block_wp_admin', 0 );
function hs_lms_block_wp_admin() {
    if ( wp_doing_ajax() ) return;
    $uid = get_current_user_id();
    if ( ! hs_lms_user_is_dashboard_only( $uid ) ) return;
    wp_safe_redirect( hs_lms_dashboard_only_destination( $uid ) );
    exit;
}

// Converte qualquer "Sem permissão" (403) do wp-admin em redirecionamento limpo
add_action( 'admin_page_access_denied', 'hs_lms_block_wp_admin_denied' );
function hs_lms_block_wp_admin_denied() {
    $uid = get_current_user_id();
    if ( ! hs_lms_user_is_dashboard_only( $uid ) ) return;
    wp_safe_redirect( hs_lms_dashboard_only_destination( $uid ) );
    exit;
}

// Esconde a barra de admin (que tem links para o wp-admin)
add_filter( 'show_admin_bar', 'hs_lms_hide_admin_bar' );
function hs_lms_hide_admin_bar( $show ) {
    if ( hs_lms_user_is_dashboard_only( get_current_user_id() ) ) return false;
    return $show;
}

// Após o login, aluno/instrutor vai direto para o dashboard
add_filter( 'login_redirect', 'hs_lms_login_redirect', 10, 3 );
function hs_lms_login_redirect( $redirect_to, $requested, $user ) {
    if ( $user instanceof WP_User && hs_lms_user_is_dashboard_only( (int) $user->ID ) ) {
        return home_url( '/dashboard/' );
    }
    return $redirect_to;
}

// ── Anti-bot: honeypot + tempo mínimo nos formulários públicos ─────────────
/**
 * Campos do honeypot: "hs_website" deve ficar vazio (bots preenchem) e
 * "hs_form_ts" carrega o timestamp de render (envio em menos de 3s = bot).
 */
function hs_lms_honeypot_fields(): void {
    echo '<p style="position:absolute;left:-9999px;opacity:0" aria-hidden="true">';
    echo '<label>Website<input type="text" name="hs_website" value="" tabindex="-1" autocomplete="off"></label>';
    echo '</p>';
    echo '<input type="hidden" name="hs_form_ts" value="' . esc_attr( (string) time() ) . '">';
}

function hs_lms_honeypot_is_bot( array $data ): bool {
    if ( ! empty( $data['hs_website'] ) ) return true;
    $ts = absint( $data['hs_form_ts'] ?? 0 );
    if ( $ts && ( time() - $ts ) < 3 ) return true;
    return false;
}

// Registro padrão do WordPress
add_action( 'register_form', 'hs_lms_honeypot_fields' );
add_filter( 'registration_errors', 'hs_lms_check_registration_honeypot', 10, 1 );
function hs_lms_check_registration_honeypot( $errors ) {
    if ( hs_lms_honeypot_is_bot( $_POST ) ) {
        $errors->add( 'hs_bot', __( 'Não foi possível validar o envio. Tente novamente.', 'hubskills-lms' ) );
        if ( function_exists( 'hs_audit_log' ) ) hs_audit_log( 0, 'bot_blocked', 'Registro bloqueado pelo honeypot' );
    }
    return $errors;
}

// ── Política de senha mínima ────────────────────────────────────────────────
/**
 * Mínimo de 8 caracteres com pelo menos uma letra e um número.
 * Aplica na definição/reset de senha (fluxo padrão do WP).
 */
function hs_lms_password_is_strong( string $password ): bool {
    return strlen( $password ) >= 8
        && preg_match( '/[A-Za-z]/', $password )
        && preg_match( '/\d/', $password );
}

add_action( 'validate_password_reset', 'hs_lms_enforce_password_policy', 10, 2 );
function hs_lms_enforce_password_policy( $errors, $user ) {
    $password = (string) ( $_POST['pass1'] ?? '' );
    if ( $password !== '' && ! hs_lms_password_is_strong( $password ) ) {
        $errors->add( 'hs_weak_password', __( '<strong>Erro:</strong> a senha precisa ter ao menos 8 caracteres, com letras e números.', 'hubskills-lms' ) );
    }
}

// ── Rate limit nos endpoints REST públicos ──────────────────────────────────
/**
 * 60 requisições por minuto por IP nos endpoints públicos hubskills/v1.
 * Usuários logados não são limitados.
 */
add_filter( 'rest_pre_dispatch', 'hs_lms_rest_rate_limit', 10, 3 );
function hs_lms_rest_rate_limit( $result, $server, $request ) {
    if ( $result !== null ) return $result;
    if ( is_user_logged_in() ) return $result;

    $route = (string) $request->get_route();
    if ( strpos( $route, '/hubskills/v1' ) !== 0 ) return $result;

    $ip  = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
    $key = 'hs_rest_rl_' . md5( $ip );
    $count = (int) get_transient( $key );
    $limit = (int) apply_filters( 'hs_lms_rest_rate_limit', 60 );

    if ( $count >= $limit ) {
        return new WP_Error( 'hs_rate_limited', __( 'Muitas requisições. Aguarde um instante.', 'hubskills-lms' ), [ 'status' => 429 ] );
    }
    set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

    return $result;
}

// ── Headers de segurança ─────────────────────────────────────────────────────
add_action( 'send_headers', 'hs_lms_security_headers' );
function hs_lms_security_headers() {
    if ( headers_sent() ) return;
    header( 'X-Frame-Options: SAMEORIGIN' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
}

// ── Retenção do log de auditoria (90 dias) ───────────────────────────────────
add_action( 'init', 'hs_lms_schedule_audit_cleanup' );
function hs_lms_schedule_audit_cleanup() {
    if ( ! wp_next_scheduled( 'hs_lms_audit_cleanup' ) ) {
        wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'hs_lms_audit_cleanup' );
    }
}

add_action( 'hs_lms_audit_cleanup', 'hs_lms_run_audit_cleanup' );
function hs_lms_run_audit_cleanup() {
    global $wpdb;
    $table = $wpdb->prefix . 'hs_audit_log';
    $days  = (int) apply_filters( 'hs_lms_audit_retention_days', 90 );
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$table} WHERE created_at < DATE_SUB( NOW(), INTERVAL %d DAY )",
        $days
    ) );
}
