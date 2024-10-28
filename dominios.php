<?php

    include "load_env.php";

    $dominios = [
        "armazem_paraiba"		=> "Armazem Paraíba",
        "braso"					=> "Braso",
        "carau_transporte"		=> "Carau Transportes",
        "comav"					=> "Comav",
        "feijao_turqueza"		=> "Feijão Turqueza",
        "fs_log_transportes"	=> "Fs Log Transportes",
        "logsync_techps"	    => "Logsync",
        "opafrutas"				=> "Opafrutas",
        "pkf_medeiros"			=> "PKF Medeiros",
        "qualy_transportes"		=> "Qualy Transportes",
        "techps"				=> "Techps",
        "trampolim_gas"			=> "Trampolim Gás",
    ];

    $dominio_array = array_keys($dominios); //Utilizado nos arquivos que importam este.

    if(empty($_POST["dominio"]) && !empty($_GET["dominio"])){
        $_POST["dominio"] = $_GET["dominio"];
    }

    $dominiosInput = "<div class='form-group' style='position: relative;'>"
        . "<label class='control-label visible-ie8 visible-ie9'>Domínio</label>"
        . "<input type='text' class='form-control form-control-solid placeholder-no-fix search-input' placeholder='TechPS' autocomplete='off'>"
        . "<input type='hidden' name='dominio' class='dominio-key' value=''>"
        . "<div class='options' style='display: none; position: absolute; top: 100%; left: 0; width: 100%; z-index: 1000;'>"; // Ajustes de posição

    // Loop para adicionar as opções ao seletor
    foreach ($dominios as $key => $value) {
        $file = $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/".$key."/index.php";
        if(file_exists($file)){
            $dominiosInput .= "<div class='option' data-value='/{$key}/index.php'>{$value}</div>"; // Chave no data-value, valor exibido
        }
    }

    $dominiosInput .= "</div></div>"; // Fecha as divs de options e form-group

    $dominiosInput .= "
    <script src='https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js'></script>
    <script>
    $(document).ready(function() {
        const searchInput = $('.search-input');
        const optionsContainer = $('.options');
        const optionsList = $('.option');
        const hiddenKeyInput = $('.dominio-key'); // Campo oculto para a chave

        searchInput.on('focus', function() {
            optionsContainer.show(); // Mostra as opções ao focar
        });

        searchInput.on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            optionsList.each(function() {
                const optionText = $(this).text().toLowerCase();
                $(this).toggle(optionText.includes(searchTerm));
            });
        });

        optionsList.on('click', function() {
            searchInput.val($(this).text()); // Preenche o input com a opção escolhida
            hiddenKeyInput.val($(this).data('value')); // Define o valor da chave no campo oculto
            optionsContainer.hide(); // Esconde as opções
        });

        $(document).on('click', function(event) {
            if (!$(event.target).closest('.form-group').length) {
                optionsContainer.hide(); // Esconde as opções ao clicar fora
            }
        });
    });
    </script>";

    // $dominiosInput = "<div class='form-group'>
    //     <select class='form-control' name='dominio'>
    //         <option value='' hidden selected>Domínio</option>";
    // foreach($dominios as $key => $value){
    //     if((!empty($_POST["dominio"]) && $_POST["dominio"] == "/".$key."/index.php") || $key==$_ENV["APP_PATH"]){
    //         $selected = "selected";
    //     }else{
    //         $selected = "";
    //     }
    //     $file = $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/".$key."/index.php";
    
    //     if(file_exists($file)){
    //         $dominiosInput .= "<option value='"."/".$key."/index.php' ".$selected.">".$value."</option>";
    //     }
    // }

    // $dominiosInput .= "</select></div>";
?>