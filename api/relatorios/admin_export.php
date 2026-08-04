?php
/**
 * API de Exportação de Relatórios Administrativos
 * 
 * Gera relatórios em Excel (XLSX) ou PDF para os tipos:
 * - usuarios
 * - associados
 * - associacoes
 * - fornecedores
 * - contas
 * 
 * Os relatórios gerados por Gestor/Admin incluem o nome e assinatura 
 * do usuário responsável.
 * 
 * Uso: /api/relatorios/admin_export?tipo=excel&relatorio=usuarios&...
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/ReportManager.php';

// Verificar autenticação e permissão (apenas Admin/Gestor)
$user = new User();
if (!$user->isLoggedIn() || !$user->hasPermission(NIVEL_GESTOR)) {
    http_response_code(403);
    die('Acesso restrito a gestores e administradores.');
}

$tipo_relatorio = $_GET['relatorio'] ?? '';
$formato = $_GET['tipo'] ?? 'pdf';

// Validar tipo de relatório
$tipos_validos = ['usuarios', 'associados', 'associacoes', 'fornecedores', 'contas'];
if (!in_array($tipo_relatorio, $tipos_validos)) {
    die('Tipo de relatório inválido.');
}

// Preparar ReportManager e filtros (remover parâmetros de controle)
$reportManager = new ReportManager();
$filtros = $_GET;
// 'tipo' no endpoint indica formato (pdf/excel/html) e não deve ser passado como filtro de dados
unset($filtros['tipo'], $filtros['formato'], $filtros['relatorio']);

// DEBUG: checar existência de registros na tabela usuarios
try {
    $db = Database::getInstance()->getConnection();
    $stmtCount = $db->query('SELECT COUNT(*) as c FROM usuarios');
    $countRow = $stmtCount->fetch(PDO::FETCH_ASSOC);
    error_log('admin_export.php - usuarios_count: ' . ($countRow['c'] ?? '0'));
} catch (Exception $e) {
    error_log('admin_export.php - erro ao contar usuarios: ' . $e->getMessage());
}

// Carregar dados do usuário responsável
$responsavel_nome = $_SESSION['user_nome'] ?? 'Usuário';
$responsavel_id = $_SESSION['user_id'] ?? 0;

// Obter dados do relatório
switch ($tipo_relatorio) {
    case 'usuarios':
        $dados = $reportManager->getUsuariosReport($filtros);
        $colunas = ['ID', 'Nome', 'CPF', 'RG', 'E-mail', 'Tipo', 'Status', 'Último Acesso'];
        $campos = ['id', 'nome', 'cpf', 'rg', 'email', 'tipo', 'status', 'ultimo_acesso'];
        $titulo = 'Relatório de Usuários';
        break;
    case 'associados':
        $dados = $reportManager->getAssociadosReport($filtros);
        $colunas = ['ID', 'Nome', 'RG', 'E-mail', 'Telefone', 'Cidade', 'UF', 'Status'];
        $campos = ['id', 'nome_completo', 'rg', 'email', 'telefone', 'cidade_nome', 'estado_uf', 'status'];
        $titulo = 'Relatório de Associados';
        break;
    case 'associacoes':
        $dados = $reportManager->getAssociacoesReport();
        $colunas = ['ID', 'Nome', 'CNPJ', 'Cidade', 'UF', 'Total Associados', 'Total Propriedades'];
        $campos = ['id', 'nome', 'cnpj', 'cidade', 'uf', 'total_associados', 'total_propriedades'];
        $titulo = 'Relatório de Associações';
        break;
    case 'fornecedores':
        $dados = $reportManager->getFornecedoresReport($filtros);
        $colunas = ['ID', 'Razão Social', 'Nome Fantasia', 'CNPJ/CPF', 'Cidade', 'UF', 'Telefone', 'E-mail'];
        $campos = ['id', 'razao_social', 'nome_fantasia', 'cnpj_cpf', 'cidade_nome', 'estado_uf', 'telefone', 'email'];
        $titulo = 'Relatório de Fornecedores';
        break;
    case 'contas':
        $dados = $reportManager->getContasReport($filtros);
        $colunas = ['ID', 'Descrição', 'Tipo', 'Valor (R$)', 'Vencimento', 'Status', 'Responsável'];
        $campos = ['id', 'descricao', 'tipo_formatado', 'valor', 'data_vencimento', 'status_formatado', 'responsavel_nome'];
        $titulo = 'Relatório de Contas';
        break;
    default:
        die('Tipo de relatório não reconhecido.');
}

// Normalizar dados: garantir que campos compostos existam
$dados = array_map(function($item) {
    if (!isset($item['nome_completo'])) {
        $item['nome_completo'] = ($item['nome'] ?? '') . ' ' . ($item['sobrenome'] ?? '');
    }
    if (isset($item['valor'])) {
        $item['valor'] = 'R$ ' . number_format((float)$item['valor'], 2, ',', '.');
    }
    if (!empty($item['data_vencimento']) && $item['data_vencimento'] !== '-') {
        $item['data_vencimento'] = date('d/m/Y', strtotime($item['data_vencimento']));
    }
    if (!empty($item['ultimo_acesso'])) {
        $item['ultimo_acesso'] = date('d/m/Y H:i', strtotime($item['ultimo_acesso']));
    }
    if (isset($item['status'])) {
        $item['status'] = ucfirst($item['status']);
    }
    return $item;
}, $dados);

if ($formato === 'html') {
    renderizarHTMLRelatorio($dados, $colunas, $campos, $titulo, $responsavel_nome, $tipo_relatorio);
    exit;
}

try {
    require_once __DIR__ . '/../../vendor/autoload.php';
    if ($formato === 'excel') {
        gerarExcelRelatorio($dados, $colunas, $campos, $titulo, $responsavel_nome);
    } else {
        gerarPDFRelatorio($dados, $colunas, $campos, $titulo, $responsavel_nome);
    }
} catch (Exception $e) {
    die('Erro ao gerar relatório: ' . $e->getMessage());
}

/**
 * Renderiza o relatório como página HTML standalone (aberta em nova aba)
 */
function renderizarHTMLRelatorio($dados, $colunas, $campos, $titulo, $responsavel_nome, $tipo_relatorio) {
    $total = count($dados);
    $data_emissao = date('d/m/Y H:i:s');
    $linhas = '';
    foreach ($dados as $item) {
        $linhas .= '<tr>';
        foreach ($campos as $campo) {
            $valor = $item[$campo] ?? '-';
            $linhas .= '<td>' . htmlspecialchars((string)$valor) . '</td>';
        }
        $linhas .= '</tr>';
    }
    $cabecalhos = '';
    foreach ($colunas as $col) {
        $cabecalhos .= '<th>' . htmlspecialchars($col) . '</th>';
    }
    echo <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$titulo} - Sistema de Relatórios</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
@media print { .no-print { display: none !important; } }
body { background: #f5f6fa; }
</style>
</head>
<body>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <div>
            <a href="javascript:window.close()" class="btn btn-outline-secondary btn-sm me-2">&larr; Fechar</a>
            <button onclick="window.print()" class="btn btn-outline-primary btn-sm"><i class="bi bi-printer me-1"></i>Imprimir</button>
            <a href="?tipo=excel&relatorio={$tipo_relatorio}" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i> Excel</a>
            <a href="?tipo=pdf&relatorio={$tipo_relatorio}" class="btn btn-outline-danger btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <h4 class="mb-0">{$titulo}</h4>
            <small class="text-muted">Responsável: {$responsavel_nome} &mdash; Emissão: {$data_emissao} &mdash; Total: {$total} registro(s)</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-dark"><tr>{$cabecalhos}</tr></thead>
                    <tbody>{$linhas}</tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white text-end small text-muted">
            <p class="mb-0">Assinatura do Responsável: _________________________________________</p>
            <p class="mb-0 fw-bold">{$responsavel_nome}</p>
        </div>
    </div>
</div>
</body>
</html>
HTML;
}

/**
 * Gera e exporta relatório em Excel
 */
function gerarExcelRelatorio($dados, $colunas, $campos, $titulo, $responsavel_nome) {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $totalColunas = count($colunas);
    $ultimaLetra = chr(64 + $totalColunas);

    // Metadados
    $spreadsheet->getProperties()
        ->setCreator($responsavel_nome)
        ->setTitle($titulo)
        ->setDescription("Relatório gerado por {$responsavel_nome}");

    // === LINHA 1: Título ===
    $sheet->setCellValue('A1', $titulo);
    $sheet->mergeCells('A1:' . $ultimaLetra . '1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    // === LINHA 2: Responsável ===
    $sheet->setCellValue('A2', "Responsável: {$responsavel_nome}");
    $sheet->mergeCells('A2:' . $ultimaLetra . '2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A2')->getFont()->setItalic(true);

    // === LINHA 3: Data de emissão ===
    $sheet->setCellValue('A3', 'Emissão: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A3:' . $ultimaLetra . '3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(10);

    // === LINHA 5: Cabeçalhos ===
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
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
    ]);

    // === LINHA 6+: Dados ===
    $row = 6;
    foreach ($dados as $item) {
        $col = 'A';
        foreach ($campos as $campo) {
            $value = $item[$campo] ?? '-';
            $sheet->setCellValue($col . $row, $value);
            $col++;
        }
        $row++;
    }

    // === Assinatura do responsável (duas linhas após os dados) ===
    $assinaturaRow = $row + 2;
    $sheet->setCellValue("A{$assinaturaRow}", "Assinatura do Responsável:");
    $sheet->getStyle("A{$assinaturaRow}")->getFont()->setBold(true)->setSize(11);

    $sheet->setCellValue("A" . ($assinaturaRow + 1), "_________________________________________");
    $sheet->getStyle("A" . ($assinaturaRow + 1))->getFont()->setSize(14);

    $sheet->setCellValue("A" . ($assinaturaRow + 2), $responsavel_nome);
    $sheet->getStyle("A" . ($assinaturaRow + 2))->getFont()->setBold(true)->setSize(11);

    // Aplicar bordas na tabela de dados
    $dataEnd = $row - 1;
    if ($dataEnd >= 6) {
        $rangeData = 'A' . $headerRow . ':' . $ultimaLetra . $dataEnd;
        $sheet->getStyle($rangeData)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
        ]);
    }

    // Auto-ajuste das colunas
    foreach (range('A', $ultimaLetra) as $colLetter) {
        $sheet->getColumnDimension($colLetter)->setAutoSize(true);
    }

    // Configurar download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . slugifyTitulo($titulo) . '_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Gera e exibe/baixa relatório em PDF
 */
function gerarPDFRelatorio($dados, $colunas, $campos, $titulo, $responsavel_nome) {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 20,
        'margin_bottom' => 25,
        'margin_header' => 10,
        'margin_footer' => 15
    ]);

    $totalColunas = count($colunas);

    // Montar cabeçalho da tabela
    $theadRows = '<tr>';
    foreach ($colunas as $header) {
        $theadRows .= '<th>' . htmlspecialchars($header) . '</th>';
    }
    $theadRows .= '</tr>';

    // Montar corpo da tabela
    $tbodyRows = '';
    if (empty($dados)) {
        $tbodyRows .= '<tr><td colspan="' . $totalColunas . '" style="text-align:center;color:#999;">Nenhum registro encontrado.</td></tr>';
    } else {
        foreach ($dados as $item) {
            $tbodyRows .= '<tr>';
            foreach ($campos as $campo) {
                $value = $item[$campo] ?? '-';
                $tbodyRows .= '<td>' . htmlspecialchars((string)$value) . '</td>';
            }
            $tbodyRows .= '</tr>';
        }
    }

    $html = '
    <style>
        body { font-family: Arial, sans-serif; font-size: 9pt; color: #333; }
        .header { text-align: center; border-bottom: 3px solid #1B5E20; padding-bottom: 10px; margin-bottom: 15px; }
        .title { font-size: 18pt; font-weight: bold; color: #1B5E20; }
        .subtitle { font-size: 10pt; color: #666; margin-top: 3px; }
        .responsavel { font-size: 10pt; color: #333; margin-top: 5px; font-style: italic; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 8.5pt; }
        th { background-color: #1B5E20; color: white; border: 1px solid #1B5E20; padding: 6px 4px; text-align: left; font-weight: bold; }
        td { border: 1px solid #ddd; padding: 4px; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .assinatura { margin-top: 40px; text-align: center; }
        .assinatura-linha { width: 350px; margin: 0 auto 5px auto; }
        .assinatura-risco { border-top: 2px solid #333; padding-top: 5px; }
        .assinatura-nome { font-weight: bold; font-size: 10pt; }
        .assinatura-cargo { font-size: 9pt; color: #666; }
        .footer { text-align: center; font-size: 7.5pt; color: #999; border-top: 1px solid #ddd; padding-top: 8px; }
    </style>

    <div class="header">
        <div class="title">' . htmlspecialchars($titulo) . '</div>
        <div class="subtitle">Sistema Integrado de Produção Orgânica</div>
        <div class="responsavel">Responsável: ' . htmlspecialchars($responsavel_nome) . '</div>
        <div class="subtitle">Emissão: ' . date('d/m/Y H:i:s') . '</div>
    </div>

    <table>
        <thead>' . $theadRows . '</thead>
        <tbody>' . $tbodyRows . '</tbody>
    </table>

    <div class="assinatura">
        <div class="assinatura-linha">
            <div class="assinatura-risco">
                <div class="assinatura-nome">' . htmlspecialchars($responsavel_nome) . '</div>
                <div class="assinatura-cargo">Responsável Técnico</div>
            </div>
        </div>
    </div>

    <div class="footer">
        Relatório gerado pelo SINPORG - Página {PAGENO} de {nbpg} | ' . date('d/m/Y H:i:s') . '
    </div>';

    $mpdf->SetTitle($titulo);
    $mpdf->SetAuthor($responsavel_nome);
    $mpdf->SetCreator('SINPORG');

    $mpdf->WriteHTML($html);
    $mpdf->Output(slugifyTitulo($titulo) . '_' . date('Ymd_His') . '.pdf', 'I');
    exit;
}

/**
 * Converte título para slug (nome de arquivo seguro)
 */
function slugifyTitulo($texto) {
    $texto = preg_replace('/[^\w\s]/u', '', $texto);
    $texto = preg_replace('/\s+/', '_', trim($texto));
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = str_replace([' ', '--', '__'], '_', $texto);
    return $texto;
}

