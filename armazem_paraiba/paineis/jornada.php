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

    function carregarJS(array $arquivos, $exibirEmpresa, $exibirOcupacao, $exibirCargo, $exibirSetor, $exibirSubSetor) {

        $linha = "
        var inicioEsc = (item.inicioEscala && item.inicioEscala !== '00:00' && item.inicioEscala !== '00:00:00') ? item.inicioEscala : '--:--';
        var fimEsc = (item.fimEscala && item.fimEscala !== '00:00' && item.fimEscala !== '00:00:00') ? item.fimEscala : '--:--';
        var escalaShow = (inicioEsc === '--:--' && fimEsc === '--:--') ? '<span class=\"sem-escala-indicador\"></span><strong>----</strong>' : inicioEsc + ' - ' + fimEsc;
        
        var classeLinha = '';
        if(jornadaMinutos > 24 * 60){
            classeLinha = 'row-jornada-critica';
        }

        if (jornadaPrevistaMinutos === 0 && !isNaN(jornadaEfetivaMinutosCalc) && jornadaEfetivaMinutosCalc > 0) {
            classeLinha += ' row-sem-jornada-prevista';
        }

        if(jornadaPrevistaMinutos > 0 && !isNaN(jornadaEfetivaMinutosCalc) && jornadaEfetivaMinutosCalc > jornadaPrevistaMinutos){
             classeLinha += ' row-hora-extra';
        }

        linha = '<tr class=\"'+classeLinha+'\">'
                    +'<td style=\'text-align: center;\'>'+item.matricula+'</td>'
                    +'<td style=\'text-align: center;\'>'+item.nome+'</td>'";
        if ($exibirEmpresa) {
            $linha .= "+'<td style=\'text-align: center;\'>'+(item.empresaNome || '')+'</td>'";
        }
        if ($exibirOcupacao) {
            $linha .= "+'<td style=\'text-align: center;\'>'+item.ocupacao+'</td>'";
        }
        $linha .= "+'<td style=\'text-align: center;\'>'+(item.parametro || '')+'</td>'";
        if ($exibirCargo) {
            $linha .= "+'<td style=\'text-align: center;\'>'+item.tipoOperacaoNome+'</td>'";
        }
        if ($exibirSetor) {
            $linha .= "+'<td style=\'text-align: center;\'>'+(item.setorNome || item.grup_tx_nome || item.setor || item.enti_setor_id || '')+'</td>'";
        }
        if ($exibirSubSetor) {
            $linha .= "+'<td style=\'text-align: center;\'>'+(item.subsetorNome || item.sbgr_tx_nome || item.subsetor || item.enti_subSetor_id || '')+'</td>'";
        }
        $linha .= "+'<td class = \'jornada inicio-jornada-click\' data-matricula=\"'+item.matricula+'\" data-data=\"'+item.data+'\">'+item.data+' '+item.diaSemana+' '+ultimoValor+'</td>'
                        +'<td class = \'jornada\'>'+escalaShow+'</td>'
                        +'<td class = \'jornada\'>'+(item.atraso && item.atraso.trim() !== '00:00' && item.atraso.trim() !== '0:00' ? item.atraso : '<strong>----</strong>')+'</td>'
                        +'<td class ='+css+'>'+jornada+'</td>'
                        +'<td class = \'jornada\'>'+jornadaPrevista+'</td>'
                        +'<td class ='+jornadaEfetivaCor+'>'+jornadaEfetiva+'</td>'
                        +'<td class = \'jornada\'>'+(item.refeicao && item.refeicao.trim() !== '00:00' && item.refeicao.trim() !== '0:00'? item.refeicao: '<strong>----</strong>')+'</td>'
                        +'<td class = \'jornada\'>'+(item.espera && item.espera.trim() !== '00:00' && item.espera.trim() !== '0:00' ? item.espera : '<strong>----</strong>')+'</td>'
                        +'<td class = \'jornada\'>'+(item.descanso && item.descanso.trim() !== '00:00' && item.descanso.trim() !== '0:00' ? item.descanso : '<strong>----</strong>')+'</td>'
                        +'<td class = \'jornada\'>'+(item.repouso && item.repouso.trim() !== '00:00' && item.repouso.trim() !== '0:00' ? item.repouso : '<strong>----</strong>')+'</td>'
                        +'<td class = \'jornada acao-ajuste\' style=\"text-align: center;\" data-idmotorista=\"'+idMotorista+'\" data-dataiso=\"'+dataISO+'\">'
                            +(jornadaMinutos > 24 * 60 && idMotorista && dataISO ? '<span class=\"glyphicon glyphicon-pencil\" title=\"Encerrar Jornada Manualmente\"></span>' : '')
                        +'</td>'
                    +'</tr>';";

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

                    var colaboradoresJornadaCritica = {};
                    function atualizarKpiJornadaCritica(){
                        var el = document.getElementById('kpi-jornada-critica-valor');
                        if(!el) return;
                        var total = Object.keys(colaboradoresJornadaCritica).length;
                        el.textContent = total;
                    }

                    var colaboradoresHoraExtra = {};
                    function atualizarKpiHoraExtra(){
                        var el = document.getElementById('kpi-hora-extra-valor');
                        if(!el) return;
                        var total = Object.keys(colaboradoresHoraExtra).length;
                        el.textContent = total;
                    }

                    var colaboradoresSemJornadaPrevista = {};
                    function atualizarKpiSemJornadaPrevista(){
                        var el = document.getElementById('kpi-sem-jornada-prevista-valor');
                        if(!el) return;
                        var total = Object.keys(colaboradoresSemJornadaPrevista).length;
                        el.textContent = total;
                    }

                    function converterParaMinutos(hora) {
                        if (!hora) return 0;  // Se o valor for vazio ou nulo, retorna 0
                        const [horas, minutos] = hora.split(':').map(Number);
                        return (horas * 60) + minutos;
                    }

                    function converterMinutosParaHHHMM(minutos) {
                        const horas = Math.floor(minutos / 60);
                        const minutosRestantes = minutos % 60;
                        return `\${horas}:\${String(minutosRestantes).padStart(2, '0')}`;
                    }

                    function ajustarPonto(idMotorista, data){
                        if(document.form_ajuste_ponto){
                            document.form_ajuste_ponto.idMotorista.value = idMotorista;
                            document.form_ajuste_ponto.data.value = data;
                            document.form_ajuste_ponto.submit();
                        }
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

                    function abrirEscalaParametro(matricula, data){
                        var empresa = $('#empresa').val() || document.myForm.empresa.value;
                        if(!empresa) return;
                        $.ajax({
                            url: 'jornada_escala.php',
                            data: {
                                empresa: empresa,
                                matricula: matricula,
                                data: data
                            },
                            dataType: 'json',
                            success: function(res){
                                if(!res || !res.length){
                                    alert('Nenhuma escala encontrada para esta matrícula no mês da data selecionada.');
                                    return;
                                }
                                var titulo = 'Escala da matrícula ' + matricula + ' - ' + data.substring(3);
                                $('#escala-popup-titulo').text(titulo);
                                var html = '<table class=\"table table-condensed table-bordered\"><thead><tr><th>Data</th><th>Dia</th><th>Escala</th></tr></thead><tbody>';
                                for(var i=0;i<res.length;i++){
                                    var linha = res[i];
                                    var destaque = linha.data === data ? ' style=\"background-color:#d4edda;\"' : '';
                                    html += '<tr'+destaque+'><td>'+linha.data+'</td><td>'+linha.diaSemana+'</td><td>'+linha.escala+'</td></tr>';
                                }
                                html += '</tbody></table>';
                                $('#escala-popup-conteudo').html(html);
                                $('#escala-popup-overlay').fadeIn(150);
                            },
                            error: function(xhr, status, err){
                                console.error('Erro ao buscar escala:', status, err);
                                alert('Erro ao carregar a escala deste funcionário.');
                            }
                        });
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

                                        var jornadaPrevistaMinutos = 0;
                                        var jornadaPrevista = '<strong>----</strong>';
                                        var inicioEscLocal = (item.inicioEscala && item.inicioEscala !== '00:00' && item.inicioEscala !== '00:00:00') ? item.inicioEscala : '--:--';
                                        var fimEscLocal = (item.fimEscala && item.fimEscala !== '00:00' && item.fimEscala !== '00:00:00') ? item.fimEscala : '--:--';
                                        if(inicioEscLocal !== '--:--' && fimEscLocal !== '--:--'){
                                            var inicioEscMin = converterParaMinutos(inicioEscLocal);
                                            var fimEscMin = converterParaMinutos(fimEscLocal);
                                            if(!isNaN(inicioEscMin) && !isNaN(fimEscMin)){
                                                if(fimEscMin <= inicioEscMin){
                                                    fimEscMin += 24 * 60;
                                                }
                                                jornadaPrevistaMinutos = fimEscMin - inicioEscMin;
                                                if(jornadaPrevistaMinutos > 0){
                                                    jornadaPrevista = converterMinutosParaHHHMM(jornadaPrevistaMinutos);
                                                }
                                            }
                                        }

                                        const refeicaoMinutos = converterParaMinutos(item.refeicao === '*' ? '00:00' : item.refeicao);
                                        const esperaMinutos = converterParaMinutos(item.espera === '*' ? '00:00' : item.espera);
                                        const descansoMinutos = converterParaMinutos(item.descanso === '*' ? '00:00' : item.descanso);
                                        const repousoMinutos = converterParaMinutos(item.repouso === '*' ? '00:00' : item.repouso);
                                        const jornadaMinutos = converterParaMinutos(jornada);

                                        var idMotorista = null;
                                        var dataISO = '';
                                        if (typeof urlArquivo === 'string') {
                                            var partesUrl = urlArquivo.split('/');
                                            var nomeArquivo = partesUrl[partesUrl.length - 1] || '';
                                            idMotorista = nomeArquivo.replace('.json','');
                                        }
                                        if (item.data) {
                                            var partesData = item.data.split('/');
                                            if (partesData.length === 3) {
                                                dataISO = partesData[2] + '-' + partesData[1] + '-' + partesData[0];
                                            }
                                        }

                                        if(!isNaN(jornadaMinutos)){
                                            var chaveMatricula = String(item.matricula || '');
                                            if(jornadaMinutos > 24 * 60 && chaveMatricula){
                                                colaboradoresJornadaCritica[chaveMatricula] = true;
                                            } else if(chaveMatricula && colaboradoresJornadaCritica[chaveMatricula]){
                                                delete colaboradoresJornadaCritica[chaveMatricula];
                                            }
                                        }

                                        if(item.adi5322 === 'sim'){
                                            let jornadaSemIntervalo = jornadaMinutos - (refeicaoMinutos + descansoMinutos + repousoMinutos);
                                                jornadaEfetiva = converterMinutosParaHHHMM(jornadaSemIntervalo);
                                        } else {
                                            let jornadaSemIntervalo = jornadaMinutos - (refeicaoMinutos + esperaMinutos + descansoMinutos + repousoMinutos);
                                            jornadaEfetiva = converterMinutosParaHHHMM(jornadaSemIntervalo);
                                        }

                                        console.log();

                                        const jornadaEfetivaMinutosCalc = converterParaMinutos(jornadaEfetiva);
                                        if(jornadaPrevistaMinutos > 0 && !isNaN(jornadaEfetivaMinutosCalc)){
                                            var chaveMatricula = String(item.matricula || '');
                                            if(chaveMatricula){
                                                if(jornadaEfetivaMinutosCalc > jornadaPrevistaMinutos){
                                                    colaboradoresHoraExtra[chaveMatricula] = true;
                                                } else if(colaboradoresHoraExtra[chaveMatricula]){
                                                    delete colaboradoresHoraExtra[chaveMatricula];
                                                }
                                            }
                                        }

                                        let jornadaEfetivaCor = 'jornada';
                                        if(item.jornadaDia != undefined && item.jornadaDia != null){
                                            jornadaEfetivaCor = calcularJornadaElimite(jornadaEfetiva , item.jornadaDia, item.limiteExtras)
                                        }

                                        if(jornadaPrevistaMinutos > 0 && !isNaN(jornadaEfetivaMinutosCalc)){
                                            if(jornadaEfetivaMinutosCalc > jornadaPrevistaMinutos){
                                                jornadaEfetivaCor = 'jornadaRed';
                                            }
                                        }

                                        if (jornadaPrevistaMinutos === 0 && !isNaN(jornadaEfetivaMinutosCalc) && jornadaEfetivaMinutosCalc > 0) {
                                            jornadaEfetivaCor = 'jornadaRed';
                                            var chaveMatricula = String(item.matricula || '');
                                            if(chaveMatricula){
                                                colaboradoresSemJornadaPrevista[chaveMatricula] = true;
                                            }
                                        }

                                        if (jornadaEfetiva === '0:00' || jornadaEfetiva === '00:00') {
                                            jornadaEfetiva = '<strong>----</strong>';
                                        }

                                        if (jornada === '0:00' || jornada === '00:00') {
                                            jornada = '<strong>----</strong>';
                                        }

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
                                    atualizarKpiJornadaCritica();
                                    atualizarKpiHoraExtra();
                                    atualizarKpiSemJornadaPrevista();
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
                        var colunasPermitidas = ['matricula', 'nome', 'ocupacao', 'parametro', 'operacao', 'setor', 'subsetor', 'inicioJornada', 'escala', 'atraso', 'jornada', 'jornadaPrevista', 'jornadaEfetiva', 'refeicao', 'espera', 'descanso', 'repouso']; 
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
                        
                        $('#tabela-empresas tbody').on('click', 'td.inicio-jornada-click', function(e) {
                            e.stopPropagation();
                            var matricula = $(this).data('matricula');
                            var data = $(this).data('data');
                            if(matricula && data){
                                abrirEscalaParametro(String(matricula), String(data));
                            }
                        });

                        // Evento para selecionar a linha ao clicar (apenas a última clicada fica destacada)
                        $('#tabela-empresas tbody').on('click', 'tr', function() {
                            $('#tabela-empresas tbody tr').removeClass('selected-row');
                            $(this).addClass('selected-row');
                        });

                        $('#tabela-empresas tbody').on('click', 'td.acao-ajuste span.glyphicon-pencil', function(e) {
                            e.stopPropagation();
                            var td = $(this).closest('td');
                            var idMotorista = td.data('idmotorista');
                            var dataISO = td.data('dataiso');
                            if (idMotorista && dataISO) {
                                if (typeof Swal !== 'undefined' && Swal.fire) {
                                    Swal.fire({
                                        icon: 'question',
                                        title: 'Encerrar jornada',
                                        text: 'Gostaria de encerrar essa jornada manualmente?',
                                        showCancelButton: true,
                                        confirmButtonText: 'Sim',
                                        cancelButtonText: 'Não'
                                    }).then(function(result){
                                        if (result.isConfirmed) {
                                            ajustarPonto(idMotorista, dataISO);
                                        }
                                    });
                                } else {
                                    if (confirm('Gostaria de encerrar essa jornada manualmente?')) {
                                        ajustarPonto(idMotorista, dataISO);
                                    }
                                }
                            }
                        });

                        // Evento do filtro de jornada crítica
                        $('#filtro-jornada-critica').on('change', function() {
                            var checked = $(this).is(':checked');
                            var linhas = $('#tabela-empresas tbody tr');
                            
                            if (checked) {
                                // Desmarca outros filtros para evitar conflito visual
                                $('#filtro-sem-jornada-prevista').prop('checked', false);
                                $('#filtro-hora-extra').prop('checked', false);
                                linhas.each(function() {
                                    var linha = $(this);
                                    if (linha.hasClass('row-jornada-critica')) {
                                        linha.show();
                                    } else {
                                        linha.hide();
                                    }
                                });
                            } else {
                                linhas.show();
                            }
                        });

                        // Evento do filtro de hora extra
                        $('#filtro-hora-extra').on('change', function() {
                            var checked = $(this).is(':checked');
                            var linhas = $('#tabela-empresas tbody tr');
                            
                            if (checked) {
                                // Desmarca outros filtros para evitar conflito visual
                                $('#filtro-jornada-critica').prop('checked', false);
                                $('#filtro-sem-jornada-prevista').prop('checked', false);
                                linhas.each(function() {
                                    var linha = $(this);
                                    if (linha.hasClass('row-hora-extra')) {
                                        linha.show();
                                    } else {
                                        linha.hide();
                                    }
                                });
                            } else {
                                linhas.show();
                            }
                        });

                        // Evento do filtro de sem jornada prevista
                        $('#filtro-sem-jornada-prevista').on('change', function() {
                            var checked = $(this).is(':checked');
                            var linhas = $('#tabela-empresas tbody tr');
                            
                            if (checked) {
                                // Desmarca outros filtros para evitar conflito visual
                                $('#filtro-jornada-critica').prop('checked', false);
                                $('#filtro-hora-extra').prop('checked', false);
                                linhas.each(function() {
                                    var linha = $(this);
                                    if (linha.hasClass('row-sem-jornada-prevista')) {
                                        linha.show();
                                    } else {
                                        linha.hide();
                                    }
                                });
                            } else {
                                linhas.show();
                            }
                        });

                        $('#escala-popup-fechar').on('click', function() {
                            $('#escala-popup-overlay').fadeOut(150);
                        });
                        $('#escala-popup-overlay').on('click', function(e) {
                            if(e.target.id === 'escala-popup-overlay'){
                                $('#escala-popup-overlay').fadeOut(150);
                            }
                        });
                    });

                       $(document).ready(function() {
                            // Inicializa o select2 no campo 'empresa'
                            $('#empresa').select2();
                        });
                    //}
                </script>";
    }

    function index() {
        cabecalho("Relatório de Jornada Aberta");

        // $texto = "<div style=''><b>Periodo da Busca:</b> $monthName de $year</div>";
        //position: absolute; top: 101px; left: 420px;

        $temSubsetorVinculado = false;
        if (!empty($_POST["busca_setor"])) {
            $rowCount = mysqli_fetch_array(query("SELECT COUNT(*) FROM sbgrupos_documentos WHERE sbgr_tx_status = 'ativo' AND sbgr_nb_idgrup = ".intval($_POST["busca_setor"]).";"));
            $temSubsetorVinculado = ($rowCount[0] > 0);
        }

        $campos = [
            combo_net("Empresa", "empresa", $_POST["empresa"]?? $_SESSION["user_nb_empresa"], 4, "empresa", ""),
            combo("Ocupação", "busca_ocupacao", ($_POST["busca_ocupacao"] ?? ""), 2, 
            ["" => "Todos", "Motorista" => "Motorista", "Ajudante" => "Ajudante", "Funcionário" => "Funcionário"]),
            combo_bd("!Cargo", "operacao", ($_POST["operacao"]?? ""), 2, "operacao", "", "ORDER BY oper_tx_nome ASC"),
            combo_bd("!Setor", 		"busca_setor", 	($_POST["busca_setor"]?? ""), 	2, "grupos_documentos", "onchange=\"(function(f){ if(f.busca_subsetor){ f.busca_subsetor.value=''; } f.reloadOnly.value='1'; f.submit(); })(document.contex_form);\""),
        ];
        if ($temSubsetorVinculado) {
            $campos[] = combo_bd("!Subsetor", 	"busca_subsetor", 	($_POST["busca_subsetor"]?? ""), 	2, "sbgrupos_documentos", "", " AND sbgr_nb_idgrup = ".intval($_POST["busca_setor"])." ORDER BY sbgr_tx_nome ASC");
        }

        $botao_imprimir = "<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>";

        $buttons = [
            botao("Buscar", "", "", "", "", "", "btn btn-info"),
            $botao_imprimir,
        ];

        echo abre_form();
        echo campo_hidden("reloadOnly", "");
        echo linha_form($campos);
        echo fecha_form($buttons);

        $arquivos = [];
        $dataEmissao = ""; //Utilizado no HTML
        $encontrado = false;
        $path = "./arquivos/jornada";
        $periodoRelatorio = ["dataInicio" => "", "dataFim" => ""];

        if ( (isset($_POST["empresa"]) || !empty($_POST['busca_data'])) && empty($_POST["reloadOnly"]) ) {
             require_once "funcoes_paineis.php";
             criar_relatorio_jornada();

            $pathsToCheck = [];
            if (!empty($_POST["empresa"])) {
                $empresa = mysqli_fetch_assoc(query(
                    "SELECT * FROM empresa
                        WHERE empr_tx_status = 'ativo'
                            AND empr_nb_id = {$_POST["empresa"]}
                        LIMIT 1;"
                ));
                $pathsToCheck[] = $path . "/" . $empresa["empr_nb_id"];
            } else {
                $sqlEmpresas = query("SELECT empr_nb_id FROM empresa WHERE empr_tx_status = 'ativo'");
                while ($row = mysqli_fetch_assoc($sqlEmpresas)) {
                    $pathsToCheck[] = $path . "/" . $row['empr_nb_id'];
                }
            }

            $quantFun = ""; //Utilizado em painel_html2.php
            
            $arquivos = [];
            $missingData = false;
            foreach ($pathsToCheck as $checkPath) {
                if (is_dir($checkPath)) {
                    $pasta = dir($checkPath);
                    while ($arquivo = $pasta->read()) {
                        if (!in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresa_"))) {
                            $arquivos[] = $checkPath . "/" . $arquivo;
                        }
                    }
                    $pasta->close();
                } else {
                    $missingData = true;
                }
            }
            
            $quantFun = " - <b>Total de funcionários com jornada:</b> ".count($arquivos);

            if (!empty($arquivos) && !$missingData) {
                $ultimoArquivo = end($arquivos);
                $alertaEmissao = "<span style='color: red; border: 2px solid; padding: 2px; border-radius: 4px;'>
                    <i style='color:red; margin-right: 5px;' title='As informações do painel não correspondem à data de hoje.' class='fa fa-warning'></i>";
                $dataEmissao = $alertaEmissao." Atualizado em: " . date("d/m/Y H:i", filemtime($ultimoArquivo)). "</span>"; //Utilizado no HTML.
                
                $encontrado = true;
            } else {
                require_once "funcoes_paineis.php";
                criar_relatorio_jornada();
                
                if (empty($_POST["empresa"])) {
                    $pathsToCheck = [];
                    $sqlEmpresas = query("SELECT empr_nb_id FROM empresa WHERE empr_tx_status = 'ativo'");
                    while ($row = mysqli_fetch_assoc($sqlEmpresas)) {
                        $pathsToCheck[] = $path . "/" . $row['empr_nb_id'];
                    }
                }

                $arquivos = [];
                foreach ($pathsToCheck as $checkPath) {
                    if (is_dir($checkPath)) {
                        $pasta = dir($checkPath);
                        while ($arquivo = $pasta->read()) {
                            if (!in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresa_"))) {
                                $arquivos[] = $checkPath . "/" . $arquivo;
                            }
                        }
                        $pasta->close();
                    }
                }
                
                $encontrado = true;
            }
        }

        if ($encontrado) {
            $logoEmpresa = mysqli_fetch_assoc(query(
            "SELECT empr_tx_logo FROM empresa
                WHERE empr_tx_status = 'ativo'
                    AND empr_tx_Ehmatriz = 'sim'
                LIMIT 1;"
            ))["empr_tx_logo"];

            $exibirEmpresa = empty($_POST["empresa"]);
            $exibirOcupacao = empty($_POST["busca_ocupacao"]);
            $exibirCargo = empty($_POST["operacao"]);
            $exibirSetor = empty($_POST["busca_setor"]);
            $exibirSubSetor = empty($_POST["busca_subsetor"]);
            $rowGravidade = "
            <div id='kpis-resumo-jornada' style='display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;'>
                <div style='background-color: var(--var-lightred); border-left: 4px solid var(--var-red); padding: 8px 12px; border-radius: 4px; min-width: 230px; display:flex; flex-direction: column; justify-content: center;'>
                    <div style='display:flex;align-items:center;justify-content:space-between; margin-bottom: 5px;'>
                        <div>
                            <div style='font-size:11px;font-weight:bold;color:var(--var-darkred);'>Alerta Crítico</div>
                            <div style='font-size:10px;'>Colaboradores com jornada &gt; 24h</div>
                        </div>
                        <div id='kpi-jornada-critica-valor' style='font-size:22px;font-weight:bold;color:var(--var-darkred);'>0</div>
                    </div>
                    <div style='display: flex; align-items: center; gap: 5px;'>
                        <input type='checkbox' id='filtro-jornada-critica' style='cursor: pointer;'>
                        <label for='filtro-jornada-critica' style='font-size: 10px; color: var(--var-darkred); margin: 0; cursor: pointer; font-weight: bold;'>Mostrar apenas jornadas > 24h</label>
                    </div>
                </div>
                <div style='background-color: var(--var-lightorange); border-left: 4px solid var(--var-orange); padding: 8px 12px; border-radius: 4px; min-width: 230px; display:flex; flex-direction: column; justify-content: center;'>
                    <div style='display:flex;align-items:center;justify-content:space-between; margin-bottom: 5px;'>
                        <div>
                            <div style='font-size:11px;font-weight:bold;color:var(--var-orange);'>Alerta Operacional</div>
                            <div style='font-size:10px;'>Colaboradores em hora extra</div>
                        </div>
                        <div id='kpi-hora-extra-valor' style='font-size:22px;font-weight:bold;color:var(--var-orange);'>0</div>
                    </div>
                    <div style='display: flex; align-items: center; gap: 5px;'>
                        <input type='checkbox' id='filtro-hora-extra' style='cursor: pointer;'>
                        <label for='filtro-hora-extra' style='font-size: 10px; color: var(--var-orange); margin: 0; cursor: pointer; font-weight: bold;'>Mostrar apenas hora extra</label>
                    </div>
                </div>
                <div style='background-color: var(--var-lightyellow); border-left: 4px solid var(--var-yellow); padding: 8px 12px; border-radius: 4px; min-width: 230px; display:flex; flex-direction: column; justify-content: center;'>
                    <div style='display:flex;align-items:center;justify-content:space-between; margin-bottom: 5px;'>
                        <div>
                            <div style='font-size:11px;font-weight:bold;color:var(--var-darkyellow);'>Alerta Escala</div>
                            <div style='font-size:10px;'>Jornada em dia sem previsão</div>
                        </div>
                        <div id='kpi-sem-jornada-prevista-valor' style='font-size:22px;font-weight:bold;color:var(--var-darkyellow);'>0</div>
                    </div>
                    <div style='display: flex; align-items: center; gap: 5px;'>
                        <input type='checkbox' id='filtro-sem-jornada-prevista' style='cursor: pointer;'>
                        <label for='filtro-sem-jornada-prevista' style='font-size: 10px; color: var(--var-darkyellow); margin: 0; cursor: pointer; font-weight: bold;'>Mostrar apenas sem previsão</label>
                    </div>
                </div>
            </div>";
            // $rowTotais = "<tr class='totais'>";
            $rowTitulos = "<tr id='titulos' class='titulos'>";
            $rowTitulos .= "<th class='matricula'>Matrícula</th>
                <th class='nome'>Nome</th>";
            if ($exibirEmpresa) $rowTitulos .= "<th class='empresa'>Empresa</th>";
            if ($exibirOcupacao) $rowTitulos .= "<th class='ocupacao'>Ocupação</th>";
            $rowTitulos .= "<th class='parametro'>Parâmetro</th>";
            if ($exibirCargo) $rowTitulos .= "<th class='operacao'>Cargo</th>";
            if ($exibirSetor) $rowTitulos .= "<th class='setor'>Setor</th>";
            if ($exibirSubSetor) $rowTitulos .= "<th class='subsetor'>SubSetor</th>";
            $rowTitulos .= "<th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='inicioJornada'>Início Jornada</th>
                <th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='escala'>Inicio / Fim de Escala</th>
                <th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='atraso'>Atraso</th>
                <th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='jornada'>Jornada</th>
                <th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='jornadaPrevista'>Jornada Prevista</th>
                <th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='jornadaEfetiva'>Jornada Efetiva</th>
                <th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='refeicao'>Refeicao</th>
                <th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='espera'>Espera</th>
                <th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='descanso'>Descanso</th>
                <th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='repouso'>Repouso</th>
                <th style='cursor: default; background-color: var(--var-blue) !important; color: black !important;' class='acoes'>Ações</th>";
            $rowTitulos .= "</tr>";

            $params = array_merge($_POST, [
                "acao" => "index",
                "acaoPrevia" => $_POST["acao"] ?? "",
                "idMotorista" => null,
                "data" => null,
                "HTTP_REFERER" => (!empty($_POST["HTTP_REFERER"]) ? $_POST["HTTP_REFERER"] : $_SERVER["REQUEST_URI"])
            ]);

            echo criarHiddenForm(
                "form_ajuste_ponto",
                array_keys($params),
                array_values($params),
                "../ajuste_ponto.php"
            );
            $filtros = [];

            if (!empty($_POST['busca_ocupacao'])) {
                $filtros[] = "<b>Ocupação:</b> " . $_POST['busca_ocupacao'];
            }

            if (!empty($_POST['operacao'])) {
                $sql = query("SELECT oper_tx_nome FROM operacao WHERE oper_nb_id IN ({$_POST['operacao']})");
                $nomes = [];
                while($row = mysqli_fetch_assoc($sql)) $nomes[] = $row['oper_tx_nome'];
                $filtros[] = "<b>Cargo:</b> " . implode(", ", $nomes);
            }

            if (!empty($_POST['busca_setor'])) {
                $sql = query("SELECT grup_tx_nome FROM grupos_documentos WHERE grup_nb_id IN ({$_POST['busca_setor']})");
                $nomes = [];
                while($row = mysqli_fetch_assoc($sql)) $nomes[] = $row['grup_tx_nome'];
                $filtros[] = "<b>Setor:</b> " . implode(", ", $nomes);
            }

            if (!empty($_POST['busca_subsetor'])) {
                $sql = query("SELECT sbgr_tx_nome FROM sbgrupos_documentos WHERE sbgr_nb_id IN ({$_POST['busca_subsetor']})");
                $nomes = [];
                while($row = mysqli_fetch_assoc($sql)) $nomes[] = $row['sbgr_tx_nome'];
                $filtros[] = "<b>SubSetor:</b> " . implode(", ", $nomes);
            }

            if (empty($empresa)) {
                $empresa = ["empr_tx_nome" => "Todas"];
            }

            $filtrosConsultaHtml = implode(" | ", $filtros);

            $titulo = "Relatório de Jornada Aberta";
            $mostra = false;
            include_once "painel_html2.php";
            echo "
            <style>
            .selected-row, .selected-row td {
                background-color: #d1e7dd !important;
            }
            .sem-escala-indicador {
                display: inline-block;
                width: 8px;
                height: 8px;
                background-color: red;
                border-radius: 50%;
                margin-right: 4px;
            }
            #escala-popup-overlay{
                display:none;
                position:fixed;
                z-index:9999;
                top:0;
                left:0;
                right:0;
                bottom:0;
                background:rgba(0,0,0,0.4);
            }
            #escala-popup{
                background:#fff;
                max-width:900px;
                max-height:80vh;
                margin:40px auto;
                padding:10px;
                overflow:auto;
                border-radius:4px;
            }
            @media print{
                .container, .container-fluid {
                    margin-right: unset;
                }
                body {
                    margin: 0 !important;
                    overflow: visible !important;
                    position: static !important;
                }

                .row {
                    display: contents !important;
                }
                table, h2, h3, p, div {
                    page-break-inside: avoid;
                }
            }   
            </style>
            <div id='escala-popup-overlay'>
                <div id='escala-popup'>
                    <div style='display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;'>
                        <span id='escala-popup-titulo' style='font-weight:bold;font-size:12px;'></span>
                        <button type='button' id='escala-popup-fechar' class='btn btn-xs btn-default'>Fechar</button>
                    </div>
                    <div id='escala-popup-conteudo'></div>
                </div>
            </div>";
        }

        carregarJS($arquivos, $exibirEmpresa, $exibirOcupacao, $exibirCargo, $exibirSetor, $exibirSubSetor);

        rodape();
    }
