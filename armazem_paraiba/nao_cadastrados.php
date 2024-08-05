<?php

    include "conecta.php";

    function index(){

        cabecalho("Matrículas Não Cadastrados");

        $matriculas = mysqli_fetch_all(
            query(
                "SELECT p.pont_tx_matricula, MAX(p.pont_tx_data) AS ultima_data FROM ponto p
                    LEFT JOIN entidade e ON p.pont_tx_matricula = e.enti_tx_matricula
                    WHERE e.enti_tx_nome IS NULL
                    GROUP BY p.pont_tx_matricula
                    ORDER BY ultima_data DESC"
            ),
            MYSQLI_ASSOC
        );
        $total = sizeof($matriculas);

        $rows = "";
        foreach ($matriculas as $valor) {
            $rows .= 
                "<tr>
                    <td style='text-align: center;'>".$valor["pont_tx_matricula"]."</td>
                    <td style='text-align: center;'>".date("d/m/Y", strtotime($valor["ultima_data"]))."</td>
                </tr>";
        }

        echo 
            "<div class='content' style='
                width: -webkit-fill-available;
                padding-left: 25%;
                padding-right: 25%;
            '>
                <div class='portlet light '>
                    <div class='portlet-body form'>

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

                        <div class='table-responsive'>
                            <table
                                class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact'>
                                <p><b>Total de Matrículas = ".$total."</b></p>
                                <thead>
                                    <tr>
                                        <th>MATRÍCULAS</th>
                                        <th>Ultima Data do Cadastro do Ponto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                ".$rows."
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>"
        ;
        rodape();

        
    }