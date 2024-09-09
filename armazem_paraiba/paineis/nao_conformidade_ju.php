<?php
/* Modo debug
	ini_set("display_errors", 1);
	error_reporting(E_ALL);
//*/
include __DIR__ . "../../funcoes_ponto.php";

function criar_relatorio_saldo(){
    global $totalResumo;
    // $periodoInicio = $_POST["busca_dataInicio"];
    // $periodoFim = $_POST["busca_dataFim"];

    $periodoInicio = "2024-07-01";
    $periodoFim = "2024-07-31";

    $empresas = mysqli_fetch_all(
        query(
            "SELECT empr_nb_id, empr_tx_nome"
                . " FROM `empresa` WHERE empr_tx_status = 'ativo'"
                . " ORDER BY empr_tx_nome ASC;"
        ),
        MYSQLI_ASSOC
    );

    foreach ($empresas as $empresa) {
        $motoristas = dadosMotorista($empresa['empr_nb_id'], $periodoInicio, $periodoFim);

        $totalFimJornada = 0;
        $totalinicioRefeicao = 0;
        $totalfimRefeicao = 0;

        foreach ($motoristas as $motorista) {
            $totalFimJornada += sizeof($motorista['fimJornada']);
            $totalinicioRefeicao += sizeof($motorista['inicioRefeicao']);
            $totalfimRefeicao += sizeof($motorista['fimRefeicao']);
        }

        $empresaTotal = [
            'empresaId'        =>  $empresa['empr_nb_id'],
            'empresaNome'      =>  $empresa['empr_tx_nome'],
            'fimJornada'       =>  $totalFimJornada,
            'inicioRefeicao'   =>  $totalinicioRefeicao,
            'fimRefeicao'      =>  $totalfimRefeicao,
        ];

        $path = "./arquivos/jornada/$empresa[empr_nb_id]/";

        // salvarArquivo($path, 'motoristas.json', $motoristasTotal);
        salvarArquivo($path, 'empresa_' . $empresa['empr_nb_id'] . '.json', $empresaTotal);

        $totalEmpresa[] = $empresaTotal;
        $path = "./arquivos/jornada/";

        salvarArquivo($path, 'empresa_' . $empresa['empr_nb_id'] . '.json', $totalEmpresa);
    }

    foreach ($totalEmpresa as $empresa) {
        $totalFimJornada += sizeof($empresa['fimJornada']);
        $totalinicioRefeicao += sizeof($empresa['inicioRefeicao']);
        $totalfimRefeicao += sizeof($empresa['fimRefeicao']);
    }

    $empresaTotal = [
        'fimJornada'       =>  $totalFimJornada,
        'inicioRefeicao'   =>  $totalinicioRefeicao,
        'fimRefeicao'      =>  $totalfimRefeicao,
    ];

    $path = "./arquivos/jornada/";

    salvarArquivo($path, 'totalEmpresa.json', $empresaTotal);
}

function dadosMotorista($idEnpresa, $periodoInicio, $periodoFim) {
    $motoristas = mysqli_fetch_all(
        query(
            "SELECT enti_nb_id, enti_tx_nome,enti_tx_matricula FROM entidade"
                . " WHERE enti_tx_status = 'ativo'"
                . " AND enti_nb_empresa = $idEnpresa"
                . " AND enti_tx_ocupacao IN ('Motorista', 'Ajudante')"
                . " ORDER BY enti_tx_nome ASC;"
        ),
        MYSQLI_ASSOC
    );

    foreach ($motoristas as $motorista) {
        $diasPonto = [];
        $dataTimeInicio = new DateTime($periodoInicio);
        $dataTimeFim = new DateTime($periodoFim);

        $mes = $dataTimeInicio->format('m');
        $ano = $dataTimeInicio->format('Y');

        for ($date = $dataTimeInicio; $date <= $dataTimeFim; $date->modify('+1 day')) {
            $dataVez = $date->format('Y-m-d');

            $diasPonto[] = diaDetalhePonto($motorista['enti_tx_matricula'], $dataVez);
        }


        $totais = [];


        foreach ($diasPonto as $diaPonto) {

            $keys = array_keys($diaPonto);
            $dataTimeDia = DateTime::createFromFormat('d/m/Y', $diaPonto["data"]);
            $dia = $dataTimeDia->format('d/m');


            foreach ($keys as $key) {
                if (!in_array($key, ["data", "diaSemana", "diffSaldo"])) {
                    $totais[$key] = [];
                }
            }
           
            foreach ($keys as $key) {
                if (strpos($diaPonto[$key], "fa-warning") !== false) {
                    $totais[$key][$dia] += substr_count($diaPonto[$key], "fa-warning");
                }
            }

            echo '<pre>';
            echo json_encode($totais, JSON_PRETTY_PRINT);
            echo '</pre>';

            // foreach ($keys as $campo) {
            //     if (strpos($diaPonto[$campo], "fa-warning") !== false) {
            //         $totais[$campo][$dia] += 1;
            //     }
            // }
        }

        die();

        $motoristasTotal = [
            'IdMotorista' => $motorista['enti_nb_id'],
            'matricula' => $motorista['enti_tx_matricula'],
            'motorista' => $motorista['enti_tx_nome'],
            'fimJornada' =>  $totais['fimJornada'],
            'inicioRefeicao' =>  $totais['inicioRefeicao'],
            'fimRefeicao' =>  $totais['fimRefeicao'],
        ];

        $path = "./arquivos/jornada/$idEnpresa";
        salvarArquivo($path, $motorista['enti_tx_matricula'] . '.json', $motoristasTotal);

        $todos[] = $motoristasTotal;
    }

    return $todos;
}

function salvarArquivo($path, $fileName, $data) {
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
    $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
    file_put_contents($path . '/' . $fileName, $jsonData);
}


criar_relatorio_saldo();
