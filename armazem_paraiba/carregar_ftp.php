<?php
	/* Modo debug{
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
		
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
        header("Pragma: no-cache"); // HTTP 1.0.
        header("Expires: 0");
	//}*/

	function updateFTP($path){

        // 🔥 garante o usuário logado correto (ou o técnico via cron)
        $usuarioLogadoId = $_SESSION["user_nb_id_temp"] ?? $_SESSION["user_nb_id"] ?? 0;

        // 🔥 usa FTP temporário se veio do carregar_ftp
        if(isset($_SESSION["ftpInfos_temp"])){
            $ftpInfos = $_SESSION["ftpInfos_temp"];
        } else {
            $ftpInfos = mysqli_fetch_assoc(query(
                "SELECT empr_tx_ftpServer, empr_tx_ftpUsername, empr_tx_ftpUserpass FROM empresa
                JOIN user ON empresa.empr_nb_id = user.user_nb_empresa
                WHERE user_nb_id = " . ($usuarioLogadoId ?? 0)
            ));
            
            // 🔥 Fallback para Super Administradores que não têm empresa vinculada:
            // Pegamos as configurações globais de FTP cadastradas
            if(empty($ftpInfos["empr_tx_ftpServer"]) || empty($ftpInfos["empr_tx_ftpUsername"]) || empty($ftpInfos["empr_tx_ftpUserpass"])){
                $ftpInfos = mysqli_fetch_assoc(query(
                    "SELECT empr_tx_ftpServer, empr_tx_ftpUsername, empr_tx_ftpUserpass FROM empresa
                    WHERE empr_tx_ftpServer IS NOT NULL AND empr_tx_ftpServer != ''
                    LIMIT 1"
                ));
            }
        }

        if(empty($ftpInfos["empr_tx_ftpServer"]) || empty($ftpInfos["empr_tx_ftpUsername"]) || empty($ftpInfos["empr_tx_ftpUserpass"])){
            set_status("ERRO: Não foi possível encontrar o servidor FTP.");
            index();
            exit;
        }

        // 🔍 último arquivo salvo
        $lastFile = mysqli_fetch_assoc(query(
            "SELECT arqu_tx_nome, arqu_tx_data FROM arquivoponto
            WHERE arqu_tx_status = 'ativo'
            AND arqu_tx_nome LIKE 'apontamento%.txt'
            ORDER BY arqu_tx_data DESC
            LIMIT 1;"
        ));

        if(!empty($lastFile)){
            $nome = str_replace(["apontamento", ".txt"], "", $lastFile["arqu_tx_nome"]);
            $dataFormatada = substr($nome, 4, 4)."-".substr($nome, 2, 2)."-".substr($nome, 0, 2);
            $dataInicial = (new DateTime($dataFormatada))->modify("+1 day");
        }else{
            $dataInicial = new DateTime();
        }

        $fileList = [];

        // 🔹 LISTAR ARQUIVOS (FTPS → FTP fallback)
        $curlListFiles = function($server, $user, $pass, $sslMode) use (&$fileList){
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "ftp://$server/");
            curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FTP_USE_EPSV, true);
            curl_setopt($ch, CURLOPT_FTP_SSL, $sslMode);
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

                // 🔥 pega só o nome do arquivo (compatível com LIST)
                $parts = preg_split('/\s+/', $line);
                $fileName = end($parts);
                $fileList[] = $fileName;
            }

            return true;
        };

        // tenta FTPS
        $conectou = $curlListFiles(
            $ftpInfos["empr_tx_ftpServer"],
            $ftpInfos["empr_tx_ftpUsername"],
            $ftpInfos["empr_tx_ftpUserpass"],
            CURLFTPSSL_ALL
        );

        // fallback FTP
        if(!$conectou){
            $conectou = $curlListFiles(
                $ftpInfos["empr_tx_ftpServer"],
                $ftpInfos["empr_tx_ftpUsername"],
                $ftpInfos["empr_tx_ftpUserpass"],
                0
            );

            if(!$conectou){
                set_status("ERRO: Não foi possível listar arquivos do FTP/FTPS.");
                index();
                exit;
            }
        }

        // 🔄 percorre datas
        for($data = $dataInicial; $data->format("Y-m-d") <= date("Y-m-d"); $data->modify("+1 day")){

            $nomeBusca = "apontamento".$data->format("dmY");

            foreach($fileList as $file){

                if(strpos($file, $nomeBusca) === false) continue;

                // 🔥 evita .txt duplicado
                $nomeArquivo = (strtolower(substr($file, -4)) === ".txt") ? $file : $file.".txt";
                $caminhoCompleto = $path."/".$nomeArquivo;

                if(file_exists($caminhoCompleto)) continue;

                // 🔽 DOWNLOAD (FTPS → FTP fallback)
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

                // 🔥 FORÇA usuário correto no salvamento
                $arquivo = [
                    "tmp_name" => $nomeArquivo,
                    "name" => $nomeArquivo,
                    "user_id" => $usuarioLogadoId
                ];

                salvarArquivoPonto($arquivo, $caminhoCompleto);
            }
        }

        // limpa ftp temporário
        unset($_SESSION["ftpInfos_temp"]);
        unset($_SESSION["user_nb_id_temp"]);

        if(is_int(strpos($_SERVER["REQUEST_URI"], "carregar_ponto"))){
            index();
        } else {
            echo "Pontos registrados com sucesso.";
        }

        exit;
    }

	function carregar_ftp(string $path){

        // Buscar usuário técnico (REP-P)
        $userTecnico = mysqli_fetch_assoc(query(
            "SELECT * FROM user
            WHERE user_tx_status = 'ativo'
            AND user_tx_nivel = 'Super Administrador'
            AND user_tx_login = '".$_GET["login"]."'
            AND user_tx_senha = '".$_GET["senha"]."'
            LIMIT 1;"
        ));

        if(empty($userTecnico)){
            echo "ERRO: usuário técnico não encontrado.";
            exit;
        }

        // Buscar dados do FTP da empresa do usuário técnico
        $ftpInfos = mysqli_fetch_assoc(query(
            "SELECT empr_tx_ftpServer, empr_tx_ftpUsername, empr_tx_ftpUserpass
            FROM empresa
            WHERE empr_nb_id = ".$userTecnico["user_nb_empresa"]."
            LIMIT 1;"
        ));

        if(empty($ftpInfos) || empty($ftpInfos["empr_tx_ftpServer"])){
            echo "ERRO: empresa sem dados de FTP.";
            exit;
        }

        // 🔥 NÃO sobrescreve sessão do usuário logado permanentemente
        // apenas injeta o FTP e o usuário técnico temporariamente
        $_SESSION["ftpInfos_temp"] = $ftpInfos;
        $_SESSION["user_nb_id_temp"] = $userTecnico["user_nb_id"];

        updateFTP($path);
        exit;
    }

	$interno = true;
	include_once("carregar_ponto.php");