<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Submit a course review/rating.
 */
function hs_submit_review( int $user_id, int $course_id, int $rating, string $comment = '' ) {
    if ( $rating < 1 || $rating > 5 ) {
        return new WP_Error( 'invalid_rating', __( 'Avaliação deve ser entre 1 e 5.', 'hubskills-lms' ) );
    }
    if ( get_post_type( $course_id ) !== 'hubskills_course' ) {
        return new WP_Error( 'invalid_course', __( 'Curso inválido.', 'hubskills-lms' ) );
    }
    if ( function_exists( 'hs_is_enrolled' ) && ! hs_is_enrolled( $user_id, $course_id ) ) {
        return new WP_Error( 'not_enrolled', __( 'Apenas alunos matriculados podem avaliar este curso.', 'hubskills-lms' ) );
    }

    $reviews = (array)( get_post_meta( $course_id, '_hs_reviews', true ) ?: [] );

    $reviews[ $user_id ] = [
        'user_id'  => $user_id,
        'rating'   => min( 5, max( 1, (int) $rating ) ),
        'comment'  => sanitize_textarea_field( $comment ),
        'date'     => current_time( 'mysql' ),
    ];

    update_post_meta( $course_id, '_hs_reviews', $reviews );

    // Recalculate average rating
    $total_rating = 0;
    foreach ( $reviews as $r ) {
        $total_rating += (int) $r['rating'];
    }
    $avg = count( $reviews ) > 0 ? round( $total_rating / count( $reviews ), 1 ) : 0;
    update_post_meta( $course_id, '_hs_rating', $avg );
    update_post_meta( $course_id, '_hs_rating_count', count( $reviews ) );

    do_action( 'hs_after_review', $user_id, $course_id, $rating );

    return true;
}

/**
 * Get all reviews for a course.
 */
function hs_get_reviews( int $course_id ) {
    return (array)( get_post_meta( $course_id, '_hs_reviews', true ) ?: [] );
}

/**
 * Get a user's review for a course.
 */
function hs_get_user_review( int $user_id, int $course_id ) {
    $reviews = hs_get_reviews( $course_id );
    return $reviews[ $user_id ] ?? null;
}
