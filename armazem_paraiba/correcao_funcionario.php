<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/

	include_once "utils/utils.php";
	include_once "check_permission.php";

	// Guard de segurança: a página e TODAS as ações são restritas ao Super Administrador.
	// O dispatch de ações acontece dentro de "conecta.php", por isso o guard roda antes.
	if(empty(session_id())){
		ini_set('session.gc_maxlifetime', 12*60*60);
		session_start();
	}

	function ehSuperAdminCorrecao(): bool{
		if(intval($_SESSION["user_nb_superadmin"] ?? 0) === 1){
			return true;
		}
		$nivel = trim(strval($_SESSION["user_tx_nivel"] ?? ""));
		return (bool)preg_match('/super\s+admin/i', $nivel);
	}

	if(!ehSuperAdminCorrecao()){
		http_response_code(403);
		echo "Acesso restrito ao Super Administrador.";
		exit;
	}

	include "conecta.php";

	// Tabela de auditoria das correções de cadastro (criada sob demanda, pois o
	// dispatch de ações acontece dentro de conecta.php antes desta linha)
	function garantirTabelaCorrecaoLog(): void{
		query("CREATE TABLE IF NOT EXISTS correcao_cadastro_log (
			corr_nb_id INT AUTO_INCREMENT PRIMARY KEY,
			corr_nb_entidade INT NOT NULL,
			corr_tx_matricula_antiga VARCHAR(11) NULL,
			corr_tx_matricula_nova VARCHAR(11) NULL,
			corr_tx_contadores TEXT NULL,
			corr_nb_user INT NULL,
			corr_tx_data DATETIME DEFAULT CURRENT_TIMESTAMP
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;");
	}

	// ============================================================
	// HELPERS
	// ============================================================

	function tabelaExiste(string $tabela): bool{
		$res = query("SHOW TABLES LIKE '".mysqli_real_escape_string($GLOBALS["conn"], $tabela)."';");
		return $res && mysqli_num_rows($res) > 0;
	}

	function valorContagem(string $sql): int{
		$res = query($sql);
		if(!$res){
			return 0;
		}
		$linha = mysqli_fetch_assoc($res);
		return intval($linha["c"] ?? 0);
	}

	// Executa UPDATE/INSERT/DELETE e retorna o nº de linhas afetadas
	// (o helper query() usa prepared statements, onde mysqli_affected_rows não funciona)
	function afetados(string $sql): int{
		global $conn;
		mysqli_query($conn, $sql);
		$n = mysqli_affected_rows($conn);
		return ($n === -1) ? 0 : intval($n);
	}

	// Conta os registros vinculados ao funcionário (prévia da migração)
	function contarVinculos(int $id, string $matricula): array{
		$conn = $GLOBALS["conn"];
		$matriculaEsc = mysqli_real_escape_string($conn, $matricula);
		$res = [];

		$res["Pontos (batidas)"] = valorContagem("SELECT COUNT(*) AS c FROM ponto WHERE pont_tx_matricula = '{$matriculaEsc}'");
		$res["Abonos"] = valorContagem("SELECT COUNT(*) AS c FROM abono WHERE abon_tx_matricula = '{$matriculaEsc}'");
		$res["Endossos"] = valorContagem("SELECT COUNT(*) AS c FROM endosso WHERE endo_nb_entidade = {$id}");
		$res["Férias"] = valorContagem("SELECT COUNT(*) AS c FROM ferias WHERE feri_nb_entidade = {$id}");
		$res["Feriados do funcionário"] = valorContagem("SELECT COUNT(*) AS c FROM feriado_funcionario WHERE fefi_nb_entidade = {$id}");
		$res["Celulares"] = valorContagem("SELECT COUNT(*) AS c FROM celular WHERE celu_nb_entidade = {$id}");
		$res["Placas"] = valorContagem("SELECT COUNT(*) AS c FROM placa WHERE plac_nb_entidade = {$id}");
		$res["Documentos"] = valorContagem("SELECT COUNT(*) AS c FROM documento_funcionario WHERE docu_nb_entidade = {$id}");
		$res["Solicitações de ajuste"] = valorContagem("SELECT COUNT(*) AS c FROM solicitacoes_ajuste WHERE id_motorista = {$id}");
		$res["Instâncias de documento"] = valorContagem("SELECT COUNT(*) AS c FROM inst_documento_modulo WHERE inst_nb_entidade = {$id}");
		$res["Assinantes (docs)"] = valorContagem("SELECT COUNT(*) AS c FROM assinantes WHERE enti_nb_id = {$id}");
		$res["Grupos de acesso"] = valorContagem("SELECT COUNT(*) AS c FROM grupo_acesso_funcionarios WHERE grfu_nb_funcionario = {$id}");
		$res["Entregas de EPI"] = valorContagem("SELECT COUNT(*) AS c FROM ss_epi_entrega WHERE ss_e_nb_colaborador_id = {$id}");
		$res["Colaborador Saúde/Seg. (matrícula)"] = valorContagem("SELECT COUNT(*) AS c FROM ss_colaborador WHERE ss_c_tx_matricula = '{$matriculaEsc}'");
		$res["Troca de turno"] = valorContagem("SELECT COUNT(*) AS c FROM solicitacao_troca_horario WHERE soli_nb_entidade = {$id} OR soli_nb_entidade_destino = {$id}");

		foreach(["diaria_deposito" => "depr_nb_entidade", "diaria_consumo" => "dcon_nb_entidade"] as $tabela => $coluna){
			if(tabelaExiste($tabela)){
				$res[($tabela === "diaria_deposito" ? "Diárias (depósito)" : "Diárias (consumo)")] = valorContagem("SELECT COUNT(*) AS c FROM {$tabela} WHERE {$coluna} = {$id}");
			}
		}
		foreach(["setor_responsavel" => "sres_nb_entidade_id", "cargo_responsavel" => "cres_nb_entidade_id", "operacao_responsavel" => "opre_nb_entidade_id"] as $tabela => $coluna){
			if(tabelaExiste($tabela)){
				$res["Responsável de ".str_replace("_responsavel", "", $tabela)] = valorContagem("SELECT COUNT(*) AS c FROM {$tabela} WHERE {$coluna} = {$id}");
			}
		}

		$res = array_filter($res, fn($v) => $v > 0);
		return $res;
	}

	// Troca o segmento de matrícula dentro de um caminho de arquivo (pasta /motoristas/{mat} e sufixo _{mat}.ext)
	function trocarMatriculaNoCaminho(string $path, string $antiga, string $nova): string{
		$path = preg_replace('#/motoristas/'.preg_quote($antiga, '#').'(/|$)#', '/motoristas/'.$nova.'$1', $path);
		$path = preg_replace('#_'.preg_quote($antiga, '#').'(\.[A-Za-z0-9]+)$#', '_'.$nova.'$1', $path);
		return $path;
	}

	function renomearArquivoSeExistir(string $origem, string $destino): void{
		if(is_file($origem) && !file_exists($destino)){
			@rename($origem, $destino);
		}
	}

	// Renomeia pastas/arquivos de motorista e atualiza os caminhos no banco
	function corrigirArquivosMotorista(int $id, string $antiga, string $nova): void{
		$rec = mysqli_fetch_assoc(query("SELECT enti_nb_empresa FROM entidade WHERE enti_nb_id = {$id} LIMIT 1;"));
		$empresa = intval($rec["enti_nb_empresa"] ?? 0);
		$base = __DIR__."/arquivos/empresa/{$empresa}/motoristas";

		// 1) Subpasta por matrícula
		$pastaAntiga = $base."/".$antiga;
		$pastaNova = $base."/".$nova;
		if(is_dir($pastaAntiga) && !is_dir($pastaNova)){
			@rename($pastaAntiga, $pastaNova);
		}

		// 2) Arquivos dentro da subpasta (CNH_{id}_{mat} / FOTO_{id}_{mat}) e flat legados
		//    Corresponde por sufixo de matrícula, independente do id no nome do arquivo
		$pastasAlvo = [];
		if(is_dir($pastaNova)){ $pastasAlvo[] = $pastaNova; }
		if(is_dir($base)){ $pastasAlvo[] = $base; } // também os arquivos flat legados soltos

		$renomearPorSufixo = function(string $pasta, string $prefixo) use ($id, $antiga, $nova): void{
			foreach(glob($pasta."/".$prefixo."_*_".$antiga.".*") ?: [] as $arq){
				$nome = basename($arq);
				$nomeNovo = preg_replace(
					'/_'.preg_quote($antiga, '/').'(\.[A-Za-z0-9]+)$/',
					'_'.$nova.'$1',
					$nome
				);
				if($nomeNovo !== $nome){
					renomearArquivoSeExistir($arq, $pasta."/".$nomeNovo);
				}
			}
		};

		foreach($pastasAlvo as $pasta){
			$renomearPorSufixo($pasta, "CNH");
			$renomearPorSufixo($pasta, "FOTO");
		}

		// 3) Atualiza caminhos no banco (entidade e user)
		$row = mysqli_fetch_assoc(query("SELECT enti_tx_foto, enti_tx_cnhAnexo FROM entidade WHERE enti_nb_id = {$id} LIMIT 1;"));
		$camposPath = [];
		$valoresPath = [];
		foreach(["enti_tx_foto", "enti_tx_cnhAnexo"] as $campo){
			if(!empty($row[$campo])){
				$novoCaminho = trocarMatriculaNoCaminho($row[$campo], $antiga, $nova);
				if($novoCaminho !== $row[$campo]){
					$camposPath[] = $campo;
					$valoresPath[] = mysqli_real_escape_string($GLOBALS["conn"], $novoCaminho);
				}
			}
		}
		if(!empty($camposPath)){
			atualizar("entidade", $camposPath, $valoresPath, $id);
		}

		$u = mysqli_fetch_assoc(query("SELECT user_nb_id, user_tx_foto FROM user WHERE user_nb_entidade = {$id} LIMIT 1;"));
		if(!empty($u) && !empty($u["user_tx_foto"])){
			$novoPath = trocarMatriculaNoCaminho($u["user_tx_foto"], $antiga, $nova);
			if($novoPath !== $u["user_tx_foto"]){
				atualizar("user", ["user_tx_foto"], [mysqli_real_escape_string($GLOBALS["conn"], $novoPath)], $u["user_nb_id"]);
			}
		}
	}

	// Renomeia fotos de placa (uploads/placa/placa_{mat}_*) e atualiza ponto.pont_tx_fotoPlaca
	function corrigirFotosPlaca(string $antiga, string $nova): void{
		$dir = __DIR__."/uploads/placa";
		if(is_dir($dir)){
			foreach(glob($dir."/placa_{$antiga}_*") ?: [] as $arq){
				$novo = dirname($arq)."/placa_{$nova}_".substr(basename($arq), strlen("placa_{$antiga}_"));
				renomearArquivoSeExistir($arq, $novo);
			}
		}
		query(
			"UPDATE ponto SET pont_tx_fotoPlaca = REPLACE(pont_tx_fotoPlaca, '".mysqli_real_escape_string($GLOBALS["conn"], "placa_".$antiga."_")."', '".mysqli_real_escape_string($GLOBALS["conn"], "placa_".$nova."_")."') WHERE pont_tx_matricula = ? AND pont_tx_fotoPlaca LIKE ?",
			"ss",
			[$nova, '%placa_'.$antiga.'\\_%']
		);
	}

	// Reescreve o conteúdo dos CSVs de endosso (md5(enti_id.mes)) com a nova matrícula/nome
	function corrigirCsvEndosso(int $id, string $antiga, string $nova): void{
		$dir = __DIR__."/arquivos/endosso";
		if(!is_dir($dir)){
			return;
		}
		$endossos = mysqli_fetch_all(query(
			"SELECT endo_tx_filename, endo_tx_nome FROM endosso WHERE endo_nb_entidade = {$id};"
		), MYSQLI_ASSOC);
		$nomeNovo = trim(strval($_POST["nome"] ?? ""));

		foreach(($endossos ?: []) as $end){
			$arquivo = $dir."/".$end["endo_tx_filename"].".csv";
			if(!is_file($arquivo)){
				continue;
			}
			$handle = fopen($arquivo, "r");
			$header = fgetcsv($handle);
			$linha = fgetcsv($handle);
			fclose($handle);
			if(empty($header) || empty($linha)){
				continue;
			}
			$dados = @array_combine($header, $linha);
			if(!is_array($dados)){
				continue;
			}
			$dados["endo_tx_matricula"] = $nova;
			if(!empty($nomeNovo)){
				$dados["endo_tx_nome"] = $nomeNovo;
			}
			$linhaNova = [];
			foreach($header as $col){
				$linhaNova[] = $dados[$col] ?? "";
			}
			$fp = fopen($arquivo, "w");
			fputcsv($fp, $header);
			fputcsv($fp, $linhaNova);
			fclose($fp);
		}
	}

	// Atualiza/renomeia os JSONs dos painéis (arquivos/endossos e arquivos/saldos)
	function corrigirJsonPaineis(int $id, string $antiga, string $nova): void{
		$nomeNovo = trim(strval($_POST["nome"] ?? ""));
		foreach(["arquivos/endossos", "arquivos/saldos"] as $raiz){
			$base = __DIR__."/".$raiz;
			if(!is_dir($base)){
				continue;
			}
			foreach(glob($base."/*/*/*.json") ?: [] as $arquivo){
				$basename = basename($arquivo);
				if(strpos($basename, "empresa_") === 0 || $basename === "empresas.json"){
					continue;
				}
				$json = @json_decode(file_get_contents($arquivo), true);
				if(!is_array($json)){
					continue;
				}
				$ehDoFunc = (intval($json["idMotorista"] ?? 0) === $id) || (strval($json["matricula"] ?? "") === $antiga);
				if(!$ehDoFunc){
					continue;
				}
				$json["matricula"] = $nova;
				if(!empty($nomeNovo)){
					$json["nome"] = $nomeNovo;
				}
				file_put_contents($arquivo, json_encode($json, JSON_UNESCAPED_UNICODE));
				if($basename === $antiga.".json"){
					$novoArquivo = dirname($arquivo)."/".$nova.".json";
					if(!file_exists($novoArquivo)){
						@rename($arquivo, $novoArquivo);
					}
				}
			}
		}
	}

	// Atualiza o cabeçalho dos CSVs de espelho (arquivos/endosso_csv/{emp}/{id}/espelho-de-ponto.csv)
	function corrigirEspelhoCsv(int $id, string $antiga, string $nova): void{
		$dir = __DIR__."/arquivos/endosso_csv";
		if(!is_dir($dir)){
			return;
		}
		foreach(glob($dir."/*") ?: [] as $empresa){
			$arquivo = $empresa."/".$id."/espelho-de-ponto.csv";
			if(!is_file($arquivo)){
				continue;
			}
			$conteudo = file_get_contents($arquivo);
			$conteudo = str_replace("Matrícula:".$antiga, "Matrícula:".$nova, $conteudo);
			file_put_contents($arquivo, $conteudo);
		}
	}

	// ============================================================
	// GRID DE SELEÇÃO
	// ============================================================

	function index(){
		verificaPermissao('/correcao_funcionario.php');

		cabecalho("Corrigir Cadastro de Funcionário");

		$camposBuscaHtml = [
			campo("Nome",						"busca_nome_like",		(!empty($_POST["busca_nome_like"])? $_POST["busca_nome_like"]: ""), 3,"","maxlength='65'"),
			campo("Matrícula",					"busca_matricula_like",	(!empty($_POST["busca_matricula_like"])? $_POST["busca_matricula_like"]: ""), 2,"","maxlength='20'"),
			combo_bd("!Empresa",				"busca_empresa",		(!empty($_POST["busca_empresa"])? $_POST["busca_empresa"]: ""), 3, "empresa", "", " ORDER BY empr_tx_nome ASC"),
			combo("Ocupação",					"busca_ocupacao",		(!empty($_POST["busca_ocupacao"])? $_POST["busca_ocupacao"]: ""), 2, ["" => "Todos", "Motorista" => "Motorista", "Ajudante" => "Ajudante", "Funcionário" => "Funcionário", "Terceirizado" => "Terceirizado"]),
			combo("Status", 					"busca_status", 			(isset($_POST["busca_status"])? $_POST["busca_status"]: "ativo"), 2, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"])
		];

		$botoesBusca = [
			botao("Limpar Filtros", "limparFiltros")
		];

		echo abre_form();
		echo linha_form($camposBuscaHtml);
		echo fecha_form([], "<hr><form style='display:inline-block;'>".implode(" ", $botoesBusca)."</form>");

		$gridFields = [
			"CÓDIGO" 				=> "enti_nb_id",
			"NOME" 					=> "enti_tx_nome",
			"MATRÍCULA" 			=> "enti_tx_matricula",
			"CPF" 					=> "enti_tx_cpf",
			"EMPRESA" 				=> "empr_tx_nome",
			"OCUPAÇÃO" 				=> "enti_tx_ocupacao",
			"STATUS" 				=> "enti_tx_status"
		];

		$acoesGrid = criarIconesGrid(
			["glyphicon glyphicon-edit search-button"],
			["correcao_funcionario.php"],
			["visualizarCorrecao"]
		);
		$gridFields["actions"] = $acoesGrid["tags"];

		$camposBusca = [
			"busca_nome_like" 		=> "enti_tx_nome",
			"busca_matricula_like" 	=> "enti_tx_matricula",
			"busca_empresa" 		=> "enti_nb_empresa",
			"busca_ocupacao" 		=> "enti_tx_ocupacao",
			"busca_status" 			=> "enti_tx_status"
		];

		$queryBase = (
			"SELECT enti_nb_id, enti_tx_nome, enti_tx_matricula, enti_tx_cpf, enti_tx_ocupacao, enti_tx_status, empr_tx_nome"
			." FROM entidade"
			." LEFT JOIN empresa ON enti_nb_empresa = empr_nb_id"
		);

		$jsFunctions = implode(" ", $acoesGrid["functions"] ?? []) . "
			var funcoesInternas = function(){
				$('.search-button').attr('title', 'Corrigir cadastro deste funcionário');
			};
		";

		echo gridDinamico("tabelaCorrecaoFuncionario", $gridFields, $camposBusca, $queryBase, $jsFunctions, 12, -1, $gridFields);

		rodape();
	}

	// ============================================================
	// FORMULÁRIO DE CORREÇÃO (cópia editável)
	// ============================================================

	function visualizarCorrecao(){
		global $a_mod;

		$id = intval($_POST["id"] ?? 0);
		if($id <= 0){
			set_status("ERRO: Funcionário inválido.");
			index();
			exit;
		}

		$a_mod = carregar("entidade", $id);
		if(empty($a_mod)){
			set_status("ERRO: Funcionário não encontrado.");
			index();
			exit;
		}

		$a_user = mysqli_fetch_assoc(query("SELECT * FROM user WHERE user_nb_entidade = {$id} LIMIT 1;"));
		$a_mod["user_tx_login"] = strval($a_user["user_tx_login"] ?? "");
		$a_mod["user_nb_id"] = strval($a_user["user_nb_id"] ?? "");

		$enti_campos = [
			"enti_tx_nome" 					=> "nome",
			"enti_tx_nascimento" 			=> "nascimento",
			"enti_tx_status" 				=> "status",
			"enti_tx_cpf" 					=> "cpf",
			"enti_tx_rg" 					=> "rg",
			"enti_tx_civil" 				=> "civil",
			"enti_tx_sexo" 					=> "sexo",
			"enti_tx_racaCor" 				=> "racaCor",
			"enti_tx_tipoSanguineo" 		=> "tipoSanguineo",
			"enti_tx_endereco" 				=> "endereco",
			"enti_tx_numero" 				=> "numero",
			"enti_tx_complemento" 			=> "complemento",
			"enti_tx_bairro" 				=> "bairro",
			"enti_nb_cidade" 				=> "cidade",
			"enti_tx_cep" 					=> "cep",
			"enti_tx_fone1" 				=> "fone1",
			"enti_tx_fone2" 				=> "fone2",
			"enti_tx_email" 				=> "email",
			"enti_tx_referencia" 			=> "referencia",
			"enti_tx_ocupacao" 				=> "ocupacao",
			"enti_nb_salario" 				=> "salario",
			"enti_nb_parametro" 			=> "parametro",
			"enti_tx_obs" 					=> "obs",
			"enti_nb_empresa" 				=> "empresa",
			"enti_setor_id" 				=> "setor",
			"enti_subSetor_id" 				=> "subSetor",
			"enti_tx_jornadaSemanal" 		=> "jornadaSemanal",
			"enti_tx_jornadaSabado" 		=> "jornadaSabado",
			"enti_tx_percHESemanal" 		=> "percHESemanal",
			"enti_tx_percHEEx" 				=> "percHEEx",
			"enti_tx_rgOrgao" 				=> "rgOrgao",
			"enti_tx_rgDataEmissao" 		=> "rgDataEmissao",
			"enti_tx_rgUf" 					=> "rgUf",
			"enti_tx_pai" 					=> "pai",
			"enti_tx_mae" 					=> "mae",
			"enti_tx_conjugue" 				=> "conjugue",
			"enti_tx_tipoOperacao" 			=> "tipoOperacao",
			"enti_tx_subcontratado" 		=> "subcontratado",
			"enti_tx_admissao" 				=> "admissao",
			"enti_tx_desligamento" 			=> "desligamento",
			"enti_tx_pis" 					=> "pis",
			"enti_tx_ctpsNumero" 			=> "ctpsNumero",
			"enti_tx_ctpsSerie" 			=> "ctpsSerie",
			"enti_tx_ctpsUf" 				=> "ctpsUf",
			"enti_tx_tituloNumero" 			=> "tituloNumero",
			"enti_tx_tituloZona" 			=> "tituloZona",
			"enti_tx_tituloSecao" 			=> "tituloSecao",
			"enti_tx_reservista" 			=> "reservista",
			"enti_tx_registroFuncional" 	=> "registroFuncional",
			"enti_tx_OrgaoRegimeFuncional" 	=> "orgaoRegimeFuncional",
			"enti_tx_vencimentoRegistro" 	=> "vencimentoRegistro",
			"enti_tx_cnhRegistro" 			=> "cnhRegistro",
			"enti_tx_cnhValidade" 			=> "cnhValidade",
			"enti_tx_cnhPrimeiraHabilitacao" => "cnhPrimeiraHabilitacao",
			"enti_tx_cnhCategoria" 			=> "cnhCategoria",
			"enti_tx_cnhPermissao" 			=> "cnhPermissao",
			"enti_tx_cnhObs" 				=> "cnhObs",
			"enti_nb_cnhCidade" 			=> "cnhCidade",
			"enti_tx_cnhEmissao" 			=> "cnhEmissao",
			"enti_tx_cnhPontuacao" 			=> "cnhPontuacao",
			"enti_tx_cnhAtividadeRemunerada" => "cnhAtividadeRemunerada",
			"enti_tx_banco" 				=> "setBanco"
		];

		$matriculaOriginal = strval($a_mod["enti_tx_matricula"] ?? "");

		// Mantém valores digitados após erro de validação
		foreach($enti_campos as $bdKey => $postKey){
			if(isset($_POST[$postKey])){
				$a_mod[$bdKey] = $_POST[$postKey];
			}
		}
		if(isset($_POST["postMatricula"])){
			$a_mod["enti_tx_matricula"] = $_POST["postMatricula"];
		}
		if(isset($_POST["login"])){
			$a_mod["user_tx_login"] = $_POST["login"];
		}

		$matricula = strval($a_mod["enti_tx_matricula"] ?? "");
		$contagens = contarVinculos($id, $matriculaOriginal);
		$matriculaAlterada = ($matricula !== $matriculaOriginal && $matricula !== "");

		cabecalho("Corrigir Cadastro de Funcionário");

		echo "<div class='alert alert-warning' role='alert' style='max-width: 1100px;'>
			<b><i class='fa fa-exclamation-triangle'></i> Modo Correção (Super Administrador)</b><br>
			Você está corrigindo o cadastro de <b>".htmlspecialchars($a_mod["enti_tx_nome"] ?? "", ENT_QUOTES, "UTF-8")."</b>
			(matrícula atual: <b>".htmlspecialchars($matricula, ENT_QUOTES, "UTF-8")."</b>).
			Ao alterar a matrícula, todos os registros vinculados (batidas, abonos, endossos, documentos, arquivos etc.) serão atualizados automaticamente e o histórico do funcionário migra para a nova matrícula.
			Esta ação é irreversível.
		</div>";

		if(!empty($contagens)){
			echo "<div class='alert alert-info' role='alert' style='max-width: 1100px;'>
				<b><i class='fa fa-info-circle'></i> Registros vinculados a este funcionário:</b><br>";
			foreach($contagens as $rotulo => $qtde){
				echo "&nbsp;&nbsp;".htmlspecialchars($rotulo, ENT_QUOTES, "UTF-8").": <b>{$qtde}</b><br>";
			}
			echo "</div>";
		}

		$statusOpt = ["ativo" => "Ativo", "inativo" => "Inativo"];
		$estadoCivilOpt = [
			"" => "Selecione",
			"Casado(a)" => "Casado(a)",
			"Solteiro(a)" => "Solteiro(a)",
			"Divorciado(a)" => "Divorciado(a)",
			"Viúvo(a)" => "Viúvo(a)"
		];
		$sexoOpt = ["" => "Selecione", "Feminino" => "Feminino", "Masculino" => "Masculino"];
		$simNaoOpt = ["" => "Selecione", "sim" => "Sim", "nao" => "Não"];

		$camposUsuario = [
			campo("E-mail*", 				"email", 			($a_mod["enti_tx_email"]?? ""),			3, ""),
			campo("Telefone 1*", 			"fone1", 			($a_mod["enti_tx_fone1"]?? ""),			2, "MASCARA_CEL"),
			campo("Telefone 2",  			"fone2", 			($a_mod["enti_tx_fone2"]?? ""),			2, "MASCARA_CEL"),
			campo("Login",					"login", 			($a_mod["user_tx_login"]?? ""),			2, "", "maxlength='65'"),
			combo("Status", 				"status", 			($a_mod["enti_tx_status"]?? "ativo"),	1, $statusOpt)
		];

		$camposPessoais = [
			campo("Matrícula*", 			"postMatricula", 	($a_mod["enti_tx_matricula"]?? ""),	2, "", "maxlength='11'"),
			campo("Nome*", 					"nome", 			($a_mod["enti_tx_nome"]?? ""),			4, "", "maxlength='65'"),
			campo_data("Nascido em*", 		"nascimento", 		($a_mod["enti_tx_nascimento"]?? ""),	2),
			campo("CPF*", 					"cpf", 				($a_mod["enti_tx_cpf"]?? ""),			2, "MASCARA_CPF"),
			campo("RG*", 					"rg", 				($a_mod["enti_tx_rg"]?? ""),			2, "MASCARA_RG", "maxlength='11'"),
			combo("Estado Civil", 			"civil", 			($a_mod["enti_tx_civil"]?? ""),			2, $estadoCivilOpt),
			combo("Sexo", 					"sexo", 			($a_mod["enti_tx_sexo"]?? ""),			2, $sexoOpt),
			campo("Emissor RG", 			"rgOrgao", 			($a_mod["enti_tx_rgOrgao"]?? ""),		2, "", "maxlength='6'"),
			campo_data("Data Emissão RG", 	"rgDataEmissao", 	($a_mod["enti_tx_rgDataEmissao"]?? ""),2),
			combo("UF RG", 					"rgUf", 			($a_mod["enti_tx_rgUf"]?? ""),			2, getUFs()),
			combo("Raça/Cor", 				"racaCor", 			($a_mod["enti_tx_racaCor"]?? ""),		2, [""=>"Selecione","B"=>"Branco","N"=>"Negro","P"=>"Pardo","I"=>"Indígena","A"=>"Amarelo"]),
			combo("Tipo Sanguíneo", 		"tipoSanguineo", 	($a_mod["enti_tx_tipoSanguineo"]?? ""),2, [""=>"Selecione","A+"=>"A+","A-"=>"A-","B+"=>"B+","B-"=>"B-","AB+"=>"AB+","AB-"=>"AB-","O+"=>"O+","O-"=>"O-"]),
			campo("CEP*", 					"cep", 				($a_mod["enti_tx_cep"]?? ""),			2, "MASCARA_CEP"),
			combo_net("Cidade/UF*", 		"cidade", 			($a_mod["enti_nb_cidade"]?? ""),		3, "cidade", "", "", "cida_tx_uf"),
			campo("Bairro*", 				"bairro", 			($a_mod["enti_tx_bairro"]?? ""),		2),
			campo("Endereço*", 				"endereco", 		($a_mod["enti_tx_endereco"]?? ""),		3),
			campo("Número", 				"numero", 			($a_mod["enti_tx_numero"]?? ""),		1, "MASCARA_NUMERO"),
			campo("Complemento", 			"complemento", 		($a_mod["enti_tx_complemento"]?? ""),	2),
			campo("Ponto de Referência", 	"referencia", 		($a_mod["enti_tx_referencia"]?? ""),	3),
			campo("Filiação Pai", 			"pai", 				($a_mod["enti_tx_pai"]?? ""),			3, "", "maxlength='65'"),
			campo("Filiação Mãe", 			"mae", 				($a_mod["enti_tx_mae"]?? ""),			3, "", "maxlength='65'"),
			campo("Nome do Cônjuge", 		"conjugue", 		($a_mod["enti_tx_conjugue"]?? ""),		3, "", "maxlength='65'"),
			textarea("Observações:", 		"obs", 				($a_mod["enti_tx_obs"]?? ""),			12)
		];

		$a_mod["enti_nb_salario"] = str_replace(".", ",", (!empty($a_mod["enti_nb_salario"])? $a_mod["enti_nb_salario"] : ""));
		$campoSalario = campo("Salário*", "salario", $a_mod["enti_nb_salario"], 1, "MASCARA_DINHEIRO");

		$condSubSetor = " ORDER BY sbgr_tx_nome ASC";
		if (!empty($a_mod["enti_setor_id"]) || !empty($_POST["setor"])) {
			$idSetorRef = (!empty($_POST["setor"]) ? intval($_POST["setor"]) : intval($a_mod["enti_setor_id"]));
			$condSubSetor = " AND sbgr_nb_idgrup = ".$idSetorRef." ORDER BY sbgr_tx_nome ASC";
		}

		$cContratual = [
			combo_bd("Empresa*", "empresa", ($a_mod["enti_nb_empresa"]?? ""), 3, "empresa"),
			combo_bd("Setor", "setor", ($a_mod["enti_setor_id"]?? ""), 3, "grupos_documentos", "onchange='filtrarSubSetor(this.value)'"),
			combo_bd("Subsetor", "subSetor", ($a_mod["enti_subSetor_id"]?? ""), 3, "sbgrupos_documentos", "", $condSubSetor),
			combo_bd("!Cargo", "tipoOperacao", (isset($a_mod["enti_tx_tipoOperacao"])? $a_mod["enti_tx_tipoOperacao"]: ""), 3, "operacao"),
			$campoSalario,
			combo("Ocupação*", "ocupacao", ($a_mod["enti_tx_ocupacao"]?? ""), 2, ["" => "Selecione", "Motorista" => "Motorista", "Ajudante" => "Ajudante", "Funcionário" => "Funcionário", "Terceirizado" => "Terceirizado"]),
			campo_data("Dt Admissão*", "admissao", ($a_mod["enti_tx_admissao"]?? ""), 2),
			campo_data("Dt. Desligamento", "desligamento", ($a_mod["enti_tx_desligamento"]?? ""), 2),
			campo("Saldo de Horas", "setBanco", ($a_mod["enti_tx_banco"]?? "00:00"), 1, "MASCARA_HORAS", "placeholder='HH:mm'"),
			combo("Subcontratado", "subcontratado", ($a_mod["enti_tx_subcontratado"]?? ""), 2, $simNaoOpt),
			campo("PIS", "pis", ($a_mod["enti_tx_pis"]?? ""), 2, "MASCARA_NUMERO", "maxlength='11'"),
			campo("CTPS Número", "ctpsNumero", ($a_mod["enti_tx_ctpsNumero"]?? ""), 2, "MASCARA_NUMERO", "maxlength='8'"),
			campo("CTPS Série", "ctpsSerie", ($a_mod["enti_tx_ctpsSerie"]?? ""), 2, "MASCARA_NUMERO", "maxlength='4'"),
			combo("CTPS UF", "ctpsUf", ($a_mod["enti_tx_ctpsUf"]?? ""), 2, getUFs()),
			campo("Título de Eleitor Número", "tituloNumero", ($a_mod["enti_tx_tituloNumero"]?? ""), 2, "MASCARA_NUMERO", "maxlength='12'"),
			campo("Título Zona", "tituloZona", ($a_mod["enti_tx_tituloZona"]?? ""), 2, "MASCARA_NUMERO"),
			campo("Título Seção", "tituloSecao", ($a_mod["enti_tx_tituloSecao"]?? ""), 2, "MASCARA_NUMERO"),
			campo("Reservista", "reservista", ($a_mod["enti_tx_reservista"]?? ""), 2, "MASCARA_NUMERO"),
			campo("Registro Funcional", "registroFuncional", ($a_mod["enti_tx_registroFuncional"]?? ""), 2, "MASCARA_NUMERO"),
			campo("Orgão Registro Funcional", "orgaoRegimeFuncional", ($a_mod["enti_tx_OrgaoRegimeFuncional"]?? ""), 2, "", "maxlength='150'"),
			campo_data("Vencimento Registro", "vencimentoRegistro", ($a_mod["enti_tx_vencimentoRegistro"]?? ""), 2)
		];

		$parametros = mysqli_fetch_all(query(
			"SELECT para_nb_id, para_tx_nome FROM parametro WHERE para_tx_status = 'ativo';"
		), MYSQLI_ASSOC);
		$aux = ["" => "Selecione"];
		foreach(($parametros ?: []) as $parametro){
			$aux[strval($parametro["para_nb_id"])] = $parametro["para_tx_nome"];
		}

		$cJornada = [
			combo("Parâmetros da Jornada", "parametro", ($a_mod["enti_nb_parametro"]?? ""), 6, $aux, "onchange='carregarParametro()'"),
			"<div name='divJornada' style='margin: 15px; width: fit-content; overflow: hidden;'>"
				."<div style='font-weight: bold;'>Jornada</div>"
				.campo_hora("Dias Úteis (Hr/dia)*", "jornadaSemanal", ($a_mod["enti_tx_jornadaSemanal"]?? ""), 2)
				.campo_hora("Sábado*", "jornadaSabado", ($a_mod["enti_tx_jornadaSabado"]?? ""), 2)
			."</div>",
			campo("H.E. Semanal (%)*", "percHESemanal", ($a_mod["enti_tx_percHESemanal"]?? ""), 2, "MASCARA_NUMERO"),
			campo("H.E. Extraordinária (%)*", "percHEEx", ($a_mod["enti_tx_percHEEx"]?? ""), 2, "MASCARA_NUMERO")
		];

		$camposCNH = [
			campo("N° Registro*", "cnhRegistro", ($a_mod["enti_tx_cnhRegistro"]?? ""), 3, "", "maxlength='11'"),
			campo("Categoria*", "cnhCategoria", ($a_mod["enti_tx_cnhCategoria"]?? ""), 3),
			combo_net("Cidade/UF Emissão*", "cnhCidade", ($a_mod["enti_nb_cnhCidade"]?? ""), 3, "cidade", "", "", "cida_tx_uf"),
			campo_data("Data Emissão*", "cnhEmissao", ($a_mod["enti_tx_cnhEmissao"]?? ""), 3),
			campo_data("Validade*", "cnhValidade", ($a_mod["enti_tx_cnhValidade"]?? ""), 3),
			campo_data("1º Habilitação*", "cnhPrimeiraHabilitacao", ($a_mod["enti_tx_cnhPrimeiraHabilitacao"]?? ""), 3),
			campo("Permissão", "cnhPermissao", ($a_mod["enti_tx_cnhPermissao"]?? ""), 3, "", "maxlength='65'"),
			campo("Pontuação", "cnhPontuacao", ($a_mod["enti_tx_cnhPontuacao"]?? ""), 3, "", "maxlength='3'"),
			combo("Atividade Remunerada", "cnhAtividadeRemunerada", ($a_mod["enti_tx_cnhAtividadeRemunerada"]?? ""), 3, $simNaoOpt),
			campo("Observações", "cnhObs", ($a_mod["enti_tx_cnhObs"]?? ""), 3, "", "maxlength='500'")
		];

		$totalVinculos = array_sum(array_values($contagens));
		$textoConfirm = "Isto irá ALTERAR o cadastro do funcionário.";
		if($matriculaAlterada){
			$textoConfirm .= " A matrícula passará de ".$matriculaOriginal." para ".$matricula." e {$totalVinculos} registros vinculados serão migrados (batidas, abonos, endossos, arquivos etc.).";
		}
		$textoConfirm .= " Esta ação é irreversível. Continuar?";

		$botoesCadastro[] = botao(
			"Aplicar Correção",
			"corrigirFuncionario",
			"",
			"",
			"",
			"",
			"btn btn-danger"
		);
		$botoesCadastro[] = criarBotaoVoltar("correcao_funcionario.php");

		echo abre_form();
		echo campo_hidden("id", $id);
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"] ?? ($_SERVER["HTTP_REFERER"] ?? ""));
		echo "<script>
			document.contex_form.onsubmit = function(e){
				var acao = (e.submitter && e.submitter.value) || '';
				if (acao !== 'corrigirFuncionario') { return true; }
				return confirm(".json_encode($textoConfirm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).");
			};
			function filtrarSubSetor(id) {
				var el = document.getElementsByName('subSetor')[0];
				if (!el) return;
				el.innerHTML = '<option value=\'\' selected>Selecione</option>';
				el.disabled = true;
				if (!id) { el.parentElement.style.display = 'none'; return; }
				var url = '".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/select2.php?path=".$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."&tabela=sbgrupos_documentos&limite=200&condicoes=' + encodeURI('AND sbgr_nb_idgrup = '+id);
				$.ajax({ url: url, dataType: 'json' }).done(function(data){
					if (Array.isArray(data)) {
						data.forEach(function(item){
							var o = new Option(item.text, item.id, false, false);
							el.appendChild(o);
						});
						el.disabled = false;
						el.parentElement.style.display = '';
					}
				});
			}
		</script>";

		fieldset("Dados de Usuário");
		echo linha_form($camposUsuario);
		echo "<br>";
		fieldset("Dados Pessoais");
		echo linha_form($camposPessoais);
		echo "<br>";
		fieldset("Dados Contratuais");
		echo linha_form($cContratual);
		echo "<br>";
		fieldset("CONVENÇÃO SINDICAL - JORNADA PADRÃO DO FUNCIONÁRIO");
		echo linha_form($cJornada);
		echo "<br>";
		echo "<div class='cnh-row'>";
		fieldset("CARTEIRA NACIONAL DE HABILITAÇÃO");
		echo linha_form($camposCNH);
		echo "</div>";

		echo "<iframe id=frame_parametro style='display: none;'></iframe>";

		echo fecha_form($botoesCadastro);

		rodape();
	}

	function carregarParametro(){
		if(empty($_GET["parametro"])){
			exit;
		}

		$parametro = mysqli_fetch_assoc(query(
			"SELECT * FROM parametro
				LEFT JOIN escala ON para_nb_id = esca_nb_parametro
				WHERE para_nb_id = {$_GET["parametro"]}
			LIMIT 1;"
		));

		if(empty($parametro)){
			exit;
		}

		echo
			"<script type='text/javascript'>
				var parametroCarregado = ".json_encode($parametro).";

				parent.document.getElementsByName('divJornada')[0].style.display	= ((parametroCarregado.para_tx_tipo == 'escala')? 'none': 'block');
				parent.document.contex_form.jornadaSemanal.value					= parametroCarregado.para_tx_jornadaSemanal;
				parent.document.contex_form.jornadaSabado.value					= parametroCarregado.para_tx_jornadaSabado;
				parent.document.contex_form.percHESemanal.value					= parametroCarregado.para_tx_percHESemanal;
				parent.document.contex_form.percHEEx.value						= parametroCarregado.para_tx_percHEEx;
			</script>"
		;
		exit;
	}

	// ============================================================
	// SALVAR CORREÇÃO + PROPAGAÇÃO DA MATRÍCULA
	// ============================================================

	function corrigirFuncionario(){
		global $conn;

		$id = intval($_POST["id"] ?? 0);
		if($id <= 0){
			set_status("ERRO: Funcionário inválido.");
			index();
			exit;
		}

		$motorista = mysqli_fetch_assoc(query("SELECT * FROM entidade WHERE enti_nb_id = {$id} LIMIT 1;"));
		if(empty($motorista)){
			set_status("ERRO: Funcionário não encontrado.");
			index();
			exit;
		}

		$matriculaAntiga = strval($motorista["enti_tx_matricula"] ?? "");
		$matriculaNova = trim(strval($_POST["postMatricula"] ?? ""));

		// Normalização igual ao cadastro (remove zeros à esquerda)
		if(!in_array($_ENV["CONTEX_PATH"], ["/comav"])){
			while(strlen($matriculaNova) > 0 && $matriculaNova[0] == "0"){
				$matriculaNova = substr($matriculaNova, 1);
			}
		}

		// ---- Validações ----
		if(empty($matriculaNova)){
			$_POST["errorFields"] = ["postMatricula"];
			set_status("ERRO: Matrícula é obrigatória.");
			visualizarCorrecao();
			exit;
		}
		if(strlen($matriculaNova) > 11){
			$_POST["errorFields"] = ["postMatricula"];
			set_status("ERRO: Matrícula com mais de 11 caracteres.");
			visualizarCorrecao();
			exit;
		}
		if($matriculaNova !== $matriculaAntiga){
			$dup = mysqli_fetch_assoc(query(
				"SELECT enti_nb_id FROM entidade WHERE enti_tx_matricula = ? AND enti_nb_id <> ? LIMIT 1",
				"si",
				[$matriculaNova, $id]
			));
			if(!empty($dup)){
				$_POST["errorFields"] = ["postMatricula"];
				set_status("ERRO: Matrícula já cadastrada para outro funcionário.");
				visualizarCorrecao();
				exit;
			}
			$pontoDup = mysqli_fetch_assoc(query(
				"SELECT pont_nb_id FROM ponto WHERE pont_tx_matricula = ? LIMIT 1",
				"s",
				[$matriculaNova]
			));
			if(!empty($pontoDup)){
				$_POST["errorFields"] = ["postMatricula"];
				set_status("ERRO: Já existem batidas de ponto com a matrícula nova (provavelmente de outro funcionário). A correção foi bloqueada para não misturar registros.");
				visualizarCorrecao();
				exit;
			}
			$abonoDup = mysqli_fetch_assoc(query(
				"SELECT abon_nb_id FROM abono WHERE abon_tx_matricula = ? LIMIT 1",
				"s",
				[$matriculaNova]
			));
			if(!empty($abonoDup)){
				$_POST["errorFields"] = ["postMatricula"];
				set_status("ERRO: Já existem abonos com a matrícula nova (provavelmente de outro funcionário).");
				visualizarCorrecao();
				exit;
			}
		}

		$login = trim(strval($_POST["login"] ?? ""));
		if(!empty($login)){
			$userDup = mysqli_fetch_assoc(query(
				"SELECT u.user_nb_id FROM user u JOIN entidade e ON u.user_nb_entidade = e.enti_nb_id
					WHERE u.user_tx_status = 'ativo' AND u.user_tx_login = ? AND e.enti_nb_id <> ? LIMIT 1",
				"si",
				[$login, $id]
			));
			if(!empty($userDup)){
				$_POST["errorFields"] = ["login"];
				set_status("ERRO: Login já cadastrado.");
				visualizarCorrecao();
				exit;
			}
		}

		// ---- Monta os dados da entidade (mesmo mapeamento do cadastro) ----
		$enti_campos = [
			"enti_tx_nome" 					=> "nome",
			"enti_tx_nascimento" 			=> "nascimento",
			"enti_tx_status" 				=> "status",
			"enti_tx_cpf" 					=> "cpf",
			"enti_tx_rg" 					=> "rg",
			"enti_tx_civil" 				=> "civil",
			"enti_tx_sexo" 					=> "sexo",
			"enti_tx_racaCor" 				=> "racaCor",
			"enti_tx_tipoSanguineo" 		=> "tipoSanguineo",
			"enti_tx_endereco" 				=> "endereco",
			"enti_tx_numero" 				=> "numero",
			"enti_tx_complemento" 			=> "complemento",
			"enti_tx_bairro" 				=> "bairro",
			"enti_nb_cidade" 				=> "cidade",
			"enti_tx_cep" 					=> "cep",
			"enti_tx_fone1" 				=> "fone1",
			"enti_tx_fone2" 				=> "fone2",
			"enti_tx_email" 				=> "email",
			"enti_tx_referencia" 			=> "referencia",
			"enti_tx_ocupacao" 				=> "ocupacao",
			"enti_nb_salario" 				=> "salario",
			"enti_nb_parametro" 			=> "parametro",
			"enti_tx_obs" 					=> "obs",
			"enti_nb_empresa" 				=> "empresa",
			"enti_setor_id" 				=> "setor",
			"enti_subSetor_id" 				=> "subSetor",
			"enti_tx_jornadaSemanal" 		=> "jornadaSemanal",
			"enti_tx_jornadaSabado" 		=> "jornadaSabado",
			"enti_tx_percHESemanal" 		=> "percHESemanal",
			"enti_tx_percHEEx" 				=> "percHEEx",
			"enti_tx_rgOrgao" 				=> "rgOrgao",
			"enti_tx_rgDataEmissao" 		=> "rgDataEmissao",
			"enti_tx_rgUf" 					=> "rgUf",
			"enti_tx_pai" 					=> "pai",
			"enti_tx_mae" 					=> "mae",
			"enti_tx_conjugue" 				=> "conjugue",
			"enti_tx_tipoOperacao" 			=> "tipoOperacao",
			"enti_tx_subcontratado" 		=> "subcontratado",
			"enti_tx_admissao" 				=> "admissao",
			"enti_tx_desligamento" 			=> "desligamento",
			"enti_tx_pis" 					=> "pis",
			"enti_tx_ctpsNumero" 			=> "ctpsNumero",
			"enti_tx_ctpsSerie" 			=> "ctpsSerie",
			"enti_tx_ctpsUf" 				=> "ctpsUf",
			"enti_tx_tituloNumero" 			=> "tituloNumero",
			"enti_tx_tituloZona" 			=> "tituloZona",
			"enti_tx_tituloSecao" 			=> "tituloSecao",
			"enti_tx_reservista" 			=> "reservista",
			"enti_tx_registroFuncional" 	=> "registroFuncional",
			"enti_tx_OrgaoRegimeFuncional" 	=> "orgaoRegimeFuncional",
			"enti_tx_vencimentoRegistro" 	=> "vencimentoRegistro",
			"enti_tx_cnhRegistro" 			=> "cnhRegistro",
			"enti_tx_cnhValidade" 			=> "cnhValidade",
			"enti_tx_cnhPrimeiraHabilitacao" => "cnhPrimeiraHabilitacao",
			"enti_tx_cnhCategoria" 			=> "cnhCategoria",
			"enti_tx_cnhPermissao" 			=> "cnhPermissao",
			"enti_tx_cnhObs" 				=> "cnhObs",
			"enti_nb_cnhCidade" 			=> "cnhCidade",
			"enti_tx_cnhEmissao" 			=> "cnhEmissao",
			"enti_tx_cnhPontuacao" 			=> "cnhPontuacao",
			"enti_tx_cnhAtividadeRemunerada" => "cnhAtividadeRemunerada",
			"enti_tx_banco" 				=> "setBanco"
		];

		$novoMotorista = [];
		foreach($enti_campos as $bdKey => $postKey){
			if(isset($_POST[$postKey])){
				$novoMotorista[$bdKey] = $_POST[$postKey];
			}
		}
		$novoMotorista["enti_tx_matricula"] = $matriculaNova;

		if(isset($novoMotorista["enti_nb_salario"])){
			$novoMotorista["enti_nb_salario"] = str_replace([".", ","], ["", "."], $novoMotorista["enti_nb_salario"]);
		}

		$invalidDates = ["0000-00-00", "0001-01-01"];
		$dateKeys = ["enti_tx_nascimento", "enti_tx_rgDataEmissao", "enti_tx_admissao", "enti_tx_desligamento", "enti_tx_vencimentoRegistro", "enti_tx_cnhValidade", "enti_tx_cnhPrimeiraHabilitacao", "enti_tx_cnhEmissao"];
		foreach($dateKeys as $dk){
			if(array_key_exists($dk, $novoMotorista)){
				$v = $novoMotorista[$dk];
				if($v === "" || in_array($v, $invalidDates, true)){
					$novoMotorista[$dk] = null;
				}
			}
		}

		$novoMotorista["enti_nb_userAtualiza"] = $_SESSION["user_nb_id"];
		$novoMotorista["enti_tx_dataAtualiza"] = date("Y-m-d H:i:s");

		// Escapa valores de texto antes do UPDATE (o helper atualizar() não escapa)
		foreach($novoMotorista as $k => $v){
			if($v === null || is_int($v)){
				continue;
			}
			if(strpos($k, "_nb_") !== false){
				$novoMotorista[$k] = is_numeric($v) ? $v : intval($v);
			}elseif(is_string($v)){
				$novoMotorista[$k] = mysqli_real_escape_string($conn, $v);
			}
		}

		$contadores = [];

		mysqli_begin_transaction($conn);
		try{
			// 1) Atualiza entidade
			atualizar("entidade", array_keys($novoMotorista), array_values($novoMotorista), $id);
			if(!empty($GLOBALS["last_sql_error"])){
				throw new RuntimeException("Falha ao atualizar entidade: ".$GLOBALS["last_sql_error"]);
			}

			// 2) Atualiza usuário (login = nova matrícula se não informado outro; senha preservada)
			$a_user = mysqli_fetch_assoc(query("SELECT * FROM user WHERE user_nb_entidade = {$id} LIMIT 1;"));
			if(!empty($a_user)){
				$newUser = [
					"user_tx_nome" 				=> $_POST["nome"],
					"user_tx_login" 			=> (!empty($login) ? $login : $matriculaNova),
					"user_tx_nivel" 			=> $_POST["ocupacao"],
					"user_tx_status" 			=> $_POST["status"],
					"user_tx_nascimento" 		=> $_POST["nascimento"],
					"user_tx_cpf" 				=> $_POST["cpf"],
					"user_tx_rg" 				=> $_POST["rg"],
					"user_nb_cidade" 			=> $_POST["cidade"],
					"user_tx_email" 			=> $_POST["email"],
					"user_tx_fone" 				=> $_POST["fone1"],
					"user_nb_empresa" 			=> $_POST["empresa"],
					"user_nb_userAtualiza" 		=> $_SESSION["user_nb_id"],
					"user_tx_dataAtualiza" 		=> date("Y-m-d H:i:s")
				];
				foreach($newUser as $key => $value){
					if($value === "" || $value === null){
						unset($newUser[$key]);
					}elseif(strpos($key, "_nb_") !== false){
						$newUser[$key] = is_numeric($value) ? $value : intval($value);
					}elseif(is_string($value)){
						$newUser[$key] = mysqli_real_escape_string($conn, $value);
					}
				}
				atualizar("user", array_keys($newUser), array_values($newUser), $a_user["user_nb_id"]);
				if(!empty($GLOBALS["last_sql_error"])){
					throw new RuntimeException("Falha ao atualizar usuário: ".$GLOBALS["last_sql_error"]);
				}
			}

			// 3) Propaga a matrícula nas tabelas que usam texto
			if($matriculaNova !== $matriculaAntiga){
				$escNova = mysqli_real_escape_string($conn, $matriculaNova);
				$escAntiga = mysqli_real_escape_string($conn, $matriculaAntiga);

				$contadores["ponto"] = afetados("UPDATE ponto SET pont_tx_matricula = '{$escNova}' WHERE pont_tx_matricula = '{$escAntiga}'");
				$contadores["abono"] = afetados("UPDATE abono SET abon_tx_matricula = '{$escNova}' WHERE abon_tx_matricula = '{$escAntiga}'");
				$contadores["endosso"] = afetados("UPDATE endosso SET endo_tx_matricula = '{$escNova}' WHERE endo_nb_entidade = {$id}");
				$contadores["ss_colaborador"] = 0;
				$contadores["troca_solicitante"] = 0;
				$contadores["troca_destino"] = 0;

				if(tabelaExiste("ss_colaborador")){
					$contadores["ss_colaborador"] = afetados("UPDATE ss_colaborador SET ss_c_tx_matricula = '{$escNova}' WHERE ss_c_tx_matricula = '{$escAntiga}'");
				}

				if(tabelaExiste("solicitacao_troca_horario")){
					$contadores["troca_solicitante"] = afetados("UPDATE solicitacao_troca_horario SET soli_tx_matricula_solicitante = '{$escNova}' WHERE soli_nb_entidade = {$id} AND soli_tx_matricula_solicitante = '{$escAntiga}'");
					$contadores["troca_destino"] = afetados("UPDATE solicitacao_troca_horario SET soli_tx_matricula_trabalhara = '{$escNova}' WHERE soli_nb_entidade_destino = {$id} AND soli_tx_matricula_trabalhara = '{$escAntiga}'");
				}

				// 4) Arquivos em disco + caminhos no banco
				corrigirArquivosMotorista($id, $matriculaAntiga, $matriculaNova);
				corrigirFotosPlaca($matriculaAntiga, $matriculaNova);
				corrigirCsvEndosso($id, $matriculaAntiga, $matriculaNova);
				corrigirJsonPaineis($id, $matriculaAntiga, $matriculaNova);
				corrigirEspelhoCsv($id, $matriculaAntiga, $matriculaNova);
			}

			// 5) Log de auditoria (falha aqui não desfaz a correção)
			try{
				garantirTabelaCorrecaoLog();
				$resLog = inserir(
					"correcao_cadastro_log",
					["corr_nb_entidade", "corr_tx_matricula_antiga", "corr_tx_matricula_nova", "corr_tx_contadores", "corr_nb_user", "corr_tx_data"],
					[$id, $matriculaAntiga, $matriculaNova, json_encode($contadores), $_SESSION["user_nb_id"], date("Y-m-d H:i:s")]
				);
			}catch(Throwable $eLog){
				$resLog = [new Exception($eLog->getMessage())];
			}

			mysqli_commit($conn);

			$msg = "Correção aplicada com sucesso. ";
			if($matriculaNova !== $matriculaAntiga){
				$msg .= "Matrícula {$matriculaAntiga} → {$matriculaNova}. Registros migrados: ".json_encode(array_filter($contadores));
			}
			if(intval($_SESSION["user_nb_entidade"] ?? 0) === $id){
				$msg .= " Como você é o próprio funcionário corrigido, faça logout e entre novamente com a nova matrícula.";
			}
			set_status($msg);
		}catch(Throwable $e){
			mysqli_rollback($conn);
			set_status("ERRO ao aplicar correção: ".$e->getMessage());
			visualizarCorrecao();
			exit;
		}

		index();
		exit;
	}
