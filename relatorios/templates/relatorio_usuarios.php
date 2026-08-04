<?php
// Template de relatório de usuários usado por ReportManager::gerarRelatorioPDF
// Variáveis esperadas: $dados (array), $filtros (array), $assetsUrl (string), $titulo (string)
?>
<div style="font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #333;">
    <div style="text-align:center; border-bottom:3px solid #1B5E20; padding-bottom:10px; margin-bottom:15px;">
        <?php if (!empty($assetsUrl)): ?>
            <img src="<?php echo htmlspecialchars($assetsUrl . '/img/logo.png'); ?>" alt="logo" style="height:50px; margin-bottom:6px;" />
        <?php endif; ?>
        <div style="font-size:16pt; font-weight:bold; color:#1B5E20; margin-top:6px;"><?php echo htmlspecialchars($titulo); ?></div>
        <div style="font-size:9pt; color:#666; margin-top:4px;">Sistema Integrado de Produção Orgânica</div>
        <div style="font-size:9pt; color:#666; margin-top:2px;">Emissão: <?php echo date('d/m/Y H:i:s'); ?></div>
    </div>

    <table width="100%" style="border-collapse:collapse; font-size:9pt;">
        <thead>
            <tr>
                <th style="background:#1B5E20; color:#fff; padding:6px; border:1px solid #ddd; text-align:left;">ID</th>
                <th style="background:#1B5E20; color:#fff; padding:6px; border:1px solid #ddd; text-align:left;">Nome</th>
                <th style="background:#1B5E20; color:#fff; padding:6px; border:1px solid #ddd; text-align:left;">E-mail</th>
                <th style="background:#1B5E20; color:#fff; padding:6px; border:1px solid #ddd; text-align:left;">Tipo</th>
                <th style="background:#1B5E20; color:#fff; padding:6px; border:1px solid #ddd; text-align:left;">Status</th>
                <th style="background:#1B5E20; color:#fff; padding:6px; border:1px solid #ddd; text-align:left;">Último Acesso</th>
                <th style="background:#1B5E20; color:#fff; padding:6px; border:1px solid #ddd; text-align:left;">CPF</th>
                <th style="background:#1B5E20; color:#fff; padding:6px; border:1px solid #ddd; text-align:left;">RG</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($dados)): ?>
                <tr>
                    <td colspan="8" style="padding:10px; text-align:center; color:#999;">Nenhum registro encontrado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($dados as $row): ?>
                    <tr>
                        <td style="padding:6px; border:1px solid #ddd; vertical-align:top;"><?php echo htmlspecialchars($row['id'] ?? '-'); ?></td>
                        <td style="padding:6px; border:1px solid #ddd; vertical-align:top;"><?php echo htmlspecialchars($row['nome'] ?? '-'); ?></td>
                        <td style="padding:6px; border:1px solid #ddd; vertical-align:top;"><?php echo htmlspecialchars($row['email'] ?? '-'); ?></td>
                        <td style="padding:6px; border:1px solid #ddd; vertical-align:top;"><?php echo htmlspecialchars(ucfirst($row['tipo'] ?? '-')); ?></td>
                        <td style="padding:6px; border:1px solid #ddd; vertical-align:top;"><?php echo htmlspecialchars(ucfirst($row['status'] ?? '-')); ?></td>
                        <td style="padding:6px; border:1px solid #ddd; vertical-align:top;"><?php echo !empty($row['ultimo_acesso']) ? date('d/m/Y H:i', strtotime($row['ultimo_acesso'])) : '-'; ?></td>
                        <td style="padding:6px; border:1px solid #ddd; vertical-align:top;"><?php echo htmlspecialchars($row['cpf'] ?? '-'); ?></td>
                        <td style="padding:6px; border:1px solid #ddd; vertical-align:top;"><?php echo htmlspecialchars($row['rg'] ?? '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div style="margin-top:30px; text-align:center;">
        <div style="width:360px; margin:0 auto 6px auto; border-top:2px solid #333; height:20px;"></div>
        <div style="font-weight:bold; font-size:10pt;"><?php echo htmlspecialchars($_SESSION['user_nome'] ?? ''); ?></div>
        <div style="font-size:9pt; color:#666;">Responsável Técnico</div>
    </div>
</div>
