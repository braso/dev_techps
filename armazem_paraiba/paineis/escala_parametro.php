<?php

require "../funcoes_ponto.php";

function index() {
    cabecalho("Relatório Escala por Parâmetro");

    if (empty($_POST["busca_dataMes"])) {
        $_POST["busca_dataMes"] = date("Y-m");
    }

    $temSubsetorVinculado = false;
    if (!empty($_POST["busca_setor"])) {
        $rowCount = mysqli_fetch_array(query("SELECT COUNT(*) FROM sbgrupos_documentos WHERE sbgr_tx_status = 'ativo' AND sbgr_nb_idgrup = ".intval($_POST["busca_setor"]).";"));
        $temSubsetorVinculado = ($rowCount[0] > 0);
    }

    $campos = [
        combo_net("Empresa", "empresa", $_POST["empresa"] ?? $_SESSION["user_nb_empresa"], 3, "empresa", ""),
        campo_mes("Mês*", "busca_dataMes", ($_POST["busca_dataMes"] ?? date("Y-m")), 2),
        campo("Nome", "busca_nome", ($_POST["busca_nome"] ?? ""), 3),
        combo("Ocupação", "busca_ocupacao", ($_POST["busca_ocupacao"] ?? ""), 2, 
            ["" => "Todos", "Motorista" => "Motorista", "Ajudante" => "Ajudante", "Funcionário" => "Funcionário"]),
        combo_bd("!Parâmetros da Jornada", "busca_parametro", ($_POST["busca_parametro"] ?? ""), 2, "parametro"),
        combo_bd("!Cargo", "operacao", ($_POST["operacao"]?? ""), 2, "operacao", "", "ORDER BY oper_tx_nome ASC"),
        combo_bd("!Setor", "busca_setor", ($_POST["busca_setor"]?? ""), 2, "grupos_documentos", "onchange=\"(function(f){ if(f.busca_subsetor){ f.busca_subsetor.value=''; } f.reloadOnly.value='1'; f.submit(); })(document.contex_form);\"")
    ];
    if ($temSubsetorVinculado) {
        $campos[] = combo_bd("!Subsetor", "busca_subsetor", ($_POST["busca_subsetor"]?? ""), 2, "sbgrupos_documentos", "", " AND sbgr_nb_idgrup = ".intval($_POST["busca_setor"])." ORDER BY sbgr_tx_nome ASC");
    }

    $buttons = [
        botao("Buscar", "", "", "", "", "", "btn btn-info")
    ];

    echo abre_form();
    echo campo_hidden("reloadOnly", "");
    echo linha_form($campos);
    echo fecha_form($buttons);

    if (!empty($_POST["empresa"]) && !empty($_POST["busca_dataMes"]) && empty($_POST["reloadOnly"])) {
        try {
            $periodoInicio = new DateTime($_POST["busca_dataMes"] . "-01");
            $periodoFim = new DateTime($periodoInicio->format("Y-m-t"));
        } catch (Exception $e) {
            set_status("Mês inválido.");
            rodape();
            return;
        }

        $filtroNome = "";
        if (!empty($_POST["busca_nome"])) {
            $filtroNome = " AND entidade.enti_tx_nome LIKE '%".$_POST["busca_nome"]."%'";
        }
        $filtroMatricula = "";
        if (!empty($_POST["busca_matricula"])) {
            $filtroMatricula = " AND entidade.enti_tx_matricula = '".$_POST["busca_matricula"]."'";
        }
        $filtroOcupacao = "";
        if (!empty($_POST["busca_ocupacao"])) {
            $filtroOcupacao = " AND entidade.enti_tx_ocupacao = '".$_POST["busca_ocupacao"]."'";
        }
        $filtroOperacao = "";
        if (!empty($_POST["operacao"])) {
            $filtroOperacao = " AND operacao.oper_nb_id = ".intval($_POST["operacao"]);
        }
        $filtroParametro = "";
        if (!empty($_POST["busca_parametro"])) {
            $filtroParametro = " AND entidade.enti_nb_parametro = ".intval($_POST["busca_parametro"]);
        }
        $filtroSetor = "";
        if (!empty($_POST["busca_setor"])) {
            $filtroSetor = " AND entidade.enti_setor_id = ".intval($_POST["busca_setor"]);
        }
        $filtroSubSetor = "";
        if (!empty($_POST["busca_subsetor"])) {
            $filtroSubSetor = " AND entidade.enti_subSetor_id = ".intval($_POST["busca_subsetor"]);
        }

        $motoristas = mysqli_fetch_all(query(
            "SELECT entidade.*, 
                    empresa.empr_nb_parametro,
                    operacao.oper_tx_nome,
                    grupos_documentos.grup_tx_nome,
                    sbgrupos_documentos.sbgr_tx_nome
             FROM entidade
             LEFT JOIN empresa ON entidade.enti_nb_empresa = empresa.empr_nb_id
             LEFT JOIN operacao ON oper_nb_id = entidade.enti_tx_tipoOperacao
             LEFT JOIN grupos_documentos ON grup_nb_id = entidade.enti_setor_id
             LEFT JOIN sbgrupos_documentos ON sbgr_nb_id = entidade.enti_subSetor_id
             WHERE entidade.enti_tx_status = 'ativo'
               AND entidade.enti_nb_empresa = " . intval($_POST["empresa"]) . "
               ".$filtroNome."
               ".$filtroMatricula."
               ".$filtroOcupacao."
               ".$filtroOperacao."
               ".$filtroParametro."
               ".$filtroSetor."
               ".$filtroSubSetor."
             ORDER BY entidade.enti_tx_nome ASC;"
        ), MYSQLI_ASSOC);

        if (empty($motoristas)) {
            set_status("Nenhum funcionário encontrado para a empresa selecionada.");
            rodape();
            return;
        }

        $cabecalho = ["Matrícula", "Nome", "Ocupação", "Cargo", "Setor", "SubSetor"];
        $diasMes = (int)$periodoFim->format("d");
        $diasSemana = ["DOM", "SEG", "TER", "QUA", "QUI", "SEX", "SAB"];
        for ($dia = 1; $dia <= $diasMes; $dia++) {
            $dataHeader = clone $periodoInicio;
            $dataHeader->modify("+" . ($dia - 1) . " day");
            $sigla = $diasSemana[(int)$dataHeader->format("w")];
            $cabecalho[] = $dataHeader->format("d") . " " . $sigla;
        }

        $valores = [];

        foreach ($motoristas as $motorista) {
            $row = [
                "matricula" => $motorista["enti_tx_matricula"],
                "nome" => $motorista["enti_tx_nome"],
                "ocupacao" => $motorista["enti_tx_ocupacao"],
                "cargo" => $motorista["oper_tx_nome"] ?? "",
                "setor" => $motorista["grup_tx_nome"] ?? "",
                "subsetor" => $motorista["sbgr_tx_nome"] ?? ""
            ];

            for ($data = clone $periodoInicio; $data <= $periodoFim; $data->modify("+1 day")) {
                $dataStr = $data->format("Y-m-d");
                $diaPonto = diaDetalhePonto($motorista, $dataStr);
                $inicioEscala = $diaPonto["inicioEscala"] ?? "";
                $fimEscala = $diaPonto["fimEscala"] ?? "";

                $inicio = (!empty($inicioEscala) && $inicioEscala !== "00:00" && $inicioEscala !== "00:00:00") ? substr($inicioEscala, 0, 5) : "--:--";
                $fim = (!empty($fimEscala) && $fimEscala !== "00:00" && $fimEscala !== "00:00:00") ? substr($fimEscala, 0, 5) : "--:--";
                $valor = ($inicio === "--:--" && $fim === "--:--") ? "----" : $inicio . " - " . $fim;

                $key = "dia_" . $data->format("d");
                $row[$key] = $valor;
            }

            $valores[] = $row;
        }

        echo "<div class='row'><div class='col-sm-12'>";
        echo "<style>
.tabela-espelho-ponto th,
.tabela-espelho-ponto td{
    font-size:9px !important;
    padding:2px 3px !important;
    white-space:nowrap;
}
.tabela-espelho-ponto td.nome{
    max-width:160px;
    overflow:hidden;
    text-overflow:ellipsis;
}
.tabela-espelho-ponto th.current-day,
.tabela-espelho-ponto td.current-day{
    background-color:#d4edda !important;
}
.tabela-espelho-ponto tr.selected-row td{
    background-color:#fff3cd !important;
}
.tabela-espelho-ponto tr.selected-row td.current-day{
    background-color:#c3e6cb !important;
}
</style>";
        echo "<div id='escala-scroll-top' style='overflow-x:auto; overflow-y:hidden; margin-bottom:5px;'><div style='height:1px;'></div></div>";
        echo "<div id='escala-grid-wrapper'>";
        $gridHtml = montarTabelaPonto($cabecalho, $valores);
        $gridHtml = str_replace("(*): Registros excluídos manualmente.<br>", "", $gridHtml);
        $gridHtml = str_replace("(**): 00:00 Caso esteja dentro da tolerância", "", $gridHtml);
        echo $gridHtml;
        echo "</div>";
        echo "</div></div>";
        echo "<script>
        (function(){
            var wrapper = document.getElementById('escala-grid-wrapper');
            if(!wrapper) return;
            var tableResp = wrapper.querySelector('.table-responsive');
            if(!tableResp) return;
            var tabela = tableResp.querySelector('table');
            var topScroll = document.getElementById('escala-scroll-top');
            if(!tabela || !topScroll) return;
            var inner = topScroll.firstElementChild;
            inner.style.width = tabela.scrollWidth + 'px';
            topScroll.onscroll = function(){ tableResp.scrollLeft = topScroll.scrollLeft; };
            tableResp.onscroll = function(){ topScroll.scrollLeft = tableResp.scrollLeft; };
            window.addEventListener('resize', function(){
                inner.style.width = tabela.scrollWidth + 'px';
            });

            var hoje = new Date();
            var mesSelecionado = '".($_POST["busca_dataMes"] ?? date("Y-m"))."';
            var mesHoje = hoje.getFullYear() + '-' + ('0' + (hoje.getMonth() + 1)).slice(-2);
            if(mesSelecionado === mesHoje){
                var diaAtual = hoje.getDate();
                var colunasFixas = 6;
                var indiceColuna = colunasFixas + diaAtual - 1;
                var thsHoje = tabela.querySelectorAll('thead tr th');
                if(thsHoje.length > indiceColuna){
                    thsHoje[indiceColuna].classList.add('current-day');
                    var linhasHoje = tabela.querySelectorAll('tbody tr');
                    for(var i = 0; i < linhasHoje.length; i++){
                        var cellsHoje = linhasHoje[i].querySelectorAll('td');
                        if(cellsHoje.length > indiceColuna){
                            cellsHoje[indiceColuna].classList.add('current-day');
                        }
                    }
                }
            }

            var linhasBody = tabela.querySelectorAll('tbody tr');
            for(var j = 0; j < linhasBody.length; j++){
                linhasBody[j].addEventListener('click', function(){
                    for(var k = 0; k < linhasBody.length; k++){
                        linhasBody[k].classList.remove('selected-row');
                    }
                    this.classList.add('selected-row');
                });
            }

            var ths = tabela.querySelectorAll('thead tr th');
            var tbody = tabela.querySelector('tbody');
            if(ths.length && tbody){
                function getCellValue(row, index){
                    var cell = row.cells[index];
                    return cell ? cell.textContent.trim() : '';
                }

                function parseHora(valor){
                    var match = valor.match(/^(\d{2}):(\d{2})/);
                    if(!match){ return null; }
                    return parseInt(match[1],10)*60 + parseInt(match[2],10);
                }

                function comparar(index, asc){
                    return function(a, b){
                        var v1 = getCellValue(asc ? a : b, index);
                        var v2 = getCellValue(asc ? b : a, index);

                        var t1 = parseHora(v1);
                        var t2 = parseHora(v2);
                        if(t1 !== null && t2 !== null){
                            return t1 - t2;
                        }

                        return v1.localeCompare(v2, 'pt-BR', {numeric:true, sensitivity:'base'});
                    };
                }

                for(var h = 0; h < ths.length; h++){
                    (function(idx){
                        ths[idx].style.cursor = 'pointer';
                        ths[idx].addEventListener('click', function(){
                            var atual = this.getAttribute('data-order') || 'desc';
                            var novo = (atual === 'asc') ? 'desc' : 'asc';
                            for(var x = 0; x < ths.length; x++){
                                ths[x].removeAttribute('data-order');
                            }
                            this.setAttribute('data-order', novo);

                            var linhas = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
                            linhas.sort(comparar(idx, novo === 'asc'));
                            for(var y = 0; y < linhas.length; y++){
                                tbody.appendChild(linhas[y]);
                            }
                        });
                    })(h);
                }
            }
        })();
        </script>";
    }

    rodape();
}
