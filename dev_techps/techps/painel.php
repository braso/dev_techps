<?php
include "conecta.php";

function motorista_nao_cadastrado()
{
    $sqlPonto = query("SELECT pont_tx_matricula FROM ponto WHERE pont_tx_matricula");

    $pontos = mysqli_fetch_all($sqlPonto, MYSQLI_ASSOC);


    $motoristas = [];


    foreach ($pontos as $valor) {

        $sqlMotorista = query("SELECT enti_tx_matricula FROM entidade WHERE enti_tx_matricula = " . $valor[pont_tx_matricula]);
        $aMotorista = carrega_array($sqlMotorista);

        if ($aMotorista == null) {

            $motoristas[] = $valor[pont_tx_matricula];

        }
    }

    // return array_unique($motoristas);

}

function index()
{
    global $CACTUX_CONF;

    cabecalho('Painel');

    echo '
    <style>
    .circular-progress{
        left: 30px;
        position: relative;
        height: 150px;
        width: 150px;
        border-radius: 50% !important;
        background: conic-gradient(#7d2ae8 3.6deg, #ededed 0deg);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .circular-progress::before{
        left: 20px;
        content: "";
        position: absolute;
        height: 110px;
        width: 110px;
        border-radius: 50%
        ;
        background-color: #fff;
    }
    .progress-value{
        position: relative;
        font-size: 20px;
        font-weight: 600;
        color: #7d2ae8;
    }
    </style>';
    echo '<div class="col-md-6 col-sm-12">';
    echo '
    <div class="container" style="box-shadow: 0 0 30px black; margin-right: 0px;height: 250px; width: 252px; margin-left: 0px;">
    <div>
    <h5>Motoristas com Não Conformidade</h5>
    </div>
    <br>
    <div class="circular-progress">
    <span class="progress-value">0/100
    </span>
    <script>
    let circularProgress = document.querySelector(".circular-progress"),
      progressValue = document.querySelector(".progress-value");
    let progressStartValue = 0,
      progressEndValue = 50,
      speed = 100;
    
    let progress = setInterval(() => {
      progressStartValue++;
      progressValue.textContent = `${progressStartValue}/100`
      circularProgress.style.background = `conic-gradient(#7d2ae8 ${progressStartValue * 3.6}deg, #ededed 0deg)`
    if(progressStartValue == progressEndValue){
      clearInterval(progress);
    }}, speed);
    </script>
    </div>
    </div>';
    echo '</div>';

    rodape();
}
?>

box-shadow: rgba(50, 50, 93, 0.25) 0px 30px 60px -12px, rgba(0, 0, 0, 0.3) 0px 18px 36px -18px;