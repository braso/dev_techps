<?php
include_once "../conecta.php";

// Salva uma nova instancia de documento e seus valores de campos.
function salvarDocumento() {
    global $conn;
    $id_tipo = $_POST['id_tipo'];
    $id_user = $_SESSION['user_nb_id'] ?? 1;

    $dados_inst = [
        'inst_nb_tipo_doc' => $id_tipo,
        'inst_nb_user' => $id_user,
        'inst_tx_status' => 'ativo'
    ];
    $res_inst = inserir('inst_documento_modulo', array_keys($dados_inst), array_values($dados_inst));
    $id_instancia = $res_inst[0];

    // Salvar valores
    $campos_res = query("SELECT * FROM camp_documento_modulo WHERE camp_nb_tipo_doc = $id_tipo AND camp_tx_status = 'ativo'");
    while ($c = mysqli_fetch_assoc($campos_res)) {
        $valor = $_POST['campo_' . $c['camp_nb_id']] ?? '';
        $dados_valor = [
            'valo_nb_instancia' => $id_instancia,
            'valo_nb_campo' => $c['camp_nb_id'],
            'valo_tx_valor' => $valor,
            'valo_tx_status' => 'ativo'
        ];
        inserir('valo_documento_modulo', array_keys($dados_valor), array_values($dados_valor));
    }

    set_status("Documento preenchido e salvo!");
    header("Location: cadastro_documento.php?id_tipo=$id_tipo");
    exit;
}

// Renderiza formulario dinamico de preenchimento conforme layout configurado.
function index() {
    global $conn;
    cabecalho("Preencher Documento");
    
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

    $id_tipo = $_POST['id_tipo'] ?? $_GET['id_tipo'] ?? $_POST['id'] ?? $_GET['id'];
    if (empty($id_tipo)) {
        header("Location: cadastro_documento.php");
        exit;
    }

    $a_tipo = carregar('tipos_documentos', $id_tipo);
    echo "<h3>Novo: " . ($a_tipo['tipo_tx_nome'] ?? "Documento") . "</h3>";

    // Busca os campos configurados
    $campos_res = query("SELECT * FROM camp_documento_modulo WHERE camp_nb_tipo_doc = $id_tipo AND camp_tx_status = 'ativo' ORDER BY camp_nb_ordem ASC");

    $fields = [
        campo_hidden("id_tipo", $id_tipo)
    ];

    while ($campo = mysqli_fetch_assoc($campos_res)) {
        $nome_input = "campo_" . $campo['camp_nb_id'];
        $label = $campo['camp_tx_label'];
        if ($campo['camp_tx_obrigatorio'] == 'sim') $label .= "*";

        switch ($campo['camp_tx_tipo']) {
            case 'texto_curto':
                $fields[] = campo($label, $nome_input, "", 4, "", "placeholder='{$campo['camp_tx_placeholder']}'");
                break;
            case 'texto_longo':
                $fields[] = textarea($label, $nome_input, "", 12);
                break;
            case 'data':
                $fields[] = campo_data($label, $nome_input, date("Y-m-d"), 3);
                break;
            case 'selecao':
                $opcoes_array = explode(',', $campo['camp_tx_opcoes']);
                $opcoes = [];
                foreach($opcoes_array as $opt) {
                    $opt = trim($opt);
                    if($opt != "") $opcoes[$opt] = $opt;
                }
                $fields[] = combo($label, $nome_input, "", 4, $opcoes);
                break;
            case 'usuario':
                $fields[] = campo($label, $nome_input, $_SESSION['user_tx_nome'] ?? '', 4, "", "readonly");
                break;
            case 'setor':
                $fields[] = campo($label, $nome_input, $_SESSION['user_tx_setor'] ?? 'Não Identificado', 4, "", "readonly");
                break;
            case 'number':
                $fields[] = campo($label, $nome_input, "", 2, "MASCARA_NUMERO");
                break;
        }
    }

    $buttons = [
        botao("Gerar Documento", "salvarDocumento"),
        botao("Cancelar", "index", "", "", "../cadastro_documento.php")
    ];

    echo abre_form("Informações do Documento");
    echo "<input type='hidden' name='id_tipo' value='{$id_tipo}'>";
    echo linha_form($fields);
    echo fecha_form($buttons);

    rodape();
}

$acao = $_POST['acao'] ?? 'index';
if (function_exists($acao)) {
    $acao();
} else {
    index();
}
?>
