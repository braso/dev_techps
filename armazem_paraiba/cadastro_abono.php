<?php
	//* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/

	include "funcoes_ponto.php";

	function cadastra_abono(){
		// Conferir se os campos obrigatórios estão preenchidos{
			
			$camposObrig = [
				"motorista" => "Funcionário",
				"busca_periodo" => "Data",
				"abono" => "Abono",
				"motivo" => "Motivo"
			];

			$errorMsg = conferirCamposObrig($camposObrig, $_POST);

			if(!empty($errorMsg)){
				set_status("ERRO: ".$errorMsg);
				layout_abono();
				exit;
			}
			dd($_POST);
		// }

		$_POST["busca_motorista"] = $_POST["motorista"];

		
		$aData = explode(" - ", $_POST["busca_periodo"]);
		$aData[0] = explode("/", $aData[0]);
		$aData[0] = $aData[0][2]."-".$aData[0][1]."-".$aData[0][0];
		$aData[1] = explode("/", $aData[1]);
		$aData[1] = $aData[1][2]."-".$aData[1][1]."-".$aData[1][0];
		//Conferir se há um período entrelaçado com essa data{
			$endosso = mysqli_fetch_assoc(
				query(
					"SELECT endo_tx_de, endo_tx_ate FROM endosso
						WHERE endo_tx_status = 'ativo'
							AND endo_nb_entidade = ".$_POST["motorista"]."
							AND (
								'".$aData[0]."' BETWEEN endo_tx_de AND endo_tx_ate
								OR '".$aData[1]."' BETWEEN endo_tx_de AND endo_tx_ate
							)
						LIMIT 1;"
				)
			);

			if(!empty($endosso)){
				$endosso["endo_tx_de"] = explode("-", $endosso["endo_tx_de"]);
				$endosso["endo_tx_de"] = $endosso["endo_tx_de"][2]."/".$endosso["endo_tx_de"][1]."/".$endosso["endo_tx_de"][0];

				$endosso["endo_tx_ate"] = explode("-", $endosso["endo_tx_ate"]);
				$endosso["endo_tx_ate"] = $endosso["endo_tx_ate"][2]."/".$endosso["endo_tx_ate"][1]."/".$endosso["endo_tx_ate"][0];

				$_POST["errorFields"][] = "busca_periodo";
				set_status("ERRO: Possui um endosso de ".$endosso["endo_tx_de"]." até ".$endosso["endo_tx_ate"].".");
				layout_abono();
				exit;
			}
		//}

		$begin = new DateTime($aData[0]);
		$end = new DateTime($aData[1]);

		$a=carregar("entidade",$_POST["motorista"]);
		
		for ($i = $begin; $i <= $end; $i->modify("+1 day")){
			$sqlRemover = query("SELECT * FROM abono WHERE abon_tx_data = '".$i->format("Y-m-d")."' AND abon_tx_matricula = '".$a["enti_tx_matricula"]."' AND abon_tx_status = 'ativo'");
			while ($aRemover = carrega_array($sqlRemover)) {
				remover("abono", $aRemover["abon_nb_id"]);
			}
			$aDetalhado = diaDetalhePonto($a["enti_tx_matricula"], $i->format("Y-m-d"));
			$aDetalhado["diffSaldo"] = str_replace(["<b>", "</b>"], ["", ""], $aDetalhado["diffSaldo"]);
			$abono = calcularAbono($aDetalhado["diffSaldo"], $_POST["abono"]);

			$novoAbono = [
				"abon_tx_data" 			=> $i->format("Y-m-d"),
				"abon_tx_matricula" 	=> $a["enti_tx_matricula"],
				"abon_tx_abono" 		=> $abono,
				"abon_nb_motivo" 		=> $_POST["motivo"],
				"abon_tx_descricao" 	=> $_POST["descricao"],
				"abon_nb_userCadastro" 	=> $_SESSION["user_nb_id"],
				"abon_tx_dataCadastro" 	=> date("Y-m-d H:i:s"),
				"abon_tx_status" 		=> "ativo"
			];
			inserir("abono", array_keys($novoAbono), array_values($novoAbono));
		}

		$_POST["acao"] = "index";

		voltar();
		exit;
	}

	function layout_abono(){
    	unset($_POST["acao"]);
		
		index();
		exit;
	}
	
	function index(){
		cabecalho("Cadastro Abono");

		$campos[0][] = combo_net(
			"Funcionário*",
			"motorista",
			(!empty($_POST["motorista"])? $_POST["motorista"]: $_POST["busca_motorista"]?? ""),
			4,
			"entidade",
			"",
			" AND enti_tx_ocupacao IN ('Motorista', 'Ajudante','Funcionário')",
			"enti_tx_matricula"
		);
		$campos[0][] = campo("Data(s)*", "busca_periodo", ($_POST["busca_periodo"]?? ""),3, "MASCARA_PERIODO");
		$campos[0][] = campo("Abono*", "abono", ($_POST["abono"]?? ""), 3, "MASCARA_HORAS");
		$campos[1][] = combo_bd("Motivo*","motivo", ($_POST["motivo"]?? ""),4,"motivo",""," AND moti_tx_tipo = 'Abono'");
		$campos[1][] = textarea("Justificativa","descricao", ($_POST["descricao"]?? ""),12);
		
		//BOTOES
    	$b[] = botao("Gravar","cadastra_abono", "","","","","btn btn-success");
		
		$voltarInfo = $_POST;
		unset($voltarInfo["errorFields"]);
		$voltarInfo["busca_periodo"] = json_encode($voltarInfo["busca_periodo"]);

		$b[] = botao("Voltar", "voltar", implode(",",array_keys($voltarInfo)), implode(",",array_values($voltarInfo))); 
		abre_form();

		if(empty($_POST["HTTP_REFERER"])){
			$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/espelho_ponto.php";
		}

		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		echo campo_hidden("busca_empresa", ($_POST["busca_empresa"]?? ""));
		echo campo_hidden("busca_dataInicio", ($_POST["busca_dataInicio"]?? ""));
		echo campo_hidden("busca_dataFim", ($_POST["busca_dataFim"]?? ""));
		
		linha_form($campos[0]);
		linha_form($campos[1]);
		fecha_form($b);

		rodape();
	}