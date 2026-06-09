<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// [hubskills_courses] — Render a course grid
add_shortcode( 'hubskills_courses', 'hs_shortcode_courses' );
function hs_shortcode_courses( $atts ) {
    $atts = shortcode_atts( [
        'count'    => 6,
        'category' => '',
        'level'    => '',
        'orderby'  => 'date',
        'order'    => 'DESC',
    ], $atts, 'hubskills_courses' );

    $args = [
        'post_type'      => 'hubskills_course',
        'posts_per_page' => (int)$atts['count'],
        'orderby'        => sanitize_text_field($atts['orderby']),
        'order'          => sanitize_text_field($atts['order']),
    ];
    if ( $atts['category'] ) {
        $args['tax_query'] = [[ 'taxonomy' => 'course_category', 'field' => 'slug', 'terms' => sanitize_text_field($atts['category']) ]];
    }
    if ( $atts['level'] ) {
        $args['meta_query'] = [[ 'key' => '_hs_level', 'value' => sanitize_text_field($atts['level']) ]];
    }

    $query = new WP_Query( $args );
    if ( ! $query->have_posts() ) return '<p>' . __( 'Nenhum curso encontrado.', 'hubskills-lms' ) . '</p>';

    $user_id     = get_current_user_id();
    $enrollments = $user_id ? ( get_user_meta($user_id,'hs_enrollments',true) ?: [] ) : [];

    ob_start();
    echo '<div class="hs-shortcode-courses courses-grid">';
    while ( $query->have_posts() ) {
        $query->the_post();
        $enroll_data = function_exists( 'hs_get_enrollment' ) ? hs_get_enrollment( $user_id, get_the_ID() ) : ( $enrollments[get_the_ID()] ?? [] );
        $enrolled = ! empty( $enroll_data );
        $progress = (int)( $enroll_data['progress'] ?? 0 );
        echo hubskills_course_card( $GLOBALS['post'], $enrolled, $progress ); // phpcs:ignore
    }
    wp_reset_postdata();
    echo '</div>';
    return ob_get_clean();
}

// [hubskills_enroll_button course_id="123"] — Single enroll button
add_shortcode( 'hubskills_enroll_button', 'hs_shortcode_enroll_button' );
function hs_shortcode_enroll_button( $atts ) {
    $atts = shortcode_atts( [ 'course_id' => 0 ], $atts );
    $course_id = (int)$atts['course_id'] ?: get_the_ID();
    if ( ! $course_id ) return '';

    if ( ! is_user_logged_in() ) {
        return '<a href="' . esc_url(wp_login_url(get_permalink())) . '" class="btn btn-gradient">' . __('Entrar para Matricular-se', 'hubskills-lms') . '</a>';
    }

    $user_id = get_current_user_id();
    $is_paid = function_exists( 'hs_is_course_paid' ) && hs_is_course_paid( $course_id );
    if ( $is_paid && function_exists( 'hs_user_has_access' ) && hs_user_has_access( $user_id, $course_id ) && ! hs_is_enrolled( $user_id, $course_id ) && function_exists( 'hs_enroll_user' ) ) {
        hs_enroll_user( $user_id, $course_id, 'payment' );
    }

    if ( hs_is_enrolled($user_id, $course_id) ) {
        return '<a href="' . esc_url(get_permalink($course_id)) . '" class="btn btn-enrolled">' . __('Continuar Curso', 'hubskills-lms') . '</a>';
    }

    if ( $is_paid ) {
        $price = get_post_meta( $course_id, '_hs_price', true );
        return '<button class="btn btn-gradient payment-btn" data-course="' . (int)$course_id . '" data-nonce="' . esc_attr(wp_create_nonce('hubskills_nonce')) . '">'
            . '<i class="fas fa-credit-card me-2"></i>' . sprintf( esc_html__( 'Comprar curso %s', 'hubskills-lms' ), esc_html( $price ) )
            . '</button>';
    }

    return '<button class="btn btn-gradient enroll-btn" data-course="' . (int)$course_id . '" data-nonce="' . esc_attr(wp_create_nonce('hubskills_nonce')) . '">'
        . '<i class="fas fa-lock-open me-2"></i>' . __('Matricular-se', 'hubskills-lms')
        . '</button>';
}

// [hubskills_progress course_id="123"] — Progress bar
add_shortcode( 'hubskills_progress', 'hs_shortcode_progress' );
function hs_shortcode_progress( $atts ) {
    $atts = shortcode_atts( [ 'course_id' => 0 ], $atts );
    if ( ! is_user_logged_in() ) return '';

    $course_id = (int)$atts['course_id'] ?: get_the_ID();
    $progress  = hs_get_progress( get_current_user_id(), $course_id );

    return '<div class="hs-progress-wrap">'
        . '<div class="progress"><div class="progress-bar" style="width:' . (int)$progress . '%"></div></div>'
        . '<span class="progress-text">' . (int)$progress . '% ' . __('concluído', 'hubskills-lms') . '</span>'
        . '</div>';
}

// [hubskills_quiz quiz_id="123"] — Inline quiz
add_shortcode( 'hubskills_quiz', 'hs_shortcode_quiz' );
function hs_shortcode_quiz( $atts ) {
    $atts = shortcode_atts( [ 'quiz_id' => 0 ], $atts );
    $quiz_id = (int)$atts['quiz_id'];
    if ( ! $quiz_id || get_post_type($quiz_id) !== 'hubskills_quiz' ) return '';
    if ( ! is_user_logged_in() ) {
        return '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" class="btn btn-gradient">' . esc_html__( 'Entrar para acessar o quiz', 'hubskills-lms' ) . '</a>';
    }
    if ( function_exists( 'hs_user_can_access_quiz' ) ) {
        $access = hs_user_can_access_quiz( get_current_user_id(), $quiz_id );
        if ( is_wp_error( $access ) ) {
            return '<p class="hs-notice hs-notice-warning">' . esc_html( $access->get_error_message() ) . '</p>';
        }
    }

    ob_start();
    ?>
    <div class="hs-quiz-container" id="quiz-<?php echo (int)$quiz_id; ?>" data-quiz="<?php echo (int)$quiz_id; ?>">
        <div class="hs-quiz-loading text-center p-4">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p><?php _e('Carregando quiz...', 'hubskills-lms'); ?></p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
