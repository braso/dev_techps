#!/usr/bin/env bash
# ============================================================
# Configura a CONTABILIDADE_API_KEY no .env de cada cliente.
#
# Rodar NO SERVIDOR (SSH do cPanel tech1694), uma vez. O que ele faz:
#
#   1. percorre as pastas de cliente em public_html/gestaodeponto/;
#   2. em cada uma que ainda não tem CONTABILIDADE_API_KEY no .env,
#      gera uma chave própria e acrescenta a linha;
#   3. imprime a tabela cliente → URL → chave para você cadastrar no app
#      de extrato.
#
# Uma chave POR CLIENTE, não uma chave só para todos: se uma vazar, o
# estrago fica num cliente, e dá para trocar aquela sem mexer nas outras.
#
# É idempotente: rodar de novo não troca chave de quem já tem. Para trocar
# uma, apague a linha do .env daquele cliente e rode outra vez.
#
#   bash CONFIGURAR_CHAVES.sh              → mostra o que faria
#   bash CONFIGURAR_CHAVES.sh --aplicar    → grava
# ============================================================
set -uo pipefail

RAIZ="${RAIZ:-$HOME/public_html/gestaodeponto}"
APLICAR="nao"
[ "${1:-}" = "--aplicar" ] && APLICAR="sim"

if [ ! -d "$RAIZ" ]; then
  echo "Não encontrei $RAIZ — ajuste a variável RAIZ no começo do script."
  exit 1
fi

# URL pública de cada cliente. Ajuste se o domínio mudar.
BASE_URL="${BASE_URL:-https://techpsgj.com.br/gestaodeponto}"

printf "%s\n" "---------------------------------------------------------------"
printf "  %s\n" "$([ "$APLICAR" = "sim" ] && echo 'APLICANDO' || echo 'SIMULAÇÃO — nada será gravado')"
printf "  raiz: %s\n" "$RAIZ"
printf "%s\n\n" "---------------------------------------------------------------"

novos=0
existentes=0
semenv=0
semapi=0

# Guarda o resultado para imprimir a tabela no fim.
TABELA=$(mktemp)

for pasta in "$RAIZ"/*/; do
  cliente=$(basename "$pasta")
  env="$pasta.env"

  # Pasta de cliente é a que tem .env. O resto (assets, backups) é pulado.
  if [ ! -f "$env" ]; then
    semenv=$((semenv + 1))
    continue
  fi

  # Sem a api.php, não há o que consultar: o deploy ainda não chegou aqui.
  if [ ! -f "$pasta/contabilidade/api.php" ]; then
    printf "  %-28s sem contabilidade/api.php (falta o deploy)\n" "$cliente"
    semapi=$((semapi + 1))
    continue
  fi

  if grep -q "^CONTABILIDADE_API_KEY" "$env" 2>/dev/null; then
    chave=$(grep "^CONTABILIDADE_API_KEY" "$env" | head -1 | sed 's/^[^=]*=//' | tr -d " \"'")
    printf "  %-28s já tem chave\n" "$cliente"
    existentes=$((existentes + 1))
    printf "%s\t%s/%s/contabilidade/api.php\t%s\n" "$cliente" "$BASE_URL" "$cliente" "$chave" >> "$TABELA"
    continue
  fi

  # 32 bytes aleatórios do próprio sistema, em hexadecimal.
  chave="ctb_$(openssl rand -hex 24 2>/dev/null || head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')"

  if [ "$APLICAR" = "sim" ]; then
    # Garante quebra de linha antes de acrescentar, senão a linha nova cola
    # na última e as duas se perdem.
    [ -n "$(tail -c 1 "$env")" ] && printf "\n" >> "$env"
    printf "CONTABILIDADE_API_KEY = '%s'\n" "$chave" >> "$env"
    chmod 600 "$env" 2>/dev/null || true
    printf "  %-28s chave criada\n" "$cliente"
  else
    printf "  %-28s criaria chave\n" "$cliente"
  fi

  novos=$((novos + 1))
  printf "%s\t%s/%s/contabilidade/api.php\t%s\n" "$cliente" "$BASE_URL" "$cliente" "$chave" >> "$TABELA"
done

printf "\n  %s cliente(s) com chave nova · %s já tinham · %s sem api.php · %s pasta(s) sem .env\n" \
  "$novos" "$existentes" "$semapi" "$semenv"

if [ "$APLICAR" = "sim" ]; then
  SAIDA="$HOME/DOMINIOS_EXTRATO.json"

  # JSON para o app de extrato cadastrar tudo de uma vez.
  {
    printf "[\n"
    primeiro=1
    while IFS=$'\t' read -r cliente url chave; do
      [ -z "$cliente" ] && continue
      [ $primeiro -eq 0 ] && printf ",\n"
      printf '  { "nome": "%s", "url_base": "%s", "api_key": "%s" }' "$cliente" "$url" "$chave"
      primeiro=0
    done < "$TABELA"
    printf "\n]\n"
  } > "$SAIDA"

  chmod 600 "$SAIDA"
  printf "\n  Arquivo para cadastrar no app:  %s\n" "$SAIDA"
  printf "  Baixe com:  scp -P <porta> usuario@servidor:%s .\n" "$SAIDA"
  printf "  E cadastre:  node bin/registrar-dominios.mjs DOMINIOS_EXTRATO.json\n"
  printf "\n  Esse arquivo contém as chaves. Apague do servidor depois de baixar:\n"
  printf "    rm %s\n" "$SAIDA"
else
  printf "\n  Para gravar de verdade:  bash CONFIGURAR_CHAVES.sh --aplicar\n"
fi

rm -f "$TABELA"
