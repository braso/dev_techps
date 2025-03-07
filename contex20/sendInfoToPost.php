<?php

    $interno = true; //Utilizado no conecta.php para reconhecer se quem está tentando acessar é uma tela ou uma query interna.
    include_once "../..".$_GET["path"]."/conecta.php";

    echo json_encode("sendInfoToPost.php");