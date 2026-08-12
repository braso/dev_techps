<?php
    /* ============================================================
       Suporte — Proxy de Imagem
       Baixa a imagem do chamado pela API (nunca expõe a admin key
       ao navegador).
       ============================================================ */
    include __DIR__ . "/../load_env.php";
    include_once __DIR__ . "/../conecta.php";
    include_once __DIR__ . "/../check_permission.php";

    $__id = (int) ($_GET["id"] ?? 0);
    $__arquivo = (int) ($_GET["arquivo"] ?? 0);
    if ($__id < 1 || $__arquivo < 1) {
        http_response_code(400);
        exit;
    }

    $__apiUrl   = rtrim(strval($_ENV["SUPORTE_API_URL"] ?? ""), "/");
    $__adminKey = strval($_ENV["SUPORTE_ADMIN_KEY"] ?? "");
    $__empresaAtual = trim(strval($_ENV["CONTEX_PATH"] ?? ""), "/");

    // Isolamento por tenant: primeiro confirma que o chamado é da empresa atual.
    $__chDet = curl_init($__apiUrl . "/suporte/tickets/" . $__id);
    curl_setopt_array($__chDet, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ["x-api-key: " . $__adminKey],
    ]);
    $__det = json_decode((string) curl_exec($__chDet), true);
    curl_close($__chDet);
    if (!is_array($__det) || strval($__det["ticket"]["empresa_key"] ?? "") !== $__empresaAtual) {
        http_response_code(404);
        exit;
    }

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
