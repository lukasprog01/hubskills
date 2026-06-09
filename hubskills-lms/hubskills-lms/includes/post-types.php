<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function hs_course_post_type_caps(): array {
    return [
        'edit_post'              => 'edit_hubskills_course',
        'read_post'              => 'read_hubskills_course',
        'delete_post'            => 'delete_hubskills_course',
        'edit_posts'             => 'edit_hubskills_courses',
        'edit_others_posts'      => 'edit_others_hubskills_courses',
        'publish_posts'          => 'publish_hubskills_courses',
        'read_private_posts'     => 'read_private_hubskills_courses',
        'delete_posts'           => 'delete_hubskills_courses',
        'delete_private_posts'   => 'delete_private_hubskills_courses',
        'delete_published_posts' => 'delete_published_hubskills_courses',
        'delete_others_posts'    => 'delete_others_hubskills_courses',
        'edit_private_posts'     => 'edit_private_hubskills_courses',
        'edit_published_posts'   => 'edit_published_hubskills_courses',
        'create_posts'           => 'edit_hubskills_courses',
    ];
}

function hs_quiz_post_type_caps(): array {
    return [
        'edit_post'              => 'edit_hubskills_quiz',
        'read_post'              => 'read_hubskills_quiz',
        'delete_post'            => 'delete_hubskills_quiz',
        'edit_posts'             => 'edit_hubskills_quizzes',
        'edit_others_posts'      => 'edit_others_hubskills_quizzes',
        'publish_posts'          => 'publish_hubskills_quizzes',
        'read_private_posts'     => 'read_private_hubskills_quizzes',
        'delete_posts'           => 'delete_hubskills_quizzes',
        'delete_private_posts'   => 'delete_private_hubskills_quizzes',
        'delete_published_posts' => 'delete_published_hubskills_quizzes',
        'delete_others_posts'    => 'delete_others_hubskills_quizzes',
        'edit_private_posts'     => 'edit_private_hubskills_quizzes',
        'edit_published_posts'   => 'edit_published_hubskills_quizzes',
        'create_posts'           => 'edit_hubskills_quizzes',
    ];
}

add_action( 'init', 'hs_register_post_types' );
function hs_register_post_types() {

    // ── Course ────────────────────────────────────────────
    register_post_type( 'hubskills_course', [
        'labels' => [
            'name'               => __( 'Cursos', 'hubskills-lms' ),
            'singular_name'      => __( 'Curso', 'hubskills-lms' ),
            'add_new'            => __( 'Novo Curso', 'hubskills-lms' ),
            'add_new_item'       => __( 'Adicionar Novo Curso', 'hubskills-lms' ),
            'edit_item'          => __( 'Editar Curso', 'hubskills-lms' ),
            'view_item'          => __( 'Ver Curso', 'hubskills-lms' ),
            'all_items'          => __( 'Todos os Cursos', 'hubskills-lms' ),
            'search_items'       => __( 'Buscar Cursos', 'hubskills-lms' ),
            'not_found'          => __( 'Nenhum curso encontrado.', 'hubskills-lms' ),
        ],
        'public'             => true,
        'show_in_rest'       => true,
        'has_archive'        => true,
        'rewrite'            => [ 'slug' => 'curso' ],
        'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
        'menu_icon'          => 'dashicons-welcome-learn-more',
        'menu_position'      => 5,
        'show_in_menu'       => true,
        'capability_type'    => [ 'hubskills_course', 'hubskills_courses' ],
        'capabilities'       => hs_course_post_type_caps(),
        'map_meta_cap'       => true,
    ] );

    // ── Lesson ────────────────────────────────────────────
    register_post_type( 'hubskills_lesson', [
        'labels' => [
            'name'          => __( 'Aulas', 'hubskills-lms' ),
            'singular_name' => __( 'Aula', 'hubskills-lms' ),
            'add_new'       => __( 'Nova Aula', 'hubskills-lms' ),
            'add_new_item'  => __( 'Adicionar Nova Aula', 'hubskills-lms' ),
            'edit_item'     => __( 'Editar Aula', 'hubskills-lms' ),
            'all_items'     => __( 'Todas as Aulas', 'hubskills-lms' ),
        ],
        'public'             => false,
        'show_ui'            => true,
        'show_in_rest'       => true,
        'supports'           => [ 'title', 'editor', 'custom-fields' ],
        'menu_icon'          => 'dashicons-video-alt3',
        'menu_position'      => 6,
        'show_in_menu'       => 'edit.php?post_type=hubskills_course',
        'capability_type'    => [ 'hubskills_lesson', 'hubskills_lessons' ],
        'capabilities'       => hs_course_post_type_caps(),
        'map_meta_cap'       => true,
    ] );

    // ── Quiz ──────────────────────────────────────────────
    register_post_type( 'hubskills_quiz', [
        'labels' => [
            'name'          => __( 'Quizzes', 'hubskills-lms' ),
            'singular_name' => __( 'Quiz', 'hubskills-lms' ),
            'add_new'       => __( 'Novo Quiz', 'hubskills-lms' ),
            'add_new_item'  => __( 'Adicionar Novo Quiz', 'hubskills-lms' ),
            'edit_item'     => __( 'Editar Quiz', 'hubskills-lms' ),
            'all_items'     => __( 'Todos os Quizzes', 'hubskills-lms' ),
        ],
        'public'          => false,
        'show_ui'         => true,
        'show_in_rest'    => true,
        'supports'        => [ 'title', 'custom-fields' ],
        'menu_icon'       => 'dashicons-clipboard',
        'menu_position'   => 7,
        'show_in_menu'    => 'edit.php?post_type=hubskills_course',
        'capability_type' => [ 'hubskills_quiz', 'hubskills_quizzes' ],
        'capabilities'    => hs_quiz_post_type_caps(),
        'map_meta_cap'    => true,
    ] );
}
