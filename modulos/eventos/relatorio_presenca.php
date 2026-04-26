<?php
// Iniciar a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    die('Acesso negado. Por favor, faça login para acessar este relatório.');
}

// Incluir autoload do Composer para mPDF
require_once __DIR__ . '/../../vendor/autoload.php';

// Incluir configuração do banco e classes
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Evento.php';
require_once __DIR__ . '/../../classes/Associado.php';

use Mpdf\Mpdf;

try {
    // Obter ID do evento
    $evento_id = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : 0;
    
    if ($evento_id <= 0) {
        die('ID do evento inválido.');
    }

    // Instanciar classes
    $objEvento = new Evento();
    $objAssociado = new Associado();
    
    // Conectar ao banco para pegar dados do evento (não tem método específico na classe Evento para um único evento com todos os detalhes)
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM eventos WHERE id = ?");
    $stmt->execute([$evento_id]);
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$evento) {
        die('Evento não encontrado.');
    }

    // Buscar presenças
    $presencas = $objEvento->getPresencasByEvento($evento_id);
    
    // Buscar todos os associados (para listar todos na chamada)
    $associados = $objAssociado->getAll();

    // Criar instância do mPDF
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 30,
        'margin_bottom' => 20,
        'margin_header' => 10,
        'margin_footer' => 10
    ]);

    // HTML do relatório
    $html = '
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #2e7d32; padding-bottom: 15px; }
        .logo { font-size: 28px; font-weight: bold; color: #2e7d32; text-transform: uppercase; }
        .title { font-size: 18px; font-weight: bold; margin-top: 10px; color: #444; }
        .info-section { margin-bottom: 20px; background-color: #f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #eee; }
        .info-grid { width: 100%; border-collapse: collapse; }
        .info-grid td { padding: 5px; }
        .label { font-weight: bold; color: #555; width: 100px; }
        
        table.list-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.list-table th { background-color: #2e7d32; color: white; padding: 10px; text-align: left; border: 1px solid #2e7d32; }
        table.list-table td { padding: 8px; border: 1px solid #ddd; }
        table.list-table tr:nth-child(even) { background-color: #fcfcfc; }
        
        .status-badge { 
            padding: 3px 8px; 
            border-radius: 3px; 
            font-size: 10px; 
            font-weight: bold; 
            text-transform: uppercase;
            color: white;
        }
        .bg-presente { background-color: #28a745; }
        .bg-ausente { background-color: #dc3545; }
        .bg-justificado { background-color: #ffc107; color: #000; }
        .bg-nenhum { background-color: #6c757d; }
        
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 9px; color: #888; border-top: 1px solid #eee; padding-top: 5px; }
        .signature-line { margin-top: 50px; text-align: center; }
        .line { border-top: 1px solid #333; width: 250px; margin: 0 auto; }
    </style>

    <div class="header">
        <div class="logo">' . SITE_TITLE . '</div>
        <div class="title">Lista de Presença em Evento</div>
        <div style="font-size: 10px; color: #666; margin-top: 5px;">Relatório Automático de Frequência</div>
    </div>

    <div class="info-section">
        <table class="info-grid">
            <tr>
                <td class="label">Evento:</td>
                <td><strong>' . htmlspecialchars($evento['nome']) . '</strong></td>
                <td class="label">Data:</td>
                <td>' . date('d/m/Y H:i', strtotime($evento['data_evento'])) . '</td>
            </tr>
            <tr>
                <td class="label">Local:</td>
                <td colspan="3">' . htmlspecialchars($evento['local']) . '</td>
            </tr>
            <tr>
                <td class="label">Status Evento:</td>
                <td>' . htmlspecialchars($evento['status']) . '</td>
                <td class="label">Gerado em:</td>
                <td>' . date('d/m/Y H:i') . '</td>
            </tr>
        </table>
    </div>

    <table class="list-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="45%">Nome do Associado</th>
                <th width="30%">E-mail / Telefone</th>
                <th width="20%" style="text-align: center;">Presença</th>
            </tr>
        </thead>
        <tbody>';

    $count = 1;
    foreach ($associados as $assoc) {
        $status = $presencas[$assoc['id']] ?? 'Nenhum';
        $status_label = $status;
        if ($status === 'Nenhum') $status_label = 'Não Registrado';
        
        $class = 'bg-' . strtolower($status);
        if ($status === 'Confirmado') $class = 'bg-nenhum';

        $html .= '
        <tr>
            <td style="text-align: center;">' . $count++ . '</td>
            <td><strong>' . htmlspecialchars($assoc['nome'] . ' ' . $assoc['sobrenome']) . '</strong></td>
            <td>' . htmlspecialchars($assoc['email']) . '</td>
            <td style="text-align: center;">
                <span class="status-badge ' . $class . '">' . htmlspecialchars($status_label) . '</span>
            </td>
        </tr>';
    }

    $html .= '
        </tbody>
    </table>

    <div class="signature-line">
        <div class="line"></div>
        <div style="margin-top: 5px;">Responsável pela Conferência</div>
        <div style="font-size: 9px; color: #888;">' . $_SESSION['user_nome'] . ' (' . date('d/m/Y') . ')</div>
    </div>

    <div class="footer">
        Relatório gerado pelo Sistema ' . SITE_TITLE . ' - Página {PAGENO} de {nbpg}
    </div>';

    // Configurar o mPDF
    $mpdf->SetTitle('Lista de Presença - ' . $evento['nome']);
    $mpdf->SetAuthor(SITE_TITLE);
    
    // Escrever o HTML
    $mpdf->WriteHTML($html);

    // Saída do arquivo
    $filename = 'lista_presenca_' . $evento_id . '_' . date('Ymd') . '.pdf';
    $mpdf->Output($filename, 'I');

} catch (Exception $e) {
    echo "Erro ao gerar relatório: " . $e->getMessage();
}
?>
