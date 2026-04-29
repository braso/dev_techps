<?php
/**
 * index.php — Raiz da pasta do cliente
 * 
 * Coloque este arquivo em cada pasta de cliente:
 *   /gestaodeponto/braso/index.php
 *   /gestaodeponto/t_militao/index.php
 *   etc.
 * 
 * Ele detecta automaticamente qual cliente é pela pasta
 * e redireciona para o login com a empresa pré-selecionada.
 */

include_once __DIR__ . "/armazem_paraiba/load_env.php";

// Detecta o slug do cliente pelo nome da pasta atual
// Ex: /gestaodeponto/braso → "braso"
$slug = strtolower(basename(__DIR__));

// Carrega o mapa de empresas para encontrar a chave correta
// Ex: "braso" → "BRASO"
include_once __DIR__ . "/../empresas.php";

$empresaKey = "";
foreach ($empresas as $key => $value) {
    if (strtolower($value) === $slug) {
        $empresaKey = $key;
        break;
    }
}

$urlBase = rtrim($_ENV["URL_BASE"] ?? "", "/");
$appPath = rtrim($_ENV["APP_PATH"] ?? "", "/");

if ($empresaKey !== "") {
    // Redireciona para o login raiz com empresa pré-selecionada
    $loginUrl = $urlBase . dirname($appPath) . "/index.php?empresa=" . urlencode($empresaKey);
} else {
    // Fallback: vai para o login raiz sem pré-seleção
    $loginUrl = $urlBase . dirname($appPath) . "/index.php";
}

header("Location: " . $loginUrl);
exit;
