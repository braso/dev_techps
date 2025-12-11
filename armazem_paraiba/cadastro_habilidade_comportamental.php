<?php



		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Cache-Control: no-cache, no-store, must-revalidate");
		header("Pragma: no-cache");
		header("Expires: 0");
	//*/


    include_once "load_env.php";
    include_once "conecta.php";
    mysqli_query($conn, "SET time_zone = '-3:00'");

    function cadastrar(){
        $camposObrig = [
            "habilidade" => "Habilidade Comportamental",
            "importancia" => "Importância"
        ];
        $errorMsg = conferirCamposObrig($camposObrig, $_POST);
        if(!empty($errorMsg)){
            set_status("ERRO: ".$errorMsg);
            index();
            exit;
        }

        $_POST["habilidade"] = trim($_POST["habilidade"]);
        $importancia = in_array($_POST["importancia"], ["alta", "media", "baixa"]) ? $_POST["importancia"] : "media";

        $habilidadeExistente = mysqli_fetch_assoc(query(
            (!empty($_POST["id"]) ?
                "SELECT habi_tx_nome FROM habilidade_comportamental WHERE habi_tx_nome = ? AND habi_nb_id != ?;" :
                "SELECT habi_tx_nome FROM habilidade_comportamental WHERE habi_tx_nome = ?;"
            ),
            (!empty($_POST["id"]) ? "si" : "s"),
            (!empty($_POST["id"]) ? [$_POST["habilidade"], (int)$_POST["id"]] : [$_POST["habilidade"]])
        ));
        if(!empty($habilidadeExistente)){
            set_status("ERRO: Esta habilidade comportamental já está cadastrada!");
            index();
            exit;
        }

        $novaHab = [
            "habi_tx_nome" => $_POST["habilidade"],
            "habi_tx_importancia" => $importancia,
            "habi_tx_dataAtualiza" => date("Y-m-d H:i:s")
        ];

        if(!empty($_POST["id"])){
            atualizar("habilidade_comportamental", array_keys($novaHab), array_values($novaHab), $_POST["id"]);
            set_status("<script>Swal.fire('Sucesso!', 'Habilidade comportamental atualizada com sucesso.', 'success');</script>");
        }else{
            $novaHab["habi_tx_dataCadastro"] = date("Y-m-d H:i:s");
            inserir("habilidade_comportamental", array_keys($novaHab), array_values($novaHab));
            set_status("<script>Swal.fire('Sucesso!', 'Habilidade comportamental inserida com sucesso.', 'success');</script>");
        }

        index();
        exit;
    }

    function editarHabilidade(){
        index();
        exit;
    }

    function excluirHabilidade(){
        query("DELETE FROM habilidade_comportamental WHERE habi_nb_id = {$_POST["id"]};");
        set_status("<script>Swal.fire('Sucesso!', 'Habilidade comportamental excluída com sucesso.', 'info');</script>");
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
                "SELECT * FROM habilidade_comportamental WHERE habi_nb_id = ?;", "i", [$_POST["id"]]
            ));
        }

        $campos = [
            campo_hidden("id", (!empty($_POST["id"]) ? $_POST["id"] : "")),
            campo("Habilidade Comportamental*", "habilidade", !empty($habilidade["habi_tx_nome"]) ? $habilidade["habi_tx_nome"] : "", 6, "", "required"),
            combo("Importância*", "importancia", !empty($habilidade["habi_tx_importancia"]) ? $habilidade["habi_tx_importancia"] : "media", 3, ["alta" => "Alta", "media" => "Média", "baixa" => "Baixa"], "required")
        ];

       $instrucoes =
"<div class='col-md-12' style='margin-top:50px'>
    <div class='portlet light' style='padding:10px;'>

        <div style='font-weight:bold; margin-bottom:6px; font-size:16px'>
            O que são Habilidades Comportamentais (Soft Skills)
        </div>

        <div style='margin-bottom:15px; line-height:1.5'>
            São características pessoais, atitudes e comportamentos que influenciam a forma como o colaborador 
            se relaciona, reage, comunica e executa suas atividades no ambiente de trabalho.
            <br><br>
            Exemplos:
            <ul style='margin: 5px 0 0 18px;'>
                <li>Comunicação clara e objetiva</li>
                <li>Trabalho em equipe e colaboração</li>
                <li>Proatividade e senso de responsabilidade</li>
                <li>Organização e gestão do tempo</li>
                <li>Resiliência e controle emocional</li>
                <li>Empatia e postura profissional</li>
            </ul>
            São fundamentais para o clima organizacional e influenciam diretamente a produtividade e 
            qualidade das entregas.
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
                    <b>Alta</b>: essencial para o cargo e setor. Sem esse comportamento, há impacto direto em 
                    segurança, atendimento, clima ou execução. Muito comum em funções de liderança,
                    atendimento ao público, cargos críticos ou ambientes que exigem precisão.
                </span>
            </li>

            <li style='margin-bottom:6px;'>
                <span style='color:#f0ad4e; margin-right:6px;'>
                    <i class='fa fa-circle'></i>
                </span>
                <span>
                    <b>Média</b>: é desejável e influencia o desempenho, mas não compromete o funcionamento
                    geral se estiver ausente. Indica maturidade e potencial de crescimento.
                </span>
            </li>

            <li>
                <span style='color:#5cb85c; margin-right:6px;'>
                    <i class='fa fa-circle'></i>
                </span>
                <span>
                    <b>Baixa</b>: diferencia o colaborador e agrega valor, mas não é determinante para a função.
                    Pode ser desenvolvida ao longo do tempo sem impacto imediato nas operações.
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
                    criarBotaoVoltar("cadastro_habilidade_comportamental.php", "voltarHabilidade")
                ]
        ;

        echo abre_form();
        echo linha_form($campos);
        echo fecha_form($botoes, $instrucoes);
    }

    function listarHabilidades() {
        $gridFields = [
            "ID" => "habi_nb_id",
            "HABILIDADE COMPORTAMENTAL" => "habi_tx_nome",
            "IMPORTÂNCIA" => "CASE habi_tx_importancia WHEN 'alta' THEN 'Alta' WHEN 'media' THEN 'Média' ELSE 'Baixa' END AS habi_tx_importancia",
            "DATA DE CADASTRO" => "habi_tx_dataCadastro",
            "DATA DE ALTERAÇÃO" => "habi_tx_dataAtualiza"
        ];

        $camposBusca = [
            "busca_nome_like" => "habi_tx_nome"
        ];

        $queryBase =
            "SELECT ".implode(", ", array_values($gridFields))." FROM habilidade_comportamental"
        ;

        

        $actions = criarIconesGrid(
            ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
            ["cadastro_habilidade_comportamental.php", "cadastro_habilidade_comportamental.php"],
            ["editarHabilidade()", "excluirHabilidade()"]
        );

        $gridFields["actions"] = $actions["tags"];

        $jsFunctions =
            "const funcoesInternas = function(){"
            .implode(" ", $actions["functions"]).
            "}"
        ;

        echo gridDinamico("habilidade_comportamental", $gridFields, $camposBusca, $queryBase, $jsFunctions);
    }

    function index(){
        //ARQUIVO QUE VALIDA A PERMISSAO VIA PERFIL DE USUARIO VINCULADO
        include "check_permission.php";
        // APATH QUE O USER ESTA TENTANDO ACESSAR PARA VERIFICAR NO PERFIL SE TEM ACESSO2
        verificaPermissao('/cadastro_habilidade_comportamental.php');

        cabecalho("Cadastro de Habilidades Comportamentais");
        formHabilidade();
        if(empty($_POST["id"])){
            listarHabilidades();
        }

        

        rodape();
    }
?>
