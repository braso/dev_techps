# Totem de Registro de Ponto com Biometria Facial

Documentacao da funcionalidade de registro de ponto por reconhecimento facial em modo totem
(kiosk), incluindo fluxo de uso, regras ativas, configuracao e detalhes tecnicos.

Commit de referencia: `5cd46c1` - "nova funcionalidade para bater ponto com facial".

---

## 1. Visao geral

O totem e uma pagina publica da empresa (sem login) onde o funcionario:

1. toca na tela ociosa (relogio) para comecar;
2. a camera abre na hora para o reconhecimento facial;
3. o sistema identifica a pessoa e **decide sozinho o tipo do registro**
   (inicio de jornada, inicio/fim de almoco ou fim de jornada);
4. recebe um alerta de sucesso ou falha e volta para a tela ociosa.

O sistema identifica a pessoa pela face (1:N), valida o perfil e a sequencia da jornada e grava
o ponto com a evidencia da distancia facial apurada.

A pagina fica na pasta da empresa, usando o banco e o `.env` da propria empresa:

```
https://<dominio>/<APP_PATH>/<pasta_da_empresa>/totem_ponto.php
```

Exemplo no ambiente de desenvolvimento:

```
http://localhost/braso/armazem_paraiba/totem_ponto.php
```

---

## 2. Fluxo de uso (tela)

| Etapa | O que acontece |
|---|---|
| Tela ociosa | Relogio, data e a mensagem "Toque na tela para registrar seu ponto". Apos 3 falhas, aparece tambem o botao **ENTRAR COM LOGIN**. |
| Camera | O toque na tela abre a camera na hora (sem botoes). O sistema aguarda rosto real, valida a qualidade e captura o descritor facial. Se ninguem aparecer em 45s, a camera fecha e volta para a tela ociosa. |
| Registro | Com a face reconhecida, o servidor decide o tipo (regra da secao 4.5) e grava o ponto. |
| Resultado | Alerta grande: verde com nome + tipo + hora (6s), ou vermelho com a mensagem amigavel (5s). Depois volta para a tela ociosa. |
| Falhas | Cada falha conta 1 tentativa. Apos 3 falhas aparece o botao **ENTRAR COM LOGIN**. O contador zera com um sucesso ou ao recarregar a pagina. |

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
- Configuracao: **Cadastros > Parametro**, secao "Outros".

### 3.4 Janela de almoco do totem

- Tela: **Cadastros > Parametro**, secao "Outros": **"Janela de almoco do totem facial"**
  (`para_tx_almocoInicio` / `para_tx_almocoFim`).
- Padrao: **11:00 as 14:00**. Dentro da janela (e sem almoco na jornada atual) o totem registra
  **Inicio Almoco**; fora dela (ou se ja almocou) registra **Fim Jornada**.

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

O servidor decide o proximo registro pelo estado do funcionario (helper `proximoMacroTotem()`):

| Estado | Tipo registrado |
|---|---|
| Sem jornada aberta | **Inicio Jornada (1)** |
| Jornada aberta de **dia anterior** + parametro "Permitir nova jornada" = Sim | **Inicio Jornada (1)** (nova; a anterior fica aberta e o resultado mostra um aviso) |
| Jornada aberta de **dia anterior** + parametro "Permitir nova jornada" = Nao | **Nao registra** ("jornada de outro dia em aberto. Procure o gestor.") |
| Almoco aberto (ultimo ponto = 3) | **Fim Almoco (4)** |
| Intervalo aberto que nao e almoco (5/7/9/11) | **Nao registra** ("intervalo em aberto. Procure o RH.") |
| Jornada aberta hoje, sem intervalo aberto, **dentro da janela de almoco** e sem almoco na jornada | **Inicio Almoco (3)** |
| Jornada aberta hoje, fora da janela **ou** ja almocou | **Fim Jornada (2)** |

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
| `index.php` / `login.php` (raiz) e `armazem_paraiba/index.php` / `logout.php` | Propagacao da origem totem e retorno no logout. |
| `armazem_paraiba/batida_ponto.php` | Botao/aviso de nova jornada na tela normal de ponto. |

### 6.2 Banco de dados

- `perfil_acesso.perfil_tx_pontoFacial ENUM('sim','nao') NOT NULL DEFAULT 'nao'`
- `ponto.pont_tx_faceDistancia DECIMAL(6,4) NULL`
- `parametro.para_tx_almocoInicio TIME NULL DEFAULT '11:00:00'`
- `parametro.para_tx_almocoFim TIME NULL DEFAULT '14:00:00'`
- `parametro.para_tx_permitirNovaJornadaDiaAnterior ENUM('sim','nao') NOT NULL DEFAULT 'sim'`
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
3. **Regra de jornada e almoco**: Cadastros > Parametro > jornada usada pela empresa >
   "Permitir abrir nova jornada com a do dia anterior em aberto?" (padrao Sim) e a
   "Janela de almoco do totem facial" (padrao 11:00 as 14:00) > salvar.
4. **Abrir o totem** na URL da empresa: `.../<pasta_da_empresa>/totem_ponto.php`.

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
  1. perfil sem a marcacao: totem recusa e oferece login;
  2. rosto desconhecido: 3 falhas e botao de login;
  3. toque na tela: camera abre direto (sem botoes);
  4. sem jornada: reconhecer e registrar Inicio Jornada automaticamente;
  5. dentro da janela de almoco: registrar Inicio Almoco; fora dela: Fim Jornada;
  6. almoco aberto: registrar Fim Almoco; intervalo nao-almoco aberto: mensagem, sem gravacao;
  7. jornada aberta de dia anterior: com parametro Sim registra nova jornada e mostra o aviso; com Nao orienta o gestor; jornada aberta no mesmo dia: inicio de jornada bloqueado (mesmo via totem);
  8. conferir no banco `pont_tx_descricao = 'Facial'` e `pont_tx_faceDistancia` preenchida;
  9. login pelo fallback e logout: retorna ao totem apenas com perfil marcado.
