<?php

/** @var array<string, mixed> $data */
$nome = htmlspecialchars((string) ($data['associado_nome'] ?? 'Produtor'), ENT_QUOTES, 'UTF-8');
$numero = htmlspecialchars((string) $data['numero'], ENT_QUOTES, 'UTF-8');
$lote = htmlspecialchars((string) ($data['lote_codigo'] ?? $data['lote_uuid'] ?? ''), ENT_QUOTES, 'UTF-8');
$emitido = htmlspecialchars((string) $data['emitido_em'], ENT_QUOTES, 'UTF-8');
$verificacao = htmlspecialchars((string) ($data['verificacao_url'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
body { font-family: sans-serif; color: #1f3a2e; }
.titulo { font-size: 26px; letter-spacing: 2px; color: #146c43; }
.sub { font-size: 14px; color: #555; }
.box { border: 2px solid #198754; padding: 24px; margin-top: 24px; }
.label { font-size: 11px; color: #666; text-transform: uppercase; }
.valor { font-size: 15px; margin-bottom: 14px; }
.rodape { margin-top: 34px; font-size: 10px; color: #666; }
.assinatura { margin-top: 42px; border-top: 1px solid #333; width: 280px; padding-top: 6px; font-size: 11px; }
</style>
</head>
<body>
    <div class="titulo">CERTIFICADO DE PRODUÇÃO ORGÂNICA</div>
    <div class="sub">Documento eletrônico de rastreabilidade — SINPORG</div>

    <div class="box">
        <div class="label">Produtor / Associado</div>
        <div class="valor"><?= $nome ?></div>

        <div class="label">Número do documento</div>
        <div class="valor"><?= $numero ?></div>

        <div class="label">Lote rastreável</div>
        <div class="valor"><?= $lote ?></div>

        <div class="label">Data de emissão</div>
        <div class="valor"><?= $emitido ?></div>
    </div>

    <p>O lote acima possui cadeia de custódia registrada no T1 — Rastreabilidade &amp; Cadeia de
    Custódia, com eventos e vínculos auditáveis.</p>

    <div class="rodape">
        Verificação pública: <?= $verificacao ?><br>
        Este documento possui hash de integridade e assinatura HMAC armazenados no sistema.
        A validade legal depende da certificadora/OCP responsável e das normas aplicáveis.
    </div>

    <div class="assinatura">
        Responsável técnico / certificador<br>
        <?= htmlspecialchars((string) ($data['emitido_por_nome'] ?? 'Sistema SINPORG'), ENT_QUOTES, 'UTF-8') ?>
    </div>
</body>
</html>
