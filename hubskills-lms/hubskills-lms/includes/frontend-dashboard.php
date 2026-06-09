<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function hs_lms_is_course_manager( int $user_id = 0 ): bool {
    $user_id = $user_id ?: get_current_user_id();
    if ( ! $user_id ) return false;

    return ( function_exists( 'hs_is_hs_admin' ) && hs_is_hs_admin( $user_id ) )
        || ( function_exists( 'hs_is_instructor' ) && hs_is_instructor( $user_id ) )
        || user_can( $user_id, 'hs_create_courses' );
}

function hs_lms_can_manage_course( int $course_id, int $user_id = 0 ): bool {
    $user_id = $user_id ?: get_current_user_id();
    if ( ! $user_id || get_post_type( $course_id ) !== 'hubskills_course' ) return false;
    if ( function_exists( 'hs_is_hs_admin' ) && hs_is_hs_admin( $user_id ) ) return true;
    if ( current_user_can( 'edit_post', $course_id ) ) return true;

    return (int) get_post_field( 'post_author', $course_id ) === $user_id
        || (int) get_post_meta( $course_id, '_hs_instructor_id', true ) === $user_id;
}

function hs_lms_user_can_access_course( int $user_id, int $course_id ): bool {
    if ( hs_lms_can_manage_course( $course_id, $user_id ) ) return true;
    return function_exists( 'hs_is_enrolled' ) && hs_is_enrolled( $user_id, $course_id );
}

function hs_lms_can_publish_courses( int $user_id = 0 ): bool {
    $user_id = $user_id ?: get_current_user_id();
    return $user_id && ( ( function_exists( 'hs_is_hs_admin' ) && hs_is_hs_admin( $user_id ) ) || user_can( $user_id, 'hs_publish_courses' ) );
}

function hs_lms_dashboard_courses( int $user_id = 0 ): array {
    $user_id = $user_id ?: get_current_user_id();
    if ( ! hs_lms_is_course_manager( $user_id ) ) return [];

    $args = [
        'post_type'      => 'hubskills_course',
        'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
        'posts_per_page' => -1,
        'orderby'        => 'modified',
        'order'          => 'DESC',
    ];

    if ( ! ( function_exists( 'hs_is_hs_admin' ) && hs_is_hs_admin( $user_id ) ) ) {
        $args['meta_query'] = [[ 'key' => '_hs_instructor_id', 'value' => $user_id ]];
    }

    $courses = get_posts( $args );
    if ( function_exists( 'hs_is_hs_admin' ) && hs_is_hs_admin( $user_id ) ) return $courses;

    $by_author = get_posts( [
        'post_type'      => 'hubskills_course',
        'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
        'posts_per_page' => -1,
        'author'         => $user_id,
    ] );

    $map = [];
    foreach ( array_merge( $courses, $by_author ) as $course ) $map[ $course->ID ] = $course;
    return array_values( $map );
}

function hs_lms_secure_upload_dir() {
    $upload = wp_upload_dir();
    if ( ! empty( $upload['error'] ) ) return new WP_Error( 'upload_dir', $upload['error'] );

    $dir = trailingslashit( $upload['basedir'] ) . 'hubskills-secure';
    if ( ! wp_mkdir_p( $dir ) ) return new WP_Error( 'upload_dir', __( 'Nao foi possivel criar a pasta protegida.', 'hubskills-lms' ) );

    if ( ! file_exists( trailingslashit( $dir ) . 'index.html' ) ) {
        file_put_contents( trailingslashit( $dir ) . 'index.html', '' );
    }
    if ( ! file_exists( trailingslashit( $dir ) . '.htaccess' ) ) {
        file_put_contents( trailingslashit( $dir ) . '.htaccess', "Options -Indexes\n<Files *>\nRequire all denied\nDeny from all\n</Files>\n" );
    }
    if ( ! file_exists( trailingslashit( $dir ) . 'web.config' ) ) {
        file_put_contents( trailingslashit( $dir ) . 'web.config', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><security><requestFiltering><fileExtensions allowUnlisted=\"false\" /></requestFiltering></security></system.webServer></configuration>\n" );
    }

    return $dir;
}

function hs_lms_allowed_upload_mimes(): array {
    return [
        'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'zip' => 'application/zip',
    ];
}

function hs_lms_normalize_asset( array $asset ): array {
    $path = (string) ( $asset['path'] ?? '' );
    if ( function_exists( 'wp_normalize_path' ) ) {
        $path = wp_normalize_path( $path );
    }

    return [
        'id'          => sanitize_key( $asset['id'] ?? '' ),
        'name'        => sanitize_text_field( $asset['name'] ?? '' ),
        'mime'        => sanitize_text_field( $asset['mime'] ?? '' ),
        'type'        => sanitize_key( $asset['type'] ?? 'resource' ),
        'path'        => $path,
        'size'        => (int) ( $asset['size'] ?? 0 ),
        'uploaded_by' => (int) ( $asset['uploaded_by'] ?? 0 ),
        'created_at'  => sanitize_text_field( $asset['created_at'] ?? '' ),
    ];
}

function hs_lms_public_asset( array $asset ): array {
    $asset = hs_lms_normalize_asset( $asset );
    unset( $asset['path'], $asset['uploaded_by'] );
    return $asset;
}

function hs_lms_secure_assets( int $course_id ): array {
    $assets = get_post_meta( $course_id, '_hs_secure_assets', true );
    if ( ! is_array( $assets ) ) return [];
    return array_values( array_filter( array_map( 'hs_lms_normalize_asset', $assets ), fn( $asset ) => $asset['id'] && $asset['path'] ) );
}

function hs_lms_find_secure_asset( int $course_id, string $asset_id ) {
    foreach ( hs_lms_secure_assets( $course_id ) as $asset ) {
        if ( $asset['id'] === sanitize_key( $asset_id ) ) return $asset;
    }
    return false;
}

function hs_lms_resolve_secure_asset_path( array $asset, string $secure_dir ) {
    $path = (string) ( $asset['path'] ?? '' );
    $real = $path ? realpath( $path ) : false;
    if ( $real ) return $real;

    $asset_id = sanitize_key( $asset['id'] ?? '' );
    if ( ! $asset_id ) return false;

    $matches = glob( trailingslashit( $secure_dir ) . '*_' . $asset_id . '.*' );
    if ( empty( $matches ) ) {
        $matches = glob( trailingslashit( $secure_dir ) . '*' . $asset_id . '*' );
    }

    foreach ( (array) $matches as $match ) {
        $real = realpath( $match );
        if ( $real && is_readable( $real ) ) return $real;
    }

    return false;
}

function hs_lms_temp_assets( int $user_id ): array {
    $assets = get_user_meta( $user_id, 'hs_lms_temp_assets', true );
    if ( ! is_array( $assets ) ) return [];
    return array_values( array_filter( array_map( 'hs_lms_normalize_asset', $assets ), fn( $asset ) => $asset['id'] && $asset['path'] ) );
}

function hs_lms_take_temp_assets( int $user_id, array $asset_ids ): array {
    $asset_ids = array_map( 'sanitize_key', $asset_ids );
    $taken = [];
    $keep = [];
    foreach ( hs_lms_temp_assets( $user_id ) as $asset ) {
        in_array( $asset['id'], $asset_ids, true ) ? $taken[] = $asset : $keep[] = $asset;
    }
    update_user_meta( $user_id, 'hs_lms_temp_assets', $keep );
    return $taken;
}

add_action( 'wp_ajax_hs_lms_upload_asset', 'hs_lms_ajax_upload_asset' );
function hs_lms_ajax_upload_asset() {
    hs_ajax_check();

    $user_id = get_current_user_id();
    if ( ! hs_lms_is_course_manager( $user_id ) ) wp_send_json_error( [ 'message' => __( 'Sem permissao para enviar arquivos.', 'hubskills-lms' ) ], 403 );

    $course_id = absint( $_POST['course_id'] ?? 0 );
    if ( $course_id && ! hs_lms_can_manage_course( $course_id, $user_id ) ) wp_send_json_error( [ 'message' => __( 'Sem permissao neste curso.', 'hubskills-lms' ) ], 403 );
    if ( empty( $_FILES['file'] ) || ! empty( $_FILES['file']['error'] ) ) wp_send_json_error( [ 'message' => __( 'Falha no upload.', 'hubskills-lms' ) ], 400 );

    $file = $_FILES['file'];
    $mb = defined( 'MB_IN_BYTES' ) ? MB_IN_BYTES : 1048576;
    if ( (int) $file['size'] > (int) apply_filters( 'hs_lms_max_upload_size', 500 * $mb ) ) wp_send_json_error( [ 'message' => __( 'Arquivo acima do limite.', 'hubskills-lms' ) ], 413 );

    $allowed = hs_lms_allowed_upload_mimes();
    $checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed );
    $ext = strtolower( $checked['ext'] ?? '' );
    $mime = $checked['type'] ?? '';
    if ( ! $ext || ! isset( $allowed[ $ext ] ) || ! $mime ) wp_send_json_error( [ 'message' => __( 'Tipo de arquivo nao permitido.', 'hubskills-lms' ) ], 415 );

    $dir = hs_lms_secure_upload_dir();
    if ( is_wp_error( $dir ) ) wp_send_json_error( [ 'message' => $dir->get_error_message() ], 500 );

    $asset_id = sanitize_key( wp_generate_uuid4() );
    $target = trailingslashit( $dir ) . gmdate( 'Ymd_His' ) . '_' . $asset_id . '.' . $ext;
    if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) wp_send_json_error( [ 'message' => __( 'Nao foi possivel salvar o arquivo.', 'hubskills-lms' ) ], 500 );

    $asset = [
        'id'          => $asset_id,
        'name'        => sanitize_file_name( $file['name'] ),
        'mime'        => $mime,
        'type'        => strpos( $mime, 'video/' ) === 0 ? 'video' : 'resource',
        'path'        => wp_normalize_path( $target ),
        'size'        => (int) $file['size'],
        'uploaded_by' => $user_id,
        'created_at'  => current_time( 'mysql' ),
    ];

    if ( $course_id ) {
        $assets = hs_lms_secure_assets( $course_id );
        $assets[] = $asset;
        update_post_meta( $course_id, '_hs_secure_assets', $assets );
    } else {
        $assets = hs_lms_temp_assets( $user_id );
        $assets[] = $asset;
        update_user_meta( $user_id, 'hs_lms_temp_assets', $assets );
    }

    if ( function_exists( 'hs_audit_log' ) ) hs_audit_log( $user_id, 'upload_asset', 'Arquivo protegido: ' . $asset['name'] );
    wp_send_json_success( hs_lms_public_asset( $asset ) );
}

function hs_lms_sanitize_curriculum( array $curriculum ): array {
    $clean = [];
    $auto_id = time();
    foreach ( $curriculum as $section ) {
        $lessons = [];
        foreach ( (array) ( $section['lessons'] ?? [] ) as $lesson ) {
            $provider = sanitize_key( $lesson['video_provider'] ?? 'none' );
            if ( ! in_array( $provider, [ 'none', 'secure', 'youtube', 'vimeo', 'external' ], true ) ) $provider = 'none';
            $lesson_id = absint( $lesson['id'] ?? 0 ) ?: ++$auto_id;
            $lessons[] = [
                'id'              => $lesson_id,
                'title'           => sanitize_text_field( $lesson['title'] ?? __( 'Nova aula', 'hubskills-lms' ) ),
                'duration'        => sanitize_text_field( $lesson['duration'] ?? '' ),
                'type'            => sanitize_key( $lesson['type'] ?? 'video' ),
                'description'     => sanitize_textarea_field( $lesson['description'] ?? '' ),
                'video_provider'  => $provider,
                'video_url'       => esc_url_raw( $lesson['video_url'] ?? '' ),
                'secure_asset_id' => sanitize_key( $lesson['secure_asset_id'] ?? '' ),
                'quiz_id'         => absint( $lesson['quiz_id'] ?? 0 ),
            ];
        }
        $clean[] = [
            'section' => sanitize_text_field( $section['section'] ?? __( 'Modulo', 'hubskills-lms' ) ),
            'lessons' => $lessons,
        ];
    }
    return $clean;
}

function hs_lms_sanitize_resources( array $resources ): array {
    $clean = [];
    foreach ( $resources as $resource ) {
        $name = sanitize_text_field( $resource['name'] ?? '' );
        if ( ! $name ) continue;
        $clean[] = [
            'name'     => $name,
            'asset_id' => sanitize_key( $resource['asset_id'] ?? '' ),
            'url'      => esc_url_raw( $resource['url'] ?? '' ),
            'mime'     => sanitize_text_field( $resource['mime'] ?? '' ),
        ];
    }
    return $clean;
}

add_action( 'wp_ajax_hs_lms_save_course', 'hs_lms_ajax_save_course' );
function hs_lms_ajax_save_course() {
    hs_ajax_check();

    $user_id = get_current_user_id();
    if ( ! hs_lms_is_course_manager( $user_id ) ) wp_send_json_error( [ 'message' => __( 'Sem permissao para criar cursos.', 'hubskills-lms' ) ], 403 );

    $course_id = absint( $_POST['course_id'] ?? 0 );
    if ( $course_id && ! hs_lms_can_manage_course( $course_id, $user_id ) ) wp_send_json_error( [ 'message' => __( 'Sem permissao para editar este curso.', 'hubskills-lms' ) ], 403 );

    $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
    if ( ! $title ) wp_send_json_error( [ 'message' => __( 'Informe o nome do curso.', 'hubskills-lms' ) ], 400 );

    $status = sanitize_key( $_POST['status'] ?? 'draft' );
    if ( ! in_array( $status, [ 'draft', 'pending', 'publish' ], true ) ) $status = 'draft';
    if ( $status === 'publish' && ! hs_lms_can_publish_courses( $user_id ) ) $status = 'pending';

    $post_data = [
        'post_type'    => 'hubskills_course',
        'post_title'   => $title,
        'post_content' => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
        'post_excerpt' => sanitize_textarea_field( wp_unslash( $_POST['excerpt'] ?? '' ) ),
        'post_status'  => $status,
    ];

    if ( $course_id ) {
        $post_data['ID'] = $course_id;
        $result = wp_update_post( $post_data, true );
    } else {
        $post_data['post_author'] = $user_id;
        $result = wp_insert_post( $post_data, true );
    }
    if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );

    $course_id = (int) $result;
    $instructor_id = $user_id;
    if ( function_exists( 'hs_is_hs_admin' ) && hs_is_hs_admin( $user_id ) && ! empty( $_POST['instructor_id'] ) ) $instructor_id = absint( $_POST['instructor_id'] );
    $instructor = get_userdata( $instructor_id );
    $instructor_name = $instructor ? $instructor->display_name : wp_get_current_user()->display_name;

    $curriculum = json_decode( wp_unslash( $_POST['curriculum_json'] ?? '[]' ), true );
    $resources = json_decode( wp_unslash( $_POST['resources_json'] ?? '[]' ), true );
    $asset_ids = json_decode( wp_unslash( $_POST['asset_ids_json'] ?? '[]' ), true );
    $curriculum = hs_lms_sanitize_curriculum( is_array( $curriculum ) ? $curriculum : [] );
    $resources = hs_lms_sanitize_resources( is_array( $resources ) ? $resources : [] );
    $asset_ids = is_array( $asset_ids ) ? array_map( 'sanitize_key', $asset_ids ) : [];

    $assets = [];
    foreach ( array_merge( hs_lms_secure_assets( $course_id ), hs_lms_take_temp_assets( $user_id, $asset_ids ) ) as $asset ) $assets[ $asset['id'] ] = $asset;

    update_post_meta( $course_id, '_hs_instructor', sanitize_text_field( $instructor_name ) );
    update_post_meta( $course_id, '_hs_instructor_id', $instructor_id );
    update_post_meta( $course_id, '_hs_level', sanitize_text_field( wp_unslash( $_POST['level'] ?? 'Iniciante' ) ) );
    update_post_meta( $course_id, '_hs_duration', sanitize_text_field( wp_unslash( $_POST['duration'] ?? '' ) ) );
    update_post_meta( $course_id, '_hs_price', sanitize_text_field( wp_unslash( $_POST['price'] ?? '' ) ) );
    update_post_meta( $course_id, '_hs_icon', sanitize_text_field( wp_unslash( $_POST['icon'] ?? '📚' ) ) );
    update_post_meta( $course_id, '_hs_curriculum', $curriculum );
    update_post_meta( $course_id, '_hs_resources', $resources );
    update_post_meta( $course_id, '_hs_secure_assets', array_values( $assets ) );
    update_post_meta( $course_id, '_hs_security_mode', 'protected_streaming' );

    $lessons_count = 0;
    foreach ( $curriculum as $section ) $lessons_count += count( $section['lessons'] ?? [] );
    update_post_meta( $course_id, '_hs_lessons_count', $lessons_count );

    if ( function_exists( 'hs_audit_log' ) ) hs_audit_log( $user_id, 'save_course_frontend', 'Curso salvo no dashboard: #' . $course_id );
    wp_send_json_success( [ 'message' => __( 'Curso salvo com sucesso.', 'hubskills-lms' ), 'course_id' => $course_id, 'permalink' => get_permalink( $course_id ) ] );
}

function hs_lms_course_students( int $course_id ): array {
    $students = [];
    foreach ( get_users( [ 'meta_key' => 'hs_enrollments', 'number' => 500, 'fields' => [ 'ID', 'display_name', 'user_email' ] ] ) as $user ) {
        if ( function_exists( 'hs_is_enrolled' ) && hs_is_enrolled( (int) $user->ID, $course_id ) ) $students[] = $user;
    }
    return $students;
}

function hs_lms_course_student_count( int $course_id ): int {
    if ( function_exists( 'hs_course_student_count' ) ) {
        return hs_course_student_count( $course_id );
    }

    return max(
        (int) get_post_meta( $course_id, '_hs_students', true ),
        (int) get_post_meta( $course_id, '_hs_student_count', true )
    );
}

function hs_lms_certificate_students(): array {
    return get_users( [
        'number'  => 500,
        'orderby' => 'display_name',
        'order'   => 'ASC',
        'fields'  => [ 'ID', 'display_name', 'user_email' ],
    ] );
}

add_action( 'wp_ajax_hs_lms_issue_certificate', 'hs_lms_ajax_issue_certificate' );
function hs_lms_ajax_issue_certificate() {
    hs_ajax_check();

    $issuer_id = get_current_user_id();
    $student_id = absint( $_POST['student_id'] ?? 0 );
    $course_id = absint( $_POST['course_id'] ?? 0 );
    if ( ! hs_lms_is_course_manager( $issuer_id ) || ! hs_lms_can_manage_course( $course_id, $issuer_id ) ) wp_send_json_error( [ 'message' => __( 'Sem permissao para emitir este certificado.', 'hubskills-lms' ) ], 403 );
    if ( ! $student_id || ! $course_id || ! function_exists( 'hs_award_certificate' ) ) wp_send_json_error( [ 'message' => __( 'Dados invalidos.', 'hubskills-lms' ) ], 400 );

    $enrollments = get_user_meta( $student_id, 'hs_enrollments', true );
    $enrollments = is_array( $enrollments ) ? $enrollments : [];
    $enrollments[ $course_id ] = array_merge( (array) ( $enrollments[ $course_id ] ?? [] ), [
        'status'       => 'completed',
        'progress'     => 100,
        'enrolled_at'  => $enrollments[ $course_id ]['enrolled_at'] ?? current_time( 'mysql' ),
        'completed_at' => current_time( 'mysql' ),
    ] );
    update_user_meta( $student_id, 'hs_enrollments', $enrollments );
    if ( function_exists( 'hs_sync_course_student_count' ) ) {
        hs_sync_course_student_count( $course_id );
    }

    $student = get_userdata( $student_id );
    $details = [
        'template'         => sanitize_key( $_POST['template'] ?? '' ),
        'student_name'     => sanitize_text_field( wp_unslash( $_POST['student_name'] ?? ( $student ? $student->display_name : '' ) ) ),
        'student_email'    => sanitize_email( wp_unslash( $_POST['student_email'] ?? ( $student ? $student->user_email : '' ) ) ),
        'student_document' => sanitize_text_field( wp_unslash( $_POST['student_document'] ?? '' ) ),
        'course_title'     => sanitize_text_field( wp_unslash( $_POST['course_title'] ?? get_the_title( $course_id ) ) ),
        'course_workload'  => sanitize_text_field( wp_unslash( $_POST['course_workload'] ?? get_post_meta( $course_id, '_hs_duration', true ) ) ),
        'completion_date'  => sanitize_text_field( wp_unslash( $_POST['completion_date'] ?? date_i18n( 'd/m/Y', current_time( 'timestamp' ) ) ) ),
        'issue_date'       => sanitize_text_field( wp_unslash( $_POST['issue_date'] ?? date_i18n( 'd/m/Y', current_time( 'timestamp' ) ) ) ),
        'instructor_name'  => sanitize_text_field( wp_unslash( $_POST['instructor_name'] ?? get_post_meta( $course_id, '_hs_instructor', true ) ) ),
        'issuer_name'      => sanitize_text_field( wp_unslash( $_POST['issuer_name'] ?? get_bloginfo( 'name' ) ) ),
        'issuer_title'     => sanitize_text_field( wp_unslash( $_POST['issuer_title'] ?? __( 'Diretor Academico', 'hubskills-lms' ) ) ),
        'location'         => sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) ),
        'grade'            => sanitize_text_field( wp_unslash( $_POST['grade'] ?? '' ) ),
        'note'             => sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ),
    ];

    hs_award_certificate( $student_id, $course_id, $details );
    $cert_id = hs_certificate_id( $student_id, $course_id );
    if ( function_exists( 'hs_audit_log' ) ) hs_audit_log( $issuer_id, 'issue_certificate', 'Certificado emitido: ' . $cert_id );
    wp_send_json_success( [
        'message'  => __( 'Certificado emitido.', 'hubskills-lms' ),
        'cert_id'  => $cert_id,
        'cert_url' => add_query_arg( 'course', $course_id, home_url( '/certificados/' ) ),
        'details'  => function_exists( 'hs_certificate_details' ) ? hs_certificate_details( $student_id, $course_id ) : [],
    ] );
}

add_action( 'wp_ajax_hs_lms_save_certificate_settings', 'hs_lms_ajax_save_certificate_settings' );
function hs_lms_ajax_save_certificate_settings() {
    hs_ajax_check();

    $issuer_id = get_current_user_id();
    $course_id = absint( $_POST['course_id'] ?? 0 );
    if ( ! $course_id || ! hs_lms_is_course_manager( $issuer_id ) || ! hs_lms_can_manage_course( $course_id, $issuer_id ) ) {
        wp_send_json_error( [ 'message' => __( 'Sem permissao para configurar este curso.', 'hubskills-lms' ) ], 403 );
    }
    if ( ! function_exists( 'hs_save_certificate_course_settings' ) ) {
        wp_send_json_error( [ 'message' => __( 'Modulo de certificados indisponivel.', 'hubskills-lms' ) ], 500 );
    }

    $settings = hs_save_certificate_course_settings( $course_id, [
        'template'        => sanitize_key( $_POST['template'] ?? '' ),
        'course_workload' => sanitize_text_field( wp_unslash( $_POST['course_workload'] ?? '' ) ),
        'instructor_name' => sanitize_text_field( wp_unslash( $_POST['instructor_name'] ?? '' ) ),
        'issuer_name'     => sanitize_text_field( wp_unslash( $_POST['issuer_name'] ?? '' ) ),
        'issuer_title'    => sanitize_text_field( wp_unslash( $_POST['issuer_title'] ?? '' ) ),
        'location'        => sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) ),
        'note'            => sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ),
    ] );

    if ( function_exists( 'hs_audit_log' ) ) hs_audit_log( $issuer_id, 'save_certificate_settings', 'Modelo de certificado salvo para curso #' . $course_id );
    wp_send_json_success( [ 'message' => __( 'Modelo automatico do curso salvo.', 'hubskills-lms' ), 'settings' => $settings ] );
}

function hs_lms_secure_asset_url( int $course_id, string $asset_id, bool $download = false ): string {
    $args = [ 'action' => 'hs_secure_asset', 'course_id' => $course_id, 'asset' => sanitize_key( $asset_id ) ];
    if ( $download ) $args['download'] = 1;
    return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-ajax.php' ) ), 'hs_secure_asset_' . $course_id . '_' . sanitize_key( $asset_id ) );
}

function hs_lms_asset_mime_type( array $asset, string $path = '' ): string {
    $name = strtolower( (string) ( $asset['name'] ?? basename( $path ) ) );
    if ( preg_match( '/\.(mp4|m4v)$/', $name ) ) return 'video/mp4';
    if ( preg_match( '/\.webm$/', $name ) ) return 'video/webm';
    if ( preg_match( '/\.mov$/', $name ) ) return 'video/quicktime';
    if ( preg_match( '/\.pdf$/', $name ) ) return 'application/pdf';

    $mime = sanitize_text_field( $asset['mime'] ?? '' );
    if ( in_array( $mime, hs_lms_allowed_upload_mimes(), true ) ) return $mime;

    if ( $path && function_exists( 'mime_content_type' ) ) {
        $detected = mime_content_type( $path );
        if ( is_string( $detected ) && $detected ) return $detected;
    }

    return 'application/octet-stream';
}

add_action( 'wp_ajax_hs_secure_asset', 'hs_lms_ajax_secure_asset' );
add_action( 'wp_ajax_nopriv_hs_secure_asset', 'hs_lms_ajax_secure_asset' );
function hs_lms_ajax_secure_asset() {
    $course_id = absint( $_GET['course_id'] ?? 0 );
    $asset_id = sanitize_key( $_GET['asset'] ?? '' );
    if ( ! is_user_logged_in() || ! $course_id || ! $asset_id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'hs_secure_asset_' . $course_id . '_' . $asset_id ) ) {
        status_header( 403 ); exit;
    }
    if ( ! hs_lms_user_can_access_course( get_current_user_id(), $course_id ) ) {
        status_header( 403 ); exit;
    }

    $asset = hs_lms_find_secure_asset( $course_id, $asset_id );
    $dir = hs_lms_secure_upload_dir();
    $base = is_wp_error( $dir ) ? false : realpath( $dir );
    $path = $asset && ! is_wp_error( $dir ) ? hs_lms_resolve_secure_asset_path( $asset, $dir ) : false;
    if ( ! $asset || ! $base || ! $path || strpos( $path, $base ) !== 0 || ! is_readable( $path ) ) {
        status_header( 404 ); exit;
    }

    $mime = hs_lms_asset_mime_type( $asset, $path );
    hs_lms_stream_file_range( $path, $mime, (string) ( $asset['name'] ?: basename( $path ) ), ! empty( $_GET['download'] ) );
}

function hs_lms_youtube_embed_url( string $url ): string {
    $parts = wp_parse_url( $url );
    $host = strtolower( preg_replace( '/^www\./', '', $parts['host'] ?? '' ) );
    $id = '';
    if ( $host === 'youtu.be' ) $id = trim( $parts['path'] ?? '', '/' );
    if ( in_array( $host, [ 'youtube.com', 'm.youtube.com', 'youtube-nocookie.com' ], true ) ) {
        parse_str( $parts['query'] ?? '', $query );
        $id = sanitize_text_field( $query['v'] ?? '' );
        if ( ! $id && preg_match( '#/(embed|shorts)/([^/?]+)#', $parts['path'] ?? '', $m ) ) $id = $m[2];
    }
    return $id ? 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $id ) . '?rel=0&modestbranding=1' : '';
}

function hs_lms_vimeo_embed_url( string $url ): string {
    preg_match( '/(\d+)/', wp_parse_url( $url, PHP_URL_PATH ) ?: '', $m );
    return ! empty( $m[1] ) ? 'https://player.vimeo.com/video/' . rawurlencode( $m[1] ) : '';
}

function hs_lms_render_lesson_media( int $course_id, array $lesson ): string {
    $provider = sanitize_key( $lesson['video_provider'] ?? '' );
    $asset_id = sanitize_key( $lesson['secure_asset_id'] ?? '' );
    $url = esc_url_raw( $lesson['video_url'] ?? '' );
    // Arquivos locais em uploads passam pelo proxy protegido (matrícula + assinatura).
    // A URL original continua sendo usada para detectar provider/extensão.
    $stream_url = ( $url && function_exists( 'hs_lms_protected_media_url' ) )
        ? hs_lms_protected_media_url( $course_id, $url )
        : $url;

    // Aulas legadas (JSON antigo) não têm video_provider — inferir pela URL
    if ( ( $provider === '' || $provider === 'none' ) && $url ) {
        $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        if ( strpos( $host, 'youtu' ) !== false ) $provider = 'youtube';
        elseif ( strpos( $host, 'vimeo' ) !== false ) $provider = 'vimeo';
        else $provider = 'external';
    }
    $title = sanitize_text_field( $lesson['title'] ?? get_the_title( $course_id ) );
    $watermark = trim( wp_get_current_user()->display_name . ' #' . get_current_user_id() );
    $asset = $asset_id ? hs_lms_find_secure_asset( $course_id, $asset_id ) : false;
    $asset_mime = is_array( $asset ) ? hs_lms_asset_mime_type( $asset ) : 'video/mp4';
    $url_mime = preg_match( '/\.webm(\?.*)?$/i', $url ) ? 'video/webm' : ( preg_match( '/\.mov(\?.*)?$/i', $url ) ? 'video/quicktime' : 'video/mp4' );

    ob_start();
    if ( $provider === 'secure' && $asset_id ) : ?>
        <div class="hs-secure-player" data-watermark="<?php echo esc_attr( $watermark ); ?>">
            <?php if ( ! $asset ) : ?>
                <div class="video-placeholder"><p><?php _e( 'Video protegido nao encontrado.', 'hubskills-lms' ); ?></p></div>
            <?php else : ?>
            <video id="courseVideo" class="hs-native-video" controls controlsList="nodownload noremoteplayback" disablePictureInPicture preload="metadata" playsinline>
                <source src="<?php echo esc_url( hs_lms_secure_asset_url( $course_id, $asset_id ) ); ?>" type="<?php echo esc_attr( $asset_mime ); ?>">
                <p><?php _e( 'Seu navegador nao conseguiu abrir este video.', 'hubskills-lms' ); ?></p>
            </video>
            <a class="hs-video-open-link" href="<?php echo esc_url( hs_lms_secure_asset_url( $course_id, $asset_id ) ); ?>" target="_blank" rel="noopener"><?php _e( 'Abrir video em nova aba', 'hubskills-lms' ); ?></a>
            <span class="hs-player-watermark"><?php echo esc_html( $watermark ); ?></span>
            <?php endif; ?>
        </div>
    <?php elseif ( $provider === 'youtube' && ( $embed = hs_lms_youtube_embed_url( $url ) ) ) : ?>
        <div class="hs-embed-player" data-watermark="<?php echo esc_attr( $watermark ); ?>"><iframe src="<?php echo esc_url( $embed ); ?>" title="<?php echo esc_attr( $title ); ?>" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe><span class="hs-player-watermark"><?php echo esc_html( $watermark ); ?></span></div>
    <?php elseif ( $provider === 'vimeo' && ( $embed = hs_lms_vimeo_embed_url( $url ) ) ) : ?>
        <div class="hs-embed-player" data-watermark="<?php echo esc_attr( $watermark ); ?>"><iframe src="<?php echo esc_url( $embed ); ?>" title="<?php echo esc_attr( $title ); ?>" loading="lazy" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe><span class="hs-player-watermark"><?php echo esc_html( $watermark ); ?></span></div>
    <?php elseif ( $url && preg_match( '/\.(mp4|m4v|webm|mov)(\?.*)?$/i', $url ) ) : ?>
        <div class="hs-secure-player" data-watermark="<?php echo esc_attr( $watermark ); ?>">
            <video id="courseVideo" class="hs-native-video" controls controlsList="nodownload noremoteplayback" disablePictureInPicture preload="metadata" playsinline>
                <source src="<?php echo esc_url( $stream_url ); ?>" type="<?php echo esc_attr( $url_mime ); ?>">
                <p><?php _e( 'Seu navegador nao conseguiu abrir este video.', 'hubskills-lms' ); ?></p>
            </video>
            <a class="hs-video-open-link" href="<?php echo esc_url( $stream_url ); ?>" target="_blank" rel="noopener"><?php _e( 'Abrir video em nova aba', 'hubskills-lms' ); ?></a>
            <span class="hs-player-watermark"><?php echo esc_html( $watermark ); ?></span>
        </div>
    <?php endif;
    return trim( ob_get_clean() );
}

function hs_lms_get_resource_url( int $course_id, array $resource ): string {
    $asset_id = sanitize_key( $resource['asset_id'] ?? '' );
    if ( $asset_id ) return hs_lms_secure_asset_url( $course_id, $asset_id, true );

    $url = esc_url_raw( $resource['url'] ?? '' );
    if ( $url && function_exists( 'hs_lms_protected_media_url' ) ) {
        $url = hs_lms_protected_media_url( $course_id, $url, true );
    }
    return $url;
}

function hs_lms_render_dashboard_tools(): string {
    if ( ! is_user_logged_in() ) return '';
    $user_id = get_current_user_id();
    $is_admin = function_exists( 'hs_is_hs_admin' ) && hs_is_hs_admin( $user_id );
    $can_manage = hs_lms_is_course_manager( $user_id );
    $courses = hs_lms_dashboard_courses( $user_id );
    $certs = array_map( 'intval', (array) ( get_user_meta( $user_id, 'hs_certificates', true ) ?: [] ) );

    ob_start(); ?>
    <section class="hs-lms-dashboard-tools" data-hs-dashboard-tools>
        <div class="hs-dashboard-toolbar">
            <div><span class="hs-kicker"><?php _e( 'Area LMS', 'hubskills-lms' ); ?></span><h2><?php _e( 'Painel operacional', 'hubskills-lms' ); ?></h2></div>
            <span class="hs-role-pill"><i class="fas fa-user-shield"></i><?php echo esc_html( $is_admin ? __( 'Admin: acesso total', 'hubskills-lms' ) : ( $can_manage ? __( 'Instrutor', 'hubskills-lms' ) : __( 'Aluno', 'hubskills-lms' ) ) ); ?></span>
        </div>

        <?php if ( $can_manage ) : ?>
        <div class="hs-manager-tabs" role="tablist">
            <button type="button" class="active" data-hs-tab="courses"><i class="fas fa-layer-group"></i><?php _e( 'Cursos', 'hubskills-lms' ); ?></button>
            <button type="button" data-hs-tab="builder"><i class="fas fa-plus-circle"></i><?php _e( 'Novo curso', 'hubskills-lms' ); ?></button>
            <button type="button" data-hs-tab="certificates"><i class="fas fa-certificate"></i><?php _e( 'Certificacao', 'hubskills-lms' ); ?></button>
            <button type="button" data-hs-tab="security"><i class="fas fa-lock"></i><?php _e( 'Protecao', 'hubskills-lms' ); ?></button>
        </div>

        <div class="hs-tab-panel active" data-hs-panel="courses">
            <div class="hs-dashboard-card">
                <div class="hs-card-head"><div><span class="hs-kicker"><?php _e( 'Gestao', 'hubskills-lms' ); ?></span><h3><?php _e( 'Cursos cadastrados', 'hubskills-lms' ); ?></h3></div><button type="button" class="hs-link-btn" data-hs-open-builder><?php _e( 'Criar curso', 'hubskills-lms' ); ?></button></div>
                <?php if ( empty( $courses ) ) : ?>
                    <div class="hs-empty-inline"><?php _e( 'Nenhum curso encontrado.', 'hubskills-lms' ); ?></div>
                <?php else : ?>
                    <div class="hs-course-admin-list">
                    <?php foreach ( $courses as $course ) : $status = get_post_status( $course ); ?>
                        <article class="hs-course-admin-row">
                            <div><h4><?php echo esc_html( $course->post_title ); ?></h4><p><?php echo esc_html( get_post_meta( $course->ID, '_hs_level', true ) ?: __( 'Sem nivel definido', 'hubskills-lms' ) ); ?></p></div>
                            <span class="hs-status-badge hs-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status ); ?></span>
                            <span><i class="fas fa-users"></i><?php echo (int) hs_lms_course_student_count( $course->ID ); ?></span>
                            <span><i class="fas fa-play-circle"></i><?php echo (int) get_post_meta( $course->ID, '_hs_lessons_count', true ); ?></span>
                            <a class="hs-row-action" href="<?php echo esc_url( get_permalink( $course ) ); ?>"><?php _e( 'Ver', 'hubskills-lms' ); ?></a>
                        </article>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="hs-tab-panel" data-hs-panel="builder">
            <form class="hs-course-wizard" data-hs-course-wizard>
                <input type="hidden" name="course_id" value="">
                <div class="hs-wizard-steps">
                    <button type="button" class="active" data-step-dot="0">1. <?php _e( 'Base', 'hubskills-lms' ); ?></button>
                    <button type="button" data-step-dot="1">2. <?php _e( 'Aulas', 'hubskills-lms' ); ?></button>
                    <button type="button" data-step-dot="2">3. <?php _e( 'Materiais', 'hubskills-lms' ); ?></button>
                    <button type="button" data-step-dot="3">4. <?php _e( 'Publicar', 'hubskills-lms' ); ?></button>
                </div>
                <div class="hs-wizard-step active" data-step="0">
                    <div class="hs-field-grid">
                        <label><?php _e( 'Nome do curso', 'hubskills-lms' ); ?><input type="text" name="title" required></label>
                        <label><?php _e( 'Nivel', 'hubskills-lms' ); ?><select name="level"><option>Iniciante</option><option>Intermediario</option><option>Avancado</option></select></label>
                        <label><?php _e( 'Duracao', 'hubskills-lms' ); ?><input type="text" name="duration" placeholder="12h"></label>
                        <label><?php _e( 'Preco', 'hubskills-lms' ); ?><input type="text" name="price" placeholder="Gratis ou R$ 197,00"></label>
                        <label><?php _e( 'Icone', 'hubskills-lms' ); ?><input type="text" name="icon" value="📚" maxlength="8"></label>
                        <label><?php _e( 'Status', 'hubskills-lms' ); ?><select name="status"><option value="draft"><?php _e( 'Rascunho', 'hubskills-lms' ); ?></option><option value="pending"><?php _e( 'Revisao', 'hubskills-lms' ); ?></option><option value="publish"><?php _e( 'Publicado', 'hubskills-lms' ); ?></option></select></label>
                        <?php if ( $is_admin ) : $users = get_users( [ 'role__in' => [ 'administrator', 'hs_admin', 'hs_instructor' ], 'fields' => [ 'ID', 'display_name', 'user_email' ] ] ); ?>
                            <label><?php _e( 'Instrutor responsavel', 'hubskills-lms' ); ?><select name="instructor_id"><?php foreach ( $users as $u ) : ?><option value="<?php echo (int) $u->ID; ?>" <?php selected( $u->ID, $user_id ); ?>><?php echo esc_html( $u->display_name . ' (' . $u->user_email . ')' ); ?></option><?php endforeach; ?></select></label>
                        <?php endif; ?>
                        <label class="hs-field-wide"><?php _e( 'Resumo', 'hubskills-lms' ); ?><textarea name="excerpt" rows="3"></textarea></label>
                        <label class="hs-field-wide"><?php _e( 'Descricao completa', 'hubskills-lms' ); ?><textarea name="description" rows="6"></textarea></label>
                    </div>
                </div>
                <div class="hs-wizard-step" data-step="1">
                    <div class="hs-builder-head"><div><h3><?php _e( 'Curriculo do curso', 'hubskills-lms' ); ?></h3></div><button type="button" class="hs-link-btn" data-add-section><i class="fas fa-plus"></i><?php _e( 'Modulo', 'hubskills-lms' ); ?></button></div>
                    <div class="hs-course-sections" data-course-sections></div>
                </div>
                <div class="hs-wizard-step" data-step="2">
                    <div class="hs-dashboard-card inner"><div class="hs-card-head"><div><span class="hs-kicker"><?php _e( 'Arquivos', 'hubskills-lms' ); ?></span><h3><?php _e( 'PDFs e materiais', 'hubskills-lms' ); ?></h3></div><label class="hs-upload-btn"><i class="fas fa-cloud-upload-alt"></i><?php _e( 'Enviar material', 'hubskills-lms' ); ?><input type="file" multiple data-resource-upload accept=".pdf,.doc,.docx,.ppt,.pptx,.zip"></label></div><div class="hs-resource-list" data-resource-list></div></div>
                </div>
                <div class="hs-wizard-step" data-step="3">
                    <div class="hs-review-grid"><div class="hs-review-item"><i class="fas fa-lock"></i><span><?php _e( 'Arquivos protegidos por login e matricula.', 'hubskills-lms' ); ?></span></div><div class="hs-review-item"><i class="fas fa-video"></i><span><?php _e( 'Upload protegido, YouTube, Vimeo e URL externa.', 'hubskills-lms' ); ?></span></div><div class="hs-review-item"><i class="fas fa-certificate"></i><span><?php _e( 'Certificados por conclusao ou emissao manual.', 'hubskills-lms' ); ?></span></div></div>
                </div>
                <div class="hs-form-notice" data-form-notice hidden></div>
                <div class="hs-wizard-actions"><button type="button" class="hs-ghost-btn" data-wizard-prev><?php _e( 'Voltar', 'hubskills-lms' ); ?></button><button type="button" class="btn btn-gradient" data-wizard-next><?php _e( 'Avancar', 'hubskills-lms' ); ?></button><button type="submit" class="btn btn-gradient" data-save-course hidden><i class="fas fa-save"></i><?php _e( 'Salvar curso', 'hubskills-lms' ); ?></button></div>
            </form>
        </div>

        <div class="hs-tab-panel" data-hs-panel="certificates">
            <div class="hs-dashboard-card">
                <div class="hs-card-head">
                    <div><span class="hs-kicker"><?php _e( 'Emissao', 'hubskills-lms' ); ?></span><h3><?php _e( 'Criador de certificados', 'hubskills-lms' ); ?></h3></div>
                </div>
                <form class="hs-cert-form hs-cert-form--advanced" data-hs-cert-form>
                    <div class="hs-cert-builder">
                        <div class="hs-cert-fields">
                            <div class="hs-field-grid">
                                <label><?php _e( 'Curso', 'hubskills-lms' ); ?>
                                    <select name="course_id" required>
                                        <option value=""><?php _e( 'Selecione', 'hubskills-lms' ); ?></option>
                                        <?php foreach ( $courses as $course ) : $cert_settings = function_exists( 'hs_certificate_course_settings' ) ? hs_certificate_course_settings( $course->ID ) : []; ?>
                                            <option value="<?php echo (int) $course->ID; ?>"
                                                    data-title="<?php echo esc_attr( $course->post_title ); ?>"
                                                    data-template="<?php echo esc_attr( $cert_settings['template'] ?? 'modern' ); ?>"
                                                    data-workload="<?php echo esc_attr( $cert_settings['course_workload'] ?? get_post_meta( $course->ID, '_hs_duration', true ) ); ?>"
                                                    data-instructor="<?php echo esc_attr( $cert_settings['instructor_name'] ?? get_post_meta( $course->ID, '_hs_instructor', true ) ); ?>"
                                                    data-issuer="<?php echo esc_attr( $cert_settings['issuer_name'] ?? get_bloginfo( 'name' ) ); ?>"
                                                    data-issuer-title="<?php echo esc_attr( $cert_settings['issuer_title'] ?? __( 'Diretor Academico', 'hubskills-lms' ) ); ?>"
                                                    data-location="<?php echo esc_attr( $cert_settings['location'] ?? '' ); ?>"
                                                    data-note="<?php echo esc_attr( $cert_settings['note'] ?? '' ); ?>">
                                                <?php echo esc_html( $course->post_title ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label><?php _e( 'Aluno cadastrado', 'hubskills-lms' ); ?>
                                    <select name="student_id" required>
                                        <option value=""><?php _e( 'Selecione', 'hubskills-lms' ); ?></option>
                                        <?php foreach ( hs_lms_certificate_students() as $student ) : ?>
                                            <option value="<?php echo (int) $student->ID; ?>"
                                                    data-course="all"
                                                    data-name="<?php echo esc_attr( $student->display_name ); ?>"
                                                    data-email="<?php echo esc_attr( $student->user_email ); ?>">
                                                <?php echo esc_html( $student->display_name . ' (' . $student->user_email . ')' ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label><?php _e( 'Modelo do curso', 'hubskills-lms' ); ?>
                                    <select name="template">
                                        <?php foreach ( hs_certificate_templates() as $template_slug => $template_label ) : ?>
                                            <option value="<?php echo esc_attr( $template_slug ); ?>"><?php echo esc_html( $template_label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label><?php _e( 'Nome no certificado', 'hubskills-lms' ); ?><input type="text" name="student_name" required></label>
                                <label><?php _e( 'E-mail do aluno', 'hubskills-lms' ); ?><input type="email" name="student_email"></label>
                                <label><?php _e( 'Documento/ID do aluno', 'hubskills-lms' ); ?><input type="text" name="student_document" placeholder="CPF, RG ou matricula"></label>
                                <label><?php _e( 'Titulo do curso', 'hubskills-lms' ); ?><input type="text" name="course_title" required></label>
                                <label><?php _e( 'Carga horaria', 'hubskills-lms' ); ?><input type="text" name="course_workload" placeholder="Ex: 20h"></label>
                                <label><?php _e( 'Data de conclusao', 'hubskills-lms' ); ?><input type="text" name="completion_date" value="<?php echo esc_attr( date_i18n( 'd/m/Y', current_time( 'timestamp' ) ) ); ?>"></label>
                                <label><?php _e( 'Data de emissao', 'hubskills-lms' ); ?><input type="text" name="issue_date" value="<?php echo esc_attr( date_i18n( 'd/m/Y', current_time( 'timestamp' ) ) ); ?>"></label>
                                <label><?php _e( 'Instrutor/responsavel', 'hubskills-lms' ); ?><input type="text" name="instructor_name"></label>
                                <label><?php _e( 'Assinatura institucional', 'hubskills-lms' ); ?><input type="text" name="issuer_name" value="<?php echo esc_attr( get_bloginfo( 'name' ) ?: 'HubSkills' ); ?>"></label>
                                <label><?php _e( 'Cargo da assinatura', 'hubskills-lms' ); ?><input type="text" name="issuer_title" value="<?php esc_attr_e( 'Diretor Academico', 'hubskills-lms' ); ?>"></label>
                                <label><?php _e( 'Local', 'hubskills-lms' ); ?><input type="text" name="location" placeholder="Cidade/UF"></label>
                                <label><?php _e( 'Nota ou aproveitamento', 'hubskills-lms' ); ?><input type="text" name="grade" placeholder="Ex: 92%"></label>
                                <label class="hs-field-wide"><?php _e( 'Observacao do certificado', 'hubskills-lms' ); ?><textarea name="note" rows="3" placeholder="Texto complementar opcional"></textarea></label>
                            </div>
                            <div class="hs-cert-actions">
                                <button type="submit" class="btn btn-gradient"><i class="fas fa-award"></i><?php _e( 'Emitir certificado', 'hubskills-lms' ); ?></button>
                                <button type="button" class="hs-ghost-btn" data-save-cert-settings><i class="fas fa-save"></i><?php _e( 'Salvar modelo do curso', 'hubskills-lms' ); ?></button>
                                <button type="button" class="hs-ghost-btn" data-certificate-print data-cert-target="[data-cert-preview]"><i class="fas fa-print"></i><?php _e( 'Imprimir / PDF', 'hubskills-lms' ); ?></button>
                                <button type="button" class="hs-ghost-btn" data-certificate-download data-cert-target="[data-cert-preview]"><i class="fas fa-image"></i><?php _e( 'Baixar imagem', 'hubskills-lms' ); ?></button>
                            </div>
                            <div class="hs-form-notice" data-cert-notice hidden></div>
                        </div>
                        <div class="hs-cert-preview-shell">
                            <div class="hs-cert-preview" data-cert-preview data-cert-id="">
                                <div class="hs-cert-preview-border"></div>
                                <div class="hs-cert-preview-head">
                                    <div><span>Hub</span>Skills</div>
                                    <strong><?php _e( 'Certificado', 'hubskills-lms' ); ?></strong>
                                </div>
                                <p><?php _e( 'Certificamos que', 'hubskills-lms' ); ?></p>
                                <h4 data-preview-student><?php _e( 'Nome do aluno', 'hubskills-lms' ); ?></h4>
                                <p><?php _e( 'concluiu com exito o curso', 'hubskills-lms' ); ?></p>
                                <h5 data-preview-course><?php _e( 'Nome do curso', 'hubskills-lms' ); ?></h5>
                                <div class="hs-cert-preview-meta">
                                    <span><?php _e( 'Conclusao', 'hubskills-lms' ); ?>: <b data-preview-completion><?php echo esc_html( date_i18n( 'd/m/Y', current_time( 'timestamp' ) ) ); ?></b></span>
                                    <span><?php _e( 'Carga horaria', 'hubskills-lms' ); ?>: <b data-preview-workload>-</b></span>
                                    <span><?php _e( 'Aproveitamento', 'hubskills-lms' ); ?>: <b data-preview-grade>-</b></span>
                                </div>
                                <p class="hs-cert-preview-note" data-preview-note></p>
                                <div class="hs-cert-preview-foot">
                                    <div><i></i><span data-preview-instructor><?php _e( 'Instrutor do Curso', 'hubskills-lms' ); ?></span><small><?php _e( 'Instrutor', 'hubskills-lms' ); ?></small></div>
                                    <div><i></i><span data-preview-issuer><?php echo esc_html( get_bloginfo( 'name' ) ?: 'HubSkills' ); ?></span><small data-preview-issuer-title><?php _e( 'Diretor Academico', 'hubskills-lms' ); ?></small></div>
                                </div>
                                <div class="hs-cert-preview-id"><?php _e( 'ID apos emissao', 'hubskills-lms' ); ?></div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="hs-tab-panel" data-hs-panel="security"><div class="hs-security-grid"><div class="hs-security-item"><i class="fas fa-user-lock"></i><strong><?php _e( 'Acesso autenticado', 'hubskills-lms' ); ?></strong><span><?php _e( 'Aulas exigem login e permissao no curso.', 'hubskills-lms' ); ?></span></div><div class="hs-security-item"><i class="fas fa-folder-minus"></i><strong><?php _e( 'Pasta bloqueada', 'hubskills-lms' ); ?></strong><span><?php _e( 'Uploads nao ficam expostos por link publico direto.', 'hubskills-lms' ); ?></span></div><div class="hs-security-item"><i class="fas fa-fingerprint"></i><strong><?php _e( 'Marca d agua', 'hubskills-lms' ); ?></strong><span><?php _e( 'O player identifica o usuario durante a reproducao.', 'hubskills-lms' ); ?></span></div></div></div>
        <?php endif; ?>

        <div class="hs-dashboard-card hs-certs-summary"><div class="hs-card-head"><div><span class="hs-kicker"><?php _e( 'Certificados', 'hubskills-lms' ); ?></span><h3><?php _e( 'Meus certificados', 'hubskills-lms' ); ?></h3></div><a class="hs-link-btn" href="<?php echo esc_url( home_url( '/certificados/' ) ); ?>"><?php _e( 'Abrir', 'hubskills-lms' ); ?></a></div>
            <?php if ( empty( $certs ) ) : ?><div class="hs-empty-inline"><?php _e( 'Nenhum certificado emitido ainda.', 'hubskills-lms' ); ?></div><?php else : ?><div class="hs-cert-mini-grid"><?php foreach ( get_posts( [ 'post_type' => 'hubskills_course', 'post__in' => $certs, 'posts_per_page' => 3 ] ) as $course ) : ?><a class="hs-cert-mini" href="<?php echo esc_url( add_query_arg( 'course', $course->ID, home_url( '/certificados/' ) ) ); ?>"><i class="fas fa-certificate"></i><span><?php echo esc_html( $course->post_title ); ?></span><small><?php echo esc_html( get_user_meta( $user_id, 'hs_cert_date_' . $course->ID, true ) ); ?></small></a><?php endforeach; ?></div><?php endif; ?>
        </div>
    </section>
    <?php return ob_get_clean();
}
