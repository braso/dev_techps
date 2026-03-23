# Especificação Técnica: Módulo de Criação e Gestão de Documentos Dinâmicos

Este documento detalha a arquitetura, modelagem e fluxos do novo módulo de documentos dinâmicos para o sistema.

---

## 1. Arquitetura do Módulo

O módulo será construído de forma modular dentro da pasta `/armazem_paraiba/documentos/`, aproveitando o motor `contex20` já existente para garantir consistência visual e de permissões.

### Estrutura de Arquivos Proposta:
- `armazem_paraiba/documentos/`
  - `cadastro_documento.php`: Listagem e busca de documentos gerados.
  - `configurar_layout.php`: Interface administrativa para definir os campos de um tipo de documento.
  - `preencher_documento.php`: Formulário dinâmico para entrada de dados pelo usuário.
  - `preview_documento.php`: Visualização prévia antes da geração do PDF.
  - `processar_pdf.php`: Motor de renderização utilizando a biblioteca TCPDF.
  - `js/documentos.js`: Scripts para manipulação dinâmica de campos (Drag & Drop, validações).
  - `css/documentos.css`: Estilização específica para layouts de documentos.

---

## 2. Modelagem de Banco de Dados

Sugestão de tabelas para suportar a flexibilidade total:

### `documento_campo`
*Armazena a definição de cada campo para um tipo de documento específico.*
- `camp_nb_id` (INT, PK, AI)
- `camp_nb_tipo_doc` (INT, FK -> tipos_documentos)
- `camp_tx_label` (VARCHAR, Ex: "Justificativa")
- `camp_tx_tipo` (ENUM: 'texto_curto', 'texto_longo', 'data', 'selecao', 'usuario', 'setor', 'numero')
- `camp_tx_opcoes` (TEXT, Opções separadas por vírgula para campos de seleção)
- `camp_nb_ordem` (INT, Ordem de exibição)
- `camp_tx_obrigatorio` (ENUM: 'sim', 'nao')
- `camp_tx_placeholder` (VARCHAR)

### `documento_instancia`
*Representa um documento específico preenchido por um usuário.*
- `inst_nb_id` (INT, PK, AI)
- `inst_nb_tipo_doc` (INT, FK -> tipos_documentos)
- `inst_nb_user` (INT, FK -> user, Quem preencheu)
- `inst_dt_criacao` (DATETIME, Data de geração)
- `inst_tx_status` (ENUM: 'rascunho', 'finalizado')

### `documento_valor`
*Armazena os dados reais preenchidos para cada campo de uma instância.*
- `valo_nb_id` (INT, PK, AI)
- `valo_nb_instancia` (INT, FK -> documento_instancia)
- `valo_nb_campo` (INT, FK -> documento_campo)
- `valo_tx_valor` (TEXT, O conteúdo preenchido)

---

## 3. Fluxo de Uso

### A. Papel: Administrador (Configuração)
1. Acessa a tela de Cadastro de Tipo de Documento.
2. Seleciona um tipo e detalha o layout clicando em "Criar/Editar Layout".
3. Adiciona campos (ex: "Data da Troca", "Justificativa").
4. Define a ordem de exibição e se são obrigatórios.
5. Salva a configuração.

### B. Papel: Usuário (Operação)
1. Acessa o novo módulo de Documentos.
2. Seleciona o tipo de documento desejado (ex: "Troca de Horário").
3. O sistema carrega o formulário dinâmico baseado na configuração do Admin.
4. O usuário preenche os campos (campos como "De:" ou "Setor" podem vir auto-preenchidos).
5. Clica em "Visualizar Preview" ou "Gerar PDF".

---

## 4. Estrutura de Telas (UI/UX)

- **Tela de Configuração de Layout:** Interface limpa com lista de campos adicionados, permitindo arrastar para reordenar (Drag & Drop) e um botão "Adicionar Novo Campo".
- **Tela de Preenchimento:** Formulário vertical seguindo o padrão `linha_form` do contex20, com validações em tempo real para campos obrigatórios.
- **Preview:** Modal ou página simples que simula o layout final do PDF antes da exportação.

---

## 5. Regras de Negócio Implementadas

- **Auto-preenchimento:** Campos do tipo `usuario` buscarão automaticamente o nome do usuário logado na sessão (`$_SESSION['user_tx_nome']`).
- **Setor Dinâmico:** Campos do tipo `setor` buscarão o setor vinculado ao perfil do usuário no momento do preenchimento.
- **Obrigatoriedade:** O sistema impedirá a geração do PDF se campos marcados como `obrigatorio = 'sim'` estiverem vazios.

---

## 6. Estratégia de PDF

Utilizaremos a biblioteca **TCPDF** (já integrada ao projeto) por ser:
- Nativa em PHP (sem dependências externas de sistema).
- Estável e com suporte total a cabeçalhos e rodapés customizados.
- Capaz de gerar layouts precisos para impressões corporativas.

---

## 7. Próximos Passos (Evolução)

- **Assinatura Digital:** Integração com o módulo de assinatura já existente no sistema.
- **Workflow:** Enviar o documento gerado para aprovação de um gestor antes de liberar o PDF final.
- **Log de Alterações:** Rastreabilidade de quem alterou o layout do documento.
