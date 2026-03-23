<?php
include_once "../conecta.php";
include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/contex20/funcoes_form.php";

// Funções de Ação
function gravarCampo() {
    $id_tipo = $_POST['id_tipo'] ?? $_POST['id'];
    $id_campo = $_POST['id_campo'];
    
    if (empty($id_tipo)) {
        echo "<div class='alert alert-danger'>ID do tipo não informado. <a href='../cadastro_tipo_doc.php'>Voltar</a></div>";
        rodape();
        exit;
    }

    $dados = [
        'camp_nb_tipo_doc' => $id_tipo,
        'camp_tx_label' => $_POST['label'],
        'camp_tx_tipo' => $_POST['tipo_campo'],
        'camp_tx_opcoes' => $_POST['opcoes'],
        'camp_nb_ordem' => $_POST['ordem'],
        'camp_tx_obrigatorio' => $_POST['obrigatorio'],
        'camp_tx_placeholder' => $_POST['placeholder']
    ];

    if ($id_campo > 0) {
        atualizar('camp_documento_modulo', array_keys($dados), array_values($dados), $id_campo);
        set_status("Campo atualizado com sucesso!");
    } else {
        inserir('camp_documento_modulo', array_keys($dados), array_values($dados));
        set_status("Campo adicionado com sucesso!");
    }
    
    $_POST['id_tipo'] = $id_tipo;
    index();
    exit;
}

function excluirCampo() {
    $id_tipo = $_POST['id_tipo'];
    $id_campo = $_POST['id_campo'];
    remover('camp_documento_modulo', $id_campo);
    set_status("Campo removido.");
    $_POST['id_tipo'] = $id_tipo;
    index();
    exit;
}

function layout_campo() {
    global $conn;
    cabecalho("Configurar Campo");
    
    // Força esconder o loading imediatamente
    echo "
    <style>
        .loading { display: none !important; }
    </style>
    <script>
        function hideLoadingFinal() {
            var loadings = document.getElementsByClassName('loading');
            for (var i = 0; i < loadings.length; i++) {
                loadings[i].style.visibility = 'hidden';
                loadings[i].style.display = 'none';
            }
        }
        hideLoadingFinal();
        setTimeout(hideLoadingFinal, 500);
    </script>";

    $id_tipo = $_POST['id_tipo'] ?? $_POST['id'] ?? 0;
    $id_campo = $_POST['id_campo'] ?? 0;
    
    // Inicializa variáveis para evitar warnings
    $vas = ['label', 'tipo_campo', 'ordem', 'obrigatorio', 'placeholder', 'opcoes'];
    foreach($vas as $v) if(!isset($_POST[$v])) $_POST[$v] = '';

    if ($id_campo > 0) {
        $a_campo = carregar('camp_documento_modulo', $id_campo);
        if($a_campo){
            $_POST = array_merge($_POST, $a_campo);
            $_POST['label'] = $a_campo['camp_tx_label'];
            $_POST['tipo_campo'] = $a_campo['camp_tx_tipo'];
            $_POST['ordem'] = $a_campo['camp_nb_ordem'];
            $_POST['obrigatorio'] = $a_campo['camp_tx_obrigatorio'];
            $_POST['placeholder'] = $a_campo['camp_tx_placeholder'];
            $_POST['opcoes'] = $a_campo['camp_tx_opcoes'];
        }
    }

    $tipos = [
        'texto_curto' => 'Texto Curto',
        'texto_longo' => 'Texto Longo (Textarea)',
        'data' => 'Data',
        'selecao' => 'Seleção (Dropdown)',
        'usuario' => 'Usuário Logado',
        'setor' => 'Setor do Usuário',
        'number' => 'Número'
    ];

    $fields = [
        "<input type='hidden' name='id_tipo' value='{$id_tipo}'>",
        "<input type='hidden' name='id_campo' value='{$id_campo}'>",
        campo("Rótulo (Label)*", "label", $_POST['label'], 4),
        combo("Tipo de Campo*", "tipo_campo", $_POST['tipo_campo'], 3, $tipos),
        campo("Ordem", "ordem", $_POST['ordem'] ?? 0, 1, "MASCARA_NUMERO"),
        combo("Obrigatório?", "obrigatorio", $_POST['obrigatorio'], 2, ['sim'=>'Sim', 'nao'=>'Não']),
        campo("Placeholder", "placeholder", $_POST['placeholder'], 2),
        campo("Opções (para Seleção - sep. por vírgula)", "opcoes", $_POST['opcoes'], 6)
    ];

    $buttons = [
        botao("Gravar Campo", "gravarCampo"),
        botao("Voltar", "index")
    ];

    echo abre_form("Dados do Campo");
    echo "<input type='hidden' name='id_tipo' value='{$id_tipo}'>";
    echo linha_form($fields);
    echo fecha_form($buttons);
    
    rodape();
}

function index() {
    global $conn;
    cabecalho("Layout do Documento");
    
    // Força esconder o loading imediatamente após o cabeçalho
    echo "
    <style>
        .loading { display: none !important; }
    </style>
    <script>
        function hideLoadingFinal() {
            var loadings = document.getElementsByClassName('loading');
            for (var i = 0; i < loadings.length; i++) {
                loadings[i].style.visibility = 'hidden';
                loadings[i].style.display = 'none';
            }
        }
        hideLoadingFinal();
        setTimeout(hideLoadingFinal, 500);
    </script>";
    
    $id_tipo = $_POST['id_tipo'] ?? $_POST['id'] ?? $_GET['id'];
    if (empty($id_tipo)) {
        echo "<div class='alert alert-danger'>ID do tipo não informado. <a href='../cadastro_tipo_doc.php'>Voltar</a></div>";
        rodape();
        exit;
    }

    // Verifica se as tabelas existem
    $check = query("SHOW TABLES LIKE 'documento_campo'");
    if (mysqli_num_rows($check) == 0) {
        echo "<div class='alert alert-warning'>
                <h4>Tabelas não encontradas!</h4>
                <p>O banco de dados precisa ser inicializado.</p>
                <a href='setup_documentos.php' class='btn btn-primary'>Inicializar Banco de Dados agora</a>
              </div>";
        rodape();
        exit;
    }

    $a_tipo = carregar('tipos_documentos', $id_tipo);
    if (!$a_tipo) {
        echo "<div class='alert alert-danger'>Tipo de documento não encontrado.</div>";
        rodape();
        exit;
    }

    // Se houver POST para atualizar dados do tipo (logo, cabeçalho)
    if (($_POST['acao_tipo'] ?? '') == 'save_tipo') {
        $dados_tipo = [];

        // Handle Logo Upload
        if (!empty($_FILES['tipo_logo_file']['name'])) {
            $arquivo = $_FILES['tipo_logo_file'];
            $pasta = "../arquivos/logos/";
            if (!is_dir($pasta)) mkdir($pasta, 0777, true);
            
            $ext = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
            $nome_arquivo = "logo_" . $id_tipo . "_" . time() . "." . $ext;
            $caminho_final = $pasta . $nome_arquivo;
            
            if (move_uploaded_file($arquivo['tmp_name'], $caminho_final)) {
                $dados_tipo['tipo_tx_logo'] = $caminho_final;
            }
        }

        atualizar('tipos_documentos', array_keys($dados_tipo), array_values($dados_tipo), $id_tipo);
        set_status("Dados do documento atualizados!");
        $a_tipo = carregar('tipos_documentos', $id_tipo);
    }
    
    echo "<h3>Configuração: " . ($a_tipo['tipo_tx_nome'] ?? 'Documento') . "</h3>";

    $fields_tipo = [
        campo_hidden("id_tipo", $id_tipo),
        campo_hidden("acao_tipo", "save_tipo"),
        "Logo Atual: " . (($a_tipo['tipo_tx_logo'] ?? null) ? "<img src='{$a_tipo['tipo_tx_logo']}' style='max-height:50px;'>" : "Nenhum"),
        "<div class='col-sm-4'><label>Novo Logo (Upload)</label><input type='file' name='tipo_logo_file' class='form-control'></div>"
    ];
    echo abre_form("Dados Fixos do PDF (Topo/Fundo)");
    echo "<input type='hidden' name='id_tipo' value='{$id_tipo}'>";
    echo linha_form($fields_tipo);
    echo fecha_form([botao("Salvar Cabeçalho", "index", "", "", "", "", "btn btn-info")]);

    echo "<hr>";
    echo "<h4>Lista de Campos Dinâmicos</h4>";

    $id_tipo_int = (int)$id_tipo;
    if ($id_tipo_int <= 0) {
        echo "<div class='alert alert-danger'>ID do tipo inválido para listagem.</div>";
        rodape();
        exit;
    }

    $queryBase = "SELECT * FROM camp_documento_modulo WHERE camp_nb_tipo_doc = $id_tipo_int AND camp_tx_status = 'ativo' ORDER BY camp_nb_ordem ASC";
    $res = query($queryBase);
    
    if (!$res || !is_object($res)) {
        echo "<div class='alert alert-warning'>Houve um problema ao carregar a lista de campos.</div>";
        if (!$res) echo "Erro SQL: " . mysqli_error($conn);
    } else {
        echo "<div class='table-responsive'><table class='table table-bordered table-striped'>";
        echo "<thead><tr><th>Ordem</th><th>Rótulo</th><th>Tipo</th><th>Obrigatório</th><th>Ações</th></tr></thead>";
        echo "<tbody>";
        if (mysqli_num_rows($res) == 0) {
            echo "<tr><td colspan='5' class='text-center'>Nenhum campo configurado.</td></tr>";
        }
        while ($row = mysqli_fetch_assoc($res)) {
            echo "<tr>";
            echo "<td>{$row['camp_nb_ordem']}</td>";
            echo "<td>{$row['camp_tx_label']}</td>";
            echo "<td>{$row['camp_tx_tipo']}</td>";
            echo "<td>" . strtoupper($row['camp_tx_obrigatorio']) . "</td>";
            echo "<td>
                    <form method='post' style='display:inline;'>
                        <input type='hidden' name='id_tipo' value='{$id_tipo}'>
                        <input type='hidden' name='id_campo' value='{$row['camp_nb_id']}'>
                        <input type='hidden' name='acao' value='layout_campo'>
                        <button type='submit' class='btn btn-xs btn-default' title='Editar'><span class='glyphicon glyphicon-pencil'></span></button>
                    </form>
                    <form method='post' style='display:inline;' onsubmit='return confirm(\"Deseja excluir?\")'>
                        <input type='hidden' name='id_tipo' value='{$id_tipo}'>
                        <input type='hidden' name='id_campo' value='{$row['camp_nb_id']}'>
                        <input type='hidden' name='acao' value='excluirCampo'>
                        <button type='submit' class='btn btn-xs btn-danger' title='Excluir'><span class='glyphicon glyphicon-trash'></span></button>
                    </form>
                  </td>";
            echo "</tr>";
        }
        echo "</tbody></table></div>";
    }

    echo "<hr>";
    echo "<h4>Gerenciar Campos</h4>";
    
    $buttons = [
        botao("Adicionar Novo Campo", "layout_campo", "", "", "", "", "btn btn-primary"),
        botao("Voltar à Lista", "voltarLista")
    ];

    echo abre_form();
    echo "<input type='hidden' name='id_tipo' value='{$id_tipo}'>";
    echo fecha_form($buttons);

    rodape();
}

function voltarLista() {
    header("Location: ../cadastro_tipo_doc.php");
    exit;
}

$acao = $_POST['acao'] ?? 'index';
if (function_exists($acao)) {
    $acao();
} else {
    index();
}
?>
