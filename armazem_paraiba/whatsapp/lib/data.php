<?php

/**
 * Datas do módulo.
 *
 * O MySQL grava os horários em UTC (a conexão do módulo força UTC) e o PHP
 * trabalha no fuso do servidor (America/Fortaleza). A conversão deve ser
 * explícita apenas na exibição, para não depender do fuso do navegador.
 */

if (!function_exists('wa_db_timezone_utc')) {
    function wa_db_timezone_utc(): void {
        global $conn;
        if ($conn instanceof mysqli) {
            @mysqli_query($conn, "SET time_zone = '+00:00'");
        }
    }
}

if (!function_exists('wa_data_utc_para_local')) {
    function wa_data_utc_para_local(?string $valor, string $formato = 'd/m/Y H:i'): string {
        $valor = trim(strval($valor));
        if ($valor === '' || str_starts_with($valor, '0000-00-00')) {
            return '';
        }
        try {
            $tzServidor = new DateTimeZone(date_default_timezone_get() ?: 'America/Fortaleza');
            $dt = new DateTime($valor, new DateTimeZone('UTC'));
            $dt->setTimezone($tzServidor);
            return $dt->format($formato);
        } catch (Throwable $e) {
            return '';
        }
    }
}
