<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
    /*/    
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");
	//*/

include "funcoes_ponto.php";

function pegarSqlDiaPonto(string $matricula, DateTime $dataInicio, DateTime $dataFim, array $cols): string{

    $condicoesPontoBasicas = " ponto.pont_tx_matricula = '{$matricula}'";

    $sqlDataInicio = $dataInicio->format("Y-m-d 00:00:00");
    $sqlDataFim = $dataFim->format("Y-m-d 23:59:59");

    $ultJornadaOntem = mysqli_fetch_assoc(query(
        "SELECT pont_tx_data, (pont_tx_tipo = 1) as jornadaAbertaAntes FROM ponto 
            WHERE {$condicoesPontoBasicas}
                AND pont_tx_tipo IN (1,2)
                AND pont_tx_data < STR_TO_DATE('{$sqlDataInicio}', '%Y-%m-%d %H:%i:%s')
            ORDER BY pont_tx_data DESC
            LIMIT 1;"
    ));

    $primJornadaAmanha = mysqli_fetch_assoc(query(
        "SELECT pont_tx_data, (pont_tx_tipo = 2) as jornadaFechadaApos FROM ponto 
            WHERE {$condicoesPontoBasicas}
                AND pont_tx_tipo IN (1,2)
                AND pont_tx_data > STR_TO_DATE('{$sqlDataFim}', '%Y-%m-%d %H:%i:%s')
            ORDER BY pont_tx_data ASC
            LIMIT 1;"
    ));


    if(!empty($ultJornadaOntem) && intval($ultJornadaOntem["jornadaAbertaAntes"])){
        $sqlDataInicio = $ultJornadaOntem["pont_tx_data"];
    }

    if(!empty($primJornadaAmanha) && intval($primJornadaAmanha["jornadaFechadaApos"])){
        $sqlDataFim = $primJornadaAmanha["pont_tx_data"];
    }

    $condicoesPontoBasicas = 
        "ponto.pont_tx_matricula = '{$matricula}' 
        AND entidade.enti_tx_status = 'ativo' 
        AND user.user_tx_status = 'ativo' 
        AND macroponto.macr_tx_status = 'ativo'"
    ;
    
    $sql = 
        "SELECT DISTINCT pont_nb_id, ".implode(",", $cols)." FROM ponto
            JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_tx_codigoInterno
            JOIN entidade ON ponto.pont_tx_matricula = entidade.enti_tx_matricula
            JOIN user ON entidade.enti_nb_id = user.user_nb_entidade
            LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id
            WHERE {$condicoesPontoBasicas}
                AND macr_tx_fonte = 'positron'
                AND ponto.pont_tx_data >= STR_TO_DATE('{$sqlDataInicio}', '%Y-%m-%d %H:%i:%s')
                AND ponto.pont_tx_data <= STR_TO_DATE('{$sqlDataFim}', '%Y-%m-%d %H:%i:%s')
            ORDER BY pont_tx_data ASC"
    ;


    return $sql;
}

function buscarEspelho(){

    if(in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário"])){
        [$_POST["busca_motorista"], $_POST["busca_empresa"]] = [$_SESSION["user_nb_entidade"], $_SESSION["user_nb_empresa"]];
    }

    
    //Confere se há algum erro na pesquisa{
        try{
            if(empty($_POST["busca_empresa"])){
                if(empty($_POST["busca_motorista"])){
                    $_POST["busca_empresa"] = $_SESSION["user_nb_empresa"];
                }else{
                    $idEmpresa = mysqli_fetch_assoc(query(
                        "SELECT empr_nb_id FROM entidade
                            JOIN empresa ON enti_nb_empresa = empr_nb_id
                            WHERE enti_tx_status = 'ativo'
                                AND enti_nb_id = ".$_POST["busca_motorista"].";"
                    ));
                    $_POST["busca_empresa"] = $idEmpresa["empr_nb_id"];
                }
            }

            if(empty($_POST["busca_periodo"]) && !empty($_POST["periodo_abono"])){
                $_POST["busca_periodo"] = $_POST["periodo_abono"];
                unset($_POST["periodo_abono"]);
            }

            //Conferir campos obrigatórios{
                $camposObrig = [
                    "busca_empresa" => "Empresa",
                    "busca_motorista" => "Funcionário",
                    "busca_periodo" => "Período"
                ];
                $errorMsg = conferirCamposObrig($camposObrig, $_POST);
                
                if(!empty($errorMsg)){
                    throw new Exception($errorMsg);
                }
            //}

            if(is_string($_POST["busca_periodo"])){
                $_POST["busca_periodo"] = explode(" - ", $_POST["busca_periodo"]);
            }
            
            if(!empty($_POST["busca_empresa"]) && !empty($_POST["busca_motorista"])){
                if($_POST["busca_periodo"][0] > date("Y-m-d") || $_POST["busca_periodo"][1] > date("Y-m-d")){
                    $_POST["errorFields"][] = "busca_periodo";
                    throw new Exception("Data de pesquisa não pode ser após hoje (".date("d/m/Y").").");
                }else{
                    $motorista = mysqli_fetch_assoc(query(
                        "SELECT enti_nb_id, enti_tx_nome, enti_tx_admissao FROM entidade"
                            ." WHERE enti_tx_status = 'ativo'"
                                ." AND enti_nb_empresa = ".$_POST["busca_empresa"]
                                ." AND enti_nb_id = ".$_POST["busca_motorista"]
                            ." LIMIT 1;"
                    ));
    
                    if(empty($motorista)){
                        $_POST["errorFields"][] = "busca_motorista";
                        throw new Exception("Este funcionário não pertence a esta empresa.");
                    }
                }
            }


            //Conferir se a data de início da pesquisa está antes do cadastro do motorista{
                if(!empty($motorista)){
                    $dataInicio = new DateTime($_POST["busca_periodo"][0]);
                    $data_cadastro = new DateTime($motorista["enti_tx_admissao"]);
                    if($dataInicio->format("Y-m") < $data_cadastro->format("Y-m")){
                        $_POST["errorFields"][] = "busca_periodo";
                        throw new Exception("O mês inicial deve ser posterior ou igual ao mês de admissão do funcionário (".$data_cadastro->format("m/Y").").");
                    }
                }
            //}
        }catch(Exception $error){
            set_status("ERRO: ".$error->getMessage());
            unset($_POST["acao"]);
        }
    //}

    index();
    exit;
}

function index() {
    cabecalho("Relatório de  Pontos");

    $condBuscaMotorista = "";
    $condBuscaEmpresa = "";

    //CAMPOS DE CONSULTA{
    $searchFields = [
        combo_net("Empresa*", "busca_empresa", ($_POST["busca_empresa"] ?? ""), 3, "empresa", "onchange=selecionaMotorista(this.value) ", $condBuscaEmpresa),
        combo_net(
            "Funcionário*",
            "busca_motorista",
            (!empty($_POST["busca_motorista"]) ? $_POST["busca_motorista"] : ""),
            4,
            "entidade",
            "",
            (!empty($_POST["busca_empresa"]) ? " AND enti_nb_empresa = " . $_POST["busca_empresa"] : "") . " AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário') " . $condBuscaEmpresa . " " . $condBuscaMotorista,
            "enti_tx_matricula"
        )
    ];

    $searchFields[] = campo(
        "Período",
        "busca_periodo",
        (!empty($_POST["busca_periodo"]) ? $_POST["busca_periodo"] : [date("Y-m-01"), date("Y-m-d")]),
        2,
        "MASCARA_PERIODO"
    );
    //}

    //BOTOES{
    $b = [
        botao("Buscar", "buscarEspelho()", "", "", "", "", "btn btn-success"),
    ];
    if (!in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário"])) {
        $b[] = botao("Cadastrar Abono", "cadastro_abono", "", "", "btn btn-secondary");
    }

    $botao_imprimir = "<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>";
    $b[] = $botao_imprimir;
    //}

    echo abre_form();
    echo linha_form($searchFields);
    echo fecha_form($b);

    $pontos = [];
    if(!empty($_POST["acao"]) && $_POST["acao"] == "buscarEspelho()"){
        global $totalResumo;

        echo   
            "<div style='display:none' id='tituloRelatorio'>
                <h1>Relatório de pontoS</h1>
                <img id='logo' style='width: 150px' src='".$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/imagens/logo_topo_cliente.png' alt='Logo Empresa Direita'>
            </div>"
        ;

        // Converte as datas para objetos DateTime
        [$startDate, $endDate] = [new DateTime($_POST["busca_periodo"][0]), new DateTime($_POST["busca_periodo"][1])];
        $diaInicioFotmat = $startDate->format("d/m/Y");
        $diaFimFotmat = $endDate->format("d/m/Y");

        $rows = [];

        $motorista = mysqli_fetch_assoc(query(
            "SELECT enti_tx_matricula, enti_tx_nome, enti_tx_cpf, enti_tx_ocupacao FROM entidade
             LEFT JOIN empresa ON entidade.enti_nb_empresa = empresa.empr_nb_id
             LEFT JOIN cidade  ON empresa.empr_nb_cidade = cidade.cida_nb_id
             LEFT JOIN parametro ON enti_nb_parametro = para_nb_id
             WHERE enti_tx_status = 'ativo'
                 AND enti_nb_id = '{$_POST["busca_motorista"]}'
             LIMIT 1;"
        ));

        $empresa = mysqli_fetch_assoc(query(
            "SELECT empr_tx_nome FROM empresa
             WHERE empr_tx_status = 'ativo'
             AND empr_nb_id = '{$_POST["busca_empresa"]}'
             LIMIT 1;"
        ));
        
        $sql = pegarSqlDiaPonto(
			$motorista["enti_tx_matricula"], $startDate, $endDate,
			[
				"pont_nb_id", 
				"pont_tx_data", 
				"macr_tx_nome", 
				"moti_tx_nome", 
				"moti_tx_legenda", 
				"pont_tx_justificativa", 
				"(SELECT user_tx_nome FROM user WHERE user.user_nb_id = pont_nb_userCadastro LIMIT 1) as userCadastro", 
				"pont_nb_userCadastro",
				"pont_tx_dataCadastro", 
				"pont_tx_placa", 
				"pont_tx_latitude", 
				"pont_tx_longitude",
				"pont_tx_dataAtualiza",
                "pont_tx_status"
			]
		);

        $pontos = mysqli_fetch_all(query($sql),MYSQLI_ASSOC);

        $logoEmpresa = mysqli_fetch_assoc(query(
            "SELECT empr_tx_logo FROM empresa
            WHERE empr_tx_status = 'ativo'
                AND empr_tx_Ehmatriz = 'sim'
            LIMIT 1;"
        ))["empr_tx_logo"];//Utilizado no HTML.

        $logoEmpresa = $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/".$logoEmpresa;

        $diaImpresao = date('d/m/Y \T H:i:s').' (UTC-3)';

        echo "<link rel='stylesheet' href='./css/paineis.css'>
                <div id='printTitulo'>
                    <img style='width: 150px' src='<?= $logoEmpresa ?>' alt='Logo Empresa Esquerda'>
                    <h2>Relatorio de Pontos</h2>
                    <div class='right-logo'>
                        <img style='width: 150px' src='$_ENV[APP_PATH].$_ENV[CONTEX_PATH]/imagens/logo_topo_cliente.png' alt='Logo Empresa Direita'>
                    </div>
                </div>
                <div class='col-md-12 col-sm-12' id='pdf2htmldiv'>
                    <div class='portlet light '>
                        <div class='emissao' style='display: block !important;'>
				            <h2 class='titulo2'>Relatorio de Pontos</h2>
                            <br>
                            <span><b>Empresa: </b>$empresa[empr_tx_nome]</span>
                            <br>
                            <span><b>Período do relatório: </b> $diaInicioFotmat a $diaFimFotmat</span>
                            <br>
                            <span><b>Matrícula: </b> $motorista[enti_tx_matricula]</span> -
                            <span><b>$motorista[enti_tx_ocupacao]: </b> $motorista[enti_tx_nome]</span> -
                            <span><b>CPF: </b> $motorista[enti_tx_cpf]</span>
                        </div>
                    <div class='panel panel-default'>
						<div class='panel-heading'>
							<h3 class='panel-title'>
								<a
									data-toggle='collapse'
									href='#collapse4'
									aria-expanded='false'
									aria-controls='collapse4'
									class='collapsed'>
									<b>
										Pontos Ativos
									</b>
								</a>
							</h3>
						</div>
						<div id='collapse4' class='panel-collapse collapse'>
                        <div class='table-responsive'>
                            <div class='portlet-body form'>
                                <table id='tabela-empresas' class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact'>
                                    <thead>
                                        <th class='cod'>CÓD</th>
                                        <th class='data'>DATA</th>
                                        <th class='placa'>PLACA</th>
                                        <th class='tipo'>TIPO</th>
                                        <th class='motivo'>MOTIVO</th>
                                        <th class='legenda'>LEGENDA</th>
                                        <th class='justificativa'>JUSTIFICATIVA</th>
                                        <th class='usuario'>USUÁRIO CADASTRO</th>
                                        <th class='dataCadastro'>DATA CADASTRO</th>
                                        <th class='localizacao'>LOCALIZAÇÃO</th>
                                    </thead>
                                    <tbody>
                                        <!-- Conteúdo do json empresas será inserido aqui -->
                                    </tbody>
                                </table>
                            </div>
						</div>
					</div>
				</div>
                <div class='panel panel-default'>
						<div class='panel-heading'>
							<h3 class='panel-title'>
								<a
									data-toggle='collapse'
									href='#collapse5'
									aria-expanded='false'
									aria-controls='collapse5'
									class='collapsed'>
									<b>
										Pontos Inativos
									</b>
								</a>
							</h3>
						</div>
						<div id='collapse5' class='panel-collapse collapse'>

                        <div class='table-responsive'>
                            <div class='portlet-body form'>
                                <table id='tabela-empresas2' class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact'>
                                    <thead>
                                        <th class='cod'>CÓD</th>
                                        <th class='data'>DATA</th>
                                        <th class='placa'>PLACA</th>
                                        <th class='tipo'>TIPO</th>
                                        <th class='motivo'>MOTIVO</th>
                                        <th class='legenda'>LEGENDA</th>
                                        <th class='justificativa'>JUSTIFICATIVA</th>
                                        <th class='usuario'>USUÁRIO CADASTRO</th>
                                        <th class='dataCadastro'>DATA CADASTRO</th>
                                        <th class='dataCadastro'>DATA EXCLUSÃO</th>
                                        <th class='localizacao'>LOCALIZAÇÃO</th>
                                    </thead>
                                    <tbody>
                                        <!-- Conteúdo do json empresas será inserido aqui -->
                                    </tbody>
                                </table>
                            </div>
						</div>
					</div>
				</div>
                <div id='impressao'>
                    <b>Impressão Doc.:</b> $diaImpresao
                </div>";
    }

    echo carregarJS($pontos);
    rodape();
}

function carregarJS($opt): string {

    $pontos = json_encode($opt);

    $select2URL =
        "{$_ENV["URL_BASE"]}{$_ENV["APP_PATH"]}/contex20/select2.php"
        . "?path={$_ENV["APP_PATH"]}{$_ENV["CONTEX_PATH"]}"
        . "&tabela=entidade"
        . "&extra_limite=15"
        . "&extra_busca=enti_tx_matricula";;

    $linha = "linha = '<tr>'";
    $linha .= "+'<td>'+item.pont_nb_id+'</td>'
                +'<td>'+formatarData(item.pont_tx_data)+'</td>'
                +'<td>'+(item.pont_tx_placa === null ? '': item.pont_tx_placa)+'</td>'
                +'<td>'+(item.macr_tx_nome === 'Inicio de Jornada' || item.macr_tx_nome === 'Fim de Jornada' 
                ? '<strong>' + item.macr_tx_nome + '</strong>' 
                : item.macr_tx_nome) +'</td>'
                +'<td>'+(item.moti_tx_nome === null ? '' : item.moti_tx_nome)+'</td>'
                +'<td>'+(item.moti_tx_legenda === null ? '' : item.moti_tx_legenda)+'</td>'
                +'<td>'+(item.pont_tx_justificativa === null ? '' : item.pont_tx_justificativa)+'</td>'
                +'<td>'+(item.userCadastro === null ? '' : item.userCadastro)+'</td>'
                +'<td>'+formatarData(item.pont_tx_dataCadastro)+'</td>'
                +'<td'+ (item.pont_tx_dataAtualiza === null ? 'display: none;' : '') +'>'+(item.pont_tx_dataAtualiza === null ? '' : formatarData(item.pont_tx_dataAtualiza))+'</td>'
               +'<td><center>'
                + (item.pont_tx_latitude && item.pont_tx_longitude 
                    ? '<a href=\"https://www.google.com/maps?q=' + item.pont_tx_latitude + ',' + item.pont_tx_longitude + '\" target=\"_blank\">'
                        + '<i class=\"fa fa-map-marker\" aria-hidden=\"true\" style=\"color: black; font-size: 20px;\"></i>'
                        + '</a>'
                    : '')
                + '</center></td>'
                +'</tr>';";

    return
        "<script>

				function selecionaMotorista(idEmpresa){
					let buscaExtra = '&extra_bd='+encodeURI('AND enti_tx_ocupacao IN (\"Motorista\", \"Ajudante\", \"Funcionário\")');
					if(idEmpresa > 0){
						buscaExtra += encodeURI(' AND enti_nb_empresa = '+idEmpresa);
						$('.busca_motorista')[0].innerHTML = null;
					}

					// Verifique se o elemento está usando Select2 antes de destruí-lo
					if($('.busca_motorista').data('select2')){
						$('.busca_motorista').select2('destroy');
					}
					$.fn.select2.defaults.set('theme', 'bootstrap');
					$('.busca_motorista').select2({
						language: 'pt-BR',
						placeholder: 'Selecione um item',
						allowClear: true,
						ajax: {
							url: '{$select2URL}'+buscaExtra,
							dataType: 'json',
							delay: 250,
							processResults: function(data){
								return {
									results: data
								};
							},
							cache: true
						}
					});
				}

				function imprimir(){
					window.print();
				}

                function formatarData(data) {
                    const date = new Date(data);

                    // Extrair partes da data
                    const dia = String(date.getDate()).padStart(2, '0');
                    const mes = String(date.getMonth() + 1).padStart(2, '0'); // Mês é baseado em zero
                    const ano = date.getFullYear();

                    // Extrair partes do horário
                    const horas = String(date.getHours()).padStart(2, '0');
                    const minutos = String(date.getMinutes()).padStart(2, '0');
                    const segundos = String(date.getSeconds()).padStart(2, '0');

                    // Retornar no formato desejado
                    return `\${dia}/\${mes}/\${ano} \${horas}:\${minutos}:\${segundos}`;
                }

                $(document).ready(function(){
					var tabela = $('#tabela-empresas tbody');
					var tabela2 = $('#tabela-empresas2 tbody');
					function carregarDados(urlArquivo){
						$.each(dados, function(index, item) {
                            console.log(item);"
								.$linha
								. "
								var novaLinha = $(linha);
                                if(item.pont_tx_status == 'ativo'){
                                    tabela.append(linha);
                                } else {
                                    tabela2.append(linha);
                                }
                        });
					}

                    var dados = $pontos;
                    carregarDados(dados);
                });

			</script>";
}
