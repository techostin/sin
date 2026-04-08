<?php
// Iniciar a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    die('Acesso negado. Por favor, faça login para acessar este relatório.');
}

// Incluir autoload do Composer para PhpSpreadsheet
require_once __DIR__ . '/../../../vendor/autoload.php';

// Incluir configuração do banco
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Database.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

try {
    // Obter parâmetros de filtro
    $data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
    $data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-t');
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $categoria_id = isset($_GET['categoria_id']) ? (int) $_GET['categoria_id'] : 0;
    $propriedade_id = isset($_GET['propriedade_id']) ? (int) $_GET['propriedade_id'] : 0;
    $associado_id = isset($_GET['associado_id']) ? (int) $_GET['associado_id'] : 0;

    // Conectar ao banco
    $db = Database::getInstance()->getConnection();

    // Construir a consulta SQL com filtros
    $params = [];
    $where = [];

    // Filtro de data
    $where[] = "ad.data_atividade BETWEEN :data_inicio AND :data_fim";
    $params[':data_inicio'] = $data_inicio;
    $params[':data_fim'] = $data_fim;

    // Filtro de status
    if (!empty($status)) {
        $where[] = "ad.status = :status";
        $params[':status'] = $status;
    }

    // Filtro de categoria
    if ($categoria_id > 0) {
        $where[] = "ad.categoria_id = :categoria_id";
        $params[':categoria_id'] = $categoria_id;
    }

    // Filtro de propriedade
    if ($propriedade_id > 0) {
        $where[] = "ad.propriedade_id = :propriedade_id";
        $params[':propriedade_id'] = $propriedade_id;
    }

    // Filtro de associado (se não for admin, só mostra as próprias atividades)
    if ($_SESSION['user_nivel'] != 1 && $_SESSION['user_nivel'] != 2) {
        $where[] = "ad.associado_id = :associado_id";
        $params[':associado_id'] = $_SESSION['user_id'];
    } elseif ($associado_id > 0) {
        $where[] = "ad.associado_id = :associado_id";
        $params[':associado_id'] = $associado_id;
    }

    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Consulta para buscar as atividades
    $sql = "SELECT 
        ad.*,
        p.propriedade,
        l.nome as lavoura,
        l.tipo_cultura as cultura,
        a.nome as associado_nome,
        a.sobrenome as associado_sobrenome,
        ca.nome as categoria_nome,
        ca.cor as categoria_cor,
        lo.nome_lote as lote_nome
        FROM atividades_diarias ad
        LEFT JOIN propriedades p ON ad.propriedade_id = p.id
        LEFT JOIN lavouras l ON ad.lavoura_id = l.id
        LEFT JOIN associados a ON ad.associado_id = a.id
        LEFT JOIN categorias_atividades ca ON ad.categoria_id = ca.id
        LEFT JOIN lotes_producao lo ON ad.lote_id = lo.id
        $where_clause
        ORDER BY ad.data_atividade DESC, ad.hora_inicio DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $atividades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Criar novo Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Configurar metadados
    $spreadsheet->getProperties()
        ->setCreator("Sistema Integrado da Produção Orgânica")
        ->setTitle("Relatório de Atividades")
        ->setSubject("Caderno de Campo - Atividades Diárias")
        ->setDescription("Relatório gerado automaticamente")
        ->setKeywords("atividades agrícolas relatório")
        ->setCategory("Relatórios");

    // Título do relatório
    $sheet->setCellValue('A1', 'Relatório de Atividades Diárias');
    $sheet->mergeCells('A1:J1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Informações do relatório
    $sheet->setCellValue('A2', 'Caderno de Campo - Sistema Integrado da Produção Orgânica');
    $sheet->mergeCells('A2:J2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A3', 'Período: ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim)));
    $sheet->mergeCells('A3:J3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A4', 'Gerado em: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A4:J4');
    $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Estatísticas
    $total_atividades = count($atividades);
    $concluidas = count(array_filter($atividades, function ($a) {
        return $a['status'] === 'Concluído'; }));
    $pendentes = count(array_filter($atividades, function ($a) {
        return $a['status'] === 'Pendente'; }));
    $em_andamento = count(array_filter($atividades, function ($a) {
        return $a['status'] === 'Em andamento'; }));

    $sheet->setCellValue('A6', 'Estatísticas:');
    $sheet->getStyle('A6')->getFont()->setBold(true);
    $sheet->setCellValue('A7', 'Total de Atividades: ' . $total_atividades);
    $sheet->setCellValue('C7', 'Concluídas: ' . $concluidas);
    $sheet->setCellValue('E7', 'Pendentes: ' . $pendentes);
    $sheet->setCellValue('G7', 'Em Andamento: ' . $em_andamento);

    // Cabeçalhos da tabela
    $row = 9;
    $headers = ['Data', 'Atividade', 'Tipo', 'Status', 'Propriedade', 'Lavoura', 'Cultura', 'Associado', 'Insumo', 'Observações'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . $row, $header);
        $col++;
    }

    // Estilizar cabeçalhos
    $sheet->getStyle('A' . $row . ':J' . $row)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);

    // Preencher dados
    $row++;
    foreach ($atividades as $atividade) {
        $sheet->setCellValue('A' . $row, date('d/m/Y', strtotime($atividade['data_atividade'])));
        $sheet->setCellValue('B' . $row, $atividade['nome']);
        $sheet->setCellValue('C' . $row, $atividade['tipo_atividade']);
        $sheet->setCellValue('D' . $row, $atividade['status']);
        $sheet->setCellValue('E' . $row, $atividade['propriedade'] ?? '-');
        $sheet->setCellValue('F' . $row, $atividade['lavoura'] ?? '-');
        $sheet->setCellValue('G' . $row, $atividade['cultura'] ?? '-');
        $sheet->setCellValue('H' . $row, ($atividade['associado_nome'] ?? '') . ' ' . ($atividade['associado_sobrenome'] ?? ''));
        $sheet->setCellValue('I' . $row, $atividade['insumo_utilisado'] ?? '-');
        $sheet->setCellValue('J' . $row, $atividade['observações'] ?? '-');

        // Colorir status
        $statusColor = 'FFFFFF';
        switch ($atividade['status']) {
            case 'Concluído':
                $statusColor = 'C6EFCE'; // Verde claro
                break;
            case 'Pendente':
                $statusColor = 'FFEB9C'; // Amarelo claro
                break;
            case 'Em andamento':
                $statusColor = 'BDD7EE'; // Azul claro
                break;
        }
        $sheet->getStyle('D' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($statusColor);

        $row++;
    }

    // Aplicar bordas em toda a tabela
    $sheet->getStyle('A9:J' . ($row - 1))->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
    ]);

    // Ajustar largura das colunas
    $sheet->getColumnDimension('A')->setWidth(12);
    $sheet->getColumnDimension('B')->setWidth(25);
    $sheet->getColumnDimension('C')->setWidth(20);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(20);
    $sheet->getColumnDimension('F')->setWidth(20);
    $sheet->getColumnDimension('G')->setWidth(15);
    $sheet->getColumnDimension('H')->setWidth(25);
    $sheet->getColumnDimension('I')->setWidth(20);
    $sheet->getColumnDimension('J')->setWidth(30);

    // Configurar cabeçalhos HTTP para download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="relatorio_atividades_' . date('Y-m-d_His') . '.xlsx"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');

    // Gerar o arquivo Excel
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    echo "Erro ao gerar relatório Excel: " . $e->getMessage();
}
?>