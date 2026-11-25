<?php
/**
 * Diagnóstico e reconciliação das identidades entre "associados" (pessoa) e
 * "usuarios" (conta de acesso ao sistema).
 *
 * Contexto: o projeto guarda a MESMA pessoa em duas tabelas:
 *   - associados : cadastro do membro (nome, sobrenome, CPF, RG, endereço,
 *                  vínculos, fichas, mensalidades, carteirinha, propriedades,
 *                  visitas, certificações). É a fonte canônica dos DADOS PESSOAIS.
 *   - usuarios   : conta de acesso (senha, nível, órgão/setor, certificadora).
 * O contrato entre elas é 1:1 quando a pessoa tem conta:
 *   usuarios.associado_id  = associados.id
 *   associados.user_id     = usuarios.id
 *
 * Uso (somente linha de comando):
 *   php dev-utils/reconciliar_identidades.php            # dry-run (só relatório)
 *   php dev-utils/reconciliar_identidades.php --apply     # aplica correções seguras
 *   php dev-utils/reconciliar_identidades.php --user=14   # foca em um usuário
 *
 * O que o --apply faz (nunca apaga registros):
 *   1. limpa FKs que apontam para IDs inexistentes;
 *   2. completa o link reverso (associados.user_id <-> usuarios.associado_id);
 *   3. espelha no usuarios os dados pessoais vazios/truncados vindos do associado
 *      (cpf, nome/sobrenome, telefone) e corrige telefone corrompido (2147483647);
 *   4. registra em audit_log cada correção aplicada.
 * Casos ambíguos (mesmo nome, CPF/e-mail divergentes) NÃO são alterados: vão
 * para a seção "revisão manual" do relatório.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este utilitário só pode ser executado pela linha de comando.');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/AuditLog.php';

$apply = in_array('--apply', $argv, true);
$force = in_array('--force', $argv, true);
$foco  = 0;
$vincular = [];   // pares confirmados manualmente: --link=usuariosId:associadoId
$mesclar = [];    // contas duplicadas da mesma pessoa: --merge=origemId:destinoId
foreach ($argv as $a) {
    if (strpos($a, '--user=') === 0) {
        $foco = (int) substr($a, 7);
    } elseif (strpos($a, '--link=') === 0) {
        $par = explode(':', substr($a, 7));
        if (count($par) === 2) { $vincular[] = [(int) $par[0], (int) $par[1]]; }
    } elseif (strpos($a, '--merge=') === 0 || strpos($a, '--merge-duplicado=') === 0) {
        $valor = substr($a, strpos($a, '=') + 1);
        $par = explode(':', $valor);
        if (count($par) === 2) { $mesclar[] = [(int) $par[0], (int) $par[1]]; }
    }
}

/**
 * Colunas que podem apontar para usuarios.id. Usadas ao mesclar contas
 * duplicadas: todas as referências da conta de origem passam para a de destino.
 */
const REFERENCIAS_USUARIO = [
    ['audit_log', 'user_id'],
    ['auth_refresh_tokens', 'user_id'],
    ['evento_presenca', 'usuario_id'],
    ['notificacoes', 'usuario_id'],
    ['ficha_adesao', 'analisado_por'],
    ['visitas_tecnicas', 'gestor_ciente_user_id'],
    ['associados', 'ficha_aprovada_por'],
    ['certificadoras', 'gestor_id'],
];

/**
 * Decisões já tomadas pela associação (versionadas em
 * dev-utils/identidades_ignoradas.json) para não voltarem ao relatório:
 *   pares_nao_duplicados      -> usuarios.id x associados.id que NÃO são a mesma pessoa
 *   emails_login_distintos_ok -> contas cujo e-mail de login difere do cadastro por decisão
 */
$decisoes = ['pares' => [], 'emails' => []];
$arqDecisoes = __DIR__ . '/identidades_ignoradas.json';
if (is_file($arqDecisoes)) {
    $json = json_decode((string) file_get_contents($arqDecisoes), true);
    if (is_array($json)) {
        foreach ((array) ($json['pares_nao_duplicados'] ?? []) as $it) {
            if (isset($it['usuario_id'], $it['associado_id'])) {
                $decisoes['pares'][(int) $it['usuario_id'] . ':' . (int) $it['associado_id']] = (string) ($it['motivo'] ?? '');
            }
        }
        foreach ((array) ($json['emails_login_distintos_ok'] ?? []) as $it) {
            if (isset($it['usuario_id'])) {
                $decisoes['emails'][(int) $it['usuario_id']] = (string) ($it['motivo'] ?? '');
            }
        }
    }
}

function colunaExiste(PDO $db, string $tabela, string $coluna): bool
{
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $st->execute([$tabela, $coluna]);
    return (int) $st->fetchColumn() > 0;
}

$db = Database::getInstance()->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$TELEFONE_LIXO = ['2147483647', '2147483648', '-2147483648', '0'];

function norm($s) { return preg_replace('/\s+/', ' ', strtolower(trim((string) $s))); }
function digitos($s) { return preg_replace('/\D+/', '', (string) $s); }
function notNull($v) { return $v !== null && trim((string) $v) !== ''; }

echo "=== Reconciliação de identidades (associados x usuarios) ===\n";
echo 'modo: ', $apply ? "APLICAR (--apply)\n" : "DRY-RUN (nenhuma alteração)\n";
if ($foco) { echo "foco: usuarios.id = {$foco}\n"; }
echo "\n";

// ---------------------------------------------------------------------------
// Carrega as duas bases
// ---------------------------------------------------------------------------
$associados = [];
foreach ($db->query('SELECT * FROM associados') as $r) { $associados[(int) $r['id']] = $r; }
$usuarios = [];
foreach ($db->query('SELECT * FROM usuarios') as $r) { $usuarios[(int) $r['id']] = $r; }
$usuariosPorAssociado = [];
foreach ($usuarios as $u) {
    if (notNull($u['associado_id'])) { $usuariosPorAssociado[(int) $u['associado_id']] = (int) $u['id']; }
}
$associadosPorUser = [];
foreach ($associados as $a) {
    if (notNull($a['user_id'])) { $associadosPorUser[(int) $a['user_id']] = (int) $a['id']; }
}

$correcoes = [];   // lista de [tabela, id, campo, de, para]
$avisos    = [];   // revisão manual

// ---------------------------------------------------------------------------
// 1) FKs inválidas
// ---------------------------------------------------------------------------
$upd = $db->prepare('UPDATE usuarios SET associado_id = NULL WHERE id = ?');
$upa = $db->prepare('UPDATE associados SET user_id = NULL WHERE id = ?');
foreach ($usuarios as $u) {
    if ($foco && (int) $u['id'] !== $foco) { continue; }
    if (notNull($u['associado_id']) && !isset($associados[(int) $u['associado_id']])) {
        $correcoes[] = ['usuarios', (int) $u['id'], 'associado_id', $u['associado_id'], 'NULL (associado inexistente)'];
        if ($apply) { $upd->execute([(int) $u['id']]); }
    }
}
foreach ($associados as $a) {
    if (notNull($a['user_id']) && !isset($usuarios[(int) $a['user_id']])) {
        $correcoes[] = ['associados', (int) $a['id'], 'user_id', $a['user_id'], 'NULL (usuário inexistente)'];
        if ($apply) { $upa->execute([(int) $a['id']]); }
    }
}

// ---------------------------------------------------------------------------
// 2) Link reverso ausente/inconsistente
// ---------------------------------------------------------------------------
$setUser  = $db->prepare('UPDATE associados SET user_id = ? WHERE id = ?');
$setAssoc = $db->prepare('UPDATE usuarios SET associado_id = ? WHERE id = ?');

// 2a) usuarios.associado_id -> associados.user_id
foreach ($usuarios as $u) {
    if ($foco && (int) $u['id'] !== $foco) { continue; }
    $aid = (int) ($u['associado_id'] ?? 0);
    if ($aid <= 0 || !isset($associados[$aid])) { continue; }
    $a = $associados[$aid];
    if ((int) ($a['user_id'] ?? 0) !== (int) $u['id']) {
        $de = notNull($a['user_id']) ? $a['user_id'] : 'NULL';
        $correcoes[] = ['associados', $aid, 'user_id', $de, $u['id'] . ' (link reverso)'];
        if ($apply) { $setUser->execute([(int) $u['id'], $aid]); }
    }
}
// 2b) associados.user_id -> usuarios.associado_id
foreach ($associados as $a) {
    $uid = (int) ($a['user_id'] ?? 0);
    if ($uid <= 0 || !isset($usuarios[$uid])) { continue; }
    if ($foco && $uid !== $foco) { continue; }
    $u = $usuarios[$uid];
    if ((int) ($u['associado_id'] ?? 0) !== (int) $a['id']) {
        $de = notNull($u['associado_id']) ? $u['associado_id'] : 'NULL';
        $correcoes[] = ['usuarios', $uid, 'associado_id', $de, $a['id'] . ' (link reverso)'];
        if ($apply) { $setAssoc->execute([(int) $a['id'], $uid]); }
    }
}

// ---------------------------------------------------------------------------
// 3) Espelhar dados pessoais do associado na conta (campos vazios/corrompidos)
// ---------------------------------------------------------------------------
foreach ($usuarios as $u) {
    if ($foco && (int) $u['id'] !== $foco) { continue; }
    $aid = (int) ($u['associado_id'] ?? 0);
    if ($aid > 0 && !isset($associados[$aid])) { $aid = 0; }
    if ($aid <= 0 && isset($associadosPorUser[(int) $u['id']])) { $aid = $associadosPorUser[(int) $u['id']]; }
    if ($aid <= 0) {
        // Conta sem cadastro de associado (equipe interna): mantém os próprios
        // dados, mas padroniza o formato do nome e limpa telefone corrompido.
        $camposSemVinculo = [];
        if (!notNull($u['sobrenome']) && strpos(trim((string) $u['nome']), ' ') !== false) {
            $partes = explode(' ', trim((string) $u['nome']), 2);
            $camposSemVinculo['nome'] = $partes[0];
            $camposSemVinculo['sobrenome'] = $partes[1];
        }
        if (in_array(trim((string) $u['telefone']), $TELEFONE_LIXO, true)) {
            $camposSemVinculo['telefone'] = null;
        }
        foreach ($camposSemVinculo as $campo => $valor) {
            $correcoes[] = ['usuarios', (int) $u['id'], $campo, var_export($u[$campo], true), $valor === null ? 'NULL (valor corrompido)' : $valor];
        }
        if ($apply && $camposSemVinculo) {
            $sets = []; $vals = [];
            foreach ($camposSemVinculo as $campo => $valor) { $sets[] = "`$campo` = ?"; $vals[] = $valor; }
            $vals[] = (int) $u['id'];
            $db->prepare('UPDATE usuarios SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
        }
        continue;
    }
    $a = $associados[$aid];

    $campos = [];
    // CPF
    if (!notNull($u['cpf']) && notNull($a['cpf'])) {
        $campos['cpf'] = $a['cpf'];
    } elseif (notNull($u['cpf']) && notNull($a['cpf']) && digitos($u['cpf']) !== digitos($a['cpf'])) {
        $avisos[] = "usuarios.id={$u['id']}: CPF divergente do associado {$aid} ({$u['cpf']} x {$a['cpf']}) — revisar manualmente.";
    }
    // Nome/sobrenome: "associados" é a fonte canônica dos dados da pessoa.
    // (a tabela usuarios costuma guardar o nome completo em "nome" e deixar
    // "sobrenome" NULL — é isso que faz a mesma pessoa aparecer com dois nomes)
    if (norm($u['nome'] . ' ' . $u['sobrenome']) !== norm($a['nome'] . ' ' . $a['sobrenome'])) {
        $campos['nome'] = $a['nome'];
        $campos['sobrenome'] = $a['sobrenome'];
    } elseif (!notNull($u['sobrenome']) && strpos(trim((string) $u['nome']), ' ') !== false) {
        // Mesmo nome, formato diferente: separa "nome" de "sobrenome" para que
        // as duas tabelas tenham exatamente o mesmo formato de exibição.
        $partes = explode(' ', trim((string) $u['nome']), 2);
        $campos['nome'] = $partes[0];
        $campos['sobrenome'] = $partes[1];
    }
    // Telefone vazio ou corrompido (2147483647 = estouro de INT no import)
    $telUsu = trim((string) $u['telefone']);
    if (notNull($a['telefone']) && (!notNull($u['telefone']) || in_array($telUsu, $TELEFONE_LIXO, true))) {
        $campos['telefone'] = $a['telefone'];
    }
    // E-mail: quando diverge, NÃO sobrescreve (é credencial de login) — só avisa
    if (notNull($u['email']) && notNull($a['email']) && norm($u['email']) !== norm($a['email'])
        && !isset($decisoes['emails'][(int) $u['id']])) {
        $avisos[] = "usuarios.id={$u['id']}: e-mail de login '{$u['email']}' difere do e-mail do cadastro '{$a['email']}' (associado {$aid}) — decidir qual é o login único.";
    }

    foreach ($campos as $campo => $valor) {
        $correcoes[] = ['usuarios', (int) $u['id'], $campo, var_export($u[$campo], true), $valor];
    }
    if ($apply && $campos) {
        $sets = [];
        $vals = [];
        foreach ($campos as $campo => $valor) { $sets[] = "`$campo` = ?"; $vals[] = $valor; }
        $vals[] = (int) $u['id'];
        $st = $db->prepare('UPDATE usuarios SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $st->execute($vals);
    }
}

// ---------------------------------------------------------------------------
// 4) Possíveis duplicidades SEM link (mesmo nome) — revisão manual
// ---------------------------------------------------------------------------
$nomesAssoc = [];
foreach ($associados as $a) { $nomesAssoc[norm($a['nome'] . ' ' . $a['sobrenome'])][] = $a; }
foreach ($usuarios as $u) {
    if ($foco && (int) $u['id'] !== $foco) { continue; }
    $nomeU = norm($u['nome'] . ' ' . $u['sobrenome']);
    $primeiro = explode(' ', $nomeU)[0] ?? '';
    if ($primeiro === '') { continue; }
    foreach ($associados as $a) {
        $nomeA = norm($a['nome'] . ' ' . $a['sobrenome']);
        $jaLinkado = ((int) ($u['associado_id'] ?? 0) === (int) $a['id']) || ((int) ($a['user_id'] ?? 0) === (int) $u['id']);
        if ($jaLinkado) { continue; }
        $mesmoNome = ($nomeU === $nomeA) || (strpos($nomeA, $nomeU) !== false) || (strpos($nomeU, $nomeA) !== false);
        $mesmoPrimeiro = (explode(' ', $nomeA)[0] ?? '') === $primeiro;
        $chaveDecisao = (int) $u['id'] . ':' . (int) $a['id'];
        if (isset($decisoes['pares'][$chaveDecisao])) { continue; }
        if ($mesmoNome) {
            $avisos[] = "Possível MESMA PESSOA: usuarios.id={$u['id']} '{$u['nome']} {$u['sobrenome']}' <{$u['email']}> x associados.id={$a['id']} '{$a['nome']} {$a['sobrenome']} <{$a['email']}>' (sem link) — confirmar e vincular.";
        } elseif ($mesmoPrimeiro) {
            $avisos[] = "Homônimo/possível duplicata: usuarios.id={$u['id']} '{$u['nome']}' <{$u['email']}> x associados.id={$a['id']} '{$a['nome']} {$a['sobrenome']}' <{$a['email']}> (CPF user=" . ($u['cpf'] ?: '—') . ", assoc=" . ($a['cpf'] ?: '—') . ') — revisar.';
        }
    }
}

// ---------------------------------------------------------------------------
// 5) Vínculos confirmados manualmente (--link=uid:aid)
//    Só aceita quando há evidência objetiva (mesmo e-mail ou mesmo CPF),
//    salvo com --force.
// ---------------------------------------------------------------------------
foreach ($vincular as [$uid, $aid]) {
    if (!isset($usuarios[$uid]) || !isset($associados[$aid])) {
        $avisos[] = "--link={$uid}:{$aid} ignorado: usuário ou associado inexistente.";
        continue;
    }
    $u = $usuarios[$uid];
    $a = $associados[$aid];
    $mesmoEmail = notNull($u['email']) && notNull($a['email']) && norm($u['email']) === norm($a['email']);
    $mesmoCpf   = notNull($u['cpf']) && notNull($a['cpf']) && digitos($u['cpf']) === digitos($a['cpf']);
    $cpfVazio   = !notNull($u['cpf']);
    if (!$force && !$mesmoEmail && !($mesmoCpf || $cpfVazio)) {
        $avisos[] = "--link={$uid}:{$aid} recusado: e-mail ('{$u['email']}' x '{$a['email']}') e CPF ('{$u['cpf']}' x '{$a['cpf']}') divergentes. Use --force após confirmar com a secretaria.";
        continue;
    }
    $correcoes[] = ['usuarios', $uid, 'associado_id', var_export($u['associado_id'], true), $aid . ' (vínculo confirmado)'];
    $correcoes[] = ['associados', $aid, 'user_id', var_export($a['user_id'], true), $uid . ' (vínculo confirmado)'];
    if ($apply) {
        $setAssoc->execute([$aid, $uid]);
        $setUser->execute([$uid, $aid]);
        AuditLog::registrar('IDENTIDADE_VINCULADA', 'usuarios', $uid, "usuarios.id={$uid} <-> associados.id={$aid} (email={$u['email']})");
    }
}

// ---------------------------------------------------------------------------
// 6) Mesclar contas duplicadas da MESMA pessoa (--merge=origem:destino)
//    A conta de destino é a canônica (mantém e-mail, CPF e senha). Todas as
//    referências da origem são transferidas, a linha de origem é copiada para
//    um arquivo de backup e só então removida.
// ---------------------------------------------------------------------------
foreach ($mesclar as [$origemId, $destinoId]) {
    if (!isset($usuarios[$origemId]) || !isset($usuarios[$destinoId])) {
        $avisos[] = "--merge={$origemId}:{$destinoId} ignorado: conta de origem ou destino inexistente.";
        continue;
    }
    if ($origemId === $destinoId) {
        $avisos[] = "--merge={$origemId}:{$destinoId} ignorado: origem e destino iguais.";
        continue;
    }
    $orig = $usuarios[$origemId];
    $dest = $usuarios[$destinoId];

    $mesmoNome = norm($orig['nome'] . ' ' . $orig['sobrenome']) === norm($dest['nome'] . ' ' . $dest['sobrenome']);
    $mesmoCpf  = notNull($orig['cpf']) && notNull($dest['cpf']) && digitos($orig['cpf']) === digitos($dest['cpf']);
    if (!$force && !$mesmoNome && !$mesmoCpf) {
        $avisos[] = "--merge={$origemId}:{$destinoId} recusado: nomes ('{$orig['nome']} {$orig['sobrenome']}' x '{$dest['nome']} {$dest['sobrenome']}') e CPFs ('{$orig['cpf']}' x '{$dest['cpf']}') divergentes. Use --force após confirmar.";
        continue;
    }

    echo "\n--- Mesclando usuarios.id={$origemId} em usuarios.id={$destinoId} ---\n";
    echo "  conta mantida : #{$destinoId} {$dest['nome']} {$dest['sobrenome']} <{$dest['email']}> cpf=" . ($dest['cpf'] ?: '—') . "\n";
    echo "  conta removida: #{$origemId} {$orig['nome']} {$orig['sobrenome']} <{$orig['email']}> cpf=" . ($orig['cpf'] ?: '—') . "\n";

    // a) transferir referências
    foreach (REFERENCIAS_USUARIO as [$tab, $col]) {
        if (!colunaExiste($db, $tab, $col)) { continue; }
        $st = $db->prepare("SELECT COUNT(*) FROM `$tab` WHERE `$col` = ?");
        $st->execute([$origemId]);
        $n = (int) $st->fetchColumn();
        if ($n <= 0) { continue; }
        $correcoes[] = [$tab, $origemId, $col, $origemId, $destinoId . " ({$n} registro(s) transferido(s))"];
        if ($apply) {
            $up = $db->prepare("UPDATE `$tab` SET `$col` = ? WHERE `$col` = ?");
            $up->execute([$destinoId, $origemId]);
        }
    }

    // b) se a conta removida estava vinculada a um associado e a mantida não
    //    estiver, o vínculo passa para a conta mantida
    $assocDaOrigem = (int) ($orig['associado_id'] ?? 0);
    if ($assocDaOrigem > 0 && isset($associados[$assocDaOrigem]) && (int) ($dest['associado_id'] ?? 0) <= 0) {
        $correcoes[] = ['usuarios', $destinoId, 'associado_id', 'NULL', $assocDaOrigem . ' (herdado da conta mesclada)'];
        $correcoes[] = ['associados', $assocDaOrigem, 'user_id', var_export($associados[$assocDaOrigem]['user_id'], true), $destinoId];
        if ($apply) {
            $setAssoc->execute([$assocDaOrigem, $destinoId]);
            $setUser->execute([$destinoId, $assocDaOrigem]);
        }
    }

    // c) backup + remoção da conta duplicada
    $correcoes[] = ['usuarios', $origemId, '(linha inteira)', 'existe', 'REMOVIDA (duplicata mesclada em #' . $destinoId . ')'];
    if ($apply) {
        $arquivo = __DIR__ . '/../tmp/merge_usuarios_' . $origemId . '_em_' . $destinoId . '_' . date('Ymd_His') . '.json';
        @file_put_contents($arquivo, json_encode(['removido_em' => date('c'), 'motivo' => 'conta duplicada da mesma pessoa', 'origem' => $orig, 'destino' => $dest], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        // libera vínculo (colunas UNIQUE) antes de remover
        $db->prepare('UPDATE usuarios SET associado_id = NULL WHERE id = ?')->execute([$origemId]);
        $db->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$origemId]);
        AuditLog::registrar('IDENTIDADE_MESCLADA', 'usuarios', $origemId, "conta #{$origemId} mesclada em #{$destinoId}; backup=" . basename($arquivo));
        echo "  backup da linha removida: {$arquivo}\n";
    }
}

// ---------------------------------------------------------------------------
// Relatório
// ---------------------------------------------------------------------------
echo "--- Correções ", $apply ? 'APLICADAS' : 'propostas', " (", count($correcoes), ") ---\n";
foreach ($correcoes as $c) {
    printf("  %-11s id=%-4s %-14s %s -> %s\n", $c[0] . '.', $c[1], $c[2], (string) $c[3], (string) $c[4]);
    if ($apply) {
        AuditLog::registrar('IDENTIDADE_RECONCILIADA', $c[0], (int) $c[1], "campo={$c[2]} de={$c[3]} para={$c[4]}");
    }
}
if (!$correcoes) { echo "  (nada a corrigir)\n"; }

$avisos = array_values(array_unique($avisos));
echo "\n--- Revisão manual (", count($avisos), ") ---\n";
foreach ($avisos as $av) { echo '  - ', $av, "\n"; }
if (!$avisos) { echo "  (nada pendente)\n"; }

if ($decisoes['pares'] || $decisoes['emails']) {
    echo "\n--- Decisões registradas (dev-utils/identidades_ignoradas.json) ---\n";
    foreach ($decisoes['pares'] as $chave => $motivo) {
        [$uidDec, $aidDec] = explode(':', $chave);
        echo "  usuarios.id={$uidDec} x associados.id={$aidDec}: não são a mesma pessoa — {$motivo}\n";
    }
    foreach ($decisoes['emails'] as $uidDec => $motivo) {
        echo "  usuarios.id={$uidDec}: e-mail de login diferente do cadastro é intencional — {$motivo}\n";
    }
}

echo "\n--- Pares de identidade (usuarios <-> associados) ---\n";
printf("  %-5s %-26s %-24s %-8s %-8s %s\n", 'uid', 'usuario.nome', 'usuario.email', 'u.assoc', 'a.user', 'associado');
foreach ($usuarios as $u) {
    if ($foco && (int) $u['id'] !== $foco) { continue; }
    $aid = (int) ($u['associado_id'] ?? 0);
    $a = ($aid && isset($associados[$aid])) ? $associados[$aid] : null;
    if (!$a && isset($associadosPorUser[(int) $u['id']])) { $a = $associados[$associadosPorUser[(int) $u['id']]]; }
    printf("  %-5s %-26s %-24s %-8s %-8s %s\n",
        $u['id'],
        substr((string) $u['nome'] . ' ' . (string) $u['sobrenome'], 0, 25),
        substr((string) $u['email'], 0, 23),
        $aid ?: '—',
        $a ? ($a['user_id'] ?: '—') : '—',
        $a ? ('assoc ' . $a['id'] . ' ' . $a['nome'] . ' ' . $a['sobrenome']) : '(sem cadastro de associado)'
    );
}
echo "\n", $apply ? "Concluído (alterações aplicadas).\n" : "Dry-run concluído. Rode com --apply para aplicar as correções propostas.\n";
