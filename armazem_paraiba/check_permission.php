<?php
function verificaPermissao($pathMenu)
{
    global $conn;

    $permitido = false;
    $perfilId = 0;

    // 1. Verifica se o usuário tem perfil ativo
    if (!empty($_SESSION["user_nb_id"])) {
        $sqlPerfil = "SELECT perfil_nb_id 
                      FROM usuario_perfil 
                      WHERE ativo = 1 
                        AND user_nb_id = ? 
                      LIMIT 1";

        $rsPerfil = query($sqlPerfil, "i", [$_SESSION["user_nb_id"]]);
        $rowPerfil = $rsPerfil ? mysqli_fetch_assoc($rsPerfil) : null;

        if (!empty($rowPerfil["perfil_nb_id"])) {
            $perfilId = (int)$rowPerfil["perfil_nb_id"];
        }
    }

    // 2. Verifica permissão no menu
    if ($perfilId > 0) {
        $sqlPerm = "SELECT 1
                    FROM perfil_menu_item p
                      JOIN menu_item m ON m.menu_nb_id = p.menu_nb_id
                    WHERE p.perfil_nb_id = ?
                      AND p.perm_ver = 1
                      AND m.menu_tx_ativo = 1
                      AND m.menu_tx_path = ?
                    LIMIT 1";

        $rsPerm = query($sqlPerm, "is", [$perfilId, $pathMenu]);
        $rowPerm = $rsPerm ? mysqli_fetch_assoc($rsPerm) : null;

        $permitido = !empty($rowPerm);
    }

    // 3. Regras por nível de acesso para funcionário
    $nivel = trim($_SESSION["user_tx_nivel"] ?? "");
    $isAdmin = (
        preg_match('/administrador/i', $nivel) ||
        preg_match('/super\s+admin/i', $nivel) ||
        preg_match('/adminsitrador/i', $nivel)
    );

    $pathsPermitidosFuncionario = ['/batida_ponto.php', '/espelho_ponto.php'];

    if (!$isAdmin && !$permitido) {
        // Regra especial para operação: esses níveis acessam batida/espelho mesmo sem
        // item explícito no perfil — mas SÓ quando não têm perfil nenhum configurado.
        // Se existe um perfil e ele não libera a batida, quem manda é o perfil.
        if ($perfilId <= 0 && preg_match('/(funcionário|motorista|ajudante|terceirizado)/i', $nivel) && in_array($pathMenu, $pathsPermitidosFuncionario)) {
            return true; // permitido por regra especial
        }

        // Sem permissão: vai para a tela inicial que a pessoa pode ver, e nunca para
        // uma tela que também é bloqueada para ela (antes caía sempre na batida).
        $_POST["returnValues"] = json_encode([
            "HTTP_REFERER" => $_ENV["APP_PATH"] . $_ENV["CONTEX_PATH"] . paginaInicialPermitida()
        ]);
        voltar();
        exit;
    }

    return true; // Se for admin ou tiver permissão marcada
}

function temPermissaoMenu($pathMenu)
{
    global $conn;
    $perfilId = 0;
    if (!empty($_SESSION["user_nb_id"])) {
        $rsPerfil = query(
            "SELECT perfil_nb_id FROM usuario_perfil WHERE ativo = 1 AND user_nb_id = ? LIMIT 1",
            "i",
            [$_SESSION["user_nb_id"]]
        );
        $rowPerfil = $rsPerfil ? mysqli_fetch_assoc($rsPerfil) : null;
        if (!empty($rowPerfil["perfil_nb_id"])) { $perfilId = (int)$rowPerfil["perfil_nb_id"]; }
    }
    if ($perfilId <= 0) { return false; }
    $rsPerm = query(
        "SELECT 1 FROM perfil_menu_item p JOIN menu_item m ON m.menu_nb_id = p.menu_nb_id WHERE p.perfil_nb_id = ? AND p.perm_ver = 1 AND m.menu_tx_ativo = 1 AND m.menu_tx_path = ? LIMIT 1",
        "is",
        [$perfilId, $pathMenu]
    );
    $rowPerm = $rsPerm ? mysqli_fetch_assoc($rsPerm) : null;
    return !empty($rowPerm);
}


/**
 * Perfil ativo do usuário logado (0 = sem perfil configurado).
 */
function perfilAtivoDoUsuario(): int
{
    if (empty($_SESSION["user_nb_id"])) { return 0; }
    $rsPerfil = query(
        "SELECT perfil_nb_id FROM usuario_perfil WHERE ativo = 1 AND user_nb_id = ? LIMIT 1",
        "i",
        [$_SESSION["user_nb_id"]]
    );
    $rowPerfil = $rsPerfil ? mysqli_fetch_assoc($rsPerfil) : null;
    return !empty($rowPerfil["perfil_nb_id"]) ? (int)$rowPerfil["perfil_nb_id"] : 0;
}

/**
 * Tela inicial que o usuário logado realmente pode abrir:
 * batida de ponto para quem bate ponto, boas-vindas para quem não bate.
 * Devolve o caminho relativo ao domínio (ex.: "/batida_ponto.php").
 */
function paginaInicialPermitida(): string
{
    $nivel = trim($_SESSION["user_tx_nivel"] ?? "");
    $ehOperacional = (bool) preg_match('/(funcionário|motorista|ajudante|terceirizado)/i', $nivel);

    if (function_exists('temPermissaoMenu') && temPermissaoMenu('/batida_ponto.php')) {
        return "/batida_ponto.php";
    }

    // Sem perfil configurado, os níveis operacionais continuam caindo na batida.
    if (perfilAtivoDoUsuario() <= 0 && $ehOperacional) {
        return "/batida_ponto.php";
    }

    return "/bem_vindo.php";
}

function camposOcultosPerfil($pathMenu)
{
    global $conn;
    $perfilId = 0;
    if (!empty($_SESSION["user_nb_id"])) {
        $rsPerfil = query(
            "SELECT perfil_nb_id FROM usuario_perfil WHERE ativo = 1 AND user_nb_id = ? LIMIT 1",
            "i",
            [$_SESSION["user_nb_id"]]
        );
        $rowPerfil = $rsPerfil ? mysqli_fetch_assoc($rsPerfil) : null;
        if (!empty($rowPerfil["perfil_nb_id"])) { $perfilId = (int)$rowPerfil["perfil_nb_id"]; }
    }
    if ($perfilId <= 0) { return []; }
    $rs = query(
        "SELECT p.campo_tx_nome FROM perfil_menu_campo p JOIN menu_item m ON m.menu_nb_id = p.menu_nb_id WHERE p.perfil_nb_id = ? AND m.menu_tx_path = ?",
        "is",
        [$perfilId, $pathMenu]
    );
    $out = [];
    while($rs && ($r = mysqli_fetch_assoc($rs))){ $out[] = $r["campo_tx_nome"]; }
    return $out;
}
