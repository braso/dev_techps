<?
    global $CONTEX;
	include_once $_SERVER['DOCUMENT_ROOT'].($CONTEX['path'])."/conecta.php";
	// include $_SERVER['DOCUMENT_ROOT'].($CONTEX['path'])."/csv_relatorio_espelho.php";
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espelho de Ponto Relatório Geral</title>
    <link rel="stylesheet" href="./css/endosso.css">
    <style>
    body {
        font-size: 11px !important;
    }
    .totais {
            background-color: #ffffcc;
    }
    
    .titulos, .conteudo {
            background-color: #e6ffff !important;
    }

    </style>
</head>
<body>
    <br>
    <table class="table" border="1">
        <thead>
            <tr class="totais">
                <th colspan="1">PERÍODO:<?= $dataInicio. ' - ' .$dataFim ?></th>
                <th colspan="1"></th>
                <th colspan="1"><?= $totais["jornadaPrevista"] ?></th>
                <th colspan="1"><?= $totais["jornadaEfetiva"] ?></th>
                <th colspan="1"><?= $totais["he50"] ?></th>
                <th colspan="1"><?= $totais["he100"] ?></th>
                <th colspan="1"><?= $totais["adicionalNoturno"] ?></th>
                <th colspan="1"><?= $totais["esperaIndenizada"] ?></th>
                <th colspan="1"><?= $totais["saldoAnterior"] ?></th>
                <th colspan="1"><?= $totais["saldoPeriodo"] ?></th>
                <th colspan="1"><?= $totais["saldoFinal"] ?></th>
            </tr>
            <tr class="titulos">
                <th>Unidade - <?= $nomeEmpresa[0]['empr_tx_nome'] ?></th>
                <th>Status Endosso</th>
                <th>Jornada Prevista</th>
                <th>Jornada Efetiva</th>
                <th>HE 50%</th>
                <th>HE 100%</th>
                <th>Adicional Noturno</th>
                <th>ESPERA INDENIZADA</th>
                <th>Saldo Anterior</th>
                <th>Saldo Periodo</th>
                <th>Saldo Final</th>
            </tr>
        </thead>
        <tbody>
            <?
            foreach ($rows as $row) {
                echo '<tr class="conteudo">';
                echo '<td>'. $row['motorista']                                                      .'</td>';
                echo '<td>'. $row['statusEndosso']                                                  .'</td>';
                echo '<td>'. ($row['jornadaPrevista'] === "00:00" ? "" : $row['jornadaPrevista'])   .'</td>';
                echo '<td>'. ($row['jornadaEfetiva'] === "00:00" ? "" : $row['jornadaEfetiva'])     .'</td>';
                echo '<td>'. ($row['he50'] === "00:00" ? "" : $row['he50'])                         .'</td>';
                echo '<td>'. ($row['he100'] === "00:00" ? "" : $row['he100'])                       .'</td>';
                echo '<td>'. ($row['adicionalNoturno'] === "00:00" ? "" : $row['adicionalNoturno']) .'</td>';
                echo '<td>'. ($row['esperaIndenizada'] === "00:00" ? "" : $row['esperaIndenizada']) .'</td>';
                echo '<td>'. $row['saldoAnterior']                                                  .'</td>';
                echo '<td>'. $row['saldoPeriodo']                                                   .'</td>';
                echo '<td>'. $row['saldoFinal']                                                     .'</td>';
				echo '</tr>';
			}
            ?>
        </tbody>
    </table>
</body>
</html>