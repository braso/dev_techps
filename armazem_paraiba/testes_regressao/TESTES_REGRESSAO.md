# Testes de Regressao do Modulo de Ponto e Endosso

Este modulo valida os pontos criticos do sistema sempre que houver alteracao em regra de negocio, permissao, menu, calculo de jornada, saldo, endosso ou exportacao.

Arquivos principais do modulo:
- [run.php](run.php)
- [../funcoes_ponto.php](../funcoes_ponto.php)
- [../espelho_ponto.php](../espelho_ponto.php)
- [../endosso.php](../endosso.php)
- [../paineis/endosso.php](../paineis/endosso.php)

## 1. O que este modulo testa

### 1.1 Contratos de fonte

O runner verifica se os arquivos sensiveis ainda contem os caminhos, funcoes e chamadas criticas esperadas.

Ele alerta se sumirem ou mudarem estes pontos:
- permissao de [espelho_ponto.php](../espelho_ponto.php)
- permissao de [endosso.php](../endosso.php)
- permissao de [paineis/endosso.php](../paineis/endosso.php)
- links do menu em [menu.php](../menu.php)
- links do header de assinatura em [assinatura/componentes/layout_header.php](../assinatura/componentes/layout_header.php)
- include de [funcoes_ponto.php](../funcoes_ponto.php) nas telas principais
- chamadas de [montarEndossoMes](../funcoes_ponto.php), [diaDetalhePonto](../funcoes_ponto.php), [calcularHorasAPagar](../funcoes_ponto.php), [setTotalResumo](../funcoes_ponto.php) e [somarTotais](../funcoes_ponto.php)

### 1.2 Funcoes puras de negocio

O runner valida regras que nao dependem de tela:
- calculo de jornada prevista com feriado e abono
- calculo de abono sobre saldo negativo
- calculo de saldo diario
- calculo de pagamento de HE50 e HE100
- tratamento de saldo negativo com e sem permissao de pagamento em periodo negativo

### 1.3 Integracao com banco e dados reais

Quando rodado com `--integration`, o modulo valida:
- retorno de [diaDetalhePonto](../funcoes_ponto.php) para um funcionario real
- retorno de [montarEndossoMes](../funcoes_ponto.php) para um endosso real
- coerencia dos contratos de menu e permissao

### 1.4 Runtime do endosso

Quando rodado com `--endosso-runtime`, o modulo inclui [endosso.php](../endosso.php) e valida helpers como:
- `endosso_mes_sql`
- `endosso_empresa_cond`
- `endosso_ids_mes`

Atenção: este modo pode executar migracoes de schema que existem dentro de [endosso.php](../endosso.php). Use apenas em ambiente de desenvolvimento ou staging.

## 2. Como executar

### 2.1 Execucao recomendada dentro do container Apache

Como o ambiente local pode nao ter PHP instalado, o caminho mais confiavel e executar dentro do container definido em [docker-compose.yml](../../docker-compose.yml):

```bash
docker compose up -d
docker compose exec apache php /var/www/html/braso/armazem_paraiba/testes_regressao/run.php
```

### 2.2 Execucao completa com integracao

```bash
docker compose exec apache php /var/www/html/braso/armazem_paraiba/testes_regressao/run.php --integration
```

### 2.3 Execucao completa com integracao e helpers do endosso

```bash
docker compose exec apache php /var/www/html/braso/armazem_paraiba/testes_regressao/run.php --integration --endosso-runtime
```

### 2.4 Saida em JSON

```bash
docker compose exec apache php /var/www/html/braso/armazem_paraiba/testes_regressao/run.php --integration --json
```

## 3. Ordem ideal antes de subir uma alteracao

1. Rodar os contratos de fonte e funcoes puras.
2. Rodar a integracao com banco.
3. Rodar o runtime do endosso apenas se a alteracao tocou [endosso.php](../endosso.php) ou a regra de pagamento/consulta.
4. Revisar qualquer falha antes de commitar.

## 4. Cenarios criticos cobertos

### 4.1 Espelho de ponto

Validar que continuam funcionando:
- tela acessa [espelho_ponto.php](../espelho_ponto.php)
- permissao do caminho continua ativa
- busca de periodo e motorista
- montagem de tabela com dias, intervalos e saldos
- redirecionamento para ajuste de ponto

Sinais de quebra:
- faltam colunas na tabela
- saldo diario vem vazio ou invertido
- usuario perde acesso mesmo com perfil valido
- botao de ajuste para de abrir a pagina correta

### 4.2 Consulta de endosso

Validar que continuam funcionando:
- consulta por empresa e periodo
- calculo de HE50 e HE100
- consolidacao de endossos do mes
- leitura de CSV/JSON do endosso
- impressao do relatorio

Sinais de quebra:
- saldo final diverge entre tela e arquivo
- um motorista endossado aparece como nao endossado
- totais por empresa ficam inconsistentes
- filtro por empresa ou cargo deixa de refletir na listagem

### 4.3 Painel de endosso

Validar que continuam funcionando:
- leitura dos arquivos em `arquivos/endossos`
- agregacao por empresa e por motorista
- totais de endossados, parciais e nao endossados
- exportacao da tabela do painel

Sinais de quebra:
- painel abre sem dados apesar de haver arquivos
- JSON nao e encontrado ou nao e parseado
- filtros de ocupacao/cargo/setor deixam de refletir no painel

### 4.4 Permissao e menu

Validar que continuam funcionando:
- item de menu do espelho
- item de menu da consulta de endosso
- item de menu do painel de endosso
- excecao de acesso para perfis operacionais

Sinais de quebra:
- o item some do menu
- perfil operacional e redirecionado para batida indevidamente
- uma rota muda e quebra o acesso no header e no menu

### 4.5 Matriz de pagamento e saldo final

O runner compara os resultados de pagamento e saldo final com esta referencia automatica:

| Cenario | Entrada resumida | Pagamento esperado | Saldo final esperado |
| --- | --- | --- | --- |
| Saldo positivo suficiente | SA 14:00, SP 14:00, HE50 04:00, HE100 10:00, limite 14:00 | HE50 04:00 / HE100 10:00 | 00:00 |
| Saldo positivo insuficiente para HE100 integral | SA 02:00, SP 02:00, HE50 01:00, HE100 10:00, limite 02:00 | HE50 00:00 / HE100 02:00 | 00:00 |
| Saldo negativo sem permissao | SA -08:00, SP -08:00, HE50 01:00, HE100 10:00, limite 02:00, regra nao | HE50 00:00 / HE100 00:00 | -08:00 |
| Saldo negativo com permissao | SA -08:00, SP -08:00, HE50 01:00, HE100 10:00, limite 10:00, regra sim | HE50 00:00 / HE100 10:00 | -18:00 |
| Banco de horas com saldo anterior positivo | SA 02:00, SP 02:00, HE50 01:00, HE100 10:00, limite 10:00, regra sim | HE50 00:00 / HE100 10:00 | -06:00 |
| Banco de horas com saldo suficiente | SA 02:00, SP 16:00, HE50 04:00, HE100 10:00, limite 10:00, regra sim | HE50 00:00 / HE100 10:00 | 08:00 |
| Desconto por atrasos nao justificados | SP 10:00, HE50 01:00, HE100 04:00, desconto faltas 02:00 | HE50 01:00 / HE100 04:00 | 03:00 |

Observacao:
- Estes cenarios validam a ordem automatica de pagamento que o sistema usa no endosso.
- Os cenarios de banco de horas validam como o saldo negativo acumula quando permitido pela regra "Pagar H.E. Ex. mesmo com Período Neg.".
- O cenario de desconto por atrasos valida a reducao do saldo final quando ha horas nao justificadas.
- Se a regra de banco de horas, zerar saldo negativo ou o cálculo de limite por parametro mudar, esta matriz deve ser revisada junto com o runner.
- Em ambiente real, os valores finais devem bater com o que aparece no espelho, no cadastro de endosso e no relatorio/painel.

### 4.6 Parametrizacao considerada nos testes

Os testes assumem a mesma cadeia de parametros usada pelo sistema:

- `Máx. de H.E. Semanal por dia*` define o teto de pagamento imediato de horas a 50% antes de remanejar excedentes para 100%.
- `Pagar H.E. Ex. mesmo com Período Neg.*` altera a ordem de pagamento quando o periodo fecha negativo.
- `Pagar Adicional Noturno*` determina se a janela noturna 22:00-05:00 entra no calculo de adicional.
- `Descontar horas por faltas não justificadas?` afeta o saldo final e o valor pago quando ha ausencia sem justificativa.
- `Utilizar regime de banco de horas?` define se o saldo nao pago/negativo deve ser acumulado como banco ou apenas zerado no fechamento.

Como isso entra na validacao automatica:

- **Ordem de pagamento por saldo (Testes 1-4)**: O runner cobre cenarios de saldo positivo, insuficiente e negativo, validando a prioridade HE100 → HE50 e o comportamento com/sem permissao de pagamento em periodo negativo.

- **Banco de horas (Testes 5-6)**: Validam como saldos negativos acumulam quando "Pagar H.E. Ex. mesmo com Período Neg." está ativo. No teste 5, com SA=02:00 e SP=02:00, pagar HE100=10:00 resulta em SF=-06:00; no teste 6, com saldo total suficiente de 18:00, após pagar HE100=10:00, SF=08:00.

- **Desconto por faltas (Teste 7)**: Valida que atrasos nao justificados reduzem o saldo final. Com SP=10:00, após pagar HE100=04:00 e HE50=01:00 (total 05:00), aplicar desconto de 02:00 resulta em SF=03:00.

- O modo `--endosso-runtime` existe para validar os helpers do fluxo real do endosso e alertar regressao quando a regra de fechamento ou os helpers de leitura mudarem.

- Quando houver mudanca em banco de horas, zerar saldo negativo, desconto de faltas ou limite semanal de HE, o resultado esperado desta matriz deve ser atualizado junto com a regra no codigo.

## 5. Como interpretar o resultado

### PASS

O caso continua coerente com o contrato esperado.

### FAIL

A regra ou o contrato de arquivo mudou e precisa ser conferido antes de seguir.

### SKIP

O teste nao encontrou dados suficientes no ambiente atual para executar um caso de integracao.

## 6. Quando uma falha exige investigacao imediata

Trate como bloqueio se a falha estiver em qualquer um destes pontos:
- [funcoes_ponto.php](../funcoes_ponto.php) com calculo alterado
- [espelho_ponto.php](../espelho_ponto.php) sem permissao ou sem montagem de tabela
- [endosso.php](../endosso.php) sem calculo de pagamento ou sem leitura de endosso
- [paineis/endosso.php](../paineis/endosso.php) sem leitura de arquivos
- [check_permission.php](../check_permission.php) com rotas removidas
- [menu.php](../menu.php) com rotas ausentes
- [assinatura/componentes/layout_header.php](../assinatura/componentes/layout_header.php) com links quebrados

## 7. Recomendacao de uso no fluxo de desenvolvimento

Antes de alterar qualquer calculo ou permissao:
1. rode o runner sem flags extras;
2. rode novamente com `--integration`;
3. se a alteracao envolver endosso, rode tambem com `--endosso-runtime` em staging;
4. compare o saldo final, os totais e o comportamento de acesso.

## 8. Observacao importante

Este modulo foi desenhado para pegar regressao de contrato e regressao de regra.
Ele nao substitui um teste de ponta a ponta em navegador, mas cobre os pontos que mais quebram neste sistema:
- calculo de jornada
- saldo e pagamento
- permissao por caminho
- leitura de arquivos do endosso
- consistencia entre telas e relatorios
