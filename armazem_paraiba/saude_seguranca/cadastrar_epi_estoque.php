<?php
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
            "ss_e_tx_ca"            => $_POST["ca"],
            "ss_e_nb_vida_util"     => $vida_util,
            "ss_e_tx_status"        => $_POST["status"] ?? "ativo"
        ];
        
        atualizar("ss_epi", array_keys($epi), array_values($epi), $id);
        
        $fotos_mantidas = !empty($_POST["fotos_mantidas"]) ? $_POST["fotos_mantidas"] : "";
        $new_paths = [];
        if (!empty($_FILES["foto"]["name"][0])) {
            $allowed = ["image/jpeg", "image/png", "image/jpg"];
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
    $sqlUniversal = query("SELECT DISTINCT ss_e_tx_grupo, ss_e_tx_subgrupo, ss_e_tx_item FROM ss_epi WHERE ss_e_tx_cadastro_tipo = 'universal' AND ss_e_tx_status = 'ativo' ORDER BY ss_e_tx_grupo, ss_e_tx_subgrupo, ss_e_tx_item");
    $universalEpis = [];
    if ($sqlUniversal) {
        while ($row = mysqli_fetch_assoc($sqlUniversal)) {
            $universalEpis[] = [
                "grupo" => $row["ss_e_tx_grupo"],
                "subgrupo" => $row["ss_e_tx_subgrupo"],
                "item" => $row["ss_e_tx_item"]
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

    $campo_fabricante   = campo("Fabricante", "fabricante", $_POST["fabricante"] ?? "", 3, "", "maxlength='100'");
    $campo_modelo       = campo("Modelo", "modelo", $_POST["modelo"] ?? "", 3, "", "maxlength='100'");
    $campo_ca           = campo("MTE Certificado de Aprovacão (CA)", "ca", $_POST["ca"] ?? "", 3, "", "maxlength='50'");
    $campo_vida_util    = campo("Vida Útil (dias)", "vida_util", $_POST["vida_util"] ?? "0", 3, "MASCARA_NUMERO");

    $campo_status       = combo("Status", "status", $_POST["status"] ?? "ativo", 3, ["ativo" => "Ativo", "inativo" => "Inativo"]);
    $campo_foto = '
        <div class="col-sm-3 margin-bottom-5 campo-fit-content">
            <label>Imagens (Selecione uma ou mais)</label>
            <input name="foto[]" id="foto_input" autocomplete="off" type="file" class="form-control input-sm campo-fit-content" multiple accept="image/jpeg,image/png,image/jpg">
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
    echo linha_form([$campo_status, $campo_foto]);
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
    <script>
    $(document).ready(function() {
        const isEditMode = <?php echo $isEdit ? 'true' : 'false'; ?>;
        
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
                    tbody.append('<tr><td colspan="10" style="text-align: center; color: #999;">Nenhum item adicionado à lista.</td></tr>');
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
                
                $('input[name="fabricante"]').val(item.fabricante);
                $('input[name="modelo"]').val(item.modelo || '');
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
                $('input[name="fabricante"]').val('');
                $('input[name="modelo"]').val('');
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
                    fabricante: $('input[name="fabricante"]').val(),
                    modelo: $('input[name="modelo"]').val(),
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
    <?php
    rodape();
}
