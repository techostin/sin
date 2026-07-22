$dest = "c:/xampp/htdocs/app/recicla"

# Arquivos de diagnostico / check / test
$files = @(
    "check_additional_tables.php",
    "check_atividades_table.php",
    "check_contas_data.php",
    "check_counts.php",
    "check_entidades.php",
    "check_nc.php",
    "check_pagamentos_data.php",
    "check_schema.php",
    "check_table.php",
    "check_cert_table.php",
    "check_plano_table.php",
    "check_contas_cols.php",
    "create_view.php",
    "criar_tabelas.php",
    "describe_pagamentos.php",
    "diagnostico_eventos.php",
    "list_all_contas.php",
    "list_cols.php",
    "list_tables.php",
    "migrate_insumos_uuid.php",
    "migrate_assinatura.php",
    "seed_roteiro.php",
    "show_create_contas.php",
    "test_schema.php",
    "testar_api_cidades.php",
    "testar_eventos.php",
    "teste_ajax.php",
    "teste_api_eventos.php",
    "teste_db.php",
    "teste_evento.php",
    "tmp_check_db.php",
    "update_reports.php",
    "verificar_estrutura_estados.php",
    "verificar_tabela_eventos.php",
    "verificar_tabela.php",
    "verificar_usuario.php",
    "translate_gsd_skills.py"
)

foreach ($f in $files) {
    $src = "c:/xampp/htdocs/app/$f"
    if (Test-Path $src) {
        Move-Item -Path $src -Destination $dest -Force
        Write-Output "Movido: $f"
    } else {
        Write-Output "Nao encontrado: $f"
    }
}

# Arquivos SQL desnecessários na raiz (scripts de migração executados)
$sqlFiles = @(
    "alterar_sigla_associacao.sql",
    "alterar_tabela_eventos_confirmados.sql",
    "create_view_caderno_campo.sql",
    "criar_tabela_eventos.sql",
    "criar_tabelas_faltantes.sql",
    "sisagro.sql",
    "sisagro-novo.sql",
    "sql_notificacoes.sql",
    "view_relatorio_caderno_campo.sql"
)

foreach ($f in $sqlFiles) {
    $src = "c:/xampp/htdocs/app/$f"
    if (Test-Path $src) {
        Move-Item -Path $src -Destination $dest -Force
        Write-Output "Movido: $f"
    }
}

# Arquivos .md (documentação / instruções desnecessárias na raiz)
$mdFiles = @(
    "Avaliacao_de_conformidade.md",
    "CORRECAO_LOGIN.md",
    "CORRECOES_ARQUIVOS_EDITAR.md",
    "CORRECOES_REALIZADAS.md",
    "IMPLEMENTACAO_UPLOAD_IMAGENS.md",
    "IMPLEMENTACAO_VISUALIZACAO_IMAGENS.md",
    "implementation_sinporg.md",
    "INSTRUCOES_CORRECAO_RELATORIO.md",
    "INSTRUCOES_RELATORIOS_PDF.md",
    "instrucoes_view_relatorio.md",
    "PRD.md",
    "RELATORIO_IMPLEMENTACAO_SINPORG.md",
    "sisagro-8.md"
)

foreach ($f in $mdFiles) {
    $src = "c:/xampp/htdocs/app/$f"
    if (Test-Path $src) {
        Move-Item -Path $src -Destination $dest -Force
        Write-Output "Movido: $f"
    }
}

# Pasta xampp inteira (duplicata)
$xamppSrc = "c:/xampp/htdocs/app/xampp"
if (Test-Path $xamppSrc) {
    Move-Item -Path $xamppSrc -Destination $dest -Force
    Write-Output "Movido: pasta xampp/"
}

# Pasta scratch (scripts de desenvolvimento temporários)
$scratchSrc = "c:/xampp/htdocs/app/scratch"
if (Test-Path $scratchSrc) {
    Move-Item -Path $scratchSrc -Destination $dest -Force
    Write-Output "Movido: pasta scratch/"
}

Write-Output "=== Concluido ==="

