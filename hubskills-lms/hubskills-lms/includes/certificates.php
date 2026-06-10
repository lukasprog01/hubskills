<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function hs_certificate_templates(): array {
    return [
        'classic'   => __( 'Classico institucional', 'hubskills-lms' ),
        'modern'    => __( 'Moderno azul e ciano', 'hubskills-lms' ),
        'executive' => __( 'Executivo premium', 'hubskills-lms' ),
    ];
}

function hs_certificate_default_course_settings( int $course_id ): array {
    return [
        'template'        => 'modern',
        'auto_issue'      => '1',
        'course_workload' => get_post_meta( $course_id, '_hs_duration', true ),
        'instructor_name' => get_post_meta( $course_id, '_hs_instructor', true ),
        'issuer_name'     => get_bloginfo( 'name' ) ?: 'HubSkills',
        'issuer_title'    => __( 'Diretor Academico', 'hubskills-lms' ),
        'location'        => '',
        'note'            => '',
    ];
}

function hs_certificate_sanitize_course_settings( array $settings ): array {
    $templates = array_keys( hs_certificate_templates() );
    $template = sanitize_key( $settings['template'] ?? 'modern' );
    if ( ! in_array( $template, $templates, true ) ) {
        $template = 'modern';
    }

    return [
        'template'        => $template,
        'auto_issue'      => ( $settings['auto_issue'] ?? '1' ) === '0' ? '0' : '1',
        'course_workload' => sanitize_text_field( wp_unslash( $settings['course_workload'] ?? '' ) ),
        'instructor_name' => sanitize_text_field( wp_unslash( $settings['instructor_name'] ?? '' ) ),
        'issuer_name'     => sanitize_text_field( wp_unslash( $settings['issuer_name'] ?? '' ) ),
        'issuer_title'    => sanitize_text_field( wp_unslash( $settings['issuer_title'] ?? '' ) ),
        'location'        => sanitize_text_field( wp_unslash( $settings['location'] ?? '' ) ),
        'note'            => sanitize_textarea_field( wp_unslash( $settings['note'] ?? '' ) ),
    ];
}

/**
 * Configuração bruta salva no curso (campos vazios = "puxar automático").
 */
function hs_certificate_course_settings_raw( int $course_id ): array {
    $stored = get_post_meta( $course_id, '_hs_certificate_settings', true );
    return is_array( $stored ) ? $stored : [];
}

/**
 * Configuração efetiva: o que foi salvo manualmente vence; campos vazios
 * caem para os dados automáticos do banco (curso, instrutor, site).
 */
function hs_certificate_course_settings( int $course_id ): array {
    $stored = hs_certificate_course_settings_raw( $course_id );
    // Strings vazias NÃO sobrescrevem os padrões automáticos
    $stored = array_filter( $stored, static function ( $value ) {
        return $value !== '' && $value !== null;
    } );

    return hs_certificate_sanitize_course_settings( array_merge( hs_certificate_default_course_settings( $course_id ), $stored ) );
}

function hs_save_certificate_course_settings( int $course_id, array $settings ): array {
    // Salva apenas o que foi digitado; vazio permanece vazio (= automático)
    $settings = hs_certificate_sanitize_course_settings( $settings );
    update_post_meta( $course_id, '_hs_certificate_settings', $settings );
    return $settings;
}

/**
 * Emissão automática habilitada para o curso?
 */
function hs_certificate_auto_issue_enabled( int $course_id ): bool {
    $settings = hs_certificate_course_settings( $course_id );
    return ( $settings['auto_issue'] ?? '1' ) !== '0';
}

/**
 * Build default certificate data for a student/course pair.
 */
function hs_certificate_default_details( int $user_id, int $course_id ): array {
    $student = get_userdata( $user_id );
    $course  = get_post( $course_id );
    $date    = get_user_meta( $user_id, 'hs_cert_date_' . $course_id, true );
    $date    = $date ?: date_i18n( 'd/m/Y', current_time( 'timestamp' ) );
    $settings = hs_certificate_course_settings( $course_id );

    $document = get_user_meta( $user_id, 'hs_document', true )
        ?: get_user_meta( $user_id, 'billing_cpf', true )
        ?: get_user_meta( $user_id, 'cpf', true );

    return [
        'template'         => $settings['template'],
        'student_name'     => $student ? $student->display_name : '',
        'student_email'    => $student ? $student->user_email : '',
        'student_document' => sanitize_text_field( (string) $document ),
        'course_title'     => $course ? get_the_title( $course ) : '',
        'course_workload'  => $settings['course_workload'],
        'completion_date'  => $date,
        'issue_date'       => date_i18n( 'd/m/Y', current_time( 'timestamp' ) ),
        'instructor_name'  => $settings['instructor_name'],
        'issuer_name'      => $settings['issuer_name'],
        'issuer_title'     => $settings['issuer_title'],
        'location'         => $settings['location'],
        'grade'            => '',
        'note'             => $settings['note'],
        'cert_id'          => hs_certificate_id( $user_id, $course_id ),
    ];
}

/**
 * Sanitize custom certificate data from dashboard forms.
 */
function hs_certificate_sanitize_details( array $details ): array {
    $clean = [];
    $text_fields = [
        'student_name',
        'student_email',
        'student_document',
        'course_title',
        'course_workload',
        'completion_date',
        'issue_date',
        'instructor_name',
        'issuer_name',
        'issuer_title',
        'location',
        'grade',
        'cert_id',
    ];

    foreach ( $text_fields as $field ) {
        $clean[ $field ] = sanitize_text_field( wp_unslash( $details[ $field ] ?? '' ) );
    }

    $template = sanitize_key( $details['template'] ?? 'modern' );
    $clean['template'] = in_array( $template, array_keys( hs_certificate_templates() ), true ) ? $template : 'modern';
    $clean['note'] = sanitize_textarea_field( wp_unslash( $details['note'] ?? '' ) );

    return $clean;
}

/**
 * Persist certificate details and keep the legacy completion date meta in sync.
 */
function hs_save_certificate_details( int $user_id, int $course_id, array $details = [] ): array {
    $defaults = hs_certificate_default_details( $user_id, $course_id );
    $details  = hs_certificate_sanitize_details( array_merge( $defaults, $details ) );

    if ( empty( $details['cert_id'] ) ) {
        $details['cert_id'] = hs_certificate_id( $user_id, $course_id );
    }
    if ( empty( $details['completion_date'] ) ) {
        $details['completion_date'] = $defaults['completion_date'];
    }

    update_user_meta( $user_id, 'hs_cert_date_' . $course_id, $details['completion_date'] );
    update_user_meta( $user_id, 'hs_cert_details_' . $course_id, $details );

    return $details;
}

/**
 * Get saved certificate details with backward-compatible defaults.
 */
function hs_certificate_details( int $user_id, int $course_id ): array {
    $stored = get_user_meta( $user_id, 'hs_cert_details_' . $course_id, true );
    if ( ! is_array( $stored ) ) {
        $stored = [];
    }

    // Campos vazios salvos não sobrescrevem os dados automáticos do aluno/curso
    $stored = array_filter( $stored, static function ( $value ) {
        return $value !== '' && $value !== null;
    } );

    return hs_certificate_sanitize_details( array_merge( hs_certificate_default_details( $user_id, $course_id ), $stored ) );
}

/**
 * Award a certificate to a user for completing a course.
 */
function hs_award_certificate( int $user_id, int $course_id, array $details = [] ) {
    $certs = array_map( 'intval', (array)( get_user_meta( $user_id, 'hs_certificates', true ) ?: [] ) );
    $already_awarded = in_array( $course_id, $certs, true );

    if ( ! $already_awarded ) {
        $certs[] = $course_id;
        update_user_meta( $user_id, 'hs_certificates', $certs );
    }

    $details = hs_save_certificate_details( $user_id, $course_id, $details );

    if ( $already_awarded ) {
        return false;
    }

    if ( function_exists( 'hs_award_xp' ) ) {
        hs_award_xp( $user_id, 500 );
    }

    // Notify student
    $student = get_userdata( $user_id );
    if ( $student ) {
        wp_mail(
            $student->user_email,
            sprintf( __( '[HubSkills] Certificado emitido: %s', 'hubskills-lms' ), $details['course_title'] ),
            sprintf( __( "Olá %s,\n\nParabéns por completar o curso \"%s\"!\n\nSeu certificado foi emitido com o código: %s\n\nVocê pode verificar seu certificado em: %s\n\n— HubSkills LMS", 'hubskills-lms' ),
                $details['student_name'],
                $details['course_title'],
                $details['cert_id'],
                home_url( '/verificar-certificado/' )
            )
        );
    }

    do_action( 'hs_certificate_awarded', $user_id, $course_id, $details );
    return true;
}

/**
 * Check if user has a certificate for a course.
 */
function hs_has_certificate( int $user_id, int $course_id ) {
    $certs = array_map( 'intval', (array)( get_user_meta( $user_id, 'hs_certificates', true ) ?: [] ) );
    return in_array( $course_id, $certs, true );
}

/**
 * Get a unique certificate ID.
 */
function hs_certificate_id( int $user_id, int $course_id ) {
    return 'HS-' . strtoupper( substr( md5( $user_id . $course_id . get_bloginfo('url') ), 0, 12 ) );
}

/**
 * Verify a certificate by its ID.
 * Returns array with user/course data or false.
 */
function hs_verify_certificate( string $cert_id ) {
    // Simple verification: iterate users with certificates
    // In production, you'd store cert IDs in a DB table
    global $wpdb;
    $users = get_users( [ 'meta_key' => 'hs_certificates', 'fields' => 'ID' ] );
    foreach ( $users as $uid ) {
        $certs = (array)( get_user_meta( $uid, 'hs_certificates', true ) ?: [] );
        foreach ( $certs as $cid ) {
            if ( hs_certificate_id( $uid, $cid ) === $cert_id ) {
                return [
                    'user'    => get_userdata( $uid ),
                    'course'  => get_post( $cid ),
                    'date'    => get_user_meta( $uid, 'hs_cert_date_' . $cid, true ),
                    'cert_id' => $cert_id,
                    'details' => hs_certificate_details( (int) $uid, (int) $cid ),
                ];
            }
        }
    }
    return false;
}
