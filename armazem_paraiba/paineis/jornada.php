<?php
/* Modo debug
        ini_set("display_errors", 1);
        error_reporting(E_ALL);
    //*/

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0");

require "../funcoes_ponto.php";
require_once __DIR__ . "/funcoes_paineis.php";
// criar_relatorio_jornada();

function carregarJS(array $arquivos) {

    $linha = "linha = '<tr>'";
    if (!empty($_POST["empresa"])) {
        $linha .= "+'<td>'+formatarData(item.data)+'</td>'
                    +'<td>'+item.matricula+'</td>'
                    +'<td>'+item.nome+'</td>'
                    +'<td>'+item.ocupacao+'</td>'
                    +'<td>'+item.jornada+'</td>'
                    +'<td>'+item.jornadaEfetiva+'</td>'
                    +'<td><strong>'+(item.refeicao? item.refeicao : '-')+'</strong></td>'
                    +'<td>'+(item.espera? item.espera : '-')+'</td>'
                    +'<td>'+(item.descanso? item.descanso : '-')+'</td>'
                    +'<td>'+(item.repouso? item.repouso : '-')+'</td>'
                +'</tr>';";
    } 

    $carregarDados = "";
    foreach ($arquivos as $arquivo) {
        $carregarDados .= "carregarDados('" . $arquivo . "');";
    }

    echo
            "<script>
                function imprimir(){
                    window.print();
                }

                function formatarData(data) {
                    // Substitui os hífens por barras para o formato dd/mm/aaaa
                    return data.replace(/-/g, '/');
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
                    // function ordenarTabela(coluna, ordem){
                    //     var linhas = tabela.find('tr').get();
                    //     linhas.sort(function(a, b){
                    //         var valorA = $(a).children('td').eq(coluna).text().toUpperCase();
                    //         var valorB = $(b).children('td').eq(coluna).text().toUpperCase();

                    //         if(valorA < valorB){
                    //             return ordem === 'asc' ? -1 : 1;
                    //         }
                    //         if(valorA > valorB){
                    //             return ordem === 'asc' ? 1 : -1;
                    //         }
                    //         return 0;
                    //     });
                    //     $.each(linhas, function(index, row){
                    //         tabela.append(row);
                    //     });
                    // }

                    // Evento de clique para ordenar a tabela ao clicar no cabeçalho
                    // $('#titulos th').click(function(){
                    //     var coluna = $(this).index();
                    //     var ordem = $(this).data('order');
                    //     $('#tabela-empresas th').data('order', 'desc'); // Redefinir ordem de todas as colunas
                    //     $(this).data('order', ordem === 'desc' ? 'asc' : 'desc');
                    //     ordenarTabela(coluna, $(this).data('order'));

                    //     // Ajustar classes para setas de ordenação
                    //     $('#titulos th').removeClass('sort-asc sort-desc');
                    //     $(this).addClass($(this).data('order') === 'asc' ? 'sort-asc' : 'sort-desc');
                    // });

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

    cabecalho("Relatorio Geral de saldo");

    $extraCampoData = "";
    if(empty($_POST["busca_dataInicio"])){
        $_POST["busca_dataInicio"] = date("Y-m-01");
    }
    if(empty($_POST["busca_dataFim"])){
        $_POST["busca_dataFim"] = date("Y-m-d");
    }

    if($_POST["busca_dataInicio"] > date("Y-m-d") || $_POST["busca_dataFim"] > date("Y-m-d")){
        unset($_POST["acao"]);
        set_status("ERRO: Não é possível perquisar após a data atual.");
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
    $encontrado = true;
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

            $motoristas = [];
            foreach($arquivos as $arquivo){
                $json = json_decode(file_get_contents($path."/".$arquivo), true);
                $json["dataAtualizacao"] = date("d/m/Y H:i", filemtime($path."/".$arquivo));
                $motoristas[] = $json;
            }
            foreach($arquivos as &$arquivo){
                $arquivo = $path."/".$arquivo;
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
        ."<th class='jornada'>Jornada</th>"
        ."<th class='jornadaEfetiva'>Jornada Efetiva</th>"
        ."<th class='refeicao'>Refeicao</th>"
        ."<th class='espera'>Espera</th>"
        ."<th class='descanso'>Descanso</th>"
        ."<th class='repouso'>Repouso</th>";
        $rowTitulos .= "</tr>";
        include_once "painel_html2.php";
    }

    carregarJS($arquivos);
    
    rodape();
}