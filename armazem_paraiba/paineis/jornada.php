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

criar_relatorio_jornada();
// function carregarJS(array $arquivos) {

//     $linha = "linha = '<tr>'";
//     if (!empty($_POST["empresa"])) {
//         $linha .= "+'<td>'+row.matricula+'</td>'
//                     +'<td>'+row.nome+'</td>'
//                     +'<td>'+row.ocupacao+'</td>'
//                     +'<td>'+row.statusEndosso+'</td>'
//                     +'<td>'+(row.jornadaPrevista?? '')+'</td>'
//                     +'<td>'+(row.jornadaEfetiva?? '')+'</td>'
//                     +'<td>'+(row.he50APagar?? '')+'</td>'
//                     +'<td>'+(row.he100APagar?? '')+'</td>'
//                     +'<td>'+(row.adicionalNoturno?? '')+'</td>'
//                     +'<td>'+(row.esperaIndenizada?? '')+'</td>'
//                     +'<td>'+(row.saldoAnterior?? '')+'</td>'
//                     +'<td>'+(row.saldoPeriodo?? '')+'</td>'
//                     +'<td>'+(row.saldoFinal?? '')+'</td>'
//                 +'</tr>';";
//     } else {
//         $linha .= "+'<td style=\"cursor: pointer;\" onclick=setAndSubmit('+row.empr_nb_id+')>'+row.empr_tx_nome+'</td>'
//                     +'<td>'+Math.round(row.percEndossado*10000)/100+'%</td>'
//                     +'<td>'+row.qtdMotoristas+'</td>'
//                     +'<td>'+(row.totais.jornadaPrevista?? '')+'</td>'
//                     +'<td>'+(row.totais.jornadaEfetiva?? '')+'</td>'
//                     +'<td>'+(row.totais.he50APagar?? '')+'</td>'
//                     +'<td>'+(row.totais.he100APagar?? '')+'</td>'
//                     +'<td>'+(row.totais.adicionalNoturno?? '')+'</td>'
//                     +'<td>'+(row.totais.esperaIndenizada?? '')+'</td>'
//                     +'<td>'+(row.totais.saldoAnterior?? '')+'</td>'
//                     +'<td>'+(row.totais.saldoPeriodo?? '')+'</td>'
//                     +'<td>'+(row.totais.saldoFinal?? '')+'</td>'
//                 +'</tr>';";
//     }

//     $carregarDados = "";
//     foreach ($arquivos as $arquivo) {
//         $carregarDados .= "carregarDados('" . $arquivo . "');";
//     }

//     echo
//     "<form name='myForm' method='post' action='" . htmlspecialchars($_SERVER["PHP_SELF"]) . "'>
//                 <input type='hidden' name='atualizar' id='atualizar'>
//                 <input type='hidden' name='empresa' id='empresa'>
//                 <input type='hidden' name='busca_data' id='busca_data'>
//             </form>
//             <script>
//                 function setAndSubmit(empresa){
//                     document.myForm.empresa.value = empresa;
//                     document.myForm.busca_data.value = document.getElementById('busca_data').value;
//                     document.myForm.submit();
//                 }

//                 function atualizarPainel(){
//                     document.myForm.empresa.value = document.getElementById('empresa').value;
//                     document.myForm.busca_data.value = document.getElementById('busca_data').value;
//                     document.myForm.atualizar.value = 'atualizar';
//                     document.myForm.submit();
//                 }

//                 function imprimir(){
//                     window.print();
//                 }
            
//                 $(document).ready(function(){
//                     var tabela = $('#tabela-empresas tbody');

//                     function carregarDados(urlArquivo){
//                         $.ajax({
//                             url: urlArquivo,
//                             dataType: 'json',
//                             success: function(data){
//                                 var row = {};
//                                 $.each(data, function(index, item){
//                                     row[index] = item;
//                                 });
//                                 if(row.idMotorista != undefined){
//                                     // Mostrar painel dos motoristas
//                                     delete row.idMotorista;

//                                     if(row.statusEndosso != 'E'){
//                                         row = {
//                                             'matricula': row.matricula,
//                                             'nome': row.nome,
//                                             'statusEndosso': row.statusEndosso,
//                                             'saldoAnterior': row.saldoAnterior
//                                         };
//                                     }
//                                 }else{
//                                     // Mostrar painel geral das empresas.
                                
//                                     if(row.percEndossado < 1){
//                                         row.totais = {
//                                             'saldoAnterior': row.totais.saldoAnterior
//                                         };
//                                     }
//                                 }
//                                 "
//         . $linha
//         . "tabela.append(linha);
//                             },
//                             error: function(){
//                                 console.log('Erro ao carregar os dados.');
//                             }
//                         });
//                     }
//                     // Função para ordenar a tabela
//                     function ordenarTabela(coluna, ordem){
//                         var linhas = tabela.find('tr').get();
//                         linhas.sort(function(a, b){
//                             var valorA = $(a).children('td').eq(coluna).text().toUpperCase();
//                             var valorB = $(b).children('td').eq(coluna).text().toUpperCase();

//                             if(valorA < valorB){
//                                 return ordem === 'asc' ? -1 : 1;
//                             }
//                             if(valorA > valorB){
//                                 return ordem === 'asc' ? 1 : -1;
//                             }
//                             return 0;
//                         });
//                         $.each(linhas, function(index, row){
//                             tabela.append(row);
//                         });
//                     }

//                     // Evento de clique para ordenar a tabela ao clicar no cabeçalho
//                     $('#titulos th').click(function(){
//                         var coluna = $(this).index();
//                         var ordem = $(this).data('order');
//                         $('#tabela-empresas th').data('order', 'desc'); // Redefinir ordem de todas as colunas
//                         $(this).data('order', ordem === 'desc' ? 'asc' : 'desc');
//                         ordenarTabela(coluna, $(this).data('order'));

//                         // Ajustar classes para setas de ordenação
//                         $('#titulos th').removeClass('sort-asc sort-desc');
//                         $(this).addClass($(this).data('order') === 'asc' ? 'sort-asc' : 'sort-desc');
//                     });

//                     " . $carregarDados . "
//                 });
//             </script>";
// }


// function index() {
//     if(!empty($_POST["atualizar"])){
//         echo "<script>alert('Atualizando os painéis, aguarde um pouco.')</script>";
//         ob_flush();
//         flush();
//         require_once "funcoes_paineis.php";
//         criar_relatorio_saldo();
//     }

//     cabecalho("Relatorio Geral de saldo");

//     $extraCampoData = "";
//     if(empty($_POST["busca_dataInicio"])){
//         $_POST["busca_dataInicio"] = date("Y-m-01");
//     }
//     if(empty($_POST["busca_dataFim"])){
//         $_POST["busca_dataFim"] = date("Y-m-d");
//     }

//     if($_POST["busca_dataInicio"] > date("Y-m-d") || $_POST["busca_dataFim"] > date("Y-m-d")){
//         unset($_POST["acao"]);
//         set_status("ERRO: Não é possível perquisar após a data atual.");
//     }

//     // $texto = "<div style=''><b>Periodo da Busca:</b> $monthName de $year</div>";
//     //position: absolute; top: 101px; left: 420px;
//     $fields = [
//         combo_net("Empresa:", "empresa", $_POST["empresa"] ?? "", 4, "empresa", ""),
//         campo_data("Data Início", "busca_dataInicio", ($_POST["busca_dataInicio"] ?? ""), 2, $extraCampoData),
//         campo_data("Data Fim", "busca_dataFim", ($_POST["busca_dataFim"] ?? ""), 2, $extraCampoData)
//         // $texto,
//     ];
//     $botao_volta = "";
//     if(!empty($_POST["empresa"])){
//         $botao_volta = "<button class='btn default' type='button' onclick='setAndSubmit(\"\")'>Voltar</button>";
//     }
//     $botao_imprimir = "<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>";
//     if(!empty($_SESSION["user_tx_nivel"]) && is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"))){
//         $botaoAtualizarPainel = "<a class='btn btn-warning' onclick='atualizarPainel()'> Atualizar Painel</a>";
//     }
//     $buttons = [
//         botao("Buscar", "buscarRelatorio()", "", "", "", "", "btn btn-info"),
//         $botao_imprimir,
//         $botao_volta,
//         $botaoAtualizarPainel
//     ];


//     abre_form("Filtro de Busca");
//     linha_form($fields);
//     fecha_form($buttons);

    

// }