<?php
    header('Content-Type: application/json');

    $arquivoCache = __DIR__."/endereço.json";

    function lerCache($arquivo){
        if(!file_exists($arquivo)){
            return [];
        }
        $conteudo = file_get_contents($arquivo);
        if($conteudo === false || trim($conteudo) === ""){
            return [];
        }
        $dados = json_decode($conteudo, true);
        return is_array($dados) ? $dados : [];
    }

    function salvarCache($arquivo, $dados){
        return file_put_contents($arquivo, json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }

    function chaveCoordenada($lat, $lon){
        return round(floatval($lat), 6).",".round(floatval($lon), 6);
    }

    $metodo = $_SERVER["REQUEST_METHOD"];

    if($metodo === "GET"){
        $lat = $_GET["lat"] ?? null;
        $lon = $_GET["lon"] ?? null;

        if(!is_numeric($lat) || !is_numeric($lon)){
            http_response_code(400);
            echo json_encode(["erro" => "Latitude e longitude sao obrigatorias e devem ser numericas."]);
            exit;
        }

        $cache = lerCache($arquivoCache);
        $chave = chaveCoordenada($lat, $lon);

        if(isset($cache[$chave])){
            echo json_encode(["encontrado" => true, "endereco" => $cache[$chave]]);
        }else{
            echo json_encode(["encontrado" => false]);
        }
        exit;
    }

    if($metodo === "POST"){
        $input = json_decode(file_get_contents("php://input"), true);

        $lat = $input["lat"] ?? $_POST["lat"] ?? null;
        $lon = $input["lon"] ?? $_POST["lon"] ?? null;
        $endereco = $input["endereco"] ?? $_POST["endereco"] ?? null;

        if(!is_numeric($lat) || !is_numeric($lon) || empty($endereco)){
            http_response_code(400);
            echo json_encode(["erro" => "Lat, lon e endereco sao obrigatorios."]);
            exit;
        }

        $cache = lerCache($arquivoCache);
        $chave = chaveCoordenada($lat, $lon);
        $cache[$chave] = trim($endereco);

        if(salvarCache($arquivoCache, $cache)){
            echo json_encode(["sucesso" => true, "chave" => $chave]);
        }else{
            http_response_code(500);
            echo json_encode(["erro" => "Nao foi possivel salvar o cache."]);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(["erro" => "Metodo nao permitido."]);
