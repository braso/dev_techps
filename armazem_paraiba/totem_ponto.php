<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/

	// Totem de registro de ponto por biometria facial.
	// Página pública (sem login) da empresa. O funcionário escolhe o tipo de registro
	// nos 4 botões, a face identifica quem é e o ponto é gravado com a distância apurada.
	// Quem tiver o perfil marcado com "Registrar Ponto com Facial" pode usar o totem.

	$interno = true;
	define("NO_CONNECTION", true);
	include_once __DIR__."/funcoes_ponto.php";
	include_once __DIR__."/check_permission.php";
	include_once __DIR__."/conecta.php";

	function totemMacros(): array{
		return [
			1 => ["nome" => "Início Jornada"],
			3 => ["nome" => "Início Almoço"],
			4 => ["nome" => "Fim Almoço"],
			2 => ["nome" => "Fim Jornada"],
		];
	}

	function totemMensagemErro(string $msg): string{
		$mapa = [
			"Jornada aberta não encontrada." 					=> "Você não tem jornada aberta. Toque em INÍCIO JORNADA.",
			"Jornada aberta já existente." 						=> "Você já tem uma jornada aberta. Toque em FIM JORNADA ou solicite um ajuste.",
			"Intervalo aberto não encontrado." 					=> "Você não tem almoço aberto. Toque em INÍCIO ALMOÇO primeiro.",
			"Não é possível fechar com um intervalo aberto." 	=> "Você tem um intervalo aberto. Finalize-o antes de encerrar a jornada.",
			"Não é possível abrir com outro intervalo aberto." 	=> "Você já tem um intervalo aberto. Finalize-o primeiro.",
			"Não é possível fechar com outro intervalo aberto." => "Finalize o intervalo aberto antes de continuar.",
			"Funcionário não encontrado." 						=> "Cadastro do funcionário não localizado. Procure o RH.",
			"Macro não encontrada." 							=> "Tipo de registro indisponível. Procure o RH.",
		];
		return $mapa[$msg] ?? "Não foi possível registrar. Procure o RH.";
	}

	function index(){
		$modelUrl = $_ENV["URL_BASE"].$_ENV["APP_PATH"]."/face_models";
		$loginUrl = $_ENV["URL_BASE"].$_ENV["APP_PATH"]."/index.php?origem=totem";
		?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title>Registro de Ponto</title>
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<style>
	* { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
	html, body { margin:0; padding:0; height:100%; overflow:hidden; font-family: Arial, Helvetica, sans-serif; background:#0f172a; color:#fff; user-select:none; }
	.tela { position:fixed; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; }

	/* Tela ociosa */
	#tela-ociosa { cursor:pointer; text-align:center; background:linear-gradient(160deg,#0f172a,#1e293b); }
	#relogio { font-size:clamp(56px, 12vw, 100px); font-weight:800; letter-spacing:4px; line-height:1; }
	#data { font-size:26px; color:#94a3b8; margin-top:10px; text-transform:capitalize; }
	#toque { margin-top:70px; font-size:24px; font-weight:700; color:#38bdf8; animation:pisca 1.6s infinite; }
	@keyframes pisca { 0%,100%{opacity:1} 50%{opacity:.35} }

	/* Login de fallback (apos 3 falhas) */
	#login-fallback { display:none; margin-top:40px; }
	#login-fallback a { display:inline-block; background:#334155; color:#fff; text-decoration:none; font-size:20px; font-weight:700; padding:14px 28px; border-radius:12px; border:2px solid #475569; }

	/* Câmera */
	#modal-camera { display:none; background:rgba(2,6,23,.94); z-index:20; }
	#video-wrap { position:relative; width:min(86vw, 640px); }
	#video { width:100%; border-radius:18px; background:#000; transform:scaleX(-1); }
	#canvas { position:absolute; inset:0; width:100%; height:100%; transform:scaleX(-1); pointer-events:none; }
	#status-camera { margin-top:18px; font-size:24px; font-weight:700; color:#38bdf8; min-height:32px; text-align:center; }
	#btn-cancelar { margin-top:22px; background:transparent; border:2px solid #64748b; color:#cbd5e1; font-size:20px; font-weight:700; padding:12px 30px; border-radius:12px; cursor:pointer; }

	/* Resultado */
	#resultado { display:none; z-index:30; text-align:center; padding:20px; }
	#resultado.ok { background:#14532d; }
	#resultado.erro { background:#7f1d1d; }
	#resultado-icone { width:120px; height:120px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 26px; font-size:70px; background:rgba(255,255,255,.15); }
	#resultado-titulo { font-size:clamp(28px, 4vw, 52px); font-weight:800; }
	#resultado-sub { font-size:clamp(20px, 2.6vw, 34px); margin-top:14px; color:rgba(255,255,255,.92); }
	#resultado-msg { font-size:20px; margin-top:22px; color:rgba(255,255,255,.8); max-width:820px; }
	#resultado .login-link { display:none; margin-top:30px; }
	#resultado .login-link a { display:inline-block; background:#fff; color:#0f172a; text-decoration:none; font-size:22px; font-weight:800; padding:16px 34px; border-radius:14px; }
</style>
</head>
<body>

<div id="tela-ociosa" class="tela">
	<div id="relogio">--:--</div>
	<div id="data"></div>
	<div id="toque">Toque na tela para registrar seu ponto</div>
	<div id="login-fallback"><a href="<?=htmlspecialchars($loginUrl, ENT_QUOTES)?>">ENTRAR COM LOGIN</a></div>
</div>

<div id="modal-camera" class="tela">
	<div id="video-wrap">
		<video id="video" autoplay muted playsinline></video>
		<canvas id="canvas"></canvas>
	</div>
	<div id="status-camera">Posicione seu rosto na câmera...</div>
	<button type="button" id="btn-cancelar">Cancelar</button>
</div>

<div id="resultado" class="tela">
	<div id="resultado-icone">✔</div>
	<div id="resultado-titulo"></div>
	<div id="resultado-sub"></div>
	<div id="resultado-msg"></div>
	<div class="login-link"><a href="<?=htmlspecialchars($loginUrl, ENT_QUOTES)?>">ENTRAR COM LOGIN</a></div>
</div>

<script>
(function(){
	const MODEL_URL = <?=json_encode($modelUrl)?>;
	const ENDPOINT  = 'totem_ponto.php';

	const telaOciosa  = document.getElementById('tela-ociosa');
	const modalCamera = document.getElementById('modal-camera');
	const resultadoEl = document.getElementById('resultado');
	const videoEl     = document.getElementById('video');
	const canvasEl    = document.getElementById('canvas');
	const statusEl    = document.getElementById('status-camera');
	const loginFallback = document.getElementById('login-fallback');

	let modelsLoaded = false;
	let stream = null;
	let detLoop = null;
	let processando = false;
	let identificando = false;
	let falhas = 0;
	let cameraTimer = null;
	let resultadoTimer = null;

	// ── Relógio ─────────────────────────────────────────────────────────
	function atualizarRelogio(){
		const now = new Date();
		const h = String(now.getHours()).padStart(2,'0');
		const m = String(now.getMinutes()).padStart(2,'0');
		const s = String(now.getSeconds()).padStart(2,'0');
		document.getElementById('relogio').textContent = h+':'+m+':'+s;
		const dias  = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
		const meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
		document.getElementById('data').textContent = dias[now.getDay()]+', '+now.getDate()+' de '+meses[now.getMonth()]+' de '+now.getFullYear();
	}
	atualizarRelogio();
	setInterval(atualizarRelogio, 1000);

	// ── Navegação entre telas ───────────────────────────────────────────
	function mostrarOciosa(){
		pararCamera();
		telaOciosa.style.display = 'flex';
		modalCamera.style.display = 'none';
		resultadoEl.style.display = 'none';
		loginFallback.style.display = (falhas >= 3)? 'block': 'none';
	}

	telaOciosa.addEventListener('click', function(e){
		if(e.target.closest('#login-fallback')){ return; }
		iniciarFacial();
	});

	// ── Câmera e reconhecimento ─────────────────────────────────────────
	async function iniciarFacial(){
		identificando = false;
		processando = false;
		if(cameraTimer){ clearTimeout(cameraTimer); }
		cameraTimer = setTimeout(function(){
			if(modalCamera.style.display === 'flex' && !identificando){
				statusEl.textContent = 'Tempo esgotado. Toque para tentar novamente.';
				mostrarOciosa();
			}
		}, 45000);
		modalCamera.style.display = 'flex';
		resultadoEl.style.display = 'none';
		statusEl.style.color = '#38bdf8';
		statusEl.textContent = 'Carregando reconhecimento facial...';

		if(typeof faceapi === 'undefined'){
			finalizarFalha('Não foi possível carregar o reconhecimento facial. Verifique a internet.', false);
			return;
		}
		if(!modelsLoaded){
			try {
				await Promise.all([
					faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
					faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
					faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
				]);
				modelsLoaded = true;
			} catch(e) {
				finalizarFalha('Não foi possível carregar o reconhecimento facial.', false);
				return;
			}
		}
		iniciarCamera();
	}

	async function iniciarCamera(){
		try {
			stream = await navigator.mediaDevices.getUserMedia({ video: { width: 480, height: 360, facingMode: 'user' } });
			videoEl.srcObject = stream;
			videoEl.onloadedmetadata = function(){
				statusEl.textContent = 'Posicione seu rosto na câmera...';
				iniciarLoop();
			};
		} catch(e) {
			finalizarFalha('Câmera indisponível. Chame o responsável.', false);
		}
	}

	function pararCamera(){
		if(cameraTimer){ clearTimeout(cameraTimer); cameraTimer = null; }
		if(detLoop){ clearInterval(detLoop); detLoop = null; }
		if(stream){ stream.getTracks().forEach(function(t){ t.stop(); }); stream = null; }
	}

	let _proc = false;
	let _ultimoNariz = null, _movAcumulado = 0, _framesAnalisados = 0;
	const MOV_FRAMES_MIN = 8, MOV_VARIANCIA_MIN = 0.4;

	function resetarMovimento(){ _ultimoNariz = null; _movAcumulado = 0; _framesAnalisados = 0; }
	function registrarMovimento(landmarks){
		const pts = landmarks.positions;
		const nariz = { x: pts[30].x, y: pts[30].y };
		if(_ultimoNariz){
			const dx = nariz.x - _ultimoNariz.x;
			const dy = nariz.y - _ultimoNariz.y;
			_movAcumulado += Math.sqrt(dx*dx + dy*dy);
		}
		_ultimoNariz = nariz;
		_framesAnalisados++;
	}
	function rostoEhReal(){
		if(_framesAnalisados < MOV_FRAMES_MIN) return null;
		return (_movAcumulado / _framesAnalisados) >= MOV_VARIANCIA_MIN;
	}

	function iniciarLoop(){
		if(detLoop) clearInterval(detLoop);
		resetarMovimento();
		detLoop = setInterval(async function(){
			if(_proc || identificando || videoEl.readyState < 2) return;
			_proc = true;
			const ctx = canvasEl.getContext('2d');
			const vW = videoEl.videoWidth || videoEl.offsetWidth;
			const vH = videoEl.videoHeight || videoEl.offsetHeight;
			if(canvasEl.width !== vW || canvasEl.height !== vH){ canvasEl.width = vW; canvasEl.height = vH; }

			const det = await faceapi
				.detectSingleFace(videoEl, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.3 }))
				.withFaceLandmarks()
				.withFaceDescriptor();

			ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);

			if(!det){
				statusEl.style.color = '#94a3b8';
				statusEl.textContent = 'Posicione seu rosto na câmera...';
				resetarMovimento();
				_proc = false;
				return;
			}

			registrarMovimento(det.landmarks);
			const ehReal = rostoEhReal();
			const b = det.detection.box;
			ctx.strokeStyle = ehReal === false ? '#ef4444' : (ehReal ? '#22c55e' : '#38bdf8');
			ctx.lineWidth = 3;
			ctx.strokeRect(b.x, b.y, b.width, b.height);

			if(ehReal === false){
				statusEl.style.color = '#ef4444';
				statusEl.textContent = 'Foto detectada. Use seu rosto real.';
				_proc = false;
				return;
			}
			if(ehReal === null || det.detection.score < 0.85){
				statusEl.style.color = '#38bdf8';
				statusEl.textContent = 'Mantenha o rosto na câmera...';
				_proc = false;
				return;
			}

			statusEl.style.color = '#22c55e';
			statusEl.textContent = 'Confirmando...';
			identificando = true;
			clearInterval(detLoop);
			detLoop = null;
			_proc = false;
			enviarDescritor(det.descriptor);
		}, 300);
	}

	async function enviarDescritor(descriptor){
		const fd = new FormData();
		fd.append('acao', 'registrarPontoFacial');
		fd.append('descritor', JSON.stringify(Array.from(descriptor)));
		try {
			const res = await fetch(ENDPOINT, { method: 'POST', body: fd });
			const json = await res.json();
			if(json.ok){
				falhas = 0;
				loginFallback.style.display = 'none';
				finalizarSucesso(json);
			}else{
				finalizarFalha(json.msg || 'Não foi possível registrar.', !!json.login);
			}
		} catch(e) {
			finalizarFalha('Falha de comunicação. Tente novamente.', false);
		}
	}

	function finalizarSucesso(json){
		pararCamera();
		const msg = json.aviso || 'Registro efetuado com sucesso.';
		mostrarResultado('ok', '✔', json.nome || 'Registro efetuado', (json.macro || '') + ' às ' + (json.hora || ''), msg, false);
		resultadoTimer = setTimeout(mostrarOciosa, json.aviso ? 9000 : 6000);
	}

	function finalizarFalha(msg, exigirLogin){
		pararCamera();
		falhas++;
		const comLogin = exigirLogin || falhas >= 3;
		mostrarResultado('erro', '✕', 'Não reconhecido', msg, comLogin ? 'Após 3 tentativas, use o login para registrar.' : ('Tentativa ' + falhas + ' de 3.'), comLogin);
		resultadoTimer = setTimeout(mostrarOciosa, 5000);
	}

	function mostrarResultado(tipo, icone, titulo, sub, msg, comLogin){
		if(resultadoTimer) clearTimeout(resultadoTimer);
		resultadoEl.className = 'tela ' + tipo;
		document.getElementById('resultado-icone').textContent = icone;
		document.getElementById('resultado-titulo').textContent = titulo;
		document.getElementById('resultado-sub').textContent = sub;
		document.getElementById('resultado-msg').textContent = msg;
		resultadoEl.querySelector('.login-link').style.display = comLogin ? 'block' : 'none';
		resultadoEl.style.display = 'flex';
	}

	document.getElementById('btn-cancelar').addEventListener('click', mostrarOciosa);
})();
</script>
</body>
</html>
		<?php
	}

	function registrarPontoFacial(){
		global $conn;

		header("Content-Type: application/json; charset=utf-8");
		header("Cache-Control: no-store");

		$responder = function(array $dados){
			echo json_encode($dados, JSON_UNESCAPED_UNICODE);
			exit;
		};

		try {
			$descritorRaw = trim(strval($_POST["descritor"] ?? ""));
			$idMacro = intval($_POST["idMacro"] ?? 0);
			$macros = totemMacros();

			if($descritorRaw === "" || ($idMacro > 0 && !isset($macros[$idMacro]))){
				$responder(["ok" => false, "msg" => "Dados inválidos. Tente novamente."]);
			}

			$descritor = json_decode($descritorRaw, true);
			if(!is_array($descritor) || count($descritor) < 64){
				$responder(["ok" => false, "msg" => "Leitura facial inválida. Tente novamente."]);
			}

			$THRESHOLD = 0.38;

			// A identificação não depende de outras tabelas: só o usuário e a entidade.
			$rs = query(
				"SELECT u.user_nb_id, u.user_tx_nome, u.user_tx_face_descriptor,
						e.enti_tx_matricula, e.enti_nb_parametro
					FROM user u
					JOIN entidade e ON e.enti_nb_id = u.user_nb_entidade
					WHERE u.user_tx_status = 'ativo'
						AND e.enti_tx_status = 'ativo'
						AND u.user_tx_face_descriptor IS NOT NULL
						AND u.user_tx_face_descriptor != ''
					LIMIT 5000"
			);
			if($rs === false || $rs === true){
				error_log("Totem facial: falha ao consultar biometrias - ".strval($GLOBALS["last_sql_error"] ?? "erro desconhecido"));
				$responder(["ok" => false, "msg" => "Erro interno ao consultar biometrias. Procure o RH."]);
			}

			$melhorUsuario = null;
			$melhorDistancia = PHP_FLOAT_MAX;
			$comparados = 0;
			while($row = mysqli_fetch_assoc($rs)){
				$descBanco = json_decode(strval($row["user_tx_face_descriptor"]), true);
				if(!is_array($descBanco) || count($descBanco) !== count($descritor)){ continue; }
				$comparados++;
				$soma = 0.0;
				foreach($descBanco as $i => $v){
					$diff = floatval($v) - floatval($descritor[$i] ?? 0);
					$soma += $diff * $diff;
				}
				$dist = sqrt($soma);
				if($dist < $melhorDistancia){
					$melhorDistancia = $dist;
					$melhorUsuario = $row;
				}
			}

			if(empty($melhorUsuario) || $melhorDistancia > $THRESHOLD){
				$responder([
					"ok" => false,
					"msg" => "Rosto não reconhecido. Posicione o rosto e tente novamente.",
					"distancia" => empty($melhorUsuario)? null: round($melhorDistancia, 4),
					"comparados" => $comparados
				]);
			}

			if(!perfilUsaPontoFacial(intval($melhorUsuario["user_nb_id"]))){
				$responder([
					"ok" => false,
					"login" => true,
					"msg" => "Seu perfil não está habilitado para o registro facial. Use o login."
				]);
			}

			$matricula = $melhorUsuario["enti_tx_matricula"];

			$ultimoRegistro = mysqli_fetch_assoc(query(
				"SELECT pont_tx_dataCadastro FROM ponto
					WHERE pont_tx_matricula = ?
					ORDER BY pont_nb_id DESC
					LIMIT 1",
				"s",
				[$matricula]
			));
			if(!empty($ultimoRegistro["pont_tx_dataCadastro"]) && (time() - strtotime($ultimoRegistro["pont_tx_dataCadastro"])) < 30){
				$responder(["ok" => false, "msg" => "Registro efetuado há instantes. Aguarde alguns segundos."]);
			}

			// Janela de almoço e regra de nova jornada do parâmetro (colunas garantidas pelo
			// conecta.php). Se por algum motivo uma coluna não existir, assume o padrão em
			// vez de derrubar a identificação.
			$almocoInicio = null;
			$almocoFim = null;
			$bloquearJornadaAnterior = false;
			$parametroId = intval($melhorUsuario["enti_nb_parametro"] ?? 0);
			if($parametroId > 0){
				$colunasParametro = ["para_tx_almocoInicio", "para_tx_almocoFim", "para_tx_permitirNovaJornadaDiaAnterior"];
				$selectParametro = [];
				foreach($colunasParametro as $colunaParametro){
					if(bancoColunaExiste($conn, "parametro", $colunaParametro)){
						$selectParametro[] = $colunaParametro;
					}
				}
				if(!empty($selectParametro)){
					$parametroJornada = mysqli_fetch_assoc(query(
						"SELECT ".implode(", ", $selectParametro)." FROM parametro WHERE para_nb_id = ? LIMIT 1",
						"i",
						[$parametroId]
					));
					$almocoInicio = $parametroJornada["para_tx_almocoInicio"] ?? null;
					$almocoFim = $parametroJornada["para_tx_almocoFim"] ?? null;
					$bloquearJornadaAnterior = (($parametroJornada["para_tx_permitirNovaJornadaDiaAnterior"] ?? "sim") === "nao");
				}
			}
			$dataPonto = new DateTime(date("Y-m-d H:i:s"));
			$avisoSucesso = null;

			// Sem tipo escolhido, o servidor decide o próximo registro automaticamente.
			if(!isset($macros[$idMacro])){
				$proximo = proximoMacroTotem($matricula, $dataPonto, $almocoInicio, $almocoFim, $bloquearJornadaAnterior);
				if(empty($proximo["macro"])){
					$mensagensProximo = [
						"intervalo_aberto" => "Você tem um intervalo em aberto (Espera/Descanso/Repouso). Procure o RH para ajustar.",
						"jornada_anterior_bloqueada" => "Você tem uma jornada de outro dia em aberto. Procure o gestor para ajustar."
					];
					$responder([
						"ok" => false,
						"msg" => $mensagensProximo[$proximo["motivo"]] ?? "Não foi possível identificar o próximo registro. Procure o RH."
					]);
				}
				if($proximo["motivo"] === "nova_jornada_dia_seguinte"){
					$avisoSucesso = "Atenção: você tinha uma jornada de outro dia em aberto. Ela ficou para ajuste do gestor.";
				}
				$idMacro = intval($proximo["macro"]);
			}

			$sessaoAnterior = $_SESSION["user_nb_id"] ?? null;
			$_SESSION["user_nb_id"] = intval($melhorUsuario["user_nb_id"]);
			try {
				$novoPonto = conferirErroPonto(
					$matricula,
					$dataPonto,
					$idMacro,
					0,
					"Registro pelo totem facial",
					$bloquearJornadaAnterior
				);
			} catch (Throwable $e) {
				if($sessaoAnterior === null){ unset($_SESSION["user_nb_id"]); } else { $_SESSION["user_nb_id"] = $sessaoAnterior; }
				$responder(["ok" => false, "msg" => totemMensagemErro($e->getMessage())]);
			}
			if($sessaoAnterior === null){ unset($_SESSION["user_nb_id"]); } else { $_SESSION["user_nb_id"] = $sessaoAnterior; }

			$novoPonto["pont_tx_descricao"] = "Facial";
			$novoPonto["pont_tx_faceDistancia"] = round($melhorDistancia, 4);

			$resultado = inserir("ponto", array_keys($novoPonto), array_values($novoPonto));
			if(!empty($resultado[0]) && is_object($resultado[0])){
				$responder(["ok" => false, "msg" => "Não foi possível gravar o registro. Procure o RH."]);
			}

			$responder([
				"ok" => true,
				"nome" => $melhorUsuario["user_tx_nome"],
				"macro" => $macros[$idMacro]["nome"],
				"hora" => $dataPonto->format("H:i"),
				"aviso" => $avisoSucesso,
				"msg" => "Registro efetuado com sucesso."
			]);
		} catch (Throwable $e) {
			$responder(["ok" => false, "msg" => "Erro inesperado. Procure o RH."]);
		}
	}
