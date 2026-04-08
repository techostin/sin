<?php
/**
 * Template para o relatório de insumos em PDF
 * 
 * Variáveis disponíveis:
 * - $insumos: Array com os dados dos insumos
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
    <title>Relatório de Insumos</title>
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
            border-bottom: 2px solid #9b59b6; /* Roxo para insumos */
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
            vertical-align: middle;
        }
        
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        /* Valores monetários */
        .valor {
            text-align: right;
            font-family: 'Courier New', monospace;
            white-space: nowrap;
        }
        
        .valor-total {
            font-weight: bold;
            background-color: #f1f8ff !important;
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
        <div class="text-center mb-3">
            <h1>RELATÓRIO DE INSUMOS</h1>
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
                <?php if (in_array('nome', $colunas)): ?><th>Insumo</th><?php endif; ?>
                <?php if (in_array('tipo', $colunas)): ?><th>Tipo</th><?php endif; ?>
                <?php if (in_array('quantidade', $colunas)): ?><th class="text-right">Quantidade</th><?php endif; ?>
                <?php if (in_array('unidade_medida', $colunas)): ?><th>Unidade</th><?php endif; ?>
                <?php if (in_array('valor_unitario', $colunas)): ?><th class="text-right">Valor Unit.</th><?php endif; ?>
                <?php if (in_array('valor_total', $colunas)): ?><th class="text-right">Valor Total</th><?php endif; ?>
                <?php if (in_array('fornecedor', $colunas)): ?><th>Fornecedor</th><?php endif; ?>
                <?php if (in_array('data_compra', $colunas)): ?><th>Data Compra</th><?php endif; ?>
                <?php if (in_array('validade', $colunas)): ?><th>Validade</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (count($insumos) > 0): ?>
                <?php 
                $totalGeral = 0;
                foreach ($insumos as $insumo): 
                    $totalGeral += (float) $insumo['valor_total'];
                ?>
                <tr>
                    <?php if (in_array('id', $colunas)): ?>
                        <td class="text-center"><?php echo str_pad($insumo['id'], 5, '0', STR_PAD_LEFT); ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('nome', $colunas)): ?>
                        <td class="text-bold"><?php echo htmlspecialchars($insumo['nome']); ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('tipo', $colunas)): ?>
                        <td><?php echo htmlspecialchars($insumo['tipo']); ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('quantidade', $colunas)): ?>
                        <td class="text-right"><?php echo number_format($insumo['quantidade'], 2, ',', '.'); ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('unidade_medida', $colunas)): ?>
                        <td class="text-center"><?php echo htmlspecialchars($insumo['unidade_medida']); ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('valor_unitario', $colunas)): ?>
                        <td class="valor">R$ <?php echo number_format($insumo['valor_unitario'], 2, ',', '.'); ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('valor_total', $colunas)): ?>
                        <td class="valor">R$ <?php echo number_format($insumo['valor_total'], 2, ',', '.'); ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('fornecedor', $colunas)): ?>
                        <td><?php echo htmlspecialchars($insumo['fornecedor'] ?? 'Não informado'); ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('data_compra', $colunas)): ?>
                        <td nowrap><?php echo $insumo['data_compra']; ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('validade', $colunas)): ?>
                        <td nowrap><?php echo $insumo['validade'] ?? 'Não informada'; ?></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                
                <!-- Totalizador -->
                <tr class="valor-total">
                    <td colspan="<?php echo count($colunas) - 1; ?>">Total Geral</td>
                    <td class="valor">R$ <?php echo number_format($totalGeral, 2, ',', '.'); ?></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td colspan="<?php echo count($colunas); ?>" class="text-center" style="padding: 30px 0;">
                        <div style="color: #6c757d; font-style: italic;">
                            <i class="fas fa-inbox" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                            Nenhum insumo encontrado para os filtros selecionados
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
