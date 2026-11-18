<?php
/**
 * Carimbos, rubricas e assinaturas posicionáveis — regra única.
 *
 * O signatário posiciona quantas marcas quiser, em qualquer página do
 * documento, arrastando cada uma na tela. Cada marca fica gravada em
 * assinatura_marcas e é desenhada no PDF pelo servidor, nunca pelo navegador.
 *
 * ── Por que as coordenadas são percentuais
 *
 * A tela mostra a página renderizada pelo pdf.js num tamanho que depende do
 * zoom, da largura da janela e do DPI do aparelho. Guardar pixels amarraria a
 * marca à tela de quem posicionou. Guardando a fração da largura e da altura
 * da página (0 a 1), a mesma marca cai no mesmo lugar em A4, ofício, paisagem
 * ou numa página girada.
 *
 * A origem é o canto SUPERIOR esquerdo, igual à da tela e igual à do TCPDF —
 * por isso o desenho no servidor não precisa inverter o Y. (Quem inverte é o
 * pdf-lib, que trabalha com origem no canto inferior; não é usado aqui.)
 *
 * ── Por que só as páginas do documento original aceitam marca
 *
 * As páginas de comprovante são removidas e redesenhadas a cada assinatura
 * (script.js, em renderAuditPages), então o número delas muda ao longo da
 * coleta. Uma marca presa a uma dessas páginas mudaria de lugar sozinha. O
 * editor só deixa marcar as páginas do documento original, e o servidor
 * descarta o que vier fora desse intervalo.
 *
 * ── Por que o desenho é no servidor
 *
 * A assinatura ICP-Brasil (setSignature, em processar_finalizacao.php) assina
 * uma faixa de bytes do arquivo: carimbo aplicado depois invalida a
 * assinatura. Além disso o navegador do signatário não pode ser a fonte do
 * documento final. O desenho acontece antes do ICP, a partir do que está
 * gravado no banco, e funciona também quando o ICP está desligado.
 */

if (!defined("ASSINATURA_MARCAS_TABELA")) {
	define("ASSINATURA_MARCAS_TABELA", "assinatura_marcas");
}

if (!function_exists("assinaturaMarcas_garantirEstrutura_interno")) {
	/**
	 * Cria a tabela e a coluna de controle na primeira execução.
	 *
	 * O módulo já segue esse padrão (ver tipo_assinatura_helper.php): são 36
	 * bases de clientes em produção e depender de rodar SQL à mão em cada uma
	 * deixaria a função pela metade em parte delas.
	 */
	function assinaturaMarcas_garantirEstrutura_interno($conn): void {
		if (!($conn instanceof mysqli)) {
			return;
		}

		@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS " . ASSINATURA_MARCAS_TABELA . " (
			id INT AUTO_INCREMENT PRIMARY KEY,
			id_solicitacao INT NOT NULL,
			id_assinante INT NULL DEFAULT NULL,
			tipo VARCHAR(20) NOT NULL,
			pagina INT NOT NULL DEFAULT 1,
			pos_x DECIMAL(9,6) NOT NULL DEFAULT 0,
			pos_y DECIMAL(9,6) NOT NULL DEFAULT 0,
			larg DECIMAL(9,6) NOT NULL DEFAULT 0,
			alt DECIMAL(9,6) NOT NULL DEFAULT 0,
			rotacao SMALLINT NOT NULL DEFAULT 0,
			conteudo TEXT NULL DEFAULT NULL,
			caminho_imagem VARCHAR(255) NULL DEFAULT NULL,
			origem VARCHAR(12) NOT NULL DEFAULT 'signatario',
			obrigatoria TINYINT(1) NOT NULL DEFAULT 0,
			ip VARCHAR(45) NULL DEFAULT NULL,
			criado_em DATETIME NULL DEFAULT NULL,
			KEY idx_marca_solicitacao (id_solicitacao),
			KEY idx_marca_assinante (id_assinante)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		/* Guarda o PDF já carimbado, para a finalização não carimbar duas
		   vezes se for executada de novo. */
		$r = @mysqli_query($conn, "SHOW COLUMNS FROM solicitacoes_assinatura LIKE 'caminho_marcado'");
		if ($r && mysqli_num_rows($r) === 0) {
			@mysqli_query($conn, "ALTER TABLE solicitacoes_assinatura ADD COLUMN caminho_marcado VARCHAR(255) NULL DEFAULT NULL");
		}
	}
}

if (!function_exists("assinaturaMarcas_tipos")) {
	/**
	 * Tipos de marca e o tamanho padrão de cada um, em fração da largura e da
	 * altura da página. São os valores com que a marca nasce na tela; o
	 * signatário redimensiona depois.
	 *
	 * @return array<string, array{rotulo: string, fonte: string, larg: float, alt: float}>
	 */
	function assinaturaMarcas_tipos(): array {
		return [
			"rubrica" => [
				"rotulo" => "Rubrica",
				"fonte"  => "imagem",
				"larg"   => 0.16,
				"alt"    => 0.045,
			],
			"assinatura" => [
				"rotulo" => "Assinatura",
				"fonte"  => "imagem",
				"larg"   => 0.28,
				"alt"    => 0.075,
			],
			"carimbo" => [
				"rotulo" => "Carimbo completo",
				"fonte"  => "bloco",
				"larg"   => 0.40,
				"alt"    => 0.085,
			],
			"nome" => [
				"rotulo" => "Nome",
				"fonte"  => "texto",
				"larg"   => 0.30,
				"alt"    => 0.022,
			],
			"cpf" => [
				"rotulo" => "CPF",
				"fonte"  => "texto",
				"larg"   => 0.22,
				"alt"    => 0.022,
			],
			"rg" => [
				"rotulo" => "RG",
				"fonte"  => "texto",
				"larg"   => 0.22,
				"alt"    => 0.022,
			],
			"data" => [
				"rotulo" => "Data e hora",
				"fonte"  => "texto",
				"larg"   => 0.24,
				"alt"    => 0.022,
			],
			"texto" => [
				"rotulo" => "Texto livre",
				"fonte"  => "texto",
				"larg"   => 0.30,
				"alt"    => 0.022,
			],
		];
	}
}

if (!function_exists("assinaturaMarcas_normalizarLista")) {
	/**
	 * Confere a lista de marcas que veio da tela antes de gravar.
	 *
	 * Nada do que o navegador manda é aproveitado sem conferência: tipo tem de
	 * estar na lista, página tem de estar dentro do documento original e as
	 * coordenadas são presas no intervalo 0..1. O texto do carimbo não vem da
	 * tela — é montado no servidor a partir do que o signatário declarou.
	 *
	 * @param mixed $bruto       JSON ou array vindo do formulário
	 * @param int   $totalPaginas páginas do documento ORIGINAL
	 * @return array<int, array<string, mixed>>
	 */
	function assinaturaMarcas_normalizarLista($bruto, int $totalPaginas): array {
		if (is_string($bruto)) {
			$bruto = json_decode($bruto, true);
		}
		if (!is_array($bruto)) {
			return [];
		}

		$tipos = assinaturaMarcas_tipos();
		$limite = static function ($v): float {
			$f = is_numeric($v) ? floatval($v) : 0.0;
			if ($f < 0) {
				return 0.0;
			}
			return $f > 1 ? 1.0 : $f;
		};

		$lista = [];
		foreach ($bruto as $item) {
			if (!is_array($item)) {
				continue;
			}

			$tipo = strtolower(trim(strval($item["tipo"] ?? "")));
			if (!isset($tipos[$tipo])) {
				continue;
			}

			$pagina = intval($item["pagina"] ?? 0);
			if ($pagina < 1 || ($totalPaginas > 0 && $pagina > $totalPaginas)) {
				continue;
			}

			$larg = $limite($item["larg"] ?? 0);
			$alt  = $limite($item["alt"] ?? 0);
			if ($larg <= 0.005 || $alt <= 0.002) {
				/* Marca sem tamanho não desenha nada; usa o padrão do tipo em
				   vez de gravar uma linha inútil. */
				$larg = $tipos[$tipo]["larg"];
				$alt  = $tipos[$tipo]["alt"];
			}

			$x = $limite($item["pos_x"] ?? 0);
			$y = $limite($item["pos_y"] ?? 0);

			/* Marca que começaria fora da página é trazida para dentro. */
			if ($x + $larg > 1) {
				$x = max(0.0, 1 - $larg);
			}
			if ($y + $alt > 1) {
				$y = max(0.0, 1 - $alt);
			}

			$rotacao = intval($item["rotacao"] ?? 0);
			if ($rotacao < -180 || $rotacao > 180) {
				$rotacao = 0;
			}

			$texto = "";
			if ($tipo === "texto") {
				$texto = trim(strval($item["conteudo"] ?? ""));
				if (function_exists("mb_substr")) {
					$texto = mb_substr($texto, 0, 200);
				} else {
					$texto = substr($texto, 0, 200);
				}
				if ($texto === "") {
					continue;
				}
			}

			$lista[] = [
				"tipo"     => $tipo,
				"pagina"   => $pagina,
				"pos_x"    => $x,
				"pos_y"    => $y,
				"larg"     => $larg,
				"alt"      => $alt,
				"rotacao"  => $rotacao,
				"conteudo" => $texto,
			];

			/* Teto defensivo: ninguém posiciona 500 carimbos à mão, mas um
			   POST forjado posicionaria. */
			if (count($lista) >= 400) {
				break;
			}
		}

		return $lista;
	}
}

if (!function_exists("assinaturaMarcas_salvar_interno")) {
	/**
	 * Grava as marcas de um signatário, substituindo o que ele tinha gravado.
	 *
	 * @param array<int, array<string, mixed>> $marcas    saída de assinaturaMarcas_normalizarLista
	 * @param array<string, string>            $conteudos texto de cada tipo, montado no servidor
	 *                                                    (nome, cpf, rg, data, carimbo)
	 * @return int quantas marcas foram gravadas
	 */
	function assinaturaMarcas_salvar_interno(
		$conn,
		int $idSolicitacao,
		int $idAssinante,
		array $marcas,
		array $conteudos = [],
		string $imagemRubrica = "",
		string $ip = "",
		string $origem = "signatario",
		bool $obrigatoria = false
	): int {
		if (!($conn instanceof mysqli) || $idSolicitacao <= 0 || empty($marcas)) {
			return 0;
		}
		assinaturaMarcas_garantirEstrutura($conn);

		$origem = in_array($origem, ["signatario", "remetente"], true) ? $origem : "signatario";

		/* Reassinatura do mesmo signatário troca as marcas dele e não duplica. */
		if ($idAssinante > 0) {
			$del = mysqli_prepare(
				$conn,
				"DELETE FROM " . ASSINATURA_MARCAS_TABELA . " WHERE id_solicitacao = ? AND id_assinante = ? AND origem = ?"
			);
			if ($del) {
				mysqli_stmt_bind_param($del, "iis", $idSolicitacao, $idAssinante, $origem);
				@mysqli_stmt_execute($del);
				mysqli_stmt_close($del);
			}
		}

		$sql = "INSERT INTO " . ASSINATURA_MARCAS_TABELA . "
					(id_solicitacao, id_assinante, tipo, pagina, pos_x, pos_y, larg, alt,
					 rotacao, conteudo, caminho_imagem, origem, obrigatoria, ip, criado_em)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
		$stmt = mysqli_prepare($conn, $sql);
		if (!$stmt) {
			return 0;
		}

		$agora = date("Y-m-d H:i:s");
		$gravadas = 0;
		$tipos = assinaturaMarcas_tipos();

		foreach ($marcas as $m) {
			$tipo = strval($m["tipo"]);
			$fonte = $tipos[$tipo]["fonte"] ?? "texto";

			$conteudo = strval($m["conteudo"] ?? "");
			if ($tipo !== "texto") {
				$conteudo = strval($conteudos[$tipo] ?? "");
			}

			$imagem = ($fonte === "imagem") ? $imagemRubrica : "";
			if ($fonte === "imagem" && $imagem === "" && $origem === "signatario") {
				/* Sem imagem de rubrica, a marca viraria um retângulo vazio no
				   documento. Cai para o texto do carimbo, que ao menos
				   identifica quem assinou.

				   Isso vale só para a marca do próprio signatário: a do
				   remetente nasce vazia de propósito, porque a rubrica ainda
				   não existe quando ele posiciona — é preenchida depois, por
				   assinaturaMarcas_preencherDoRemetente(). */
				$conteudo = strval($conteudos["carimbo"] ?? $conteudos["nome"] ?? "");
				if ($conteudo === "") {
					continue;
				}
			}

			$pagina  = intval($m["pagina"]);
			$posX    = floatval($m["pos_x"]);
			$posY    = floatval($m["pos_y"]);
			$larg    = floatval($m["larg"]);
			$alt     = floatval($m["alt"]);
			$rotacao = intval($m["rotacao"]);
			$idAss   = $idAssinante > 0 ? $idAssinante : null;
			$obrig   = $obrigatoria ? 1 : 0;

			mysqli_stmt_bind_param(
				$stmt,
				"iisidddd" . "isssiss",
				$idSolicitacao,
				$idAss,
				$tipo,
				$pagina,
				$posX,
				$posY,
				$larg,
				$alt,
				$rotacao,
				$conteudo,
				$imagem,
				$origem,
				$obrig,
				$ip,
				$agora
			);
			if (@mysqli_stmt_execute($stmt)) {
				$gravadas++;
			}
		}
		mysqli_stmt_close($stmt);

		return $gravadas;
	}
}

if (!function_exists("assinaturaMarcas_listar_interno")) {
	/**
	 * Marcas de uma solicitação, em ordem de página.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	function assinaturaMarcas_listar_interno($conn, int $idSolicitacao, int $idAssinante = 0): array {
		if (!($conn instanceof mysqli) || $idSolicitacao <= 0) {
			return [];
		}
		assinaturaMarcas_garantirEstrutura($conn);

		$sql = "SELECT m.*, a.nome AS assinante_nome
					FROM " . ASSINATURA_MARCAS_TABELA . " m
					LEFT JOIN assinantes a ON a.id = m.id_assinante
					WHERE m.id_solicitacao = ?";
		if ($idAssinante > 0) {
			$sql .= " AND m.id_assinante = ?";
		}
		$sql .= " ORDER BY m.pagina ASC, m.id ASC";

		$stmt = mysqli_prepare($conn, $sql);
		if (!$stmt) {
			return [];
		}
		if ($idAssinante > 0) {
			mysqli_stmt_bind_param($stmt, "ii", $idSolicitacao, $idAssinante);
		} else {
			mysqli_stmt_bind_param($stmt, "i", $idSolicitacao);
		}
		mysqli_stmt_execute($stmt);
		$res = mysqli_stmt_get_result($stmt);
		$lista = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
		mysqli_stmt_close($stmt);

		return is_array($lista) ? $lista : [];
	}
}

if (!function_exists("assinaturaMarcas_montarConteudos")) {
	/**
	 * Texto de cada tipo de marca, montado no servidor a partir do que o
	 * signatário declarou. A tela manda posição; o conteúdo é daqui.
	 *
	 * @return array<string, string>
	 */
	function assinaturaMarcas_montarConteudos(
		string $nome,
		string $cpf,
		string $rg,
		string $dataHora,
		string $protocolo = ""
	): array {
		$nome = trim($nome);
		$cpf = trim($cpf);
		$rg = trim($rg);
		$dataHora = trim($dataHora) !== "" ? trim($dataHora) : date("d/m/Y H:i");

		$linhasCarimbo = ["Assinado eletronicamente"];
		if ($nome !== "") {
			$linhasCarimbo[] = $nome;
		}
		$doc = [];
		if ($cpf !== "") {
			$doc[] = "CPF " . $cpf;
		}
		if ($rg !== "") {
			$doc[] = "RG " . $rg;
		}
		if (!empty($doc)) {
			$linhasCarimbo[] = implode("  ·  ", $doc);
		}
		$linhasCarimbo[] = $dataHora;
		if ($protocolo !== "") {
			$linhasCarimbo[] = "Protocolo " . $protocolo;
		}

		return [
			"nome"       => $nome,
			"cpf"        => $cpf !== "" ? "CPF: " . $cpf : "",
			"rg"         => $rg !== "" ? "RG: " . $rg : "",
			"data"       => $dataHora,
			"carimbo"    => implode("\n", $linhasCarimbo),
			"rubrica"    => implode("\n", array_slice($linhasCarimbo, 0, 2)),
			"assinatura" => implode("\n", array_slice($linhasCarimbo, 0, 2)),
		];
	}
}

if (!function_exists("assinaturaMarcas_normalizarListaRemetente")) {
	/**
	 * Mesma conferência da lista do signatário, guardando a qual signatário
	 * cada marca pertence.
	 *
	 * No momento em que o remetente posiciona, os signatários ainda não
	 * existem no banco — a solicitação nem foi criada. O que existe é a ordem
	 * deles no formulário (1, 2, 3...), e é por ela que a marca é amarrada;
	 * o id real entra depois, quando as linhas de assinantes são inseridas.
	 *
	 * @return array<int, array<string, mixed>> cada item com a chave "ordem"
	 */
	function assinaturaMarcas_normalizarListaRemetente($bruto, int $totalPaginas): array {
		if (is_string($bruto)) {
			$bruto = json_decode($bruto, true);
		}
		if (!is_array($bruto)) {
			return [];
		}

		/* Item por item, em vez de validar a lista toda e depois tentar
		   casar cada resultado com o item de origem: dois signatários podem
		   reservar o mesmo tipo na mesma página, e aí o casamento por
		   conteúdo erraria o dono da marca. */
		$comOrdem = [];
		foreach ($bruto as $cru) {
			if (!is_array($cru)) {
				continue;
			}
			$limpa = assinaturaMarcas_normalizarLista([$cru], $totalPaginas);
			if (empty($limpa)) {
				continue;
			}
			$marca = $limpa[0];
			$marca["ordem"] = max(1, intval($cru["ordem"] ?? 1));
			$comOrdem[] = $marca;

			if (count($comOrdem) >= 400) {
				break;
			}
		}

		return $comOrdem;
	}
}

if (!function_exists("assinaturaMarcas_salvarDoRemetente_interno")) {
	/**
	 * Grava as marcas que o remetente definiu, já amarradas aos signatários.
	 *
	 * Nascem sem conteúdo e sem imagem: nome, CPF, RG, data e a rubrica só
	 * existem quando o signatário assina. Quem preenche é
	 * assinaturaMarcas_preencherDoRemetente(), no ato da assinatura.
	 *
	 * @param array<int, array<string, mixed>> $marcas      saída de assinaturaMarcas_normalizarListaRemetente
	 * @param array<int, int>                  $ordemParaId ordem no formulário => id em assinantes
	 * @return int quantas foram gravadas
	 */
	function assinaturaMarcas_salvarDoRemetente_interno($conn, int $idSolicitacao, array $marcas, array $ordemParaId): int {
		if (!($conn instanceof mysqli) || $idSolicitacao <= 0 || empty($marcas) || empty($ordemParaId)) {
			return 0;
		}

		/* Agrupa por signatário: salvar() grava de uma vez as marcas de um
		   signatário e apaga as anteriores dele. */
		$porAssinante = [];
		foreach ($marcas as $marca) {
			$ordem = intval($marca["ordem"] ?? 1);
			$idAssinante = intval($ordemParaId[$ordem] ?? 0);
			if ($idAssinante <= 0) {
				/* Marca de um signatário que não entrou na solicitação. */
				continue;
			}
			$porAssinante[$idAssinante][] = $marca;
		}

		$gravadas = 0;
		foreach ($porAssinante as $idAssinante => $lista) {
			$gravadas += assinaturaMarcas_salvar(
				$conn,
				$idSolicitacao,
				$idAssinante,
				$lista,
				[],
				"",
				"",
				"remetente",
				true
			);
		}

		return $gravadas;
	}
}

if (!function_exists("assinaturaMarcas_preencherDoRemetente_interno")) {
	/**
	 * Põe o conteúdo nas marcas que o remetente deixou posicionadas.
	 *
	 * Chamada no ato da assinatura: é só aí que existem o nome declarado, o
	 * CPF, o RG, a data, o protocolo e o PNG da rubrica. A posição é a que o
	 * remetente escolheu e não muda.
	 *
	 * @param array<string, string> $conteudos saída de assinaturaMarcas_montarConteudos
	 * @return int quantas marcas foram preenchidas
	 */
	function assinaturaMarcas_preencherDoRemetente_interno(
		$conn,
		int $idSolicitacao,
		int $idAssinante,
		array $conteudos,
		string $imagemRubrica = ""
	): int {
		if (!($conn instanceof mysqli) || $idSolicitacao <= 0 || $idAssinante <= 0) {
			return 0;
		}
		assinaturaMarcas_garantirEstrutura($conn);

		$pendentes = mysqli_prepare(
			$conn,
			"SELECT id, tipo FROM " . ASSINATURA_MARCAS_TABELA . "
				WHERE id_solicitacao = ? AND id_assinante = ? AND origem = 'remetente'"
		);
		if (!$pendentes) {
			return 0;
		}
		mysqli_stmt_bind_param($pendentes, "ii", $idSolicitacao, $idAssinante);
		mysqli_stmt_execute($pendentes);
		$res = mysqli_stmt_get_result($pendentes);
		$linhas = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
		mysqli_stmt_close($pendentes);

		if (empty($linhas)) {
			return 0;
		}

		$tipos = assinaturaMarcas_tipos();
		$upd = mysqli_prepare(
			$conn,
			"UPDATE " . ASSINATURA_MARCAS_TABELA . "
				SET conteudo = ?, caminho_imagem = ?
				WHERE id = ? LIMIT 1"
		);
		if (!$upd) {
			return 0;
		}

		$preenchidas = 0;
		foreach ($linhas as $linha) {
			$tipo = strval($linha["tipo"] ?? "");
			$fonte = $tipos[$tipo]["fonte"] ?? "texto";

			$conteudo = strval($conteudos[$tipo] ?? "");
			$imagem = "";
			if ($fonte === "imagem") {
				$imagem = $imagemRubrica;
				if ($imagem === "") {
					/* Tipo de assinatura da empresa sem rubrica: a marca de
					   imagem vira o texto do carimbo, para o lugar reservado
					   pelo remetente não sair em branco. */
					$conteudo = strval($conteudos["carimbo"] ?? $conteudos["nome"] ?? "");
				}
			}

			if ($conteudo === "" && $imagem === "") {
				continue;
			}

			$id = intval($linha["id"]);
			mysqli_stmt_bind_param($upd, "ssi", $conteudo, $imagem, $id);
			if (@mysqli_stmt_execute($upd)) {
				$preenchidas++;
			}
		}
		mysqli_stmt_close($upd);

		return $preenchidas;
	}
}

if (!function_exists("assinaturaMarcas_listarDoRemetente_interno")) {
	/**
	 * Marcas que o remetente reservou para um signatário, para a tela de
	 * assinatura mostrar onde ele já está sendo esperado.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	function assinaturaMarcas_listarDoRemetente_interno($conn, int $idSolicitacao, int $idAssinante): array {
		if (!($conn instanceof mysqli) || $idSolicitacao <= 0 || $idAssinante <= 0) {
			return [];
		}
		assinaturaMarcas_garantirEstrutura($conn);

		$stmt = mysqli_prepare(
			$conn,
			"SELECT tipo, pagina, pos_x, pos_y, larg, alt
				FROM " . ASSINATURA_MARCAS_TABELA . "
				WHERE id_solicitacao = ? AND id_assinante = ? AND origem = 'remetente'
				ORDER BY pagina ASC, id ASC"
		);
		if (!$stmt) {
			return [];
		}
		mysqli_stmt_bind_param($stmt, "ii", $idSolicitacao, $idAssinante);
		mysqli_stmt_execute($stmt);
		$res = mysqli_stmt_get_result($stmt);
		$lista = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
		mysqli_stmt_close($stmt);

		return is_array($lista) ? $lista : [];
	}
}

if (!function_exists("assinaturaMarcas_seloArquivo")) {
	/**
	 * Caminho e proporção da logo TechPS estampada na rubrica.
	 *
	 * Usa __DIR__ e não o $baseDir que chega por parâmetro porque cada pasta de
	 * cliente tem a sua cópia de assets/logo.png: o selo sai com a logo da pasta
	 * que está gerando o documento, e não com a de outra.
	 *
	 * A proporção vem do arquivo, não de número fixo: quem trocar a logo por uma
	 * de outro formato não precisa mexer no código para o selo não deformar.
	 *
	 * O "caminho" pode vir como bytes no formato "@" do TCPDF quando o fundo
	 * branco foi removido — o Image() aceita os dois, e quem chama não precisa
	 * saber qual dos dois recebeu.
	 *
	 * @return array{caminho: string, proporcao: float}
	 */
	function assinaturaMarcas_seloArquivo(): array {
		static $cache = null;
		if ($cache !== null) {
			return $cache;
		}

		$cache = ["caminho" => "", "proporcao" => 0.0];

		$abs = __DIR__ . "/assets/logo.png";
		if (!is_file($abs)) {
			return $cache;
		}

		$tam = @getimagesize($abs);
		if (!is_array($tam) || empty($tam[0]) || empty($tam[1])) {
			return $cache;
		}

		$cache = [
			"caminho"   => assinaturaMarcas_seloSemFundo($abs),
			"proporcao" => floatval($tam[0]) / floatval($tam[1]),
		];
		return $cache;
	}
}

if (!function_exists("assinaturaMarcas_seloSemFundo")) {
	/**
	 * Devolve a logo com o fundo branco transparente, pronta para o Image().
	 *
	 * A assets/logo.png foi feita para fundo branco de tela: dois terços dela
	 * são branco opaco. Estampada por cima do documento, esse branco apagaria
	 * o que estivesse embaixo — e a rubrica costuma cair perto de texto.
	 *
	 * Devolve no formato "@" do TCPDF, que recebe os bytes da imagem em vez do
	 * caminho: assim não precisa de pasta com permissão de escrita no servidor,
	 * que em cliente de hospedagem compartilhada nem sempre existe.
	 *
	 * Sem GD, ou se qualquer passo falhar, devolve o caminho original: selo com
	 * fundo branco é pior que selo transparente, e melhor que documento sem
	 * selo ou finalização quebrada.
	 */
	function assinaturaMarcas_seloSemFundo(string $abs) {
		if (!function_exists("imagecreatefrompng") || !function_exists("imagesavealpha")) {
			return $abs;
		}

		$origem = @imagecreatefrompng($abs);
		if (!$origem) {
			return $abs;
		}

		$w = imagesx($origem);
		$h = imagesy($origem);
		$destino = @imagecreatetruecolor($w, $h);
		if (!$destino) {
			imagedestroy($origem);
			return $abs;
		}

		imagealphablending($destino, false);
		imagesavealpha($destino, true);
		imagefill($destino, 0, 0, imagecolorallocatealpha($destino, 255, 255, 255, 127));

		/* O branco não é cortado a seco: o pixel vai ficando transparente
		   conforme clareia. Corte seco deixaria serrilha branca em volta das
		   letras, que num selo de 10 mm aparece como sujeira. */
		for ($y = 0; $y < $h; $y++) {
			for ($x = 0; $x < $w; $x++) {
				$cor = imagecolorat($origem, $x, $y);
				$r = ($cor >> 16) & 0xFF;
				$g = ($cor >> 8) & 0xFF;
				$b = $cor & 0xFF;
				$jaTransparente = ($cor >> 24) & 0x7F;

				$claro = min($r, $g, $b);
				if ($claro >= 250) {
					$alfa = 127;
				} elseif ($claro >= 235) {
					$alfa = intval(round(127 * ($claro - 235) / 15));
				} else {
					$alfa = 0;
				}
				$alfa = max($alfa, $jaTransparente);

				imagesetpixel($destino, $x, $y, imagecolorallocatealpha($destino, $r, $g, $b, $alfa));
			}
		}
		imagedestroy($origem);

		ob_start();
		$ok = @imagepng($destino);
		$dados = ob_get_clean();
		imagedestroy($destino);

		if (!$ok || $dados === "" || $dados === false) {
			return $abs;
		}
		return "@" . $dados;
	}
}

if (!function_exists("assinaturaMarcas_seloCaixa")) {
	/**
	 * Onde o selo entra dentro da área da rubrica: canto superior direito.
	 *
	 * O tamanho acompanha a marca em vez de ser fixo em milímetros porque o
	 * signatário redimensiona a rubrica arrastando na tela — selo de tamanho
	 * fixo ficaria maior que a própria rubrica numa marca pequena.
	 *
	 * A proporção da logo é respeitada: o lado que estoura primeiro manda e o
	 * outro sai dele. Esticar a logo para preencher a caixa a deforma.
	 *
	 * Devolve caixa vazia quando a marca é pequena demais para caber um selo
	 * legível — abaixo de 4 mm de largura a logo vira um borrão, e borrão no
	 * documento assinado é pior que selo nenhum.
	 *
	 * @param float $proporcao largura/altura da logo
	 * @return array{x: float, y: float, w: float, h: float} em mm, vazia se não cabe
	 */
	function assinaturaMarcas_seloCaixa(float $x, float $y, float $w, float $h, float $proporcao): array {
		$vazio = ["x" => 0.0, "y" => 0.0, "w" => 0.0, "h" => 0.0];
		if ($proporcao <= 0 || $w <= 0 || $h <= 0) {
			return $vazio;
		}

		/* 30% da área da rubrica: o bastante para ler a marca, pouco o bastante
		   para a rubrica continuar sendo o que se vê primeiro. */
		$selW = min($w * 0.30, $h * 0.30 * $proporcao);
		$selH = $selW / $proporcao;

		if ($selW < 4.0) {
			return $vazio;
		}

		return [
			"x" => $x + $w - ($w * 0.04) - $selW,
			"y" => $y + ($h * 0.06),
			"w" => $selW,
			"h" => $selH,
		];
	}
}

if (!function_exists("assinaturaMarcas_desenharUma")) {
	/**
	 * Desenha uma marca na página que está aberta no TCPDF.
	 *
	 * Recebe a medida da página em milímetros e converte o percentual aqui,
	 * porque só neste ponto se sabe o tamanho real da página importada.
	 *
	 * @param array<string, mixed> $marca
	 * @return array{desenhou: bool, aviso: string}
	 */
	function assinaturaMarcas_desenharUma($pdf, array $marca, float $pagW, float $pagH, string $baseDir): array {
		$tipos = assinaturaMarcas_tipos();
		$tipo = strval($marca["tipo"] ?? "");
		if (!isset($tipos[$tipo])) {
			return ["desenhou" => false, "aviso" => "tipo desconhecido: " . $tipo];
		}

		$x = floatval($marca["pos_x"]) * $pagW;
		$y = floatval($marca["pos_y"]) * $pagH;
		$w = floatval($marca["larg"]) * $pagW;
		$h = floatval($marca["alt"]) * $pagH;
		if ($w <= 0 || $h <= 0) {
			return ["desenhou" => false, "aviso" => "marca sem tamanho"];
		}

		$fonte = $tipos[$tipo]["fonte"];
		$conteudo = strval($marca["conteudo"] ?? "");
		$imagemRel = trim(strval($marca["caminho_imagem"] ?? ""));

		$rotacao = intval($marca["rotacao"] ?? 0);
		if ($rotacao !== 0) {
			/* Gira em torno do centro da marca, para a rotação não arrastar a
			   marca para fora do lugar escolhido. */
			$pdf->StartTransform();
			$pdf->Rotate(-$rotacao, $x + $w / 2, $y + $h / 2);
		}

		$erro = "";
		$aviso = "";

		if ($fonte === "imagem") {
			$abs = $imagemRel !== ""
				? $baseDir . "/" . ltrim(str_replace("\\", "/", $imagemRel), "/")
				: "";

			if ($abs === "" || !is_file($abs)) {
				/* Sem o arquivo da rubrica, desenha o texto em vez de deixar
				   um retângulo vazio no documento. */
				$aviso = $imagemRel === "" ? "" : "rubrica não encontrada em " . $imagemRel . "; usei o texto";
				$fonte = "texto";
			} else {
				try {
					/* fitbox 'CM' centraliza dentro da área sem distorcer a
					   rubrica — esticar uma assinatura à mão descaracteriza. */
					$pdf->Image($abs, $x, $y, $w, $h, "", "", "", false, 300, "", false, false, 0, "CM", false, false);
				} catch (Throwable $e) {
					/* PNG com transparência depende da GD no servidor. Se
					   falhar, a marca vira texto em vez de derrubar a
					   finalização inteira. */
					$aviso = "rubrica não pôde ser embutida (" . $e->getMessage() . "); usei o texto";
					$fonte = "texto";
				}
			}

			/* Selo TechPS no canto superior direito da própria área da rubrica.
			   Fica dentro do StartTransform aberto acima, então acompanha a marca
			   quando o signatário a girou.
			   Vale para a rubrica e não para a assinatura: são duas marcas de
			   imagem, e o pedido foi selar a rubrica.
			   Fora do try de cima de propósito: falha do selo não pode transformar
			   a rubrica em texto. */
			if ($fonte === "imagem" && $tipo === "rubrica") {
				$selo = assinaturaMarcas_seloArquivo();
				if ($selo["caminho"] !== "") {
					$caixa = assinaturaMarcas_seloCaixa($x, $y, $w, $h, $selo["proporcao"]);
					if ($caixa["w"] > 0) {
						try {
							$pdf->Image($selo["caminho"], $caixa["x"], $caixa["y"], $caixa["w"], $caixa["h"], "", "", "", false, 300, "", false, false, 0, "", false, false);
						} catch (Throwable $e) {
							/* Selo é enfeite: documento sem ele vale, documento que
							   não finaliza não vale nada. */
							$aviso = "selo TechPS não pôde ser embutido (" . $e->getMessage() . ")";
						}
					}
				}
			}
		}

		if ($fonte !== "imagem") {
			$linhas = preg_split('/\r\n|\r|\n/', $conteudo) ?: [];
			$linhas = array_values(array_filter(array_map("trim", $linhas), function ($l) {
				return $l !== "";
			}));
			if (empty($linhas)) {
				$erro = "marca de texto sem conteúdo";
			} else {
				if ($fonte === "bloco") {
					$pdf->SetFillColor(255, 255, 255);
					$pdf->SetDrawColor(120, 120, 120);
					$pdf->SetLineStyle(["width" => 0.2, "color" => [120, 120, 120]]);
					$pdf->Rect($x, $y, $w, $h, "DF");
				}

				$pad = $fonte === "bloco" ? min(1.5, $h * 0.12) : 0.0;
				$areaH = max(0.1, $h - $pad * 2);
				$areaW = max(0.1, $w - $pad * 2);

				/* Corpo da fonte pela altura disponível por linha (1 mm vale
				   2.834 pt), preso entre 4 e 13 pt: abaixo de 4 não se lê, e
				   acima de 13 o carimbo fica maior que a assinatura. */
				$alturaLinhaMm = $areaH / count($linhas);
				$corpo = $alturaLinhaMm * 2.834 * 0.72;
				$corpo = max(4.0, min(13.0, $corpo));

				$pdf->SetTextColor(20, 20, 20);
				$yLinha = $y + $pad;
				foreach ($linhas as $i => $linha) {
					$pdf->SetFont("helvetica", $i === 0 ? "B" : "", $corpo);
					$pdf->SetXY($x + $pad, $yLinha);
					$pdf->Cell($areaW, $alturaLinhaMm, $linha, 0, 0, "L", false, "", 1);
					$yLinha += $alturaLinhaMm;
				}
			}
		}

		if ($rotacao !== 0) {
			$pdf->StopTransform();
		}

		return ["desenhou" => ($erro === ""), "aviso" => ($erro !== "" ? $erro : $aviso)];
	}
}

if (!function_exists("assinaturaMarcas_desenharNoArquivo")) {
	/**
	 * Lê um PDF, desenha as marcas e grava o resultado em outro arquivo.
	 *
	 * Separada de assinaturaMarcas_aplicarNoPdf de propósito: aqui não há
	 * banco, então é esta função que o harness de teste exercita — o teste
	 * roda o mesmo código que a produção, em vez de uma cópia dele que pode
	 * divergir sem ninguém perceber.
	 *
	 * @param array<int, array<string, mixed>> $marcas com tipo, pagina, pos_x,
	 *        pos_y, larg, alt, rotacao, conteudo e caminho_imagem
	 * @return array{ok: bool, aplicadas: int, avisos: string[], error?: string}
	 */
	function assinaturaMarcas_desenharNoArquivo(array $marcas, string $entradaAbs, string $saidaAbs, string $baseDir): array {
		if (!is_file($entradaAbs)) {
			return ["ok" => false, "aplicadas" => 0, "avisos" => [], "error" => "PDF de entrada não encontrado."];
		}

		/* Mesmo carregamento usado na finalização ICP. */
		$tcpdfPath = "";
		foreach ([$baseDir . "/vendor/tecnickcom/tcpdf/tcpdf.php", $baseDir . "/../tcpdf/tcpdf.php"] as $cand) {
			if (file_exists($cand)) {
				$tcpdfPath = $cand;
				break;
			}
		}
		if ($tcpdfPath === "") {
			return ["ok" => false, "aplicadas" => 0, "avisos" => [], "error" => "Biblioteca TCPDF não encontrada no servidor."];
		}
		require_once $tcpdfPath;

		spl_autoload_register(function ($class) use ($baseDir) {
			if (strpos($class, "setasign\\Fpdi\\") === 0) {
				$arquivo = str_replace("\\", "/", substr($class, 14)) . ".php";
				$caminho = $baseDir . "/vendor/setasign/fpdi/src/" . $arquivo;
				if (file_exists($caminho)) {
					require_once $caminho;
				}
			}
		});
		if (!class_exists("setasign\\Fpdi\\Tcpdf\\Fpdi")) {
			return ["ok" => false, "aplicadas" => 0, "avisos" => [], "error" => "Biblioteca FPDI não encontrada no servidor."];
		}

		/* Agrupa por página: cada marca é desenhada enquanto a página dela
		   está aberta no TCPDF. Página fora do documento não encontra onde ser
		   desenhada e some sem quebrar nada. */
		$porPagina = [];
		foreach ($marcas as $m) {
			$porPagina[intval($m["pagina"] ?? 0)][] = $m;
		}

		$avisos = [];
		$aplicadas = 0;

		try {
			/* Classe anônima só para desligar o $tcpdflink, que é protegido:
			   com ele ligado o TCPDF acrescenta mais um rodapé "Powered by
			   TCPDF" na última página a cada passada, e esta é uma passada
			   nova — o documento terminaria com o aviso repetido. */
			$pdf = new class extends \setasign\Fpdi\Tcpdf\Fpdi {
				public function semCreditoTcpdf(): void {
					$this->tcpdflink = false;
				}
			};
			$pdf->semCreditoTcpdf();
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
			$pdf->SetAutoPageBreak(false);
			$pdf->SetMargins(0, 0, 0, true);
			$pdf->SetCellPadding(0);
			$pdf->setImageScale(1);

			$totalPaginas = $pdf->setSourceFile($entradaAbs);
			for ($p = 1; $p <= $totalPaginas; $p++) {
				$tpl = $pdf->importPage($p);
				$tam = $pdf->getTemplateSize($tpl);
				$orientacao = ($tam["width"] > $tam["height"]) ? "L" : "P";
				$pdf->AddPage($orientacao, [$tam["width"], $tam["height"]]);
				$pdf->useTemplate($tpl);

				if (empty($porPagina[$p])) {
					continue;
				}
				foreach ($porPagina[$p] as $marca) {
					$res = assinaturaMarcas_desenharUma($pdf, $marca, floatval($tam["width"]), floatval($tam["height"]), $baseDir);
					if (!empty($res["desenhou"])) {
						$aplicadas++;
					}
					if (!empty($res["aviso"])) {
						$avisos[] = "Página " . $p . ": " . $res["aviso"];
					}
				}
			}

			if (!is_dir(dirname($saidaAbs))) {
				@mkdir(dirname($saidaAbs), 0777, true);
			}
			$pdf->Output($saidaAbs, "F");
			if (!is_file($saidaAbs)) {
				throw new Exception("O arquivo carimbado não foi gravado.");
			}
		} catch (Throwable $e) {
			if (is_file($saidaAbs)) {
				@unlink($saidaAbs);
			}
			return [
				"ok" => false,
				"aplicadas" => 0,
				"avisos" => $avisos,
				"error" => "Não foi possível aplicar os carimbos: " . $e->getMessage(),
			];
		}

		return ["ok" => true, "aplicadas" => $aplicadas, "avisos" => $avisos];
	}
}

if (!function_exists("assinaturaMarcas_aplicarNoPdf_interno")) {
	/**
	 * Gera um novo PDF com todas as marcas da solicitação desenhadas.
	 *
	 * Roda antes da assinatura ICP: o arquivo que sai daqui é o que o
	 * setSignature() vai assinar. Quando não há marca, devolve ok com
	 * caminho vazio e o chamador segue com o arquivo que já tinha.
	 *
	 * @return array{ok: bool, caminho: string, aplicadas: int, avisos: string[], error?: string}
	 */
	function assinaturaMarcas_aplicarNoPdf_interno($conn, int $idSolicitacao, string $caminhoRelPdf): array {
		$vazio = ["ok" => true, "caminho" => "", "aplicadas" => 0, "avisos" => []];
		if (!($conn instanceof mysqli) || $idSolicitacao <= 0) {
			return $vazio;
		}

		assinaturaMarcas_garantirEstrutura($conn);
		$marcas = assinaturaMarcas_listar($conn, $idSolicitacao);
		if (empty($marcas)) {
			return $vazio;
		}

		$baseDir = rtrim(str_replace("\\", "/", __DIR__), "/");

		/* Já carimbado numa execução anterior: não carimbar de novo. */
		$stmtJa = mysqli_prepare($conn, "SELECT caminho_marcado FROM solicitacoes_assinatura WHERE id = ? LIMIT 1");
		if ($stmtJa) {
			mysqli_stmt_bind_param($stmtJa, "i", $idSolicitacao);
			mysqli_stmt_execute($stmtJa);
			$rowJa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtJa));
			mysqli_stmt_close($stmtJa);
			$ja = trim(strval($rowJa["caminho_marcado"] ?? ""));
			if ($ja !== "" && is_file($baseDir . "/" . ltrim($ja, "/"))) {
				return ["ok" => true, "caminho" => $ja, "aplicadas" => count($marcas), "avisos" => []];
			}
		}

		$entrada = $baseDir . "/" . ltrim(str_replace("\\", "/", $caminhoRelPdf), "/");
		if (!is_file($entrada)) {
			return ["ok" => false, "caminho" => "", "aplicadas" => 0, "avisos" => [], "error" => "PDF não encontrado para aplicar os carimbos."];
		}

		/* Se o usuário ativou a flag para preservar assinaturas E o documento contiver assinatura digital prévia (ex: Gov.br / ICP),
		   não passamos pelo FPDI, pois o FPDI destrói e remove a assinatura existente.
		   Caso contrário (padrão), o sistema segue o fluxo normal. */
		$preservarAtivo = false;
		if ($idSolicitacao > 0 && ($conn instanceof mysqli)) {
			$stmtCheckPres = mysqli_prepare($conn, "SELECT preservar_assinaturas FROM solicitacoes_assinatura WHERE id = ? LIMIT 1");
			if ($stmtCheckPres) {
				mysqli_stmt_bind_param($stmtCheckPres, "i", $idSolicitacao);
				mysqli_stmt_execute($stmtCheckPres);
				$resP = mysqli_stmt_get_result($stmtCheckPres);
				$rowP = $resP ? mysqli_fetch_assoc($resP) : null;
				mysqli_stmt_close($stmtCheckPres);
				$preservarAtivo = (strtolower(trim(strval($rowP["preservar_assinaturas"] ?? "nao"))) === "sim");
			}
		}

		require_once __DIR__ . "/assinatura_incremental_helper.php";
		$temAssinaturaDigital = assinatura_pdfTemAssinaturaDigital($entrada);
		if ($temAssinaturaDigital || $preservarAtivo) {
			$dispInc = function_exists("assinatura_incrementalDisponivel") ? assinatura_incrementalDisponivel() : ["disponivel" => false];
			if (!empty($dispInc["disponivel"])) {
				return ["ok" => true, "caminho" => $caminhoRelPdf, "aplicadas" => 0, "avisos" => ["Preservada assinatura prévia existente no documento conforme configuração."]];
			}
		}

		$saidaRel = "docAssinado/marcado_" . $idSolicitacao . "_" . time() . "_" . bin2hex(random_bytes(4)) . ".pdf";
		$desenho = assinaturaMarcas_desenharNoArquivo($marcas, $entrada, $baseDir . "/" . $saidaRel, $baseDir);
		if (empty($desenho["ok"])) {
			return [
				"ok" => false,
				"caminho" => "",
				"aplicadas" => 0,
				"avisos" => $desenho["avisos"] ?? [],
				"error" => strval($desenho["error"] ?? "Não foi possível aplicar os carimbos."),
			];
		}
		$aplicadas = intval($desenho["aplicadas"] ?? 0);
		$avisos = $desenho["avisos"] ?? [];

		$stmtUp = mysqli_prepare($conn, "UPDATE solicitacoes_assinatura SET caminho_marcado = ? WHERE id = ? LIMIT 1");
		if ($stmtUp) {
			mysqli_stmt_bind_param($stmtUp, "si", $saidaRel, $idSolicitacao);
			@mysqli_stmt_execute($stmtUp);
			mysqli_stmt_close($stmtUp);
		}

		return ["ok" => true, "caminho" => $saidaRel, "aplicadas" => $aplicadas, "avisos" => $avisos];
	}
}


/* ──────────────────────────────────────────────────────────────────────────
   Invólucros públicos: nada de carimbo pode derrubar a tela de assinatura.

   No PHP 8.1+ o mysqli lança exceção por padrão, e "@" não silencia exceção.
   O conecta.php desliga o relato durante a criação de estrutura e o RELIGA em
   seguida, então quando estas funções rodam o modo normalmente é o de lançar.
   Base de cliente sem privilégio de ALTER, tabela em outro charset, coluna que
   já existe com outro tipo — qualquer um desses casos viraria Fatal error no
   meio da assinatura.

   A regra é a mesma que vale para o desenho: o documento continua sendo
   assinado; o carimbo é que deixa de sair, e o motivo vai para o log do PHP.
   ────────────────────────────────────────────────────────────────────────── */
if (!function_exists("assinaturaMarcas_garantirEstrutura")) {
	function assinaturaMarcas_garantirEstrutura($conn): void {
		try {
			assinaturaMarcas_garantirEstrutura_interno($conn);
		} catch (Throwable $e) {
			error_log("assinatura/marcas: " . $e->getMessage());
		}
	}
}

if (!function_exists("assinaturaMarcas_salvar")) {
	function assinaturaMarcas_salvar($conn, int $idSolicitacao, int $idAssinante, array $marcas, array $conteudos = [], string $imagemRubrica = "", string $ip = "", string $origem = "signatario", bool $obrigatoria = false): int {
		try {
			return assinaturaMarcas_salvar_interno($conn, $idSolicitacao, $idAssinante, $marcas, $conteudos, $imagemRubrica, $ip, $origem, $obrigatoria);
		} catch (Throwable $e) {
			error_log("assinatura/marcas: " . $e->getMessage());
			return 0;
		}
	}
}

if (!function_exists("assinaturaMarcas_listar")) {
	function assinaturaMarcas_listar($conn, int $idSolicitacao, int $idAssinante = 0): array {
		try {
			return assinaturaMarcas_listar_interno($conn, $idSolicitacao, $idAssinante);
		} catch (Throwable $e) {
			error_log("assinatura/marcas: " . $e->getMessage());
			return [];
		}
	}
}

if (!function_exists("assinaturaMarcas_salvarDoRemetente")) {
	function assinaturaMarcas_salvarDoRemetente($conn, int $idSolicitacao, array $marcas, array $ordemParaId): int {
		try {
			return assinaturaMarcas_salvarDoRemetente_interno($conn, $idSolicitacao, $marcas, $ordemParaId);
		} catch (Throwable $e) {
			error_log("assinatura/marcas: " . $e->getMessage());
			return 0;
		}
	}
}

if (!function_exists("assinaturaMarcas_preencherDoRemetente")) {
	function assinaturaMarcas_preencherDoRemetente($conn, int $idSolicitacao, int $idAssinante, array $conteudos, string $imagemRubrica = ""): int {
		try {
			return assinaturaMarcas_preencherDoRemetente_interno($conn, $idSolicitacao, $idAssinante, $conteudos, $imagemRubrica);
		} catch (Throwable $e) {
			error_log("assinatura/marcas: " . $e->getMessage());
			return 0;
		}
	}
}

if (!function_exists("assinaturaMarcas_listarDoRemetente")) {
	function assinaturaMarcas_listarDoRemetente($conn, int $idSolicitacao, int $idAssinante): array {
		try {
			return assinaturaMarcas_listarDoRemetente_interno($conn, $idSolicitacao, $idAssinante);
		} catch (Throwable $e) {
			error_log("assinatura/marcas: " . $e->getMessage());
			return [];
		}
	}
}

if (!function_exists("assinaturaMarcas_aplicarNoPdf")) {
	function assinaturaMarcas_aplicarNoPdf($conn, int $idSolicitacao, string $caminhoRelPdf): array {
		try {
			return assinaturaMarcas_aplicarNoPdf_interno($conn, $idSolicitacao, $caminhoRelPdf);
		} catch (Throwable $e) {
			error_log("assinatura/marcas: " . $e->getMessage());
			return ["ok" => true, "caminho" => "", "aplicadas" => 0, "avisos" => []];
		}
	}
}
