<?php
/**
 * Backup automático para AWS S3 (ou compatível: Backblaze B2, DigitalOcean Spaces, MinIO).
 *
 * - Sem SDK: assinatura AWS Signature V4 implementada sobre wp_remote_request().
 * - Agendamento inteligente: roda de madrugada e SÓ envia se algo mudou desde o último backup.
 * - Conteúdo: dump SQL das tabelas do site + JSON das configurações do LMS.
 * - Retenção automática: apaga backups antigos no bucket além do limite configurado.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Configuração ────────────────────────────────────────────────────────────

function hs_aws_settings(): array {
    $defaults = [
        'access_key' => '',
        'secret_key' => '',
        'region'     => 'us-east-1',
        'bucket'     => '',
        'endpoint'   => '', // vazio = AWS oficial; preencha para S3-compatível
        'prefix'     => 'hubskills-backups',
        'schedule'   => 'smart',   // disabled | daily | weekly | smart
        'retention'  => 14,        // nº de backups mantidos no bucket
        'full_db'    => '1',       // 1 = banco inteiro; 0 = só tabelas do LMS/WP essenciais
    ];
    return wp_parse_args( (array) get_option( 'hs_aws_backup', [] ), $defaults );
}

function hs_aws_log( string $msg, string $status = 'info' ) {
    $log   = (array) get_option( 'hs_aws_backup_log', [] );
    $log[] = [ 'time' => current_time( 'mysql' ), 'status' => $status, 'msg' => $msg ];
    update_option( 'hs_aws_backup_log', array_slice( $log, -50 ), false );
}

// ── Assinatura AWS Signature V4 ─────────────────────────────────────────────

/**
 * Executa uma requisição assinada (SigV4) contra o S3.
 *
 * @param string $method  GET|PUT|DELETE
 * @param string $key     Caminho do objeto (sem barra inicial) ou '' para o bucket
 * @param string $body    Corpo (PUT)
 * @param array  $query   Query string (ex.: list-type=2)
 * @return array|WP_Error resposta do wp_remote_request
 */
function hs_aws_s3_request( string $method, string $key = '', string $body = '', array $query = [] ) {
    $cfg = hs_aws_settings();
    if ( ! $cfg['access_key'] || ! $cfg['secret_key'] || ! $cfg['bucket'] ) {
        return new WP_Error( 'hs_aws_unconfigured', __( 'Preencha Access Key, Secret e Bucket nas configurações.', 'hubskills-lms' ) );
    }

    $host = $cfg['endpoint']
        ? preg_replace( '#^https?://#', '', rtrim( $cfg['endpoint'], '/' ) )
        : $cfg['bucket'] . '.s3.' . $cfg['region'] . '.amazonaws.com';
    // Endpoint customizado usa path-style: host/bucket/key
    $path = $cfg['endpoint'] ? '/' . $cfg['bucket'] . '/' . $key : '/' . $key;
    $path = '/' . ltrim( $path, '/' );

    $amz_date  = gmdate( 'Ymd\THis\Z' );
    $datestamp = gmdate( 'Ymd' );
    $payload   = hash( 'sha256', $body );

    ksort( $query );
    $canonical_query = http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );

    $canonical_uri = str_replace( '%2F', '/', rawurlencode( $path ) );

    $headers = [
        'host'                 => $host,
        'x-amz-content-sha256' => $payload,
        'x-amz-date'           => $amz_date,
    ];
    ksort( $headers );
    $canonical_headers = '';
    foreach ( $headers as $k => $v ) $canonical_headers .= $k . ':' . $v . "\n";
    $signed_headers = implode( ';', array_keys( $headers ) );

    $canonical_request = implode( "\n", [ $method, $canonical_uri, $canonical_query, $canonical_headers, $signed_headers, $payload ] );

    $scope          = $datestamp . '/' . $cfg['region'] . '/s3/aws4_request';
    $string_to_sign = implode( "\n", [ 'AWS4-HMAC-SHA256', $amz_date, $scope, hash( 'sha256', $canonical_request ) ] );

    $k_date    = hash_hmac( 'sha256', $datestamp, 'AWS4' . $cfg['secret_key'], true );
    $k_region  = hash_hmac( 'sha256', $cfg['region'], $k_date, true );
    $k_service = hash_hmac( 'sha256', 's3', $k_region, true );
    $k_signing = hash_hmac( 'sha256', 'aws4_request', $k_service, true );
    $signature = hash_hmac( 'sha256', $string_to_sign, $k_signing );

    $authorization = sprintf(
        'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
        $cfg['access_key'], $scope, $signed_headers, $signature
    );

    $url = 'https://' . $host . $path . ( $canonical_query ? '?' . $canonical_query : '' );

    return wp_remote_request( $url, [
        'method'  => $method,
        'timeout' => 120,
        'body'    => $body !== '' ? $body : null,
        'headers' => [
            'Authorization'        => $authorization,
            'x-amz-date'           => $amz_date,
            'x-amz-content-sha256' => $payload,
            'Content-Type'         => 'application/octet-stream',
        ],
    ] );
}

// ── Geração do backup ───────────────────────────────────────────────────────

/** Dump SQL via $wpdb (sem exec/mysqldump — funciona em qualquer hospedagem). */
function hs_aws_sql_dump(): string {
    global $wpdb;
    $cfg    = hs_aws_settings();
    $tables = $wpdb->get_col( 'SHOW TABLES' );

    if ( $cfg['full_db'] !== '1' ) {
        $keep = [ 'posts', 'postmeta', 'users', 'usermeta', 'options', 'terms', 'term_taxonomy', 'term_relationships', 'termmeta', 'comments', 'commentmeta' ];
        $tables = array_filter( $tables, function ( $t ) use ( $wpdb, $keep ) {
            if ( strpos( $t, $wpdb->prefix . 'hs_' ) === 0 ) return true;
            foreach ( $keep as $k ) if ( $t === $wpdb->prefix . $k ) return true;
            return false;
        } );
    }

    $dump = "-- HubSkills LMS backup\n-- Site: " . home_url() . "\n-- Data: " . current_time( 'mysql' ) . "\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ( $tables as $table ) {
        $create = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
        if ( ! $create ) continue;
        $dump .= "DROP TABLE IF EXISTS `{$table}`;\n" . $create[1] . ";\n\n";

        $offset = 0;
        while ( $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` LIMIT %d OFFSET %d", 500, $offset ), ARRAY_A ) ) {
            $values = [];
            foreach ( $rows as $row ) {
                $vals = array_map( function ( $v ) use ( $wpdb ) {
                    if ( $v === null ) return 'NULL';
                    return "'" . esc_sql( $v ) . "'";
                }, array_values( $row ) );
                $values[] = '(' . implode( ',', $vals ) . ')';
            }
            $dump  .= "INSERT INTO `{$table}` VALUES\n" . implode( ",\n", $values ) . ";\n";
            $offset += 500;
        }
        $dump .= "\n";
    }

    $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $dump;
}

/** Monta o zip do backup em uploads/hs-backups e devolve o caminho. */
function hs_aws_build_backup_file() {
    if ( ! class_exists( 'ZipArchive' ) ) {
        return new WP_Error( 'hs_aws_nozip', __( 'Extensão ZipArchive indisponível no PHP.', 'hubskills-lms' ) );
    }

    $upload = wp_upload_dir();
    $dir    = trailingslashit( $upload['basedir'] ) . 'hs-backups';
    wp_mkdir_p( $dir );
    // Bloqueia listagem/acesso web à pasta
    if ( ! file_exists( $dir . '/.htaccess' ) ) file_put_contents( $dir . '/.htaccess', "Deny from all\n" );
    if ( ! file_exists( $dir . '/index.php' ) )  file_put_contents( $dir . '/index.php', "<?php // silêncio\n" );

    $file = $dir . '/hubskills-backup-' . gmdate( 'Ymd-His' ) . '.zip';
    $zip  = new ZipArchive();
    if ( $zip->open( $file, ZipArchive::CREATE ) !== true ) {
        return new WP_Error( 'hs_aws_zipfail', __( 'Não foi possível criar o arquivo zip.', 'hubskills-lms' ) );
    }

    $zip->addFromString( 'database.sql', hs_aws_sql_dump() );

    // Configurações do LMS (todas as options hs_*)
    global $wpdb;
    $options = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'hs\_%' OR option_name LIKE 'hubskills%'", ARRAY_A );
    $zip->addFromString( 'lms-settings.json', wp_json_encode( $options, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

    $zip->addFromString( 'manifest.json', wp_json_encode( [
        'site'        => home_url(),
        'generated'   => current_time( 'mysql' ),
        'wp_version'  => get_bloginfo( 'version' ),
        'lms_version' => defined( 'HS_LMS_VERSION' ) ? HS_LMS_VERSION : '?',
        'php'         => PHP_VERSION,
    ], JSON_PRETTY_PRINT ) );

    $zip->close();
    return $file;
}

/** Hash do estado do conteúdo — usado pelo modo inteligente para pular backups sem mudança. */
function hs_aws_content_fingerprint(): string {
    global $wpdb;
    $last_post   = $wpdb->get_var( "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_status NOT IN ('auto-draft')" );
    $last_user   = $wpdb->get_var( "SELECT MAX(user_registered) FROM {$wpdb->users}" );
    $hs_tables   = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}hs\_%'" );
    $counts      = [];
    foreach ( $hs_tables as $t ) $counts[ $t ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$t}`" );
    return md5( $last_post . '|' . $last_user . '|' . wp_json_encode( $counts ) );
}

// ── Execução ────────────────────────────────────────────────────────────────

/**
 * Roda um backup completo (gera, envia ao S3, aplica retenção).
 *
 * @param bool $forced  true ignora a checagem de mudanças do modo inteligente.
 * @return true|WP_Error
 */
function hs_aws_run_backup( bool $forced = false ) {
    $cfg = hs_aws_settings();

    if ( ! $forced && $cfg['schedule'] === 'smart' ) {
        $fingerprint = hs_aws_content_fingerprint();
        if ( $fingerprint === get_option( 'hs_aws_last_fingerprint' ) ) {
            hs_aws_log( __( 'Modo inteligente: nada mudou desde o último backup — envio pulado.', 'hubskills-lms' ) );
            return true;
        }
    }

    $file = hs_aws_build_backup_file();
    if ( is_wp_error( $file ) ) {
        hs_aws_log( $file->get_error_message(), 'error' );
        return $file;
    }

    $key  = trim( $cfg['prefix'], '/' ) . '/' . basename( $file );
    $resp = hs_aws_s3_request( 'PUT', $key, file_get_contents( $file ) );
    @unlink( $file );

    if ( is_wp_error( $resp ) ) {
        hs_aws_log( $resp->get_error_message(), 'error' );
        return $resp;
    }
    $code = wp_remote_retrieve_response_code( $resp );
    if ( $code !== 200 ) {
        $err = sprintf( __( 'S3 respondeu HTTP %d: %s', 'hubskills-lms' ), $code, substr( wp_remote_retrieve_body( $resp ), 0, 300 ) );
        hs_aws_log( $err, 'error' );
        return new WP_Error( 'hs_aws_put_failed', $err );
    }

    update_option( 'hs_aws_last_fingerprint', hs_aws_content_fingerprint(), false );
    update_option( 'hs_aws_last_backup', current_time( 'mysql' ), false );
    hs_aws_log( sprintf( __( 'Backup enviado: s3://%s/%s (%s)', 'hubskills-lms' ), $cfg['bucket'], $key, size_format( strlen( wp_remote_retrieve_body( $resp ) ) ?: 0 ) ), 'success' );

    hs_aws_apply_retention();
    return true;
}

/** Lista os backups no bucket e apaga os mais antigos além da retenção. */
function hs_aws_apply_retention() {
    $cfg  = hs_aws_settings();
    $resp = hs_aws_s3_request( 'GET', '', '', [ 'list-type' => '2', 'prefix' => trim( $cfg['prefix'], '/' ) . '/' ] );
    if ( is_wp_error( $resp ) || wp_remote_retrieve_response_code( $resp ) !== 200 ) return;

    if ( ! preg_match_all( '#<Key>([^<]+)</Key>#', wp_remote_retrieve_body( $resp ), $m ) ) return;
    $keys = $m[1];
    sort( $keys ); // nomes têm timestamp → ordem cronológica

    $excess = count( $keys ) - max( 1, (int) $cfg['retention'] );
    for ( $i = 0; $i < $excess; $i++ ) {
        hs_aws_s3_request( 'DELETE', $keys[ $i ] );
        hs_aws_log( sprintf( __( 'Retenção: backup antigo removido (%s).', 'hubskills-lms' ), $keys[ $i ] ) );
    }
}

// ── Cron ────────────────────────────────────────────────────────────────────

add_action( 'hs_aws_backup_cron', function () { hs_aws_run_backup(); } );

add_action( 'init', 'hs_aws_schedule_cron' );
function hs_aws_schedule_cron() {
    $cfg       = hs_aws_settings();
    $scheduled = wp_next_scheduled( 'hs_aws_backup_cron' );

    if ( $cfg['schedule'] === 'disabled' || ! $cfg['bucket'] ) {
        if ( $scheduled ) wp_unschedule_event( $scheduled, 'hs_aws_backup_cron' );
        return;
    }

    $recurrence = $cfg['schedule'] === 'weekly' ? 'weekly' : 'daily'; // smart roda diário, mas pula sem mudanças
    if ( ! $scheduled ) {
        // 3h da manhã local: horário de menor tráfego
        $first = strtotime( 'tomorrow 3:00' ) - ( (int) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
        wp_schedule_event( $first, $recurrence, 'hs_aws_backup_cron' );
    }
}

// ── Tela admin ──────────────────────────────────────────────────────────────

add_action( 'admin_menu', function () {
    add_submenu_page( 'hubskills-lms', __( 'Backups (AWS)', 'hubskills-lms' ), '☁️ ' . __( 'Backups (AWS)', 'hubskills-lms' ), 'manage_options', 'hubskills-aws-backup', 'hs_aws_admin_page' );
}, 30 );

function hs_aws_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    // Salvar configurações
    if ( isset( $_POST['hs_aws_nonce'] ) && wp_verify_nonce( $_POST['hs_aws_nonce'], 'hs_aws_save' ) ) {
        $cfg = hs_aws_settings();
        $new = [
            'access_key' => sanitize_text_field( $_POST['access_key'] ?? '' ),
            'secret_key' => $_POST['secret_key'] !== '' ? sanitize_text_field( $_POST['secret_key'] ) : $cfg['secret_key'],
            'region'     => sanitize_text_field( $_POST['region'] ?? 'us-east-1' ),
            'bucket'     => sanitize_text_field( $_POST['bucket'] ?? '' ),
            'endpoint'   => esc_url_raw( $_POST['endpoint'] ?? '' ),
            'prefix'     => sanitize_title( $_POST['prefix'] ?? 'hubskills-backups' ) ?: 'hubskills-backups',
            'schedule'   => in_array( $_POST['schedule'] ?? '', [ 'disabled', 'daily', 'weekly', 'smart' ], true ) ? $_POST['schedule'] : 'smart',
            'retention'  => max( 1, (int) ( $_POST['retention'] ?? 14 ) ),
            'full_db'    => isset( $_POST['full_db'] ) ? '1' : '0',
        ];
        update_option( 'hs_aws_backup', $new );
        wp_clear_scheduled_hook( 'hs_aws_backup_cron' );
        hs_aws_schedule_cron();
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Configurações salvas.', 'hubskills-lms' ) . '</p></div>';

        if ( isset( $_POST['hs_aws_action'] ) && $_POST['hs_aws_action'] === 'test' ) {
            $resp = hs_aws_s3_request( 'GET', '', '', [ 'list-type' => '2', 'max-keys' => '1' ] );
            $code = is_wp_error( $resp ) ? 0 : wp_remote_retrieve_response_code( $resp );
            echo $code === 200
                ? '<div class="notice notice-success"><p>✅ ' . esc_html__( 'Conexão com o bucket OK!', 'hubskills-lms' ) . '</p></div>'
                : '<div class="notice notice-error"><p>❌ ' . esc_html( is_wp_error( $resp ) ? $resp->get_error_message() : sprintf( __( 'Falha (HTTP %d). Verifique credenciais, região e bucket.', 'hubskills-lms' ), $code ) ) . '</p></div>';
        }

        if ( isset( $_POST['hs_aws_action'] ) && $_POST['hs_aws_action'] === 'backup_now' ) {
            $r = hs_aws_run_backup( true );
            echo is_wp_error( $r )
                ? '<div class="notice notice-error"><p>❌ ' . esc_html( $r->get_error_message() ) . '</p></div>'
                : '<div class="notice notice-success"><p>✅ ' . esc_html__( 'Backup enviado ao bucket com sucesso!', 'hubskills-lms' ) . '</p></div>';
        }
    }

    $cfg  = hs_aws_settings();
    $last = get_option( 'hs_aws_last_backup' );
    $log  = array_reverse( (array) get_option( 'hs_aws_backup_log', [] ) );
    ?>
    <div class="wrap">
        <h1>☁️ <?php _e( 'Backups automáticos (AWS S3)', 'hubskills-lms' ); ?></h1>
        <p><?php _e( 'Envia dump do banco + configurações do LMS para um bucket S3 (AWS ou compatível). Modo inteligente só envia quando algo mudou.', 'hubskills-lms' ); ?></p>
        <?php if ( $last ) : ?><p><strong><?php printf( esc_html__( 'Último backup: %s', 'hubskills-lms' ), esc_html( $last ) ); ?></strong></p><?php endif; ?>

        <form method="post">
            <?php wp_nonce_field( 'hs_aws_save', 'hs_aws_nonce' ); ?>
            <table class="form-table">
                <tr><th><?php _e( 'Access Key ID', 'hubskills-lms' ); ?></th>
                    <td><input type="text" name="access_key" value="<?php echo esc_attr( $cfg['access_key'] ); ?>" class="regular-text" autocomplete="off"></td></tr>
                <tr><th><?php _e( 'Secret Access Key', 'hubskills-lms' ); ?></th>
                    <td><input type="password" name="secret_key" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo $cfg['secret_key'] ? esc_attr__( '•••••• (preencha só para trocar)', 'hubskills-lms' ) : ''; ?>"></td></tr>
                <tr><th><?php _e( 'Região', 'hubskills-lms' ); ?></th>
                    <td><input type="text" name="region" value="<?php echo esc_attr( $cfg['region'] ); ?>" class="regular-text" placeholder="us-east-1"> <span class="description">sa-east-1 = São Paulo</span></td></tr>
                <tr><th><?php _e( 'Bucket', 'hubskills-lms' ); ?></th>
                    <td><input type="text" name="bucket" value="<?php echo esc_attr( $cfg['bucket'] ); ?>" class="regular-text"></td></tr>
                <tr><th><?php _e( 'Endpoint customizado', 'hubskills-lms' ); ?></th>
                    <td><input type="url" name="endpoint" value="<?php echo esc_attr( $cfg['endpoint'] ); ?>" class="regular-text" placeholder="https://... (vazio = AWS)">
                        <p class="description"><?php _e( 'Para Backblaze B2, DigitalOcean Spaces, MinIO etc.', 'hubskills-lms' ); ?></p></td></tr>
                <tr><th><?php _e( 'Pasta no bucket (prefixo)', 'hubskills-lms' ); ?></th>
                    <td><input type="text" name="prefix" value="<?php echo esc_attr( $cfg['prefix'] ); ?>" class="regular-text"></td></tr>
                <tr><th><?php _e( 'Agendamento', 'hubskills-lms' ); ?></th>
                    <td><select name="schedule">
                        <option value="smart"    <?php selected( $cfg['schedule'], 'smart' ); ?>>🧠 <?php _e( 'Inteligente — diário às 3h, só envia se houve mudança (recomendado)', 'hubskills-lms' ); ?></option>
                        <option value="daily"    <?php selected( $cfg['schedule'], 'daily' ); ?>><?php _e( 'Diário (sempre envia)', 'hubskills-lms' ); ?></option>
                        <option value="weekly"   <?php selected( $cfg['schedule'], 'weekly' ); ?>><?php _e( 'Semanal', 'hubskills-lms' ); ?></option>
                        <option value="disabled" <?php selected( $cfg['schedule'], 'disabled' ); ?>><?php _e( 'Desativado', 'hubskills-lms' ); ?></option>
                    </select></td></tr>
                <tr><th><?php _e( 'Retenção', 'hubskills-lms' ); ?></th>
                    <td><input type="number" name="retention" value="<?php echo (int) $cfg['retention']; ?>" min="1" max="365" style="width:80px">
                        <span class="description"><?php _e( 'backups mantidos no bucket (os mais antigos são apagados)', 'hubskills-lms' ); ?></span></td></tr>
                <tr><th><?php _e( 'Conteúdo', 'hubskills-lms' ); ?></th>
                    <td><label><input type="checkbox" name="full_db" <?php checked( $cfg['full_db'], '1' ); ?>> <?php _e( 'Banco de dados completo (desmarcado = só tabelas do LMS e essenciais do WP)', 'hubskills-lms' ); ?></label></td></tr>
            </table>
            <p>
                <button class="button button-primary" name="hs_aws_action" value="save"><?php _e( 'Salvar', 'hubskills-lms' ); ?></button>
                <button class="button" name="hs_aws_action" value="test"><?php _e( '🔌 Salvar e testar conexão', 'hubskills-lms' ); ?></button>
                <button class="button" name="hs_aws_action" value="backup_now"><?php _e( '⬆️ Salvar e fazer backup agora', 'hubskills-lms' ); ?></button>
            </p>
        </form>

        <?php if ( $log ) : ?>
            <h2><?php _e( 'Histórico', 'hubskills-lms' ); ?></h2>
            <table class="widefat striped" style="max-width:900px">
                <thead><tr><th style="width:160px"><?php _e( 'Quando', 'hubskills-lms' ); ?></th><th><?php _e( 'Evento', 'hubskills-lms' ); ?></th></tr></thead>
                <tbody>
                <?php foreach ( array_slice( $log, 0, 20 ) as $entry ) : ?>
                    <tr><td><?php echo esc_html( $entry['time'] ); ?></td>
                        <td><?php echo ( $entry['status'] === 'error' ? '❌ ' : ( $entry['status'] === 'success' ? '✅ ' : 'ℹ️ ' ) ) . esc_html( $entry['msg'] ); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}
