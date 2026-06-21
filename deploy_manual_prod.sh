#!/bin/bash
# Script manual de deploy para producao
# Equivalente ao .cpanel.yml (inclui ARMAPLAST)
# Execute no terminal do cPanel com: bash deploy_manual_prod.sh

DEPLOY_LOG=/home/tech1694/deploy_manual.log

echo "" >> $DEPLOY_LOG
echo "========================================" >> $DEPLOY_LOG
echo "DEPLOY MANUAL INICIADO em $(date '+%Y-%m-%d %H:%M:%S')" >> $DEPLOY_LOG
echo "Usuario: $(whoami)" >> $DEPLOY_LOG
echo "========================================" >> $DEPLOY_LOG

# Origem
GENERAL=/home/tech1694/repositories/prod/*
DOMAIN=/home/tech1694/repositories/prod/armazem_paraiba/*

# Destinos
GENERALDEPLOY=/home/tech1694/public_html/gestaodeponto/
BLUEROAD=/home/tech1694/public_html/gestaodeponto/blueroad
BRASO=/home/tech1694/public_html/gestaodeponto/braso
CARAU=/home/tech1694/public_html/gestaodeponto/carau_transporte
COMAV=/home/tech1694/public_html/gestaodeponto/comav
FEIJAO=/home/tech1694/public_html/gestaodeponto/feijao_turqueza
FS_LOG=/home/tech1694/public_html/gestaodeponto/fs_log_transportes
HN_TRANSP=/home/tech1694/public_html/gestaodeponto/hn_transportes
IFRN=/home/tech1694/public_html/gestaodeponto/ifrn
JRJ_ORG=/home/tech1694/public_html/gestaodeponto/jrj_organizacao
LOGSYNC=/home/tech1694/public_html/gestaodeponto/logsync_techps
LEMON=/home/tech1694/public_html/gestaodeponto/lemon
NH_TRANSP=/home/tech1694/public_html/gestaodeponto/nh_transportes
OPAFRUTAS=/home/tech1694/public_html/gestaodeponto/opafrutas
PKF=/home/tech1694/public_html/gestaodeponto/pkf_medeiros
QUALY=/home/tech1694/public_html/gestaodeponto/qualy_transportes
TECHPS=/home/tech1694/public_html/gestaodeponto/techps
TECHPSDEMO=/home/tech1694/public_html/gestaodeponto/techps_demo
TRAMPGAS=/home/tech1694/public_html/gestaodeponto/trampolim_gas
TRANSCOPEL=/home/tech1694/public_html/gestaodeponto/transcopel
SAO_LUCAS=/home/tech1694/public_html/gestaodeponto/sao_lucas
PB_TRANSPORTES=/home/tech1694/public_html/gestaodeponto/pb_transportes
ODONTOTANGARA=/home/tech1694/public_html/gestaodeponto/odontotangara
CLINICA_GERLANE=/home/tech1694/public_html/gestaodeponto/clinica_gerlane
IRANEIDE_OLIVEIRA=/home/tech1694/public_html/gestaodeponto/iraneide_oliveira
MIDIA_DIGITAL=/home/tech1694/public_html/gestaodeponto/midia_digital
ENOVE=/home/tech1694/public_html/gestaodeponto/enove
T_MILITAO=/home/tech1694/public_html/gestaodeponto/t_militao
LAUTO=/home/tech1694/public_html/gestaodeponto/lauto
GST=/home/tech1694/public_html/gestaodeponto/gst
ARMAPLAST=/home/tech1694/public_html/gestaodeponto/armaplast

# Funcao para copiar com log
copiar() {
    origem=$1
    destino=$2
    echo "[$(date '+%H:%M:%S')] Copiando $origem -> $destino ..." >> $DEPLOY_LOG
    /bin/cp -R $origem $destino >> $DEPLOY_LOG 2>&1
    if [ $? -eq 0 ]; then
        echo "[$(date '+%H:%M:%S')] OK: $origem -> $destino" >> $DEPLOY_LOG
    else
        echo "[$(date '+%H:%M:%S')] ERRO: $origem -> $destino" >> $DEPLOY_LOG
    fi
}

# Copia arquivos gerais
copiar "$GENERAL" "$GENERALDEPLOY"

# Copia arquivos especificos para cada cliente
copiar "$DOMAIN" "$BLUEROAD"
copiar "$DOMAIN" "$BRASO"
copiar "$DOMAIN" "$CARAU"
copiar "$DOMAIN" "$COMAV"
copiar "$DOMAIN" "$FEIJAO"
copiar "$DOMAIN" "$FS_LOG"
copiar "$DOMAIN" "$HN_TRANSP"
copiar "$DOMAIN" "$IFRN"
copiar "$DOMAIN" "$JRJ_ORG"
copiar "$DOMAIN" "$LEMON"
copiar "$DOMAIN" "$LOGSYNC"
copiar "$DOMAIN" "$NH_TRANSP"
copiar "$DOMAIN" "$OPAFRUTAS"
copiar "$DOMAIN" "$PKF"
copiar "$DOMAIN" "$QUALY"
copiar "$DOMAIN" "$TECHPS"
copiar "$DOMAIN" "$TECHPSDEMO"
copiar "$DOMAIN" "$TRAMPGAS"
copiar "$DOMAIN" "$TRANSCOPEL"
copiar "$DOMAIN" "$SAO_LUCAS"
copiar "$DOMAIN" "$PB_TRANSPORTES"
copiar "$DOMAIN" "$ODONTOTANGARA"
copiar "$DOMAIN" "$CLINICA_GERLANE"
copiar "$DOMAIN" "$IRANEIDE_OLIVEIRA"
copiar "$DOMAIN" "$MIDIA_DIGITAL"
copiar "$DOMAIN" "$ENOVE"
copiar "$DOMAIN" "$T_MILITAO"
copiar "$DOMAIN" "$LAUTO"
copiar "$DOMAIN" "$GST"
copiar "$DOMAIN" "$ARMAPLAST"

echo "[$(date '+%H:%M:%S')] DEPLOY MANUAL FINALIZADO" >> $DEPLOY_LOG
echo "========================================" >> $DEPLOY_LOG

echo "Deploy manual concluido. Verifique o log em: $DEPLOY_LOG"
