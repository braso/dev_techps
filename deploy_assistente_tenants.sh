#!/usr/bin/env bash
#
# Prepara a base do Assistente Virtual em todos os tenants.
#
# Cada tenant tem o próprio .env e o próprio banco MySQL. Este script entra
# na pasta de cada tenant e roda a migração + ingestão usando a conexão
# daquele .env. Não existe uma base central.
#
# Uso (no terminal do cPanel, após o deploy):
#   bash deploy_assistente_tenants.sh
#   bash deploy_assistente_tenants.sh --enabled-only
#   BASE=/caminho/gestaodeponto bash deploy_assistente_tenants.sh
#
# Variáveis opcionais:
#   BASE      pasta que contém as pastas dos clientes
#             (padrão: /home/tech1694/public_html/gestaodeponto)
#   PHP_BIN   binário do PHP CLI (padrão: php)
#   LOG       arquivo de log (padrão: ~/deploy_assistente.log)
#
# O script é idempotente: pode ser rodado novamente após um deploy ou após
# a restauração do banco de um cliente.
set -u

BASE="${BASE:-/home/tech1694/public_html/gestaodeponto}"
PHP_BIN="${PHP_BIN:-php}"
LOG="${LOG:-$HOME/deploy_assistente.log}"
ENABLED_ONLY=0

while [ $# -gt 0 ]; do
    case "$1" in
        --enabled-only)
            ENABLED_ONLY=1
            ;;
        --base)
            shift
            BASE="${1:-$BASE}"
            ;;
        -h|--help)
            sed -n '2,30p' "$0"
            exit 0
            ;;
        *)
            echo "Opção desconhecida: $1" >&2
            exit 2
            ;;
    esac
    shift
done

if [ ! -d "$BASE" ]; then
    echo "Pasta base não encontrada: $BASE" >&2
    exit 1
fi

: > "$LOG"
echo "Início: $(date '+%Y-%m-%d %H:%M:%S')" >> "$LOG"
echo "Base: $BASE" >> "$LOG"
echo "PHP: $("$PHP_BIN" -v 2>/dev/null | head -n1)" >> "$LOG"
echo "----------------------------------------" >> "$LOG"

total=0
ok=0
fail=0
skip=0
falhas=""

# Mesma ordem de busca do load_env.php do tenant.
env_efetivo() {
    dir="$1"
    if [ -n "${TECHPS_ENV_FILE:-}" ] && [ -f "$TECHPS_ENV_FILE" ]; then
        printf '%s\n' "$TECHPS_ENV_FILE"
        return 0
    fi
    for candidato in \
        "$(dirname "$dir")/../../techps.env" \
        "$(dirname "$dir")/../techps.env" \
        "$dir/.env" \
        "$(dirname "$dir")/.env"; do
        if [ -f "$candidato" ]; then
            printf '%s\n' "$candidato"
            return 0
        fi
    done
    return 1
}

for dir in "$BASE"/*/; do
    [ -d "$dir" ] || continue
    tenant="$(basename "$dir")"

    if [ ! -f "$dir/assistente_virtual/ingestao.php" ]; then
        skip=$((skip + 1))
        continue
    fi

    env_file="$(env_efetivo "$dir" || true)"
    if [ -z "$env_file" ]; then
        skip=$((skip + 1))
        continue
    fi

    enabled="$(grep -E '^[[:space:]]*ASSISTENTE_ENABLED[[:space:]]*=' "$env_file" 2>/dev/null | tail -n1 | sed 's/.*=//' | tr -d '[:space:]')"
    if [ "$ENABLED_ONLY" -eq 1 ] && [ "$enabled" != "1" ]; then
        printf '%-28s %s\n' "$tenant" "ignorado (ASSISTENTE_ENABLED=$enabled)"
        skip=$((skip + 1))
        continue
    fi

    total=$((total + 1))
    echo "=== $tenant | $(date '+%Y-%m-%d %H:%M:%S') | enabled=$enabled | env=$env_file ===" >> "$LOG"

    if (
        cd "$dir" || exit 1
        "$PHP_BIN" assistente_virtual/migrar.php &&
        "$PHP_BIN" assistente_virtual/ingestao.php --all &&
        "$PHP_BIN" assistente_virtual/ingestao.php --status
    ) >> "$LOG" 2>&1; then
        printf '%-28s %s\n' "$tenant" "OK"
        ok=$((ok + 1))
    else
        printf '%-28s %s\n' "$tenant" "FALHOU (veja o log)"
        fail=$((fail + 1))
        falhas="$falhas $tenant"
    fi
done

echo "----------------------------------------" >> "$LOG"
echo "Fim: $(date '+%Y-%m-%d %H:%M:%S') | OK=$ok Falhas=$fail Ignorados=$skip" >> "$LOG"

echo
echo "Tenants processados: $total | OK: $ok | Falhas: $fail | Ignorados: $skip"
[ -n "$falhas" ] && echo "Falhas:$falhas"
echo "Log completo: $LOG"

[ "$fail" -eq 0 ]
