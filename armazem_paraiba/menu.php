<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/

    include "load_env.php";
    include_once "conecta.php";
    include_once "check_permission.php";

	function verificarAtividade($paginasAtivas) {
		foreach ($paginasAtivas as $pagina){
			if (is_int(strpos($_SERVER["REQUEST_URI"], $_ENV["CONTEX_PATH"].$pagina))) {
				return "active";
			}
		}
		return "";
	}

    function mostrarMenuDoNivel($nivel): string{
        global $CONTEX;
		
		$camposOcultosProdução = [];
		if(is_int(strpos($_SERVER["REQUEST_URI"], 'dev'))){
			$camposOcultosProdução = [
				"/paineis/disponibilidade.php" 	  => "Disponibilidade",
			];
		}
		

        $paginas = [
            "cadastros" => [
                "/cadastro_celular.php" 	=> "Celular",
                "/cadastro_empresa.php" 	=> "Empresa/Filial",
                "/cadastro_endosso.php" 	=> "Endosso",
                "/cadastro_feriado.php" 	=> "Feriado",
                "/cadastro_ferias.php" 		=> "Férias",
                "/cadastro_funcionario.php"	=> "Funcionário",
                "/cadastro_abono.php"		=> "Abono",
                "/cadastro_macro.php" 		=> "Macro",
                "/cadastro_motivo.php" 		=> "Motivo",
                "/cadastro_operacao.php" 	=> "Cargo",
                "/cadastro_parametro.php" 	=> "Parâmetro",
                "/cadastro_placa.php" 		=> "Placas",
                "/cadastro_setor.php" 		=> "Setor",
                "/cadastro_tipo_doc.php" 	=> "Tipo de Documento",
                "/cadastro_usuario.php" 	=> "Usuário",            
                "/cadastro_habilidade_tecnica.php" 	=> "Habilidades Técnicas",
                "/cadastro_habilidade_comportamental.php" 	=> "Habilidades Comportamentais",
                "/cadastro_perfil_acesso.php" 	=> "Perfil de Acesso",
                "/cadastro_usuario_perfil.php" 	=> "Permisoes de usuarios",


            ],
			"ponto" => [
				"/batida_ponto.php"     => "Registrar Ponto",
				"/endosso.php" 			=> "Consultar Endossos",
				"/espelho_ponto.php" 	=> "Espelhos de Ponto",
				"/carregar_ponto.php" 	=> "Integrações de Ponto",
				"/nao_cadastrados.php" 	=> "Não Cadastrados",
				"/nao_conformidade.php" => "Não Conformidades"
			],
			"painel" => [
				"/paineis/ajustes.php"			=> "Ajustes",
				"/paineis/disponibilidade.php"	=> "Disponibilidade",
				"/paineis/endosso.php"			=> "Endosso",
				"/paineis/jornada.php"			=> "Jornada Aberta",
				"/paineis/nc_juridica.php"		=> "Não Conformidades Jurídicas",
				"/paineis/saldo.php"			=> "Saldo"
			] + $camposOcultosProdução,
			"relatórios" => [
					"/relatorio_pontos.php" => "Pontos"
			],
			// "suporte" => [
			// 	"/#" 		=> "Perguntas Frequentes", 
			// 	"/doc.php" 	=> "Ver Documentação"
			// ]
		];
$path = strtolower($_SERVER['REQUEST_URI']);  
$showComunicado = (strpos($path, "/techps") !== false);

if ($showComunicado) {
    $paginas["cadastros"]["/cadastro_comunicado.php"] = "Comunicado";
}



        $menus = [
            "cadastros" => "",
            "ponto" => "",
            "painel" => "",
            "relatórios" => "",
            "suporte" => "",
        ];
        // Perfil vinculado ao usuário (se existir)
        $perfilId = 0;
        if(!empty($_SESSION["user_nb_id"])){
            $rowPerfil = mysqli_fetch_assoc(query("SELECT perfil_nb_id FROM usuario_perfil WHERE ativo = 1 AND user_nb_id = ? LIMIT 1", "i", [$_SESSION["user_nb_id"]]));
            if(!empty($rowPerfil["perfil_nb_id"])) $perfilId = (int)$rowPerfil["perfil_nb_id"];
        }

        $allowedBySecao = [];
        $labelsIndex = [];
        foreach($paginas as $secName => $secao){
            foreach($secao as $key => $label){
                if(!isset($labelsIndex[$label])) $labelsIndex[$label] = [];
                $labelsIndex[$label][] = strtolower($secName);
            }
        }
        if($perfilId > 0){
            $rs = query(
                "SELECT m.menu_tx_label FROM perfil_menu_item p"
                ." JOIN menu_item m ON m.menu_nb_id = p.menu_nb_id"
                ." WHERE p.perfil_nb_id = ? AND p.perm_ver = 1 AND m.menu_tx_ativo = 1",
                "i",
                [$perfilId]
            );
            while($rs && ($r = mysqli_fetch_assoc($rs))){
                $label = $r["menu_tx_label"];
                if(!empty($labelsIndex[$label])){
                    foreach($labelsIndex[$label] as $sec){
                        if(!isset($allowedBySecao[$sec])) $allowedBySecao[$sec] = [];
                        $allowedBySecao[$sec][] = $label;
                    }
                }
            }
        }

        $iconSection = [
            "cadastros" => "fa fa-folder-open",
            "ponto" => "fa fa-clock",
            "painel" => "fa fa-tachometer",
            "relatórios" => "fa fa-file-alt",
            "suporte" => "fa fa-life-ring",
        ];
        $iconMap = [
            "Celular" => "fa fa-mobile",
            "Empresa/Filial" => "fa fa-building",
            "Endosso" => "fa fa-check-circle",
            "Feriado" => "fa fa-calendar-day",
            "Férias" => "fa fa-umbrella-beach",
            "Funcionário" => "fa fa-user",
            "Macro" => "fa fa-sitemap",
            "Motivo" => "fa fa-comment-dots",
            "Operação" => "fa fa-cogs",
            "Parâmetro" => "fa fa-sliders-h",
            "Placas" => "fa fa-id-badge",
            "Setor" => "fa fa-layer-group",
            "Tipo de Documento" => "fa fa-file",
            "Usuário" => "fa fa-user-cog",
            "Habilidades Técnicas" => "fa fa-tools",
            "Habilidades Comportamentais" => "fa fa-users",
            "Perfil de Acesso" => "fa fa-shield-alt",
            "Permisoes de usuarios" => "fa fa-user-shield",
            "Comunicado" => "fa fa-bullhorn",
            "Registrar Ponto" => "fa fa-clock",
            "Consultar Endossos" => "fa fa-clipboard-check",
            "Espelhos de Ponto" => "fa fa-file-alt",
            "Integrações de Ponto" => "fa fa-exchange-alt",
            "Não Cadastrados" => "fa fa-user-slash",
            "Não Conformidades" => "fa fa-exclamation-triangle",
            "Ajustes" => "fa fa-wrench",
            "Disponibilidade" => "fa fa-calendar-check",
            "Jornada Aberta" => "fa fa-road",
            "Não Conformidades Jurídicas" => "fa fa-balance-scale",
            "Saldo" => "fa fa-chart-line",
            "Pontos" => "fa fa-list-alt"
        ];
        foreach($paginas as $title => $secao){
            $children = "";
            $secKey = strtolower($title);
            $parentAllowed = false;
            if($perfilId > 0){
                $parentAllowed = !empty($allowedBySecao[$secKey]) && in_array(ucfirst($title), $allowedBySecao[$secKey]);
            }
            foreach($secao as $key => $value){
                $full = __DIR__.$key;
                if(!file_exists($full)){
                    continue;
                }
                // Filtra filhos por permissões diretas do menu
                if($perfilId > 0){
                    $nivelUser = $_SESSION["user_tx_nivel"] ?? "";
                    $isAdminUser = (is_int(strpos($nivelUser, "Administrador")) || is_int(strpos($nivelUser, "Super Administrador")));
                    if(function_exists('temPermissaoMenu') && !$isAdminUser){
                        if(!temPermissaoMenu($key)){
                            continue;
                        }
                    }
                }
                $children .= "<li class=''><a href='".$CONTEX["path"].$key."' class='nav-link'> ".$value."</a></li>";
            }
            // Se houver perfil vinculado, mostra a seção se houver filhos OU se o PAI estiver permitido
            $showSection = true;
            if($perfilId > 0){
                $showSection = ($children !== "" || $parentAllowed);
            }
            if($showSection){
                $menus[$title] = "
                    <li class='menu-dropdown classic-menu-dropdown ".verificarAtividade(array_keys($secao))."'>
                        <a href='javascript:;'> ".ucfirst($title)."<span class='arrow'></span></a>
                        <ul class='dropdown-menu pull-left'>".$children."</ul></li>";
            } else {
                $menus[$title] = "";
            }
        }
		
		if(is_bool(strpos($_SERVER["REQUEST_URI"], 'dev'))){
			// unset($menus["relatórios"]);
			unset($menus["suporte"]);
		}
	
        $menuMotorista = 
            "<li class=''><a href='".$CONTEX["path"]."/batida_ponto.php'		class='nav-link'> Registrar Ponto</a></li>
             <li class=''><a href='".$CONTEX["path"]."/espelho_ponto.php'		class='nav-link'> Espelhos de Ponto</a></li>"
        ;

        $isAdmin = is_int(strpos($nivel, "Administrador"));
        $isSuperAdmin = is_int(strpos($nivel, "Super Administrador"));
        $menusConcat = $menus["cadastros"].$menus["ponto"].$menus["painel"].($menus["suporte"]?? "").($menus["relatórios"] ?? "");
        if ($isSuperAdmin) {
            return $menusConcat;
        }
        if ($perfilId > 0) {
            if(in_array($nivel, ["Motorista", "Ajudante", "Funcionário"]) && strpos($menusConcat, "/espelho_ponto.php") === false){
                $menusConcat .= "<li class=''><a href='".$CONTEX["path"]."/espelho_ponto.php' class='nav-link'> Espelhos de Ponto</a></li>";
            }
            return $menusConcat;
        }
        if ($isAdmin) {
            return $menusConcat;
        }
        if (is_int(strpos($nivel, "Supervisão"))) {
            return $menus["cadastros"].$menus["ponto"];
        }
        if(in_array($nivel, ["Motorista", "Ajudante", "Funcionário"])){
            return $menuMotorista;
        }

		return "";
	}

echo 
		"<style>
			.menu-dropdown.active > a {
				background-color: #8c98a6 !important;
			}
			.classic-menu-dropdown ul.dropdown-menu li a i {
				color: var(--sec-icon-color, inherit);
			}
		</style>"
	;

    echo 
        "<!-- INICIO HEADER MENU -->"
            ."<div class='page-header-menu'>"
                ."<div class='container-fluid'>"
                ."<!-- INICIO MEGA MENU -->"
                    ."<!-- DOC: Apply 'hor-menu-light' class after the 'hor-menu' class below to have a horizontal menu with white background -->"
                    ."<!-- DOC: Remove data-hover='dropdown' and data-close-others='true' attributes below to disable the dropdown opening on mouse hover -->"
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

    echo "<script>(function(){var items=document.querySelectorAll('.classic-menu-dropdown');for(var i=0;i<items.length;i++){var li=items[i];var ai=li.querySelector(':scope > a i');if(ai){var c=window.getComputedStyle(ai).color;li.style.setProperty('--sec-icon-color',c);}}})();</script>";

    $hasTable = false;
    if(isset($conn)){
        $chk = mysqli_query($conn, "SHOW TABLES LIKE 'comunicados'");
        if($chk){ $hasTable = mysqli_num_rows($chk) > 0; }
    }
    if($hasTable && !empty($_SESSION["user_tx_nivel"])){
        $destino = (is_int(strpos($_SESSION["user_tx_nivel"], "Administrador")) || is_int(strpos($_SESSION["user_tx_nivel"], "Super Administrador")))? "Administrador": "Funcionário";
        $comu = mysqli_fetch_assoc(query("SELECT comu_tx_texto FROM comunicados WHERE comu_tx_destino = ? ORDER BY comu_nb_id DESC LIMIT 1", "s", [$destino]));
        if(!empty($comu["comu_tx_texto"])){
            echo "<div class='container-fluid' style='margin-top:10px'><div class='alert alert-info' role='alert' style='display:flex; align-items:flex-start; gap:10px'><i class='fa fa-info-circle' style='font-size:18px'></i><div style='flex:1'>".nl2br(htmlspecialchars($comu["comu_tx_texto"])) ."</div></div></div>";
        }
    }
	
