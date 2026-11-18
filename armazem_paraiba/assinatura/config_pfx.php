<?php
// Configuração do Certificado Digital ICP-Brasil
// Defina aqui o caminho do arquivo .pfx e a senha

return [
    'pfx_path' => __DIR__ . '/pfx/TECH PS LTDA_42390531000188.pfx', // Caminho absoluto ou relativo ao script
    'pfx_password' => 'TechPS@2026', // Coloque a senha do certificado aqui
    'python_path' => '/opt/alt/python310/bin/python3', // Interpretador Python 3.10 CloudLinux cPanel
    'auto_sign' => true, // Se true, usa este certificado automaticamente quando o ICP for solicitado
    'preservar_assinaturas_padrao' => false // Por padrão falso (desmarcado); ativado sob demanda pelo usuário
];
?>