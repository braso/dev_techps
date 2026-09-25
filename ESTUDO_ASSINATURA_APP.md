# Estudo de viabilidade: assinatura eletrônica no app (notificações)

Data: 2026-09-25. Base analisada: `dev_techps/armazem_paraiba` (código + banco `techpsjornada_dev` no Docker) e app React Native `APP TECH PS` (v1.1.011).

## 1. O que já existe no sistema web

### Módulo de assinatura: `armazem_paraiba/assinatura/`
| Arquivo | Papel |
|---|---|
| `processar_envio.php` | Cria a solicitação, gera tokens e envia o 1º e-mail |
| `assinar_via_link.php` | **Página pública de assinatura** (sem login, só token). Mostra PDF, pede CPF e RG, checkbox de aceite |
| `assinar.php` | POST que valida CPF/RG contra `entidade` e grava a assinatura |
| `email_helper.php` | SMTP, `enviarEmailProximo`, `assinatura_getBaseUrl`, **`assinatura_getNotificacoesCounts($entiId)`** (contador de pendências pronto) |
| `email_config.php` | SMTP (Titan) e `BASE_URL_ASSINATURA` |
| `pendentes.php` | Lista de pendências do funcionário logado na web (query reaproveitável) |
| `renovar_link.php` | Renova prazo, gira o token e reenvia e-mail |
| `script.js` | Carimba o PDF no navegador (pdf-lib), calcula hash, POST para `assinar.php` |

### Tabelas (já existem no banco dev)
- `solicitacoes_assinatura`: documento (caminho, `id_documento`, `expires_at`, `status` pendente/em_progresso/assinado, `modo_envio`).
- `assinantes`: um por signatário. **`enti_nb_id`** (entidade/motorista), `token` (64 hex, único, é o que vai na URL), `ordem`, `status` pendente/assinado/dispensado.
- `assinatura_eletronica`: log de auditoria (cpf, rg, ip, hash, lat/long).
- `signatarios_externos`: pessoas sem cadastro.
- `notificacoes`: só é referenciada em um INSERT no `renovar_link.php`; **não existe CREATE TABLE e não existe no banco dev**.

### O link que vai por e-mail
```
{BASE_URL_ASSINATURA}/assinar_via_link.php?token={assinantes.token}
```
Ex.: `http://localhost/braso/armazem_paraiba/assinatura/assinar_via_link.php?token=ab12...`
A página valida: prazo (`expires_at`), já assinado, ordem do signatário. CPF/RG conferidos com `entidade.enti_tx_cpf` / `enti_tx_rg`.

### Ponte usuário do app -> signatário
- App loga com `user` (JWT carrega `user_id`).
- `user.user_nb_entidade = entidade.enti_nb_id = assinantes.enti_nb_id`.
- Todos os motoristas ativos consultados têm CPF e RG preenchidos em `entidade`.
- Já há 1 solicitação de teste no banco (ASO.pdf, entidade 16 = usuário "Teste" 1010, assinada).

### Webservice do app: `armazem_paraiba/ws/`
- `index.php`: roteador manual (`switch` + whitelist de rotas).
- `endpoints.php`: handlers; `lib.php`: `validate_token`, `get_data`, `insert_data` (PDO).
- Padrão de novo endpoint: adicionar na whitelist, `case` no switch, função em `endpoints.php`.
- Testado local: `http://localhost/braso/armazem_paraiba/ws/...` responde.

## 2. O que precisa entrar na pasta `ws`

### 2.1 Endpoint `GET /ws/signatures/{userId}` (lista pendências)
Retorna JSON com os documentos que o usuário precisa assinar agora (mesma regra do `pendentes.php`/`getNotificacoesCounts`: status do assinante ≠ assinado, é a menor ordem pendente, solicitação pendente/em_progresso, não expirada).

Campos: `id` (assinantes.id), `id_documento`, `titulo` (nome_arquivo_original ou tipo), `data_solicitacao`, `expires_at`, `funcao`, `ordem`, `total_signatarios`, `url` (link completo de assinatura), `lida` (ver 2.3).

```sql
SELECT a.id, a.token, a.funcao, a.ordem, a.status,
       s.id AS solicitacao_id, s.id_documento, s.nome_arquivo_original,
       s.data_solicitacao, s.expires_at, s.status AS status_solicitacao,
       t.tipo_tx_nome AS tipo
FROM assinantes a
JOIN solicitacoes_assinatura s ON s.id = a.id_solicitacao
JOIN user u ON u.user_nb_entidade = a.enti_nb_id
LEFT JOIN tipos_documentos t ON t.tipo_nb_id = s.tipo_documento_id
WHERE u.user_nb_id = ?
  AND LOWER(TRIM(a.status)) <> 'assinado'
  AND a.ordem = (SELECT MIN(a2.ordem) FROM assinantes a2
                 WHERE a2.id_solicitacao = a.id_solicitacao
                   AND LOWER(TRIM(a2.status)) <> 'assinado')
  AND s.status IN ('pendente','em_progresso')
  AND (s.expires_at IS NULL OR s.expires_at > UTC_TIMESTAMP())
ORDER BY s.data_solicitacao DESC
```
A URL é montada no servidor: `rtrim(BASE_URL_ASSINATURA,'/') . '/assinar_via_link.php?token=' . $token`.
Observação: `assinatura_getBaseUrl()` usa o diretório da requisição atual; chamada de dentro do `ws` ela devolveria `/ws`. No endpoint usar a constante `BASE_URL_ASSINATURA` (ou uma nova `URL_BASE` do `.env`), que em produção precisa apontar para `https://techpsgj.com.br/gestaodeponto/<empresa>/assinatura`.

### 2.2 Endpoint `GET /ws/signatures/{userId}/count` (badge)
Retorna `{ "pendentes": N, "nao_lidas": M }`. Barato, chamado ao abrir o app e ao voltar do background.

### 2.3 Marcação de "lida" (opcional, recomendado)
Para o sino mostrar "nova" só uma vez: tabela nova `assinatura_app_leitura` (`assinante_id`, `user_nb_id`, `lida_em`) ou uma coluna `app_lida_em DATETIME NULL` em `assinantes`. Endpoint `PUT /ws/signatures/{assinanteId}/read`.
Alternativa sem banco: o app guarda localmente os ids já vistos. Mais simples, mas perde ao reinstalar.

### 2.4 Não precisa mudar
- `assinar_via_link.php` e `assinar.php`: a assinatura continua acontecendo na página web existente, com a mesma validação de CPF/RG, hash e auditoria. O app só leva o usuário até lá.
- E-mail continua sendo enviado normalmente; o app é um segundo canal.

### 2.5 Ajustes de infraestrutura
- Criar a tabela `notificacoes` que o `renovar_link.php` já tenta usar (hoje esse INSERT falha silenciosamente), ou remover a referência.
- Garantir `BASE_URL_ASSINATURA` correta por empresa em produção (hoje o fallback é localhost).

## 3. O que muda no app

### 3.1 Redux / API
- `src/redux/actions/Signatures.ts` + `reducers/Signatures.ts`: `fetchSignatures(userId)`, `fetchSignaturesCount`, `markSignatureRead`. Cache em AsyncStorage para mostrar a lista offline (o link só funciona online).
- Chamar `fetchSignaturesCount` no login, no `useFocusEffect` da Home e quando o app volta para `active` (o `NetworkManager` já tem esse gancho).

### 3.2 Telas
- **Home**: card "Notificações" (ícone de sino) com badge numérico. Já existem cards "Suporte" e "Contato" sem ação; um deles pode virar Notificações.
- **Tela Notificações** (`src/screens/Notifications`): lista de documentos pendentes (título, data, prazo, status "novo"/"visto"). Toque abre a assinatura.
- Item já assinado some da lista após o próximo `fetch` (o servidor muda o status).

### 3.3 Abrir a assinatura: duas opções
| | Navegador (Linking.openURL) | WebView dentro do app |
|---|---|---|
| Esforço | Mínimo (API já existe no RN) | Adicionar `react-native-webview`, tela nova, rebuild nativo |
| Experiência | Sai do app, volta pelo botão do sistema | Fica no app, header próprio, botão voltar |
| Compatibilidade da página | 100% (Chrome completo) | Página usa Tailwind CDN, pdf-lib, SweetAlert, iframe de PDF e geolocalização. O iframe de PDF em WebView Android **não renderiza PDF nativamente** (Chrome mostra, WebView não). Precisaria trocar o iframe por PDF.js ou abrir o PDF em tela separada |
| Geolocalização | Chrome pede permissão | Precisa `geolocationEnabled` + permissão já concedida pelo app |
| Detectar conclusão | Não detecta; app atualiza lista no retorno | Pode interceptar a URL/JSON de sucesso e atualizar na hora |

**Recomendação:** fase 1 com **navegador** (entrega rápida, zero risco na página de assinatura). Fase 2, se quiser, WebView com ajuste do visualizador de PDF na página.

### 3.4 Aviso ao usuário
- Notificação local Android via `NotificationModule.showNotification` (já existe) quando o `count` aumentar em relação ao último valor salvo. Funciona só com o app aberto ou ao voltar do background.
- Push real (aviso com app fechado) exigiria FCM: projeto Firebase, `google-services.json`, token por dispositivo salvo no servidor e envio no `processar_envio.php`. Fica como fase 3.

## 4. Segurança e pontos de atenção
- O token do assinante já é o segredo do link; expor via `ws` exige JWT válido do próprio usuário (o endpoint filtra por `user_nb_id` do token, ignorando o `{userId}` da URL se divergir).
- Link expira (`expires_at`). O app mostra "expirado" e orienta a pedir renovação.
- A assinatura só ocorre online. Lista pode ser cacheada.
- Multi-empresa: cada tenant tem seu `ws` e seu `assinatura`; a mudança no `ws` precisa ir para todas as pastas de empresa no deploy.

## 5. Esforço estimado
| Etapa | Onde | Estimativa |
|---|---|---|
| Endpoints `signatures` (lista, count, read) + tabela de leitura | ws | 0,5 dia |
| Redux + tela Notificações + badge na Home + abrir no navegador | app | 1 dia |
| Notificação local ao detectar pendência nova | app | 0,5 dia |
| (Opcional) WebView + PDF.js na página | app + web | 1 a 2 dias |
| (Opcional) Push FCM | app + web | 2 a 3 dias |
