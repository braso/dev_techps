# Totem de Registro de Ponto com Biometria Facial

Documentacao da funcionalidade de registro de ponto por reconhecimento facial em modo totem
(kiosk), incluindo fluxo de uso, regras ativas, configuracao e detalhes tecnicos.

Commit de referencia: `5cd46c1` - "nova funcionalidade para bater ponto com facial".

---

## 1. Visao geral

O registro de ponto por facial roda dentro do **Modo Totem da tela de login** (raiz do sistema).
O funcionario ativa o Modo Totem (a empresa e configurada uma vez), toca na tela e a camera abre.
O sistema identifica a pessoa (1:N) e decide:

- se o perfil dela tem **"Registrar Ponto com Facial" = Sim** -> registra o ponto (fluxo
  automatico, secoes 4.4 a 4.6) e mostra o resultado na tela;
- senao -> segue o **fluxo de login do totem** (entra no sistema), como sempre funcionou.

Assim existe um unico Modo Totem: ele bate o ponto de quem tem o perfil habilitado e loga os
demais. O backend do ponto e o endpoint `acao=registrarPontoFacial` do arquivo
`.../<pasta_da_empresa>/totem_ponto.php`; a pagina HTML desse arquivo continua acessivel, mas o
modo oficial e o da tela de login.

A empresa do totem e a pasta do deployment, que define o banco:

```
https://<dominio>/<APP_PATH>/<pasta_da_empresa>/totem_ponto.php   (endpoint)
https://<dominio>/<APP_PATH>/index.php                            (tela de login / Modo Totem)
```

Exemplo no ambiente de desenvolvimento:

```
http://localhost/braso/armazem_paraiba/totem_ponto.php
http://localhost/braso/index.php
```

---

## 2. Fluxo de uso (tela)

| Etapa | O que acontece |
|---|---|
| Modo Totem | Ativado pela tela de login (`index.php`); a empresa fica salva no dispositivo. |
| Toque | O toque na tela abre a camera (modelos e camera ja pre-carregados em background). |
| Identificacao | O sistema aguarda rosto real, valida a qualidade e captura o descritor facial. |
| Perfil com ponto facial | O endpoint registra o ponto automaticamente (regra da secao 4.5) e mostra nome + tipo + hora (verde). Se houver jornada de outro dia, mostra tambem o aviso. |
| Perfil comum | Segue o fluxo de login do totem (entra no sistema), como antes. |
| Regras da jornada | Quando a regra nao permite (intervalo aberto, jornada de outro dia bloqueada, cooldown etc.), mostra a mensagem amigavel e volta ao totem. |

---

## 3. Pre-requisitos e configuracao

### 3.1 Cadastro da biometria

- Tela: **Cadastros > Facial** (`armazem_paraiba/cadastro_facial.php`).
- Captura no minimo 3 amostras pela webcam; o sistema grava a **media dos descritores** (vetor de
  128 floats) em `user.user_tx_face_descriptor`. Nao guarda imagem.
- O cadastro e por usuario do sistema (o funcionario precisa ter usuario vinculado a entidade).
- O mesmo threshold de 0.38 e usado no cadastro para impedir biometrias duplicadas.

### 3.2 Perfil de acesso

- Tela: **Cadastros > Perfil de Acesso** (`armazem_paraiba/cadastro_perfil_acesso.php`).
- Marque **"Registrar Ponto com Facial"** (so fica disponivel se **"Registrar Ponto"** tambem
  estiver marcado).
- Somente perfis marcados conseguem registrar pelo totem. Perfil nao marcado recebe a mensagem
  "Seu perfil nao esta habilitado para o registro facial. Use o login." e o botao de login.
- Persistencia: coluna `perfil_acesso.perfil_tx_pontoFacial` (`sim`/`nao`, padrao `nao`).

### 3.3 Regra de jornada (mesmo dia x dia seguinte)

- **Mesmo dia**: com jornada aberta, **nao abre outra jornada** — a jornada precisa estar
  fechada para iniciar uma nova no mesmo dia. Esse bloqueio e sempre aplicado.
- **Dia seguinte**: se a jornada aberta for de um dia anterior, o comportamento depende do
  parametro da jornada **"Permitir abrir nova jornada com a do dia anterior em aberto?"**
  (`para_tx_permitirNovaJornadaDiaAnterior`, padrao **Sim**):
  - **Sim**: abre a nova jornada; a anterior fica em aberto para ajuste do gestor e o totem
    mostra o aviso "Atencao: voce tinha uma jornada de outro dia em aberto. Ela ficou para
    ajuste do gestor.".
  - **Nao**: nao abre; o totem mostra "Voce tem uma jornada de outro dia em aberto. Procure o
    gestor para ajustar." e o botao JORNADA nao aparece na tela web.
- Configuracao: **Cadastros > Parametro**, secao **"Acordo Sindical"** (editavel somente quando
  "Acordo Sindical" = Sim).

### 3.4 Horario do almoco pre assinalado

- Tela: **Cadastros > Parametro**, secao **"Acordo Sindical"** (editavel somente quando
  "Acordo Sindical" = Sim): **"Horario do almoco pre assinalado"**
  (`para_tx_almocoInicio` / `para_tx_almocoFim`), padrao **11:00 as 14:00**. Os horarios so ficam
  habilitados para alteracao quando **"Almoco pre assinalado?" = Sim**.
- Usado quando **"Almoco pre assinalado?" = Sim**: no Fim Jornada, o sistema registra o Inicio e
  o Fim Almoco nesses horarios (quando a jornada atravessa a janela).
- A flag **"Almoco pre assinalado?"** (`para_tx_almocoPreAssinalado`, padrao Nao) faz o
  funcionario registrar apenas Entrada e Fim Jornada; o almoco entra automaticamente (secao 4.5).
- A flag **"Almoco pre assinalado somente apos 6h de jornada continua"**
  (`para_tx_almocoPreJornada6h`, padrao **Sim**, so editavel com "Almoco pre assinalado?" = Sim)
  exige o cumprimento de 6 horas continuas de jornada (Entrada ate a batida) para o lancamento
  automatico do almoco (secao 4.5). Desligado o "Almoco pre assinalado?", o valor e preservado.

### 3.5 Camera

- Requer **HTTPS** (ou localhost). O navegador so libera a camera em conexao segura.
- O reconhecimento usa `face-api.js` (CDN) e os modelos locais em `face_models` na raiz da
  aplicacao. Sem internet o `face-api.js` nao carrega (os modelos sao locais).

---

## 4. Regras ativas

### 4.1 Identificacao facial

- O descritor enviado precisa ser um array JSON com **no minimo 64 floats** (na pratica 128).
- Comparacao **1:N** contra usuarios com `user_tx_status = 'ativo'`, entidade ativa e biometria
  cadastrada. Distancia euclidiana; vence a menor distancia.
- **Threshold: 0.38**. Acima disso: "Rosto nao reconhecido".
- **Prova de vida (anti-spoofing) no navegador**:
  - micro-movimento do nariz: minimo de 8 frames analisados e variancia media >= 0.4;
  - score do detector de rosto >= 0.85;
  - foto/tela estatica e bloqueada ("Foto detectada. Use seu rosto real.").

### 4.2 Permissao

- Somente perfis com `perfil_tx_pontoFacial = 'sim'` (via `usuario_perfil` ativo) podem registrar.
- A verificacao usa o `user_nb_id` identificado pela face (helper `perfilUsaPontoFacial()` em
  `check_permission.php`).

### 4.3 Antifraude e limites

- **Cooldown de 30 segundos por matricula**: se o ultimo registro do funcionario tem
  `pont_tx_dataCadastro` ha menos de 30s, o registro e bloqueado
  ("Registro efetuado ha instantes. Aguarde alguns segundos."). O cooldown roda antes da
  validacao de sequencia.
- **3 falhas** (rosto, perfil ou camera) liberam o botao **ENTRAR COM LOGIN**.
- Nao existe limite por IP nem token CSRF (pagina publica por natureza). A prova de vida e
  executada no cliente.

### 4.4 Sequencia da jornada (`conferirErroPonto`)

Valida a ordem dos registros (mesma regra da tela normal de ponto e dos ajustes):

- **Sem jornada aberta** (ou ultimo ponto de jornada e Fim): aceita apenas **Inicio Jornada**.
- **Jornada aberta** (ultimo ponto de jornada e Inicio):
  - **Inicio Jornada**: bloqueado no mesmo dia; liberado no dia seguinte (a anterior permanece
    aberta e o totem mostra um aviso).
  - **Inicio Almoco** e **Fim Jornada**: permitidos se nao houver intervalo aberto.
  - **Fim Almoco**: bloqueado se nao houver almoco aberto.
- **Intervalo aberto** (ultimo ponto impar, ex.: 3): aceita apenas o fechamento correspondente
  (3 -> 4). Abrir outro intervalo ou fechar a jornada e bloqueado.
- **Ultimo ponto e fim de intervalo** (4, 6, 8, 10, 12) com jornada aberta: inicio de almoco e
  fim de jornada sao aceitos; inicio de jornada segue a regra de data acima.
- **Mesmo minuto**: se ja existe ponto no mesmo minuto, o novo recebe +1 segundo para manter a
  ordenacao.

### 4.5 Decisao automatica do tipo (totem sem botoes)

A decisao e por **quantidade de pontos** da jornada aberta (a hora nao decide), pelo helper
`proximoMacroTotem()`:

| Estado | Tipo registrado |
|---|---|
| Sem jornada aberta | **Inicio Jornada (1)** |
| Jornada aberta com **1 ponto** (so a entrada) | **Inicio Almoco (3)** |
| Jornada aberta com **2 pontos** | **Fim Almoco (4)** |
| Jornada aberta com **3 pontos** | **Fim Jornada (2)** |
| Jornada aberta com **4+ pontos** | **Nao registra** ("registros alem do esperado. Procure o RH.") |
| **Almoco pre assinalado = Sim** + jornada com 1 ponto | **Fim Jornada (2)** com o almoco inserido automaticamente (abaixo) |
| Jornada aberta de **dia anterior** + parametro "Permitir nova jornada" = Sim | **Inicio Jornada (1)** (nova; a anterior fica aberta e o resultado mostra um aviso) |
| Jornada aberta de **dia anterior** + parametro "Permitir nova jornada" = Nao | **Nao registra** ("jornada de outro dia em aberto. Procure o gestor.") |
| Jornada de dia anterior com exatamente **2 pontos** | O 2º ponto e convertido em **Fim Jornada** (sem 3º ponto) e a nova jornada abre normalmente |
| Intervalo aberto que nao e almoco (5/7/9/11) | **Nao registra** ("intervalo em aberto. Procure o RH.") |

**Almoco pre assinalado (flag Sim):** o funcionario faz apenas Entrada e Fim Jornada. No Fim
Jornada:

- Fim **depois** da janela: insere o **Inicio Almoco** e o **Fim Almoco** nos horarios da janela;
- Fim **dentro** da janela: insere o **Inicio Almoco** no inicio da janela e o **Fim Almoco** no
  horario da propria batida (ex.: janela 10:00-11:00 e fim as 10:30 -> almoco 10:00-10:30);
- Fim **antes** da janela: nao insere o almoco e finaliza com a observacao "Saida antes do
  almoco pre assinalado." na justificativa.

Com a flag **"Almoco pre assinalado somente apos 6h de jornada continua"**
(`para_tx_almocoPreJornada6h`, padrao **Sim**) ligada, o almoco automatico so e inserido se a
jornada continua (Entrada ate a batida do Fim Jornada) tiver cumprido **6 horas**. Abaixo disso o
Fim Jornada e gravado normalmente, sem o par de almoco.

Os pontos automaticos usam a descricao `Almoco pre assinalado`.

- Depois de decidir, o registro passa pelas mesmas validacoes do `conferirErroPonto()`
  (secao 4.4) e pela regra de data (secao 4.6).
- O tipo escolhido pelo cliente (`idMacro`) continua aceito pelo endpoint; sem ele, o modo
  automatico entra em acao.

### 4.6 Jornada aberta: mesma dia x dia seguinte

Resumo da regra implementada em `conferirErroPonto`:

| Situacao | Parametro "Nao" | Parametro "Sim" |
|---|---|---|
| Jornada aberta iniciada **hoje** + Inicio Jornada | Bloqueia | Bloqueia |
| Jornada aberta iniciada **ontem ou antes** + Inicio Jornada | Bloqueia | Libera (anterior fica aberta; aviso no totem) |
| Jornada fechada / sem jornada + Inicio Jornada | Libera | Libera |

- O bloqueio do mesmo dia vale para qualquer estado do ultimo ponto (inclusive quando o ultimo
  ponto e um fim de intervalo, ex.: apos o almoco).
- A data considerada e a data de inicio da jornada aberta (ultimo ponto de jornada tipo 1).
- Na tela web (`batida_ponto.php`) o botao JORNADA so aparece quando a jornada aberta e de um
  dia anterior; no mesmo dia aparece apenas o aviso "Voce tem uma jornada em aberto desde ...".
- No totem o tipo e decidido pelo servidor (secao 4.5); quando a regra nao permite, aparece uma
  mensagem amigavel e nada e gravado.

### 4.7 O que e gravado

No registro do ponto:

| Campo | Valor |
|---|---|
| `pont_tx_descricao` | `Facial` |
| `pont_tx_faceDistancia` | Distancia apurada (DECIMAL 6,4) - auditoria |
| `pont_tx_justificativa` | `Registro pelo totem facial` |
| `pont_tx_tipo` / `pont_tx_tipoOriginal` | Codigo do macro escolhido |
| `pont_nb_userCadastro` | Usuario identificado pela face |
| Latitude/Longitude | Nao grava (diferente da batida web/app) |

Mensagens de erro traduzidas para o funcionario (exemplos):

| Erro interno | Mensagem no totem |
|---|---|
| Jornada aberta nao encontrada. | Voce nao tem jornada aberta. Toque em INICIO JORNADA. |
| Jornada aberta ja existente. | Voce ja tem uma jornada aberta. Toque em FIM JORNADA ou solicite um ajuste. |
| Intervalo aberto nao encontrado. | Voce nao tem almoco aberto. Toque em INICIO ALMOCO primeiro. |
| Nao e possivel fechar com um intervalo aberto. | Voce tem um intervalo aberto. Finalize-o antes de encerrar a jornada. |

---

## 5. Retorno ao totem apos login (fallback)

Depois de 3 falhas, o botao **ENTRAR COM LOGIN** aponta para `index.php?origem=totem`
(tela de login atual). A origem e propagada pelo login (manual e facial) e gravada na sessao.

Regra do retorno no logout:

- Volta para `totem_ponto.php` somente quando **as duas condicoes** forem verdadeiras:
  1. o login foi iniciado pelo link do totem (`origem_totem`); e
  2. o perfil ativo do usuario tem **"Registrar Ponto com Facial"** = Sim.
- Todos os demais casos (login normal, perfis sem a marcacao, administradores) continuam
  voltando para a tela de login (`../index.php`), como antes.

---

## 6. Estrutura tecnica

### 6.1 Arquivos

| Arquivo | Papel |
|---|---|
| `armazem_paraiba/totem_ponto.php` (novo) | Pagina kiosk + endpoint AJAX `registrarPontoFacial()` (mesmo arquivo, `acao=registrarPontoFacial`). |
| `armazem_paraiba/funcoes_ponto.php` | `conferirErroPonto()` (sequencia + regra de data), `jornadaAbertaDesdePontos()` e `proximoMacroTotem()` (decisao automatica). |
| `armazem_paraiba/check_permission.php` | Helper `perfilUsaPontoFacial()`. |
| `armazem_paraiba/conecta.php` | Migracoes das colunas novas. |
| `armazem_paraiba/cadastro_perfil_acesso.php` | Checkbox "Registrar Ponto com Facial". |
| `armazem_paraiba/cadastro_parametro.php` | Janela de almoco do totem e "Permitir abrir nova jornada com a do dia anterior em aberto?". |
| `armazem_paraiba/cadastro_facial.php` | Cadastro da biometria (ja existente). |
| `index.php` / `login.php` (raiz) | Modo Totem unificado: identifica a pessoa e registra o ponto (perfil habilitado) ou segue o login normal do totem. |
| `armazem_paraiba/index.php` / `logout.php` | Propagacao da origem totem (pagina avulsa) e retorno no logout. |
| `armazem_paraiba/batida_ponto.php` | Botao/aviso de nova jornada na tela normal de ponto. |

### 6.2 Banco de dados

- `perfil_acesso.perfil_tx_pontoFacial ENUM('sim','nao') NOT NULL DEFAULT 'nao'`
- `ponto.pont_tx_faceDistancia DECIMAL(6,4) NULL`
- `parametro.para_tx_almocoInicio TIME NULL DEFAULT '11:00:00'`
- `parametro.para_tx_almocoFim TIME NULL DEFAULT '14:00:00'`
- `parametro.para_tx_permitirNovaJornadaDiaAnterior ENUM('sim','nao') NOT NULL DEFAULT 'sim'`
- `parametro.para_tx_almocoPreAssinalado ENUM('sim','nao') NOT NULL DEFAULT 'nao'` (almoço automático)
- `parametro.para_tx_almocoPreJornada6h ENUM('sim','nao') NOT NULL DEFAULT 'sim'` (exige 6h contínuas para o almoço automático)
- `user.user_tx_face_descriptor LONGTEXT` (ja existia, guarda o vetor facial)

As colunas novas sao criadas automaticamente pelas migracoes de `conecta.php` quando qualquer
pagina da empresa e carregada (inclusive o proprio totem). Perfis existentes assumem `nao`
(funcionalidade desligada).

### 6.3 Endpoint

- `POST totem_ponto.php` com `acao=registrarPontoFacial` e `descritor` (JSON). O `idMacro`
  (1, 3, 4 ou 2) e **opcional**: sem ele o servidor decide o tipo automaticamente (secao 4.5).
  A resposta e JSON: `{ok, nome, macro, hora, aviso, msg}` ou
  `{ok:false, msg, distancia, comparados, login?}`. O `aviso` aparece quando uma jornada de
  outro dia ficou em aberto (a nova foi iniciada mesmo assim). A `distancia` (menor distancia
  encontrada) e `comparados` (quantos usuarios com biometria entraram na comparacao) aparecem no
  erro de rosto nao reconhecido para facilitar o suporte.
- O endpoint e publico (sem sessao de usuario), mas exige perfil marcado para gravar.
- A identificacao consulta apenas `user` + `entidade` (nao depende da tabela `parametro`); a
  janela de almoco e o "permitir nova jornada" sao buscados depois, com fallback caso a coluna
  nao exista.
- Se a consulta de biometrias falhar, o erro e registrado no log e a resposta e
  "Erro interno ao consultar biometrias. Procure o RH." (em vez de "Rosto nao reconhecido").

---

## 7. Como habilitar em uma empresa (passo a passo)

1. **Cadastrar a biometria**: Cadastros > Facial > selecionar a empresa e o funcionario >
   capturar no minimo 3 vezes > salvar.
2. **Liberar o perfil**: Cadastros > Perfil de Acesso > marcar "Registrar Ponto" e
   "Registrar Ponto com Facial" > salvar. Garantir que os funcionarios estejam vinculados a esse
   perfil (Cadastros > Permissoes de usuarios).
3. **Regra de jornada e almoco**: Cadastros > Parametro > jornada usada pela empresa, secao
   "Acordo Sindical" (campos so editaveis com Acordo = Sim): "Permitir abrir nova jornada com a
   do dia anterior em aberto?" (padrao Sim), "Horario do almoco pre assinalado" (padrao 11:00 as
   14:00, so editavel com "Almoco pre assinalado?" = Sim) e "Almoco pre assinalado?" (padrao Nao).
   A flag "Almoco pre assinalado somente apos 6h de jornada continua" vem marcada por padrao > salvar.
4. **Ativar o Modo Totem**: abrir a tela de login (`.../index.php`), clicar em **Modo Totem**,
   selecionar a empresa (fica salva no dispositivo) e usar a tela cheia.

---

## 8. Limitacoes e observacoes

- Camera exige HTTPS (ou localhost); sem isso o navegador bloqueia o acesso.
- `face-api.js` vem de CDN; os modelos sao locais (`face_models`). Sem internet o
  reconhecimento nao carrega.
- Nao ha registro de imagem do rosto, apenas o vetor biometrico e a distancia da validacao.
- A checagem de perfil usa `LIMIT 1` (primeiro perfil ativo do usuario); usuarios com mais de um
  perfil ativo devem ter a marcacao no perfil retornado.
- O totem registra apenas os 4 tipos (jornada e almoco); descanso, espera e repouso continuam
  disponiveis somente na tela normal de ponto/app. Se houver um intervalo desses aberto, o totem
  nao grava e orienta procurar o RH/gestor.
- O app (webservice `begin_journey`) possui validacao propria e **nao** foi alterado por esta
  funcionalidade.
- A prova de vida e executada no cliente; um atacante determinado pode tentar contorna-la. O
  cooldown e a permissao por perfil reduzem o risco, mas nao ha limite por IP.

---

## 9. Verificacao

- Regressao do modulo de ponto:
  `docker compose exec apache php /var/www/html/braso/armazem_paraiba/testes_regressao/run.php`
- Checklist manual:
  1. perfil sem a marcacao: o totem segue o login normal no sistema;
  2. perfil com a marcacao: o totem registra o ponto automaticamente;
  3. toque na tela: camera abre direto (sem botoes);
  4. sem jornada: reconhecer e registrar Inicio Jornada automaticamente;
  5. regra por quantidade: 2ª batida = Inicio Almoco; 3ª = Fim Almoco; 4ª = Fim Jornada;
  6. "Almoco pre assinalado" = Sim: 2ª batida registra Fim Jornada e insere o almoço na janela (somente com a flag de 6h desligada ou jornada continua >= 6h); se o Fim Jornada for antes da janela, finaliza com observacao;
  7. jornada aberta de dia anterior: com parametro Sim registra nova jornada e mostra o aviso; com Nao orienta o gestor; jornada de 2 pontos do dia anterior: 2º ponto vira Fim Jornada e a nova abre normal; intervalo nao-almoco aberto: mensagem, sem gravacao;
  8. conferir no banco `pont_tx_descricao = 'Facial'` e `pont_tx_faceDistancia` preenchida;
  9. pagina avulsa do totem (URL antiga): login pelo fallback e logout voltam ao totem apenas com perfil marcado.
