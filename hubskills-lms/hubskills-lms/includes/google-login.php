<?php
/**
 * Login com Google (OAuth 2.0) — configurável pelo administrador master.
 *
 * - Tela de configuração (manage_options): ativar, Client ID, Client Secret, criar usuários.
 * - Botão "Entrar com Google" na tela de login (só aparece se ativado e configurado).
 * - Fluxo OAuth sem SDK (wp_remote_post): code → token → userinfo → login/cria usuário.
 * - State com nonce (CSRF), auditoria de cada login social.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function hs_google_settings(): array {
    $defaults = [
        'enabled'       => '0',
        'client_id'     => '',
        'client_secret' => '',
        'create_users'  => '1', // cria conta de aluno automaticamente no 1º login
    ];
    return wp_parse_args( (array) get_option( 'hs_google_login', [] ), $defaults );
}

function hs_google_is_ready(): bool {
    $c = hs_google_settings();
    return $c['enabled'] === '1' && $c['client_id'] !== '' && $c['client_secret'] !== '';
}

/** URI de redirecionamento que deve ser cadastrada no Google Cloud Console. */
function hs_google_redirect_uri(): string {
    return add_query_arg( 'action', 'hs_google_callback', wp_login_url() );
}

// ── Botão na tela de login ──────────────────────────────────────────────────

add_action( 'login_form', 'hs_google_login_button' );
function hs_google_login_button() {
    if ( ! hs_google_is_ready() ) return;
    $url = add_query_arg( 'action', 'hs_google', wp_login_url() );
    // preserva o redirect_to, se houver
    if ( ! empty( $_REQUEST['redirect_to'] ) ) {
        $url = add_query_arg( 'redirect_to', rawurlencode( wp_unslash( $_REQUEST['redirect_to'] ) ), $url );
    }
    ?>
    <style>
        .hs-google-sep{display:flex;align-items:center;text-align:center;color:#8a93a3;font-size:12px;margin:18px 0 14px}
        .hs-google-sep::before,.hs-google-sep::after{content:"";flex:1;height:1px;background:#e0e0e0}
        .hs-google-sep span{padding:0 12px}
        .hs-google-btn{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;
            padding:11px 14px;border:1.5px solid #dadce0;border-radius:10px;background:#fff;color:#3c4043;
            font-family:'Poppins',system-ui,sans-serif;font-weight:600;font-size:14px;text-decoration:none;
            transition:background .2s,box-shadow .2s;box-sizing:border-box}
        .hs-google-btn:hover{background:#f7f8fa;box-shadow:0 1px 6px rgba(0,0,0,.12)}
        .hs-google-btn svg{width:18px;height:18px;flex:none}
    </style>
    <div class="hs-google-sep"><span><?php esc_html_e( 'ou', 'hubskills-lms' ); ?></span></div>
    <a href="<?php echo esc_url( $url ); ?>" class="hs-google-btn">
        <svg viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.71-1.57 2.68-3.89 2.68-6.62z"/>
            <path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.8.54-1.84.86-3.04.86-2.34 0-4.32-1.58-5.03-3.7H.96v2.33A9 9 0 0 0 9 18z"/>
            <path fill="#FBBC05" d="M3.97 10.72a5.4 5.4 0 0 1 0-3.44V4.95H.96a9 9 0 0 0 0 8.1l3.01-2.33z"/>
            <path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58C13.46.9 11.43 0 9 0A9 9 0 0 0 .96 4.95l3.01 2.33C4.68 5.16 6.66 3.58 9 3.58z"/>
        </svg>
        <?php esc_html_e( 'Entrar com Google', 'hubskills-lms' ); ?>
    </a>
    <?php
}

// ── Fluxo OAuth ─────────────────────────────────────────────────────────────

// Passo 1: redireciona o usuário para o consentimento do Google
add_action( 'login_form_hs_google', 'hs_google_start' );
function hs_google_start() {
    if ( ! hs_google_is_ready() ) wp_die( __( 'Login com Google não está configurado.', 'hubskills-lms' ) );

    $c     = hs_google_settings();
    $state = wp_generate_password( 24, false );
    set_transient( 'hs_google_state_' . $state, [
        'redirect_to' => isset( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : '',
    ], 10 * MINUTE_IN_SECONDS );

    $params = [
        'client_id'     => $c['client_id'],
        'redirect_uri'  => hs_google_redirect_uri(),
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ];
    wp_redirect( 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params ) );
    exit;
}

// Passo 2: callback — troca o code por token, pega o perfil e loga/cria o usuário
add_action( 'login_form_hs_google_callback', 'hs_google_callback' );
function hs_google_callback() {
    if ( ! hs_google_is_ready() ) wp_die( __( 'Login com Google não está configurado.', 'hubskills-lms' ) );

    $state = sanitize_text_field( $_GET['state'] ?? '' );
    $saved = get_transient( 'hs_google_state_' . $state );
    if ( $state === '' || $saved === false ) {
        wp_die( __( 'Sessão de login com Google expirada ou inválida. Tente novamente.', 'hubskills-lms' ) );
    }
    delete_transient( 'hs_google_state_' . $state );

    if ( ! empty( $_GET['error'] ) ) {
        wp_redirect( wp_login_url() );
        exit;
    }
    $code = sanitize_text_field( $_GET['code'] ?? '' );
    if ( $code === '' ) wp_die( __( 'Código de autorização ausente.', 'hubskills-lms' ) );

    $c = hs_google_settings();

    // Troca code → token
    $resp = wp_remote_post( 'https://oauth2.googleapis.com/token', [
        'timeout' => 15,
        'body'    => [
            'code'          => $code,
            'client_id'     => $c['client_id'],
            'client_secret' => $c['client_secret'],
            'redirect_uri'  => hs_google_redirect_uri(),
            'grant_type'    => 'authorization_code',
        ],
    ] );
    if ( is_wp_error( $resp ) ) wp_die( esc_html( $resp->get_error_message() ) );
    $token = json_decode( wp_remote_retrieve_body( $resp ), true );
    if ( empty( $token['access_token'] ) ) {
        wp_die( __( 'Falha ao obter o token do Google. Verifique Client ID/Secret e a URI de redirecionamento.', 'hubskills-lms' ) );
    }

    // Perfil do usuário
    $info = wp_remote_get( 'https://www.googleapis.com/oauth2/v3/userinfo', [
        'timeout' => 15,
        'headers' => [ 'Authorization' => 'Bearer ' . $token['access_token'] ],
    ] );
    if ( is_wp_error( $info ) ) wp_die( esc_html( $info->get_error_message() ) );
    $profile = json_decode( wp_remote_retrieve_body( $info ), true );

    $email = sanitize_email( $profile['email'] ?? '' );
    if ( ! $email || empty( $profile['email_verified'] ) ) {
        wp_die( __( 'Não foi possível validar seu e-mail no Google.', 'hubskills-lms' ) );
    }

    // Encontra ou cria o usuário
    $user = get_user_by( 'email', $email );
    if ( ! $user ) {
        if ( $c['create_users'] !== '1' ) {
            wp_die( __( 'Sua conta Google não está cadastrada nesta plataforma. Procure o administrador.', 'hubskills-lms' ) );
        }
        $username = hs_google_unique_login( $email );
        $user_id  = wp_insert_user( [
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => wp_generate_password( 24 ),
            'display_name' => sanitize_text_field( $profile['name'] ?? $username ),
            'first_name'   => sanitize_text_field( $profile['given_name'] ?? '' ),
            'last_name'    => sanitize_text_field( $profile['family_name'] ?? '' ),
            'role'         => 'hs_student',
        ] );
        if ( is_wp_error( $user_id ) ) wp_die( esc_html( $user_id->get_error_message() ) );
        update_user_meta( $user_id, 'hs_google_linked', 1 );
        $user = get_user_by( 'id', $user_id );
        if ( function_exists( 'hs_audit_log' ) ) hs_audit_log( $user_id, 'google_signup', 'Conta criada via login com Google: ' . $email );
    }

    // Autentica
    wp_set_current_user( $user->ID );
    wp_set_auth_cookie( $user->ID, true );
    do_action( 'wp_login', $user->user_login, $user );
    if ( function_exists( 'hs_audit_log' ) ) hs_audit_log( $user->ID, 'google_login', 'Login via Google: ' . $email );

    // Redireciona (mesma lógica de papel do tema, se existir)
    $redirect = ! empty( $saved['redirect_to'] ) ? $saved['redirect_to'] : '';
    $redirect = apply_filters( 'login_redirect', $redirect ?: admin_url(), $redirect, $user );
    wp_safe_redirect( $redirect );
    exit;
}

function hs_google_unique_login( string $email ): string {
    $base = sanitize_user( current( explode( '@', $email ) ), true ) ?: 'aluno';
    $login = $base;
    $i = 1;
    while ( username_exists( $login ) ) $login = $base . $i++;
    return $login;
}

// ── Tela de configuração (admin master) ─────────────────────────────────────

add_action( 'admin_menu', function () {
    add_submenu_page( 'hubskills-lms', __( 'Login com Google', 'hubskills-lms' ), '🔑 ' . __( 'Login Google', 'hubskills-lms' ), 'manage_options', 'hubskills-google-login', 'hs_google_admin_page' );
}, 33 );

function hs_google_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    if ( isset( $_POST['hs_google_nonce'] ) && wp_verify_nonce( $_POST['hs_google_nonce'], 'hs_google_save' ) ) {
        $cur = hs_google_settings();
        update_option( 'hs_google_login', [
            'enabled'       => isset( $_POST['enabled'] ) ? '1' : '0',
            'client_id'     => sanitize_text_field( $_POST['client_id'] ?? '' ),
            'client_secret' => $_POST['client_secret'] !== '' ? sanitize_text_field( $_POST['client_secret'] ) : $cur['client_secret'],
            'create_users'  => isset( $_POST['create_users'] ) ? '1' : '0',
        ] );
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Configurações salvas.', 'hubskills-lms' ) . '</p></div>';
    }

    $c = hs_google_settings();
    ?>
    <div class="wrap">
        <h1>🔑 <?php _e( 'Login com Google', 'hubskills-lms' ); ?></h1>
        <p><?php _e( 'Permite que alunos entrem com a conta Google. Configure um "OAuth Client ID" (tipo: Aplicativo da Web) no Google Cloud Console.', 'hubskills-lms' ); ?></p>

        <div class="notice notice-info inline" style="padding:12px">
            <p style="margin:0 0 6px"><strong><?php _e( 'Cole esta URI de redirecionamento autorizada no Google:', 'hubskills-lms' ); ?></strong></p>
            <code style="user-select:all"><?php echo esc_html( hs_google_redirect_uri() ); ?></code>
            <p style="margin:8px 0 0"><?php _e( 'E como "Origem JavaScript autorizada":', 'hubskills-lms' ); ?> <code style="user-select:all"><?php echo esc_html( home_url() ); ?></code></p>
        </div>

        <form method="post">
            <?php wp_nonce_field( 'hs_google_save', 'hs_google_nonce' ); ?>
            <table class="form-table">
                <tr><th><?php _e( 'Ativar login com Google', 'hubskills-lms' ); ?></th>
                    <td><label><input type="checkbox" name="enabled" <?php checked( $c['enabled'], '1' ); ?>> <?php _e( 'Mostrar o botão "Entrar com Google" na tela de login', 'hubskills-lms' ); ?></label></td></tr>
                <tr><th><?php _e( 'Client ID', 'hubskills-lms' ); ?></th>
                    <td><input type="text" name="client_id" value="<?php echo esc_attr( $c['client_id'] ); ?>" class="regular-text" autocomplete="off" placeholder="123456789-xxxx.apps.googleusercontent.com"></td></tr>
                <tr><th><?php _e( 'Client Secret', 'hubskills-lms' ); ?></th>
                    <td><input type="password" name="client_secret" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo $c['client_secret'] ? esc_attr__( '•••••• (preencha só para trocar)', 'hubskills-lms' ) : ''; ?>"></td></tr>
                <tr><th><?php _e( 'Criar conta automaticamente', 'hubskills-lms' ); ?></th>
                    <td><label><input type="checkbox" name="create_users" <?php checked( $c['create_users'], '1' ); ?>> <?php _e( 'No primeiro acesso, criar uma conta de aluno para e-mails ainda não cadastrados', 'hubskills-lms' ); ?></label></td></tr>
            </table>
            <p><button class="button button-primary"><?php _e( 'Salvar', 'hubskills-lms' ); ?></button>
               <?php if ( hs_google_is_ready() ) : ?><span style="color:#22c55e;margin-left:10px">✓ <?php _e( 'Configurado e ativo', 'hubskills-lms' ); ?></span><?php endif; ?></p>
        </form>

        <h2><?php _e( 'Passo a passo rápido', 'hubskills-lms' ); ?></h2>
        <ol>
            <li><?php _e( 'Acesse console.cloud.google.com → APIs e Serviços → Credenciais.', 'hubskills-lms' ); ?></li>
            <li><?php _e( 'Criar credenciais → ID do cliente OAuth → Aplicativo da Web.', 'hubskills-lms' ); ?></li>
            <li><?php _e( 'Cole a Origem e a URI de redirecionamento mostradas acima.', 'hubskills-lms' ); ?></li>
            <li><?php _e( 'Copie o Client ID e o Client Secret para os campos acima e ative.', 'hubskills-lms' ); ?></li>
        </ol>
    </div>
    <?php
}
