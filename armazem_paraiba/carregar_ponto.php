<?php
	/*Modo debug{
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//}*/
	
	include_once "conecta.php";
	include_once "alerta_carrega_ponto.php";

	function carrega_ponto(){

		$arquivo = 'apontamento'.date('dmY').'*.txt';
		$path = 'arquivos/pontos/';
		$local_file = $path.$arquivo;
		$arquivo = $_FILES['arquivo'];
		
		if($arquivo['error'] === 0){
			$local_file = $path.$arquivo['name'];
			
			move_uploaded_file($arquivo['tmp_name'],$path.$arquivo['name']);
			$campos = ['arqu_tx_nome', 'arqu_tx_data', 'arqu_nb_user', 'arqu_tx_status'];
			$valores = [$arquivo['name'], date("Y-m-d H:i:s"), $_SESSION['user_nb_id'], 'ativo'];
			$newArquivoPonto = [
				'arqu_tx_nome' 		=> $arquivo['name'],
				'arqu_tx_data' 		=> date("Y-m-d H:i:s"),
				'arqu_nb_user' 		=> $_SESSION['user_nb_id'],
				'arqu_tx_status' 	=> 'ativo'
			];

			$newPontos = [];

			$error = false;

			foreach (file($local_file) as $line) {
				//matricula dmYhi 999 macroponto.codigoExterno
				//Obs.: A matrícula deve ter 10 dígitos, então se tiver menos, adicione zeros à esquerda.
				//Ex.: 000000591322012024091999911
				$line = trim($line);
				$matricula = substr($line, 0, 10)+0;

				$data = substr($line, 10, 8);
				$data = substr($data, 4, 4)."-".substr($data, 2, 2)."-".substr($data, 0, 2);

				$hora = substr($line, 18, 4);
				$hora = substr($hora, 0, 2).":".substr($hora, 2, 2).":00";

				$codigoExterno = substr($line, -2, 2)+0;

				$queryMacroPonto = query("SELECT macr_tx_codigoInterno FROM macroponto WHERE macr_tx_codigoExterno = '".$codigoExterno."'");

				$aTipo = carrega_array($queryMacroPonto);

				$newPonto = [
					'pont_nb_user'			=> $_SESSION['user_nb_id'],
					'pont_nb_arquivoponto'	=> null,						//Será definido após inserir o arquivo de ponto.
					'pont_tx_matricula'		=> $matricula,
					'pont_tx_data'			=> $data." ".$hora,
					'pont_tx_tipo'			=> $aTipo[0],
					'pont_tx_tipoOriginal'	=> $codigoExterno,
					'pont_tx_status'		=> 'ativo',
					'pont_tx_dataCadastro'	=> date("Y-m-d H:i:s")
				];
				
				$check = query(
					"SELECT * FROM ponto 
						WHERE pont_tx_matricula = ".$newPonto['pont_tx_matricula']."
							AND pont_tx_data = '".$newPonto['pont_tx_data']."'
							AND pont_tx_tipo = '".$newPonto['pont_tx_tipo']."'
							AND pont_tx_tipoOriginal = '".$newPonto['pont_tx_tipoOriginal']."';"
				);
				
				if(num_linhas($check) === 0){
					$newPontos[] = $newPonto;
				}else{
					$error = true;
					set_status("Alguns pontos já existem no banco.");
					break;
				}
			}

			if(!$error){
				$arquivoPontoId = inserir('arquivoponto', array_keys($newArquivoPonto), array_values($newArquivoPonto));
				foreach($newPontos as $newPonto){
					$newPonto['pont_nb_arquivoponto'] = intval($arquivoPontoId);
					inserir('ponto', array_keys($newPonto), array_values($newPonto));
				}
			}
		}else{
			set_status("Ocorreu um problema ao gravar o arquivo.\n");
		}
		index();
		exit;
	}


	function layout_ponto(){
		cabecalho('Carregar Ponto');

		//$c[] = campo('Data do Arquivo:','data',date("d/m/Y"),2,MASCARA_DATA);
		$c[] = arquivo('Arquivo Ponto (.txt):', 'arquivo', '', 5);

		$b[] = botao("Enviar", 'carrega_ponto','','','','','btn btn-success');
		$b[] = botao("Voltar", 'index');

		abre_form('Arquivo de Ponto');
		linha_form($c);
		fecha_form($b);

		rodape();
	}

	function cadastra_notificacao(){
		
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

	function layout_ftp(){
		// error_reporting(E_ALL);

		$arquivo = 'apontamento'.date('dmY').'*.txt';
		$path = 'arquivos/pontos/';

		$local_file = $path.$arquivo;
		$server_file = './'.$arquivo;

		// connect and login to FTP server

		$infos = query('SELECT empr_tx_ftpServer, empr_tx_ftpUsername, empr_tx_ftpUserpass FROM empresa join user on empresa.empr_nb_id = user.user_nb_empresa WHERE user_nb_id = '.$_SESSION['user_nb_id'])->fetch_assoc();
		
		
	// 	$ftp_server = "ftp-jornadas.positronrt.com.br";

	// 	$ftp_username = '08995631000108';

	// 	$ftp_userpass = '0899';


		$ftp_conn = ftp_connect($infos['empr_tx_ftpServer']) or die("Could not connect to $infos[empr_tx_ftpServer]");
		$login = ftp_login($ftp_conn, $infos['empr_tx_ftpUsername'], $infos['empr_tx_ftpUserpass']);

		//BUSCA O ARQUIVO

		$fileList = ftp_nlist($ftp_conn, $arquivo);
		for ($i = 0; $i < count($fileList); $i++) {

			$sqlCheck = "SELECT * FROM arquivoponto WHERE arqu_tx_nome = '$fileList[$i]' AND arqu_tx_status = 'ativo' LIMIT 1";
			$queryCheck = query($sqlCheck);
			if (num_linhas($queryCheck) > 0) continue;

			$local_file = $path.$fileList[$i];

			if (ftp_get($ftp_conn, $local_file, $fileList[$i], FTP_BINARY)) {
				// echo "Successfully written to $path$fileList[$i]<br>";

				$campos = ['arqu_tx_nome', 'arqu_tx_data', 'arqu_nb_user', 'arqu_tx_status'];
				$valores = [$fileList[$i], date("Y-m-d H:i:s"), $_SESSION['user_nb_id'], 'ativo'];
				$idArquivo = inserir('arquivoponto', $campos, $valores)[0];


				foreach (file($local_file) as $line) {
					$line = trim($line);
					$matricula = substr($line, 0, 10) + 0;

					$data = substr($line, 10, 8);
					$data = substr($data, 4, 4)."-".substr($data, 2, 2)."-".substr($data, 0, 2);

					$hora = substr($line, 18, 4);
					$hora = substr($hora, 0, 2).":".substr($hora, 2, 2).":00";

					$codigoExterno = substr($line, -2, 2) + 0;

					$queryMacroPonto = query("SELECT macr_tx_codigoInterno FROM macroponto WHERE macr_tx_codigoExterno = '".$codigoExterno."'");
					$aTipo = carrega_array($queryMacroPonto);

					$campos = ['pont_nb_user', 'pont_nb_arquivoponto', 'pont_tx_matricula', 'pont_tx_data', 'pont_tx_tipo', 'pont_tx_tipoOriginal', 'pont_tx_status', 'pont_tx_dataCadastro'];
					$valores = [$_SESSION['user_nb_id'], $idArquivo, $matricula, "$data $hora", $aTipo[0], $codigoExterno, 'ativo', date("Y-m-d H:i:s")];
					inserir('ponto', $campos, $valores);

				}
			} else {
				echo "Houve um problema ao salvar o arquivo.\n";
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
			exit;
		}
		index();
		exit;
	}

	function index(){
		global $CACTUX_CONF;
		if ($_SERVER['HTTP_ENV'] == 'carrega_cron') {
			// Aplicar após criar o usuário REP-P
			$rep_p_user = query('SELECT user_nb_id, user_tx_nivel, user_tx_login FROM user WHERE user_tx_login LIKE "%Techps.admin%" LIMIT 1');
			$user = mysqli_fetch_all($rep_p_user, MYSQLI_ASSOC);

 			$_SESSION['user_nb_id'] = $user[0]['user_nb_id'];
			$_SESSION['user_tx_nivel'] = $user[0]['user_tx_nivel'];
			$_SESSION['user_tx_login'] = $user[0]['user_tx_login'];

// 			$_SESSION['user_nb_id'] = 138;
// 			$_SESSION['user_tx_nivel'] = 'Super Administrador';
// 			$_SESSION['user_tx_login'] = 'Techps.admin';
			layout_ftp();
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
			campo('Código:', 'busca_codigo', ($_POST['busca_codigo']?? ''), 2),
			campo('Data Início:', 'busca_inicio', ($_POST['busca_inicio']?? ''), 2, 'MASCARA_DATA'),
			campo('Data Fim:', 'busca_fim', ($_POST['busca_fim']?? ''), 2, 'MASCARA_DATA')
		];

		//BOTOES
		$b = [
			botao("Buscar", 'index'),
			botao("Inserir", 'layout_ponto','','','','','btn btn-success'),
			botao("Atualizar", 'layout_ftp','','','','','btn btn-primary'),
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

		$cab = ['CÓD', 'ARQUIVO', 'USUÁRIO', 'DATA', 'SITUAÇÃO'];

		$val = ['arqu_nb_id', 'arqu_tx_nome', 'user_tx_nome', 'data(arqu_tx_data,1)', 'ucfirst(arqu_tx_status)'];
		grid($sql, $cab, $val, '', '', 0, '');

		rodape();
	}
?>