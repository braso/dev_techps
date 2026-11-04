# Biblioteca de POPs

Módulo de gestão dos **Procedimentos Operacionais Padrão** da empresa, integrado ao módulo de Termos e ao cadastro de setores (`grupos_documentos`).

## O que a página entrega

- Abas por setor (Tecnologia, RH, Administrativo etc.) com contagem de POPs.
- Busca por título, código, setor, responsável, palavras-chave e nome do arquivo.
- Filtros por origem (termo ou PDF), situação (ativo/inativo), com fluxograma e favoritos.
- Cartões com prévia imediata em PDF, detalhes do procedimento e aba de **Fluxograma** quando anexado.
- Download, abertura em nova aba (modo apresentação), cópia de link público e envio por e-mail/notificação para colaboradores.
- Favoritos por usuário.
- Envio de PDF com classificação completa: título, setor, código, versão, vigência, responsável, palavras-chave, objetivo e fluxograma.
- Gestão rápida de setores em `setores.php`, reaproveitando a tabela `grupos_documentos`.

## Como um POP entra na biblioteca

### 1. Pelo módulo de Termos (documento editável)

Em **Cadastros > Modelos de Termos**:

1. Crie ou edite um modelo.
2. Marque **Este documento é um POP? = Sim**.
3. Preencha setor, código, versão, vigência, responsável, palavras-chave e objetivo.
4. Opcionalmente anexe um **Fluxograma** (imagem ou PDF).
5. Grave o modelo.

O modelo passa a aparecer na Biblioteca de POPs. A prévia em PDF é gerada com dados de exemplo (a prévia não usa dados reais de colaboradores nem o bloco de assinaturas).

### 2. Pelo upload de PDF

Na própria Biblioteca de POPs, use **Enviar POP em PDF** e preencha os mesmos campos de classificação. O arquivo fica em `armazem_paraiba/arquivos/pop/documentos/` e passa a aparecer na aba do setor escolhido.

## Permissão por perfil de acesso

A página usa o mesmo controle do restante do sistema (`verificaPermissao`/`temPermissaoMenu`).

1. Abra **Cadastros > Perfil de Acesso**.
2. Edite o perfil desejado.
3. Marque **Biblioteca de POPs** (seção Cadastros).
4. Salve o perfil.

Administradores e Super Administradores acessam independentemente do perfil.

## Banco de dados

As tabelas/colunas são criadas automaticamente na primeira utilização:

- `modelo_termo`: colunas `mode_tx_pop`, `mode_nb_pop_setor`, `mode_tx_pop_codigo`, `mode_tx_pop_versao`, `mode_dt_pop_vigencia`, `mode_tx_pop_objetivo`, `mode_tx_pop_responsavel`, `mode_tx_pop_tags`, `mode_tx_pop_fluxograma`, `mode_tx_pop_fluxograma_nome`.
- `pop_documento`: PDFs enviados diretamente pela Biblioteca.
- `pop_envio`: links/tokens públicos de compartilhamento (30 dias).
- `pop_favorito`: favoritos por usuário.

## Arquivos e segurança

- Os arquivos ficam em `armazem_paraiba/arquivos/pop/` e **não são servidos diretamente**: o `.htaccess` da pasta bloqueia o acesso e todo download passa por `pop_arquivo.php`.
- O `pop_arquivo.php` valida a permissão do usuário logado **ou** o token público do envio.
- Links públicos são aleatórios, expiram em 30 dias e deixam de funcionar se o POP for desativado/excluído.
- Uploads aceitam PDF (documento) e PNG/JPG/WEBP/PDF (fluxograma), com limite de 20 MB.

## Arquivos principais

| Arquivo | Função |
|---|---|
| `index.php` | Biblioteca, filtros, cartões, modais de prévia/upload/envio e ações de gestão |
| `funcoes_pop.php` | Regras de negócio, consultas, links públicos, streaming de arquivos e envio de e-mail |
| `pop_arquivo.php` | Entrega segura de PDF/fluxograma (sessão ou token) |
| `pop_visualizar.php` | Página pública do link enviado ao colaborador |
| `pop_favoritar.php` | Alterna favorito via AJAX |
| `pop_gerar_link.php` | Gera link público para copiar/compartilhar |
| `buscar_colaboradores.php` | Busca AJAX de colaboradores para o modal de envio |
| `setores.php` | Cadastro rápido de setores usado nas abas |
| `assets/pop.css` | Estilos da Biblioteca e da gestão de setores |

## Observações

- O envio por e-mail usa a configuração SMTP já utilizada pelo módulo de assinatura (`smtp_credenciais.php`/`.env`). Se o SMTP não estiver configurado, o link público ainda pode ser copiado manualmente.
- O colaborador também recebe uma notificação no sino do sistema quando possui usuário vinculado à entidade.
