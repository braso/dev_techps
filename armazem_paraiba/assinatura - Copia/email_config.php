<?php
// Configurações de E-mail (SMTP)
// Preencha com os dados do seu provedor de e-mail

// Exemplo com Gmail (requer Senha de App):
// Host: smtp.gmail.com
// Port: 587 (TLS) ou 465 (SSL)
// Username: seu_email@gmail.com
// Password: senha_de_app_gerada

define('SMTP_HOST', 'smtp.titan.email'); // Altere para seu host
define('SMTP_USER', 'suporte@techps.com.br'); // Altere para seu usuário
define('SMTP_PASS', 'gX%]b6qNe=Tg]56'); // Altere para sua senha
define('SMTP_PORT', 465); // Porta (587 ou 465)
define('SMTP_SECURE', 'ssl'); // 'tls' ou 'ssl'
define('SMTP_FROM_EMAIL', 'suporte@techps.com.br');
define('SMTP_FROM_NAME', 'Tech PS');

// URL base para o link de assinatura
// Deve apontar para a pasta onde está o arquivo assinar_via_link.php
// Ex: http://localhost/techps/dev_techps/armazem_paraiba/assinatura
define('BASE_URL_ASSINATURA', 'http://localhost/braso/armazem_paraiba/assinatura');
?>
