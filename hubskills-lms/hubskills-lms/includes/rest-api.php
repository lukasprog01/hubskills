<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', 'hs_register_rest_routes' );
function hs_register_rest_routes() {
    $ns = 'hubskills/v1';

    // Courses list
    register_rest_route( $ns, '/courses', [
        'methods'             => 'GET',
        'callback'            => 'hs_rest_get_courses',
        'permission_callback' => '__return_true',
    ] );

    // Single course
    register_rest_route( $ns, '/courses/(?P<id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'hs_rest_get_course',
        'permission_callback' => '__return_true',
        'args'                => [ 'id' => [ 'validate_callback' => 'is_numeric' ] ],
    ] );

    // User stats (requires auth)
    register_rest_route( $ns, '/me/stats', [
        'methods'             => 'GET',
        'callback'            => 'hs_rest_get_me_stats',
        'permission_callback' => 'is_user_logged_in',
    ] );

    // Enroll
    register_rest_route( $ns, '/enroll', [
        'methods'             => 'POST',
        'callback'            => 'hs_rest_enroll',
        'permission_callback' => 'is_user_logged_in',
    ] );

    // Verify certificate
    register_rest_route( $ns, '/verify-certificate/(?P<cert_id>[a-zA-Z0-9-]+)', [
        'methods'             => 'GET',
        'callback'            => 'hs_rest_verify_certificate',
        'permission_callback' => '__return_true',
    ] );
}

function hs_rest_verify_certificate( WP_REST_Request $request ) {
    $cert_id = sanitize_text_field( $request['cert_id'] );
    $data    = hs_verify_certificate( $cert_id );

    if ( ! $data ) {
        return new WP_Error( 'not_found', __( 'Certificado não encontrado.', 'hubskills-lms' ), [ 'status' => 404 ] );
    }

    return rest_ensure_response( [
        'success' => true,
        'data'    => [
            'student'    => $data['details']['student_name'] ?? ( $data['user'] ? $data['user']->display_name : '' ),
            'course'     => $data['details']['course_title'] ?? ( $data['course'] ? get_the_title( $data['course'] ) : '' ),
            'date'       => $data['details']['completion_date'] ?? ( $data['date'] ?? '' ),
            'workload'   => $data['details']['course_workload'] ?? '',
            'grade'      => $data['details']['grade'] ?? '',
            'instructor' => $data['details']['instructor_name'] ?? '',
            'issued_at'  => $data['details']['issue_date'] ?? '',
            'cert_id'    => $data['cert_id'] ?? '',
        ],
    ] );
}

function hs_rest_get_courses( WP_REST_Request $request ) {
    $per_page = min( 50, max( 1, (int) ( $request['per_page'] ?? 12 ) ) );
    $page     = max( 1, (int) ( $request['page'] ?? 1 ) );

    $query = new WP_Query( [
        'post_type'      => 'hubskills_course',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'post_status'    => 'publish',
    ] );

    $courses = [];
    foreach ( $query->posts as $post ) {
        $courses[] = hs_format_course_for_api( $post );
    }

    return rest_ensure_response( [
        'courses' => $courses,
        'total'   => $query->found_posts,
        'pages'   => $query->max_num_pages,
    ] );
}

function hs_rest_get_course( WP_REST_Request $request ) {
    $post = get_post( (int)$request['id'] );
    if ( ! $post || $post->post_type !== 'hubskills_course' || ( $post->post_status !== 'publish' && ! current_user_can( 'edit_post', $post->ID ) ) ) {
        return new WP_Error( 'not_found', __( 'Curso não encontrado.', 'hubskills-lms' ), [ 'status' => 404 ] );
    }
    return rest_ensure_response( hs_format_course_for_api( $post ) );
}

function hs_rest_get_me_stats() {
    return rest_ensure_response( hs_get_user_stats( get_current_user_id() ) );
}

function hs_rest_enroll( WP_REST_Request $request ) {
    $course_id = (int)( $request['course_id'] ?? 0 );
    $result    = hs_enroll_user( get_current_user_id(), $course_id );
    if ( is_wp_error( $result ) ) {
        if ( $result->get_error_code() === 'already_enrolled' ) {
            return rest_ensure_response( [
                'success'          => true,
                'already_enrolled' => true,
                'course_url'       => get_permalink( $course_id ),
            ] );
        }
        $status = $result->get_error_code() === 'payment_required' ? 402 : 400;
        return new WP_Error( $result->get_error_code(), $result->get_error_message(), [ 'status' => $status ] );
    }
    return rest_ensure_response( [ 'success' => true, 'course_url' => get_permalink( $course_id ) ] );
}

function hs_format_course_for_api( WP_Post $post ) {
    return [
        'id'          => $post->ID,
        'title'       => get_the_title( $post ),
        'slug'        => $post->post_name,
        'url'         => get_permalink( $post ),
        'excerpt'     => get_the_excerpt( $post ),
        'instructor'  => get_post_meta( $post->ID, '_hs_instructor', true ),
        'rating'      => (float) get_post_meta( $post->ID, '_hs_rating', true ),
        'students'    => function_exists( 'hs_course_student_count' )
            ? hs_course_student_count( (int) $post->ID )
            : max( (int) get_post_meta( $post->ID, '_hs_students', true ), (int) get_post_meta( $post->ID, '_hs_student_count', true ) ),
        'duration'    => get_post_meta( $post->ID, '_hs_duration', true ),
        'level'       => get_post_meta( $post->ID, '_hs_level', true ),
        'price'       => get_post_meta( $post->ID, '_hs_price', true ) ?: 'Grátis',
        'icon'        => get_post_meta( $post->ID, '_hs_icon', true ) ?: '📚',
        'thumbnail'   => get_the_post_thumbnail_url( $post, 'course-thumb' ),
    ];
}
