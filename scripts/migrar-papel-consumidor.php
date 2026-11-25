<?php

declare(strict_types=1);

define('SINPORG_SKIP_SESSION', true);
require __DIR__ . '/../config/config.php';

$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// 1) Ficha de adesão passa a aceitar o tipo consumidor/cliente.
// (o usuário da aplicação pode não ter ALTER; nesse caso rode o SQL como root)
try {
    $pdo->exec(
        "ALTER TABLE ficha_adesao
          MODIFY tipo_associado
          ENUM('agricultor_familiar','colaborador','honorario','institucional','consumidor') NULL DEFAULT NULL"
    );
    echo "ficha_adesao.tipo_associado atualizado" . PHP_EOL;
} catch (\Throwable $e) {
    echo "AVISO: ALTER não permitido para o usuário da aplicação; aplique o enum 'consumidor' como root." . PHP_EOL;
}

// 2) Papel consumidor.
$stmt = $pdo->prepare("SELECT id FROM papeis WHERE codigo = 'consumidor' LIMIT 1");
$stmt->execute();
$papelId = (int) $stmt->fetchColumn();
if ($papelId <= 0) {
    $pdo->prepare("INSERT INTO papeis (codigo, nome, descricao) VALUES ('consumidor', 'Membro consumidor', 'Associado consumidor/cliente, sem unidade de produção')")
        ->execute();
    $papelId = (int) $pdo->lastInsertId();
    echo "papel consumidor criado (id {$papelId})" . PHP_EOL;
} else {
    echo "papel consumidor já existe (id {$papelId})" . PHP_EOL;
}

// 3) Permissões de autoatendimento no catálogo.
$permissoes = [
    'associados.self' => 'Acessar somente a própria ficha/dados de associado',
    'documentos.self' => 'Acessar somente os próprios documentos e carteirinha',
    'financeiro.self' => 'Acessar somente as próprias mensalidades',
    'relatorios.self' => 'Acessar somente os próprios relatórios/histórico',
];
foreach ($permissoes as $codigo => $descricao) {
    $stmt = $pdo->prepare('SELECT id FROM permissoes WHERE codigo = :c LIMIT 1');
    $stmt->execute(['c' => $codigo]);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->prepare('INSERT INTO permissoes (codigo, descricao) VALUES (:c, :d)')
            ->execute(['c' => $codigo, 'd' => $descricao]);
        echo "permissão {$codigo} criada" . PHP_EOL;
    }
}

// 4) Vínculos do papel consumidor: somente portal + self-service.
$permitidas = ['portal.read', 'associados.self', 'documentos.self', 'financeiro.self', 'relatorios.self'];
$stmt = $pdo->prepare(
    "DELETE pp FROM papel_permissao pp
       JOIN permissoes perm ON perm.id = pp.permissao_id
      WHERE pp.papel_id = ? AND perm.codigo NOT IN ('portal.read','associados.self','documentos.self','financeiro.self','relatorios.self')"
);
$stmt->execute([$papelId]);

foreach ($permitidas as $codigo) {
    $stmt = $pdo->prepare('SELECT id FROM permissoes WHERE codigo = :c LIMIT 1');
    $stmt->execute(['c' => $codigo]);
    $permId = (int) $stmt->fetchColumn();
    if ($permId <= 0) {
        continue;
    }
    $pdo->prepare(
        'INSERT IGNORE INTO papel_permissao (papel_id, permissao_id) VALUES (:p, :m)'
    )->execute(['p' => $papelId, 'm' => $permId]);
}
echo 'permissões do consumidor: ' . implode(', ', $permitidas) . PHP_EOL;
