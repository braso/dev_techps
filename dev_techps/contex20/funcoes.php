<?php
global $CONTEX;

if($_GET['acao'] && $_POST['acao'] == ''){
	$_POST['acao']=$_GET['acao'];
}


// if(!$_SESSION['user_nb_id']){
// 	require('index.php');
// 	exit;
// }

if($_POST['acao'] == $_GET['acao'] || $_GET['acao'] == 'index'){
	foreach ($_GET as $key => $value) {
		if($key != 'acao' && $value != ''){
			$_POST[$key] = $value;
		}
	}
}

if($_POST['acao']==''){
	if(function_exists('index')){

		index();
		exit;
	}
}else{
	if(function_exists($_POST['acao'])){
		$_POST['acao']();
	}else{
		echo"ERRO: Função '".$_POST['acao']."' não existe!";
		exit;
	}
	
}



// include "funcoes_rel.php";


function data_extenso($data){
	setlocale(LC_TIME, 'portuguese');
	return utf8_encode(strftime('%d de %B de %Y', strtotime($data)));
}


function entre($string_inicio,$string_fim,$string_completa){
    $temp1 = strpos($string_completa,$string_inicio)+strlen($string_inicio);
    $result = substr($string_completa,$temp1,strlen($string_completa));
    $dd=strpos($result,$string_fim);
    if($dd == 0){
        $dd = strlen($result);
    }

    return substr($result,0,$dd);
}


function dias_internacao($id){
	$a = carregar('evolucao',$id);
	if($a['evol_tx_dataAlta']=='')
		$a['evol_tx_dataAlta'] = date("Y-m-d");

	$data1 = new DateTime ($a['evol_tx_data']);
	$data2 = new DateTime ($a['evol_tx_dataAlta']);
	$intervalo = $data1 -> diff($data2);
	return $intervalo -> days + 1;
	exit;

}


function valorPorExtenso($valorExtenso=0) {
	$singular = array("centavo", "real", "mil", "milhão", "bilhão", "trilhão", "quatrilhão");
	$plural = array("centavos", "reais", "mil", "milhões", "bilhões", "trilhões","quatrilhões");
 
	$c = array("", "cem", "duzentos", "trezentos", "quatrocentos","quinhentos", "seiscentos", "setecentos", "oitocentos", "novecentos");
	$d = array("", "dez", "vinte", "trinta", "quarenta", "cinquenta","sessenta", "setenta", "oitenta", "noventa");
	$d10 = array("dez", "onze", "doze", "treze", "quatorze", "quinze","dezesseis", "dezesete", "dezoito", "dezenove");
	$u = array("", "um", "dois", "três", "quatro", "cinco", "seis","sete", "oito", "nove");
 
	$z=0;
 
	$valorExtenso = number_format($valorExtenso, 2, ".", ".");
	$inteiro = explode(".", $valorExtenso);
	for($i=0;$i<count($inteiro);$i++)
		for($ii=strlen($inteiro[$i]);$ii<3;$ii++)
			$inteiro[$i] = "0".$inteiro[$i];
 
	// $fim identifica onde que deve se dar junção de centenas por "e" ou por "," 😉
	$fim = count($inteiro) - ($inteiro[count($inteiro)-1] > 0 ? 1 : 2);
	for ($i=0;$i<count($inteiro);$i++) {
		$valorExtenso = $inteiro[$i];
		$rc = (($valorExtenso > 100) && ($valorExtenso < 200)) ? "cento" : $c[$valorExtenso[0]];
		$rd = ($valorExtenso[1] < 2) ? "" : $d[$valorExtenso[1]];
		$ru = ($valorExtenso > 0) ? (($valorExtenso[1] == 1) ? $d10[$valorExtenso[2]] : $u[$valorExtenso[2]]) : "";
	
		$r = $rc.(($rc && ($rd || $ru)) ? " e " : "").$rd.(($rd && $ru) ? " e " : "").$ru;
		$t = count($inteiro)-1-$i;
		$r .= $r ? " ".($valorExtenso > 1 ? $plural[$t] : $singular[$t]) : "";
		if ($valorExtenso == "000")$z++; elseif ($z > 0) $z--;
		if (($t==1) && ($z>0) && ($inteiro[0] > 0)) $r .= (($z>1) ? " de " : "").$plural[$t]; 
		if ($r) $rt = $rt . ((($i > 0) && ($i <= $fim) && ($inteiro[0] > 0) && ($z < 1)) ? ( ($i < $fim) ? ", " : " e ") : " ") . $r;
	}
 
	return($rt ? $rt : "zero");
}

function valorPorExtenso2($valorExtenso=0) {
	// $singular = array("centavo", "real", "mil", "milhão", "bilhão", "trilhão", "quatrilhão");
	// $plural = array("centavos", "reais", "mil", "milhões", "bilhões", "trilhões","quatrilhões");
 
	$c = array("", "cem", "duzentos", "trezentos", "quatrocentos","quinhentos", "seiscentos", "setecentos", "oitocentos", "novecentos");
	$d = array("", "dez", "vinte", "trinta", "quarenta", "cinquenta","sessenta", "setenta", "oitenta", "noventa");
	$d10 = array("dez", "onze", "doze", "treze", "quatorze", "quinze","dezesseis", "dezesete", "dezoito", "dezenove");
	$u = array("", "um", "dois", "três", "quatro", "cinco", "seis","sete", "oito", "nove");
 
	$z=0;
 
	$valorExtenso = number_format($valorExtenso, 2, ".", ".");
	$inteiro = explode(".", $valorExtenso);
	for($i=0;$i<count($inteiro);$i++)
		for($ii=strlen($inteiro[$i]);$ii<3;$ii++)
			$inteiro[$i] = "0".$inteiro[$i];
 
	// $fim identifica onde que deve se dar junção de centenas por "e" ou por "," 😉
	$fim = count($inteiro) - ($inteiro[count($inteiro)-1] > 0 ? 1 : 2);
	for ($i=0;$i<count($inteiro);$i++) {
		$valorExtenso = $inteiro[$i];
		$rc = (($valorExtenso > 100) && ($valorExtenso < 200)) ? "cento" : $c[$valorExtenso[0]];
		$rd = ($valorExtenso[1] < 2) ? "" : $d[$valorExtenso[1]];
		$ru = ($valorExtenso > 0) ? (($valorExtenso[1] == 1) ? $d10[$valorExtenso[2]] : $u[$valorExtenso[2]]) : "";
	
		$r = $rc.(($rc && ($rd || $ru)) ? " e " : "").$rd.(($rd && $ru) ? " e " : "").$ru;
		$t = count($inteiro)-1-$i;
		// $r .= $r ? " ".($valorExtenso > 1 ? $plural[$t] : $singular[$t]) : "";
		if ($valorExtenso == "000")$z++; elseif ($z > 0) $z--;
		if (($t==1) && ($z>0) && ($inteiro[0] > 0)) $r .= (($z>1) ? " de " : "").$plural[$t]; 
		if ($r) $rt = $rt . ((($i > 0) && ($i <= $fim) && ($inteiro[0] > 0) && ($z < 1)) ? ( ($i < $fim) ? ", " : " e ") : " ") . $r;
	}
 
	return($rt ? $rt : "zero");
}

function diferenca_data( $data1,$data2='' ){
	if($data2=='')
		$data2=date("Y-m-d");
	// formato da data yyyy-mm-dd
	$date = new DateTime( $data1 );
	$interval = $date->diff( new DateTime( $data2 ) );
	return $interval->format( '%Y Anos, %m Meses e %d Dias' ); 
}

function validaCPF($cpf) {
 
    // Extrai somente os números
    $cpf = preg_replace( '/[^0-9]/is', '', $cpf );
     
    // Verifica se foi informado todos os digitos corretamente
    if (strlen($cpf) != 11) {
        return false;
    }
    // Verifica se foi informada uma sequência de digitos repetidos. Ex: 111.111.111-11
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    // Faz o calculo para validar o CPF
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    return true;
}

function modal_alert($title, $msg){
	global $CONTEX;
	include 'modal_alert.php';
}


function inserir($tabela,$campos,$valores){
	if(count($campos) != count($valores)){
		echo"ERRO Número de campos não confere com número de linhas na função de inserir!";
		exit;
	}


	$valores= "'".implode("','",$valores)."'";

	$campos=implode(',',$campos);

	try{
		query("INSERT INTO $tabela ($campos) VALUES($valores);") or die(mysql_error());
		$sql = query("SELECT LAST_INSERT_ID();") or die(mysql_error());
	}catch (Exception $e){
		return;
	}

	set_status("Registro inserido com sucesso!");


	$a = carrega_array($sql);
	return $a[0];
}

function atualizar($tabela,$campos,$valores,$id){
	if(count($campos) != count($valores)){
		echo"ERRO Número de campos não confere com número de linhas na função de atualizar!";
		exit;
	}

	$tab=substr($tabela,0,4);



	$inserir= " $campos[0] = '$valores[0]' ";

	for($i=1;$i<count($campos);$i++){
		$inserir.=", $campos[$i] = '$valores[$i]' ";

	}




	query("UPDATE $tabela SET $inserir WHERE ".$tab."_nb_id='$id'") or die(mysql_error());
	set_status("Registro atualizado com sucesso!");

}

function remover($tabela,$id){

	$tab=substr($tabela,0,4);

	query("UPDATE $tabela SET ".$tab."_tx_status='inativo' WHERE ".$tab."_nb_id = '$id' LIMIT 1");

}


function remover_ponto($tabela,$id,$just){

    $tab=substr($tabela,0,4);

    query("UPDATE $tabela SET ".$tab."_tx_status='inativo', ".$tab."_tx_justificativa='$just' WHERE ".$tab."_nb_id='$id' LIMIT 1");

}

function campo_domain($nome,$variavel,$modificador,$tamanho,$mascara='',$extra=''){

	if($mascara=="domain") {
		$data_input="<script>
			$(document).ready(function() {
				var inputField = $('#nomeDominio');
				var domainPrefix = 'https://braso.mobi/".(is_int(strpos($_SERVER["REQUEST_URI"], 'dev_'))? 'dev_techps/': 'techps/')."';

				function updateDisplayedText() {
					var inputValue = inputField.val();

					if (inputValue.startsWith(domainPrefix)) {
						var displayedText = inputValue.substring(domainPrefix.length);
						inputField.val(displayedText);
					}
				}

				// Executar a função de atualização quando o campo for modificado
				inputField.on('input', updateDisplayedText);

				// Inicializar o campo com o valor correto
				updateDisplayedText();
			});
			</script>";
	}

	$campo='<div class="col-sm-'.$tamanho.' margin-bottom-5">
			<label><b>'.$nome.'</b></label>
			<input name="'.$variavel.'" id="'.$variavel.'" value="'.$modificador.'" autocomplete="off" type="text" class="form-control input-sm" '.$extra.'>
		</div>';

	

	return $campo.$data_input;

}

function num_linhas($sql){

	return mysqli_num_rows($sql);
}


function carrega_array($sql, $mode = MYSQLI_BOTH){

	return mysqli_fetch_array($sql, $mode);

}

function ultimo_reg($tabela){

	$tab=substr($tabela,0,4);

	$tab=$tab."_nb_id";

	$sql=query("SELECT $tab FROM $tabela ORDER BY $tab DESC LIMIT 1");
	$a=carrega_array($sql);
	return $a[0];

}



function carregar($tabela,$id='',$campo='',$valor='',$extra='',$exibe=0){

	$tab = substr($tabela,0,4);
	$ext = '';

	$extra_id = ($id!='')? " AND ".$tab."_nb_id= $id": '';

	if($campo[0]!='') {
		$a_campo = explode(',', $campo);
		$a_valor = explode(',', $valor);

		for ($i = 0; $i < count($a_campo); $i++) {

			$ext .= " AND " . str_replace(',', '', $a_campo[$i]) . " = '" . str_replace(',', '', $a_valor[$i]) . "' ";

		}
	}

	$query = "SELECT * FROM $tabela WHERE 1 $extra_id $ext $extra LIMIT 1";

	if($exibe == 1){
		echo $query;
	}
	
	if($extra_id == '' && $ext == '' && $extra == ''){
		return [];
	}else{
		return mysqli_fetch_array(query($query));
	}


}

function valor($valor,$mostrar=0){

	if(floatval(@str_replace(array(','), array('.'), $valor)) ){
		$mostrar = 1;//SEMPRE VAI EXIBIR
	}

	if($mostrar == 1 || $valor > 0 ) {
		// nosso formato
		if (substr($valor, -3, 1) == ',')
			return @str_replace(array('.', ','), array('', '.'), $valor); // retorna 100000.50
		else
			return @number_format($valor, 2, ',', '.'); // retorna 100.000,50
	}else
		return '';
}

function data($data,$hora=0){

	if($data=='0000-00-00' || $data=='00/00/0000' )
		return '';

	if($hora==1){
		$hora="&nbsp;(".substr($data,11).")";
	}elseif($hora==2){
		return substr($data,11);
	}elseif($hora==3){
		return substr($data,11, -3);
	}else{
		$hora='';
	}

	$data=substr($data,0,10);



	if (strstr($data, "/")){//verifica se tem a barra /
		$d = explode ("/", $data);//tira a barra
		$rstData = "$d[2]-$d[1]-$d[0]";//separa as datas $d[2] = ano $d[1] = mes etc...
		return $rstData.$hora;
	}
	else if(strstr($data, "-")){
		$data = substr($data, 0, 10);
		$d = explode ("-", $data);
		$rstData = "$d[2]/$d[1]/$d[0]";
		return $rstData.$hora;
	}
	else{
		return '';
	}

}

function fieldset($nome=''){
	echo "<div class=portlet-title>";
	echo "<span class='caption-subject font-dark bold uppercase'> $nome</span>";
	echo "</div>";
	echo "<hr style='margin:6px;'>";
}

function set_status($msg='') {

	if($msg == '')
		global $msg;
	
	if(strrpos($msg, 'ERRO:') !== false){
		$msg = '<b style="color: red">'.$msg.'</b>';
	}

	$_POST['msg_status'] = $msg;

}

function campo_data($nome,$variavel,$modificador,$tamanho,$extra=''){
	
	$campo='<div class="col-sm-'.$tamanho.' margin-bottom-5">
		<label><b>'.$nome.'</b></label>
		<input name="'.$variavel.'" id="'.$variavel.'" value="'.$modificador.'" autocomplete="off" type="date" class="form-control input-sm" '.$extra.'>
	</div>';

	return $campo;

}

function campo_hora($nome,$variavel,$modificador,$tamanho,$extra='',$intervalo=''){
	
	$campo='<div class="col-sm-'.$tamanho.' margin-bottom-5">
		<label><b>'.$nome.'</b></label>
		<input name="'.$variavel.'" id="'.$variavel.'" value="'.$modificador.'" autocomplete="off" type="time" step="'.$intervalo.'" class="form-control input-sm" '.$extra.'>
	</div>';

	return $campo;

}


function campo_mes($nome,$variavel,$modificador,$tamanho,$extra=''){
	
	$campo='<div class="col-sm-'.$tamanho.' margin-bottom-5">
		<label><b>'.$nome.'</b></label>
		<input name="'.$variavel.'" id="'.$variavel.'" value="'.$modificador.'" autocomplete="off" type="month" class="form-control input-sm" '.$extra.'>
	</div>';

	return $campo;

}

function campo_jornada($nome,$variavel,$modificador,$tamanho){	
	// $data_input = "<script>
	// let jornada = document.getElementById(".$variavel."]);
	// let today = new Date();
	// let date = today.getFullYear()+'-'+(today.getMonth()+1)+'-'+today.getDate();
	// let time = today.getHours() + ':' + today.getMinutes() + ':' + today.getSeconds();
	// let dateTime = date+' '+time;
	// jornada.value = dateTime;
	// </script>";
	
	$campo='<div class="col-sm-'.$tamanho.' margin-bottom-5">
			<label><b>'.$nome.'</b></label>
			<input name="'.$variavel.'" id="'.$variavel.'" value="'.$modificador.'" autocomplete="off" type="text" class="form-control input-sm" '.$extra.' '.$data_input2.'>
		</div>';

	

	return $campo;

}

function checkbox_banco($nome, $variavel, $modificadoRadio,$modificadoCampo=0, $modificadoCampo2=0, $tamanho) {
	$data_input = '<script>
	const radioSim = document.getElementById("sim");
	const radioNao = document.getElementById("nao");
	const campo = document.getElementById("' . $variavel . '");
	const campo2 = document.getElementById("limiteHoras");
	if("'.$modificadoRadio.'" === "sim"){
		radioSim.checked = true;
	}
	else {
		radioNao.checked = true;
	}
	if (radioSim.checked) {
			campo.style.display = ""; // Exibe o campo quando "Mostrar Campo" é selecionado
			campo2.style.display = ""; 
	}
	// Adicionando um ouvinte de eventos aos elementos de rádio
	radioSim.addEventListener("change", function() {
		if (radioSim.checked) {
			campo.style.display = ""; // Exibe o campo quando "Mostrar Campo" é selecionado
			campo2.style.display = ""; 
		}
	});
	radioNao.addEventListener("change", function() {
	if (radioNao.checked) {
		campo.style.display = "none"; // Oculta o campo quando "Não Mostrar Campo" é selecionado
		campo2.style.display = "none"; 
	}
	});
	</script>';
	//  Utiliza regime de banco de horas?
	$campo = '
	<div class="col-sm-' . $tamanho . ' margin-bottom-5">
		<label><b>' . $nome . '</b></label><br>
		<label class="radio-inline">
			<input type="radio" id="sim" name="banco" value="sim"> Sim
		</label>
		<label class="radio-inline">
			<input type="radio" id="nao" name="banco" value="nao"> Não
		</label>
	</div>
	<div id="' . $variavel . '" class="col-sm-' . $tamanho . ' margin-bottom-5" style="display: none;">
			<label><b>Quandidade de Dias:</b></label>
			<input class="form-control input-sm" type="number" value="'.$modificadoCampo.'" id="outroCampo" name="quandDias" autocomplete="off">
	</div>
	<div id="limiteHoras" class="col-sm-' . $tamanho . ' margin-bottom-5" style="display: none;">
		<label><b>Quandidade de Horas Limite:</b></label>
		<input class="form-control input-sm" type="number" value="'.$modificadoCampo2.'" id="outroCampo" name="quandHoras" autocomplete="off">
	</div>
	';

	return $campo . $data_input;
}


function campo($nome,$variavel,$modificador,$tamanho,$mascara='',$extra=''){
	// $variavel_limpa = str_replace(array("[","]"),array("\\[","\\]"),$variavel);	

	if($mascara=="MASCARA_DATA") {
		$data_input = "<script>$(\"#$variavel\").inputmask(\"date\", { \"clearIncomplete\": true, placeholder: 'dd/mm/aaaa' });</script>";
	}
	elseif($mascara=="MASCARA_VALOR")
		// $data_input2 = "data-inputmask='true'";
		// $data_input2 = "data-inputmask-maskMoney=\"allowNegative: true, thousands:'.', decimal:',', affixesStay: false\"";
		// $data_input = "<script>$(\"#$variavel\").maskMoney({ allowNegative: true, thousands:'.', decimal:',', affixesStay: false});</script>";
		$data_input = "<script>$('[name=\"$variavel\"]').maskMoney({ allowNegative: true, thousands:'.', decimal:',', affixesStay: false});</script>";
	elseif($mascara=="MASCARA_FONE")
		$data_input="<script>$('[name=\"$variavel\"]').inputmask({mask: ['(99) 9999-9999', '(99) 99999-9999'], placeholder: \" \" });</script>";
	elseif($mascara=="MASCARA_NUMERO")
		$data_input="<script>$('[name=\"$variavel\"]').inputmask(\"numeric\", {rightAlign: false});</script>";
	elseif($mascara=="MASCARA_CEL")
		$data_input="<script>$('[name=\"$variavel\"]').inputmask({mask: ['(99) 9999-9999', '(99) 99999-9999'], placeholder: \" \" });</script>";
	elseif($mascara=="MASCARA_CEP")
		$data_input="<script>$('[name=\"$variavel\"]').inputmask('99999-999', { clearIncomplete: true, placeholder: \" \" });</script>";
	elseif($mascara=="MASCARA_CPF")
		$data_input="<script>$('[name=\"$variavel\"]').inputmask({mask: '999.999.999-99', clearIncomplete: true, placeholder: \" \" });</script>";
	elseif($mascara=="MASCARA_CNPJ")
		$data_input="<script>$('[name=\"$variavel\"]').inputmask('99.999.999/9999-99', { clearIncomplete: true, placeholder: \" \" });</script>";
	elseif($mascara=="MASCARA_DINHERO")
		$data_input="<script>
		$(function(){
			$('[name=\"$variavel\"]').maskMoney({
			   allowNegative: true,
			   thousands: '.',
			   decimal: ','
			});
		 });
		</script>";

			// <input name="'.$variavel.'" id="'.$variavel.'" value="'.$modificador.'" autocomplete="off" type="text" class="form-control input-sm" '.$extra.' data-placeholder="____" data-inputmask="'.$data_input.'">

		$campo='<div class="col-sm-'.$tamanho.' margin-bottom-5">
			<label><b>'.$nome.'</b></label>
			<input name="'.$variavel.'" id="'.$variavel.'" value="'.$modificador.'" autocomplete="off" type="text" class="form-control input-sm" '.$extra.' '.$data_input2.'>
			<div id="feedback"></div>
		</div>';

	

	return $campo.$data_input;

}

function datepick($nome,$variavel,$modificador,$tamanho,$extra=''){

	$campo='<div class="col-sm-'.$tamanho.' margin-bottom-5">
			<label><b>'.$nome.'</b></label>
			<input name="'.$variavel.'" id="'.$variavel.'" value="'.$modificador.'" size="16" readonly style="background-color:white;" autocomplete="off" type="text" class="form-control input-sm" '.$extra.'>
		</div>

		<script src="/contex20/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js" type="text/javascript"></script>
		<script src="/contex20/assets/global/plugins/bootstrap-datepicker/locales/bootstrap-datepicker.pt-BR.min.js" type="text/javascript"></script>
		<script>
			if (jQuery().datepicker) {
			$("#'.$variavel.'").datepicker({
				orientation: "left",
				autoclose: true,
				format: "dd/mm/yyyy",
				language: "pt-BR"
			});
			
		}
		</script>
		';



	return $campo;

}

function textarea($nome,$variavel,$modificador,$tamanho,$extra=''){

	$campo='<div class="col-sm-'.$tamanho.' margin-bottom-5">
			<label><b>'.$nome.'</b></label>
			<textarea name="'.$variavel.'" id="'.$variavel.'" autocomplete="off" type="password" class="form-control input-sm" '.$extra.'>'.$modificador.'</textarea>
		</div>';

		return $campo;

}

function historico_paciente($id_paciente){


	$sql = query("SELECT hist_tx_descricao,hist_tx_data,user_tx_nome FROM historico,user WHERE user_nb_id = hist_nb_user AND hist_nb_entidade = '$id_paciente' ORDER BY hist_nb_id DESC");
	while($a=carrega_array($sql)){

		$historico .= "=================== <b>DATA: ".data($a['hist_tx_data'])." - PROFISSIONAL: $a[user_tx_nome]</b> ===================<br>";
		$historico .= $a['hist_tx_descricao'];
		$historico .= "<br><br>";
	}

	return $historico;

}

function ckeditor($nome,$variavel,$modificador,$tamanho,$extra=''){

	// echo '';
	$campo='<script src="/ckeditor/ckeditor.js"></script>
		<div class="col-sm-'.$tamanho.' margin-bottom-5">
			<label><b>'.$nome.'</b></label>
			<textarea id="'.$variavel.'" name="'.$variavel.'" class="form-control input-sm" '.$extra.'>'.$modificador.'</textarea>
		</div>
		<script>
			CKEDITOR.replace( "'.$variavel.'" );
		</script>';

		return $campo;

}

function campo_hidden($nome,$valor){


	$campo='<input type="hidden" name="'.$nome.'" id="'.$nome.'" value="'.$valor.'" >';

	echo $campo;

}

function campo_senha($nome,$variavel,$modificador,$tamanho,$extra=''){


$campo='<div class="col-sm-'.$tamanho.' margin-bottom-5">
			<label><b>'.$nome.'</b></label>
			<input name="'.$variavel.'" value="'.$modificador.'" autocomplete="off" type="password" class="form-control input-sm" '.$extra.'>
		</div>';

	return $campo;

}

function texto($nome,$modificador,$tamanho='',$extra=''){


$campo='<div class="col-sm-'.$tamanho.' margin-bottom-5" '.$extra.'>
			<label><b>'.$nome.'</b></label><br>
			<p class="text-left">'.$modificador.'&nbsp;</p>
		</div>';

	return $campo;

}

function texto2($nome,$modificador,$tamanho,$extra=''){


$campo='<div class="col-xs-'.$tamanho.' margin-bottom-5" '.$extra.'>
			<label><b>'.$nome.'</b></label><br>
			<p class="text-left">'.$modificador.'&nbsp;</p>
		</div>';

	return $campo;

}

function combo($nome,$variavel,$modificador,$tamanho,$opcao,$extra=''){
	$t_opcao=count($opcao);
	for($i=0;$i<$t_opcao;$i++){
		if($opcao[$i] != $modificador)
			$selected='';
		else
			$selected="selected";

		$c_opcao .= '<option value="'.$opcao[$i].'" '.$selected.'>'.$opcao[$i].'</option>';
	}


	$campo='<div class="col-sm-'.$tamanho.' margin-bottom-5">
				<label><b>'.$nome.'</b></label>
				<select name="'.$variavel.'" class="form-control input-sm" '.$extra.'>
					'.$c_opcao.'
				</select>
			</div>';


	return $campo;

}

function combo_net($nome,$variavel,$modificador,$tamanho,$tabela,$extra='',$extra_bd='',$extra_busca='',$extra_ordem='',$extra_limite='15'){
global $CONTEX,$conn;

if($modificador>0){
	$tab = substr($tabela,0,4);
	if($extra_busca != '')
		$extra_campo = ",$extra_busca";
		

	$sql=query("SELECT ".$tab."_tx_nome $extra_campo FROM $tabela WHERE  ".$tab."_nb_id = '$modificador' AND ".$tab."_tx_status = 'ativo'");
	$a=carrega_array($sql);
	if($extra_busca != '')
		$a[0] = "[$a[1]] $a[0]";
	$opt="<option value='$modificador'>$a[0]</option>";
}
?>

<!-- <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.js"></script> -->
<!-- <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" /> -->
<!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script> -->
<?
	// <select id="'.$variavel.'" name="'.$variavel.'" class="form-control input-sm select2 '.$variavel.'" '.$extra.'></select>
	$campo='<div class="col-sm-'.$tamanho.' margin-bottom-5">
				<label><b>'.$nome.'</b></label>
				<select class="'.$variavel.' form-control input-sm" id="'.$variavel.'" style="width:100%" '.$extra.' name="'.$variavel.'">
				'.$opt.'
				</select>
			</div>';

?>
<script type="text/javascript">
$.fn.select2.defaults.set("theme", "bootstrap");
$(window).bind("load", function() {
	$('.<?=$variavel?>').select2({
		language: 'pt-BR',
		placeholder: 'Selecione um item',
		allowClear: true,
		ajax: {
			url: '/contex20/select2.php?path=<?=$CONTEX['path']?>&tabela=<?=$tabela?>&extra_ordem=<?=$extra_ordem?>&extra_limite=<?=$extra_limite?>&extra_bd=<?=urlencode($extra_bd)?>&extra_busca=<?=urlencode($extra_busca)?>',
			dataType: 'json',
			delay: 250,
			processResults: function (data) {
			return {
				results: data
			};
			},
			cache: true
		}
	});
});


</script>
<?
	// echo $campo;
	return $campo;

}


function combo_bd($nome,$variavel,$modificador,$tamanho,$tabela,$extra='',$extra_bd=''){

	$tab=substr($tabela,0,4);
	
	if($nome[0] == "!"){
		$c_opcao.="<option value=''></option>";
		$nome=substr($nome, 1);
	}
	
	// if(stripos($extra_bd,"order by") === false){
	// 	$extra_bd=" ORDER BY ".$tab."_tx_nome ASC";
	// }

	if($extra_bd == ''){
		$extra_bd = " ORDER BY ".$tab."_tx_nome ASC";
	}

	
	$sql=query("SELECT ".$tab."_nb_id, ".$tab."_tx_nome FROM $tabela WHERE ".$tab."_tx_status != 'inativo' $extra_bd");
	while($a=mysqli_fetch_array($sql)){

		if($a[0] == $modificador || $a[1] == $modificador)
			$selected="selected";
		else
			$selected='';

		$c_opcao .= '<option value="'.$a[0].'" '.$selected.'>'.$a[1].'</option>';

	}

	$campo='<div class="col-sm-'.$tamanho.' margin-bottom-5">
				<label><b>'.$nome.'</b></label>
				<select name="'.$variavel.'" id="'.$variavel.'" class="form-control input-sm" '.$extra.'>
					'.$c_opcao.'
				</select>
			</div>';


	return $campo;

}

function arquivosParametro($nome,$idParametro,$arquivos){

	$arquivo_list = '';
	if (!empty($arquivos)) {
		foreach($arquivos as $arquivo){
			$dataHoraOriginal = $arquivo['doc_tx_dataCadastro'];
			$dataHora = new DateTime($dataHoraOriginal);
			$dataHoraFormatada = $dataHora->format('d/m/Y H:i:s');
			$arquivo_list .= "
			<tr role='row' class='odd'>
			<td>$arquivo[doc_tx_nome]</td>
			<td>$arquivo[doc_tx_descricao]</td>
			<td>$dataHoraFormatada</td>
			<td>
				<a style='color: steelblue;' onclick=\"javascript:downloadArquivo($idParametro,'$arquivo[doc_tx_caminho]','downloadArquivo');\"><i class='glyphicon glyphicon-cloud-download'></i></a>
			</td>
			<td>
				<a style='color: red;' onclick=\"javascript:remover_arquivo($idParametro,$arquivo[doc_nb_id],'$arquivo[doc_tx_nome]','excluir_documento');\"><i class='glyphicon glyphicon-trash'></i></a>
			</td>";
		}
	}


	$tabela='
		<div class="portlet light ">
			<div class="portlet-title">
			<div class="caption">
				<span class="caption-subject font-dark bold uppercase">'.$nome.'</span>
			</div>
			</div>
			<div class="portlet-body">
				<table id="contex-grid" class="table compact table-striped table-bordered table-hover dt-responsive"
					width="100%" id="sample_2">
					<thead>
						<tr role="row">
							<th class="sorting" tabindex="0" aria-controls="contex-grid" rowspan="1" colspan="1"
								aria-label="NOME: activate to sort column ascending" style="width: 40px;">NOME</th>
							<th class="sorting" tabindex="0" aria-controls="contex-grid" rowspan="1" colspan="1"
								aria-label="DESCRIÇÃO: activate to sort column ascending" style="width: 40px;">
								DESCRIÇÃO</th>
							<th class="sorting" tabindex="0" aria-controls="contex-grid" rowspan="1" colspan="1"
								aria-label="DATA CADASTRO: activate to sort column ascending" style="width: 40px;">
								DATA CADASTRO</th>
							<th class="sorting" tabindex="0" aria-controls="contex-grid" rowspan="1" colspan="1"
								aria-label="DOWNLOAD: activate to sort column ascending" style="width: 40px;"><i
									class="glyphicon glyphicon-cloud-download"></i></th>
							<th class="sorting" tabindex="0" aria-controls="contex-grid" rowspan="1" colspan="1"
								aria-label="DOWNLOAD: activate to sort column ascending" style="width: 40px;"><i
									class="glyphicon glyphicon-trash"></i></th>
						</tr>
					</thead>
					<thbody>
					'.$arquivo_list.'
					<tr role="row" class="even">
					<td>
					<a href="#" data-toggle="modal" data-target="#myModal">
					<i class="glyphicon glyphicon-plus-sign"></i>
					</a>
					</td>
					</thbody>
					</table>
	';

	$modal = "
	<div class='modal fade' id='myModal' tabindex='-1' role='dialog' aria-labelledby='myModalLabel'>
		<div class='modal-dialog' role='document'>
			<div class='modal-content'>
				<div class='modal-header'>
				<button type='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
				<h4 class='modal-title' id='myModalLabel'>Upload Arquivo</h4>
				</div>
				<div class='modal-body'>
				<form name='form_enviar_arquivo' method='post' action='cadastro_parametro.php' enctype='multipart/form-data'>
					<div class='form-group'>
						<label for='file-name' class='control-label'>Nome do arquivo:</label>
						<input type='text' class='form-control' name='file-name'>
					</div>
					<div class='form-group'>
						<label for='description-text' class='control-label'>Descrição:</label>
						<textarea class='form-control' name='description-text'></textarea>
					</div>
					<div class='form-group'>
						<label for='file' class='control-label'>Arquivo:</label>
						<input type='file' class='form-control' name='file'>
					</div>
					
					<input type='hidden' name='acao' value='enviar_documento'>
					
					<input type='hidden' name='idParametro' value='$idParametro'>
				</form>
				</div>
				<div class='modal-footer'>
					<button type='button' class='btn btn-default' data-dismiss='modal'>Cancelar</button>
					<button type='button' class='btn btn-primary' data-dismiss='modal' 
					onclick=\"javascript:enviar_arquivo();\">Salvar arquivo</button>
				</div>
			</div>
		</div>
	</div>
	
	<script type='text/javascript'>
	function enviar_arquivo() {
		document.form_enviar_arquivo.submit();
	}
	
	</script>
	";

		return $tabela.$modal;

}


function arquivo($nome,$variavel,$modificador,$tamanho,$extra=''){
	global $CONTEX;
	if($modificador){
		$ver = "<a href=$CONTEX[path]/$modificador target=_blank>(Ver)</a>";
	}

	$campo='<div class="col-sm-'.$tamanho.' margin-bottom-5">
				<label><b>'.$nome.$ver.'</b></label>
				<input name="'.$variavel.'" value="'.$CONTEX['path']."/".$modificador.'" autocomplete="off" type="file" class="form-control input-sm" '.$extra.'>
			</div>';

		return $campo;

}

function enviar($arquivo,$diretorio,$nome='') {

	$target_path = "$diretorio";
	
	$extensao = pathinfo($target_path . basename($_FILES[$arquivo]['name'], PATHINFO_EXTENSION));
	// if('.php', '.php3', '.php4', '.phtml', '.pl', '.py', '.jsp', '.asp', '.htm', '.shtml', '.sh', '.cgi')

	if($nome!='') {
		$target_path .= "$nome.$extensao[extension]";
	}else {
		$target_path .= basename($_FILES[$arquivo]['name']);
	}
	
	if(move_uploaded_file($_FILES[$arquivo]['tmp_name'], $target_path)) {
		set_status("O arquivo ".  basename( $_FILES[$arquivo]['name']). " foi enviado");
		return $target_path;
	} else{
		echo("Ocorreu um erro ao tentar enviar o arquivo!");
		exit;

	}

}


function botao($nome,$acao,$campos='',$valores='',$extra='',$salvar=''){
global $idsBotaoContex;	
	if($campos[0]!=''){

		$a_campos=explode(',',$campos);
		$a_valores=explode(',',$valores);

		for($i=0;$i<count($a_campos);$i++){

			// $hidden.="<input type='hidden' name='$a_campos[$i]' value='$a_valores[$i]'>";
			$hidden.="var input$i = document.createElement('input');
					input$i.type = 'hidden';
					input$i.name = '$a_campos[$i]';
					input$i.value = '$a_valores[$i]';
					document.forms[0].appendChild(input$i);";

		}

	}

	if($salvar == 1){
		?>
			<script type="text/javascript">
			function criarGET() {
				var form = document.forms[0];
				var elements = form.elements;
				var values = [];
				var primeiraAcao = '';

				for (var i = 0; i < elements.length; i++){
					if(elements[i].name == 'acao' && elements[i].value  != 'index'){
						continue;
					}

					values.push(encodeURIComponent(elements[i].name) + '=' + encodeURIComponent(elements[i].value));

				}
				form.action = '?' + values.join('&');
			}
			</script>
		<?
		$funcaoOnClick = 'criarGET();';
	}

	$nomeFuncao='b'.md5($nome.$campos.$valores);
	if($hidden!=''){
		$funcaoJs="
			<script>
			function $nomeFuncao(){
				$hidden
			}
			</script>
		";
		$funcaoOnClick .= $nomeFuncao.'();';
	}

	
	if($funcaoOnClick != ''){
		$funcaoOnClick = 'onclick="'.$funcaoOnClick.'"';
	}

	return $funcaoJs.'<button '.$funcaoOnClick.' name="acao" id="botaoContex'.$nome.'" value="'.$acao.'"  type="submit" '.$extra.'  class="btn default">'.$nome.'</button>';

}


function query($query,$debug=''){
	global $conn;
	$sql = mysqli_query($conn,$query) or die(mysqli_error($conn));
	if($debug=='1'){
		echo $query;
	}
	return $sql;

}


function icone_modificar($id,$acao,$campos='',$valores='',$target='',$icone='glyphicon glyphicon-search',$action='',$msg='',$title=''){
	if($icone==''){
		$icone = 'glyphicon glyphicon-search';
	}
	
	if($icone == 'glyphicon glyphicon-search' && $title == '')
		$title = 'Modificar';
	
	$icone='class="'.$icone.'"';
	
	return "<center><a title=\"$title\" style='color:gray' onclick='javascript:contex_icone(\"$id\",\"$acao\",\"$campos\",\"$valores\",\"$target\",\"$msg\",\"$action\");' ><spam $icone></spam></a></center>";
	
}

function icone_excluir($id,$acao,$campos='',$valores='',$target='',$icone='',$msg='Deseja excluir o registro?', $action='', $title=''){
	if($icone==''){
		$icone = 'glyphicon glyphicon-remove';
	}
	
	if($icone == 'glyphicon glyphicon-remove' && $title == '')
		$title = 'Excluir';

	$icone='class="'.$icone.'"';
	
	return "<center><a title=\"$title\" style='color:gray' onclick='javascript:contex_icone(\"$id\",\"$acao\",\"".$campos."\",\"".$valores."\",\"$target\",\"$msg\",\"$action\");' ><spam $icone></spam></a></center>";
	
}

function abre_menu_aba($nome,$id,$contexAbaAtiva=''){
	global $CONTEX;
	$CONTEX['abaAtiva'] = $contexAbaAtiva;
	$a_nome = explode(",",$nome);
	$a_id = explode(",",$id);

	if(count($a_nome) != count($a_id)){
		echo "ERRO: Função de abas errada!";
	}

	$aba = "<div class='portlet-body'>";
	$aba .= "<ul class='nav nav-tabs'>";
	for($i=0;$i<count($a_nome);$i++){
		if($CONTEX['abaAtiva']==''){
			$CONTEX['abaAtiva']=$a_id[$i];
			$active = 'class="active"';
		}else{


			if($a_id[$i]==$CONTEX['abaAtiva']){
				$CONTEX['abaAtiva']=$a_id[$i];
				$active = 'class="active"';
			}else{
				$active = '';
			}

		}

		$aba .= "<li $active>";
		$aba .= "<a href='#".$a_id[$i]."' data-toggle='tab'> ".$a_nome[$i]." </a>";
		$aba .= "</li>";

	}

	$aba.='</ul>';

	
	echo $aba.'<div class="tab-content">';
}

function fecha_menu_aba(){
	echo '</div>';
	echo '</div>';
}

function abre_aba($id){
	global $CONTEX;
	if($CONTEX['abaAtiva'] == $id){
		$active = 'active in';
	}else{
		$active = '';
	}

	echo '<div class="tab-pane fade '.$active.'" id="'.$id.'">';
}

function fecha_aba(){
	echo '</div>';
}
