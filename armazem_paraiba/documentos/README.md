# Modulo Documentos - Guia de Manutencao

## Visao geral
A pasta documentos concentra o modulo de documento dinamico do sistema.
Ele permite:

1. Configurar campos por tipo de documento.
2. Preencher documento com formulario dinamico.
3. Salvar instancia e valores no banco.
4. Gerar PDF a partir da instancia salva.

## Arquivos principais
- cadastro_documento.php
  - Tela principal de gestao das instancias de documento.
  - Acesso para novo documento, listagem e exclusao.
- configurar_layout.php
  - Configura campos do layout de um tipo de documento.
  - Permite editar ordem, tipo, obrigatoriedade, opcoes e logo.
- preencher_documento.php
  - Renderiza formulario dinamico conforme layout configurado.
  - Salva instancia em inst_documento_modulo e valores em valo_documento_modulo.
- processar_pdf.php
  - Carrega instancia e valores e monta o PDF final.
  - Usa a biblioteca TCPDF para renderizacao.
- setup_documentos.php
  - Inicializa estruturas de banco do modulo.
- reparar_banco.php
  - Script de reparo para bases antigas/inconsistentes.

## Banco de dados usado pelo modulo
- camp_documento_modulo: definicao dos campos por tipo.
- inst_documento_modulo: instancia de documento gerado.
- valo_documento_modulo: valor preenchido para cada campo da instancia.

## Tabelas que precisam existir para o modulo funcionar

O modulo depende das tabelas abaixo. Se o banco for novo, rode o SQL de criacao. Se a base ja existir, garanta tambem as colunas extras em `tipos_documentos`.

### 1) Ajustes em `tipos_documentos`

```sql
ALTER TABLE tipos_documentos
  ADD COLUMN IF NOT EXISTS tipo_tx_logo VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS tipo_tx_cabecalho TEXT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS tipo_tx_rodape TEXT DEFAULT NULL;

ALTER TABLE inst_documento_modulo
ADD COLUMN inst_nb_entidade INT(11) NULL AFTER inst_nb_user,
ADD COLUMN inst_tx_data_referencia DATE NULL AFTER inst_nb_entidade;

ALTER TABLE solicitacoes_ajuste
ADD COLUMN data_envio_documento DATETIME NULL AFTER data_visualizacao;
```

### 2) Tabela `camp_documento_modulo`

```sql
CREATE TABLE IF NOT EXISTS camp_documento_modulo (
  camp_nb_id INT(11) AUTO_INCREMENT PRIMARY KEY,
  camp_nb_tipo_doc INT(11) NOT NULL,
  camp_tx_label VARCHAR(255) NOT NULL,
  camp_tx_tipo ENUM('texto_curto', 'texto_longo', 'data', 'selecao', 'usuario', 'setor', 'number') NOT NULL,
  camp_tx_opcoes TEXT DEFAULT NULL,
  camp_nb_ordem INT(11) DEFAULT 0,
  camp_tx_obrigatorio ENUM('sim', 'nao') DEFAULT 'nao',
  camp_tx_placeholder VARCHAR(255) DEFAULT NULL,
  camp_tx_status ENUM('ativo', 'inativo') DEFAULT 'ativo',
  FOREIGN KEY (camp_nb_tipo_doc) REFERENCES tipos_documentos(tipo_nb_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### 3) Tabela `inst_documento_modulo`

```sql
CREATE TABLE IF NOT EXISTS inst_documento_modulo (
  inst_nb_id INT(11) AUTO_INCREMENT PRIMARY KEY,
  inst_nb_tipo_doc INT(11) NOT NULL,
  inst_nb_user INT(11) NOT NULL,
  inst_dt_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
  inst_tx_status ENUM('ativo', 'inativo') DEFAULT 'ativo',
  FOREIGN KEY (inst_nb_tipo_doc) REFERENCES tipos_documentos(tipo_nb_id) ON DELETE CASCADE,
  FOREIGN KEY (inst_nb_user) REFERENCES user(user_nb_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### 4) Tabela `valo_documento_modulo`

```sql
CREATE TABLE IF NOT EXISTS valo_documento_modulo (
  valo_nb_id INT(11) AUTO_INCREMENT PRIMARY KEY,
  valo_nb_instancia INT(11) NOT NULL,
  valo_nb_campo INT(11) NOT NULL,
  valo_tx_valor TEXT DEFAULT NULL,
  valo_tx_status ENUM('ativo', 'inativo') DEFAULT 'ativo',
  FOREIGN KEY (valo_nb_instancia) REFERENCES inst_documento_modulo(inst_nb_id) ON DELETE CASCADE,
  FOREIGN KEY (valo_nb_campo) REFERENCES camp_documento_modulo(camp_nb_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### 5) Observacao sobre base antiga

Existe uma checagem legada para `documento_campo` em `configurar_layout.php`, mas o fluxo atual do modulo usa `camp_documento_modulo`. Se sua base antiga ainda tiver `documento_campo`, trate isso como migracao de compatibilidade, nao como tabela principal do modulo.

## Fluxo operacional
1. Criar tipo de documento em cadastro de tipo.
2. Configurar campos em configurar_layout.php.
3. Gerar instancia em cadastro_documento.php -> Novo Documento.
4. Preencher campos em preencher_documento.php.
5. Visualizar PDF por processar_pdf.php?id=ID.

## Integracao com assinatura (PDF unico)
- Quando existe solicitacao em assinatura com id_documento = INST_ID, a listagem em cadastro_documento.php prioriza esse arquivo.
- Se nao houver arquivo no modulo assinatura, o sistema usa fallback para processar_pdf.php.
- Isso mantem um unico PDF de referencia por instancia, sem quebrar o fluxo antigo.
- Neste modulo, a assinatura e operada somente via sistema (lista de pendentes), sem envio de e-mail.

## Ajustes de ponto (gestao)
- O modulo de ajustes foi alinhado para usar aprovadores por cadastro de responsaveis (setor_responsavel/operacao_responsavel), no mesmo conceito do troca de turno.
- Aprovacao de ajuste dispara assinatura ICP-Brasil para 2 partes: solicitante e gestor aprovador.
- O fluxo existente de aprovacao/rejeicao continua igual; a assinatura foi adicionada como etapa complementar.
- A geracao do PDF depende de tipo com layout ativo em camp_documento_modulo (prioridade para Comunicacao Interna, com fallback para Solicitacao de Ajuste/Ajuste Ponto).

## Pergunta comum: quem gera o PDF?
O PDF e gerado pelo arquivo processar_pdf.php.

Detalhe tecnico:
- processar_pdf.php e o controlador do fluxo de geracao.
- TCPDF e a biblioteca que faz o render do documento.

Em resumo:
- processar_pdf.php decide o conteudo e chama a renderizacao.
- TCPDF desenha e entrega o PDF.

## Dicas de manutencao
- Alteracoes visuais de PDF: processar_pdf.php (HTML + classe MYPDF).
- Alteracoes de campos dinamicos: configurar_layout.php.
- Problemas de schema: setup_documentos.php e reparar_banco.php.
- Se um campo nao aparecer no PDF, valide:
  1. campo ativo no layout
  2. valor salvo em valo_documento_modulo
  3. instancia correta em inst_documento_modulo
