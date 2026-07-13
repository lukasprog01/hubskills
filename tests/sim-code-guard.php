<?php
/**
 * Simulação do Code Guard rodando — carrega o código REAL (includes/code-guard.php)
 * com um WordPress "de mentira" (stubs) e encena uma invasão ao plugin.
 */
error_reporting(E_ALL & ~E_DEPRECATED);

// ── Stub mínimo do WordPress ────────────────────────────────────────────────
define('ABSPATH', __DIR__);
$GLOBALS['__opts'] = [];
function add_action() {}
function add_filter() {}
function get_option($k, $d = false)      { return $GLOBALS['__opts'][$k] ?? $d; }
function update_option($k, $v, $a = null){ $GLOBALS['__opts'][$k] = $v; return true; }
function delete_option($k)               { unset($GLOBALS['__opts'][$k]); return true; }
function current_time($t)                { return date('Y-m-d H:i:s'); }
function get_current_user_id()           { return 1; }
function wp_parse_args($a, $d)           { return array_merge($d, (array)$a); }
function untrailingslashit($s)           { return rtrim($s, '/\\'); }
function trailingslashit($s)             { return untrailingslashit($s) . '/'; }

// hs_audit_log existe? simulamos o log para ver o registro de violação
$GLOBALS['__audit'] = [];
function hs_audit_log($uid, $action, $desc) { $GLOBALS['__audit'][] = "[$action] $desc"; }

// ── Mini-plugin de teste ────────────────────────────────────────────────────
$dir = sys_get_temp_dir() . '/hs-guard-sim-' . uniqid();
mkdir($dir . '/includes', 0777, true);
mkdir($dir . '/languages', 0777, true);
file_put_contents($dir . '/hubskills-lms.php', "<?php // plugin principal v1.1.0\n");
file_put_contents($dir . '/includes/quiz.php', "<?php function hs_quiz(){ return 70; }\n");
file_put_contents($dir . '/includes/payments.php', "<?php // cobranças legítimas\n");
file_put_contents($dir . '/languages/pt_BR.mo', "binário-de-traducao\n"); // deve ser IGNORADO

define('HS_LMS_VERSION', '1.1.0');
define('HS_LMS_DIR', $dir . '/');

// Carrega o CÓDIGO REAL do Code Guard
require __DIR__ . '/../hubskills-lms/hubskills-lms/includes/code-guard.php';

function titulo($t){ echo "\n\033[1m== $t ==\033[0m\n"; }
function status($r){
    if ($r['ok']) { echo "  ✅ ÍNTEGRO — nenhum arquivo alterado.\n"; return; }
    foreach ($r['modified'] as $f) echo "  ✏️  MODIFICADO: $f\n";
    foreach ($r['added']    as $f) echo "  ➕ ADICIONADO (não pertence ao plugin!): $f\n";
    foreach ($r['missing']  as $f) echo "  ❌ REMOVIDO: $f\n";
}

// ── PASSO 1: gerar o manifesto (na ativação do plugin) ──────────────────────
titulo('PASSO 1 — Plugin ativado: gera manifesto SHA-256');
hs_guard_rebuild_manifest();
$m = get_option('hs_guard_manifest');
echo "  Arquivos monitorados: " . count($m['files']) . " (a pasta languages/ foi ignorada de propósito)\n";
foreach ($m['files'] as $rel => $hash) echo "    • $rel  →  " . substr($hash, 0, 16) . "...\n";

// ── PASSO 2: verificação de rotina (cron diário) ────────────────────────────
titulo('PASSO 2 — Verificação diária (nada mudou)');
status(hs_guard_verify());

// ── PASSO 3: INVASÃO — alguém edita, adiciona e remove arquivos ─────────────
titulo('PASSO 3 — 🦹 Invasor adultera o plugin via FTP');
echo "  > injeta código malicioso em includes/payments.php\n";
file_put_contents($dir . '/includes/payments.php', "<?php // cobranças legítimas\n eval(\$_GET['x']); // BACKDOOR\n");
echo "  > sobe um arquivo novo includes/backdoor.php\n";
file_put_contents($dir . '/includes/backdoor.php', "<?php system(\$_POST['cmd']);\n");
echo "  > apaga includes/quiz.php\n";
unlink($dir . '/includes/quiz.php');

titulo('PASSO 3 — Verificação detecta a adulteração');
$r = hs_guard_verify();
status($r);

// dispara a lógica do cron (alerta + auditoria) como faz o hook real
if (!$r['ok']) {
    $problemas = array_merge($r['modified'], $r['added'], $r['missing']);
    hs_audit_log(0, 'guard_integrity_failed', 'Integridade do plugin violada: ' . implode(', ', $problemas));
    echo "\n  📧 E-mail de alerta seria enviado ao admin.\n";
    echo "  📝 Registro na auditoria:\n";
    foreach ($GLOBALS['__audit'] as $log) echo "       $log\n";
}

// ── PASSO 4: edição da pasta IGNORADA não dispara alarme ────────────────────
titulo('PASSO 4 — Editar languages/ (build de tradução) NÃO é violação');
file_put_contents($dir . '/languages/pt_BR.mo', "traducao-atualizada-no-build\n");
// restaura os arquivos do plugin para isolar o teste
file_put_contents($dir . '/includes/payments.php', "<?php // cobranças legítimas\n");
file_put_contents($dir . '/includes/quiz.php', "<?php function hs_quiz(){ return 70; }\n");
unlink($dir . '/includes/backdoor.php');
status(hs_guard_verify());

// ── PASSO 5: atualização legítima → reconstruir manifesto ───────────────────
titulo('PASSO 5 — Você atualiza de propósito e reconstrói o manifesto');
file_put_contents($dir . '/includes/quiz.php', "<?php function hs_quiz(){ return 60; } // mudei a nota\n");
echo "  (após editar, antes de reconstruir:)\n";
status(hs_guard_verify());
echo "  > clicando em 'Reconstruir manifesto'...\n";
hs_guard_rebuild_manifest();
status(hs_guard_verify());

// limpeza
array_map('unlink', glob($dir . '/includes/*'));
array_map('unlink', glob($dir . '/languages/*'));
@unlink($dir . '/hubskills-lms.php');
@rmdir($dir . '/includes'); @rmdir($dir . '/languages'); @rmdir($dir);
echo "\n\033[1m✓ Simulação concluída.\033[0m\n";
