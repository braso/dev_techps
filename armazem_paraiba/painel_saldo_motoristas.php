<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/

	function empresa($aEmpresa, $idEmpresa){
		$MotoristasTotais = [];
		$MotoristaTotais = [];
		$endPastaPaineis = "./arquivos/paineis";
        var_dump($aEmpresa); echo "<br><br>";
        var_dump($idEmpresa); echo "<br><br>";
        var_dump($endPastaPaineis."/saldos/empresas/".$_POST["busca_data"]); echo "<br><br>";
        die();

		global $CONTEX;
		
		if (is_dir($endPastaPaineis."/saldos/empresas/".$_POST["busca_data"])){
			// Obtém O total dos saldos das empresa
			$file = $endPastaPaineis."/saldos/".$idEmpresa."/".$_POST["busca_data"]."/totalMotoristas.json";

			if (file_exists($endPastaPaineis."/saldos/".$idEmpresa."/".$_POST["busca_data"])) {
				$conteudo_json = file_get_contents($file);
				$MotoristasTotais = json_decode($conteudo_json,true);
			}

			foreach(["jornadaPrevista", "JornadaEfetiva", "he50", "he100", "adicionalNoturno", "esperaIndenizada", "saldoAnterior", "saldoPeriodo", "saldoFinal"] as $campo){
				if($MotoristasTotais[$campo] == "00:00"){
					$MotoristasTotais[$campo] = "";
				}
			}

			// Obtém o total dos saldos de cada Motorista
			$fileEmpresa = $endPastaPaineis."/".$idEmpresa."/".$_POST["busca_data"]."/motoristas.json";
			if (file_exists($endPastaPaineis."/".$idEmpresa."/".$_POST["busca_data"])) {
				$conteudo_json = file_get_contents($fileEmpresa);
				$MotoristaTotais = json_decode($conteudo_json, true);
			}
		}else{
			echo "<script>alert('Não Possui dados desse mês')</script>";
		}


		// Obtém o tempo da última modificação do arquivo
		$timestamp = filemtime($file);
		$emissão = date("d/m/Y H:i:s", $timestamp);


		// Calcula a porcentagem
		$porcentagenNaEndo 	= ($MotoristasTotais["naoEndossados"] != 0)? number_format(($MotoristasTotais["naoEndossados"]/$MotoristasTotais["totalMotorista"])*100, 2): number_format(0,2);
		$porcentagenEndoPc 	= ($MotoristasTotais["endossoPacial"] != 0)? number_format(($MotoristasTotais["endossoPacial"]/$MotoristasTotais["totalMotorista"])*100, 2): number_format(0,2);
		$porcentagenEndo 	= ($MotoristasTotais["endossados"] != 0)?    number_format(($MotoristasTotais["endossados"]/$MotoristasTotais["totalMotorista"])*100, 2):    number_format(0,2);


		$quantMeta = 0;
		$quantPosi = 0;
		$quantNega = 0;

		foreach ($MotoristaTotais as $MotoristaTotal) {
			if($MotoristaTotal["statusEndosso"] == "E" && $MotoristaTotal["saldoFinal"] == "00:00"){
				$quantMeta++;
			}elseif($MotoristaTotal["saldoFinal"] > "00:00"){
				$quantPosi++;
			}elseif($MotoristaTotal["saldoFinal"] < "00:00"){
				$quantNega++;
			}
		}

		$porcentagenMeta = ($quantMeta != 0)? number_format(($quantMeta/count($MotoristaTotais))*100, 2): number_format(0,2);
		$porcentagenNega = ($quantNega != 0)? number_format(($quantNega/count($MotoristaTotais))*100, 2): number_format(0,2);
		$porcentagenPosi = ($quantPosi != 0)? number_format(($quantPosi/count($MotoristaTotais))*100, 2): number_format(0,2);

		include "painel_saldo_html.php";
	}
?>