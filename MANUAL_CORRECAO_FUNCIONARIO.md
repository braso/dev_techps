# Manual de Uso — Correção de Cadastro de Funcionário

| | |
|---|---|
| **Módulo** | Cadastros |
| **Página** | `correcao_funcionario.php` |
| **Perfil com acesso** | Super Administrador |
| **Versão do documento** | 1.0 |
| **Data** | 19/08/2026 |
| **Público** | Administradores do sistema |

---

## 1. Visão geral

A funcionalidade **"Corrigir Funcionário"** permite ao Super Administrador alterar dados de um funcionário que o cadastro normal **não permite mais editar**, sendo o caso mais comum a **matrícula cadastrada incorretamente**.

Ao aplicar a correção da matrícula, o sistema **propaga automaticamente a nova matrícula para todo o histórico do funcionário**:

- ✅ Batidas de ponto (registros de jornada)
- ✅ Abonos
- ✅ Endossos (banco e arquivos CSV)
- ✅ Código do funcionário em Saúde e Segurança do Trabalho
- ✅ Textos de solicitações de troca de turno
- ✅ Arquivos em disco (foto, CNH, documentos vinculados à matrícula)
- ✅ Fotos de placa registradas nas batidas
- ✅ Relatórios/JSONs dos painéis (endosso e saldo)

O que **não muda** (continua vinculado normalmente ao mesmo funcionário):

- ID interno do cadastro
- Usuário de login (senha, biometria facial, crachá RFID, perfis de acesso)
- Férias, celulares, placas, documentos, diárias, solicitações de ajuste
- Entregas de EPI e vínculos por ID

> ⚠️ **Importante:** a correção é **definitiva e irreversível**. Antes de executar, confira os dados e os avisos apresentados na tela.

---

## 2. Acesso à funcionalidade

1. Entre no sistema com um usuário **Super Administrador**.
2. No menu superior, clique em **Cadastros**.
3. Clique em **Corrigir Funcionário**.

![Acesso ao menu — Cadastros → Corrigir Funcionário](imagens_manual_correcao/01_menu.png)

> 🔒 **Restrição de perfil:** usuários que não sejam Super Administrador **não visualizam o item no menu** e, mesmo acessando a URL diretamente, são bloqueados com a mensagem *"Acesso restrito ao Super Administrador."*

---

## 3. Tela de seleção do funcionário

A tela inicial apresenta um **grid com todos os funcionários cadastrados**, com filtros de busca:

| Filtro | O que faz |
|---|---|
| **Nome** | Localiza por parte do nome |
| **Matrícula** | Localiza por parte da matrícula |
| **Empresa** | Filtra por empresa/filial |
| **Ocupação** | Motorista, Ajudante, Funcionário ou Terceirizado |
| **Status** | Ativo ou Inativo |

![Tela de seleção do funcionário](imagens_manual_correcao/02_selecao.png)

**Como localizar e abrir o funcionário:**

1. Preencha um ou mais filtros e pressione **Enter** (ou aguarde a busca).
2. Localize a linha do funcionário desejado.
3. Clique no **ícone de lápis** ✏️ na coluna de ações da linha.
4. O sistema abrirá o formulário de correção com todos os dados do funcionário.

![Botão de edição por linha](imagens_manual_correcao/03_icone_editar.png)

---

## 4. Formulário de correção

Ao abrir o formulário, duas informações importantes aparecem no topo da tela:

### 4.1 Aviso de modo correção (amarelo)

Confirma qual funcionário está sendo corrigido e informa a matrícula atual.

![Aviso de modo correção](imagens_manual_correcao/04_aviso_correcao.png)

### 4.2 Contadores de registros vinculados (azul)

Mostra **quantos registros** estão vinculados ao funcionário e serão migrados caso a matrícula seja alterada:

```
Registros vinculados a este funcionário:
  Pontos (batidas): 8.766
  Abonos: 31
  Endossos: 12
  Documentos: 3
  ...
```

![Contadores de registros vinculados](imagens_manual_correcao/05_contadores.png)

> 💡 Use esses números como conferência: se o funcionário tiver histórico, os contadores devem estar acima de zero.

---

## 5. Editando os dados

O formulário está organizado nas mesmas seções do cadastro normal:

| Seção | Principais campos |
|---|---|
| **Dados de Usuário** | E-mail, Telefone, Login, Status |
| **Dados Pessoais** | **Matrícula**, Nome, Nascimento, CPF, RG, endereço, filiação etc. |
| **Dados Contratuais** | Empresa, Setor, Cargo, Salário, Ocupação, Admissão, PIS, CTPS |
| **Jornada** | Parâmetro da jornada, jornada diária/sábado, percentuais de H.E. |
| **CNH** | Registro, categoria, cidade de emissão, validade |

![Formulário de correção](imagens_manual_correcao/06_formulario.png)

### 5.1 O campo Matrícula

- É o campo **mais comum de correção** (matrícula digitada errada no cadastro).
- Aceita até **11 caracteres**.
- Zeros à esquerda são removidos automaticamente (ex.: `01234` → `1234`).
- Não é obrigatório alterar: se você só quiser corrigir outro campo (ex.: nome), a matrícula atual permanece.

> ❌ **Não é possível** informar uma matrícula que já pertença a **outro funcionário** ou que já possua **batidas de ponto/abonos registrados** com ela. O sistema bloqueia a operação para evitar a mistura de dados de pessoas diferentes.

---

## 6. Aplicando a correção

1. Após ajustar os campos desejados, clique no botão vermelho **"Aplicar Correção"**.
2. O sistema exibe um **alerta de confirmação** com o resumo da operação:

```
Isto irá ALTERAR o cadastro do funcionário. A matrícula passará de 4097 para 4097X
e 8.797 registros vinculados serão migrados (batidas, abonos, endossos, arquivos etc.).
Esta ação é irreversível. Continuar?
```

![Confirmação da correção](imagens_manual_correcao/07_confirmacao.png)

3. Clique em **OK** para confirmar ou **Cancelar** para voltar sem alterar nada.
4. Ao final, o sistema retorna ao grid e exibe a mensagem de sucesso com a quantidade de registros migrados:

```
Correção aplicada com sucesso. Matrícula 4097 → 4097X. Registros migrados: {"ponto":8766,"abono":31}
```

![Sucesso na correção](imagens_manual_correcao/08_sucesso.png)

---

## 7. O que acontece depois da correção (técnico)

Ao alterar a matrícula, o sistema executa, **em uma única transação** (tudo ou nada):

| Item | O que é feito |
|---|---|
| `entidade` | Matrícula e demais campos atualizados |
| `user` | Login passa a ser a nova matrícula (senha **preservada**) |
| `ponto` (batidas) | Todas as batidas migram para a nova matrícula |
| `abono` | Todos os abonos migram para a nova matrícula |
| `endosso` | Matrícula atualizada no banco e nos arquivos CSV |
| `ss_colaborador` | Matrícula atualizada no módulo de Saúde e Segurança |
| Troca de turno | Textos de matrícula atualizados |
| Arquivos em disco | Pastas `motoristas/{matrícula}/` e arquivos `CNH_*`/`FOTO_*` renomeados (inclusive legados antigos) |
| Fotos de placa | Arquivos e referências renomeados |
| Painéis (JSON) | Relatórios de endosso/saldo atualizados e renomeados |
| **Log de auditoria** | Registro gravado na tabela `correcao_cadastro_log` |

**Não é migrado** (não há necessidade, ou são dados de entrada históricos):

- Arquivos brutos de importação de ponto (`arquivos/pontos/*.txt`)
- Rastreamento por placa (Positron)
- Assinaturas eletrônicas identificadas por CPF/RG/e-mail
- Logs de auditoria de RFID (histórico)

---

## 8. Regras — o que pode e o que NÃO pode

### ✅ Pode

- Alterar a matrícula de um funcionário (matrícula incorreta no cadastro)
- Corrigir qualquer campo do cadastro (nome, CPF, endereço, cargo, jornada, CNH etc.)
- Corrigir funcionários ativos ou inativos
- Alterar o login do usuário (campo Login) — a senha **nunca** é alterada por esta tela
- Utilizar a funcionalidade quantas vezes forem necessárias (repetições sucessivas são permitidas, ex.: `4097 → 4097X → 4097Y`)

### ❌ Não pode

| Situação | Motivo |
|---|---|
| Acessar sem ser Super Administrador | Acesso restrito por perfil |
| Matrícula vazia | Campo obrigatório |
| Matrícula com mais de 11 caracteres | Limite do sistema |
| Matrícula já existente em **outro** funcionário | Impede duplicidade |
| Matrícula que já possui **batidas de ponto** | Evita misturar registros de pessoas diferentes |
| Matrícula que já possui **abonos** | Evita misturar registros de pessoas diferentes |
| Alterar a senha do usuário | A senha é preservada de propósito |
| Desfazer uma correção | A operação é irreversível (registrada em log) |

---

## 9. Caso especial: corrigindo o próprio usuário logado

Se o funcionário corrigido for **o próprio usuário que está executando a correção** (ou estiver logado em outra sessão), o sistema avisa que:

> *"Como você é o próprio funcionário corrigido, faça logout e entre novamente com a nova matrícula."*

Nesse caso, o usuário deve:

1. Sair do sistema (botão Sair).
2. Entrar novamente usando o **novo login** (nova matrícula) e a mesma senha.

---

## 10. Rastreabilidade (auditoria)

Toda correção é gravada na tabela `correcao_cadastro_log`:

| Coluna | Conteúdo |
|---|---|
| `corr_nb_entidade` | ID do funcionário corrigido |
| `corr_tx_matricula_antiga` | Matrícula antes da correção |
| `corr_tx_matricula_nova` | Matrícula depois da correção |
| `corr_tx_contadores` | Quantidade de registros migrados por tabela (JSON) |
| `corr_nb_user` | Usuário que executou a correção |
| `corr_tx_data` | Data/hora da correção |

> 💡 Caso o cliente precise comprovar uma correção (ex.: auditoria trabalhista), essa tabela permite identificar **quem corrigiu, quando, de qual matrícula para qual, e quantos registros foram migrados**.

---

## 11. Boas práticas recomendadas

1. **Confira sempre os contadores** da tela antes de aplicar — se o funcionário tem histórico, eles devem ser maiores que zero.
2. **Use o campo de busca** (nome ou matrícula) para evitar corrigir o funcionário errado.
3. **Valide a matrícula nova** antes: confirme com o RH/DP que o número está correto e não pertence a outra pessoa.
4. **Execute em horário de baixo movimento**, pois envolve a atualização de todos os registros de ponto do funcionário.
5. **Sempre registre o motivo** em um e-mail/OS para o suporte, já que a operação é irreversível.
6. **Teste em ambiente de desenvolvimento/demo** antes de usar em produção, se possível.

---

## 12. Problemas comuns

| Sintoma | Causa provável | Solução |
|---|---|---|
| Item "Corrigir Funcionário" não aparece no menu | Usuário não é Super Administrador | Solicitar acesso ao suporte |
| *"Acesso restrito ao Super Administrador."* ao acessar a URL | Perfil sem permissão | Mesma orientação acima |
| *"Matrícula já cadastrada para outro funcionário."* | A matrícula nova já existe no sistema | Conferir com o DP o número correto |
| *"Já existem batidas de ponto com a matrícula nova"* | A matrícula nova já possui registros de ponto de outro funcionário | Não é possível usar essa matrícula; o sistema bloqueia para não misturar dados |
| *"Já existem abonos com a matrícula nova"* | A matrícula nova já possui abonos | Idem acima |
| Mensagem de sucesso não aparece | A correção foi cancelada na confirmação | Repetir o procedimento confirmando o alerta |
| Após corrigir, o funcionário não consegue entrar no sistema | Login mudou para a nova matrícula | Entrar com o novo login (mesma senha) |

---

## 13. Referências técnicas (desenvolvedores)

| Item | Detalhe |
|---|---|
| Arquivo da funcionalidade | `armazem_paraiba/correcao_funcionario.php` |
| Menu | `armazem_paraiba/menu.php` (item `Corrigir Funcionário`) |
| Tabela de auditoria | `correcao_cadastro_log` (criada automaticamente na primeira correção) |
| Validação de perfil | `user_nb_superadmin = 1` **ou** nível de usuário contendo "Super Admin" |
| Transação | `mysqli_begin_transaction` → `COMMIT`/`ROLLBACK` |
| Fluxo de propagação | Veja seção 7 deste manual |
| Helpers do framework | `contex20/funcoes.php`, `contex20/funcoes_form.php`, `contex20/funcoes_grid.php` |

### 13.1 Testes de regressão sugeridos após alterações no código

1. Corrigir matrícula de funcionário com pontos/abonos/endossos → conferir espelho de ponto e painéis.
2. Tentar matrícula duplicada → deve bloquear.
3. Tentar matrícula com batidas existentes → deve bloquear.
4. Acessar a página com usuário não-admin → deve bloquear.
5. Conferir o registro na tabela `correcao_cadastro_log` após cada correção.

---

*Documento mantido pela equipe de desenvolvimento. Em caso de dúvidas, abrir chamado no suporte.*
