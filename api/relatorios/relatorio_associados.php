<?php
// Iniciar a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';

// Verificar se o usuário está logado e tem permissão
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_nivel'])) {
    die('Acesso negado. Por favor, faça login para acessar este relatório.');
}

if (!in_array($_SESSION['user_nivel'], [NIVEL_ADMIN, NIVEL_GESTOR], true)) {
    die('Você não tem permissão para acessar este relatório.');
}

require_once __DIR__ . '/../../vendor/autoload.php';
use Mpdf\Mpdf;

$nome_associacao = isset($_POST['nome_associacao']) ? trim($_POST['nome_associacao']) : 'Associação';
$nome_responsavel = isset($_POST['nome_responsavel']) ? trim($_POST['nome_responsavel']) : '';
$formato = isset($_POST['formato']) ? $_POST['formato'] : 'pdf';

try {
    $db = Database::getInstance()->getConnection();
    
    $query = "SELECT 
                a.id, 
                CONCAT(a.nome, ' ', a.sobrenome) as nome_completo,
                a.email,
                a.cpf,
                a.rg,
                a.telefone,
                c.nome as cidade_nome,
                a.estado,
                a.data_cadastro,
                a.status
              FROM associados a
              LEFT JOIN cidades c ON a.cidade = c.id
              ORDER BY a.nome, a.sobrenome";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $associados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html = '
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .title { font-size: 24px; font-weight: bold; color: #333; margin-bottom: 5px; }
        .subtitle { font-size: 14px; color: #666; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .signature-section { margin-top: 50px; text-align: center; }
        .signature-line { border-top: 1px solid #000; width: 300px; margin: 0 auto 5px auto; }
        .status-ativo { color: #198754; font-weight: bold; }
        .status-inativo { color: #dc3545; font-weight: bold; }
    </style>
    
    <div class="header">
        <div class="title">' . htmlspecialchars($nome_associacao) . '</div>
        <div class="subtitle">Relatório Geral de Associados</div>
        <div style="margin-top: 5px; color: #666;">Gerado em: ' . date('d/m/Y H:i:s') . ' | Total de associados: ' . count($associados) . '</div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>E-mail</th>
                <th>CPF</th>
                <th>RG</th>
                <th>Telefone</th>
                <th>Cidade/UF</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($associados)) {
        $html .= '<tr><td colspan="7" style="text-align: center;">Nenhum associado encontrado.</td></tr>';
    } else {
        foreach ($associados as $row) {
            $cpf = !empty($row['cpf']) ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $row['cpf']) : 'Não informado';
            $telefone = !empty($row['telefone']) ? $row['telefone'] : 'Não informado';
            
            $cidade_uf = (!empty($row['cidade_nome']) ? $row['cidade_nome'] : 'Não informada');
            if (!empty($row['estado'])) $cidade_uf .= '/' . $row['estado'];

            $statusTexto = ($row['status'] === 'ativo') ? 'Ativo' : 'Inativo';
            $statusClasse = ($row['status'] === 'ativo') ? 'status-ativo' : 'status-inativo';

            $html .= '<tr>
                <td>' . htmlspecialchars($row['nome_completo']) . '</td>
                <td>' . htmlspecialchars($row['email'] ?? 'Não informado') . '</td>
                <td>' . htmlspecialchars($cpf) . '</td>
                <td>' . htmlspecialchars($row['rg'] ?? 'Não informado') . '</td>
                <td>' . htmlspecialchars($telefone) . '</td>
                <td>' . htmlspecialchars($cidade_uf) . '</td>
                <td class="' . $statusClasse . '">' . $statusTexto . '</td>
            </tr>';
        }
    }

    $html .= '</tbody></table>';

    // Seção de assinaturas
    $html .= '<div class="signature-section">';
    $html .= '<div class="signature-line"></div>';
    if (!empty($nome_responsavel)) {
        $html .= '<div>' . htmlspecialchars($nome_responsavel) . '</div>';
        $html .= '<div style="font-size: 9px; color: #666;">Coordenador / Responsável</div>';
    } else {
        $html .= '<div>Assinatura do Responsável</div>';
    }
    $html .= '</div>';

    // Processamento de Exportação
    if ($formato === 'excel') {
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=relatorio_associados_" . date('Ymd_His') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");
        echo '<meta charset="utf-8">';
        echo $html;
        exit;
    } else {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15
        ]);

        $mpdf->SetTitle('Relatório de Associados');
        $mpdf->SetAuthor('SisAgro');
        $mpdf->SetSubject('Relatório Geral');
        $mpdf->SetFooter('Página {PAGENO} de {nbpg}');

        $mpdf->WriteHTML($html);
        $mpdf->Output('relatorio_associados_' . date('Ymd_His') . '.pdf', 'I');
    }

} catch (Exception $e) {
    die("Erro ao gerar relatório: " . $e->getMessage());
}
?>
