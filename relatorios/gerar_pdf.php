<?php
// Habilitar exibição de erros para desenvolvimento
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir autoload do Composer para mPDF
require_once __DIR__ . '/../vendor/autoload.php';

// Incluir configuração do banco
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

use Mpdf\Mpdf;

// Inicializar sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação do usuário
$user = new User();
if (!$user->isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    echo 'Acesso não autorizado';
    exit();
}

try {
    // Conectar ao banco
    $db = Database::getInstance()->getConnection();

    // Buscar associados com dados da associação
    $sql = "
        SELECT 
            a.*,
            ass.nome as associacao_nome
        FROM associados a
        LEFT JOIN associacoes ass ON a.vinculo = ass.id
        ORDER BY a.nome, a.sobrenome ASC
    ";
    
    $stmt = $db->query($sql);
    $associados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estatísticas
    $total_associados = count($associados);
    $associados_com_vinculo = count(array_filter($associados, function($a) {
        return !empty($a['associacao_nome']);
    }));
    $associados_sem_vinculo = $total_associados - $associados_com_vinculo;

    // Incluir o template
    ob_start();
    include __DIR__ . '/templates/relatorio_associados.php';
    $html = ob_get_clean();

    // Criar instância do mPDF
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15,
        'default_font' => 'helvetica',
        'orientation' => 'P'
    ]);

    // Adicionar cabeçalho
    $mpdf->SetHeader('Relatório de Associados||{PAGENO}');
    
    // Adicionar rodapé
    $mpdf->SetFooter('Sistema de Gestão de Associados - Página {PAGENO} de {nb}');

    // Adicionar conteúdo
    $mpdf->WriteHTML($html);

    // Gerar o PDF
    $filename = 'relatorio_associados_' . date('Y-m-d_His') . '.pdf';
    
    // Saída para o navegador
    $mpdf->Output($filename, 'I');
    
} catch (Exception $e) {
    // Em caso de erro, mostrar mensagem amigável
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Erro ao gerar relatório</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; color: #333; }
            .error-container { max-width: 800px; margin: 50px auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 5px; background-color: #f9f9f9; }
            h1 { color: #d32f2f; margin-top: 0; }
            pre { background-color: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; }
            .btn { display: inline-block; padding: 8px 16px; background-color: #1976d2; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px; }
            .btn:hover { background-color: #1565c0; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1>Ocorreu um erro ao gerar o relatório</h1>
            <p>Por favor, tente novamente mais tarde ou entre em contato com o suporte técnico.</p>
            <p>Detalhes do erro:</p>
            <pre>' . htmlspecialchars($e->getMessage()) . '</pre>
            <a href="' . SITE_URL . '/relatorios" class="btn">Voltar para a página de relatórios</a>
        </div>
    </body>
    </html>';
    exit();
}
