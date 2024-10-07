<?php
/* Modo debug
        ini_set("display_errors", 1);
        error_reporting(E_ALL);
    //*/

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header('Cache-Control: post-check=0, pre-check=0', FALSE);
header('Pragma: no-cache');
header("Expires: 0");

require "../funcoes_ponto.php";
require_once __DIR__ . "/funcoes_paineis.php";
// criar_relatorio_jornada();

function carregarJS(array $arquivos) {

    $linha = "linha = '<tr>'";
    if (!empty($_POST["empresa"])) {
        $linha .= "+'<td style=\'text-align: center;\'>'+item.data+'</td>'
                    +'<td style=\'text-align: center;\'>'+item.matricula+'</td>'
                    +'<td style=\'text-align: center;\'>'+item.nome+'</td>'
                    +'<td style=\'text-align: center;\'>'+item.ocupacao+'</td>'
                    +'<td style=\'text-align: center;\'>'+item.jornada+'</td>'
                    +'<td style=\'text-align: center;\'>'+item.jornadaEfetiva+'</td>'
                    +'<td style=\'text-align: center;\'>'+(item.refeicao? item.refeicao : '<strong>----</strong>')+'</td>'
                    +'<td style=\'text-align: center;\'>'+(item.espera? item.espera : '<strong>----</strong>')+'</td>'
                    +'<td style=\'text-align: center;\'>'+(item.descanso? item.descanso : '<strong>----</strong>')+'</td>'
                    +'<td style=\'text-align: center;\'>'+(item.repouso? item.repouso : '<strong>----</strong>')+'</td>'
                +'</tr>';";
    } 

    $carregarDados = "";
    foreach ($arquivos as $arquivo) {
        $carregarDados .= "carregarDados('" . $arquivo . "');";
    }

    echo
            "<form name='myForm' method='post' action='".htmlspecialchars($_SERVER["PHP_SELF"])."'>
                <input type='hidden' name='atualizar' id='atualizar'>
                <input type='hidden' name='empresa' id='empresa'>
                <input type='hidden' name='busca_data' id='busca_data'>
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
                                $.each(data, function(index, item){"
                                . $linha
                                . "tabela.append(linha);"
                                . " console.log(item);
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
            </script>";
}

function index() {
    if(!empty($_POST["atualizar"])){
        echo "<script>alert('Atualizando os painéis, aguarde um pouco.')</script>";
        ob_flush();
        flush();
        require_once "funcoes_paineis.php";
        criar_relatorio_jornada();
    }

    cabecalho("Relatorio de Jornada Aberta");

    $extraCampoData = "";
    if(empty($_POST["busca_data"])){
        $_POST["busca_data"] = date("Y-m");
    }
    // $texto = "<div style=''><b>Periodo da Busca:</b> $monthName de $year</div>";
    //position: absolute; top: 101px; left: 420px;
    $fields = [
        combo_net("Empresa:", "empresa", $_POST["empresa"] ?? "", 4, "empresa", ""),
        campo_mes("Data", "busca_data", ($_POST["busca_data"] ?? ""), 2, $extraCampoData)
    ];

    $botao_volta = "";
    if(!empty($_POST["empresa"])){
        $botao_volta = "<button class='btn default' type='button' onclick='setAndSubmit(\"\")'>Voltar</button>";
    }
    $botao_imprimir = "<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>";
    if(!empty($_SESSION["user_tx_nivel"]) && is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"))){
        $botaoAtualizarPainel = "<a class='btn btn-warning' onclick='atualizarPainel()'> Atualizar Painel</a>";
    }
    $buttons = [
        botao("Buscar", "index", "", "", "", "", "btn btn-info"),
        $botao_imprimir,
        $botao_volta,
        $botaoAtualizarPainel
    ];


    abre_form("Filtro de Busca");
    linha_form($fields);
    fecha_form($buttons);

    $arquivos = [];
    $dataEmissao = ""; //Utilizado no HTML
    $encontrado = false;
    $path = "./arquivos/jornada";
    $periodoRelatorio = ["dataInicio" => "", "dataFim" => ""];

    if(!empty($_POST["empresa"]) && !empty($_POST["busca_data"])){
        
        $empresa = mysqli_fetch_assoc(query(
            "SELECT * FROM empresa"
            ." WHERE empr_tx_status = 'ativo'"
                ." AND empr_nb_id = ".$_POST["empresa"]
            ." LIMIT 1;"
        ));
        
        $path .= "/".$_POST["busca_data"]."/".$empresa["empr_nb_id"];

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