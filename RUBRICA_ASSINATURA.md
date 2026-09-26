# Assinatura eletrônica por rubrica (desenho)

Data: 2026-09-25. Complementa `ESTUDO_ASSINATURA_APP.md`.

## Configuração
Cadastro de Empresa > campo **"Assinatura eletrônica"** (`empresa.empr_tx_tipoAssinatura`):
| Valor | Tela do link | Validação |
|---|---|---|
| `cpf_rg` (padrão) | Campos CPF e RG | CPF/RG conferidos com `entidade` |
| `rubrica` | Quadro para desenhar a rubrica (lápis + Limpar) | Rubrica obrigatória; CPF/RG do cadastro gravados na auditoria |
| `ambos` | CPF, RG e quadro de rubrica | Os dois |

A empresa do signatário é resolvida por `entidade.enti_nb_empresa` (depois `signatarios_externos.sign_nb_empresa`, `solicitacoes_assinatura.empresa_id`, matriz).
A coluna é criada automaticamente na primeira abertura do cadastro de empresa ou do link.

## Onde a rubrica aparece no PDF
- Somente na **página de comprovantes** (não no documento original).
- **Comprovante de assinatura** (páginas de auditoria no final): coluna própria à direita do card com a rubrica e, abaixo, nome completo, CPF e RG. O texto do card (hash, navegador) fica à esquerda sem sobrepor.
- A finalização ICP (`processar_finalizacao.php`) re-renderiza o PDF já carimbado, então a rubrica se mantém no documento final.

## Rubrica cadastrada no funcionário
Cadastro de Funcionário > seção "Foto e Rubrica" > campo **Rubrica (.png, .jpg, fundo branco)**. Fica em `entidade.enti_tx_rubrica` (arquivo em `arquivos/empresa/{emp}/motoristas/{matricula}/RUBRICA_{id}_{matricula}.*`), com botão "Excluir rubrica".
No link de assinatura (modo `rubrica` ou `ambos`), o quadro já abre com essa imagem (fundo branco convertido em transparente). O signatário pode manter, ou "Limpar" e desenhar; o botão "Usar rubrica do cadastro" recarrega a imagem. O carimbo usa o que estiver no quadro.

## Onde a rubrica fica gravada
- Arquivo PNG em `assinatura/rubricas/rubrica_{time}_{protocolo}.png`
- `assinantes.rubrica_path` e `assinantes.metadados` (`tipo_assinatura`, `rubrica_path`)
- No estado de auditoria dentro do PDF (`TECHPS_AUDIT_V1`), para os próximos signatários redesenharem o comprovante.

## Arquivos alterados
- `cadastro_empresa.php`: coluna, campo nas listas de salvar/carregar, combo no formulário (editável e somente leitura).
- `cadastro_funcionario.php`: coluna `enti_tx_rubrica`, upload e exclusão da rubrica, campo no formulário.
- `assinatura/tipo_assinatura_helper.php` (novo): resolve o tipo por empresa, garante colunas, salva o PNG da rubrica.
- `assinatura/assinar_via_link.php`: mostra campos conforme o tipo, quadro de rubrica (canvas, toque e mouse), flags JS.
- `assinatura/script.js`: validação por tipo, rubrica incorporada no PDF (rodapé das páginas + card do comprovante), envio no POST.
- `assinatura/assinar.php`: valida por tipo, salva a rubrica, grava `rubrica_path`.

## Testes feitos (ambiente Docker dev)
- Sem rubrica com empresa `ambos` -> 422 "A rubrica é obrigatória".
- CPF errado com rubrica -> 422 "CPF e RG não conferem".
- CPF/RG certos + rubrica -> sucesso, PNG salvo, `rubrica_path` preenchido, documento finalizado (ICP).
- Empresa `rubrica`: página sem campos CPF/RG; POST sem CPF/RG + rubrica -> sucesso.
- O carimbo no PDF (pdf-lib) roda no navegador; testar abrindo o link pendente no Chrome.

## Deploy
Copiar os 6 arquivos acima para a pasta de cada empresa em produção. Nenhum SQL manual é necessário.

## Expiração automática
Solicitações `pendente`/`em_progresso` com `expires_at` vencido passam a `status = 'expirado'` automaticamente (função `assinatura_expirarPendentes` em `tipo_assinatura_helper.php`), executada ao abrir qualquer tela do módulo (`componentes/layout_header.php`), o link de assinatura e o webservice do app (`/ws/signatures`). Ao renovar o link (`renovar_link.php`) o status volta para `pendente` (ou `em_progresso` se alguém já assinou). O filtro "Expirado" em `consultar.php` também considera esse status.
