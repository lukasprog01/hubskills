<?php
/**
 * HubSkills LMS — suíte de testes
 * Execute: php tests/run-tests.php  (a partir da raiz do repositório)
 */
require __DIR__ . '/bootstrap.php';

$plugin = dirname( __DIR__ ) . '/hubskills-lms/hubskills-lms';
require $plugin . '/includes/payments.php';
require $plugin . '/includes/secure-media.php';
require $plugin . '/includes/refunds.php';
require $plugin . '/includes/frontend-dashboard.php';
require $plugin . '/admin/course-metabox.php';

// ── Micro-framework ───────────────────────────────────────
$GLOBALS['__tests'] = [ 'pass' => 0, 'fail' => 0, 'failures' => [] ];

function expect( $label, $actual, $expected ) {
    if ( $actual === $expected ) {
        $GLOBALS['__tests']['pass']++;
        echo "  ✓ {$label}\n";
    } else {
        $GLOBALS['__tests']['fail']++;
        $GLOBALS['__tests']['failures'][] = $label;
        echo "  ✗ {$label}\n    esperado: " . var_export( $expected, true ) . "\n    recebido: " . var_export( $actual, true ) . "\n";
    }
}
function expect_true( $label, $actual ) { expect( $label, (bool) $actual, true ); }
function suite( $name ) { echo "\n── {$name} " . str_repeat( '─', max( 1, 50 - mb_strlen( $name ) ) ) . "\n"; }

// ══ 1. Preço (payments.php) ═══════════════════════════════
suite( 'hs_parse_course_price' );
expect( 'numérico simples', hs_parse_course_price( 97 ), 97.0 );
expect( 'formato BR "R$ 97,00"', hs_parse_course_price( 'R$ 97,00' ), 97.0 );
expect( 'milhar BR "R$ 1.234,56"', hs_parse_course_price( 'R$ 1.234,56' ), 1234.56 );
expect( 'formato US "1,234.56"', hs_parse_course_price( '1,234.56' ), 1234.56 );
expect( '"Grátis" vira zero', hs_parse_course_price( 'Grátis' ), 0.0 );
expect( 'negativo vira zero', hs_parse_course_price( -5 ), 0.0 );
expect( 'string vazia vira zero', hs_parse_course_price( '' ), 0.0 );

// ══ 2. Sanitização de currículo (frontend-dashboard.php) ══
suite( 'hs_lms_sanitize_curriculum' );
$dirty = [
    [
        'section' => '  Módulo <script>alert(1)</script>1 ',
        'lessons' => [
            [ 'id' => 10, 'title' => '<b>Aula</b> 1', 'type' => 'video', 'video_provider' => 'youtube', 'video_url' => 'https://youtu.be/abc', 'quiz_id' => '5abc' ],
            [ 'title' => 'Sem ID', 'video_provider' => 'hack-provider' ],
        ],
    ],
];
$clean = hs_lms_sanitize_curriculum( $dirty );
expect( 'script removido do módulo (tag + conteúdo)', $clean[0]['section'], 'Módulo 1' );
expect( 'HTML removido do título da aula', $clean[0]['lessons'][0]['title'], 'Aula 1' );
expect( 'id existente preservado', $clean[0]['lessons'][0]['id'], 10 );
expect( 'quiz_id convertido para int', $clean[0]['lessons'][0]['quiz_id'], 5 );
expect( 'provider inválido vira none', $clean[0]['lessons'][1]['video_provider'], 'none' );
expect_true( 'aula sem id ganha id automático', $clean[0]['lessons'][1]['id'] > 0 );

// ══ 3. Sanitização de quiz (course-metabox.php) ═══════════
suite( 'hs_sanitize_quiz_questions' );
$questions = hs_sanitize_quiz_questions( [
    [ 'id' => 1, 'text' => 'Pergunta válida?', 'options' => [ 'A', 'B', 'C', 'D' ], 'correct' => 2, 'explanation' => 'Porque sim.' ],
    [ 'text' => '', 'options' => [ 'A', 'B' ] ],
    [ 'text' => 'Só uma opção', 'options' => [ 'A' ] ],
    [ 'text' => 'Correct fora do range', 'options' => [ 'A', 'B' ], 'correct' => 9 ],
] );
expect( 'questões inválidas descartadas', count( $questions ), 2 );
expect( 'questão válida preservada', $questions[0]['text'], 'Pergunta válida?' );
expect( 'resposta correta preservada', $questions[0]['correct'], 2 );
expect( 'correct clampado ao nº de opções', $questions[1]['correct'], 1 );
expect_true( 'questão sem id ganha id', $questions[1]['id'] > 0 );

// ══ 4. Mídia protegida (secure-media.php) ═════════════════
suite( 'secure-media: detecção de arquivo local' );
expect( 'URL de uploads vira relpath', hs_lms_local_upload_relpath( 'https://example.test/wp-content/uploads/2026/06/video.mp4' ), '2026/06/video.mp4' );
expect( 'querystring é descartada', hs_lms_local_upload_relpath( 'https://example.test/wp-content/uploads/a.mp4?x=1' ), 'a.mp4' );
expect( 'URL externa retorna false', hs_lms_local_upload_relpath( 'https://cdn.terceiro.com/video.mp4' ), false );
expect( 'path traversal rejeitado', hs_lms_local_upload_relpath( 'https://example.test/wp-content/uploads/../wp-config.php' ), false );
expect( 'traversal URL-encoded rejeitado', hs_lms_local_upload_relpath( 'https://example.test/wp-content/uploads/%2e%2e/wp-config.php' ), false );

suite( 'secure-media: URLs assinadas' );
$signed = hs_lms_protected_media_url( 42, 'https://example.test/wp-content/uploads/2026/06/video.mp4' );
expect_true( 'URL local vira proxy assinado', strpos( $signed, 'action=hs_protected_media' ) !== false );
expect_true( 'URL assinada carrega sig', strpos( $signed, 'sig=' ) !== false );
expect_true( 'URL assinada carrega expiração', strpos( $signed, 'exp=' ) !== false );
expect( 'URL externa passa intocada', hs_lms_protected_media_url( 42, 'https://youtu.be/abc' ), 'https://youtu.be/abc' );

$exp = time() + 100;
$tok = hs_lms_media_token( 42, '2026/06/video.mp4', $exp );
expect_true( 'token validado com hash_equals', hash_equals( hs_lms_media_token( 42, '2026/06/video.mp4', $exp ), $tok ) );
expect_true( 'curso diferente gera token diferente', ! hash_equals( hs_lms_media_token( 43, '2026/06/video.mp4', $exp ), $tok ) );
expect_true( 'expiração diferente gera token diferente', ! hash_equals( hs_lms_media_token( 42, '2026/06/video.mp4', $exp + 1 ), $tok ) );

// ══ 5. Elegibilidade de reembolso (refunds.php) ═══════════
suite( 'hs_lms_refund_eligibility' );
$paid_order = [ 'id' => 100, 'status' => 'paid', 'created_at' => gmdate( 'Y-m-d H:i:s' ) ];

$result = hs_lms_refund_eligibility( [ 'id' => 101, 'status' => 'pending', 'created_at' => gmdate( 'Y-m-d H:i:s' ) ] );
expect( 'pedido não pago é rejeitado', is_wp_error( $result ) ? $result->get_error_code() : 'ok', 'not_paid' );

update_post_meta( 100, '_hs_order_paid_at', gmdate( 'Y-m-d H:i:s' ) );
expect( 'pedido pago dentro da janela é elegível', hs_lms_refund_eligibility( $paid_order ), true );

update_post_meta( 100, '_hs_order_refund_request', [ 'status' => 'pending' ] );
$result = hs_lms_refund_eligibility( $paid_order );
expect( 'solicitação duplicada é rejeitada', is_wp_error( $result ) ? $result->get_error_code() : 'ok', 'already_requested' );
delete_post_meta( 100, '_hs_order_refund_request' );

update_post_meta( 100, '_hs_order_paid_at', gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) );
$result = hs_lms_refund_eligibility( $paid_order );
expect( 'fora da janela de 7 dias é rejeitado', is_wp_error( $result ) ? $result->get_error_code() : 'ok', 'window_expired' );

update_option( 'hs_lms_refund_window_days', 60 );
expect( 'janela configurável é respeitada', hs_lms_refund_eligibility( $paid_order ), true );
update_option( 'hs_lms_refund_window_days', 7 );

// ══ 6. Embeds de vídeo (frontend-dashboard.php) ═══════════
suite( 'embeds de vídeo' );
expect( 'youtu.be vira embed nocookie', hs_lms_youtube_embed_url( 'https://youtu.be/dQw4w9WgXcQ' ), 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0&modestbranding=1' );
expect( 'youtube.com/watch vira embed', hs_lms_youtube_embed_url( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ), 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0&modestbranding=1' );
expect( 'URL não-YouTube retorna vazio', hs_lms_youtube_embed_url( 'https://vimeo.com/123' ), '' );
expect( 'vimeo vira player embed', hs_lms_vimeo_embed_url( 'https://vimeo.com/123456' ), 'https://player.vimeo.com/video/123456' );

// ── Resumo ────────────────────────────────────────────────
$t = $GLOBALS['__tests'];
echo "\n" . str_repeat( '═', 54 ) . "\n";
echo "Total: " . ( $t['pass'] + $t['fail'] ) . " | Passou: {$t['pass']} | Falhou: {$t['fail']}\n";
if ( $t['fail'] > 0 ) {
    echo "Falhas:\n";
    foreach ( $t['failures'] as $f ) echo "  - {$f}\n";
    exit( 1 );
}
echo "✓ Todos os testes passaram!\n";
exit( 0 );
