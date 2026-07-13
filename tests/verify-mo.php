<?php
// Lê os .mo binários de volta e confere amostras de tradução.
function mo_read( string $f ): array {
    $d = file_get_contents( $f );
    $h = unpack( 'V7', substr( $d, 0, 28 ) );
    if ( $h[1] !== 0x950412de ) { fwrite( STDERR, "MAGIC INVÁLIDO em $f\n" ); exit( 1 ); }
    $n = $h[3]; $o_id = $h[4]; $o_str = $h[5]; $t = [];
    for ( $i = 0; $i < $n; $i++ ) {
        $a = unpack( 'V2', substr( $d, $o_id + $i * 8, 8 ) );
        $b = unpack( 'V2', substr( $d, $o_str + $i * 8, 8 ) );
        $t[ substr( $d, $a[2], $a[1] ) ] = substr( $d, $b[2], $b[1] );
    }
    return $t;
}

$base = dirname( __DIR__ ) . '/hubskills-lms/hubskills-lms/languages/';
$fail = 0;
foreach ( [ 'en_US', 'en_GB', 'es_ES', 'es_MX', 'es_AR', 'es_CO', 'es_CL' ] as $loc ) {
    $t = mo_read( $base . "hubskills-lms-$loc.mo" );
    echo "$loc: " . ( count( $t ) - 1 ) . " strings\n";
    if ( count( $t ) < 500 ) $fail++;
}

$en = mo_read( $base . 'hubskills-lms-en_US.mo' );
$es = mo_read( $base . 'hubskills-lms-es_ES.mo' );
$samples = [ 'Matricular-se', 'Comprar módulo', 'Certificamos que', 'Chave de licença', 'Solicitar reembolso', 'Você precisa estar logado.' ];
foreach ( $samples as $s ) {
    $ok = ! empty( $en[ $s ] ) && ! empty( $es[ $s ] );
    if ( ! $ok ) $fail++;
    printf( "%s %s => EN: %s | ES: %s\n", $ok ? 'OK  ' : 'FAIL', $s, $en[ $s ] ?? '∅', $es[ $s ] ?? '∅' );
}

// Confere que placeholders %s/%d foram preservados em todas as traduções
foreach ( [ 'en_US' => $en, 'es_ES' => $es ] as $loc => $t ) {
    foreach ( $t as $id => $str ) {
        if ( $id === '' ) continue;
        preg_match_all( '/%(?:\d+\$)?[sd]/', $id, $a );
        preg_match_all( '/%(?:\d+\$)?[sd]/', $str, $b );
        sort( $a[0] ); sort( $b[0] );
        if ( $a[0] !== $b[0] ) { $fail++; echo "FAIL placeholders ($loc): " . substr( $id, 0, 60 ) . "\n"; }
    }
}

echo $fail ? "\n$fail problema(s)\n" : "\nTudo certo: .mo válidos, amostras e placeholders conferem.\n";
exit( $fail ? 1 : 0 );
