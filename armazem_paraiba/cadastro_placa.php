<?php

// Incluindo arquivos de configuração
include_once "load_env.php";
include_once "conecta.php";

// Ativa o buffer de saída no início do script
ob_start();

// Função de cabeçalho HTML (retorna a string, não imprime)
cabecalho('Cadastro de Placas');

// Função para carregar empresas
function carregaEmpresa() {
    global $conn;
    $sql = "SELECT empr_nb_id, empr_tx_nome FROM empresa";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        die("Erro ao consultar empresas: " . mysqli_error($conn)); // Melhor tratamento de erro
    }
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Processamento do formulário (separado para melhor organização)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar'])) {
    $placa = strtoupper(trim($_POST['placa']));
    $modelo = trim($_POST['modelo']);
    $empresa = (int)$_POST['empresa'];

    // Validação (essencial para segurança)
    if (empty($placa) || empty($modelo) || $empresa <= 0) {
        $error = "Por favor, preencha todos os campos corretamente.";
        header("Location: ".$_SERVER['PHP_SELF']."?error=".urlencode($error));
        exit;
    }

    if (isset($_POST['editar_id']) && $_POST['editar_id'] != '') {
        // Atualizar placa
        $editar_id = (int)$_POST['editar_id'];
        $sql = "UPDATE placa SET placa = ?, modelo = ?, placa_id_empresa = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssii", $placa, $modelo, $empresa, $editar_id);
        $acao = 'editado';
    } else {
        // Inserir nova placa
        $sql = "INSERT INTO placa (placa, modelo, placa_id_empresa) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $placa, $modelo, $empresa);
        $acao = 'cadastrado';
    }

    if (mysqli_stmt_execute($stmt)) {
        // Redirecionar em caso de sucesso
        $success = ($acao == 'cadastrado') ? "Placa cadastrada com sucesso!" : "Placa editada com sucesso!";
        header("Location: ".$_SERVER['PHP_SELF']."?success=".urlencode($success));
        exit;
    } else {
        // Redirecionar em caso de erro
        $error = "Erro ao " . ($acao == 'cadastrado' ? 'cadastrar' : 'editar') . " a placa: " . mysqli_error($conn);
        header("Location: ".$_SERVER['PHP_SELF']."?error=".urlencode($error));
        exit;
    }
    mysqli_stmt_close($stmt); // Fechar a declaração preparada
}

// Função para exibir o formulário de cadastro
function cadastro_placa($editar = null) {
    global $conn;
    $empresas = carregaEmpresa();
    $placa = '';
    $modelo = '';
    $empresa = '';
    $editar_id = '';

    if ($editar) {
        $sql = "SELECT * FROM placa WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $editar);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        if ($res && $row = mysqli_fetch_assoc($res)) { // Verificação se $res é válido
            $placa = $row['placa'];
            $modelo = $row['modelo'];
            $empresa = $row['placa_id_empresa'];
            $editar_id = $row['id'];
        } else {
            echo "<p class='alert alert-danger'>Placa não encontrada.</p>";
            return;
        }
        mysqli_stmt_close($stmt);
    }

    echo '
    <div class="container">
        <div class="panel panel-default">
            <div class="panel-heading">' . ($editar ? 'Editar Placa' : 'Cadastro de Placas') . '</div>
            <div class="panel-body">
                <form method="post" action="" class="form-horizontal">
                    <input type="hidden" name="editar_id"  value="' . htmlspecialchars($editar_id) . '">
                    
                    <div class="form-group" style="display: flex; gap: 20px; align-items: flex-end; margin-bottom: 20px;">
                        <div style="flex: 1;">
                            <label for="placa" class="form-label"><B>PLACA</B>:</label>
                            <input type="text" id="placa" name="placa" placeholder="INFORME A PLACA" class="form-control" required maxlength="8" pattern="[A-Za-z]{3}[0-9][A-Za-z0-9]{3}|[A-Za-z]{3}[0-9]{4}" value="' . htmlspecialchars($placa) . '">
                        </div>
                        
                        <div style="flex: 1;">
                            <label for="modelo" class="form-label"><B>NOME DO VEÍCULO:</B></label>
                            <input type="text" id="modelo" name="modelo" placeholder="INFORME O NOME DO VEÍCULO" class="form-control" value="' . htmlspecialchars($modelo) . '">
                        </div>

                        <div style="flex: 1;">
                            <label for="empresa" class="form-label"><B>EMPRESA:</B></label>
                            <select id="empresa" name="empresa" placeholder="INFORME A EMPRESA" class="form-control" required>
                                <option value="">Selecione uma empresa</option>';
                                foreach ($empresas as $emp) {
                                    $selected = ($empresa == $emp['empr_nb_id']) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($emp['empr_nb_id']) . '" ' . $selected . '>' . htmlspecialchars($emp['empr_tx_nome']) . '</option>';
                                }
                        echo '
                            </select>
                        </div>
                    </div>

                 <div class="form-group" style="text-align: center;">
    <button type="submit" name="cadastrar" class="btn btn-primary">' . ($editar ? 'Atualizar' : 'Cadastrar') . '</button>
</div>

                </form>
            </div>
        </div>
    </div>';

            }







// Excluir placa (separado do processamento do formulário)
if (isset($_GET['excluir'])) {
    $idExcluir = (int)$_GET['excluir'];
    $sql = "DELETE FROM placa WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $idExcluir);

    if (mysqli_stmt_execute($stmt)) {
        $success = "Placa excluída com sucesso!";
        header("Location: ".$_SERVER['PHP_SELF']."?success=".urlencode($success));
        exit;
    } else {
        $error = "Erro ao excluir placa: " . mysqli_error($conn);
        header("Location: ".$_SERVER['PHP_SELF']."?error=".urlencode($error));
        exit;
    }
    mysqli_stmt_close($stmt);
}

// Função para listar as placas
function listarPlacas() {
    global $conn;
    $sql = "SELECT p.*, e.empr_tx_nome FROM placa p JOIN empresa e ON p.placa_id_empresa = e.empr_nb_id ORDER BY p.id DESC";
    $res = mysqli_query($conn, $sql);

    if (!$res) {
        echo "<p class='alert alert-danger'>Erro na consulta SQL: " . mysqli_error($conn) . "</p>";
        return;
    }
    echo '<div class="container mt-4">
    <div style="background: white; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); padding: 20px;">
        <table class="table table-striped table-hover">
            <thead class="thead-dark">
                <tr>
                    <th scope="col">PLACA</th>
                    <th scope="col">NOME DO VEÍUCLO</th>
                    <th scope="col">EMPRESA</th>
                    <th scope="col" class="acoes text-center">AÇÕES</th>
                </tr>
            </thead>
            <tbody>';
        while ($row = mysqli_fetch_assoc($res)) {
            echo '<tr>
                <td>' . htmlspecialchars($row['placa']) . '</td>
                <td>' . htmlspecialchars($row['modelo']) . '</td>
                <td>' . htmlspecialchars($row['empr_tx_nome']) . '</td>
                <td class="acoes text-center">
                    <a href="?editar=' . htmlspecialchars($row['id']) . '" class="btn btn-warning btn-sm mx-1">Editar</a>
                    <a href="?excluir=' . htmlspecialchars($row['id']) . '" onclick="return confirm(\'Tem certeza que deseja excluir?\')" class="btn btn-danger btn-sm mx-1">Excluir</a>
                </td>
            </tr>';
        }
        echo '</tbody>
        </table>
    </div>
</div>';
}
// Mensagens de sucesso e erro (exibidas antes do conteúdo principal)
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success']);
    echo "<script>
        Swal.fire({
            icon: 'success',
            title: '$success_message',
            showConfirmButton: false,
            timer: 2000
        });
    </script>";
}

if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: '$error_message'
        });
    </script>";
}

// Se estiver editando
$editar = isset($_GET['editar']) ? (int)$_GET['editar'] : null;
cadastro_placa($editar);
listarPlacas();

rodape();

// Envia o buffer de saída para o navegador
ob_end_flush();

?>
