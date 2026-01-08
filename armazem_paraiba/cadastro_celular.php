<?php
    // ini_set("display_errors", 1);
    // error_reporting(E_ALL);

    // header("Expires: 01 Jan 2001 00:00:00 GMT");
    // header("Cache-Control: no-cache, no-store, must-revalidate");
    // header("Cache-Control: post-check=0, pre-check=0", FALSE);

    include_once "load_env.php";
    include_once "conecta.php";
    mysqli_query($conn, "SET time_zone = '-3:00'");

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
        if(!empty($_POST["id"])){
            $celular = mysqli_fetch_assoc(query(
                "SELECT * FROM celular WHERE celu_nb_id = " . (int)$_POST["id"]
            ));
            // Preenche os POSTs caso não existam
            if($celular) {
                $_POST["nome_like"]             = $_POST["nome_like"] ?? $celular["celu_tx_nome"];
                $_POST["imei"]                  = $_POST["imei"] ?? $celular["celu_tx_imei"];
                $_POST["numero"]                = $_POST["numero"] ?? $celular["celu_tx_numero"];
                $_POST["operadora"]             = $_POST["operadora"] ?? $celular["celu_tx_operadora"];
                $_POST["cimie"]                 = $_POST["cimie"] ?? $celular["celu_tx_cimie"];
                $_POST["sistemaOperacional"]    = $_POST["sistemaOperacional"] ?? $celular["celu_tx_sistemaOperacional"];
                $_POST["marcaModelo_like"]      = $_POST["marcaModelo_like"] ?? $celular["celu_tx_marcaModelo"];
                $_POST["entidade"]              = $_POST["entidade"] ?? $celular["celu_nb_entidade"];
            }
        }

        echo abre_form();
        echo linha_form([
            // --- CORREÇÃO IMPORTANTE: Campo 'acao' para o Cadastrar/Atualizar funcionar ---
            campo_hidden("acao", ""), 
            campo_hidden("id", (!empty($_POST["id"])? $_POST["id"]: "")),
            campo("Nome", "nome_like", (!empty($_POST["nome_like"])? $_POST["nome_like"]: ""), 4, "", "required"),
            campo("IMEI", "imei", (!empty($_POST["imei"])? $_POST["imei"]: ""), 2, "", "required"),
            campo("Número", "numero", (!empty($_POST["numero"])? $_POST["numero"]: ""), 2, "", "required"),
            campo("Operadora", "operadora", (!empty($_POST["operadora"])? $_POST["operadora"]: ""), 2),
            campo("CIMIE", "cimie", (!empty($_POST["cimie"])? $_POST["cimie"]: ""), 2),
            campo("Sistema Operacional", "sistemaOperacional", (!empty($_POST["sistemaOperacional"])? $_POST["sistemaOperacional"]: ""), 3),
            campo("Marca e Modelo", "marcaModelo_like", (!empty($_POST["marcaModelo_like"])? $_POST["marcaModelo_like"]: ""), 2),
            combo_net("Responsável", "entidade", (!empty($_POST["entidade"])? $_POST["entidade"]: ""), 3, "entidade")
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
            "IMEI"              => "celu_tx_imei",
            "NÚMERO"            => "celu_tx_numero",
            "OPERADORA"         => "celu_tx_operadora",
            "CIMIE"             => "celu_tx_cimie",
            "S.O."              => "celu_tx_sistemaOperacional",
            "MARCA/MODELO"      => "celu_tx_marcaModelo",
            "RESPONSÁVEL"       => "enti_tx_nome",
            "CADASTRADO EM"     => "DATE_FORMAT(celu_tx_dataCadastro, '%d/%m/%Y %H:%i:%s')",
            "ATUALIZADO EM"     => "DATE_FORMAT(celu_tx_dataAtualiza, '%d/%m/%Y %H:%i:%s')",
        ];

        $camposBusca = [
            "nome_like"          => "celu_tx_nome",
            "imei"               => "celu_tx_imei",
            "numero"             => "celu_tx_numero",
            "operadora"          => "celu_tx_operadora",
            "cimie"              => "celu_tx_cimie",
            "sistemaOperacional" => "celu_tx_sistemaOperacional",
            "marcaModelo_like"   => "celu_tx_marcaModelo",
        ];

        $queryBase = "SELECT ".implode(", ", array_values($gridFields))." FROM celular
            JOIN entidade ON celu_nb_entidade = enti_nb_id";

        $actions = criarIconesGrid(
            ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-button"], 
            ["cadastro_celular.php", "javascript:void(0)"],
            ["editarCelular()", "excluirCelular()"]
        );

        // 2. Anula o JS automático
        $actions["functions"][1] = ""; 

        // 3. Força o HTML da lixeira (mantendo o ícone 'remove' original)
        $actions["tags"][1] = '<span class="glyphicon glyphicon-remove search-button" onclick="confirmarExclusao(this)" title="Excluir" style="color:#d9534f; cursor:pointer;"></span>';

        $gridFields["actions"] = $actions["tags"];
        $jsDoEditar = $actions["functions"][0];

        // 4. JavaScript Ajustado
        $jsFunctions = '
            const funcoesInternas = function(){
                try {
                    ' . $jsDoEditar . '
                } catch(e) { console.error(e); }
            };

            window.confirmarExclusao = function(elemento){
                var linha = $(elemento).closest("tr");
                var id = linha.find("td:eq(0)").text(); 

                Swal.fire({
                    title: "Tem certeza?",
                    text: "Excluir celular código: " + id,
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#d33",    // Vermelho para Ação Perigosa
                    cancelButtonColor: "#6c757d",  // Cinza para Cancelar (Neutro)
                    confirmButtonText: "Sim, excluir!",
                    cancelButtonText: "Cancelar"
                }).then((result) => {
                    if (result.isConfirmed) {
                        enviarExclusao(id);
                    }
                });
            };

            window.enviarExclusao = function(id){
                var form = document.createElement("form");
                form.method = "POST";
                form.action = ""; 
                
                var fieldAcao = document.createElement("input");
                fieldAcao.type = "hidden";
                fieldAcao.name = "acao";
                fieldAcao.value = "excluirCelular";
                form.appendChild(fieldAcao);

                var fieldId = document.createElement("input");
                fieldId.type = "hidden";
                fieldId.name = "id";
                fieldId.value = id; 
                form.appendChild(fieldId);

                document.body.appendChild(form);
                form.submit();
            };
        ';

        echo gridDinamico("celular", $gridFields, $camposBusca, $queryBase, $jsFunctions);
    };

    function cadastrarCelular(){
        $fields = ["nome_like", "imei", "numero", "operadora", "cimie", "sistemaOperacional", "marcaModelo_like"];
        foreach($fields as $field){
            $_POST[$field] = isset($_POST[$field]) ? trim($_POST[$field]) : '';
        }

        $errorMsg = conferirCamposObrig(["nome_like" => "Nome", "imei" => "IMEI", "numero" => "Número"], $_POST);
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

        $novoCelular = [
            "celu_tx_nome" => $_POST["nome_like"],
            "celu_tx_imei" => $_POST["imei"],
            "celu_tx_numero" => $_POST["numero"],
            "celu_tx_operadora" => $_POST["operadora"],
            "celu_tx_cimie" => $_POST["cimie"],
            "celu_tx_sistemaOperacional" => $_POST["sistemaOperacional"],
            "celu_tx_marcaModelo" => $_POST["marcaModelo_like"],
            "celu_nb_entidade" => (!empty($_POST["entidade"]) ? (int)$_POST["entidade"] : null),
            "celu_tx_dataAtualiza" => date("Y-m-d H:i:s")
        ];

        if(!empty($_POST["id"])){
            atualizar("celular", array_keys($novoCelular), array_values($novoCelular), $_POST["id"]);
            set_status("<script>Swal.fire('Sucesso!', 'Celular atualizado com sucesso.', 'success');</script>");
        } else {
            $novoCelular["celu_tx_dataCadastro"] = date("Y-m-d H:i:s");
            inserir("celular", array_keys($novoCelular), array_values($novoCelular));
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