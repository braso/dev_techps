<?php
include "conecta.php";

function exclui_parametro(){

	remover('parametro',$_POST['id']);
	index();
	exit;

}

function downloadArquivo() {
    // Verificar se o arquivo existe
    if (file_exists($_POST['caminho'])) {
        // Configurar cabeçalhos para forçar o download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($_POST['caminho']));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($_POST['caminho']));

        // Lê o arquivo e o envia para o navegador
        readfile($_POST['caminho']);
        exit;
    } else {
        echo 'O arquivo não foi encontrado.';
    }
	$_POST['id'] = $_POST['idParametro'];
	modifica_parametro();
	exit;
}

function enviar_documento() {
	$idParametro = $_POST['idParametro'];
	$arquivos =  $_FILES['file'];
	$novo_nome = $_POST['file-name'];
	$descricao = $_POST['description-text'];

	$allowed = array('image/jpeg', 'image/png', 'application/msword', 'application/pdf');

	if (in_array($arquivos['type'], $allowed) && $arquivos['name'] != '') {
			$pasta_parametro = "arquivos/parametro/$idParametro/";
	
			if (!is_dir($pasta_parametro)) {
				mkdir($pasta_parametro, 0777, true);
			}
	
			$arquivo_temporario = $arquivos['tmp_name'];
			$extensao = pathinfo($arquivos['name'], PATHINFO_EXTENSION); 
			$novo_nome_com_extensao = $novo_nome . '.' . $extensao;
            $caminho_destino = $pasta_parametro . $novo_nome_com_extensao;

			print_r([$idParametro,$novo_nome_com_extensao,$descricao,$caminho_destino,date("Y-m-d H:i:s")]);
	
			if (move_uploaded_file($arquivo_temporario, $caminho_destino)) {
				inserir('documento_parametro', ['para_nb_id','doc_tx_nome','doc_tx_descricao','doc_tx_caminho','doc_tx_dataCadastro'],[$idParametro,$novo_nome_com_extensao,$descricao,$caminho_destino,date("Y-m-d H:i:s")]);
			}
	}

	$_POST['id'] = $idParametro;
	modifica_parametro();
	exit;
}

function excluir_documento() {

	query("DELETE FROM `documento_parametro` WHERE doc_nb_id = $_POST[idArq]");
	
	$_POST['id'] = $_POST['idParametro'];
	modifica_parametro();
	exit;
}

function modifica_parametro(){
	global $a_mod;

	$a_mod=carregar('parametro',$_POST['id']);

	layout_parametro();
	exit;

}

function cadastra_parametro(){
// 	$camposObrigatorios = ['aInserir'];
// 	foreach($camposObrigatorios as $campo){
// 		if(!isset($_POST[$campo]) || empty($_POST[$campo])){
// 			echo '<script>alert("Preencha todos os campos obrigatórios.")</script>';
// 			layout_parametro();
// 			exit;
// 		}
// 	}


	$quandDias = ($_POST['quandDias'] == '') ? 0 : $_POST['quandDias'];
	
	$campos=[
		'para_tx_nome', 'para_tx_jornadaSemanal', 'para_tx_jornadaSabado', 'para_tx_percentualHE', 'para_tx_percentualSabadoHE', 'para_tx_HorasEXExcedente', 
		'para_tx_tolerancia', 'para_tx_acordo', 'para_tx_inicioAcordo', 'para_tx_fimAcordo', 'para_nb_userCadastro', 'para_tx_dataCadastro', 'para_tx_diariasCafe', 
		'para_tx_diariasAlmoco', 'para_tx_diariasJanta', 'para_tx_status', 'para_tx_banco', 'para_tx_setData', 'para_nb_qDias', 'para_tx_paramObs'
	];
	$valores=[
		$_POST['nome'], $_POST['jornadaSemanal'], $_POST['jornadaSabado'], $_POST['percentualHE'], $_POST['percentualSabadoHE'], $_POST['HorasEXExcedente'], 
		$_POST['tolerancia'],$_POST['acordo'], $_POST['inicioAcordo'], $_POST['fimAcordo'], $_SESSION['user_nb_id'],date("Y-m-d"),
		$_POST['diariasCafe'], $_POST['diariasAlmoco'], $_POST['diariasJanta'], 'ativo', $_POST['banco'], $_POST['setCampo'], $quandDias, $_POST['paramObs'],
	];

	if($_POST['id']>0){
		//CARREGA O PARAMETRO ANTES DE ATUALIZAR
		$aParametro = carregar('parametro', $_POST['id']);

		
		$campos = array_merge($campos,['para_nb_userAtualiza','para_tx_dataAtualiza']);
		$valores = array_merge($valores,[$_SESSION['user_nb_id'], date("Y-m-d H:i:s")]);
		atualizar('parametro',$campos,$valores,$_POST['id']);
		
		$sql = query("SELECT * FROM entidade WHERE enti_tx_status != 'inativo'
			AND enti_nb_parametro = '".(int)$_POST['id']."'");
		while($a = carrega_array($sql)){
			//SE O PARAMETRO FOR EXATAMENTE IGUAL AO DO MOTORISTA E ELE ESTIVER NO PARAMETRO ATUALIZA
			if( $aParametro['para_tx_jornadaSemanal'] == $a['enti_tx_jornadaSemanal'] && $aParametro['para_tx_jornadaSabado'] == $a['enti_tx_jornadaSabado'] &&
				$aParametro['para_tx_percentualHE'] == $a['enti_tx_percentualHE'] && $aParametro['para_tx_percentualSabadoHE'] == $a['enti_tx_percentualSabadoHE']){
	
				atualizar('entidade',
					['enti_tx_jornadaSemanal', 'enti_tx_jornadaSabado', 'enti_tx_percentualHE', 'enti_tx_percentualSabadoHE'],
					[$_POST['jornadaSemanal'], $_POST['jornadaSabado'], $_POST['percentualHE'], $_POST['percentualSabadoHE']],
					$a['enti_nb_id']
				);

			}
			
		}
		
		$idParametro = $_POST['id'];

	} else {
		$campos = array_merge($campos,['para_nb_userAtualiza','para_tx_dataAtualiza']);
		$valores = array_merge($valores,[$_SESSION['user_nb_id'], date("Y-m-d H:i:s")]);
		$idParametro =  inserir('parametro',$campos,$valores);
	}

	index();
	exit;
}



function layout_parametro(){
	global $a_mod;

	if(isset($_POST['nome'])){
		$campos = [
			'nome',
			'jornadaSemanal',
			'jornadaSabado',
			'tolerancia',
			'percentualHE',
			'percentualSabadoHE',
			'HorasEXExcedente',
			'diariasCafe',
			'diariasAlmoco',
			'diariasJanta',
			'acordo',
			'inicioAcordo',
			'fimAcordo',
			'banco',
			'paramObs'
		];
		foreach($campos as $campo){
			$a_mod['para_tx_'.$campo] = $_POST[$campo];

		}
		unset($campos);
	}

	cabecalho("Cadastro de Parâmetros");
	
	if(empty($a_mod['id'])){
	  $c[] .= arquivosParametro("Documentos","arquivos",'',3);
	}

	$c = [
		campo('Nome', 'nome', $a_mod['para_tx_nome'], 6),
		campo_hora('Jornada Semanal (Horas/Dia)', 'jornadaSemanal', $a_mod['para_tx_jornadaSemanal'], 3),
		campo_hora('Jornada Sábado (Horas/Dia)', 'jornadaSabado', $a_mod['para_tx_jornadaSabado'], 3),
		campo('Tolerância de jornada Saldo diário (Minutos)', 'tolerancia', $a_mod['para_tx_tolerancia'], 3,'MASCARA_NUMERO','maxlength="3"'),
		campo('Percentual da Hora Extra(%)', 'percentualHE', $a_mod['para_tx_percentualHE'], 3, 'MASCARA_NUMERO'),
		campo('Percentual da Hora Extra 100% (domingos e feriados)', 'percentualSabadoHE', $a_mod['para_tx_percentualSabadoHE'], 3, 'MASCARA_NUMERO'),
		campo_hora('Quando Exceder o limite de Horas Extras %, o excedente será Hora Extra 100% (Horas/Minutos)', 'HorasEXExcedente', $a_mod['para_tx_HorasEXExcedente'], 3),
		campo('Diária Café da Manhã(R$)', 'diariasCafe', $a_mod['para_tx_diariasCafe'], 3, 'MASCARA_DINHERO'),
		campo('Diária Almoço(R$)', 'diariasAlmoco', $a_mod['para_tx_diariasAlmoco'], 3, 'MASCARA_DINHERO'),
		campo('Diária Jantar(R$)', 'diariasJanta', $a_mod['para_tx_diariasJanta'], 3, 'MASCARA_DINHERO'),
		combo('Acordo Sindical', 'acordo', $a_mod['para_tx_acordo'], 3, ['Sim', 'Não']),
		campo_data('Início do Acordo', 'inicioAcordo', $a_mod['para_tx_inicioAcordo'], 3),
		campo_data('Fim do Acordo', 'fimAcordo', $a_mod['para_tx_fimAcordo'], 3),
		ckeditor('Descrição:', 'paramObs', $a_mod['para_tx_paramObs'], 12,'maxlength="100"'),
	];
	
	$botao[] = botao('Gravar','cadastra_parametro','id',$_POST['id']);
	$botao[] = botao('Voltar','index');
	
	abre_form('Dados dos Parâmetros');
	linha_form($c);

	if($a_mod['para_nb_userCadastro'] > 0){
		$a_userCadastro = carregar('user',$a_mod['para_nb_userCadastro']);
		$txtCadastro = "Registro inserido por $a_userCadastro[user_tx_login] às ".data($a_mod['para_tx_dataCadastro'],1).".";
		$cAtualiza[] = texto("Data de Cadastro","$txtCadastro",5);
		if($a_mod['para_nb_userAtualiza'] > 0){
			$a_userAtualiza = carregar('user',$a_mod['para_nb_userAtualiza']);
			$txtAtualiza = "Registro atualizado por $a_userAtualiza[user_tx_login] às ".data($a_mod['para_tx_dataAtualiza'],1).".";
			$cAtualiza[] = texto("Última Atualização","$txtAtualiza",5);
		}
		echo "<br>";
		linha_form($cAtualiza);
	}

	
	fecha_form($botao);
	
	if (!empty($a_mod['para_nb_id'])) {
	    $sqlArquivos= query("SELECT * FROM `documento_parametro` WHERE para_nb_id = $a_mod[para_nb_id]");
	    $arquivos = mysqli_fetch_all($sqlArquivos, MYSQLI_ASSOC);
		echo arquivosParametro("Documentos", $a_mod['para_nb_id'], $arquivos);
	}

	rodape();
	?>
    <form name="form_excluir_arquivo" method="post" action="cadastro_parametro.php">
		<input type="hidden" name="idParametro" value="">
		<input type="hidden" name="idArq" value="">
		<input type="hidden" name="acao" value="">
	</form>

	<form name="form_download_arquivo" method="post" action="cadastro_parametro.php">
		<input type="hidden" name="idParametro" value="">
		<input type="hidden" name="caminho" value="">
		<input type="hidden" name="acao" value="">
	</form>
	
	<script type="text/javascript">
		function remover_arquivo(id, idArq, arquivo, acao ) {
			if (confirm('Deseja realmente excluir o arquivo ' + arquivo + '?')) {
				document.form_excluir_arquivo.idParametro.value = id;
				document.form_excluir_arquivo.idArq.value = idArq;
				document.form_excluir_arquivo.acao.value = acao;
				document.form_excluir_arquivo.submit();
			}
		}

		function downloadArquivo(id, caminho, acao) {
			document.form_download_arquivo.idParametro.value = id;
			document.form_download_arquivo.caminho.value = caminho;
			document.form_download_arquivo.acao.value = acao;
			document.form_download_arquivo.submit();
        }
	 </script>
	<?
}

function index(){

	cabecalho("Cadastro de Parâmetros");

	$extra = '';

	$extra .= ($_POST['busca_codigo']) ? " AND para_nb_id = '$_POST[busca_codigo]'" : '';
	$extra .= ($_POST['busca_nome']) ? " AND para_tx_nome LIKE '%" . $_POST['busca_nome'] . "%'" : '';
	$extra .= ($_POST['busca_acordo'] &&  $_POST['busca_acordo'] != 'Todos') ? " AND para_tx_acordo = '".$_POST['busca_acordo']."'" : '';
	$extra .= ($_POST['busca_banco'] &&  $_POST['busca_banco'] != 'Todos') ? " AND para_tx_banco = '".$_POST['busca_banco']."'" : '';

	$c = [
		campo('Código', 'busca_codigo', $_POST['busca_codigo'], 2, 'MASCARA_NUMERO'),
		campo('Nome', 'busca_nome', $_POST['busca_nome'], 4),
		combo('Acordo', 'busca_acordo', $_POST['busca_acordo'], 2, array('Todos', 'Sim', 'Não')),
		combo('Banco de Horas', 'busca_banco', $_POST['busca_banco'], 2, array('Todos', 'Sim', 'Não')),
		combo('Vencidos', 'busca_vencidos', $_POST['busca_vencidos'], 2, array('Todos', 'Sim', 'Não'))
	];

	$botao = [
		botao('Buscar', 'index'),
		botao('Inserir', 'layout_parametro'),
	];
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);
	
	if ($_POST['busca_vencidos'] === 'Sim') {
		$sql = "SELECT *, DATEDIFF('$diaAtual' ,para_tx_setData) AS diferenca_em_dias
		FROM `parametro` WHERE DATEDIFF('$diaAtual',para_tx_setData) < para_nb_qDias OR DATEDIFF('$diaAtual',para_tx_setData) IS NULL $extra";
	}
	else if($_POST['busca_vencidos'] === 'Não'){
		$sql = "SELECT *, DATEDIFF('$diaAtual' ,para_tx_setData) AS diferenca_em_dias
		FROM `parametro` WHERE DATEDIFF('$diaAtual',para_tx_setData) > para_nb_qDias OR DATEDIFF('$diaAtual',para_tx_setData) IS NULL $extra";
	} else
	    $sql = "SELECT * FROM parametro WHERE para_tx_status != 'inativo' $extra";

	$cab = ['CÓDIGO','NOME','JORNADA SEMANAL/DIA','JORNADA SÁBADO','HE(%)','HE SÁBADO(%)','ACORDO','INÍCIO','FIM','',''];
	$val = ['para_nb_id','para_tx_nome','para_tx_jornadaSemanal','para_tx_jornadaSabado','para_tx_percentualHE','para_tx_percentualSabadoHE','para_tx_acordo','data(para_tx_inicioAcordo)','data(para_tx_fimAcordo)','icone_modificar(para_nb_id,modifica_parametro)','icone_excluir(para_nb_id,exclui_parametro)'];

	grid($sql,$cab,$val);

	rodape();

}