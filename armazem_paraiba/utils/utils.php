<?php
/**
 * Configura os botões de Editar (Lupa) e Excluir (Lixeira/SweetAlert)
 * Retorna um array com as tags HTML e o JavaScript necessário.
 */
function gerarAcoesComConfirmacao($arquivoFuncaoEditarExcluir, $funcaoEditar, $funcaoExcluir) {
    
    //Cria a estrutura padrão usando a função do sistema legado
    $actions = criarIconesGrid(
        ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"], 
        [$arquivoFuncaoEditarExcluir, "javascript:void(0)"],
        [$funcaoEditar, $funcaoExcluir]
    );

    // Anula o JS automático do botão Excluir (índice 1)
    $actions["functions"][1] = ""; 

    // Força o HTML da lixeira
    $actions["tags"][1] = '<span class="glyphicon glyphicon-remove search-button" ' . 
                            //Passamos a funçãoExcluir como parâmetro para a função confirmarExclusaoGenerica
                          'onclick="confirmarExclusaoGenerica(this, \'' . $funcaoExcluir . '\')" ' . 
                          'title="Excluir" style="color:#d9534f; cursor:pointer;"></span>';

    // Captura o JS do botão editar
    $jsDoEditar = $actions["functions"][0];

    $jsFunctions = '
        var funcoesInternas = function(){
            try { ' . $jsDoEditar . ' } catch(e) { console.error(e); }
        };

        window.confirmarExclusaoGenerica = function(elemento, acaoPHP){
            var linha = $(elemento).closest("tr");
            var id = linha.find("td:eq(0)").text(); 

            Swal.fire({
                title: "Tem certeza?",
                text: "Excluir registro código: " + id,
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#6c757d",
                confirmButtonText: "Sim, excluir!",
                cancelButtonText: "Cancelar"
            }).then((result) => {
                if (result.isConfirmed) {
                    enviarExclusaoGenerica(id, acaoPHP);
                }
            });
        };

        window.enviarExclusaoGenerica = function(id, acaoPHP){
            var form = document.createElement("form");
            form.method = "POST";
            form.action = ""; 
            
            var fieldAcao = document.createElement("input");
            fieldAcao.type = "hidden";
            fieldAcao.name = "acao";
            fieldAcao.value = acaoPHP; // Aqui está a mágica: usa o nome que veio do PHP
            form.appendChild(fieldAcao);

            var fieldId = document.createElement("input");
            fieldId.type = "hidden";
            fieldId.name = "id";
            fieldId.value = id; 
            form.appendChild(fieldId);

            document.body.appendChild(form);
            form.submit();
        };
    ';

    return [
        "tags" => $actions["tags"],
        "js"   => $jsFunctions
    ];
}

?>