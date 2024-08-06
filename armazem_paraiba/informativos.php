<?php
function bemVindo($usuario, $turnoAtual, $horaEntrada) {
?>
    <style>
        .portlet.light {
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
        }

        #boas-vindas {
            font-size: 16px;
        }

        p {
            text-align: justify !important;
            padding: 0px !important;
            border-top: none !important;
            border-bottom: none !important;
        }

        h4 {
            text-align: center;
        }

        table {
            text-align: center;
            margin: 0 auto;
        }
    </style>
    <div id='boas-vindas' class="portlet light">
        <p>Bem Vindo(a), <?= $usuario ?>. Período da <?= $turnoAtual ?> iniciado às <?= $horaEntrada ?>.
            <br>
            <br>
            Aqui você encontra informações relacionadas aos registros e apontamentos de espelho de ponto, endosso, não conformidades e tem
            acesso aos relatórios dos serviços contratados.
            <br>
            Em caso de dúvida, estamos sempre à sua disposição.
        </p>
        <h4><b>Informações importantes:</b></h4>
        <table class="table w-auto table-condensed flip-content table-hover compact">
            <tbody>
                <tr>
                    <th>Telefone: </th>
                    <td><a href='https://api.whatsapp.com/send?phone=5584981578492 '>(84) 98157-8492</a></td>
                </tr>
                <tr>
                    <th>Treinamento: </th>
                    <td><a href='mailto: treinamento@techps.com.br'>treinamento@techps.com.br</a></td>
                </tr>
                <tr>
                    <th>Suporte de sistemas: </th>
                    <td><a href='mailto:suporte@techps.com.br'>suporte@techps.com.br</a></td>
                </tr>
                <tr>
                    <th>Comercial: </th>
                    <td><a href='mailto:comercial@techps.com.br'>comercial@techps.com.br</a></td>
                </tr>
                <tr>
                    <th>Financeiro: </th>
                    <td><a href='mailto:financeiro@techps.com.br'>financeiro@techps.com.br</a></td>
                </tr>
                <tr>
                    <th>Administrativo: </th>
                    <td><a href='mailto:administrativo@techps.com.br'>administrativo@techps.com.br</a></td>
                </tr>
            </tbody>
        </table>
    </div>
<?php
}
?>