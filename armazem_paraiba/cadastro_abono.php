<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/

	include "funcoes_ponto.php";

	function cadastra_abono(){
		// Conferir se os campos obrigatórios estão preenchidos{
			$campos_obrigatorios = ['daterange' => 'Data', 'abono' => 'Horas', 'motivo' => 'Motivo'];
			$error = false;
			$errorMsg = '';
			foreach(array_keys($campos_obrigatorios) as $campo){
				if(!isset($_POST[$campo]) || empty($_POST[$campo])){
					$error = true;
					$errorMsg .= $campos_obrigatorios[$campo].', ';
				}
			}

			if($error){
				set_status('ERRO: Campos obrigatórios não preenchidos: '. substr($errorMsg, 0, strlen($errorMsg)-2).'.');
				layout_abono();
				exit;
			}
		// }

		$_POST['busca_motorista'] = $_POST['motorista'];

		
		$aData = explode(" - ", $_POST['daterange']);
		$aData[0] = explode('/', $aData[0]);
		$aData[0] = $aData[0][2].'-'.$aData[0][1].'-'.$aData[0][0];
		$aData[1] = explode('/', $aData[1]);
		$aData[1] = $aData[1][2].'-'.$aData[1][1].'-'.$aData[1][0];
		//Conferir se há um período entrelaçado com essa data{
			$endosso = mysqli_fetch_all(
				query(
					"SELECT * FROM endosso
						WHERE endo_tx_status = 'ativo'
							AND endo_nb_entidade = ".$_POST['motorista']."
							AND (
								'".$aData[0]."' BETWEEN endo_tx_de AND endo_tx_ate
								OR '".$aData[1]."' BETWEEN endo_tx_de AND endo_tx_ate
							)
						LIMIT 1"
				),
				MYSQLI_ASSOC
			);

			if(!empty($endosso)){
				$endosso = $endosso[0];
				$endosso['endo_tx_de'] = explode('-', $endosso['endo_tx_de']);
				$endosso['endo_tx_de'] = $endosso['endo_tx_de'][2].'/'.$endosso['endo_tx_de'][1].'/'.$endosso['endo_tx_de'][0];

				$endosso['endo_tx_ate'] = explode('-', $endosso['endo_tx_ate']);
				$endosso['endo_tx_ate'] = $endosso['endo_tx_ate'][2].'/'.$endosso['endo_tx_ate'][1].'/'.$endosso['endo_tx_ate'][0];

				set_status('ERRO: Possui um endosso de '.$endosso['endo_tx_de'].' até '.$endosso['endo_tx_ate'].'.');
				layout_abono();
				exit;
			}
		//}

		$begin = new DateTime($aData[0]);
		$end = new DateTime($aData[1]);

		$a=carregar('entidade',$_POST['motorista']);
		
		for ($i = $begin; $i <= $end; $i->modify('+1 day')) {

			$sqlRemover = query("SELECT * FROM abono WHERE abon_tx_data = '".$i->format("Y-m-d")."' AND abon_tx_matricula = '".$a["enti_tx_matricula"]."' AND abon_tx_status = 'ativo'");
			while ($aRemover = carrega_array($sqlRemover)) {
				remover('abono', $aRemover['abon_nb_id']);
			}

			$aDetalhado = diaDetalhePonto($a['enti_tx_matricula'], $i->format("Y-m-d"));

			$abono = calcularAbono($aDetalhado['diffSaldo'], $_POST['abono']);


			$campos = ['abon_tx_data', 'abon_tx_matricula', 'abon_tx_abono', 'abon_nb_motivo', 'abon_tx_descricao', 'abon_nb_userCadastro', 'abon_tx_dataCadastro', 'abon_tx_status'];
			$valores = [$i->format("Y-m-d"), $a['enti_tx_matricula'], $abono, $_POST['motivo'], $_POST['descricao'], $_SESSION['user_nb_id'], date("Y-m-d H:i:s"), 'ativo'];

			inserir('abono', $campos, $valores);
		}

		$_POST['acao'] = "index";
		$_POST['busca_empresa'] = $_POST['busca_empresa']??$_POST['empresa'];
		$_POST['busca_motorista'] = $_POST['motorista'];
		$_POST['busca_dataInicio'] = $_POST['dataInicio'];
		$_POST['busca_dataFim'] = $_POST['dataFim'];

		echo "<form name='form_voltar' action='".$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/espelho_ponto.php' method='post'>";
		foreach($_POST as $key => $value){
			echo "<input type='hidden' name='".$key."' value='".$value."'/>";
		}
		echo "</form>";
		echo "<script>document.form_voltar.submit()</script>";
		exit;
	}

	function layout_abono(){
		echo "<form action='".$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/cadastro_abono.php' name='form_cadastro_abono' method='post'>";

    	unset($_POST['acao']);
		
		foreach($_POST as $key => $value){
			echo "<input type='hidden' name='".$key."' value='".$value."'>";
		}
		echo "</form>";
		echo "<script>document.form_cadastro_abono.submit();</script>";
		exit;
	}
	
	function index(){
		cabecalho("Cadastro Abono");

		$c[] = combo_net(
			"Motorista/Ajudante*",
			"motorista",
			(!empty($_POST["motorista"])? $_POST["motorista"]: $_POST["busca_motorista"]?? ""),
			4,
			"entidade",
			"",
			" AND enti_tx_ocupacao IN ('Motorista', 'Ajudante')",
			"enti_tx_matricula"
		);
		$c[] = campo("Data(s)*","daterange", ($_POST["daterange"]?? ""),3);
		$c[] = campo("Abono*", "abono", ($_POST["abono"]?? ""), 3, "MASCARA_HORAS");
		$c2[] = combo_bd("Motivo*","motivo", ($_POST["motivo"]?? ""),4,"motivo",""," AND moti_tx_tipo = 'Abono'");
		$c2[] = textarea("Justificativa","descricao", ($_POST["descricao"]?? ""),12);
		
		//BOTOES
		$b[] = botao("Gravar","cadastra_abono","","","","","btn btn-success");
		$b[] = botao(
			"Voltar",
			"voltar",
			implode(",",array_keys($_POST)),
			implode(",",array_values($_POST))
		);
		abre_form("Filtro de Busca");

		if(empty($_POST["HTTP_REFERER"])){
			$_POST["HTTP_REFERER"] = $_SERVER["HTTP_REFERER"];
			if(is_int(strpos($_SERVER["HTTP_REFERER"], "cadastro_abono.php"))){
				$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/espelho_ponto.php";
			}
		}else {
			$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/nao_conformidade.php";
		}

		campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		campo_hidden("busca_empresa", $_POST["busca_empresa"]);
		campo_hidden("busca_dataInicio", $_POST["busca_dataInicio"]);
		campo_hidden("busca_dataFim", $_POST["busca_dataFim"]);
		
		linha_form($c);
		linha_form($c2);
		fecha_form($b);

		rodape();


		echo 
			"<script type='text/javascript' src='js/moment.min.js'></script>
			<script type='text/javascript' src='js/daterangepicker.min.js'></script>
			<link rel='stylesheet' type='text/css' href='js/daterangepicker.css' />

			<script>
				$(function() {
					$('input[name=\"daterange\"]').daterangepicker({
						opens: 'left',
						'locale': {
							'format': 'DD/MM/YYYY',
							'separator': ' - ',
							'applyLabel': 'Aplicar',
							'cancelLabel': 'Cancelar',
							'fromLabel': 'From',
							'toLabel': 'To',
							'customRangeLabel': 'Custom',
							'weekLabel': 'W',
							'daysOfWeek': ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'],
							'monthNames': ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'],
							'firstDay': 1
						},
					}, function(start, end, label) {
						// console.log('A new date selection was made: '+start.format('YYYY-MM-DD')+' to '+end.format('YYYY-MM-DD'));
					});
				});
			</script>"
		;
	}