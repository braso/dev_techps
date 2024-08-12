<?php

function bemVindo($usuario, $turnoAtual, $horaEntrada) {
    echo"<style>
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
    </style>";
    echo "
    <div id='boas-vindas' class='portlet light'>
        <p>Bem Vindo(a), $usuario.</p>
        <p>Período da $turnoAtual iniciado às $horaEntrada.</p>
        <p>Aqui você encontra informações relacionadas aos registros e apontamentos de espelho de ponto, endosso, não conformidades e tem
        acesso aos relatórios dos serviços contratados.</p>
        <p>Em caso de dúvida, estamos sempre à sua disposição.</p>

        <h4><b>Informações importantes:</b></h4>
        " . gerarTabelaInformacoes() . "
    </div>";
}

function gerarTabelaInformacoes() {
    $contatos = [
        "Telefone" => "<a href='https://api.whatsapp.com/send?phone=5584981578492' target='_blank'>(84) 98157-8492</a>",
        "Treinamento" => "<a href='mailto:treinamento@techps.com.br' target='_blank'>treinamento@techps.com.br</a>",
        "Suporte de sistemas" => "<a href='mailto:suporte@techps.com.br' target='_blank'>suporte@techps.com.br</a>",
        "Comercial" => "<a href='mailto:comercial@techps.com.br' target='_blank'>comercial@techps.com.br</a>",
        "Financeiro" => "<a href='mailto:financeiro@techps.com.br' target='_blank'>financeiro@techps.com.br</a>",
        "Administrativo" => "<a href='mailto:administrativo@techps.com.br' target='_blank'>administrativo@techps.com.br</a>"
    ];

    $html = "<table class='table w-auto table-condensed flip-content table-hover compact'>
                <tbody>";
    
    foreach ($contatos as $area => $link) {
        $html .= "
                    <tr>
                        <th>$area: </th>
                        <td>$link</td>
                    </tr>";
    }
    
    $html .= "  </tbody>
             </table>";

    return $html;
}
?>
