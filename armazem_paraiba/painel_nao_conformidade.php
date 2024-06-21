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
					AND enti_nb_empresa = ".$_POST['empresa']."
					AND enti_tx_status != 'inativo'
				ORDER BY enti_tx_nome"
		);
		
        while ($aMotorista = carrega_array($sqlMotorista)) {
			      $counts = [];
            $rows = [];
            $inicioJornada = 0;
            $inicioRefeicao = 0;
            $fimRefeicao = 0;
            $fimJornada = 0;
            $diffRefeicao = 0;
            $diffJornada = 0;
            $jornadaPrevista = 0;
            $diffJornadaEfetiva = 0;
            $maximoDirecaoContinua = 0;
            $diffEspera = 0;
            $diffDescanso = 0;
            $diffRepouso = 0;

            for ($i = 1; $i <= $daysInMonth; $i++) {
                $dataVez = $_POST['busca_data']."-".str_pad($i, 2, 0, STR_PAD_LEFT);
                $aDetalhado = diaDetalhePonto($aMotorista['enti_tx_matricula'], $dataVez);

                if(strpos($aDetalhado['inicioJornada'] , 'fa-warning') !== false){
                    $inicioJornada += 1;
                }
                if(strpos($aDetalhado['inicioRefeicao'] , 'fa-warning') !== false){
                    $inicioRefeicao += 1;
                }
                if(strpos($aDetalhado['fimRefeicao'] , 'fa-warning') !== false){
                    $fimRefeicao += 1;
                }
                if(strpos($aDetalhado['fimJornada'] , 'fa-warning') !== false){
                    $fimJornada += 1;
                }
                if(strpos($aDetalhado['diffRefeicao'] , 'fa-warning') !== false){
                    $diffRefeicao += 1;
                }
                if(strpos($aDetalhado['diffEspera'] , 'fa-warning') !== false){
                    $diffEspera += 1;
                }
                if(strpos($aDetalhado['diffDescanso'] , 'fa-warning') !== false){
                    $diffDescanso += 1;
                }
                if(strpos($aDetalhado['diffRepouso'] , 'fa-warning') !== false){
                    $diffRepouso += 1;
                }
                if(strpos($aDetalhado['diffJornada'] , 'fa-warning') !== false){
                    $diffJornada += 1;
                }
                if(strpos($aDetalhado['jornadaPrevista'] , 'fa-warning') !== false){
                    $jornadaPrevista += 1;
                }
                if(strpos($aDetalhado['diffJornadaEfetiva'] , 'fa-warning') !== false){
                    $diffJornadaEfetiva += 1;
                }
                if(strpos($aDetalhado['maximoDirecaoContinua'] , 'fa-warning') !== false){
                    $maximoDirecaoContinua += 1;
                }
            }


            // $rows[$aMotorista['enti_tx_matricula']] = $counts;
            // $path = "./arquivos/paineis/nao_conformidade/$_POST[empresa]";
            // if(!is_dir($path)){
            //     mkdir($path,0700,true);
            // }
            // $fileName = 'nao_comformidade'.date('YmdHis').'.json';
            // $json = json_encode($rows,JSON_UNESCAPED_UNICODE);
            // file_put_contents($path.'/'.$fileName,$json);
        }
	}

    function index(){
        cabecalho('Painel Não Conformidade');

        if(empty($_POST['busca_data'])){
            $_POST['busca_data'] = date("Y-m");
        }

        // if(empty($_POST['empresa'])){
        //     $empresa = mysqli_fetch_all(
        //         query("SELECT empr_nb_id, empr_tx_nome, empr_tx_logo FROM `empresa` WHERE empr_tx_status != 'inativo' ORDER BY empr_tx_nome ASC;"),
        //         MYSQLI_ASSOC
        //     );
        // }

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
            combo_net('Empresa:*','empresa',($_POST['empresa']?? ''),4,'empresa', ''),
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
            botao("Buscar", 'index', '', '', '', '', 'btn btn-success'),
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