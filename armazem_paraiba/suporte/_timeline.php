<?php
    /* ============================================================
       Suporte — Helper da Timeline
       Renderiza a linha do tempo dos eventos do chamado.
       Uso: suporte_render_timeline($eventos)
       ============================================================ */

    include_once __DIR__ . "/_datas.php";

    if (!function_exists("suporte_render_timeline")) {
        function suporte_render_timeline(array $eventos): string {
            $icones = [
                "aberto"             => ["#27ae60", "fa fa-folder-open", "Chamado aberto"],
                "aceito"             => ["#337ab7", "fa fa-handshake-o", "Atendimento iniciado"],
                "tipo"               => ["#8e44ad", "fa fa-tag", "Classificação"],
                "prioridade"         => ["#c0392b", "fa fa-flag", "Prioridade alterada"],
                "status"             => ["#e67e22", "fa fa-exchange", "Status alterado"],
                "comentario_gestor"  => ["#16a085", "fa fa-comment", "Resposta do suporte"],
                "comentario_empresa" => ["#2c3e50", "fa fa-reply", "Resposta do cliente"],
            ];

            if (empty($eventos)) {
                return '<p class="text-muted"><i class="fa fa-info-circle"></i> Nenhum evento registrado.</p>';
            }

            $html = '<div style="position:relative;padding-left:34px;border-left:2px solid #e5e5e5;margin-left:12px;">';
            foreach ($eventos as $e) {
                $tipo = strval($e["evento"] ?? "");
                $def = $icones[$tipo] ?? ["#999", "fa fa-circle-o", "Evento"];
                $cor = $def[0];
                $icone = $def[1];
                $titulo = $def[2];
                $descricao = trim(strval($e["descricao"] ?? ""));
                $autor = trim(strval($e["autor"] ?? ""));
                $data = suporte_fmt_data(strval($e["created_at"] ?? ""));

                $html .= '<div style="position:relative;margin-bottom:16px;">';
                $html .= '<span style="position:absolute;left:-43px;top:0;width:22px;height:22px;border-radius:50%;background:' . $cor . ';color:#fff;text-align:center;line-height:22px;font-size:10px;"><i class="' . $icone . '" aria-hidden="true"></i></span>';
                $html .= '<div style="font-size:13px;font-weight:700;color:#333;">' . htmlspecialchars($titulo) . '</div>';
                $html .= '<div style="font-size:12px;color:#666;">' . htmlspecialchars($descricao) . '</div>';
                $html .= '<div style="font-size:11px;color:#999;margin-top:2px;">' . htmlspecialchars($data);
                if ($autor !== "") {
                    $html .= ' — <i class="fa fa-user-circle"></i> ' . htmlspecialchars($autor);
                }
                $html .= '</div></div>';
            }
            $html .= '</div>';
            return $html;
        }
    }
