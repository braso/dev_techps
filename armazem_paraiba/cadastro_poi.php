<?php

		ini_set("display_errors", 1);
		error_reporting(E_ALL);

	include_once "load_env.php";
	include "check_permission.php";
	include_once "conecta.php";

	// --- ROTEADOR DE AÇÕES ---
	// Verifica se chegou alguma ação via POST
	if(!empty($_POST['acao'])){
		$acao = $_POST['acao'];
		// Remove parênteses se houver (ex: "editarPoi()" -> "editarPoi")
		$acao = preg_replace('/\(.*\)$/', '', $acao);
		if(function_exists($acao)){
			$acao();
			exit;
		} else {
			echo "Erro: Função '$acao' não existe no PHP.";
			exit;
		}
	}

	/**
	 * Cria automaticamente a tabela de POIs caso ela ainda nao exista.
	 * Campos: Nome, Cnpj, Contato, Latitude, Longitude e Raio (50m por padrao).
	 */
	function ensurePoiSchema(){
		$dbRow = mysqli_fetch_assoc(query("SELECT DATABASE() AS db"));
		$db = strval($dbRow["db"] ?? "");
		if($db === ""){
			return;
		}

		$exists = mysqli_fetch_assoc(query(
			"SELECT 1 AS ok
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = ?
				AND TABLE_NAME = 'poi'
			LIMIT 1",
			"s",
			[$db]
		));

		if(empty($exists)){
			query(
				"CREATE TABLE IF NOT EXISTS poi (
					poi_nb_id INT AUTO_INCREMENT PRIMARY KEY,
					poi_tx_nome VARCHAR(150) NOT NULL,
					poi_tx_cnpj VARCHAR(20) NOT NULL DEFAULT '',
					poi_tx_contato VARCHAR(100) NOT NULL DEFAULT '',
					poi_tx_latitude DECIMAL(10,7) NOT NULL,
					poi_tx_longitude DECIMAL(10,7) NOT NULL,
					poi_nb_raio INT NOT NULL DEFAULT 50,
					poi_tx_icone VARCHAR(50) NOT NULL DEFAULT '',
					poi_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
					poi_nb_userCadastro INT DEFAULT NULL,
					poi_tx_dataCadastro DATETIME NOT NULL,
					UNIQUE KEY uniq_poi_latlong (poi_tx_latitude, poi_tx_longitude),
					KEY idx_poi_status (poi_tx_status)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
			);
			query(
				"CREATE TABLE IF NOT EXISTS poi_acao_esperada (
					paes_nb_id INT AUTO_INCREMENT PRIMARY KEY,
					paes_nb_poi INT NOT NULL,
					paes_tx_codigo VARCHAR(50) NOT NULL,
					UNIQUE KEY uk_poi_acao (paes_nb_poi, paes_tx_codigo)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
			);
			return;
		}

		// Garante colunas novas em instalacoes antigas.
		$cols = mysqli_fetch_all(query(
			"SELECT COLUMN_NAME
			FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = ?
				AND TABLE_NAME = 'poi'",
			"s",
			[$db]
		), MYSQLI_ASSOC);
		$colNames = array_flip(array_map(fn($r) => strval($r["COLUMN_NAME"] ?? ""), $cols ?: []));

		if(!isset($colNames["poi_nb_raio"])){
			query("ALTER TABLE poi ADD COLUMN poi_nb_raio INT NOT NULL DEFAULT 50");
		}
		migrarIconePoi();
	}

	/**
	 * Migração: adiciona coluna poi_tx_icone se não existir
	 */
	function migrarIconePoi(){
		$dbRow = mysqli_fetch_assoc(query("SELECT DATABASE() AS db"));
		$db = strval($dbRow["db"] ?? "");
		if($db === ""){ return; }

		$cols = mysqli_fetch_all(query(
			"SELECT COLUMN_NAME
			 FROM information_schema.COLUMNS
			 WHERE TABLE_SCHEMA = ?
			   AND TABLE_NAME = 'poi'",
			"s", [$db]
		), MYSQLI_ASSOC);
		$colNames = array_flip(array_map(fn($r) => strval($r["COLUMN_NAME"] ?? ""), $cols ?: []));

		if(!isset($colNames["poi_tx_icone"])){
			query("ALTER TABLE poi ADD COLUMN poi_tx_icone VARCHAR(50) NOT NULL DEFAULT '' AFTER poi_nb_raio");
		}
		if(!isset($colNames["poi_tx_endereco"])){
			query("ALTER TABLE poi ADD COLUMN poi_tx_endereco VARCHAR(255) NOT NULL DEFAULT '' AFTER poi_tx_icone");
		}
		if(!isset($colNames["poi_tx_cep"])){
			query("ALTER TABLE poi ADD COLUMN poi_tx_cep VARCHAR(10) NOT NULL DEFAULT '' AFTER poi_tx_endereco");
		}
		if(!isset($colNames["poi_tx_imagem"])){
			query("ALTER TABLE poi ADD COLUMN poi_tx_imagem VARCHAR(255) NOT NULL DEFAULT '' AFTER poi_tx_cep");
		}

		// Garante a tabela de ações esperadas
		$dbRow2 = mysqli_fetch_assoc(query("SELECT DATABASE() AS db"));
		$db2 = strval($dbRow2["db"] ?? "");
		if($db2 !== ""){
			$chkAcao = mysqli_fetch_assoc(query(
				"SELECT 1 AS ok FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'poi_acao_esperada' LIMIT 1",
				"s", [$db2]
			));
			if(empty($chkAcao)){
				query("CREATE TABLE IF NOT EXISTS poi_acao_esperada (
					paes_nb_id INT AUTO_INCREMENT PRIMARY KEY,
					paes_nb_poi INT NOT NULL,
					paes_tx_codigo VARCHAR(50) NOT NULL,
					UNIQUE KEY uk_poi_acao (paes_nb_poi, paes_tx_codigo)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
			}
		}
	}

	/**
	 * Exclui (inativa) um POI.
	 */
	function excluirPoi(){
		if(!empty($_POST["id"])){
			atualizar("poi", ["poi_tx_status"], ["inativo"], intval($_POST["id"]));
		}
		index();
		exit;
	}

	/**
	 * Carrega um POI para edicao.
	 */
	function editarPoi(){
		global $a_mod;
		$a_mod = carregar("poi", $_POST["id"]);
		visualizarCadastro();
		exit;
	}

	/**
	 * Salva imagem enviada no diretório arquivos/poi/
	 */
	function salvarImagemPoi($arquivo){
		$dir = __DIR__ . "/arquivos/poi";
		if(!is_dir($dir)){
			@mkdir($dir, 0755, true);
		}
		$ext = strtolower(pathinfo($arquivo["name"], PATHINFO_EXTENSION));
		$extsPermitidas = ["jpg", "jpeg", "png", "gif", "webp"];
		if(!in_array($ext, $extsPermitidas)){
			return false;
		}
		$nomeUnico = "poi_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
		$destino = $dir . "/" . $nomeUnico;
		if(move_uploaded_file($arquivo["tmp_name"], $destino)){
			return "arquivos/poi/" . $nomeUnico;
		}
		return false;
	}

	/**
	 * Cadastra ou atualiza um POI.
	 */
	function cadastrarPoi(){
		ensurePoiSchema();

		$camposObrig = [
			"nome" 		=> "Nome",
			"latitude" 	=> "Latitude",
			"longitude" => "Longitude"
		];
		$errorMsg = conferirCamposObrig($camposObrig, $_POST);
		if(!empty($errorMsg)){
			set_status("ERRO: ".$errorMsg);
			visualizarCadastro();
			exit;
		}

		// Normaliza CNPJ (apenas numeros) e raio (default 50m).
		$cnpj  = preg_replace('/[^0-9]/', '', (string)($_POST["cnpj"] ?? ""));
		$raio  = intval($_POST["raio"] ?? 50);
		if($raio <= 0){ $raio = 50; }

		// Verifica duplicidade de nome
		$nome = trim($_POST["nome"]);
		$editId = intval($_POST["id"] ?? 0);
		$dupCheck = query("SELECT poi_nb_id FROM poi WHERE poi_tx_nome = ? AND poi_tx_status = 'ativo' LIMIT 1", "s", [$nome]);
		$dupRow = $dupCheck ? mysqli_fetch_assoc($dupCheck) : null;
		if($dupRow && intval($dupRow["poi_nb_id"] ?? 0) !== $editId){
			set_status("ERRO: Já existe um POI com este nome.");
			visualizarCadastro();
			exit;
		}

		// Upload de imagem
		$caminhoImagem = "";
		if(!empty($_FILES["imagem"]) && $_FILES["imagem"]["error"] === UPLOAD_ERR_OK){
			$caminhoImagem = salvarImagemPoi($_FILES["imagem"]);
			if(!$caminhoImagem){
				set_status("ERRO: Falha ao salvar imagem do local.");
				visualizarCadastro();
				exit;
			}
		}

		$dados = [
			"poi_tx_nome" 		=> trim($_POST["nome"]),
			"poi_tx_cnpj" 		=> $cnpj,
			"poi_tx_contato" 	=> trim($_POST["contato"] ?? ""),
			"poi_tx_endereco" 	=> trim($_POST["endereco"] ?? ""),
			"poi_tx_cep" 		=> trim($_POST["cep"] ?? ""),
			"poi_tx_latitude" 	=> str_replace(",", ".", $_POST["latitude"]),
			"poi_tx_longitude"	=> str_replace(",", ".", $_POST["longitude"]),
			"poi_nb_raio" 		=> $raio,
			"poi_tx_icone" 		=> trim($_POST["icone"] ?? ""),
			"poi_tx_status" 	=> "ativo"
		];

		if($editId > 0){
			if($caminhoImagem){
				$dados["poi_tx_imagem"] = $caminhoImagem;
				// Remove imagem antiga
				$antigo = mysqli_fetch_assoc(query("SELECT poi_tx_imagem FROM poi WHERE poi_nb_id = ?", "i", [$editId]));
				if(!empty($antigo["poi_tx_imagem"]) && file_exists($antigo["poi_tx_imagem"])){
					@unlink($antigo["poi_tx_imagem"]);
				}
			}
			atualizar("poi", array_keys($dados), array_values($dados), strval($editId));
		}else{
			if($caminhoImagem){
				$dados["poi_tx_imagem"] = $caminhoImagem;
			}
			$dados["poi_nb_userCadastro"]  = $_SESSION["user_nb_id"] ?? null;
			$dados["poi_tx_dataCadastro"]  = date("Y-m-d H:i:s");
			$res = inserir("poi", array_keys($dados), array_values($dados));
			if(gettype($res[0]) == "object"){
				set_status("ERRO: ".$res[0]->getMessage());
				visualizarCadastro();
				exit;
			}
			$editId = intval($res[0]);
		}

		// Salva as ações esperadas
		$acoes = (array)($_POST['acoes_esperadas'] ?? []);
		$acoes = array_filter($acoes, function($v){ return trim($v) !== ''; });
		$acoes = array_unique(array_map('trim', $acoes));
		// Remove ações existentes e insere as selecionadas
		query("DELETE FROM poi_acao_esperada WHERE paes_nb_poi = ?", "i", [$editId]);
		foreach($acoes as $acao){
			if($acao !== ''){
				query("INSERT INTO poi_acao_esperada (paes_nb_poi, paes_tx_codigo) VALUES (?, ?)", "is", [$editId, $acao]);
			}
		}

		index();
		exit;
	}

	/**
	 * Tela de cadastro/edicao de POI.
	 */
	function visualizarCadastro(){
		global $a_mod;

		cabecalho("Cadastro de POI");

		if(empty($a_mod)){ // Cadastro novo
			$values  = $_POST;
			$prefix  = "";
			$btn_txt = "Cadastrar";
		}else{ // Edicao
			$values  = $a_mod;
			$prefix  = "poi_";
			$btn_txt = "Atualizar";
		}

		$campos = ["nome", "cnpj", "contato", "endereco", "cep", "latitude", "longitude", "raio", "icone", "imagem"];
		$input_values = [];
		foreach($campos as $campo){
			if(empty($input_values[$campo])){
				$input_values[$campo] = !empty($values[$prefix."tx_".$campo]) ? $values[$prefix."tx_".$campo] : (!empty($values[$prefix."nb_".$campo]) ? $values[$prefix."nb_".$campo] : "");
			}
		}
		if(empty($input_values["raio"])){
			$input_values["raio"] = 50;
		}

		// Carrega ações esperadas existentes (edição)
		$acoesSelecionadas = [];
		$editId = intval($values[$prefix."nb_id"] ?? 0);
		if($editId > 0){
			$rsAcoes = query("SELECT paes_tx_codigo FROM poi_acao_esperada WHERE paes_nb_poi = ?", "i", [$editId]);
			while($rsAcoes && ($r = mysqli_fetch_assoc($rsAcoes))){ $acoesSelecionadas[] = $r['paes_tx_codigo']; }
		}

		$iconeAtual = $input_values["icone"] ?? "";
		$tiposPoi = [];
		$rsTipos = query("SELECT poti_tx_codigo, poti_tx_nome, poti_tx_emoji FROM poi_tipo WHERE poti_tx_status = 'ativo' ORDER BY poti_tx_nome ASC");
		while($rsTipos && ($r = mysqli_fetch_assoc($rsTipos))){ $tiposPoi[] = $r; }
		$iconeHtml = "<select name='icone' class='form-control' id='poi_icone'>";
		$iconeHtml .= "<option value=''>Selecione o tipo</option>";
		foreach($tiposPoi as $t){
			$sel = ($t['poti_tx_codigo'] === $iconeAtual) ? " selected" : "";
			$iconeHtml .= "<option value='".htmlspecialchars($t['poti_tx_codigo'])."' data-emoji='".htmlspecialchars($t['poti_tx_emoji'])."'$sel>".htmlspecialchars($t['poti_tx_emoji'])." ".htmlspecialchars($t['poti_tx_nome'])."</option>";
		}
		$iconeHtml .= "<option value='__novo__' style='color:#004173; font-weight:600;'>➕ Criar novo tipo...</option>";
		$iconeHtml .= "</select>";

		$imagemAtual = $input_values["imagem"] ?? "";
		$imagemHtml = "<input type='file' name='imagem' accept='image/png,image/jpeg,image/gif,image/webp' class='form-control' style='padding:6px 10px;'>";
		if($imagemAtual){
			$cacheBreaker = "?v=" . time();
			$imagemHtml .= "<div style='margin-top:6px;'><img src='{$imagemAtual}{$cacheBreaker}' style='max-width:100%; max-height:80px; border-radius:4px; border:1px solid #ddd;'></div>";
		}

		$acoesDisponiveis = ["Jornada", "Refeição", "Espera", "Descanso", "Repouso", "Pernoite"];
		$acoesHtml = "<div class='col-sm-4 margin-bottom-5 campo-fit-content'><label>Ações Esperadas</label><select name='acoes_esperadas[]' id='acoes_esperadas' multiple class='form-control' style='width:100%;'>";
		foreach($acoesDisponiveis as $acao){
			$sel = in_array($acao, $acoesSelecionadas) ? " selected" : "";
			$acoesHtml .= "<option value='".htmlspecialchars($acao, ENT_QUOTES)."'{$sel}>".htmlspecialchars($acao)."</option>";
		}
		$acoesHtml .= "</select></div>";
		$acoesHtml .= "<script>$(document).ready(function(){ $('#acoes_esperadas').select2({ placeholder: 'Selecione as ações...', language: 'pt-BR', theme: 'bootstrap', width: '100%', allowClear: true }); });</script>";

		$c = [
			campo("Nome*",				"nome",			$input_values["nome"],		4, "", "maxlength='150'"),
			campo("CNPJ / CPF",			"cnpj",			$input_values["cnpj"],		3, "MASCARA_CPF/CNPJ"),
			campo("Telefone",			"contato",		$input_values["contato"],	3, "MASCARA_FONE"),
			campo("Endereço",			"endereco",		$input_values["endereco"],	6),
			campo("CEP",				"cep",			$input_values["cep"],		2, "MASCARA_CEP"),
			campo("Latitude*",			"latitude",		$input_values["latitude"],	2),
			campo("Longitude*",			"longitude",	$input_values["longitude"],	2),
			"<div class='col-sm-4 margin-bottom-5 campo-fit-content'><label>Raio (metros)</label><input type='range' id='raio_range' min='10' max='500' value='".($input_values["raio"] ?: 50)."' style='width:100%;'><input type='number' name='raio' id='raio' value='".($input_values["raio"] ?: 50)."' min='1' style='width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px; margin-top:4px;'></div>",
			"<div class='col-sm-3 margin-bottom-5 campo-fit-content'><label>Tipo de POI <a href='#' onclick='abrirModalGerenciarTipos();return false;' style='font-size:12px;font-weight:400;'>Gerenciar</a></label>{$iconeHtml}</div>",
			$acoesHtml
		];

		$botao = [
			botao($btn_txt, "cadastrarPoi", "id", ($_POST["id"] ?? ""), "", "", "btn btn-success"),
			criarBotaoVoltar("cadastro_poi.php")
		];

		echo abre_form("Dados do Ponto de Interesse (POI)");
		echo linha_form($c);
		echo fecha_form($botao);

		// Barra de busca de endereço via API gratuita Nominatim (OpenStreetMap)
		echo "
		<div class='col-md-12 margin-bottom-20'>
			<div class='portlet light'>
				<div class='portlet-title'>
					<div class='caption'><i class='fa fa-map-marker'></i> Capturar Coordenadas (clique no mapa)</div>
				</div>
				<div class='portlet-body'>
					<div class='row' style='margin-bottom:10px; position:relative;'>
						<div class='col-sm-10'>
							<input type='text' id='geoSearchInput' class='form-control' placeholder='Digite endereço, CEP, nome do local...' style='height:38px;' autocomplete='off'>
							<div id='geoResults' style='position:absolute; top:42px; left:15px; right:15px; z-index:9999; background:#fff; border:1px solid #ccc; max-height:300px; overflow-y:auto; display:none; box-shadow:0 4px 8px rgba(0,0,0,.15);'></div>
						</div>
						<div class='col-sm-2'>
							<button id='geoSearchBtn' class='btn btn-primary' style='width:100%; height:38px;'><i class='fa fa-search'></i> Buscar</button>
						</div>
					</div>
					<div id='mapaPoi' style='width:100%; height:550px; border:1px solid #ccc;'></div>
				</div>
			</div>
		</div>";

		// Management JS functions defined before rodape so they're always available
		$basePathPoi = $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"];
		echo "<script>
if(!window.basePath) window.basePath = '{$basePathPoi}';
var _emojiSelecionado = '📦';
function abrirModalNovoTipoPoi(){
	document.getElementById('novoTipoNome').value = '';_emojiSelecionado = '📦';
	var b=document.querySelectorAll('#gradeEmojis button');for(var i=0;i<b.length;i++)b[i].classList.remove('selecionado');
	var def=document.querySelector('#gradeEmojis button[data-emoji=\"📦\"]');if(def)def.classList.add('selecionado');
	document.getElementById('emojiSelecionado').textContent = '📦';
	document.getElementById('novoTipoStatus').innerHTML = '';
	document.getElementById('modalNovoTipoPoi').style.display = 'flex';
	setTimeout(function(){document.getElementById('novoTipoNome').focus();},100);
	if(!window._emojiGridClickInited){
		window._emojiGridClickInited=true;
		(function initEmoji(){
			var g=document.getElementById('gradeEmojis');
			if(g){g.addEventListener('click',function(e){
				var btn=e.target.closest('button');if(!btn)return;
				document.querySelectorAll('#gradeEmojis button').forEach(function(b){b.classList.remove('selecionado');});
				btn.classList.add('selecionado');_emojiSelecionado=btn.getAttribute('data-emoji')||'📌';
				document.getElementById('emojiSelecionado').textContent=_emojiSelecionado;
			});}else setTimeout(initEmoji,50);
		})();
	}
}
function fecharModalNovoTipoPoi(){document.getElementById('modalNovoTipoPoi').style.display='none';}
function salvarNovoTipoPoi(){
	var n=document.getElementById('novoTipoNome').value.trim(),e=_emojiSelecionado,s=document.getElementById('novoTipoStatus');
	if(!n){s.innerHTML='<span style=\"color:red;\">Informe o nome do tipo.</span>';document.getElementById('novoTipoNome').focus();return;}
	s.innerHTML='<span style=\"color:#666;\">Salvando...</span>';
	var fd=new FormData();fd.append('ajax_action','criar_tipo_poi');fd.append('codigo',n);fd.append('nome',n);fd.append('emoji',e);
	fetch(window.basePath+'/ajax_poi_tipo.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
		if(d.sucesso){
			s.innerHTML='<span style=\"color:green;\">Tipo criado com sucesso!</span>';
			var sel=document.getElementById('poi_icone'),opt=document.createElement('option');
			opt.value=d.tipo.poti_tx_codigo;opt.textContent=d.tipo.poti_tx_emoji+' '+d.tipo.poti_tx_nome;
			opt.setAttribute('data-emoji',d.tipo.poti_tx_emoji);sel.insertBefore(opt,sel.querySelector('option[value=\"__novo__\"]'));
			sel.value=d.tipo.poti_tx_codigo;setTimeout(fecharModalNovoTipoPoi,800);
		}else{s.innerHTML='<span style=\"color:red;\">'+(d.erro||'Erro ao salvar.')+'</span>';}
	}).catch(function(){s.innerHTML='<span style=\"color:red;\">Erro na requisição.</span>';});
}
function abrirModalGerenciarTipos(){document.getElementById('modalGerenciarTipos').style.display='flex';carregarListaTipos();}
function fecharModalGerenciarTipos(){document.getElementById('modalGerenciarTipos').style.display='none';}
function carregarListaTipos(){
	var l=document.getElementById('listaTiposPoi');
	var tbar=document.getElementById('toolbarGerenciar');
	l.innerHTML='<div class=\"empty-state\"><i class=\"fa fa-spinner fa-spin\"></i> Carregando...</div>';
	var fd=new FormData();fd.append('ajax_action','listar_todos_tipos_poi');
	fetch('ajax_poi_tipo.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
		if(!Array.isArray(d)||d.length===0){l.innerHTML='<div class=\"empty-state\">Nenhum tipo cadastrado.</div>';if(tbar)tbar.style.display='none';return;}
		if(tbar)tbar.style.display='flex';
		var h='';d.forEach(function(t){
			var a=t.poti_tx_status==='inativo';h+='<div class=\"tipo-row'+(a?' inativo':'')+'\">';
			h+='<input type=\"checkbox\" class=\"tipo-check\" value=\"'+escapeHtml(String(t.poti_nb_id))+'\" style=\"margin-right:8px;flex-shrink:0;\">';
			h+='<div class=\"tipo-info\"><span>'+escapeHtml(t.poti_tx_emoji||'')+'</span>';
			h+='<span class=\"tipo-nome\">'+escapeHtml(t.poti_tx_nome||'')+'</span>';
			h+='<span class=\"tipo-codigo\">('+escapeHtml(t.poti_tx_codigo||'')+')</span></div>';
			h+='<div class=\"tipo-actions\">';
			h+='<button class=\"btn-edit\" onclick=\"abrirModalEditarTipo(\\''+escapeJs(String(t.poti_nb_id))+'\\',\\''+escapeJs(t.poti_tx_nome)+'\\',\\''+escapeJs(t.poti_tx_emoji||'')+'\\',\\''+escapeJs(t.poti_tx_status||'ativo')+'\\')\"><i class=\"fa fa-pencil\"></i></button>';
			h+='<button class=\"btn-delete\" onclick=\"excluirTipo(\\''+escapeJs(String(t.poti_nb_id))+'\\',\\''+escapeJs(t.poti_tx_nome)+'\\')\"><i class=\"fa fa-trash\"></i></button>';
			h+='</div></div>';});l.innerHTML=h;
	}).catch(function(){l.innerHTML='<div class=\"empty-state\">Erro ao carregar.</div>';if(tbar)tbar.style.display='none';});
}
function toggleSelectAll(cb){
	var checks=document.querySelectorAll('#listaTiposPoi .tipo-check');
	for(var i=0;i<checks.length;i++)checks[i].checked=cb.checked;
}
function excluirSelecionados(){
	var checks=document.querySelectorAll('#listaTiposPoi .tipo-check:checked');
	if(checks.length===0){alert('Selecione pelo menos um tipo.');return;}
	var ids=[];for(var i=0;i<checks.length;i++)ids.push(checks[i].value);
	if(!confirm('Excluir '+ids.length+' tipo(s)?'))return;
	var fd=new FormData();fd.append('ajax_action','excluir_varios_tipos_poi');
	for(var i=0;i<ids.length;i++)fd.append('ids[]',ids[i]);
	fetch('ajax_poi_tipo.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
		if(d.sucesso){carregarListaTipos();}
		else{alert(d.erro||'Erro ao excluir.');}
	});
}
function abrirModalEditarTipo(id,nome,emoji,status){
	document.getElementById('editarTipoId').value=id;document.getElementById('editarTipoNome').value=nome;
	document.getElementById('editarTipoEmoji').value=emoji;document.getElementById('editarTipoStatus').value=status;
	document.getElementById('editarTipoStatusField').innerHTML='Status: '+(status==='ativo'?'<span style=\"color:green;\">Ativo</span>':'<span style=\"color:#888;\">Inativo</span>');
	document.getElementById('editarTipoStatusField').style.display='block';document.getElementById('editarTipoStatusEl').innerHTML='';
	document.getElementById('modalEditarTipo').style.display='flex';
}
function fecharModalEditarTipo(){document.getElementById('modalEditarTipo').style.display='none';}
function salvarEditarTipo(){
	var i=document.getElementById('editarTipoId').value.trim(),n=document.getElementById('editarTipoNome').value.trim();
	var e=document.getElementById('editarTipoEmoji').value.trim(),s=document.getElementById('editarTipoStatus').value;
	var st=document.getElementById('editarTipoStatusEl');
	if(!n||!i){st.innerHTML='<span style=\"color:red;\">Preencha todos os campos.</span>';return;}
	st.innerHTML='<span style=\"color:#666;\">Salvando...</span>';
	var fd=new FormData();fd.append('ajax_action','editar_tipo_poi');fd.append('id',i);fd.append('nome',n);fd.append('emoji',e);
	fetch('ajax_poi_tipo.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
		if(d.sucesso){st.innerHTML='<span style=\"color:green;\">Tipo atualizado!</span>';fecharModalEditarTipo();carregarListaTipos();}
		else{st.innerHTML='<span style=\"color:red;\">'+(d.erro||'Erro ao salvar.')+'</span>';}
	}).catch(function(){st.innerHTML='<span style=\"color:red;\">Erro na requisição.</span>';});
}
function excluirTipo(id,nome){
	if(!confirm('Tem certeza que deseja excluir o tipo '+nome+'?'))return;
	var fd=new FormData();fd.append('ajax_action','excluir_tipo_poi');fd.append('id',id);
	fetch('ajax_poi_tipo.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
		if(d.sucesso){carregarListaTipos();}else{alert(d.erro||'Erro ao excluir.');}
	});
}
function escapeHtml(text){var d=document.createElement('div');d.textContent=text;return d.innerHTML;}
function escapeJs(text){return String(text).replace(new RegExp('\\\"','g'),'\\\\\"').replace(new RegExp(\"'\",'g'),'\\\\\\'');}
</script>";

		rodape();

		// Modal para criar novo tipo de POI
		$basePathPoi = $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"];
		echo "
		<style>
		.emojigrid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;margin-top:6px;max-height:220px;overflow-y:auto;padding:4px;border:1px solid #eee;border-radius:8px;background:#fafafa;}
		.emojigrid button{font-size:24px;width:44px;height:44px;display:flex;align-items:center;justify-content:center;border:2px solid transparent;border-radius:8px;background:white;cursor:pointer;transition:all .15s;}
		.emojigrid button:hover{border-color:#004173;background:#e8f0fe;transform:scale(1.12);}
		.emojigrid button.selecionado{border-color:#004173;background:#d0e2ff;box-shadow:0 0 0 2px #004173;transform:scale(1.1);}
		</style>
		<div id='modalNovoTipoPoi' style='display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.5); z-index:99999; align-items:center; justify-content:center;'>
			<div style='background:white; border-radius:12px; padding:24px; width:480px; max-width:95%; box-shadow:0 8px 30px rgba(0,0,0,.3); position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);'>
				<h3 style='margin:0 0 4px 0; font-size:18px;'>➕ Criar novo tipo de POI</h3>
				<p style='color:#666; font-size:13px; margin-bottom:14px;'>Dê um nome e escolha um ícone.</p>
				<div style='margin-bottom:10px;'>
					<label style='display:block; font-weight:600; font-size:13px; margin-bottom:4px;'>Nome <span style='color:red;'>*</span></label>
					<input type='text' id='novoTipoNome' class='form-control' placeholder='Ex: Escola' style='width:100%;' autocomplete='off'>
				</div>
				<div style='margin-bottom:14px;'>
					<label style='display:block; font-weight:600; font-size:13px; margin-bottom:4px;'>Ícone <span style='color:red;'>*</span> <span id='emojiSelecionado' style='font-size:18px; margin-left:6px;'></span></label>
					<div class='emojigrid' id='gradeEmojis'>
						<button type='button' data-emoji='📦'>📦</button><button type='button' data-emoji='🏢'>🏢</button><button type='button' data-emoji='🏭'>🏭</button><button type='button' data-emoji='🏪'>🏪</button><button type='button' data-emoji='⛽'>⛽</button><button type='button' data-emoji='🅿️'>🅿️</button><button type='button' data-emoji='🏥'>🏥</button>
						<button type='button' data-emoji='🏦'>🏦</button><button type='button' data-emoji='🍽️'>🍽️</button><button type='button' data-emoji='🏨'>🏨</button><button type='button' data-emoji='🚚'>🚚</button><button type='button' data-emoji='📍'>📍</button><button type='button' data-emoji='🏁'>🏁</button><button type='button' data-emoji='🏫'>🏫</button>
						<button type='button' data-emoji='🛒'>🛒</button><button type='button' data-emoji='⚕️'>⚕️</button><button type='button' data-emoji='🔧'>🔧</button><button type='button' data-emoji='⚙️'>⚙️</button><button type='button' data-emoji='🛠️'>🛠️</button><button type='button' data-emoji='🚛'>🚛</button><button type='button' data-emoji='🚌'>🚌</button>
						<button type='button' data-emoji='🚕'>🚕</button><button type='button' data-emoji='✈️'>✈️</button><button type='button' data-emoji='⚓'>⚓</button><button type='button' data-emoji='🚢'>🚢</button><button type='button' data-emoji='🚂'>🚂</button><button type='button' data-emoji='🏗️'>🏗️</button><button type='button' data-emoji='🏠'>🏠</button>
						<button type='button' data-emoji='⛪'>⛪</button><button type='button' data-emoji='🎓'>🎓</button><button type='button' data-emoji='📚'>📚</button><button type='button' data-emoji='📋'>📋</button><button type='button' data-emoji='🛡️'>🛡️</button><button type='button' data-emoji='🔒'>🔒</button><button type='button' data-emoji='🔑'>🔑</button>
						<button type='button' data-emoji='🪪'>🪪</button><button type='button' data-emoji='📞'>📞</button><button type='button' data-emoji='🖥️'>🖥️</button><button type='button' data-emoji='🚧'>🚧</button><button type='button' data-emoji='🧰'>🧰</button><button type='button' data-emoji='🧲'>🧲</button><button type='button' data-emoji='🔋'>🔋</button>
						<button type='button' data-emoji='🍕'>🍕</button><button type='button' data-emoji='🍔'>🍔</button><button type='button' data-emoji='☕'>☕</button><button type='button' data-emoji='🥤'>🥤</button><button type='button' data-emoji='🧃'>🧃</button><button type='button' data-emoji='🏟️'>🏟️</button><button type='button' data-emoji='🎪'>🎪</button>
						<button type='button' data-emoji='🎯'>🎯</button><button type='button' data-emoji='🎳'>🎳</button><button type='button' data-emoji='🎮'>🎮</button><button type='button' data-emoji='🌲'>🌲</button><button type='button' data-emoji='🌳'>🌳</button><button type='button' data-emoji='🏔️'>🏔️</button><button type='button' data-emoji='🏝️'>🏝️</button>
						<button type='button' data-emoji='🏖️'>🏖️</button><button type='button' data-emoji='🚁'>🚁</button><button type='button' data-emoji='🛸'>🛸</button><button type='button' data-emoji='🚤'>🚤</button><button type='button' data-emoji='🚑'>🚑</button><button type='button' data-emoji='🚒'>🚒</button><button type='button' data-emoji='⚖️'>⚖️</button>
						<button type='button' data-emoji='🏛️'>🏛️</button><button type='button' data-emoji='📊'>📊</button><button type='button' data-emoji='📜'>📜</button><button type='button' data-emoji='🛋️'>🛋️</button><button type='button' data-emoji='🛏️'>🛏️</button><button type='button' data-emoji='🚿'>🚿</button><button type='button' data-emoji='🧹'>🧹</button>
						<button type='button' data-emoji='🩺'>🩺</button><button type='button' data-emoji='💊'>💊</button><button type='button' data-emoji='🔬'>🔬</button><button type='button' data-emoji='🧪'>🧪</button><button type='button' data-emoji='📡'>📡</button><button type='button' data-emoji='📷'>📷</button><button type='button' data-emoji='🎨'>🎨</button>
						<button type='button' data-emoji='🖼️'>🖼️</button><button type='button' data-emoji='🎵'>🎵</button><button type='button' data-emoji='🎭'>🎭</button><button type='button' data-emoji='📝'>📝</button><button type='button' data-emoji='⚽'>⚽</button><button type='button' data-emoji='🏀'>🏀</button><button type='button' data-emoji='🎾'>🎾</button>
						<button type='button' data-emoji='🏐'>🏐</button><button type='button' data-emoji='🚴'>🚴</button><button type='button' data-emoji='🏧'>🏧</button><button type='button' data-emoji='💳'>💳</button><button type='button' data-emoji='💰'>💰</button><button type='button' data-emoji='🧯'>🧯</button><button type='button' data-emoji='🗑️'>🗑️</button>
					</div>
				</div>
				<div style='display:flex; gap:10px;'>
					<button type='button' onclick='fecharModalNovoTipoPoi()' style='flex:1; padding:10px; border:1px solid #ccc; border-radius:6px; background:#f5f5f5; cursor:pointer; font-size:14px;'>Cancelar</button>
					<button type='button' onclick='salvarNovoTipoPoi()' style='flex:1; padding:10px; border:none; border-radius:6px; background:#004173; color:white; cursor:pointer; font-size:14px;'>Salvar Tipo</button>
				</div>
				<div id='novoTipoStatus' style='margin-top:12px; font-size:13px;'></div>
			</div>
		</div>

		<style>
		#modalGerenciarTipos .tipo-row{display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-bottom:1px solid #eee;}
		#modalGerenciarTipos .tipo-row:hover{background:#f9f9f9;}
		#modalGerenciarTipos .tipo-row.inativo{opacity:.5;}
		#modalGerenciarTipos .tipo-info{display:flex;align-items:center;gap:8px;flex:1;min-width:0;}
		#modalGerenciarTipos .tipo-nome{font-weight:600;font-size:13px;}
		#modalGerenciarTipos .tipo-codigo{font-size:11px;color:#888;}
		#modalGerenciarTipos .tipo-actions{display:flex;gap:4px;}
		#modalGerenciarTipos .tipo-actions button{padding:4px 8px;font-size:12px;border-radius:4px;border:none;cursor:pointer;}
		#modalGerenciarTipos .btn-edit{background:#004173;color:white;}
		#modalGerenciarTipos .btn-delete{background:#c0392b;color:white;}
		#modalGerenciarTipos .lista-tipos{max-height:350px;overflow-y:auto;border:1px solid #ddd;border-radius:6px;background:#fff;}
		#modalGerenciarTipos .empty-state{text-align:center;padding:20px;color:#888;font-size:13px;}
		</style>
		<div id='modalGerenciarTipos' style='display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;'>
			<div style='background:white;border-radius:12px;padding:20px;width:500px;max-width:95%;max-height:80vh;overflow-y:auto;box-shadow:0 8px 30px rgba(0,0,0,.3);'>
				<div style='display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;'>
					<h3 style='margin:0;font-size:16px;color:#004173;'><i class='fa fa-tags'></i> Gerenciar Tipos de POI</h3>
					<button type='button' onclick='fecharModalGerenciarTipos()' style='background:none;border:none;font-size:20px;cursor:pointer;color:#666;'>&times;</button>
				</div>
				<div id='toolbarGerenciar' style='display:none;align-items:center;gap:8px;margin-bottom:8px;font-size:13px;'>
					<label style='display:flex;align-items:center;gap:4px;cursor:pointer;'><input type='checkbox' onchange='toggleSelectAll(this)'> Selecionar todos</label>
					<button type='button' onclick='excluirSelecionados()' style='padding:4px 10px;border:none;border-radius:4px;background:#c0392b;color:white;cursor:pointer;font-size:12px;'><i class='fa fa-trash'></i> Excluir selecionados</button>
				</div>
				<div class='lista-tipos' id='listaTiposPoi'><div class='empty-state'>Carregando...</div></div>
			</div>
		</div>
		<div id='modalEditarTipo' style='display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:100000;align-items:center;justify-content:center;'>
			<div style='background:white;border-radius:12px;padding:20px;width:420px;max-width:95%;box-shadow:0 8px 30px rgba(0,0,0,.3);'>
				<h3 style='margin:0 0 12px 0;font-size:16px;color:#004173;'><i class='fa fa-pencil'></i> Editar Tipo de POI</h3>
				<input type='hidden' id='editarTipoId'>
				<div style='margin-bottom:10px;'>
					<label style='display:block;font-weight:600;font-size:13px;margin-bottom:4px;'>Nome <span style='color:red;'>*</span></label>
					<input type='text' id='editarTipoNome' class='form-control' placeholder='Nome do tipo'>
				</div>
				<div style='margin-bottom:10px;'>
					<label style='display:block;font-weight:600;font-size:13px;margin-bottom:4px;'>Emoji</label>
					<input type='text' id='editarTipoEmoji' class='form-control' placeholder='📦' maxlength='10'>
				</div>
				<div id='editarTipoStatusField' style='margin-bottom:10px;display:none;font-size:13px;'></div>
				<input type='hidden' id='editarTipoStatus' value='ativo'>
				<div style='display:flex;gap:8px;'>
					<button type='button' onclick='fecharModalEditarTipo()' style='flex:1;padding:10px;border:1px solid #ccc;border-radius:6px;background:#f5f5f5;cursor:pointer;font-size:14px;'>Cancelar</button>
					<button type='button' onclick='salvarEditarTipo()' style='flex:1;padding:10px;border:none;border-radius:6px;background:#004173;color:white;cursor:pointer;font-size:14px;'>Salvar</button>
				</div>
				<div id='editarTipoStatusEl' style='margin-top:10px;font-size:13px;'></div>
			</div>
		</div>
		<script>
		if(!window.emojiGridInited){
			window.emojiGridInited = true;
			document.addEventListener('DOMContentLoaded', function(){
				var g = document.getElementById('gradeEmojis');
				if(g) g.addEventListener('click', function(e){
					var btn = e.target.closest('button');
					if(!btn) return;
					document.querySelectorAll('#gradeEmojis button').forEach(function(b){ b.classList.remove('selecionado'); });
					btn.classList.add('selecionado');
					_emojiSelecionado = btn.getAttribute('data-emoji') || '📌';
					document.getElementById('emojiSelecionado').textContent = _emojiSelecionado;
				});
			});
		}
		document.addEventListener('DOMContentLoaded', function(){
			var sel = document.getElementById('poi_icone');
			if(sel){
				sel.addEventListener('change', function(){
					if(this.value === '__novo__'){
						this.value = '';
						abrirModalNovoTipoPoi();
					}
				});
			}
		});
		</script>
		<link rel='stylesheet' href='https://unpkg.com/leaflet@1.9.4/dist/leaflet.css' />
		<script src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'></script>
		<script>
		(function(){
			var map = L.map('mapaPoi').setView([-7.2361, -35.8767], 13); // Campina Grande/PB como padrao

			// Camadas de mapa (mesmo padrao da logistica.php)
			var defaultLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				attribution: '&copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a> contributors',
				maxZoom: 19
			});
			var satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
				attribution: '&copy; Esri', maxZoom: 19
			});
			var hybridLayer = L.tileLayer('https://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
				maxZoom: 20, subdomains: ['mt0','mt1','mt2','mt3'], attribution: '&copy; Google'
			}).addTo(map);
			var terrainLayer = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
				maxZoom: 17, attribution: '&copy; OpenTopoMap'
			});
			L.control.layers({
				'Híbrido': hybridLayer,
				'OpenStreetMap': defaultLayer,
				'Satélite': satelliteLayer,
				'Terreno': terrainLayer
			}).addTo(map);

			var marker = null;
			var circle = null;

			function aplicarPonto(lat, lng){
				var raio = parseInt(document.contex_form.raio.value, 10);
				if(!isFinite(raio) || raio <= 0){ raio = 50; }

				document.contex_form.latitude.value = lat.toFixed(7);
				document.contex_form.longitude.value = lng.toFixed(7);

				if(marker){ map.removeLayer(marker); }
				if(circle){ map.removeLayer(circle); }
				marker = L.marker([lat, lng]).addTo(map);
				circle = L.circle([lat, lng], { radius: raio, color: '#337ab7', fillColor: '#337ab7', fillOpacity: 0.15 }).addTo(map);
			}

			function atualizarRaioMapa(){
				var raio = parseInt(document.contex_form.raio.value, 10);
				if(!isFinite(raio) || raio <= 0){ raio = 50; }
				document.getElementById('raio_range').value = raio;
				document.getElementById('raio').value = raio;
				if(circle){
					circle.setRadius(raio);
				}
			}

			// Sincroniza slider <-> number input e atualiza o círculo no mapa
			var raioRange = document.getElementById('raio_range');
			var raioInput = document.getElementById('raio');
			if(raioRange){
				raioRange.addEventListener('input', function(){
					raioInput.value = this.value;
					if(circle){ circle.setRadius(parseInt(this.value, 10) || 50); }
				});
			}
			if(raioInput){
				raioInput.addEventListener('input', function(){
					raioRange.value = this.value;
					if(circle){ circle.setRadius(parseInt(this.value, 10) || 50); }
				});
			}

			// Pre-preenche ponto quando editando
			var latIni = parseFloat(document.contex_form.latitude.value.replace(',', '.'));
			var lngIni = parseFloat(document.contex_form.longitude.value.replace(',', '.'));
			if(isFinite(latIni) && isFinite(lngIni)){
				aplicarPonto(latIni, lngIni);
				map.setView([latIni, lngIni], 16);
			}

			function reverseGeocode(lat, lng) {
				var url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + lat + '&lon=' + lng + '&accept-language=pt&addressdetails=1';
				fetch(url, { headers: { 'User-Agent': 'TechPS-POI/1.0' } })
					.then(function(r){ return r.json(); })
					.then(function(data){
						if(data && data.address){
							preencherEndereco(data.address);
						}
					})
					.catch(function(){});
			}

			map.on('click', function(e){
				aplicarPonto(e.latlng.lat, e.latlng.lng);
				reverseGeocode(e.latlng.lat, e.latlng.lng);
			});

			// Autocomplete e busca geográfica via Nominatim (OpenStreetMap)
			var geoDebounce = null;
			var geoLastQuery = '';
			var geoSelected = false;

			function fecharResultados() {
				document.getElementById('geoResults').style.display = 'none';
			}

			function preencherEndereco(address) {
				var parts = [];
				if(address.road || address.pedestrian || address.footway){
					parts.push(address.road || address.pedestrian || address.footway);
				}
				if(address.suburb || address.neighbourhood || address.hamlet){
					parts.push(address.suburb || address.neighbourhood || address.hamlet);
				}
				if(address.city || address.town || address.village || address.municipality){
					parts.push(address.city || address.town || address.village || address.municipality);
				}
				if(address.state){ parts.push(address.state); }
				var enderecoVal = parts.join(', ');
				if(enderecoVal){
					document.getElementById('endereco').value = enderecoVal;
				}

				if(address.postcode){
					document.getElementById('cep').value = address.postcode;
				}
			}

			function buscarLocal(q) {
				if(!q){
					q = document.getElementById('geoSearchInput').value.trim();
				}
				if(!q){ alert('Digite um endereço, CEP ou local para buscar.'); return; }
				fecharResultados();

				var btn = document.getElementById('geoSearchBtn');
				btn.disabled = true;
				btn.innerHTML = '<i class=\"fa fa-spinner fa-spin\"></i> Buscando...';

				var url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&q=' + encodeURIComponent(q) + '&limit=1&accept-language=pt&countrycodes=br&addressdetails=1';
				fetch(url, { headers: { 'User-Agent': 'TechPS-POI/1.0' } })
					.then(function(r){ return r.json(); })
					.then(function(data){
						if(data && data.length > 0){
							var lat = parseFloat(data[0].lat);
							var lng = parseFloat(data[0].lon);
							aplicarPonto(lat, lng);
							map.setView([lat, lng], 16);
							geoSelected = true;
							if(data[0].address){
								preencherEndereco(data[0].address);
							}
						}else{
							alert('Nenhum local encontrado para \"' + q + '\". Tente um termo mais específico.');
						}
					})
					.catch(function(err){
						alert('Erro ao buscar local. Verifique sua conexão e tente novamente.');
						console.error(err);
					})
					.finally(function(){
						btn.disabled = false;
						btn.innerHTML = '<i class=\"fa fa-search\"></i> Buscar';
					});
			}

			function autocompleteLocal() {
				var q = document.getElementById('geoSearchInput').value.trim();
				var resultsDiv = document.getElementById('geoResults');

				if(q.length < 3 || q === geoLastQuery){ resultsDiv.style.display = 'none'; return; }
				geoLastQuery = q;

				var url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&q=' + encodeURIComponent(q) + '&limit=6&accept-language=pt&countrycodes=br&addressdetails=1';
				fetch(url, { headers: { 'User-Agent': 'TechPS-POI/1.0' } })
					.then(function(r){ return r.json(); })
					.then(function(data){
						resultsDiv.innerHTML = '';
						if(data && data.length > 0){
							data.forEach(function(item){
								var label = item.display_name || '';
								var lat = parseFloat(item.lat);
								var lng = parseFloat(item.lon);

								var div = document.createElement('div');
								div.className = 'geo-result-item';
								div.style.cssText = 'padding:8px 12px; cursor:pointer; border-bottom:1px solid #eee; font-size:13px;';
								div.onmouseover = function(){ this.style.background = '#f0f7ff'; };
								div.onmouseout = function(){ this.style.background = '#fff'; };
								div.textContent = label;
								div.addEventListener('click', function(){
									document.getElementById('geoSearchInput').value = label;
									aplicarPonto(lat, lng);
									map.setView([lat, lng], 16);
									geoSelected = true;
									if(item.address){
										preencherEndereco(item.address);
									}
									fecharResultados();
								});
								resultsDiv.appendChild(div);
							});
							resultsDiv.style.display = 'block';
						}else{
							resultsDiv.style.display = 'none';
						}
					})
					.catch(function(){
						resultsDiv.style.display = 'none';
					});
			}

			document.getElementById('geoSearchBtn').addEventListener('click', function(){ buscarLocal(); });
			document.getElementById('geoSearchInput').addEventListener('keydown', function(e){
				if(e.key === 'Enter'){
					if(document.getElementById('geoResults').style.display === 'block'){
						fecharResultados();
					}
					buscarLocal();
				}
				if(e.key === 'Escape'){ fecharResultados(); }
			});
			document.getElementById('geoSearchInput').addEventListener('input', function(){
				geoSelected = false;
				geoLastQuery = '';
				if(geoDebounce){ clearTimeout(geoDebounce); }
				geoDebounce = setTimeout(autocompleteLocal, 500);
			});
			document.addEventListener('click', function(e){
				if(!e.target.closest('#geoSearchInput') && !e.target.closest('#geoResults')){
					fecharResultados();
				}
			});
		})();
		</script>";
	}

		/**
	 * Importa POIs a partir de um arquivo CSV.
	 * Colunas esperadas: nome, cnpj, contato, endereco, cep, latitude, longitude, raio, icone
	 */
	function importarPoiCsv(){
		if(!function_exists('verificaPermissao')){
			include "check_permission.php";
		}
		verificaPermissao('/cadastro_poi.php');
		ensurePoiSchema();

		if(empty($_FILES['arquivo_csv']['tmp_name'])){
			set_status("ERRO: Selecione um arquivo CSV.");
			index();
			exit;
		}

		$handle = fopen($_FILES['arquivo_csv']['tmp_name'], 'r');
		if(!$handle){
			set_status("ERRO: Não foi possível abrir o arquivo CSV.");
			index();
			exit;
		}

		// Detecta delimitador
		$primeiraLinha = fgets($handle);
		rewind($handle);
		$delimitador = strpos($primeiraLinha, ';') !== false ? ';' : ',';

		$cabecalho = fgetcsv($handle, 0, $delimitador);
		if(!$cabecalho){
			set_status("ERRO: CSV vazio ou sem cabeçalho.");
			index();
			exit;
		}

		// Normaliza cabeçalho
		$cabecalho = array_map(function($v){ return strtolower(trim($v)); }, $cabecalho);

		$colunasObrigatorias = ['nome', 'latitude', 'longitude'];
		$faltando = [];
		foreach($colunasObrigatorias as $col){
			if(!in_array($col, $cabecalho)){
				$faltando[] = $col;
			}
		}
		if(!empty($faltando)){
			set_status("ERRO: Colunas obrigatórias ausentes: " . implode(', ', $faltando) . ".");
			index();
			exit;
		}

		$indice = [];
		foreach(['nome','cnpj','contato','endereco','cep','latitude','longitude','raio','icone'] as $col){
			$indice[$col] = array_search($col, $cabecalho);
		}

		$inseridos = 0;
		$erros = [];
		$linhaNum = 1;

		while(($linha = fgetcsv($handle, 0, $delimitador)) !== false){
			$linhaNum++;
			// Ignora linhas vazias, comentários ou instrução sep= do Excel
			if(count($linha) === 0 || (isset($linha[0]) && (strpos(trim($linha[0]), '#') === 0 || strpos(trim($linha[0]), 'sep=') === 0))){
				continue;
			}
			if(count($linha) < count($cabecalho)){
				continue;
			}

			$nome      = trim($linha[$indice['nome']] ?? '');
			$cnpj      = preg_replace('/[^0-9]/', '', (string)($linha[$indice['cnpj']] ?? ''));
			$contato   = trim($linha[$indice['contato']] ?? '');
			$endereco  = trim($linha[$indice['endereco']] ?? '');
			$cep       = preg_replace('/[^0-9]/', '', (string)($linha[$indice['cep']] ?? ''));
			$latitude  = str_replace(',', '.', trim($linha[$indice['latitude']] ?? ''));
			$longitude = str_replace(',', '.', trim($linha[$indice['longitude']] ?? ''));
			$raio      = intval($linha[$indice['raio']] ?? 50);
			$icone     = trim($linha[$indice['icone']] ?? '');

			if($nome === '' || !is_numeric($latitude) || !is_numeric($longitude)){
				$erros[] = "Linha {$linhaNum}: nome, latitude ou longitude inválidos.";
				continue;
			}

			// Verifica se já existe POI com mesmo nome + latitude + longitude
			$existente = mysqli_fetch_assoc(query(
				"SELECT poi_nb_id FROM poi WHERE poi_tx_nome = ? AND poi_tx_latitude = ? AND poi_tx_longitude = ? LIMIT 1",
				"sss",
				[$nome, $latitude, $longitude]
			));

			if(!empty($existente)){
				query(
					"UPDATE poi SET
						poi_tx_cnpj = ?, poi_tx_contato = ?, poi_tx_endereco = ?, poi_tx_cep = ?,
						poi_nb_raio = ?, poi_tx_icone = ?, poi_tx_status = 'ativo'
					 WHERE poi_nb_id = ?",
					"ssssisi",
					[$cnpj, $contato, $endereco, $cep, $raio, $icone, $existente['poi_nb_id']]
				);
				$inseridos++;
			}else{
				query(
					"INSERT INTO poi
						(poi_tx_nome, poi_tx_cnpj, poi_tx_contato, poi_tx_endereco, poi_tx_cep,
						 poi_tx_latitude, poi_tx_longitude, poi_nb_raio, poi_tx_icone, poi_tx_status, poi_tx_imagem)
					 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'ativo', '')",
					"sssssssis",
					[$nome, $cnpj, $contato, $endereco, $cep, $latitude, $longitude, $raio, $icone]
				);
				$inseridos++;
			}
		}
		fclose($handle);

		$msg = "Importação concluída: {$inseridos} POI(s) processado(s).";
		if(!empty($erros)){
			$msg .= " Erros: " . implode(' ', array_slice($erros, 0, 5));
			if(count($erros) > 5){
				$msg .= " e mais " . (count($erros) - 5) . " erro(s).";
			}
		}
		set_status($msg);
		index();
	}

/**
	 * Tela principal: listagem em grid + mapa com POIs e os pontos do motorista.
	 * Parametros via GET (mesmos da logistica.php):
	 *   motorista, matricula, data, cnpj
	 */
	function index(){
		// Garante que as funcoes de permissao estejam disponiveis.
		if(!function_exists('verificaPermissao')){
			include "check_permission.php";
		}
		verificaPermissao('/cadastro_poi.php');

		cabecalho("Cadastro de POI");
		ensurePoiSchema();

		// ---- Filtros do cadastro ----
		$tiposFiltro = ["" => "Todos"];
		$rsTiposFiltro = query("SELECT poti_tx_codigo, poti_tx_nome FROM poi_tipo WHERE poti_tx_status = 'ativo' ORDER BY poti_tx_nome ASC");
		while($rsTiposFiltro && ($r = mysqli_fetch_assoc($rsTiposFiltro))){
			$tiposFiltro[$r['poti_tx_codigo']] = $r['poti_tx_nome'];
		}

		$fields = [
			campo("Código",		"busca_codigo",		($_POST["busca_codigo"] ?? ""),	1, "MASCARA_NUMERO", "maxlength='6' min='0'"),
			campo("Nome",		"busca_nome_like",	($_POST["busca_nome_like"] ?? ""),	3, "", "maxlength='150'"),
			combo("Tipo",		"busca_tipo",		($_POST["busca_tipo"] ?? ""),		2, $tiposFiltro),
			campo("CPF/CNPJ",	"busca_cpf_cnpj",	($_POST["busca_cpf_cnpj"] ?? ""),	2, "MASCARA_CPF/CNPJ"),
			combo("Status",		"busca_status",		($_POST["busca_status"] ?? "ativo"), 2, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"])
		];

		$buttons = [
			botao("Buscar", "index"),
			botao("Limpar Filtro", "limparFiltros"),
			botao("Inserir", "visualizarCadastro", "", "", "", "", "btn btn-success"),
			
			botao("Mapa de POIs", "abrirMapaPoi", "", "", "", "", "btn btn-info"),
			'<button type="button" class="btn btn-warning" onclick="abrirModalImportarPoi()">Importar CSV</button>',
			'<a class="btn btn-default" href="arquivos/instrucoesPoi/modelo_importacao_poi.csv" download>Modelo CSV</a>',

		];

		echo abre_form();
		echo linha_form($fields);
		echo fecha_form($buttons);

		echo "<style>.form-actions{display:flex;flex-wrap:wrap;gap:6px;}.form-actions .fecha-form-btn{display:inline-flex;}</style>";

		// ---- Grid ----
			// O gridDinamico() adiciona "WHERE 1" automaticamente ao final da queryBase.
			// Como esta tabela nao tem JOIN, nao podemos usar $extra com AND solto.
			// O filtro de status e os demais ficam por conta do grid JS via searchFields.
			// O input busca_status tem default "ativo", entao o JS ja envia o filtro correto.

			$emojiMap = [];
			$__rsEmoji = query("SELECT poti_tx_codigo, poti_tx_emoji FROM poi_tipo WHERE poti_tx_status = 'ativo'");
			while($__rsEmoji && ($__r = mysqli_fetch_assoc($__rsEmoji))){
				$emojiMap[$__r['poti_tx_codigo']] = $__r['poti_tx_emoji'];
			}
			$caseEmoji = "CASE poi_tx_icone";
			foreach ($emojiMap as $k => $v) { $caseEmoji .= " WHEN '$k' THEN '$v'"; }
			$caseEmoji .= " ELSE '📌' END AS IMAGEM";

			$acoesSubquery = "COALESCE(CONCAT((SELECT COUNT(*) FROM poi_acao_esperada WHERE paes_nb_poi = poi_nb_id), '||', (SELECT GROUP_CONCAT(paes_tx_codigo ORDER BY paes_tx_codigo SEPARATOR ', ') FROM poi_acao_esperada WHERE paes_nb_poi = poi_nb_id)), '0') AS ACOES_INFO";

			$gridFields = [
				"CÓDIGO"	=> "poi_nb_id",
				"IMAGEM"	=> $caseEmoji,
				"NOME"		=> "poi_tx_nome",
				"TIPO"		=> "poi_tx_icone",
				"CNPJ"		=> "poi_tx_cnpj",
				"CONTATO"	=> "poi_tx_contato",
				"ENDEREÇO"	=> "poi_tx_endereco",
				"CEP"		=> "poi_tx_cep",
				"LATITUDE"	=> "poi_tx_latitude",
				"LONGITUDE" => "poi_tx_longitude",
				"RAIO (m)"	=> "poi_nb_raio",
				"STATUS"	=> "poi_tx_status",
				"AÇÕES ESPERADAS"	=> $acoesSubquery
			];

			$camposBusca = [
				"busca_codigo" 	=> "poi_nb_id",
				"busca_nome_like" => "poi_tx_nome",
				"busca_tipo" 	=> "poi_tx_icone",
				"busca_cpf_cnpj" => "poi_tx_cnpj",
				"busca_status" 	=> "poi_tx_status"
			];

			$queryBase = "SELECT ".implode(", ", array_values($gridFields))." FROM poi";

		$actions = criarIconesGrid(
			["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
			["cadastro_poi.php", "cadastro_poi.php"],
			["editarPoi", "excluirPoi"]
		);
		$indiceStatus = array_search("STATUS", array_keys($gridFields));
		$actions["functions"][1] .= "esconderInativar('glyphicon glyphicon-remove search-remove', {$indiceStatus});";

		$gridFields["actions"] = $actions["tags"];
		$jsFunctions = "const funcoesInternas = function(){ ".implode(" ", $actions["functions"])."
			try{
				$('#result tbody tr').each(function(){
					$(this).find('td').each(function(){
						var txt = $(this).text();
						if(txt.indexOf('||') > -1){
							var parts = txt.split('||');
							var qtd = parseInt(parts[0], 10) || 0;
							var lista = parts[1] || '';
							if(qtd > 0){
								var encoded = $('<span>').text(lista).html();
								$(this).html('<span style=\"cursor:pointer;color:#004173;font-weight:600;\" data-acoes=\"' + encoded + '\">' + qtd + ' | ver mais</span>');
							}else{
								$(this).text('0');
							}
						}
					});
				});
				$('#result tbody').off('click', 'span[data-acoes]').on('click', 'span[data-acoes]', function(){
					var acoes = $(this).data('acoes');
					if(typeof Swal !== 'undefined'){
						Swal.fire({
							title: 'Ações Esperadas',
							html: '<div style=\"text-align:left; font-size:16px;\">' + acoes + '</div>',
							icon: 'info',
							confirmButtonText: 'Fechar',
							confirmButtonColor: '#004173'
						});
					}else{
						alert('Ações Esperadas:\\n' + acoes);
					}
				});
			}catch(e){}
		}";
		echo gridDinamico("tabelaPoi", $gridFields, $camposBusca, $queryBase, $jsFunctions);

		$instrucoesPath = __DIR__ . "/arquivos/instrucoesPoi/modelo_importacao_poi.md";
		$instrucoesHtml = "";
		if(file_exists($instrucoesPath)){
			$md = file_get_contents($instrucoesPath);
			$instrucoesHtml = markdownSimplesParaHtml($md);
		}

		echo "
		<style>
		.modalImportacaoPoi td, .modalImportacaoPoi th { padding:6px 10px; border:1px solid #ddd; }
		.modalImportacaoPoi th { background:#004173; color:#fff; font-weight:600; }
		.modalImportacaoPoi table { width:100%; border-collapse:collapse; margin-bottom:16px; }
		.modalImportacaoPoi p { margin:0 0 8px 0; line-height:1.6; }
		.modalImportacaoPoi h1 { font-size:20px; color:#004173; margin:0 0 12px 0; }
		.modalImportacaoPoi h2 { font-size:16px; color:#004173; margin:14px 0 8px 0; padding-bottom:4px; border-bottom:2px solid #004173; }
		.modalImportacaoPoi h3 { font-size:14px; color:#333; margin:12px 0 6px 0; }
		.modalImportacaoPoi ul { margin:4px 0 10px 0; padding-left:20px; }
		.modalImportacaoPoi li { margin-bottom:3px; line-height:1.5; }
		.modalImportacaoPoi code { background:#f4f4f4; padding:2px 5px; border-radius:3px; font-size:12px; color:#c7254e; }
		.modalImportacaoPoi pre { background:#2d2d2d; color:#f8f8f2; padding:12px; border-radius:6px; overflow-x:auto; font-size:12px; }
		.modalImportacaoPoi pre code { background:transparent; color:#f8f8f2; padding:0; }
		.modalImportacaoPoi blockquote { margin:8px 0; padding:10px 14px; background:#fff3cd; border-left:4px solid #ffc107; border-radius:4px; font-size:13px; }
		</style>
		<script>
		window.abrirModalImportarPoi = function(){
			var instrucoes = " . json_encode($instrucoesHtml) . ";
			Swal.fire({
				width: '900px',
				title: '<span style=\"font-size:22px;\"><i class=\"fa fa-upload\" style=\"margin-right:8px;\"></i>Importar POIs via CSV</span>',
				html:
					'<div class=\"modalImportacaoPoi\" style=\"text-align:left; max-height:500px; overflow-y:auto; margin-bottom:15px; padding:0 4px;\">' +
					instrucoes +
					'</div>' +
					'<div style=\"border-top:1px solid #eee; padding-top:15px;\">' +
					'<form id=\"formImportarPoi\" method=\"post\" enctype=\"multipart/form-data\" action=\"cadastro_poi.php\">' +
					'<input type=\"hidden\" name=\"acao\" value=\"importarPoiCsv\">' +
					'<div style=\"display:flex; align-items:center; gap:10px;\">' +
					'<label style=\"font-weight:600; font-size:14px; white-space:nowrap; min-width:55px;\">Arquivo:</label>' +
					'<input type=\"file\" name=\"arquivo_csv\" accept=\".csv\" style=\"flex:1; padding:8px; border:1px dashed #ccc; border-radius:6px; background:#fafafa; font-size:13px;\">' +
					'</div>' +
					'</form>' +
					'</div>',
				icon: null,
				confirmButtonText: '<i class=\"fa fa-upload\"></i> Importar',
				confirmButtonColor: '#004173',
				showCancelButton: true,
				cancelButtonText: 'Fechar',
				cancelButtonColor: '#6c757d',
				footer: '<a class=\"btn btn-default btn-sm\" href=\"arquivos/instrucoesPoi/modelo_importacao_poi.csv\" download style=\"color:#004173;\"><i class=\"fa fa-download\"></i> Baixar modelo CSV</a>',
				preConfirm: function(){
					var form = document.getElementById('formImportarPoi');
					if(form && form.arquivo_csv && form.arquivo_csv.files.length === 0){
						Swal.showValidationMessage('Selecione um arquivo CSV');
						return false;
					}
					return true;
				}
			}).then(function(result){
				if(result.isConfirmed){
					document.getElementById('formImportarPoi').submit();
				}
			});
		};
		</script>";

		rodape();
	}

	/**
	 * Mapa com os POIs cadastrados e os pontos batidos pelo motorista no dia.
	 * Permite flagar (marcar) POIs e mostrar os pontos de interesse.
	 */
	/**
	 * Converte Markdown simples para HTML (sem dependência externa).
	 */
	function markdownSimplesParaHtml($texto){
		$linhas = explode("\n", $texto);
		$html = "";
		$emTabela = false;
		$emLista = false;
		$emCodigo = false;

		foreach($linhas as $i => $linha){
			$trim = trim($linha);

			// Bloco de código ```
			if(strpos($trim, '```') === 0){
				if($emCodigo){
					$html .= "</code></pre>\n";
					$emCodigo = false;
				}else{
					$html .= "<pre><code>";
					$emCodigo = true;
				}
				continue;
			}
			if($emCodigo){
				$html .= htmlspecialchars($linha) . "\n";
				continue;
			}

			// Fechar tabela se linha não começa com |
			if($emTabela && $linha !== '' && $trim !== '' && $trim[0] !== '|' && $trim[0] !== ':' && !preg_match('/^[\s\|:\-]+$/', $trim)){
				$html .= "</tbody></table>\n";
				$emTabela = false;
			}

			// Cabeçalho
			if(preg_match('/^(#{1,3})\s+(.+)$/', $trim, $m)){
				$nivel = strlen($m[1]);
				$html .= "<h{$nivel}>" . htmlspecialchars($m[2]) . "</h{$nivel}>\n";
				continue;
			}

			// Citação
			if(preg_match('/^>\s*(.+)$/', $trim, $m)){
				$html .= "<blockquote>" . htmlspecialchars($m[1]) . "</blockquote>\n";
				continue;
			}

			// Tabela
			if(preg_match('/^\|(.+)\|$/', $trim, $m)){
				$proxLinha = $linhas[$i + 1] ?? '';
				$proxTrim = trim($proxLinha);
				if(!$emTabela){
					$html .= "<table class='table table-bordered table-condensed' style='font-size:13px; margin-bottom:10px;'>\n";
					$celulas = explode('|', $m[1]);
					if(preg_match('/^[\s\|:\-]+$/', $proxTrim)){
						$html .= "<thead><tr>";
						foreach($celulas as $cel){
							$html .= "<th>" . htmlspecialchars(trim($cel)) . "</th>";
						}
						$html .= "</tr></thead><tbody>";
					}else{
						$html .= "<tbody><tr>";
						foreach($celulas as $cel){
							$html .= "<td>" . htmlspecialchars(trim($cel)) . "</td>";
						}
						$html .= "</tr>";
					}
					$emTabela = true;
				}else{
					$html .= "<tr>";
					$celulas = explode('|', $m[1]);
					foreach($celulas as $cel){
						$html .= "<td>" . htmlspecialchars(trim($cel)) . "</td>";
					}
					$html .= "</tr>\n";
				}
				continue;
			}

			// Lista
			if(preg_match('/^[\-\*]\s+(.+)$/', $trim, $m)){
				if(!$emLista){ $html .= "<ul>\n"; $emLista = true; }
				$html .= "<li>" . htmlspecialchars($m[1]) . "</li>\n";
				continue;
			}elseif($emLista){
				$html .= "</ul>\n";
				$emLista = false;
			}

			// Negrito, itálico, código inline
			$trim = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $trim);
			$trim = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $trim);
			$trim = preg_replace('/`(.+?)`/', '<code>$1</code>', $trim);

			if($trim === ''){
				$html .= "<p>&nbsp;</p>\n";
				continue;
			}
			$html .= "<p>" . $trim . "</p>\n";
		}

		if($emCodigo){ $html .= "</code></pre>\n"; }
		if($emLista){ $html .= "</ul>\n"; }
		if($emTabela){ $html .= "</tbody></table>\n"; }

		return $html;
	}

	function abrirMapaPoi(){
		ensurePoiSchema();

		// Captura os parametros da URL (mesmos usados na logistica.php).
		$motorista = $_GET["motorista"] ?? ($_POST["motorista"] ?? "");
		$matricula = $_GET["matricula"] ?? ($_POST["matricula"] ?? "");
		$data      = $_GET["data"]      ?? ($_POST["data"] ?? "");
		$cnpj      = $_GET["cnpj"]      ?? ($_POST["cnpj"] ?? "");

		cabecalho("Mapa de POIs - Logística");

		// ---- POIs ativos ----
		$pois = mysqli_fetch_all(query(
			"SELECT poi_nb_id, poi_tx_nome, poi_tx_cnpj, poi_tx_contato,
					poi_tx_latitude, poi_tx_longitude, poi_nb_raio, poi_tx_icone,
					poi_tx_endereco, poi_tx_cep, poi_tx_imagem,
					(SELECT GROUP_CONCAT(paes_tx_codigo ORDER BY paes_tx_codigo SEPARATOR ', ')
					 FROM poi_acao_esperada
					 WHERE paes_nb_poi = poi.poi_nb_id) AS poi_tx_acoes_esperadas
			 FROM poi
			 WHERE poi_tx_status = 'ativo'
			 ORDER BY poi_tx_nome ASC"
		), MYSQLI_ASSOC);

		// ---- Tipos de POI para o icone dinâmico ----
		$rsTiposMapa = query("SELECT poti_tx_codigo, poti_tx_emoji FROM poi_tipo WHERE poti_tx_status = 'ativo'");
		$tiposMapa = [];
		while($rsTiposMapa && ($r = mysqli_fetch_assoc($rsTiposMapa))){ $tiposMapa[] = $r; }

		// ---- Pontos do motorista no dia (se houver matricula/data) ----
		$pontos = [];
		if(!empty($matricula) && !empty($data)){
			$dataInicio = $data." 00:00:00";
			$dataFim    = $data." 23:59:59";
			$rsPontos = query(
				"SELECT pont_tx_data, macr_tx_nome, pont_tx_latitude, pont_tx_longitude
				 FROM ponto
				 JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_tx_codigoInterno
				 WHERE pont_tx_status = 'ativo'
				   AND macroponto.macr_tx_fonte = 'positron'
				   AND pont_tx_matricula = ?
				   AND pont_tx_data BETWEEN ? AND ?
				   AND pont_tx_latitude IS NOT NULL
				   AND pont_tx_longitude IS NOT NULL
				   AND pont_tx_latitude <> ''
				   AND pont_tx_longitude <> ''
				 ORDER BY pont_tx_data ASC",
				"sss",
				[$matricula, $dataInicio, $dataFim]
			);
			while($rsPontos && ($r = mysqli_fetch_assoc($rsPontos))){
				$pontos[] = $r;
			}
		}

		$poisJson   = json_encode($pois   ?: [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$pontosJson = json_encode($pontos ?: [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$tiposJson  = json_encode($tiposMapa ?: [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		echo "
		<div class='col-md-12'>
			<div class='portlet light'>
				<div class='portlet-title'>
					<div class='caption'>
						<i class='fa fa-map-marked-alt'></i>
						Mapa de Pontos de Interesse
						".(!empty($motorista) ? "<small class='text-muted'> &nbsp;|&nbsp; Motorista: ".htmlspecialchars($motorista)."</small>" : "")."
						".(!empty($data)      ? "<small class='text-muted'> &nbsp;|&nbsp; Data: ".htmlspecialchars(date("d/m/Y", strtotime($data)))."</small>" : "")."
					</div>
					<div class='actions'>
							<button class='btn btn-default btn-sm' id='btnTogglePois' type='button' onclick='toggleCamadaPois()'><i class='fa fa-eye'></i> Mostrar/Esconder POIs</button>
							<button class='btn btn-default btn-sm' id='btnTogglePontos' type='button' onclick='toggleCamadaPontos()'><i class='fa fa-route'></i> Mostrar/Esconder Pontos</button>
							<a href='".$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/cadastro_poi.php' class='btn btn-default btn-sm'><i class='fa fa-arrow-left'></i> Voltar</a>
						</div>
				</div>
				<div class='portlet-body'>
					<div id='mapaPoiLogistica' style='width:100%; height:800px; border:1px solid #ccc;'></div>
				<div id='listaPoisFlag' class='margin-top-15'>
					<h4>Pontos de Interesse</h4>
					<table class='table table-condensed table-hover' id='tabelaPoisMapa'>
						<thead>
							<tr>
								<th style='width:30px;'></th>
								<th>Nome</th>
								<th>Tipo</th>
								<th>CNPJ / CPF</th>
								<th>Telefone</th>
								<th>Endereço</th>
								<th>CEP</th>
								<th>Raio</th>
								<th>Coordenadas</th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
				</div>
			</div>
		</div>";

		rodape();

		echo "
		<link rel='stylesheet' href='https://unpkg.com/leaflet@1.9.4/dist/leaflet.css' />
		<script src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'></script>
		<script>
		(function(){
			var pois = ".($poisJson ?: "[]").";
			var pontos = ".($pontosJson ?: "[]").";
			var poiTipos = ".($tiposJson ?: "[]").";
			var _eMap = {};
			poiTipos.forEach(function(t){ _eMap[t.poti_tx_codigo] = t.poti_tx_emoji; });

			var map = L.map('mapaPoiLogistica').setView([-7.2361, -35.8767], 13);

			// Camadas de mapa (mesmo padrao da logistica.php)
			var defaultLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				maxZoom: 19,
				attribution: '&copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a> contributors'
			});
			var satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
				maxZoom: 19, attribution: '&copy; Esri'
			});
			var hybridLayer = L.tileLayer('https://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
				maxZoom: 20, subdomains: ['mt0','mt1','mt2','mt3'], attribution: '&copy; Google'
			}).addTo(map);
			var terrainLayer = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
				maxZoom: 17, attribution: '&copy; OpenTopoMap'
			});
			L.control.layers({
				'Híbrido': hybridLayer,
				'OpenStreetMap': defaultLayer,
				'Satélite': satelliteLayer,
				'Terreno': terrainLayer
			}).addTo(map);

			var camadaPois   = L.layerGroup().addTo(map);
			var camadaPontos = L.layerGroup().addTo(map);
			var marcadoresPoi = {};
			var flags = {};

			// ---- Renderiza POIs ----
			var tbody = document.querySelector('#tabelaPoisMapa tbody');
			pois.forEach(function(p){
				var lat = parseFloat(p.poi_tx_latitude);
				var lng = parseFloat(p.poi_tx_longitude);
				if(!isFinite(lat) || !isFinite(lng)){ return; }

				var circle = L.circle([lat, lng], {
					radius: parseInt(p.poi_nb_raio, 10) || 50,
					color: '#4b6cb7', fillColor: '#4b6cb7', fillOpacity: 0.12
				}).addTo(camadaPois);

				var emoji = _eMap[p.poi_tx_icone] || '📌';
				var poiIcon = L.divIcon({
					className: 'poi-custom-icon',
					html: '<div style=\"background:transparent; width:28px; height:28px; display:flex; align-items:center; justify-content:center; font-size:24px; line-height:1;\">' + emoji + '</div>',
					iconSize: [28, 28], iconAnchor: [14, 14], popupAnchor: [0, -16]
				});

				var marker = L.marker([lat, lng], { icon: poiIcon }).addTo(camadaPois);
				marker.bindTooltip(p.poi_tx_nome, { permanent: false });
				var imgHtml = p.poi_tx_imagem ? '<img src=\"' + p.poi_tx_imagem + '?v=' + Date.now() + '\" style=\"max-width:180px; max-height:120px; border-radius:4px; margin-bottom:4px; display:block;\">' : '';
				var acoesHtml = p.poi_tx_acoes_esperadas
					? '<div style=\"margin-top:8px; padding:8px; background:#e8f4fd; border-left:4px solid #004173; border-radius:4px;\"><b>Ações Esperadas:</b> ' + p.poi_tx_acoes_esperadas + '</div>'
					: '';
				marker.bindPopup(
					'<div style=\"font-size:16px; line-height:1.7;\">' +
					imgHtml +
					'<b>' + p.poi_tx_nome + '</b><br>' +
					'<b>Tipo:</b> ' + emoji + ' ' + (p.poi_tx_icone || '-') + '<br>' +
					'<b>CNPJ / CPF:</b> ' + (p.poi_tx_cnpj || '-') + '<br>' +
					'<b>Telefone:</b> ' + (p.poi_tx_contato || '-') + '<br>' +
					(p.poi_tx_endereco ? '<b>Endereço:</b> ' + p.poi_tx_endereco + '<br>' : '') +
					(p.poi_tx_cep ? '<b>CEP:</b> ' + p.poi_tx_cep + '<br>' : '') +
					'<b>Raio:</b> ' + (p.poi_nb_raio || 50) + 'm<br>' +
					'<b>Lat/Lon:</b> ' + lat.toFixed(6) + ', ' + lng.toFixed(6) + '<br>' +
					acoesHtml +
					'</div>'
				);
				marcadoresPoi[p.poi_nb_id] = { marker: marker, circle: circle };

				// Linha da tabela com checkbox para flagar
				var tr = document.createElement('tr');
				tr.innerHTML =
					'<td><input type=\"checkbox\" data-poi-id=\"'+p.poi_nb_id+'\"></td>' +
					'<td>' + p.poi_tx_nome + '</td>' +
					'<td>' + emoji + ' ' + (p.poi_tx_icone || '-') + '</td>' +
					'<td>' + (p.poi_tx_cnpj || '-') + '</td>' +
					'<td>' + (p.poi_tx_contato || '-') + '</td>' +
					'<td>' + (p.poi_tx_endereco || '-') + '</td>' +
					'<td>' + (p.poi_tx_cep || '-') + '</td>' +
					'<td>' + (p.poi_nb_raio || 50) + 'm</td>' +
					'<td>' + lat.toFixed(6) + ', ' + lng.toFixed(6) + '</td>';
				tbody.appendChild(tr);
			});

			// ---- Renderiza pontos do motorista ----
			var latlngs = [];
			pontos.forEach(function(pt, idx){
				var lat = parseFloat(pt.pont_tx_latitude);
				var lng = parseFloat(pt.pont_tx_longitude);
				if(!isFinite(lat) || !isFinite(lng)){ return; }
				latlngs.push([lat, lng]);

				var m = L.circleMarker([lat, lng], {
					radius: 6, color: '#e67e22', fillColor: '#e67e22', fillOpacity: 0.9
				}).addTo(camadaPontos);
				var data = pt.pont_tx_data || '';
				m.bindPopup('<b>' + (pt.macr_tx_nome || 'Ponto') + '</b><br>' + data);
			});
			if(latlngs.length > 1){
				L.polyline(latlngs, { color: '#e67e22', weight: 3, opacity: 0.7 }).addTo(camadaPontos);
			}

			// Ajusta o zoom para englobar tudo.
			var todos = [];
			pois.forEach(function(p){
				var lat = parseFloat(p.poi_tx_latitude), lng = parseFloat(p.poi_tx_longitude);
				if(isFinite(lat) && isFinite(lng)){ todos.push([lat, lng]); }
			});
			latlngs.forEach(function(ll){ todos.push(ll); });
			if(todos.length > 0){
				map.fitBounds(todos, { padding: [40, 40] });
			}

			// ---- Toggle camadas ----
				window.toggleCamadaPois = function(){
					if(map.hasLayer(camadaPois)){ map.removeLayer(camadaPois); } else { camadaPois.addTo(map); }
				};
				window.toggleCamadaPontos = function(){
					if(map.hasLayer(camadaPontos)){ map.removeLayer(camadaPontos); } else { camadaPontos.addTo(map); }
				};

			// ---- Flagar POI ----
			tbody.addEventListener('change', function(e){
				var input = e.target;
				if(!input.matches('input[type=checkbox][data-poi-id]')){ return; }
				var id = input.getAttribute('data-poi-id');
				var obj = marcadoresPoi[id];
				if(!obj){ return; }
				if(input.checked){
					flags[id] = true;
					obj.circle.setStyle({ color: '#27ae60', fillColor: '#27ae60', fillOpacity: 0.35 });
					obj.marker.setIcon(L.divIcon({ className: 'leaflet-div-icon-flag', html: '<span style=\"font-size:22px; color:#27ae60;\">📍</span>', iconSize: [24, 24], iconAnchor: [12, 24] }));
				}else{
					delete flags[id];
					obj.circle.setStyle({ color: '#4b6cb7', fillColor: '#4b6cb7', fillOpacity: 0.12 });
					obj.marker.setIcon(new L.Icon.Default());
				}
			});
		})();
		</script>
		<style>
			.leaflet-div-icon-flag{ background: transparent; border: none; }
		</style>";
	}
