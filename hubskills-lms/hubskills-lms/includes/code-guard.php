<?php
/**
 * Code Guard — proteção do código do plugin contra edição não autorizada.
 *
 * Camadas:
 *  1. Manifesto de integridade: hash SHA-256 de cada arquivo do plugin; verificação
 *     diária via cron + sob demanda. Arquivo alterado/adicionado/removido gera alerta
 *     no admin, registro na auditoria e e-mail opcional.
 *  2. Bloqueio do editor de arquivos do WP (Aparência/Plugins > Editor) — equivale a
 *     DISALLOW_FILE_EDIT sem precisar mexer no wp-config.php.
 *  3. Tela de status com diff de arquivos e botão para reconstruir o manifesto após
 *     uma atualização legítima.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Configuração ────────────────────────────────────────────────────────────

function hs_guard_settings(): array {
    $defaults = [
        'block_editor' => '1', // bloqueia editor de plugins/temas do WP
        'email_alert'  => '1', // avisa o admin por e-mail quando detectar alteração
    ];
    return wp_parse_args( (array) get_option( 'hs_code_guard', [] ), $defaults );
}

// ── Manifesto de integridade ────────────────────────────────────────────────

/** Calcula os hashes de todos os arquivos do plugin (exceto languages, que mudam no build de tradução). */
function hs_guard_scan(): array {
    $base   = untrailingslashit( HS_LMS_DIR );
    $hashes = [];
    $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ) );
    foreach ( $it as $file ) {
        if ( ! $file->isFile() ) continue;
        $rel = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $base ) + 1 ) );
        if ( strpos( $rel, 'languages/' ) === 0 ) continue;
        $hashes[ $rel ] = hash_file( 'sha256', $file->getPathname() );
    }
    ksort( $hashes );
    return $hashes;
}

function hs_guard_rebuild_manifest() {
    update_option( 'hs_guard_manifest', [
        'version'   => defined( 'HS_LMS_VERSION' ) ? HS_LMS_VERSION : '?',
        'built_at'  => current_time( 'mysql' ),
        'files'     => hs_guard_scan(),
    ], false );
    delete_option( 'hs_guard_last_report' );
    if ( function_exists( 'hs_audit_log' ) ) {
        hs_audit_log( get_current_user_id(), 'guard_manifest_rebuilt', 'Manifesto de integridade do plugin reconstruído.' );
    }
}

/**
 * Compara o estado atual com o manifesto.
 *
 * @return array{ok:bool, modified:array, added:array, missing:array}|null null = sem manifesto
 */
function hs_guard_verify(): ?array {
    $manifest = get_option( 'hs_guard_manifest' );
    if ( empty( $manifest['files'] ) ) return null;

    $current  = hs_guard_scan();
    $expected = $manifest['files'];

    $modified = [];
    foreach ( $expected as $rel => $hash ) {
        if ( isset( $current[ $rel ] ) && ! hash_equals( $hash, $current[ $rel ] ) ) $modified[] = $rel;
    }
    $added   = array_values( array_diff( array_keys( $current ), array_keys( $expected ) ) );
    $missing = array_values( array_diff( array_keys( $expected ), array_keys( $current ) ) );

    $report = [
        'ok'         => ! $modified && ! $added && ! $missing,
        'modified'   => $modified,
        'added'      => $added,
        'missing'    => $missing,
        'checked_at' => current_time( 'mysql' ),
    ];
    update_option( 'hs_guard_last_report', $report, false );
    return $report;
}

// ── Cron diário + alertas ───────────────────────────────────────────────────

add_action( 'init', function () {
    if ( ! wp_next_scheduled( 'hs_guard_cron' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'hs_guard_cron' );
    }
} );

add_action( 'hs_guard_cron', function () {
    $report = hs_guard_verify();
    if ( ! $report || $report['ok'] ) return;

    $problems = array_merge( $report['modified'], $report['added'], $report['missing'] );
    if ( function_exists( 'hs_audit_log' ) ) {
        hs_audit_log( 0, 'guard_integrity_failed', 'Integridade do plugin violada: ' . implode( ', ', array_slice( $problems, 0, 10 ) ) );
    }

    $cfg = hs_guard_settings();
    if ( $cfg['email_alert'] === '1' && ! get_transient( 'hs_guard_email_sent' ) ) {
        wp_mail(
            get_option( 'admin_email' ),
            '[' . get_bloginfo( 'name' ) . '] ⚠️ Alerta: código do HubSkills LMS foi alterado',
            "A verificação de integridade detectou alterações nos arquivos do plugin:\n\n"
            . ( $report['modified'] ? "MODIFICADOS:\n- " . implode( "\n- ", $report['modified'] ) . "\n\n" : '' )
            . ( $report['added']    ? "ADICIONADOS:\n- " . implode( "\n- ", $report['added'] ) . "\n\n" : '' )
            . ( $report['missing']  ? "REMOVIDOS:\n- " . implode( "\n- ", $report['missing'] ) . "\n\n" : '' )
            . "Se você não fez essas mudanças, pode ser invasão: troque senhas, restaure um backup e reinstale o plugin a partir do zip oficial.\n"
            . 'Tela de status: ' . admin_url( 'admin.php?page=hubskills-code-guard' )
        );
        set_transient( 'hs_guard_email_sent', 1, DAY_IN_SECONDS );
    }
} );

// Aviso no topo do admin enquanto a integridade estiver violada
add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $report = get_option( 'hs_guard_last_report' );
    if ( ! $report || ! empty( $report['ok'] ) ) return;
    $count = count( $report['modified'] ) + count( $report['added'] ) + count( $report['missing'] );
    printf(
        '<div class="notice notice-error"><p><strong>⚠️ HubSkills Code Guard:</strong> %s <a href="%s">%s</a></p></div>',
        esc_html( sprintf( _n( '%d arquivo do plugin foi alterado sem autorização.', '%d arquivos do plugin foram alterados sem autorização.', $count, 'hubskills-lms' ), $count ) ),
        esc_url( admin_url( 'admin.php?page=hubskills-code-guard' ) ),
        esc_html__( 'Ver detalhes', 'hubskills-lms' )
    );
} );

// ── Bloqueio do editor de arquivos do WP ────────────────────────────────────

add_filter( 'map_meta_cap', function ( array $caps, string $cap ) {
    if ( in_array( $cap, [ 'edit_plugins', 'edit_themes', 'edit_files' ], true )
        && hs_guard_settings()['block_editor'] === '1' ) {
        $caps[] = 'do_not_allow';
    }
    return $caps;
}, 10, 2 );

// ── Tela admin ──────────────────────────────────────────────────────────────

add_action( 'admin_menu', function () {
    add_submenu_page( 'hubskills-lms', __( 'Proteção de Código', 'hubskills-lms' ), '🛡️ ' . __( 'Proteção de Código', 'hubskills-lms' ), 'manage_options', 'hubskills-code-guard', 'hs_guard_admin_page' );
}, 32 );

function hs_guard_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    if ( isset( $_POST['hs_guard_nonce'] ) && wp_verify_nonce( $_POST['hs_guard_nonce'], 'hs_guard' ) ) {
        $action = sanitize_key( $_POST['hs_guard_action'] ?? '' );
        if ( $action === 'save' ) {
            update_option( 'hs_code_guard', [
                'block_editor' => isset( $_POST['block_editor'] ) ? '1' : '0',
                'email_alert'  => isset( $_POST['email_alert'] ) ? '1' : '0',
            ] );
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Configurações salvas.', 'hubskills-lms' ) . '</p></div>';
        }
        if ( $action === 'verify' )  hs_guard_verify();
        if ( $action === 'rebuild' ) hs_guard_rebuild_manifest();
    }

    $manifest = get_option( 'hs_guard_manifest' );
    if ( ! $manifest ) { hs_guard_rebuild_manifest(); $manifest = get_option( 'hs_guard_manifest' ); }
    $report = get_option( 'hs_guard_last_report' );
    $cfg    = hs_guard_settings();
    ?>
    <div class="wrap">
        <h1>🛡️ <?php _e( 'Proteção de Código', 'hubskills-lms' ); ?></h1>
        <p><?php printf( esc_html__( 'Manifesto: %d arquivos monitorados · construído em %s (v%s).', 'hubskills-lms' ), count( $manifest['files'] ?? [] ), esc_html( $manifest['built_at'] ?? '—' ), esc_html( $manifest['version'] ?? '?' ) ); ?></p>

        <?php if ( $report ) : ?>
            <?php if ( ! empty( $report['ok'] ) ) : ?>
                <div class="notice notice-success inline"><p>✅ <?php printf( esc_html__( 'Íntegro — última verificação: %s', 'hubskills-lms' ), esc_html( $report['checked_at'] ) ); ?></p></div>
            <?php else : ?>
                <div class="notice notice-error inline"><p>⚠️ <?php printf( esc_html__( 'Alterações detectadas (verificado em %s):', 'hubskills-lms' ), esc_html( $report['checked_at'] ) ); ?></p>
                    <ul style="margin-left:20px;list-style:disc">
                        <?php foreach ( $report['modified'] as $f ) : ?><li>✏️ <?php echo esc_html( $f ); ?> — <?php _e( 'modificado', 'hubskills-lms' ); ?></li><?php endforeach; ?>
                        <?php foreach ( $report['added'] as $f ) : ?><li>➕ <?php echo esc_html( $f ); ?> — <?php _e( 'adicionado (não fazia parte do plugin!)', 'hubskills-lms' ); ?></li><?php endforeach; ?>
                        <?php foreach ( $report['missing'] as $f ) : ?><li>❌ <?php echo esc_html( $f ); ?> — <?php _e( 'removido', 'hubskills-lms' ); ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field( 'hs_guard', 'hs_guard_nonce' ); ?>
            <p>
                <button class="button button-primary" name="hs_guard_action" value="verify"><?php _e( '🔍 Verificar integridade agora', 'hubskills-lms' ); ?></button>
                <button class="button" name="hs_guard_action" value="rebuild" onclick="return confirm('<?php echo esc_js( __( 'Reconstruir o manifesto aceita o estado ATUAL dos arquivos como legítimo. Só faça isso após uma atualização sua. Continuar?', 'hubskills-lms' ) ); ?>')"><?php _e( '♻️ Reconstruir manifesto (após atualização legítima)', 'hubskills-lms' ); ?></button>
            </p>
            <table class="form-table">
                <tr><th><?php _e( 'Editor de arquivos do WP', 'hubskills-lms' ); ?></th>
                    <td><label><input type="checkbox" name="block_editor" <?php checked( $cfg['block_editor'], '1' ); ?>> <?php _e( 'Bloquear o editor de plugins e temas do painel (recomendado — equivale a DISALLOW_FILE_EDIT)', 'hubskills-lms' ); ?></label></td></tr>
                <tr><th><?php _e( 'Alerta por e-mail', 'hubskills-lms' ); ?></th>
                    <td><label><input type="checkbox" name="email_alert" <?php checked( $cfg['email_alert'], '1' ); ?>> <?php printf( esc_html__( 'Enviar alerta para %s quando detectar alteração', 'hubskills-lms' ), esc_html( get_option( 'admin_email' ) ) ); ?></label></td></tr>
            </table>
            <p><button class="button button-primary" name="hs_guard_action" value="save"><?php _e( 'Salvar', 'hubskills-lms' ); ?></button></p>
        </form>

        <h2><?php _e( 'Camadas extras (fora do plugin)', 'hubskills-lms' ); ?></h2>
        <ol>
            <li><?php _e( '<strong>wp-config.php:</strong> <code>define(\'DISALLOW_FILE_MODS\', true);</code> bloqueia também instalação/atualização de plugins pelo painel (só FTP).', 'hubskills-lms' ); ?></li>
            <li><?php _e( '<strong>Permissões de arquivo:</strong> 644 para arquivos e 755 para pastas; dono diferente do usuário do PHP impede escrita pelo próprio site.', 'hubskills-lms' ); ?></li>
            <li><?php _e( '<strong>Ofuscação/compilação comercial:</strong> ionCube Encoder ou SourceGuardian criptografam o PHP (exige extensão no servidor) — proteção forte para venda do plugin.', 'hubskills-lms' ); ?></li>
            <li><?php _e( '<strong>Licenciamento remoto:</strong> a tela de Extensões já valida licenças; partes premium podem ficar num servidor seu e ser servidas via API.', 'hubskills-lms' ); ?></li>
        </ol>
    </div>
    <?php
}

// Reconstrói o manifesto automaticamente quando o plugin é atualizado de versão
add_action( 'admin_init', function () {
    $manifest = get_option( 'hs_guard_manifest' );
    if ( $manifest && defined( 'HS_LMS_VERSION' ) && ( $manifest['version'] ?? '' ) !== HS_LMS_VERSION ) {
        hs_guard_rebuild_manifest();
    }
} );
