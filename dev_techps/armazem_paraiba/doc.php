<?php 
//ini_set('display_errors', 1);
//error_reporting(E_ALL);
include 'conecta.php';
?>

<?= 

cabecalho(''); ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Documentação</title>
        <style>
                #iframe-doc{
                    margin: 0;
                    padding: 0;
                }
            </style>
</head>
<body>
        <div><iframe id="iframe-doc"
  width="1400px"
  height="1400px"
  frameborder="0"
  marginheight="0"
  marginwidth="0"
  src="https://doctechps.braso.solutions/documentacao/">
</iframe
></div>
        


        
</body>
</html>

<?= 
rodape(''); ?>
