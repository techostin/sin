<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
define('SINPORG_SKIP_SESSION', true);
require __DIR__ . '/../config/config.php';

$pdo = App\Kernel\Persistence\Connection::pdo();
$pdo->beginTransaction();

try {
    $roles = [];
    foreach ($pdo->query('SELECT id, codigo FROM papeis')->fetchAll() as $row) {
        $roles[(string) $row['codigo']] = (int) $row['id'];
    }

    $users = $pdo->query('SELECT id, nivel, tipo_usuario FROM usuarios')->fetchAll();
    $assigned = 0;
    $already = 0;

    foreach ($users as $user) {
        $userId = (int) $user['id'];
        $exists = $pdo->prepare(
            'SELECT 1 FROM usuario_papel WHERE usuario_id = :id AND ativo = 1 LIMIT 1'
        );
        $exists->execute(['id' => $userId]);
        if ($exists->fetchColumn() !== false) {
            $already++;
            continue;
        }

        $nivel = (int) $user['nivel'];
        $code = $nivel === 1 ? 'admin' : ($nivel === 2 ? 'gestor_associacao' : 'produtor');
        if (!isset($roles[$code])) {
            throw new RuntimeException('Papel ausente: ' . $code);
        }

        $insert = $pdo->prepare(
            "INSERT INTO usuario_papel (usuario_id, papel_id, escopo_tipo, escopo_id, ativo)
             VALUES (:usuario_id, :papel_id, 'global', 0, 1)"
        );
        $insert->execute(['usuario_id' => $userId, 'papel_id' => $roles[$code]]);
        $assigned++;
    }

    $missingUuid = $pdo->query("SELECT id FROM associados WHERE uuid IS NULL OR uuid = ''")->fetchAll();
    $uuidFixed = 0;
    foreach ($missingUuid as $row) {
        $update = $pdo->prepare('UPDATE associados SET uuid = UUID() WHERE id = :id');
        $update->execute(['id' => (int) $row['id']]);
        $uuidFixed++;
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'FALHA: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

$totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
$usersWithRole = (int) $pdo->query(
    'SELECT COUNT(DISTINCT usuario_id) FROM usuario_papel WHERE ativo = 1'
)->fetchColumn();
$totalAssociates = (int) $pdo->query('SELECT COUNT(*) FROM associados')->fetchColumn();
$associatesWithUuid = (int) $pdo->query(
    "SELECT COUNT(*) FROM associados WHERE uuid IS NOT NULL AND uuid <> ''"
)->fetchColumn();
$associatesWithUser = (int) $pdo->query(
    'SELECT COUNT(DISTINCT a.id) FROM associados a JOIN usuarios u ON u.associado_id = a.id'
)->fetchColumn();

echo json_encode([
    'usuarios_total' => $totalUsers,
    'usuarios_com_papel' => $usersWithRole,
    'papeis_atribuidos_agora' => $assigned,
    'usuarios_ja_com_papel' => $already,
    'associados_total' => $totalAssociates,
    'associados_com_uuid' => $associatesWithUuid,
    'uuids_corrigidos' => $uuidFixed,
    'associados_com_conta_usuario' => $associatesWithUser,
    'associados_sem_conta_usuario' => $totalAssociates - $associatesWithUser,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
