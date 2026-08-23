<?php
    /* ============================================================
       Suporte — Helper de Anexos
       Renderiza os anexos do chamado (imagem/vídeo/documento).
       Uso: suporte_render_anexos($arquivos, $ticketId, "imagem.php")
       ============================================================ */

    if (!function_exists("suporte_render_anexos")) {
        function suporte_render_anexos(array $arquivos, int $ticketId, string $proxyScript): string {
            if (empty($arquivos)) {
                return '<p class="text-muted"><i class="fa fa-info-circle"></i> Este chamado não possui anexos.</p>';
            }

            $iconesDocumento = [
                "pdf"  => "fa-file-pdf-o",
                "doc"  => "fa-file-word-o",
                "docx" => "fa-file-word-o",
                "xls"  => "fa-file-excel-o",
                "xlsx" => "fa-file-excel-o",
                "ppt"  => "fa-file-powerpoint-o",
                "pptx" => "fa-file-powerpoint-o",
                "txt"  => "fa-file-text-o",
                "csv"  => "fa-file-text-o",
            ];

            $html = '<div style="display:flex;flex-wrap:wrap;gap:10px;">';
            foreach ($arquivos as $a) {
                $arquivoId = (int) ($a["id"] ?? 0);
                $nome = strval($a["nome_original"] ?? "");
                $tipo = strval($a["tipo"] ?? "imagem");
                $tamanhoKb = number_format((int) ($a["tamanho_bytes"] ?? 0) / 1024, 1, ",", ".");
                $url = $proxyScript . "?id=" . $ticketId . "&arquivo=" . $arquivoId;

                $html .= '<a href="' . $url . '" target="_blank" title="' . htmlspecialchars($nome, ENT_QUOTES) . '">';
                $html .= '<div style="border:1px solid #ddd;border-radius:6px;overflow:hidden;width:150px;text-align:center;">';

                if ($tipo === "imagem") {
                    $html .= '<img src="' . $url . '" style="width:150px;height:110px;object-fit:cover;display:block;" '
                        . 'onerror="this.parentElement.innerHTML=\'<div style=\\\'padding:30px 8px;color:#999;\\\'>Sem prévia<br><small>' . htmlspecialchars($nome, ENT_QUOTES) . '</small></div>\';" />';
                } elseif ($tipo === "video") {
                    $html .= '<video controls style="width:150px;height:110px;background:#000;display:block;"><source src="' . $url . '"></video>';
                } else {
                    $pontoIdx = strrpos($nome, ".");
                    $ext = $pontoIdx !== false ? strtolower(substr($nome, $pontoIdx + 1)) : "";
                    $icone = $iconesDocumento[$ext] ?? "fa-file-o";
                    $html .= '<div style="padding:24px 8px;color:#555;"><i class="fa ' . $icone . '" style="font-size:32px;"></i></div>';
                }

                $html .= '<div style="padding:4px;font-size:11px;color:#555;word-break:break-all;">';
                $html .= htmlspecialchars($nome);
                $html .= '<br><small>' . $tamanhoKb . ' KB</small>';
                $html .= '</div></div></a>';
            }
            $html .= '</div>';
            return $html;
        }
    }
