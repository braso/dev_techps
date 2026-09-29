<?php
	/* ============================================================
	   Tela inicial de quem não bate ponto e não tem telas de gestão.
	   Antes essas pessoas caíam na batida de ponto mesmo sem ter a
	   permissão, e ficavam presas numa tela que não era delas.
	   Aqui é só a saudação com a data e a hora grandes; o que a pessoa
	   pode acessar continua no menu de cima.
	   ============================================================ */

	include "conecta.php";

	function bemVindoNomeCurto(): string {
		$nome = trim(strval($_SESSION["user_tx_nome"] ?? ""));
		if($nome === ""){
			$nome = trim(strval($_SESSION["user_tx_login"] ?? ""));
		}
		if($nome === ""){
			return "";
		}
		$partes = preg_split('/\s+/', $nome);
		return ucfirst(mb_strtolower(strval($partes[0]), "UTF-8"));
	}

	function bemVindoDataCompleta(): string {
		$diasSemana = ["domingo", "segunda-feira", "terça-feira", "quarta-feira", "quinta-feira", "sexta-feira", "sábado"];
		$meses = ["janeiro", "fevereiro", "março", "abril", "maio", "junho", "julho", "agosto", "setembro", "outubro", "novembro", "dezembro"];

		$diaSemana = $diasSemana[intval(date("w"))];
		$mes = $meses[intval(date("n")) - 1];

		return $diaSemana.", ".date("j")." de ".$mes." de ".date("Y");
	}

	function bemVindoSaudacao(): string {
		$hora = intval(date("G"));
		if($hora < 12){
			return "Bom dia";
		}
		if($hora < 18){
			return "Boa tarde";
		}
		return "Boa noite";
	}

	function index(){
		global $CONTEX;

		cabecalho("");

		$nome = bemVindoNomeCurto();
		$saudacao = bemVindoSaudacao().($nome !== "" ? ", ".htmlspecialchars($nome) : "");
		$data = htmlspecialchars(bemVindoDataCompleta());
		$hora = date("H:i:s");

		echo <<<HTML
		<style>
			.bv-palco{
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				text-align: center;
				min-height: calc(100vh - 260px);
				padding: 24px 16px;
			}

			.bv-saudacao{
				font-size: 20px;
				color: #5f6b7a;
				margin-bottom: 4px;
			}

			.bv-marca{
				font-size: 30px;
				font-weight: 700;
				color: #2f6fa3;
				margin-bottom: 28px;
			}

			.bv-hora{
				font-size: 96px;
				font-weight: 300;
				line-height: 1;
				letter-spacing: 2px;
				color: #32373d;
				font-variant-numeric: tabular-nums;
			}

			.bv-data{
				margin-top: 12px;
				font-size: 18px;
				color: #5f6b7a;
				text-transform: capitalize;
			}

			.bv-dica{
				margin-top: 36px;
				font-size: 13px;
				color: #98a1ac;
				max-width: 420px;
			}

			@media(max-width:768px){
				.bv-palco{ min-height: calc(100vh - 220px); }
				.bv-marca{ font-size: 24px; margin-bottom: 20px; }
				.bv-hora{ font-size: 56px; letter-spacing: 1px; }
				.bv-data{ font-size: 15px; }
			}
		</style>

		<div class="bv-palco">
			<div class="bv-saudacao">{$saudacao}</div>
			<div class="bv-marca">Bem-vindo à TechPS</div>
			<div class="bv-hora" id="bvHora">{$hora}</div>
			<div class="bv-data">{$data}</div>
			<div class="bv-dica">Use o menu acima para abrir as telas liberadas para o seu perfil.</div>
		</div>

		<script>
			(function(){
				var alvo = document.getElementById("bvHora");
				if(!alvo) return;
				function doisDigitos(n){ return (n < 10 ? "0" : "") + n; }
				function atualizar(){
					var agora = new Date();
					alvo.textContent = doisDigitos(agora.getHours()) + ":" + doisDigitos(agora.getMinutes()) + ":" + doisDigitos(agora.getSeconds());
				}
				atualizar();
				setInterval(atualizar, 1000);
			})();
		</script>
		HTML;

		rodape();
	}
