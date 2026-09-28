<?php
/* ============================================================
   Configurações de E-mail (SMTP) da assinatura eletrônica.

   A SENHA NÃO FICA NESTE ARQUIVO (ele vai para o git). Ela é lida,
   nesta ordem:

   1) do .env do domínio, se tiver as chaves SMTP_*;
   2) do arquivo compartilhado smtp_credenciais.php na raiz da
      instalação (uma pasta acima das pastas dos clientes) — um único
      arquivo serve para TODOS os domínios.

   Modelo do arquivo compartilhado: smtp_credenciais-exemplo.php
   (copie para smtp_credenciais.php e preencha; ele é ignorado pelo git).
   ============================================================ */

include_once __DIR__ . "/../load_env.php";

// 2) arquivo compartilhado da raiz (não versionado): define $SMTP_CREDENCIAIS.
$SMTP_CREDENCIAIS = [];
$arquivoCredenciais = dirname(__DIR__, 2) . "/smtp_credenciais.php";
if (is_file($arquivoCredenciais)) {
    $lidas = include $arquivoCredenciais;
    if (is_array($lidas)) {
        $SMTP_CREDENCIAIS = $lidas;
    }
}

if (!function_exists("assinatura_email_config")) {
    function assinatura_email_config(string $chave, array $compartilhadas, string $padrao = ""): string {
        $valor = $_ENV[$chave] ?? getenv($chave);
        if (!is_string($valor) || trim($valor) === "") {
            $valor = $compartilhadas[$chave] ?? "";
        }
        $valor = is_string($valor) ? trim($valor) : "";
        return $valor !== "" ? $valor : $padrao;
    }
}

if (!defined("SMTP_HOST"))       define("SMTP_HOST",       assinatura_email_config("SMTP_HOST", $SMTP_CREDENCIAIS, "smtp.titan.email"));
if (!defined("SMTP_PORT"))       define("SMTP_PORT",       intval(assinatura_email_config("SMTP_PORT", $SMTP_CREDENCIAIS, "465")));
if (!defined("SMTP_SECURE"))     define("SMTP_SECURE",     assinatura_email_config("SMTP_SECURE", $SMTP_CREDENCIAIS, "ssl"));
if (!defined("SMTP_USER"))       define("SMTP_USER",       assinatura_email_config("SMTP_USER", $SMTP_CREDENCIAIS));
if (!defined("SMTP_PASS"))       define("SMTP_PASS",       assinatura_email_config("SMTP_PASS", $SMTP_CREDENCIAIS));
if (!defined("SMTP_FROM_EMAIL")) define("SMTP_FROM_EMAIL", assinatura_email_config("SMTP_FROM_EMAIL", $SMTP_CREDENCIAIS, SMTP_USER));
if (!defined("SMTP_FROM_NAME"))  define("SMTP_FROM_NAME",  assinatura_email_config("SMTP_FROM_NAME", $SMTP_CREDENCIAIS, "Tech PS"));

if (SMTP_USER === "" || SMTP_PASS === "") {
    error_log("[assinatura/email_config] Sem credenciais de SMTP para " . strval($_ENV["CONTEX_PATH"] ?? "?") . ": crie smtp_credenciais.php na raiz da instalação. Nenhum e-mail será enviado.");
}

// URL base do link de assinatura enviado por e-mail.
// Sem BASE_URL_ASSINATURA no .env, é montada com o domínio que está sendo acessado.
if (!defined("BASE_URL_ASSINATURA")) {
    $baseAssinatura = assinatura_email_config("BASE_URL_ASSINATURA", $SMTP_CREDENCIAIS);
    if ($baseAssinatura === "") {
        $raiz = rtrim(assinatura_email_config("URL_BASE", $SMTP_CREDENCIAIS), "/");
        if ($raiz !== "") {
            $baseAssinatura = $raiz
                . rtrim(assinatura_email_config("APP_PATH", $SMTP_CREDENCIAIS), "/")
                . rtrim(assinatura_email_config("CONTEX_PATH", $SMTP_CREDENCIAIS), "/")
                . "/assinatura";
        }
    }
    define("BASE_URL_ASSINATURA", rtrim($baseAssinatura, "/"));
}
