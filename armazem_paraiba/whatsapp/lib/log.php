<?php
/**
 * Log do módulo WhatsApp.
 * Usa o log central do sistema quando disponível; caso contrário, error_log.
 */

if (!function_exists('wa_log')) {
    function wa_log(string $evento, string $detalhe = '', array $extra = [], string $nivel = 'info'): void {
        try {
            if (function_exists('registrar_log')) {
                registrar_log('whatsapp', $evento, $detalhe, $extra, $nivel);
                return;
            }
        } catch (Throwable $e) {
            // segue para o fallback
        }
        error_log('[whatsapp][' . $nivel . '] ' . $evento . ' ' . $detalhe . ' ' . json_encode($extra, JSON_UNESCAPED_UNICODE));
    }
}
