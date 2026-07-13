<?php
// Teste rápido do sistema de licenças das Extensões (roda fora do WP com stubs).
define( 'ABSPATH', __DIR__ . '/' );
function __( $s, $d = null ) { return $s; }
function esc_html__( $s, $d = null ) { return $s; }
function add_action() {}
function apply_filters( $tag, $value ) { return $value; }
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function update_option( $k, $v ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function current_time() { return date( 'Y-m-d H:i:s' ); }
function get_current_user_id() { return 1; }
function do_action() {}
class WP_Error {
    private $code; private $msg;
    public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->msg = $m; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->msg; }
}
function is_wp_error( $x ) { return $x instanceof WP_Error; }

require dirname( __DIR__ ) . '/hubskills-lms/hubskills-lms/admin/extensions.php';

$pass = 0; $fail = 0;
function check( $label, $cond ) {
    global $pass, $fail;
    if ( $cond ) { $pass++; echo "OK   $label\n"; }
    else { $fail++; echo "FAIL $label\n"; }
}

// 1. Gerar e validar para cada módulo do catálogo
foreach ( array_keys( hs_lms_extensions_catalog() ) as $slug ) {
    $key = hs_lms_generate_license_key( $slug );
    check( "gera/valida $slug ($key)", hs_lms_validate_license( $slug, $key ) === true );
}

// 2. Chave de um módulo não vale para outro
$key_pay = hs_lms_generate_license_key( 'payments-pro' );
check( 'chave de payments-pro recusada em ia-tutor', is_wp_error( hs_lms_validate_license( 'ia-tutor', $key_pay ) ) );

// 3. Chave adulterada é recusada
$bad = substr( $key_pay, 0, -1 ) . ( substr( $key_pay, -1 ) === 'A' ? 'B' : 'A' );
check( 'chave adulterada recusada', is_wp_error( hs_lms_validate_license( 'payments-pro', $bad ) ) );

// 4. Formato inválido / módulo inexistente
check( 'formato inválido recusado', is_wp_error( hs_lms_validate_license( 'payments-pro', 'ABC-123' ) ) );
check( 'módulo inexistente recusado', is_wp_error( hs_lms_validate_license( 'nao-existe', $key_pay ) ) );

// 5. Validação aceita minúsculas/espaços (normalização)
check( 'aceita minúsculas e espaços', hs_lms_validate_license( 'payments-pro', '  ' . strtolower( $key_pay ) . '  ' ) === true );

// 6. Ativar grava option e helper enxerga
hs_lms_activate_extension( 'payments-pro', $key_pay );
check( 'option hs_lms_active_extensions gravada', get_option( 'hs_lms_active_extensions' ) === [ 'payments-pro' ] );
check( 'hs_lms_is_extension_active true', hs_lms_is_extension_active( 'payments-pro' ) === true );
check( 'licença salva com a chave', ( hs_lms_get_licenses()['payments-pro']['key'] ?? '' ) === $key_pay );

// 7. Desativar limpa tudo
hs_lms_deactivate_extension( 'payments-pro' );
check( 'desativação remove da lista ativa', hs_lms_is_extension_active( 'payments-pro' ) === false );
check( 'desativação remove a licença', ! isset( hs_lms_get_licenses()['payments-pro'] ) );

echo "\n$pass passaram, $fail falharam\n";
exit( $fail > 0 ? 1 : 0 );
