<?php
/**
 * Plugin Name: HubSkills LMS
 * Plugin URI:  https://hubskills.com.br
 * Description: Sistema LMS completo para plataforma EAD - cursos, matriculas, progresso, quizzes, certificados e gamificacao.
 * Version:     1.0.0
 * Author:      HubSkills
 * Author URI:  https://hubskills.com.br
 * Text Domain: hubskills-lms
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'HS_LMS_VERSION',  '1.0.1' );
define( 'HS_LMS_PLUGIN',   __FILE__ );
define( 'HS_LMS_DIR',      plugin_dir_path( __FILE__ ) );
define( 'HS_LMS_URL',      plugin_dir_url( __FILE__ ) );
define( 'HS_LMS_BASENAME', plugin_basename( __FILE__ ) );

// ── Autoload includes ─────────────────────────────────────
require_once HS_LMS_DIR . 'includes/post-types.php';
require_once HS_LMS_DIR . 'includes/taxonomies.php';
require_once HS_LMS_DIR . 'includes/roles.php';
require_once HS_LMS_DIR . 'includes/security.php';
require_once HS_LMS_DIR . 'includes/enrollment.php';
require_once HS_LMS_DIR . 'includes/progress.php';
require_once HS_LMS_DIR . 'includes/gamification.php';
require_once HS_LMS_DIR . 'includes/quiz.php';
require_once HS_LMS_DIR . 'includes/certificates.php';
require_once HS_LMS_DIR . 'includes/instructors.php';
require_once HS_LMS_DIR . 'includes/payments.php';
require_once HS_LMS_DIR . 'includes/refunds.php';
require_once HS_LMS_DIR . 'includes/reviews.php';
require_once HS_LMS_DIR . 'includes/shortcodes.php';
require_once HS_LMS_DIR . 'includes/ajax.php';
require_once HS_LMS_DIR . 'includes/secure-media.php';
require_once HS_LMS_DIR . 'includes/frontend-dashboard.php';
require_once HS_LMS_DIR . 'includes/rest-api.php';
if ( is_admin() ) {
    require_once HS_LMS_DIR . 'admin/settings.php';
    require_once HS_LMS_DIR . 'admin/course-metabox.php';
    require_once HS_LMS_DIR . 'admin/admin-dashboard.php';
    require_once HS_LMS_DIR . 'admin/ajax-admin.php';
}

// ── Activation / Deactivation ─────────────────────────────
register_activation_hook( __FILE__, 'hs_lms_activate' );
function hs_lms_activate() {
    hs_register_post_types();
    hs_register_taxonomies();
    do_action( 'hs_lms_activate' );
    flush_rewrite_rules();
    hs_lms_create_pages();
}

register_deactivation_hook( __FILE__, 'hs_lms_deactivate_cleanup' );
function hs_lms_deactivate_cleanup() {
    do_action( 'hs_lms_deactivate' );
    flush_rewrite_rules();
}

function hs_lms_create_pages() {
    $pages = [
        'dashboard'          => [ 'title' => 'Dashboard',           'template' => 'page-dashboard.php' ],
        'cursos'             => [ 'title' => 'Cursos',               'template' => 'page-courses.php' ],
        'perfil'             => [ 'title' => 'Meu Perfil',           'template' => 'page-student-profile-enhanced.php' ],
        'certificados'       => [ 'title' => 'Certificados',         'template' => 'page-certificate.php' ],
        'seja-instrutor'     => [ 'title' => 'Seja Instrutor',       'template' => 'page-instructor-register.php' ],
        'painel-instrutor'   => [ 'title' => 'Painel do Instrutor',  'template' => 'page-instructor-dashboard.php' ],
        'instrutor'          => [ 'title' => 'Perfil do Instrutor',  'template' => 'page-instructor-profile.php' ],
        'verificar-certificado' => [ 'title' => 'Verificar Certificado', 'template' => 'page-verify-certificate.php' ],
    ];
    foreach ( $pages as $slug => $data ) {
        if ( ! get_page_by_path( $slug ) ) {
            $id = wp_insert_post([
                'post_title'   => $data['title'],
                'post_name'    => $slug,
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => '',
            ]);
            if ( $id && ! is_wp_error($id) ) {
                update_post_meta( $id, '_wp_page_template', $data['template'] );
            }
        }
    }

    // Tell the theme to rebuild the nav menu with the new pages
    do_action( 'hs_after_create_pages' );
}

// ── Enqueue Plugin Assets ─────────────────────────────────
add_action( 'wp_enqueue_scripts', 'hs_lms_enqueue_assets' );
function hs_lms_enqueue_assets() {
    $css_version = file_exists( HS_LMS_DIR . 'assets/css/hubskills.css' ) ? (string) filemtime( HS_LMS_DIR . 'assets/css/hubskills.css' ) : HS_LMS_VERSION;
    $js_version  = file_exists( HS_LMS_DIR . 'assets/js/hubskills.js' ) ? (string) filemtime( HS_LMS_DIR . 'assets/js/hubskills.js' ) : HS_LMS_VERSION;

    wp_enqueue_style( 'hs-lms', HS_LMS_URL . 'assets/css/hubskills.css', [], $css_version );
    wp_enqueue_script( 'hs-lms', HS_LMS_URL . 'assets/js/hubskills.js', [ 'jquery' ], $js_version, true );
    wp_localize_script( 'hs-lms', 'HSLMS', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'hubskills_nonce' ),
        'rest'    => rest_url( 'hubskills/v1/' ),
        'is_logged' => is_user_logged_in() ? 1 : 0,
        'user_id'   => get_current_user_id(),
    ] );
}

// ── Admin Assets ──────────────────────────────────────────
add_action( 'admin_enqueue_scripts', 'hs_lms_admin_assets' );
function hs_lms_admin_assets( $hook ) {
    if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) && strpos( (string) $hook, 'hubskills' ) === false ) return;
    wp_enqueue_style( 'hs-lms-admin', HS_LMS_URL . 'assets/css/admin.css', [], HS_LMS_VERSION );
    wp_enqueue_script( 'hs-lms-admin', HS_LMS_URL . 'assets/js/admin.js', [ 'jquery', 'wp-util' ], HS_LMS_VERSION, true );

    // Builder visual nas telas de edição de curso e quiz
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( $screen && in_array( $screen->post_type, [ 'hubskills_course', 'hubskills_quiz' ], true ) ) {
        $builder_version = file_exists( HS_LMS_DIR . 'assets/js/builder.js' ) ? (string) filemtime( HS_LMS_DIR . 'assets/js/builder.js' ) : HS_LMS_VERSION;
        wp_enqueue_script( 'hs-lms-builder', HS_LMS_URL . 'assets/js/builder.js', [ 'jquery', 'jquery-ui-sortable' ], $builder_version, true );
    }
}

// ── Plugin Action Links ───────────────────────────────────
add_filter( 'plugin_action_links_' . HS_LMS_BASENAME, 'hs_lms_action_links' );
function hs_lms_action_links( $links ) {
    $links[] = '<a href="' . admin_url('admin.php?page=hubskills-lms') . '">' . __('Configurações', 'hubskills-lms') . '</a>';
    return $links;
}

// ── Load textdomain ───────────────────────────────────────
add_action( 'plugins_loaded', 'hs_lms_load_textdomain' );
function hs_lms_load_textdomain() {
    load_plugin_textdomain( 'hubskills-lms', false, dirname( HS_LMS_BASENAME ) . '/languages' );
}
