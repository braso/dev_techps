<?php
    // ini_set("display_errors", 1);
    // error_reporting(E_ALL);

    // header("Expires: 01 Jan 2001 00:00:00 GMT");
    // header("Cache-Control: no-cache, no-store, must-revalidate");
    // header("Cache-Control: post-check=0, pre-check=0", FALSE);

    include_once "utils/utils.php";
    include_once "utils/celular_opcoes.php";
    include_once "documentos/termos/funcoes_termos.php";
    include_once "load_env.php";
    include_once "conecta.php";
    mysqli_query($conn, "SET time_zone = '-3:00'");

    // Garante a estrutura do módulo de termos (o roteador global chama index()/ação
    // ainda durante o include do conecta.php, então chamamos dentro das funções).
    function garantirEstruturaTermosCelular(){
        global $conn;
        termos_ensure_tables($conn);
    }

    function ensureCelularEntidadeOpcional(){
        global $conn;
        if(!($conn instanceof mysqli)){
            return false;
        }
        $check = @mysqli_query($conn, "SHOW COLUMNS FROM celular LIKE 'celu_nb_entidade'");
        if(!($check instanceof mysqli_result) || !($row = mysqli_fetch_assoc($check))){
            return false;
        }
        if(strtoupper(strval($row["Null"] ?? "NO")) !== "NO"){
            return true;
        }
        try{
            if(!mysqli_query($conn, "ALTER TABLE celular MODIFY COLUMN celu_nb_entidade INT(11) NULL DEFAULT NULL")){
                error_log("[cadastro_celular] Falha ao liberar NULL em celular.celu_nb_entidade: " . mysqli_error($conn));
                return false;
            }
        }catch(Throwable $e){
            error_log("[cadastro_celular] Falha ao liberar NULL em celular.celu_nb_entidade: " . $e->getMessage());
            return false;
        }
        return true;
    }
    ensureCelularEntidadeOpcional();

    // --- Controle gerencial / auditoria de aparelhos ---
    function celular_registrar_movimentacao(int $celularId, $entAnt, $entNovo, $statusAnt, $statusNovo, string $motivo = ""): void {
        global $conn;
        if($celularId <= 0){ return; }
        $user = intval($_SESSION["user_nb_id"] ?? 0);
        $sql = "INSERT INTO celular_movimentacao
            (movi_nb_celular, movi_nb_entidade_anterior, movi_nb_entidade_novo, movi_tx_status_anterior, movi_tx_status_novo, movi_tx_motivo, movi_nb_user)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = @mysqli_prepare($conn, $sql);
        if(!$stmt){ return; }
        $ea = ($entAnt !== null && intval($entAnt) > 0) ? intval($entAnt) : null;
        $en = ($entNovo !== null && intval($entNovo) > 0) ? intval($entNovo) : null;
        $sa = ($statusAnt !== null && strval($statusAnt) !== "") ? strval($statusAnt) : null;
        $sn = ($statusNovo !== null && strval($statusNovo) !== "") ? strval($statusNovo) : null;
        $mot = ($motivo !== "") ? $motivo : null;
        mysqli_stmt_bind_param($stmt, "iiisssi", $celularId, $ea, $en, $sa, $sn, $mot, $user);
        @mysqli_stmt_execute($stmt);
        @mysqli_stmt_close($stmt);
    }

    function celular_status_opcoes(): array {
        return [
            "disponivel"   => "Disponível",
            "em_uso"       => "Em uso",
            "devolvido"    => "Devolvido",
            "extraviado"   => "Extraviado",
            "manutencao"   => "Manutenção",
            "descartado"   => "Descartado",
        ];
    }

    function opcoesSistemaOperacionalCelular(){
        return [
            "Android" => "Android",
            "iOS" => "iOS",
            "Windows" => "Windows",
            "Outro" => "Outro"
        ];
    }

    function checklistDoPost($prefixo, array $opcoes){
        $marcados = [];
        foreach(array_keys($opcoes) as $key){
            if(!empty($_POST[$prefixo."_".$key])){
                $marcados[] = $key;
            }
        }
        return implode(",", $marcados);
    }

    function celular_json_resposta(int $code, array $payload): void {
        http_response_code($code);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // --- VÍNCULO DE TERMO COM O EQUIPAMENTO (módulo documentos/termos) ---
    // Só podem ser vinculados modelos cujo Tipo de Documento tenha "Equipamento = Sim".
    // Ao gerar o documento, os campos do equipamento ({{equipamento_*}}) são
    // preenchidos com os dados do aparelho vinculado.
    function opcoesVinculoTermo(){
        garantirEstruturaTermosCelular();
        $id = intval($_POST["id"] ?? 0);
        if($id <= 0){
            celular_json_resposta(400, ["ok" => false, "error" => "Equipamento inválido."]);
        }
        $resCel = query("SELECT celu_nb_id, celu_tx_nome, celu_tx_imei, celu_nb_modelo_termo FROM celular WHERE celu_nb_id = ? LIMIT 1", "i", [$id]);
        $celular = ($resCel instanceof mysqli_result) ? mysqli_fetch_assoc($resCel) : null;
        if(empty($celular)){
            celular_json_resposta(404, ["ok" => false, "error" => "Equipamento não encontrado."]);
        }

        $modelos = [];
        $idsValidos = [];
        foreach(termos_modelos_vinculaveis_equipamento() as $m){
            $mid = intval($m["mode_nb_id"] ?? 0);
            if($mid <= 0){
                continue;
            }
            $idsValidos[$mid] = true;
            $modelos[] = [
                "id" => $mid,
                "nome" => strval($m["mode_tx_nome"] ?? ""),
                "tipo" => strval($m["tipo_tx_nome"] ?? "")
            ];
        }

        $vinculados = [];
        foreach(celular_termos_vinculados($id) as $t){
            $vinculados[] = intval($t["mode_nb_id"]);
        }
        $principal = intval($celular["celu_nb_modelo_termo"] ?? 0);
        if($principal > 0 && !in_array($principal, $vinculados, true)){ $vinculados[] = $principal; }

        celular_json_resposta(200, [
            "ok" => true,
            "equipamento" => [
                "id" => intval($celular["celu_nb_id"]),
                "nome" => strval($celular["celu_tx_nome"] ?? ""),
                "imei" => strval($celular["celu_tx_imei"] ?? "")
            ],
            "vinculados" => $vinculados,
            "modelos" => $modelos
        ]);
    }

    function salvarVinculoTermo(){
        garantirEstruturaTermosCelular();
        $id = intval($_POST["id"] ?? 0);
        $modelo = intval($_POST["modelo"] ?? 0);
        if($id <= 0 || $modelo <= 0){
            celular_json_resposta(400, ["ok" => false, "error" => "Selecione o termo que será vinculado ao equipamento."]);
        }

        $resModelo = query(
            "SELECT m.mode_nb_id, m.mode_tx_nome
             FROM modelo_termo m
             INNER JOIN tipos_documentos t ON t.tipo_nb_id = m.mode_nb_tipo_doc
             WHERE m.mode_nb_id = ? AND m.mode_tx_status = 'ativo' AND LOWER(TRIM(t.tipo_tx_ativo)) = 'sim'
             LIMIT 1",
            "i",
            [$modelo]
        );
        if(!($resModelo instanceof mysqli_result) || mysqli_num_rows($resModelo) == 0){
            celular_json_resposta(400, ["ok" => false, "error" => "Este modelo de termo não pode ser vinculado a equipamentos (o Tipo de Documento precisa ter Equipamento = Sim e o modelo estar ativo)."]);
        }
        $rowModelo = mysqli_fetch_assoc($resModelo);

        query("UPDATE celular SET celu_nb_modelo_termo = ? WHERE celu_nb_id = ?", "ii", [$modelo, $id]);
        termos_log("celular_termo_vinculado", "Equipamento #{$id} vinculado ao modelo #{$modelo}", [
            "modelo" => strval($rowModelo["mode_tx_nome"] ?? "")
        ]);
        celular_json_resposta(200, ["ok" => true]);
    }

    function removerVinculoTermo(){
        garantirEstruturaTermosCelular();
        $id = intval($_POST["id"] ?? 0);
        if($id <= 0){
            celular_json_resposta(400, ["ok" => false, "error" => "Equipamento inválido."]);
        }
        query("UPDATE celular SET celu_nb_modelo_termo = NULL WHERE celu_nb_id = ?", "i", [$id]);
        termos_log("celular_termo_removido", "Equipamento #{$id} teve o vínculo de termo removido");
        celular_json_resposta(200, ["ok" => true]);
    }

    function dadosEnvioTermo(){
        garantirEstruturaTermosCelular();
        $id = intval($_POST["id"] ?? 0);
        if($id <= 0){
            celular_json_resposta(400, ["ok" => false, "error" => "Equipamento inválido."]);
        }
        $res = query(
            "SELECT c.celu_nb_id, c.celu_tx_nome, c.celu_tx_imei, c.celu_nb_modelo_termo, c.celu_nb_entidade, c.celu_tx_status,
                    m.mode_nb_id, m.mode_tx_nome, m.mode_tx_status,
                    t.tipo_tx_nome, t.tipo_tx_ativo,
                    e.enti_nb_id AS resp_id, e.enti_tx_nome AS resp_nome, e.enti_tx_email AS resp_email
             FROM celular c
             LEFT JOIN modelo_termo m ON m.mode_nb_id = c.celu_nb_modelo_termo
             LEFT JOIN tipos_documentos t ON t.tipo_nb_id = m.mode_nb_tipo_doc
             LEFT JOIN entidade e ON e.enti_nb_id = c.celu_nb_entidade
             WHERE c.celu_nb_id = ? LIMIT 1",
            "i",
            [$id]
        );
        $cel = ($res instanceof mysqli_result) ? mysqli_fetch_assoc($res) : null;
        if(empty($cel)){
            celular_json_resposta(404, ["ok" => false, "error" => "Equipamento não encontrado."]);
        }
        if(intval($cel["mode_nb_id"] ?? 0) <= 0){
            celular_json_resposta(400, ["ok" => false, "error" => "Este equipamento ainda não possui um termo vinculado. Clique na coluna TERMO para vincular."]);
        }
        if(strtolower(trim(strval($cel["celu_tx_status"] ?? ""))) !== "em_uso"){
            celular_json_resposta(400, ["ok" => false, "error" => "Este equipamento precisa estar com status 'Em uso' para enviar termo."]);
        }
        if(strtolower(trim(strval($cel["mode_tx_status"] ?? "inativo"))) !== "ativo"){
            celular_json_resposta(400, ["ok" => false, "error" => "O modelo de termo vinculado a este equipamento está inativo."]);
        }
        if(strtolower(trim(strval($cel["tipo_tx_ativo"] ?? "nao"))) !== "sim"){
            celular_json_resposta(400, ["ok" => false, "error" => "O Tipo de Documento do termo vinculado não está marcado como 'com equipamento'."]);
        }

        celular_json_resposta(200, [
            "ok" => true,
            "equipamento" => [
                "id" => intval($cel["celu_nb_id"]),
                "nome" => strval($cel["celu_tx_nome"] ?? ""),
                "imei" => strval($cel["celu_tx_imei"] ?? "")
            ],
            "modelo" => [
                "id" => intval($cel["mode_nb_id"]),
                "nome" => strval($cel["mode_tx_nome"] ?? "")
            ],
            "responsavel" => [
                "id" => intval($cel["resp_id"] ?? 0),
                "nome" => strval($cel["resp_nome"] ?? ""),
                "email" => strval($cel["resp_email"] ?? "")
            ]
        ]);
    }

    function devolverCelular(){
        garantirEstruturaTermosCelular();
        $id = intval($_POST["id"] ?? 0);
        $motivo = trim(strval($_POST["motivo"] ?? ""));
        if($id <= 0){ celular_json_resposta(400, ["ok" => false, "error" => "Equipamento inválido."]); }
        $res = query("SELECT celu_nb_entidade, celu_tx_status FROM celular WHERE celu_nb_id = ? LIMIT 1", "i", [$id]);
        $row = ($res instanceof mysqli_result) ? mysqli_fetch_assoc($res) : null;
        if(empty($row)){ celular_json_resposta(404, ["ok" => false, "error" => "Equipamento não encontrado."]); }
        $entAnt = ($row["celu_nb_entidade"] !== null && $row["celu_nb_entidade"] !== "") ? (int)$row["celu_nb_entidade"] : null;
        $stAnt = $row["celu_tx_status"] ?? null;
        query("UPDATE celular SET celu_nb_entidade = NULL, celu_tx_status = 'devolvido', celu_dt_dataDevolucao = ?, celu_tx_motivo_status = ? WHERE celu_nb_id = ?", "ssi", [date("Y-m-d"), ($motivo !== "" ? $motivo : null), $id]);
        celular_registrar_movimentacao($id, $entAnt, null, $stAnt, "devolvido", $motivo !== "" ? $motivo : "Devolução do aparelho");
        celular_json_resposta(200, ["ok" => true]);
    }

    function alterarStatusCelular(){
        $id = intval($_POST["id"] ?? 0);
        $status = trim(strval($_POST["status"] ?? ""));
        $motivo = trim(strval($_POST["motivo"] ?? ""));
        if($id <= 0){ celular_json_resposta(400, ["ok" => false, "error" => "Equipamento inválido."]); }
        $opcoes = celular_status_opcoes();
        if(!isset($opcoes[$status])){ celular_json_resposta(400, ["ok" => false, "error" => "Status inválido."]); }
        $res = query("SELECT celu_nb_entidade, celu_tx_status FROM celular WHERE celu_nb_id = ? LIMIT 1", "i", [$id]);
        $row = ($res instanceof mysqli_result) ? mysqli_fetch_assoc($res) : null;
        if(empty($row)){ celular_json_resposta(404, ["ok" => false, "error" => "Equipamento não encontrado."]); }
        $entAnt = ($row["celu_nb_entidade"] !== null && $row["celu_nb_entidade"] !== "") ? (int)$row["celu_nb_entidade"] : null;
        $stAnt = $row["celu_tx_status"] ?? null;
        // Se marcar como disponível, limpa responsável e data de devolução.
        if($status === "disponivel"){
            query("UPDATE celular SET celu_nb_entidade = NULL, celu_tx_status = ?, celu_dt_dataDevolucao = NULL, celu_tx_motivo_status = ? WHERE celu_nb_id = ?", "ssi", [$status, ($motivo !== "" ? $motivo : null), $id]);
        } elseif($status === "devolvido"){
            query("UPDATE celular SET celu_tx_status = ?, celu_dt_dataDevolucao = ?, celu_tx_motivo_status = ? WHERE celu_nb_id = ?", "sssi", [$status, date("Y-m-d"), ($motivo !== "" ? $motivo : null), $id]);
        } else {
            query("UPDATE celular SET celu_tx_status = ?, celu_tx_motivo_status = ? WHERE celu_nb_id = ?", "ssi", [$status, ($motivo !== "" ? $motivo : null), $id]);
        }
        celular_registrar_movimentacao($id, $entAnt, $entAnt, $stAnt, $status, $motivo !== "" ? $motivo : "Alteração de status");
        celular_json_resposta(200, ["ok" => true]);
    }

    function historicoCelular(){
        $id = intval($_POST["id"] ?? 0);
        if($id <= 0){ celular_json_resposta(400, ["ok" => false, "error" => "Equipamento inválido."]); }
        $res = query("SELECT m.*, e1.enti_tx_nome AS nome_ant, e2.enti_tx_nome AS nome_novo, u.user_tx_login AS usuario
                      FROM celular_movimentacao m
                      LEFT JOIN entidade e1 ON e1.enti_nb_id = m.movi_nb_entidade_anterior
                      LEFT JOIN entidade e2 ON e2.enti_nb_id = m.movi_nb_entidade_novo
                      LEFT JOIN user u ON u.user_nb_id = m.movi_nb_user
                      WHERE m.movi_nb_celular = ?
                      ORDER BY m.movi_dt_data DESC, m.movi_nb_id DESC", "i", [$id]);
        $out = [];
        if($res instanceof mysqli_result){
            while($r = mysqli_fetch_assoc($res)){
                // Converte data (armazenada em UTC) para horário do Brasil.
                if(!empty($r["movi_dt_data"])){
                    try{
                        $dt = new DateTime($r["movi_dt_data"], new DateTimeZone("UTC"));
                        $dt->setTimezone(new DateTimeZone("America/Fortaleza"));
                        $r["movi_dt_data"] = $dt->format("Y-m-d H:i:s");
                    }catch(Throwable $e){}
                }
                $out[] = $r;
            }
        }
        celular_json_resposta(200, ["ok" => true, "itens" => $out]);
    }

    function dadosLoteTermosCelular(){
        garantirEstruturaTermosCelular();
        // Retorna aparelhos com responsável e modelo vinculado.
        $res = query("SELECT c.celu_nb_id, c.celu_tx_nome, c.celu_tx_imei, c.celu_nb_entidade, c.celu_nb_modelo_termo, c.celu_tx_status,
                             e.enti_tx_nome AS resp_nome, e.enti_tx_email AS resp_email
                      FROM celular c
                      INNER JOIN entidade e ON e.enti_nb_id = c.celu_nb_entidade
                      WHERE c.celu_nb_entidade IS NOT NULL AND c.celu_nb_entidade > 0
                      ORDER BY c.celu_tx_nome ASC");
        $out = [];
        if($res instanceof mysqli_result){
            while($r = mysqli_fetch_assoc($res)){
                $ids = [];
                $principal = intval($r["celu_nb_modelo_termo"] ?? 0);
                if($principal > 0){ $ids[] = $principal; }
                $resM = query("SELECT cetm_nb_modelo FROM celular_termo WHERE cetm_nb_celular = ?", "i", [intval($r["celu_nb_id"])]);
                while($resM && ($rm = mysqli_fetch_assoc($resM))){
                    $mid = intval($rm["cetm_nb_modelo"] ?? 0);
                    if($mid > 0 && !in_array($mid, $ids, true)){ $ids[] = $mid; }
                }
                $r["modelos_termo"] = implode(",", $ids);
                $termosArr = [];
                foreach($ids as $mid){
                    $resMod = query("SELECT mode_tx_nome FROM modelo_termo WHERE mode_nb_id = ? LIMIT 1", "i", [$mid]);
                    if($resMod instanceof mysqli_result && ($rm = mysqli_fetch_assoc($resMod))){
                        $termosArr[] = ["id" => $mid, "nome" => strval($rm["mode_tx_nome"] ?? "")];
                    }
                }
                $r["termos"] = $termosArr;
                $r["modelo_nome"] = !empty($termosArr) ? $termosArr[0]["nome"] : "";
                if(!empty($ids)){
                    $out[] = $r;
                }
            }
        }
        celular_json_resposta(200, ["ok" => true, "aparelhos" => $out]);
    }

    function celular_termos_vinculados(int $celuId): array {
        global $conn;
        $out = [];
        if($celuId <= 0){ return $out; }
        $res = query("SELECT m.mode_nb_id, m.mode_tx_nome, t.tipo_tx_nome
                      FROM celular_termo ct
                      INNER JOIN modelo_termo m ON m.mode_nb_id = ct.cetm_nb_modelo
                      LEFT JOIN tipos_documentos t ON t.tipo_nb_id = m.mode_nb_tipo_doc
                      WHERE ct.cetm_nb_celular = ?
                      ORDER BY m.mode_tx_nome ASC", "i", [$celuId]);
        if($res instanceof mysqli_result){
            while($r = mysqli_fetch_assoc($res)){ $out[] = $r; }
        }
        return $out;
    }

    function termosVinculadosCelular(){
        garantirEstruturaTermosCelular();
        $id = intval($_POST["id"] ?? 0);
        if($id <= 0){ celular_json_resposta(400, ["ok" => false, "error" => "Equipamento inválido."]); }
        $resCel = query("SELECT celu_nb_id, celu_tx_nome, celu_tx_imei FROM celular WHERE celu_nb_id = ? LIMIT 1", "i", [$id]);
        $cel = ($resCel instanceof mysqli_result) ? mysqli_fetch_assoc($resCel) : null;
        if(empty($cel)){ celular_json_resposta(404, ["ok" => false, "error" => "Equipamento não encontrado."]); }

        $vinculados = [];
        foreach(celular_termos_vinculados($id) as $t){
            $vinculados[] = intval($t["mode_nb_id"]);
        }
        // Compatibilidade: inclui o vínculo único antigo se existir.
        $resAntigo = query("SELECT celu_nb_modelo_termo FROM celular WHERE celu_nb_id = ? LIMIT 1", "i", [$id]);
        if($resAntigo instanceof mysqli_result && ($rowA = mysqli_fetch_assoc($resAntigo))){
            $ma = intval($rowA["celu_nb_modelo_termo"] ?? 0);
            if($ma > 0 && !in_array($ma, $vinculados, true)){ $vinculados[] = $ma; }
        }

        $modelos = [];
        foreach(termos_modelos_vinculaveis_equipamento() as $m){
            $mid = intval($m["mode_nb_id"] ?? 0);
            if($mid <= 0){ continue; }
            $modelos[] = [
                "id" => $mid,
                "nome" => strval($m["mode_tx_nome"] ?? ""),
                "tipo" => strval($m["tipo_tx_nome"] ?? ""),
                "vinculado" => in_array($mid, $vinculados, true)
            ];
        }

        celular_json_resposta(200, [
            "ok" => true,
            "equipamento" => [
                "id" => intval($cel["celu_nb_id"]),
                "nome" => strval($cel["celu_tx_nome"] ?? ""),
                "imei" => strval($cel["celu_tx_imei"] ?? "")
            ],
            "vinculados" => $vinculados,
            "modelos" => $modelos
        ]);
    }

    function salvarTermosVinculadosCelular(){
        garantirEstruturaTermosCelular();
        $id = intval($_POST["id"] ?? 0);
        if($id <= 0){ celular_json_resposta(400, ["ok" => false, "error" => "Equipamento inválido."]); }
        $modelos = $_POST["modelos"] ?? [];
        if(!is_array($modelos)){ $modelos = []; }
        $modelos = array_values(array_unique(array_filter(array_map("intval", $modelos))));

        // Limpa vínculos antigos e insere os novos.
        query("DELETE FROM celular_termo WHERE cetm_nb_celular = ?", "i", [$id]);
        foreach($modelos as $mid){
            query("INSERT IGNORE INTO celular_termo (cetm_nb_celular, cetm_nb_modelo) VALUES (?, ?)", "ii", [$id, $mid]);
        }
        // Mantém o primeiro como vínculo principal (compatibilidade).
        $principal = !empty($modelos) ? $modelos[0] : null;
        if($principal !== null){
            query("UPDATE celular SET celu_nb_modelo_termo = ? WHERE celu_nb_id = ?", "ii", [$principal, $id]);
        } else {
            query("UPDATE celular SET celu_nb_modelo_termo = NULL WHERE celu_nb_id = ?", "i", [$id]);
        }
        termos_log("celular_termos_vinculados", "Equipamento #{$id} teve termos vinculados atualizados", ["qtd" => count($modelos)]);
        celular_json_resposta(200, ["ok" => true]);
    }

    // --- ROTEADOR DE AÇÕES ---
    // Verifica se chegou alguma ação via POST
    if(!empty($_POST['acao'])){
        $acao = $_POST['acao'];
        if(function_exists($acao)){
            $acao();
            exit;
        } else {
            echo "Erro: Função '$acao' não existe no PHP.";
            exit;
        }
    }

    // Campo de Operadora: combo com operadoras pré-cadastradas e a opção
    // "Outro", que libera um campo de texto para digitar a operadora.
    function comboOperadora($modificador, $outroValor, $tamanho = 2){
        $htmlOpcoes = "";
        foreach(opcoesOperadora() as $key => $value){
            $selected = ($key == $modificador) ? "selected" : "";
            $htmlOpcoes .= "<option value='{$key}' {$selected}>{$value}</option>";
        }
        $outroValor   = htmlspecialchars(strval($outroValor), ENT_QUOTES);
        $displayOutro = ($modificador === "outro") ? "" : "display:none;";
        return
            "<div class='col-sm-{$tamanho} margin-bottom-5 campo-fit-content'>
                <label>Operadora</label>
                <select name='operadora' id='operadora' class='form-control input-sm campo-fit-content'>
                    {$htmlOpcoes}
                </select>
                <input name='operadoraOutro' id='operadoraOutro' value='{$outroValor}' autocomplete='off' type='text' class='form-control input-sm campo-fit-content' style='margin-top:4px; {$displayOutro}' placeholder='Digite a operadora'>
                <script>
                    $(function(){
                        var \$selOperadora = $('[name=\"operadora\"]');
                        var \$campoOutro = $('#operadoraOutro');
                        function operadoraToggleOutro(){
                            \$campoOutro.toggle(\$selOperadora.val() === 'outro');
                        }
                        \$selOperadora.off('change.operadoraOutro').on('change.operadoraOutro', operadoraToggleOutro);
                        operadoraToggleOutro();
                    });
                </script>
            </div>";
    }

    function formCelular() {
        $celular = null;
        if(!empty($_POST["id"])){
            $celular = mysqli_fetch_assoc(query(
                "SELECT * FROM celular WHERE celu_nb_id = " . (int)$_POST["id"]
            ));
            // Preenche os POSTs caso não existam
            if($celular) {
                $_POST["nome_like"]          = $_POST["nome_like"] ?? $celular["celu_tx_nome"];
                $_POST["tipo"]               = $_POST["tipo"] ?? ($celular["celu_tx_tipo"] ?? "");
                $_POST["marca_like"]         = $_POST["marca_like"] ?? ($celular["celu_tx_marca"] ?? "");
                $_POST["modelo_like"]        = $_POST["modelo_like"] ?? ($celular["celu_tx_modelo"] ?? "");
                $_POST["imei"]               = $_POST["imei"] ?? $celular["celu_tx_imei"];
                $_POST["imei2"]              = $_POST["imei2"] ?? ($celular["celu_tx_imei2"] ?? "");
                $_POST["numeroSerie_like"]   = $_POST["numeroSerie_like"] ?? ($celular["celu_tx_numeroSerie"] ?? "");
                $_POST["numero"]             = $_POST["numero"] ?? $celular["celu_tx_numero"];
                $_POST["numero2"]            = $_POST["numero2"] ?? ($celular["celu_tx_numero2"] ?? "");
                $_POST["operadora"]          = $_POST["operadora"] ?? $celular["celu_tx_operadora"];
                $_POST["cimie"]              = $_POST["cimie"] ?? $celular["celu_tx_cimie"];
                $_POST["sistemaOperacional"] = $_POST["sistemaOperacional"] ?? $celular["celu_tx_sistemaOperacional"];
                $__soOpcoes = opcoesSistemaOperacionalCelular();
                $__soAtual = trim(strval($_POST["sistemaOperacional"]));
                if($__soAtual !== "" && !isset($__soOpcoes[$__soAtual])){
                    $_POST["sistemaOperacionalOutro"] = $_POST["sistemaOperacionalOutro"] ?? $__soAtual;
                    $_POST["sistemaOperacional"] = "Outro";
                }
                $_POST["acessoriosOutros"]   = $_POST["acessoriosOutros"] ?? ($celular["celu_tx_acessoriosOutros"] ?? "");
                $_POST["aplicativosOutros"]  = $_POST["aplicativosOutros"] ?? ($celular["celu_tx_aplicativosOutros"] ?? "");
                $_POST["estadoConservacao"]  = $_POST["estadoConservacao"] ?? ($celular["celu_tx_estadoConservacao"] ?? "");
                $_POST["observacoes"]        = $_POST["observacoes"] ?? ($celular["celu_tx_observacoes"] ?? "");
                $_POST["valorEstimado"]      = $_POST["valorEstimado"] ?? ($celular["celu_tx_valorEstimado"] ?? "");
                $_POST["dataEntrega"]        = $_POST["dataEntrega"] ?? ($celular["celu_tx_dataEntrega"] ?? "");
                $_POST["entidade"]           = $_POST["entidade"] ?? $celular["celu_nb_entidade"];
                $_POST["status"]             = $_POST["status"] ?? ($celular["celu_tx_status"] ?? "");
                $_POST["dataDevolucao"]      = $_POST["dataDevolucao"] ?? ($celular["celu_dt_dataDevolucao"] ?? "");
                $_POST["motivo_status"]      = $_POST["motivo_status"] ?? ($celular["celu_tx_motivo_status"] ?? "");
            }
        }

        // No cadastro novo (ou após erro de validação) mantém o que o usuário marcou;
        // na edição, carrega o que já está salvo no banco.
        $acessoriosMarcados  = ($celular["celu_tx_acessorios"] ?? "");
        $aplicativosMarcados = ($celular["celu_tx_aplicativos"] ?? "");
        if(empty($_POST["id"]) || !empty($_POST["msg_status"])){
            $acessoriosMarcados  = checklistDoPost("acessorios", opcoesAcessorios());
            $aplicativosMarcados = checklistDoPost("aplicativos", opcoesAplicativos());
        }

        // Operadora: opções pré-cadastradas + "Outro" (digita a operadora).
        // Um valor salvo fora da lista volta como "Outro" já preenchido.
        $operadoraAtual  = (!empty($_POST["operadora"]) ? $_POST["operadora"] : "");
        $operadoraOutro  = (!empty($_POST["operadoraOutro"]) ? $_POST["operadoraOutro"] : "");
        $opcoesOperadora = opcoesOperadora();
        if($operadoraAtual !== "" && $operadoraAtual !== "outro" && !isset($opcoesOperadora[$operadoraAtual])){
            $operadoraOutro = $operadoraAtual;
            $operadoraAtual = "outro";
        }

        echo abre_form();
        echo linha_form([
            // --- CORREÇÃO IMPORTANTE: Campo 'acao' para o Cadastrar/Atualizar funcionar ---
            campo_hidden("acao", ""), 
            campo_hidden("id", (!empty($_POST["id"])? $_POST["id"]: "")),
            campo("Nome", "nome_like", (!empty($_POST["nome_like"])? $_POST["nome_like"]: ""), 4, "", "required"),
            combo("Tipo", "tipo", (!empty($_POST["tipo"])? $_POST["tipo"]: ""), 2, opcoesTipoCelular()),
            campo("Marca", "marca_like", (!empty($_POST["marca_like"])? $_POST["marca_like"]: ""), 3),
            campo("Modelo", "modelo_like", (!empty($_POST["modelo_like"])? $_POST["modelo_like"]: ""), 3)
        ]);
        echo linha_form([
            campo("IMEI 1", "imei", (!empty($_POST["imei"])? $_POST["imei"]: ""), 3, "MASCARA_IMEI", "required"),
            campo("IMEI 2", "imei2", (!empty($_POST["imei2"])? $_POST["imei2"]: ""), 3, "MASCARA_IMEI"),
            campo("Número de série", "numeroSerie_like", (!empty($_POST["numeroSerie_like"])? $_POST["numeroSerie_like"]: ""), 3),
            campo("ICCID do chip", "cimie", (!empty($_POST["cimie"])? $_POST["cimie"]: ""), 3, "MASCARA_ICCID")
        ]);
        echo linha_form([
            campo("Número da linha 1 (chip)", "numero", (!empty($_POST["numero"])? $_POST["numero"]: ""), 3, "MASCARA_CEL", "required"),
            campo("Número da linha 2 (chip)", "numero2", (!empty($_POST["numero2"])? $_POST["numero2"]: ""), 3, "MASCARA_CEL"),
            comboOperadora($operadoraAtual, $operadoraOutro, 3),
            combo("Sistema Operacional", "sistemaOperacional", (!empty($_POST["sistemaOperacional"])? $_POST["sistemaOperacional"]: ""), 2, opcoesSistemaOperacionalCelular()),
            campo("Outro S.O.", "sistemaOperacionalOutro", (!empty($_POST["sistemaOperacionalOutro"])? $_POST["sistemaOperacionalOutro"]: ""), 2, "", (($_POST["sistemaOperacional"] ?? "") === "Outro" ? "" : "disabled"))
        ]);
        echo linha_form([
            campo("Valor estimado do bem", "valorEstimado", (!empty($_POST["valorEstimado"])? $_POST["valorEstimado"]: ""), 2, "MASCARA_DINHEIRO"),
            campo_data("Data da entrega", "dataEntrega", (!empty($_POST["dataEntrega"])? $_POST["dataEntrega"]: ""), 2),
            combo_net("Responsável", "entidade", (!empty($_POST["entidade"])? $_POST["entidade"]: ""), 3, "entidade"),
            combo("Status", "status", (!empty($_POST["status"])? $_POST["status"]: ""), 2, celular_status_opcoes()),
            campo_data("Devolução", "dataDevolucao", (!empty($_POST["dataDevolucao"])? $_POST["dataDevolucao"]: ""), 2)
        ]);
        echo linha_form([
            campo("Motivo", "motivo_status", (!empty($_POST["motivo_status"])? $_POST["motivo_status"]: ""), 12)
        ]);
        echo linha_form([
            checkbox("Acessórios entregues", "acessorios", opcoesAcessorios(), 6, "checkbox", "", $acessoriosMarcados),
            checkbox("Aplicativos instalados", "aplicativos", opcoesAplicativos(), 6, "checkbox", "", $aplicativosMarcados)
        ]);
        // Campos "Outros (especifique)" só ficam liberados quando o respectivo
        // checkbox "Outros" está marcado nos acessórios/aplicativos.
        $outrosAcessoriosMarcado  = in_array("outros", explode(",", strval($acessoriosMarcados)));
        $outrosAplicativosMarcado = in_array("outros_aplicativos", explode(",", strval($aplicativosMarcados)));

        echo linha_form([
            campo("Outros acessórios (especifique)", "acessoriosOutros", (!empty($_POST["acessoriosOutros"])? $_POST["acessoriosOutros"]: ""), 3, "", $outrosAcessoriosMarcado ? "" : "disabled"),
            campo("Outros aplicativos (especifique)", "aplicativosOutros", (!empty($_POST["aplicativosOutros"])? $_POST["aplicativosOutros"]: ""), 3, "", $outrosAplicativosMarcado ? "" : "disabled"),
            combo_radio("Estado de conservação na entrega", "estadoConservacao", (!empty($_POST["estadoConservacao"])? $_POST["estadoConservacao"]: ""), 6, opcoesEstadoConservacao())
        ]);
        echo "<script>
            $(function(){
                function celularLiberarOutros(nomeFlag, idCampo){
                    var \$flag  = $('[name=\"' + nomeFlag + '\"]');
                    var \$campo = $('#' + idCampo);
                    if(\$flag.length === 0 || \$campo.length === 0){
                        return;
                    }
                    \$flag.off('change.celularOutros').on('change.celularOutros', function(){
                        \$campo.prop('disabled', !\$flag.is(':checked'));
                    });
                    \$campo.prop('disabled', !\$flag.is(':checked'));
                }
                celularLiberarOutros('acessorios_outros', 'acessoriosOutros');
                celularLiberarOutros('aplicativos_outros_aplicativos', 'aplicativosOutros');
                (function(){
                    var \$sel = $('[name=\"sistemaOperacional\"]');
                    var \$campo = $('#sistemaOperacionalOutro');
                    function toggle(){ \$campo.prop('disabled', \$sel.val() !== 'Outro'); }
                    \$sel.off('change.soOutro').on('change.soOutro', toggle);
                    toggle();
                })();
            });
        </script>";
        echo linha_form([
            textarea("Observações", "observacoes", (!empty($_POST["observacoes"])? $_POST["observacoes"]: ""), 12)
        ]);
        
        $botoes = !empty($_POST["id"])?
            [
                botao("Atualizar", "cadastrarCelular", "id", $_POST["id"], "", "", "btn btn-success"),
                criarBotaoVoltar("cadastro_celular.php", "voltarCelular")
            ]:
            [
                botao("Cadastrar", "cadastrarCelular", "", "", "", "", "btn btn-success"),
                "<button type='button' class='btn btn-default' onclick='if(typeof consultarRegistros===\"function\"){consultarRegistros();}'>Filtrar</button>",
                "<button type='button' class='btn btn-default' onclick='limparFiltrosCelular()'>Limpar filtros</button>"
            ];
            
        echo fecha_form($botoes);
    }

    function listarCelulares() {
        // ... (Definição dos campos continua igual) ...
        $gridFields = [
            "CÓDIGO"            => "celu_nb_id",
            "NOME"              => "celu_tx_nome",
            "TIPO"              => "celu_tx_tipo",
            "MARCA"             => "celu_tx_marca",
            "MODELO"            => "celu_tx_modelo",
            "IMEI 1"            => "celu_tx_imei",
            "IMEI 2"            => "celu_tx_imei2",
            "Nº SÉRIE"          => "celu_tx_numeroSerie",
            "Nº LINHA"          => "celu_tx_numero",
            "Nº LINHA 2"        => "celu_tx_numero2",
            "OPERADORA"         => "celu_tx_operadora",
            "ICCID"             => "celu_tx_cimie",
            "S.O."              => "celu_tx_sistemaOperacional",
            "ESTADO"            => "celu_tx_estadoConservacao",
            "VALOR ESTIMADO"    => "celu_tx_valorEstimado",
            "ENTREGA"           => "DATE_FORMAT(celu_dt_dataEntrega, '%d/%m/%Y')",
            "RESPONSÁVEL"       => "enti_tx_nome",
            "STATUS"            => "CASE celu_tx_status
                WHEN 'disponivel' THEN '<span class=\"label label-success\">Disponível</span>'
                WHEN 'em_uso' THEN '<span class=\"label label-primary\">Em uso</span>'
                WHEN 'devolvido' THEN '<span class=\"label label-info\">Devolvido</span>'
                WHEN 'extraviado' THEN '<span class=\"label label-danger\">Extraviado</span>'
                WHEN 'manutencao' THEN '<span class=\"label label-warning\">Manutenção</span>'
                WHEN 'descartado' THEN '<span class=\"label label-default\">Descartado</span>'
                ELSE celu_tx_status END AS STATUS",

        ];

        $camposBusca = [
            "nome_like"          => "celu_tx_nome",
            "tipo"               => "celu_tx_tipo",
            "marca_like"         => "celu_tx_marca",
            "modelo_like"        => "celu_tx_modelo",
            "imei"               => "celu_tx_imei",
            "imei2"              => "celu_tx_imei2",
            "numeroSerie_like"   => "celu_tx_numeroSerie",
            "numero"             => "celu_tx_numero",
            "operadora"          => "celu_tx_operadora",
            "cimie"              => "celu_tx_cimie",
            "sistemaOperacional" => "celu_tx_sistemaOperacional",
        ];

        // Coluna TERMO: clique abre o modal para vincular/alterar o termo do aparelho.
        // Modelos elegíveis: Tipo de Documento com "Equipamento = Sim".


        $gridFields["TERMO"] = "CONCAT(
                '<span class=\"termo-link-celular\" style=\"cursor:pointer;white-space:nowrap;\" data-id=\"', celu_nb_id, '\" data-nome=\"', REPLACE(IFNULL(celu_tx_nome, ''), '\"', '&quot;'), '\" title=\"Clique para vincular/alterar os termos deste equipamento\">',
                IF((SELECT COUNT(*) FROM celular_termo WHERE cetm_nb_celular = celu_nb_id) = 0 AND (celu_nb_modelo_termo IS NULL OR celu_nb_modelo_termo = 0),
                    '<span class=\"text-muted\"><i class=\"fa fa-link\"></i> Vincular termo</span>',
                    CONCAT('<i class=\"fa fa-file-text-o\"></i> ', REPLACE(REPLACE(REPLACE(IFNULL((SELECT GROUP_CONCAT(m.mode_tx_nome SEPARATOR ', ') FROM celular_termo ct JOIN modelo_termo m ON m.mode_nb_id = ct.cetm_nb_modelo WHERE ct.cetm_nb_celular = celu_nb_id), tm.mode_tx_nome), '&', '&amp;'), '<', '&lt;'), '\"', '&quot;'), ' <i class=\"fa fa-pencil\" style=\"font-size:10px;color:#999;\"></i>')
                ),
                '</span>'
            ) AS termo_html";

        $queryBase = "SELECT ".implode(", ", array_values($gridFields))." FROM celular
            LEFT JOIN entidade ON celu_nb_entidade = enti_nb_id
            LEFT JOIN modelo_termo tm ON tm.mode_nb_id = celu_nb_modelo_termo";

        $msgPadrao = "Tem certeza que deseja excluir o celular <br><h3 style='color:#337ab7;'>{NOME} <br><small>(IMEI: {IMEI 1})</small></h3>?";
    
        $msgAviso = "<b>ATENÇÃO!</b><br>
                        O celular <b>{NOME}</b> está sob responsabilidade de:<br>
                        <h3 style='color:#d9534f;'>{RESPONSÁVEL}</h3><br>
                        Deseja excluir o aparelho e remover o vínculo deste usuário mesmo assim?";

        $configuracao = gerarAcoesComConfirmacao(
            "cadastro_celular.php", 
            "editarCelular", 
            "excluirCelular",
            "CÓDIGO",
            $msgPadrao, 
            "RESPONSÁVEL", 
            $msgAviso 
        );
        $configuracao["tags"][] = "<span class='glyphicon glyphicon-send termo-celular-enviar' title='Enviar Termo' style='color:#5cb85c; cursor:pointer;'></span>";
        $configuracao["tags"][] = "<span class='glyphicon glyphicon-time celular-historico' title='Histórico' style='color:#337ab7; cursor:pointer;'></span>";
        $configuracao["tags"][] = "<span class='glyphicon glyphicon-log-out celular-devolver' title='Devolver / Alterar status' style='color:#d9534f; cursor:pointer;'></span>";
        $gridFields["actions"] = $configuracao["tags"];
        $jsFunctions = $configuracao["js"] . "
            $(document).off('click.termoCelularVincular', '.termo-link-celular').on('click.termoCelularVincular', '.termo-link-celular', function(event){
                event.preventDefault();
                termoCelularAbrirVinculo(parseInt($(this).attr('data-id'), 10) || 0);
            });
            // Tooltip dinâmico: mostra o responsável no ícone de enviar termo.
            $(document).ajaxComplete(function(event, xhr, settings){
                if(!settings || !settings.url || settings.url.indexOf('getTableInfo.php') < 0){ return; }
                try {
                    var idx = -1;
                    $('#result thead th').each(function(i){ if($(this).text().trim().toUpperCase().indexOf('RESPONS') >= 0){ idx = i; } });
                    if(idx < 0){ return; }
                    $('#result tbody tr').each(function(){
                        var resp = $(this).find('td').eq(idx).text().trim();
                        if(resp){ $(this).find('.termo-celular-enviar').attr('title', 'Enviar termo para: ' + resp); }
                    });
                } catch(e) {}
            });

            $(document).off('click.termoCelularEnviar', '.termo-celular-enviar').on('click.termoCelularEnviar', '.termo-celular-enviar', function(event){
                event.preventDefault();
                var id = parseInt($(event.target).closest('tr').attr('data-row-id'), 10) || 0;
                termoCelularAbrirEnvioDireto(id);
            });
            $(document).off('click.celularHistorico', '.celular-historico').on('click.celularHistorico', '.celular-historico', function(event){
                event.preventDefault();
                var id = parseInt($(event.target).closest('tr').attr('data-row-id'), 10) || 0;
                celularAbrirHistorico(id);
            });
            $(document).off('click.celularDevolver', '.celular-devolver').on('click.celularDevolver', '.celular-devolver', function(event){
                event.preventDefault();
                var id = parseInt($(event.target).closest('tr').attr('data-row-id'), 10) || 0;
                if (!id) { return; }
                celularAbrirStatus(id);
            });
        ";

        echo "<div style='margin:8px 0;'><button type='button' class='btn btn-primary' onclick='termoCelularAbrirLote()'><i class='fa fa-send'></i> Enviar Termos (lote por aparelhos)</button></div>";
        echo "<div style='margin:4px 0 8px 0; font-size:11px; color:#666;'>Legenda status: <span class='label label-success'>Disponível</span> <span class='label label-primary'>Em uso</span> <span class='label label-info'>Devolvido</span> <span class='label label-danger'>Extraviado</span> <span class='label label-warning'>Manutenção</span> <span class='label label-default'>Descartado</span></div>";
        echo gridDinamico("celular", $gridFields, $camposBusca, $queryBase, $jsFunctions);
    };

    function cadastrarCelular(){
        $fields = [
            "nome_like", "tipo", "marca_like", "modelo_like", "imei", "imei2", "numeroSerie_like", "numero", "numero2",
            "operadora", "operadoraOutro", "cimie", "sistemaOperacional", "sistemaOperacionalOutro", "acessoriosOutros", "aplicativosOutros",
            "estadoConservacao", "observacoes", "valorEstimado", "dataEntrega"
        ];
        foreach($fields as $field){
            $_POST[$field] = isset($_POST[$field]) ? trim($_POST[$field]) : '';
        }

        // Operadora "Outro": salva o nome digitado no campo livre.
        if($_POST["operadora"] === "outro"){
            $_POST["operadora"] = $_POST["operadoraOutro"];
        }
        // Sistema Operacional "Outro": salva o nome digitado no campo livre.
        if(($_POST["sistemaOperacional"] ?? "") === "Outro"){
            $_POST["sistemaOperacional"] = trim(strval($_POST["sistemaOperacionalOutro"] ?? ""));
            if($_POST["sistemaOperacional"] === ""){ $_POST["sistemaOperacional"] = "Outro"; }
        }

        $errorMsg = conferirCamposObrig([
            "nome_like" => "Nome",
            "imei"      => "IMEI 1",
            "numero"    => "Número da linha (chip)"
        ], $_POST);
        if(!empty($errorMsg)){
            set_status("ERRO: ".$errorMsg);
            unset($_POST["cadastrarCelular"]);
            index();
            exit;
        }

        // Verifica duplicidade de IMEI
        $imeiQuery = !empty($_POST["id"]) ?
            ["SELECT celu_nb_id FROM celular WHERE celu_tx_imei = ? AND celu_nb_id != ?;", "si", [$_POST["imei"], (int)$_POST["id"]]] :
            ["SELECT celu_nb_id FROM celular WHERE celu_tx_imei = ?;", "s", [$_POST["imei"]]];
            
        $imeiJaCadastrado = !empty(mysqli_fetch_assoc(query($imeiQuery[0], $imeiQuery[1], $imeiQuery[2])));
        if($imeiJaCadastrado){
            set_status("<script>Swal.fire('Opa!', 'Este IMEI já está vinculado à outro celular.', 'error');</script>");
            index();
            exit;
        }

        $entidadeSelecionada = (!empty($_POST["entidade"]) ? (int)$_POST["entidade"] : null);

        // Status gerencial do aparelho (novo controle).
        $statusSelecionado = trim(strval($_POST["status"] ?? ""));
        if($statusSelecionado === "" || !isset(celular_status_opcoes()[$statusSelecionado])){
            $statusSelecionado = ($entidadeSelecionada !== null) ? "em_uso" : "disponivel";
        }
        $motivoStatus = trim(strval($_POST["motivo_status"] ?? ""));

        // Estado anterior (para auditoria) quando for edição.
        $entidadeAnterior = null;
        $statusAnterior = null;
        if(!empty($_POST["id"])){
            $resAnt = query("SELECT celu_nb_entidade, celu_tx_status FROM celular WHERE celu_nb_id = ? LIMIT 1", "i", [(int)$_POST["id"]]);
            if($resAnt instanceof mysqli_result && ($rowAnt = mysqli_fetch_assoc($resAnt))){
                $entidadeAnterior = ($rowAnt["celu_nb_entidade"] !== null && $rowAnt["celu_nb_entidade"] !== "") ? (int)$rowAnt["celu_nb_entidade"] : null;
                $statusAnterior = $rowAnt["celu_tx_status"] ?? null;
            }
        }

        // Se o ativo ficará sem responsável, garante que a coluna aceite NULL no banco.
        // Em produção o usuário do banco pode não ter privilégio de ALTER; nesse caso
        // orienta a selecionar um responsável em vez de exibir o erro cru de SQL.
        if($entidadeSelecionada === null && !ensureCelularEntidadeOpcional()){
            set_status("<script>Swal.fire('Atenção!', 'Selecione um Responsável para o celular. O banco de dados ainda não permite salvar ativo sem responsável.', 'warning');</script>");
            index();
            exit;
        }

        $novoCelular = [
            "celu_tx_nome" => $_POST["nome_like"],
            "celu_tx_tipo" => $_POST["tipo"],
            "celu_tx_marca" => $_POST["marca_like"],
            "celu_tx_modelo" => $_POST["modelo_like"],
            "celu_tx_imei" => $_POST["imei"],
            "celu_tx_imei2" => $_POST["imei2"],
            "celu_tx_numeroSerie" => $_POST["numeroSerie_like"],
            "celu_tx_numero" => $_POST["numero"],
            "celu_tx_numero2" => ($_POST["numero2"] ?? ""),
            "celu_tx_operadora" => $_POST["operadora"],
            "celu_tx_cimie" => $_POST["cimie"],
            "celu_tx_sistemaOperacional" => $_POST["sistemaOperacional"],
            "celu_tx_marcaModelo" => trim($_POST["marca_like"]." ".$_POST["modelo_like"]),
            "celu_tx_acessorios" => checklistDoPost("acessorios", opcoesAcessorios()),
            "celu_tx_acessoriosOutros" => $_POST["acessoriosOutros"],
            "celu_tx_aplicativos" => checklistDoPost("aplicativos", opcoesAplicativos()),
            "celu_tx_aplicativosOutros" => $_POST["aplicativosOutros"],
            "celu_tx_estadoConservacao" => $_POST["estadoConservacao"],
            "celu_tx_observacoes" => $_POST["observacoes"],
            "celu_tx_valorEstimado" => $_POST["valorEstimado"],
            "celu_dt_dataEntrega" => (!empty($_POST["dataEntrega"]) ? $_POST["dataEntrega"] : null),
            "celu_nb_entidade" => $entidadeSelecionada,
            "celu_tx_status" => $statusSelecionado,
            "celu_dt_dataDevolucao" => ($statusSelecionado === "devolvido" ? date("Y-m-d") : null),
            "celu_tx_motivo_status" => ($motivoStatus !== "" ? $motivoStatus : null),
            "celu_tx_dataAtualiza" => date("Y-m-d H:i:s")
        ];

        if(!empty($_POST["id"])){
            atualizar("celular", array_keys($novoCelular), array_values($novoCelular), $_POST["id"]);
            // Auditoria: registra se houve mudança de responsável e/ou status.
            $mudouEnt = ($entidadeAnterior !== $entidadeSelecionada);
            $mudouSt = ($statusAnterior !== $statusSelecionado);
            if($mudouEnt || $mudouSt){
                celular_registrar_movimentacao((int)$_POST["id"], $entidadeAnterior, $entidadeSelecionada, $statusAnterior, $statusSelecionado, $motivoStatus);
            }
            set_status("<script>Swal.fire('Sucesso!', 'Celular atualizado com sucesso.', 'success');</script>");
        } else {
            $novoCelular["celu_tx_dataCadastro"] = date("Y-m-d H:i:s");
            $retorno = inserir("celular", array_keys($novoCelular), array_values($novoCelular));
            if(is_array($retorno) && isset($retorno[0]) && is_object($retorno[0])){
                set_status("<script>Swal.fire('Erro!', 'Não foi possível cadastrar o celular: " . addslashes($retorno[0]->getMessage()) . "', 'error');</script>");
                index();
                exit;
            }
            $novoId = 0;
            if(is_array($retorno)){
                foreach($retorno as $r){ if(is_int($r) && $r > 0){ $novoId = $r; break; } }
            } elseif(is_numeric($retorno)){ $novoId = (int)$retorno; }
            if($novoId > 0){
                celular_registrar_movimentacao($novoId, null, $entidadeSelecionada, null, $statusSelecionado, $motivoStatus !== "" ? $motivoStatus : "Cadastro inicial");
            }
            set_status("<script>Swal.fire('Sucesso!', 'Celular inserido com sucesso.', 'success');</script>");
        }
        unset($_POST);
        index();
        exit;
    }

    function editarCelular(){
        index();
        exit;
    }

    function excluirCelular(){
        if(empty($_POST["id"])){
            set_status("Erro: ID não informado.");
            index();
            exit;
        }
        query("DELETE FROM celular WHERE celu_nb_id = " . (int)$_POST["id"]);
        set_status("<script>Swal.fire('Sucesso!', 'Celular excluído com sucesso.', 'info');</script>");
        unset($_POST["id"]);
        index();
        exit;
    }

    function voltarCelular(){
        unset($_POST);
        index();
        exit;
    }

    function modalVincularTermoCelular(){
        echo "
        <div class='modal fade' id='modal_termo_celular' tabindex='-1' role='dialog'>
            <div class='modal-dialog' role='document'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <button type='button' class='close' data-dismiss='modal'>&times;</button>
                        <h4 class='modal-title'>Vincular Termos ao Equipamento</h4>
                    </div>
                    <div class='modal-body'>
                        <div class='alert alert-info' style='padding:8px 12px; font-size:12px;'>
                            <i class='fa fa-link'></i> Selecione um ou mais termos usados por este equipamento. Na geração do documento, os campos do equipamento (ex.: <code>{{equipamento_imei}}</code>) são preenchidos com os dados deste aparelho.
                        </div>
                        <div id='termocel_vinc_equip' class='text-muted' style='margin-bottom:8px;'></div>
                        <div id='termocel_vinc_lista' style='max-height:320px; overflow-y:auto; border:1px solid #eee; padding:6px; background:#fcfcfc;'>
                            <div class='text-muted'>Carregando termos...</div>
                        </div>
                        <div class='text-muted' style='font-size:11px; margin-top:8px;'>
                            Só aparecem modelos cujo Tipo de Documento esteja marcado com <b>Equipamento = Sim</b>.
                            Precisa de outro? <a href='documentos/termos/modelos_termo.php' target='_blank'>Cadastrar modelos de termo</a>.
                        </div>
                    </div>
                    <div class='modal-footer'>
                        <button type='button' class='btn btn-danger pull-left' id='termocel_vinc_btn_remover' onclick='termoCelularRemoverVinculo()' style='display:none;'>Remover todos</button>
                        <button type='button' class='btn btn-default' data-dismiss='modal'>Fechar</button>
                        <button type='button' class='btn btn-success' onclick='termoCelularSalvarVinculo()'>Salvar vínculos</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            var TERMOCEL_VINC_ID = 0;

            function termoCelularEscape(s) {
                return $('<div>').text(s || '').html();
            }

            function termoCelularUniform() {
                try {
                    if (window.jQuery && jQuery.uniform && typeof jQuery.uniform.update === 'function') {
                        jQuery.uniform.update();
                    }
                } catch (e) {}
            }

            function termoCelularAbrirVinculo(id) {
                if (!id) { return; }
                TERMOCEL_VINC_ID = id;
                $('#termocel_vinc_equip').html('Equipamento: <b>#' + id + '</b>');
                $('#termocel_vinc_lista').html('<div class=\"text-muted\">Carregando termos...</div>');
                $('#termocel_vinc_btn_remover').hide();
                $('#modal_termo_celular').modal('show');
                $.ajax({
                    url: 'cadastro_celular.php',
                    method: 'POST',
                    data: { acao: 'opcoesVinculoTermo', id: id },
                    dataType: 'json'
                }).done(function(res) {
                    if (!res || !res.ok) {
                        $('#termocel_vinc_lista').html('<div class=\"alert alert-danger\">' + termoCelularEscape(res && res.error ? res.error : 'Falha ao carregar os termos.') + '</div>');
                        return;
                    }
                    $('#termocel_vinc_equip').html(
                        'Equipamento: <b>#' + res.equipamento.id + ' — ' + termoCelularEscape(res.equipamento.nome) + '</b>'
                        + (res.equipamento.imei ? ' <span class=\"text-muted\">(IMEI: ' + termoCelularEscape(res.equipamento.imei) + ')</span>' : '')
                    );
                    var html = '';
                    if (!res.modelos.length) {
                        html = '<div class=\"text-muted\">Nenhum modelo de termo disponível para equipamentos. Cadastre um modelo cujo Tipo de Documento esteja marcado com Equipamento = Sim.</div>';
                    } else {
                        html += '<div style=\"margin-bottom:6px;\"><a href=\"javascript:void(0);\" onclick=\"$(\'input[name=termocel_modelo]\').prop(\'checked\', false); termoCelularUniform();\" style=\"font-size:11px;\">Limpar seleção</a></div>';
                        var vinculados = (res.vinculados || []);
                        $.each(res.modelos, function(i, m) {
                            var checked = (vinculados.indexOf(parseInt(m.id, 10)) >= 0) ? 'checked' : '';
                            html += '<label style=\"font-weight:normal; display:block; margin:2px 0; overflow-wrap:break-word;\">'
                                + '<input type=\"checkbox\" name=\"termocel_modelo\" value=\"' + m.id + '\" ' + checked + '> '
                                + '<b>' + termoCelularEscape(m.nome) + '</b>'
                                + (m.tipo ? ' <span class=\"text-muted\">— ' + termoCelularEscape(m.tipo) + '</span>' : '')
                                + '</label>';
                        });
                    }
                    $('#termocel_vinc_lista').html(html);
                    $('#termocel_vinc_btn_remover').toggle(parseInt(res.atual, 10) > 0);
                    termoCelularUniform();
                }).fail(function() {
                    $('#termocel_vinc_lista').html('<div class=\"alert alert-danger\">Falha de comunicação ao carregar os termos.</div>');
                });
            }

            function termoCelularSalvarVinculo() {
                if (!TERMOCEL_VINC_ID) { return; }
                var modelos = [];
                $('input[name=termocel_modelo]:checked').each(function() { modelos.push(parseInt($(this).val(), 10)); });
                $.ajax({
                    url: 'cadastro_celular.php',
                    method: 'POST',
                    data: { acao: 'salvarTermosVinculadosCelular', id: TERMOCEL_VINC_ID, modelos: modelos },
                    dataType: 'json'
                }).done(function(res) {
                    if (!res || !res.ok) {
                        if (window.Swal) { Swal.fire('Erro!', (res && res.error) ? res.error : 'Não foi possível salvar o vínculo.', 'error'); }
                        else { alert((res && res.error) ? res.error : 'Não foi possível salvar o vínculo.'); }
                        return;
                    }
                    $('#modal_termo_celular').modal('hide');
                    if (window.Swal) { Swal.fire('Sucesso!', 'Termo vinculado ao equipamento.', 'success'); }
                    if (typeof consultarRegistros === 'function') { consultarRegistros(); }
                }).fail(function() {
                    alert('Falha de comunicação ao salvar o vínculo.');
                });
            }

            function termoCelularRemoverVinculo() {
                if (!TERMOCEL_VINC_ID) { return; }
                var removerVinculo = function() {
                    $.ajax({
                        url: 'cadastro_celular.php',
                        method: 'POST',
                        data: { acao: 'salvarTermosVinculadosCelular', id: TERMOCEL_VINC_ID, modelos: [] },
                        dataType: 'json'
                    }).done(function(res) {
                        if (!res || !res.ok) {
                            if (window.Swal) { Swal.fire('Erro!', (res && res.error) ? res.error : 'Não foi possível remover o vínculo.', 'error'); }
                            else { alert((res && res.error) ? res.error : 'Não foi possível remover o vínculo.'); }
                            return;
                        }
                        $('#modal_termo_celular').modal('hide');
                        if (window.Swal) { Swal.fire('Removido!', 'O vínculo de termo deste equipamento foi removido.', 'info'); }
                        if (typeof consultarRegistros === 'function') { consultarRegistros(); }
                    }).fail(function() {
                        alert('Falha de comunicação ao remover o vínculo.');
                    });
                };
                if (window.Swal) {
                    Swal.fire({
                        title: 'Remover vínculo?',
                        text: 'O equipamento deixará de usar este termo na geração dos documentos.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sim, remover!',
                        cancelButtonText: 'Cancelar'
                    }).then(function(result) { if (result.isConfirmed) { removerVinculo(); } });
                } else if (confirm('Deseja remover o vínculo deste equipamento?')) {
                    removerVinculo();
                }
            }
        </script>
        ";
    }

    function modalEnviarTermoCelular(){
        $userId = intval($_SESSION["user_nb_id"] ?? 0);
        $empresaPadrao = 0;
        if($userId > 0){
            $resU = query("SELECT user_nb_empresa FROM user WHERE user_nb_id = ? LIMIT 1", "i", [$userId]);
            if($resU instanceof mysqli_result){
                $rowU = mysqli_fetch_assoc($resU);
                $empresaPadrao = intval($rowU["user_nb_empresa"] ?? 0);
            }
        }

        $empresasHtml = "";
        foreach(termos_opcoes_empresas() as $k => $v){
            if($k === ""){
                continue;
            }
            $checked = intval($k) === $empresaPadrao ? "checked" : "";
            $empresasHtml .= "<label class='checkbox-inline' style='margin-left:0; margin-right:14px;'><input type='checkbox' class='termocel-empresa' value='" . intval($k) . "' {$checked}> " . termos_h($v) . "</label>";
        }

        $cargosHtml = "";
        foreach(termos_opcoes_cargos() as $k => $v){
            if($k === ""){
                continue;
            }
            $cargosHtml .= "<label style='font-weight:normal; margin:0 12px 2px 0; display:inline-block;'><input type='checkbox' class='termocel-cargo' value='" . intval($k) . "'> " . termos_h($v) . "</label>";
        }
        $setoresHtml = "";
        foreach(termos_opcoes_setores() as $k => $v){
            if($k === ""){
                continue;
            }
            $setoresHtml .= "<label style='font-weight:normal; margin:0 12px 2px 0; display:inline-block;'><input type='checkbox' class='termocel-setor' value='" . intval($k) . "'> " . termos_h($v) . "</label>";
        }

        echo "
        <style>
            .termocel-ajuda { overflow-wrap: break-word; word-wrap: break-word; }
            #termocel_envio_titulo { overflow-wrap: break-word; word-wrap: break-word; }
            #termocel_envio_empresas label { overflow-wrap: break-word; word-wrap: break-word; }
        </style>
        <div class='modal fade' id='modal_enviar_termo_celular' tabindex='-1' role='dialog'>
            <div class='modal-dialog modal-lg' role='document'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <button type='button' class='close' data-dismiss='modal'>&times;</button>
                        <h4 class='modal-title' id='termocel_envio_titulo'>Enviar Termo</h4>
                    </div>
                    <div class='modal-body'>
                        <div id='termocel_envio_info' class='alert alert-info' style='padding:8px 12px; font-size:12px;'></div>
                        <div class='row'>
                            <div class='col-sm-12' style='margin-bottom:10px;'>
                                <b>Empresas</b> <span class='text-muted'>(a da sua sessão já vem marcada — pode marcar várias)</span>
                                <div style='margin:4px 0; display:flex; flex-wrap:wrap; gap:4px; align-items:center;'>
                                    <button type='button' class='btn btn-default btn-xs' onclick='termocelEnvioEmpresasMarcar(true)'>Marcar todas</button>
                                    <button type='button' class='btn btn-default btn-xs' onclick='termocelEnvioEmpresasMarcar(false)'>Desmarcar todas</button>
                                    <span style='margin-left:10px;'>Selecionar as</span>
                                    <input type='number' id='termocel_envio_qtd_empresas' class='form-control input-sm' style='display:inline-block; width:60px;' min='1' placeholder='N'>
                                    <span>primeiras</span>
                                    <button type='button' class='btn btn-default btn-xs' onclick='termocelEnvioEmpresasSelecionarQtd()'>Aplicar</button>
                                </div>
                                <div id='termocel_envio_empresas'>{$empresasHtml}</div>
                            </div>
                        </div>
                        <div class='row' style='margin-bottom:8px;'>
                            <div class='col-sm-4'>
                                <b>Cargo</b>
                                <div style='margin:2px 0;'>
                                    <button type='button' class='btn btn-default btn-xs' onclick='termocelEnvioCargosMarcar(true)'>Marcar todos</button>
                                    <button type='button' class='btn btn-default btn-xs' onclick='termocelEnvioCargosMarcar(false)'>Desmarcar todos</button>
                                </div>
                                <div id='termocel_envio_cargos' style='max-height:120px; overflow-y:auto; border:1px solid #eee; padding:4px; background:#fcfcfc;'>
                                    {$cargosHtml}
                                </div>
                            </div>
                            <div class='col-sm-4'>
                                <b>Setor</b>
                                <div style='margin:2px 0;'>
                                    <button type='button' class='btn btn-default btn-xs' onclick='termocelEnvioSetoresMarcar(true)'>Marcar todos</button>
                                    <button type='button' class='btn btn-default btn-xs' onclick='termocelEnvioSetoresMarcar(false)'>Desmarcar todos</button>
                                </div>
                                <div id='termocel_envio_setores' style='max-height:120px; overflow-y:auto; border:1px solid #eee; padding:4px; background:#fcfcfc;'>
                                    {$setoresHtml}
                                </div>
                            </div>
                            <div class='col-sm-4'>
                                <b>Buscar funcionário</b>
                                <input id='termocel_envio_busca' class='form-control input-sm' placeholder='Nome, matrícula ou CPF'>
                                <div class='text-muted' style='font-size:11px; margin-top:2px;'>Vazio = todos os funcionários das empresas selecionadas.</div>
                                <div style='margin-top:6px;'>
                                    <button type='button' class='btn btn-default btn-xs' onclick='termocelEnvioMarcarTodos(true)'>Marcar todos</button>
                                    <button type='button' class='btn btn-default btn-xs' onclick='termocelEnvioMarcarTodos(false)'>Desmarcar todos</button>
                                    <button type='button' class='btn btn-default btn-xs' onclick='termocelEnvioMarcarSemTermo()'>Somente sem termo</button>
                                </div>
                            </div>
                        </div>
                        <div id='termocel_envio_lista' style='min-height:60px;'>
                            <div class='text-muted'>Selecione uma ou mais empresas para carregar os funcionários.</div>
                        </div>
                        <div class='row' style='margin-top:10px;'>
                            <div class='col-sm-3'>
                                <label class='control-label'>Validar ICP</label>
                                <select id='termocel_envio_icp' class='form-control input-sm'>
                                    <option value='nao'>Não</option>
                                    <option value='sim'>Sim</option>
                                </select>
                                <span class='help-block termocel-ajuda' style='font-size:11px; margin-bottom:0;'>Aplica assinatura digital ICP-Brasil no PDF final ao concluir as assinaturas.</span>
                            </div>
                            <div class='col-sm-3'>
                                <label class='control-label'>Enviar e-mail</label>
                                <select id='termocel_envio_email' class='form-control input-sm'>
                                    <option value='sim'>Sim</option>
                                    <option value='nao'>Não</option>
                                </select>
                                <span class='help-block termocel-ajuda' style='font-size:11px; margin-bottom:0;'>Envia o e-mail com o link de assinatura para cada funcionário.</span>
                            </div>
                            <div class='col-sm-3'>
                                <label class='control-label'>Forçar regeração</label>
                                <select id='termocel_envio_forcar' class='form-control input-sm'>
                                    <option value='nao'>Não</option>
                                    <option value='sim'>Sim</option>
                                </select>
                                <span class='help-block termocel-ajuda' style='font-size:11px; margin-bottom:0;'>Não: pula quem já tem termo. Sim: gera de novo (novo PDF e nova assinatura).</span>
                            </div>
                            <div class='col-sm-3' style='padding-top:28px;'>
                                <b id='termocel_envio_selecionados'>0 selecionado(s)</b>
                                <span class='help-block termocel-ajuda' style='font-size:11px; margin-bottom:0;'>Badge mostra o status do termo de cada funcionário.</span>
                            </div>
                        </div>
                        <div class='row' style='margin-top:6px;'>
                            <div class='col-sm-3'>
                                <label class='control-label'>Prazo para assinatura (dias)</label>
                                <input type='number' id='termocel_envio_prazo' class='form-control input-sm' min='0' max='30' step='1' value='1'>
                                <span class='help-block termocel-ajuda' style='font-size:11px; margin-bottom:0;'>Quantos dias o link de assinatura fica válido. 0 = sem prazo de expiração.</span>
                            </div>
                            <div class='col-sm-3'>
                                <label class='control-label'>Assinatura por hierarquia</label>
                                <select id='termocel_envio_hierarquia' class='form-control input-sm'>
                                    <option value='nao'>Não</option>
                                    <option value='sim'>Sim</option>
                                </select>
                                <span class='help-block termocel-ajuda' style='font-size:11px; margin-bottom:0;'>Sim: os responsáveis do funcionário assinam em sequência e, por último, o funcionário.</span>
                            </div>
                        </div>
                        <div id='termocel_envio_progresso' style='display:none; margin-top:12px;'>
                            <div class='progress' style='margin-bottom:4px;'>
                                <div id='termocel_envio_barra' class='progress-bar progress-bar-success' style='width:0%'></div>
                            </div>
                            <div id='termocel_envio_texto' class='text-muted'></div>
                            <div id='termocel_envio_resultado' style='margin-top:8px; max-height:200px; overflow:auto; font-size:12px;'></div>
                        </div>
                    </div>
                    <div class='modal-footer'>
                        <button type='button' class='btn btn-default' data-dismiss='modal'>Fechar</button>
                        <button type='button' id='termocel_envio_btn' class='btn btn-success' onclick='termoCelularEnviar()'>
                            Enviar Termo
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            var TERMOCEL_ENVIO_ATIVO = 0;
            var TERMOCEL_ENVIO_MODELO = 0;
            var TERMOCEL_ENVIO_RESP = 0;
            var TERMOCEL_ENVIO_RESP_MARCADO = false;

            function termoCelularAbrirEnvio(id) {
                if (!id) { return; }
                $.ajax({
                    url: 'cadastro_celular.php',
                    method: 'POST',
                    data: { acao: 'dadosEnvioTermo', id: id },
                    dataType: 'json'
                }).done(function(res) {
                    if (!res || !res.ok) {
                        if (window.Swal) { Swal.fire('Atenção!', (res && res.error) ? res.error : 'Não foi possível carregar os dados do equipamento.', 'warning'); }
                        else { alert((res && res.error) ? res.error : 'Não foi possível carregar os dados do equipamento.'); }
                        return;
                    }
                    TERMOCEL_ENVIO_ATIVO = parseInt(res.equipamento.id, 10) || 0;
                    TERMOCEL_ENVIO_MODELO = parseInt(res.modelo.id, 10) || 0;
                    TERMOCEL_ENVIO_RESP = parseInt(res.responsavel.id, 10) || 0;
                    TERMOCEL_ENVIO_RESP_MARCADO = false;
                    $('#termocel_envio_titulo').text('Enviar Termo — ' + (res.modelo.nome || ''));
                    $('#termocel_envio_info').html(
                        '<i class=\"fa fa-mobile\"></i> Equipamento: <b>#' + TERMOCEL_ENVIO_ATIVO + ' — ' + termoCelularEscape(res.equipamento.nome) + '</b>'
                        + (res.equipamento.imei ? ' <span class=\"text-muted\">(IMEI: ' + termoCelularEscape(res.equipamento.imei) + ')</span>' : '')
                        + '<br><i class=\"fa fa-file-text-o\"></i> Termo: <b>' + termoCelularEscape(res.modelo.nome) + '</b>'
                        + (TERMOCEL_ENVIO_RESP > 0 ? '<br><i class=\"fa fa-user\"></i> Responsável: <b>' + termoCelularEscape(res.responsavel.nome) + '</b>' : '')
                    );
                    $('#termocel_envio_progresso').hide();
                    $('#termocel_envio_resultado').html('').hide();
                    $('#termocel_envio_lista').html('<div class=\"text-muted\">Selecione uma ou mais empresas para carregar os funcionários.</div>');
                    termocelEnvioAtualizarContagem();
                    $('#modal_enviar_termo_celular').modal('show');
                    termoCelularCarregarFuncionarios();
                }).fail(function() {
                    alert('Falha de comunicação ao carregar os dados do equipamento.');
                });
            }

            function termocelEnvioEmpresasSelecionadas() {
                var empresas = [];
                $('#termocel_envio_empresas .termocel-empresa:checked').each(function() {
                    empresas.push(parseInt($(this).val(), 10));
                });
                return empresas;
            }

            function termocelEnvioCargosSelecionados() {
                var cargos = [];
                $('#termocel_envio_cargos .termocel-cargo:checked').each(function() {
                    cargos.push(parseInt($(this).val(), 10));
                });
                return cargos;
            }

            function termocelEnvioSetoresSelecionados() {
                var setores = [];
                $('#termocel_envio_setores .termocel-setor:checked').each(function() {
                    setores.push(parseInt($(this).val(), 10));
                });
                return setores;
            }

            function termocelEnvioEmpresasMarcar(marcar) {
                $('#termocel_envio_empresas .termocel-empresa').prop('checked', marcar);
                termoCelularUniform();
                termoCelularCarregarFuncionarios();
            }

            function termocelEnvioCargosMarcar(marcar) {
                $('#termocel_envio_cargos .termocel-cargo').prop('checked', marcar);
                termoCelularUniform();
                termoCelularCarregarFuncionarios();
            }

            function termocelEnvioSetoresMarcar(marcar) {
                $('#termocel_envio_setores .termocel-setor').prop('checked', marcar);
                termoCelularUniform();
                termoCelularCarregarFuncionarios();
            }

            function termocelEnvioEmpresasSelecionarQtd() {
                var qtd = parseInt($('#termocel_envio_qtd_empresas').val() || '0', 10);
                if (isNaN(qtd) || qtd < 1) {
                    alert('Informe a quantidade de empresas.');
                    return;
                }
                var boxes = $('#termocel_envio_empresas .termocel-empresa');
                boxes.prop('checked', false);
                boxes.slice(0, qtd).prop('checked', true);
                termoCelularUniform();
                termoCelularCarregarFuncionarios();
            }

            function termoCelularCarregarFuncionarios() {
                var empresas = termocelEnvioEmpresasSelecionadas();
                if (empresas.length === 0) {
                    $('#termocel_envio_lista').html('<div class=\"text-muted\">Nenhuma empresa selecionada.</div>');
                    termocelEnvioAtualizarContagem();
                    return;
                }
                var marcados = {};
                $('#termocel_envio_lista .termocel-func:checked').each(function() {
                    marcados[$(this).val()] = true;
                });
                $('#termocel_envio_lista').html('<div class=\"text-muted\">Carregando funcionários...</div>');
                $.ajax({
                    url: 'documentos/termos/buscar_funcionarios.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        empresas: empresas,
                        cargos: termocelEnvioCargosSelecionados(),
                        setores: termocelEnvioSetoresSelecionados(),
                        busca: $('#termocel_envio_busca').val() || '',
                        modelo: TERMOCEL_ENVIO_MODELO
                    }),
                    dataType: 'json'
                }).done(function(res) {
                    if (!res || !res.ok) {
                        $('#termocel_envio_lista').html('<div class=\"alert alert-danger\">' + termoCelularEscape(res && res.error ? res.error : 'Falha ao carregar funcionários.') + '</div>');
                        return;
                    }
                    var html = '<div class=\"text-muted\" style=\"margin-bottom:4px;\">' + res.total + ' funcionário(s).</div>';
                    html += '<div class=\"table-responsive\" style=\"max-height:320px; overflow:auto;\"><table class=\"table table-bordered table-striped table-condensed\">';
                    html += '<thead><tr><th style=\"width:30px\"></th><th>Matrícula</th><th>Nome</th><th>CPF</th><th>Cargo</th><th>Setor</th><th>Empresa</th><th>Termo</th></tr></thead><tbody>';
                    $.each(res.funcionarios, function(i, f) {
                        var badge = '';
                        if (f.status_termo) {
                            var cor = (f.status_termo === 'assinado' || f.status_termo === 'visualizado') ? 'success' : ((f.status_termo === 'aguardando_assinatura' || f.status_termo === 'notificado') ? 'warning' : 'info');
                            badge = '<span class=\"label label-' + cor + '\">' + termoCelularEscape(f.status_termo) + '</span>';
                        } else {
                            badge = '<span class=\"label label-default\">sem termo</span>';
                        }
                        var checked = (marcados[f.enti_nb_id] || (!TERMOCEL_ENVIO_RESP_MARCADO && TERMOCEL_ENVIO_RESP > 0 && parseInt(f.enti_nb_id, 10) === TERMOCEL_ENVIO_RESP)) ? 'checked' : '';
                        html += '<tr><td><input type=\"checkbox\" class=\"termocel-func\" value=\"' + f.enti_nb_id + '\" ' + checked + '></td>'
                            + '<td>' + termoCelularEscape(f.matricula) + '</td>'
                            + '<td>' + termoCelularEscape(f.nome) + '</td>'
                            + '<td>' + termoCelularEscape(f.cpf) + '</td>'
                            + '<td>' + termoCelularEscape(f.cargo) + '</td>'
                            + '<td>' + termoCelularEscape(f.setor) + '</td>'
                            + '<td>' + termoCelularEscape(f.empresa) + '</td>'
                            + '<td>' + badge + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                    $('#termocel_envio_lista').html(html);
                    TERMOCEL_ENVIO_RESP_MARCADO = true;
                    termoCelularUniform();
                    termocelEnvioAtualizarContagem();
                }).fail(function() {
                    $('#termocel_envio_lista').html('<div class=\"alert alert-danger\">Falha de comunicação ao carregar funcionários.</div>');
                });
            }

            function termocelEnvioAtualizarContagem() {
                $('#termocel_envio_selecionados').text($('#termocel_envio_lista .termocel-func:checked').length + ' selecionado(s)');
            }

            function termocelEnvioMarcarTodos(marcar) {
                $('#termocel_envio_lista .termocel-func').prop('checked', marcar);
                termoCelularUniform();
                termocelEnvioAtualizarContagem();
            }

            function termocelEnvioMarcarSemTermo() {
                $('#termocel_envio_lista .termocel-func').each(function() {
                    var tr = $(this).closest('tr');
                    $(this).prop('checked', tr.find('.label-default').length > 0);
                });
                termoCelularUniform();
                termocelEnvioAtualizarContagem();
            }

            function termoCelularEnviar() {
                var ids = [];
                $('#termocel_envio_lista .termocel-func:checked').each(function() {
                    ids.push(parseInt($(this).val(), 10));
                });
                if (TERMOCEL_ENVIO_ATIVO <= 0) {
                    alert('Equipamento inválido.');
                    return;
                }
                if (TERMOCEL_ENVIO_MODELO <= 0) {
                    alert('Nenhum termo vinculado a este equipamento.');
                    return;
                }
                if (ids.length === 0) {
                    alert('Selecione ao menos um funcionário.');
                    return;
                }
                $('#termocel_envio_btn').prop('disabled', true);
                $('#termocel_envio_progresso').show();
                $('#termocel_envio_resultado').html('').hide();
                var params = {
                    modelo_id: TERMOCEL_ENVIO_MODELO,
                    enviar_assinatura: 'sim',
                    validar_icp: $('#termocel_envio_icp').val(),
                    enviar_email: $('#termocel_envio_email').val(),
                    forcar: $('#termocel_envio_forcar').val(),
                    prazo_expiracao_dias: parseInt($('#termocel_envio_prazo').val(), 10) || 0,
                    hierarquia: $('#termocel_envio_hierarquia').val()
                };
                var total = ids.length, feitos = 0, okC = 0, errC = 0, resumo = [];
                function proximo(inicio) {
                    if (inicio >= total) {
                        $('#termocel_envio_barra').css('width', '100%');
                        $('#termocel_envio_texto').text('Concluído: ' + total + ' processado(s).');
                        $('#termocel_envio_resultado').html('<b>Resumo: ' + okC + ' OK | ' + errC + ' erro(s)</b><br>' + resumo.join('<br>')).show();
                        $('#termocel_envio_btn').prop('disabled', false);
                        termoCelularCarregarFuncionarios();
                        return;
                    }
                    var fim = Math.min(inicio + 5, total);
                    var chunk = ids.slice(inicio, fim);
                    var ativos = {};
                    chunk.forEach(function(eid) { ativos[eid] = TERMOCEL_ENVIO_ATIVO; });
                    $.ajax({
                        url: 'documentos/termos/processar_termos.php',
                        method: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify($.extend({ entidades: chunk, ativos: ativos }, params)),
                        dataType: 'json'
                    }).done(function(res) {
                        if (res && Array.isArray(res.resultados)) {
                            $.each(res.resultados, function(i, r) {
                                feitos++;
                                if (r.ok) { okC++; } else { errC++; }
                                resumo.push((r.ok ? 'OK' : 'ERRO') + ' - ' + (r.msg || ''));
                            });
                        } else {
                            feitos += chunk.length;
                            errC += chunk.length;
                            resumo.push('ERRO - ' + ((res && res.error) ? res.error : 'resposta inválida do servidor.'));
                        }
                        $('#termocel_envio_barra').css('width', Math.round(feitos * 100 / total) + '%');
                        $('#termocel_envio_texto').text('Processando ' + feitos + ' de ' + total + '...');
                        proximo(fim);
                    }).fail(function(xhr) {
                        feitos += chunk.length;
                        errC += chunk.length;
                        var detalhe = (xhr && xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'falha de comunicação (verifique as permissões de acesso aos termos).';
                        resumo.push('ERRO - ' + detalhe);
                        $('#termocel_envio_barra').css('width', Math.round(feitos * 100 / total) + '%');
                        proximo(fim);
                    });
                }
                proximo(0);
            }

            $(function() {
                $('#termocel_envio_empresas').on('change', '.termocel-empresa', termoCelularCarregarFuncionarios);
                $('#termocel_envio_cargos').on('change', '.termocel-cargo', termoCelularCarregarFuncionarios);
                $('#termocel_envio_setores').on('change', '.termocel-setor', termoCelularCarregarFuncionarios);
                $('#termocel_envio_lista').on('change', '.termocel-func', termocelEnvioAtualizarContagem);
                var termocelTimerBusca = null;
                $('#termocel_envio_busca').on('input', function() {
                    clearTimeout(termocelTimerBusca);
                    termocelTimerBusca = setTimeout(termoCelularCarregarFuncionarios, 350);
                });
            });
        </script>
        ";
    }

    function modalLoteTermosCelular(){
        echo "
        <div class='modal fade' id='modal_lote_termos_celular' tabindex='-1' role='dialog'>
            <div class='modal-dialog modal-lg' role='document'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <button type='button' class='close' data-dismiss='modal'>&times;</button>
                        <h4 class='modal-title'>Enviar Termos (lote por aparelhos)</h4>
                    </div>
                    <div class='modal-body'>
                        <div class='alert alert-info' style='padding:8px 12px; font-size:12px;'>
                            <i class='fa fa-mobile'></i> Envia o termo vinculado a cada aparelho para o <b>responsável</b> daquele aparelho, com os dados daquele aparelho. Somente aparelhos com responsável e termo vinculado aparecem.
                        </div>
                        <div class='row' style='margin-bottom:8px;'>
                            <div class='col-sm-8'>
                                <label class='control-label'>Termo a enviar</label>
                                <select id='termoCelularLoteModelo' class='form-control input-sm'></select>
                            </div>
                            <div class='col-sm-4 text-right' style='padding-top:22px;'>
                                <b id='termoCelularLoteSel'>0 selecionado(s)</b>
                            </div>
                        </div>
                        <div class='row' style='margin-bottom:8px;'>
                            <div class='col-sm-8'>
                                <button type='button' class='btn btn-default btn-xs' onclick='termoCelularLoteMarcar(true)'>Marcar todos</button>
                                <button type='button' class='btn btn-default btn-xs' onclick='termoCelularLoteMarcar(false)'>Desmarcar todos</button>
                                <button type='button' class='btn btn-default btn-xs' onclick='termoCelularLoteMarcarEmUso()'>Somente em uso</button>
                            </div>
                            <div class='col-sm-4'>
                                <input id='termoCelularLoteBusca' class='form-control input-sm' placeholder='Buscar aparelho/responsável'>
                            </div>
                        </div>
                        <div id='termoCelularLoteLista' style='max-height:320px; overflow:auto; border:1px solid #eee; padding:4px; background:#fcfcfc;'>
                            <div class='text-muted'>Carregando aparelhos...</div>
                        </div>
                        <div class='row' style='margin-top:10px;'>
                            <div class='col-sm-3'>
                                <label class='control-label'>Validar ICP</label>
                                <select id='termoCelularLoteIcp' class='form-control input-sm'>
                                    <option value='nao'>Não</option>
                                    <option value='sim'>Sim</option>
                                </select>
                            </div>
                            <div class='col-sm-3'>
                                <label class='control-label'>Enviar e-mail</label>
                                <select id='termoCelularLoteEmail' class='form-control input-sm'>
                                    <option value='sim'>Sim</option>
                                    <option value='nao'>Não</option>
                                </select>
                            </div>
                            <div class='col-sm-3'>
                                <label class='control-label'>Forçar regeração</label>
                                <select id='termoCelularLoteForcar' class='form-control input-sm'>
                                    <option value='nao'>Não</option>
                                    <option value='sim'>Sim</option>
                                </select>
                            </div>
                            <div class='col-sm-3'>
                                <label class='control-label'>Prazo (dias)</label>
                                <input type='number' id='termoCelularLotePrazo' class='form-control input-sm' min='0' max='30' value='1'>
                            </div>
                        </div>
                        <div class='row' style='margin-top:6px;'>
                            <div class='col-sm-3'>
                                <label class='control-label'>Assinatura por hierarquia</label>
                                <select id='termoCelularLoteHier' class='form-control input-sm'>
                                    <option value='nao'>Não</option>
                                    <option value='sim'>Sim</option>
                                </select>
                            </div>
                        </div>
                        <div id='termoCelularLoteProgresso' style='display:none; margin-top:12px;'>
                            <div class='progress' style='margin-bottom:4px;'>
                                <div id='termoCelularLoteBarra' class='progress-bar progress-bar-success' style='width:0%'></div>
                            </div>
                            <div id='termoCelularLoteTexto' class='text-muted'></div>
                            <div id='termoCelularLoteResultado' style='margin-top:8px; max-height:200px; overflow:auto; font-size:12px;'></div>
                        </div>
                    </div>
                    <div class='modal-footer'>
                        <button type='button' class='btn btn-default' data-dismiss='modal'>Fechar</button>
                        <button type='button' id='termoCelularLoteBtn' class='btn btn-success' onclick='termoCelularLoteEnviar()'>Enviar selecionados</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            var TERMOCEL_LOTE_APARELHOS = [];
            function termoCelularAbrirLote() {
                TERMOCEL_LOTE_APARELHOS = [];
                $('#termoCelularLoteLista').html('<div class=\"text-muted\">Carregando aparelhos...</div>');
                $('#termoCelularLoteProgresso').hide();
                $('#termoCelularLoteResultado').html('').hide();
                $('#modal_lote_termos_celular').modal('show');
                $.ajax({
                    url: 'cadastro_celular.php',
                    method: 'POST',
                    data: { acao: 'dadosLoteTermosCelular' },
                    dataType: 'json'
                }).done(function(res) {
                    if (!res || !res.ok) {
                        $('#termoCelularLoteLista').html('<div class=\"alert alert-danger\">Falha ao carregar aparelhos.</div>');
                        return;
                    }
                    TERMOCEL_LOTE_APARELHOS = res.aparelhos || [];
                    // Popula seletor de termos únicos a partir dos termos retornados.
                    var modelosUnicos = {};
                    var nomes = {};
                    $.each(TERMOCEL_LOTE_APARELHOS, function(i, a) {
                        $.each(a.termos || [], function(j, t) {
                            var mid = parseInt(t.id, 10);
                            if (mid > 0) {
                                modelosUnicos[mid] = true;
                                if (!nomes[mid]) { nomes[mid] = t.nome || ('Modelo #' + mid); }
                            }
                        });
                    });
                    var opts = '<option value=\"\">Todos os termos</option>';
                    $.each(Object.keys(modelosUnicos), function(i, mid) {
                        opts += '<option value=\"' + mid + '\">' + termoCelularEscape(nomes[mid]) + '</option>';
                    });
                    $('#termoCelularLoteModelo').html(opts);
                    termoCelularLoteRender();
                }).fail(function() {
                    $('#termoCelularLoteLista').html('<div class=\"alert alert-danger\">Falha de comunicação.</div>');
                });
            }
            function termoCelularLoteRender() {
                var busca = ($('#termoCelularLoteBusca').val() || '').toLowerCase();
                var modeloFiltro = parseInt($('#termoCelularLoteModelo').val() || '0', 10) || 0;
                var html = '<table class=\"table table-bordered table-striped table-condensed\"><thead><tr><th style=\"width:30px\"></th><th>Aparelho</th><th>IMEI</th><th>Responsável</th><th>Status</th><th>Termo vinculado</th></tr></thead><tbody>';
                $.each(TERMOCEL_LOTE_APARELHOS, function(i, a) {
                    var txt = ((a.celu_tx_nome||'') + ' ' + (a.celu_tx_imei||'') + ' ' + (a.resp_nome||'') + ' ' + (a.modelo_nome||'')).toLowerCase();
                    if (busca && txt.indexOf(busca) === -1) { return; }
                    var emUso = (a.celu_tx_status === 'em_uso');
                    html += '<tr>'
                        + '<td><input type=\"checkbox\" class=\"termoCelularLoteChk\" value=\"' + a.celu_nb_id + '\" data-status=\"' + (a.celu_tx_status||'') + '\" ' + (emUso ? '' : 'disabled') + '></td>'
                        + '<td>' + termoCelularEscape(a.celu_tx_nome) + '</td>'
                        + '<td>' + termoCelularEscape(a.celu_tx_imei) + '</td>'
                        + '<td>' + termoCelularEscape(a.resp_nome) + '</td>'
                        + '<td>' + termoCelularEscape(a.celu_tx_status) + '</td>'
                        + '<td>' + termoCelularEscape(a.modelo_nome) + '</td>'
                        + '</tr>';
                });
                html += '</tbody></table>';
                $('#termoCelularLoteLista').html(html);
                termoCelularLoteAtualizar();
            }
            function termoCelularLoteMarcar(marcar) {
                $('#termoCelularLoteLista .termoCelularLoteChk').prop('checked', marcar);
                termoCelularLoteAtualizar();
            }
            function termoCelularLoteMarcarEmUso() {
                $('#termoCelularLoteLista .termoCelularLoteChk').each(function() {
                    $(this).prop('checked', $(this).attr('data-status') === 'em_uso');
                });
                termoCelularLoteAtualizar();
            }
            function termoCelularLoteAtualizar() {
                $('#termoCelularLoteSel').text($('#termoCelularLoteLista .termoCelularLoteChk:checked').length + ' selecionado(s)');
            }
            $(function() {
                $('#termoCelularLoteBusca').on('input', termoCelularLoteRender);
                $('#termoCelularLoteLista').on('change', '.termoCelularLoteChk', termoCelularLoteAtualizar);
            });
            function termoCelularLoteEnviar() {
                var ids = [];
                $('#termoCelularLoteLista .termoCelularLoteChk:checked').each(function() { ids.push(parseInt($(this).val(), 10)); });
                if (ids.length === 0) { alert('Selecione ao menos um aparelho.'); return; }
                $('#termoCelularLoteBtn').prop('disabled', true);
                $('#termoCelularLoteProgresso').show();
                $('#termoCelularLoteResultado').html('').hide();
                var params = {
                    modelo_id: parseInt($('#termoCelularLoteModelo').val() || '0', 10) || 0,
                    validar_icp: $('#termoCelularLoteIcp').val(),
                    enviar_email: $('#termoCelularLoteEmail').val(),
                    forcar: $('#termoCelularLoteForcar').val(),
                    prazo_expiracao_dias: parseInt($('#termoCelularLotePrazo').val(), 10) || 0,
                    hierarquia: $('#termoCelularLoteHier').val()
                };
                var total = ids.length, feitos = 0, okC = 0, errC = 0, resumo = [], resumoOk = [], resumoErr = [];
                function proximo(inicio) {
                    if (inicio >= total) {
                        $('#termoCelularLoteBarra').css('width', '100%');
                        $('#termoCelularLoteTexto').text('Concluído: ' + total + ' processado(s).');
                        var htmlRes = '<b>Resumo: ' + okC + ' OK | ' + errC + ' erro(s)</b>';
                        if (resumoOk.length) {
                            htmlRes += '<br><br><b class=\"text-success\">Enviados com sucesso:</b><br>' + resumoOk.map(function(m){ return 'OK - ' + m; }).join('<br>');
                        }
                        if (resumoErr.length) {
                            htmlRes += '<br><br><b class=\"text-danger\">Erros:</b><br>' + resumoErr.map(function(m){ return 'ERRO - ' + m; }).join('<br>');
                        }
                        $('#termoCelularLoteResultado').html(htmlRes).show();
                        $('#termoCelularLoteBtn').prop('disabled', false);
                        return;
                    }
                    var fim = Math.min(inicio + 5, total);
                    var chunk = ids.slice(inicio, fim);
                    $.ajax({
                        url: 'documentos/termos/processar_termos_aparelhos.php',
                        method: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify($.extend({ aparelhos: chunk }, params)),
                        dataType: 'json'
                    }).done(function(res) {
                        if (res && Array.isArray(res.resultados)) {
                            $.each(res.resultados, function(i, r) {
                                feitos++;
                                if (r.ok) { okC++; resumoOk.push(r.msg || ''); } else { errC++; resumoErr.push(r.msg || ''); }
                            });
                        } else {
                            feitos += chunk.length; errC += chunk.length;
                            resumo.push('ERRO - ' + ((res && res.error) ? res.error : 'resposta inválida.'));
                        }
                        $('#termoCelularLoteBarra').css('width', Math.round(feitos * 100 / total) + '%');
                        $('#termoCelularLoteTexto').text('Processando ' + feitos + ' de ' + total + '...');
                        proximo(fim);
                    }).fail(function(xhr) {
                        feitos += chunk.length; errC += chunk.length;
                        var detalhe = 'falha de comunicação.';
                        if (xhr) {
                            if (xhr.responseJSON && xhr.responseJSON.error) { detalhe = xhr.responseJSON.error; }
                            else if (xhr.responseText) { detalhe = xhr.responseText.replace(/<[^>]*>/g, '').trim().substring(0, 200); }
                            else if (xhr.status) { detalhe = 'HTTP ' + xhr.status + ' ' + (xhr.statusText || ''); }
                        }
                        resumo.push('ERRO - ' + detalhe);
                        $('#termoCelularLoteBarra').css('width', Math.round(feitos * 100 / total) + '%');
                        proximo(fim);
                    });
                }
                proximo(0);
            }
        </script>
        ";
    }

    function modalHistoricoCelular(){
        echo "
        <div class='modal fade' id='modal_historico_celular' tabindex='-1' role='dialog'>
            <div class='modal-dialog modal-lg' role='document'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <button type='button' class='close' data-dismiss='modal'>&times;</button>
                        <h4 class='modal-title'>Histórico do Aparelho</h4>
                    </div>
                    <div class='modal-body'>
                        <div id='celularHistoricoInfo' class='text-muted' style='margin-bottom:8px;'></div>
                        <div id='celularHistoricoLista' style='max-height:360px; overflow:auto;'>
                            <div class='text-muted'>Carregando...</div>
                        </div>
                    </div>
                    <div class='modal-footer'>
                        <button type='button' class='btn btn-default' data-dismiss='modal'>Fechar</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            function celularAbrirHistorico(id) {
                if (!id) { return; }
                $('#celularHistoricoInfo').html('Aparelho #' + id);
                $('#celularHistoricoLista').html('<div class=\"text-muted\">Carregando...</div>');
                $('#modal_historico_celular').modal('show');
                $.ajax({
                    url: 'cadastro_celular.php',
                    method: 'POST',
                    data: { acao: 'historicoCelular', id: id },
                    dataType: 'json'
                }).done(function(res) {
                    if (!res || !res.ok) {
                        $('#celularHistoricoLista').html('<div class=\"alert alert-danger\">Falha ao carregar histórico.</div>');
                        return;
                    }
                    var html = '<table class=\"table table-bordered table-striped table-condensed\"><thead><tr><th>Data</th><th>Usuário</th><th>Status (ant → novo)</th><th>Responsável (ant → novo)</th><th>Motivo</th></tr></thead><tbody>';
                    $.each(res.itens, function(i, m) {
                        html += '<tr>'
                            + '<td>' + termoCelularEscape(m.movi_dt_data) + '</td>'
                            + '<td>' + termoCelularEscape(m.usuario) + '</td>'
                            + '<td>' + termoCelularEscape(m.movi_tx_status_anterior || '-') + ' → ' + termoCelularEscape(m.movi_tx_status_novo || '-') + '</td>'
                            + '<td>' + termoCelularEscape(m.nome_ant || '-') + ' → ' + termoCelularEscape(m.nome_novo || '-') + '</td>'
                            + '<td>' + termoCelularEscape(m.movi_tx_motivo || '-') + '</td>'
                            + '</tr>';
                    });
                    if (!res.itens || !res.itens.length) {
                        html += '<tr><td colspan=\"5\" class=\"text-muted\">Sem movimentações registradas.</td></tr>';
                    }
                    html += '</tbody></table>';
                    $('#celularHistoricoLista').html(html);
                }).fail(function() {
                    $('#celularHistoricoLista').html('<div class=\"alert alert-danger\">Falha de comunicação.</div>');
                });
            }
        </script>
        ";
    }

    function modalStatusCelular(){
        $opcoes = celular_status_opcoes();
        $optsHtml = "";
        foreach($opcoes as $k => $v){
            $optsHtml .= "<option value='" . htmlspecialchars($k, ENT_QUOTES) . "'>" . htmlspecialchars($v, ENT_QUOTES) . "</option>";
        }
        echo "
        <div class='modal fade' id='modal_status_celular' tabindex='-1' role='dialog'>
            <div class='modal-dialog' role='document'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <button type='button' class='close' data-dismiss='modal'>&times;</button>
                        <h4 class='modal-title'>Alterar Status do Aparelho</h4>
                    </div>
                    <div class='modal-body'>
                        <input type='hidden' id='statusCelularId' value=''>
                        <div class='form-group'>
                            <label>Status</label>
                            <select id='statusCelularNovo' class='form-control input-sm'>{$optsHtml}</select>
                        </div>
                        <div class='form-group'>
                            <label>Motivo (opcional)</label>
                            <textarea id='statusCelularMotivo' class='form-control input-sm' rows='2'></textarea>
                        </div>
                    </div>
                    <div class='modal-footer'>
                        <button type='button' class='btn btn-default' data-dismiss='modal'>Cancelar</button>
                        <button type='button' class='btn btn-success' onclick='celularSalvarStatus()'>Salvar</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            function celularAbrirStatus(id) {
                if (!id) { return; }
                $('#statusCelularId').val(id);
                $('#statusCelularMotivo').val('');
                $('#modal_status_celular').modal('show');
            }
            function celularSalvarStatus() {
                var id = parseInt($('#statusCelularId').val(), 10) || 0;
                if (!id) { return; }
                $.ajax({
                    url: 'cadastro_celular.php',
                    method: 'POST',
                    data: { acao: 'alterarStatusCelular', id: id, status: $('#statusCelularNovo').val(), motivo: $('#statusCelularMotivo').val() },
                    dataType: 'json'
                }).done(function(res) {
                    if (!res || !res.ok) {
                        if (window.Swal) { Swal.fire('Erro!', (res && res.error) ? res.error : 'Não foi possível alterar.', 'error'); }
                        else { alert((res && res.error) ? res.error : 'Não foi possível alterar.'); }
                        return;
                    }
                    $('#modal_status_celular').modal('hide');
                    if (window.Swal) { Swal.fire('Sucesso!', 'Status atualizado.', 'success'); }
                    if (typeof consultarRegistros === 'function') { consultarRegistros(); }
                }).fail(function() {
                    alert('Falha de comunicação ao alterar status.');
                });
            }
        </script>
        ";
    }

    function modalEnviarTermoDiretoCelular(){
        echo "
        <div class='modal fade' id='modal_enviar_termo_direto_celular' tabindex='-1' role='dialog'>
            <div class='modal-dialog' role='document'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <button type='button' class='close' data-dismiss='modal'>&times;</button>
                        <h4 class='modal-title' id='termoCelularDiretoTitulo'>Enviar Termo</h4>
                    </div>
                    <div class='modal-body'>
                        <div id='termoCelularDiretoInfo' class='alert alert-info' style='padding:8px 12px; font-size:12px;'></div>
                        <div class='row' style='margin-bottom:8px;'>
                            <div class='col-sm-12'>
                                <label class='control-label'>Termo a enviar</label>
                                <select id='termoCelularDiretoModelo' class='form-control input-sm'></select>
                            </div>
                        </div>
                        <div class='row'>
                            <div class='col-sm-4'>
                                <label class='control-label'>Validar ICP</label>
                                <select id='termoCelularDiretoIcp' class='form-control input-sm'>
                                    <option value='nao'>Não</option>
                                    <option value='sim'>Sim</option>
                                </select>
                            </div>
                            <div class='col-sm-4'>
                                <label class='control-label'>Enviar e-mail</label>
                                <select id='termoCelularDiretoEmail' class='form-control input-sm'>
                                    <option value='sim'>Sim</option>
                                    <option value='nao'>Não</option>
                                </select>
                            </div>
                            <div class='col-sm-4'>
                                <label class='control-label'>Forçar regeração</label>
                                <select id='termoCelularDiretoForcar' class='form-control input-sm'>
                                    <option value='nao'>Não</option>
                                    <option value='sim'>Sim</option>
                                </select>
                            </div>
                        </div>
                        <div class='row' style='margin-top:6px;'>
                            <div class='col-sm-4'>
                                <label class='control-label'>Prazo (dias)</label>
                                <input type='number' id='termoCelularDiretoPrazo' class='form-control input-sm' min='0' max='30' value='1'>
                            </div>
                            <div class='col-sm-4'>
                                <label class='control-label'>Assinatura por hierarquia</label>
                                <select id='termoCelularDiretoHier' class='form-control input-sm'>
                                    <option value='nao'>Não</option>
                                    <option value='sim'>Sim</option>
                                </select>
                            </div>
                        </div>
                        <div id='termoCelularDiretoProgresso' style='display:none; margin-top:12px;'>
                            <div class='progress' style='margin-bottom:4px;'>
                                <div id='termoCelularDiretoBarra' class='progress-bar progress-bar-success' style='width:0%'></div>
                            </div>
                            <div id='termoCelularDiretoTexto' class='text-muted'></div>
                            <div id='termoCelularDiretoResultado' style='margin-top:8px; max-height:200px; overflow:auto; font-size:12px;'></div>
                        </div>
                    </div>
                    <div class='modal-footer'>
                        <button type='button' class='btn btn-default' data-dismiss='modal'>Fechar</button>
                        <button type='button' id='termoCelularDiretoBtn' class='btn btn-success' onclick='termoCelularEnviarDiretoConfirmar()'>Enviar</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            var TERMOCEL_DIRETO_ID = 0;
            function termoCelularAbrirEnvioDireto(id) {
                if (!id) { return; }
                TERMOCEL_DIRETO_ID = id;
                $('#termoCelularDiretoBtn').prop('disabled', false);
                $('#termoCelularDiretoInfo').html('<i class=\"fa fa-mobile\"></i> Aparelho #' + id + ' — carregando...');
                $('#termoCelularDiretoProgresso').hide();
                $('#termoCelularDiretoResultado').html('').hide();
                $('#termoCelularDiretoBarra').css('width', '0%');
                $('#modal_enviar_termo_direto_celular').modal('show');
                $.ajax({
                    url: 'cadastro_celular.php',
                    method: 'POST',
                    data: { acao: 'termosVinculadosCelular', id: id },
                    dataType: 'json'
                }).done(function(resTermos) {
                    var opts = '';
                    if (resTermos && resTermos.ok && resTermos.modelos && resTermos.modelos.length) {
                        $.each(resTermos.modelos, function(i, m) {
                            if (m.vinculado) {
                                opts += '<option value=\"' + m.id + '\">' + termoCelularEscape(m.nome) + '</option>';
                            }
                        });
                    }
                    if (opts === '') {
                        opts = '<option value=\"\">Nenhum termo vinculado</option>';
                    }
                    $('#termoCelularDiretoModelo').html(opts);
                });
                $.ajax({
                    url: 'cadastro_celular.php',
                    method: 'POST',
                    data: { acao: 'dadosEnvioTermo', id: id },
                    dataType: 'json'
                }).done(function(res) {
                    if (!res || !res.ok) {
                        $('#termoCelularDiretoInfo').html('<i class=\"fa fa-mobile\"></i> Aparelho #' + id + ' <br><span class=\"text-danger\">' + termoCelularEscape((res && res.error) ? res.error : 'Não foi possível carregar os dados.') + '</span>');
                        $('#termoCelularDiretoBtn').prop('disabled', true);
                        return;
                    }
                    var respNome = (res.responsavel && res.responsavel.nome) ? res.responsavel.nome : '';
                    var html = '<i class=\"fa fa-mobile\"></i> Aparelho #' + id + ' — enviando para <b>' + termoCelularEscape(respNome ? respNome : 'o responsável cadastrado') + '</b>, com os dados deste aparelho.';
                    $('#termoCelularDiretoInfo').html(html);
                    $('#termoCelularDiretoBtn').prop('disabled', false);
                }).fail(function(xhr) {
                    var msg = 'Não foi possível carregar os dados.';
                    if (xhr && xhr.responseJSON && xhr.responseJSON.error) { msg = xhr.responseJSON.error; }
                    $('#termoCelularDiretoInfo').html('<i class=\"fa fa-mobile\"></i> Aparelho #' + id + ' <br><span class=\"text-danger\">' + termoCelularEscape(msg) + '</span>');
                    $('#termoCelularDiretoBtn').prop('disabled', true);
                });
            }
            function termoCelularEnviarDiretoConfirmar() {
                if (TERMOCEL_DIRETO_ID <= 0) { alert('Aparelho inválido.'); return; }
                $('#termoCelularDiretoBtn').prop('disabled', true);
                $('#termoCelularDiretoProgresso').show();
                $('#termoCelularDiretoResultado').html('').hide();
                var params = {
                    aparelhos: [TERMOCEL_DIRETO_ID],
                    modelo_id: parseInt($('#termoCelularDiretoModelo').val(), 10) || 0,
                    validar_icp: $('#termoCelularDiretoIcp').val(),
                    enviar_email: $('#termoCelularDiretoEmail').val(),
                    forcar: $('#termoCelularDiretoForcar').val(),
                    prazo_expiracao_dias: parseInt($('#termoCelularDiretoPrazo').val(), 10) || 0,
                    hierarquia: $('#termoCelularDiretoHier').val()
                };
                $.ajax({
                    url: 'documentos/termos/processar_termos_aparelhos.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(params),
                    dataType: 'json'
                }).done(function(res) {
                    var okC = 0, errC = 0, resumo = [];
                    if (res && Array.isArray(res.resultados)) {
                        $.each(res.resultados, function(i, r) {
                            if (r.ok) { okC++; } else { errC++; }
                            resumo.push((r.ok ? 'OK' : 'ERRO') + ' - ' + (r.msg || ''));
                        });
                    } else {
                        errC++; resumo.push('ERRO - ' + ((res && res.error) ? res.error : 'resposta inválida.'));
                    }
                    $('#termoCelularDiretoBarra').css('width', '100%');
                    $('#termoCelularDiretoTexto').text('Concluído.');
                    $('#termoCelularDiretoResultado').html('<b>Resumo: ' + okC + ' OK | ' + errC + ' erro(s)</b><br>' + resumo.join('<br>')).show();
                    $('#termoCelularDiretoBtn').prop('disabled', false);
                }).fail(function(xhr) {
                    var detalhe = 'falha de comunicação.';
                    if (xhr) {
                        if (xhr.responseJSON && xhr.responseJSON.error) { detalhe = xhr.responseJSON.error; }
                        else if (xhr.responseText) { detalhe = xhr.responseText.replace(/<[^>]*>/g, '').trim().substring(0, 200); }
                        else if (xhr.status) { detalhe = 'HTTP ' + xhr.status + ' ' + (xhr.statusText || ''); }
                    }
                    $('#termoCelularDiretoResultado').html('ERRO - ' + detalhe).show();
                    $('#termoCelularDiretoBtn').prop('disabled', false);
                });
            }
        </script>
        ";
    }

    function index(){
        garantirEstruturaTermosCelular();
        echo "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css'>";
        echo "<style>
            #acessorios label, #aplicativos label { margin-right: 14px; margin-bottom: 6px; }
            #acessorios > div:first-child, #aplicativos > div:first-child { font-weight: 600; margin-bottom: 6px; }
        </style>";
        cabecalho("Cadastro de Celulares");
        formCelular();
        if(empty($_POST["id"])){
            // Sincroniza status de assinatura dos termos dos aparelhos listados.
            if(function_exists("termos_sincronizar_registro")){
                $resSync = query("SELECT * FROM termo_gerado WHERE terg_nb_ativo IS NOT NULL AND terg_tx_status IN ('aguardando_assinatura','notificado','visualizado','gerado')");
                if($resSync instanceof mysqli_result){
                    while($rs = mysqli_fetch_assoc($resSync)){
                        termos_sincronizar_registro($rs);
                    }
                }
            }
            listarCelulares();
            modalVincularTermoCelular();
            modalEnviarTermoCelular();
            modalLoteTermosCelular();
            modalHistoricoCelular();
            modalStatusCelular();
            modalEnviarTermoDiretoCelular();
            echo "<script>function limparFiltrosCelular(){ if(!window.\$) return; $('form[name=\"contex_form\"] :input').each(function(){ if(this.type!=='hidden' && this.name!=='acao' && this.name!=='id'){ $(this).val(''); } }); if(typeof consultarRegistros==='function'){ consultarRegistros(); } }</script>";
        }
        rodape();
    }

    // --- FALLBACK ---
    // Se não houver ação, carrega a tela inicial
    if(empty($_POST['acao'])){
        index();
    }
?>