<?php
/**
 * Configura os botões de Editar (Lupa) e Excluir (Lixeira/SweetAlert)
 * Retorna um array com as tags HTML e o JavaScript necessário.
 */
function gerarAcoesComConfirmacao(
    $arquivoFuncaoEditarExcluir, 
    $nomeAcaoEditar, 
    $nomeAcaoExcluir, 
    $mensagemExcluir = "Excluir registro código: ",
    $nomeColunaTituloId = "CÓDIGO" // Parâmetro novo: O texto do cabeçalho da coluna de ID
) {
    
    // Cria a estrutura padrão usando a função do sistema legado
    $actions = criarIconesGrid(
        ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"], 
        [$arquivoFuncaoEditarExcluir, "javascript:void(0)"],
        [$nomeAcaoEditar . "()", ""] 
    );

    // Força o HTML da lixeira passando os parâmetros limpos
    $actions["tags"][1] = '<span class="glyphicon glyphicon-remove search-button" ' . 
                          'onclick="confirmarExclusaoGenerica(this, \'' . $nomeAcaoExcluir . '\', \'' . $nomeColunaTituloId . '\')" ' . 
                          'title="Excluir" style="color:#d9534f; cursor:pointer;"></span>';

    $jsDoEditar = $actions["functions"][0];

    $jsFunctions = '
        var funcoesInternas = function(){
            try { ' . $jsDoEditar . ' } catch(e) { console.error(e); }
        };

        if (typeof window.confirmarExclusaoGenerica === "undefined") {
            window.confirmarExclusaoGenerica = function(elemento, acaoPHP, nomeColunaTituloId){
                var linha = $(elemento).closest("tr");
                var tabela = $(elemento).closest("table");
                
                // O "Radar": Descobre em qual posição a coluna do ID está agora
                var indexDaColuna = -1;
                tabela.find("thead th").each(function(index) {
                    // Remove espaços e ignora maiúsculas/minúsculas para evitar erros de digitação
                    if ($(this).text().trim().toUpperCase() === nomeColunaTituloId.toUpperCase()) {
                        indexDaColuna = index;
                        return false; // Interrompe o loop ao encontrar
                    }
                });

                // Proteção (Fallback): Se por algum motivo bizarro a coluna não existir, volta pro padrão 0
                if (indexDaColuna === -1) {
                    console.warn("Coluna " + nomeColunaTituloId + " não encontrada. Usando a primeira coluna.");
                    indexDaColuna = 0; 
                }

                // Pega o ID usando a posição correta que acabamos de descobrir
                var id = linha.find("td").eq(indexDaColuna).text().trim(); 

                Swal.fire({
                    title: "Tem certeza?",
                    text: "' . $mensagemExcluir . '" + id,
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
        }

        if (typeof window.enviarExclusaoGenerica === "undefined") {
            window.enviarExclusaoGenerica = function(id, acaoPHP){
                var form = document.createElement("form");
                form.method = "POST";
                form.action = ""; 
                
                var fieldAcao = document.createElement("input");
                fieldAcao.type = "hidden";
                fieldAcao.name = "acao";
                fieldAcao.value = acaoPHP; 
                form.appendChild(fieldAcao);

                var fieldId = document.createElement("input");
                fieldId.type = "hidden";
                fieldId.name = "id";
                fieldId.value = id; 
                form.appendChild(fieldId);

                document.body.appendChild(form);
                form.submit();
            };
        }
    ';
    return [
        "tags" => $actions["tags"],
        "js"   => $jsFunctions 
    ];
};

// Função para gerar o alerta de sucesso duplo (Cadastrar Mais / Voltar para o Grid)
function alertaSucessoCadastro($titulo, $mensagem, $acaoCadastrarMais, $urlVoltar) {
    return "<script>
        Swal.fire({
            title: '{$titulo}',
            text: '{$mensagem}',
            icon: 'success',
            showCancelButton: true,
            confirmButtonColor: '#5cb85c',
            cancelButtonColor: '#f0ad4e',
            confirmButtonText: '<i class=\"fa fa-plus\"></i> Cadastrar mais',
            cancelButtonText: '<i class=\"fa fa-list\"></i> Voltar para o grid',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                var f = document.createElement('form');
                f.method = 'POST';
                f.action = ''; // Recarrega a própria página
                var a = document.createElement('input');
                a.type = 'hidden'; a.name = 'acao'; a.value = '{$acaoCadastrarMais}';
                f.appendChild(a);
                document.body.appendChild(f);
                f.submit();
            } else {
                window.location.href = '{$urlVoltar}';
            }
        });
    </script>";
}

// FUNÇÃO AUXILIAR DE AUDITORIA DO RFID
function registrarLogRfid($id_rfid, $acao, $status_anterior, $status_novo, $entidade_anterior, $entidade_nova, $motivo = "") {
    $id_usuario_logado = isset($_SESSION["user_nb_id"]) ? (int)$_SESSION["user_nb_id"] : 0;
    
    // Proteção contra valores nulos para o banco não dar erro
    $entidade_anterior = $entidade_anterior ? (int)$entidade_anterior : "NULL";
    $entidade_nova = $entidade_nova ? (int)$entidade_nova : "NULL";

    $sql_log = "INSERT INTO rfids_log 
        (rlog_nb_rfid_id, rlog_tx_acao, rlog_tx_status_anterior, rlog_tx_status_novo, 
        rlog_nb_entidade_anterior, rlog_nb_entidade_nova, rlog_tx_motivo, rlog_nb_user_atualiza) 
        VALUES 
        ({$id_rfid}, '{$acao}', '{$status_anterior}', '{$status_novo}', 
        {$entidade_anterior}, {$entidade_nova}, '{$motivo}', {$id_usuario_logado})";
        
    query($sql_log);
};
?>