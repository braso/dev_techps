<style>
	.th-align {
		text-align: center; /* Define o alinhamento horizontal desejado, pode ser center, left ou right */
		vertical-align: middle !important; /* Define o alinhamento vertical desejado, pode ser top, middle ou bottom */
	}
	
	#saldo {
		width: 50% !important;
		margin-top: 9px !important;
		text-align: center;
	}
</style>

<form name="form_cadastrar_endosso" method="post">
	<input type="hidden" name="acao" value="cadastrar_endosso">
	<input type="hidden" name="idMotorista" value="<?= implode(",", $aIdMotorista) ?>">
	<input type="hidden" name="matriculaMotorista" value="<?= implode(",", $aMatriculaMotorista) ?>">
	<input type="hidden" name="busca_empresa" value="<?= $_POST['busca_empresa'] ?>">
	<input type="hidden" name="busca_data" value="<?= $_POST['busca_data'] ?>">
	<input type="hidden" name="busca_motorista" value="<?= $_POST['busca_motorista'] ?>">
	<input type="hidden" name="busca_situacao" value="<?= $_POST['busca_situacao'] ?>">
	<input type="hidden" name="aSaldo" value="<?= htmlspecialchars(json_encode($aSaldo)) ?>">
</form>

<form name="form_imprimir_relatorio" method="post" target="_blank">
	<input type="hidden" name="acao" value="imprimir_relatorio">
	<input type="hidden" name="idMotoristaEndossado" value="<?= implode(",", $aIdMotoristaEndossado) ?>">
	<input type="hidden" name="matriculaMotoristaEndossado" value="<?= (implode(",", $aMatriculaMotoristaEndossado)) ?>">
	<input type="hidden" name="busca_empresa" value="<?= $_POST['busca_empresa'] ?>">
	<input type="hidden" name="busca_data" value="<?= $_POST['busca_data'] ?>">
	<input type="hidden" name="busca_motorista" value="<?= $_POST['busca_motorista'] ?>">
	<input type="hidden" name="busca_situacao" value="<?= $_POST['busca_situacao'] ?>">
</form>

<form name="form_ajuste_ponto" method="post" target="_blank">
	<input type="hidden" name="acao" value="layout_ajuste">
	<input type="hidden" name="id" value="<?= $aMotorista['enti_nb_id'] ?>">
	<input type="hidden" name="data">
	<input type="hidden" name="data_de">
	<input type="hidden" name="data_ate">
</form>

<script>
	function selecionaMotorista(idEmpresa) {
		let buscaExtra = '';
		if (idEmpresa > 0) {
			buscaExtra = encodeURI('AND enti_tx_tipo = "Motorista" AND enti_nb_empresa = "' + idEmpresa + '"');
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

	window.onload = function() {
		document.getElementById('dadosResumo').innerHTML = '<?=$counts['message']?>';

		document.getElementById('botaoContexCadastrar CadastrarEndosso').onclick = function() {
			window.location.href = '<?= "https://braso.mobi" . $CONTEX['path'] . "/cadastro_endosso" ?>';
		}

		document.getElementById('botaoContexCadastrar ImprimirRelatorio').onclick = function() {
			document.form_imprimir_relatorio.submit();
		}
	};
</script>