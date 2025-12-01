<?php
    //* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
		
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
        header("Pragma: no-cache"); // HTTP 1.0.
        header("Expires: 0");
	//*/

    include_once __DIR__."/funcoes_ponto.php";

    $sqlFuncionarios = 
        "SELECT entidade.enti_nb_id FROM entidade"
			." LEFT JOIN parametro ON enti_nb_parametro = para_nb_id"
			." WHERE enti_tx_status = 'ativo'"
				." AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')"
			." ORDER BY enti_tx_nome;"
        ;

	$funcionarios = mysqli_fetch_all(query($sqlFuncionarios), MYSQLI_ASSOC);
    $dateMes = new DateTime("2025-08-01 00:00:00");
	
	for($mes = $dateMes; $mes < new DateTime("2025-11-01 00:00:00"); $mes->add(DateInterval::createFromDateString("1 month"))){
        dd("Mês: ".$mes->format("Y-m-d"), false);
        foreach($funcionarios as $funcionario){
            dd(["Funcionário: ", $funcionario], false);
            $month = intval($mes->format("m"));
            $year = intval($dateMes->format("Y"));
            
            $daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year));
            $endossos = mysqli_fetch_all(query(
                "SELECT endo_nb_id, endo_tx_filename FROM endosso 
                    WHERE endo_tx_status = 'ativo'
                        AND endo_nb_entidade = '{$funcionario["enti_nb_id"]}'
                        AND endo_tx_de >= '".sprintf("%04d-%02d-%02d", $year, $month, "01")."'
                        AND endo_tx_ate <= '".sprintf("%04d-%02d-%02d", $year, $month, $daysInMonth)."'
                    ORDER BY endo_tx_de ASC"
            ),MYSQLI_ASSOC);

            foreach($endossos as &$endosso){
                if(!file_exists("{$_SERVER["DOCUMENT_ROOT"]}{$_ENV["APP_PATH"]}{$_ENV["CONTEX_PATH"]}/arquivos/endosso/{$endosso["endo_tx_filename"]}.csv")){
                    $endosso = null;
                    continue;
                }
                $endosso = array_merge(["endo_nb_id" => $endosso["endo_nb_id"]], lerEndossoCSV($endosso["endo_tx_filename"]));

                dd(["Endosso: ", $endosso], false);

                $desatualizado = false;
                foreach($endosso["endo_tx_pontos"] as &$row){
                    if(is_int(strpos($row[0], "glyphicon glyphicon-pencil color_green'></i>"))){
                        $row[0] = str_replace("glyphicon glyphicon-pencil color_green'></i>", "color_green glyphicon glyphicon-pencil'>(E)</i>", $row[0]);
                        $desatualizado = true;
                    }elseif(is_int(strpos($row[0], "color_green glyphicon glyphicon-pencil'></i>"))){
                        $row[0] = str_replace("color_green glyphicon glyphicon-pencil'></i>", "color_green glyphicon glyphicon-pencil'>(E)</i>", $row[0]);
                        $desatualizado = true;

                    }elseif(is_int(strpos($row[0], "glyphicon glyphicon-pencil color_red'></i>"))){
                        $row[0] = str_replace("glyphicon glyphicon-pencil color_red'></i>", "color_red glyphicon glyphicon-pencil'>(E)</i>", $row[0]);
                        $desatualizado = true;
                    }elseif(is_int(strpos($row[0], "color_red glyphicon glyphicon-pencil'></i>"))){
                        $row[0] = str_replace("color_red glyphicon glyphicon-pencil'></i>", "color_red glyphicon glyphicon-pencil'>(E)</i>", $row[0]);
                        $desatualizado = true;

                    }elseif(is_int(strpos($row[0], "glyphicon glyphicon-pencil color_blue'></i>"))){
                        $row[0] = str_replace("glyphicon glyphicon-pencil color_blue'></i>", "color_blue glyphicon glyphicon-pencil'>(E)</i>", $row[0]);
                        $desatualizado = true;
                    }elseif(is_int(strpos($row[0], "color_blue glyphicon glyphicon-pencil'></i>"))){
                        $row[0] = str_replace("color_blue glyphicon glyphicon-pencil'></i>", "color_blue glyphicon glyphicon-pencil'>(E)</i>", $row[0]);
                        $desatualizado = true;
                    }
                }
                
                if($desatualizado){
                    if(preg_match("/_\d*/", $endosso["endo_tx_filename"]) > 0){
                        $filename = substr($endosso["endo_tx_filename"], 0, strpos($endosso["endo_tx_filename"], "_"));
                    }else{
                        $filename = $endosso["endo_tx_filename"];
                    }
                    $path = "{$_SERVER["DOCUMENT_ROOT"]}{$CONTEX["path"]}/arquivos/endosso";
                    if(file_exists("{$path}/{$filename}.csv")){
                        $version = 2;
                        while(file_exists("{$path}/{$filename}_".strval($version).".csv")){
                            $version++;
                        }
                        $filename = "{$filename}_".strval($version);
                    }
                    $endosso["endo_tx_filename"] = $filename;

                    $endosso["endo_tx_pontos"]  = json_encode($endosso["endo_tx_pontos"]);
                    $endosso["endo_tx_pontos"]  = str_replace("<\/", "</", $endosso["endo_tx_pontos"]);
                    $endosso["totalResumo"]     = json_encode($endosso["totalResumo"]);
                    $endosso["totalResumo"]     = str_replace("<\/", "</", $endosso["totalResumo"]);

                    $file = fopen($path."/".$filename.".csv", "w");
                    fputcsv($file, array_keys($endosso));
                    fputcsv($file, array_values($endosso));
                    fclose($file);

                    unset($endosso["endo_tx_pontos"]);
                    unset($endosso["totalResumo"]);
                    unset($endosso["endo_tx_nome"]);
                    
                    atualizar("endosso", array_keys($endosso), array_values($endosso), $endosso["endo_nb_id"]);
                    
                    dd($endosso["endo_tx_filename"]." atualizado.", false);
                }
            }
        }
    }

    dd("finalizado");
?>