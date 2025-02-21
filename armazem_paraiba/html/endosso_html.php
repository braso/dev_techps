<style>
	.th-align {
		text-align: center; /* Define o alinhamento horizontal desejado, pode ser center, left ou right */
		vertical-align: middle !important; /* Define o alinhamento vertical desejado, pode ser top, middle ou bottom */
	}
	
	#saldo {
		margin-top: 9px !important;
		text-align: center;
	}
</style>

<form name="form_imprimir_relatorio" method="post" target="_blank">
	<input type="hidden" name="acao" value="imprimir_relatorio">
	<input type="hidden" name="idMotoristaEndossado">
	<input type="hidden" name="matriculaMotoristaEndossado">
	<input type="hidden" name="busca_empresa">
	<input type="hidden" name="busca_data">
	<input type="hidden" name="busca_motorista">
	<input type="hidden" name="busca_situacao">
</form>

<script>
	var form = document.form_imprimir_relatorio;
	<?=
		"form.idMotoristaEndossado.value 		= '".(!empty($aIdMotoristaEndossado)? implode(",", $aIdMotoristaEndossado) : "")."';
		form.matriculaMotoristaEndossado.value 	= '".(!empty($aMatriculaMotoristaEndossado)? implode(",", $aMatriculaMotoristaEndossado): '')."';
		form.busca_empresa.value 				= '".(!empty($_POST["busca_empresa"])? $_POST["busca_empresa"]: "")."';
		form.busca_data.value 					= '".(!empty($_POST["busca_data"])? $_POST["busca_data"]: "")."';
		form.busca_motorista.value 				= '".(!empty($_POST["busca_motorista"])? $_POST["busca_motorista"]: "")."';
		form.busca_situacao.value 				= '".(!empty($_POST["busca_situacao"])? $_POST["busca_situacao"]: "")."';
		form.id.value 							= '".(!empty($aMotorista["enti_nb_id"])? $aMotorista["enti_nb_id"]: "")."';
		let select2URL = '{$select2URL}'"
	?>

	function ajustarPonto(idMotorista, data) {
		document.form_ajuste_ponto.idMotorista.value = idMotorista;
		document.form_ajuste_ponto.data.value = data;
		document.form_ajuste_ponto.submit();
	}

	function selecionaMotorista(idEmpresa) {
		let buscaExtra = '';
		if(idEmpresa > 0){
			buscaExtra = '&extra_bd='+encodeURI('AND enti_tx_ocupacao IN ("Motorista", "Ajudante", "Funcionário") AND enti_nb_empresa = "'+idEmpresa+'"');
			$('.busca_motorista')[0].innerHTML = null;
		}else{
			buscaExtra = '&extra_bd='+encodeURI('AND enti_tx_ocupacao IN ("Motorista", "Ajudante", "Funcionário")');
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
				url: select2URL+buscaExtra,
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