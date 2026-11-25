<?php
/**
 * Template para o relatório financeiro em PDF
 * 
 * Variáveis disponíveis:
 * - $lancamentos: Array com os dados dos lançamentos financeiros
 * - $totalReceitas: Valor total de receitas
 * - $totalDespesas: Valor total de despesas
 * - $saldo: Saldo (receitas - despesas)
 * - $dataInicio: Data de início do período (opcional)
 * - $dataFim: Data de fim do período (opcional)
 * - $tipo: Tipo de lançamento (receita/despesa)
 * - $categoria: ID da categoria filtrada (se houver)
 * - $ordenarPor: Campo para ordenação
 * - $direcao: Direção da ordenação (ASC ou DESC)
 * - $colunas: Array com as colunas a serem exibidas
 */

// Obter totais da sessão se não estiverem definidos
if (!isset($totalReceitas) && isset($_SESSION['relatorio_financeiro_totais'])) {
    $totalReceitas = $_SESSION['relatorio_financeiro_totais']['receitas'];
    $totalDespesas = $_SESSION['relatorio_financeiro_totais']['despesas'];
    $saldo = $_SESSION['relatorio_financeiro_totais']['saldo'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório Financeiro</title>
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
            border-bottom: 2px solid #27ae60; /* Verde para finanças */
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
        
        .status-pago, .status-recebido {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-pendente {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-vencido {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-cancelado {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        /* Valores monetários */
        .valor {
            text-align: right;
            font-family: 'Courier New', monospace;
            font-weight: 500;
        }
        
        .receita {
            color: #28a745;
        }
        
        .despesa {
            color: #dc3545;
        }
        
        /* Totais */
        .totais {
            margin-top: 20px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .total-row {
            display: flex;
            border-bottom: 1px solid #dee2e6;
        }
        
        .total-row:last-child {
            border-bottom: none;
        }
        
        .total-label {
            flex: 1;
            padding: 10px 15px;
            font-weight: 600;
            background-color: #f8f9fa;
            border-right: 1px solid #dee2e6;
        }
        
        .total-value {
            flex: 1;
            padding: 10px 15px;
            text-align: right;
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }
        
        .total-receita {
            color: #28a745;
        }
        
        .total-despesa {
            color: #dc3545;
        }
        
        .total-saldo {
            color: #007bff;
            font-size: 1.1em;
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
            <h1>RELATÓRIO FINANCEIRO</h1>
            <p class="mb-0">Sistema de Gestão de Associações</p>
            
            <?php if ($dataInicio && $dataFim): ?>
                <p class="periodo">Período: <?php echo date('d/m/Y', strtotime($dataInicio)); ?> a <?php echo date('d/m/Y', strtotime($dataFim)); ?></p>
            <?php else: ?>
                <p class="periodo">Dados completos</p>
            <?php endif; ?>
            
            <?php if ($tipo === 'receita'): ?>
                <p class="periodo">Tipo: Receitas</p>
            <?php elseif ($tipo === 'despesa'): ?>
                <p class="periodo">Tipo: Despesas</p>
            <?php endif; ?>
            
            <p class="periodo">Gerado em: <?php echo date('d/m/Y H:i'); ?></p>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <?php if (in_array('id', $colunas)): ?><th>ID</th><?php endif; ?>
                <?php if (in_array('descricao', $colunas)): ?><th>Descrição</th><?php endif; ?>
                <?php if (in_array('categoria', $colunas)): ?><th>Categoria</th><?php endif; ?>
                <?php if (in_array('tipo', $colunas)): ?><th>Tipo</th><?php endif; ?>
                <?php if (in_array('valor', $colunas)): ?><th class="text-right">Valor</th><?php endif; ?>
                <?php if (in_array('data_vencimento', $colunas)): ?><th>Vencimento</th><?php endif; ?>
                <?php if (in_array('data_pagamento', $colunas)): ?><th>Pagamento</th><?php endif; ?>
                <?php if (in_array('status', $colunas)): ?><th class="text-center">Status</th><?php endif; ?>
                <?php if (in_array('conta', $colunas)): ?><th>Conta</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (count($lancamentos) > 0): ?>
                <?php foreach ($lancamentos as $lancamento): 
                    $statusClass = 'status-' . strtolower($lancamento['status']);
                    $statusText = ucfirst(strtolower($lancamento['status']));
                    $valorClass = strtolower($lancamento['tipo']);
                ?>
                <tr>
                    <?php if (in_array('id', $colunas)): ?>
                        <td class="text-center"><?php echo str_pad($lancamento['id'], 5, '0', STR_PAD_LEFT); ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('descricao', $colunas)): ?>
                        <td><?php echo htmlspecialchars($lancamento['descricao']); ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('categoria', $colunas)): ?>
                        <td><?php echo htmlspecialchars($lancamento['categoria'] ?? 'Não categorizado'); ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('tipo', $colunas)): ?>
                        <td><?php echo ucfirst($lancamento['tipo']); ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('valor', $colunas)): ?>
                        <td class="valor <?php echo $valorClass; ?>">
                            R$ <?php echo number_format($lancamento['valor'], 2, ',', '.'); ?>
                        </td>
                    <?php endif; ?>
                    
                    <?php if (in_array('data_vencimento', $colunas)): ?>
                        <td nowrap><?php echo $lancamento['data_vencimento']; ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('data_pagamento', $colunas)): ?>
                        <td nowrap><?php echo $lancamento['data_pagamento'] ?? '-'; ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('status', $colunas)): ?>
                        <td class="text-center">
                            <span class="status-badge <?php echo $statusClass; ?>">
                                <?php echo $statusText; ?>
                            </span>
                        </td>
                    <?php endif; ?>
                    
                    <?php if (in_array('conta', $colunas)): ?>
                        <td><?php echo htmlspecialchars($lancamento['conta_nome'] ?? '-'); ?></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?php echo count($colunas); ?>" class="text-center" style="padding: 30px 0;">
                        <div style="color: #6c757d; font-style: italic;">
                            <i class="fas fa-inbox" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                            Nenhum lançamento encontrado para os filtros selecionados
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if (count($lancamentos) > 0): ?>
    <div class="totais">
        <div class="total-row">
            <div class="total-label">Total de Receitas:</div>
            <div class="total-value total-receita">R$ <?php echo number_format($totalReceitas, 2, ',', '.'); ?></div>
        </div>
        <div class="total-row">
            <div class="total-label">Total de Despesas:</div>
            <div class="total-value total-despesa">R$ <?php echo number_format($totalDespesas, 2, ',', '.'); ?></div>
        </div>
        <div class="total-row" style="background-color: #f1f8ff;">
            <div class="total-label"><strong>Saldo:</strong></div>
            <div class="total-value total-saldo"><strong>R$ <?php echo number_format($saldo, 2, ',', '.'); ?></strong></div>
        </div>
    </div>
    <?php endif; ?>
    
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
