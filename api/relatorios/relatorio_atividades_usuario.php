<?php
// Iniciar a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir o arquivo de configuração para ter acesso às constantes
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    die('Acesso negado. Por favor, faça login para acessar este relatório.');
}

// Incluir autoload do Composer
require_once __DIR__ . '/../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use TCPDF as TCPDF;

// Verificar formato solicitado (padrão: pdf)
$formato = isset($_GET['formato']) ? strtolower($_GET['formato']) : 'pdf';
if (!in_array($formato, ['pdf', 'excel'])) {
    $formato = 'pdf';
}

// Verificar autenticação do usuário
$user = new User();
if (!$user->isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    echo 'Acesso não autorizado';
    exit;
}

// Obter dados do usuário logado
$usuario = $user->getCurrentUser();
if (!$usuario) {
    header('HTTP/1.1 401 Unauthorized');
    echo 'Não foi possível identificar o usuário logado.';
    exit;
}

// Determinar o associado_id vinculado ao usuário (para filtrar atividades)
$associado_id = null;
if (($_SESSION['user_table'] ?? 'associados') === 'usuarios') {
    // Usuário logado na tabela usuarios possui coluna associado_id
    $associado_id = isset($usuario['associado_id']) ? (int)$usuario['associado_id'] : 0;
} else {
    // Usuário logado diretamente como associado
    $associado_id = (int)$usuario['id'];
}

if ($associado_id <= 0) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Erro: não foi possível identificar o associado vinculado a este usuário.';
    exit;
}

// Configurações do relatório
$titulo = 'Relatório de Atividades do Usuário';
$data_geracao = date('d/m/Y H:i:s');
$usuario_nome = $usuario['nome'] ?? 'Usuário';

try {
    // Conectar ao banco de dados
    $db = Database::getInstance()->getConnection();
    
    // Buscar as atividades do associado vinculado ao usuário logado
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
            p.propriedade AS propriedade_nome,
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
            ad.associado_id = :associado_id
        ORDER BY 
            ad.data_atividade DESC, 
            ad.hora_inicio DESC
    ");
    
    $stmt->bindParam(':associado_id', $associado_id, PDO::PARAM_INT);
    $stmt->execute();
    $atividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar se existem atividades
    if (count($atividades) === 0) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<div class="alert alert-info">Nenhuma atividade encontrada para o usuário logado.</div>';
        exit;
    }
    
    // Calcular totais
    $total_atividades = count($atividades);
    $total_horas = 0;
    
    foreach ($atividades as $atividade) {
        if (!empty($atividade['hora_inicio']) && !empty($atividade['hora_fim'])) {
            $inicio = new DateTime($atividade['hora_inicio']);
            $fim = new DateTime($atividade['hora_fim']);
            $diferenca = $inicio->diff($fim);
            $total_horas += $diferenca->h + ($diferenca->i / 60);
        }
    }
    
    // Gerar relatório no formato solicitado
    if ($formato === 'excel') {
        // ========== GERAÇÃO DO RELATÓRIO EM EXCEL ==========
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Configurar título
        $sheet->setCellValue('A1', $titulo);
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');
        
        $sheet->setCellValue('A2', 'Gerado em: ' . $data_geracao . ' | Usuário: ' . $usuario_nome);
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getFont()->setSize(11);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal('center');
        
        // Cabeçalhos da tabela
        $cabecalhos = ['Data', 'Atividade', 'Propriedade', 'Lavoura', 'Início', 'Término', 'Duração', 'Status'];
        $col = 0;
        foreach ($cabecalhos as $cabecalho) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . '4';
            $sheet->setCellValue($cell, $cabecalho);
            $col++;
        }
        
        // Estilo dos cabeçalhos
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            'borders' => ['allBorders' => ['borderStyle' => 'thin']]
        ];
        $sheet->getStyle('A4:H4')->applyFromArray($headerStyle);
        
        // Preencher dados
        $row = 5;
        foreach ($atividades as $atividade) {
            // Calcular duração
            $duracao = '';
            if (!empty($atividade['hora_inicio']) && !empty($atividade['hora_fim'])) {
                $inicio = new DateTime($atividade['hora_inicio']);
                $fim = new DateTime($atividade['hora_fim']);
                $diferenca = $inicio->diff($fim);
                $duracao = $diferenca->format('%H:%I');
            }
            
            $sheet->setCellValue('A' . $row, $atividade['data_formatada']);
            $sheet->setCellValue('B' . $row, $atividade['atividade_nome']);
            $sheet->setCellValue('C' . $row, $atividade['propriedade_nome'] ?? 'N/A');
            $sheet->setCellValue('D' . $row, $atividade['lavoura_nome'] ?? 'N/A');
            $sheet->setCellValue('E' . $row, !empty($atividade['hora_inicio']) ? substr($atividade['hora_inicio'], 0, 5) : 'N/A');
            $sheet->setCellValue('F' . $row, !empty($atividade['hora_fim']) ? substr($atividade['hora_fim'], 0, 5) : 'N/A');
            $sheet->setCellValue('G' . $row, $duracao);
            $sheet->setCellValue('H' . $row, $atividade['status']);
            
            // Aplicar estilo às células de dados
            $dataStyle = [
                'borders' => ['allBorders' => ['borderStyle' => 'thin']],
                'alignment' => ['horizontal' => 'left', 'vertical' => 'center']
            ];
            $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray($dataStyle);
            
            // Centralizar colunas de horário e duração
            $sheet->getStyle('E' . $row . ':G' . $row)->getAlignment()->setHorizontal('center');
            $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal('center');
            
            $row++;
        }
        
        // Adicionar linha de totais
        $row++;
        $sheet->setCellValue('A' . $row, 'Total de Atividades:');
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal('right');
        $sheet->setCellValue('H' . $row, $total_atividades);
        $sheet->getStyle('H' . $row)->getFont()->setBold(true);
        $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal('center');
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Total de Horas:');
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal('right');
        $sheet->setCellValue('H' . $row, number_format($total_horas, 1, ',', '.') . ' h');
        $sheet->getStyle('H' . $row)->getFont()->setBold(true);
        $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal('center');
        
        // Ajustar largura das colunas
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Configurar cabeçalhos para download
        $filename = 'relatorio_atividades_usuario_' . date('Y-m-d_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        // Criar o arquivo Excel
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
        
    } else {
        // ========== GERAÇÃO DO RELATÓRIO EM PDF ==========
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    
    // Configurações do documento
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Sistema Integrado da Produção Orgânica');
    $pdf->SetTitle($titulo);
    $pdf->SetSubject('Relatório de Atividades');
    $pdf->SetKeywords('atividades, relatório, agricultura, gestão');
    
    // Remover cabeçalho e rodapé padrão
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Adicionar uma página
    $pdf->AddPage();
    
    // Configurações de fonte
    $pdf->SetFont('helvetica', '', 10);
    
    // Conteúdo do relatório
    $html = '    
    <style>
        .titulo {
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .subtitulo {
            font-size: 12px;
            text-align: center;
            margin-bottom: 15px;
            color: #7f8c8d;
        }
        .cabecalho-tabela {
            background-color: #f8f9fa;
            font-weight: bold;
            text-align: center;
        }
        .rodape-tabela {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .texto-pequeno {
            font-size: 9px;
        }
        .texto-direita {
            text-align: right;
        }
        .texto-centro {
            text-align: center;
        }
        .borda-inferior {
            border-bottom: 1px solid #dee2e6;
        }
        .espacamento-superior {
            margin-top: 15px;
        }
    </style>
    
    <div class="titulo">' . $titulo . '</div>
    <div class="subtitulo">Gerado em: ' . $data_geracao . ' | Usuário: ' . htmlspecialchars($usuario_nome) . '</div>
    
    <table border="0.5" cellpadding="4" cellspacing="0" width="100%">
        <thead>
            <tr class="cabecalho-tabela">
                <th width="7%">Data</th>
                <th width="15%">Atividade</th>
                <th width="32%">Propriedade</th>
                <th width="18%">Lavoura</th>
                <th width="8%" class="texto-centro">Início</th>
                <th width="8%" class="texto-centro">Término</th>
                <th width="7%" class="texto-centro">Duração</th>
                <th width="5%" class="texto-centro">Status</th>
            </tr>
        </thead>
        <tbody>';
    
    // Adicionar linhas com os dados das atividades
    foreach ($atividades as $atividade) {
        // Calcular duração
        $duracao = '';
        if (!empty($atividade['hora_inicio']) && !empty($atividade['hora_fim'])) {
            $inicio = new DateTime($atividade['hora_inicio']);
            $fim = new DateTime($atividade['hora_fim']);
            $diferenca = $inicio->diff($fim);
            $duracao = $diferenca->format('%H:%I');
        }
        
        // Determinar a cor do status
        $cor_status = '';
        switch (strtolower($atividade['status'])) {
            case 'concluído':
            case 'concluida':
                $cor_status = '#28a745'; // Verde
                break;
            case 'pendente':
                $cor_status = '#ffc107'; // Amarelo
                break;
            case 'cancelado':
            case 'cancelada':
                $cor_status = '#dc3545'; // Vermelho
                break;
            default:
                $cor_status = '#6c757d'; // Cinza
        }
        
        $html .= '
            <tr>
                <td class="borda-inferior">' . htmlspecialchars($atividade['data_formatada']) . '</td>
                <td class="borda-inferior">' . htmlspecialchars($atividade['atividade_nome']) . '</td>
                <td class="borda-inferior">' . htmlspecialchars($atividade['propriedade_nome'] ?? 'N/A') . '</td>
                <td class="borda-inferior">' . htmlspecialchars($atividade['lavoura_nome'] ?? 'N/A') . '</td>
                <td class="texto-centro borda-inferior">' . (!empty($atividade['hora_inicio']) ? substr($atividade['hora_inicio'], 0, 5) : 'N/A') . '</td>
                <td class="texto-centro borda-inferior">' . (!empty($atividade['hora_fim']) ? substr($atividade['hora_fim'], 0, 5) : 'N/A') . '</td>
                <td class="texto-centro borda-inferior">' . $duracao . '</td>
                <td class="texto-centro borda-inferior" style="color: ' . $cor_status . ';">' . htmlspecialchars($atividade['status']) . '</td>
            </tr>';
    }
    
    // Adicionar rodapé com totais
    $html .= '
            <tr class="rodape-tabela">
                <td colspan="7" class="texto-direita"><strong>Total de Atividades:</strong></td>
                <td class="texto-centro"><strong>' . $total_atividades . '</strong></td>
            </tr>
            <tr class="rodape-tabela">
                <td colspan="7" class="texto-direita"><strong>Total de Horas:</strong></td>
                <td class="texto-centro"><strong>' . number_format($total_horas, 1, ',', '.') . ' h</strong></td>
            </tr>';
    
    $html .= '
        </tbody>
    </table>
    
    <div class="espacamento-superior">
        <p class="texto-pequeno">
            <strong>Legenda de Status:</strong><br>
            <span style="color: #28a745;">• Concluído</span> | 
            <span style="color: #ffc107;">• Pendente</span> | 
            <span style="color: #dc3545;">• Cancelado</span> | 
            <span style="color: #6c757d;">• Outros</span>
        </p>
    </div>
    
    <div class="espacamento-superior texto-pequeno" style="text-align: center; color: #7f8c8d;">
        Relatório gerado pelo Sistema Integrado da Produção Orgânica em ' . $data_geracao . '
    </div>';
    
        // Escrever o conteúdo HTML no PDF
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Nome do arquivo
        $filename = 'relatorio_atividades_usuario_' . date('Y-m-d_His') . '.pdf';
        
        // Saída do PDF para o navegador
        $pdf->Output($filename, 'I');
    }
    
} catch (Exception $e) {
    // Em caso de erro, exibir mensagem
    header('Content-Type: text/html; charset=utf-8');
    echo '<div class="alert alert-danger">Erro ao gerar o relatório: ' . htmlspecialchars($e->getMessage()) . '</div>';
    // Exibir detalhes do erro apenas em ambiente de desenvolvimento
    // Nota: Para habilitar debug, defina a constante DEBUG no config.php
    if (function_exists('getenv') && getenv('APP_DEBUG') === 'true') {
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    }
}
