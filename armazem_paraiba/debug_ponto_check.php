<?php
    include "conecta.php";
    
    // Check Matrícula 5
    $matricula = '5';
    $matriculaExiste = mysqli_fetch_assoc(query(
        "SELECT enti_nb_id, enti_tx_matricula, enti_tx_nome FROM entidade 
            WHERE enti_tx_matricula = '{$matricula}'
            LIMIT 1;"
    ));
    
    echo "Matrícula '{$matricula}': ";
    if($matriculaExiste){
        echo "Encontrada (ID: " . $matriculaExiste['enti_nb_id'] . ", Nome: " . $matriculaExiste['enti_tx_nome'] . ")\n";
    } else {
        echo "NÃO Encontrada\n";
    }
    
    // Check Macroponto 10 (positron)
    $codigoExterno = '10';
    $macroPonto = mysqli_fetch_assoc(query(
        "SELECT macr_nb_id, macr_tx_codigoInterno, macr_tx_codigoExterno FROM macroponto
         WHERE macr_tx_status = 'ativo'
            AND macr_tx_fonte = 'positron'
            AND macr_tx_codigoExterno = '".$codigoExterno."'
         LIMIT 1;"
    ));
    
    echo "Macroponto '{$codigoExterno}' (positron): ";
    if($macroPonto){
        echo "Encontrado (ID: " . $macroPonto['macr_nb_id'] . ", CodigoInterno: " . $macroPonto['macr_tx_codigoInterno'] . ")\n";
    } else {
        echo "NÃO Encontrado\n";
    }

    // Check directory
    $path = "arquivos/pontos";
    echo "Diretório '{$path}': ";
    if(is_dir($path)){
        echo "Existe\n";
        echo "Writable: " . (is_writable($path) ? "Sim" : "Não") . "\n";
    } else {
        echo "Não existe\n";
    }
?>