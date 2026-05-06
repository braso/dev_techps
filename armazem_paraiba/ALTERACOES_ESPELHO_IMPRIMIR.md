# Alterações em espelho_ponto.php - Impressão

## O que foi ajustado

### 1. Impressão individual por espelho
- Cada espelho gerado na consulta passou a ficar dentro de um container com ID próprio.
- Foi adicionado um botão "Imprimir Este Espelho" em cada espelho aberto.
- Esse botão agora usa `data-acao-imprimir='individual'` e recebe `onclick` via ligação direta no carregamento do DOM.

### 2. Impressão de todos os espelhos
- O botão geral de imprimir passou a pegar todos os containers de espelho abertos na tela.
- Na impressão consolidada, cada espelho é clonado separadamente.
- Entre os espelhos impressos foi adicionada quebra de página para manter a separação.
- O botão geral agora usa `data-acao-imprimir='todos'` e também recebe `onclick` programaticamente.

### 3. Escopo das funções de impressão
- O clique deixou de depender de `onclick` inline no HTML renderizado.
- Os botões são ligados por JavaScript após o DOM estar pronto, eliminando a referência quebrada anterior.
- A inicialização da busca automática do select2 foi protegida com verificação de `window.jQuery`.

### 4. Compatibilidade do JavaScript
- O bloco de impressão foi ajustado para evitar sintaxe moderna que poderia impedir a execução no navegador.
- Foram mantidas chamadas simples de função e loops compatíveis com o ambiente da tela.

### 5. Impressão igual ao Ctrl+P (ícones e cores)
- A janela de impressão passou a copiar os mesmos estilos carregados na tela (`link[rel="stylesheet"]` e `style`).
- Com isso, os ícones do espelho e as classes visuais originais são preservados no preview e no PDF.
- Foi adicionado ajuste de impressão para manter cores corretas ao salvar em PDF (`print-color-adjust` e `-webkit-print-color-adjust`).

## Arquivo alterado
- `armazem_paraiba/espelho_ponto.php`
- `armazem_paraiba/ALTERACOES_ESPELHO_IMPRIMIR.md`

## Observação
- Não foram alteradas outras regras da tela além do necessário para a impressão.