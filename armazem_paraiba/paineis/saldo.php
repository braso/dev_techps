<?php
    /* Modo debug
        ini_set("display_errors", 1);
        error_reporting(E_ALL);
    */
     
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

    function carregarJS(array $arquivos, array $empresasSemDados = []){

        $linha = "linha = '<tr>'";
        $modoDetalheJS = (!empty($_POST["empresa"]) && ($_POST["empresa_modo"] ?? "") === "detalhe");
        // $modoTerceirizado é passado via variável JS global antes de carregarJS ser chamado
        if($modoDetalheJS){
            $linha .= "+'<td>'+row.matricula+'</td>'
                    +'<td>'+row.nome+'</td>'
                    +'<td>'+(row.ocupacao?? '')+'</td>'
                    +'<td>'+(row.tipoOperacaoNome?? '')+'</td>'
                    +'<td>'+(row.setorNome?? '')+'</td>'
                    +'<td>'+(row.subsetorNome?? '')+'</td>'
                    +'<td class=\"'+(row.statusEndosso === 'E' ? 'endo' : (row.statusEndosso === 'EP' ? 'endo-parc' : 'nao-endo'))+'\">'
                        +'<strong>'+row.statusEndosso+'</strong>'
                    +'</td>'
                    +'<td>'+(row.jornadaPrevista == '00:00' ? '' : row.jornadaPrevista?? '')+'</td>'
                    +'<td>'+(row.jornadaEfetiva == '00:00' ? '' : row.jornadaEfetiva?? '')+'</td>'
                    +(modoTerceirizado ? '' : '<td>'+(row.HESemanal == '00:00' ? '' : row.HESemanal?? '')+'</td>')
                    +(modoTerceirizado ? '' : '<td>'+(row.HESabado == '00:00' ? '' : row.HESabado?? '')+'</td>')
                    +(modoTerceirizado ? '' : '<td>'+(row.adicionalNoturno == '00:00' ? '' : row.adicionalNoturno?? '')+'</td>')
                    +(modoTerceirizado ? '' : '<td>'+(row.esperaIndenizada == '00:00' ? '' : row.esperaIndenizada?? '')+'</td>')
                    +'<td id=\"'+(row.saldoAnterior > '00:00' ? 'saldo-final' : (row.saldoAnterior === '00:00' ? 'saldo-zero' : 'saldo-negativo'))+'\">'
                    +(row.saldoAnterior?? '')+'</td>'
                    +'<td>'+(row.saldoPeriodo > '00:00' ? '<strong>' + row.saldoPeriodo + '</strong>' : (row.saldoPeriodo ?? ''))+'</td>'
                    +'<td id=\"'+(row.saldoFinal > '00:00' ? 'saldo-final' : (row.saldoFinal === '00:00' ? 'saldo-zero' : 'saldo-negativo'))+'\">'
                    +(row.saldoFinal?? '')+indicador+'</td>'
                +'</tr>';";
        }else{
            $linha .= "+'<td style=\"cursor: pointer;\" onclick=\"setAndSubmit(' + row.empr_nb_id + ')\">'+row.empr_tx_nome+'</td>'
                    +'<td>'+Math.round(row.percEndossado*10000)/100+'%</td>'
                    +'<td>'+row.qtdFuncionarios+'</td>'
                    +'<td>'+(row.totais.jornadaPrevista == '00:00' ? '' : row.totais.jornadaPrevista)+'</td>'
                    +'<td>'+(row.totais.jornadaEfetiva == '00:00' ? '' : row.totais.jornadaEfetiva)+'</td>'
                    +'<td>'+(row.totais.HESemanal == '00:00' ? '' : row.totais.HESemanal)+'</td>'
                    +'<td>'+(row.totais.HESabado == '00:00' ? '' : row.totais.HESabado)+'</td>'
                    +'<td>'+(row.totais.adicionalNoturno == '00:00' ? '' : row.totais.adicionalNoturno)+'</td>'
                    +'<td>'+(row.totais.esperaIndenizada == '00:00' ? '' : row.totais.esperaIndenizada)+'</td>'
                    +'<td id=\"'+(row.totais.saldoAnterior > '00:00' ? 'saldo-final' : (row.totais.saldoAnterior === '00:00' ? 'saldo-zero' : 'saldo-negativo'))+'\" >'
                    +(row.totais.saldoAnterior == '00:00' ? '' : row.totais.saldoAnterior)+'</td>'
                    +'<td>'+(row.totais.saldoPeriodo > '00:00' ? '<strong>' + row.totais.saldoPeriodo + '</strong>' : (row.totais.saldoPeriodo ?? ''))+'</td>'
                    +'<td id=\"'+(row.totais.saldoFinal > '00:00' ? 'saldo-final' : (row.totais.saldoFinal === '00:00' ? 'saldo-zero' : 'saldo-negativo'))+'\">'
                    +(row.totais.saldoFinal ?? '')+indicador+'</td>'
                +'</tr>';";

        }

        $carregarDados = "";
        // Linhas de empresas zeradas (sem JSON no mês) renderizadas via PHP
        $linhasEmpresasZeradas = "";
        foreach($arquivos as $arquivo){
            $carregarDados .= "carregarDados('".$arquivo."');";
        }
        // Quando não há JSONs mas há empresas do banco (sem dados no mês, modo empresa)
        if(empty($arquivos) && !$modoDetalheJS && !empty($empresasSemDados)){
            foreach($empresasSemDados as $emp){
                $linhasEmpresasZeradas .= "<tr>"
                    ."<td style='cursor:pointer;' onclick=\"setAndSubmit(".intval($emp["empr_nb_id"]).")\">".htmlspecialchars($emp["empr_tx_nome"], ENT_QUOTES)."</td>"
                    ."<td>0%</td>"
                    ."<td>0</td>"
                    ."<td>00:00</td><td>00:00</td><td>00:00</td><td>00:00</td><td>00:00</td><td>00:00</td><td>00:00</td><td>00:00</td><td>00:00</td>"
                    ."</tr>";
            }
        }

        echo
            "<form name='myForm' method='post' action='".htmlspecialchars($_SERVER["PHP_SELF"])."'>
                <input type='hidden' name='acao'>
                <input type='hidden' name='campoAcao'>
                <input type='hidden' name='empresa'>
                <input type='hidden' name='empresa_filtro' value='".htmlspecialchars($_POST["empresa_filtro"] ?? "", ENT_QUOTES)."'>
                <input type='hidden' name='empresa_modo'>
                <input type='hidden' name='busca_dataMes'>
                <input type='hidden' name='busca_dataInicio'>
                <input type='hidden' name='busca_dataFim'>
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
                    document.myForm.busca_dataMes.value = document.getElementById('busca_dataMes').value;
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
                    document.myForm.busca_dataMes.value = document.getElementById('busca_dataMes').value;
                    // Restaura a seleção original de empresas do filtro (salva em empresa_filtro)
                    var empresaFiltroInput = document.querySelector('form[name=\"myForm\"] [name=\"empresa_filtro\"]');
                    document.myForm.empresa.value = empresaFiltroInput ? empresaFiltroInput.value : '';
                    // Copia os demais filtros (já populados com $_POST correto)
                    var filtros = ['busca_ocupacao','operacao','busca_setor','busca_subsetor'];
                    filtros.forEach(function(nome){
                        var origem = document.querySelector('.js-filtro-hidden[data-filter-name=\"' + nome + '\"]');
                        var destino = document.querySelector('form[name=\"myForm\"] [name=\"' + nome + '\"]');
                        if(origem && destino){ destino.value = origem.value; }
                    });
                    document.myForm.submit();
                }

                function atualizarPainel(){
                    var empresaInput = document.querySelector('form[name=\"contex_form\"] input[name=\"empresa\"]') || document.getElementById('empresa');
                    document.myForm.empresa.value = empresaInput ? empresaInput.value : '';
                    document.myForm.busca_dataInicio.value = document.getElementById('busca_dataInicio').value;
                    document.myForm.busca_dataFim.value = document.getElementById('busca_dataFim').value;
                    document.myForm.busca_ocupacao.value = document.querySelector('[name=\"busca_ocupacao\"]').value;
                    document.myForm.acao.value = 'atualizar';
                    document.myForm.submit();
                }

                function imprimir(){
                    window.print();
                }

                $(document).ready(function(){
                    var tabela = $('#tabela-empresas tbody');
                    // Normaliza filtros vindos do PHP: aceita vazio ou valor único
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

                    console.log('Filtros ocupacao:', ocupacoesFilter);
                    function carregarDados(urlArquivo){
                        $.ajax({
                            url: urlArquivo + '?v=' + new Date().getTime(),
                            dataType: 'json',
                            success: function(data){
                                var row = {};
                                $.each(data, function(index, item){
                                    row[index] = item;
                                });
                                console.log(row.saldoAnterior);

                                // Normaliza valores das linhas para comparação (trim + lowercase)
                                var rowOcup = normalizarOcupacao(row.ocupacao);
                                var rowOper = (row.tipoOperacao || '').toString().trim().toLowerCase();
                                var rowSet = (row.setor || '').toString().trim().toLowerCase();
                                var rowSub = (row.subsetor || '').toString().trim().toLowerCase();

                                if (
                                    (ocupacoesFilter.length > 0 && rowOcup !== '' && !ocupacoesFilter.includes(rowOcup)) ||
                                    (operacaoFilter.length > 0 && rowOper !== '' && !operacaoFilter.includes(rowOper)) ||
                                    (setorFilter.length > 0 && rowSet !== '' && !setorFilter.includes(rowSet)) ||
                                    (subSetorFilter.length > 0 && rowSub !== '' && !subSetorFilter.includes(rowSub))
                                ) {
                                    return; // pula esta linha se qualquer filtro não permitir
                                }

                                var saldoAnterior = horasParaMinutos(row.saldoAnterior !== undefined ? row.saldoAnterior : row.totais.saldoAnterior);
                                var saldoFinal = horasParaMinutos(row.saldoFinal !== undefined ? row.saldoFinal : row.totais.saldoFinal);
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
                                console.log(row);
                                if(row.idMotorista != undefined){
                                    delete row.idMotorista;
                                }
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
                        return (horasNumeros * 60) + (horasNumeros < 0 ? -minutos : minutos);
                    }
                        
                    // Função para ordenar a tabela
                    function ordenarTabela(coluna, ordem){
                        var linhas = tabela.find('tr').get();
                        
                        linhas.sort(function(a, b){
                            var valorA = $(a).children('td').eq(coluna).text();
                            var valorB = $(b).children('td').eq(coluna).text();

                            // Verifica se os valores estão no formato HHH:mm (inclui de 1 a 3 dígitos nas horas)
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
        include __DIR__.'/../check_permission.php';
        verificaPermissao('/paineis/saldo.php');

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
        
        $setoresSelecionados = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_POST["busca_setor"] ?? ""))))));
        $temSubsetorVinculado = false;
        if (!empty($setoresSelecionados)) {
            $condSetores = implode(',', $setoresSelecionados);
            $rowCount = mysqli_fetch_array(query("SELECT COUNT(*) FROM sbgrupos_documentos WHERE sbgr_tx_status = 'ativo' AND sbgr_nb_idgrup IN (".$condSetores.");"));
            $temSubsetorVinculado = ($rowCount[0] > 0);
        }

        $empresaSelecionadas = [];
        // Em modo detalhe, usa empresa_filtro para popular o checklist (preserva seleção original)
        // Em modo normal, usa empresa diretamente
        $empresaModoAtual = $_POST["empresa_modo"] ?? "";
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
            if(isset($empresaOpcoes[$empresaIdSelecionada])){
                $empresaLabelBotao = $empresaOpcoes[$empresaIdSelecionada];
            }
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
                .htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8')
                ."</label>";
        }
        $selectEmpresa .= "</div></div></div>";

        $ocupacaoSelecionadas = array_values(array_filter(array_map('trim', explode(',', (string)($_POST["busca_ocupacao"] ?? ""))), function($v){ return $v !== ''; }));
        $ocupacaoOpcoes = [
            "Motorista" => "Motorista",
            "Ajudante" => "Ajudante",
            "Funcionário" => "Funcionário",
            "Terceirizado" => "Terceirizado"
        ];
        $selectOcupacao = "<div class='col-sm-2 margin-bottom-5 campo-fit-content'>"
            ."<label>Ocupação</label>"
            ."<div class='filtro-compact' data-filter='busca_ocupacao' data-label='Ocupação' style='position:relative;'>"
            ."<button type='button' class='btn btn-default btn-block js-filtro-toggle' style='display:flex;justify-content:space-between;align-items:center;'>"
            ."<span class='filtro-label'>Ocupação".(count($ocupacaoSelecionadas) > 0 ? " (".count($ocupacaoSelecionadas).")" : "")."</span><span class='caret'></span></button>"
            ."<div class='filtro-dropdown-menu' style='display:none; position:absolute; left:0; right:0; z-index:1050; background:#fff; border:1px solid #d9d9d9; padding:8px; max-height:240px; overflow:auto;'>"
            ."<div style='margin-bottom:8px;'>"
            ."<button type='button' class='btn btn-xs btn-link js-filtro-todos' data-target='busca_ocupacao' data-action='all'>Marcar todos</button>"
            ."<button type='button' class='btn btn-xs btn-link js-filtro-todos' data-target='busca_ocupacao' data-action='none'>Desmarcar todos</button>"
            ."</div>"
            ."<input type='hidden' class='js-filtro-hidden' data-filter-name='busca_ocupacao' name='busca_ocupacao' value='".htmlspecialchars(implode(',', $ocupacaoSelecionadas), ENT_QUOTES, 'UTF-8')."'>";
        foreach($ocupacaoOpcoes as $ocupVal => $ocupLabel){
            $checked = in_array((string)$ocupVal, $ocupacaoSelecionadas, true) ? "checked" : "";
            $selectOcupacao .= "<label style='display:block;margin-bottom:6px;cursor:pointer;'>"
                ."<input type='checkbox' class='js-filtro-checkbox' data-target='busca_ocupacao' value='".htmlspecialchars((string)$ocupVal, ENT_QUOTES, 'UTF-8')."' ".$checked." style='margin-right:6px;'>"
                .htmlspecialchars($ocupLabel, ENT_QUOTES, 'UTF-8')
                ."</label>";
        }
        $selectOcupacao .= "</div></div></div>";

        $operacaoSelecionadas = array_values(array_filter(array_map('trim', explode(',', (string)($_POST["operacao"] ?? ""))), function($v){ return $v !== ''; }));
        $operacaoOpcoes = [];
        $resOperacaoFiltro = query("SELECT oper_nb_id, oper_tx_nome FROM operacao WHERE oper_tx_status = 'ativo' ORDER BY oper_tx_nome ASC");
        while($operacaoRow = mysqli_fetch_assoc($resOperacaoFiltro)){
            $operacaoOpcoes[(string)intval($operacaoRow["oper_nb_id"])] = $operacaoRow["oper_tx_nome"];
        }
        $selectOperacao = "<div class='col-sm-2 margin-bottom-5 campo-fit-content'>"
            ."<label>Cargo</label>"
            ."<div class='filtro-compact' data-filter='operacao' data-label='Cargo' style='position:relative;'>"
            ."<button type='button' class='btn btn-default btn-block js-filtro-toggle' style='display:flex;justify-content:space-between;align-items:center;'>"
            ."<span class='filtro-label'>Cargo".(count($operacaoSelecionadas) > 0 ? " (".count($operacaoSelecionadas).")" : "")."</span><span class='caret'></span></button>"
            ."<div class='filtro-dropdown-menu' style='display:none; position:absolute; left:0; right:0; z-index:1050; background:#fff; border:1px solid #d9d9d9; padding:8px; max-height:240px; overflow:auto;'>"
            ."<div style='margin-bottom:8px;'>"
            ."<button type='button' class='btn btn-xs btn-link js-filtro-todos' data-target='operacao' data-action='all'>Marcar todos</button>"
            ."<button type='button' class='btn btn-xs btn-link js-filtro-todos' data-target='operacao' data-action='none'>Desmarcar todos</button>"
            ."</div>"
            ."<input type='hidden' class='js-filtro-hidden' data-filter-name='operacao' name='operacao' value='".htmlspecialchars(implode(',', $operacaoSelecionadas), ENT_QUOTES, 'UTF-8')."'>";
        foreach($operacaoOpcoes as $operVal => $operLabel){
            $checked = in_array((string)$operVal, $operacaoSelecionadas, true) ? "checked" : "";
            $selectOperacao .= "<label style='display:block;margin-bottom:6px;cursor:pointer;'>"
                ."<input type='checkbox' class='js-filtro-checkbox' data-target='operacao' value='".htmlspecialchars((string)$operVal, ENT_QUOTES, 'UTF-8')."' ".$checked." style='margin-right:6px;'>"
                .htmlspecialchars($operLabel, ENT_QUOTES, 'UTF-8')
                ."</label>";
        }
        $selectOperacao .= "</div></div></div>";

        $setorSelecionados = array_values(array_filter(array_map('trim', explode(',', (string)($_POST["busca_setor"] ?? ""))), function($v){ return $v !== ''; }));
        $setorOpcoes = [];
        $resSetorFiltro = query("SELECT grup_nb_id, grup_tx_nome FROM grupos_documentos WHERE grup_tx_status = 'ativo' ORDER BY grup_tx_nome ASC");
        while($setorRow = mysqli_fetch_assoc($resSetorFiltro)){
            $setorOpcoes[(string)intval($setorRow["grup_nb_id"])] = $setorRow["grup_tx_nome"];
        }
        $selectSetor = "<div class='col-sm-2 margin-bottom-5 campo-fit-content'>"
            ."<label>Setor</label>"
            ."<div class='filtro-compact' data-filter='busca_setor' data-label='Setor' style='position:relative;'>"
            ."<button type='button' class='btn btn-default btn-block js-filtro-toggle' style='display:flex;justify-content:space-between;align-items:center;'>"
            ."<span class='filtro-label'>Setor".(count($setorSelecionados) > 0 ? " (".count($setorSelecionados).")" : "")."</span><span class='caret'></span></button>"
            ."<div class='filtro-dropdown-menu' style='display:none; position:absolute; left:0; right:0; z-index:1050; background:#fff; border:1px solid #d9d9d9; padding:8px; max-height:240px; overflow:auto;'>"
            ."<div style='margin-bottom:8px;'>"
            ."<button type='button' class='btn btn-xs btn-link js-filtro-todos' data-target='busca_setor' data-action='all'>Marcar todos</button>"
            ."<button type='button' class='btn btn-xs btn-link js-filtro-todos' data-target='busca_setor' data-action='none'>Desmarcar todos</button>"
            ."</div>"
            ."<input type='hidden' class='js-filtro-hidden' data-filter-name='busca_setor' name='busca_setor' value='".htmlspecialchars(implode(',', $setorSelecionados), ENT_QUOTES, 'UTF-8')."'>";
        foreach($setorOpcoes as $setorVal => $setorLabel){
            $checked = in_array((string)$setorVal, $setorSelecionados, true) ? "checked" : "";
            $selectSetor .= "<label style='display:block;margin-bottom:6px;cursor:pointer;'>"
                ."<input type='checkbox' class='js-filtro-checkbox' data-target='busca_setor' value='".htmlspecialchars((string)$setorVal, ENT_QUOTES, 'UTF-8')."' ".$checked." style='margin-right:6px;'>"
                .htmlspecialchars($setorLabel, ENT_QUOTES, 'UTF-8')
                ."</label>";
        }
        $selectSetor .= "</div></div></div>";

        $subsetorSelecionados = array_values(array_filter(array_map('trim', explode(',', (string)($_POST["busca_subsetor"] ?? ""))), function($v){ return $v !== ''; }));
        $selectSubsetor = "";
        if($temSubsetorVinculado){
            $subsetorOpcoes = [];
            $condSubsetor = implode(',', $setoresSelecionados);
            $resSubsetorFiltro = query("SELECT sbgr_nb_id, sbgr_tx_nome FROM sbgrupos_documentos WHERE sbgr_tx_status = 'ativo' AND sbgr_nb_idgrup IN (".$condSubsetor.") ORDER BY sbgr_tx_nome ASC");
            while($subsetorRow = mysqli_fetch_assoc($resSubsetorFiltro)){
                $subsetorOpcoes[(string)intval($subsetorRow["sbgr_nb_id"])] = $subsetorRow["sbgr_tx_nome"];
            }

            $selectSubsetor = "<div class='col-sm-2 margin-bottom-5 campo-fit-content'>"
                ."<label>Subsetor</label>"
                ."<div class='filtro-compact' data-filter='busca_subsetor' data-label='Subsetor' style='position:relative;'>"
                ."<button type='button' class='btn btn-default btn-block js-filtro-toggle' style='display:flex;justify-content:space-between;align-items:center;'>"
                ."<span class='filtro-label'>Subsetor".(count($subsetorSelecionados) > 0 ? " (".count($subsetorSelecionados).")" : "")."</span><span class='caret'></span></button>"
                ."<div class='filtro-dropdown-menu' style='display:none; position:absolute; left:0; right:0; z-index:1050; background:#fff; border:1px solid #d9d9d9; padding:8px; max-height:240px; overflow:auto;'>"
                ."<div style='margin-bottom:8px;'>"
                ."<button type='button' class='btn btn-xs btn-link js-filtro-todos' data-target='busca_subsetor' data-action='all'>Marcar todos</button>"
                ."<button type='button' class='btn btn-xs btn-link js-filtro-todos' data-target='busca_subsetor' data-action='none'>Desmarcar todos</button>"
                ."</div>"
                ."<input type='hidden' class='js-filtro-hidden' data-filter-name='busca_subsetor' name='busca_subsetor' value='".htmlspecialchars(implode(',', $subsetorSelecionados), ENT_QUOTES, 'UTF-8')."'>";
            foreach($subsetorOpcoes as $subsetorVal => $subsetorLabel){
                $checked = in_array((string)$subsetorVal, $subsetorSelecionados, true) ? "checked" : "";
                $selectSubsetor .= "<label style='display:block;margin-bottom:6px;cursor:pointer;'>"
                    ."<input type='checkbox' class='js-filtro-checkbox' data-target='busca_subsetor' value='".htmlspecialchars((string)$subsetorVal, ENT_QUOTES, 'UTF-8')."' ".$checked." style='margin-right:6px;'>"
                    .htmlspecialchars($subsetorLabel, ENT_QUOTES, 'UTF-8')
                    ."</label>";
            }
            $selectSubsetor .= "</div></div></div>";
        }

        $campos = [
            $selectEmpresa,
            $campoAcao,
            $selectOcupacao,
            $selectOperacao,
            $selectSetor,
        ];
        if($temSubsetorVinculado){
            $campos[] = $selectSubsetor;
        }
        $campos[] = campo_mes("Mês*", "busca_dataMes", ($_POST["busca_dataMes"]?? date("Y-m")), 2);



        $botao_volta = "";
        if(!empty($_POST["empresa"]) && ($_POST["empresa_modo"] ?? "") === "detalhe"){
            $botao_volta = "<button class='btn default' type='button' onclick='voltarParaEmpresas()'>Voltar</button>";
        }
        $botao_imprimir = "<button class='btn default' type='button' onclick='enviarDados()'>Imprimir</button>
        <script>
        function enviarDados() {
            var data = '" . $_POST["busca_dataMes"] . "';
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
                            (name === 'busca_data' ? data : 'saldo');
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

        $buttons = [
            botao("Buscar", "enviarForm()", "", "", "", "", "btn btn-info"),
            $botao_imprimir,
            $botao_volta
        ];


        echo abre_form();
        echo campo_hidden("reloadOnly", "");
        echo linha_form($campos);
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
            // Atualiza o título do filtro com a contagem de itens selecionados
            function atualizarTitulo(target, count){
                var wrap = document.querySelector('.filtro-compact[data-filter="' + target + '"]');
                if(!wrap){ return; }
                var label = wrap.querySelector('.filtro-label');
                if(!label){ return; }
                var baseLabel = wrap.getAttribute('data-label') || target;
                if(target === 'empresa' && count === 1){
                    var checkedEmpresa = wrap.querySelector('input.js-filtro-checkbox[data-target="empresa"]:checked');
                    var empresaTexto = '';
                    if(checkedEmpresa && checkedEmpresa.parentElement){
                        empresaTexto = checkedEmpresa.parentElement.textContent.trim();
                    }
                    label.textContent = empresaTexto || baseLabel;
                    return;
                }
                label.textContent = baseLabel + (count > 1 ? ' (' + count + ')' : '');
            }

            function sincronizarDoHidden(target){
                var hidden = document.querySelector('.js-filtro-hidden[data-filter-name="' + target + '"]');
                if(!hidden){ return; }
                var vals = hidden.value ? hidden.value.split(',') : [];
                var elems = document.querySelectorAll('input.js-filtro-checkbox[data-target="' + target + '"]');
                elems.forEach(function(chk){
                    chk.checked = vals.indexOf(chk.value) !== -1;
                });
                // Atualiza plugin de estilização (Uniform / iCheck) se presente
                if(window.jQuery && typeof jQuery.uniform !== 'undefined' && typeof jQuery.uniform.update === 'function'){
                    jQuery.uniform.update(jQuery(elems));
                }
                atualizarTitulo(target, vals.filter(function(v){ return v !== ''; }).length);
            }

            document.querySelectorAll('.js-filtro-hidden').forEach(function(hidden){
                var name = hidden.getAttribute('data-filter-name');
                if(name){
                    sincronizarDoHidden(name);
                }
            });

            document.querySelectorAll('.js-filtro-toggle').forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    e.stopPropagation();
                    var menu = btn.parentNode.querySelector('.filtro-dropdown-menu');
                    var isOpen = menu && menu.style.display === 'block';
                    fecharDropdowns();
                    if(menu){ menu.style.display = isOpen ? 'none' : 'block'; }
                });
            });

            document.querySelectorAll('.filtro-dropdown-menu').forEach(function(menu){
                menu.addEventListener('click', function(e){ e.stopPropagation(); });
            });

            document.querySelectorAll('.js-filtro-checkbox').forEach(function(chk){
                chk.addEventListener('change', function(){
                    var target = chk.getAttribute('data-target');
                    if(target){
                        atualizarHidden(target);
                    }
                });
            });

            document.querySelectorAll('.js-filtro-todos').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var target = btn.getAttribute('data-target');
                    if(!target){ return; }
                    var marcar = btn.getAttribute('data-action') === 'all';
                    var boxes = document.querySelectorAll('input.js-filtro-checkbox[data-target="' + target + '"]');
                    boxes.forEach(function(chk){
                        chk.checked = marcar;
                    });
                    if(window.jQuery && typeof jQuery.uniform !== 'undefined' && typeof jQuery.uniform.update === 'function'){
                        jQuery.uniform.update(jQuery(boxes));
                    }
                    atualizarHidden(target);
                });
            });

            document.addEventListener('click', function(){
                fecharDropdowns();
            });
        })();
        </script>
HTML;

        
        $arquivos = [];
        $empresas = [];
        $logoEmpresa = "";
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
        $modoDetalhe = false;
        $modoTerceirizado = false;
        
        
        if(!empty($_POST["acao"]) && $_POST["acao"] == "buscar" && empty($_POST["reloadOnly"])){
            $path .= "/".$_POST["busca_dataMes"];
            $modoDetalhe = (!empty($_POST["empresa"]) && ($_POST["empresa_modo"] ?? "") === "detalhe");
            if($modoDetalhe){
                // Painel dos saldos dos motoristas de uma ou mais empresas (CSV)
                $empresaIds = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$_POST["empresa"])) )));
                if(empty($empresaIds)){
                    $empresaIds = [intval($_POST["empresa"])];
                }

                $normalizarTxt = function($valor){
                    return mb_strtolower(trim((string)$valor));
                };
                $normalizarOcupacao = function($ocupacao) use ($normalizarTxt){
                    $txt = $normalizarTxt($ocupacao);
                    if($txt === "tercerizado"){
                        return "terceirizado";
                    }
                    return $txt;
                };
                $parseFiltroCsv = function($raw, $normalizador = null){
                    $itens = array_values(array_filter(array_map('trim', explode(',', (string)$raw)), function($v){ return $v !== ''; }));
                    if($normalizador !== null){
                        $itens = array_map($normalizador, $itens);
                    }
                    return $itens;
                };

                $ocupacaoFiltro = $parseFiltroCsv($_POST["busca_ocupacao"] ?? "", $normalizarOcupacao);
                $operacaoFiltro = $parseFiltroCsv($_POST["operacao"] ?? "", $normalizarTxt);
                $setorFiltro = $parseFiltroCsv($_POST["busca_setor"] ?? "", $normalizarTxt);
                $subsetorFiltro = $parseFiltroCsv($_POST["busca_subsetor"] ?? "", $normalizarTxt);

                $motoristas = [];
                $empresaNomes = [];
                $latestMTime = 0;
                $periodoRelatorioBruto = null;

                foreach($empresaIds as $empresaId){
                    $aEmpresa = mysqli_fetch_assoc(query(
                        "SELECT * FROM empresa
                        WHERE empr_tx_status = 'ativo'
                            AND empr_nb_id = {$empresaId}
                        LIMIT 1;"
                    ));
                    if(empty($aEmpresa)){
                        continue;
                    }

                    $pathEmpresa = $path . "/" . $aEmpresa["empr_nb_id"];
                    if(!is_dir($pathEmpresa)){
                        continue;
                    }

                    $encontrado = true;
                    $empresaNomes[] = $aEmpresa["empr_tx_nome"];
                    $jsonEmpresaPath = $pathEmpresa . "/empresa_" . $aEmpresa["empr_nb_id"] . ".json";
                    if(is_file($jsonEmpresaPath)){
                        $mtime = filemtime($jsonEmpresaPath);
                        if($mtime > $latestMTime){
                            $latestMTime = $mtime;
                        }
                        if($periodoRelatorioBruto === null){
                            $periodoTmp = json_decode(file_get_contents($jsonEmpresaPath), true);
                            if(!empty($periodoTmp["dataInicio"]) && !empty($periodoTmp["dataFim"])){
                                $periodoRelatorioBruto = [
                                    "dataInicio" => $periodoTmp["dataInicio"],
                                    "dataFim" => $periodoTmp["dataFim"]
                                ];
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
                    $dataAtual = date("d/m/Y");
                    if($dataArquivoFmt != $dataAtual){
                        $alertaEmissao = "<span style='color: red; border: 2px solid; padding: 2px; border-radius: 4px;'>
                        <i style='color:red; margin-right: 5px;' title='As informações do painel não correspondem à data de hoje.' class='fa fa-warning'></i>";
                    } else {
                        $alertaEmissao = "<span>";
                    }

                    $dataEmissao = $alertaEmissao . " Atualizado em: ".($latestMTime > 0 ? date("d/m/Y H:i", $latestMTime) : "")."</span>";

                    if($periodoRelatorioBruto !== null){
                        $periodoRelatorio["dataInicio"] = DateTime::createFromFormat("Y-m-d", $periodoRelatorioBruto["dataInicio"])->format("d/m");
                        $periodoRelatorio["dataFim"] = DateTime::createFromFormat("Y-m-d", $periodoRelatorioBruto["dataFim"])->format("d/m");
                    }

                    foreach($arquivos as $arquivo){
                        $json = json_decode(file_get_contents($arquivo), true);
                        $json["dataAtualizacao"] = date("d/m/Y H:i", filemtime($arquivo));
                        foreach($totais as $key => $value){
                            $totais[$key] = operarHorarios([$totais[$key], $json[$key]], "+");
                        }
                        $motoristas[] = $json;
                    }

                    $totais["empresaNome"] = count($empresaNomes) > 1 ? "Múltiplas empresas" : ($empresaNomes[0] ?? "");
                    $hasDetailFilter = (!empty($_POST["busca_ocupacao"]) || !empty($_POST["operacao"]) || !empty($_POST["busca_setor"]) || !empty($_POST["busca_subsetor"]));
                    $totaisFiltrados = [];
                    foreach($totais as $k => $v){
                        if($k !== "empresaNome"){
                            $totaisFiltrados[$k] = "00:00";
                        }
                    }

                    // Detecta se todos os funcionários (após filtro) são terceirizados
                    $modoTerceirizado = false;
                    $countFiltrados = 0;
                    $countTerceirizados = 0;
                    foreach($motoristas as $saldosMotorista){
                        $rowOcupacao = $normalizarOcupacao($saldosMotorista["ocupacao"] ?? "");
                        $rowOperacao = $normalizarTxt($saldosMotorista["tipoOperacao"] ?? "");
                        $rowSetor = $normalizarTxt($saldosMotorista["setor"] ?? "");
                        $rowSubsetor = $normalizarTxt($saldosMotorista["subsetor"] ?? "");
                        if((!empty($ocupacaoFiltro) && $rowOcupacao !== "" && !in_array($rowOcupacao, $ocupacaoFiltro, true))
                            || (!empty($operacaoFiltro) && $rowOperacao !== "" && !in_array($rowOperacao, $operacaoFiltro, true))
                            || (!empty($setorFiltro) && $rowSetor !== "" && !in_array($rowSetor, $setorFiltro, true))
                            || (!empty($subsetorFiltro) && $rowSubsetor !== "" && !in_array($rowSubsetor, $subsetorFiltro, true))){
                            continue;
                        }
                        $countFiltrados++;
                        if($rowOcupacao === "terceirizado"){ $countTerceirizados++; }
                    }
                    if($countFiltrados > 0 && $countFiltrados === $countTerceirizados){
                        $modoTerceirizado = true;
                    }
                    // Também ativa modo terceirizado se o filtro de ocupação só tem terceirizado
                    if(!$modoTerceirizado && count($ocupacaoFiltro) > 0 && $ocupacaoFiltro === ["terceirizado"]){
                        $modoTerceirizado = true;
                    }

                    foreach($motoristas as $saldosMotorista){
                        $rowOcupacao = $normalizarOcupacao($saldosMotorista["ocupacao"] ?? "");
                        $rowOperacao = $normalizarTxt($saldosMotorista["tipoOperacao"] ?? "");
                        $rowSetor = $normalizarTxt($saldosMotorista["setor"] ?? "");
                        $rowSubsetor = $normalizarTxt($saldosMotorista["subsetor"] ?? "");

                        if((!empty($ocupacaoFiltro) && $rowOcupacao !== "" && !in_array($rowOcupacao, $ocupacaoFiltro, true))
                            || (!empty($operacaoFiltro) && $rowOperacao !== "" && !in_array($rowOperacao, $operacaoFiltro, true))
                            || (!empty($setorFiltro) && $rowSetor !== "" && !in_array($rowSetor, $setorFiltro, true))
                            || (!empty($subsetorFiltro) && $rowSubsetor !== "" && !in_array($rowSubsetor, $subsetorFiltro, true))){
                            continue;
                        }

                        $statusEndosso = $saldosMotorista["statusEndosso"] ?? "";
                        if(isset($contagemEndossos[$statusEndosso])){
                            $contagemEndossos[$statusEndosso]++;
                        }
                        foreach($totaisFiltrados as $key => $value){
                            $totaisFiltrados[$key] = operarHorarios([$totaisFiltrados[$key], $saldosMotorista[$key]], "+");
                        }
                        if(($saldosMotorista["saldoFinal"] ?? "") === "00:00"){
                            $contagemSaldos["meta"]++;
                        }elseif(!empty($saldosMotorista["saldoFinal"]) && $saldosMotorista["saldoFinal"][0] == "-"){
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
                    "SELECT empr_tx_logo FROM empresa
                    WHERE empr_tx_status = 'ativo'
                        AND empr_tx_Ehmatriz = 'sim'
                    LIMIT 1;"
                ))["empr_tx_logo"];//Utilizado no HTML.

                $logoEmpresa = $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/".$logoEmpresa;

                
                $encontrado = true; // Sempre mostra o grid, mesmo sem dados no mês
                $dataEmissao = "";

                // IDs das empresas selecionadas no filtro
                $empresasFiltroIds = [];
                if(!empty($_POST["empresa"]) && ($_POST["empresa_modo"] ?? "") !== "detalhe"){
                    $empresasFiltroIds = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$_POST["empresa"])), function($v){ return $v > 0; })));
                }

                if(is_dir($path) && is_file($path."/empresas.json")){
                    $arquivoGeral = $path."/empresas.json";

                    $dataArquivo = date("d/m/Y", filemtime($arquivoGeral));
                    if($dataArquivo != date("d/m/Y")){
                        $alertaEmissao = "<i style='color:red;' title='As informações do painel não correspondem à data de hoje.' class='fa fa-warning'></i>";
                    } else {
                        $alertaEmissao = "";
                    }

                    $dataEmissao = $alertaEmissao ." Atualizado em: ".date("d/m/Y H:i", filemtime($arquivoGeral));
                    $arquivoGeral = json_decode(file_get_contents($arquivoGeral), true);

                    $periodoRelatorio = [
                        "dataInicio" => $arquivoGeral["dataInicio"],
                        "dataFim" => $arquivoGeral["dataFim"]
                    ];
                    $periodoRelatorio["dataInicio"] = DateTime::createFromFormat("Y-m-d", $periodoRelatorio["dataInicio"])->format("d/m");
                    $periodoRelatorio["dataFim"] = DateTime::createFromFormat("Y-m-d", $periodoRelatorio["dataFim"])->format("d/m");

                    // Lê os JSONs das empresas da pasta do mês
                    $pastaSaldos = dir($path);
                    while($arquivo = $pastaSaldos->read()){
                        if(!empty($arquivo) && !in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresas"))){
                            if(!empty($empresasFiltroIds) && !in_array(intval($arquivo), $empresasFiltroIds, true)){
                                continue;
                            }
                            $arquivoJson = $path."/".$arquivo."/empresa_".$arquivo.".json";
                            if(!is_file($arquivoJson)){ continue; }
                            $arquivos[] = $arquivoJson;
                            $json = json_decode(file_get_contents($arquivoJson), true);
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
                        if($empresa["percEndossado"] === 1){
                            $contagemEndossos["E"]++;
                        }elseif($empresa["percEndossado"] === 0){
                            $contagemEndossos["N"]++;
                        }else{
                            $contagemEndossos["EP"]++;
                        }
                    }
                }else{
                    // Sem dados no mês: monta grid com empresas do banco todas zeradas
                    $dataEmissao = "<i style='color:orange;' title='Sem dados gerados para este mês.'  class='fa fa-warning'></i> Sem dados para este mês";

                    $condEmpresasBanco = "";
                    if(!empty($empresasFiltroIds)){
                        $condEmpresasBanco = " AND empr_nb_id IN (".implode(',', $empresasFiltroIds).")";
                    }
                    $resEmpresasBanco = query("SELECT empr_nb_id, empr_tx_nome FROM empresa WHERE empr_tx_status = 'ativo'".$condEmpresasBanco." ORDER BY empr_tx_nome ASC");
                    $totaisZero = [
                        "jornadaPrevista" => "00:00", "jornadaEfetiva" => "00:00",
                        "HESemanal" => "00:00", "HESabado" => "00:00",
                        "adicionalNoturno" => "00:00", "esperaIndenizada" => "00:00",
                        "saldoAnterior" => "00:00", "saldoPeriodo" => "00:00", "saldoFinal" => "00:00"
                    ];
                    while($empRow = mysqli_fetch_assoc($resEmpresasBanco)){
                        $empresas[] = [
                            "empr_nb_id"     => $empRow["empr_nb_id"],
                            "empr_tx_nome"   => $empRow["empr_tx_nome"],
                            "percEndossado"  => 0,
                            "qtdFuncionarios"=> 0,
                            "totais"         => $totaisZero
                        ];
                    }
                    // Cria arquivos virtuais (array vazio, carregarDados não será chamado)
                    $arquivos = [];
                }
            }
        }

        [$percEndosso["E"], $percEndosso["EP"], $percEndosso["N"]] = calcPercs(array_values($contagemEndossos));
        [$performance["positivos"], $performance["meta"], $performance["negativos"]] = calcPercs(array_values($contagemSaldos));

        echo 
            "<script>
                var modoTerceirizado = ".($modoTerceirizado ? 'true' : 'false').";
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
                $rowTotais .= 
                    "<th colspan='2'>".$totais["empresaNome"]."</th>"
                    ."<th colspan='1'></th>"
                    ."<th colspan='1'></th>"
                    ."<th colspan='1'></th>"
                    ."<th colspan='1'></th>"
                    ."<th colspan='1'></th>"
                    ."<th colspan='1'>".($hasDetailFilter ? $totaisFiltrados["jornadaPrevista"] : $totais["jornadaPrevista"])."</th>"
                    ."<th colspan='1'>".($hasDetailFilter ? $totaisFiltrados["jornadaEfetiva"] : $totais["jornadaEfetiva"])."</th>"
                    .(!$modoTerceirizado ? "<th colspan='1'>".($hasDetailFilter ? $totaisFiltrados["HESemanal"] : $totais["HESemanal"])."</th>" : "")
                    .(!$modoTerceirizado ? "<th colspan='1'>".($hasDetailFilter ? $totaisFiltrados["HESabado"] : $totais["HESabado"])."</th>" : "")
                    .(!$modoTerceirizado ? "<th colspan='1'>".($hasDetailFilter ? $totaisFiltrados["adicionalNoturno"] : $totais["adicionalNoturno"])."</th>" : "")
                    .(!$modoTerceirizado ? "<th colspan='1'>".($hasDetailFilter ? $totaisFiltrados["esperaIndenizada"] : $totais["esperaIndenizada"])."</th>" : "")
                    ."<th colspan='1'>".($hasDetailFilter ? $totaisFiltrados["saldoAnterior"] : $totais["saldoAnterior"])."</th>"
                    ."<th colspan='1'>".($hasDetailFilter ? $totaisFiltrados["saldoPeriodo"] : $totais["saldoPeriodo"])."</th>"
                    ."<th colspan='1'>".($hasDetailFilter ? $totaisFiltrados["saldoFinal"] : $totais["saldoFinal"])."</th>";
                ;

                $rowTitulos .= 
                    "<th class='matricula'>Matrícula</th>
                    <th class='nome'>Nome</th>
                    <th class='ocupacao'>Ocupação</th>
                    <th class='operacao'>Cargo</th>
                    <th class='operacao'>Setor</th>
                    <th class='operacao'>SubSetor</th>
                    <th class='status'>Status Endosso</th>
                    <th class='jornadaPrevista'>Jornada Prevista</th>
                    <th class='jornadaEfetiva'>Jornada Efetiva</th>"
                    .(!$modoTerceirizado ? "<th class='HESemanal'>H.E. Semanal</th>" : "")
                    .(!$modoTerceirizado ? "<th class='HEEx'>H.E. Ex.</th>" : "")
                    .(!$modoTerceirizado ? "<th class='adicionalNoturno'>Adicional Noturno</th>" : "")
                    .(!$modoTerceirizado ? "<th class='esperaIndenizada'>Espera Indenizada</th>" : "")
                    ."<th class='saldoAnterior'>Saldo Anterior</th>
                    <th class='saldoPeriodo'>Saldo Período</th>
                    <th class='saldoFinal'>Saldo Bruto</th>"
                ;

                // $rowTotais .= 
                //     "<th colspan='2'>{$totais["empresaNome"]}</th>
                //     <th colspan='1'></th>
                //     <th colspan='1'></th>
                //     <th colspan='1'>{$totais["jornadaPrevista"]}</th>
                //     <th colspan='1'>{$totais["jornadaEfetiva"]}</th>
                //     <th colspan='1'>{$totais["HESemanal"]}</th>
                //     <th colspan='1'>{$totais["HESabado"]}</th>
                //     <th colspan='1'>{$totais["adicionalNoturno"]}</th>
                //     <th colspan='1'>{$totais["esperaIndenizada"]}</th>
                //     <th colspan='1'>{$totais["saldoAnterior"]}</th>
                //     <th colspan='1'>{$totais["saldoPeriodo"]}</th>
                //     <th colspan='1'>{$totais["saldoFinal"]}</th>
                //     <th colspan='1'></th>
                //     <th colspan='1'></th>";
                // ;

                // $rowTitulos .= 
                //     "<th class='matricula'>Matrícula</th>
                //     <th class='nome'>Nome</th>
                //     <th class='ocupacao'>Ocupação</th>
                //     <th class='status'>Status Endosso</th>
                //     <th class='jornadaPrevista'>Jornada Prevista</th>
                //     <th class='jornadaEfetiva'>Jornada Efetiva</th>
                //     <th class='HESemanal'>H.E. Semanal</th>
                //     <th class='HEEx'>H.E. Ex.</th>
                //     <th class='adicionalNoturno'>Adicional Noturno</th>
                //     <th class='esperaIndenizada'>Espera Indenizada</th>
                //     <th class='saldoAnterior'>Saldo Anterior</th>
                //     <th class='saldoPeriodo'>Saldo Período</th>
                //     <th class='saldoFinal'>Saldo Final</th>
                //     <th class='saldoFinal'>Faltas</th>
                //     <th class='saldoFinal'>Atrasos</th>"
                // ;
            }else{

                $totais["HESemanal"] = ($totais["HESemanal"] == "00:00")? "": $totais["HESemanal"];
                $totais["HESabado"] = ($totais["HESabado"] == "00:00")? "": $totais["HESabado"];
                $totais["adicionalNoturno"] = ($totais["adicionalNoturno"] == "00:00")? "": $totais["adicionalNoturno"];
                $totais["esperaIndenizada"] = ($totais["esperaIndenizada"] == "00:00")? "": $totais["esperaIndenizada"];
                $totais["saldoAnterior"] = ($totais["saldoAnterior"] == "00:00")? "": $totais["saldoAnterior"];
                $totais["saldoPeriodo"] = ($totais["saldoPeriodo"] == "00:00")? "": $totais["saldoPeriodo"];
                $totais["saldoFinal"] = ($totais["saldoFinal"] == "00:00")? "": $totais["saldoFinal"];

                $rowTotais .= 
                    "<th colspan='1'></th>
                    <th colspan='1'></th>
                    <th colspan='1'></th>
                    <th colspan='1'>{$totais["jornadaPrevista"]}</th>
                    <th colspan='1'>{$totais["jornadaEfetiva"]}</th>
                    <th colspan='1'>{$totais["HESemanal"]}</th>
                    <th colspan='1'>{$totais["HESabado"]}</th>
                    <th colspan='1'>{$totais["adicionalNoturno"]}</th>
                    <th colspan='1'>{$totais["esperaIndenizada"]}</th>
                    <th colspan='1'>{$totais["saldoAnterior"]}</th>
                    <th colspan='1'>{$totais["saldoPeriodo"]}</th>
                    <th colspan='1'>{$totais["saldoFinal"]}</th>"
                ;

                $rowTitulos .= 
                    "<th data-column='nome' data-order='asc'>Nome da Empresa/Filial</th>
                    <th data-column='percEndossados' data-order='asc'>% Endossados</th>
                    <th data-column='qtdMotoristas' data-order='asc'>Qtd</th>
                    <th data-column='jornadaPrevista' data-order='asc'>Jornada Prevista</th>
                    <th data-column='JornadaEfetiva' data-order='asc'>Jornada Efetiva</th>
                    <th data-column='HESemanal' data-order='asc'>H.E. Semanal</th>
                    <th data-column='HEEx' data-order='asc'>H.E. Ex.</th>
                    <th data-column='adicionalNoturno' data-order='asc'>Adicional Noturno</th>
                    <th data-column='esperaIndenizada' data-order='asc'>Espera Indenizada</th>
                    <th data-column='saldoAnterior' data-order='asc'>Saldo Anterior</th>
                    <th data-column='saldoPeriodo' data-order='asc'>Saldo Periodo</th>
                    <th data-column='saldoFinal' data-order='asc'>Saldo Final</th>"
                ;
            }
            $rowTotais .= "</tr>";
            $rowTitulos .= "</tr>";

            $titulo = "Geral de saldo";
            include_once "painel_html.php";

            // Injeta linhas zeradas diretamente quando não há JSONs no mês
            if(!empty($linhasEmpresasZeradas)){
                echo "<script>document.querySelector('#tabela-empresas tbody').innerHTML = ".json_encode($linhasEmpresasZeradas).";</script>";
            }

            echo 
                "<div class='script'>
                    <script>"
                        .($modoDetalhe ? "document.getElementById('tabela1').style.display = 'table';": "")
                        ."var porcentagemEndoTds = document.getElementsByClassName('porcentagemEndo')[0].getElementsByTagName('td');
                        var porcentagemNaEndoTds = document.getElementsByClassName('porcentagemNaEndo')[0].getElementsByTagName('td');
                        var porcentagemEndoPcTds = document.getElementsByClassName('porcentagemEndoPc')[0].getElementsByTagName('td');
                        
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
            if(!empty($_POST["acao"]) && $_POST["acao"] == "buscar" && $modoDetalhe){
                set_status("Não possui dados desse mês para esta empresa");
                echo "<script>alert('Não Possui dados desse mês para esta empresa')</script>";
            }
        }
        
        carregarJS($arquivos, $empresas ?? []);
        rodape();
    }
