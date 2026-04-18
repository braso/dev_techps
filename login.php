<?php
// Debug para produção - REMOVER APÓS CORREÇÃO
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
?>
<!DOCTYPE html>
<!--[if IE 8]> <html lang="en" class="ie8 no-js"> <![endif]-->
<!--[if IE 9]> <html lang="en" class="ie9 no-js"> <![endif]-->
<html lang="pt-BR">
<head>
	<meta charset="utf-8" />
	<title>TechPS</title>
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta content="width=device-width, initial-scale=1" name="viewport" />
	<link href="https://fonts.googleapis.com/css?family=Open+Sans:400,300,600,700&subset=all" rel="stylesheet" type="text/css" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/simple-line-icons/simple-line-icons.min.css" rel="stylesheet" type="text/css" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/uniform/css/uniform.default.css" rel="stylesheet" type="text/css" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap-switch/css/bootstrap-switch.min.css" rel="stylesheet" type="text/css" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/css/select2-bootstrap.min.css" rel="stylesheet" type="text/css" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/css/components.min.css" rel="stylesheet" id="style_components" type="text/css" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/css/plugins.min.css" rel="stylesheet" type="text/css" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/pages/css/login.min.css" rel="stylesheet" type="text/css" />
	<link rel="apple-touch-icon" sizes="180x180" href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/favicon/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/favicon/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/favicon/favicon-16x16.png">
	<link rel="shortcut icon" type="image/x-icon" href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/favicon/favicon-32x32.png?v=2">
	<link rel="manifest" href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/favicon/site.webmanifest">
</head>
<body class="login">

	<!-- Botão Modo Totem — canto superior esquerdo, fora do form -->
	<button id="btn-totem" style="position:fixed;top:16px;right:16px;background:transparent;border:1px solid rgba(255,255,255,.5);color:rgba(255,255,255,.8);border-radius:6px;padding:7px 14px;font-size:12px;letter-spacing:.5px;cursor:pointer;z-index:100;transition:all .2s;" onmouseover="this.style.background='rgba(255,255,255,.1)'" onmouseout="this.style.background='transparent'">
		<i class="fa fa-desktop"></i> Modo Totem
	</button>

	<div class="logo">
		<a href="https://techps.com.br/">
			<img src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/logo.png" alt="" />
		</a>
	</div>

	<div class="content">
		<form class="login-form" method="post">
			<h3 class="form-title font-blue">Login <?=(is_int(strpos($_SERVER["REQUEST_URI"], "dev"))? "(Dev)": "")?></h3>
			<?=$empresasInput?>
			<div class="form-group">
				<input class="form-control form-control-solid placeholder-no-fix" type="text" autocomplete="off" placeholder="Usuário" name="user" <?=(!empty($_POST["user"])? "value=".$_POST["user"]: "")?> />
			</div>
			<div class="form-group">
				<input class="form-control form-control-solid placeholder-no-fix" type="password" autocomplete="off" placeholder="Senha" name="password" />
			</div>
			<div style="display:flex; align-items:center; width:100%; justify-content:space-between; margin-top:10px">
				<label style="display:flex; align-items:center; gap:12px; margin:0; white-space:nowrap; flex-shrink:0">
					<input type="checkbox" name="remember" />
					<span>Lembre-me</span>
				</label>
				<a href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/recupera_senha.php"?>" id="forget-password" class="forget-password">Esqueceu sua senha?</a>
			</div>
			<?=(!empty($_POST["sourcePage"]) ? "<input type='hidden' name='sourcePage' value='".$_POST["sourcePage"]."'/>" : "")?>
			<?= $msg ?>
			<div class="form-actions" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
				<input type="submit" class="btn blue uppercase" name="botao" value="Entrar">
				<button type="button" id="btn-face-login" class="btn btn-default uppercase" title="Entrar com reconhecimento facial">
					<i class="fa fa-eye"></i> Biometria Facial
				</button>
			</div>

			<!-- Modal reconhecimento facial -->
			<div id="modal-face" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center;">
				<div style="background:#fff; border-radius:10px; padding:24px; max-width:520px; width:95%; text-align:center; position:relative;">
					<button onclick="fecharModalFace()" style="position:absolute;top:10px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;">✕</button>
					<h4 style="margin-bottom:4px;"><i class="fa fa-eye"></i> Reconhecimento Facial</h4>
					<p id="face-login-status" style="color:#888; font-size:13px; min-height:18px;">Carregando modelos de IA...</p>
					<div style="position:relative; display:inline-block; width:100%; max-width:460px;">
						<video id="face-login-video" autoplay muted playsinline style="border-radius:8px; border:3px solid #ddd; display:block; width:100%; height:auto;"></video>
						<canvas id="face-login-canvas" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;border-radius:8px;"></canvas>
					</div>
					<div id="face-login-resultado" style="margin-top:12px; min-height:36px;"></div>
				</div>
			</div>

			<p style="font-size:small; margin:10px 0px">Versão: <?= $version; ?><br>Data de lançamento: <?= $release_date; ?></p>
		</form>
	</div>

	<div class="copyright"><?= date("Y") ?> © TechPS.</div>

	<!-- ══ MODO TOTEM ══════════════════════════════════════════════════════════ -->
	<!-- Modal configuração do totem -->
	<div id="modal-totem-config" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);z-index:10000;align-items:center;justify-content:center;">
		<div style="background:#fff;border-radius:12px;padding:32px;max-width:420px;width:90%;text-align:center;">
			<h4 style="margin-bottom:20px"><i class="fa fa-desktop"></i> Configurar Modo Totem</h4>
			<div style="margin-bottom:16px;text-align:left">
				<label style="font-size:13px;color:#666;margin-bottom:6px;display:block">Empresa</label>
				<select id="totem-empresa-sel" class="form-control">
					<option value="">— Selecione a empresa —</option>
					<?php foreach($empresas as $key => $dir): if(!file_exists(__DIR__."/".$dir)) continue; ?>
					<option value="<?= $key ?>"><?= $empresasNomes[$dir] ?? $key ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div style="display:flex;gap:10px;justify-content:center;margin-top:8px">
				<button onclick="document.getElementById('modal-totem-config').style.display='none'" class="btn btn-default">Cancelar</button>
				<button onclick="ativarTotem()" class="btn btn-primary"><i class="fa fa-desktop"></i> Ativar Totem</button>
			</div>
		</div>
	</div>

	<!-- Tela Totem -->
	<div id="tela-totem" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);z-index:9998;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;user-select:none;">

		<!-- Canvas de partículas -->
		<canvas id="totem-particles" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;"></canvas>

		<!-- Botão sair (discreto, canto superior direito) -->
		<button onclick="sairTotem(event)" style="position:absolute;top:16px;right:16px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:rgba(255,255,255,.5);border-radius:6px;padding:6px 12px;font-size:11px;cursor:pointer;" title="Sair do modo totem">
			<i class="fa fa-times"></i> Sair
		</button>

		<!-- Logo -->
		<img src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/logo.png" style="height:48px;opacity:.8;margin-bottom:32px;" alt="Logo">

		<!-- Relógio -->
		<div id="totem-hora" style="font-size:96px;font-weight:700;color:#fff;letter-spacing:-2px;line-height:1;font-family:'Open Sans',sans-serif;">00:00</div>
		<div id="totem-data" style="font-size:22px;color:rgba(255,255,255,.7);margin-top:8px;margin-bottom:48px;font-family:'Open Sans',sans-serif;"></div>

		<!-- Empresa -->
		<div id="totem-empresa-nome" style="font-size:18px;color:rgba(255,255,255,.5);margin-bottom:64px;letter-spacing:1px;text-transform:uppercase;"></div>

		<!-- CTA -->
		<div style="display:flex;flex-direction:column;align-items:center;gap:12px;">
			<div style="width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.1);border:2px solid rgba(255,255,255,.3);display:flex;align-items:center;justify-content:center;animation:totem-pulse 2s infinite;">
				<i class="fa fa-hand-pointer-o" style="font-size:32px;color:#fff;"></i>
			</div>
			<div style="font-size:20px;color:rgba(255,255,255,.8);font-weight:300;letter-spacing:2px;">TOQUE PARA FAZER LOGIN</div>
		</div>

		<!-- Câmera facial inline no totem -->
		<div id="totem-cam-area" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.85);z-index:10001;flex-direction:column;align-items:center;justify-content:center;">
			<p id="totem-status" style="color:#fff;font-size:16px;margin-bottom:16px;min-height:24px;">Posicione seu rosto na câmera...</p>
			<div style="position:relative;display:inline-block;max-width:480px;width:90%;">
				<video id="totem-video" autoplay muted playsinline style="border-radius:12px;display:block;width:100%;height:auto;border:3px solid rgba(255,255,255,.3);"></video>
				<canvas id="totem-canvas" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;border-radius:12px;"></canvas>
			</div>
			<div id="totem-resultado" style="margin-top:16px;min-height:40px;text-align:center;"></div>
			<button onclick="fecharCamTotem()" style="margin-top:20px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;border-radius:8px;padding:10px 24px;font-size:14px;cursor:pointer;">
				<i class="fa fa-times"></i> Cancelar
			</button>
		</div>
	</div>

	<style>
	@keyframes totem-pulse {
		0%,100%{ transform:scale(1); box-shadow:0 0 0 0 rgba(255,255,255,.3); }
		50%    { transform:scale(1.05); box-shadow:0 0 0 16px rgba(255,255,255,0); }
	}
	</style>

	<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/jquery.min.js" type="text/javascript"></script>
	<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
	<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/js.cookie.min.js" type="text/javascript"></script>
	<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js" type="text/javascript"></script>
	<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js" type="text/javascript"></script>
	<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/jquery.blockui.min.js" type="text/javascript"></script>
	<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/uniform/jquery.uniform.min.js" type="text/javascript"></script>

	<?=$dataScript?>

	<!-- Lembre-me -->
	<script>
	(function(){
		function setCookie(n,v,d){var t=new Date();t.setTime(t.getTime()+(d*24*60*60*1000));document.cookie=n+"="+encodeURIComponent(v)+";expires="+t.toUTCString()+";path=/"}
		function getCookie(n){var m=("; "+document.cookie).split("; "+n+"=");if(m.length===2) return decodeURIComponent(m.pop().split(";").shift());return ""}
		var form=document.querySelector('.login-form');
		if(!form) return;
		var emp=form.querySelector('[name="empresa"]');
		var rem=form.querySelector('input[name="remember"]');
		var remembered=(localStorage.getItem('remember_me')==='1' || getCookie('remember_me')==='1');
		if(remembered){
			if(rem) rem.checked=true;
			var le=localStorage.getItem('remember_empresa');
			if(emp && le) { try { emp.value=le; } catch(e){} }
		}
		form.addEventListener('submit',function(){
			var checked=rem && rem.checked;
			if(checked){
				if(emp) localStorage.setItem('remember_empresa', emp.value||'');
				localStorage.setItem('remember_me','1');
				setCookie('remember_me','1',180);
			}else{
				localStorage.removeItem('remember_empresa');
				localStorage.removeItem('remember_me');
				setCookie('remember_me','',-1);
			}
		});
	})();
	</script>

	<!-- Biometria Facial -->
	<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
	<script>
	(function(){
		const MODEL_URL = '<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/face_models';
		const ENDPOINT  = '<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/login_facial.php';

		let modelsLoaded = false;
		let stream       = null;
		let detLoop      = null;
		let autenticando = false;

		const btnFace     = document.getElementById('btn-face-login');
		const modal       = document.getElementById('modal-face');
		const videoEl     = document.getElementById('face-login-video');
		const canvasEl    = document.getElementById('face-login-canvas');
		const statusEl    = document.getElementById('face-login-status');
		const resultadoEl = document.getElementById('face-login-resultado');
		const loginForm   = document.querySelector('.login-form');

		if (!btnFace) return;

		btnFace.addEventListener('click', abrirModal);

		async function abrirModal() {
			modal.style.display = 'flex';
			resultadoEl.innerHTML = '';
			autenticando = false;
			statusEl.style.color = '#888';
			statusEl.textContent = 'Carregando modelos de IA...';
			if (!modelsLoaded) {
				try {
					await Promise.all([
						faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
						faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
						faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
					]);
					modelsLoaded = true;
				} catch(e) {
					statusEl.textContent = 'Erro ao carregar modelos.';
					return;
				}
			}
			iniciarCamera();
		}

		window.fecharModalFace = function() {
			modal.style.display = 'none';
			pararCamera();
			autenticando = false;
			resetarMovimento();
		};

		async function iniciarCamera() {
			try {
				stream = await navigator.mediaDevices.getUserMedia({ video: { width: 420, height: 315, facingMode: 'user' } });
				videoEl.srcObject = stream;
				videoEl.onloadedmetadata = () => {
					statusEl.textContent = 'Posicione seu rosto na câmera...';
					iniciarLoop();
				};
			} catch(e) {
				statusEl.textContent = 'Câmera indisponível: ' + e.message;
			}
		}

		function pararCamera() {
			if (detLoop) clearInterval(detLoop);
			if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
		}

		let _proc = false;

		// ── Anti-spoofing: detecção de micro-movimento ──────────────────────
		// Rosto real tem tremor natural (respiração, micro-expressões).
		// Foto/tela é completamente estática — landmarks não variam.
		// Acumula variância da posição do nariz entre frames consecutivos.
		let _ultimoNariz    = null; // {x, y} do frame anterior
		let _movAcumulado   = 0;    // soma de variações detectadas
		let _framesAnalisados = 0;
		const MOV_FRAMES_MIN  = 8;   // mínimo de frames para avaliar
		const MOV_VARIANCIA_MIN = 0.4; // variância mínima acumulada (pixels médios por frame)
		// Uma foto tem variância ~0.0, rosto real tem ~0.5–3.0

		function resetarMovimento() {
			_ultimoNariz = null;
			_movAcumulado = 0;
			_framesAnalisados = 0;
		}

		function registrarMovimento(landmarks) {
			const pts  = landmarks.positions;
			const nariz = { x: pts[30].x, y: pts[30].y };
			if (_ultimoNariz) {
				const dx = nariz.x - _ultimoNariz.x;
				const dy = nariz.y - _ultimoNariz.y;
				_movAcumulado += Math.sqrt(dx*dx + dy*dy);
			}
			_ultimoNariz = nariz;
			_framesAnalisados++;
		}

		function rostoEhReal() {
			if (_framesAnalisados < MOV_FRAMES_MIN) return null; // ainda avaliando
			const media = _movAcumulado / _framesAnalisados;
			return media >= MOV_VARIANCIA_MIN;
		}
		// ────────────────────────────────────────────────────────────────────

		function iniciarLoop() {
			if (detLoop) clearInterval(detLoop);
			resetarMovimento();
			const ctx = canvasEl.getContext('2d');
			detLoop = setInterval(async () => {
				if (_proc || autenticando || videoEl.readyState < 2) return;
				_proc = true;

				// Sincroniza canvas com dimensões internas reais do vídeo
				const vW = videoEl.videoWidth  || videoEl.offsetWidth;
				const vH = videoEl.videoHeight || videoEl.offsetHeight;
				if (canvasEl.width !== vW || canvasEl.height !== vH) {
					canvasEl.width  = vW;
					canvasEl.height = vH;
				}

				const det = await faceapi
					.detectSingleFace(videoEl, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.3 }))
					.withFaceLandmarks()
					.withFaceDescriptor();

				ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);

				if (!det) {
					statusEl.style.color = '#888';
					statusEl.textContent = 'Posicione seu rosto na câmera...';
					resultadoEl.innerHTML = '';
					resetarMovimento();
					_proc = false;
					return;
				}

				// Registra movimento deste frame
				registrarMovimento(det.landmarks);
				const ehReal = rostoEhReal();

				// Qualidade do detector
				const detScore  = det.detection.score;
				const qualidade = Math.round(detScore * 100);
				const corQ      = detScore >= 0.90 ? '#27ae60' : detScore >= 0.75 ? '#e67e22' : '#e74c3c';
				const labelQ    = detScore >= 0.90 ? 'Ótima' : detScore >= 0.75 ? 'Boa' : 'Fraca';

				// Progresso da verificação de vivacidade
				const progMov   = Math.min(_framesAnalisados / MOV_FRAMES_MIN * 100, 100);
				const corMov    = ehReal === null ? '#3c8dbc' : (ehReal ? '#27ae60' : '#e74c3c');
				const labelMov  = ehReal === null
					? 'Verificando presença real... ' + Math.round(progMov) + '%'
					: (ehReal ? '✔ Presença confirmada' : '✗ Foto detectada — use o rosto real');

				resultadoEl.innerHTML =
					'<div style="font-size:11px;color:#888;margin-bottom:3px">Qualidade do rosto</div>' +
					'<div style="background:#eee;border-radius:4px;height:6px;overflow:hidden;margin-bottom:6px">' +
						'<div style="height:100%;width:'+qualidade+'%;background:'+corQ+';border-radius:4px;transition:width .2s"></div>' +
					'</div>' +
					'<div style="font-size:11px;color:#888;margin-bottom:3px">Verificação de presença real</div>' +
					'<div style="background:#eee;border-radius:4px;height:6px;overflow:hidden">' +
						'<div style="height:100%;width:'+progMov+'%;background:'+corMov+';border-radius:4px;transition:width .3s"></div>' +
					'</div>' +
					'<div style="font-size:12px;color:'+corMov+';margin-top:4px;font-weight:600">'+labelMov+'</div>';

				const b = det.detection.box;
				ctx.strokeStyle = ehReal === false ? '#e74c3c' : (ehReal ? '#27ae60' : '#3c8dbc');
				ctx.lineWidth   = 3;
				ctx.shadowColor = ctx.strokeStyle;
				ctx.shadowBlur  = 8;
				ctx.strokeRect(b.x, b.y, b.width, b.height);
				ctx.shadowBlur  = 0;

				// Bloqueia se foto detectada
				if (ehReal === false) {
					statusEl.style.color = '#e74c3c';
					statusEl.textContent = '✗ Foto detectada. Use seu rosto real.';
					_proc = false;
					return;
				}

				// Aguarda avaliação completa e qualidade mínima
				if (ehReal === null || detScore < 0.85) {
					statusEl.style.color = '#3c8dbc';
					statusEl.textContent = 'Mantenha o rosto na câmera...';
					_proc = false;
					return;
				}

				statusEl.style.color = '#27ae60';
				statusEl.textContent = '✔ Presença real confirmada — identificando...';
				autenticando = true;
				clearInterval(detLoop);
				_proc = false;
				await autenticar(det.descriptor);
			}, 300);
		}

		async function autenticar(descriptor) {
			const empresaEl  = loginForm ? loginForm.querySelector('[name="empresa"]') : null;
			const empresaVal = empresaEl ? empresaEl.value : '';
			if (!empresaVal) {
				resultadoEl.innerHTML = "<div style='color:#e74c3c;font-size:13px;'>Selecione a empresa antes de usar a biometria.</div>";
				autenticando = false;
				iniciarLoop();
				return;
			}
			statusEl.textContent = 'Consultando servidor...';
			const fd = new FormData();
			fd.append('empresa_key', empresaVal);
			fd.append('descritor', JSON.stringify(Array.from(descriptor)));
			try {
				const res  = await fetch(ENDPOINT, { method: 'POST', body: fd });
				const json = await res.json();
				if (json.ok) {
					pararCamera();
					statusEl.style.color = '#27ae60';
					statusEl.textContent = '✔ ' + json.nome;
					// Substitui a câmera por tela de sucesso verde
					videoEl.parentElement.style.background = '#eafaf1';
					videoEl.style.display = 'none';
					canvasEl.style.display = 'none';
					videoEl.parentElement.innerHTML =
						'<div style="padding:40px 20px;text-align:center;">' +
							'<div style="width:80px;height:80px;border-radius:50%;background:#27ae60;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">' +
								'<i class="fa fa-check" style="font-size:36px;color:#fff;"></i>' +
							'</div>' +
							'<div style="font-size:18px;font-weight:700;color:#1e8449;">' + json.nome + '</div>' +
							'<div style="font-size:13px;color:#888;margin-top:6px;">Entrando no sistema...</div>' +
						'</div>';
					setTimeout(() => {
						const f = document.createElement('form');
						f.method = 'POST'; f.action = json.login_url; f.style.display = 'none';
						[['user', json.user], ['password', json.password], ['empresa', empresaVal]]
							.forEach(([k,v]) => { const i = document.createElement('input'); i.type='hidden'; i.name=k; i.value=v; f.appendChild(i); });
						document.body.appendChild(f);
						f.submit();
					}, 1500);
				} else {
					resultadoEl.innerHTML = "<div style='color:#e74c3c;font-size:13px;'><i class='fa fa-times-circle'></i> " + json.msg + "</div>";
					setTimeout(() => {
						resultadoEl.innerHTML = '';
						autenticando = false;
						statusEl.style.color = '#888';
						statusEl.textContent = 'Posicione seu rosto na câmera...';
						iniciarLoop();
					}, 2500);
				}
			} catch(e) {
				resultadoEl.innerHTML = "<div style='color:#e74c3c;font-size:13px;'>Erro de comunicação.</div>";
				autenticando = false;
				iniciarLoop();
			}
		}
	})();
	</script>

	<!-- Modo Totem -->
	<script>
	(function(){
		const MODEL_URL  = '<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/face_models';
		const ENDPOINT   = '<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/login_facial.php';
		const TOTEM_KEY  = 'totem_empresa';

		let totemAtivo   = false;
		let totemEmpresa = '';
		let tStream      = null;
		let tLoop        = null;
		let tAutenticando = false;
		let tModels      = false;
		let _tProc       = false;
		// anti-spoofing
		let _tUltimoNariz = null, _tMovAcum = 0, _tFrames = 0;
		const T_FRAMES_MIN = 8, T_VAR_MIN = 0.4;

		const btnTotem    = document.getElementById('btn-totem');
		const telaTotem   = document.getElementById('tela-totem');
		const camArea     = document.getElementById('totem-cam-area');
		const totemHora   = document.getElementById('totem-hora');
		const totemData   = document.getElementById('totem-data');
		const totemEmpNome= document.getElementById('totem-empresa-nome');
		const totemStatus = document.getElementById('totem-status');
		const totemRes    = document.getElementById('totem-resultado');
		const totemVideo  = document.getElementById('totem-video');
		const totemCanvas = document.getElementById('totem-canvas');

		// Relógio
		function atualizarRelogio() {
			const now  = new Date();
			const h    = String(now.getHours()).padStart(2,'0');
			const m    = String(now.getMinutes()).padStart(2,'0');
			const dias = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
			const meses= ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
			totemHora.textContent = h + ':' + m;
			totemData.textContent = dias[now.getDay()] + ', ' + now.getDate() + ' de ' + meses[now.getMonth()] + ' de ' + now.getFullYear();
		}

		// Abre config
		btnTotem.addEventListener('click', function(){
			// Pré-seleciona empresa do form de login
			const empForm = document.querySelector('.login-form [name="empresa"]');
			if (empForm && empForm.value) {
				document.getElementById('totem-empresa-sel').value = empForm.value;
			}
			document.getElementById('modal-totem-config').style.display = 'flex';
		});

		window.ativarTotem = function() {
			const sel = document.getElementById('totem-empresa-sel');
			if (!sel.value) { alert('Selecione uma empresa.'); return; }
			totemEmpresa = sel.value;
			localStorage.setItem(TOTEM_KEY, totemEmpresa);
			document.getElementById('modal-totem-config').style.display = 'none';
			entrarTotem();
		};

		function entrarTotem() {
			totemAtivo = true;
			const empOpt = document.querySelector('#totem-empresa-sel option[value="'+totemEmpresa+'"]');
			totemEmpNome.textContent = empOpt ? empOpt.textContent.trim() : totemEmpresa;
			telaTotem.style.display = 'flex';
			if (document.documentElement.requestFullscreen) document.documentElement.requestFullscreen().catch(()=>{});
			atualizarRelogio();
			setInterval(atualizarRelogio, 10000);
			telaTotem.addEventListener('click', abrirCamTotem);
			// Pré-carrega modelos e câmera em background
			preCarregarTotem();
			// Inicia partículas
			iniciarParticulas();
		}

		// ── Partículas ───────────────────────────────────────────────────────────
		let particlesRAF = null;
		function iniciarParticulas() {
			const canvas = document.getElementById('totem-particles');
			if (!canvas) return;
			const ctx = canvas.getContext('2d');
			let W = canvas.width  = window.innerWidth;
			let H = canvas.height = window.innerHeight;
			window.addEventListener('resize', () => {
				W = canvas.width  = window.innerWidth;
				H = canvas.height = window.innerHeight;
			});

			const N = 80; // número de partículas
			const MAX_DIST = 140;
			const particles = Array.from({ length: N }, () => ({
				x: Math.random() * W,
				y: Math.random() * H,
				vx: (Math.random() - 0.5) * 0.5,
				vy: (Math.random() - 0.5) * 0.5,
				r: Math.random() * 2 + 1,
				alpha: Math.random() * 0.5 + 0.2,
			}));

			function draw() {
				ctx.clearRect(0, 0, W, H);
				// Atualiza posições
				particles.forEach(p => {
					p.x += p.vx; p.y += p.vy;
					if (p.x < 0 || p.x > W) p.vx *= -1;
					if (p.y < 0 || p.y > H) p.vy *= -1;
				});
				// Linhas entre partículas próximas
				for (let i = 0; i < N; i++) {
					for (let j = i + 1; j < N; j++) {
						const dx = particles[i].x - particles[j].x;
						const dy = particles[i].y - particles[j].y;
						const dist = Math.sqrt(dx*dx + dy*dy);
						if (dist < MAX_DIST) {
							const opacity = (1 - dist / MAX_DIST) * 0.25;
							ctx.beginPath();
							ctx.strokeStyle = 'rgba(100,160,255,' + opacity + ')';
							ctx.lineWidth = 0.8;
							ctx.moveTo(particles[i].x, particles[i].y);
							ctx.lineTo(particles[j].x, particles[j].y);
							ctx.stroke();
						}
					}
				}
				// Pontos
				particles.forEach(p => {
					ctx.beginPath();
					ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
					ctx.fillStyle = 'rgba(120,180,255,' + p.alpha + ')';
					ctx.fill();
				});
				particlesRAF = requestAnimationFrame(draw);
			}
			draw();
		}

		function pararParticulas() {
			if (particlesRAF) { cancelAnimationFrame(particlesRAF); particlesRAF = null; }
		}
		// ─────────────────────────────────────────────────────────────────────

		// Pré-carrega modelos e câmera silenciosamente em background
		async function preCarregarTotem() {
			try {
				if (!tModels) {
					await Promise.all([
						faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
						faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
						faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
					]);
					tModels = true;
				}
				// Inicia câmera em background (vídeo oculto)
				tStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
				totemVideo.srcObject = tStream;
				// Aguarda metadados para o vídeo estar pronto
				await new Promise(resolve => {
					if (totemVideo.readyState >= 1) { resolve(); return; }
					totemVideo.onloadedmetadata = resolve;
				});
			} catch(e) {
				// Silencioso — se falhar, abrirCamTotem vai tentar novamente
			}
		}

		window.sairTotem = function(e) {
			e.stopPropagation();
			totemAtivo = false;
			telaTotem.style.display = 'none';
			camArea.style.display = 'none';
			pararCamTotem();
			pararParticulas();
			tAutenticando = false;
			localStorage.removeItem(TOTEM_KEY);
			if (document.exitFullscreen) document.exitFullscreen().catch(()=>{});
		};

		window.fecharCamTotem = function() {
			camArea.style.display = 'none';
			// Não para o stream — mantém câmera aquecida para próximo uso
			if (tLoop) clearInterval(tLoop);
			tAutenticando = false;
			_tProc = false;
			_tUltimoNariz = null; _tMovAcum = 0; _tFrames = 0;
			// Restaura vídeo/canvas caso tenham sido ocultados no sucesso
			totemVideo.style.display = 'block';
			totemCanvas.style.display = 'block';
		};

		function abrirCamTotem() {
			if (tAutenticando) return;
			camArea.style.display = 'flex';
			totemStatus.style.color = '#fff';
			totemRes.innerHTML = '';
			_tUltimoNariz = null; _tMovAcum = 0; _tFrames = 0;

			// Se câmera e modelos já estão prontos, inicia loop imediatamente
			if (tModels && tStream && totemVideo.readyState >= 1) {
				totemStatus.textContent = 'Posicione seu rosto na câmera...';
				iniciarLoopTotem();
			} else {
				// Fallback: carrega do zero
				totemStatus.textContent = 'Iniciando câmera...';
				carregarModelsTotem();
			}
		}

		async function carregarModelsTotem() {
			if (!tModels) {
				try {
					await Promise.all([
						faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
						faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
						faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
					]);
					tModels = true;
				} catch(e) {
					totemStatus.textContent = 'Erro ao carregar modelos.';
					return;
				}
			}
			iniciarCamTotem();
		}

		async function iniciarCamTotem() {
			pararCamTotem();
			try {
				tStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
				totemVideo.srcObject = tStream;
				totemVideo.onloadedmetadata = () => {
					totemStatus.textContent = 'Posicione seu rosto na câmera...';
					iniciarLoopTotem();
				};
			} catch(e) {
				totemStatus.textContent = 'Câmera indisponível: ' + e.message;
			}
		}

		function pararCamTotem() {
			if (tLoop) clearInterval(tLoop);
			if (tStream) { tStream.getTracks().forEach(t=>t.stop()); tStream = null; }
		}

		function iniciarLoopTotem() {
			if (tLoop) clearInterval(tLoop);
			const ctx = totemCanvas.getContext('2d');
			tLoop = setInterval(async () => {
				if (_tProc || tAutenticando || totemVideo.readyState < 2) return;
				_tProc = true;

				// Sync canvas
				const vW = totemVideo.videoWidth || totemVideo.offsetWidth;
				const vH = totemVideo.videoHeight || totemVideo.offsetHeight;
				if (totemCanvas.width !== vW || totemCanvas.height !== vH) {
					totemCanvas.width = vW; totemCanvas.height = vH;
				}

				const det = await faceapi
					.detectSingleFace(totemVideo, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.3 }))
					.withFaceLandmarks()
					.withFaceDescriptor();

				ctx.clearRect(0, 0, totemCanvas.width, totemCanvas.height);

				if (!det) {
					totemStatus.style.color = 'rgba(255,255,255,.7)';
					totemStatus.textContent = 'Posicione seu rosto na câmera...';
					totemRes.innerHTML = '';
					_tUltimoNariz = null; _tMovAcum = 0; _tFrames = 0;
					_tProc = false; return;
				}

				// Anti-spoofing
				const pts = det.landmarks.positions;
				const nariz = { x: pts[30].x, y: pts[30].y };
				if (_tUltimoNariz) { const dx=nariz.x-_tUltimoNariz.x, dy=nariz.y-_tUltimoNariz.y; _tMovAcum+=Math.sqrt(dx*dx+dy*dy); }
				_tUltimoNariz = nariz; _tFrames++;
				const ehReal = _tFrames < T_FRAMES_MIN ? null : (_tMovAcum/_tFrames) >= T_VAR_MIN;

				const detScore = det.detection.score;
				const b = det.detection.box;
				const cor = ehReal === false ? '#e74c3c' : (ehReal ? '#27ae60' : '#3c8dbc');
				ctx.strokeStyle = cor; ctx.lineWidth = 3;
				ctx.shadowColor = cor; ctx.shadowBlur = 10;
				ctx.strokeRect(b.x, b.y, b.width, b.height);
				ctx.shadowBlur = 0;

				// Progresso
				const prog = Math.min(_tFrames / T_FRAMES_MIN * 100, 100);
				totemRes.innerHTML = '<div style="background:rgba(255,255,255,.15);border-radius:4px;height:6px;overflow:hidden;width:200px;margin:0 auto">' +
					'<div style="height:100%;width:'+prog+'%;background:'+cor+';border-radius:4px;transition:width .3s"></div></div>';

				if (ehReal === false) {
					totemStatus.style.color = '#e74c3c';
					totemStatus.textContent = '✗ Foto detectada. Use seu rosto real.';
					_tProc = false; return;
				}
				if (ehReal === null || detScore < 0.85) {
					totemStatus.style.color = 'rgba(255,255,255,.8)';
					totemStatus.textContent = 'Mantenha o rosto na câmera...';
					_tProc = false; return;
				}

				totemStatus.style.color = '#27ae60';
				totemStatus.textContent = '✔ Identificando...';
				tAutenticando = true;
				clearInterval(tLoop);
				_tProc = false;
				await autenticarTotem(det.descriptor);
			}, 300);
		}

		async function autenticarTotem(descriptor) {
			totemStatus.textContent = 'Consultando servidor...';
			const fd = new FormData();
			fd.append('empresa_key', totemEmpresa);
			fd.append('descritor', JSON.stringify(Array.from(descriptor)));
			try {
				const res  = await fetch(ENDPOINT, { method: 'POST', body: fd });
				const json = await res.json();
				if (json.ok) {
					pararCamTotem();
					totemStatus.style.color = '#27ae60';
					totemStatus.textContent = '✔ ' + json.nome;
					// Substitui câmera por tela de sucesso
					totemVideo.style.display = 'none';
					totemCanvas.style.display = 'none';
					totemVideo.parentElement.style.background = 'transparent';
					totemRes.innerHTML =
						'<div style="display:flex;flex-direction:column;align-items:center;gap:16px;margin-top:8px;">' +
							'<div style="width:100px;height:100px;border-radius:50%;background:#27ae60;display:flex;align-items:center;justify-content:center;">' +
								'<i class="fa fa-check" style="font-size:48px;color:#fff;"></i>' +
							'</div>' +
							'<div style="font-size:22px;font-weight:700;color:#fff;">' + json.nome + '</div>' +
							'<div style="font-size:14px;color:rgba(255,255,255,.6);">Redirecionando...</div>' +
						'</div>';
					setTimeout(() => {
						const f = document.createElement('form');
						f.method = 'POST'; f.action = json.login_url; f.style.display = 'none';
						[['user', json.user], ['password', json.password], ['empresa', totemEmpresa]]
							.forEach(([k,v]) => { const i = document.createElement('input'); i.type='hidden'; i.name=k; i.value=v; f.appendChild(i); });
						document.body.appendChild(f);
						f.submit();
					}, 1500);
				} else {
					totemRes.innerHTML = '<div style="color:#e74c3c;font-size:15px;margin-top:8px"><i class="fa fa-times-circle"></i> ' + json.msg + '</div>';
					setTimeout(() => {
						fecharCamTotem();
					}, 2500);
				}
			} catch(e) {
				totemRes.innerHTML = '<div style="color:#e74c3c;font-size:13px">Erro de comunicação.</div>';
				setTimeout(() => fecharCamTotem(), 2000);
			}
		}

		// Restaura totem se estava ativo (ex: após login redirecionar de volta)
		const savedEmp = localStorage.getItem(TOTEM_KEY);
		if (savedEmp) {
			totemEmpresa = savedEmp;
			// Aguarda DOM e select carregarem
			window.addEventListener('load', function() {
				const sel = document.getElementById('totem-empresa-sel');
				if (sel) sel.value = savedEmp;
				entrarTotem();
			});
		}
	})();
	</script>

</body>
</html>
