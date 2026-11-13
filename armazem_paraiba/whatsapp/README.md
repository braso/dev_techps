# CRM - TECHPS (módulo WhatsApp)

Módulo de atendimento por WhatsApp integrado ao TechPS, com inbox, contatos,
envio em lote, configuração de provedor, webhook e widget flutuante.

> **Documentação funcional:** [docs/GUIA-CONFIGURACAO-E-USO.md](docs/GUIA-CONFIGURACAO-E-USO.md)
> Explica a diferença entre Evolution API e Cloud API oficial, campo por campo
> da tela de configuração, uso diário, permissões, cron e problemas comuns.
>
> **Backlog de evolução:** [docs/MELHORIAS-CRM-TECHPS.md](docs/MELHORIAS-CRM-TECHPS.md)
> Lista as mudanças planejadas: mídias, ligações condicionais, retenção 0,
> exportação, filtro de mensagens e mensagens pré-prontas.

## Requisitos

- PHP 8.1+ com `mysqli`, `curl` e `fileinfo`.
- `sodium` (recomendado) ou `openssl` para criptografar credenciais.
- MySQL/MariaDB com `utf8mb4`.
- Cron do cPanel (ou equivalente) para processar a fila.

## Ativação no tenant

No `.env` do tenant:

```env
WHATSAPP_ENABLED=1
WHATSAPP_TEST_NUMBER=5511999999999
```

`WHATSAPP_TEST_NUMBER` é opcional e usado apenas em testes manuais.

## Migração

Via navegador: a primeira abertura de qualquer tela do módulo executa a
migração automaticamente.

Via CLI (recomendado no deploy):

```bash
php /caminho/do/tenant/whatsapp/migrar.php
```

A migração cria as tabelas `wa_*`, registra `schema_version` em `wa_meta` e
insere os itens de menu da seção **CRM - TECHPS** (Chat, Contatos, Envio em Lote e
Configurações), liberando-os para os perfis administrativos.

Garanta que `whatsapp/arquivos/` tenha permissão de escrita para o usuário do
PHP (os anexos enviados/recebidos ficam nessa pasta, protegida por `.htaccess`).

## Permissões

1. Abra **Cadastro de Perfil de Acesso**.
2. Libere os itens da seção **CRM - TECHPS** para os perfis desejados.
3. O widget flutuante só aparece para quem tem permissão de `/whatsapp/index.php`.

A flag `WHATSAPP_ENABLED` controla o módulo inteiro: com valor diferente de `1`,
o menu e o widget não aparecem.

## Cron

Cadastre no cPanel, por tenant:

```
* * * * * /usr/local/bin/php /home/USUARIO/public_html/gestaodeponto/TENANT/whatsapp/cron.php >> /home/USUARIO/logs/whatsapp-TENANT.log 2>&1
```

O cron:

- processa até 20 mensagens pendentes por execução;
- aplica até 3 novas tentativas com backoff (1, 5 e 15 minutos);
- sincroniza contatos uma vez por dia;
- aplica a retenção de mensagens/anexos uma vez por dia quando o valor é maior que 0.

## Mídias

O chat aceita envio de imagem, áudio, vídeo e documento pelo botão de clipe.
Também há botão de câmera com prévia ao vivo (webcam no desktop ou câmera do
celular) e botão de microfone para gravar áudio no navegador (`MediaRecorder`),
com timer e cancelamento. Se a câmera ao vivo não estiver disponível, o módulo
abre o seletor nativo/câmera do dispositivo. Os arquivos são validados por MIME
real (`finfo`), gravados em
`whatsapp/arquivos/` e baixados somente por `arquivo.php` com sessão e permissão.

- Imagem: JPG, PNG e WEBP (limite de 5 MB).
- Áudio: OGG, MP3, M4A, AAC, AMR e WAV (limite de 16 MB). A gravação prioriza MP4/M4A; OGG/Opus é usado quando o navegador oferece.
- Vídeo: MP4 e 3GP (limite de 16 MB).
- Documento: PDF, DOC/DOCX, XLS/XLSX, PPT/PPTX, TXT, CSV e ZIP (limite padrão de 16 MB, ajustável por `WHATSAPP_MEDIA_MAX_MB`).

As mídias recebidas pela Cloud API são baixadas pelo `media_id`; as recebidas
pela Evolution usam base64, o endpoint `getBase64FromMediaMessage` ou a URL do
webhook. O arquivo é salvo antes que a URL temporária do provedor expire.

Em produção, mantenha `upload_max_filesize` e `post_max_size` do PHP iguais ou
maiores que o limite configurado em `WHATSAPP_MEDIA_MAX_MB`.

O chat controla mensagens já renderizadas e não permite duas buscas simultâneas,
evitando que o mesmo anexo apareça duas vezes quando o envio e o polling coincidem.

## Exportação

No cabeçalho da conversa, o botão de download abre a exportação em CSV.
É possível escolher **últimos N dias** ou um **intervalo de datas**.
O arquivo inclui data, direção, tipo, mensagem, status, usuário, contato,
telefone, anexo, MIME e ID do provedor. A exportação exige permissão de chat,
usa CSRF, registra auditoria e ignora fórmulas de planilha.

## Webhook

A URL é exibida em **CRM - TECHPS → Configurações → Webhook**:

```
https://SEU_DOMINIO/APP_PATH/TENANT/whatsapp/webhook.php
```

### Cloud API (Meta)

- Cadastre a URL como **Callback URL**.
- Informe o mesmo **Verify Token** salvo na configuração.
- O POST é validado por `X-Hub-Signature-256` com o **App Secret**.

### Evolution API

- Cadastre a URL no webhook da instância.
- Envie o header `apikey` (ou use `?token=`) com a mesma API Key configurada.
- Os eventos tratados são `messages.upsert` e `messages.update`.
- Os endpoints da Evolution variam por versão; valide a versão usada no seu
  servidor antes de produção.

## Provedores

### Evolution API

Campos: `base_url`, `instance`, `api_key`.
A tela de configurações permite testar a conexão, consultar o estado e gerar
QR Code.

### Cloud API

Campos: `phone_number_id`, `waba_id`, `access_token`, `app_secret`,
`verify_token` e a base da Graph API (padrão `https://graph.facebook.com/v21.0`).

Observações:

- Texto livre só é permitido dentro da janela de 24 horas.
- Fora da janela, use um template aprovado.
- Templates podem ser sincronizados em **Configurações → Templates**.

## Testes locais

```bash
php whatsapp/testes/teste_telefone.php
php whatsapp/testes/teste_seguranca.php
php whatsapp/testes/teste_providers.php
php whatsapp/testes/teste_midia.php
```

- `teste_telefone.php`: normalização E.164.
- `teste_seguranca.php`: criptografia/descriptografia de credenciais.
- `teste_providers.php`: parse de webhook e validação de assinatura/apikey dos dois provedores.
- `teste_midia.php`: MIME, limites, sanitização, upload e gravação de mídia.

## Segurança

- Tokens e segredos são criptografados no banco (`sodium` ou AES-256-GCM) e
  nunca são exibidos no HTML.
- CSRF em todos os POSTs autenticados.
- Prepared statements em todas as consultas.
- Webhook com validação de assinatura/token e idempotência em `wa_evento`.
- Mídias ficam em `whatsapp/arquivos/` com `.htaccess` bloqueando acesso direto;
  o download passa por `arquivo.php` com verificação de permissão.

## LGPD

- `wa_contato.cont_tx_optin` e `cont_dt_optin` controlam o consentimento.
- O envio em lote só inclui contatos com opt-in, salvo liberação explícita de
  administrador.
- A retenção é configurável em dias; `0` significa **nunca apagar** e é o padrão de novas instalações. Valores maiores são aplicados pelo cron.
- Exclusões e exportações devem ser tratadas pelo administrador do tenant.

## Estrutura

```
whatsapp/
  bootstrap.php            # telas autenticadas
  bootstrap_public.php     # webhook/cron
  migracoes.php            # tabelas e menu
  migrar.php               # migração CLI
  index.php                # inbox/chat
  contatos.php             # contatos e sincronização
  lote.php                 # envio em lote
  configuracoes.php        # provedor, webhook, templates e logs
  exportar.php             # exportação CSV de conversa por período
  api.php                  # AJAX autenticado
  webhook.php              # callbacks do provedor
  cron.php                 # fila, sincronização e retenção
  arquivo.php              # download autenticado de mídia
  widget.php               # botão flutuante
  assets/                  # CSS e JS
  lib/                     # segurança, telefone, contatos, fila, mensagens, mídia e providers
  arquivos/                # mídia protegida
  testes/teste_midia.php   # validação de MIME, limites e armazenamento
```
