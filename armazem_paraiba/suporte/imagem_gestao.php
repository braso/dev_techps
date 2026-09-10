<?php
    /* ============================================================
       Suporte — Proxy de Imagem (Gestão TechPS)
       Baixa imagens de chamados de QUALQUER empresa pela API.
       Só funciona no domínio TechPS.
       ============================================================ */
    include __DIR__ . "/../load_env.php";
    include_once __DIR__ . "/../conecta.php";
    include_once __DIR__ . "/../check_permission.php";

    $__empresaAtual = trim(strval($_ENV["CONTEX_PATH"] ?? ""), "/");
    // Gestão central: domínios TechPS (produção) e Demo (desenvolvimento).
    if (strpos($__empresaAtual, "techps") === false && strpos($__empresaAtual, "demo") === false) {
        http_response_code(403);
        exit;
    }

    $__id = (int) ($_GET["id"] ?? 0);
    $__arquivo = (int) ($_GET["arquivo"] ?? 0);
    if ($__id < 1 || $__arquivo < 1) {
        http_response_code(400);
        exit;
    }

    $__apiUrl   = rtrim(strval($_ENV["SUPORTE_API_URL"] ?? ""), "/");
    $__adminKey = strval($_ENV["SUPORTE_ADMIN_KEY"] ?? "");

    $__ch = curl_init($__apiUrl . "/suporte/tickets/{$__id}/arquivos/{$__arquivo}");
    curl_setopt_array($__ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ["x-api-key: " . $__adminKey],
    ]);
    $__bytes = curl_exec($__ch);
    $__httpCode = (int) curl_getinfo($__ch, CURLINFO_HTTP_CODE);
    $__mime = (string) curl_getinfo($__ch, CURLINFO_CONTENT_TYPE);
    curl_close($__ch);

    if ($__httpCode !== 200 || $__bytes === false) {
        http_response_code(404);
        exit;
    }

    header("Content-Type: " . ($__mime !== "" ? $__mime : "application/octet-stream"));
    header("Cache-Control: private, max-age=3600");
    echo $__bytes;
