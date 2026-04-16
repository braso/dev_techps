# Troca de Turno - Guia de Manutencao

## Visao geral
Este modulo implementa o fluxo completo de troca de horario entre colaboradores:

1. Solicitante abre solicitacao para outra matricula.
2. Sistema identifica gestores do solicitante e do colaborador informado.
3. Gestores aprovam ou reprovam na tela de gestao.
4. Se aprovado, sistema cria documento dinamico do tipo Troca de Horario.
5. Documento aparece automaticamente em documentos/cadastro_documento.php.
6. Aprovado tambem envia o PDF para assinatura ICP-Brasil (solicitante, destino e aprovador).

## Arquivos da pasta
- api_busca_matricula.php
  - Endpoint JSON para preencher Nome/Setor/Subsetor por matricula.
- solicitar_troca_turno.php
  - Tela de abertura da solicitacao e historico do usuario.
- gestao_troca_turno.php
  - Tela de decisao dos gestores (aprovar/reprovar).
- helpers_troca_turno.php
  - Regras de negocio, utilitarios de banco, notificacoes e geracao de documento.

## Como funciona a geracao automatica de documento
A geracao ocorre somente quando o gestor aprova a solicitacao.

Fluxo tecnico:
1. gestao_troca_turno.php chama tt_gerarDocumentoTrocaHorario.
2. helpers_troca_turno.php busca tipo ativo com nome Troca de Horario/Troca de Horario.
3. Busca campos ativos configurados em camp_documento_modulo para esse tipo.
4. Cria instancia em inst_documento_modulo.
5. Preenche valores em valo_documento_modulo com dados da solicitacao.
6. Atualiza a solicitacao com o ID da instancia criada (soli_nb_id_instancia).

## Regras importantes
- Reprovado nao gera documento.
- Se nao existir modelo ativo de Troca de Horario, nao gera documento.
- Se o modelo existir, mas sem campos ativos, nao gera documento.
- A aprovacao continua funcionando mesmo quando nao ha layout.
- O PDF usado para assinatura segue padrao visual (logo/cabecalho/rodape).
- O sistema prioriza um unico PDF por instancia (id_documento = INST_ID).

## Assinatura ICP-Brasil
- Apos aprovacao, o modulo envia automaticamente para assinatura digital.
- Signatarios: Solicitante, Trabalhara para, Aprovador.
- Sem hierarquia: todos com ordem 1.
- Grupo de envio: troca_turno_ID_SOLICITACAO (evita duplicidade).
- Operacao de assinatura via sistema (sem envio de e-mail neste fluxo).

## Onde configurar o layout do PDF
1. Acesse documentos/configurar_layout.php.
2. Selecione o tipo de documento Troca de Horario.
3. Cadastre/ordene campos dinamicos.
4. Opcional: configure logo/cabecalho no tipo.

## Onde visualizar o documento criado
1. Acesse documentos/cadastro_documento.php.
2. Localize a instancia criada.
3. Clique no icone de PDF (processar_pdf.php?id=ID).

## Campos tipicos mapeados automaticamente
O helper tenta resolver por label (normalizado):
- Emitido por -> usuario que criou a solicitacao
- Trabalhara para -> colaborador informado na solicitacao
- Aprovador por -> gestor que decidiu
- Datas/turnos/setor/subsetor/matricula/cpf/complemento -> dados da solicitacao

## Dicas de manutencao
- Se um campo do PDF vier vazio, revise o label configurado no layout.
- Evite labels muito genericos; prefira nomes claros no modelo.
- Em erros de fluxo, use o arquivo debug_log_trocadeturno.txt na raiz de armazem_paraiba.


## Tabelas que precisam existir para o modulo funcionar

## Tabela solicitacao_turno
```sql
CREATE TABLE solicitacao_turno (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    
    id_entidade_solicitante INT(11) NOT NULL,
    id_entidade_destino INT(11) NOT NULL,
    
    data_turno DATE NOT NULL,
    turno_atual VARCHAR(50) NULL,
    turno_desejado VARCHAR(50) NOT NULL,
    
    motivo TEXT NULL,
    
    status VARCHAR(20) DEFAULT 'pendente',
    
    id_gestor INT(11) NULL,
    justificativa_gestor TEXT NULL,
    
    data_solicitacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_decisao DATETIME NULL,

    -- Índices
    INDEX idx_solicitante (id_entidade_solicitante),
    INDEX idx_destino (id_entidade_destino),
    INDEX idx_status (status),
    INDEX idx_data (data_turno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```