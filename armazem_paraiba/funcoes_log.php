<?php
	// Helper generico de logs do sistema (multi-modulo).
	// Persiste em banco (tabela log_sistema) em vez de arquivos.

	if(!function_exists("registrar_log")){
		function registrar_log(
			string $modulo,
			string $evento,
			string $detalhe = "",
			array $extra = [],
			string $nivel = "info",
			array $contexto = []
		): bool {
			global $conn;

			$modulo = trim($modulo) !== "" ? trim($modulo) : "sistema";
			$evento = trim($evento) !== "" ? trim($evento) : "evento";
			$niveisValidos = ["debug","info","warning","error","critical"];
			if(!in_array($nivel, $niveisValidos, true)){
				$nivel = "info";
			}

			$detalhe = preg_replace('/[\r\n]+/', " ", trim($detalhe));

			$extraJson = null;
			if(!empty($extra)){
				$extraJson = json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				if($extraJson === false){
					$extraJson = null;
				}
			}

			$usuario = array_key_exists("usuario", $contexto) ? intval($contexto["usuario"]) : intval($_SESSION["user_nb_id"] ?? 0);
			$login = array_key_exists("login", $contexto) ? trim(strval($contexto["login"])) : trim(strval($_SESSION["user_tx_login"] ?? ""));
			$ip = array_key_exists("ip", $contexto) ? trim(strval($contexto["ip"])) : trim(strval($_SERVER["REMOTE_ADDR"] ?? ""));
			$origem = array_key_exists("origem", $contexto) ? trim(strval($contexto["origem"])) : "";
			$recursoTipo = array_key_exists("recurso_tipo", $contexto) ? trim(strval($contexto["recurso_tipo"])) : "";
			$recursoId = array_key_exists("recurso_id", $contexto) && $contexto["recurso_id"] !== null ? intval($contexto["recurso_id"]) : null;
			$metodo = array_key_exists("metodo", $contexto) ? trim(strval($contexto["metodo"])) : trim(strval($_SERVER["REQUEST_METHOD"] ?? ""));
			$uri = array_key_exists("uri", $contexto) ? trim(strval($contexto["uri"])) : trim(strval($_SERVER["REQUEST_URI"] ?? ""));
			$userAgent = array_key_exists("user_agent", $contexto) ? trim(strval($contexto["user_agent"])) : trim(strval($_SERVER["HTTP_USER_AGENT"] ?? ""));
			$session = array_key_exists("session", $contexto) ? trim(strval($contexto["session"])) : trim(strval(session_id()));

			// Fallback se o banco nao estiver disponivel: nao quebra o fluxo principal.
			if(!($conn instanceof mysqli)){
				error_log(sprintf(
					"[log] modulo=%s evento=%s nivel=%s usuario=%d ip=%s detalhe=%s extra=%s",
					$modulo, $evento, $nivel, $usuario, $ip, $detalhe, strval($extraJson)
				));
				return false;
			}

			try {
				$colunas = [
					"log_tx_modulo", "log_tx_evento", "log_tx_nivel", "log_tx_detalhe",
					"log_tx_extra", "log_nb_usuario", "log_tx_login", "log_tx_ip",
					"log_tx_origem", "log_tx_recurso_tipo", "log_nb_recurso",
					"log_tx_metodo", "log_tx_uri", "log_tx_user_agent", "log_tx_session",
					"log_tx_dataCadastro"
				];
				$valores = [
					$modulo, $evento, $nivel, ($detalhe !== "" ? $detalhe : null),
					$extraJson, ($usuario > 0 ? $usuario : null), ($login !== "" ? $login : null),
					($ip !== "" ? $ip : null), ($origem !== "" ? $origem : null),
					($recursoTipo !== "" ? $recursoTipo : null), $recursoId,
					($metodo !== "" ? $metodo : null), ($uri !== "" ? $uri : null),
					($userAgent !== "" ? substr($userAgent, 0, 255) : null),
					($session !== "" ? $session : null),
					date("Y-m-d H:i:s")
				];

				if(function_exists("inserir")){
					inserir("log_sistema", $colunas, $valores);
				} else {
					$placeholders = implode(",", array_fill(0, count($colunas), "?"));
					$sql = "INSERT INTO log_sistema (" . implode(",", $colunas) . ") VALUES (" . $placeholders . ")";
					// fallback simples sem tipos estritos
					mysqli_query($conn, $sql);
				}
				return true;
			} catch (Throwable $e) {
				error_log("[log] falha ao gravar log em banco: " . $e->getMessage());
				return false;
			}
		}
	}

	if(!function_exists("log_limpar")){
		function log_limpar(?string $modulo = null, int $dias = 30): void {
			global $conn;
			if(!($conn instanceof mysqli) || $dias <= 0){
				return;
			}
			try {
				$corte = date("Y-m-d H:i:s", strtotime("-" . intval($dias) . " days"));
				if($modulo !== null && trim($modulo) !== ""){
					query("DELETE FROM log_sistema WHERE log_tx_modulo = ? AND log_tx_dataCadastro < ?", "ss", [trim($modulo), $corte]);
				} else {
					query("DELETE FROM log_sistema WHERE log_tx_dataCadastro < ?", "s", [$corte]);
				}
			} catch (Throwable $e) {
				error_log("[log] falha ao limpar logs: " . $e->getMessage());
			}
		}
	}
