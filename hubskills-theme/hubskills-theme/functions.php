<?php
/**
 * HubSkills EAD Theme functions
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'HUBSKILLS_THEME_VERSION', '1.0.1' );
define( 'HUBSKILLS_THEME_URL', get_template_directory_uri() );
define( 'HUBSKILLS_THEME_PATH', get_template_directory() );

function hubskills_uses_app_layout(): bool {
    $templates = [
        'page-dashboard.php',
        'page-dashboard-courses.php',
        'page-profile.php',
        'page-student-profile-enhanced.php',
        'page-certificate.php',
        'page-instructor-dashboard.php',
    ];

    return is_page_template( $templates ) || is_singular( 'hubskills_course' );
}

// ─── Theme Setup ─────────────────────────────────────────────────────────────
add_action( 'after_setup_theme', 'hubskills_theme_setup' );
function hubskills_theme_setup() {
    load_theme_textdomain( 'hubskills', HUBSKILLS_THEME_PATH . '/languages' );
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', [ 'search-form', 'comment-form', 'gallery', 'caption', 'style', 'script' ] );
    add_theme_support( 'custom-logo', [
        'height'      => 60,
        'width'       => 200,
        'flex-width'  => true,
        'flex-height' => true,
    ] );
    register_nav_menus( [
        'primary'  => __( 'Menu Principal', 'hubskills' ),
        'dashboard' => __( 'Menu Dashboard', 'hubskills' ),
        'footer'   => __( 'Menu Rodapé', 'hubskills' ),
    ] );
}

// ─── Scripts & Styles ────────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'hubskills_enqueue_assets' );
function hubskills_enqueue_assets() {
    // Bibliotecas locais (assets/vendor) — sem dependência de CDN externo (LGPD/desempenho)
    $vendor = HUBSKILLS_THEME_URL . '/assets/vendor';

    // Google Fonts (Poppins + Inter) servidas localmente
    wp_enqueue_style( 'hubskills-fonts', $vendor . '/fonts/fonts.css', [], HUBSKILLS_THEME_VERSION );

    // Bootstrap 5
    wp_enqueue_style( 'bootstrap', $vendor . '/bootstrap/bootstrap.min.css', [], '5.3.2' );

    // Font Awesome
    wp_enqueue_style( 'fontawesome', $vendor . '/fontawesome/css/all.min.css', [], '6.5.1' );

    // AOS
    wp_enqueue_style( 'aos', $vendor . '/aos/aos.css', [], '2.3.4' );

    // Swiper
    wp_enqueue_style( 'swiper', $vendor . '/swiper/swiper-bundle.min.css', [], '11' );

    // Video.js
    wp_enqueue_style( 'videojs', $vendor . '/videojs/video-js.css', [], '8.6.1' );

    // Theme CSS
    $main_css_version = file_exists( HUBSKILLS_THEME_PATH . '/assets/css/main.css' )
        ? (string) filemtime( HUBSKILLS_THEME_PATH . '/assets/css/main.css' )
        : HUBSKILLS_THEME_VERSION;

    wp_enqueue_style( 'hubskills-animations', HUBSKILLS_THEME_URL . '/assets/css/animations.css', [], HUBSKILLS_THEME_VERSION );
    wp_enqueue_style( 'hubskills-main', HUBSKILLS_THEME_URL . '/assets/css/main.css', [ 'bootstrap', 'hubskills-animations' ], $main_css_version );

    // Panel CSS — loaded on app pages and instructor public flows.
    $panel_templates = [ 'page-instructor-register.php', 'page-instructor-profile.php' ];
    if ( hubskills_uses_app_layout() || is_page_template( $panel_templates ) ) {
        wp_enqueue_style( 'hubskills-panel', HUBSKILLS_THEME_URL . '/assets/css/panel.css', [ 'hubskills-main' ], HUBSKILLS_THEME_VERSION );
    }

    // Bootstrap JS
    wp_enqueue_script( 'bootstrap-bundle', $vendor . '/bootstrap/bootstrap.bundle.min.js', [], '5.3.2', true );

    // AOS
    wp_enqueue_script( 'aos-js', $vendor . '/aos/aos.js', [], '2.3.4', true );

    // GSAP
    wp_enqueue_script( 'gsap', $vendor . '/gsap/gsap.min.js', [], '3.12.5', true );
    wp_enqueue_script( 'gsap-scroll', $vendor . '/gsap/ScrollTrigger.min.js', [ 'gsap' ], '3.12.5', true );

    // Swiper
    wp_enqueue_script( 'swiper-js', $vendor . '/swiper/swiper-bundle.min.js', [], '11', true );

    // Chart.js
    wp_enqueue_script( 'chartjs', $vendor . '/chartjs/chart.umd.min.js', [], '4.4.2', true );

    // Particles.js
    wp_enqueue_script( 'particles', $vendor . '/particles/particles.min.js', [], '2.0.0', true );

    // Video.js
    wp_enqueue_script( 'videojs', $vendor . '/videojs/video.min.js', [], '8.6.1', true );

    // VanillaTilt
    wp_enqueue_script( 'vanillatilt', $vendor . '/vanilla-tilt/vanilla-tilt.min.js', [], '1.8.1', true );

    // Theme JS
    wp_enqueue_script( 'hubskills-theme', HUBSKILLS_THEME_URL . '/assets/js/theme.js', [ 'jquery', 'bootstrap-bundle', 'gsap', 'swiper-js', 'chartjs' ], HUBSKILLS_THEME_VERSION, true );

    // Pass AJAX data to JS
    wp_localize_script( 'hubskills-theme', 'HubSkillsAjax', [
        'ajaxurl'   => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'hubskills_nonce' ),
        'siteurl'   => get_site_url(),
        'is_logged' => is_user_logged_in() ? 'yes' : 'no',
        'user_id'   => get_current_user_id(),
        'rest_url'  => rest_url( 'hubskills/v1/' ),
    ] );
}

// ─── Widget Areas ─────────────────────────────────────────────────────────────
add_action( 'widgets_init', 'hubskills_register_sidebars' );
function hubskills_register_sidebars() {
    register_sidebar( [
        'name'          => __( 'Sidebar Dashboard', 'hubskills' ),
        'id'            => 'dashboard-sidebar',
        'description'   => __( 'Widgets no sidebar do dashboard.', 'hubskills' ),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ] );
    register_sidebar( [
        'name'          => __( 'Footer Col 1', 'hubskills' ),
        'id'            => 'footer-1',
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h5 class="footer-heading">',
        'after_title'   => '</h5>',
    ] );
}

// ─── Body Classes ─────────────────────────────────────────────────────────────
add_filter( 'body_class', 'hubskills_body_classes' );
function hubskills_body_classes( $classes ) {
    if ( is_singular( 'hubskills_course' ) ) {
        $classes[] = 'page-player';
    }
    if ( hubskills_uses_app_layout() ) {
        $classes[] = 'page-dashboard';
        $classes[] = 'has-sidebar';
    }

    return $classes;
}

// ─── Custom Image Sizes ────────────────────────────────────────────────────────
add_action( 'after_setup_theme', 'hubskills_image_sizes' );
function hubskills_image_sizes() {
    add_image_size( 'course-thumb', 400, 240, true );
    add_image_size( 'course-hero', 1200, 600, true );
    add_image_size( 'lesson-thumb', 640, 360, true );
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Render a course card HTML.
 * Accepts a WP_Post object of type hubskills_course.
 */
function hubskills_course_card( $post, $enrolled = false, $progress = 0 ) {
    $title       = get_the_title( $post );
    $permalink   = get_permalink( $post );
    $instructor  = get_post_meta( $post->ID, '_hs_instructor', true );
    $rating      = (float) get_post_meta( $post->ID, '_hs_rating', true );
    $students    = function_exists( 'hs_course_student_count' )
        ? hs_course_student_count( (int) $post->ID )
        : max( (int) get_post_meta( $post->ID, '_hs_students', true ), (int) get_post_meta( $post->ID, '_hs_student_count', true ) );
    $duration    = get_post_meta( $post->ID, '_hs_duration', true );
    $lessons_num = (int)   get_post_meta( $post->ID, '_hs_lessons_count', true );
    $price       = get_post_meta( $post->ID, '_hs_price', true ) ?: 'Grátis';
    $is_paid     = function_exists( 'hs_is_course_paid' ) && hs_is_course_paid( (int) $post->ID );
    $level       = get_post_meta( $post->ID, '_hs_level', true ) ?: 'Iniciante';
    $icon        = get_post_meta( $post->ID, '_hs_icon', true ) ?: '📚';
    $color_start = get_post_meta( $post->ID, '_hs_color_start', true ) ?: '#7C3AED';
    $color_end   = get_post_meta( $post->ID, '_hs_color_end', true ) ?: '#06B6D4';

    if ( ! $enrolled && is_user_logged_in() && function_exists( 'hs_get_enrollment' ) ) {
        $detected_enrollment = hs_get_enrollment( get_current_user_id(), (int) $post->ID );
        if ( ! empty( $detected_enrollment ) ) {
            $enrolled = true;
            $progress = (int) ( $detected_enrollment['progress'] ?? $progress );
        }
    }

    $level_colors = [ 'Iniciante' => '#10B981', 'Intermediário' => '#F59E0B', 'Avançado' => '#EF4444' ];
    $level_color  = $level_colors[ $level ] ?? '#7C3AED';

    $stars = '';
    for ( $i = 1; $i <= 5; $i++ ) {
        $class = $i <= round( $rating ) ? 'fas fa-star' : 'far fa-star';
        $stars .= "<i class=\"{$class}\"></i>";
    }

    $btn_text = $enrolled ? ( $progress > 0 ? 'Continuar' : 'Acessar' ) : ( $is_paid ? 'Comprar' : 'Matricular-se' );
    $btn_class = $enrolled ? 'btn-enrolled' : 'btn-enroll';

    ob_start(); ?>
    <div class="course-card" data-tilt data-tilt-max="8" data-tilt-speed="400" data-course-id="<?php echo esc_attr( $post->ID ); ?>">
        <div class="course-thumbnail" style="background: linear-gradient(135deg, <?php echo esc_attr($color_start); ?>, <?php echo esc_attr($color_end); ?>)">
            <span class="course-icon"><?php echo esc_html( $icon ); ?></span>
            <?php if ( $enrolled ) : ?>
            <div class="course-enrolled-badge"><i class="fas fa-check-circle"></i><?php _e( 'Matriculado', 'hubskills' ); ?></div>
            <?php endif; ?>
            <div class="course-level" style="background: <?php echo esc_attr($level_color); ?>22; color: <?php echo esc_attr($level_color); ?>; border-color: <?php echo esc_attr($level_color); ?>44">
                <?php echo esc_html( $level ); ?>
            </div>
        </div>
        <div class="course-info">
            <div class="course-instructor">
                <div class="instructor-avatar"><?php echo esc_html( mb_substr( $instructor, 0, 2 ) ); ?></div>
                <span><?php echo esc_html( $instructor ); ?></span>
            </div>
            <h3 class="course-title"><a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a></h3>
            <div class="course-meta">
                <span><i class="fas fa-play-circle"></i> <?php echo (int) $lessons_num; ?> aulas</span>
                <span><i class="fas fa-clock"></i> <?php echo esc_html( $duration ); ?></span>
                <span><i class="fas fa-users"></i> <?php echo number_format( $students, 0, ',', '.' ); ?></span>
            </div>
            <div class="course-rating">
                <div class="stars"><?php echo $stars; // phpcs:ignore ?></div>
                <span class="rating-value"><?php echo number_format( $rating, 1 ); ?></span>
            </div>
            <?php if ( $enrolled && $progress > 0 ) : ?>
            <div class="course-progress">
                <div class="progress">
                    <div class="progress-bar" style="width: <?php echo (int) $progress; ?>%"></div>
                </div>
                <span class="progress-text"><?php echo (int) $progress; ?>% concluído</span>
            </div>
            <?php endif; ?>
            <div class="course-footer">
                <span class="course-price <?php echo $price === 'Grátis' ? 'free' : ''; ?>"><?php echo esc_html( $price ); ?></span>
                <?php if ( $enrolled ) : ?>
                <a href="<?php echo esc_url( $permalink ); ?>" class="btn-course <?php echo esc_attr( $btn_class ); ?>">
                    <?php echo esc_html( $btn_text ); ?> <i class="fas fa-arrow-right"></i>
                </a>
                <?php elseif ( ! is_user_logged_in() ) : ?>
                <a href="<?php echo esc_url( wp_login_url( $permalink ) ); ?>" class="btn-course <?php echo esc_attr( $btn_class ); ?>">
                    <?php echo esc_html( $is_paid ? __( 'Comprar', 'hubskills' ) : __( 'Entrar', 'hubskills' ) ); ?> <i class="fas fa-arrow-right"></i>
                </a>
                <?php elseif ( $is_paid ) : ?>
                <button type="button" class="btn-course <?php echo esc_attr( $btn_class ); ?> payment-btn" data-course="<?php echo (int) $post->ID; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'hubskills_nonce' ) ); ?>">
                    <?php echo esc_html( $btn_text ); ?> <i class="fas fa-credit-card"></i>
                </button>
                <?php else : ?>
                <button type="button" class="btn-course <?php echo esc_attr( $btn_class ); ?> enroll-btn" data-course="<?php echo (int) $post->ID; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'hubskills_nonce' ) ); ?>">
                    <?php echo esc_html( $btn_text ); ?> <i class="fas fa-arrow-right"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
}

/**
 * Redirect guests from protected pages.
 */
add_action( 'template_redirect', 'hubskills_protect_pages' );
function hubskills_protect_pages() {
    $protected = [ 'page-dashboard.php', 'page-profile.php', 'page-student-profile-enhanced.php', 'page-certificate.php', 'page-instructor-dashboard.php' ];
    if ( is_page_template( $protected ) && ! is_user_logged_in() ) {
        wp_redirect( wp_login_url( get_permalink() ) );
        exit;
    }
}

// ─── Default Nav Fallback ─────────────────────────────────────────────────────

/**
 * Rendered when no menu is assigned to 'primary' location.
 * Always shows Início, Cursos and Seja Instrutor so guests see the links.
 */
function hubskills_default_nav() {
    $current = get_permalink();
    $items   = [
        home_url( '/' )              => __( 'Home', 'hubskills' ),
        home_url( '/cursos/' )       => __( 'Todos os cursos', 'hubskills' ),
        home_url( '/contatos/' )     => __( 'Contatos', 'hubskills' ),
        home_url( '/blog/' )         => __( 'Blog', 'hubskills' ),
    ];
    echo '<ul class="navbar-nav ms-auto me-4">';
    foreach ( $items as $url => $label ) {
        $active = ( rtrim( $current, '/' ) === rtrim( $url, '/' ) ) ? ' active' : '';
        echo '<li class="nav-item"><a class="nav-link' . esc_attr( $active ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
    }
    echo '</ul>';
}

// ─── Auto-create Primary Nav Menu ─────────────────────────────────────────────

add_action( 'after_switch_theme', 'hubskills_create_nav_menu' );
add_action( 'hs_after_create_pages', 'hubskills_create_nav_menu' );
function hubskills_create_nav_menu() {
    $menu_name     = 'Menu Principal';
    $menu_exists   = wp_get_nav_menu_object( $menu_name );
    $menu_id       = $menu_exists ? $menu_exists->term_id : wp_create_nav_menu( $menu_name );

    // Clear existing items so we don't duplicate on re-run
    if ( $menu_exists ) {
        $existing = wp_get_nav_menu_items( $menu_id );
        if ( $existing ) {
            foreach ( $existing as $item ) {
                wp_delete_post( $item->ID, true );
            }
        }
    }

    $nav_items = [
        [ 'title' => __( 'Home', 'hubskills' ),             'slug' => '' ],
        [ 'title' => __( 'Todos os cursos', 'hubskills' ),  'slug' => 'cursos' ],
        [ 'title' => __( 'Contatos', 'hubskills' ),         'slug' => 'contatos' ],
        [ 'title' => __( 'Blog', 'hubskills' ),             'slug' => 'blog' ],
    ];

    $order = 1;
    foreach ( $nav_items as $ni ) {
        $args = [
            'menu-item-title'  => $ni['title'],
            'menu-item-status' => 'publish',
            'menu-item-position' => $order++,
        ];

        if ( $ni['slug'] === '' ) {
            $args['menu-item-type'] = 'custom';
            $args['menu-item-url']  = home_url( '/' );
        } else {
            $page = get_page_by_path( $ni['slug'] );
            if ( $page ) {
                $args['menu-item-type']      = 'post_type';
                $args['menu-item-object']    = 'page';
                $args['menu-item-object-id'] = $page->ID;
            } else {
                $args['menu-item-type'] = 'custom';
                $args['menu-item-url']  = home_url( '/' . $ni['slug'] . '/' );
            }
        }

        wp_update_nav_menu_item( $menu_id, 0, $args );
    }

    // Assign to 'primary' theme location
    $locations             = get_theme_mod( 'nav_menu_locations', [] );
    $locations['primary']  = $menu_id;
    set_theme_mod( 'nav_menu_locations', $locations );
}

// ─── Botão de dashboard por papel ──────────────────────────────────────────────
/**
 * Retorna o botão de acesso correto para o usuário logado:
 *  - Administrador  → Painel Administrativo (wp-admin)
 *  - Instrutor      → Dashboard do Instrutor
 *  - Aluno (padrão) → Área do Aluno
 *
 * @return array{url:string,label:string,icon:string}
 */
function hubskills_user_dashboard_button(): array {
    $user_id = get_current_user_id();

    if ( user_can( $user_id, 'manage_options' ) ) {
        return [
            'url'   => admin_url(),
            'label' => __( 'Painel Administrativo', 'hubskills' ),
            'icon'  => 'fas fa-shield-halved',
        ];
    }

    if ( function_exists( 'hs_is_instructor' ) && hs_is_instructor( $user_id ) ) {
        return [
            'url'   => home_url( '/painel-instrutor/' ),
            'label' => __( 'Dashboard do Instrutor', 'hubskills' ),
            'icon'  => 'fas fa-chalkboard-teacher',
        ];
    }

    return [
        'url'   => home_url( '/dashboard/' ),
        'label' => __( 'Área do Aluno', 'hubskills' ),
        'icon'  => 'fas fa-user-graduate',
    ];
}

// ─── Login/Logout redirect ─────────────────────────────────────────────────────
add_filter( 'login_redirect', 'hubskills_login_redirect', 10, 3 );
function hubskills_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
    // Administradores vão para o wp-admin (ou para onde pediram)
    if ( $user instanceof WP_User && user_can( $user, 'manage_options' ) ) {
        return $requested_redirect_to ?: admin_url();
    }
    $dashboard = get_page_by_path( 'dashboard' );
    if ( $dashboard ) {
        return get_permalink( $dashboard->ID );
    }
    return home_url( '/dashboard/' );
}

add_filter( 'logout_redirect', 'hubskills_logout_redirect' );
function hubskills_logout_redirect() {
    return home_url( '/' );
}

// ─── Excerpt length ───────────────────────────────────────────────────────────
add_filter( 'excerpt_length', function() { return 20; } );
add_filter( 'excerpt_more',  function() { return '...'; } );

// ─── Elementor Compatibility ─────────────────────────────────────────────────
add_action( 'after_setup_theme', 'hubskills_elementor_support' );
function hubskills_elementor_support() {
    add_theme_support( 'elementor' );
    add_theme_support( 'header-footer-elementor' );
}

add_action( 'elementor/elements/categories_registered', 'hubskills_elementor_categories' );
function hubskills_elementor_categories() {
    if ( ! defined( 'ELEMENTOR_VERSION' ) ) return;
    \Elementor\Plugin::instance()->elements_manager->add_category(
        'hubskills',
        [ 'title' => __( 'HubSkills', 'hubskills' ), 'icon' => 'fa fa-graduation-cap' ],
        1
    );
}
