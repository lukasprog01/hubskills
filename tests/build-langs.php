<?php
// Gera .po e compila .mo do HubSkills LMS a partir de translations-map.php.
// Locales: en_US/en_GB (en) e es_ES/es_MX/es_AR/es_CO/es_CL (es).
$src  = dirname( __DIR__ ) . '/hubskills-lms/hubskills-lms';
$lang = $src . '/languages';
$pot  = file_get_contents( $lang . '/hubskills-lms.pot' );
$map  = require __DIR__ . '/translations-map.php';

// Mapa indexado também por trim() para tolerar espaços nas pontas.
$lookup = [];
foreach ( $map as $k => $v ) { $lookup[ $k ] = $v; $lookup[ trim( $k ) ] = $v; }

// Extrai msgids do .pot
preg_match_all( '/^msgid "((?:[^"\\\\]|\\\\.)*)"$/m', $pot, $m );
$msgids = [];
foreach ( $m[1] as $raw ) {
    if ( $raw === '' ) continue;
    $msgids[] = stripcslashes( str_replace( '\\n', "\n", $raw ) );
}

function po_escape( $s ) {
    return str_replace( [ '\\', '"', "\n", "\t" ], [ '\\\\', '\\"', '\\n', '\\t' ], $s );
}

function write_mo( $file, array $entries ) {
    ksort( $entries, SORT_STRING );
    $entries = array_merge( [ '' => "Content-Type: text/plain; charset=UTF-8\n" ], $entries );
    $n = count( $entries );
    $ids = ''; $strs = ''; $id_tbl = []; $str_tbl = [];
    foreach ( $entries as $id => $str ) {
        $id_tbl[]  = [ strlen( (string) $id ), strlen( $ids ) ];
        $str_tbl[] = [ strlen( $str ), strlen( $strs ) ];
        $ids  .= $id . "\0";
        $strs .= $str . "\0";
    }
    $id_base  = 28 + $n * 16;
    $str_base = $id_base + strlen( $ids );
    $mo = pack( 'V7', 0x950412de, 0, $n, 28, 28 + $n * 8, 0, 0 );
    foreach ( $id_tbl as $t )  $mo .= pack( 'V2', $t[0], $id_base + $t[1] );
    foreach ( $str_tbl as $t ) $mo .= pack( 'V2', $t[0], $str_base + $t[1] );
    $mo .= $ids . $strs;
    file_put_contents( $file, $mo );
}

$locales = [
    'en_US' => 'en', 'en_GB' => 'en',
    'es_ES' => 'es', 'es_MX' => 'es', 'es_AR' => 'es', 'es_CO' => 'es', 'es_CL' => 'es',
];
$lang_names = [ 'en' => 'English', 'es' => 'Español' ];

$missing = [];
foreach ( [ 'en', 'es' ] as $code ) {
    $entries = [];
    foreach ( $msgids as $id ) {
        $tr = $lookup[ $id ][ $code ] ?? $lookup[ trim( $id ) ][ $code ] ?? null;
        if ( $tr === null ) { $missing[ $id ] = true; continue; }
        $entries[ $id ] = $tr;
    }
    foreach ( $locales as $locale => $lc ) {
        if ( $lc !== $code ) continue;
        // .po
        $po  = "# HubSkills LMS — {$lang_names[$code]} ($locale)\n";
        $po .= "msgid \"\"\nmsgstr \"\"\n";
        $po .= "\"Project-Id-Version: HubSkills LMS 1.0.1\\n\"\n";
        $po .= "\"Language: $locale\\n\"\n";
        $po .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
        $po .= "\"Plural-Forms: nplurals=2; plural=(n != 1);\\n\"\n\n";
        foreach ( $entries as $id => $str ) {
            $po .= 'msgid "' . po_escape( $id ) . "\"\n";
            $po .= 'msgstr "' . po_escape( $str ) . "\"\n\n";
        }
        file_put_contents( "$lang/hubskills-lms-$locale.po", $po );
        write_mo( "$lang/hubskills-lms-$locale.mo", $entries );
        echo "$locale: " . count( $entries ) . " strings → .po + .mo\n";
    }
}

if ( $missing ) {
    echo "\nSEM TRADUÇÃO (" . count( $missing ) . "):\n";
    foreach ( array_keys( $missing ) as $id ) echo "  - " . substr( str_replace( "\n", '\\n', $id ), 0, 90 ) . "\n";
    exit( 1 );
}
echo "\nTodas as strings traduzidas.\n";
