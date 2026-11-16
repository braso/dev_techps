# Guia de Configuração e Uso — CRM - TECHPS (módulo WhatsApp)

> **Módulo:** `armazem_paraiba/whatsapp/`
> **Atualizado em:** 12/11/2026
> **Objetivo:** explicar a página de configuração, a diferença entre Evolution API e Cloud API oficial e como usar o módulo no dia a dia.

---

## 1. Visão geral

O CRM - TECHPS permite:

- conversar com funcionários e usuários pelo WhatsApp;
- sincronizar contatos do cadastro de funcionário/usuário;
- abrir a conversa por atalho nas telas de cadastro;
- enviar a mesma mensagem em lote;
- receber respostas e status de entrega/leitura por webhook;
- usar um widget flutuante em qualquer tela do sistema;
- controlar tudo por permissão de perfil e por tenant.

Ele aceita dois provedores:

| Provedor | Como conecta | Indicado para |
|---|---|---|
| **Cloud API (Meta)** | API oficial, com token e WABA | Produção |
| **Evolution API** | Gateway não oficial, com QR Code | Desenvolvimento/homologação |

---

## 2. O que é a Evolution API e para que serve

A **Evolution API** é um gateway de terceiros que conecta o sistema ao WhatsApp usando o protocolo do WhatsApp Web. Em vez de token da Meta, você aponta para um servidor Evolution e conecta uma instância lendo um **QR Code** com o celular.

### Quando usar

- Testes e homologação sem depender de aprovação da Meta.
- Operações pequenas, internas ou temporárias.
- Quando você ainda não tem App/WABA na Meta.

### Vantagens

- Instalação rápida.
- Não exige aprovação de templates.
- A página de configuração consegue gerar o QR Code e consultar o estado da instância.

### Riscos e limitações

- **Não é oficial.** O número pode ser bloqueado ou banido pelo WhatsApp.
- Não tem as garantias, limites e qualidade da API oficial.
- Os endpoints variam conforme a versão da Evolution.
- Não é recomendada para produção com clientes.
- Para produção, use a **Cloud API oficial**.

No módulo, os campos **Base URL**, **Instância** e **API Key**, além do botão **Conectar / QR Code**, são exclusivos da Evolution.

---

## 3. Cloud API oficial

A **Cloud API** é a API oficial da Meta para WhatsApp Business. Ela usa:

- um **App** no Meta for Developers;
- uma **WABA** (WhatsApp Business Account);
- um **número** vinculado;
- **Access Token**;
- **App Secret** para validar webhooks;
- **Verify Token** definido por você;
- URL pública **HTTPS**.

### Vantagens

- Oficial e estável.
- Templates aprovados.
- Status de entrega/leitura confiáveis.
- Regras claras de qualidade e limites.
- Recomendada para produção.

### Limitações importantes

- Texto livre só dentro da **janela de 24 horas** desde a última mensagem do contato.
- Fora da janela, use **template aprovado**.
- Exige opt-in e boas práticas de LGPD.
- O número não pode estar ativo no WhatsApp comum.
- Produção exige verificação do negócio, nome de exibição e meio de pagamento.

---

## 4. Os dois interruptores do módulo

| Interruptor | Onde fica | Para que serve |
|---|---|---|
| `WHATSAPP_ENABLED=1` | `.env` do tenant (`armazem_paraiba/.env`) | Liga o menu, o widget e o webhook. Se estiver desligado, o módulo fica oculto. |
| **Status do módulo = Ativo** | Página de Configurações | Libera os envios. Se estiver inativo, o chat recusa mensagens e a fila aguarda. |

Os dois precisam estar ligados para o uso completo.

---

## 5. Página de Configurações

Caminho no menu: **CRM - TECHPS → Configurações**
Permissão: `/whatsapp/configuracoes.php`

A página tem cinco abas:

1. **Provedor**
2. **Webhook**
3. **Templates**
4. **Logs**
5. **Retenção**

### 5.1 Aba Provedor — campos comuns

| Campo | O que é | Observação |
|---|---|---|
| **Provedor** | Escolhe Cloud API ou Evolution | Os campos dos dois aparecem, mas só o selecionado fica habilitado. |
| **Status do módulo** | Ativo/Inativo | Inativo bloqueia envios. |
| **Número para teste** | Número usado pelo botão “Enviar teste” | Formato: DDD + número. Também aceita `WHATSAPP_TEST_NUMBER` no `.env` como fallback. |
| **URL do webhook** | URL pública que você cadastra na Meta ou na Evolution | Somente leitura, com botão de copiar. |

### 5.2 Campos da Cloud API

| Campo | Onde encontrar | Para que serve |
|---|---|---|
| **Phone Number ID** | Meta → WhatsApp → API Setup | Identifica o número na Cloud API. Não é o telefone. |
| **WABA ID** | Meta → WhatsApp → API Setup / Conta WhatsApp Business | Identifica a conta business; usado para templates. |
| **Verify Token** | Você cria | Precisa ser idêntico ao token cadastrado no webhook da Meta. |
| **Access Token** | Meta → API Setup (temporário) ou System User (permanente) | Autoriza envios e consultas. |
| **App Secret** | App → Configurações → Básico | Valida a assinatura dos webhooks. |
| **Graph API Base URL** | Padrão `https://graph.facebook.com/v21.0` | Só altere se usar proxy/gateway. |

> **Importante:** Token e App Secret são criptografados no banco. Depois de salvos, a página mostra apenas “(configurado)”. Deixar o campo em branco preserva o valor atual.

> **Ao trocar de provedor:** informe o novo Token. Se deixar em branco, o token do provedor anterior pode ser mantido.

### 5.3 Campos da Evolution API

| Campo | O que é |
|---|---|
| **Base URL** | URL do servidor Evolution, ex.: `https://evolution.suaempresa.com` |
| **Instância** | Nome da instância criada na Evolution |
| **API Key** | Chave de autenticação da Evolution |

### 5.4 Botões

| Botão | O que faz |
|---|---|
| **Salvar configuração** | Grava provider, status, credenciais, número de teste e retenção. |
| **Testar conexão** | Valida token/ID na Cloud ou o estado da instância na Evolution. Não envia mensagem. |
| **Enviar teste** | Envia uma mensagem de texto para o número de teste configurado. |
| **Conectar / QR Code** | Exclusivo da Evolution; na Cloud informa que não se aplica. |
| **Ver estado** | Consulta o estado da conexão. |

---

## 6. Aba Webhook

A página mostra a URL completa, por exemplo:

```
https://app.suaempresa.com.br/gestaodeponto/TENANT/whatsapp/webhook.php
```

### Cloud API (Meta)

1. Meta for Developers → App → WhatsApp → Configuração.
2. Webhook → Editar.
3. **Callback URL:** cole a URL exibida no módulo.
4. **Verify Token:** cole o mesmo valor salvo na aba Provedor.
5. Salve e assine o campo **messages**.

A Meta fará:

- `GET` de verificação com `hub.mode`, `hub.verify_token` e `hub.challenge`;
- `POST` de mensagens/status com o header `X-Hub-Signature-256`.

O módulo valida a assinatura com o **App Secret**. Se o segredo estiver errado ou vazio, o webhook responde **401**.

### Evolution API

Cadastre a URL no webhook da instância e envie o header `apikey` (ou `?token=`) com a mesma API Key configurada.

O módulo aceita as duas formas: header `apikey` **ou** parâmetro `?token=`. Se a Evolution enviar um header diferente do token global, o `?token=` correto na URL ainda é aceito.

### Requisitos

- HTTPS público, com certificado válido.
- `localhost` e HTTP não funcionam para a Meta.
- Para desenvolvimento, use ngrok/cloudflared e abra a configuração pelo endereço HTTPS do túnel.
- A URL precisa incluir o caminho do tenant.

---

## 7. Aba Templates

- Exclusiva da Cloud API.
- **Sincronizar templates** consulta a WABA e grava nome, idioma, categoria, status e componentes em `wa_template`.
- **Não cria** templates. Crie e aprove no WhatsApp Manager da Meta.
- A interface atual de chat e lote envia **texto livre**; ainda não há seletor de template para envio.
- Fora da janela de 24h, será necessário enviar template. O provider já tem o método, mas a tela ainda não expõe.

---

## 8. Aba Logs

Mostra os últimos 20 eventos de `wa_evento`:

- data;
- provedor;
- tipo;
- status (`recebido`, `processado`, `erro`);
- erro.

Use para diagnosticar webhook, assinatura inválida e falhas de processamento.

---

## 9. Aba Retenção

- Campo **Manter por (dias)**.
- **0 = nunca apagar** (padrão para novas instalações).
- Com valor maior que 0, o cron diário remove mensagens, anexos e eventos antigos.
- A retenção ajuda na LGPD e no tamanho do banco.
- Tenants que já tinham um valor configurado continuam com ele até alterarem a tela.

---

## 10. Como usar o módulo no dia a dia

### 10.1 Chat

- Menu **CRM - TECHPS → Chat**.
- Lista de conversas à esquerda, conversa à direita.
- Busca por nome/telefone; filtros **Todas**, **Não lidas** e **Minhas**.
- Envio com **Enter**; **Shift+Enter** quebra linha.
- Status por mensagem: pendente, enviado, entregue, lido, falhou.
- Botão **Novo** para buscar contato ou cadastrar um número.
- Polling de 5 segundos para novas mensagens.
- Atalhos nas grids de funcionário/usuário abrem a conversa correspondente.
- Acima da lista de conversas, o filtro **Todas** mostra todas as conversas, inclusive números externos que chegam no aparelho; **Da empresa** mostra apenas contatos vinculados ao cadastro de funcionário/usuário.

### 10.1.1 Mídias

- Clique no **clipe** ao lado do campo de texto para anexar um arquivo.
- Clique na **câmera** para abrir a prévia ao vivo (webcam no desktop ou câmera do celular), enquadrar e clicar em **Tirar foto**. Se a câmera ao vivo não estiver disponível, abre o seletor nativo do dispositivo.
- Clique no **microfone** para gravar um áudio; clique novamente para parar. Use **Cancelar** para descartar a gravação.
- É possível enviar imagem, áudio, vídeo ou documento; a legenda é o texto digitado no campo.
- Antes do envio, o chat mostra o nome, o tamanho e a prévia (para imagens).
- O botão **×** remove o arquivo selecionado.
- Limites: imagem 5 MB; áudio, vídeo e documento 16 MB. O limite de documento pode ser ajustado em `WHATSAPP_MEDIA_MAX_MB`.
- Formatos: JPG/PNG/WEBP; OGG/MP3/M4A/AAC/AMR/WAV; MP4/3GP; PDF/DOC/DOCX/XLS/XLSX/PPT/PPTX/TXT/CSV/ZIP.
- Imagens abrem em nova aba; áudios e vídeos têm player; documentos são baixados pelo link.
- Mídias recebidas e enviadas pelo celular também aparecem no histórico.
- O arquivo fica em `WHATSAPP_MEDIA_PATH` (fora do document root) e só é entregue por `arquivo.php`, com sessão e permissão.
- A gravação usa o microfone do dispositivo e exige permissão do navegador; em produção o site precisa estar em HTTPS.
- A câmera usa `getUserMedia` para prévia ao vivo; no celular, prioriza a câmera traseira. Se a permissão for negada, o seletor nativo é aberto.
- O chat evita exibir a mesma mensagem duas vezes quando a atualização automática e o envio acontecem ao mesmo tempo.

### 10.1.2 Mensagens prontas e comando `/`

- Cadastre em **CRM - TECHPS → Mensagens Prontas**.
- O **título/tag** funciona como “quando usar” e agrupa as variações; no cadastro é possível escolher uma tag existente ou digitar uma nova.
- O **subtítulo** é a opção que aparece na lista.
- Variáveis aceitas: `{nome}`, `{primeiro_nome}`, `{setor}`, `{cargo}`, `{empresa}` e `{telefone}`; também funciona com chaves duplas (`{{nome}}`).
- No campo **Mensagem**, clique nos botões de variáveis para inseri-las na posição do cursor.
- No chat, digite `/` no início do campo (ou após um espaço) para abrir a lista.
- Selecione o título e depois o subtítulo; o texto é inserido no campo já com as variáveis do contato para revisão antes de enviar.
- No envio em lote, selecione **Título** e **Subtítulo** na etapa 2; o corpo é preenchido no campo de mensagem.
- Mensagens inativas não aparecem no chat nem no lote.

### 10.1.3 Exportar conversa

- Abra a conversa e clique no ícone de **download** no cabeçalho.
- Escolha **Últimos N dias** e informe a quantidade, ou **Intervalo de datas** com data inicial e final.
- O arquivo é um **CSV** com ID, data/hora, direção, tipo, mensagem, status, usuário, contato, telefone, anexo, MIME e ID do provedor.
- O download é autenticado, registra a exportação no log e respeita o período informado.
- O intervalo máximo aceito é de 10 anos; o período de dias aceita de 1 a 3650.

### 10.2 Contatos

- Menu **CRM - TECHPS → Contatos**.
- **Sincronizar contatos** importa funcionários/usuários ativos.
- Filtros por nome, empresa, setor, cargo e opt-in.
- Ações: abrir conversa, copiar telefone.
- Checkbox + **Enviar em lote** leva os contatos para o assistente de lote.

### 10.3 Envio em lote

1. **Destinatários:** filtros e seleção manual.
2. **Mensagem:** texto com variáveis `{nome}`, `{primeiro_nome}`, `{setor}`, `{cargo}`, `{empresa}`, `{telefone}`. Os botões de variáveis inserem cada uma na posição do cursor.
3. **Prévia:** totais, opt-in e confirmação.
4. **Envio:** cria o lote, enfileira e mostra o progresso.

Regras:

- limite de 200 destinatários por lote;
- somente opt-in, salvo liberação explícita de administrador;
- a fila é processada pelo cron;
- administrador pode usar **Processar fila agora** na etapa 4;
- relatório pode ser exportado em CSV.

### 10.4 Widget flutuante

- Botão fixo no canto inferior direito.
- Abre um mini chat com lista de conversas.
- Link **Abrir módulo completo**.
- Só aparece com `WHATSAPP_ENABLED=1` e permissão de chat.

### 10.5 Permissões

- A migração libera os itens para perfis administrativos.
- Demais perfis: **Cadastro de Perfil de Acesso → CRM - TECHPS**.
- O widget e o menu respeitam a permissão de `/whatsapp/index.php`.

### 10.6 Atualização automática

- O **widget flutuante** consulta as conversas a cada 5 segundos, mesmo com o painel fechado.
- O badge vermelho no ícone do CRM - TECHPS mostra o total de mensagens não lidas.
- A lista de conversas do chat também é atualizada a cada 5 segundos.
- Ao abrir uma conversa com a aba focada, ela é marcada como lida.
- Ao voltar para a aba, a lista e a conversa aberta são atualizadas.
- Não é necessário recarregar a página para ver mensagens novas.

### 10.7 Chamadas de voz

Disponível somente com **Cloud API** e número com Calling habilitado na Meta.

- Em **Configurações → Chamadas**, selecione **Ativo** e salve; use **Habilitar calling no número** para ligar o recurso na Graph API.
- No chat, o ícone de telefone fica habilitado quando o recurso está ativo.
- Na primeira ligação, o contato pode precisar autorizar; use **Solicitar permissão**. A chamada começa após a aprovação no WhatsApp.
- Para chamadas recebidas, mantenha o CRM aberto; o sistema consulta chamadas pendentes e mostra **Aceitar / Rejeitar**.
- Durante a chamada: **Mudo** e **Encerrar**.
- Requisitos: limite de mensagens ≥ 2.000 destinatários, linha de crédito, webhook `calls` e permissão do usuário.
- A Evolution API não realiza chamadas oficiais; o botão fica desabilitado nesse provedor.
- É necessário HTTPS e permissão de microfone no navegador. A chamada real deve ser homologada com a conta Cloud API de produção.

**Evolution API:** o WhatsApp Web oficial faz chamadas, mas a Evolution usa Baileys, que não implementa oferta/atendimento de áudio. O endpoint `call/offer` é um stub. Para voz real na Evolution seria necessário um webphone terceirizado (Wavoip) com token/dispositivo próprio; a alternativa oficial é a Cloud API com número elegível.

---

## 11. Cron

O envio em lote e os retries dependem do cron:

```
* * * * * /usr/local/bin/php /home/USUARIO/public_html/gestaodeponto/TENANT/whatsapp/cron.php >> /home/USUARIO/logs/whatsapp-TENANT.log 2>&1
```

O cron:

- processa até 20 mensagens pendentes por execução;
- aplica até 3 novas tentativas com backoff (1, 5 e 15 minutos);
- sincroniza contatos uma vez por dia;
- aplica a retenção uma vez por dia; com 0, não apaga nada.

---

## 12. Regras da Cloud API

1. **Janela de 24 horas:** texto livre só até 24h após a última mensagem do contato.
2. **Templates:** fora da janela, use template aprovado.
3. **Opt-in:** necessário para contato e marketing.
4. **Qualidade:** bloqueios e denúncias reduzem o limite do número.
5. **Cobrança:** consulte a tabela vigente da Meta.
6. **Produção:** verificação do negócio, nome de exibição, pagamento e número aprovado.

---

## 13. Segurança e LGPD

- Tokens e App Secret criptografados com `APP_KEY`.
- CSRF em todos os POSTs autenticados.
- Prepared statements em todas as consultas.
- Webhook com assinatura/apikey e idempotência.
- Mídias em pasta protegida por `.htaccess`; download por `arquivo.php`.
- Controle de opt-in em `wa_contato`.
- Retenção configurável.
- Não usar a base de funcionários para marketing sem base legal.

---

## 14. Problemas comuns

| Sintoma | Causa provável | Solução |
|---|---|---|
| “Configure e ative o provedor” | Status inativo ou credenciais incompletas | Salvar e ativar. |
| Teste de conexão falha | Token expirado, ID errado ou permissão faltando | Gerar novo token e conferir IDs. |
| Webhook não valida | Verify Token diferente | Igualar nos dois lados. |
| Webhook 401 | App Secret errado/ausente | Preencher e salvar. |
| Webhook 400 | Corpo inválido/vazio ou proxy | Verificar payload e proxy. |
| Nenhum evento aparece | Flag desligada, webhook não assinado ou URL inacessível | Ligar flag e assinar `messages`. |
| Enviar teste falha | Número de teste vazio, não autorizado ou fora da janela | Preencher o número e testar com template/contato que já enviou mensagem. |
| QR não conecta | QR expirado, limite de dispositivos ou bloqueio do WhatsApp | Gerar novo QR e escanear em segundos; remover dispositivos antigos; testar outro número. |
| Lote pendente | Cron não configurado ou provedor inativo | Configurar cron/ativar provedor. |
| Menu não aparece | Flag desligada ou perfil sem permissão | `WHATSAPP_ENABLED=1` e liberar no perfil. |
| Templates não sincronizam | WABA/token/permissão | Conferir WABA e permissões do System User. |
| QR Code não funciona | Provedor Cloud | QR é exclusivo da Evolution. |
| “APP_KEY não configurada” | Falta no `.env` | Definir `APP_KEY`. |

---

## 15. Limitações atuais

- Sem envio de template pela interface (apenas sincronização).
- Sem upload/download de anexos pela interface.
- Sem painel de qualidade/limites da Meta.
- Webhook é cadastrado manualmente no painel da Meta/Evolution.
- Chat e lote enviam texto; fora da janela de 24h é necessário template.

---

## 16. Checklist de ativação da Cloud API

- [ ] `WHATSAPP_ENABLED=1` no `.env`.
- [ ] `APP_KEY` configurada.
- [ ] App Business criado e produto WhatsApp adicionado.
- [ ] WABA e número vinculados.
- [ ] Phone Number ID e WABA ID anotados.
- [ ] Access Token permanente de System User gerado.
- [ ] App Secret copiado.
- [ ] Verify Token criado.
- [ ] Migração executada (`php whatsapp/migrar.php`).
- [ ] Campos da Cloud salvos na página.
- [ ] **Testar conexão** com sucesso.
- [ ] Webhook cadastrado na Meta com a URL e o Verify Token.
- [ ] Campo **messages** assinado.
- [ ] **Enviar teste** funcionando.
- [ ] Status do módulo **Ativo**.
- [ ] Cron configurado.
- [ ] Perfis liberados.
- [ ] Opt-in e LGPD validados.
- [ ] Templates aprovados para uso fora da janela de 24h.

---

## 17. Glossário

| Termo | Significado |
|---|---|
| **Cloud API** | API oficial da Meta para WhatsApp Business. |
| **WABA** | WhatsApp Business Account. |
| **Phone Number ID** | ID do número na Cloud API. |
| **Access Token** | Token de autorização da API. |
| **App Secret** | Segredo do App, usado na assinatura do webhook. |
| **Verify Token** | String que você cria para validar o webhook. |
| **Template** | Mensagem pré-aprovada pela Meta. |
| **Janela de 24h** | Período em que texto livre é permitido após a última mensagem do contato. |
| **Opt-in** | Consentimento do contato para receber mensagens. |
| **Evolution API** | Gateway não oficial que conecta via QR Code. |
