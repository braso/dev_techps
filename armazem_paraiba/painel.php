<?php
    /* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/

    include 'painel_empresas.php';
    include 'painel_empresa.php';
    include "funcoes_ponto.php";
   

    function index() {
        global $totalResumo, $CONTEX;

        if(array_key_exists('atualizar', $_POST) && !empty($_POST['atualizar'])){
            echo '<script>alert("Atualizando os painéis, aguarde um pouco ")</script>';
			ob_flush();
			flush();
			criar_relatorio($_POST['busca_data']);
        }
        
        if(empty($_POST['busca_data'])){
            $_POST['busca_data'] = date("Y-m");
        }

        	// Obtenha o primeiro dia do mês
        $dataInicio = new DateTime($_POST['busca_data']  . '-01');
        $dataInicioFormatada = $dataInicio->format('d/m/Y');
        $dataInicio = $dataInicio->format('Y-m-d');
        // Obtenha o último dia do mês
        $dataFim = new DateTime($_POST['busca_data']  . '-01');
        $dataFim->modify('last day of this month');
        $dataFimFormatada = $dataFim->format('d/m/Y');
        $dataFim = $dataFim->format('Y-m-d');

        $dateParts = explode('-',$_POST['busca_data']);
        $monthNum = $dateParts[1];
        $year = $dateParts[0];

        $monthNames = array(
            '01' => 'Janeiro',
            '02' => 'Fevereiro',
            '03' => 'Março',
            '04' => 'Abril',
            '05' => 'Maio',
            '06' => 'Junho',
            '07' => 'Julho',
            '08' => 'Agosto',
            '09' => 'Setembro',
            '10' => 'Outubro',
            '11' => 'Novembro',
            '12' => 'Dezembro'
        );

        
        $monthName = $monthNames[$monthNum];

        cabecalho('Relatório Final de Endosso');

        $texto = "<div style=''><b>Periodo da Busca:</b> $monthName de $year</div>";
        //position: absolute; top: 101px; left: 420px;
        $c = [
            combo_net('Empresa:','empresa',$_POST['empresa']?? '',4,'empresa', ''),
            campo_mes('Data:','busca_data',(!empty($_POST['busca_data'])?$_POST['busca_data'] : ''), 2),
            $texto,
        ];

        $botao_imprimir =
                '<button class="btn default" type="button" onclick="imprimir()">Imprimir</button >
                        <script>
                            function imprimir() {
                                // Abrir a caixa de diálogo de impressão
                                window.print();
                            }
                        </script>';
        $botaoCsv = "<button id='btnCsv' class='btn btn-success' style='background-color: green !important;' onclick='downloadCSV()'>Baixar CSV</button>";
        
        if (!empty($_SESSION['user_tx_nivel']) && is_int(strpos($_SESSION['user_tx_nivel'], 'Administrador'))) {
            $botaoAtualizarPainel = 
            '<a class="btn btn-warning" onclick="atualizarPainel()"> Atualizar Painel </a>';
        }

        if (isset($_POST['empresa']) && !empty($_POST['empresa'])) {
            $botao_volta = "<button class='btn default' type='button' onclick='setAndSubmit(\"\")'>Voltar</button>";
        }
        
        $b = [
            botao("Buscar", 'index', '', '', '', '', 'btn btn-success'),
            $botao_imprimir,
            $botaoCsv,
            $botao_volta,
            $botaoAtualizarPainel
        ];
        
        abre_form('Filtro de Busca');
        linha_form($c);
        fecha_form($b);
        
        if (isset($_POST['empresa']) && !empty($_POST['empresa']) && isset($_POST['busca_data']) && !empty($_POST['busca_data'])) {
            $idEmpresa = $_POST['empresa'];
            $aEmpresa = mysqli_fetch_all(query("SELECT empr_tx_logo FROM `empresa` WHERE empr_tx_Ehmatriz = 'sim' AND empr_nb_id = $idEmpresa"), MYSQLI_ASSOC);
            empresa($aEmpresa,$idEmpresa);
        }else{
            $aEmpresa = mysqli_fetch_all(query("SELECT empr_tx_logo FROM `empresa` WHERE empr_tx_Ehmatriz = 'sim'"), MYSQLI_ASSOC);
            empresas($aEmpresa);
        }
        ?>
            <style>

            @media print {
                        body {
                            margin: 1cm;
                            margin-right: 0cm; /* Ajuste o valor conforme necessário para afastar do lado direito */
                            transform: scale(1.0);
                            transform-origin: top left;
                        }
                    
                        @page {
                            size: A4 landscape;
                            margin: 1cm;
                        }
                        #tituloRelatorio{
                            /*font-size: 2px !important;*/
                            /*padding-left: 200px;*/
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            margin-bottom: -50px !important;
                        }

                        div:nth-child(6) > div,
                        body > div.scroll-to-top{
                            display: none !important;
                        }
                        body > div.page-container > div > div.page-content > div > div > div > div > div:nth-child(3){
                            display: none;
                        }
                        .portlet-body.form .table-responsive {
                            overflow-x: visible !important;
                            margin-left: -50px !important;
                        }
                        #pdf2htmldiv > div{
                            padding: 88px 20px 15px !important;
                        }
                        /* .portlet.light>.portlet-title {
                            border-bottom: none;
                            margin-bottom: 0px;
                        } */
                        body > div.page-container > div > div.page-content > div > div > div > div > div:nth-child(7){
                            display: none !important;
                        }
                        .caption{
                            padding-top: 0px;
                            margin-left: -50px !important;
                            padding-bottom: 0px;
                        }
                        .emissao{
                            text-align: left;
                            margin-left: 25px !important;
                            position: absolute;
                        }
                        .porcentagenEndo{
                            box-shadow: 0 0 0 1000px #66b3ff inset !important;
                        }
                        .porcentagenNaEndo{
                            box-shadow: 0 0 0 1000px #ff471a inset !important;
                        }
                        .porcentagenEndoPc{
                            box-shadow: 0 0 0 1000px #ffff66 inset !important;
                        }
                        thead tr.totais th {
                            box-shadow: 0 0 0 1000px #ffe699 inset !important; /* Cor para impressão */
                        }
                        thead tr.titulos th {
                            box-shadow: 0 0 0 1000px #99ccff inset !important; /* Cor para impressão */
                        }
                        .porcentagenMeta{
                            box-shadow: 0 0 0 1000px #66b3ff inset !important;
                        }
                        .porcentagenPosit{
                            box-shadow: 0 0 0 1000px #00b33c inset !important;
                        }
                        .porcentagenNegat{
                            box-shadow: 0 0 0 1000px #ff471a inset !important;
                        }
                        div:nth-child(10) > div{
                        padding: 75px 20px 15px !important;
                       }
                }

                    table thead tr th:nth-child(3),
                    table thead tr th:nth-child(7),
                    table thead tr th:nth-child(11),
                    table td:nth-child(3),
                    table td:nth-child(7),
                    table td:nth-child(11) {
                        border-right: 3px solid #d8e4ef !important;
                    }
                    .th-align {
                        text-align: center; /* Define o alinhamento horizontal desejado, pode ser center, left ou right */
                        vertical-align: middle !important; /* Define o alinhamento vertical desejado, pode ser top, middle ou bottom */
                        
                    }
                    .emissao{
                        text-align: left;
                        padding-left: 63%;
                        position: absolute;
                    }
            </style>
            <form name="myForm" method="POST" action="<?= htmlspecialchars(basename($_SERVER["PHP_SELF"])); ?>">
                <input type="hidden" name="empresa" id="empresa">
                <input type="hidden" name="busca_data" id="busca_data">
            </form>
            <form name="formularioAtualizarPainel" method="POST" action="<?= htmlspecialchars(basename($_SERVER["PHP_SELF"])); ?>">
                <input type="hidden" name="atualizar" id="atualizar">
                <input type="hidden" name="busca_data" id="busca_dataAtualizar">
            </form>

            <script>
                 function setAndSubmit(empresa) {
                    document.myForm.empresa.value = empresa;
                    document.myForm.busca_data.value = document.getElementById('busca_data').value;
                    document.myForm.submit();
                 }
                function atualizarPainel() {
                    document.formularioAtualizarPainel.busca_dataAtualizar.value = document.getElementById("busca_data").value;
                    document.formularioAtualizarPainel.atualizar.value = "atualizar";
                    document.formularioAtualizarPainel.submit();
                }
            </script>

        <?php
        
        rodape();
    }
?>