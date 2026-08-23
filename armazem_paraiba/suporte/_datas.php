<?php
    /* ============================================================
       Suporte — Helper de Datas
       Converte o timestamp UTC (ISO, terminado em "Z") devolvido pela
       API de suporte para o horário de Brasília, formatado como
       "dd/mm/aaaa hh:mm".
       Uso: suporte_fmt_data($isoUtc)
       ============================================================ */

    if (!function_exists("suporte_fmt_data")) {
        function suporte_fmt_data(?string $isoUtc): string {
            $isoUtc = trim(strval($isoUtc ?? ""));
            if ($isoUtc === "") {
                return "";
            }
            try {
                $dt = new DateTime($isoUtc, new DateTimeZone("UTC"));
                $dt->setTimezone(new DateTimeZone("America/Sao_Paulo"));
                return $dt->format("d/m/Y H:i");
            } catch (Exception $e) {
                return $isoUtc;
            }
        }
    }
