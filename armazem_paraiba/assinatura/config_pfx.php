<?php
// Configuração do Certificado Digital ICP-Brasil
// Defina aqui o caminho do arquivo .pfx e a senha

return [
    'pfx_path' => __DIR__ . '/pfx/TECH PS LTDA_42390531000188.pfx', // Caminho absoluto ou relativo ao script
    'pfx_password' => 'TechPS@2026', // Coloque a senha do certificado aqui
    'auto_sign' => true // Se true, o sistema tentará usar este certificado automaticamente
];
?>