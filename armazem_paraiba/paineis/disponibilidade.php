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
            $linha .= " +'<td style=\'text-align: center;\'>'+item.ocupacao+'</td>'
                        +'<td style=\'text-align: center;\'>'+ (item.tipoOperacaoNome || '-') +'</td>'
                        +'<td style=\'text-align: center;\'>'+item.matricula+'</td>'
                        +'<td style=\'text-align: center;\'>'+item.Nome+'</td>'
                        +'<td style=\'text-align: center;\'>'+item.ultimaJornada+'</td>'
                        +'<td style=\'text-align: center;'+ css +'\'><strong>'+item.repouso+'</strong></td>'
                        +'<td style=\'text-align: center;\'>'+item.Apos8+'</td>'
                        +'<td style=\'text-align: center;\'>'+item.Apos11+'</td>'
                        +'<td style=\'text-align: center;\'>'+status+'</td>'
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

                    var contagemOcupacoes = {}; 
                    $(document).ready(function(){
                        var tabela = $('#tabela-empresas tbody');
                        var filtro = '".$filtro ."';
                        console.log(filtro);

                        function carregarDados(urlArquivo){
                            $.ajax({
                                url: urlArquivo + '?v=' + new Date().getTime(),
                                dataType: 'json',
                                success: function(data){
                                    var contagemStatus = {
                                        disponivel: 0,
                                        parcial: 0,
                                        naoPermitido: 0
                                    };
                                    var row = {};
                                    $.each(data, function(index, item){
                                        console.log(index);
                                        // console.log(item);
                                        var css = '';
                                        var status = '';
                                        if(index == 'disponivel'){
                                            css = 'background-color: lightgreen;';
                                            status = 'Disponivel';
                                        } else if(index == 'parcial') {
                                            css = 'background-color: var(--var-lightorange);';
                                            status = 'Parcialmente Disponivel';
                                        } else if(index == 'naoPermitido') {
                                            css = 'background-color: var(--var-darkred); color: white;';
                                            status = 'Indisponível';
                                        }

                                        if (filtro !== '' && index !== filtro) {
                                            return;
                                        }

                                        if(index != 'EmJornada'){
                                            if (Array.isArray(item)) {
                                                // Itera sobre o array de motoristas
                                                $.each(item, function(index, item) {
                                                    if (item.ocupacao) {
                                                        if (!contagemOcupacoes[item.ocupacao]) {
                                                            contagemOcupacoes[item.ocupacao] = 0;
                                                        }
                                                        contagemOcupacoes[item.ocupacao]++;
                                                    }
                                                    ". $linha."
                                                    tabela.append(linha);
                                                });
                                            }
                                        }  

                                        if(contagemStatus.hasOwnProperty(index)) {
                                            if (Array.isArray(item)) {
                                                contagemStatus[index] += item.length;
                                            }
                                        }
                                    });

                                    console.log(contagemOcupacoes);
                                    
                                    var consulta = $('#consulta');
                                    consulta.css({
                                        color: 'red',
                                        border: '2px solid',
                                        padding: '2px',
                                        borderRadius: '4px',
                                        display: 'inline-block'
                                    });
                                    
                                    var consultas = `<i style='color:red; margin-right: 5px;' title='Aqui apresenta a data da projeção de jornada consultada' class='fa fa-warning'></i>`;
                                    consultas += `<span style=\"color: red;\"><b>Projeção de Disponibilidade de Jornada para:&nbsp;</b></span><spam> \${data.total.consulta}</span>`;

                                    consulta.append(consultas);

                                    var consulta = $('#consulta');
                                    var ocupacaoData ='".$_POST["busca_ocupacao"]."';
                                    if(ocupacaoData == ''){
                                        ocupacaoData = 'Todos';
                                    }
                                    consulta.after('<br><strong>Ocupação:&nbsp</strong> <span>'+ocupacaoData+'</span>');

                                    var resumo = $('#resumo');
                                    resumo.after('<br><span style=\"font-size: 10px; text-align: justify;\"><i class=\"fa fa-info-circle\" aria-hidden=\"true\" style=\"font-size: 14px;\"></i> Funcionários com jornada aberta não aparecem neste painel.</span>');

                                    var tabela_funcionarios = $('#tabela-funcionarios thead');

                                    var ocupacoesHTML = '';
                                    var totalOcupacoes = 0;
                                    var linhasOcupacoes = '';

                                    // Gera as linhas por ocupação
                                    for (let ocupacao in contagemOcupacoes) {
                                        let quantidade = contagemOcupacoes[ocupacao];
                                        totalOcupacoes += quantidade;

                                        linhasOcupacoes += `
                                            <tr>
                                                <th><span style=\"text-transform: capitalize;\">\${ocupacao}</span></th>
                                                <td style=\"text-align: center;\"><strong>\${quantidade}</strong></td>
                                            </tr>`;
                                    }

                                    // Linha do total no início
                                    ocupacoesHTML += `
                                        <tr>
                                            <th title=\"Quantidade por ocupação\">Total de Ocupações:</th>
                                            <td style=\"text-align: center;\"><strong>\${totalOcupacoes}</strong></td>
                                        </tr>`;

                                    // Junta total com as linhas das ocupações
                                    ocupacoesHTML += linhasOcupacoes;

                                    tabela_funcionarios.append(ocupacoesHTML);


                                    var tabela_disponivel = $('#tabela-disponivel thead');
                                    var tabela_parcial = $('#tabela-parcial thead');
                                    var tabela_indisponivel = $('#tabela-indisponivel thead');
                                    var tabela_jornada = $('#tabela-jornada thead');

                                    var linha_disponivel = `
                                        <tr>
                                            <td style=\"background-color: lightgreen; height: 54px; vertical-align: middle; font-size: 13px; padding: 10px;\">
                                                <div style=\"display: flex; justify-content: space-between; align-items: center;\">
                                                    <strong>Disponível com 11H:</strong>
                                                    <span style=\"margin-left: 10px;\"><strong>\${contagemStatus.disponivel}</strong></span>
                                                </div>
                                            </td>
                                        </tr>`;
                                    tabela_disponivel.append(linha_disponivel);

                                    var linha_parcial = `
                                        <tr>
                                            <td style=\"background-color: var(--var-lightorange); height: 54px; vertical-align: middle; font-size: 13px; padding: 10px;\">
                                                <div style=\"display: flex; justify-content: space-between; align-items: center;\">
                                                    <strong>Parcialmente Disponível com 8H:</strong>
                                                    <span style=\"margin-left: 10px;\"><strong>\${contagemStatus.parcial}</strong></span>
                                                </div>
                                            </td>
                                        </tr>`;
                                    tabela_parcial.append(linha_parcial);

                                    var linha_indisponivel = `
                                        <tr>
                                            <td style=\"background-color: #a30000; color: white; padding: 5px 10px; height: 54px; vertical-align: middle; font-size: 13px; padding: 10px;\">
                                                <div style=\"display: flex; justify-content: space-between; align-items: center;\">
                                                    <strong>Indisponível:</strong>
                                                    <span style=\"margin-left: 10px;\"><strong>\${contagemStatus.naoPermitido}</strong></span>
                                                </div>
                                            </td>
                                        </tr>`;
                                    tabela_indisponivel.append(linha_indisponivel);

                                    var linha_jornada = `
                                        <tr>
                                            <td style=\"background-color: black; color: white; padding: 5px 10px; height: 54px; vertical-align: middle; font-size: 13px; padding: 10px;\">
                                                <div style=\"display: flex; justify-content: space-between; align-items: center;\">
                                                    <strong>Em Jornada</strong>
                                                    <span style=\"margin-left: 10px;\"><strong>\${data.total.totalMotoristasJornada}</strong></span>
                                                </div>
                                            </td>
                                        </tr>`;
                                    tabela_jornada.append(linha_jornada);

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
                            if (['jornada', 'consulta', 'disponível8', 'disponível11'].includes(coluna)) {
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

                        function ordenarTabela2(coluna, ordem) {
                            var linhas = tabela2.find('tr').get();
                            
                            linhas.sort(function(a, b) {
                                var valorA = converterValor($(a).children('td').eq(coluna).text(), colunasPermitidas2[coluna]);
                                var valorB = converterValor($(b).children('td').eq(coluna).text(), colunasPermitidas2[coluna]);

                                if (valorA < valorB) {
                                    return ordem === 'asc' ? -1 : 1;
                                }
                                if (valorA > valorB) {
                                    return ordem === 'asc' ? 1 : -1;
                                }
                                return 0;
                            });

                            $.each(linhas, function(index, row) {
                                tabela2.append(row);
                            });
                        }

                        var colunasPermitidas = ['ocupacao', 'matricula', 'nome', 'jornada', 'consulta', 'repouso', 'disponível8', 'disponível11'];
                        var colunasPermitidas2 = ['ocupacao2', 'matricula2', 'nome2', 'jornada2', 'consulta2', 'repouso2', 'disponível82', 'disponível112'];

                        // Evento de clique para ordenar a tabela ao clicar no cabeçalho
                        $('#titulos3 th').click(function() {
                            var colunaClicada = $(this).attr('class');

                            var classePermitida = colunasPermitidas.some(function(coluna) {
                                return colunaClicada.includes(coluna);
                            });

                            if (classePermitida) {
                                var coluna = $(this).index();
                                var ordem = $(this).data('order');

                                // Redefinir ordem de todas as colunas
                                $('#titulos3 th').data('order', 'desc');
                                $(this).data('order', ordem === 'desc' ? 'asc' : 'desc');

                                // Chama a função de ordenação
                                ordenarTabela(coluna, $(this).data('order'));

                                // Ajustar classes para setas de ordenação
                                $('#titulos3 th').removeClass('sort-asc sort-desc');
                                $(this).addClass($(this).data('order') === 'asc' ? 'sort-asc' : 'sort-desc');
                            }
                        });

                         $('#titulos4 th').click(function() {
                            var colunaClicada = $(this).attr('class');

                            var classePermitida = colunasPermitidas2.some(function(coluna) {
                                return colunaClicada.includes(coluna);
                            });

                            if (classePermitida) {
                                var coluna = $(this).index();
                                var ordem = $(this).data('order');

                                // Redefinir ordem de todas as colunas
                                $('#titulos4 th').data('order', 'desc');
                                $(this).data('order', ordem === 'desc' ? 'asc' : 'desc');

                                // Chama a função de ordenação
                                ordenarTabela2(coluna, $(this).data('order'));

                                // Ajustar classes para setas de ordenação
                                $('#titulos4 th').removeClass('sort-asc sort-desc');
                                $(this).addClass($(this).data('order') === 'asc' ? 'sort-asc' : 'sort-desc');
                            }
                        });

                        ".$carregarDados."
                    });
                </script>";
    }
    
    function index() {
        cabecalho("Painel de Disponibilidade de Jornada");

        // $texto = "<div style=''><b>Periodo da Busca:</b> $monthName de $year</div>";
        //position: absolute; top: 101px; left: 420px;

        $campos = [
            combo_net("Empresa", "empresa", $_POST["empresa"]?? $_SESSION["user_nb_empresa"], 4, "empresa", ""),
            combo("Ocupação", "busca_ocupacao", ($_POST["busca_ocupacao"] ?? ""), 2, 
            ["" => "Todos", "Motorista" => "Motorista", "Ajudante" => "Ajudante", "Funcionário" => "Funcionário"]),
            campo_dataHora("Disponibilidade de Jornada Projetada para:","busca_periodo",(!empty($_POST["busca_periodo"])? $_POST["busca_periodo"] : ''),
            2),
            combo("Status", "busca_Dispobilidade", ($_POST["busca_Dispobilidade"] ?? ""), 2, 
            ["" => "Todos", "disponivel" => "Disponives", "naoPermitido" => "Indisponives", "parcial" => "Parcialmente disponível"]),
            combo_bd("!Operação", "operacao", ($_POST["operacao"]?? ""), 2, "operacao", "", "ORDER BY oper_tx_nome ASC"),
        ];

        $botao_imprimir = "<button class='btn default' type='button' onclick='enviarDados()'>Imprimir</button>
        <script>
        function enviarDados() {
            console.log(contagemOcupacoes);
            const tabelaOriginal = document.querySelector('#tabela-empresas');
            const disponivel = document.querySelector('#tabela-disponivel span strong')?.textContent || '0';
            const parcial = document.querySelector('#tabela-parcial span strong')?.textContent || '0';
            const indisponível = document.querySelector('#tabela-indisponivel span strong')?.textContent || '0';
            const EmJornada = document.querySelector('#tabela-jornada span strong')?.textContent || '0';

            if (!tabelaOriginal) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'export_paineis.php';
            form.target = '_blank';

            const data = '" . $_POST["busca_periodo"] . "';
            const empresa = " . (!empty($_POST['empresa']) ? $_POST['empresa'] : 'null') . ";
            
            const campos = [
                { name: 'empresa', value: empresa },
                { name: 'busca_data', value: data },
                { name: 'relatorio', value: 'disponibilidade' }
            ];

            campos.forEach(({ name, value }) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            });

            const tabelaClone = tabelaOriginal.cloneNode(true);
            tabelaClone.querySelectorAll('i.fa, script, style, link').forEach(el => el.remove());

            let htmlSimplificado = '<table style=\"width:100%;border-collapse:collapse;font-family:helvetica;font-size:7pt\">';

            const thead = tabelaClone.querySelector('thead');
            if (thead) {
                htmlSimplificado += '<thead>';
                thead.querySelectorAll('tr').forEach(tr => {
                    
                htmlSimplificado += '<tr>';
                tr.querySelectorAll('th').forEach((th, index)=> {
                    let largura = '80px'; // valor padrão
                    if (index === 0) largura = '40px';
                    else if (index === 1) largura = '40px';
                    else if (index === 2) largura = '222px';
                    else if (index === 3) largura = '80px';
                    else if (index === 4) largura = '100px';
                    else if (index === 5) largura = '100px';

                    htmlSimplificado += '<th style=\"border:0.5px solid #000;padding:2px;text-align:center;font-weight:bold;background-color:#444d58; color:white; width:' + largura + '\">';
                        htmlSimplificado += th.innerText.trim();
                        htmlSimplificado += '</th>';
                    });
                    htmlSimplificado += '</tr>';
                });
                htmlSimplificado += '</thead>';
            }

            const indiceDisponibilidade = 4; // ajuste para o índice real da coluna \"Tempo de Disponibilidade\"

            const tbody = tabelaClone.querySelector('tbody');
            if (tbody) {
                htmlSimplificado += '<tbody>';
                tbody.querySelectorAll('tr').forEach(tr => {
                    htmlSimplificado += '<tr>';
                    tr.querySelectorAll('td').forEach((td, colIndex) => {
                        let largura = '80px'; // valor padrão
                        if (colIndex === 0) largura = '40px';
                        else if (colIndex === 1) largura = '40px';
                        else if (colIndex === 2) largura = '222px';
                        else if (colIndex === 3) largura = '80px';
                        else if (colIndex === 4) largura = '100px';
                        else if (colIndex === 5) largura = '100px';

                        let estiloBase = 'border:0.5px solid #000;padding:2px;font-size:7pt;width:' + largura + ';';
                        estiloBase += (colIndex === 2)
                            ? 'text-align:left;white-space:nowrap;overflow:hidden;max-width:90px;'
                            : 'text-align:center;';

                        let conteudo = td.innerHTML.trim();

                        if (colIndex === indiceDisponibilidade) {
                            const estiloInline = td.getAttribute('style') || '';
                            let css = '';
                            let status = td.innerHTML.trim();

                            console.log(conteudo);

                            if (estiloInline.includes('lightgreen')) {
                                css = 'background-color: lightgreen;';
                            } else if (estiloInline.includes('var(--var-lightorange)')) {
                                css = 'background-color: #ffc680;';
                            } else if (estiloInline.includes('var(--var-darkred)')) {
                                css = 'background-color: #ff4d4d;';
                            }

                            estiloBase += css;
                        }

                        htmlSimplificado += '<td style=\"' + estiloBase + '\">';
                        htmlSimplificado += td.innerHTML.trim();
                        htmlSimplificado += '</td>';
                    });
                    htmlSimplificado += '</tr>';
                });
                htmlSimplificado += '</tbody>';
            }

            htmlSimplificado += '</table>';

            const inputTabela = document.createElement('input');
            inputTabela.type = 'hidden';
            inputTabela.name = 'htmlTabela';
            inputTabela.value = htmlSimplificado;
            form.appendChild(inputTabela);

            const inputDisponivel = document.createElement('input');
            inputDisponivel.type = 'hidden';
            inputDisponivel.name = 'disponivel';
            inputDisponivel.value = disponivel;
            form.appendChild(inputDisponivel);

            const inputOcupacao = document.createElement('input');
            inputOcupacao.type = 'hidden';
            inputOcupacao.name = 'ocupacao';
            inputOcupacao.value = JSON.stringify(contagemOcupacoes);
            form.appendChild(inputOcupacao);

            const inputParcial = document.createElement('input');
            inputParcial.type = 'hidden';
            inputParcial.name = 'parcial';
            inputParcial.value = parcial;
            form.appendChild(inputParcial);

            const inputIndisponível = document.createElement('input');
            inputIndisponível.type = 'hidden';
            inputIndisponível.name = 'indisponível';
            inputIndisponível.value = indisponível;
            form.appendChild(inputIndisponível);

            const inputEmJornada = document.createElement('input');
            inputEmJornada.type = 'hidden';
            inputEmJornada.name = 'EmJornada';
            inputEmJornada.value = EmJornada;
            form.appendChild(inputEmJornada);

            const inputConsultaOcupacao = document.createElement('input');
            inputConsultaOcupacao.type = 'hidden';
            inputConsultaOcupacao.name = 'consultaOcupacao';
            inputConsultaOcupacao.value = '".$_POST["busca_ocupacao"]."';
            form.appendChild(inputConsultaOcupacao);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        </script>";

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
                    // $alertaEmissao = "<span style='color: red; border: 2px solid; padding: 2px; border-radius: 4px;'>
                        // <i style='color:red; margin-right: 5px;' title='As informações do painel não correspondem à data de hoje.' class='fa fa-warning'></i>";
                    // $dataEmissao = $alertaEmissao." Atualizado em: " . date("d/m/Y H:i", filemtime($arquivo)). "</span>";
                    $dataEmissao = "Atualizado em: " . date("d/m/Y H:i", filemtime($arquivo)). "</span>";
                    $encontrado = true;
                }
            }else {
                $encontrado = false;
            }

            if ($encontrado) {
                $titulo = "Painel de Disponibilidade de Jornada";
                // $mostra = false;
                $rowTitulos = "<tr id='titulos3' class='titulos3'>";
                $rowTitulos .= "
                <th class='ocupacao'>Ocupação</th>
                <th class='operacao'>Operação</th>
                <th class='matricula'>Matrícula</th>
                <th class='nome'>Nome</th>
                <th class='jornada'>Fim de jornada</th>
                <th class='repouso'>Tempo de Disponibilidade</th>
                <th class='disponível8'>Disponibilidade Parcial (8h)</th>
                <th class='disponível11'>Disponibilidade Total (11h)</th>
                <th class=''>Status</th>";
                $rowTitulos .= "</tr>";

                $tabelaMotivo = "
			    <div style='display: flex; flex-direction: column;'>
				<div class='row' id='resumo'>
					<div class='col-md-4.5'>
						<table id='tabela-funcionarios'
							class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact'>
							<thead>
							</thead>
                        </table>
                    </div>
                    <div style=\"padding-left: 50px;\">
                        <table id=\"tabela-disponivel\"
                            class=\"table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact \"
                            style=\"margin-bottom: 15px; border-radius: 15px; overflow: hidden;\">
                            <thead></thead>
                        </table>
                    </div>
                    <div style=\"padding-left: 30px;\">
                        <table id=\"tabela-parcial\"
                            class=\"table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact\"
                            style=\"margin-bottom: 15px; border-radius: 15px; overflow: hidden;\">
                            <thead></thead>
                        </table>
                    </div>
                    <div style=\"padding-left: 30px;\">
                        <table id=\"tabela-indisponivel\"
                            class=\"table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact\"
                            style=\"margin-bottom: 15px; border-radius: 15px; overflow: hidden;\">
                            <thead></thead>
                        </table>
                    </div>
                    <div style=\"padding-left: 30px;\">
                        <table id=\"tabela-jornada\"
                            class=\"table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact\"
                            style=\"margin-bottom: 15px; border-radius: 15px; overflow: hidden;\">
                            <thead></thead>
                        </table>
                    </div>
                    ";

                $painelDisp = true;
                include_once "painel_html2.php";
            }

            carregarJS($arquivos);
        }


        rodape();
    }
