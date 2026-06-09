<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Count every curriculum item that must be completed for a course.
 */
function hs_course_total_required_parts( int $course_id ): int {
    $curriculum = get_post_meta( $course_id, '_hs_curriculum', true );
    if ( ! is_array( $curriculum ) ) return 0;

    $total = 0;
    foreach ( $curriculum as $section ) {
        $total += count( (array) ( $section['lessons'] ?? [] ) );
    }
    return $total;
}

function hs_normalize_completed_lessons( array $completed ): array {
    return array_values( array_unique( array_filter( array_map( 'intval', $completed ) ) ) );
}

function hs_course_certificate_url( int $course_id ): string {
    return add_query_arg( 'course', $course_id, home_url( '/certificados/' ) );
}

function hs_complete_course_if_ready( int $user_id, int $course_id, array &$data ): bool {
    if ( (int) ( $data['progress'] ?? 0 ) < 100 ) return false;
    if ( ( $data['status'] ?? '' ) === 'completed' && function_exists( 'hs_has_certificate' ) && hs_has_certificate( $user_id, $course_id ) ) return false;

    $data['status']       = 'completed';
    $data['progress']     = 100;
    $data['completed_at'] = current_time( 'mysql' );

    if ( function_exists( 'hs_award_certificate' ) ) {
        hs_award_certificate( $user_id, $course_id );
    }
    if ( function_exists( 'hs_award_badge' ) ) {
        hs_award_badge( $user_id, 'graduate' );
    }
    if ( function_exists( 'hs_log_activity' ) ) {
        hs_log_activity( $user_id, 'cert', sprintf(
            __( 'Completou o curso <strong>%s</strong> e recebeu o certificado automaticamente 🎓', 'hubskills-lms' ),
            get_the_title( $course_id )
        ) );
    }

    return true;
}

function hs_sync_course_progress( int $user_id, int $course_id ): array {
    $enrollments = function_exists( 'hs_get_user_enrollments' ) ? hs_get_user_enrollments( $user_id ) : ( get_user_meta( $user_id, 'hs_enrollments', true ) ?: [] );
    $key = function_exists( 'hs_get_enrollment_key' ) ? hs_get_enrollment_key( $enrollments, $course_id ) : $course_id;
    if ( $key === null || empty( $enrollments[ $key ] ) || ! is_array( $enrollments[ $key ] ) ) {
        return [ 'progress' => 0, 'completed' => false, 'certificate_url' => '' ];
    }

    $data =& $enrollments[ $key ];
    $completed = hs_normalize_completed_lessons( (array) ( $data['completed_lessons'] ?? [] ) );
    $total = hs_course_total_required_parts( $course_id );

    $data['completed_lessons'] = $completed;
    $data['progress'] = $total > 0 ? min( 100, round( count( $completed ) / $total * 100 ) ) : 0;
    $completed_now = hs_complete_course_if_ready( $user_id, $course_id, $data );

    update_user_meta( $user_id, 'hs_enrollments', $enrollments );

    return [
        'progress'        => (int) $data['progress'],
        'completed'       => ( $data['status'] ?? '' ) === 'completed',
        'completed_now'   => $completed_now,
        'certificate_url' => ( $data['status'] ?? '' ) === 'completed' ? hs_course_certificate_url( $course_id ) : '',
    ];
}

/**
 * Mark a lesson as completed and recalculate course progress.
 *
 * @return bool|WP_Error
 */
function hs_complete_lesson( int $user_id, int $course_id, int $lesson_id ) {
    if ( ! hs_is_enrolled( $user_id, $course_id ) ) {
        return new WP_Error( 'not_enrolled', __( 'Você não está matriculado neste curso.', 'hubskills-lms' ) );
    }

    $lesson_info = hs_get_course_lesson_info( $course_id, $lesson_id );
    if ( ! $lesson_info ) {
        return new WP_Error( 'invalid_lesson', __( 'Aula não pertence a este curso.', 'hubskills-lms' ) );
    }

    if ( ! hs_is_lesson_available( $user_id, $course_id, (int) $lesson_info['index'] ) ) {
        return new WP_Error( 'lesson_locked', __( 'Esta aula ainda não está disponível.', 'hubskills-lms' ) );
    }

    $enrollments = function_exists( 'hs_get_user_enrollments' ) ? hs_get_user_enrollments( $user_id ) : ( get_user_meta( $user_id, 'hs_enrollments', true ) ?: [] );
    $key = function_exists( 'hs_get_enrollment_key' ) ? hs_get_enrollment_key( $enrollments, $course_id ) : $course_id;
    if ( $key === null || empty( $enrollments[ $key ] ) || ! is_array( $enrollments[ $key ] ) ) {
        return new WP_Error( 'not_enrolled', __( 'Você não está matriculado neste curso.', 'hubskills-lms' ) );
    }
    $data        = &$enrollments[ $key ];

    $completed = hs_normalize_completed_lessons( (array)( $data['completed_lessons'] ?? [] ) );
    if ( in_array( $lesson_id, $completed, true ) ) {
        return new WP_Error( 'already_done', __( 'Aula já concluída.', 'hubskills-lms' ) );
    }

    $completed[]                  = $lesson_id;
    $data['completed_lessons']    = hs_normalize_completed_lessons( $completed );
    $data['last_accessed']        = current_time( 'Y-m-d' );

    $total_lessons = hs_course_total_required_parts( $course_id );
    $data['progress'] = $total_lessons > 0 ? min( 100, round( count( $data['completed_lessons'] ) / $total_lessons * 100 ) ) : 0;
    $completed_now = hs_complete_course_if_ready( $user_id, $course_id, $data );

    update_user_meta( $user_id, 'hs_enrollments', $enrollments );

    // Award XP + update streak
    $xp = hs_award_xp( $user_id, 50 );
    hs_update_streak( $user_id );

    // Check badges
    if ( count( $data['completed_lessons'] ) === 1 ) hs_award_badge( $user_id, 'first_lesson' );

    // Log activity
    hs_log_activity( $user_id, 'lesson', sprintf(
        __( 'Concluiu aula <strong>#%d</strong> em %s', 'hubskills-lms' ),
        $lesson_id,
        get_the_title( $course_id )
    ) );

    // Update weekly minutes (approximate: assume each lesson ~15 min)
    hs_add_weekly_minutes( $user_id, 15 );

    do_action( 'hs_after_complete_lesson', $user_id, $course_id, $lesson_id, $data );

    return [
        'xp_gained'       => 50,
        'progress'        => (int) $data['progress'],
        'new_xp'          => $xp,
        'completed'       => ( $data['status'] ?? '' ) === 'completed',
        'completed_now'   => $completed_now,
        'certificate_url' => ( $data['status'] ?? '' ) === 'completed' ? hs_course_certificate_url( $course_id ) : '',
    ];
}

/**
 * Get progress for a course.
 */
function hs_get_progress( int $user_id, int $course_id ) {
    $enrollment = function_exists( 'hs_get_enrollment' ) ? hs_get_enrollment( $user_id, $course_id ) : [];
    return (int)( $enrollment['progress'] ?? 0 );
}

/**
 * Check if a lesson is available (drip content).
 * If the course has drip days set, lessons are released X days after enrollment.
 */
function hs_is_lesson_available( int $user_id, int $course_id, int $lesson_index ) {
    $drip_days = (int) get_post_meta( $course_id, '_hs_drip_days', true );
    if ( $drip_days <= 0 ) return true;

    $enrollment = hs_get_enrollment( $user_id, $course_id );
    if ( empty( $enrollment ) ) return false;

    $enrolled_date = $enrollment['enrolled_date'] ?? current_time( 'Y-m-d' );
    $available_from = date( 'Y-m-d', strtotime( $enrolled_date . ' +' . ( $lesson_index * $drip_days ) . ' days' ) );
    $today = current_time( 'Y-m-d' );

    return $today >= $available_from;
}

function hs_get_course_lesson_info( int $course_id, int $lesson_id ) {
    if ( $lesson_id <= 0 || get_post_type( $course_id ) !== 'hubskills_course' ) {
        return null;
    }

    $curriculum = get_post_meta( $course_id, '_hs_curriculum', true );
    if ( ! is_array( $curriculum ) ) {
        return null;
    }

    $index = 0;
    foreach ( $curriculum as $section_index => $section ) {
        foreach ( (array) ( $section['lessons'] ?? [] ) as $lesson ) {
            $current_id = (int) ( $lesson['id'] ?? 0 );
            $quiz_id    = (int) ( $lesson['quiz_id'] ?? 0 );
            if ( $current_id === $lesson_id || $quiz_id === $lesson_id ) {
                return [
                    'lesson'        => $lesson,
                    'section'       => $section,
                    'index'         => $index,
                    'section_index' => (int) $section_index,
                ];
            }
            $index++;
        }
    }

    return null;
}

function hs_user_can_access_lesson( int $user_id, int $course_id, int $lesson_id ) {
    if ( ! hs_is_enrolled( $user_id, $course_id ) ) {
        return new WP_Error( 'not_enrolled', __( 'Você não está matriculado neste curso.', 'hubskills-lms' ) );
    }

    $lesson_info = hs_get_course_lesson_info( $course_id, $lesson_id );
    if ( ! $lesson_info ) {
        return new WP_Error( 'invalid_lesson', __( 'Aula não pertence a este curso.', 'hubskills-lms' ) );
    }

    if ( ! hs_is_lesson_available( $user_id, $course_id, (int) $lesson_info['index'] ) ) {
        return new WP_Error( 'lesson_locked', __( 'Esta aula ainda não está disponível.', 'hubskills-lms' ) );
    }

    return true;
}

function hs_user_can_access_quiz( int $user_id, int $quiz_id ) {
    if ( get_post_type( $quiz_id ) !== 'hubskills_quiz' ) {
        return new WP_Error( 'invalid_quiz', __( 'Quiz inválido.', 'hubskills-lms' ) );
    }

    $enrollments = get_user_meta( $user_id, 'hs_enrollments', true );
    if ( ! is_array( $enrollments ) || empty( $enrollments ) ) {
        return new WP_Error( 'not_enrolled', __( 'Você precisa estar matriculado no curso para acessar este quiz.', 'hubskills-lms' ) );
    }

    foreach ( array_keys( $enrollments ) as $course_id ) {
        $lesson_info = hs_get_course_lesson_info( (int) $course_id, $quiz_id );
        if ( $lesson_info ) {
            return hs_user_can_access_lesson( $user_id, (int) $course_id, $quiz_id );
        }
    }

    return new WP_Error( 'quiz_not_available', __( 'Quiz não disponível para as suas matrículas.', 'hubskills-lms' ) );
}

add_action( 'hs_quiz_completed', 'hs_complete_quiz_lesson_on_pass', 10, 3 );
function hs_complete_quiz_lesson_on_pass( int $user_id, int $quiz_id, array $result_data ) {
    if ( empty( $result_data['passed'] ) ) return;

    $enrollments = get_user_meta( $user_id, 'hs_enrollments', true );
    if ( ! is_array( $enrollments ) ) return;

    foreach ( array_keys( $enrollments ) as $course_id ) {
        $course_id = (int) $course_id;
        $lesson_info = hs_get_course_lesson_info( $course_id, $quiz_id );
        if ( ! $lesson_info ) continue;

        $lesson = (array) ( $lesson_info['lesson'] ?? [] );
        $lesson_id = (int) ( $lesson['id'] ?? 0 );
        if ( ! $lesson_id ) return;

        $key = function_exists( 'hs_get_enrollment_key' ) ? hs_get_enrollment_key( $enrollments, $course_id ) : $course_id;
        if ( $key === null ) continue;

        $completed = hs_normalize_completed_lessons( (array) ( $enrollments[ $key ]['completed_lessons'] ?? [] ) );
        if ( in_array( $lesson_id, $completed, true ) ) {
            hs_sync_course_progress( $user_id, $course_id );
            return;
        }

        hs_complete_lesson( $user_id, $course_id, $lesson_id );
        return;
    }
}

/**
 * Track weekly learning minutes for chart data.
 */
function hs_add_weekly_minutes( int $user_id, int $minutes ) {
    $weekly = get_user_meta( $user_id, 'hs_weekly_minutes', true );
    if ( ! is_array( $weekly ) ) $weekly = array_fill( 0, 7, 0 );
    $day_of_week = (int) date('N') - 1; // 0=Mon, 6=Sun
    $weekly[ $day_of_week ] += $minutes;
    update_user_meta( $user_id, 'hs_weekly_minutes', $weekly );
}
