<?php
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/

	include_once 'funcoes_ponto.php';

	function cadastrarAjuste(){
		//Conferir se tem as informações necessárias{
			if(empty($_POST['hora']) || empty($_POST['idMacro']) || empty($_POST['motivo'])){
				set_status("ERRO: Dados insuficientes!");
				index();
				exit;
			}
			$aMotorista = carregar('entidade',$_POST['id']);
			$aTipo = carregar('macroponto', $_POST['idMacro']);
			if(empty($aMotorista)){
				set_status("ERRO: Motorista não encontrado.");
				index();
				exit;
			}
			if(empty($aTipo)){
				set_status("ERRO: Macro não encontrado.");
				index();
				exit;
			}
		//}
		
		//Tratamento de erros{
			$error = false;
			$errorMsg = 'ERRO: ';
			$codigosJornada = ['inicio' => 1, 'fim' => 2];
			//Conferir se há uma jornada aberta{
				$temJornadaAberta = mysqli_fetch_all(
					query(
						"SELECT * FROM ponto 
							WHERE pont_tx_tipo IN ('".$codigosJornada['inicio']."', '".$codigosJornada['fim']."')
								AND pont_tx_status != 'inativo'
								AND pont_tx_matricula = '".$aMotorista['enti_tx_matricula']."'
								AND pont_tx_data < '".$_POST['data'].' '.$_POST['hora']."'
							ORDER BY pont_tx_data DESC
							LIMIT 1"
					),
					MYSQLI_ASSOC
				)[0];
				$temJornadaAberta = (!empty($temJornadaAberta) && intval($temJornadaAberta['pont_tx_tipo']) == $codigosJornada['inicio']);

				if($temJornadaAberta){
					if(intval($aTipo['macr_tx_codigoInterno']) == $codigosJornada['inicio']){ //Se tem jornada aberta e está tentando cadastrar uma abertura de jornada
						$error = true;
						$errorMsg .= 'Não é possível registrar um '.strtolower($aTipo['macr_tx_nome']).' sem fechar o anterior.';
					}elseif(intval($aTipo['macr_tx_codigoInterno']) == $codigosJornada['fim']){
						$jornadaFechada = mysqli_fetch_assoc(
							query(
								"SELECT * FROM ponto 
									WHERE pont_tx_tipo IN ('".$codigosJornada['inicio']."', '".$codigosJornada['fim']."')
										AND pont_tx_status != 'inativo'
										AND pont_tx_matricula = '".$aMotorista['enti_tx_matricula']."'
										AND pont_tx_data >= '".$_POST['data'].' '.$_POST['hora']."'
									ORDER BY pont_tx_data ASC
									LIMIT 1"
							)
						);
						$jornadaFechada = (!empty($jornadaFechada) && $jornadaFechada['pont_tx_tipo'] == $codigosJornada['fim']);
						if(!empty($jornadaFechada)){
							$error = true;
							$errorMsg .= 'Esta jornada já foi fechada neste horário ou após ele.';
						}
					}else{
						$matchedTypes = [
							'inicios' => [3,5,7,9,11],
							'fins' => [4,6,8,10,12]
						];

						$matchedTypes = [
							'inicios' => mysqli_fetch_all(query("SELECT macr_tx_codigoInterno FROM macroponto WHERE macr_tx_nome LIKE 'Inicio%' AND macr_tx_nome NOT LIKE '%de Jornada'"), MYSQLI_ASSOC),
							'fins' 	  => mysqli_fetch_all(query("SELECT macr_tx_codigoInterno FROM macroponto WHERE macr_tx_nome LIKE 'Fim%' AND macr_tx_nome NOT LIKE '%de Jornada'"),    MYSQLI_ASSOC),
						];
						for($f = 0; $f < count($matchedTypes['inicios']); $f++){
							$matchedTypes['inicios'][$f] = intval($matchedTypes['inicios'][$f]['macr_tx_codigoInterno']);
							$matchedTypes['fins'][$f]    = intval($matchedTypes['fins'][$f]['macr_tx_codigoInterno']);
						}

						$temPeriodoAberto = mysqli_fetch_all(
							query(
								"SELECT * FROM ponto 
									WHERE pont_tx_status != 'inativo'
										AND pont_tx_matricula = '".$aMotorista['enti_tx_matricula']."'
										AND pont_tx_data LIKE '".$_POST['data']."%'
										AND pont_tx_data < '".$_POST['data'].' '.$_POST['hora']."'
									ORDER BY pont_tx_data DESC
									LIMIT 1"
							),
							MYSQLI_ASSOC
						)[0];
						
						$temPeriodoAberto['pont_tx_tipo'] = intval($temPeriodoAberto['pont_tx_tipo']);
						$temPeriodoAberto['macr_tx_codigoInterno'] = intval($temPeriodoAberto['macr_tx_codigoInterno']);
						
						$openTypeValue = intval($aTipo['macr_tx_codigoInterno'])-(intval($aTipo['macr_tx_codigoInterno'])%2 == 0? 1: 0); //O código de abertura do tipo que está sendo registrado.
						$sameType = ($temPeriodoAberto['pont_tx_tipo'] == $openTypeValue || $temPeriodoAberto['pont_tx_tipo'] == $openTypeValue+1); //Se esse período encontrado é do mesmo tipo que está tentando ser cadastrado
						$temPeriodoAberto = in_array($temPeriodoAberto['pont_tx_tipo'], $matchedTypes['inicios']); //Se encontrou um período aberto
						
						if(in_array(intval($aTipo['macr_tx_codigoInterno']), $matchedTypes['inicios'])){ //Se está registrando uma abertura de período
							if($temPeriodoAberto){
								$error = true;
								$errorMsg .= 'Não é possível registrar um '.strtolower($aTipo['macr_tx_nome']).' sem fechar o anterior.';
							}
						}elseif(in_array(intval($aTipo['macr_tx_codigoInterno']), $matchedTypes['fins'])){ //Se está registrando um fechamento de período
							if(!$temPeriodoAberto || !$sameType){ //Se não tem um período aberto ou se o período aberto é de tipo diferente do que está sendo fechado 
								$error = true;
								$errorMsg .= 'Não é possível registrar um '.strtolower($aTipo['macr_tx_nome']).' sem abrir um período antes.';
							}
						}
					}
				}elseif($aTipo['macr_tx_codigoInterno'] != '1'){
					$error = true;
					$errorMsg .= 'Não é possível realizar ajustes sem uma jornada aberta.';
				}
			//}

			if($error){
				set_status($errorMsg);
				index();
				exit;
			}
		//}



		$campos = ['pont_nb_user', 'pont_tx_matricula', 'pont_tx_data', 'pont_tx_tipo', 'pont_tx_tipoOriginal', 'pont_tx_status', 'pont_tx_dataCadastro', 'pont_nb_motivo', 'pont_tx_descricao'];
		$valores = [$_SESSION['user_nb_id'], $aMotorista['enti_tx_matricula'], "$_POST[data] $_POST[hora]", $aTipo['macr_tx_codigoInterno'], $aTipo['macr_tx_codigoExterno'], 'ativo', date("Y-m-d H:i:s"),$_POST['motivo'],$_POST['descricao']];
		
		
		inserir('ponto',$campos,$valores);
		index();
		exit;
	}

	function voltar(){
		global $CONTEX;
    
		$aMotorista = carregar('entidade',$_POST['id']);
		echo 
			'<form action="https://braso.mobi'.$CONTEX['path'].'/espelho_ponto" name="form_voltar" method="post">
				<input type="hidden" name="busca_motorista" value="'.$_POST['id'].'">
				<input type="hidden" name="busca_dataInicio" value="'.$_POST['data_de'].'">
				<input type="hidden" name="busca_dataFim" value="'.$_POST['data_ate'].'">
				<input type="hidden" name="busca_empresa" value="'.$aMotorista['enti_nb_empresa'].'">
				<input type="hidden" name="acao" value="index">
				</form>
			<script>
				document.form_voltar.submit();
			</script>'
		;
		exit;
	}
	
	function status() {
		return  
			"<style>
				#statusDiv{
					display: inline-flex;
				}
				#status-label{
				margin-right: 10px; 
				
				}
				#status {
					margin-top: -5px;
					width: 93px;
				}
				</style>
				<div id='statusDiv'>
					<label id='status-label'>Status:</label>
					<select name='status' id='status' class='form-control input-sm' onchange='ajusta_ponto(".$_POST['id'].", null, \"".$_POST['data_de']."\",  \"".$_POST['data_ate']."\", this.value)'>
						<option value='ativo'>Ativos</option>
						<option value='inativo' ".((!empty($_POST['status']) && $_POST['status'] == 'inativo')? 'selected': '').">Inativos</option>
					</select>
				</div>"
		;
	}

	function pegarSqlDia(string $matricula): string{
		$condicoesPontoBasicas = [
			"ponto.pont_tx_status = 'ativo'",
			"ponto.pont_tx_matricula = '".$matricula."'"
		];

		$abriuJornadaHoje = mysqli_fetch_assoc(
			query(
				"SELECT * FROM ponto
					WHERE ".implode(" AND ", $condicoesPontoBasicas)."
						AND ponto.pont_tx_tipo = 1
						AND ponto.pont_tx_data LIKE '%".$_POST['data']."%'
					ORDER BY ponto.pont_tx_data ASC
					LIMIT 1;"
			)
		);

		//Definir data de início da query{
			if(empty($abriuJornadaHoje)){
				//Se não abriu uma jornada hoje, confere se há uma jornada aberta que veio de antes do dia.
				$temJornadaAberta = mysqli_fetch_assoc(
					query(
						"SELECT ponto.pont_tx_data, (ponto.pont_tx_tipo = 1) as temJornadaAberta FROM ponto
							WHERE ".implode(" AND ", $condicoesPontoBasicas)."
								AND ponto.pont_tx_tipo IN (1,2)
								AND pont_tx_data <= '".$_POST['data']." 00:00:00'
							ORDER BY pont_tx_data DESC
							LIMIT 1;"
					)
				);

				if(!empty($temJornadaAberta) && intval($temJornadaAberta['temJornadaAberta'])){
					//Se tem uma jornada que veio de antes do dia, confere se esta foi fechada hoje.
					$jornadaFechadaHoje = mysqli_fetch_assoc(
						query(
							"SELECT ponto.pont_tx_data, (ponto.pont_tx_tipo = 2) as jornadaFechadaHoje FROM ponto
								WHERE ".implode(" AND ", $condicoesPontoBasicas)."
									AND ponto.pont_tx_tipo IN (1,2)
									AND pont_tx_data LIKE '%".$_POST['data']."%'
								ORDER BY pont_tx_data ASC
								LIMIT 1;"
						)
					);
					if(!empty($jornadaFechadaHoje) && intval($jornadaFechadaHoje['jornadaFechadaHoje'])){
						//Se a jornada aberta antes do dia foi fechada, deve considerar apenas após esse fechamento.
						$sqlDataInicio = $jornadaFechadaHoje['pont_tx_data'];
					}else{
						//Se não, pega desde o momento em que a jornada foi aberta.
						$sqlDataInicio = $temJornadaAberta['pont_tx_data'];
					}
				}else{
					//Se não tem uma jornada que veio de antes, considera a partir de meia-noite de hoje.
					$sqlDataInicio = $_POST['data']." 00:00:00";
				}
			}else{
				//Se abriu jornada hoje, considera a partir da data de abertura da jornada.
				$sqlDataInicio = $abriuJornadaHoje['pont_tx_data'];
			}
		//}

		//Definir data de fim da query{
			$sqlDataFim = $_POST['data'].' 23:59:59';
			if(!empty($abriuJornadaHoje)){
				//Se abriu jornada hoje, confere se teve uma jornada aberta que seguiu pros dias seguintes
				$deixouJornadaAberta = mysqli_fetch_assoc(
					query(
						"SELECT ponto.pont_tx_data, (ponto.pont_tx_tipo = 1) as deixouJornadaAberta FROM ponto
							WHERE ".implode(" AND ", $condicoesPontoBasicas)."
								AND ponto.pont_tx_tipo IN (1,2)
								AND pont_tx_data <= '".$_POST['data']." 23:59:59'
							ORDER BY pont_tx_data DESC
							LIMIT 1;"
					)
				);
				if(!empty($deixouJornadaAberta) && intval($deixouJornadaAberta['deixouJornadaAberta'])){
					//Se deixou uma jornada aberta pros dias seguintes, confere se ela terminou.
					$fimJornada = mysqli_fetch_assoc(
						query(
							"SELECT ponto.pont_tx_data, (ponto.pont_tx_tipo = 2) as fimJornada FROM ponto
								WHERE ".implode(" AND ", $condicoesPontoBasicas)."
									AND ponto.pont_tx_tipo IN (1,2)
									AND pont_tx_data > '".$_POST['data']." 23:59:59'
								ORDER BY pont_tx_data ASC
								LIMIT 1;"
						)
					);
					if(!empty($fimJornada) && intval($fimJornada['fimJornada'])){
						//Se a jornada deixada aberta já foi finalizada, pega até o fechamento dessa jornada deixada.
						$sqlDataFim = $fimJornada['pont_tx_data'];
					}
				}
			}
		//}

		$condicoesPontoBasicas[0] = "ponto.pont_tx_status = '".$_POST['status']."'";
		
		$sql = 
			"SELECT * FROM ponto
				JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_tx_codigoInterno
				JOIN user ON ponto.pont_nb_user = user.user_nb_id
				LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id
				WHERE ".implode(" AND ", $condicoesPontoBasicas)."
					AND ponto.pont_tx_data >= '".$sqlDataInicio."'
					AND ponto.pont_tx_data <= '".$sqlDataFim."'
				ORDER BY pont_tx_data ASC"
		;

		return $sql;
	}

	function index(){
		global $CONTEX;

		if(empty($_POST['id']) || empty($_POST['data'])){
			echo '<script>alert("ERRO: Deve ser selecionado um motorista e uma data para ajustar.")</script>';

			echo 
				'<form action="https://braso.mobi'.$CONTEX['path'].'/espelho_ponto" name="form_voltar" method="post">
					<input type="hidden" name="data_de" value="'.$_POST['data_de'].'">
					<input type="hidden" name="data_ate" value="'.$_POST['data_ate'].'">
				</form>
				<script>
					document.form_voltar.submit();
				</script>'
			;
			exit;
		}else{
			$a_mod['data'] = $_POST['data'];
			$a_mod['id'] = $_POST['id'];
		}
		cabecalho('Ajuste de Ponto');

		if(empty($_POST['data_de']) && !empty($_POST['data'])){
			$_POST['data_de'] = $_POST['data'];
		}
		if(empty($_POST['data_ate']) && !empty($_POST['data'])){
			$_POST['data_ate'] = $_POST['data'];
		}

		$aMotorista = carregar('entidade',$_POST['id']);

		$sqlCheck = query("SELECT user_tx_login, endo_tx_dataCadastro FROM endosso, user 
			WHERE '".$_POST['data']."' BETWEEN endo_tx_de AND endo_tx_ate
				AND endo_nb_entidade = '".$aMotorista['enti_nb_id']."'
				AND endo_tx_matricula = '".$aMotorista['enti_tx_matricula']."' 
				AND endo_tx_status = 'ativo' 
				AND endo_nb_userCadastro = user_nb_id 
			LIMIT 1"
		);
		$aEndosso = carrega_array($sqlCheck);

		$botao_imprimir =
			'<button class="btn default" type="button" onclick="imprimir()">Imprimir</button >
					<script>
						function imprimir() {
							// Abrir a caixa de diálogo de impressão
							window.print();
						}
					</script>';


		if (empty($_POST['status'])) {
			$_POST['status'] = 'ativo';
		}

		$formStatus = "
		        <form name='form_ajuste_status' action='https://braso.mobi$CONTEX[path]/ajuste_ponto' method='post'>
					<input type='hidden' name='acao' value='index'>
					<input type='hidden' name='id'>
					<input type='hidden' name='data'>
					<input type='hidden' name='data_de'>
					<input type='hidden' name='data_ate'>
					<input type='hidden' name='status'>
				</form>
				<script>
				selecionarOpcaoPorValor('$_POST[status]');
				
				function selecionarOpcaoPorValor(valor) {
				    // Obtém o elemento select
				    var selectElement = document.getElementById('status');

                    // Define o valor da opção desejada como selecionado
                    selectElement.value = valor;
                }
				$(document).ready(function() {
                    // Adicione sua função aqui
                    var select = document.getElementById('status');
                    
                    select.addEventListener('change', function() {
                        var value = select.value;  // Correção aqui
                        console.log('O valor selecionado é: ' + value);
                        
                        ajusta_ponto($_POST[id], '$_POST[data]', '$_POST[data_de]',  '$_POST[data_ate]', value);
                    });
                });
                
                function ajusta_ponto(motorista, data, data_de, data_ate, status) {
                    console.log(motorista);
					document.form_ajuste_status.id.value = motorista;
					document.form_ajuste_status.data.value = data;
					document.form_ajuste_status.data_de.value = data_de;
					document.form_ajuste_status.data_ate.value = data_ate;
					document.form_ajuste_status.status.value = status;
					document.form_ajuste_status.submit();
				}
			</script>"
		;

		$c[] = texto('Matrícula',$aMotorista['enti_tx_matricula'],2);
		$c[] = texto('Motorista',$aMotorista['enti_tx_nome'],5);
		$c[] = texto('CPF',$aMotorista['enti_tx_cpf'],3);

		$_POST['status'] = (!empty($_POST['status']) && $_POST['status'] != 'undefined'? $_POST['status']: 'ativo');

		$c2[] = campo_data('Data', 'data', ($_POST['data']?? ''), 2, "onfocusout='ajusta_ponto(".$_POST['id'].", this.value, \"".$_POST['data_de']."\", \"".$_POST['data_ate']."\")', null");
		$c2[] = campo_hora('Hora','hora',$_POST['hora'],2);
		$c2[] = combo_bd('Código Macro','idMacro',$_POST['idMacro'],4,'macroponto','','ORDER BY macr_nb_id ASC');
		$c2[] = combo_bd('Motivo:','motivo',$_POST['motivo'],4,'motivo','',' AND moti_tx_tipo = "Ajuste"');

		$c3[] = textarea('Justificativa:','descricao',$_POST['descricao'],12);

		if(!empty($aEndosso) && count($aEndosso) > 0){
			$c2[] = texto('Endosso:',"Endossado por ".$aEndosso['user_tx_login']." em ".data($aEndosso['endo_tx_dataCadastro'],1),6);
		}else{
			$botao[] = botao('Gravar','cadastrarAjuste','id,busca_motorista,data_de,data_ate,data,busca_data',"$_POST[id],$_POST[id],$_POST[data_de],$_POST[data_ate],$_POST[data],".substr($_POST['data'],0, -3));
			$iconeExcluir = "icone_excluir_ajuste(pont_nb_id,excluir_ponto,idEntidade,".$_POST['data_de'].",".$_POST['data_ate'].",".strval($_POST['id']).")"; //Utilizado em grid()
		}
		$botao[] = $botao_imprimir;
		$botao[] = botao(
			'Voltar', 
			'voltar', 
			'data_de,data_ate,id,busca_empresa,busca_motorista,data,busca_data', 
			($_POST['data_de']??'').",".($_POST['data_ate']??'').",".$_POST['id'].",".$aMotorista['enti_nb_empresa'].",".$_POST['id'].",".$_POST['data'].",".substr($_POST['data'], 0, -3)
		);
		$botao[] = status();
		
		abre_form('Dados do Ajuste de Ponto');
		linha_form($c);
		linha_form($c2);
		linha_form($c3);
		fecha_form($botao);

		$sql = pegarSqlDia($aMotorista['enti_tx_matricula']);
		$justificativa = 'pont_tx_descricao';
		if($_POST['status'] === 'inativo'){
		    $justificativa = 'pont_tx_justificativa';
		}

		$gridFields = [
			'CÓD'												=> 'pont_nb_id',
			'DATA'												=> 'data(pont_tx_data, 1)',
			'TIPO'												=> 'macr_tx_nome',
			'MOTIVO'											=> 'moti_tx_nome',
			'LEGENDA'											=> 'moti_tx_legenda',
			'JUSTIFICATIVA'										=> $justificativa,
			'USUÁRIO'											=> 'user_tx_login',
			'DATA CADASTRO'										=> 'data(pont_tx_dataCadastro,1)',
			'<spam class="glyphicon glyphicon-remove"></spam>'	=> $iconeExcluir
		];
		
		grid($sql, array_keys($gridFields), array_values($gridFields), '', '', 1, 'desc', -1);

		echo
			"<form name='form_ajuste_status' action='https://braso.mobi".$CONTEX['path']."/ajuste_ponto' method='post'>
				<input type='hidden' name='acao' value='index'>
				<input type='hidden' name='id'>
				<input type='hidden' name='data'>
				<input type='hidden' name='data_de'>
				<input type='hidden' name='data_ate'>
				<input type='hidden' name='status'>
			</form>
			<script>
				valorDataInicial = document.getElementById('data').value;
				valorStatusInicial = document.getElementById('status').value;
				function ajusta_ponto(motorista, data, data_de, data_ate, status) {
					if(data == null){
						data = document.getElementById('data').value;
					}
					if(status == null){
						status = document.getElementById('status').value;
					}

					if(valorDataInicial != data || valorStatusInicial != status){
						document.form_ajuste_status.id.value = motorista;
						document.form_ajuste_status.data.value = data;
						document.form_ajuste_status.data_de.value = data_de;
						document.form_ajuste_status.data_ate.value = data_ate;
						document.form_ajuste_status.status.value = status;
						document.getElementById('status').value = status;
						document.form_ajuste_status.submit();
					}
				}
			</script>"
		;

		rodape();
	}
?>