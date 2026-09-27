# Estudo: criação automática de empresa (provisionamento)

Data: 2026-09-27. Base analisada: `.cpanel.yml`, `.cpanelPROD.yml`, `.cpanelDEV.yml`, `deploy_manual_prod.sh`, `empresas.php`, `index.php` (raiz), `.env-example` (raiz e empresa), módulo `suporte/`.

## 1. Como é hoje (manual)
| Passo | Onde | Observação |
|---|---|---|
| Criar a pasta da empresa | cPanel, `public_html/gestaodeponto/<pasta>` | |
| Copiar os arquivos | cPanel | origem: `repositories/prod/armazem_paraiba/*` |
| Criar banco e usuário MySQL | cPanel | um banco por empresa |
| Carregar estrutura e dados base | phpMyAdmin | não existe dump/migração no repositório (dev tem 82 tabelas) |
| Criar o `.env` da empresa | arquivo na pasta | `DB_*`, `APP_KEY`, `CONTEX_PATH`, chaves do suporte |
| Registrar a sigla no login | `empresas.php` (array fixo no código) | exige commit + deploy |
| Incluir no deploy | `.cpanel.yml` (export + cp por empresa) | exige commit; hoje `.cpanel.yml` e `.cpanelPROD.yml` já divergem (SAMBAIBA só em um) |
| App | build de APK com o endereço da empresa | |

Pontos frágeis: lista de empresas repetida em 4 lugares (`empresas.php`, 2 arquivos cpanel, script manual), sem fonte única; estrutura do banco sem versão.

## 2. É possível automatizar? Sim
O cPanel oferece a UAPI, que cria banco, usuário e permissões por linha de comando (`uapi Mysql create_database`, `create_user`, `set_privileges_on_database`) quando executada pelo usuário da conta. Um cron da própria conta roda como esse usuário, então consegue: criar pasta, copiar arquivos, criar banco, importar estrutura e gravar o `.env`. Nada disso precisa de acesso root.

## 3. Desenho proposto

### 3.1 Tela no domínio mestre (TechPS)
"Nova Empresa" (só Super Administrador, só no domínio `techps`): nome, sigla de login, nome da pasta, CNPJ, e-mail e login do administrador inicial. Grava um pedido na tabela `provisionamento` do banco mestre com status `pendente`. A tela mostra o andamento de cada etapa.

### 3.2 Worker por cron (a cada minuto)
Script PHP de linha de comando fora da pasta pública (ex.: `/home/tech1694/provisionador/worker.php`). Para cada pedido pendente executa, registrando cada etapa:
1. Valida o nome da pasta (apenas `a-z`, `0-9`, `_`; não pode existir).
2. Cria `public_html/gestaodeponto/<pasta>` e copia `repositories/prod/armazem_paraiba/*`.
3. Cria banco e usuário via UAPI, com senha aleatória forte.
4. Importa a estrutura: `mysqldump --no-data` de um banco modelo + dados das tabelas de referência (cidades, macros de ponto, parâmetro padrão, itens de menu, perfis).
5. Grava o `.env` a partir de um modelo: `DB_*`, `APP_KEY` novo, `CONTEX_PATH`, e as chaves `SUPORTE_API_*` para a empresa já nascer ligada ao suporte mestre.
6. Cria a empresa matriz e o usuário administrador no banco novo.
7. Registra a empresa no cadastro único (3.3).
8. Marca o pedido como `concluido` e avisa por e-mail. Em erro, marca `erro` com a etapa e a mensagem, e desfaz o que criou.

### 3.3 Cadastro único de empresas
Trocar o array fixo de `empresas.php` por um arquivo de dados (`empresas.json`, fora do controle de versão ou mantido pelo worker). `empresas.php` passa a ler esse arquivo. Empresa nova entra no login sem commit.

### 3.4 Deploy sem editar o `.cpanel.yml`
O `.cpanel.yml` aceita qualquer comando. Em vez de um `export` + `cp` por empresa, uma única tarefa chama um script que percorre as pastas:
```
for d in /home/tech1694/public_html/gestaodeponto/*/; do
  [ -f "$d/.env" ] && /bin/cp -R $DOMAIN "$d"
done
```
Toda pasta que tem `.env` é uma empresa e recebe a atualização. Com isso **nem o `.cpanel.yml` precisa de edição manual** para empresa nova, e as divergências entre os arquivos acabam.

### 3.5 Banco modelo
Manter um banco `modelo` (ou usar o `demo`) sempre atualizado como referência de estrutura. Como o sistema já cria colunas sozinho em vários pontos, o modelo precisa ser o banco que recebe as atualizações primeiro. Alternativa mais robusta a médio prazo: arquivo de estrutura versionado no repositório.

## 4. Segurança
- Provisionamento só a partir do domínio mestre e só por Super Administrador.
- A tela web apenas grava o pedido; quem executa é o cron. O PHP da web não ganha poder de criar banco nem de escrever fora da própria pasta.
- Credenciais geradas ficam só no `.env` da empresa; o pedido guarda apenas o status.
- Nome de pasta e sigla validados por lista de caracteres permitidos (evita caminho malicioso).
- Log de cada execução em arquivo fora da pasta pública.

## 5. Limites e dependências
- Limite de bancos do plano de hospedagem e tamanho máximo do nome (prefixo `tech1694_`).
- Confirmar no servidor: `uapi` disponível no shell do cron, `mysqldump`/`mysql` no PATH, e cron liberado na conta.
- App: hoje cada empresa exige um APK próprio. O provisionamento não resolve isso; a solução é um APK único que pede a sigla da empresa no login (etapa separada).

## 6. Etapas e esforço
| Etapa | Entrega | Estimativa |
|---|---|---|
| 1 | Deploy por varredura de pastas (3.4) + cadastro único de empresas (3.3) | 0,5 a 1 dia |
| 2 | Worker de provisionamento + banco modelo (3.2, 3.5), testado no ambiente dev | 2 a 3 dias |
| 3 | Tela "Nova Empresa" no mestre com acompanhamento (3.1) | 1 dia |
| 4 | (Opcional) APK único com escolha de empresa no login | 2 dias |

A etapa 1 já elimina a edição manual do `.cpanel.yml` e do `empresas.php`, mesmo antes do provisionamento completo.
