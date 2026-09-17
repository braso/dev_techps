<?php
    /* ============================================================
       Suporte — funcionários que recebem os chamados
       O tipo do chamado aponta para um setor, e quem está nesse
       setor recebe. A fonte é o cadastro de funcionários do domínio
       Demo: funcionário ativo, em setor marcado como "Disponibilizar
       no módulo de suporte". Este arquivo envia essa lista inteira
       ao servidor central, que avisa e lista quem recebe.
       Chamado ao salvar funcionário/setor no Demo e pelo botão
       "Sincronizar agora" em Gestão de Suporte → Configurações.
       ============================================================ */

    if (!function_exists("suporte_dominio_mestre")) {
        // A lista de setores e de funcionários do suporte só é mantida no domínio Demo.
        function suporte_dominio_mestre(): bool {
            return strpos(trim(strval($_ENV["CONTEX_PATH"] ?? ""), "/"), "demo") !== false;
        }
    }

    if (!function_exists("suporte_sincronizar_membros")) {
        /**
         * Envia ao servidor central os funcionários dos setores de suporte.
         * Nunca interrompe quem chamou: devolve ["ok" => bool, "msg" => string, "total" => int].
         */
        function suporte_sincronizar_membros(int $timeout = 8): array {
            if (!suporte_dominio_mestre()) {
                return ["ok" => false, "msg" => "A lista de funcionários do suporte só é enviada a partir do domínio Demo.", "total" => 0];
            }
            $apiUrl = rtrim(strval($_ENV["SUPORTE_API_URL"] ?? ""), "/");
            $adminKey = strval($_ENV["SUPORTE_ADMIN_KEY"] ?? "");
            if ($apiUrl === "" || $adminKey === "") {
                return ["ok" => false, "msg" => "SUPORTE_API_URL/SUPORTE_ADMIN_KEY não configurados no .env.", "total" => 0];
            }

            // A coluna nasce no Cadastro de Setor; sem ela, nenhum setor está no suporte ainda.
            $rsColuna = query(
                "SELECT 1 AS ok FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grupos_documentos' AND COLUMN_NAME = 'grup_tx_disponivel_suporte'
                 LIMIT 1"
            );
            $temColuna = $rsColuna && mysqli_fetch_assoc($rsColuna);

            $membros = [];
            if ($temColuna) {
                $rs = query(
                    "SELECT e.enti_nb_id, e.enti_tx_nome, e.enti_tx_email, e.enti_setor_id, u.user_tx_login, u.user_tx_email
                     FROM entidade e
                     JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
                     LEFT JOIN user u ON u.user_nb_entidade = e.enti_nb_id AND u.user_tx_status = 'ativo'
                     WHERE e.enti_tx_status = 'ativo'
                       AND g.grup_tx_status = 'ativo'
                       AND g.grup_tx_disponivel_suporte = 'sim'
                     ORDER BY e.enti_nb_id"
                );
                // Leitura falhou: não envia nada, senão o central desativaria todo mundo.
                if (!$rs) {
                    return ["ok" => false, "msg" => "Não foi possível ler os funcionários dos setores de suporte.", "total" => 0];
                }
                while ($r = mysqli_fetch_assoc($rs)) {
                    $idEntidade = (int) $r["enti_nb_id"];
                    if (isset($membros[$idEntidade])) {
                        continue; // mesma pessoa com mais de um usuário
                    }
                    $email = trim(strval($r["enti_tx_email"] ?? ""));
                    if ($email === "") {
                        $email = trim(strval($r["user_tx_email"] ?? ""));
                    }
                    $membros[$idEntidade] = [
                        "origem_entidade_id" => $idEntidade,
                        "nome"               => trim(strval($r["enti_tx_nome"] ?? "")),
                        "email"              => $email,
                        "login"              => trim(strval($r["user_tx_login"] ?? "")),
                        "setor_origem_id"    => (int) $r["enti_setor_id"],
                    ];
                }
            }

            // Lista vazia aqui é real (ninguém em setor de suporte), então o central pode aplicar.
            $corpo = json_encode(["membros" => array_values($membros), "permitir_vazio" => "1"], JSON_UNESCAPED_UNICODE);
            $ch = curl_init($apiUrl . "/suporte/atendentes/sincronizar");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => ["x-api-key: " . $adminKey, "Content-Type: application/json"],
                CURLOPT_POSTFIELDS     => $corpo,
            ]);
            $resposta = curl_exec($ch);
            $erroCurl = curl_errno($ch) ? curl_error($ch) : "";
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $json = json_decode((string) $resposta, true);
            if ($httpCode >= 200 && $httpCode < 300 && is_array($json) && !empty($json["ok"])) {
                $total = (int) ($json["total"] ?? count($membros));
                return ["ok" => true, "msg" => "Funcionários sincronizados: {$total} recebendo chamados.", "total" => $total];
            }
            $detalhe = $erroCurl !== "" ? $erroCurl : strval($json["msg"] ?? ("HTTP " . $httpCode));
            error_log("suporte_sincronizar_membros: " . $detalhe);
            return ["ok" => false, "msg" => $detalhe, "total" => 0];
        }
    }
