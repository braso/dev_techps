<?php
// Incluindo arquivos de configuração
include_once "load_env.php";
include_once "conecta.php";
mysqli_query($conn, "SET time_zone = '-3:00'");
ob_start();
echo'<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
echo'<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">';
// Função de cabeçalho HTML (retorna a string, não imprime)

// Função para carregar empresas
function carregaEmpresa() {
    global $conn;
    $sql = "SELECT empr_nb_id, empr_tx_nome FROM empresa";
    $result = mysqli_query($conn, $sql);
    if (!$result) die("Erro ao consultar empresas: " . mysqli_error($conn));
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function carregaFuncionario() {
    global $conn;
    $sql = "SELECT enti_nb_id, enti_tx_nome FROM entidade
WHERE enti_tx_tipo = 'Motorista' AND enti_tx_status = 'ativo'
ORDER BY enti_tx_nome;
";
    $result = mysqli_query($conn, $sql);
    if (!$result) die('Erro ao consultar motoristas: ' . mysqli_error($conn));
    
    $dados = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    // Debug: imprime os dados para verificar os motoristas retornados
 ;
    
    return $dados;
}

// Processamento do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar'])) {
    $placa = strtoupper(trim($_POST['placa']));
   $modelo = !empty($_POST['modelo']) ? trim($_POST['modelo']) : null;

    $empresa = (int)$_POST['empresa'];
$funcionario = isset($_POST['funcionario']) && is_numeric($_POST['funcionario']) ? (int)$_POST['funcionario'] : null;
    $editar_id = isset($_POST['editar_id']) ? (int)$_POST['editar_id'] : null;

    // Validação dos campos
    if (empty($placa)  || $empresa <= 0) {
        $error = "Por favor, preencha todos os campos obrigatórios corretamente.";
        header("Location: ".$_SERVER['PHP_SELF']."?error=".urlencode($error));
        exit;
    }

    // Verificar se a placa já existe para a empresa (exceto se for edição do mesmo registro)
    $sqlCheck = "SELECT p.id, e.empr_tx_nome FROM placa p
                 JOIN empresa e ON p.placa_id_empresa = e.empr_nb_id
                 WHERE p.placa = ? AND p.placa_id_empresa = ?";
    if ($editar_id) {
        $sqlCheck .= " AND p.id != ?";
        $stmtCheck = mysqli_prepare($conn, $sqlCheck);
        mysqli_stmt_bind_param($stmtCheck, "sii", $placa, $empresa, $editar_id);
    } else {
        $stmtCheck = mysqli_prepare($conn, $sqlCheck);
        mysqli_stmt_bind_param($stmtCheck, "si", $placa, $empresa);
    }
    mysqli_stmt_execute($stmtCheck);
    $resultCheck = mysqli_stmt_get_result($stmtCheck);
    if ($rowCheck = mysqli_fetch_assoc($resultCheck)) {
        $empresaNome = $rowCheck['empr_tx_nome'];
        $error = "Já existe uma placa cadastrada para a empresa \"$empresaNome\"!";
        header("Location: ".$_SERVER['PHP_SELF']."?error=".urlencode($error));
        exit;
    }
    mysqli_stmt_close($stmtCheck);

    // Atualizar ou inserir
if ($editar_id) {
    $sql = "UPDATE placa SET placa = ?, modelo = ?, placa_id_empresa = ?, entidade_id = ?, data_alteracao = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssiii", $placa, $modelo, $empresa, $funcionario, $editar_id);
    $acao = 'editada';
} else {
    $sql = "INSERT INTO placa (placa, modelo, placa_id_empresa, entidade_id, data_cadastro) VALUES (?, ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssii", $placa, $modelo, $empresa, $funcionario);
    $acao = 'cadastrada';
}

    if (mysqli_stmt_execute($stmt)) {
        $success = "Placa $acao com sucesso!";
        header("Location: ".$_SERVER['PHP_SELF']."?success=".urlencode($success));
        exit;
    } else {
        $error = "Erro ao $acao a placa: " . mysqli_error($conn);
        header("Location: ".$_SERVER['PHP_SELF']."?error=".urlencode($error));
        exit;
    }
    mysqli_stmt_close($stmt);
}

// Função para exibir o formulário
function cadastro_placa($editar = null) {
    global $conn;
    $empresas = carregaEmpresa();
    $funcionarios = carregaFuncionario();
    $placa = ''; $modelo = ''; $empresa = ''; $funcionario = ''; $editar_id = '';

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
            $funcionario = $row['entidade_id'];
            $editar_id = $row['id'];
        } else {
            echo "<p class='alert alert-danger'>Placa não encontrada.</p>";
            return;
        }
        mysqli_stmt_close($stmt);
    }
  echo '
    <div class="container" style="width: 100%; max-width: 1500px; margin-top: 20px;">
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
                            <label for="modelo" class="form-label"><B>VEÍCULO / FROTA:</B></label>
                            <input type="text" id="modelo" name="modelo" placeholder="INFORME O NOME DO VEÍCULO" class="form-control" value="' . htmlspecialchars($modelo) . '">
                        </div>
                        <div style="flex: 1;">
                            <label for="funcionario" class="form-label"><b>MOTORISTA</b>:</label>
                            <select id="funcionario" name="funcionario" class="form-control">
                                <option value="">Selecione um motorista</option>';
                                
// Fecha o echo para inserir PHP puro
foreach ($funcionarios as $func) {
    $selected = ($funcionario == $func['enti_nb_id']) ? 'selected' : '';
    echo '<option value="' . htmlspecialchars($func['enti_nb_id']) . '" ' . $selected . '>' 
         . htmlspecialchars($func['enti_tx_nome']) . '</option>';
}

echo '
                            </select>
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

// Excluir placa
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
    $sql = "SELECT p.*, e.empr_tx_nome, en.enti_tx_nome AS funcionario_nome
        FROM placa p
        JOIN empresa e ON p.placa_id_empresa = e.empr_nb_id
        LEFT JOIN entidade en ON p.entidade_id = en.enti_nb_id
        ORDER BY p.id DESC";
    
    $res = mysqli_query($conn, $sql);

    if (!$res) {
        echo "<p class='alert alert-danger'>Erro na consulta SQL: " . mysqli_error($conn) . "</p>";
        return;
    }
    echo '<div class="container" style="width: 100%; max-width: 1500px;">
    <div style="background: white; font-size:10px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); padding: 20px;">
        <table class="table table-striped table-hover" style="font-size:10px;">
            <thead class="thead-dark">
              <tr>
                <th scope="col">PLACA</th>
                <th scope="col">VEÍCULO</th>
                <th scope="col">MOTORISTA</th>
                <th scope="col">EMPRESA</th>
                <th scope="col">DATA DE CADASTRO</th>
                <th scope="col">DATA DE ALTERAÇÃO</th>
                <th scope="col" class="acoes text-center">AÇÕES</th>
            </tr>
            </thead>
            <tbody>';
         while ($row = mysqli_fetch_assoc($res)) {
        echo '<tr>
            <td style="padding:4px 6px;">' . htmlspecialchars($row['placa']) . '</td>
            <td style="padding:4px 6px; word-break:break-word;">' . htmlspecialchars($row['modelo']) . '</td>
            <td style="padding:4px 6px; word-break:break-word;">' . htmlspecialchars($row['funcionario_nome'] ?: '-') . '</td>
            <td style="padding:4px 6px; word-break:break-word;">' . htmlspecialchars($row['empr_tx_nome']) . '</td>
            <td style="padding:4px 6px;">' . ($row['data_cadastro'] ? date('d/m/Y H:i', strtotime($row['data_cadastro'])) : '-') . '</td>
            <td style="padding:4px 6px;">' . ($row['data_alteracao'] ? date('d/m/Y H:i', strtotime($row['data_alteracao'])) : '-') . '</td>
            <td class="acoes text-center" style="padding:4px 6px;">
                <a href="?editar=' . htmlspecialchars($row['id']) . '" class="btn btn-warning btn-sm mx-1" title="Editar">
                    <i class="fas fa-pencil-alt"></i>
                </a>
                <br><br>
                <a href="?excluir=' . htmlspecialchars($row['id']) . '" onclick="return confirm(\'Tem certeza que deseja excluir?\')" class="btn btn-danger btn-sm mx-1" title="Excluir">
                    <i class="fas fa-trash-alt"></i>
                </a>
            </td>
        </tr>';
    }
    echo '</tbody>
        </table>
    </div>
</div>';
}

// Exibe popups de sucesso/erro após o HTML (garante que funcione em qualquer lugar)
if (isset($_GET['success'])) {
    echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Sucesso!',
            text: '" . htmlspecialchars($_GET['success']) . "',
            showConfirmButton: false,
            timer: 2000
        });
    </script>";
}
if (isset($_GET['error'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: '" . htmlspecialchars($_GET['error']) . "'
        });
    </script>";
}

// Se estiver editando
$editar = isset($_GET['editar']) ? (int)$_GET['editar'] : null;
cabecalho('Cadastro de Placas');
cadastro_placa($editar);
listarPlacas();

rodape();

// Envia o buffer de saída para o navegador
ob_end_flush();