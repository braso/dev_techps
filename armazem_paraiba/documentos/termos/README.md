# Módulo de Termos / Documentos com Placeholders

Módulo para criação de **documentos com texto padrão editável** (ex.: Termo de Compromisso no Ponto Eletrônico, conforme CCT), com preenchimento automático das informações do funcionário, geração em **lote** de PDFs e envio para o **módulo de assinatura eletrônica** já existente no sistema.

Localização: `armazem_paraiba/documentos/termos/`

---

## 1. Visão geral do fluxo

```
Gestor cria o TIPO DE DOCUMENTO (Assinatura = Sim/Não define o fluxo)
        ↓
Gestor cria MODELO (texto padrão com placeholders {{...}})
        ↓
Grid de Modelos → botão Enviar para Assinatura → MODAL
        (empresas, cargos e setores com multi-seleção)
        ↓
Sistema resolve os placeholders com os dados de cada funcionário e gera o PDF (TCPDF)
        ↓
Assinatura = Sim → envia para o módulo de assinatura (e-mail com link, validade 24h)
Assinatura = Não → salva o PDF direto no prontuário do funcionário
        ↓
PDF assinado retorna para arquivos/Funcionarios/{id}/ e aparece na aba Documentos
        ↓
Status acompanhado em "Termos Gerados" e em Assinatura > Documentos
```

---

## 2. Arquivos do módulo

| Arquivo | Função |
|---|---|
| `modelos_termo.php` | CRUD de modelos (nome, tipo de documento, status e texto padrão com placeholders). No grid, ícone **Enviar para Assinatura** abre o modal de seleção em lote |
| `buscar_funcionarios.php` | Endpoint AJAX do modal: lista funcionários conforme empresas, cargos, setores e busca (inclui indicador de termo já existente) |
| `gerar_termos.php` | Tela alternativa de geração em lote com filtros multi-seleção (empresa/cargo/setor), opções (ICP, e-mail, forçar, lote) e barra de progresso |
| `processar_termos.php` | Endpoint AJAX: processa cada funcionário, gera PDF e envia para assinatura |
| `preview_termo.php` | Pré-visualização do texto do modelo: mostra o documento com as tags (`{{...}}`) **destacadas em amarelo** e a lista dos campos que serão preenchidos |
| `listar_termos.php` | Lista dos termos gerados com status, sincronização manual/em massa, cancelamento e botão Voltar |
| `funcoes_termos.php` | Funções compartilhadas: tabelas, placeholders, PDF (logos e cabeçalho), logs, sincronização |
| `logs/` | Logs diários em TXT com retenção automática de 30 dias (pasta bloqueada via `.htaccess`) |
| `README.md` | Este documento |

---

## 3. Banco de dados

As tabelas são criadas automaticamente na primeira utilização (não altera nada do sistema existente).

### `modelo_termo` — o documento configurável
| Coluna | Descrição |
|---|---|
| `mode_nb_id` | PK |
| `mode_tx_nome` | Nome do modelo (ex.: "Termo Ponto Eletrônico - Motorista") |
| `mode_nb_tipo_doc` | FK `tipos_documentos.tipo_nb_id` (define se exige assinatura e o visual do PDF) |
| `mode_tx_conteudo` | Texto padrão em HTML com placeholders |
| `mode_tx_status` | `ativo` / `inativo` |
| Auditoria | `mode_nb_userCadastro`, `mode_tx_dataCadastro`, `mode_nb_userAtualiza`, `mode_tx_dataAtualiza` |

### `modelo_termo_assinante` — assinantes (reservado)
Tabela reservada para futura configuração de assinantes por modelo. Hoje a tela de modelo **não** gerencia assinantes: quando não há assinantes configurados, o documento com assinatura é enviado com o **funcionário como único assinante** (função "Funcionário").

### `termo_gerado` — controle de cada documento gerado
| Coluna | Descrição |
|---|---|
| `terg_nb_id` | PK |
| `terg_nb_modelo` / `terg_nb_entidade` | Modelo e funcionário |
| `terg_nb_tipo_doc` | Tipo de documento usado |
| `terg_tx_status` | `gerado` · `aguardando_assinatura` · `assinado` · `erro` · `cancelado` |
| `terg_tx_caminho` | Caminho do PDF (local quando sem assinatura; assinado quando concluído) |
| `terg_nb_solicitacao_assinatura` / `terg_tx_id_documento` | Vínculo com o módulo de assinatura |
| `terg_nb_documento_funcionario` | Vínculo com a aba Documentos do funcionário |
| `terg_tx_detalhe` | Detalhe/erro |
| `terg_dt_geracao` / `terg_dt_data_assinatura` | Datas |

---

## 4. Placeholders disponíveis

Preenchidos automaticamente com os dados do cadastro do funcionário:

| Placeholder | Origem |
|---|---|
| `{{empresa_razao}}` / `{{empresa}}` | Razão social da empresa do funcionário |
| `{{empresa_cnpj}}` / `{{cnpj}}` | CNPJ formatado |
| `{{empresa_contato}}` | Contato cadastrado na empresa |
| `{{funcionario_nome}}` / `{{nome}}` | Nome completo |
| `{{funcionario_cpf}}` / `{{cpf}}` | CPF formatado |
| `{{funcionario_rg}}` / `{{rg}}` | RG |
| `{{funcionario_pis}}` / `{{pis}}` | PIS |
| `{{ctps_numero}}` / `{{carteira_trabalho}}` | Nº da Carteira de Trabalho |
| `{{ctps_serie}}` | Série da CTPS |
| `{{ctps_uf}}` | UF da CTPS |
| `{{admissao}}` | Data de admissão (dd/mm/aaaa) |
| `{{admissao_extenso}}` | Data de admissão por extenso |
| `{{cargo}}` | Cargo (operacao) com fallback para ocupação |
| `{{matricula}}` | Matrícula |
| `{{cnh_numero}}` / `{{cnh_categoria}}` / `{{cnh_validade}}` | Dados da CNH |
| `{{cidade_assinatura}}` / `{{cidade}}` | Cidade/UF da empresa do funcionário (ex.: "Duque de Caxias/RJ") |
| `{{data_atual}}` / `{{data_atual_extenso}}` | Data de geração |
| `{{email_funcionario}}` | E-mail do funcionário |
| `{{bloco_assinaturas}}` | Linhas de assinatura (estrutura reservada para múltiplos assinantes) |

Tudo entre `{{ }}` que não for reconhecido permanece no texto e é registrado nos logs como token desconhecido.

---

## 5. Como usar

### Passo 1 — Criar o Tipo de Documento
1. Menu **Cadastros > Tipo de Documento** (`cadastro_tipo_doc.php`).
2. Crie o tipo (ex.: "Termo Ponto Eletrônico"), grupo/subgrupo e marque **Assinatura = Sim** quando o documento for assinado. **Essa configuração é a única que define se o documento vai para assinatura ou é só PDF.**
3. No grid do tipo de documento:
   - Coluna **MODELOS**: mostra quantos modelos ativos existem (badge verde "N modelo(s)" e linha destacada quando há modelos).
   - Botão **Termos** (verde quando há modelos): abre a página de Modelos já filtrada pelo tipo.
   - Botões **Visualizar** (editar tipo) e **Excluir**.
4. Opcional: em `documentos/configurar_layout.php` configure cabeçalho/rodapé do tipo. **A logo não precisa ser configurada por tipo**: o PDF usa por padrão a logo do cliente (empresa do funcionário) à direita e a logo do sistema à esquerda.

### Passo 2 — Criar o Modelo
1. Menu **Cadastros > Modelos de Termos** → **Novo Modelo**.
2. Informe **nome**, **tipo de documento** e **status** (se vier do botão "Termos" do tipo, o tipo já vem selecionado).
3. Escreva o texto padrão no editor visual, usando os placeholders pelo menu "Inserir campo do funcionário...".
   - Exemplo: `Entre a empresa {{empresa_razao}} inscrita no CNPJ {{empresa_cnpj}}... o empregado {{funcionario_nome}}, CPF {{funcionario_cpf}}, portador da CTPS nº {{ctps_numero}} série {{ctps_serie}}...`
4. Grave (botões centralizados no rodapé). Use **Pré-visualizar** para conferir o texto com as tags destacadas — o modelo é salvo **sempre com os placeholders**, nunca com dados de funcionário.

### Passo 3 — Enviar para assinatura (modal)
1. Menu **Cadastros > Modelos de Termos**.
2. No grid, clique no ícone **Enviar para Assinatura** do modelo desejado.
3. No modal:
   - **Empresas**: checkboxes com multi-seleção (a empresa do usuário logado já vem marcada). Botões **Marcar todas / Desmarcar todas** e **Selecionar as N primeiras** (quantidade livre).
   - **Cargo / Setor**: checkboxes com multi-seleção (marcar quantos quiser) + **Marcar todos / Desmarcar todos**.
   - **Buscar funcionário**: nome, matrícula ou CPF (vazio = todos).
   - Lista de funcionários com badges de status do termo (sem termo / gerado / aguardando assinatura / assinado) e botões **Marcar todos / Desmarcar todos / Somente sem termo**.
   - Opções: **Validar ICP**, **Enviar e-mail**, **Forçar regeração**.
4. Clique **Enviar para Assinatura**: um documento é gerado **para cada funcionário** com os dados dele e da empresa vinculada.
   - Tipo com **Assinatura = Sim**: cada funcionário recebe o e-mail com o link para assinar o próprio documento; ao assinar, o PDF retorna para `arquivos/Funcionarios/{id}/` e aparece na **aba Documentos** e em **Assinatura > Documentos**.
   - Tipo com **Assinatura = Não**: o PDF é gerado e salvo direto no prontuário (sem e-mail, sem solicitação).

### Passo 4 — Acompanhar
- **Termos Gerados**: status de cada termo, sincronizar (manual ou em massa), cancelar e **Voltar** para a página de Modelos.
- **Aba Documentos do funcionário**: o PDF (gerado ou assinado) aparece lá automaticamente.
- **Assinatura > Documentos**: as solicitações enviadas aparecem no módulo de assinatura.
- **Sino do sistema**: o funcionário vê a pendência de assinatura (badge no sino → "Assinaturas Pendentes") quando o documento foi enviado para assinatura.

---

## 6. Regras de comportamento

- **Assinatura**: determinada **somente** pelo tipo de documento (`tipo_tx_assinatura`). Não existe mais opção "enviar p/ assinatura" na tela de geração — quem decide é `cadastro_tipo_doc.php`.
  - **Sim**: o PDF é gerado temporariamente e enviado pela integração `assinatura/integracao/assinatura_integracao.php` (e-mail com link, validade de 24h). Sem assinantes configurados, o **funcionário é o único assinante**. O PDF assinado retorna a `arquivos/Funcionarios/{id}/` e é registrado em `documento_funcionario` (`docu_tx_assinado = 'sim'`).
  - **Não**: o PDF é salvo direto em `arquivos/Funcionarios/{id}/` e registrado em `documento_funcionario` (`docu_tx_assinado = 'nao'`), status `gerado`; **Validar ICP e Enviar e-mail são ignorados** nesse caminho.
- **Validar ICP**: aplica assinatura digital com certificado ICP-Brasil no PDF final ao concluir as assinaturas (aplica-se quando há assinatura).
- **Enviar e-mail = Não**: o e-mail cadastrado é **desconsiderado** — a solicitação é criada com um e-mail interno (ex.: `sememail.<id>@techps.com.br`) e o funcionário assina pelo sistema (sino/pendências). Com **Sim**, exige e-mail válido.
- **Duplicidade**: o sistema avisa quem já possui termo (badges) e pula quem já tem (`gerado`/`aguardando_assinatura`/`assinado`); só regera com **Forçar regeração = Sim** (status `erro`/`cancelado` não bloqueiam).
- **Pré-visualização**: mostra o texto do modelo com as tags destacadas + painel "Campos usados neste documento"; não gera PDF e nunca salva nada.
- **PDF (visual)**: cabeçalho com logo da empresa do funcionário à **direita** e logo do tipo/sistema à **esquerda** (fallback `imagens/logo_topo_cliente.png`), título centralizado **abaixo** das logos e linha separadora; margens e rodapé com paginação.
- **Exclusão de modelo**: botão Excluir remove o modelo **permanentemente** (e assinantes vinculados). Ativar/Desativar apenas muda o status.

---

## 7. Segurança

- Reaproveita a autenticação e o controle de sessão do sistema (`conecta.php`).
- **Permissão por perfil**: as telas chamam `verificaPermissao()` e o processamento AJAX valida `temPermissaoMenu()`. Administradores passam direto; demais perfis são liberados em **Cadastros > Perfil de Acesso** (os itens aparecem automaticamente na seção "Cadastros").
- Entrada de dados tratada com `htmlspecialchars` e consultas preparadas.
- HTML do editor é **sanitizado** no servidor (allowlist de tags, remoção de `on*`, `javascript:`, `<script>` etc.).
- A pasta de logs é bloqueada via `.htaccess` (negada para acesso web).
- Páginas do módulo não alteram nenhum fluxo existente: apenas adicionam telas, tabelas próprias e itens de menu.

---

## 8. Logs

- Arquivos diários em `logs/termos_AAAA-MM-DD.txt`.
- Registram: data/hora, usuário (login + id), IP, evento, detalhe e extras (modelo, entidade, termo, solicitação).
- Eventos principais: criação/edição/exclusão de modelo, geração, envio para assinatura, erros, sincronização, cancelamento e tentativas sem permissão.
- **Retenção de 30 dias**: a cada gravação o módulo remove automaticamente arquivos com mais de 30 dias — o volume nunca cresce sem limite.

---

## 9. Manutenção / Troubleshooting

- **PDF não reflete alteração do modelo**: os PDFs são gerados no momento do processamento; regere o termo (com "Forçar" se necessário).
- **Status não atualiza sozinho**: use **Sincronizar** ou **Sincronizar Pendentes** em "Termos Gerados" (consulta o status em `solicitacoes_assinatura`).
- **Modelo não aparece no Gerar Termos / modal**: confira se está `ativo` e se o tipo de documento está `ativo`.
- **Erro de envio de assinatura**: confira o e-mail do funcionário/empresa e o SMTP do módulo de assinatura; com "Enviar e-mail = Não" o e-mail não é exigido. O detalhe fica no status do termo e nos logs.
- **Tokens desconhecidos**: aparecem literalmente no texto e são registrados nos logs (`pdf_tokens_desconhecidos`).
- **Filtros não retornam funcionários**: verifique se ao menos uma empresa está marcada (sem empresa o modal não lista).
- **Botões de marcação sem efeito visual**: a sincronização do Uniform é automática; se algum checkbox parecer não marcar, recarregue a página (Cache-Control já evita cache das telas do módulo).

## 10. Referências técnicas

- PDF: TCPDF (`armazem_paraiba/tcpdf/tcpdf.php`), mesmo padrão do módulo `documentos/processar_pdf.php`.
- Assinatura: `armazem_paraiba/assinatura/integracao/assinatura_integracao.php` (`enviarDocumentoParaAssinatura` e `enviarDocumentoParaMultiplosAssinantes`; opção `email_fallback` quando não há e-mail).
- Dados do funcionário: tabelas `entidade`, `empresa`, `operacao`, `grupos_documentos`, `cidade`.
- Referência de fluxo: `armazem_paraiba/treinamento/certificado.php` (padrão de geração + assinatura + controle de status).