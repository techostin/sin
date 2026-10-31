<?php
/**
 * Migração: confirmação de privilégios do pedido de acesso. Idempotente.
 * Uso: php dev-utils/migrar_confirmacao_acesso.php [--apply]
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Somente CLI.'); }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
$apply = in_array('--apply', $argv, true);
$db = Database::getInstance()->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
function col(PDO $db, string $c): bool { $s=$db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME=?"); $s->execute([$c]); return (int)$s->fetchColumn()>0; }
$cols = [
  'nivel_solicitado'      => "ALTER TABLE usuarios ADD COLUMN nivel_solicitado TINYINT(1) NULL AFTER nivel",
  'perfil_solicitado'     => "ALTER TABLE usuarios ADD COLUMN perfil_solicitado VARCHAR(50) NULL AFTER nivel_solicitado",
  'acesso_confirmado_por' => "ALTER TABLE usuarios ADD COLUMN acesso_confirmado_por INT(11) NULL AFTER perfil_solicitado",
  'acesso_confirmado_em'  => "ALTER TABLE usuarios ADD COLUMN acesso_confirmado_em DATETIME NULL AFTER acesso_confirmado_por",
  'acesso_negado_motivo'  => "ALTER TABLE usuarios ADD COLUMN acesso_negado_motivo VARCHAR(255) NULL AFTER acesso_confirmado_em",
];
$passos = [];
foreach ($cols as $c => $sql) { if (!col($db,$c)) $passos[] = $sql; }
echo 'Passos pendentes: ', count($passos), "\n";
foreach ($passos as $p) echo '  ', $p, "\n";
if (!$apply) { echo "\n(dry-run) rode com --apply para executar.\n"; exit; }
foreach ($passos as $p) { $db->exec($p); echo 'OK  ', $p, "\n"; }
// Backfill: pedidos pendentes que ainda não têm o nível solicitado registrado
if ($apply) {
  $n = $db->exec("UPDATE usuarios SET nivel_solicitado = nivel WHERE status='pendente' AND nivel_solicitado IS NULL");
  echo 'Backfill nivel_solicitado em pendentes: ', $n, "\n";
}
echo "\nColunas de confirmação:\n";
foreach ($db->query("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME IN ('nivel','nivel_solicitado','perfil_solicitado','acesso_confirmado_por','acesso_confirmado_em','acesso_negado_motivo') ORDER BY ORDINAL_POSITION") as $r) printf("  %-24s %-14s null=%s\n", $r['COLUMN_NAME'], $r['COLUMN_TYPE'], $r['IS_NULLABLE']);