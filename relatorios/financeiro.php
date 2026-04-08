<?php
ob_start(); // Inicia o buffer de saída para evitar que espaços ou warnings disparem headers precocemente
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/ReportManager.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = new User();
if (!$user->isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login');
    exit();
}

// Apenas admins e gestores podem acessar
$userNivel = $_SESSION['user_nivel'] ?? 3;
if ($userNivel > 2) {
    $_SESSION['erro'] = 'Você não tem permissão para acessar o relatório financeiro.';
    header('Location: ' . SITE_URL . '/relatorios/');
    exit();
}

$tipo = $_GET['tipo'] ?? '';
if (!in_array($tipo, ['pdf', 'excel'])) {
    header('Location: ' . SITE_URL . '/relatorios/');
    exit();
}

// ─── Filtros ─────────────────────────────────────────────────────────────────
$nomeEntidade = trim($_GET['nome_entidade'] ?? '');
if (empty($nomeEntidade)) {
    $nomeEntidade = 'Sistema Integrado da Produção Orgânica';
}

$filtros = [
    'tipo'        => $_GET['filtro_tipo']  ?? '',   // 'Receita' | 'Despesa' | ''
    'data_inicio' => $_GET['data_inicio']  ?? '',
    'data_fim'    => $_GET['data_fim']     ?? '',
];

// ─── Buscar dados diretamente da tabela contas ───────────────────────────────
// Estrutura real: id, nome, tipo (Receita|Despesa), valor, data
try {
    $reportManager = new ReportManager();
    $dados = $reportManager->getFinanceiroReport($filtros);
} catch (Exception $e) {
    error_log('Financeiro report error: ' . $e->getMessage());
    $dados = [];
}

// ─── Calcular totais ──────────────────────────────────────────────────────────
$totalReceitas = 0;
$totalDespesas = 0;
foreach ($dados as $row) {
    if (strtolower($row['tipo']) === 'receita') {
        $totalReceitas += (float)($row['valor'] ?? 0);
    } else {
        $totalDespesas += (float)($row['valor'] ?? 0);
    }
}
$saldo = $totalReceitas - $totalDespesas;

$periodoLabel = '';
if (!empty($filtros['data_inicio']) && !empty($filtros['data_fim'])) {
    $periodoLabel = 'Período: ' . date('d/m/Y', strtotime($filtros['data_inicio']))
                  . ' a '      . date('d/m/Y', strtotime($filtros['data_fim']));
} else {
    $periodoLabel = 'Todos os registros';
}

// ════════════════════════════════════════════════════════════
//  PDF  (mPDF)
// ════════════════════════════════════════════════════════════
if ($tipo === 'pdf') {

    ob_start();
    $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><style>
        body  { font-family: sans-serif; font-size: 9pt; color: #222; margin: 0; padding: 0; }
        h1    { font-size: 15pt; text-align: center; color: #1a5276; margin: 0 0 4px 0; }
        .sub  { text-align: center; color: #555; font-size: 8.5pt; margin-bottom: 12px; }
        .boxes{ width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .boxes td { padding: 8px 14px; font-size: 10pt; font-weight: bold; border-radius: 4px; }
        .b-rec{ background:#d5f5e3; color:#1e8449; }
        .b-des{ background:#fadbd8; color:#922b21; }
        .b-sal{ background:#d6eaf8; color:#1a5276; }
        table.main { width:100%; border-collapse:collapse; font-size:8.5pt; }
        table.main th { background:#1a5276; color:#fff; padding:5px 8px; text-align:left; font-size:8pt; }
        table.main td { padding:5px 8px; border-bottom:1px solid #e5e5e5; vertical-align:top; }
        table.main tr:nth-child(even) td { background:#f4f6f7; }
        .t-rec{ color:#1e8449; font-weight:bold; }
        .t-des{ color:#922b21; font-weight:bold; }
        .r    { text-align:right; }
        .ent  { text-align:center; font-size:13pt; font-weight:bold; color:#1a5276; margin:0 0 2px 0; }
        h1    { font-size:15pt; text-align:center; color:#1a5276; margin:0 0 2px 0; }
        .sipo { text-align:center; font-size:9pt; color:#555; font-style:italic; margin:0 0 10px 0; }
        .sub  { text-align:center; color:#555; font-size:8.5pt; margin-bottom:12px; }
        .ent-sep { border:none; border-top:2px solid #1a5276; margin:6px auto 8px; width:60%; }
    </style></head><body>';

    $html .= '<div class="ent">' . htmlspecialchars($nomeEntidade) . '</div>';
    $html .= '<h1>RELATÓRIO FINANCEIRO</h1>';
    $html .= '<p class="sipo">Sistema Integrado da Produção Orgânica</p>';
    $html .= '<p class="sub">' . htmlspecialchars($periodoLabel) . ' &nbsp;|&nbsp; Gerado em: ' . date('d/m/Y H:i') . '</p>';

    // Caixas de resumo
    $html .= '<table class="boxes"><tr>
        <td class="b-rec">&#128200; Receitas: R$ ' . number_format($totalReceitas, 2, ',', '.') . '</td>
        <td class="b-des">&#128201; Despesas: R$ ' . number_format($totalDespesas, 2, ',', '.') . '</td>
        <td class="b-sal">&#128178; Saldo: R$ '    . number_format($saldo,          2, ',', '.') . '</td>
    </tr></table>';

    // Tabela — todas as colunas da tabela 'contas'
    $html .= '<table class="main"><thead><tr>
        <th>ID</th><th>Nome</th><th>Tipo</th><th class="r">Valor (R$)</th><th>Data</th>
    </tr></thead><tbody>';

    if (empty($dados)) {
        $html .= '<tr><td colspan="5" style="text-align:center;padding:20px;color:#888;">Nenhum lançamento encontrado.</td></tr>';
    } else {
        foreach ($dados as $i => $row) {
            $tipoLower = strtolower($row['tipo'] ?? '');
            $cls       = $tipoLower === 'receita' ? 't-rec' : 't-des';
            $dataFmt   = !empty($row['data']) ? date('d/m/Y', strtotime($row['data'])) : '-';
            $valorFmt  = 'R$ ' . number_format((float)($row['valor'] ?? 0), 2, ',', '.');

            $html .= '<tr>
                <td>' . htmlspecialchars($row['id'] ?? ($i + 1)) . '</td>
                <td>' . htmlspecialchars($row['nome'] ?? '-') . '</td>
                <td class="' . $cls . '">' . htmlspecialchars($row['tipo'] ?? '-') . '</td>
                <td class="r ' . $cls . '">' . $valorFmt . '</td>
                <td>' . $dataFmt . '</td>
            </tr>';
        }
    }

    $html .= '</tbody></table>';
    $html .= '<div class="foot">Gerado por: ' . htmlspecialchars($_SESSION['user_nome'] ?? 'Sistema') . ' &mdash; ' . htmlspecialchars($nomeEntidade) . ' &mdash; ' . date('d/m/Y H:i') . '</div>';
    $html .= '</body></html>';
    ob_end_clean();

    $mpdf = new \Mpdf\Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'A4',
        'margin_left'   => 12,
        'margin_right'  => 12,
        'margin_top'    => 16,
        'margin_bottom' => 16,
        'default_font'  => 'helvetica',
    ]);
    $mpdf->WriteHTML($html);
    $mpdf->Output('relatorio_financeiro_' . date('Ymd_His') . '.pdf', 'I');
    exit();
}

// ════════════════════════════════════════════════════════════
//  EXCEL  (PhpSpreadsheet)
// ════════════════════════════════════════════════════════════
if ($tipo === 'excel') {
    file_put_contents(__DIR__.'/debug.log', "Iniciando Excel...\n", FILE_APPEND);
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Financeiro');

    // Título
    $sheet->setCellValue('A1', 'RELATÓRIO FINANCEIRO — ' . strtoupper($nomeEntidade));
    $sheet->mergeCells('A1:E1');
    $sheet->getStyle('A1')->applyFromArray([
        'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1A5276']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    $sheet->setCellValue('A2', $periodoLabel . '   |   Gerado em: ' . date('d/m/Y H:i'));
    $sheet->mergeCells('A2:E2');
    $sheet->getStyle('A2')->applyFromArray([
        'font'      => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '555555']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    // Resumo financeiro
    // Resumo financeiro
    $resumoStyle = ['font' => ['bold' => true], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]];
    $sheet->setCellValue('A4', 'Total Receitas');
    $sheet->setCellValue('B4', $totalReceitas);
    $sheet->getStyle('A4:B4')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => '1E8449']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D5F5E3']]
    ]);
    $sheet->getStyle('A4')->applyFromArray($resumoStyle);
    $sheet->getStyle('B4')->getNumberFormat()->setFormatCode('R$ #,##0.00');

    $sheet->setCellValue('C4', 'Total Despesas');
    $sheet->setCellValue('D4', $totalDespesas);
    $sheet->getStyle('C4:D4')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => '922B21']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FADBD8']]
    ]);
    $sheet->getStyle('C4')->applyFromArray($resumoStyle);
    $sheet->getStyle('D4')->getNumberFormat()->setFormatCode('R$ #,##0.00');

    $saldoColor = $saldo >= 0 ? '1A5276' : '922B21';
    $sheet->setCellValue('E4', 'Saldo: R$ ' . number_format($saldo, 2, ',', '.'));
    $sheet->getStyle('E4')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => $saldoColor]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D6EAF8']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);

    // Cabeçalhos da tabela
    $headers = ['ID', 'Nome', 'Tipo', 'Valor (R$)', 'Data'];
    $cols    = ['A', 'B', 'C', 'D', 'E'];
    foreach ($headers as $idx => $h) {
        $sheet->setCellValue($cols[$idx] . '6', $h);
    }
    $sheet->getStyle('A6:E6')->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A5276']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
    ]);

    // Dados
    $rowIdx = 7;
    foreach ($dados as $i => $row) {
        $dataFmt  = !empty($row['data']) ? date('d/m/Y', strtotime($row['data'])) : '-';
        $valor    = (float)($row['valor'] ?? 0);
        $tipoStr  = $row['tipo'] ?? '-';
        $corTipo  = strtolower($tipoStr) === 'receita' ? '1E8449' : '922B21';

        $sheet->setCellValue('A' . $rowIdx, $row['id'] ?? ($i + 1));
        $sheet->setCellValue('B' . $rowIdx, $row['nome'] ?? '-');
        $sheet->setCellValue('C' . $rowIdx, $tipoStr);
        $sheet->setCellValue('D' . $rowIdx, $valor);
        $sheet->setCellValue('E' . $rowIdx, $dataFmt);

        $sheet->getStyle('C' . $rowIdx)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => $corTipo]]]);
        $sheet->getStyle('D' . $rowIdx)->applyFromArray(['font' => ['color' => ['rgb' => $corTipo]]]);
        $sheet->getStyle('D' . $rowIdx)->getNumberFormat()->setFormatCode('R$ #,##0.00');
        $sheet->getStyle('D' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        if ($i % 2 === 0) {
            $sheet->getStyle('A' . $rowIdx . ':E' . $rowIdx)->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F4F6F7']]
            ]);
        }
        $sheet->getStyle('A' . $rowIdx . ':E' . $rowIdx)->applyFromArray([
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DEDEDE']]]
        ]);

        $rowIdx++;
    }

    // Linha de saldo final
    $sheet->setCellValue('D' . $rowIdx, 'SALDO FINAL:');
    $sheet->setCellValue('E' . $rowIdx, $saldo);
    $sheet->getStyle('D' . $rowIdx . ':E' . $rowIdx)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => $saldoColor]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EBF5FB']],
    ]);
    $sheet->getStyle('E' . $rowIdx)->getNumberFormat()->setFormatCode('R$ #,##0.00');

    foreach ($cols as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    file_put_contents(__DIR__.'/debug.log', "Tabela preenchida.\n", FILE_APPEND);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $filename = 'relatorio_financeiro_' . date('Ymd_His') . '.xlsx';
    $temp_file = tempnam(sys_get_temp_dir(), 'phpxls');

    try {
        $writer = new Xlsx($spreadsheet);
        $writer->save($temp_file);
    } catch (\Exception $e) {
        die("Erro ao gerar o arquivo Excel: " . $e->getMessage());
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Content-Length: ' . filesize($temp_file));

    readfile($temp_file);
    unlink($temp_file);
    exit();
}