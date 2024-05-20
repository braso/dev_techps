<?php
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/
		
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
		global $a_mod;

		if(empty($a_mod)){
			$a_mod = carregar('parametro', $_POST['id']);
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
			$a_mod['para_nb_qDias'] = $_POST['para_nb_Qdias'];
			$a_mod['para_tx_horasLimite'] = $_POST['para_tx_horasLimite'];
			unset($campos);
		}

		$novoParametro = [
			'para_nb_id' => $_POST['idParametro'],
			'doc_tx_nome' => $_POST['file-name'],
			'doc_tx_descricao' => $_POST['description-text'],
			//doc_tx_caminho está mais abaixo
			'doc_tx_dataCadastro' => date("Y-m-d H:i:s")
		];
		
		$arquivo =  $_FILES['file'];
		$formatosImg = ['image/jpeg', 'image/png', 'application/msword', 'application/pdf'];

		if (in_array($arquivo['type'], $formatosImg) && $arquivo['name'] != '') {
				$pasta_parametro = "arquivos/parametro/".$novoParametro['para_nb_id']."/";
		
				if (!is_dir($pasta_parametro)) {
					mkdir($pasta_parametro, 0777, true);
				}
		
				$arquivo_temporario = $arquivo['tmp_name'];
				$extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
				$novoParametro['doc_tx_nome'] .= '.'.$extensao;
				$novoParametro['doc_tx_caminho'] = $pasta_parametro.$novoParametro['doc_tx_nome'];
		
				if (move_uploaded_file($arquivo_temporario, $novoParametro['doc_tx_caminho'])) {
					inserir('documento_parametro', array_keys($novoParametro), array_values($novoParametro));
				}
		}

		$_POST['id'] = $novoParametro['para_nb_id'];
		modifica_parametro();
		exit;
	}

	function excluir_documento() {
		query("DELETE FROM `documento_parametro` WHERE doc_nb_id = ".$_POST['idArq'].";");
		
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

		if(is_int(strpos(implode(",",array_keys($_POST)), "ignorarCampos"))){
			$campos = ['descanso', 'espera', 'repouso', 'repousoEmbarcado'];
			$_POST['ignorarCampos'] = [];
			foreach($campos as $campo){
				if(isset($_POST['ignorarCampos_'.$campo])){
					$_POST['ignorarCampos'][] = $campo;
				}
			}
			$_POST['ignorarCampos'] = implode(",",$_POST['ignorarCampos']);
		}else{
			$_POST['ignorarCampos'] = null;
		}

		$camposObrigatorios = ['nome' => 'Nome', 'jornadaSemanal' => 'Jornada Semanal (Horas/Dia)', 'jornadaSabado' => 'Jornada Sábado (Horas/Dia)',
		'tolerancia' => 'Tolerância de jornada Saldo diário (Minutos)', 'percentualHE' => 'Percentual da Hora Extra (Semanal)', 
		'percentualSabadoHE' => 'Percentual da Hora Extra (Dias sem Jornada Prevista)', 'HorasEXExcedente' => 'Máximo de Horas Extras 50% (diário)'];

		// Removido temporariamente
		// if(!empty($_POST['acordo']) && $_POST['acordo'] == 'sim'){
		// 	$camposObrigatorios[] = 'inicioAcordo';
		// 	$camposObrigatorios[] = 'fimAcordo';
		// }
		
		if(!empty($_POST['banco']) && $_POST['banco'] == 'sim'){
			$camposObrigatorios['quandDias'] = 'Quantidade de Dias';
			$camposObrigatorios['quandHoras'] = 'Quantidade de Horas Limite';
		}
		
		$error = false;
		$emptyFields = '';
		foreach(array_keys($camposObrigatorios) as $campo){
		    if(!isset($_POST[$campo]) || empty($_POST[$campo])){
		        $error = true;
				$emptyFields .= $camposObrigatorios[$campo].', ';
			}
		}
		
		$emptyFields = substr($emptyFields, 0, strlen($emptyFields)-2);

		if($error){
		    echo '<script>alert("Informações obrigatórias faltando: '.$emptyFields.'.")</script>';
			layout_parametro();
			exit;
		}

		unset($campos_obrigatorios);
		
		$novoParametro = [
			'para_tx_nome' 					=> $_POST['nome'], 
			'para_tx_jornadaSemanal' 		=> $_POST['jornadaSemanal'], 
			'para_tx_jornadaSabado' 		=> $_POST['jornadaSabado'], 
			'para_tx_percentualHE' 			=> $_POST['percentualHE'], 
			'para_tx_percentualSabadoHE' 	=> $_POST['percentualSabadoHE'], 
			'para_tx_HorasEXExcedente' 		=> $_POST['HorasEXExcedente'], 
			'para_tx_tolerancia' 			=> $_POST['tolerancia'], 
			//ignorarCampos está mais abaixo
			'para_tx_acordo' 				=> $_POST['acordo'], 
			'para_tx_inicioAcordo' 			=> $_POST['inicioAcordo'], 
			'para_tx_fimAcordo' 			=> $_POST['fimAcordo'], 
			'para_nb_userCadastro' 			=> intval($_SESSION['user_nb_id']),
			'para_tx_dataCadastro' 			=> date("Y-m-d"), 
			'para_tx_diariasCafe' 			=> $_POST['diariasCafe'], 
			'para_tx_diariasAlmoco' 		=> $_POST['diariasAlmoco'], 
			'para_tx_diariasJanta' 			=> $_POST['diariasJanta'], 
			'para_tx_status' 				=> 'ativo', 
			'para_tx_banco' 				=> $_POST['banco'], 
			'para_tx_setData' 				=> $_POST['setCampo'], 
			'para_nb_qDias' 				=> $_POST['quandDias'],
			'para_tx_horasLimite' 			=> $_POST['quandHoras'],
			'para_tx_paramObs' 				=> $_POST['paramObs'],
		];

		if(!empty($_POST['ignorarCampos']) || $_POST['ignorarCampos'] == null){
			$novoParametro['para_tx_ignorarCampos'] = $_POST['ignorarCampos'];
		}

		if(!empty($_POST['banco']) && $_POST['banco'] == 'nao'){
			unset($novoParametro['para_nb_qDias']);
			unset($novoParametro['para_tx_horasLimite']);
		}

		if(!empty($_POST['acordo']) && $_POST['acordo'] == 'nao'){
			unset($novoParametro['para_tx_inicioAcordo']);
			unset($novoParametro['para_tx_fimAcordo']);
		}
		

		$novoParametro['para_nb_userAtualiza'] = $_SESSION['user_nb_id'];
		$novoParametro['para_tx_dataAtualiza'] = date("Y-m-d H:i:s");
		
		if(!empty($_POST['id'])){ //Se está editando

			$aParametro = carregar('parametro', $_POST['id']);

			$motoristasNoPadrao = mysqli_fetch_all(
				query(
					"SELECT * FROM entidade 
						WHERE enti_tx_status != 'inativo'
							AND enti_nb_parametro = '".(int)$_POST['id']."'"
				),
				MYSQLI_ASSOC
			);

			atualizar('parametro',array_keys($novoParametro),array_values($novoParametro),$_POST['id']);
			
			foreach($motoristasNoPadrao as $motorista){
				// Se o motorista estava dentro do padrão do parâmetro antes da atualização, atualiza os parâmetros do motorista junto.
				if($aParametro['para_nb_id'] == $motorista['enti_nb_parametro'] && $motorista['enti_tx_ehPadrao'] == 'sim'){
					atualizar(
						'entidade',
						['enti_tx_jornadaSemanal', 'enti_tx_jornadaSabado', 'enti_tx_percentualHE', 'enti_tx_percentualSabadoHE'],
						[$_POST['jornadaSemanal'], $_POST['jornadaSabado'], $_POST['percentualHE'], $_POST['percentualSabadoHE']],
						$motorista['enti_nb_id']
					);
				}
			}
		} else {
			inserir('parametro',array_keys($novoParametro),array_values($novoParametro));
		}

		layout_parametro();
		exit;
	}

	function layout_parametro(){
		global $a_mod;

		if(empty($a_mod) && !empty($_POST['id'])){
			$a_mod = carregar('parametro', $_POST['id']);
			$campos = [
				'nome', 'jornadaSemanal', 'jornadaSabado', 'tolerancia', 'percentualHE',
				'percentualSabadoHE', 'HorasEXExcedente', 'diariasCafe', 'diariasAlmoco', 'diariasJanta',
				'acordo', 'inicioAcordo', 'fimAcordo', 'banco', 'paramObs'
			];
			foreach($campos as $campo){
				if(!empty($_POST[$campo])){
					$a_mod['para_tx_'.$campo] = $_POST[$campo];
				}
			}
			if(!empty($a_mod['para_tx_ignorarCampos'])){
				foreach(explode(',', $a_mod['para_tx_ignorarCampos']) as $campo){
					$_POST['ignorarCampos_'.$campo] = true;
				}
			}
			if(!empty($_POST['quandDias'])){
				$a_mod['para_nb_qDias'] = $_POST['quandDias'];
			}
			if(!empty($_POST['quandHoras'])){
				$a_mod['para_tx_horasLimite'] = $_POST['quandHoras'];
			}
			unset($campos);
		}

		cabecalho("Cadastro de Parâmetros");
		
		$c = [
			campo('Nome*:', 'nome', ($a_mod['para_tx_nome']?? ''), 6),
			campo_hora('Jornada Semanal (Horas/Dia)*:', 'jornadaSemanal', ($a_mod['para_tx_jornadaSemanal']?? ''), 3),
			campo_hora('Jornada Sábado (Horas/Dia)*:', 'jornadaSabado', ($a_mod['para_tx_jornadaSabado']?? ''), 3),
			campo('Tolerância de jornada Saldo diário (Minutos)*:', 'tolerancia', ($a_mod['para_tx_tolerancia']?? ''), 3,'MASCARA_NUMERO','maxlength="3"'),
			campo('Percentual da Hora Extra (Semanal)*:', 'percentualHE', ($a_mod['para_tx_percentualHE']?? ''), 3, 'MASCARA_NUMERO', 'maxlength="3"'),
			campo('Percentual da Hora Extra (Dias sem Jornada Prevista)*:', 'percentualSabadoHE', ($a_mod['para_tx_percentualSabadoHE']?? ''), 3, 'MASCARA_NUMERO'),
			campo_hora('Máximo de Horas Extras 50% (diário)*', 'HorasEXExcedente', ($a_mod['para_tx_HorasEXExcedente']?? ''), 3),
			campo('Diária Café da Manhã(R$)', 'diariasCafe', ($a_mod['para_tx_diariasCafe']?? ''), 3, 'MASCARA_DINHERO'),
			campo('Diária Almoço(R$)', 'diariasAlmoco', ($a_mod['para_tx_diariasAlmoco']?? ''), 3, 'MASCARA_DINHERO'),
			campo('Diária Jantar(R$)', 'diariasJanta', ($a_mod['para_tx_diariasJanta']?? ''), 3, 'MASCARA_DINHERO'),
			combo('Acordo Sindical', 'acordo', ($a_mod['para_tx_acordo']?? ''), 3, ['sim' => "Sim", 'nao' => "Não"]),
			campo_data('Início do Acordo*', 'inicioAcordo', ($a_mod['para_tx_inicioAcordo']?? ''), 3),
			campo_data('Fim do Acordo*', 'fimAcordo', ($a_mod['para_tx_fimAcordo']?? ''), 3),
			checkbox_banco('Utilizar regime de banco de horas?','banco',($a_mod['para_tx_banco']?? ''),($a_mod['para_nb_qDias']?? ''), ($a_mod['para_tx_horasLimite']?? ''),3),
			ckeditor('Descrição:', 'paramObs', ($a_mod['para_tx_paramObs']?? ''), 12,'maxlength="100"'),
		];

		if (!empty($a_mod['para_nb_id'])) {
			$sqlArquivos= query("SELECT * FROM `documento_parametro` WHERE para_nb_id = $a_mod[para_nb_id]");
			$arquivos = mysqli_fetch_all($sqlArquivos, MYSQLI_ASSOC);
		}

		$camposAIgnorar = [
			checkbox(
				'Ignorar intervalos:',
				'ignorarCampos', (
					[
						'repouso' => 'Repouso', 
						'descanso' => 'Descanso',
						'espera' => 'Espera',
						'repousoEmbarcado' => 'Repouso Embarcado',
					]
				), 
				5,
				'',
				$a_mod['para_tx_ignorarCampos'] ?? ''
			)
		];
		
		$botao = [
			botao('Gravar','cadastra_parametro','id',($_POST['id']?? ''),'','','btn btn-success'),
			botao('Voltar','index')
		];
		
		abre_form('Dados dos Parâmetros');
		linha_form($c);
		linha_form($camposAIgnorar);

		if(!empty($a_mod['para_nb_userCadastro'])){
			$a_userCadastro = carregar('user', $a_mod['para_nb_userCadastro']);
			if(!empty($a_userCadastro)){
				$cAtualiza = [
					texto(
						"Data de Cadastro",
						"Registro inserido por ".$a_userCadastro['user_tx_login']." às ".data($a_mod['para_tx_dataCadastro'],1).".",
						5
					)
				];
				
				if($a_mod['para_nb_userAtualiza'] > 0){
					$a_userAtualiza = carregar('user',$a_mod['para_nb_userAtualiza']);
					$cAtualiza[] = texto(
						"Última Atualização",
						"Registro atualizado por ".$a_userAtualiza['user_tx_login']." às ".data($a_mod['para_tx_dataAtualiza'],1).".",
						5
					);
				}
				echo "<br>";
				linha_form($cAtualiza);
			}
		}

		fecha_form($botao);

		if (!empty($a_mod['para_nb_id'])) {
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
				function remover_arquivo(id, idArq, arquivo, acao ){
					if (confirm('Deseja realmente excluir o arquivo '+arquivo+'?')){
						document.form_excluir_arquivo.idParametro.value = id;
						document.form_excluir_arquivo.idArq.value = idArq;
						document.form_excluir_arquivo.acao.value = acao;
						document.form_excluir_arquivo.submit();
					}
				}

				function downloadArquivo(id, caminho, acao){
					document.form_download_arquivo.idParametro.value = id;
					document.form_download_arquivo.caminho.value = caminho;
					document.form_download_arquivo.acao.value = acao;
					document.form_download_arquivo.submit();
				}
			</script>
		<?php
	}

	function index(){

		cabecalho("Cadastro de Parâmetros");

		$extra = '';

		$extra .= (!empty($_POST['busca_codigo'])) ? " AND para_nb_id LIKE '%".$_POST['busca_codigo']."%'" : '';
		$extra .= (!empty($_POST['busca_nome'])) ? " AND para_tx_nome LIKE '%" . $_POST['busca_nome'] . "%'" : '';
		$extra .= (!empty($_POST['busca_acordo']) &&  $_POST['busca_acordo'] != 'Todos') ? " AND para_tx_acordo = '".$_POST['busca_acordo']."'" : '';
		$extra .= (!empty($_POST['busca_banco']) &&  $_POST['busca_banco'] != 'Todos') ? " AND para_tx_banco = '".$_POST['busca_banco']."'" : '';

		$c = [
			campo('Código', 'busca_codigo', $_POST['busca_codigo']?? '', 2, 'MASCARA_NUMERO', 'maxlength="6"'),
			campo('Nome', 'busca_nome', $_POST['busca_nome']?? '', 4, '', 'maxlength="65"'),
      		combo('Acordo', 'busca_acordo', $_POST['busca_acordo']?? '', 2, ['' => 'Todos', 'sim' => 'Sim', 'nao' => 'Não']),
			combo('Banco de Horas', 'busca_banco', $_POST['busca_banco']?? '', 2, ['' => 'Todos', 'sim' => 'Sim', 'nao' => 'Não']),
			combo('Vencidos', 'busca_vencidos', $_POST['busca_vencidos']?? '', 2, ['' => 'Todos', 'sim' => 'Sim', 'nao' => 'Não'])
		];

		$botao = [
			botao('Buscar', 'index'),
			botao('Inserir', 'layout_parametro','','','','','btn btn-success'),
		];
		
		abre_form('Filtro de Busca');
		linha_form($c);
		fecha_form($botao);
		if (isset($_POST['busca_vencidos']) && $_POST['busca_vencidos'] === 'sim') {
			$sql = "SELECT *, DATEDIFF('".date('Y-m-d')."' ,para_tx_setData) AS diferenca_em_dias
			FROM `parametro` WHERE DATEDIFF('".date('Y-m-d')."',para_tx_setData) < para_nb_qDias OR DATEDIFF('".date('Y-m-d')."',para_tx_setData) IS NULL $extra";
		}
		else if(isset($_POST['busca_vencidos']) && $_POST['busca_vencidos'] === 'nao'){
			$sql = "SELECT *, DATEDIFF('".date('Y-m-d')."' ,para_tx_setData) AS diferenca_em_dias
			FROM `parametro` WHERE DATEDIFF('".date('Y-m-d')."',para_tx_setData) > para_nb_qDias OR DATEDIFF('".date('Y-m-d')."',para_tx_setData) IS NULL $extra";
		} else{
			$sql = "SELECT * FROM parametro WHERE para_tx_status != 'inativo' $extra";
		}

		$cab = ['CÓDIGO','NOME','JORNADA SEMANAL/DIA','JORNADA SÁBADO','HE(%)','HE SÁBADO(%)','ACORDO','INÍCIO','FIM','',''];
		$val = ['para_nb_id','para_tx_nome','para_tx_jornadaSemanal','para_tx_jornadaSabado','para_tx_percentualHE','para_tx_percentualSabadoHE','para_tx_acordo','data(para_tx_inicioAcordo)','data(para_tx_fimAcordo)','icone_modificar(para_nb_id,modifica_parametro)','icone_excluir(para_nb_id,exclui_parametro)'];

		grid($sql,$cab,$val);

		rodape();

	}
?>