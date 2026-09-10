<?php
	/* ============================================================
	   Estrutura do menu do sistema — fonte única.
	   Usada pelo menu padrão (menu.php, layout Metronic) e pelo
	   cabeçalho próprio do módulo de assinatura
	   (assinatura/componentes/layout_header.php, layout Tailwind).
	   Aplica todas as regras de visibilidade — domínio, placas
	   cadastradas, arquivo existente, perfil de acesso e nível — e
	   devolve só dados; cada tela desenha com o próprio HTML.
	   ============================================================ */

	include_once __DIR__."/check_permission.php";

	if(!function_exists("menu_estrutura_do_nivel")){
		/**
		 * Nós do menu, já na ordem de exibição. Cada nó é:
		 *  - ["tipo" => "secao", "chave", "titulo", "paths" (todas as páginas da seção,
		 *     para marcar a ativa), "itens" => [["path", "label", "iti"]], "duas_colunas"]
		 *  - ["tipo" => "link", "path", "label"]  (atalho solto, sem dropdown)
		 */
		function menu_estrutura_do_nivel($nivel): array{
			// Normaliza o nível para comparação estável e sem depender de caixa alta/baixa.
			$nivelNormalizado = mb_strtolower(trim(strval($nivel ?? "")));
			// Considera as duas grafias encontradas no sistema para o mesmo perfil.
			$ehTerceirizado = in_array($nivelNormalizado, ["terceirizado"], true);
			// Nome da seção principal do menu para este perfil.
			$rotuloSecaoPonto = $ehTerceirizado ? "Produção" : "Ponto";
			// Traduz apenas os textos exibidos no menu; caminhos e permissões permanecem inalterados.
			$rotuloMenuPonto = function(string $label) use ($ehTerceirizado): string {
				if(!$ehTerceirizado){
					return $label;
				}
				$mapa = [
					"Registrar Ponto" => "Registrar Produção",
					"Espelhos de Ponto" => "Espelhos de Produção",
					"Integrações de Ponto" => "Integrações de Produção",
					"Pontos" => "Produções"
				];
				return $mapa[$label] ?? $label;
			};

			$camposOcultosProdução = [];
			if(is_int(strpos($_SERVER["REQUEST_URI"], 'dev'))){
				$camposOcultosProdução = [
					"/paineis/disponibilidade.php" 	  => "Disponibilidade",
				];
			}

			$paginas = [
				"cadastros" => [
					"/cadastro_rfid.php" 		=> "RFID",
					"/cadastro_celular.php" 	=> "Celular",
					"/cadastro_empresa.php" 	=> "Empresa/Filial",
					"/cadastro_endosso.php" 	=> "Endosso",
					"/cadastro_feriado.php" 	=> "Feriado",
					"/cadastro_ferias.php" 		=> "Férias",
					"/cadastro_funcionario.php"	=> "Funcionário",
					"/correcao_funcionario.php" => "Corrigir Funcionário",
					"/cadastro_facial.php"      => "Facial",
					"/cadastro_abono.php"		=> "Abono",
					"/cadastro_macro.php" 		=> "Macro",
					"/cadastro_motivo.php" 		=> "Motivo",
					"/cadastro_operacao.php" 	=> "Cargo",
					"/cadastro_parametro.php" 	=> "Parâmetro",
					"/cadastro_placa.php" 		=> "Placas",
					"/cadastro_setor.php" 		=> "Setor",
					"/cadastro_tipo_doc.php" 	=> "Tipo de Documento",
					"/documentos/cadastro_documento.php" => "Gestão de Documentos",
					"/cadastro_usuario.php" 	=> "Usuário",
					"/cadastro_habilidade_tecnica.php" 	=> "Habilidades Técnicas",
					"/cadastro_habilidade_comportamental.php" 	=> "Habilidades Comportamentais",
					"/cadastro_perfil_acesso.php" 	=> "Perfil de Acesso",
					"/cadastro_usuario_perfil.php" 	=> "Permisoes de usuarios",
				   // "/cadastro_comunicado_interno.php" 	=> "Comunicado Interno",
				],
				"ponto" => [
					"/batida_ponto.php"     => "Registrar Ponto",
					"/endosso.php" 			=> "Consultar Endossos",
					"/espelho_ponto.php" 	=> "Espelhos de Ponto",
					"/carregar_ponto.php" 	=> "Integrações de Ponto",
					"/nao_cadastrados.php" 	=> "Não Cadastrados",
					"/nao_conformidade.php" => "Não Conformidades",
					"/ponto_auditoria.php" => "Auditoria",
					"/telas/gerenciar_ajustes.php" => "Gerenciar Ajustes",
					"/trocadeturno/gestao_troca_turno.php" => "Gestão de Turno",
					"/trocadeturno/solicitar_troca_turno.php" => "Troca de Turno"
				],
				"diárias" => [
					"/diarias/gestao_diarias.php" => "Gestão de Diárias",
					"/diarias/bases_diarias.php" => "Bases de Diárias",
					"/diarias/parametros_diarias.php" => "Parâmetros de Diárias"
				],
				"painel" => [
					"/dashboard.php"				=> "Torre de Comando",
					"/paineis/ajustes.php"			=> "Ajustes",
					"/paineis/disponibilidade.php"	=> "Disponibilidade",
					"/paineis/endosso.php"			=> "Endosso",
					"/paineis/jornada.php"			=> "Jornada Aberta",
					"/paineis/nc_juridica.php"		=> "Não Conformidades Jurídicas",
					"/paineis/saldo.php"			=> "Saldo",
					"/paineis/escala_parametro.php"	=> "Escalas"
				] + $camposOcultosProdução,
				"logística" => [
					"/cadastro_poi.php"  => "POI",

				],
				"relatórios" => [
						"/relatorio_pontos.php" => "Pontos"
				],
				"assinatura" => [
					"/assinatura/index.php"             => "Dashboard",
					"/assinatura/nova_assinatura.php"   => "Nova Assinatura",
					"/assinatura/governanca.php"        => "Assinatura com Governança",
					"/assinatura/documentos.php"        => "Documentos",
					"/assinatura/consultar.php"         => "Consultar",
					"/assinatura/cadastro_signatario.php" => "Signatários Externos",
					"#iti"                              => "Validar Assinatura",
				],
				"epi" => [
					"/saude_seguranca/cadastro_epi.php" => "Cadastro de EPI",
					"/saude_seguranca/entrega_epi.php"  => "Entrega de EPI",
					"/saude_seguranca/estoque_epi.php"  => "Estoque de EPI"
				],
				"suporte" => [
					"/suporte/index.php" => "Chamados de Suporte",
				],
				"treinamento" => [
					"/treinamento/cadastro_treinamento.php" => "Gerenciar Treinamentos",
					"/treinamento/treinamento_assistir.php" => "Meus Treinamentos",
				],
			];
			$path = strtolower($_SERVER['REQUEST_URI']);
			$showComunicado = (strpos($path, "/techps") !== false);

			// Gestão de Suporte: visível nos domínios TechPS (produção) e Demo (desenvolvimento).
			// TEMPORARIO (validacao): armazem_paraiba liberado junto.
			if (strpos($path, "/techps") !== false || strpos($path, "/demo") !== false || strpos($path, "/armazem_paraiba") !== false) {
				$paginas["suporte"]["/suporte/gestao.php"] = "Gestão de Suporte";
				$paginas["suporte"]["/suporte/dashboard.php"] = "Dashboard de Suporte";
			}

			if ($showComunicado) {
				$paginas["cadastros"]["/cadastro_comunicado.php"] = "Comunicado";
			}

			// Perfil vinculado ao usuário (se existir)
			$perfilId = 0;
			if(!empty($_SESSION["user_nb_id"])){
				$rsPerfil = query("SELECT perfil_nb_id FROM usuario_perfil WHERE ativo = 1 AND user_nb_id = ? LIMIT 1", "i", [$_SESSION["user_nb_id"]]);
				$rowPerfil = $rsPerfil ? mysqli_fetch_assoc($rsPerfil) : null;
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

			// Verifica se existe pelo menos uma placa cadastrada para mostrar o menu Logística.
			$temPlacaCadastrada = false;
			$rsPlacas = query("SELECT 1 FROM placa LIMIT 1");
			if($rsPlacas && mysqli_num_rows($rsPlacas) > 0){
				$temPlacaCadastrada = true;
			}

			// Remove a seção logística antes do loop se não houver placas cadastradas
			if(!$temPlacaCadastrada){
				unset($paginas["logística"]);
			}

			$secoes = [];
			foreach($paginas as $title => $secao){
				$itens = [];
				$secKey = strtolower($title);
				$parentAllowed = false;
				if($perfilId > 0){
					$parentAllowed = !empty($allowedBySecao[$secKey]) && in_array(ucfirst($title), $allowedBySecao[$secKey]);
				}
				foreach($secao as $key => $value){
					if ($key !== "#iti") {
						$full = __DIR__.$key;
						if(!file_exists($full)){
							continue;
						}
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
					// Só altera a apresentação da seção "ponto" para usuários terceirizados.
					$itens[] = [
						"path"  => $key,
						"label" => ($secKey === "ponto") ? $rotuloMenuPonto($value) : $value,
						"iti"   => ($key === "#iti"),
					];
				}
				// Se houver perfil vinculado, mostra a seção se houver filhos OU se o PAI estiver permitido
				$showSection = true;
				if($perfilId > 0){
					$showSection = (!empty($itens) || $parentAllowed);
				}
				if($showSection){
					$secoes[$title] = [
						"tipo"         => "secao",
						"chave"        => $title,
						// Mantém o título original das demais seções e personaliza apenas a de ponto.
						"titulo"       => ($secKey === "ponto") ? $rotuloSecaoPonto : ucfirst($title),
						"paths"        => array_keys($secao),
						"itens"        => $itens,
						"duas_colunas" => count($itens) > 10,
					];
				}
			}

			// Ordem de exibição das seções (diferente da ordem de definição acima).
			$ordem = ["cadastros", "ponto", "painel", "logística", "epi", "assinatura", "diárias", "suporte", "relatórios", "treinamento"];
			$todas = [];
			foreach($ordem as $chave){
				if(isset($secoes[$chave])){
					$todas[] = $secoes[$chave];
				}
			}

			$niveisOperacionais = ["Motorista", "Ajudante", "Funcionário", "Terceirizado"];
			$isAdmin = is_int(strpos($nivel, "Administrador"));
			$isSuperAdmin = is_int(strpos($nivel, "Super Administrador"));
			if ($isSuperAdmin) {
				return $todas;
			}
			if ($perfilId > 0) {
				// Garante o acesso rápido ao espelho para níveis operacionais quando o perfil não o trouxer explicitamente.
				$temEspelho = false;
				foreach($todas as $sec){
					foreach($sec["itens"] as $item){
						if($item["path"] === "/espelho_ponto.php"){
							$temEspelho = true;
						}
					}
				}
				if(in_array($nivel, $niveisOperacionais) && !$temEspelho){
					$todas[] = ["tipo" => "link", "path" => "/espelho_ponto.php", "label" => $rotuloMenuPonto("Espelhos de Ponto")];
				}
				return $todas;
			}
			if ($isAdmin) {
				return $todas;
			}
			if (is_int(strpos($nivel, "Supervisão"))) {
				return array_values(array_filter($todas, fn($sec) => in_array($sec["chave"], ["cadastros", "ponto"], true)));
			}
			// Menu enxuto para perfis operacionais (inclui terceirizado).
			if(in_array($nivel, $niveisOperacionais)){
				return [
					["tipo" => "link", "path" => "/batida_ponto.php",  "label" => $rotuloMenuPonto("Registrar Ponto")],
					["tipo" => "link", "path" => "/espelho_ponto.php", "label" => $rotuloMenuPonto("Espelhos de Ponto")],
				];
			}

			return [];
		}
	}
