<?php

declare(strict_types=1);

/**
 * Diagnóstico de acesso de um usuário na plataforma.
 *
 * Responde "por que fulano (não) acessa o sistema X?" mostrando o perfil
 * efetivo, os papéis, as permissões e a decisão por sistema.
 *
 * Uso (CLI, bloqueado na web):
 *   php scripts/diagnostico-acesso.php vanessa@gmail.com
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script é CLI-only.');
}

require dirname(__DIR__) . '/src/Platform/Bootstrap.php';

use App\Platform\Aplicacoes;
use App\Platform\Autorizacao;
use App\Platform\Identidades;

$alvo = trim((string) ($argv[1] ?? ''));
if ($alvo === '') {
    fwrite(STDERR, "Uso: php scripts/diagnostico-acesso.php <email|id>\n");
    exit(2);
}

$identidade = ctype_digit($alvo)
    ? Identidades::porUsuarioId((int) $alvo)
    : Identidades::porEmail($alvo);

if ($identidade === null) {
    $identidade = Identidades::porEmail($alvo);
}

if ($identidade === null) {
    fwrite(STDERR, "Nenhuma conta encontrada para: {$alvo}\n");
    exit(1);
}

echo '=== IDENTIDADE ===' . PHP_EOL;
echo 'nome:          ' . $identidade['nome'] . PHP_EOL;
echo 'email:         ' . $identidade['email'] . PHP_EOL;
echo 'origem:        ' . $identidade['origem'] . ' (id ' . $identidade['id'] . ')' . PHP_EOL;
echo 'tipo/nível:    ' . $identidade['tipo'] . ' / ' . $identidade['nivel'] . PHP_EOL;
echo 'status:        ' . ($identidade['status'] ?? '-') . PHP_EOL;
echo 'perfil:        ' . $identidade['perfil'] . ' [' . $identidade['perfil_codigo'] . ']' . PHP_EOL;
echo 'admin:         ' . (Autorizacao::ehAdmin($identidade) ? 'SIM' : 'não') . PHP_EOL;
echo 'papéis:        ' . (implode(', ', $identidade['papeis']) ?: '(nenhum)') . PHP_EOL;
echo 'permissoes:    ' . implode(', ', $identidade['permissoes']) . PHP_EOL;
echo PHP_EOL;

echo '=== ACESSO POR SISTEMA ===' . PHP_EOL;
foreach (Aplicacoes::todos() as $slug => $app) {
    $permitido = Autorizacao::podeAcessar($identidade, (string) $slug);
    $visiveis = $permitido ? Autorizacao::funcoesVisiveis($identidade, (string) $slug) : [];
    $total = count($app['funcoes']);

    echo sprintf(
        '%-9s %-28s %s  funções: %d/%d',
        $slug,
        $app['nome'],
        $permitido ? 'PERMITIDO' : 'NEGADO   ',
        count($visiveis),
        $total
    ) . PHP_EOL;

    if ($permitido) {
        foreach ($visiveis as $funcao) {
            echo '   ✓ ' . $funcao['titulo'] . ' (' . $funcao['permissao'] . ')' . PHP_EOL;
        }
    } else {
        $faltando = [];
        foreach ($app['permissoes'] as $permissao) {
            $faltando[] = $permissao;
        }
        echo '   ✗ exige uma destas permissões: ' . implode(', ', $faltando) . PHP_EOL;
    }
    echo PHP_EOL;
}
