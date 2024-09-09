<?php

	$curl = curl_init();

	curl_setopt_array($curl, [
	CURLOPT_PORT => "8000",
	CURLOPT_URL => "http://localhost:8000/braso/armazem_paraiba/cadastro_usuario.php",
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_ENCODING => "",
	CURLOPT_MAXREDIRS => 10,
	CURLOPT_TIMEOUT => 30,
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	CURLOPT_CUSTOMREQUEST => "GET",
	CURLOPT_POSTFIELDS => "",
	CURLOPT_COOKIE => "PHPSESSID=3ip2asmqb5epkp8f1mj7p5cbk7",
	CURLOPT_HTTPHEADER => [
		"Content-Type: application/x-www-form-urlencoded",
		"User-Agent: insomnia/9.0.0"
	],
	]);

	$response = curl_exec($curl);
	$err = curl_error($curl);

	curl_close($curl);

	if ($err) {
	echo "cURL Error #:" . $err;
	} else {
	echo $response;
	}