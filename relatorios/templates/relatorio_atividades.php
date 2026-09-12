<?php
/**
 * Template para o relatório de atividades diárias em PDF
 * 
 * Variáveis disponíveis:
 * - $atividades: Array com os dados das atividades
 * - $dataInicio: Data de início do período (opcional)
 * - $dataFim: Data de fim do período (opcional)
 * - $ordenarPor: Campo para ordenação
 * - $direcao: Direção da ordenação (ASC ou DESC)
 * - $colunas: Array com as colunas a serem exibidas
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Atividades Diárias</title>
    <style>
        /* Estilos base */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #fff;
        }
        
        /* Cabeçalho */
        .header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #3498db; /* Azul para atividades */
            position: relative;
        }
        
        .header h1 {
            color: #2c3e50;
            margin: 0 0 5px 0;
            font-size: 20pt;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        
        .header p {
            margin: 0;
            color: #7f8c8d;
            font-size: 11pt;
        }
        
        .periodo {
            font-size: 10pt;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        /* Tabela */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 9pt;
            page-break-inside: avoid;
        }
        
        th {
            background-color: #f8f9fa;
            color: #495057;
            text-align: left;
            padding: 8px 10px;
            border: 1px solid #dee2e6;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 8pt;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 8px 10px;
            border: 1px solid #dee2e6;
            vertical-align: top;
        }
        
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        /* Status */
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 8pt;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pendente {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-concluido {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-cancelado {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-andamento {
            background-color: #cce5ff;
            color: #004085;
        }
        
        /* Rodapé */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #6c757d;
            padding: 10px 0;
            border-top: 1px solid #dee2e6;
            background-color: #fff;
        }
        
        /* Número da página */
        .page-number:before {
            content: counter(page);
        }
        
        .page-count:before {
            content: counter(pages);
        }
        
        /* Quebra de página */
        .page-break {
            page-break-before: always;
        }
        
        /* Texto em negrito */
        .text-bold {
            font-weight: 600;
        }
        
        /* Alinhamento de texto */
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        /* Espaçamento */
        .mb-0 {
            margin-bottom: 0;
        }
        
        .mt-0 {
            margin-top: 0;
        }
        
        /* Responsividade */
        @media print {
            body {
                padding: 0;
                margin: 0;
            }
            
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="<?php echo htmlspecialchars(($logoPath ?? '') ?: ((defined('SITE_URL') ? SITE_URL : '/app') . '/img/logo_Sinporg-2.png')); ?>" alt="SINPORG - Sistema Integrado da Produção Orgânica" class="logo" style="max-width:210px; height:auto;" />
        <div class="text-center mb-3">
            <h1>RELATÓRIO DE ATIVIDADES DIÁRIAS</h1>
            <p class="mb-0">Sistema de Gestão de Associações</p>
            
            <?php if ($dataInicio && $dataFim): ?>
                <p class="periodo">Período: <?php echo date('d/m/Y', strtotime($dataInicio)); ?> a <?php echo date('d/m/Y', strtotime($dataFim)); ?></p>
            <?php else: ?>
                <p class="periodo">Dados completos</p>
            <?php endif; ?>
            
            <p class="periodo">Gerado em: <?php echo date('d/m/Y H:i'); ?></p>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <?php if (in_array('id', $colunas)): ?><th>ID</th><?php endif; ?>
                <?php if (in_array('nome', $colunas)): ?><th>Atividade</th><?php endif; ?>
                <?php if (in_array('descricao', $colunas)): ?><th>Descrição</th><?php endif; ?>
                <?php if (in_array('data_atividade', $colunas)): ?><th>Data</th><?php endif; ?>
                <?php if (in_array('categoria', $colunas)): ?><th>Categoria</th><?php endif; ?>
                <?php if (in_array('responsavel', $colunas)): ?><th>Responsável</th><?php endif; ?>
                <?php if (in_array('status', $colunas)): ?><th class="text-center">Status</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (count($atividades) > 0): ?>
                <?php foreach ($atividades as $atividade): 
                    $statusClass = 'status-' . strtolower($atividade['status']);
                    $statusText = ucfirst(strtolower($atividade['status']));
                ?>
                <tr>
                    <?php if (in_array('id', $colunas)): ?>
                        <td class="text-center"><?php echo str_pad($atividade['id'], 5, '0', STR_PAD_LEFT); ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('nome', $colunas)): ?>
                        <td class="text-bold"><?php echo htmlspecialchars($atividade['nome']); ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('descricao', $colunas)): ?>
                        <td><?php echo nl2br(htmlspecialchars($atividade['descricao'])); ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('data_atividade', $colunas)): ?>
                        <td nowrap><?php echo $atividade['data_atividade']; ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('categoria', $colunas)): ?>
                        <td><?php echo htmlspecialchars($atividade['categoria'] ?? 'Não informada'); ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('responsavel', $colunas)): ?>
                        <td><?php echo htmlspecialchars($atividade['responsavel'] ?? 'Não informado'); ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('status', $colunas)): ?>
                        <td class="text-center">
                            <span class="status-badge <?php echo $statusClass; ?>">
                                <?php echo $statusText; ?>
                            </span>
                        </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?php echo count($colunas); ?>" class="text-center" style="padding: 30px 0;">
                        <div style="color: #6c757d; font-style: italic;">
                            <i class="fas fa-inbox" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                            Nenhuma atividade encontrada para os filtros selecionados
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="footer">
        <div>Página <span class="page-number"></span> de <span class="page-count"></span></div>
        <div>Gerado por: <?php echo htmlspecialchars($_SESSION['user_nome'] ?? 'Sistema'); ?></div>
    </div>
    
    <script type="text/php">
        if (isset($pdf)) {
            $text = "Página {PAGE_NUM} de {PAGE_COUNT}";
            $size = 8;
            $font = $fontMetrics->getFont("Verdana");
            $width = $fontMetrics->get_text_width($text, $font, $size) / 2;
            $x = ($pdf->get_width() - $width) / 2;
            $y = $pdf->get_height() - 15;
            $pdf->page_text($x, $y, $text, $font, $size);
            
            $text = "Gerado em: " . date('d/m/Y H:i');
            $x = $pdf->get_width() - $fontMetrics->get_text_width($text, $font, $size) - 10;
            $y = $pdf->get_height() - 15;
            $pdf->page_text($x, $y, $text, $font, $size);
            
            $text = "SisAgro - Gestão de Associações";
            $x = 10;
            $y = $pdf->get_height() - 15;
            $pdf->page_text($x, $y, $text, $font, $size);
        }
    </script>
</body>
</html>
