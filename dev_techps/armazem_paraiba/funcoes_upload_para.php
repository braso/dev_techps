<?php

// if ($_SERVER["REQUEST_METHOD"] == "POST") {
//     // Verifica se há um arquivo enviado
//     if (isset($_FILES["arquivo"])) {
//         $arquivo = $_FILES["arquivo"];
//         $descricao = $_POST["descricao"];

//         // Verifica se o upload foi bem-sucedido
//         if ($arquivo["error"] == 0) {
//             $nomeArquivo = $arquivo["name"];
//             $destino = "uploads/" . $nomeArquivo;

//             // Move o arquivo para o diretório desejado
//             move_uploaded_file($arquivo["tmp_name"], $destino);

//             // Pega a primeira descrição
//             $primeiraDescricao = is_array($descricao) ? $descricao[0] : $descricao;

//             // Aqui você pode realizar operações com a descrição, como salvá-la em um banco de dados
//             // Neste exemplo, apenas exibiremos os dados
//             echo "Arquivo: $nomeArquivo, Descrição: $primeiraDescricao<br>";
//         } else {
//             echo "Erro no upload do arquivo.";
//         }
//     }
// }

function arquivo_list($nome, $tamanho, $idParametro){
    $botao = '
    <div class="col-sm-'.$tamanho.' margin-bottom-5">
        <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#myModal">
        '. $nome .'
        </button>
    </div>' ;
      
    $campo =  ' 
    <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
          <div class="modal-dialog" role="document">
              <div class="modal-content">
                  <div class="modal-header">
                      <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                              aria-hidden="true">&times;</span></button>
                      <h4 class="modal-title" id="myModalLabel">'.$nome.'</h4>
                  </div>
                  <div class="modal-body">
                      <form id="meuFormulario" action="funcoes_upload_para.php" method="post" enctype="multipart/form-data">
                            <label for="arquivo">Selecione um arquivo:</label>
                            <input class="form-control input-sm" type="file" id="arquivo" name="arquivo" accept=".jpg, .jpeg, .png">
                          
                            <label for="descricao">Descrição do arquivo:</label>
                            <textarea id="descricao" name="descricao" maxlength="255" rows="4"></textarea>
                          <br>
                          
                          <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Fechar</button>
                            <button type="submit" class="btn btn-success">Enviar</button>
                          </div>
                      </form>
                  </div>
              </div>
          </div>
      </div>';
    
    $script = '
      <script>
          $("#meuFormulario").submit(function() {
              // Adicione aqui qualquer lógica de validação necessária antes de enviar o formulário

              $("<input />").attr("type", "hidden").attr("name", "informacao_extra").attr("value", "'.$idParametro.'").appendTo("#meuFormulario");
              // Fechar o modal manualmente
              $("#myModal").modal("hide");
          });
      </script>';
  
    return $botao.$campo.$script;
  }