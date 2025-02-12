<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/
	require_once __DIR__."/funcoes_paineis.php";
	require __DIR__."/../funcoes_ponto.php";

	header("Expires: 01 Jan 2001 00:00:00 GMT");
	header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
	header('Cache-Control: post-check=0, pre-check=0', FALSE);
	header('Pragma: no-cache');

    function carregarJS(array $arquivos) {

        $linha = "linha = '<tr>'";
        if (!empty($_POST["empresa"])) {
            $linha .= " +'<td style=\'text-align: center;\'>'+item.matricula+'</td>'
                        +'<td style=\'text-align: center;\'>'+item.Nome+'</td>'
                        +'<td style=\'text-align: center;\'>'+item.ocupacao+'</td>'
                        +'<td style=\'text-align: center;\' class =>'+item.Apos11+'</td>'
                    +'</tr>';";
        }

        $carregarDados = "";
        foreach ($arquivos as $arquivo) {
            $carregarDados .= "carregarDados('".$arquivo."');";
        }
        
        echo
        "       <script>

                    function imprimir(){
                        window.print();
                    }

                    $(document).ready(function(){
                        var tabela = $('#tabela-empresas tbody');
                        var campo = $('.total-jornada');
                        var campo2 = $('.total-sem-jornada');

                        function carregarDados(urlArquivo){
                            $.ajax({
                                url: urlArquivo + '?v=' + new Date().getTime(),
                                dataType: 'json',
                                success: function(data){
                                    var row = {};
                                    $.each(data, function(index, item){
                                        console.log(item);
                                        console.log(index);
                                        if(!isNaN(index) && typeof item === 'object'){
                                            ". $linha."
                                            tabela.append(linha);
                                        }else{
                                            campo.html('<b>- Funcionários em jornada: </b>'+item.totalMotoristasJornada);
                                            campo2.html('<b>Funcionários disponíveis após 11 horas de interstício para iniciar uma jornada: </b>'+item.totalMotoristasLivres);
                                        }
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

                        ".$carregarDados."
                    });
                </script>";
    }
    
    function index() {
        cabecalho("Relatório de Jornada Aberta");

        // $texto = "<div style=''><b>Periodo da Busca:</b> $monthName de $year</div>";
        //position: absolute; top: 101px; left: 420px;

        $campos = [
            combo_net("Empresa", "empresa", $_POST["empresa"] ?? "", 4, "empresa", ""),
            combo("Ocupação", "busca_ocupacao", ($_POST["busca_ocupacao"] ?? ""), 2, 
            ["" => "Todos", "Motorista" => "Motorista", "Ajudante" => "Ajudante", "Funcionário" => "Funcionário"]),
        ];

        $botao_imprimir = "<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>";

        $buttons = [
            botao("Buscar", "", "", "", "", "", "btn btn-info"),
            $botao_imprimir,
        ];

        echo abre_form();
        echo linha_form($campos);
        echo fecha_form($buttons);

        if (!empty($_POST["empresa"])) {
            $path = "./arquivos/nc_logistica/".$_POST["empresa"];
            $encontrado = false;
            // logisticas();

            if (is_dir($path)) {
                $pasta = dir($path);
                while ($arquivo = $pasta->read()) {
                    if (!in_array($arquivo, [".", ".."])) {
                        $arquivos[] = $arquivo;
                    }
                }
                $pasta->close();

                foreach ($arquivos as &$arquivo) {
                    $arquivo = $path."/".$arquivo;
                }

                if (!empty($arquivo)){
                    $alertaEmissao = "<span style='color: red; border: 2px solid; padding: 2px; border-radius: 4px;'>
                        <i style='color:red; margin-right: 5px;' title='As informações do painel não correspondem à data de hoje.' class='fa fa-warning'></i>";
                    $dataEmissao = $alertaEmissao." Atualizado em: " . date("d/m/Y H:i", filemtime($arquivo)). "</span>";
                    $encontrado = true;
                }
            }else {
                $encontrado = false;
            }

            if ($encontrado) {
                $titulo = "Relatório de Logísticas";
                $mostra = false;
                $rowTitulos = "<tr id='titulos' class='titulos'>";
                $rowTitulos .= "
                <th class='matricula'>Matrícula</th>
                <th class='nome'>Nome</th>
                <th class='ocupacao'>Ocupação</th>
                <th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='jornada'> Quando poderá iniciar uma nova jornada</th>";
                $rowTitulos .= "</tr>";
                include_once "painel_html2.php";
            }

            carregarJS($arquivos);
        }


        rodape();
    }