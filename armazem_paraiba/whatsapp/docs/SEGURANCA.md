# Segurança — CRM - TECHPS (módulo WhatsApp)

**Data da auditoria:** 14/11/2026  
**Escopo:** `armazem_paraiba/whatsapp/` (PHP, JavaScript, webhook, API, mídia e configuração).

## 1. Resumo executivo

Foram corrigidos pontos de CSRF, exposição de arquivos, SSRF em download de mídia, XSS em dados embutidos no HTML, ausência de limites no webhook, falta de headers de segurança e exposição do `.env` no servidor local.

O módulo continua dependendo de uma implantação correta: HTTPS, permissões de sessão, `.env` fora do document root, mídia fora do document root e bloqueio de `.git`/arquivos internos pelo servidor web.

## 2. Correções aplicadas

### 2.1 CSRF e métodos HTTP

- Criada a lista central `wa_api_exigir_post()` em `api.php`.
- Ações que alteram estado agora retornam **405** quando chamadas por GET:
  - envio de mensagens/mídia;
  - criação de contato e lote;
  - sincronização de contatos/templates;
  - processamento de fila;
  - configuração, QR Code, Calling e testes;
  - ações de chamadas.
- `qrcode`, `testar_provider` e `sincronizar_templates` foram alterados no frontend para **POST + CSRF**.
- `sincronizar_contatos` e `processar_fila` já usavam POST e agora também são bloqueados por método no backend.
- Formulários administrativos já validavam CSRF; a validação foi mantida.

### 2.2 Armazenamento de mídia

- A mídia saiu de `whatsapp/arquivos/` (dentro do document root) para `WHATSAPP_MEDIA_PATH`, fora da pasta pública.
- Fallback seguro para diretório temporário quando a pasta configurada não existe/é gravável.
- O código rejeita uma pasta de mídia que esteja dentro do `DOCUMENT_ROOT`.
- Diretório criado com permissão `0750`; arquivos com `0640`.
- `.htaccess` da pasta antiga foi reforçado com `Require all denied`/`Deny from all` e `Options -Indexes`, mas **não é a proteção principal**: servidores embutidos ignoram `.htaccess`.
- `arquivo.php`, fila, retenção, index e testes passaram a usar `wa_midia_pasta()`.

### 2.3 `.env` fora do document root

- `load_env.php` agora procura, nesta ordem:
  1. `TECHPS_ENV_FILE`;
  2. `techps.env` fora do document root;
  3. `.env` legado apenas com `TECHPS_ALLOW_PUBLIC_ENV=1`.
- Arquivos de ambiente dentro do document root são ignorados e registrados no log.
- O `.env` local foi movido para `/var/www/techps.env` e removido da pasta pública.
- `TECHPS_ALLOW_PUBLIC_ENV` existe apenas para migração temporária; **não usar em produção**.

### 2.4 Upload e entrega de arquivos

- `wa_midia_salvar_upload()` exige `is_uploaded_file()` em requisições web (CLI continua permitindo testes controlados).
- Validação por MIME real (`finfo`), whitelist de tipos, limite por categoria e nome aleatório já existiam e foram mantidos.
- `arquivo.php` agora:
  - valida o MIME contra a whitelist antes de definir `Content-Type`;
  - força `application/octet-stream` para MIME desconhecido;
  - usa `Content-Disposition: attachment` fora de imagem/áudio/vídeo;
  - envia `X-Content-Type-Options: nosniff`, CSP `default-src 'none'` e `Cache-Control: no-store`.

### 2.5 SSRF e download de mídia

- `WaHttpClient::raw()` valida URLs de mídia:
  - somente `http`/`https`;
  - bloqueia credenciais na URL;
  - bloqueia portas fora de 80/443, exceto no host da Evolution configurado;
  - resolve o host e bloqueia IPs privados/reservados/loopback;
  - permite host privado apenas quando é o host da base URL da Evolution;
  - limita o tamanho do download;
  - desativa redirecionamentos;
  - restringe protocolos para HTTP/HTTPS.
- O provider Cloud e o provider Evolution passaram a informar limite de bytes; a Evolution informa também o host permitido da própria base.
- JSON, multipart e downloads usam `CURLOPT_SSL_VERIFYPEER`/`VERIFYHOST` e protocolos restritos.
- Base URL de Cloud API é validada como HTTPS; Evolution aceita HTTP/HTTPS para ambiente local.

### 2.6 Webhook

- Assinatura Cloud (`X-Hub-Signature-256`) e token/apikey da Evolution continuam obrigatórios, com `hash_equals`.
- Payload acima de **8 MB** retorna `413`.
- Idempotência por hash do payload em `wa_evento` foi mantida.
- Assinatura/token inválido **não grava** em `wa_evento`; usa apenas `error_log`, evitando log flooding.
- `wa_evento` não é executado como HTML; erros são escapados na tela de configuração.

### 2.7 XSS

- Criado `wa_json_script()` com `JSON_HEX_TAG`, `JSON_HEX_AMP`, `JSON_HEX_APOS` e `JSON_HEX_QUOT`.
- Dados de contato, mensagens prontas, IDs e configuração embutidos em `<script>` passaram a usar essa função.
- Resultado de conexão, templates e Calling na tela de configuração usa `textContent`, não `innerHTML`.
- QR Code passou a ser montado via DOM (`createElement`) e `src` validado como `data:image/png|jpeg|webp`.
- Saídas PHP continuam usando `wa_e()`; os pontos auditados não tinham HTML sem escape.

### 2.8 Headers de segurança

`wa_security_headers()` aplica em respostas dinâmicas:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(self), microphone=(self), geolocation=()`
- `Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; form-action 'self'`
- `Strict-Transport-Security` quando a requisição é HTTPS
- `Cache-Control: no-store, private` nas telas autenticadas
- `Cache-Control: no-store, private` nas respostas JSON

Além disso, `conecta.php` ativa cookies de sessão `HttpOnly`, `SameSite=Lax` e
`Secure` em HTTPS, além de `session.use_strict_mode` e `use_only_cookies`.

### 2.9 Rate limit

Foram adicionados limites por sessão para:

- criação de contato: 10/min;
- criação de lote: 10/min;
- sincronização de contatos: 2/5 min;
- processamento manual da fila: 5/min;
- teste de conexão: 10/min;
- envio de teste: 3/min;
- QR Code: 6/min;
- sincronização de templates: 5/min;
- alteração de Calling: 5/min;
- salvamento de configuração: 10/min.

Envio de texto e mídia já tinham limites próprios.

### 2.10 CSV e opt-in

- O CSV do lote agora neutraliza células iniciadas por `=`, `+`, `-` ou `@`, como já ocorria na exportação de conversa.
- O envio em lote sem opt-in continua restrito a administrador.
- A liberação administrativa agora exige justificativa de pelo menos 10 caracteres e registra `lote_optout_liberado` no log, com lote, quantidade sem opt-in, justificativa e usuário.

### 2.11 Arquivos internos

- Arquivos de teste (`testes/teste_*.php`) retornam **403** quando acessados por HTTP.
- `cron.php` e `migrar.php` continuam exclusivos de CLI.
- A pasta de testes não deve ser publicada em produção.

## 3. Verificações executadas

- `php -l` em todos os arquivos PHP: sem erros.
- `node --check` em `whatsapp.js`, `lote.js`, `chamadas.js` e `widget.js`: sem erros.
- Testes de providers: 17 OK.
- Testes de mídia: 17 OK.
- Teste de segurança: criptografia, JSON para `<script>`, validação HTTPS e bloqueio de SSRF: OK.
- GET em ações mutáveis (`sincronizar_contatos`, `sincronizar_templates`, `qrcode`): HTTP **405**.
- Headers verificados em `index.php` e `arquivo.php`: `nosniff`, CSP, `X-Frame-Options`, `Referrer-Policy`, `no-store`.
- `arquivo.php?anexo=...`: 200 com `Content-Type: image/jpeg`.
- Arquivo antigo acessado diretamente: **404** após mover a mídia.
- `teste_midia.php` acessado por HTTP: **403**.
- `.env` acessado por URL: **404** após mover para fora do document root.
- `wa_json_script('</script>...')`: não contém `</script>` bruto.
- `WaHttpClient::raw('http://127.0.0.1:9/')`: bloqueado por SSRF.

## 4. Riscos residuais e requisitos de implantação

1. **Servidor local `php -S`:** ele ignora `.htaccess` e continua podendo expor `.git`, `docs` e outros arquivos estáticos. Não usar em produção. Em Apache/Nginx, bloquear `.git`, `.env`, `docs`, `testes` e arquivos internos. O servidor embutido também expõe `X-Powered-By`; em produção use um servidor real com `expose_php=Off`.
2. **HTTPS:** obrigatório em produção. Sem HTTPS, cookies de sessão e tokens ficam vulneráveis.
3. **Cookies de sessão:** o endurecimento foi aplicado em `conecta.php`; confirme no servidor que o cookie sai com `HttpOnly`, `SameSite` e `Secure` em HTTPS (o `Secure` depende da detecção de HTTPS/proxy).
4. **`TECHPS_ENV_FILE` e `WHATSAPP_MEDIA_PATH`:** manter fora do document root, com permissões restritas. O fallback em `/tmp` é seguro contra exposição, mas não é persistente.
5. **Rate limit em sessão:** não substitui WAF/reverse proxy; um atacante pode criar novas sessões. Para alto risco, aplicar limite por IP/Usuário no proxy.
6. **Webhook Evolution:** o token em query string é aceito por compatibilidade. Preferir header `apikey`/`X-CSRF` e não registrar a URL completa em logs de proxy.
7. **Opt-in:** o campo `cont_tx_optin` ainda não tem tela de registro/revogação. O bloqueio de lote existe, mas a conformidade LGPD depende de processo de consentimento e evidência. A exceção administrativa agora fica justificada no log.
8. **Retenção:** `0` significa nunca apagar. Se a operação exigir descarte, configurar dias e validar a rotina de cron.
9. **`.env` legado:** `TECHPS_ALLOW_PUBLIC_ENV=1` é apenas migração; remover após mover o arquivo.
10. **Segredos:** rotacionar tokens da Meta/Evolution e `APP_KEY` se houver suspeita de exposição. Não versionar `.env` nem mídia. Para Evolution remota, prefira HTTPS; HTTP só é aceitável em rede local controlada.
11. **Acesso por conversa:** o módulo aplica permissão por tela (chat, lote, contatos), não por conversa individual. Qualquer usuário com acesso ao chat pode abrir anexos e exportar conversas; se houver necessidade de sigilo entre equipes, implementar escopo por responsável/equipe.

## 5. Arquivos principais alterados

- `lib/seguranca.php` — headers, JSON seguro, validação de URL.
- `lib/provider/HttpClient.php` — protocolos, SSRF, redirect e limite.
- `lib/provider/EvolutionProvider.php` / `CloudApiProvider.php` — download seguro.
- `lib/midia.php` — pasta externa, `is_uploaded_file`, limites.
- `lib/fila.php` — caminho de mídia.
- `lib/config.php` — validação de base URL.
- `arquivo.php` — MIME/Disp/CSP.
- `api.php` — métodos, rate limits, SDP/justificativa.
- `bootstrap.php` / `bootstrap_public.php` — headers e timezone.
- `configuracoes.php` — XSS do QR/resultados, POSTs.
- `index.php`, `lote.php`, `contatos.php`, `widget.php`, `mensagens_prontas.php` — JSON seguro e caminhos.
- `assets/lote.js` — justificativa opt-in.
- `testes/teste_seguranca.php` — novos testes.
- `armazem_paraiba/load_env.php` — `.env` fora do document root.
- `armazem_paraiba/conecta.php` — cookies de sessão `HttpOnly`/`SameSite`/`Secure`, `use_strict_mode`, `use_only_cookies`.
- `.env-example` — `WHATSAPP_MEDIA_PATH` e observação de ambiente.

## 6. Checklist de segurança para deploy

- [ ] HTTPS ativo e HSTS.
- [ ] `.env` fora do document root (`TECHPS_ENV_FILE`).
- [ ] `WHATSAPP_MEDIA_PATH` persistente e fora do document root.
- [ ] `.git`, `docs`, `testes` e arquivos internos bloqueados no servidor web.
- [ ] Cookies de sessão com `HttpOnly`, `Secure` e `SameSite`.
- [ ] `APP_KEY` forte e rotação de tokens.
- [ ] Webhook Cloud com App Secret; Evolution com API Key.
- [ ] Backups de mídia e banco.
- [ ] Política de opt-in/LGPD e retenção definidas.
- [ ] Monitoramento de logs de `lote_optout_liberado`, `webhook_assinatura_invalida` e falhas de mídia.
