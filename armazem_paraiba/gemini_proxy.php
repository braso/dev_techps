<?php
    include_once "load_env.php";
    header('Content-Type: application/json');
    if($_SERVER['REQUEST_METHOD'] !== 'POST'){
        http_response_code(405);
        echo json_encode(["error" => "Método inválido"]);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $msg = trim($input['message'] ?? '');
    if($msg === ''){
        http_response_code(400);
        echo json_encode(["error" => "Mensagem vazia"]);
        exit;
    }
    $apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');
    if(!$apiKey){
        http_response_code(500);
        echo json_encode(["error" => "API key não configurada"]);
        exit;
    }
$prefix = "
Você é um assistente especializado em habilidades técnicas e perfil comportamental de funcionários e setores. 

Responda SEMPRE em português.

Seu estilo:
- Respostas curtas, claras e úteis.
- Cordial e profissional.
- Não faça perguntas obrigatórias.
- Não conduza o usuário por etapas.
- Apenas responda ao que o usuário perguntar.
- Quando o usuário solicitar, forneça:
  • Recomendações práticas,
  • Exemplos de avaliação,
  • Como vincular habilidades a funções, perfis ou setores.
- Se o usuário não especificar setor, área ou tipo de habilidade, responda mesmo assim, com sugestões gerais, sem exigir mais informações.
";
    $payload = [
        "contents" => [
            [
                "role" => "user",
                "parts" => [["text" => $prefix."\n\n".$msg]]
            ]
        ]
    ];
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=".urlencode($apiKey);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if($resp === false){
        http_response_code(500);
        echo json_encode(["error" => "Falha na requisição", "detail" => $err]);
        exit;
    }
    $data = json_decode($resp, true);
    if($httpCode !== 200){
        http_response_code($httpCode);
        $msgErr = $data['error']['message'] ?? 'Erro desconhecido';
        echo json_encode(["error" => $msgErr, "status" => $httpCode]);
        exit;
    }
    $text = "";
    if(isset($data['candidates'][0]['content']['parts'])){
        foreach($data['candidates'][0]['content']['parts'] as $p){
            if(isset($p['text'])){ $text .= $p['text']; }
        }
    }
    echo json_encode(["text" => $text]);
?>
