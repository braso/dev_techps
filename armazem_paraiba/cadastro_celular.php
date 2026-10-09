<?php
    // ini_set("display_errors", 1);
    // error_reporting(E_ALL);

    // header("Expires: 01 Jan 2001 00:00:00 GMT");
    // header("Cache-Control: no-cache, no-store, must-revalidate");
    // header("Cache-Control: post-check=0, pre-check=0", FALSE);

    include_once "utils/utils.php";
    include_once "utils/celular_opcoes.php";
    include_once "load_env.php";
    include_once "conecta.php";
    mysqli_query($conn, "SET time_zone = '-3:00'");

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

        $queryBase = "SELECT ".implode(", ", array_values($gridFields))." FROM celular
        LEFT JOIN entidade ON celu_nb_entidade = enti_nb_id";

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
        $gridFields["actions"] = $configuracao["tags"];
        $jsFunctions = $configuracao["js"];

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

    function index(){
        echo "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css'>";
        echo "<style>
            #acessorios label, #aplicativos label { margin-right: 14px; margin-bottom: 6px; }
            #acessorios > div:first-child, #aplicativos > div:first-child { font-weight: 600; margin-bottom: 6px; }
        </style>";
        cabecalho("Cadastro de Celulares");
        formCelular();
        if(empty($_POST["id"])){
            listarCelulares();
        }
        rodape();
    }

    // --- FALLBACK ---
    // Se não houver ação, carrega a tela inicial
    if(empty($_POST['acao'])){
        index();
    }
?>