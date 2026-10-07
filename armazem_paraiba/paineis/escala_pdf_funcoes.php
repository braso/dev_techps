<?php

	function escalaPdfCorFundo(string $style): ?array {
		if ($style === "") {
			return null;
		}
		if (preg_match('/background-color\s*:\s*#([0-9a-fA-F]{6})/', $style, $m)) {
			return [
				hexdec(substr($m[1], 0, 2)),
				hexdec(substr($m[1], 2, 2)),
				hexdec(substr($m[1], 4, 2)),
			];
		}
		if (preg_match('/background-color\s*:\s*#([0-9a-fA-F]{3})\b/', $style, $m)) {
			return [
				hexdec(str_repeat($m[1][0], 2)),
				hexdec(str_repeat($m[1][1], 2)),
				hexdec(str_repeat($m[1][2], 2)),
			];
		}
		if (preg_match('/background-color\s*:\s*rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/i', $style, $m)) {
			return [intval($m[1]), intval($m[2]), intval($m[3])];
		}
		return null;
	}

	function escalaPdfTextoSemAcento(string $texto): string {
		$texto = mb_strtoupper(trim($texto), "UTF-8");
		return strtr($texto, [
			"Á" => "A", "À" => "A", "Ã" => "A", "Â" => "A", "Ä" => "A",
			"É" => "E", "È" => "E", "Ê" => "E", "Ë" => "E",
			"Í" => "I", "Ì" => "I", "Î" => "I", "Ï" => "I",
			"Ó" => "O", "Ò" => "O", "Õ" => "O", "Ô" => "O", "Ö" => "O",
			"Ú" => "U", "Ù" => "U", "Û" => "U", "Ü" => "U",
			"Ç" => "C",
		]);
	}

	function escalaPdfExtrairLinhas(string $tabelaHtml): array {
		$linhas = [];
		$doc = new DOMDocument();
		libxml_use_internal_errors(true);
		$carregou = $doc->loadHTML(
			'<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $tabelaHtml,
			LIBXML_NOERROR | LIBXML_NOWARNING
		);
		libxml_clear_errors();
		if (!$carregou) {
			return [];
		}

		$trs = $doc->getElementsByTagName('tr');
		foreach ($trs as $tr) {
			$celulas = [];
			foreach ($tr->childNodes as $node) {
				if (!($node instanceof DOMElement)) {
					continue;
				}
				$tag = strtolower($node->nodeName);
				if ($tag !== "td" && $tag !== "th") {
					continue;
				}
				$texto = $node->textContent;
				$texto = preg_replace('/\x{00A0}/u', ' ', $texto);
				$texto = preg_replace('/\s+/u', ' ', $texto);
				$texto = trim($texto);
				$negrito = ($tag === "th");
				// Horário no formato "08:00 - 17:00" vira duas linhas, ganhando legibilidade
				if (preg_match('/^\d{2}:\d{2}\s*-\s*\d{2}:\d{2}$/u', $texto)) {
					$texto = preg_replace('/\s*-\s*/u', "\n", $texto);
				}
				// Férias: em vez de "FÉRIAS" inteiro (que estreita demais a célula),
				// usa a quebra "FÉ-\nRIAS" e destaca em negrito
				if (escalaPdfTextoSemAcento($texto) === "FERIAS") {
					$texto = "FÉ-\nRIAS";
					$negrito = true;
				}
				$celulas[] = [
					"texto" => $texto,
					"cor" => escalaPdfCorFundo($node->getAttribute("style")),
					"negrito" => $negrito,
				];
			}
			if (!empty($celulas)) {
				$linhas[] = $celulas;
			}
		}
		return $linhas;
	}

	function escalaPdfChaveColuna(string $texto): ?string {
		$t = mb_strtolower(trim($texto), "UTF-8");
		if (preg_match('/^\d{1,2}\b/u', $t)) {
			return null; // coluna de dia
		}
		if (mb_strpos($t, "subsetor") !== false) {
			return "subsetor";
		}
		if (mb_strpos($t, "matr") !== false) {
			return "matr";
		}
		if (mb_strpos($t, "nome") !== false) {
			return "nome";
		}
		if (mb_strpos($t, "empresa") !== false) {
			return "empresa";
		}
		if (mb_strpos($t, "ocupa") !== false) {
			return "ocupacao";
		}
		if (mb_strpos($t, "cargo") !== false) {
			return "cargo";
		}
		if (mb_strpos($t, "setor") !== false) {
			return "setor";
		}
		if (mb_strpos($t, "escala") !== false) {
			return "escala";
		}
		if (mb_strpos($t, "total") !== false) {
			return "total";
		}
		return null; // coluna flexível
	}

	function escalaPdfLarguras(array $cabecalhoTextos, float $larguraTotal): array {
		$largurasBase = [
			"matr" => 15.0,
			"nome" => 42.0,
			"empresa" => 26.0,
			"ocupacao" => 17.0,
			"cargo" => 20.0,
			"setor" => 20.0,
			"subsetor" => 20.0,
			"escala" => 14.0,
			"total" => 13.0,
		];
		$expansoes = [
			"nome" => 30.0,
			"empresa" => 16.0,
			"cargo" => 8.0,
			"setor" => 8.0,
			"subsetor" => 8.0,
			"escala" => 6.0,
			"ocupacao" => 6.0,
			"matr" => 4.0,
			"total" => 4.0,
		];

		$larguras = [];
		$chaves = [];
		$indicesFlexiveis = [];
		$somaFixas = 0.0;
		foreach ($cabecalhoTextos as $i => $texto) {
			$chave = escalaPdfChaveColuna($texto);
			if ($chave !== null && isset($largurasBase[$chave])) {
				$larguras[$i] = $largurasBase[$chave];
				$somaFixas += $largurasBase[$chave];
			} else {
				$chave = "flex";
				$larguras[$i] = 0.0;
				$indicesFlexiveis[] = $i;
			}
			$chaves[$i] = $chave;
		}

		$qtdFlexiveis = count($indicesFlexiveis);
		if ($qtdFlexiveis > 0) {
			$minFlex = 2.4;
			$maxFlex = 16.0;
			$larguraFlexivel = ($larguraTotal - $somaFixas) / $qtdFlexiveis;

			if ($larguraFlexivel < $minFlex) {
				$larguraFlexivel = $minFlex;
				$disponivel = $larguraTotal - ($larguraFlexivel * $qtdFlexiveis);
				$fator = ($somaFixas > 0) ? max(0.35, $disponivel / $somaFixas) : 1.0;
				foreach ($larguras as $i => $w) {
					if ($w > 0) {
						$larguras[$i] = $w * $fator;
					}
				}
			} elseif ($larguraFlexivel > $maxFlex) {
				$larguraFlexivel = $maxFlex;
				$sobra = $larguraTotal - ($larguraFlexivel * $qtdFlexiveis);
				foreach ($expansoes as $chave => $extra) {
					foreach ($larguras as $i => $w) {
						if ($sobra <= 0) {
							break 2;
						}
						if ($chaves[$i] === $chave && $w > 0) {
							$add = min($extra, $sobra);
							$larguras[$i] += $add;
							$sobra -= $add;
						}
					}
				}
			}

			foreach ($indicesFlexiveis as $i) {
				$larguras[$i] = $larguraFlexivel;
			}
		}

		$soma = array_sum($larguras);
		if ($soma > $larguraTotal && $soma > 0) {
			// Segurança: nunca ultrapassar a largura útil da folha
			$fator = $larguraTotal / $soma;
			foreach ($larguras as $i => $w) {
				$larguras[$i] = $w * $fator;
			}
		}

		ksort($larguras);
		return array_values($larguras);
	}

	function escalaPdfMedirLinhas($pdf, array $linhas, array $larguras, float $fonte, float $pad): array {
		static $cache = [];
		$alturas = [];
		$total = 0.0;
		foreach ($linhas as $linha) {
			$alturaLinha = 0.0;
			foreach ($linha as $i => $celula) {
				$w = $larguras[$i] ?? 8.0;
				$negrito = !empty($celula["negrito"]);
				$chave = $fonte . "|" . $pad . "|" . $w . "|" . ($negrito ? "B" : "N") . "|" . $celula["texto"];
				if (isset($cache[$chave])) {
					$h = $cache[$chave];
				} else {
					$pdf->SetFont("helvetica", $negrito ? "B" : "", $fonte);
					$pdf->setCellPadding($pad);
					$h = $pdf->getStringHeight($w, $celula["texto"], false, true, null, 0);
					$cache[$chave] = $h;
				}
				if ($h > $alturaLinha) {
					$alturaLinha = $h;
				}
			}
			$alturas[] = $alturaLinha;
			$total += $alturaLinha;
		}
		return ["linhas" => $alturas, "total" => $total];
	}

	function escalaPdfIdentificarColunas(array $cabecalho): array {
		$fixos = [];
		$dias = [];
		foreach ($cabecalho as $i => $texto) {
			$chave = escalaPdfChaveColuna($texto);
			if ($chave === null) {
				$dias[] = $i;
			} else {
				$fixos[$i] = $chave;
			}
		}
		return [$fixos, $dias];
	}

	function escalaPdfRotuloBloco(array $cabecalhoBloco): string {
		$numeros = [];
		foreach ($cabecalhoBloco as $texto) {
			if (preg_match('/^(\d{1,2})\b/u', trim($texto), $m)) {
				$numeros[] = $m[1];
			}
		}
		if (empty($numeros)) {
			return "";
		}
		return (count($numeros) === 1) ? "Dia " . $numeros[0] : "Dias " . $numeros[0] . " a " . end($numeros);
	}

	function escalaPdfConstruirBlocos(array $linhas, array $fixos, array $dias, int $divisoes, bool $comRotulo): array {
		$totalColunas = count($linhas[0]);

		if ($divisoes <= 1 || count($dias) < 2) {
			$particoes = [$dias];
		} else {
			$particoes = [];
			$restante = $dias;
			$porBloco = intdiv(count($dias), $divisoes);
			for ($b = 0; $b < $divisoes; $b++) {
				if ($b === $divisoes - 1) {
					$particoes[] = $restante;
				} else {
					$particoes[] = array_splice($restante, 0, $porBloco);
				}
			}
		}

		$blocos = [];
		foreach ($particoes as $parte) {
			if (empty($parte)) {
				continue;
			}
			$incluir = array_fill(0, $totalColunas, false);
			foreach (array_keys($fixos) as $i) {
				$incluir[$i] = true;
			}
			foreach ($parte as $i) {
				$incluir[$i] = true;
			}

			$linhasBloco = [];
			foreach ($linhas as $linha) {
				$nova = [];
				foreach ($linha as $i => $celula) {
					if (!empty($incluir[$i])) {
						$nova[] = $celula;
					}
				}
				$linhasBloco[] = $nova;
			}

			$cabecalhoBloco = array_map(function ($c) {
				return $c["texto"] ?? "";
			}, $linhasBloco[0]);

			$blocos[] = [
				"rotulo" => $comRotulo ? escalaPdfRotuloBloco($cabecalhoBloco) : "",
				"linhas" => $linhasBloco,
				"cabecalho" => $cabecalhoBloco,
			];
		}
		return $blocos;
	}

	function escalaPdfAplicarLargurasBlocos(array $blocos, float $larguraTotal): array {
		if (empty($blocos)) {
			return $blocos;
		}

		// Bloco de referência: o que tem mais colunas de dia (colunas mais estreitas)
		$referencia = null;
		$maxFlex = -1;
		foreach ($blocos as $bloco) {
			$flex = 0;
			foreach ($bloco["cabecalho"] as $texto) {
				if (escalaPdfChaveColuna($texto) === null) {
					$flex++;
				}
			}
			if ($flex > $maxFlex) {
				$maxFlex = $flex;
				$referencia = $bloco;
			}
		}
		if ($referencia === null) {
			return $blocos;
		}

		$largurasRef = escalaPdfLarguras($referencia["cabecalho"], $larguraTotal);
		$larguraDia = 0.0;
		$largurasFixas = [];
		foreach ($referencia["cabecalho"] as $i => $texto) {
			$chave = escalaPdfChaveColuna($texto);
			if ($chave === null) {
				$larguraDia = $largurasRef[$i];
			} else {
				$largurasFixas[$chave] = $largurasRef[$i];
			}
		}

		foreach ($blocos as $bi => $bloco) {
			$larguras = [];
			foreach ($bloco["cabecalho"] as $texto) {
				$chave = escalaPdfChaveColuna($texto);
				if ($chave === null || !isset($largurasFixas[$chave])) {
					$larguras[] = $larguraDia;
				} else {
					$larguras[] = $largurasFixas[$chave];
				}
			}
			$blocos[$bi]["larguras"] = $larguras;
		}

		return $blocos;
	}

	function escalaPdfMedirBlocos($pdf, array $blocos, float $fonte, float $pad, float $alturaFaixa, float $espacoBlocos): array {
		$total = 0.0;
		$linhasPorBloco = [];
		$qtdBlocos = count($blocos);
		foreach ($blocos as $bi => $bloco) {
			$medida = escalaPdfMedirLinhas($pdf, $bloco["linhas"], $bloco["larguras"], $fonte, $pad);
			$linhasPorBloco[$bi] = $medida["linhas"];
			$total += $medida["total"];
			if (($bloco["rotulo"] ?? "") !== "") {
				$total += $alturaFaixa;
			}
			if ($bi < $qtdBlocos - 1) {
				$total += $espacoBlocos;
			}
		}
		return ["total" => $total, "linhas" => $linhasPorBloco];
	}

	function escalaPdfTokensCabem($pdf, array $blocos, float $fonte, float $pad, float $margem = 0.3): bool {
		static $cache = [];
		$pdf->SetFont("helvetica", "", $fonte);
		foreach ($blocos as $bloco) {
			foreach ($bloco["linhas"] as $linha) {
				foreach ($linha as $i => $celula) {
					$texto = $celula["texto"] ?? "";
					// Confere tokens de celulas de horario, cabecalhos e valores de palavra unica
					// (ex.: matricula) para evitar quebra no meio da palavra
					$temQuebraForcada = (strpos($texto, "\n") !== false);
					$palavraUnica = !$temQuebraForcada && (strpos($texto, " ") === false);
					if (empty($celula["negrito"]) && !$temQuebraForcada && !$palavraUnica) {
						continue;
					}
					$wmax = ($bloco["larguras"][$i] ?? 8.0) - (2 * $pad) - $margem;
					if ($wmax <= 0) {
						return false;
					}
					foreach (explode("\n", $texto) as $parte) {
						foreach (explode(" ", $parte) as $token) {
							if ($token === "") {
								continue;
							}
							$chave = $fonte . "|" . $token;
							if (!isset($cache[$chave])) {
								$cache[$chave] = $pdf->GetStringWidth($token);
							}
							if ($cache[$chave] > $wmax) {
								return false;
							}
						}
					}
				}
			}
		}
		return true;
	}

	function escalaPdfEscolherFonteBlocos($pdf, array $blocos, float $alturaDisponivel, float $alturaFaixa, float $espacoBlocos): array {
		$candidatas = [12, 11, 10, 9, 8.5, 8, 7.5, 7, 6.5, 6, 5.5, 5, 4.5, 4, 3.6, 3.2, 2.8, 2.4, 2.0];
		$melhor = null;

		$lo = 0;
		$hi = count($candidatas) - 1;
		while ($lo <= $hi) {
			$meio = intval(($lo + $hi) / 2);
			$fonte = $candidatas[$meio];
			$pad = min(0.8, max(0.15, round($fonte * 0.08, 2)));
			$tokens = escalaPdfTokensCabem($pdf, $blocos, $fonte, $pad);
			$medida = escalaPdfMedirBlocos($pdf, $blocos, $fonte, $pad, $alturaFaixa, $espacoBlocos);
			if ($tokens && $medida["total"] <= $alturaDisponivel) {
				$melhor = [
					"fonte" => $fonte,
					"pad" => $pad,
					"medida" => $medida,
					"coube" => true,
					"tokens" => true,
				];
				$hi = $meio - 1; // ainda cabe fonte maior?
			} else {
				$lo = $meio + 1; // precisa diminuir
			}
		}

		if ($melhor === null) {
			$fonte = end($candidatas);
			$pad = 0.15;
			$medida = escalaPdfMedirBlocos($pdf, $blocos, $fonte, $pad, $alturaFaixa, $espacoBlocos);
			$melhor = [
				"fonte" => $fonte,
				"pad" => $pad,
				"medida" => $medida,
				"coube" => ($medida["total"] <= $alturaDisponivel),
				"tokens" => escalaPdfTokensCabem($pdf, $blocos, $fonte, $pad),
			];
		}

		return $melhor;
	}

	function escalaPdfDesenharFaixa($pdf, string $texto, float $x, float $y, float $largura, float $altura): void {
		$pdf->SetFillColor(226, 232, 240);
		$pdf->SetDrawColor(70, 70, 70);
		$pdf->SetLineWidth(0.12);
		$pdf->Rect($x, $y, $largura, $altura, "DF");
		$pdf->SetFont("helvetica", "B", 8);
		$pdf->setCellPadding(0.4);
		$pdf->MultiCell($largura, $altura, $texto, 0, "C", false, 0, $x, $y, true, 0, false, true, $altura, "M", false);
	}

	function escalaPdfDesenharLinha($pdf, array $linha, float $altura, array $larguras, float $x0, float $y, float $fonte, float $pad, bool $cabecalho): void {
		$x = $x0;
		foreach ($linha as $i => $celula) {
			$w = $larguras[$i] ?? 8.0;
			$cor = (!empty($celula["cor"])) ? $celula["cor"] : ($cabecalho ? [236, 236, 236] : null);
			if (!empty($cor)) {
				$pdf->SetFillColor($cor[0], $cor[1], $cor[2]);
				$pdf->Rect($x, $y, $w, $altura, "DF");
			} else {
				$pdf->Rect($x, $y, $w, $altura, "D");
			}
			$x += $w;
		}

		$x = $x0;
		foreach ($linha as $i => $celula) {
			$w = $larguras[$i] ?? 8.0;
			$pdf->SetFont("helvetica", ($cabecalho || !empty($celula["negrito"])) ? "B" : "", $fonte);
			$pdf->setCellPadding($pad);
			$pdf->MultiCell($w, $altura, $celula["texto"], 0, "C", false, 0, $x, $y, true, 0, false, true, $altura, "M", false);
			$x += $w;
		}
	}

	function escalaPdfDesenharBlocos($pdf, array $blocos, float $fonte, float $pad, float $alturaFaixa, float $espacoBlocos, array $alturasPorBloco): void {
		$margens = $pdf->getMargins();
		$larguraConteudo = $pdf->getPageWidth() - $margens["left"] - $margens["right"];
		$limite = $pdf->getPageHeight() - $margens["bottom"] - 1;
		$y = $margens["top"];
		$primeiroBloco = true;

		$pdf->SetDrawColor(70, 70, 70);
		$pdf->SetLineWidth(0.12);

		foreach ($blocos as $bi => $bloco) {
			$linhas = $bloco["linhas"];
			$larguras = $bloco["larguras"];
			$alturas = $alturasPorBloco[$bi] ?? [];
			$somaLarguras = array_sum($larguras);
			$x0 = $margens["left"];
			if ($somaLarguras > 0 && $somaLarguras < $larguraConteudo) {
				// Centraliza o bloco quando sobra espaço lateral
				$x0 += ($larguraConteudo - $somaLarguras) / 2;
			}

			$rotulo = $bloco["rotulo"] ?? "";
			$alturaFaixaLocal = ($rotulo !== "") ? $alturaFaixa : 0.0;
			$alturaCabecalho = $alturas[0] ?? 4.0;

			if (!$primeiroBloco) {
				if ($y + $espacoBlocos + $alturaFaixaLocal + $alturaCabecalho > $limite + 0.02) {
					$pdf->AddPage();
					$y = $pdf->getMargins()["top"];
				} else {
					$y += $espacoBlocos;
				}
			}
			$primeiroBloco = false;

			if ($y + $alturaFaixaLocal + $alturaCabecalho > $limite + 0.02) {
				$pdf->AddPage();
				$y = $pdf->getMargins()["top"];
			}

			if ($alturaFaixaLocal > 0) {
				escalaPdfDesenharFaixa($pdf, $rotulo, $x0, $y, $somaLarguras, $alturaFaixaLocal);
				$y += $alturaFaixaLocal;
			}

			foreach ($linhas as $idx => $linha) {
				$altura = $alturas[$idx] ?? 4.0;
				if ($y + $altura > $limite + 0.02) {
					$pdf->AddPage();
					$y = $pdf->getMargins()["top"];
					if (!empty($linhas[0])) {
						escalaPdfDesenharLinha($pdf, $linhas[0], $alturaCabecalho, $larguras, $x0, $y, $fonte, $pad, true);
						$y += $alturaCabecalho;
					}
				}
				escalaPdfDesenharLinha($pdf, $linha, $altura, $larguras, $x0, $y, $fonte, $pad, ($idx === 0));
				$y += $altura;
			}
		}
	}
