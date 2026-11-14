# CRM - TECHPS (módulo WhatsApp)

Módulo de atendimento por WhatsApp integrado ao TechPS, com inbox, contatos,
envio em lote, mídias, mensagens pré-prontas, exportação, configuração de provedor,
webhook e widget flutuante.

> **Documentação funcional:** [docs/GUIA-CONFIGURACAO-E-USO.md](docs/GUIA-CONFIGURACAO-E-USO.md)
> Explica a diferença entre Evolution API e Cloud API oficial, campo por campo
> da tela de configuração, uso diário, permissões, cron e problemas comuns.
>
> **Backlog de evolução:** [docs/MELHORIAS-CRM-TECHPS.md](docs/MELHORIAS-CRM-TECHPS.md)
> Lista as mudanças planejadas: mídias, ligações condicionais, retenção 0,
> exportação, filtro de mensagens e mensagens pré-prontas.
>
> **Auditoria de segurança:** [docs/SEGURANCA.md](docs/SEGURANCA.md)
> Correções, verificações, riscos residuais e checklist de implantação.

## Requisitos

- PHP 8.1+ com `mysqli`, `curl` e `fileinfo`.
- `sodium` (recomendado) ou `openssl` para criptografar credenciais.
- MySQL/MariaDB com `utf8mb4`.
- Cron do cPanel (ou equivalente) para processar a fila.

## Ativação no tenant

O `.env` do tenant **não deve ficar dentro do document root**. O carregador procura primeiro `TECHPS_ENV_FILE` e depois um `techps.env` fora da pasta pública (ex.: `/var/www/techps.env` ou `/home/USUARIO/techps.env`). Em instalações antigas, `TECHPS_ALLOW_PUBLIC_ENV=1` permite o `.env` público temporariamente — não use em produção.

```env
WHATSAPP_ENABLED=1
WHATSAPP_TEST_NUMBER=5511999999999
WHATSAPP_MEDIA_PATH=/var/lib/techps/whatsapp-media
```

`WHATSAPP_TEST_NUMBER` é opcional e usado apenas em testes manuais. `WHATSAPP_MEDIA_PATH` deve apontar para uma pasta persistente, fora do document root e com escrita para o PHP.

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

Configure `WHATSAPP_MEDIA_PATH` no `.env` com uma pasta **fora do document root**
(ex.: `/var/lib/techps/whatsapp-media`) e garanta permissão de escrita para o
usuário do PHP. Se não for configurada, o módulo tenta uma pasta irmã do
document root e, como fallback seguro, usa o diretório temporário do sistema.
Os anexos nunca devem ficar dentro de `whatsapp/arquivos/` em produção.

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
real (`finfo`), gravados em `WHATSAPP_MEDIA_PATH` (fora do document root) e
baixados somente por `arquivo.php` com sessão e permissão.

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

## Mensagens prontas

O cadastro fica em **CRM - TECHPS → Mensagens Prontas**. Cada registro tem:

- **Título / tag**: quando usar (agrupa as variações; o formulário permite escolher uma tag existente).
- **Subtítulo**: a variação que aparece na lista.
- **Mensagem**: o texto, com as variáveis `{nome}`, `{primeiro_nome}`, `{setor}`, `{cargo}`, `{empresa}` e `{telefone}` (ou `{{nome}}` com chaves duplas). No cadastro, botões de variáveis inserem cada uma na posição do cursor.
- **Status** (ativo/inativo).

No chat, digite `/` no campo de mensagem para abrir os títulos; escolha o título e depois o subtítulo. O texto é inserido com as variáveis do contato já substituídas.
No envio em lote, use os campos **Mensagem pré-pronta → Título/Subtítulo** para preencher o texto. Na etapa de mensagem, os botões de variáveis também inserem `{nome}`, `{primeiro_nome}`, `{setor}`, `{cargo}`, `{empresa}` e `{telefone}` na posição do cursor.

A lista de conversas tem o filtro **Todas** / **Da empresa**: “Da empresa” mostra somente contatos vinculados ao cadastro de funcionário/usuário; “Todas” também inclui números externos que chegam no aparelho. O filtro é aplicado na lista e preservado ao abrir uma conversa.

## Horários das mensagens

O banco grava os horários em UTC e a interface converte para o fuso do servidor (`America/Fortaleza`). A conversão é aplicada no carregamento inicial, no polling, na lista de conversas e na exportação CSV, independentemente do fuso do navegador.

## Chamadas de voz

O módulo está preparado para chamadas oficiais da **Cloud API**:

- **Configurações → Chamadas:** ativa/desativa o recurso e permite habilitar Calling no número via Graph API.
- **Botão de telefone no chat:** inicia uma chamada de saída; fica desabilitado se o provedor não for Cloud API ou se Calling não estiver ativo.
- **Permissão do contato:** antes de ligar, o módulo consulta a permissão; se necessário, envia uma solicitação de permissão pelo WhatsApp.
- **Chamada recebida:** o chat consulta chamadas pendentes a cada 3 segundos e mostra o modal para aceitar ou rejeitar.
- **WebRTC:** o navegador negocia o áudio via SDP; o backend usa os endpoints oficiais e processa os webhooks `calls`.

Requisitos: número na Cloud API, Calling habilitado na Meta, limite de mensagens de pelo menos 2.000 destinatários, linha de crédito, webhook `calls` e permissão do usuário. A Evolution API não faz chamadas oficiais. A chamada real ainda precisa de homologação com uma conta/número elegível.

### Evolution API e WhatsApp Web

O WhatsApp Web oficial faz chamadas, mas a Evolution API não usa o cliente Web oficial: ela usa Baileys, uma implementação independente do protocolo multi-dispositivo. O endpoint `call/offer` da Evolution atual é um stub e retorna um ID fixo; o Baileys não implementa oferta/atendimento de áudio, apenas recebe eventos de chamada e permite rejeitar.

Para chamadas reais via Evolution é necessário um serviço de webphone terceirizado, como o Wavoip (token/dispositivo próprio), ou usar a Cloud API oficial com número elegível.

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
- `teste_seguranca.php`: criptografia/descriptografia, JSON seguro para `<script>`, validação de URL e bloqueio de SSRF.
- `teste_providers.php`: parse de webhook e validação de assinatura/apikey dos dois provedores.
- `teste_midia.php`: MIME, limites, sanitização, upload e gravação de mídia.

## Segurança

> **Auditoria completa:** [docs/SEGURANCA.md](docs/SEGURANCA.md)  
> Achados, correções, testes e riscos residuais de 14/11/2026.

### Controles aplicados

- Tokens e segredos são criptografados no banco (`sodium` ou AES-256-GCM) e
  nunca são exibidos no HTML.
- CSRF em todos os POSTs autenticados. Ações que alteram estado também exigem
  POST; GET em ação mutável retorna **405**.
- Prepared statements em todas as consultas.
- Webhook com validação de assinatura/token, idempotência em `wa_evento` e
  limite de **8 MB** por payload. Assinatura inválida não grava evento no banco.
- Mídias ficam em `WHATSAPP_MEDIA_PATH`, fora do document root; o download passa
  por `arquivo.php` com permissão, validação de MIME e `Content-Disposition`.
- Downloads externos têm limite de tamanho, bloqueio de IP privado/reservado,
  restrição a HTTP/HTTPS e redirecionamentos desativados.
- Headers: `X-Content-Type-Options`, `X-Frame-Options`, `X-Permitted-Cross-Domain-Policies`,
  `Referrer-Policy`, `Permissions-Policy`, CSP básica e HSTS em HTTPS.
- Sessão com cookie `HttpOnly`, `SameSite=Lax`, `Secure` em HTTPS e
  `session.use_strict_mode`.
- Rate limit por sessão para envio, contatos, lotes, configuração, QR Code,
  sincronizações e testes.
- JSON embutido em `<script>` usa escape hexadecimal (`JSON_HEX_*`).
- CSV de conversa e de lote neutralizam fórmulas (`=`, `+`, `-`, `@`).
- Exceção de envio em lote sem opt-in exige justificativa (mínimo 10 caracteres)
  e registra `lote_optout_liberado` no log.

### Verificações da auditoria

- `php -l` em todos os PHP: OK.
- `node --check` em todos os JS: OK.
- `teste_providers.php`: 17 testes OK.
- `teste_midia.php`: 17 testes OK.
- `teste_seguranca.php`: criptografia, JSON seguro, validação HTTPS e bloqueio
  de SSRF OK.
- GET em ações mutáveis: **405**; API sem sessão: **401 JSON**.
- `.env`, `bootstrap.php` e mídia antiga via URL: **404**.
- Testes acessados por HTTP: **403**.
- Anexo autenticado: **200** com MIME correto.

### Requisitos de implantação

- Não usar `php -S` em produção; ele ignora `.htaccess` e pode expor `.git`,
  `docs` e arquivos estáticos.
- Manter `.env` fora do document root (`TECHPS_ENV_FILE`) e
  `WHATSAPP_MEDIA_PATH` em volume persistente fora da pasta pública.
- Usar HTTPS e confirmar cookies `Secure`/`SameSite` no servidor real.
- O controle de acesso é por tela do módulo, não por conversa individual.
- O opt-in ainda precisa de uma tela de registro/revogação; o bloqueio de lote e
  a justificativa administrativa já estão ativos.

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
  mensagens_prontas.php    # cadastro de mensagens pré-prontas
  configuracoes.php        # provedor, webhook, templates e logs
  exportar.php             # exportação CSV de conversa por período
  api.php                  # AJAX autenticado
  webhook.php              # callbacks do provedor
  cron.php                 # fila, sincronização e retenção
  arquivo.php              # download autenticado de mídia
  widget.php               # botão flutuante
  assets/                  # CSS e JS (inclui chamadas.js para WebRTC)
  lib/chamadas.php         # estado e eventos das chamadas
  lib/                     # segurança, telefone, contatos, fila, mensagens, mídia, chamadas e providers
  arquivos/                # .htaccess legado; mídia real fica em WHATSAPP_MEDIA_PATH
  testes/teste_midia.php   # validação de MIME, limites e armazenamento
```
