<?php
ob_start();
include "conecta.php";

function cadastrarEpiEstoque() {
    if (!empty($_POST["id"]) && empty($_POST["itens_json"])) {
        // Direct single-item update
        $id = (int)$_POST["id"];
        $vida_util = !empty($_POST["vida_util"]) ? (int)$_POST["vida_util"] : 0;
        
        $epi = [
            "ss_e_tx_grupo"         => $_POST["grupo"],
            "ss_e_tx_subgrupo"      => $_POST["subgrupo"],
            "ss_e_tx_item"          => $_POST["item"],
            "ss_e_tx_descricao"     => $_POST["descricao"],
            "ss_e_tx_fabricante"    => $_POST["fabricante"],
            "ss_e_tx_modelo"        => $_POST["modelo"] ?? "",
            "ss_e_tx_variacoes"     => $_POST["variacoes"] ?? "",
            "ss_e_tx_ca"            => $_POST["ca"],
            "ss_e_nb_vida_util"     => $vida_util,
            "ss_e_tx_status"        => $_POST["status"] ?? "ativo"
        ];
        
        atualizar("ss_epi", array_keys($epi), array_values($epi), $id);
        
        $fotos_mantidas = !empty($_POST["fotos_mantidas"]) ? $_POST["fotos_mantidas"] : "";
        $new_paths = [];
        if (!empty($_FILES["foto"]["name"][0])) {
            $allowed = ["image/jpeg", "image/png", "image/jpg", "image/webp"];
            $total_files = count($_FILES["foto"]["name"]);
            $dir_foto = "arquivos/epi/{$id}/";
            $dir_foto_abs = $_SERVER["DOCUMENT_ROOT"] . $_ENV["APP_PATH"] . "/" . $dir_foto;
            if (!is_dir($dir_foto_abs)) {
                mkdir($dir_foto_abs, 0777, true);
            }
            for ($i = 0; $i < $total_files; $i++) {
                if ($_FILES["foto"]["error"][$i] === UPLOAD_ERR_OK) {
                    $file_type = $_FILES["foto"]["type"][$i];
                    if (in_array($file_type, $allowed)) {
                        $nomeOriginal = basename($_FILES["foto"]["name"][$i]);
                        $ext = pathinfo($nomeOriginal, PATHINFO_EXTENSION);
                        $target_name = "FOTO_{$id}_" . time() . "_" . $i . "." . $ext;
                        $target_path = $dir_foto_abs . $target_name;
                        if (move_uploaded_file($_FILES["foto"]["tmp_name"][$i], $target_path)) {
                            $new_paths[] = $dir_foto . $target_name;
                        }
                    }
                }
            }
        }
        $fotos_existentes_array = array_filter(explode(",", $fotos_mantidas));
        $final_paths = array_merge($fotos_existentes_array, $new_paths);
        $final_paths_str = implode(",", $final_paths);
        atualizar("ss_epi", ["ss_e_tx_foto"], [$final_paths_str ? $final_paths_str : null], $id);
        
        set_status("Item atualizado com sucesso no estoque!");
        redireciona("estoque_epi.php");
        exit;
    }

    $itensJson = $_POST["itens_json"] ?? "";
    $itens = json_decode($itensJson, true);
    
    if (empty($itens) || !is_array($itens)) {
        set_status("ERRO: Nenhum item na lista para gravar.");
        redireciona("cadastrar_epi_estoque.php");
        exit;
    }

    $gravados = 0;
    foreach ($itens as $item) {
        $vida_util = !empty($item["vida_util"]) ? (int)$item["vida_util"] : 0;
        
        $epi = [
            "ss_e_tx_grupo"         => $item["grupo"],
            "ss_e_tx_subgrupo"      => $item["subgrupo"],
            "ss_e_tx_item"          => $item["item"],
            "ss_e_tx_descricao"     => $item["descricao"],
            "ss_e_tx_fabricante"    => $item["fabricante"],
            "ss_e_tx_modelo"        => $item["modelo"] ?? "",
            "ss_e_tx_variacoes"     => $item["variacoes"] ?? "",
            "ss_e_tx_ca"            => $item["ca"],
            "ss_e_nb_vida_util"     => $vida_util,
            "ss_e_tx_status"        => $item["status"] ?? "ativo",
            "ss_e_tx_cadastro_tipo" => "estoque"
        ];

        if (!empty($item["id"])) {
            $id = (int)$item["id"];
            atualizar("ss_epi", array_keys($epi), array_values($epi), $id);
        } else {
            $res = inserir("ss_epi", array_keys($epi), array_values($epi));
            $id = (int)$res[0];
        }

        $existing_paths = [];
        if (!empty($item["fotos_existentes"])) {
            $existing_paths = array_filter(explode(",", $item["fotos_existentes"]));
        }

        // Processamento de novas fotos em Base64
        $new_paths = [];
        if (!empty($item["fotos"]) && is_array($item["fotos"])) {
            foreach ($item["fotos"] as $fKey => $fObj) {
                if (!empty($fObj["base64"]) && strpos($fObj["base64"], "data:image/") === 0) {
                    $partes = explode(',', $fObj["base64"]);
                    if (count($partes) > 1) {
                        $base64_data = $partes[1];
                        $extensao = "jpg";
                        if (preg_match('/^data:image\/(\w+);base64/', $fObj["base64"], $match)) {
                            $extensao = strtolower($match[1]);
                        }
                        
                        $dir_foto = "arquivos/epi/{$id}/";
                        $dir_foto_abs = $_SERVER["DOCUMENT_ROOT"] . $_ENV["APP_PATH"] . "/" . $dir_foto;
                        if (!is_dir($dir_foto_abs)) {
                            mkdir($dir_foto_abs, 0777, true);
                        }
                        
                        $caminho_foto_abs = $dir_foto_abs . "FOTO_{$id}_" . time() . "_" . $fKey . "." . $extensao;
                        $conteudo = base64_decode($base64_data);
                        if (file_put_contents($caminho_foto_abs, $conteudo)) {
                            $new_paths[] = $dir_foto . "FOTO_{$id}_" . time() . "_" . $fKey . "." . $extensao;
                        }
                    }
                }
            }
        }

        $final_paths = array_merge($existing_paths, $new_paths);
        $final_paths_str = implode(",", $final_paths);
        atualizar("ss_epi", ["ss_e_tx_foto"], [$final_paths_str ? $final_paths_str : null], $id);

        $gravados++;
    }

    set_status("Itens cadastrados no estoque com sucesso!");
    redireciona("estoque_epi.php");
    exit;
}

function salvarFabricanteAjax() {
    global $conn;

    $nome = trim($_POST["nome"] ?? "");
    $cnpjDigits = preg_replace('/[^0-9]/', '', trim($_POST["cnpj"] ?? ""));
    $fantasia = trim($_POST["nome_fantasia"] ?? "");
    $telefone = trim($_POST["telefone"] ?? "");

    if (empty($nome) || empty($cnpjDigits)) {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Nome do fabricante e CNPJ são obrigatórios."]);
        exit;
    }

    if (strlen($cnpjDigits) !== 14) {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "CNPJ inválido. Informe os 14 dígitos."]);
        exit;
    }

    if (mb_strlen($nome) > 100) {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "O nome do fabricante deve ter no máximo 100 caracteres."]);
        exit;
    }

    $nome_e = mysqli_real_escape_string($conn, $nome);
    $cnpj_e = mysqli_real_escape_string($conn, $cnpjDigits);
    $fantasia_e = mysqli_real_escape_string($conn, $fantasia);
    $telefone_e = mysqli_real_escape_string($conn, $telefone);
    $user = (int)($_SESSION["user_nb_id"] ?? 0);

    // Verifica duplicidade comparando apenas os dígitos (aceita formatos com/sem pontuação)
    $existe = query("SELECT ss_fa_nb_id FROM ss_fabricante WHERE REPLACE(REPLACE(REPLACE(IFNULL(ss_fa_tx_cnpj, ''), '.', ''), '/', ''), '-', '') = '{$cnpj_e}' LIMIT 1");
    if ($existe && mysqli_num_rows($existe) > 0) {
        $id = (int)mysqli_fetch_assoc($existe)["ss_fa_nb_id"];
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Já existe um fabricante cadastrado com este CNPJ.", "id" => $id]);
        exit;
    }

    // Também evita duplicidade por nome (sem CNPJ) para registros legados
    $existeNome = query("SELECT ss_fa_nb_id FROM ss_fabricante WHERE LOWER(ss_fa_tx_nome) = LOWER('{$nome_e}') LIMIT 1");
    if ($existeNome && mysqli_num_rows($existeNome) > 0) {
        $id = (int)mysqli_fetch_assoc($existeNome)["ss_fa_nb_id"];
        ob_clean();
        echo json_encode(["status" => "success", "message" => "Fabricante já cadastrado.", "id" => $id, "nome" => $nome, "duplicado" => true]);
        exit;
    }

    $res = query(
        "INSERT INTO ss_fabricante (ss_fa_tx_nome, ss_fa_tx_nome_fantasia, ss_fa_tx_cnpj, ss_fa_tx_telefone, ss_fa_tx_status, ss_fa_nb_userCadastro, ss_fa_tx_dataCadastro)
         VALUES ('{$nome_e}', '{$fantasia_e}', '{$cnpj_e}', '{$telefone_e}', 'ativo', {$user}, NOW())"
    );
    if (!$res) {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Erro ao cadastrar fabricante: " . ($GLOBALS["last_sql_error"] ?? "erro desconhecido")]);
        exit;
    }

    $id = mysqli_insert_id($conn);
    ob_clean();
    echo json_encode(["status" => "success", "message" => "Fabricante cadastrado com sucesso!", "id" => $id, "nome" => $nome, "cnpj" => formatarCnpj($cnpjDigits)]);
    exit;
}



function index() {
    $isEdit = false;
    if (!empty($_POST["id"])) {
        if (is_array($_POST["id"])) {
            $_POST["id"] = $_POST["id"][0];
        }
        $isEdit = true;
        $epi = carregar("ss_epi", $_POST["id"]);
        foreach ($epi as $key => $value) {
            $cleanedKey = str_replace("ss_e_tx_", "", $key);
            $cleanedKey = str_replace("ss_e_nb_", "", $cleanedKey);
            if (empty($_POST[$cleanedKey])) {
                $_POST[$cleanedKey] = $value;
            }
        }
    }

    // Carregar EPIs universais para selects encadeados
    $sqlUniversal = query("SELECT DISTINCT ss_e_tx_grupo, ss_e_tx_subgrupo, ss_e_tx_item, ss_e_tx_variacoes FROM ss_epi WHERE ss_e_tx_cadastro_tipo = 'universal' AND ss_e_tx_status = 'ativo' ORDER BY ss_e_tx_grupo, ss_e_tx_subgrupo, ss_e_tx_item");
    $universalEpis = [];
    if ($sqlUniversal) {
        while ($row = mysqli_fetch_assoc($sqlUniversal)) {
            $universalEpis[] = [
                "grupo" => $row["ss_e_tx_grupo"],
                "subgrupo" => $row["ss_e_tx_subgrupo"],
                "item" => $row["ss_e_tx_item"],
                "variacoes" => $row["ss_e_tx_variacoes"] ?? ""
            ];
        }
    }

    cabecalho($isEdit ? "Editar EPI no Estoque" : "Ficha de EPI no Estoque");
    ?>
    <style>
        .campo-fit-content {
            min-width: 0 !important;
        }
        .campo-fit-content label {
            display: block !important;
            margin-bottom: 5px !important;
            font-weight: bold;
        }
        .select2-container--bootstrap, 
        .select2-container,
        span.select2-container {
            display: block !important;
            width: 100% !important;
        }
        span.select2-selection.select2-selection--single {
            width: 100% !important;
        }
    </style>
    <?php

    // Campos do formulário
    $campo_grupo       = combo("Grupo*", "grupo", $_POST["grupo"] ?? "", 4, ["" => "Carregando grupos..."]);
    $campo_subgrupo    = combo("EPI", "subgrupo", $_POST["subgrupo"] ?? "", 4, ["" => "Selecione o Grupo primeiro..."]);
    $campo_item        = combo("Descrição*", "item", $_POST["item"] ?? "", 4, ["" => "Selecione o Subgrupo primeiro..."]);

    // Fabricantes (lista com busca e botão + para cadastro rápido — mesma regra do Fornecedor)
    $sqlFabricantes = query("SELECT ss_fa_nb_id, ss_fa_tx_nome FROM ss_fabricante WHERE ss_fa_tx_status = 'ativo' ORDER BY ss_fa_tx_nome ASC");
    $fabricanteOptionsHtml = '<option value="">Selecione o Fabricante</option>';
    $fabricanteSelecionado = $_POST["fabricante"] ?? "";
    $fabricanteNaLista = false;
    if ($sqlFabricantes) {
        while ($rowFa = mysqli_fetch_assoc($sqlFabricantes)) {
            $nomeFa = htmlspecialchars($rowFa["ss_fa_tx_nome"]);
            $selFa = ($nomeFa === $fabricanteSelecionado) ? " selected" : "";
            if ($selFa !== "") {
                $fabricanteNaLista = true;
            }
            $fabricanteOptionsHtml .= "<option value=\"{$nomeFa}\"{$selFa}>{$nomeFa}</option>";
        }
    }
    // Garante o fabricante já gravado em registros anteriores (antes da tabela) no select
    if (!empty($fabricanteSelecionado) && !$fabricanteNaLista) {
        $fabricanteOptionsHtml = '<option value="' . htmlspecialchars($fabricanteSelecionado) . '" selected>' . htmlspecialchars($fabricanteSelecionado) . '</option>' . $fabricanteOptionsHtml;
    }

    $campo_fabricante = '
        <style>
            #fabricante + .select2-container { width: auto !important; flex: 1; min-width: 0; }
            #fabricante + .select2-container .select2-selection--single { height: 30px; }
            #fabricante + .select2-container .select2-selection__rendered { line-height: 28px; padding-left: 8px; }
            #fabricante + .select2-container .select2-selection__arrow { height: 28px; }
        </style>
        <div class="col-sm-3 margin-bottom-5 campo-fit-content">
            <label>Fabricante</label>
            <div style="display: flex; align-items: center; gap: 6px;">
                <select name="fabricante" id="fabricante" class="form-control input-sm">
                    ' . $fabricanteOptionsHtml . '
                </select>
                <button type="button" class="btn btn-success btn-sm" id="btn_novo_fabricante" title="Cadastrar novo fabricante" style="white-space: nowrap; flex-shrink: 0;"><i class="fa fa-plus"></i> Novo</button>
            </div>
        </div>';
    $campo_modelo       = campo("Modelo", "modelo", $_POST["modelo"] ?? "", 3, "", "maxlength='100'");
    $campo_ca           = campo("MTE Certificado de Aprovacão (CA)", "ca", $_POST["ca"] ?? "", 3, "", "maxlength='50'");
    $campo_vida_util    = campo("Vida Útil (dias)", "vida_util", $_POST["vida_util"] ?? "0", 3, "MASCARA_NUMERO");

    $campo_variacoes    = campo("Variações (Numeração/Tamanho)", "variacoes", $_POST["variacoes"] ?? "", 3, "", "maxlength='255' placeholder='Ex: 42, 44, 46'");

    $campo_status       = combo("Status", "status", $_POST["status"] ?? "ativo", 3, ["ativo" => "Ativo", "inativo" => "Inativo"]);
    $campo_foto = '
        <div class="col-sm-12 margin-bottom-5 campo-fit-content" data-dropzone-foto>
            <label>Imagens do EPI (Opcional)</label>
            <div id="dropzone_foto" data-dropzone-area style="border: 2px dashed #b0b9c4; border-radius: 8px; background: #f8fafc; padding: 22px 15px; text-align: center; cursor: pointer; transition: all .2s;">
                <div style="font-size: 30px; color: #337ab7;"><i class="fa fa-cloud-upload"></i></div>
                <div style="font-size: 15px; font-weight: bold; color: #333; margin-top: 6px;">Arraste e solte as imagens aqui</div>
                <div style="color: #888; margin-top: 3px;">Você também pode clicar aqui para escolher da galeria ou usar a câmera</div>
                <div style="margin-top: 12px;">
                    <button type="button" class="btn btn-sm btn-primary" id="btn_escolher_fotos" data-galeria><i class="fa fa-folder-open"></i> Escolher da Galeria</button>
                    <button type="button" class="btn btn-sm btn-success" id="btn_tirar_foto" data-camera-btn><i class="fa fa-camera"></i> Tirar Foto (Câmera)</button>
                </div>
            </div>
            <input name="foto[]" id="foto_input" autocomplete="off" type="file" accept="image/*" multiple style="display: none;">
            <input name="foto_cam[]" id="foto_camera_input" autocomplete="off" type="file" accept="image/*" capture="environment" data-camera style="display: none;">
        </div>';

    $fotos = [];
    if (!empty($_POST["foto"])) {
        $fotos = array_filter(explode(",", $_POST["foto"]));
    }

    $preview_html = "";
    foreach ($fotos as $idx => $f) {
        $src = ss_resolve_foto_url($f);
        $preview_html .= '
            <div class="preview-item" data-path="' . htmlspecialchars($f) . '" style="display: inline-flex; align-items: center; gap: 5px; margin-right: 10px; margin-bottom: 10px; border: 1px solid #ccc; padding: 5px; border-radius: 4px;">
                <img src="' . $src . '" style="max-height: 80px; max-width: 80px; object-fit: cover; cursor: pointer;" onclick="verImagemMaior(\'' . $src . '\')">
                <button type="button" class="btn btn-danger btn-xs btn_remover_foto_existente" data-path="' . htmlspecialchars($f) . '" title="Remover"><i class="fa fa-remove"></i></button>
            </div>';
    }

    $preview_div = '
        <div class="col-sm-12 margin-bottom-5" id="preview_foto_container" style="margin-top: 15px; display: block;">
            <div id="existing_photos_container" style="display: inline-block;">' . $preview_html . '</div>
            <div id="new_photos_container" style="display: inline-block;"></div>
        </div>
    ';

    $campo_descricao   = textarea("Observações", "descricao", $_POST["descricao"] ?? "", 12, "style='height: 100px;'");

    // Configuração de botões do formulário principal
    $buttons = [];
    if ($isEdit) {
        $buttons[] = botao("Gravar Alterações", "cadastrarEpiEstoque", "id", $_POST["id"], "", "", "btn btn-success");
        $buttons[] = '<a href="estoque_epi.php" class="btn btn-default"><i class="fa fa-arrow-left"></i> Voltar</a>';
    } else {
        $buttons[] = '<button type="button" class="btn btn-primary" id="btn_adicionar_lista">Adicionar à Lista</button>';
        $buttons[] = '<button type="button" class="btn btn-default" id="btn_limpar_form">Limpar Campos</button>';
    }

    echo abre_form($isEdit ? "Editar Dados do EPI" : "Dados do EPI no Estoque");
    echo campo_hidden("remover_foto_atual", "0");
    echo campo_hidden("fotos_mantidas", $_POST["foto"] ?? "");
    echo linha_form([$campo_grupo, $campo_subgrupo, $campo_item]);
    echo linha_form([$campo_fabricante, $campo_modelo, $campo_ca, $campo_vida_util]);
    echo linha_form([$campo_variacoes, $campo_status, $campo_foto]);
    echo linha_form([$preview_div]);
    echo linha_form([$campo_descricao]);
    echo fecha_form($buttons);

    if (!$isEdit) {
        // Tabela de itens adicionados temporariamente (Apenas no cadastro de novo lote)
        echo "
        <div class='portlet light bordered' style='margin-top: 20px;'>
            <div class='portlet-title'>
                <div class='caption font-green-haze'>
                    <i class='fa fa-list font-green-haze'></i>
                    <span class='caption-subject bold uppercase'>Itens a serem Gravados no Estoque</span>
                </div>
            </div>
            <div class='portlet-body'>
                <div class='table-responsive'>
                    <table class='table table-striped table-bordered table-hover' id='tabela_itens_temp'>
                        <thead>
                            <tr>
                                <th>Grupo</th>
                                <th>EPI</th>
                                <th>Descrição</th>
                                <th>Fabricante</th>
                                <th>Modelo</th>
                                <th>Variações</th>
                                <th>CA</th>
                                <th>Vida Útil</th>
                                <th>Imagem</th>
                                <th>Status</th>
                                <th style='width: 100px;'>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Itens renderizados por JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        ";

        // Formulário final de gravação (Apenas no cadastro de novo lote)
        echo abre_form("Confirmar Gravação");
        echo '<input type="hidden" name="itens_json" id="itens_json" value="">';
        echo '<input type="hidden" name="id" value="">';
        
        $final_buttons = [];
        $final_buttons[] = botao("Gravar Todos os Itens", "cadastrarEpiEstoque", "", "", "", "", "btn btn-success");
        $final_buttons[] = '<a href="estoque_epi.php" class="btn btn-default"><i class="fa fa-arrow-left"></i> Voltar</a>';
        echo fecha_form($final_buttons);
    }
    ?>
    <!-- Modal Novo Fabricante -->
    <div class="modal fade" id="modal_novo_fabricante" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 11000;">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"><i class="fa fa-industry"></i> Novo Fabricante</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="control-label">Nome / Razão Social*</label>
                        <input type="text" class="form-control input-sm" id="nfa_nome" maxlength="100" placeholder="Ex: 3M do Brasil LTDA">
                    </div>
                    <div class="form-group">
                        <label class="control-label">Nome Fantasia</label>
                        <input type="text" class="form-control input-sm" id="nfa_nome_fantasia" maxlength="255" placeholder="Ex: 3M">
                    </div>
                    <div class="form-group">
                        <label class="control-label">CNPJ*</label>
                        <input type="text" class="form-control input-sm" id="nfa_cnpj" maxlength="18" placeholder="00.000.000/0000-00">
                    </div>
                    <div class="form-group">
                        <label class="control-label">Telefone</label>
                        <input type="text" class="form-control input-sm" id="nfa_telefone" maxlength="15" placeholder="(00) 00000-0000">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" id="btn_salvar_fabricante"><i class="fa fa-check"></i> Salvar Fabricante</button>
                </div>
            </div>
        </div>
    </div>
    <script>
    $(document).ready(function() {
        const isEditMode = <?php echo $isEdit ? 'true' : 'false'; ?>;

        // Fabricante: select2 com busca + cadastro rápido (mesma regra do Fornecedor)
        if (typeof $.fn.select2 === 'function') {
            $.fn.select2.defaults.set('theme', 'bootstrap');
            $('select[name="fabricante"]').select2({
                placeholder: 'Busque por nome...',
                allowClear: true,
                matcher: function(params, data) {
                    if (!params.term || params.term.trim() === '') {
                        return data;
                    }
                    var term = params.term.trim().toLowerCase();
                    var text = (data.text || '').toLowerCase();
                    if (text.indexOf(term) > -1) {
                        return data;
                    }
                    return null;
                }
            });
        }

        $('#btn_novo_fabricante').on('click', function() {
            $('#modal_novo_fabricante').modal('show');
            $('#nfa_nome').focus();
        });

        if (typeof $.fn.inputmask === 'function') {
            $('#nfa_cnpj').inputmask({ mask: ['99.999.999/9999-99'], placeholder: '00.000.000/000-00' });
            $('#nfa_telefone').inputmask({ mask: ['(99) 9999-9999', '(99) 99999-9999'], placeholder: '' });
        }

        $('#btn_salvar_fabricante').on('click', function() {
            var nome = $('#nfa_nome').val().trim();
            var cnpj = $('#nfa_cnpj').val().trim();
            if (!nome) {
                alert('Informe o nome/razão social do fabricante.');
                $('#nfa_nome').focus();
                return;
            }
            if (!cnpj) {
                alert('Informe o CNPJ.');
                $('#nfa_cnpj').focus();
                return;
            }

            var $btn = $(this).prop('disabled', true);

            $.ajax({
                url: 'cadastrar_epi_estoque.php?acao=salvarFabricanteAjax',
                type: 'POST',
                data: {
                    nome: nome,
                    nome_fantasia: $('#nfa_nome_fantasia').val().trim(),
                    cnpj: cnpj,
                    telefone: $('#nfa_telefone').val().trim()
                },
                dataType: 'json',
                success: function(resp) {
                    $btn.prop('disabled', false);
                    if (resp.status === 'success') {
                        var novoOption = new Option(resp.nome, resp.nome, false, true);
                        $('#fabricante').append(novoOption).trigger('change');
                        $('#nfa_nome').val('');
                        $('#nfa_nome_fantasia').val('');
                        $('#nfa_cnpj').val('');
                        $('#nfa_telefone').val('');
                        $('#modal_novo_fabricante').modal('hide');
                        if (!resp.duplicado) {
                            alert('Fabricante cadastrado com sucesso!');
                        }
                    } else {
                        alert(resp.message || 'Erro ao cadastrar fabricante.');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false);
                    alert('Erro de comunicação com o servidor.');
                }
            });
        });
        
        // Select2 & cascading dropdown logic
        const data = <?php echo json_encode($universalEpis); ?>;
        const $grupoSelect = $('select[name="grupo"]');
        const $subgrupoSelect = $('select[name="subgrupo"]');
        const $itemSelect = $('select[name="item"]');
        
        const currentGrupo = <?php echo json_encode($_POST['grupo'] ?? ''); ?>;
        const currentSubgrupo = <?php echo json_encode($_POST['subgrupo'] ?? ''); ?>;
        const currentItem = <?php echo json_encode($_POST['item'] ?? ''); ?>;

        function populateGrupos() {
            let grupos = [...new Set(data.map(d => d.grupo))];
            if (currentGrupo && !grupos.includes(currentGrupo)) {
                grupos.push(currentGrupo);
            }
            grupos.sort();
            
            $grupoSelect.html('<option value="">Selecione o Grupo</option>');
            grupos.forEach(g => {
                if (g) {
                    $grupoSelect.append(new Option(g, g, g === currentGrupo, g === currentGrupo));
                }
            });
            if (typeof $.fn.select2 === 'function') {
                $grupoSelect.select2();
            }
            $grupoSelect.trigger('change');
        }

        $grupoSelect.on('change', function() {
            const selectedGrupo = $(this).val();
            $subgrupoSelect.html('<option value="">Selecione o Subgrupo</option>').prop('disabled', !selectedGrupo);
            $itemSelect.html('<option value="">Selecione o Item</option>').prop('disabled', true);
            
            if (selectedGrupo) {
                let subgrupos = [];
                data.filter(d => d.grupo === selectedGrupo).forEach(d => {
                    if (d.subgrupo) {
                        d.subgrupo.split(/[;,]/).forEach(s => {
                            let ts = s.trim();
                            if (ts && !subgrupos.includes(ts)) {
                                subgrupos.push(ts);
                            }
                        });
                    }
                });
                if (selectedGrupo === currentGrupo && currentSubgrupo && !subgrupos.includes(currentSubgrupo)) {
                    subgrupos.push(currentSubgrupo);
                }
                subgrupos.sort();
                
                subgrupos.forEach(s => {
                    if (s) {
                        $subgrupoSelect.append(new Option(s, s, s === currentSubgrupo, s === currentSubgrupo));
                    }
                });
            }
            if (typeof $.fn.select2 === 'function') {
                $subgrupoSelect.select2();
                $itemSelect.select2();
            }
            $subgrupoSelect.trigger('change');
        });

        $subgrupoSelect.on('change', function() {
            const selectedGrupo = $grupoSelect.val();
            const selectedSubgrupo = $(this).val();
            $itemSelect.html('<option value="">Selecione o Item</option>').prop('disabled', !selectedSubgrupo);
            
            if (selectedGrupo && selectedSubgrupo) {
                let items = [];
                data.filter(d => d.grupo === selectedGrupo).forEach(d => {
                    let hasSub = false;
                    if (d.subgrupo) {
                        d.subgrupo.split(/[;,]/).forEach(s => {
                            if (s.trim() === selectedSubgrupo) hasSub = true;
                        });
                    }
                    if (hasSub && d.item) {
                        d.item.split(/[;,]/).forEach(i => {
                            let ti = i.trim();
                            if (ti && !items.includes(ti)) {
                                items.push(ti);
                            }
                        });
                    }
                });
                if (selectedGrupo === currentGrupo && selectedSubgrupo === currentSubgrupo && currentItem && !items.includes(currentItem)) {
                    items.push(currentItem);
                }
                items.sort();
                
                items.forEach(i => {
                    if (i) {
                        $itemSelect.append(new Option(i, i, i === currentItem, i === currentItem));
                    }
                });
            }
            if (typeof $.fn.select2 === 'function') {
                $itemSelect.select2();
            }

            // Preencher automaticamente as variações do item universal selecionado
            const matchedItem = data.find(d => d.grupo === $grupoSelect.val() && d.item === $(this).val());
            if (matchedItem && matchedItem.variacoes) {
                $('input[name="variacoes"]').val(matchedItem.variacoes);
            }
        });

        populateGrupos();

        let tempFotosArray = []; // Array de {base64: "...", name: "..."}
        
        // Detectar alteração de novos arquivos
        $('#foto_input').on('change', function(event) {
            $('#new_photos_container').empty();
            tempFotosArray = [];
            
            const files = event.target.files;
            if (files && files.length > 0) {
                const allowedTypes = ["image/jpeg", "image/png", "image/jpg"];
                
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    if (!allowedTypes.includes(file.type)) {
                        Swal.fire('Atenção', 'O arquivo "' + file.name + '" não é uma imagem válida (JPEG, JPG ou PNG).', 'warning');
                        continue;
                    }
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        tempFotosArray.push({
                            base64: e.target.result,
                            name: file.name
                        });
                        
                        // Mostrar preview da imagem nova
                        const imgHtml = `
                            <div class="preview-item-new" style="display: inline-flex; align-items: center; gap: 5px; margin-right: 10px; margin-bottom: 10px; border: 1px solid #aaa; padding: 5px; border-radius: 4px; background: #f9f9f9;">
                                <img src="${e.target.result}" style="max-height: 80px; max-width: 80px; object-fit: cover; cursor: pointer;" onclick="verImagemMaior('${e.target.result}')" title="Nova Imagem">
                            </div>`;
                        $('#new_photos_container').append(imgHtml);
                    };
                    reader.readAsDataURL(file);
                }
            }
        });

        // Dropzone: arrastar e soltar imagens, galeria ou câmera
        var dropzoneEl = document.getElementById('dropzone_foto');
        var fotoInputEl = document.getElementById('foto_input');

        function adicionarArquivosAoSistema(novosArquivos) {
            if (!window.DataTransfer || !fotoInputEl || !novosArquivos || novosArquivos.length === 0) {
                return false;
            }
            var dt = new DataTransfer();
            var existentes = fotoInputEl.files ? Array.prototype.slice.call(fotoInputEl.files) : [];
            existentes.forEach(function(f) { dt.items.add(f); });
            Array.prototype.slice.call(novosArquivos).forEach(function(f) { dt.items.add(f); });
            fotoInputEl.files = dt.files;
            return true;
        }

        if (dropzoneEl) {
            $(dropzoneEl).on('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(dropzoneEl).css('border-color', '#337ab7').css('background', '#eef4fb');
            });
            $(dropzoneEl).on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(dropzoneEl).css('border-color', '#b0b9c4').css('background', '#f8fafc');
            });
            $(dropzoneEl).on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(dropzoneEl).css('border-color', '#b0b9c4').css('background', '#f8fafc');
                var arquivos = e.originalEvent.dataTransfer ? e.originalEvent.dataTransfer.files : null;
                if (!arquivos || arquivos.length === 0) return;
                if (adicionarArquivosAoSistema(arquivos)) {
                    $('#foto_input').trigger('change');
                } else {
                    alert('Seu navegador não suporta arrastar arquivos. Use os botões abaixo.');
                }
            });
            $(dropzoneEl).on('click', function() {
                $('#foto_input').click();
            });
        }

        $('#btn_escolher_fotos').on('click', function(e) {
            e.stopPropagation();
            $('#foto_input').click();
        });
        $('#btn_tirar_foto').on('click', function(e) {
            e.stopPropagation();
            $('#foto_camera_input').click();
        });
        $('#foto_camera_input').on('change', function() {
            var arquivos = this.files;
            if (!arquivos || arquivos.length === 0) return;
            if (adicionarArquivosAoSistema(arquivos)) {
                $('#foto_input').trigger('change');
            }
            this.value = '';
        });

        // Clique para remover foto existente
        $(document).on('click', '.btn_remover_foto_existente', function() {
            const pathToRemove = $(this).attr('data-path');
            $(this).closest('.preview-item').remove();
            
            let mantidas = $('#fotos_mantidas').val().split(',').filter(Boolean);
            mantidas = mantidas.filter(p => p !== pathToRemove);
            $('#fotos_mantidas').val(mantidas.join(','));
            $('#remover_foto_atual').val('1'); // Sinaliza que fotos foram modificadas/removidas
        });

        function removerPreviaFoto() {
            tempFotosArray = [];
            $('#foto_input').val('');
            $('#new_photos_container').empty();
        }

        if (!isEditMode) {
            $('#itens_json').closest('form').attr('name', 'form_gravar_lote');
            
            // Lógica de lista temporária de cadastro em lote
            let itemsList = [];
            let editIndex = null;
            
            function renderTable() {
                const tbody = $('#tabela_itens_temp tbody');
                tbody.empty();
                
                if (itemsList.length === 0) {
                    tbody.append('<tr><td colspan="11" style="text-align: center; color: #999;">Nenhum item adicionado à lista.</td></tr>');
                    $('#itens_json').val('');
                    return;
                }
                
                itemsList.forEach((item, index) => {
                    const row = $('<tr>');
                    row.append($('<td>').text(item.grupo));
                    row.append($('<td>').text(item.subgrupo));
                    row.append($('<td>').text(item.item));
                    row.append($('<td>').text(item.fabricante || '---'));
                    row.append($('<td>').text(item.modelo || '---'));
                    row.append($('<td>').text(item.variacoes || '---'));
                    row.append($('<td>').text(item.ca || '---'));
                    row.append($('<td>').text(item.vida_util + ' dias'));
                    
                    let fotosHtml = '';
                    let allFotos = [];
                    if (item.fotos_existentes) {
                        allFotos = allFotos.concat(item.fotos_existentes.split(',').filter(Boolean));
                    }
                    if (item.fotos) {
                        item.fotos.forEach(f => {
                            allFotos.push(f.base64 || f);
                        });
                    }
                    allFotos.forEach(fotoPath => {
                        let resolvedSrc = ssResolveFotoUrl(fotoPath);
                        fotosHtml += `<a href="${resolvedSrc}" target="_blank" style="margin-right: 5px;"><img src="${resolvedSrc}" style="max-height: 40px; max-width: 40px; border-radius: 4px; object-fit: cover;"></a>`;
                    });
                    if (!fotosHtml) fotosHtml = '<span class="text-muted">-</span>';
                    row.append($('<td>').html(fotosHtml));
                    
                    row.append($('<td>').html('<span class="label label-' + (item.status === 'ativo' ? 'success' : 'danger') + '">' + item.status.toUpperCase() + '</span>'));
                    
                    const actionsTd = $('<td>');
                    const editBtn = $('<button type="button" class="btn btn-xs btn-warning" style="margin-right: 5px;"><i class="fa fa-edit"></i></button>');
                    editBtn.on('click', () => editItem(index));
                    
                    const deleteBtn = $('<button type="button" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>');
                    deleteBtn.on('click', () => removeItem(index));
                    
                    actionsTd.append(editBtn).append(deleteBtn);
                    row.append(actionsTd);
                    tbody.append(row);
                });
                
                $('#itens_json').val(JSON.stringify(itemsList));
            }

            function editItem(index) {
                editIndex = index;
                const item = itemsList[index];
                
                $('select[name="grupo"]').val(item.grupo).trigger('change');
                $('select[name="subgrupo"]').val(item.subgrupo).trigger('change');
                $('select[name="item"]').val(item.item).trigger('change');
                
                $('select[name="fabricante"]').val(item.fabricante).trigger('change');
                $('input[name="modelo"]').val(item.modelo || '');
                $('input[name="variacoes"]').val(item.variacoes || '');
                $('input[name="ca"]').val(item.ca);
                $('input[name="vida_util"]').val(item.vida_util);
                $('select[name="status"]').val(item.status).trigger('change');
                $('textarea[name="descricao"]').val(item.descricao);
                
                $('#existing_photos_container').empty();
                $('#new_photos_container').empty();
                tempFotosArray = [];

                let allExisting = [];
                if (item.fotos_existentes) {
                    allExisting = item.fotos_existentes.split(',').filter(Boolean);
                }
                
                $('#fotos_mantidas').val(item.fotos_existentes || "");
                
                allExisting.forEach(f => {
                    let src = ssResolveFotoUrl(f);
                    let pItem = `
                        <div class="preview-item" data-path="${f}" style="display: inline-flex; align-items: center; gap: 5px; margin-right: 10px; margin-bottom: 10px; border: 1px solid #ccc; padding: 5px; border-radius: 4px;">
                            <img src="${src}" style="max-height: 80px; max-width: 80px; object-fit: cover; cursor: pointer;" onclick="verImagemMaior('${src}')">
                            <button type="button" class="btn btn-danger btn-xs btn_remover_foto_existente" data-path="${f}"><i class="fa fa-remove"></i></button>
                        </div>`;
                    $('#existing_photos_container').append(pItem);
                });

                if (item.fotos) {
                    item.fotos.forEach(fObj => {
                        let pathOrBase64 = fObj.base64 || fObj;
                        if (pathOrBase64.indexOf('data:image/') === 0) {
                            tempFotosArray.push(fObj);
                            let imgHtml = `
                                <div class="preview-item-new" style="display: inline-flex; align-items: center; gap: 5px; margin-right: 10px; margin-bottom: 10px; border: 1px solid #aaa; padding: 5px; border-radius: 4px; background: #f9f9f9;">
                                    <img src="${pathOrBase64}" style="max-height: 80px; max-width: 80px; object-fit: cover; cursor: pointer;" onclick="verImagemMaior('${pathOrBase64}')">
                                </div>`;
                            $('#new_photos_container').append(imgHtml);
                        } else {
                            let src = ssResolveFotoUrl(pathOrBase64);
                            let pItem = `
                                <div class="preview-item" data-path="${pathOrBase64}" style="display: inline-flex; align-items: center; gap: 5px; margin-right: 10px; margin-bottom: 10px; border: 1px solid #ccc; padding: 5px; border-radius: 4px;">
                                    <img src="${src}" style="max-height: 80px; max-width: 80px; object-fit: cover; cursor: pointer;" onclick="verImagemMaior('${src}')">
                                    <button type="button" class="btn btn-danger btn-xs btn_remover_foto_existente" data-path="${pathOrBase64}"><i class="fa fa-remove"></i></button>
                                </div>`;
                            $('#existing_photos_container').append(pItem);
                        }
                    });
                }
                
                $('#btn_adicionar_lista').text('Atualizar na Lista').removeClass('btn-primary').addClass('btn-info');
            }

            function removeItem(index) {
                Swal.fire({
                    title: 'Remover?',
                    text: 'Deseja remover este item da lista temporária?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sim, remover',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        itemsList.splice(index, 1);
                        renderTable();
                        if (editIndex === index) {
                            limparForm();
                        }
                    }
                });
            }

            function limparForm() {
                editIndex = null;
                $('select[name="grupo"]').val('').trigger('change');
                $('select[name="fabricante"]').val('').trigger('change');
                $('input[name="modelo"]').val('');
                $('input[name="variacoes"]').val('');
                $('input[name="ca"]').val('');
                $('input[name="vida_util"]').val('0');
                $('select[name="status"]').val('ativo').trigger('change');
                $('textarea[name="descricao"]').val('');
                removerPreviaFoto();
                $('#btn_adicionar_lista').text('Adicionar à Lista').removeClass('btn-info').addClass('btn-primary');
            }

            $('#btn_adicionar_lista').on('click', function() {
                const grupo = $grupoSelect.val();
                const subgrupo = $subgrupoSelect.val();
                const itemVal = $itemSelect.val();
                
                if (!grupo || !itemVal) {
                    Swal.fire('Atenção', 'Os campos Grupo e Descrição são obrigatórios.', 'warning');
                    return;
                }
                
                const itemData = {
                    id: editIndex !== null ? itemsList[editIndex].id : '',
                    grupo: grupo,
                    subgrupo: subgrupo,
                    item: itemVal,
                    fabricante: $('select[name="fabricante"]').val(),
                    modelo: $('input[name="modelo"]').val(),
                    variacoes: $('input[name="variacoes"]').val(),
                    ca: $('input[name="ca"]').val(),
                    vida_util: parseInt($('input[name="vida_util"]').val(), 10) || 0,
                    status: $('select[name="status"]').val() || 'ativo',
                    descricao: $('textarea[name="descricao"]').val(),
                    fotos: tempFotosArray.length > 0 ? tempFotosArray : (editIndex !== null ? itemsList[editIndex].fotos : []),
                    fotos_existentes: $('#fotos_mantidas').val() || ""
                };
                
                if (editIndex !== null) {
                    itemsList[editIndex] = itemData;
                } else {
                    itemsList.push(itemData);
                }
                
                limparForm();
                renderTable();
            });
            
            $('#btn_limpar_form').on('click', limparForm);

            let bypassValidation = false;
            $('form[name="form_gravar_lote"]').on('submit', function(e) {
                if (bypassValidation) {
                    return;
                }

                const activeBtn = $(document.activeElement);
                if (activeBtn.attr('name') === 'acao' && activeBtn.val() === 'voltar') {
                    if (itemsList.length > 0) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Sair sem salvar?',
                            text: 'Existem itens na lista temporária que ainda não foram gravados. Deseja realmente voltar e perder as alterações?',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Sim, sair',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                bypassValidation = true;
                                var form = $('form[name="form_gravar_lote"]');
                                var inputAcao = $('<input type="hidden" name="acao" value="voltar">');
                                form.append(inputAcao);
                                form.submit();
                            }
                        });
                    }
                    return;
                }

                if (itemsList.length === 0) {
                    e.preventDefault();
                    Swal.fire('Atenção', 'Adicione pelo menos um item à lista antes de gravar.', 'warning');
                }
            });

            renderTable();
        }
    });
    </script>
    <script src="<?php echo $_ENV["APP_PATH"]; ?>/armazem_paraiba/saude_seguranca/js/dropzone_foto.js"></script>
    <?php
    rodape();
}
