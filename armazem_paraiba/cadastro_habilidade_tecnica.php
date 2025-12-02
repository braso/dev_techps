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
            combo("Importância*", "importancia", !empty($habilidade["habi_tx_importancia"]) ? $habilidade["habi_tx_importancia"] : "media", 3, ["alta" => "Alta", "media" => "Média", "baixa" => "Baixa"], "required")
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
            "IMPORTÂNCIA" => "CASE habi_tx_importancia WHEN 'alta' THEN CONCAT('<span style\\=\"color:#d9534f\"><i class\\=\'fa fa-circle\\'></i> Alta</span>') WHEN 'media' THEN CONCAT('<span style\\=\"color:#f0ad4e\"><i class\\=\'fa fa-circle\\'></i> Média</span>') ELSE CONCAT('<span style\\=\"color:#5cb85c\"><i class\\=\'fa fa-circle\\'></i> Baixa</span>') END AS habi_tx_importancia",
            "DATA DE CADASTRO" => "habi_tx_dataCadastro",
            "DATA DE ALTERAÇÃO" => "habi_tx_dataAtualiza"
        ];

        $camposBusca = [
            "busca_nome_like" => "habi_tx_nome"
        ];

        $queryBase =
            "SELECT ".implode(", ", array_values($gridFields))." FROM habilidade_tecnica"
        ;

        $total = mysqli_fetch_assoc(query("SELECT COUNT(*) AS qtd FROM habilidade_tecnica;"));
        $alta = mysqli_fetch_assoc(query("SELECT COUNT(*) AS qtd FROM habilidade_tecnica WHERE habi_tx_importancia = 'alta';"));
        $media = mysqli_fetch_assoc(query("SELECT COUNT(*) AS qtd FROM habilidade_tecnica WHERE habi_tx_importancia = 'media';"));
        $baixa = mysqli_fetch_assoc(query("SELECT COUNT(*) AS qtd FROM habilidade_tecnica WHERE habi_tx_importancia = 'baixa';"));

   

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
        cabecalho("Cadastro de Habilidades Técnicas");
        formHabilidade();
        if(empty($_POST["id"])){
            listarHabilidades();
        }
echo "<button id='geminiChatBtn' 
style='position:fixed; display:none; right:20px; bottom:20px; z-index:9999;
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

/* Conversor Markdown simples */
function md(text){
    return text
        .replace(/^### (.*)/gm, '<h3 style=\"margin:6px 0\">$1</h3>')
        .replace(/^## (.*)/gm, '<h2 style=\"margin:6px 0\">$1</h2>')
        .replace(/^# (.*)/gm, '<h1 style=\"margin:6px 0\">$1</h1>')
        .replace(/\\*\\*(.*?)\\*\\*/g, '<b>$1</b>')
        .replace(/\\*(.*?)\\*/g, '<i>$1</i>')
        .replace(/\\n/g, '<br>');
}

/* Bolha de mensagem */
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

/* bolha de digitando... */
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

/* Envio */
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

/* Abrir / Fechar */
btn.onclick = ()=> panel.style.display='block';
close.onclick = ()=> panel.style.display='none';

})();
</script>";


        rodape();
    }
?>
