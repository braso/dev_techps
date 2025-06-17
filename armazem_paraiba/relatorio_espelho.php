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
            setTimeout(() => {
                window.print();
            }, 500);
        }
    </script>
</head>

<body>    
    <script>
    function downloadCSV(idMotorista, nomeMotorista) {
        // Caminho do arquivo CSV no servidor
        var filePath = './arquivos/endosso_csv/'+<?=$aEmpresa["empr_nb_id"]?>+'/'+idMotorista+'/espelho-de-ponto.csv'; // Substitua pelo caminho do seu arquivo

        // Cria um link para download
        var link = document.createElement('a');

        // Configurações do link
        link.setAttribute('href', filePath);
        link.setAttribute('download', 'espelho-de-ponto-'+nomeMotorista+'.csv');

        // Adiciona o link ao documento
        document.body.appendChild(link);

        // Simula um clique no link para iniciar o download
        link.click();

        // Remove o link
        document.body.removeChild(link);
    }
    </script>


    <br>
    <div class="relatorio">
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
                </tr>

                <tr class="employee-info">
                    <td style="padding-left: 12px; text-align: left;"><b>Nome:</b> <?=$motorista['enti_tx_nome']?>
                    </td>
                    <td style="text-align: left;"><b>Função:</b> <?=$motorista['enti_tx_ocupacao']?></td>
                    <td style="text-align: left;"><b>CPF:</b> <?=$motorista['enti_tx_cpf']?></td>
                    <td style="text-align: left;"><b>Turno:</b> D.SEM/H: <?=$motorista['enti_tx_jornadaSemanal']?> FDS/H:
                        <?=$motorista['enti_tx_jornadaSabado']?> </td>
                    <td style="text-align: left;"><b>Matrícula:</b> <?=$motorista['enti_tx_matricula']?></td>
                    <td style="text-align: left;"><b>Admissão:</b> <?=data($motorista['enti_tx_admissao'])?></td>
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
                    <th>HE <?=$motorista["enti_tx_percHESemanal"]?>%</th>
                    <th>HE&nbsp;<?=$motorista["enti_tx_percHEEx"]?>%</th>
                    <th>ADICIONAL NOT.</th>
                    <th>ESPERA IND.</th>
                    <th>MOTIVO</th>
                    <th>SALDO</th>
                </tr>
            </thead>
            <tbody>
                <?php
                        // dd( $aDia,false);
                    foreach ($aDia as $aDiaVez) {
                        if(strpos($aDiaVez[10], "00:00") !== false){
                            $aDiaVez[10] = "";
                        }
                        echo '<tr>';
                        for ($j = 0; $j < count($aDiaVez); $j++){
                            echo '<td>'.$aDiaVez[$j].'</td>';
                        }
                        echo '</tr>';
                    }
                ?>

            </tbody>
        </table>

        <div style="display: flex; justify-content: space-between;">
            <div><b>TOTAL: <?=$qtdDiasEndossados?> dias</b></div>
            <div><b>Criação Doc.:</b> <?=date("d/m/Y H:i:s", strtotime($endossoCompleto['endo_tx_dataCadastro']))?> (UTC-3)</div>
        </div>



        <table class="table-bottom-new">
            <thead>
                <tr>
                    <th  style="width: 240px"></th>
                    <th></th>
                    <th class="space"></th>
                    <th colspan="2" class="bordered">Legendas</th>
                    <th class="space"></th>
                    <th style="width: 250px"></th>
                    <th style="width: 75px"></th>
                    <th class="space"></th>
                    <th style="width: 250px"></th>
                    <th style="width: 75px"></th>
                    <th class="space"></th>
                    <th style="width: 250px"></th>
                    <th style="width: 75px"></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="bordered">Carga Horaria Prevista:</td>
                    <td class="bordered">
                        <center><?=$totalResumo['jornadaPrevista']?></center>
                    </td>
                    <td></td>
                    <td class="bordered">
                        <center><b>I</b></center>
                    </td>
                    <td class="bordered">
                        <center>Incluída Manualmente</center>
                    </td>
                    <td></td>
                    <td class="bordered">Saldo Anterior</td>
                    <td class="bordered"><?=$totalResumo['saldoAnterior']?></td>
                    <td></td>
                    <td class="bordered">Saldo Período</td>
                    <td class="bordered"><?=$totalResumo['diffSaldo']?></td>
                    <td></td>
                    <td class="bordered">Saldo Bruto</td>
                    <td class="bordered"><?=$totalResumo['saldoBruto']?></td>
                </tr>

                <tr>
                    <td class="bordered">Carga Horaria Efetiva Realizada:</td>
                    <td class="bordered">
                        <center><?=$totalResumo['diffJornadaEfetiva']?></center>
                    </td>
                    <td></td>
                    <td class="bordered">
                        <center><b>P</b></center>
                    </td>
                    <td class="bordered">
                        <center>Pré-Assinalada</center>
                    </td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>

                <tr>
                    <td class="bordered">Adicional Noturno:</td>
                    <td class="bordered">
                        <center><?=$totalResumo['adicionalNoturno']?></center>
                    </td>
                    <td></td>
                    <td class="bordered">
                        <center><b>T</b></center>
                    </td>
                    <td class="bordered">
                        <center>Outras fontes de marcação</center>
                    </td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>

                <tr>
                    <td class="bordered">Espera Indenizada:</td>
                    <td class="bordered">
                        <center><?=$totalResumo['esperaIndenizada']?></center>
                    </td>
                    <td></td>
                    <td class="bordered">
                        <center><b>DSR</b></center>
                    </td>
                    <td class="bordered">
                        <center>Descanso Semanal Remunerado e Abono </center>
                    </td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>

                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="bordered">
                        <center><b>*</b></center>
                    </td>
                    <td class="bordered">
                        <center>Registros excluídos manualmente </center>
                    </td>
                    <td></td>
                    <td colspan="2">
                        <hr/>
                    </td>
                    <td></td>
                    <td colspan="2">
                        <hr/>
                    </td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>

                <tr>
                    <td class="bordered">
                        Horas Extras (<?=$motorista["enti_tx_percHESemanal"]?>%) - a pagar:
                    </td>
                    <td class="bordered">
                        <center><?=$totalResumo["HESemanalAPagar"]?></center>
                    </td>
                    <td></td>
                    <td class="bordered">
                        <center><b>D+</b></center>
                    </td>
                    <td class="bordered">
                        <center>Jornada terminada nos dias seguintes</center>
                    </td>
                    <td></td>
                    <td colspan="2">
                        <center>
                            <p>Responsável</p>
                        </center>
                    </td>
                    <td></td>
                    <td colspan="2">
                        <center>
                            <p><?=$motorista['enti_tx_nome']?></p>
                        </center>
                    </td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>

                <tr>
                    <td class="bordered">
                        Horas Extras (<?=$motorista["enti_tx_percHEEx"]?>%) - a pagar:
                    </td>
                    <td class="bordered">
                        <center><?=$totalResumo["HEExAPagar"]?></center>
                    </td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td colspan="2">
                        <center>
                            <p><?=$motorista['enti_tx_ocupacao']?></p>
                        </center>
                    </td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>

                <tr>
                    <td class="bordered">
                        Saldo Final (após pagamentos):
                    </td>
                    <td class="bordered">
                        <center><?=$totalResumo["saldoFinal"]?></center>
                    </td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td colspan="3" id="impressao"><b>Emissão Doc.:</b> <?=date("d/m/Y H:i:s")." (UTC-3)"?></td>
                </tr>

            </tbody>
        </table>
        <div id='exporta'>
            <?= implode("", $botoes) ?>
        </div>
    </div>
    <div style="page-break-after: always;"></div>
</body>
</html>