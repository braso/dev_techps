# Modulo Documentos - Guia de Manutencao

## Visao geral
A pasta documentos concentra o modulo de documento dinamico do sistema.
Ele permite:

1. Configurar campos por tipo de documento.
2. Preencher documento com formulario dinamico.
3. Salvar instancia e valores no banco.
4. Gerar PDF a partir da instancia salva.

## Arquivos principais
- cadastro_documento.php
  - Tela principal de gestao das instancias de documento.
  - Acesso para novo documento, listagem e exclusao.
- configurar_layout.php
  - Configura campos do layout de um tipo de documento.
  - Permite editar ordem, tipo, obrigatoriedade, opcoes e logo.
- preencher_documento.php
  - Renderiza formulario dinamico conforme layout configurado.
  - Salva instancia em inst_documento_modulo e valores em valo_documento_modulo.
- processar_pdf.php
  - Carrega instancia e valores e monta o PDF final.
  - Usa a biblioteca TCPDF para renderizacao.
- setup_documentos.php
  - Inicializa estruturas de banco do modulo.
- reparar_banco.php
  - Script de reparo para bases antigas/inconsistentes.

## Banco de dados usado pelo modulo
- camp_documento_modulo: definicao dos campos por tipo.
- inst_documento_modulo: instancia de documento gerado.
- valo_documento_modulo: valor preenchido para cada campo da instancia.

## Fluxo operacional
1. Criar tipo de documento em cadastro de tipo.
2. Configurar campos em configurar_layout.php.
3. Gerar instancia em cadastro_documento.php -> Novo Documento.
4. Preencher campos em preencher_documento.php.
5. Visualizar PDF por processar_pdf.php?id=ID.

## Pergunta comum: quem gera o PDF?
O PDF e gerado pelo arquivo processar_pdf.php.

Detalhe tecnico:
- processar_pdf.php e o controlador do fluxo de geracao.
- TCPDF e a biblioteca que faz o render do documento.

Em resumo:
- processar_pdf.php decide o conteudo e chama a renderizacao.
- TCPDF desenha e entrega o PDF.

## Dicas de manutencao
- Alteracoes visuais de PDF: processar_pdf.php (HTML + classe MYPDF).
- Alteracoes de campos dinamicos: configurar_layout.php.
- Problemas de schema: setup_documentos.php e reparar_banco.php.
- Se um campo nao aparecer no PDF, valide:
  1. campo ativo no layout
  2. valor salvo em valo_documento_modulo
  3. instancia correta em inst_documento_modulo
