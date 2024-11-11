<?php
	/* Modo debug{
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
		
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
        header("Pragma: no-cache"); // HTTP 1.0.
        header("Expires: 0");
	//}*/

	function updateFTP($path){
		// connect and login to FTP server
		$ftpInfos = mysqli_fetch_assoc(query(
			"SELECT empr_tx_ftpServer, empr_tx_ftpUsername, empr_tx_ftpUserpass FROM empresa"
			." JOIN user ON empresa.empr_nb_id = user.user_nb_empresa"
			." WHERE user_nb_id = ".$_SESSION["user_nb_id"]
		));
		
		if(empty($ftpInfos["empr_tx_ftpServer"]) || empty($ftpInfos["empr_tx_ftpUserpass"]) || empty($ftpInfos["empr_tx_ftpUserpass"])){
			set_status("ERRO: Não foi possível encontrar o servidor FTP.");
			exit;
		}

		
		try{
			$ftp_conn = ftp_connect($ftpInfos["empr_tx_ftpServer"]);
			if($ftp_conn === false){
				throw new Exception("Conexção FTP retornou falso.");
			}

			$loggedIn = ftp_login($ftp_conn, $ftpInfos["empr_tx_ftpUsername"], $ftpInfos["empr_tx_ftpUserpass"]);
			if(!$loggedIn){
				throw new Exception("Login retornou falso.");
			}
		}catch(Exception $e){
			echo "ERRO: Não foi possível conectar à ".$ftpInfos["empr_tx_ftpServer"];
			echo "<script>console.log('".$e->getMessage()."')</script>";
			exit;
		}
		ftp_pasv($ftp_conn, true);

		$lastFile = mysqli_fetch_assoc(query(
			"SELECT arqu_tx_nome, arqu_tx_data FROM arquivoponto"
			." WHERE arqu_tx_status = 'ativo'"
				." AND (arqu_tx_nome REGEXP '[apontamento][[:digit:]]{14}.txt') = 1"
			." ORDER BY arqu_tx_data DESC"
			." LIMIT 1;"
		));
		$lastFile["nameDate"] = str_replace(["apontamento", ".txt"], ["", ""], $lastFile["arqu_tx_nome"]);
		$lastFile["nameDate"] = substr($lastFile["nameDate"], 4, 4)."-".substr($lastFile["nameDate"], 2, 2)."-".substr($lastFile["nameDate"], 0, 2);

		$fileList = ftp_nlist($ftp_conn, ".");
		$fileList = implode(", ", $fileList);
		for($data = (new DateTime($lastFile["nameDate"]))->modify("+1 day"); $data->format("Y-m-d") <= date("Y-m-d"); $data->modify("+1 day")){
			if(is_int(strpos($fileList, "apontamento".$data->format("dmY")))){

				$ext = ".txt";
				$nomeArqRemoto = substr($fileList, strpos($fileList, "apontamento".$data->format("dmY")), 25).$ext;
				$nomeArquivo = substr($fileList, strpos($fileList, "apontamento".$data->format("dmY")), 25);

				// $fileExists = (
				// 	mysqli_num_rows(query(
				// 		"SELECT * FROM arquivoponto"
				// 		." WHERE arqu_tx_status = 'ativo'"
				// 		." AND arqu_tx_nome = '".$nomeArquivo.$ext."'"
				// 		." LIMIT 1"
				// 	)) > 0
				// );
				// if($fileExists){
					// $f = 2;
					// $nomeArquivo  .= "_".$f;
					// for(; file_exists($path.$nomeArquivo.$ext); $f++){
					// 	$nomeArquivo = substr($nomeArquivo , 0, strlen($nomeArquivo)-2)."_".$f;
					// }
				// }
				$caminhoCompleto = $path."/".$nomeArquivo.$ext;
				
				if(!ftp_get($ftp_conn, $caminhoCompleto, $nomeArqRemoto)){
					set_status("ERRO: Houve um problema ao salvar o arquivo.");
					exit;
				}

				saveRegisterFile(["tmp_name" => $caminhoCompleto, "name" => $nomeArquivo.$ext], $caminhoCompleto);
			}
		}
		ftp_close($ftp_conn);
		
		criar_relatorio(date("Y-m"));
		if(is_int(strpos($_SERVER["REQUEST_URI"], "carregar_ponto"))){
			index();
		}else{
			echo "Pontos registrados com sucesso.";
		}
		exit;
	}

	function carregar_ftp(string $path){
		// Aplicar após criar o usuário REP-P
		$user = mysqli_fetch_assoc(query(
			"SELECT * FROM user"
			." WHERE user_tx_status = 'ativo'"
				." AND user_tx_nivel = 'Super Administrador'"
				." AND user_tx_login = '".$_GET["login"]."'"
				." AND user_tx_senha = '".$_GET["senha"]."'"
			." LIMIT 1;"
		));

		if(empty($user) || count($user) == 0){
			echo "ERRO: usuário não encontrado.";
			exit;
		}

		foreach($user as $key => $value){
			$_SESSION[$key] = $value;
		}
		updateFTP($path);
		exit;
	}

	$interno = true;
	include_once("carregar_ponto.php");
