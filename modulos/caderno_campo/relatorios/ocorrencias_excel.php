<?php
// Exporta relatório de ocorrências para Excel
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../../classes/User.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    die('Acesso negado. Por favor, faça login para acessar este relatório.');
}

try {
    $user = new User();
    
    $severidade = isset($_GET['severidade']) ? $_GET['severidade'] : '';
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $propriedade_id = isset($_GET['propriedade_id']) ? (int)$_GET['propriedade_id'] : 0;
    $data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
    $data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';

    $db = Database::getInstance()->getConnection();

    $params = [];
    $where = [];

    if (!empty($severidade)) {
        $where[] = "om.severidade = :severidade";
        $params[':severidade'] = $severidade;
    }

    if (!empty($status)) {
        $where[] = "om.status = :status";
        $params[':status'] = $status;
    }

    if ($propriedade_id > 0) {
        $where[] = "om.propriedade_id = :propriedade_id";
        $params[':propriedade_id'] = $propriedade_id;
    }

    if (!empty($data_inicio)) {
        $where[] = "om.data_deteccao >= :data_inicio";
        $params[':data_inicio'] = $data_inicio;
    }

    if (!empty($data_fim)) {
        $where[] = "om.data_deteccao <= :data_fim";
        $params[':data_fim'] = $data_fim;
    }

    if (($_SESSION['user_nivel'] ?? NIVEL_USUARIO) > NIVEL_GESTOR) {
        $assoc_id = $user->getAssociadoId();
        if ($assoc_id) {
            $where[] = "om.associado_id = :associado_id";
            $params[':associado_id'] = $assoc_id;
        }
    }

    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT om.*, p.propriedade as propriedade_nome,
            l.nome as lavoura_nome, i.nome as insumo_nome,
            a.nome as associado_nome, a.sobrenome as associado_sobrenome
            FROM ocorrencias_manejo om
            LEFT JOIN propriedades p ON om.propriedade_id = p.id
            LEFT JOIN lavouras l ON om.lavoura_id = l.id
            LEFT JOIN insumos i ON om.insumo_utilizado_id = i.id
            LEFT JOIN associados a ON om.associado_id = a.id
            $where_clause
            ORDER BY om.data_deteccao DESC, om.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $ocorrencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Título
    $sheet->setCellValue('A1', 'Relatório de Ocorrências');
    $sheet->mergeCells('A1:J1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A2', 'Gerado em: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:J2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Cabeçalhos
    $row = 4;
    $headers = ['ID', 'Data Detecção', 'Tipo', 'Nome Comum', 'Severidade', 'Status', 'Propriedade', 'Lavoura', 'Produtor', 'Ação de Manejo'];
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . $row, $h);
        $col++;
    }

    // Dados
    $row++;
    foreach ($ocorrencias as $oc) {
        $st = $oc['status'] ?? 'Aberta';
        
        $sheet->setCellValue('A' . $row, $oc['id']);
        $sheet->setCellValue('B' . $row, (!empty($oc['data_deteccao']) ? date('d/m/Y', strtotime($oc['data_deteccao'])) : '-'));
        $sheet->setCellValue('C' . $row, $oc['tipo'] ?? '-');
        $sheet->setCellValue('D' . $row, $oc['nome_comum'] ?? '-');
        $sheet->setCellValue('E' . $row, $oc['severidade'] ?? '-');
        $sheet->setCellValue('F' . $row, $st);
        $sheet->setCellValue('G' . $row, $oc['propriedade_nome'] ?? '-');
        $sheet->setCellValue('H' . $row, $oc['lavoura_nome'] ?? '-');
        $sheet->setCellValue('I' . $row, trim(($oc['associado_nome'] ?? '') . ' ' . ($oc['associado_sobrenome'] ?? '')));
        $sheet->setCellValue('J' . $row, $oc['acao_manejo'] ?? '-');
        $row++;
    }

    // Formatação
    foreach (range('A', 'J') as $c) {
        $sheet->getColumnDimension($c)->setAutoSize(true);
    }
    $sheet->getStyle('A4:J4')->getFont()->setBold(true);
    $sheet->getStyle('A4:J' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // Saída
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="relatorio_ocorrencias_' . date('Y-m-d_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    echo 'Erro ao gerar Excel: ' . htmlspecialchars($e->getMessage());
}
?>
