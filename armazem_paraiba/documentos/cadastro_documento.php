<?php
include_once "../conecta.php";

echo "
<script>
    function hideLoading() {
        if(document.getElementsByClassName('loading')[0] != undefined){
            document.getElementsByClassName('loading')[0].style.display = 'none';
        }
    }
    document.addEventListener('DOMContentLoaded', hideLoading);
    setTimeout(hideLoading, 3000);
</script>";

function novoDocumento() {
    cabecalho("Novo Documento");
    
    // Lista de tipos de documentos ativos
    $tiposQuery = "SELECT tipo_nb_id, tipo_tx_nome FROM tipos_documentos WHERE tipo_tx_status = 'ativo' ORDER BY tipo_tx_nome ASC";
    $res = query($tiposQuery);
    
    $opcoes = ['' => 'Selecione...'];
    while ($row = mysqli_fetch_assoc($res)) {
        $opcoes[$row['tipo_nb_id']] = $row['tipo_tx_nome'];
    }

    $fields = [
        combo("Modelo de Documento", "id_tipo", "", 6, $opcoes)
    ];

    $buttons = [
        botao("Avançar", "irParaPreenchimento"),
        botao("Voltar", "index")
    ];

    echo abre_form("Escolha um Modelo");
    echo linha_form($fields);
    echo fecha_form($buttons);
    
    rodape();
}

function irParaPreenchimento() {
    $id_tipo = $_POST['id_tipo'] ?? $_GET['id_tipo'] ?? 0;
    if (empty($id_tipo)) {
        set_status("ERRO: Selecione um tipo de documento.");
        novoDocumento();
        exit;
    }
    header("Location: preencher_documento.php?id_tipo=" . $id_tipo);
    exit;
}

function index() {
    global $conn;
    cabecalho("Gestão de Documentos");
    
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
    
    // Botão para criar novo
    $buttons = [
        botao("Novo Documento", "novoDocumento")
    ];

    echo abre_form();
    echo "<input type='hidden' name='id_tipo' value='{$id_tipo}'>";
    echo fecha_form($buttons);

    // Listagem de documentos já gerados
    $gridFields = [
        "CÓDIGO" => "inst_nb_id",
        "TIPO" => "tipo_tx_nome",
        "CRIADO POR" => "user_tx_login",
        "DATA" => "inst_dt_criacao",
        "STATUS" => "inst_tx_status"
    ];

    $camposBusca = [
        "busca_tipo" => "tipo_tx_nome",
        "busca_user" => "user_tx_login"
    ];

    $queryBase = "SELECT i.*, t.tipo_tx_nome, u.user_tx_nome 
                  FROM inst_documento_modulo i
                  JOIN tipos_documentos t ON i.inst_nb_tipo_doc = t.tipo_nb_id
                  JOIN user u ON i.inst_nb_user = u.user_nb_id
                  WHERE i.inst_tx_status = 'ativo'";
    
    // Icones para Visualizar/Gerar PDF e Excluir
    $actions = [
        "tags" => [
            "<a href='processar_pdf.php?id={{ID}}' target='_blank' title='Gerar PDF'><span class='glyphicon glyphicon-file'></span></a>",
            "<form method='post' style='display:inline;' onsubmit='return confirm(\"Excluir documento?\")'>
                <input type='hidden' name='id_instancia' value='{{ID}}'>
                <input type='hidden' name='acao' value='excluirInstancia'>
                <button type='submit' class='btn btn-xs btn-danger' style='background:none;border:none;color:#d9534f;'><span class='glyphicon glyphicon-trash'></span></button>
             </form>"
        ]
    ];
    
    // Para simplificar e garantir funcionamento imediato, usarei uma tabela manual aqui também
    // já que o gridDinamico requer configurações extras no JS global.
    
    $res = query($queryBase . " ORDER BY inst_dt_criacao DESC");
    echo "<h3>Documentos Gerados</h3>";
    echo "<table class='table table-bordered table-striped'>";
    echo "<thead><tr><th>ID</th><th>Tipo</th><th>Usuário</th><th>Data</th><th>Status</th><th>Ações</th></tr></thead>";
    echo "<tbody>";
    while ($row = mysqli_fetch_assoc($res)) {
        echo "<tr>";
        echo "<td>{$row['inst_nb_id']}</td>";
        echo "<td>{$row['tipo_tx_nome']}</td>";
        echo "<td>{$row['user_tx_nome']}</td>";
        echo "<td>" . date("d/m/Y H:i", strtotime($row['inst_dt_criacao'])) . "</td>";
        echo "<td>" . strtoupper($row['inst_tx_status']) . "</td>";
        echo "<td>
                <a href='processar_pdf.php?id={$row['inst_nb_id']}' target='_blank' class='btn btn-xs btn-info' title='PDF'><span class='glyphicon glyphicon-print'></span></a>
                <form method='post' style='display:inline;' onsubmit='return confirm(\"Deseja excluir?\")'>
                    <input type='hidden' name='id_instancia' value='{$row['inst_nb_id']}'>
                    <input type='hidden' name='acao' value='excluirDocumento'>
                    <button type='submit' class='btn btn-xs btn-danger'><span class='glyphicon glyphicon-trash'></span></button>
                </form>
              </td>";
        echo "</tr>";
    }
    echo "</tbody></table>";

    rodape();
}

function excluirDocumento() {
    $id = $_POST['id_instancia'];
    remover('inst_documento_modulo', $id);
    set_status("Documento removido.");
    index();
    exit;
}

$acao = $_POST['acao'] ?? 'index';
if (function_exists($acao)) {
    $acao();
} else {
    index();
}
?>
