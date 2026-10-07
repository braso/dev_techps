<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/
	require_once __DIR__ . "/../tcpdf/tcpdf.php";
	require_once __DIR__ . "/escala_pdf_funcoes.php";
	require_once __DIR__ . "/../funcoes_ponto.php";

	class EscalaPDF extends TCPDF {
		public $tituloPersonalizado = "Escala de Trabalho";
		public $subtituloPersonalizado = "";
		public $empresaLogo = "";

		public function Header() {
			$logoCliente = __DIR__ . "/../imagens/logo_topo_cliente.png";
			if (file_exists($logoCliente)) {
				$this->Image($logoCliente, 8, 4, 30, 8);
			}
			if (!empty($this->empresaLogo)) {
				$logoEmpresa = __DIR__ . "/../" . $this->empresaLogo;
				if (file_exists($logoEmpresa)) {
					$this->Image($logoEmpresa, $this->GetPageWidth() - 42, 4, 34, 10);
				}
			}

			$this->SetY(5);
			$this->SetFont("helvetica", "B", 15);
			$this->Cell(0, 9, $this->tituloPersonalizado, 0, 1, "C");
			if ($this->subtituloPersonalizado !== "") {
				$this->SetFont("helvetica", "", 7);
				$this->MultiCell(0, 3.2, $this->subtituloPersonalizado, 0, "C");
			}
			$this->SetDrawColor(60, 60, 60);
			$this->Line(4, $this->GetY() + 1.5, $this->GetPageWidth() - 4, $this->GetY() + 1.5);

			// Garante que o conteúdo comece abaixo do cabeçalho, mesmo com subtítulo em várias linhas
			$this->setTopMargin(max(22, $this->GetY() + 3));
		}

		public function Footer() {
			$this->SetY(-8);
			$this->SetFont("helvetica", "I", 6.5);
			$this->Cell(0, 4, "Gerado em " . date("d/m/Y H:i") . "  -  Página " . $this->getAliasNumPage() . " de " . $this->getAliasNbPages(), 0, 0, "C");
		}
	}

	$idEmpresa = intval($_POST["IdEmpresa"] ?? 0);
	if ($idEmpresa <= 0 && !empty($_SESSION["user_nb_empresa"])) {
		$idEmpresa = intval($_SESSION["user_nb_empresa"]);
	}
	$empresa = ($idEmpresa > 0) ? carregar("empresa", $idEmpresa) : [];

	$titulo = trim((string)($_POST["paginaTitulo"] ?? ""));
	if ($titulo === "") {
		$titulo = "Escala de Trabalho";
	}
	$titulo = trim(preg_replace('/\s+/', ' ', $titulo));

	$filtrosTexto = trim((string)($_POST["filtros_texto"] ?? ""));
	$filtrosTexto = trim(preg_replace('/\s+/', ' ', $filtrosTexto));
	if (mb_strlen($filtrosTexto, "UTF-8") > 320) {
		$filtrosTexto = mb_substr($filtrosTexto, 0, 317, "UTF-8") . "...";
	}

	$tabelaHtml = (string)($_POST["tabela_html"] ?? "");
	$tabelaHtml = mb_convert_encoding($tabelaHtml, "UTF-8", "UTF-8, ISO-8859-1, Windows-1252");
	$tabelaHtml = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/is', '', $tabelaHtml);
	$tabelaHtml = preg_replace('/<i\b[^>]*>.*?<\/i>/is', '', $tabelaHtml);
	$tabelaHtml = str_replace(';""', '', $tabelaHtml);

	$linhas = escalaPdfExtrairLinhas($tabelaHtml);
	if (empty($linhas)) {
		header("Content-Type: text/html; charset=UTF-8");
		die("Nenhuma tabela recebida para gerar o PDF.");
	}

	// A coluna "Total Previsto" permanece no grid, mas e removida do PDF
	$indicesTotal = [];
	foreach ($linhas[0] as $i => $celula) {
		if (escalaPdfChaveColuna($celula["texto"] ?? "") === "total") {
			$indicesTotal[] = $i;
		}
	}
	if (!empty($indicesTotal)) {
		foreach ($linhas as &$linha) {
			foreach (array_reverse($indicesTotal) as $i) {
				if (array_key_exists($i, $linha)) {
					array_splice($linha, $i, 1);
				}
			}
		}
		unset($linha);
	}

	$pdf = new EscalaPDF("L", "mm", "A4", true, "UTF-8", false);
	$pdf->SetCreator("TechPS");
	$pdf->SetAuthor("TechPS");
	$pdf->SetTitle($titulo);
	$pdf->empresaLogo = $empresa["empr_tx_logo"] ?? "";
	$pdf->tituloPersonalizado = $titulo;
	$pdf->subtituloPersonalizado = $filtrosTexto;
	$pdf->SetMargins(4, 22, 4);
	$pdf->SetHeaderMargin(4);
	$pdf->SetFooterMargin(4);
	$pdf->setCellHeightRatio(1.15);
	$pdf->SetAutoPageBreak(false, 8);
	$pdf->AddPage();

	$margens = $pdf->getMargins();
	$larguraConteudo = $pdf->getPageWidth() - $margens["left"] - $margens["right"];
	$alturaDisponivel = $pdf->getPageHeight() - $margens["top"] - $margens["bottom"] - 1;

	$alturaFaixa = 4.5;
	$espacoBlocos = 4.0;

	$cabecalhoTextos = array_map(function ($celula) {
		return $celula["texto"] ?? "";
	}, reset($linhas));

	[$colunasFixas, $colunasDias] = escalaPdfIdentificarColunas($cabecalhoTextos);

	// Layout escolhido pelo usuario (auto | dividido | colunas)
	$layoutPreferido = strtolower(trim((string)($_POST["layout_pdf"] ?? "auto")));
	if ($layoutPreferido === "dividido") {
		$divisoesTestar = [2];
	} elseif ($layoutPreferido === "colunas") {
		$divisoesTestar = [1];
	} else {
		$divisoesTestar = [1, 2];
	}

	// Compara o layout de colunas do mês com o layout de mês dividido; em modo automatico,
	// prefere o mês dividido quando a fonte fica próxima (até 0,5pt menor) do melhor caso,
	// pois as colunas de dias ficam mais largas e legíveis.
	$resultados = [];
	foreach ($divisoesTestar as $divisoes) {
		if ($divisoes === 2 && count($colunasDias) < 6) {
			continue;
		}
		$blocos = escalaPdfConstruirBlocos($linhas, $colunasFixas, $colunasDias, $divisoes, ($divisoes > 1));
		$blocos = escalaPdfAplicarLargurasBlocos($blocos, $larguraConteudo);
		$escolha = escalaPdfEscolherFonteBlocos($pdf, $blocos, $alturaDisponivel, $alturaFaixa, $espacoBlocos);
		$resultados[] = ["divisoes" => $divisoes, "blocos" => $blocos, "escolha" => $escolha];
	}
	if (empty($resultados)) {
		// Sem colunas de dia suficientes para dividir: monta bloco único
		$blocos = escalaPdfConstruirBlocos($linhas, $colunasFixas, $colunasDias, 1, false);
		$blocos = escalaPdfAplicarLargurasBlocos($blocos, $larguraConteudo);
		$resultados[] = [
			"divisoes" => 1,
			"blocos" => $blocos,
			"escolha" => escalaPdfEscolherFonteBlocos($pdf, $blocos, $alturaDisponivel, $alturaFaixa, $espacoBlocos),
		];
	}

	$layout = null;
	foreach ($resultados as $resultado) {
		if (!$resultado["escolha"]["coube"] || !$resultado["escolha"]["tokens"]) {
			continue;
		}
		if ($layout === null) {
			$layout = $resultado;
			continue;
		}
		if ($resultado["divisoes"] > $layout["divisoes"]) {
			// Mês dividido: aceita quando a fonte fica próxima da melhor
			if ($resultado["escolha"]["fonte"] >= $layout["escolha"]["fonte"] - 0.5) {
				$layout = $resultado;
			}
		} elseif ($resultado["escolha"]["fonte"] > $layout["escolha"]["fonte"]) {
			$layout = $resultado;
		}
	}
	if ($layout === null) {
		foreach ($resultados as $resultado) {
			if ($layout === null || $resultado["escolha"]["fonte"] > $layout["escolha"]["fonte"]) {
				$layout = $resultado;
			}
		}
	}

	$blocos = $layout["blocos"];
	$escolha = $layout["escolha"];

	// Distribui a sobra vertical para preencher melhor a folha em exposicao
	$totalLinhas = 0;
	foreach ($blocos as $bloco) {
		$totalLinhas += count($bloco["linhas"]);
	}
	$sobra = ($alturaDisponivel - $escolha["medida"]["total"]) - 0.5;
	$extra = ($sobra > 0 && $totalLinhas > 0) ? min(1.5, $sobra / $totalLinhas) : 0.0;
	$alturasPorBloco = $escolha["medida"]["linhas"];
	if ($extra > 0) {
		foreach ($alturasPorBloco as $bi => $alturas) {
			foreach ($alturas as $ri => $h) {
				$alturasPorBloco[$bi][$ri] = $h + $extra;
			}
		}
	}

	escalaPdfDesenharBlocos($pdf, $blocos, $escolha["fonte"], $escolha["pad"], $alturaFaixa, $espacoBlocos, $alturasPorBloco);

	$nomeArquivo = preg_replace('/[\/\\\\:*?"<>|]+/', '-', $titulo);
	$nomeArquivo = trim(preg_replace('/\s+/', ' ', $nomeArquivo));
	$nomeArquivo = ($nomeArquivo !== "") ? $nomeArquivo . ".pdf" : "escala.pdf";

	$pdf->Output($nomeArquivo, "I");
