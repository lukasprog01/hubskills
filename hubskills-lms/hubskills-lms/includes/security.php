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
