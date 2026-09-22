# Módulo de Treinamento

Módulo de treinamentos online com vídeos (YouTube/Vimeo/Upload), materiais de apoio, controle de acesso por perfil e acompanhamento de progresso. Desenvolvido em PHP puro (procedural) no contexto `armazem_paraiba`.

## Estrutura de Arquivos

```
armazem_paraiba/treinamento/
├── cadastro_treinamento.php    # Admin: CRUD de treinamentos + atribuições
├── treinamento_assistir.php    # Usuário: listagem "Meus Treinamentos"
├── treinamento_player.php      # Usuário: player com proteções anti-adiantamento
├── certificado.php             # Geração automática do certificado de conclusão
└── uploads/materiais/{id}/     # Arquivos de material de apoio por treinamento
```

## Tabelas (criadas automaticamente no `conecta.php`)

| Tabela | Finalidade |
|---|---|
| `treinamento` | Cadastro do treinamento (título, vídeo, duração em segundos, perfis permitidos em JSON, status, obrigatoriedade, datas) |
| `treinamento_material` | Material de apoio (PDF/imagem) vinculado ao treinamento |
| `treinamento_questao` | Banco de questões (desativado temporariamente) |
| `treinamento_progresso` | Progresso individual: tempo assistido (segundos), % assistida, conclusão, avaliação |
| `treinamento_atribuicao` | Atribuição individual de acesso (legado) |
| `treinamento_bloqueio` | Bloqueio individual: usuário desmarcado não vê o treinamento mesmo com perfil |
| `treinamento_log` | Auditoria de eventos (acesso, edição, avaliação) |
| `treinamento_certificado` | Controle do certificado gerado (um por treinamento/usuário): status, caminho, documento do funcionário e solicitação de assinatura |

## Fluxo de Acesso

### Admin (cadastro_treinamento.php)

1. **Dados Gerais**: título, descrição, tipo (DSS/Treinamento), tipo de treinamento, duração (`mm:ss`, armazenada em segundos), validade, datas de publicação/liberação, tipo de vídeo (YouTube/Vimeo/Upload Local), URL do vídeo, obrigatório e **Perfis de Acesso Permitidos**.
2. **Material de Apoio**: envio de PDF ou imagem que aparece junto ao treinamento.
3. **Atribuições**: lista os funcionários **agrupados por perfil**, todos **marcados por padrão**. Desmarcar um funcionário cria um **bloqueio individual** (registrado em `treinamento_bloqueio`) - ele não verá o treinamento mesmo que seu perfil esteja permitido.

### Usuário comum (treinamento_assistir.php)

- Lista apenas os treinamentos cujo perfil do usuário está na lista de perfis permitidos **e** não está bloqueado individualmente.
- Status por card: **Não Iniciado** → **Em Andamento** (ao abrir o player) → **Concluído** (ao atingir 100%).

### Player (treinamento_player.php)

- **Progresso** = posição máxima alcançada no vídeo ÷ duração de referência (menor entre a duração real do vídeo e a duração cadastrada).
- Retomada: ao reabrir, o vídeo volta ao ponto salvo.
- Envio do progresso a cada 5s via `$.post` + `sendBeacon` no `pagehide`/`beforeunload` (salva ao fechar).

## Proteções Anti-Adiantamento (mesmas regras do plataforma_dss)

### Bloqueio de avanço
- **Upload (HTML5)**: controles nativos desabilitados (Play/Pausa/Mudo customizados); eventos `seeking`/`timeupdate` revertem qualquer avanço além do último ponto; teclado bloqueado (setas, espaço, J/K/L); scroll e menu de contexto bloqueados.
- **YouTube**: timer de 1s via YouTube IFrame API detecta pulos > 2s e faz `seekTo` de volta ao ponto permitido. Embed criado pelo `YT.Player` com `origin` automático.
- **Vimeo**: mesma proteção via Player API (`setCurrentTime`).
- **Servidor**: limite de avanço de 10s por request, exceto quando o vídeo é concluído (100%).

### Bloqueio de velocidade
- **Upload**: `Object.defineProperty(video, 'playbackRate', { configurable: false })` - a propriedade sempre retorna 1.0 e rejeita atribuição (não pode ser contornado nem via DevTools); + listeners `ratechange` e `MutationObserver`.
- **YouTube/Vimeo**: verificação a cada 500ms - qualquer velocidade diferente de 1x é restaurada.

### Progresso anti-fraude
- O percentual é calculado pela **posição máxima assistida** (`ultimoTempo`), não pelo tempo decorrido. Voltar o vídeo não "ganha" progresso; a barra fica no último ponto máximo e só completa 100% quando o vídeo termina de verdade.
- Duração de referência: `min(duração real do vídeo, duração cadastrada)`.

## Conclusão

- Ao atingir 100% (vídeo terminado), o servidor marca `trepr_nb_concluido = 1` e grava a data de conclusão automaticamente - **sem depender de avaliação** (banco de questões desativado temporariamente).
- Para séries, a linha geral de progresso é marcada como concluída quando **todos os episódios** estão aprovados.
- A **emissão do certificado** tem regra própria e exige também a aprovação na avaliação quando houver questões (ver seção Certificados).

## Certificados

Gerados automaticamente quando o treinamento é **concluído e aprovado** (ao abrir o player, se ainda não existir; o acompanhamento também possui o botão **Gerar Certificados** para reprocessar todos os concluídos).

Regra de emissão (`treinamento_certificado_estaConcluido`):
- **Vídeo único**: exige o vídeo finalizado (`trepr_nb_concluido = 1`) **e** aprovação na avaliação (`trepr_nb_avaliacao_aprovada = 1`) quando o treinamento tiver questões ativas. Sem questões cadastradas, basta o vídeo finalizado.
- **Série**: exige **todos os episódios aprovados** (a aprovação do episódio já pressupõe o vídeo assistido). O PDF lista cada episódio com título, duração, data de conclusão e aproveitamento, além do total de carga horária.

1. **Tipo de documento**: o certificado usa o tipo de documento ativo chamado `Certificado`/`Certificados` (criado em `cadastro_tipo_doc.php`). O cabeçalho/rodapé configurados em `documentos/configurar_layout.php` são aplicados ao PDF (mesmo padrão TCPDF do módulo Documentos).
   - **Logo**: usa `tipo_tx_logo` do tipo quando configurado; senão, usa a logo do cliente (`imagens/logo_topo_cliente.png`) à esquerda e a logo da empresa do funcionário (`empresa.empr_tx_logo`) à direita, como nos demais PDFs do sistema.
   - **Visual**: moldura dupla, título e cabeçalho na cor do sistema e código de autenticação no rodapé.
2. **Campos**: se o tipo tiver campos dinâmicos configurados (`camp_documento_modulo`), os valores são preenchidos automaticamente conforme o rótulo (nome, CPF, matrícula, empresa, treinamento, carga horária, data, nota, instrutor, validade, código etc.). Sem layout configurado, usa o conjunto padrão de campos.
3. **Assinatura = Não** (`tipo_tx_assinatura = 'nao'`): o PDF é gerado e salvo em `arquivos/Funcionarios/{entidadeId}/`, e registrado em `documento_funcionario` (aba **Documentos** do funcionário em `cadastro_funcionario.php`).
4. **Assinatura = Sim** (`tipo_tx_assinatura = 'sim'`): o PDF é enviado ao módulo de assinatura via `assinatura/integracao/assinatura_integracao.php` (assinante único = funcionário, `salvar_documento_funcionario = 'sim'`). Após a assinatura, o módulo existente salva o PDF assinado na **mesma pasta** e registra em `documento_funcionario` com `docu_tx_assinado = 'sim'`.
5. **Vencimento**: quando o tipo tem vencimento `sim`, a data é calculada com base em `trei_nb_dias_validade` do treinamento.
6. **Status** (`treinamento_certificado.trece_tx_status`): `gerado`, `aguardando_assinatura`, `assinado` ou `erro`. O status de assinatura é sincronizado automaticamente a partir de `solicitacoes_assinatura` ao abrir o player.
7. **Constante opcional**: definir `TREINAMENTO_CERTIFICADO_ENVIAR_EMAIL` como `false` desativa o e-mail de convite de assinatura (útil em homologação).

## Pontos de Atenção (bugs conhecidos e contornos)

1. **Dispatcher do `funcoes.php`**: roda durante o `include` do `conecta.php` e, como o PHP registra as funções do arquivo antes de executar o código, ele já encontra `index()` e a chama com `exit` antes de qualquer código no nível superior do arquivo. **Qualquer código executável deve ficar DENTRO do `index()`** (ex.: o handler AJAX `listar_usuarios_perfis`).
2. **POST com `acao`**: é interceptado pelo dispatcher. O módulo usa `acao_player` (player) e `salvar`/`salvar_questao` (cadastro) para contornar.
3. **`insertInto` do `funcoes.php`**: trata campos `_tx_` e `_dt_` como string (`s`) e o restante como double (`d`). Correção aplicada para `_dt_` (campos de data precisam de `s`).
4. **Caminhos de download**: usar URL absoluta (`$_ENV["URL_BASE"] . $CONTEX["path"] . "/treinamento/uploads/..."`), pois links relativos duplicam a pasta `treinamento/`.
5. **JSON nos perfis**: `trei_tx_tipo_usuario_permitido` é TEXT com JSON. Ao salvar, converter para inteiros (`array_map('intval', ...)`). Na consulta, verificar com `JSON_CONTAINS` tanto número quanto string (dados antigos podem estar como string). **Não usar `CAST(... AS JSON)`** - não existe no MariaDB.
6. **`trei_nb_carga_horaria`**: armazenado em **segundos** (migração: minutos × 60). O formulário usa máscara `mm:ss`.

## Referências

- Arquivo de referência das regras anti-fraude: `C:\Users\Ornilio Neto\Documents\projetos\plataforma_dss\resources\views\treinamentos\player.blade.php`
- Contexto: `dev_techps` → `armazem_paraiba` (banco `techpsjornada_dev`, MariaDB via Docker).