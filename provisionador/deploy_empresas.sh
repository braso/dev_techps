#!/bin/bash
# Deploy por varredura: copia os arquivos do sistema para TODA pasta de empresa que tenha .env.
# Empresa nova (criada pelo provisionador) entra no deploy sozinha, sem editar o .cpanel.yml.
#
# Uso:  bash deploy_empresas.sh <origem_geral> <origem_empresa> <raiz_destino>
# Ex.:  bash deploy_empresas.sh /home/tech1694/repositories/prod /home/tech1694/repositories/prod/armazem_paraiba /home/tech1694/public_html/gestaodeponto

GERAL="${1:?origem geral}"; EMPRESA="${2:?origem da empresa}"; DESTINO="${3:?raiz de destino}"
LOG="${DEPLOY_LOG:-$DESTINO/provisionador/logs/deploy.log}"
mkdir -p "$(dirname "$LOG")" 2>/dev/null
echo "=== deploy $(date '+%Y-%m-%d %H:%M:%S') ===" >> "$LOG"

# 1) Arquivos gerais (raiz, contex20, PHPMailer, face_models, provisionador...)
/bin/cp -R "$GERAL"/* "$DESTINO"/ >> "$LOG" 2>&1 && echo "OK geral" >> "$LOG" || echo "ERRO geral" >> "$LOG"

# 2) Cada empresa = pasta com .env
total=0
for d in "$DESTINO"/*/; do
  [ -f "${d}.env" ] || continue
  nome="$(basename "$d")"
  if /bin/cp -R "$EMPRESA"/* "$d" >> "$LOG" 2>&1; then echo "OK $nome" >> "$LOG"; else echo "ERRO $nome" >> "$LOG"; fi
  total=$((total+1))
done
echo "empresas atualizadas: $total" >> "$LOG"
echo "empresas atualizadas: $total"
