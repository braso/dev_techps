<?php
	/* Modo debug{
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
		
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
        header("Pragma: no-cache"); // HTTP 1.0.
        header("Expires: 0");
	//}*/

	function updateFTP($path){

    // buscar dados do FTP
    $ftpInfos = mysqli_fetch_assoc(query(
        "SELECT empr_tx_ftpServer, empr_tx_ftpUsername, empr_tx_ftpUserpass FROM empresa"
        ." JOIN user ON empresa.empr_nb_id = user.user_nb_empresa"
        ." WHERE user_nb_id = ".$_SESSION["user_nb_id"]
    ));

    if(empty($ftpInfos["empr_tx_ftpServer"]) || empty($ftpInfos["empr_tx_ftpUsername"]) || empty($ftpInfos["empr_tx_ftpUserpass"])){
        set_status("ERRO: Não foi possível encontrar o servidor FTP.");
        index();
        exit;
    }

    // buscar último arquivo salvo
    $lastFile = mysqli_fetch_assoc(query(
        "SELECT arqu_tx_nome, arqu_tx_data FROM arquivoponto"
        ." WHERE arqu_tx_status = 'ativo'"
        ." AND (arqu_tx_nome REGEXP '[apontamento][[:digit:]]{14}.txt') = 1"
        ." ORDER BY arqu_tx_data DESC"
        ." LIMIT 1;"
    ));

    if(!empty($lastFile)){
        $lastFile["nameDate"] = str_replace(["apontamento", ".txt"], ["", ""], $lastFile["arqu_tx_nome"]);
        $lastFile["nameDate"] = substr($lastFile["nameDate"], 4, 4)."-".substr($lastFile["nameDate"], 2, 2)."-".substr($lastFile["nameDate"], 0, 2);
        $dataInicial = (new DateTime($lastFile["nameDate"]))->modify("+1 day");
    }else{
        $dataInicial = new DateTime();
    }

    $fileList = [];

    // Função para listar arquivos via cURL com tentativa de FTPS ou FTP simples
    $curlListFiles = function($server, $user, $pass, $sslMode) use (&$fileList){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "ftp://$server/");
        curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FTP_USE_EPSV, true);
        curl_setopt($ch, CURLOPT_FTP_SSL, $sslMode); // CURLFTPSSL_ALL, CURLFTPSSL_TRY ou 0
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $data = curl_exec($ch);
        if(curl_errno($ch) || $data === false){
            curl_close($ch);
            return false;
        }
        curl_close($ch);

        $lines = explode("\n", $data);
			foreach($lines as $line){
				$line = trim($line);
				if(empty($line)) continue;

				// extrair só o nome do arquivo
				$parts = preg_split('/\s+/', $line);
				$fileName = end($parts); // pega a última parte da linha
				$fileList[] = $fileName;
			}
        return true;
    };

    // Tenta FTPS primeiro
    $conectou = $curlListFiles($ftpInfos["empr_tx_ftpServer"], $ftpInfos["empr_tx_ftpUsername"], $ftpInfos["empr_tx_ftpUserpass"], CURLFTPSSL_ALL);

    // Se falhar, tenta FTP simples automaticamente
    if(!$conectou){
        $conectou = $curlListFiles($ftpInfos["empr_tx_ftpServer"], $ftpInfos["empr_tx_ftpUsername"], $ftpInfos["empr_tx_ftpUserpass"], 0);
        if(!$conectou){
            set_status("ERRO: Não foi possível listar arquivos do FTP/FTPS.");
            index();
            exit;
        }
    }

    // transformar lista em string para busca
    $fileListStr = implode(", ", $fileList);

    // percorrer dias para baixar arquivos
    for($data = $dataInicial; $data->format("Y-m-d") <= date("Y-m-d"); $data->modify("+1 day")){
        $nomeBusca = "apontamento".$data->format("dmY");
        foreach($fileList as $file){
            if(strpos($file, $nomeBusca) !== false){
                $ext = ".txt";
                $nomeArquivo = (strtolower(substr($file, -4)) === ".txt") ? $file : $file.$ext;
                $caminhoCompleto = $path."/".$nomeArquivo;

                if(file_exists($caminhoCompleto)){
                    continue;
                }

                // Baixar arquivo com FTPS e fallback automático
                $baixou = false;
                foreach([CURLFTPSSL_ALL, 0] as $sslMode){
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, "ftp://".$ftpInfos["empr_tx_ftpServer"]."/".$file);
                    curl_setopt($ch, CURLOPT_USERPWD, $ftpInfos["empr_tx_ftpUsername"].":".$ftpInfos["empr_tx_ftpUserpass"]);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FTP_USE_EPSV, true);
                    curl_setopt($ch, CURLOPT_FTP_SSL, $sslMode);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

                    $dataArquivo = curl_exec($ch);
                    if(!curl_errno($ch) && $dataArquivo !== false){
                        file_put_contents($caminhoCompleto, $dataArquivo);
                        $baixou = true;
                        curl_close($ch);
                        break;
                    }
                    curl_close($ch);
                }

                if(!$baixou){
                    set_status("ERRO: Não foi possível baixar o arquivo $file.");
                    index();
                    exit;
                }

                $arquivo = [
                    "tmp_name" => $nomeArquivo,
                    "name" => $nomeArquivo
                ];
                salvarArquivoPonto($arquivo, $caminhoCompleto);
            }
        }
    }

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
