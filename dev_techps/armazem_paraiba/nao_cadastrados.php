<?php

include "conecta.php";

function motorista_nao_cadastrado()
{
    $sqlPonto = query("SELECT p.pont_tx_matricula, MAX(p.pont_tx_data) AS ultima_data
    FROM ponto p
    LEFT JOIN entidade e ON p.pont_tx_matricula = e.enti_tx_matricula
    WHERE e.enti_tx_nome IS NULL
    GROUP BY p.pont_tx_matricula
    ORDER BY ultima_data DESC");

    $pontos = mysqli_fetch_all($sqlPonto, MYSQLI_ASSOC);
    
    return $pontos;

}


function index()
{
    global $CACTUX_CONF;

    cabecalho('Matrículas Não Cadastrados');

    ?>
    <div class="col-md-5 col-sm-5" style="left: 410px;">
        <div class="portlet light ">
            <div class="portlet-body form">

                 <style>
                    table thead tr th:nth-child(4),
                    table thead tr th:nth-child(8),
                    table thead tr th:nth-child(12),
                    table td:nth-child(4),
                    table td:nth-child(8),
                    table td:nth-child(12) {
                        border-right: 3px solid #d8e4ef !important;
                    }
                </style>

                <div class="table-responsive">
                    <table
                        class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact'>

                        <thead>
                            <tr>
                                <th>MATRÍCULAS</th>
                                <?
                                 echo "<th>Ultima Data do Cadastro do Ponto</th>";
                                 echo "<th>Total de Matrículas = ".sizeof(motorista_nao_cadastrado())."</th>";
                                ?>
                        </thead>
                        </tr>
                        <?
                        $matriculas = motorista_nao_cadastrado();
                        ?>

                        <tbody>
                            <?
                            foreach ($matriculas as $valor) {
                                echo '<tr>';
                                echo '<td>' . $valor['pont_tx_matricula']. '</td>';
                                echo '<td>' . date("d-m-Y", strtotime($valor['ultima_data'])). '</td>';
                                echo '</tr>';
                            }
                            ?>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?
    rodape();

    
}

?>