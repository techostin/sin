<?php

declare(strict_types=1);

/**
 * Reclassificação do "Gestor" legado (nível 2) como FISCAL/TÉCNICO.
 *
 * No sistema antigo existe um único nível 2 chamado "Gestor/Fiscal". Na
 * prática, essas contas são de FISCAL/TÉCNICO (MAPA, EMATER, prefeituras,
 * certificadoras) — não de gestor da associação. O modelo K1 separa:
 *
 *   gestor_associacao   -> quadro social, financeiro, governança
 *   gestor_fiscalizacao -> fiscalização/certificação completas (fiscal)
 *
 * Este script garante que toda conta legada de nível 2 / tipo 'gestor' tenha
 * `gestor_fiscalizacao` e REMOVE o `gestor_associacao` indevidamente
 * atribuído no backfill inicial. Idempotente e auditado.
 *
 * Uso (CLI, bloqueado na web):
 *   php scripts/migrar-papeis-gestor-legado.php            # dry-run
 *   php scripts/migrar-papeis-gestor-legado.php --apply    # aplica
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

$papeis = [];
foreach ($pdo->query("SELECT id, codigo FROM papeis WHERE codigo IN ('gestor_fiscalizacao','gestor_associacao')") as $r) {
    $papeis[(string) $r['codigo']] = (int) $r['id'];
}
if (!isset($papeis['gestor_fiscalizacao'], $papeis['gestor_associacao'])) {
    exit("Papéis K1 não encontrados. Rode a migração K1 primeiro.\n");
}

$sql = "SELECT u.id, u.nome, u.email, u.tipo, u.nivel, u.certificadora_id,
               GROUP_CONCAT(DISTINCT p.codigo ORDER BY p.codigo) AS papeis
        FROM usuarios u
        LEFT JOIN usuario_papel up ON up.usuario_id = u.id AND up.ativo = 1
        LEFT JOIN papeis p ON p.id = up.papel_id
        WHERE u.status = 'ativo'
          AND (u.nivel = 2 OR u.tipo = 'gestor')
        GROUP BY u.id
        ORDER BY u.id";

$alvos = $pdo->query($sql)->fetchAll();

echo 'modo: ' . ($aplicar ? 'APLICAR' : 'dry-run (use --apply para gravar)') . PHP_EOL;
echo 'regra: nível 2 = FISCAL -> manter gestor_fiscalizacao e remover gestor_associacao' . PHP_EOL . PHP_EOL;

$adicionados = [];
$removidos = [];

foreach ($alvos as $u) {
    $usuarioId = (int) $u['id'];
    $atuais = array_filter(array_map('trim', explode(',', (string) ($u['papeis'] ?? ''))));

    $temFiscal = in_array('gestor_fiscalizacao', $atuais, true);
    $temAssoc = in_array('gestor_associacao', $atuais, true);

    echo sprintf(
        '#%d %s <%s> tipo=%s nível=%d certificadora=%s papéis=[%s]',
        $usuarioId,
        (string) $u['nome'],
        (string) $u['email'],
        (string) $u['tipo'],
        (int) $u['nivel'],
        $u['certificadora_id'] !== null ? (string) $u['certificadora_id'] : '-',
        $atuais !== [] ? implode(', ', $atuais) : '-'
    ) . PHP_EOL;

    if (!$temFiscal) {
        echo '   → precisa receber gestor_fiscalizacao' . PHP_EOL;
    }
    if ($temAssoc) {
        echo '   → gestor_associacao indevido será removido' . PHP_EOL;
    }
    if ($temFiscal && !$temAssoc) {
        echo '   → já está correto' . PHP_EOL;
        continue;
    }

    if (!$aplicar) {
        continue;
    }

    if (!$temFiscal) {
        $existe = $pdo->prepare(
            "SELECT 1 FROM usuario_papel
             WHERE usuario_id = :uid AND papel_id = :pid AND escopo_tipo = 'global' AND escopo_id = 0 AND ativo = 1"
        );
        $existe->execute(['uid' => $usuarioId, 'pid' => $papeis['gestor_fiscalizacao']]);
        if ($existe->fetchColumn() === false) {
            $ins = $pdo->prepare(
                "INSERT INTO usuario_papel (usuario_id, papel_id, escopo_tipo, escopo_id, ativo)
                 VALUES (:uid, :pid, 'global', 0, 1)"
            );
            $ins->execute(['uid' => $usuarioId, 'pid' => $papeis['gestor_fiscalizacao']]);
            $novo = (int) $pdo->lastInsertId();
            $adicionados[] = $novo;
            $pdo->prepare(
                'INSERT INTO audit_log (user_id, acao, tabela, registro_id, descricao, ip)
                 VALUES (NULL, :acao, :tabela, :rid, :desc, NULL)'
            )->execute([
                'acao' => 'PAPEL_ATRIBUIDO',
                'tabela' => 'usuario_papel',
                'rid' => $novo,
                'desc' => 'gestor_fiscalizacao (reclassificação: nível 2 legado é fiscal)',
            ]);
            echo '      + gestor_fiscalizacao (id ' . $novo . ')' . PHP_EOL;
        }
    }

    if ($temAssoc) {
        $sel = $pdo->prepare(
            "SELECT id FROM usuario_papel
             WHERE usuario_id = :uid AND papel_id = :pid AND ativo = 1"
        );
        $sel->execute(['uid' => $usuarioId, 'pid' => $papeis['gestor_associacao']]);
        foreach ($sel->fetchAll() as $vinculo) {
            $pdo->prepare('DELETE FROM usuario_papel WHERE id = :id')
                ->execute(['id' => (int) $vinculo['id']]);
            $removidos[] = (int) $vinculo['id'];
            $pdo->prepare(
                'INSERT INTO audit_log (user_id, acao, tabela, registro_id, descricao, ip)
                 VALUES (NULL, :acao, :tabela, :rid, :desc, NULL)'
            )->execute([
                'acao' => 'PAPEL_REMOVIDO',
                'tabela' => 'usuario_papel',
                'rid' => (int) $vinculo['id'],
                'desc' => 'gestor_associacao removido (nível 2 legado é fiscal, não gestor de associação)',
            ]);
            echo '      - gestor_associacao (id ' . (int) $vinculo['id'] . ')' . PHP_EOL;
        }
    }
}

echo PHP_EOL;
echo $aplicar
    ? 'Resumo: +' . count($adicionados) . ' gestor_fiscalizacao (' . implode(', ', $adicionados) . '); -'
        . count($removidos) . ' gestor_associacao (' . implode(', ', $removidos) . ')' . PHP_EOL
    : 'Resumo: ' . count($alvos) . ' conta(s) analisada(s) em dry-run.' . PHP_EOL;
