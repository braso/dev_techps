<?php
/**
 * Normalização de telefone para o padrão E.164 usado pelo WhatsApp:
 * 55 + DDD + número, somente dígitos.
 */

if (!function_exists('wa_normalizar_telefone')) {
    function wa_normalizar_telefone(?string $telefone): ?string {
        $digits = preg_replace('/\D+/', '', strval($telefone ?? ''));
        if ($digits === null || $digits === '') {
            return null;
        }

        // Remove zero(s) de discagem/prefixo inicial (ex.: 011 98765-4321).
        while (strlen($digits) > 1 && $digits[0] === '0') {
            $digits = substr($digits, 1);
        }

        if (str_starts_with($digits, '55')) {
            $len = strlen($digits);
            if ($len === 12 || $len === 13) {
                return wa_ddd_valido(substr($digits, 2, 2)) ? $digits : null;
            }
            return null;
        }

        $len = strlen($digits);
        if ($len === 10 || $len === 11) {
            if (!wa_ddd_valido(substr($digits, 0, 2))) {
                return null;
            }
            return '55' . $digits;
        }

        return null;
    }
}

if (!function_exists('wa_ddd_valido')) {
    function wa_ddd_valido(string $ddd): bool {
        $n = (int)$ddd;
        return $n >= 11 && $n <= 99;
    }
}

if (!function_exists('wa_formatar_telefone')) {
    function wa_formatar_telefone(?string $e164): string {
        $digits = preg_replace('/\D+/', '', strval($e164 ?? ''));
        if ($digits === null || $digits === '') {
            return '';
        }
        if (str_starts_with($digits, '55') && (strlen($digits) === 12 || strlen($digits) === 13)) {
            $ddd = substr($digits, 2, 2);
            $numero = substr($digits, 4);
            if (strlen($numero) === 9) {
                return "({$ddd}) " . substr($numero, 0, 5) . '-' . substr($numero, 5);
            }
            if (strlen($numero) === 8) {
                return "({$ddd}) " . substr($numero, 0, 4) . '-' . substr($numero, 4);
            }
            return "({$ddd}) {$numero}";
        }
        return $digits;
    }
}

if (!function_exists('wa_telefone_exibicao')) {
    function wa_telefone_exibicao(?string $telefone, ?string $e164 = null): string {
        $mostrar = wa_formatar_telefone($e164 ?? $telefone);
        return $mostrar !== '' ? $mostrar : strval($telefone ?? '');
    }
}
