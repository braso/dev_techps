<?php

include "conecta.php";

function motorista_nao_cadastrado()
{
    $sqlPonto = query("SELECT DISTINCT p.pont_tx_matricula FROM ponto p LEFT JOIN entidade e ON p.pont_tx_matricula = e.enti_tx_matricula WHERE e.enti_tx_nome IS NULL;");

    $pontos = mysqli_fetch_all($sqlPonto, MYSQLI_ASSOC);
    
    // var_dump($pontos);
    // die();
    
    return $pontos;
    

    // $motoristas = [];


    // foreach ($pontos as $valor) {

    //     $sqlMotorista = query("SELECT enti_tx_matricula FROM entidade WHERE enti_tx_matricula = " . $valor['pont_tx_matricula']);
    //     $aMotorista = carrega_array($sqlMotorista);

    //     if ($aMotorista == null) {

    //         $motoristas[] = $valor['pont_tx_matricula'];

    //     }
    // }


    // return array_unique($motoristas);

}


function index()
{
    global $CACTUX_CONF;

    cabecalho('Matriculas Não Cadastrados');

    ?>
    <div class="col-md-3 col-sm-3" style="left: 500px;">
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
                                 echo "<th>Total = ".sizeof(motorista_nao_cadastrado())."</th>"
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