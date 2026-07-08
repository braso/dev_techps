<?php

		ini_set("display_errors", 1);
		error_reporting(E_ALL);

	include_once "load_env.php";
	include "check_permission.php";
	include_once "conecta.php";

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

		if(!empty($_POST["id"])){
			if($caminhoImagem){
				$dados["poi_tx_imagem"] = $caminhoImagem;
				// Remove imagem antiga
				$antigo = mysqli_fetch_assoc(query("SELECT poi_tx_imagem FROM poi WHERE poi_nb_id = ?", "i", [intval($_POST["id"])]));
				if(!empty($antigo["poi_tx_imagem"]) && file_exists($antigo["poi_tx_imagem"])){
					@unlink($antigo["poi_tx_imagem"]);
				}
			}
			atualizar("poi", array_keys($dados), array_values($dados), intval($_POST["id"]));
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

		$iconeAtual = $input_values["icone"] ?? "";
		$iconeOptions = [
			"" => "Padrão (azul)",
			"fa-box" => "📦 Caixa",
			"fa-building" => "🏢 Prédio",
			"fa-industry" => "🏭 Indústria",
			"fa-store" => "🏪 Loja",
			"fa-gas-pump" => "⛽ Posto",
			"fa-parking" => "🅿️ Estacionamento",
			"fa-hospital" => "🏥 Hospital",
			"fa-university" => "🏦 Banco",
			"fa-utensils" => "🍽️ Restaurante",
			"fa-hotel" => "🏨 Hotel",
			"fa-warehouse" => "🏭 Armazém",
			"fa-truck" => "🚚 Caminhão",
			"fa-map-pin" => "📍 Alfinete",
			"fa-flag-checkered" => "🏁 Ponto de Jornada"
		];
		$iconeHtml = "<select name='icone' class='form-control'>";
		foreach($iconeOptions as $val => $label){
			$sel = ($val === $iconeAtual) ? " selected" : "";
			$iconeHtml .= "<option value='".htmlspecialchars($val)."'$sel>".htmlspecialchars($label)."</option>";
		}
		$iconeHtml .= "</select>";

		$imagemAtual = $input_values["imagem"] ?? "";
		$imagemHtml = "<input type='file' name='imagem' accept='image/png,image/jpeg,image/gif,image/webp' class='form-control' style='padding:6px 10px;'>";
		if($imagemAtual){
			$cacheBreaker = "?v=" . time();
			$imagemHtml .= "<div style='margin-top:6px;'><img src='{$imagemAtual}{$cacheBreaker}' style='max-width:100%; max-height:80px; border-radius:4px; border:1px solid #ddd;'></div>";
		}

		$c = [
			campo("Nome*",				"nome",			$input_values["nome"],		4, "", "maxlength='150'"),
			campo("CNPJ",				"cnpj",			$input_values["cnpj"],		3, "MASCARA_CPF/CNPJ"),
			campo("Contato",			"contato",		$input_values["contato"],	3),
			campo("Endereço",			"endereco",		$input_values["endereco"],	6),
			campo("CEP",				"cep",			$input_values["cep"],		2, "MASCARA_CEP"),
			campo("Latitude*",			"latitude",		$input_values["latitude"],	2),
			campo("Longitude*",			"longitude",	$input_values["longitude"],	2),
			campo("Raio (metros)",		"raio",			$input_values["raio"],		2, "MASCARA_NUMERO"),
			"<div class='col-sm-3 margin-bottom-5 campo-fit-content'><label>Ícone</label>{$iconeHtml}</div>",
			"<div class='col-sm-4 margin-bottom-5 campo-fit-content'><label>Imagem do Local</label>{$imagemHtml}</div>"
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

		rodape();

		echo "
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
		$fields = [
			campo("Código",		"busca_codigo",		($_POST["busca_codigo"] ?? ""),	1, "MASCARA_NUMERO", "maxlength='6' min='0'"),
			campo("Nome",		"busca_nome_like",	($_POST["busca_nome_like"] ?? ""),	3, "", "maxlength='150'"),
			campo("CNPJ",		"busca_cnpj",		($_POST["busca_cnpj"] ?? ""),		2, "MASCARA_CPF/CNPJ"),
			combo("Status",		"busca_status",		($_POST["busca_status"] ?? "ativo"), 2, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"])
		];

		$buttons = [
			botao("Buscar", "index"),
			botao("Limpar Filtro", "limparFiltros"),
			botao("Inserir", "visualizarCadastro", "", "", "", "", "btn btn-success"),
			botao("Mapa de POIs", "abrirMapaPoi", "", "", "", "", "btn btn-info")
		];

		echo abre_form();
		echo linha_form($fields);
		echo fecha_form($buttons);

		// ---- Grid ----
			// O gridDinamico() adiciona "WHERE 1" automaticamente ao final da queryBase.
			// Como esta tabela nao tem JOIN, nao podemos usar $extra com AND solto.
			// O filtro de status e os demais ficam por conta do grid JS via searchFields.
			// O input busca_status tem default "ativo", entao o JS ja envia o filtro correto.
			$gridFields = [
				"CÓDIGO"	=> "poi_nb_id",
				"IMAGEM"	=> "poi_tx_imagem",
				"NOME"		=> "poi_tx_nome",
				"CNPJ"		=> "poi_tx_cnpj",
				"CONTATO"	=> "poi_tx_contato",
				"ENDEREÇO"	=> "poi_tx_endereco",
				"CEP"		=> "poi_tx_cep",
				"LATITUDE"	=> "poi_tx_latitude",
				"LONGITUDE" => "poi_tx_longitude",
				"RAIO (m)"	=> "poi_nb_raio",
				"STATUS"	=> "poi_tx_status"
			];

			$camposBusca = [
				"busca_codigo" 	=> "poi_nb_id",
				"busca_nome_like" => "poi_tx_nome",
				"busca_cnpj" 	=> "poi_tx_cnpj",
				"busca_status" 	=> "poi_tx_status"
			];

			$queryBase = "SELECT ".implode(", ", array_values($gridFields))." FROM poi";

		$actions = criarIconesGrid(
			["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
			["cadastro_poi.php", "cadastro_poi.php"],
			["editarPoi()", "excluirPoi()"]
		);
		$actions["functions"][1] .= "esconderInativar('glyphicon glyphicon-remove search-remove', 7);";

		$gridFields["actions"] = $actions["tags"];
		$jsFunctions = "const funcoesInternas = function(){ ".implode(" ", $actions["functions"])." }";

		echo gridDinamico("tabelaPoi", $gridFields, $camposBusca, $queryBase, $jsFunctions);

		rodape();
	}

	/**
	 * Mapa com os POIs cadastrados e os pontos batidos pelo motorista no dia.
	 * Permite flagar (marcar) POIs e mostrar os pontos de interesse.
	 */
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
					poi_tx_latitude, poi_tx_longitude, poi_nb_raio, poi_tx_icone
			 FROM poi
			 WHERE poi_tx_status = 'ativo'
			 ORDER BY poi_tx_nome ASC"
		), MYSQLI_ASSOC);

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
									<th>CNPJ</th>
									<th>Contato</th>
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

				var poiIcon = L.icon({
				iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png',
				shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
				iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
			});
			if(p.poi_tx_icone){
				var emojiMap = {
					'fa-box': '📦', 'fa-building': '🏢', 'fa-industry': '🏭', 'fa-store': '🏪',
					'fa-gas-pump': '⛽', 'fa-parking': '🅿️', 'fa-hospital': '🏥', 'fa-university': '🏦',
					'fa-utensils': '🍽️', 'fa-hotel': '🏨', 'fa-warehouse': '🏭', 'fa-truck': '🚚',
				'fa-map-pin': '📍',
				'fa-flag-checkered': '🏁'
			};
				var e = emojiMap[p.poi_tx_icone] || '📌';
				poiIcon = L.divIcon({
					className: 'poi-custom-icon',
					html: '<div style=\"background:#004173; color:white; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:18px; box-shadow:0 2px 6px rgba(0,0,0,.3); border:2px solid white;\">' + e + '</div>',
					iconSize: [32, 32], iconAnchor: [16, 32], popupAnchor: [0, -36]
				});
			}
			var marker = L.marker([lat, lng], { icon: poiIcon }).addTo(camadaPois);
				marker.bindTooltip(p.poi_tx_nome, { permanent: false });
				var imgHtml = p.poi_tx_imagem ? '<img src=\"' + p.poi_tx_imagem + '?v=' + Date.now() + '\" style=\"max-width:180px; max-height:120px; border-radius:4px; margin-bottom:4px; display:block;\">' : '';
				marker.bindPopup(
					imgHtml +
					'<b>' + p.poi_tx_nome + '</b><br>' +
					'CNPJ: ' + (p.poi_tx_cnpj || '-') + '<br>' +
					'Contato: ' + (p.poi_tx_contato || '-') + '<br>' +
					'Raio: ' + (p.poi_nb_raio || 50) + 'm'
				);
				marcadoresPoi[p.poi_nb_id] = { marker: marker, circle: circle };

				// Linha da tabela com checkbox para flagar
				var tr = document.createElement('tr');
				tr.innerHTML =
					'<td><input type=\"checkbox\" data-poi-id=\"'+p.poi_nb_id+'\"></td>' +
					'<td>' + p.poi_tx_nome + '</td>' +
					'<td>' + (p.poi_tx_cnpj || '-') + '</td>' +
					'<td>' + (p.poi_tx_contato || '-') + '</td>' +
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
