# Carimbos e rubricas posicionáveis no documento — viabilidade

Pedido do setor jurídico: no ato da assinatura, o signatário precisa poder
adicionar **vários** carimbos e **várias** rubricas, arrastando cada um para
qualquer lugar de qualquer página, com um botão para acrescentar mais.

Veredito: **viável**, sem biblioteca nova paga e sem trocar o fluxo de
assinatura. Mas não é uma tela nova em cima do que existe — exige trocar o
visualizador e mudar *onde* o documento é estampado. Abaixo o que há hoje, o
que falta, os riscos e as fases.

---

## Situação: concluído (fases 1 a 5)

Implementado em 06/11/2026.

| Arquivo | O que faz |
|---|---|
| `marcas_helper.php` (novo) | Regra única: tabela `assinatura_marcas` com migração automática, tipos de marca, conferência do que vem da tela, e o desenho no PDF com FPDI |
| `editor_marcas.js` (novo) | Editor: renderiza o documento com pdf.js e põe as marcas arrastáveis por cima. Serve as duas telas |
| `vendor_js/` (novo) | pdf.js 3.11.174 e pdf-lib 1.17.1 servidos pelo próprio servidor |
| `assinar_via_link.php` | `<iframe>` trocado pelo editor; barra de carimbos; zoom; campo oculto `marcas` |
| `assinar.php` | Grava as marcas, preenche as que o remetente reservou e aplica os carimbos antes do ICP |
| `processar_finalizacao.php` | Aplica os carimbos em qualquer caminho de finalização |
| `nova_assinatura.php` | Modal "Posicionar carimbos" para o remetente, com um signatário por cor |
| `processar_envio.php` | Grava as marcas do remetente depois de inserir os assinantes |
| `script.js` | Manda as posições no envio; baixa o documento uma vez só |

**Marcas disponíveis:** rubrica, assinatura, carimbo completo, nome, CPF, RG,
data e texto livre. Quantas quiser, em qualquer página, arrastando e
redimensionando, mais o botão "Rubricar todas as páginas".

### Quem posiciona

Os dois, como recomendado:

- **O remetente**, em `nova_assinatura.php`, antes de enviar: escolhe o
  signatário num seletor (cada um com sua cor) e reserva os lugares. Essas
  marcas aparecem **travadas** para quem assina — em laranja, com o ícone de
  alfinete — e são preenchidas no servidor no ato da assinatura, porque nome,
  CPF, RG, data, protocolo e rubrica só existem naquele momento.
- **O signatário**, em `assinar_via_link.php`: além dos lugares reservados,
  acrescenta quantas marcas quiser.

Disponível no envio **avulso** (um PDF, um ou vários signatários, com posição
por signatário) e no envio do **mesmo documento para todos os funcionários**
(a posição escolhida uma vez vale para cada cópia). **Não** aparece em
"separar páginas" nem no envio com um PDF diferente por funcionário: nesses
dois casos cada pessoa recebe um documento distinto, e uma posição por número
de página não significaria a mesma coisa em todos.

### Um defeito pré-existente corrigido no caminho

A consulta que carrega o signatário em `assinar_via_link.php` não trazia
`s.empresa_id`. O `assinatura_obterTipoAssinatura()` recebia sempre `0` nesse
parâmetro, então, para **signatário externo**, a empresa nunca era descoberta e
o tipo caía no padrão `cpf_rg` — a configuração "rubrica" ou "ambos" do cadastro
da empresa era silenciosamente ignorada, e o quadro de rubrica não aparecia.
Só apareceu porque a tela foi carregada de verdade pelo Apache: o tipo voltava
`cpf_rg` com a empresa configurada como `ambos`.

Correção: acrescentar `s.empresa_id` ao `SELECT`. Com isso o tipo passa a
resolver para `ambos` e o quadro de rubrica aparece.

### Decisões tomadas durante a construção

- **Só as páginas do documento original aceitam marca.** As de comprovante são
  removidas e redesenhadas a cada assinatura (`script.js`, em
  `renderAuditPages`), então o número delas muda durante a coleta e uma marca
  presa a uma delas mudaria de lugar sozinha. O editor as mostra hachuradas,
  com o rótulo "Comprovante · não aceita carimbo", e o servidor descarta o que
  vier fora do intervalo.
- **O conteúdo de cada carimbo é montado no servidor.** A tela manda apenas
  tipo, página e coordenadas.
- **A rubrica mantém a proporção e fica centralizada na caixa** (fitbox `CM`).
  Esticar uma assinatura à mão para preencher a caixa descaracterizaria o
  traçado.
- **O desenho é idempotente.** O arquivo carimbado fica em
  `solicitacoes_assinatura.caminho_marcado`; rodar a finalização de novo não
  carimba duas vezes.
- **Carimbo que falha não derruba a assinatura.** Rubrica cujo PNG não pode ser
  embutido (servidor sem GD, por exemplo) cai para o texto, e erro na passada
  inteira volta como aviso — o documento continua assinado.
- **O rodapé "Powered by TCPDF" não duplica.** O TCPDF acrescenta um por
  passada e esta é uma passada nova; desligado com uma classe anônima, porque a
  propriedade é protegida.
- **O documento é baixado uma vez.** O `script.js` baixa e avisa o editor pelo
  evento `assinatura:documento-pronto`.
- **Trocar o PDF zera o posicionamento.** Sem isso, as marcas do documento
  antigo seguiriam com o documento novo.
- **A marca do remetente é amarrada pela ORDEM do signatário**, não por id: os
  assinantes só existem no banco depois do envio. A conferência valida item a
  item, porque dois signatários podem reservar o mesmo tipo na mesma página e
  um casamento por conteúdo erraria o dono da marca.
- **Celular:** alças e botões maiores abaixo de 640px, gestos por pointer
  events (mouse, dedo e caneta no mesmo código), zoom de 50% a 400% e, no
  desktop, ajuste fino pelas setas do teclado (Shift move mais rápido, Delete
  remove).

### O que foi testado

Harness em `regressao.php` + `conferir.php`, no diretório de trabalho da
sessão. Usa as funções reais, não cópias. Dez casos, todos passando:

- recusa de página inexistente, tipo fora da lista e texto vazio;
- três signatários reservando **o mesmo tipo na mesma página** — cada um fica
  com a posição e o dono certos;
- marca sem ordem cai no signatário 1;
- os três tipos de assinatura da empresa (`cpf_rg`, `rubrica`, `ambos`):
  com rubrica desenha a imagem, sem rubrica cai para o texto;
- documento de **40 páginas** com rubrica em todas, mais carimbo girado 20° e
  CPF na página em paisagem: 42 marcas em 0,13 s;
- rubrica apontando para arquivo inexistente: cai para o texto e avisa;
- PDF de entrada inexistente: devolve erro sem lançar exceção.

A conferência das posições é feita com PyMuPDF, medindo o PDF de saída em
milímetros — não confia no que o PHP relatou. Desvio de **0,00 mm** em retrato
e em paisagem, incluindo a conta da centralização dentro da caixa. O texto
original do documento permanece intacto e o rodapé do TCPDF não duplicou.

### Testado também contra o banco e o Apache do container

Com o `docker compose` do projeto de pé (`dev_techps-db-1` e
`dev_techps_apache`), o harness `_teste_marcas_db.php` roda dentro do container
contra o MariaDB de desenvolvimento. **24 casos, todos passando:**

- a migração cria a tabela e a coluna `caminho_marcado`, e rodar duas vezes não
  quebra;
- as marcas do remetente gravam com `origem = remetente` e `obrigatoria = 1`,
  sem conteúdo e sem imagem, cada signatário com a posição dele;
- marca de signatário que não entrou na solicitação é descartada;
- o preenchimento no ato da assinatura põe o caminho da rubrica e o texto com
  CPF e RG, e **não** mexe na marca de quem ainda não assinou;
- reassinar substitui as marcas do signatário em vez de duplicar;
- o desenho grava `caminho_marcado` e rodar de novo devolve o **mesmo** arquivo;
- solicitação sem marca não gera arquivo novo;
- conexão inválida não lança exceção para fora e devolve os valores seguros.

As duas telas foram carregadas pelo Apache do container, com HTTP 200 e **zero
aviso de PHP**: a tela de assinatura (47 KB, com o editor, o quadro de rubrica e
os botões de carimbo) e a tela do remetente (219 KB, com o modal, o seletor de
signatário e os sete botões). Os dois endpoints conferem: `marcas=1` devolve 3
lugares reservados para a Maria e 2 para o João, cada um com a coordenada
certa, e `arquivo=1` serve o PDF com `Content-Type: application/pdf`.

### Blindagem contra exceção do mysqli

No PHP 8.1+ o mysqli lança exceção por padrão e **o `@` não silencia exceção**.
O `conecta.php` deste sistema desliga o relato durante a criação de estrutura e
o **religa** em seguida (linha 791), então quando as funções dos carimbos rodam
o modo normalmente é o de lançar. Base de cliente sem privilégio de `ALTER`,
tabela em outro charset ou coluna já existente com outro tipo viraria
`Fatal error` no meio da assinatura.

Cada função de banco virou `..._interno` e ganhou um invólucro público com
`try/catch` que registra o motivo no log e devolve um valor seguro. A regra é a
mesma do desenho: **problema de carimbo não impede ninguém de assinar.**

### O que ainda NÃO foi testado

**O arrastar e o zoom no navegador.** O Claude in Chrome está desligado nas
configurações da máquina, então não foi possível dirigir o Chrome. Todo o
JavaScript passa no `node --check` (inclusive os blocos inline, nos dois ramos
de PHP) e os símbolos da API do pdf.js usados existem no bundle, mas isso não
substitui arrastar uma marca com o dedo e com o mouse.

---

## 1. Como o documento é estampado hoje

O fluxo real é um só: o link enviado por e-mail (`assinar_via_link.php`).

1. **Visualização** — `assinar_via_link.php:674` mostra o PDF num `<iframe>`,
   ou seja, no visualizador nativo do navegador. Em paralelo os bytes do
   arquivo são baixados para `currentPdfBytes`.

2. **Coleta** — nome, CPF e RG em campos de texto; a rubrica é desenhada num
   `<canvas>` e exportada como PNG transparente para um input oculto
   (`assinar_via_link.php:996-1062`). Há também "Usar rubrica do cadastro",
   que carrega `entidade.enti_tx_rubrica` e remove o fundo branco.

3. **Estampagem — no navegador** — `script.js:236` em diante usa **pdf-lib**
   para ler o estado de auditoria embutido no próprio PDF (`Subject`/`Title`,
   prefixo `TECHPS_AUDIT_V1:` em base64), acrescentar o signatário atual e
   **redesenhar as páginas de comprovante no fim do arquivo**, com um card por
   signatário: nome, CPF, RG, data, IP, hash, navegador e a imagem da rubrica.
   O código diz isso explicitamente em `script.js:398`:

   > A rubrica aparece apenas na página de comprovantes (card do signatário),
   > não no documento original.

4. **Gravação** — o PDF já modificado volta ao servidor como
   `arquivo_assinado` (`script.js:606`) e é salvo em `docAssinado/`
   (`assinar.php:644`). A rubrica também vai para `rubricas/` e para
   `assinantes.rubrica_path`, como evidência — esse arquivo nunca é usado para
   desenhar nada.

5. **Finalização** — quando o último assina (`assinar.php:795`), e **somente
   se** a solicitação foi criada com `validar_icp = 'sim'`,
   `processar_finalizacao.php` reimporta todas as páginas com FPDI, aplica
   `setSignature()` com o PFX e desenha **um** carimbo ICP-Brasil de
   150 × 36 mm em posição fixa, no canto inferior direito da **última página**,
   mais a linha do validador do ITI (`processar_finalizacao.php:345-419`).

### O que isso significa

- Nada é desenhado **no corpo** do documento hoje. Nem a rubrica. O que existe
  é uma página de comprovante anexada no fim e um carimbo ICP fixo na última
  página.
- Quem estampa é o **navegador do signatário**. O servidor recebe o PDF pronto
  e confia nele.
- Com ICP desligado, o arquivo final é exatamente o que o navegador devolveu —
  não passa por nenhuma estampagem no servidor.

---

## 2. Como foi construído, e por quê

### 2.1 Visualizador com camada de posicionamento

O `<iframe>` é inviável para isso. O visualizador nativo do Chrome não expõe a
geometria da página renderizada, o scroll nem o zoom, então não há como saber
em que ponto do PDF o usuário soltou o carimbo.

Troca necessária: **PDF.js** renderizando cada página num `<canvas>`, com uma
camada de `div`s absolutos arrastáveis por cima. É o que DocuSign e Clicksign
fazem.

Cuidados que costumam ser esquecidos e quebram em produção:

- **Conversão de coordenadas** — a tela trabalha em pixels com origem no canto
  superior esquerdo; o PDF trabalha em pontos com origem no **inferior**
  esquerdo. O Y precisa ser invertido.
- **Guardar em percentual**, nunca em pixels nem em milímetros. Assim a marca
  cai no mesmo lugar independente do zoom, do DPI da tela e do tamanho da
  página.
- **Rotação e tamanho de página** — `/Rotate 90`, páginas em paisagem e
  `CropBox` diferente do `MediaBox` deslocam tudo. O percentual precisa ser
  calculado sobre o mesmo viewport que o PDF.js reportou, e lido de volta com o
  tamanho que o `getTemplateSize()` do FPDI devolve.

### 2.2 Modelo de dados

Tabela nova, uma linha por marca:

| campo | para que serve |
|---|---|
| `id_solicitacao`, `id_assinante` | de quem é a marca |
| `tipo` | `rubrica`, `assinatura`, `carimbo`, `cpf`, `rg`, `nome`, `data`, `texto` |
| `pagina` | página do documento original |
| `pos_x`, `pos_y`, `larg`, `alt` | percentuais de 0 a 1 |
| `rotacao` | opcional |
| `conteudo` / `caminho_imagem` | texto do carimbo ou PNG da rubrica |
| `criado_em`, `ip` | trilha de auditoria |

A migração tem de ser automática, no padrão que o módulo já usa
(`SHOW COLUMNS` + `ALTER TABLE` em `tipo_assinatura_helper.php:11`). São 36
bases de clientes em produção — depender de rodar SQL à mão em cada uma é
pedir para ficar pela metade.

### 2.3 Mudar onde o documento é estampado

Recomendo tirar a estampagem do navegador e levá-la para o servidor, na mesma
passada do FPDI que já existe em `processar_finalizacao.php`. Três motivos, em
ordem de importância:

1. **A assinatura ICP não admite alteração posterior.** O `setSignature()`
   assina uma faixa de bytes do arquivo. Qualquer carimbo aplicado depois
   invalida a assinatura. Todas as marcas têm de ser desenhadas antes do
   `Output()`, na mesma passada — ou seja, no servidor.
2. **Hoje o servidor aceita o PDF que o navegador devolveu.** Quem controla o
   navegador controla o conteúdo do arquivo final. Desenhando no servidor a
   partir das coordenadas gravadas, o arquivo passa a ser produzido por quem
   assina criptograficamente.
3. **Sem ICP não há estampagem nenhuma hoje.** A regra nova tem de valer nos
   dois casos, senão metade dos clientes não vê carimbo.

Efeito colateral bom: o PDF original deixa de ser reescrito a cada assinatura.
Hoje, num documento com cinco signatários, o arquivo é decodificado e
recodificado cinco vezes pelo pdf-lib no navegador. Guardando as marcas no
banco e desenhando uma vez só no fim, cada signatário vê o original com as
marcas dos anteriores desenhadas **pelo editor** (não pelo PDF), e o arquivo
sofre uma única passada.

### 2.4 Interface

- Botões "+ Rubrica", "+ Carimbo", "+ CPF", "+ RG" — cada clique joga uma marca
  nova na página visível, pronta para arrastar.
- Arrastar, redimensionar pelo canto, excluir e duplicar.
- **"Rubricar todas as páginas"** — é o caso mais pedido pelo jurídico e, sem
  esse botão, rubricar um contrato de 40 páginas é inviável na mão.
- Contador do que ainda falta posicionar, e bloqueio do botão Assinar enquanto
  houver marca obrigatória pendente.

### 2.5 Celular

Boa parte dos signatários assina pelo telefone. Arrastar um carimbo sobre uma
A4 numa tela de 5 polegadas não funciona com o mesmo código do desktop:
precisa de toque para posicionar, pinça para o zoom e ajuste fino por setas.
Não é detalhe de acabamento; é um bloco de trabalho próprio.

---

## 3. Riscos que continuam valendo

**Carimbo cobrindo cláusula.** Se o signatário posiciona livremente, ele pode
tapar um trecho do contrato — e quem vai reclamar disso depois é o próprio
jurídico que pediu a liberdade. Mitigações: gravar as coordenadas de toda marca
na trilha de auditoria, **manter sempre a página de comprovante** (ela passa a
ser a prova de que nada foi escondido) e, de preferência, o remetente
pré-definir em `nova_assinatura.php` os pontos obrigatórios, deixando as marcas
livres como extra.

**pdf-lib vem de CDN externo.** `assinar_via_link.php:504` carrega
`unpkg.com/pdf-lib@1.17.1`. Um fluxo com valor jurídico não deveria depender da
disponibilidade de um CDN de terceiros — se o unpkg cair ou mudar, ninguém
assina. Vale trazer pdf-lib e pdf.js para dentro do repositório no mesmo
trabalho.

**PDF criptografado ou com senha.** O FPDI não abre. Já é limitação hoje; com
carimbo posicionado fica mais visível, então precisa de mensagem clara em vez
de erro genérico.

**Documento muito grande.** PDF.js renderizando 100 páginas em celular antigo
engasga. Renderizar só a página visível, sob demanda.

---

## 4. Fases, como foram entregues

| Fase | Entrega | Estado |
|---|---|---|
| 1 | Tabela de marcas com migração automática; pdf.js e pdf-lib locais; visualizador paginado no lugar do iframe | feito |
| 2 | Editor: adicionar, arrastar, redimensionar, excluir; oito tipos de marca; "rubricar todas as páginas" | feito |
| 3 | Renderização no servidor, dentro da passada do FPDI, antes do `setSignature()`; com e sem ICP; comprovante preservado | feito |
| 4 | Remetente reservando lugares por signatário em `nova_assinatura.php` | feito |
| 5 | Celular (alças maiores, pointer events, zoom), ajuste fino pelas setas, e regressão nos três tipos de assinatura | feito |

---

## 5. Decisão que foi tomada

**Quem posiciona:** os dois. O remetente reserva os lugares obrigatórios e o
signatário acrescenta quantos quiser — a terceira opção da análise original,
que era a recomendada.

O risco que isso cria continua valendo e vale repetir aqui, porque é o que o
jurídico pode cobrar depois: **o signatário pode posicionar um carimbo sobre
uma cláusula.** O que o sistema faz a respeito:

- grava as coordenadas de toda marca, com ip e data, em `assinatura_marcas`;
- mantém sempre a página de comprovante, que passa a ser a prova de que nada
  foi escondido;
- deixa o remetente reservar os lugares, para o caminho normal ser o do
  documento com a assinatura onde a empresa quer.

Se o jurídico quiser **proibir** marcas livres e aceitar só os lugares
reservados, é uma trava de uma linha: deixar de gravar as marcas com
`origem = 'signatario'` em `assinar.php` e esconder a barra de carimbos na tela
de assinatura.
