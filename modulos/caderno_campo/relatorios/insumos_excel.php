<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    die('Acesso negado. Por favor, faça login para acessar este relatório.');
}

// Incluir autoload do Composer para PhpSpreadsheet
require_once __DIR__ . '/../../../vendor/autoload.php';

// Incluir configuração do banco
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../../classes/User.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

try {
    $user = new User();

    // Conectar ao banco
    $db = Database::getInstance()->getConnection();

    // Filtro de escopo: não-gestores só veem os próprios insumos
    $params = [];
    $where = ["i.excluido = 0"];
    if (($_SESSION['user_nivel'] ?? NIVEL_USUARIO) > NIVEL_GESTOR) {
        $assoc_id = $user->getAssociadoId();
        if ($assoc_id) {
            $where[] = "i.associado_id = :associado_id";
            $params[':associado_id'] = $assoc_id;
        }
    }
    $where_clause = implode(' AND ', $where);

    // Buscar dados dos insumos
    $sql = "SELECT 
        i.*,
        a.nome as associado_nome,
        f.nome as fornecedor_nome,
        f.telefone as fornecedor_telefone,
        f.email as fornecedor_email
        FROM insumos i
LEFT JOIN associados a ON i.associado_id = a.id
LEFT JOIN fornecedores f ON i.fornecedor_id = f.id
WHERE $where_clause
ORDER BY i.nome ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estatísticas
    $total_insumos = count($insumos);
    $total_estoque = array_sum(array_column($insumos, 'quantidade_estoque'));
    $insumos_baixo_estoque = count(array_filter($insumos, function ($i) {
        return $i['quantidade_estoque'] < 10; }));
    $insumos_sem_estoque = count(array_filter($insumos, function ($i) {
        return $i['quantidade_estoque'] <= 0; }));

    // Criar novo Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Configurar metadados
    $spreadsheet->getProperties()
        ->setCreator("Sistema Integrado da Produção Orgânica")
        ->setTitle("Relatório de Insumos")
        ->setSubject("Caderno de Campo - Controle de Insumos")
        ->setDescription("Relatório gerado automaticamente")
        ->setKeywords("insumos, caderno de campo, agricultura")
        ->setCategory("Relatórios");

    // Título do relatório
    $sheet->setCellValue('A1', 'Relatório de Insumos');
    $sheet->mergeCells('A1:G1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Informações do relatório
    $sheet->setCellValue('A2', 'Caderno de Campo - Sistema Integrado da Produção Orgânica');
    $sheet->mergeCells('A2:H2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A3', 'Gerado em: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A3:H3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Estatísticas
    $sheet->setCellValue('A5', 'Estatísticas:');
    $sheet->getStyle('A5')->getFont()->setBold(true);
    $sheet->setCellValue('A6', 'Total de Insumos: ' . $total_insumos);
    $sheet->setCellValue('C6', 'Total em Estoque: ' . number_format($total_estoque, 2));
    $sheet->setCellValue('E6', 'Baixo Estoque: ' . $insumos_baixo_estoque);
    $sheet->setCellValue('G6', 'Sem Estoque: ' . $insumos_sem_estoque);

    // Cabeçalhos da tabela
    $row = 8;
    $headers = ['Insumo', 'Descrição', 'Associado', 'Fornecedor', 'Estoque Anterior', 'Recebido', 'Estoque Atual', 'Validade'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . $row, $header);
        $col++;
    }

    // Estilizar cabeçalhos
    $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '28a745']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);

    // Preencher dados
    $row++;
    foreach ($insumos as $insumo) {
        $sheet->setCellValue('A' . $row, $insumo['nome']);
        $sheet->setCellValue('B' . $row, $insumo['descricao'] ?? '-');
        $sheet->setCellValue('C' . $row, $insumo['associado_nome'] ?? '-');
        $sheet->setCellValue('D' . $row, $insumo['fornecedor_nome'] ?? '-');
        $sheet->setCellValue('E' . $row, number_format($insumo['estoq_anterior'] ?? 0, 2));
        $sheet->setCellValue('F' . $row, number_format($insumo['quant_recebida'] ?? 0, 2));
        $sheet->setCellValue('G' . $row, number_format($insumo['quantidade_estoque'] ?? 0, 2));
        $sheet->setCellValue('H' . $row, $insumo['data_validade'] ? date('d/m/Y', strtotime($insumo['data_validade'])) : '-');

        // Colorir estoque
        $estoqueColor = 'FFFFFF';
        if ($insumo['quantidade_estoque'] <= 0) {
            $estoqueColor = 'FFCCCC'; // Vermelho claro
        } elseif ($insumo['quantidade_estoque'] < 10) {
            $estoqueColor = 'FFEB9C'; // Amarelo claro
        } else {
            $estoqueColor = 'C6EFCE'; // Verde claro
        }
        $sheet->getStyle('G' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($estoqueColor);

        $row++;
    }

    // Aplicar bordas em toda a tabela
    $sheet->getStyle('A8:H' . ($row - 1))->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
    ]);

    // Ajustar largura das colunas
    $sheet->getColumnDimension('A')->setWidth(25);
    $sheet->getColumnDimension('B')->setWidth(30);
    $sheet->getColumnDimension('C')->setWidth(25);
    $sheet->getColumnDimension('D')->setWidth(25);
    $sheet->getColumnDimension('E')->setWidth(18);
    $sheet->getColumnDimension('F')->setWidth(15);
    $sheet->getColumnDimension('G')->setWidth(15);
    $sheet->getColumnDimension('H')->setWidth(15);

    // Configurar cabeçalhos HTTP para download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="relatorio_insumos_' . date('Y-m-d_His') . '.xlsx"');
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