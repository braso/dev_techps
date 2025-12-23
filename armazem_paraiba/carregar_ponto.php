<?php

		ini_set("display_errors", 1);
		error_reporting(E_ALL);
		
        header("Expires: 01 Jan 2001 00:00:00 GMT");
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
        header("Pragma: no-cache"); // HTTP 1.0.
	
	global $path;
	$path = "arquivos/pontos";

	if(is_bool(strpos($_SERVER["REQUEST_URI"], "carregar_ftp"))){
		include_once "carregar_ftp.php";
		unset($interno);
	}
	include_once "funcoes_ponto.php";

	function showErrMsg(string $caminhoCompleto, string $errorMsg): string{
		$fileContent = str_replace("<br>	", "\n	", $errorMsg);
		file_put_contents($caminhoCompleto, $fileContent);
		return "<div style='width:50%; text-align:left;'>"
				."<a href='".($caminhoCompleto)."'>Visualizar Erros</a>"
			."</div>";
	}

	function getLastFileDate($dataAtual): string{

        $dataUltimoArquivo = mysqli_fetch_assoc(query(
            "SELECT arqu_tx_data FROM arquivoponto
				WHERE arqu_tx_status = 'ativo'
					AND arqu_tx_nome LIKE '%apontamento%'
				ORDER BY arqu_tx_data DESC
				LIMIT 1;"
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
			$emails = mysqli_fetch_assoc(query("SELECT * FROM configuracao_alerta LIMIT 1;"));
			if((int)$diferenca <= 6){
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
			
			// Verifica se o diretório de destino existe
			if (!is_dir($path)) {
				// Tenta criar se não existir
				if (!mkdir($path, 0777, true)) {
					set_status("ERRO: Não foi possível criar o diretório de destino: " . $path);
					index();
					exit;
				}
			}

			$local_file = $path."/".$arquivo["name"];

			$ext = substr($arquivo["name"], strrpos($arquivo["name"], "."));
			$baseNomeArquivo = str_replace($ext, "", $arquivo["name"]);

			if(file_exists($local_file)){
				$f = 2;
				$baseNomeArquivo  .= "_".$f;
				for(; file_exists($path."/".$baseNomeArquivo.$ext); $f++){
					$baseNomeArquivo = substr($baseNomeArquivo , 0, strlen($baseNomeArquivo)-2)."_".$f;
				}
				$arquivo["name"] = $baseNomeArquivo;
				$local_file = $path."/".$arquivo["name"].$ext;
			}

			if (!move_uploaded_file($arquivo["tmp_name"], $local_file)) {
				set_status("ERRO: Falha ao mover o arquivo para a pasta de destino.<br>Temp: " . $arquivo["tmp_name"] . "<br>Destino: " . $local_file);
				index();
				exit;
			}

			salvarArquivoPonto($arquivo, $local_file);

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
		$buttons[] = criarBotaoVoltar();

		if(empty($_POST["HTTP_REFERER"])){
			$_POST["HTTP_REFERER"] = $_SERVER["HTTP_REFERER"];
			if(is_int(strpos($_SERVER["HTTP_REFERER"], "carregar_ponto.php"))){
				$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/carregar_ponto.php";
			}
		}

		echo abre_form("Arquivo de Ponto");
		echo linha_form($fields);
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		echo fecha_form($buttons);

		rodape();
	}

	function salvarArquivoPonto(array $arquivo, string $caminhoCompleto){
		global $path;

		//$local_file = $path."/".$arquivo["name"];
		$newPontos = [];
		$baseErrMsg = [
			"ERROS:",
			"camposObrigatorios" => [],
			"registerNotFound" => [],
			"notRecognized" => [],
		];
		$errorMsg = $baseErrMsg;

		//Conferir se as informações necessárias estão preenchidas{
			if(empty($arquivo["tmp_name"])){
				$errorMsg["camposObrigatorios"][] = "Caminho temporário do arquivo.";
			}
			if(empty($caminhoCompleto)){
				$errorMsg["camposObrigatorios"][] = "Caminho do arquivo.";
			}
			if(empty($arquivo["name"])){
				$errorMsg["camposObrigatorios"][] = "Nome do arquivo.";
			}
		//}

		if(!empty($errorMsg["camposObrigatorios"])){
			set_status($errorMsg[0]." Campos obrigatórios não preenchidos: ".implode("<br>	", $errorMsg["camposObrigatorios"]));
			exit;
		}

		$ext = substr($arquivo["name"], strrpos($arquivo["name"], "."));
		$baseNomeArquivo = str_replace($ext, "", $arquivo["name"]);

		$newArquivoPonto = [
			"arqu_tx_nome" 		=> $arquivo["name"],
			"arqu_tx_data" 		=> date("Y-m-d H:i:s"),
			"arqu_nb_user" 		=> $_SESSION["user_nb_id"],
			"arqu_tx_status" 	=> "ativo"
		];

		
		// ini_set("auto_detect_line_endings", true); // Deprecated no PHP 8.1+
		
		// Verifica se o arquivo existe antes de tentar ler
		if (!file_exists($caminhoCompleto)) {
			// Tenta caminho absoluto se relativo falhar
			if (file_exists(__DIR__ . "/" . $caminhoCompleto)) {
				$caminhoCompleto = __DIR__ . "/" . $caminhoCompleto;
			} elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . "/" . $caminhoCompleto)) {
                // Tenta a partir da raiz do servidor
                $caminhoCompleto = $_SERVER['DOCUMENT_ROOT'] . "/" . $caminhoCompleto;
            } else {
				// Debug detalhado do caminho
				$debugInfo = "Caminho Original: " . $caminhoCompleto . "<br>";
				$debugInfo .= "Caminho Absoluto (__DIR__): " . __DIR__ . "/" . $caminhoCompleto . "<br>";
				$debugInfo .= "Caminho Raiz ($_SERVER[DOCUMENT_ROOT]): " . $_SERVER['DOCUMENT_ROOT'] . "/" . $caminhoCompleto . "<br>";
				$debugInfo .= "Diretório Atual (getcwd): " . getcwd() . "<br>";
				
				// Verifica se o diretório existe, para saber se é só o arquivo ou a pasta
				$dir = dirname($caminhoCompleto);
				$debugInfo .= "Diretório do arquivo existe? " . (is_dir($dir) ? "SIM" : "NÃO") . "<br>";
				
				set_status("ERRO: O arquivo não foi encontrado no servidor.<br>" . $debugInfo);
				return;
			}
		}

		$lines = file($caminhoCompleto, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if ($lines === false || empty($lines)) {
			set_status("ERRO: Arquivo vazio ou não pôde ser lido.");
			return;
		}

		foreach($lines as $lineIndex => $line){
			
			//matricula dmYhi 999 macroponto.codigoExterno
			//Obs.: A matrícula deve ter 10 dígitos, então se tiver menos, adicione zeros à esquerda.
			//Ex.: 0000005913 22012024 0919 999 11
			$line = trim($line);
			if(empty($line)){
				continue; // Pula para a próxima linha
			}
			
			// Validação básica de tamanho da linha
			if (strlen($line) < 26) { // 10+8+4+3+2 (aprox)
				// Tenta ser resiliente, mas loga erro se muito curto
				if(empty($errorMsg["notRecognized"])){
					$errorMsg["notRecognized"][] = "Linhas com formato inválido (muito curtas):";
				}
				$errorMsg["notRecognized"][] = "Linha " . ($lineIndex+1) . ": " . htmlspecialchars($line);
				continue;
			}

			[$matricula, $data, $hora, $codigoExterno] = [substr($line, 0, 10), substr($line, 10, 8), substr($line, 18, 4), substr($line,25, 2)];

			//CONFERIR MATRÍCULA{
				while($matricula[0] == "0"){
					$matricula = substr($matricula, 1);
				}
				$matriculaExiste = mysqli_fetch_assoc(query(
					"SELECT enti_tx_matricula FROM entidade 
						WHERE enti_tx_matricula = '{$matricula}'
						LIMIT 1;"
				));

				if(empty($matriculaExiste) || count($matriculaExiste) == 0){
					if(empty($errorMsg["registerNotFound"])){
						$errorMsg["registerNotFound"][] = "Matrículas não encontradas:";
					}
					$errorMsg["registerNotFound"][] = $matricula;
				}
			//}

			$data = substr($data, 4, 4)."-".substr($data, 2, 2)."-".substr($data, 0, 2);
			$hora = substr($hora, 0, 2).":".substr($hora, 2, 2).":00";

			// Mapeamento manual de códigos externos para internos, já que o banco não tem esses códigos exatos
			$mapaCodigos = [
				'01' => ['interno' => 1, 'nome' => 'Inicio de Jornada'],
				'02' => ['interno' => 2, 'nome' => 'Fim de Jornada'],
				'03' => ['interno' => 3, 'nome' => 'Inicio de Refeição'],
				'04' => ['interno' => 4, 'nome' => 'Fim de Refeição']
			];

			if (isset($mapaCodigos[$codigoExterno])) {
				// Usa o mapeamento manual
				$macroPonto = [
					"macr_tx_codigoInterno" => $mapaCodigos[$codigoExterno]['interno'],
					"macr_tx_nome" => $mapaCodigos[$codigoExterno]['nome']
				];
			} else {
				// Tenta buscar no banco
				$macroPonto = mysqli_fetch_assoc(query(
					"SELECT macr_tx_codigoInterno, macr_tx_codigoExterno FROM macroponto"
					." WHERE macr_tx_status = 'ativo'"
						// ." AND macr_tx_fonte = 'positron'" // Removido filtro de fonte para ser mais abrangente
						." AND (macr_tx_codigoExterno = '".$codigoExterno."' OR macr_tx_codigoInterno = '".intval($codigoExterno)."')"
					." LIMIT 1;"
				));
			}

			$userId = mysqli_fetch_assoc(query(
				"SELECT user_nb_id FROM user 
					JOIN entidade ON user_nb_entidade = enti_nb_id
					WHERE user_tx_status = 'ativo' AND enti_tx_matricula = '{$matricula}'
					LIMIT 1;"
			));

			if(empty($macroPonto)){
				if(empty($errorMsg["notRecognized"])){
					$errorMsg["notRecognized"][] = "Tipo de ponto não reconhecido: ";
				}
				$errorMsg["notRecognized"][] = $codigoExterno;
				continue;
			}

			$newPonto = [
				"pont_nb_userCadastro"	=> $userId["user_nb_id"],
				"pont_nb_arquivoponto"	=> null,						//Será definido após inserir o arquivo de ponto.
				"pont_tx_matricula"		=> strval($matricula),
				"pont_tx_data"			=> $data." ".$hora,
				"pont_tx_tipo"			=> $macroPonto["macr_tx_codigoInterno"],
				"pont_tx_status"		=> "ativo",
				"pont_tx_dataCadastro"	=> date("Y-m-d H:i:s")
			];

			$pontoExistente = mysqli_fetch_assoc(query(
				"SELECT pont_nb_id, pont_tx_matricula, pont_tx_data, pont_tx_tipo FROM ponto 
					WHERE 1"
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
				$errorMsg["existingPoints"][] = "Pontos já existentes: ";
			}
			$errorMsg["existingPoints"][] = $pontoExistente["pont_tx_matricula"].": (".$pontoExistente["pont_tx_data"].")";
		}

		//*Salvar registros e arquivo{
			if (empty($newPontos)) {
				// Se não há pontos para inserir, não faz sentido inserir o arquivo vazio, a menos que todos sejam duplicados e queiramos registrar o arquivo.
				// Mas o usuário relatou que não insere.
				// Vamos forçar um erro se não houver pontos e não houver duplicados detectados.
				
				if (empty($errorMsg["existingPoints"]) && empty($errorMsg["notRecognized"]) && empty($errorMsg["registerNotFound"])) {
					// Situação crítica: arquivo lido, mas nenhum ponto gerado e nenhum erro padrão.
					// Provavelmente layout errado que passou despercebido?
					set_status("ERRO CRÍTICO: O arquivo foi lido mas nenhum ponto foi identificado para importação. Verifique o formato do arquivo.");
					// Debug visual para o usuário
					echo "<h1>Debug de Importação</h1>";
					echo "<p>Total linhas lidas: " . count($lines) . "</p>";
					echo "<pre>";
					foreach($lines as $i => $l){
						if($i > 10) break;
						echo "Linha $i: " . htmlspecialchars($l) . "\n";
					}
					echo "</pre>";
					exit;
				} elseif (!empty($errorMsg["existingPoints"]) && count($newPontos) == 0) {
					// Apenas duplicatas
					set_status("Todos os pontos do arquivo já existem no sistema.");
					// Opcional: Ainda assim inserir o arquivo como histórico? O código original inseria.
					// Mas se não há novos pontos, $newPontos está vazio.
					// Vamos permitir continuar para mostrar o relatório de erros (duplicatas).
				}
			}

			// Log pré-transação
			file_put_contents($path."/debug_importacao.log", date("Y-m-d H:i:s") . " - Iniciando transação. Pontos a inserir: " . count($newPontos) . "\n", FILE_APPEND);

			query("START TRANSACTION;");
			try {
				$arquivoPontoId = inserir("arquivoponto", array_keys($newArquivoPonto), array_values($newArquivoPonto));
				
				if(empty($arquivoPontoId) || !isset($arquivoPontoId[0]) || ($arquivoPontoId[0] instanceof Exception)){
					throw new Exception("Falha ao inserir arquivo de ponto.");
				}

				$idArquivo = intval($arquivoPontoId[0]);

				foreach($newPontos as $newPonto){
					$newPonto["pont_nb_arquivoponto"] = $idArquivo;
					$resultPonto = inserir("ponto", array_keys($newPonto), array_values($newPonto));
					
					if(empty($resultPonto) || !isset($resultPonto[0]) || ($resultPonto[0] instanceof Exception)){
						// Se falhar um ponto, lançamos exceção para reverter tudo
						$erroMsg = ($resultPonto[0] instanceof Exception) ? $resultPonto[0]->getMessage() : "Erro desconhecido ao inserir ponto.";
						throw new Exception("Falha ao inserir ponto para matrícula " . $newPonto['pont_tx_matricula'] . ": " . $erroMsg);
					}
				}
				query("COMMIT;");
			} catch (Exception $e) {
				query("ROLLBACK;");
				$msgErro = "Erro ao salvar pontos: " . $e->getMessage();
				set_status($msgErro);
				// Log para debug
				file_put_contents($path."/debug_importacao.log", date("Y-m-d H:i:s") . " - " . $msgErro . "\n", FILE_APPEND);
				return;
			}

			if($errorMsg != $baseErrMsg){
				foreach($errorMsg as $key => $value){
					if(is_array($value)){
						$errorMsg[$key] = implode("<br>	", array_unique($value));
					}
				}
				$errorMsg = implode("\n", $errorMsg);
				set_status(showErrMsg($path."/".$baseNomeArquivo."_log".$ext, $errorMsg));
			}
		//}*/
	}

	function index(){
		global $path;
		
		if(is_int(strpos($_SERVER["REQUEST_URI"], "carregar_ftp"))){
			carregar_ftp($path);
			exit;
		}
		
		cabecalho("Carregar Ponto", 1);
		
		//CONSULTA
		$fields = [
			campo("Código", "busca_codigo", ($_POST["busca_codigo"]?? ""), 2),
			campo("Data Início", "busca_inicio_ge", ($_POST["busca_inicio_ge"]?? ""), 2, "MASCARA_DATA"),
		];

		//BOTOES
		$buttons = [
			botao("Buscar", "index"),
			botao("Inserir novo arquivo", "viewManualInsert", "", "", "", "", "btn btn-success"),
			botao("Atualizar FTP", "updateFTP('".$path."')", "path", $path, "", "", "btn btn-primary"),
			// botao("Configuração", "layout_notificacao", "", "", "", "", "btn btn-warning")
		];

		echo abre_form();
		echo linha_form($fields);
		echo fecha_form($buttons);

		// Grid dinâmico{
			$gridFields = [
				"CÓD" 				=> "arqu_nb_id",
				"ARQUIVO" 			=> "arqu_tx_nome",
				"USUÁRIO" 			=> "user_tx_nome",
				"CARREGADO EM" 		=> "CONCAT('data(\"', arqu_tx_data, '\")') AS arqu_tx_data",
				"STATUS" 			=> "arqu_tx_status"
			];

			$camposBusca = [
				"busca_codigo" 		=> "arqu_nb_id",
				"busca_inicio_ge"	=> "arqu_tx_data",
			];

			$queryBase = (
				"SELECT ".implode(", ", array_values($gridFields))." FROM arquivoponto"
				." JOIN user ON arqu_nb_user = user_nb_id"
			);

			$actions = criarIconesGrid(
				["glyphicon glyphicon-download glyphicon-clickable"],
				["carregar_ponto.php"],
				["downloadArquivo()"]
			);

			$gridFields["actions"] = $actions["tags"];
	
			$actions["functions"] = [
				"$('[class=\"glyphicon glyphicon-download glyphicon-clickable\"]').click(function(event){
					form = document.createElement('form');
					form.setAttribute('method', 'post');
					form.setAttribute('action', 'carregar_ponto.php');
					
					fileNameInput = document.createElement('input');
					fileNameInput.setAttribute('name', 'caminho');
					fileNameInput.setAttribute('value', './arquivos/pontos/' + $(event.target).parent().parent().children()[1].innerHTML);
					form.appendChild(fileNameInput);
					
					actionInput = document.createElement('input');
					actionInput.setAttribute('name', 'acao');
					actionInput.setAttribute('value', 'downloadArquivo()');
					form.appendChild(actionInput);

					inputs = document.contex_form.getElementsByTagName('input');
					selects = document.contex_form.getElementsByTagName('select');

					if(inputs != undefined){
						for(key in inputs){
							if(inputs[key].value != undefined && inputs[key].value != ''){
								form.appendChild(inputs[key].cloneNode(true));
							}
						}
					}
					if(selects != undefined){
						for(key in selects){
							if(selects[key].value != undefined && selects[key].value != ''){
								form.appendChild(selects[key].cloneNode(true));
							}
						}
					}

					document.getElementsByTagName('body')[0].appendChild(form);
					form.submit();
				});"
			];

	
			$jsFunctions =
				"const funcoesInternas = function(){
					".implode(" ", $actions["functions"])."
				}
				orderCol = 'arqu_nb_id DESC';"
			;

			echo gridDinamico("tabelaArquivos", $gridFields, $camposBusca, $queryBase, $jsFunctions);
		// }
		rodape();
	}
