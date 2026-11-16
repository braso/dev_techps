# Módulo de Assinatura: Suporte à Preservação de Assinaturas Prévias (PAdES Incremental)

Este documento descreve a implementação da funcionalidade de **Assinatura Incremental** e preservação de assinaturas digitais pré-existentes no módulo de assinatura eletrônica e digital do TechPS (`armazem_paraiba/assinatura/`).

---

## 1. Diagnóstico do Problema

Quando um documento PDF que já continha uma assinatura digital anterior (como **Gov.br**, **ICP-Brasil**, **DocMDP** ou assinaturas PAdES/PKCS#7) era assinado pelo módulo do TechPS, a assinatura anterior era removida ou invalidada pelos seguintes motivos:

1. **Back-end legado (`marcas_helper.php` / FPDI + TCPDF):**
   * O FPDI (`setasign\Fpdi\Tcpdf\Fpdi`) não edita PDFs; ele importa as páginas apenas como imagens/conteúdo gráfico (`Form XObject`) dentro de um novo PDF em branco gerado pelo TCPDF. Nesse processo, os dicionários `/Type /Sig`, `/AcroForm`, `/ByteRange` e anotações visuais da assinatura Gov.br na página 1 eram completamente eliminados.
2. **Finalização ICP legada (`processar_finalizacao.php`):**
   * O fluxo padrão de certificação A1 utilizava FPDI para importar o documento e gerar uma nova assinatura TCPDF, recriando o arquivo do zero e descartando as assinaturas originais.

---

## 2. Solução Implementada

Conforme os requisitos do sistema:
* **Flag de Controle do Gestor (Desmarcada por Padrão):**
  * A decisão é exclusivamente dos gestores no momento do envio; o signatário final que acessa o link não tem essa opção e assina diretamente.
  * **Por padrão, a flag vem DESMARCADA (`nao` / `false`)**, mantendo 100% o fluxo original e padrão de funcionamento do sistema.
  * O campo está disponível exclusivamente para os gestores na criação de solicitações em:
    * **Modo Lote** (separar por funcionário) em `nova_assinatura.php`
    * **Modo Avulso** (documento individual) em `nova_assinatura.php`
    * **Modo Signatário Externo** em `nova_assinatura.php`
    * **Governança / Solicitações em Bloco** em `governanca.php`
* **Fluxo padrão 100% mantido (Flag desmarcada):**
  * Qualquer documento enviado com a flag desmarcada continua usando exatamente o fluxo original via TCPDF/FPDI com carimbo visual único, sem nenhuma alteração.
* **Ativação condicional (Flag marcada + assinatura detectada):**
  * Se e somente se a flag estiver marcada (`sim`) E o PDF contiver uma assinatura digital anterior (Gov.br, ICP-Brasil, etc.), o sistema ativa condicionalmente o motor de **Assinatura Incremental PAdES**.
* **Coleta integral da assinatura do funcionário:**
  * A folha de **COMPROVANTES DE ASSINATURA ELETRÔNICA** é gerada contendo os dados completos do signatário: Nome, CPF, RG, Data/Hora, IP, Hash de protocolo, e a imagem da **Rubrica** desenhada.
  * O carimbo visual do Gov.br na página 1 é mantido 100% intacto.
  * O carimbo visual ICP-Brasil com QR Code é posicionado no canto inferior direito da página de comprovantes.
* **Integração completa com as consultas e pastas:**
  * O arquivo intermediário é gravado em `docAssinado/assinado_{time}_{protocolo}.pdf`.
  * O arquivo final com ambas as assinaturas é gravado em `docFinalizado/final_{time}_{uniqid}.pdf`.
  * O documento é automaticamente copiado para a pasta do colaborador em `arquivos/Funcionarios/{entidadeId}/`.
  * Os registros no banco de dados (`solicitacoes_assinatura`, `assinantes`, `documento_funcionario`, `assinatura_eletronica`) são atualizados mantendo o status `finalizado`.
  * Na tela de consulta (`consultar.php`), solicitações com essa flag ativa recebem a etiqueta/badge visual `Múltiplas assinaturas`.

---

## 3. Arquitetura e Componentes

```
armazem_paraiba/assinatura/
├── assinatura_incremental_helper.php   (Detecção de assinaturas prévias e invocação do motor Python)
├── assinar_incremental.py             (Motor pyHanko para PAdES incremental com carimbo QR Code)
├── marcas_helper.php                  (Bypass do FPDI destrutivo quando detectada assinatura digital)
├── assinar.php                        (Recebimento do arquivo com a página de comprovantes do colaborador)
├── assinar_via_link.php               (Detecção e flag informativa ao front-end)
├── script.js                          (Geração da folha de comprovantes/rubrica com pdf-lib)
└── processar_finalizacao.php          (Roteamento condicional para o motor incremental pyHanko)
```

### Detalhes Técnicos dos Componentes

### 1. `assinatura_incremental_helper.php`
* **`assinatura_pdfTemAssinaturaDigital(string $caminhoAbsoluto): bool`**:
  Analisa o arquivo PDF em busca dos marcadores obrigatórios de assinatura digital (`/ByteRange`, `/Sig`, `pkcs7`, `ETSI.CAdES`, `Adobe.PPKLite`). Retorna `true` se o PDF já estiver assinado digitalmente.
* **`assinatura_encontrarPython(): ?string`**:
  Localiza o executável do Python 3 no servidor (`python3`, `python`, caminhos no Windows e `/usr/bin/python3` no Alpine Linux Docker).
* **`assinatura_executarAssinaturaIncremental(...)`**:
  Invoca o script `assinar_incremental.py` passando os parâmetros de entrada, saída, certificado `.pfx`, senha e metadados.

### 2. `assinar_incremental.py`
* Utiliza a biblioteca **pyHanko** (`IncrementalPdfFileWriter` com `strict=False` para suportar seções *hybrid cross-reference* geradas pelo Gov.br).
* Aplica a nova assinatura digital ICP-Brasil como revisão incremental (Revision 2), preservando a Revision 1 (Gov.br) intacta conforme ISO 32000-1 / PAdES.
* Renderiza o carimbo visual com QR Code apontando para o validador ITI (`https://validar.iti.gov.br/`), nome do signatário, data e hora, respeitando as dimensões da página final.

### 3. `script.js`
* O `pdf-lib` carrega os bytes do PDF e executa `renderAuditPages()`, criando a página de "COMPROVANTES DE ASSINATURA ELETRÔNICA" com os dados do colaborador e sua rubrica gráfica.
* As anotações existentes (incluindo o widget `/Sig` e carimbo visual do Gov.br na página 1) são preservadas.
* O arquivo com a nova página é enviado via POST multipart como `arquivo_assinado`.

### 4. `assinar.php`
* Recebe `arquivo_assinado` e salva em `docAssinado/assinado_{time}_{protocolo}.pdf`.
* Registra os metadados do signatário na tabela `assinatura_eletronica` e atualiza `assinantes` como `assinado`.
* Dispara a finalização do documento via `assinatura_finalizar_documento_icp()`.

### 5. `marcas_helper.php`
* Na função `assinaturaMarcas_aplicarNoPdf`, verifica se `preservar_assinaturas === 'sim'` E se `assinatura_pdfTemAssinaturaDigital` é `true`. Apenas se ambas forem verdadeiras, evita passar pelo FPDI. Caso a flag esteja desmarcada (`nao`), segue o fluxo normal com FPDI.

### 6. `processar_finalizacao.php`
* Verifica se `preservar_assinaturas === 'sim'` E se `assinatura_pdfTemAssinaturaDigital($inputPdfPath)` é `true`:
  * **Se a flag for `nao` (padrão):** Executa o fluxo original com TCPDF e FPDI (100% mantido).
  * **Se a flag for `sim` E houver assinatura prévia:** Executa `assinatura_executarAssinaturaIncremental()` via pyHanko, gravando o arquivo em `docFinalizado/final_...pdf` com ambas as assinaturas válidas preservadas.
* O arquivo finalizado é copiado para `arquivos/Funcionarios/{entidadeId}/`, e o registro em `documento_funcionario` é atualizado como `docu_tx_assinado = 'sim'`.

---

## 4. Resultado dos Testes End-to-End

### Cenário 1: Flag Desmarcada (`preservar_assinaturas = 'nao'`) - Padrão do Sistema
* **Comportamento verificado:** Execução 100% via fluxo original TCPDF/FPDI.
* **Resultado:** Assinatura digital única do sistema aplicada, mantendo o comportamento pré-existente.

### Cenário 2: Flag Marcada (`preservar_assinaturas = 'sim'`) com PDF assinado previamente (Gov.br)
* **Documento testado:** `1NR35_FRANCISCO_CARLOS_CASEMIRO_DE_SOUZ.pdf` (Assinado originalmente pelo Gov.br).
* **Fluxo de teste executado:**
  1. Criação da solicitação com a flag ativada.
  2. Coleta dos dados do colaborador (Nome, CPF, RG e rubrica).
  3. Geração da página de comprovantes de assinatura eletrônica via `pdf-lib`.
  4. Finalização ICP-Brasil incremental via pyHanko.
* **Resultado verificado:**
  * **Páginas:** 3 páginas no total (Página 1: Certificado com carimbo Gov.br intacto; Página 2: Conteúdo programático; Página 3: Comprovante de assinatura do colaborador + Carimbo digital ICP-Brasil com QR Code).
  * **Assinatura 1:** `Signature1` (Gov.br) → **Preservada**.
  * **Assinatura 2:** `SigTechPS` (TechPS ICP-Brasil) → **Preservada e Válida**.
  * **Total de assinaturas embutidas:** 2 assinaturas ativas no PDF final.
  * **Cópia para o funcionário:** Salvo com sucesso em `armazem_paraiba/arquivos/Funcionarios/2/`.
  * **Status no banco:** `solicitacoes_assinatura` e `documento_funcionario` marcados como `assinado` / `finalizado`.
