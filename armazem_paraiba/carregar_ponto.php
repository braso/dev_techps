<?php
	/* Modo debug{
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//}*/

	include_once "funcoes_ponto.php";
	include_once "conecta.php";
	include_once "alerta_carrega_ponto.php";

	function insertFile(){

		$arquivo = $_FILES['arquivo'];
		$path = 'arquivos/pontos/';
		$local_file = $path.$arquivo["name"];
		
		if(isset($arquivo["error"]) && $arquivo["error"] === 0){
			$local_file = $path.$arquivo["name"];
			
     		saveFileRegisters($arquivo["name"], $local_file);

		}else{
			set_status("ERRO: Ocorreu um problema ao gravar o arquivo.");
		}
		index();
		exit;
	}

	function insertFileView(){
		cabecalho('Carregar Ponto');

		//$c[] = campo('Data do Arquivo','data',date("d/m/Y"),2,MASCARA_DATA);
		$c[] = arquivo('Arquivo Ponto (.txt)', 'arquivo', '', 5);

		$b[] = botao("Enviar", 'insertFile','','','','','btn btn-success');
		$b[] = botao("Voltar", 'index');

		abre_form('Arquivo de Ponto');
		linha_form($c);
		fecha_form($b);

		rodape();
	}

	function cadastra_notificacao(){
		die("cadastra_notificacao");
		
		$campos = ['conf_tx_emailFun','conf_tx_emailAdm'];
		$valores = [$_POST['emailFuncionario'],$_POST['emailFuncionario']];

		if(!empty($_POST['id'])) {
			$campos = array_merge($campos,['conf_tx_dataAtualiza']);
			$valores = array_merge($valores,[date("Y-m-d H:i:s")]);
			atualizar('configuracao_alerta',$campos,$valores,$_POST['id']);
		}else {
			$campos = array_merge($campos,['conf_tx_dataCadastro']);
			$valores = array_merge($valores,[date("Y-m-d H:i:s")]);
			inserir('configuracao_alerta',$campos,$valores);
		}

		index();
		exit;
	}

	function layout_notificacao(){
		$sqlCheck = query("SELECT * FROM `configuracao_alerta` LIMIT 1");
		$emails = mysqli_fetch_assoc($sqlCheck);

		if (!empty($emails)) {
			$emailFun = $emails['conf_tx_emailFun'];
			$emailAdm = $emails['conf_tx_emailAdm'];
			$atualizacao = $emails['conf_tx_dataAtualiza'];
			$cadastro = $emails['conf_tx_dataCadastro'];
		}
		

		$cAtualiza = [];
		if (!empty($cadastro)) {
			$txtCadastro = "Registro inserido às ".data($cadastro, 1).".";
			$cAtualiza[] = texto("Data de Cadastro", "$txtCadastro", 5);
		}

		if (!empty($atualizacao)) {
			$txtAtualiza = "Registro atualizado às ".data($atualizacao, 1).".";
			$cAtualiza[] = texto("Última Atualização", "$txtAtualiza", 5);
		}

		cabecalho('Configura Notificação');

		//$c[] = campo('Data do Arquivo:','data',date("d/m/Y"),2,MASCARA_DATA);
		$c = [
			campo('E-mail do Funcionario', 'emailFuncionario', $emailFun, 2),
			campo('E-mail do Administrado', 'emailAdministrado', $emailAdm, 2)
		];

		$b= [ 
			botao("Gravar", 'cadastra_notificacao', 'id', $_POST['id']),
			botao("Voltar", 'index')
		];

		abre_form('Arquivo de Ponto');
		linha_form($c);
		if (count($cAtualiza) > 0){
			linha_form($cAtualiza);
		}
		fecha_form($b);

		rodape();
	}

	function updateFTP(){
		$arquivo = 'apontamento'.date('dmY').'*.txt';
		$path = 'arquivos/pontos/';
		$local_file = $path.$arquivo;

		$server_file = './'.$arquivo;

		// connect and login to FTP server
		$infos = query('SELECT empr_tx_ftpServer, empr_tx_ftpUsername, empr_tx_ftpUserpass FROM empresa join user on empresa.empr_nb_id = user.user_nb_empresa WHERE user_nb_id = '.$_SESSION['user_nb_id'])->fetch_assoc();
		
		if(empty($infos["empr_tx_ftpServer"]) || empty($infos["empr_tx_ftpUserpass"]) || empty($infos["empr_tx_ftpUserpass"])){
			set_status("ERRO: Não foi possível encontrar o servidor FTP.");
			index();
			exit;
		}

		try{
			$ftp_conn = ftp_connect($infos['empr_tx_ftpServer']);
			$login = ftp_login($ftp_conn, $infos['empr_tx_ftpUsername'], $infos['empr_tx_ftpUserpass']);
		}catch(Exception $e){
			set_status("Não foi possível conectar a ".$infos['empr_tx_ftpServer']);
			echo "<script>console.log('".$e->getMessage()."')</script>";
			index();
			exit;
		}

		//BUSCA O ARQUIVO

		$fileList = ftp_nlist($ftp_conn, $arquivo);
		foreach ($fileList as $arquivoNome) {
			$sqlCheck = "SELECT * FROM arquivoponto WHERE arqu_tx_nome = '$arquivoNome' AND arqu_tx_status = 'ativo' LIMIT 1";
			if (num_linhas(query($sqlCheck)) > 0) {
				continue;
			}

      		$local_file = $path.$arquivoNome;

			if (ftp_get($ftp_conn, $local_file, $arquivoNome, FTP_BINARY)) {
				saveFileRegisters($arquivoNome, $local_file);
			} else {
				echo "Houve um problema ao salvar o arquivo.";
				exit;
			}
		}
		
		if(count($fileList) === 0){
			diffData(date('dmY'));
			index();
			exit;
		}

		ftp_close($ftp_conn);
		if ($_SERVER['HTTP_ENV'] == 'carrega_cron'){
			criar_relatorio(null);
			exit;
		}
		index();
		exit;
	}

	function saveFileRegisters($arquivoNome, $local_file){

		
		$arquivo = $_FILES['arquivo'];
		$path = 'arquivos/pontos/';
		$local_file = $path.$arquivo['name'];

		$ext = substr($arquivoNome, strrpos($arquivoNome, "."));
		$arquivoNome = str_replace($ext, "", $arquivoNome);

		if(file_exists($path.$arquivoNome)){
			$f = 2;
			$arquivoNome .= "_".$f;
			for(; file_exists($path.$arquivoNome); $f++){
				$arquivoNome = substr($arquivoNome, 0, strlen($arquivoNome)-2)."_".$f;
			}
		}

		$newArquivoPonto = [
			'arqu_tx_nome' 		=> $arquivoNome.$ext,
			'arqu_tx_data' 		=> date("Y-m-d H:i:s"),
			'arqu_nb_user' 		=> $_SESSION['user_nb_id'],
			'arqu_tx_status' 	=> 'ativo'
		];

		$newPontos = [];
		$error = false;
		$errorMsg = ["ERROS:"];

		foreach (file($arquivo['tmp_name']) as $line) {
			//matricula dmYhi 999 macroponto.codigoExterno
			//Obs.: A matrícula deve ter 10 dígitos, então se tiver menos, adicione zeros à esquerda.
			//Ex.: 000000591322012024091999911
			$line = trim($line);

			if ($line === '') {
				continue; // Pula para a próxima iteração
			}

			//CONFERIR MATRÍCULA{
				$matricula = substr($line, 0, 10);
				while($matricula[0] == "0"){
					$matricula = substr($matricula, 1);
				}
				// $matriculaExiste = mysqli_fetch_assoc(query(
				// 	"SELECT enti_tx_matricula FROM entidade 
				// 		WHERE enti_tx_matricula = '".$matricula."'
				// 		LIMIT 1"
				// ));
				// if(empty($matriculaExiste) || count($matriculaExiste) == 0){
				// 	$error = true;
				// 	if(empty($errorMsg["registerNotFound"])){
				// 		$errorMsg["registerNotFound"] = "Matrículas não encontradas:";
				// 	}
				// 	$errorMsg["registerNotFound"] .= "<br>	". $matricula;
				// }
			//}

			$data = substr($line, 10, 8);
			$data = substr($data, 4, 4)."-".substr($data, 2, 2)."-".substr($data, 0, 2);

			$hora = substr($line, 18, 4);
			$hora = substr($hora, 0, 2).":".substr($hora, 2, 2).":00";

			$codigoExterno = substr($line, -2, 2);

			$macroPonto = mysqli_fetch_assoc(query("SELECT macr_tx_codigoInterno, macr_tx_codigoExterno FROM macroponto WHERE macr_tx_codigoExterno = '".$codigoExterno."'"));

			if(empty($macroPonto)){
				$error = true;
				if(empty($errorMsg["notRecognized"])){
					$errorMsg["notRecognized"] = "Tipo de ponto não reconhecido: ";
				}
				$errorMsg["notRecognized"] .= "<br>	".$codigoExterno;
				break;
			}

			$newPonto = [
				'pont_nb_user'			=> $_SESSION['user_nb_id'],
				'pont_nb_arquivoponto'	=> null,						//Será definido após inserir o arquivo de ponto.
				'pont_tx_matricula'		=> strval($matricula),
				'pont_tx_data'			=> $data." ".$hora,
				'pont_tx_tipo'			=> $macroPonto['macr_tx_codigoInterno'],
				'pont_tx_status'		=> 'ativo',
				'pont_tx_dataCadastro'	=> date("Y-m-d H:i:s")
			];

			
			$check = mysqli_fetch_assoc(query(
				"SELECT pont_nb_id, pont_tx_matricula, pont_tx_data, pont_tx_tipo FROM ponto 
					WHERE pont_tx_status = 'ativo'"
					." AND pont_tx_matricula = '".$newPonto['pont_tx_matricula']."'"
					." AND pont_tx_data = '".$newPonto['pont_tx_data']."'"
					." AND pont_tx_tipo = '".$newPonto['pont_tx_tipo']."'"
				.";"
			));
			
			if(empty($check) || count($check) === 0){
				$newPontos[] = $newPonto;
			}else{
				$error = true;
				$check["pont_tx_data"] = explode(" ", $check["pont_tx_data"]);
				$check["pont_tx_data"][0] = explode("-", $check["pont_tx_data"][0]);
				$check["pont_tx_data"] = implode("/", array_reverse($check["pont_tx_data"][0]))." ".$check["pont_tx_data"][1];

				if(empty($errorMsg["existingPoints"])){
					$errorMsg["existingPoints"] = "Pontos já existentes: ";
				}
				$errorMsg["existingPoints"] .= "<br>	".$check["pont_tx_matricula"].": (".$check["pont_tx_data"].")";
			}
		}

		if(!$error){
			move_uploaded_file($arquivo['tmp_name'],$local_file);
			
			$arquivoPontoId = inserir('arquivoponto', array_keys($newArquivoPonto), array_values($newArquivoPonto));
			foreach($newPontos as $newPonto){
				$newPonto['pont_nb_arquivoponto'] = intval($arquivoPontoId[0]);
				inserir('ponto', array_keys($newPonto), array_values($newPonto));
			}
		}else{

			$fileContent = implode("\n", $errorMsg);
			$fileContent = str_replace("<br>	", "\n	", $fileContent);
			file_put_contents($path.$arquivoNome."_log".$ext, $fileContent);

			set_status(
				"<div style='width:50%; text-align:left;'>"
					."<a href='".($path.$arquivoNome."_log".$ext)."'>Visualizar Erros</a>"
				."</div>"
			);
		}
	}

	function index(){
		if(is_int(strpos($_SERVER["REQUEST_URI"], "carregar_ftp"))){
			if (!empty($_SERVER['HTTP_ENV']) && $_SERVER['HTTP_ENV'] == 'carrega_cron') {
				// Aplicar após criar o usuário REP-P
				$rep_p_user = query("SELECT user_nb_id, user_tx_nivel, user_tx_login FROM user WHERE user_tx_login LIKE '%Techps.admin%' LIMIT 1");
				$user = mysqli_fetch_all($rep_p_user, MYSQLI_ASSOC);
	
				 $_SESSION['user_nb_id'] 	= $user[0]['user_nb_id'];
				$_SESSION['user_tx_nivel'] 	= $user[0]['user_tx_nivel'];
				$_SESSION['user_tx_login'] 	= $user[0]['user_tx_login'];
				updateFTP();
			}else{
				echo "Server info not found.";
			}

			exit;
		}

		cabecalho('Carregar Ponto', 1);

		$extra = '';
		if (!empty($_POST['busca_inicio'])){
			$extra .= " AND arqu_tx_data >= '".$_POST['busca_inicio']."'";
		}
		if (!empty($_POST['busca_fim'])){
			$extra .= " AND arqu_tx_data <= '".$_POST['busca_fim']."'";
		}
		if (!empty($_POST['busca_codigo'])){
			$extra .= " AND arqu_nb_id = $_POST[busca_codigo]";
		}

		//CONSULTA
		$c = [
			campo('Código', 'busca_codigo', ($_POST['busca_codigo']?? ''), 2),
			campo('Data Início', 'busca_inicio', ($_POST['busca_inicio']?? ''), 2, 'MASCARA_DATA'),
			campo('Data Fim', 'busca_fim', ($_POST['busca_fim']?? ''), 2, 'MASCARA_DATA')
		];

		//BOTOES
		$b = [
			botao("Buscar", 'index'),
			botao("Inserir", 'insertFileView','','','','','btn btn-success'),
			botao("Atualizar FTP", 'updateFTP','','','','','btn btn-primary'),
			botao("Configuração", 'layout_notificacao','','','','','btn btn-warning')
		];

		abre_form('Filtro de Busca');
		linha_form($c);
		fecha_form($b);

		$sql = 
			"SELECT * FROM arquivoponto, user
				WHERE arqu_nb_user = user_nb_id
					AND arqu_tx_status != 'inativo'
					".$extra."
					ORDER BY arqu_tx_data DESC
				LIMIT 400"
		;


		$gridValues = [
			'CÓD' => "arqu_nb_id",
			'ARQUIVO' => "arqu_tx_nome",
			'USUÁRIO' => "user_tx_nome",
			'DATA' => "data(arqu_tx_data,1)",
			'SITUAÇÃO' => "ucfirst(arqu_tx_status)",
			'<spam class="glyphicon glyphicon-download" style="font-size: 16px;"></spam>' => "icone_download(arqu_tx_nome)"
		];

		grid($sql, array_keys($gridValues), array_values($gridValues), '', 12, 0, '');

		rodape();
	}
?>