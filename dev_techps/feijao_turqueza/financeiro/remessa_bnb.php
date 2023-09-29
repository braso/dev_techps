<?php
include "../conecta.php";

function sanitizeString($string) {



    // matriz de entrada

    $what = array( 'ä','ã','à','á','â','ê','ë','è','é','ï','ì','í','ö','õ','ò','ó','ô','ü','ù','ú','û','À','Á','É','Í','Ó','Ú','ñ','Ñ','ç','Ç','-','(',')',',',';',':','|','!','"','#','$','%','&','/','=','?','~','^','>','<','ª','º','°' );



    // matriz de saída

    $by   = array( 'a','a','a','a','a','e','e','e','e','i','i','i','o','o','o','o','o','u','u','u','u','A','A','E','I','O','U','n','n','c','C','','','','','','','','','','','','','','','','','','','','','','',' ' );



    // devolver a string

    return str_replace($what, $by, $string);

}





function cadastra_remessa(){

	include('../boletophp/include/funcoes_bnb.php');



	//PREPARA SQL QUE PERCORRE OS BOLETOS

	if($_POST[data1]=='')

		$_POST[data1] = date("d/m/Y");

		

	$extra .= " AND bole_tx_dataCadastro >= '".data($_POST[data1])." 00:00:00'";

	

	if($_POST[data2]=='')

		$_POST[data2] = date("d/m/Y");



	$extra .= " AND bole_tx_dataCadastro <= '".data($_POST[data2])." 23:59:59'";



	$extra .= " AND movi_nb_forma = '$_POST[forma]'";



	if($_POST[nome])

		$extra .=" AND enti_tx_nome LIKE '%$_POST[nome]%'";

	if($_POST[turma])

		$extra .=" AND enti_nb_turma = '$_POST[turma]'";



	$sql = query("SELECT * FROM boleto, movimento, entidade,turma WHERE bole_tx_status =  'ativo'  AND movi_nb_planoconta = '1' AND movi_nb_entidade = enti_nb_id  AND bole_nb_movimento = movi_nb_id AND enti_nb_turma = turm_nb_id $extra");



	//CARREGA DADOS DO BOLETO

	$a_form = carregar('forma',$_POST[forma]);



	//CRIA ARQUIVO DE REMESSA

	$nome_arquivo = "../arquivos/remessa/bnb_".date('YmdHis').".txt";



	if( !( $h=@fopen($nome_arquivo,'w') ) ) {

		

		set_status("ERRO: Impossível criar o arquivo!");

		index();

		exit;

	}



	//-----inicio linha 0------

	$cabecalho="01REMESSA01COBRANCA       ";

	$agencia=str_pad($a_form[form_tx_agencia],4,"0",STR_PAD_LEFT);

	$zeros2='00';

	$conta=str_pad($a_form[form_tx_conta],7,"0",STR_PAD_LEFT);

	$dg_conta=substr($a_form[form_tx_contadv],-1,1);

	$branco6='      ';

	$nome=str_pad(substr("AURINEIDE FREIRE DOS SANTOS-ME",0,30),30);

	$codBanco="004";

	$nomeBanco=str_pad("B. DO NORDESTE",15);

	$data=date('dmy');

	$codUsuario='000';

	$branco291=str_pad('',291);

	$nSequencial='000001'; 



	// $total=strlen($cabecalho)+$total;

	// echo "'cabecalho - $cabecalho' - ".strlen($cabecalho)." TOTAL: ".$total."<br>";



	// $total=strlen($agencia)+$total;

	// echo "'agencia - $agencia' - ".strlen($agencia)." TOTAL: ".$total."<br>";



	// $total=strlen($zeros2)+$total;

	// echo "'zeros2 - $zeros2' - ".strlen($zeros2)." TOTAL: ".$total."<br>";



	// $total=strlen($conta)+$total;

	// echo "'conta - $conta' - ".strlen($conta)." TOTAL: ".$total."<br>";



	// $total=strlen($dg_conta)+$total;

	// echo "'dg_conta - $dg_conta' - ".strlen($dg_conta)." TOTAL: ".$total."<br>";



	// $total=strlen($branco6)+$total;

	// echo "'branco6 - $branco6' - ".strlen($branco6)." TOTAL: ".$total."<br>";



	// $total=strlen($nome)+$total;

	// echo "'nome - $nome' - ".strlen($nome)." TOTAL: ".$total."<br>";



	// $total=strlen($codBanco)+$total;

	// echo "'codBanco - $codBanco' - ".strlen($codBanco)." TOTAL: ".$total."<br>";



	// $total=strlen($nomeBanco)+$total;

	// echo "'nomeBanco - $nomeBanco' - ".strlen($nomeBanco)." TOTAL: ".$total."<br>";



	// $total=strlen($data)+$total;

	// echo "'data - $data' - ".strlen($data)." TOTAL: ".$total."<br>";



	// $total=strlen($codUsuario)+$total;

	// echo "'codUsuario - $codUsuario' - ".strlen($codUsuario)." TOTAL: ".$total."<br>";



	// $total=strlen($branco291)+$total;

	// echo "'branco291 - $branco291' - ".strlen($branco291)." TOTAL: ".$total."<br>";



	// $total=strlen($nSequencial)+$total;

	// echo "'nSequencial - $nSequencial' - ".strlen($nSequencial)." TOTAL: ".$total."<br>";



	// exit;





	fwrite($h,$cabecalho.$agencia.$zeros2.$conta.$dg_conta.$branco6.$nome.$codBanco.$nomeBanco.$data.$codUsuario.$branco291.$nSequencial. "\r\n");

	//------fim linha 0-------

	

	$TOTAL_BOLETOS=0;

	while($a=carrega_array($sql)){

		$TOTAL_BOLETOS++;



		$IDS_BOL[] = $a[bole_nb_id];



		$a_cida = carregar('cidade',$a[enti_nb_cidade]);



		$valor=valor($a[bole_tx_valordoc]);

		$valor=str_pad(str_replace(array('.',','),array('',''),$valor),13,"0",STR_PAD_LEFT);



		if($a[enti_tx_cep]=='')

			$CEP = '00000000';

		else

			$CEP=$a[enti_tx_cep];





		//-----inicio linha 1------

		$tipolinha="1";

		$branco16=str_pad("",16);

		$agencia=str_pad($a_form[form_tx_agencia],4,"0",STR_PAD_LEFT);

		$zeros2='00';

		$conta=str_pad($a_form[form_tx_conta].$a_form[form_tx_contadv],8,"0",STR_PAD_LEFT);

		$multa='00';

		$branco4=str_pad("",4);

		$numControle=str_pad($a[bole_nb_id],25,"0",STR_PAD_LEFT); //No de Controle do Título do Cliente. (Controle da empresa)

		$nnum=str_pad($a[bole_tx_nossoNum],7,"0",STR_PAD_LEFT);

		$dv_nnum=digitoVerificador_nossonumero($nnum);

		$numContrato=str_pad('',10,"0",STR_PAD_LEFT); //Número do Contrato para cobrança caucionada/vinculada. Preencher com zeros para cobrança simples.

		$dataDesconto="000000";

		$valorDesconto=str_pad("",13,"0");

		$branco8=str_pad("",8);

		$carteira='4'; //4 -> carteira 21 = Cobranca simples-Boleto emitido pelo cliente



		// $codServico='06'; //06 ALTERACAO DE VENCIMENTO

		// $codServico='02'; //02 BAIXA DE BOLETO

		$codServico='01'; //01 REMESSA NORMAL



		$seuNumero=str_pad($a[bole_nb_id],10,"0",STR_PAD_LEFT);

		$vencimento=substr($a[bole_tx_vencimento],8,2).substr($a[bole_tx_vencimento],5,2).substr($a[bole_tx_vencimento],2,2); 

		$valorTitulo=$valor;

		$numeroBanco='000'; //Preencher com Zeros (Banco Cobrador será definido conforme cobradora)

		$agenciaCobradora='0000'; //Agência Cobradora será definida pelo BNB Com base no CEP do Sacado !------------ESTE ITEM ESTA PENDENTE ---------------!

		$branco1=str_pad("",1);

		$especie='01'; // 01 -> Duplicata Mercantil

		$aceite='S';

		$dataEmissao=date('dmy');

		$codInstrucao='0000';

		$jurosDia=str_pad("",13,"0");

		$dataDesconto='000000';

		$valorDesconto=str_pad("",13,"0");

		$valorIOC=str_pad("",13,"0");

		$valorAbatimento=str_pad("",13,"0");



		if(strlen($a[enti_tx_cpf])=='14'){

			$codInscricao='01'; // ATENCAO!!! “01” para CPF, ou “02” para CNPJ.

		}else{

			$codInscricao='02'; // ATENCAO!!! “01” para CPF, ou “02” para CNPJ.

		}



		$incricao=str_pad(str_replace(array('.','-','/'),array('','',''),$a[enti_tx_cpf]),14,"0",STR_PAD_LEFT);

		$nomeSacado=strtoupper(substr(str_pad(sanitizeString($a[enti_tx_nome]),40," ",STR_PAD_LEFT),0,40));

		$enderecoSacado=strtoupper(substr(str_pad(sanitizeString($a[enti_tx_endereco]),40),0,40));

		$complementoSacado=str_pad("",12);

		$cepSacado=str_pad(str_replace(array('-','.'),array('',''),$a[enti_tx_cep]),8,"0",STR_PAD_LEFT);	

		

		if($a[enti_nb_cidade]=='' || $a[enti_nb_cidade]=='0'){

			$a_cida[cida_tx_nome] = "MOSSORO";

			$a_cida[cida_tx_uf]='RN';

		}

		$cidadeSacado=strtoupper(substr(str_pad(sanitizeString($a_cida[cida_tx_nome]),15," ",STR_PAD_RIGHT),0,15));

		$ufSacado=strtoupper(substr(str_pad(sanitizeString($a_cida[cida_tx_uf]),2),0,2));

		$msgSacadi=strtoupper(substr(str_pad(sanitizeString($a[bole_tx_obs]),40),0,40));

		$protesto='99';

		$moeda='0';

		$nRegistro=str_pad(++$nSequencial,6,"0",STR_PAD_LEFT);



		fwrite($h,$tipolinha.$branco16.$agencia.$zeros2.$conta.$multa.$branco4.$numControle.$nnum.$dv_nnum.$numContrato.$dataDesconto.$valorDesconto.$branco8.$carteira.

			$codServico.$seuNumero.$vencimento.$valorTitulo.$numeroBanco.$agenciaCobradora.$branco1.$especie.$aceite.$dataEmissao.$codInstrucao.$jurosDia.$dataDesconto.$valorDesconto.

			$valorIOC.$valorAbatimento.$codInscricao.$incricao.$nomeSacado.$enderecoSacado.$complementoSacado.$cepSacado.$cidadeSacado.$ufSacado.$msgSacadi.$protesto.$moeda.$nRegistro. "\r\n");

		//------fim linha 1-------



		// $tipolinha

		// $branco16

		// $agencia

		// $zeros2

		// $conta

		// $multa

		// $branco4

		// $numControle

		// $nnum

		// $dv_nnum

		// $numContrato

		// $dataDesconto

		// $valorDesconto

		// $branco8

		// $carteira

		// $codServico

		// $seuNumero

		// $vencimento

		// $valorTitulo

		// $numeroBanco

		// $agenciaCobradora

		// $branco1

		// $especie

		// $aceite

		// $dataEmissao

		// $codInstrucao

		// $jurosDia

		// $dataDesconto

		// $valorDesconto

		// $valorIOC

		// $valorAbatimento

		// $codInscricao

		// $incricao

		// $nomeSacado

		// $enderecoSacado

		// $complementoSacado

		// $cepSacado

		// $cidadeSacado

		// $ufSacado

		// $msgSacadi

		// $protesto

		// $moeda

		// $nRegistro







	}



	

	//------inicio linha 9-------

	fwrite($h,"9".str_pad("",393).str_pad(++$nSequencial,6,"0",STR_PAD_LEFT). "\r\n");

	//------fim linha 9-------



		

	fclose($h);





	if( $TOTAL_BOLETOS > 0 ) {

		

		$campos=array(reme_tx_nome,reme_tx_arquivo,reme_tx_data,reme_nb_user,reme_tx_status);

		$valores=array("Remessa $a_form[form_tx_nome] de $_POST[data1] a $_POST[data2]",basename($nome_arquivo),date("Y-m-d"),$_SESSION[user_nb_id],'ativo');

		inserir('remessa',$campos,$valores,1);

		$id_remessa = ultimo_reg('remessa');	

		

		$ids_string = @implode(",",$IDS_BOL);

		query("UPDATE boleto SET bole_nb_remessa='$id_remessa' WHERE bole_nb_id IN ($ids_string)");

		

		set_status("$TOTAL_BOLETOS boletos gerados com sucesso!");

		

	} else {

		set_status("ERRO: Não há boletos a enviar!");

		unlink($nome_arquivo);

		

	}



	index();

	exit;



}





function layout_remessabnb(){



	include "../boletophp/funcoes_bnb.php";



	cabecalho('Remessa Bancária BNB');



	if($_POST[data1]=='')

		$_POST[data1] = date("d/m/Y");

		

	$extra .= " AND bole_tx_dataCadastro >= '".data($_POST[data1])." 00:00:00'";

	

	if($_POST[data2]=='')

		$_POST[data2] = date("d/m/Y");



	$extra .= " AND bole_tx_dataCadastro <= '".data($_POST[data2])." 23:59:59'";



	

	$extra .= " AND movi_nb_forma = '4'";



	if($_POST[nome])

		$extra .=" AND enti_tx_nome LIKE '%$_POST[nome]%'";

	if($_POST[turma])

		$extra .=" AND enti_nb_turma = '$_POST[turma]'";



	$c[] = campo('Data Cad. Inicial:','data1',$_POST[data1],2,MASCARA_DATA);

	$c[] = campo('Data Cad. Final:','data2',$_POST[data2],2,MASCARA_DATA);

	$c[] = campo('Nome','nome',$_POST[nome],2);

	$c[] = combo_bd('Forma:','forma',$_POST[forma],3,'forma',''," AND form_nb_id = '4'");

	$c[] = combo_bd('!Turma','turma',$_POST[turma],3,'turma','',"AND turm_nb_id IN (SELECT rate_nb_turma FROM rateado WHERE rate_tx_status = 'ativo')");

	// $c[] = arquivo('Arquivo de retorno Bancário:','arquivo','',5);







	



	$b[] = botao("Buscar",'layout_remessabnb');

	$b[] = botao("Gerar",'cadastra_remessa');

	$b[] = botao("Voltar",'index');

	

	abre_form('Arquivo de Remessa');

	linha_form($c);

	fecha_form($b);



	$sql = "SELECT * FROM boleto, movimento, entidade,turma WHERE bole_tx_status =  'ativo'  AND movi_nb_planoconta = '1' AND movi_nb_entidade = enti_nb_id  AND bole_nb_movimento = movi_nb_id AND enti_nb_turma = turm_nb_id $extra";

	$cab = array('CÓDIGO','NOSSO NÚM.','ALUNO','TURMA','OBSERVAÇÃO','PARCELA','DATA CADASTRO','VALOR','SITUAÇÃO','');

	$val = array('bole_nb_id','bole_tx_nossoNum','enti_tx_nome','turm_tx_nome','bole_tx_obs','bole_tx_parcela','data(bole_tx_dataCadastro)',

		'valor(bole_tx_valordoc)','ucfirst(bole_tx_status)',

		// 'icone_modificar(bole_nb_id,layout_atualiza)',

		// 'icone_modificar(bole_nb_id,imprime_boleto_individual,,,_blank,glyphicon glyphicon-list-alt,../boletophp/boleto_santander.php)');

		'icone_modificar(bole_nb_id,abre_boleto_individual,,,_blank,glyphicon glyphicon-list-alt)');



	grid($sql,$cab,$val,0,'',0);



	rodape();

}
function download_remessa(){
	$a=carregar('remessa',$_POST[id]);
	$file = "http://www.containerti.com.br/imagem/arquivos/remessa/$a[reme_tx_arquivo]";
	header_remove();
	header("Content-Disposition: attachment; filename='" . basename($file) . "'"); 
	readfile ($file);
	exit(); 
}

function index() {
	global $CACTUX_CONF;

	cabecalho('Remessa Bancária BNB',1);

	if($_POST[busca_inicio])
		$extra .= " AND reme_tx_data >= '".data($_POST[busca_inicio],1)."'";
	if($_POST[busca_fim])
		$extra .= " AND reme_tx_data <= '".data($_POST[busca_fim],1)."'";

	
	
	//CONSULTA
	$c[] = campo('Código:','busca_codigo',$_POST[busca_codigo],2);
	$c[] = campo('Data Início:','busca_inicio',$_POST[busca_inicio],2,MASCARA_DATA);
	$c[] = campo('Data Fim:','busca_fim',$_POST[busca_fim],2,MASCARA_DATA);
		
	

//BOTOES
	$b[] = botao("Buscar",'index');
	$b[] = botao("Inserir",'layout_remessabnb');
	
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);
	

	$sql=" SELECT *	FROM remessa,user WHERE  reme_nb_user = user_nb_id AND  reme_tx_status != 'inativo' $extra ";
	$cab = array('CÓD','ARQUIVO','USUÁRIO','DATA','SITUAÇÃO','');

	$ver2 = 'icone_modificar(reme_nb_id,download_remessa,,,_blank,glyphicon glyphicon-list-alt)';
	$val = array(reme_nb_id,reme_tx_nome,user_tx_nome,'data(reme_tx_data)','ucfirst(reme_tx_status)',$ver2);
	grid($sql,$cab,$val,'','',0,'desc');

	
	rodape();
}