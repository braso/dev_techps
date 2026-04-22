<?php
  
/*
		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");
*/
	include_once "utils/utils.php";
	include_once "check_permission.php";
	include "conecta.php";
	
	$chkRfidUserCol = query("SHOW COLUMNS FROM rfids LIKE 'rfids_nb_user_id';");
	if(!is_string($chkRfidUserCol) && $chkRfidUserCol && mysqli_num_rows($chkRfidUserCol) == 0){
		@query("ALTER TABLE rfids ADD COLUMN rfids_nb_user_id INT(11) DEFAULT NULL AFTER rfids_tx_uid;");
	}
	

	function carregarJS(){
		global $a_mod;

		$path_parts = pathinfo(__FILE__);

		$parametroPadrao = $a_mod["parametroPadrao"];

		$displayCamposJornada = "block";
		if(!empty($a_mod["enti_nb_parametro"])){
			$displayCamposJornada = mysqli_fetch_assoc(query(
				"SELECT para_tx_tipo FROM parametro WHERE para_nb_id = ?",
				"i",
				[$a_mod["enti_nb_parametro"]]
			));
			$displayCamposJornada = ($displayCamposJornada["para_tx_tipo"] == "horas_por_dia")? "block": "none";
		}

		echo 
			"



			
			<form name='form_excluir_arquivo' method='post' action='cadastro_funcionario.php'>
				<input type='hidden' name='idEntidade' value=''>
				<input type='hidden' name='idArq' value=''>
				<input type='hidden' name='nome_arquivo' value=''>
				<input type='hidden' name='acao' value=''>
			</form>

			<form name='form_download_arquivo' method='post' action='cadastro_funcionario.php'>
				<input type='hidden' name='idEntidade' value=''>
				<input type='hidden' name='caminho' value=''>
				<input type='hidden' name='acao' value=''>
			</form>

			<script>

				function downloadArquivo(id, caminho, acao) {
					document.form_download_arquivo.idEntidade.value = id;
					document.form_download_arquivo.caminho.value = caminho;
					document.form_download_arquivo.acao.value = acao;
					document.form_download_arquivo.submit();
				}

				function remover_arquivo(id, idArq, arquivo, acao ) {
					if (confirm('Deseja realmente excluir o arquivo '+arquivo+'?')) {
						document.form_excluir_arquivo.idEntidade.value = id;
						document.form_excluir_arquivo.idArq.value = idArq;
						document.form_excluir_arquivo.acao.value = acao;
						document.form_excluir_arquivo.submit();
					}
				}

				function buscarCEP(cep) {
					var num = cep.replace(/[^0-9]/g, '');
					if (num.length == '8') {
						document.getElementById('frame_parametro').src = '{$path_parts["basename"]}?acao=carregarEndereco&cep='+num;
					}
				}

				function carregarEmpresa(id) {
					document.getElementById('frame_parametro').src = 'cadastro_funcionario.php?acao=carregarEmpresa&emp='+id;
				}

				function carregarParametro() {
					id = document.getElementsByName('parametro')[0].value;
					document.getElementById('frame_parametro').src = 'cadastro_funcionario.php?acao=carregarParametro&parametro='+id;
					conferirParametroPadrao();
				}

				function filtrarSubSetor(id) {
					var el = document.getElementsByName('subSetor')[0];
					if (!el) return;
					el.innerHTML = '<option value=\'\' selected>Selecione</option>';
					el.disabled = true;
					if (!id) { el.parentElement.style.display = 'none'; return; }
					var url = '".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/select2.php?path=".$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."&tabela=sbgrupos_documentos&limite=200&condicoes=' + encodeURI('AND sbgr_nb_idgrup = '+id);
					$.ajax({ url: url, dataType: 'json' }).done(function(data){
						if (Array.isArray(data)) {
							if (data.length > 0) {
								data.forEach(function(item){
									var o = new Option(item.text, item.id, false, false);
									el.appendChild(o);
								});
								el.disabled = false;
								el.parentElement.style.display = '';
								var sel = '".((!empty($a_mod["enti_subSetor_id"]))? $a_mod["enti_subSetor_id"]: "")."';
								if (sel) { el.value = sel; }
							} else {
								el.disabled = true;
								el.parentElement.style.display = 'none';
							}
						}
					});
				}

				function fillSelectAjax(selectName, tabela, condicoes, selected) {
					var el = document.getElementsByName(selectName)[0];
					if (!el) return;
					el.innerHTML = '<option value=\'\' disabled selected>Selecione</option>';
					var url = '".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/select2.php?path=".$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."&tabela=' + encodeURIComponent(tabela) + '&limite=200&condicoes=' + encodeURI(condicoes || '');
					$.ajax({ url: url, dataType: 'json' }).done(function(data){
						if (Array.isArray(data)) {
							data.forEach(function(item){
								var o = new Option(item.text, item.id, false, false);
								el.appendChild(o);
							});
							if (selected) { el.value = String(selected); }
						}
					});
				}

				function fillBuscaSubsetor() {
					var setorBusca = document.getElementsByName('busca_setor')[0];
					var el = document.getElementsByName('busca_subsetor')[0];
					if (!setorBusca || !el) return;
					el.innerHTML = '<option value=\'\' selected>Selecione</option>';
					var id = setorBusca.value;
					if (!id) { el.disabled = true; return; }
					var url = '".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/select2.php?path=".$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."&tabela=sbgrupos_documentos&limite=200&condicoes=' + encodeURI('AND sbgr_nb_idgrup = '+id);
					$.ajax({ url: url, dataType: 'json' }).done(function(data){
						if (Array.isArray(data)) {
							data.forEach(function(item){
								var o = new Option(item.text, item.id, false, false);
								el.appendChild(o);
							});
							var sel = '".(isset($_POST["busca_subsetor"]) ? $_POST["busca_subsetor"] : "")."';
							if (sel) { el.value = sel; }
							el.disabled = false;
						}
					});
				}

				var parametroPadrao = ".json_encode($parametroPadrao).";
				function padronizarParametro() {
					var padraoDisplayJornada = (parametroPadrao.para_tx_tipo == 'escala')? 'none': 'block';
					parent.document.getElementsByName('divJornada')[0].style.display = padraoDisplayJornada;
					
					parent.document.contex_form.parametro.value 		= parametroPadrao.para_nb_id;
					parent.document.contex_form.jornadaSemanal.value 	= parametroPadrao.para_tx_jornadaSemanal;
					parent.document.contex_form.jornadaSabado.value 	= parametroPadrao.para_tx_jornadaSabado;
					parent.document.contex_form.percHESemanal.value 	= parametroPadrao.para_tx_percHESemanal;
					parent.document.contex_form.percHEEx.value 			= parametroPadrao.para_tx_percHEEx;

					conferirParametroPadrao();
				}

				function conferirParametroPadrao(){
					var padronizado = (
						parametroPadrao.para_nb_id == parent.document.contex_form.parametro.value &&
						parametroPadrao.para_tx_jornadaSemanal == parent.document.contex_form.jornadaSemanal.value &&
						parametroPadrao.para_tx_jornadaSabado == parent.document.contex_form.jornadaSabado.value &&
						parametroPadrao.para_tx_percHESemanal == parent.document.contex_form.percHESemanal.value &&
						parametroPadrao.para_tx_percHEEx == parent.document.contex_form.percHEEx.value
					);
					parent.document.getElementsByName('textoParametroPadrao')[0].getElementsByTagName('p')[0].innerText = (padronizado? 'Sim': 'Não');
				}
				conferirParametroPadrao();


				function checkOcupation(ocupation){
					console.log(ocupation);
					if(ocupation == 'Motorista'){
						document.getElementsByClassName('cnh-row')[0].setAttribute('style', '')
					}else{
						document.getElementsByClassName('cnh-row')[0].setAttribute('style', 'display:none')
					}
				}

				checkOcupation(document.getElementsByName('ocupacao')[0].value);

				function remover_foto(id, acao, arquivo) {
					if (confirm('Deseja realmente excluir o arquivo '+arquivo+'?')) {
						document.form_excluir_arquivo.idEntidade.value = id;
						document.form_excluir_arquivo.nome_arquivo.value = arquivo;
						document.form_excluir_arquivo.acao.value = acao;
						document.form_excluir_arquivo.submit();
					}
				}

				function remover_cnh(id, acao, arquivo) {
					if (confirm('Deseja realmente excluir o arquivo CNH '+arquivo+'?')) {
						document.form_excluir_arquivo.idEntidade.value = id;
						document.form_excluir_arquivo.nome_arquivo.value = arquivo;
						document.form_excluir_arquivo.acao.value = acao;
						document.form_excluir_arquivo.submit();
					}
				}

				parent.document.getElementsByName('divJornada')[0].style.display = '{$displayCamposJornada}';

				function irParaUsuario(idUser) {
					var form = document.createElement('form');
					form.method = 'POST';
					form.action = 'cadastro_usuario.php';

					var inputAcao = document.createElement('input');
					inputAcao.type = 'hidden';
					inputAcao.name = 'acao';
					inputAcao.value = 'modificarUsuario';
					form.appendChild(inputAcao);

					var inputId = document.createElement('input');
					inputId.type = 'hidden';
					inputId.name = 'id';
					inputId.value = idUser;
					form.appendChild(inputId);

                    // Avisa para o usuário que viemos da tela de funcionário (opcional, para controle futuro)
                    var fieldOrigem = document.createElement('input');
                    fieldOrigem.type = 'hidden';
                    fieldOrigem.name = 'tela_origem';
                    fieldOrigem.value = 'ficha_funcionario';
                    form.appendChild(fieldOrigem);

					document.body.appendChild(form);
					form.submit();
				}

				function imprimir() {
					// Abrir a caixa de diálogo de impressão
					// window.print();
					const id = '$_POST[id]';
					var form = document.createElement('form');
					form.method = 'POST';
					form.action = './impressao/ficha_funcionario.php';
					form.target = '_blank';

					var inputId = document.createElement('input');
					inputId.type = 'hidden';
					inputId.name = 'id';
					inputId.value = id;
					form.appendChild(inputId);

					document.body.appendChild(form);
					form.submit();
					document.body.removeChild(form);
				}


				
			</script>

			<script>
				function escapeHtml(s){
					var div = document.createElement('div');
					div.textContent = (s === undefined || s === null) ? '' : String(s);
					return div.innerHTML;
				}
				function selecionarResponsavelFuncionario(){
					if(window.jQuery && jQuery.fn && jQuery.fn.select2){
						var $el = jQuery('.resp-funcionario').first();
						if($el && $el.length){
							try{
								$el.select2('open');
							}catch(e){}
						}
					}
				}
				function verResponsaveisSetor(){
					verResponsaveis('setor');
				}
				function verResponsaveisCargo(){
					verResponsaveis('cargo');
				}
				function verResponsaveis(tipo){
					var tituloEl = document.getElementById('modal_responsaveis_titulo');
					var listaEl = document.getElementById('modal_responsaveis_lista');
					var linkEl = document.getElementById('modal_responsaveis_link');
					if(!tituloEl || !listaEl){
						return;
					}

					var setorEl = document.getElementsByName('setor')[0];
					var cargoEl = document.getElementsByName('tipoOperacao')[0];

					var id = 0;
					var url = '';
					if(tipo === 'setor'){
						id = parseInt((setorEl && setorEl.value) ? setorEl.value : '0', 10);
						tituloEl.textContent = 'Responsáveis do Setor';
						url = 'cadastro_setor.php?acao=api_responsaveis_setor&setor_id=' + id;
						if(linkEl){ linkEl.href = 'cadastro_setor.php'; }
					}else{
						id = parseInt((cargoEl && cargoEl.value) ? cargoEl.value : '0', 10);
						tituloEl.textContent = 'Responsáveis do Cargo';
						url = 'cadastro_operacao.php?acao=api_responsaveis_cargo&cargo_id=' + id;
						if(linkEl){ linkEl.href = 'cadastro_operacao.php'; }
					}

					if(!id || id <= 0){
						listaEl.innerHTML = \"<div class='alert alert-warning'>Selecione um \" + (tipo === 'setor' ? 'setor' : 'cargo') + \" para ver os responsáveis.</div>\";
						if(window.jQuery && jQuery.fn && jQuery.fn.modal){
							jQuery('#modal_responsaveis').modal('show');
						}
						return;
					}

					listaEl.innerHTML = \"<div class='alert alert-info'>Carregando...</div>\";

					var abrirModal = function(){
						if(window.jQuery && jQuery.fn && jQuery.fn.modal){
							jQuery('#modal_responsaveis').modal('show');
						}
					};

					var render = function(items){
						items = items || [];
						if(!items.length){
							listaEl.innerHTML = \"<div class='alert alert-warning'>Nenhum responsável vinculado.</div>\";
							abrirModal();
							return;
						}
						var html = '<ul>';
						items.forEach(function(it){
							if(!it){ return; }
							var txt = (it.text !== undefined && it.text !== null) ? String(it.text) : String(it.id || '');
							txt = escapeHtml(txt);
							html += '<li>' + txt + '</li>';
						});
						html += '</ul>';
						listaEl.innerHTML = html;
						abrirModal();
					};

					fetch(url, { credentials: 'same-origin' })
						.then(function(r){
							return r.text().then(function(t){
								var txt = String(t || '');
								var data = null;
								try{
									data = JSON.parse(txt);
								}catch(e){
									data = null;
								}
								if(!r.ok){
									throw { status: r.status, body: txt, json: data };
								}
								if(data && data.error){
									throw { status: r.status, body: txt, json: data };
								}
								if(data === null){
									throw { status: r.status, body: txt, json: null };
								}
								return data;
							});
						})
						.then(function(data){ render(data); })
						.catch(function(err){
							var body = err && err.body ? String(err.body) : '';
							body = escapeHtml(body);
							if(body.length > 800){ body = body.slice(0, 800) + '...'; }
							var status = (err && err.status) ? String(err.status) : '';
							listaEl.innerHTML =
								\"<div class='alert alert-danger'>Não foi possível carregar os responsáveis\" + (status ? ' (HTTP ' + status + ')' : '') + \".</div>\" +
								(body ? (\"<pre style='white-space:pre-wrap; margin:0;'>\" + body + \"</pre>\") : '');
							abrirModal();
						});
				}
			</script>"
		;

		return;
	}

	function carregarEmpresa(){
		$aEmpresa = carregar("empresa", (int)$_GET["emp"]);
		if ($aEmpresa["empr_nb_parametro"] > 0) {
			echo 
				"<script type='text/javascript'>
					var parametroEmpresa = '{$aEmpresa["empr_nb_parametro"]}';

					parent.document.contex_form.parametro.value = parametroEmpresa;
					parent.document.contex_form.parametro.onchange();
				</script>"
			;
		}
		exit;
	}

	function carregarParametroPadrao(int $idEmpresa = 0){
		global $a_mod;

		if(!empty($idEmpresa) && !empty($a_mod["enti_nb_empresa"])){
			$idEmpresa = intval($a_mod["enti_nb_empresa"]);
		}else{
			$idEmpresa = -1;
		}

		$a_mod["parametroPadrao"] = mysqli_fetch_assoc(query(
			"SELECT parametro.* FROM empresa
				JOIN parametro ON empresa.empr_nb_parametro = parametro.para_nb_id
				WHERE para_tx_status = 'ativo'
					AND empresa.empr_nb_id = ?
				LIMIT 1;",
			"i",
			[$idEmpresa]
		));
	}

	function carregarParametro(){
		if(empty($_GET["parametro"])){
			exit;
		}

		$parametro = mysqli_fetch_assoc(query(
			"SELECT * FROM parametro
				LEFT JOIN escala ON para_nb_id = esca_nb_parametro
				WHERE para_nb_id = {$_GET["parametro"]}
			LIMIT 1;"
		));
		
		if(empty($parametro)){
			exit;
		}

		echo
			"<script type='text/javascript'>
				var parametroCarregado = ".json_encode($parametro).";

				console.log(parametroCarregado);

				console.log(document.getElementsByName('divJornada'));
				parent.document.getElementsByName('divJornada')[0].style.display	= ((parametroCarregado.para_tx_tipo == 'escala')? 'none': 'block');
				
				parent.document.contex_form.jornadaSemanal.value						= parametroCarregado.para_tx_jornadaSemanal;
				parent.document.contex_form.jornadaSabado.value							= parametroCarregado.para_tx_jornadaSabado;
				parent.document.contex_form.percHESemanal.value							= parametroCarregado.para_tx_percHESemanal;
				parent.document.contex_form.percHEEx.value								= parametroCarregado.para_tx_percHEEx;
			</script>"
		;
		exit;
	}
	function carregarEndereco(){
		global $CONTEX;

		echo 
	      	"<script src='".$CONTEX['path']."/../contex20/assets/global/plugins/jquery.min.js' type='text/javascript'></script>
			<script src='".$CONTEX['path']."/../contex20/assets/global/plugins/select2/js/select2.min.js'></script>
			<script src='".$CONTEX['path']."/../contex20/assets/global/plugins/jquery-inputmask/jquery.inputmask.bundle.min.js' type='text/javascript'></script>
			<script src='".$CONTEX['path']."/../contex20/assets/global/plugins/jquery-inputmask/maskMoney.js' type='text/javascript'></script>

			<script type='text/javascript'>
				jQuery.ajax({
					url: 'https://viacep.com.br/ws/".urlencode($_GET["cep"])."/json/',
					type: 'get',
        			dataType: 'json',
					success: function(data) {
						if(data.erro == undefined){
							parent.document.contex_form.endereco.value = data.logradouro
							parent.document.contex_form.bairro.value = data.bairro;
							var selecionado = $('.cidade', parent.document);
							selecionado.empty();
							selecionado.append('<option value='+data.ibge+'>['+data.uf+'] '+data.localidade+'</option>');
							selecionado.val(data.ibge).trigger('change');
						}
					}
				});
			</script>"
    	;
		exit;
	}

	function showError(string $msg, array $errorFields){
		$_POST["errorFields"] = $errorFields;
		set_status("ERRO: {$msg}");
		visualizarCadastro();
		exit;
	}

	function ensureEntidadeResponsavelSchema(): void {
		$dbRow = mysqli_fetch_assoc(query("SELECT DATABASE() AS db"));
		$db = strval($dbRow["db"] ?? "");
		if($db === ""){
			return;
		}

		$cols = mysqli_fetch_all(query(
			"SELECT COLUMN_NAME
			FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = ?
				AND TABLE_NAME = 'entidade'",
			"s",
			[$db]
		), MYSQLI_ASSOC);

		$colNames = array_map(fn($r) => strval($r["COLUMN_NAME"] ?? ""), $cols ?: []);
		$has = array_flip($colNames);

		if(!isset($has["enti_respSetor_id"])){
			query("ALTER TABLE entidade ADD COLUMN enti_respSetor_id INT NULL");
		}
		if(!isset($has["enti_respCargo_id"])){
			query("ALTER TABLE entidade ADD COLUMN enti_respCargo_id INT NULL");
		}
		if(!isset($has["enti_respSetor_ids"])){
			query("ALTER TABLE entidade ADD COLUMN enti_respSetor_ids TEXT NULL");
		}
		if(!isset($has["enti_respCargo_ids"])){
			query("ALTER TABLE entidade ADD COLUMN enti_respCargo_ids TEXT NULL");
		}
		if(!isset($has["enti_respFuncionario_id"])){
			query("ALTER TABLE entidade ADD COLUMN enti_respFuncionario_id INT NULL");
		}
		if(!isset($has["enti_respFuncionario_ids"])){
			query("ALTER TABLE entidade ADD COLUMN enti_respFuncionario_ids TEXT NULL");
		}
	}

	function cadastrarMotorista(){
		global $a_mod;

		ensureEntidadeResponsavelSchema();

		if(!empty($_POST["matricula"])){
			$_POST["postMatricula"] = $_POST["matricula"];
		}
		if(!in_array($_ENV["CONTEX_PATH"], ["/comav"])){
			while($_POST["postMatricula"][0] == "0"){
				$_POST["postMatricula"] = substr($_POST["postMatricula"], 1);
			}
		}

		$_POST["cpf"] = preg_replace("/[^0-9]/is", "", $_POST["cpf"]);
		$_POST["rg"] = preg_replace("/[^0-9]/is", "", $_POST["rg"]);
		// Normalização de campos numéricos adicionais
		foreach(["pis","ctpsNumero","ctpsSerie","tituloNumero","tituloZona","tituloSecao","reservista","registroFuncional"] as $numField){
			if(isset($_POST[$numField])){ $_POST[$numField] = preg_replace('/[^0-9]/', '', $_POST[$numField]); }
		}
		if(isset($_POST["ctpsUf"])) { $_POST["ctpsUf"] = strtoupper($_POST["ctpsUf"]); }

		$enti_campos = [
			"enti_tx_matricula" 				=> "postMatricula", 
			"enti_tx_nome" 					=> "nome", 
			"enti_tx_nascimento" 				=> "nascimento", 
			"enti_tx_status" 					=> "status", 
			"enti_tx_cpf" 						=> "cpf",
			"enti_tx_rg" 						=> "rg",
			"enti_tx_civil" 					=> "civil",
			"enti_tx_sexo" 					=> "sexo",
			"enti_tx_racaCor" 				=> "racaCor",
			"enti_tx_tipoSanguineo" 			=> "tipoSanguineo",
			"enti_tx_endereco" 				=> "endereco",
			"enti_tx_numero" 					=> "numero",
			"enti_tx_complemento" 				=> "complemento",
			"enti_tx_bairro" 					=> "bairro",
			"enti_nb_cidade" 					=> "cidade",
			"enti_tx_cep" 						=> "cep",
			"enti_tx_fone1" 					=> "fone1",
			"enti_tx_fone2" 					=> "fone2",
			"enti_tx_email" 					=> "email",
			"enti_tx_referencia" 				=> "referencia",
			"enti_tx_ocupacao" 				=> "ocupacao",
			"enti_nb_salario" 				=> "salario",
			"enti_nb_parametro" 				=> "parametro", 
			"enti_tx_obs" 					=> "obs", 
			"enti_nb_empresa" 				=> "empresa",
			"enti_setor_id" 					=> "setor",
			"enti_subSetor_id" 				=> "subSetor",
			"enti_tx_jornadaSemanal" 		=> "jornadaSemanal",
			"enti_tx_jornadaSabado" 			=> "jornadaSabado",
			"enti_tx_percHESemanal" 			=> "percHESemanal",
			"enti_tx_percHEEx" 				=> "percHEEx",
			"enti_tx_rgOrgao" 				=> "rgOrgao", 
			"enti_tx_rgDataEmissao" 			=> "rgDataEmissao", 
			"enti_tx_rgUf" 					=> "rgUf",
			"enti_tx_pai" 					=> "pai", 
			"enti_tx_mae" 					=> "mae", 
			"enti_tx_conjugue" 				=> "conjugue", 
			"enti_tx_tipoOperacao" 			=> "tipoOperacao",
			"enti_tx_subcontratado" 			=> "subcontratado", 
			"enti_tx_admissao" 				=> "admissao", 
			"enti_tx_desligamento" 			=> "desligamento",
			"enti_tx_pis" 					=> "pis",
			"enti_tx_ctpsNumero" 				=> "ctpsNumero",
			"enti_tx_ctpsSerie" 				=> "ctpsSerie",
			"enti_tx_ctpsUf" 					=> "ctpsUf",
			"enti_tx_tituloNumero" 			=> "tituloNumero",
			"enti_tx_tituloZona" 				=> "tituloZona",
			"enti_tx_tituloSecao" 			=> "tituloSecao",
			"enti_tx_reservista" 				=> "reservista",
			"enti_tx_registroFuncional" 		=> "registroFuncional",
			"enti_tx_OrgaoRegimeFuncional" 	=> "orgaoRegimeFuncional",
			"enti_tx_vencimentoRegistro" 		=> "vencimentoRegistro",
			"enti_tx_cnhRegistro" 				=> "cnhRegistro", 
			"enti_tx_cnhValidade" 				=> "cnhValidade", 
			"enti_tx_cnhPrimeiraHabilitacao" 	=> "cnhPrimeiraHabilitacao", 
			"enti_tx_cnhCategoria" 			=> "cnhCategoria", 
			"enti_tx_cnhPermissao" 			=> "cnhPermissao",
			"enti_tx_cnhObs" 					=> "cnhObs", 
			"enti_nb_cnhCidade" 				=> "cnhCidade", 
			"enti_tx_cnhEmissao" 				=> "cnhEmissao", 
			"enti_tx_cnhPontuacao" 			=> "cnhPontuacao", 
			"enti_tx_cnhAtividadeRemunerada" 	=> "cnhAtividadeRemunerada",
			"enti_tx_banco" 					=> "setBanco"
		];

		$novoMotorista = [];
		$postKeys = array_values($enti_campos);

		foreach($enti_campos as $bdKey => $postKey){
			if(isset($_POST[$postKey])){
				$a_mod[$bdKey] = $_POST[$postKey];
				$novoMotorista[$bdKey] = $_POST[$postKey];
			}
		}
		$respSetorIds = $_POST["respSetor"] ?? [];
		$respSetorIds = is_array($respSetorIds) ? $respSetorIds : [$respSetorIds];
		$respSetorIds = array_values(array_unique(array_filter(array_map("intval", $respSetorIds), fn($v) => $v > 0)));
		$a_mod["enti_respSetor_id"] = $respSetorIds[0] ?? null;
		$a_mod["enti_respSetor_ids"] = !empty($respSetorIds) ? implode(",", $respSetorIds) : null;
		$novoMotorista["enti_respSetor_id"] = $a_mod["enti_respSetor_id"];
		$novoMotorista["enti_respSetor_ids"] = $a_mod["enti_respSetor_ids"];

		$respCargoIds = $_POST["respCargo"] ?? [];
		$respCargoIds = is_array($respCargoIds) ? $respCargoIds : [$respCargoIds];
		$respCargoIds = array_values(array_unique(array_filter(array_map("intval", $respCargoIds), fn($v) => $v > 0)));
		$a_mod["enti_respCargo_id"] = $respCargoIds[0] ?? null;
		$a_mod["enti_respCargo_ids"] = !empty($respCargoIds) ? implode(",", $respCargoIds) : null;
		$novoMotorista["enti_respCargo_id"] = $a_mod["enti_respCargo_id"];
		$novoMotorista["enti_respCargo_ids"] = $a_mod["enti_respCargo_ids"];
		if(!empty($_POST["desligamento"])){
			$novoMotorista["enti_tx_desligamento"] = $_POST["desligamento"];
		}
		unset($enti_campos);

		if(isset($novoMotorista["enti_nb_salario"])){
			$novoMotorista["enti_nb_salario"] = str_replace([".", ","], ["", "."], $novoMotorista["enti_nb_salario"]);
		}

		$invalidDates = ["0000-00-00","0001-01-01"];
		$dateKeys = ["enti_tx_nascimento","enti_tx_rgDataEmissao","enti_tx_admissao","enti_tx_desligamento","enti_tx_vencimentoRegistro","enti_tx_cnhValidade","enti_tx_cnhPrimeiraHabilitacao","enti_tx_cnhEmissao"];
		foreach($dateKeys as $dk){
			if(array_key_exists($dk, $novoMotorista)){
				$v = $novoMotorista[$dk];
				if($v === "" || in_array($v, $invalidDates, true)){
					$novoMotorista[$dk] = null;
				}
			}
		}


		//Conferir se os campos obrigatórios estão preenchidos{
			$camposObrig = [
				"nome" 						=> "Nome", 
				"nascimento" 				=> "Nascido em",
				"cpf" 						=> "CPF",
				"rg" 						=> "RG",
				"bairro" 					=> "Bairro",
				"cep" 						=> "CEP",
				"endereco" 					=> "Endereço",
				"cidade" 					=> "Cidade/UF",
				"fone1" 					=> "Telefone 1",
				"email" 					=> "E-mail",
				"empresa" 					=> "Empresa",
				"salario" 					=> "Salário",
				"ocupacao" 					=> "Ocupação",
				"admissao" 					=> "Dt Admissão",
				"parametro" 				=> "Parâmetro",
				// "jornadaSemanal" 			=> "Jornada Semanal",
				// "jornadaSabado" 			=> "Sábado",
				"percHESemanal" 			=> "H.E. Semanal",
				"percHEEx" 					=> "H.E. Extraordinária",
				"cnhRegistro" 				=> "N° Registro da CNH",
				"cnhCategoria" 				=> "Categoria do CNH",
				"cnhCidade" 				=> "Cidade do CNH",
				"cnhEmissao" 				=> "Data de Emissão do CNH",
				"cnhValidade" 				=> "Validade do CNH",
				"cnhPrimeiraHabilitacao" 	=> "1° Habilitação"
			];
			if(empty($novoMotorista["enti_tx_matricula"])){
				$camposObrig["postMatricula"] = "Matrícula";
			}
			// CNH é obrigatória apenas para Motorista. Para Ajudante, Funcionário e
			// Terceirizado, os campos são removidos da validação obrigatória.
			if(in_array($_POST["ocupacao"], ["Ajudante", "Funcionário", "Terceirizado"])){
				unset(
					$camposObrig["cnhRegistro"],
					$camposObrig["cnhCategoria"],
					$camposObrig["cnhCidade"],
					$camposObrig["cnhEmissao"],
					$camposObrig["cnhValidade"],
					$camposObrig["cnhPrimeiraHabilitacao"]
				);
			}
			if($_POST["status"] == "inativo"){
				$camposObrig["desligamento"] = "Desligamento";
			}

			if(!empty($_POST["parametro"])){
				$parametro = mysqli_fetch_assoc(query("SELECT para_tx_tipo FROM parametro WHERE para_nb_id = {$_POST["parametro"]}"));

				if($parametro["para_tx_tipo"] == "horas_por_dia"){
					$camposObrig["jornadaSemanal"] = "Jornada Semanal";
					$camposObrig["jornadaSabado"] = "Sábado";
				}
			}

			$errorMsg = conferirCamposObrig($camposObrig, $_POST);
			if(!empty($errorMsg)){
				showError($errorMsg, $_POST["errorFields"]);
			}
			unset($camposObrig);
		//}

		//Conferir se a matrícula já existe{
			if(!isset($_POST["id"]) && !empty($_POST["postMatricula"])){
				$matriculaExistente = !empty(mysqli_fetch_assoc(query(
					"SELECT * FROM entidade"
						." WHERE enti_tx_matricula = '".($_POST["postMatricula"]?? "-1")."'"
					." LIMIT 1;"
				)));

				$errorMsg = "";
				if($matriculaExistente){
					$errorMsg = "Matrícula já cadastrada.";
				}
				if(strlen($_POST["postMatricula"]) > 11){
					$errorMsg = "Matrícula com mais de 11 caracteres.";
				}

				if(!empty($errorMsg)){
					$_POST["errorFields"][] = "postMatricula";
					showError($errorMsg, ["postMatricula"]);
				}
			}
		//}

		//Conferir se o login já existe{
			if(!empty($_POST["login"])){
				$otherUser = mysqli_fetch_assoc(query(
					"SELECT user.* FROM user
						JOIN entidade ON user_nb_entidade = enti_nb_id
						WHERE user_tx_status = 'ativo'
							AND user_tx_login = '{$_POST["login"]}'"
							.(!empty($_POST["id"])? " AND enti_nb_id <> ".$_POST["id"]: "")
						." LIMIT 1;"
				));
				if(!empty($otherUser)){
					showError("Login já cadastrado.", ["login"]);
				}
			}
		//}

		//Conferir se o CPF é válido{
			if(!validarCPF($novoMotorista["enti_tx_cpf"])){
				showError("CPF inválido.", ["cpf"]);
			}
		//}

		//Conferir se o RG é válido{
			$_POST["rg"] = preg_replace( "/[^0-9]/is", "", $_POST["rg"]);
			if(strlen($_POST["rg"]) < 3){
				$_POST["errorFields"][] = "rg";
				showError("RG Parcial.", ["rg"]);
			}
		//}

		//Conferir se está ativo e a data de demissão é anterior a atual{
			if(!empty($novoMotorista["enti_tx_desligamento"]) && $novoMotorista["enti_tx_status"] == "ativo" && $novoMotorista["enti_tx_desligamento"] < date("Y-m-d")){
				showError("Não é possível colocar uma data de desligamento anterior a hoje enquanto o motorista estiver ativo.", ["status", "desligamento"]);
			}
		//}

		if (empty($_POST["id"])) {//Se está criando um motorista novo
			$loginEfetivoPre = (!empty($_POST["login"])? $_POST["login"]: $_POST["postMatricula"]);
			if(!empty($loginEfetivoPre)){
				$existsUserPre = mysqli_fetch_assoc(query(
					"SELECT user_nb_id FROM user WHERE user_tx_status = 'ativo' AND user_tx_login = ? LIMIT 1;",
					"s",
					[$loginEfetivoPre]
				));
				if(!empty($existsUserPre)){
					showError("Já existe usuário com o login/matrícula informados.", [(!empty($_POST["login"]) ? "login" : "postMatricula")]);
				}
			}
			query("START TRANSACTION;");
			$aEmpresa = carregar("empresa", $_POST["empresa"]);
			if($aEmpresa["empr_nb_parametro"] > 0){
				$aParametro = carregar("parametro", $aEmpresa["empr_nb_parametro"]);
				if (
					[$aParametro["para_tx_jornadaSemanal"], $aParametro["para_tx_jornadaSabado"], $aParametro["para_tx_percHESemanal"], $aParametro["para_tx_percHEEx"], $aParametro["para_nb_id"]] ==
					[$novoMotorista["enti_tx_jornadaSemanal"], $novoMotorista["enti_tx_jornadaSabado"], $novoMotorista["enti_tx_percHESemanal"], $novoMotorista["enti_tx_percHEEx"], $novoMotorista["enti_nb_parametro"]]
				){
					$ehPadrao = "sim";
				}else{
					$ehPadrao = "nao";
				}
			}
			$novoMotorista["enti_nb_userCadastro"] = $_SESSION["user_nb_id"];
			$novoMotorista["enti_tx_dataCadastro"] = date("Y-m-d H:i:s");
			$novoMotorista["enti_tx_ehPadrao"] = $ehPadrao;
			$insertEnt = inserir("entidade", array_keys($novoMotorista), array_values($novoMotorista));
			if(empty($insertEnt) || ($insertEnt[0] instanceof Exception)){
				query("ROLLBACK;");
				dd($insertEnt);
				set_status("ERRO ao cadastrar motorista.");
				index();
				exit;
			}
			$id = $insertEnt[0];
			
			$newUser = [
				"user_tx_nome" 			=> $_POST["nome"],
				"user_tx_nivel" 		=> $_POST["ocupacao"], 
				"user_tx_login" 		=> (!empty($_POST["login"])? $_POST["login"]: $_POST["postMatricula"]), 
				"user_tx_senha" 		=> md5($_POST["cpf"]), 
				"user_tx_status" 		=> $_POST["status"], 
				"user_nb_entidade" 		=> $id,
				"user_tx_nascimento" 	=> $_POST["nascimento"], 
				"user_tx_cpf" 			=> $_POST["cpf"],
				"user_tx_rg" 			=> $_POST["rg"],
				"user_nb_cidade" 		=> $_POST["cidade"], 
				"user_tx_email" 		=> $_POST["email"], 
				"user_tx_fone" 			=> $_POST["fone1"], 
				"user_nb_empresa" 		=> $_POST["empresa"],
				"user_nb_userCadastro" 	=> $_SESSION["user_nb_id"], 
				"user_tx_dataCadastro" 	=> date("Y-m-d H:i:s")
			];
			foreach($newUser as $key => $value){
				if($value === "" || $value === null){
					unset($newUser[$key]);
				}
			}

			$insertUser = inserir("user", array_keys($newUser), array_values($newUser));
			if(empty($insertUser) || ($insertUser[0] instanceof Exception)){
				query("ROLLBACK;");
				dd($insertUser);
				set_status("ERRO ao cadastrar usuário.");
				index();
				exit;
			}

			
			query("COMMIT;");
		}else{ // Se está editando um motorista existente

			// Inclui Terceirizado na busca do usuário vinculado para permitir edição
			// sem perder o vínculo por filtro restritivo de níveis antigos.
			$a_user = mysqli_fetch_array(query(
				"SELECT * FROM user 
					WHERE user_nb_entidade = ".$_POST["id"]."
						AND user_tx_nivel IN ('Motorista', 'Ajudante','Funcionário', 'Terceirizado')"
			), MYSQLI_BOTH);

			$_POST["nivel"] = $_POST["ocupacao"];

			if(!empty($a_user["user_nb_id"])){
				$newUser = [
					"user_tx_nome" 			=> $_POST["nome"], 
					"user_tx_login" 		=> (!empty($_POST["login"])? $_POST["login"]: $_POST["postMatricula"]), 
					"user_tx_nivel" 		=> $_POST["nivel"],
					"user_tx_status" 		=> $_POST["status"], 
					"user_nb_entidade" 		=> $_POST["id"],
					"user_tx_nascimento" 	=> $_POST["nascimento"], 
					"user_tx_cpf" 			=> $_POST["cpf"], 
					"user_tx_rg" 			=> $_POST["rg"], 
					"user_nb_cidade" 		=> $_POST["cidade"], 
					"user_tx_email" 		=> $_POST["email"], 
					"user_tx_fone" 			=> $_POST["fone1"],
					"user_nb_empresa" 		=> $_POST["empresa"],
					"user_nb_userAtualiza" 	=> $_SESSION["user_nb_id"], 
					"user_tx_dataAtualiza" 	=> date("Y-m-d H:i:s")
				];
				foreach($newUser as $key => $value){
					if(empty($value)){
						unset($newUser[$key]);
					}
				}
				atualizar("user", array_keys($newUser), array_values($newUser), $a_user["user_nb_id"]);
			}
			$aEmpresa = carregar("empresa", $_POST["empresa"]);
			if ($aEmpresa["empr_nb_parametro"] > 0) {
				$aParametro = carregar("parametro", $aEmpresa["empr_nb_parametro"]);
				if (
					$aParametro["para_tx_jornadaSemanal"] != $novoMotorista["enti_tx_jornadaSemanal"] ||
					$aParametro["para_tx_jornadaSabado"] != $novoMotorista["enti_tx_jornadaSabado"] ||
					$aParametro["para_tx_percHESemanal"] != $novoMotorista["enti_tx_percHESemanal"] ||
					$aParametro["para_tx_percHEEx"] != $novoMotorista["enti_tx_percHEEx"] ||
					$aParametro["para_nb_id"] != $novoMotorista["enti_nb_parametro"]
				) {
					$ehPadrao = "nao";
				} else {
					$ehPadrao = "sim";
				}
			}
			$novoMotorista["enti_nb_userAtualiza"] = $_SESSION["user_nb_id"];
			$novoMotorista["enti_tx_dataAtualiza"] = date("Y-m-d H:i:s");
			$novoMotorista["enti_tx_ehPadrao"] = $ehPadrao;

			atualizar("entidade", array_keys($novoMotorista), array_values($novoMotorista), $_POST["id"]);
			$id = $_POST["id"];

		}

		$file_type = $_FILES["cnhAnexo"]["type"]; //returns the mimetype

		$allowed = ["image/jpeg", "image/gif", "image/png", "application/pdf"];
		if (in_array($file_type, $allowed) && $_FILES["cnhAnexo"]["name"] != "") {

			if (!is_dir("arquivos/empresa/{$_POST["empresa"]}/motoristas/{$_POST["matricula"]}")) {
				mkdir("arquivos/empresa/{$_POST["empresa"]}/motoristas/{$_POST["matricula"]}", 0777, true);
			}

			$arq = enviar("cnhAnexo", "arquivos/empresa/{$_POST["empresa"]}/motoristas/{$_POST["matricula"]}/", "CNH_{$id}_{$_POST["postMatricula"]}");
			if ($arq) {
				atualizar("entidade", ["enti_tx_cnhAnexo"], [$arq], $id);
			}
		}
		
		
		$idUserFoto = mysqli_fetch_assoc(query("SELECT user_nb_id FROM user WHERE user_nb_entidade = '{$id}' LIMIT 1;"));
		$file_type = $_FILES["foto"]["type"]; //returns the mimetype

		$allowed = ["image/jpeg", "image/gif", "image/png"];
		if (in_array($file_type, $allowed) && $_FILES["foto"]["name"] != "") {

			if (!is_dir("arquivos/empresa/{$_POST["empresa"]}/motoristas/{$_POST["matricula"]}")) {
				mkdir("arquivos/empresa/{$_POST["empresa"]}/motoristas/{$_POST["matricula"]}", 0777, true);
			}

			$arq = enviar("foto", "arquivos/empresa/{$_POST["empresa"]}/motoristas/{$_POST["matricula"]}/", "FOTO_{$id}_{$_POST["postMatricula"]}");
			if($arq){
				atualizar("entidade", ["enti_tx_foto"], [$arq], $id);
				atualizar("user", ["user_tx_foto"], [$arq], $idUserFoto["user_nb_id"]);
			}
		}

		$_POST["id"] = $id;
		index();
		exit;
	}

	function modificarMotorista(){
		global $a_mod;

		$id = intval($_POST["id"] ?? 0);
		if($id <= 0){
			set_status("ERRO: Código do funcionário inválido.");
			index();
			exit;
		}

		$a_mod = carregar("entidade", $id);
		if(empty($a_mod)){
			set_status("ERRO: Funcionário não encontrado.");
			index();
			exit;
		}
		//dd($a_mod, false);
		visualizarCadastro();
		exit;
	}

	function excluirMotorista(){
		$id = intval($_POST["id"] ?? 0);
		if($id <= 0){
			set_status("ERRO: Código do funcionário inválido.");
			index();
			exit;
		}

		$motorista = mysqli_fetch_assoc(query(
			"SELECT enti_tx_desligamento, user_nb_id FROM entidade 
				LEFT JOIN user ON enti_nb_id = user_nb_entidade
				WHERE enti_tx_status = 'ativo'
					AND enti_nb_id = ".$id."
				LIMIT 1;"
		));

		if(empty($motorista)){
			set_status("Funcionário já inativado.");
			index();
			exit;
		}

		if(empty($motorista["enti_tx_desligamento"]) || $motorista["enti_tx_desligamento"] > date("Y-m-d")){
			$motorista["enti_tx_desligamento"] = date("Y-m-d");
		}
		
		atualizar("entidade", ["enti_tx_status", "enti_tx_desligamento"], ["inativo", $motorista["enti_tx_desligamento"]], $id);
		if(!empty($motorista["user_nb_id"])){
			atualizar("user", ["user_tx_status"], ["inativo"], $motorista["user_nb_id"]);
		}
		
		index();
		exit;
	}

	function excluir_documento() {

		query("DELETE FROM documento_funcionario WHERE docu_nb_id = $_POST[idArq]");
		
		// Após excluir, apenas recarrega a tela do funcionário
		$_POST["id"] = $_POST["idEntidade"];
		modificarMotorista();
		exit;
	}

	function excluirFoto(){
		$caminho = $_POST["nome_arquivo"] ?? "";
		if (!empty($caminho) && file_exists($caminho)) { @unlink($caminho); }
		atualizar("entidade", ["enti_tx_foto"], [""], $_POST["idEntidade"]);
		$rowU = mysqli_fetch_assoc(query("SELECT user_nb_id FROM user WHERE user_nb_entidade = ? LIMIT 1", "i", [$_POST["idEntidade"]]));
		if (!empty($rowU["user_nb_id"])) {
			atualizar("user", ["user_tx_foto"], [""], $rowU["user_nb_id"]);
		}
		$_POST["id"] = $_POST["idEntidade"];
		modificarMotorista();
		exit;
	}

	function excluirCNH(){
		atualizar("entidade", ["enti_tx_cnhAnexo"], [""], $_POST["idEntidade"]);
		$_POST["id"] = $_POST["idEntidade"];
		modificarMotorista();
		exit;
	}

	function atualizarDocumento() {
		$idDoc = intval($_POST["idDoc"] ?? 0);
		$nome = $_POST["file-name-edit"] ?? '';
		$descricao = $_POST["description-text-edit"] ?? '';
		$visibilidade = $_POST["visibilidade-edit"] ?? 'nao';
		$dataVenc = $_POST["data_vencimento_edit"] ?? null;
		$doc = mysqli_fetch_assoc(query("SELECT * FROM documento_funcionario WHERE docu_nb_id = {$idDoc} LIMIT 1;"));
		if (empty($doc)) {
			set_status("Registro não encontrado.");
			$_POST["id"] = $_POST["idRelacionado"];
			visualizarCadastro();
			exit;
		}
		$errorMsg = "";
		$novoTipo = isset($_POST["tipo_documento_edit"]) ? intval($_POST["tipo_documento_edit"]) : 0;
		$novoSetor = isset($_POST["setor-edit"]) ? intval($_POST["setor-edit"]) : 0;
		$novoSubSetor = isset($_POST["sub-setor-edit"]) ? intval($_POST["sub-setor-edit"]) : 0;
		$tipoUsado = $novoTipo > 0 ? $novoTipo : intval($doc["docu_tx_tipo"]);
		$subgrupoUsado = $novoSubSetor > 0 ? $novoSubSetor : 0;
		$obg = mysqli_fetch_assoc(query("SELECT tipo_tx_vencimento FROM tipos_documentos WHERE tipo_nb_id = {$tipoUsado} LIMIT 1;"));
		if (($obg["tipo_tx_vencimento"] ?? 'nao') === 'sim' && (empty($dataVenc) || $dataVenc === "0000-00-00")) {
			$errorMsg = "Campo obrigatório não preenchidos: Data de Vencimento";
		}
		if ($subgrupoUsado > 0) {
			$rows = mysqli_fetch_all(query(
				"SELECT docu_tx_nome FROM documento_funcionario WHERE docu_tx_tipo = {$tipoUsado}
				AND docu_nb_sbgrupo = {$subgrupoUsado} AND docu_nb_entidade = {$doc["docu_nb_entidade"]}
				AND docu_nb_id <> {$idDoc}"), MYSQLI_ASSOC);
		} else {
			$rows = mysqli_fetch_all(query(
				"SELECT docu_tx_nome FROM documento_funcionario WHERE docu_tx_tipo = {$tipoUsado}
				AND (docu_nb_sbgrupo IS NULL OR docu_nb_sbgrupo = 0) AND docu_nb_entidade = {$doc["docu_nb_entidade"]}
				AND docu_nb_id <> {$idDoc}"), MYSQLI_ASSOC);
		}
		$buscaNormalizada = normalizar($nome);
		$encontrado = array_filter(array_column($rows, 'docu_tx_nome'), fn($n) => normalizar($n) === $buscaNormalizada);
		if (!empty($encontrado)) {
			$errorMsg = "Já existe um documento com esse nome para o tipo selecionado.";
		}
		$novoCaminho = null;
		$novoAssinado = $doc["docu_tx_assinado"] ?? "nao";
		$arquivoEdit = $_FILES["file-edit"] ?? null;
		if (!$errorMsg && $arquivoEdit && $arquivoEdit["error"] === UPLOAD_ERR_OK) {
			$formatos = [
				"image/jpeg" => "jpg",
				"image/png" => "png",
				"application/msword" => "doc",
				"application/vnd.openxmlformats-officedocument.wordprocessingml.document" => "docx",
				"application/pdf" => "pdf"
			];
			$tipo = mime_content_type($arquivoEdit["tmp_name"]);
			if (!array_key_exists($tipo, $formatos)) {
				$errorMsg = "Tipo de arquivo não permitido.";
			} else {
				$nomeOriginal = basename($arquivoEdit["name"]);
				$nomeSeguro = preg_replace('/[^\p{L}\p{N}\s\.\-\_]/u', '_', $nomeOriginal);
				$pasta = "arquivos/Funcionarios/" . intval($doc["docu_nb_entidade"]) . "/";
				if (!is_dir($pasta)) { mkdir($pasta, 0777, true); }
				$novoCaminho = $pasta . $nomeSeguro;
				if (file_exists($novoCaminho)) {
					$info = pathinfo($nomeSeguro);
					$base = $info["filename"];
					$ext = isset($info["extension"]) ? '.' . $info["extension"] : '';
					$nomeSeguro = $base . '_' . time() . $ext;
					$novoCaminho = $pasta . $nomeSeguro;
				}
				if ($tipo === "application/pdf" && function_exists("temAssinaturaRapido")) {
					$novoAssinado = temAssinaturaRapido($arquivoEdit["tmp_name"]) ? "sim" : "nao";
				} else {
					$novoAssinado = "nao";
				}
				if (!move_uploaded_file($arquivoEdit["tmp_name"], $novoCaminho)) {
					$errorMsg = "Falha ao mover o arquivo para o diretório de destino.";
					$novoCaminho = null;
				}
			}
		}
		if (!empty($errorMsg)) {
			set_status("ERRO: ".$errorMsg);
			$_POST["id"] = $doc["docu_nb_entidade"];
			visualizarCadastro();
			exit;
		}
		$campos = ["docu_tx_nome","docu_tx_descricao","docu_tx_dataVencimento","docu_tx_visivel","docu_tx_tipo","docu_nb_sbgrupo"];
		$valores = [$nome,$descricao,$dataVenc,$visibilidade,$tipoUsado,($subgrupoUsado > 0 ? $subgrupoUsado : null)];
		if ($novoCaminho) {
			$campos[] = "docu_tx_caminho";
			$campos[] = "docu_tx_assinado";
			$valores[] = $novoCaminho;
			$valores[] = $novoAssinado;
			if (!empty($doc["docu_tx_caminho"]) && $doc["docu_tx_caminho"] !== $novoCaminho && file_exists($doc["docu_tx_caminho"])) {
				@unlink($doc["docu_tx_caminho"]);
			}
		}
		atualizar("documento_funcionario", $campos, $valores, $idDoc);
		set_status("Registro atualizado com sucesso.");
		$_POST["id"] = $doc["docu_nb_entidade"];
		visualizarCadastro();
		exit;
	}

	function temAssinaturaRapido($filePath) {
		if (!file_exists($filePath)) {
			return false;
		}

		$conteudo = file_get_contents($filePath);
		
		// Procura por duas chaves que são obrigatórias em PDFs assinados.
		if (strpos($conteudo, '/AcroForm') !== false && strpos($conteudo, '/Sig') !== false) {
			return true;
		}
		
		return false;
	}

	function enviarDocumento() {
		global $a_mod;

		// dd($_POST);

		if (empty($a_mod) && isset($_POST["idRelacionado"])) {
			$a_mod = carregar("entidade", $_POST["idRelacionado"]);
		}

		$errorMsg = "";
		if(isset($_POST["tipo_documento"]) && !empty($_POST["tipo_documento"])){
			$obgVencimento = mysqli_fetch_all(query("SELECT tipo_tx_vencimento FROM `tipos_documentos` 
			WHERE tipo_nb_id = {$_POST["tipo_documento"]}"), MYSQLI_ASSOC);

			if($obgVencimento[0]['tipo_tx_vencimento'] == 'sim' && (empty($_POST["data_vencimento"]) || $_POST["data_vencimento"] == "0000-00-00")){
				$errorMsg = "Campo obrigatório não preenchidos: Data de Vencimento";
			}
		}

		if(!empty($_POST["tipo_documento"]) && !empty($_POST["sub-setor"])) {

			$nomes_documentos_subsetor = mysqli_fetch_all(query(
				"SELECT docu_tx_nome
				FROM documento_funcionario
				WHERE docu_tx_tipo = {$_POST["tipo_documento"]} AND docu_nb_sbgrupo = {$_POST["sub-setor"]} AND docu_nb_entidade = {$_POST["idRelacionado"]}" 
			), MYSQLI_ASSOC);

			$buscaNormalizada = normalizar($_POST["file-name"]);

			$encontrado = array_filter(
				array_column($nomes_documentos_subsetor, 'docu_tx_nome'),
				fn($nome) => normalizar($nome) === $buscaNormalizada
			);

			if (!empty($encontrado)) {
				$errorMsg = "Já existe um documento com esse nome para o tipo selecionado.";
			} 

		} else if(!empty($_POST["tipo_documento"])){

			$nomes_documentos_setor = mysqli_fetch_all(query(
				"SELECT docu_tx_nome
				FROM documento_funcionario
				WHERE docu_tx_tipo = {$_POST["tipo_documento"]}
				AND (docu_nb_sbgrupo IS NULL OR docu_nb_sbgrupo = 0) AND docu_nb_entidade = {$_POST["idRelacionado"]}"
			), MYSQLI_ASSOC);

			$buscaNormalizada = normalizar($_POST["file-name"]);

			$encontrado = array_filter(
				array_column($nomes_documentos_setor, 'docu_tx_nome'),
				fn($nome) => normalizar($nome) === $buscaNormalizada
			);

			if (!empty($encontrado)) {
				$errorMsg = "Já existe um documento com esse nome para o tipo selecionado.";
			} 

		}

		if(!empty($errorMsg)){
			set_status("ERRO: ".$errorMsg);
			$_POST["id"] = $_POST["idRelacionado"];
			visualizarCadastro();
			exit;
		}

		$novoArquivo = [
			"docu_nb_entidade" => (int) $_POST["idRelacionado"],
			"docu_tx_nome" => $_POST["file-name"] ?? '',
			"docu_tx_descricao" => $_POST["description-text"] ?? '',
			"docu_tx_dataCadastro" => date("Y-m-d H:i:s"),
			"docu_tx_dataVencimento" => $_POST["data_vencimento"] ?? null,
			"docu_tx_tipo" => $_POST["tipo_documento"] ?? '',
			"docu_nb_sbgrupo" => (int) $_POST["sub-setor"] ?? null,
			"docu_tx_usuarioCadastro" => (int) $_POST["idUserCadastro"],
			"docu_tx_assinado" => "nao",
			"docu_tx_visivel" => $_POST["visibilidade"] ?? 'nao'
		];

		$arquivo = $_FILES["file"] ?? null;
		if (!$arquivo || $arquivo["error"] !== UPLOAD_ERR_OK) {
			set_status("Erro no upload do arquivo.");
			visualizarCadastro();
			exit;
		}

		// Tipos de arquivo permitidos
		$formatos = [
			"image/jpeg" => "jpg",
			"image/png" => "png",
			"application/msword" => "doc",
			"application/vnd.openxmlformats-officedocument.wordprocessingml.document" => "docx",
			"application/pdf" => "pdf"
		];

		// Valida tipo real do arquivo (mais seguro que apenas $_FILES["type"])
		$tipo = mime_content_type($arquivo["tmp_name"]);
		if (!array_key_exists($tipo, $formatos)) {
			set_status("Tipo de arquivo não permitido.");
			visualizarCadastro();
			exit;
		}

		// Usa o nome original do arquivo (mas sanitiza para evitar caracteres perigosos)
		$nomeOriginal = basename($arquivo["name"]); // remove possíveis caminhos
		$nomeSeguro = preg_replace('/[^\p{L}\p{N}\s\.\-\_]/u', '_', $nomeOriginal); // mantém letras, números, espaço, ponto, traço e underscore

		$pasta_funcionario = "arquivos/Funcionarios/" . $novoArquivo["docu_nb_entidade"] . "/";
		if (!is_dir($pasta_funcionario)) {
			mkdir($pasta_funcionario, 0777, true);
		}

		// Caminho físico usa o nome original (sanitizado)
		$novoArquivo["docu_tx_caminho"] = $pasta_funcionario . $nomeSeguro;

		// Se for PDF, verifica assinatura
		if ($tipo === "application/pdf" && function_exists("temAssinaturaRapido")) {
			if (temAssinaturaRapido($arquivo["tmp_name"])) {
				$novoArquivo["docu_tx_assinado"] = "sim";
			}
		}

		// Evita sobrescrever arquivos já existentes
		if (file_exists($novoArquivo["docu_tx_caminho"])) {
			$info = pathinfo($nomeSeguro);
			$base = $info["filename"];
			$ext = isset($info["extension"]) ? '.' . $info["extension"] : '';
			$nomeSeguro = $base . '_' . time() . $ext;
			$novoArquivo["docu_tx_caminho"] = $pasta_funcionario . $nomeSeguro;
		}

		// Move o arquivo e salva no banco
		if (move_uploaded_file($arquivo["tmp_name"], $novoArquivo["docu_tx_caminho"])) {
			inserir("documento_funcionario", array_keys($novoArquivo), array_values($novoArquivo));
			set_status("Registro inserido com sucesso.");
		} else {
			set_status("Falha ao mover o arquivo para o diretório de destino.");
		}

		$_POST["id"] = $novoArquivo["docu_nb_entidade"];
		visualizarCadastro();
		exit;
	}

	function visualizarCadastro(){
		global $a_mod;

		echo '<style>
		@media print{
			input, select, textarea {
				background: transparent !important;
				box-shadow: none !important;
				color: black !important;
				font-size: 10pt !important;
				// margin: 0 !important;
				width: 100% !important;
			}

			 label, .form-label {
				font-weight: bold !important;
				color: #000 !important;
				padding-bottom: 10px !important;
			}

			.form-group,
			.row,
			.linha-campos,
			.form-row,
			.form-section {
				display: flex !important;
				flex-wrap: wrap !important;
				// gap: 10px !important;
				width: 100% !important;
			}
			
			#email, #nome{
				width: 240px !important;
			}
			
			select {
				appearance: none !important;        /* Padrão moderno */
				-webkit-appearance: none !important; /* Safari/Chrome */
				-moz-appearance: none !important;    /* Firefox */
			}

			input[type="date"]::-webkit-calendar-picker-indicator {
				display: none !important;
				-webkit-appearance: none !important;
			}

			.select2-selection__clear,
			div.col-sm-4.margin-bottom-5.campo-fit-content,
			form > div.form-actions,
			span.select2-selection__arrow,
			form > div.msg-status-text,
			#padronizarParametro,
			.scroll-to-top {
				display: none !important;
			}
			
			.portlet.light{
			    padding: 12px 0px 0px !important;
			}
			
			.portlet{
				margin-bottom: 0px !important;
			}
			form > div.cnh-row > div.row,
			form > div:nth-child(21),
			form > div:nth-child(25){
			    margin: 0px 0px 0px 0px !important;
			}

			div.col-sm-2.margin-bottom-5.campo-fit-content.text-field > p > img{
				width: 350px !important;
			}
			
			form > div:nth-child(11),
			form > div:nth-child(19){
				margin-top: 100px;
			}

			div.imageForm > div > div.col-sm-2.margin-bottom-5.campo-fit-content.text-field{
				padding-left: 350px !important;
			}

			// div:nth-child(20) > label{
			// 	padding-bottom: 10px;
			// }
			
			.form-group > div,
			.form-row > div,
			div > .campo-fit-content {
				// width: 240px !important;  /* largura fixa para todos os campos */
				box-sizing: border-box;
				margin-right: 10px; /* espaçamento lateral */
			}

			.container,
			.card,
			.card-body,
			.section-wrapper {
				width: 100% !important;
				margin: 0 !important;
				padding: 0 !important;
			}

			/* Oculta elementos desnecessários na impressão */
			.no-print,
			.btn,
			nav,
			footer {
				display: none !important;
			}

			.container-fluid
			{
			    padding-left: 0px !important;
        		padding-right: 0px !important;
			}
			.page-content{
				min-height: 0px !important;
			}
			
			form > div:nth-child(25) > div:nth-child(1),
			form > div:nth-child(9) > div.col-sm-2.margin-bottom-5.campo-fit-content.text-field{
			    margin-right: 17px;
			}
			
			

			@page{
				size: A4 landscape;
				// margin: 1cm;
			}
		}
		</style>';

		if(!empty($a_mod["enti_nb_empresa"])){
			carregarParametroPadrao($a_mod["enti_nb_empresa"]);
		}
		
		if(empty($a_mod) && !empty($_POST)){
			if(isset($_POST["id"])){
				$a_mod = carregar("entidade", $_POST["id"]);
			}

			$campos = mysqli_fetch_all(query("SHOW COLUMNS FROM entidade;"));

			for($f = 0; $f < sizeof($campos); $f++){
				$campos[$f] = $campos[$f][0];
			}

			
			foreach($campos as $campo){
				if(isset($_POST[$campo]) && !empty($_POST[$campo])){
					$a_mod[$campo] = $_POST[str_replace(["enti_tx_", "enti_nb_"], "", $campo)];
				}
			}
		}


		if(!empty($a_mod["enti_nb_id"])){
			$login = mysqli_fetch_all(
				query(
					"SELECT user_tx_login FROM user 
						WHERE ".$a_mod["enti_nb_id"]." = user_nb_entidade 
						LIMIT 1"
				),
				MYSQLI_ASSOC
			)[0];
			$a_mod["user_tx_login"] = $login["user_tx_login"];
		}
		
		cabecalho("Cadastro de Funcionário");

		if(!empty($a_mod["enti_tx_nascimento"])){
			$data1 = new DateTime($a_mod["enti_tx_nascimento"]);
			$data2 = new DateTime(date("Y-m-d"));
	
			$intervalo = $data1->diff($data2);
	
			$idade = "{$intervalo->y} anos, {$intervalo->m} meses e {$intervalo->d} dias";
		}
		
		
		if(!empty($a_mod["enti_tx_foto"])){
			$img = texto(
				"<a style='color:gray' onclick='javascript:remover_foto(\"".($a_mod["enti_nb_id"]?? "")."\",\"excluirFoto\",\"".($a_mod["enti_tx_foto"]?? "")."\");' >
					<spam class='glyphicon glyphicon-remove'></spam>
					Excluir
				</a>", 
				"<img style='width: 100%;' src='".($a_mod["enti_tx_foto"]?? "")."' />", 
				2
			);
		}else{
			$img = texto(
				"", 
				"<img style='width: 100%;' src='../contex20/img/driver.png' />",
				2
			);
		}
		
		$tabIndex = 1;

		// Busca o Usuário (1:1) vinculado a este funcionário para checar o RFID
		$entityIdForRfid = !empty($a_mod["enti_nb_id"]) ? (int)$a_mod["enti_nb_id"] : 0;
		$userIdForRedirect = "";
		$rfidTexto = "Sem RFID vinculado";

		if ($entityIdForRfid > 0) {
            // Descobre o ID do usuário (tabela user)
			$rowUser = mysqli_fetch_assoc(query("SELECT user_nb_id FROM user WHERE user_nb_entidade = {$entityIdForRfid} AND user_tx_status = 'ativo' LIMIT 1"));
			
			if (!empty($rowUser)) {
				$userIdForRedirect = $rowUser["user_nb_id"];
				
				// Busca o RFID pelo ID do usuário
				$rowAssigned = mysqli_fetch_assoc(query("SELECT rfids_tx_uid, rfids_tx_descricao FROM rfids WHERE rfids_nb_user_id = {$userIdForRedirect} AND rfids_tx_status = 'ativo' LIMIT 1"));
				
				if (!empty($rowAssigned)) {
					$rfidTexto = "<b>" . $rowAssigned["rfids_tx_uid"] . "</b>";
					if (!empty($rowAssigned["rfids_tx_descricao"])) {
                        // Trunca a descrição se for muito longa
                        $descricao = $rowAssigned["rfids_tx_descricao"];
                        if (mb_strlen($descricao) > 35) {
                            $descricao = mb_substr($descricao, 0, 35) . "...";
                        }
						$rfidTexto .= " - " . $descricao;
					}
				}
			}
		}

		$btnAlterarRfid = "";
		if (!empty($userIdForRedirect)) {
			// Tem usuário, mostra o botão de lápis que leva pra tela de usuários
			$btnAlterarRfid = "&nbsp;&nbsp;<a href=\"javascript:void(0);\" onclick=\"irParaUsuario({$userIdForRedirect})\" title=\"Editar vínculo no Cadastro de Usuário\" style=\"color: #f0ad4e;\"><span class=\"glyphicon glyphicon-pencil\"></span></a>";
		} elseif ($entityIdForRfid > 0) {
			// Tem funcionário mas não tem usuário ativo
			$btnAlterarRfid = "&nbsp;&nbsp;<small style='color:red;'>(Ative o usuário para vincular crachá)</small>";
		}

		$camposImg = [
			$img,
			arquivo("Arquivo (.png, .jpg)", "foto", ($a_mod["enti_tx_foto"]?? ""), 4, "tabindex=".sprintf("%02d", $tabIndex++))
		];

		$statusOpt = ["ativo" => "Ativo", "inativo" => "Inativo"];
		$estadoCivilOpt = [
			"" => "Selecione", 
			"Casado(a)" => "Casado(a)", 
			"Solteiro(a)" => "Solteiro(a)", 
			"Divorciado(a)" => "Divorciado(a)", 
			"Viúvo(a)" => "Viúvo(a)"
		];

		$sexoOpt = ["" => "Selecione", "Feminino" => "Feminino", "Masculino" => "Masculino"];

		$camposUsuario = [
			campo("E-mail*", 				"email", 			($a_mod["enti_tx_email"]?? ""),			2, "", 					"tabindex=".sprintf("%02d", $tabIndex++)),
			campo("Telefone 1*", 			"fone1", 			($a_mod["enti_tx_fone1"]?? ""),			2, "MASCARA_CEL", 		"tabindex=".sprintf("%02d", $tabIndex++)),
			campo("Telefone 2",  			"fone2", 			($a_mod["enti_tx_fone2"]?? ""),			2, "MASCARA_CEL", 		"tabindex=".sprintf("%02d", $tabIndex++)),
			campo("Login",					"login", 			($a_mod["user_tx_login"]?? ""),			2, "", 					"tabindex=".sprintf("%02d", $tabIndex++)),
			combo("Status", 			"status", 			($a_mod["enti_tx_status"]?? ""),			1, $statusOpt, 			"tabindex=".sprintf("%02d", $tabIndex++)),
			texto("Crachá (RFID)".$btnAlterarRfid, $rfidTexto, 2, "")
		];

		$camposPessoais = [
			((!empty($_POST["id"]))?
				texto("Matrícula*", $a_mod["enti_tx_matricula"], 2, "tabindex=".sprintf("%02d", $tabIndex++)." maxlength=11"):
				campo("Matrícula*", "postMatricula", ($a_mod["enti_tx_matricula"]?? ""), 2, "", "tabindex=".sprintf("%02d", $tabIndex++)." maxlength=11")
			)
		];

		$estados = mysqli_fetch_all(query(
			"SELECT DISTINCT cida_tx_uf FROM cidade ORDER BY cida_tx_uf;"
		), MYSQLI_ASSOC);
		$aux = ["" => ""];
		foreach($estados as $estado){
			$aux[$estado["cida_tx_uf"]] = $estado["cida_tx_uf"];
		}
		$estados = $aux;

		$camposPessoais = array_merge($camposPessoais, [
			campo(	  	"Nome*", 				"nome", 			($a_mod["enti_tx_nome"]?? ""),			4, "",					"maxlength='65' tabindex=".sprintf("%02d", $tabIndex++)),
			campo_data(	"Nascido em*",	 		"nascimento", 		($a_mod["enti_tx_nascimento"]?? ""),	2, 						"tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"CPF*", 				"cpf", 				($a_mod["enti_tx_cpf"]?? ""),			2, "MASCARA_CPF", 		"tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"RG*", 					"rg", 				($a_mod["enti_tx_rg"]?? ""),			2, "MASCARA_RG", 		"maxlength='11' tabindex=".sprintf("%02d", $tabIndex++)),
			combo(		"Estado Civil", 		"civil", 			($a_mod["enti_tx_civil"]?? ""),			2, $estadoCivilOpt, 	"tabindex=".sprintf("%02d", $tabIndex++)),
			combo(		"Sexo", 				"sexo", 				($a_mod["enti_tx_sexo"]?? ""),			2, $sexoOpt, 			"tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"Emissor RG", 			"rgOrgao", 			($a_mod["enti_tx_rgOrgao"]?? ""),		3, "",					"maxlength='6' tabindex=".sprintf("%02d", $tabIndex++)),
			campo_data(	"Data Emissão RG", 		"rgDataEmissao", 	($a_mod["enti_tx_rgDataEmissao"]?? ""),	2, 						"tabindex=".sprintf("%02d", $tabIndex++)),
			combo(		"UF RG", 				"rgUf", 				($a_mod["enti_tx_rgUf"]?? ""),			2, getUFs(), 			"tabindex=".sprintf("%02d", $tabIndex++)),
            combo(		"Raça/Cor", 			"racaCor", 			($a_mod["enti_tx_racaCor"]?? ""),		2, [""=>"Selecione","B"=>"Branco","N"=>"Negro","P"=>"Pardo","I"=>"Indígena","A"=>"Amarelo"], "tabindex=".sprintf("%02d", $tabIndex++)),
            combo(		"Tipo Sanguíneo", 		"tipoSanguineo", 	($a_mod["enti_tx_tipoSanguineo"]?? ""), 2, [""=>"Selecione","A+"=>"A+","A-"=>"A-","B+"=>"B+","B-"=>"B-","AB+"=>"AB+","AB-"=>"AB-","O+"=>"O+","O-"=>"O-"], "tabindex=".sprintf("%02d", $tabIndex++)),
			"<div class='col-sm-2 margin-bottom-5' style='width:100%; height:25px'></div>",

			campo(	  	"CEP*", 				"cep", 				($a_mod["enti_tx_cep"]?? ""),			2, "MASCARA_CEP", 		"onfocusout='buscarCEP(this.value);' tabindex=".sprintf("%02d", $tabIndex++)),
			combo_net(	"Cidade/UF*", 			"cidade", 			($a_mod["enti_nb_cidade"]?? ""),		3, "cidade", 			"tabindex=".sprintf("%02d", $tabIndex++), "", "cida_tx_uf"),
			campo(	  	"Bairro*", 				"bairro", 			($a_mod["enti_tx_bairro"]?? ""),		2, "", 					"tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"Endereço*", 			"endereco", 		($a_mod["enti_tx_endereco"]?? ""),		3, "", 					"tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"Número", 				"numero", 			($a_mod["enti_tx_numero"]?? ""),		1, "MASCARA_NUMERO", 	"tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"Complemento", 			"complemento", 		($a_mod["enti_tx_complemento"]?? ""),	2, "", 					"tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"Ponto de Referência", 	"referencia", 		($a_mod["enti_tx_referencia"]?? ""),	3, "", 					"tabindex=".sprintf("%02d", $tabIndex++)),
			"<div class='col-sm-2 margin-bottom-5' style='width:100%; height:25px'></div>",

			campo(	  	"Filiação Pai", 		"pai", 				($a_mod["enti_tx_pai"]?? ""),			3, "", 					"maxlength='65' tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"Filiação Mãe", 		"mae", 				($a_mod["enti_tx_mae"]?? ""),			3, "", 					"maxlength='65' tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"Nome do Cônjuge",	 	"conjugue", 		($a_mod["enti_tx_conjugue"]?? ""),		3, "", 					"maxlength='65' tabindex=".sprintf("%02d", $tabIndex++)),
			
			textarea(	"Observações:", "obs", ($a_mod["enti_tx_obs"]?? ""), 12, "tabindex=".sprintf("%02d", $tabIndex++))
		]);
		
		//Se tiver permissão, esquece isso de administrador ou super
		$extraEmpresa = "";
		
		
		$a_mod["enti_nb_salario"] = str_replace(".", ",", (!empty($a_mod["enti_nb_salario"])? $a_mod["enti_nb_salario"] : ""));
		$campoSalario = campo("Salário*", "salario", $a_mod["enti_nb_salario"], 1, "MASCARA_DINHEIRO", "tabindex=".sprintf("%02d", $tabIndex+2));

        $condSubSetor = " ORDER BY sbgr_tx_nome ASC";
        if (!empty($a_mod["enti_setor_id"]) || !empty($_POST["setor"])) {
            $idSetorRef = (!empty($_POST["setor"]) ? intval($_POST["setor"]) : intval($a_mod["enti_setor_id"]));
            $condSubSetor = " AND sbgr_nb_idgrup = ".$idSetorRef." ORDER BY sbgr_tx_nome ASC";
        }

		$cContratual = [
			combo_bd("Empresa*", "empresa", ($a_mod["enti_nb_empresa"]?? $_SESSION["user_nb_empresa"]), 3, "empresa", "onchange='carregarEmpresa(this.value)' tabindex=".sprintf("%02d", $tabIndex++), $extraEmpresa),
			combo_bd("Setor", "setor", ($a_mod["enti_setor_id"]?? ""), 3, "grupos_documentos", "id='setor' onchange='filtrarSubSetor(this.value)' tabindex=".sprintf("%02d", $tabIndex++)),
			combo_bd("Subsetor", "subSetor", ($a_mod["enti_subSetor_id"]?? ""), 3, "sbgrupos_documentos", "tabindex=".sprintf("%02d", $tabIndex++), $condSubSetor),
			combo_bd("!Cargo", "tipoOperacao", (isset($a_mod["enti_tx_tipoOperacao"])? $a_mod["enti_tx_tipoOperacao"]: ""), 3, "operacao", "id='tipoOperacao' tabindex=".sprintf("%02d", $tabIndex++)),
			$campoSalario
		];
		$tabIndex++;

		$preRespSetorIds = $_POST["respSetor"] ?? [];
		$preRespSetorIds = is_array($preRespSetorIds) ? $preRespSetorIds : [$preRespSetorIds];
		$preRespSetorIds = array_values(array_unique(array_filter(array_map("intval", $preRespSetorIds), fn($v) => $v > 0)));
		if(empty($preRespSetorIds)){
			$csv = trim(strval($a_mod["enti_respSetor_ids"] ?? ""));
			if($csv !== ""){
				foreach(explode(",", $csv) as $p){
					$v = intval(trim($p));
					if($v > 0){ $preRespSetorIds[] = $v; }
				}
			}
			$preRespSetorIds = array_values(array_unique(array_filter(array_map("intval", $preRespSetorIds), fn($v) => $v > 0)));
		}

		$preRespCargoIds = $_POST["respCargo"] ?? [];
		$preRespCargoIds = is_array($preRespCargoIds) ? $preRespCargoIds : [$preRespCargoIds];
		$preRespCargoIds = array_values(array_unique(array_filter(array_map("intval", $preRespCargoIds), fn($v) => $v > 0)));
		if(empty($preRespCargoIds)){
			$csv = trim(strval($a_mod["enti_respCargo_ids"] ?? ""));
			if($csv !== ""){
				foreach(explode(",", $csv) as $p){
					$v = intval(trim($p));
					if($v > 0){ $preRespCargoIds[] = $v; }
				}
			}
			$preRespCargoIds = array_values(array_unique(array_filter(array_map("intval", $preRespCargoIds), fn($v) => $v > 0)));
		}

		$respSetorOptions = "";
		if(!empty($preRespSetorIds)){
			$idsSql = implode(",", $preRespSetorIds);
			$rows = mysqli_fetch_all(query(
				"SELECT
					e.enti_nb_id AS id,
					e.enti_tx_nome AS nome,
					e.enti_tx_email AS email,
					g.grup_tx_nome AS setor_nome,
					o.oper_tx_nome AS cargo_nome
				FROM entidade e
				LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
				LEFT JOIN operacao o ON o.oper_nb_id = e.enti_tx_tipoOperacao
				WHERE e.enti_nb_id IN ($idsSql)
				ORDER BY e.enti_tx_nome ASC"
			), MYSQLI_ASSOC);
			foreach(($rows ?: []) as $r){
				$idResp = intval($r["id"] ?? 0);
				if($idResp <= 0){ continue; }
				$nome = strval($r["nome"] ?? "");
				$email = strval($r["email"] ?? "");
				$setorNome = strval($r["setor_nome"] ?? "");
				$cargoNome = strval($r["cargo_nome"] ?? "");
				$label = $nome !== "" ? $nome : ("ID " . $idResp);
				if($setorNome !== ""){ $label .= " - S: ".$setorNome; }
				if($cargoNome !== ""){ $label .= " - C: ".$cargoNome; }
				if($email !== ""){ $label .= " | ".$email; }
				$respSetorOptions .= "<option value='".$idResp."' selected>".htmlspecialchars($label, ENT_QUOTES, "UTF-8")."</option>";
			}
		}

		$respCargoOptions = "";
		if(!empty($preRespCargoIds)){
			$idsSql = implode(",", $preRespCargoIds);
			$rows = mysqli_fetch_all(query(
				"SELECT
					e.enti_nb_id AS id,
					e.enti_tx_nome AS nome,
					e.enti_tx_email AS email,
					g.grup_tx_nome AS setor_nome,
					o.oper_tx_nome AS cargo_nome
				FROM entidade e
				LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
				LEFT JOIN operacao o ON o.oper_nb_id = e.enti_tx_tipoOperacao
				WHERE e.enti_nb_id IN ($idsSql)
				ORDER BY e.enti_tx_nome ASC"
			), MYSQLI_ASSOC);
			foreach(($rows ?: []) as $r){
				$idResp = intval($r["id"] ?? 0);
				if($idResp <= 0){ continue; }
				$nome = strval($r["nome"] ?? "");
				$email = strval($r["email"] ?? "");
				$setorNome = strval($r["setor_nome"] ?? "");
				$cargoNome = strval($r["cargo_nome"] ?? "");
				$label = $nome !== "" ? $nome : ("ID " . $idResp);
				if($setorNome !== ""){ $label .= " - S: ".$setorNome; }
				if($cargoNome !== ""){ $label .= " - C: ".$cargoNome; }
				if($email !== ""){ $label .= " | ".$email; }
				$respCargoOptions .= "<option value='".$idResp."' selected>".htmlspecialchars($label, ENT_QUOTES, "UTF-8")."</option>";
			}
		}

		$cResponsaveis = [
			"<div class='col-sm-6 margin-bottom-5 campo-fit-content'>
				<label class='control-label'>Responsáveis do Setor</label>
				<select class='form-control input-sm resp-setor' name='respSetor[]' multiple='multiple' style='width: 100%;'>".$respSetorOptions."</select>
				<div class='help-block'>Lista todos os responsáveis cadastrados no setor selecionado. Marque quais serão responsáveis deste funcionário.</div>
			</div>",
			"<div class='col-sm-6 margin-bottom-5 campo-fit-content'>
				<label class='control-label'>Responsáveis do Cargo</label>
				<select class='form-control input-sm resp-cargo' name='respCargo[]' multiple='multiple' style='width: 100%;'>".$respCargoOptions."</select>
				<div class='help-block'>Lista todos os responsáveis cadastrados no cargo selecionado. Marque quais serão responsáveis deste funcionário.</div>
			</div>",
			"<script>
				(function(){
					function init(){
						if(!(window.jQuery && jQuery.fn && jQuery.fn.select2)){
							setTimeout(init, 200);
							return;
						}
						var \$setorSel = jQuery('.resp-setor');
						var \$cargoSel = jQuery('.resp-cargo');
						if(\$setorSel.length && !\$setorSel.data('select2')){
							\$setorSel.select2({ theme: 'bootstrap', width: '100%', placeholder: 'Selecione', allowClear: true });
						}
						if(\$cargoSel.length && !\$cargoSel.data('select2')){
							\$cargoSel.select2({ theme: 'bootstrap', width: '100%', placeholder: 'Selecione', allowClear: true });
						}

						function optionMap(\$sel){
							var m = {};
							\$sel.find('option').each(function(){
								var v = String(this.value || '');
								if(v){ m[v] = String(jQuery(this).text() || ''); }
							});
							return m;
						}

						function selectedSet(\$sel){
							var a = \$sel.val() || [];
							var set = {};
							a.forEach(function(v){ set[String(v)] = true; });
							return set;
						}

						function repopular(\$sel, items){
							items = Array.isArray(items) ? items : [];
							var oldMap = optionMap(\$sel);
							var selSet = selectedSet(\$sel);
							\$sel.empty();
							items.forEach(function(it){
								if(!it || it.id === undefined || it.id === null){ return; }
								var id = String(it.id);
								var text = (it.text !== undefined && it.text !== null) ? String(it.text) : id;
								var opt = new Option(text, id, false, !!selSet[id]);
								\$sel.append(opt);
								delete oldMap[id];
							});
							Object.keys(oldMap).forEach(function(id){
								if(!selSet[id]){ return; }
								var opt = new Option(oldMap[id] || ('ID ' + id), id, false, true);
								\$sel.append(opt);
							});
							\$sel.trigger('change.select2');
						}

						function carregar(tipo, id, limpar){
							var \$sel = (tipo === 'setor') ? \$setorSel : \$cargoSel;
							if(limpar){
								try{ \$sel.val(null); }catch(e){}
							}
							id = parseInt(String(id || '0'), 10);
							if(!id || id <= 0){
								repopular(\$sel, []);
								return;
							}
							var url = (tipo === 'setor')
								? ('cadastro_setor.php?acao=api_responsaveis_setor&setor_id=' + id)
								: ('cadastro_operacao.php?acao=api_responsaveis_cargo&cargo_id=' + id);
							fetch(url, { credentials: 'same-origin' })
								.then(function(r){ return r.json(); })
								.then(function(data){
									repopular(\$sel, data);
								})
								.catch(function(){
									repopular(\$sel, []);
								});
						}

						var setorEl = document.getElementsByName('setor')[0];
						var cargoEl = document.getElementsByName('tipoOperacao')[0];
						if(setorEl){
							setorEl.addEventListener('change', function(){ carregar('setor', this.value, true); });
							if(setorEl.value){ carregar('setor', setorEl.value, false); }
						}
						if(cargoEl){
							cargoEl.addEventListener('change', function(){ carregar('cargo', this.value, true); });
							if(cargoEl.value){ carregar('cargo', cargoEl.value, false); }
						}
					}
					init();
				})();
			</script>"
		];

		$respModalHtml = "
			<div class='modal fade' id='modal_responsaveis' tabindex='-1' role='dialog' aria-hidden='true'>
				<div class='modal-dialog modal-lg'>
					<div class='modal-content'>
						<div class='modal-header'>
							<button type='button' class='close' data-dismiss='modal' aria-hidden='true'></button>
							<h4 class='modal-title' id='modal_responsaveis_titulo'></h4>
						</div>
						<div class='modal-body'>
							<div id='modal_responsaveis_lista'></div>
							<div class='help-block'>Para alterar os responsáveis, faça no cadastro de Setor ou no cadastro de Cargo.</div>
						</div>
						<div class='modal-footer'>
							<button type='button' class='btn default' data-dismiss='modal'>Fechar</button>
							<a class='btn btn-primary' id='modal_responsaveis_link' href='cadastro_setor.php' target='_blank'>Abrir cadastro</a>
						</div>
					</div>
				</div>
			</div>
		";
		$respModalJs = "";

		$cContratual = array_merge($cContratual, [
			//dropbox da ocupação 
			combo(		"Ocupação*", 		"ocupacao", 		(!empty($a_mod["enti_tx_ocupacao"])? $a_mod["enti_tx_ocupacao"]	:""), 		2, ["" => "Selecione", "Motorista" => "Motorista", "Ajudante" => "Ajudante", "Funcionário" => "Funcionário", "Terceirizado" => "Terceirizado"], "tabindex=".sprintf("%02d", $tabIndex++)." onchange=checkOcupation(this.value)"),
			campo_data(	"Dt Admissão*", 	"admissao", 		(!empty($a_mod["enti_tx_admissao"])? $a_mod["enti_tx_admissao"]		 	:""), 		2, "tabindex=".sprintf("%02d", $tabIndex++)),
			campo_data(	"Dt. Desligamento", "desligamento", 	(!empty($a_mod["enti_tx_desligamento"])? $a_mod["enti_tx_desligamento"] 	:""), 		2, "tabindex=".sprintf("%02d", $tabIndex++)),
			campo(		"Saldo de Horas", 	"setBanco", 		(!empty($a_mod["enti_tx_banco"])? $a_mod["enti_tx_banco"] 				:"00:00"), 	1, "MASCARA_HORAS", "placeholder='HH:mm' tabindex=".sprintf("%02d", $tabIndex++)),
			combo(		"Subcontratado", 	"subcontratado", 	(!empty($a_mod["enti_tx_subcontratado"])? $a_mod["enti_tx_subcontratado"] 	:""), 		2, ["" => "Selecione", "sim" => "Sim", "nao" => "Não"], "tabindex=".sprintf("%02d", $tabIndex++)),
		]);

        $cContratual = array_merge($cContratual, [
            campo(        "PIS",                   "pis",                 ($a_mod["enti_tx_pis"]?? ""),                 2, "MASCARA_NUMERO", "maxlength='11' tabindex=".sprintf("%02d", $tabIndex++)),
            campo(        "CTPS Número",           "ctpsNumero",         ($a_mod["enti_tx_ctpsNumero"]?? ""),         2, "MASCARA_NUMERO", "maxlength='8' tabindex=".sprintf("%02d", $tabIndex++)),
            campo(        "CTPS Série",           "ctpsSerie",         ($a_mod["enti_tx_ctpsSerie"]?? ""),         2, "MASCARA_NUMERO", "maxlength='4' tabindex=".sprintf("%02d", $tabIndex++)),
            combo(        "CTPS UF",               "ctpsUf",             ($a_mod["enti_tx_ctpsUf"]?? ""),             2, getUFs(),          "tabindex=".sprintf("%02d", $tabIndex++)),
            campo(        "Título de Eleitor Número", "tituloNumero",   ($a_mod["enti_tx_tituloNumero"]?? ""),     2, "MASCARA_NUMERO", "maxlength='12' tabindex=".sprintf("%02d", $tabIndex++)),
            campo(        "Título Zona",           "tituloZona",         ($a_mod["enti_tx_tituloZona"]?? ""),         2, "MASCARA_NUMERO", "tabindex=".sprintf("%02d", $tabIndex++)),
            campo(        "Título Seção",           "tituloSecao",         ($a_mod["enti_tx_tituloSecao"]?? ""),         2, "MASCARA_NUMERO", "tabindex=".sprintf("%02d", $tabIndex++)),
            campo(        "Reservista",           "reservista",         ($a_mod["enti_tx_reservista"]?? ""),         2, "MASCARA_NUMERO", "tabindex=".sprintf("%02d", $tabIndex++)),
            campo(        "Registro Funcional",   "registroFuncional",  ($a_mod["enti_tx_registroFuncional"]?? ""),  2, "MASCARA_NUMERO", "tabindex=".sprintf("%02d", $tabIndex++)),
            campo(        "Orgão Registro Funcional",   "orgaoRegimeFuncional",  ($a_mod["enti_tx_OrgaoRegimeFuncional"]?? ""),  2, "", "maxlength='150' tabindex=".sprintf("%02d", $tabIndex++)),
            campo_data(   "Vencimento Registro",  "vencimentoRegistro", ($a_mod["enti_tx_vencimentoRegistro"]?? ""), 2,                     "tabindex=".sprintf("%02d", $tabIndex++))
        ]);

		$conferirPadraoJS = "";
		if (!empty($a_mod["enti_nb_empresa"])){
			$icone_padronizar = "<a id='padronizarParametro' style='text-shadow: none; color: #337ab7;' onclick='javascript:padronizarParametro();' title='Utilizar parâmetro padrão da empresa.' > (Padronizar) </a>";
			$conferirPadraoJS = "conferirParametroPadrao();";
		}

		$parametros = mysqli_fetch_all(query(
			"SELECT para_nb_id, para_tx_nome FROM parametro WHERE para_tx_status = 'ativo';"
		), MYSQLI_ASSOC);

		$aux = ["" => "Selecione"];
		foreach($parametros as $parametro){
			$aux[strval($parametro["para_nb_id"])] = $parametro["para_tx_nome"];
		}
		$parametros = $aux;


		// NÃO ESTÁ CONSEGUINDO ATUALIZAR COM A FUNÇÃO carregarParametro()
		$cJornada = [
			"<div style='overflow: hidden;'>"
				.combo("Parâmetros da Jornada".($icone_padronizar?? ""), "parametro", ($a_mod["enti_nb_parametro"]?? ""), 6, $parametros, "onchange='carregarParametro()' tabindex=".sprintf("%02d", $tabIndex++))
			."</div>",
			// combo_bd(	"!Parâmetros da Jornada*".($icone_padronizar?? ""), "parametro", ($a_mod["enti_nb_parametro"]?? ""), 6, "parametro", "onchange='carregarParametro()' tabindex=".sprintf("%02d", $tabIndex++)), 
			
			// combo_bd2(
			// 	"Parâmetros da Jornada*".($icone_padronizar?? ""), 
			// 	"parametro", 
			// 	($a_mod["enti_nb_parametro"]?? ""), 
			// 	"SELECT para_nb_id as 'value', para_tx_nome as 'text' FROM parametro WHERE para_tx_status = 'ativo'", 
			// 	"col-sm-6 margin-bottom-5 campo-fit-content",
			// 	"form-control input-sm campo-fit-content", 
			// 	"onchange='carregarParametro()' tabindex=".sprintf("%02d", $tabIndex++),
			// 	[
			// 		["value" => "", "text" => "Selecione", "props" => "disabled"]
			// 	]
			// ),
			texto(		"Escala", ($a_mod["textoEscala"]?? ""), 4, "name='textoEscala' style='display:none;'"),
			"<div name='divJornada' style='margin: 15px; width: fit-content; overflow: hidden;'>"
				."<div style='font-weight: bold;'>Jornada</div>"
				.campo_hora(	"Dias Úteis (Hr/dia)*", "jornadaSemanal", ($a_mod["enti_tx_jornadaSemanal"]?? ""), 2, "tabindex=".sprintf("%02d", $tabIndex++)." onchange='{$conferirPadraoJS}'")
				.campo_hora(	"Sábado*", "jornadaSabado", ($a_mod["enti_tx_jornadaSabado"]?? ""), 2, "tabindex=".sprintf("%02d", $tabIndex++)." onchange='{$conferirPadraoJS}'")
			."</div>",
			campo(		"H.E. Semanal (%)*", "percHESemanal", ($a_mod["enti_tx_percHESemanal"]?? ""), 2, "MASCARA_NUMERO", "tabindex=".sprintf("%02d", $tabIndex++)." onchange='{$conferirPadraoJS}'"),
			campo(		"H.E. Extraordinária (%)*", "percHEEx", ($a_mod["enti_tx_percHEEx"]?? ""), 2, "MASCARA_NUMERO", "tabindex=".sprintf("%02d", $tabIndex++)." onchange='{$conferirPadraoJS}'")
		];
		
		$cJornada[]=texto("Convenção Padrão?", "...", 2, "name='textoParametroPadrao'");
		$iconeExcluirCNH = "";
		if (!empty($a_mod["enti_tx_cnhAnexo"])){
			$iconeExcluirCNH = "<a style='text-shadow: none; color: #337ab7;' onclick='javascript:remover_cnh(\"".$a_mod["enti_nb_id"]."\",\"excluirCNH\",\"\",\"\",\"\",\"Deseja excluir a CNH?\");' > (Excluir) </a>";
		}

		$camposCNH = [
			campo("N° Registro*", "cnhRegistro", ($a_mod["enti_tx_cnhRegistro"]?? ""), 3,"","maxlength='11' tabindex=".sprintf("%02d", $tabIndex++)),
			campo("Categoria*", "cnhCategoria", ($a_mod["enti_tx_cnhCategoria"]?? ""), 3, "", "tabindex=".sprintf("%02d", $tabIndex++)),
			combo_net("Cidade/UF Emissão*", "cnhCidade", ($a_mod["enti_nb_cnhCidade"]?? ""), 3, "cidade", "tabindex=".sprintf("%02d", $tabIndex++), "", "cida_tx_uf"),
			campo_data("Data Emissão*", "cnhEmissao", ($a_mod["enti_tx_cnhEmissao"]?? ""), 3, "tabindex=".sprintf("%02d", $tabIndex++)),
			campo_data("Validade*", "cnhValidade", ($a_mod["enti_tx_cnhValidade"]?? ""), 3, "tabindex=".sprintf("%02d", $tabIndex++)),
			campo_data("1º Habilitação*", "cnhPrimeiraHabilitacao", ($a_mod["enti_tx_cnhPrimeiraHabilitacao"]?? ""), 3, "tabindex=".sprintf("%02d", $tabIndex++)),
			campo("Permissão", "cnhPermissao", ($a_mod["enti_tx_cnhPermissao"]?? ""), 3,"","maxlength='65' tabindex=".sprintf("%02d", $tabIndex++)),
			campo("Pontuação", "cnhPontuacao", ($a_mod["enti_tx_cnhPontuacao"]?? ""), 3,"","maxlength='3' tabindex=".sprintf("%02d", $tabIndex++)),
			combo("Atividade Remunerada", "cnhAtividadeRemunerada", ($a_mod["enti_tx_cnhAtividadeRemunerada"]?? ""), 3, ["" => "Selecione", "sim" => "Sim", "nao" => "Não"], "tabindex=".sprintf("%02d", $tabIndex++)),
			arquivo("CNH (.png, .jpg, .pdf)".$iconeExcluirCNH, "cnhAnexo", ($a_mod["enti_tx_cnhAnexo"]?? ""), 4, "tabindex=".sprintf("%02d", $tabIndex++)),
			campo("Observações", "cnhObs", ($a_mod["enti_tx_cnhObs"]?? ""), 3,"","maxlength='500' tabindex=".sprintf("%02d", $tabIndex++))
		];


		$botoesCadastro[] = botao(
			"Gravar", 
			"cadastrarMotorista", 
			((empty($_POST["id"]) || empty($a_mod["enti_tx_matricula"]))? "": "id,matricula"),
			((empty($_POST["id"]) || empty($a_mod["enti_tx_matricula"]))? "": $_POST["id"].",".$a_mod["enti_tx_matricula"]),
			"tabindex=".sprintf("%02d", $tabIndex++),
			"",
			"btn btn-success"
		);

		$botoesCadastro[] = criarBotaoVoltar(null, null, "tabindex=".sprintf("%02d", $tabIndex++));

		if (!empty($_POST["id"])) {
			$botoesCadastro[] = '<button class="btn default" type="button" onclick="imprimir()">Imprimir</button>';
		}

		echo abre_form();
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		fieldset("Dados de Usuário");
		echo linha_form($camposUsuario);
		echo "<br>";
		fieldset("Dados Pessoais");
		echo linha_form($camposPessoais);
		echo "<br>";
		fieldset("Foto");
		echo "<div class='imageForm'>";
		echo linha_form($camposImg);
		echo "</div>";
		echo "<br>";
		fieldset("Dados Contratuais");
		echo linha_form($cContratual);
		echo "<br>";
		fieldset("Responsáveis");
		echo linha_form($cResponsaveis);
		echo $respModalHtml.$respModalJs;
		echo "<br>";
		fieldset("CONVENÇÃO SINDICAL - JORNADA PADRÃO DO FUNCIONÁRIO");
		echo linha_form($cJornada);
		echo "<br>";
		echo "<div class='cnh-row'>";
			fieldset("CARTEIRA NACIONAL DE HABILITAÇÃO");
			echo linha_form($camposCNH);
		echo "</div>";

		if (!empty($a_mod["enti_nb_userCadastro"])) {
			$a_userCadastro = carregar("user", $a_mod["enti_nb_userCadastro"]);
			$txtCadastro = "Registro inserido por $a_userCadastro[user_tx_login] às ".data($a_mod["enti_tx_dataCadastro"]).".";
			$cAtualiza[] = texto("Data de Cadastro", "$txtCadastro", 5);
			if ($a_mod["enti_nb_userAtualiza"] > 0) {
				$a_userAtualiza = carregar("user", $a_mod["enti_nb_userAtualiza"]);
				$txtAtualiza = "Registro atualizado por $a_userAtualiza[user_tx_login] às ".data($a_mod["enti_tx_dataAtualiza"], 1).".";
				$cAtualiza[] = texto("Última Atualização", strval($txtAtualiza), 5);
			}
			echo "<br>";
			echo linha_form($cAtualiza);
		}

		echo "<iframe id=frame_parametro style='display: none;'></iframe>";

		echo fecha_form($botoesCadastro);

		if (!empty($a_mod["enti_nb_id"])) {
			$arquivos = mysqli_fetch_all(query(
				"SELECT 
				documento_funcionario.docu_nb_id,
				documento_funcionario.docu_nb_entidade,
				documento_funcionario.docu_tx_dataCadastro,
				documento_funcionario.docu_tx_dataVencimento,
				documento_funcionario.docu_tx_caminho,
				documento_funcionario.docu_tx_descricao,
				documento_funcionario.docu_tx_nome,
				documento_funcionario.docu_tx_visivel,
				documento_funcionario.docu_tx_assinado,
				documento_funcionario.docu_tx_tipo,
				documento_funcionario.docu_nb_sbgrupo,
				t.tipo_nb_id,
				t.tipo_tx_nome,
				gd.grup_nb_id,
				gd.grup_tx_nome,
				subg.sbgr_nb_id,
				subg.sbgr_tx_nome
				FROM documento_funcionario
				LEFT JOIN tipos_documentos t 
				ON documento_funcionario.docu_tx_tipo = t.tipo_nb_id
				LEFT JOIN grupos_documentos gd 
				ON t.tipo_nb_grupo = gd.grup_nb_id
				LEFT JOIN sbgrupos_documentos subg
				ON subg.sbgr_nb_id = documento_funcionario.docu_nb_sbgrupo
				WHERE documento_funcionario.docu_nb_entidade = ".$a_mod["enti_nb_id"]
			),MYSQLI_ASSOC);
			echo "</div><div class='col-md-12'><div class='col-md-12 col-sm-12'>".arquivosFuncionario("Documentos", $a_mod["enti_nb_id"], $arquivos);
		}
		rodape();
		
		echo 
			"<form method='post' name='form_modifica' id='form_modifica'>
				<input type='hidden' name='id' value=''>
				<input type='hidden' name='acao' value='modificarMotorista'>
			</form>"
		;

		carregarJS();
	}

	function normalizarTextoCsv(string $txt): string {
		$txt = trim((string)$txt);
		if($txt === ""){
			return "";
		}
		$txt = @mb_convert_encoding($txt, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
		$txt = mb_strtolower($txt, 'UTF-8');
		$conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
		if($conv !== false){
			$txt = $conv;
		}
		$txt = preg_replace('/[^a-z0-9]+/', '_', $txt);
		return trim((string)$txt, '_');
	}

	function detectarDelimitadorCsv(string $linhaCabecalho): string {
		$contagens = [
			';' => substr_count($linhaCabecalho, ';'),
			',' => substr_count($linhaCabecalho, ','),
			"\t" => substr_count($linhaCabecalho, "\t")
		];
		arsort($contagens);
		$delim = array_key_first($contagens);
		return (!empty($delim) ? $delim : ';');
	}

	function obterCabecalhoModeloCsvFuncionario(): array {
		return [
			"Email*",
			"Telefone 1*",
			"Telefone 2",
			"Login",
			"Status",
			"Matrícula*",
			"Nome*",
			"Nascido em*",
			"CPF*",
			"RG*",
			"Sexo",
			"Emissor RG",
			"UF RG",
			"CEP*",
			"Cidade/UF*",
			"UF",
			"Bairro*",
			"Endereço*",
			"Número",
			"Complemento",
			"Empresa",
			"Setor",
			"Sub Setor",
			"Cargo",
			"Salário*",
			"Ocupação*",
			"Dt Admissão",
			"Subcontratado",
			"Órgão Registro Funcional",
			"Registro Funcional",
			"Parametros da Jornada Escala*",
			"cidade_uf",
			"estado_civil",
			"raca_cor",
			"tipo_sanguineo",
			"data_emissao_rg",
			"referencia",
			"pai",
			"mae",
			"conjugue",
			"obs",
			"saldo",
			"dt_admissao*",
			"dt_desligamento",
			"parametro_jornada*",
			"jornada_semanal",
			"jornada_sabado",
			"he_semanal_percentual*",
			"he_extra_percentual*",
			"pis",
			"ctps_numero",
			"ctps_serie",
			"ctps_uf",
			"titulo_numero",
			"titulo_zona",
			"titulo_secao",
			"reservista",
			"registro_funcional",
			"orgao_regime_funcional",
			"vencimento_registro",
			"resp_setor_ids",
			"resp_cargo_ids",
			"cnh_registro",
			"cnh_categoria",
			"cnh_cidade",
			"cnh_emissao",
			"cnh_validade",
			"cnh_primeira_habilitacao",
			"cnh_permissao",
			"cnh_pontuacao",
			"cnh_atividade_remunerada",
			"cnh_obs"
		];
	}

	function baixarModeloCsvFuncionarios(){
		$header = obterCabecalhoModeloCsvFuncionario();
		$nomeArquivo = "modelo_importacao_funcionarios.csv";

		header("Content-Type: text/csv; charset=UTF-8");
		header("Content-Disposition: attachment; filename=\"{$nomeArquivo}\"");
		header("Pragma: no-cache");
		header("Expires: 0");

		$out = fopen("php://output", "w");
		fwrite($out, "\xEF\xBB\xBF");
		fputcsv($out, $header, ';');
		fclose($out);
		exit;
	}

	function uploadCsvFuncionarios(){
		if(empty($_FILES["arquivo_csv_funcionarios"]["tmp_name"])){
			set_status("ERRO: Selecione um arquivo CSV para upload.");
			index();
			exit;
		}

		$tmp = $_FILES["arquivo_csv_funcionarios"]["tmp_name"];
		$nome = $_FILES["arquivo_csv_funcionarios"]["name"] ?? "";
		if(strtolower(pathinfo($nome, PATHINFO_EXTENSION)) !== "csv"){
			set_status("ERRO: Arquivo inválido. Envie um arquivo .csv.");
			index();
			exit;
		}

		$fpRaw = fopen($tmp, "r");
		if(!$fpRaw){
			set_status("ERRO: Não foi possível abrir o CSV enviado.");
			index();
			exit;
		}
		$linhaCabecalhoRaw = (string)fgets($fpRaw);
		fclose($fpRaw);
		if(trim($linhaCabecalhoRaw) === ""){
			set_status("ERRO: CSV vazio ou sem cabeçalho.");
			index();
			exit;
		}

		$delim = detectarDelimitadorCsv($linhaCabecalhoRaw);
		$fp = fopen($tmp, "r");
		$cab = fgetcsv($fp, 0, $delim);
		if($cab === false || count($cab) <= 1){
			rewind($fp);
			$cab = fgetcsv($fp, 0, ';');
			$delim = ';';
		}
		if($cab === false || count($cab) <= 1){
			rewind($fp);
			$cab = fgetcsv($fp, 0, ',');
			$delim = ',';
		}
		if(isset($cab[0])){
			$cab[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$cab[0]);
		}
		if($cab === false || !is_array($cab)){
			fclose($fp);
			set_status("ERRO: Não foi possível ler o cabeçalho do CSV.");
			index();
			exit;
		}

		$idx = [];
		foreach($cab as $i => $col){
			$key = normalizarTextoCsv((string)$col);
			if($key !== '' && !isset($idx[$key])){
				$idx[$key] = $i;
			}
		}

		$findIdx = function(array $aliases) use ($idx): ?int {
			foreach($aliases as $a){
				$k = normalizarTextoCsv($a);
				if(isset($idx[$k])){
					return $idx[$k];
				}
			}
			return null;
		};

		$findIdxByTokens = function(array $tokenSets) use ($idx): ?int {
			foreach($idx as $k => $iCol){
				$compact = str_replace('_', '', (string)$k);
				foreach($tokenSets as $tokens){
					$ok = true;
					foreach($tokens as $t){
						if(strpos($compact, $t) === false){
							$ok = false;
							break;
						}
					}
					if($ok){
						return $iCol;
					}
				}
			}
			return null;
		};

		$col = [
			"email" => $findIdx(["email"]),
			"telefone_1" => $findIdx(["telefone 1", "telefone_1", "fone1"]),
			"telefone_2" => $findIdx(["telefone 2", "telefone_2", "fone2"]),
			"matricula" => ($findIdx(["matricula", "matrícula", "matrcula"]) ?? $findIdxByTokens([["matr","cula"],["matric"]])),
			"nome" => $findIdx(["nome"]),
			"nascido_em" => $findIdx(["nascido em", "nascido_em"]),
			"cpf" => $findIdx(["cpf"]),
			"rg" => $findIdx(["rg"]),
			"cep" => $findIdx(["cep"]),
			"cod_ibge" => $findIdx(["cod ibge", "cod_ibge", "ibge"]),
			"cidade_uf" => $findIdx(["cidade/uf", "cidade_uf", "cidade"]),
			"uf" => $findIdx(["uf"]),
			"bairro" => $findIdx(["bairro"]),
			"endereco" => $findIdx(["endereco"]),
			"empresa" => $findIdx(["empresa"]),
			"salario" => ($findIdx(["salario", "salário", "salrio"]) ?? $findIdxByTokens([["sal","rio"],["salar"]])),
			"ocupacao" => ($findIdx(["ocupacao", "ocupação", "ocupao"]) ?? $findIdxByTokens([["ocup"]])),
			"parametro_jornada_escala" => $findIdx(["parametros da jornada escala", "parametros_da_jornada_escala"]),
			"parametro_jornada" => $findIdx(["parametro_jornada"]),
			"dt_admissao" => $findIdx(["dt admissao", "dt_admissao"]),
			"status" => $findIdx(["status"]),
			"jornada_semanal" => $findIdx(["jornada semanal", "jornada_semanal"]),
			"jornada_sabado" => $findIdx(["jornada sabado", "jornada_sabado"]),
			"he_semanal" => $findIdx(["he % semanal", "he semanal percentual", "he_semanal_percentual"]),
			"he_extra" => $findIdx(["he % extra", "he extra percentual", "he_extra_percentual"]),
			"login" => $findIdx(["login"]),
			"setor" => $findIdx(["setor"]),
			"subsetor" => $findIdx(["subsetor", "sub setor"]),
			"cargo" => $findIdx(["cargo"])
		];

		$required = ["email","telefone_1","matricula","nome","nascido_em","cpf","rg","cep","bairro","endereco","salario","ocupacao","dt_admissao"];
		$missingCols = [];
		foreach($required as $rk){
			if($col[$rk] === null){
				$missingCols[] = $rk;
			}
		}
		if($col["parametro_jornada"] === null && $col["parametro_jornada_escala"] === null){
			$missingCols[] = "parametro_jornada";
		}
		if(!empty($missingCols)){
			fclose($fp);
			set_status("ERRO: Colunas obrigatórias ausentes no CSV: ".implode(", ", $missingCols).".");
			index();
			exit;
		}

		$get = function(array $row, ?int $i): string {
			if($i === null){
				return "";
			}
			return trim((string)($row[$i] ?? ""));
		};
		$toDate = function(string $v): ?string {
			$v = trim($v);
			if($v === "") return null;
			if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
			if(preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $v)){
				[$d,$m,$y] = explode('/', $v);
				if(checkdate((int)$m, (int)$d, (int)$y)) return sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
			}
			return null;
		};
		$toDecimal = function(string $v): ?string {
			$v = trim($v);
			if($v === "") return null;
			$v = str_replace(["R$"," "], "", $v);
			$v = str_replace('.', '', $v);
			$v = str_replace(',', '.', $v);
			return (is_numeric($v) ? (string)$v : null);
		};

		$resolveCidade = function(string $codIbge, string $cidadeUf, string $uf) {
			$codIbge = trim($codIbge);
			if($codIbge !== "" && ctype_digit($codIbge)){
				$r = mysqli_fetch_assoc(query("SELECT cida_nb_id FROM cidade WHERE cida_nb_id = ? LIMIT 1", "i", [intval($codIbge)]));
				if(!empty($r["cida_nb_id"])) return intval($r["cida_nb_id"]);
			}
			$cidadeUf = trim($cidadeUf);
			$uf = strtoupper(trim($uf));
			if($cidadeUf === "") return null;
			if(strpos($cidadeUf, '/') !== false){
				[$cidadeTxt, $ufTxt] = array_map('trim', explode('/', $cidadeUf, 2));
				$uf = strtoupper($ufTxt);
			}else{
				$cidadeTxt = $cidadeUf;
			}
			if($cidadeTxt === "") return null;

			if($uf !== ""){
				$r = mysqli_fetch_assoc(query("SELECT cida_nb_id FROM cidade WHERE cida_tx_status = 'ativo' AND cida_tx_nome = ? AND cida_tx_uf = ? LIMIT 1", "ss", [$cidadeTxt, $uf]));
				if(!empty($r["cida_nb_id"])) return intval($r["cida_nb_id"]);
			}
			$res = query("SELECT cida_nb_id, cida_tx_nome FROM cidade WHERE cida_tx_status = 'ativo'".($uf !== "" ? " AND cida_tx_uf = ?" : ""), ($uf !== "" ? "s" : ""), ($uf !== "" ? [$uf] : []));
			$alvo = normalizarTextoCsv($cidadeTxt);
			while($rowCidade = mysqli_fetch_assoc($res)){
				if(normalizarTextoCsv($rowCidade["cida_tx_nome"] ?? "") === $alvo){
					return intval($rowCidade["cida_nb_id"] ?? 0) ?: null;
				}
			}
			return null;
		};

		$resolveParametro = function(string $raw) {
			$raw = preg_replace('/\s+/u', ' ', trim($raw));
			if($raw === "") return null;
			if(ctype_digit($raw)){
				$r = mysqli_fetch_assoc(query("SELECT para_nb_id FROM parametro WHERE para_nb_id = ? AND para_tx_status = 'ativo' LIMIT 1", "i", [intval($raw)]));
				if(!empty($r["para_nb_id"])) return intval($r["para_nb_id"]);
			}
			$r = mysqli_fetch_assoc(query("SELECT para_nb_id FROM parametro WHERE para_tx_nome = ? LIMIT 1", "s", [$raw]));
			if(!empty($r["para_nb_id"])) return intval($r["para_nb_id"]);

			$r = mysqli_fetch_assoc(query("SELECT para_nb_id FROM parametro WHERE para_tx_status = 'ativo' AND para_tx_nome = ? LIMIT 1", "s", [$raw]));
			if(!empty($r["para_nb_id"])) return intval($r["para_nb_id"]);

			$res = query("SELECT para_nb_id, para_tx_nome FROM parametro WHERE para_tx_status = 'ativo'");
			$alvo = normalizarTextoCsv($raw);
			while($rowPar = mysqli_fetch_assoc($res)){
				$nomePar = preg_replace('/\s+/u', ' ', trim((string)($rowPar["para_tx_nome"] ?? "")));
				if(normalizarTextoCsv($nomePar) === $alvo){
					return intval($rowPar["para_nb_id"] ?? 0) ?: null;
				}
			}
			return null;
		};

		$resolveTabela = function(string $raw, string $tabela, string $idCol, string $nomeCol) {
			$raw = trim($raw);
			if($raw === "") return null;
			if(ctype_digit($raw)){
				$r = mysqli_fetch_assoc(query("SELECT {$idCol} id FROM {$tabela} WHERE {$idCol} = ? LIMIT 1", "i", [intval($raw)]));
				if(!empty($r["id"])) return intval($r["id"]);
			}
			$r = mysqli_fetch_assoc(query("SELECT {$idCol} id FROM {$tabela} WHERE {$nomeCol} = ? LIMIT 1", "s", [$raw]));
			return (!empty($r["id"]) ? intval($r["id"]) : null);
		};

		$empresaSessaoId = intval($_SESSION["user_nb_empresa"] ?? 0);
		$empresaBuscaId = intval($_POST["empresa_padrao_upload"] ?? ($_POST["busca_empresa"] ?? 0));
		$resolveEmpresa = function(string $raw) use ($empresaSessaoId, $empresaBuscaId) {
			$raw = trim($raw);
			if($raw === ""){
				if($empresaSessaoId > 0) return $empresaSessaoId;
				if($empresaBuscaId > 0) return $empresaBuscaId;
				$r = mysqli_fetch_assoc(query("SELECT empr_nb_id FROM empresa WHERE empr_tx_status = 'ativo' LIMIT 1"));
				return (!empty($r["empr_nb_id"]) ? intval($r["empr_nb_id"]) : null);
			}
			if(ctype_digit($raw)){
				$r = mysqli_fetch_assoc(query("SELECT empr_nb_id FROM empresa WHERE empr_nb_id = ? AND empr_tx_status = 'ativo' LIMIT 1", "i", [intval($raw)]));
				if(!empty($r["empr_nb_id"])) return intval($r["empr_nb_id"]);
			}
			$r = mysqli_fetch_assoc(query("SELECT empr_nb_id FROM empresa WHERE (empr_tx_nome = ? OR empr_tx_fantasia = ?) AND empr_tx_status = 'ativo' LIMIT 1", "ss", [$raw, $raw]));
			return (!empty($r["empr_nb_id"]) ? intval($r["empr_nb_id"]) : null);
		};

		$total = 0;
		$gravados = 0;
		$erros = [];
		$lin = 1;
		while(($row = fgetcsv($fp, 0, $delim)) !== false){
			$lin++;
			if(count(array_filter($row, fn($v) => trim((string)$v) !== "")) === 0){
				continue;
			}
			$total++;

			$email = $get($row, $col["email"]);
			$fone1 = $get($row, $col["telefone_1"]);
			$fone2 = $get($row, $col["telefone_2"]);
			$matricula = $get($row, $col["matricula"]);
			$nomeFunc = $get($row, $col["nome"]);
			$nasc = $toDate($get($row, $col["nascido_em"]));
			$cpf = preg_replace('/[^0-9]/', '', $get($row, $col["cpf"]));
			$rg = preg_replace('/[^0-9]/', '', $get($row, $col["rg"]));
			$cep = $get($row, $col["cep"]);
			$bairro = $get($row, $col["bairro"]);
			$endereco = $get($row, $col["endereco"]);
			$salario = $toDecimal($get($row, $col["salario"]));
			$ocupacao = $get($row, $col["ocupacao"]);
			$paramRawEscala = $get($row, $col["parametro_jornada_escala"]);
			$paramRawCampo = $get($row, $col["parametro_jornada"]);
			$paramRaw = ($paramRawCampo !== "" ? $paramRawCampo : $paramRawEscala);
			$admissao = $toDate($get($row, $col["dt_admissao"]));

			if(in_array($_ENV["CONTEX_PATH"], ["/comav"]) === false){
				while(strlen($matricula) > 1 && isset($matricula[0]) && $matricula[0] === '0'){
					$matricula = substr($matricula, 1);
				}
			}

			$faltando = [];
			foreach([
				"email" => $email,
				"telefone_1" => $fone1,
				"matricula" => $matricula,
				"nome" => $nomeFunc,
				"nascido_em" => $nasc,
				"cpf" => $cpf,
				"rg" => $rg,
				"cep" => $cep,
				"bairro" => $bairro,
				"endereco" => $endereco,
				"salario" => $salario,
				"ocupacao" => $ocupacao,
				"parametro_jornada" => $paramRaw,
				"dt_admissao" => $admissao
			] as $k => $v){
				if($v === null || $v === "") $faltando[] = $k;
			}
			if(!empty($faltando)){
				$erros[] = "Linha {$lin}: campos obrigatórios não preenchidos (".implode(', ', $faltando).").";
				continue;
			}

			if(!validarCPF($cpf)){
				$erros[] = "Linha {$lin}: CPF inválido.";
				continue;
			}
			if(strlen($rg) < 3){
				$erros[] = "Linha {$lin}: RG inválido.";
				continue;
			}

			$cidadeId = $resolveCidade($get($row, $col["cod_ibge"]), $get($row, $col["cidade_uf"]), $get($row, $col["uf"]));
			if(empty($cidadeId)){
				$erros[] = "Linha {$lin}: Cód. IBGE/Cidade não encontrado(a).";
				continue;
			}

			$empresaId = $resolveEmpresa($get($row, $col["empresa"]));
			if(empty($empresaId)){
				$erros[] = "Linha {$lin}: empresa não encontrada e não foi possível usar empresa padrão.";
				continue;
			}

			$parametroId = $resolveParametro($paramRaw);
			if(empty($parametroId)){
				$erros[] = "Linha {$lin}: parametro_jornada não encontrado.";
				continue;
			}

			$setorId = $resolveTabela($get($row, $col["setor"]), "grupos_documentos", "grup_nb_id", "grup_tx_nome");
			$subsetorId = $resolveTabela($get($row, $col["subsetor"]), "sbgrupos_documentos", "sbgr_nb_id", "sbgr_tx_nome");
			$cargoId = $resolveTabela($get($row, $col["cargo"]), "operacao", "oper_nb_id", "oper_tx_nome");

			$status = normalizarTextoCsv($get($row, $col["status"])) === "inativo" ? "inativo" : "ativo";
			$jornadaSemanal = $get($row, $col["jornada_semanal"]);
			$jornadaSabado = $get($row, $col["jornada_sabado"]);
			$percSemanal = $get($row, $col["he_semanal"]);
			$percExtra = $get($row, $col["he_extra"]);

			$paramData = mysqli_fetch_assoc(query("SELECT para_tx_jornadaSemanal, para_tx_jornadaSabado, para_tx_percHESemanal, para_tx_percHEEx FROM parametro WHERE para_nb_id = ? LIMIT 1", "i", [$parametroId]));
			if($jornadaSemanal === "") $jornadaSemanal = (string)($paramData["para_tx_jornadaSemanal"] ?? "");
			if($jornadaSabado === "") $jornadaSabado = (string)($paramData["para_tx_jornadaSabado"] ?? "");
			if($percSemanal === "") $percSemanal = (string)($paramData["para_tx_percHESemanal"] ?? "");
			if($percExtra === "") $percExtra = (string)($paramData["para_tx_percHEEx"] ?? "");

			$exMat = mysqli_fetch_assoc(query("SELECT enti_nb_id FROM entidade WHERE enti_tx_matricula = ? LIMIT 1", "s", [$matricula]));
			if(!empty($exMat)){
				$erros[] = "Linha {$lin}: matrícula já cadastrada.";
				continue;
			}
			$login = $get($row, $col["login"]);
			if($login === "") $login = $matricula;
			$exLogin = mysqli_fetch_assoc(query("SELECT user_nb_id FROM user WHERE user_tx_status = 'ativo' AND user_tx_login = ? LIMIT 1", "s", [$login]));
			if(!empty($exLogin)){
				$erros[] = "Linha {$lin}: login já cadastrado.";
				continue;
			}

			$novoMotorista = [
				"enti_tx_matricula" => $matricula,
				"enti_tx_nome" => $nomeFunc,
				"enti_tx_nascimento" => $nasc,
				"enti_tx_status" => $status,
				"enti_tx_cpf" => $cpf,
				"enti_tx_rg" => $rg,
				"enti_tx_endereco" => $endereco,
				"enti_tx_bairro" => $bairro,
				"enti_nb_cidade" => $cidadeId,
				"enti_tx_cep" => $cep,
				"enti_tx_fone1" => $fone1,
				"enti_tx_fone2" => $fone2,
				"enti_tx_email" => $email,
				"enti_tx_ocupacao" => $ocupacao,
				"enti_nb_salario" => $salario,
				"enti_nb_parametro" => $parametroId,
				"enti_nb_empresa" => $empresaId,
				"enti_setor_id" => $setorId,
				"enti_subSetor_id" => $subsetorId,
				"enti_tx_tipoOperacao" => $cargoId,
				"enti_tx_admissao" => $admissao,
				"enti_tx_jornadaSemanal" => $jornadaSemanal,
				"enti_tx_jornadaSabado" => $jornadaSabado,
				"enti_tx_percHESemanal" => $percSemanal,
				"enti_tx_percHEEx" => $percExtra,
				"enti_nb_userCadastro" => $_SESSION["user_nb_id"],
				"enti_tx_dataCadastro" => date('Y-m-d H:i:s'),
				"enti_tx_ehPadrao" => "nao"
			];
			$novoMotorista = array_filter($novoMotorista, function($v){ return !($v === "" || $v === []); });

			query("START TRANSACTION;");
			$insertEnt = inserir("entidade", array_keys($novoMotorista), array_values($novoMotorista));
			if(empty($insertEnt) || ($insertEnt[0] instanceof Exception)){
				query("ROLLBACK;");
				$erros[] = "Linha {$lin}: erro ao inserir funcionário.";
				continue;
			}
			$idEntidade = intval($insertEnt[0]);

			$newUser = [
				"user_tx_nome" => $nomeFunc,
				"user_tx_nivel" => $ocupacao,
				"user_tx_login" => $login,
				"user_tx_senha" => md5($cpf),
				"user_tx_status" => $status,
				"user_nb_entidade" => $idEntidade,
				"user_tx_nascimento" => $nasc,
				"user_tx_cpf" => $cpf,
				"user_tx_rg" => $rg,
				"user_nb_cidade" => $cidadeId,
				"user_tx_email" => $email,
				"user_tx_fone" => $fone1,
				"user_nb_empresa" => $empresaId,
				"user_nb_userCadastro" => $_SESSION["user_nb_id"],
				"user_tx_dataCadastro" => date('Y-m-d H:i:s')
			];
			$newUser = array_filter($newUser, function($v){ return !($v === "" || $v === []); });
			$insertUser = inserir("user", array_keys($newUser), array_values($newUser));
			if(empty($insertUser) || ($insertUser[0] instanceof Exception)){
				query("ROLLBACK;");
				$erros[] = "Linha {$lin}: erro ao inserir usuário vinculado.";
				continue;
			}

			query("COMMIT;");
			$gravados++;
		}
		fclose($fp);

		if($total === 0){
			set_status("ERRO: O CSV não possui linhas de dados para importação.");
			index();
			exit;
		}
		if($gravados <= 0){
			set_status("ERRO: Nenhum funcionário foi importado. Total linhas: {$total}. Erros: ".count($erros).".<br>".implode("<br>", array_slice($erros, 0, 30)).(count($erros) > 30 ? "<br>..." : ""));
			index();
			exit;
		}

		$msg = "OK: Importação concluída. Total linhas: {$total}. Gravados: {$gravados}.";
		if(!empty($erros)){
			$msg .= "<br>Ocorreram ".count($erros)." erro(s):<br>".implode("<br>", array_slice($erros, 0, 30)).(count($erros) > 30 ? "<br>..." : "");
		}
		set_status($msg);
		index();
		exit;
	}
	
function index(){
		
        // APATH QUE O USER ESTA TENTANDO ACESSAR PARA VERIFICAR NO PERFIL SE TEM ACESSO2
        verificaPermissao('/cadastro_funcionario.php');
		
        cabecalho("Cadastro de Funcionário");

		//Se tiver permissão, esquece isso de administrador ou super
		$extraEmpresa = "";
		if ($_SESSION["user_nb_empresa"] > 0 && is_bool(stripos($_SESSION["user_tx_nivel"], "Administrador")) && is_bool(stripos($_SESSION["user_tx_nivel"], "Super"))) {
			$extraEmpresa = " AND empr_nb_id = '".$_SESSION["user_nb_empresa"]."'";
		}
		
		if(!empty($_POST["busca_cpf_like"])){
			$_POST["busca_cpf_like"] = preg_replace( "/[^0-9]/is", "", $_POST["busca_cpf_like"]);
		}

		$camposBusca = [
			campo("Código",						"busca_codigo",			(!empty($_POST["busca_codigo"])? $_POST["busca_codigo"]: ""), 1,"","maxlength='6'"),
			campo("Nome",						"busca_nome_like",		(!empty($_POST["busca_nome_like"])? $_POST["busca_nome_like"]: ""), 2,"","maxlength='65'"),
			campo("Matrícula",					"busca_matricula_like",	(!empty($_POST["busca_matricula_like"])? $_POST["busca_matricula_like"]: ""), 1,"","maxlength='20'"),
			campo("CPF",						"busca_cpf_like",			(!empty($_POST["busca_cpf_like"])? $_POST["busca_cpf_like"]: ""), 2, "MASCARA_CPF"),
			combo_bd("!Empresa",				"busca_empresa",		(!empty($_POST["busca_empresa"])? $_POST["busca_empresa"]: ""), 2, "empresa", "", $extraEmpresa),
			combo("Ocupação",					"busca_ocupacao",		(!empty($_POST["busca_ocupacao"])? $_POST["busca_ocupacao"]: ""), 2, ["" => "Todos", "Motorista" => "Motorista", "Ajudante" => "Ajudante", "Funcionário" => "Funcionário"]),
			combo("Convenção Padrão",			"busca_padrao",			(!empty($_POST["busca_padrao"])? $_POST["busca_padrao"]: ""), 2, ["" => "Todos", "sim" => "Sim", "nao" => "Não"]),
			combo_bd("!Parâmetros da Jornada", 	"busca_parametro", 		(!empty($_POST["busca_parametro"])? $_POST["busca_parametro"]: ""), 4, "parametro"),
			combo("Status", 					"busca_status", 			(isset($_POST["busca_status"])? $_POST["busca_status"]: "ativo"), 2, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"]),
			combo_bd("!Setor", 				"busca_setor", 			(!empty($_POST["busca_setor"])? $_POST["busca_setor"]: ""), 2, "grupos_documentos"),
            combo_bd("!Subsetor",            "busca_subsetor",        (!empty($_POST["busca_subsetor"]) ? $_POST["busca_subsetor"] : ""), 2, "sbgrupos_documentos", "", (!empty($_POST["busca_setor"]) ? " AND sbgr_nb_idgrup = ".intval($_POST["busca_setor"])." ORDER BY sbgr_tx_nome ASC" : " ORDER BY sbgr_tx_nome ASC")),
			combo_bd("!Cargo", 				"busca_operacao", 		(!empty($_POST["busca_operacao"])? $_POST["busca_operacao"]: ""), 2, "operacao")
		];

		$botoesBusca = [
			botao("Inserir", "visualizarCadastro","","","","","btn btn-success"),
			'<button class="btn default" type="button" onclick="imprimirTabelaCompleta()">Imprimir</button>',
			'<a class="btn btn-info" href="cadastro_funcionario.php?acao=baixarModeloCsvFuncionarios">Download Modelo CSV</a>',
			botao("Limpar Filtros", "limparFiltros")
		];

		$formUploadCsv = "
			<form method='post' enctype='multipart/form-data' style='display:inline-block; margin-left:8px;'>
				<input type='hidden' name='acao' value='uploadCsvFuncionarios'>
				<input type='hidden' name='empresa_padrao_upload' value='".intval($_POST["busca_empresa"] ?? 0)."'>
				<input type='file' name='arquivo_csv_funcionarios' accept='.csv,text/csv' required style='display:inline-block; width:240px;'>
				<button class='btn btn-warning' type='submit'>Upload CSV</button>
			</form>
		";

		echo abre_form();
		echo linha_form($camposBusca);
		echo fecha_form([], "<hr><form style='display:inline-block;'>".implode(" ", $botoesBusca)."</form>".$formUploadCsv);

		$logoEmpresa = mysqli_fetch_assoc(query(
            "SELECT empr_tx_logo FROM empresa
                    WHERE empr_tx_status = 'ativo'
                        AND empr_tx_Ehmatriz = 'sim'
                    LIMIT 1;"
        ))["empr_tx_logo"];


		echo "<div id='tituloRelatorio' style='display: none;'>
                    <img style='width: 190px; height: 40px;' src='./imagens/logo_topo_cliente.png' alt='Logo Empresa Esquerda'>
					<h1>Cadastro de Funcionários</h1>
                    <img style='width: 180px; height: 80px;' src='./$logoEmpresa' alt='Logo Empresa Direita'>
            </div>";
		

			$gridFields = [
                "CÓDIGO" 				=> "enti_nb_id",
                "NOME" 					=> "enti_tx_nome",
                "MATRÍCULA" 			=> "enti_tx_matricula",
                "CPF" 					=> "enti_tx_cpf",
                "EMPRESA" 				=> "empr_tx_nome",
                "CARGO" 				=> "oper_tx_nome",
                "SETOR" 				=> "grup_tx_nome",
                "SUBSETOR" 			    => "sbgr_tx_nome",
                "FONE 1" 				=> "enti_tx_fone1",
                "OCUPAÇÃO" 				=> "enti_tx_ocupacao",
                "DATA CADASTRO" 		=> "DATE_FORMAT(enti_tx_dataCadastro, '%d/%m/%Y')",
                "PARÂMETRO DA JORNADA" 	=> "para_tx_nome",
                "CONVENÇÃO PADRÃO" 		=> "IF(enti_tx_ehPadrao = \"sim\", \"Sim\", \"Não\") AS enti_tx_ehPadrao",
                "STATUS" 				=> "enti_tx_status",
				"UID"                   => "rfids_tx_uid",
                "AUTENTICAÇÃO"          => "rfids_nb_id",
                "FACIAL"                => "IF(user.user_tx_face_descriptor IS NOT NULL AND user.user_tx_face_descriptor != '', 1, 0) AS tem_facial"
            ];

			$allGridFields = [
                "CÓDIGO" 				=> "enti_nb_id",
                "NOME" 					=> "enti_tx_nome",
                "MATRÍCULA" 			=> "enti_tx_matricula",
                "CPF" 					=> "enti_tx_cpf",
                "EMPRESA" 				=> "empr_tx_nome",
                "CARGO" 				=> "oper_tx_nome",
                "SETOR" 				=> "grup_tx_nome",
                "SUBSETOR" 				=> "sbgr_tx_nome",
                "FONE 1" 				=> "enti_tx_fone1",
                "OCUPAÇÃO" 				=> "enti_tx_ocupacao",
                "DATA CADASTRO" 		=> "DATE_FORMAT(enti_tx_dataCadastro, '%d/%m/%Y')",
                "PARÂMETRO DA JORNADA" 	=> "para_tx_nome",
                "CONVENÇÃO PADRÃO" 		=> "IF(enti_tx_ehPadrao = \"sim\", \"Sim\", \"Não\") AS enti_tx_ehPadrao",
                "STATUS" 				=> "enti_tx_status",
				"UID"                   => "rfids_tx_uid",
                "AUTENTICAÇÃO"          => "rfids_nb_id",
                "FACIAL"                => "IF(user.user_tx_face_descriptor IS NOT NULL AND user.user_tx_face_descriptor != '', 1, 0) AS tem_facial"
            ];

			$allGridFields = [
                "CÓDIGO" 				=> "enti_nb_id",
                "NOME" 					=> "enti_tx_nome",
                "MATRÍCULA" 			=> "enti_tx_matricula",
                "CPF" 					=> "enti_tx_cpf",
                "EMPRESA" 				=> "empr_tx_nome",
                "CARGO" 				=> "oper_tx_nome",
                "SETOR" 				=> "grup_tx_nome",
                "SUBSETOR" 				=> "sbgr_tx_nome",
                "FONE 1" 				=> "enti_tx_fone1",
                "OCUPAÇÃO" 				=> "enti_tx_ocupacao",
                "DATA CADASTRO" 		=> "DATE_FORMAT(enti_tx_dataCadastro, '%d/%m/%Y')",
                "PARÂMETRO DA JORNADA" 	=> "para_tx_nome",
                "CONVENÇÃO PADRÃO" 		=> "IF(enti_tx_ehPadrao = \"sim\", \"Sim\", \"Não\") AS enti_tx_ehPadrao",
                "STATUS" 				=> "enti_tx_status",
				"UID"                   => "rfids_tx_uid",
                "AUTENTICAÇÃO"          => "rfids_nb_id",
                "FACIAL"                => "IF(user.user_tx_face_descriptor IS NOT NULL AND user.user_tx_face_descriptor != '', 1, 0) AS tem_facial",
                "TELEFONE 2"            => "enti_tx_fone2",
                "NASCIMENTO"            => "DATE_FORMAT(enti_tx_nascimento, '%d/%m/%Y')",
                "RG"                    => "enti_tx_rg",
                "ESTADO CIVIL"          => "enti_tx_civil",
                "SEXO"                  => "enti_tx_sexo",
                "RG ÓRGÃO"              => "enti_tx_rgOrgao",
                "RG DATA EMISSÃO"       => "DATE_FORMAT(enti_tx_rgDataEmissao, '%d/%m/%Y')",
                "RG UF"                 => "enti_tx_rgUf",
                "RAÇA/COR"              => "enti_tx_racaCor",
                "TIPO SANGUÍNEO"        => "enti_tx_tipoSanguineo",
                "CEP"                   => "enti_tx_cep",
                "CIDADE"                => "CONCAT(cid_residencia.cida_tx_nome, '/', cid_residencia.cida_tx_uf)",
                "BAIRRO"                => "enti_tx_bairro",
                "ENDEREÇO"              => "enti_tx_endereco",
                "NÚMERO"                => "enti_tx_numero",
                "COMPLEMENTO"           => "enti_tx_complemento",
                "REFERÊNCIA"            => "enti_tx_referencia",
                "PAI"                   => "enti_tx_pai",
                "MÃE"                   => "enti_tx_mae",
                "CÔNJUGE"               => "enti_tx_conjugue",
                "OBSERVAÇÕES"           => "enti_tx_obs",
                "SALÁRIO"               => "enti_nb_salario",
                "ADMISSÃO"              => "DATE_FORMAT(enti_tx_admissao, '%d/%m/%Y')",
                "DESLIGAMENTO"          => "DATE_FORMAT(enti_tx_desligamento, '%d/%m/%Y')",
                "BANCO HORAS"           => "enti_tx_banco",
                "SUBCONTRATADO"         => "enti_tx_subcontratado",
                "PIS"                   => "enti_tx_pis",
                "CTPS NÚMERO"           => "enti_tx_ctpsNumero",
                "CTPS SÉRIE"            => "enti_tx_ctpsSerie",
                "CTPS UF"               => "enti_tx_ctpsUf",
                "TÍTULO NÚMERO"         => "enti_tx_tituloNumero",
                "TÍTULO ZONA"           => "enti_tx_tituloZona",
                "TÍTULO SEÇÃO"          => "enti_tx_tituloSecao",
                "RESERVISTA"            => "enti_tx_reservista",
                "REGISTRO FUNCIONAL"    => "enti_tx_registroFuncional",
                "ORGÃO REG. FUNC."      => "enti_tx_OrgaoRegimeFuncional",
                "VENCIMENTO REGISTRO"   => "DATE_FORMAT(enti_tx_vencimentoRegistro, '%d/%m/%Y')",
                "JORNADA SEMANAL"       => "enti_tx_jornadaSemanal",
                "JORNADA SÁBADO"        => "enti_tx_jornadaSabado",
                "HE SEMANAL %"          => "enti_tx_percHESemanal",
                "HE EXTRA %"            => "enti_tx_percHEEx",
                "CNH REGISTRO"          => "enti_tx_cnhRegistro",
                "CNH CATEGORIA"         => "enti_tx_cnhCategoria",
                "CNH CIDADE"            => "CONCAT(cid_cnh.cida_tx_nome, '/', cid_cnh.cida_tx_uf)",
                "CNH EMISSÃO"           => "DATE_FORMAT(enti_tx_cnhEmissao, '%d/%m/%Y')",
                "CNH VALIDADE"          => "DATE_FORMAT(enti_tx_cnhValidade, '%d/%m/%Y')",
                "CNH 1ª HABILITAÇÃO"    => "DATE_FORMAT(enti_tx_cnhPrimeiraHabilitacao, '%d/%m/%Y')",
                "CNH PERMISSÃO"         => "enti_tx_cnhPermissao",
                "CNH PONTUAÇÃO"         => "enti_tx_cnhPontuacao",
                "CNH ATIV. REMUNERADA"  => "enti_tx_cnhAtividadeRemunerada",
                "CNH OBS"               => "enti_tx_cnhObs"
				
			];
	
			$camposBusca = [
				"busca_codigo" 			=> "enti_nb_id",
				"busca_nome_like" 		=> "enti_tx_nome",
				"busca_matricula_like" 	=> "enti_tx_matricula",
				"busca_cpf" 			=> "enti_tx_cpf",
				"busca_empresa" 		=> "enti_nb_empresa",
				"busca_setor" 			=> "enti_setor_id",
				"busca_subsetor" 		=> "enti_subSetor_id",
				"busca_ocupacao" 		=> "enti_tx_ocupacao",
				"busca_padrao" 			=> "enti_tx_ehPadrao",
				"busca_parametro" 		=> "enti_nb_parametro",
				"busca_status" 			=> "enti_tx_status",
				"busca_operacao" 		=> "enti_tx_tipoOperacao"
			];
	
            $queryBase = (
                "SELECT ".implode(", ", array_values($allGridFields))." FROM entidade"
                    ." LEFT JOIN empresa ON enti_nb_empresa = empr_nb_id"
                    ." LEFT JOIN grupos_documentos ON enti_setor_id = grup_nb_id"
                    ." LEFT JOIN sbgrupos_documentos subg ON enti_subSetor_id = subg.sbgr_nb_id"
                    ." LEFT JOIN parametro ON enti_nb_parametro = para_nb_id"
                    ." LEFT JOIN operacao ON enti_tx_tipoOperacao = oper_nb_id"
                    ." LEFT JOIN cidade cid_residencia ON enti_nb_cidade = cid_residencia.cida_nb_id"
                    ." LEFT JOIN cidade cid_cnh ON enti_nb_cnhCidade = cid_cnh.cida_nb_id"
                    ." LEFT JOIN user ON user.user_nb_entidade = entidade.enti_nb_id AND user.user_tx_status = 'ativo'"
                    ." LEFT JOIN rfids ON rfids.rfids_nb_user_id = user.user_nb_id AND rfids.rfids_tx_status = 'ativo'"
            );
	
			// 1. Chamamos a utilitária para gerar os botões padrão (limpando aquele seu JS antigo manual!)
            $acoesGrid = gerarAcoesComConfirmacao(
                "cadastro_funcionario.php", 
                "modificarMotorista", 
                "excluirMotorista", 
                "CÓDIGO",
            	"Deseja excluir o funcionário: <br><h3 style='color:#337ab7;'>{NOME}<br><small>CPF: {CPF}</small></h3>"
            );

            $gridFields["actions"] = $acoesGrid["tags"];

            // 2. Mesclamos o JS da utilitária com as regras dinâmicas
            $jsFunctions = $acoesGrid["js"] . "
                
                // FUNÇÃO RADAR: Descobre em qual índice numérico uma coluna está baseada no nome
                const pegarIndiceColuna = function(nomeColuna) {
                    var index = -1;
                    $('table thead th').each(function(i) {
                        if ($(this).text().trim().toUpperCase() === nomeColuna.toUpperCase()) {
                            index = i;
                            return false; // Interrompe o loop ao encontrar
                        }
                    });
                    return index;
                };

                // FUNÇÃO: Varre a tabela e desenha os ícones HTML de biometria/crachá
                const formatarBiometria = function() {
                    var idxCodigo = pegarIndiceColuna('CÓDIGO');
                    var idxAutenticacao = pegarIndiceColuna('AUTENTICAÇÃO');
                    var idxFacial = pegarIndiceColuna('FACIAL');
                    if (idxCodigo === -1 || idxAutenticacao === -1) return;

                    $('table tbody tr').each(function() {
                        var idEntidade = $(this).find('td').eq(idxCodigo).text().trim();
                        var tdAutenticacao = $(this).find('td').eq(idxAutenticacao);
                        var idRfid = tdAutenticacao.text().trim();
                        var temFacial = idxFacial !== -1 ? $(this).find('td').eq(idxFacial).text().trim() : '0';

                        if (!idEntidade) return;

                        var htmlIcones = '';

                        // RFID
                        if (idRfid !== '') {
                            htmlIcones += '<span onclick=\"abrirRfidDireto(' + idRfid + ', ' + idEntidade + ')\" class=\"glyphicon glyphicon-credit-card\" style=\"color:#28a745;font-size:14px;margin-right:12px;cursor:pointer;\" title=\"Editar Crachá Ativo\"></span>';
                        } else {
                            htmlIcones += '<span class=\"glyphicon glyphicon-credit-card\" style=\"color:#808080;font-size:14px;margin-right:12px;\" title=\"Sem Crachá\"></span>';
                        }

                        // Digital
                        htmlIcones += '<span class=\"glyphicon glyphicon-hand-up\" style=\"color:#808080;font-size:14px;margin-right:12px;\" title=\"Sem Digital\"></span>';

                        // Facial
                        if (temFacial === '1') {
                            htmlIcones += '<span onclick=\"abrirFacialDireto(' + idEntidade + ')\" class=\"glyphicon glyphicon-user\" style=\"color:#28a745;font-size:14px;cursor:pointer;\" title=\"Biometria Facial Ativa — clique para gerenciar\"></span>';
                        } else {
                            htmlIcones += '<span onclick=\"abrirFacialDireto(' + idEntidade + ')\" class=\"glyphicon glyphicon-user\" style=\"color:#808080;font-size:14px;cursor:pointer;\" title=\"Sem Biometria Facial — clique para cadastrar\"></span>';
                        }

                        tdAutenticacao.html(htmlIcones);
                    });
                };

                window.abrirFacialDireto = function(idEntidade) {
                    window.location.href = 'cadastro_facial.php?enti_id=' + idEntidade;
                };

                window.abrirRfidDireto = function(idRfid, idUsuario) {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'cadastro_rfid.php';
                    
                    var inputId = document.createElement('input');
                    inputId.type = 'hidden';
                    inputId.name = 'id';
                    inputId.value = idRfid;
                    form.appendChild(inputId);
                    
                    var inputAcao = document.createElement('input');
                    inputAcao.type = 'hidden';
                    inputAcao.name = 'acao';
                    inputAcao.value = 'modificarRfid';
                    form.appendChild(inputAcao);

                    // bilhete dizendo que viemos do Grid de Funcionário
                    var fieldOrigem = document.createElement('input');
                    fieldOrigem.type = 'hidden';
                    fieldOrigem.name = 'tela_origem';
                    fieldOrigem.value = 'grid_funcionario';
                    form.appendChild(fieldOrigem);
                    
                    document.body.appendChild(form);
                    form.submit();
                };

                // Executa as funções no ciclo de vida do grid
                var funcoesInternasAntiga = funcoesInternas; 
                funcoesInternas = function(){
                    // Roda o JS da lupa e do SweetAlert
                    if(typeof funcoesInternasAntiga === 'function') funcoesInternasAntiga(); 
                    
                    // Roda a formatação de crachás
                    formatarBiometria();
                    // Oculta coluna FACIAL (usada só internamente para o ícone)
                    var idxFacialHide = pegarIndiceColuna('FACIAL');
                    if (idxFacialHide !== -1) {
                        $('table thead th').eq(idxFacialHide).hide();
                        $('table tbody tr').each(function(){ $(this).find('td').eq(idxFacialHide).hide(); });
                    }
                };
            ";
	
			echo gridDinamico("tabelaMotoristas", $gridFields, $camposBusca, $queryBase, $jsFunctions, 12, -1, $allGridFields);
		//}

		rodape();
	}
