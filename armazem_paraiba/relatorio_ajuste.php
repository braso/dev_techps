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
    cabecalho("Relatório de ajustes de ponto");

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
                <h1>Relatório de ajustes de ponto</h1>
                <img id='logo' style='width: 150px' src='".$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/imagens/logo_topo_cliente.png' alt='Logo Empresa Direita'>
            </div>"
        ;

        // Converte as datas para objetos DateTime
        [$startDate, $endDate] = [new DateTime($_POST["busca_periodo"][0]), new DateTime($_POST["busca_periodo"][1])];
        $diaInicio = $startDate->format("Y-m-d");
        $diaFim = $endDate->format("Y-m-d");
        $diaInicioFotmat = $startDate->format("d-m-Y");
        $diaFimFotmat = $endDate->format("d-m-Y");

        $rows = [];

        $motorista = mysqli_fetch_assoc(query(
            "SELECT enti_tx_matricula, enti_tx_matricula, enti_tx_nome, enti_tx_cpf, enti_tx_ocupacao FROM entidade
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

        $pontos = mysqli_fetch_all(
            query(
            "SELECT DISTINCT ponto.pont_nb_id, ponto.pont_tx_descricao, ponto.pont_tx_data,
            ponto.pont_tx_latitude, ponto.pont_tx_longitude, ponto.pont_tx_justificativa,
            ponto.pont_tx_placa, ponto.pont_tx_dataAtualiza, ponto.pont_tx_dataCadastro,
            ponto.pont_tx_status, motivo.moti_tx_nome, macroponto.macr_tx_nome, motivo.moti_tx_legenda,user.user_tx_nome"
                ." FROM ponto"
                ." LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id"
                ." LEFT JOIN user ON ponto.pont_nb_userCadastro = user.user_nb_id"
                ." INNER JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_tx_codigoInterno"
                ." AND macroponto.macr_tx_fonte = 'positron'"
                ." WHERE pont_tx_matricula = '$motorista[enti_tx_matricula]'"
                ." AND pont_tx_justificativa IS NOT NULL"
                ." AND pont_tx_data BETWEEN STR_TO_DATE('$diaInicio 00:00:00', '%Y-%m-%d %H:%i:%s')"
                ." AND STR_TO_DATE('$diaFim 23:59:59', '%Y-%m-%d %H:%i:%s')"
                ." ORDER BY ponto.pont_tx_data ASC;"
            ),
            MYSQLI_ASSOC
        );

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
                    <h2>Relatorio de ajustes de ponto</h2>
                    <div class='right-logo'>
                        <img style='width: 150px' src='$_ENV[APP_PATH].$_ENV[CONTEX_PATH]/imagens/logo_topo_cliente.png' alt='Logo Empresa Direita'>
                    </div>
                </div>
                <div class='col-md-12 col-sm-12' id='pdf2htmldiv'>
                    <div class='portlet light '>
                        <div class='emissao' style='display: block !important;'>
				            <h2 class='titulo2'>Relatorio de ajustes de ponto</h2>
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
										Ajustes Ativos
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
										Ajustes Inativos
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
                +'<td>'+item.pont_tx_data+'</td>'
                +'<td>'+(item.pont_tx_placa === null ? '': item.pont_tx_placa)+'</td>'
                +'<td>'+item.macr_tx_nome+'</td>'
                +'<td>'+(item.moti_tx_nome === null ? '' : item.moti_tx_nome)+'</td>'
                +'<td>'+(item.moti_tx_legenda === null ? '' : item.moti_tx_legenda)+'</td>'
                +'<td>'+item.pont_tx_justificativa+'</td>'
                +'<td>'+item.user_tx_nome+'</td>'
                +'<td>'+item.pont_tx_dataCadastro+'</td>'
                +'<td>'+(item.pont_tx_dataAtualiza === null ? '' : item.pont_tx_dataAtualiza )+'</td>'
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
