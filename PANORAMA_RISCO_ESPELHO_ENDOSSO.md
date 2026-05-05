# Panorama Completo de Impacto e Risco

Data da analise: 2026-05-05
Escopo principal: espelho_ponto.php, funcoes_ponto.php, endosso.php e paineis/endosso.php

## 1) Visao Geral do Acoplamento

O modulo de ponto/endosso esta fortemente acoplado por:
- Regras de negocio centralizadas em funcoes_ponto.php
- Contrato de dados entre funcoes de calculo e telas
- Persistencia hibrida: banco + CSV + JSON
- Controle de acesso por caminho literal de menu/permissao

Arquivos centrais:
- [armazem_paraiba/funcoes_ponto.php](armazem_paraiba/funcoes_ponto.php#L11)
- [armazem_paraiba/espelho_ponto.php](armazem_paraiba/espelho_ponto.php#L13)
- [armazem_paraiba/endosso.php](armazem_paraiba/endosso.php#L7)
- [armazem_paraiba/paineis/endosso.php](armazem_paraiba/paineis/endosso.php#L13)

## 2) Fluxo Funcional de Alto Nivel

### 2.1 Espelho

1. Usuario acessa espelho
2. Validacao de permissao por path
3. Busca motorista(s) e periodo
4. Recupera dados endossados do mes
5. Calcula dias restantes com diaDetalhePonto
6. Soma totais e renderiza tabela
7. Exibe saldos e permite redirecionamento para ajuste

Pontos tecnicos:
- Permissao: [armazem_paraiba/espelho_ponto.php](armazem_paraiba/espelho_ponto.php#L343)
- Busca de endosso do mes: [armazem_paraiba/espelho_ponto.php](armazem_paraiba/espelho_ponto.php#L671)
- Calculo dia a dia: [armazem_paraiba/espelho_ponto.php](armazem_paraiba/espelho_ponto.php#L708)
- Totais: [armazem_paraiba/espelho_ponto.php](armazem_paraiba/espelho_ponto.php#L786)
- Tabela: [armazem_paraiba/espelho_ponto.php](armazem_paraiba/espelho_ponto.php#L899)

### 2.2 Consulta de Endosso

1. Usuario filtra por empresa/data/motorista/status
2. Sistema identifica endossados por banco e CSV
3. Monta visao consolidada por motorista
4. Calcula pagamento (HE50/HE100) e saldos
5. Exibe tabela e habilita impressao

Pontos tecnicos:
- SQL de mes: [armazem_paraiba/endosso.php](armazem_paraiba/endosso.php#L9)
- Identificacao de ids endossados: [armazem_paraiba/endosso.php](armazem_paraiba/endosso.php#L38)
- Busca principal: [armazem_paraiba/endosso.php](armazem_paraiba/endosso.php#L302)
- Regra de pagamento: [armazem_paraiba/endosso.php](armazem_paraiba/endosso.php#L494)
- Permissao: [armazem_paraiba/endosso.php](armazem_paraiba/endosso.php#L654)

### 2.3 Painel de Endosso

1. Opcionalmente atualiza arquivos de relatorio
2. Le arquivos JSON em arquivos/endossos
3. Aplica filtros ocupacao/cargo/setor/subsetor
4. Recalcula totais e distribuicoes
5. Renderiza painel e prepara exportacao/impressao

Pontos tecnicos:
- Entrada do painel: [armazem_paraiba/paineis/endosso.php](armazem_paraiba/paineis/endosso.php#L339)
- Atualizacao dos dados: [armazem_paraiba/paineis/endosso.php](armazem_paraiba/paineis/endosso.php#L350)
- Pasta de leitura: [armazem_paraiba/paineis/endosso.php](armazem_paraiba/paineis/endosso.php#L538)
- Leitura de JSON por empresa/motorista: [armazem_paraiba/paineis/endosso.php](armazem_paraiba/paineis/endosso.php#L645)

## 3) Nucleo de Regras (Maior Ponto de Risco)

Funcoes mais sensiveis:
- calcularHorasAPagar: [armazem_paraiba/funcoes_ponto.php](armazem_paraiba/funcoes_ponto.php#L195)
- diaDetalhePonto: [armazem_paraiba/funcoes_ponto.php](armazem_paraiba/funcoes_ponto.php#L632)
- montarEndossoMes: [armazem_paraiba/funcoes_ponto.php](armazem_paraiba/funcoes_ponto.php#L1617)
- setTotalResumo: [armazem_paraiba/funcoes_ponto.php](armazem_paraiba/funcoes_ponto.php#L2134)
- somarTotais: [armazem_paraiba/funcoes_ponto.php](armazem_paraiba/funcoes_ponto.php#L2142)

Impacto transversal:
- Espelho
- Cadastro de endosso
- Consulta de endosso
- Paineis
- Relatorios e impressao

## 4) Arquivos Ligados Diretamente

Inclusoes de funcoes_ponto.php detectadas:
- [armazem_paraiba/espelho_ponto.php](armazem_paraiba/espelho_ponto.php#L13)
- [armazem_paraiba/endosso.php](armazem_paraiba/endosso.php#L7)
- [armazem_paraiba/cadastro_endosso.php](armazem_paraiba/cadastro_endosso.php#L12)
- [armazem_paraiba/ajuste_pontofuncionario.php](armazem_paraiba/ajuste_pontofuncionario.php#L2)
- [armazem_paraiba/ajuste_ponto.php](armazem_paraiba/ajuste_ponto.php#L11)
- [armazem_paraiba/nao_conformidade.php](armazem_paraiba/nao_conformidade.php#L18)
- [armazem_paraiba/relatorio_pontos.php](armazem_paraiba/relatorio_pontos.php#L11)
- [armazem_paraiba/gerar_espelho_assinatura.php](armazem_paraiba/gerar_espelho_assinatura.php#L7)
- [armazem_paraiba/paineis/endosso.php](armazem_paraiba/paineis/endosso.php#L13)
- [armazem_paraiba/paineis/saldo.php](armazem_paraiba/paineis/saldo.php#L13)

## 5) Alertas de Alteracao (Onde Pode Quebrar)

## ALERTA A - Alterar estrutura de retorno de diaDetalhePonto

Risco:
- Mudar nome de chave, ordem de colunas ou formato de valor

Pode quebrar em:
- Espelho: [armazem_paraiba/espelho_ponto.php](armazem_paraiba/espelho_ponto.php#L786)
- Endosso consulta: [armazem_paraiba/endosso.php](armazem_paraiba/endosso.php#L412)
- Tabelas de renderizacao: chamadas de montarTabelaPonto

Sintoma esperado:
- Colunas erradas
- Totais trocados
- Campos vazios em massa
- Avisos incoerentes

## ALERTA B - Alterar calcularHorasAPagar

Risco:
- Mudar regra de prioridade HE100/HE50
- Mudar semantica de saldo negativo

Pode quebrar em:
- Cadastro: [armazem_paraiba/cadastro_endosso.php](armazem_paraiba/cadastro_endosso.php#L576)
- Consulta: [armazem_paraiba/endosso.php](armazem_paraiba/endosso.php#L494)
- Relatorios de saldo e painel

Sintoma esperado:
- Saldo final divergente entre telas
- Pagamento de HE fora da regra esperada
- Diferenca entre endosso salvo e exibido

## ALERTA C - Alterar montagem de endosso mensal

Risco:
- Mudar logica de merge de multiplos endossos
- Mudar chaves em totalResumo

Pode quebrar em:
- Espelho (aproveitamento de dias ja endossados)
- Consulta de endosso consolidada
- Painel por empresa/motorista

Ponto sensivel:
- [armazem_paraiba/funcoes_ponto.php](armazem_paraiba/funcoes_ponto.php#L1617)

## ALERTA D - Alterar CSV/JSON de endosso

Risco:
- Mudar estrutura sem versionamento/migracao

Pode quebrar em:
- Leitura de historico em consultas
- Painel que depende de arquivos em disco
- Exportacoes/impressao

Pontos tecnicos:
- Leitura JSON: [armazem_paraiba/paineis/endosso.php](armazem_paraiba/paineis/endosso.php#L653)
- Leitura em relatorio: [armazem_paraiba/relatorio_espelho.php](armazem_paraiba/relatorio_espelho.php#L69)

## ALERTA E - Alterar path de paginas

Risco:
- Renomear arquivo sem atualizar permissoes e menu

Pode quebrar em:
- Acesso de usuarios operacionais
- Itens de menu nao aparecem
- Redirecionamento indevido para batida

Pontos tecnicos:
- Regra especial funcionario: [armazem_paraiba/check_permission.php](armazem_paraiba/check_permission.php#L48)
- Mapeamento menu: [armazem_paraiba/menu.php](armazem_paraiba/menu.php#L83)
- Header assinatura: [armazem_paraiba/assinatura/componentes/layout_header.php](armazem_paraiba/assinatura/componentes/layout_header.php#L116)

## ALERTA F - DDL em runtime no endosso

Risco:
- Execucao de ALTER TABLE durante request

Ponto tecnico:
- [armazem_paraiba/endosso.php](armazem_paraiba/endosso.php#L22)

Pode quebrar em:
- Ambientes com permissao restrita no banco
- Deploy parcial sem coluna endo_nb_empresa

Sintoma esperado:
- Falhas intermitentes
- Filtro por empresa inconsistente

## 6) Matriz de Impacto Rapida

Mudanca: Assinatura de funcao em funcoes_ponto.php
- Impacto: Muito alto
- Areas: Espelho, Endosso, Painel, Ajustes, Relatorios
- Risco: Fatal em runtime

Mudanca: Regra de saldo/HE
- Impacto: Muito alto
- Areas: Cadastro, Consulta, Painel, Financeiro
- Risco: Erro de negocio

Mudanca: Nome de path de tela
- Impacto: Alto
- Areas: Permissao, Menu, Links internos
- Risco: Acesso bloqueado

Mudanca: Estrutura JSON/CSV
- Impacto: Alto
- Areas: Painel e historico
- Risco: Dados nao carregam

Mudanca: Apenas CSS/HTML do espelho
- Impacto: Medio
- Areas: Visual e impressao
- Risco: Baixo no calculo, medio na usabilidade

## 7) Checklist Obrigatorio Antes de Subir Alteracao

### 7.1 Validacao funcional por perfil

- Administrador
- Supervisor
- Motorista
- Ajudante
- Funcionario
- Terceirizado

Validar para cada perfil:
- Acesso a espelho
- Acesso a endosso (se aplicavel)
- Acesso a painel (se aplicavel)
- Menu exibindo links corretos

### 7.2 Validacao de regra

- Periodo com saldo positivo
- Periodo com saldo negativo
- HE100 maior que limite
- HE50 + HE100 no limite
- Funcionario sem ponto
- Funcionario com faltas nao justificadas
- Motorista com espera/repouso
- Escala com virada de dia
- Feriado e domingo facultativo

### 7.3 Validacao de consistencia entre telas

Conferir se os valores batem entre:
- Espelho
- Cadastro de endosso
- Consulta de endosso
- Painel de endosso

Campos criticos:
- saldoAnterior
- diffSaldo (saldo periodo)
- saldoBruto
- he50APagar
- he100APagar
- saldoFinal

### 7.4 Validacao de arquivo e persistencia

- CSV do endosso gerado corretamente
- JSON do painel gerado e lido
- Sem divergencia entre banco e arquivo

## 8) Recomendacoes de Mudanca Segura

1. Para mudancas em regra de negocio, alterar primeiro funcoes_ponto.php em branch isolada e executar bateria de regressao completa.
2. Nao renomear chaves de retorno sem camada de compatibilidade temporaria.
3. Se mudar estrutura de JSON/CSV, aplicar versionamento de schema e fallback de leitura.
4. Evitar DDL em runtime; mover para migracao de banco controlada.
5. Em mudanca de path, atualizar em conjunto:
- check_permission.php
- menu.php
- layout_header.php
- chamadas internas de redirecionamento

## 9) Mapa de Arquivos que Tambem Devem Ser Revisados em Mudancas

- [armazem_paraiba/cadastro_endosso.php](armazem_paraiba/cadastro_endosso.php#L339)
- [armazem_paraiba/nao_conformidade.php](armazem_paraiba/nao_conformidade.php#L171)
- [armazem_paraiba/relatorio_pontos.php](armazem_paraiba/relatorio_pontos.php#L71)
- [armazem_paraiba/paineis/funcoes_paineis.php](armazem_paraiba/paineis/funcoes_paineis.php#L506)
- [armazem_paraiba/gerar_espelho_assinatura.php](armazem_paraiba/gerar_espelho_assinatura.php#L47)

## 10) Conclusao Executiva

Se houver alteracao em funcoes_ponto.php, o risco de regressao e alto e transversal.

As quebras mais provaveis aparecem em tres frentes:
- Regra de saldo/HE inconsistente
- Contrato de dados quebrado entre calculo e tela
- Falha de acesso por path/permissao/menu

Prioridade de teste apos qualquer alteracao:
1. Espelho por motorista e por periodo
2. Cadastro e consulta de endosso
3. Painel consolidado por empresa
4. Permissao e navegacao por perfil
