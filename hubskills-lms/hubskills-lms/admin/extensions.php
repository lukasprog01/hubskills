<?php
/**
 * HubSkills LMS — Página de Extensões (módulos premium)
 *
 * Vitrine dos módulos adicionais + sistema de licenças.
 * O botão "Comprar módulo" leva para a página oficial do módulo, onde
 * acontece a compra. Após a compra o cliente recebe o arquivo do módulo
 * e a chave de licença, que é ativada nesta tela.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * URL base da loja de módulos.
 * TODO: trocar para a loja definitiva (ex: https://hubskills.com.br/modulos)
 */
function hs_lms_extensions_store_url(): string {
    return (string) apply_filters( 'hs_lms_extensions_store_url', 'https://fatalweb.unaux.com' );
}

/**
 * Catálogo de módulos premium.
 */
function hs_lms_extensions_catalog(): array {
    return [
        'payments-pro' => [
            'icon'  => '💳',
            'name'  => __( 'Payments Pro', 'hubskills-lms' ),
            'desc'  => __( 'PIX automático com confirmação instantânea, parcelamento no cartão e assinaturas recorrentes.', 'hubskills-lms' ),
            'tag'   => __( 'Mais vendido', 'hubskills-lms' ),
            'color' => '#10B981',
        ],
        'reports-analytics' => [
            'icon'  => '📊',
            'name'  => __( 'Reports & Analytics', 'hubskills-lms' ),
            'desc'  => __( 'Funil de vendas, engajamento aula a aula, taxa de conclusão e exportações em PDF/Excel.', 'hubskills-lms' ),
            'tag'   => '',
            'color' => '#06B6D4',
        ],
        'drip-trilhas' => [
            'icon'  => '💧',
            'name'  => __( 'Drip & Trilhas', 'hubskills-lms' ),
            'desc'  => __( 'Liberação programada de aulas, pré-requisitos entre cursos e trilhas de aprendizado.', 'hubskills-lms' ),
            'tag'   => '',
            'color' => '#3B82F6',
        ],
        'b2b-turmas' => [
            'icon'  => '🏢',
            'name'  => __( 'B2B & Turmas', 'hubskills-lms' ),
            'desc'  => __( 'Venda para empresas: matrículas em lote, gestor de turma e relatórios por equipe.', 'hubskills-lms' ),
            'tag'   => '',
            'color' => '#8B5CF6',
        ],
        'marketplace' => [
            'icon'  => '🛒',
            'name'  => __( 'Marketplace', 'hubskills-lms' ),
            'desc'  => __( 'Split automático de comissões para instrutores, saques e extrato financeiro individual.', 'hubskills-lms' ),
            'tag'   => '',
            'color' => '#F59E0B',
        ],
        'email-automations' => [
            'icon'  => '✉️',
            'name'  => __( 'Email & Automations', 'hubskills-lms' ),
            'desc'  => __( 'Templates de e-mail bonitos, sequências automáticas e integração Mailchimp/Brevo.', 'hubskills-lms' ),
            'tag'   => '',
            'color' => '#EC4899',
        ],
        'ia-tutor' => [
            'icon'  => '🤖',
            'name'  => __( 'IA Tutor', 'hubskills-lms' ),
            'desc'  => __( 'Assistente de dúvidas por IA dentro da aula, treinado no conteúdo do seu curso.', 'hubskills-lms' ),
            'tag'   => __( 'Novo', 'hubskills-lms' ),
            'color' => '#7C3AED',
        ],
        'gamification-pro' => [
            'icon'  => '🎮',
            'name'  => __( 'Gamification Pro', 'hubskills-lms' ),
            'desc'  => __( 'Loja de pontos, missões semanais, ranking entre turmas e recompensas customizadas.', 'hubskills-lms' ),
            'tag'   => '',
            'color' => '#EF4444',
        ],
    ];
}

// ── Sistema de licenças ──────────────────────────────────────────────────────

/**
 * Salt da validação offline. Trocar este valor invalida chaves já emitidas.
 * A validação offline pode ser sobrescrita por checagem remota na loja via
 * filtro `hs_lms_validate_license` (retornar true/false/WP_Error encerra a checagem).
 */
function hs_lms_license_salt(): string {
    return 'hs-lms|fb7d2c91e4a85306|v1';
}

/**
 * Código do módulo usado dentro da chave (slug sem hífens, maiúsculo).
 * Ex.: payments-pro → PAYMENTSPRO
 */
function hs_lms_extension_code( string $slug ): string {
    return strtoupper( str_replace( '-', '', $slug ) );
}

/**
 * Gera uma chave de licença para um módulo.
 * Usada pela loja na emissão (não é chamada no fluxo normal do plugin).
 * Formato: HSLMS-<MODULO>-<SERIAL 8>-<CHECK 6>
 */
function hs_lms_generate_license_key( string $slug ): string {
    $code   = hs_lms_extension_code( $slug );
    $serial = strtoupper( substr( str_replace( [ '+', '/', '=' ], '', base64_encode( random_bytes( 12 ) ) ), 0, 8 ) );
    $check  = strtoupper( substr( hash( 'sha256', $code . '|' . $serial . '|' . hs_lms_license_salt() ), 0, 6 ) );
    return sprintf( 'HSLMS-%s-%s-%s', $code, $serial, $check );
}

/**
 * Valida uma chave de licença para um módulo do catálogo.
 *
 * @return true|WP_Error
 */
function hs_lms_validate_license( string $slug, string $key ) {
    $key = strtoupper( trim( $key ) );

    // Permite validação remota (loja) substituir a checagem offline.
    $remote = apply_filters( 'hs_lms_validate_license', null, $slug, $key );
    if ( $remote === true )  return true;
    if ( $remote === false ) return new WP_Error( 'invalid_license', __( 'Chave de licença recusada pelo servidor de licenças.', 'hubskills-lms' ) );
    if ( is_wp_error( $remote ) ) return $remote;

    if ( ! isset( hs_lms_extensions_catalog()[ $slug ] ) ) {
        return new WP_Error( 'unknown_extension', __( 'Módulo desconhecido.', 'hubskills-lms' ) );
    }
    if ( ! preg_match( '/^HSLMS-([A-Z0-9]+)-([A-Z0-9]{8})-([A-F0-9]{6})$/', $key, $m ) ) {
        return new WP_Error( 'invalid_format', __( 'Formato de chave inválido. Use a chave exatamente como recebida no e-mail de compra.', 'hubskills-lms' ) );
    }
    if ( $m[1] !== hs_lms_extension_code( $slug ) ) {
        return new WP_Error( 'wrong_module', __( 'Esta chave pertence a outro módulo.', 'hubskills-lms' ) );
    }

    $expected = strtoupper( substr( hash( 'sha256', $m[1] . '|' . $m[2] . '|' . hs_lms_license_salt() ), 0, 6 ) );
    if ( ! hash_equals( $expected, $m[3] ) ) {
        return new WP_Error( 'invalid_license', __( 'Chave de licença inválida. Confira se copiou a chave completa.', 'hubskills-lms' ) );
    }

    return true;
}

/**
 * Licenças salvas. [ slug => [ 'key' => ..., 'activated_at' => ..., 'activated_by' => ... ] ]
 */
function hs_lms_get_licenses(): array {
    return (array) get_option( 'hs_lms_licenses', [] );
}

/**
 * Módulos ativos (licenciados).
 */
function hs_lms_active_extensions(): array {
    return (array) apply_filters( 'hs_lms_active_extensions', get_option( 'hs_lms_active_extensions', [] ) );
}

/**
 * Helper para os módulos checarem a própria licença.
 */
function hs_lms_is_extension_active( string $slug ): bool {
    return in_array( $slug, hs_lms_active_extensions(), true );
}

/**
 * Ativa a licença de um módulo (chave já validada).
 */
function hs_lms_activate_extension( string $slug, string $key ): void {
    $licenses = hs_lms_get_licenses();
    $licenses[ $slug ] = [
        'key'          => strtoupper( trim( $key ) ),
        'activated_at' => current_time( 'mysql' ),
        'activated_by' => get_current_user_id(),
    ];
    update_option( 'hs_lms_licenses', $licenses, false );

    $active = (array) get_option( 'hs_lms_active_extensions', [] );
    if ( ! in_array( $slug, $active, true ) ) {
        $active[] = $slug;
        update_option( 'hs_lms_active_extensions', $active, false );
    }

    do_action( 'hs_lms_extension_activated', $slug );
}

/**
 * Desativa a licença de um módulo.
 */
function hs_lms_deactivate_extension( string $slug ): void {
    $licenses = hs_lms_get_licenses();
    unset( $licenses[ $slug ] );
    update_option( 'hs_lms_licenses', $licenses, false );

    $active = array_values( array_diff( (array) get_option( 'hs_lms_active_extensions', [] ), [ $slug ] ) );
    update_option( 'hs_lms_active_extensions', $active, false );

    do_action( 'hs_lms_extension_deactivated', $slug );
}

// ── Handlers (admin-post) ────────────────────────────────────────────────────

add_action( 'admin_post_hs_lms_activate_extension', 'hs_lms_handle_activate_extension' );
function hs_lms_handle_activate_extension() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Acesso negado.', 'hubskills-lms' ) );
    check_admin_referer( 'hs_lms_extension_license' );

    $slug = sanitize_key( $_POST['extension'] ?? '' );
    $key  = sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) );

    $valid = hs_lms_validate_license( $slug, $key );
    if ( is_wp_error( $valid ) ) {
        hs_lms_extensions_redirect( 'error', $valid->get_error_message() );
    }

    hs_lms_activate_extension( $slug, $key );

    if ( function_exists( 'hs_audit_log' ) ) {
        hs_audit_log( get_current_user_id(), 'extension_activated', 'Licença ativada para o módulo ' . $slug );
    }

    $name = hs_lms_extensions_catalog()[ $slug ]['name'] ?? $slug;
    hs_lms_extensions_redirect( 'success', sprintf( __( 'Licença do módulo %s ativada com sucesso!', 'hubskills-lms' ), $name ) );
}

add_action( 'admin_post_hs_lms_deactivate_extension', 'hs_lms_handle_deactivate_extension' );
function hs_lms_handle_deactivate_extension() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Acesso negado.', 'hubskills-lms' ) );
    check_admin_referer( 'hs_lms_extension_license' );

    $slug = sanitize_key( $_POST['extension'] ?? '' );
    hs_lms_deactivate_extension( $slug );

    if ( function_exists( 'hs_audit_log' ) ) {
        hs_audit_log( get_current_user_id(), 'extension_deactivated', 'Licença removida do módulo ' . $slug );
    }

    hs_lms_extensions_redirect( 'success', __( 'Licença removida. O módulo foi desativado.', 'hubskills-lms' ) );
}

/**
 * Redireciona de volta para a página de extensões com um aviso.
 */
function hs_lms_extensions_redirect( string $type, string $message ): void {
    set_transient( 'hs_lms_ext_notice_' . get_current_user_id(), [ 'type' => $type, 'message' => $message ], 60 );
    wp_safe_redirect( admin_url( 'admin.php?page=hubskills-extensions' ) );
    exit;
}

// ── Submenu ──────────────────────────────────────────────────────────────────
add_action( 'admin_menu', 'hs_lms_register_extensions_menu', 20 );
function hs_lms_register_extensions_menu() {
    add_submenu_page(
        'hubskills-lms',
        __( 'Extensões', 'hubskills-lms' ),
        '🧩 ' . __( 'Extensões', 'hubskills-lms' ),
        'manage_options',
        'hubskills-extensions',
        'hs_lms_extensions_page'
    );
}

// ── Página ───────────────────────────────────────────────────────────────────
function hs_lms_extensions_page() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Acesso negado.', 'hubskills-lms' ) );

    $store    = hs_lms_extensions_store_url();
    $active   = hs_lms_active_extensions();
    $licenses = hs_lms_get_licenses();

    $notice = get_transient( 'hs_lms_ext_notice_' . get_current_user_id() );
    if ( $notice ) delete_transient( 'hs_lms_ext_notice_' . get_current_user_id() );
    ?>
    <div class="wrap hs-extensions-wrap">
        <h1 style="display:flex;align-items:center;gap:10px">🧩 <?php esc_html_e( 'Extensões HubSkills', 'hubskills-lms' ); ?></h1>

        <?php if ( $notice ) : ?>
        <div class="notice notice-<?php echo $notice['type'] === 'success' ? 'success' : 'error'; ?> is-dismissible">
            <p><?php echo esc_html( $notice['message'] ); ?></p>
        </div>
        <?php endif; ?>

        <p style="font-size:14px;color:#666;max-width:720px">
            <?php esc_html_e( 'Amplie o seu LMS com módulos oficiais. Ao clicar em "Comprar módulo" você será levado à página oficial para concluir a compra — após o pagamento, você recebe o arquivo do módulo e a chave de licença para ativar aqui mesmo.', 'hubskills-lms' ); ?>
        </p>

        <div class="hs-ext-grid">
            <?php foreach ( hs_lms_extensions_catalog() as $slug => $ext ) :
                $is_active = in_array( $slug, $active, true );
                $license   = $licenses[ $slug ]['key'] ?? '';
                $masked    = $license ? substr( $license, 0, 6 ) . str_repeat( '•', 10 ) . substr( $license, -6 ) : '';
                $url = add_query_arg( [
                    'utm_source'   => 'plugin',
                    'utm_medium'   => 'extensions-page',
                    'utm_campaign' => $slug,
                    'modulo'       => $slug,
                ], $store );
            ?>
            <div class="hs-ext-card" style="border-top:3px solid <?php echo esc_attr( $ext['color'] ); ?>">
                <div class="hs-ext-head">
                    <span class="hs-ext-icon" style="background:<?php echo esc_attr( $ext['color'] ); ?>1a"><?php echo esc_html( $ext['icon'] ); ?></span>
                    <div>
                        <h3><?php echo esc_html( $ext['name'] ); ?></h3>
                        <?php if ( $ext['tag'] ) : ?>
                        <span class="hs-ext-tag" style="background:<?php echo esc_attr( $ext['color'] ); ?>;"><?php echo esc_html( $ext['tag'] ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <p><?php echo esc_html( $ext['desc'] ); ?></p>

                <?php if ( $is_active ) : ?>
                <div class="hs-ext-actions">
                    <span class="hs-ext-active">✅ <?php esc_html_e( 'Módulo ativo', 'hubskills-lms' ); ?></span>
                    <?php if ( $masked ) : ?><code class="hs-ext-key" title="<?php esc_attr_e( 'Chave de licença', 'hubskills-lms' ); ?>"><?php echo esc_html( $masked ); ?></code><?php endif; ?>
                </div>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Remover a licença e desativar este módulo?', 'hubskills-lms' ) ); ?>');">
                    <?php wp_nonce_field( 'hs_lms_extension_license' ); ?>
                    <input type="hidden" name="action" value="hs_lms_deactivate_extension">
                    <input type="hidden" name="extension" value="<?php echo esc_attr( $slug ); ?>">
                    <button type="submit" class="button-link hs-ext-deactivate"><?php esc_html_e( 'Remover licença', 'hubskills-lms' ); ?></button>
                </form>
                <?php else : ?>
                <div class="hs-ext-actions">
                    <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
                        <?php esc_html_e( 'Comprar módulo', 'hubskills-lms' ); ?> ↗
                    </a>
                    <button type="button" class="button hs-ext-toggle-license" data-target="hs-ext-license-<?php echo esc_attr( $slug ); ?>">
                        🔑 <?php esc_html_e( 'Já tenho a chave', 'hubskills-lms' ); ?>
                    </button>
                </div>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hs-ext-license-form" id="hs-ext-license-<?php echo esc_attr( $slug ); ?>" hidden>
                    <?php wp_nonce_field( 'hs_lms_extension_license' ); ?>
                    <input type="hidden" name="action" value="hs_lms_activate_extension">
                    <input type="hidden" name="extension" value="<?php echo esc_attr( $slug ); ?>">
                    <input type="text" name="license_key" class="regular-text" placeholder="HSLMS-XXXX-XXXXXXXX-XXXXXX" required autocomplete="off" spellcheck="false">
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Ativar licença', 'hubskills-lms' ); ?></button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="hs-ext-footer">
            <p>
                🔑 <?php esc_html_e( 'Já comprou um módulo? Instale o arquivo recebido (Plugins → Adicionar novo → Enviar plugin) e ative a chave de licença no cartão do módulo acima.', 'hubskills-lms' ); ?>
                <a href="<?php echo esc_url( $store ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Central de compras e suporte', 'hubskills-lms' ); ?> ↗</a>
            </p>
        </div>
    </div>

    <script>
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.hs-ext-toggle-license');
        if (!btn) return;
        var form = document.getElementById(btn.getAttribute('data-target'));
        if (!form) return;
        form.hidden = !form.hidden;
        if (!form.hidden) form.querySelector('input[name="license_key"]').focus();
    });
    </script>

    <style>
    .hs-ext-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:20px; margin-top:20px; }
    .hs-ext-card { background:#fff; border:1px solid #e2e2e2; border-radius:10px; padding:20px; display:flex; flex-direction:column; gap:12px; box-shadow:0 1px 2px rgba(0,0,0,.04); }
    .hs-ext-head { display:flex; align-items:center; gap:12px; }
    .hs-ext-icon { font-size:26px; width:52px; height:52px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .hs-ext-card h3 { margin:0; font-size:16px; }
    .hs-ext-tag { display:inline-block; color:#fff; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; padding:2px 8px; border-radius:99px; margin-top:4px; }
    .hs-ext-card p { margin:0; color:#555; font-size:13px; line-height:1.5; flex:1; }
    .hs-ext-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .hs-ext-active { color:#10B981; font-weight:600; font-size:13px; }
    .hs-ext-key { font-size:11px; color:#666; background:#f6f6f6; padding:2px 6px; border-radius:4px; }
    .hs-ext-deactivate { color:#b32d2e; font-size:12px; padding:0; }
    .hs-ext-license-form { display:flex; gap:8px; align-items:center; flex-wrap:wrap; background:#f8f6ff; border:1px dashed #c9bbf2; border-radius:8px; padding:10px; }
    .hs-ext-license-form input[name="license_key"] { flex:1; min-width:200px; font-family:monospace; text-transform:uppercase; }
    .hs-ext-footer { margin-top:24px; padding:14px 18px; background:#f8f6ff; border:1px solid #e6e0fa; border-radius:8px; max-width:900px; }
    .hs-ext-footer p { margin:0; font-size:13px; color:#555; }
    </style>
    <?php
}
