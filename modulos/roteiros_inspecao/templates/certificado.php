<?php
/**
 * Template: Certificado de Conformidade Orgânica
 * Local: app/modulos/roteiros_inspecao/templates/certificado.php
 * 
 * OBS: As variáveis abaixo são MOCKS para visualização.
 * Em produção, você deve popular $dados_produtor, $certificado e $produtos 
 * a partir do banco de dados no seu Controller.
 */

if (!isset($dados_produtor)) {
    $dados_produtor = [
        'nome' => 'Fazenda Vida Orgânica Ltda.',
        'cnpj' => '12.345.678/0001-90',
        'endereco' => 'Rodovia Verde, Km 12 - Zona Rural',
        'cidade_uf' => 'Município - UF, CEP 00000-000'
    ];
}

if (!isset($certificado)) {
    $certificado = [
        'numero' => 'BR-ORG-2026/00142',
        'escopo' => 'Produção Vegetal Primária',
        'normas' => 'Lei Brasileira nº 10.831/2003 e Instruções Normativas (MAPA)',
        'data_emissao' => '03 de Agosto de 2026',
        'data_validade' => '03 de Agosto de 2027',
        'local' => 'São Paulo'
    ];
}

if (!isset($produtos)) {
    // Lista de todos os produtos do produtor selecionado
    $produtos = [
        ['nome' => 'Alface Crespa', 'area_estimativa' => '1.5 ha', 'status' => 'Orgânico'],
        ['nome' => 'Tomate Cereja', 'area_estimativa' => '0.8 ha', 'status' => 'Orgânico'],
        ['nome' => 'Cenoura', 'area_estimativa' => '2.0 ha', 'status' => 'Orgânico'],
        ['nome' => 'Rúcula', 'area_estimativa' => '1.0 ha', 'status' => 'Orgânico'],
        ['nome' => 'Abobrinha', 'area_estimativa' => '0.5 ha', 'status' => 'Orgânico']
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado - <?php echo htmlspecialchars($dados_produtor['nome']); ?></title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }

        :root {
            --primary-color: #1b5e20;
            --secondary-color: #f57f17;
            --text-main: #333333;
            --bg-color: #fdfbf7;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #e0e0e0;
            margin: 0;
            padding: 20px;
        }

        .page-break {
            page-break-before: always;
            margin-top: 40px;
        }

        .certificate-container {
            background-color: var(--bg-color);
            width: 100%;
            max-width: 950px;
            padding: 50px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            position: relative;
            border: 2px solid var(--primary-color);
            outline: 10px solid var(--bg-color);
            outline-offset: -12px;
            margin-bottom: 30px;
            box-sizing: border-box;
        }

        .certificate-container::before {
            content: '';
            position: absolute;
            top: 18px; left: 18px; right: 18px; bottom: 18px;
            border: 1px solid rgba(27, 94, 32, 0.4);
            pointer-events: none;
        }

        .certificate-container::after {
            content: 'ORGÂNICO';
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 130px;
            color: rgba(27, 94, 32, 0.04);
            font-weight: 800;
            letter-spacing: 15px;
            pointer-events: none;
            z-index: 1;
        }

        .certificate-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .header {
            margin-bottom: 40px;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            padding-bottom: 25px;
            display: table;
            width: 100%;
        }

        .logo-placeholder {
            width: 130px;
            height: 130px;
            border: 2px dashed var(--primary-color);
            border-radius: 50%;
            display: table-cell;
            vertical-align: middle;
            font-size: 13px;
            color: var(--primary-color);
            font-weight: 600;
            text-transform: uppercase;
            text-align: center;
            background-color: rgba(27, 94, 32, 0.03);
            line-height: 1.4;
        }

        .logo-placeholder.certificadora {
            border-radius: 8px;
        }

        .header-titles {
            display: table-cell;
            vertical-align: middle;
            padding: 0 20px;
            text-align: center;
        }

        .header-titles h3 {
            margin: 0;
            color: var(--primary-color);
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .header-titles h1 {
            margin: 15px 0 10px 0;
            color: var(--primary-color);
            font-size: 36px;
            font-family: 'Playfair Display', Georgia, serif;
            letter-spacing: 1px;
            line-height: 1.2;
        }

        .certificate-number {
            font-size: 15px;
            color: #555;
            font-weight: 700;
            background-color: rgba(0,0,0,0.03);
            display: inline-block;
            padding: 5px 15px;
            border-radius: 4px;
        }

        .body-text {
            color: var(--text-main);
            line-height: 1.6;
            margin-bottom: 40px;
        }

        .certify-text {
            font-size: 18px;
            margin-bottom: 15px;
            color: #555;
        }

        .producer-info {
            margin: 25px 0;
            padding: 25px;
            background-color: rgba(27, 94, 32, 0.03);
            border-radius: 8px;
            border-left: 5px solid var(--primary-color);
            text-align: left;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }

        .producer-name {
            font-size: 26px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 8px;
            font-family: 'Playfair Display', Georgia, serif;
        }

        .producer-details {
            font-size: 15px;
            color: #444;
            margin: 4px 0;
            font-weight: 500;
        }

        .compliance-text {
            font-size: 16px;
            margin: 30px 0 20px 0;
            padding: 0 40px;
        }

        .standards-box {
            display: inline-block;
            padding: 12px 25px;
            border: 1px solid var(--secondary-color);
            border-radius: 6px;
            color: var(--text-main);
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 35px;
            background-color: rgba(245, 127, 23, 0.08);
            box-shadow: 0 2px 5px rgba(245, 127, 23, 0.1);
        }

        .scope-details {
            display: table;
            width: 100%;
            margin-bottom: 30px;
            text-align: left;
            background: #fff;
            padding: 25px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-sizing: border-box;
        }

        .scope-item {
            display: table-cell;
            width: 50%;
            padding: 0 20px;
        }

        .scope-item:first-child {
            border-right: 1px solid #e0e0e0;
        }

        .scope-label {
            font-size: 12px;
            text-transform: uppercase;
            color: #777;
            letter-spacing: 1.5px;
            margin-bottom: 8px;
            display: block;
            font-weight: 600;
        }

        .scope-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .footer {
            display: table;
            width: 100%;
            margin-top: 60px;
            padding: 0 20px;
            box-sizing: border-box;
        }

        .dates-section {
            display: table-cell;
            text-align: left;
            vertical-align: bottom;
        }

        .date-item {
            margin-bottom: 12px;
            font-size: 14px;
            color: #555;
        }

        .date-item strong {
            color: var(--primary-color);
            font-size: 16px;
            display: block;
            margin-top: 4px;
        }

        .signature-section {
            display: table-cell;
            text-align: center;
            width: 280px;
            vertical-align: bottom;
        }

        .signature-line {
            border-bottom: 1px solid var(--text-main);
            height: 50px;
            margin-bottom: 15px;
        }

        .signature-name {
            font-weight: 700;
            font-size: 16px;
            color: var(--text-main);
            margin: 0 0 4px 0;
        }

        .signature-title {
            font-size: 13px;
            color: #666;
            margin: 0;
            font-weight: 500;
        }

        /* Tabela de Produtos (Anexo) */
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            text-align: left;
        }

        .products-table th, .products-table td {
            padding: 12px 15px;
            border: 1px solid #ddd;
        }

        .products-table th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .products-table td {
            font-size: 14px;
            color: #333;
        }

        .products-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .anexo-title {
            text-align: left;
            color: var(--primary-color);
            font-size: 24px;
            margin-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
            font-family: 'Playfair Display', Georgia, serif;
        }

        @media print {
            body { 
                background: none; 
                padding: 0; 
                margin: 0;
            }
            .certificate-container { 
                box-shadow: none; 
                border-color: #000; 
                outline: none;
                max-width: 100%;
                width: 100vw;
                padding: 40px;
                box-sizing: border-box;
                border: 2px solid #000;
                margin-bottom: 0;
            }
            .certificate-container::before {
                border-color: #000;
            }
            .certificate-container::after {
                color: rgba(0, 0, 0, 0.05); /* Mais suave para não atrapalhar leitura */
            }
            .page-break {
                page-break-before: always;
                border-top: none;
            }
            .products-table th {
                background-color: #e0e0e0 !important;
                color: #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
</head>
<body>

<!-- PÁGINA 1: CERTIFICADO -->
<div class="certificate-container">
    <div class="certificate-content">
        
        <div class="header">
            <div class="logo-placeholder certificadora">
                Logo da<br>Certificadora<br>(Ex: IBD)
            </div>
            
            <div class="header-titles">
                <h3>Programa de Certificação</h3>
                <h1>Certificado de Conformidade Orgânica</h1>
                <div class="certificate-number">Nº do Certificado: <?php echo htmlspecialchars($certificado['numero']); ?></div>
            </div>
            
            <div class="logo-placeholder">
                Selo<br>SisOrg<br>Brasil
            </div>
        </div>

        <div class="body-text">
            <div class="certify-text">
                Certificamos que a unidade de produção / empresa:
            </div>

            <div class="producer-info">
                <div class="producer-name"><?php echo htmlspecialchars($dados_produtor['nome']); ?></div>
                <p class="producer-details">CNPJ / CPF: <?php echo htmlspecialchars($dados_produtor['cnpj']); ?></p>
                <p class="producer-details">Endereço: <?php echo htmlspecialchars($dados_produtor['endereco']); ?></p>
                <p class="producer-details"><?php echo htmlspecialchars($dados_produtor['cidade_uf']); ?></p>
            </div>

            <div class="compliance-text">
                Foi inspecionada, auditada e considerada em conformidade com as exigências técnicas para a produção, processamento e comercialização de produtos orgânicos, conforme estabelecido pelas normas:
            </div>

            <div class="standards-box">
                <?php echo htmlspecialchars($certificado['normas']); ?>
            </div>

            <div class="scope-details">
                <div class="scope-item">
                    <span class="scope-label">Escopo da Certificação</span>
                    <span class="scope-value"><?php echo htmlspecialchars($certificado['escopo']); ?></span>
                </div>
                <div class="scope-item">
                    <span class="scope-label">Produtos Certificados</span>
                    <span class="scope-value">Ver Anexo I (Lista de Produtos)</span>
                </div>
            </div>
        </div>

        <div class="footer">
            <div class="dates-section">
                <div class="date-item">
                    Local e Data de Emissão: 
                    <strong><?php echo htmlspecialchars($certificado['local']); ?>, <?php echo htmlspecialchars($certificado['data_emissao']); ?></strong>
                </div>
                <div class="date-item" style="margin-top: 20px;">
                    Válido até: 
                    <strong><?php echo htmlspecialchars($certificado['data_validade']); ?></strong>
                </div>
            </div>

            <div class="signature-section">
                <div class="signature-line"></div>
                <p class="signature-name">Dr. João Silva</p>
                <p class="signature-title">Diretor de Certificação</p>
                <p class="signature-disclaimer" style="font-size: 10px; color: #888; margin-top: 10px; line-height: 1.3;">
                    A validade e autenticidade deste certificado estão condicionadas a auditorias de manutenção periódicas.
                </p>
            </div>
        </div>

    </div>
</div>

<!-- PÁGINA 2: ANEXO COM LISTA DE PRODUTOS -->
<div class="certificate-container page-break">
    <div class="certificate-content">
        
        <h2 class="anexo-title">Anexo I - Lista de Produtos Certificados</h2>
        
        <div style="text-align: left; margin-bottom: 25px; color: #444; font-size: 15px;">
            <p style="margin: 5px 0;"><strong>Produtor Titular:</strong> <?php echo htmlspecialchars($dados_produtor['nome']); ?></p>
            <p style="margin: 5px 0;"><strong>Nº do Certificado Relacionado:</strong> <?php echo htmlspecialchars($certificado['numero']); ?></p>
            <p style="margin: 5px 0;"><strong>Data de Validade:</strong> <?php echo htmlspecialchars($certificado['data_validade']); ?></p>
        </div>

        <table class="products-table">
            <thead>
                <tr>
                    <th style="width: 50px;">Item</th>
                    <th>Produto Cultivado / Processado</th>
                    <th>Área / Volume Estimado</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($produtos) && is_array($produtos)): ?>
                    <?php foreach ($produtos as $index => $produto): ?>
                        <tr>
                            <td><?php echo str_pad($index + 1, 2, '0', STR_PAD_LEFT); ?></td>
                            <td><strong><?php echo htmlspecialchars($produto['nome']); ?></strong></td>
                            <td><?php echo htmlspecialchars($produto['area_estimativa']); ?></td>
                            <td style="color: var(--primary-color); font-weight: 600;"><?php echo htmlspecialchars($produto['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 30px;">Nenhum produto certificado encontrado para este produtor.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div style="margin-top: 50px; text-align: center; font-size: 12px; color: #666; border-top: 1px dashed #ccc; padding-top: 20px;">
            Este anexo é parte integrante e inseparável do certificado <?php echo htmlspecialchars($certificado['numero']); ?>. <br>Sua validade está condicionada à validade do documento principal.
        </div>
    </div>
</div>

</body>
</html>
