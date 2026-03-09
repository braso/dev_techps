<?php
if(!isset($_SESSION)) {
    session_start();
}
// Determine path prefix for assets and links
// If style.css exists in current directory, we are in root.
// Otherwise we assume we are one level deep (e.g. dadosbases/).
$path_prefix = file_exists('style.css') ? '' : '../';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assinatura Digital - TechPS</title>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Custom Style -->
    <link rel="stylesheet" href="<?php echo $path_prefix; ?>style.css">
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
                    <a href="<?php echo $path_prefix; ?>index.php" class="flex-shrink-0 flex items-center gap-3">
                        <img class="h-8 w-auto object-contain" src="<?php echo $path_prefix; ?>assets/logo.png" alt="TechPS Logo">
                        <div class="hidden md:block h-6 w-px bg-gray-300"></div>
                        <span class="text-lg font-bold text-gray-800 tracking-tight">Assinatura Digital</span>
                    </a>
                </div>

                <!-- Navigation (Desktop - System Menu) -->
                <nav class="hidden md:flex space-x-2">
                    <?php
                    // Definição dos Menus do Sistema (Baseado em menu.php)
                    $menu_sistema = [
                        "Cadastros" => [
                            "RFID" => "../cadastro_rfid.php",
                            "Celular" => "../cadastro_celular.php",
                            "Empresa/Filial" => "../cadastro_empresa.php",
                            "Endosso" => "../cadastro_endosso.php",
                            "Feriado" => "../cadastro_feriado.php",
                            "Férias" => "../cadastro_ferias.php",
                            "Funcionário" => "../cadastro_funcionario.php",
                            "Abono" => "../cadastro_abono.php",
                            "Macro" => "../cadastro_macro.php",
                            "Motivo" => "../cadastro_motivo.php",
                            "Cargo" => "../cadastro_operacao.php",
                            "Parâmetro" => "../cadastro_parametro.php",
                            "Placas" => "../cadastro_placa.php",
                            "Setor" => "../cadastro_setor.php",
                            "Tipo de Documento" => "../cadastro_tipo_doc.php",
                            "Usuário" => "../cadastro_usuario.php",
                            "Habilidades Técnicas" => "../cadastro_habilidade_tecnica.php",
                            "Habilidades Comportamentais" => "../cadastro_habilidade_comportamental.php",
                            "Perfil de Acesso" => "../cadastro_perfil_acesso.php",
                            "Permissões de Usuários" => "../cadastro_usuario_perfil.php"
                        ],
                        "Ponto" => [
                            "Registrar Ponto" => "../batida_ponto.php",
                            "Consultar Endossos" => "../endosso.php",
                            "Espelhos de Ponto" => "../espelho_ponto.php",
                            "Integrações de Ponto" => "../carregar_ponto.php",
                            "Não Cadastrados" => "../nao_cadastrados.php",
                            "Não Conformidades" => "../nao_conformidade.php",
                            "Auditoria" => "../ponto_auditoria.php"
                        ],
                        "Painel" => [
                            "Ajustes" => "../paineis/ajustes.php",
                            "Disponibilidade" => "../paineis/disponibilidade.php",
                            "Endosso" => "../paineis/endosso.php",
                            "Jornada Aberta" => "../paineis/jornada.php",
                            "Não Conformidades Jurídicas" => "../paineis/nc_juridica.php",
                            "Saldo" => "../paineis/saldo.php",
                            "Escalas" => "../paineis/escala_parametro.php"
                        ],
                        "Relatórios" => [
                            "Pontos" => "../relatorio_pontos.php"
                        ],
                        "Assinatura Digital" => [
                            "Dashboard" => "index.php",
                            "Nova Assinatura" => "nova_assinatura.php",
                            "Envio em Massa" => "enviar_documento.php",
                            "Consultar" => "consultar.php",
                            "Funcionários" => "dadosbases/funcionarios.php",
                            "Finalizar (ICP)" => "finalizar.php"
                        ]
                    ];

                    foreach($menu_sistema as $categoria => $itens) {
                        echo '
                        <div class="relative group">
                            <button class="nav-link flex items-center h-full">
                                <span>'.$categoria.'</span>
                                <i class="fas fa-chevron-down ml-1 text-xs text-gray-400"></i>
                            </button>
                            <div class="absolute left-0 mt-2 w-56 bg-white border border-gray-200 rounded-md shadow-lg hidden group-hover:block z-50 max-h-[80vh] overflow-y-auto">
                                <div class="py-1">';
                                    foreach($itens as $nome => $link) {
                                        echo '<a href="'.$path_prefix.$link.'" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-blue-600 transition-colors">'.$nome.'</a>';
                                    }
                        echo '  </div>
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
                    <a href="<?php echo $path_prefix; ?>../logout.php" class="p-2 text-gray-400 hover:text-red-600 transition-colors" title="Sair do Sistema">
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
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="<?php echo $path_prefix; ?>index.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100">Dashboard</a>
                <a href="<?php echo $path_prefix; ?>nova_assinatura.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100">Nova Assinatura</a>
                <a href="<?php echo $path_prefix; ?>enviar_documento.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100">Envio em Massa</a>
                <a href="<?php echo $path_prefix; ?>consultar.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100">Consultar</a>
                <a href="<?php echo $path_prefix; ?>dadosbases/funcionarios.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100">Funcionários</a>
                <a href="<?php echo $path_prefix; ?>finalizar.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100">Finalizar (ICP)</a>
            </div>
        </div>
    </header>

    <main class="flex-grow py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">