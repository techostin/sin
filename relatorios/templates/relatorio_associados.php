<?php
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('America/Sao_Paulo');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title><?php echo htmlspecialchars($titulo ?? 'Relatório de Associados', ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        @page { margin: 20mm 10mm 15mm 10mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #333; margin: 0; padding: 0; }
        .header { text-align: center; border-bottom: 2px solid #2c3e50; padding-bottom: 10px; margin-bottom: 12px; }
        .header h1 { margin: 0 0 4px 0; font-size: 16pt; color: #2c3e50; }
        .subtitle { margin: 0; font-size: 10pt; color: #555; }
        .meta { margin-top: 8px; font-size: 9pt; color: #666; }
        .filters { margin: 8px 0 12px; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; font-size: 8pt; }
        thead { display: table-header-group; }
        th, td { border: 1px solid #d8d8d8; padding: 6px 7px; vertical-align: top; }
        th { background: #2c3e50; color: #fff; font-size: 7.5pt; text-transform: uppercase; }
        tbody tr:nth-child(even) { background: #f6f8fa; }
        .status { display: inline-block; border-radius: 10px; padding: 2px 8px; font-size: 8pt; font-weight: bold; }
        .status-ativo { background: #d4edda; color: #155724; }
        .status-inativo { background: #f8d7da; color: #721c24; }
        .status-pendente { background: #fff3cd; color: #856404; }
        .assinatura { margin: 20px auto 0; width: 320px; text-align: center; font-size: 9pt; }
        .assinatura .linha { border-top: 1px solid #111; margin-bottom: 6px; }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo htmlspecialchars($titulo ?? 'Relatório de Associados', ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="subtitle"><?php echo htmlspecialchars($associacao_razao_social ?? 'Associação', ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="meta">Gerado em: <?php echo htmlspecialchars($data_geracao ?? date('d/m/Y H:i:s'), ENT_QUOTES, 'UTF-8'); ?> | Total: <?php echo (int)($total_registros ?? 0); ?></p>
    </div>

    <div class="filters">
        <?php if (!empty($filtros['data_inicio']) || !empty($filtros['data_fim'])): ?>
            <p><strong>Período:</strong> <?php echo htmlspecialchars($filtros['data_inicio'] ?: '-', ENT_QUOTES, 'UTF-8'); ?> até <?php echo htmlspecialchars($filtros['data_fim'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <p>
            <strong>Ordenação:</strong>
            <?php
            $campo = str_replace('_', ' ', (string)($filtros['ordenar_por'] ?? 'nome'));
            $ordem = (isset($filtros['direcao']) && strtoupper((string)$filtros['direcao']) === 'DESC') ? 'decrescente' : 'crescente';
            echo htmlspecialchars(ucfirst($campo) . ' (' . $ordem . ')', ENT_QUOTES, 'UTF-8');
            ?>
        </p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Nome Completo</th>
                <th>CPF</th>
                <th>RG</th>
                <th>CNPJ</th>
                <th>Contatos</th>
                <th>Endereço</th>
                <th>Cidade/UF</th>
                <th>Associação</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php $contador = 1; ?>
            <?php foreach (($associados ?? []) as $associado): ?>
                <tr>
                    <td><?php echo $contador++; ?></td>
                    <td><?php echo htmlspecialchars($associado['nome_completo'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($associado['cpf'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($associado['rg'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($associado['cnpj'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <?php
                        $contatos = [];
                        if (!empty($associado['telefone'])) $contatos[] = 'T: ' . $associado['telefone'];
                        if (!empty($associado['celular'])) $contatos[] = 'C: ' . $associado['celular'];
                        if (!empty($associado['email'])) $contatos[] = 'E: ' . $associado['email'];
                        echo htmlspecialchars($contatos ? implode(' | ', $contatos) : '-', ENT_QUOTES, 'UTF-8');
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($associado['endereco_completo'] ?: 'Endereço não informado', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <?php
                        $cidadeUf = [];
                        if (!empty($associado['cidade'])) $cidadeUf[] = $associado['cidade'];
                        if (!empty($associado['estado'])) $cidadeUf[] = $associado['estado'];
                        echo htmlspecialchars($cidadeUf ? implode('/', $cidadeUf) : '-', ENT_QUOTES, 'UTF-8');
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($associado['associacao_nome'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td style="text-align:center;">
                        <?php
                        $status = strtolower((string)($associado['status_descricao'] ?? 'pendente'));
                        $statusClass = $status === 'ativo' ? 'status-ativo' : ($status === 'inativo' ? 'status-inativo' : 'status-pendente');
                        ?>
                        <span class="status <?php echo $statusClass; ?>"><?php echo htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="assinatura">
        <div class="linha"></div>
        <div><strong><?php echo htmlspecialchars($coordenador_nome ?? 'Coordenador', ENT_QUOTES, 'UTF-8'); ?></strong></div>
        <div><?php echo htmlspecialchars($coordenador_cargo ?? 'Coordenação', ENT_QUOTES, 'UTF-8'); ?></div>
        <div><?php echo date('d/m/Y'); ?></div>
    </div>
</body>
</html>
