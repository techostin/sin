<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    die('Acesso negado.');
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../../classes/ReportManager.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

try {
    $reportManager = new ReportManager();
    $filtros = $_GET;
    $dados = $reportManager->getAssociadosReport($filtros);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Associados');

    // Cabeçalhos
    $headers = ['ID', 'Nome', 'Sobrenome', 'E-mail', 'Telefone', 'RG', 'Cidade', 'UF', 'Status'];
    $sheet->fromArray($headers, NULL, 'A1');

    // Estilo do cabeçalho
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E7D32']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $sheet->getStyle('A1:I1')->applyFromArray($headerStyle);

    // Dados
    if (!empty($dados)) {
        $rowNum = 2;
        foreach ($dados as $item) {
            $sheet->setCellValue('A' . $rowNum, $item['id']);
            $sheet->setCellValue('B' . $rowNum, $item['nome']);
            $sheet->setCellValue('C' . $rowNum, $item['sobrenome']);
            $sheet->setCellValue('D' . $rowNum, $item['email']);
            $sheet->setCellValue('E' . $rowNum, $item['telefone']);
            $sheet->setCellValue('F' . $rowNum, $item['rg'] ?? '-');
            $sheet->setCellValue('G' . $rowNum, $item['cidade_nome'] ?? '-');
            $sheet->setCellValue('H' . $rowNum, $item['estado_uf'] ?? '-');
            $sheet->setCellValue('I' . $rowNum, ucfirst($item['status']));
            $rowNum++;
        }
        
        // Auto-ajuste de colunas
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    // Configurar headers para download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="Relatorio_Associados_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    die('Erro ao gerar planilha: ' . $e->getMessage());
}
