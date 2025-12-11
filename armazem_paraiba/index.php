<?php
	
		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");
	//*/

	$started = session_start();
	
	include_once "load_env.php";

	if(empty($_POST["getSessionValues"])){
		echo "<style>";
		include "css/index.css";
		echo "</style>";
	}

	$turnos = ["Noite", "Manhã", "Tarde", "Noite"];
	$turnoAtual = $turnos[intval((intval(date("H"))-3)/6)];

	function index(){
		global $turnoAtual;

		
		if(array_values(array_intersect(array_keys($_SESSION), ["user_tx_nome", "user_tx_login", "user_tx_nivel", "horaEntrada"])) != ["user_tx_login", "user_tx_nome", "user_tx_nivel", "horaEntrada"]){
			logar();
		}

		include_once "conecta.php";
		cabecalho("");
		showWelcome($_SESSION["user_tx_nome"],$turnoAtual,$_SESSION["horaEntrada"]);
		mostrarComunicadoPopup();
		rodape();
		exit;
	}

	function showWelcome($usuario, $turnoAtual, $horaEntrada) {
		global $turnoAtual;

        $contatos = [
            "Telefone" 				=> "<a href='https://api.whatsapp.com/send?phone=5584981578492' target='_blank'>(84) 98157-8492</a>",
            "Treinamento" 			=> "<a href='mailto:treinamento@techps.com.br' target='_blank'>treinamento@techps.com.br</a>",
            "Suporte de Sistemas" 	=> "<a href='mailto:suporte@techps.com.br' target='_blank'>suporte@techps.com.br</a>",
            "Comercial" 			=> "<a href='mailto:comercial@techps.com.br' target='_blank'>comercial@techps.com.br</a>",
            "Financeiro" 			=> "<a href='mailto:financeiro@techps.com.br' target='_blank'>financeiro@techps.com.br</a>",
            "Administrativo" 		=> "<a href='mailto:administrativo@techps.com.br' target='_blank'>administrativo@techps.com.br</a>"
        ];

        $table = "<table class='table w-auto table-condensed flip-content table-hover compact'><tbody>";
        foreach ($contatos as $area => $link) {
            $table .= "<tr><th>".$area.": </th><td>".$link."</td></tr>";
        }
        $table .= "</tbody></table>";

		$motoristasAtivos = mysqli_fetch_all(query(
			"SELECT enti_nb_id FROM entidade
			WHERE enti_tx_status = 'ativo';"
		), MYSQLI_ASSOC);
		$ativos = count($motoristasAtivos);
		

		$motoristasInativos = mysqli_fetch_all(query(
				"SELECT  enti_nb_id FROM entidade
				WHERE enti_tx_status = 'inativo';"
			), MYSQLI_ASSOC);
		$inativos = count($motoristasInativos);
		
		$filiais = mysqli_fetch_all(query(
				"SELECT  empr_nb_id FROM empresa
				WHERE empr_tx_Ehmatriz != 'sim';"
			), MYSQLI_ASSOC);
		$empresas = count($filiais);
		echo "
			<div class='container'>
				<div class='row' style='display:flex; justify-content: center; align-items: flex-end;'>

					<div class='col-sm-2'>
						<div class='panel panel-primary'>
							<div class='panel-heading text-center' style='display: flex; align-items: center; justify-content: center; '>
								<h3 class='panel-title' >
									<span class='glyphicon glyphicon-user'></span> Funcionários Ativos
								</h3>
							</div>
							<div class='panel-body text-center' style='display: flex; align-items: center; justify-content: center; height: 70px;'>
								<h1 style='font-size: 28px; margin: 0;'>$ativos</h1>
							</div>
						</div>
					</div>

					<div class='col-sm-2'>
						<div class='panel panel-info'>
							<div class='panel-heading text-center' style='display: flex; align-items: center; justify-content: center;'>
								<h3 class='panel-title'>
									<span class='glyphicon glyphicon-user'></span> Funcionários Inativos
								</h3>
							</div>
							<div class='panel-body text-center' style='display: flex; align-items: center; justify-content: center; height: 70px;'>
								<h1 style='font-size: 28px; margin: 0;'>$inativos</h1>
							</div>
						</div>
					</div>

					<div class='col-sm-2'>
						<div class='panel panel-success'>
							<div class='panel-heading text-center' style='display: flex; align-items: center; justify-content: center; min-height: 56px;'>
								<h3 class='panel-title'>
									<span class='glyphicon glyphicon-briefcase'></span> Filiais
								</h3>
							</div>
							<div class='panel-body text-center' style='display: flex; align-items: center; justify-content: center; height: 70px;'>
								<h1 style='font-size: 28px; margin: 0;'>$empresas</h1>
							</div>
						</div>
					</div>

				</div>
			</div>
		";
		echo 
			"<div id='boas-vindas' class='portlet light'>"
				."<div style='text-align: center; align-content: center; height: 5em;'>"
					."Bem Vindo(a), <b>".$usuario."</b>.<br>"
					."Período da ".$turnoAtual." iniciado às ".$horaEntrada."."
				."</div>"
				."<div class='obs'>"
					."<p>Neste sistema, você encontra informações relacionadas a: "
						."<ul>"
							."<li>Registros;</li>"
							."<li>Apontamentos de espelho de ponto;</li>"
							."<li>Endosso;</li>"
							."<li>Não conformidades;</li>"
							."<li>Acesso aos relatórios dos serviços contratados.</li>"
						."</ul>"
					."</p>"
				."</div>"
				."<p>Em caso de dúvida, respondemos a partir de uma das formas de contato abaixo.</p>"
				."<h4><b>Contatos:</b></h4>"
				."".$table."
			</div>"
		;
	}function mostrarComunicadoPopup(){
		// IMPORTANTE: Traz a conexão principal para dentro do escopo da função
		global $conn; 

		// Tenta pegar via $_ENV, se não conseguir, tenta via getenv()
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
		echo "<script>console.log('DB2: env host=".$host." name=".$n." port=".$port."');</script>";

		$texto = "";
		$fonte = ""; // Para saber de onde veio o dado (log)
		$titulo = "";
		$identificador = "";
		$nivel = isset($_SESSION["user_tx_nivel"]) ? $_SESSION["user_tx_nivel"] : "";
		$destino = (is_int(strpos($nivel, "Administrador")) || is_int(strpos($nivel, "Super Administrador"))) ? "Administrador" : "Funcionário";

		// 1. TENTA CONECTAR NO DB 2
		if(!empty($h) && !empty($u) && !empty($n)){
			$link = mysqli_init();
			mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 2);
			
			echo "<script>console.log('DB2: tentando conectar em ".$host.( $port? (':'.$port): '' )." banco ".$n."');</script>";
			$connected = $port ? mysqli_real_connect($link, $host, $u, $p, $n, $port) : mysqli_real_connect($link, $host, $u, $p, $n);
			if($connected){
				echo "<script>console.log('DB2: conexão estabelecida');</script>";
				mysqli_set_charset($link, "utf8mb4");
				
				$has = mysqli_query($link, "SHOW TABLES LIKE 'comunicados'");
				if($has && mysqli_num_rows($has) > 0){
					echo "<script>console.log('DB2: tabela comunicados encontrada');</script>";
					$chkCol = mysqli_query($link, "SHOW COLUMNS FROM comunicados LIKE 'comu_tx_dataCadastro'");
					$hasDate = $chkCol && mysqli_num_rows($chkCol) > 0;
					$chkDest = mysqli_query($link, "SHOW COLUMNS FROM comunicados LIKE 'comu_tx_destino'");
					$hasDest = $chkDest && mysqli_num_rows($chkDest) > 0;
					$chkTitle = mysqli_query($link, "SHOW COLUMNS FROM comunicados LIKE 'comu_tx_titulo'");
					$hasTitle = $chkTitle && mysqli_num_rows($chkTitle) > 0;
					$chkId = mysqli_query($link, "SHOW COLUMNS FROM comunicados LIKE 'comu_nb_id'");
					$hasId = $chkId && mysqli_num_rows($chkId) > 0;
					$fields = "comu_tx_texto".($hasTitle ? ", comu_tx_titulo" : "").($hasId ? ", comu_nb_id" : "").($hasDate ? ", comu_tx_dataCadastro" : "");
					$base = "SELECT ".$fields." FROM comunicados";
					if($hasDest){ $base .= " WHERE comu_tx_destino = '".mysqli_real_escape_string($link, $destino)."'"; }
					$order = $hasDate ? " ORDER BY comu_tx_dataCadastro DESC, comu_nb_id DESC" : " ORDER BY comu_nb_id DESC";
					$res = mysqli_query($link, $base.$order." LIMIT 1");
					if(!$res){
						echo "<script>console.warn('DB2: erro ao consultar comunicados: "+mysqli_error($link)+"');</script>";
					}

					if($res){
						$row = mysqli_fetch_assoc($res);
						if(!empty($row["comu_tx_texto"])){
							$texto = $row["comu_tx_texto"];
							if(!empty($row["comu_tx_titulo"])) $titulo = $row["comu_tx_titulo"];
							$identificador = !empty($row["comu_nb_id"]) ? (string)$row["comu_nb_id"] : (!empty($row["comu_tx_dataCadastro"]) ? (string)$row["comu_tx_dataCadastro"] : substr(sha1(($titulo??"")."|".$texto),0,16));
							$fonte = "Banco Externo (DB2)";
							echo "<script>console.log('DB2: comunicado obtido (".strlen($texto)." chars)');</script>";
							if(!empty($titulo)) echo "<script>console.log('DB2: título: ".substr(json_encode($titulo),0,60)."...');</script>";
						} else {
							echo "<script>console.log('DB2: nenhum texto retornado');</script>";
						}
					}
				} else {
					echo "<script>console.log('DB2: Tabela comunicados não encontrada');</script>";
				}
				mysqli_close($link);
			} else {
				echo "<script>console.warn('DB2: Não foi possível conectar: " . mysqli_connect_error() . "');</script>";
			}
		} else {
			echo "<script>console.warn('DB2: env incompleto, verifique DB_HOST2/DB_USER2/DB_NAME2');</script>";
		}

		// 2. FALLBACK: TENTA NO DB PRINCIPAL ($conn)
		if(empty($texto) && isset($conn)){
			echo "<script>console.log('Fallback: tentando banco principal');</script>";
			// Verifica se a tabela existe no banco principal
			$chk2 = mysqli_query($conn, "SHOW TABLES LIKE 'comunicados'");
			if($chk2 && mysqli_num_rows($chk2) > 0){
				$chkCol2 = mysqli_query($conn, "SHOW COLUMNS FROM comunicados LIKE 'comu_tx_dataCadastro'");
				$hasDate2 = $chkCol2 && mysqli_num_rows($chkCol2) > 0;
				$chkDest2 = mysqli_query($conn, "SHOW COLUMNS FROM comunicados LIKE 'comu_tx_destino'");
				$hasDest2 = $chkDest2 && mysqli_num_rows($chkDest2) > 0;
				$chkTitle2 = mysqli_query($conn, "SHOW COLUMNS FROM comunicados LIKE 'comu_tx_titulo'");
				$hasTitle2 = $chkTitle2 && mysqli_num_rows($chkTitle2) > 0;
				$chkId2 = mysqli_query($conn, "SHOW COLUMNS FROM comunicados LIKE 'comu_nb_id'");
				$hasId2 = $chkId2 && mysqli_num_rows($chkId2) > 0;
				$fields2 = "comu_tx_texto".($hasTitle2 ? ", comu_tx_titulo" : "").($hasId2 ? ", comu_nb_id" : "").($hasDate2 ? ", comu_tx_dataCadastro" : "");
				$base2 = "SELECT ".$fields2." FROM comunicados";
				if($hasDest2){ $base2 .= " WHERE comu_tx_destino = '".mysqli_real_escape_string($conn, $destino)."'"; }
				$order2 = $hasDate2 ? " ORDER BY comu_tx_dataCadastro DESC, comu_nb_id DESC" : " ORDER BY comu_nb_id DESC";
				$r2 = mysqli_query($conn, $base2.$order2." LIMIT 1");
				if($r2){
					$rw2 = mysqli_fetch_assoc($r2);
					if(!empty($rw2["comu_tx_texto"])){
						$texto = $rw2["comu_tx_texto"];
						if(!empty($rw2["comu_tx_titulo"])) $titulo = $rw2["comu_tx_titulo"];
						$fonte = "Banco Principal (Local)";
						echo "<script>console.log('Fallback: comunicado obtido (".strlen($texto)." chars)');</script>";
						if(empty($identificador)){
							$identificador = !empty($rw2["comu_nb_id"]) ? (string)$rw2["comu_nb_id"] : (!empty($rw2["comu_tx_dataCadastro"]) ? (string)$rw2["comu_tx_dataCadastro"] : substr(sha1(($titulo??"")."|".$texto),0,16));
						}
					}
				}
			} else {
				echo "<script>console.log('DB Principal: Tabela comunicados não existe.');</script>";
			}
		}

		// 3. EXIBIÇÃO
		if(!empty($texto)){
			$allowed = '<a><b><strong><i><em><u><br><p><ul><ol><li><h1><h2><h3><h4>';
			$conteudo = strip_tags($texto, $allowed);
			$conteudo = preg_replace('/on\\w+\s*=\s*"[^"]*"/i', '', $conteudo);
			$conteudo = preg_replace('/href\s*=\s*"\s*javascript:[^"]*"/i', 'href="#"', $conteudo);
			if(strpos($conteudo, '<') === false){
				$conteudo = nl2br(htmlspecialchars($conteudo));
				$conteudo = preg_replace('~(https?://[\w\-\./?%&=+#]+)~i', '<a href="$1" target="_blank" rel="noopener">$1</a>', $conteudo);
			}
			$jsConteudo = json_encode($conteudo);
			
			// Título
			$titulo = trim((string)$titulo);
			if($titulo === "") $titulo = "Comunicado";
			$tituloSafe = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
			$jsTitulo = json_encode($tituloSafe);
			$jsId = json_encode($identificador);
			
			echo "<script>console.log('Comunicado encontrado via: " . $fonte . "');</script>";
			
			// Modal Bootstrap
			echo "
			<div class='modal fade' id='comunicadoModal' tabindex='-1' role='dialog' aria-hidden='true'>
				<div class='modal-dialog modal-lg' style='max-width: 1200px; width: 100%;'>
					<div class='modal-content'>
						<div class='modal-header'>
						<button type='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
						<h4 class='modal-title'>".$tituloSafe."</h4>
						</div>
						<div class='modal-body'>".$conteudo."</div>
						<div class='modal-footer'>
							<button type='button' class='btn btn-primary' data-dismiss='modal'>Ok</button>
						</div>
					</div>
				</div>
			</div>";

			echo "<script>
			(function(){
				var id = ".$jsId.";
				function getCookie(n){ var m=document.cookie.match(new RegExp('(?:^|; )'+n.replace(/([.$?*|{}()\\[\\]\\/\\+^])/g,'\\\\$1')+'=([^;]*)')); return m? decodeURIComponent(m[1]) : null; }
				function setCookie(n,v,d){ var dt=new Date(); dt.setDate(dt.getDate()+(d||365)); document.cookie=n+'='+encodeURIComponent(v)+'; path=/; expires='+dt.toUTCString(); }
				if(getCookie('comunicado_visto') === id){ return; }
				function markSeen(){ setCookie('comunicado_visto', id, 365); }
				(function(){ if(document.getElementById('comunicadoNative')) return; var ov=document.createElement('div'); ov.id='comunicadoNative'; ov.style.position='fixed'; ov.style.zIndex='2147483647'; ov.style.left='0'; ov.style.top='0'; ov.style.right='0'; ov.style.bottom='0'; ov.style.background='rgba(0,0,0,.5)'; ov.style.display='flex'; ov.style.alignItems='center'; ov.style.justifyContent='center'; var box=document.createElement('div'); box.style.background='#fff'; box.style.maxWidth='960px'; box.style.width='95%'; box.style.borderRadius='6px'; box.style.boxShadow='0 6px 20px rgba(0,0,0,.2)'; box.style.padding='16px'; var h=document.createElement('div'); h.style.fontSize='18px'; h.style.fontWeight='600'; h.style.marginBottom='8px'; h.textContent = ".$jsTitulo."; var b=document.createElement('div'); b.innerHTML = ".$jsConteudo."; var f=document.createElement('div'); f.style.textAlign='right'; f.style.marginTop='12px'; var bt=document.createElement('button'); bt.textContent='Ok'; bt.className='btn btn-primary'; bt.onclick=function(){ markSeen(); document.body.removeChild(ov) }; f.appendChild(bt); box.appendChild(h); box.appendChild(b); box.appendChild(f); ov.appendChild(box); document.body.appendChild(ov); })();
				var t=0, iv=setInterval(function(){ t++; if(window.jQuery && $.fn.modal){ clearInterval(iv); try{ $('#comunicadoModal').modal('show'); $('#comunicadoModal .btn.btn-primary').on('click', function(){ markSeen(); }); var ov=document.getElementById('comunicadoNative'); if(ov) document.body.removeChild(ov); }catch(e){} } else if(t>50){ clearInterval(iv); } },100);
			})();
			</script>";
		} else {
			echo "<script>console.warn('Nenhum comunicado encontrado em nenhum dos bancos.');</script>";
		}
	}

	if(!empty($_SESSION["user_nb_id"]) && empty($_POST["user"]) && empty($_POST["password"])){ //Se já há um usuário logado e não está tentando um novo login
		$interno = true;
		include_once "conecta.php";
		cabecalho("");
		showWelcome($_SESSION["user_tx_nome"],$turnoAtual,$_SESSION["horaEntrada"]);
		mostrarComunicadoPopup();
		rodape();
		exit;
	}

	function logar(){
	    
		global $turnoAtual;
		if(empty($_POST["user"]) && !empty($_POST["username"])){
			$_POST["user"] = $_POST["username"];
		}
	
		$error = "emptyfields";
	
		if(!empty($_POST["user"]) && !empty($_POST["password"])){//Tentando logar
			if(!empty($_SESSION["user_tx_login"]) && $_SESSION["user_tx_login"] != $_POST["user"]){ //Se já há um usuário logado
				$_SESSION = [];
				session_destroy();
			}else{
				$_SESSION["user_tx_login"] = $_POST["user"];
			}
	
			
			$interno = true; //Utilizado em conecta.php;
			include_once "conecta.php";
            include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/contex20/funcoes_form.php";
            include_once "check_permission.php";
			
			$usuario = mysqli_fetch_assoc(query(
				"SELECT * FROM user"
					." WHERE user_tx_status = 'ativo'"
						." AND user_tx_login = '".$_POST["user"]."'"
						." AND user_tx_senha = '".$_POST["password"]."';"
			));
			
			if(!empty($usuario)){ //Se encontrou um usuário

				if (!empty($usuario["user_tx_expiracao"]) && strtotime($usuario["user_tx_expiracao"]) < strtotime(date("Y-m-d"))){
					$error = "expireduser";
					$_POST["HTTP_REFERER"] = $_ENV["APP_PATH"]."/index.php?error=".$error;
					$_POST["returnValues"] = json_encode([
						"HTTP_REFERER" => $_POST["HTTP_REFERER"],
						"empresa" => $_POST["empresa"],
						"user" => $_POST["user"],
						"password" => $_POST["password"]
					]);
					voltar();
					exit;
				}
	
			foreach($usuario as $key => $value){
				$_SESSION[$key] = $value;
			}

	
				if(!isset($_SESSION["horaEntrada"])){
					$_SESSION["horaEntrada"] = date("H:i");
				}
				if(!empty($_POST["getSessionValues"])){
					echo json_encode($_SESSION);
					exit;
				}
                if(in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário"])){
                    $bateRow = mysqli_fetch_assoc(query(
                        "SELECT enti_tx_batePonto FROM entidade WHERE enti_nb_id = ".intval($_SESSION["user_nb_entidade"])." LIMIT 1;"
                    ));
                    $deveBater = strtolower($bateRow["enti_tx_batePonto"] ?? "sim");
                    $temPerm = function_exists('temPermissaoMenu') ? temPermissaoMenu('/batida_ponto.php') : true;
                    if($deveBater === 'sim' && $temPerm){
                        echo "<meta http-equiv='refresh' content='0; url=./batida_ponto.php'/>";
                        exit;
                    }
                }
	
				if(!empty($_POST["sourcePage"]) && is_int(strpos($_POST["sourcePage"], $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]))){
					echo 
						"<form name='goToSourceForm' action='".$_POST["sourcePage"]."'></form>"
						."<script>document.goToSourceForm.submit();</script>"
					;
				}
	
			cabecalho("");
			showWelcome($usuario["user_tx_nome"], $turnoAtual, $_SESSION["horaEntrada"]);
			mostrarComunicadoPopup();
			rodape();
			exit;
		}
		}
        
		$error = "notfound";
		$_POST["HTTP_REFERER"] = $_ENV["APP_PATH"]."/index.php?error=".$error;
		$_POST["returnValues"] = json_encode([
			"HTTP_REFERER" => $_POST["HTTP_REFERER"],
			"empresa" => $_POST["empresa"],
			"user" => $_POST["user"],
			"password" => $_POST["password"]
		]);

		voltar();
		exit;
	}

	logar();
