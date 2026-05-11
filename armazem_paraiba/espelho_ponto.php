<?php


/*
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
*/
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");


	include "funcoes_ponto.php"; //Conecta incluso dentro de funcoes_ponto
	include_once "check_permission.php";

	function getRotulosEspelho(): array{
		static $rotulos = null;
		if($rotulos !== null){
			return $rotulos;
		}

		// Mantem a mesma precedencia usada na batida: nivel do usuario, ocupacao da entidade e fallback de sessao.
		$ocupacaoUsuario = trim((string)($_SESSION["user_tx_nivel"] ?? ""));
		if(empty($ocupacaoUsuario) && !empty($_SESSION["user_nb_entidade"])){
			$ocupacaoEntidade = mysqli_fetch_assoc(query(
				"SELECT enti_tx_ocupacao FROM entidade WHERE enti_nb_id = '".$_SESSION["user_nb_entidade"]."' LIMIT 1;"
			));
			$ocupacaoUsuario = trim((string)($ocupacaoEntidade["enti_tx_ocupacao"] ?? ""));
		}
		if(empty($ocupacaoUsuario) && !empty($_SESSION["enti_tx_ocupacao"])){
			$ocupacaoUsuario = trim((string)$_SESSION["enti_tx_ocupacao"]);
		}

		$ocupacaoNormalizada = strtolower($ocupacaoUsuario);
		$ehTerceirizado = in_array($ocupacaoNormalizada, ["terceirizado",], true);

		$rotulos = [
			"ehTerceirizado" => $ehTerceirizado,
			"modulo" => $ehTerceirizado ? "Produção" : "Ponto",
			"funcionario" => $ehTerceirizado ? "Médico" : "Funcionário",
			"funcionarioPlural" => $ehTerceirizado ? "Médico" : "Funcionários"
		];

		return $rotulos;
	}

	// 🔧 Gera rótulos dinâmicos baseado na ocupação de um motorista específico
	function getRotulosPorMotorista(array $motorista): array {
		$ocupacao = trim((string)($motorista["enti_tx_ocupacao"] ?? ""));
		$ocupacaoNormalizada = strtolower($ocupacao);
		$ehTerceirizado = in_array($ocupacaoNormalizada, ["terceirizado"], true);

			// Quando for terceirizado, tentar recuperar o nome do cargo cadastrado
			$cargoNome = "Médico";
			if($ehTerceirizado){
				$tipoOperacao = $motorista["enti_tx_tipoOperacao"] ?? null;
				if(!empty($tipoOperacao)){
					$op = mysqli_fetch_assoc(query("SELECT oper_tx_nome FROM operacao WHERE oper_nb_id = '".intval($tipoOperacao)."' LIMIT 1;"));
					if($op && !empty($op['oper_tx_nome'])){
						$cargoNome = $op['oper_tx_nome'];
					}
				}
			}

			return [
				"ehTerceirizado" => $ehTerceirizado,
				"modulo" => $ehTerceirizado ? "Produção" : "Ponto",
				"funcionario" => $ehTerceirizado ? $cargoNome : "Funcionário",
				"funcionarioPlural" => $ehTerceirizado ? $cargoNome : "Funcionários"
			];
	}

	function normalizarFiltroArray($valor): array{
		if (is_array($valor)) {
			return array_values(array_filter(array_map('trim', $valor), function($v) { return $v !== ''; }));
		}
		if (is_string($valor) && $valor !== '') {
			$partes = array_map('trim', explode(',', $valor));
			return array_values(array_filter($partes, function($v) { return $v !== ''; }));
		}
		return [];
	}

	function renderFiltroCheckboxGroup($titulo, $name, $opcoes, $selecionados, $width=3) {
		$selecionados = normalizarFiltroArray($selecionados);
		$selecionadosQtd = count($selecionados);
		$tituloRender = $titulo;
		if($selecionadosQtd === 1){
			$valorSelecionado = (string)$selecionados[0];
			$labelSelecionado = "";
			foreach($opcoes as $valor => $rotulo){
				if((string)$valor === $valorSelecionado){
					$labelSelecionado = (string)$rotulo;
					break;
				}
			}
			if($labelSelecionado !== ""){
				$tituloRender = $labelSelecionado;
			}
		}elseif($selecionadosQtd > 1){
			$tituloRender = $titulo." ({$selecionadosQtd})";
		}
		$nameAttr = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
		$habilitarBusca = in_array($name, ["busca_motorista", "busca_empresa"], true);
		$placeholderBusca = "Digite para filtrar";
		if($name === "busca_motorista"){
			$placeholderBusca = "Digite o nome do funcionário";
		}elseif($name === "busca_empresa"){
			$placeholderBusca = "Digite o nome da empresa";
		}
		$groupId = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
		$hiddenValue = htmlspecialchars(implode(',', $selecionados), ENT_QUOTES, 'UTF-8');
		$tituloAttr = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
		$tituloRenderAttr = htmlspecialchars($tituloRender, ENT_QUOTES, 'UTF-8');

		$html = "<div class='col-sm-{$width} margin-bottom-5 campo-fit-content'>"
			."<div class='filtro-dropdown' data-filter-group='".$groupId."' style='position:relative; overflow:visible;'>"
			."<button type='button' class='btn btn-default btn-block filtro-dropdown-toggle js-filtro-toggle' data-target='".$nameAttr."' data-base-label='".$tituloAttr."' aria-expanded='false' style='display:flex; justify-content:space-between; align-items:center; gap:10px;'>"
			."<span class='js-filtro-label' style='text-align:left;'>".$tituloRenderAttr."</span>"
			."<span class='caret'></span>"
			."</button>"
			."<div class='filtro-dropdown-menu' style='display:none; position:absolute; left:0; right:0; top:calc(100% + 4px); z-index:1050; background:#fff; border:1px solid #d9d9d9; border-radius:8px; box-shadow:0 12px 30px rgba(0,0,0,.12); padding:10px; max-height:260px; overflow:auto;'>"
			."<input type='hidden' class='js-filtro-hidden' data-filter-name='".$nameAttr."' name='".$nameAttr."' value='".$hiddenValue."'>"
			."<div style='display:flex; gap:6px; flex-wrap:wrap; margin-bottom:10px;'>"
			."<button type='button' class='btn btn-xs btn-default js-filtro-todos' data-target='".$nameAttr."' data-action='all'>Marcar todos</button>"
			."<button type='button' class='btn btn-xs btn-default js-filtro-todos' data-target='".$nameAttr."' data-action='none'>Desmarcar todos</button>"
			."</div>";

		if($name === "busca_empresa"){
			$html .= "<div style='margin-bottom:10px;'>"
				."<button type='button' class='btn btn-xs btn-info js-aplicar-empresa'>Aplicar empresas</button>"
				."</div>";
		}

		if($habilitarBusca){
			$html .= "<input type='text' class='form-control input-sm js-filtro-search' data-target='".$nameAttr."'"
				." placeholder='".htmlspecialchars($placeholderBusca, ENT_QUOTES, 'UTF-8')."'"
				." style='margin-bottom:10px;' autocomplete='off'>";
		}

		if(empty($opcoes)){
			$html .= "<div style='color:#777;'>Sem opções</div>";
		}else{
			foreach($opcoes as $valor => $rotulo){
				$valorStr = (string)$valor;
				$checked = in_array($valorStr, $selecionados, true) ? "checked" : "";
				$html .= "<label class='js-filtro-item' style='display:block; margin-bottom:6px; font-weight:normal; cursor:pointer;'>"
					."<input type='checkbox' class='js-filtro-checkbox' data-target='".$nameAttr."' value='".htmlspecialchars($valorStr, ENT_QUOTES, 'UTF-8')."' ".$checked." style='margin-right:6px;'>"
					.htmlspecialchars($rotulo)
					."</label>";
			}
		}

		$html .= "</div></div></div>";
		return $html;
	}

	function montarMensagemParametro(array &$motorista): string{
		$mensagemParametro = $motorista["para_tx_nome"];
		if($motorista["para_tx_tipo"] == "horas_por_dia"){
			$mensagemParametro .= " Semanal (".$motorista["enti_tx_jornadaSemanal"]."), Sábado (".$motorista["enti_tx_jornadaSabado"].")";
		}elseif(($motorista["para_tx_tipo"] == "escala")){
			$escala = mysqli_fetch_assoc(query("SELECT * FROM escala WHERE esca_nb_parametro = {$motorista["enti_nb_parametro"]}"));
			$mensagemParametro .= "<br>Dia 1: ".(new DateTime($escala["esca_tx_dataInicio"]))->format("d/m/Y");
		}

		if(!empty($motorista["empr_nb_parametro"])){
			$parametroEmpresa = mysqli_fetch_assoc(query(
				"SELECT para_tx_jornadaSemanal, para_tx_jornadaSabado, para_tx_percHESemanal, para_tx_percHEEx, para_nb_id, para_tx_nome, para_tx_tipo FROM parametro"
					." WHERE para_tx_status = 'ativo'"
						." AND para_nb_id = ".$motorista["empr_nb_parametro"]
					." LIMIT 1;"
			));
			
			if(!empty($parametroEmpresa)){
				if($parametroEmpresa["para_tx_tipo"] == "horas_por_dia"){
					$padronizado = (
						[$motorista["para_nb_id"], $motorista["para_tx_jornadaSemanal"], $motorista["para_tx_jornadaSabado"], $motorista["para_tx_percHESemanal"], $motorista["para_tx_percHEEx"]]
						==
						[$parametroEmpresa["para_nb_id"], $parametroEmpresa["para_tx_jornadaSemanal"], $parametroEmpresa["para_tx_jornadaSabado"], $parametroEmpresa["para_tx_percHESemanal"], $parametroEmpresa["para_tx_percHEEx"]]
					);
				}else{
					$padronizado = (
						[$motorista["para_nb_id"], $motorista["para_tx_percHESemanal"], $motorista["para_tx_percHEEx"]]
						==
						[$parametroEmpresa["para_nb_id"], $parametroEmpresa["para_tx_percHESemanal"], $parametroEmpresa["para_tx_percHEEx"]]
					);
				}

				$mensagemParametro = (!$padronizado? "Não ": "")."Padronizado.<br>";
				$mensagemParametro .= "{$motorista["para_tx_nome"]}";
				if(!empty($motorista["para_tx_jornadaSemanal"]) && !empty($motorista["para_tx_jornadaSabado"])){
					$mensagemParametro .= "<br>Semanal ({$motorista["para_tx_jornadaSemanal"]}), Sábado ({$motorista["para_tx_jornadaSabado"]})";
				}
			}
		}
		return $mensagemParametro;
	}


	function redirParaAbono(){
		unset($_POST["acao"]);
		if(empty($_POST['busca_motorista'])){
			header("Location: {$_ENV["APP_PATH"]}{$_ENV["CONTEX_PATH"]}/cadastro_abono.php");
			exit;
		}

		$_POST["acao"] = "index";
		echo criarHiddenForm(
			"form_abono",
			array_keys($_POST),
			array_values($_POST),
			"cadastro_abono.php"
		);
		echo "<script>document.form_abono.submit();</script>";
		exit;
	}

	function redirParaAjustePonto(){
		$rotulos = getRotulosEspelho();
		unset($_POST["acao"]);
		if(empty($_POST['busca_motorista'])){
			if(in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário", "Terceirizado",])){
				$_POST['busca_motorista'] = $_SESSION["user_nb_entidade"];
			}else{
				set_status("ERRO: Selecione um {$rotulos["funcionario"]} para ajustar a {$rotulos["modulo"]}.");
				index();
				exit;
			}
		}

		$_POST["acao"] = "index";
		$_POST["idMotorista"] = $_POST["busca_motorista"];
		$_POST["data"] = $_POST["busca_periodo"][0] ?? date("Y-m-d");
		echo criarHiddenForm(
			"form_ajuste",
			array_keys($_POST),
			array_values($_POST),
			"ajuste_pontofuncionario.php"
		);
		echo "<script>document.form_ajuste.submit();</script>";
		exit;
	}
	/*// funcao paa chamar a pagina de ajuste de ponto do funcionario
	function redirParaAjustePonto(){
		unset($_POST["acao"]);
		if(empty($_POST['busca_motorista'])){
			header("Location: {$_ENV["APP_PATH"]}{$_ENV["CONTEX_PATH"]}/ajuste_pontofuncionario.php");
			exit;
		}

		$_POST["acao"] = "index";
		echo criarHiddenForm(
			"form_ajuste_ponto",
			array_keys($_POST),
			array_values($_POST),
			"ajuste_pontofuncionario.php"
		);
		echo "<script>document.form_ajuste_ponto.submit();</script>";
		exit;
	}*/


	function buscarEspelho(){
		$rotulos = getRotulosEspelho();
		include_once "check_permission.php";
		$temPermissao = temPermissaoMenu('/espelho_ponto.php');
		if(in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário", "Terceirizado", "Tercerizado"]) && !$temPermissao){
			[$_POST["busca_motorista"], $_POST["busca_empresa"]] = [$_SESSION["user_nb_entidade"], $_SESSION["user_nb_empresa"]];
		}
		
		//Confere se há algum erro na pesquisa{
			try{

				if(empty($_POST["busca_periodo"]) && !empty($_POST["periodo_abono"])){
					$_POST["busca_periodo"] = $_POST["periodo_abono"];
					unset($_POST["periodo_abono"]);
				}
				$empresasSelecionadas = normalizarFiltroArray($_POST["busca_empresa"] ?? "");
				$empresasIds = array_map('intval', $empresasSelecionadas);
				$empresasIds = array_values(array_filter($empresasIds, function($v){ return $v > 0; }));
				$condEmpresaBusca = "";
				if(!empty($empresasIds)){
					$condEmpresaBusca = " AND enti_nb_empresa IN (".implode(',', $empresasIds).")";
				}
				$motoristasSelecionados = normalizarFiltroArray($_POST["busca_motorista"] ?? "");
				if(empty($motoristasSelecionados) && !empty($_POST["busca_motorista"])){
					$motoristasSelecionados = [$_POST["busca_motorista"]];
				}
				if(count($motoristasSelecionados) > 1 && empty($empresasIds)){
					throw new Exception("Selecione ao menos uma Empresa para consultar múltiplos {$rotulos["funcionarioPlural"]}.");
				}
	
				//Conferir campos obrigatórios{
					$camposObrig = [
						"busca_empresa" => "Empresa",
						"busca_motorista" => $rotulos["funcionario"],
						"busca_periodo" => "Período"
					];
					$errorMsg = conferirCamposObrig($camposObrig, $_POST);
					
					if(!empty($errorMsg)){
						throw new Exception($errorMsg);
					}
				//}

				if(is_string($_POST["busca_periodo"])){
					$_POST["busca_periodo"] = explode(" - ", $_POST["busca_periodo"]);
				}

				if($_POST["busca_periodo"][0] > date("Y-m-d") || $_POST["busca_periodo"][1] > date("Y-m-d")){
					$_POST["errorFields"][] = "busca_periodo";
					throw new Exception("Data de pesquisa não pode ser após hoje (".date("d/m/Y").").");
				}else{
					if(count($motoristasSelecionados) === 1){
						$motorista = mysqli_fetch_assoc(query(
							"SELECT enti_tx_admissao FROM entidade"
								." WHERE enti_tx_status = 'ativo'"
									.$condEmpresaBusca
									." AND enti_nb_id = ".intval($motoristasSelecionados[0])
								." LIMIT 1;"
							));
	
						if(empty($motorista)){
							$_POST["errorFields"][] = "busca_motorista";
							throw new Exception("Este {$rotulos["funcionario"]} não pertence a esta empresa.");
						}

						//Conferir se a data de início da pesquisa está antes do cadastro do motorista{
							$dataInicio = new DateTime($_POST["busca_periodo"][0]);
							$data_cadastro = new DateTime($motorista["enti_tx_admissao"]);
							if($dataInicio->format("Y-m") < $data_cadastro->format("Y-m")){
								$_POST["errorFields"][] = "busca_periodo";
								throw new Exception("O mês inicial deve ser posterior ou igual ao mês de admissão do {$rotulos["funcionario"]} (".$data_cadastro->format("m/Y").").");
							}
						//}
					}
				}
			}catch(Exception $error){
				set_status("ERRO: ".$error->getMessage());
				unset($_POST["acao"]);
			}
		//}

		index();
		exit;
	}

	function index(){
		$rotulos = getRotulosEspelho();
		
		//ARQUIVO QUE VALIDA A PERMISSAO VIA PERFIL DE USUARIO VINCULADO
        // APATH QUE O USER ESTA TENTANDO ACESSAR PARA VERIFICAR NO PERFIL SE TEM ACESSO2
		include_once "check_permission.php";
        verificaPermissao('/espelho_ponto.php');
        $temPermissao = temPermissaoMenu('/espelho_ponto.php');
		
		cabecalho(empty($_POST["title"])? "Buscar Espelho de {$rotulos["modulo"]}": $_POST["title"]);
		
		// Definir ajustarPonto globalmente logo no início, antes de qualquer tabela
		echo "<script>
			function ajustarPonto(idMotorista, data){
				var form = document.querySelector('form[name=\"form_ajuste_ponto\"]');
				if(!form){ alert('Formulário de ajuste não encontrado.'); return; }
				var fieldId = form.querySelector('[name=\"idMotorista\"]');
				var fieldData = form.querySelector('[name=\"data\"]');
				if(fieldId) fieldId.value = idMotorista;
				if(fieldData) fieldData.value = data;
				form.submit();
			}
		</script>";

		echo "<style>";
		include "css/espelho_ponto.css";
		echo "</style>";

		//CAMPOS DE CONSULTA{
			$condBuscaMotorista = "AND enti_tx_status = 'ativo'";
			$condBuscaEmpresa = "AND empr_tx_status = 'ativo'";

			if(in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário", "Terceirizado", "Tercerizado"]) && !$temPermissao){
                [$_POST["busca_motorista"], $_POST["busca_empresa"]] = [$_SESSION["user_nb_entidade"], $_SESSION["user_nb_empresa"]];
                $condBuscaMotorista .= " AND enti_nb_id = '".$_SESSION["user_nb_entidade"]."'";
				
				$motoristaLogado = mysqli_fetch_assoc(query("SELECT enti_tx_nome FROM entidade WHERE enti_nb_id = ".$_SESSION["user_nb_entidade"]." LIMIT 1"));
				
				$searchFields = [
					campo($rotulos["funcionario"], "nome_motorista_view", $motoristaLogado["enti_tx_nome"], 4, "", "readonly")
				];

			}else{
				$empresasSelecionadas = normalizarFiltroArray($_POST["busca_empresa"] ?? "");

				$empresasOpcoes = [];
				$empresasResult = query("SELECT empr_nb_id, empr_tx_nome FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_tx_nome ASC");
				while($rowEmpresa = mysqli_fetch_assoc($empresasResult)){
					$empresasOpcoes[$rowEmpresa['empr_nb_id']] = $rowEmpresa['empr_tx_nome'];
				}
                
                // Filtros adicionais: Cargo e Setor
				// Ambos influenciam a lista de colaboradores/funcionarios.

				// Condicoes para filtrar colaboradores/funcionarios por Cargo/Setor/Subsetor.
                $condCargoSetor = "";
				
				// Se tiver Cargo selecionado, adiciona ao filtro
				if (!empty($_POST["busca_operacao"])) {
					$condCargoSetor .= " AND enti_tx_tipoOperacao = ".intval($_POST["busca_operacao"]);
				}
				
				// Se tiver Setor selecionado, adiciona ao filtro
                if (!empty($_POST["busca_setor"])) {
                    $condCargoSetor .= " AND enti_setor_id = ".intval($_POST["busca_setor"]);
                }
                if (!empty($_POST["busca_subsetor"])) {
                    $condCargoSetor .= " AND enti_subSetor_id = ".intval($_POST["busca_subsetor"]);
                }
				$funcionariosOpcoes = [];
				if (!empty($empresasSelecionadas)) {
					$idsEmpresa = array_map('intval', $empresasSelecionadas);
					$idsEmpresa = array_values(array_filter($idsEmpresa, function($v){ return $v > 0; }));
					if(!empty($idsEmpresa)){
						$condCargoSetor .= " AND enti_nb_empresa IN (".implode(',', $idsEmpresa).")";

						$funcionariosResult = query(
							"SELECT DISTINCT enti_nb_id, enti_tx_nome, enti_tx_matricula
							 FROM entidade
							 WHERE enti_tx_status = 'ativo'"
							.$condBuscaMotorista.
							" AND enti_nb_empresa IN (".implode(',', $idsEmpresa).")"
							.$condCargoSetor.
							" ORDER BY enti_tx_nome ASC"
						);
						while($rowFuncionario = mysqli_fetch_assoc($funcionariosResult)){
							$funcionariosOpcoes[$rowFuncionario['enti_nb_id']] = "[".$rowFuncionario['enti_tx_matricula']."] ".$rowFuncionario['enti_tx_nome'];
						}
					}
				}

                $hasSubsetor = 0;
                if (!empty($_POST["busca_setor"])) {
                    $row = mysqli_fetch_assoc(query(
                        "SELECT COUNT(*) AS c FROM sbgrupos_documentos WHERE sbgr_tx_status = 'ativo' AND sbgr_nb_idgrup = ".intval($_POST["busca_setor"])." LIMIT 1;"
                    ));
                    $hasSubsetor = (int)($row["c"]??0);
                }

                $searchFields = [
					renderFiltroCheckboxGroup("Empresa*", "busca_empresa", $empresasOpcoes, $_POST["busca_empresa"] ?? "", 3),
					renderFiltroCheckboxGroup("{$rotulos["funcionario"]}*", "busca_motorista", $funcionariosOpcoes, $_POST["busca_motorista"] ?? "", 4),
                    combo_bd("!Cargo", "busca_operacao", (!empty($_POST["busca_operacao"]) ? $_POST["busca_operacao"] : ""), 2, "operacao", "onchange='this.form.submit()'"),
                    combo_bd("!Setor", "busca_setor", (!empty($_POST["busca_setor"]) ? $_POST["busca_setor"] : ""), 2, "grupos_documentos", "onchange='this.form.submit()'"),
                ];
                if ($hasSubsetor > 0) {
                    $searchFields[] = combo_bd("!Subsetor", "busca_subsetor", (!empty($_POST["busca_subsetor"]) ? $_POST["busca_subsetor"] : ""), 2, "sbgrupos_documentos", "onchange='this.form.submit()'", (!empty($_POST["busca_setor"]) ? " AND sbgr_nb_idgrup = ".intval($_POST["busca_setor"])." ORDER BY sbgr_tx_nome ASC" : " AND 1 = 0 ORDER BY sbgr_tx_nome ASC"));
                }

            }

			$searchFields[] = campo(
				"Período", "busca_periodo",
				(!empty($_POST["busca_periodo"])? $_POST["busca_periodo"]: [date("Y-m-01"), date("Y-m-d")]),
				2,
				"MASCARA_PERIODO"
			);
		//}

		//BOTOES{
			$b = [
				botao("Buscar", "buscarEspelho()", "", "", "", "", "btn btn-success"),
			];
			
				$b[] = botao("Cadastrar Abono", "redirParaAbono", "acaoPrevia", $_POST["acao"]??"", "btn btn-secondary");
				$b[] = botao("Solicitar Ajuste", "redirParaAjustePonto()", "acaoPrevia", $_POST["acao"]??"", "btn btn-secondary");

			
			if(!empty($_POST["acao"]) && $_POST["acao"] == "buscarEspelho()"){
				$b[] = "<button class='btn default' type='button' onclick='imprimir(this)'>Imprimir</button>";
			}
		//}
		
		echo abre_form();
		echo campo_hidden("isAutoReload", "");
		echo linha_form($searchFields);
		echo fecha_form($b);
		echo <<<'JS'
		<script>(function(){
			var empresasPendentesAplicacao = false;
			function normalizarFiltroTexto(txt){
				txt = (txt || '').toString().toLowerCase().trim();
				if(typeof txt.normalize === 'function'){
					txt = txt.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
				}
				return txt;
			}

			function aplicarBuscaDropdown(target){
				if(!target){
					return;
				}
				var input = $('.js-filtro-search[data-target="' + target + '"]');
				if(!input.length){
					return;
				}
				var termo = normalizarFiltroTexto(input.val());
				var itens = $('input.js-filtro-checkbox[data-target="' + target + '"]').closest('label.js-filtro-item');
				itens.each(function(){
					var texto = normalizarFiltroTexto($(this).text());
					$(this).toggle(termo === '' || texto.indexOf(termo) !== -1);
				});
			}

			function fecharDropdowns(excecao){
				$('.filtro-dropdown').each(function(){
					if(excecao && $(this).is(excecao)){
						return;
					}
					$(this).removeClass('open');
					$(this).find('.filtro-dropdown-menu').hide();
					$(this).find('.js-filtro-toggle').attr('aria-expanded', 'false');
				});
			}

			function alternarDropdown(botao){
				var wrapper = $(botao).closest('.filtro-dropdown');
				var menu = wrapper.find('.filtro-dropdown-menu').first();
				var isOpen = wrapper.hasClass('open');
				fecharDropdowns(wrapper);
				if(!isOpen){
					wrapper.addClass('open');
					menu.show();
					$(botao).attr('aria-expanded', 'true');
					var campoBusca = wrapper.find('.js-filtro-search').first();
					if(campoBusca.length){
						campoBusca.focus();
						aplicarBuscaDropdown(campoBusca.data('target'));
					}
				}
			}

			function atualizarTituloFiltro(nome){
				var wrapper = $('.filtro-dropdown').has('input.js-filtro-hidden[data-filter-name="' + nome + '"]');
				if(!wrapper.length){
					return;
				}
				var botao = wrapper.find('.js-filtro-toggle').first();
				var labelBase = botao.data('base-label') || nome;
				var selecionados = wrapper.find('input.js-filtro-checkbox[data-target="' + nome + '"]:checked');
				var qtd = selecionados.length;
				var texto = labelBase;
				if(qtd === 1){
					texto = selecionados.first().closest('label').text().trim();
				}else if(qtd > 1){
					texto = labelBase + ' (' + qtd + ')';
				}
				botao.find('.js-filtro-label').text(texto);
			}

			function atualizarHidden(nome){
				var checked = $('input.js-filtro-checkbox[data-target="' + nome + '"]:checked');
				var valores = [];
				checked.each(function(){
					valores.push($(this).val());
				});
				var hiddenInput = $('input.js-filtro-hidden[data-filter-name="' + nome + '"]');
				hiddenInput.val(valores.join(','));
				atualizarTituloFiltro(nome);
			}

			function sincronizarFiltros(){
				$('input.js-filtro-hidden').each(function(){
					atualizarHidden($(this).data('filter-name'));
				});
			}

			$(document).on('change', 'input.js-filtro-checkbox', function(){
				var target = $(this).data('target');
				atualizarHidden(target);
				if(target === 'busca_empresa'){
					empresasPendentesAplicacao = true;
				}
			});

			$(document).on('click', '.js-aplicar-empresa', function(e){
				e.preventDefault();
				e.stopPropagation();
				var form = document.contex_form;
				if(!form){
					return;
				}
				if(!empresasPendentesAplicacao){
					fecharDropdowns();
					return;
				}
				if(form.isAutoReload){
					form.isAutoReload.value = '1';
				}
				$('input.js-filtro-checkbox[data-target="busca_motorista"]').prop('checked', false);
				atualizarHidden('busca_motorista');
				empresasPendentesAplicacao = false;
				form.submit();
			});

			$(document).on('click', '.js-filtro-toggle', function(e){
				e.preventDefault();
				e.stopPropagation();
				alternarDropdown(this);
			});

			$(document).on('click', '.js-filtro-todos', function(){
				var target = $(this).data('target');
				var action = $(this).data('action');
				var marcar = action === 'all';
				var checkboxes = $('input.js-filtro-checkbox[data-target="' + target + '"]');
				checkboxes.each(function(){
					var checked = $(this).prop('checked');
					if(checked !== marcar){
						$(this).click();
					}
				});
				atualizarHidden(target);
			});

			$(document).on('input keyup', '.js-filtro-search', function(){
				aplicarBuscaDropdown($(this).data('target'));
			});

			$(document).on('click', function(){
				fecharDropdowns();
			});

			$(document).on('click', '.filtro-dropdown-menu', function(e){
				e.stopPropagation();
			});

			$('form[name="contex_form"]').on('submit', function(){
				var autoReload = this.isAutoReload && this.isAutoReload.value === '1';
				if(!autoReload && this.isAutoReload){
					this.isAutoReload.value = '';
				}
			});

			sincronizarFiltros();
		})();</script>
JS;
		// if(!in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário"])){
		// 	echo botao("Cadastrar Abono", "redirParaAbono", "", "", "btn btn-secondary");
		// }
		// if(!empty($_POST["acao"]) && $_POST["acao"] == "buscarEspelho()"){
		// 	echo "<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>";
		// }


		$opt = "";
		//Buscar Espelho{
			if(!empty($_POST["acao"]) && $_POST["acao"] == "buscarEspelho()"){
				echo   
					"<div style='display:none' id='tituloRelatorio'>
						<h1>Espelho de {$rotulos["modulo"]}</h1>
						<img id='logo' style='width: 150px' src='".$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/imagens/logo_topo_cliente.png' alt='Logo Empresa Direita'>
					</div>"
				;

				// Converte as datas para objetos DateTime
				$motoristasSelecionados = normalizarFiltroArray($_POST["busca_motorista"] ?? "");
				if(empty($motoristasSelecionados) && !empty($_POST["busca_motorista"])){
					$motoristasSelecionados = [$_POST["busca_motorista"]];
				}
				if(empty($motoristasSelecionados)){
					throw new Exception("Selecione ao menos um {$rotulos["funcionario"]}.");
				}

				[$startDateBase, $endDateBase] = [new DateTime($_POST["busca_periodo"][0]), new DateTime($_POST["busca_periodo"][1]." 23:59:59")];
				$totalMotoristasSelecionados = count($motoristasSelecionados);

				foreach($motoristasSelecionados as $indiceMotorista => $idMotorista){
					$startDate = clone $startDateBase;
					$endDate = clone $endDateBase;
					$rows = [];
					$_POST["busca_motorista"] = $idMotorista;
					
					$motorista = mysqli_fetch_assoc(query(
						"SELECT * FROM entidade
						 LEFT JOIN empresa ON entidade.enti_nb_empresa = empresa.empr_nb_id
						 LEFT JOIN cidade  ON empresa.empr_nb_cidade = cidade.cida_nb_id
						 LEFT JOIN parametro ON enti_nb_parametro = para_nb_id
						 WHERE enti_tx_status = 'ativo'
							 AND enti_nb_id = '".intval($idMotorista)."'
						 LIMIT 1;"
					));

				// 🔧 Gera rótulos dinâmicos baseado na ocupação DESTE motorista
				$rotulosMotorista = getRotulosPorMotorista($motorista);
			
			//Conferir se há dias do mês já endossados{
				$endossoMes = montarEndossoMes($startDate, $motorista);
					
					if(!empty($endossoMes)){
						$diasEndossados = 0;
						foreach($endossoMes["endo_tx_pontos"] as $row){
							$day = DateTime::createFromFormat("d/m/Y", $row["data"]);
							if($day >= $startDate && $day <= $endDate){
								// Não aplicar tolerância aqui - os dados do endosso já foram processados
								$diasEndossados++;
								$rows[] = $row;
							}
						}
						if($diasEndossados > 0){
							$startDate->modify("+{$diasEndossados} day");
						}
					}
				//}

				
				// Loop for para percorrer as datas
				$descFaltasNaoJustificadas = "00:00";
				$qtdDiasNaoJustificados = 0;
				for ($date = $startDate; $date <= $endDate; $date->modify("+1 day")){
					$aDetalhado = diaDetalhePonto($motorista, $date->format("Y-m-d"));

					// INICIO Lógica de Tolerância
					if(!empty($motorista["para_tx_tolerancia"]) && isset($aDetalhado["diffSaldo"])){
						$tolerancia = intval($motorista["para_tx_tolerancia"]);
						$saldoDiarioStr = strip_tags($aDetalhado["diffSaldo"]);
						$parts = explode(":", $saldoDiarioStr);
						
						if(count($parts) == 2){
							$minutos = intval($parts[0]) * 60 + ($parts[0][0] == '-' ? -1 : 1) * intval($parts[1]);
							if(abs($minutos) <= $tolerancia){
								$aDetalhado["diffSaldo"] = "00:00";
							}
						}
					}
					// FIM Lógica de Tolerância
					
					/* Descomentar ao conseguir adaptar a lógica da página de nao_conformidade para espelho_ponto
						if(!empty($_POST["naoConformidade"])){
							$rowString = implode(", ", array_values($aDetalhado));
							$qtdErros = (
									substr_count($rowString, "fa-warning") 																				//Conta todos os triângulos, pois todos os triângulos são alertas de não conformidade.
									+((is_int(strpos($rowString, "fa-info-circle")))*(substr_count($rowString, "color:red;") + substr_count($rowString, "color:orange;")))	//Conta os círculos que sejam vermelhos ou laranjas.
								)
								*!(is_int(strpos($rowString, "Batida início de jornada não registrada!")) && is_int(strpos($rowString, "Abono: ")))
							;
						
							if($qtdErros == 0){
								$keyPrimColunaTotal = array_search("diffRefeicao", array_keys($aDetalhado));
								for($f2 = $keyPrimColunaTotal; $f2 < count($aDetalhado); $f2++){
									$totalResumo[$f2-$keyPrimColunaTotal] = operarHorarios([$totalResumo[$f2-$keyPrimColunaTotal], strip_tags(array_values($aDetalhado)[$f2])], "-");
								}
								continue;
							}
						}


						$row = array_values(array_merge([verificaTolerancia($aDetalhado["diffSaldo"], $date->format("Y-m-d"), $motorista["enti_nb_id"])], $aDetalhado));
						for($f = 0; $f < sizeof($row)-1; $f++){
							if(in_array($f, [3, 4, 5, 6, 12])){//Se for das colunas de início de jornada, refeição ou "Jornada Prevista", não apaga
								continue;
							}
							if($row[$f] == "00:00"){
								$row[$f] = "";
							}
						}
						$rows[] = $row;
					//*/

					if(isset($aDetalhado['inicioEscala'])) unset($aDetalhado['inicioEscala']);
					if(isset($aDetalhado['fimEscala'])) unset($aDetalhado['fimEscala']);

					$colunasAManterZeros = ["inicioJornada", "inicioRefeicao", "fimRefeicao", "fimJornada", "jornadaPrevista", "diffSaldo"];
					foreach($aDetalhado as $key => &$value){
						if(in_array($key, $colunasAManterZeros)){//Se for das colunas de início de jornada, refeição ou "Jornada Prevista", mantém os valores zerados.
							continue;
						}
						if($value == "00:00"){
							$value = "";
						}
					}
					
					$row = array_merge([verificaTolerancia($aDetalhado["diffSaldo"], $date->format("Y-m-d"), $motorista["enti_nb_id"])], $aDetalhado);
					
					// Substituir "00:00" por vazio em todos os campos da linha final, exceto colunas protegidas se necessário
					foreach($row as $key => &$val){
						if(is_string($val) && trim($val) === "00:00"){
							$val = "";
						}
					}

					// Se for terceirizado, reduzir a linha para as colunas solicitadas
					if(!empty($rotulosMotorista["ehTerceirizado"]) && $rotulosMotorista["ehTerceirizado"]){
						$primeiro = array_values($row)[0] ?? ""; // marcador de tolerância (posição 0)
						$filtered = [];
						$filtered[] = $primeiro; // coluna vazia/marcador
						$filtered['data'] = $row['data'] ?? '';
						$filtered['diaSemana'] = $row['diaSemana'] ?? '';
						$filtered['inicioJornada'] = $row['inicioJornada'] ?? '';
						$filtered['fimJornada'] = $row['fimJornada'] ?? '';
						$filtered['jornadaPrevista'] = $row['jornadaPrevista'] ?? '';
						// 'diffJornadaEfetiva' corresponde a "JORNADA EFETIVA"
						$filtered['diffJornadaEfetiva'] = $row['diffJornadaEfetiva'] ?? $row['diffJornada'] ?? '';
						$filtered['diffSaldo'] = $row['diffSaldo'] ?? '';
						$row = $filtered;
					}
					if(strpos($row["inicioJornada"], "Batida início de jornada não registrada!") !== false){
						$descFaltasNaoJustificadas = operarHorarios([$descFaltasNaoJustificadas, $row["jornadaPrevista"]], "+");
						$qtdDiasNaoJustificados++;
					}

					$rows[] = $row;
				}

				$totalResumo = setTotalResumo(array_slice(array_keys($rows[0]), 7));
				
				somarTotais($totalResumo, $rows);


				$mensagemParametro = montarMensagemParametro($motorista);


				$ultimoEndosso = mysqli_fetch_assoc(query(
					"SELECT endo_tx_filename FROM endosso"
						." WHERE endo_tx_status = 'ativo'"
							." AND endo_nb_entidade = '".$motorista["enti_nb_id"]."'"
							." AND endo_tx_ate < '".$_POST["busca_periodo"][0]."'"
						." ORDER BY endo_tx_ate DESC"
						." LIMIT 1;"
				));
				
				
				$saldoAnterior = "00:00";
				if(!empty($ultimoEndosso) && file_exists("{$_SERVER["DOCUMENT_ROOT"]}{$_ENV["APP_PATH"]}{$_ENV["CONTEX_PATH"]}/arquivos/endosso/{$ultimoEndosso["endo_tx_filename"]}.csv")){
					$ultimoEndosso = lerEndossoCSV($ultimoEndosso["endo_tx_filename"]);
					$saldoAnterior = $ultimoEndosso["totalResumo"]["saldoFinal"]?? "--:--";
				}elseif(!empty($motorista["enti_tx_banco"])){
					$saldoAnterior = $motorista["enti_tx_banco"];
					$saldoAnterior = ($saldoAnterior == "00:00" && strlen($saldoAnterior) > 5)? substr($saldoAnterior, 1): $saldoAnterior;
				}

				$saldoBruto = $totalResumo["diffSaldo"];
				$saldoBruto = operarHorarios([$saldoAnterior, $totalResumo["diffSaldo"]], "+");

				$saldosMotorista = "SALDOS: <br>
					<div class='table-responsive' style='display: flex; justify-content: space-between; align-items: center;'>
						<table class='table w-auto text-xsmall bold table-bordered table-striped table-condensed flip-content table-hover compact' id='saldo'>
							<thead>
								<tr>
									<th>Anterior:</th>
									<th>Período:</th>
									<th>Bruto:</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td>".($saldoAnterior?? "--:--")."</td>
									<td>".($totalResumo["diffSaldo"]?? "--:--")."</td>
									<td>".($saldoBruto?? "--:--")."</td>
								</tr>
							</tbody>
						</table>
						<div style='font-weight: 600;'>
							".(($motorista["para_tx_descFaltas"] == "sim" && $descFaltasNaoJustificadas != "00:00")? "Serão descontadas {$descFaltasNaoJustificadas} horas por {$qtdDiasNaoJustificados} faltas não justificadas.":"")."
						</div>
					</div>"
				;
						
				$periodoPesquisa = "De ".date("d/m/Y", strtotime($_POST["busca_periodo"][0]))." até ".date("d/m/Y", strtotime($_POST["busca_periodo"][1]));
			
				$cabecalho = [
					"", "DATA", "<div style='margin:11px'>DIA</div>", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA",
					"REFEIÇÃO"/*, ESPERA*/, "DESCANSO"/*, "REPOUSO"*/, "JORNADA", 
					"JORNADA PREVISTA", "JORNADA EFETIVA"/*, "MDC"*/, "INTERSTÍCIO", "H.E. {$motorista["enti_tx_percHESemanal"]}%", "H.E. {$motorista["enti_tx_percHEEx"]}%",
					"ADICIONAL NOT."/*, "ESPERA INDENIZADA"*/, "SALDO DIÁRIO(**)"
				];

				// Se o motorista for terceirizado, exibe apenas as colunas solicitadas.
				if(!empty(
					$rotulosMotorista["ehTerceirizado"]
				) && $rotulosMotorista["ehTerceirizado"]) {
					$cabecalho = [
						"", "DATA", "<div style='margin:11px'>DIA</div>", "INÍCIO JORNADA", "FIM JORNADA", "JORNADA PREVISTA", "JORNADA EFETIVA", "SALDO DIÁRIO(**)"
					];
				}

				if(in_array($motorista["enti_tx_ocupacao"], ["Ajudante", "Motorista"])){
					$cabecalho = array_merge(
						array_slice($cabecalho, 0, 8), 
						["ESPERA"], 
						array_slice($cabecalho, 8, 1), 
						["REPOUSO"], 
						array_slice($cabecalho, 9, 3), 
						["MDC"], 
						array_slice($cabecalho, 12, 4), 
						["ESPERA INDENIZADA"], 
						array_slice($cabecalho, 16, count($cabecalho))
					);
				}

				unset(
					$totalResumo["saldoAnterior"],
					$totalResumo["saldoBruto"],
					$totalResumo["he50APagar"],
					$totalResumo["he100APagar"],
					$totalResumo["saldoFinal"],
					$totalResumo["horas_descontadas"],
					$totalResumo["desconto_manual"],
					$totalResumo["desconto_faltas_nao_justificadas"]
				);
				$rowTotal = array_values(array_merge(["", "", "", "", "", "", "<b>TOTAL</b>"], $totalResumo));
				foreach($rowTotal as &$valTotal){
					if(is_string($valTotal) && trim($valTotal) === "00:00"){
						$valTotal = "";
					}
				}
				$rows[] = $rowTotal;

				echo abre_form(
					"<table class='table w-auto text-xsmall bold table-bordered table-striped table-condensed flip-content table-hover compact espelho-cabecalho-info'>
						<thead>
							<tr>
								<th>Empresa</th>
								<th>{$rotulosMotorista["funcionario"]}</th>
								<th>Parâmetro</th>
							</tr>
						<tbody>
							<tr>
								<td>{$motorista["empr_tx_nome"]}</td>
								<td>[{$motorista["enti_tx_matricula"]}] {$motorista["enti_tx_nome"]}</td>
								<td>{$mensagemParametro}</td>
						</tbody>
					</table>
					{$periodoPesquisa}<br>
					{$saldosMotorista}"
				);
				echo montarTabelaPonto($cabecalho, $rows);
				echo fecha_form();

				unset($_POST["errorFields"]);

				
				$params = array_merge($_POST, [
					"acao" => "index",
					"acaoPrevia" => $_POST["acao"],
					"idMotorista" => "",
					"data" => "",
					"HTTP_REFERER" => (!empty($_POST["HTTP_REFERER"])? $_POST["HTTP_REFERER"]: $_SERVER["REQUEST_URI"])
				]);

				
				if(in_array($_SESSION["user_tx_nivel"],["Administrador", "Super Administrador"])){
					$paginaDestino = "ajuste_ponto.php";
				}else{
					$paginaDestino = "ajuste_pontofuncionario.php";
				}
				echo criarHiddenForm(
					"form_ajuste_ponto",
					array_keys($params),
					array_values($params),
					$paginaDestino
				);
				unset($params);
					if($indiceMotorista < ($totalMotoristasSelecionados - 1)){
						echo "<div style='page-break-after:always;'></div>";
					}
				}
			}
		//}
		
		echo carregarJS($opt);
		rodape();
	}

	function carregarJS($opt): string{
		$rotulos = getRotulosEspelho();

		
		$select2URL = 
			"{$_ENV["URL_BASE"]}{$_ENV["APP_PATH"]}/contex20/select2.php"
			."?path={$_ENV["APP_PATH"]}{$_ENV["CONTEX_PATH"]}"
			."&tabela=entidade"
			."&colunas=enti_tx_matricula"
			."&limite=15"
		;

		$empresaLogoId = $_POST["busca_empresa"] ?? "";
		$empresaLogoLista = normalizarFiltroArray($empresaLogoId);
		if(!empty($empresaLogoLista)){
			$empresaLogoId = intval($empresaLogoLista[0]);
		}

		$logoEmpresa = mysqli_fetch_assoc(query(
            "SELECT empr_tx_logo FROM empresa
                WHERE empr_tx_status = 'ativo'
					AND empr_nb_id = '".intval($empresaLogoId)."'
				LIMIT 1;"
			))["empr_tx_logo"] ?? "";
		$logoEmpresa = addslashes($logoEmpresa);
		$logoEmpresa = str_replace('`', '\`', $logoEmpresa);

		return <<<JS
		<script>
				function obterFiltroCheckbox(nome){
					var valor = \$('input.js-filtro-hidden[data-filter-name="' + nome + '"]').val() || '';
					if(!valor){
						return [];
					}
					return valor.split(',').map(function(item){
						return item.trim();
					}).filter(function(item){
						return item !== '';
					});
				}

				function montarCondicaoLista(coluna, valores, numerico){
					if(!valores || !valores.length){
						return '';
					}
					if(numerico){
						valores = valores.map(function(v){
							return parseInt(v, 10);
						}).filter(function(v){
							return !isNaN(v) && v > 0;
						});
						if(!valores.length){
							return '';
						}
						return ' AND ' + coluna + ' IN (' + valores.join(',') + ')';
					}
					valores = valores.map(function(v){
						return '"' + String(v).replace(/"/g, '\\"') + '"';
					});
					return ' AND ' + coluna + ' IN (' + valores.join(',') + ')';
				}

				function selecionaMotorista(){
					var condicoes = '&condicoes=' + encodeURI('enti_tx_ocupacao IN ("Motorista", "Ajudante", "Funcionário", "Terceirizado", "Tercerizado")');
					var empresas = obterFiltroCheckbox('busca_empresa');
					var ocupacoes = obterFiltroCheckbox('busca_ocupacao');
					condicoes += encodeURI(montarCondicaoLista('enti_nb_empresa', empresas, true));
					condicoes += encodeURI(montarCondicaoLista('enti_tx_ocupacao', ocupacoes, false));

					if(\$('.busca_motorista').data('select2')){
						\$('.busca_motorista').select2('destroy');
					}
					\$.fn.select2.defaults.set('theme', 'bootstrap');
					\$('.busca_motorista').select2({
						language: {
							noResults: function(){
								return 'Nenhum {$rotulos["funcionario"]} encontrado para a combinação do filtro';
							}
						},
						placeholder: 'Selecione um item',
						allowClear: true,
						ajax: {
							url: '{$select2URL}'+condicoes,
							dataType: 'json',
							delay: 250,
							processResults: function(data){
								return {
									results: data
								};
							},
							cache: true
						}
					});
				}

				\$(function(){
					selecionaMotorista();
				});

				function imprimir(){
					// Procurar tabelas de espelho e extrair o bloco .portlet correspondente
					var tabelas = document.querySelectorAll('.espelho-cabecalho-info');
					if(!tabelas || tabelas.length === 0){
						alert('Nenhum espelho encontrado para impressão.');
						return;
					}

					var blocos = [];
					for(var i=0;i<tabelas.length;i++){
						var t = tabelas[i];
						var portlet = t.closest('.portlet');
						if(portlet){
							var clone = portlet.cloneNode(true);
							// remover botões de impressão se existirem
							var btns = clone.querySelectorAll('[data-acao-imprimir]');
							for(var j=0;j<btns.length;j++){ if(btns[j] && btns[j].parentNode) btns[j].parentNode.removeChild(btns[j]); }
							blocos.push(clone.outerHTML);
						}
					}

					if(blocos.length === 0){ alert('Nenhum espelho válido encontrado para impressão.'); return; }

					var dataAtual = new Date().toLocaleString();
					var cabecalhoHTML = '<header class="print-header">'
						+ '<img src="./imagens/logo_topo_cliente.png" alt="Logo Esquerda">'
						+ '<h1>Registros</h1>'
						+ '</header>';
					var rodapeHTML = "<footer class='print-footer'><div><strong>TECHPS®</strong></div><div><em>Gerado em: " + dataAtual + "</em></div></footer>";
					var paginasHTML = blocos.map(function(bloco){
						return '<section class="print-page">'
							+ cabecalhoHTML
							+ '<main class="conteudo-impressao">' + bloco + '</main>'
							+ rodapeHTML
							+ '</section>';
					}).join('');

					var estilosAtuais = '';
					var nosEstilo = document.querySelectorAll('link[rel="stylesheet"], style');
					for(var k=0; k<nosEstilo.length; k++){
						estilosAtuais += nosEstilo[k].outerHTML;
					}
					var ajusteCorImpressao = '<style>@media print{*{-webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important;}}</style>';

					var janela = window.open('','_blank');
					if(!janela){ alert('Popup bloqueado. Permita popups para este site e tente novamente.'); return; }
					janela.document.write('<html><head><title>Impressão - Registros</title><meta charset="utf-8">' + estilosAtuais + '<link rel="stylesheet" href="./css/impressao_espelho.css">' + ajusteCorImpressao + '</head><body>' + paginasHTML + '<script>window.onload=function(){window.print();}; window.addEventListener("afterprint", function(){ window.close(); });<\/script></body></html>');
					janela.document.close();
				}

				// Adiciona botão de imprimir individual em cada portlet que contenha um espelho
				(function(){
					try{
						var tabelas = document.querySelectorAll('.espelho-cabecalho-info');
						for(var i=0;i<tabelas.length;i++){
							var portlet = tabelas[i].closest('.portlet');
							if(!portlet) continue;
							// Não duplicar botão
							if(portlet.querySelector('[data-acao-imprimir]')) continue;
							var btn = document.createElement('button');
							btn.type = 'button';
							btn.className = 'btn btn-sm btn-primary imprimir-individual';
							btn.setAttribute('data-acao-imprimir','individual');
							btn.title = 'Imprimir este espelho';
							btn.innerHTML = '<i class="glyphicon glyphicon-print"></i> Imprimir';
							btn.style.marginLeft = '8px';
							btn.style.marginTop = '4px';
							btn.style.display = 'inline-flex';
							btn.style.alignItems = 'center';
							btn.style.gap = '6px';
							btn.onclick = function(e){ e.stopPropagation(); imprimirIndividual(this); };
							// Tenta inserir na área de título, se existir
							var title = portlet.querySelector('.portlet-title');
							if(title){
								var tools = title.querySelector('.tools');
								if(tools){ tools.appendChild(btn); }
								else { title.appendChild(btn); }
							}else{
								portlet.insertBefore(btn, portlet.firstChild);
							}
						}
					}catch(e){ console && console.error && console.error(e); }
				})();

				function imprimirIndividual(btn){
					if(!btn) return;
					var portlet = btn.closest('.portlet');
					if(!portlet) return;
					var clone = portlet.cloneNode(true);
					// remover botões de impressão se existirem
					var btns = clone.querySelectorAll('[data-acao-imprimir]');
					for(var j=0;j<btns.length;j++){ if(btns[j] && btns[j].parentNode) btns[j].parentNode.removeChild(btns[j]); }
					var bloco = clone.outerHTML;
					var dataAtual = new Date().toLocaleString();
					var cabecalhoHTML = '<header class="print-header">'
						+ '<img src="./imagens/logo_topo_cliente.png" alt="Logo Esquerda">'
						+ '<h1>Registros</h1>'
						+ '</header>';
					var rodapeHTML = "<footer class='print-footer'><div><strong>TECHPS®</strong></div><div><em>Gerado em: " + dataAtual + "</em></div></footer>";
					var estilosAtuais = '';
					var nosEstilo = document.querySelectorAll('link[rel="stylesheet"], style');
					for(var k=0; k<nosEstilo.length; k++){
						estilosAtuais += nosEstilo[k].outerHTML;
					}
					var ajusteCorImpressao = '<style>@media print{*{-webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important;}}</style>';
					var pagina = '<section class="print-page">' + cabecalhoHTML + '<main class="conteudo-impressao">' + bloco + '</main>' + rodapeHTML + '</section>';
					var janela = window.open('','_blank');
					if(!janela){ alert('Popup bloqueado. Permita popups para este site e tente novamente.'); return; }
					janela.document.write('<html><head><title>Impressão - Registros</title><meta charset="utf-8">' + estilosAtuais + '<link rel="stylesheet" href="./css/impressao_espelho.css">' + ajusteCorImpressao + '</head><body>' + pagina + '<script>window.onload=function(){window.print();}; window.addEventListener("afterprint", function(){ window.close(); });<\/script></body></html>');
					janela.document.close();
				}
			</script>
		JS;
	}
