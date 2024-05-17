<?php
    /* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/

    include "funcoes_ponto.php";

    function criar_relatorio(){

        $idEmpresa = intval($_POST['busca_empresa']);
        $dataInicio = $_POST['busca_dataInicio'];
        $dataFim = $_POST['busca_dataFim'];
        
        if (empty($idEmpresa) || empty($dataInicio) || empty($dataFim)){
                echo '<script>alert("Insira data e empresa para gerar relatório.");</script>';
                index();
                exit;
        }
        
        // $motoristas = mysqli_fetch_all(query("SELECT enti_nb_id, enti_tx_nome,enti_tx_matricula  FROM entidade WHERE enti_nb_empresa = $idEmpresa AND enti_tx_status != 'inativo'"), 
        // MYSQLI_ASSOC);
        $motoristas = mysqli_fetch_all(query("SELECT enti_nb_id, enti_tx_nome, enti_tx_matricula 
    FROM entidade 
    WHERE enti_tx_matricula = '007' 
    AND enti_tx_status != 'inativo'
    "), 
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
            
            $totalJorPrevResut = "00:00";
            $totalJorPrev = "00:00";
            $totalJorEfe = "00:00";
            $totalHE50 = "00:00";
            $totalHE100 = "00:00";
            $totalAdicNot = "00:00";
            $totalEspInd = "00:00";
            $totalSaldoPeriodo = "00:00";
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
            
            $totalSaldoPeriodo = 0;
            foreach ($diasPonto as $diaPonto) {
                // $totalJorPrev      = tempoParaMinutos($diaPonto['jornadaPrevista']);
                // $totalJorEfe       = tempoParaMinutos($diaPonto['diffJornadaEfetiva']);
                // $totalHE50         = empty($diaPonto['he50']) ? tempoParaMinutos("00:00") : tempoParaMinutos($diaPonto['he50']);
                // $totalHE100        = empty($diaPonto['he100']) ? tempoParaMinutos("00:00") : tempoParaMinutos($diaPonto['he100']);
                // $totalAdicNot      = tempoParaMinutos($diaPonto['adicionalNoturno']);
                // $totalEspInd       = tempoParaMinutos($diaPonto['esperaIndenizada']);
                // $totalSaldoPeriodo = somarHorarios([$diaPonto['diffSaldo'],$totalSaldoPeriodo]);

                $totalJorPrev      = somarHorarios([$totalJorPrev,      $diaPonto['diffSaldo']]);
                $totalJorEfe       = somarHorarios([$totalJorEfe,       $diaPonto['diffJornadaEfetiva']]);
                $totalHE50         = somarHorarios([$totalHE50,         (empty($diaPonto['he50'])? '00:00': $diaPonto['he50'])]);
                $totalHE100        = somarHorarios([$totalHE100,        (empty($diaPonto['he100'])? '00:00': $diaPonto['he100'])]);
                $totalAdicNot      = somarHorarios([$totalAdicNot,      $diaPonto['adicionalNoturno']]);
                $totalEspInd       = somarHorarios([$totalEspInd,       $diaPonto['esperaIndenizada']]);
                $totalSaldoPeriodo = somarHorarios([$totalSaldoPeriodo, $diaPonto['diffSaldo']]);
            }
            
            
            if($saldoAnterior != '00:00' && !empty($saldoAnterior)){
                $saldoFinal = somarHorarios([$saldoAnterior, $totalSaldoPeriodo]);
            }else{
                $saldoFinal = somarHorarios(['00:00', $totalSaldoPeriodo]);
            }
            $rows[] = [
                'motorista' => $motorista['enti_tx_nome'],
                'statusEndosso' => $endossado,
                'jornadaPrevista' => $totalJorPrev,
                'jornadaEfetiva' => $totalJorEfe,
                'he50' => $totalHE50,
                'he100' => $totalHE100,
                'adicionalNoturno' => $totalAdicNot,
                'esperaIndenizada' => $totalEspInd,
                'saldoAnterior' => $saldoAnterior,
                'saldoPeriodo' => $totalSaldoPeriodo,
                'saldoFinal' => $saldoFinal
            ];
        }
        // $saldoFinal = 0;
        foreach($rows as $row){
            $totalJorPrev      = somarHorarios([$totalJorPrev, $row['jornadaPrevista']]);
            $totalJorEfe       = somarHorarios([$totalJorEfe, $row['jornadaEfetiva']]);
            $totalHE50         = somarHorarios([$totalHE50, $row['he50']]);
            $totalHE100        = somarHorarios([$totalHE100, $row['he100']]);
            $totalAdicNot      = somarHorarios([$totalAdicNot, $row['adicionalNoturno']]);
            $totalEspInd       = somarHorarios([$totalEspInd, $row['esperaIndenizada']]);
            $saldoAnterior     = somarHorarios([$saldoAnterior, $row['saldoAnterior']]);
            $totalSaldoPeriodo = somarHorarios([$totalSaldoPeriodo, $row['saldoPeriodo']]);
            $saldoFinal        = somarHorarios([$saldoFinal, $row['saldoFinal']]);
        }
        
        $totais = [
                'jornadaPrevista' => $totalJorPrev,
                'JornadaEfetiva' => $totalJorEfe,
                'he50' => $totalHE50,
                'he100' => $totalHE100,
                'adicionalNoturno' => $totalAdicNot,
                'esperaIndenizada' => $totalEspInd,
                'saldoAnterior' => $saldoAnterior,
                'saldoPeriodo' => $totalSaldoPeriodo,
                'saldoFinal' => $saldoFinal
            ];
            
        $nomeEmpresa = mysqli_fetch_all(query("SELECT empr_tx_nome  FROM empresa WHERE 	empr_nb_id = $idEmpresa AND empr_tx_status != 'inativo'"), 
        MYSQLI_ASSOC);
        
        include "./relatorio_espelho_geral_oacdc.php";
        exit;
    }

    function index(){
        global $totalResumo, $CONTEX;
        
        cabecalho('Relatorio Geral de Espelho de Ponto');
        
        $extraEmpresa = '';
        $extraCampoData = '';
        if (!empty($_SESSION['user_nb_empresa']) && $_SESSION['user_tx_nivel'] != 'Administrador' && $_SESSION['user_tx_nivel'] != 'Super Administrador') {
            $extraEmpresa = " AND enti_nb_empresa = ".$_SESSION['user_nb_empresa'];
        }
        
        $c = [
                combo_net('Empresa*:', 'busca_empresa', ($_POST['busca_empresa']?? ''), 3, 'empresa', " ", $extraEmpresa),
                campo_data('Data Início:', 'busca_dataInicio', ($_POST['busca_dataInicio']?? ''), 2, $extraCampoData),
                campo_data('Data Fim:', 'busca_dataFim', ($_POST['busca_dataFim']?? ''), 2,$extraCampoData)
            ];
            
        $b = [
            '<button name="acao" id="criaRelatorio" type="button" onload="disablePrintButton()" class="btn btn-info">Imprimir Relatório</button>',
            ];

        abre_form('Filtro de Busca');
        linha_form($c);
        fecha_form($b);
        ?>
        <form name="form_imprimir_relatorio" method="post" target="_blank">
            <input type="hidden" name="acao" value="criar_relatorio">
            <input type="hidden" name="busca_empresa" value="">
            <input type="hidden" name="busca_dataInicio" value=""> 
            <input type="hidden" name="busca_dataFim" value=""> 
        </form>
        <script>
            window.onload = function() {
                document.getElementById('criaRelatorio').onclick = function() {
                    var valorEmpresa = document.getElementById('busca_empresa').value
                    var valorDataInicio = document.getElementById('busca_dataInicio').value
                    var valorDataFim = document.getElementById('busca_dataFim').value
                    document.form_imprimir_relatorio.busca_empresa.value = valorEmpresa;
                    document.form_imprimir_relatorio.busca_dataInicio.value = valorDataInicio;
                    document.form_imprimir_relatorio.busca_dataFim.value = valorDataFim;
                    document.form_imprimir_relatorio.submit();
                }
            }
        </script>
        
    <?php
        rodape();
    }
?>