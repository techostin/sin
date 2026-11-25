<?php

declare(strict_types=1);

define('SINPORG_SKIP_SESSION', true);
require __DIR__ . '/../config/config.php';

$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$permitidasMembro = [
    'caderno.read', 'caderno.write', 'portal.read', 'agroindustria.read', 'agroindustria.write',
];

$stmt = $pdo->prepare("SELECT id FROM papeis WHERE codigo = 'produtor' LIMIT 1");
$stmt->execute();
$papelId = (int) $stmt->fetchColumn();

if ($papelId <= 0) {
    exit("Papel 'produtor' não encontrado.\n");
}

$marcadores = implode(',', array_fill(0, count($permitidasMembro), '?'));
$sql = "DELETE pp FROM papel_permissao pp
         JOIN permissoes perm ON perm.id = pp.permissao_id
        WHERE pp.papel_id = ?
          AND perm.codigo NOT IN ({$marcadores})";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$papelId], $permitidasMembro));
$removidas = $stmt->rowCount();

echo "Permissões globais removidas do papel produtor: {$removidas}" . PHP_EOL;

$stmt = $pdo->prepare(
    "SELECT perm.codigo FROM papel_permissao pp
       JOIN permissoes perm ON perm.id = pp.permissao_id
      WHERE pp.papel_id = ? ORDER BY perm.codigo"
);
$stmt->execute([$papelId]);
echo 'Permissões restantes: ' . implode(', ', array_map(static fn(array $r): string => (string) $r['codigo'], $stmt->fetchAll())) . PHP_EOL;
