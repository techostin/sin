<?php
/**
 * Gerador de Relatórios de Conformidade Orgânica (Fase B).
 *
 * Uso: /relatorios-conformidade/gerar?relatorio=<slug>&formato=pdf|excel|html
 *      &data_inicio=&data_fim=&status=&tipo=&associado_id=
 *
 * Formatos: pdf (mPDF), excel (PhpSpreadsheet), html (página imprimível).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../../classes/User.php';
require_once __DIR__ . '/../../../classes/ReportConformidade.php';
require_once __DIR__ . '/../../../includes/report_logo.php';

$user = new User();
if (!$user->isLoggedIn()) {
    http_response_code(401);
    die('Acesso restrito. Faça login para gerar relatórios.');
}

$catalogo = ReportConformidade::catalogo();
$slug = $_GET['relatorio'] ?? '';
$formato = $_GET['formato'] ?? 'pdf';

if (!isset($catalogo[$slug])) {
    http_response_code(400);
    die('Relatório inválido. Slugs válidos: ' . implode(', ', array_keys($catalogo)));
}

if (!in_array($formato, ['pdf', 'excel', 'html'], true)) {
    http_response_code(400);
    die('Formato inválido. Formatos válidos: pdf, excel, html');
}

// Filtros vindos da URL
$filtros = [];
foreach (['data_inicio', 'data_fim', 'status', 'tipo', 'associado_id', 'acao', 'user_id'] as $campo) {
    if (!empty($_GET[$campo])) {
        $filtros[$campo] = $_GET[$campo];
    }
}

// Usuários não-gestores visualizam apenas os próprios dados
if (!$user->hasPermission(NIVEL_GESTOR)) {
    $filtros['associado_id'] = (int)$user->getAssociadoId();
}

$metodo = $catalogo[$slug]['metodo'];
$dados = ReportConformidade::$metodo($filtros);

$responsavel_nome = $_SESSION['user_nome'] ?? 'Usuário';
$dados['responsavel'] = $responsavel_nome;

require_once __DIR__ . '/../../../vendor/autoload.php';

if ($formato === 'pdf') {
    gerarPDF($dados, $slug);
} elseif ($formato === 'excel') {
    gerarExcel($dados, $slug);
} else {
    gerarHTML($dados, $slug, $filtros);
}

/**
 * Gera o PDF do relatório com a logo SINPORG no topo central.
 */
function gerarPDF($dados, $slug) {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 20,
        'margin_bottom' => 20,
        'margin_header' => 10,
        'margin_footer' => 12,
    ]);

    $html = '<style>' . sinporg_report_header_css() . '</style>';
    $html .= sinporg_report_header($dados['titulo'], $dados['descricao'], $dados['responsavel'] ?? '');

    // Resumo
    if (!empty($dados['resumo'])) {
        $html .= '<table class="sinporg-resumo"><tr>';
        foreach ($dados['resumo'] as $box) {
            $html .= '<td><div class="n">' . htmlspecialchars((string)$box['valor']) . '</div><div class="l">' . htmlspecialchars($box['label']) . '</div></td>';
        }
        $html .= '</tr></table>';
    }

    // Tabelas
    foreach ($dados['tabelas'] as $tabela) {
        $html .= '<div class="sinporg-tabela-titulo">' . htmlspecialchars($tabela['titulo']) . '</div>';
        $html .= '<table class="sinporg-table"><thead><tr>';
        foreach ($tabela['colunas'] as $col) {
            $html .= '<th>' . htmlspecialchars($col) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        if (empty($tabela['dados'])) {
            $html .= '<tr><td colspan="' . count($tabela['colunas']) . '" style="text-align:center;color:#999;">Nenhum registro encontrado para os filtros informados.</td></tr>';
        } else {
            foreach ($tabela['dados'] as $linha) {
                $html .= '<tr>';
                foreach ($tabela['campos'] as $campo) {
                    $html .= '<td>' . htmlspecialchars((string)($linha[$campo] ?? '-')) . '</td>';
                }
                $html .= '</tr>';
            }
        }
        $html .= '</tbody></table>';
    }

    // Assinatura do responsável
    $html .= '<div class="sinporg-assinatura">'
        . '<div class="sinporg-assinatura-risco">'
        . '<div style="font-weight:bold; font-size:10pt;">' . htmlspecialchars($dados['responsavel'] ?? '') . '</div>'
        . '<div style="font-size:9pt; color:#666;">Responsável Técnico</div>'
        . '</div></div>';

    $html .= sinporg_report_footer();

    $mpdf->SetTitle($dados['titulo']);
    $mpdf->SetAuthor($dados['responsavel'] ?? 'SINPORG');
    $mpdf->SetCreator('SINPORG - Sistema Integrado da Produção Orgânica');

    $mpdf->WriteHTML($html);
    $mpdf->Output('relatorio_' . $slug . '_' . date('Ymd_His') . '.pdf', 'I');
    exit;
}

/**
 * Gera o relatório em Excel, uma aba por tabela, com a logo no cabeçalho.
 */
function gerarExcel($dados, $slug) {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $spreadsheet->getProperties()
        ->setCreator($dados['responsavel'] ?? 'SINPORG')
        ->setTitle($dados['titulo'])
        ->setDescription($dados['descricao'] ?? '');

    $logoPath = sinporg_logo_file();
    $primeiraAba = true;

    foreach ($dados['tabelas'] as $idx => $tabela) {
        if ($primeiraAba) {
            $sheet = $spreadsheet->getActiveSheet();
            $primeiraAba = false;
        } else {
            $sheet = $spreadsheet->createSheet();
        }
        $sheet->setTitle(substr(($tabela['titulo'] ?? 'Relatório ' . ($idx + 1)), 0, 31));

        // Impressão em A4 paisagem
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);

        $colunas = $tabela['colunas'];
        $campos = $tabela['campos'];
        $totalColunas = count($colunas);
        $ultimaLetra = $totalColunas > 0 ? chr(64 + $totalColunas) : 'A';

        // Logo (imagem) centralizada no topo
        if (file_exists($logoPath)) {
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setName('SINPORG');
            $drawing->setPath($logoPath);
            $drawing->setHeight(50);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(5);
            $drawing->setOffsetY(3);
            $drawing->setWorksheet($sheet);
        }

        // Linha 1: Título
        $sheet->setCellValue('A1', $dados['titulo']);
        $sheet->mergeCells('A1:' . $ultimaLetra . '1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(42);

        // Linha 2: Descrição da tabela
        $sheet->setCellValue('A2', $tabela['titulo']);
        $sheet->mergeCells('A2:' . $ultimaLetra . '2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

        // Linha 3: Responsável e emissão
        $sheet->setCellValue('A3', "Responsável: " . ($dados['responsavel'] ?? '') . "  |  Emissão: " . date('d/m/Y H:i:s'));
        $sheet->mergeCells('A3:' . $ultimaLetra . '3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(9);

        // Linha 5: Cabeçalhos
        $headerRow = 5;
        $col = 'A';
        foreach ($colunas as $header) {
            $sheet->setCellValue($col . $headerRow, $header);
            $col++;
        }
        $rangeHeaders = 'A' . $headerRow . ':' . $ultimaLetra . $headerRow;
        $sheet->getStyle($rangeHeaders)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B5E20']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
        ]);

        // Dados
        $row = 6;
        foreach ($tabela['dados'] as $item) {
            $col = 'A';
            foreach ($campos as $campo) {
                $value = $item[$campo] ?? '-';
                $sheet->setCellValueExplicit($col . $row, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $col++;
            }
            $row++;
        }

        $dataEnd = $row - 1;
        if ($dataEnd >= $headerRow) {
            $sheet->getStyle('A' . $headerRow . ':' . $ultimaLetra . $dataEnd)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            ]);
        }

        // Auto-ajuste das colunas (com limite para não estourar a largura)
        foreach (range('A', $ultimaLetra) as $colLetter) {
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Assinatura
        $assinaturaRow = $row + 2;
        $sheet->setCellValue("A{$assinaturaRow}", 'Assinatura do Responsável:');
        $sheet->getStyle("A{$assinaturaRow}")->getFont()->setBold(true)->setSize(11);
        $sheet->setCellValue("A" . ($assinaturaRow + 1), '_________________________________________');
        $sheet->setCellValue("A" . ($assinaturaRow + 2), $dados['responsavel'] ?? '');
        $sheet->getStyle("A" . ($assinaturaRow + 2))->getFont()->setBold(true)->setSize(11);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="relatorio_' . $slug . '_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Gera uma página HTML standalone imprimível.
 */
function gerarHTML($dados, $slug, $filtros) {
    $logoUrl = sinporg_logo_url();
    $resumoHtml = '';
    if (!empty($dados['resumo'])) {
        $resumoHtml .= '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:14px 0;">';
        foreach ($dados['resumo'] as $box) {
            $resumoHtml .= '<div style="flex:1;min-width:150px;text-align:center;border:1px solid #c8d6c0;border-radius:8px;background:#f6faf3;padding:10px;">'
                . '<div style="font-size:22px;font-weight:bold;color:#1B5E20;">' . htmlspecialchars((string)$box['valor']) . '</div>'
                . '<div style="font-size:11px;color:#666;text-transform:uppercase;">' . htmlspecialchars($box['label']) . '</div></div>';
        }
        $resumoHtml .= '</div>';
    }

    $tabelasHtml = '';
    foreach ($dados['tabelas'] as $tabela) {
        $tabelasHtml .= '<h5 style="color:#1B5E20;margin-top:22px;">' . htmlspecialchars($tabela['titulo']) . '</h5>'
            . '<div class="table-responsive"><table class="table table-striped table-bordered table-sm"><thead><tr>';
        foreach ($tabela['colunas'] as $col) {
            $tabelasHtml .= '<th>' . htmlspecialchars($col) . '</th>';
        }
        $tabelasHtml .= '</tr></thead><tbody>';
        if (empty($tabela['dados'])) {
            $tabelasHtml .= '<tr><td colspan="' . count($tabela['colunas']) . '" class="text-center text-muted">Nenhum registro encontrado.</td></tr>';
        } else {
            foreach ($tabela['dados'] as $linha) {
                $tabelasHtml .= '<tr>';
                foreach ($tabela['campos'] as $campo) {
                    $tabelasHtml .= '<td>' . htmlspecialchars((string)($linha[$campo] ?? '-')) . '</td>';
                }
                $tabelasHtml .= '</tr>';
            }
        }
        $tabelasHtml .= '</tbody></table></div>';
    }

    echo <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>{$dados['titulo']} - SINPORG</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
@page { size: A4 landscape; margin: 10mm; }
@media print { .no-print { display: none !important; } }
body { background: #f5f6fa; }
</style>
</head>
<body>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <a href="javascript:window.close()" class="btn btn-outline-secondary btn-sm me-2">&larr; Fechar</a>
        <button onclick="window.print()" class="btn btn-outline-primary btn-sm"><i class="bi bi-printer me-1"></i> Imprimir</button>
    </div>
    <div class="card shadow-sm">
        <div class="card-header bg-white text-center py-4" style="border-bottom:3px solid #1B5E20;">
            <img src="{$logoUrl}" alt="SINPORG" style="height:62px; margin-bottom:6px;" />
            <h4 class="mb-1 fw-bold" style="color:#1B5E20;">{$dados['titulo']}</h4>
            <div class="text-muted">{$dados['descricao']}</div>
            <div class="text-muted small">Responsável: {$dados['responsavel']} &mdash; Emissão: " . date('d/m/Y H:i:s') . "</div>
        </div>
        <div class="card-body">
            {$resumoHtml}
            {$tabelasHtml}
        </div>
        <div class="card-footer bg-white text-center small text-muted">
            <p class="mb-0 mt-3">Assinatura do Responsável: _________________________________________</p>
            <p class="mb-0 fw-bold">{$dados['responsavel']}</p>
        </div>
    </div>
</div>
</body>
</html>
HTML;
    exit;
}
