<?php
include_once "load_env.php";
include_once "funcoes_ponto.php";
include_once "conecta.php"; // Incluindo a conexão

cabecalho('Cadastro de Placas'); // Passando o título

// Função para carregar as empresas do banco de dados
function carregaEmpresa() {
    global $conn;
    $sql = "SELECT empr_nb_id, empr_tx_nome FROM empresa";
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        die("Erro ao consultar empresas: " . mysqli_error($conn));
    }

    return mysqli_fetch_all($result, MYSQLI_ASSOC); // Retorna todas as empresas de uma vez
}

// Função para exibir o formulário de cadastro de placas
function cadastro_placa() {
    global $conn; // Disponibilize a conexão dentro da função
    $empresas = carregaEmpresa();

    // Exibir formulário
    echo '
    <div class="container">
        <h1>Cadastro de Placas</h1>
        <form id="cadastroPlaca" method="post" action="">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="placa">Placa:</label>
                    <input type="text" id="placa" name="placa" required maxlength="8" pattern="[A-Za-z]{3}[0-9][A-Za-z0-9]{3}|[A-Za-z]{3}[0-9]{4}" placeholder="Ex: ABC1234">
                </div>
                <div class="form-group col-md-6">
                    <label for="modelo">Nome do Veículo:</label>
                    <input type="text" id="modelo" name="modelo" required placeholder="Nome do veículo">
                </div>
            </div>
            <div class="form-group">
                <label for="empresa">Empresa:</label>
                <select id="empresa" name="empresa" required>
                    <option value="">Selecione uma empresa</option>';

                    foreach ($empresas as $empresa) {
                        echo '<option value="'.$empresa['empr_nb_id'].'">'.$empresa['empr_tx_nome'].'</option>';
                    }
                    echo '
                </select>
            </div>
            <button type="submit" name="cadastrar">Cadastrar</button>
        </form>
    </div>';
}

// Estilos CSS para o formulário
echo '<style>
    .container {
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: column;
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        width: 100%;
        max-width: 800px; /* Aumente a largura máxima para acomodar os campos lado a lado */
        margin: 20px auto;
    }
    h1 {
        text-align: center;
        color: #333;
    }
    .form-group {
        margin-bottom: 15px;
    }
    /* Estilo para alinhar os campos lado a lado */
    .form-row {
        display: flex;
        align-items:center; /* Alinha verticalmente os campos */
        justify-content:center; /* Espaço entre os campos */
        width: 100%; /* Garante que a linha ocupe toda a largura do container */
    }
    .form-row .form-group {
        width: 88%; /* Ajuste a largura para que os campos fiquem lado a lado com um pequeno espaço */
    }
    label {
        display: block;
        font-weight: bold;
        margin-bottom: 5px;
    }
    input, select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 5px;
        font-size: 16px;
        box-sizing: border-box; /* Garante que o padding não aumente a largura total */
    }
    button {
        width: 100%;
        background-color: #007bff;
        color: white;
        border: none;
        padding: 10px;
        font-size: 16px;
        border-radius: 5px;
        cursor: pointer;
    }
    button:hover {
        background-color: #0056b3;
    }
</style>';

// Processar o formulário APÓS exibir
if (isset($_POST['cadastrar'])) {
    global $conn;  // Adicione global $conn;
    $placa = mysqli_real_escape_string($conn, $_POST["placa"]);
    $modelo = mysqli_real_escape_string($conn, $_POST["modelo"]);
    $empresa_id = mysqli_real_escape_string($conn, $_POST["empresa"]);  // Corrigido: Use o ID da empresa vindo do select.

    $sql = "INSERT INTO placa (placa, modelo, placa_id_empresa) VALUES ('$placa', '$modelo', '$empresa_id')";

    if ($conn->query($sql) === TRUE) {
        echo "<script>
                setTimeout(function() {
                    swal('Sucesso!', 'Cadastro realizado com sucesso!', 'success');
                }, 200); // Exibe o popup após um pequeno atraso para garantir que o DOM esteja carregado
              </script>";
    } else {
        echo "<script>
                setTimeout(function() {
                    swal('Erro!', 'Erro ao cadastrar: " . $conn->error . "', 'error');
                }, 200); // Exibe o popup após um pequeno atraso para garantir que o DOM esteja carregado
              </script>";
    }
}




function listar_placas() {
    global $conn;
    $sql = "SELECT p.placa, p.modelo, e.empr_tx_nome AS nome_empresa, e.empr_tx_cnpj AS cnpj_empresa,  p.id
            FROM placa p
            JOIN empresa e ON p.placa_id_empresa = e.empr_nb_id";

    $result = $conn->query($sql);

    if ($result === FALSE) {
        echo "<p style='color:red;'>Erro na consulta SQL: " . $conn->error . "</p>";
        return; // Parar a função se houver um erro
    }

    if ($result->num_rows > 0) {
        echo "<h2>Placas Cadastradas</h2>";
        echo '<style>
            .table-container {
                width: 100%;
                overflow-x: auto; /* Habilita a rolagem horizontal em telas menores */
            }
            table {
                border-collapse: collapse;
                width: 100%;
                margin-bottom: 20px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                border-radius: 8px;
                overflow: hidden; /* Garante que a borda arredondada funcione */
            }
            th, td {
                padding: 12px 15px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }
            th {
                background-color: #007bff;
                color: white;
                font-weight: bold;
                text-transform: uppercase;
            }
            tbody tr:nth-child(even) {
                background-color: #f2f2f2;
            }
            tbody tr:hover {
                background-color: #ddd;
            }
            .acoes {
                white-space: nowrap; /* Impede quebras de linha */
            }
            .acoes a {
                display: inline-block;
                padding: 8px 12px;
                margin: 5px;
                border-radius: 5px;
                text-decoration: none;
                color: #007bff;
                border: 1px solid #007bff;
                transition: background-color 0.3s, color 0.3s;
            }
            .acoes a:hover {
                background-color: #007bff;
                color: white;
            }
        </style>';

        echo '<div class="table-container">';
        echo "<table class='table table-striped'>";
        echo "<thead>
                    <tr>
                        <th>Placa</th>
                        <th>Modelo</th>
                        <th>Empresa</th>
                        <th>CNPJ</th>
             
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>";

        while($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>".$row["placa"]."</td>
                    <td>".$row["modelo"]."</td>
                    <td>".$row["nome_empresa"]."</td>
                    <td>".$row["cnpj_empresa"]."</td>
                  
                    <td class='acoes'>
                        
                        <a href='#' onclick='excluirPlaca(".$row["id"].")'>Excluir</a>
                    </td>
                  </tr>";
        }
        echo "</tbody></table>";
        echo '</div>'; // Fecha o table-container
    } else {
        echo "<p>Nenhuma placa cadastrada.</p>";
    }
}

// Chame a função cadastro_placa() para exibir o formulário
cadastro_placa();

// Chame a função listar_placas() para exibir a lista de placas
listar_placas();

rodape('cadastro_placa');

// Modal de Edição
echo '
<div class="modal fade" id="editarModal" tabindex="-1" role="dialog" aria-labelledby="editarModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editarModalLabel">Editar Placa</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">×</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="editarPlacaForm" method="post" action="">
           <input type="hidden" name="placa_id" id="placa_id">
          <div class="form-group">
             <label for="editar_placa">Placa:</label>
              <input type="text" class="form-control" id="editar_placa" name="editar_placa" required>
           </div>
           <div class="form-group">
              <label for="editar_modelo">Modelo:</label>
              <input type="text" class="form-control" id="editar_modelo" name="editar_modelo" required>
          </div>
            <div class="form-group">
                <label for="editar_empresa">Empresa:</label>
                <select id="editar_empresa" name="editar_empresa" required>
                    <option value="">Selecione uma empresa</option>';
                    $empresas = carregaEmpresa(); // Recarregue as empresas para o modal de edição
                    foreach ($empresas as $empresa) {
                      echo '<option value="'.$empresa['empr_nb_id'].'">'.$empresa['empr_tx_nome'].'</option>';
                    }

               echo '
                   </select> </div>
          <button type="submit" name="atualizarPlaca" class="btn btn-primary">Salvar Alterações</button>
       </form>
      </div>
    </div>
  </div>
</div>';

// Processar a atualização da placa
if (isset($_POST['atualizarPlaca'])) {
    global $conn;
    $placa_id = mysqli_real_escape_string($conn, $_POST['placa_id']);
    $placa = mysqli_real_escape_string($conn, $_POST["editar_placa"]);
    $modelo = mysqli_real_escape_string($conn, $_POST["editar_modelo"]);
    $empresa_id = mysqli_real_escape_string($conn, $_POST["editar_empresa"]);

    $sql = "UPDATE placa SET placa='$placa', modelo='$modelo', placa_id_empresa='$empresa_id' WHERE id = '$placa_id'";

    if ($conn->query($sql) === TRUE) {
        echo "<script>
                swal('Sucesso!', 'Placa atualizada com sucesso!', 'success')
                .then(() => {
                    window.location.href = 'cadastro_placa.php'; // Recarrega a página
                });
              </script>";
    } else {
        echo "<script>swal('Erro!', 'Erro ao atualizar placa: " . $conn->error . "', 'error');</script>";
    }
}

// Processar a exclusão da placa
echo "<script>
function excluirPlaca(id) {
    swal({
        title: 'Tem certeza?',
        text: 'Deseja realmente excluir esta placa?',
        icon: 'warning',
        buttons: true,
        dangerMode: true,
    }).then((willDelete) => {
        if (willDelete) {
            // Enviar uma requisição POST para excluir a placa
            $.ajax({
                url: 'cadastro_placa.php', // Envia para a mesma página
                type: 'POST',
                data: { excluir_placa_id: id },
                success: function(response) {
                    swal('Sucesso!', 'Placa excluída com sucesso!', 'success')
                    .then(() => {
                        window.location.href = 'cadastro_placa.php'; // Recarrega a página
                    });
                },
                error: function(xhr, status, error) {
                    swal('Erro!', 'Erro ao excluir placa: ' + error, 'error');
                }
            });
        }
    });
}
</script>";

if (isset($_POST['excluir_placa_id'])) {
    global $conn;
    $excluir_placa_id = mysqli_real_escape_string($conn, $_POST['excluir_placa_id']);

    $sql = "DELETE FROM placa WHERE id = '$excluir_placa_id'";

    if ($conn->query($sql) === TRUE) {
        echo "<script>
                swal('Sucesso!', 'Placa excluída com sucesso!', 'success')
                .then(() => {
                    window.location.href = 'cadastro_placa.php'; // Recarrega a página
                });
              </script>";
    } else {
        echo "<script>swal('Erro!', 'Erro ao excluir placa: " . $conn->error . "', 'error');</script>";
    }
}

?>
<script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script> <!-- Inclua a biblioteca SweetAlert -->

<script>
    //Para o modal
    function editarPlaca(id) {
        $.ajax({
            url: 'buscar_placa.php', // Crie este arquivo
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(data) {
                $('#placa_id').val(data.id);
                $('#editar_placa').val(data.placa);
                $('#editar_modelo').val(data.modelo);
                $('#editar_empresa').val(data.placa_id_empresa);
                $('#editarModal').modal('show');
            },
            error: function(error) {
              console.error('Erro ao buscar placa:', error);
              swal("Erro", "Erro ao buscar placa para edição. Detalhes no console.", "error");
            }
          });
    }


</script>
