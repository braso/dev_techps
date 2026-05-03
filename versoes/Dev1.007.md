# Dev1.007 — 03/05/2026

## Correções

### `armazem_paraiba/funcoes_ponto.php`
- Corrigida função `somarTotais` — agora trata `&nbsp;` e HTML antes de somar, resolvendo o problema do **total de Jornada Prevista** não ser exibido no espelho de ponto

### `armazem_paraiba/endosso.php`
- Removidas colunas extras `inicioEscala` e `fimEscala` das linhas da tabela de endosso (apareciam como colunas vazias após "SALDO DIÁRIO")
- Removidos campos `inicioEscala`, `fimEscala`, `HESemanalAPagar`, `HEExAPagar` e `diffJornada` da linha de totalizador, eliminando os `00:00` extras no final da linha de total

## Arquivos Alterados

| Arquivo | Alteração |
|---|---|
| `armazem_paraiba/funcoes_ponto.php` | Correção do `somarTotais` para tratar HTML/`&nbsp;` antes de somar |
| `armazem_paraiba/endosso.php` | Remoção de colunas extras nas linhas e no totalizador |
