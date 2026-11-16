<?php
/**
 * Acesso ao banco do módulo, com prepared statements.
 * Evita depender do dispatcher legado do contex20.
 */

if (!function_exists('ia_db_query')) {
    function ia_db_query(string $sql, string $types = '', array $vars = []) {
        global $conn;
        if (!($conn instanceof mysqli)) {
            return false;
        }
        if ($types === '' || empty($vars)) {
            $rs = @mysqli_query($conn, $sql);
            if ($rs === false) {
                error_log('[assistente][sql] ' . mysqli_error($conn) . ' SQL: ' . substr($sql, 0, 220));
            }
            return $rs;
        }
        $stmt = @mysqli_prepare($conn, $sql);
        if (!$stmt) {
            error_log('[assistente][sql] prepare: ' . mysqli_error($conn) . ' SQL: ' . substr($sql, 0, 220));
            return false;
        }
        mysqli_stmt_bind_param($stmt, $types, ...$vars);
        if (!mysqli_stmt_execute($stmt)) {
            error_log('[assistente][sql] execute: ' . mysqli_stmt_error($stmt) . ' SQL: ' . substr($sql, 0, 220));
            return false;
        }
        $resultado = mysqli_stmt_get_result($stmt);
        if ($resultado === false) {
            return $stmt; // INSERT/UPDATE/DELETE: devolve o statement para insert_id/affected_rows
        }
        return $resultado;
    }
}

if (!function_exists('ia_db_all')) {
    function ia_db_all(string $sql, string $types = '', array $vars = []): array {
        $rs = ia_db_query($sql, $types, $vars);
        $out = [];
        if ($rs instanceof mysqli_result) {
            while ($row = mysqli_fetch_assoc($rs)) {
                $out[] = $row;
            }
        }
        return $out;
    }
}

if (!function_exists('ia_db_one')) {
    function ia_db_one(string $sql, string $types = '', array $vars = []): ?array {
        $rs = ia_db_query($sql, $types, $vars);
        if ($rs instanceof mysqli_result) {
            $row = mysqli_fetch_assoc($rs);
            return $row ?: null;
        }
        return null;
    }
}

if (!function_exists('ia_db_val')) {
    function ia_db_val(string $sql, string $types = '', array $vars = []) {
        $row = ia_db_one($sql, $types, $vars);
        if (!$row) {
            return null;
        }
        return array_values($row)[0] ?? null;
    }
}

if (!function_exists('ia_db_insert_id')) {
    function ia_db_insert_id($stmt): int {
        global $conn;
        if ($stmt instanceof mysqli_stmt) {
            return (int)mysqli_stmt_insert_id($stmt);
        }
        return (int)mysqli_insert_id($conn);
    }
}
