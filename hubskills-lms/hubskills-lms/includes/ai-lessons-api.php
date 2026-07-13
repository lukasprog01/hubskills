<?php
/**
 * AI Lessons API — envio de aulas por agentes de IA com credencial do tutor.
 *
 * Modo seguro:
 *  - Token individual por tutor, gerado/revogado no próprio perfil (hash SHA-256 no banco).
 *  - Escopo restrito: apenas cursos cujo _hs_instructor_id é o dono do token.
 *  - A IA só cria/edita aulas no currículo (_hs_curriculum) — nunca publica, nunca apaga,
 *    nunca toca em preço, alunos, pagamentos ou configurações.
 *  - Campos editáveis em lista branca; conteúdo sanitizado por hs_lms_sanitize_curriculum().
 *  - Rate limit por tutor e auditoria de todas as escritas em hs_audit_log.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Configuração ────────────────────────────────────────────────────────────

function hs_ai_settings(): array {
    $defaults = [
        'enabled'     => '1',
        'rate_limit'  => 30,  // escritas por hora, por tutor
        'max_content' => 64000, // bytes por descrição de aula
    ];
    return wp_parse_args( (array) get_option( 'hs_ai_lessons', [] ), $defaults );
}

// ── Token do tutor (perfil) ─────────────────────────────────────────────────

function hs_ai_user_can_have_token( int $user_id ): bool {
    return function_exists( 'hs_is_instructor' ) && hs_is_instructor( $user_id )
        || user_can( $user_id, 'hs_edit_any_course' );
}

add_action( 'show_user_profile', 'hs_ai_profile_section' );
add_action( 'edit_user_profile', 'hs_ai_profile_section' );
function hs_ai_profile_section( WP_User $user ) {
    // O token só é gerenciado pelo próprio tutor (modo seguro) — admins veem apenas o status.
    if ( ! hs_ai_user_can_have_token( $user->ID ) ) return;
    $is_self  = get_current_user_id() === $user->ID;
    $hint     = get_user_meta( $user->ID, '_hs_ai_token_hint', true );
    $created  = get_user_meta( $user->ID, '_hs_ai_token_created', true );
    $last     = get_user_meta( $user->ID, '_hs_ai_token_last_used', true );
    $nonce    = wp_create_nonce( 'hs_ai_token' );
    ?>
    <h2>🤖 <?php _e( 'Acesso de IA (envio de aulas)', 'hubskills-lms' ); ?></h2>
    <table class="form-table" id="hs-ai-token-box">
        <tr>
            <th><?php _e( 'Token de API', 'hubskills-lms' ); ?></th>
            <td>
                <?php if ( $hint ) : ?>
                    <p><code>hsk_••••••••<?php echo esc_html( $hint ); ?></code>
                       — <?php printf( esc_html__( 'criado em %s', 'hubskills-lms' ), esc_html( $created ?: '—' ) ); ?>
                       · <?php printf( esc_html__( 'último uso: %s', 'hubskills-lms' ), esc_html( $last ?: __( 'nunca', 'hubskills-lms' ) ) ); ?></p>
                <?php else : ?>
                    <p><em><?php _e( 'Nenhum token ativo.', 'hubskills-lms' ); ?></em></p>
                <?php endif; ?>

                <?php if ( $is_self ) : ?>
                    <p>
                        <button type="button" class="button button-primary" id="hs-ai-generate"><?php echo $hint ? esc_html__( 'Gerar novo token (revoga o atual)', 'hubskills-lms' ) : esc_html__( 'Gerar token', 'hubskills-lms' ); ?></button>
                        <?php if ( $hint ) : ?>
                            <button type="button" class="button" id="hs-ai-revoke"><?php _e( 'Revogar token', 'hubskills-lms' ); ?></button>
                        <?php endif; ?>
                    </p>
                    <p id="hs-ai-token-result" style="display:none;background:#f0f6e6;border:1px solid #7ad03a;border-radius:4px;padding:10px;font-family:monospace;word-break:break-all"></p>
                    <p class="description">
                        <?php _e( 'Use este token no cabeçalho <code>Authorization: Bearer SEU_TOKEN</code>. A IA só consegue criar e editar aulas (como conteúdo do currículo) nos SEUS cursos — nunca publica o curso, nunca apaga nada e não acessa dados de alunos ou pagamentos. O token aparece UMA única vez: guarde-o.', 'hubskills-lms' ); ?>
                    </p>
                    <script>
                    (function(){
                        var nonce = '<?php echo esc_js( $nonce ); ?>';
                        function call(op){
                            var data = new FormData();
                            data.append('action','hs_ai_token_op');
                            data.append('op',op);
                            data.append('_wpnonce',nonce);
                            fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:data})
                                .then(function(r){return r.json();})
                                .then(function(j){
                                    var box = document.getElementById('hs-ai-token-result');
                                    if (j.success && j.data.token){
                                        box.style.display='block';
                                        box.textContent='✅ Token (copie agora, não será mostrado de novo): ' + j.data.token;
                                    } else if (j.success){
                                        location.reload();
                                    } else {
                                        alert(j.data && j.data.message ? j.data.message : 'Erro');
                                    }
                                });
                        }
                        var g=document.getElementById('hs-ai-generate'), r=document.getElementById('hs-ai-revoke');
                        if(g) g.addEventListener('click',function(){ call('generate'); });
                        if(r) r.addEventListener('click',function(){ if(confirm('<?php echo esc_js( __( 'Revogar o token atual? A IA perderá o acesso imediatamente.', 'hubskills-lms' ) ); ?>')) call('revoke'); });
                    })();
                    </script>
                <?php else : ?>
                    <p class="description"><?php _e( 'Apenas o próprio tutor pode gerar ou revogar o token (modo seguro).', 'hubskills-lms' ); ?></p>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php
}

add_action( 'wp_ajax_hs_ai_token_op', 'hs_ai_token_op' );
function hs_ai_token_op() {
    check_ajax_referer( 'hs_ai_token' );
    $user_id = get_current_user_id();
    if ( ! hs_ai_user_can_have_token( $user_id ) ) {
        wp_send_json_error( [ 'message' => __( 'Apenas tutores podem ter token de IA.', 'hubskills-lms' ) ] );
    }

    $op = sanitize_key( $_POST['op'] ?? '' );
    if ( $op === 'revoke' ) {
        delete_user_meta( $user_id, '_hs_ai_token_hash' );
        delete_user_meta( $user_id, '_hs_ai_token_hint' );
        if ( function_exists( 'hs_audit_log' ) ) hs_audit_log( $user_id, 'ai_token_revoked', 'Token de IA revogado pelo tutor.' );
        wp_send_json_success( [ 'revoked' => true ] );
    }

    if ( $op === 'generate' ) {
        $token = 'hsk_' . bin2hex( random_bytes( 24 ) );
        update_user_meta( $user_id, '_hs_ai_token_hash', hash( 'sha256', $token ) );
        update_user_meta( $user_id, '_hs_ai_token_hint', substr( $token, -4 ) );
        update_user_meta( $user_id, '_hs_ai_token_created', current_time( 'mysql' ) );
        delete_user_meta( $user_id, '_hs_ai_token_last_used' );
        if ( function_exists( 'hs_audit_log' ) ) hs_audit_log( $user_id, 'ai_token_generated', 'Novo token de IA gerado pelo tutor.' );
        wp_send_json_success( [ 'token' => $token ] );
    }

    wp_send_json_error( [ 'message' => __( 'Operação inválida.', 'hubskills-lms' ) ] );
}

// ── Autenticação das rotas ──────────────────────────────────────────────────

/**
 * Autentica a requisição pelo token Bearer e devolve o tutor dono.
 *
 * @return WP_User|WP_Error
 */
function hs_ai_authenticate( WP_REST_Request $request ) {
    $cfg = hs_ai_settings();
    if ( $cfg['enabled'] !== '1' ) {
        return new WP_Error( 'hs_ai_disabled', __( 'O acesso de IA está desativado nesta plataforma.', 'hubskills-lms' ), [ 'status' => 403 ] );
    }

    $auth = $request->get_header( 'authorization' ) ?: '';
    if ( ! preg_match( '/Bearer\s+(hsk_[a-f0-9]{48})/i', $auth, $m ) ) {
        return new WP_Error( 'hs_ai_no_token', __( 'Token ausente ou em formato inválido. Use Authorization: Bearer hsk_...', 'hubskills-lms' ), [ 'status' => 401 ] );
    }

    $users = get_users( [
        'meta_key'   => '_hs_ai_token_hash',
        'meta_value' => hash( 'sha256', $m[1] ),
        'number'     => 1,
        'fields'     => 'all',
    ] );
    if ( ! $users ) {
        return new WP_Error( 'hs_ai_bad_token', __( 'Token inválido ou revogado.', 'hubskills-lms' ), [ 'status' => 401 ] );
    }

    $user = $users[0];
    if ( ! hs_ai_user_can_have_token( $user->ID ) ) {
        return new WP_Error( 'hs_ai_not_tutor', __( 'Este usuário não é mais tutor.', 'hubskills-lms' ), [ 'status' => 403 ] );
    }

    update_user_meta( $user->ID, '_hs_ai_token_last_used', current_time( 'mysql' ) );
    return $user;
}

/** Rate limit de escrita: N requisições por hora por tutor. */
function hs_ai_check_rate_limit( int $user_id ) {
    $cfg   = hs_ai_settings();
    $key   = 'hs_ai_rl_' . $user_id;
    $count = (int) get_transient( $key );
    if ( $count >= (int) $cfg['rate_limit'] ) {
        return new WP_Error( 'hs_ai_rate_limited', sprintf( __( 'Limite de %d escritas por hora atingido. Tente mais tarde.', 'hubskills-lms' ), (int) $cfg['rate_limit'] ), [ 'status' => 429 ] );
    }
    set_transient( $key, $count + 1, HOUR_IN_SECONDS );
    return true;
}

/** Garante que o curso existe e pertence ao tutor. */
function hs_ai_own_course( int $course_id, int $user_id ) {
    $course = get_post( $course_id );
    if ( ! $course || $course->post_type !== 'hubskills_course' ) {
        return new WP_Error( 'hs_ai_no_course', __( 'Curso não encontrado.', 'hubskills-lms' ), [ 'status' => 404 ] );
    }
    if ( (int) get_post_meta( $course_id, '_hs_instructor_id', true ) !== $user_id ) {
        return new WP_Error( 'hs_ai_not_owner', __( 'Este curso não pertence ao tutor do token.', 'hubskills-lms' ), [ 'status' => 403 ] );
    }
    return $course;
}

// ── Rotas ───────────────────────────────────────────────────────────────────

add_action( 'rest_api_init', 'hs_ai_register_routes' );
function hs_ai_register_routes() {
    $ns = 'hubskills/v1';

    register_rest_route( $ns, '/ai/me', [
        'methods'             => 'GET',
        'callback'            => 'hs_ai_route_me',
        'permission_callback' => '__return_true',
    ] );

    register_rest_route( $ns, '/ai/courses', [
        'methods'             => 'GET',
        'callback'            => 'hs_ai_route_courses',
        'permission_callback' => '__return_true',
    ] );

    register_rest_route( $ns, '/ai/courses/(?P<id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'hs_ai_route_course',
        'permission_callback' => '__return_true',
    ] );

    register_rest_route( $ns, '/ai/courses/(?P<id>\d+)/lessons', [
        'methods'             => 'POST',
        'callback'            => 'hs_ai_route_create_lesson',
        'permission_callback' => '__return_true',
    ] );

    register_rest_route( $ns, '/ai/courses/(?P<id>\d+)/lessons/(?P<lesson_id>\d+)', [
        'methods'             => 'PUT, PATCH',
        'callback'            => 'hs_ai_route_update_lesson',
        'permission_callback' => '__return_true',
    ] );
}

function hs_ai_route_me( WP_REST_Request $request ) {
    $user = hs_ai_authenticate( $request );
    if ( is_wp_error( $user ) ) return $user;

    return rest_ensure_response( [
        'tutor' => [ 'id' => $user->ID, 'name' => $user->display_name ],
        'scope' => [ 'read_own_courses', 'create_lessons', 'edit_lessons' ],
        'safe_mode' => [
            'publica_curso'      => false,
            'apaga_conteudo'     => false,
            'acessa_alunos'      => false,
            'acessa_pagamentos'  => false,
            'rate_limit_hora'    => (int) hs_ai_settings()['rate_limit'],
        ],
    ] );
}

function hs_ai_route_courses( WP_REST_Request $request ) {
    $user = hs_ai_authenticate( $request );
    if ( is_wp_error( $user ) ) return $user;

    $courses = get_posts( [
        'post_type'   => 'hubskills_course',
        'post_status' => [ 'publish', 'draft', 'pending' ],
        'numberposts' => 100,
        'meta_query'  => [ [ 'key' => '_hs_instructor_id', 'value' => $user->ID ] ],
    ] );

    $out = [];
    foreach ( $courses as $c ) {
        $curriculum = get_post_meta( $c->ID, '_hs_curriculum', true ) ?: [];
        $out[] = [
            'id'       => $c->ID,
            'title'    => $c->post_title,
            'status'   => $c->post_status,
            'sections' => array_values( wp_list_pluck( (array) $curriculum, 'section' ) ),
            'lessons'  => (int) get_post_meta( $c->ID, '_hs_lessons_count', true ),
        ];
    }
    return rest_ensure_response( $out );
}

function hs_ai_route_course( WP_REST_Request $request ) {
    $user = hs_ai_authenticate( $request );
    if ( is_wp_error( $user ) ) return $user;

    $course = hs_ai_own_course( (int) $request['id'], $user->ID );
    if ( is_wp_error( $course ) ) return $course;

    return rest_ensure_response( [
        'id'         => $course->ID,
        'title'      => $course->post_title,
        'status'     => $course->post_status,
        'curriculum' => get_post_meta( $course->ID, '_hs_curriculum', true ) ?: [],
    ] );
}

/** Campos de aula aceitos da IA (lista branca). */
function hs_ai_lesson_input( WP_REST_Request $request ): array {
    $cfg  = hs_ai_settings();
    $body = $request->get_json_params() ?: $request->get_body_params();
    $desc = (string) ( $body['description'] ?? '' );
    if ( strlen( $desc ) > (int) $cfg['max_content'] ) {
        $desc = substr( $desc, 0, (int) $cfg['max_content'] );
    }
    return [
        'title'          => (string) ( $body['title'] ?? '' ),
        'duration'       => (string) ( $body['duration'] ?? '' ),
        'type'           => (string) ( $body['type'] ?? 'video' ),
        'description'    => $desc,
        'video_provider' => (string) ( $body['video_provider'] ?? 'none' ),
        'video_url'      => (string) ( $body['video_url'] ?? '' ),
        'section'        => sanitize_text_field( (string) ( $body['section'] ?? '' ) ),
    ];
}

function hs_ai_route_create_lesson( WP_REST_Request $request ) {
    $user = hs_ai_authenticate( $request );
    if ( is_wp_error( $user ) ) return $user;

    $rl = hs_ai_check_rate_limit( $user->ID );
    if ( is_wp_error( $rl ) ) return $rl;

    $course = hs_ai_own_course( (int) $request['id'], $user->ID );
    if ( is_wp_error( $course ) ) return $course;

    $input = hs_ai_lesson_input( $request );
    if ( $input['title'] === '' ) {
        return new WP_Error( 'hs_ai_no_title', __( 'O campo title é obrigatório.', 'hubskills-lms' ), [ 'status' => 400 ] );
    }

    $curriculum = get_post_meta( $course->ID, '_hs_curriculum', true ) ?: [];
    $section    = $input['section'] !== '' ? $input['section'] : __( 'Aulas geradas por IA', 'hubskills-lms' );
    unset( $input['section'] );
    $input['id'] = 0; // hs_lms_sanitize_curriculum gera id novo

    $found = false;
    foreach ( $curriculum as &$sec ) {
        if ( ( $sec['section'] ?? '' ) === $section ) {
            $sec['lessons'][] = $input;
            $found = true;
            break;
        }
    }
    unset( $sec );
    if ( ! $found ) {
        $curriculum[] = [ 'section' => $section, 'lessons' => [ $input ] ];
    }

    $curriculum = hs_lms_sanitize_curriculum( $curriculum );
    update_post_meta( $course->ID, '_hs_curriculum', $curriculum );
    hs_ai_refresh_lessons_count( $course->ID, $curriculum );

    // Recupera a aula recém-criada (última da seção)
    $new_lesson = null;
    foreach ( $curriculum as $sec2 ) {
        if ( $sec2['section'] === $section ) $new_lesson = end( $sec2['lessons'] );
    }

    if ( function_exists( 'hs_audit_log' ) ) {
        hs_audit_log( $user->ID, 'ai_lesson_created', sprintf( 'IA criou a aula "%s" no curso #%d (%s), seção "%s".', $new_lesson['title'] ?? '?', $course->ID, $course->post_title, $section ) );
    }

    return rest_ensure_response( [
        'success' => true,
        'course'  => $course->ID,
        'section' => $section,
        'lesson'  => $new_lesson,
        'note'    => __( 'Aula adicionada ao currículo. O curso NÃO foi publicado — revise e publique pelo painel.', 'hubskills-lms' ),
    ] );
}

function hs_ai_route_update_lesson( WP_REST_Request $request ) {
    $user = hs_ai_authenticate( $request );
    if ( is_wp_error( $user ) ) return $user;

    $rl = hs_ai_check_rate_limit( $user->ID );
    if ( is_wp_error( $rl ) ) return $rl;

    $course = hs_ai_own_course( (int) $request['id'], $user->ID );
    if ( is_wp_error( $course ) ) return $course;

    $lesson_id  = (int) $request['lesson_id'];
    $input      = hs_ai_lesson_input( $request );
    $curriculum = get_post_meta( $course->ID, '_hs_curriculum', true ) ?: [];

    $updated = null;
    foreach ( $curriculum as &$sec ) {
        foreach ( (array) ( $sec['lessons'] ?? [] ) as &$lesson ) {
            if ( (int) ( $lesson['id'] ?? 0 ) !== $lesson_id ) continue;
            // Lista branca: só sobrescreve o que veio preenchido.
            foreach ( [ 'title', 'duration', 'type', 'description', 'video_provider', 'video_url' ] as $field ) {
                if ( $input[ $field ] !== '' ) $lesson[ $field ] = $input[ $field ];
            }
            $updated = $lesson;
            break 2;
        }
    }
    unset( $sec, $lesson );

    if ( ! $updated ) {
        return new WP_Error( 'hs_ai_no_lesson', __( 'Aula não encontrada neste curso.', 'hubskills-lms' ), [ 'status' => 404 ] );
    }

    $curriculum = hs_lms_sanitize_curriculum( $curriculum );
    update_post_meta( $course->ID, '_hs_curriculum', $curriculum );
    hs_ai_refresh_lessons_count( $course->ID, $curriculum );

    if ( function_exists( 'hs_audit_log' ) ) {
        hs_audit_log( $user->ID, 'ai_lesson_updated', sprintf( 'IA editou a aula #%d ("%s") no curso #%d (%s).', $lesson_id, $updated['title'] ?? '?', $course->ID, $course->post_title ) );
    }

    return rest_ensure_response( [ 'success' => true, 'course' => $course->ID, 'lesson_id' => $lesson_id ] );
}

function hs_ai_refresh_lessons_count( int $course_id, array $curriculum ) {
    $count = 0;
    foreach ( $curriculum as $section ) $count += count( (array) ( $section['lessons'] ?? [] ) );
    update_post_meta( $course_id, '_hs_lessons_count', $count );
}
