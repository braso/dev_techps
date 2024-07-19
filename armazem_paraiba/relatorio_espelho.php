<?php
	include_once $_SERVER['DOCUMENT_ROOT'].($CONTEX['path'])."/conecta.php";
	include $_SERVER['DOCUMENT_ROOT'].($CONTEX['path'])."/csv_relatorio_espelho.php";
?>
<!DOCTYPE html>
<html lang="en">


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espelho de Ponto</title>
    <link rel="stylesheet" href="./css/endosso.css">

    <script>
    function imprimir() {
        // Abrir a caixa de diálogo de impressão
        window.print();
    }
    </script>
</head>

<body>    
    <script>
    function downloadCSV() {
        // Caminho do arquivo CSV no servidor
        var filePath = '<?="./arquivos/endosso_csv/$aEmpresa[empr_nb_id]/$aMotorista[enti_nb_id]/espelho-de-ponto.csv"?>'; // Substitua pelo caminho do seu arquivo

        // Cria um link para download
        var link = document.createElement('a');

        // Configurações do link
        link.setAttribute('href', filePath);
        link.setAttribute('download', '<?="espelho-de-ponto-$aMotorista[enti_tx_nome].csv"?>');

        // Adiciona o link ao documento
        document.body.appendChild(link);

        // Simula um clique no link para iniciar o download
        link.click();

        // Remove o link
        document.body.removeChild(link);
    }
    </script>


    <br>

    <div class="header">
        <img src="<?=$aEmpresa['empr_tx_logo']?>" alt="Logo Empresa Esquerda">
        <h1>Espelho de Ponto</h1>
        <div class="right-logo">
            <img src="<?=$CONTEX['path']?>/imagens/logo_topo_cliente.png" alt="Logo Empresa Direita">
        </div>
    </div>
    <div class="info">
        <table class="table-header">
            <tr class="company-info">
                <td style="padding-left: 12px; text-align: left;"><b>Empresa:</b> <?=$aEmpresa['empr_tx_nome']?></td>
                <td style="text-align: left;"><b>CNPJ:</b> <?=$aEmpresa['empr_tx_cnpj']?></td>
                <td colspan="2" style="text-align: left;"><b>End.</b>
                    <?="$enderecoEmpresa, $aEmpresa[cida_tx_nome]/$aEmpresa[cida_tx_uf], $aEmpresa[empr_tx_cep]"?>
                </td>
                <td style="text-align: left;"><b>Período:</b>
                    <?=date("d/m/Y", strtotime($endossoCompleto['endo_tx_de']))?> -
                    <?=date("d/m/Y", strtotime($endossoCompleto['endo_tx_ate']))?></td>
                <td style="text-align: left;"><b>Emissão Doc.:</b>
                    <?=date("d/m/Y H:i:s", strtotime($endossoCompleto['endo_tx_dataCadastro'])) . " (UTC-3)"?> </td>
            </tr>

            <tr class="employee-info">
                <td style="padding-left: 12px; text-align: left;"><b><?=$aMotorista['enti_tx_ocupacao']?>:</b> <?=$aMotorista['enti_tx_nome']?>
                </td>
                <td style="text-align: left;"><b>Função:</b> <?=$aMotorista['enti_tx_ocupacao']?></td>
                <td style="text-align: left;"><b>CPF:</b> <?=$aMotorista['enti_tx_cpf']?></td>
                <td style="text-align: left;"><b>Turno:</b> D.SEM/H: <?=$aMotorista['enti_tx_jornadaSemanal']?> FDS/H:
                    <?=$aMotorista['enti_tx_jornadaSabado']?> </td>
                <td style="text-align: left;"><b>Matrícula:</b> <?=$aMotorista['enti_tx_matricula']?></td>
                <td style="text-align: left;"><b>Admissão:</b> <?=data($aMotorista['enti_tx_admissao'])?></td>
            </tr>
        </table>
    </div>
    <table class="table" border="1">
        <thead>
            <tr>
                <th colspan="2">PERÍODO</th>
                <th colspan="4">REGISTROS DE JORNADA</th>
                <th colspan="4">INTERVALOS</th>
                <th colspan="3">JORNADA</th>
                <th colspan="5">APURAÇÃO DO CONTROLE DA JORNADA</th>
                <!-- <th>TRATAMENTO</th> -->
            </tr>
            <tr>
                <th>DATA</th>
                <th>DIA</th>
                <th>INÍCIO</th>
                <th>INÍCIO REF.</th>
                <th>FIM REF.</th>
                <th>FIM</th>
                <th>REFEIÇÃO</th>
                <th>ESPERA</th>
                <th>DESCANSO</th>
                <th>REPOUSO</th>
                <th>PREVISTA</th>
                <th>EFETIVA</th>
                <th>MDC</th>
                <th>INTERSTÍCIO</th>
                <th>HE 50%</th>
                <th>HE&nbsp;100%</th>
                <th>ADICIONAL NOT.</th>
                <th>ESPERA IND.</th>
                <th>MOTIVO</th>
                <th>SALDO</th>
            </tr>
        </thead>
        <tbody>
            <?php
				foreach ($aDia as $aDiaVez) {
					echo '<tr>';
					for ($j = 0; $j < 20; $j++){
						echo '<td>'.$aDiaVez[$j].'</td>';
					}
					echo '</tr>';
				}
			?>

        </tbody>
    </table>

    <div><b>TOTAL: <?=$diasEndossados?> dias</b></div>


    <table class="table-bottom">
        <tr>
            <td rowspan="2" style="width: 150px;">
                <table class="table-info">
                    <tr>
                        <td>Carga Horaria Prevista:</td>
                        <td>
                            <center><?=$totalResumo['jornadaPrevista']?></center>
                        </td>
                    </tr>
                    <tr>
                        <td>Carga Horaria Efetiva Realizada:</td>
                        <td>
                            <center><?=$totalResumo['diffJornadaEfetiva']?></center>
                        </td>
                    </tr>
                    <tr>
                        <td>Adicional Noturno:</td>
                        <td>
                            <center><?=$totalResumo['adicionalNoturno']?></center>
                        </td>
                    </tr>
                    <tr>
                        <td>Espera Indenizada:</td>
                        <td>
                            <center><?=$totalResumo['esperaIndenizada']?></center>
                        </td>
                    </tr>
                </table>

                <table class="table-info2">
                    <tr>
                        <td>Horas Extras (50%) - a pagar:</td>
                        <td>
                            <center><?=$totalResumo["he50_aPagar"]?></center>
                        </td>
                    </tr>
                    <tr>
                        <td>Horas Extras (100%) - a pagar:</td>
                        <td>
                            <center><?=$totalResumo["he100_aPagar"]?></center>
                        </td>
                    </tr>
                    <tr>
                        <td>Saldo Final (após pagamentos):</td>
                        <td>
                            <center><?=$totalResumo["saldoFinal"]?></center>
                        </td>
                    </tr>
                </table>

            <td rowspan="2" style="width: 150px;">
                <table class="table-legenda">
                    <tr>
                        <th colspan="2" style="text-align: center;">
                            <b>Legendas</b>
                        </th>
                    </tr>
                    <tr>
                        <td>
                            <center><b>I</b></center>
                        </td>
                        <td>
                            <center>Incluída Manualmente</center>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <center><b>P</b></center>
                        </td>
                        <td>
                            <center>Pré-Assinalada</center>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <center><b>T</b></center>
                        </td>
                        <td>
                            <center>Outras fontes de marcação</center>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <center><b>DSR</b></center>
                        </td>
                        <td>
                            <center>Descanso Semanal Remunerado e Abono </center>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <center><b>*</b></center>
                        </td>
                        <td>
                            <center>Registros excluídos manualmente </center>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <center><b>D+</b></center>
                        </td>
                        <td>
                            <center>Jornada terminada nos dias seguintes</center>
                        </td>
                    </tr>
                </table>
            </td>
            </td>

            <td>
                <table class="table-resumo">
                    <tr>
                        <td>Saldo Anterior</td>
                        <td><?=$totalResumo['saldoAnterior']?></td>
                        <td class="empty"></td>
                        <td>Saldo Período</td>
                        <td><?=$totalResumo['diffSaldo']?></td>
                        <td class="empty"></td>
                        <td>Saldo Atual</td>
                        <td><?=$totalResumo['saldoAtual']?></td>
                    </tr>
                </table>
            </td>

        </tr>
        <tr>
            <td>
                <div class="signature-block">
                    <center>
                        <p>___________________________________________________________</p>
                    </center>
                    <center>
                        <p>Responsável</p>
                    </center>
                    <center>
                        <p>Cargo</p>
                    </center>
                </div>
                <div class="signature-block">
                    <center>
                        <p>___________________________________________________________</p>
                    </center>
                    <center>
                        <p><?=$aMotorista['enti_tx_nome']?></p>
                    </center>
                    <center>
                        <p><?=$aMotorista['enti_tx_nivel']?></p>
                    </center>
                </div>
            </td>
        </tr>
        <tr>
            <td></td>
            <td></td>
            <td id="impressao"><b>Impressão Doc.:</b> <?=date("d/m/Y \T H:i:s") . "(UTC-3)"?></td>
        </tr>
    </table>
    <div id="exporta">
        <button id="btnImprimir" class="btn default" type="button" onclick="imprimir()">
            <img width="20" height="20" src="https://img.icons8.com/android/24/FFFFFF/print.png" alt="print"/> Imprimir</button>
        <button id="btnCsv" onclick="downloadCSV()">
            <img width="20" height="20" src="https://img.icons8.com/glyph-neue/64/FFFFFF/csv.png" alt="csv"/> Baixar CSV</button>
    </div>
    <div style="page-break-after: always;"></div>
</body>
</html>