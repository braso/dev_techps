<?php
	include "funcoes_ponto.php"; // conecta.php importado dentro de funcoes_ponto	

	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/

	function totalNaoConformidade(){

		
		$date = new DateTime($_POST['busca_data']);
		$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $date->format('m'), $date->format('Y'));
		$sqlMotorista = query(
			"SELECT * FROM entidade
				WHERE enti_tx_ocupacao IN ('Motorista', 'Ajudante')
					AND enti_nb_empresa = ".$_POST['busca_empresa']."
					AND enti_tx_status != 'inativo'
				ORDER BY enti_tx_nome"
		);
		var_dump($sqlMotorista);
		for ($i = 1; $i <= $daysInMonth; $i++) {
			$dataVez = $_POST['busca_data']."-".str_pad($i, 2, 0, STR_PAD_LEFT);
			var_dump($dataVez);
		}
		die();
		// $aDetalhado = diaDetalhePonto($aMotorista['enti_tx_matricula'], $dataVez);
		// for($f = 0; $f < count($aDia); $f++){
		// 	$keys = array_keys($aDia[$f]);
		// 	$hasUnconformities = false;
		// 	foreach($keys as $key){
		// 		if(strpos($aDia[$f][$key], 'fa-warning') !== false){
		// 			$hasUnconformities = true;
		// 			$counts['naoConformidade'] += substr_count($aDia[$f][$key], 'fa-warning');
		// 		}
		// 	}
	}

    function index(){
        cabecalho('Painel Não Conformidade');

        if(empty($_POST['busca_data'])){
            $_POST['busca_data'] = date("Y-m");
        }

        // 	// Obtenha o primeiro dia do mês
        // $dataInicio = new DateTime($_POST['busca_data']  . '-01');
        // $dataInicioFormatada = $dataInicio->format('d/m/Y');
        // $dataInicio = $dataInicio->format('Y-m-d');
        // // Obtenha o último dia do mês
        // $dataFim = new DateTime($_POST['busca_data']  . '-01');
        // $dataFim->modify('last day of this month');
        // $dataFimFormatada = $dataFim->format('d/m/Y');
        // $dataFim = $dataFim->format('Y-m-d');

        // $dateParts = explode('-',$_POST['busca_data']);
        // $monthNum = $dateParts[1];
        // $year = $dateParts[0];

        // $monthNames = array(
        //     '01' => 'Janeiro',
        //     '02' => 'Fevereiro',
        //     '03' => 'Março',
        //     '04' => 'Abril',
        //     '05' => 'Maio',
        //     '06' => 'Junho',
        //     '07' => 'Julho',
        //     '08' => 'Agosto',
        //     '09' => 'Setembro',
        //     '10' => 'Outubro',
        //     '11' => 'Novembro',
        //     '12' => 'Dezembro'
        // );

        
        // $monthName = $monthNames[$monthNum];

		// $texto = "<div style=''><b>Periodo da Busca:</b> $monthName de $year</div>";
        $c = [
            combo_net('Empresa:*','empresa',($_POST['busca_empresa']?? ''),4,'empresa', ''),
            campo_mes('Data:',     'busca_data',      (!empty($_POST['busca_data'])?      $_POST['busca_data']     : ''), 2),
            // $texto,
        ];

		// $botao_imprimir =
        //         '<button class="btn default" type="button" onclick="imprimir()">Imprimir</button >
        //                 <script>
        //                     function imprimir() {
        //                         // Abrir a caixa de diálogo de impressão
        //                         window.print();
        //                     }
        //                 </script>';
        // $botaoCsv = "<button id='btnCsv' class='btn btn-success' style='background-color: green !important;' onclick='downloadCSV()'>Baixar CSV</button>";
        
        // if (!empty($_SESSION['user_tx_nivel']) && is_int(strpos($_SESSION['user_tx_nivel'], 'Administrador'))) {
        //     $botaoAtualizarPainel = 
        //     '<a class="btn btn-warning" onclick="atualizarPainel()"> Atualizar Painel </a>';
        // }

        // if (isset($_POST['empresa']) && !empty($_POST['empresa'])) {
        //     $botao_volta = "<button class='btn default' type='button' onclick='setAndSubmit(\"\")'>Voltar</button>";
        // }
        
        $b = [
        botao("Buscar", 'index', '', '', '', 1,'btn btn-info'),
        // $botao_imprimir,
        // $botaoCsv,
        // $botao_volta,
        // $botaoAtualizarPainel
        ];
		abre_form('Filtro de Busca');
		linha_form($c);
		fecha_form($b);
		
		totalNaoConformidade();

		rodape();
    }

?>