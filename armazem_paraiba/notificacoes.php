<?php
/* ============================================================
   Motor de notificações do sino do cabeçalho — categorias de
   alerta de gestão (não conformidade, jornada crítica, CNH,
   férias), configuráveis por usuário, com opção de e-mail.
   Só é incluído para usuários não-operacionais (quem também vê
   a Torre de Comando) — reaproveita as mesmas consultas/limiares.
   Pressupõe que quem inclui este arquivo já rodou conecta.php.
   ============================================================ */

// Mesma proteção usada em torre_comando.php: se query() falhar (tabela/coluna que não
// existe nesse ambiente), evita o TypeError fatal de passar `false` para o mysqli_fetch_*.
if (!function_exists("torre_fetch_assoc")) {
    function torre_fetch_assoc($resultado): array {
        if ($resultado === false || $resultado === null) return [];
        return mysqli_fetch_assoc($resultado) ?: [];
    }
}
if (!function_exists("torre_fetch_all")) {
    function torre_fetch_all($resultado, int $modo = MYSQLI_ASSOC): array {
        if ($resultado === false || $resultado === null) return [];
        return mysqli_fetch_all($resultado, $modo) ?: [];
    }
}

function notificacao_ensure_schema(): void {
    $dbRow = torre_fetch_assoc(query("SELECT DATABASE() AS db"));
    $db = strval($dbRow["db"] ?? "");
    if ($db === "") return;

    $exists = torre_fetch_assoc(query(
        "SELECT 1 AS ok FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'notificacao_preferencia' LIMIT 1",
        "s", [$db]
    ));
    if (empty($exists)) {
        query(
            "CREATE TABLE IF NOT EXISTS notificacao_preferencia (
                noti_nb_id INT AUTO_INCREMENT PRIMARY KEY,
                noti_nb_usuario INT NOT NULL,
                noti_tx_categorias TEXT NOT NULL,
                noti_tx_email_ativo ENUM('sim','nao') NOT NULL DEFAULT 'nao',
                noti_tx_email VARCHAR(190) NOT NULL DEFAULT '',
                noti_tx_dataAtualiza DATETIME NOT NULL,
                UNIQUE KEY uniq_usuario (noti_nb_usuario)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}

// Catálogo de categorias que o usuário pode ligar/desligar — mesmos alertas que já existem na Torre de Comando.
function notificacao_categorias_disponiveis(): array {
    return [
        "nc_alta"            => "Não conformidade de gravidade alta",
        "jornada_critica"    => "Jornadas críticas (sem bater ponto há 24h+)",
        "cnh_vencendo"       => "CNH vencendo em 30 dias",
        "ferias_proximas"    => "Férias começando em 7 dias",
        "afastamentos_hoje"  => "Afastamentos ativos hoje",
        "advertencias"       => "Advertências candidatas no mês",
        "abonos_mes"         => "Abonos lançados no mês",
        "frota_indisponivel" => "Motoristas indisponíveis agora",
        "saldo_negativo"     => "Banco de horas do período negativo",
    ];
}

function notificacao_carregar_preferencia(int $usuarioId): array {
    $padrao = ["categorias" => ["nc_alta", "jornada_critica", "cnh_vencendo"], "email_ativo" => false, "email" => ""];
    if ($usuarioId <= 0) return $padrao;
    notificacao_ensure_schema();
    $row = torre_fetch_assoc(query("SELECT * FROM notificacao_preferencia WHERE noti_nb_usuario = ?", "i", [$usuarioId]));
    if (empty($row)) return $padrao;
    $categorias = json_decode(strval($row["noti_tx_categorias"] ?? ""), true);
    return [
        "categorias" => is_array($categorias) ? array_values(array_map("strval", $categorias)) : $padrao["categorias"],
        "email_ativo" => strval($row["noti_tx_email_ativo"] ?? "nao") === "sim",
        "email" => strval($row["noti_tx_email"] ?? ""),
    ];
}

// Calcula as notificações ativas agora, para o conjunto de categorias habilitadas.
// Reaproveita os mesmos limiares e consultas já usados na Torre de Comando (torre_comando.php).
function notificacao_calcular(array $categoriasAtivas): array {
    $itens = [];
    if (empty($categoriasAtivas)) return $itens;

    if (in_array("jornada_critica", $categoriasAtivas, true)) {
        $rows = torre_fetch_all(query(
            "SELECT p.pont_tx_tipo AS tipo, p.pont_tx_data AS ultima_data
             FROM ponto p
             INNER JOIN (
                SELECT pont_tx_matricula, MAX(pont_tx_data) AS max_data
                FROM ponto WHERE pont_tx_status = 'ativo' AND pont_tx_data >= (NOW() - INTERVAL 7 DAY)
                GROUP BY pont_tx_matricula
             ) ult ON ult.pont_tx_matricula = p.pont_tx_matricula AND ult.max_data = p.pont_tx_data
             JOIN entidade e ON e.enti_tx_matricula = p.pont_tx_matricula
             WHERE p.pont_tx_status = 'ativo' AND e.enti_tx_status = 'ativo'"
        ), MYSQLI_ASSOC) ?: [];
        $criticas = 0;
        $agora = time();
        foreach ($rows as $r) {
            $tipo = intval($r["tipo"]);
            if ($tipo === 2) continue; // fora de jornada, não conta
            $ultima = strtotime(strval($r["ultima_data"]));
            if ($ultima && (($agora - $ultima) / 3600) > 24) $criticas++;
        }
        if ($criticas > 0) {
            $itens[] = [
                "icone" => "fa-exclamation-triangle", "cor" => "#d9534f",
                "titulo" => $criticas . " jornada(s) crítica(s)",
                "texto" => "Sem bater ponto há mais de 24h",
                "link" => "paineis/jornada.php",
            ];
        }
    }

    if (in_array("cnh_vencendo", $categoriasAtivas, true)) {
        $total = intval((torre_fetch_assoc(query(
            "SELECT COUNT(*) AS c FROM entidade
             WHERE enti_tx_status = 'ativo' AND enti_tx_ocupacao = 'Motorista'
               AND enti_tx_cnhValidade IS NOT NULL AND enti_tx_cnhValidade NOT IN ('', '0000-00-00')
               AND enti_tx_cnhValidade BETWEEN CURDATE() AND (CURDATE() + INTERVAL 30 DAY)"
        ))["c"] ?? 0));
        if ($total > 0) {
            $itens[] = [
                "icone" => "fa-id-card", "cor" => "#d9534f",
                "titulo" => $total . " CNH vencendo",
                "texto" => "Nos próximos 30 dias",
                "link" => "cadastro_funcionario.php",
            ];
        }
    }

    if (in_array("ferias_proximas", $categoriasAtivas, true)) {
        $total = intval((torre_fetch_assoc(query(
            "SELECT COUNT(*) AS c FROM ferias f JOIN entidade e ON e.enti_nb_id = f.feri_nb_entidade
             WHERE f.feri_tx_status = 'ativo' AND e.enti_tx_status = 'ativo'
               AND f.feri_tx_dataInicio BETWEEN (CURDATE() + INTERVAL 1 DAY) AND (CURDATE() + INTERVAL 7 DAY)"
        ))["c"] ?? 0));
        if ($total > 0) {
            $itens[] = [
                "icone" => "fa-umbrella", "cor" => "#f0ad4e",
                "titulo" => $total . " férias começando",
                "texto" => "Nos próximos 7 dias",
                "link" => "cadastro_ferias.php",
            ];
        }
    }

    if (in_array("nc_alta", $categoriasAtivas, true)) {
        include_once __DIR__ . "/torre_comando.php";
        $empresas = torre_fetch_all(query("SELECT empr_nb_id FROM empresa WHERE empr_tx_status = 'ativo'"), MYSQLI_ASSOC) ?: [];
        $idsEmpresas = array_map("intval", array_column($empresas, "empr_nb_id"));
        $ncRef = torre_mes_mais_recente_nc($idsEmpresas);
        if ($ncRef) {
            $totais = torre_nc_totais($ncRef["empresas"], $ncRef["mes"]);
            $grav = torre_gravidade($totais);
            if ($grav["alta"] > 0) {
                $itens[] = [
                    "icone" => "fa-balance-scale", "cor" => "#d9534f",
                    "titulo" => number_format($grav["alta"], 0, ",", ".") . " ocorrência(s) de gravidade alta",
                    "texto" => "Não conformidade — ref. " . torre_mes_label($ncRef["mes"]),
                    "link" => "paineis/nc_juridica.php",
                ];
            }
        }
    }

    if (in_array("afastamentos_hoje", $categoriasAtivas, true)) {
        $total = intval((torre_fetch_assoc(query(
            "SELECT COUNT(*) AS c FROM abono a
             JOIN motivo m ON m.moti_nb_id = a.abon_nb_motivo
             JOIN entidade e ON e.enti_tx_matricula = a.abon_tx_matricula
             WHERE a.abon_tx_status = 'ativo' AND m.moti_tx_tipo = 'Afastamento'
               AND a.abon_tx_data = CURDATE() AND e.enti_tx_status = 'ativo'"
        ))["c"] ?? 0));
        if ($total > 0) {
            $itens[] = [
                "icone" => "fa-user-times", "cor" => "#f0ad4e",
                "titulo" => $total . " afastamento(s) hoje",
                "texto" => "Ausência justificada como afastamento",
                "link" => "cadastro_abono.php",
            ];
        }
    }

    if (in_array("advertencias", $categoriasAtivas, true)) {
        $total = intval((torre_fetch_assoc(query(
            "SELECT COUNT(*) AS c FROM abono a
             JOIN motivo m ON m.moti_nb_id = a.abon_nb_motivo
             JOIN entidade e ON e.enti_tx_matricula = a.abon_tx_matricula
             WHERE a.abon_tx_status = 'ativo' AND m.moti_tx_advertencia = 'sim' AND e.enti_tx_status = 'ativo'
               AND a.abon_tx_data BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND CURDATE()"
        ))["c"] ?? 0));
        if ($total > 0) {
            $itens[] = [
                "icone" => "fa-bullhorn", "cor" => "#f0ad4e",
                "titulo" => $total . " advertência(s) candidata(s)",
                "texto" => "Abonos sinalizados no mês",
                "link" => "cadastro_abono.php",
            ];
        }
    }

    if (in_array("abonos_mes", $categoriasAtivas, true)) {
        $total = intval((torre_fetch_assoc(query(
            "SELECT COUNT(*) AS c FROM abono a
             JOIN entidade e ON e.enti_tx_matricula = a.abon_tx_matricula
             WHERE a.abon_tx_status = 'ativo' AND e.enti_tx_status = 'ativo'
               AND a.abon_tx_data BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND CURDATE()"
        ))["c"] ?? 0));
        if ($total > 0) {
            $itens[] = [
                "icone" => "fa-file-text-o", "cor" => "#5bc0de",
                "titulo" => $total . " abono(s) no mês",
                "texto" => "Total lançado desde o início do mês",
                "link" => "cadastro_abono.php",
            ];
        }
    }

    if (in_array("frota_indisponivel", $categoriasAtivas, true)) {
        $rows = torre_fetch_all(query(
            "SELECT e.enti_tx_matricula AS matricula,
                (SELECT MAX(p2.pont_tx_data) FROM ponto p2 WHERE p2.pont_tx_matricula = e.enti_tx_matricula AND p2.pont_tx_tipo = 2 AND p2.pont_tx_status='ativo') AS ultimo_fim,
                (SELECT MAX(p3.pont_tx_data) FROM ponto p3 WHERE p3.pont_tx_matricula = e.enti_tx_matricula AND p3.pont_tx_status='ativo') AS ultima_batida
             FROM entidade e
             WHERE e.enti_tx_status = 'ativo' AND e.enti_tx_ocupacao = 'Motorista'"
        ), MYSQLI_ASSOC) ?: [];
        $indisponivel = 0;
        foreach ($rows as $m) {
            if (empty($m["ultimo_fim"]) || $m["ultimo_fim"] !== $m["ultima_batida"]) continue; // em jornada — não entra aqui
            $horas = (time() - strtotime($m["ultimo_fim"])) / 3600;
            if ($horas < 8) $indisponivel++;
        }
        if ($indisponivel > 0) {
            $itens[] = [
                "icone" => "fa-truck", "cor" => "#d9534f",
                "titulo" => $indisponivel . " motorista(s) indisponível(is)",
                "texto" => "Menos de 8h de descanso desde a última jornada",
                "link" => "paineis/disponibilidade.php",
            ];
        }
    }

    if (in_array("saldo_negativo", $categoriasAtivas, true)) {
        include_once __DIR__ . "/torre_comando.php";
        $empresas = torre_fetch_all(query("SELECT empr_nb_id FROM empresa WHERE empr_tx_status = 'ativo'"), MYSQLI_ASSOC) ?: [];
        $idsEmpresas = array_map("intval", array_column($empresas, "empr_nb_id"));
        $saldoRef = torre_mes_mais_recente_saldo($idsEmpresas);
        if ($saldoRef) {
            $saldoTotais = torre_saldo_totais($saldoRef["empresas"], $saldoRef["mes"]);
            if ($saldoTotais["saldoFinal"] < 0) {
                $itens[] = [
                    "icone" => "fa-line-chart", "cor" => "#d9534f",
                    "titulo" => "Banco de horas negativo",
                    "texto" => torre_horas_fmt($saldoTotais["saldoFinal"]) . "h — ref. " . torre_mes_label($saldoRef["mes"]),
                    "link" => "paineis/saldo.php",
                ];
            }
        }
    }

    return $itens;
}
