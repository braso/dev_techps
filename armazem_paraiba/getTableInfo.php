<?php
    $interno = true; //Utilizado no conecta.php para reconhecer se quem está tentando acessar é uma tela ou uma query interna.
    include_once __DIR__."/conecta.php";

    
    $query = base64_decode($_POST["query"][0]).urldecode(base64_decode($_POST["query"][1]));
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total = $result->num_rows;


    $limit = intval(base64_decode($_POST["query"][2]));
    $offset = intval(base64_decode($_POST["query"][3]));
    if($offset > $total){
        $offset = $offset-$limit;
    }

    $query .= " LIMIT {$limit} OFFSET {$offset};";

    try {
        $queryResult = mysqli_fetch_all(query($query), MYSQLI_ASSOC);
    } catch (Exception $e) {
        echo json_encode($e->getMessage());
        exit;
    }


    $tabela = [
        "header" => [],
        "rows" => [],
        "total" => $total
    ];

    if(!empty($queryResult)){
        $headers = array_keys($queryResult[0]);
        $tabela["header"] = $headers;
        
        foreach ($queryResult as $row) {
            $tabelaRow = [];
            foreach ($row as $key => $data){
                preg_match('/^((.+[^\n ])*)\((.*)\)$/', $data, $match);
                if(isset($match[1])){
                    try{
                        $data = eval("return {$match[0]};");
                    }catch(Exception $e){}
                }
                $tabelaRow[$key] = $data;
            }
            
            $tabela["rows"][] = $tabelaRow;
        }
    }

    echo json_encode($tabela);