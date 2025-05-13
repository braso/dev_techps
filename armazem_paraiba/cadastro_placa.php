<?php

include_once "load_env.php";
include_once "conecta.php";

ob_start();
cabecalho('Cadastro de Placas');

// Função para carregar empresas
function carregaEmpresa() {
    global $conn;
    $sql = "SELECT empr_nb_id, empr_tx_nome FROM empresa ORDER BY empr_tx_nome";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        die("Erro ao consultar empresas: " . mysqli_error($conn));
    }
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Processamento do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar'])) {
    $placa = strtoupper(trim($_POST['placa']));
    $modelo = trim($_POST['modelo']) ?: null; // Permite modelo null
    $empresa = (int)$_POST['empresa'];

    // Validação básica
    if (empty($placa) || $empresa <= 0) {
        $error = "Por favor, preencha a placa e selecione uma empresa.";
        header("Location: ".$_SERVER['PHP_SELF']."?error=".urlencode($error));
        exit;
    }

    // Verificar se a placa já existe
    $check_sql = "SELECT id FROM placa WHERE placa = ? AND placa_id_empresa = ?";
    if (isset($_POST['editar_id']) && $_POST['editar_id'] != '') {
        $check_sql .= " AND id != ?";
    }
    
    $check_stmt = mysqli_prepare($conn, $check_sql);
    if (isset($_POST['editar_id']) && $_POST['editar_id'] != '') {
        $editar_id = (int)$_POST['editar_id'];
        mysqli_stmt_bind_param($check_stmt, "sii", $placa, $empresa, $editar_id);
    } else {
        mysqli_stmt_bind_param($check_stmt, "si", $placa, $empresa);
    }
    
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        $error = "Esta placa já está cadastrada para esta empresa.";
        header("Location: ".$_SERVER['PHP_SELF']."?error=".urlencode($error));
        mysqli_stmt_close($check_stmt);
        exit;
    }
    mysqli_stmt_close($check_stmt);

    // Processar cadastro/edição
    if (isset($_POST['editar_id']) && $_POST['editar_id'] != '') {
        $editar_id = (int)$_POST['editar_id'];
        $sql = "UPDATE placa SET placa = ?, modelo = ?, placa_id_empresa = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssii", $placa, $modelo, $empresa, $editar_id);
        $acao = 'editado';
    } else {
        $sql = "INSERT INTO placa (placa, modelo, placa_id_empresa) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $placa, $modelo, $empresa);
        $acao = 'cadastrado';
    }

    if (mysqli_stmt_execute($stmt)) {
        $success = ($acao == 'cadastrado') ? "Placa cadastrada com sucesso!" : "Placa editada com sucesso!";
        header("Location: ".$_SERVER['PHP_SELF']."?success=".urlencode($success));
        mysqli_stmt_close($stmt);
        exit;
    } else {
        $error = "Erro ao " . ($acao == 'cadastrado' ? 'cadastrar' : 'editar') . " a placa: " . mysqli_error($conn);
        header("Location: ".$_SERVER['PHP_SELF']."?error=".urlencode($error));
        mysqli_stmt_close($stmt);
        exit;
    }
}

// Função para exibir o formulário
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

        if ($res && $row = mysqli_fetch_assoc($res)) {
            $placa = $row['placa'];
            $modelo = $row['modelo'];
            $empresa = $row['placa_id_empresa'];
            $editar_id = $row['id'];
        } else {
            echo "<p class='alert alert-danger'>Placa não encontrada.</p>";
            mysqli_stmt_close($stmt);
            return;
        }
        mysqli_stmt_close($stmt);
    }

    // HTML do formulário
    echo '
    <div class="container">
        <div class="panel panel-default">
            <div class="panel-heading">' . ($editar ? 'Editar Placa' : 'Cadastro de Placas') . '</div>
            <div class="panel-body">
                <form method="post" action="" class="form-horizontal" id="formPlaca">
                    <input type="hidden" name="editar_id" value="' . htmlspecialchars($editar_id) . '">
                    
                    <div class="form-group" style="display: flex; gap: 20px; align-items: flex-end; margin-bottom: 20px;">
                        <div style="flex: 1;">
                            <label for="placa" class="form-label"><b>PLACA</b>:</label>
                            <input type="text" id="placa" name="placa" placeholder="INFORME A PLACA" 
                                   class="form-control" required maxlength="8" 
                                   pattern="[A-Za-z]{3}[0-9][A-Za-z0-9][0-9]{2}|[A-Za-z]{3}[0-9]{4}" 
                                   value="' . htmlspecialchars($placa) . '">
                        </div>
                        
                        <div style="flex: 1;">
                            <label for="modelo" class="form-label"><b>NOME DO VEÍCULO</b>:</label>
                            <input type="text" id="modelo" name="modelo" placeholder="INFORME O NOME DO VEÍCULO" 
                                   class="form-control" value="' . htmlspecialchars($modelo) . '">
                        </div>

                        <div style="flex: 1;">
                            <label for="empresa" class="form-label"><b>EMPRESA</b>:</label>
                            <select id="empresa" name="empresa" class="form-control" required>
                                <option value="">Selecione uma empresa</option>';
                                foreach ($empresas as $emp) {
                                    $selected = ($empresa == $emp['empr_nb_id']) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($emp['empr_nb_id']) . '" ' . $selected . '>' 
                                         . htmlspecialchars($emp['empr_tx_nome']) . '</option>';
                                }
    echo '              </select>
                        </div>
                    </div>

                    <div class="form-group" style="text-align: center;">
                        <button type="submit" name="cadastrar" class="btn btn-primary">
                            ' . ($editar ? 'Atualizar' : 'Cadastrar') . '
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>';
}

// Excluir placa
if (isset($_GET['excluir'])) {
    $idExcluir = (int)$_GET['excluir'];
    $sql = "DELETE FROM placa WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $idExcluir);

    if (mysqli_stmt_execute($stmt)) {
        $success = "Placa excluída com sucesso!";
        header("Location: ".$_SERVER['PHP_SELF']."?success=".urlencode($success));
        mysqli_stmt_close($stmt);
        exit;
    } else {
        $error = "Erro ao excluir placa: " . mysqli_error($conn);
        header("Location: ".$_SERVER['PHP_SELF']."?error=".urlencode($error));
        mysqli_stmt_close($stmt);
        exit;
    }
}

// Listar placas
function listarPlacas() {
    global $conn;
    $sql = "SELECT p.*, e.empr_tx_nome 
            FROM placa p 
            JOIN empresa e ON p.placa_id_empresa = e.empr_nb_id 
            ORDER BY p.id DESC";
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
                        <th scope="col">NOME DO VEÍCULO</th>
                        <th scope="col">EMPRESA</th>
                        <th scope="col" class="acoes text-center">AÇÕES</th>
                    </tr>
                </thead>
                <tbody>';
    
    while ($row = mysqli_fetch_assoc($res)) {
        echo '<tr>
            <td>' . htmlspecialchars($row['placa']) . '</td>
            <td>' . htmlspecialchars($row['modelo'] ?: '-') . '</td>
            <td>' . htmlspecialchars($row['empr_tx_nome']) . '</td>
            <td class="acoes text-center">
                <a href="?editar=' . htmlspecialchars($row['id']) . '" class="btn btn-warning btn-sm mx-1">Editar</a>
                <a href="?excluir=' . htmlspecialchars($row['id']) . '" 
                   onclick="return confirm(\'Tem certeza que deseja excluir?\')" 
                   class="btn btn-danger btn-sm mx-1">Excluir</a>
            </td>
        </tr>';
    }
    
    echo '</tbody></table></div></div>';
}

// Mensagens
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

// Renderização
$editar = isset($_GET['editar']) ? (int)$_GET['editar'] : null;
cadastro_placa($editar);
listarPlacas();
rodape();
ob_end_flush();
?>