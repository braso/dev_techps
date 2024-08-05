<?php
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/

    ini_set('display_errors', 1);
		error_reporting(E_ALL);

	header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
	header("Pragma: no-cache"); // HTTP 1.0.
	header("Expires: 0");
	
    $empresas = [];
    $empresasTotais = [];
    $emissao = [];

    $dataTimeInicio = new DateTime($periodoInicio);
    $dataTimeFim = new DateTime($periodoFim);
    $mes = $dataTimeInicio->format('m');
    $ano = $dataTimeInicio->format('Y');

    if (is_dir("./arquivos/paineis/Saldo/empresas/$mes-$ano") != false) {
        $file = "./arquivos/paineis/Saldo/empresas/$mes-$ano/empresas.json";
        if (file_exists("./arquivos/paineis/Saldo/empresas/$mes-$ano")) {
            $conteudo_json = file_get_contents($file);
            $empresas = json_decode($conteudo_json,true);
        }

        // Obtém O total dos saldos de cada empresa
        $fileEmpresas = "./arquivos/paineis/Saldo/empresas/$mes-$ano/totalEmpresas.json";

        if (file_exists("./arquivos/paineis/Saldo/empresas/$mes-$ano")) {
            $conteudo_json = file_get_contents($fileEmpresas);
            $empresasTotais = json_decode($conteudo_json,true);
        }
        
        // Obtém o tempo da última modificação do arquivo
        $timestamp = filemtime($file);
        $Emissão = date('d/m/Y H:i:s', $timestamp);
    }else   
        echo '<script>alert("Não Possui dados desse mês")</script>';

    var_dump($empresas);

	

?>