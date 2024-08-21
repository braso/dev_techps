<?php
/* Modo debug
	ini_set("display_errors", 1);
	error_reporting(E_ALL);
//*/
include "funcoes_ponto.php";

function salvarArquivo($path, $fileName, $data) {
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
    $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
    file_put_contents($path . '/' . $fileName, $jsonData);
}

function dadosMotorista($idEnpresa, $periodoInicio, $periodoFim) {
    $motoristas = mysqli_fetch_all(
        query(
        "SELECT enti_nb_id, enti_tx_nome,enti_tx_matricula FROM entidade" 
        ." WHERE enti_tx_status = 'ativo'"
        ." AND enti_nb_empresa = $idEnpresa"
        ." AND enti_tx_ocupacao IN ('Motorista', 'Ajudante')"
        ." ORDER BY enti_tx_nome ASC;"
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

        $campos = ['fimJornada', 'inicioRefeicao', 'fimRefeicao'];
        $totais = [];

        // Inicializa o array $totais com chaves para cada campo e arrays vazios para armazenar as datas
        foreach ($campos as $campo) {
            $totais[$campo] = [];
        }

        foreach ($diasPonto as $diaPonto) {
            $dataTimeDia = DateTime::createFromFormat('d/m/Y', $diaPonto["data"]);
            $dia = $dataTimeDia->format('d/m');
            foreach ($campos as $campo) {
                if (strpos($diaPonto[$campo], "fa-warning") !== false) {
                    $totais[$campo][$dia] += 1;
                }
            }
        }

        $motoristasTotal[] = [
            'IdMotorista' => $motorista['enti_nb_id'],
            'matricula' => $motorista['enti_tx_matricula'],
            'motorista' => $motorista['enti_tx_nome'],
            'fimJornada' =>  $totais['fimJornada'],
            'inicioRefeicao' =>  $totais['inicioRefeicao'],
            'fimRefeicao' =>  $totais['fimRefeicao'],
        ];
    }

    return $motoristasTotal;
}

function criar_relatorio_saldo() {
    global $totalResumo;
    // $periodoInicio = $_POST["busca_dataInicio"];
    // $periodoFim = $_POST["busca_dataFim"];

    $periodoInicio = "2024-07-16";
    $periodoFim = "2024-08-16";

    $empresas = mysqli_fetch_all(
        query(
        "SELECT empr_nb_id, empr_tx_nome"
        ." FROM `empresa` WHERE empr_tx_status = 'ativo'"
        ." ORDER BY empr_tx_nome ASC;"
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

        $empresaTotal[] = [
            'empresaId'        => $empresa['empr_nb_id'],
            'empresaNome'      => $empresa['empr_tx_nome'],
            'fimJornada'       =>  $totalFimJornada,
            'inicioRefeicao'   =>  $totalinicioRefeicao,
            'fimRefeicao'      =>  $totalfimRefeicao,
        ];


        // echo '<pre>';
        // echo json_encode($empresaTotal, JSON_PRETTY_PRINT);
        // echo '</pre>';
        // die();


        $mesAno = (new DateTime($periodoInicio))->format('m-Y');
        $path = "./arquivos/paineis/jornada/$empresa[empr_nb_id]/$mesAno";
    
        salvarArquivo($path, 'motoristas.json', $motoristasTotal);
        salvarArquivo($path, 'totalEmpresas.json', $empresaTotal);
    }

}

criar_relatorio_saldo();
