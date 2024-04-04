<?php
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

include "funcoes_ponto.php";

function index() {
    global $totalResumo, $CONTEX;

    cabecalho('Relatorio Geral de Espelho de Ponto');

    $c = [
        combo_net('Empresa:','empresa','',4,'empresa', '')
    ];

    $botao_imprimir =
			'<button class="btn default" type="button" onclick="imprimir()">Imprimir</button >
					<script>
						function imprimir() {
							// Abrir a caixa de diálogo de impressão
							window.print();
						}
					</script>';

    $b = [
       botao("Buscar", 'index', $_POST['empresa']?? '', '', '', 1,'btn btn-info'),
       $botao_imprimir
    ];

    abre_form('Filtro de Busca');
    linha_form($c);
    fecha_form($b);
    
    if (isset($_POST['empresa']) && !empty($_POST['empresa'])) {
         $idEmpresa = $_POST['empresa'];
         $aEmpresa = mysqli_fetch_all(query("SELECT empr_tx_logo FROM `empresa` WHERE empr_tx_Ehmatriz = 'sim' AND empr_nb_id = $idEmpresa"), MYSQLI_ASSOC);
         include_once 'painel_empresa.php';
        
    }else{
        $aEmpresa = mysqli_fetch_all(query("SELECT empr_tx_logo FROM `empresa` WHERE empr_tx_Ehmatriz = 'sim'"), MYSQLI_ASSOC);
        include_once 'painel_empresas.php';
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
                    .portlet.light>.portlet-title {
                        border-bottom: none;
                        margin-bottom: 0px;
                    }
                    .caption{
                        padding-top: 0px;
                        margin-left: -50px !important;
                        padding-bottom: 0px;
                    }
                    .emissao{
                        text-align: left;
                        padding-left: 300px !important;
                        position: absolute;
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
                    padding-left: 350px;
                    position: absolute;
                }
        </style>

    <?php
    
    rodape();
}
?>