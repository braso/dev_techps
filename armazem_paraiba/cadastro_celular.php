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

        $atual = intval($celular["celu_nb_modelo_termo"] ?? 0);
        if($atual > 0 && !isset($idsValidos[$atual])){
            $atual = 0;
        }

        celular_json_resposta(200, [
            "ok" => true,
            "equipamento" => [
                "id" => intval($celular["celu_nb_id"]),
                "nome" => strval($celular["celu_tx_nome"] ?? ""),
                "imei" => strval($celular["celu_tx_imei"] ?? "")
            ],
            "atual" => $atual,
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
            "SELECT c.celu_nb_id, c.celu_tx_nome, c.celu_tx_imei, c.celu_nb_modelo_termo, c.celu_nb_entidade,
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
                $_POST["operadora"]          = $_POST["operadora"] ?? $celular["celu_tx_operadora"];
                $_POST["cimie"]              = $_POST["cimie"] ?? $celular["celu_tx_cimie"];
                $_POST["sistemaOperacional"] = $_POST["sistemaOperacional"] ?? $celular["celu_tx_sistemaOperacional"];
                $_POST["acessoriosOutros"]   = $_POST["acessoriosOutros"] ?? ($celular["celu_tx_acessoriosOutros"] ?? "");
                $_POST["aplicativosOutros"]  = $_POST["aplicativosOutros"] ?? ($celular["celu_tx_aplicativosOutros"] ?? "");
                $_POST["estadoConservacao"]  = $_POST["estadoConservacao"] ?? ($celular["celu_tx_estadoConservacao"] ?? "");
                $_POST["observacoes"]        = $_POST["observacoes"] ?? ($celular["celu_tx_observacoes"] ?? "");
                $_POST["valorEstimado"]      = $_POST["valorEstimado"] ?? ($celular["celu_tx_valorEstimado"] ?? "");
                $_POST["dataEntrega"]        = $_POST["dataEntrega"] ?? ($celular["celu_tx_dataEntrega"] ?? "");
                $_POST["entidade"]           = $_POST["entidade"] ?? $celular["celu_nb_entidade"];
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
            campo("IMEI 1", "imei", (!empty($_POST["imei"])? $_POST["imei"]: ""), 2, "", "required"),
            campo("IMEI 2", "imei2", (!empty($_POST["imei2"])? $_POST["imei2"]: ""), 2),
            campo("Número de série", "numeroSerie_like", (!empty($_POST["numeroSerie_like"])? $_POST["numeroSerie_like"]: ""), 3),
            campo("Número da linha (chip)", "numero", (!empty($_POST["numero"])? $_POST["numero"]: ""), 3, "", "required"),
            campo("Operadora", "operadora", (!empty($_POST["operadora"])? $_POST["operadora"]: ""), 2)
        ]);
        echo linha_form([
            campo("ICCID do chip", "cimie", (!empty($_POST["cimie"])? $_POST["cimie"]: ""), 2),
            campo("Sistema Operacional", "sistemaOperacional", (!empty($_POST["sistemaOperacional"])? $_POST["sistemaOperacional"]: ""), 3),
            campo("Valor estimado do bem", "valorEstimado", (!empty($_POST["valorEstimado"])? $_POST["valorEstimado"]: ""), 2, "MASCARA_DINHEIRO"),
            campo_data("Data da entrega", "dataEntrega", (!empty($_POST["dataEntrega"])? $_POST["dataEntrega"]: ""), 2),
            combo_net("Responsável", "entidade", (!empty($_POST["entidade"])? $_POST["entidade"]: ""), 3, "entidade")
        ]);
        echo linha_form([
            checkbox("Acessórios entregues", "acessorios", opcoesAcessorios(), 6, "checkbox", "", $acessoriosMarcados),
            checkbox("Aplicativos instalados", "aplicativos", opcoesAplicativos(), 6, "checkbox", "", $aplicativosMarcados)
        ]);
        echo linha_form([
            campo("Outros acessórios (especifique)", "acessoriosOutros", (!empty($_POST["acessoriosOutros"])? $_POST["acessoriosOutros"]: ""), 3),
            campo("Outros aplicativos (especifique)", "aplicativosOutros", (!empty($_POST["aplicativosOutros"])? $_POST["aplicativosOutros"]: ""), 3),
            combo_radio("Estado de conservação na entrega", "estadoConservacao", (!empty($_POST["estadoConservacao"])? $_POST["estadoConservacao"]: ""), 6, opcoesEstadoConservacao())
        ]);
        echo linha_form([
            textarea("Observações", "observacoes", (!empty($_POST["observacoes"])? $_POST["observacoes"]: ""), 12)
        ]);
        
        $botoes = !empty($_POST["id"])?
            [
                botao("Atualizar", "cadastrarCelular", "id", $_POST["id"], "", "", "btn btn-success"),
                criarBotaoVoltar("cadastro_celular.php", "voltarCelular")
            ]:
            [botao("Cadastrar", "cadastrarCelular", "", "", "", "", "btn btn-success")];
            
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
            "OPERADORA"         => "celu_tx_operadora",
            "ICCID"             => "celu_tx_cimie",
            "S.O."              => "celu_tx_sistemaOperacional",
            "ESTADO"            => "celu_tx_estadoConservacao",
            "VALOR ESTIMADO"    => "celu_tx_valorEstimado",
            "ENTREGA"           => "DATE_FORMAT(celu_dt_dataEntrega, '%d/%m/%Y')",
            "RESPONSÁVEL"       => "enti_tx_nome",
            "CADASTRADO EM"     => "DATE_FORMAT(celu_tx_dataCadastro, '%d/%m/%Y %H:%i:%s')",
            "ATUALIZADO EM"     => "DATE_FORMAT(celu_tx_dataAtualiza, '%d/%m/%Y %H:%i:%s')",
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
                '<span class=\"termo-link-celular\" style=\"cursor:pointer;white-space:nowrap;\" data-id=\"', celu_nb_id, '\" data-nome=\"', REPLACE(IFNULL(celu_tx_nome, ''), '\"', '&quot;'), '\" title=\"Clique para vincular/alterar o termo deste equipamento\">',
                IF(celu_nb_modelo_termo IS NULL OR celu_nb_modelo_termo = 0 OR tm.mode_nb_id IS NULL,
                    '<span class=\"text-muted\"><i class=\"fa fa-link\"></i> Vincular termo</span>',
                    CONCAT('<i class=\"fa fa-file-text-o\"></i> ', REPLACE(REPLACE(REPLACE(IFNULL(tm.mode_tx_nome, ''), '&', '&amp;'), '<', '&lt;'), '\"', '&quot;'), ' <i class=\"fa fa-pencil\" style=\"font-size:10px;color:#999;\"></i>')
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
        $gridFields["actions"] = $configuracao["tags"];
        $jsFunctions = $configuracao["js"] . "
            $(document).off('click.termoCelularVincular', '.termo-link-celular').on('click.termoCelularVincular', '.termo-link-celular', function(event){
                event.preventDefault();
                termoCelularAbrirVinculo(parseInt($(this).attr('data-id'), 10) || 0);
            });
            $(document).off('click.termoCelularEnviar', '.termo-celular-enviar').on('click.termoCelularEnviar', '.termo-celular-enviar', function(event){
                event.preventDefault();
                var id = parseInt($(event.target).closest('tr').attr('data-row-id'), 10) || 0;
                termoCelularAbrirEnvio(id);
            });
        ";

        echo gridDinamico("celular", $gridFields, $camposBusca, $queryBase, $jsFunctions);
    };

    function cadastrarCelular(){
        $fields = [
            "nome_like", "tipo", "marca_like", "modelo_like", "imei", "imei2", "numeroSerie_like", "numero",
            "operadora", "cimie", "sistemaOperacional", "acessoriosOutros", "aplicativosOutros",
            "estadoConservacao", "observacoes", "valorEstimado", "dataEntrega"
        ];
        foreach($fields as $field){
            $_POST[$field] = isset($_POST[$field]) ? trim($_POST[$field]) : '';
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
            "celu_tx_dataAtualiza" => date("Y-m-d H:i:s")
        ];

        if(!empty($_POST["id"])){
            atualizar("celular", array_keys($novoCelular), array_values($novoCelular), $_POST["id"]);
            set_status("<script>Swal.fire('Sucesso!', 'Celular atualizado com sucesso.', 'success');</script>");
        } else {
            $novoCelular["celu_tx_dataCadastro"] = date("Y-m-d H:i:s");
            $retorno = inserir("celular", array_keys($novoCelular), array_values($novoCelular));
            if(is_array($retorno) && isset($retorno[0]) && is_object($retorno[0])){
                set_status("<script>Swal.fire('Erro!', 'Não foi possível cadastrar o celular: " . addslashes($retorno[0]->getMessage()) . "', 'error');</script>");
                index();
                exit;
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
                        <h4 class='modal-title'>Vincular Termo ao Equipamento</h4>
                    </div>
                    <div class='modal-body'>
                        <div class='alert alert-info' style='padding:8px 12px; font-size:12px;'>
                            <i class='fa fa-link'></i> Selecione o termo usado por este equipamento. Na geração do documento, os campos do equipamento (ex.: <code>{{equipamento_imei}}</code>) são preenchidos com os dados deste aparelho.
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
                        <button type='button' class='btn btn-danger pull-left' id='termocel_vinc_btn_remover' onclick='termoCelularRemoverVinculo()' style='display:none;'>Remover vínculo</button>
                        <button type='button' class='btn btn-default' data-dismiss='modal'>Fechar</button>
                        <button type='button' class='btn btn-success' onclick='termoCelularSalvarVinculo()'>Salvar vínculo</button>
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
                        $.each(res.modelos, function(i, m) {
                            var checked = (parseInt(m.id, 10) === parseInt(res.atual, 10)) ? 'checked' : '';
                            html += '<label style=\"font-weight:normal; display:block; margin:2px 0; overflow-wrap:break-word;\">'
                                + '<input type=\"radio\" name=\"termocel_modelo\" value=\"' + m.id + '\" ' + checked + '> '
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
                var modelo = parseInt($('input[name=termocel_modelo]:checked').val(), 10) || 0;
                if (modelo <= 0) {
                    if (window.Swal) { Swal.fire('Atenção!', 'Selecione o termo que será vinculado ao equipamento.', 'warning'); }
                    else { alert('Selecione o termo que será vinculado ao equipamento.'); }
                    return;
                }
                $.ajax({
                    url: 'cadastro_celular.php',
                    method: 'POST',
                    data: { acao: 'salvarVinculoTermo', id: TERMOCEL_VINC_ID, modelo: modelo },
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
                        data: { acao: 'removerVinculoTermo', id: TERMOCEL_VINC_ID },
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
            listarCelulares();
            modalVincularTermoCelular();
            modalEnviarTermoCelular();
        }
        rodape();
    }

    // --- FALLBACK ---
    // Se não houver ação, carrega a tela inicial
    if(empty($_POST['acao'])){
        index();
    }
?>