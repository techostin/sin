<?php
/**
 * Ficha de Inspeção (PDF) — modelo de checklist do roteiro para impressão.
 *
 * Gera um PDF imprimível da Ficha de Inspeção baseada no roteiro/template,
 * com campos em branco para o técnico preencher manualmente quando não tiver
 * acesso a computador no campo.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    die('Acesso negado. Faça login para gerar a ficha de inspeção.');
}

// Garante saída limpa para o PDF (ignora warnings/resíduos do ambiente)
while (ob_get_level() > 0) { ob_end_clean(); }
ob_start();

// mPDF usa pcre para processar o HTML das tabelas grandes
ini_set('pcre.backtrack_limit', '5000000');
ini_set('pcre.recursion_limit', '500000');

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../../classes/User.php';
require_once __DIR__ . '/../../../classes/RoteiroInspecao.php';

use Mpdf\Mpdf;

$roteiroId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($roteiroId <= 0) {
    die('Roteiro inválido.');
}

$user = new User();
if (!$user->isLoggedIn()) {
    die('Acesso não autorizado.');
}

$userNivel = $_SESSION['user_nivel'] ?? NIVEL_USUARIO;
if ($userNivel > NIVEL_GESTOR) {
    die('Acesso restrito a gestores/técnicos.');
}

$roteiro = (new RoteiroInspecao())->getById($roteiroId);
if (!$roteiro) {
    die('Roteiro não encontrado.');
}

$checklist     = is_array($roteiro['checklist']) ? $roteiro['checklist'] : [];
$itensCriticos = is_array($roteiro['itens_criticos']) ? $roteiro['itens_criticos'] : [];

$logoData = is_file(SINPORG_LOGO_PATH)
    ? 'data:image/png;base64,' . base64_encode(file_get_contents(SINPORG_LOGO_PATH))
    : '';

$linhasChecklist = '';
$n = 0;
foreach ($checklist as $item) {
    $n++;
    $idCrit = (int)($item['id'] ?? $n);
    $texto   = htmlspecialchars((string)($item['item'] ?? ''), ENT_QUOTES, 'UTF-8');
    $cat     = htmlspecialchars((string)($item['categoria'] ?? 'Geral'), ENT_QUOTES, 'UTF-8');
    $peso    = (int)($item['peso'] ?? 10);
    $critico = in_array($idCrit, $itensCriticos, true) || !empty($item['obrigatorio']);
    $critTxt = $critico ? 'SIM' : '';

    $linhasChecklist .= '<tr>'
        . '<td class="td-num">' . $n . '</td>'
        . '<td class="td-item">' . $texto . '</td>'
        . '<td class="td-cat">' . $cat . '</td>'
        . '<td class="td-peso">' . $peso . '</td>'
        . '<td class="td-crit">' . $critTxt . '</td>'
        . '<td class="td-conf">[ ] Sim&nbsp;&nbsp;&nbsp;[ ] Não&nbsp;&nbsp;&nbsp;[ ] NA</td>'
        . '<td class="td-obs"></td>'
        . '</tr>';
}

$html = '
<style>
    body { font-family: helvetica; font-size: 10pt; color: #1f2937; }
    .topo { width: 100%; border-collapse: collapse; }
    .topo td { vertical-align: middle; }
    .logo { width: 46mm; }
    .tit-ficha { font-size: 17pt; font-weight: bold; color: #14532d; text-align: center; letter-spacing: 1px; }
    .sub-ficha { font-size: 10pt; color: #4b5563; text-align: center; margin-top: 1mm; }
    .linha-div { border-bottom: 1.4px solid #16a34a; margin: 4mm 0 4mm; }
    .cartao { border: 1px solid #d1d5db; border-left: 4px solid #16a34a; padding: 4mm 5mm; margin-bottom: 4mm; }
    .cartao-tit { font-weight: bold; font-size: 11pt; color: #14532d; margin-bottom: 2mm; }
    .info-linha { width: 100%; border-collapse: collapse; font-size: 10pt; }
    .info-linha td { padding: 2.2mm 1mm; }
    .campo { border-bottom: 1px solid #9ca3af; min-width: 40mm; }
    .label-campo { color: #4b5563; font-size: 9pt; }
    table.ficha { width: 100%; border-collapse: collapse; font-size: 9pt; }
    table.ficha th { background: #166534; color: #fff; text-align: left; padding: 2.4mm 1.6mm; font-size: 9pt; }
    table.ficha td { border: 1px solid #cbd5e1; padding: 3mm 1.6mm; vertical-align: top; line-height: 1.35; }
    .td-num   { width: 8mm; text-align: center; }
    .td-item  { width: 66mm; }
    .td-cat   { width: 23mm; }
    .td-peso  { width: 11mm; text-align: center; }
    .td-crit  { width: 12mm; text-align: center; font-weight: bold; color: #b91c1c; }
    .td-conf  { width: 32mm; font-size: 8.5pt; }
    .td-obs   { width: 28mm; min-height: 12mm; }
    .assinaturas { width: 100%; border-collapse: collapse; margin-top: 8mm; }
    .assinaturas td { width: 50%; text-align: center; vertical-align: bottom; padding: 0 4mm; }
    .ass-linha { border-bottom: 1.4px solid #111827; height: 16mm; }
    .ass-nome { font-size: 9.5pt; color: #4b5563; margin-top: 2mm; }
    .rodape-nota { font-size: 8pt; color: #6b7280; margin-top: 6mm; text-align: center; }
</style>

<table class="topo">
    <tr>
        <td class="logo">' . ($logoData ? '<img src="' . $logoData . '" style="width:44mm;">' : '') . '</td>
        <td>
            <div class="tit-ficha">FICHA DE INSPEÇÃO</div>
            <div class="sub-ficha">' . htmlspecialchars($roteiro['tipo'], ENT_QUOTES, 'UTF-8') . ' — Sistema Integrado de Produção Orgânica</div>
        </td>
    </tr>
</table>
<div class="linha-div"></div>

<div class="cartao">
    <div class="cartao-tit">' . htmlspecialchars($roteiro['nome'], ENT_QUOTES, 'UTF-8') . '</div>
    ' . (!empty($roteiro['codigo']) ? '<div style="font-size:9pt;">Código: <strong>' . htmlspecialchars($roteiro['codigo'], ENT_QUOTES, 'UTF-8') . '</strong></div>' : '') . '
    ' . (!empty($roteiro['descricao']) ? '<div style="font-size:9pt;color:#374151;margin-top:1mm;">' . htmlspecialchars($roteiro['descricao'], ENT_QUOTES, 'UTF-8') . '</div>' : '') . '
    ' . (!empty($roteiro['objetivo']) ? '<div style="font-size:9pt;color:#374151;margin-top:1mm;"><strong>Objetivo:</strong> ' . htmlspecialchars($roteiro['objetivo'], ENT_QUOTES, 'UTF-8') . '</div>' : '') . '
    <div style="font-size:9pt;margin-top:1mm;">Pontuação mínima para aprovação: <strong>' . (int)$roteiro['pontuacao_minima'] . '%</strong></div>
</div>

<div class="cartao">
    <div class="cartao-tit">Dados da Inspeção</div>
    <table class="info-linha">
        <tr>
            <td><span class="label-campo">Produtor(a):</span> <span class="campo">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></td>
            <td><span class="label-campo">Data:</span> ____/____/______</td>
        </tr>
        <tr>
            <td><span class="label-campo">Propriedade:</span> <span class="campo">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></td>
            <td><span class="label-campo">Município / UF:</span> _</td>
        </tr>
        <tr>
            <td><span class="label-campo">Técnico Responsável:</span> <span class="campo">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></td>
            <td><span class="label-campo">Nº da Visita:</span> _</td>
        </tr>
    </table>
</div>

<table class="ficha">
    <thead>
        <tr>
            <th class="td-num">Nº</th>
            <th class="td-item">Requisito / Pergunta</th>
            <th class="td-cat">Categoria</th>
            <th class="td-peso">Peso</th>
            <th class="td-crit">Crít.</th>
            <th class="td-conf">Conformidade</th>
            <th class="td-obs">Observações</th>
        </tr>
    </thead>
    <tbody>
        ' . ($linhasChecklist ?: '<tr><td colspan="7">Roteiro sem itens de checklist.</td></tr>') . '
    </tbody>
</table>

<table class="assinaturas">
    <tr>
        <td class="ass-linha"></td>
        <td class="ass-linha"></td>
    </tr>
    <tr>
        <td class="ass-nome">Assinatura do Técnico Responsável</td>
        <td class="ass-nome">Assinatura do Produtor(a)</td>
    </tr>
</table>

<div class="rodape-nota">Ficha de inspeção gerada pelo SINPORG. Itens marcados como <strong>Crítico (Crít.)</strong> geram reprovação automática caso não sejam atendidos.</div>
';

$mpdf = new Mpdf([
    'mode'          => 'utf-8',
    'format'        => 'A4',
    'orientation'   => 'P',
    'margin_left'   => 12,
    'margin_right'  => 12,
    'margin_top'    => 12,
    'margin_bottom' => 16,
    'margin_footer' => 8,
    'default_font'  => 'helvetica',
]);

$mpdf->SetTitle('Ficha de Inspeção - ' . $roteiro['nome']);
$mpdf->SetAuthor('SINPORG');
$mpdf->SetFooter('SINPORG - Ficha de Inspeção | {PAGENO} de {nb}');
$mpdf->WriteHTML($html);
$ext = !empty($roteiro['codigo']) ? preg_replace('/[^A-Za-z0-9-_]/', '', $roteiro['codigo']) : 'roteiro';
while (ob_get_level() > 0) { ob_end_clean(); }
$mpdf->Output('ficha_inspecao_' . ($ext ?: $roteiroId) . '.pdf', 'I');