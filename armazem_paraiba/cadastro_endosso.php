<?php
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/

	include "funcoes_ponto.php"; // conecta.php importado dentro de funcoes_ponto	

	function checkbox2($nome, $variavel, $modificador, $tamanho){
		$campo = 
			'<div class="col-sm-' . $tamanho . ' margin-bottom-5" style="min-width:200px;">
				<label><b>' . $nome . '</b></label><br>
				<label class="radio-inline">
					<input type="radio" id="sim" name="pagar_horas" value="sim" '.($modificador == 'sim'? 'checked': '').'> Sim
				</label>
				<label class="radio-inline">
          <input type="radio" id="nao" name="pagar_horas" value="nao"'.(($modificador == 'nao' || empty($modificador))? 'checked': '').'> Não
				</label>
			</div>
		
			<div id="' . $variavel . '" class="col-sm-' . $tamanho . ' margin-bottom-5" style="display: none;">
				<label><b>Máximo de horas extras a pagar:</b></label>
				<input class="form-control input-sm" type="time" id="outroCampo" name="quantHoras" autocomplete="off" value = "'.(!empty($_POST['quantHoras'])? $_POST['quantHoras']:'00:00').'">
			</div>
			<script>
				const radioSim = document.getElementById("sim");
				const radioNao = document.getElementById("nao");
				const campo = document.getElementById("' . $variavel . '");
				if (radioSim.checked) {
						campo.style.display = ""; // Exibe o campo quando "Mostrar Campo" é selecionado
				}
				
				// Adicionando um ouvinte de eventos aos elementos de rádio
				radioSim.addEventListener("change", function() {
					if (radioSim.checked) {
						campo.style.display = ""; // Exibe o campo quando "Mostrar Campo" é selecionado
					}
				});
				
				radioNao.addEventListener("change", function() {
				if (radioNao.checked) {
					campo.style.display = "none"; // Oculta o campo quando "Não Mostrar Campo" é selecionado
				}
				});
			</script>'
		;

		return $campo;
	}

	function voltar(){
		header('Location: '.$_SERVER['REQUEST_URI'].'/../endosso');
	}

	function cadastrar(){
		global $totalResumo;

		//Conferir se os campos obrigatórios estão preenchidos{
			$showError = False;
			$errorMsg = 'Há campos obrigatórios não preenchidos: ';
			if(!isset($_POST['empresa'])){
				if($_SESSION['user_tx_nivel'] == 'Super Administrador'){
					$showError = True;
					$errorMsg .= 'Empresa, ';
				}else{
					$_POST['empresa'] = $_SESSION['user_nb_empresa'];
				}
			}
			if(empty($_POST['data_de']) || empty($_POST['data_ate'])){
				$showError = True;
				$errorMsg .= 'Data, ';
			}
			if(!$showError){
				$errorMsg = '';
			}
			if($showError){
				set_status('ERRO: '.substr($errorMsg, 0, strlen($errorMsg)-2));
				index();
				return;
			}
		//}

		//Conferir se o endosso tem mais de um mês{
			$difference = strtotime($_POST['data_ate']) - strtotime($_POST['data_de']);
			$qttDays = floor($difference / (60 * 60 * 24));
			if($qttDays > 31){
				$showError = True;
				$errorMsg = 'Não é possível cadastrar um endosso com mais de um mês.';
			}
			unset($difference);
		//}

		$queryMotoristas = 
			"SELECT entidade.*, empresa.empr_nb_cidade, cidade.cida_nb_id, cidade.cida_tx_uf, parametro.para_tx_acordo FROM entidade
				JOIN empresa ON enti_nb_empresa = empr_nb_id
				JOIN cidade ON empr_nb_cidade = cida_nb_id
				JOIN parametro ON enti_nb_parametro = para_nb_id
				WHERE enti_tx_status != 'inativo'";

		if(!isset($_POST['busca_motorista'])){
			$queryMotoristas .= " AND enti_nb_empresa = ".$_POST['empresa']."";
		}else{
			$queryMotoristas .= " AND enti_nb_id = ".$_POST['busca_motorista']."";
		}

		$motoristas = mysqli_fetch_all(
			query($queryMotoristas),
			MYSQLI_ASSOC
		);

		$novosEndossos = [];

		foreach($motoristas as $motorista){
			$showError = False;
			//Conferir se está entrelaçado com outro endosso{
				$endossosMotorista = mysqli_fetch_all(
					query(
						"SELECT endo_tx_de, endo_tx_ate FROM endosso
							WHERE endo_nb_entidade = ".$motorista['enti_nb_id']."
								AND (
									(endo_tx_ate >= '".$_POST['data_de']."') 
									AND ('".$_POST['data_ate']."' >= endo_tx_de)
								)
								AND endo_tx_status != 'inativo'
							LIMIT 1;"
					),
					MYSQLI_ASSOC
				);
				if(count($endossosMotorista) > 0){
					$showError = True;
					$endossosMotorista[0]['endo_tx_de'] = explode('-', $endossosMotorista[0]['endo_tx_de']);
					$endossosMotorista[0]['endo_tx_de'] = sprintf('%02d/%02d/%04d', $endossosMotorista[0]['endo_tx_de'][2], $endossosMotorista[0]['endo_tx_de'][1], $endossosMotorista[0]['endo_tx_de'][0]);
					$endossosMotorista[0]['endo_tx_ate'] = explode('-', $endossosMotorista[0]['endo_tx_ate']);
					$endossosMotorista[0]['endo_tx_ate'] = sprintf('%02d/%02d/%04d', $endossosMotorista[0]['endo_tx_ate'][2], $endossosMotorista[0]['endo_tx_ate'][1], $endossosMotorista[0]['endo_tx_ate'][0]);
					$errorMsg = 'Já endossado de '.$endossosMotorista[0]['endo_tx_de'].' até '.$endossosMotorista[0]['endo_tx_ate'].'.  ';
				}else{
					
				}
				unset($endossosMotorista);
			//}

			//Conferir se é o primeiro endosso que está sendo registrado{
				if(!$showError){
					$primEndosso = mysqli_fetch_all(
						query(
							"SELECT * FROM endosso 
								WHERE endo_tx_matricula = '".$motorista['enti_tx_matricula']."'
									AND endo_tx_status != 'inativo'
								ORDER BY endo_tx_de DESC
								LIMIT 1"
						), 
						MYSQLI_ASSOC
					);
					if(count($primEndosso) == 0){
						$primEndosso = true;
					}else{
						//Conferir se o endosso que está sendo feito vem antes do primeiro{
							if($_POST['data_ate'] < $primEndosso[0]['endo_tx_de']){
								$errorMsg = 'Não é possível endossar antes do primeiro endosso.  ';
								$showError = true;
							}
							$primEndosso = false;
						//}
					}
				}
			//}

			//Conferir se tem espaço entre o último endosso e o endosso atual{
				if(!$showError){
					$ultimoEndosso = mysqli_fetch_all(
						query(
							"SELECT * FROM `endosso`
								WHERE endo_tx_matricula = '".$motorista['enti_tx_matricula']."'
									AND endo_tx_ate < '".$_POST['data_de']."'
									AND endo_tx_status = 'ativo'
								ORDER BY endo_tx_ate DESC
								LIMIT 1;"
						),
						MYSQLI_ASSOC
					);
					if(is_array($ultimoEndosso) && count($ultimoEndosso) > 0 && !$primEndosso){ //Se possui um último Endosso
						$ultimoEndosso = $ultimoEndosso[0];
						$ultimoEndosso['endo_tx_ate'] = DateTime::createFromFormat('Y-m-d', $ultimoEndosso['endo_tx_ate']);
						$dataDe = DateTime::createFromFormat('Y-m-d', $_POST['data_de']);
						$qtdDias = date_diff($ultimoEndosso['endo_tx_ate'], $dataDe);
						if($qtdDias->d > 1){
							$showError = True;
							$errorMsg = 'Há um tempo não endossado entre '.$ultimoEndosso['endo_tx_ate']->format('d/m/Y').' e '.$dataDe->format('d/m/Y').'.  ';
						}
					}else{ //Se é o primeiro endosso sendo feito para este motorista
						if(isset($motorista['enti_tx_banco'])){
							$ultimoEndosso['endo_tx_saldo'] = $motorista['enti_tx_banco'];
						}else{
							$ultimoEndosso['endo_tx_saldo'] = '00:00';
						}
					}
				}
			//}

			if($showError){
				$novoEndosso = [
					'endo_nb_entidade' 	=> $motorista['enti_nb_id'],
					'endo_tx_matricula' => $motorista['enti_tx_matricula'],
					'error' 			=> True,
					'errorMsg' 			=> '['.$motorista['enti_tx_matricula'].'] '.$motorista['enti_tx_nome'].': '.substr($errorMsg, 0, strlen($errorMsg)-2)
				];
				$novosEndossos[] = $novoEndosso;
				continue;
			}

			//<Pegar dados do ponto>
				$aDia = [];
				for ($i = 0; $i <= $qttDays; $i++) {
					$dataVez = strtotime($_POST['data_de']);
					$dataVez = date('Y-m-d', $dataVez+($i*60*60*24));
					$aDetalhado = diaDetalhePonto($motorista['enti_tx_matricula'], $dataVez);

					$row = array_values(array_merge([verificaTolerancia($aDetalhado['diffSaldo'], $dataVez, $motorista['enti_nb_id'])], $aDetalhado));
					for ($f = 0; $f < sizeof($row) - 1; $f++) {
						if(is_int(strpos($row[$f], 'ajusta_ponto('))){
							$begin = strpos($row[$f], 'ajusta_ponto(');
							$end = strpos($row[$f], ')', $begin);
							$row[$f] = substr($row[$f], 0, $begin).'avisar_ponto_endossado()'.substr($row[$f], $end+1);
						}
						if(is_int(strpos($row[$f], "Ajuste de Ponto"))){
							$row[$f] = str_replace("Ajuste de Ponto", "Ajuste de Ponto(endossado)", $row[$f]);
							$row[$f] = str_replace("class=\"fa fa-circle\">", "class=\"fa fa-circle\">(E)", $row[$f]);
						}
						if ($row[$f] == "00:00") {
							$row[$f] = "";
						}
					}
					$aDia[] = $row;
				}

				$sqlEndosso = query("SELECT endo_tx_dataCadastro, endo_tx_ate, endo_tx_horasApagar, endo_tx_pagarHoras FROM endosso WHERE endo_tx_matricula = '$motorista[enti_tx_matricula]'");
				$aEndosso = carrega_array($sqlEndosso);

				$saldoAnterior = $ultimoEndosso['endo_tx_saldo'];
				
				$sqlMotorista = query(
					"SELECT * FROM entidade".
						" LEFT JOIN parametro ON enti_nb_parametro = para_nb_id".
						" WHERE enti_tx_tipo IN ('Motorista', 'Ajudante')".
						" AND enti_nb_id IN (".$motorista['enti_nb_id'].")".
						" AND enti_nb_empresa = ".$motorista['enti_nb_empresa'].
						" ORDER BY enti_tx_nome"
				);
				$dadosMotorista = carrega_array($sqlMotorista);

				if(!empty($dadosMotorista['para_nb_qDias'])){
					$dataCicloProx = strtotime($dadosMotorista['para_tx_inicioAcordo']);
				    while($dataCicloProx < strtotime($aEndosso['endo_tx_ate'])){
    					$dataCicloProx += intval($dadosMotorista['para_nb_qDias'])*60*60*24;
    				}
    				$dataCicloAnt = $dataCicloProx - intval($dadosMotorista['para_nb_qDias'])*60*60*24;
    
    				$dataCicloProx = date('Y-m-d', $dataCicloProx);
    				$dataCicloAnt  = date('Y-m-d', $dataCicloAnt);
				}else{
				    $dataCicloProx = '--/--/----';
				    $dataCicloAnt  = '--/--/----';
				}

				$saldoAtual = operarHorarios([$saldoAnterior, $totalResumo['diffSaldo']], '+');
				$saldoAtual = operarHorarios([$saldoAtual, $totalResumo['he50'], $totalResumo['he100']], '-');

				$totalResumo['saldoAnterior'] = $saldoAnterior;
				$totalResumo['saldoAtual'] = $saldoAtual;

				// unset($aDia);
			//</Pegar dados do ponto>

			$novoEndosso = [
				'endo_nb_entidade' 		=> $motorista['enti_nb_id'],
				'endo_tx_nome' 			=> $motorista['enti_tx_nome'],
				'endo_tx_matricula' 	=> $motorista['enti_tx_matricula'],
				'endo_tx_mes' 			=> substr($_POST['data_de'], 0, 8).'01',
				'endo_tx_saldo' 		=> operarHorarios([$totalResumo['saldoAnterior'], $totalResumo['diffSaldo']], '+'),
				'endo_tx_de' 			=> $_POST['data_de'],
				'endo_tx_ate' 			=> $_POST['data_ate'],
				'endo_tx_dataCadastro' 	=> date('Y-m-d h:i:s'),
				'endo_nb_userCadastro' 	=> $_SESSION['user_nb_id'],
				'endo_tx_status' 		=> 'ativo',
				'endo_tx_pagarHoras' 	=> $_POST['pagar_horas']?? 'nao',
				'endo_tx_horasApagar' 	=> $_POST['quantHoras']?? '00:00',
				'endo_tx_pontos'		=> $aDia,
				'totalResumo'			=> $totalResumo
			];

			$novoEndosso['endo_tx_pontos'] = json_encode($novoEndosso['endo_tx_pontos']);
			$novoEndosso['totalResumo'] = json_encode($novoEndosso['totalResumo']);
			
			$novoEndosso['endo_tx_pontos'] = str_replace('<\/', '</', $novoEndosso['endo_tx_pontos']);
			$novoEndosso['totalResumo'] = str_replace('<\/', '</', $novoEndosso['totalResumo']);

			$novosEndossos[] = $novoEndosso;
			$totalResumo = ['diffRefeicao' => '00:00', 'diffEspera' => '00:00', 'diffDescanso' => '00:00', 'diffRepouso' => '00:00', 'diffJornada' => '00:00', 'jornadaPrevista' => '00:00', 'diffJornadaEfetiva' => '00:00', 'maximoDirecaoContinua' => '', 'intersticio' => '00:00', 'he50' => '00:00', 'he100' => '00:00', 'adicionalNoturno' => '00:00', 'esperaIndenizada' => '00:00', 'diffSaldo' => '00:00'];
		}

		$showError = False;
		$errorMsg = '<br><br>ERRO(S):<br>';

		$showSuccess = False;
		$successMsg = '<br><br>Registro(s) inserido(s) com sucesso!<br><br>Horas a pagar:<br>';
		
		foreach($novosEndossos as $novoEndosso){
			if(isset($novoEndosso['error'])){
				$showError = True;
				$errorMsg .= $novoEndosso['errorMsg'].'<br>';
				continue;
			}
			
			$showSuccess = True;
			$he50 = json_decode($novoEndosso['totalResumo']);
			$he50 = $he50->he50;
			if($he50 > $novoEndosso['endo_tx_horasApagar']){
				$he50 = $novoEndosso['endo_tx_horasApagar'];
			}
			$successMsg .= '- ['.$novoEndosso['endo_tx_matricula'].'] '.$novoEndosso['endo_tx_nome'].': '.$he50.'<br>';
			
			//*
			$filename = md5($novoEndosso['endo_tx_matricula'].$novoEndosso['endo_tx_mes']);
			if(!is_dir("./arquivos/endosso")){
				mkdir("./arquivos/endosso");
			}
			$path = './arquivos/endosso/';
			if(file_exists($path.$filename.'.csv')){
				$version = 2;
				while(file_exists($path.$filename.'_'.strval($version).'.csv')){
					$version++;
				}
				$filename = $filename.'_'.strval($version);
			}
			$novoEndosso['endo_tx_filename'] = $filename;
			$file = fopen($path.$filename.'.csv', 'w');
			fputcsv($file, array_keys($novoEndosso));
			fputcsv($file, array_values($novoEndosso));
			fclose($file);
			
			unset($novoEndosso['endo_tx_pontos']);
			unset($novoEndosso['totalResumo']);
			unset($novoEndosso['endo_tx_nome']);
			
			inserir('endosso', array_keys($novoEndosso), array_values($novoEndosso));
			//*/
		}

		$statusMsg = ($showSuccess? $successMsg: '').($showError? $errorMsg: '');
		set_status($statusMsg);

		index();
		exit;
	}

	function carregarJS(){
		global $CONTEX;
		?><script>
			function selecionaMotorista(idEmpresa) {
				let buscaExtra = encodeURI("AND enti_tx_tipo IN ('Motorista', 'Ajudante')"+
					(idEmpresa > 0? " AND enti_nb_empresa = '"+idEmpresa+"'": "")
				);

				if ($('.busca_motorista').data('select2')) {// Verifica se o elemento está usando Select2 antes de destruí-lo
					$('.busca_motorista').select2('destroy');
				}

				$.fn.select2.defaults.set("theme", "bootstrap");
				$('.busca_motorista').select2({
					language: 'pt-BR',
					placeholder: 'Selecione um item',
					allowClear: true,
					ajax: {
						url: "<?=$CONTEX['path']?>/../contex20/select2.php?path=<?=$CONTEX['path']?>&tabela=entidade&extra_ordem=&extra_limite=15&extra_bd="+buscaExtra+"&extra_busca=enti_tx_matricula",
						dataType: 'json',
						delay: 250,
						processResults: function(data){
							return {results: data};
						},
						cache: true
					}
				});
			}
		</script><?php

	}

	function index(){

		if(!empty($_GET['test'])){
			$_GET['test'] = explode(', ', $_GET['test']);
			$_POST['empresa'] = intval($_GET['test'][0]);
			$_POST['data_de'] = $_GET['test'][1];
			$_POST['data_ate'] = $_GET['test'][2];
			$_POST['busca_motorista'] = intval($_GET['test'][3]);
		}
		// $url = explode('/', $_SERVER['SCRIPT_URL']);
		// $url = implode('/', [$url[0], $url[1], $url[2]]);

		cabecalho('Cadastro Endosso');

		$extra_bd_motorista = " AND enti_tx_tipo IN ('Motorista', 'Ajudante')";
		if($_SESSION['user_tx_nivel'] != 'Super Administrador'){
			$extra_bd_motorista .= ' AND enti_nb_empresa = '.$_SESSION['user_tx_emprCnpj'];
		}
		if(!empty($_POST['empresa'])){
			$extra_bd_motorista .= ' AND enti_nb_empresa = '.$_POST['empresa'];
		}

		$c = [
			combo_net('Motorista*:','busca_motorista',$_POST['busca_motorista']?? '',4,'entidade','',$extra_bd_motorista,'enti_tx_matricula'),
			campo_data('De*:','data_de',$_POST['data_de']?? '',2),
			campo_data('Ate*:','data_ate',$_POST['data_ate']?? '',2),
			checkbox2('Pagar Horas Extras', 'horasApagar', $_POST['pagar_horas']?? '', 3)
		];
		if(is_int(strpos($_SESSION['user_tx_nivel'], "Administrador"))){
			array_unshift($c, combo_net('Empresa*:','empresa', $_POST['empresa']?? '',4,'empresa', 'onchange=selecionaMotorista(this.value)'));
		}else{
			array_unshift($c, campo_hidden('empresa', $_SESSION['user_nb_empresa']));
		}
		$b = [
			botao('Voltar', 'voltar'),
			botao('Cadastrar Endosso', 'cadastrar')
		];

		abre_form();
		linha_form($c);
		fecha_form($b);
		
		rodape();

		carregarJS();
	}
?>