<?php

		ini_set("display_errors", 1);
		error_reporting(E_ALL);

    include "funcoes_ponto.php";
    include_once __DIR__."/check_permission.php";

function cadastraPonto(){
	//se nao tiver permisao na batida_ponto.php manda par ao index
    if(function_exists('verificaPermissao')){ verificaPermissao('/batida_ponto.php');

		

	}
		$hoje = date("Y-m-d");
		try {
			$motorista = mysqli_fetch_assoc(query(
				"SELECT enti_tx_matricula FROM entidade 
					WHERE enti_tx_status = 'ativo' 
						AND enti_nb_id = {$_SESSION["user_nb_entidade"]}
					LIMIT 1;"
			));
			$novoPonto = conferirErroPonto($motorista["enti_tx_matricula"], new DateTime("{$hoje} ".date("H:i:00")), intval($_POST["idMacro"]), (!empty($_POST["motivo"])? $_POST["motivo"]: 0), (!empty($_POST["justificativa"])? $_POST["justificativa"]: ""));
		} catch (\Exception $e) {
			set_status("ERRO: ".$e->getMessage());
			index();
			exit;
		}
		
		$novoPonto = array_merge($novoPonto,[
			"pont_tx_descricao" => "Mobile",
			"pont_tx_latitude" => !empty($_POST["latitude"])? $_POST["latitude"]: null,
			"pont_tx_longitude" => !empty($_POST["longitude"])? $_POST["longitude"]: null,
			"pont_tx_placa" => !empty($_POST["placa"])? $_POST["placa"]: null
		]);


		inserir("ponto", array_keys($novoPonto), array_values($novoPonto));

		index();
		exit;
	}

	function pegarPontosDia(string $matricula, $columns = ["*"]): array{
		$hoje = date("Y-m-d");
		$condicoesPontoBasicas = 
			"ponto.pont_tx_status = 'ativo'
			AND ponto.pont_tx_matricula = '{$matricula}'
			AND ponto.pont_tx_data <= '{$hoje} ".date("H:i:s")."'"
		;

		$abriuJornadaHoje = mysqli_fetch_assoc(
			query(
				"SELECT * FROM ponto
					WHERE {$condicoesPontoBasicas}
						AND ponto.pont_tx_tipo = 1
						AND ponto.pont_tx_data LIKE '%{$hoje}%'
					ORDER BY ponto.pont_tx_data ASC
					LIMIT 1;"
			)
		);

		if(empty($abriuJornadaHoje)){
			//Confere se há uma jornada aberta que veio do dia anterior.
			$temJornadaAberta = mysqli_fetch_assoc(
				query(
					"SELECT ponto.pont_tx_data, (ponto.pont_tx_tipo = 1) as temJornadaAberta FROM ponto
						WHERE {$condicoesPontoBasicas}
							AND ponto.pont_tx_tipo IN (1,2)
							AND pont_tx_data <= '{$hoje} 00:00:00'
						ORDER BY pont_tx_data DESC
						LIMIT 1;"
				)
			);

			if(!empty($temJornadaAberta) && intval($temJornadaAberta["temJornadaAberta"])){//Se tem uma jornada que veio do dia anterior
				$jornadaFechadaHoje = mysqli_fetch_assoc(
					query(
						"SELECT ponto.pont_tx_data, (ponto.pont_tx_tipo = 2) as jornadaFechadaHoje FROM ponto
							WHERE {$condicoesPontoBasicas}
								AND ponto.pont_tx_tipo IN (1,2)
								AND pont_tx_data LIKE '%{$hoje}%'
							ORDER BY pont_tx_data ASC
							LIMIT 1;"
					)
				);
				if(!empty($jornadaFechadaHoje) && intval($jornadaFechadaHoje["jornadaFechadaHoje"])){
					$sqlDataInicio = $jornadaFechadaHoje["pont_tx_data"];
				}else{
					$sqlDataInicio = $temJornadaAberta["pont_tx_data"];
				}
			}else{
				$sqlDataInicio = $hoje." ".date("H:i:s");
			}
		}else{
			$sqlDataInicio = $abriuJornadaHoje["pont_tx_data"];
		}

		$condicoesPontoBasicas .= " AND macr_tx_status = 'ativo'";
		
		$sql = 
			"SELECT DISTINCT pont_nb_id, ".implode(", ", $columns)." FROM ponto
				JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_tx_codigoInterno
				WHERE {$condicoesPontoBasicas}
					AND macroponto.macr_tx_fonte = 'positron'
					AND ponto.pont_tx_data >= '{$sqlDataInicio}'
				ORDER BY pont_tx_data ASC"
		;

		$pontosCompleto = mysqli_fetch_all(query($sql),MYSQLI_ASSOC);

		return [$pontosCompleto, $sql];
	}

	function criaBotaoRegistro(string $classe, int $tipoRegistro, string $nome, string $iconClass){
		return 
			"<button type='button'class='{$classe}' onclick='carregar_submit(\"".strval($tipoRegistro)."\",\" Tem certeza que deseja {$nome}?\");'>
				<div class='button-icon'>
				    <i style='min-height: var(--icon-size); line-height: var(--icon-size);' class='{$iconClass}'></i>
				</div>
				<div class='button-title'>
				    {$nome}
				</div>
			</button>
			<script>
				triedLocation = false;
				document.addEventListener('DOMContentLoaded', function() {
					if (window.__geoInitDone) { return; }
					window.__geoInitDone = true;
					// setLocationState(false);
					if(!triedLocation){
						if (navigator.permissions && navigator.permissions.query){
							navigator.permissions.query({ name: 'geolocation' }).then(function(res){
								if(res.state === 'granted'){
									if (navigator.geolocation){
										navigator.geolocation.getCurrentPosition(locationAllowed, locationDenied, { enableHighAccuracy: true });
									}
								}else{
									// showGeoPopup();
								}
							});
						}else{
							// showGeoPopup();
						}
					}
					triedLocation = true;
				});
				/*
				function ensureSweetAlert(cb){
					if (window.Swal){ cb(); return; }
					var s = document.createElement('script');
					s.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
					s.onload = cb;
					document.head.appendChild(s);
				}
				function ensurePermissionHint(){
					if (document.getElementById('geo-permission-hint')) return;
					var hint = document.createElement('div');
					hint.id = 'geo-permission-hint';
					hint.setAttribute('style','position:fixed;top:8px;left:8px;max-width:340px;padding:10px 12px;background:#fff;border:1px solid #ddd;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:9999;font: 600 12px/1.5 sans-serif;display:none;');
					hint.innerHTML = '<div style=\"display:flex;align-items:flex-start;gap:8px\"><i class=\"fa fa-lock\" style=\"color:#e67e22;font-size:16px\"></i><div>Para ativar a localização: clique no ícone de cadeado ou (!) no inicio da URL, abra Permissões e defina Localização ou Local como Permitir ou simplismente ative o local.</div></div><div style=\"margin-top:8px;text-align:right\"><button type=\"button\" id=\"geo-permission-hint-close\" class=\"btn btn-xs btn-default\">Entendi</button></div>';
					document.body.appendChild(hint);
					var closeBtn = document.getElementById('geo-permission-hint-close');
					if (closeBtn){ closeBtn.onclick = function(){ hint.style.display = 'none'; }; }
				}
				function showPermissionHint(show){
					ensurePermissionHint();
					var h = document.getElementById('geo-permission-hint');
					if (h){ h.style.display = show ? 'block' : 'none'; }
				}
				function showGeoPopup(){
					ensureSweetAlert(function(){
						showPermissionHint(true);
						function requestGeo(){
							return new Promise(function(resolve, reject){
								if (!navigator.geolocation){ reject(new Error('Geolocalização não suportada')); return; }
								navigator.geolocation.getCurrentPosition(function(pos){ resolve(pos); }, function(err){ reject(err); }, { enableHighAccuracy: true });
							});
						}
						var opts = {
							title: 'É necessário permitir a localização para bater o ponto',
							icon: 'warning',
							confirmButtonText: 'Siga as instruções em sua tela',
							allowOutsideClick: false,
							allowEscapeKey: false,
							preConfirm: function(){
							return requestGeo().then(function(pos){
								locationAllowed(pos);
							}).catch(function(err){
								Swal.showValidationMessage('Permita a localização no navegador para continuar');
								locationDenied(err);
								showPermissionHint(true);
							});
							}
						};
						if (location.protocol !== 'https:' && location.hostname !== 'localhost'){
							opts.title = 'Conexão insegura impede solicitar localização';
							opts.html = 'Acesse por HTTPS ou localhost para o navegador permitir a localização.';
							opts.confirmButtonText = 'Entendi';
							opts.preConfirm = function(){};
						}
						if (navigator.permissions && navigator.permissions.query){
							navigator.permissions.query({ name: 'geolocation' }).then(function(res){
								if (res.state === 'denied'){
									opts.html = 'Clique no cadeado ao lado da URL, abra Permissões e defina Localização como Permitir.';
								}
								Swal.fire(opts);
							});
						}else{
							Swal.fire(opts);
						}
					});
				}
				*/
                
                function setLocationState(allowed){
                    var warn = document.getElementById('location-warning');
                    if(warn){ warn.style.display = allowed ? 'none' : 'block'; }
                    document.querySelectorAll('.btn.green, .btn.red').forEach(function(b){ b.disabled = !allowed; });
                }
                function locationAllowed(pos) {
                    var latitude = pos.coords.latitude;
                    var longitude = pos.coords.longitude;
                
                    console.log('Latitude: ' + latitude);
                    console.log('Longitude: ' + longitude);
                
                    // Atribuir os valores aos campos do formulário
                    document.getElementById('latitude').value = latitude;
                    document.getElementById('longitude').value = longitude;
                    setLocationState(true);
                }
                
				function locationDenied(err) {
					console.log('Erro ao obter localização: ', err);
					setLocationState(false);
					// showPermissionHint(true);
				}
			</script>
			";
	}

function index() {
    if(function_exists('verificaPermissao')){ verificaPermissao('/batida_ponto.php'); }
		
		global $CONTEX;
		$hoje = date("Y-m-d");
		cabecalho("Registrar Ponto");
		
		if(empty($_SESSION["user_nb_entidade"])){
			echo 
				"<form name='goToIndexForm' action='{$_ENV["APP_PATH"]}{$_ENV["CONTEX_PATH"]}/logout.php'></form>"
				."<script>"
					."alert('Funcionário não localizado. Tente fazer o login novamente.');"
					."document.goToIndexForm.submit();"
				."</script>"
			;
			exit;
		}

		$motorista = mysqli_fetch_assoc(query(
			"SELECT * FROM entidade"
			." JOIN user ON enti_nb_id = user_nb_entidade"
			." WHERE enti_nb_id = ".$_SESSION["user_nb_entidade"].";"
		));

		[$pontosCompleto, $sql] = pegarPontosDia($motorista["enti_tx_matricula"], ["pont_tx_data", "pont_tx_tipo", "pont_tx_dataCadastro", "macr_tx_nome"]);

		if(!empty($pontosCompleto)){
			$pontos = [
				"primeiro" => $pontosCompleto[0],
				"ultimo" => $pontosCompleto[count($pontosCompleto)-1]
			];
		}else{
			$pontos = [
				"primeiro" => null,
				"ultimo" => null
			];
		}

		$inicios = [
			1  => "inicioJornada", 
			3  => "inicioRefeicao", 
			5  => "inicioEspera", 
			7  => "inicioDescanso", 
			9  => "inicioRepouso", 
			11 => "inicioRepousoEmbarcado"
		];
		$fins = [
			2  => "fimJornada", 
			4  => "fimRefeicao", 
			6  => "fimEspera", 
			8  => "fimDescanso", 
			10 => "fimRepouso", 
			12 => "fimRepousoEmbarcado"
		];

		$pares = [
			"jornada" 			=> [],
			"refeicao" 			=> [],
			"espera" 			=> [],
			"descanso"			=> [],
			"repouso"			=> [],
			"repousoEmbarcado" 	=> []
		];

		
		for($f = 0; $f < count($pontosCompleto); $f++){
			if(empty($pontosCompleto[$f])){
				continue;
			}
			
			$value = DateTime::createFromFormat("Y-m-d H:i:s", $pontosCompleto[$f]["pont_tx_data"]);
			$fullDaysCount = (date_diff($value, DateTime::createFromFormat("Y-m-d", $hoje))->d)-1;
			$value = ($value->format("Y-m-d") < $hoje)? operarHorarios([$value->format("H:i"), sprintf("%02d:%02d", ($fullDaysCount*24), "00")], "-"): $value->format("H:i");
			
			switch(intval($pontosCompleto[$f]["pont_tx_tipo"])){
				case 1:
					$pares["jornada"][] = [
						"inicio" => $value
					];
				break;
				case 2:
					if(count($pares["jornada"]) == 0){
						$pares["jornada"][0] = ["inicio" => null, "fim" => $value];
					}else{
						$pares["jornada"][count($pares["jornada"])-1]["fim"] = $value;
					}
				break;
				case 3:
					$pares["refeicao"][] = [
						"inicio" => $value
					];
				break;
				case 4:
					$pares["refeicao"][count($pares["refeicao"])-1]["fim"] = $value;
				break;
				case 5:
					$pares["espera"][] = [
						"inicio" => $value
					];
				break;
				case 6:
					$pares["espera"][count($pares["espera"])-1]["fim"] = $value;
				break;
				case 7:
					$pares["descanso"][] = [
						"inicio" => $value
					];
				break;
				case 8:
					$pares["descanso"][count($pares["descanso"])-1]["fim"] = $value;
				break;
				case 9:
					$pares["repouso"][] = [
						"inicio" => $value
					];
				break;
				case 10:
					$pares["repouso"][count($pares["repouso"])-1]["fim"] = $value;
				break;
				case 11:
					$pares["repousoEmbarcado"][] = [
						"inicio" => $value
					];
				break;
				case 12:
					$pares["repousoEmbarcado"][count($pares["repousoEmbarcado"])-1]["fim"] = $value;
				break;
			}
		}

		$jornadaCompleta = "00:00";
		for($f = 0; $f < count($pares["jornada"]); $f++){
			if(!empty($pares["jornada"][$f]["fim"])){
				$jornada = operarHorarios([$pares["jornada"][$f]["fim"], $pares["jornada"][$f]["inicio"]], "-");
				$jornadaCompleta = operarHorarios([$jornadaCompleta, $jornada], "+");
			}
			// if($f == count($pares["jornada"])-1 && empty($pares["jornada"][$f]["fim"])){
			// 	$jornada = operarHorarios([$value, $pares["jornada"][$f]["inicio"]], "-");
			// 	$jornadaCompleta = operarHorarios([$jornadaCompleta, $jornada], "+");
			// }
		}

		//Utilizado em batida_ponto_html.php
		$ultimoInicioJornada = !empty($pares["jornada"])? $pares["jornada"][count($pares["jornada"])-1]["inicio"]: null;
		
		foreach($pares as $key => $value){
			if(is_array($value) && count($value) > 0){
				for($f = 0; $f < count($value); $f++){
					if(isset($value[$f]["inicio"]) && isset($value[$f]["fim"])){
						$value[$f] = operarHorarios([$value[$f]["fim"], $value[$f]["inicio"]], "-");
					}else{
						$value[$f] = "00:00";
					}
				}
				$value = operarHorarios($value, "+");
			}else{
				$value = "00:00";
			}
			$pares[$key] = $value;
		}
		
		
		
		$jornadaEfetiva = operarHorarios([$pares["refeicao"], $pares["espera"], $pares["descanso"], $pares["repouso"], $pares["repousoEmbarcado"]], "+");
		$jornadaEfetiva = operarHorarios([$jornadaCompleta, $jornadaEfetiva], "-");


        $botoes = [
            "inicioJornada"             => criaBotaoRegistro("btn green", 1,  "JORNADA", ($_SESSION["user_tx_nivel"] == "Funcionário" ? "fa fa-flag-checkered fa-6" : "fa fa-car fa-6")),
            "inicioRefeicao"             => criaBotaoRegistro("btn blue", 3,  "REFEIÇÃO", "fa fa-cutlery fa-6"),
            // "inicioEspera"             => criaBotaoRegistro("btn purple", 5,  "ESPERA", "fa fa-clock-o fa-6"),
            "inicioDescanso"             => criaBotaoRegistro("btn orange", 7,  "DESCANSO", "fa fa-hourglass-start fa-6"),
            // "inicioRepouso"             => criaBotaoRegistro("btn dark", 9,  "REPOUSO", "fa fa-bed fa-6"),
            // "inicioRepousoEmbarcado"    => criaBotaoRegistro("btn cyan", 11, "Iniciar Repouso Embarcado", "fa fa-bed fa-6"),

            "fimJornada"                 => criaBotaoRegistro("btn red", 2,  "FIM JORNADA", ($_SESSION["user_tx_nivel"] == "Funcionário" ? "fa fa-flag-checkered fa-6" : "fa fa-car fa-6")),
            "fimRefeicao" 				=> criaBotaoRegistro("btn red", 4,  "FIM REFEIÇÃO", "fa fa-cutlery fa-6"),
            // "fimEspera" 				=> criaBotaoRegistro("btn red", 6,  "FIM ESPERA", "fa fa-clock-o fa-6"),
            "fimDescanso" 				=> criaBotaoRegistro("btn red", 8,  "FIM DESCANSO", "fa fa-hourglass-end fa-6"),
            // "fimRepouso" 				=> criaBotaoRegistro("btn red", 10, "FIM REPOUSO", "fa fa-bed fa-6"),
            // "fimRepousoEmbarcado" 	=> criaBotaoRegistro("btn red", 12, "Encerrar Repouso Embarcado", "fa fa-bed fa-6"),
        ];

		if($_SESSION["user_tx_nivel"] != "Funcionário"){
			$botoes["inicioEspera"] = criaBotaoRegistro("btn purple", 5,  "ESPERA", "fa fa-clock-o fa-6");
			$botoes["fimEspera"] = criaBotaoRegistro("btn red", 6,  "FIM ESPERA", "fa fa-clock-o fa-6");
			
			$botoes["inicioRepouso"] = criaBotaoRegistro("btn dark", 9,  "REPOUSO", "fa fa-bed fa-6");
			$botoes["fimRepouso"] = criaBotaoRegistro("btn red", 10, "FIM REPOUSO", "fa fa-bed fa-6");
		}

		
		$botoesVisiveis = [];

		if (empty($pontos["ultimo"]["pont_tx_tipo"]) || intval($pontos["ultimo"]["pont_tx_tipo"]) == 2) {
			$botoesVisiveis = [$botoes["inicioJornada"]];
		} elseif ($pontos["ultimo"]["pont_tx_tipo"] == 1 || in_array($pontos["ultimo"]["pont_tx_tipo"], array_keys($fins))){
			if($_SESSION["user_tx_nivel"] != "Funcionário"){
				$botoesVisiveis = [
					$botoes["inicioRepouso"],
					$botoes["inicioDescanso"], 
					$botoes["inicioEspera"], 
					$botoes["inicioRefeicao"], 
					$botoes["fimJornada"]
				];
			}else{
				$botoesVisiveis = [
					$botoes["inicioDescanso"], 
					$botoes["inicioRefeicao"], 
					$botoes["fimJornada"]
				];
			}
		}elseif(in_array($pontos["ultimo"]["pont_tx_tipo"], array_keys($inicios))){
			$botoesVisiveis = [
				$botoes[$fins[$pontos["ultimo"]["pont_tx_tipo"]+1]]
			];
		}

		$motorista["user_tx_cpf"] = str_split($motorista["user_tx_cpf"], 3);
		$motorista["user_tx_cpf"] = $motorista["user_tx_cpf"][0].".".$motorista["user_tx_cpf"][1].".".$motorista["user_tx_cpf"][2]."-".$motorista["user_tx_cpf"][3];

        $fields = [
            "<div id='clockParent' class='col-sm-5 margin-bottom-5' >
                <label>Hora</label><br>
                <p class='text-left' id='clock'>Carregando...</p>
                <div id='timeout' value='15'>Inatividade: --:--</div>
            </div>",
            "<div id='location-warning' class='col-sm-12' style='color:red; display:none;'></div>",
            "<div class='col-sm-5 margin-bottom-5 info-grid'>"
                ."<div class='margin-bottom-5'><b>Data:</b> ".date("d/m")."</div>"
                ."<div class='margin-bottom-5'><b>Matrícula:</b> ".$motorista["enti_tx_matricula"]."</div>"
                ."<div class='margin-bottom-5'><b>CPF:</b> ".$motorista["user_tx_cpf"]."</div>"
                ."<div class='margin-bottom-10'><b>Nome:</b> ".$motorista["user_tx_nome"]."</div>"
            ."</div>",
        ];

		$logoutTime = 30; //Utilizado em batida_ponto_html.php

		$aEndosso = mysqli_fetch_array(query(
			"SELECT user_tx_login, endo_tx_dataCadastro
				FROM endosso, user
				WHERE endo_tx_status = 'ativo'
					AND '{$hoje}' BETWEEN endo_tx_de AND endo_tx_ate
					AND endo_nb_entidade = '{$motorista["enti_nb_id"]}'
					AND endo_nb_userCadastro = user_nb_id
				LIMIT 1"
		), MYSQLI_BOTH);

		$afastamento = mysqli_fetch_assoc(query(
			"SELECT * FROM abono 
				JOIN motivo ON abon_nb_motivo = moti_nb_id
				JOIN user ON abon_nb_userCadastro = user_nb_id
				WHERE abon_tx_status = 'ativo'
					AND abon_tx_matricula = '{$motorista["enti_tx_matricula"]}'
					AND moti_tx_tipo = 'Afastamento'
					AND abon_tx_data = '".date("Y-m-d")."'
				LIMIT 1;"
		));

		$ferias = mysqli_fetch_assoc(query(
			"SELECT * FROM ferias
				WHERE feri_tx_status = 'ativo'
					AND feri_nb_entidade = '{$motorista["enti_nb_id"]}'
					AND '{$hoje}' BETWEEN feri_tx_dataInicio AND feri_tx_dataFim
				LIMIT 1;"
		));

		if(!empty($aEndosso) || !empty($afastamento) || !empty($ferias)){
			$botoesVisiveis = [];
			$logoutTime = 300; //Utilizado em batida_ponto_html.php

			if (!empty($aEndosso)){
				$fields[] = texto("Endosso:", "Endossado por ".$aEndosso["user_tx_login"]." em ".data($aEndosso["endo_tx_dataCadastro"], 1), 6);
			}elseif(!empty($afastamento)){
				$fields[] = texto("Afastamento:", "Afastado por motivo de {$afastamento["moti_tx_nome"]}<br> por ".$afastamento["user_tx_login"]." em ".data($afastamento["abon_tx_dataCadastro"], 1), 6);
			}elseif(!empty($ferias)){
				$fields[] = texto("Férias:", "Férias de ({$ferias["feri_tx_dataInicio"]} a {$ferias["feri_tx_dataFim"]})", 6);
			}
		}else{
			if($motorista["enti_tx_ocupacao"] == "Motorista"){
				$fields[] = campo("Placa do Veículo", "placa", ($_POST["placa"]?? ""), 2, "MASCARA_PLACA", "");
			}
			$fields[] = textarea("Justificativa", "justificativa", ($_POST["justificativa"]?? ""), 5, "style='resize: vertical;' placeholder='Em caso de inconsistência, justificar aqui.'");
		}
			


		echo abre_form();
		echo linha_form($fields);
		echo fecha_form($botoesVisiveis);


		$gridFields = [
			"DATA"			=> "data(pont_tx_data, 1)",
			"TIPO"			=> "macr_tx_nome",
			"DATA CADASTRO"	=> "data(pont_tx_dataCadastro,1)",
			"PLACA"			=> "pont_tx_placa"
		];

		grid($sql, array_keys($gridFields), array_values($gridFields), "", "", 0, "desc");
		rodape();

		include "html/batida_ponto_html.php";
	}
