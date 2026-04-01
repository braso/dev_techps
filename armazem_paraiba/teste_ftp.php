<?php

$server = "ftp-jornadas.positronrt.com.br";
$user = "09608305000155";
$pass = "09608305000155";

echo "<h2>Teste FTP via cURL (TLS)</h2>";

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, "ftp://$server/");
curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// 🔥 FORÇA TLS
curl_setopt($ch, CURLOPT_FTP_SSL, CURLFTPSSL_ALL);
curl_setopt($ch, CURLOPT_FTPSSLAUTH, CURLFTPAUTH_TLS);

// 🚨 DESABILITA VERIFICAÇÃO SSL (ESSENCIAL)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

// debug
curl_setopt($ch, CURLOPT_VERBOSE, true);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo "❌ Erro: " . curl_error($ch);
} else {
    echo "✅ Conectado com sucesso!<br><br>";
    echo "<pre>$response</pre>";
}

curl_close($ch);