<?php
	/* ============================================================
	   Nova Empresa (provisionamento automático)
	   Disponível apenas no domínio mestre (PROVISIONAMENTO_HABILITADO=1
	   no .env da empresa) e apenas para Super Administrador.

	   Pede os mesmos dados do Cadastro de Empresa/Filial, mais a pasta,
	   a sigla de login e o administrador inicial.

	   Esta tela só REGISTRA o pedido. Quem cria banco, pasta e .env é o
	   provisionador executado pelo cron (provisionador/worker.php), que
	   também grava a empresa no cadastro de empresas deste domínio mestre
	   (auditoria) e no banco da empresa nova (como matriz).

	   O acompanhamento é feito em um modal (etapas e progresso de 0 a 100%),
	   consultando o servidor sem recarregar a página.
	   ============================================================ */
	include_once "check_permission.php";
	include_once "load_env.php";
	include_once "utils/utils.php";

	function prov_tela_habilitada(): bool{
		$flag = trim(strval($_ENV["PROVISIONAMENTO_HABILITADO"] ?? ""));
		$nivel = strval($_SESSION["user_tx_nivel"] ?? "");
		return $flag === "1" && (bool)preg_match('/super\s*admin/i', $nivel);
	}

	function prov_tela_garantirTabela(){
		global $conn;
		@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS provisionamento (
			prov_nb_id INT(11) NOT NULL AUTO_INCREMENT,
			prov_tx_nome VARCHAR(255) NOT NULL,
			prov_tx_sigla VARCHAR(30) NOT NULL,
			prov_tx_pasta VARCHAR(40) NOT NULL,
			prov_tx_cnpj VARCHAR(25) NULL,
			prov_tx_email VARCHAR(255) NULL,
			prov_tx_adminNome VARCHAR(255) NOT NULL,
			prov_tx_adminLogin VARCHAR(50) NOT NULL,
			prov_tx_adminSenhaHash VARCHAR(64) NULL,
			prov_tx_status VARCHAR(20) NOT NULL DEFAULT 'pendente',
			prov_tx_etapa VARCHAR(60) NULL,
			prov_tx_log LONGTEXT NULL,
			prov_tx_criados TEXT NULL,
			prov_nb_userCadastro INT(11) NULL,
			prov_tx_dataCadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			prov_tx_dataInicio DATETIME NULL,
			prov_tx_dataFim DATETIME NULL,
			PRIMARY KEY (prov_nb_id),
			KEY idx_prov_status (prov_tx_status)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$novas = [
			"prov_tx_dados"          => "ALTER TABLE provisionamento ADD COLUMN prov_tx_dados LONGTEXT NULL",
			"prov_tx_logo"           => "ALTER TABLE provisionamento ADD COLUMN prov_tx_logo VARCHAR(255) NULL",
			"prov_nb_empresaMestre"  => "ALTER TABLE provisionamento ADD COLUMN prov_nb_empresaMestre INT(11) NULL",
			"prov_nb_progresso"      => "ALTER TABLE provisionamento ADD COLUMN prov_nb_progresso INT(3) NOT NULL DEFAULT 0"
		];
		foreach($novas as $col => $ddl){
			$r = @mysqli_query($conn, "SHOW COLUMNS FROM provisionamento LIKE '{$col}'");
			if($r && mysqli_num_rows($r) === 0){ @mysqli_query($conn, $ddl); }
		}
	}

	function prov_tela_empresasExistentes(): array{
		$empresas = []; $empresasNomes = [];
		$arq = dirname(__DIR__) . "/empresas.php";
		if(is_file($arq)){
			$postBkp = $_POST; $getBkp = $_GET;
			ob_start();
			include $arq;
			ob_end_clean();
			$_POST = $postBkp; $_GET = $getBkp;
		}
		return ["siglas" => array_map("strtoupper", array_keys($empresas)), "pastas" => array_values($empresas)];
	}

	function prov_tela_senha(int $len = 10): string{
		$alf = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789";
		$s = "";
		for($i = 0; $i < $len; $i++){ $s .= $alf[random_int(0, strlen($alf) - 1)]; }
		return $s;
	}

	function prov_tela_regimes(): array{
		return ["" => "Selecione", "Simples Nacional" => "Simples Nacional", "Lucro Presumido" => "Lucro Presumido", "Lucro Real" => "Lucro Real"];
	}

	function prov_tela_tiposAssinatura(): array{
		return ["cpf_rg" => "CPF e RG", "rubrica" => "Rubrica (desenho)", "ambos" => "CPF, RG e Rubrica"];
	}

	function prov_tela_json($data){
		while(ob_get_level() > 0){ @ob_end_clean(); }
		header("Content-Type: application/json; charset=utf-8");
		header("Cache-Control: no-store");
		echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	}

	function prov_tela_ehAjax(): bool{
		return strval($_POST["ajax"] ?? "") === "1";
	}

	/** Dados de um pedido no formato usado pela tela (lista e modal). */
	function prov_tela_pedido(array $p, bool $comLog = false): array{
		$d = json_decode(strval($p["prov_tx_dados"] ?? ""), true);
		if(!is_array($d)) $d = [];
		$st = strval($p["prov_tx_status"]);
		$log = strval($p["prov_tx_log"] ?? "");
		// Última etapa numerada que aparece no log (em caso de erro, é onde parou)
		$passo = 0;
		if(preg_match_all('/\]\s(\d)-[a-z]+:/', $log, $m) && !empty($m[1])){ $passo = intval(end($m[1])); }
		$criados = strval($p["prov_tx_criados"] ?? "");
		$temCriados = $criados !== "" && !in_array($criados, ["[]", "{}"], true);
		$out = [
			"id"            => intval($p["prov_nb_id"]),
			"nome"          => strval($p["prov_tx_nome"]),
			"sigla"         => strval($p["prov_tx_sigla"]),
			"pasta"         => strval($p["prov_tx_pasta"]),
			"adminLogin"    => strval($p["prov_tx_adminLogin"]),
			"cnpj"          => strval($p["prov_tx_cnpj"] ?? ""),
			"cidade"        => strval($d["cidadeTexto"] ?? ""),
			"status"        => $st,
			"etapa"         => strval($p["prov_tx_etapa"] ?? ""),
			"passo"         => $passo,
			"progresso"     => $st === "concluido" ? 100 : intval($p["prov_nb_progresso"] ?? 0),
			"empresaMestre" => !empty($p["prov_nb_empresaMestre"]) ? intval($p["prov_nb_empresaMestre"]) : null,
			"usuario"       => strval($p["user_tx_login"] ?? ""),
			"pedidoEm"      => !empty($p["prov_tx_dataCadastro"]) ? date("d/m/Y H:i", strtotime($p["prov_tx_dataCadastro"])) : "",
			"finalizadoEm"  => !empty($p["prov_tx_dataFim"]) ? date("d/m/Y H:i:s", strtotime($p["prov_tx_dataFim"])) : "",
			"temLog"        => $log !== "",
			"podeCancelar"  => in_array($st, ["pendente", "aguardando"], true),
			"podeDesfazer"  => in_array($st, ["concluido", "erro"], true) && $temCriados,
			"urlLogin"      => ($_ENV["URL_BASE"] ?? "") . ($_ENV["APP_PATH"] ?? "") . "/index.php?empresa=" . urlencode(strval($p["prov_tx_sigla"]))
		];
		if($comLog){ $out["log"] = $log; }
		return $out;
	}

	// ---------------------------------------------------------------- ações auxiliares (AJAX)
	function buscarCidade(){
		if(!prov_tela_habilitada()){ prov_tela_json([]); }
		$q = trim(strval($_POST["q"] ?? ""));
		$ibge = intval($_POST["ibge"] ?? 0);
		if($ibge > 0){
			$rs = query("SELECT cida_nb_id AS id, CONCAT('[', cida_tx_uf, '] ', cida_tx_nome) AS text FROM cidade WHERE cida_nb_id = ? LIMIT 1", "i", [$ibge]);
		}else{
			if(mb_strlen($q) < 2){ prov_tela_json([]); }
			$like = "%" . $q . "%";
			$rs = query("SELECT cida_nb_id AS id, CONCAT('[', cida_tx_uf, '] ', cida_tx_nome) AS text FROM cidade WHERE cida_tx_status = 'ativo' AND cida_tx_nome LIKE ? ORDER BY cida_tx_nome ASC LIMIT 25", "s", [$like]);
		}
		$out = [];
		while($rs && ($r = mysqli_fetch_assoc($rs))){ $out[] = $r; }
		prov_tela_json($out);
	}

	function checarCnpjProv(){
		if(!prov_tela_habilitada()){ prov_tela_json(["existe" => false]); }
		$cnpj = preg_replace('/[^0-9]/', '', strval($_POST["cnpj"] ?? ""));
		if(strlen($cnpj) < 11){ prov_tela_json(["existe" => false]); }
		$rs = query("SELECT empr_tx_nome FROM empresa WHERE REPLACE(REPLACE(REPLACE(empr_tx_cnpj,'.',''),'/',''),'-','') = ? LIMIT 1", "s", [$cnpj]);
		$r = $rs ? mysqli_fetch_assoc($rs) : null;
		prov_tela_json(["existe" => !empty($r), "nome" => $r["empr_tx_nome"] ?? ""]);
	}

	function listarPedidos(){
		if(!prov_tela_habilitada()){ prov_tela_json(["ok" => false, "pedidos" => []]); }
		prov_tela_garantirTabela();
		$rs = query("SELECT p.*, u.user_tx_login FROM provisionamento p LEFT JOIN user u ON u.user_nb_id = p.prov_nb_userCadastro ORDER BY p.prov_nb_id DESC LIMIT 50");
		$out = [];
		while($rs && ($r = mysqli_fetch_assoc($rs))){ $out[] = prov_tela_pedido($r, false); }
		prov_tela_json(["ok" => true, "pedidos" => $out]);
	}

	function statusPedido(){
		if(!prov_tela_habilitada()){ prov_tela_json(["ok" => false]); }
		prov_tela_garantirTabela();
		$id = intval($_POST["id"] ?? 0);
		$rs = query("SELECT p.*, u.user_tx_login FROM provisionamento p LEFT JOIN user u ON u.user_nb_id = p.prov_nb_userCadastro WHERE p.prov_nb_id = ? LIMIT 1", "i", [$id]);
		$r = $rs ? mysqli_fetch_assoc($rs) : null;
		if(!$r){ prov_tela_json(["ok" => false, "msg" => "Pedido não encontrado."]); }
		prov_tela_json(["ok" => true, "pedido" => prov_tela_pedido($r, true)]);
	}

	// ---------------------------------------------------------------- ações
	function prov_tela_responder(bool $ok, string $msg, array $extra = []){
		if(prov_tela_ehAjax()){
			prov_tela_json(array_merge(["ok" => $ok, "msg" => $msg], $extra));
		}
		set_status(($ok ? "" : "ERRO: ") . htmlspecialchars($msg));
		if(!empty($extra["campos"])){ $_POST["errorFields"] = $extra["campos"]; }
		index(); exit;
	}

	function solicitarEmpresa(){
		global $conn;
		if(!prov_tela_habilitada()){ prov_tela_responder(false, "Recurso indisponível neste domínio ou para o seu perfil."); }
		prov_tela_garantirTabela();

		$g = function(string $k, int $max = 255): string { return mb_substr(trim(strval($_POST[$k] ?? "")), 0, $max); };

		// Dados do Cadastro de Empresa
		$dados = [
			"nome"               => $g("nome", 255),
			"fantasia"           => $g("fantasia", 255),
			"cnpj"               => $g("cnpj", 25),
			"cep"                => $g("cep", 20),
			"endereco"           => $g("endereco", 255),
			"numero"             => $g("numero", 30),
			"bairro"             => $g("bairro", 255),
			"complemento"        => $g("complemento", 100),
			"referencia"         => $g("referencia", 100),
			"cidade"             => strval(intval($_POST["cidade"] ?? 0) ?: ""),
			"fone1"              => $g("fone1", 30),
			"fone2"              => $g("fone2", 30),
			"contato"            => $g("contato", 100),
			"email"              => $g("email", 255),
			"inscricaoEstadual"  => $g("inscricaoEstadual", 20),
			"inscricaoMunicipal" => $g("inscricaoMunicipal", 20),
			"regimeTributario"   => $g("regimeTributario", 50),
			"dataRegistroCNPJ"   => $g("dataRegistroCNPJ", 10),
			"tipoAssinatura"     => $g("tipoAssinatura", 10),
			"parametro"          => strval(intval($_POST["parametro"] ?? 0) ?: ""),
			"parametroNome"      => "",
			"cidadeTexto"        => ""
		];
		// Dados do provisionamento
		$sigla  = strtoupper($g("sigla", 30));
		$pasta  = strtolower($g("pasta", 40));
		$aNome  = $g("adminNome", 255);
		$aLogin = $g("adminLogin", 50);
		$aSenha = strval($_POST["adminSenha"] ?? "");

		// Mesmos campos obrigatórios do Cadastro de Empresa
		$obrig = ["cnpj" => "CNPJ", "nome" => "Nome", "cep" => "CEP", "numero" => "Número", "email" => "E-mail", "parametro" => "Parâmetro", "cidade" => "Cidade", "endereco" => "Endereço", "bairro" => "Bairro"];
		$erros = []; $camposErro = [];
		foreach($obrig as $k => $rotulo){
			if($dados[$k] === ""){ $erros[] = "{$rotulo} é obrigatório"; $camposErro[] = $k; }
		}
		$cnpjDig = preg_replace('/[^0-9]/', '', $dados["cnpj"]);
		if($dados["cnpj"] !== "" && !in_array(strlen($cnpjDig), [11, 14], true)){ $erros[] = "CPF/CNPJ inválido"; $camposErro[] = "cnpj"; }
		if($dados["email"] !== "" && !filter_var($dados["email"], FILTER_VALIDATE_EMAIL)){ $erros[] = "e-mail inválido"; $camposErro[] = "email"; }
		if($dados["dataRegistroCNPJ"] !== "" && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dados["dataRegistroCNPJ"])){ $erros[] = "data de registro do CNPJ inválida"; $camposErro[] = "dataRegistroCNPJ"; }
		if(!isset(prov_tela_regimes()[$dados["regimeTributario"]])){ $dados["regimeTributario"] = ""; }
		if(!isset(prov_tela_tiposAssinatura()[$dados["tipoAssinatura"]])){ $dados["tipoAssinatura"] = "cpf_rg"; }

		if(!preg_match('/^[A-Z0-9][A-Z0-9 _]{1,29}$/', $sigla)){ $erros[] = "sigla inválida (2 a 30 caracteres: maiúsculas, números, espaço ou _)"; $camposErro[] = "sigla"; }
		if(!preg_match('/^[a-z][a-z0-9_]{2,39}$/', $pasta)){ $erros[] = "pasta inválida (3 a 40 caracteres: minúsculas, números e _, começando por letra)"; $camposErro[] = "pasta"; }
		if(in_array($pasta, ["contex20","phpmailer","node_modules","face_models","provisionador","api","versoes","arquivos","ws","assinatura"], true)){ $erros[] = "nome de pasta reservado"; $camposErro[] = "pasta"; }
		if(!preg_match('/^[A-Za-z0-9._@-]{3,50}$/', $aLogin)){ $erros[] = "login do administrador inválido"; $camposErro[] = "adminLogin"; }
		if($aSenha !== "" && strlen($aSenha) < 6){ $erros[] = "a senha inicial precisa de ao menos 6 caracteres"; $camposErro[] = "adminSenha"; }

		if(!$erros){
			$ex = prov_tela_empresasExistentes();
			if(in_array($pasta, $ex["pastas"], true) || file_exists(dirname(__DIR__) . "/" . $pasta)){ $erros[] = "já existe uma empresa/pasta '{$pasta}'"; $camposErro[] = "pasta"; }
			if(in_array($sigla, $ex["siglas"], true)){ $erros[] = "já existe uma empresa com a sigla '{$sigla}'"; $camposErro[] = "sigla"; }
			$r = query("SELECT 1 FROM provisionamento WHERE prov_tx_status IN ('aguardando','pendente','processando') AND (prov_tx_pasta = ? OR prov_tx_sigla = ?) LIMIT 1", "ss", [$pasta, $sigla]);
			if($r && mysqli_num_rows($r) > 0){ $erros[] = "já existe um pedido em andamento para essa pasta ou sigla"; }
			$r = query("SELECT empr_tx_nome FROM empresa WHERE empr_tx_status = 'ativo' AND REPLACE(REPLACE(REPLACE(empr_tx_cnpj,'.',''),'/',''),'-','') = ? LIMIT 1", "s", [$cnpjDig]);
			if($r && ($rowC = mysqli_fetch_assoc($r))){ $erros[] = "o CNPJ já está cadastrado neste domínio para '{$rowC["empr_tx_nome"]}'"; $camposErro[] = "cnpj"; }
			$r = query("SELECT CONCAT('[', cida_tx_uf, '] ', cida_tx_nome) t FROM cidade WHERE cida_nb_id = ? LIMIT 1", "i", [intval($dados["cidade"])]);
			$rowCid = $r ? mysqli_fetch_assoc($r) : null;
			if(!$rowCid){ $erros[] = "cidade não encontrada"; $camposErro[] = "cidade"; } else { $dados["cidadeTexto"] = $rowCid["t"]; }
			$r = query("SELECT para_tx_nome FROM parametro WHERE para_nb_id = ? LIMIT 1", "i", [intval($dados["parametro"])]);
			$rowPar = $r ? mysqli_fetch_assoc($r) : null;
			if(!$rowPar){ $erros[] = "parâmetro não encontrado"; $camposErro[] = "parametro"; } else { $dados["parametroNome"] = $rowPar["para_tx_nome"]; }
		}

		// Logo (opcional), mesma regra do Cadastro de Empresa
		$logoTmp = null; $logoExt = "";
		$f = $_FILES["logo"] ?? null;
		if($f && !empty($f["name"]) && ($f["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK){
			$tipos = ["image/jpeg" => "jpg", "image/png" => "png", "image/gif" => "gif"];
			$info = @getimagesize($f["tmp_name"]);
			$mime = is_array($info) ? strval($info["mime"] ?? "") : "";
			if(!isset($tipos[$mime])){ $erros[] = "a logo deve ser uma imagem PNG, JPG ou GIF"; $camposErro[] = "logo"; }
			elseif(intval($f["size"]) > 2 * 1024 * 1024){ $erros[] = "a logo deve ter no máximo 2 MB"; $camposErro[] = "logo"; }
			else { $logoTmp = $f["tmp_name"]; $logoExt = $tipos[$mime]; }
		}

		if($erros){
			prov_tela_responder(false, implode("; ", array_unique($erros)) . ".", ["campos" => array_values(array_unique($camposErro))]);
		}

		if($aSenha === "") $aSenha = prov_tela_senha();
		if($aNome === "") $aNome = $aLogin;
		$dadosJson = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		query(
			"INSERT INTO provisionamento (prov_tx_nome, prov_tx_sigla, prov_tx_pasta, prov_tx_cnpj, prov_tx_email, prov_tx_adminNome, prov_tx_adminLogin, prov_tx_adminSenhaHash, prov_tx_dados, prov_tx_status, prov_nb_userCadastro)
			 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'aguardando', ?)",
			"sssssssssi",
			[$dados["nome"], $sigla, $pasta, $dados["cnpj"], $dados["email"], $aNome, $aLogin, md5($aSenha), $dadosJson, intval($_SESSION["user_nb_id"] ?? 0)]
		);
		$idPedido = intval(mysqli_insert_id($conn));
		if($idPedido <= 0){ prov_tela_responder(false, "Não foi possível registrar o pedido."); }

		if($logoTmp){
			$dir = __DIR__ . "/arquivos/provisionamento/{$idPedido}/";
			if(!is_dir($dir)) @mkdir($dir, 0775, true);
			if(@move_uploaded_file($logoTmp, $dir . "logo." . $logoExt)){
				query("UPDATE provisionamento SET prov_tx_logo = ? WHERE prov_nb_id = ?", "si", ["arquivos/provisionamento/{$idPedido}/logo.{$logoExt}", $idPedido]);
			}
		}
		// Só agora libera para o agendador (evita processar antes de a logo estar gravada)
		query("UPDATE provisionamento SET prov_tx_status = 'pendente' WHERE prov_nb_id = ?", "i", [$idPedido]);

		if(prov_tela_ehAjax()){
			prov_tela_json(["ok" => true, "id" => $idPedido, "adminLogin" => $aLogin, "adminSenha" => $aSenha, "msg" => "Pedido #{$idPedido} registrado."]);
		}
		$_POST = [];
		set_status("Pedido #{$idPedido} registrado. A empresa será criada em instantes. Anote a senha inicial do administrador '".htmlspecialchars($aLogin)."': <b>".htmlspecialchars($aSenha)."</b> (ela não será exibida novamente).");
		index(); exit;
	}

	function desfazerEmpresa(){
		if(!prov_tela_habilitada()){ prov_tela_responder(false, "Sem permissão."); }
		prov_tela_garantirTabela();
		$id = intval($_POST["id"] ?? 0);
		$confirmacao = strtolower(trim(strval($_POST["confirmacao"] ?? "")));
		$row = $id > 0 ? mysqli_fetch_assoc(query("SELECT * FROM provisionamento WHERE prov_nb_id = ?", "i", [$id])) : null;
		if(!$row){ prov_tela_responder(false, "Pedido não encontrado."); }
		if(in_array($row["prov_tx_status"], ["pendente", "aguardando"], true)){
			query("UPDATE provisionamento SET prov_tx_status = 'cancelado', prov_tx_dataFim = NOW() WHERE prov_nb_id = ?", "i", [$id]);
			prov_tela_responder(true, "Pedido cancelado antes de ser executado.", ["id" => $id, "acompanhar" => false]);
		}elseif(in_array($row["prov_tx_status"], ["concluido", "erro"], true)){
			if($confirmacao !== $row["prov_tx_pasta"]){
				prov_tela_responder(false, "Confirmação incorreta. Digite o nome da pasta ({$row["prov_tx_pasta"]}) para desfazer.");
			}
			query("UPDATE provisionamento SET prov_tx_status = 'desfazer', prov_nb_progresso = 0 WHERE prov_nb_id = ?", "i", [$id]);
			prov_tela_responder(true, "Remoção solicitada.", ["id" => $id, "acompanhar" => true]);
		}
		prov_tela_responder(false, "Este pedido está '{$row["prov_tx_status"]}' e não pode ser desfeito agora.");
	}

	// ---------------------------------------------------------------- tela
	function index(){
		global $conn;
		if(!prov_tela_habilitada()){
			cabecalho("Nova Empresa");
			echo "<div class='alert alert-warning'>Recurso disponível apenas no domínio mestre e para Super Administrador.</div>";
			rodape();
			return;
		}
		prov_tela_garantirTabela();
		$parametros = [];
		$rsP = query("SELECT para_nb_id, para_tx_nome FROM parametro WHERE para_tx_status = 'ativo' ORDER BY para_tx_nome ASC");
		while($rsP && ($r = mysqli_fetch_assoc($rsP))){ $parametros[$r["para_nb_id"]] = $r["para_tx_nome"]; }

		$v = function(string $k): string { return htmlspecialchars(strval($_POST[$k] ?? ""), ENT_QUOTES); };
		$self = basename(__FILE__);

		cabecalho("Nova Empresa (provisionamento automático)");
?>

<?php if(!empty($_POST["msg_status"])): $__erro = is_int(strpos(strval($_POST["msg_status"]), "ERRO")); ?>
<div class="alert <?php echo $__erro ? "alert-danger" : "alert-success"; ?>" style="margin-bottom:16px;font-size:13.5px"><?php echo $_POST["msg_status"]; ?></div>
<?php endif; ?>

<style>
.pv-card{background:#fff;border:1px solid #e2e2e2;border-radius:8px;padding:18px;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:16px}
.pv-title{font-size:12px;text-transform:uppercase;letter-spacing:.6px;color:#999;margin-bottom:6px;display:flex;align-items:center;gap:6px}
.pv-grid{display:grid;grid-template-columns:repeat(12,1fr);gap:10px 14px}
.pv-c2{grid-column:span 2}.pv-c3{grid-column:span 3}.pv-c4{grid-column:span 4}.pv-c5{grid-column:span 5}.pv-c6{grid-column:span 6}.pv-c12{grid-column:span 12}
@media(max-width:900px){.pv-c2,.pv-c3,.pv-c4,.pv-c5,.pv-c6{grid-column:span 12}}
.pv-grid label{font-size:12px;font-weight:600;color:#555;display:block;margin:0 0 3px}
.pv-grid input[type=text],.pv-grid input[type=email],.pv-grid input[type=date],.pv-grid select{width:100%;border:1px solid #ddd;border-radius:4px;padding:6px 9px;font-size:13px;height:32px;background:#fff}
.pv-grid input[type=file]{font-size:12px}
.pv-erro{border-color:#e74c3c !important;background:#fff6f5 !important}
.pv-help{font-size:11.5px;color:#888;margin-top:3px;line-height:1.4}
.pv-btn{border:0;border-radius:4px;padding:9px 16px;font-size:13px;font-weight:600;color:#fff;background:#3c8dbc;cursor:pointer;text-decoration:none;display:inline-block}
.pv-btn:disabled{opacity:.6;cursor:default}
.pv-btn.danger{background:#c0392b}
.pv-btn.sec{background:#888}
.pv-btn.ok{background:#1e7e34}
.pv-btn.peq{padding:4px 9px;font-size:11px}
.pv-badge{display:inline-block;padding:2px 9px;border-radius:10px;font-size:11px;font-weight:600;color:#fff}
.pv-item{border:1px solid #eee;border-radius:6px;padding:12px;margin-bottom:10px}
.pv-item h4{margin:0 0 4px;font-size:14px}
.pv-mini{height:5px;background:#eee;border-radius:3px;overflow:hidden;margin-top:8px}
.pv-mini > div{height:5px;background:#3c8dbc;transition:width .5s}
.pv-sep{grid-column:span 12;border-top:1px dashed #e5e5e5;margin:8px 0 0;padding-top:10px}
.pv-cid{position:relative}
.pv-cid-lista{position:absolute;z-index:20;left:0;right:0;top:100%;background:#fff;border:1px solid #ddd;border-top:0;max-height:200px;overflow:auto;display:none;box-shadow:0 4px 10px rgba(0,0,0,.08)}
.pv-cid-lista div{padding:6px 9px;font-size:12.5px;cursor:pointer}
.pv-cid-lista div:hover{background:#eef6fb}
.pv-aviso{font-size:11.5px;margin-top:3px}
.pv-alerta{border-radius:6px;padding:10px 12px;font-size:13px;margin-bottom:12px;display:none}
.pv-alerta.erro{background:#fdecea;border:1px solid #f5c6cb;color:#8a1f17;display:block}

/* Modal de acompanhamento */
.pv-modal-fundo{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:10500;display:none;align-items:center;justify-content:center;padding:16px}
.pv-modal-fundo.aberto{display:flex}
.pv-modal{background:#fff;border-radius:10px;width:100%;max-width:620px;max-height:92vh;display:flex;flex-direction:column;box-shadow:0 12px 40px rgba(0,0,0,.35);overflow:hidden}
.pv-modal-topo{padding:16px 20px;border-bottom:1px solid #eee;display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
.pv-modal-topo h3{margin:0;font-size:16px;font-weight:700;color:#333}
.pv-modal-topo small{color:#888;font-size:12px}
.pv-modal-x{border:0;background:none;font-size:22px;line-height:1;color:#999;cursor:pointer}
.pv-modal-corpo{padding:18px 20px;overflow:auto}
.pv-modal-rodape{padding:12px 20px;border-top:1px solid #eee;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}
.pv-barra{height:22px;background:#eef1f4;border-radius:11px;overflow:hidden;position:relative}
.pv-barra-fill{height:100%;width:0;background:linear-gradient(90deg,#3c8dbc,#35a4bc);transition:width .6s ease;border-radius:11px}
.pv-barra-fill.anim{background-image:linear-gradient(45deg,rgba(255,255,255,.22) 25%,transparent 25%,transparent 50%,rgba(255,255,255,.22) 50%,rgba(255,255,255,.22) 75%,transparent 75%,transparent);background-size:28px 28px;background-color:#3c8dbc;animation:pvListras 1s linear infinite}
.pv-barra-fill.ok{background:#1e7e34}
.pv-barra-fill.erro{background:#c0392b}
.pv-barra-fill.roxo{background-color:#8e44ad}
@keyframes pvListras{from{background-position:0 0}to{background-position:28px 0}}
.pv-barra-txt{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#222;text-shadow:0 0 3px #fff}
.pv-situacao{font-size:13px;color:#555;margin:10px 0 14px;min-height:18px}
.pv-passos{list-style:none;margin:0;padding:0}
.pv-passos li{display:flex;align-items:flex-start;gap:10px;padding:7px 0;border-bottom:1px solid #f4f4f4;font-size:13px;color:#999}
.pv-passos li:last-child{border-bottom:0}
.pv-passos .ic{flex:0 0 22px;height:22px;border-radius:50%;border:2px solid #ddd;display:flex;align-items:center;justify-content:center;font-size:11px;color:#bbb;background:#fff}
.pv-passos li.feito{color:#333}
.pv-passos li.feito .ic{border-color:#1e7e34;background:#1e7e34;color:#fff}
.pv-passos li.atual{color:#1b5e86;font-weight:600}
.pv-passos li.atual .ic{border-color:#3c8dbc;color:#3c8dbc}
.pv-passos li.falhou{color:#8a1f17;font-weight:600}
.pv-passos li.falhou .ic{border-color:#c0392b;background:#c0392b;color:#fff}
.pv-passos small{display:block;font-weight:400;color:#888;font-size:11.5px;margin-top:1px}
.pv-senha{background:#fff8e1;border:1px solid #ffe082;border-radius:6px;padding:10px 12px;font-size:13px;margin-bottom:14px;display:none}
.pv-senha b{font-family:Consolas,monospace;font-size:15px;letter-spacing:.5px}
.pv-log{background:#1e1e1e;color:#d4d4d4;font-family:Consolas,monospace;font-size:11px;padding:10px;border-radius:4px;white-space:pre-wrap;max-height:200px;overflow:auto;margin-top:12px;display:none}
.pv-link{font-size:12px;color:#3c8dbc;cursor:pointer;background:none;border:0;padding:0;margin-top:12px}
</style>

<div class="pv-card">
	<div class="pv-alerta" id="pvAlerta"></div>
	<form method="post" enctype="multipart/form-data" onsubmit="return pvEnviar(this);" autocomplete="off" id="pvForm">
		<input type="hidden" name="acao" value="solicitarEmpresa">

		<div class="pv-title"><i class="fa fa-building"></i> Dados da Empresa</div>
		<div class="pv-grid">
			<div class="pv-c3"><label>CPF/CNPJ*</label><input type="text" name="cnpj" maxlength="18" value="<?php echo $v("cnpj"); ?>" required onblur="pvChecarCnpj(this.value)"><div class="pv-aviso" id="pvCnpjAviso"></div></div>
			<div class="pv-c5"><label>Nome*</label><input type="text" name="nome" maxlength="65" value="<?php echo $v("nome"); ?>" required oninput="pvSugerir(this.form)"></div>
			<div class="pv-c4"><label>Nome Fantasia</label><input type="text" name="fantasia" maxlength="65" value="<?php echo $v("fantasia"); ?>"></div>

			<div class="pv-c2"><label>CEP*</label><input type="text" name="cep" maxlength="9" value="<?php echo $v("cep"); ?>" required onblur="pvCep(this.value)"></div>
			<div class="pv-c5"><label>Endereço*</label><input type="text" name="endereco" maxlength="100" value="<?php echo $v("endereco"); ?>" required></div>
			<div class="pv-c2"><label>Número*</label><input type="text" name="numero" maxlength="30" value="<?php echo $v("numero"); ?>" required></div>
			<div class="pv-c3"><label>Bairro*</label><input type="text" name="bairro" maxlength="30" value="<?php echo $v("bairro"); ?>" required></div>

			<div class="pv-c3"><label>Complemento</label><input type="text" name="complemento" maxlength="100" value="<?php echo $v("complemento"); ?>"></div>
			<div class="pv-c3"><label>Referência</label><input type="text" name="referencia" maxlength="100" value="<?php echo $v("referencia"); ?>"></div>
			<div class="pv-c6 pv-cid">
				<label>Cidade/UF*</label>
				<input type="hidden" name="cidade" value="">
				<input type="text" id="pvCidadeBusca" data-campo="cidade" placeholder="Digite o nome da cidade ou informe o CEP" oninput="pvBuscarCidade(this.value)" autocomplete="off">
				<div class="pv-cid-lista" id="pvCidadeLista"></div>
			</div>

			<div class="pv-c3"><label>Telefone 1</label><input type="text" name="fone1" maxlength="20" value="<?php echo $v("fone1"); ?>"></div>
			<div class="pv-c3"><label>Telefone 2</label><input type="text" name="fone2" maxlength="20" value="<?php echo $v("fone2"); ?>"></div>
			<div class="pv-c3"><label>Contato</label><input type="text" name="contato" maxlength="100" value="<?php echo $v("contato"); ?>"></div>
			<div class="pv-c3"><label>E-mail*</label><input type="email" name="email" maxlength="120" value="<?php echo $v("email"); ?>" required></div>

			<div class="pv-c3"><label>Inscrição Estadual</label><input type="text" name="inscricaoEstadual" maxlength="20" value="<?php echo $v("inscricaoEstadual"); ?>"></div>
			<div class="pv-c3"><label>Inscrição Municipal</label><input type="text" name="inscricaoMunicipal" maxlength="20" value="<?php echo $v("inscricaoMunicipal"); ?>"></div>
			<div class="pv-c3"><label>Regime Tributário</label>
				<select name="regimeTributario"><?php foreach(prov_tela_regimes() as $k => $t): ?><option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($t); ?></option><?php endforeach; ?></select>
			</div>
			<div class="pv-c3"><label>Data Reg. CNPJ</label><input type="date" name="dataRegistroCNPJ" value="<?php echo $v("dataRegistroCNPJ"); ?>"></div>

			<div class="pv-c4"><label>Logo (.png, .jpeg)</label><input type="file" name="logo" accept="image/png,image/jpeg,image/gif"></div>
			<div class="pv-c3"><label>Assinatura eletrônica</label>
				<select name="tipoAssinatura"><?php foreach(prov_tela_tiposAssinatura() as $k => $t): ?><option value="<?php echo $k; ?>"><?php echo htmlspecialchars($t); ?></option><?php endforeach; ?></select>
			</div>
			<div class="pv-c5"><label>Parâmetros da Jornada*</label>
				<select name="parametro" required>
					<option value="">Selecione</option>
					<?php foreach($parametros as $pid => $pn): ?><option value="<?php echo intval($pid); ?>"><?php echo htmlspecialchars($pn); ?></option><?php endforeach; ?>
				</select>
				<div class="pv-help">A empresa nova recebe os parâmetros do banco modelo; o escolhido aqui vira o padrão dela.</div>
			</div>

			<div class="pv-sep"><div class="pv-title"><i class="fa fa-server"></i> Acesso da empresa</div></div>
			<div class="pv-c3"><label>Sigla de login*</label><input type="text" name="sigla" maxlength="30" value="<?php echo $v("sigla"); ?>" required style="text-transform:uppercase" placeholder="ex.: OPAFRUTAS">
				<div class="pv-help">O que o usuário escolhe no campo Empresa da tela de login.</div></div>
			<div class="pv-c3"><label>Pasta (endereço)*</label><input type="text" name="pasta" maxlength="40" value="<?php echo $v("pasta"); ?>" required style="text-transform:lowercase" placeholder="ex.: opafrutas">
				<div class="pv-help">Minúsculas, números e _. Vira o endereço e o nome do banco.</div></div>
			<div class="pv-c6"><label>Endereço que será criado</label><input type="text" id="pvUrlPrevia" value="" readonly style="background:#f7f7f7;color:#666"></div>

			<div class="pv-sep"><div class="pv-title"><i class="fa fa-user"></i> Administrador inicial</div></div>
			<div class="pv-c4"><label>Nome</label><input type="text" name="adminNome" maxlength="120" value="<?php echo $v("adminNome"); ?>"></div>
			<div class="pv-c4"><label>Login*</label><input type="text" name="adminLogin" maxlength="50" value="<?php echo $v("adminLogin"); ?>" required></div>
			<div class="pv-c4"><label>Senha inicial</label><input type="text" name="adminSenha" maxlength="40" placeholder="em branco = gerar automaticamente">
				<div class="pv-help">É mostrada uma única vez, na janela de acompanhamento.</div></div>

			<div class="pv-c12" style="margin-top:6px">
				<button type="submit" class="pv-btn" id="pvBtnCriar"><i class="fa fa-magic"></i> Criar empresa</button>
				<span class="pv-help" style="margin-left:10px">Cria banco, pasta, <code>.env</code>, empresa matriz e administrador. Também registra a empresa no cadastro deste domínio, para auditoria. Se alguma etapa falhar, tudo é desfeito.</span>
			</div>
		</div>
	</form>
</div>

<div class="pv-card">
	<div class="pv-title"><i class="fa fa-list"></i> Pedidos <span id="pvListaSit" style="color:#3c8dbc;text-transform:none"></span></div>
	<div id="pvLista"><div class="pv-help">Carregando...</div></div>
</div>

<!-- Modal de acompanhamento -->
<div class="pv-modal-fundo" id="pvModal">
	<div class="pv-modal" role="dialog" aria-modal="true">
		<div class="pv-modal-topo">
			<div>
				<h3 id="pvMTitulo">Criando empresa</h3>
				<small id="pvMSub"></small>
			</div>
			<button type="button" class="pv-modal-x" onclick="pvFecharModal()" title="Fechar">&times;</button>
		</div>
		<div class="pv-modal-corpo">
			<div class="pv-senha" id="pvMSenha"></div>
			<div class="pv-barra"><div class="pv-barra-fill" id="pvMBarra"></div><div class="pv-barra-txt" id="pvMPct">0%</div></div>
			<div class="pv-situacao" id="pvMSituacao"></div>
			<ul class="pv-passos" id="pvMPassos"></ul>
			<button type="button" class="pv-link" id="pvMLogBtn" onclick="pvAlternarLog()">Mostrar log técnico</button>
			<div class="pv-log" id="pvMLog"></div>
		</div>
		<div class="pv-modal-rodape" id="pvMRodape"></div>
	</div>
</div>

<script>
var PV_SELF = <?php echo json_encode($self); ?>;
var PV_BASE = <?php echo json_encode(rtrim(($_ENV["URL_BASE"] ?? "") . ($_ENV["APP_PATH"] ?? ""), "/") . "/"); ?>;
var PV_CORES = {aguardando:'#f0ad4e', pendente:'#f0ad4e', processando:'#3c8dbc', concluido:'#1e7e34', erro:'#c0392b', desfazer:'#8e44ad', desfeito:'#777', cancelado:'#777'};
var PV_ROTULOS = {aguardando:'na fila', pendente:'na fila', processando:'criando', concluido:'concluído', erro:'erro', desfazer:'removendo', desfeito:'removida', cancelado:'cancelado'};
var PV_PASSOS = [
	{n:1, t:'Validação dos dados', d:'Confere pasta, sigla e login'},
	{n:2, t:'Banco de dados', d:'Cria o banco e o usuário exclusivos da empresa'},
	{n:3, t:'Estrutura e cadastros de referência', d:'Tabelas, cidades, macros, motivos, feriados, menus e perfis'},
	{n:4, t:'Empresa e administrador', d:'Empresa matriz, administrador inicial e registro neste domínio'},
	{n:5, t:'Arquivos do sistema', d:'Copia os arquivos para a pasta da empresa e aplica a logo'},
	{n:6, t:'Configuração', d:'Grava o .env com as credenciais da empresa'},
	{n:7, t:'Registro no login', d:'Inclui a empresa na tela de login'},
	{n:8, t:'Conclusão', d:'Empresa pronta para uso'}
];
var pvModalId = 0, pvModalTimer = null, pvSenhaMem = {}, pvListaTimer = null;

function pvEsc(t){ return String(t == null ? '' : t).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
function pvPost(dados){
	var fd = (dados instanceof FormData) ? dados : new FormData();
	if(!(dados instanceof FormData)){ Object.keys(dados).forEach(function(k){ fd.append(k, dados[k]); }); }
	fd.append('ajax', '1');
	return fetch(PV_SELF, {method:'POST', body:fd, credentials:'same-origin'}).then(function(r){
		return r.text().then(function(t){
			try { return JSON.parse(t); } catch(e){ throw new Error('Resposta inesperada do servidor (HTTP ' + r.status + '). ' + t.replace(/<[^>]*>/g,' ').substring(0, 160)); }
		});
	});
}

// ------------------------------------------------------------ formulário
function pvSlug(t){ return (t||'').normalize('NFD').replace(/[̀-ͯ]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'').replace(/^([0-9])/,'e$1').substring(0,40); }
function pvPrevia(f){ document.getElementById('pvUrlPrevia').value = f.pasta.value ? (PV_BASE + f.pasta.value.toLowerCase() + '/') : ''; }
function pvSugerir(f){
	if(!f.pasta.dataset.editado){ f.pasta.value = pvSlug(f.nome.value); }
	if(!f.sigla.dataset.editado){ f.sigla.value = pvSlug(f.nome.value).replace(/_/g,' ').toUpperCase().substring(0,30); }
	pvPrevia(f);
}
document.addEventListener('input', function(e){
	if(!e.target) return;
	if(e.target.classList) e.target.classList.remove('pv-erro');
	if(e.target.name==='pasta' || e.target.name==='sigla'){ e.target.dataset.editado = '1'; pvPrevia(e.target.form); }
	if(e.target.name==='cnpj'){ e.target.value = pvMascaraDoc(e.target.value); }
	if(e.target.name==='cep'){ var d=e.target.value.replace(/\D/g,'').substring(0,8); e.target.value = d.length>5 ? d.substring(0,5)+'-'+d.substring(5) : d; }
	if(e.target.name==='fone1' || e.target.name==='fone2'){ e.target.value = pvMascaraFone(e.target.value); }
});
function pvMascaraDoc(v){
	var d = v.replace(/\D/g,'').substring(0,14);
	if(d.length <= 11) return d.replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2');
	return d.replace(/^(\d{2})(\d)/,'$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3').replace(/\.(\d{3})(\d)/,'.$1/$2').replace(/(\d{4})(\d)/,'$1-$2');
}
function pvMascaraFone(v){
	var d = v.replace(/\D/g,'').substring(0,11);
	if(d.length <= 10) return d.replace(/^(\d{2})(\d)/,'($1) $2').replace(/(\d{4})(\d)/,'$1-$2');
	return d.replace(/^(\d{2})(\d)/,'($1) $2').replace(/(\d{5})(\d)/,'$1-$2');
}
function pvSelecionarCidade(id, texto){
	var f = document.getElementById('pvForm');
	f.cidade.value = id;
	var b = document.getElementById('pvCidadeBusca');
	b.value = texto; b.classList.remove('pv-erro');
	document.getElementById('pvCidadeLista').style.display = 'none';
}
var pvCidTimer = null;
function pvBuscarCidade(q){
	var f = document.getElementById('pvForm');
	f.cidade.value = '';
	clearTimeout(pvCidTimer);
	var lista = document.getElementById('pvCidadeLista');
	var termo = (q||'').replace(/^\[[A-Z]{2}\]\s*/, '');
	if(termo.length < 2){ lista.style.display = 'none'; return; }
	pvCidTimer = setTimeout(function(){
		pvPost({acao:'buscarCidade', q:termo}).then(function(itens){
			lista.innerHTML = '';
			(itens||[]).forEach(function(it){
				var d = document.createElement('div'); d.textContent = it.text;
				d.onclick = function(){ pvSelecionarCidade(it.id, it.text); };
				lista.appendChild(d);
			});
			lista.style.display = (itens && itens.length) ? 'block' : 'none';
		}).catch(function(){ lista.style.display = 'none'; });
	}, 250);
}
function pvCep(cep){
	var d = (cep||'').replace(/\D/g,'');
	if(d.length !== 8) return;
	fetch('https://viacep.com.br/ws/' + d + '/json/').then(function(r){ return r.json(); }).then(function(data){
		if(!data || data.erro) return;
		var f = document.getElementById('pvForm');
		if(!f.endereco.value) f.endereco.value = data.logradouro || '';
		if(!f.bairro.value) f.bairro.value = data.bairro || '';
		if(data.ibge){
			pvPost({acao:'buscarCidade', ibge:data.ibge}).then(function(itens){ if(itens && itens.length){ pvSelecionarCidade(itens[0].id, itens[0].text); } });
		}
	}).catch(function(){});
}
function pvChecarCnpj(v){
	var aviso = document.getElementById('pvCnpjAviso');
	aviso.textContent = '';
	var d = (v||'').replace(/\D/g,'');
	if(d.length !== 11 && d.length !== 14) return;
	pvPost({acao:'checarCnpjProv', cnpj:d}).then(function(res){
		if(res && res.existe){ aviso.style.color = '#c0392b'; aviso.textContent = 'CNPJ já cadastrado neste domínio: ' + res.nome; }
	}).catch(function(){});
}
function pvAlerta(msg){
	var a = document.getElementById('pvAlerta');
	if(!msg){ a.className = 'pv-alerta'; a.textContent = ''; return; }
	a.className = 'pv-alerta erro'; a.textContent = msg;
	a.scrollIntoView({behavior:'smooth', block:'center'});
}
function pvMarcarErros(campos){
	var f = document.getElementById('pvForm');
	Array.prototype.forEach.call(f.querySelectorAll('.pv-erro'), function(el){ el.classList.remove('pv-erro'); });
	(campos||[]).forEach(function(c){
		var el = f.querySelector('[name="'+c+'"]:not([type=hidden])') || f.querySelector('[data-campo="'+c+'"]');
		if(el) el.classList.add('pv-erro');
	});
}
function pvEnviar(f){
	pvAlerta('');
	f.pasta.value = f.pasta.value.toLowerCase().trim();
	f.sigla.value = f.sigla.value.toUpperCase().trim();
	if(!f.cidade.value){ pvMarcarErros(['cidade']); pvAlerta('Selecione a cidade na lista (digite o nome ou informe o CEP).'); return false; }
	if(!/^[a-z][a-z0-9_]{2,39}$/.test(f.pasta.value)){ pvMarcarErros(['pasta']); pvAlerta('Pasta inválida: use minúsculas, números e _, começando por letra (3 a 40 caracteres).'); return false; }
	if(!confirm('Criar a empresa "'+f.nome.value+'" na pasta /'+f.pasta.value+' ?')) return false;

	var btn = document.getElementById('pvBtnCriar');
	btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Registrando...';
	pvPost(new FormData(f)).then(function(res){
		if(!res.ok){
			pvMarcarErros(res.campos);
			pvAlerta(res.msg || 'Não foi possível registrar o pedido.');
			return;
		}
		pvSenhaMem[res.id] = {login: res.adminLogin, senha: res.adminSenha};
		f.reset();
		f.cidade.value = '';
		delete f.pasta.dataset.editado; delete f.sigla.dataset.editado;
		pvPrevia(f);
		document.getElementById('pvCnpjAviso').textContent = '';
		pvCarregarLista();
		pvAbrirModal(res.id);
	}).catch(function(e){
		pvAlerta(e.message || 'Falha de comunicação com o servidor.');
	}).then(function(){
		btn.disabled = false; btn.innerHTML = '<i class="fa fa-magic"></i> Criar empresa';
	});
	return false;
}

// ------------------------------------------------------------ lista de pedidos
function pvEmAndamento(st){ return ['aguardando','pendente','processando','desfazer'].indexOf(st) !== -1; }
function pvRenderLista(pedidos){
	var box = document.getElementById('pvLista');
	if(!pedidos || !pedidos.length){ box.innerHTML = '<div class="pv-help">Nenhum pedido registrado.</div>'; return; }
	box.innerHTML = pedidos.map(function(p){
		var acoes = '<button type="button" class="pv-btn sec peq" onclick="pvAbrirModal('+p.id+')"><i class="fa fa-tasks"></i> Acompanhar</button> ';
		if(p.status === 'concluido') acoes += '<a class="pv-btn ok peq" target="_blank" href="'+pvEsc(p.urlLogin)+'">Abrir login</a> ';
		if(p.podeCancelar) acoes += '<button type="button" class="pv-btn danger peq" onclick="pvCancelar('+p.id+')">Cancelar</button> ';
		if(p.podeDesfazer) acoes += '<button type="button" class="pv-btn danger peq" data-pasta="'+pvEsc(p.pasta)+'" onclick="pvDesfazer('+p.id+', this.dataset.pasta)"><i class="fa fa-undo"></i> Desfazer (remover empresa)</button>';
		var mini = pvEmAndamento(p.status) ? '<div class="pv-mini"><div style="width:'+(p.status==='desfazer'?100:p.progresso)+'%;background:'+(PV_CORES[p.status]||'#3c8dbc')+'"></div></div>' : '';
		return '<div class="pv-item">'
			+ '<h4>#'+p.id+' '+pvEsc(p.nome)+' <span class="pv-badge" style="background:'+(PV_CORES[p.status]||'#777')+'">'+pvEsc(PV_ROTULOS[p.status]||p.status)+(p.status==='processando'?' '+p.progresso+'%':'')+'</span></h4>'
			+ '<div style="font-size:12px;color:#666">Sigla <b>'+pvEsc(p.sigla)+'</b> &middot; pasta <b>/'+pvEsc(p.pasta)+'</b> &middot; admin <b>'+pvEsc(p.adminLogin)+'</b>'
			+ (p.cnpj ? ' &middot; CNPJ '+pvEsc(p.cnpj) : '')
			+ (p.cidade ? ' &middot; '+pvEsc(p.cidade) : '')
			+ (p.empresaMestre ? ' &middot; cadastro neste domínio: empresa #'+p.empresaMestre : '')
			+ '<br>Pedido em '+pvEsc(p.pedidoEm)+' por '+pvEsc(p.usuario||'-')
			+ (p.finalizadoEm ? ' &middot; finalizado '+pvEsc(p.finalizadoEm) : '')
			+ '</div>' + mini
			+ '<div style="margin-top:8px">'+acoes+'</div>'
			+ '</div>';
	}).join('');
}
function pvCarregarLista(){
	return pvPost({acao:'listarPedidos'}).then(function(res){
		var pedidos = (res && res.pedidos) || [];
		pvRenderLista(pedidos);
		var ativo = pedidos.some(function(p){ return pvEmAndamento(p.status); });
		document.getElementById('pvListaSit').innerHTML = ativo ? '&nbsp;<i class="fa fa-refresh fa-spin"></i> em andamento' : '';
		clearTimeout(pvListaTimer);
		if(ativo) pvListaTimer = setTimeout(pvCarregarLista, 4000);
	}).catch(function(){
		clearTimeout(pvListaTimer);
		pvListaTimer = setTimeout(pvCarregarLista, 8000);
	});
}
function pvCancelar(id){
	if(!confirm('Cancelar este pedido?')) return;
	pvPost({acao:'desfazerEmpresa', id:id}).then(function(res){
		if(!res.ok) alert(res.msg || 'Não foi possível cancelar.');
		pvCarregarLista();
	}).catch(function(e){ alert(e.message); });
}
function pvDesfazer(id, pasta){
	var NL = String.fromCharCode(10);
	var v = prompt('ATENÇÃO: isso remove o banco de dados, a pasta e o acesso da empresa.' + NL + 'O cadastro neste domínio fica inativo, para auditoria.' + NL + NL + 'Para confirmar, digite o nome da pasta: ' + pasta);
	if(v === null) return;
	pvPost({acao:'desfazerEmpresa', id:id, confirmacao:v}).then(function(res){
		if(!res.ok){ alert(res.msg || 'Não foi possível desfazer.'); return; }
		pvCarregarLista();
		if(res.acompanhar) pvAbrirModal(id);
	}).catch(function(e){ alert(e.message); });
}

// ------------------------------------------------------------ modal de acompanhamento
function pvAbrirModal(id){
	pvModalId = id;
	document.getElementById('pvMLog').style.display = 'none';
	document.getElementById('pvMLogBtn').textContent = 'Mostrar log técnico';
	document.getElementById('pvMTitulo').textContent = 'Pedido #' + id;
	document.getElementById('pvMSub').textContent = '';
	document.getElementById('pvMPassos').innerHTML = '';
	document.getElementById('pvMRodape').innerHTML = '';
	document.getElementById('pvMSituacao').textContent = 'Consultando...';
	pvBarra(0, 'anim');
	var s = pvSenhaMem[id], box = document.getElementById('pvMSenha');
	if(s){ box.style.display = 'block'; box.innerHTML = '<i class="fa fa-key"></i> Senha inicial do administrador <b>'+pvEsc(s.login)+'</b>: <b>'+pvEsc(s.senha)+'</b><br><small>Anote agora. Ela não será exibida novamente depois de fechar esta página.</small>'; }
	else { box.style.display = 'none'; box.innerHTML = ''; }
	document.getElementById('pvModal').classList.add('aberto');
	pvConsultar();
}
function pvFecharModal(){
	document.getElementById('pvModal').classList.remove('aberto');
	clearTimeout(pvModalTimer);
	pvModalId = 0;
	pvCarregarLista();
}
function pvAlternarLog(){
	var l = document.getElementById('pvMLog'), b = document.getElementById('pvMLogBtn');
	var abrir = l.style.display !== 'block';
	l.style.display = abrir ? 'block' : 'none';
	b.textContent = abrir ? 'Ocultar log técnico' : 'Mostrar log técnico';
	if(abrir) l.scrollTop = l.scrollHeight;
}
function pvBarra(pct, classe){
	var b = document.getElementById('pvMBarra');
	b.className = 'pv-barra-fill' + (classe ? ' ' + classe : '');
	b.style.width = Math.max(0, Math.min(100, pct)) + '%';
	document.getElementById('pvMPct').textContent = Math.round(pct) + '%';
}
function pvPrimeiroErro(log){
	// Primeira linha "ERRO: ..." do log (a causa; as seguintes são a reversão)
	var achado = '';
	String(log || '').split(String.fromCharCode(10)).some(function(linha){
		var i = linha.indexOf('ERRO: ');
		if(i === -1) return false;
		achado = linha.substring(i + 6);
		return true;
	});
	return achado;
}
function pvDetalhePasso(log, n){
	// Última linha do log daquela etapa (ex.: "[10:00:01] 5-arquivos: 1709 arquivos copiados")
	var marca = '] ' + n + '-', ultimo = '';
	String(log || '').split(String.fromCharCode(10)).forEach(function(linha){
		var i = linha.indexOf(marca);
		if(i === -1) return;
		var j = linha.indexOf(': ', i);
		if(j !== -1) ultimo = linha.substring(j + 2);
	});
	return ultimo;
}
function pvRenderModal(p){
	document.getElementById('pvMTitulo').textContent = (p.status === 'desfazer' || p.status === 'desfeito' ? 'Removendo empresa: ' : 'Criando empresa: ') + p.nome;
	document.getElementById('pvMSub').textContent = 'Pedido #' + p.id + ' · sigla ' + p.sigla + ' · pasta /' + p.pasta;
	var log = p.log || '';
	var l = document.getElementById('pvMLog');
	var noFim = l.scrollTop + l.clientHeight >= l.scrollHeight - 20;
	l.textContent = log || '(sem registros ainda)';
	if(noFim) l.scrollTop = l.scrollHeight;

	var sit = '', rodape = '<button type="button" class="pv-btn sec" onclick="pvFecharModal()">Fechar</button>';
	var remocao = (p.status === 'desfazer' || p.status === 'desfeito' || p.status === 'cancelado');

	if(p.status === 'aguardando' || p.status === 'pendente'){
		pvBarra(0, 'anim');
		sit = '<i class="fa fa-clock-o"></i> Na fila. O agendador do servidor inicia em até 1 minuto.';
	}else if(p.status === 'processando'){
		pvBarra(p.progresso, 'anim');
		var atual = PV_PASSOS.filter(function(x){ return x.n === p.passo; })[0];
		sit = '<i class="fa fa-cog fa-spin"></i> ' + (atual ? atual.t : 'Processando') + '...';
	}else if(p.status === 'concluido'){
		pvBarra(100, 'ok');
		sit = '<i class="fa fa-check-circle" style="color:#1e7e34"></i> <b>Empresa criada com sucesso.</b>' + (p.empresaMestre ? ' Registrada neste domínio como empresa #' + p.empresaMestre + '.' : '');
		rodape = '<a class="pv-btn ok" target="_blank" href="'+pvEsc(p.urlLogin)+'"><i class="fa fa-sign-in"></i> Abrir login da empresa</a>' + rodape;
	}else if(p.status === 'erro'){
		pvBarra(Math.max(p.progresso, 5), 'erro');
		var erro = pvPrimeiroErro(log) || 'Falha durante a criação.';
		sit = '<i class="fa fa-times-circle" style="color:#c0392b"></i> <b>Não foi possível criar a empresa.</b><br>' + pvEsc(erro) + '<br><small>O que havia sido criado foi removido.</small>';
	}else if(p.status === 'desfazer'){
		pvBarra(100, 'anim roxo');
		document.getElementById('pvMPct').textContent = 'removendo...';
		sit = '<i class="fa fa-cog fa-spin"></i> Removendo banco, pasta e acesso da empresa...';
	}else if(p.status === 'desfeito'){
		pvBarra(100, 'ok');
		document.getElementById('pvMPct').textContent = 'removida';
		sit = '<i class="fa fa-check-circle" style="color:#1e7e34"></i> <b>Empresa removida.</b> O cadastro neste domínio ficou inativo, para auditoria.';
	}else if(p.status === 'cancelado'){
		pvBarra(0, '');
		sit = 'Pedido cancelado antes de ser executado.';
	}
	document.getElementById('pvMSituacao').innerHTML = sit;
	document.getElementById('pvMRodape').innerHTML = rodape;

	var ul = document.getElementById('pvMPassos');
	if(remocao){
		var ultima = '';
		String(log).split(String.fromCharCode(10)).forEach(function(linha){
			var i = linha.indexOf('DESFEITO: ');
			if(i !== -1) ultima = linha.substring(i + 10);
		});
		var itens = ultima.split(', ').filter(Boolean);
		ul.innerHTML = p.status === 'desfeito'
			? itens.map(function(t){ return '<li class="feito"><span class="ic"><i class="fa fa-check"></i></span><span>'+pvEsc(t)+'</span></li>'; }).join('')
			: '';
		return;
	}
	ul.innerHTML = PV_PASSOS.map(function(x){
		var cls = '', ic = x.n, det = pvDetalhePasso(log, x.n) || x.d;
		if(p.status === 'concluido' || x.n < p.passo){ cls = 'feito'; ic = '<i class="fa fa-check"></i>'; }
		else if(x.n === p.passo){
			if(p.status === 'erro'){ cls = 'falhou'; ic = '<i class="fa fa-times"></i>'; det = (pvPrimeiroErro(log) || det); }
			else if(p.status === 'processando'){ cls = 'atual'; ic = '<i class="fa fa-cog fa-spin"></i>'; }
		}
		return '<li class="'+cls+'"><span class="ic">'+ic+'</span><span>'+pvEsc(x.t)+'<small>'+pvEsc(det)+'</small></span></li>';
	}).join('');
}
function pvConsultar(){
	if(!pvModalId) return;
	var id = pvModalId;
	pvPost({acao:'statusPedido', id:id}).then(function(res){
		if(pvModalId !== id) return;
		if(!res || !res.ok){ document.getElementById('pvMSituacao').textContent = (res && res.msg) || 'Pedido não encontrado.'; return; }
		pvRenderModal(res.pedido);
		clearTimeout(pvModalTimer);
		if(pvEmAndamento(res.pedido.status)){ pvModalTimer = setTimeout(pvConsultar, 1500); }
		else { pvCarregarLista(); }
	}).catch(function(e){
		if(pvModalId !== id) return;
		document.getElementById('pvMSituacao').textContent = 'Sem resposta do servidor, tentando de novo...';
		clearTimeout(pvModalTimer);
		pvModalTimer = setTimeout(pvConsultar, 4000);
	});
}
document.getElementById('pvModal').addEventListener('click', function(e){ if(e.target === this) pvFecharModal(); });
document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && pvModalId) pvFecharModal(); });

pvPrevia(document.getElementById('pvForm'));
pvCarregarLista();
</script>
<?php
		rodape();
	}

	include_once "conecta.php";
