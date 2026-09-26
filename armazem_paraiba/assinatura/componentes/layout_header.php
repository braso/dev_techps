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

// Expira solicitações pendentes com prazo vencido (status -> 'expirado')
if (isset($conn) && ($conn instanceof mysqli)) {
    include_once __DIR__ . "/../tipo_assinatura_helper.php";
    if (function_exists('assinatura_expirarPendentes')) { assinatura_expirarPendentes($conn); }
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
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        /* Menu do sistema completo (ate 10 secoes): links mais compactos no header. */
        .navbar-techps nav .nav-link {
            padding: 0.5rem 0.6rem;
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
                        <div class="hidden md:block xl:hidden h-6 w-px bg-gray-300"></div>
                        <span class="hidden md:block xl:hidden text-lg font-bold text-gray-800 tracking-tight">Assinatura Digital</span>
                    </a>
                </div>

                <!-- Navigation (Desktop - System Menu) -->
                <nav class="hidden xl:flex items-center space-x-0.5">
                    <?php
                    // Mesmo menu do sistema: seções, permissões por perfil e regras de nível vêm
                    // de menu_estrutura.php, a mesma fonte do menu padrão. Antes era um array fixo
                    // copiado do menu.php, que parou no tempo e ignorava o perfil do usuário.
                    include_once __DIR__ . "/../../menu_estrutura.php";
                    $menuNos = menu_estrutura_do_nivel($_SESSION["user_tx_nivel"] ?? "");

                    $menuEsc = function($texto): string {
                        return htmlspecialchars(strval($texto), ENT_QUOTES, "UTF-8");
                    };
                    // "Validar Assinatura" não navega: abre as instruções do ITI (definidas no rodapé).
                    $menuAtributos = function(array $item) use ($baseContex, $menuEsc): string {
                        return !empty($item["iti"])
                            ? 'href="javascript:void(0);" onclick="abrirInstrucoesITI(); return false;"'
                            : 'href="' . $menuEsc($baseContex . $item["path"]) . '"';
                    };

                    foreach($menuNos as $no) {
                        if($no["tipo"] === "link"){
                            echo '<a '.$menuAtributos($no).' class="nav-link">'.$menuEsc($no["label"]).'</a>';
                            continue;
                        }
                        // Seção sem nenhuma página acessível não vira um dropdown vazio.
                        if(empty($no["itens"])){
                            continue;
                        }
                        echo '
                        <div class="relative group">
                            <button class="nav-link flex items-center h-full">
                                <span>'.$menuEsc($no["titulo"]).'</span>
                                <i class="fas fa-chevron-down ml-1 text-xs text-gray-400"></i>
                            </button>
                            <div class="absolute left-0 top-full pt-2 w-56 hidden group-hover:block z-50">
                                <div class="bg-white border border-gray-200 rounded-md shadow-lg max-h-[80vh] overflow-y-auto">
                                    <div class="py-1">';
                                    foreach($no["itens"] as $item) {
                                        echo '<a '.$menuAtributos($item).' class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-blue-600 transition-colors">'.$menuEsc($item["label"]).'</a>';
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
                        <div class="hidden sm:block xl:hidden 2xl:block text-sm">
                            <p class="font-medium text-gray-700 leading-none"><?php echo $user_login; ?></p>
                            <p class="text-xs text-gray-500 mt-0.5">Usuário Sistema</p>
                        </div>
                    </div>

                    <!-- Logout Button -->
                    <a href="<?php echo $baseContex; ?>/logout.php" class="p-2 text-gray-400 hover:text-red-600 transition-colors" title="Sair do Sistema">
                        <i class="fas fa-sign-out-alt text-xl"></i>
                    </a>
                    
                    <!-- Mobile Menu Button -->
                    <button type="button" id="mobile-menu-btn" class="xl:hidden p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500">
                        <span class="sr-only">Open menu</span>
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile Navigation (Hidden by default) -->
        <div class="xl:hidden hidden border-t border-gray-200 bg-gray-50" id="mobile-menu">
            <div class="px-2 pt-2 pb-3 space-y-2">
                <?php
                    $secIndex = 0;
                    foreach($menuNos as $no) {
                        if($no["tipo"] === "link"){
                            echo '<a '.$menuAtributos($no).' class="block bg-white border border-gray-200 rounded-md px-3 py-3 text-sm font-semibold text-gray-800">'.$menuEsc($no["label"]).'</a>';
                            continue;
                        }
                        if(empty($no["itens"])){
                            continue;
                        }
                        $secIndex++;
                        $secId = "mobile-sec-" . $secIndex;
                        echo '
                            <div class="bg-white border border-gray-200 rounded-md overflow-hidden">
                                <button type="button" class="w-full flex items-center justify-between px-3 py-3 text-left text-sm font-semibold text-gray-800" data-mobile-toggle="'.$secId.'">
                                    <span>'.$menuEsc($no["titulo"]).'</span>
                                    <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                                </button>
                                <div id="'.$secId.'" class="hidden border-t border-gray-200 bg-gray-50">
                                    <div class="py-1">';
                                        foreach($no["itens"] as $item) {
                                            echo '<a '.$menuAtributos($item).' class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-blue-600 transition-colors">'.$menuEsc($item["label"]).'</a>';
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
