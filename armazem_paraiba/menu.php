<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/

    include "load_env.php";
    include_once "conecta.php";
    include_once "check_permission.php";
    include_once "menu_estrutura.php";
    echo '<link rel="stylesheet" href="'.$CONTEX['path'].'/css/menu.css">';

	function verificarAtividade($paginasAtivas) {
		foreach ($paginasAtivas as $pagina){
			if (is_int(strpos($_SERVER["REQUEST_URI"], $_ENV["CONTEX_PATH"].$pagina))) {
				return "active";
			}
		}
		return "";
	}

    // Desenha o menu Metronic a partir da estrutura compartilhada (menu_estrutura.php).
    // Quais seções e páginas aparecem — domínio, placas, perfil de acesso, nível — é
    // decidido lá; aqui é só HTML. O cabeçalho do módulo de assinatura usa a mesma fonte.
    function mostrarMenuDoNivel($nivel): string{
        global $CONTEX;

        $html = "";
        foreach(menu_estrutura_do_nivel($nivel) as $no){
            if($no["tipo"] === "link"){
                $html .= "<li class=''><a href='".$CONTEX["path"].$no["path"]."' class='nav-link'> ".$no["label"]."</a></li>";
                continue;
            }

            $children = "";
            foreach($no["itens"] as $item){
                if ($item["iti"]) {
                    $children .= "<li class='dd'><a href='javascript:void(0);' onclick='abrirInstrucoesITI(); return false;' class='nav-link'> ".$item["label"]."</a></li>";
                } else {
                    $children .= "<li class='dd'><a href='".$CONTEX["path"].$item["path"]."' class='nav-link'> ".$item["label"]."</a></li>";
                }
            }

            //Decide se a seção é simples ou 2 colunas
            $ulClass = "dropdown-menu pull-left";
            if($no["duas_colunas"]){
                $ulClass .= " dropdown-2cols";
            }

            $html .= "
                    <li class='menu-dropdown classic-menu-dropdown ".verificarAtividade($no["paths"])."'>
                        <a>".$no["titulo"]."</a>
                        <ul class='".$ulClass."'>".$children."</ul>
                    </li>";
        }
        return $html;
    }

    echo 
        "<!-- INICIO HEADER MENU -->"
            ."<div class='page-header-menu'>"
                ."<div class='container-fluid'>"
                ."<!-- INICIO MEGA MENU -->"
                    ."<div class='hor-menu'>"
                        ."<ul class='nav navbar-nav'>"
                            .mostrarMenuDoNivel($_SESSION["user_tx_nivel"])
                        ."</ul>"
                    ."</div>"
                ."<!-- FIM MEGA MENU -->"
                ."</div>"
            ."</div>"
        ."<!-- FIM HEADER MENU -->"
    ;

    // Garante que a funcao do menu "Validar Assinatura" esteja disponivel em todas as paginas
    echo "
    <script>
        (function(){
            window.ssResolveFotoUrl = function(p) {
                if (!p) return '';
                if (p.indexOf('data:image/') === 0 || p.indexOf('http') === 0) {
                    return p;
                }
                const appPath = " . json_encode($_ENV["APP_PATH"] ?? "/braso") . ";
                const hasSaudeSeguranca = (p.indexOf('saude_seguranca/') !== -1 || p.indexOf('armazem_paraiba/') !== -1);
                if (hasSaudeSeguranca) {
                    return appPath + '/' + p;
                }
                return appPath + '/' + p;
            };
            // Fallback automático de caminho de imagem: se o caminho da raiz falhar,
            // tenta o mesmo arquivo em armazem_paraiba/saude_seguranca (e vice-versa).
            document.addEventListener('error', function(e) {
                var img = e.target;
                if (!img || img.tagName !== 'IMG' || img.getAttribute('data-img-fallback') === 'done') return;
                var src = img.getAttribute('src') || '';
                if (src.indexOf('data:image/') === 0 || src.indexOf('http') === 0) return;
                var appPath = " . json_encode($_ENV["APP_PATH"] ?? "/braso") . ";
                var alternativo = null;
                if (src.indexOf(appPath + '/armazem_paraiba/saude_seguranca/') !== -1) {
                    alternativo = src.replace(appPath + '/armazem_paraiba/saude_seguranca/', appPath + '/');
                } else if (src.indexOf(appPath + '/') === 0) {
                    alternativo = src.replace(appPath + '/', appPath + '/armazem_paraiba/saude_seguranca/');
                }
                if (alternativo) {
                    img.setAttribute('data-img-fallback', 'done');
                    img.setAttribute('src', alternativo);
                }
            }, true);
            window.verImagemMaior = function(src) {
                if (typeof Swal === 'undefined') {
                    window.open(src, '_blank');
                } else {
                    Swal.fire({
                        imageUrl: src,
                        imageAlt: 'Visualização da Imagem',
                        showConfirmButton: false,
                        showCloseButton: true,
                        background: '#fff',
                        backdrop: 'rgba(0,0,0,0.8)'
                    });
                }
            };
            if (typeof Swal === 'undefined') {
                var s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
                s.onload = function() { definirAbrirInstrucoesITI(); };
                document.head.appendChild(s);
            } else {
                definirAbrirInstrucoesITI();
            }
            function definirAbrirInstrucoesITI() {
                if (typeof window.abrirInstrucoesITI === 'function') return;
                window.abrirInstrucoesITI = function() {
                    Swal.fire({
                        title: 'Como validar sua assinatura no ITI',
                        html: `
                            <div class='text-left text-sm text-gray-700 space-y-3'>
                                <p>Siga os passos abaixo para verificar a validade juridica do documento assinado:</p>
                                <ol class='list-decimal list-inside space-y-2'>
                                    <li>Ao acessar o site do ITI, clique em <strong>\"Escolher arquivo\"</strong>.</li>
                                    <li>Selecione o <strong>PDF assinado</strong> que possui o certificado ICP-Brasil.</li>
                                    <li>Marque a opcao <strong>\"Concordo com os termos\"</strong>.</li>
                                    <li>Clique no botao <strong>\"Validar\"</strong>.</li>
                                </ol>
                                <p class='text-xs text-gray-500 mt-2'>Voce sera redirecionado para o validador oficial do ITI em uma nova aba.</p>
                            </div>
                        `,
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonText: '<i class=\"fa fa-external-link-alt mr-1\"></i> Entendi, abrir ITI',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#4b6cb7',
                        cancelButtonColor: '#6b7280',
                        reverseButtons: true,
                        allowOutsideClick: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.open('https://validar.iti.gov.br', '_blank', 'noopener,noreferrer');
                        }
                    });
                };
            }
        })();
    </script>
    ";

    /*
    $hasTable = false;
    global $conn;
    if(isset($conn)){
        $chk = mysqli_query($conn, "SHOW TABLES LIKE 'comunicado_interno'");
        if($chk){ $hasTable = mysqli_num_rows($chk) > 0; }
    }
    if($hasTable && !empty($_SESSION["user_tx_nivel"])){
        $nivel = trim($_SESSION["user_tx_nivel"]);
        $comu = mysqli_fetch_assoc(query("SELECT coin_tx_titulo, coin_tx_texto, coin_tx_tipo_conteudo, coin_tx_imagem FROM comunicado_interno WHERE coin_tx_status = 'ativo' AND (FIND_IN_SET('todos', coin_tx_dest_perfis) > 0 OR FIND_IN_SET(?, coin_tx_dest_perfis) > 0) ORDER BY coin_nb_id DESC LIMIT 1", "s", [$nivel]));
        
        if(!empty($comu)){
            echo "<div class='container-fluid menu-alert-container'><div class='alert alert-info menu-alert' role='alert'><i class='fa fa-info-circle menu-alert__icon'></i><div class='menu-alert__text'>";
            echo "<strong>".htmlspecialchars($comu["coin_tx_titulo"])."</strong><br>";
            if($comu["coin_tx_tipo_conteudo"] == "imagem" && !empty($comu["coin_tx_imagem"])){
                 echo "<a href='".$comu["coin_tx_imagem"]."' target='_blank' class='alert-link'>Visualizar Imagem do Comunicado</a>";
            } else {
                 echo $comu["coin_tx_texto"];
            }
            echo "</div></div></div>";
        }
    }
    */
	
