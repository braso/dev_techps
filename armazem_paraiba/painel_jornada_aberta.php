<?php
/* Modo debug
	ini_set("display_errors", 1);
	error_reporting(E_ALL);
//*/

// ini_set("display_errors", 1);
// error_reporting(E_ALL);

include "funcoes_ponto.php";


function criar_relatorio_saldo()
{
    global $totalResumo;
    // $periodoInicio = $_POST["busca_dataInicio"];
    // $periodoFim = $_POST["busca_dataFim"];

    $periodoInicio = "2024-08-01";
    $periodoFim = "2024-08-16";

    $empresas = mysqli_fetch_all(
        query("SELECT empr_nb_id, empr_tx_nome FROM `empresa` WHERE empr_tx_status != 'inativo' ORDER BY empr_tx_nome ASC;"),
        MYSQLI_ASSOC
    );

    foreach ($empresas as $empresa) {

        $motoristas = mysqli_fetch_all(
            query("SELECT enti_nb_id, enti_tx_nome,enti_tx_matricula  FROM entidade WHERE enti_nb_empresa = $empresa[empr_nb_id] AND enti_tx_status != 'inativo' AND enti_tx_ocupacao IN ('Motorista', 'Ajudante') ORDER BY enti_tx_nome ASC"),
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
            $totais = array_fill_keys($campos, 0); // Inicializa totais para cada campo

            foreach ($diasPonto as $diaPonto) {
                foreach ($campos as $campo) {
                    if (strpos($diaPonto[$campo], "fa-warning") !== false) {
                        $totais[$campo] += substr_count($diaPonto[$campo], "fa-warning");
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

        $path = "./arquivos/paineis/jornada/$empresa[empr_nb_id]/$mes-$ano";
        $fileName = 'motoristas.json';
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        $jsonArquiMoto = json_encode($motoristasTotal, JSON_UNESCAPED_UNICODE);
        file_put_contents($path . '/' . $fileName, $jsonArquiMoto);

        $totalFimJornada = 0;
        $totalinicioRefeicao = 0;
        $totalfimRefeicao = 0;

        foreach ($motoristasTotal as $row) {
            $totalFimJornada     +=  $row['fimJornada'];
            $totalinicioRefeicao +=  $row['inicioRefeicao'];
            $totalfimRefeicao    +=  $row['fimRefeicao'];
        }

        $empresaTotal[] = [
            'empresaId'        => $empresa['empr_nb_id'],
            'empresaNome'      => $empresa['empr_tx_nome'],
            'fimJornada'       =>  $totalFimJornada,
            'inicioRefeicao'   =>  $totalinicioRefeicao,
            'fimRefeicao'      =>  $totalfimRefeicao,
        ];

        $path = "./arquivos/paineis/jornada/$empresa[empr_nb_id]/$mes-$ano";
        $fileName = 'totalEmpresas.json';
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        $jsonArquiTotais = json_encode($empresaTotal, JSON_UNESCAPED_UNICODE);
        file_put_contents($path . '/' . $fileName, $jsonArquiTotais);
    }

    $path = "./arquivos/paineis/jornada/empresas/$mes-$ano";
    $fileName = 'totalEmpresas.json';
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
    $jsonArquiTotais = json_encode($empresaTotal, JSON_UNESCAPED_UNICODE);
    file_put_contents($path . '/' . $fileName, $jsonArquiTotais);

    $totais[] = [
        'empresaId'        => $empresa['empr_nb_id'],
        'empresaNome'      => $empresa['empr_tx_nome'],
        'fimJornada'       =>  $totalFimJornada,
        'inicioRefeicao'   =>  $totalinicioRefeicao,
        'fimRefeicao'      =>  $totalfimRefeicao,
    ];
}

criar_relatorio_saldo();
