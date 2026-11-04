<?php
/**
 * Módulo POP — Biblioteca de Procedimentos Operacionais Padrão.
 *
 * Reaproveita as funções do módulo de Termos (tabela modelo_termo, geração de
 * PDF com TCPDF, permissões e logs) e mantém a classificação POP em campos
 * próprios. Documentos enviados diretamente em PDF ficam na tabela
 * pop_documento e também são servidos com controle de permissão/token.
 */

if(!defined("POP_DIR")){
	define("POP_DIR", __DIR__);
}
if(!defined("POP_MODULO_DIR")){
	define("POP_MODULO_DIR", dirname(__DIR__, 2));
}

include_once dirname(__DIR__) . "/termos/funcoes_termos.php";

if(!function_exists("pop_h")){
	function pop_h(string $valor): string {
		return htmlspecialchars($valor, ENT_QUOTES, "UTF-8");
	}
}

if(!function_exists("pop_ensure_schema")){
	function pop_ensure_schema(): void {
		global $conn;
		static $executado = false;
		if($executado || !($conn instanceof mysqli)){
			return;
		}
		$executado = true;

		// Garante também a classificação POP nos modelos de termo.
		termos_ensure_tables($conn);

		mysqli_query($conn, "CREATE TABLE IF NOT EXISTS pop_documento (
			pop_nb_id INT AUTO_INCREMENT PRIMARY KEY,
			pop_tx_titulo VARCHAR(255) NOT NULL,
			pop_tx_codigo VARCHAR(60) NULL,
			pop_nb_setor INT NULL,
			pop_tx_versao VARCHAR(30) NULL,
			pop_dt_vigencia DATE NULL,
			pop_tx_objetivo TEXT NULL,
			pop_tx_responsavel VARCHAR(255) NULL,
			pop_tx_tags VARCHAR(255) NULL,
			pop_tx_arquivo VARCHAR(500) NOT NULL,
			pop_tx_arquivo_nome VARCHAR(255) NULL,
			pop_tx_fluxograma VARCHAR(500) NULL,
			pop_tx_fluxograma_nome VARCHAR(255) NULL,
			pop_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
			pop_nb_userCadastro INT NULL,
			pop_tx_dataCadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
			pop_nb_userAtualiza INT NULL,
			pop_tx_dataAtualiza DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
			KEY idx_setor (pop_nb_setor),
			KEY idx_status (pop_tx_status)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		mysqli_query($conn, "CREATE TABLE IF NOT EXISTS pop_envio (
			pev_nb_id INT AUTO_INCREMENT PRIMARY KEY,
			pev_tx_token VARCHAR(64) NOT NULL,
			pev_tx_origem ENUM('termo','upload') NOT NULL,
			pev_nb_doc INT NOT NULL,
			pev_nb_entidade INT NULL,
			pev_tx_nome VARCHAR(255) NULL,
			pev_tx_email VARCHAR(255) NULL,
			pev_tx_mensagem TEXT NULL,
			pev_nb_user_envio INT NULL,
			pev_dt_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
			pev_dt_expira DATETIME NULL,
			pev_dt_visualizacao DATETIME NULL,
			pev_nb_visualizacoes INT NOT NULL DEFAULT 0,
			pev_tx_status ENUM('ativo','cancelado') NOT NULL DEFAULT 'ativo',
			UNIQUE KEY uniq_token (pev_tx_token),
			KEY idx_doc (pev_tx_origem, pev_nb_doc),
			KEY idx_entidade (pev_nb_entidade)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		mysqli_query($conn, "CREATE TABLE IF NOT EXISTS pop_favorito (
			popf_nb_id INT AUTO_INCREMENT PRIMARY KEY,
			popf_nb_user INT NOT NULL,
			popf_tx_origem ENUM('termo','upload') NOT NULL,
			popf_nb_doc INT NOT NULL,
			popf_tx_dataCadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_favorito (popf_nb_user, popf_tx_origem, popf_nb_doc),
			KEY idx_user (popf_nb_user)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}
}

if(!function_exists("pop_autorizar")){
	function pop_autorizar(): void {
		termos_verificar_permissao("/documentos/pop/index.php");
	}
}

if(!function_exists("pop_listar_setores")){
	function pop_listar_setores(bool $incluirInativos = false): array {
		$where = $incluirInativos ? "" : "WHERE grup_tx_status = 'ativo'";
		$res = query("SELECT grup_nb_id, grup_tx_nome, grup_tx_status
			FROM grupos_documentos
			{$where}
			ORDER BY grup_tx_nome ASC");
		$out = [];
		while($res instanceof mysqli_result && ($row = mysqli_fetch_assoc($res))){
			$id = intval($row["grup_nb_id"] ?? 0);
			if($id <= 0){
				continue;
			}
			$out[$id] = [
				"id" => $id,
				"nome" => strval($row["grup_tx_nome"] ?? ""),
				"status" => strval($row["grup_tx_status"] ?? "inativo")
			];
		}
		return $out;
	}
}

if(!function_exists("pop_extensao")){
	function pop_extensao(string $caminho): string {
		$caminho = trim(str_replace("\\", "/", $caminho));
		if($caminho === ""){
			return "";
		}
		$base = basename($caminho);
		$pos = strrpos($base, ".");
		if($pos === false){
			return "";
		}
		return strtolower(substr($base, $pos + 1));
	}
}

if(!function_exists("pop_tipo_fluxograma")){
	function pop_tipo_fluxograma(string $caminho): string {
		return pop_extensao($caminho) === "pdf" ? "pdf" : "imagem";
	}
}

if(!function_exists("pop_data_br")){
	function pop_data_br(string $data, bool $comHora = false): string {
		$data = trim($data);
		if($data === "" || $data === "0000-00-00" || strpos($data, "0000-00-00") === 0){
			return "—";
		}
		$ts = strtotime($data);
		if(!$ts){
			return $data;
		}
		return date($comHora ? "d/m/Y H:i" : "d/m/Y", $ts);
	}
}

if(!function_exists("pop_resumo")){
	function pop_resumo(string $texto, int $limite = 150): string {
		$texto = trim(preg_replace('/\s+/', " ", strip_tags($texto)));
		if($texto === ""){
			return "";
		}
		if(mb_strlen($texto, "UTF-8") <= $limite){
			return $texto;
		}
		return rtrim(mb_substr($texto, 0, $limite, "UTF-8")) . "…";
	}
}

if(!function_exists("pop_documento_modelo")){
	function pop_documento_modelo(array $row): array {
		$id = intval($row["mode_nb_id"] ?? 0);
		$status = strtolower(trim(strval($row["mode_tx_status"] ?? "inativo"))) === "ativo" ? "ativo" : "inativo";
		$fluxograma = trim(strval($row["mode_tx_pop_fluxograma"] ?? ""));
		$atualizado = trim(strval($row["mode_tx_dataAtualiza"] ?? ""));
		if($atualizado === ""){
			$atualizado = trim(strval($row["mode_tx_dataCadastro"] ?? ""));
		}
		$codigo = trim(strval($row["mode_tx_pop_codigo"] ?? ""));
		if($codigo === ""){
			$codigo = "POP-" . str_pad(strval($id), 3, "0", STR_PAD_LEFT);
		}
		$versao = trim(strval($row["mode_tx_pop_versao"] ?? ""));
		if($versao === ""){
			$versao = "1.0";
		}
		$cache = (int)($atualizado !== "" ? strtotime($atualizado) : 0);

		return [
			"origem" => "termo",
			"id" => $id,
			"key" => "termo:" . $id,
			"titulo" => trim(strval($row["mode_tx_nome"] ?? "")) !== "" ? strval($row["mode_tx_nome"]) : ("POP #" . $id),
			"codigo" => $codigo,
			"setor_id" => intval($row["mode_nb_pop_setor"] ?? 0),
			"setor_nome" => trim(strval($row["setor_nome"] ?? "")),
			"versao" => $versao,
			"vigencia" => trim(strval($row["mode_dt_pop_vigencia"] ?? "")),
			"objetivo" => trim(strval($row["mode_tx_pop_objetivo"] ?? "")),
			"responsavel" => trim(strval($row["mode_tx_pop_responsavel"] ?? "")),
			"tags" => trim(strval($row["mode_tx_pop_tags"] ?? "")),
			"status" => $status,
			"atualizado" => $atualizado,
			"atualizado_ts" => $atualizado !== "" ? (int)strtotime($atualizado) : 0,
			"arquivo_rel" => "",
			"arquivo_nome" => "",
			"tem_fluxograma" => $fluxograma !== "",
			"fluxograma_rel" => $fluxograma,
			"fluxograma_nome" => trim(strval($row["mode_tx_pop_fluxograma_nome"] ?? "")),
			"fluxograma_tipo" => $fluxograma !== "" ? pop_tipo_fluxograma($fluxograma) : "",
			"pdf_url" => "pop_arquivo.php?origem=termo&id=" . $id . "&v=" . $cache,
			"fluxograma_url" => $fluxograma !== "" ? ("pop_arquivo.php?origem=termo&id=" . $id . "&tipo=fluxograma&v=" . $cache) : "",
			"editar_url" => "../termos/modelos_termo.php?acao=form&id=" . $id,
			"origem_label" => "Criado no módulo de termos",
			"origem_icone" => "fa-file-text-o"
		];
	}
}

if(!function_exists("pop_documento_upload")){
	function pop_documento_upload(array $row): array {
		$id = intval($row["pop_nb_id"] ?? 0);
		$status = strtolower(trim(strval($row["pop_tx_status"] ?? "inativo"))) === "ativo" ? "ativo" : "inativo";
		$fluxograma = trim(strval($row["pop_tx_fluxograma"] ?? ""));
		$atualizado = trim(strval($row["pop_tx_dataAtualiza"] ?? ""));
		if($atualizado === ""){
			$atualizado = trim(strval($row["pop_tx_dataCadastro"] ?? ""));
		}
		$codigo = trim(strval($row["pop_tx_codigo"] ?? ""));
		if($codigo === ""){
			$codigo = "POP-" . str_pad(strval($id), 3, "0", STR_PAD_LEFT);
		}
		$versao = trim(strval($row["pop_tx_versao"] ?? ""));
		if($versao === ""){
			$versao = "1.0";
		}
		$cache = (int)($atualizado !== "" ? strtotime($atualizado) : 0);

		return [
			"origem" => "upload",
			"id" => $id,
			"key" => "upload:" . $id,
			"titulo" => trim(strval($row["pop_tx_titulo"] ?? "")) !== "" ? strval($row["pop_tx_titulo"]) : ("POP #" . $id),
			"codigo" => $codigo,
			"setor_id" => intval($row["pop_nb_setor"] ?? 0),
			"setor_nome" => trim(strval($row["setor_nome"] ?? "")),
			"versao" => $versao,
			"vigencia" => trim(strval($row["pop_dt_vigencia"] ?? "")),
			"objetivo" => trim(strval($row["pop_tx_objetivo"] ?? "")),
			"responsavel" => trim(strval($row["pop_tx_responsavel"] ?? "")),
			"tags" => trim(strval($row["pop_tx_tags"] ?? "")),
			"status" => $status,
			"atualizado" => $atualizado,
			"atualizado_ts" => $atualizado !== "" ? (int)strtotime($atualizado) : 0,
			"arquivo_rel" => trim(strval($row["pop_tx_arquivo"] ?? "")),
			"arquivo_nome" => trim(strval($row["pop_tx_arquivo_nome"] ?? "")),
			"tem_fluxograma" => $fluxograma !== "",
			"fluxograma_rel" => $fluxograma,
			"fluxograma_nome" => trim(strval($row["pop_tx_fluxograma_nome"] ?? "")),
			"fluxograma_tipo" => $fluxograma !== "" ? pop_tipo_fluxograma($fluxograma) : "",
			"pdf_url" => "pop_arquivo.php?origem=upload&id=" . $id . "&v=" . $cache,
			"fluxograma_url" => $fluxograma !== "" ? ("pop_arquivo.php?origem=upload&id=" . $id . "&tipo=fluxograma&v=" . $cache) : "",
			"editar_url" => "index.php?editar=" . $id,
			"origem_label" => "PDF enviado pela empresa",
			"origem_icone" => "fa-file-pdf-o"
		];
	}
}

if(!function_exists("pop_carregar_upload")){
	function pop_carregar_upload(int $id): array {
		if($id <= 0){
			return [];
		}
		$res = query(
			"SELECT p.*, g.grup_tx_nome AS setor_nome
			 FROM pop_documento p
			 LEFT JOIN grupos_documentos g ON g.grup_nb_id = p.pop_nb_setor
			 WHERE p.pop_nb_id = ? LIMIT 1",
			"i",
			[$id]
		);
		return ($res instanceof mysqli_result) ? (mysqli_fetch_assoc($res) ?: []) : [];
	}
}

if(!function_exists("pop_listar_documentos")){
	function pop_listar_documentos(): array {
		pop_ensure_schema();
		$docs = [];

		$res = query(
			"SELECT m.*, g.grup_tx_nome AS setor_nome
			 FROM modelo_termo m
			 LEFT JOIN grupos_documentos g ON g.grup_nb_id = m.mode_nb_pop_setor
			 WHERE m.mode_tx_pop = 'sim'
			 ORDER BY m.mode_nb_id DESC"
		);
		while($res instanceof mysqli_result && ($row = mysqli_fetch_assoc($res))){
			$docs[] = pop_documento_modelo($row);
		}

		$res = query(
			"SELECT p.*, g.grup_tx_nome AS setor_nome
			 FROM pop_documento p
			 LEFT JOIN grupos_documentos g ON g.grup_nb_id = p.pop_nb_setor
			 ORDER BY p.pop_nb_id DESC"
		);
		while($res instanceof mysqli_result && ($row = mysqli_fetch_assoc($res))){
			$docs[] = pop_documento_upload($row);
		}

		usort($docs, function($a, $b){
			$ta = intval($a["atualizado_ts"] ?? 0);
			$tb = intval($b["atualizado_ts"] ?? 0);
			if($ta === $tb){
				return strcmp(strval($b["key"] ?? ""), strval($a["key"] ?? ""));
			}
			return $tb <=> $ta;
		});

		return $docs;
	}
}

if(!function_exists("pop_obter_documento")){
	function pop_obter_documento(string $origem, int $id): array {
		if($id <= 0){
			return [];
		}
		if($origem === "termo"){
			$modelo = termos_carregar_modelo($id);
			if(empty($modelo) || strtolower(trim(strval($modelo["mode_tx_pop"] ?? "nao"))) !== "sim"){
				return [];
			}
			$resSetor = query(
				"SELECT grup_tx_nome FROM grupos_documentos WHERE grup_nb_id = ? LIMIT 1",
				"i",
				[intval($modelo["mode_nb_pop_setor"] ?? 0)]
			);
			if($resSetor instanceof mysqli_result && ($rowSetor = mysqli_fetch_assoc($resSetor))){
				$modelo["setor_nome"] = $rowSetor["grup_tx_nome"] ?? "";
			}
			return pop_documento_modelo($modelo);
		}
		if($origem === "upload"){
			$row = pop_carregar_upload($id);
			return empty($row) ? [] : pop_documento_upload($row);
		}
		return [];
	}
}

if(!function_exists("pop_favoritos_usuario")){
	function pop_favoritos_usuario(int $userId): array {
		$out = [];
		if($userId <= 0){
			return $out;
		}
		$res = query(
			"SELECT popf_tx_origem, popf_nb_doc FROM pop_favorito WHERE popf_nb_user = ?",
			"i",
			[$userId]
		);
		while($res instanceof mysqli_result && ($row = mysqli_fetch_assoc($res))){
			$key = strval($row["popf_tx_origem"] ?? "") . ":" . intval($row["popf_nb_doc"] ?? 0);
			$out[$key] = true;
		}
		return $out;
	}
}

if(!function_exists("pop_filtrar_documentos")){
	function pop_filtrar_documentos(array $docs, array $filtros, array $favoritos = []): array {
		$status = strtolower(trim(strval($filtros["status"] ?? "ativo")));
		$setor = strval($filtros["setor"] ?? "");
		$origem = strtolower(trim(strval($filtros["origem"] ?? "")));
		$busca = trim(strval($filtros["busca"] ?? ""));
		$somenteFluxograma = !empty($filtros["fluxograma"]);
		$somenteFavoritos = !empty($filtros["favoritos"]);
		$buscaNorm = mb_strtolower($busca, "UTF-8");

		$out = [];
		foreach($docs as $doc){
			if($status !== "" && $status !== "todos" && strval($doc["status"] ?? "") !== $status){
				continue;
			}
			if($setor === "sem"){
				if(intval($doc["setor_id"] ?? 0) > 0){
					continue;
				}
			}elseif($setor !== "" && $setor !== "0"){
				if(intval($doc["setor_id"] ?? 0) !== intval($setor)){
					continue;
				}
			}
			if($origem !== "" && $origem !== "todos" && strval($doc["origem"] ?? "") !== $origem){
				continue;
			}
			if($somenteFluxograma && empty($doc["tem_fluxograma"])){
				continue;
			}
			if($somenteFavoritos && empty($favoritos[strval($doc["key"] ?? "")])){
				continue;
			}
			if($buscaNorm !== ""){
				$alvo = mb_strtolower(
					strval($doc["titulo"] ?? "") . " " .
					strval($doc["codigo"] ?? "") . " " .
					strval($doc["setor_nome"] ?? "") . " " .
					strval($doc["objetivo"] ?? "") . " " .
					strval($doc["responsavel"] ?? "") . " " .
					strval($doc["tags"] ?? "") . " " .
					strval($doc["versao"] ?? "") . " " .
					strval($doc["arquivo_nome"] ?? ""),
					"UTF-8"
				);
				if(mb_strpos($alvo, $buscaNorm) === false){
					continue;
				}
			}
			$out[] = $doc;
		}

		$ordenar = strtolower(trim(strval($filtros["ordenar"] ?? "recentes")));
		usort($out, function($a, $b) use ($ordenar){
			if($ordenar === "nome"){
				return strcasecmp(strval($a["titulo"] ?? ""), strval($b["titulo"] ?? ""));
			}
			if($ordenar === "vigencia"){
				$va = strval($a["vigencia"] ?? "");
				$vb = strval($b["vigencia"] ?? "");
				if($va === $vb){
					return strcasecmp(strval($a["titulo"] ?? ""), strval($b["titulo"] ?? ""));
				}
				if($va === ""){ return 1; }
				if($vb === ""){ return -1; }
				return strcmp($vb, $va);
			}
			if($ordenar === "setor"){
				$sa = strcasecmp(strval($a["setor_nome"] ?? ""), strval($b["setor_nome"] ?? ""));
				if($sa !== 0){ return $sa; }
				return strcasecmp(strval($a["titulo"] ?? ""), strval($b["titulo"] ?? ""));
			}
			return intval($b["atualizado_ts"] ?? 0) <=> intval($a["atualizado_ts"] ?? 0);
		});

		return $out;
	}
}

if(!function_exists("pop_contar_por_setor")){
	function pop_contar_por_setor(array $docs): array {
		$out = ["todos" => 0, "sem" => 0];
		foreach($docs as $doc){
			if(strval($doc["status"] ?? "") !== "ativo"){
				continue;
			}
			$out["todos"]++;
			$setorId = intval($doc["setor_id"] ?? 0);
			if($setorId > 0){
				$out[$setorId] = intval($out[$setorId] ?? 0) + 1;
			}else{
				$out["sem"]++;
			}
		}
		return $out;
	}
}

if(!function_exists("pop_estatisticas")){
	function pop_estatisticas(array $docs): array {
		$ativos = array_values(array_filter($docs, fn($doc) => strval($doc["status"] ?? "") === "ativo"));
		$setores = [];
		$fluxogramas = 0;
		$termos = 0;
		$uploads = 0;
		foreach($ativos as $doc){
			$setorId = intval($doc["setor_id"] ?? 0);
			if($setorId > 0){
				$setores[$setorId] = true;
			}
			if(!empty($doc["tem_fluxograma"])){
				$fluxogramas++;
			}
			if(strval($doc["origem"] ?? "") === "termo"){
				$termos++;
			}else{
				$uploads++;
			}
		}

		$compartilhamentos = 0;
		$colaboradores = 0;
		$res = query("SELECT COUNT(*) AS total, COUNT(DISTINCT pev_nb_entidade) AS pessoas FROM pop_envio WHERE pev_tx_status = 'ativo'");
		if($res instanceof mysqli_result && ($row = mysqli_fetch_assoc($res))){
			$compartilhamentos = intval($row["total"] ?? 0);
			$colaboradores = intval($row["pessoas"] ?? 0);
		}

		return [
			"documentos" => count($ativos),
			"setores" => count($setores),
			"fluxogramas" => $fluxogramas,
			"termos" => $termos,
			"uploads" => $uploads,
			"compartilhamentos" => $compartilhamentos,
			"colaboradores" => $colaboradores
		];
	}
}

if(!function_exists("pop_contar_documentos_setor")){
	function pop_contar_documentos_setor(int $setorId): int {
		if($setorId <= 0){
			return 0;
		}
		$res = query(
			"SELECT
				(SELECT COUNT(*) FROM modelo_termo WHERE mode_tx_pop = 'sim' AND mode_nb_pop_setor = ?) +
				(SELECT COUNT(*) FROM pop_documento WHERE pop_nb_setor = ?) AS total",
			"ii",
			[$setorId, $setorId]
		);
		if($res instanceof mysqli_result && ($row = mysqli_fetch_assoc($res))){
			return intval($row["total"] ?? 0);
		}
		return 0;
	}
}

if(!function_exists("pop_base_url")){
	function pop_base_url(): string {
		$base = rtrim(strval($_ENV["URL_BASE"] ?? ""), "/");
		if($base === ""){
			$proto = trim(strval($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? ""));
			if($proto === ""){
				$https = strtolower(trim(strval($_SERVER["HTTPS"] ?? "")));
				$proto = ($https !== "" && $https !== "off") ? "https" : "http";
			}
			$host = trim(strval($_SERVER["HTTP_X_FORWARDED_HOST"] ?? ($_SERVER["HTTP_HOST"] ?? "")));
			$base = $proto . "://" . $host;
		}
		$app = rtrim(strval($_ENV["APP_PATH"] ?? ""), "/");
		$ctx = rtrim(strval($_ENV["CONTEX_PATH"] ?? ""), "/");
		if($ctx === ""){
			$ctx = "/armazem_paraiba";
		}
		return rtrim($base . $app . $ctx, "/");
	}
}

if(!function_exists("pop_link_publico")){
	function pop_link_publico(string $token): string {
		return pop_base_url() . "/documentos/pop/pop_visualizar.php?token=" . urlencode($token);
	}
}

if(!function_exists("pop_criar_envio")){
	function pop_criar_envio(string $origem, int $docId, int $entidadeId = 0, string $nome = "", string $email = "", string $mensagem = "", int $userId = 0): string {
		if(!in_array($origem, ["termo", "upload"], true) || $docId <= 0){
			return "";
		}
		$token = bin2hex(random_bytes(24));
		$retorno = inserir("pop_envio", [
			"pev_tx_token",
			"pev_tx_origem",
			"pev_nb_doc",
			"pev_nb_entidade",
			"pev_tx_nome",
			"pev_tx_email",
			"pev_tx_mensagem",
			"pev_nb_user_envio",
			"pev_dt_expira"
		], [
			$token,
			$origem,
			$docId,
			$entidadeId > 0 ? $entidadeId : null,
			mb_substr($nome, 0, 255, "UTF-8"),
			mb_substr($email, 0, 255, "UTF-8"),
			$mensagem,
			$userId > 0 ? $userId : null,
			date("Y-m-d H:i:s", strtotime("+30 days"))
		]);
		$id = 0;
		if(is_array($retorno)){
			foreach($retorno as $valor){
				if(is_int($valor) && $valor > 0){
					$id = $valor;
					break;
				}
			}
		}
		return $id > 0 ? $token : "";
	}
}

if(!function_exists("pop_envio_por_token")){
	function pop_envio_por_token(string $token): array {
		$token = trim($token);
		if($token === "" || !preg_match('/^[a-f0-9]{32,64}$/i', $token)){
			return [];
		}
		$res = query(
			"SELECT * FROM pop_envio WHERE pev_tx_token = ? AND pev_tx_status = 'ativo' LIMIT 1",
			"s",
			[$token]
		);
		$row = ($res instanceof mysqli_result) ? (mysqli_fetch_assoc($res) ?: []) : [];
		if(empty($row)){
			return [];
		}
		$expira = trim(strval($row["pev_dt_expira"] ?? ""));
		if($expira !== "" && strtotime($expira) !== false && strtotime($expira) < time()){
			return [];
		}
		return $row;
	}
}

if(!function_exists("pop_registrar_visualizacao")){
	function pop_registrar_visualizacao(int $envioId): void {
		if($envioId <= 0){
			return;
		}
		query(
			"UPDATE pop_envio
			 SET pev_dt_visualizacao = COALESCE(pev_dt_visualizacao, NOW()),
			     pev_nb_visualizacoes = pev_nb_visualizacoes + 1
			 WHERE pev_nb_id = ?",
			"i",
			[$envioId]
		);
	}
}

if(!function_exists("pop_mime_arquivo")){
	function pop_mime_arquivo(string $caminho): string {
		$ext = pop_extensao($caminho);
		$mapa = [
			"pdf" => "application/pdf",
			"png" => "image/png",
			"jpg" => "image/jpeg",
			"jpeg" => "image/jpeg",
			"webp" => "image/webp"
		];
		return $mapa[$ext] ?? "application/octet-stream";
	}
}

if(!function_exists("pop_stream_arquivo")){
	function pop_stream_arquivo(string $caminhoRel, string $nomeArquivo, bool $download = false): void {
		$caminhoRel = trim(str_replace("\\", "/", $caminhoRel));
		if($caminhoRel === ""){
			http_response_code(404);
			exit("Arquivo não encontrado.");
		}
		$base = realpath(TERMOS_ARQUIVOS_POP_DIR);
		$abs = realpath(TERMOS_MODULO_DIR . "/" . ltrim($caminhoRel, "/"));
		if($base === false || $abs === false || !is_file($abs)){
			http_response_code(404);
			exit("Arquivo não encontrado.");
		}
		$baseNorm = rtrim(str_replace("\\", "/", $base), "/") . "/";
		$absNorm = str_replace("\\", "/", $abs);
		if(strpos($absNorm, $baseNorm) !== 0){
			http_response_code(403);
			exit("Acesso negado.");
		}

		$nomeArquivo = trim($nomeArquivo);
		if($nomeArquivo === ""){
			$nomeArquivo = basename($abs);
		}
		$nomeArquivo = preg_replace('/[\r\n"]/', "", $nomeArquivo);
		$nomeAscii = preg_replace('/[^\x20-\x7E]/', "_", $nomeArquivo);
		$nomeAscii = str_replace(["\\", "\""], "_", $nomeAscii);
		$mime = pop_mime_arquivo($abs);

		header("Content-Type: " . $mime);
		header("Content-Length: " . filesize($abs));
		header("X-Content-Type-Options: nosniff");
		header("Cache-Control: private, no-store, no-cache, must-revalidate");
		header("Content-Disposition: " . ($download ? "attachment" : "inline") . "; filename=\"" . $nomeAscii . "\"; filename*=UTF-8''" . rawurlencode($nomeArquivo));
		readfile($abs);
		exit;
	}
}

if(!function_exists("pop_dados_preview")){
	function pop_dados_preview(): array {
		$empresa = trim(strval($_SESSION["empr_tx_nome"] ?? ""));
		$cnpj = "00.000.000/0001-00";
		$contato = "(00) 0000-0000";
		$emailEmpresa = "contato@empresa.com.br";
		$logoEmpresa = "";
		$cidade = "Cidade";
		$uf = "UF";

		$resEmp = query(
			"SELECT empr_tx_nome, empr_tx_cnpj, empr_tx_contato, empr_tx_email, empr_tx_logo
			 FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_nb_id ASC LIMIT 1"
		);
		if($resEmp instanceof mysqli_result && ($rowEmp = mysqli_fetch_assoc($resEmp))){
			if($empresa === ""){
				$empresa = trim(strval($rowEmp["empr_tx_nome"] ?? ""));
			}
			$cnpj = trim(strval($rowEmp["empr_tx_cnpj"] ?? "")) ?: $cnpj;
			$contato = trim(strval($rowEmp["empr_tx_contato"] ?? "")) ?: $contato;
			$emailEmpresa = trim(strval($rowEmp["empr_tx_email"] ?? "")) ?: $emailEmpresa;
			$logoEmpresa = trim(strval($rowEmp["empr_tx_logo"] ?? ""));
		}
		if($empresa === ""){
			$empresa = "EMPRESA EXEMPLO";
		}

		return [
			"empr_tx_nome" => $empresa,
			"empr_tx_cnpj" => $cnpj,
			"empr_tx_contato" => $contato,
			"empr_email" => $emailEmpresa,
			"empr_tx_logo" => $logoEmpresa,
			"enti_tx_nome" => "NOME DO COLABORADOR",
			"enti_tx_cpf" => "000.000.000-00",
			"enti_tx_rg" => "0000000",
			"enti_tx_pis" => "00000000000",
			"enti_tx_ctpsNumero" => "000000",
			"enti_tx_ctpsSerie" => "0000",
			"enti_tx_ctpsUf" => "UF",
			"enti_tx_admissao" => date("Y-m-d"),
			"enti_tx_matricula" => "000000",
			"enti_tx_ocupacao" => "Cargo do colaborador",
			"cargo_nome" => "Cargo do colaborador",
			"enti_tx_email" => "colaborador@empresa.com.br",
			"enti_tx_cnhRegistro" => "00000000000",
			"enti_tx_cnhCategoria" => "AB",
			"enti_tx_cnhValidade" => date("Y-m-d", strtotime("+1 year")),
			"cida_tx_nome" => $cidade,
			"cida_tx_uf" => $uf
		];
	}
}

if(!function_exists("pop_render_modelo_pdf")){
	function pop_render_modelo_pdf(array $modelo, bool $download = false): void {
		$id = intval($modelo["mode_nb_id"] ?? 0);
		if($id <= 0){
			http_response_code(404);
			exit("POP não encontrado.");
		}
		$tipo = termos_carregar_tipo(intval($modelo["mode_nb_tipo_doc"] ?? 0));
		$dados = pop_dados_preview();

		// A prévia é institucional: não resolve o bloco de assinaturas com nomes
		// reais de signatários. O bloco é gerado apenas na emissão do termo.
		$modeloPreview = $modelo;
		$modeloPreview["mode_tx_conteudo"] = preg_replace(
			'/\{\{\s*bloco_assinaturas\s*\}\}/i',
			'',
			strval($modelo["mode_tx_conteudo"] ?? "")
		);
		$nome = trim(strval($modelo["mode_tx_pop_codigo"] ?? ""));
		if($nome === ""){
			$nome = "POP-" . $id;
		}
		$nome = strval(preg_replace('/[^\p{L}\p{N}\s\.\-\_]/u', "_", $nome));
		$nome = trim($nome) !== "" ? trim($nome) : ("POP-" . $id);
		$nome .= ".pdf";

		$tmp = tempnam(sys_get_temp_dir(), "pop_pdf_");
		if($tmp === false){
			http_response_code(500);
			exit("Não foi possível preparar a prévia do POP.");
		}

		$ok = false;
		try{
			$ok = termos_renderizar_pdf($dados, $modeloPreview, $tipo, $tmp, false);
		}catch(Throwable $e){
			$ok = false;
		}

		if(!$ok || !is_file($tmp) || filesize($tmp) <= 0){
			@unlink($tmp);
			http_response_code(500);
			exit("Não foi possível gerar a prévia do POP.");
		}

		$pdf = @file_get_contents($tmp);
		@unlink($tmp);
		if($pdf === false || $pdf === ""){
			http_response_code(500);
			exit("Não foi possível ler a prévia do POP.");
		}

		$nomeAscii = preg_replace('/[^\x20-\x7E]/', "_", $nome);
		$nomeAscii = str_replace(["\\", "\""], "_", $nomeAscii);
		header("Content-Type: application/pdf");
		header("Content-Length: " . strlen($pdf));
		header("X-Content-Type-Options: nosniff");
		header("Content-Disposition: " . ($download ? "attachment" : "inline") . "; filename=\"" . $nomeAscii . "\"; filename*=UTF-8''" . rawurlencode($nome));
		echo $pdf;
		exit;
	}
}

if(!function_exists("pop_enviar_email_documento")){
	function pop_enviar_email_documento(string $email, string $nome, string $titulo, string $setor, string $link, string $mensagem = ""): bool {
		$email = trim($email);
		if($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)){
			return false;
		}

		require_once POP_MODULO_DIR . "/assinatura/integracao/assinatura_integracao.php";
		assinatura_integracao_carregarEmail();
		if(!function_exists("configurarSMTP") || !class_exists("PHPMailer\\PHPMailer\\PHPMailer")){
			return false;
		}

		try{
			$mail = new PHPMailer\PHPMailer\PHPMailer(true);
			configurarSMTP($mail);
			$mail->addAddress($email, $nome);

			$cidLogo = function_exists("assinatura_email_embedLogo") ? assinatura_email_embedLogo($mail, "logo_techps_pop") : "";
			$mail->isHTML(true);
			$assunto = trim(preg_replace('/[\r\n]+/', " ", $titulo));
			$mail->Subject = "POP disponível: " . ($assunto !== "" ? $assunto : "documento");

			$headerHtml = $cidLogo !== ""
				? "<img src='{$cidLogo}' alt='TechPS' style='max-width:150px;'>"
				: "<h2 style='color:#333;margin:0;'>TechPS</h2>";

			$nomeSafe = function_exists("assinatura_h") ? assinatura_h(strtoupper($nome)) : pop_h(strtoupper($nome));
			$tituloSafe = function_exists("assinatura_h") ? assinatura_h($titulo) : pop_h($titulo);
			$setorSafe = function_exists("assinatura_h") ? assinatura_h($setor) : pop_h($setor);
			$linkSafe = function_exists("assinatura_h") ? assinatura_h($link) : pop_h($link);
			$mensagemSafe = nl2br(function_exists("assinatura_h") ? assinatura_h($mensagem) : pop_h($mensagem));

			$setorHtml = $setor !== ""
				? "<p style='margin:8px 0 0;color:#555;'><strong>Setor:</strong> {$setorSafe}</p>"
				: "";
			$mensagemHtml = trim($mensagem) !== ""
				? "<div style='background:#f8f9fa;border-left:4px solid #16a34a;padding:14px 16px;margin:22px 0;border-radius:4px;color:#444;'>{$mensagemSafe}</div>"
				: "";

			$contentHtml = "
				<h2 style='color:#111827;font-size:24px;margin-top:0;'>Olá, {$nomeSafe}.</h2>
				<p style='color:#4b5563;font-size:16px;line-height:1.55;'>
					Um Procedimento Operacional Padrão (POP) foi disponibilizado para você:
				</p>
				<div style='background:#f0fdf4;border:1px solid #bbf7d0;padding:18px 20px;margin:22px 0;border-radius:8px;'>
					<p style='margin:0;color:#166534;font-size:20px;font-weight:700;'>{$tituloSafe}</p>
					{$setorHtml}
				</div>
				{$mensagemHtml}
				<div style='text-align:center;margin:32px 0;'>
					<a href='{$linkSafe}' style='background:#16a34a;color:#fff;padding:15px 30px;text-decoration:none;border-radius:7px;font-weight:700;font-size:16px;display:inline-block;'>
						Abrir POP
					</a>
				</div>
				<p style='font-size:12px;color:#6b7280;line-height:1.5;border-top:1px solid #eee;padding-top:18px;'>
					Este link é pessoal e fica disponível por 30 dias. Se o botão não funcionar, copie e cole o endereço abaixo no navegador:<br>
					<span style='word-break:break-all;font-family:monospace;'>{$linkSafe}</span>
				</p>
			";

			$footerHtml = "<p style='margin:0;'>Mensagem automática enviada pela Biblioteca de POPs TechPS.</p>"
				. "<p style='margin:5px 0 0;'>&copy; " . date("Y") . " Armazém Paraíba - Todos os direitos reservados.</p>";

			$mail->Body = function_exists("assinatura_email_wrap")
				? assinatura_email_wrap($headerHtml, $contentHtml, $footerHtml)
				: $headerHtml . $contentHtml . $footerHtml;
			$mail->AltBody = "POP disponível: {$titulo}\n" . ($setor !== "" ? "Setor: {$setor}\n" : "") . "\nAcesse: {$link}\n";
			$mail->send();
			return true;
		}catch(Throwable $e){
			if(function_exists("registrar_log")){
				registrar_log("pop", "email_erro", $e->getMessage(), ["email" => $email, "titulo" => $titulo], "warning");
			}
			return false;
		}
	}
}

if(!function_exists("pop_log")){
	function pop_log(string $evento, string $detalhe = "", array $extra = []): void {
		if(function_exists("registrar_log")){
			registrar_log("pop", $evento, $detalhe, $extra, "info");
		}
		if(function_exists("log_limpar")){
			log_limpar("pop", 90);
		}
	}
}
