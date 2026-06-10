<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Instructor Application ──────────────────────────────────────────────────

/**
 * Submit an instructor application.
 */
function hs_submit_instructor_application( int $user_id, array $data ) {
    $existing = get_user_meta( $user_id, 'hs_instructor_application', true );
    if ( $existing && in_array( $existing['status'] ?? '', [ 'pending', 'approved' ], true ) ) {
        return new WP_Error( 'already_applied', __( 'Você já possui uma candidatura ativa.', 'hubskills-lms' ) );
    }

    $application = [
        'status'     => 'pending',
        'name'       => sanitize_text_field( $data['name'] ?? '' ),
        'bio'        => sanitize_textarea_field( $data['bio'] ?? '' ),
        'expertise'  => sanitize_text_field( $data['expertise'] ?? '' ),
        'experience' => sanitize_textarea_field( $data['experience'] ?? '' ),
        'linkedin'   => esc_url_raw( $data['linkedin'] ?? '' ),
        'youtube'    => esc_url_raw( $data['youtube'] ?? '' ),
        'website'    => esc_url_raw( $data['website'] ?? '' ),
        'areas'      => array_map( 'sanitize_text_field', (array)( $data['areas'] ?? [] ) ),
        'submitted'  => current_time( 'mysql' ),
    ];

    update_user_meta( $user_id, 'hs_instructor_application', $application );

    hs_notify_admin_new_application( $user_id, $application );

    return true;
}

/**
 * Approve an instructor application.
 */
function hs_approve_instructor( int $user_id, int $admin_id = 0 ) {
    $application = get_user_meta( $user_id, 'hs_instructor_application', true );
    if ( ! $application ) {
        return new WP_Error( 'no_application', __( 'Candidatura não encontrada.', 'hubskills-lms' ) );
    }

    $application['status']      = 'approved';
    $application['reviewed_by'] = $admin_id;
    $application['reviewed_at'] = current_time( 'mysql' );

    update_user_meta( $user_id, 'hs_instructor_application', $application );
    hs_promote_to_instructor( $user_id );

    $profile = [
        'bio'       => $application['bio'] ?? '',
        'expertise' => $application['expertise'] ?? '',
        'linkedin'  => $application['linkedin'] ?? '',
        'youtube'   => $application['youtube'] ?? '',
        'website'   => $application['website'] ?? '',
        'areas'     => $application['areas'] ?? [],
        'rating'    => 0,
        'students'  => 0,
        'courses'   => 0,
    ];
    update_user_meta( $user_id, 'hs_instructor_profile', $profile );

    if ( function_exists( 'hs_audit_log' ) ) {
        hs_audit_log( $admin_id, 'approve_instructor', 'Instructor aprovado: user #' . $user_id );
    }

    hs_notify_instructor_approved( $user_id );

    return true;
}

/**
 * Reject an instructor application.
 */
function hs_reject_instructor( int $user_id, string $reason = '', int $admin_id = 0 ) {
    $application = get_user_meta( $user_id, 'hs_instructor_application', true );
    if ( ! $application ) {
        return new WP_Error( 'no_application', __( 'Candidatura não encontrada.', 'hubskills-lms' ) );
    }

    $application['status']      = 'rejected';
    $application['reject_reason'] = sanitize_textarea_field( $reason );
    $application['reviewed_by'] = $admin_id;
    $application['reviewed_at'] = current_time( 'mysql' );

    update_user_meta( $user_id, 'hs_instructor_application', $application );

    if ( function_exists( 'hs_audit_log' ) ) {
        hs_audit_log( $admin_id, 'reject_instructor', 'Instructor rejeitado: user #' . $user_id . ' — ' . $reason );
    }

    hs_notify_instructor_rejected( $user_id, $reason );

    return true;
}

/**
 * Get all instructor applications (paginated).
 */
function hs_get_instructor_applications( string $status = 'all', int $limit = 20, int $offset = 0 ) {
    $args = [
        'meta_key'    => 'hs_instructor_application',
        'number'      => $limit,
        'offset'      => $offset,
        'count_total' => false,
    ];

    $users = get_users( $args );
    $result = [];

    foreach ( $users as $user ) {
        $app = get_user_meta( $user->ID, 'hs_instructor_application', true );
        if ( ! is_array( $app ) ) continue;
        if ( $status !== 'all' && ( $app['status'] ?? '' ) !== $status ) continue;

        $result[] = array_merge( $app, [
            'user_id'      => $user->ID,
            'display_name' => $user->display_name,
            'email'        => $user->user_email,
            'avatar'       => get_avatar_url( $user->ID, [ 'size' => 48 ] ),
        ] );
    }

    return $result;
}

// ── Instructor Profile ──────────────────────────────────────────────────────

/**
 * Update instructor profile data.
 */
function hs_update_instructor_profile( int $user_id, array $data ) {
    if ( ! hs_is_instructor( $user_id ) && ! hs_is_hs_admin( $user_id ) ) {
        return new WP_Error( 'not_instructor', __( 'Acesso negado.', 'hubskills-lms' ) );
    }

    $profile = get_user_meta( $user_id, 'hs_instructor_profile', true );
    if ( ! is_array( $profile ) ) $profile = [];

    $allowed = [ 'bio', 'expertise', 'linkedin', 'youtube', 'website', 'areas', 'tagline' ];
    foreach ( $allowed as $key ) {
        if ( isset( $data[ $key ] ) ) {
            if ( in_array( $key, [ 'linkedin', 'youtube', 'website' ], true ) ) {
                $profile[ $key ] = esc_url_raw( $data[ $key ] );
            } elseif ( $key === 'areas' ) {
                $profile[ $key ] = array_map( 'sanitize_text_field', (array)$data[ $key ] );
            } elseif ( $key === 'bio' ) {
                $profile[ $key ] = sanitize_textarea_field( $data[ $key ] );
            } else {
                $profile[ $key ] = sanitize_text_field( $data[ $key ] );
            }
        }
    }

    update_user_meta( $user_id, 'hs_instructor_profile', $profile );
    return true;
}

/**
 * Get instructor profile data.
 */
function hs_get_instructor_profile( int $user_id ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) return null;

    $profile = (array)( get_user_meta( $user_id, 'hs_instructor_profile', true ) ?: [] );

    return array_merge( [
        'user_id'      => $user_id,
        'display_name' => $user->display_name,
        'email'        => $user->user_email,
        'avatar'       => get_avatar_url( $user_id, [ 'size' => 120 ] ),
        'bio'          => '',
        'expertise'    => '',
        'tagline'      => '',
        'linkedin'     => '',
        'youtube'      => '',
        'website'      => '',
        'areas'        => [],
        'rating'       => 0,
        'students'     => 0,
        'courses'      => 0,
        'joined'       => get_user_meta( $user_id, 'hs_instructor_since', true ) ?: $user->user_registered,
    ], $profile );
}

// ── Instructor Stats ────────────────────────────────────────────────────────

/**
 * Get stats for an instructor (courses, enrolled students, earnings).
 */
function hs_get_instructor_stats( int $user_id ) {
    $courses = get_posts([
        'post_type'      => 'hubskills_course',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_query'     => [[
            'key'   => '_hs_instructor_id',
            'value' => $user_id,
        ]],
        'fields' => 'ids',
    ]);

    $total_students = 0;
    $total_earnings = 0.0;

    foreach ( $courses as $course_id ) {
        $total_students += function_exists( 'hs_course_student_count' )
            ? hs_course_student_count( (int) $course_id )
            : max( (int) get_post_meta( $course_id, '_hs_students', true ), (int) get_post_meta( $course_id, '_hs_student_count', true ) );

        // Sum earnings from paid orders
        global $wpdb;
        $course_revenue = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM( pm.meta_value )
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id
             INNER JOIN {$wpdb->postmeta} pm3 ON pm.post_id = pm3.post_id
             WHERE pm.meta_key   = '_hs_order_amount'
               AND pm2.meta_key  = '_hs_order_course_id' AND pm2.meta_value = %d
               AND pm3.meta_key  = '_hs_order_status'    AND pm3.meta_value = 'paid'",
            $course_id
        ) );

        $commission_rate = (float)( get_user_meta( $user_id, 'hs_instructor_commission_rate', true ) ?: 70 );
        $total_earnings += $course_revenue * ( $commission_rate / 100 );
    }

    return [
        'courses'  => count( $courses ),
        'students' => $total_students,
        'earnings' => round( $total_earnings, 2 ),
        'rating'   => (float)( get_user_meta( $user_id, 'hs_instructor_rating', true ) ?: 0 ),
    ];
}

/**
 * Get all instructors list for admin.
 */
function hs_get_all_instructors( int $limit = 50, int $offset = 0 ) {
    $users = get_users([
        'role'   => 'hs_instructor',
        'number' => $limit,
        'offset' => $offset,
    ]);

    $result = [];
    foreach ( $users as $user ) {
        $stats   = hs_get_instructor_stats( $user->ID );
        $profile = hs_get_instructor_profile( $user->ID );
        $result[] = array_merge( $profile, $stats, [
            'commission' => (int)( get_user_meta( $user->ID, 'hs_instructor_commission_rate', true ) ?: 70 ),
            'status'     => get_user_meta( $user->ID, 'hs_account_status', true ) ?: 'active',
        ] );
    }

    return $result;
}

// ── Instructor Courses ──────────────────────────────────────────────────────

/**
 * Get courses belonging to an instructor.
 */
function hs_get_instructor_courses( int $user_id ) {
    return get_posts([
        'post_type'      => 'hubskills_course',
        'posts_per_page' => -1,
        'post_status'    => [ 'publish', 'draft', 'pending' ],
        'meta_query'     => [[
            'key'   => '_hs_instructor_id',
            'value' => $user_id,
        ]],
    ]);
}

// ── Email Notifications ─────────────────────────────────────────────────────

function hs_notify_admin_new_application( int $user_id, array $application ) {
    $admin_email = get_option( 'admin_email' );
    $user        = get_userdata( $user_id );
    if ( ! $user ) return;

    $subject = sprintf( __( '[HubSkills] Nova candidatura de instrutor: %s', 'hubskills-lms' ), $user->display_name );
    $body    = sprintf(
        __( "Olá,\n\nUm novo usuário deseja se tornar instrutor:\n\nNome: %s\nEmail: %s\nEspecialidade: %s\n\nAcesse o painel para aprovar ou rejeitar.\n\n— HubSkills LMS", 'hubskills-lms' ),
        $user->display_name,
        $user->user_email,
        $application['expertise'] ?? ''
    );

    wp_mail( $admin_email, $subject, $body );
}

function hs_notify_instructor_approved( int $user_id ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) return;

    $subject = __( '[HubSkills] Parabéns! Sua candidatura foi aprovada', 'hubskills-lms' );
    $body    = sprintf(
        __( "Olá %s,\n\nSua candidatura para instrutor foi aprovada!\n\nVocê já pode acessar o painel de instrutor e criar seus cursos.\n\n— HubSkills LMS", 'hubskills-lms' ),
        $user->display_name
    );

    wp_mail( $user->user_email, $subject, $body );
}

function hs_notify_instructor_rejected( int $user_id, string $reason = '' ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) return;

    $subject = __( '[HubSkills] Sobre sua candidatura de instrutor', 'hubskills-lms' );
    $body    = sprintf(
        __( "Olá %s,\n\nInfelizmente sua candidatura de instrutor não foi aprovada neste momento.\n\n%s\n\nVocê pode se candidatar novamente no futuro.\n\n— HubSkills LMS", 'hubskills-lms' ),
        $user->display_name,
        $reason ? 'Motivo: ' . $reason : ''
    );

    wp_mail( $user->user_email, $subject, $body );
}

// ── AJAX: Instructor Application ─────────────────────────────────────────────

add_action( 'wp_ajax_hs_apply_instructor', 'hs_ajax_apply_instructor' );
function hs_ajax_apply_instructor() {
    check_ajax_referer( 'hubskills_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => __( 'Faça login primeiro.', 'hubskills-lms' ) ] );
    }
    if ( function_exists( 'hs_lms_honeypot_is_bot' ) && hs_lms_honeypot_is_bot( $_POST ) ) {
        if ( function_exists( 'hs_audit_log' ) ) hs_audit_log( get_current_user_id(), 'bot_blocked', 'Candidatura de instrutor bloqueada pelo honeypot' );
        wp_send_json_error( [ 'message' => __( 'Não foi possível validar o envio. Tente novamente.', 'hubskills-lms' ) ], 400 );
    }

    $user_id = get_current_user_id();
    $result  = hs_submit_instructor_application( $user_id, $_POST );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }

    wp_send_json_success( [ 'message' => __( 'Candidatura enviada com sucesso!', 'hubskills-lms' ) ] );
}

add_action( 'wp_ajax_hs_update_instructor_profile', 'hs_ajax_update_instructor_profile' );
function hs_ajax_update_instructor_profile() {
    check_ajax_referer( 'hubskills_nonce', 'nonce' );
    $user_id = get_current_user_id();

    $result = hs_update_instructor_profile( $user_id, $_POST );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }

    wp_send_json_success( [ 'message' => __( 'Perfil atualizado!', 'hubskills-lms' ) ] );
}
