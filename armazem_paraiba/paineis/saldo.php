<?php
    /* Modo debug
        ini_set("display_errors", 1);
        error_reporting(E_ALL);
    //*/
     
    header("Expires: 01 Jan 2001 00:00:00 GMT");
    header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
    header('Cache-Control: post-check=0, pre-check=0', FALSE);
    header('Pragma: no-cache');

    require_once __DIR__."/funcoes_paineis.php";
    require __DIR__."/../funcoes_ponto.php";

    function enviarForm(){
        $_POST["acao"] = $_POST["campoAcao"];
        index();
    }

    function carregarJS(array $arquivos){

        $linha = "linha = '<tr>'";
        if(!empty($_POST["empresa"])){
            $linha .= "+'<td>'+row.matricula+'</td>'
                    +'<td>'+row.nome+'</td>'
                    +'<td>'+(row.ocupacao?? '')+'</td>'
                    +'<td class=\"'+(row.statusEndosso === 'E' ? 'endo' : (row.statusEndosso === 'EP' ? 'endo-parc' : 'nao-endo'))+'\">'
                        +'<strong>'+row.statusEndosso+'</strong>'
                    +'</td>'
                    +'<td>'+(row.jornadaPrevista == '00:00' ? '' : row.jornadaPrevista?? '')+'</td>'
                    +'<td>'+(row.jornadaEfetiva == '00:00' ? '' : row.jornadaEfetiva?? '')+'</td>'
                    +'<td>'+(row.HESemanal == '00:00' ? '' : row.HESemanal?? '')+'</td>'
                    +'<td>'+(row.HESabado == '00:00' ? '' : row.HESabado?? '')+'</td>'
                    +'<td>'+(row.adicionalNoturno == '00:00' ? '' : row.adicionalNoturno?? '')+'</td>'
                    +'<td>'+(row.esperaIndenizada == '00:00' ? '' : row.esperaIndenizada?? '')+'</td>'
                    +'<td>'+(row.saldoAnterior?? '')+'</td>'
                    +'<td>'+(row.saldoPeriodo > '00:00' ? '<strong>' + row.saldoPeriodo + '</strong>' : (row.saldoPeriodo ?? ''))+'</td>'
                    +'<td id='+(row.saldoFinal > '00:00' ? 'saldo-final' : (row.saldoFinal === '00:00' ? 'saldo-zero' : 'saldo-negativo'))+';\">'
                    +(row.saldoFinal?? '')+'</td>'
                +'</tr>';";
        }else{
            $linha .= "+'<td style=\"cursor: pointer;\" onclick=\"setAndSubmit(' + row.empr_nb_id + ')\">'+row.empr_tx_nome+'</td>'
                    +'<td>'+Math.round(row.percEndossado*10000)/100+'%</td>'
                    +'<td>'+row.qtdMotoristas+'</td>'
                    +'<td>'+(row.totais.jornadaPrevista == '00:00' ? '' : row.totais.jornadaPrevista)+'</td>'
                    +'<td>'+(row.totais.jornadaEfetiva == '00:00' ? '' : row.totais.jornadaEfetiva)+'</td>'
                    +'<td>'+(row.totais.HESemanal == '00:00' ? '' : row.totais.HESemanal)+'</td>'
                    +'<td>'+(row.totais.HESabado == '00:00' ? '' : row.totais.HESabado)+'</td>'
                    +'<td>'+(row.totais.adicionalNoturno == '00:00' ? '' : row.totais.adicionalNoturno)+'</td>'
                    +'<td>'+(row.totais.esperaIndenizada == '00:00' ? '' : row.totais.esperaIndenizada)+'</td>'
                    +'<td>'+(row.totais.saldoAnterior == '00:00' ? '' : row.totais.saldoAnterior)+'</td>'
                    +'<td>'+(row.totais.saldoPeriodo > '00:00' ? '<strong>' + row.totais.saldoPeriodo + '</strong>' : (row.totais.saldoPeriodo ?? ''))+'</td>'
                    +'<td id='+(row.totais.saldoFinal > '00:00' ? 'saldo-final' : (row.totais.saldoFinal === '00:00' ? 'saldo-zero' : 'saldo-negativo'))+'>'
                    +(row.totais.saldoFinal ?? '')+'</td>'
                +'</tr>';";

        }

        $carregarDados = "";
        foreach($arquivos as $arquivo){
            $carregarDados .= "carregarDados('".$arquivo."');";
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
                function setAndSubmit(empresa){
                    document.myForm.acao.value = 'enviarForm()';
                    document.myForm.campoAcao.value = 'buscar';
                    document.myForm.empresa.value = empresa;
                    document.myForm.busca_dataMes.value = document.getElementById('busca_dataMes').value;
                    document.myForm.submit();
                }

                function atualizarPainel(){
                    document.myForm.empresa.value = document.getElementById('empresa').value;
                    document.myForm.busca_dataInicio.value = document.getElementById('busca_dataInicio').value;
                    document.myForm.busca_dataFim.value = document.getElementById('busca_dataFim').value;
                    document.myForm.acao.value = 'atualizar';
                    document.myForm.submit();
                }

                function imprimir(){
                    window.print();
                }

                $(document).ready(function(){
                    var tabela = $('#tabela-empresas tbody');

                    function carregarDados(urlArquivo){
                        $.ajax({
                            url: urlArquivo + '?v=' + new Date().getTime(),
                            dataType: 'json',
                            success: function(data){
                                var row = {};
                                $.each(data, function(index, item){
                                    row[index] = item;
                                });
                                console.log(row);
                                if(row.idMotorista != undefined){
                                    delete row.idMotorista;
                                }"
                                .$linha
                                ."tabela.append(linha);
                            },
                            error: function(){
                                console.log('Erro ao carregar os dados.');
                            }
                        });
                    }
                    // Função para conversão de Horas para Minutos
                    function horasParaMinutos(horas) {
                        var partes = horas.split(':');
                        var horasNumeros = parseInt(partes[0], 10);  // Horas (pode ser positivo ou negativo)
                        var minutos = parseInt(partes[1], 10);       // Minutos

                        // Converte as horas para minutos totais
                        return (horasNumeros * 60) + (horasNumeros < 0 ? -minutos : minutos);
                    }
                        
                    // Função para ordenar a tabela
                    function ordenarTabela(coluna, ordem){
                        var linhas = tabela.find('tr').get();
                        
                        linhas.sort(function(a, b){
                            var valorA = $(a).children('td').eq(coluna).text();
                            var valorB = $(b).children('td').eq(coluna).text();

                            // Verifica se os valores estão no formato HHH:mm (inclui 1, 2 ou 3 dígitos nas horas)
                            if (valorA.match(/^-?\d{1,3}:\d{2}$/) && valorB.match(/^-?\d{1,3}:\d{2}$/)) {
                                valorA = horasParaMinutos(valorA);
                                valorB = horasParaMinutos(valorB);
                            }

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

                    // Evento de clique para ordenar a tabela ao clicar no cabeçalho
                    $('#titulos th').click(function(){
                        var coluna = $(this).index();
                        var ordem = $(this).data('order');
                        $('#tabela-empresas th').data('order', 'desc'); // Redefinir ordem de todas as colunas
                        $(this).data('order', ordem === 'desc' ? 'asc' : 'desc');
                        ordenarTabela(coluna, $(this).data('order'));

                        // Ajustar classes para setas de ordenação
                        $('#titulos th').removeClass('sort-asc sort-desc');
                        $(this).addClass($(this).data('order') === 'asc' ? 'sort-asc' : 'sort-desc');
                    });

                    $('#tabela1 tbody td').click(function(event) {
                        if ($(this).is(':first-child')) {
                            var textoPrimeiroTd = $(this).text().trim(); // Pega o texto do primeiro <td>
                            var status = '';
                            if(textoPrimeiroTd === 'Não Endossado'){
                                var status = 'N';
                            } else if (textoPrimeiroTd === 'Endo. Parcialmente'){
                                var status = 'EP';
                            } else{
                                var status = 'E'
                            }

                            $('#tabela-empresas tbody tr').each(function() {
                                var textoCelula = $(this).find('td').eq(3).text().trim(); // Pegar o texto da primeira célula (coluna 3) de cada linha
                                // Mostrar ou ocultar a linha com base na comparação
                                if (textoCelula === status) {
                                    $(this).show(); // Mostrar linha se o texto da célula corresponder ao valor clicado
                                } else {
                                    $(this).hide(); // Ocultar linha se o texto da célula for diferente
                                }
                            });

            
                        } else {
                            event.stopPropagation(); // Impede que o evento de clique se propague
                        }
                    });

                    $('#tabela1 thead tr th').click(function(event) {
                        if ($(this).is(':first-child')) {
                            var textoPrimeiroTd = $(this).text().trim(); // Pega o texto do primeiro <td>
                            $('#tabela-empresas tbody tr').each(function() {
                                $(this).show(); // Mostrar linha se o texto da célula corresponder ao valor clicado
                            });
                        } else {
                            event.stopPropagation(); // Impede que o evento de clique se propague
                        }
                    });

                    $('#tabela2 tbody td').click(function(event) {
                        if ($(this).is(':first-child')) {
                            var textoPrimeiroTd = $(this).text().trim(); // Pega o texto do primeiro <td>

                            // Definindo a condição de filtro com base no texto do primeiro <td>
                            var condicao;
                            if (textoPrimeiroTd === 'Meta') {
                                condicao = function(textoCelula) {
                                    return textoCelula === '00:00'; // Exibir se for igual a 00:00
                                };
                            } else if (textoPrimeiroTd === 'Positivo') {
                                condicao = function(textoCelula) {
                                    return textoCelula > '00:00'; // Exibir se for maior que 00:00
                                };
                            } else {
                                condicao = function(textoCelula) {
                                    return textoCelula < '00:00'; // Exibir se for menor que 00:00
                                };
                            }

                            // Percorrendo as linhas da tabela #tabela-empresas
                            $('#tabela-empresas tbody tr').each(function() {
                                var textoCelula = $(this).find('td').eq(12).text().trim(); // Pegar o texto da coluna 13 de cada linha
                                // Mostrar ou ocultar a linha com base na condição definida
                                if (condicao(textoCelula)) {
                                    $(this).show(); // Mostrar linha se a condição for verdadeira
                                } else {
                                    $(this).hide(); // Ocultar linha se a condição for falsa
                                }
                            });
                        } else {
                            event.stopPropagation(); // Impede que o evento de clique se propague
                        }
                    });

                    $('#tabela2 thead tr th').click(function(event) {
                        if ($(this).is(':first-child')) {
                            var textoPrimeiroTd = $(this).text().trim(); // Pega o texto do primeiro <td>
                            $('#tabela-empresas tbody tr').each(function() {
                                $(this).show(); // Mostrar linha se o texto da célula corresponder ao valor clicado
                            });
                        } else {
                            event.stopPropagation(); // Impede que o evento de clique se propague
                        }
                    });

                    ".$carregarDados."
                });

                //Variação dos campos de pesquisa{
                    var camposAcao = document.getElementsByName('campoAcao');
                    if (camposAcao[0].checked){
                        document.getElementById('botaoContexBuscar').innerHTML = 'Buscar';
                    }
                    if (camposAcao[1].checked){
                        document.getElementById('botaoContexBuscar').innerHTML = 'Atualizar';
                    }
                    camposAcao[0].addEventListener('change', function() {
                        if (camposAcao[0].checked){
                            document.getElementById('botaoContexBuscar').innerHTML = 'Buscar';
                        }
                    });
                    camposAcao[1].addEventListener('change', function() {
                        if (camposAcao[1].checked){
                            document.getElementById('botaoContexBuscar').innerHTML = 'Atualizar';
                        }
                    });
                //}
            </script>"
        ;
    }

    function index(){

        if(empty($_POST["busca_dataMes"])){
            $_POST["busca_dataMes"] = date("Y-m"); 
        }

        if(!empty($_POST["acao"])){
            if($_POST["busca_dataMes"] > date("Y-m")){
                unset($_POST["acao"]);
                $_POST["errorFields"][] = "busca_dataMes";
                set_status("ERRO: Insira um mês menor ou igual ao atual. (".date("m/Y").")");
                cabecalho("Relatório Geral de Saldo");
            }elseif($_POST["acao"] == "atualizarPainel"){
                echo "<script>alert('Atualizando os painéis, aguarde um pouco.')</script>";
                ob_flush();
                flush();

                //Este comando de cabecalho deve ficar entre o alert() e a chamada de criar_relatorio_saldo() para notificar e aparecer o ícone de carregamento antes de começar o processamento
                cabecalho("Relatório Geral de Saldo");

                criar_relatorio_saldo();
            }else{
                cabecalho("Relatório Geral de Saldo");
            }
        }else{
            cabecalho("Relatório Geral de Saldo");
        }

        // $texto = "<div style=''><b>Periodo da Busca:</b> $monthName de $year</div>";
        //position: absolute; top: 101px; left: 420px;

        $campoAcao = 
            "<div class='col-sm-2 margin-bottom-5' style='min-width: fit-content;'>
				<label>"."Ação"."</label><br>
				<label class='radio-inline'>
					<input type='radio' name='campoAcao' value='buscar' ".((empty($_POST["campoAcao"]) || $_POST["campoAcao"] == "buscar")? "checked": "")."> Buscar
				</label>
				<label class='radio-inline'>
          			<input type='radio' name='campoAcao' value='atualizarPainel'".(!empty($_POST["campoAcao"]) && $_POST["campoAcao"] == "atualizarPainel"? "checked": "")."> Atualizar
				</label>
			</div>"
        ;
        
        $campos = [
            combo_net("Empresa", "empresa", $_POST["empresa"] ?? "", 4, "empresa", ""),
            $campoAcao,
            campo_mes("Mês*", "busca_dataMes", ($_POST["busca_dataMes"]?? date("Y-m")), 2),
        ];



        $botao_volta = "";
        if(!empty($_POST["empresa"])){
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
        $path = "./arquivos/saldos";
        $periodoRelatorio = ["dataInicio" => "", "dataFim" => ""];

        $contagemSaldos = [
            "positivos" => 0,
            "meta" => 0,
            "negativos" => 0
        ];
        $contagemEndossos = [
            "E" => 0,
            "EP" => 0,
            "N" => 0
        ];
        $totais = [
            "jornadaPrevista" 	=> "00:00",
            "jornadaEfetiva" 	=> "00:00",
            "HESemanal" 		=> "00:00",
            "HESabado" 			=> "00:00",
            "adicionalNoturno" 	=> "00:00",
            "esperaIndenizada" 	=> "00:00",
            "saldoAnterior" 	=> "00:00",
            "saldoPeriodo" 		=> "00:00",
            "saldoFinal" 		=> "00:00"
        ];

        $periodoRelatorio = [
            "dataInicio" => "1900-01-01",
            "dataFim" => "1900-01-01"
        ];
        
        
        if(!empty($_POST["acao"]) && $_POST["acao"] == "buscar"){
            $path .= "/".$_POST["busca_dataMes"];
            if(!empty($_POST["empresa"])){
                //Painel dos saldos dos motoristas de uma empresa específica
                $aEmpresa = mysqli_fetch_assoc(query(
                    "SELECT * FROM empresa"
                    ." WHERE empr_tx_status = 'ativo'"
                        ." AND empr_nb_id = ".$_POST["empresa"]
                    ." LIMIT 1;"
                ));
                $path .= "/".$aEmpresa["empr_nb_id"];
                if(is_dir($path)){
                    $encontrado = true;
                    $pastaSaldosEmpresa = dir($path);
                    while($arquivo = $pastaSaldosEmpresa->read()){
                        if(!in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresa_"))){
                            $arquivos[] = $arquivo;
                        }
                    }
                    $pastaSaldosEmpresa->close();
    
                    $dataEmissao = "Atualizado em: ".date("d/m/Y H:i", filemtime($path."/"."empresa_".$aEmpresa["empr_nb_id"].".json")); //Utilizado no HTML.
                    $periodoRelatorio = json_decode(file_get_contents($path."/"."empresa_".$aEmpresa["empr_nb_id"].".json"), true);
                    $periodoRelatorio = [
                        "dataInicio" => $periodoRelatorio["dataInicio"],
                        "dataFim" => $periodoRelatorio["dataFim"]
                    ];
                    $periodoRelatorio["dataInicio"] = DateTime::createFromFormat("Y-m-d", $periodoRelatorio["dataInicio"])->format("d/m");
                    $periodoRelatorio["dataFim"] = DateTime::createFromFormat("Y-m-d", $periodoRelatorio["dataFim"])->format("d/m");
    
                    $motoristas = [];
                    foreach($arquivos as $arquivo){
                        $json = json_decode(file_get_contents($path."/".$arquivo), true);
                        $json["dataAtualizacao"] = date("d/m/Y H:i", filemtime($path."/".$arquivo));
                        foreach($totais as $key => $value){
                            $totais[$key] = operarHorarios([$totais[$key], $json[$key]], "+");
                        }
                        $motoristas[] = $json;
                    }
                    foreach($arquivos as &$arquivo){
                        $arquivo = $path."/".$arquivo;
                    }
                    $totais["empresaNome"] = $aEmpresa["empr_tx_nome"];
    
                    foreach($motoristas as $saldosMotorista){
                        $contagemEndossos[$saldosMotorista["statusEndosso"]]++;
                        if($saldosMotorista["saldoFinal"] === "00:00"){
                            $contagemSaldos["meta"]++;
                        }elseif($saldosMotorista["saldoFinal"][0] == "-"){
                            $contagemSaldos["negativos"]++;
                        }else{
                            $contagemSaldos["positivos"]++;
                        }
                    }
                }
            }else{
                //Painel geral das empresas
                $empresas = [];
                $logoEmpresa = mysqli_fetch_assoc(query(
                    "SELECT empr_tx_logo FROM empresa"
                    ." WHERE empr_tx_status = 'ativo'"
                        ." AND empr_tx_Ehmatriz = 'sim'"
                    ." LIMIT 1;"
                ))["empr_tx_logo"];//Utilizado no HTML.

                $logoEmpresa = $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/".$logoEmpresa;

                
                if(is_dir($path) && is_file($path."/empresas.json")){
                    $encontrado = true;
                    $arquivoGeral = $path."/empresas.json";
                    $dataEmissao = "Atualizado em: ".date("d/m/Y H:i", filemtime($arquivoGeral)); //Utilizado no HTML.
                    $arquivoGeral = json_decode(file_get_contents($arquivoGeral), true);

                    $periodoRelatorio = [
                        "dataInicio" => $arquivoGeral["dataInicio"],
                        "dataFim" => $arquivoGeral["dataFim"]
                    ];

                    $periodoRelatorio["dataInicio"] = DateTime::createFromFormat("Y-m-d", $periodoRelatorio["dataInicio"])->format("d/m");
                    $periodoRelatorio["dataFim"] = DateTime::createFromFormat("Y-m-d", $periodoRelatorio["dataFim"])->format("d/m");

                    $pastaSaldos = dir($path);
                    while($arquivo = $pastaSaldos->read()){
                        if(!empty($arquivo) && !in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresas"))){
                            $arquivo = $path."/".$arquivo."/empresa_".$arquivo.".json";
                            $arquivos[] = $arquivo;
                            $json = json_decode(file_get_contents($arquivo), true);
                            foreach($totais as $key => $value){
                                $totais[$key] = operarHorarios([$totais[$key], $json["totais"][$key]], "+");
                            }
                            $empresas[] = $json;
                        }
                    }
                    $pastaSaldos->close();
                    
                    foreach($empresas as $empresa){
                        if($empresa["totais"]["saldoFinal"] === "00:00"){
                            $contagemSaldos["meta"]++;
                        }elseif($empresa["totais"]["saldoFinal"][0] == "-"){
                            $contagemSaldos["negativos"]++;
                        }else{
                            $contagemSaldos["positivos"]++;
                        }

                        if ($empresa["percEndossado"] === 1) {
                            $contagemEndossos["E"]++;
                        }elseif($empresa["percEndossado"] === 0){
                            $contagemEndossos["N"]++;
                        }else{
                            $contagemEndossos["EP"]++;
                        }
                    }
                }
            }
        }

        [$percEndosso["E"], $percEndosso["EP"], $percEndosso["N"]] = calcPercs(array_values($contagemEndossos));
        [$performance["positivos"], $performance["meta"], $performance["negativos"]] = calcPercs(array_values($contagemSaldos));

        echo 
            "<script>
                var endossos = {
                    'totais': {
                        'E': ".$contagemEndossos["E"].",
                        'EP': ".$contagemEndossos["EP"].",
                        'N': ".$contagemEndossos["N"]."
                    },
                    'porcentagens': {
                        'E': ".$percEndosso["E"].",
                        'EP': ".$percEndosso["EP"].",
                        'N': ".$percEndosso["N"].",
                    }
                }
                var saldos = {
                    'totais': {
                        'meta': ".$contagemSaldos["meta"].",
                        'positivos': ".$contagemSaldos["positivos"].",
                        'negativos': ".$contagemSaldos["negativos"].",
                    },
                    'porcentagens': {
                        'meta': ".$performance["meta"].",
                        'positivos': ".$performance["positivos"].",
                        'negativos': ".$performance["negativos"].",
                    }
                };
            </script>"
        ;
        if($encontrado){
            $rowTotais = "<tr class='totais'>";
            $rowTitulos = "<tr id='titulos' class='titulos'>";

            if(!empty($_POST["empresa"])){
                $rowTotais .= 
                    "<th colspan='2'>".$totais["empresaNome"]."</th>"
                    ."<th colspan='1'></th>"
                    ."<th colspan='1'></th>"
                    ."<th colspan='1'>".$totais["jornadaPrevista"]."</th>"
                    ."<th colspan='1'>".$totais["jornadaEfetiva"]."</th>"
                    ."<th colspan='1'>".$totais["HESemanal"]."</th>"
                    ."<th colspan='1'>".$totais["HESabado"]."</th>"
                    ."<th colspan='1'>".$totais["adicionalNoturno"]."</th>"
                    ."<th colspan='1'>".$totais["esperaIndenizada"]."</th>"
                    ."<th colspan='1'>".$totais["saldoAnterior"]."</th>"
                    ."<th colspan='1'>".$totais["saldoPeriodo"]."</th>"
                    ."<th colspan='1'>".$totais["saldoFinal"]."</th>";
                ;

                $rowTitulos .= 
                    "<th class='matricula'>Matrícula</th>"
                    ."<th class='nome'>Nome</th>"
                    ."<th class='ocupacao'>Ocupação</th>"
                    ."<th class='status'>Status Endosso</th>"
                    ."<th class='jornadaPrevista'>Jornada Prevista</th>"
                    ."<th class='jornadaEfetiva'>Jornada Efetiva</th>"
                    ."<th class='HESemanal'>H.E. Semanal</th>"
                    ."<th class='HEEx'>H.E. Ex.</th>"
                    ."<th class='adicionalNoturno'>Adicional Noturno</th>"
                    ."<th class='esperaIndenizada'>Espera Indenizada</th>"
                    ."<th class='saldoAnterior'>Saldo Anterior</th>"
                    ."<th class='saldoPeriodo'>Saldo Período</th>"
                    ."<th class='saldoFinal'>Saldo Final</th>"
                ;
            }else{
                $rowTotais .= 
                    "<th colspan='1'></th>"
                    ."<th colspan='1'></th>"
                    ."<th colspan='1'></th>"
                    ."<th colspan='1'>".$totais["jornadaPrevista"]."</th>"
                    ."<th colspan='1'>".$totais["jornadaEfetiva"]."</th>"
                    ."<th colspan='1'> ".(($totais["HESemanal"] == "00:00")? '': $totais["HESemanal"])."</th>"
                    ."<th colspan='1'> ".(($totais["HESabado"] == "00:00")? '': $totais["HESabado"])."</th>"
                    ."<th colspan='1'> ".(($totais["adicionalNoturno"] == "00:00")? '': $totais["adicionalNoturno"])."</th>"
                    ."<th colspan='1'> ".(($totais["esperaIndenizada"] == "00:00")? '': $totais["esperaIndenizada"])."</th>"
                    ."<th colspan='1'> ".(($totais["saldoAnterior"] == "00:00")? '': $totais["saldoAnterior"])."</th>"
                    ."<th colspan='1'> ".(($totais["saldoPeriodo"] == "00:00")? '': $totais["saldoPeriodo"])."</th>"
                    ."<th colspan='1'> ".(($totais["saldoFinal"] == "00:00")? '': $totais["saldoFinal"])."</th>"
                ;

                $rowTitulos .= 
                    "<th data-column='nome' data-order='asc'>Nome da Empresa/Filial</th>"
                    ."<th data-column='percEndossados' data-order='asc'>% Endossados</th>"
                    ."<th data-column='qtdMotoristas' data-order='asc'>Qtd. Motoristas</th>"
                    ."<th data-column='jornadaPrevista' data-order='asc'>Jornada Prevista</th>"
                    ."<th data-column='JornadaEfetiva' data-order='asc'>Jornada Efetiva</th>"
                    ."<th data-column='HESemanal' data-order='asc'>H.E. Semanal</th>"
                    ."<th data-column='HEEx' data-order='asc'>H.E. Ex.</th>"
                    ."<th data-column='adicionalNoturno' data-order='asc'>Adicional Noturno</th>"
                    ."<th data-column='esperaIndenizada' data-order='asc'>Espera Indenizada</th>"
                    ."<th data-column='saldoAnterior' data-order='asc'>Saldo Anterior</th>"
                    ."<th data-column='saldoPeriodo' data-order='asc'>Saldo Periodo</th>"
                    ."<th data-column='saldoFinal' data-order='asc'>Saldo Final</th>"
                ;
            }
            $rowTotais .= "</tr>";
            $rowTitulos .= "</tr>";

            $titulo = "Geral de saldo";
            include_once "painel_html.php";

            echo "<div class='script'>"
                ."<script>"
                    .((!empty($_POST["empresa"]))? "document.getElementById('tabela1').style.display = 'table';": "")
                    // ."console.log(endossos);"
                    ."document.getElementsByClassName('porcentagemEndo')[0].getElementsByTagName('td')[1].innerHTML = endossos.totais.E;
                    document.getElementsByClassName('porcentagemEndoPc')[0].getElementsByTagName('td')[1].innerHTML = endossos.totais.EP;
                    document.getElementsByClassName('porcentagemNaEndo')[0].getElementsByTagName('td')[1].innerHTML = endossos.totais.N;
                    document.getElementsByClassName('porcentagemEndo')[0].getElementsByTagName('td')[2].innerHTML = Math.round(endossos.porcentagens.E*10000)/100+'%';
                    document.getElementsByClassName('porcentagemEndoPc')[0].getElementsByTagName('td')[2].innerHTML = Math.round(endossos.porcentagens.EP*10000)/100+'%';
                    document.getElementsByClassName('porcentagemNaEndo')[0].getElementsByTagName('td')[2].innerHTML = Math.round(endossos.porcentagens.N*10000)/100+'%';
                    
                    document.getElementsByClassName('porcentagemPosi')[0].getElementsByTagName('td')[1].innerHTML = saldos.totais.positivos;
                    document.getElementsByClassName('porcentagemMeta')[0].getElementsByTagName('td')[1].innerHTML = saldos.totais.meta;
                    document.getElementsByClassName('porcentagemNega')[0].getElementsByTagName('td')[1].innerHTML = saldos.totais.negativos;
                    document.getElementsByClassName('porcentagemPosi')[0].getElementsByTagName('td')[2].innerHTML = Math.round(saldos.porcentagens.positivos*10000)/100+'%';
                    document.getElementsByClassName('porcentagemMeta')[0].getElementsByTagName('td')[2].innerHTML = Math.round(saldos.porcentagens.meta*10000)/100+'%';
                    document.getElementsByClassName('porcentagemNega')[0].getElementsByTagName('td')[2].innerHTML = Math.round(saldos.porcentagens.negativos*10000)/100+'%';
                    document.getElementsByClassName('script')[0].innerHTML = '';
                </script>"
            ;
            echo "</div>";
        }else{
            if(!empty($_POST["acao"]) && $_POST["acao"] == "buscar"){
                set_status("Não Possui dados desse mês");
                echo "<script>alert('Não Possui dados desse mês')</script>";
            }
        }
        
        carregarJS($arquivos);
        rodape();
    }