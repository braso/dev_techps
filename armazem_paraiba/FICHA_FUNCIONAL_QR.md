# Ficha Funcional Pública (QR Code)

Módulo de consulta pública da ficha funcional do colaborador. O acesso é feito por:
- QR Code impresso no cartão entregue ao funcionário; ou
- código único digitado em `ficha_publica.php`.

A página é pública, não exige login, e exibe somente:
- identificação do funcionário (nome, matrícula, CPF mascarado, cargo, setor, empresa, admissão, situação e foto);
- EPIs atualmente em posse, com data de entrega, vencimento e status;
- treinamentos concluídos, com data de conclusão, carga horária, validade e status.

## Telas

### `ficha_qrcode.php`

Tela interna autenticada (permissão herdada de `/cadastro_funcionario.php`).

- **Individual**: aberta pelo botão **QR Code** na ficha do funcionário. Exibe o cartão, o link, o código e os botões de copiar/imprimir/regenerar.
- **Lista/lote**: aberta pelo botão **QR Codes** na listagem de funcionários ou pelo menu **Cadastros → Ficha Funcional (QR)**. Permite filtrar por nome, matrícula, empresa, setor e status; selecionar vários funcionários e imprimir os cartões em lote.

### `ficha_publica.php`

Página pública, sem sessão. Recebe o código por `?c=XXXXX-XXXXX` ou pelo formulário de consulta.

## Código único

- 10 caracteres no formato `XXXXX-XXXXX`.
- Alfabeto sem `I`, `L`, `O` e `U` para evitar confusão na digitação.
- Gerado aleatoriamente na primeira abertura do QR e armazenado na tabela `funcionario_ficha_publica`.
- A digitação aceita com/sem hífen e em maiúsculas/minúsculas.
- **Gerar novo código** inativa o código anterior. O cartão/QR antigo deixa de funcionar.
- Funcionários inativos não têm a ficha exibida publicamente.

## Regras de status

### EPIs

Considera o registro mais recente de cada combinação funcionário + EPI + variação. Registros excluídos (`inativo`) e de não-entrega (`nao_entregue`) são ignorados. Só é exibido se o registro mais recente estiver `ativo`.

| Status | Regra |
|---|---|
| Em dia | Vencimento maior que hoje + 30 dias |
| A vencer | Vencimento entre hoje e hoje + 30 dias |
| Vence hoje | Vencimento igual a hoje |
| Vencido | Vencimento anterior a hoje |
| Sem validade | EPI sem vida útil e sem validade de lote (vencimento igual à entrega) |

### Treinamentos

Exibe somente `treinamento_progresso` com `trepr_nb_concluido = 1` na linha geral (sem episódio). Treinamentos concluídos continuam aparecendo mesmo que o cadastro do treinamento tenha sido inativado depois.

| Status | Regra |
|---|---|
| Em dia | Conclusão + dias de validade maior que hoje + 30 dias |
| A vencer | Validade entre hoje e hoje + 30 dias |
| Vence hoje | Validade igual a hoje |
| Vencido | Validade anterior a hoje |
| Sem validade | `trei_nb_dias_validade = 0` |

## Configuração da URL pública

O link do QR é montado a partir do host atual da requisição. Em produção, se o sistema for acessado por um host interno/proxy, defina no `.env`:

```env
FICHA_PUBLICA_URL_BASE = https://app.suaempresa.com.br
```

Informe apenas `scheme + host`, sem o caminho `/braso`. O sistema acrescenta automaticamente `APP_PATH + CONTEX_PATH + /ficha_publica.php`.

## Segurança e privacidade

- A página pública não inicia sessão e não inclui `conecta.php`.
- Todas as consultas usam prepared statements.
- A saída HTML é escapada.
- Cabeçalhos: `noindex`, `nofollow`, `no-store`, `no-referrer`, `X-Frame-Options: DENY`.
- CPF exibido somente como `***.***.***-XX`.
- Não são exibidos salário, endereço, RG, CNH, dados bancários ou dados médicos.
- O código dá acesso à ficha: entregue o cartão apenas ao funcionário e regenere o código em caso de perda/compartilhamento indevido.
- A tela interna registra em `log_sistema` os eventos `codigo_gerado` e `codigo_regenerado`.

## Banco de dados

Tabela criada automaticamente pelo `conecta.php` e também pela página pública:

```sql
CREATE TABLE funcionario_ficha_publica (
    ffp_nb_id INT AUTO_INCREMENT PRIMARY KEY,
    ffp_nb_entidade INT NOT NULL,
    ffp_tx_codigo CHAR(10) NOT NULL,
    ffp_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
    ffp_dt_dataCadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ffp_nb_userCadastro INT NULL,
    ffp_dt_dataAtualiza DATETIME NULL,
    ffp_nb_userAtualiza INT NULL,
    UNIQUE KEY uk_codigo (ffp_tx_codigo),
    KEY idx_entidade_status (ffp_nb_entidade, ffp_tx_status)
);
```

Índices de apoio:
- `ss_epi_entrega (ss_e_nb_colaborador_id, ss_e_tx_status)`
- `ss_epi_entrega (ss_e_nb_colaborador_id, ss_e_nb_epi_id, ss_e_tx_variacao)`
- `user (user_nb_entidade)`

## Arquivos

- `ficha_publica.php` — página pública.
- `ficha_publica_funcoes.php` — conexão, código, consultas, status, CPF, URLs e QR SVG.
- `ficha_qrcode.php` — tela interna de geração/impressão.
- `conecta.php` — migração da tabela e índices.
- `cadastro_funcionario.php` — botões de acesso.
- `menu_estrutura.php` — item de menu.
