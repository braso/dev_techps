<?php
/**
 * Formatos aceitos nos anexos de documento — regra única do sistema.
 *
 * Usada pelas telas que guardam arquivo em documento_empresa,
 * documento_funcionario, documento_parametro e no anexo do abono.
 * Antes cada tela carregava sua própria cópia da lista, e todas as
 * cópias aceitavam só PDF, JPG, PNG, DOC e DOCX — planilha era recusada
 * com "Tipo de arquivo não permitido". Com a regra num lugar só, mudar
 * o que é aceito deixa de ser mexer em quatro arquivos e lembrar de
 * todos.
 *
 * ── Por que a conferência olha a extensão E o conteúdo
 *
 * Olhar só o conteúdo, como era feito antes, deixa passar um arquivo
 * chamado "algo.php" com cabeçalho de imagem: o mime_content_type()
 * devolve image/jpeg, o arquivo é aceito e vai para dentro de
 * arquivos/, que o servidor publica. O que entrou como imagem passa a
 * ser código que roda. Por isso a extensão precisa estar na lista.
 *
 * Olhar só a extensão confia no nome que o navegador mandou — então o
 * conteúdo também é lido.
 *
 * ── Por que a conferência entre os dois é frouxa para o Office
 *
 * .xlsx, .docx, .odt e .ods são arquivos ZIP por dentro; .xls e .doc
 * são contêineres OLE. Dependendo da versão da libmagic instalada no
 * servidor, o mime_content_type() devolve "application/zip" ou
 * "application/CDFV2" em vez do tipo exato. Se a conferência exigisse
 * o tipo exato, planilha continuaria sendo recusada em parte dos
 * servidores — que é justamente o problema a corrigir. Para esses
 * formatos o contêiner genérico é aceito; a extensão é que manda.
 * Nenhuma das extensões da lista é executada pelo servidor, então o
 * risco de aceitar o genérico é o de guardar um arquivo inútil, não o
 * de guardar um arquivo que roda.
 */

if (!function_exists("documentoFormatosPermitidos")) {
	/**
	 * extensão => tipos de conteúdo que o fileinfo pode devolver para ela.
	 *
	 * @return array<string, string[]>
	 */
	function documentoFormatosPermitidos(): array {
		return [
			/* Documentos */
			"pdf"  => ["application/pdf"],
			"doc"  => ["application/msword"],
			"docx" => ["application/vnd.openxmlformats-officedocument.wordprocessingml.document"],
			"rtf"  => ["application/rtf", "text/rtf"],
			"odt"  => ["application/vnd.oasis.opendocument.text"],

			/* Planilhas — o que faltava */
			"xls"  => ["application/vnd.ms-excel"],
			"xlsx" => ["application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"],
			"xlsm" => ["application/vnd.ms-excel.sheet.macroEnabled.12"],
			"ods"  => ["application/vnd.oasis.opendocument.spreadsheet"],
			"csv"  => ["text/csv", "application/csv", "text/plain"],

			/* Apresentações */
			"ppt"  => ["application/vnd.ms-powerpoint"],
			"pptx" => ["application/vnd.openxmlformats-officedocument.presentationml.presentation"],

			/* Texto simples */
			"txt"  => ["text/plain"],

			/* Imagens — inclui o que celular gera (webp, heic) */
			"jpg"  => ["image/jpeg"],
			"jpeg" => ["image/jpeg"],
			"png"  => ["image/png"],
			"gif"  => ["image/gif"],
			"webp" => ["image/webp"],
			"bmp"  => ["image/bmp", "image/x-ms-bmp"],
			"tif"  => ["image/tiff"],
			"tiff" => ["image/tiff"],
			"heic" => ["image/heic", "image/heif"],
		];
	}
}

if (!function_exists("documentoMimesGenericos")) {
	/**
	 * Tipos que a libmagic devolve quando reconhece só o contêiner, sem
	 * identificar qual formato Office está dentro. Aceitos apenas porque
	 * a extensão já foi conferida contra a lista.
	 *
	 * @return string[]
	 */
	function documentoMimesGenericos(): array {
		return [
			"application/zip",
			"application/x-zip-compressed",
			"application/CDFV2",
			"application/CDFV2-corrupt",
			"application/CDFV2-unknown",
			"application/x-ole-storage",
			"application/vnd.ms-office",
			"application/octet-stream",
		];
	}
}

if (!function_exists("documentoFormatosImagem")) {
	/**
	 * Só imagem — para campos que são foto de algo (CNH, retrato,
	 * rubrica), onde planilha não faria sentido. Inclui o que celular
	 * gera hoje: antes a foto saída de iPhone (.heic) e a de WhatsApp
	 * (.webp) eram descartadas sem avisar nada.
	 *
	 * @return string[]
	 */
	function documentoFormatosImagem(): array {
		return ["jpg", "jpeg", "png", "gif", "webp", "bmp", "tif", "tiff", "heic"];
	}
}

if (!function_exists("documentoFormatosAccept")) {
	/**
	 * Valor do atributo accept do <input type="file">. Serve só de ajuda
	 * para quem está escolhendo o arquivo: o navegador filtra a janela,
	 * mas quem decide é documentoValidarAnexo().
	 *
	 * @param string[] $apenas restringe a estas extensões
	 */
	function documentoFormatosAccept(array $apenas = []): string {
		$exts = empty($apenas)
			? array_keys(documentoFormatosPermitidos())
			: array_map("strtolower", $apenas);
		return "." . implode(",.", $exts);
	}
}

if (!function_exists("documentoFormatosTexto")) {
	/**
	 * Lista em português, para a tela e para a mensagem de erro.
	 *
	 * @param string[] $apenas restringe a estas extensões
	 */
	function documentoFormatosTexto(array $apenas = []): string {
		if (empty($apenas)) {
			return "PDF, Word (DOC, DOCX, RTF, ODT), Excel (XLS, XLSX, XLSM, ODS), "
				. "CSV, TXT, PowerPoint (PPT, PPTX) e imagens "
				. "(JPG, PNG, GIF, WEBP, BMP, TIFF, HEIC)";
		}

		$nomes = array_values(array_unique(array_map("strtoupper", $apenas)));
		if (count($nomes) === 1) {
			return $nomes[0];
		}
		$ultimo = array_pop($nomes);
		return implode(", ", $nomes) . " e " . $ultimo;
	}
}

if (!function_exists("documentoNomeSeguro")) {
	/**
	 * Nome de arquivo seguro para gravar em disco, com UMA extensão.
	 *
	 * Os pontos internos do nome são trocados por underscore de
	 * propósito. "relatorio.php.pdf" tem extensão .pdf e passaria na
	 * lista, mas há configuração de Apache que executa como PHP todo
	 * nome que CONTENHA .php, não só o que termina nele. Deixando um
	 * ponto só, o que foi gravado como PDF não tem como ser lido pelo
	 * servidor como outra coisa.
	 *
	 * O nome que aparece na tela é o docu_tx_nome, digitado no
	 * formulário — este aqui é só o do arquivo em disco.
	 */
	function documentoNomeSeguro(string $nomeOriginal, string $extensao): string {
		$base = pathinfo(basename($nomeOriginal), PATHINFO_FILENAME);

		/* Mantém letra, número, espaço, traço e underscore. Acento fica:
		   os nomes vêm em português e o disco aguenta. */
		$base = preg_replace('/[^\p{L}\p{N}\s\-\_]/u', '_', $base) ?? "";
		$base = trim(preg_replace('/\s+/u', ' ', $base) ?? "");

		/* Nome que sobrou vazio (ex.: arquivo chamado só "...") ou longo
		   demais para o sistema de arquivos. */
		if ($base === "" || $base === "_") {
			$base = "documento_" . date("Ymd_His");
		}
		if (function_exists("mb_substr")) {
			$base = mb_substr($base, 0, 120);
		} else {
			$base = substr($base, 0, 120);
		}

		return $base . "." . strtolower($extensao);
	}
}

if (!function_exists("documentoValidarAnexo")) {
	/**
	 * Confere um item de $_FILES antes de qualquer gravação.
	 *
	 * @param mixed    $arquivo item de $_FILES
	 * @param string[] $apenas  restringe a estas extensões (campos de
	 *                          foto, por exemplo, aceitam só imagem)
	 * @return array{ok: bool, erro: string, extensao: string, mime: string}
	 */
	function documentoValidarAnexo($arquivo, array $apenas = []): array {
		$recusar = function (string $motivo): array {
			return ["ok" => false, "erro" => $motivo, "extensao" => "", "mime" => ""];
		};

		if (!is_array($arquivo) || !isset($arquivo["tmp_name"])) {
			return $recusar("Nenhum arquivo foi recebido.");
		}

		/* Erro de transporte vem antes de tudo. A mensagem é específica
		   porque "Erro no upload do arquivo" não diz se o problema é o
		   tamanho, a conexão ou o arquivo não ter sido escolhido — e o
		   limite do servidor é a causa mais comum. */
		$erro = $arquivo["error"] ?? UPLOAD_ERR_NO_FILE;
		if ($erro !== UPLOAD_ERR_OK) {
			switch ($erro) {
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:
					return $recusar(
						"O arquivo é maior que o limite do servidor ("
						. ini_get("upload_max_filesize") . ")."
					);
				case UPLOAD_ERR_PARTIAL:
					return $recusar("O envio foi interrompido antes de terminar. Tente novamente.");
				case UPLOAD_ERR_NO_FILE:
					return $recusar("Nenhum arquivo foi selecionado.");
				case UPLOAD_ERR_NO_TMP_DIR:
				case UPLOAD_ERR_CANT_WRITE:
					return $recusar("O servidor não conseguiu gravar o arquivo temporário. Avise o suporte.");
				default:
					return $recusar("Não foi possível receber o arquivo (código " . intval($erro) . ").");
			}
		}

		if (!is_uploaded_file($arquivo["tmp_name"])) {
			return $recusar("O arquivo recebido não veio de um envio válido.");
		}

		$permitidos = documentoFormatosPermitidos();
		if (!empty($apenas)) {
			$permitidos = array_intersect_key(
				$permitidos,
				array_flip(array_map("strtolower", $apenas))
			);
		}

		$extensao = strtolower(pathinfo((string) ($arquivo["name"] ?? ""), PATHINFO_EXTENSION));

		if ($extensao === "") {
			return $recusar("O arquivo não tem extensão. Aceitamos: " . documentoFormatosTexto($apenas) . ".");
		}
		if (!array_key_exists($extensao, $permitidos)) {
			return $recusar(
				"Arquivo ." . $extensao . " não é aceito. Aceitamos: "
				. documentoFormatosTexto($apenas) . "."
			);
		}

		$mime = "";
		if (function_exists("mime_content_type")) {
			$mime = (string) @mime_content_type($arquivo["tmp_name"]);
		}

		/* Servidor sem a extensão fileinfo não tem como ler o conteúdo.
		   A extensão já está na lista e nenhuma delas é executável, então
		   o anexo passa em vez de a tela travar sem explicação. */
		if ($mime === "") {
			return ["ok" => true, "erro" => "", "extensao" => $extensao, "mime" => ""];
		}

		$esperados = $permitidos[$extensao];
		if (in_array($mime, $esperados, true) || in_array($mime, documentoMimesGenericos(), true)) {
			return ["ok" => true, "erro" => "", "extensao" => $extensao, "mime" => $mime];
		}

		return $recusar(
			"O conteúdo do arquivo não corresponde à extensão ." . $extensao
			. ". Confira se o arquivo não está corrompido ou se a extensão foi trocada à mão."
		);
	}
}
