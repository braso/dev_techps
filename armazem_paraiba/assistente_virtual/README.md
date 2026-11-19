# Assistente Virtual TechPS

Assistente interno de dúvidas do sistema, com **base própria no MySQL do tenant** e **geração de respostas 100% local** via Ollama. Não usa API externa e não consulta dados pessoais.

## O que ele responde

- Como acessar, configurar e usar as telas do sistema.
- O que cada campo, botão e rotina faz.
- Passo a passo de cadastros, ponto, espelho, endossos, férias, diárias, documentos, assinatura, treinamento e suporte.
- Onde fica cada tela e qual o caminho do menu.

**Fora do escopo:** dados do próprio usuário (espelho, saldo, ajustes), cálculos trabalhistas, questões jurídicas, aprovações e qualquer ação que altere dados.

## Requisitos

- PHP 8.1+ com `mysqli`, `curl`, `mbstring`, `dom` e `json`.
- MySQL/MariaDB com `utf8mb4` e, preferencialmente, `FULLTEXT` em InnoDB.
- [Ollama](https://ollama.com/) local com um modelo de chat e, opcionalmente, `nomic-embed-text`.
- Para DOCX: `ZipArchive`, `PharData` ou `unzip` disponível no servidor.
- Para PDF: `pdftotext` (poppler-utils).

## Configuração

Adicione ao arquivo de ambiente do tenant (o mesmo carregado por `load_env.php`):

```env
ASSISTENTE_ENABLED=1
ASSISTENTE_PROVIDER=ollama
ASSISTENTE_MODEL=qwen2.5:1.5b
ASSISTENTE_MODEL_COMPLEX=qwen2.5:3b
ASSISTENTE_ROUTER=1
ASSISTENTE_EMBED_MODEL=nomic-embed-text
ASSISTENTE_OLLAMA_URL=http://127.0.0.1:11434
ASSISTENTE_MAX_TOKENS=350
ASSISTENTE_TEMPERATURE=0.1
ASSISTENTE_DAILY_QUOTA=300
ASSISTENTE_RATE_LIMIT=20
ASSISTENTE_TIMEOUT=180
ASSISTENTE_RETENTION_DAYS=90
ASSISTENTE_TOP_CHUNKS=5
ASSISTENTE_MIN_SCORE=0.9
ASSISTENTE_NUM_CTX=6144
ASSISTENTE_NUM_THREAD=0
ASSISTENTE_KEEP_ALIVE=1h
ASSISTENTE_EMBEDDINGS=0
```

Em Docker, use `ASSISTENTE_OLLAMA_URL=http://host.docker.internal:11434`.

### Modelos recomendados

| Cenário | Modelo | Observação |
|---|---|---|
| Piloto em CPU | `qwen2.5:1.5b` | Rápido; respostas quentes em ~3–5 s |
| Perguntas complexas em CPU | `qwen2.5:3b` | Usado pelo roteador; mais lento, porém mais preciso |
| Produção com GPU 12–16 GB | `qwen2.5:7b` | Melhor qualidade e latência |
| Produção com GPU 24 GB | `qwen2.5:14b` | Melhor aderência a regras e português |

Baixe com `ollama pull qwen2.5:1.5b` e `ollama pull qwen2.5:3b` (ou 7B/14B). O assistente não baixa modelos sozinho.

O roteador usa `ASSISTENTE_MODEL_COMPLEX` quando a pergunta contém termos de regra/cálculo (CNPJ, endosso, banco de horas, cálculo, parâmetro etc.). Para desligar e usar um único modelo, defina `ASSISTENTE_ROUTER=0`.

## Migração e ingestão

```bash
php armazem_paraiba/assistente_virtual/migrar.php
php armazem_paraiba/assistente_virtual/ingestao.php --all
php armazem_paraiba/assistente_virtual/ingestao.php --status
```

Opções:

```bash
php ingestao.php --manual          # manual do sistema
php ingestao.php --docs            # documentação .md e POP
php ingestao.php --menu            # mapa de telas
php ingestao.php --faq             # FAQs aprovadas
php ingestao.php --reset --all     # limpa e reindexa
php ingestao.php --all --embeddings
php ingestao.php --limpar-retencao
```

A ingestão é idempotente por hash: o mesmo conteúdo não é duplicado. Quando um documento muda, a versão antiga é desativada e a nova entra.

### Recuperação da base

A consulta em tempo de execução é feita nas tabelas MySQL `ia_documento`, `ia_chunk` e `ia_faq`. O `knowledge/manual_espelho_ponto.md` é apenas a fonte usada na ingestão; ele não é lido a cada pergunta.

Se o banco for restaurado de um backup anterior à ingestão, essas três tabelas voltam vazias e o assistente passa a responder “não encontrei”. Nesse caso:

1. Acesse o assistente ou o painel admin. Na primeira requisição, o módulo detecta a base vazia e reindexa automaticamente a partir dos arquivos locais.
2. Se preferir forçar, rode `php armazem_paraiba/assistente_virtual/ingestao.php --all`.
3. Confira com `php armazem_paraiba/assistente_virtual/ingestao.php --status`.

Evite `--reset` sem rodar a ingestão em seguida: ele limpa as tabelas antes de reconstruir. O painel admin também mostra um aviso quando a base está vazia.

## Deploy em produção (multi-tenant)

Cada cliente tem uma pasta e um banco MySQL próprios. O código é o mesmo, mas a migração e a ingestão rodam **dentro da pasta de cada cliente**, usando o ambiente efetivo daquele tenant. Não existe uma base central.

1. Publique o código normalmente pelo `.cpanel.yml`. Ele copia `armazem_paraiba/*` para cada pasta de cliente.
2. Confira no **ambiente efetivo** de cada cliente (`TECHPS_ENV_FILE`, `techps.env` fora do `public_html` ou `.env` da pasta):
   ```ini
   ASSISTENTE_ENABLED = 1
   ASSISTENTE_PROVIDER = extrativo
   ```
   Com `extrativo`, não é preciso Ollama nem modelos. Se um dia quiser usar o LLM local:
   ```ini
   ASSISTENTE_PROVIDER = ollama
   ASSISTENTE_OLLAMA_URL = http://127.0.0.1:11434
   ```
   e mantenha o Ollama rodando com os modelos `qwen2.5:1.5b` e `qwen2.5:3b`.
   Enquanto `ASSISTENTE_ENABLED = 0`, o widget não aparece para os usuários; o **Super Administrador sempre vê**, porque não tem perfil de acesso para marcar o item. Para liberar um beta tester não-admin, defina `1` no ambiente efetivo daquele tenant e marque o perfil dele em **Cadastros > Perfil de Acesso > Assistente Virtual**.
3. Depois do deploy, na raiz do deploy (`/home/tech1694/public_html/gestaodeponto`), rode uma vez o script que percorre os tenants:
   ```bash
   bash deploy_assistente_tenants.sh
   # ou somente quem já está com ASSISTENTE_ENABLED=1:
   bash deploy_assistente_tenants.sh --enabled-only
   ```
   Ele entra em cada pasta, roda `migrar.php` e `ingestao.php --all`, e grava o log em `~/deploy_assistente.log`.
4. Se preferir não rodar o script, o primeiro acesso ao assistente em cada tenant já cria as tabelas e reconstrói a base automaticamente. O script só evita a primeira requisição lenta e mostra erros por cliente.

O `knowledge/manual_espelho_ponto.md` é versionado de propósito: se o servidor não conseguir ler o DOCX, a ingestão usa esse espelho. O DOCX oficial continua em `docs/` e é a fonte preferencial.

Para liberar os beta testers em cada tenant: **Cadastros > Perfil de Acesso > Assistente Virtual**. O Super Administrador sempre vê o assistente. A migração libera o item de menu para os perfis que têm usuários de nível Administrador/Super Administrador; os demais perfis precisam ser marcados manualmente.

### Cron de retenção

Para aplicar a retenção de conversas e logs, agende no cPanel:

```
0 3 * * * /usr/bin/php /caminho/do/tenant/armazem_paraiba/assistente_virtual/ingestao.php --limpar-retencao
```

`ASSISTENTE_RETENTION_DAYS=0` desativa a exclusão automática.

## Menu e permissões

A migração registra:

- `/assistente_virtual/index.php` — **Assistente Virtual** (acesso beta controlado por perfil).
- `/assistente_virtual/admin.php` — **Base de Conhecimento (IA)** (perfis administrativos).

**Beta:** o Super Administrador sempre vê o assistente, pois não possui perfil de acesso para marcar o item. Para os demais usuários, o widget só aparece com `ASSISTENTE_ENABLED=1` e o item **Assistente Virtual** marcado no perfil. Não há liberação automática por nível operacional. O conteúdo continua filtrado por `menu_path` e `chk_tx_perfis`; uma rotina administrativa não é respondida para um funcionário.

Quando o **CRM - TECHPS** também está ativo, o botão do assistente fica ao lado do botão do WhatsApp, sem sobrepor os ícones. O painel do assistente abre acima dos dois botões.

## Como funciona

1. O usuário pergunta pelo widget ou pela página completa.
2. A API valida sessão, CSRF, rate limit e quota.
3. Se houver FAQ aprovada suficientemente próxima, devolve a resposta literal (sem LLM).
4. Caso contrário, busca trechos no MySQL (FULLTEXT + boost por título/fonte) e filtra por permissão.
5. Se a relevância for baixa, responde “não encontrei” e sugere o suporte.
6. Se houver base, monta o contexto e chama o Ollama local com streaming.
7. A interface exibe as fontes (documento, seção e caminho da tela).

## FAQs e modo sem Ollama

As FAQs aprovadas ficam na tabela **`ia_faq`** do banco de cada tenant. O arquivo `knowledge/faq_seed.md` é a carga inicial; o admin pode criar, editar e desativar FAQs em **Base de Conhecimento (IA)**. A resposta por FAQ é determinística: se a pergunta do usuário for suficientemente próxima, o assistente devolve o texto aprovado, sem passar pelo LLM. As FAQs também são indexadas em `ia_documento`/`ia_chunk` para a busca geral.

O Ollama é opcional. Para operar sem LLM:

```ini
ASSISTENTE_PROVIDER = extrativo
```

Em produção, defina isso no **arquivo de ambiente efetivo do tenant**: `TECHPS_ENV_FILE`, `techps.env` fora do `public_html` ou o `.env` da pasta do cliente (nessa ordem de busca do `load_env.php`).

Nesse modo:

- Perguntas com FAQ aprovada recebem a resposta literal.
- As demais perguntas retornam os trechos mais relevantes do manual/documentação, com as fontes.
- Não há geração de texto por IA; as respostas são mais literais e podem ser mais longas.
- O painel admin mostra **Determinística (sem LLM)** e não tenta conectar no Ollama.
- Nenhuma chamada externa é feita.

É o modo indicado quando o servidor não tem GPU/RAM para o Ollama ou quando o cliente não quer instalar o modelo.

## Desempenho

- **FAQ aprovada:** resposta instantânea, sem LLM.
- **Saudação (oi/olá):** resposta instantânea, sem LLM.
- **1.5B em CPU:** ~3–5 s com o modelo aquecido; o primeiro uso após o descarregamento pode levar ~30 s.
- **3B em CPU:** ~50 s; usado apenas para perguntas complexas pelo roteador.
- `ASSISTENTE_KEEP_ALIVE=1h` mantém o modelo carregado e evita o cold start.
- No admin, o card **Ollama** mostra **Conectado/Desconectado** e o botão **Testar conexão com o Ollama** mede a latência.
- Para produção, o caminho é GPU + 7B/14B.

**Base inicial:** 27 documentos, 305 trechos e 28 FAQs.

## Segurança

- CSRF em todas as ações.
- Rate limit por sessão e quota diária.
- Prepared statements em todas as consultas.
- Filtro por perfil e caminho de tela antes de montar o contexto.
- Links de tela validados contra o menu permitido.
- Markdown sanitizado no front-end.
- Logs de consulta mascaram CPF, e-mail e telefone.
- Nenhuma chamada externa; nenhuma ferramenta de dados pessoais.
- Retenção configurável (`ASSISTENTE_RETENTION_DAYS`).

## Estrutura

```text
assistente_virtual/
  bootstrap.php          # ambiente, conexão, permissões, migrações e libs
  index.php              # página completa
  admin.php              # curadoria, estatísticas e teste
  api.php                # endpoint JSON/SSE
  widget.php             # botão flutuante global
  migracoes.php          # tabelas ia_* e menu
  migrar.php             # migração CLI
  ingestao.php           # ingestão CLI
  lib/                   # config, db, text, ingest, retrieval, provider, audit
  assets/                # CSS e JS
  knowledge/faq_seed.md  # FAQs aprovadas iniciais
  tools/                 # conversor auxiliar do manual
  testes/                # testes CLI de busca e Ollama
```

## Testes locais

```bash
php testes/teste_ollama.php
php testes/teste_permissoes.php
php testes/teste_busca.php "Como consultar o espelho de ponto?"
php testes/teste_busca_perfil.php "Como cadastrar um funcionário?"
```

Os testes de perfil usam uma sessão de funcionário e confirmam que rotinas administrativas não são retornadas. O `teste_permissoes.php` cobre admin, funcionário, FAQ restrita, FAQ pública e pergunta fora do sistema.

## Observações

- O manual canônico é `docs/Detalhamento_Manual_Espelho de Ponto - V08_30_03_2026.docx`; a documentação `.md`, o POP e o mapa de telas complementam.
- O manual e os uploads locais não são versionados; o código e a FAQ inicial são.
- A qualidade depende da curadoria. Revise as respostas ruins e as perguntas sem resposta no admin.
