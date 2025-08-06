<?php
include_once "load_env.php";
include_once "conecta.php";
mysqli_query($conn, "SET time_zone = '-3:00'");
ob_start();

echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">';

cabecalho('Cadastro de Celulares');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar'])) {
    $nome = trim($_POST['nome']);
    $imei = trim($_POST['imei']);
    $numero = trim($_POST['numero']);
    $operadora = trim($_POST['operadora']);
    $cimie = trim($_POST['cimie']);
    $so = trim($_POST['sistema_operacional']);
    $marca_modelo = trim($_POST['marca_modelo']);
    $entidade_id = isset($_POST['entidade_id']) ? (int) $_POST['entidade_id'] : null;
    $editar_id = isset($_POST['editar_id']) ? (int)$_POST['editar_id'] : null;

    if (empty($nome) || empty($imei) || empty($numero)) {
        $error = "Preencha todos os campos obrigatórios (Nome, IMEI e Número).";
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . urlencode($error));
        exit;
    }

    $sqlCheck = "SELECT id FROM celular WHERE imei = ?";
    if ($editar_id) {
        $sqlCheck .= " AND id != ?";
        $stmtCheck = mysqli_prepare($conn, $sqlCheck);
        mysqli_stmt_bind_param($stmtCheck, "si", $imei, $editar_id);
    } else {
        $stmtCheck = mysqli_prepare($conn, $sqlCheck);
        mysqli_stmt_bind_param($stmtCheck, "s", $imei);
    }
    mysqli_stmt_execute($stmtCheck);
    $resCheck = mysqli_stmt_get_result($stmtCheck);
    if (mysqli_fetch_assoc($resCheck)) {
        $error = "Este IMEI já está cadastrado!";
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . urlencode($error));
        exit;
    }
    mysqli_stmt_close($stmtCheck);

    if ($editar_id) {
        $sql = "UPDATE celular SET nome=?, imei=?, numero=?, operadora=?, cimie=?, sistema_operacional=?, marca_modelo=?, entidade_id=?, data_alteracao=NOW() WHERE id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssssssii", $nome, $imei, $numero, $operadora, $cimie, $so, $marca_modelo, $entidade_id, $editar_id);
        $acao = 'atualizado';
    } else {
        $sql = "INSERT INTO celular (nome, imei, numero, operadora, cimie, sistema_operacional, marca_modelo, entidade_id, data_cadastro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssssssi", $nome, $imei, $numero, $operadora, $cimie, $so, $marca_modelo, $entidade_id);
        $acao = 'cadastrado';
    }

    if (mysqli_stmt_execute($stmt)) {
        $success = "Celular $acao com sucesso!";
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($success));
        exit;
    } else {
        $error = "Erro ao salvar celular: " . mysqli_error($conn);
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . urlencode($error));
        exit;
    }
    mysqli_stmt_close($stmt);
}

function formCelular($editar = null) {
    global $conn;
    $dados = ['nome'=>'','imei'=>'','numero'=>'','operadora'=>'','cimie'=>'','sistema_operacional'=>'','marca_modelo'=>'','entidade_id'=>'','id'=>''];

    if ($editar) {
        $sql = "SELECT * FROM celular WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $editar);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && $row = mysqli_fetch_assoc($res)) {
            $dados = $row;
        }
        mysqli_stmt_close($stmt);
    }

    echo '
    <div class="container" style="width: 100%; max-width: 1500px; margin-top: 20px;">
        <div class="panel panel-default">
            <div class="panel-heading">' . ($editar ? 'Editar Celular' : 'Cadastro de Celulares') . '</div>
            <div class="panel-body">
                <form method="post" action="" class="form-horizontal">
                    <input type="hidden" name="editar_id" value="' . htmlspecialchars($dados['id']) . '">
                    <div class="form-group" style="display: flex; flex-wrap: wrap; gap: 20px;">
                        <div style="flex: 1;">
                            <label><b>NOME</b>:</label>
                            <input type="text"  placeholder="Nome"  name="nome" class="form-control" required value="' . htmlspecialchars($dados['nome']) . '">
                        </div>
                        <div style="flex: 1;">
                            <label><b>IMEI</b>:</label>
                            <input type="text"  placeholder="IMEI"   name="imei" class="form-control" required maxlength="30" value="' . htmlspecialchars($dados['imei']) . '">
                        </div>
                        <div style="flex: 1;">
                            <label><b>Número</b>:</label>
                            <input type="text" name="numero"  placeholder="Número" class="form-control" required maxlength="20" value="' . htmlspecialchars($dados['numero']) . '">
                        </div>
                        <div style="flex: 1;">
                            <label><b>Operadora</b>:</label>
                            <input type="text" name="operadora"  placeholder="Operadora"     class="form-control" value="' . htmlspecialchars($dados['operadora']) . '">
                        </div>
                        <div style="flex: 1;">
                            <label><b>CIMIE</b>:</label>
                            <input type="text" name="cimie" class="form-control"  placeholder="CIMIE" value="' . htmlspecialchars($dados['cimie']) . '">
                        </div>
                        <div style="flex: 1;">
                            <label><b>Sistema Operacional</b>:</label>
                            <input type="text" name="sistema_operacional" class="form-control"  placeholder="Sistema Operacional" value="' . htmlspecialchars($dados['sistema_operacional']) . '">
                        </div>
                        <div style="flex: 1;">
                            <label><b>Marca e Modelo</b>:</label>
                            <input type="text" name="marca_modelo" class="form-control"  placeholder="Marca e Modelo" value="' . htmlspecialchars($dados['marca_modelo']) . '">
                        </div>
                        <div style="flex: 1;">
                            <label><b>Responsável</b>:</label>
                            <select name="entidade_id" class="form-control">
                                <option value="">-- Selecione --</option>';
                                $sqlFunc = "SELECT enti_nb_id, enti_tx_nome FROM entidade
                                WHERE enti_tx_tipo = 'Motorista' AND enti_tx_status = 'ativo'
                                ORDER BY enti_tx_nome";
                                $resFunc = mysqli_query($conn, $sqlFunc);
                                while ($func = mysqli_fetch_assoc($resFunc)) {
                                    $selected = ($dados['entidade_id'] == $func['enti_nb_id']) ? 'selected' : '';
                                    echo '<option value="' . $func['enti_nb_id'] . '" ' . $selected . '>' . htmlspecialchars($func['enti_tx_nome']) . '</option>';
                                }
                            echo '
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="text-align: center; margin-top: 20px;">
                        <button type="submit" name="cadastrar" class="btn btn-primary">' . ($editar ? 'Atualizar' : 'Cadastrar') . '</button>
                    </div>
                </form>
            </div>
        </div>
    </div>';
}

function listarCelulares() {
    global $conn;
    $sql = "SELECT c.*, e.enti_tx_nome AS funcionario_nome
            FROM celular c
            LEFT JOIN entidade e ON c.entidade_id = e.enti_nb_id
            ORDER BY c.id DESC";
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        echo "<p class='alert alert-danger'>Erro ao listar celulares: " . mysqli_error($conn) . "</p>";
        return;
    }
    echo '<div class="container" style="width: 100%; max-width: 1500px;">
    <div style="background: white; font-size:10px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); padding: 20px;">
        <table class="table table-striped table-hover" style="font-size:10px;">
            <thead class="thead-dark">
              <tr>
                <th>NOME</th>
                <th>IMEI</th>
                <th>Número</th>
                <th>Operadora</th>
                <th>CIMIE</th>
                <th>S.O.</th>
                <th>Marca/Modelo</th>
                <th>Responsável</th>
                <th>Cadastro</th>
                <th>Alteração</th>
                <th class="text-center">Ações</th>
            </tr>
            </thead>
            <tbody>';
    while ($row = mysqli_fetch_assoc($res)) {
        echo '<tr>
            <td>' . htmlspecialchars($row['nome']) . '</td>
            <td>' . htmlspecialchars($row['imei']) . '</td>
            <td>' . htmlspecialchars($row['numero']) . '</td>
            <td>' . htmlspecialchars($row['operadora']) . '</td>
            <td>' . htmlspecialchars($row['cimie']) . '</td>
            <td>' . htmlspecialchars($row['sistema_operacional']) . '</td>
            <td>' . htmlspecialchars($row['marca_modelo']) . '</td>
            <td>' . htmlspecialchars($row['funcionario_nome']) . '</td>
            <td>' . date('d/m/Y H:i', strtotime($row['data_cadastro'])) . '</td>
            <td>' . date('d/m/Y H:i', strtotime($row['data_alteracao'])) . '</td>
            <td class="text-center">
                <a href="?editar=' . $row['id'] . '" class="btn btn-sm btn-warning" title="Editar">
                    <i class="fa fa-edit"></i>
                </a>
                <a href="?excluir=' . $row['id'] . '" class="btn btn-sm btn-danger" title="Excluir" onclick="return confirm(\'Tem certeza que deseja excluir este celular?\')">
                    <i class="fa fa-trash"></i>
                </a>
            </td>
        </tr>';
    }
    echo '</tbody></table></div></div>';
}
// Excluir celular
if (isset($_GET['excluir'])) {
    $excluir_id = (int) $_GET['excluir'];
    $sqlDel = "DELETE FROM celular WHERE id = ?";
    $stmtDel = mysqli_prepare($conn, $sqlDel);
    mysqli_stmt_bind_param($stmtDel, "i", $excluir_id);
    if (mysqli_stmt_execute($stmtDel)) {
        $success = "Celular excluído com sucesso!";
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($success));
        exit;
    } else {
        $error = "Erro ao excluir celular: " . mysqli_error($conn);
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . urlencode($error));
        exit;
    }
    mysqli_stmt_close($stmtDel);
}

// Exibir mensagens
if (isset($_GET['success'])) {
    echo "<script>Swal.fire('Sucesso!', '" . htmlspecialchars($_GET['success']) . "', 'success');</script>";
}
if (isset($_GET['error'])) {
    echo "<script>Swal.fire('Erro!', '" . htmlspecialchars($_GET['error']) . "', 'error');</script>";
}

// Renderiza formulário e lista
$editar_id = isset($_GET['editar']) ? (int)$_GET['editar'] : null;
formCelular($editar_id);
listarCelulares();

rodape();
?>
