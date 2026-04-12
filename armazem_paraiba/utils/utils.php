<?php
/**
 * Configura os botões de Editar (Lupa) e Excluir (Lixeira/SweetAlert)
 * Retorna um array com as tags HTML e o JavaScript necessário.
 */
function gerarAcoesComConfirmacao(
    $arquivoFuncaoEditarExcluir, 
    $nomeAcaoEditar, 
    $nomeAcaoExcluir, 
    $nomeColunaTituloId = "CÓDIGO",
    $templatePadrao = "Deseja excluir o registro {CÓDIGO}?", 
    $colunaCondicao = "", // Ex: FUNCIONÁRIO
    $templateCondicao = "" // Mensagem se tiver funcionário
) {
    
    $actions = criarIconesGrid(
        ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"], 
        [$arquivoFuncaoEditarExcluir, "javascript:void(0)"],
        [$nomeAcaoEditar . "()", ""] 
    );

    // Protege os acentos para o JavaScript
    $encPadrao = rawurlencode($templatePadrao);
    $encCondicao = rawurlencode($templateCondicao);

    $actions["tags"][1] = '<span class="glyphicon glyphicon-remove search-button" ' . 
                          'onclick="confirmarExclusaoGenerica(this, \'' . $nomeAcaoExcluir . '\', \'' . $nomeColunaTituloId . '\', \'' . $encPadrao . '\', \'' . $colunaCondicao . '\', \'' . $encCondicao . '\')" ' . 
                          'title="Excluir" style="color:#d9534f; cursor:pointer;"></span>';

    $jsDoEditar = $actions["functions"][0];

    $jsFunctions = '
        var funcoesInternas = function(){
            try { ' . $jsDoEditar . ' } catch(e) { console.error(e); }
        };

        if (typeof window.confirmarExclusaoGenerica === "undefined") {
            window.confirmarExclusaoGenerica = function(elemento, acaoPHP, nomeColunaTituloId, encPadrao, colunaCondicao, encCondicao){
                var linha = $(elemento).closest("tr");
                var tabela = $(elemento).closest("table");
                
                // Mapeia todas as colunas VISÍVEIS na tela
                var dadosDaLinha = {};
                tabela.find("thead th").each(function(index) {
                    var nomeCol = $(this).text().trim().toUpperCase();
                    var valorCol = linha.find("td").eq(index).text().trim();
                    dadosDaLinha[nomeCol] = valorCol;
                });

                // SALVA-VIDAS Pega o ID direto da raiz do HTML (nunca fica oculto)
                var id = linha.attr("data-row-id"); 
                if (!id || id === "") {
                    id = dadosDaLinha[nomeColunaTituloId.toUpperCase()] || "";
                }
                
                var templateAtivo = decodeURIComponent(encPadrao);
                var templateAlerta = decodeURIComponent(encCondicao);

                // Só tenta usar o template de Alerta se a coluna Condição estiver visível e preenchida
                if (colunaCondicao !== "" && dadosDaLinha[colunaCondicao.toUpperCase()] !== undefined) {
                    var valorCondicao = dadosDaLinha[colunaCondicao.toUpperCase()];
                    if (valorCondicao !== "" && valorCondicao !== "---") {
                        templateAtivo = templateAlerta;
                    }
                }

                // SALVA-VIDAS 2: Fallback Inteligente para o Motor de Templates
                var textoSwal = templateAtivo.replace(/\{([^}]+)\}/g, function(match, nomeVariavel) {
                    var valorDaColuna = dadosDaLinha[nomeVariavel.toUpperCase()];
                    
                    // Se a coluna não existir na tela (oculta), devolve "[Oculto]"
                    if (valorDaColuna === undefined || valorDaColuna === "") {
                        return "<span style=\'color:#999; font-size:0.8em;\'>[Oculto]</span>";
                    }
                    return valorDaColuna;
                });

                Swal.fire({
                    title: "Tem certeza?",
                    html: textoSwal, 
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
                var a = document.createElement("input"); a.type = "hidden"; a.name = "acao"; a.value = acaoPHP; form.appendChild(a);
                var i = document.createElement("input"); i.type = "hidden"; i.name = "id"; i.value = id; form.appendChild(i);
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

// Função genérica para alerta de sucesso na Atualização (OK / Editar Novamente)
function alertaSucessoAtualizacao($titulo, $mensagem, $jsVoltar, $jsEditarNovamente) {
    return "<script>
        Swal.fire({
            title: '{$titulo}',
            text: '{$mensagem}',
            icon: 'success',
            showCancelButton: true,
            confirmButtonColor: '#5cb85c',
            cancelButtonColor: '#f0ad4e',
            confirmButtonText: '<i class=\"fa fa-check\"></i> concluir edição',
            cancelButtonText: '<i class=\"fa fa-pencil\"></i> Continuar editando',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                {$jsVoltar}
            } else {
                {$jsEditarNovamente}
            }
        });
    </script>";
}

// Função genérica para criar um Botão solto na tela que exige confirmação via SweetAlert antes de dar o POST
function botaoAcaoComConfirmacao($idRegistro, $acaoPHP, $textoBotao, $iconeBotao, $classeBotao, $swalTitulo, $swalTexto, $swalIcone = 'question', $corConfirmar = '#5bc0de') {
    // Gera um ID único pro script não dar conflito se você usar 2 botões na mesma tela
    $idFuncao = "confirma_" . uniqid(); 
    
    return "<button type='button' class='{$classeBotao}' onclick=\"{$idFuncao}('{$idRegistro}', '{$acaoPHP}')\"><i class='{$iconeBotao}'></i> {$textoBotao}</button>
    <script>
    function {$idFuncao}(id, acao) {
        Swal.fire({
            title: '{$swalTitulo}',
            text: '{$swalTexto}',
            icon: '{$swalIcone}',
            showCancelButton: true,
            confirmButtonColor: '{$corConfirmar}',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class=\"fa fa-check\"></i> Sim, confirmar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                var f = document.createElement('form');
                f.method = 'POST';
                f.action = ''; // Envia para a própria página
                
                var a = document.createElement('input'); a.type = 'hidden'; a.name = 'acao'; a.value = acao; f.appendChild(a);
                var i = document.createElement('input'); i.type = 'hidden'; i.name = 'id'; i.value = id; f.appendChild(i);
                
                document.body.appendChild(f);
                f.submit();
            }
        });
    }
    </script>";
}

// FUNÇÃO AUXILIAR DE AUDITORIA DO RFID
function registrarLogRfid($id_rfid, $acao, $status_anterior, $status_novo, $entidade_anterior, $entidade_nova, $motivo = "") {
    $id_usuario_logado = isset($_SESSION["user_nb_id"]) ? (int)$_SESSION["user_nb_id"] : 0;
    
    // Proteção contra valores nulos para o banco não dar erro
    $entidade_anterior = $entidade_anterior ? (int)$entidade_anterior : "NULL";
    $entidade_nova = $entidade_nova ? (int)$entidade_nova : "NULL";

    $data_hora_atual = date("Y-m-d H:i:s");

    $sql_log = "INSERT INTO rfids_log 
        (rlog_nb_rfid_id, rlog_tx_acao, rlog_tx_status_anterior, rlog_tx_status_novo, 
        rlog_nb_user_anterior, rlog_nb_user_novo, rlog_tx_motivo, rlog_nb_user_atualiza, rlog_dt_data) 
        VALUES 
        ({$id_rfid}, '{$acao}', '{$status_anterior}', '{$status_novo}', 
        {$entidade_anterior}, {$entidade_nova}, '{$motivo}', {$id_usuario_logado}, '{$data_hora_atual}')";
        
    query($sql_log);
};


?>