# Provisionador de empresas

Cria uma empresa nova de ponta a ponta, sem cPanel manual. Branch de teste: `teste/provisionamento-empresas` (criada a partir do commit `31f402c` da `main`).

## O que faz
1. Valida nome da pasta, sigla e login.
2. Cria banco e usuário exclusivos (`<prefixo><pasta>`).
3. Importa a estrutura do banco modelo e os cadastros de referência.
4. Cria a empresa matriz e o administrador inicial; copia os Super Administradores do modelo.
5. Copia os arquivos do sistema para `<raiz>/<pasta>/` (sem `.env`, `arquivos/`, uploads e APKs da empresa modelo).
6. Grava o `.env` da empresa (banco, `APP_KEY` novo, `CONTEX_PATH`, tela de provisionamento desligada).
7. Inclui a empresa no login (`empresas.json`, lido por `empresas.php`).

Se qualquer etapa falhar, tudo o que foi criado é removido. O que cada pedido criou fica anotado nele, e o "desfazer" remove somente isso.

## Dados pedidos na tela
Os mesmos do Cadastro de Empresa/Filial (CPF/CNPJ, nome, fantasia, CEP, endereço, número, bairro, complemento, referência, cidade/UF, telefones, contato, e-mail, inscrições, regime tributário, data de registro do CNPJ, logo, tipo de assinatura eletrônica e parâmetro de jornada), com os mesmos campos obrigatórios, mais a sigla de login, a pasta e o administrador inicial.

## Registro no domínio mestre (auditoria)
Ao criar a empresa, o provisionador grava os dados em dois lugares:
| Onde | Como |
|---|---|
| Banco da empresa nova | empresa **matriz**, ativa |
| Cadastro de empresas do domínio mestre | mesma empresa, **não matriz**, com o endereço criado em `empr_tx_domain` e o usuário que pediu em `empr_nb_userCadastro` |

O id do registro mestre fica no pedido (`prov_nb_empresaMestre`).
- Se a criação **falha no meio**, o registro mestre é apagado (a empresa nunca existiu).
- Se a empresa é **desfeita depois de criada**, o registro mestre fica **inativo** e é mantido para auditoria.

## Como usar
- **Tela**: Cadastros > "Nova Empresa (automático)". Só aparece com `PROVISIONAMENTO_HABILITADO = 1` no `.env` da empresa e para Super Administrador. A tela apenas registra o pedido.
- **Execução**: cron chamando `php provisionador/worker.php processar`.
- **Linha de comando**:
```
php worker.php checar
php worker.php criar-pedido --nome="Empresa X" --sigla=EMPX --pasta=empresa_x --admin-login=admin.x
php worker.php processar
php worker.php status
php worker.php ver <id>
php worker.php desfazer <id>
```

## Configuração (`.env` da raiz)
| Chave | Dev (Docker) | Produção (cPanel) |
|---|---|---|
| `PROVISION_DRIVER` | `sql` | `uapi` |
| `PROVISION_DB_ADMIN_USER` / `_PASSWORD` | `root` / senha | não usado |
| `PROVISION_DB_PREFIX` | `techps_` | `tech1694_` |
| `PROVISION_TEMPLATE_TENANT` | `armazem_paraiba` | pasta cujo banco serve de modelo (ex.: `demo`) |
| `PROVISION_MASTER_TENANT` | `armazem_paraiba` | `techps` |
| `PROVISION_SOURCE_DIR` | (padrão) | `/home/tech1694/repositories/prod/armazem_paraiba` |
| `PROVISION_TARGET_ROOT` | (padrão) | `/home/tech1694/public_html/gestaodeponto` |
| `PROVISION_REFERENCE_TABLES` | (padrão) | lista de tabelas copiadas com dados |

## Servidor dev (levantamento em 2026-09-27, somente leitura)
| Item | Encontrado |
|---|---|
| PHP do shell/cron | 8.3.33, com mysqli |
| Funções bloqueadas | `exec, passthru, system, show_source, shell_exec, mail` |
| `proc_open` | disponível; chamou a UAPI com sucesso (listou 16 bancos) |
| UAPI | `/usr/bin/uapi`; prefixo `devtechpsgj_`; banco até 64 caracteres, usuário até 47 |
| Limite de bancos | ilimitado (16 em uso) |
| Raiz das empresas | `/home/devtechpsgj/public_html/dev` (`APP_PATH = /dev`) |
| Repositório | `/home/devtechpsgj/repositories/dev_techps`, publicado por tag (`Dev1.119`) |
| Empresa mestre | não existe pasta `techps` no dev; usar `demo` |
| Cron | nenhum agendado |

Por causa do bloqueio de `exec`, o provisionador chama a UAPI com `proc_open`, sem passar pelo shell.

Configuração sugerida para o `.env` da raiz no servidor dev:
```
PROVISION_DRIVER = uapi
PROVISION_TEMPLATE_TENANT = demo
PROVISION_MASTER_TENANT = demo
PROVISION_SOURCE_DIR = /home/devtechpsgj/repositories/dev_techps/armazem_paraiba
```
`PROVISION_DB_PREFIX` pode ficar de fora: com o driver `uapi` o prefixo vem do próprio cPanel.
No `.env` da pasta `demo`: `PROVISIONAMENTO_HABILITADO = 1`.
Cron: `* * * * * /usr/local/bin/php /home/devtechpsgj/public_html/dev/provisionador/worker.php processar >/dev/null 2>&1`

## Testado no Docker local (2026-09-27)
| Cenário | Resultado |
|---|---|
| Criação completa | 57 s; login web lista a empresa; `ws/login` autentica; banco isolado |
| Banco já existente | falha na etapa 2; nada criado; banco pré-existente preservado |
| Falha após criar banco e copiar arquivos | pasta, banco e usuário removidos automaticamente |
| Desfazer por comando e pela tela | banco, usuário, pasta e registro removidos; banco modelo intacto |
| Pasta maliciosa (`../x`), duplicidade, usuário sem perfil | pedido recusado |

**Driver `uapi`**: testado localmente com uma UAPI simulada e com `exec` bloqueado (criação, login e desfazer). No servidor real só foi testada a leitura (listar bancos).

**Não testado**: criação real de banco pelo cPanel e o `deploy_empresas.sh` no servidor.

## Deploy sem editar o `.cpanel.yml`
`deploy_empresas.sh` copia os arquivos para toda pasta que tenha `.env`. Exemplo de uso em `cpanel.exemplo.yml`. **Não está ativo**: o `.cpanel.yml` atual não foi alterado.

## Como voltar ao que era antes
1. Remover a empresa de teste: `php provisionador/worker.php desfazer <id>` (ou botão Desfazer na tela).
2. Código: `git switch main` e, se quiser descartar, `git branch -D teste/provisionamento-empresas`. A `main` não foi alterada.
3. Configuração local: apagar as linhas `PROVISION_*` do `.env` da raiz e `PROVISIONAMENTO_HABILITADO` do `.env` da empresa (ambos fora do git).
4. Banco mestre: `DROP TABLE provisionamento;` (só guarda a fila de pedidos).

## Segurança
- Pasta `provisionador/` bloqueada para a web (`.htaccess`) e os scripts recusam execução fora da linha de comando.
- A tela web não cria banco nem escreve fora da própria pasta; só grava o pedido.
- Senha do administrador fica só como hash no pedido e é apagada ao concluir.
- O desfazer só remove pasta com o marcador `.provisionado` do próprio pedido e nunca o banco modelo/mestre.
