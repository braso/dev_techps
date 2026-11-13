<?php
/**
 * Execução de comandos de escrita (INSERT/UPDATE/DELETE).
 *
 * O query() global do sistema chama mysqli_stmt_get_result(), que retorna
 * false para comandos que não devolvem resultado. Por isso ele serve para
 * SELECT, mas não para checar o sucesso de INSERT/UPDATE/DELETE. Aqui a
 * execução é feita diretamente no statement.
 */

if (!function_exists('wa_exec')) {
    function wa_exec(string $sql, string $types = '', array $vars = []): bool {
        global $conn;
        if (!($conn instanceof mysqli)) {
            return false;
        }
        $stmt = @mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $GLOBALS['wa_last_sql_error'] = mysqli_error($conn);
            error_log('[whatsapp][sql] prepare: ' . mysqli_error($conn) . ' SQL: ' . substr(preg_replace('/\s+/', ' ', $sql), 0, 220));
            return false;
        }
        if ($types !== '' && !empty($vars)) {
            mysqli_stmt_bind_param($stmt, $types, ...$vars);
        }
        $ok = @mysqli_stmt_execute($stmt);
        if (!$ok) {
            $GLOBALS['wa_last_sql_error'] = mysqli_stmt_error($stmt);
            error_log('[whatsapp][sql] execute: ' . mysqli_stmt_error($stmt) . ' SQL: ' . substr(preg_replace('/\s+/', ' ', $sql), 0, 220));
        }
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('wa_insert_id')) {
    function wa_insert_id(): int {
        global $conn;
        return $conn instanceof mysqli ? (int)mysqli_insert_id($conn) : 0;
    }
}

if (!function_exists('wa_affected_rows')) {
    function wa_affected_rows(): int {
        global $conn;
        return $conn instanceof mysqli ? (int)mysqli_affected_rows($conn) : 0;
    }
}
