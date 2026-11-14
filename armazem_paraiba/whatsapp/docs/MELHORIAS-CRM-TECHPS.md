# CRM - TECHPS — Backlog de evolução do módulo WhatsApp

> **Módulo:** CRM - TECHPS (internamente `armazem_paraiba/whatsapp/`)
> **Data:** 13/11/2026
> **Status:** em implementação; itens 1, 2, 4, 5 e 6 concluídos no código. Itens 2 e 3 aguardam homologação real com conta Cloud API elegível.
> **Plano de controle:** `PLANOS_DE_DESENVOLVIMENTO.md` — PLANO-001.

## 1. Resumo

| Item | Mudança | Status | Estimativa padrão |
|---|---|---|---|
| 1 | Renomear a interface de WhatsApp para **CRM - TECHPS** | Concluído em 13/11/2026 | 1 dia |
| 2 | Enviar e receber mídias + câmera e gravação de áudio | Implementado; teste real pendente | 4–6 dias |
| 3 | Ligações quando a API permitir | Estrutura implementada; homologação real pendente | 2–3 dias (spike/flag) + integração WebRTC/SIP |
| 4 | Retenção padrão **0 = nunca apagar** | Implementado em 13/11/2026 | 0,5 dia (dentro da Fase E4) |
| 5 | Exportar conversas por dias ou intervalo de datas | Implementado em 13/11/2026 | 1,5–2,5 dias (dentro da Fase E4) |
| 6 | Separar mensagens e cadastrar mensagens pré-prontas | Implementado em 13/11/2026 | 3–5 dias |

**Total da evolução:** 13–20 dias úteis (média 16,5).
**Sem ligações (se a API não permitir):** 11–17 dias úteis (média 13,5).
**Com DEV IA:** 4–7 dias úteis (média 5,5).

## 2. Ordem de implementação

1. Renomeação visual e documental.
2. Estrutura de mídias no provedor e no armazenamento.
3. Retenção 0 e exportação.
4. Filtro “Todas” x “Da empresa”.
5. Mensagens pré-prontas (cadastro, lote e comando `/`).
6. Ligações condicionais, somente depois do spike técnico.
7. Testes integrados, permissões e documentação.

> A ordem pode ser ajustada, mas cada item deve ter aceite antes de avançar para o próximo.

---

## 3. Item 1 — Renomear para CRM - TECHPS

### Objetivo

Trocar o nome exibido ao usuário de “WhatsApp” para **CRM - TECHPS**, mantendo a integração WhatsApp por trás.

### Escopo

- Título do menu lateral e da seção.
- Título das páginas: chat, contatos, envio em lote e configurações.
- Widget flutuante: título, `title` e `aria-label`.
- Textos de estado vazio, modais, avisos e botões.
- Documentação do módulo.

### O que **não** muda

- Pasta `armazem_paraiba/whatsapp/`.
- Tabelas `wa_*`.
- Flag `WHATSAPP_ENABLED` e demais variáveis de ambiente.
- Rotas `/whatsapp/*.php` e permissões existentes.
- Classes, funções e nomes internos do código.

### Critérios de aceite

- O menu mostra **CRM - TECHPS**.
- As páginas e o widget mostram **CRM - TECHPS**.
- Nenhuma rota, tabela ou permissão existente quebra.
- A documentação usa “CRM - TECHPS (módulo WhatsApp)” quando precisar diferenciar.

### Implementação (13/11/2026)

- Menu lateral, chat, contatos, envio em lote, configurações e widget renomeados.
- Atalhos dos cadastros de funcionário e usuário atualizados.
- Tela de perfil de acesso passou a exibir a seção como **CRM - TECHPS**.
- Documentação e plano de controle atualizados.
- Pasta, rotas, tabelas `wa_*` e flag `WHATSAPP_ENABLED` preservadas.

---

## 4. Item 2 — Mídias

### Objetivo

Permitir enviar e receber imagens, documentos, áudio e vídeo pelo chat, quando o provedor oferecer suporte.

### O que a API oferece

| Provedor | Envio | Recebimento | Observações |
|---|---|---|---|
| Cloud API (Meta) | Imagem, áudio, vídeo e documento | Mídia chega por `media_id`; é preciso baixar com o token | Formatos e limites variam por tipo; áudio e vídeo têm validação própria |
| Evolution API | `sendMedia` com URL ou base64 | O webhook pode trazer URL, base64 ou metadados | A resposta varia por versão; validar no ambiente usado |

### Escopo

- Upload de arquivo no chat com preview antes do envio.
- Tipos iniciais: imagem (`jpg`, `jpeg`, `png`, `webp`), documento (`pdf`, `doc`, `docx`, `xls`, `xlsx`), áudio (`ogg`, `mp3`, `m4a`) e vídeo (`mp4`).
- Validação de tipo e tamanho no cliente e no servidor.
- Armazenamento fora do document root (`WHATSAPP_MEDIA_PATH`) com nome aleatório.
- Download somente por `arquivo.php`, com verificação de permissão.
- Registro do tipo, nome original, MIME, tamanho e caminho na mensagem.
- Exibição de imagem com preview, áudio com player, vídeo com player e documento com link.
- Tratamento de falha de upload e de envio, com status na mensagem.
- Recebimento de mídia pelo webhook: baixar quando necessário, salvar e vincular à conversa.

### Fora do escopo inicial

- Gravação de vídeo pelo navegador.
- Edição de imagem, recorte ou transcrição de áudio.
- Stickers, enquetes, localização e contatos.

### Critérios de aceite

- Enviar uma imagem e visualizar o preview no histórico.
- Enviar um PDF e baixar pelo link autenticado.
- Enviar um áudio e reproduzir no chat.
- Receber uma mídia e visualizá-la/baixá-la.
- Arquivo acima do limite é rejeitado com mensagem clara.
- Usuário sem permissão não baixa mídia de outra conversa.

### Pontos técnicos

- Adicionar `sendMedia()` na interface de provedor e implementar em Cloud API e Evolution.
- Reutilizar `wa_mensagem` ou criar campos de metadados de mídia, se necessário.
- Definir limite inicial conservador (ex.: 16 MB) e ajustar conforme o provedor.
- Validar MIME real com `finfo`, não confiar apenas na extensão.

### Implementação (13/11/2026)

- Upload pelo botão de clipe no rodapé do chat, com preview de imagem e remoção antes do envio.
- Botão de câmera com prévia ao vivo (webcam no desktop ou câmera do celular), captura em JPEG e fallback para o seletor nativo quando a câmera não está disponível.
- Botão de microfone para gravar áudio no navegador (`MediaRecorder`), com timer, cancelamento e anexo antes do envio.
- Formatos de gravação priorizam MP4/M4A; o navegador pode usar OGG/Opus quando disponível. WebM é fallback e pode não ser aceito pelo provedor.
- Tipos aceitos: JPG/PNG/WEBP, OGG/MP3/M4A/AAC/AMR/WAV, MP4/3GP, PDF/DOC/DOCX/XLS/XLSX/PPT/PPTX/TXT/CSV/ZIP.
- Limites: imagem 5 MB; áudio, vídeo e documento 16 MB (documento pode subir com `WHATSAPP_MEDIA_MAX_MB`).
- Envio pela fila existente; o arquivo é gravado em `WHATSAPP_MEDIA_PATH`, fora do document root, com nome aleatório.
- Download somente por `arquivo.php`, com sessão e permissão; documentos usam `download=1`.
- Recebimento pela Cloud API: baixa o `media_id` e salva antes da URL expirar.
- Recebimento pela Evolution: usa `base64` do webhook, endpoint `getBase64FromMediaMessage` ou URL de fallback.
- Mensagens de saída enviadas pelo celular também têm a mídia baixada e exibida.
- Testes: `testes/teste_midia.php` valida MIME, limites, sanitização e gravação; `testes/teste_providers.php` cobre o parse de mídia dos dois provedores.
- Layout do chat com imagem, documento, legenda e botões de clipe/câmera/microfone validado em tela local.
- Correção de duplicidade visual: o chat ignora IDs de mensagens já renderizadas e não dispara duas buscas simultâneas; o envio também tem trava contra duplo clique/Enter.
- Envio real para um número válido ainda precisa ser homologado; o teste com número inexistente confirmou o caminho de erro e reagendamento.

---

## 5. Item 3 — Ligações

### Objetivo

Oferecer botão de ligação no chat **somente se** o provedor e a conta permitirem.

### O que a API oferece

- **Cloud API:** a Calling API oficial permite **fazer e receber chamadas de voz** com usuários do WhatsApp via VoIP/WebRTC ou SIP.
- **Chamadas iniciadas pela empresa (business-initiated):** o caso do botão “Ligar” do gestor, disponível nas localidades da Cloud API, exceto Estados Unidos, Canadá, Egito, Vietnã e Nigéria. O Brasil não está na lista de exceção, mas o número precisa estar habilitado e elegível.
- **Chamadas iniciadas pelo usuário:** disponíveis em todas as localidades da Cloud API.
- **Evolution API/Baileys:** não oferece uma API oficial e estável para iniciar chamadas de voz. Eventos de chamada podem aparecer no webhook, mas isso não equivale a discar.

### Pesquisa oficial (13/11/2026)

A documentação oficial da Meta confirma o suporte. Para o TechPS, uma chamada iniciada pelo gestor exige:

1. Número em uso na **Cloud API** (não no WhatsApp Business app).
2. App inscrito no webhook `calls` e com permissão `whatsapp_business_messaging`.
3. Limite diário de mensagens de pelo menos **2.000 destinatários únicos**.
4. **Calling habilitado** no número; vem desabilitado por padrão.
5. Linha de crédito/forma de pagamento válida; chamadas são cobradas por duração, em pulsos de 6 segundos. Para o usuário, a chamada é gratuita.
6. Permissão do usuário para receber chamada da empresa: temporária (7 dias) ou permanente. No Brasil, fora do modelo quality-based (Colômbia/México), é necessário obter essa permissão por mensagem interativa ou template.
7. Limite de **100 chamadas conectadas por usuário/24h** e limites de pedido de permissão (1 em 24h e 2 em 7 dias por par).
8. Implementação de **WebRTC ou SIP** para o áudio/SDP; a API não fornece pilha de mídia pronta. Também é necessário tratar webhooks de `connect`, `ringing`, `accepted`, `rejected` e `terminate`.

**Conclusão:** é tecnicamente possível e oficial, mas não é apenas um `POST` de mensagem. É uma integração de voz (WebRTC/SIP), com permissão do usuário e requisitos de conta. A estimativa de 2–3 dias cobre o spike e a flag; a implementação completa tende a ser maior.

### Implementação preparada (13/11/2026)

- Tabelas `wa_chamada` e `wa_chamada_evento` (schema versão 6).
- Configuração **Configurações → Chamadas**: status ativo/inativo e botão para habilitar Calling no número.
- Métodos Cloud API para consultar permissão, solicitar permissão, iniciar, pré-aceitar, aceitar, rejeitar e encerrar chamadas.
- Webhook `calls` processa connect, ringing, accepted, rejected e terminate, com SDP e duração.
- Chat com botão de telefone no cabeçalho (habilitado somente com Cloud API + Calling ativo).
- Modal WebRTC para chamada de saída e entrada, com aceitar, rejeitar, mudo e encerrar.
- Polling de chamadas recebidas a cada 3 segundos enquanto o chat está aberto.
- Testes locais: criação de chamada, evento de conexão/resposta SDP, status, término, chamada de entrada pendente e pré-aceite. **Não foi possível testar uma chamada real sem credenciais Cloud API elegíveis.**

### Limitações desta preparação

- O WhatsApp Web oficial faz chamadas, mas a Evolution API usa Baileys, não o cliente Web oficial. O endpoint `call/offer` atual é um stub e não liga de verdade; o Baileys não implementa oferta/atendimento de áudio.
- Para voz real via Evolution seria necessário um webphone terceirizado (Wavoip) com token/dispositivo próprio; a alternativa oficial é a Cloud API com número elegível.
- O número precisa estar na Cloud API e com Calling habilitado na Meta.
- A permissão do usuário é obrigatória no modelo padrão.
- O pedido de permissão usa mensagem interativa livre (janela de 24h); o envio por template aprovado ainda pode ser adicionado.
- A chamada real depende de WebRTC/SIP, microfone e conta elegível; a homologação em produção ainda é necessária.

Fontes oficiais:
- https://developers.facebook.com/documentation/business-messaging/whatsapp/calling/
- https://developers.facebook.com/documentation/business-messaging/whatsapp/calling/business-initiated-calls/
- https://developers.facebook.com/documentation/business-messaging/whatsapp/calling/user-call-permissions/
- https://developers.facebook.com/documentation/business-messaging/whatsapp/calling/call-settings/
- https://developers.facebook.com/documentation/business-messaging/whatsapp/calling/pricing/

### Estratégia

1. Fazer um spike com o número de produção/homologação para confirmar a disponibilidade.
2. Criar uma flag `WHATSAPP_CALLING_ENABLED` ou configuração por tenant.
3. Se disponível, exibir o botão **Ligar** e registrar início/fim/status no histórico.
4. Se indisponível, **não exibir** o botão e documentar o motivo.
5. Não simular ligação com `wa.me` ou `tel:` como se fosse chamada WhatsApp.

### Critérios de aceite

- Com a flag desligada ou API indisponível, o botão não aparece.
- Com a flag ligada e API disponível, a chamada é iniciada e registrada.
- O usuário vê o status da chamada (iniciada, encerrada, falhou).
- A documentação explica claramente a limitação por país/conta.

---

## 6. Item 4 — Retenção padrão 0

### Objetivo

Fazer o módulo **não apagar mensagens por padrão**. O valor `0` significa “nunca apagar”.

### Escopo

- Alterar o padrão da configuração `cfg_nb_retencao_dias` para `0`.
- Ajustar a migração para novos tenants.
- Na tela de configuração, permitir `0` e exibir: **“0 = nunca apagar”**.
- No cron, se o valor for `0` ou menor, pular a rotina de exclusão.
- Manter a exclusão de anexos junto com as mensagens quando a retenção for maior que `0`.
- Registrar no log quando a retenção estiver desativada.

### Critérios de aceite

- Instalação nova nasce com 0 dias.
- Com 0, o cron não apaga mensagens nem anexos.
- Com valor maior que 0, a rotina continua funcionando como antes.
- A tela explica o efeito do 0 sem ambiguidade.

### Atenção

- A mudança de padrão não deve apagar histórico existente.
- Para tenants que já usam retenção, avaliar se o valor atual será mantido ou migrado para 0; a decisão deve ser explícita.

### Implementação (13/11/2026)

- Schema e valor padrão de `cfg_nb_retencao_dias` alterados para `0`.
- Tela de configuração aceita `0`, mostra “0 = nunca apagar” e tem limite de 0 a 3650.
- `wa_limpar_retencao()` retorna sem apagar quando o valor é 0 e registra a desativação no log.
- Configuração local ajustada para 0; tenants existentes mantêm o valor salvo até alterarem a tela.
- Teste: com 0, a rotina retornou `removidas=0` sem tocar no histórico.

---

## 7. Item 5 — Exportação de conversas

### Objetivo

Exportar uma conversa informando **quantidade de dias** ou **datas específicas**.

### Escopo

- Botão **Exportar** no chat.
- Modal com duas opções:
  - **Últimos N dias** (ex.: 7, 30, 60, 90 ou valor livre).
  - **Intervalo de datas** (data inicial e data final).
- Validação: data inicial não pode ser maior que a final; limite máximo configurável.
- Formato inicial: **CSV** com cabeçalho.
- Colunas: data/hora, direção (entrada/saída), tipo, mensagem, status, contato, telefone e usuário responsável.
- Nome do arquivo: `crm-techps-conversa-<contato>-<data>.csv`.
- Download autenticado e registro em log de auditoria.
- Opcional depois: PDF/HTML com aparência de conversa.

### Critérios de aceite

- Exportar os últimos 30 dias e conferir o período no CSV.
- Exportar um intervalo específico e conferir os limites.
- Conversa vazia retorna mensagem amigável.
- Usuário sem permissão não exporta.
- Dados de outro tenant nunca entram no arquivo.

### Implementação (13/11/2026)

- Botão de download no cabeçalho da conversa abre o modal de exportação.
- Modal com “Últimos N dias” (1–3650) ou intervalo de datas; datas validadas no cliente e no servidor.
- Endpoint `exportar.php` autenticado, com CSRF, permissão de chat e auditoria em log.
- CSV em UTF-8 com BOM e separador `;`, contendo ID, data/hora, direção, tipo, mensagem, status, usuário, contato, telefone, anexo, MIME e ID do provedor.
- Fórmulas de planilha são neutralizadas com prefixo `'`.
- Testes via HTTP: exportação dos últimos 30 dias e de um dia específico retornaram CSV com cabeçalho e mensagens.

### LGPD

- Exportação é operação sensível; registrar quem exportou, quando e com qual filtro.
- Evitar incluir mídia binária no CSV; exportar apenas metadados e links, se necessário.

---

## 8. Item 6 — Separação de mensagens e mensagens pré-prontas

### 8.1 Filtro “Todas” x “Da empresa”

- Na lista de conversas, adicionar abas ou segmento:
  - **Todas:** todas as conversas, incluindo números externos que chegam no aparelho.
  - **Da empresa:** somente contatos vinculados ao cadastro de funcionário/usuário (`cont_nb_entidade` ou `cont_nb_user`).
- Manter a posição da conversa e o campo de resposta.
- Não alterar o badge de não lidas.

### 8.2 Cadastro de mensagens pré-prontas

Criar um cadastro administrativo com:

| Campo | Descrição |
|---|---|
| Título | Quando usar. Ex.: “Primeiro contato com cada motorista de um novo cliente.” |
| Subtítulo | Código ou descrição curta. Ex.: “T1 — Template único, mensagem personalizada.” |
| Mensagem | Corpo do texto, com variáveis opcionais. |
| Ativo | Permite ocultar sem excluir. |

Exemplo do solicitante:

```text
Título: Quando usar: Primeiro contato com cada motorista de um novo cliente.
Subtítulo: T1 Template único, mensagem personalizada.
Mensagem: Olá, {{nome}}! Sou da {{empresa}}...
```

### 8.3 Uso no envio em lote

- No fluxo do lote, adicionar a opção **Usar mensagem pré-pronta**.
- Primeiro selecionar o **título**; depois a lista mostra os **subtítulos** daquele título.
- Exibir preview da mensagem antes de confirmar.
- Substituir variáveis por dados do contato quando disponíveis.
- Manter a opção de escrever uma mensagem livre.

### 8.4 Uso no chat individual

- Ao digitar `/` no campo de mensagem, abrir a lista de títulos.
- Ao escolher o título, listar os subtítulos.
- Ao escolher o subtítulo, inserir o texto no campo para revisão e envio.
- Fechar com `Esc`; seleção por clique.
- Não disparar o comando se `/` fizer parte de uma URL ou texto comum; usar `/` no início do campo ou após espaço.

### 8.5 Permissões

- Administradores: criar, editar, ativar/desativar e excluir mensagens pré-prontas.
- Usuários do chat: usar as mensagens, sem editar.
- Exportação e cadastro de mídias exigem permissão específica.

### Critérios de aceite

- Cadastrar título, subtítulo e mensagem; listar por título e subtítulo.
- No lote, selecionar título e subtítulo e enviar para os destinatários.
- No chat, digitar `/`, escolher título/subtítulo e inserir a mensagem.
- Variáveis são substituídas corretamente ou destacadas quando ausentes.
- Usuário sem permissão não vê o cadastro administrativo.

### Implementação (13/11/2026)

- Lista de conversas com abas **Todas** e **Da empresa**; “Da empresa” filtra contatos vinculados a funcionário/usuário, e “Todas” inclui números externos. O filtro é aplicado no carregamento e no polling da lista.
- Tabela `wa_mensagem_pronta` com título, subtítulo, mensagem, status e auditoria.
- Página **CRM - TECHPS → Mensagens Prontas** com cadastro, edição, exclusão e ativação/inativação.
- O campo **Ordem** foi removido da tela e da lógica em 13/11/2026; a listagem segue título, subtítulo e ID. A coluna permanece no banco sem uso para evitar migração destrutiva.
- Título funciona como tag reutilizável: o formulário lista tags existentes em chips e datalist; o subtítulo sugere as variações daquela tag.
- Campo Mensagem tem botões para inserir `{nome}`, `{primeiro_nome}`, `{setor}`, `{cargo}`, `{empresa}` e `{telefone}` na posição do cursor.
- Menu e permissões atualizados; perfis administrativos recebem o novo item na migração.
- API `mensagens_prontas` liberada para usuários de chat ou lote, retornando apenas itens ativos.
- Chat: digitar `/` abre títulos; clicar no título lista subtítulos; clicar no subtítulo insere o texto já personalizado com os dados do contato; `Esc` fecha.
- Lote: selects de **Título** e **Subtítulo** preenchem o campo de mensagem, mantendo a edição livre e a prévia; botões de variáveis também inserem na posição do cursor.
- O envio de texto também personaliza variáveis no servidor, garantindo consistência mesmo se o texto for editado; aceita `{nome}` e `{{nome}}`.
- Testes: cadastro/exclusão via HTTP, listagem pela API e filtro `Da empresa` retornando somente contatos vinculados a funcionário/usuário.

---

## 9. Testes e validação

- Testes unitários de normalização, permissões, variáveis e limites de mídia.
- Testes de integração com Cloud API e Evolution em homologação.
- Teste de webhook de mídia e de chamada.
- Teste de exportação com período e datas.
- Teste de retenção 0 e retenção > 0.
- Teste de concorrência no lote e no comando `/`.
- Checklist de aceite por item antes de avançar.

## 10. Decisões e pendências

- Limite máximo de mídia por tipo e provedor em produção.
- Exportação em PDF/HTML (opcional; CSV já implementado).
- Disponibilidade real da Calling API no número/país.
- Homologação de envio real de mídia e das permissões por perfil em produção.

## 11. Mapeamento com o Task64/Trello

O bloco atualizado está no `PLANOS_DE_DESENVOLVIMENTO.md`, em **PLANO-001 — CRM - TECHPS**, com:

- contexto do MVP concluído;
- entregáveis da evolução;
- fases E1–E6;
- critérios de aceite;
- dependências, riscos e observações.
