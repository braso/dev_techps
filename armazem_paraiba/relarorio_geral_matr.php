<?php
ini_set('display_errors', 1);
error_reporting(E_ERROR | E_WARNING );
// error_reporting(E_ALL);

include "funcoes_ponto.php";

function tempoParaMinutos($tempo) {
    list($horas, $minutos) = explode(':', $tempo);
    return intval($horas) * 60 + intval($minutos); // Convertendo para int antes da multiplicação
}

// Função para converter minutos em tempo
function minutosParaTempo($minutos) {
    $horas = floor($minutos / 60);
    $minutos = $minutos % 60;
    return sprintf("%02d:%02d", $horas, $minutos);
}

function criar_relatorio(){
    $_POST['busca_empresa'] = 3;
    $_POST['busca_dataInicio'] = '2024-02-01';
    $_POST['busca_dataFim'] = '2024-02-29';

    $idEmpresa = $_POST['busca_empresa'];
    $dataInicio = $_POST['busca_dataInicio'];
    $dataFim = $_POST['busca_dataFim'];
    
    $motoristas = mysqli_fetch_all(query("SELECT enti_nb_id, enti_tx_nome,enti_tx_matricula  FROM entidade WHERE enti_nb_empresa = $idEmpresa AND enti_tx_status != 'inativo'"), 
    MYSQLI_ASSOC);

    $rows = [];
    foreach($motoristas as $motorista){
        $endossado = '';

        $endossos = mysqli_fetch_all(query("SELECT * FROM endosso 
        WHERE endo_tx_status = 'ativo'
            AND (endo_tx_de = '$dataInicio'
            OR endo_tx_ate = '$dataFim')
            AND endo_nb_entidade = $motorista[enti_nb_id]"),MYSQLI_ASSOC);
        
        switch (count($endossos)) {
            case 0:
                $endossado = "N";
                break;
            
            case 1:
                $endossado = "E";
                break;
            
            default:
                $endossado = "";
                break;
        }
        
        $totalJorPrevResut = 0;
        $totalJorPrev = 0;
        $totalJorEfe = 0;
		$totalHE50 = 0;
		$totalHE100 = 0;
		$totalAdicNot = 0;
		$totalEspInd = 0;
		$totalSaldoPeriodo = 0;
		$saldoFinal = '00:00';
		$dateTimeInicio = new DateTime($dataInicio);
        $dateTimeFim = new DateTime($dataFim);
        $diasPonto = [];
        
        for ($dia = $dateTimeInicio; $dia <= $dateTimeFim; $dia->modify('+1 day')) { 
            $dataVez = $dia->format('Y-m-d');
            $diasPonto[] = diaDetalhePonto($motorista['enti_tx_matricula'], $dataVez);
        }
        
        $saldoAnterior = mysqli_fetch_all(query("SELECT endo_tx_saldo FROM `endosso`
					WHERE endo_tx_matricula = '".$motorista['enti_tx_matricula']."'
						AND endo_tx_ate < '".$dataInicio."'
						AND endo_tx_status = 'ativo'
					ORDER BY endo_tx_ate DESC
					LIMIT 1;"),MYSQLI_ASSOC);
					
		if(isset($saldoAnterior[0]['endo_tx_saldo'])){
			$saldoAnterior = $saldoAnterior[0]['endo_tx_saldo'];
		}elseif(!empty($aMotorista['enti_tx_banco'])){
			$saldoAnterior = $aMotorista['enti_tx_banco'];
			$saldoAnterior = $saldoAnterior[0][0] == '0' && strlen($saldoAnterior) > 5? substr($saldoAnterior, 1): $saldoAnterior;
		}else{
			$saldoAnterior = '00:00';
		}
        
        foreach ($diasPonto as $diaPonto) {
            $totalJorPrev      += tempoParaMinutos($diaPonto['jornadaPrevista']);
            $totalJorEfe       += tempoParaMinutos($diaPonto['diffJornadaEfetiva']);
            $totalHE50         += empty($diaPonto['he50']) ? tempoParaMinutos("00:00") : tempoParaMinutos($diaPonto['he50']);
            $totalHE100        += empty($diaPonto['he100']) ? tempoParaMinutos("00:00") : tempoParaMinutos($diaPonto['he100']);
			$totalAdicNot      += tempoParaMinutos($diaPonto['adicionalNoturno']);
			$totalEspInd       += tempoParaMinutos($diaPonto['esperaIndenizada']);
			$totalSaldoPeriodo += tempoParaMinutos($diaPonto['diffSaldo']);
        }
        
        if($saldoAnterior != '--:--'){
			$saldoFinal = somarHorarios([$saldoAnterior, minutosParaTempo($totalSaldoPeriodo)]);
		}else{
			$saldoFinal = somarHorarios(['00:00', minutosParaTempo($totalSaldoPeriodo)]);
		}
        $rows[] = [
            'motorista' => $motorista['enti_tx_nome'],
            'statusEndosso' => $endossado,
            'jornadaPrevista' => minutosParaTempo($totalJorPrev),
            'JornadaEfetiva' => minutosParaTempo($totalJorEfe),
            'he50' => minutosParaTempo($totalHE50),
    		'he100' => minutosParaTempo($totalHE100),
    		'adicionalNoturno' => minutosParaTempo($totalAdicNot),
    		'esperaIndenizada' => minutosParaTempo($totalEspInd),
    		'saldoAnterior' => $saldoAnterior,
    		'saldoPeriodo' => minutosParaTempo($totalSaldoPeriodo),
    		'saldoFinal' => $saldoFinal
        ];
        var_dump($rows);
        exit;
    }
    
}

function index(){
    global $totalResumo, $CONTEX;

    criar_relatorio();
}