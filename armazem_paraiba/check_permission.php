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

        $rowPerfil = mysqli_fetch_assoc(query($sqlPerfil, "i", [$_SESSION["user_nb_id"]]));

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

        $rowPerm = mysqli_fetch_assoc(query($sqlPerm, "is", [$perfilId, $pathMenu]));

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
        // Se for funcionário, deixa livre apenas batida/espelho
        if (preg_match('/(funcionário|motorista|ajudante)/i', $nivel) && in_array($pathMenu, $pathsPermitidosFuncionario)) {
            return true; // permitido por regra especial
        }

        // Se não tiver permissão, redireciona
        $_POST["returnValues"] = json_encode([
            "HTTP_REFERER" => $_ENV["APP_PATH"] . $_ENV["CONTEX_PATH"] . "/batida_ponto.php"
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
        $rowPerfil = mysqli_fetch_assoc(query(
            "SELECT perfil_nb_id FROM usuario_perfil WHERE ativo = 1 AND user_nb_id = ? LIMIT 1",
            "i",
            [$_SESSION["user_nb_id"]]
        ));
        if (!empty($rowPerfil["perfil_nb_id"])) { $perfilId = (int)$rowPerfil["perfil_nb_id"]; }
    }
    if ($perfilId <= 0) { return false; }
    $rowPerm = mysqli_fetch_assoc(query(
        "SELECT 1 FROM perfil_menu_item p JOIN menu_item m ON m.menu_nb_id = p.menu_nb_id WHERE p.perfil_nb_id = ? AND p.perm_ver = 1 AND m.menu_tx_ativo = 1 AND m.menu_tx_path = ? LIMIT 1",
        "is",
        [$perfilId, $pathMenu]
    ));
    return !empty($rowPerm);
}


function camposOcultosPerfil($pathMenu)
{
    global $conn;
    $perfilId = 0;
    if (!empty($_SESSION["user_nb_id"])) {
        $rowPerfil = mysqli_fetch_assoc(query(
            "SELECT perfil_nb_id FROM usuario_perfil WHERE ativo = 1 AND user_nb_id = ? LIMIT 1",
            "i",
            [$_SESSION["user_nb_id"]]
        ));
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
