<?php
/**
 * Endpoint da busca de placa mais próxima, usado pelo ajuste de ponto.
 *
 * Duas ações: "buscar" devolve o ranking das placas da empresa por distância
 * até a coordenada da batida, e "inserir" grava a placa escolhida.
 *
 * ── Sobre a autenticação
 *
 * Passa pelo conecta.php de propósito, que é o portão de login do sistema. Há
 * endpoint neste projeto que abre conexão própria e não confere sessão alguma
 * (ajax_poi_tipo.php); aqui não, porque a resposta expõe a posição da frota.
 *
 * ── Sobre o nome do parâmetro de ação
 *
 * A ação chega em "placaAcao", e não em "acao": o contex20/funcoes.php, que o
 * conecta inclui, executa o $_POST["acao"] como nome de função dentro daquele
 * include. Usar "acao" aqui entregaria a escolha da função ao cliente.
 */

ob_start();

/* Mesmo em erro fatal a resposta sai como JSON: a tela espera JSON e trataria
   um HTML de erro como "falhou sem motivo". */
register_shutdown_function(function () {
	$erro = error_get_last();
	if ($erro && in_array($erro["type"], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
		if (ob_get_level() > 0) {
			ob_end_clean();
		}
		if (!headers_sent()) {
			header("Content-Type: application/json; charset=utf-8");
		}
		echo json_encode([
			"ok"   => false,
			"erro" => "Erro no servidor: " . $erro["message"],
		], JSON_UNESCAPED_UNICODE);
	}
});

require_once __DIR__ . "/placa_proxima.php";

include "conecta.php";

if (ob_get_level() > 0) {
	ob_end_clean();
}
header("Content-Type: application/json; charset=utf-8");

function placaProximaResponder(array $dados): void {
	echo json_encode($dados, JSON_UNESCAPED_UNICODE);
	exit;
}

/* Login não basta: a resposta diz onde está cada veículo da frota. Vale a mesma
   regra da tela que tem o botão — o index() do ajuste_ponto.php libera quem vê o
   espelho, e cobra o /ajuste_ponto.php de quem não vê.
   Usa o usuarioPodeAcessar(), que é a versão do verificaPermissao() feita para
   API: a outra manda o navegador para outra tela, e aqui isso chegaria como um
   HTML no lugar do JSON. */
include_once __DIR__ . "/check_permission.php";

if (!function_exists("usuarioPodeAcessar")) {
	placaProximaResponder(["ok" => false, "erro" => "Não foi possível conferir a permissão."]);
}
if (!temPermissaoMenu("/espelho_ponto.php") && !usuarioPodeAcessar("/ajuste_ponto.php")) {
	placaProximaResponder(["ok" => false, "erro" => "Sem permissão para consultar a posição da frota."]);
}

$acao = strval($_POST["placaAcao"] ?? "");
$idPonto = intval($_POST["idPonto"] ?? 0);

if ($acao === "buscar") {
	/* A janela chega da tela porque o gestor pode querer abrir mais quando a
	   placa certa nao aparece; limitada para nao virar "o dia inteiro conta". */
	$janela = intval($_POST["janelaMinutos"] ?? 60);
	$janela = max(5, min(240, $janela));

	placaProximaResponder(placaProximaDoPonto($idPonto, $janela));
}

if ($acao === "inserir") {
	placaProximaResponder(placaProximaGravar(
		$idPonto,
		strval($_POST["placa"] ?? ""),
		$_SESSION["user_nb_id"] ?? null
	));
}

placaProximaResponder(["ok" => false, "erro" => "Ação inválida."]);
