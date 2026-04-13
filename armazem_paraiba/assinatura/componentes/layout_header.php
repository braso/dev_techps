<?php
if(!isset($_SESSION)) {
    session_start();
}
$urlBase = $_ENV["URL_BASE"] ?? (($_SERVER["REQUEST_SCHEME"] ?? "http") . "://" . ($_SERVER["HTTP_HOST"] ?? "localhost"));
$hasEnvPaths = isset($_ENV["APP_PATH"], $_ENV["CONTEX_PATH"]);
if($hasEnvPaths){
    $baseContex = rtrim(($urlBase ?? "") . ($_ENV["APP_PATH"] ?? "") . ($_ENV["CONTEX_PATH"] ?? ""), "/");
    $baseAssinatura = $baseContex . "/assinatura";
} else {
    $scriptName = strval($_SERVER["SCRIPT_NAME"] ?? "");
    $assinaturaDir = rtrim(str_replace("\\", "/", dirname($scriptName)), "/");
    if($assinaturaDir === "" || $assinaturaDir === "."){
        $assinaturaDir = "/assinatura";
    }
    $baseAssinatura = rtrim($urlBase, "/") . $assinaturaDir;
    $baseContex = rtrim($urlBase, "/") . rtrim(dirname($assinaturaDir), "/");
}

$empresaTitulo = trim(strval($_SESSION["empr_tx_nome"] ?? ""));
if($empresaTitulo === "" && isset($conn) && ($conn instanceof mysqli)){
    $resEmp = @mysqli_query($conn, "SELECT empr_tx_nome FROM empresa LIMIT 1");
    if($resEmp){
        $rowEmp = mysqli_fetch_assoc($resEmp);
        $empresaTitulo = trim(strval($rowEmp["empr_tx_nome"] ?? ""));
        if($empresaTitulo !== ""){
            $_SESSION["empr_tx_nome"] = $empresaTitulo;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $empresaTitulo !== "" ? ("TechPS | " . htmlspecialchars($empresaTitulo, ENT_QUOTES, "UTF-8")) : "TechPS"; ?></title>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Custom Style -->
    <link rel="stylesheet" href="<?php echo $baseAssinatura; ?>/style.css">
    <style>
        /* Navbar custom overrides */
        .navbar-techps {
            background-color: #fff;
            border-bottom: 2px solid #e2e8f0; /* Borda mais visível */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); /* Sombra mais pronunciada (shadow-md) */
        }
        .nav-link {
            color: #4b5563;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.375rem;
            transition: all 0.2s;
        }
        .nav-link:hover {
            color: #1f2937;
            background-color: #f3f4f6;
        }
        .nav-link.active {
            color: #2563eb;
            background-color: #eff6ff;
        }
        /* Fix container width */
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
    </style>
</head>
<body class="bg-gray-50 flex flex-col min-h-screen">
    <header class="navbar-techps sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <!-- Logo & Brand -->
                <div class="flex items-center gap-4">
                    <a href="<?php echo $baseContex; ?>/index.php" class="flex-shrink-0 flex items-center gap-3">
                        <img class="h-8 w-auto object-contain" src="<?php echo $baseAssinatura; ?>/assets/logo.png" alt="TechPS Logo">
                        <div class="hidden md:block h-6 w-px bg-gray-300"></div>
                        <span class="hidden md:block text-lg font-bold text-gray-800 tracking-tight">Assinatura Digital</span>
                    </a>
                </div>

                <!-- Navigation (Desktop - System Menu) -->
                <nav class="hidden md:flex space-x-2">
                    <?php
                    // Definição dos Menus do Sistema (Baseado em menu.php)
                    $menu_sistema = [
                        "Cadastros" => [
                            "RFID" => $baseContex."/cadastro_rfid.php",
                            "Celular" => $baseContex."/cadastro_celular.php",
                            "Empresa/Filial" => $baseContex."/cadastro_empresa.php",
                            "Endosso" => $baseContex."/cadastro_endosso.php",
                            "Feriado" => $baseContex."/cadastro_feriado.php",
                            "Férias" => $baseContex."/cadastro_ferias.php",
                            "Funcionário" => $baseContex."/cadastro_funcionario.php",
                            "Abono" => $baseContex."/cadastro_abono.php",
                            "Macro" => $baseContex."/cadastro_macro.php",
                            "Motivo" => $baseContex."/cadastro_motivo.php",
                            "Cargo" => $baseContex."/cadastro_operacao.php",
                            "Parâmetro" => $baseContex."/cadastro_parametro.php",
                            "Placas" => $baseContex."/cadastro_placa.php",
                            "Setor" => $baseContex."/cadastro_setor.php",
                            "Tipo de Documento" => $baseContex."/cadastro_tipo_doc.php",
                            "Usuário" => $baseContex."/cadastro_usuario.php",
                            "Habilidades Técnicas" => $baseContex."/cadastro_habilidade_tecnica.php",
                            "Habilidades Comportamentais" => $baseContex."/cadastro_habilidade_comportamental.php",
                            "Perfil de Acesso" => $baseContex."/cadastro_perfil_acesso.php",
                            "Permissões de Usuários" => $baseContex."/cadastro_usuario_perfil.php"
                        ],
                        "Ponto" => [
                            "Registrar Ponto" => $baseContex."/batida_ponto.php",
                            "Consultar Endossos" => $baseContex."/endosso.php",
                            "Espelhos de Ponto" => $baseContex."/espelho_ponto.php",
                            "Integrações de Ponto" => $baseContex."/carregar_ponto.php",
                            "Não Cadastrados" => $baseContex."/nao_cadastrados.php",
                            "Não Conformidades" => $baseContex."/nao_conformidade.php",
                            "Auditoria" => $baseContex."/ponto_auditoria.php"
                        ],
                        "Painel" => [
                            "Ajustes" => $baseContex."/paineis/ajustes.php",
                            "Disponibilidade" => $baseContex."/paineis/disponibilidade.php",
                            "Endosso" => $baseContex."/paineis/endosso.php",
                            "Jornada Aberta" => $baseContex."/paineis/jornada.php",
                            "Não Conformidades Jurídicas" => $baseContex."/paineis/nc_juridica.php",
                            "Saldo" => $baseContex."/paineis/saldo.php",
                            "Escalas" => $baseContex."/paineis/escala_parametro.php"
                        ],
                        "Relatórios" => [
                            "Pontos" => $baseContex."/relatorio_pontos.php"
                        ],
                        "Assinatura Digital" => [
                            "Dashboard" => $baseAssinatura."/index.php",
                            "Nova Assinatura" => $baseAssinatura."/nova_assinatura.php",
                            "Assinatura com Governança" => $baseAssinatura."/governanca.php",
                            "Documentos" => $baseAssinatura."/documentos.php",
                            "Consultar" => $baseAssinatura."/consultar.php",
                           
                           // "Finalizar (ICP)" => $baseAssinatura."/finalizar.php"
                        ]
                    ];

                    foreach($menu_sistema as $categoria => $itens) {
                        echo '
                        <div class="relative group">
                            <button class="nav-link flex items-center h-full">
                                <span>'.$categoria.'</span>
                                <i class="fas fa-chevron-down ml-1 text-xs text-gray-400"></i>
                            </button>
                            <div class="absolute left-0 top-full pt-2 w-56 hidden group-hover:block z-50">
                                <div class="bg-white border border-gray-200 rounded-md shadow-lg max-h-[80vh] overflow-y-auto">
                                    <div class="py-1">';
                                    foreach($itens as $nome => $link) {
                                        echo '<a href="'.$link.'" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-blue-600 transition-colors">'.$nome.'</a>';
                                    }
                        echo '          </div>
                                </div>
                            </div>
                        </div>';
                    }
                    ?>
                </nav>


                <!-- User Profile -->
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-3 px-3 py-1.5 rounded-full bg-gray-50 border border-gray-200">
                        <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold">
                            <?php 
                                $user_login = isset($_SESSION['user_tx_login']) ? $_SESSION['user_tx_login'] : 'U';
                                echo strtoupper(substr($user_login, 0, 1));
                            ?>
                        </div>
                        <div class="hidden sm:block text-sm">
                            <p class="font-medium text-gray-700 leading-none"><?php echo $user_login; ?></p>
                            <p class="text-xs text-gray-500 mt-0.5">Usuário Sistema</p>
                        </div>
                    </div>

                    <!-- Logout Button -->
                    <a href="<?php echo $baseContex; ?>/logout.php" class="p-2 text-gray-400 hover:text-red-600 transition-colors" title="Sair do Sistema">
                        <i class="fas fa-sign-out-alt text-xl"></i>
                    </a>
                    
                    <!-- Mobile Menu Button -->
                    <button class="md:hidden p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500">
                        <span class="sr-only">Open menu</span>
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile Navigation (Hidden by default) -->
        <div class="md:hidden hidden border-t border-gray-200 bg-gray-50" id="mobile-menu">
            <div class="px-2 pt-2 pb-3 space-y-2">
                <?php
                    $secIndex = 0;
                    foreach($menu_sistema as $categoria => $itens) {
                        $secIndex++;
                        $secId = "mobile-sec-" . $secIndex;
                        echo '
                            <div class="bg-white border border-gray-200 rounded-md overflow-hidden">
                                <button type="button" class="w-full flex items-center justify-between px-3 py-3 text-left text-sm font-semibold text-gray-800" data-mobile-toggle="'.$secId.'">
                                    <span>'.htmlspecialchars($categoria, ENT_QUOTES, "UTF-8").'</span>
                                    <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                                </button>
                                <div id="'.$secId.'" class="hidden border-t border-gray-200 bg-gray-50">
                                    <div class="py-1">';
                                        foreach($itens as $nome => $link) {
                                            echo '<a href="'.$link.'" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-blue-600 transition-colors">'.htmlspecialchars($nome, ENT_QUOTES, "UTF-8").'</a>';
                                        }
                        echo '      </div>
                                </div>
                            </div>';
                    }
                ?>
            </div>
        </div>
    </header>

    <main class="flex-grow py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
