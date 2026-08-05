# Diarias - Guia de Manutencao

## Visao geral
O controle de diarias e feito pelo GESTOR (nao ha solicitacao do funcionario).
O gestor:

1. Deposita o valor pago ao motorista informando a quantos dias aquele valor e referente
   (ex.: 10 dias de diarias).
2. Lanca dia a dia a diaria consumida pelo motorista (diaria cheia ou outro valor).
3. Acompanha o saldo em reais e em dias no periodo.

## Fluxo de trabalho

### Deposito
- Lancar valor total pago + quantidade de dias que o valor cobre (5, 10, 15...).
- O sistema calcula o valor por dia (total / dias) para exibicao.
- O deposito nao e obrigatorio a completar 10 dias exatos: se o saldo ficou negativo,
  deposita-se 10 dias + o saldo negativo; se positivo, pode completar apenas os 10 dias
  ou lancar qualquer valor desejado.

### Consumo diario
- A cada dia, o gestor informa se o motorista usou a diaria cheia (valor padrao
  configuravel, ver parametro valor_diaria_cheia) ou outro valor.
- Cada lancamento de consumo conta como um dia consumido.

### Saldo
- Saldo (R$) = total depositado - total consumido.
- Saldo em dias = dias depositados - dias consumidos.
- Painel mostra saldo do periodo selecionado (mes) e saldo geral de todos os periodos.

## Arquivos da pasta
- helpers_diarias.php
  - Schema das tabelas, parametros, calculo de saldo e consultas.
- gestao_diarias.php
  - Tela principal do gestor: filtro por empresa (matriz por padrao) e funcionario,
    lancamento de consumo, lancamento de deposito, saldos e historicos.
- parametros_diarias.php
  - Tela restrita a super admin para ajustar valores de referencia e limite de km.

## Filtro de empresa e funcionario
- O campo "Empresa" lista a matriz primeiro e as filiais (vinculadas pelo CNPJ da
  matriz) em seguida; por padrao vem selecionada a empresa matriz.
- O campo "Funcionario" lista os funcionarios ativos da empresa selecionada.
- Se um funcionario for escolhido de outra empresa (ex.: pelo atalho de lancamentos),
  a empresa e ajustada automaticamente.

## Parametros configuráveis (tabela diaria_parametro)
- valor_pernoite        -> R$ 107,00 (referencia da clausula)
- valor_sem_pernoite    -> R$ 55,00
- valor_almoco          -> R$ 40,00
- limite_km_almoco      -> 80 km
- valor_diaria_cheia    -> R$ 107,00 (valor usado no lancamento de "diaria cheia")

Os valores padrao sao inseridos automaticamente na primeira execucao.

## Tabelas do modulo

### diaria_deposito
```sql
CREATE TABLE diaria_deposito (
    depr_nb_id INT AUTO_INCREMENT PRIMARY KEY,
    depr_nb_entidade INT NOT NULL,
    depr_tx_data DATE NOT NULL,
    depr_nb_dias INT NOT NULL DEFAULT 1,
    depr_tx_valor_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    depr_tx_valor_dia DECIMAL(10,2) NOT NULL DEFAULT 0,
    depr_tx_observacao TEXT,
    depr_nb_user INT DEFAULT NULL,
    depr_tx_dataCadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entidade (depr_nb_entidade),
    INDEX idx_data (depr_tx_data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### diaria_consumo
```sql
CREATE TABLE diaria_consumo (
    dcon_nb_id INT AUTO_INCREMENT PRIMARY KEY,
    dcon_nb_entidade INT NOT NULL,
    dcon_tx_data DATE NOT NULL,
    dcon_tx_tipo ENUM('cheia','outra') NOT NULL DEFAULT 'cheia',
    dcon_tx_valor DECIMAL(10,2) NOT NULL DEFAULT 0,
    dcon_tx_observacao TEXT,
    dcon_nb_user INT DEFAULT NULL,
    dcon_tx_dataCadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entidade (dcon_nb_entidade),
    INDEX idx_data (dcon_tx_data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### diaria_parametro
```sql
CREATE TABLE diaria_parametro (
    diar_pa_nb_id INT AUTO_INCREMENT PRIMARY KEY,
    diar_pa_tx_chave VARCHAR(60) NOT NULL,
    diar_pa_tx_valor VARCHAR(60) NOT NULL,
    diar_pa_tx_descricao VARCHAR(255),
    diar_pa_tx_status ENUM('ativo','inativo') DEFAULT 'ativo',
    diar_pa_tx_dataCadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_chave (diar_pa_tx_chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

## Dicas de manutencao
- Em erros de fluxo, use o arquivo debug_log_diarias.txt na raiz de armazem_paraiba.
- Valores alterados em parametros_diarias.php valem para novos lancamentos; lancamentos
  ja salvos mantem o valor informado no momento.
- O valor da diaria cheia preenche automaticamente o campo de consumo; para valores
  diferentes use o tipo "outro valor".
- O acesso a tela de gestao e via menu "Diarias -> Gestao de Diarias" (perfil com
  permissao no menu ou super admin).
