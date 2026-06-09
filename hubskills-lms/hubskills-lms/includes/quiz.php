<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Save a quiz result for a user.
 *
 * @param int   $user_id
 * @param int   $quiz_id  (post ID of hubskills_quiz)
 * @param int   $score    number of correct answers
 * @param int   $total    total questions
 * @param array $answers  user's answer indices keyed by question index
 * @return array
 */
function hs_save_quiz_result( int $user_id, int $quiz_id, int $score, int $total, array $answers = [] ) {
    $percentage  = $total > 0 ? round( $score / $total * 100 ) : 0;
    $pass_threshold = (int)( get_post_meta( $quiz_id, '_hs_pass_threshold', true ) ?: 70 );
    $passed      = $percentage >= $pass_threshold;

    $results     = (array)( get_user_meta( $user_id, 'hs_quiz_results', true ) ?: [] );
    $prev        = $results[ $quiz_id ] ?? null;

    $result_data = [
        'score'      => $score,
        'total'      => $total,
        'percentage' => $percentage,
        'passed'     => $passed,
        'attempts'   => ( $prev['attempts'] ?? 0 ) + 1,
        'answers'    => $answers,
        'date'       => current_time( 'Y-m-d H:i' ),
    ];

    $results[ $quiz_id ] = $result_data;
    update_user_meta( $user_id, 'hs_quiz_results', $results );

    // XP rewards
    $xp_gained = $passed ? 200 : 50;
    hs_award_xp( $user_id, $xp_gained );
    hs_update_streak( $user_id );

    if ( $passed ) {
        hs_award_badge( $user_id, 'quiz_master' );
        if ( $percentage === 100 ) hs_award_badge( $user_id, 'perfectionist' );

        $quiz_title = get_the_title( $quiz_id );
        hs_log_activity( $user_id, 'quiz', sprintf(
            __( 'Passou no quiz <strong>%s</strong> com %d%%! 🧠', 'hubskills-lms' ),
            esc_html( $quiz_title ),
            $percentage
        ) );
    } else {
        hs_log_activity( $user_id, 'quiz', sprintf(
            __( 'Fez o quiz <strong>%s</strong> — %d%%. Continue tentando!', 'hubskills-lms' ),
            esc_html( get_the_title( $quiz_id ) ),
            $percentage
        ) );
    }

    do_action( 'hs_quiz_completed', $user_id, $quiz_id, $result_data );

    $course_progress = [];
    if ( $passed && function_exists( 'hs_get_course_lesson_info' ) ) {
        $enrollments = get_user_meta( $user_id, 'hs_enrollments', true );
        foreach ( array_keys( is_array( $enrollments ) ? $enrollments : [] ) as $course_id ) {
            $course_id = (int) $course_id;
            if ( ! hs_get_course_lesson_info( $course_id, $quiz_id ) ) continue;

            $sync = function_exists( 'hs_sync_course_progress' ) ? hs_sync_course_progress( $user_id, $course_id ) : [];
            $course_progress = [
                'course_id'       => $course_id,
                'progress'        => (int) ( $sync['progress'] ?? 0 ),
                'course_completed'=> ! empty( $sync['completed'] ),
                'certificate_url' => $sync['certificate_url'] ?? '',
            ];
            break;
        }
    }

    return array_merge( $result_data, [ 'xp_gained' => $xp_gained ], $course_progress );
}

/**
 * Get quiz result for a user.
 */
function hs_get_quiz_result( int $user_id, int $quiz_id ) {
    $results = get_user_meta( $user_id, 'hs_quiz_results', true ) ?: [];
    return $results[ $quiz_id ] ?? [];
}

/**
 * Get questions for a quiz.
 */
function hs_get_quiz_questions( int $quiz_id ) {
    return (array)( get_post_meta( $quiz_id, '_hs_questions', true ) ?: [] );
}

/**
 * Grade a quiz submission.
 *
 * @param int   $quiz_id
 * @param array $answers  [ question_index => chosen_option_index ]
 * @return array { score, total, correct_answers }
 */
function hs_grade_quiz( int $quiz_id, array $answers ) {
    $questions = hs_get_quiz_questions( $quiz_id );
    $total     = count( $questions );
    $score     = 0;
    $correct   = [];

    foreach ( $questions as $i => $q ) {
        $is_correct = isset( $answers[ $i ] ) && (int)$answers[ $i ] === (int)$q['correct'];
        if ( $is_correct ) $score++;
        $correct[ $i ] = [
            'correct'     => $is_correct,
            'user_answer' => $answers[ $i ] ?? null,
            'right_answer'=> $q['correct'],
            'explanation' => $q['explanation'] ?? '',
        ];
    }

    return [ 'score' => $score, 'total' => $total, 'details' => $correct ];
}
