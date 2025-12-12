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
                    +'<td>'+(row.tipoOperacaoNome?? '')+'</td>'
                    +'<td>'+(row.setorNome?? '')+'</td>'
                    +'<td>'+(row.subsetorNome?? '')+'</td>'
                    +'<td class=\"'+(row.statusEndosso === 'E'? 'endo': (row.statusEndosso === 'EP'? 'endo-parc': 'nao-endo'))+'\">'
                        +'<strong>'+row.statusEndosso+'</strong>'
                    +'</td>'
                    +'<td>'+(invalidValues.includes(row.jornadaPrevista)? '': row.jornadaPrevista?? '')+'</td>'
                    +'<td>'+(invalidValues.includes(row.jornadaEfetiva)? '': row.jornadaEfetiva?? '')+'</td>'
                    +'<td>'+(invalidValues.includes(row.he50APagar)? '': row.he50APagar?? '')+'</td>'
                    +'<td>'+(invalidValues.includes(row.he100APagar)? '': row.he100APagar?? '')+'</td>'
                    +'<td>'+(invalidValues.includes(row.adicionalNoturno)? '': row.adicionalNoturno?? '')+'</td>'
                    +'<td>'+(invalidValues.includes(row.esperaIndenizada)? '': row.esperaIndenizada?? '')+'</td>'
                    +'<td id=\"'+(row.saldoAnterior > '00:00'? 'saldo-final': (row.saldoAnterior === '00:00'? 'saldo-zero': 'saldo-negativo'))+'\">'
                    +(row.saldoAnterior?? '')+'</td>'
                    +'<td>'+(row.saldoPeriodo > '00:00'? '<strong>' + row.saldoPeriodo + '</strong>': (row.saldoPeriodo?? ''))+'</td>'
                   +'<td id=\"'+(row.saldoFinal > '00:00'? 'saldo-final': (row.saldoFinal === '00:00'? 'saldo-zero': 'saldo-negativo'))+'\";\">'
                    +(row.saldoFinal?? '')+indicador+'</td>'
                +'</tr>';";
        }else{
            $linha .= "+'<td class=\"nomeEmpresa\" style=\"cursor: pointer;\" onclick=\"setAndSubmit(' + row.empr_nb_id + ')\">'+row.empr_tx_nome+'</td>'
                    +'<td>'+Math.round(row.percEndossado*10000)/100+'%</td>'
                    +'<td>'+row.qtdMotoristas+'</td>'
                    +'<td>'+(invalidValues.includes(row.totais.jornadaPrevista)? '': row.totais.jornadaPrevista)+'</td>'
                    +'<td>'+(invalidValues.includes(row.totais.jornadaEfetiva)? '': row.totais.jornadaEfetiva)+'</td>'
                    +'<td>'+(invalidValues.includes(row.totais.he50APagar)? '': row.totais.he50APagar)+'</td>'
                    +'<td>'+(invalidValues.includes(row.totais.he100APagar)? '': row.totais.he100APagar)+'</td>'
                    +'<td>'+(invalidValues.includes(row.totais.adicionalNoturno)? '': row.totais.adicionalNoturno)+'</td>'
                    +'<td>'+(invalidValues.includes(row.totais.esperaIndenizada)? '': row.totais.esperaIndenizada)+'</td>'
                    +'<td id=\"'+(row.totais.saldoAnterior > '00:00'? 'saldo-final': (row.totais.saldoAnterior === '00:00'? 'saldo-zero': 'saldo-negativo'))+'\" >'
                    +(row.totais.saldoAnterior == '00:00'? '': row.totais.saldoAnterior)+'</td>'
                    +'<td>'+(row.totais.saldoPeriodo > '00:00'? '<strong>' + row.totais.saldoPeriodo + '</strong>': (row.totais.saldoPeriodo?? ''))+'</td>'
                    +'<td id=\"'+(row.totais.saldoFinal > '00:00'? 'saldo-final': (row.totais.saldoFinal === '00:00'? 'saldo-zero': 'saldo-negativo'))+'\">'
                    +(row.totais.saldoFinal?? '')+indicador+'</td>'
                +'</tr>';";
        }

        $carregarDados = "";
        foreach($arquivos as $arquivo){
            $carregarDados .= "carregarDados('".$arquivo."');";
        }

        echo
        "

            <form name='myForm' method='post' action='".htmlspecialchars($_SERVER["PHP_SELF"])."'>
                <input type='hidden' name='acao'>
                <input type='hidden' name='atualizar'>
                <input type='hidden' name='campoAcao'>
                <input type='hidden' name='empresa'>
                <input type='hidden' name='busca_ocupacao'>
                <input type='hidden' name='busca_dataFim'>
                <input type='hidden' name='busca_data'>
            </form>
            <script>
                function setAndSubmit(empresa){
                    document.myForm.acao.value = 'enviarForm()';
                    document.myForm.campoAcao.value = 'buscar';
                    document.myForm.empresa.value = empresa;
                    document.myForm.busca_ocupacao.value = document.querySelector('[name=\"busca_ocupacao\"]').value;
                    document.myForm.busca_data.value = document.getElementById('busca_data').value;
                    document.myForm.submit();
                }

                function atualizarPainel(){
                    document.myForm.empresa.value = document.getElementById('empresa').value;
                    document.myForm.busca_data.value = document.getElementById('busca_data').value;
                    document.myForm.atualizar.value = 'atualizar';
                    document.myForm.busca_ocupacao.value = document.querySelector('[name=\"busca_ocupacao\"]').value;
                    document.myForm.submit();
                }

                function imprimir(){
                    window.print();
                }
            
                $(document).ready(function(){
                    var tabela = $('#tabela-empresas tbody');
                    var ocupacoesPermitidas = '".$_POST["busca_ocupacao"]."';
                    var operacaoPermitidas = '".$_POST["operacao"]."';
                    var setorPermitidas = '".$_POST["busca_setor"]."';
				    var subSetorPermitidas = '".$_POST["busca_subsetor"]."';

                    function carregarDados(urlArquivo){
                        $.ajax({
                            url: urlArquivo + '?v=' + new Date().getTime(),
                            dataType: 'json',
                            success: function(data){
                                var row = {};
                                $.each(data, function(index, item){
                                    row[index] = item;
                                });

                                if (
                                    (ocupacoesPermitidas.length > 0 && !ocupacoesPermitidas.includes(row.ocupacao)) ||
                                    (operacaoPermitidas.length > 0 && !operacaoPermitidas.includes(row.tipoOperacao)) ||
                                    (setorPermitidas.length > 0 && !setorPermitidas.includes(row.setor)) ||
                                    (subSetorPermitidas.length > 0 && !subSetorPermitidas.includes(row.subsetor))
                                ) {
                                    return; // pula esta linha se qualquer filtro não permitir
                                }

                                var saldoAnterior = horasParaMinutos(row.saldoAnterior !== undefined? row.saldoAnterior: row.totais.saldoAnterior);
                                var saldoFinal = horasParaMinutos(row.saldoFinal !== undefined? row.saldoFinal: row.totais.saldoFinal);
                                var indicador = '';
                                if (saldoAnterior >= 0 && saldoFinal >= 0) {
                                    // Ambos os saldos são positivos
                                    indicador = definirIndicador(saldoAnterior, saldoFinal);
                                } else if (saldoAnterior >= 0 && saldoFinal <= 0) {
                                    // Saldo anterior positivo e saldo final negativo
                                    indicador = definirIndicador(saldoAnterior, saldoFinal);
                                } else if (saldoAnterior <= 0 && saldoFinal >= 0) {
                                    // Saldo anterior negativo e saldo final positivo
                                    indicador = definirIndicador(saldoAnterior, saldoFinal);
                                } else if (saldoAnterior <= 0 && saldoFinal <= 0) {
                                    // Ambos os saldos são negativos
                                    indicador = definirIndicador(saldoAnterior, saldoFinal);
                                } else {
                                    // Caso em que saldoAnterior é zero e saldoFinal é zero
                                    indicador = ' <i class=\"fa fa-minus\" style=\"color: gray;\"></i>';
                                }
                                if(row.idMotorista != undefined){
                                    // Mostrar painel dos motoristas
                                    delete row.idMotorista;

                                    if(row.statusEndosso != 'E'){
                                        row = {
                                            'matricula': row.matricula,
                                            'nome': row.nome,
                                            'ocupacao': row.ocupacao,
                                            'tipoOperacaoNome': row.tipoOperacaoNome,
                                            'setorNome': row.setorNome,
                                            'subsetorNome': row.subsetorNome,
                                            'statusEndosso': row.statusEndosso,
                                            'saldoAnterior': row.saldoAnterior
                                        };
                                    }
                                }else{
                                    // Mostrar painel geral das empresas.
                                
                                    if(Math.round(row.percEndossado*10000)/100 < 1){
                                        row.totais = {
                                            'saldoAnterior': row.totais.saldoAnterior
                                        };
                                    }
                                }
                                invalidValues = [undefined, '00:00'];
                                {$linha}
                                tabela.append(linha);
                            },
                            error: function(){
                                console.log('Erro ao carregar os dados.');
                            }
                        });
                    }

                    function definirIndicador(minutosColuna1, minutosColuna3) {
                        if (minutosColuna1 < minutosColuna3) {
                            // Se o valor de minutos da coluna 3 for maior (menos negativo ou positivo), é uma melhora.
                            return ' <i class=\"fa fa-chevron-up\" style=\"color: green;\"></i>';
                        } else if (minutosColuna1 > minutosColuna3) {
                            // Se o valor de minutos da coluna 1 for maior (mais longe de zero), é pior.
                            return ' <i class=\"fa fa-chevron-down\" style=\"color: red;\"></i>';
                        } else {
                            // Sem alteração, neutro.
                            return ' <i class=\"fa fa-minus\" style=\"color: gray;\"></i>';
                        }
                    }

                    // Função para conversão de Horas para Minutos
                    function horasParaMinutos(horas) {
                        var partes = horas.split(':');
                        var horasNumeros = parseInt(partes[0], 10);  // Horas (pode ser positivo ou negativo)
                        var minutos = parseInt(partes[1], 10);       // Minutos

                        // Converte as horas para minutos totais
                        return (horasNumeros * 60) + (horasNumeros < 0? -minutos: minutos);
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
                                return ordem === 'asc'? -1: 1;
                            }
                            if(valorA > valorB){
                                return ordem === 'asc'? 1: -1;
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
                        $(this).data('order', ordem === 'desc'? 'asc': 'desc');
                        ordenarTabela(coluna, $(this).data('order'));

                        // Ajustar classes para setas de ordenação
                        $('#titulos th').removeClass('sort-asc sort-desc');
                        $(this).addClass($(this).data('order') === 'asc'? 'sort-asc': 'sort-desc');
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
            </script>"
        ;
    }

    function index(){
        include __DIR__.'/../check_permission.php';
        verificaPermissao('/paineis/endosso.php');
        require_once __DIR__."/funcoes_paineis.php";
        // $_POST['busca_ocupacao'] = 'foi';
        
        if(!empty($_POST["atualizar"])){
            echo "<script>alert('Atualizando os painéis, aguarde um pouco.')</script>";
            ob_flush();
            flush();
            cabecalho("Relatório de Endossos");
            criar_relatorio_endosso();
        }else{
            cabecalho("Relatório de Endossos");
        }

        $extraCampoData = "";
        if(empty($_POST["busca_data"])){
            $_POST["busca_data"] = date("Y-m");
        }

        // $texto = "<div style=''><b>Periodo da Busca:</b> $monthName de $year</div>";
        //position: absolute; top: 101px; left: 420px;
        $temSubsetorVinculado = false;
        if (!empty($_POST["busca_setor"])) {
            $rowCount = mysqli_fetch_array(query("SELECT COUNT(*) FROM sbgrupos_documentos WHERE sbgr_tx_status = 'ativo' AND sbgr_nb_idgrup = ".intval($_POST["busca_setor"]).";"));
            $temSubsetorVinculado = ($rowCount[0] > 0);
        }

        $fields = [
            combo_net("Empresa:", "empresa", $_POST["empresa"]?? "", 4, "empresa", ""),
            combo("Ocupação", "busca_ocupacao", ($_POST["busca_ocupacao"] ?? ""), 2, 
            ["" => "Todos", "Motorista" => "Motorista", "Ajudante" => "Ajudante", "Funcionário" => "Funcionário"]),
            combo_bd("!Cargo", "operacao", ($_POST["operacao"]?? ""), 2, "operacao", "", "ORDER BY oper_tx_nome ASC"),
            combo_bd("!Setor", 		"busca_setor", 	($_POST["busca_setor"]?? ""), 	2, "grupos_documentos", "onchange=\"(function(f){ if(f.busca_subsetor){ f.busca_subsetor.value=''; } f.reloadOnly.value='1'; f.submit(); })(document.contex_form);\""),
            campo_mes("Data", "busca_data", ($_POST["busca_data"]?? ""), 2, $extraCampoData),
        ];
        if ($temSubsetorVinculado) {
            $fields[] = combo_bd("!Subsetor", 	"busca_subsetor", 	($_POST["busca_subsetor"]?? ""), 	2, "sbgrupos_documentos", "", " AND sbgr_nb_idgrup = ".intval($_POST["busca_setor"])." ORDER BY sbgr_tx_nome ASC");
        }
        
        $botao_volta = "";
        if(!empty($_POST["empresa"])){
            $botao_volta = "<button class='btn default' type='button' onclick='setAndSubmit(\"\")'>Voltar</button>";
        }
        $botao_imprimir = "<button class='btn default' type='button' onclick='enviarDados()'>Imprimir</button>
        <script>
        function enviarDados() {
            var data = '" . $_POST["busca_data"] . "';
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'export_paineis.php';
            form.target = '_blank';

            // Adiciona campos básicos
            ['empresa', 'busca_data', 'relatorio'].forEach(function(name) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = name === 'empresa' ? (" . (!empty($_POST['empresa']) ? $_POST['empresa'] : 'null') . ") : 
                            (name === 'busca_data' ? data : 'endosso');
                form.appendChild(input);
            });

            // Processamento otimizado da tabela
            var tabelaOriginal = document.querySelector('#tabela-empresas');
            if (tabelaOriginal) {
                // Cria uma cópia limpa da tabela
                var tabelaClone = tabelaOriginal.cloneNode(true);
                
                // Remove elementos problemáticos (mantém as classes de status)
                tabelaClone.querySelectorAll('i.fa, script, style, link').forEach(el => el.remove());
                
                // Cores fixas para substituir as variáveis CSS
                var coresStatus = {
                    'endo': '#4ea9ff',       // azul claro (substitui var(--var-blue))
                    'endo-parc': '#ffe80063',  // amarelo claro (substitui var(--var-darkyellow))
                    'nao-endo': '#ec4141'    // vermelho claro (substitui var(--var-red))
                };
                
                // Cria um novo HTML simplificado
                var htmlSimplificado = '<table style=\"width:100%;border-collapse:collapse;font-family:helvetica;font-size:7pt\">';
                
                // Processa o cabeçalho - REMOVENDO a linha 'totais'
                var thead = tabelaClone.querySelector('thead');
                if (thead) {
                    htmlSimplificado += '<thead>';
                    thead.querySelectorAll('tr').forEach(tr => {
                        if (tr.classList.contains('totais')) {
                            return;
                        }
                        
                        htmlSimplificado += '<tr>';
                        tr.querySelectorAll('th').forEach(th => {
                            htmlSimplificado += '<th style=\"border:0.5px solid #000;padding:2px;text-align:center;font-weight:bold;background-color:#4ea9ff\">';
                            htmlSimplificado += th.innerHTML;
                            htmlSimplificado += '</th>';
                        });
                        htmlSimplificado += '</tr>';
                    });
                    htmlSimplificado += '</thead>';
                }
                
                // Processa o corpo com estilos condicionais
                var tbody = tabelaClone.querySelector('tbody');
                if (tbody) {
                    htmlSimplificado += '<tbody>';
                    tbody.querySelectorAll('tr').forEach(tr => {
                        htmlSimplificado += '<tr>';
                        tr.querySelectorAll('td').forEach((td, colIndex) => {
                            // Estilo base para todas as células
                            var estiloBase = 'border:0.5px solid #000;padding:2px;font-size:7pt;';
                            
                            // Estilo especial para coluna de nomes (segunda coluna)
                            if (colIndex === 1) {
                                estiloBase += 'text-align:left;white-space:nowrap;overflow:hidden;max-width:90px;';
                            } else {
                                estiloBase += 'text-align:center;';
                            }
                            
                            // Estilo condicional para coluna de status (assumindo que é a 4ª coluna - índice 3)
                            if (colIndex === 3) {
                                var statusClass = '';
                                if (td.classList.contains('endo')) {
                                    estiloBase += 'background-color:' + coresStatus['endo'] + ';';
                                } else if (td.classList.contains('endo-parc')) {
                                    estiloBase += 'background-color:' + coresStatus['endo-parc'] + ';';
                                } else if (td.classList.contains('nao-endo')) {
                                    estiloBase += 'background-color:' + coresStatus['nao-endo'] + ';';
                                }
                            }

                            if (colIndex === 10) {
                                // console.log(td.id);
                                if (td.id === 'saldo-zero') {
                                    estiloBase += 'color:blue;';
                                } else if (td.id === 'saldo-final') {
                                    estiloBase += 'color:green;';
                                } else if (td.id === 'saldo-negativo') {
                                    estiloBase += 'color:red;';
                                }
                            }

                            if (colIndex === 12) {
                                // console.log(td.id);
                                if (td.id === 'saldo-zero') {
                                    estiloBase += 'color:blue;';
                                } else if (td.id === 'saldo-final') {
                                    estiloBase += 'color:green;';
                                } else if (td.id === 'saldo-negativo') {
                                    estiloBase += 'color:red;';
                                }
                            }
                            
                            htmlSimplificado += '<td style=\"' + estiloBase + '\">';
                            htmlSimplificado += td.innerHTML;
                            htmlSimplificado += '</td>';
                        });
                        htmlSimplificado += '</tr>';
                    });
                    htmlSimplificado += '</tbody>';
                }
                
                htmlSimplificado += '</table>';
                
                // Adiciona ao formulário
                var inputTabela = document.createElement('input');
                inputTabela.type = 'hidden';
                inputTabela.name = 'htmlTabela';
                inputTabela.value = htmlSimplificado;
                form.appendChild(inputTabela);
            }

            // Envia o formulário
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        </script>";
        if(!empty($_SESSION["user_tx_nivel"]) && is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"))){
            $botaoAtualizarPainel = "<a class='btn btn-warning' onclick='atualizarPainel()'> Atualizar Painel</a>";
        }
        $buttons = [
            botao("Buscar", "index", "", "", "", "", "btn btn-info"),
            $botao_imprimir,
            $botao_volta,
            $botaoAtualizarPainel
        ];


        echo abre_form();
        echo campo_hidden("reloadOnly", "");
        echo linha_form($fields);
        echo fecha_form($buttons);

        
        $arquivos = [];
        $dataEmissao = ""; //Utilizado no HTML
        $encontrado = true;
        $path = "./arquivos/endossos";
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
            "jornadaPrevista" => "00:00",
            "jornadaEfetiva" => "00:00",
            "he50APagar" => "00:00",
            "he100APagar" => "00:00",
            "adicionalNoturno" => "00:00",
            "esperaIndenizada" => "00:00",
            "saldoAnterior" => "00:00",
            "saldoPeriodo" => "00:00",
            "saldoFinal" => "00:00"
        ];

        $periodoRelatorio = [
            "dataInicio" => "1900-01-01",
            "dataFim" => "1900-01-01"
        ];

        if(!empty($_POST["empresa"]) && !empty($_POST["busca_data"]) && empty($_POST["reloadOnly"])){
            //Painel dos endossos dos motoristas de uma empresa específica
            $empresa = mysqli_fetch_assoc(query(
                "SELECT * FROM empresa
                WHERE empr_tx_status = 'ativo'
                    AND empr_nb_id = {$_POST["empresa"]}
                LIMIT 1;"
            ));
            
            
            $path .= "/".$_POST["busca_data"]."/".$empresa["empr_nb_id"];

            if(is_dir($path)){
                $pastaSaldosEmpresa = dir($path);
                $motoristas = mysqli_fetch_all(query(
                    "SELECT enti_tx_matricula, enti_tx_desligamento, enti_tx_admissao FROM entidade
                        WHERE enti_tx_status != 'ativo'
                            AND enti_nb_empresa = {$empresa["empr_nb_id"]}
                            AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')
                        ORDER BY enti_tx_nome ASC;"
                ), MYSQLI_ASSOC);

                $dataBusca = new DateTime($_POST["busca_data"]);
                foreach($motoristas as $motorista){
                    if (!empty($motorista["enti_tx_desligamento"])) {
                        $dataMotorista = new DateTime($motorista["enti_tx_desligamento"]);
                        $dataMotorista = $dataMotorista->format("Y-m");
                        if ($dataBusca > $dataMotorista) {
                            $matriculasInativas = array_map(function($matricula) {
                                return $matricula . ".json";
                            }, array_column($motoristas, "enti_nb_id"));
                            $matriculasInativas = array_map(function($matricula) {
                                return $matricula . ".json";
                            }, array_column($motoristas, "enti_nb_id"));
                        }
                    } else {
                        $dataMotorista = new DateTime($motorista["enti_tx_admissao"]);
                        $dataMotorista = $dataMotorista->format("Y-m");
                        if ($dataBusca < $dataMotorista) {
                            $matriculasInativas = array_map(function($matricula) {
                                return $matricula . ".json";
                            }, array_column($motoristas, "enti_nb_id"));
                            $matriculasInativas = array_map(function($matricula) {
                                return $matricula . ".json";
                            }, array_column($motoristas, "enti_nb_id"));
                        }
                    }
                }

                while($arquivo = $pastaSaldosEmpresa->read()){
                    if(!in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresa_"))){
                        $arquivos[] = $arquivo;

                        if (!empty($matriculasInativas) && in_array($arquivo, $matriculasInativas)) {
                            $arquivos = array_diff($arquivos, [$arquivo]);
                            // unlink($path."/". $arquivo);
                        }

                    }
                }

                $pastaSaldosEmpresa->close();

                $dataArquivo = date("d/m/Y", filemtime($path . "/empresa_" . $empresa["empr_nb_id"] . ".json"));
                $horaArquivo = date("H:i", filemtime($path . "/empresa_" . $empresa["empr_nb_id"] . ".json"));

                $dataAtual = date("d/m/Y");
                $horaAtual = date("H:i");
                if($dataArquivo != $dataAtual){
                    $alertaEmissao = "<span style='color: red; border: 2px solid; padding: 2px; border-radius: 4px;'>
                    <i style='color:red; margin-right: 5px;' title='As informações do painel não correspondem à data de hoje.' class='fa fa-warning'></i>";
                } else {
                    $alertaEmissao = "<span>";
                    $alertaEmissao = "<span>";
                }

                $dataEmissao = $alertaEmissao." Atualizado em: ".date("d/m/Y H:i", filemtime($path."/empresa_".$empresa["empr_nb_id"].".json")). "</span>"; //Utilizado no HTML.
                $periodoRelatorio = json_decode(file_get_contents($path."/empresa_".$empresa["empr_nb_id"].".json"), true);
                $periodoRelatorio = [
                    "dataInicio" => $periodoRelatorio["dataInicio"],
                    "dataFim" => $periodoRelatorio["dataFim"]
                ];

                $motoristas = [];
                foreach($arquivos as $arquivo){
                    $json = json_decode(file_get_contents($path."/".$arquivo), true);
                    $json["dataAtualizacao"] = date("d/m/Y H:i", filemtime($path."/".$arquivo));
                    $motoristas[] = $json;
                }
                foreach($arquivos as &$arquivo){
                    $arquivo = $path."/".$arquivo;
                }
                $totais["empresaNome"] = $empresa["empr_tx_nome"];

                $caminho = $path . '/empresa_'.$empresa["empr_nb_id"].'.json';

                if (file_exists($caminho)) {
                    $TotaisJson = json_decode(file_get_contents($caminho), true);
                    // agora $conteudo tem os dados do arquivo
                }

                // Aplica filtros aos motoristas para refletir na contagem e nos totais
                $motoristasFiltrados = array_filter($motoristas, function($m){
                    if (!empty($_POST["busca_ocupacao"]) && $m["ocupacao"] !== $_POST["busca_ocupacao"]) return false;
                    if (!empty($_POST["operacao"]) && $m["tipoOperacao"] != $_POST["operacao"]) return false;
                    if (!empty($_POST["busca_setor"]) && $m["setor"] != $_POST["busca_setor"]) return false;
                    if (!empty($_POST["busca_subsetor"]) && $m["subsetor"] != $_POST["busca_subsetor"]) return false;
                    return true;
                });

                // Recalcula os totais com base no filtro aplicado
                $totais = [
                    "jornadaPrevista" => "00:00",
                    "jornadaEfetiva" => "00:00",
                    "he50APagar" => "00:00",
                    "he100APagar" => "00:00",
                    "adicionalNoturno" => "00:00",
                    "esperaIndenizada" => "00:00",
                    "saldoAnterior" => "00:00",
                    "saldoPeriodo" => "00:00",
                    "saldoFinal" => "00:00"
                ];

                foreach ($motoristasFiltrados as $m) {
                    $totais["jornadaPrevista"]  = operarHorarios([$totais["jornadaPrevista"],  (!empty($m["jornadaPrevista"])  ? $m["jornadaPrevista"]  : "00:00")], "+");
                    $totais["jornadaEfetiva"]   = operarHorarios([$totais["jornadaEfetiva"],   (!empty($m["jornadaEfetiva"])   ? $m["jornadaEfetiva"]   : "00:00")], "+");
                    $totais["he50APagar"]       = operarHorarios([$totais["he50APagar"],       (!empty($m["he50APagar"])       ? $m["he50APagar"]       : "00:00")], "+");
                    $totais["he100APagar"]      = operarHorarios([$totais["he100APagar"],      (!empty($m["he100APagar"])      ? $m["he100APagar"]      : "00:00")], "+");
                    $totais["adicionalNoturno"] = operarHorarios([$totais["adicionalNoturno"], (!empty($m["adicionalNoturno"]) ? $m["adicionalNoturno"] : "00:00")], "+");
                    $totais["esperaIndenizada"] = operarHorarios([$totais["esperaIndenizada"], (!empty($m["esperaIndenizada"]) ? $m["esperaIndenizada"] : "00:00")], "+");
                    $totais["saldoAnterior"]    = operarHorarios([$totais["saldoAnterior"],    (!empty($m["saldoAnterior"])    ? $m["saldoAnterior"]    : "00:00")], "+");
                    $totais["saldoPeriodo"]     = operarHorarios([$totais["saldoPeriodo"],     (!empty($m["saldoPeriodo"])     ? $m["saldoPeriodo"]     : "00:00")], "+");
                    $totais["saldoFinal"]       = operarHorarios([$totais["saldoFinal"],       (!empty($m["saldoFinal"])       ? $m["saldoFinal"]       : "00:00")], "+");
                }

                foreach($motoristasFiltrados as $saldosMotorista){
                    $contagemEndossos[$saldosMotorista["statusEndosso"]]++;
                    if($saldosMotorista["statusEndosso"] == "E"){
                        if($saldosMotorista["saldoFinal"] === "00:00"){
                            $contagemSaldos["meta"]++;
                        }elseif(!empty($saldosMotorista["saldoFinal"]) && $saldosMotorista["saldoFinal"][0] == "-"){
                            $contagemSaldos["negativos"]++;
                        }else{
                            $contagemSaldos["positivos"]++;
                        }
                    }
                }
            }else{
                $encontrado = false;
            }
        }elseif(!empty($_POST["busca_data"]) && empty($_POST["reloadOnly"])){
            //Painel geral das empresas
            $empresas = [];
            $logoEmpresa = mysqli_fetch_assoc(query(
                "SELECT empr_tx_logo FROM empresa
                WHERE empr_tx_status = 'ativo'
                    AND empr_tx_Ehmatriz = 'sim'
                LIMIT 1;"
            ))["empr_tx_logo"];//Utilizado no HTML.

            $logoEmpresa = $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/".$logoEmpresa;


            $path .= "/".$_POST["busca_data"];

            
            if(is_dir($path) && file_exists($path."/empresas.json")){
                $dataArquivo = date("d/m/Y H:i", filemtime($path . "/empresas.json"));
                $horaArquivo = date("H:i", filemtime($path . "/empresas.json"));

                $dataAtual = date("d/m/Y");
                $horaAtual = date("H:i");
                if($dataArquivo != $dataAtual){
                    $alertaEmissao = "<span style='color: red; border: 2px solid; padding: 2px; border-radius: 4px;'>
                    <i style='color:red;' title='As informações do painel não correspondem à data de hoje.' class='fa fa-warning'></i>";
                } else {
                    $alertaEmissao = "<span>";
                    $alertaEmissao = "<span>";
                }
                $dataEmissao = $alertaEmissao." Atualizado em: ".date("d/m/Y H:i", filemtime($path."/empresas.json"))."</span>"; //Utilizado no HTML.
                $arquivoGeral = json_decode(file_get_contents($path."/empresas.json"), true);

                $periodoRelatorio = [
                    "dataInicio" => $arquivoGeral["dataInicio"],
                    "dataFim" => $arquivoGeral["dataFim"]
                ];

                $pastaEndossos = dir($path);
                while($arquivo = $pastaEndossos->read()){
                    if(!empty($arquivo) && !in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresas"))){
                        $arquivo = $path."/".$arquivo."/empresa_".$arquivo.".json";
                        $arquivos[] = $arquivo;
                        $json = json_decode(file_get_contents($arquivo), true);
                        foreach($totais as $key => $value){
                            $totais[$key] = operarHorarios([
                                !empty($totais[$key]) ? $totais[$key] : "00:00",
                                !empty($json["totais"][$key]) ? $json["totais"][$key] : "00:00"
                            ], "+");
                            $totais[$key] = operarHorarios([
                                !empty($totais[$key]) ? $totais[$key] : "00:00",
                                !empty($json["totais"][$key]) ? $json["totais"][$key] : "00:00"
                            ], "+");
                        }
                        $empresas[] = $json;

                    }
                }
                $pastaEndossos->close();
                
                foreach($empresas as $empresa){
                    if($empresa["percEndossado"] < 1){
                        $empresa["totais"] = [
                            "saldoAnterior" => $empresa["totais"]["saldoAnterior"]
                        ];
                        if($empresa["percEndossado"] <= 0){
                            $contagemEndossos["N"]++;
                        }else{
                            $contagemEndossos["EP"]++;
                        }
                    }else{
                        $contagemEndossos["E"]++;
                        
                        if($empresa["totais"]["saldoFinal"] === "00:00"){
                            $contagemSaldos["meta"]++;
                        }elseif($empresa["totais"]["saldoFinal"][0] == "-"){
                            $contagemSaldos["negativos"]++;
                        }else{
                            $contagemSaldos["positivos"]++;
                        }
                    }
                }
            }else{
                $encontrado = false;
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
                $rowTotais .= <<<EOD
                    <th colspan='2'>{$totais["empresaNome"]}</th>
                    <th colspan='1'></th>
                    <th colspan='1'></th>
                    <th colspan='1'></th>
                    <th colspan='1'></th>
                    <th colspan='1'></th>
                    <th colspan='1'>{$totais["jornadaPrevista"]}</th>
                    <th colspan='1'>{$totais["jornadaEfetiva"]}</th>
                    <th colspan='1'>{$totais["he50APagar"]}</th>
                    <th colspan='1'>{$totais["he100APagar"]}</th>
                    <th colspan='1'>{$totais["adicionalNoturno"]}</th>
                    <th colspan='1'>{$totais["esperaIndenizada"]}</th>
                    <th colspan='1'>{$totais["saldoAnterior"]}</th>
                    <th colspan='1'>{$totais["saldoPeriodo"]}</th>
                    <th colspan='1'>{$totais["saldoFinal"]}</th>
                EOD;

                $rowTitulos .= <<<EOD
                    <th class='matricula'>Matrícula</th>
                    <th class='nome'>Nome</th>
                    <th class='ocupacao'>Ocupação</th>
                    <th class='operacao'>Cargo</th>
                    <th class='setor'>Setor</th>
                    <th class='subsetor'>SubSetor</th>
                    <th class='status'>Status Endosso</th>
                    <th class='jornadaPrevista'>Jornada Prevista</th>
                    <th class='jornadaEfetiva'>Jornada Efetiva</th>
                    <th class='he50APagar'>H.E. Semanal Pago</th>
                    <th class='he100APagar'>H.E. Ex. Pago</th>
                    <th class='adicionalNoturno'>Adicional Noturno</th>
                    <th class='esperaIndenizada'>Espera Indenizada</th>
                    <th class='saldoAnterior'>Saldo Anterior</th>
                    <th class='saldoPeriodo'>Saldo Período</th>
                    <th class='saldoFinal'>Saldo Final</th>
                    EOD;

                // $rowTotais .= <<<EOD
                //     <th colspan='2'>{$totais["empresaNome"]}</th>
                //     <th colspan='1'></th>
                //     <th colspan='1'></th>
                //     <th colspan='1'>{$TotaisJson["totais"]["jornadaPrevista"]}</th>
                //     <th colspan='1'>{$TotaisJson["totais"]["jornadaEfetiva"]}</th>
                //     <th colspan='1'>{$TotaisJson["totais"]["he50APagar"]}</th>
                //     <th colspan='1'>{$TotaisJson["totais"]["he100APagar"]}</th>
                //     <th colspan='1'>{$TotaisJson["totais"]["adicionalNoturno"]}</th>
                //     <th colspan='1'>{$TotaisJson["totais"]["esperaIndenizada"]}</th>
                //     <th colspan='1'>{$TotaisJson["totais"]["saldoAnterior"]}</th>
                //     <th colspan='1'>{$TotaisJson["totais"]["saldoPeriodo"]}</th>
                //     <th colspan='1'>{$TotaisJson["totais"]["saldoFinal"]}</th>
                //     <th colspan='1'></th>
                //     <th colspan='1'></th>
                // EOD;

                // $rowTitulos .= <<<EOD
                //     <th class='matricula'>Matrícula</th>
                //     <th class='nome'>Nome</th>
                //     <th class='ocupacao'>Ocupação</th>
                //     <th class='status'>Status Endosso</th>
                //     <th class='jornadaPrevista'>Jornada Prevista</th>
                //     <th class='jornadaEfetiva'>Jornada Efetiva</th>
                //     <th class='he50APagar'>H.E. Semanal Pago</th>
                //     <th class='he100APagar'>H.E. Ex. Pago</th>
                //     <th class='adicionalNoturno'>Adicional Noturno</th>
                //     <th class='esperaIndenizada'>Espera Indenizada</th>
                //     <th class='saldoAnterior'>Saldo Anterior</th>
                //     <th class='saldoPeriodo'>Saldo Período</th>
                //     <th class='saldoFinal'>Saldo Final</th>
                //     <th class='saldoFinal'>Faltas</th>
                //     <th class='saldoFinal'>Atrasos</th>
                //     EOD;
            }else{
                $totais["he50APagar"] = ($totais["he50APagar"] == "00:00")? "": $totais["he50APagar"];
                $totais["he100APagar"] = ($totais["he100APagar"] == "00:00")? "": $totais["he100APagar"];
                $totais["adicionalNoturno"] = ($totais["adicionalNoturno"] == "00:00")? "": $totais["adicionalNoturno"];
                $totais["esperaIndenizada"] = ($totais["esperaIndenizada"] == "00:00")? "": $totais["esperaIndenizada"];
                $totais["saldoAnterior"] = ($totais["saldoAnterior"] == "00:00")? "": $totais["saldoAnterior"];
                $totais["saldoPeriodo"] = ($totais["saldoPeriodo"] == "00:00")? "": $totais["saldoPeriodo"];
                $totais["saldoFinal"] = ($totais["saldoFinal"] == "00:00")? "": $totais["saldoFinal"];

                $rowTotais .= <<<EOD
                    <th colspan='1'></th>
                    <th colspan='1'></th>
                    <th colspan='1'></th>
                    <th colspan='1'></th>
                    <th colspan='1'>{$totais["jornadaPrevista"]}</th>
                    <th colspan='1'>{$totais["jornadaEfetiva"]}</th>
                    <th colspan='1'>{$totais["he50APagar"]}</th>
                    <th colspan='1'>{$totais["he100APagar"]}</th>
                    <th colspan='1'>{$totais["adicionalNoturno"]}</th>
                    <th colspan='1'>{$totais["esperaIndenizada"]}</th>
                    <th colspan='1'>{$totais["saldoAnterior"]}</th>
                    <th colspan='1'>{$totais["saldoPeriodo"]}</th>
                    <th colspan='1'>{$totais["saldoFinal"]}</th>
                EOD;

                $rowTitulos .= <<<EOD
                    "<th data-column='nome' data-order='asc'>Nome da Empresa/Filial</th>
                    <th data-column='percEndossados' data-order='asc'>% Endossados</th>
                    <th data-column='qtdMotoristas' data-order='asc'>Qtd. Motoristas</th>
                    <th data-column='jornadaPrevista' data-order='asc'>Jornada Prevista</th>
                    <th data-column='JornadaEfetiva' data-order='asc'>Jornada Efetiva</th>
                    <th data-column='he50APagar' data-order='asc'>H.E. Semanal Pago</th>
                    <th data-column='he100APagar' data-order='asc'>H.E. Ex. Pago</th>
                    <th data-column='adicionalNoturno' data-order='asc'>Adicional Noturno</th>
                    <th data-column='esperaIndenizada' data-order='asc'>Espera Indenizada</th>
                    <th data-column='saldoAnterior' data-order='asc'>Saldo Anterior</th>
                    <th data-column='saldoPeriodo' data-order='asc'>Saldo Periodo</th>
                    <th data-column='saldoFinal' data-order='asc'>Saldo Final</th>
                EOD;
            }
            $rowTotais .= "</tr>";
            $rowTitulos .= "</tr>";

            $titulo = "de Endosso"; // usado no html
            include_once "painel_html.php";

            echo
        "<div class='script'>
                    <script>
                        var porcentagemEndoTds = document.getElementsByClassName('porcentagemEndo')[0].getElementsByTagName('td');
                        var porcentagemEndoPcTds = document.getElementsByClassName('porcentagemEndoPc')[0].getElementsByTagName('td');
                        var porcentagemNaEndoTds = document.getElementsByClassName('porcentagemNaEndo')[0].getElementsByTagName('td');
                        porcentagemEndoTds[1].innerHTML = endossos.totais.E;
                        porcentagemEndoPcTds[1].innerHTML = endossos.totais.EP;
                        porcentagemNaEndoTds[1].innerHTML = endossos.totais.N;
                        porcentagemEndoTds[2].innerHTML = Math.round(endossos.porcentagens.E*10000)/100+'%';
                        porcentagemEndoPcTds[2].innerHTML = Math.round(endossos.porcentagens.EP*10000)/100+'%';
                        porcentagemNaEndoTds[2].innerHTML = Math.round(endossos.porcentagens.N*10000)/100+'%';


                        var porcentagemPosiTds = document.getElementsByClassName('porcentagemPosi')[0].getElementsByTagName('td');
                        var porcentagemMetaTds = document.getElementsByClassName('porcentagemMeta')[0].getElementsByTagName('td');
                        var porcentagemNegaTds = document.getElementsByClassName('porcentagemNega')[0].getElementsByTagName('td');
                        porcentagemPosiTds[1].innerHTML = saldos.totais.positivos;
                        porcentagemMetaTds[1].innerHTML = saldos.totais.meta;
                        porcentagemNegaTds[1].innerHTML = saldos.totais.negativos;
                        porcentagemPosiTds[2].innerHTML = Math.round(saldos.porcentagens.positivos*10000)/100+'%';
                        porcentagemMetaTds[2].innerHTML = Math.round(saldos.porcentagens.meta*10000)/100+'%';
                        porcentagemNegaTds[2].innerHTML = Math.round(saldos.porcentagens.negativos*10000)/100+'%';

                        document.getElementsByClassName('script')[0].innerHTML = '';
                    </script>
                </div>"
            ;
        }else{
            if(!empty($_POST["acao"])){
                echo "<script>alert('Não Possui dados desse mês')</script>";
            }
        }
        
        carregarJS($arquivos);
        rodape();
    }
