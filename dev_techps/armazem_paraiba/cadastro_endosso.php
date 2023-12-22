<?php
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/
	include "funcoes_ponto.php";
	

	function checkbox2($nome, $variavel, $modificador, $tamanho){
		$campo = 
			'<div class="col-sm-' . $tamanho . ' margin-bottom-5">
				<label><b>' . $nome . '</b></label><br>
				<label class="radio-inline">
					<input type="radio" id="sim" name="pagar_horas" value="sim" '.($modificador == 'sim'? 'checked': '').'> Sim
				</label>
				<label class="radio-inline">
					<input type="radio" id="nao" name="pagar_horas" value="nao"'.($modificador == 'nao'? 'checked': '').'> Não
				</label>
			</div>
		
			<div id="' . $variavel . '" class="col-sm-' . $tamanho . ' margin-bottom-5" style="display: none;">
				<label><b>Quantidade de Horas:</b></label>
				<input class="form-control input-sm" type="time" id="outroCampo" name="quandHoras" autocomplete="off" '.(!empty($_POST['quandHoras'])? 'value = "'.$_POST['quandHoras'].'"':'').'>
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
			$show_error = False;
			$error_msg = 'Há campos obrigatórios não preenchidos: ';
			if(!isset($_POST['empresa'])){
				if($_SESSION['user_tx_nivel'] == 'Super Administrador'){
					$show_error = True;
					$error_msg .= 'Empresa, ';
				}else{
					$_POST['empresa'] = $_SESSION['user_tx_emprCnpj'];
				}
			}
			if(!isset($_POST['busca_motorista'])){
				$show_error = True;
				$error_msg .= 'Motorista, ';
			}
			if(empty($_POST['data_de']) || empty($_POST['data_ate'])){
				$show_error = True;
				$error_msg .= 'Data, ';
			}
			if(!$show_error){
				$error_msg = '';
			}
			if($show_error){
				echo "<script>alert('".substr($error_msg, 0, strlen($error_msg)-2)."')</script>";
				index();
				return;
			}
		//}

		//Conferir se o endosso tem mais de um mês{
			$difference = strtotime($_POST['data_ate']) - strtotime($_POST['data_de']);
			$qttDays = floor($difference / (60 * 60 * 24));
			if($qttDays > 31){
				$show_error = True;
				$error_msg = 'Não é possível cadastrar um endosso com mais de um mês.';
			}
		//}
		//Conferir se não está entrelaçada com outro endosso
		$endossos = mysqli_fetch_array(
			query("
				SELECT endo_tx_de, endo_tx_ate from endosso
					WHERE endo_nb_entidade = ".$_POST['busca_motorista']."
						AND NOT(
							(endo_tx_ate < '".$_POST['data_de']."') OR ('".$_POST['data_ate']."' < endo_tx_de)
						) LIMIT 1;
			")
		);
		// print_r(count($endossos));
		if(count($endossos) > 0){
			$show_error = True;
			$error_msg = 'Já há um endosso para este motorista nesta faixa de tempo.  ';
		}

		if($show_error){
			echo "<script>alert('".substr($error_msg, 0, strlen($error_msg)-2)."')</script>";
			index();
			return;
		}

		$sql = query(
			"SELECT entidade.*, empresa.empr_nb_cidade, cidade.cida_nb_id, cidade.cida_tx_uf, parametro.para_tx_acordo FROM entidade
				JOIN empresa ON enti_nb_empresa = empr_nb_id
				JOIN cidade ON empr_nb_cidade = cida_nb_id
				JOIN parametro ON enti_nb_parametro = para_nb_id
				WHERE enti_tx_status != 'inativo' AND enti_nb_id = '".$_POST['busca_motorista']."' LIMIT 1"
		);
		$motorista = carrega_array($sql);

		//<Pegar dados do ponto>
			$date = new DateTime($_POST['data_de']);
			$month = $date->format('m');
			$year = $date->format('Y');
			$aDia = [];
			$campos = [
				'data', 'diaSemana', 'inicioJornada', 'inicioRefeicao', 
				'fimRefeicao', 'fimJornada', 'diffRefeicao', 'diffEspera', 'diffDescanso', 'diffRepouso', 
				'diffJornada', 'diffJornadaEfetiva', 'jornadaPrevista', 'intersticio', 'he50', 'he100', 
				'adicionalNoturno', 'esperaIndenizada', 'moti_tx_motivo'
			];
			for ($i = 0; $i <= $qttDays; $i++) {
				$dataVez = strtotime($_POST['data_de']);
				$dataVez = date('Y-m-d', $dataVez+($i*60*60*24));
				$aDetalhado = diaDetalheEndosso2($motorista, $dataVez);

				$aDetalhadoCampos = [];
				foreach($campos as $campo){
					$aDetalhadoCampos[] = $aDetalhado[$campo];
				}

				$row = array_values(array_merge([verificaTolerancia($aDetalhado['diffSaldo'], $dataVez, $motorista['enti_nb_id'])], $aDetalhadoCampos));
				for ($f = 0; $f < sizeof($row) - 1; $f++) {
					if ($row[$f] == "00:00") {
						$row[$f] = "";
					}
				}
				$aDia[] = $row;
				print_r($row);
				print_r('<br><br>');
			}
			unset($campos);

			$sqlEndosso = query("SELECT endo_tx_dataCadastro, endo_tx_ate, endo_tx_horasApagar, endo_tx_pagarHoras FROM endosso WHERE endo_tx_matricula = '$motorista[enti_tx_matricula]'");
			$aEndosso = carrega_array($sqlEndosso);

			$lastMonthDate = date('Y-m', strtotime('-1 month', strtotime($year.'-'.$month.'-01')));
			$qttDays = cal_days_in_month(CAL_GREGORIAN, $month-1, $year);
			$saldoAnterior = '00:00';
			
			$lastMonthDay  = $lastMonthDate.'-'.str_pad($i, 2, 0, STR_PAD_LEFT);
			$saldoPassado  = diaDetalheEndosso($motorista['enti_tx_matricula'], $lastMonthDay)['diffSaldo'];
			$saldoAnterior = somarHorarios([$saldoAnterior, $saldoPassado]);
			
			$sqlMotorista = query(
				"SELECT * FROM entidade".
					" LEFT JOIN parametro ON enti_nb_parametro = para_nb_id".
					" WHERE enti_tx_tipo = 'Motorista'".
					" AND enti_nb_id IN (".$motorista['enti_nb_id'].")".
					" AND enti_nb_empresa = ".$motorista['enti_nb_empresa'].
					" ORDER BY enti_tx_nome"
			);
			$dadosMotorista = carrega_array($sqlMotorista);

			/*
			$teste = strtotime("2023-10-28 11:54:34");
			$teste = date('Y-m-d h:i:s', $teste+10); //Soma 10 segundos
			echo $teste;
			*/

			$dataCicloProx = strtotime($dadosMotorista['para_tx_inicioAcordo']);
			while($dataCicloProx < strtotime($aEndosso['endo_tx_ate'])){
				$dataCicloProx += intval($dadosMotorista['para_nb_qDias'])*60*60*24;
			}
			$dataCicloAnt = $dataCicloProx - intval($dadosMotorista['para_nb_qDias'])*60*60*24;

			$dataCicloProx = date('Y-m-d', $dataCicloProx);
			$dataCicloAnt  = date('Y-m-d', $dataCicloAnt);


			if($aEndosso['endo_tx_dataCadastro'] > $dataCicloProx){
				//Obrigar a pagar horas extras
				
				// $horasObrigatorias = ???
			}
			/*
				Fazer as contas pra relacionar o dia do cadastro do endosso com o dia do cadastro do parâmetro e conferir
			se o endosso está dentro da faixa de tempo em que deve ser obrigado pagar hora extra ou não
			Se(endossoCadastroData > ultimoCicloData){
				Obrigar pagar horas extras entre $dataCicloAnt e $dataCicloProx
				(horas que devem ser obrigadas a pagar: endo_tx_saldo + somaSaldos(endo_tx_ate, $dataCicloProx))
				$horasObrigatorias = endo_tx_saldo + somaSaldos(endo_tx_ate, $dataCicloProx)
			}
			*/


			if (isset($dadosMotorista['para_nb_qDias']) && $dadosMotorista['para_nb_qDias'] != null) { //Deve ser feito somente quando for obrigado a pagar a hora extra?

				//Contexto do HE100
				// $he100 = strtotime($aDetalhado['he100']);
				$he100 = explode(':', $aDetalhado['he100']);
				$he100 = intval($he100[0]) * 60 + ($he100[0][0] == '-' ? -1 : 1) * intval($he100[1]);
				
				$he50_pagar = explode(':', $totalResumo['he50']);
				$he50_pagar = intval($he50_pagar[0]) * 60 + ($he50_pagar[0][0] == '-' ? -1 : 1) * intval($he50_pagar[1]);
				
				$he100_pagar = explode(':', $totalResumo['he100']);
				$he100_pagar = intval($he100_pagar[0]) * 60 + ($he100_pagar[0][0] == '-' ? -1 : 1) * intval($he100_pagar[1]);

				$saldoPeriodo = explode(':', $totalResumo['diffSaldo']);
				$saldoPeriodo = intval($saldoPeriodo[0]) * 60 + ($saldoPeriodo[0][0] == '-' ? -1 : 1) * intval($saldoPeriodo[1]);
				
				if ($saldoPeriodo <= 0) {
					# Não faz nada
				} else {
					if ($he100_pagar > 0) {
						$transferir = $saldoPeriodo - (($saldoPeriodo > $he100) ? $he100 : 0);

						$saldoPeriodo -= $transferir;
						$he100_pagar += $transferir;
						$totalResumo['he100'] = intval($he100_pagar / 60) . ':' . ($he100_pagar - intval($he100_pagar / 60) * 60);
					}
				}

				//Contexto do HE50
				if($aEndosso['endo_tx_pagarHoras'] == 'sim'){
					if($saldoPeriodo > $aEndosso['endo_tx_horasApagar']){
						$transferir = $aEndosso['endo_tx_horasApagar'];
					}else{
						$transferir = $saldoPeriodo;
					}
					$saldoPeriodo -= $transferir;

					$he50_pagar += $transferir;
					$totalResumo['he50'] = intval($he50_pagar / 60) . ':' . ($he50_pagar - intval($he50_pagar / 60) * 60);
				}

				$saldoPeriodo = strtotime($saldoPeriodo);
				$totalResumo['diffSaldo'] = date('Y-m-d: h:i:s', $saldoPeriodo);
			}

			$saldoAtual = somarHorarios([$saldoAnterior, $totalResumo['diffSaldo']]);

			// unset($aDia);
		//</Pegar dados do ponto>

		$novo_endosso = [
			'endo_nb_entidade' 		=> $motorista['enti_nb_id'],
			'endo_tx_matricula' 	=> $motorista['enti_tx_matricula'],
			'endo_tx_mes' 			=> substr($_POST['data_de'], 0, 8).'01',
			'endo_tx_de' 			=> $_POST['data_de'],
			'endo_tx_ate' 			=> $_POST['data_ate'],
			'endo_tx_dataCadastro' 	=> date('Y-m-d h:i:s'),
			'endo_nb_userCadastro' 	=> $_SESSION['user_nb_id'],
			'endo_tx_status' 		=> 'ativo',
			'endo_tx_pagarHoras' 	=> $_POST['pagar_horas'],
			'endo_tx_horasApagar' 	=> $_POST['quandHoras'],
			'endo_tx_pontos'		=> $aDia,
			'totalResumo'			=> $totalResumo
		];
		
		// print_r($novo_endosso);
		// inserir('endosso', array_keys($novo_endosso), array_values($novo_endosso));

		index();
		return;
	}

	function load_js_functions(){
		global $CONTEX;
		?><script>
			function selecionaMotorista(idEmpresa) {
				let buscaExtra = encodeURI('AND enti_tx_tipo = "Motorista"'+
					(idEmpresa > 0? ' AND enti_nb_empresa = "'+idEmpresa+'"': '')
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
						url: "/contex20/select2.php?path=<?=$CONTEX['path']?>&tabela=entidade&extra_ordem=&extra_limite=15&extra_bd="+buscaExtra+"&extra_busca=enti_tx_matricula",
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

		$extra_bd_motorista = ' AND enti_tx_tipo = "Motorista"';
		if($_SESSION['user_tx_nivel'] != 'Super Administrador'){
			$extra_bd_motorista .= 'AND enti_nb_empresa = '.$_SESSION['user_tx_emprCnpj'];
		}

		$c = [
			campo_data('De*:','data_de',$_POST['data_de']?? '',2),
			campo_data('Ate*:','data_ate',$_POST['data_ate']?? '',2),
			combo_net('Motorista*:','busca_motorista',$_POST['busca_motorista']?? '',4,'entidade','',$extra_bd_motorista,'enti_tx_matricula'),
			checkbox2('Pagar Horas Extras', 'horasApagar', $_POST['pagar_horas']?? '', 2)
		];
		if($_SESSION['user_tx_nivel'] == 'Super Administrador'){
			array_unshift($c, combo_net('Empresa*:','empresa',$_POST['empresa']?? '',4,'empresa', 'onchange=selecionaMotorista(this.value)'));
		}
		$b = [
			botao('Voltar', 'voltar'),
			botao('Cadastrar Endosso', 'cadastrar')
		];

		abre_form();
		linha_form($c);
		fecha_form($b);
		
		rodape();

		load_js_functions();
	}
?>