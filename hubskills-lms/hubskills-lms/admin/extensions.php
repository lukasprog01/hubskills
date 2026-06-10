<?php
/**
 * HubSkills LMS — Página de Extensões (módulos premium)
 *
 * Vitrine dos módulos adicionais. O botão "Ativar módulo" leva para a
 * página oficial do módulo, onde acontece a compra e a liberação da
 * licença (modelo freemium padrão do ecossistema WordPress).
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

/**
 * Módulos ativos (licenciados). Por enquanto nenhum — o array será
 * preenchido pelo sistema de licenças quando os módulos existirem.
 */
function hs_lms_active_extensions(): array {
    return (array) apply_filters( 'hs_lms_active_extensions', get_option( 'hs_lms_active_extensions', [] ) );
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

    $store  = hs_lms_extensions_store_url();
    $active = hs_lms_active_extensions();
    ?>
    <div class="wrap hs-extensions-wrap">
        <h1 style="display:flex;align-items:center;gap:10px">🧩 <?php esc_html_e( 'Extensões HubSkills', 'hubskills-lms' ); ?></h1>
        <p style="font-size:14px;color:#666;max-width:720px">
            <?php esc_html_e( 'Amplie o seu LMS com módulos oficiais. Ao clicar em "Ativar módulo" você será levado à página oficial para concluir a compra — após o pagamento, você recebe o arquivo do módulo e a chave de licença para liberar a funcionalidade.', 'hubskills-lms' ); ?>
        </p>

        <div class="hs-ext-grid">
            <?php foreach ( hs_lms_extensions_catalog() as $slug => $ext ) :
                $is_active = in_array( $slug, $active, true );
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
                <div class="hs-ext-actions">
                    <?php if ( $is_active ) : ?>
                        <span class="hs-ext-active">✅ <?php esc_html_e( 'Módulo ativo', 'hubskills-lms' ); ?></span>
                    <?php else : ?>
                        <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
                            <?php esc_html_e( 'Ativar módulo', 'hubskills-lms' ); ?> ↗
                        </a>
                        <span class="hs-ext-hint"><?php esc_html_e( 'Compra na página oficial', 'hubskills-lms' ); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="hs-ext-footer">
            <p>
                🔑 <?php esc_html_e( 'Já comprou um módulo? Após instalar o arquivo recebido, a chave de licença é inserida na tela do próprio módulo.', 'hubskills-lms' ); ?>
                <a href="<?php echo esc_url( $store ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Central de compras e suporte', 'hubskills-lms' ); ?> ↗</a>
            </p>
        </div>
    </div>

    <style>
    .hs-ext-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:20px; margin-top:20px; }
    .hs-ext-card { background:#fff; border:1px solid #e2e2e2; border-radius:10px; padding:20px; display:flex; flex-direction:column; gap:12px; box-shadow:0 1px 2px rgba(0,0,0,.04); }
    .hs-ext-head { display:flex; align-items:center; gap:12px; }
    .hs-ext-icon { font-size:26px; width:52px; height:52px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .hs-ext-card h3 { margin:0; font-size:16px; }
    .hs-ext-tag { display:inline-block; color:#fff; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; padding:2px 8px; border-radius:99px; margin-top:4px; }
    .hs-ext-card p { margin:0; color:#555; font-size:13px; line-height:1.5; flex:1; }
    .hs-ext-actions { display:flex; align-items:center; gap:10px; }
    .hs-ext-hint { font-size:11px; color:#999; }
    .hs-ext-active { color:#10B981; font-weight:600; font-size:13px; }
    .hs-ext-footer { margin-top:24px; padding:14px 18px; background:#f8f6ff; border:1px solid #e6e0fa; border-radius:8px; max-width:900px; }
    .hs-ext-footer p { margin:0; font-size:13px; color:#555; }
    </style>
    <?php
}
