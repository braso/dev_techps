<?php
/**
 * Tipo de assinatura eletrônica configurado no cadastro da empresa
 * (empresa.empr_tx_tipoAssinatura): cpf_rg | rubrica | ambos
 *
 * Usado por assinar_via_link.php (monta a tela) e assinar.php (valida e grava).
 */

if (!function_exists('assinatura_garantirColunasRubrica')) {
    function assinatura_garantirColunasRubrica($conn): void {
        if (!isset($conn) || !($conn instanceof mysqli)) return;
        $r = @mysqli_query($conn, "SHOW COLUMNS FROM empresa LIKE 'empr_tx_tipoAssinatura'");
        if ($r && mysqli_num_rows($r) === 0) {
            @mysqli_query($conn, "ALTER TABLE empresa ADD COLUMN empr_tx_tipoAssinatura ENUM('cpf_rg','rubrica','ambos') NOT NULL DEFAULT 'cpf_rg'");
        }
        $r = @mysqli_query($conn, "SHOW COLUMNS FROM assinantes LIKE 'rubrica_path'");
        if ($r && mysqli_num_rows($r) === 0) {
            @mysqli_query($conn, "ALTER TABLE assinantes ADD COLUMN rubrica_path VARCHAR(255) NULL DEFAULT NULL");
        }
    }
}

if (!function_exists('assinatura_normalizarTipo')) {
    function assinatura_normalizarTipo($tipo): string {
        $t = strtolower(trim(strval($tipo)));
        return in_array($t, ['cpf_rg', 'rubrica', 'ambos'], true) ? $t : 'cpf_rg';
    }
}

if (!function_exists('assinatura_obterTipoAssinatura')) {
    /**
     * Resolve a empresa do signatário e devolve o tipo de assinatura exigido.
     * Ordem: entidade (enti_nb_empresa) > signatário externo (sign_nb_empresa) > solicitação (empresa_id) > padrão cpf_rg.
     */
    function assinatura_obterTipoAssinatura($conn, int $entiId = 0, string $email = '', int $empresaIdSolicitacao = 0): string {
        if (!isset($conn) || !($conn instanceof mysqli)) return 'cpf_rg';
        assinatura_garantirColunasRubrica($conn);

        $empresaId = 0;
        if ($entiId > 0) {
            $st = mysqli_prepare($conn, "SELECT enti_nb_empresa FROM entidade WHERE enti_nb_id = ? LIMIT 1");
            if ($st) {
                mysqli_stmt_bind_param($st, "i", $entiId);
                mysqli_stmt_execute($st);
                $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
                mysqli_stmt_close($st);
                $empresaId = intval($row["enti_nb_empresa"] ?? 0);
            }
        }
        if ($empresaId <= 0 && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $em = strtolower(trim($email));
            $st = mysqli_prepare($conn, "SELECT enti_nb_empresa FROM entidade WHERE LOWER(TRIM(enti_tx_email)) = ? LIMIT 1");
            if ($st) {
                mysqli_stmt_bind_param($st, "s", $em);
                mysqli_stmt_execute($st);
                $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
                mysqli_stmt_close($st);
                $empresaId = intval($row["enti_nb_empresa"] ?? 0);
            }
            if ($empresaId <= 0) {
                $chk = @mysqli_query($conn, "SHOW COLUMNS FROM signatarios_externos LIKE 'sign_nb_empresa'");
                if ($chk && mysqli_num_rows($chk) > 0) {
                    $st = mysqli_prepare($conn, "SELECT sign_nb_empresa FROM signatarios_externos WHERE LOWER(TRIM(sign_tx_email)) = ? LIMIT 1");
                    if ($st) {
                        mysqli_stmt_bind_param($st, "s", $em);
                        mysqli_stmt_execute($st);
                        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
                        mysqli_stmt_close($st);
                        $empresaId = intval($row["sign_nb_empresa"] ?? 0);
                    }
                }
            }
        }
        if ($empresaId <= 0 && $empresaIdSolicitacao > 0) {
            $empresaId = $empresaIdSolicitacao;
        }
        if ($empresaId <= 0) {
            // Sem empresa identificada: usa a matriz, se houver
            $r = @mysqli_query($conn, "SELECT empr_nb_id FROM empresa WHERE empr_tx_Ehmatriz = 'sim' ORDER BY empr_nb_id LIMIT 1");
            $row = $r ? mysqli_fetch_assoc($r) : null;
            $empresaId = intval($row["empr_nb_id"] ?? 0);
        }
        if ($empresaId <= 0) return 'cpf_rg';

        $st = mysqli_prepare($conn, "SELECT empr_tx_tipoAssinatura FROM empresa WHERE empr_nb_id = ? LIMIT 1");
        if (!$st) return 'cpf_rg';
        mysqli_stmt_bind_param($st, "i", $empresaId);
        mysqli_stmt_execute($st);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
        return assinatura_normalizarTipo($row["empr_tx_tipoAssinatura"] ?? 'cpf_rg');
    }
}

if (!function_exists('assinatura_tipoExigeCpfRg')) {
    function assinatura_tipoExigeCpfRg(string $tipo): bool { return $tipo === 'cpf_rg' || $tipo === 'ambos'; }
    function assinatura_tipoExigeRubrica(string $tipo): bool { return $tipo === 'rubrica' || $tipo === 'ambos'; }
    function assinatura_tipoDescricao(string $tipo): string {
        return ['cpf_rg' => 'CPF e RG', 'rubrica' => 'Rubrica', 'ambos' => 'CPF, RG e Rubrica'][$tipo] ?? 'CPF e RG';
    }
}

if (!function_exists('assinatura_expirarPendentes')) {
    /**
     * Marca como 'expirado' as solicitações pendentes/em progresso cujo prazo (expires_at, UTC) já passou.
     * Chamada nas telas do módulo, no link de assinatura e no webservice do app.
     */
    function assinatura_garantirStatusExpirado($conn): void {
        // A coluna status pode ser ENUM: garante que aceita 'expirado'
        try {
            $r = @mysqli_query($conn, "SHOW COLUMNS FROM solicitacoes_assinatura LIKE 'status'");
            $col = $r ? mysqli_fetch_assoc($r) : null;
            $type = strval($col["Type"] ?? "");
            if (stripos($type, "enum(") === 0 && stripos($type, "'expirado'") === false) {
                $novo = substr($type, 0, -1) . ",'expirado')";
                $null = (strtoupper(strval($col["Null"] ?? "")) === "YES") ? "NULL" : "NOT NULL";
                $def = isset($col["Default"]) && $col["Default"] !== null ? " DEFAULT '" . mysqli_real_escape_string($conn, strval($col["Default"])) . "'" : "";
                @mysqli_query($conn, "ALTER TABLE solicitacoes_assinatura MODIFY COLUMN status {$novo} {$null}{$def}");
            }
        } catch (Throwable $e) {}
    }

    function assinatura_expirarPendentes($conn): int {
        if (!isset($conn) || !($conn instanceof mysqli)) return 0;
        try {
            $chk = @mysqli_query($conn, "SHOW COLUMNS FROM solicitacoes_assinatura LIKE 'expires_at'");
            if (!$chk || mysqli_num_rows($chk) === 0) return 0;
            assinatura_garantirStatusExpirado($conn);
            $ok = @mysqli_query($conn,
                "UPDATE solicitacoes_assinatura
                 SET status = 'expirado'
                 WHERE LOWER(TRIM(status)) IN ('pendente','em_progresso')
                   AND expires_at IS NOT NULL
                   AND expires_at <> '0000-00-00 00:00:00'
                   AND expires_at < UTC_TIMESTAMP()");
            return $ok ? intval(mysqli_affected_rows($conn)) : 0;
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('assinatura_salvarRubricaBase64')) {
    /**
     * Grava a rubrica (data URL PNG do canvas) em assinatura/rubricas/ e devolve o caminho relativo, ou '' se inválida.
     */
    function assinatura_salvarRubricaBase64(string $dataUrl, string $protocolo): string {
        $dataUrl = trim($dataUrl);
        if ($dataUrl === '' || !preg_match('#^data:image/png;base64,#i', $dataUrl)) return '';
        $bin = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
        if ($bin === false || strlen($bin) < 100 || strlen($bin) > 2 * 1024 * 1024) return '';
        if (substr($bin, 0, 8) !== "\x89PNG\r\n\x1a\n") return '';
        $dir = __DIR__ . "/rubricas/";
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $protocolo);
        $file = "rubrica_" . time() . "_" . ($safe !== '' ? $safe : bin2hex(random_bytes(6))) . ".png";
        if (@file_put_contents($dir . $file, $bin) === false) return '';
        return "rubricas/" . $file;
    }
}
