<?php
	/*
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	*/

$rawUri = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : "/";
if(function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE){ session_start(); }
$nivel = isset($_SESSION["user_tx_nivel"]) ? $_SESSION["user_tx_nivel"] : "";
if($nivel !== "Super Administrador"){
    $dir = rtrim(dirname($rawUri), '/\\');
    $target = ($dir ? ($dir."/index.php") : "/index.php");
    header("Location: ".$target);
    exit;
}

include "conecta.php";

function salvarNoDb2($data){
    $h = !empty($_ENV["DB_HOST2"]) ? trim((string)$_ENV["DB_HOST2"]) : getenv("DB_HOST2");
    $u = !empty($_ENV["DB_USER2"]) ? trim((string)$_ENV["DB_USER2"]) : getenv("DB_USER2");
    $p = !empty($_ENV["DB_PASSWORD2"]) ? trim((string)$_ENV["DB_PASSWORD2"]) : getenv("DB_PASSWORD2");
    $n = !empty($_ENV["DB_NAME2"]) ? trim((string)$_ENV["DB_NAME2"]) : getenv("DB_NAME2");
    $port = !empty($_ENV["DB_PORT2"]) ? (int)trim((string)$_ENV["DB_PORT2"]) : (int)getenv("DB_PORT2");
    $host = $h;
    if(strpos($host, ':') !== false){
        $parts = explode(':', $host, 2);
        $host = $parts[0];
        if(empty($port) && is_numeric($parts[1])){ $port = (int)$parts[1]; }
    }

    if(empty($h) || empty($u) || empty($n)){ return; }
    $link = mysqli_init();
    mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 2);
    $connected = $port ? mysqli_real_connect($link, $host, $u, $p, $n, $port) : mysqli_real_connect($link, $host, $u, $p, $n);
    if(!$connected){ return; }
    mysqli_set_charset($link, "utf8mb4");

    $has = mysqli_query($link, "SHOW TABLES LIKE 'comunicados'");
    if(!$has || mysqli_num_rows($has) === 0){ mysqli_close($link); return; }

    $colsRes = mysqli_query($link, "SHOW COLUMNS FROM comunicados");
    $avail = [];
    while($colsRes && ($r = mysqli_fetch_assoc($colsRes))){ $avail[] = $r['Field']; }
    $payload = [];
    if(in_array('comu_tx_titulo', $avail)) $payload['comu_tx_titulo'] = $data['comu_tx_titulo'] ?? '';
    if(in_array('comu_tx_texto', $avail)) $payload['comu_tx_texto'] = $data['comu_tx_texto'] ?? '';
    if(in_array('comu_tx_destino', $avail)) $payload['comu_tx_destino'] = $data['comu_tx_destino'] ?? '';
    if(in_array('comu_tx_status', $avail)) $payload['comu_tx_status'] = $data['comu_tx_status'] ?? 'ativo';
    if(in_array('comu_nb_userCadastro', $avail) && !empty($_SESSION['user_nb_id'])) $payload['comu_nb_userCadastro'] = strval($_SESSION['user_nb_id']);
    if(in_array('comu_tx_dataCadastro', $avail)) $payload['comu_tx_dataCadastro'] = date("Y-m-d H:i:s");
    if(empty($payload)){ mysqli_close($link); return; }

    $keys = array_keys($payload);
    $vals = [];
    foreach($payload as $v){ $vals[] = "'".mysqli_real_escape_string($link, $v)."'"; }
    $sql = "INSERT INTO comunicados (".implode(',', $keys).") VALUES (".implode(',', $vals).")";
    mysqli_query($link, $sql);
    mysqli_close($link);
}

	function excluirComunicado(){
		remover("comunicados", $_POST["id"]);
		index();
		exit;
	}

	function modificarComunicado(){
		$_POST = array_merge($_POST, mysqli_fetch_assoc(query("SELECT * FROM comunicados WHERE comu_nb_id = {$_POST["id"]};")));
		layout_comunicado();
		exit;
	}

	function cadastra_comunicado(){
		$camposObrig = [
			"titulo" => "Título",
			"texto" => "Texto",
			"destino" => "Destino"
		];
		$errorMsg = conferirCamposObrig($camposObrig, $_POST);
		if(!empty($errorMsg)){
			set_status("ERRO: ".$errorMsg);
			layout_comunicado();
			exit;
		}

        $allowed = '<a><b><strong><i><em><u><br><p><ul><ol><li><h1><h2><h3><h4>';
        $html = isset($_POST["texto"]) ? $_POST["texto"] : "";
        $html = strip_tags($html, $allowed);
        $html = preg_replace('/on\\w+\s*=\s*"[^"]*"/i', '', $html);
        $html = preg_replace('/href\s*=\s*"\s*javascript:[^"]*"/i', 'href="#"', $html);

        $novo = [
            "comu_tx_titulo" => $_POST["titulo"],
            "comu_tx_texto" => $html,
            "comu_tx_destino" => $_POST["destino"],
            "comu_tx_status" => "ativo"
        ];

        if(!empty($_POST["id"])){
            atualizar("comunicados", array_keys($novo), array_values($novo), $_POST["id"]);
        }else{
            $novo["comu_nb_userCadastro"] = $_SESSION["user_nb_id"];
            $novo["comu_tx_dataCadastro"] = date("Y-m-d H:i:s");
            inserir("comunicados", array_keys($novo), array_values($novo));
        }
        salvarNoDb2($novo);

		index();
		exit;
	}

	function layout_comunicado(){
		cabecalho("Cadastro de Comunicado");

		$destinos = [
			"" => "",
			"Administrador" => "Administradores",
			"Funcionário" => "Funcionários"
		];

        $initial = (!empty($_POST["comu_tx_texto"]) ? $_POST["comu_tx_texto"] : (!empty($_POST["texto"]) ? $_POST["texto"] : ""));
        $editor = "<div class='col-sm-12 margin-bottom-5' style='min-width: fit-content;'>"
            ."<label>Conteúdo*</label>"
            ."<div id='comu-toolbar' class='btn-group' style='margin-bottom:8px'>"
                ."<button type='button' class='btn btn-default' title='Negrito' data-cmd='bold'><span class='glyphicon glyphicon-bold'></span></button>"
                ."<button type='button' class='btn btn-default' title='Itálico' data-cmd='italic'><span class='glyphicon glyphicon-italic'></span></button>"
                ."<button type='button' class='btn btn-default' title='Sublinhado' data-cmd='underline'><span class='glyphicon glyphicon-text-width'></span></button>"
                ."<button type='button' class='btn btn-default' title='Lista' data-cmd='insertUnorderedList'><span class='glyphicon glyphicon-list'></span></button>"
                ."<button type='button' class='btn btn-default' title='Link' data-cmd='createLink'><span class='glyphicon glyphicon-link'></span></button>"
            ."</div>"
            ."<div id='comu-editor' class='form-control' contenteditable='true' style='min-height:240px; white-space: pre-wrap;'>".$initial."</div>"
            ."<input type='hidden' name='texto' id='comu-texto'>"
        ."</div>"
        ."<script>(function(){var ed=document.getElementById('comu-editor');var tb=document.getElementById('comu-toolbar');if(tb){tb.addEventListener('click',function(e){var b=e.target.closest('button');if(!b)return;var cmd=b.getAttribute('data-cmd');if(cmd==='createLink'){var url=prompt('URL:');if(url){document.execCommand('createLink',false,url);}}else if(cmd){document.execCommand(cmd,false,null);}});}if(document.contex_form){document.contex_form.addEventListener('submit',function(){var h=document.getElementById('comu-texto');h.value=ed.innerHTML;});}})();</script>";

        $campos = [
            campo("Título*", "titulo", (!empty($_POST["comu_tx_titulo"])? $_POST["comu_tx_titulo"]: (!empty($_POST["titulo"]) ? $_POST["titulo"] : "")), 6, "", "maxlength='150'"),
            combo("Destino*", "destino", (!empty($_POST["comu_tx_destino"])? $_POST["comu_tx_destino"]: (!empty($_POST["destino"]) ? $_POST["destino"] : "")), 3, $destinos),
            $editor
        ];

		$botoes = [
			botao("Gravar", "cadastra_comunicado", "id", (!empty($_POST["comu_nb_id"]) ? $_POST["comu_nb_id"] : (!empty($_POST["id"]) ? $_POST["id"] : NULL)), "", "", "btn btn-success"),
			criarBotaoVoltar()
		];

		echo abre_form("Dados do Comunicado");
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"] ?? ( $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/index.php"));
		echo linha_form($campos);
		echo fecha_form($botoes);

		rodape();
	}

	function index(){
		if(is_bool(strpos($_SESSION["user_tx_nivel"], "Administrador")) && is_bool(strpos($_SESSION["user_tx_nivel"], "Super Administrador"))){
			$_POST["returnValues"] = json_encode([
				"HTTP_REFERER" => $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/index.php"
			]);
			voltar();
		}

		cabecalho("Cadastro de Comunicado");

		$destinos = [
			"" => "Todos",
			"Administrador" => "Administradores",
			"Funcionário" => "Funcionários"
		];

		$campos = [
			campo("Código", "busca_codigo", (!empty($_POST["busca_codigo"]) ? $_POST["busca_codigo"] : ""), 2, "MASCARA_NUMERO", "maxlength='6'"),
			campo("Título", "busca_titulo_like", (!empty($_POST["busca_titulo_like"]) ? $_POST["busca_titulo_like"] : ""), 6, "", "maxlength='150'"),
			combo("Destino", "busca_destino", (!empty($_POST["busca_destino"]) ? $_POST["busca_destino"] : ""), 3, $destinos),
			campo_hidden("busca_status", "ativo")
		];

		$botoes = [
			botao("Buscar", "index"),
			botao("Inserir", "layout_comunicado", "", "", "", "", "btn btn-success")
		];

		echo abre_form();
		echo linha_form($campos);
		echo fecha_form($botoes);

		$gridFields = [
			"CÓDIGO" => "comu_nb_id",
			"TÍTULO" => "comu_tx_titulo",
			"DESTINO" => "comu_tx_destino",
			"CADASTRO" => "comu_tx_dataCadastro"
		];

		$camposBusca = [
			"busca_codigo" => "comu_nb_id",
			"busca_titulo_like" => "comu_tx_titulo",
			"busca_destino" => "comu_tx_destino",
			"busca_status" => "comu_tx_status"
		];

		$queryBase = ("SELECT ".implode(", ", array_values($gridFields))." FROM comunicados");

        $actions = criarIconesGrid(
            ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-trash search-remove"],
            ["cadastro_comunicado.php", "cadastro_comunicado.php"],
            ["modificarComunicado()", "excluirComunicado()"]
        );

		$gridFields["actions"] = $actions["tags"];

		$jsFunctions =
			"const funcoesInternas = function(){".
				implode(" ", $actions["functions"]).
			"}";

		echo gridDinamico("tabelaComunicados", $gridFields, $camposBusca, $queryBase, $jsFunctions);

		rodape();
	}

	index();
?>
