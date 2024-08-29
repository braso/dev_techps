<?php
    //* Modo debug
        ini_set("display_errors", 1);
        error_reporting(E_ALL);

        header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");
    //*/

    die(var_dump($_ENV));

    include __DIR__."/../funcoes_ponto.php";

    function salvarArquivo(string $path, string $fileName, string $data){
        if(!is_dir($path)){
            mkdir($path, 0755, true);
        }
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        file_put_contents($path.'/'.$fileName, $data);
    }

    function pegarDadosMotoristas($idEmpresa, $periodoInicio, $periodoFim){
        $motoristas = mysqli_fetch_all(query(
            "SELECT enti_nb_id, enti_tx_nome, enti_tx_matricula FROM entidade"
            ." WHERE enti_tx_status = 'ativo'"
                ." AND enti_nb_empresa = ".$idEmpresa
                ." AND enti_tx_ocupacao IN ('Motorista', 'Ajudante')"
            ." ORDER BY enti_tx_nome ASC;"
        ), MYSQLI_ASSOC);

        foreach($motoristas as &$motorista){
            $dataTimeInicio = new DateTime($periodoInicio);
            $dataTimeFim = new DateTime($periodoFim);

            $campos = ["fimJornada", "inicioRefeicao", "fimRefeicao"];
            $totais = [];
            // Inicializa o array $totais com chaves para cada campo e arrays vazios para armazenar as datas
            foreach($campos as $campo){
                $totais[$campo] = [];
            }

            for($date = $dataTimeInicio; $date <= $dataTimeFim; $date->modify('+1 day')){
                $diaPonto = diaDetalhePonto($motorista["enti_tx_matricula"], $date->format('Y-m-d'));
                $dataTimeDia = DateTime::createFromFormat('d/m/Y', $diaPonto["data"]);
                $dia = $dataTimeDia->format('d/m');
                foreach($campos as $campo){
                    if(is_int(strpos($diaPonto[$campo], "fa-warning"))){
                        $totais[$campo][$dia] += 1;
                    }
                }
            }

            $motorista = [
                "idMotorista"       => $motorista["enti_nb_id"],
                "matricula"         => $motorista["enti_tx_matricula"],
                "nome"              => $motorista["enti_tx_nome"],
                "fimJornada"        => $totais["fimJornada"],
                "inicioRefeicao"    => $totais["inicioRefeicao"],
                "fimRefeicao"       => $totais["fimRefeicao"],
            ];
        }

        return $motoristas;
    }

    function criar_relatorio_jornada(){
        global $totalResumo;
        $_POST["busca_dataInicio"] = "2024-07-01";
        $_POST["busca_dataFim"] = "2024-07-31";
        
        
        $periodoInicio = $_POST["busca_dataInicio"];
        $periodoFim = $_POST["busca_dataFim"];
        
        
        $empresas = mysqli_fetch_all(query(
            "SELECT empr_nb_id, empr_tx_nome FROM empresa"
            ." WHERE empr_tx_status = 'ativo'"
            ." ORDER BY empr_tx_nome ASC;"
        ),MYSQLI_ASSOC);
        
        $totalEmpresas = [];
        
        $campos = ["fimJornada", "inicioRefeicao", "fimRefeicao"];
        
        $path = "./arquivos/jornada";
        foreach($empresas as $empresa){
            $motoristas = pegarDadosMotoristas($empresa["empr_nb_id"], $periodoInicio, $periodoFim);
            
            $totais = [];
            foreach($campos as $campo){
                $totais[$campo] = 0;
            }
            
            foreach($motoristas as $motorista){
                foreach(array_keys($totais) as $key){
                    $totais[$key] += sizeof($motorista[$key]);    
                }
                salvarArquivo($path."/".$empresa["empr_nb_id"], $motorista['enti_tx_matricula'].'.json', $motorista);
            }

            $empresa = array_merge(["empresaId" => $empresa["empr_nb_id"], "empresaNome" => $empresa["empr_tx_nome"]], $totais);
        
            salvarArquivo($path."/".$empresa["empresaId"], "empresa_".$empresa["empresaId"].".json", $empresa);

            $totalEmpresas[] = $empresa;
        }
        
        foreach($campos as $campo){
            $totais[$campo] = 0;
        }

        foreach($totalEmpresas as $totalEmpresa){
            foreach($campos as $campo){
                $totalEmpresas[$campo] += sizeof($totalEmpresa[$campo]);
            }
        }
        salvarArquivo($path, "empresas.json", $totalEmpresas);
    }

    criar_relatorio_jornada();
