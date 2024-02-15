<?php
	//* Modo debug
		// ini_set('display_errors', 1);
		// error_reporting(E_ALL);
	//*/

	include_once "funcoes_ponto.php"; //Conecta incluso dentro de funcoes_ponto

	function index() {
		global $CONTEX, $totalResumo;
	
		cabecalho('Espelho de Ponto');
		
		$extraBuscaMotorista = '';
		if ($_SESSION['user_tx_nivel'] == 'Motorista') {
			$_POST['busca_motorista'] = $_SESSION['user_nb_entidade'];
			$extraBuscaMotorista = " AND enti_nb_id = '$_SESSION[user_nb_entidade]'";
			$extraCampoData= 'disabled';

			if (isset($_POST['busca_dataInicio']) || !empty($_POST['busca_dataInicio'])){
				$_POST['busca_dataInicio'] = date("Y-m-01");
			}
			if (isset($_POST['busca_dataFim']) || !empty($_POST['busca_dataFim'])){
				$_POST['busca_dataFim'] = date("Y-m-d");
			}
	
		}
	
		if (!empty($_POST['busca_motorista'])) {
			$aMotorista = carregar('entidade', $_POST['busca_motorista']);
			$aEmpresa = carregar('empresa', $aMotorista['enti_nb_empresa']);
		}

		$extraEmpresa = '';
		if ($_SESSION['user_nb_empresa'] > 0 && $_SESSION['user_tx_nivel'] != 'Administrador' && $_SESSION['user_tx_nivel'] != 'Super Administrador') {
			$extraEmpresa = " AND enti_nb_empresa = '$_SESSION[user_nb_empresa]'";
		}
	
		if (!isset($_POST['busca_dataInicio']) || empty($_POST['busca_dataInicio'])){
			$_POST['busca_dataInicio'] = date("Y-m-01");
		}
		if (!isset($_POST['busca_dataFim']) || empty($_POST['busca_dataFim'])){
			$_POST['busca_dataFim'] = date("Y-m-d");
		}

		//Confere se há algum erro na pesquisa{
		$searchError = false;
		if(isset($_POST['acao']) && $_POST['acao'] == 'index'){
			$errorMsg = 'Insira os campos para pesquisar: ';
			if(empty($_POST['busca_empresa'])){
				$searchError = true;
				$errorMsg .= 'Empresa, ';
				$_POST['busca_empresa'] = $_SESSION['user_nb_empresa'];
			}
			if(empty($_POST['busca_motorista'])){
				$searchError = true;
				$errorMsg .= 'Motorista, ';
			}
			if(empty($_POST['busca_dataInicio'])){
				$searchError = true;
				$errorMsg .= 'Data Início, ';
			}
			if(empty($_POST['busca_dataFim'])){
				$searchError = true;
				$errorMsg .= 'Data Fim, ';
			}

			if(!$searchError && !empty($_POST['busca_empresa']) && !empty($_POST['busca_motorista'])){
				$motorista = mysqli_fetch_all(
					query(
						"SELECT enti_nb_id FROM entidade
							WHERE enti_tx_status = 'ativo'
								AND enti_nb_empresa = ".$_POST['busca_empresa']."
								AND enti_nb_id = ".$_POST['busca_motorista']."
							LIMIT 1"
					),
					MYSQLI_ASSOC
				);

				if(empty($motorista)){
					$searchError = true;
					$errorMsg = 'Este motorista não pertence a esta empresa. ';
				}
			}

			if($searchError){
				$errorMsg = substr($errorMsg, 0, -2).'.';
				set_status('ERRO: '.$errorMsg);
			}
		}else{
			$_POST['busca_empresa'] = intval($_SESSION['user_nb_empresa']);
		}
		//}

		//CAMPOS DE CONSULTA

		$c = [
			combo_net('Empresa*:', 'busca_empresa', ($_POST['busca_empresa']?? ''), 3, 'empresa', 'onchange=selecionaMotorista(this.value)', $extraEmpresa),
			combo_net('Motorista*:', 'busca_motorista', ($_POST['busca_motorista']?? ''), 4, 'entidade', '', " AND enti_tx_tipo = \"Motorista\" $extraEmpresa $extraBuscaMotorista", 'enti_tx_matricula'),
			campo_data('Data Início:', 'busca_dataInicio', ($_POST['busca_dataInicio']?? ''), 2,$extraCampoData),
			campo_data('Data Fim:', 'busca_dataFim', ($_POST['busca_dataFim']?? ''), 2,$extraCampoData)
		];
	
		//BOTOES
		$b = [
			botao("Buscar", 'index','','','','','btn btn-success')
		];
		if ($_SESSION['user_tx_nivel'] != 'Motorista') {
			$b[] = botao("Cadastrar Abono", 'layout_abono');
		}
	
		abre_form('Filtro de Busca');
		linha_form($c);
		fecha_form($b);
	
		$cab = [
			"", "DATA", "<div style='margin:10px'>DIA</div>", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA",
			"REFEIÇÃO", "ESPERA", "DESCANSO", "REPOUSO", "JORNADA", "JORNADA PREVISTA", "JORNADA EFETIVA", "MDC", "INTERSTÍCIO", "HE 50%", "HE&nbsp;100%",
			"ADICIONAL NOT.", "ESPERA INDENIZADA", "SALDO DIÁRIO(**)"
		];
	
		// Converte as datas para objetos DateTime
		$startDate = !empty($_POST['busca_dataInicio'])? new DateTime($_POST['busca_dataInicio']): '';
		$endDate   = !empty($_POST['busca_dataFim'])? new DateTime($_POST['busca_dataFim']): '';

		if (!$searchError && !empty($_POST['acao']) && $_POST['acao'] == 'index'){
			$aDia = [];

			// Loop for para percorrer as datas
			for ($date = $startDate; $date <= $endDate; $date->modify('+1 day')) {
				$dataVez = $date->format('Y-m-d');

				$aDetalhado = diaDetalhePonto($aMotorista['enti_tx_matricula'], $dataVez);
				
				$row = array_values(array_merge([verificaTolerancia($aDetalhado['diffSaldo'], $dataVez, $aMotorista['enti_nb_id'])], $aDetalhado));
				for($f = 0; $f < sizeof($row)-1; $f++){
          			if($f == 12){//Se for da coluna "Jornada Prevista", não apaga
						continue;
					}
					if($row[$f] == "00:00"){
						$row[$f] = "";
					}
				}
				$aDia[] = $row;
			}
	
			if (!empty($aEmpresa['empr_nb_parametro'])) {
				$aParametro = carregar('parametro', $aEmpresa['empr_nb_parametro']);
				if (
					$aParametro['para_tx_jornadaSemanal'] != $aMotorista['enti_tx_jornadaSemanal'] ||
					$aParametro['para_tx_jornadaSabado'] != $aMotorista['enti_tx_jornadaSabado'] ||
					$aParametro['para_tx_percentualHE'] != $aMotorista['enti_tx_percentualHE'] ||
					$aParametro['para_tx_percentualSabadoHE'] != $aMotorista['enti_tx_percentualSabadoHE'] ||
					$aParametro['para_nb_id'] != $aMotorista['enti_nb_parametro']
				) {
					$parametroPadrao = 'Convenção Não Padronizada, Semanal ('.$aMotorista['enti_tx_jornadaSemanal'].'), Sábado ('.$aMotorista['enti_tx_jornadaSabado'].')';
				} else {
					$parametroPadrao = 'Convenção Padronizada: '.$aParametro['para_tx_nome'].', Semanal ('.$aParametro['para_tx_jornadaSemanal'].'), Sábado ('.$aParametro['para_tx_jornadaSabado'].')';
				}
			}else{

			}

			$saldoAnterior = mysqli_fetch_assoc(
				query(
					"SELECT endo_tx_saldo FROM `endosso`
						WHERE endo_tx_matricula = '".$aMotorista['enti_tx_matricula']."'
							AND endo_tx_ate < '".$_POST['busca_dataInicio']."'
							AND endo_tx_status = 'ativo'
						ORDER BY endo_tx_ate DESC
						LIMIT 1;"
				)
			);
			if(isset($saldoAnterior['endo_tx_saldo'])){
				$saldoAnterior = $saldoAnterior['endo_tx_saldo'];
			}elseif(!empty($aMotorista['enti_tx_banco'])){
				$saldoAnterior = $aMotorista['enti_tx_banco'];
				$saldoAnterior = $saldoAnterior[0] == '0' && strlen($saldoAnterior) > 5? substr($saldoAnterior, 1): $saldoAnterior;
			}else{
				$saldoAnterior = '--:--';
			}

			$saldoFinal = '--:--';
			if($saldoAnterior != '--:--'){
				$saldoFinal = somarHorarios([$saldoAnterior, $totalResumo['diffSaldo']]);
			}else{
				$saldoFinal = somarHorarios(['00:00', $totalResumo['diffSaldo']]);
			}
			

			$saldosMotorista = 'SALDOS: <br>
				<div class="table-responsive">
					<table class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact" id="saldo">
					  <thead><tr>
						<th>Anterior:</th>
						<th>Período:</th>
						<th>Final:</th>
					  </thead></tr>
					  <tbody>
						<tr>
						  <td>'.$saldoAnterior.'</td>
						  <td>'.$totalResumo['diffSaldo'].'</td>
						  <td>'.$saldoFinal.'</td>
						</tr>
					  </tbody>
					</table>
				  </div>'
			;
				 
			$periodoPesquisa = 'De '.date("d/m/Y", strtotime($_POST['busca_dataInicio'])).' até '.date("d/m/Y", strtotime($_POST['busca_dataFim']));
      
			abre_form(
				"$aEmpresa[empr_tx_nome]<br>"
				."[$aMotorista[enti_tx_matricula]] $aMotorista[enti_tx_nome]<br>"
				."$parametroPadrao<br><br>"
				."$periodoPesquisa<br>"
				."$saldosMotorista"
			);
	?>
	
			<style>
				table thead tr th:nth-child(3),
				table thead tr th:nth-child(7),
				table thead tr th:nth-child(11),
				table td:nth-child(3),
				table td:nth-child(7),
				table td:nth-child(11) {
					border-right: 3px solid #d8e4ef !important;
				}
				.th-align {
				    text-align: center; /* Define o alinhamento horizontal desejado, pode ser center, left ou right */
				    vertical-align: middle !important; /* Define o alinhamento vertical desejado, pode ser top, middle ou bottom */
				    
				}
			</style>
		<?
			$aDia[] = array_values(array_merge(array('', '', '', '', '', '', '<b>TOTAL</b>'), $totalResumo));
			
			grid2($cab, $aDia, "Jornada Semanal (Horas): $aMotorista[enti_tx_jornadaSemanal]");
			fecha_form();
		}
		
		rodape();
	
		?>
		<style>
			#saldo {
				width: 50% !important;
				margin-top: 9px !important;
				text-align: center;
			}
		</style>
	
		<form name="form_ajuste_ponto" method="post">
			<input type="hidden" name="acao" value="layout_ajuste">
			<input type="hidden" name="id" value="<?= $aMotorista['enti_nb_id'] ?>">
			<input type="hidden" name="data">
			<input type="hidden" name="data_de" value="<?=$_POST['busca_dataInicio']?>">
			<input type="hidden" name="data_ate" value="<?=$_POST['busca_dataFim']?>">
		</form>

		<script>
			function selecionaMotorista(idEmpresa) {
				let buscaExtra = '';
				if (idEmpresa > 0) {
					buscaExtra = encodeURI('AND enti_tx_tipo = "Motorista" AND enti_nb_empresa = "' + idEmpresa + '"');
					$('.busca_motorista')[0].innerHTML = null
				} else {
					buscaExtra = encodeURI('AND enti_tx_tipo = "Motorista"');
				}
				// Verifique se o elemento está usando Select2 antes de destruí-lo
				if ($('.busca_motorista').data('select2')) {
					$('.busca_motorista').select2('destroy');
				}

				$.fn.select2.defaults.set("theme", "bootstrap");
				$('.busca_motorista').select2({
					language: 'pt-BR',
					placeholder: 'Selecione um item',
					allowClear: true,
					ajax: {
						url: "/contex20/select2.php?path=" + "<?= $CONTEX['path'] ?>" + "&tabela=entidade&extra_ordem=&extra_limite=15&extra_bd=" + buscaExtra + "&extra_busca=enti_tx_matricula",
						dataType: 'json',
						delay: 250,
						processResults: function(data) {
							return {
								results: data
							};
						},
						cache: true
					}
				});
			}
		</script>
	<?
	}
?>