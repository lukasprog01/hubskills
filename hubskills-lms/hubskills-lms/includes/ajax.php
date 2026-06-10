<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Helper: verify nonce and login
function hs_ajax_check( string $action = 'hubskills_nonce' ) {
    if ( ! check_ajax_referer( $action, 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => __( 'Sessão expirada. Recarregue a página.', 'hubskills-lms' ) ], 403 );
    }
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => __( 'Você precisa estar logado.', 'hubskills-lms' ) ], 401 );
    }
}

// ── Enroll ────────────────────────────────────────────────
add_action( 'wp_ajax_hs_enroll', 'hs_ajax_enroll' );
function hs_ajax_enroll() {
    hs_ajax_check();
    $user_id   = get_current_user_id();
    $course_id = (int)( $_POST['course_id'] ?? 0 );
    if ( ! $course_id ) wp_send_json_error( [ 'message' => __( 'ID de curso inválido.', 'hubskills-lms' ) ] );

    $result = hs_enroll_user( $user_id, $course_id );
    if ( is_wp_error( $result ) ) {
        if ( $result->get_error_code() === 'already_enrolled' ) {
            wp_send_json_success( [
                'message'          => __( 'Você já está matriculado neste curso.', 'hubskills-lms' ),
                'already_enrolled' => true,
                'course_url'       => get_permalink( $course_id ),
                'enrollment'       => function_exists( 'hs_get_enrollment' ) ? hs_get_enrollment( $user_id, $course_id ) : [],
            ] );
        }
        $status = $result->get_error_code() === 'payment_required' ? 402 : 400;
        wp_send_json_error( [ 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ], $status );
    }
    wp_send_json_success( [
        'message'    => __( 'Matrícula realizada com sucesso!', 'hubskills-lms' ),
        'course_url' => get_permalink( $course_id ),
        'enrollment' => function_exists( 'hs_get_enrollment' ) ? hs_get_enrollment( $user_id, $course_id ) : [],
    ] );
}

// ── Complete Lesson ───────────────────────────────────────
add_action( 'wp_ajax_hs_complete_lesson', 'hs_ajax_complete_lesson' );
function hs_ajax_complete_lesson() {
    hs_ajax_check();
    $user_id   = get_current_user_id();
    $course_id = (int)( $_POST['course_id'] ?? 0 );
    $lesson_id = (int)( $_POST['lesson_id'] ?? 0 );
    if ( ! $course_id || ! $lesson_id ) wp_send_json_error( [ 'message' => __( 'Parâmetros inválidos.', 'hubskills-lms' ) ] );

    $result = hs_complete_lesson( $user_id, $course_id, $lesson_id );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }
    wp_send_json_success( $result );
}

// ── Submit Quiz ───────────────────────────────────────────
add_action( 'wp_ajax_hs_submit_quiz', 'hs_ajax_submit_quiz' );
function hs_ajax_submit_quiz() {
    hs_ajax_check();
    $user_id = get_current_user_id();
    $quiz_id = (int)( $_POST['quiz_id'] ?? 0 );
    $answers = array_map( 'intval', (array)( $_POST['answers'] ?? [] ) );
    if ( ! $quiz_id || get_post_type( $quiz_id ) !== 'hubskills_quiz' ) wp_send_json_error( [ 'message' => __( 'Quiz inválido.', 'hubskills-lms' ) ] );

    if ( function_exists( 'hs_user_can_access_quiz' ) ) {
        $access = hs_user_can_access_quiz( $user_id, $quiz_id );
        if ( is_wp_error( $access ) ) {
            wp_send_json_error( [ 'message' => $access->get_error_message() ], 403 );
        }
    }

    $grade  = hs_grade_quiz( $quiz_id, $answers );
    $result = hs_save_quiz_result( $user_id, $quiz_id, $grade['score'], $grade['total'], $answers );
    wp_send_json_success( array_merge( $result, [ 'details' => $grade['details'] ] ) );
}

// ── Get Quiz Data ─────────────────────────────────────────
add_action( 'wp_ajax_hs_get_quiz',        'hs_ajax_get_quiz' );
function hs_ajax_get_quiz() {
    hs_ajax_check();
    $user_id = get_current_user_id();
    $quiz_id = (int)( $_GET['quiz_id'] ?? 0 );
    if ( ! $quiz_id || get_post_type( $quiz_id ) !== 'hubskills_quiz' ) {
        wp_send_json_error( [ 'message' => __( 'Quiz não encontrado.', 'hubskills-lms' ) ] );
    }

    if ( function_exists( 'hs_user_can_access_quiz' ) ) {
        $access = hs_user_can_access_quiz( $user_id, $quiz_id );
        if ( is_wp_error( $access ) ) {
            wp_send_json_error( [ 'message' => $access->get_error_message() ], 403 );
        }
    }

    $questions  = hs_get_quiz_questions( $quiz_id );
    $sanitized  = array_map( function( $q ) {
        return [
            'id'      => (int)($q['id'] ?? 0),
            'text'    => wp_kses_post( $q['text'] ?? '' ),
            'options' => array_map( 'sanitize_text_field', (array)($q['options'] ?? []) ),
        ];
    }, $questions );

    wp_send_json_success( [
        'id'         => $quiz_id,
        'title'      => get_the_title( $quiz_id ),
        'time_limit' => (int)get_post_meta( $quiz_id, '_hs_time_limit', true ),
        'questions'  => $sanitized,
        'count'      => count( $sanitized ),
    ] );
}

// ── Get User Stats ────────────────────────────────────────
add_action( 'wp_ajax_hs_get_stats', 'hs_ajax_get_stats' );
function hs_ajax_get_stats() {
    hs_ajax_check();
    wp_send_json_success( hs_get_user_stats( get_current_user_id() ) );
}

// ── Unenroll ──────────────────────────────────────────────
add_action( 'wp_ajax_hs_unenroll', 'hs_ajax_unenroll' );
function hs_ajax_unenroll() {
    hs_ajax_check();
    $course_id = (int)( $_POST['course_id'] ?? 0 );
    $result    = hs_unenroll_user( get_current_user_id(), $course_id );
    $result ? wp_send_json_success() : wp_send_json_error( [ 'message' => __( 'Erro ao cancelar matrícula.', 'hubskills-lms' ) ] );
}

// ── Submit Review ─────────────────────────────────────────
add_action( 'wp_ajax_hs_submit_review', 'hs_ajax_submit_review' );
function hs_ajax_submit_review() {
    hs_ajax_check();
    $user_id   = get_current_user_id();
    $course_id = (int)( $_POST['course_id'] ?? 0 );
    $rating    = (int)( $_POST['rating'] ?? 0 );
    $comment   = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );

    $result = hs_submit_review( $user_id, $course_id, $rating, $comment );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }
    wp_send_json_success( [ 'message' => __( 'Avaliação enviada!', 'hubskills-lms' ) ] );
}

// ── Save Lesson Notes ─────────────────────────────────────
add_action( 'wp_ajax_hs_save_notes', 'hs_ajax_save_notes' );
function hs_ajax_save_notes() {
    hs_ajax_check();
    $user_id   = get_current_user_id();
    $course_id = (int)( $_POST['course_id'] ?? 0 );
    $lesson_id = (int)( $_POST['lesson_id'] ?? 0 );
    $content   = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) );
    $key       = 'hs_notes_' . $course_id . '_' . $lesson_id;

    if ( function_exists( 'hs_user_can_access_lesson' ) ) {
        $access = hs_user_can_access_lesson( $user_id, $course_id, $lesson_id );
        if ( is_wp_error( $access ) ) {
            wp_send_json_error( [ 'message' => $access->get_error_message() ], 403 );
        }
    }

    $all_notes = (array)( get_user_meta( $user_id, 'hs_lesson_notes', true ) ?: [] );
    $all_notes[ $key ] = [ 'content' => $content, 'updated' => current_time( 'mysql' ) ];
    update_user_meta( $user_id, 'hs_lesson_notes', $all_notes );

    wp_send_json_success( [ 'message' => __( 'Anotações salvas!', 'hubskills-lms' ) ] );
}

// ── Get Lesson Notes ──────────────────────────────────────
add_action( 'wp_ajax_hs_get_notes', 'hs_ajax_get_notes' );
function hs_ajax_get_notes() {
    hs_ajax_check();
    $user_id   = get_current_user_id();
    $course_id = (int)( $_GET['course_id'] ?? 0 );
    $lesson_id = (int)( $_GET['lesson_id'] ?? 0 );
    $key       = 'hs_notes_' . $course_id . '_' . $lesson_id;

    if ( function_exists( 'hs_user_can_access_lesson' ) ) {
        $access = hs_user_can_access_lesson( $user_id, $course_id, $lesson_id );
        if ( is_wp_error( $access ) ) {
            wp_send_json_error( [ 'message' => $access->get_error_message() ], 403 );
        }
    }

    $all_notes = (array)( get_user_meta( $user_id, 'hs_lesson_notes', true ) ?: [] );
    $notes     = $all_notes[ $key ]['content'] ?? '';

    wp_send_json_success( [ 'content' => $notes ] );
}

// ── Edição de perfil no frontend (sem wp-admin) ────────────────────────────
add_action( 'wp_ajax_hs_update_profile', 'hs_ajax_update_profile' );
function hs_ajax_update_profile() {
    hs_ajax_check();

    $user_id = get_current_user_id();
    $display_name = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
    $email        = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
    $password     = (string) ( $_POST['new_password'] ?? '' );
    $password2    = (string) ( $_POST['new_password_confirm'] ?? '' );

    if ( mb_strlen( $display_name ) < 2 ) {
        wp_send_json_error( [ 'message' => __( 'Informe um nome válido.', 'hubskills-lms' ) ], 400 );
    }
    if ( ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => __( 'Informe um e-mail válido.', 'hubskills-lms' ) ], 400 );
    }
    $existing = email_exists( $email );
    if ( $existing && (int) $existing !== $user_id ) {
        wp_send_json_error( [ 'message' => __( 'Este e-mail já está em uso por outra conta.', 'hubskills-lms' ) ], 400 );
    }

    $data = [
        'ID'           => $user_id,
        'display_name' => $display_name,
        'user_email'   => $email,
    ];

    if ( $password !== '' ) {
        $strong = function_exists( 'hs_lms_password_is_strong' )
            ? hs_lms_password_is_strong( $password )
            : ( strlen( $password ) >= 8 );
        if ( ! $strong ) {
            wp_send_json_error( [ 'message' => __( 'A nova senha precisa ter ao menos 8 caracteres, com letras e números.', 'hubskills-lms' ) ], 400 );
        }
        if ( $password !== $password2 ) {
            wp_send_json_error( [ 'message' => __( 'A confirmação de senha não confere.', 'hubskills-lms' ) ], 400 );
        }
        $data['user_pass'] = $password;
    }

    $result = wp_update_user( $data );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
    }

    if ( function_exists( 'hs_audit_log' ) ) {
        hs_audit_log( $user_id, 'update_profile', 'Perfil atualizado pelo dashboard' );
    }

    // Senha alterada derruba a sessão atual — reautentica para manter o usuário logado
    if ( $password !== '' ) {
        wp_set_auth_cookie( $user_id, true );
    }

    wp_send_json_success( [ 'message' => __( 'Perfil atualizado com sucesso!', 'hubskills-lms' ) ] );
}
