<?php
    /* Modo debug
        ini_set("display_errors", 1);
        error_reporting(E_ALL);
    //*/

    header("Expires: 01 Jan 2001 00:00:00 GMT");
    header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
    header('Cache-Control: post-check=0, pre-check=0', FALSE);
    header('Pragma: no-cache');

    require "../funcoes_ponto.php";
    require_once __DIR__ . "/funcoes_paineis.php";
    // criar_relatorio_jornada();

    function enviarForm() {
        $_POST["acao"] = $_POST["campoAcao"];
        index();
    }

    function carregarJS(array $arquivos) {

        $linha = "linha = '<tr>'";
        if (!empty($_POST["empresa"])) {
            $linha .= "+'<td style=\'text-align: center;\'>'+item.data+'</td>'
                        +'<td style=\'text-align: center;\'>'+item.matricula+'</td>'
                        +'<td style=\'text-align: center;\'>'+item.nome+'</td>'
                        +'<td style=\'text-align: center;\'>'+item.ocupacao+'</td>'
                        +'<td style=\'text-align: center;'+ cor +'\'>'+jornada+'</td>'
                        +'<td style=\'text-align: center;'+ cor +'\'>'+item.jornadaEfetiva+'</td>'
                        +'<td style=\'text-align: center;'+ cor +'\'>'+(refeicao ? refeicao : '<strong>----</strong>')+'</td>'
                        +'<td style=\'text-align: center;'+ cor +'\'>'+(espera ? espera : '<strong>----</strong>')+'</td>'
                        +'<td style=\'text-align: center;'+ cor +'\'>'+(descanso ? descanso : '<strong>----</strong>')+'</td>'
                        +'<td style=\'text-align: center;'+ cor +'\'>'+(repouso ? repouso : '<strong>----</strong>')+'</td>'
                    +'</tr>';";
        } 

        $carregarDados = "";
        foreach ($arquivos as $arquivo) {
            $carregarDados .= "carregarDados('" . $arquivo . "');";
        }

        echo
        "<form name='myForm' method='post' action='" . htmlspecialchars($_SERVER["PHP_SELF"]) . "'>
                    <input type='hidden' name='acao'>
                    <input type='hidden' name='campoAcao'>
                    <input type='hidden' name='empresa'>
                    <input type='hidden' name='busca_dataMes'>
                    <input type='hidden' name='busca_dataInicio'>
                    <input type='hidden' name='busca_dataFim'>
                    <input type='hidden' name='busca_data'>
                </form>
                <script>
                    function atualizarPainel(){
                        document.myForm.empresa.value = document.getElementById('empresa').value;
                        document.myForm.busca_data.value = document.getElementById('busca_data').value;
                        document.myForm.atualizar.value = 'atualizar';
                        document.myForm.submit();
                    }

                    function imprimir(){
                        window.print();
                    }
                
                    $(document).ready(function(){
                        var tabela = $('#tabela-empresas tbody');

                        function carregarDados(urlArquivo){
                            $.ajax({
                                url: urlArquivo,
                                dataType: 'json',
                                success: function(data){
                                    var row = {};
                                    $.each(data, function(index, item){
                                        function parseData(dataString) {
                                            var partes = dataString.split('/'); // Divide a string 'dd/mm/yyyy'
                                            var dia = parseInt(partes[0], 10);  // Pega o dia
                                            var mes = parseInt(partes[1], 10) - 1; // Pega o mês (0-11)
                                            var ano = parseInt(partes[2], 10);  // Pega o ano
                                            return new Date(ano, mes, dia);  // Retorna a data como um objeto Date
                                        }

                                        function processaCampo(campo, diferencaDias, isDataAtual) {
                                            // Só aplicar a lógica se a data não for a atual
                                            if (!isDataAtual && campo === '*') {
                                                return '* D+' + diferencaDias;
                                            }
                                            return campo;
                                        }


                                        // Converte a string de data do item para um objeto Date
                                        var dataItem = parseData(item.data);
                                        var dataAtual = new Date(); // Pega a data atual

                                        dataItem.setHours(0, 0, 0, 0);
                                        dataAtual.setHours(0, 0, 0, 0);

                                        var isDataAtual = dataItem.getTime() === dataAtual.getTime();
                                        var diferencaDias = isDataAtual ? 0 : Math.floor((dataAtual - dataItem) / (1000 * 60 * 60 * 24));

                                        if(item.data.indexOf('(E)') == -1) {
                                            var cor = '';
                                            // Definir a cor com base na quantidade de dias
                                            if (diferencaDias <= 3) {
                                                cor = 'background-color: green; color:white';
                                            } else if (diferencaDias <= 6) {
                                                cor = 'background-color: yellow;'
                                            } else {
                                                cor = 'background-color: red; color:white';
                                            }
                                        }

                                        var jornada = processaCampo(item.jornada, diferencaDias, isDataAtual);
                                        var refeicao = processaCampo(item.refeicao, diferencaDias, isDataAtual);
                                        var espera = processaCampo(item.espera, diferencaDias, isDataAtual);
                                        var descanso = processaCampo(item.descanso, diferencaDias, isDataAtual);
                                        var repouso = processaCampo(item.repouso, diferencaDias, isDataAtual);
                                    "
                                        . $linha ."
                                        tabela.append(linha);
                                    });
                                },
                                error: function(){
                                    console.error('Erro ao carregar os dados.');
                                }
                            });
                        }
                        // Função para ordenar a tabela
                        function ordenarTabela(coluna, ordem){
                            var linhas = tabela.find('tr').get();
                            linhas.sort(function(a, b){
                                var valorA = $(a).children('td').eq(coluna).text().toUpperCase();
                                var valorB = $(b).children('td').eq(coluna).text().toUpperCase();

                                if(valorA < valorB){
                                    return ordem === 'asc' ? -1 : 1;
                                }
                                if(valorA > valorB){
                                    return ordem === 'asc' ? 1 : -1;
                                }
                                return 0;
                            });
                            $.each(linhas, function(index, row){
                                tabela.append(row);
                            });
                        }
                        var colunasPermitidas = ['data', 'nome', 'matricula', 'ocupacao']; 
                        // Evento de clique para ordenar a tabela ao clicar no cabeçalho
                        $('#titulos th').click(function(){
                            var colunaClicada = $(this).attr('class');
                            // console.log(colunaClicada)
                            
                            var classePermitida = colunasPermitidas.some(function(coluna) {
                                return colunaClicada.includes(coluna);
                            });

                            if (classePermitida) {
                                var coluna = $(this).index();
                                var ordem = $(this).data('order');

                                // Redefinir ordem de todas as colunas
                                $('#tabela-empresas th').data('order', 'desc'); 
                                $(this).data('order', ordem === 'desc' ? 'asc' : 'desc');
                                
                                // Chama a função de ordenação
                                ordenarTabela(coluna, $(this).data('order'));

                                // Ajustar classes para setas de ordenação
                                $('#titulos th').removeClass('sort-asc sort-desc');
                                $(this).addClass($(this).data('order') === 'asc' ? 'sort-asc' : 'sort-desc');
                            }
                        });

                        " . $carregarDados . "
                    });
                    //Variação dos campos de pesquisa{
                        var camposAcao = document.getElementsByName('campoAcao');
                        if (camposAcao[0].checked){
                            document.getElementById('botaoContexBuscar').innerHTML = 'Buscar';
                            document.getElementById('busca_dataMes').parentElement.style.display = 'block';
                            document.getElementById('busca_dataInicio').parentElement.style.display = 'none';
                            document.getElementById('busca_dataFim').parentElement.style.display = 'none';
                        }
                        if (camposAcao[1].checked){
                            document.getElementById('botaoContexBuscar').innerHTML = 'Atualizar';
                            document.getElementById('busca_dataMes').parentElement.style.display = 'none';
                            document.getElementById('busca_dataInicio').parentElement.style.display = 'block';
                            document.getElementById('busca_dataFim').parentElement.style.display = 'block';
                        }
                        camposAcao[0].addEventListener('change', function() {
                            if (camposAcao[0].checked){
                                document.getElementById('botaoContexBuscar').innerHTML = 'Buscar';
                                document.getElementById('busca_dataMes').parentElement.style.display = 'block';
                                document.getElementById('busca_dataInicio').parentElement.style.display = 'none';
                                document.getElementById('busca_dataFim').parentElement.style.display = 'none';
                            }
                        });
                        camposAcao[1].addEventListener('change', function() {
                            if (camposAcao[1].checked){
                                document.getElementById('botaoContexBuscar').innerHTML = 'Atualizar';
                                document.getElementById('busca_dataMes').parentElement.style.display = 'none';
                                document.getElementById('busca_dataInicio').parentElement.style.display = 'block';
                                document.getElementById('busca_dataFim').parentElement.style.display = 'block';
                            }
                        });
                    //}
                </script>";
    }

    function index() {

        if (empty($_POST["busca_dataMes"])) {
            $_POST["busca_dataMes"] = date("Y-m");
        }
        if (empty($_POST["busca_dataInicio"])) {
            $_POST["busca_dataInicio"] = date("Y-m-01");
        }
        if (empty($_POST["busca_dataFim"])) {
            $_POST["busca_dataFim"] = date("Y-m-d");
        }

        if (!empty($_POST["acao"]) && $_POST["acao"] == "buscar") {
            // if(empty($_POST["busca_periodo"])){
            //     $_POST["busca_periodo"] = date("01/m/Y")." - ".date("d/m/Y");
            // }
            // $datas = explode(" - ", $_POST["busca_periodo"]);
            // $_POST["busca_dataInicio"] = DateTime::createFromFormat("d/m/Y", $datas[0])->format("Y-m-d");
            // $_POST["busca_dataFim"] = DateTime::createFromFormat("d/m/Y", $datas[1])->format("Y-m-d");

            if ($_POST["busca_dataMes"] > date("Y-m")) {
                unset($_POST["acao"]);
                $_POST["errorFields"][] = "busca_dataMes";
                set_status("ERRO: Não é possível pesquisar após a data atual.");
            }
            cabecalho("Relatorio de Jornada Aberta");
        } elseif (!empty($_POST["acao"]) && $_POST["acao"] == "atualizarPainel") {
            echo "<script>alert('Atualizando os painéis, aguarde um pouco.')</script>";
            ob_flush();
            flush();

            cabecalho("Relatorio de Jornada Aberta");

            $err = ($_POST["busca_dataInicio"] > date("Y-m-d")) * 1 + ($_POST["busca_dataFim"] > date("Y-m-d")) * 2;
            if ($err > 0) {
                switch ($err) {
                    case 1:
                        $_POST["errorFields"][] = "busca_dataInicio";
                        break;
                    case 2:
                        $_POST["errorFields"][] = "busca_dataFim";
                        break;
                    case 3:
                        $_POST["errorFields"][] = "busca_dataInicio";
                        $_POST["errorFields"][] = "busca_dataFim";
                        break;
                }
                unset($_POST["acao"]);
                set_status("ERRO: Não é possível atualizar após a data atual.");
            } else {
                require_once "funcoes_paineis.php";
                criar_relatorio_jornada();
            }
        } else {
            cabecalho("Relatorio de Jornada Aberta");
        }

        // $texto = "<div style=''><b>Periodo da Busca:</b> $monthName de $year</div>";
        //position: absolute; top: 101px; left: 420px;

        $campoAcao =
            "<div class='col-sm-2 margin-bottom-5' style='min-width:200px;'>
                    <label>" . "Ação" . "</label><br>
                    <label class='radio-inline'>
                        <input type='radio' name='campoAcao' value='buscar' " . ((empty($_POST["campoAcao"]) || $_POST["campoAcao"] == "buscar") ? "checked" : "") . "> Buscar
                    </label>
                    <label class='radio-inline'>
                        <input type='radio' name='campoAcao' value='atualizarPainel'" . (!empty($_POST["campoAcao"]) && $_POST["campoAcao"] == "atualizarPainel" ? "checked" : "") . "> Atualizar
                    </label>
                </div>";

        $campos = [
            combo_net("Empresa", "empresa", $_POST["empresa"] ?? "", 4, "empresa", ""),
            // campo("Período*", "busca_periodo", ($_POST["busca_periodo"]?? ""), 3, "MASCARA_PERIODO"),
            $campoAcao,
            campo_mes("Mês*", "busca_dataMes", ($_POST["busca_dataMes"] ?? date("Y-m")), 2),
            campo_data("Data Início*", "busca_dataInicio", ($_POST["busca_dataInicio"] ?? ""), 2),
            campo_data("Data Fim*", "busca_dataFim", ($_POST["busca_dataFim"] ?? ""), 2)
            // $texto,
        ];



        $botao_volta = "";
        if (!empty($_POST["empresa"])) {
            $botao_volta = "<button class='btn default' type='button' onclick='setAndSubmit(\"\")'>Voltar</button>";
        }
        $botao_imprimir = "<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>";

        $buttons = [
            botao("Buscar", "enviarForm()", "", "", "", "", "btn btn-info"),
            $botao_imprimir,
            $botao_volta
        ];


        abre_form();
        linha_form($campos);
        fecha_form($buttons);

        $arquivos = [];
        $dataEmissao = ""; //Utilizado no HTML
        $encontrado = false;
        $path = "./arquivos/jornada";
        $periodoRelatorio = ["dataInicio" => "", "dataFim" => ""];

        if(!empty($_POST["empresa"]) && !empty($_POST["busca_dataMes"])){
            
            $empresa = mysqli_fetch_assoc(query(
                "SELECT * FROM empresa"
                ." WHERE empr_tx_status = 'ativo'"
                    ." AND empr_nb_id = ".$_POST["empresa"]
                ." LIMIT 1;"
            ));
            
            $path .= "/".$_POST["busca_dataMes"]."/".$empresa["empr_nb_id"];

            if(is_dir($path)){
                $pasta = dir($path);
                while($arquivo = $pasta->read()){
                    if(!in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresa_"))){
                        $arquivos[] = $arquivo;
                    }
                }
                $pasta->close();

                foreach($arquivos as &$arquivo){
                    $arquivo = $path."/".$arquivo;
                }

                if (!empty($arquivo)) {
                    $dataEmissao = "Atualizado em: ".date("d/m/Y H:i", filemtime($arquivo)); //Utilizado no HTML.
                    $arquivoGeral = json_decode(file_get_contents($arquivo), true);

                    $periodoRelatorio = [
                        "dataInicio" => $arquivoGeral[0]["dataInicio"],
                        "dataFim" => $arquivoGeral[0]["dataFim"]
                    ];

                    $encontrado = true;
                } else {
                    echo "<script>alert('Não tem jornadas abertas.')</script>";
                }
            }else{
                $encontrado = false;
            }
        }

        if($encontrado){
            // $rowTotais = "<tr class='totais'>";
            $rowTitulos = "<tr id='titulos' class='titulos'>";
            $rowTitulos .= 
            "<th class='data'>Data</th>"
            ."<th class='matricula'>Matrícula</th>"
            ."<th class='nome'>Nome</th>"
            ."<th class='ocupacao'>Ocupação</th>"
            ."<th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='jornada'>Jornada</th>"
            ."<th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='jornadaEfetiva'>Jornada Efetiva</th>"
            ."<th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='refeicao'>Refeicao</th>"
            ."<th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='espera'>Espera</th>"
            ."<th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='descanso'>Descanso</th>"
            ."<th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='repouso'>Repouso</th>";
            $rowTitulos .= "</tr>";
            include_once "painel_html2.php";
        }

        carregarJS($arquivos);
        
        rodape();
    }