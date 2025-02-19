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
    require_once __DIR__."/funcoes_paineis.php";
    // criar_relatorio_jornada();
 
    function carregarJS(array $arquivos) {

        $linha = "linha = '<tr>'";
        if (!empty($_POST["empresa"])) {
            $linha .= "+'<td style=\'text-align: center;\'>'+item.data+' '+ultimoValor+'</td>'
                        +'<td style=\'text-align: center;\'>'+item.matricula+'</td>'
                        +'<td style=\'text-align: center;\'>'+item.nome+'</td>'
                        +'<td style=\'text-align: center;\'>'+item.ocupacao+'</td>'
                        +'<td class ='+css+'>'+jornada+'</td>'
                        +'<td class ='+jornadaEfetivaCor+'>'+jornadaEfetiva+'</td>'
                        +'<td class = \'jornada\'>'+(item.refeicao? item.refeicao: '<strong>----</strong>')+'</td>'
                        +'<td class = \'jornada\'>'+(item.espera ? item.espera : '<strong>----</strong>')+'</td>'
                        +'<td class = \'jornada\'>'+(item.descanso ? item.descanso : '<strong>----</strong>')+'</td>'
                        +'<td class = \'jornada\'>'+(item.repouso ? item.repouso : '<strong>----</strong>')+'</td>'
                    +'</tr>';";
        }

        $carregarDados = "";
        foreach ($arquivos as $arquivo) {
            $carregarDados .= "carregarDados('".$arquivo."');";
        }
        
        echo
        "<form name='myForm' method='post' action='".htmlspecialchars($_SERVER["PHP_SELF"]). "'>
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

                    console.log(new Date());

                    function converterParaMinutos(hora) {
                        if (!hora) return 0;  // Se o valor for vazio ou nulo, retorna 0
                        const [horas, minutos] = hora.split(':').map(Number);
                        return (horas * 60) + minutos;
                    }

                    function converterMinutosParaHHHMM(minutos) {
                        const horas = Math.floor(minutos / 60);
                        const minutosRestantes = minutos % 60;
                        return `\${horas}:\${String(minutosRestantes) . padStart(2, '0')}`;
                    }

                    function calcularJornadaElimite(horasTrabalhadas, jornadaPadrao, limiteExtra) {
                        if(jornadaPadrao === '00:00' || limiteExtra === undefined) {
                            jornadaPadrao = '08:00'
                        }
                            
                        if(limiteExtra === '00:00' || limiteExtra === undefined) {
                            limiteExtra = '02:00'
                        }

                        // Converter jornada padrão e limite extra para minutos
                        const [jornadaHoras, jornadaMinutos] = jornadaPadrao.split(':').map(Number);
                        const jornadaPadraoMinutos = jornadaHoras * 60 + jornadaMinutos;

                        const [limiteHoras, limiteMinutos] = limiteExtra.split(':').map(Number);
                        const limiteExtraMinutos = limiteHoras * 60 + limiteMinutos;

                        // Converter horas trabalhadas para minutos
                        const [horas, minutos] = horasTrabalhadas.split(':').map(Number);
                        const minutosTrabalhados = horas * 60 + minutos;

                        let corTexto = 'jornada';

                        if (minutosTrabalhados >= jornadaPadraoMinutos && minutosTrabalhados <= jornadaPadraoMinutos + limiteExtraMinutos) {
                            corTexto = 'jornadaYellow'; // Dentro do padrão e limite extra
                        } else if (minutosTrabalhados >= jornadaPadraoMinutos) {
                            corTexto = 'jornadaRed'; // Excede o limite extra
                        } else {
                            corTexto = 'jornada';
                        }

                        return corTexto;
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
                                        var ultimoValor = '';
                                        if (item.inicioJornada.includes(' ')) {
                                            const partes = item.inicioJornada.split(' '); 
                                            ultimoValor = partes.pop(); 
                                        } else {
                                            ultimoValor = item.inicioJornada;
                                        }
                                        
                                        function calculaDiferencaEmHoras(dataInicio, horaInicio) {
                                            // Convertendo a data de início no formato adequado (dd/mm/yyyy para yyyy-mm-dd)
                                            const dataParts = dataInicio.split('/');
                                            const dataFormatada = `\${dataParts[2]}-\${dataParts[1]}-\${dataParts[0]}`; // Formato: yyyy-mm-dd
                                            
                                            // Parsing da hora de início (horas e minutos)
                                            const [horasInicio, minutosInicio] = horaInicio.split(':').map(Number);
                                            
                                            // Criando a data completa de início com a data e hora fornecidas
                                            const inicio = new Date(dataFormatada);
                                            inicio.setHours(horasInicio, minutosInicio, 0, 0); // Configura a hora de início corretamente

                                            // Obtendo a data e hora atual
                                            const agora = new Date();
                                            
                                            // Calculando a diferença em milissegundos
                                            const diferencaMilliseconds = agora - inicio;

                                            // Calcular a diferença em dias (convertendo milissegundos para dias)
                                            const diferencaDias = Math.floor(diferencaMilliseconds / (1000 * 60 * 60 * 24)); // Diferença em dias

                                            // Calcular a diferença de horas restantes (ignorando os dias completos)
                                            const horasRestantes = Math.floor((diferencaMilliseconds % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));

                                            // Calcular a diferença de minutos restantes
                                            const minutosRestantes = Math.floor((diferencaMilliseconds % (1000 * 60 * 60)) / (1000 * 60));

                                            // Somando 24 horas para cada dia de diferença
                                            const totalHoras = ((diferencaDias - 1) * 24) + horasRestantes;

                                            // Formatação no formato HHH:mm:00
                                            const resultadoFormatado = `\${String(totalHoras)}:\${String(minutosRestantes) . padStart(2, '0')}`;

                                            return resultadoFormatado;
                                        }

                                        var jornada = calculaDiferencaEmHoras(item.data, ultimoValor);

                                        var diferencaDias = item.diaDiferenca;

                                        const refeicaoMinutos = converterParaMinutos(item.refeicao === '*' ? '00:00' : item.refeicao);
                                        const esperaMinutos = converterParaMinutos(item.espera === '*' ? '00:00' : item.espera);
                                        const descansoMinutos = converterParaMinutos(item.descanso === '*' ? '00:00' : item.descanso);
                                        const repousoMinutos = converterParaMinutos(item.repouso === '*' ? '00:00' : item.repouso);
                                        const jornadaMinutos = converterParaMinutos(jornada);

                                        if(item.adi5322 === 'sim'){
                                            let jornadaSemIntervalo = jornadaMinutos - (refeicaoMinutos + descansoMinutos + repousoMinutos);
                                                jornadaEfetiva = converterMinutosParaHHHMM(jornadaSemIntervalo);
                                        } else {
                                            let jornadaSemIntervalo = jornadaMinutos - (refeicaoMinutos + esperaMinutos + descansoMinutos + repousoMinutos);
                                            jornadaEfetiva = converterMinutosParaHHHMM(jornadaSemIntervalo);
                                        }


                                        let jornadaEfetivaCor = calcularJornadaElimite(jornadaEfetiva , item.jornadaDia, item.limiteExtras)

                                        var css = 'jornada';
                                        const limite = converterParaMinutos('10:00');
                                        if(jornadaMinutos > limite){
                                            css = 'jornadaD';
                                        }

                                        if (item.diaDiferenca !== 0) {
                                            jornada = jornada + ' (D+' + item.diaDiferenca + ') ';
                                        }

                                    ". $linha."
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

                        ".$carregarDados."
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

        $arquivos = [];
        $dataEmissao = ""; //Utilizado no HTML
        $encontrado = false;
        $path = "./arquivos/jornada";
        $periodoRelatorio = ["dataInicio" => "", "dataFim" => ""];

        if (!empty($_POST["empresa"])) {
             require_once "funcoes_paineis.php";
            //  $tempoInicio = microtime(true);
             criar_relatorio_jornada();
            //  $tempoFim = microtime(true);
            //  $tempoExecucao = $tempoFim - $tempoInicio;
            //  $tempoExecucaoMinutos = $tempoExecucao / 60;
            //  echo "Tempo de execução: " . number_format($tempoExecucaoMinutos, 4) . " minutos";

            $empresa = mysqli_fetch_assoc(query(
                "SELECT * FROM empresa
                    WHERE empr_tx_status = 'ativo'
                        AND empr_nb_id = {$_POST["empresa"]}
                    LIMIT 1;"
            ));

            $path .= "/".$empresa["empr_nb_id"];

            if (is_dir($path)) {
                $pasta = dir($path);
                while ($arquivo = $pasta->read()) {
                    if (!in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresa_"))) {
                        $arquivos[] = $arquivo;
                    }
                    $quantFun = " - <b>Total de funcionários com jornada:</b> ".count($arquivos);
                }
                $pasta->close();

                foreach ($arquivos as &$arquivo) {
                    $arquivo = $path."/".$arquivo;
                }

                if (!empty($arquivo)) {
                    $alertaEmissao = "<span style='color: red; border: 2px solid; padding: 2px; border-radius: 4px;'>
                        <i style='color:red; margin-right: 5px;' title='As informações do painel não correspondem à data de hoje.' class='fa fa-warning'></i>";
                    $dataEmissao = $alertaEmissao." Atualizado em: " . date("d/m/Y H:i", filemtime($arquivo)). "</span>"; //Utilizado no HTML.
                    $arquivoGeral = json_decode(file_get_contents($arquivo), true);

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
                "<th class='data'>Data</th>
                <th class='matricula'>Matrícula</th>
                <th class='nome'>Nome</th>
                <th class='ocupacao'>Ocupação</th>
                <th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='jornada'>Jornada</th>
                <th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='jornadaEfetiva'>Jornada Efetiva</th>
                <th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='refeicao'>Refeicao</th>
                <th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='espera'>Espera</th>
                <th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='descanso'>Descanso</th>
                <th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='repouso'>Repouso</th>";
            $rowTitulos .= "</tr>";
            $titulo = "Relatório de Jornada Aberta";
            $mostra = false;
            include_once "painel_html2.php";
        }

        carregarJS($arquivos);

        rodape();
    }