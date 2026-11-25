<?php

declare(strict_types=1);

/**
 * Perfis da diretoria e isolamento do técnico/fiscal.
 *
 * Reflete a organização real da associação:
 *  - diretor/vice/presidente -> acesso total ao módulo de associação;
 *  - tesoureiro              -> área financeira do módulo de associação;
 *  - secretário              -> gestão de documentos do módulo de associação;
 *  - técnico/fiscal          -> SOMENTE fiscalização (não acessa a área associativa).
 *
 * Garante no banco:
 *  - permissões documentos.read/write;
 *  - papéis diretor, tesoureiro e secretario com suas permissões;
 *  - remoção de associados.read/portal.read dos papéis de fiscalização.
 *
 * Uso (CLI):
 *   php scripts/migrar-papeis-diretoria.php            # dry-run
 *   php scripts/migrar-papeis-diretoria.php --apply
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script é CLI-only.');
}

define('SINPORG_SKIP_SESSION', true);
require dirname(__DIR__) . '/config/config.php';

if ((defined('DB_NAME') ? DB_NAME : '') !== 'sisagro') {
    exit("Banco inesperado. Abortando por segurança.\n");
}

$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$aplicar = in_array('--apply', $argv, true);

$permissoesNovas = [
    'documentos.read' => 'Ler documentos da associação',
    'documentos.write' => 'Emitir/registrar documentos da associação',
];

$papeisNovos = [
    'diretor' => 'Diretor(a) — acesso total ao módulo de associação',
    'tesoureiro' => 'Tesoureiro(a) — área financeira do módulo de associação',
    'secretario' => 'Secretário(a) — gestão de documentos do módulo de associação',
];

$mapaPapeis = [
    'diretor' => [
        'associados.read', 'associados.write', 'associados.approve',
        'financeiro.read', 'financeiro.write', 'governanca.read', 'governanca.write',
        'relatorios.read', 'portal.read', 'config.read',
        'documentos.read', 'documentos.write',
    ],
    'tesoureiro' => [
        'associados.read', 'financeiro.read', 'financeiro.write',
        'governanca.read', 'relatorios.read', 'portal.read',
    ],
    'secretario' => [
        'associados.read', 'documentos.read', 'documentos.write',
        'governanca.read', 'relatorios.read', 'portal.read',
    ],
];

$papelAdmin = (int) ($pdo->query("SELECT id FROM papeis WHERE codigo = 'admin' LIMIT 1")->fetchColumn() ?: 0);

$fiscais = ['gestor_fiscalizacao', 'fiscal_campo', 'certificador'];
$permissoesRemoverDoFiscal = ['associados.read', 'portal.read'];

echo 'modo: ' . ($aplicar ? 'APLICAR' : 'dry-run (use --apply para gravar)') . PHP_EOL . PHP_EOL;

$acoes = 0;

// 1. Permissões
foreach ($permissoesNovas as $codigo => $descricao) {
    $stmt = $pdo->prepare('SELECT id FROM permissoes WHERE codigo = :c');
    $stmt->execute(['c' => $codigo]);
    if ($stmt->fetchColumn() !== false) {
        echo "permissão {$codigo}: já existe" . PHP_EOL;
        continue;
    }
    echo "permissão {$codigo}: criar" . PHP_EOL;
    $acoes++;
    if ($aplicar) {
        $pdo->prepare('INSERT INTO permissoes (codigo, descricao) VALUES (:c, :d)')
            ->execute(['c' => $codigo, 'd' => $descricao]);
    }
}

// 2. Papéis e vínculos papel_permissao
foreach ($papeisNovos as $codigo => $nome) {
    $stmt = $pdo->prepare('SELECT id FROM papeis WHERE codigo = :c');
    $stmt->execute(['c' => $codigo]);
    $papelId = $stmt->fetchColumn();

    if ($papelId === false) {
        echo "papel {$codigo}: criar" . PHP_EOL;
        $acoes++;
        if ($aplicar) {
            $pdo->prepare('INSERT INTO papeis (codigo, nome, descricao) VALUES (:c, :n, :d)')
                ->execute(['c' => $codigo, 'n' => $nome, 'd' => $nome]);
            $papelId = (int) $pdo->lastInsertId();
        }
    } else {
        $papelId = (int) $papelId;
    }

    if (!$aplicar || $papelId <= 0) {
        echo "papel {$codigo}: " . count($mapaPapeis[$codigo]) . ' permissões previstas' . PHP_EOL;
        continue;
    }

    foreach ($mapaPapeis[$codigo] as $permissao) {
        $permId = (int) ($pdo->query("SELECT id FROM permissoes WHERE codigo = " . $pdo->quote($permissao) . ' LIMIT 1')->fetchColumn() ?: 0);
        if ($permId <= 0) {
            echo "   ! permissão ausente: {$permissao}" . PHP_EOL;
            continue;
        }
        $existe = $pdo->prepare('SELECT 1 FROM papel_permissao WHERE papel_id = :p AND permissao_id = :m');
        $existe->execute(['p' => $papelId, 'm' => $permId]);
        if ($existe->fetchColumn() === false) {
            $pdo->prepare('INSERT INTO papel_permissao (papel_id, permissao_id) VALUES (:p, :m)')
                ->execute(['p' => $papelId, 'm' => $permId]);
            $acoes++;
        }
    }
    echo "papel {$codigo}: vínculos garantidos" . PHP_EOL;
}

// 3. Admin também recebe as novas permissões
if ($aplicar && $papelAdmin > 0) {
    foreach (array_keys($permissoesNovas) as $permissao) {
        $permId = (int) ($pdo->query("SELECT id FROM permissoes WHERE codigo = " . $pdo->quote($permissao) . ' LIMIT 1')->fetchColumn() ?: 0);
        if ($permId <= 0) {
            continue;
        }
        $existe = $pdo->prepare('SELECT 1 FROM papel_permissao WHERE papel_id = :p AND permissao_id = :m');
        $existe->execute(['p' => $papelAdmin, 'm' => $permId]);
        if ($existe->fetchColumn() === false) {
            $pdo->prepare('INSERT INTO papel_permissao (papel_id, permissao_id) VALUES (:p, :m)')
                ->execute(['p' => $papelAdmin, 'm' => $permId]);
            $acoes++;
        }
    }
}

// 4. Técnico/fiscal não acessa a área associativa
foreach ($fiscais as $papelCodigo) {
    $stmt = $pdo->prepare('SELECT id FROM papeis WHERE codigo = :c');
    $stmt->execute(['c' => $papelCodigo]);
    $papelId = (int) ($stmt->fetchColumn() ?: 0);
    if ($papelId <= 0) {
        continue;
    }

    foreach ($permissoesRemoverDoFiscal as $permissao) {
        $permId = (int) ($pdo->query("SELECT id FROM permissoes WHERE codigo = " . $pdo->quote($permissao) . ' LIMIT 1')->fetchColumn() ?: 0);
        if ($permId <= 0) {
            continue;
        }
        $stmt = $pdo->prepare('SELECT 1 FROM papel_permissao WHERE papel_id = :p AND permissao_id = :m');
        $stmt->execute(['p' => $papelId, 'm' => $permId]);
        if ($stmt->fetchColumn() === false) {
            continue;
        }
        echo "papel {$papelCodigo}: remover permissão {$permissao}" . PHP_EOL;
        $acoes++;
        if ($aplicar) {
            $pdo->prepare('DELETE FROM papel_permissao WHERE papel_id = :p AND permissao_id = :m')
                ->execute(['p' => $papelId, 'm' => $permId]);
        }
    }
}

echo PHP_EOL . 'ações: ' . $acoes . ($aplicar ? ' (aplicadas)' : ' (previstas em dry-run)') . PHP_EOL;
