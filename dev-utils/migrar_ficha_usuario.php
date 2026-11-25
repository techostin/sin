<?php
/**
 * Migração: ficha_adesao ligada à conta (usuarios). Idempotente.
 * Uso: php dev-utils/migrar_ficha_usuario.php [--apply]
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Somente CLI.'); }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
$apply = in_array('--apply', $argv, true);
$db = Database::getInstance()->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
function coluna(PDO $db, string $t, string $c): bool { $s=$db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?"); $s->execute([$t,$c]); return (int)$s->fetchColumn()>0; }
function indice(PDO $db, string $t, string $i): bool { $s=$db->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?"); $s->execute([$t,$i]); return (int)$s->fetchColumn()>0; }
function fk(PDO $db, string $t, string $c): bool { $s=$db->prepare("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?"); $s->execute([$t,$c]); return (int)$s->fetchColumn()>0; }
$passos = [];
$s = $db->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ficha_adesao' AND COLUMN_NAME='associado_id'");
$assocNullable = ($s->fetchColumn() === 'YES');
if (!$assocNullable) $passos[] = "ALTER TABLE ficha_adesao MODIFY associado_id INT(11) NULL";
if (!coluna($db,'ficha_adesao','usuario_id')) $passos[] = "ALTER TABLE ficha_adesao ADD COLUMN usuario_id INT(11) NULL AFTER associado_id";
if (!indice($db,'ficha_adesao','uq_ficha_usuario')) $passos[] = "ALTER TABLE ficha_adesao ADD UNIQUE KEY uq_ficha_usuario (usuario_id)";
if (!fk($db,'ficha_adesao','fk_ficha_adesao_usuario')) $passos[] = "ALTER TABLE ficha_adesao ADD CONSTRAINT fk_ficha_adesao_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL";
if (!coluna($db,'usuarios','tem_agroindustria')) $passos[] = "ALTER TABLE usuarios ADD COLUMN tem_agroindustria TINYINT(1) NOT NULL DEFAULT 0 AFTER tipo_usuario";
if (!coluna($db,'usuarios','area_producao'))    $passos[] = "ALTER TABLE usuarios ADD COLUMN area_producao SET('animal','vegetal','agroindustria') NOT NULL DEFAULT '' AFTER tem_agroindustria";
echo 'Passos pendentes: ', count($passos), "\n";
foreach ($passos as $p) echo '  ', $p, "\n";
if (!$apply) { echo "\n(dry-run) rode com --apply para executar.\n"; exit; }
foreach ($passos as $p) { $db->exec($p); echo 'OK  ', $p, "\n"; }
echo "\nSchema final:\n";
foreach ($db->query("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ficha_adesao' AND COLUMN_NAME IN ('associado_id','usuario_id')") as $r) printf("  %-14s %-10s null=%s\n", $r['COLUMN_NAME'], $r['COLUMN_TYPE'], $r['IS_NULLABLE']);