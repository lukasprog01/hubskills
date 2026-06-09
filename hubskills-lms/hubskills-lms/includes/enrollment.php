<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function hs_get_user_enrollments( int $user_id ): array {
    $enrollments = get_user_meta( $user_id, 'hs_enrollments', true );
    return is_array( $enrollments ) ? $enrollments : [];
}

function hs_get_enrollment_key( array $enrollments, int $course_id ) {
    if ( array_key_exists( $course_id, $enrollments ) ) {
        return $course_id;
    }

    $string_key = (string) $course_id;
    if ( array_key_exists( $string_key, $enrollments ) ) {
        return $string_key;
    }

    return null;
}

function hs_course_student_count( int $course_id, bool $force_refresh = false ): int {
    static $counts = null;

    if ( $force_refresh || $counts === null ) {
        $counts = [];
        $users = get_users( [
            'meta_key' => 'hs_enrollments',
            'fields'   => 'ID',
            'number'   => -1,
        ] );

        foreach ( $users as $user_id ) {
            foreach ( hs_get_user_enrollments( (int) $user_id ) as $enrolled_course_id => $data ) {
                if ( ! is_array( $data ) ) {
                    continue;
                }
                $normalized_course_id = (int) $enrolled_course_id;
                $counts[ $normalized_course_id ] = ( $counts[ $normalized_course_id ] ?? 0 ) + 1;
            }
        }
    }

    return (int) ( $counts[ $course_id ] ?? 0 );
}

function hs_sync_course_student_count( int $course_id ): int {
    $count = hs_course_student_count( $course_id, true );
    update_post_meta( $course_id, '_hs_students', $count );
    update_post_meta( $course_id, '_hs_student_count', $count );
    return $count;
}

function hs_sync_all_course_student_counts(): void {
    $users = get_users( [
        'meta_key' => 'hs_enrollments',
        'fields'   => 'ID',
        'number'   => -1,
    ] );
    $course_ids = [];

    foreach ( $users as $user_id ) {
        foreach ( hs_get_user_enrollments( (int) $user_id ) as $course_id => $data ) {
            if ( is_array( $data ) ) {
                $course_ids[ (int) $course_id ] = true;
            }
        }
    }

    foreach ( array_keys( $course_ids ) as $course_id ) {
        hs_sync_course_student_count( (int) $course_id );
    }
}

function hs_sync_user_paid_access_enrollments( int $user_id ): int {
    if ( ! get_userdata( $user_id ) ) {
        return 0;
    }

    $orders = get_posts( [
        'post_type'      => 'hs_order',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'   => '_hs_order_user_id',
                'value' => $user_id,
            ],
            [
                'key'   => '_hs_order_status',
                'value' => 'paid',
            ],
        ],
    ] );

    $synced = 0;
    foreach ( $orders as $order_id ) {
        $course_id = (int) get_post_meta( (int) $order_id, '_hs_order_course_id', true );
        if ( ! $course_id || hs_is_enrolled( $user_id, $course_id ) ) {
            continue;
        }

        $result = hs_enroll_user( $user_id, $course_id, 'payment' );
        if ( ! is_wp_error( $result ) ) {
            $synced++;
        }
    }

    return $synced;
}

/**
 * Enroll a user in a course.
 *
 * @param int $user_id
 * @param int $course_id
 * @return bool|WP_Error
 */
function hs_enroll_user( int $user_id, int $course_id, string $source = 'manual' ) {
    if ( ! get_userdata( $user_id ) ) return new WP_Error( 'invalid_user', __( 'Usuário inválido.', 'hubskills-lms' ) );
    if ( get_post_type( $course_id ) !== 'hubskills_course' ) return new WP_Error( 'invalid_course', __( 'Curso inválido.', 'hubskills-lms' ) );
    if ( get_post_status( $course_id ) !== 'publish' && ! current_user_can( 'edit_post', $course_id ) ) {
        return new WP_Error( 'invalid_course', __( 'Curso indisponível.', 'hubskills-lms' ) );
    }

    if (
        $source !== 'payment'
        && function_exists( 'hs_is_course_paid' )
        && hs_is_course_paid( $course_id )
        && ( ! function_exists( 'hs_user_has_access' ) || ! hs_user_has_access( $user_id, $course_id ) )
    ) {
        return new WP_Error( 'payment_required', __( 'Este curso é pago. Conclua o pagamento antes da matrícula.', 'hubskills-lms' ) );
    }

    $enrollments = hs_get_user_enrollments( $user_id );

    if ( hs_get_enrollment_key( $enrollments, $course_id ) !== null ) {
        return new WP_Error( 'already_enrolled', __( 'Você já está matriculado neste curso.', 'hubskills-lms' ) );
    }

    $enrollments[ $course_id ] = [
        'progress'           => 0,
        'completed_lessons'  => [],
        'status'             => 'in_progress',
        'enrolled_date'      => current_time( 'Y-m-d' ),
        'last_accessed'      => current_time( 'Y-m-d' ),
        'minutes'            => 0,
    ];

    update_user_meta( $user_id, 'hs_enrollments', $enrollments );

    // Award XP for enrolling
    hs_award_xp( $user_id, 10 );

    // Log activity
    hs_log_activity( $user_id, 'enroll', sprintf(
        __( 'Matriculado em <strong>%s</strong>', 'hubskills-lms' ),
        get_the_title( $course_id )
    ) );

    hs_sync_course_student_count( $course_id );

    do_action( 'hs_after_enroll', $user_id, $course_id );

    // Notify instructor about new enrollment
    $instructor_id = (int) get_post_meta( $course_id, '_hs_instructor_id', true );
    if ( $instructor_id ) {
        $instructor = get_userdata( $instructor_id );
        $student    = get_userdata( $user_id );
        if ( $instructor && $student ) {
            wp_mail(
                $instructor->user_email,
                sprintf( __( '[HubSkills] Novo aluno matriculado em %s', 'hubskills-lms' ), get_the_title( $course_id ) ),
                sprintf( __( "Olá %s,\n\nO aluno %s acabou de se matricular no curso \"%s\".\n\n— HubSkills LMS", 'hubskills-lms' ), $instructor->display_name, $student->display_name, get_the_title( $course_id ) )
            );
        }
    }

    return true;
}

/**
 * Check if a user is enrolled in a course.
 */
function hs_is_enrolled( int $user_id, int $course_id ) {
    $enrollments = hs_get_user_enrollments( $user_id );
    return hs_get_enrollment_key( $enrollments, $course_id ) !== null;
}

/**
 * Get all courses a user is enrolled in.
 */
function hs_get_enrolled_courses( int $user_id ) {
    $enrollments = hs_get_user_enrollments( $user_id );
    if ( empty( $enrollments ) ) return [];

    $courses = [];
    foreach ( $enrollments as $course_id => $data ) {
        $post = get_post( $course_id );
        if ( $post && $post->post_status === 'publish' ) {
            $courses[] = [ 'post' => $post, 'data' => $data ];
        }
    }
    return $courses;
}

/**
 * Get enrollment data for a specific course.
 */
function hs_get_enrollment( int $user_id, int $course_id ) {
    $enrollments = hs_get_user_enrollments( $user_id );
    $key = hs_get_enrollment_key( $enrollments, $course_id );
    $data = $key !== null ? ( $enrollments[ $key ] ?? [] ) : [];
    return is_array( $data ) ? $data : [];
}

/**
 * Unenroll a user from a course.
 */
function hs_unenroll_user( int $user_id, int $course_id ) {
    $enrollments = hs_get_user_enrollments( $user_id );
    $key = hs_get_enrollment_key( $enrollments, $course_id );
    if ( $key === null ) return false;

    unset( $enrollments[ $key ] );
    update_user_meta( $user_id, 'hs_enrollments', $enrollments );

    hs_sync_course_student_count( $course_id );

    do_action( 'hs_after_unenroll', $user_id, $course_id );
    return true;
}
