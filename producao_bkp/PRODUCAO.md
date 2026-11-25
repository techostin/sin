# ============================================================================
# GUIA DE AJUSTES PARA PRODUÇÃO — Sincronização das tabelas CNPO e PEAF
# ============================================================================
#
# OBJETIVO
#   Manter as tabelas `cnpo` e `agroindustria_existentes` sempre atualizadas
#   com as fontes oficiais (MAPA e SDR-RS) via cron.
#
# SUMÁRIO DO QUE VOCÊ PRECISA FAZER, NA ORDEM:
#   1. Confirmar caminho do app no servidor (§3)
#   2. Ajustar credenciais do banco (§4)
#   3. Verificar PHP CLI e extensões (§5)
#   4. Garantir permissões de escrita (§6)
#   5. Confirmar PHP cURL/allow_url_fopen e saída HTTPS (§7)
#   6. Criar as tabelas no banco de produção (§8)
#   7. Instalar o cron (§9)
#   8. Testar o cron manualmente (§10)
#   9. Monitorar os logs (§11)
#
# ============================================================================
# 1. O QUE ESTE PACOTE CONTÉM
# ============================================================================
#   producao_bkp/
#   ├── PRODUCAO.md                     ← este guia (passo a passo)
#   ├── sincronizar_externos.sh         ← runner opcional (CNPO+PEAF com log)
#   └── CRON_PROD.txt                   ← linhas de cron prontas p/ colar
#
#   Scripts de sincronização (ficam no app, fora deste pacote):
#     dev-utils/atualizar_cnpo.php   -> tabela `cnpo`
#     dev-utils/atualizar_peaf.php   -> tabela `agroindustria_existentes`
#     dev-utils/migrate_peaf_cnpo.php -> cria as tabelas (primeira carga)
#
# ============================================================================
# 2. ANTES DE COMEÇAR — LISTA DE PRÉ-REQUISITOS DO SERVIDOR
# ============================================================================
#   [ ] Acesso SSH ou painel com "Cron Jobs" (cPanel/Plesk)
#   [ ] PHP CLI (>8.1) com extensões: php-xml, php-zip, php-mbstring, php-curl
#   [ ] PDO MySQL (pdo_mysql) habilitado
#   [ ] Composer instalado (vendor/ presente — veja §5.2)
#   [ ] Acesso HTTPS de saída para gov.br e sdr.rs.gov.br (sem bloqueio)
#
# ============================================================================
# 3. CONFIRMAR O CAMINHO DO APP NO SERVIDOR
# ============================================================================
#   O app será publicado via deploy.sh em:
#     edmilson@204.216.159.247:/home/edmilson/htdocs/.../app
#
#   No servidor, descubra o caminho absoluto da pasta `app`:
#     cd ~/htdocs ; ls            # localize a pasta do projeto
#     pwd                         # caminho absoluto
#
#   Exemplo provável:
#     CAMINHO_APP=/home/edmilson/htdocs/sinporg.com.br/app
#
#   Depois de confirmar, use CAMINHO_APP em TODOS os comandos abaixo.
#
# ============================================================================
# 4. AJUSTAR CREDENCIAIS DO BANCO (config/config.php)
# ============================================================================
#   ATENÇÃO: config.php está versionado com credenciais de DESENVOLVIMENTO
#   (root / senha vazia / sisagro). Em produção, sobrescreva localmente:
#
#     define('DB_HOST', 'localhost');
#     define('DB_USER', 'USUARIO_REAL');
#     define('DB_PASS', 'SENHA_REAL');
#     define('DB_NAME', 'BANCO_REAL');
#
#   NÃO versionar as credenciais reais. Mantê-las apenas no arquivo do servidor.
#
# ============================================================================
# 5. VERIFICAR PHP CLI E EXTENSÕES
# ============================================================================
#   5.1 Descobrir o binário do PHP CLI
#       which php                # caso exista no PATH
#       /usr/local/bin/php -v    # ou versões específicas do cPanel (ea-php):
#       ls /opt/cpanel/ea-php*   # ex.: /opt/cpanel/ea-php82/root/usr/bin/php
#
#   5.2 Garantir vendor/ presente (PhpSpreadsheet)
#       O composer.json requer phpoffice/phpspreadsheet. No servidor:
#         cd $CAMINHO_APP && composer install --no-dev
#       Ou garanta que vendor/ já veio no deploy.
#
#   5.3 Testar se o ambiente de produção tem tudo:
#         cd $CAMINHO_APP
#         /caminho/do/php -m | grep -iE "xml|zip|mbstring|curl|pdo_mysql"
#
#   Se faltar "curl", tudo bem: o script cai para file_get_contents, desde que
#   allow_url_fopen = On (veja §7). PhpSpreadsheet exige xml+zip, esses não podem faltar.
#
# ============================================================================
# 6. PERMISSÕES DE ESCRITA (storage/externos/ e logs/)
# ============================================================================
#   Os scripts baixam as planilhas para storage/externos/ e gravam log em logs/.
#   Garanta escrita para o usuário que roda o cron:
#     chmod -R 775 $CAMINHO_APP/storage/externos $CAMINHO_APP/logs
#   (ou ajuste o dono: chown -R usuário:grupo ...)
#
# ============================================================================
# 7. PHP cURL / allow_url_fopen E SAÍDA HTTPS
# ============================================================================
#   Os scripts já usam cURL quando disponível (recomendado). Verifique:
#     /caminho/do/php -i | grep -i "allow_url_fopen"
#     /caminho/do/php -m | grep -i curl
#   Confirme que o servidor consegue acessar as fontes (teste no servidor):
#     curl -sI https://www.sdr.rs.gov.br/agroindustria-familiar | head -1
#     curl -sI https://www.gov.br/agricultura/pt-br/assuntos/sustentabilidade/organicos/cadastro-nacional-de-produtores-organicos-cnpo | head -1
#
# ============================================================================
# 8. CRIAR AS TABELAS NO BANCO DE PRODUÇÃO (primeira carga)
# ============================================================================
#   Rodar UMA VEZ, manualmente, antes de instalar o cron:
#     cd $CAMINHO_APP
#     /caminho/do/php dev-utils/migrate_peaf_cnpo.php
#   Isso cria `agroindustria_existentes`, `cnpo` e as tabelas de histórico
#   (`agroindustria_atualizacoes`, `cnpo_atualizacoes`), já com a carga atual.
#   Opcionalmente, para recriar do zero: adicione o argumento --drop=1.
#
# ============================================================================
# 9. INSTALAR O CRON
# ============================================================================
#   Escolha UMA das opções abaixo:
#
#   --- 9.A Via cron direto (recomendado, sem depender de .sh) ---------------
#     crontab -e    # e adicione as linhas do arquivo CRON_PROD.txt
#     (ou cPanel > Cron Jobs / Plesk > Schedule Tasks)
#
#   --- 9.B Via runner (se a hospedagem permite executar .sh) ---------------
#     Copie sincronizar_externos.sh para dentro do app (ou use o do app:
#     scripts/sincronizar_externos.sh). Depois:
#       chmod +x $CAMINHO_APP/scripts/sincronizar_externos.sh
#       crontab -e  # e adicione:
#       0 8 5,15,25 * * $CAMINHO_APP/scripts/sincronizar_externos.sh cnpo
#       0 8 1 * *       $CAMINHO_APP/scripts/sincronizar_externos.sh peaf
#
# ============================================================================
# 10. TESTAR O CRON MANUALMENTE
# ============================================================================
#   Depois de instalar, confirme que tudo funciona rodando na mão:
#     cd $CAMINHO_APP
#     /caminho/do/php dev-utils/atualizar_cnpo.php
#     /caminho/do/php dev-utils/atualizar_peaf.php
#   Saída esperada (com dados já sincronizados):
#     "A versão ... já está sincronizada. Nada a fazer."
#   Para testar um carregamento DE VERDADE sem risco, apague o registro do
#   histórico e rode de novo (recarrega a tabela a partir da planilha local):
#     DELETE FROM cnpo_atualizacoes;
#     /caminho/do/php dev-utils/atualizar_cnpo.php
#   (faz o mesmo para PEAF via agroindustria_atualizacoes)
#
# ============================================================================
# 11. MONITORAR OS LOGS
# ============================================================================
#   Cada execução registra em:
#     logs/cron_externos.log    (se usar o runner .sh)
#   E no banco:
#     cnpo_atualizacoes / agroindustria_atualizacoes
#   Verifique periodicamente a última versão e o total de registros:
#     SELECT arquivo, data_planilha, total_registros, criado_em FROM cnpo_atualizacoes ORDER BY criado_em DESC LIMIT 5;
#     SELECT arquivo, total_registros, criado_em FROM agroindustria_atualizacoes ORDER BY criado_em DESC LIMIT 5;
#
# ============================================================================
# 12. DATAS OFICIAIS DE ATUALIZAÇÃO (para alinhar o cron)
# ============================================================================
#   CNPO (MAPA): dias 05, 15 e 25 de cada mês (publicado no dia seguinte).
#   PEAF (SDR-RS): mensalmente (arquivo novo publicado na página oficial).
#   Os scripts leem SEMPRE a versão mais recente da página oficial e são
#   idempotentes (não reprocessam uma versão já gravada).
#
# ============================================================================