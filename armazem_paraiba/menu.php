<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/

	function verificarAtividade($paginasAtivas) {
		foreach ($paginasAtivas as $pagina) {
			if (strpos($_SERVER["REQUEST_URI"], $pagina) !== false) {
				return "active";
			}
		}
		return "";
	}

	function mostrarMenuDoNivel($nivel): string{
		global $CONTEX;

		$paginas = [
			"cadastros" => [
				"/cadastro_empresa.php" 	=> "Empresa/Filial", 
				"/cadastro_endosso.php" 	=> "Endosso", 
				"/cadastro_feriado.php" 	=> "Feriado", 
				"/cadastro_motorista.php" 	=> "Funcionário", 
				"/cadastro_macro.php" 		=> "Macro",
				"/cadastro_motivo.php" 		=> "Motivo", 
				"/cadastro_parametro.php" 	=> "Parâmetro", 
				"/cadastro_usuario.php" 	=> "Usuário" 
			],
			"ponto" => [
				"/endosso.php" 			=> "Endossos", 
				"/espelho_ponto.php" 	=> "Espelhos de Ponto", 
				"/carregar_ponto.php" 	=> "Integrações de Ponto", 
				"/nao_conformidade.php" => "Não Conformidades", 
				"/nao_cadastrados.php" 	=> "Não cadastrados"
			],
			"painel" => [
				"/paineis/saldo.php"	  => "Saldo",
				"/paineis/endosso.php"	  => "Endosso",
				// "/paineis/jornada.php" 	  => "Jornada Aberta",
				// "/paineis/nc_juridica.php"=> "Não Conformidades Juridicas"
			],
			"suporte" => [
				"/#" 		=> "Perguntas Frequentes", 
				"/doc.php" 	=> "Ver Documentação"
			]
		];

		$menus = [
			"cadastros" => "",
			"ponto" => "",
			"painel" => "",
			"suporte" => "",
		];
	
		foreach($paginas as $title => $secao){
			$menus[$title] = "
				<li class='menu-dropdown classic-menu-dropdown ".verificarAtividade($secao)."'>
					<a href='javascript:;'>".ucfirst($title)."<span class='arrow'></span></a>
					<ul class='dropdown-menu pull-left'>"
			;
			foreach($secao as $key => $value){
				$menus[$title] .= "<li class=''><a href='".$CONTEX["path"].$key."' class='nav-link'>".$value."</a></li>";
			}
			$menus[$title] .= "</ul></li>";
		}
		
		if(is_bool(strpos($_SERVER["REQUEST_URI"], 'dev_'))){
			unset($menus["suporte"]);
		}
	
		$menuMotorista = 
			"<li class=''><a href='".$CONTEX["path"]."/batida_ponto.php'		class='nav-link'>Registrar Ponto</a></li>
			 <li class=''><a href='".$CONTEX["path"]."/cadastro_usuario.php'	class='nav-link'>Usuário</a></li>
			 <li class=''><a href='".$CONTEX["path"]."/espelho_ponto.php'		class='nav-link'>Espelhos de Ponto</a></li>"
		;

		if (is_int(strpos($nivel, "Administrador")) || is_int(strpos($nivel, "Super Administrador"))) {
			return $menus["cadastros"].$menus["ponto"].$menus["painel"].($menus["suporte"]?? "");
		}
		// if (is_int(strpos($nivel, "Funcionário"))) {
		// 	return $menus["cadastros"].$menus["ponto"];
		// }
		if(in_array($nivel, ["Motorista", "Ajudante", "Funcionário"])){
			return $menuMotorista;
		}
	}

	echo 
		"<style>
			.menu-dropdown.active > a {
				background-color: #8c98a6 !important;
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
	