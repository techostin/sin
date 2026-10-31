#!/bin/bash
#
# Sincronizador das tabelas de referência externa (produção)
# ==========================================================
# Executa as duas sincronizações oficiais:
#   1. CNPO (MAPA)        -> tabela cnpo
#   2. PEAF (SDR-RS)      -> tabela agroindustria_existentes
#
# Uso via cron (exemplos):
#   # CNPO: MAPA atualiza nos dias 05, 15 e 25 de cada mês
#   0 8 5,15,25 * *  $HOME/htdocs/sinporg.com.br/app/scripts/sincronizar_externos.sh cnpo
#
#   # PEAF: SDR-RS publica mensalmente
#   0 8 1 * *        $HOME/htdocs/sinporg.com.br/app/scripts/sincronizar_externos.sh peaf
#
#   # (opcional) rodar tudo diariamente — scripts são idempotentes,
#   # só processam versões novas
#   0 6 * * *        $HOME/htdocs/sinporg.com.br/app/scripts/sincronizar_externos.sh all
#
# Saída e erros são gravados em logs/cron_externos.log
# Ajuste APP_DIR e PHP_BIN conforme a hospedagem.

set -u

# --------------------------------------------------------------------
# Configuração — ajustar conforme ambiente de produção
# --------------------------------------------------------------------
APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
LOG_DIR="$APP_DIR/logs"
LOG_FILE="$LOG_DIR/cron_externos.log"

mkdir -p "$LOG_DIR"

# Redireciona toda a saída (stdout+stderr) para o arquivo de log.
exec >> "$LOG_FILE" 2>&1

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"
}

log "=== Sincronização externa iniciada (alvo: ${1:-all}) ==="

run_sync() {
    local script="$1"
    local nome="$2"
    if [ ! -f "$APP_DIR/$script" ]; then
        log "ERRO: script não encontrado -> $script"
        return 1
    fi
    log "--- $nome ---"
    if "$PHP_BIN" "$APP_DIR/$script" 2>&1; then
        log "$nome OK"
        return 0
    else
        log "$nome FALHOU (exit $?)"
        return 1
    fi
}

TARGET="${1:-all}"
RC=0

case "$TARGET" in
    cnpo)
        run_sync "dev-utils/atualizar_cnpo.php" "CNPO" || RC=1
        ;;
    peaf)
        run_sync "dev-utils/atualizar_peaf.php" "PEAF" || RC=1
        ;;
    all)
        run_sync "dev-utils/atualizar_cnpo.php" "CNPO" || RC=1
        run_sync "dev-utils/atualizar_peaf.php" "PEAF" || RC=1
        ;;
    *)
        log "Uso: $0 {cnpo|peaf|all}"
        RC=1
        ;;
esac

log "=== Sincronização externa finalizada (status: $RC) ==="
exit $RC