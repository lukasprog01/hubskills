<?php
/**
 * HubSkills LMS — Mídia protegida
 *
 * Proxy de streaming para arquivos locais (wp-content/uploads) referenciados
 * em aulas e materiais. URLs diretas são trocadas por URLs assinadas com
 * expiração; o endpoint valida login, assinatura, prazo e matrícula antes
 * de servir o arquivo com suporte a HTTP Range.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Streaming com suporte a Range (compartilhado) ─────────
function hs_lms_stream_file_range( string $path, string $mime, string $name, bool $download = false ) {
    $size = filesize( $path );
    $start = 0;
    $end = $size - 1;
    $is_range = false;

    if ( ! empty( $_SERVER['HTTP_RANGE'] ) && preg_match( '/bytes=(\d*)-(\d*)/', sanitize_text_field( wp_unslash( $_SERVER['HTTP_RANGE'] ) ), $m ) ) {
        if ( $m[1] === '' && $m[2] !== '' ) {
            $suffix = min( (int) $m[2], $size );
            $start = max( 0, $size - $suffix );
        } elseif ( $m[1] !== '' ) {
            $start = max( 0, (int) $m[1] );
        }
        if ( $m[2] !== '' && $m[1] !== '' ) $end = min( $end, (int) $m[2] );
        if ( $start > $end || $start >= $size ) {
            status_header( 416 );
            header( "Content-Range: bytes */{$size}" );
            exit;
        }
        $is_range = true;
    }

    $length = $end - $start + 1;
    @set_time_limit( 0 );
    @ini_set( 'zlib.output_compression', 'Off' );
    while ( ob_get_level() > 0 ) {
        if ( ! @ob_end_clean() ) break;
    }

    status_header( $is_range ? 206 : 200 );
    nocache_headers();
    header( 'Content-Type: ' . $mime );
    header( 'Accept-Ranges: bytes' );
    if ( $is_range ) header( "Content-Range: bytes {$start}-{$end}/{$size}" );
    header( 'Content-Length: ' . $length );
    header( 'Content-Transfer-Encoding: binary' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'X-Accel-Buffering: no' );
    header( 'X-Robots-Tag: noindex, nofollow' );
    header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
    header( 'Content-Disposition: ' . ( $download ? 'attachment' : 'inline' ) . '; filename="' . sanitize_file_name( $name ?: basename( $path ) ) . '"' );

    $handle = fopen( $path, 'rb' );
    if ( ! $handle ) {
        status_header( 500 ); exit;
    }
    fseek( $handle, $start );
    $sent = 0;
    while ( ! feof( $handle ) && $sent < $length ) {
        $chunk = min( 1024 * 1024, $length - $sent );
        echo fread( $handle, $chunk );
        $sent += $chunk;
        @flush();
    }
    fclose( $handle );
    exit;
}

// ── Detecção de arquivo local em uploads ──────────────────
/**
 * Retorna o caminho relativo dentro de uploads se a URL apontar para um
 * arquivo local do próprio site, ou false caso contrário.
 */
function hs_lms_local_upload_relpath( string $url ) {
    $uploads = wp_get_upload_dir();
    if ( ! empty( $uploads['error'] ) ) return false;

    $base_url = trailingslashit( set_url_scheme( $uploads['baseurl'], 'http' ) );
    $check    = set_url_scheme( $url, 'http' );
    if ( strpos( $check, $base_url ) !== 0 ) return false;

    $rel = substr( $check, strlen( $base_url ) );
    $rel = rawurldecode( strtok( $rel, '?' ) );
    if ( $rel === '' || strpos( $rel, '..' ) !== false ) return false;

    return $rel;
}

function hs_lms_media_token( int $course_id, string $relpath, int $expires ): string {
    return hash_hmac( 'sha256', $course_id . '|' . $relpath . '|' . $expires, wp_salt( 'auth' ) );
}

/**
 * Troca URLs de mídia local por URLs assinadas servidas pelo proxy
 * protegido. URLs externas (YouTube, Vimeo, CDN de terceiros) passam direto.
 */
function hs_lms_protected_media_url( int $course_id, string $url, bool $download = false ): string {
    $relpath = hs_lms_local_upload_relpath( $url );
    if ( false === $relpath ) return $url;

    $expires = time() + (int) apply_filters( 'hs_lms_media_url_ttl', 6 * HOUR_IN_SECONDS );
    $args = [
        'action'    => 'hs_protected_media',
        'course_id' => $course_id,
        'file'      => rtrim( strtr( base64_encode( $relpath ), '+/', '-_' ), '=' ),
        'exp'       => $expires,
        'sig'       => hs_lms_media_token( $course_id, $relpath, $expires ),
    ];
    if ( $download ) $args['download'] = 1;

    return add_query_arg( $args, admin_url( 'admin-ajax.php' ) );
}

// ── Tipos permitidos no proxy ─────────────────────────────
function hs_lms_protected_media_mimes(): array {
    return apply_filters( 'hs_lms_protected_media_mimes', [
        'mp4'  => 'video/mp4',
        'm4v'  => 'video/mp4',
        'webm' => 'video/webm',
        'mov'  => 'video/quicktime',
        'mp3'  => 'audio/mpeg',
        'm4a'  => 'audio/mp4',
        'pdf'  => 'application/pdf',
        'zip'  => 'application/zip',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ] );
}

// ── Endpoint do proxy ─────────────────────────────────────
add_action( 'wp_ajax_hs_protected_media', 'hs_lms_ajax_protected_media' );
add_action( 'wp_ajax_nopriv_hs_protected_media', 'hs_lms_ajax_protected_media' );
function hs_lms_ajax_protected_media() {
    if ( ! is_user_logged_in() ) {
        status_header( 403 ); exit;
    }

    $course_id = absint( $_GET['course_id'] ?? 0 );
    $file_b64  = sanitize_text_field( wp_unslash( $_GET['file'] ?? '' ) );
    $expires   = absint( $_GET['exp'] ?? 0 );
    $sig       = sanitize_text_field( wp_unslash( $_GET['sig'] ?? '' ) );

    $relpath = base64_decode( strtr( $file_b64, '-_', '+/' ), true );
    if ( ! $course_id || ! $relpath || ! $expires || ! $sig ) {
        status_header( 400 ); exit;
    }
    if ( $expires < time() ) {
        status_header( 410 ); exit; // URL expirada — recarregue a página da aula
    }
    if ( ! hash_equals( hs_lms_media_token( $course_id, $relpath, $expires ), $sig ) ) {
        status_header( 403 ); exit;
    }
    if ( function_exists( 'hs_lms_user_can_access_course' ) && ! hs_lms_user_can_access_course( get_current_user_id(), $course_id ) ) {
        status_header( 403 ); exit;
    }

    $uploads = wp_get_upload_dir();
    $base = realpath( $uploads['basedir'] );
    $path = $base ? realpath( trailingslashit( $uploads['basedir'] ) . $relpath ) : false;
    if ( ! $base || ! $path || strpos( $path, $base ) !== 0 || ! is_readable( $path ) ) {
        status_header( 404 ); exit;
    }

    $ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
    $mimes = hs_lms_protected_media_mimes();
    if ( ! isset( $mimes[ $ext ] ) ) {
        status_header( 415 ); exit;
    }

    hs_lms_stream_file_range( $path, $mimes[ $ext ], basename( $path ), ! empty( $_GET['download'] ) );
}
