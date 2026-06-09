<?php
/**
 * Bootstrap de testes — shims mínimos do WordPress para testar a lógica
 * do plugin sem uma instalação WP. Execute: php tests/run-tests.php
 */
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'MB_IN_BYTES', 1048576 );

$GLOBALS['__options']   = [];
$GLOBALS['__post_meta'] = [];
$GLOBALS['__user_meta'] = [];

// ── Hooks (no-op) ─────────────────────────────────────────
function add_action( ...$a ) {}
function add_filter( ...$a ) {}
function add_shortcode( ...$a ) {}
function remove_action( ...$a ) {}
function do_action( ...$a ) {}
function apply_filters( $tag, $value, ...$rest ) { return $value; }
function register_rest_route( ...$a ) {}
function register_post_type( ...$a ) {}
function register_activation_hook( ...$a ) {}

// ── i18n / escape ─────────────────────────────────────────
function __( $t, $d = null ) { return $t; }
function _e( $t, $d = null ) { echo $t; }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $t, $d = null ) { return esc_html( $t ); }
function esc_attr( $t ) { return esc_html( $t ); }
function esc_textarea( $t ) { return esc_html( $t ); }
function esc_url( $t ) { return (string) $t; }
function esc_url_raw( $t ) { return trim( (string) $t ); }
function wp_kses( $t, $allowed = [] ) { return $t; }
function wp_kses_post( $t ) { return $t; }

// ── Sanitização ───────────────────────────────────────────
function sanitize_text_field( $str ) {
    $str = (string) ( is_scalar( $str ) ? $str : '' );
    $str = wp_strip_all_tags_shim( $str );
    return trim( preg_replace( '/[\r\n\t ]+/', ' ', $str ) );
}
function sanitize_textarea_field( $str ) {
    return trim( wp_strip_all_tags_shim( (string) ( is_scalar( $str ) ? $str : '' ) ) );
}
function wp_strip_all_tags_shim( $str ) {
    return preg_replace( '/<[^>]*>/', '', preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $str ) );
}
function sanitize_key( $key ) {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) ( is_scalar( $key ) ? $key : '' ) ) );
}
function sanitize_file_name( $name ) { return preg_replace( '/[^A-Za-z0-9._\-]/', '-', (string) $name ); }
function sanitize_email( $email ) { return (string) filter_var( (string) $email, FILTER_SANITIZE_EMAIL ); }
function absint( $n ) { return abs( (int) $n ); }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( (string) $v ); }
function wp_json_encode( $v, $flags = 0 ) { return json_encode( $v, $flags ); }
function wp_trim_words( $text, $num = 55, $more = '…' ) {
    $words = preg_split( '/\s+/', trim( (string) $text ) );
    if ( count( $words ) <= $num ) return implode( ' ', $words );
    return implode( ' ', array_slice( $words, 0, $num ) ) . $more;
}

// ── URLs ──────────────────────────────────────────────────
function wp_parse_url( $url, $component = -1 ) { return $component === -1 ? parse_url( (string) $url ) : parse_url( (string) $url, $component ); }
function set_url_scheme( $url, $scheme = 'http' ) { return preg_replace( '#^[a-z]+://#i', $scheme . '://', (string) $url ); }
function trailingslashit( $p ) { return rtrim( (string) $p, "/\\" ) . '/'; }
function untrailingslashit( $p ) { return rtrim( (string) $p, "/\\" ); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
function add_query_arg( $args, $url = '' ) {
    $sep = strpos( $url, '?' ) === false ? '?' : '&';
    return $url . $sep . http_build_query( (array) $args );
}
function wp_normalize_path( $p ) { return str_replace( '\\', '/', (string) $p ); }
function wp_get_upload_dir() {
    $dir = sys_get_temp_dir() . '/hs-test-uploads';
    if ( ! is_dir( $dir ) ) mkdir( $dir, 0777, true );
    return [ 'basedir' => $dir, 'baseurl' => 'https://example.test/wp-content/uploads', 'error' => false ];
}

// ── Opções e metadados (em memória) ───────────────────────
function get_option( $key, $default = false ) { return $GLOBALS['__options'][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['__options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['__options'][ $key ] ); return true; }
function get_post_meta( $id, $key = '', $single = false ) {
    $v = $GLOBALS['__post_meta'][ $id ][ $key ] ?? '';
    return $single ? $v : ( $v === '' ? [] : [ $v ] );
}
function update_post_meta( $id, $key, $value ) { $GLOBALS['__post_meta'][ $id ][ $key ] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['__post_meta'][ $id ][ $key ] ); return true; }
function get_user_meta( $id, $key = '', $single = false ) {
    $v = $GLOBALS['__user_meta'][ $id ][ $key ] ?? '';
    return $single ? $v : ( $v === '' ? [] : [ $v ] );
}
function update_user_meta( $id, $key, $value ) { $GLOBALS['__user_meta'][ $id ][ $key ] = $value; return true; }
function delete_user_meta( $id, $key ) { unset( $GLOBALS['__user_meta'][ $id ][ $key ] ); return true; }
function metadata_exists( $type, $id, $key ) { return isset( $GLOBALS['__user_meta'][ $id ][ $key ] ); }

// ── Tempo / segurança ─────────────────────────────────────
function current_time( $type ) { return $type === 'timestamp' ? time() : gmdate( 'Y-m-d H:i:s' ); }
function wp_salt( $scheme = 'auth' ) { return 'test-salt-' . $scheme; }
function wp_create_nonce( $action = -1 ) { return substr( md5( 'nonce' . $action ), 0, 10 ); }
function wp_verify_nonce( $nonce, $action = -1 ) { return $nonce === wp_create_nonce( $action ); }
function wp_nonce_url( $url, $action = -1 ) { return add_query_arg( [ '_wpnonce' => wp_create_nonce( $action ) ], $url ); }
function wp_nonce_field( ...$a ) {}
function check_ajax_referer( ...$a ) { return true; }
function wp_generate_uuid4() { return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000, mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff) ); }

// ── Posts / usuários (stubs) ──────────────────────────────
function get_posts( $args = [] ) { return []; }
function get_post( $id = null ) { return null; }
function get_post_type( $id = null ) { return ''; }
function get_post_status( $id = null ) { return 'publish'; }
function get_the_title( $id = 0 ) { return 'Curso #' . (int) $id; }
function get_permalink( $id = 0 ) { return 'https://example.test/?p=' . (int) $id; }
function get_users( $args = [] ) { return []; }
function get_userdata( $id ) { return false; }
function get_user_by( $field, $value ) { return false; }
function wp_get_current_user() { $u = new stdClass(); $u->ID = 0; $u->display_name = 'Test'; $u->user_email = 'test@example.test'; return $u; }
function is_user_logged_in() { return false; }
function get_current_user_id() { return 0; }
function current_user_can( ...$a ) { return false; }
function get_bloginfo( $k = '' ) { return 'HubSkills Test'; }
function get_avatar_url( ...$a ) { return ''; }
function wp_mail( ...$a ) { return true; }
function date_i18n( $format, $ts = null ) { return gmdate( $format, $ts ?? time() ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d, ',', '.' ); }
function status_header( ...$a ) {}
function nocache_headers() {}
function wp_redirect( ...$a ) {}
function wp_login_url( ...$a ) { return ''; }
function load_plugin_textdomain( ...$a ) {}
function plugin_basename( $f ) { return basename( (string) $f ); }
function plugin_dir_path( $f ) { return trailingslashit( dirname( (string) $f ) ); }
function plugin_dir_url( $f ) { return 'https://example.test/wp-content/plugins/' . basename( dirname( (string) $f ) ) . '/'; }

// ── WP_Error ──────────────────────────────────────────────
class WP_Error {
    private $code; private $message; private $data;
    public function __construct( $code = '', $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
    public function add( $code, $message, $data = null ) { if ( ! $this->code ) { $this->code = $code; $this->message = $message; } }
}
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

class WP_Post { public $ID = 0; }
