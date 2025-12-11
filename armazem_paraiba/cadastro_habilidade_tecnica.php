<?php



		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");
	//*/


    include_once "load_env.php";
    include_once "conecta.php";
    mysqli_query($conn, "SET time_zone = '-3:00'");

    function cadastrar(){
        $camposObrig = [
            "habilidade" => "Habilidade Técnica",
            "importancia" => "Importância"
        ];
        $errorMsg = conferirCamposObrig($camposObrig, $_POST);
        if(!empty($errorMsg)){
            set_status("ERRO: ".$errorMsg);
            index();
            exit;
        }

        $_POST["habilidade"] = trim($_POST["habilidade"]);
        $_POST["descricao"] = isset($_POST["descricao"]) ? trim($_POST["descricao"]) : null;
        $importancia = in_array($_POST["importancia"], ["alta", "media", "baixa"]) ? $_POST["importancia"] : "media";

        $habilidadeExistente = mysqli_fetch_assoc(query(
            (!empty($_POST["id"]) ?
                "SELECT habi_tx_nome FROM habilidade_tecnica WHERE habi_tx_nome = ? AND habi_nb_id != ?;" :
                "SELECT habi_tx_nome FROM habilidade_tecnica WHERE habi_tx_nome = ?;"
            ),
            (!empty($_POST["id"]) ? "si" : "s"),
            (!empty($_POST["id"]) ? [$_POST["habilidade"], (int)$_POST["id"]] : [$_POST["habilidade"]])
        ));
        if(!empty($habilidadeExistente)){
            set_status("ERRO: Esta habilidade técnica já está cadastrada!");
            index();
            exit;
        }

        $novaHab = [
            "habi_tx_nome" => $_POST["habilidade"],
            "habi_tx_importancia" => $importancia,
            "habi_tx_descricao" => $_POST["descricao"] ?? null,
            "habi_tx_dataAtualiza" => date("Y-m-d H:i:s")
        ];

        if(!empty($_POST["id"])){
            atualizar("habilidade_tecnica", array_keys($novaHab), array_values($novaHab), $_POST["id"]);
            set_status("<script>Swal.fire('Sucesso!', 'Habilidade técnica atualizada com sucesso.', 'success');</script>");
        }else{
            $novaHab["habi_tx_dataCadastro"] = date("Y-m-d H:i:s");
            inserir("habilidade_tecnica", array_keys($novaHab), array_values($novaHab));
            set_status("<script>Swal.fire('Sucesso!', 'Habilidade técnica inserida com sucesso.', 'success');</script>");
        }

        index();
        exit;
    }

    function editarHabilidade(){
        index();
        exit;
    }

    function excluirHabilidade(){
        query("DELETE FROM habilidade_tecnica WHERE habi_nb_id = {$_POST["id"]};");
        set_status("<script>Swal.fire('Sucesso!', 'Habilidade técnica excluída com sucesso.', 'info');</script>");
        unset($_POST["id"]);
        index();
        exit;
    }

    function voltarHabilidade(){
        unset($_POST);
        index();
        exit;
    }

    function formHabilidade(){
        if(!empty($_POST["id"])){
            $habilidade = mysqli_fetch_assoc(query(
                "SELECT * FROM habilidade_tecnica WHERE habi_nb_id = ?;", "i", [$_POST["id"]]
            ));
        }

        $campos = [
            campo_hidden("id", (!empty($_POST["id"]) ? $_POST["id"] : "")),
            campo("Habilidade Técnica*", "habilidade", !empty($habilidade["habi_tx_nome"]) ? $habilidade["habi_tx_nome"] : "", 6, "", "required"),
            combo("Importância*", "importancia", !empty($habilidade["habi_tx_importancia"]) ? $habilidade["habi_tx_importancia"] : "media", 3, ["alta" => "Alta", "media" => "Média", "baixa" => "Baixa"], "required"),
            textarea("Descrição", "descricao", !empty($habilidade["habi_tx_descricao"]) ? $habilidade["habi_tx_descricao"] : "", 12)
        ];

      $instrucoes = 
"<div class='col-md-12' style='margin-top:50px'>
    <div class='portlet light' style='padding:10px;'>

        <div style='font-weight:bold; margin-bottom:6px; font-size:16px'>
            O que são Habilidades Técnicas (Hard Skills)
        </div>

        <div style='margin-bottom:15px; line-height:1.5'>
            São conhecimentos e capacidades mensuráveis que um colaborador utiliza para realizar 
            tarefas específicas de sua função. Normalmente podem ser aprendidas por meio de 
            cursos, treinamentos, certificações e experiência prática.
            <br><br>
            Exemplos:
            <ul style='margin: 5px 0 0 18px;'>
                <li>Domínio de softwares e sistemas internos</li>
                <li>Operação de máquinas ou processos técnicos</li>
                <li>Programação, análise de dados, manutenção, logística etc.</li>
                <li>Métodos e normas aplicadas ao setor</li>
            </ul>
            A definição correta dessas habilidades ajuda a empresa a treinar, selecionar e avaliar melhor seus colaboradores.
        </div>

        <hr>

        <div style='font-weight:bold; margin-bottom:6px; font-size:16px'>
            Como classificar a importância
        </div>

        <ul style='list-style:none; padding-left:0; margin:0;'>

            <li style='margin-bottom:6px;'>
                <span style='color:#d9534f; margin-right:6px;'>
                    <i class='fa fa-circle'></i>
                </span>
                <span>
                    <b>Alta</b>: essencial para o setor/funcionário. Define um perfil crítico. 
                    A falta dessa habilidade compromete o funcionamento ou gera risco.
                </span>
            </li>

            <li style='margin-bottom:6px;'>
                <span style='color:#f0ad4e; margin-right:6px;'>
                    <i class='fa fa-circle'></i>
                </span>
                <span>
                    <b>Média</b>: importante e desejável, mas não interrompe as operações caso ausente. 
                    Contribui fortemente para desempenho e redução de erros.
                </span>
            </li>

            <li>
                <span style='color:#5cb85c; margin-right:6px;'>
                    <i class='fa fa-circle'></i>
                </span>
                <span>
                    <b>Baixa</b>: complementar. Melhora eficiência, versatilidade e qualidade, 
                    mas não determina o desempenho principal do cargo.
                </span>
            </li>

        </ul>
    </div>
</div>";


        $botoes =
            empty($_POST["id"]) ?
                [botao("Cadastrar", "cadastrar", "cadastrar_habilidade", "", "class='btn btn-success'")] :
                [
                    botao("Atualizar", "cadastrar", "atualizar_habilidade", "", "class='btn btn-success'"),
                    criarBotaoVoltar("cadastro_habilidade_tecnica.php", "voltarHabilidade")
                ]
        ;

        echo abre_form();
        echo linha_form($campos);
        echo fecha_form($botoes, $instrucoes);
    }

    function listarHabilidades() {
        $gridFields = [
            "ID" => "habi_nb_id",
            "HABILIDADE TÉCNICA" => "habi_tx_nome",
            "IMPORTÂNCIA" => "CASE habi_tx_importancia WHEN 'alta' THEN 'Alta' WHEN 'media' THEN 'Média' ELSE 'Baixa' END AS habi_tx_importancia",
            "DESCRIÇÃO" => "habi_tx_descricao",
            "DATA DE CADASTRO" => "habi_tx_dataCadastro",
            "DATA DE ALTERAÇÃO" => "habi_tx_dataAtualiza"
        ];

        $camposBusca = [
            "busca_nome_like" => "habi_tx_nome"
        ];

        $queryBase =
            "SELECT ".implode(", ", array_values($gridFields))." FROM habilidade_tecnica"
        ;

        

        $actions = criarIconesGrid(
            ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
            ["cadastro_habilidade_tecnica.php", "cadastro_habilidade_tecnica.php"],
            ["editarHabilidade()", "excluirHabilidade()"]
        );

        $gridFields["actions"] = $actions["tags"];

        $jsFunctions =
            "const funcoesInternas = function(){"
            .implode(" ", $actions["functions"]).
            "}"
        ;

        echo gridDinamico("habilidade_tecnica", $gridFields, $camposBusca, $queryBase, $jsFunctions);
    }

    function index(){
        //ARQUIVO QUE VALIDA A PERMISSAO VIA PERFIL DE USUARIO VINCULADO
        include "check_permission.php";
        // APATH QUE O USER ESTA TENTANDO ACESSAR PARA VERIFICAR NO PERFIL SE TEM ACESSO2
        verificaPermissao('/cadastro_habilidade_tecnica.php');

        cabecalho("Cadastro de Habilidades Técnicas");
        formHabilidade();
        if(empty($_POST["id"])){
            listarHabilidades();
        }
        


        rodape();
    }
?>

