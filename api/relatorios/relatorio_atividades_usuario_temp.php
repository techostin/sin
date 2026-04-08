<?php
// Iniciar a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir o arquivo de configuração para ter acesso às constantes
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';

// Verificar se o usuário está logado e tem permissão
if (!isset($_SESSION['user_id'])) {
    die('Acesso negado. Por favor, faça login para acessar este relatório.');
}

// Incluir a biblioteca TCPDF
require_once __DIR__ . '/../../vendor/autoload.php';

// Criar uma nova instância do TCPDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Configurar informações do documento
$pdf->SetCreator('Sistema Integrado da Produção Orgânica');
$pdf->SetAuthor('SisAgro');
$pdf->SetTitle('Relatório de Atividades do Usuário');
$pdf->SetSubject('Relatório de Atividades');
$pdf->SetKeywords('Atividades, Relatório, PDF, Agricultura');

// Remover cabeçalho e rodapé padrão
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Adicionar uma página
$pdf->AddPage();

// Configurar fonte
$pdf->SetFont('helvetica', '', 10);

// Adicionar cabeçalho personalizado
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Relatório de Atividades do Usuário', 0, 1, 'C');
$pdf->Ln(5);

// Adicionar data e hora da geração
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 10, 'Gerado em: ' . date('d/m/Y H:i:s'), 0, 1, 'R');
$pdf->Ln(5);

try {
    // Conectar ao banco de dados
    $db = Database::getInstance()->getConnection();
    
    // Obter o ID do usuário logado
    $usuario_id = $_SESSION['user_id'];
    
    // Buscar as atividades do usuário logado
    $stmt = $db->prepare("
        SELECT 
            ad.id, 
            ad.nome AS atividade_nome, 
            ad.descricao,
            DATE_FORMAT(ad.data_atividade, '%d/%m/%Y') AS data_formatada,
            ad.hora_inicio,
            ad.hora_fim,
            ad.tipo_atividade,
            ad.status,
            p.nome AS propriedade_nome,
            l.nome AS lavoura_nome,
            a.nome AS associado_nome,
            ca.nome AS categoria_nome,
            ca.cor AS categoria_cor
        FROM 
            atividades_diarias ad
            LEFT JOIN propriedades p ON ad.propriedade_id = p.id
            LEFT JOIN lavouras l ON ad.lavoura_id = l.id
            LEFT JOIN associados a ON ad.associado_id = a.id
            LEFT JOIN categorias_atividades ca ON ad.categoria_id = ca.id
        WHERE 
            ad.usuario_id = :usuario_id
        ORDER BY 
            ad.data_atividade DESC, 
            ad.hora_inicio DESC
    ");
    
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $atividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar se existem atividades
    if (count($atividades) === 0) {
        $pdf->Cell(0, 10, 'Nenhuma atividade encontrada para o período selecionado.', 0, 1, 'C');
    } else {
        // Calcular totais
        $total_atividades = count($atividades);
        $total_horas = 0;
        
        // Cabeçalho da tabela
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(25, 10, 'Data', 1, 0, 'C', 1);
        $pdf->Cell(50, 10, 'Atividade', 1, 0, 'C', 1);
        $pdf->Cell(45, 10, 'Propriedade', 1, 0, 'C', 1);
        $pdf->Cell(30, 10, 'Início', 1, 0, 'C', 1);
        $pdf->Cell(30, 10, 'Término', 1, 0, 'C', 1);
        $pdf->Cell(20, 10, 'Duração', 1, 1, 'C', 1);
        
        $pdf->SetFont('helvetica', '', 9);
        
        foreach ($atividades as $atividade) {
            // Calcular duração
            $duracao = '';
            if (!empty($atividade['hora_inicio']) && !empty($atividade['hora_fim'])) {
                $inicio = new DateTime($atividade['hora_inicio']);
                $fim = new DateTime($atividade['hora_fim']);
                $diferenca = $inicio->diff($fim);
                $duracao = $diferenca->format('%H:%I');
                $total_horas += ($diferenca->h * 60 + $diferenca->i) / 60; // Converter para horas decimais
            }
            
            // Determinar a cor do status
            $cor_status = '0,0,0'; // Preto como padrão
            switch (strtolower($atividade['status'])) {
                case 'concluído':
                case 'concluida':
                    $cor_status = '40, 167, 69'; // Verde
                    break;
                case 'pendente':
                    $cor_status = '255, 193, 7'; // Amarelo
                    break;
                case 'cancelado':
                case 'cancelada':
                    $cor_status = '220, 53, 69'; // Vermelho
                    break;
            }
            
            // Adicionar linha da tabela
            $pdf->Cell(25, 8, $atividade['data_formatada'], 'LR', 0, 'C');
            $pdf->Cell(50, 8, substr($atividade['atividade_nome'], 0, 25), 'LR', 0, 'L');
            $pdf->Cell(45, 8, substr($atividade['propriedade_nome'] ?? 'N/A', 0, 20), 'LR', 0, 'L');
            $pdf->Cell(30, 8, (!empty($atividade['hora_inicio']) ? substr($atividade['hora_inicio'], 0, 5) : 'N/A'), 'LR', 0, 'C');
            $pdf->Cell(30, 8, (!empty($atividade['hora_fim']) ? substr($atividade['hora_fim'], 0, 5) : 'N/A'), 'LR', 0, 'C');
            $pdf->Cell(20, 8, $duracao, 'LR', 1, 'C');
            
            // Adicionar linha de status
            $pdf->SetTextColor(explode(', ', $cor_status));
            $pdf->Cell(200, 6, 'Status: ' . $atividade['status'], 'LRB', 1, 'L');
            $pdf->SetTextColor(0, 0, 0); // Voltar para preto
        }
        
        // Adicionar totais
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(180, 10, 'Total de Atividades:', 1, 0, 'R');
        $pdf->Cell(20, 10, $total_atividades, 1, 1, 'C');
        
        $pdf->Cell(180, 10, 'Total de Horas Trabalhadas:', 1, 0, 'R');
        $pdf->Cell(20, 10, number_format($total_horas, 1) . ' h', 1, 1, 'C');
    }
    
    // Fechar e gerar o PDF
    $pdf->Output('relatorio_atividades_usuario_' . date('Y-m-d_His') . '.pdf', 'I');
    
} catch (Exception $e) {
    // Em caso de erro, exibir mensagem
    die('Erro ao gerar o relatório: ' . $e->getMessage());
}
?>
