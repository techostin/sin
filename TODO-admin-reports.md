# TODO - Exportação de Relatórios Administrativos

## Etapas
- [x] 1. Analisar estrutura atual (relatorios_admin.php, ReportManager.php, exportar_relatorio.php)
- [x] 2. Criar api/relatorios/admin_export.php - endpoint de exportação Excel e PDF
- [x] 3. Atualizar modulos/caderno_campo/relatorios_admin.php - corrigir botões de exportação
- [x] 4. Corrigir rota - usar acesso direto ao PHP em vez de URL amigável (que não estava funcionando para /api/relatorios/*)
- [x] 5. Testar: URL acessível via servidor web (retorna "Acesso restrito a gestores" sem sessão, o que é esperado)
