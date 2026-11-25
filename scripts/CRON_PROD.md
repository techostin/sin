# ============================================================================
# CRON — Sincronização das tabelas externas em PRODUÇÃO
# ============================================================================
# Copie estas linhas para o crontab da hospedagem:
#   crontab -e   (ou em cPanel/WHM -> Cron Jobs, ou Plesk -> Schedule Tasks)
#
# Requisitos:
#   - PHP CLI disponível no servidor (ajuste PHP_BIN se preciso)
#   - Ajuste o caminho /home/edmilson/htdocs/... para o diretório real do app
#   - As tabelas `cnpo` e `agroindustria_existentes` já devem existir no banco
#     (rodar previamente: php dev-utils/migrate_peaf_cnpo.php)
#
# ----------------------------------------------------------------------------
# 1) CNPO (MAPA) —— MAPA publica atualizações nos dias 05, 15 e 25 de cada mês
# Rodamos 1h após a publicação esperada (08:00 = horário local do servidor).
# ----------------------------------------------------------------------------
0 8 5,15,25 * *  cd /home/edmilson/htdocs/sinporg.com.br/app && php dev-utils/atualizar_cnpo.php >> logs/cron_externos.log 2>&1

# ----------------------------------------------------------------------------
# 2) PEAF (SDR-RS) —— SDR publica mensalmente (dia 1 como definição)
# ----------------------------------------------------------------------------
0 8 1 * *  cd /home/edmilson/htdocs/sinporg.com.br/app && php dev-utils/atualizar_peaf.php >> logs/cron_externos.log 2>&1

# ----------------------------------------------------------------------------
# 3) (Alternativa simplificada) Rodar TUDO diariamente — scripts são
#    idempotentes e só processam versões novas; seguro para rodar todo dia.
# ----------------------------------------------------------------------------
# 0 6 * * *  cd /home/edmilson/htdocs/sinporg.com.br/app && php dev-utils/atualizar_cnpo.php >> logs/cron_externos.log 2>&1 && php dev-utils/atualizar_peaf.php >> logs/cron_externos.log 2>&1

# ============================================================================
# OPÇÃO COM O SCRIPT RUNNER (recomendado se a hospedagem permitir executar .sh)
# ============================================================================
# 0 8 5,15,25 * *  /home/edmilson/htdocs/sinporg.com.br/app/scripts/sincronizar_externos.sh cnpo
# 0 8 1 * *        /home/edmilson/htdocs/sinporg.com.br/app/scripts/sincronizar_externos.sh peaf