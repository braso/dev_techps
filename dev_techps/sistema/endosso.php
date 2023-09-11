<?php
	include "funcoes_ponto.php"; //Conecta incluso dentro de funcoes_ponto

	function imprimir_endosso(){
		global $totalResumo;

		if($_POST['busca_data'] && $_POST['busca_empresa']){
			
			$date = new DateTime($_POST['busca_data']);
			$month = $date->format('m');
			$year = $date->format('Y');
			
			$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

			$aEmpresa = carregar('empresa',$_POST['busca_empresa']);
			$aCidadeEmpresa = carregar('cidade',$aEmpresa['empr_nb_cidade']);

			$enderecoEmpresa = implode(", ",array_filter([
				$aEmpresa['empr_tx_endereco'],
				$aEmpresa['empr_tx_numero'],
				$aEmpresa['empr_tx_bairro'],
				$aEmpresa['empr_tx_complemento'],
				$aEmpresa['empr_tx_referencia']])
			);
			
			$sqlMotorista = query("SELECT * FROM entidade WHERE enti_tx_tipo = 'Motorista' AND enti_nb_id IN (".$_POST['idMotoristaEndossado'].") AND enti_nb_empresa = ".$_POST['busca_empresa']." ORDER BY enti_tx_nome");
			while($aMotorista = carrega_array($sqlMotorista)){

				for ($i = 1; $i <= $daysInMonth; $i++) {
					$dataVez = $_POST['busca_data']."-".str_pad($i,2,0,STR_PAD_LEFT);
					
					$aDetalhado = diaDetalhePonto($aMotorista['enti_tx_matricula'], $dataVez);

					$aDia[] = array_values(array_merge([verificaTolerancia($aDetalhado['diffSaldo'], $dataVez, $aMotorista['enti_nb_id'])], [$aMotorista['enti_tx_matricula']], $aDetalhado));
				}

?>
				<!DOCTYPE html>
					<html lang="en">
						<head>
							<meta charset="UTF-8">
							<meta name="viewport" content="width=device-width, initial-scale=1.0">
							<title>Espelho de Ponto</title>
							<link rel="stylesheet" href="css/endosso.css">
						</head>
						<body>
							<div class="header">
								<img src="<?=$aEmpresa['empr_tx_logo']?>" alt="Logo Empresa Esquerda">
								<h1>Espelho de Ponto</h1>
								<div class="right-logo">
									<p><?=date("d/m/Y H:i:s")?></p>
									<img src=<?=getcwd()."/imagens/logo_topo_cliente.png"?> alt="Logo Empresa Direita">
								</div>
							</div>
							<div class="info">
								<div class="employee-info">
									<p>Motorista: <?=$aMotorista['enti_tx_nome']?></p>
									<p>Função: <?=$aMotorista['enti_tx_ocupacao']?></p>
									<p>CPF: <?=$aMotorista['enti_tx_cpf']?></p>
									<p>Matrícula: <?=$aMotorista['enti_tx_matricula']?></p>
									<p>Admissão: <?=data($aMotorista['enti_tx_admissao'])?></p>
								</div>

								<div class="company-info">
									<p>Empresa: <?=$aEmpresa['empr_tx_nome']?></p>
									<p>CNPJ: <?=$aEmpresa['empr_tx_cnpj']?></p>
									<p><?=$enderecoEmpresa?></p>
									<p><?="$aCidadeEmpresa[cida_tx_nome]/$aCidadeEmpresa[cida_tx_uf]"?></p>
									<p>CEP: <?=$aEmpresa['empr_tx_cep']?></p>
								</div>
							</div>
							<table class="table" border="1">
								<thead>
									<tr>
										<th>MAT.</th>
										<th>DATA</th>
										<th>DIA</th>
										<th>INÍCIO JORNADA</th>
										<th>INÍCIO REFEIÇÃO</th>
										<th>FIM REFEIÇÃO</th>
										<th>FIM JORNADA</th>
										<th>REFEIÇÃO</th>
										<th>ESPERA</th>
										<th>DESCANSO</th>
										<th>REPOUSO</th>
										<th>JORNADA</th>
										<th>JORNADA PREVISTA</th>
										<th>JORNADA EFETIVA</th>
										<th>MDC</th>
										<th>INTERSTÍCIO</th>
										<th>HE 50%</th>
										<th>HE&nbsp;100%</th>
										<th>ADICIONAL NOT.</th>
										<th>ESPERA INDENIZADA</th>
										<th>SALDO DIÁRIO</th>
									</tr>
								</thead>
								<tbody>
<?
				// $aDia[] = array_values(array_merge(['','','','','','','','<b>TOTAL</b>'], $totalResumo));
				for ($i=0; $i < count($aDia); $i++) { 
					$j=1;
					$aDiaVez = $aDia[$i];
?>
									<tr>
										<td><?=$aDiaVez[$j++]?></td>
										<td><?=$aDiaVez[$j++]?></td>
										<td><?=$aDiaVez[$j++]?></td>
										<td><?=$aDiaVez[$j++]?></td>
										<td><?=$aDiaVez[$j++]?></td>
										<td><?=$aDiaVez[$j++]?></td>
										<td><?=$aDiaVez[$j++]?></td>
										<td><?=$aDiaVez[$j++]?></td>
										<td><?=$aDiaVez[$j++]?></td>
										<td><?=$aDiaVez[$j++]?></td>
										<td><?=$aDiaVez[$j++]?></td>
										<td><?=$aDiaVez[$j++]?></td>
										<td><?=$aDiaVez[$j++]?></td>
										<td><?=$aDiaVez[$j++]?></td>
										<td><?=$aDiaVez[$j++]?></td>
										<td><?=$aDiaVez[$j++]?></td>
										<td><?=$aDiaVez[$j++]?></td>
										<td><?=$aDiaVez[$j++]?></td>
										<td><?=$aDiaVez[$j++]?></td>
										<td><?=$aDiaVez[$j++]?></td>
										<td><?=$aDiaVez[$j++]?></td>
									</tr>			
<?
				}
?>		
								</tbody>
							</table>
							<div class="signatures">
								<div class="signature-block">
									<table class="table-info">
										<tr>
											<td>Carga horaria Mensal</td>
											<td><center><?=$totalResumo['jornadaPrevista']?></center></td>
										</tr>
										<tr>
											<td>Horas Efetivas realizadas:</td>
											<td><center><?=$totalResumo['diffJornadaEfetiva']?></center></td>
										</tr>
										<tr>
											<td>Adicional Noturno:</td>
											<td><center><?=$totalResumo['adicionalNoturno']?></center></td>
										</tr>
										<tr>
											<td>Horas Extras:</td>
											<td><center><?=$totalResumo['he50']?></center></td>
										</tr>
										<tr>
											<td>Horas Extras (100%):</td>
											<td><center><?=$totalResumo['he100']?></center></td>
										</tr>
										<tr>
											<td>Espera Indenizada:</td>
											<td><center><?=$totalResumo['esperaIndenizada']?></center></td>
										</tr>
										<tr>
											<td>Saldo Período:</td>
											<td><center><?=$totalResumo['diffSaldo']?></center></td>
										</tr>
									</table>
								</div>
								<div class="signature-block" style="align-self: flex-end;">
									<center><p>_________________________________________________________</p></center>
									<center><p>Responsável</p></center>
									<center><p>Cargo:</p></center>
								</div>
								<div class="signature-block" style="align-self: flex-end;">
									<center><p>_________________________________________________________</p></center>
									<center><p><?=$aMotorista['enti_tx_nome']?></p></center>
									<center><p>Motorista</p></center>
								</div>
							</div>
						</body>
					</html>
					<div style="page-break-after: always;"></div>
<?
				$totalResumo = ['diffRefeicao' => '00:00','diffEspera' => '00:00','diffDescanso' => '00:00','diffRepouso' => '00:00','diffJornada' => '00:00','jornadaPrevista' => '00:00','diffJornadaEfetiva' => '00:00','maximoDirecaoContinua' => '','intersticio' => '00:00','he50' => '00:00','he100' => '00:00','adicionalNoturno' => '00:00','esperaIndenizada' => '00:00','diffSaldo' => '00:00'];
				unset($aDia);

			}
		}
		
		exit;
	}


	function layout_endosso() {

		$aID = explode(',', $_POST['idMotorista']);
		$aMatricula = explode(',', $_POST['matriculaMotorista']);

		
		for ($i=0; $i < count($aID); $i++) { 
			$sqlCheck = query("SELECT endo_nb_id FROM endosso WHERE endo_tx_mes = '".$_POST['busca_data'].'-01'."' 
				AND endo_nb_entidade = '".$aID[$i]."' AND endo_tx_matricula = '".$aMatricula[$i]."' AND endo_tx_status = 'ativo'");
			if(num_linhas($sqlCheck) == 0){
			
				$campos = ['endo_nb_entidade', 'endo_tx_matricula', 'endo_tx_mes', 'endo_tx_dataCadastro', 'endo_nb_userCadastro', 'endo_tx_status'];
				$valores = [$aID[$i], $aMatricula[$i], $_POST['busca_data'].'-01', date("Y-m-d H:i:s"), $_SESSION['user_nb_id'], 'ativo'];
				
				inserir('endosso',$campos,$valores);
			}
		}

		index();
		exit;
	}


	function index() {
		global $totalResumo;

		cabecalho('Endosso'.(is_int(strpos($_SERVER["REQUEST_URI"], 'dev_'))? ' (Dev)': ''));

		if($_POST['busca_motorista']){
			$extra = " AND enti_nb_id = ".$_POST['busca_motorista'];
		}

		if($_POST['busca_data'] && $_POST['busca_empresa']){
			$carregando = "Carregando...";
		}

		if($_POST['busca_data'] == ''){
			$_POST['busca_data'] = date("Y-m");
		}
		

		$countEndosso = $countNaoConformidade = $countVerificados = $countEndossados = $countNaoEndossados = 0;

		//CONSULTA
		$c[] = combo_net('Empresa:','busca_empresa',$_POST['busca_empresa'],4,'empresa');
		$c[] = campo_mes('Data:','busca_data',$_POST['busca_data'],2);
		$c[] = combo_net('Motorista:','busca_motorista',$_POST['busca_motorista'],4,'entidade','',' AND enti_tx_tipo = "Motorista"','enti_tx_matricula');
		$c[] = combo('Situação:','busca_situacao',$_POST['busca_situacao'],2,['Todos', 'Verificado', 'Não conformidade', 'Endossado', 'Endossado parcialmente']);
		
		//BOTOES
		$b[] = botao("Buscar",'index');
		$b[] = botao("Cadastrar Abono",'layout_abono');
		// $b[] = botao("Cadastrar Endosso",'cadastra_endosso');
		if($_POST['busca_situacao'] != 'Verificado'){
			$disabled = 'disabled=disabled title="Filtre apenas por Verificado para efetuar o endosso."';
			$disabled2 = 'disabled=disabled title="Filtre apenas por Verificado para efetuar a impressão endosso."';
		}
		$b[] = '<button name="acao" id="botaoContexCadastrar Endosso" value="layout_endosso" '.$disabled.' type="button" class="btn default">Cadastrar Endosso</button>';
		$b[] = '<button name="acao" id="botaoContexCadastrar ImprimirEndosso" value="impressao_endosso" '.$disabled2.' type="button" class="btn default">Imprimir Endossados</button>';
		$b[] = '<span id=dadosResumo><b>'.$carregando.'</b></span>';
		
		
		abre_form('Filtro de Busca');
		linha_form($c);
		fecha_form($b);
		
		// $cab = ["MATRÍCULA", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA", "REFEIÇÃO", "ESPERA", "ATRASO", "EFETIVA", "PERÍODO TOTAL", "INTERSTÍCIO DIÁRIO", "INT. SEMANAL", "ABONOS", "FALTAS", "FOLGAS", "H.E.", "H.E. 100%", "ADICIONAL NOTURNO", "ESPERA INDENIZADA", "OBSERVAÇÕES"];
		$cab = ["", "MAT.", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA",
		"REFEIÇÃO", "ESPERA", "DESCANSO", "REPOUSO", "JORNADA", "JORNADA PREVISTA", "JORNADA EFETIVA","MDC","INTERSTÍCIO","HE 50%", "HE&nbsp;100%",
		"ADICIONAL NOT.", "ESPERA INDENIZADA", "SALDO DIÁRIO"];

		if($_POST['busca_data'] && $_POST['busca_empresa']){
			
			$date = new DateTime($_POST['busca_data']);
			$month = $date->format('m');
			$year = $date->format('Y');
			
			$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
			
			$sqlMotorista = query("SELECT * FROM entidade WHERE enti_tx_tipo = 'Motorista' AND enti_nb_empresa = ".$_POST['busca_empresa']." $extra ORDER BY enti_tx_nome");
			while($aMotorista = carrega_array($sqlMotorista)){

				for ($i = 1; $i <= $daysInMonth; $i++) {
					$dataVez = $_POST['busca_data']."-".str_pad($i,2,0,STR_PAD_LEFT);
					
					$aDetalhado = diaDetalhePonto($aMotorista['enti_tx_matricula'], $dataVez, (isset($_POST['busca_situacao'])? $_POST['busca_situacao']: ''));

					$aDia[] = array_values(array_merge([verificaTolerancia($aDetalhado['diffSaldo'], $dataVez, $aMotorista['enti_nb_id'])], [$aMotorista['enti_tx_matricula']], $aDetalhado));
					$aDiaOriginal[] = $aDetalhado;
				}

				// if($aMotorista['enti_tx_matricula']=='6952'){
				// 	echo "<pre>";
				// 	print_r($aDiaOriginal);
				// 	echo "</pre>";
				// }

				$exibir = 1;
				
				for ($i=0; $i < count($aDiaOriginal); $i++) { 
					$diaVez = $aDiaOriginal[$i];

					$temRed = false;
					foreach(['diffRefeicao', 'diffEspera', 'diffDescanso', 'diffRepouso', 'diffJornada', 'jornadaPrevista', 'diffJornadaEfetiva', 'maximoDirecaoContinua', 'intersticio', 
						'he50', 'he100', 'adicionalNoturno', 'esperaIndenizada', 'diffSaldo'] as $campo){
						if(is_int(strpos($diaVez[$campo], 'color:red;'))){
							$temRed = true;
							break;
						}
					}
					if($temRed){
						//SE HOUVER RED E BUSCA POR NAO CONFORMIDADE EXIBE. LOGICA CONTRARIA CASO VERIFICADOS
						if($_POST['busca_situacao'] == 'Não conformidade' || $_POST['busca_situacao'] == 'Todos'){
							$countNaoConformidade++;
							$exibir = 1;
							break;
						}elseif($_POST['busca_situacao'] == 'Verificado' ){
							$exibir = 0;
							$totalResumo = [
								'diffRefeicao' => '00:00','diffEspera' => '00:00','diffDescanso' => '00:00','diffRepouso' => '00:00','diffJornada' => '00:00',
								'jornadaPrevista' => '00:00','diffJornadaEfetiva' => '00:00','maximoDirecaoContinua' => '','intersticio' => '00:00','he50' => '00:00',
								'he100' => '00:00','adicionalNoturno' => '00:00','esperaIndenizada' => '00:00','diffSaldo' => '00:00'
							];
							break;
						}
					}else{
						if($_POST['busca_situacao'] == 'Não conformidade'){
							$exibir = 0;
						}elseif($_POST['busca_situacao'] == 'Verificado' ){
							$exibir = 1;
						}
					}
					

				}

				if($exibir == 0){
					unset($aDia);
					unset($aDiaOriginal);
				}

				if(count($aDia) > 0){

					$sqlCheck = query(
						"SELECT user_tx_login, endo_tx_dataCadastro 
							FROM endosso, user 
							WHERE endo_tx_mes = '".substr($_POST['busca_data'], 0,7).'-01'.
								"' AND endo_nb_entidade = '".$aMotorista['enti_nb_id'].
								"' AND = '".$aMotorista['enti_tx_matricula'].
								"' AND endo_tx_status = 'ativo'".
								"' AND endo_nb_userCadastro = user_nb_id".
							"LIMIT 1"
					);
					$aEndosso = carrega_array($sqlCheck);
					if(count($aEndosso) > 0){
						$infoEndosso = " - Endossado por ".$aEndosso['user_tx_login']." em ".data($aEndosso['endo_tx_dataCadastro'],1);
						$countEndossados++;
						$aIdMotoristaEndossado[] = $aMotorista['enti_nb_id'];
						$aMatriculaMotoristaEndossado[] = $aMotorista['enti_tx_matricula'];
					}else{
						$infoEndosso = '';	
						$countNaoEndossados++;
					}

					$aIdMotorista[] = $aMotorista['enti_nb_id'];
					$aMatriculaMotorista[] = $aMotorista['enti_tx_matricula'];

					$countEndosso++;

					if($aMotorista['enti_nb_parametro'] > 0 ){
						$aParametro = carregar('parametro', $aMotorista['enti_nb_parametro']);
						if( $aParametro['para_tx_jornadaSemanal'] != $aMotorista['enti_tx_jornadaSemanal'] ||
							$aParametro['para_tx_jornadaSabado'] != $aMotorista['enti_tx_jornadaSabado'] ||
							$aParametro['para_tx_percentualHE'] != $aMotorista['enti_tx_percentualHE'] ||
							$aParametro['para_tx_percentualSabadoHE'] != $aMotorista['enti_tx_percentualSabadoHE']){
				
							$ehPadrão = 'Não';
						}else{
							$ehPadrão = 'Sim';
						}
						
						$convencaoPadrao = '| Convenção Padrão? ' . $ehPadrão;
						
					}

					abre_form("[$aMotorista[enti_tx_matricula]] $aMotorista[enti_tx_nome] $infoEndosso $convencaoPadrao");
				
					$aDia[] = array_values(array_merge(['','','','','','','','<b>TOTAL</b>'], $totalResumo));
					
					grid2($cab,$aDia,"Jornada Semanal (Horas): $aMotorista[enti_tx_jornadaSemanal]");
					fecha_form();

				}
				
				$totalResumo = ['diffRefeicao' => '00:00','diffEspera' => '00:00','diffDescanso' => '00:00','diffRepouso' => '00:00','diffJornada' => '00:00','jornadaPrevista' => '00:00','diffJornadaEfetiva' => '00:00','maximoDirecaoContinua' => '','intersticio' => '00:00','he50' => '00:00','he100' => '00:00','adicionalNoturno' => '00:00','esperaIndenizada' => '00:00','diffSaldo' => '00:00'];

				unset($aDia);
				unset($aDiaOriginal);

			}

		}

		if($_POST['busca_situacao'] == 'Todos' || $_POST['busca_situacao'] == 'Verificado'){
			$countVerificados = $countEndosso - $countNaoConformidade;
		}

		?>
		<div class="printable">

		</div>
		<?

		rodape();

		?>

		<style>
			table thead tr th:nth-child(4),
			table thead tr th:nth-child(8),
			table thead tr th:nth-child(12),
			table td:nth-child(4),
			table td:nth-child(8),
			table td:nth-child(12) {
				border-right: 3px solid #d8e4ef !important;
			}




		</style>

		<form name="form_layout_endosso" method="post">
			<input type="hidden" name="acao" value="layout_endosso">
			<input type="hidden" name="idMotorista" value="<?=implode(",", $aIdMotorista)?>">
			<input type="hidden" name="matriculaMotorista" value="<?=implode(",", $aMatriculaMotorista)?>">
			<input type="hidden" name="busca_empresa" value="<?=$_POST['busca_empresa']?>">
			<input type="hidden" name="busca_data" value="<?=$_POST['busca_data']?>">
			<input type="hidden" name="busca_motorista" value="<?=$_POST['busca_motorista']?>">
			<input type="hidden" name="busca_situacao" value="<?=$_POST['busca_situacao']?>">
		</form>

		<form name="form_imprimir_endosso" method="post" target="_blank" >
			<input type="hidden" name="acao" value="imprimir_endosso">
			<input type="hidden" name="idMotoristaEndossado" value="<?=implode(",", $aIdMotoristaEndossado)?>">
			<input type="hidden" name="matriculaMotoristaEndossado" value="<?=implode(",", $aMatriculaMotoristaEndossado)?>">
			<input type="hidden" name="busca_empresa" value="<?=$_POST['busca_empresa']?>">
			<input type="hidden" name="busca_data" value="<?=$_POST['busca_data']?>">
			<input type="hidden" name="busca_motorista" value="<?=$_POST['busca_motorista']?>">
			<input type="hidden" name="busca_situacao" value="<?=$_POST['busca_situacao']?>">
		</form>

		<form name="form_ajuste_ponto" method="post" target="_blank">
			<input type="hidden" name="acao" value="layout_ajuste">
			<input type="hidden" name="id" value="<?=$aMotorista['enti_nb_id']?>">
			<input type="hidden" name="data">
		</form>

		<script>
			function ajusta_ponto(data, motorista){
				document.form_ajuste_ponto.data.value = data;
				document.form_ajuste_ponto.id.value = motorista;
				document.form_ajuste_ponto.submit();
			}
			
			window.onload = function() {
				document.getElementById('dadosResumo').innerHTML = '<b>Total: <?=$countEndosso?> | Verificados: <?=$countVerificados?> | Não Conformidade: <?=$countNaoConformidade?> | Endossados: <?=$countEndossados?> | Não Endossados: <?=$countNaoEndossados?></b>';

				document.getElementById('botaoContexCadastrar Endosso').onclick = function(){
					if(confirm('Deseja confirma o endosso de <?=$countEndosso?> motorista(s)?\nVerificados: <?=$countVerificados?> motorista(s).\nNão Conformidade: <?=$countNaoConformidade?> motorista(s).')){
						if(<?=count($aIdMotorista)?>){
							document.form_layout_endosso.submit()
						}else{
							alert('Não há motoristas para endossar!')
						}
					}
				}

				document.getElementById('botaoContexCadastrar ImprimirEndosso').onclick = function(){
					document.form_imprimir_endosso.submit()
				}
				
			};
			

		</script>
		<?

	}
?>