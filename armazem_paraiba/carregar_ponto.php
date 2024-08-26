<?php
	//* Modo debug{
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
		
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
        header("Pragma: no-cache"); // HTTP 1.0.
        header("Expires: 0");
	//}*/

	include_once "funcoes_ponto.php";

	global $path;
	$path = "arquivos/pontos";

	function getLastFileDate($dataAtual): string{

        $dataUltimoArquivo = mysqli_fetch_assoc(query(
            "SELECT arqu_tx_data FROM arquivoponto"
            ." WHERE arqu_tx_status = 'ativo'"
            ." AND arqu_tx_nome LIKE '%apontamento%'"
            ." ORDER BY arqu_tx_data DESC"
            ." LIMIT 1;"
        ));
		if(empty($dataUltimoArquivo)){
			return "A data do último arquivo não foi encontrada.";
		}
        // Converter a string 'Y-m-d H:i:s' em um objeto DateTime
        $dataUltimoArquivo = DateTime::createFromFormat('Y-m-d H:i:s', $dataUltimoArquivo["arqu_tx_data"]);
		
		// Calcular a diferença
		$dataAtual = DateTime::createFromFormat('dmY', $dataAtual);
		$diferenca = $dataAtual->diff($dataUltimoArquivo)->format('%a');
		$dominio = substr($_ENV["CONTEX_PATH"],1);

		$msg = "Faz ".$diferenca." dias que o arquivo de ponto no domínio ".$dominio." não é atualizado.";

		/*Enviar emails{
			$emails = mysqli_fetch_assoc(query(
			    "SELECT * FROM configuracao_alerta"
			    ." LIMIT 1"
			));
			if ((int)$diferenca <= 6){
				sendEmailAlerta($emails['conf_tx_emailFun'], $emails['conf_tx_emailFun'], $msg);
			}else{
				sendEmailAlerta($emails['conf_tx_emailAdm'], $emails['conf_tx_emailAdm'], $msg);
			}
		//}*/

		return $msg;
    }

    function insertFile(){
		global $path;

		$arquivo = $_FILES["arquivo"];

		if(isset($arquivo["error"]) && $arquivo["error"] === 0){
			$local_file = $path.$arquivo["name"];
			$ext = substr($arquivo["name"], strrpos($arquivo["name"], "."));
			$nomeArquivo = str_replace($ext, "", $arquivo["name"]);

			if(file_exists($path.$arquivo["name"])){
				$f = 2;
				$nomeArquivo  .= "_".$f;
				$arquivo["name"] = $nomeArquivo.$ext;
				$local_file = $path.$arquivo["name"];
				for(; file_exists($path.$nomeArquivo); $f++){
					$arquivo["name"] = substr($nomeArquivo , 0, strlen($nomeArquivo )-2)."_".$f;
					$local_file = $path.$arquivo["name"];
				}
			}
     		saveRegisterFile($arquivo, $local_file);

		}else{
			set_status("ERRO: Ocorreu um problema ao gravar o arquivo.");
		}
		index();
		exit;
	}

	function viewManualInsert(){
		cabecalho("Carregar Ponto Manualmente");

		$fields = [
			// campo("Data do Arquivo","data",date("d/m/Y"),2,MASCARA_DATA),
			arquivo("Arquivo Ponto (.txt)", "arquivo", "", 5)
		];

		$buttons[] = botao("Enviar", "insertFile","","","","","btn btn-success");
		$buttons[] = botao("Voltar", "voltar");

		if(empty($_POST["HTTP_REFERER"])){
			$_POST["HTTP_REFERER"] = $_SERVER["HTTP_REFERER"];
			if(is_int(strpos($_SERVER["HTTP_REFERER"], "carregar_ponto.php"))){
				$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/carregar_ponto.php";
			}
		}

		abre_form("Arquivo de Ponto");
		linha_form($fields);
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		fecha_form($buttons);

		rodape();
	}

	// function cadastra_notificacao(){
		// 	die("cadastra_notificacao");
			
		// 	$campos = ["conf_tx_emailFun","conf_tx_emailAdm"];
		// 	$valores = [$_POST["emailFuncionario"],$_POST["emailFuncionario"]];

		// 	if(!empty($_POST["id"])) {
		// 		$campos = array_merge($campos,["conf_tx_dataAtualiza"]);
		// 		$valores = array_merge($valores,[date("Y-m-d H:i:s")]);
		// 		atualizar("configuracao_alerta",$campos,$valores,$_POST["id"]);
		// 	}else {
		// 		$campos = array_merge($campos,["conf_tx_dataCadastro"]);
		// 		$valores = array_merge($valores,[date("Y-m-d H:i:s")]);
		// 		inserir("configuracao_alerta",$campos,$valores);
		// 	}

		// 	index();
		// 	exit;
	// }

	// function layout_notificacao(){
		// Separar para um arquivo específico de cadastro de notificação
		// die("layout_notificacao");
		// $sqlCheck = query("SELECT * FROM configuracao_alerta LIMIT 1");
		// $emails = mysqli_fetch_assoc($sqlCheck);

		// if (!empty($emails)) {
		// 	$emailFun = $emails["conf_tx_emailFun"];
		// 	$emailAdm = $emails["conf_tx_emailAdm"];
		// 	$atualizacao = $emails["conf_tx_dataAtualiza"];
		// 	$cadastro = $emails["conf_tx_dataCadastro"];
		// }
		

		// $camposData = [];
		// if (!empty($cadastro)) {
		// 	$txtCadastro = "Registro inserido às ".data($cadastro, 1).".";
		// 	$camposData[] = texto("Data de Cadastro", $txtCadastro, 5);
		// }

		// if (!empty($atualizacao)) {
		// 	$txtAtualiza = "Registro atualizado às ".data($atualizacao, 1).".";
		// 	$camposData[] = texto("Última Atualização", $txtAtualiza, 5);
		// }

		// cabecalho("Configura Notificação");

		// //$fields[] = campo("Data do Arquivo:","data",date("d/m/Y"),2,MASCARA_DATA);
		// $fields = [
		// 	campo("E-mail do Funcionario", "emailFuncionario", $emailFun, 2),
		// 	campo("E-mail do Administrado", "emailAdministrado", $emailAdm, 2)
		// ];

		// $buttons = [ 
		// 	botao("Gravar", "cadastra_notificacao", "id", $_POST["id"]),
		// 	botao("Voltar", "voltar")
		// ];

		// if(empty($_POST["HTTP_REFERER"])){
		// 	$_POST["HTTP_REFERER"] = $_SERVER["HTTP_REFERER"];
		// 	if(is_int(strpos($_SERVER["HTTP_REFERER"], "cadastro_usuario.php"))){
		// 		$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/cadastro_usuario.php";
		// 	}
		// }

		// abre_form("Arquivo de Ponto");
		// campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		// linha_form($fields);
		// if (count($camposData) > 0){
		// 	linha_form($camposData);
		// }
		// fecha_form($buttons);

		// rodape();
	// }

	function updateFTP(){
		global $path;

		// connect and login to FTP server
		$infos = mysqli_fetch_assoc(query(
			"SELECT empr_tx_ftpServer, empr_tx_ftpUsername, empr_tx_ftpUserpass FROM empresa "
			." JOIN user ON empresa.empr_nb_id = user.user_nb_empresa"
			." WHERE user_nb_id = ".$_SESSION["user_nb_id"]
		));
		
		if(empty($infos["empr_tx_ftpServer"]) || empty($infos["empr_tx_ftpUserpass"]) || empty($infos["empr_tx_ftpUserpass"])){
			set_status("ERRO: Não foi possível encontrar o servidor FTP.");
			index();
			exit;
		}
		
		
		try{
			$ftp_conn = ftp_connect($infos["empr_tx_ftpServer"]);
			if($ftp_conn === false){
				throw new Exception("Conexção FTP retornou falso.");
			}

			$loggedIn = ftp_login($ftp_conn, $infos["empr_tx_ftpUsername"], $infos["empr_tx_ftpUserpass"]);
			if(!$loggedIn){
				throw new Exception("Login retornou falso.");
			}
		}catch(Exception $e){
			set_status("ERRO: Não foi possível conectar à ".$infos["empr_tx_ftpServer"]);
			echo "<script>console.log('".$e->getMessage()."')</script>";
			index();
			exit;
		}

		$lastFile = mysqli_fetch_assoc(query(
			"SELECT arqu_tx_data FROM arquivoponto"
			." WHERE arqu_tx_status = 'ativo'"
			." AND (arqu_tx_nome REGEXP '[apontamento][[:digit:]]{14}.txt') = 1"
			." ORDER BY arqu_tx_data DESC"
			." LIMIT 1;"
		));

		
		for($data = new DateTime($lastFile["arqu_tx_data"]); $data->format("Y-m-d") <= date("Y-m-d"); $data->modify("+1 day")){
			$fileList = ftp_nlist($ftp_conn, "apontamento".$data->format("dmY")."*.txt");
			if(!empty($fileList)){
				foreach($fileList as $nomeArquivo){
					$fileExists = (
						num_linhas(query(
							"SELECT * FROM arquivoponto"
							." WHERE arqu_tx_status = 'ativo'"
							." AND arqu_tx_nome = '".$nomeArquivo."'"
							." LIMIT 1"
						)) > 0
					);
					if($fileExists){
						continue;
					}
		
					$caminhoCompleto = $path."/".$nomeArquivo;
					
					if(!ftp_get($ftp_conn, $caminhoCompleto, $nomeArquivo)){
						set_status("ERRO: Houve um problema ao salvar o arquivo.");
						index();
						exit;
					}
					saveRegisterFile([$nomeArquivo], $caminhoCompleto);
				}
			}
		}

		ftp_close($ftp_conn);
		if (!empty($_SERVER["HTTP_ENV"]) && $_SERVER["HTTP_ENV"] == "carrega_cron"){
			criar_relatorio(null);
		}
		index();
		exit;
	}

	function saveRegisterFile(array $fileInfo, string $caminhoCompleto){
		global $path;
	    
		//$local_file = $path."/".$nomeArquivo;
		$newPontos = [];
		$baseErrMsg = "ERROS:";
		$errorMsg = [$baseErrMsg];

		//Conferir se as informações necessárias estão preenchidas{
			if(empty($fileInfo["tmp_name"])){
				$errorMsg["camposObrigatorios"] .= "<br>  Nome temporário.";
			}
			if(empty($fileInfo["name"])){
				$errorMsg["camposObrigatorios"] .= "<br>  Nome do arquivo.";
			}
 
			if(isset($errosMsg["camposObrigatorios"])){
				$errorMsg["camposObrigatorios"] = "Informações necessárias não encontradas: ".$errorMsg["camposObrigatorios"];
			}
		//}

		$ext = substr($fileInfo["name"], strrpos($fileInfo["name"], "."));

		$newArquivoPonto = [
			"arqu_tx_nome" 		=> $fileInfo["name"],
			"arqu_tx_data" 		=> date("Y-m-d H:i:s"),
			"arqu_nb_user" 		=> $_SESSION["user_nb_id"],
			"arqu_tx_status" 	=> "ativo"
		];
		
		foreach (file($fileInfo["tmp_name"]) as $line){
		
			//matricula dmYhi 999 macroponto.codigoExterno
			//Obs.: A matrícula deve ter 10 dígitos, então se tiver menos, adicione zeros à esquerda.
			//Ex.: 0000005913 22012024 0919 999 11
			$line = trim($line);
			if (empty($line)) {
				continue; // Pula para a próxima iteração
			}
			[$matricula, $data, $hora, $codigoExterno] = [substr($line, 0, 10), substr($line, 10, 8), substr($line, 18, 4), substr($line,25, 2)];

			//CONFERIR MATRÍCULA{
				while($matricula[0] == "0"){
					$matricula = substr($matricula, 1);
				}
				$matriculaExiste = mysqli_fetch_assoc(query(
					"SELECT enti_tx_matricula FROM entidade 
						WHERE enti_tx_matricula = '".$matricula."'
						LIMIT 1"
				));
				if(empty($matriculaExiste) || count($matriculaExiste) == 0){
					if(empty($errorMsg["registerNotFound"])){
						$errorMsg["registerNotFound"] = "Matrículas não encontradas:";
					}
					$errorMsg["registerNotFound"] .= "<br>	". $matricula;
				}
			//}

			$data = substr($data, 4, 4)."-".substr($data, 2, 2)."-".substr($data, 0, 2);
			$hora = substr($hora, 0, 2).":".substr($hora, 2, 2).":00";

			$macroPonto = mysqli_fetch_assoc(query(
				"SELECT macr_tx_codigoInterno, macr_tx_codigoExterno FROM macroponto"
				." WHERE macr_tx_status = 'ativo'"
					." AND macr_tx_codigoExterno = '".$codigoExterno."'"
				." LIMIT 1;"
			));

			if(empty($macroPonto)){
				if(empty($errorMsg["notRecognized"])){
					$errorMsg["notRecognized"] = "Tipo de ponto não reconhecido: ";
				}
				$errorMsg["notRecognized"] .= "<br>	".$codigoExterno;
				continue;
			}

			$newPonto = [
				"pont_nb_user"			=> $_SESSION["user_nb_id"],
				"pont_nb_arquivoponto"	=> null,						//Será definido após inserir o arquivo de ponto.
				"pont_tx_matricula"		=> strval($matricula),
				"pont_tx_data"			=> $data." ".$hora,
				"pont_tx_tipo"			=> $macroPonto["macr_tx_codigoInterno"],
				"pont_tx_status"		=> "ativo",
				"pont_tx_dataCadastro"	=> date("Y-m-d H:i:s")
			];

			
			$pontoExistente = mysqli_fetch_assoc(query(
				"SELECT pont_nb_id, pont_tx_matricula, pont_tx_data, pont_tx_tipo FROM ponto 
					WHERE pont_tx_status = 'ativo'"
					." AND pont_tx_matricula = '".$newPonto["pont_tx_matricula"]."'"
					." AND pont_tx_data = '".$newPonto["pont_tx_data"]."'"
					." AND pont_tx_tipo = '".$newPonto["pont_tx_tipo"]."'"
				.";"
			));
			
			if(empty($pontoExistente) || count($pontoExistente) == 0){
				$newPontos[] = $newPonto;
				continue;
			}

			$pontoExistente["pont_tx_data"] = explode(" ", $pontoExistente["pont_tx_data"]);
			$pontoExistente["pont_tx_data"][0] = explode("-", $pontoExistente["pont_tx_data"][0]);
			$pontoExistente["pont_tx_data"] = implode("/", array_reverse($pontoExistente["pont_tx_data"][0]))." ".$pontoExistente["pont_tx_data"][1];

			if(empty($errorMsg["existingPoints"])){
				$errorMsg["existingPoints"] = "Pontos já existentes: ";
			}
			$errorMsg["existingPoints"] .= "<br>	".$pontoExistente["pont_tx_matricula"].": (".$pontoExistente["pont_tx_data"].")";
		}

		//*Salvar registros e arquivo{
			move_uploaded_file($fileInfo["tmp_name"],$caminhoCompleto);
			
			$arquivoPontoId = inserir("arquivoponto", array_keys($newArquivoPonto), array_values($newArquivoPonto));
			foreach($newPontos as $newPonto){
				$newPonto["pont_nb_arquivoponto"] = intval($arquivoPontoId[0]);
				inserir("ponto", array_keys($newPonto), array_values($newPonto));
			}

			$errorMsg = implode("\n", $errorMsg);
			if($errorMsg != $baseErrMsg){
				$fileContent = $errorMsg;
				$fileContent = str_replace("<br>	", "\n	", $fileContent);
				file_put_contents($path."/".$fileInfo["name"]."_log".$ext, $fileContent);
	
				set_status(
					"<div style='width:50%; text-align:left;'>"
						."<a href='".($path."/".$fileInfo["name"]."_log".$ext)."'>Visualizar Erros</a>"
					."</div>"
				);
			}
		//}*/
	}

	function index(){
		global $path;
		// if(is_int(strpos($_SERVER["REQUEST_URI"], "carregar_ftp"))){
		// 	carregar_ftp();
		// 	exit;
		// }

		cabecalho("Carregar Ponto", 1);

		$extra = 
			((!empty($_POST["busca_inicio"]))? " AND arqu_tx_data >= '".$_POST["busca_inicio"]."'": "")
			.((!empty($_POST["busca_fim"]))? " AND arqu_tx_data <= '".$_POST["busca_fim"]."'": "")
			.((!empty($_POST["busca_codigo"]))? " AND arqu_nb_id = ".$_POST["busca_codigo"]: "")
		;
		
		//CONSULTA
		$fields = [
			campo("Código", "busca_codigo", ($_POST["busca_codigo"]?? ""), 2),
			campo("Data Início", "busca_inicio", ($_POST["busca_inicio"]?? ""), 2, "MASCARA_DATA"),
			campo("Data Fim", "busca_fim", ($_POST["busca_fim"]?? ""), 2, "MASCARA_DATA")
		];

		//BOTOES
		$buttons = [
			botao("Buscar", "index"),
			botao("Inserir manualmente", "viewManualInsert","","","","","btn btn-success"),
			botao("Atualizar FTP", "updateFTP","","","","","btn btn-primary"),
			// botao("Configuração", "layout_notificacao","","","","","btn btn-warning")
		];

		abre_form("Filtro de Busca");
		linha_form($fields);
		fecha_form($buttons);

		$sql = 
			"SELECT * FROM arquivoponto
				JOIN user ON arqu_nb_user = user_nb_id 
				WHERE arqu_tx_status = 'ativo'
					".$extra."
					ORDER BY arqu_tx_data DESC
				LIMIT 400;"
		;


		$gridValues = [
			"CÓD" => "arqu_nb_id",
			"ARQUIVO" => "arqu_tx_nome",
			"USUÁRIO" => "user_tx_nome",
			"DATA" => "data(arqu_tx_data,1)",
			"SITUAÇÃO" => "ucfirst(arqu_tx_status)",
			"<spam class='glyphicon glyphicon-download' style='font-size: 16px;'></spam>" => "icone_download(arqu_tx_nome)"
		];

		grid($sql, array_keys($gridValues), array_values($gridValues), "", 12, 0, "");
		rodape();
	}
