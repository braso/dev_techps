<?php
/*
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
*/
		header("Expires: 01 Jan 2001 00:00:00 GMT");
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Cache-Control: post-check=0, pre-check=0", FALSE);


	// ---- Helper para salvar ações esperadas de POI ----
	function salvarAcoesEsperadasPoi($poiId, $acoes){
		query("CREATE TABLE IF NOT EXISTS poi_acao_esperada (
			paes_nb_id INT AUTO_INCREMENT PRIMARY KEY,
			paes_nb_poi INT NOT NULL,
			paes_tx_codigo VARCHAR(50) NOT NULL,
			UNIQUE KEY uk_poi_acao (paes_nb_poi, paes_tx_codigo)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		query("DELETE FROM poi_acao_esperada WHERE paes_nb_poi = ?", "i", [$poiId]);
		$acoes = is_array($acoes) ? $acoes : [];
		foreach($acoes as $acao){
			$acao = trim($acao);
			if($acao !== ''){
				query("INSERT IGNORE INTO poi_acao_esperada (paes_nb_poi, paes_tx_codigo) VALUES (?, ?)", "is", [$poiId, $acao]);
			}
		}
	}

	// Processa salvamento de POI via AJAX (chamado pelo router via acao=salvar_poi)
	function salvar_poi(){
		query("CREATE TABLE IF NOT EXISTS poi (
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
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$rsCheck = query("SHOW COLUMNS FROM poi LIKE 'poi_tx_icone'");
		if($rsCheck && !mysqli_fetch_assoc($rsCheck)){
			query("ALTER TABLE poi ADD COLUMN poi_tx_icone VARCHAR(50) NOT NULL DEFAULT '' AFTER poi_nb_raio");
		}
		$rsCheck2 = query("SHOW COLUMNS FROM poi LIKE 'poi_tx_endereco'");
		if($rsCheck2 && !mysqli_fetch_assoc($rsCheck2)){
			query("ALTER TABLE poi ADD COLUMN poi_tx_endereco VARCHAR(255) NOT NULL DEFAULT '' AFTER poi_tx_icone");
			query("ALTER TABLE poi ADD COLUMN poi_tx_cep VARCHAR(10) NOT NULL DEFAULT '' AFTER poi_tx_endereco");
		}
		$rsCheckImg = query("SHOW COLUMNS FROM poi LIKE 'poi_tx_imagem'");
		if($rsCheckImg && !mysqli_fetch_assoc($rsCheckImg)){
			query("ALTER TABLE poi ADD COLUMN poi_tx_imagem VARCHAR(255) NOT NULL DEFAULT '' AFTER poi_tx_cep");
		}
		header("Content-Type: application/json");
		$erro = "";
		$novoId = null;

		$nome = trim($_POST["nome"] ?? "");
		$cnpj = preg_replace('/[^0-9]/', '', (string)($_POST["cnpj"] ?? ""));
		$contato = trim($_POST["contato"] ?? "");
		$endereco = trim($_POST["endereco"] ?? "");
		$cep = trim($_POST["cep"] ?? "");
		$latitude = str_replace(",", ".", trim($_POST["latitude"] ?? ""));
		$longitude = str_replace(",", ".", trim($_POST["longitude"] ?? ""));
		$raio = intval($_POST["raio"] ?? 50);
		$icone = trim($_POST["icone"] ?? "");
		$caminhoImagem = "";

		if(!empty($_FILES["imagem"]) && $_FILES["imagem"]["error"] === UPLOAD_ERR_OK){
			$dir = __DIR__ . "/arquivos/poi";
			if(!is_dir($dir)){ @mkdir($dir, 0755, true); }
			$ext = strtolower(pathinfo($_FILES["imagem"]["name"], PATHINFO_EXTENSION));
			$extsPermitidas = ["jpg","jpeg","png","gif","webp"];
			if(in_array($ext, $extsPermitidas)){
				$nomeUnico = "poi_".time()."_".bin2hex(random_bytes(4)).".".$ext;
				$destino = $dir."/".$nomeUnico;
				if(move_uploaded_file($_FILES["imagem"]["tmp_name"], $destino)){
					$caminhoImagem = "arquivos/poi/".$nomeUnico;
				}
			}
		}

		if(empty($nome)){
			echo json_encode(["sucesso" => false, "erro" => "Nome é obrigatório"]);
			exit;
		}
		if(!is_numeric($latitude) || !is_numeric($longitude)){
			echo json_encode(["sucesso" => false, "erro" => "Latitude e Longitude inválidas"]);
			exit;
		}
		if($raio <= 0){ $raio = 50; }

		$userId = !empty($_SESSION["user_nb_id"]) ? (int)$_SESSION["user_nb_id"] : 0;
		$editId = !empty($_POST["id"]) ? (int)$_POST["id"] : 0;

		$dupCheck = query("SELECT poi_nb_id FROM poi WHERE poi_tx_nome = ? AND poi_tx_status = 'ativo' LIMIT 1", "s", [$nome]);
		$dupRow = $dupCheck ? mysqli_fetch_assoc($dupCheck) : null;
		if($dupRow && (!empty($dupRow["poi_nb_id"]) && intval($dupRow["poi_nb_id"]) !== $editId)){
			echo json_encode(["sucesso" => false, "erro" => "Já existe um POI com este nome."]);
			exit;
		}

		if($editId > 0){
			$dados = [
				"poi_tx_nome"       => $nome,
				"poi_tx_cnpj"       => $cnpj,
				"poi_tx_contato"    => $contato,
				"poi_tx_endereco"   => $endereco,
				"poi_tx_cep"        => $cep,
				"poi_tx_latitude"   => $latitude,
				"poi_tx_longitude"  => $longitude,
				"poi_nb_raio"       => $raio,
				"poi_tx_icone"      => $icone
			];
			if($caminhoImagem){
				$dados["poi_tx_imagem"] = $caminhoImagem;
				$antigo = mysqli_fetch_assoc(query("SELECT poi_tx_imagem FROM poi WHERE poi_nb_id = ?", "i", [$editId]));
				if(!empty($antigo["poi_tx_imagem"]) && file_exists($antigo["poi_tx_imagem"])){
					@unlink($antigo["poi_tx_imagem"]);
				}
			}
			atualizar("poi", array_keys($dados), array_values($dados), strval($editId));
			$dados["poi_nb_id"] = $editId;
			salvarAcoesEsperadasPoi($editId, $_POST["acoes_esperadas"] ?? []);
			echo json_encode(["sucesso" => true, "id" => $editId, "poi" => $dados]);
			exit;
		}

		$dados = [
			"poi_tx_nome"       => $nome,
			"poi_tx_cnpj"       => $cnpj,
			"poi_tx_contato"    => $contato,
			"poi_tx_endereco"   => $endereco,
			"poi_tx_cep"        => $cep,
			"poi_tx_latitude"   => $latitude,
			"poi_tx_longitude"  => $longitude,
			"poi_nb_raio"       => $raio,
			"poi_tx_icone"      => $icone,
			"poi_tx_status"     => "ativo",
			"poi_nb_userCadastro" => $userId,
			"poi_tx_dataCadastro" => date("Y-m-d H:i:s")
		];
		if($caminhoImagem){
			$dados["poi_tx_imagem"] = $caminhoImagem;
		}

		$res = inserir("poi", array_keys($dados), array_values($dados));

		if(gettype($res[0] ?? null) === "object"){
			echo json_encode(["sucesso" => false, "erro" => $res[0]->getMessage()]);
			exit;
		}

		$novoId = $res[0] ?? null;

		if($novoId){
			salvarAcoesEsperadasPoi($novoId, $_POST["acoes_esperadas"] ?? []);
		}

		echo json_encode(["sucesso" => true, "id" => $novoId, "poi" => $dados]);
		exit;
	}

	include_once "funcoes_ponto.php";

	function cadastrarAjuste(){
		try{
			$matricula = mysqli_fetch_assoc(query(
				"SELECT enti_tx_matricula FROM entidade
					WHERE enti_tx_status = 'ativo'
						AND enti_nb_id = {$_POST["idMotorista"]}
					LIMIT 1;"
			))["enti_tx_matricula"];
			$newPonto = conferirErroPonto($matricula, new DateTime("{$_POST["data"]} {$_POST["hora"]}"), $_POST["idMacro"], $_POST["motivo"], $_POST["justificativa"]);
		}catch(Exception $e){
			set_status($e->getMessage());
			index();
			exit;
		}

		//Conferir se já existe um ponto naquele segundo para adicionar o próximo 1 segundo após{
			$temPonto = mysqli_fetch_assoc(query(
				"SELECT pont_nb_id, pont_tx_data FROM ponto
					WHERE pont_tx_status = 'ativo'
						AND pont_tx_matricula = '{$matricula}'
						AND STR_TO_DATE(pont_tx_data, '%Y-%m-%d %H:%i') = STR_TO_DATE('".($_POST["data"]." ".$_POST["hora"])."', '%Y-%m-%d %H:%i')
					ORDER BY pont_tx_data DESC
					LIMIT 1;"
			));
	
			if(!empty($temPonto["pont_tx_data"])){
				$seg = explode(":", $temPonto["pont_tx_data"])[2];
				$seg = intval($seg)+1;
				$newPonto["pont_tx_data"] = "{$_POST["data"]} {$_POST["hora"]}:".str_pad(strval($seg), 2, "0", STR_PAD_LEFT);
			}
		//}

		inserir("ponto", array_keys($newPonto), array_values($newPonto));
		index();
		exit;
	}

    function excluirPonto(){

        $ids = [];
        if(!empty($_POST["idPonto"])){
            $ids = is_array($_POST["idPonto"]) ? $_POST["idPonto"] : [$_POST["idPonto"]];
        }

        if(empty($ids) || empty($_POST["justificativa"])){
            set_status("ERRO: Não foi possível inativar o ponto.");
            index();
            exit;
        }

        if(empty($_POST["dataAtualiza"])){
            $_POST["dataAtualiza"] = date("Y-m-d H:i:s");
            $_POST["userAtualiza"] = $_SESSION["user_nb_id"];
        }

        $dataRef = null;
        foreach($ids as $id){
            $ponto = mysqli_fetch_assoc(query(
                "SELECT * FROM ponto 
                    WHERE pont_nb_id = {$id} 
                    LIMIT 1;"
            ));
            if(empty($ponto)){ continue; }
            if($ponto["pont_tx_status"] == "inativo"){ continue; }
            $ponto["pont_tx_status"] = "inativo";
            $ponto["pont_tx_justificativa"] = $_POST["justificativa"];
            $ponto["pont_tx_dataAtualiza"] = $_POST["dataAtualiza"];
            $ponto["pont_nb_userAtualiza"] = $_POST["userAtualiza"];
            atualizar("ponto", array_keys($ponto), array_values($ponto), $ponto["pont_nb_id"]);
            if(empty($dataRef)){
                $dataRef = explode(" ", $ponto["pont_tx_data"])[0];
            }
        }

        if(!empty($dataRef)){
            $_POST["data"] = $dataRef;
        }

        index();
        exit;
    }

	function status() {
		return  
			"<style>
				#statusDiv{
					display: inline-flex;
				}
				#status-label{
					margin-right: 10px; 
				}
				#status {
					margin-top: -5px;
					width: 93px;
				}
				</style>
				<div id='statusDiv'>
					<label id='status-label'>Status:</label>
					<select name='status' id='status' class='form-control input-sm campo-fit-content' onchange='atualizar_form({$_POST["idMotorista"]}, \"{$_POST["data"]}\", this.value)'>
						<option value='ativo'>Ativos</option>
						<option value='inativo' ".((!empty($_POST["status"]) && $_POST["status"] == "inativo")? "selected": "").">Inativos</option>
					</select>
				</div>"
		;
	}

	// Função para carregar os CNPJs formatados da tabela "empresa"
	// function carregarCNPJsFormatados() {
	// 	global $conn;
	// 	// Consulta SQL para buscar os CNPJs
	// 	$sql = "SELECT empr_tx_cnpj FROM empresa";

	// 	$result = mysqli_query($conn, $sql);

	// 	if (!$result) {
	// 		die("Erro ao consultar CNPJs: ".mysqli_error($conn));
	// 	}

	// 	$cnpjs_formatados = [];
	// 	while ($row = mysqli_fetch_assoc($result)) {
	// 		// Remove pontos, traços e barras do CNPJ
	// 		$cnpj_formatado = preg_replace("/[^0-9]/", "", $row["empr_tx_cnpj"]);
	// 		$cnpjs_formatados[] = $cnpj_formatado;
	// 	}
	// 	return $cnpjs_formatados;
	// }

	function carregarJS(){

		$postValues = $_POST;
		$postValues["acao"] = '';
		$postValues["idPonto"] = '';
		unset($postValues["id"]);
		unset($postValues["errorFields"]);
		unset($postValues["msg_status"]);
		$postValues = json_encode($postValues);
		echo 
			"<script>
				function imprimir() {
					// Abrir a caixa de diálogo de impressão
					window.print();
				}

				function addPostValuesToForm(form, postValues){
					input = '';
					for(key in postValues){
						input = document.createElement('input');
						input.type = 'hidden';
						input.value = postValues[key];
						input.name = key;
						if(Array.isArray(postValues[key])){
							input.name += '[]';
							for(f2 in postValues[key]){
								newInput = document.createElement('input');
								newInput.type = input.type;
								newInput.name = input.name;
								newInput.value = postValues[key][f2];
								form.append(newInput);
							}
						}else{
							form.append(input);
						}
					}
				}

				valorDataInicial = document.getElementById('data').value;
				valorStatusInicial = document.getElementById('status').value;

				function atualizar_form(motorista, data, status){
					if(data == null){
						data = document.getElementById('data').value;
					}
					if(status == null){
						status = document.getElementById('status').value;
					}

					if(valorDataInicial != data || valorStatusInicial != status){
						var form = document.form_ajuste_status;
						addPostValuesToForm(form, {$postValues});
						form.acao.value = 'index';
						form.data.value = data;
						form.status.value = status;
						form.submit();
					}
				}

                function excluirPontoJS(idPonto){
                    var selecionados = [];
                    var checks = document.querySelectorAll('input.bulk-excluir:checked');
                    for(var i=0;i<checks.length;i++){ selecionados.push(checks[i].getAttribute('data-id')); }
                    if(selecionados.length === 0 && idPonto){ selecionados.push(String(idPonto)); }
                    if(selecionados.length === 0){ return; }

                    Swal.fire({
                        icon: 'warning',
                        title: 'Excluir ponto(s)',
                        html: '<p>Ao excluir o ponto, a recuperação torna-se irreversível.</p>'+
                              '<label style=\'display:block;text-align:left;margin-top:10px;\'>Justificativa</label>'+
                              '<textarea id=\'swal-justificativa\' class=\'swal2-textarea\' placeholder=\'Descreva o motivo\'></textarea>',
                        showCancelButton: true,
                        confirmButtonText: 'Excluir',
                        cancelButtonText: 'Cancelar',
                        focusConfirm: false,
                        preConfirm: function(){
                            var v = document.getElementById('swal-justificativa').value.trim();
                            if(!v){ Swal.showValidationMessage('Informe a justificativa'); }
                            return v;
                        }
                    }).then(function(res){
                        if(!res.isConfirmed || !res.value){ return; }
                        var form = document.form_ajuste_status;
                        addPostValuesToForm(form, {$postValues});
                        form.acao.value = 'excluirPonto';
                        for(var j=0;j<selecionados.length;j++){
                            var inp = document.createElement('input');
                            inp.type = 'hidden';
                            inp.name = 'idPonto[]';
                            inp.value = selecionados[j];
                            form.append(inp);
                        }
                        var justificativa = document.createElement('input');
                        justificativa.type = 'hidden';
                        justificativa.name = 'justificativa';
                        justificativa.value = res.value;
                        form.append(justificativa);
                        form.submit();
                    });
                }
            </script>"
        ;
	}



	


		 function index() {

		//ARQUIVO QUE VALIDA A PERMISSAO VIA PERFIL DE USUARIO VINCULADO
		include_once "check_permission.php";
		// APATH QUE O USER ESTA TENTANDO ACESSAR PARA VERIFICAR NO PERFIL SE TEM ACESSO2
		if(!temPermissaoMenu('/espelho_ponto.php')){
			verificaPermissao('/ajuste_ponto.php');
		}
		
		
		global $CONTEX;

		//Conferir se os campos de $_POST estão vazios{
			if((empty($_POST["idMotorista"]) || empty($_POST["data"])) && empty($_POST["action"])){
				echo "<script>alert('ERRO: Deve ser selecionado um funcionário e uma data para ajustar.')</script>";
				
				$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/espelho_ponto.php";
				if(empty($_POST["returnValues"])){
					$_POST["returnValues"] = json_encode($_POST);
				}
				voltar();
				exit;
			}
	
			if (empty($_POST["status"])) {
				$_POST["status"] = "ativo";
			}
		//}

		// POIs ativos (carregado antes do cabecalho para popular o sidebar de POI)
		$__poisRes = query(
			"SELECT poi_nb_id, poi_tx_nome, poi_tx_cnpj, poi_tx_contato,
					poi_tx_latitude, poi_tx_longitude, poi_nb_raio, poi_tx_icone,
					poi_tx_endereco, poi_tx_cep, poi_tx_imagem,
					(SELECT GROUP_CONCAT(paes_tx_codigo ORDER BY paes_tx_codigo SEPARATOR ', ') FROM poi_acao_esperada WHERE paes_nb_poi = poi.poi_nb_id) AS poi_tx_acoes_esperadas
			 FROM poi
			 WHERE poi_tx_status = 'ativo'
			 ORDER BY poi_tx_nome ASC"
		);
		$__pois = ($__poisRes instanceof mysqli_result) ? mysqli_fetch_all($__poisRes, MYSQLI_ASSOC) : [];
		// Garante tabela poi_tipo + carrega tipos
		query("CREATE TABLE IF NOT EXISTS poi_tipo (
			poti_nb_id INT AUTO_INCREMENT PRIMARY KEY,
			poti_tx_codigo VARCHAR(50) NOT NULL UNIQUE,
			poti_tx_nome VARCHAR(100) NOT NULL,
			poti_tx_emoji VARCHAR(10) NOT NULL DEFAULT '📌',
			poti_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo'
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$__tiposPoi = [];
		$__rsTipos = query("SELECT poti_tx_codigo, poti_tx_nome, poti_tx_emoji FROM poi_tipo WHERE poti_tx_status = 'ativo' ORDER BY poti_tx_nome ASC");
		while($__rsTipos && ($__r = mysqli_fetch_assoc($__rsTipos))){ $__tiposPoi[] = $__r; }
		$__tiposPoiJson = json_encode($__tiposPoi ?: [], JSON_UNESCAPED_UNICODE);
		$__poisJson = json_encode($__pois ?: [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$__sqlErr = $GLOBALS["last_sql_error"] ?? null;
		echo "<script>console.log('[AJUSTE POI] tipos carregados no PHP: ', ".count($__tiposPoi)."); ";
		if($__sqlErr){
			echo "console.error('[AJUSTE POI] SQL error: ', ".json_encode($__sqlErr, JSON_UNESCAPED_UNICODE).");";
		}
		echo "</script>";

		cabecalho("Ajuste de Ponto");

?>
<!-- Menu de contexto do mapa de eventos -->
<div id="ajusteMapContextMenu" style="display:none; position:fixed; background:white; border:1px solid #ccc; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,.2); z-index:20000; min-width:180px; padding:4px 0;">
	<div style="padding:8px 14px; cursor:pointer; font-size:14px; display:flex; align-items:center; gap:8px;" onclick="ajusteAbrirCadastroPoi()">
		<span style="font-size:18px;">📌</span> Cadastrar POI
	</div>
</div>

<!-- Sidebar de cadastro de POI -->
<div id="ajustePoiSidebar" style="display:none; position:fixed; top:0; right:0; width:400px; max-width:95%; height:100vh; background:white; box-shadow:-4px 0 20px rgba(0,0,0,.2); z-index:20001; overflow-y:auto; padding:20px; transition:right .3s ease;">
	<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
		<h4 style="margin:0; font-size:18px;">📌 Cadastrar POI</h4>
		<button type="button" onclick="ajusteFecharModalPoi()" style="background:none; border:none; font-size:22px; cursor:pointer; padding:4px 8px; color:#999;">&times;</button>
	</div>
	<form id="ajustePoiForm" onsubmit="return ajusteSalvarPoi(event)">
		<input type="hidden" id="ajuste_poi_id" name="id" value="0">
		<input type="hidden" id="ajuste_poi_latitude" name="latitude">
		<input type="hidden" id="ajuste_poi_longitude" name="longitude">
		<div style="margin-bottom:12px;">
			<label style="display:block; font-size:13px; font-weight:600; margin-bottom:3px;">Nome *</label>
			<input type="text" id="ajuste_poi_nome" name="nome" required style="width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px;">
		</div>
		<div style="margin-bottom:12px;">
			<label style="display:block; font-size:13px; font-weight:600; margin-bottom:3px;">Endereço</label>
			<input type="text" id="ajuste_poi_endereco" name="endereco" style="width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px;">
		</div>
		<div style="margin-bottom:12px; display:flex; gap:10px;">
			<div style="flex:1;">
				<label style="display:block; font-size:13px; font-weight:600; margin-bottom:3px;">CEP</label>
				<input type="text" id="ajuste_poi_cep" name="cep" style="width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px;">
			</div>
			<div style="flex:1;">
				<label style="display:block; font-size:13px; font-weight:600; margin-bottom:3px;">CNPJ / CPF</label>
				<input type="text" id="ajuste_poi_cnpj" name="cnpj" style="width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px;">
			</div>
		</div>
		<div style="margin-bottom:12px;">
			<label style="display:block; font-size:13px; font-weight:600; margin-bottom:3px;">Telefone</label>
			<input type="text" id="ajuste_poi_contato" name="contato" style="width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px;">
		</div>
		<div style="margin-bottom:12px;">
			<div style="display:flex; gap:6px; align-items:flex-end;">
				<div style="flex:1;">
					<label style="display:block; font-size:13px; font-weight:600; margin-bottom:3px;">Latitude</label>
					<input type="text" id="ajuste_poi_lat_display" disabled style="width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px; background:#f5f5f5;">
				</div>
				<div style="flex:1;">
					<label style="display:block; font-size:13px; font-weight:600; margin-bottom:3px;">Longitude</label>
					<input type="text" id="ajuste_poi_lon_display" disabled style="width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px; background:#f5f5f5;">
				</div>
				<button type="button" onclick="ajusteEscolherPontoMapa()" style="height:38px; padding:8px 10px; border:1px solid #004173; border-radius:6px; background:#004173; color:white; cursor:pointer; font-size:12px; white-space:nowrap;">📍 Mapa</button>
			</div>
		</div>
		<div style="margin-bottom:12px; display:flex; gap:10px;">
			<div style="flex:1;">
				<label style="display:block; font-size:13px; font-weight:600; margin-bottom:3px;">Raio (metros)</label>
				<input type="range" id="ajuste_poi_raio_range" min="10" max="500" value="50" style="width:100%;">
				<input type="number" id="ajuste_poi_raio" name="raio" value="50" min="1" style="width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px; margin-top:4px;">
			</div>
			<div style="flex:1;">
				<label style="display:block; font-size:13px; font-weight:600; margin-bottom:3px;">Tipo de POI</label>
				<select id="ajuste_poi_icone" name="icone" style="width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px;">
					<option value="">Selecione o tipo</option>
					<?php foreach($__tiposPoi as $t): ?>
					<option value="<?=htmlspecialchars($t['poti_tx_codigo'])?>" data-emoji="<?=htmlspecialchars($t['poti_tx_emoji'])?>"><?=$t['poti_tx_emoji']?> <?=htmlspecialchars($t['poti_tx_nome'])?></option>
					<?php endforeach; ?>
					<option value="__novo__" style="color:#004173; font-weight:600;">➕ Criar novo tipo...</option>
				</select>
			</div>
		</div>
		<div style="margin-bottom:12px;">
			<label style="display:block; font-size:13px; font-weight:600; margin-bottom:3px;">Ações Esperadas</label>
			<select id="ajuste_poi_acoes_esperadas" name="acoes_esperadas[]" multiple style="width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px; min-height:80px;">
				<option value="Jornada">Jornada</option>
				<option value="Refeição">Refeição</option>
				<option value="Espera">Espera</option>
				<option value="Descanso">Descanso</option>
				<option value="Repouso">Repouso</option>
				<option value="Pernoite">Pernoite</option>
			</select>
		</div>
		<div style="margin-bottom:12px;">
			<label style="display:block; font-size:13px; font-weight:600; margin-bottom:3px;">Imagem do Local</label>
			<input type="file" id="ajuste_poi_imagem" name="imagem" accept="image/png,image/jpeg,image/gif,image/webp" style="width:100%; padding:6px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px;">
			<div id="ajuste_poi_imagem_preview" style="margin-top:4px;"></div>
		</div>
		<div style="display:flex; gap:10px; margin-top:16px;">
			<button type="button" onclick="ajusteFecharModalPoi()" style="flex:1; padding:10px; border:1px solid #ccc; border-radius:6px; background:#f5f5f5; cursor:pointer; font-size:14px;">Cancelar</button>
			<button type="submit" style="flex:1; padding:10px; border:none; border-radius:6px; background:#004173; color:white; cursor:pointer; font-size:14px;">Salvar POI</button>
		</div>
	</form>
</div>

<style>
.ajuste-emojigrid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;margin-top:6px;max-height:220px;overflow-y:auto;padding:4px;border:1px solid #eee;border-radius:8px;background:#fafafa;}
.ajuste-emojigrid button{font-size:24px;width:44px;height:44px;display:flex;align-items:center;justify-content:center;border:2px solid transparent;border-radius:8px;background:white;cursor:pointer;transition:all .15s;}
.ajuste-emojigrid button:hover{border-color:#004173;background:#e8f0fe;transform:scale(1.12);}
.ajuste-emojigrid button.selecionado{border-color:#004173;background:#d0e2ff;box-shadow:0 0 0 2px #004173;transform:scale(1.1);}
</style>
<div id="ajusteModalNovoTipoPoi" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.5); z-index:99999; align-items:center; justify-content:center;">
	<div style="background:white; border-radius:12px; padding:24px; width:480px; max-width:95%; box-shadow:0 8px 30px rgba(0,0,0,.3); position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);">
		<h3 style="margin:0 0 4px 0; font-size:18px;">➕ Criar novo tipo de POI</h3>
		<p style="color:#666; font-size:13px; margin-bottom:14px;">Dê um nome e escolha um ícone.</p>
		<div style="margin-bottom:10px;">
			<label style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;">Nome <span style="color:red;">*</span></label>
			<input type="text" id="ajusteNovoTipoNome" class="form-control" placeholder="Ex: Escola" style="width:100%;" autocomplete="off">
		</div>
		<div style="margin-bottom:14px;">
			<label style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;">Ícone <span style="color:red;">*</span> <span id="ajusteEmojiSelecionado" style="font-size:18px; margin-left:6px;"></span></label>
			<div class="ajuste-emojigrid" id="ajusteGradeEmojis">
				<button type="button" data-emoji="📦">📦</button><button type="button" data-emoji="🏢">🏢</button><button type="button" data-emoji="🏭">🏭</button><button type="button" data-emoji="🏪">🏪</button><button type="button" data-emoji="⛽">⛽</button><button type="button" data-emoji="🅿️">🅿️</button><button type="button" data-emoji="🏥">🏥</button>
				<button type="button" data-emoji="🏦">🏦</button><button type="button" data-emoji="🍽️">🍽️</button><button type="button" data-emoji="🏨">🏨</button><button type="button" data-emoji="🚚">🚚</button><button type="button" data-emoji="📍">📍</button><button type="button" data-emoji="🏁">🏁</button><button type="button" data-emoji="🏫">🏫</button>
				<button type="button" data-emoji="🛒">🛒</button><button type="button" data-emoji="⚕️">⚕️</button><button type="button" data-emoji="🔧">🔧</button><button type="button" data-emoji="⚙️">⚙️</button><button type="button" data-emoji="🛠️">🛠️</button><button type="button" data-emoji="🚛">🚛</button><button type="button" data-emoji="🚌">🚌</button>
				<button type="button" data-emoji="🚕">🚕</button><button type="button" data-emoji="✈️">✈️</button><button type="button" data-emoji="⚓">⚓</button><button type="button" data-emoji="🚢">🚢</button><button type="button" data-emoji="🚂">🚂</button><button type="button" data-emoji="🏗️">🏗️</button><button type="button" data-emoji="🏠">🏠</button>
				<button type="button" data-emoji="⛪">⛪</button><button type="button" data-emoji="🎓">🎓</button><button type="button" data-emoji="📚">📚</button><button type="button" data-emoji="📋">📋</button><button type="button" data-emoji="🛡️">🛡️</button><button type="button" data-emoji="🔒">🔒</button><button type="button" data-emoji="🔑">🔑</button>
				<button type="button" data-emoji="🪪">🪪</button><button type="button" data-emoji="📞">📞</button><button type="button" data-emoji="🖥️">🖥️</button><button type="button" data-emoji="🚧">🚧</button><button type="button" data-emoji="🧰">🧰</button><button type="button" data-emoji="🧲">🧲</button><button type="button" data-emoji="🔋">🔋</button>
				<button type="button" data-emoji="🍕">🍕</button><button type="button" data-emoji="🍔">🍔</button><button type="button" data-emoji="☕">☕</button><button type="button" data-emoji="🥤">🥤</button><button type="button" data-emoji="🧃">🧃</button><button type="button" data-emoji="🏟️">🏟️</button><button type="button" data-emoji="🎪">🎪</button>
				<button type="button" data-emoji="🎯">🎯</button><button type="button" data-emoji="🎳">🎳</button><button type="button" data-emoji="🎮">🎮</button><button type="button" data-emoji="🌲">🌲</button><button type="button" data-emoji="🌳">🌳</button><button type="button" data-emoji="🏔️">🏔️</button><button type="button" data-emoji="🏝️">🏝️</button>
				<button type="button" data-emoji="🏖️">🏖️</button><button type="button" data-emoji="🚁">🚁</button><button type="button" data-emoji="🛸">🛸</button><button type="button" data-emoji="🚤">🚤</button><button type="button" data-emoji="🚑">🚑</button><button type="button" data-emoji="🚒">🚒</button><button type="button" data-emoji="⚖️">⚖️</button>
				<button type="button" data-emoji="🏛️">🏛️</button><button type="button" data-emoji="📊">📊</button><button type="button" data-emoji="📜">📜</button><button type="button" data-emoji="🛋️">🛋️</button><button type="button" data-emoji="🛏️">🛏️</button><button type="button" data-emoji="🚿">🚿</button><button type="button" data-emoji="🧹">🧹</button>
				<button type="button" data-emoji="🩺">🩺</button><button type="button" data-emoji="💊">💊</button><button type="button" data-emoji="🔬">🔬</button><button type="button" data-emoji="🧪">🧪</button><button type="button" data-emoji="📡">📡</button><button type="button" data-emoji="📷">📷</button><button type="button" data-emoji="🎨">🎨</button>
				<button type="button" data-emoji="🖼️">🖼️</button><button type="button" data-emoji="🎵">🎵</button><button type="button" data-emoji="🎭">🎭</button><button type="button" data-emoji="📝">📝</button><button type="button" data-emoji="⚽">⚽</button><button type="button" data-emoji="🏀">🏀</button><button type="button" data-emoji="🎾">🎾</button>
				<button type="button" data-emoji="🏐">🏐</button><button type="button" data-emoji="🚴">🚴</button><button type="button" data-emoji="🏧">🏧</button><button type="button" data-emoji="💳">💳</button><button type="button" data-emoji="💰">💰</button><button type="button" data-emoji="🧯">🧯</button><button type="button" data-emoji="🗑️">🗑️</button>
			</div>
		</div>
		<div style="display:flex; gap:10px;">
			<button type="button" onclick="ajusteFecharModalNovoTipoPoi()" style="flex:1; padding:10px; border:1px solid #ccc; border-radius:6px; background:#f5f5f5; cursor:pointer; font-size:14px;">Cancelar</button>
			<button type="button" onclick="ajusteSalvarNovoTipoPoi()" style="flex:1; padding:10px; border:none; border-radius:6px; background:#004173; color:white; cursor:pointer; font-size:14px;">Salvar Tipo</button>
		</div>
		<div id="ajusteNovoTipoStatus" style="margin-top:12px; font-size:13px;"></div>
	</div>
</div>
<?php
		$motorista = mysqli_fetch_assoc(query(
			"SELECT enti_nb_id, enti_tx_matricula, enti_tx_nome, enti_tx_ocupacao, enti_tx_cpf, enti_nb_empresa FROM entidade
				WHERE enti_tx_status = 'ativo'
					AND enti_nb_id = {$_POST["idMotorista"]}
				LIMIT 1;"
		));

		$endosso = mysqli_fetch_array(query(
			"SELECT user_tx_login as endo_nb_userCadastro, endo_tx_dataCadastro FROM endosso
				JOIN user ON endo_nb_userCadastro = user_nb_id
				WHERE endo_tx_status = 'ativo'
					AND '{$_POST["data"]}' BETWEEN endo_tx_de AND endo_tx_ate
					AND endo_nb_entidade = '{$motorista["enti_nb_id"]}'
				LIMIT 1;"
		), MYSQLI_BOTH);

		$botao_imprimir = "<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>";

		$cnpjs = mysqli_fetch_all(query("SELECT empr_tx_cnpj FROM empresa"), MYSQLI_ASSOC);

		// Assumindo que $motorista já tenha os valores definidos
		// Construir o botão com o código JavaScript embutido
		$botaoConsLog = 
			"<button class='btn default' type='button' onclick='consultarLogistica()'>Consultar Logística</button>
			<script>
			function consultarLogistica() {
				// Obter valores do PHP e HTML
				var matricula = '{$motorista["enti_tx_matricula"]}';
				var motorista = '{$motorista["enti_tx_nome"]}';
				var data = document.getElementById('data').value;

				// Obter todos os CNPJs da variável PHP
				var cnpjs = ".json_encode($cnpjs).";

				// Verificar o conteúdo de cnpjs no console
				// console.log('CNPJs:', cnpjs);

				if (!Array.isArray(cnpjs)) {
					console.error('CNPJs não é um array:', cnpjs);
					return;
				}

				if (cnpjs.length === 0) {
					console.error('A lista de CNPJs está vazia.');
					return;
				}

				// Converte a lista de CNPJs para uma string separada por vírgulas
				var cnpjString = cnpjs.map(String).join(',');

				// Construir a URL com os parâmetros dinâmicos
				var url = '".$_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/logistica.php';
				url += '?motorista='+encodeURIComponent(motorista)+
					'&matricula='+encodeURIComponent(matricula)+
					'&data='+encodeURIComponent(data) +
					'&cnpj='+encodeURIComponent(cnpjString);  // Adicionando todos os CNPJs

				// Abrir a nova página em uma nova aba
				window.open(url, '_blank');
			}
			</script>"
		;

		$botaoLocEventos = 
			"<button class='btn default' type='button' onclick='abrirLocalizacoesEventos()'>Localização Eventos</button>
			<link rel='stylesheet' href='https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'>
			<link rel='stylesheet' href='https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css'>
			<link rel='stylesheet' href='https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css'>
			<script src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'></script>
			<script src='https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js'></script>
			<script>window.pois = {$__poisJson}; window.poiTipos = {$__tiposPoiJson};</script>
			<div id='mapModal' style='display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:10000; align-items:center; justify-content:center;'>
				<div style='background:#fff; width:90%; height:80%; border-radius:8px; position:relative; padding:8px; display:flex; flex-direction:column;'>
					<div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:6px'>
						<div style='font-weight:bold'>Localização de eventos</div>
						<button type='button' id='closeMap' class='btn btn-default'>Fechar</button>
					</div>
					<div id='leafletMap' style='width:100%; height:100%; border:1px solid #eee; border-radius:6px'></div>
				</div>
			</div>
			<script>
			var __ajusteMap=null;
			var __ajusteCluster=null;
			var __ajusteMarkers=[];
			var __ajusteLinkLayer=null;
			var __ajusteLegendDiv=null;
			var __ajusteSelectedIdx=-1;
			function __ajusteHighlightLegendItem(idx){
				try{
					if(!__ajusteLegendDiv) return;
					var items=__ajusteLegendDiv.querySelectorAll('[data-idx]');
					for(var i=0;i<items.length;i++){
						items[i].style.background='';
						items[i].style.border='';
					}
					var el=__ajusteLegendDiv.querySelector('[data-idx=\"'+idx+'\"]');
					if(el){
						el.style.background='#d4edda';
						el.style.border='1px solid #28a745';
						__ajusteSelectedIdx=idx;
					}
				}catch(e){}
			}
			function __ajusteComputeDistanceMeters(lat1,lng1,lat2,lng2){
				var R=6371000;
				var toRad=function(v){ return v*Math.PI/180; };
				var dLat=toRad(lat2-lat1);
				var dLng=toRad(lng2-lng1);
				var a=Math.sin(dLat/2)*Math.sin(dLat/2)+Math.cos(toRad(lat1))*Math.cos(toRad(lat2))*Math.sin(dLng/2)*Math.sin(dLng/2);
				var c=2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));
				return R*c;
			}
			function __ajusteIsStart(tipoUpper){
				return (tipoUpper.indexOf('INICIO')>=0);
			}
			function __ajusteBaseKey(tipoUpper){
				var key=tipoUpper.replace('INICIO DE ','').replace('FIM DE ','').trim();
				return key;
			}
			function __ajusteFindPairIndex(coords, idx){
				var c=coords[idx];
				var tUpper=(c.tipo||'').toUpperCase();
				var base=__ajusteBaseKey(tUpper);
				var isStart=__ajusteIsStart(tUpper);
				if(!base) return -1;
				if(isStart){
					for(var i=idx+1;i<coords.length;i++){
						var ti=(coords[i].tipo||'').toUpperCase();
						if(ti.indexOf('FIM')>=0 && __ajusteBaseKey(ti)===base){ return i; }
					}
				}else{
					for(var j=idx-1;j>=0;j--){
						var tj=(coords[j].tipo||'').toUpperCase();
						if(tj.indexOf('INICIO')>=0 && __ajusteBaseKey(tj)===base){ return j; }
					}
				}
				return -1;
			}
			function __ajusteShowPair(coords, idx){
				if(!__ajusteMap) return;
				var pairIdx=__ajusteFindPairIndex(coords, idx);
				if(pairIdx<0) return;
				var a=coords[idx];
				var b=coords[pairIdx];
				var start=a, end=b;
				var aIsStart=__ajusteIsStart((a.tipo||'').toUpperCase());
				if(!aIsStart){ start=b; end=a; }
				if(__ajusteLinkLayer){ __ajusteMap.removeLayer(__ajusteLinkLayer); __ajusteLinkLayer=null; }
				var dist=__ajusteComputeDistanceMeters(start.lat,start.lng,end.lat,end.lng);
				var severityColor='#FFA500';
				var base=__ajusteBaseKey((a.tipo||'').toUpperCase());
				if(dist>500){
					var bUpper=base.toUpperCase();
					if(bUpper.indexOf('ESPERA')>=0 || bUpper.indexOf('DESCANSO')>=0){ severityColor='#FF0000'; }
					else if(bUpper.indexOf('REFEI')>=0){ severityColor='#FF7F00'; }
				}
				__ajusteLinkLayer=L.polyline([[start.lat,start.lng],[end.lat,end.lng]],{color:severityColor,weight:4,dashArray:'6 6'}).addTo(__ajusteMap);
				var midLat=(start.lat+end.lat)/2;
				var midLng=(start.lng+end.lng)/2;
				var warnText='';
				if(dist>500){
					var bUpper2=base.toUpperCase();
					var nivel=(bUpper2.indexOf('ESPERA')>=0 || bUpper2.indexOf('DESCANSO')>=0)? 'Warning Grave' : 'Warning';
					warnText='<div style=\"color:'+severityColor+'; font-weight:bold\">'+nivel+' - Houve deslocamento maior que o esperado</div>';
				}
				var tempoTotal='';
				if(start.data && start.hora && end.data && end.hora){
					var parseDateBR=function(d,h){ var p=d.split('/'); var q=(h||'00:00:00').split(':'); return new Date(parseInt(p[2]),parseInt(p[1])-1,parseInt(p[0]),parseInt(q[0]||0),parseInt(q[1]||0),parseInt(q[2]||0)); };
					var dtIni=parseDateBR(start.data,start.hora);
					var dtFim=parseDateBR(end.data,end.hora);
					var diffMs=dtFim-dtIni;
					if(!isNaN(diffMs) && diffMs>=0){
						var th=Math.floor(diffMs/3600000);
						var tm=Math.floor((diffMs%3600000)/60000);
						var ts=Math.floor((diffMs%60000)/1000);
						tempoTotal='<div>Tempo total: '+th+'h '+tm+'min '+ts+'s</div>';
					}
				}
				var html='<div style=\"font-size:16px; line-height:1.5\">'
					+'<div style=\"font-weight:bold; font-size:17px\">'+(a.tipo||'')+'</div>'
					+'<div>Início: '+(start.data||'')+(start.hora? ' '+start.hora : '')+'</div>'
					+'<div>Fim: '+(end.data||'')+(end.hora? ' '+end.hora : '')+'</div>'
					+'<div>Distância aprox.: '+(dist/1000).toFixed(2)+' km</div>'
					+tempoTotal
					+warnText
					+'</div>';
				L.popup({maxWidth:480}).setLatLng([midLat,midLng]).setContent(html).openOn(__ajusteMap);
				__ajusteMap.fitBounds([[start.lat,start.lng],[end.lat,end.lng]],{padding:[40,40]});
			}
			function __ajusteZoomToEvent(i){
				if(!__ajusteMap || !__ajusteCluster) return;
				var m=__ajusteMarkers[i];
				if(!m) return;
				__ajusteCluster.zoomToShowLayer(m,function(){
					m.openPopup();
					__ajusteHighlightLegendItem(i);
					__ajusteShowPair(window.__ajusteCoordsRef||[], i);
				});
			}
			function abrirLocalizacoesEventos(){
				var t=document.querySelector('[id^=\"contex-grid-\"]');
				if(!t) return;
				var ths=t.querySelectorAll('thead tr th');
				var idxTipo=-1, idxLoc=-1, idxLeg=-1, idxData=-1, idxHora=-1;
				for(var i=0;i<ths.length;i++){
					var txt=(ths[i].textContent||'').trim().toUpperCase();
					if(txt==='TIPO') idxTipo=i;
					if(txt.indexOf('LOCALIZA')>=0) idxLoc=i;
					if(txt==='LEGENDA') idxLeg=i;
					if(idxData<0 && txt==='DATA') idxData=i;
					if(idxHora<0 && txt==='HORA') idxHora=i;
				}
				if(idxLoc<0) return;
				var rows=t.querySelectorAll('tbody tr');
				var coords=[];
				for(var r=0;r<rows.length;r++){
					var tds=rows[r].children;
					if(!tds || tds.length===0) continue;
					var tipo=(idxTipo>=0 && tds[idxTipo])? (tds[idxTipo].textContent||'').trim() : '';
					var legenda=(idxLeg>=0 && tds[idxLeg])? (tds[idxLeg].textContent||'').trim() : '';
					var dataRaw=(idxData>=0 && tds[idxData])? (tds[idxData].textContent||'').trim() : '';
					var mDate=dataRaw? dataRaw.match(/(\d{2}\/\d{2}\/\d{4})/) : null;
					var mTime=dataRaw? dataRaw.match(/(\d{2}:\d{2}:\d{2})/) : null;
					var dataVal=mDate? mDate[1] : '';
					var horaVal=mTime? mTime[1] : '';
					if(!horaVal && idxHora>=0 && tds[idxHora]){ horaVal=(tds[idxHora].textContent||'').trim(); }
					var a=tds[idxLoc]? tds[idxLoc].querySelector('a[href*=\"google.com/maps?q\"]') : null;
					if(!a) continue;
					var href=a.getAttribute('href')||'';
					var qIndex=href.indexOf('q=');
					if(qIndex<0) continue;
					var qStr=href.substring(qIndex+2);
					var parts=qStr.split(',');
					if(parts.length<2) continue;
					var lat=parseFloat(parts[0]);
					var lng=parseFloat(parts[1]);
					if(isNaN(lat)||isNaN(lng)) continue;
					coords.push({lat:lat,lng:lng,tipo:tipo,legenda:legenda,data:dataVal,hora:horaVal});
				}
				var modal=document.getElementById('mapModal');
				modal.style.display='flex';
				var mapDiv=document.getElementById('leafletMap');
				if(__ajusteMap){ __ajusteMap.remove(); __ajusteMap=null; }
				__ajusteMap=L.map(mapDiv).setView(coords.length? [coords[0].lat, coords[0].lng] : [-14.235,-51.925], 5);
				var baseMapa=L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19});
				var googleHybrid=L.tileLayer('https://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}',{maxZoom:20, subdomains:['mt0','mt1','mt2','mt3']});
				baseMapa.addTo(__ajusteMap);
				var __ajustePoiLayer = L.layerGroup().addTo(__ajusteMap);
				window.__ajustePoiLayer = __ajustePoiLayer;
				L.control.layers({'Mapa':baseMapa,'Satélite (Híbrido)':googleHybrid},{'📍 POIs': __ajustePoiLayer},{position:'topright'}).addTo(__ajusteMap);
				var greenIcon=new L.Icon({iconUrl:'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png',shadowUrl:'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',iconSize:[25,41],iconAnchor:[12,41],popupAnchor:[1,-34],shadowSize:[41,41]});
				var redIcon=new L.Icon({iconUrl:'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',shadowUrl:'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',iconSize:[25,41],iconAnchor:[12,41],popupAnchor:[1,-34],shadowSize:[41,41]});
				var yellowIcon=new L.Icon({iconUrl:'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-yellow.png',shadowUrl:'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',iconSize:[25,41],iconAnchor:[12,41],popupAnchor:[1,-34],shadowSize:[41,41]});
				var cluster=L.markerClusterGroup({spiderfyOnEveryZoom:true, disableClusteringAtZoom:18});
				__ajusteCluster=cluster;
				__ajusteMarkers=[];
				cluster.addTo(__ajusteMap);
				var bounds=[];
				for(var k=0;k<coords.length;k++){
					var c=coords[k];
					var icon=yellowIcon;
					var tUpper=(c.tipo||'').toUpperCase();
					if(tUpper.indexOf('INICIO DE JORNADA')>=0) icon=greenIcon;
					else if(tUpper.indexOf('FIM DE JORNADA')>=0) icon=redIcon;
					var popupHtml='<div style=\"font-size:16px; line-height:1.5\">'
						+(c.tipo? ('<div style=\"font-weight:bold; font-size:17px\">'+c.tipo+'</div>') : '')
						+(c.legenda? ('<div>'+c.legenda+'</div>') : '')
						+(c.data? ('<div>'+c.data+(c.hora? ' '+c.hora : '')+'</div>') : '')
						+'<button onclick=\"ajusteAbrirCadastroPoiPorCoordenadas('+c.lat+','+c.lng+')\" style=\"margin-top:8px;padding:6px 12px;border:none;border-radius:6px;background:#004173;color:white;cursor:pointer;font-size:13px;width:100%;\">📌 Criar POI aqui</button>'
						+'</div>';
					var m=L.marker([c.lat,c.lng],{icon:icon}).bindPopup(popupHtml,{maxWidth:420});
					(function(idx){
						m.on('click', function(){ __ajusteHighlightLegendItem(idx); __ajusteShowPair(coords, idx); });
					})(k);
					__ajusteMarkers.push(m);
					cluster.addLayer(m);
					bounds.push([c.lat,c.lng]);
				}
				if(bounds.length>0){ __ajusteMap.fitBounds(cluster.getBounds()); }

				// ---- POIs ----
				var __ajustePoisData = window.pois || [];
				var __ajustePoiMarkers = [];
				var __ajustePoiLegendItems = [];
				var __ajusteTipos = window.poiTipos || [];
				var __ajustePoiEmojiMap = {};
				__ajusteTipos.forEach(function(t){ __ajustePoiEmojiMap[t.poti_tx_codigo] = t.poti_tx_emoji; });
				if (__ajustePoisData.length > 0) {
					for(var __p=0;__p<__ajustePoisData.length;__p++){
						var __poi=__ajustePoisData[__p];
						var __poiLat=parseFloat(__poi.poi_tx_latitude);
						var __poiLng=parseFloat(__poi.poi_tx_longitude);
						if(isNaN(__poiLat)||isNaN(__poiLng)) continue;
						var __emoji=__ajustePoiEmojiMap[__poi.poi_tx_icone]||'📌';
						var __poiIcon=L.divIcon({className:'ajuste-poi-icon',html:'<div style=\"font-size:26px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;text-shadow:0 1px 3px rgba(0,0,0,.2);\">'+__emoji+'</div>',iconSize:[32,32],iconAnchor:[16,32],popupAnchor:[0,-36]});
						var __poiImg=__poi.poi_tx_imagem?'<img src=\"'+__poi.poi_tx_imagem+'\" style=\"max-width:200px;max-height:130px;border-radius:6px;margin-bottom:6px;display:block;border:1px solid #ddd;\">':'';
						var __acoesHtml=__poi.poi_tx_acoes_esperadas?'<div style=\"margin-top:8px;padding:8px;background:#e8f4fd;border-left:4px solid #004173;border-radius:4px;font-size:13px;\"><b>Ações Esperadas:</b> '+__poi.poi_tx_acoes_esperadas+'</div>':'';
						var __poiPopup='<div style=\"font-size:14px;min-width:220px\">'+
							__poiImg+
							'<strong>📌 '+__poi.poi_tx_nome+'</strong>'+
							(__poi.poi_tx_endereco?'<br><b>Endereço:</b> '+__poi.poi_tx_endereco:'')+
							(__poi.poi_tx_cep?'<br><b>CEP:</b> '+__poi.poi_tx_cep:'')+
							(__poi.poi_tx_cnpj?'<br><b>CNPJ:</b> '+__poi.poi_tx_cnpj:'')+
							(__poi.poi_tx_contato?'<br><b>Contato:</b> '+__poi.poi_tx_contato:'')+
							'<br><b>Lat:</b> '+__poi.poi_tx_latitude+
							'<br><b>Lon:</b> '+__poi.poi_tx_longitude+
							(__poi.poi_nb_raio?'<br><b>Raio:</b> '+__poi.poi_nb_raio+'m':'')+
							__acoesHtml+
							'<hr style=\"margin:6px 0; border:none; border-top:1px solid #eee;\">'+
							'<a href=\"javascript:void(0)\" onclick=\"ajusteEditarPoi('+__poi.poi_nb_id+')\" style=\"color:#004173; font-size:13px; text-decoration:none;\">✏️ Editar</a>'+
						'</div>';
						var __poiM=L.marker([__poiLat,__poiLng],{icon:__poiIcon}).bindPopup(__poiPopup).addTo(__ajustePoiLayer);
						if(__poi.poi_nb_raio>0){
							L.circle([__poiLat,__poiLng],{radius:parseInt(__poi.poi_nb_raio,10)||50,color:'#002244',fillColor:'#002244',fillOpacity:0.2,weight:2}).addTo(__ajustePoiLayer);
						}
						__ajustePoiMarkers.push(__poiM);
						__ajustePoiLegendItems.push({nome:__poi.poi_tx_nome,emoji:__emoji,raio:__poi.poi_nb_raio});
					}
				}
				window.__ajusteZoomToPoi = function(idx){
					var m=__ajustePoiMarkers[idx];
					if(!m || !__ajusteMap) return;
					__ajusteMap.setView(m.getLatLng(), 17);
					m.openPopup();
				};

				var Legend=L.Control.extend({
					onAdd:function(){
						var div=L.DomUtil.create('div','info legend');
						window.__ajusteLegendDiv=div;
						div.style.background='#fff'; div.style.padding='8px'; div.style.border='1px solid #ddd'; div.style.borderRadius='6px'; div.style.fontSize='15px';
						var html='<div style=\"font-weight:bold; margin-bottom:4px; font-size:18px;cursor:pointer;\" data-leg-toggle=\"leg-marcadores\">Legenda <span class=\"leg-toggle\" style=\"font-size:14px;float:right\">▼</span></div>'
							+'<div id=\"leg-marcadores\">'
							+'<div style=\"display:flex; align-items:center; gap:6px; font-size:15px\"><img src=\"https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png\" style=\"width:16px;height:26px\"> <span>Inicio de Jornada</span></div>'
							+'<div style=\"display:flex; align-items:center; gap:6px; font-size:15px\"><img src=\"https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png\" style=\"width:16px;height:26px\"> <span>Fim de Jornada</span></div>'
							+'<div style=\"display:flex; align-items:center; gap:6px; font-size:15px\"><img src=\"https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-yellow.png\" style=\"width:16px;height:26px\"> <span>Eventos</span></div>'
							+'</div>'
							+'<div style=\"margin-top:4px; font-weight:bold; font-size:16px;cursor:pointer;\" data-leg-toggle=\"leg-eventos-list\">Eventos plotados <span class=\"leg-toggle-ev\" style=\"font-size:12px;float:right\">▼</span></div>';
						html+= '<div id=\"leg-eventos-list\" style=\"max-height:160px; overflow:auto; margin-top:4px\">';
						for(var i=0;i<coords.length;i++){

							var c=coords[i];
							var tUpper=(c.tipo||'').toUpperCase();
							var iconSrc='https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-yellow.png';
							if(tUpper.indexOf('INICIO DE JORNADA')>=0) iconSrc='https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png';
							else if(tUpper.indexOf('FIM DE JORNADA')>=0) iconSrc='https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png';
							var dtTxt=(c.data? c.data : '');
							if(c.hora){ dtTxt = dtTxt? (dtTxt+' '+c.hora) : c.hora; }
							var line='<div data-idx=\"'+i+'\" id=\"ajuste-legend-item-'+i+'\" style=\"display:flex; align-items:center; gap:6px; font-size:15px; cursor:pointer; border-radius:4px; padding:4px\" onclick=\"__ajusteZoomToEvent('+i+')\"><img src=\"'+iconSrc+'\" style=\"width:12px;height:20px\"> <span>'+ (dtTxt? (dtTxt+' - ') : '') + (c.tipo||'') +'</span></div>';
							html+= line;
						}
						html+='</div>';
						// ---- POIs na legenda ----
						if(__ajustePoiLegendItems.length>0){
							html+='<div style=\"margin-top:4px; font-weight:bold; font-size:16px;cursor:pointer;\" data-leg-toggle=\"leg-poi-list\">📍 POIs <span class=\"leg-toggle-poi\" style=\"font-size:12px;float:right\">▼</span></div>';
							html+='<div id=\"leg-poi-list\" style=\"max-height:160px; overflow-y:auto; margin-top:4px\">';
							for(var __pl=0;__pl<__ajustePoiLegendItems.length;__pl++){
								var __pli=__ajustePoiLegendItems[__pl];
								var __plRaio=__pli.raio?' ('+__pli.raio+'m)':'';
								html+='<div style=\"display:flex; align-items:center; gap:6px; font-size:15px; cursor:pointer; border-radius:4px; padding:4px\" onclick=\"__ajusteZoomToPoi('+__pl+')\"><span style=\"font-size:18px\">'+__pli.emoji+'</span> <span>'+__pli.nome+__plRaio+'</span></div>';
							}
							html+='</div>';
						}
						div.innerHTML=html;
						div.addEventListener(\"click\",function(ev){
							var t=ev.target.closest(\"[data-leg-toggle]\");
							if(!t) return;
							var targetId=t.getAttribute(\"data-leg-toggle\");
							var target=document.getElementById(targetId);
							if(!target) return;
							var isHidden=target.style.display===\"none\";
							target.style.display=isHidden?\"\":\"none\";
							var arrow=t.querySelector(\"span[class^=leg-toggle]\");
							if(arrow) arrow.textContent=isHidden?\"▼\":\"▶\";
						});
						return div;
					}
				});
				__ajusteMap.addControl(new Legend({position:'bottomright'}));
				window.__ajusteCoordsRef=coords;
				var btn=document.getElementById('closeMap');
				btn.onclick=function(){ modal.style.display='none'; if(__ajusteMap){ __ajusteMap.remove(); __ajusteMap=null; } __ajusteLegendDiv=null; __ajusteSelectedIdx=-1; };

				// ---- Controles e eventos de POI no mapa de eventos ----
				ajusteConfigurarEventosPoiMapa();
			}
			</script>"
		;

		echo <<<'AJUSTEPOIJS'
		<script>
		// ===== FUNÇÕES DE CADASTRO/EDIÇÃO DE POI =====
		var _ajusteContextLat = null;
		var _ajusteContextLng = null;
		var _ajusteCirclePreview = null;
		var _ajusteAddPoiMode = false;
		var _ajusteAddPoiClickHandler = null;

		function ajusteMontarIconePoi(icone) {
			var dynamicTipos = window.poiTipos || [];
			var eMap = {};
			dynamicTipos.forEach(function(t){ eMap[t.poti_tx_codigo] = t.poti_tx_emoji; });
			var emoji = eMap[icone] || '📌';
			return L.divIcon({
				className: 'ajuste-poi-custom-icon',
				html: '<div style="font-size:26px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;text-shadow:0 1px 3px rgba(0,0,0,.2);">'+emoji+'</div>',
				iconSize: [32, 32],
				iconAnchor: [16, 32],
				popupAnchor: [0, -36]
			});
		}

		function ajusteAbrirContextMenu(e) {
			e.originalEvent.preventDefault();
			_ajusteContextLat = e.latlng.lat;
			_ajusteContextLng = e.latlng.lng;
			var menu = document.getElementById("ajusteMapContextMenu");
			menu.style.display = "block";
			menu.style.left = (e.originalEvent.clientX - 90) + "px";
			menu.style.top = (e.originalEvent.clientY - 10) + "px";
		}

		function ajusteConfigurarEventosPoiMapa(){
			if(!__ajusteMap) return;
			__ajusteMap.on("contextmenu", ajusteAbrirContextMenu);

			// Botão "Cadastrar POI" no mapa
			var poiBtnControl = L.control({ position: 'topleft' });
			poiBtnControl.onAdd = function() {
				var div = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
				div.innerHTML = '<a href="javascript:void(0)" id="ajusteAddPoiBtnMap" title="Clique para ativar modo de cadastro de POI. Depois clique no mapa para escolher o local." style="display:flex; flex-direction:column; align-items:center; justify-content:center; width:50px; height:44px; background:white; border-radius:4px 4px 0 0; box-shadow:0 1px 5px rgba(0,0,0,.3); cursor:pointer; text-decoration:none; color:#333; font-size:18px; line-height:1.2;"><span>📌</span><span style="font-size:9px; font-weight:600; text-transform:uppercase;">POI</span></a>';
				return div;
			};
			poiBtnControl.addTo(__ajusteMap);
			document.getElementById("ajusteAddPoiBtnMap").addEventListener("click", function(e){
				e.preventDefault();
				ajusteToggleAddPoiMode();
			});
			// Botão olho para mostrar/esconder POIs
			var poiEyeControl = L.control({ position: 'topleft' });
			poiEyeControl.onAdd = function() {
				var div = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
				div.innerHTML = '<a href="javascript:void(0)" id="ajustePoiEyeBtn" title="Mostrar/Esconder POIs" style="display:flex;flex-direction:column;align-items:center;justify-content:center;width:50px;height:34px;background:white;border-radius:0 0 4px 4px;box-shadow:0 1px 5px rgba(0,0,0,.3);cursor:pointer;text-decoration:none;color:#333;font-size:20px;line-height:1;border-top:1px solid #eee;">👁️</a>';
				return div;
			};
			poiEyeControl.addTo(__ajusteMap);
			var _ajustePoiVisible = true;
			document.getElementById("ajustePoiEyeBtn").addEventListener("click", function(e){
				e.preventDefault();
				_ajustePoiVisible = !_ajustePoiVisible;
				if (_ajustePoiVisible) {
					__ajusteMap.addLayer(window.__ajustePoiLayer);
					this.style.opacity = '1';
				} else {
					__ajusteMap.removeLayer(window.__ajustePoiLayer);
					this.style.opacity = '0.4';
				}
			});
		}

		window.ajusteAbrirCadastroPoi = function(poiData) {
			document.getElementById("ajusteMapContextMenu").style.display = "none";
			if (poiData) {
				document.getElementById("ajuste_poi_id").value = poiData.poi_nb_id || 0;
				document.getElementById("ajuste_poi_latitude").value = poiData.poi_tx_latitude;
				document.getElementById("ajuste_poi_longitude").value = poiData.poi_tx_longitude;
				document.getElementById("ajuste_poi_lat_display").value = poiData.poi_tx_latitude;
				document.getElementById("ajuste_poi_lon_display").value = poiData.poi_tx_longitude;
				document.getElementById("ajuste_poi_nome").value = poiData.poi_tx_nome || "";
				document.getElementById("ajuste_poi_endereco").value = poiData.poi_tx_endereco || "";
				document.getElementById("ajuste_poi_cep").value = poiData.poi_tx_cep || "";
				document.getElementById("ajuste_poi_cnpj").value = poiData.poi_tx_cnpj || "";
				document.getElementById("ajuste_poi_contato").value = poiData.poi_tx_contato || "";
				var raio = poiData.poi_nb_raio || 50;
				document.getElementById("ajuste_poi_raio").value = raio;
				document.getElementById("ajuste_poi_raio_range").value = raio;
				document.getElementById("ajuste_poi_icone").value = poiData.poi_tx_icone || "";
				document.getElementById("ajuste_poi_imagem").value = "";
				var preview = document.getElementById("ajuste_poi_imagem_preview");
				preview.innerHTML = poiData.poi_tx_imagem ? '<img src="' + poiData.poi_tx_imagem + '?v=' + Date.now() + '" style="max-width:180px; max-height:100px; border-radius:4px; border:1px solid #ddd;">' : '';
				var acoesSelect = document.getElementById("ajuste_poi_acoes_esperadas");
				var acoesStr = poiData.poi_tx_acoes_esperadas || "";
				var acoesArr = acoesStr.split(", ").map(function(s){ return s.trim(); }).filter(function(s){ return s; });
				for (var i = 0; i < acoesSelect.options.length; i++) {
					acoesSelect.options[i].selected = acoesArr.indexOf(acoesSelect.options[i].value) > -1;
				}
				document.getElementById("ajustePoiSidebar").style.display = "block";
				ajusteAtualizarPreviewRaio();
				return;
			}
			ajusteAbrirCadastroPoiPorCoordenadas(_ajusteContextLat, _ajusteContextLng);
		};

		window.ajusteAbrirCadastroPoiPorCoordenadas = function(lat, lng) {
			document.getElementById("ajuste_poi_id").value = 0;
			document.getElementById("ajuste_poi_latitude").value = lat;
			document.getElementById("ajuste_poi_longitude").value = lng;
			document.getElementById("ajuste_poi_lat_display").value = (typeof lat === 'number' ? lat : parseFloat(lat)).toFixed(6);
			document.getElementById("ajuste_poi_lon_display").value = (typeof lng === 'number' ? lng : parseFloat(lng)).toFixed(6);
			document.getElementById("ajuste_poi_nome").value = "";
			document.getElementById("ajuste_poi_endereco").value = "";
			document.getElementById("ajuste_poi_cep").value = "";
			document.getElementById("ajuste_poi_cnpj").value = "";
			document.getElementById("ajuste_poi_contato").value = "";
			document.getElementById("ajuste_poi_raio").value = "50";
			document.getElementById("ajuste_poi_raio_range").value = "50";
			document.getElementById("ajuste_poi_icone").value = "";
			document.getElementById("ajuste_poi_imagem").value = "";
			document.getElementById("ajuste_poi_imagem_preview").innerHTML = "";
			var selAcoes = document.getElementById("ajuste_poi_acoes_esperadas");
			if(selAcoes){ for(var i=0;i<selAcoes.options.length;i++){ selAcoes.options[i].selected = false; } }
			document.getElementById("ajustePoiSidebar").style.display = "block";
			ajusteAtualizarPreviewRaio();
			ajusteReverseGeocodeLog(lat, lng);
		};

		window.ajusteEditarPoi = function(poiId) {
			var poisData = window.pois || [];
			var encontrado = null;
			for (var i = 0; i < poisData.length; i++) {
				if (poisData[i].poi_nb_id == poiId) {
					encontrado = poisData[i];
					break;
				}
			}
			if (encontrado) {
				ajusteAbrirCadastroPoi(encontrado);
			} else {
				alert("POI não encontrado para edição.");
			}
		};

		window.ajusteFecharModalPoi = function() {
			document.getElementById("ajustePoiSidebar").style.display = "none";
			ajusteRemoverPreviewRaio();
		};

		// Mascara CNPJ/CPF
		function ajusteMascararCnpjCpf(input) {
			var v = input.value.replace(/\D/g, '');
			if (v.length <= 11) {
				v = v.replace(/^(\d{3})(\d)/, '$1.$2');
				v = v.replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3');
				v = v.replace(/\.(\d{3})(\d)/, '.$1-$2');
			} else {
				v = v.replace(/^(\d{2})(\d)/, '$1.$2');
				v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
				v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
				v = v.replace(/(\d{4})(\d)/, '$1-$2');
			}
			input.value = v;
		}
		document.addEventListener("input", function(e) {
			if (e.target && e.target.id === "ajuste_poi_cnpj") {
				ajusteMascararCnpjCpf(e.target);
			}
		});

		function ajusteAtualizarPreviewRaio() {
			if (!__ajusteMap) return;
			var lat = parseFloat(document.getElementById("ajuste_poi_latitude").value);
			var lng = parseFloat(document.getElementById("ajuste_poi_longitude").value);
			if (isNaN(lat) || isNaN(lng)) return;
			var raio = parseInt(document.getElementById("ajuste_poi_raio").value, 10) || 50;
			ajusteRemoverPreviewRaio();
			_ajusteCirclePreview = L.circle([lat, lng], {
				radius: raio,
				color: '#c0392b',
				fillColor: '#c0392b',
				fillOpacity: 0.15,
				weight: 3,
				dashArray: '8,6'
			}).addTo(__ajusteMap);
		}

		function ajusteRemoverPreviewRaio() {
			if (_ajusteCirclePreview && __ajusteMap) {
				__ajusteMap.removeLayer(_ajusteCirclePreview);
				_ajusteCirclePreview = null;
			}
		}

		document.addEventListener("input", function(e) {
			if (e.target.id === "ajuste_poi_raio_range") {
				document.getElementById("ajuste_poi_raio").value = e.target.value;
				ajusteAtualizarPreviewRaio();
			} else if (e.target.id === "ajuste_poi_raio") {
				document.getElementById("ajuste_poi_raio_range").value = e.target.value;
				ajusteAtualizarPreviewRaio();
			}
		});

		document.addEventListener('click', function(e) {
			var menu = document.getElementById("ajusteMapContextMenu");
			if (menu && !menu.contains(e.target)) {
				menu.style.display = "none";
			}
		});

		function ajusteReverseGeocodeLog(lat, lng) {
			var ufMap = {
				'acre':'AC','alagoas':'AL','amapá':'AP','amazonas':'AM','bahia':'BA','ceará':'CE',
				'distrito federal':'DF','espírito santo':'ES','goiás':'GO','maranhão':'MA',
				'mato grosso':'MT','mato grosso do sul':'MS','minas gerais':'MG','pará':'PA',
				'paraíba':'PB','paraná':'PR','pernambuco':'PE','piauí':'PI','rio de janeiro':'RJ',
				'rio grande do norte':'RN','rio grande do sul':'RS','rondônia':'RO','roraima':'RR',
				'santa catarina':'SC','são paulo':'SP','sergipe':'SE','tocantins':'TO'
			};
			function preencherCep(cep){
				var cepLimpo = cep.replace(/\D/g, '');
				if(cepLimpo.length === 8){ document.getElementById("ajuste_poi_cep").value = cepLimpo; }
			}
			function buscarCepViaCep(addr){
				if(!addr) return;
				var uf = ufMap[(addr.state || '').toLowerCase().trim()];
				var cidade = (addr.city || addr.town || addr.village || '').toLowerCase().trim();
				var logradouro = (addr.road || addr.pedestrian || addr.footway || addr.highway || addr.street || '').toLowerCase().trim();
				if(!uf || !cidade || !logradouro) return;
				var viaUrl = 'https://viacep.com.br/ws/' + encodeURIComponent(uf) + '/' + encodeURIComponent(cidade) + '/' + encodeURIComponent(logradouro) + '/json/';
				fetch(viaUrl)
					.then(function(r){ return r.json(); })
					.then(function(viaData){
						if(!viaData || viaData.erro) return;
						if(Array.isArray(viaData) && viaData.length > 0 && viaData[0].cep){
							preencherCep(viaData[0].cep);
						}else if(viaData.cep){
							preencherCep(viaData.cep);
						}
					})
					.catch(function(){});
			}
			var url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + lat + '&lon=' + lng + '&accept-language=pt&countrycodes=br&addressdetails=1';
			fetch(url, { headers: { 'User-Agent': 'TechPS-POI/1.0' } })
				.then(function(r){ return r.json(); })
				.then(function(data){
					var encontrouCep = false;
					if(data && data.address){
						var addr = data.address;
						var parts = [];
						if(addr.road){ parts.push(addr.road); }
						if(addr.suburb || addr.neighbourhood){ parts.push(addr.suburb || addr.neighbourhood); }
						if(addr.city || addr.town || addr.village){ parts.push(addr.city || addr.town || addr.village); }
						if(addr.state){ parts.push(addr.state); }
						var endVal = parts.join(', ');
						if(endVal){ document.getElementById("ajuste_poi_endereco").value = endVal; }
						if(addr.postcode){
							preencherCep(addr.postcode);
							encontrouCep = true;
						}else if(data.display_name){
							var cepMatch = data.display_name.match(/(\d{5}-?\d{3})/);
							if(cepMatch){ preencherCep(cepMatch[1]); encontrouCep = true; }
						}
						if(!encontrouCep){ buscarCepViaCep(addr); }
					}else if(data && data.display_name){
						var cepMatch = data.display_name.match(/(\d{5}-?\d{3})/);
						if(cepMatch){ preencherCep(cepMatch[1]); }
					}
				})
				.catch(function(){});
		}

		window.ajusteEscolherPontoMapa = function() {
			ajusteFecharModalPoi();
			if(!__ajusteMap) return;
			__ajusteMap.getContainer().style.cursor = "crosshair";
			var selectHandler = function(e) {
				__ajusteMap.off("click", selectHandler);
				__ajusteMap.getContainer().style.cursor = "";
				_ajusteContextLat = e.latlng.lat;
				_ajusteContextLng = e.latlng.lng;
				document.getElementById("ajuste_poi_latitude").value = _ajusteContextLat;
				document.getElementById("ajuste_poi_longitude").value = _ajusteContextLng;
				document.getElementById("ajuste_poi_lat_display").value = _ajusteContextLat.toFixed(6);
				document.getElementById("ajuste_poi_lon_display").value = _ajusteContextLng.toFixed(6);
				document.getElementById("ajustePoiSidebar").style.display = "block";
				ajusteReverseGeocodeLog(_ajusteContextLat, _ajusteContextLng);
				setTimeout(ajusteAtualizarPreviewRaio, 100);
			};
			__ajusteMap.on("click", selectHandler);
		};

		window.ajusteSalvarPoi = function(event) {
			event.preventDefault();
			var form = document.getElementById("ajustePoiForm");
			var dados = new FormData(form);
			dados.append("acao", "salvar_poi");

			fetch(window.location.href, {
				method: "POST",
				body: dados
			})
			.then(function(r) {
				if (!r.ok) throw new Error("HTTP " + r.status);
				return r.text();
			})
			.then(function(text) {
				var resp;
				try { resp = JSON.parse(text); }
				catch(e) { throw new Error("Resposta inválida do servidor: " + text.substring(0, 200)); }
				if (resp.sucesso) {
					ajusteFecharModalPoi();
					var editId = parseInt(document.getElementById("ajuste_poi_id").value, 10);
					var novosPois = window.pois || [];
					resp.poi.poi_nb_id = resp.id;
					if (editId > 0) {
						for (var i = 0; i < novosPois.length; i++) {
							if (novosPois[i].poi_nb_id == editId) {
								novosPois[i] = resp.poi;
								break;
							}
						}
						alert("POI atualizado com sucesso!");
					} else {
						novosPois.push(resp.poi);
						alert("POI cadastrado com sucesso!");
					}
					window.pois = novosPois;
					// Recarrega o mapa de eventos se estiver aberto
					if(document.getElementById('mapModal').style.display === 'flex'){
						abrirLocalizacoesEventos();
					}
					// Recarrega a página para refletir POIs na grade
					setTimeout(function(){ location.reload(); }, 600);
				} else {
					alert("Erro: " + (resp.erro || "Erro ao salvar POI"));
				}
			})
			.catch(function(err) {
				alert("Erro ao salvar POI: " + err.message);
			});
			return false;
		};

		window.ajusteToggleAddPoiMode = function() {
			if(!__ajusteMap) return;
			_ajusteAddPoiMode = !_ajusteAddPoiMode;
			var btn = document.getElementById("ajusteAddPoiBtnMap");
			if (_ajusteAddPoiMode) {
				btn.style.backgroundColor = "#004173";
				btn.style.color = "white";
				__ajusteMap.getContainer().style.cursor = "crosshair";
				_ajusteAddPoiClickHandler = function(e) {
					__ajusteMap.off("click", _ajusteAddPoiClickHandler);
					_ajusteAddPoiClickHandler = null;
					_ajusteAddPoiMode = false;
					__ajusteMap.getContainer().style.cursor = "";
					if (btn) {
						btn.style.backgroundColor = "";
						btn.style.color = "";
					}
					_ajusteContextLat = e.latlng.lat;
					_ajusteContextLng = e.latlng.lng;
					ajusteAbrirCadastroPoi();
				};
				__ajusteMap.on("click", _ajusteAddPoiClickHandler);
			} else {
				__ajusteMap.getContainer().style.cursor = "";
				btn.style.backgroundColor = "";
				btn.style.color = "";
				if (_ajusteAddPoiClickHandler) {
					__ajusteMap.off("click", _ajusteAddPoiClickHandler);
					_ajusteAddPoiClickHandler = null;
				}
			}
		};

		// ---- Modal novo tipo de POI ----
		var _ajusteEmojiSelecionado = '📦';
		window.ajusteAbrirModalNovoTipoPoi = function(){
			document.getElementById('ajusteNovoTipoNome').value = '';
			_ajusteEmojiSelecionado = '📦';
			document.querySelectorAll('#ajusteGradeEmojis button').forEach(function(b){ b.classList.remove('selecionado'); });
			var def = document.querySelector('#ajusteGradeEmojis button[data-emoji="📦"]');
			if(def) def.classList.add('selecionado');
			document.getElementById('ajusteEmojiSelecionado').textContent = '📦';
			document.getElementById('ajusteNovoTipoStatus').innerHTML = '';
			document.getElementById('ajusteModalNovoTipoPoi').style.display = 'flex';
			setTimeout(function(){ document.getElementById('ajusteNovoTipoNome').focus(); }, 100);
		};
		window.ajusteFecharModalNovoTipoPoi = function(){
			document.getElementById('ajusteModalNovoTipoPoi').style.display = 'none';
		};
		document.addEventListener('DOMContentLoaded', function(){
			document.getElementById('ajusteGradeEmojis').addEventListener('click', function(e){
				var btn = e.target.closest('button');
				if(!btn) return;
				document.querySelectorAll('#ajusteGradeEmojis button').forEach(function(b){ b.classList.remove('selecionado'); });
				btn.classList.add('selecionado');
				_ajusteEmojiSelecionado = btn.getAttribute('data-emoji') || '📌';
				document.getElementById('ajusteEmojiSelecionado').textContent = _ajusteEmojiSelecionado;
			});
			var sel = document.getElementById('ajuste_poi_icone');
			if(sel){
				sel.addEventListener('change', function(){
					if(this.value === '__novo__'){
						this.value = '';
						ajusteAbrirModalNovoTipoPoi();
					}
				});
			}
		});
		window.ajusteSalvarNovoTipoPoi = function(){
			var nome = document.getElementById('ajusteNovoTipoNome').value.trim();
			var emoji = _ajusteEmojiSelecionado;
			var statusEl = document.getElementById('ajusteNovoTipoStatus');
			if(!nome){
				statusEl.innerHTML = '<span style="color:red;">Informe o nome do tipo.</span>';
				document.getElementById('ajusteNovoTipoNome').focus();
				return;
			}
			var codigo = nome;
			statusEl.innerHTML = '<span style="color:#666;">Salvando...</span>';
			var formData = new FormData();
			formData.append('ajax_action', 'criar_tipo_poi');
			formData.append('codigo', codigo);
			formData.append('nome', nome);
			formData.append('emoji', emoji);
			fetch(window.basePath + '/ajax_poi_tipo.php', { method: 'POST', body: formData })
				.then(function(r){ return r.json(); })
				.then(function(data){
					if(data.sucesso){
						statusEl.innerHTML = '<span style="color:green;">Tipo criado com sucesso!</span>';
						var sel = document.getElementById('ajuste_poi_icone');
						var opt = document.createElement('option');
						opt.value = data.tipo.poti_tx_codigo;
						opt.textContent = data.tipo.poti_tx_emoji + ' ' + data.tipo.poti_tx_nome;
						opt.setAttribute('data-emoji', data.tipo.poti_tx_emoji);
						var novoItem = sel.querySelector('option[value="__novo__"]');
						sel.insertBefore(opt, novoItem);
						sel.value = data.tipo.poti_tx_codigo;
						// Atualiza array global de tipos
						if(window.poiTipos){ window.poiTipos.push(data.tipo); }
						setTimeout(ajusteFecharModalNovoTipoPoi, 800);
					}else{
						statusEl.innerHTML = '<span style="color:red;">' + (data.erro || 'Erro ao salvar.') + '</span>';
					}
				})
				.catch(function(err){
					statusEl.innerHTML = '<span style="color:red;">Erro na requisição.</span>';
					console.error('AJAX_ERRO', err);
				});
		};
		</script>
AJUSTEPOIJS;

		$textFields[] = texto("Matrícula", $motorista["enti_tx_matricula"], 2);
		$textFields[] = texto($motorista["enti_tx_ocupacao"], $motorista["enti_tx_nome"], 5);
		$textFields[] = texto("CPF", $motorista["enti_tx_cpf"], 3);

		$_POST["status"] = (!empty($_POST["status"]) && $_POST["status"] != "undefined"? $_POST["status"]: "ativo");

		$variableFields = [];
		$campoJust = [];

		$afastamento = mysqli_fetch_assoc(query(
			"SELECT abono.*, motivo.* FROM abono
				JOIN entidade ON abon_tx_matricula = enti_tx_matricula
				JOIN motivo ON abon_nb_motivo = moti_nb_id
				WHERE abon_tx_status = 'ativo' AND enti_tx_status = 'ativo' AND moti_tx_status = 'ativo'
					AND enti_nb_id = {$_POST["idMotorista"]}
					AND abon_tx_data = '{$_POST["data"]}'
					AND moti_tx_tipo = 'Afastamento'
			LIMIT 1;"
		));

		$ferias = mysqli_fetch_assoc(query(
			"SELECT * FROM ferias
				WHERE feri_tx_status = 'ativo'
					AND feri_nb_entidade = '{$motorista["enti_nb_id"]}'
					AND '".date("Y-m-d")."' BETWEEN feri_tx_dataInicio AND feri_tx_dataFim
				LIMIT 1;"
		));

		$iconeExcluir = "";
		$variableFields = [
			campo_data("Data*", "data", ($_POST["data"]?? ""), 2, "onfocusout='atualizar_form({$_POST["idMotorista"]}, this.value, \"{$_POST["status"]}\")'")
		];
		if(!empty($endosso)){
			$variableFields = array_merge($variableFields, [texto("Endosso", "Endossado por ".$endosso["endo_nb_userCadastro"]." em ".data($endosso["endo_tx_dataCadastro"], 1), 8)]);
		}elseif(!empty($afastamento)){
			$variableFields = array_merge($variableFields, [texto("Afastamento", "Afastado por motivo de {$afastamento["moti_tx_nome"]}", 8)]);
		}elseif(!empty($ferias)){
			$variableFields = array_merge($variableFields, [texto("Férias:", "Férias de ({$ferias["feri_tx_dataInicio"]} a {$ferias["feri_tx_dataFim"]})", 6)]);
		}else{
			$botoes[] = botao("Gravar", "cadastrarAjuste");

			//Precisa ser uma função para que o server-side chame e substitua os nomes dos campos pelos valores
			$iconeExcluir = "pont_nb_id";
			
			
			$variableFields = array_merge($variableFields, [
				campo_hora("Hora*", "hora", ($_POST["hora"]?? ""), 2, ""),
				combo_bd("Tipo de Registro*", "idMacro", ($_POST["idMacro"]?? ""), 4, "macroponto", "", "ORDER BY macr_nb_id"),
				combo_bd("Motivo*", "motivo", ($_POST["motivo"]?? ""), 4, "motivo", "", " AND moti_tx_tipo = 'Ajuste' ORDER BY moti_tx_nome")
			]);
			$campoJust[] = textarea("Justificativa", "justificativa", ($_POST["justificativa"]?? ""), 12, 'maxlength=680');
		}

		$botoes[] = $botao_imprimir;
		$botoes[] = criarBotaoVoltar("espelho_ponto.php");
		$botoes[] = $botaoConsLog; //BOTÃO CONSULTAR LOGISTICA
		$botoes[] = $botaoLocEventos;
		$botoes[] = status();

		
		echo abre_form("Dados do Ajuste de Ponto");
		echo linha_form($textFields);
		
		echo campo_hidden("idMotorista", $_POST["idMotorista"]);
		//Campos para retornar para a pesquisa do espelho de ponto ou após um registro de ponto{
			echo campo_hidden("busca_empresa", 		empty($_POST["busca_empresa"])? "": $_POST["busca_empresa"]);
			echo campo_hidden("busca_motorista", 	$_POST["idMotorista"]);
			echo campo_hidden("busca_data", 		$_POST["data"]);
			echo campo_hidden("busca_periodo[]",	$_POST["busca_periodo"][0]);
			echo campo_hidden("busca_periodo[]",	$_POST["busca_periodo"][1]);
		//}
		
		echo linha_form($variableFields);
		echo linha_form($campoJust);
		echo fecha_form($botoes);

		$iconeExcluir = criarSQLIconeTabela("pont_nb_id", "excluirPonto", "Excluir", "glyphicon glyphicon-remove", "Deseja inativar o registro?", "excluirPontoJS(',pont_nb_id,')");
		$checkboxBulk = "CONCAT('<input type=\"checkbox\" class=\"bulk-excluir\" data-id=\"', pont_nb_id, '\"/>')";


		$sql = pegarSqlDia(
			$motorista["enti_tx_matricula"], 
			new DateTime($_POST["data"]." 00:00:00"),
			[
				"pont_tx_data", 
				"endo_tx_status",
				"macr_tx_nome", 
				"moti_tx_nome", 
				"moti_tx_legenda", 
				"pont_tx_justificativa", 
				"(SELECT user_tx_nome FROM user WHERE user.user_nb_id = pont_nb_userCadastro LIMIT 1) as userCadastro", 
				"pont_nb_userCadastro",
				"pont_tx_dataCadastro", 
				"pont_tx_placa",
				"pont_tx_fotoPlaca",
				"pont_tx_latitude", 
				"pont_tx_longitude",
				"pont_tx_dataAtualiza",
				"IF(pont_tx_status = 'ativo' AND endo_tx_status IS NULL, {$checkboxBulk}, NULL) as bulkExcluir",
				"IF(pont_tx_status = 'ativo' AND endo_tx_status IS NULL, {$iconeExcluir}, NULL) as iconeExcluir"
			]
		);

		$fotoPlacaIcone = "CONCAT(
			IF(pont_tx_fotoPlaca IS NOT NULL AND pont_tx_fotoPlaca != '',
				CONCAT('<a href=\"#\" onclick=\"event.preventDefault(); document.getElementById(\\'fotoModalImage\\').src = \\'',pont_tx_fotoPlaca,'\\'; document.getElementById(\\'fotoModal\\').style.display = \\'flex\\';\" style=\"cursor:pointer;font-size:18px;\" title=\"Ver foto\">👁️</a>'),
				''
			)
		)";

		$gridFields = [
            "CÓD"										=> "pont_nb_id",
			"DATA"										=> "data(pont_tx_data,1)",
			"PLACA"									=> "pont_tx_placa",
			"FOTO"										=> $fotoPlacaIcone,
			"TIPO"										=> "destacarJornadas(macr_tx_nome)",
			"MOTIVO"									=> "moti_tx_nome",
			"LEGENDA"									=> "moti_tx_legenda",
			"JUSTIFICATIVA"								=> "pont_tx_justificativa",
			"USUÁRIO CADASTRO"							=> "userCadastro(pont_nb_userCadastro)",
			"DATA CADASTRO"								=> "data(pont_tx_dataCadastro,1)",
			"DATA EXCLUSÃO"								=> "data(pont_tx_dataAtualiza,1)",
            "LOCALIZAÇÃO"								=> "map(pont_nb_id)",
            "Excluir Vários"								=> "bulkExcluir",
            "<spam class='glyphicon glyphicon-remove'></spam>"	=> "iconeExcluir"
        ];

		grid($sql, array_keys($gridFields), array_values($gridFields), "", "12", 1, "desc", -1);

		$logoEmpresa = mysqli_fetch_assoc(query(
            "SELECT empr_tx_logo FROM empresa
                    WHERE empr_tx_status = 'ativo'
                        AND empr_tx_Ehmatriz = 'sim'
                    LIMIT 1;"
        ))["empr_tx_logo"];

		echo
			"<div id='tituloRelatorio'>
                    <img style='width: 190px; height: 40px;' src='./imagens/logo_topo_cliente.png' alt='Logo Empresa Esquerda'>
                    <img style='width: 180px; height: 80px; margin-left: 850px;' src='./$logoEmpresa' alt='Logo Empresa Direita'>
            </div>
			<form name='form_ajuste_status' action='".$_SERVER["HTTP_ORIGIN"].$CONTEX["path"]."/ajuste_ponto.php' method='post'>
			</form>
			<div class='comentario-impressao'>
				<strong>Observações:</strong>
				<div class='linha-comentario'></div>
				<div class='linha-comentario'></div>
				<div class='linha-comentario'></div>
			</div>
			<style>
				.comentario-impressao {
					display: none;
				}
				@media print {
					@page {
						size: A4 landscape;
						margin: 1cm;
					}
					
					#tituloRelatorio {
						display: flex !important;
						align-items: center;       /* Alinha verticalmente */
						justify-content: space-between; /* Espaça os elementos nas extremidades */
						gap: 1em;                  /* Espaço entre elementos, se quiser */
					}

					#tituloRelatorio h1 {
						margin: 0;
						font-size: 1.5em;          /* Ajuste o tamanho conforme necessário */
						flex-grow: 1;
						text-align: center;
					}

					#tituloRelatorio img {
						display: block;
					}
					.comentario-impressao {
						display: block;
						margin-top: 30px;
						font-size: 14px;
						color: #000;
					}

					.linha-comentario {
						border-bottom: 1px solid #000;
						margin-bottom: 20px;
						height: 30px;
						width: 100%;
					}
					body {
						margin: 1cm;
						margin-right: 0cm; /* Ajuste o valor conforme necessário para afastar do lado direito */
						transform: scale(1.0);
						transform-origin: top left;
					}
					#tituloRelatorio{
						display: flex !important;
						position: absolute;
						top: 5px;
					}
						
					form > .row
					{
						display: none;
					}
					
					form > div:nth-child(1) {
						flex-wrap: nowrap !important;
					}

					.row {
						margin: 0px 0px 0px 0px !important;
					}
					
                    [id^=\"contex-grid-\"] > thead > tr > th:nth-child(12),
                    [id^=\"contex-grid-\"] > tbody > tr > td:nth-child(12),
                    [id^=\"contex-grid-\"] > thead > tr > th:nth-child(13),
                    [id^=\"contex-grid-\"] > tbody > tr > td:nth-child(13),
                    .scroll-to-top {
                        display: none !important;
                    }

					.portlet>.portlet-body p {
						margin-top: 0 !important;
					}
					div.page-content > div > div > div > div
					{
						padding-top: 9em;
					}
					.portlet.light {
						padding: 0px 10px !important; /* Reduzindo o padding */
						font-size: 10px !important; /* Reduzindo o tamanho da fonte */
						margin-bottom: 0px !important;
					}

					.row div {
						min-width: min-content !important;
					}

					form > div:nth-child(1){
						display: flex;
						flex-wrap: wrap;
					}
					.col-sm-2,
					.col-sm-5,
					.col-sm-3 {
						width: 40% !important;
						padding-left: 0px;
					}
				}
				#tituloRelatorio{
					display: none;
				}
			</style>"
		;

		// ---- POI detection: check each point in the grid against POIs ----
		if (!empty($__pois)) {
			echo "
			<script>
			(function(){
				var _pois = window.pois || [];
				if (_pois.length === 0) return;
				function _haversineKm(lat1, lon1, lat2, lon2) {
					var R = 6371;
					var dLat = (lat2-lat1)*Math.PI/180;
					var dLon = (lon2-lon1)*Math.PI/180;
					var a = Math.sin(dLat/2)*Math.sin(dLat/2) + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)*Math.sin(dLon/2);
					return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
				}
				var tbl = document.querySelector('[id^=\"contex-grid-\"]');
				if (!tbl) return;
				var ths = tbl.querySelectorAll('thead tr th');
				var idxLoc = -1, idxJust = -1;
				for (var i = 0; i < ths.length; i++) {
					var txt = (ths[i].textContent || '').trim().toUpperCase();
					if (txt.indexOf('LOCALIZA') >= 0) idxLoc = i;
					if (txt === 'JUSTIFICATIVA') idxJust = i;
				}
				if (idxLoc < 0 || idxJust < 0) return;
				var rows = tbl.querySelectorAll('tbody tr');
				for (var r = 0; r < rows.length; r++) {
					var tds = rows[r].children;
					if (!tds || tds.length <= Math.max(idxLoc, idxJust)) continue;
					var a = tds[idxLoc] ? tds[idxLoc].querySelector('a[href*=\"google.com/maps?q\"]') : null;
					if (!a) continue;
					var href = a.getAttribute('href') || '';
					var qIdx = href.indexOf('q=');
					if (qIdx < 0) continue;
					var parts = href.substring(qIdx + 2).split(',');
					if (parts.length < 2) continue;
					var lat = parseFloat(parts[0]), lng = parseFloat(parts[1]);
					if (isNaN(lat) || isNaN(lng)) continue;
					var matches = [];
					for (var p = 0; p < _pois.length; p++) {
						var poi = _pois[p];
						var pl = parseFloat(poi.poi_tx_latitude), pn = parseFloat(poi.poi_tx_longitude);
						var pr = parseInt(poi.poi_nb_raio, 10) || 50;
						if (isNaN(pl) || isNaN(pn)) continue;
						var dist = _haversineKm(lat, lng, pl, pn) * 1000;
						if (dist <= pr) matches.push(poi.poi_tx_nome);
					}
					if (matches.length > 0) {
						var justTd = tds[idxJust];
						var existing = (justTd.textContent || '').trim();
						var addText = 'POI - ' + matches.join(', POI - ');
						justTd.textContent = existing ? existing + ' | ' + addText : addText;
					}
				}
			})();
			</script>";
		}

		echo "
		<div id='fotoModal' style='display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:99999;justify-content:center;align-items:center;' onclick='this.style.display=\"none\"'>
			<div style='position:relative;max-width:90%;max-height:90%;'>
				<button onclick='document.getElementById(\"fotoModal\").style.display=\"none\"' style='position:absolute;top:-40px;right:0;background:none;border:none;color:#fff;font-size:30px;cursor:pointer;'>&times;</button>
				<img id='fotoModalImage' src='' style='max-width:100%;max-height:80vh;border-radius:8px;box-shadow:0 0 20px rgba(0,0,0,0.5);'/>
			</div>
		</div>";

		carregarJS();

		echo "<script>window.basePath = '".($_ENV["APP_PATH"].$_ENV["CONTEX_PATH"])."';</script>";

		rodape();
	}
