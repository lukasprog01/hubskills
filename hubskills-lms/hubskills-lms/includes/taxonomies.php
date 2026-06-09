<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'hs_register_taxonomies' );
function hs_register_taxonomies() {

    register_taxonomy( 'course_category', 'hubskills_course', [
        'labels' => [
            'name'          => __( 'Categorias', 'hubskills-lms' ),
            'singular_name' => __( 'Categoria', 'hubskills-lms' ),
            'all_items'     => __( 'Todas as Categorias', 'hubskills-lms' ),
            'add_new_item'  => __( 'Adicionar Categoria', 'hubskills-lms' ),
            'edit_item'     => __( 'Editar Categoria', 'hubskills-lms' ),
        ],
        'hierarchical'      => true,
        'public'            => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'rewrite'           => [ 'slug' => 'categoria-curso' ],
    ] );

    register_taxonomy( 'course_tag', 'hubskills_course', [
        'labels' => [
            'name'          => __( 'Tags de Curso', 'hubskills-lms' ),
            'singular_name' => __( 'Tag', 'hubskills-lms' ),
        ],
        'hierarchical' => false,
        'public'       => true,
        'show_in_rest' => true,
        'rewrite'      => [ 'slug' => 'tag-curso' ],
    ] );
}
