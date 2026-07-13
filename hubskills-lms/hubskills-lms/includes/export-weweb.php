<?php
/**
 * Exportação para WeWeb — pacote de migração completo.
 *
 * Gera um zip com todas as coleções do LMS em JSON normalizado (formato que o WeWeb
 * importa em backends como Xano/Supabase), mais o dump SQL completo e um guia de
 * migração. Também expõe endpoints REST paginados para o WeWeb consumir o site
 * ao vivo como data source (útil na transição, antes de desligar o WordPress).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Coleções ────────────────────────────────────────────────────────────────

/** Cursos com currículo completo. */
function hs_weweb_collection_courses(): array {
    $out = [];
    foreach ( get_posts( [ 'post_type' => 'hubskills_course', 'post_status' => 'any', 'numberposts' => -1 ] ) as $c ) {
        $out[] = [
            'id'             => $c->ID,
            'title'          => $c->post_title,
            'slug'           => $c->post_name,
            'status'         => $c->post_status,
            'description'    => $c->post_content,
            'excerpt'        => $c->post_excerpt,
            'created_at'     => $c->post_date_gmt,
            'updated_at'     => $c->post_modified_gmt,
            'instructor_id'  => (int) get_post_meta( $c->ID, '_hs_instructor_id', true ),
            'instructor'     => get_post_meta( $c->ID, '_hs_instructor', true ),
            'price'          => get_post_meta( $c->ID, '_hs_price', true ),
            'level'          => get_post_meta( $c->ID, '_hs_level', true ),
            'duration'       => get_post_meta( $c->ID, '_hs_duration', true ),
            'rating'         => get_post_meta( $c->ID, '_hs_rating', true ),
            'students'       => get_post_meta( $c->ID, '_hs_students', true ),
            'lessons_count'  => (int) get_post_meta( $c->ID, '_hs_lessons_count', true ),
            'featured'       => get_post_meta( $c->ID, '_hs_featured', true ) === '1',
            'thumbnail_url'  => get_the_post_thumbnail_url( $c->ID, 'full' ) ?: '',
            'categories'     => wp_get_post_terms( $c->ID, 'hubskills_category', [ 'fields' => 'names' ] ),
            'curriculum'     => get_post_meta( $c->ID, '_hs_curriculum', true ) ?: [],
        ];
    }
    return $out;
}

/** Quizzes com questões. */
function hs_weweb_collection_quizzes(): array {
    $out = [];
    foreach ( get_posts( [ 'post_type' => 'hubskills_quiz', 'post_status' => 'any', 'numberposts' => -1 ] ) as $q ) {
        $out[] = [
            'id'             => $q->ID,
            'title'          => $q->post_title,
            'status'         => $q->post_status,
            'time_limit'     => (int) get_post_meta( $q->ID, '_hs_time_limit', true ),
            'pass_threshold' => (int) get_post_meta( $q->ID, '_hs_pass_threshold', true ),
            'questions'      => get_post_meta( $q->ID, '_hs_questions', true ) ?: [],
        ];
    }
    return $out;
}

/**
 * Usuários com todos os dados do LMS (matrículas, progresso, gamificação).
 *
 * @param bool $with_password_hashes inclui o hash de senha do WP (phpass/bcrypt)
 *                                   para migração com login preservado.
 */
function hs_weweb_collection_users( bool $with_password_hashes = false ): array {
    $out = [];
    foreach ( get_users( [ 'number' => -1 ] ) as $u ) {
        $lms_meta = [];
        foreach ( get_user_meta( $u->ID ) as $key => $values ) {
            if ( strpos( $key, 'hs_' ) === 0 || strpos( $key, '_hs_' ) === 0 ) {
                $lms_meta[ $key ] = maybe_unserialize( $values[0] );
            }
        }
        $row = [
            'id'            => $u->ID,
            'email'         => $u->user_email,
            'username'      => $u->user_login,
            'display_name'  => $u->display_name,
            'registered_at' => $u->user_registered,
            'roles'         => $u->roles,
            'enrollments'   => $lms_meta['hs_enrollments'] ?? [],
            'lms_meta'      => $lms_meta,
        ];
        if ( $with_password_hashes ) $row['password_hash'] = $u->user_pass;
        $out[] = $row;
    }
    return $out;
}

/** Matrículas achatadas (1 linha por aluno×curso) — ideal para banco relacional. */
function hs_weweb_collection_enrollments(): array {
    $out = [];
    foreach ( get_users( [ 'number' => -1, 'meta_key' => 'hs_enrollments' ] ) as $u ) {
        foreach ( (array) get_user_meta( $u->ID, 'hs_enrollments', true ) as $course_id => $data ) {
            $out[] = array_merge(
                [ 'user_id' => $u->ID, 'user_email' => $u->user_email, 'course_id' => (int) $course_id ],
                is_array( $data ) ? $data : [ 'value' => $data ]
            );
        }
    }
    return $out;
}

/** Pedidos/pagamentos. */
function hs_weweb_collection_orders(): array {
    global $wpdb;
    $ids = $wpdb->get_col( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_hs_order_user_id'" );
    $out = [];
    foreach ( $ids as $id ) {
        $post = get_post( $id );
        if ( ! $post ) continue;
        $meta = [];
        foreach ( get_post_meta( $id ) as $key => $values ) {
            if ( strpos( $key, '_hs_order' ) === 0 ) $meta[ substr( $key, 1 ) ] = maybe_unserialize( $values[0] );
        }
        $out[] = array_merge( [ 'id' => (int) $id, 'created_at' => $post->post_date_gmt, 'status' => $post->post_status ], $meta );
    }
    return $out;
}

/** Configurações do LMS. */
function hs_weweb_collection_settings(): array {
    global $wpdb;
    $rows = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'hs\_%' OR option_name LIKE 'hubskills%'", ARRAY_A );
    $out  = [];
    foreach ( $rows as $row ) {
        // Nunca exporta credenciais sensíveis
        if ( in_array( $row['option_name'], [ 'hs_aws_backup' ], true ) ) continue;
        $out[ $row['option_name'] ] = maybe_unserialize( $row['option_value'] );
    }
    return $out;
}

function hs_weweb_collections(): array {
    return [
        'courses'     => 'hs_weweb_collection_courses',
        'quizzes'     => 'hs_weweb_collection_quizzes',
        'users'       => 'hs_weweb_collection_users',
        'enrollments' => 'hs_weweb_collection_enrollments',
        'orders'      => 'hs_weweb_collection_orders',
        'settings'    => 'hs_weweb_collection_settings',
    ];
}

// ── Geração do pacote ───────────────────────────────────────────────────────

function hs_weweb_build_package( bool $with_passwords, bool $with_sql ) {
    if ( ! class_exists( 'ZipArchive' ) ) {
        return new WP_Error( 'hs_weweb_nozip', __( 'Extensão ZipArchive indisponível no PHP.', 'hubskills-lms' ) );
    }

    $upload = wp_upload_dir();
    $dir    = trailingslashit( $upload['basedir'] ) . 'hs-exports';
    wp_mkdir_p( $dir );
    if ( ! file_exists( $dir . '/.htaccess' ) ) file_put_contents( $dir . '/.htaccess', "Deny from all\n" );
    if ( ! file_exists( $dir . '/index.php' ) )  file_put_contents( $dir . '/index.php', "<?php // silêncio\n" );

    $file = $dir . '/hubskills-weweb-export-' . gmdate( 'Ymd-His' ) . '.zip';
    $zip  = new ZipArchive();
    if ( $zip->open( $file, ZipArchive::CREATE ) !== true ) {
        return new WP_Error( 'hs_weweb_zipfail', __( 'Não foi possível criar o zip.', 'hubskills-lms' ) );
    }

    $counts = [];
    foreach ( hs_weweb_collections() as $name => $fn ) {
        $data = $name === 'users' ? $fn( $with_passwords ) : $fn();
        $counts[ $name ] = is_array( $data ) ? count( $data ) : 0;
        $zip->addFromString( 'collections/' . $name . '.json', wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
    }

    if ( $with_sql && function_exists( 'hs_aws_sql_dump' ) ) {
        $zip->addFromString( 'database.sql', hs_aws_sql_dump() );
    }

    $zip->addFromString( 'manifest.json', wp_json_encode( [
        'format'      => 'hubskills-weweb-export/1.0',
        'site'        => home_url(),
        'generated'   => current_time( 'mysql' ),
        'lms_version' => defined( 'HS_LMS_VERSION' ) ? HS_LMS_VERSION : '?',
        'collections' => $counts,
        'passwords_included' => $with_passwords,
        'sql_included'       => $with_sql,
        'uploads_note' => __( 'Mídias (vídeos/imagens) NÃO vão no zip — as URLs nos JSONs apontam para wp-content/uploads. Copie a pasta uploads via FTP ou mova para um CDN/S3 antes de desligar o WordPress.', 'hubskills-lms' ),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

    $zip->addFromString( 'LEIA-ME-WEWEB.md', hs_weweb_readme() );
    $zip->close();

    return $file;
}

function hs_weweb_readme(): string {
    return "# Migração HubSkills LMS → WeWeb\n\n"
        . "O WeWeb é só o front-end: ele precisa de um backend de dados. Este pacote foi montado para os 3 caminhos mais comuns:\n\n"
        . "## Caminho 1 — WeWeb + Xano (recomendado para alto tráfego)\n"
        . "1. No Xano, crie as tabelas: courses, quizzes, users, enrollments, orders (os campos são exatamente as chaves dos JSONs em collections/).\n"
        . "2. Importe cada arquivo de collections/ via Xano > Database > Import (CSV/JSON).\n"
        . "3. No WeWeb, conecte o plugin Xano e aponte as coleções.\n"
        . "4. Senhas: se o export incluiu password_hash (phpass/bcrypt do WordPress), implemente a verificação no Xano (lambda) ou force redefinição de senha em massa — mais simples e seguro.\n\n"
        . "## Caminho 2 — WeWeb + Supabase\n"
        . "1. Crie um projeto Postgres no Supabase e use database.sql apenas como referência de dados (o schema do WP não é ideal para Postgres — prefira importar os JSONs de collections/ com o script de import do Supabase).\n"
        . "2. Conecte o plugin Supabase no WeWeb.\n\n"
        . "## Caminho 3 — Transição suave (WordPress vira só API)\n"
        . "Enquanto migra, o WeWeb pode consumir este site ao vivo via REST:\n"
        . "`GET {site}/wp-json/hubskills/v1/export/{collection}?page=1&per_page=100`\n"
        . "com o cabeçalho `X-HS-Export-Key` (gerado na tela Exportar WeWeb do admin).\n"
        . "Coleções: courses, quizzes, users, enrollments, orders, settings.\n"
        . "Assim o front sai do WordPress primeiro (resolvendo o gargalo de acessos) e o banco migra depois com calma.\n\n"
        . "## Checklist antes de desligar o WordPress\n"
        . "- [ ] Mídias copiadas de wp-content/uploads para CDN/S3 e URLs atualizadas\n"
        . "- [ ] Pagamentos: reconfigurar gateways no novo backend (webhooks mudam de URL!)\n"
        . "- [ ] E-mails transacionais reapontados\n"
        . "- [ ] Redirecionamentos 301 das URLs antigas\n"
        . "- [ ] Backup final (use a tela Backups AWS) guardado fora do servidor\n";
}

// ── REST: coleções ao vivo para o WeWeb ─────────────────────────────────────

function hs_weweb_get_api_key(): string {
    $key = get_option( 'hs_weweb_api_key' );
    if ( ! $key ) {
        $key = 'hswx_' . bin2hex( random_bytes( 20 ) );
        update_option( 'hs_weweb_api_key', $key, false );
    }
    return $key;
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'hubskills/v1', '/export/(?P<collection>[a-z_]+)', [
        'methods'             => 'GET',
        'callback'            => 'hs_weweb_rest_collection',
        'permission_callback' => '__return_true',
    ] );
} );

function hs_weweb_rest_collection( WP_REST_Request $request ) {
    $sent = $request->get_header( 'x-hs-export-key' ) ?: sanitize_text_field( $request['api_key'] ?? '' );
    if ( ! $sent || ! hash_equals( hs_weweb_get_api_key(), $sent ) ) {
        return new WP_Error( 'hs_weweb_forbidden', __( 'Chave de exportação inválida. Gere na tela Exportar (WeWeb).', 'hubskills-lms' ), [ 'status' => 401 ] );
    }

    $collections = hs_weweb_collections();
    $name        = sanitize_key( $request['collection'] );
    if ( ! isset( $collections[ $name ] ) ) {
        return new WP_Error( 'hs_weweb_no_collection', sprintf( __( 'Coleção inexistente. Disponíveis: %s', 'hubskills-lms' ), implode( ', ', array_keys( $collections ) ) ), [ 'status' => 404 ] );
    }

    $data = $name === 'users' ? $collections[ $name ]( false ) : $collections[ $name ]();
    if ( $name === 'settings' ) return rest_ensure_response( $data );

    $per_page = min( 500, max( 1, (int) ( $request['per_page'] ?: 100 ) ) );
    $page     = max( 1, (int) ( $request['page'] ?: 1 ) );
    $total    = count( $data );

    return rest_ensure_response( [
        'collection' => $name,
        'total'      => $total,
        'page'       => $page,
        'per_page'   => $per_page,
        'pages'      => (int) ceil( $total / $per_page ),
        'items'      => array_slice( $data, ( $page - 1 ) * $per_page, $per_page ),
    ] );
}

// ── Tela admin ──────────────────────────────────────────────────────────────

add_action( 'admin_menu', function () {
    add_submenu_page( 'hubskills-lms', __( 'Exportar (WeWeb)', 'hubskills-lms' ), '📦 ' . __( 'Exportar (WeWeb)', 'hubskills-lms' ), 'manage_options', 'hubskills-weweb', 'hs_weweb_admin_page' );
}, 31 );

add_action( 'admin_post_hs_weweb_download', 'hs_weweb_download' );
function hs_weweb_download() {
    if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'hs_weweb_download' ) ) {
        wp_die( __( 'Sem permissão.', 'hubskills-lms' ) );
    }
    $upload = wp_upload_dir();
    $dir    = trailingslashit( $upload['basedir'] ) . 'hs-exports';
    $file   = $dir . '/' . basename( sanitize_file_name( wp_unslash( $_GET['file'] ?? '' ) ) );
    if ( ! file_exists( $file ) || pathinfo( $file, PATHINFO_EXTENSION ) !== 'zip' ) {
        wp_die( __( 'Arquivo não encontrado.', 'hubskills-lms' ) );
    }
    nocache_headers();
    header( 'Content-Type: application/zip' );
    header( 'Content-Disposition: attachment; filename="' . basename( $file ) . '"' );
    header( 'Content-Length: ' . filesize( $file ) );
    readfile( $file );
    exit;
}

function hs_weweb_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $notice = '';
    if ( isset( $_POST['hs_weweb_nonce'] ) && wp_verify_nonce( $_POST['hs_weweb_nonce'], 'hs_weweb_export' ) ) {
        if ( ( $_POST['hs_weweb_action'] ?? '' ) === 'regen_key' ) {
            delete_option( 'hs_weweb_api_key' );
            hs_weweb_get_api_key();
            $notice = '<div class="notice notice-success"><p>' . esc_html__( 'Nova chave de API gerada. A anterior parou de funcionar.', 'hubskills-lms' ) . '</p></div>';
        } else {
            $result = hs_weweb_build_package( isset( $_POST['with_passwords'] ), isset( $_POST['with_sql'] ) );
            if ( is_wp_error( $result ) ) {
                $notice = '<div class="notice notice-error"><p>❌ ' . esc_html( $result->get_error_message() ) . '</p></div>';
            } else {
                if ( function_exists( 'hs_audit_log' ) ) hs_audit_log( get_current_user_id(), 'weweb_export', 'Pacote de exportação WeWeb gerado: ' . basename( $result ) );
                $url = wp_nonce_url( admin_url( 'admin-post.php?action=hs_weweb_download&file=' . rawurlencode( basename( $result ) ) ), 'hs_weweb_download' );
                $notice = '<div class="notice notice-success"><p>✅ ' . esc_html__( 'Pacote gerado!', 'hubskills-lms' ) . ' <a class="button button-primary" href="' . esc_url( $url ) . '">⬇️ ' . esc_html__( 'Baixar pacote', 'hubskills-lms' ) . '</a> <em>(' . esc_html( size_format( filesize( $result ) ) ) . ')</em></p></div>';
            }
        }
    }

    $key = hs_weweb_get_api_key();
    ?>
    <div class="wrap">
        <h1>📦 <?php _e( 'Exportar para WeWeb', 'hubskills-lms' ); ?></h1>
        <?php echo $notice; // já escapado acima ?>
        <p><?php _e( 'Para quando o site crescer além do WordPress: gera um pacote com todos os dados do LMS prontos para importar num backend (Xano/Supabase) e conectar ao WeWeb.', 'hubskills-lms' ); ?></p>

        <form method="post">
            <?php wp_nonce_field( 'hs_weweb_export', 'hs_weweb_nonce' ); ?>
            <h2><?php _e( '1. Pacote de migração (zip)', 'hubskills-lms' ); ?></h2>
            <p><?php _e( 'Inclui: cursos + currículo, quizzes, usuários, matrículas/progresso, pedidos e configurações, em JSON; mais manifest e guia de migração.', 'hubskills-lms' ); ?></p>
            <p><label><input type="checkbox" name="with_sql" checked> <?php _e( 'Incluir dump SQL completo (database.sql)', 'hubskills-lms' ); ?></label></p>
            <p><label><input type="checkbox" name="with_passwords"> <?php _e( 'Incluir hashes de senha dos usuários (só marque na migração final — trate o arquivo como confidencial)', 'hubskills-lms' ); ?></label></p>
            <p><button class="button button-primary" name="hs_weweb_action" value="export"><?php _e( '📦 Gerar pacote de exportação', 'hubskills-lms' ); ?></button></p>

            <h2><?php _e( '2. API ao vivo para o WeWeb (transição)', 'hubskills-lms' ); ?></h2>
            <p><?php _e( 'O WeWeb pode consumir o site como REST API enquanto a migração não termina:', 'hubskills-lms' ); ?></p>
            <p><code><?php echo esc_html( home_url( '/wp-json/hubskills/v1/export/courses?page=1&per_page=100' ) ); ?></code></p>
            <p><?php _e( 'Cabeçalho obrigatório:', 'hubskills-lms' ); ?> <code>X-HS-Export-Key: <?php echo esc_html( $key ); ?></code></p>
            <p class="description"><?php _e( 'Coleções: courses, quizzes, users (sem senhas), enrollments, orders, settings.', 'hubskills-lms' ); ?></p>
            <p><button class="button" name="hs_weweb_action" value="regen_key" onclick="return confirm('<?php echo esc_js( __( 'Gerar nova chave? A atual deixa de funcionar.', 'hubskills-lms' ) ); ?>')"><?php _e( '♻️ Gerar nova chave', 'hubskills-lms' ); ?></button></p>
        </form>
    </div>
    <?php
}
