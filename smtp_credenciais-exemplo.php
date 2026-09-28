<?php
/* ============================================================
   Credenciais de e-mail (SMTP) — arquivo COMPARTILHADO por todos
   os domínios da instalação.

   COMO USAR NO SERVIDOR:
   1) copie este arquivo para  smtp_credenciais.php  (na mesma pasta,
      ou seja, a pasta que contém as pastas dos clientes);
   2) preencha SMTP_USER e SMTP_PASS com a caixa de e-mail em uso;
   3) pronto: vale para todos os domínios, inclusive os novos.

   smtp_credenciais.php é ignorado pelo git de propósito: a senha não
   pode ir para o repositório. Um .env de domínio com chaves SMTP_*
   tem prioridade sobre este arquivo.
   ============================================================ */

return [
    "SMTP_HOST"       => "smtp.titan.email",
    "SMTP_PORT"       => "465",
    "SMTP_SECURE"     => "ssl",
    "SMTP_USER"       => "assinatura@techps.com.br",
    "SMTP_PASS"       => "COLOQUE_A_SENHA_DA_CAIXA_AQUI",
    "SMTP_FROM_EMAIL" => "assinatura@techps.com.br",
    "SMTP_FROM_NAME"  => "Tech PS",
];
