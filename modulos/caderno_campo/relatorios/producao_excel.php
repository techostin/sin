<?php
// Exporta relatório de produção para Excel
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    die('Acesso negado. Por favor, faça login para acessar este relatório.');
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../../classes/User.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

try {
    $user = new User();
    $db = Database::getInstance()->getConnection();

    // Filtro de escopo: não-gestores só veem produção das próprias propriedades
    $params = [];
    $where = [];
    if (($_SESSION['user_nivel'] ?? NIVEL_USUARIO) > NIVEL_GESTOR) {
        $assoc_id = $user->getAssociadoId();
        if ($assoc_id) {
            $where[] = "prop.associado_id = :associado_id";
            $params[':associado_id'] = $assoc_id;
        }
    }
    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT 
        p.*,
        l.nome as lavoura_nome,
        l.tipo_cultura as cultura,
        l.area as area_lavoura,
        l.data_plantio,
        prop.propriedade,
        a.nome as associado_nome,
        lp.nome_lote,
        lp.data_producao,
        lp.quantidade as quantidade_lote,
        lp.unidade_medida as unidade_lote,
        CONCAT('PROD-', LPAD(p.id, 6, '0')) as codigo_rastreabilidade_produto,
        CONCAT('LOTE-', LPAD(lp.id, 6, '0')) as codigo_rastreabilidade_lote
        FROM produtos p
        LEFT JOIN lavouras l ON p.lavoura_id = l.id
        LEFT JOIN propriedades prop ON l.propriedade_id = prop.id
        LEFT JOIN associados a ON prop.associado_id = a.id
        LEFT JOIN lotes_producao lp ON p.id = lp.produto_id
        $where_clause
        ORDER BY p.data_colheita DESC, lp.data_producao DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Título
    $sheet->setCellValue('A1', 'Relatório de Produção');
    $sheet->mergeCells('A1:K1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A2', 'Gerado em: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:K2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Cabeçalhos
    $row = 4;
    $headers = ['Código Produto', 'Produto', 'Tipo', 'Lavoura', 'Cultura', 'Quantidade', 'Unidade', 'Data Colheita', 'Lote', 'Código Lote', 'Associado'];
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . $row, $h);
        $col++;
    }

    // Dados
    $row++;
    foreach ($produtos as $p) {
        $sheet->setCellValue('A' . $row, $p['codigo_rastreabilidade_produto'] ?? ('PROD-' . str_pad($p['id'],6,'0',STR_PAD_LEFT)));
        $sheet->setCellValue('B' . $row, $p['nome'] ?? '-');
        $sheet->setCellValue('C' . $row, $p['tipo'] ?? '-');
        $sheet->setCellValue('D' . $row, $p['lavoura_nome'] ?? '-');
        $sheet->setCellValue('E' . $row, $p['cultura'] ?? '-');
        $sheet->setCellValue('F' . $row, $p['quantidade_produzida'] ?? ($p['quantidade_lote'] ?? 0));
        $sheet->setCellValue('G' . $row, $p['unidade_medida'] ?? ($p['unidade_lote'] ?? '-'));
        $sheet->setCellValue('H' . $row, (!empty($p['data_colheita']) ? date('d/m/Y', strtotime($p['data_colheita'])) : '-'));
        $sheet->setCellValue('I' . $row, $p['nome_lote'] ?? '-');
        $sheet->setCellValue('J' . $row, $p['codigo_rastreabilidade_lote'] ?? '-');
        $sheet->setCellValue('K' . $row, $p['associado_nome'] ?? '-');
        $row++;
    }

    // Formatação
    foreach (range('A', 'K') as $c) {
        $sheet->getColumnDimension($c)->setAutoSize(true);
    }
    $sheet->getStyle('A4:K4')->getFont()->setBold(true);
    $sheet->getStyle('A4:K' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // Saída
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="relatorio_producao_' . date('Y-m-d_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    echo 'Erro ao gerar Excel: ' . htmlspecialchars($e->getMessage());
}

?>
