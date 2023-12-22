<?php

session_start();
include "conecta.php";

function carrega_ponto(){

	$arquivo = 'apontamento' . date('dmY') . '*.txt';
	$path = 'arquivos/pontos/';
	$local_file = $path . $arquivo;
	$arquivo = $_FILES['arquivo'];
	
	if($arquivo['error'] === 0){
		query("SELECT * FROM arquivoponto WHERE arqu_tx_nome = '$arquivo[name]' AND arqu_tx_status = 'ativo' LIMIT 1");
		$local_file = $path . $arquivo['name'];
		
		move_uploaded_file($arquivo['tmp_name'],$path.$arquivo['name']);
		$campos = array('arqu_tx_nome', 'arqu_tx_data', 'arqu_nb_user', 'arqu_tx_status');
		$valores = array($arquivo['name'], date("Y-m-d H:i:s"), $_SESSION['user_nb_id'], 'ativo');
		$idArquivo = inserir('arquivoponto', $campos, $valores);

		foreach (file($local_file) as $line) {
			$line = trim($line);
			$loginMotorista = sprintf('%04d', substr($line, 0, 10));
			$data = substr($line, 10, 8);
			$data = substr($data, 4, 4)."-".substr($data, 2, 2)."-".substr($data, 0, 2);
			$hora = substr($line, 18, 4);
			$hora = substr($hora, 0, 2).":".substr($hora, 2, 2).":00";
			$codigoExterno = substr($line, -2, 2)+0;
			// echo $line."->";
			// echo "$loginMotorista|$data|$hora|$codigoExterno<hr>";
			$queryMacroPonto = query("SELECT macr_tx_codigoInterno FROM macroponto WHERE macr_tx_codigoExterno = '" . $codigoExterno . "'");
			$aTipo = carrega_array($queryMacroPonto);
			$campos = array('pont_nb_user', 'pont_nb_arquivoponto', 'pont_tx_matricula', 'pont_tx_data', 'pont_tx_tipo', 'pont_tx_tipoOriginal', 'pont_tx_status', 'pont_tx_dataCadastro');
			$valores = array($_SESSION['user_nb_id'], $idArquivo, $loginMotorista, "$data $hora", $aTipo[0], $codigoExterno, 'ativo', date("Y-m-d H:i:s"));
			
			$check = query('SELECT * FROM ponto WHERE pont_tx_matricula = '.$loginMotorista.' AND pont_tx_data = "'."$data $hora".'" AND pont_tx_tipo = '.$aTipo[0].' AND pont_tx_tipoOriginal = '.$codigoExterno.';');
			
			if(num_linhas($check) === 0){
				inserir('ponto', $campos, $valores);
			}else{
				set_status("Alguns pontos, Já existe no banco");
			}
		}

	}else{
		set_status("Ocorreu um problema ao gravar o arquivo\n");
	}
	index();
	exit;
}


function layout_ponto(){
	cabecalho('Carregar Ponto');

	//$c[] = campo('Data do Arquivo:','data',date("d/m/Y"),2,MASCARA_DATA);
	$c[] = arquivo('Arquivo Ponto (.txt):', 'arquivo', '', 5);

	$b[] = botao("Enviar", 'carrega_ponto');
	$b[] = botao("Voltar", 'index');

	abre_form('Arquivo de Ponto');
	linha_form($c);
	fecha_form($b);

	rodape();
}


function layout_ftp(){
	// error_reporting(E_ALL);

	$arquivo = 'apontamento' . date('dmY') . '*.txt';
	$path = 'arquivos/pontos/';

	$local_file = $path . $arquivo;
	$server_file = './' . $arquivo;

	// connect and login to FTP server

	$infos = query('SELECT empr_tx_ftpServer, empr_tx_ftpUsername, empr_tx_ftpUserpass FROM empresa join user on empresa.empr_nb_id = user.user_nb_empresa WHERE user_nb_id = '.$_SESSION['user_nb_id'])->fetch_assoc();
	
	
// 	$ftp_server = "ftp-jornadas.positronrt.com.br";

// 	$ftp_username = '08995631000108';

// 	$ftp_userpass = '0899';


	$ftp_conn = ftp_connect($infos['empr_tx_ftpServer']) or die("Could not connect to $infos['empr_tx_ftpServer']");
	$login = ftp_login($ftp_conn, $infos['empr_tx_ftpUsername'], $infos['empr_tx_ftpUserpass']);

	//BUSCA O ARQUIVO

	$fileList = ftp_nlist($ftp_conn, $arquivo);
	// $fileList = array(
	// 	'apontamento27032023010000.txt',
	// 	'apontamento28032023010000.txt',
	// 	'apontamento29032023010000.txt',
	// 	'apontamento30032023010000.txt',
	// 	'apontamento31032023010000.txt'
	// );
// 	print_r($fileList);
// 	die();
	for ($i = 0; $i < count($fileList); $i++) {

		$sqlCheck = "SELECT * FROM arquivoponto WHERE arqu_tx_nome = '$fileList[$i]' AND arqu_tx_status = 'ativo' LIMIT 1";
		$queryCheck = query($sqlCheck);
		if (num_linhas($queryCheck) > 0) continue;

		$local_file = $path . $fileList[$i];

		if (ftp_get($ftp_conn, $local_file, $fileList[$i], FTP_BINARY)) {
			// echo "Successfully written to $path$fileList[$i]<br>";

			$campos = array('arqu_tx_nome', 'arqu_tx_data', 'arqu_nb_user', 'arqu_tx_status');
			$valores = array($fileList[$i], date("Y-m-d H:i:s"), $_SESSION['user_nb_id'], 'ativo');
			$idArquivo = inserir('arquivoponto', $campos, $valores);


			foreach (file($local_file) as $line) {
				$line = trim($line);
				$loginMotorista = sprintf('%04d', substr($line, 0, 10));

				$data = substr($line, 10, 8);
				$data = substr($data, 4, 4) . "-" . substr($data, 2, 2) . "-" . substr($data, 0, 2);

				$hora = substr($line, 18, 4);
				$hora = substr($hora, 0, 2) . ":" . substr($hora, 2, 2) . ":00";

				$codigoExterno = substr($line, -2, 2) + 0;
				// echo $line."->";
				// echo "$loginMotorista|$data|$hora|$codigoExterno<hr>";

				$queryMacroPonto = query("SELECT macr_tx_codigoInterno FROM macroponto WHERE macr_tx_codigoExterno = '" . $codigoExterno . "'");
				$aTipo = carrega_array($queryMacroPonto);

				$campos = array('pont_nb_user', 'pont_nb_arquivoponto', 'pont_tx_matricula', 'pont_tx_data', 'pont_tx_tipo', 'pont_tx_tipoOriginal', 'pont_tx_status', 'pont_tx_dataCadastro');
				$valores = array($_SESSION['user_nb_id'], $idArquivo, $loginMotorista, "$data $hora", $aTipo[0], $codigoExterno, 'ativo', date("Y-m-d H:i:s"));
				inserir('ponto', $campos, $valores);

			}
		} else {
			echo "Houve um problema ao salvar o arquivo.\n";
			exit;
		}
	}

	// var_dump($ftp_conn)
	// // then do something...
	// close connection

	ftp_close($ftp_conn);
	if ($_SERVER['HTTP_ENV'] == 'carrega_cron') {
		exit;
	}
	index();
	exit;

}


function index(){
	global $CACTUX_CONF;
	if ($_SERVER['HTTP_ENV'] == 'carrega_cron') {
		// Aplicar após criar o usuário REP-P
		// $rep_p_user = query('SELECT user_nb_id, user_tx_nivel, user_tx_login FROM user WHERE user_tx_login LIKE "%REP-P%" LIMIT 1');
		// $_SESSION['user_nb_id'] = $rep_p_user['user_nb_id'];
		// $_SESSION['user_tx_nivel'] = $rep_p_user['user_tx_nivel'];
		// $_SESSION['user_tx_login'] = $rep_p_user['user_tx_login'];

		$_SESSION['user_nb_id'] = 1;
		$_SESSION['user_tx_nivel'] = 'Administrador';
		$_SESSION['user_tx_login'] = 'adm';
		// $_SESSION['user_tx_login'] = 'Techps.admin';
		layout_ftp();
		exit;
	}

	cabecalho('Carregar Ponto', 1);

	$extra = '';
	if ($_POST['busca_inicio'])
		$extra .= " AND reto_tx_dataArquivo >= '" . data($_POST['busca_inicio'], 1) . "'";
	if ($_POST['busca_fim'])
		$extra .= " AND reto_tx_dataArquivo <= '" . data($_POST['busca_fim'], 1) . "'";



	//CONSULTA
	$c[] = campo('Código:', 'busca_codigo', $_POST['busca_codigo'], 2);
	$c[] = campo('Data Início:', 'busca_inicio', $_POST['busca_inicio'], 2, 'MASCARA_DATA');
	$c[] = campo('Data Fim:', 'busca_fim', $_POST['busca_fim'], 2, 'MASCARA_DATA');


	//BOTOES
	$b[] = botao("Buscar", 'index');
	$b[] = botao("Inserir", 'layout_ponto');
	$b[] = botao("Atualizar", 'layout_ftp');

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT * FROM arquivoponto,user WHERE arqu_nb_user = user_nb_id AND arqu_tx_status != 'inativo' $extra";
	$cab = array('CÓD', 'ARQUIVO', 'USUÁRIO', 'DATA', 'SITUAÇÃO');

	// $ver2 = "icone_modificar(arqu_nb_id,layout_confirma)";
	$val = array('arqu_nb_id', 'arqu_tx_nome', 'user_tx_nome', 'data(arqu_tx_data,1)', 'ucfirst(arqu_tx_status)');
	grid($sql, $cab, $val, '', '', 0, 'desc');

	rodape();
}