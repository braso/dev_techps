# Módulo de Saúde e Segurança (Gestão de EPIs)

Este módulo foi projetado para gerenciar o cadastro de Equipamentos de Proteção Individual (EPIs), a composição de kits de entrega rápida, o controle de movimentação de estoque (por Filial/Matriz) e o registro/impressão de Fichas de Entrega de EPI para colaboradores.

---

## 1. Arquitetura de Arquivos

O módulo está contido no diretório `armazem_paraiba/saude_seguranca/` e é composto pelos seguintes arquivos essenciais:

* **[conecta.php](file:///c:/Users/Ornilio%20Neto/Documents/Techps/dev_techps/armazem_paraiba/saude_seguranca/conecta.php)**: Script de inicialização que cria a estrutura das tabelas no banco de dados e executa migrações automáticas de novas colunas/configurações.
* **[funcoes_saude.php](file:///c:/Users/Ornilio%20Neto/Documents/Techps/dev_techps/armazem_paraiba/saude_seguranca/funcoes_saude.php)**: Contém funções compartilhadas de banco de dados, cálculos matemáticos (como cálculo de validade/vida útil) e regras de negócios comuns.
* **[cadastro_epi.php](file:///c:/Users/Ornilio%20Neto/Documents/Techps/dev_techps/armazem_paraiba/saude_seguranca/cadastro_epi.php)**: Interface administrativa e CRUD para cadastro de EPIs.
* **[estoque_epi.php](file:///c:/Users/Ornilio%20Neto/Documents/Techps/dev_techps/armazem_paraiba/saude_seguranca/estoque_epi.php)**: Painel de controle de inventário (entradas e saídas manuais) e gerenciador de **Kits de EPIs** (agrupamentos de múltiplos EPIs com quantidades pré-definidas).
* **[cadastro_colaborador.php](file:///c:/Users/Ornilio%20Neto/Documents/Techps/dev_techps/armazem_paraiba/saude_seguranca/cadastro_colaborador.php)**: Tela de associação que direciona o usuário rapidamente para as entregas registradas de determinado funcionário.
* **[entrega_epi.php](file:///c:/Users/Ornilio%20Neto/Documents/Techps/dev_techps/armazem_paraiba/saude_seguranca/entrega_epi.php)**: O core do módulo. Permite o lançamento em lote de entregas (com suporte a Kits), controle dinâmico de estoques locais e remotos, upload de fotos de recibos, assinaturas digitais, inativações justificadas com controle de estorno e geração da Ficha de EPI em PDF.

---

## 2. Estrutura do Banco de Dados

O módulo cria e gerencia as seguintes tabelas MySQL:

### `ss_epi` (Cadastro de Equipamentos)
* `ss_e_nb_id` (PK): Código de identificação do EPI.
* `ss_e_tx_grupo`: Grupo do EPI (ex: Calçado, Proteção Auditiva).
* `ss_e_tx_subgrupo`: Subgrupo (ex: Sapato de Segurança, Abafador de Ruídos).
* `ss_e_tx_item`: Nome do item.
* `ss_e_tx_ca`: Número do Certificado de Aprovação (CA) emitido pelo Ministério do Trabalho.
* `ss_e_nb_vida_util`: Tempo estimado de vida útil do equipamento em dias (usado para prever o vencimento).
* `ss_e_tx_cadastro_tipo`: Definido como `'estoque'` por padrão.
* `ss_e_tx_foto`: Caminho do arquivo de imagem do equipamento.
* `ss_e_tx_status`: Status (`'ativo'` ou `'inativo'`).

### `ss_epi_estoque` (Movimentações de Inventário)
* `ss_e_nb_id` (PK): Código da movimentação.
* `ss_e_nb_epi_id` (FK): Referência ao EPI cadastrado.
* `ss_e_nb_empresa_id` (FK): ID da empresa/filial proprietária do saldo. Nulo ou 0 equivale à Matriz.
* `ss_e_tx_tipo`: Tipo da movimentação (`'entrada'` ou `'saida'`).
* `ss_e_nb_quantidade`: Quantidade movimentada.
* `ss_e_tx_motivo`: Descrição do motivo (ex: Compra via NF, Entrega, Ajuste Manual).
* `ss_e_tx_data_recebimento`: Data da movimentação ou entrada física.
* `ss_e_tx_chave_nf`: Chave da Nota Fiscal de aquisição (quando aplicável).
* `ss_e_tx_fornecedor`: Nome do fornecedor emissor.

### `ss_epi_entrega` (Registro de Entregas)
* `ss_e_nb_id` (PK): Código do registro de entrega.
* `ss_e_nb_colaborador_id` (FK): Referência à tabela de colaboradores (`entidade`).
* `ss_e_nb_epi_id` (FK): Referência ao EPI entregue.
* `ss_e_nb_empresa_id` (FK): ID da filial em que a entrega foi faturada/realizada.
* `ss_e_tx_data_entrega`: Data em que o EPI foi entregue ao funcionário.
* `ss_e_nb_quantidade`: Quantidade entregue.
* `ss_e_tx_vencimento`: Data de vencimento prevista para o EPI (calculada a partir da vida útil).
* `ss_e_tx_status`: Status da entrega (`'ativo'` [Entregue], `'substituido'`, `'devolvido'`, `'perdido'`, `'nao_entregue'`, `'inativo'` [Excluído]).
* `ss_e_tx_assinatura`: Assinatura eletrônica baseada em vetor de imagem/coordenadas.
* `ss_e_tx_foto`: Caminhos das fotos anexas dos recibos/comprovantes.
* `ss_e_tx_observacao`: Observações adicionais descritas no momento da entrega.
* `ss_e_tx_justificativa_exclusao`: Justificativa obrigatória preenchida no caso de exclusão da entrega.

### `ss_kit` & `ss_kit_item` (Composição de Kits)
* `ss_kit`: Código e nome do kit (ex: "Kit Motorista").
* `ss_kit_item`: Tabela pivô contendo o ID do kit, o ID do EPI vinculado e a quantidade padrão recomendada para entrega daquele item no lote.

---

## 3. Principais Funções e Helpers

### `funcoes_saude.php`

#### `obterSaldoEstoque(int $idEpi, ?int $empresaId = null, bool $conferirTodasFiliais = false): int`
* **Finalidade**: Retorna o saldo disponível de determinado EPI.
* **Comportamento**: Se a consulta for feita informando a ID da Matriz, o banco consolida os registros cuja coluna `empresa_id` seja o ID da Matriz, `0` ou `NULL`, garantindo retrocompatibilidade. Se `$conferirTodasFiliais` for verdadeiro, calcula a soma agregada em toda a rede.

#### `registrarMovimentacaoEstoque(int $idEpi, int $qtd, string $tipo, string $motivo, ... ?int $empresaId = null): bool`
* **Finalidade**: Insere um registro de movimentação de entrada/saída na tabela `ss_epi_estoque` para atualizar os saldos.

#### `calcularVencimentoEpi(string $dataEntrega, int $vidaUtilDias): string`
* **Finalidade**: Calcula e retorna a data exata de vencimento estimada somando os dias de vida útil à data de entrega original.

### `entrega_epi.php`

#### `obterSaldosEpiFiliaisAjax()`
* **Finalidade**: Endpoint chamado dinamicamente (via AJAX/GET) que retorna os saldos de um EPI agrupados por filiais.
* **Diferencial**: Consolida saldos de registros órfãos ou legados (`0` ou `NULL`) com a ID ativa da Matriz, retornando uma lista limpa para comparação e seleção no frontend.

#### `cadastrarEntregaLoteAjax()`
* **Finalidade**: Endpoint chamado (via AJAX/POST) para salvar a sacola de entregas gerada no frontend. Executa transações seguras de baixa no estoque para cada item adicionado.

#### `excluirEntrega()`
* **Finalidade**: Processa a inativação de uma entrega física registrada.
* **Comportamento**: Altera o status da entrega para `'inativo'` no banco de dados e registra a justificativa de cancelamento informada pelo operador. Se o operador optar por estornar o produto no checkbox, ele gera automaticamente uma movimentação de `'entrada'` para devolver os itens ao saldo disponível da filial.

---

## 4. Regras de Negócio Importantes (Gotchas & Constraints)

1. **Exclusão de Cargos Administrativos (Diretores)**:
   * Colaboradores que ocupam cargos cujo nome contenha "Diretor" são removidos dos dropdowns e autocompletes de seleção de funcionários para entrega de EPI.
2. **Ocultação de EPIs Esgotados**:
   * O dropdown de seleção de EPIs só apresenta equipamentos que possuam saldo maior que zero na filial selecionada ou em alguma outra filial associada. EPIs completamente sem saldo em toda a rede são ocultados automaticamente da lista.
3. **Identificação Visual de Estoques Externos**:
   * No dropdown de seleção de EPIs, se um item possuir saldo de estoque zerado na filial de entrega selecionada mas possuir estoque em outra filial, a opção é exibida estilizada em **cor laranja (#d97706), itálico e negrito**, indicando que está disponível em outra unidade.
4. **Importação e Transferência no Lançamento**:
   * No grid de lançamentos (sacola), se o usuário adicionar um item sem estoque na filial atual mas com saldo em outra, o sistema exibe um alerta de estoque insuficiente na cor vermelha e oferece um link de ação: *"Importar de [Nome da Filial] (Saldo: X)"*. Clicar no link define o campo `import_de` com o ID da filial externa, efetuando o débito da movimentação no local correto ao gravar.
5. **Filtro de Exclusão Física no Grid**:
   * Registros com status `'inativo'` (excluídos) são filtrados diretamente no SQL da query do grid (`ss_e_tx_status <> 'inativo'`), garantindo que nunca mais apareçam em relatórios ou buscas gerais, independentemente dos filtros de busca.
