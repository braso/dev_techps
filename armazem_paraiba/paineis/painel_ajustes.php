<?php
/* Modo debug
	ini_set("display_errors", 1);
	error_reporting(E_ALL);
//*/
include "funcoes_ponto.php";

function dadosAjusteMotoristas($idEmpresa, $periodoInicio, $periodoFim) {
    $ajustes = mysqli_fetch_all(query(
            "SELECT entidade.enti_tx_nome, ponto.pont_tx_data, ponto.pont_tx_matricula, motivo.moti_tx_nome, macroponto.macr_tx_nome FROM ponto"
               ." INNER JOIN entidade entidade on ponto.pont_tx_matricula = enti_tx_matricula"
               ." INNER JOIN motivo motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id"
               ." INNER JOIN macroponto macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id"
               ." WHERE pont_tx_status = 'ativo'"
                   ." AND enti_nb_empresa = '".$idEmpresa."'"
                   ." AND pont_nb_arquivoponto IS NULL"
                   ." AND pont_tx_descricao IS NOT NULL"
                   ." AND pont_tx_data BETWEEN STR_TO_DATE('".$periodoInicio." 00:00:00', '%Y-%m-%d %H:%i:%s') AND STR_TO_DATE('".$periodoFim." 23:59:59', '%Y-%m-%d %H:%i:%s');"
    ),MYSQLI_ASSOC);

    $resultado = [];
    $motoristasUnicos = [];

    // Coleta dados dos motoristas
    foreach ($ajustes as $registro) {
        $motorista = $registro['enti_tx_nome'];
        $tipo = $registro['macr_tx_nome'];
        $data = $registro['pont_tx_data'];

        // Inicializa o array para o motorista se ainda não existir
        if (!isset($motoristasUnicos[$motorista])) {
            $motoristasUnicos[$motorista] = [];
        }

        // Inicializa o array para o tipo se ainda não existir
        if (!isset($motoristasUnicos[$motorista][$tipo])) {
            $motoristasUnicos[$motorista][$tipo] = [];
        }

        // Adiciona a data ao tipo do motorista
        $motoristasUnicos[$motorista][$tipo][] = $data;
    }

    // Cria o resultado final com motoristas únicos
    foreach ($motoristasUnicos as $motorista => $tipos) {
        $resultado[] = [
            'motorista' => $motorista,
            'tipos' => $tipos
        ];
    }

    return $resultado;
}

function criar_relatorio_saldo(){
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

    $totalInicioJorn = 0;
    $totalFimJorn = 0;
    $totalInicioReif = 0;
    $totalFimReif = 0;

    foreach ($empresas as $empresa) {
        $ajustes =  dadosAjusteMotoristas($empresa["empr_nb_id"], $periodoInicio, $periodoFim);
    }


    $totalMotorista = count($ajustes);
    foreach($ajustes as $value){
        $totalInicioJorn += count($value["tipos"]["Inicio de Jornada"]);
        $totalFimJorn  += count($value["tipos"]["Fim de Jornada"]);
        $totalInicioReif += count($value["tipos"]["Inicio de Refeição"]);
        $totalFimReif += count($value["tipos"]["Fim de Refeição"]);
    }
    
    echo '<pre>';
    echo json_encode($ajustes, JSON_PRETTY_PRINT);
    echo '</pre>';
    die();
}

function salvarArquivo($path, $fileName, $data)
{
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
    $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
    file_put_contents($path.'/'.$fileName, $jsonData);
}

criar_relatorio_saldo();
