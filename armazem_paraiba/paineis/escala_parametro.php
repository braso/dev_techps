<?php
	
require "../funcoes_ponto.php";

function index() {
    // Verificação e criação da coluna turno se não existir
    if(mysqli_num_rows(query("SHOW COLUMNS FROM parametro LIKE 'para_tx_turno'")) == 0){
        query("ALTER TABLE parametro ADD COLUMN para_tx_turno CHAR(1) COMMENT 'M-Manhã, T-Tarde, V-Vespertino, N-Noite, D-Diurno'");
    }

    cabecalho("Relatório Escala por Parâmetro");

    $buscaDataMes = $_POST["busca_dataMes"] ?? date("Y-m");

    $temSubsetorVinculado = false;
    if (!empty($_POST["busca_setor"])) {
        $rowCount = mysqli_fetch_array(query("SELECT COUNT(*) FROM sbgrupos_documentos WHERE sbgr_tx_status = 'ativo' AND sbgr_nb_idgrup = ".intval($_POST["busca_setor"]).";"));
        $temSubsetorVinculado = ($rowCount[0] > 0);
    }

        $campos = [
        combo_net("Empresa", "empresa", $_POST["empresa"] ?? "", 3, "empresa", ""),
        campo_mes("Mês*", "busca_dataMes", $buscaDataMes, 2),
        campo("Nome", "busca_nome", ($_POST["busca_nome"] ?? ""), 3),
        combo("Ocupação", "busca_ocupacao", ($_POST["busca_ocupacao"] ?? ""), 2, 
            ["" => "Todos", "Motorista" => "Motorista", "Ajudante" => "Ajudante", "Funcionário" => "Funcionário"]),
        combo_bd2(
            "Parâmetros da Jornada",
            "busca_parametro",
            ($_POST["busca_parametro"] ?? ""),
            "SELECT DISTINCT 
                parametro.para_nb_id AS value,
                parametro.para_tx_nome AS text,
                '' AS props
            FROM parametro
            JOIN entidade ON entidade.enti_nb_parametro = parametro.para_nb_id
            WHERE parametro.para_tx_status = 'ativo'
              AND entidade.enti_tx_status = 'ativo'
              ".(!empty($_POST["empresa"])? " AND entidade.enti_nb_empresa = ".intval($_POST["empresa"]): "")."
            ORDER BY parametro.para_tx_nome ASC",
            "col-sm-2 margin-bottom-5 campo-fit-content",
            "form-control input-sm campo-fit-content",
            "",
            [
                ["value" => "", "text" => "", "props" => ""]
            ]
        ),
        combo_bd("!Cargo", "operacao", ($_POST["operacao"]?? ""), 2, "operacao", "", "ORDER BY oper_tx_nome ASC"),
        combo_bd("!Setor", "busca_setor", ($_POST["busca_setor"]?? ""), 2, "grupos_documentos", "onchange=\"(function(f){ if(f.busca_subsetor){ f.busca_subsetor.value=''; } f.reloadOnly.value='1'; f.submit(); })(document.contex_form);\"")
    ];
    if ($temSubsetorVinculado) {
        $campos[] = combo_bd("!Subsetor", "busca_subsetor", ($_POST["busca_subsetor"]?? ""), 2, "sbgrupos_documentos", "", " AND sbgr_nb_idgrup = ".intval($_POST["busca_setor"])." ORDER BY sbgr_tx_nome ASC");
    }

    $exibicao_valores = [];
    if (!empty($_POST["exibicao_todo_mes"])) $exibicao_valores[] = "todo_mes";
    if (!empty($_POST["exibicao_hoje"])) $exibicao_valores[] = "hoje";
    // Se nenhum for enviado (primeiro load), marca 'todo_mes' como padrão? Ou deixa vazio?
    // O usuário pediu "deve ter a escolha". Vou deixar vazio se não houver post, ou padrão todo mês.
    // Mas para manter compatibilidade com o comportamento padrão (todo mês), se nenhum estiver marcado, assume todo mês logicamente.
    // Visualmente, vou marcar 'todo_mes' se for GET ou se estiver explicitamente marcado.
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        $exibicao_valores[] = "todo_mes";
    }

    $campos[] = checkbox(
        "Exibição", 
        "exibicao", 
        ["todo_mes" => "Todo o Mês", "hoje" => "A partir de hoje"], 
        3, 
        "checkbox", 
        "onchange=\"handleCheckboxExibicao(event)\"", 
        json_encode($exibicao_valores)
    );

    $campos[] = combo(
        "Visualização",
        "visualizacao",
        $_POST["visualizacao"] ?? "analitica",
        2,
        ["analitica" => "Analítica", "sintetica" => "Sintética"]
    );

    $buttons = [
        botao("Buscar", "", "", "", "", "", "btn btn-info")
    ];

    echo abre_form();
    echo campo_hidden("reloadOnly", "");
    echo linha_form($campos);
    echo fecha_form($buttons);
    ?>
    <script>
    function handleCheckboxExibicao(event) {
        if (event.target.type !== 'checkbox') return;
        
        const checkboxId = event.target.id;
        const isChecked = event.target.checked;
        
        if (isChecked) {
            // Se marcou 'todo_mes', desmarca 'hoje'
            if (checkboxId === 'todo_mes') {
                const hojeCheckbox = document.getElementById('hoje');
                if (hojeCheckbox) {
                    hojeCheckbox.checked = false;
                    // Atualiza o Uniform se estiver sendo usado
                    if ($.uniform) $.uniform.update(hojeCheckbox);
                }
            }
            // Se marcou 'hoje', desmarca 'todo_mes'
            else if (checkboxId === 'hoje') {
                const todoMesCheckbox = document.getElementById('todo_mes');
                if (todoMesCheckbox) {
                    todoMesCheckbox.checked = false;
                    // Atualiza o Uniform se estiver sendo usado
                    if ($.uniform) $.uniform.update(todoMesCheckbox);
                }
            }
        }
    }
    </script>
    <?php

    if ($_SERVER["REQUEST_METHOD"] === "POST" && empty($_POST["reloadOnly"])) {
        $filtrosUsados = [];

        if (!empty($_POST["empresa"])) {
            $empresaNome = mysqli_fetch_assoc(query(
                "SELECT empr_tx_nome FROM empresa WHERE empr_nb_id = ".intval($_POST["empresa"])." LIMIT 1;"
            ));
            $filtrosUsados[] = "Empresa: ".htmlspecialchars($empresaNome["empr_tx_nome"] ?? $_POST["empresa"]);
        } else {
            $filtrosUsados[] = "Empresa: Todas";
        }

        if (!empty($_POST["busca_dataMes"])) {
            $mesBusca = DateTime::createFromFormat("Y-m", $_POST["busca_dataMes"]);
            if ($mesBusca) {
                $filtrosUsados[] = "Mês: ".$mesBusca->format("m/Y");
            }
        }

        if (!empty($_POST["busca_nome"])) {
            $filtrosUsados[] = "Nome contém: ".htmlspecialchars($_POST["busca_nome"]);
        }

        if (!empty($_POST["busca_ocupacao"])) {
            $filtrosUsados[] = "Ocupação: ".htmlspecialchars($_POST["busca_ocupacao"]);
        }

        if (!empty($_POST["busca_parametro"])) {
            $parametroNome = mysqli_fetch_assoc(query(
                "SELECT para_tx_nome FROM parametro WHERE para_nb_id = ".intval($_POST["busca_parametro"])." LIMIT 1;"
            ));
            if (!empty($parametroNome)) {
                $filtrosUsados[] = "Parâmetro da Jornada: ".htmlspecialchars($parametroNome["para_tx_nome"]);
            }
        }

        if (!empty($_POST["operacao"])) {
            $operacaoNome = mysqli_fetch_assoc(query(
                "SELECT oper_tx_nome FROM operacao WHERE oper_nb_id = ".intval($_POST["operacao"])." LIMIT 1;"
            ));
            if (!empty($operacaoNome)) {
                $filtrosUsados[] = "Cargo: ".htmlspecialchars($operacaoNome["oper_tx_nome"]);
            }
        }

        if (!empty($_POST["busca_setor"])) {
            $setorNome = mysqli_fetch_assoc(query(
                "SELECT grup_tx_nome FROM grupos_documentos WHERE grup_nb_id = ".intval($_POST["busca_setor"])." LIMIT 1;"
            ));
            if (!empty($setorNome)) {
                $filtrosUsados[] = "Setor: ".htmlspecialchars($setorNome["grup_tx_nome"]);
            }
        }

        if (!empty($_POST["busca_subsetor"])) {
            $subSetorNome = mysqli_fetch_assoc(query(
                "SELECT sbgr_tx_nome FROM sbgrupos_documentos WHERE sbgr_nb_id = ".intval($_POST["busca_subsetor"])." LIMIT 1;"
            ));
            if (!empty($subSetorNome)) {
                $filtrosUsados[] = "Subsetor: ".htmlspecialchars($subSetorNome["sbgr_tx_nome"]);
            }
        }

        if (!empty($filtrosUsados)) {
            $dataGeracao = date("d/m/Y H:i:s");
            echo "<div class='row'>"
                ."<div class='col-sm-12 margin-bottom-5 campo-fit-content'>"
                ."<div id='filtros-aplicados' style='padding:5px 10px; margin-bottom:10px; text-align:center; color:#000;'>"
                ."Filtros aplicados: ".implode(" | ", $filtrosUsados)
                ."<br><span style='font-size:11px;'>Gerado em: {$dataGeracao}</span>"
                ."</div>"
                ."</div>"
                ."</div>";
        }
    }

        if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["busca_dataMes"]) && empty($_POST["reloadOnly"])) {
        try {
            $periodoInicio = new DateTime($_POST["busca_dataMes"] . "-01");
            $periodoFim = new DateTime($periodoInicio->format("Y-m-t"));
            
            $hoje = new DateTime();
            $hoje->setTime(0, 0, 0);
            
            // Prioriza "A partir de hoje" APENAS se "Todo o Mês" NÃO estiver marcado
            // Ou seja: Se marcou "Hoje" e NÃO marcou "Todo mês", aplica o filtro de hoje.
            // Se marcou ambos, ou apenas "Todo mês", ou nenhum, exibe o mês completo.
            
            $exibirHoje = !empty($_POST["exibicao_hoje"]);
            $exibirTodoMes = !empty($_POST["exibicao_todo_mes"]);

            if (
                $exibirHoje && 
                !$exibirTodoMes &&
                $periodoInicio->format("Y-m") === $hoje->format("Y-m")
            ) {
                $periodoInicio = clone $hoje;
            }
        } catch (Exception $e) {
            set_status("Mês inválido.");
            rodape();
            return;
        }

        $filtroEmpresa = "";
        if (!empty($_POST["empresa"])) {
            $filtroEmpresa = " AND entidade.enti_nb_empresa = " . intval($_POST["empresa"]);
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
                    empresa.empr_tx_nome,
                    operacao.oper_tx_nome,
                    grupos_documentos.grup_tx_nome,
                    sbgrupos_documentos.sbgr_tx_nome,
                    parametro.para_tx_nome,
                    parametro.para_tx_turno
             FROM entidade
             LEFT JOIN empresa ON entidade.enti_nb_empresa = empresa.empr_nb_id
             LEFT JOIN operacao ON oper_nb_id = entidade.enti_tx_tipoOperacao
             LEFT JOIN grupos_documentos ON grup_nb_id = entidade.enti_setor_id
             LEFT JOIN sbgrupos_documentos ON sbgr_nb_id = entidade.enti_subSetor_id
             LEFT JOIN parametro ON parametro.para_nb_id = entidade.enti_nb_parametro
             JOIN user ON user.user_nb_entidade = entidade.enti_nb_id
             WHERE entidade.enti_tx_status = 'ativo'
               AND user.user_tx_status = 'ativo'
               AND (
                    user.user_tx_nivel IN ('Funcionário', 'Motorista', 'Ajudante')
                    OR user.user_tx_nivel LIKE '%administrador%'
                    OR user.user_tx_nivel LIKE '%super admin%'
                    OR EXISTS (
                        SELECT 1 FROM usuario_perfil up
                        JOIN perfil_menu_item pmi ON pmi.perfil_nb_id = up.perfil_nb_id
                        JOIN menu_item mi ON mi.menu_nb_id = pmi.menu_nb_id
                        WHERE up.user_nb_id = user.user_nb_id
                          AND up.ativo = 1
                          AND pmi.perm_ver = 1
                          AND mi.menu_tx_ativo = 1
                          AND mi.menu_tx_path = '/batida_ponto.php'
                    )
               )
               " . $filtroEmpresa . "
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

        $exibirEmpresa = empty($_POST["empresa"]);
        $exibirOcupacao = empty($_POST["busca_ocupacao"]);
        $exibirCargo = empty($_POST["operacao"]);
        $exibirSetor = empty($_POST["busca_setor"]);
        $exibirSubSetor = empty($_POST["busca_subsetor"]);

        // Busca feriados ativos no período
        $feriados = mysqli_fetch_all(query(
            "SELECT feri_tx_data, feri_tx_nome FROM feriado 
             WHERE feri_tx_status = 'ativo' 
               AND feri_tx_data BETWEEN '{$periodoInicio->format('Y-m-d')}' AND '{$periodoFim->format('Y-m-d')}'"
        ), MYSQLI_ASSOC);
        
        $feriadosMap = [];
        foreach ($feriados as $f) {
            $feriadosMap[$f['feri_tx_data']] = $f['feri_tx_nome'];
        }

        $cabecalho = ["Matrícula", "Nome"];
        $numColunasFixas = 2;

        $visualizacao = $_POST["visualizacao"] ?? "analitica";

        if ($exibirEmpresa) {
            $cabecalho[] = "Empresa";
            $numColunasFixas++;
        }
        if ($exibirOcupacao) {
            $cabecalho[] = "Ocupação";
            $numColunasFixas++;
        }
        if ($exibirCargo) {
            $cabecalho[] = "Cargo";
            $numColunasFixas++;
        }
        if ($exibirSetor) {
            $cabecalho[] = "Setor";
            $numColunasFixas++;
        }
        if ($exibirSubSetor) {
            $cabecalho[] = "SubSetor";
            $numColunasFixas++;
        }

        if ($visualizacao == "sintetica") {
            $cabecalho[] = "Escala";
            $numColunasFixas++;
        }
        
        $diasMes = (int)$periodoFim->format("d");
        $diasSemana = ["DOM", "SEG", "TER", "QUA", "QUI", "SEX", "SAB"];
        
        $diasDestacar = [];

        for ($data = clone $periodoInicio; $data <= $periodoFim; $data->modify("+1 day")) {
            $sigla = $diasSemana[(int)$data->format("w")];
            $dataStr = $data->format("Y-m-d");
            
            $tituloColuna = $data->format("d") . " " . $sigla;
            $ehDomingo = ($data->format('w') == 0);
            $ehFeriado = isset($feriadosMap[$dataStr]);

            // Verifica se é domingo ou feriado para destaque posterior via JS
            if ($ehDomingo || $ehFeriado) {
                $diasDestacar[] = (int)$data->format("d");
                
                $legenda = [];
                if ($ehDomingo) {
                    $legenda[] = "Domingo";
                }
                if ($ehFeriado) {
                    $legenda[] = $feriadosMap[$dataStr];
                }
                $textoLegenda = implode(" - ", $legenda);
                
                $tituloColuna .= " <i class='fa fa-question-circle' title='$textoLegenda' style='cursor:help; font-size: 11px; color: #333;'></i>";
            }
            
            $cabecalho[] = $tituloColuna;
        }
        $cabecalho[] = "Total Previsto";

        $valores = [];

        foreach ($motoristas as $motorista) {
            $row = [
                "matricula" => $motorista["enti_tx_matricula"],
                "nome" => $motorista["enti_tx_nome"]
            ];

            $horarioEscala = "";

            if ($exibirEmpresa) {
                $row["empresa_nome"] = $motorista["empr_tx_nome"];
            }

            if ($exibirOcupacao) {
                $row["ocupacao"] = $motorista["enti_tx_ocupacao"];
            }
            if ($exibirCargo) {
                $row["cargo"] = $motorista["oper_tx_nome"] ?? "";
            }
            if ($exibirSetor) {
                $row["setor"] = $motorista["grup_tx_nome"] ?? "";
            }
            if ($exibirSubSetor) {
                $row["subsetor"] = $motorista["sbgr_tx_nome"] ?? "";
            }

            // Se for sintética, adiciona o placeholder da escala
            if ($visualizacao == "sintetica") {
                $row["escala"] = "";
            }

            $totalPrevistoMinutos = 0;

            for ($data = clone $periodoInicio; $data <= $periodoFim; $data->modify("+1 day")) {
                $dataStr = $data->format("Y-m-d");
                $diaPonto = diaDetalhePonto($motorista, $dataStr);
                $inicioEscala = $diaPonto["inicioEscala"] ?? "";
                $fimEscala = $diaPonto["fimEscala"] ?? "";

                $inicio = (!empty($inicioEscala) && $inicioEscala !== "00:00" && $inicioEscala !== "00:00:00") ? substr($inicioEscala, 0, 5) : "--:--";
                $fim = (!empty($fimEscala) && $fimEscala !== "00:00" && $fimEscala !== "00:00:00") ? substr($fimEscala, 0, 5) : "--:--";
                
                $valor = ($inicio === "--:--" && $fim === "--:--") ? "----" : $inicio . " - " . $fim;

                if ($visualizacao == "sintetica") {
                    // Se for sintética, exibe a sigla do turno ou "----" se não tiver horário
                    if ($valor !== "----") {
                        $valor = !empty($motorista["para_tx_turno"]) ? $motorista["para_tx_turno"] : "----";
                        
                        // Captura o primeiro horário válido para exibir na coluna Escala
                        if (empty($horarioEscala)) {
                            $horarioEscala = $inicio . " " . $fim;
                        }
                    } else {
                        $valor = "----";
                    }
                }

                // Lógica de destaque movida para JS/CSS para preencher a célula inteira

                if ($inicio !== "--:--" && $fim !== "--:--") {
                    $hInicio = DateTime::createFromFormat('H:i', $inicio);
                    $hFim = DateTime::createFromFormat('H:i', $fim);
                    
                    if ($hInicio && $hFim) {
                        if ($hFim < $hInicio) {
                            $hFim->modify('+1 day');
                        }
                        $diff = $hInicio->diff($hFim);
                        $minutos = ($diff->h * 60) + $diff->i;
                        $totalPrevistoMinutos += $minutos;
                    }
                }

                $key = "dia_" . $data->format("d");
                $row[$key] = $valor;
            }

            if ($visualizacao == "sintetica") {
                // Se não encontrou horário em nenhum dia, define como vazio
                if (empty($horarioEscala)) {
                    $horarioEscala = "--:-- --:--";
                }
                $row["escala"] = $horarioEscala;
            }

            $horasTotal = floor($totalPrevistoMinutos / 60);
            $minutosTotal = $totalPrevistoMinutos % 60;
            $row["totalPrevisto"] = sprintf("%02d:%02d", $horasTotal, $minutosTotal);

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
.tabela-espelho-ponto td.holiday-sunday{
    background-color:#ffe0b2 !important;
    color: black !important;
}
</style>";
        echo "<div style='margin-bottom:8px; text-align:left;'>";
        echo "<button type='button' class='btn btn-success btn-sm' onclick='exportarEscalaCSV()'>Exportar CSV</button> ";
        echo "<button type='button' class='btn btn-primary btn-sm' onclick='exportarEscalaExcel()'>Exportar Excel</button>";
        
        $qtdeFuncionarios = count($valores);
        echo "<span style='margin-left: 20px; font-weight: bold; font-size: 14px;'>Total de Funcionários: $qtdeFuncionarios</span>";

        echo "</div>";
        echo "<div id='escala-scroll-top' style='overflow-x:auto; overflow-y:hidden; margin-bottom:5px;'><div style='height:1px;'></div></div>";
        echo "<div id='escala-grid-wrapper'>";
        $gridHtml = montarTabelaPonto($cabecalho, $valores);
        $gridHtml = str_replace("(*): Registros excluídos manualmente.<br>", "", $gridHtml);
        $gridHtml = str_replace("(**): 00:00 Caso esteja dentro da tolerância", "", $gridHtml);
        echo $gridHtml;
        echo "</div>";
        echo "</div></div>";
        ?>
        <script>
        window.exportarEscalaCSV = function() {
            try {
                var wrapper = document.getElementById('escala-grid-wrapper');
                if (!wrapper) { alert('Erro: Container da tabela não encontrado.'); return; }

                var tabela = wrapper.querySelector('table');
                if (!tabela) { alert('Erro: Tabela não encontrada.'); return; }

                var linhas = tabela.querySelectorAll('tr');
                if (linhas.length === 0) { alert('Erro: Tabela vazia.'); return; }

                var csv = [];

                var filtrosDiv = document.getElementById('filtros-aplicados');
                if (filtrosDiv) {
                    var filtrosTexto = filtrosDiv.innerText || filtrosDiv.textContent;
                    var linhasFiltro = filtrosTexto.split('\n');
                    linhasFiltro.forEach(function(linha) {
                         if (linha.trim()) {
                             csv.push('"' + linha.trim().replace(/"/g, '""') + '"');
                         }
                    });
                    csv.push('');
                }

                for (var i = 0; i < linhas.length; i++) {
                    var cols = linhas[i].querySelectorAll('th,td');
                    var row = [];
                    if (cols.length === 0) continue;

                    for (var j = 0; j < cols.length; j++) {
                        var texto = cols[j].innerText || cols[j].textContent || '';
                        texto = texto.replace(/\s+/g, ' ').trim();
                        texto = texto.replace(/"/g, '""');
                        row.push('"' + texto + '"');
                    }
                    csv.push(row.join(';'));
                }

                if (csv.length === 0) { alert('Erro: Nenhum dado para exportar.'); return; }

                var csvContent = '\uFEFF' + csv.join('\r\n');
                var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                var link = document.createElement('a');
                var url = URL.createObjectURL(blob);

                link.setAttribute('href', url);
                link.setAttribute('download', 'escala_parametro.csv');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } catch (e) {
                console.error(e);
                alert('Erro ao exportar CSV: ' + e.message);
            }
        };

        window.exportarEscalaExcel = function() {
            try {
                var wrapper = document.getElementById('escala-grid-wrapper');
                if (!wrapper) { alert('Erro: Container da tabela não encontrado.'); return; }

                var tabela = wrapper.querySelector('table');
                if (!tabela) { alert('Erro: Tabela não encontrada.'); return; }

                var linhas = tabela.querySelectorAll('tr');
                if (linhas.length === 0) { alert('Erro: Tabela vazia.'); return; }

                var csv = [];

                var filtrosDiv = document.getElementById('filtros-aplicados');
                if (filtrosDiv) {
                    var filtrosTexto = filtrosDiv.innerText || filtrosDiv.textContent;
                    var linhasFiltro = filtrosTexto.split('\n');
                    linhasFiltro.forEach(function(linha) {
                         if (linha.trim()) {
                             csv.push('"' + linha.trim().replace(/"/g, '""') + '"');
                         }
                    });
                    csv.push('');
                }

                for (var i = 0; i < linhas.length; i++) {
                    var cols = linhas[i].querySelectorAll('th,td');
                    var row = [];
                    if (cols.length === 0) continue;

                    for (var j = 0; j < cols.length; j++) {
                        var texto = cols[j].innerText || cols[j].textContent || '';
                        texto = texto.replace(/\s+/g, ' ').trim();
                        texto = texto.replace(/"/g, '""');
                        row.push('"' + texto + '"');
                    }
                    csv.push(row.join(';'));
                }

                if (csv.length === 0) { alert('Erro: Nenhum dado para exportar.'); return; }

                var csvContent = '\uFEFF' + csv.join('\r\n');
                var blob = new Blob([csvContent], { type: 'application/vnd.ms-excel;charset=utf-8;' });
                var link = document.createElement('a');
                var url = URL.createObjectURL(blob);

                link.setAttribute('href', url);
                link.setAttribute('download', 'escala_parametro.xls');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } catch (e) {
                console.error(e);
                alert('Erro ao exportar Excel: ' + e.message);
            }
        };

        (function() {
            var wrapper = document.getElementById('escala-grid-wrapper');
            if (!wrapper) return;
            var tableResp = wrapper.querySelector('.table-responsive');
            if (!tableResp) return;
            var tabela = tableResp.querySelector('table');
            var topScroll = document.getElementById('escala-scroll-top');
            if (!tabela || !topScroll) return;
            var inner = topScroll.firstElementChild;
            inner.style.width = tabela.scrollWidth + 'px';
            topScroll.onscroll = function() { tableResp.scrollLeft = topScroll.scrollLeft; };
            tableResp.onscroll = function() { topScroll.scrollLeft = tableResp.scrollLeft; };
            window.addEventListener('resize', function() {
                inner.style.width = tabela.scrollWidth + 'px';
            });

            var hoje = new Date();
            var mesSelecionado = '<?=$buscaDataMes?>';
            var mesHoje = hoje.getFullYear() + '-' + ('0' + (hoje.getMonth() + 1)).slice(-2);
            if (mesSelecionado === mesHoje) {
                var diaAtual = hoje.getDate();
                var colunasFixas = <?= $numColunasFixas ?>;
                var indiceColuna = colunasFixas + diaAtual - 1;
                var thsHoje = tabela.querySelectorAll('thead tr th');
                if (thsHoje.length > indiceColuna) {
                    thsHoje[indiceColuna].classList.add('current-day');
                    var linhasHoje = tabela.querySelectorAll('tbody tr');
                    for (var i = 0; i < linhasHoje.length; i++) {
                        var cellsHoje = linhasHoje[i].querySelectorAll('td');
                        if (cellsHoje.length > indiceColuna) {
                            cellsHoje[indiceColuna].classList.add('current-day');
                        }
                    }
                }
            }

            // Destaque para Domingos e Feriados
            var diasDestacar = <?= json_encode($diasDestacar) ?>;
            var numColunasFixas = <?= $numColunasFixas ?>;
            
            if (diasDestacar && diasDestacar.length > 0) {
                var ths = tabela.querySelectorAll('thead tr th');
                var linhas = tabela.querySelectorAll('tbody tr');
                
                diasDestacar.forEach(function(dia) {
                    var indiceColuna = numColunasFixas + dia - 1;
                    
                    // Removido destaque do cabeçalho conforme solicitado
                    /*
                    if (ths.length > indiceColuna) {
                        ths[indiceColuna].classList.add('holiday-sunday');
                    }
                    */
                    
                    for (var i = 0; i < linhas.length; i++) {
                        var cells = linhas[i].querySelectorAll('td');
                        if (cells.length > indiceColuna) {
                            cells[indiceColuna].classList.add('holiday-sunday');
                        }
                    }
                });
            }

            var linhasBody = tabela.querySelectorAll('tbody tr');
            for (var j = 0; j < linhasBody.length; j++) {
                linhasBody[j].addEventListener('click', function() {
                    for (var k = 0; k < linhasBody.length; k++) {
                        linhasBody[k].classList.remove('selected-row');
                    }
                    this.classList.add('selected-row');
                });
            }

            var ths = tabela.querySelectorAll('thead tr th');
            var tbody = tabela.querySelector('tbody');
            if (ths.length && tbody) {
                function getCellValue(row, index) {
                    var cell = row.cells[index];
                    return cell ? cell.textContent.trim() : '';
                }

                function parseHora(valor) {
                    var match = valor.match(/^(\d{2}):(\d{2})/);
                    if (!match) { return null; }
                    return parseInt(match[1], 10) * 60 + parseInt(match[2], 10);
                }

                function comparar(index, asc) {
                    return function(a, b) {
                        var v1 = getCellValue(asc ? a : b, index);
                        var v2 = getCellValue(asc ? b : a, index);

                        var t1 = parseHora(v1);
                        var t2 = parseHora(v2);
                        if (t1 !== null && t2 !== null) {
                            return t1 - t2;
                        }

                        return v1.localeCompare(v2, 'pt-BR', { numeric: true, sensitivity: 'base' });
                    };
                }

                for (var h = 0; h < ths.length; h++) {
                    (function(idx) {
                        ths[idx].style.cursor = 'pointer';
                        ths[idx].addEventListener('click', function() {
                            var atual = this.getAttribute('data-order') || 'desc';
                            var novo = (atual === 'asc') ? 'desc' : 'asc';
                            for (var x = 0; x < ths.length; x++) {
                                ths[x].removeAttribute('data-order');
                            }
                            this.setAttribute('data-order', novo);

                            var linhas = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
                            linhas.sort(comparar(idx, novo === 'asc'));
                            for (var y = 0; y < linhas.length; y++) {
                                tbody.appendChild(linhas[y]);
                            }
                        });
                    })(h);
                }
            }
        })();
        </script>
<?php
    }

    rodape();
}
