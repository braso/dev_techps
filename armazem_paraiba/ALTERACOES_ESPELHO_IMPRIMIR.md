# Alterações em espelho_ponto.php - Impressão

## O que foi ajustado

### 5. Impressão igual ao Ctrl+P (ícones e cores)
- A janela de impressão passou a copiar os mesmos estilos carregados na tela (`link[rel="stylesheet"]` e `style`).
- Com isso, os ícones do espelho e as classes visuais originais são preservados no preview e no PDF.
- Foi adicionado ajuste de impressão para manter cores corretas ao salvar em PDF (`print-color-adjust` e `-webkit-print-color-adjust`).

### 6. Botão de impressão individual
- O que foi alterado: adicionada interface para imprimir um único espelho (cada `portlet`) sem alterar a função de imprimir todos.
- Como funciona: um botão é injetado dinamicamente em cada `portlet` que contenha `.espelho-cabecalho-info`. Ao clicar, a função `imprimirIndividual(btn)` clona o `portlet`, remove botões de impressão do clone, monta um HTML de impressão (cabeçalho + conteúdo + rodapé), injeta os mesmos estilos da página atual e abre o preview de impressão (mesmo comportamento do imprimir todos).
- Arquivo alterado: `armazem_paraiba/espelho_ponto.php`
- Função/JS implementado: `imprimirIndividual(btn)` e um inicializador que adiciona o botão em cada portlet. O botão usa `data-acao-imprimir='individual'`.

### 7. Melhoria visual do botão de imprimir individual
- O botão individual foi tornado mais visível: classe `btn btn-sm btn-primary`, texto "Imprimir" junto ao ícone `glyphicon-print`, margem e alinhamento aplicados via `style` inline para garantir boa apresentação em vários templates.
- Arquivo alterado: `armazem_paraiba/espelho_ponto.php`

### 8. Observações sobre comportamento e compatibilidade
- A impressão individual reutiliza as mesmas técnicas aplicadas à impressão consolidada (copiar estilos, forçar ajuste de cores), garantindo que o preview e o PDF mantenham cores, ícones e tipografia equivalentes ao Ctrl+P.
- A implementação evita duplicar botões caso a página já possua um controle similar (verificação via `querySelector`).

### 9. Alteração no filtro de Empresa (página de Perfis de Usuário)
- O que foi alterado: quando há apenas uma empresa ativa disponível, o filtro `Empresa` é preenchido automaticamente e o texto do toggle do filtro passa a mostrar o nome da empresa selecionada.
- Como funciona: ao montar `$optsEmpresas`, se houver exatamente 1 opção e `busca_empresa` estiver vazio, o código define `$_POST['busca_empresa']` com o id único; o render do filtro mostra o rótulo quando houver exatamente um selecionado.
- Arquivo alterado: `armazem_paraiba/cadastro_usuario_perfil.php`
- Funções/trechos alterados: lógica de auto-seleção e ajuste do texto exibido no toggle do `renderFiltroCheckboxGroup()`.

## Arquivos alterados (resumo)
- `armazem_paraiba/espelho_ponto.php` — impressão consolidada já existente; adição de: copiar estilos da página para a janela de impressão, função `imprimirIndividual(btn)`, injeção do botão por portlet e melhoria visual do botão.
- `armazem_paraiba/css/impressao_espelho.css` — adaptação para cabeçalho/rodapé em fluxo por página (evitar `position: fixed` que quebrava entre páginas), definições de `.print-page`, `.print-header`, `.print-footer`.
- `armazem_paraiba/ALTERACOES_ESPELHO_IMPRIMIR.md` — este arquivo (atualizado).
- `armazem_paraiba/cadastro_usuario_perfil.php` — auto-seleção do filtro `Empresa` quando houver apenas 1 opção e exibição do nome no toggle.

## Notas finais
- Não foram alteradas regras de negócio além das mencionadas; mudanças focadas em experiência de impressão e usabilidade do filtro.
- Caso queira, eu posso:
	- Mover o botão visualmente para a área de `portlet-title .tools` (mais à direita);
	- Marcar visualmente os checkboxes no dropdown do filtro quando a empresa for auto-selecionada (atualmente o toggle mostra o nome).
