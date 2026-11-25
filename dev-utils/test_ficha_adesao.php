<?php
/**
 * Teste automatizado do fluxo da Ficha de Adesão (CLI).
 * 1. status inicial (sem ficha) 2. rascunho 3. submissão 4. pendentes
 * 5. aprovação (numero_cadastro + data_entrada + associado Ativo)
 * 6. recusa com parecer 7. reenvio 8. limpeza dos dados de teste.
 */
error_reporting(E_ALL);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/FichaAdesao.php';

@session_start();
$db = Database::getInstance()->getConnection();
$model = new FichaAdesao();

$assoc = $db->query("SELECT id, nome FROM associados WHERE status = 'ativo' AND id NOT IN (SELECT associado_id FROM ficha_adesao) ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$assoc) {
    echo "ERRO: nenhum associado disponível para teste
";
    exit(1);
}
$aid = (int)$assoc['id'];
echo "Associado de teste: #{$aid} {$assoc['nome']}
";

$base = [
    'tipo_associado' => 'agricultor_familiar',
    'nome_completo' => $assoc['nome'] . ' Teste',
    'data_nascimento' => '1985-06-15',
    'cpf' => '111.444.777-35',
    'rg_orgao_emissor' => '1234567890 SSP/RS',
    'municipio' => 'Porto Alegre',
    'estado' => 'RS',
    'cep' => '90000-000',
    'zona' => 'rural',
    'telefone_whatsapp' => '(51) 99999-0000',
    'area_total_ha' => '12,5',
    'situacao_fundiaria' => 'propria',
    'tipo_certificacao' => 'ocs',
    'sistemas_producao' => ['horticultura', 'fruticultura'],
    'canais_comercializacao' => ['feiras_livres', 'paa_pnae'],
    'produtos' => [
        ['produto_cultura' => 'Alface', 'variedade_especie' => 'Crespa', 'producao_estimada' => '500 pés/mês', 'unidade' => 'un.', 'destino_principal' => 'Feira'],
        ['produto_cultura' => 'Morango', 'variedade_especie' => 'San Andreas', 'producao_estimada' => '200 kg', 'unidade' => 'kg', 'destino_principal' => 'CSA'],
    ],
    'aceite_termos' => 1,
];

// 1) status inicial
$st = $model->statusDoAssociado($aid);
echo "1) status inicial: " . $st['status'] . ($st['status'] === 'sem_ficha' ? " OK" : " FALHOU") . "
";

// 2) salvar rascunho
$r = $model->salvar($aid, $base, false);
echo "2) salvar rascunho: " . ($r['success'] ? "OK (id {$r['ficha_id']})" : "FALHOU: {$r['message']}") . "
";
$st = $model->statusDoAssociado($aid);
echo "   status após rascunho: " . $st['status'] . "
";

// 3) submeter
$r = $model->salvar($aid, $base, true);
echo "3) submeter: " . ($r['success'] ? "OK" : "FALHOU: {$r['message']}") . "
";
$st = $model->statusDoAssociado($aid);
echo "   status após submissão: " . $st['status'] . " | produtos: " . count($st['produtos']) . "
";

// 4) listar pendentes contém a ficha
$pend = $model->listarPendentes();
$encontrou = false;
foreach ($pend as $p) { if ((int)$p['associado_id'] === $aid) { $encontrou = true; break; } }
echo "4) aparece em listarPendentes: " . ($encontrou ? "OK" : "FALHOU") . "
";

// 5) aprovar
$ficha = $model->getByAssociadoId($aid);
$r = $model->aprovar((int)$ficha['id'], 1);
echo "5) aprovar: " . ($r['success'] ? "OK ({$r['message']})" : "FALHOU: {$r['message']}") . "
";
$ficha = $model->getByAssociadoId($aid);
echo "   numero_cadastro=" . var_export($ficha['numero_cadastro'], true) . " data_entrada=" . var_export($ficha['data_entrada'], true) . "
";
$assocRow = $db->query("SELECT data_admissao, status_associado FROM associados WHERE id = " . $aid)->fetch(PDO::FETCH_ASSOC);
echo "   associado: data_admissao=" . var_export($assocRow['data_admissao'], true) . " status_associado=" . $assocRow['status_associado'] . "
";
$st = $model->statusDoAssociado($aid);
echo "   status após aprovação: " . $st['status'] . "
";

// 6) tentar editar aprovada (deve bloquear)
$r = $model->salvar($aid, $base, true);
echo "6) editar aprovada bloqueada: " . ($r['success'] === false ? "OK" : "FALHOU") . "
";

// limpar a ficha de teste do associado para testar recusa com outro
$db->exec("DELETE FROM ficha_adesao WHERE associado_id = " . $aid);
$db->exec("UPDATE associados SET data_admissao = NULL, status_associado = 'Ativo' WHERE id = " . $aid);

// 7) recusar com parecer
$r = $model->salvar($aid, $base, true);
$ficha = $model->getByAssociadoId($aid);
$r = $model->recusar((int)$ficha['id'], 2, 'Documentação incompleta. Anexar comprovante.');
echo "7) recusar: " . ($r['success'] ? "OK" : "FALHOU: {$r['message']}") . "
";
$st = $model->statusDoAssociado($aid);
echo "   status: " . $st['status'] . " | parecer: " . $st['ficha']['parecer_diretoria'] . "
";

// 8) reenviar após recusa
$r = $model->salvar($aid, $base, true);
$st = $model->statusDoAssociado($aid);
echo "8) reenvio após recusa: status=" . $st['status'] . " parecer_limpo=" . var_export($st['ficha']['parecer_diretoria'], true) . "
";

// 9) limpeza final
$db->exec("DELETE FROM ficha_adesao WHERE associado_id = " . $aid);
echo "9) limpeza: " . ((int)$db->query("SELECT COUNT(*) FROM ficha_adesao WHERE associado_id = " . $aid)->fetchColumn() === 0 ? "OK" : "FALHOU") . "
";
echo "FIM DOS TESTES
";
