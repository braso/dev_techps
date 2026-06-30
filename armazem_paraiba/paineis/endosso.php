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

    function buscar(){
        $_POST["acao"] = "buscar";
        index();
    }

    function enviarForm(){
        $_POST["acao"] = $_POST["campoAcao"];
        index();
    }

    function carregarJS(array $arquivos, array $empresasSemDados = []){

        $linha = "linha = '<tr>'";
        $modoDetalheJS = (!empty($_POST["empresa"]) && ($_POST["empresa_modo"] ?? "") === "detalhe");
        if($modoDetalheJS){
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
                <input type='hidden' name='empresa_filtro' value='".htmlspecialchars($_POST["empresa_filtro"] ?? "", ENT_QUOTES)."'>
                <input type='hidden' name='empresa_modo'>
                <input type='hidden' name='busca_data'>
                <input type='hidden' name='busca_ocupacao'>
                <input type='hidden' name='operacao'>
                <input type='hidden' name='busca_setor'>
                <input type='hidden' name='busca_subsetor'>
            </form>
            <script>
                function setAndSubmit(empresa){
                    document.myForm.acao.value = 'enviarForm()';
                    document.myForm.campoAcao.value = 'buscar';
                    document.myForm.empresa.value = empresa;
                    document.myForm.empresa_modo.value = 'detalhe';
                    document.myForm.busca_data.value = document.getElementById('busca_data').value;
                    // Salva a seleção atual do filtro de empresas para restaurar ao voltar
                    var hiddenEmpresa = document.querySelector('.js-filtro-hidden[data-filter-name=\"empresa\"]');
                    document.myForm.empresa_filtro.value = hiddenEmpresa ? hiddenEmpresa.value : '';
                    // Copia todos os outros filtros para preservar ao voltar
                    var filtros = ['busca_ocupacao','operacao','busca_setor','busca_subsetor'];
                    filtros.forEach(function(nome){
                        var origem = document.querySelector('.js-filtro-hidden[data-filter-name=\"' + nome + '\"]');
                        var destino = document.querySelector('form[name=\"myForm\"] [name=\"' + nome + '\"]');
                        if(origem && destino){ destino.value = origem.value; }
                    });
                    document.myForm.submit();
                }

                function voltarParaEmpresas(){
                    document.myForm.acao.value = 'enviarForm()';
                    document.myForm.campoAcao.value = 'buscar';
                    document.myForm.empresa_modo.value = 'filtro';
                    document.myForm.busca_data.value = document.getElementById('busca_data').value;
                    // Restaura a seleção original de empresas do filtro (salva em empresa_filtro)
                    var empresaFiltroInput = document.querySelector('form[name=\"myForm\"] [name=\"empresa_filtro\"]');
                    document.myForm.empresa.value = empresaFiltroInput ? empresaFiltroInput.value : '';
                    // Copia os demais filtros
                    var filtros = ['busca_ocupacao','operacao','busca_setor','busca_subsetor'];
                    filtros.forEach(function(nome){
                        var origem = document.querySelector('.js-filtro-hidden[data-filter-name=\"' + nome + '\"]');
                        var destino = document.querySelector('form[name=\"myForm\"] [name=\"' + nome + '\"]');
                        if(origem && destino){ destino.value = origem.value; }
                    });
                    document.myForm.submit();
                }

                function atualizarPainel(){
                    var hiddenEmpresa = document.querySelector('.js-filtro-hidden[data-filter-name=\"empresa\"]');
                    document.myForm.empresa.value = hiddenEmpresa ? hiddenEmpresa.value : '';
                    document.myForm.busca_data.value = document.getElementById('busca_data').value;
                    document.myForm.atualizar.value = 'atualizar';
                    var filtros = ['busca_ocupacao','operacao','busca_setor','busca_subsetor'];
                    filtros.forEach(function(nome){
                        var origem = document.querySelector('.js-filtro-hidden[data-filter-name=\"' + nome + '\"]');
                        var destino = document.querySelector('form[name=\"myForm\"] [name=\"' + nome + '\"]');
                        if(origem && destino){ destino.value = origem.value; }
                    });
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

                    function normalizarOcupacao(valor){
                        var txt = (valor || '').toString().trim().toLowerCase();
                        if (txt === 'tercerizado') {
                            return 'terceirizado';
                        }
                        return txt;
                    }

                    function toFilterArray(raw){
                        if(!raw) return [];
                        return raw.toString().split(',').map(function(s){ return normalizarOcupacao(s); }).filter(function(s){ return s !== ''; });
                    }
                    var ocupacoesFilter = toFilterArray(ocupacoesPermitidas);
                    var operacaoFilter = toFilterArray(operacaoPermitidas);
                    var setorFilter = toFilterArray(setorPermitidas);
                    var subSetorFilter = toFilterArray(subSetorPermitidas);

                    function carregarDados(urlArquivo){
                        $.ajax({
                            url: urlArquivo + '?v=' + new Date().getTime(),
                            dataType: 'json',
                            success: function(data){
                                var row = {};
                                $.each(data, function(index, item){
                                    row[index] = item;
                                });

                                // Normaliza valores das linhas para comparação
                                var rowOcup = normalizarOcupacao(row.ocupacao);
                                var rowOper = (row.tipoOperacao || '').toString().trim().toLowerCase();
                                var rowSet  = (row.setor || '').toString().trim().toLowerCase();
                                var rowSub  = (row.subsetor || '').toString().trim().toLowerCase();

                                if (
                                    (ocupacoesFilter.length > 0 && rowOcup !== '' && !ocupacoesFilter.includes(rowOcup)) ||
                                    (operacaoFilter.length > 0 && rowOper !== '' && !operacaoFilter.includes(rowOper)) ||
                                    (setorFilter.length > 0 && rowSet !== '' && !setorFilter.includes(rowSet)) ||
                                    (subSetorFilter.length > 0 && rowSub !== '' && !subSetorFilter.includes(rowSub))
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
                                            'saldoAnterior': row.saldoAnterior,
                                            'saldoPeriodo': row.saldoPeriodo,
                                            'saldoFinal': row.saldoFinal
                                        };
                                    }
                                }else{
                                    // Mostrar painel geral das empresas.
                                
                                    if(row.percEndossado !== undefined && row.percEndossado < 1){
                                        row.totais = {
                                            'saldoAnterior': row.totais ? row.totais.saldoAnterior : undefined
                                        };
                                    }
                                }
                                if(row.totais === undefined){
                                    row.totais = {};
                                }
                                var invalidValues = [undefined, '00:00'];
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
                        if (!horas || typeof horas !== 'string' || !horas.includes(':')) {
                            return 0;
                        }
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

        $botao_volta = "";
        $botao_imprimir = "";
        $botao_baixar_txt = "";
        $botaoAtualizarPainel = "";
        $linhasEmpresasZeradas = "";

        // $texto = "<div style=''><b>Periodo da Busca:</b> $monthName de $year</div>";
        //position: absolute; top: 101px; left: 420px;
        $setoresSelecionados = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_POST["busca_setor"] ?? ""))))));
        $temSubsetorVinculado = false;
        if (!empty($setoresSelecionados)) {
            $condSetores = implode(',', $setoresSelecionados);
            $rowCount = mysqli_fetch_array(query("SELECT COUNT(*) FROM sbgrupos_documentos WHERE sbgr_tx_status = 'ativo' AND sbgr_nb_idgrup IN (".$condSetores.");"));
            $temSubsetorVinculado = ($rowCount[0] > 0);
        }

        $empresaModoAtual = $_POST["empresa_modo"] ?? "";
        $empresaSelecionadas = [];
        // Em modo detalhe, usa empresa_filtro para popular o checklist (preserva seleção original)
        if($empresaModoAtual === "detalhe" && !empty($_POST["empresa_filtro"])){
            $empresaSelecionadasRaw = (string)$_POST["empresa_filtro"];
        } else {
            $empresaSelecionadasRaw = !empty($_POST["empresa"]) ? (string)$_POST["empresa"] : (!empty($_SESSION["user_nb_empresa"]) ? (string)$_SESSION["user_nb_empresa"] : "");
        }
        if($empresaSelecionadasRaw !== ""){
            $empresaSelecionadas = array_values(array_filter(array_map('trim', explode(',', $empresaSelecionadasRaw)), function($v){ return $v !== ''; }));
        }

        $empresaOpcoes = [];
        $resEmpresasFiltro = query("SELECT empr_nb_id, empr_tx_nome FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_tx_nome ASC");
        while($empresaRow = mysqli_fetch_assoc($resEmpresasFiltro)){
            $empresaOpcoes[(string)intval($empresaRow["empr_nb_id"])] = $empresaRow["empr_tx_nome"];
        }

        $empresaLabelBotao = "Empresa";
        if(count($empresaSelecionadas) === 1){
            $empresaIdSelecionada = (string)$empresaSelecionadas[0];
            if(isset($empresaOpcoes[$empresaIdSelecionada])) $empresaLabelBotao = $empresaOpcoes[$empresaIdSelecionada];
        }elseif(count($empresaSelecionadas) > 1){
            $empresaLabelBotao = "Empresa (".count($empresaSelecionadas).")";
        }

        $selectEmpresa = "<div class='col-sm-4 margin-bottom-5 campo-fit-content'>"
            ."<label>Empresa</label>"
            ."<div class='filtro-compact' data-filter='empresa' data-label='Empresa' style='position:relative;'>"
            ."<button type='button' class='btn btn-default btn-block js-filtro-toggle' style='display:flex;justify-content:space-between;align-items:center;'>"
            ."<span class='filtro-label'>".htmlspecialchars($empresaLabelBotao, ENT_QUOTES, 'UTF-8')."</span><span class='caret'></span></button>"
            ."<div class='filtro-dropdown-menu' style='display:none; position:absolute; left:0; right:0; z-index:1050; background:#fff; border:1px solid #d9d9d9; padding:8px; max-height:240px; overflow:auto;'>"
            ."<div style='margin-bottom:8px;'>"
            ."<button type='button' class='btn btn-xs btn-link js-filtro-todos' data-target='empresa' data-action='all'>Marcar todos</button>"
            ."<button type='button' class='btn btn-xs btn-link js-filtro-todos' data-target='empresa' data-action='none'>Desmarcar todos</button>"
            ."</div>"
            ."<input type='hidden' class='js-filtro-hidden' data-filter-name='empresa' name='empresa' value='".htmlspecialchars(implode(',', $empresaSelecionadas), ENT_QUOTES, 'UTF-8')."'>";
        foreach($empresaOpcoes as $empresaId => $empresaNome){
            $checked = in_array((string)$empresaId, $empresaSelecionadas, true) ? "checked" : "";
            $selectEmpresa .= "<label style='display:block;margin-bottom:6px;cursor:pointer;'>"
                ."<input type='checkbox' class='js-filtro-checkbox' data-target='empresa' value='".htmlspecialchars((string)$empresaId, ENT_QUOTES, 'UTF-8')."' ".$checked." style='margin-right:6px;'>"
                .htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8')."</label>";
        }
        $selectEmpresa .= "</div></div></div>";

        $ocupacaoSelecionadas = array_values(array_filter(array_map('trim', explode(',', (string)($_POST["busca_ocupacao"] ?? ""))), function($v){ return $v !== ''; }));
        $ocupacaoOpcoes = ["Motorista"=>"Motorista","Ajudante"=>"Ajudante","Funcionário"=>"Funcionário","Terceirizado"=>"Terceirizado"];
        $selectOcupacao = "<div class='col-sm-2 margin-bottom-5 campo-fit-content'>"
            ."<label>Ocupação</label>"
            ."<div class='filtro-compact' data-filter='busca_ocupacao' data-label='Ocupação' style='position:relative;'>"
            ."<button type='button' class='btn btn-default btn-block js-filtro-toggle' style='display:flex;justify-content:space-between;align-items:center;'>"
            ."<span class='filtro-label'>Ocupação".(count($ocupacaoSelecionadas)>0?" (".count($ocupacaoSelecionadas).")":"")."</span><span class='caret'></span></button>"
            ."<div class='filtro-dropdown-menu' style='display:none; position:absolute; left:0; right:0; z-index:1050; background:#fff; border:1px solid #d9d9d9; padding:8px; max-height:240px; overflow:auto;'>"
            ."<div style='margin-bottom:8px;'>"
            ."<button type='button' class='btn btn-xs btn-link js-filtro-todos' data-target='busca_ocupacao' data-action='all'>Marcar todos</button>"
            ."<button type='button' class='btn btn-xs btn-link js-filtro-todos' data-target='busca_ocupacao' data-action='none'>Desmarcar todos</button>"
            ."</div>"
            ."<input type='hidden' class='js-filtro-hidden' data-filter-name='busca_ocupacao' name='busca_ocupacao' value='".htmlspecialchars(implode(',', $ocupacaoSelecionadas), ENT_QUOTES, 'UTF-8')."'>";
        foreach($ocupacaoOpcoes as $v => $l){
            $ck = in_array((string)$v, $ocupacaoSelecionadas, true) ? "checked" : "";
            $selectOcupacao .= "<label style='display:block;margin-bottom:6px;cursor:pointer;'><input type='checkbox' class='js-filtro-checkbox' data-target='busca_ocupacao' value='".htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8')."' ".$ck." style='margin-right:6px;'>".htmlspecialchars($l, ENT_QUOTES, 'UTF-8')."</label>";
        }
        $selectOcupacao .= "</div></div></div>";

        $operacaoSelecionadas = array_values(array_filter(array_map('trim', explode(',', (string)($_POST["operacao"] ?? ""))), function($v){ return $v !== ''; }));
        $operacaoOpcoes = [];
        $resOperacaoFiltro = query("SELECT oper_nb_id, oper_tx_nome FROM operacao WHERE oper_tx_status = 'ativo' ORDER BY oper_tx_nome ASC");
        while($r = mysqli_fetch_assoc($resOperacaoFiltro)) $operacaoOpcoes[(string)intval($r["oper_nb_id"])] = $r["oper_tx_nome"];
        $selectOperacao = "<div class='col-sm-2 margin-bottom-5 campo-fit-content'>"
            ."<label>Cargo</label>"
            ."<div class='filtro-compact' data-filter='operacao' data-label='Cargo' style='position:relative;'>"
            ."<button type='button' class='btn btn-default btn-block js-filtro-toggle' style='display:flex;justify-content:space-between;align-items:center;'>"
            ."<span class='filtro-label'>Cargo".(count($operacaoSelecionadas)>0?" (".count($operacaoSelecionadas).")":"")."</span><span class='caret'></span></button>"
            ."<div class='filtro-dropdown-menu' style='display:none; position:absolute; left:0; right:0; z-index:1050; background:#fff; border:1px solid #d9d9d9; padding:8px; max-height:240px; overflow:auto;'>"
            ."<div style='margin-bottom:8px;'>"
            ."<button type='button' class='btn btn-xs btn-link js-filtro-todos' data-target='operacao' data-action='all'>Marcar todos</button>"
            ."<button type='button' class='btn btn-xs btn-link js-filtro-todos' data-target='operacao' data-action='none'>Desmarcar todos</button>"
            ."</div>"
            ."<input type='hidden' class='js-filtro-hidden' data-filter-name='operacao' name='operacao' value='".htmlspecialchars(implode(',', $operacaoSelecionadas), ENT_QUOTES, 'UTF-8')."'>";
        foreach($operacaoOpcoes as $v => $l){
            $ck = in_array((string)$v, $operacaoSelecionadas, true) ? "checked" : "";
            $selectOperacao .= "<label style='display:block;margin-bottom:6px;cursor:pointer;'><input type='checkbox' class='js-filtro-checkbox' data-target='operacao' value='".htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8')."' ".$ck." style='margin-right:6px;'>".htmlspecialchars($l, ENT_QUOTES, 'UTF-8')."</label>";
        }
        $selectOperacao .= "</div></div></div>";

        $setorSelecionados = array_values(array_filter(array_map('trim', explode(',', (string)($_POST["busca_setor"] ?? ""))), function($v){ return $v !== ''; }));
        $setorOpcoes = [];
        $resSetorFiltro = query("SELECT grup_nb_id, grup_tx_nome FROM grupos_documentos WHERE grup_tx_status = 'ativo' ORDER BY grup_tx_nome ASC");
        while($r = mysqli_fetch_assoc($resSetorFiltro)) $setorOpcoes[(string)intval($r["grup_nb_id"])] = $r["grup_tx_nome"];
        $selectSetor = "<div class='col-sm-2 margin-bottom-5 campo-fit-content'>"
            ."<label>Setor</label>"
            ."<div class='filtro-compact' data-filter='busca_setor' data-label='Setor' style='position:relative;'>"
            ."<button type='button' class='btn btn-default btn-block js-filtro-toggle' style='display:flex;justify-content:space-between;align-items:center;'>"
            ."<span class='filtro-label'>Setor".(count($setorSelecionados)>0?" (".count($setorSelecionados).")":"")."</span><span class='caret'></span></button>"
            ."<div class='filtro-dropdown-menu' style='display:none; position:absolute; left:0; right:0; z-index:1050; background:#fff; border:1px solid #d9d9d9; padding:8px; max-height:240px; overflow:auto;'>"
            ."<div style='margin-bottom:8px;'>"
            ."<button type='button' class='btn btn-xs btn-link js-filtro-todos' data-target='busca_setor' data-action='all'>Marcar todos</button>"
            ."<button type='button' class='btn btn-xs btn-link js-filtro-todos' data-target='busca_setor' data-action='none'>Desmarcar todos</button>"
            ."</div>"
            ."<input type='hidden' class='js-filtro-hidden' data-filter-name='busca_setor' name='busca_setor' value='".htmlspecialchars(implode(',', $setorSelecionados), ENT_QUOTES, 'UTF-8')."'>";
        foreach($setorOpcoes as $v => $l){
            $ck = in_array((string)$v, $setorSelecionados, true) ? "checked" : "";
            $selectSetor .= "<label style='display:block;margin-bottom:6px;cursor:pointer;'><input type='checkbox' class='js-filtro-checkbox' data-target='busca_setor' value='".htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8')."' ".$ck." style='margin-right:6px;'>".htmlspecialchars($l, ENT_QUOTES, 'UTF-8')."</label>";
        }
        $selectSetor .= "</div></div></div>";

        $subsetorSelecionados = array_values(array_filter(array_map('trim', explode(',', (string)($_POST["busca_subsetor"] ?? ""))), function($v){ return $v !== ''; }));
        $selectSubsetor = "";
        if($temSubsetorVinculado){
            $subsetorOpcoes = [];
            $condSub = implode(',', $setoresSelecionados);
            $resSubFiltro = query("SELECT sbgr_nb_id, sbgr_tx_nome FROM sbgrupos_documentos WHERE sbgr_tx_status = 'ativo' AND sbgr_nb_idgrup IN (".$condSub.") ORDER BY sbgr_tx_nome ASC");
            while($r = mysqli_fetch_assoc($resSubFiltro)) $subsetorOpcoes[(string)intval($r["sbgr_nb_id"])] = $r["sbgr_tx_nome"];
            $selectSubsetor = "<div class='col-sm-2 margin-bottom-5 campo-fit-content'>"
                ."<label>Subsetor</label>"
                ."<div class='filtro-compact' data-filter='busca_subsetor' data-label='Subsetor' style='position:relative;'>"
                ."<button type='button' class='btn btn-default btn-block js-filtro-toggle' style='display:flex;justify-content:space-between;align-items:center;'>"
                ."<span class='filtro-label'>Subsetor".(count($subsetorSelecionados)>0?" (".count($subsetorSelecionados).")":"")."</span><span class='caret'></span></button>"
                ."<div class='filtro-dropdown-menu' style='display:none; position:absolute; left:0; right:0; z-index:1050; background:#fff; border:1px solid #d9d9d9; padding:8px; max-height:240px; overflow:auto;'>"
                ."<div style='margin-bottom:8px;'>"
                ."<button type='button' class='btn btn-xs btn-link js-filtro-todos' data-target='busca_subsetor' data-action='all'>Marcar todos</button>"
                ."<button type='button' class='btn btn-xs btn-link js-filtro-todos' data-target='busca_subsetor' data-action='none'>Desmarcar todos</button>"
                ."</div>"
                ."<input type='hidden' class='js-filtro-hidden' data-filter-name='busca_subsetor' name='busca_subsetor' value='".htmlspecialchars(implode(',', $subsetorSelecionados), ENT_QUOTES, 'UTF-8')."'>";
            foreach($subsetorOpcoes as $v => $l){
                $ck = in_array((string)$v, $subsetorSelecionados, true) ? "checked" : "";
                $selectSubsetor .= "<label style='display:block;margin-bottom:6px;cursor:pointer;'><input type='checkbox' class='js-filtro-checkbox' data-target='busca_subsetor' value='".htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8')."' ".$ck." style='margin-right:6px;'>".htmlspecialchars($l, ENT_QUOTES, 'UTF-8')."</label>";
            }
            $selectSubsetor .= "</div></div></div>";
        }

        $fields = [
            $selectEmpresa,
            $selectOcupacao,
            $selectOperacao,
            $selectSetor,
            campo_mes("Data", "busca_data", ($_POST["busca_data"]?? ""), 2, $extraCampoData),
        ];
        if($temSubsetorVinculado) $fields[] = $selectSubsetor;

        if($empresaModoAtual === "detalhe" && !empty($_POST["empresa"])){
            $botao_volta = "<button class='btn default' type='button' onclick='voltarParaEmpresas()'>Voltar</button>";
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
        if(!empty($_POST["empresa"])){
            $botao_baixar_txt = "<button class='btn default' type='button' onclick='exportarAdicionalNoturno()'>Baixar TXT Ad.Not.</button>
            <script>
            function exportarAdicionalNoturno() {
                var empresaId = '" . intval($_POST['empresa']) . "';
                var buscaData = '" . ($_POST['busca_data'] ?? '') . "';
                if (!empresaId || !buscaData) {
                    alert('Empresa e competência são obrigatórias');
                    return;
                }
                var competencia = buscaData.replace('-', '');
                
                var linhas = [];
                var tabela = document.querySelector('#tabela-empresas tbody');
                if (!tabela) {
                    alert('Tabela de resultados não encontrada.');
                    return;
                }
                var tableRows = tabela.querySelectorAll('tr');
                tableRows.forEach(function(tr) {
                    var tds = tr.cells;
                    if (tds.length < 12) return; 
                    var matricula = (tds[0]?.innerText || tds[0]?.textContent || '').trim();
                    var statusEndosso = (tds[6]?.innerText || tds[6]?.textContent || '').trim();
                    // Pega o valor e remove ':' (ex: 00:36 -> 0036)
                    var adNot = (tds[11]?.innerText || tds[11]?.textContent || '').trim().replace(':', '');
                    
                    if ((statusEndosso === 'E' || statusEndosso === 'EP') && matricula !== '' && adNot !== '' && parseInt(adNot) > 0) {
                        linhas.push({
                            matricula: matricula,
                            adicionalNoturno: adNot,
                            statusEndosso: statusEndosso // For debugging/verification, not strictly needed for TXT
                        });
                    }
                });

                if (linhas.length === 0) {
                    alert('Nenhum registro endossado com adicional noturno encontrado para exportar.');
                    return;
                }

                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'export_adicional_noturno.php';
                
                var empresaInput = document.createElement('input'); 
                empresaInput.type = 'hidden';
                empresaInput.name = 'empresa';
                empresaInput.value = empresaId;
                form.appendChild(empresaInput);

                var competenciaInput = document.createElement('input');
                competenciaInput.type = 'hidden';
                competenciaInput.name = 'competencia';
                competenciaInput.value = competencia;
                form.appendChild(competenciaInput);

                var dadosInput = document.createElement('input');
                dadosInput.type = 'hidden';
                dadosInput.name = 'dados';
                dadosInput.value = JSON.stringify(linhas);
                form.appendChild(dadosInput);

                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
            }
            </script>";
        }

        $buttons = [
            botao("Buscar", "buscar", "", "", "", "", "btn btn-info"),
            $botao_imprimir,
            $botao_baixar_txt,
            $botao_volta,
            $botaoAtualizarPainel
        ];


        echo abre_form();
        echo campo_hidden("reloadOnly", "");
        echo linha_form($fields);
        echo fecha_form($buttons);

        echo <<<'HTML'
        <script>
        (function(){
            function fecharDropdowns(){
                document.querySelectorAll('.filtro-dropdown-menu').forEach(function(menu){
                    menu.style.display = 'none';
                });
            }
            function atualizarHidden(target){
                var hidden = document.querySelector('.js-filtro-hidden[data-filter-name="' + target + '"]');
                if(!hidden){ return; }
                var values = [];
                document.querySelectorAll('input.js-filtro-checkbox[data-target="' + target + '"]:checked').forEach(function(chk){
                    values.push(chk.value);
                });
                hidden.value = values.join(',');
                atualizarTitulo(target, values.length);
            }
            function atualizarTitulo(target, count){
                var wrap = document.querySelector('.filtro-compact[data-filter="' + target + '"]');
                if(!wrap){ return; }
                var label = wrap.querySelector('.filtro-label');
                if(!label){ return; }
                var baseLabel = wrap.getAttribute('data-label') || target;
                if(target === 'empresa' && count === 1){
                    var checkedEmpresa = wrap.querySelector('input.js-filtro-checkbox[data-target="empresa"]:checked');
                    var empresaTexto = (checkedEmpresa && checkedEmpresa.parentElement) ? checkedEmpresa.parentElement.textContent.trim() : '';
                    label.textContent = empresaTexto || baseLabel;
                    return;
                }
                label.textContent = baseLabel + (count > 1 ? ' (' + count + ')' : '');
            }
            document.querySelectorAll('.js-filtro-toggle').forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.preventDefault(); e.stopPropagation();
                    var menu = btn.parentNode.querySelector('.filtro-dropdown-menu');
                    var isOpen = menu && menu.style.display === 'block';
                    fecharDropdowns();
                    if(menu){ menu.style.display = isOpen ? 'none' : 'block'; }
                });
            });
            // Impede que cliques dentro do dropdown fechem o menu
            document.querySelectorAll('.filtro-dropdown-menu').forEach(function(menu){
                menu.addEventListener('click', function(e){ e.stopPropagation(); });
            });
            document.querySelectorAll('.js-filtro-checkbox').forEach(function(chk){
                chk.addEventListener('change', function(){
                    var target = chk.getAttribute('data-target');
                    if(target){ atualizarHidden(target); }
                });
            });
            document.querySelectorAll('.js-filtro-todos').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var target = btn.getAttribute('data-target');
                    if(!target){ return; }
                    var marcar = btn.getAttribute('data-action') === 'all';
                    var boxes = document.querySelectorAll('input.js-filtro-checkbox[data-target="' + target + '"]');
                    boxes.forEach(function(chk){ chk.checked = marcar; });
                    if(window.jQuery && typeof jQuery.uniform !== 'undefined' && typeof jQuery.uniform.update === 'function'){
                        jQuery.uniform.update(jQuery(boxes));
                    }
                    atualizarHidden(target);
                });
            });
            document.addEventListener('click', function(){ fecharDropdowns(); });
        })();
        </script>
HTML;

        
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
        if(!empty($_POST["busca_data"])){
            $dataMes = DateTime::createFromFormat("Y-m-d H:i:s", $_POST["busca_data"]."-01 00:00:00");
            if ($dataMes) {
                $dataFim = DateTime::createFromFormat("Y-m-d H:i:s", (date("Y-m-d") < $dataMes->format("Y-m-t") ? date("Y-m-d") : $dataMes->format("Y-m-t"))." 00:00:00");
                $periodoRelatorio = [
                    "dataInicio" => $dataMes->format("d/m"),
                    "dataFim" => $dataFim->format("d/m")
                ];
            }
        }

        $modoDetalhe = false;

        if(!empty($_POST["empresa"]) && !empty($_POST["busca_data"]) && empty($_POST["reloadOnly"])
            && ($empresaModoAtual === "detalhe")){
            $modoDetalhe = true;
            //Painel dos endossos dos motoristas de uma ou mais empresas
            $empresaIds = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$_POST["empresa"])), function($v){ return $v > 0; })));

            $normalizarTxt = function($valor){ return mb_strtolower(trim((string)$valor)); };
            $normalizarOcupacao = function($ocupacao) use ($normalizarTxt){
                $txt = $normalizarTxt($ocupacao);
                return ($txt === "tercerizado") ? "terceirizado" : $txt;
            };
            $parseFiltroCsv = function($raw, $norm = null){
                $itens = array_values(array_filter(array_map('trim', explode(',', (string)$raw)), function($v){ return $v !== ''; }));
                return ($norm !== null) ? array_map($norm, $itens) : $itens;
            };
            $ocupacaoFiltro = $parseFiltroCsv($_POST["busca_ocupacao"] ?? "", $normalizarOcupacao);
            $operacaoFiltro = $parseFiltroCsv($_POST["operacao"] ?? "", $normalizarTxt);
            $setorFiltro    = $parseFiltroCsv($_POST["busca_setor"] ?? "", $normalizarTxt);
            $subsetorFiltro = $parseFiltroCsv($_POST["busca_subsetor"] ?? "", $normalizarTxt);

            $motoristas = [];
            $empresaNomes = [];
            $latestMTime = 0;
            $periodoRelatorioBruto = null;

            foreach($empresaIds as $empresaId){
                $aEmpresa = mysqli_fetch_assoc(query(
                    "SELECT * FROM empresa WHERE empr_tx_status = 'ativo' AND empr_nb_id = {$empresaId} LIMIT 1;"
                ));
                if(empty($aEmpresa)) continue;

                $pathEmpresa = $path . "/" . $_POST["busca_data"] . "/" . $aEmpresa["empr_nb_id"];
                if(!is_dir($pathEmpresa)) continue;

                $encontrado = true;
                $empresaNomes[] = $aEmpresa["empr_tx_nome"];
                $jsonEmpresaPath = $pathEmpresa . "/empresa_" . $aEmpresa["empr_nb_id"] . ".json";
                if(is_file($jsonEmpresaPath)){
                    $mtime = filemtime($jsonEmpresaPath);
                    if($mtime > $latestMTime) $latestMTime = $mtime;
                    if($periodoRelatorioBruto === null){
                        $periodoTmp = json_decode(file_get_contents($jsonEmpresaPath), true);
                        if(!empty($periodoTmp["dataInicio"]) && !empty($periodoTmp["dataFim"])){
                            $periodoRelatorioBruto = ["dataInicio" => $periodoTmp["dataInicio"], "dataFim" => $periodoTmp["dataFim"]];
                        }
                    }
                }
                $pastaSaldosEmpresa = dir($pathEmpresa);
                while($arquivo = $pastaSaldosEmpresa->read()){
                    if(!in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresa_"))){
                        $arquivos[] = $pathEmpresa . "/" . $arquivo;
                    }
                }
                $pastaSaldosEmpresa->close();
            }

            if($encontrado){
                $dataArquivoFmt = $latestMTime > 0 ? date("d/m/Y", $latestMTime) : date("d/m/Y");
                if($dataArquivoFmt != date("d/m/Y")){
                    $alertaEmissao = "<span style='color: red; border: 2px solid; padding: 2px; border-radius: 4px;'><i style='color:red; margin-right: 5px;' title='As informações do painel não correspondem à data de hoje.' class='fa fa-warning'></i>";
                }else{
                    $alertaEmissao = "<span>";
                }
                $dataEmissao = $alertaEmissao . " Atualizado em: ".($latestMTime > 0 ? date("d/m/Y H:i", $latestMTime) : "")."</span>";
                if($periodoRelatorioBruto !== null){
                    $dtIni = DateTime::createFromFormat("d/m/Y", $periodoRelatorioBruto["dataInicio"]);
                    $dtFim = DateTime::createFromFormat("d/m/Y", $periodoRelatorioBruto["dataFim"]);
                    $periodoRelatorio = [
                        "dataInicio" => $dtIni ? $dtIni->format("d/m") : "",
                        "dataFim" => $dtFim ? $dtFim->format("d/m") : ""
                    ];
                }
                foreach($arquivos as $arquivo){
                    $json = json_decode(file_get_contents($arquivo), true);
                    $json["dataAtualizacao"] = date("d/m/Y H:i", filemtime($arquivo));
                    $motoristas[] = $json;
                }
                $totais["empresaNome"] = count($empresaNomes) > 1 ? "Múltiplas empresas" : ($empresaNomes[0] ?? "");
                $totaisFiltrados = array_fill_keys(array_keys(array_filter($totais, function($k){ return $k !== "empresaNome"; }, ARRAY_FILTER_USE_KEY)), "00:00");

                foreach($motoristas as $saldosMotorista){
                    $rowOcup = $normalizarOcupacao($saldosMotorista["ocupacao"] ?? "");
                    $rowOper = $normalizarTxt($saldosMotorista["tipoOperacao"] ?? "");
                    $rowSet  = $normalizarTxt($saldosMotorista["setor"] ?? "");
                    $rowSub  = $normalizarTxt($saldosMotorista["subsetor"] ?? "");
                    if((!empty($ocupacaoFiltro) && $rowOcup !== "" && !in_array($rowOcup, $ocupacaoFiltro, true))
                        || (!empty($operacaoFiltro) && $rowOper !== "" && !in_array($rowOper, $operacaoFiltro, true))
                        || (!empty($setorFiltro) && $rowSet !== "" && !in_array($rowSet, $setorFiltro, true))
                        || (!empty($subsetorFiltro) && $rowSub !== "" && !in_array($rowSub, $subsetorFiltro, true))) continue;

                    $statusEndosso = $saldosMotorista["statusEndosso"] ?? "";
                    if(isset($contagemEndossos[$statusEndosso])) $contagemEndossos[$statusEndosso]++;
                    foreach($totaisFiltrados as $key => $value){
                        $totaisFiltrados[$key] = operarHorarios([$totaisFiltrados[$key], $saldosMotorista[$key] ?? "00:00"], "+");
                    }
                    if(($saldosMotorista["saldoFinal"] ?? "") === "00:00") $contagemSaldos["meta"]++;
                    elseif(!empty($saldosMotorista["saldoFinal"]) && $saldosMotorista["saldoFinal"][0] == "-") $contagemSaldos["negativos"]++;
                    else $contagemSaldos["positivos"]++;
                }
                $totais = array_merge($totais, $totaisFiltrados);
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

            
            $arquivoGeralPath = $path."/empresas.json";
            $temJsonEmpresas = false;
            if(is_dir($path)){
                $pastas = glob($path."/*", GLOB_ONLYDIR);
                foreach($pastas as $p){
                    if(count(glob($p."/empresa_*.json")) > 0){
                        $temJsonEmpresas = true;
                        break;
                    }
                }
            }
            if(is_dir($path) && (file_exists($arquivoGeralPath) || $temJsonEmpresas)){
                $mtimeGeral = file_exists($arquivoGeralPath) ? filemtime($arquivoGeralPath) : time();
                $dataArquivo = date("d/m/Y H:i", $mtimeGeral);
                $horaArquivo = date("H:i", $mtimeGeral);

                $dataAtual = date("d/m/Y");
                $horaAtual = date("H:i");
                if($dataArquivo != $dataAtual){
                    $alertaEmissao = "<span style='color: red; border: 2px solid; padding: 2px; border-radius: 4px;'>
                    <i style='color:red;' title='As informações do painel não correspondem à data de hoje.' class='fa fa-warning'></i>";
                } else {
                    $alertaEmissao = "<span>";
                }
                $dataEmissao = $alertaEmissao." Atualizado em: ".date("d/m/Y H:i", $mtimeGeral)."</span>"; //Utilizado no HTML.
                
                if(file_exists($arquivoGeralPath)){
                    $arquivoGeral = json_decode(file_get_contents($arquivoGeralPath), true);
                    $dtIni = DateTime::createFromFormat("d/m/Y", $arquivoGeral["dataInicio"]);
                    $dtFim = DateTime::createFromFormat("d/m/Y", $arquivoGeral["dataFim"]);
                } else {
                    // Fallback se empresas.json não existir
                    $dataInicioFallback = $dataMes->format("01/m/Y");
                    $dataFimFallback = $dataFim->format("d/m/Y");
                    $pastas = glob($path."/*", GLOB_ONLYDIR);
                    foreach($pastas as $p){
                        $arquivosEmpresas = glob($p."/empresa_*.json");
                        if(!empty($arquivosEmpresas)){
                            $empJson = json_decode(file_get_contents($arquivosEmpresas[0]), true);
                            if(!empty($empJson["dataInicio"]) && !empty($empJson["dataFim"])){
                                $dataInicioFallback = $empJson["dataInicio"];
                                $dataFimFallback = $empJson["dataFim"];
                                break;
                            }
                        }
                    }
                    $dtIni = DateTime::createFromFormat("d/m/Y", $dataInicioFallback);
                    $dtFim = DateTime::createFromFormat("d/m/Y", $dataFimFallback);
                }
                $periodoRelatorio = [
                    "dataInicio" => $dtIni ? $dtIni->format("d/m") : "",
                    "dataFim" => $dtFim ? $dtFim->format("d/m") : ""
                ];

                $pastaEndossos = dir($path);
                while($arquivo = $pastaEndossos->read()){
                    if(!empty($arquivo) && !in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresas"))){
                        // Filtra pelas empresas selecionadas no filtro
                        if(!empty($empresaSelecionadas) && !in_array((string)intval($arquivo), $empresaSelecionadas, true)){
                            continue;
                        }
                        $arquivo = $path."/".$arquivo."/empresa_".$arquivo.".json";
                        $arquivos[] = $arquivo;
                        $json = json_decode(file_get_contents($arquivo), true);
                        foreach($totais as $key => $value){
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
                // Sem dados no mês: monta grid com empresas do banco todas zeradas
                $encontrado = true;
                $latestUpdate = 0;
                $endossosDir = "./arquivos/endossos";
                if (is_dir($endossosDir)) {
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($endossosDir, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::SELF_FIRST
                    );
                    foreach ($iterator as $file) {
                        if ($file->isFile() && (strpos($file->getFilename(), 'empresa_') === 0 || $file->getFilename() === 'empresas.json')) {
                            $mtime = $file->getMTime();
                            if ($mtime > $latestUpdate) {
                                $latestUpdate = $mtime;
                            }
                        }
                    }
                }

                if ($latestUpdate > 0) {
                    $dataEmissao = "<i style='color:orange;' title='Sem dados gerados para este mês.' class='fa fa-warning'></i> Atualizado em: " . date("d/m/Y H:i", $latestUpdate);
                } else {
                    $dataEmissao = "<i style='color:orange;' title='Sem dados gerados para este mês.' class='fa fa-warning'></i> Atualizado em: Nunca";
                }

                $empresasFiltroIds = [];
                if(!empty($_POST["empresa"]) && ($empresaModoAtual !== "detalhe")){
                    $empresasFiltroIds = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$_POST["empresa"])), function($v){ return $v > 0; })));
                }

                $condEmpresasBanco = "";
                if(!empty($empresasFiltroIds)){
                    $condEmpresasBanco = " AND empr_nb_id IN (".implode(',', $empresasFiltroIds).")";
                }
                $resEmpresasBanco = query("SELECT empr_nb_id, empr_tx_nome FROM empresa WHERE empr_tx_status = 'ativo'".$condEmpresasBanco." ORDER BY empr_tx_nome ASC");
                $totaisZero = [
                    "jornadaPrevista" => "00:00", "jornadaEfetiva" => "00:00",
                    "he50APagar" => "00:00", "he100APagar" => "00:00",
                    "adicionalNoturno" => "00:00", "esperaIndenizada" => "00:00",
                    "saldoAnterior" => "00:00", "saldoPeriodo" => "00:00", "saldoFinal" => "00:00"
                ];
                $linhasEmpresasZeradas = "";
                while($empRow = mysqli_fetch_assoc($resEmpresasBanco)){
                    $empresas[] = [
                        "empr_nb_id"     => $empRow["empr_nb_id"],
                        "empr_tx_nome"   => $empRow["empr_tx_nome"],
                        "percEndossado"  => 0,
                        "qtdMotoristas"  => 0,
                        "totais"         => $totaisZero
                    ];

                    $linhasEmpresasZeradas .= "<tr>"
                        ."<td class='nomeEmpresa' style='cursor:pointer;' onclick=\"setAndSubmit(".intval($empRow["empr_nb_id"]).")\">".htmlspecialchars($empRow["empr_tx_nome"], ENT_QUOTES)."</td>"
                        ."<td>0%</td>"
                        ."<td>0</td>"
                        ."<td>00:00</td><td>00:00</td><td>00:00</td><td>00:00</td><td>00:00</td><td>00:00</td><td>00:00</td><td>00:00</td><td>00:00</td>"
                        ."</tr>";
                }
                $arquivos = [];
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

            if($modoDetalhe){
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
                    <th data-column='nome' data-order='asc'>Nome da Empresa/Filial</th>
                    <th data-column='percEndossados' data-order='asc'>% Endossados</th>
                    <th data-column='qtdMotoristas' data-order='asc'>Qtd</th>
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

            if(!empty($linhasEmpresasZeradas)){
                echo "<script>document.querySelector('#tabela-empresas tbody').innerHTML = ".json_encode($linhasEmpresasZeradas).";</script>";
            }

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
        
        carregarJS($arquivos, $empresas ?? []);
        rodape();
    }
