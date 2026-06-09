<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Menu registration moved to admin-dashboard.php (single unified menu).

add_action( 'admin_init', 'hs_admin_register_settings' );
function hs_admin_register_settings() {
    register_setting( 'hs_lms_options', 'hs_lms_options', 'hs_sanitize_options' );

    add_settings_section( 'hs_general', __('Geral', 'hubskills-lms'), null, 'hubskills-lms' );
    add_settings_section( 'hs_gamification', __('Gamificação', 'hubskills-lms'), null, 'hubskills-lms' );

    $fields = [
        'platform_name'       => [ 'section' => 'hs_general',       'label' => __('Nome da Plataforma','hubskills-lms'), 'default' => 'HubSkills', 'type' => 'text' ],
        'xp_per_lesson'       => [ 'section' => 'hs_gamification',   'label' => __('XP por Aula Concluída','hubskills-lms'), 'default' => 50, 'type' => 'number' ],
        'xp_per_quiz_pass'    => [ 'section' => 'hs_gamification',   'label' => __('XP por Quiz Aprovado','hubskills-lms'), 'default' => 200, 'type' => 'number' ],
        'xp_per_course'       => [ 'section' => 'hs_gamification',   'label' => __('XP por Curso Concluído','hubskills-lms'), 'default' => 500, 'type' => 'number' ],
        'quiz_pass_threshold' => [ 'section' => 'hs_gamification',   'label' => __('Nota Mínima Quiz (%)','hubskills-lms'), 'default' => 70, 'type' => 'number' ],
    ];

    foreach ( $fields as $id => $f ) {
        add_settings_field( $id, $f['label'], 'hs_field_cb', 'hubskills-lms', $f['section'], [ 'id' => $id, 'type' => $f['type'], 'default' => $f['default'] ] );
    }
}

function hs_field_cb( array $args ) {
    $opts = get_option('hs_lms_options', []);
    $val  = $opts[$args['id']] ?? $args['default'];
    echo '<input type="' . esc_attr($args['type']) . '" name="hs_lms_options[' . esc_attr($args['id']) . ']" value="' . esc_attr($val) . '" class="regular-text">';
}

function hs_sanitize_options( array $input ): array {
    return array_map( 'sanitize_text_field', $input );
}

function hs_get_option( string $key, $default = '' ) {
    $opts = get_option('hs_lms_options', []);
    return $opts[$key] ?? $default;
}

function hs_admin_settings_page() { ?>
<div class="wrap">
    <h1><?php _e('HubSkills LMS — Configurações', 'hubskills-lms'); ?></h1>
    <form method="post" action="options.php">
        <?php settings_fields('hs_lms_options'); do_settings_sections('hubskills-lms'); submit_button(); ?>
    </form>
</div>
<?php }

function hs_admin_reports_page() {
    $user_count  = count_users();
    $course_count= wp_count_posts('hubskills_course')->publish;
    $enrolled_count = 0;
    $users = get_users(['meta_key'=>'hs_enrollments','fields'=>'ID']);
    foreach ($users as $uid) {
        $e = get_user_meta($uid,'hs_enrollments',true);
        $enrolled_count += is_array($e) ? count($e) : 0;
    }
    ?>
<div class="wrap">
    <h1><?php _e('HubSkills LMS — Relatórios', 'hubskills-lms'); ?></h1>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-top:20px">
        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;text-align:center">
            <div style="font-size:2rem;font-weight:800;color:#7C3AED"><?php echo (int)$user_count['total_users']; ?></div>
            <div style="color:#666"><?php _e('Total de Usuários', 'hubskills-lms'); ?></div>
        </div>
        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;text-align:center">
            <div style="font-size:2rem;font-weight:800;color:#06B6D4"><?php echo (int)$course_count; ?></div>
            <div style="color:#666"><?php _e('Cursos Publicados', 'hubskills-lms'); ?></div>
        </div>
        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;text-align:center">
            <div style="font-size:2rem;font-weight:800;color:#10B981"><?php echo (int)$enrolled_count; ?></div>
            <div style="color:#666"><?php _e('Total de Matrículas', 'hubskills-lms'); ?></div>
        </div>
    </div>
</div>
    <?php
}
