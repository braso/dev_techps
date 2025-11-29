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
            "IMPORTÂNCIA" => "CASE habi_tx_importancia WHEN 'alta' THEN CONCAT('<span style\\=\"color:#d9534f\"><i class\\=\'fa fa-circle\\'></i> Alta</span>') WHEN 'media' THEN CONCAT('<span style\\=\"color:#f0ad4e\"><i class\\=\'fa fa-circle\\'></i> Média</span>') ELSE CONCAT('<span style\\=\"color:#5cb85c\"><i class\\=\'fa fa-circle\\'></i> Baixa</span>') END AS habi_tx_importancia",
            "DATA DE CADASTRO" => "habi_tx_dataCadastro",
            "DATA DE ALTERAÇÃO" => "habi_tx_dataAtualiza"
        ];

        $camposBusca = [
            "busca_nome_like" => "habi_tx_nome"
        ];

        $queryBase =
            "SELECT ".implode(", ", array_values($gridFields))." FROM habilidade_comportamental"
        ;

        $total = mysqli_fetch_assoc(query("SELECT COUNT(*) AS qtd FROM habilidade_comportamental;"));
        $alta = mysqli_fetch_assoc(query("SELECT COUNT(*) AS qtd FROM habilidade_comportamental WHERE habi_tx_importancia = 'alta';"));
        $media = mysqli_fetch_assoc(query("SELECT COUNT(*) AS qtd FROM habilidade_comportamental WHERE habi_tx_importancia = 'media';"));
        $baixa = mysqli_fetch_assoc(query("SELECT COUNT(*) AS qtd FROM habilidade_comportamental WHERE habi_tx_importancia = 'baixa';"));

        $overview =
            "<div class='row' style='margin-top:50px; margin-bottom:10px;'>"
            ."<div class='col-sm-3'><div class='portlet light' style='padding:10px; border-left:4px solid #777'>"
                ."<div style='font-weight:bold'>Total</div><div>".intval($total["qtd"])."</div>"
            ."</div></div>"
            ."<div class='col-sm-3'><div class='portlet light' style='padding:10px; border-left:4px solid #d9534f'>"
                ."<div style='font-weight:bold'><span style='color:#d9534f'><i class='fa fa-circle'></i></span> Alta</div><div>".intval($alta["qtd"])."</div>"
            ."</div></div>"
            ."<div class='col-sm-3'><div class='portlet light' style='padding:10px; border-left:4px solid #f0ad4e'>"
                ."<div style='font-weight:bold'><span style='color:#f0ad4e'><i class='fa fa-circle'></i></span> Média</div><div>".intval($media["qtd"])."</div>"
            ."</div></div>"
            ."<div class='col-sm-3'><div class='portlet light' style='padding:10px; border-left:4px solid #5cb85c'>"
                ."<div style='font-weight:bold'><span style='color:#5cb85c'><i class='fa fa-circle'></i></span> Baixa</div><div>".intval($baixa["qtd"])."</div>"
            ."</div></div>"
            ."</div>";

        echo "<div style='height:50px'></div>";
        echo $overview;

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
        cabecalho("Cadastro de Habilidades Comportamentais");
        formHabilidade();
        if(empty($_POST["id"])){
            listarHabilidades();
        }

        echo "<button id='geminiChatBtn' 
style='position:fixed; display:block; right:20px; bottom:20px; z-index:9999;
border:none; border-radius:50%; width:56px; height:56px;
background:#4c6ef5; color:#fff; font-size:22px;
box-shadow:0 4px 12px rgba(0,0,0,.25); cursor:pointer'>
<i class='fa fa-comments'></i>
</button>";

        echo "<div id='geminiChatPanel' 
style='position:fixed; right:20px; bottom:90px; z-index:9999; 
width:380px; height:75vh; background:#fff; border:1px solid #ccc; 
border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.25); 
display:none; overflow:hidden'>

    <div style='padding:12px; background:#4c6ef5; color:white; 
        font-weight:bold; display:flex; justify-content:space-between; 
        align-items:center'>
        Assistente HT & Comportamento
        <button id='geminiChatClose' 
            style='border:none; background:transparent; color:white; 
            font-size:20px; cursor:pointer'>×</button>
    </div>

    <div id='geminiChatMessages' 
        style='padding:12px; height:calc(75vh - 110px); overflow-y:auto;
        background:#f7f7f7'></div>

    <div style='padding:10px; border-top:1px solid #ddd; 
        display:flex; gap:8px; background:white'>
        <input id='geminiChatInput' type='text' 
            class='form-control' placeholder='Digite sua pergunta'
            style='border-radius:6px'>
        <button id='geminiChatSend' class='btn btn-primary'>Enviar</button>
    </div>

</div>";

        echo "<script>
(function(){

var btn   = document.getElementById('geminiChatBtn');
var panel = document.getElementById('geminiChatPanel');
var close = document.getElementById('geminiChatClose');
var input = document.getElementById('geminiChatInput');
var send  = document.getElementById('geminiChatSend');
var msgs  = document.getElementById('geminiChatMessages');

function md(text){
    return text
        .replace(/^### (.*)/gm, '<h3 style=\"margin:6px 0\">$1</h3>')
        .replace(/^## (.*)/gm, '<h2 style=\"margin:6px 0\">$1</h2>')
        .replace(/^# (.*)/gm, '<h1 style=\"margin:6px 0\">$1</h1>')
        .replace(/\\*\\*(.*?)\\*\\*/g, '<b>$1</b>')
        .replace(/\\*(.*?)\\*/g, '<i>$1</i>')
        .replace(/\\n/g, '<br>');
}

function bubble(text, who){
    var wrap = document.createElement('div');
    wrap.style.margin = '10px 0';
    wrap.style.maxWidth = '85%';
    wrap.style.padding = '10px 14px';
    wrap.style.borderRadius = '12px';
    wrap.style.lineHeight = '1.4';
    wrap.style.fontSize = '14px';
    wrap.style.whiteSpace = 'normal';

    if(who === 'me'){
        wrap.style.background = '#4c6ef5';
        wrap.style.color = 'white';
        wrap.style.marginLeft = 'auto';
    } else {
        wrap.style.background = 'white';
        wrap.style.color = '#222';
        wrap.style.border = '1px solid #ddd';
    }

    wrap.innerHTML = md(text);
    msgs.appendChild(wrap);
    msgs.scrollTop = msgs.scrollHeight;
}

function typing(){
    var d = document.createElement('div');
    d.id = 'typingBubble';
    d.style.padding = '10px 14px';
    d.style.background = 'white';
    d.style.border = '1px solid #ddd';
    d.style.borderRadius = '12px';
    d.style.maxWidth = '85%';
    d.style.margin = '10px 0';
    d.innerHTML = '<span style=\"font-size:20px\">...</span>';
    msgs.appendChild(d);
    msgs.scrollTop = msgs.scrollHeight;
}

send.onclick = function(){
    var v = input.value.trim();
    if(!v) return;

    bubble(v, 'me');
    input.value = '';

    typing();

    fetch('gemini_proxy.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({message:v})
    })
    .then(r=>r.json())
    .then(j=>{
        var t = document.getElementById('typingBubble');
        if(t) t.remove();

        bubble(j.error ? 'Erro: '+j.error : j.text, 'bot');
    })
    .catch(()=>{
        var t = document.getElementById('typingBubble');
        if(t) t.remove();
        bubble('Erro ao consultar.', 'bot');
    });
};

btn.onclick = ()=> panel.style.display='block';
close.onclick = ()=> panel.style.display='none';

})();
</script>";

        rodape();
    }
?>
