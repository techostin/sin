<?php
/**
 * API de Relatórios Administrativos - Exportação Excel e PDF
 *
 * endpoint: /api/relatorios_admin.php?relatorio=usuarios&formato=excel
 *
 * Formatos: excel, pdf
 * Relatórios: usuarios, associados, associacoes, fornecedores, contas, insumos, atividades, lotes, propriedades
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/ReportManager.php';

// Verificar autenticação e permissão
$user = new User();
if (!$user->isLoggedIn() || !$user->hasPermission(NIVEL_GESTOR)) {
    http_response_code(401);
    die(json_encode(['error' => 'Acesso não autorizado']));
}

// Obter dados do usuário responsável
$responsavel_nome = $_SESSION['user_nome'] ?? 'Usuário não identificado';
$responsavel_id = $_SESSION['user_id'] ?? 0;
$responsavel_tipo = $_SESSION['user_nivel'] ?? NIVEL_USUARIO;

// Buscar assinatura digital do usuário na tabela associados/usuarios
$db = Database::getInstance()->getConnection();
$tabela = User::tabelaUsuarioSegura(null, 'usuarios'); // B-01: whitelist de tabelas
$assinatura = null;
try {
    $stmt = $db->prepare("SELECT assinatura FROM {$tabela} WHERE id = ?");
    $stmt->execute([$responsavel_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['assinatura'])) {
        $assinatura = $row['assinatura'];
    }
} catch (Exception $e) {
    // ignora erro
}

$relatorio = $_GET['relatorio'] ?? '';
$formato = $_GET['formato'] ?? 'pdf';

// Filtros
$filtros = [];
foreach (['data_inicio', 'data_fim', 'status', 'tipo', 'cidade', 'tipo_fornecedor'] as $campo) {
    if (!empty($_GET[$campo])) {
        $filtros[$campo] = $_GET[$campo];
    }
}

try {
    $reportManager = new ReportManager();

    // Mapear relatórios para seus respectivos métodos e colunas
    $relatorios_map = [
        'usuarios' => [
            'metodo' => 'getUsuariosReport',
            'colunas' => ['ID', 'Nome', 'E-mail', 'Tipo', 'Status', 'Último Acesso'],
            'campos' => ['id', 'nome', 'email', 'tipo', 'status', 'ultimo_acesso'],
            'titulo' => 'Relatório de Usuários',
            'descricao' => 'Listagem completa de usuários cadastrados no sistema'
        ],
        'associados' => [
            'metodo' => 'getAssociadosReport',
            'colunas' => ['ID', 'Nome', 'E-mail', 'Telefone', 'CPF/CNPJ', 'Cidade', 'UF', 'Status', 'Associação'],
            'campos' => ['id', 'nome_completo', 'email', 'telefone', 'cpf_cnpj', 'cidade_nome', 'estado_uf', 'status', 'associacao_nome'],
            'titulo' => 'Relatório de Associados',
            'descricao' => 'Listagem completa de associados e produtores'
        ],
        'associacoes' => [
            'metodo' => 'getAssociacoesReport',
            'colunas' => ['ID', 'Nome', 'CNPJ', 'Cidade', 'UF', 'Total Associados', 'Total Propriedades'],
            'campos' => ['id', 'nome', 'cnpj', 'cidade_nome', 'estado_uf', 'total_associados', 'total_propriedades'],
            'titulo' => 'Relatório de Associações',
            'descricao' => 'Listagem completa de associações e cooperativas'
        ],
        'fornecedores' => [
            'metodo' => 'getFornecedoresReport',
            'colunas' => ['ID', 'Razão Social', 'Nome Fantasia', 'CNPJ/CPF', 'Cidade', 'UF', 'Telefone', 'E-mail'],
            'campos' => ['id', 'razao_social', 'nome_fantasia', 'cnpj_cpf', 'cidade_nome', 'estado_uf', 'telefone', 'email'],
            'titulo' => 'Relatório de Fornecedores',
            'descricao' => 'Listagem completa de fornecedores cadastrados'
        ],
        'contas' => [
            'metodo' => 'getContasReport',
            'colunas' => ['ID', 'Descrição', 'Tipo', 'Valor', 'Vencimento', 'Status', 'Responsável'],
            'campos' => ['id', 'descricao', 'tipo_formatado', 'valor', 'data_vencimento', 'status_formatado', 'responsavel_nome'],
            'titulo' => 'Relatório de Contas',
            'descricao' => 'Listagem completa de contas a pagar e receber'
        ],
        'insumos' => [
            'metodo' => 'getInsumosReport',
            'colunas' => ['ID', 'Nome', 'Fornecedor', 'Qtd. Estoque', 'Unidade', 'Validade', 'Associado'],
            'campos' => ['id', 'nome', 'fornecedor_nome', 'quantidade_estoque', 'unidade_medida', 'data_validade', 'associado_nome'],
            'titulo' => 'Relatório de Insumos',
            'descricao' => 'Listagem completa de insumos cadastrados'
        ],
        'atividades' => [
            'metodo' => 'getAtividadesReport',
            'colunas' => ['ID', 'Data', 'Atividade', 'Categoria', 'Status', 'Propriedade', 'Lavoura', 'Associado'],
            'campos' => ['id', 'data_atividade', 'nome', 'categoria_nome', 'status', 'propriedade_nome', 'lavoura_nome', 'associado_nome'],
            'titulo' => 'Relatório de Atividades Diárias',
            'descricao' => 'Listagem de atividades diárias do caderno de campo'
        ],
        'lotes' => [
            'metodo' => 'getLotesReport',
            'colunas' => ['ID', 'Lote', 'Produto', 'Qtd. Produzida', 'Unidade', 'Data Produção', 'Validade'],
            'campos' => ['id', 'nome_lote', 'produto_nome', 'quantidade_produzida', 'unidade_medida', 'data_producao', 'data_validade_lote'],
            'titulo' => 'Relatório de Lotes de Produção',
            'descricao' => 'Listagem de lotes de produção registrados'
        ],
        'propriedades' => [
            'metodo' => 'getPropriedadesReport',
            'colunas' => ['ID', 'Propriedade', 'Responsável', 'Cidade', 'UF', 'Área Total (ha)', 'Total Lavouras'],
            'campos' => ['id', 'propriedade', 'responsavel_nome', 'cidade', 'uf', 'area_total', 'total_lavouras'],
            'titulo' => 'Relatório de Propriedades',
            'descricao' => 'Listagem completa de propriedades cadastradas'
        ]
    ];

    // Validar relatório solicitado
    if (!isset($relatorios_map[$relatorio])) {
        http_response_code(400);
        die(json_encode(['error' => 'Tipo de relatório inválido. Tipos válidos: ' . implode(', ', array_keys($relatorios_map))]));
    }

    $config = $relatorios_map[$relatorio];
    $metodo = $config['metodo'];
    $dados = $reportManager->$metodo($filtros);
    $colunas = $config['colunas'];
    $campos = $config['campos'];
    $titulo = $config['titulo'];

    // Validar formato
    if (!in_array($formato, ['excel', 'pdf'], true)) {
        http_response_code(400);
        die(json_encode(['error' => 'Formato inválido. Formatos válidos: excel, pdf']));
    }

    // Normalizar dados
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

    require_once __DIR__ . '/../vendor/autoload.php';

    if ($formato === 'excel') {
        gerarExcelRelatorio($dados, $colunas, $campos, $titulo, $responsavel_nome);
    } else {
        gerarPDFRelatorio($dados, $colunas, $campos, $titulo, $responsavel_nome);
    }

} catch (Exception $e) {
    error_log('Erro ao gerar relatório: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['error' => 'Erro ao gerar relatório', 'message' => $e->getMessage()]));
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

/**
 * Busca a certificadora do usuário logado e resolve os caminhos das logos.
 * @return array{0: string|null, 1: string|null} [logo certificadora, selo SisOrg]
 */
function buscarLogosCertificadora() {
    $db = Database::getInstance()->getConnection();
    $userId = $_SESSION['user_id'] ?? 0;
    $cert = null;
    try {
        // 1. Via certificadora_id na tabela usuarios
        $stmt = $db->prepare(
            "SELECT c.* FROM certificadoras c
             INNER JOIN usuarios u ON u.certificadora_id = c.id
             WHERE u.id = ? LIMIT 1"
        );
        $stmt->execute([$userId]);
        $cert = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cert) {
            // 2. Fallback: via gestor_id na tabela certificadoras
            $stmt2 = $db->prepare("SELECT * FROM certificadoras WHERE gestor_id = ? LIMIT 1");
            $stmt2->execute([$userId]);
            $cert = $stmt2->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log('Erro ao buscar certificadora: ' . $e->getMessage());
    }

    // Logo da certificadora
    $logoCert = null;
    if ($cert) {
        $logoFile = $cert['logo_filename'] ?? null;
        if ($logoFile) {
            $path = realpath(__DIR__ . '/../uploads/certificadoras/' . $logoFile);
            if (!$path || !is_file($path)) {
                $path = realpath(__DIR__ . '/../uploads/' . $logoFile);
            }
            if ($path && is_file($path)) {
                $logoCert = $path;
            }
        }
    }
    // Fallback: logo genérica do Brasil Orgânico
    if (!$logoCert) {
        $fallback = realpath(__DIR__ . '/../assets/img/logo-organico-brasil.svg');
        if ($fallback && is_file($fallback)) {
            $logoCert = $fallback;
        }
    }

    // Selo SisOrg
    $seloPath = realpath(__DIR__ . '/../uploads/selo_SisOrg.jpeg');
    $logoSelo = ($seloPath && is_file($seloPath)) ? $seloPath : null;

    return [$logoCert, $logoSelo];
}

/**
 * Gera e exporta relatório em Excel (XLSX)
 */
function gerarExcelRelatorio($dados, $colunas, $campos, $titulo, $responsavel_nome) {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $totalColunas = count($colunas);
    $ultimaLetra = chr(64 + $totalColunas);

    $spreadsheet->getProperties()
        ->setCreator($responsavel_nome)
        ->setTitle($titulo)
        ->setDescription("Relatório gerado por {$responsavel_nome}");

    // Logos da certificadora (esquerda) e selo SisOrg (direita)
    [$logoCert, $logoSelo] = buscarLogosCertificadora();

    // Linha 1: Título
    $sheet->setCellValue('A1', $titulo);
    $sheet->mergeCells('A1:' . $ultimaLetra . '1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    // Inserir logos no cabeçalho (linha 1, esquerda e direita)
    if ($logoCert && is_file($logoCert)) {
        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setName('Logo Certificadora');
        $drawing->setPath($logoCert);
        $drawing->setHeight(45);
        $drawing->setCoordinates('A1');
        $drawing->setOffsetX(5);
        $drawing->setOffsetY(5);
        $drawing->setWorksheet($sheet);
    }
    if ($logoSelo && is_file($logoSelo)) {
        $drawing2 = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing2->setName('Selo SisOrg');
        $drawing2->setPath($logoSelo);
        $drawing2->setHeight(45);
        $drawing2->setCoordinates($ultimaLetra . '1');
        $drawing2->setOffsetX(-50);
        $drawing2->setOffsetY(5);
        $drawing2->setWorksheet($sheet);
    }

    // Logo SINPORG centralizada no topo
    $logoSinporg = realpath(__DIR__ . '/../img/logo_Sinporg-2.png');
    if ($logoSinporg && is_file($logoSinporg)) {
        $drawing3 = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing3->setName('SINPORG');
        $drawing3->setPath($logoSinporg);
        $drawing3->setHeight(50);
        $deslocamento = $totalColunas > 2 ? floor(($totalColunas - 1) / 2) : 0;
        $colCentral = chr(65 + $deslocamento);
        $drawing3->setCoordinates($colCentral . '1');
        $drawing3->setOffsetX(30);
        $drawing3->setOffsetY(5);
        $drawing3->setWorksheet($sheet);
    }

    // Linha 2: Responsável
    $sheet->setCellValue('A2', "Responsável: {$responsavel_nome}");
    $sheet->mergeCells('A2:' . $ultimaLetra . '2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A2')->getFont()->setItalic(true);

    // Linha 3: Data de emissão
    $sheet->setCellValue('A3', 'Emissão: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A3:' . $ultimaLetra . '3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(10);

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
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
    ]);

    // Linha 6+: Dados
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

    // Assinatura do responsável
    $assinaturaRow = $row + 2;
    $sheet->setCellValue("A{$assinaturaRow}", "Assinatura do Responsável:");
    $sheet->getStyle("A{$assinaturaRow}")->getFont()->setBold(true)->setSize(11);

    $sheet->setCellValue("A" . ($assinaturaRow + 1), "_________________________________________");
    $sheet->getStyle("A" . ($assinaturaRow + 1))->getFont()->setSize(14);

    $sheet->setCellValue("A" . ($assinaturaRow + 2), $responsavel_nome);
    $sheet->getStyle("A" . ($assinaturaRow + 2))->getFont()->setBold(true)->setSize(11);

    // Bordas na tabela de dados
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

    // Logos da certificadora (esquerda) e selo SisOrg (direita)
    [$logoCert, $logoSelo] = buscarLogosCertificadora();

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

    // Imagens do cabeçalho (logo esquerda, selo direita)
    $imgLogo = $logoCert ? '<img src="' . $logoCert . '" style="height:45px; float:left; margin-right:10px;">' : '';
    $imgSelo = $logoSelo ? '<img src="' . $logoSelo . '" style="height:45px; float:right; margin-left:10px;">' : '';

    $html = '
    <style>
        body { font-family: Arial, sans-serif; font-size: 9pt; color: #333; }
        .header { text-align: center; border-bottom: 3px solid #1B5E20; padding-bottom: 10px; margin-bottom: 15px; background: #ffffff; }
        .header-logos { overflow: hidden; margin-bottom: 8px; }
        .sinporg-logo { height: 56px; margin-bottom: 6px; }
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
        <div class="header-logos">' . $imgLogo . $imgSelo . '</div>
        <img class="sinporg-logo" src="' . __DIR__ . '/../img/logo_Sinporg-2.png" alt="SINPORG - Sistema Integrado da Produção Orgânica" />
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
