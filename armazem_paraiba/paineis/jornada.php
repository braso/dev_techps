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
                                url: urlArquivo + '?v=' + new Date().getTime(),
                                dataType: 'json',
                                success: function(data){
                                    var row = {};
                                    $.each(data, function(index, item){
                                        console.log(item);

                                        function processaCampo(campo,diferencaDias, hora) {
                                            // Só aplicar a lógica se a data não for a atual
                                            const [horas, minutos] = hora.split(':').map(Number);
                                            const horaEspecifica = new Date();
                                            horaEspecifica.setDate(horaEspecifica.getDate() - 1); // Configura para o dia anterior
                                            horaEspecifica.setHours(horas);
                                            horaEspecifica.setMinutes(minutos);
                                            horaEspecifica.setSeconds(0); // Para garantir que os segundos estejam zerados

                                            const horaAtual = new Date(); // Hora atual
                                            const diferencaMilliseconds = horaAtual - horaEspecifica; // Diferença em milissegundos
                                            const diferencaHoras = Math.floor(diferencaMilliseconds / (1000 * 60 * 60)); // Converte para horas

                                            // Adiciona a diferença de dias em horas
                                            const totalHoras = diferencaHoras + (diferencaDias * 24);
                                            const horasFormatadas = Math.floor(totalHoras);
                                            const minutosFormatados = Math.round((totalHoras - horasFormatadas) * 60);

                                            const resultadoFormatado = `\${horasFormatadas}:\${String(minutosFormatados).padStart(2, '0')}`;

                                            // console.log(resultadoFormatado);
                                            if(diferencaDias !== 0){
                                                return resultadoFormatado+' D+' + diferencaDias;
                                            }
                                            return campo;
                                        }

                                        var cor = '';
                                        cor = 'background-color: var(--var-red); color: white;';
                                        var diferencaDias = item.diaDiferenca;

                                        var jornada = processaCampo(item.jornada, diferencaDias, item.inicioJornada);
                                        var refeicao = item.refeicao;
                                        var espera = item.espera;
                                        var descanso = item.descanso;
                                        var repouso = item.repouso;

                                    ". $linha . "
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

                       $(document).ready(function() {
                            // Obtém o botão
                            const button = document.getElementById('botaoContexBuscar');

                            // Inicializa o select2 no campo 'empresa'
                            $('#empresa').select2();

                            // Verifica se já há uma opção selecionada ao carregar a página
                            if ($('#empresa').val()) {
                                button.removeAttribute('disabled'); // Habilita o botão se houver um valor selecionado
                            } else {
                                button.setAttribute('disabled', true); // Desabilita se não houver
                            }

                            // Escuta o evento 'select2:select' para capturar quando uma nova opção é selecionada
                            $('#empresa').on('select2:select', function(e) {
                                button.removeAttribute('disabled'); // Habilita o botão ao selecionar
                            });

                            // Escuta o evento 'select2:unselect' para capturar quando uma opção é desmarcada (se múltiplo)
                            $('#empresa').on('select2:unselect', function(e) {
                                button.setAttribute('disabled', true); // Desabilita o botão ao desmarcar
                            });
                        });
                    //}
                </script>";
    }

    function index() {

        if (empty($_POST["busca_dataMes"])) {
            $_POST["busca_dataMes"] = date("Y-m");
        }

        cabecalho("Relatorio de Jornada Aberta");

        // $texto = "<div style=''><b>Periodo da Busca:</b> $monthName de $year</div>";
        //position: absolute; top: 101px; left: 420px;

        $campos = [
            combo_net("Empresa", "empresa", $_POST["empresa"] ?? "", 4, "empresa", ""),
            campo_mes("Mês*", "busca_dataMes", ($_POST["busca_dataMes"] ?? date("Y-m")), 2),
        ];

        $botao_imprimir = "<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>";

        $buttons = [
            botao("Buscar", "", "", "", "", "", "btn btn-info"),
            $botao_imprimir,
        ];

        abre_form();
        linha_form($campos);
        fecha_form($buttons);

        $arquivos = [];
        $dataEmissao = ""; //Utilizado no HTML
        $encontrado = false;
        $path = "./arquivos/jornada";
        $periodoRelatorio = ["dataInicio" => "", "dataFim" => ""];

        if (!empty($_POST["empresa"]) && !empty($_POST["busca_dataMes"])) {
             require_once "funcoes_paineis.php";
            //  $tempoInicio = microtime(true);
             criar_relatorio_jornada();
            //  $tempoFim = microtime(true);
            //  $tempoExecucao = $tempoFim - $tempoInicio;
            //  echo "Tempo de execução: " . number_format($tempoExecucao, 4) . " segundos";

            $empresa = mysqli_fetch_assoc(query(
                "SELECT * FROM empresa"
                    . " WHERE empr_tx_status = 'ativo'"
                    . " AND empr_nb_id = " . $_POST["empresa"]
                    . " LIMIT 1;"
            ));

            $path .= "/" . $_POST["busca_dataMes"] . "/" . $empresa["empr_nb_id"];

            if (is_dir($path)) {
                $pasta = dir($path);
                while ($arquivo = $pasta->read()) {
                    if (!in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresa_"))) {
                        $arquivos[] = $arquivo;
                    }
                }
                $pasta->close();

                foreach ($arquivos as &$arquivo) {
                    $arquivo = $path . "/" . $arquivo;
                }

                if (!empty($arquivo)) {
                    $dataEmissao = "Atualizado em: " . date("d/m/Y H:i", filemtime($arquivo)); //Utilizado no HTML.
                    $arquivoGeral = json_decode(file_get_contents($arquivo), true);

                    $periodoRelatorio = [
                        "dataInicio" => $arquivoGeral[0]["dataInicio"],
                        "dataFim" => $arquivoGeral[0]["dataFim"]
                    ];

                    $encontrado = true;
                } else {
                    require_once "funcoes_paineis.php";
                    criar_relatorio_jornada();
                    $encontrado = true;
                    // echo "<script>alert('Não tem jornadas abertas.')</script>";
                }
            } else {
                $encontrado = false;
            }
        }

        if ($encontrado) {
            // $rowTotais = "<tr class='totais'>";
            $rowTitulos = "<tr id='titulos' class='titulos'>";
            $rowTitulos .=
                "<th class='data'>Data</th>"
                . "<th class='matricula'>Matrícula</th>"
                . "<th class='nome'>Nome</th>"
                . "<th class='ocupacao'>Ocupação</th>"
                . "<th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='jornada'>Jornada</th>"
                . "<th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='jornadaEfetiva'>Jornada Efetiva</th>"
                . "<th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='refeicao'>Refeicao</th>"
                . "<th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='espera'>Espera</th>"
                . "<th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='descanso'>Descanso</th>"
                . "<th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='repouso'>Repouso</th>";
            $rowTitulos .= "</tr>";
            $titulo = "de Jornada Aberta";
            include_once "painel_html2.php";
        }

        carregarJS($arquivos);

        rodape();
    }
