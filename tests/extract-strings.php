<?php
// Extrai strings traduzíveis (text domain hubskills-lms) e gera o .pot.
$src = dirname( __DIR__ ) . '/hubskills-lms/hubskills-lms';
$out = $src . '/languages';
if ( ! is_dir( $out ) ) mkdir( $out, 0777, true );

$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src ) );
$strings = []; // msgid => [refs]

$fn  = '(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)';
$str = "(?:'((?:[^'\\\\]|\\\\.)*)'|\"((?:[^\"\\\\]|\\\\.)*)\")";

foreach ( $files as $f ) {
    if ( $f->isDir() || $f->getExtension() !== 'php' ) continue;
    $code = file_get_contents( $f->getPathname() );
    $rel  = str_replace( '\\', '/', substr( $f->getPathname(), strlen( $src ) + 1 ) );
    if ( preg_match_all( "/$fn\\(\\s*$str\\s*,\\s*'hubskills-lms'/s", $code, $m, PREG_OFFSET_CAPTURE ) ) {
        foreach ( $m[0] as $i => $match ) {
            $raw = $m[1][$i][0] !== '' ? $m[1][$i][0] : $m[2][$i][0];
            $msgid = stripcslashes( $raw );
            $line  = substr_count( substr( $code, 0, $match[1] ), "\n" ) + 1;
            $strings[ $msgid ][] = "$rel:$line";
        }
    }
}

ksort( $strings );

function po_escape( $s ) {
    return str_replace( [ '\\', '"', "\n", "\t" ], [ '\\\\', '\\"', '\\n', '\\t' ], $s );
}

$pot  = "# HubSkills LMS — template de tradução\n";
$pot .= "msgid \"\"\nmsgstr \"\"\n";
$pot .= "\"Project-Id-Version: HubSkills LMS 1.0.1\\n\"\n";
$pot .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
$pot .= "\"X-Domain: hubskills-lms\\n\"\n\n";

foreach ( $strings as $msgid => $refs ) {
    $pot .= '#: ' . implode( ' ', array_slice( array_unique( $refs ), 0, 3 ) ) . "\n";
    $pot .= 'msgid "' . po_escape( $msgid ) . "\"\n";
    $pot .= "msgstr \"\"\n\n";
}

file_put_contents( $out . '/hubskills-lms.pot', $pot );
echo count( $strings ) . " strings extraídas → languages/hubskills-lms.pot\n";

// Despeja a lista para tradução
file_put_contents( __DIR__ . '/strings-list.txt', implode( "\n---\n", array_keys( $strings ) ) );
echo "Lista em tests/strings-list.txt\n";
