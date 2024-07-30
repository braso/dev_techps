<?php 
    /* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/
    
    include "conecta.php";

    cabecalho(""); 
    echo 
        "<!DOCTYPE html>
        <html lang='pt-BR'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Documentação</title>
                <style>
                    #iframeContainer {
                        overflow: auto;
                    }
                    #iframe-doc {
                        margin: 0;
                        padding: 0;
                        width: 1250px;
                        height: 1400px;
                        border: none;
                    } 
                </style>
            </head>
            <body>
                <div id='iframeContainer'>
                    <iframe id='iframe-doc' src='https://doctechps.braso.solutions/documentacao/'>
                    </iframe>
                </div>
            </body>
        </html>"
    ;
    rodape("");