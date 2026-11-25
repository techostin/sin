<?php
/**
 * Template para o relatório de propriedades em PDF
 * 
 * Variáveis disponíveis:
 * - $propriedades: Array com os dados das propriedades
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
    <title>Relatório de Propriedades Rurais</title>
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
            border-bottom: 2px solid #28a745; /* Verde para propriedades */
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
            color: #666;
            font-size: 10pt;
        }
        
        .logo {
            max-width: 120px;
            margin-bottom: 15px;
        }
        
        /* Informações do relatório */
        .info-box {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745; /* Verde para propriedades */
        }
        
        .info-item {
            display: flex;
            margin-bottom: 5px;
            font-size: 9.5pt;
        }
        
        .info-label {
            font-weight: 600;
            min-width: 120px;
            color: #555;
        }
        
        /* Tabela */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0 25px;
            font-size: 9pt;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        thead {
            background-color: #28a745; /* Verde para propriedades */
            color: white;
        }
        
        th {
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 8.5pt;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 8px 12px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        
        tbody tr {
            transition: background-color 0.2s;
        }
        
        tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        
        /* Status */
        .status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 8.5pt;
            font-weight: 600;
            text-align: center;
            min-width: 70px;
        }
        
        .status-ativo {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-inativo {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-pendente {
            background-color: #fff3cd;
            color: #856404;
        }
        
        /* Badges para contadores */
        .badge {
            display: inline-block;
            padding: 3px 7px;
            font-size: 8.5pt;
            font-weight: 600;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            border-radius: 10px;
            background-color: #e9ecef;
            color: #495057;
        }
        
        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        /* Rodapé */
        .footer {
            text-align: right;
            font-size: 8pt;
            color: #6c757d;
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #e9ecef;
        }
        
        /* Utilitários */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: 600; }
        .page-break { page-break-after: always; }
        .no-print { display: none; }
        .mb-0 { margin-bottom: 0; }
        .mt-0 { margin-top: 0; }
        .mt-3 { margin-top: 1rem; }
        .mb-3 { margin-bottom: 1rem; }
        
        /* Responsividade */
        @page {
            margin: 20mm 10mm 15mm 10mm;
        }
        
        .page-number:after {
            content: counter(page);
        }
        
        .page-count:after {
            content: counter(pages);
        }
        
        /* Estilo para a lista de culturas */
        .culturas-list {
            margin: 0;
            padding: 0;
            list-style: none;
            font-size: 8.5pt;
        }
        
        .culturas-list li {
            display: inline-block;
            background-color: #e9ecef;
            color: #495057;
            padding: 2px 8px;
            border-radius: 10px;
            margin: 2px;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="<?php echo htmlspecialchars(($logoPath ?? '') ?: ((defined('SITE_URL') ? SITE_URL : '/app') . '/img/logo_Sinporg-2.png')); ?>" alt="SINPORG - Sistema Integrado da Produção Orgânica" class="logo" style="max-width:210px; height:auto;" />
        <div class="text-center mb-3">
            <h1>RELATÓRIO DE PROPRIEDADES RURAIS</h1>
            <p class="mb-0">Sistema de Gestão de Associações</p>
        </div>
    </div>
    
    <div class="info-box">
        <div class="info-item">
            <span class="info-label">Data de Emissão:</span>
            <span><?php echo date('d/m/Y H:i:s'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Total de Propriedades:</span>
            <span class="text-bold"><?php echo number_format(count($propriedades), 0, ',', '.'); ?></span>
        </div>
        <?php if ($dataInicio && $dataFim): ?>
        <div class="info-item">
            <span class="info-label">Período:</span>
            <span><?php echo date('d/m/Y', strtotime($dataInicio)) . ' até ' . date('d/m/Y', strtotime($dataFim)); ?></span>
        </div>
        <?php endif; ?>
        <div class="info-item">
            <span class="info-label">Ordenação:</span>
            <span>
                <?php 
                    $ordenacao = [
                        'nome' => 'Nome da Propriedade',
                        'proprietario' => 'Proprietário',
                        'area_total' => 'Área Total',
                        'cidade' => 'Cidade',
                        'data_cadastro' => 'Data de Cadastro',
                        'status' => 'Status'
                    ];
                    echo $ordenacao[$ordenarPor] ?? 'Nome da Propriedade';
                    echo ' (' . ($direcao == 'ASC' ? 'Crescente' : 'Decrescente') . ')';
                ?>
            </span>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <?php if (in_array('id', $colunas)): ?><th>ID</th><?php endif; ?>
                <?php if (in_array('nome', $colunas)): ?><th>Nome da Propriedade</th><?php endif; ?>
                <?php if (in_array('proprietario', $colunas)): ?><th>Proprietário</th><?php endif; ?>
                <?php if (in_array('area_total', $colunas)): ?><th class="text-center">Área (ha)</th><?php endif; ?>
                <?php if (in_array('cidade', $colunas)): ?><th>Cidade/UF</th><?php endif; ?>
                <?php if (in_array('total_talhoes', $colunas)): ?><th class="text-center">Talhões</th><?php endif; ?>
                <?php if (in_array('culturas', $colunas)): ?><th>Culturas</th><?php endif; ?>
                <?php if (in_array('data_cadastro', $colunas)): ?><th>Cadastro</th><?php endif; ?>
                <?php if (in_array('status', $colunas)): ?><th class="text-center">Status</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (count($propriedades) > 0): ?>
                <?php foreach ($propriedades as $propriedade): 
                    $statusClass = 'status-' . strtolower($propriedade['status']);
                    $statusText = ucfirst(strtolower($propriedade['status']));
                    
                    // Preparar lista de culturas
                    $culturas = !empty($propriedade['culturas']) ? explode(', ', $propriedade['culturas']) : [];
                ?>
                <tr>
                    <?php if (in_array('id', $colunas)): ?>
                        <td class="text-center"><?php echo str_pad($propriedade['id'], 5, '0', STR_PAD_LEFT); ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('nome', $colunas)): ?>
                        <td class="text-bold"><?php echo htmlspecialchars($propriedade['propriedade_nome']); ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('proprietario', $colunas)): ?>
                        <td><?php echo $propriedade['proprietario_nome'] ?: '<span class="text-muted">Não informado</span>'; ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('area_total', $colunas)): ?>
                        <td class="text-right"><?php echo number_format($propriedade['area_total'], 2, ',', '.'); ?></td>
                    <?php endif; ?>
                    
                    <?php if (in_array('cidade', $colunas)): ?>
                        <td nowrap>
                            <?php 
                                echo $propriedade['cidade'] ?: '-';
                                echo $propriedade['estado'] ? '/' . $propriedade['estado'] : '';
                            ?>
                        </td>
                    <?php endif; ?>
                    
                    <?php if (in_array('total_talhoes', $colunas)): ?>
                        <td class="text-center">
                            <span class="badge <?php echo $propriedade['total_talhoes'] > 0 ? 'badge-success' : ''; ?>">
                                <?php echo $propriedade['total_talhoes'] ?: '0'; ?>
                            </span>
                        </td>
                    <?php endif; ?>
                    
                    <?php if (in_array('culturas', $colunas)): ?>
                        <td>
                            <?php if (!empty($culturas)): ?>
                                <ul class="culturas-list">
                                    <?php foreach (array_slice($culturas, 0, 3) as $cultura): ?>
                                        <li><?php echo htmlspecialchars(trim($cultura)); ?></li>
                                    <?php endforeach; ?>
                                    <?php if (count($culturas) > 3): ?>
                                        <li>+<?php echo count($culturas) - 3; ?> mais</li>
                                    <?php endif; ?>
                                </ul>
                            <?php else: ?>
                                <span class="text-muted">Nenhuma cultura cadastrada</span>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    
                    <?php if (in_array('data_cadastro', $colunas)): ?>
                        <td nowrap><?php echo $propriedade['data_cadastro']; ?></td>
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
                            Nenhuma propriedade encontrada para os filtros selecionados
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
            $size = 9;
            $font = $fontMetrics->getFont("sans-serif");
            $width = $fontMetrics->get_text_width($text, $font, $size) / 2;
            $x = ($pdf->get_width() - $width) / 2;
            $y = $pdf->get_height() - 15;
            $pdf->page_text($x, $y, $text, $font, $size);
            
            $pdf->page_text(30, $y, date('d/m/Y H:i:s'), $font, $size);
            $pdf->page_text($pdf->get_width() - 50, $y, "SGA", $font, $size);
        }
    </script>
</body>
</html>
