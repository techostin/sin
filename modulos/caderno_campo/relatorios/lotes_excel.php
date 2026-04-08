<?php
// Exporta relatório de lotes para Excel
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Database.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

try {
    $db = Database::getInstance()->getConnection();

    $sql = "SELECT 
        lp.*,
        p.nome as produto_nome,
        p.tipo as produto_tipo,
        p.unidade_medida as produto_unidade,
        p.data_colheita,
        l.nome as lavoura_nome,
        l.tipo_cultura as cultura,
        prop.propriedade,
        a.nome as associado_nome,
        CONCAT('LOTE-', LPAD(lp.id, 6, '0')) as codigo_rastreabilidade
        FROM lotes_producao lp
        LEFT JOIN produtos p ON lp.produto_id = p.id
        LEFT JOIN lavouras l ON p.lavoura_id = l.id
        LEFT JOIN propriedades prop ON l.propriedade_id = prop.id
        LEFT JOIN associados a ON l.associado_id = a.id
        ORDER BY lp.data_producao DESC";

    $stmt = $db->query($sql);
    $lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Título
    $sheet->setCellValue('A1', 'Relatório de Lotes de Produção');
    $sheet->mergeCells('A1:J1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A2', 'Gerado em: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:J2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Cabeçalhos
    $row = 4;
    $headers = ['Código', 'Nome Lote', 'Produto', 'Tipo Produto', 'Quantidade', 'Unidade', 'Data Produção', 'Propriedade', 'Lavoura', 'Responsável'];
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . $row, $h);
        $col++;
    }

    // Dados
    $row++;
    foreach ($lotes as $lote) {
        $sheet->setCellValue('A' . $row, $lote['codigo_rastreabilidade']);
        $sheet->setCellValue('B' . $row, $lote['nome_lote'] ?? $lote['nome'] ?? '-');
        $sheet->setCellValue('C' . $row, $lote['produto_nome'] ?? '-');
        $sheet->setCellValue('D' . $row, $lote['produto_tipo'] ?? '-');
        $sheet->setCellValue('E' . $row, $lote['quantidade'] ?? 0);
        $sheet->setCellValue('F' . $row, $lote['unidade_medida'] ?? $lote['produto_unidade'] ?? '-');
        $sheet->setCellValue('G' . $row, (!empty($lote['data_producao']) ? date('d/m/Y', strtotime($lote['data_producao'])) : '-'));
        $sheet->setCellValue('H' . $row, $lote['propriedade'] ?? '-');
        $sheet->setCellValue('I' . $row, $lote['lavoura_nome'] ?? '-');
        $sheet->setCellValue('J' . $row, $lote['associado_nome'] ?? '-');
        $row++;
    }

    // Formatação básica
    foreach (range('A', 'J') as $c) {
        $sheet->getColumnDimension($c)->setAutoSize(true);
    }
    $sheet->getStyle('A4:J4')->getFont()->setBold(true);
    $sheet->getStyle('A4:J' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // Saída
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="relatorio_lotes_' . date('Y-m-d_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    echo 'Erro ao gerar Excel: ' . htmlspecialchars($e->getMessage());
}

?>
