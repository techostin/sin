<?php
/**
 * Template: Declaração de Compromisso (Certificação Orgânica)
 * Local: app/modulos/roteiros_inspecao/templates/declaracao_compromisso.php
 */

if (!isset($dados_produtor)) {
    $dados_produtor = [
        'nome' => 'Nome do Produtor',
        'cnpj' => '000.000.000-00',
        'endereco' => 'Endereço',
        'cidade_uf' => 'Cidade - UF'
    ];
}
if (!isset($certificado)) {
    $certificado = [
        'numero' => '00000',
        'local' => 'Local',
        'data_emissao' => date('d/m/Y')
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Declaração de Compromisso</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            margin: 40px;
            color: #333;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            margin-bottom: 50px;
            border-bottom: 2px solid #1b5e20;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #1b5e20;
            font-size: 24px;
            margin: 0;
            text-transform: uppercase;
        }
        .header h2 {
            font-size: 16px;
            color: #555;
            font-weight: normal;
            margin-top: 5px;
        }
        .content {
            text-align: justify;
            font-size: 16px;
        }
        .content p {
            margin-bottom: 20px;
        }
        .highlight {
            font-weight: bold;
        }
        .footer {
            margin-top: 80px;
            text-align: center;
        }
        .signature-line {
            width: 300px;
            border-bottom: 1px solid #333;
            margin: 0 auto 10px auto;
        }
        .date {
            text-align: right;
            margin-top: 40px;
            margin-bottom: 60px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Declaração de Compromisso</h1>
        <h2>Conformidade com a Produção Orgânica (Lei nº 10.831/2003)</h2>
    </div>

    <div class="content">
        <p>
            Eu, <span class="highlight"><?php echo htmlspecialchars($dados_produtor['nome']); ?></span>, inscrito(a) no CPF/CNPJ sob o nº <span class="highlight"><?php echo htmlspecialchars($dados_produtor['cnpj']); ?></span>, residente e domiciliado(a) em <span class="highlight"><?php echo htmlspecialchars($dados_produtor['endereco'] . ', ' . $dados_produtor['cidade_uf']); ?></span>, declaro formalmente, para os devidos fins de certificação orgânica e sob as penas da lei, que:
        </p>
        
        <ol>
            <li style="margin-bottom: 15px;">Conheço e compreendo os regulamentos técnicos da produção orgânica brasileira, especialmente a Lei nº 10.831 de 2003 e os seus regulamentos (Instruções Normativas do MAPA).</li>
            <li style="margin-bottom: 15px;">Comprometo-me a aplicar os princípios e normas da produção orgânica em toda a minha unidade de produção listada no escopo do Certificado nº <span class="highlight"><?php echo htmlspecialchars($certificado['numero']); ?></span>.</li>
            <li style="margin-bottom: 15px;">Asseguro que não utilizo e não utilizarei agrotóxicos, fertilizantes químicos de síntese ou sementes/mudas transgênicas (OGM) em minhas áreas certificadas.</li>
            <li style="margin-bottom: 15px;">Permitirei o livre acesso dos inspetores, auditores da certificadora e agentes de fiscalização do Ministério da Agricultura (MAPA) à minha unidade de produção, bem como a toda documentação necessária (caderno de campo, notas fiscais, etc.) para verificação de conformidade.</li>
            <li style="margin-bottom: 15px;">Comprometo-me a informar imediatamente à certificadora ou ao SPG sobre qualquer alteração em meu sistema de produção ou sobre o uso acidental de substâncias não permitidas.</li>
        </ol>

        <p>
            Estou ciente de que o não cumprimento deste compromisso sujeitará a minha unidade de produção à suspensão ou cancelamento da certificação orgânica, além das penalidades previstas na legislação vigente.
        </p>
    </div>

    <div class="date">
        <?php echo htmlspecialchars($certificado['local']); ?>, <?php echo htmlspecialchars($certificado['data_emissao']); ?>
    </div>

    <div class="footer">
        <div class="signature-line"></div>
        <p class="highlight"><?php echo htmlspecialchars($dados_produtor['nome']); ?></p>
        <p>Produtor / Responsável Legal</p>
    </div>

</body>
</html>
