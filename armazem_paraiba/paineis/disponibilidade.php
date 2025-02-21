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
        $filtro = $_POST["busca_Dispobilidade"];

        $linha = "linha = '<tr>'";
        if (!empty($_POST["empresa"])) {
            $linha .= " +'<td style=\'text-align: center;'+ css +'\'>'+item.ocupacao+'</td>'
                        +'<td style=\'text-align: center;'+ css +'\'>'+item.matricula+'</td>'
                        +'<td style=\'text-align: center;'+ css +'\'>'+item.Nome+'</td>'
                        +'<td style=\'text-align: center;'+ css +'\'>'+item.ultimaJornada+'</td>'
                        +'<td style=\'text-align: center;'+ css +'\'>'+item.consulta+'</td>'
                        +'<td style=\'text-align: center;'+ css +'\'>'+item.repouso+'</td>'
                        +'<td style=\'text-align: center;'+ css +'\' class =>'+item.Apos11+'</td>'
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
                        var filtro = '".$filtro ."';
                        console.log(filtro);

                        function carregarDados(urlArquivo){
                            $.ajax({
                                url: urlArquivo + '?v=' + new Date().getTime(),
                                dataType: 'json',
                                success: function(data){
                                    var row = {};
                                    $.each(data, function(index, item){
                                        // console.log(index);
                                        // console.log(item);
                                        var css = '';
                                        if(index == 'disponivel'){
                                            css = 'background-color: lightgreen;';
                                        } else if(index == 'naoPermitido'){
                                            css = 'background-color: var(--var-lightred);';
                                        } else {
                                            css = 'background-color: var(--var-lightorange);';
                                        }

                                        if (filtro !== '' && index !== filtro) {
                                            return;
                                        }
                                        
                                        if (Array.isArray(item)) {
                                            // Itera sobre o array de motoristas
                                            $.each(item, function(index, item) {
                                                ". $linha."
                                                tabela.append(linha);
                                            });
                                        }
                                        
                                        
                                    });
                                    campo.html('<b>- Funcionários em jornada: </b>'+data.total.totalMotoristasJornada);
                                    campo2.html('<b>Funcionários disponíveis após 11 horas de interstício para iniciar uma jornada: </b>'+data.total.totalMotoristasLivres)
                                },
                                error: function(){
                                    console.error('Erro ao carregar os dados.');
                                }
                            });
                        }
                        // Função para converter valores para comparação
                        function converterValor(valor, coluna) {
                            valor = valor.trim(); // Remove espaços extras

                            // Se for uma data no formato 'DD/MM/YYYY HH:mm', converte para timestamp
                            if (['jornada', 'consulta', 'disponível'].includes(coluna)) {
                                var dataMoment = moment(valor, 'DD/MM/YYYY HH:mm', true);
                                if (dataMoment.isValid()) {
                                    return dataMoment.valueOf(); // Retorna timestamp para ordenação correta
                                }
                            }

                            // Se for um tempo no formato 'HHH:i' (exemplo: 125:30)
                            if (coluna === 'repouso' && /^\d+:\d{2}$/.test(valor)) {
                                var partes = valor.split(':');
                                var horas = parseInt(partes[0], 10);
                                var minutos = parseInt(partes[1], 10);
                                return horas * 60 + minutos; // Converte para minutos totais para ordenação
                            }

                            // Se for número, converte para inteiro ou float
                            if (!isNaN(valor.replace(',', '.'))) {
                                return parseFloat(valor.replace(',', '.')); // Garante ordenação correta de números
                            }

                            return valor.toUpperCase(); // Retorna texto em maiúsculas para comparação alfabética
                        }

                        // Função para ordenar a tabela
                        function ordenarTabela(coluna, ordem) {
                            var linhas = tabela.find('tr').get();
                            
                            linhas.sort(function(a, b) {
                                var valorA = converterValor($(a).children('td').eq(coluna).text(), colunasPermitidas[coluna]);
                                var valorB = converterValor($(b).children('td').eq(coluna).text(), colunasPermitidas[coluna]);

                                if (valorA < valorB) {
                                    return ordem === 'asc' ? -1 : 1;
                                }
                                if (valorA > valorB) {
                                    return ordem === 'asc' ? 1 : -1;
                                }
                                return 0;
                            });

                            $.each(linhas, function(index, row) {
                                tabela.append(row);
                            });
                        }

                        var colunasPermitidas = ['ocupacao', 'matricula', 'nome', 'jornada', 'consulta', 'repouso', 'disponível'];

                        // Evento de clique para ordenar a tabela ao clicar no cabeçalho
                        $('#titulos th').click(function() {
                            var colunaClicada = $(this).attr('class');

                            var classePermitida = colunasPermitidas.some(function(coluna) {
                                return colunaClicada.includes(coluna);
                            });

                            if (classePermitida) {
                                var coluna = $(this).index();
                                var ordem = $(this).data('order');

                                // Redefinir ordem de todas as colunas
                                $('#titulos th').data('order', 'desc');
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
        cabecalho("Relatório de disponibilidade");

        // $texto = "<div style=''><b>Periodo da Busca:</b> $monthName de $year</div>";
        //position: absolute; top: 101px; left: 420px;

        $campos = [
            combo_net("Empresa", "empresa", $_POST["empresa"] ?? "", 4, "empresa", ""),
            combo("Ocupação", "busca_ocupacao", ($_POST["busca_ocupacao"] ?? ""), 2, 
            ["" => "Todos", "Motorista" => "Motorista", "Ajudante" => "Ajudante", "Funcionário" => "Funcionário"]),
            campo_dataHora("Dispobilidade","busca_periodo",(!empty($_POST["busca_periodo"])? $_POST["busca_periodo"]:  date("Y-m-d H:i")),
            2),
            combo("Status Dispobilidade", "busca_Dispobilidade", ($_POST["busca_Dispobilidade"] ?? ""), 2, 
            ["" => "Todos", "disponivel" => "Disponives", "naoPermitido" => "Indisponives", "parcial" => "Parcialmente disponível"]),
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
            logisticas();

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
                $titulo = "Relatório de disponibilidade";
                // $mostra = false;
                $rowTitulos = "<tr id='titulos' class='titulos'>";
                $rowTitulos .= "
                <th class='ocupacao'>Ocupação</th>
                <th class='matricula'>Matrícula</th>
                <th class='nome'>Nome</th>
                <th class='jornada'>Último Fechamento de jornada</th>
                <th class='consulta'>Última Consulta</th>
                <th class='repouso'>Tempo de Repouso</th>
                <th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='disponível'> Data disponível</th>";
                $rowTitulos .= "</tr>";
                $painelDisp = true;
                include_once "painel_html2.php";
            }

            carregarJS($arquivos);
        }


        rodape();
    }