<!DOCTYPE html>
<!-- 
	Template Name: Metronic - Responsive Admin Dashboard Template build with Twitter Bootstrap 3.3.6
	Version: 4.5.4
	Author: KeenThemes
	Website: http://www.keenthemes.com/
	Contact: support@keenthemes.com
	Follow: www.twitter.com/keenthemes
	Like: www.facebook.com/keenthemes
	Purchase: http://themeforest.net/item/metronic-responsive-admin-dashboard-template/4021469?ref=keenthemes
	License: You must have a valid license purchased only from themeforest(the above link) in order to legally use the theme for your project.
-->
<!--[if IE 8]> <html lang="en" class="ie8 no-js"> <![endif]-->
<!--[if IE 9]> <html lang="en" class="ie9 no-js"> <![endif]-->
<html lang="pt-BR">
<!-- COMECO HEAD -->
	<head>
		<meta charset="utf-8" />
		<title>TechPS</title>
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta content="width=device-width, initial-scale=1" name="viewport" />
		<meta content="" name="description" />
		<meta content="" name="author" />
		<!-- COMECO GLOBAL MANDATORY STYLES -->
		<link href="https://fonts.googleapis.com/css?family=Open+Sans:400,300,600,700&subset=all" rel="stylesheet" type="text/css" />
		<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
		<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/simple-line-icons/simple-line-icons.min.css" rel="stylesheet" type="text/css" />
		<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
		<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/uniform/css/uniform.default.css" rel="stylesheet" type="text/css" />
		<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap-switch/css/bootstrap-switch.min.css" rel="stylesheet" type="text/css" />
		<!-- FIM GLOBAL MANDATORY STYLES -->
		<!-- COMECO PLUGINS DE PAGINA -->
		<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css" />
		<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/css/select2-bootstrap.min.css" rel="stylesheet" type="text/css" />
		<!-- FIM PLUGINS DE PAGINA -->
		<!-- COMECO THEME GLOBAL STYLES -->
		<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/css/components.min.css" rel="stylesheet" id="style_components" type="text/css" />
		<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/css/plugins.min.css" rel="stylesheet" type="text/css" />
		<!-- FIM THEME GLOBAL STYLES -->
		<!-- COMECO PAGE LEVEL STYLES -->
		<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/pages/css/login.min.css" rel="stylesheet" type="text/css" />
		<!-- FIM PAGE LEVEL STYLES -->
		<!-- COMECO THEME LAYOUT STYLES -->
		<!-- FIM THEME LAYOUT STYLES -->
		<link rel="apple-touch-icon" sizes="180x180" href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/favicon/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/favicon/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/favicon/favicon-16x16.png">
		<link rel="shortcut icon" type="image/x-icon" href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/favicon/favicon-32x32.png?v=2">
		<link rel="manifest" href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/favicon/site.webmanifest">
	</head>
	<!-- FIM HEAD -->
	<body class="login">
		<!-- COMECO LOGO -->
		<div class="logo">
			<a href="https://techps.com.br/">
				<img src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/logo.png" alt="" /> </a>
		</div>
		<!-- FIM LOGO -->
		<!-- COMECO LOGIN -->
		<div class="content">
			<!-- COMECO LOGIN FORM -->
			<form class="login-form" method="post">
				<h3 class="form-title font-blue">Login <?=(is_int(strpos($_SERVER["REQUEST_URI"], "dev"))? "(Dev)": "")?></h3>
				<!--Vem do arquivo empresas.php -->
				<?=$empresasInput?>

				<div class="form-group">
					<!--ie8, ie9 does not support html5 placeholder, so we just show field title for that-->
					<input 
						class="form-control form-control-solid placeholder-no-fix" 
						type="text"
						autocomplete="off" 
						placeholder="Usuário" 
						name="user"
						<?=(!empty($_POST["user"])? "value=".$_POST["user"]: "")?>
					/>
				</div>
				<div class="form-group">
					<input 
						class="form-control form-control-solid placeholder-no-fix" 
						type="password" 
						autocomplete="off"
						placeholder="Senha" 
						name="password"
					/>
				</div>
				<div class="" style="display:flex; align-items:center; width:100%; justify-content:space-between; margin-top:10px">
					<label style="display:flex; align-items:center; gap:12px; margin:0; white-space:nowrap; flex-shrink:0">
						<input type="checkbox" name="remember" />
						<span>Lembre-me</span>
					</label>
					<a href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/recupera_senha.php"?>" id="forget-password" class="forget-password">Esqueceu sua senha?</a>
				</div>
				<?=(!empty($_POST["sourcePage"]) ? "<input type='hidden' name='sourcePage' value= '".$_POST["sourcePage"]."'/>" : "")?>
				<?= $msg ?>
				<div class="form-actions" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
					<input type="submit" class="btn blue uppercase" name="botao" value="Entrar">
					<button type="button" id="btn-face-login" class="btn btn-default uppercase" title="Entrar com reconhecimento facial">
						<i class="fa fa-eye"></i> Biometria Facial
					</button>
				</div>

				<!-- Modal de reconhecimento facial -->
				<div id="modal-face" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center;">
					<div style="background:#fff; border-radius:10px; padding:24px; max-width:520px; width:95%; text-align:center; position:relative;">
						<button onclick="fecharModalFace()" style="position:absolute;top:10px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;">✕</button>
						<h4 style="margin-bottom:4px;"><i class="fa fa-eye"></i> Reconhecimento Facial</h4>
						<p id="face-login-status" style="color:#888; font-size:13px; min-height:18px;">Carregando modelos de IA...</p>
						<div style="position:relative; display:inline-block;">
							<video id="face-login-video" width="420" height="315" autoplay muted playsinline style="border-radius:8px; border:3px solid #ddd; display:block;"></video>
							<canvas id="face-login-canvas" width="420" height="315" style="position:absolute;top:0;left:0;pointer-events:none;"></canvas>
						</div>
						<div id="face-login-resultado" style="margin-top:12px; min-height:36px;"></div>
					</div>
				</div>
				<p style="font-size: small; margin: 10px 0px">Versão:
					<?= $version; ?><br>
					Data de lançamento:
					<?= $release_date; ?>
				</p>
			</form>
			<!-- FIM LOGIN FORM -->
		</div>
		<div class="copyright">
			<?= date("Y") ?> © TechPS.
		</div>
		<!--[if lt IE 9]>
		<![endif]-->
		<!-- COMECO PLUGINS PRINCIPAL -->
		<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/jquery.min.js" type="text/javascript"></script>
		<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
		<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/js.cookie.min.js" type="text/javascript"></script>
		<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js" type="text/javascript"></script>
		<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js" type="text/javascript"></script>
		<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/jquery.blockui.min.js" type="text/javascript"></script>
		<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/uniform/jquery.uniform.min.js" type="text/javascript"></script>

		<?=$dataScript?>
		<script>
		(function(){
			function setCookie(n,v,d){var t=new Date();t.setTime(t.getTime()+(d*24*60*60*1000));document.cookie=n+"="+encodeURIComponent(v)+";expires="+t.toUTCString()+";path=/"}
			function getCookie(n){var m=("; "+document.cookie).split("; "+n+"=");if(m.length===2) return decodeURIComponent(m.pop().split(";").shift());return ""}
			var form=document.querySelector('.login-form');
			if(!form) return;
			var user=form.querySelector('input[name="user"]');
			var pass=form.querySelector('input[name="password"]');
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
		<!-- FIM PLUGINS PRINCIPAL -->
		<!-- COMECO PLUGINS DE PAGINA -->
		<!-- FIM PLUGINS DE PAGINA -->
		<!-- COMECO SCRIPTS GLOBAL -->
		<!-- FIM SCRIPTS GLOBAL -->
		<!-- COMECO PAGE LEVEL SCRIPTS -->
		<!-- FIM PAGE LEVEL SCRIPTS -->
		<!-- COMECO THEME LAYOUT SCRIPTS -->
		<!-- FIM THEME LAYOUT SCRIPTS -->

		<!-- face-api.js para login facial -->
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
			};

			async function iniciarCamera() {
				try {
					stream = await navigator.mediaDevices.getUserMedia({
						video: { width: 420, height: 315, facingMode: 'user' }
					});
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
			function iniciarLoop() {
				if (detLoop) clearInterval(detLoop);
				const ctx = canvasEl.getContext('2d');

				detLoop = setInterval(async () => {
					if (_proc || autenticando || videoEl.readyState < 2) return;
					_proc = true;

					const det = await faceapi
						.detectSingleFace(videoEl, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.3 }))
						.withFaceLandmarks()
						.withFaceDescriptor();

					ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);

					if (!det) {
						statusEl.style.color = '#888';
						statusEl.textContent = 'Posicione seu rosto na câmera...';
						resultadoEl.innerHTML = '';
						_proc = false;
						return;
					}

					// Score de detecção (0→1): qualidade do rosto na frame atual
					const detScore  = det.detection.score;
					const qualidade = Math.round(detScore * 100);
					const cor       = detScore >= 0.90 ? '#27ae60' : detScore >= 0.75 ? '#e67e22' : '#e74c3c';
					const label     = detScore >= 0.90 ? 'Ótima' : detScore >= 0.75 ? 'Boa' : 'Fraca';

					// Barra de qualidade em tempo real
					resultadoEl.innerHTML =
						'<div style="font-size:11px;color:#888;margin-bottom:3px">Qualidade do rosto detectado</div>' +
						'<div style="background:#eee;border-radius:4px;height:8px;overflow:hidden">' +
							'<div style="height:100%;width:'+qualidade+'%;background:'+cor+';border-radius:4px;transition:width .2s"></div>' +
						'</div>' +
						'<div style="font-size:12px;color:'+cor+';margin-top:3px;font-weight:600">'+qualidade+'% — '+label+'</div>';

					const b = det.detection.box;
					ctx.strokeStyle = cor;
					ctx.lineWidth   = 3;
					ctx.shadowColor = cor;
					ctx.shadowBlur  = 8;
					ctx.strokeRect(b.x, b.y, b.width, b.height);
					ctx.shadowBlur  = 0;

					// Só autentica com qualidade >= 85%
					if (detScore < 0.85) {
						statusEl.style.color = '#e67e22';
						statusEl.textContent = 'Centralize o rosto e melhore a iluminação...';
						_proc = false;
						return;
					}

					statusEl.style.color = '#27ae60';
					statusEl.textContent = '✔ Qualidade suficiente — identificando...';

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
						// Confiança: usa 0.6 como base (distância máxima real do face-api)
						// 0.00 = 100%, 0.30 = 50%, 0.60 = 0%
						const dist = json.distancia || 0;
						const conf = Math.round(Math.max(0, (1 - dist / 0.6) * 100));
						const corConf = conf >= 70 ? '#27ae60' : conf >= 50 ? '#e67e22' : '#e74c3c';
						resultadoEl.innerHTML =
							"<div style='color:#27ae60;font-weight:bold;font-size:14px;'><i class='fa fa-check-circle'></i> " + json.nome + " — entrando...</div>";
							/*
							"<div style='font-size:11px;color:#888;margin-top:6px'>Confiança de reconhecimento</div>" +
							"<div style='background:#eee;border-radius:4px;height:8px;overflow:hidden;margin-top:3px'>" +
								"<div style='height:100%;width:"+conf+"%;background:"+corConf+";border-radius:4px'></div>" +
							"</div>" +
							"<div style='font-size:12px;color:"+corConf+";margin-top:3px;font-weight:600'>"+conf+"% (distância: "+dist+")</div>";
							*/

						// ── DEBUG: 5s para copiar os valores antes de redirecionar ──
						/*
						let countdown = 5;
						const timer = setInterval(() => {
							countdown--;
							const el = document.getElementById('debug-countdown');
							if (el) el.textContent = countdown;
							if (countdown <= 0) {
								clearInterval(timer);
								const f = document.createElement('form');
								f.method = 'POST'; f.action = json.login_url; f.style.display = 'none';
								[['user', json.user], ['password', json.password], ['empresa', empresaVal]]
									.forEach(([k,v]) => { const i = document.createElement('input'); i.type='hidden'; i.name=k; i.value=v; f.appendChild(i); });
								document.body.appendChild(f);
								f.submit();
							}
						}, 1000);
						resultadoEl.innerHTML += "<div style='margin-top:8px;font-size:12px;color:#888'>Redirecionando em <b id='debug-countdown'>5</b>s...</div>";
						*/
						setTimeout(() => {
							const f = document.createElement('form');
							f.method = 'POST'; f.action = json.login_url; f.style.display = 'none';
							[['user', json.user], ['password', json.password], ['empresa', empresaVal]]
								.forEach(([k,v]) => { const i = document.createElement('input'); i.type='hidden'; i.name=k; i.value=v; f.appendChild(i); });
							document.body.appendChild(f);
							f.submit();
						}, 1000);
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
	</body>

			// Sorteia 2 movimentos aleatórios a cada sessão
			function sortearDesafios() {
				const embaralhado = MOVIMENTOS.slice().sort(() => Math.random() - 0.5);
				return embaralhado.slice(0, 2);
			}

			// Calcula yaw (rotação horizontal) e pitch (inclinação vertical)
			// a partir dos 68 landmarks do face-api
			function calcularPose(landmarks) {
				const pts = landmarks.positions;
				// Yaw: diferença horizontal entre ponta do nariz e centro dos olhos
				const noseX   = pts[30].x;
				const leftEye  = (pts[36].x + pts[39].x) / 2;
				const rightEye = (pts[42].x + pts[45].x) / 2;
				const eyeCenter = (leftEye + rightEye) / 2;
				const eyeWidth  = rightEye - leftEye;
				const yaw = (noseX - eyeCenter) / (eyeWidth || 1); // negativo=esquerda, positivo=direita

				// Pitch: diferença vertical entre nariz e queixo vs nariz e testa
				const noseY  = pts[30].y;
				const chinY  = pts[8].y;
				const browY  = (pts[19].y + pts[24].y) / 2;
				const faceH  = chinY - browY || 1;
				const pitch  = (noseY - browY) / faceH - 0.5; // negativo=cima, positivo=baixo

				return { yaw, pitch };
			}

			// Thresholds de movimento
			const YAW_THRESH   = 0.18; // ~18% da largura dos olhos
			const PITCH_THRESH = 0.12; // ~12% da altura do rosto

			function detectarMovimento(pose, desafio) {
				switch (desafio.id) {
					case 'esquerda': return pose.yaw < -YAW_THRESH;
					case 'direita':  return pose.yaw >  YAW_THRESH;
					case 'cima':     return pose.pitch < -PITCH_THRESH;
					case 'baixo':    return pose.pitch >  PITCH_THRESH;
				}
				return false;
			}

			// ── Estado ──────────────────────────────────────────────────────
			let modelsLoaded  = false;
			let stream        = null;
			let detLoop       = null;
			let processando   = false;
			let desafios      = [];   // lista de movimentos a cumprir
			let desafioAtual  = 0;    // índice do desafio em andamento
			let livenessOk    = false;
			let descriptorCapturado = null;

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
				resetarLiveness();

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
						statusEl.textContent = 'Erro ao carregar modelos. Verifique a pasta face_models.';
						return;
					}
				}
				iniciarCamera();
			}

			function resetarLiveness() {
				desafios     = sortearDesafios();
				desafioAtual = 0;
				livenessOk   = false;
				descriptorCapturado = null;
				processando  = false;
			}

			window.fecharModalFace = function() {
				modal.style.display = 'none';
				pararCamera();
				resetarLiveness();
			};

			async function iniciarCamera() {
				try {
					stream = await navigator.mediaDevices.getUserMedia({ video: { width: 420, height: 315, facingMode: 'user' } });
					videoEl.srcObject = stream;
					videoEl.onloadedmetadata = () => iniciarLoop();
					mostrarDesafioAtual();
				} catch(e) {
					statusEl.textContent = 'Câmera indisponível: ' + e.message;
				}
			}

			function pararCamera() {
				if (detLoop) clearInterval(detLoop);
				if (stream)  { stream.getTracks().forEach(t => t.stop()); stream = null; }
			}

			function mostrarDesafioAtual() {
				if (desafioAtual < desafios.length) {
					statusEl.style.color = '#e67e22';
					statusEl.textContent = desafios[desafioAtual].label;
				}
			}

			// ── Loop principal ───────────────────────────────────────────────
			function iniciarLoop() {
				if (detLoop) clearInterval(detLoop);
				const ctx = canvasEl.getContext('2d');

				detLoop = setInterval(async () => {
					if (processando || videoEl.readyState < 2) return;
					processando = true;

					const det = await faceapi
						.detectSingleFace(videoEl, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 }))
						.withFaceLandmarks()
						.withFaceDescriptor();

					ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);

					if (!det) {
						statusEl.style.color = '#888';
						statusEl.textContent = 'Posicione seu rosto na câmera...';
						processando = false;
						return;
					}

					// Desenha retângulo
					const b = det.detection.box;
					ctx.strokeStyle = livenessOk ? '#27ae60' : '#e67e22';
					ctx.lineWidth = 3;
					ctx.strokeRect(b.x, b.y, b.width, b.height);

					if (livenessOk) {
						// Liveness aprovado — faz reconhecimento
						processando = false;
						clearInterval(detLoop);
						await reconhecer(det.descriptor);
						return;
					}

					// Verifica desafio atual
					const pose = calcularPose(det.landmarks);
					if (detectarMovimento(pose, desafios[desafioAtual])) {
						desafioAtual++;
						if (desafioAtual >= desafios.length) {
							// Todos os desafios cumpridos — captura descritor
							livenessOk = true;
							statusEl.style.color = '#27ae60';
							statusEl.textContent = '✔ Verificação concluída! Identificando...';
						} else {
							// Próximo desafio
							mostrarDesafioAtual();
						}
					}

					processando = false;
				}, 350);
			}

			// ── Reconhecimento após liveness ─────────────────────────────────
			async function reconhecer(descriptor) {
				const empresaEl  = loginForm ? loginForm.querySelector('[name="empresa"]') : null;
				const empresaVal = empresaEl ? empresaEl.value : '';

				if (!empresaVal) {
					resultadoEl.innerHTML = "<div style='color:#e74c3c;font-size:13px;'>Selecione a empresa antes de usar a biometria.</div>";
					resetarLiveness();
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
						resultadoEl.innerHTML = "<div style='color:#27ae60;font-weight:bold;font-size:14px;'><i class='fa fa-check-circle'></i> " + json.nome + " — fazendo login...</div>";

						setTimeout(() => {
							const hiddenForm = document.createElement('form');
							hiddenForm.method = 'POST';
							hiddenForm.action = json.login_url;
							hiddenForm.style.display = 'none';
							[['user', json.user], ['password', json.password], ['empresa', empresaVal]]
								.forEach(([k,v]) => {
									const inp = document.createElement('input');
									inp.type='hidden'; inp.name=k; inp.value=v;
									hiddenForm.appendChild(inp);
								});
							document.body.appendChild(hiddenForm);
							hiddenForm.submit();
						}, 1200);

					} else {
						resultadoEl.innerHTML = "<div style='color:#e74c3c;font-size:13px;'><i class='fa fa-times-circle'></i> " + json.msg + "</div>";
						// Reinicia liveness para nova tentativa
						setTimeout(() => {
							resultadoEl.innerHTML = '';
							resetarLiveness();
							mostrarDesafioAtual();
							iniciarLoop();
						}, 2500);
					}
				} catch(e) {
					resultadoEl.innerHTML = "<div style='color:#e74c3c;font-size:13px;'>Erro de comunicação com o servidor.</div>";
					resetarLiveness();
					iniciarLoop();
				}
			}
		})();
		</script>
	</body>
