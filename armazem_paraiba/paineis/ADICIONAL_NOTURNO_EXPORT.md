# Documentação Técnica: Módulo de Exportação de Adicional Noturno (TXT)

## 1. Visão Geral
Este módulo foi desenvolvido para permitir a exportação dos valores de Adicional Noturno apurados no sistema de ponto para arquivos de texto (.txt), visando a integração facilitada com sistemas de folha de pagamento externos. A funcionalidade gera um arquivo de largura fixa seguindo rigorosamente as especificações de importação de lançamentos.

## 2. Fluxo de Funcionamento

### 2.1 Interface e Captura (Frontend)
Localizado em `endosso.php`, o processo é iniciado através do botão "Baixar TXT Ad.Not.".

*   **Filtragem Dinâmica**: O script percorre as linhas da tabela de resultados (`#tabela-empresas`), extraindo dados apenas de registros com status "E" (Endossado) ou "EP" (Endossado Parcialmente).
*   **Saneamento de Dados**: No JavaScript, o valor do Adicional Noturno é capturado e limpo (remoção do caractere `:`) para garantir que o backend receba apenas inteiros representando os minutos ou o formato numérico bruto.
*   **Transferência de Dados**: Os dados são serializados em formato JSON e enviados via método `POST` para o script de processamento, utilizando um formulário criado dinamicamente para contornar limitações de tamanho de URL (`GET`).

### 2.2 Processamento e Geração (Backend)
O arquivo `export_adicional_noturno.php` gerencia a criação do arquivo físico.

*   **Controle de Cache**: Para garantir que o arquivo baixado seja sempre a versão mais recente e evitar problemas em navegadores baseados em Chromium, são aplicados cabeçalhos rigorosos de expiração e revalidação:
    ```php
    header("Expires: 01 Jan 2001 00:00:00 GMT");
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header('Cache-Control: post-check=0, pre-check=0', FALSE);
    header('Pragma: no-cache');
    ```
*   **Codificação**: O arquivo é gerado em `ISO-8859-1` (ANSI), garantindo compatibilidade com sistemas legados de folha de pagamento que frequentemente não suportam UTF-8.

## 3. Especificação do Layout (TXT)

O arquivo é composto por registros de **48 caracteres** por linha, com quebra de linha padrão Windows (`\r\n`).

| Posição | Campo | Tamanho | Conteúdo / Regra |
| :--- | :--- | :--- | :--- |
| 001 - 002 | Tipo de Registro | 2 | Fixo "10" |
| 003 - 012 | Matrícula | 10 | Alinhado à direita, preenchido com zeros à esquerda |
| 013 - 018 | Competência | 6 | Formato AAAAMM |
| 019 - 027 | Código da Rubrica | 9 | Fixo "25" (zero-filled) |
| 028 - 029 | Tipo de Processo | 2 | Fixo "11" |
| 030 - 038 | Valor Ad. Noturno | 9 | Valor bruto, preenchido com zeros à esquerda |
| 039 - 048 | Código da Empresa | 10 | Fixo "210" (zero-filled) |

## 4. Implementação de Segurança e Estabilidade

*   **Prevenção de Corrupção de Download**: O uso de `ob_end_clean()` antes do envio dos cabeçalhos garante que nenhum resíduo de saída (warnings ou espaços em branco) corrompa o binário do arquivo ou altere o `Content-Length`.
*   **Cálculo de Content-Length**: O tamanho do arquivo é calculado precisamente sobre a string final via `strlen()`, evitando que o navegador interrompa o download ou exiba erros de rede.
*   **Validação de Matrícula**: O sistema sanitiza a matrícula para remover caracteres não numéricos e trunca o valor para 10 dígitos antes da aplicação do `str_pad`, prevenindo quebras de layout.

## 5. Manutenção e Suporte

### Localização dos Arquivos
*   **Frontend**: `armazem_paraiba/paineis/endosso.php`
*   **Backend**: `armazem_paraiba/paineis/export_adicional_noturno.php`

### Dependências
*   Bibliotecas: jQuery (para manipulação de DOM/AJAX no frontend).
*   Servidor: PHP 7.4 ou superior com suporte a `mbstring` e funções de buffer de saída.

---
*Documento gerado para fins de documentação de projeto e transferência de conhecimento.*