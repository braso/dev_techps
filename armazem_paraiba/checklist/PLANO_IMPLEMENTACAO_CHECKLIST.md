# Plano de Implementação — Módulo Checklist (TechPS)

> **Repositório-alvo:** `C:\Users\Ornilio Neto\Documents\Techps\dev_techps`
> **Módulo novo:** `armazem_paraiba/checklist/`
> **Referência analisada:** projeto DSS (`https://github.com/transp-admir/dss/tree/main/app`)
> **Data:** 12/11/2026
> **Versão do plano:** 1.0

---

## 0. Como usar este plano

1. Leia o documento inteiro antes de alterar código.
2. Implemente uma fase por vez, na ordem da seção 10.
3. Não altere módulos fora de `armazem_paraiba/checklist/`, exceto os pontos de integração listados na seção 8.
4. Ao final de cada fase, execute os testes da seção 11 e valide os critérios de aceite.
5. Se faltar uma decisão de negócio, pare e pergunte; não invente regra.

---

## 1. Objetivo

Criar o módulo de **Checklist** no TechPS com:

- cadastro de modelos de checklist por empresa/unidade;
- itens e subitens ordenados, com setor responsável;
- bloco especial de controle de extintores;
- área do motorista para responder o checklist;
- assinatura digital do motorista e do responsável;
- controle de pendências geradas por “Não Conforme”;
- acompanhamento diário/mensal por veículo;
- indisponibilidade de veículo e isenção de motorista;
- relatórios consolidados, status diário e PDF;
- permissões por perfil e isolamento por tenant.

O DSS é a **referência funcional**. O TechPS é PHP procedural + MySQL; portanto, o código será reimplementado, não copiado.

---

## 2. Escopo

### 2.1 Entra no MVP

- Modelos de checklist: criar, editar, ativar/desativar.
- Itens principais e subitens, com ordem e setor responsável.
- Bloco de extintores (5 posições fixas).
- Lista de checklists do motorista com status do período.
- Preenchimento com Conforme / Não Conforme / N/A + observação.
- Assinatura do motorista (obrigatória) e do responsável (opcional).
- Registro de tempo de preenchimento.
- Uma resposta por período/veículo/checklist/motorista.
- Geração automática de pendência para “Não Conforme”.
- Fechamento automático de pendência quando o item volta a “Conforme”.
- Acompanhamento por veículo com status diário/mensal.
- Indisponibilidade de veículo.
- Isenção de motorista por período.
- Tela de pendências com filtros, resolução, O.S. e observação.
- Relatório consolidado e relatório de status diário.
- Exportação PDF.
- Permissões, menu e auditoria.

### 2.2 Não entra agora

- CRM, funil ou cadastro de leads.
- Assinatura de conteúdos/treinamentos (isso é do módulo de treinamento).
- Gestão de documentos fixos do DSS.
- Aplicativo mobile nativo; a tela será responsiva.
- Integração obrigatória com sistema de manutenção; será opcional via webhook.
- Importação automática de todo o histórico do DSS (pode ser feita em script separado).

---

## 3. Inventário do Checklist no DSS

### 3.1 Modelos de dados encontrados

| Modelo DSS | Finalidade |
|---|---|
| `Checklist` | Modelo: título, código, revisão, data, tipo, unidade, ativo. |
| `ChecklistItem` | Item ou subitem; ordem textual; setor responsável; suporta o bloco `__BLOCO_EXTINTORES__`. |
| `ChecklistPreenchido` | Resposta de um motorista para um checklist/veículo em um período. Guarda assinaturas, observações gerais e tempo. |
| `ChecklistResposta` | Resposta por item: CONFORME, NAO CONFORME ou N/A; observação. |
| `ExtintorCheck` | Dados de até 5 extintores por preenchimento. |
| `Pendencia` | Pendência aberta por resposta “Não Conforme”; status, O.S. e observação administrativa. |
| `VeiculoIndisponibilidade` | Período em que o veículo não pode ser cobrado. |
| `MotoristaIsencao` | Isenção do motorista por data e tipo de checklist. |
| `UnidadeConfig` | Permissão de troca de veículo pelo motorista. |

### 3.2 Funcionalidades do DSS

**Gestão de modelos**

- Criar checklist com título, código, revisão, data, tipo e unidade.
- Listar modelos ativos/inativos.
- Exibir itens do checklist e permitir adicionar categorias e subitens.
- Editar item/subitem em modal.
- Excluir item/subitem com confirmação.
- Adicionar bloco especial “Controle de Extintores”.
- Ativar/desativar modelo; se nunca foi preenchido, desativar exclui permanentemente; se já foi preenchido, mantém histórico.
- Ordenação natural de ordem (`1`, `2`, `1.1`, `1.2`).

**Área do motorista**

- Listar checklists ativos da unidade do motorista.
- Mostrar status: pendente, preenchido hoje, preenchido neste mês.
- Impedir preenchimento sem veículo vinculado.
- Permitir troca de veículo conforme configuração da unidade:
  - `NENHUMA`;
  - `UNIDADE`;
  - `OPERACAO`.
- Preencher itens e subitens com três respostas.
- Observação apenas para “Não Conforme”.
- Preencher bloco de extintores.
- Informar pendências gerais.
- Ler termo de ciência e responsabilidade.
- Assinar com canvas (assinatura do motorista obrigatória; responsável opcional).
- Exibir modo somente leitura depois de preenchido no período.
- Medir tempo de preenchimento.

**Pendências**

- Cada resposta “Não Conforme” gera uma pendência por item/veículo, se ainda não houver uma aberta.
- Resposta “Conforme” fecha automaticamente a pendência aberta do mesmo item/veículo.
- Tela administrativa agrupa pendências por veículo.
- Filtros por veículo e tipo (checklist/serviço).
- Resolver com status (`RESOLVIDO`, `FINALIZADO`), número de O.S. e observação.
- Permissão por unidade e setor do usuário.
- API externa para finalizar pendência por `id_checklist` (resposta).

**Acompanhamento e exceções**

- Dashboard diário por veículo com status diário e mensal.
- Status: Preenchido, Pendente, Isento, Indisponível, N/A.
- Mostrar motorista, horário, quantidade de registros e tempo gasto.
- Resumo lateral de pendentes diários e mensais.
- Registrar indisponibilidade de veículo com período, motivo e tipo afetado.
- Registrar isenção de motorista por período, individual ou todos, com motivo e tipo.

**Relatórios**

- Relatório consolidado por tipo, veículo e período.
- Agrupamento por veículo e data.
- Relatório de status diário.
- Geração de PDF.

### 3.3 Arquivos de referência no DSS

- `app/models.py` — modelos de checklist.
- `app/routes.py` — rotas de modelo, itens, preenchimento, acompanhamento, pendências, isenções e relatórios.
- `app/templates/checklists.html` — gestão de modelos.
- `app/templates/checklist_detalhe.html` — itens/subitens.
- `app/templates/motorista_lista_checklists.html` — lista do motorista.
- `app/templates/motorista_preencher_checklist.html` — preenchimento.
- `app/templates/admin_pendencias.html` — pendências.
- `app/templates/admin_acompanhamento_diario.html` — acompanhamento.
- `app/templates/admin_relatorios_consolidados.html` — relatório consolidado.
- `app/templates/admin_relatorio_status_diario.html` — status diário.
- `app/static/js/checklist.js` — assinatura, validação e envio.
- `app/static/css/checklist.css` — estilos do checklist.

---

## 4. Arquitetura no TechPS

### 4.1 Estrutura de arquivos

```
armazem_paraiba/checklist/
  bootstrap.php              # sessão, conexão, permissão, migrações e helpers
  migracoes.php              # tabelas, índices e menu
  index.php                  # dashboard/lista de modelos
  modelo.php                 # criar/editar modelo
  modelo_itens.php           # gerenciar itens e subitens
  meus_checklists.php        # lista do motorista
  preencher.php              # preenchimento/visualização
  acompanhamento.php         # status diário/mensal por veículo
  pendencias.php             # pendências
  relatorios.php             # relatório consolidado/status diário
  pdf.php                    # geração de PDF
  configuracoes.php          # troca de veículo, webhook e retenção
  api.php                    # AJAX autenticado
  assets/
    checklist.css
    checklist.js
    assinatura.js
  lib/
    checklist.php            # helpers de modelo/item
    preenchimento.php        # período, respostas e validações
    pendencias.php           # abertura/fechamento de pendências
    relatorios.php           # consultas de relatório
    seguranca.php            # CSRF, escape, permissões
```

### 4.2 Mapeamento DSS → TechPS

| DSS | TechPS |
|---|---|
| `Motorista` | `entidade` + `user` vinculado (`user_nb_entidade`). |
| `Veiculo` / `nome_conjunto` | `placa` (`plac_nb_id`, `plac_tx_placa`, `plac_tx_modelo`). |
| `Unidade` | `empresa` (`empr_nb_id`). Decisão pendente: usar empresa ou setor. |
| `Usuario` administrativo | `user` + `perfil_acesso`. |
| `setor_responsavel` | nome do setor em `grupos_documentos.grup_tx_nome`. |
| `operacao` | `operacao.oper_tx_nome`, via `entidade.enti_tx_tipoOperacao`. |
| `UnidadeConfig` | nova `checklist_config` por empresa. |
| `Pendencia` | nova `checklist_pendencia`. |
| `VeiculoIndisponibilidade` | nova `checklist_indisponibilidade`. |
| `MotoristaIsencao` | nova `checklist_isencao`. |
| `ChecklistPreenchido` | nova `checklist_preenchido`. |
| `ChecklistResposta` | nova `checklist_resposta`. |
| `ExtintorCheck` | nova `checklist_extintor`. |

### 4.3 Regra de período

O TechPS deve calcular uma **data de referência** para garantir uma resposta por período:

| Tipo | Data de referência |
|---|---|
| DIÁRIO | data do preenchimento |
| SEMANAL | segunda-feira da semana |
| MENSAL | primeiro dia do mês |
| OUTRO | data do preenchimento (ou data manual, se definido) |

A unicidade será:

```
(checklist_id, placa_id, entidade_id, data_referencia)
```

Assim, o mesmo motorista não preenche duas vezes o mesmo checklist no mesmo período/veículo.

### 4.4 Fluxo do motorista

1. Usuário logado no TechPS acessa **Checklist → Meus Checklists**.
2. Sistema localiza a entidade do usuário e o veículo vinculado em `placa`.
3. Lista modelos ativos da empresa do usuário (e globais, se adotado).
4. Para cada modelo, calcula o status do período.
5. Ao abrir:
   - se já preenchido, exibe somente leitura;
   - se pendente, exibe formulário.
6. Ao salvar:
   - grava preenchimento, respostas, extintores e assinaturas;
   - abre/fecha pendências;
   - dispara webhook opcional de manutenção;
   - registra log;
   - volta para a lista.

---

## 5. Banco de dados

Criar em `checklist/migracoes.php` com `CREATE TABLE IF NOT EXISTS`. Engine `InnoDB`, charset `utf8mb4`.

### 5.1 `checklist`

```sql
CREATE TABLE IF NOT EXISTS checklist (
  chec_nb_id INT AUTO_INCREMENT PRIMARY KEY,
  chec_tx_titulo VARCHAR(200) NOT NULL,
  chec_tx_codigo VARCHAR(50) NOT NULL,
  chec_tx_revisao VARCHAR(20) NOT NULL,
  chec_dt_modelo DATE NOT NULL,
  chec_tx_tipo ENUM('DIARIO','SEMANAL','MENSAL','OUTRO') NOT NULL DEFAULT 'DIARIO',
  chec_nb_empresa INT NULL,
  chec_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  chec_nb_userCadastro INT NULL,
  chec_dt_dataCadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
  chec_dt_dataAtualiza DATETIME NULL,
  KEY idx_empresa_status (chec_nb_empresa, chec_tx_status),
  KEY idx_codigo (chec_tx_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5.2 `checklist_item`

```sql
CREATE TABLE IF NOT EXISTS checklist_item (
  chit_nb_id INT AUTO_INCREMENT PRIMARY KEY,
  chit_nb_checklist INT NOT NULL,
  chit_nb_parent INT NULL,
  chit_tx_texto TEXT NOT NULL,
  chit_tx_ordem VARCHAR(10) NULL,
  chit_tx_setorResponsavel VARCHAR(150) NULL,
  chit_tx_especial VARCHAR(30) NULL,
  chit_dt_dataCadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_checklist (chit_nb_checklist),
  KEY idx_parent (chit_nb_parent)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

`chit_tx_especial = 'extintores'` identifica o bloco especial. O texto pode ser `__BLOCO_EXTINTORES__` para compatibilidade com o DSS.

### 5.3 `checklist_preenchido`

```sql
CREATE TABLE IF NOT EXISTS checklist_preenchido (
  chpr_nb_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  chpr_nb_checklist INT NOT NULL,
  chpr_nb_placa INT NOT NULL,
  chpr_nb_entidade INT NOT NULL,
  chpr_nb_empresa INT NULL,
  chpr_dt_referencia DATE NOT NULL,
  chpr_dt_preenchimento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  chpr_tx_assinaturaMotorista LONGTEXT NULL,
  chpr_tx_assinaturaResponsavel LONGTEXT NULL,
  chpr_tx_outrosProblemas TEXT NULL,
  chpr_tx_solucoesAdotadas TEXT NULL,
  chpr_tx_pendenciasGerais TEXT NULL,
  chpr_nb_tempoPreenchimento INT NULL,
  chpr_nb_user INT NULL,
  UNIQUE KEY uk_periodo (chpr_nb_checklist, chpr_nb_placa, chpr_nb_entidade, chpr_dt_referencia),
  KEY idx_placa_data (chpr_nb_placa, chpr_dt_referencia),
  KEY idx_entidade_data (chpr_nb_entidade, chpr_dt_referencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5.4 `checklist_resposta`

```sql
CREATE TABLE IF NOT EXISTS checklist_resposta (
  chre_nb_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  chre_nb_preenchimento BIGINT NOT NULL,
  chre_nb_item INT NOT NULL,
  chre_tx_resposta ENUM('CONFORME','NAO CONFORME','N/A') NOT NULL,
  chre_tx_observacao TEXT NULL,
  UNIQUE KEY uk_item (chre_nb_preenchimento, chre_nb_item),
  KEY idx_preenchimento (chre_nb_preenchimento),
  KEY idx_item (chre_nb_item)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5.5 `checklist_extintor`

```sql
CREATE TABLE IF NOT EXISTS checklist_extintor (
  chex_nb_id INT AUTO_INCREMENT PRIMARY KEY,
  chex_nb_preenchimento BIGINT NOT NULL,
  chex_tx_local VARCHAR(100) NULL,
  chex_tx_tipo VARCHAR(50) NULL,
  chex_tx_peso VARCHAR(20) NULL,
  chex_dt_vencimento DATE NULL,
  chex_tx_trocado ENUM('Sim','Nao') NULL,
  chex_tx_motivoTroca TEXT NULL,
  KEY idx_preenchimento (chex_nb_preenchimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5.6 `checklist_pendencia`

```sql
CREATE TABLE IF NOT EXISTS checklist_pendencia (
  chpe_nb_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  chpe_nb_item INT NOT NULL,
  chpe_nb_placa INT NOT NULL,
  chpe_nb_respostaAbertura BIGINT NOT NULL,
  chpe_tx_status ENUM('PENDENTE','RESOLVIDO','FINALIZADO') NOT NULL DEFAULT 'PENDENTE',
  chpe_tx_numeroOS VARCHAR(100) NULL,
  chpe_tx_observacaoAdmin TEXT NULL,
  chpe_dt_dataCriacao DATETIME DEFAULT CURRENT_TIMESTAMP,
  chpe_dt_dataResolucao DATETIME NULL,
  KEY idx_placa_status (chpe_nb_placa, chpe_tx_status),
  KEY idx_item_status (chpe_nb_item, chpe_tx_status),
  KEY idx_resposta (chpe_nb_respostaAbertura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5.7 `checklist_indisponibilidade`

```sql
CREATE TABLE IF NOT EXISTS checklist_indisponibilidade (
  chin_nb_id INT AUTO_INCREMENT PRIMARY KEY,
  chin_nb_placa INT NOT NULL,
  chin_dt_inicio DATE NOT NULL,
  chin_dt_fim DATE NULL,
  chin_tx_motivo TEXT NOT NULL,
  chin_tx_tipoChecklist ENUM('DIARIO','SEMANAL','MENSAL','OUTRO') NULL,
  chin_nb_user INT NULL,
  chin_dt_dataCadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_placa_periodo (chin_nb_placa, chin_dt_inicio, chin_dt_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5.8 `checklist_isencao`

```sql
CREATE TABLE IF NOT EXISTS checklist_isencao (
  chis_nb_id INT AUTO_INCREMENT PRIMARY KEY,
  chis_nb_entidade INT NOT NULL,
  chis_dt_data DATE NOT NULL,
  chis_tx_motivo VARCHAR(255) NOT NULL,
  chis_tx_tipoChecklist ENUM('DIARIO','SEMANAL','MENSAL','OUTRO') NOT NULL,
  chis_nb_user INT NULL,
  chis_dt_dataCadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_data_entidade_tipo (chis_dt_data, chis_nb_entidade, chis_tx_tipoChecklist),
  KEY idx_entidade_data (chis_nb_entidade, chis_dt_data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5.9 `checklist_config`

```sql
CREATE TABLE IF NOT EXISTS checklist_config (
  chco_nb_id INT AUTO_INCREMENT PRIMARY KEY,
  chco_nb_empresa INT NULL,
  chco_tx_trocaVeiculo ENUM('NENHUMA','EMPRESA','OPERACAO') NOT NULL DEFAULT 'NENHUMA',
  chco_tx_webhookUrl VARCHAR(500) NULL,
  chco_tx_webhookKey VARCHAR(255) NULL,
  chco_nb_userAtualiza INT NULL,
  chco_dt_dataAtualiza DATETIME NULL,
  UNIQUE KEY uk_empresa (chco_nb_empresa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5.10 Menu e permissões

A migração deve inserir os itens em `menu_item` e conceder aos perfis administrativos, seguindo o padrão do módulo WhatsApp:

- `/checklist/index.php` — Modelos de Checklist
- `/checklist/meus_checklists.php` — Meus Checklists
- `/checklist/acompanhamento.php` — Acompanhamento
- `/checklist/pendencias.php` — Pendências
- `/checklist/relatorios.php` — Relatórios
- `/checklist/configuracoes.php` — Configurações

Adicionar a seção `checklist` em `menu_estrutura.php`, no `$ordem` e na lista de seções de `cadastro_perfil_acesso.php`.

---

## 6. Telas e funcionalidades

### 6.1 Modelos de checklist — `index.php` e `modelo.php`

- Listar modelos com status, título, código, tipo, empresa/unidade, quantidade de itens.
- Criar modelo com:
  - título, código, revisão, data do modelo;
  - tipo (`DIARIO`, `SEMANAL`, `MENSAL`, `OUTRO`);
  - empresa/unidade (vazio = global, se adotado);
  - status ativo/inativo.
- Editar modelo.
- Ativar/desativar:
  - se nunca preenchido, desativar pode excluir permanentemente;
  - se já preenchido, apenas marcar inativo.
- Ações rápidas para gerenciar itens.

### 6.2 Itens e subitens — `modelo_itens.php`

- Adicionar categoria principal.
- Adicionar subitem com:
  - texto;
  - ordem textual (`1`, `1.1`, `1.2`);
  - setor responsável.
- Ordenação natural.
- Editar item/subitem em modal via AJAX.
- Excluir item/subitem com confirmação; exclusão em cascata dos subitens.
- Adicionar bloco especial “Controle de Extintores” (`__BLOCO_EXTINTORES__` / `chit_tx_especial='extintores'`).
- Setores carregados de `grupos_documentos` ou da origem definida na decisão pendente.

### 6.3 Meus Checklists — `meus_checklists.php`

- Acesso por usuário logado com entidade vinculada.
- Identificar motorista = entidade do usuário.
- Identificar veículo(s) em `placa` com `plac_nb_entidade = entidade`.
- Se não houver veículo:
  - exibir mensagem;
  - permitir troca/vínculo apenas se a configuração permitir.
- Listar checklists ativos da empresa do motorista.
- Calcular status por período:
  - `Pendente`;
  - `Preenchido hoje`;
  - `Preenchido este mês`;
  - `Preenchido nesta semana`;
  - `Indisponível`/`Isento` quando aplicável.
- Botão **Preencher** ou **Visualizar**.
- Modal de troca de veículo, se permitido:
  - `NENHUMA`: não mostra;
  - `EMPRESA`: apenas veículos da mesma empresa;
  - `OPERACAO`: apenas veículos da mesma operação/cargo.
- Ao trocar, desvincular o veículo anterior se necessário e registrar log.

### 6.4 Preenchimento — `preencher.php`

- Cabeçalho com código, tipo, revisão, data do modelo e veículo.
- Se já preenchido no período: modo somente leitura.
- Blocos:
  1. Itens principais com subitens.
  2. Para cada item/subitem: Conforme / Não Conforme / N/A.
  3. Observação opcional; persistir somente se Não Conforme.
  4. Bloco de extintores, se existir:
     - CABINE;
     - 1° CARRETA L.D;
     - 1° CARRETA L.E;
     - 2° CARRETA L.D;
     - 2° CARRETA L.E;
     - tipo, peso, vencimento, trocado, motivo.
  5. Pendências gerais.
  6. Termo de ciência e responsabilidade.
  7. Assinatura do motorista (obrigatória) e do responsável (opcional), via canvas.
- Validações:
  - todos os itens respondidos;
  - peso e vencimento dos extintores preenchidos, salvo “N/A”;
  - assinatura do motorista;
  - impedir envio duplicado;
  - bloquear duplicidade por período.
- Ao salvar:
  - criar `checklist_preenchido`;
  - criar `checklist_resposta`;
  - criar `checklist_extintor`;
  - abrir pendências para Não Conforme;
  - fechar pendências abertas para itens Conforme;
  - opcionalmente chamar webhook de manutenção;
  - registrar log;
  - exibir mensagem e voltar.

### 6.5 Acompanhamento — `acompanhamento.php`

- Selecionar data (padrão hoje).
- Localizar checklist ativo diário e mensal da empresa (o mais recente, se houver mais de um).
- Listar veículos ativos e veículos inativos com registro no período.
- Para cada veículo, exibir:
  - status diário;
  - status mensal;
  - motorista vinculado;
  - horário, quantidade de registros e tempo gasto;
  - pendências abertas.
- Status:
  - **Preenchido**;
  - **Pendente**;
  - **Isento**;
  - **Indisponível**;
  - **N/A**.
- Resumos laterais de pendentes diários e mensais.
- Botão **Declarar Veículo Indisponível**:
  - veículo, início, fim opcional, motivo, tipo de checklist afetado.
- Botão **Liberar Motorista(s)**:
  - motorista individual ou todos;
  - período;
  - tipo de checklist;
  - motivo.
- Filtro por empresa/unidade para não administradores.

### 6.6 Pendências — `pendencias.php`

- Listar pendências abertas agrupadas por veículo.
- Filtros por veículo e tipo.
- Colunas: data, tipo, descrição, observação, reportado por.
- Resolver pendência:
  - status `RESOLVIDO` ou `FINALIZADO`;
  - número de O.S.;
  - observação administrativa;
  - data de resolução.
- Permissões:
  - admin: tudo;
  - não admin: somente empresa/unidade e setor responsável.
- Endpoint `api.php?acao=finalizar_pendencia` para integração externa:
  - recebe `id_checklist` (ID da resposta), `status`, `numero_os`, `observacao`;
  - localiza a pendência por `chpe_nb_respostaAbertura`;
  - responde JSON idempotente.

### 6.7 Relatórios — `relatorios.php` e `pdf.php`

- Relatório consolidado:
  - filtros por tipo, veículo e período;
  - agrupamento por veículo e data;
  - exibe preenchimentos, respostas e pendências;
  - exportação PDF.
- Relatório de status diário:
  - status por veículo;
  - pendências;
  - exportação PDF.
- Relatório de preenchimentos:
  - lista de preenchidos com filtros;
  - visualização detalhada;
  - exportação.
- Usar `tcpdf` já existente no TechPS ou HTML de impressão, se preferir.

### 6.8 Configurações — `configuracoes.php`

- Por empresa:
  - permissão de troca de veículo (`NENHUMA`, `EMPRESA`, `OPERACAO`);
  - URL e chave do webhook de manutenção (opcional).
- Logs de ações do módulo em `log_sistema` com `registrar_log('checklist', ...)`.

---

## 7. Regras de negócio

1. **Um preenchimento por período/veículo/checklist/motorista.**
2. **Checklist inativo não aparece para o motorista.**
3. **Modelo sem itens não aparece para o motorista.**
4. **Checklist global** (empresa nula) aparece para todas as empresas, se adotado.
5. **Não Conforme** abre pendência se não existir uma aberta para o mesmo item/veículo.
6. **Conforme** fecha automaticamente a pendência aberta do mesmo item/veículo.
7. **Observação** só é persistida quando a resposta é Não Conforme.
8. **Assinatura do motorista** é obrigatória; a do responsável é opcional.
9. **Extintores** aceitam “N/A” em peso/vencimento quando não aplicável.
10. **Troca de veículo** respeita a configuração da empresa/operação.
11. **Unidade/empresa** isola os dados; não aceitar empresa via request para burlar.
12. **Setor responsável** filtra pendências de usuários não administradores.
13. **Desativar modelo nunca preenchido** pode excluir; com histórico, apenas inativar.
14. **O.S.** é texto livre; não há integração obrigatória com manutenção.
15. **Tempo de preenchimento** é calculado do início da tela até o envio, com proteção contra valores negativos.

---

## 8. Permissões, menu e ativação

### 8.1 Menu

Adicionar em `menu_estrutura.php`:

```php
"checklist" => [
    "/checklist/index.php"          => "Modelos",
    "/checklist/meus_checklists.php"=> "Meus Checklists",
    "/checklist/acompanhamento.php" => "Acompanhamento",
    "/checklist/pendencias.php"     => "Pendências",
    "/checklist/relatorios.php"     => "Relatórios",
    "/checklist/configuracoes.php"  => "Configurações",
],
```

Adicionar `"checklist"` em `$ordem` e em `$secs` de `cadastro_perfil_acesso.php`.

### 8.2 Permissões no código

- `checklist/index.php` → `/checklist/index.php`
- `checklist/modelo_itens.php` → `/checklist/index.php`
- `checklist/meus_checklists.php` → `/checklist/meus_checklists.php`
- `checklist/preencher.php` → `/checklist/meus_checklists.php`
- `checklist/acompanhamento.php` → `/checklist/acompanhamento.php`
- `checklist/pendencias.php` → `/checklist/pendencias.php`
- `checklist/relatorios.php` → `/checklist/relatorios.php`
- `checklist/configuracoes.php` → `/checklist/configuracoes.php`
- `api.php` → validar a permissão da ação.

### 8.3 Ativação por tenant

Recomendação: usar `CHECKLIST_ENABLED=1` no `.env` do tenant, no mesmo padrão do `WHATSAPP_ENABLED`. Sem a flag, a seção não aparece. Adicionar ao `.env-example`.

---

## 9. Segurança e LGPD

- CSRF em todos os POSTs autenticados.
- Prepared statements em todas as consultas.
- Escape de saída em textos, nomes, observações e assinaturas.
- Validar que o item pertence ao checklist e que o preenchimento pertence ao motorista/empresa.
- Validar permissão em cada ação do `api.php`.
- Limitar tamanho da assinatura (ex.: 1 MB) e validar `data:image/png;base64`.
- Não expor assinaturas em listagens; apenas na visualização autorizada.
- Registrar ações sensíveis em `log_sistema`.
- Isolamento por tenant já é garantido pelo banco; reforçar no código.
- LGPD: consentimento/termo de ciência, retenção configurável, acesso restrito, exclusão sob solicitação.
- Evitar envio de dados pessoais para webhooks sem necessidade.

---

## 10. Fases e estimativas

| Fase | Entrega | Mín. (dias) | Máx. (dias) | Média |
|---|---|---|---|---|
| Fase 0 | Fundação: estrutura, migrações, menu, permissões, config e helpers | 2 | 3 | 2,5 |
| Fase 1 | Modelos e itens: CRUD, subitens, setor, bloco de extintores, ativar/desativar | 4 | 5 | 4,5 |
| Fase 2 | Motorista: vínculo/troca de veículo, lista, preenchimento, assinaturas e validações | 5 | 7 | 6,0 |
| Fase 3 | Acompanhamento, indisponibilidade, isenções, pendências e fechamento automático | 4 | 6 | 5,0 |
| Fase 4 | Relatórios consolidado/diário, PDF e API de finalização | 3 | 4 | 3,5 |
| Fase 5 | Refinamentos, testes, documentação, importação opcional do DSS | 2 | 3 | 2,5 |
| **Total** | | **20** | **28** | **24,0** |

> **Estimativa com DEV IA:** 6–10 dias úteis (média 8), considerando desenvolvimento assistido por IA.

### 10.1 Fase 0 — Fundação

- [ ] Criar `armazem_paraiba/checklist/`.
- [ ] `bootstrap.php`, `migracoes.php`, `lib/seguranca.php`.
- [ ] Criar tabelas e índices.
- [ ] Menu, permissões e `.env-example`.
- [ ] Helpers de período e status.

**Aceite:** migração roda, menu aparece conforme permissão, modelos vazios são listados.

### 10.2 Fase 1 — Modelos e itens

- [ ] CRUD de modelos.
- [ ] Itens/subitens, ordem natural, setor responsável.
- [ ] Bloco de extintores.
- [ ] Ativar/desativar e exclusão condicionada.
- [ ] Modal de edição de item.

**Aceite:** criar modelo com categorias/subitens e bloco de extintores; ativar/desativar.

### 10.3 Fase 2 — Motorista e preenchimento

- [ ] `meus_checklists.php`.
- [ ] Vínculo/troca de veículo.
- [ ] `preencher.php` com respostas, extintores, observações, termo.
- [ ] Assinaturas (motorista/responsável).
- [ ] Validações e gravação do tempo.
- [ ] Modo somente leitura.

**Aceite:** motorista preenche uma vez por período; assinatura obrigatória; duplicidade bloqueada.

### 10.4 Fase 3 — Acompanhamento e pendências

- [ ] `acompanhamento.php` com status diário/mensal.
- [ ] Indisponibilidade de veículo.
- [ ] Isenção de motorista.
- [ ] `pendencias.php`.
- [ ] Abertura/fechamento automático.
- [ ] API de finalização.

**Aceite:** “Não Conforme” abre pendência; “Conforme” fecha; acompanhamento mostra status corretos.

### 10.5 Fase 4 — Relatórios e PDF

- [ ] Relatório consolidado.
- [ ] Relatório de status diário.
- [ ] Lista/detalhe de preenchidos.
- [ ] PDF com `tcpdf`.
- [ ] Filtros por empresa/período/tipo/veículo.

**Aceite:** filtros retornam dados corretos; PDF abre e respeita o período.

### 10.6 Fase 5 — Refinamentos

- [ ] Responsividade mobile.
- [ ] Logs e mensagens amigáveis.
- [ ] Testes manuais completos.
- [ ] Documentação do módulo.
- [ ] Script opcional de importação do DSS.

**Aceite:** fluxo completo em desktop e celular; documentação publicada.

---

## 11. Testes

### 11.1 Automatizados/CLI

- `php checklist/testes/teste_periodo.php` — cálculo de data de referência.
- `php checklist/testes/teste_pendencias.php` — abertura/fechamento.
- `php checklist/testes/teste_assinatura.php` — validação de data URL.
- `php -l` em todos os arquivos.

### 11.2 Manuais

- [ ] Criar modelo DIÁRIO com categorias, subitens e bloco de extintores.
- [ ] Criar modelo MENSAL.
- [ ] Ativar/desativar modelo sem preenchimento.
- [ ] Ativar/desativar modelo com histórico.
- [ ] Motorista sem veículo: mensagem correta.
- [ ] Motorista com veículo: lista e preenchimento.
- [ ] Troca de veículo conforme cada permissão.
- [ ] Preencher todos os itens; validar campos obrigatórios.
- [ ] Assinar motorista e responsável.
- [ ] Tentar duplicar preenchimento no mesmo período.
- [ ] Marcar Não Conforme e conferir pendência.
- [ ] Marcar Conforme depois e conferir fechamento.
- [ ] Resolver pendência com O.S. e observação.
- [ ] Acompanhar status diário e mensal.
- [ ] Registrar indisponibilidade e conferir status.
- [ ] Registrar isenção individual e todos.
- [ ] Gerar relatório consolidado e PDF.
- [ ] Testar permissões de perfis diferentes.
- [ ] Testar em dois tenants.

---

## 12. Deploy e migração

1. Implementar em `armazem_paraiba/checklist/`.
2. Adicionar `CHECKLIST_ENABLED=1` no `.env` do tenant piloto.
3. Rodar:
   ```bash
   php armazem_paraiba/checklist/migrar.php
   ```
   ou criar `checklist/migrar.php` dentro do módulo, no mesmo padrão do WhatsApp.
4. Liberar permissões no perfil.
5. Cadastrar um modelo de teste.
6. Validar com um motorista de teste.
7. Liberar para os demais tenants.

O deploy do `.cpanelPROD.yml` copia `armazem_paraiba/*`, incluindo a nova pasta.

---

## 13. Riscos

| Risco | Mitigação |
|---|---|
| Mapeamento de unidade (empresa x setor) | Definir antes da Fase 1; recomendação: empresa. |
| Motorista com mais de um veículo | Definir regra; recomendação: permitir seleção entre os vinculados. |
| Fuso horário | Usar o fuso do `conecta.php` (America/Fortaleza) em todo o módulo. |
| Assinatura pesada no banco | Limitar tamanho; opcionalmente salvar em arquivo. |
| Duplicidade de preenchimento | Unique key e validação no servidor. |
| Relatórios lentos | Índices por placa/data e filtros obrigatórios. |
| Integração de manutenção | Webhook opcional, com timeout e log. |
| Dados históricos do DSS | Importação separada, com dry-run. |
| Permissões por setor | Testar com usuário não admin e setor correto. |

---

## 14. Decisões pendentes

1. **Unidade:** usar `empresa` ou `setor` como unidade do checklist?
   - Recomendação: `empresa`.
2. **Veículo:** usar `placa` como conjunto e permitir mais de uma placa por motorista?
   - Recomendação: usar `placa`; permitir seleção quando houver mais de uma.
3. **Tipos:** implementar DIÁRIO, SEMANAL, MENSAL e OUTRO desde o MVP?
   - Recomendação: sim, com data de referência por tipo.
4. **Integração de manutenção:** webhook opcional ou nenhum no MVP?
   - Recomendação: opcional, desligado por padrão.
5. **Assinatura:** base64 no banco ou arquivo em pasta protegida?
   - Recomendação: base64 para o MVP; arquivo se o volume crescer.
6. **Importar dados do DSS?**
   - Recomendação: apenas modelos/itens no início; histórico depois, se necessário.
7. **Flag `CHECKLIST_ENABLED`?**
   - Recomendação: sim, para ativação controlada por tenant.

---

## 15. Critérios de aceite finais

- [ ] Admin cria modelo com itens/subitens e bloco de extintores.
- [ ] Motorista vê apenas checklists da sua empresa e período.
- [ ] Motorista preenche, assina e envia uma única vez por período.
- [ ] Não Conforme gera pendência; Conforme fecha.
- [ ] Admin acompanha status diário/mensal por veículo.
- [ ] Admin registra indisponibilidade e isenção.
- [ ] Pendências podem ser resolvidas com O.S. e observação.
- [ ] Relatórios e PDF refletem os filtros.
- [ ] Permissões bloqueiam acessos indevidos.
- [ ] Nenhum dado de outro tenant é acessível.
- [ ] Documentação e testes concluídos.

---

## 16. Referências do DSS

- Repositório: `https://github.com/transp-admir/dss/tree/main/app`
- Modelos: `app/models.py` (bloco “MODELOS DE CHECKLIST”).
- Rotas: `app/routes.py` (`/checklist/preencher`, `/checklists`, `/checklists/add`, `/checklists/<id>`, `/checklists/add_item`, `/checklists/preenchidos`, `/acompanhamento_diario`, `/pendencias`, `/relatorios_consolidados`, `/relatorio/status_diario`, `/gerar_relatorio_pdf`).
- Templates: `checklists.html`, `checklist_detalhe.html`, `motorista_lista_checklists.html`, `motorista_preencher_checklist.html`, `admin_pendencias.html`, `admin_acompanhamento_diario.html`, `admin_relatorios_consolidados.html`, `admin_relatorio_status_diario.html`.
- JS/CSS: `static/js/checklist.js`, `static/css/checklist.css`.
