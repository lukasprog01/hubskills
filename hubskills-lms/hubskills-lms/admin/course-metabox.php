<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'add_meta_boxes', 'hs_add_course_metaboxes' );
function hs_add_course_metaboxes() {
    add_meta_box( 'hs_course_details', __('Detalhes do Curso', 'hubskills-lms'), 'hs_course_details_cb', 'hubskills_course', 'normal', 'high' );
    add_meta_box( 'hs_course_curriculum', __('Currículo / Aulas', 'hubskills-lms'), 'hs_curriculum_cb', 'hubskills_course', 'normal', 'default' );
    add_meta_box( 'hs_course_certificate', __('Certificado Automático', 'hubskills-lms'), 'hs_course_certificate_cb', 'hubskills_course', 'side', 'default' );
    add_meta_box( 'hs_quiz_questions', __('Questões do Quiz', 'hubskills-lms'), 'hs_quiz_questions_cb', 'hubskills_quiz', 'normal', 'high' );
}

// ── Course Details ────────────────────────────────────────
function hs_course_details_cb( WP_Post $post ) {
    wp_nonce_field( 'hs_save_course_meta', 'hs_course_nonce' );
    $meta = [
        '_hs_instructor'   => get_post_meta($post->ID,'_hs_instructor',true),
        '_hs_rating'       => get_post_meta($post->ID,'_hs_rating',true),
        '_hs_students'     => get_post_meta($post->ID,'_hs_students',true),
        '_hs_duration'     => get_post_meta($post->ID,'_hs_duration',true),
        '_hs_level'        => get_post_meta($post->ID,'_hs_level',true),
        '_hs_price'        => get_post_meta($post->ID,'_hs_price',true),
        '_hs_icon'         => get_post_meta($post->ID,'_hs_icon',true),
        '_hs_color_start'  => get_post_meta($post->ID,'_hs_color_start',true) ?: '#7C3AED',
        '_hs_color_end'    => get_post_meta($post->ID,'_hs_color_end',true) ?: '#06B6D4',
        '_hs_featured'     => get_post_meta($post->ID,'_hs_featured',true),
        '_hs_lessons_count'=> get_post_meta($post->ID,'_hs_lessons_count',true),
    ];
    ?>
    <style>
    .hs-meta-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    .hs-field { display:flex; flex-direction:column; gap:4px; }
    .hs-field label { font-weight:600; font-size:12px; text-transform:uppercase; color:#666; }
    .hs-field input, .hs-field select { border:1px solid #ddd; border-radius:4px; padding:6px 10px; width:100%; }
    </style>
    <div class="hs-meta-grid">
        <div class="hs-field">
            <label><?php _e('Instrutor', 'hubskills-lms'); ?></label>
            <input type="text" name="_hs_instructor" value="<?php echo esc_attr($meta['_hs_instructor']); ?>" placeholder="Prof. Nome Sobrenome">
        </div>
        <div class="hs-field">
            <label><?php _e('Nível', 'hubskills-lms'); ?></label>
            <select name="_hs_level">
                <?php foreach (['Iniciante','Intermediário','Avançado'] as $lv) : ?>
                <option value="<?php echo esc_attr($lv); ?>" <?php selected($meta['_hs_level'],$lv); ?>><?php echo esc_html($lv); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="hs-field">
            <label><?php _e('Duração (ex: 42h)', 'hubskills-lms'); ?></label>
            <input type="text" name="_hs_duration" value="<?php echo esc_attr($meta['_hs_duration']); ?>" placeholder="42h">
        </div>
        <div class="hs-field">
            <label><?php _e('Nº de Aulas', 'hubskills-lms'); ?></label>
            <input type="number" name="_hs_lessons_count" value="<?php echo (int)$meta['_hs_lessons_count']; ?>" min="0">
        </div>
        <div class="hs-field">
            <label><?php _e('Avaliação (0-5)', 'hubskills-lms'); ?></label>
            <input type="number" name="_hs_rating" value="<?php echo esc_attr($meta['_hs_rating']); ?>" step="0.1" min="0" max="5">
        </div>
        <div class="hs-field">
            <label><?php _e('Número de Alunos', 'hubskills-lms'); ?></label>
            <input type="number" name="_hs_students" value="<?php echo (int)$meta['_hs_students']; ?>" min="0">
        </div>
        <div class="hs-field">
            <label><?php _e('Preço (vazio = Grátis)', 'hubskills-lms'); ?></label>
            <input type="text" name="_hs_price" value="<?php echo esc_attr($meta['_hs_price']); ?>" placeholder="R$ 97,00 ou Grátis">
        </div>
        <div class="hs-field">
            <label><?php _e('Ícone (emoji)', 'hubskills-lms'); ?></label>
            <input type="text" name="_hs_icon" value="<?php echo esc_attr($meta['_hs_icon']); ?>" placeholder="⚡">
        </div>
        <div class="hs-field">
            <label><?php _e('Cor Gradiente Início (hex)', 'hubskills-lms'); ?></label>
            <input type="color" name="_hs_color_start" value="<?php echo esc_attr($meta['_hs_color_start']); ?>">
        </div>
        <div class="hs-field">
            <label><?php _e('Cor Gradiente Fim (hex)', 'hubskills-lms'); ?></label>
            <input type="color" name="_hs_color_end" value="<?php echo esc_attr($meta['_hs_color_end']); ?>">
        </div>
        <div class="hs-field" style="grid-column:span 2">
            <label><input type="checkbox" name="_hs_featured" value="1" <?php checked($meta['_hs_featured'],'1'); ?>>
            <?php _e(' Destacar curso na Home', 'hubskills-lms'); ?></label>
        </div>
    </div>
    <?php
}

// ── Curriculum Builder ────────────────────────────────────
function hs_curriculum_cb( WP_Post $post ) {
    $curriculum = get_post_meta($post->ID, '_hs_curriculum', true) ?: [];
    $json = htmlspecialchars(json_encode($curriculum), ENT_QUOTES, 'UTF-8');
    ?>
    <p style="color:#666;font-size:13px"><?php _e('Defina o currículo em formato JSON. Cada seção contém um nome e array de aulas.', 'hubskills-lms'); ?></p>
    <p style="color:#666;font-size:12px"><?php _e('Exemplo:', 'hubskills-lms'); ?></p>
    <pre style="font-size:11px;background:#f5f5f5;padding:8px;border-radius:4px;overflow:auto;max-height:120px">[{"section":"Módulo 1","lessons":[{"id":1,"title":"Introdução","duration":"10min","type":"video","video_url":"https://..."}]}]</pre>
    <textarea name="_hs_curriculum_json" rows="12" style="width:100%;font-family:monospace;font-size:12px;border:1px solid #ddd;border-radius:4px;padding:8px"><?php echo $json; ?></textarea>
    <?php
}

function hs_course_certificate_cb( WP_Post $post ) {
    $settings = function_exists( 'hs_certificate_course_settings' ) ? hs_certificate_course_settings( $post->ID ) : [];
    $templates = function_exists( 'hs_certificate_templates' ) ? hs_certificate_templates() : [ 'modern' => __( 'Moderno azul e ciano', 'hubskills-lms' ) ];
    ?>
    <p style="color:#666;font-size:12px;margin-top:0"><?php _e('Usado quando o aluno conclui todas as partes do curso.', 'hubskills-lms'); ?></p>
    <p>
        <label style="display:block;font-weight:600;margin-bottom:4px"><?php _e('Modelo', 'hubskills-lms'); ?></label>
        <select name="_hs_certificate_template" style="width:100%">
            <?php foreach ( $templates as $slug => $label ) : ?>
                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $settings['template'] ?? 'modern', $slug ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <p><label style="display:block;font-weight:600;margin-bottom:4px"><?php _e('Carga horária', 'hubskills-lms'); ?></label><input type="text" name="_hs_certificate_workload" value="<?php echo esc_attr( $settings['course_workload'] ?? '' ); ?>" style="width:100%"></p>
    <p><label style="display:block;font-weight:600;margin-bottom:4px"><?php _e('Instrutor/assinatura', 'hubskills-lms'); ?></label><input type="text" name="_hs_certificate_instructor" value="<?php echo esc_attr( $settings['instructor_name'] ?? '' ); ?>" style="width:100%"></p>
    <p><label style="display:block;font-weight:600;margin-bottom:4px"><?php _e('Instituição', 'hubskills-lms'); ?></label><input type="text" name="_hs_certificate_issuer" value="<?php echo esc_attr( $settings['issuer_name'] ?? '' ); ?>" style="width:100%"></p>
    <p><label style="display:block;font-weight:600;margin-bottom:4px"><?php _e('Cargo da instituição', 'hubskills-lms'); ?></label><input type="text" name="_hs_certificate_issuer_title" value="<?php echo esc_attr( $settings['issuer_title'] ?? '' ); ?>" style="width:100%"></p>
    <p><label style="display:block;font-weight:600;margin-bottom:4px"><?php _e('Local', 'hubskills-lms'); ?></label><input type="text" name="_hs_certificate_location" value="<?php echo esc_attr( $settings['location'] ?? '' ); ?>" style="width:100%"></p>
    <p><label style="display:block;font-weight:600;margin-bottom:4px"><?php _e('Observação padrão', 'hubskills-lms'); ?></label><textarea name="_hs_certificate_note" rows="4" style="width:100%"><?php echo esc_textarea( $settings['note'] ?? '' ); ?></textarea></p>
    <?php
}

// ── Quiz Questions Builder ────────────────────────────────
function hs_quiz_questions_cb( WP_Post $post ) {
    $questions  = get_post_meta($post->ID, '_hs_questions', true) ?: [];
    $time_limit = get_post_meta($post->ID, '_hs_time_limit', true) ?: 600;
    $threshold  = get_post_meta($post->ID, '_hs_pass_threshold', true) ?: 70;
    $json = htmlspecialchars(json_encode($questions), ENT_QUOTES, 'UTF-8');
    wp_nonce_field('hs_save_quiz_meta','hs_quiz_nonce');
    ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        <div>
            <label style="font-weight:600;font-size:12px;text-transform:uppercase;color:#666"><?php _e('Tempo Limite (segundos)', 'hubskills-lms'); ?></label>
            <input type="number" name="_hs_time_limit" value="<?php echo (int)$time_limit; ?>" style="width:100%;border:1px solid #ddd;border-radius:4px;padding:6px 10px;margin-top:4px">
        </div>
        <div>
            <label style="font-weight:600;font-size:12px;text-transform:uppercase;color:#666"><?php _e('Nota Mínima (%)', 'hubskills-lms'); ?></label>
            <input type="number" name="_hs_pass_threshold" value="<?php echo (int)$threshold; ?>" min="0" max="100" style="width:100%;border:1px solid #ddd;border-radius:4px;padding:6px 10px;margin-top:4px">
        </div>
    </div>
    <p style="color:#666;font-size:13px"><?php _e('Questões em JSON. Cada questão tem: id, text, options (array de 4), correct (0-3), explanation.', 'hubskills-lms'); ?></p>
    <pre style="font-size:11px;background:#f5f5f5;padding:8px;border-radius:4px;max-height:100px;overflow:auto">[{"id":1,"text":"Qual é...?","options":["A","B","C","D"],"correct":2,"explanation":"Porque..."}]</pre>
    <textarea name="_hs_questions_json" rows="14" style="width:100%;font-family:monospace;font-size:12px;border:1px solid #ddd;border-radius:4px;padding:8px"><?php echo $json; ?></textarea>
    <?php
}

// ── Save Meta ─────────────────────────────────────────────
add_action( 'save_post', 'hs_save_course_meta' );
function hs_save_course_meta( int $post_id ) {
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision($post_id) ) return;

    // Course
    if ( get_post_type($post_id) === 'hubskills_course' ) {
        if ( ! isset($_POST['hs_course_nonce']) || ! wp_verify_nonce($_POST['hs_course_nonce'], 'hs_save_course_meta') ) return;
        if ( ! current_user_can('edit_post', $post_id) ) return;

        $fields = [ '_hs_instructor','_hs_rating','_hs_students','_hs_duration','_hs_level','_hs_price','_hs_icon','_hs_color_start','_hs_color_end','_hs_lessons_count' ];
        foreach ($fields as $f) {
            if ( isset($_POST[$f]) ) update_post_meta($post_id, $f, sanitize_text_field($_POST[$f]));
        }
        update_post_meta($post_id, '_hs_featured', isset($_POST['_hs_featured']) ? '1' : '0');

        if ( isset($_POST['_hs_curriculum_json']) ) {
            $decoded = json_decode(wp_unslash($_POST['_hs_curriculum_json']), true);
            if (is_array($decoded)) update_post_meta($post_id, '_hs_curriculum', $decoded);
        }

        if ( function_exists( 'hs_save_certificate_course_settings' ) ) {
            hs_save_certificate_course_settings( $post_id, [
                'template'        => sanitize_key( $_POST['_hs_certificate_template'] ?? '' ),
                'course_workload' => sanitize_text_field( wp_unslash( $_POST['_hs_certificate_workload'] ?? '' ) ),
                'instructor_name' => sanitize_text_field( wp_unslash( $_POST['_hs_certificate_instructor'] ?? '' ) ),
                'issuer_name'     => sanitize_text_field( wp_unslash( $_POST['_hs_certificate_issuer'] ?? '' ) ),
                'issuer_title'    => sanitize_text_field( wp_unslash( $_POST['_hs_certificate_issuer_title'] ?? '' ) ),
                'location'        => sanitize_text_field( wp_unslash( $_POST['_hs_certificate_location'] ?? '' ) ),
                'note'            => sanitize_textarea_field( wp_unslash( $_POST['_hs_certificate_note'] ?? '' ) ),
            ] );
        }
    }

    // Quiz
    if ( get_post_type($post_id) === 'hubskills_quiz' ) {
        if ( ! isset($_POST['hs_quiz_nonce']) || ! wp_verify_nonce($_POST['hs_quiz_nonce'], 'hs_save_quiz_meta') ) return;
        if ( ! current_user_can('edit_post', $post_id) ) return;

        update_post_meta($post_id, '_hs_time_limit',      (int)($_POST['_hs_time_limit'] ?? 600));
        update_post_meta($post_id, '_hs_pass_threshold',  (int)($_POST['_hs_pass_threshold'] ?? 70));

        if ( isset($_POST['_hs_questions_json']) ) {
            $decoded = json_decode(wp_unslash($_POST['_hs_questions_json']), true);
            if (is_array($decoded)) update_post_meta($post_id, '_hs_questions', $decoded);
        }
    }
}
