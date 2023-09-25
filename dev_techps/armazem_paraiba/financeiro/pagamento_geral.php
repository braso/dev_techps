<?php
include "../conecta.php";


function excluir_boleto(){
	remover('boleto',$_POST[id]);
	index();
	exit;

}

function modifica_boleto(){
	global $a_mod;

	$a_mod = carregar('boleto',$_POST[id]);
	$a_mov = carregar('movimento',$a[bole_nb_movimento]);

	layout_modifica();
	exit;

}

function atualizar_boleto(){
	$a_bol = carregar('boleto',$_POST[id]);
	$a_mov = carregar('movimento',$a_mod[bole_nb_movimento]);

	$campos=array(bole_tx_vencimento,bole_tx_valordoc,bole_tx_obs);
	$valores=array(data($_POST[vencimento]),valor($_POST[valor]),$_POST[obs]);
	atualizar('boleto',$campos,$valores,$_POST[id]);

	index();
	exit;

}

function cadastra_recebimento(){

	for($i=0;$i<$_POST[parcela];$i++){
		$_POST[vencimento][]=$_POST['vencimento'.$i];
		$_POST[valor][]=$_POST['valor'.$i];
		$_POST[obs][]=$_POST['obs'.$i];
	}


	$campos=array(movi_nb_entidade,movi_nb_planoconta,movi_tx_tipo,movi_tx_data,movi_tx_parcelas,movi_tx_status,movi_nb_forma);
	$valores=array($_POST[entidade],$_POST[plano],$_POST[tipo],date('Y-m-d'),$_POST[parcela],ativo,$_POST[forma]);
	inserir('movimento',$campos,$valores);
	$id_mov=ultimo_reg('movimento');


	if($_POST[forma]>'1'){
		$sql = query("SELECT bole_tx_nossoNum FROM boleto WHERE bole_nb_forma = '$_POST[forma]' ORDER BY bole_tx_nossoNum+0 DESC LIMIT 1");
		$a = carrega_array($sql);
		$nossoNum = $a[0];
	}

	for($i=0;$i<$_POST[parcela];$i++){
		$nossoNum = $nossoNum+1;
		$campos=array(bole_nb_movimento,bole_tx_vencimento,bole_tx_valordoc,bole_tx_parcela,bole_tx_status,bole_tx_obs,bole_tx_dataCadastro,bole_nb_userCadastro,bole_tx_nossoNum,bole_nb_forma,bole_tx_previsao);
		$valores=array($id_mov,data($_POST[vencimento][$i]),valor($_POST[valor][$i]),($i+1),ativo,($i+1)."/$_POST[parcela] ".$_POST[obs][$i],date("Y-m-d H:i:s"),$_SESSION[user_nb_id],$nossoNum,$_POST[forma],data($_POST[vencimento][$i]));
		inserir('boleto',$campos,$valores);

	}

	index();
	exit;

}


function cadastra_pagamento(){

	for($i=0;$i<$_POST[parcela];$i++){
		$_POST[vencimento][]=$_POST['vencimento'.$i];
		$_POST[valor][]=$_POST['valor'.$i];
		$_POST[obs][]=$_POST['obs'.$i];
	}	

	$campos=array(movi_nb_entidade,movi_nb_planoconta,movi_tx_tipo,movi_tx_data,movi_tx_parcelas,movi_tx_status,movi_nb_forma);
	$valores=array($_POST[entidade],$_POST[plano],$_POST[tipo],date('Y-m-d'),$_POST[parcela],ativo,$_POST[forma]);
	inserir('movimento',$campos,$valores);
	$id_mov=ultimo_reg('movimento');


	for($i=0;$i<$_POST[parcela];$i++){

		$campos=array(bole_nb_movimento,bole_tx_vencimento,bole_tx_valordoc,bole_tx_parcela,bole_tx_status,bole_tx_obs,bole_tx_dataCadastro,bole_nb_userCadastro,bole_nb_forma,bole_tx_previsao);
		$valores=array($id_mov,data($_POST[vencimento][$i]),valor($_POST[valor][$i]),($i+1),ativo,($i+1)."/$_POST[parcela] ".$_POST[obs][$i],date("Y-m-d H:i:s"),$_SESSION[user_nb_id],$_POST[forma],data($_POST[vencimento][$i]));
		inserir('boleto',$campos,$valores);

	}

	index();
	exit;

}


function cadastra_fobo(){
	
	$campos=array(fobo_nb_boleto,fobo_tx_valor,fobo_nb_forma,fobo_tx_status,fobo_tx_data,fobo_nb_user,fobo_tx_obs);
	$valores=array($_POST[id],valor($_POST[valor_pg]),$_POST[forma_pg],'ativo',date("Y-m-d H:i:s"),$_SESSION[user_nb_id],$_POST[obs]);
	inserir('fobo',$campos,$valores);

	atualizar('boleto',array('bole_tx_status','bole_tx_valor','bole_tx_data'),array('ativo','',''),$_POST[id]);

	layout_baixa();
	exit;


}

function cadastra_finaliza(){

	$sql_soma=query("SELECT SUM(fobo_tx_valor) FROM fobo WHERE fobo_nb_boleto = '$_POST[id]' AND fobo_tx_status != 'inativo'");
	$a_soma = carrega_array($sql_soma);
	
	$campos=array(bole_tx_data,bole_tx_valor,bole_tx_status,bole_tx_dataAtualiza,bole_nb_userAtualiza);
	$valores=array(date('Y-m-d'),$a_soma[0],'encerrado',date("Y-m-d"),$_SESSION[user_nb_id]);
	atualizar('boleto',$campos,$valores,$_POST[id]);

	index();
	exit;


}



function layout_pagamento(){

	if($_GET[t]=='Receita')
		cabecalho('Contas a Receber');
	elseif($_GET[t]=='Despesa')
		cabecalho("Contas a Pagar");

	$c[]=combo_net('Entidade:','entidade',$_POST[entidade],4,'entidade');
	// $c[]=combo_bd('Entidade:','entidade',$_POST[entidade],4,'entidade');
	$c[]=combo_bd('Plano de Contas:','plano',$_POST[plano],3,'planoconta'," AND plan_tx_tipo = 'Despesa'");
	$c[]=combo_bd('Forma:','forma',$_POST[forma],2,'forma',''," order by form_tx_nome DESC");
	$c[]=combo('Tipo:','tipo',$_POST[busca_tipo],2,array('Despesa'));
	$c[]=campo('Parcelas:','parcela','1',1,'','onKeyUp="parcelas(this.value);"');


	$botao[] = botao('Gravar','cadastra_pagamento');
	$botao[] = botao('Voltar','index');

	abre_form('Dados do Pagamento');
	linha_form($c);

	for($i=0;$i<24;$i++){
		$c2[]=campo(($i+1).'° Vencimento:','vencimento'.$i,data($a_mod[bole_tx_vencimento]),2,MASCARA_DATA);
		$c2[]=campo(($i+1).'° Valor:','valor'.$i,'',3,MASCARA_VALOR);
		$c2[]=campo(($i+1).'° Observação:','obs'.$i,'',6);

		if($i>0)
			echo "<div id='div".$i."' style='display: none'>";
		else
			echo "<div id='div0' style='display: block'>";

		linha_form($c2);
		unset($c2);
		echo "</div>";
	}


	fecha_form($botao);

	rodape();


	?>

	<script>
		function parcelas(par){

			for (i = 1; i != 12; i++) {
				if(i < par)
					window.document.getElementById('div'+i).style.display='block';
				else
					window.document.getElementById('div'+i).style.display='none';

			}

		}
	</script>


	<?



}



function layout_modifica(){
	global $a_mod;
	$a_mov = carregar('movimento',$a_mod[bole_nb_movimento]);
	$a_ent = carregar('entidade',$a_mov[movi_nb_entidade]);
	$a_form = carregar('forma',$a_mov[movi_nb_forma]);
	$a_pla=carregar('planoconta',$a_mov[movi_nb_planoconta]);

	if($_GET[t]=='Receita')
		cabecalho('Contas a Receber');
	elseif($_GET[t]=='Despesa')
		cabecalho("Contas a Pagar");

	$c[]=texto('Entidade:',$a_ent[enti_tx_nome],4);
	$c[]=texto('Plano de Contas:',$a_pla[plan_tx_nome],3);
	$c[]=texto('Forma:',$a_form[form_tx_nome],2);
	$c[]=texto('Tipo:',$a_mov[movi_tx_tipo],2);
	
	$c2[]=campo('Vencimento:','vencimento',data($a_mod[bole_tx_vencimento]),2,MASCARA_DATA);
	$c2[]=campo('Valor:','valor',valor($a_mod[bole_tx_valordoc]),3,MASCARA_VALOR);
	$c2[]=campo('Observação:','obs',$a_mod[bole_tx_obs],6);


	$botao[] = botao('Gravar','atualizar_boleto','id',$_POST[id]);
	$botao[] = botao('Voltar','index');

	abre_form('Dados do Movimento');
	linha_form($c);

	linha_form($c2);

	if($a_mod[bole_tx_vencimento]<date('Y-m-d') && $a_mod[bole_tx_status] == 'ativo'){
		$data1 = new DateTime ($a_mod[bole_tx_vencimento]);
		$data2 = new DateTime (date("Y-m-d"));

		$intervalo = $data1 -> diff($data2);
		$dias = $intervalo->days;

		$c3[]=texto('Dias Atraso:',$dias,2);

		$a_form = carregar('forma',$a_mov[movi_nb_forma]);

		$multa = ($a_form[form_tx_multa]*$a_mod[bole_tx_valordoc])/100;
		$juros = $dias*($a_form[form_tx_juros]*$a_mod[bole_tx_valordoc])/100;

		$c3[]=texto('Forma de Pagamento',$a_form[form_tx_nome],2);
		$c3[]=texto("Multa (".valor($a_form[form_tx_multa])."%)",valor($multa),2);
		$c3[]=texto("Juros/dia (".valor($a_form[form_tx_juros])."%)",valor($juros),2);
		$c3[]=texto("<b>VALOR FINAL</b>",'<b>'.valor($a_mod[bole_tx_valordoc]+$juros+$multa).'</b>',2);
		fieldset('BOLETO EM ATRASO');
		linha_form($c3);
	}


	fecha_form($botao);

	rodape();


}


function layout_receita(){

	if($_GET[t]=='Receita')
		cabecalho('Contas a Receber');
	elseif($_GET[t]=='Despesa')
		cabecalho("Contas a Pagar");

	$c[]=combo_net('Entidade:','entidade',$_POST[entidade],4,'entidade');
	// $c[]=combo_bd('Entidade:','entidade',$_POST[entidade],4,'entidade');
	$c[]=combo_bd('Plano de Contas:','plano',$_POST[plano],3,'planoconta'," AND plan_tx_tipo = 'Receita'");
	$c[]=combo_bd('Forma:','forma',$_POST[forma],2,'forma',''," order by form_tx_nome DESC");
	$c[]=combo('Tipo:','tipo',$_POST[busca_tipo],2,array('Receita'));
	$c[]=campo('Parcelas:','parcela','1',1,'','onKeyUp="parcelas(this.value);"');



	$botao[] = botao('Gravar','cadastra_recebimento');
	$botao[] = botao('Voltar','index');

	abre_form('Dados do Recebimento');
	linha_form($c);

	for($i=0;$i<24;$i++){
		$c2[]=campo(($i+1).'° Vencimento:','vencimento'.$i,data($a_mod[bole_tx_vencimento]),2,MASCARA_DATA);
		$c2[]=campo(($i+1).'° Valor:','valor'.$i,'',3,MASCARA_VALOR);
		$c2[]=campo(($i+1).'° Observação:','obs'.$i,'',6);

		if($i>0)
			echo "<div id='div".$i."' style='display: none'>";
		else
			echo "<div id='div0' style='display: block'>";

		linha_form($c2);
		unset($c2);
		echo "</div>";
	}


	fecha_form($botao);

	rodape();


	?>

	<script>
		function parcelas(par){

			for (i = 1; i != 12; i++) {
				if(i < par)
					window.document.getElementById('div'+i).style.display='block';
				else
					window.document.getElementById('div'+i).style.display='none';

			}

		}
	</script>


<?



}

function excluir_fobo(){
	$a=carregar('fobo',$_POST[id]);
	remover('fobo',$_POST[id]);

	$_POST[id] = $a[fobo_nb_boleto];

	atualizar('boleto',array('bole_tx_status','bole_tx_valor','bole_tx_data'),array('ativo','',''),$_POST[id]);

	layout_baixa();
	exit;
}

function layout_baixa(){

	if($_GET[t]=='Receita')
		cabecalho('Contas a Receber');
	elseif($_GET[t]=='Despesa')
		cabecalho("Contas a Pagar");

	$a_bol=carregar('boleto',$_POST[id]);
	$a_mov=carregar('movimento',$a_bol[bole_nb_movimento]);
	$a_ent=carregar('entidade',$a_mov[movi_nb_entidade]);
	$a_pla=carregar('planoconta',$a_mov[movi_nb_planoconta]);


	if($a_bol[bole_tx_data]=='' || $a_bol[bole_tx_data]=='0000-00-00')
		$a_bol[bole_tx_data]=date("Y-m-d");

	if($a_bol[bole_tx_valor]=='')
		$a_bol[bole_tx_valor]=$a_bol[bole_tx_valordoc];

	$sql_soma=query("SELECT SUM(fobo_tx_valor) FROM fobo WHERE fobo_nb_boleto = '$a_bol[bole_nb_id]' AND fobo_tx_status != 'inativo'");
	$a_soma = carrega_array($sql_soma);

	$t[]=texto('Código:',$a_bol[bole_nb_id],1);
	$t[]=texto('Entidade:',$a_ent[enti_tx_nome],3);
	$t[]=texto('Descrição:',$a_bol[bole_tx_obs],3);
	$t[]=texto('Plano Conta:',$a_pla[plan_tx_nome],2);
	$t[]=texto('Tipo:',$a_mov[movi_tx_tipo],1);
	$t[]=texto('Situação:',ucfirst($a_bol[bole_tx_status]),2);
	$t[]=texto('Vencimento:',data($a_bol[bole_tx_vencimento]),2);
	$t[]=texto('Valor:',valor($a_bol[bole_tx_valordoc]),2);
	$t[]=texto('Valor Pago:',valor($a_soma[0],1),2);
	$t[]=texto('Valor Pendente:',valor($a_bol[bole_tx_valordoc]-$a_soma[0],1),2);
	$t[]=texto('Situação:',ucfirst($a_bol[bole_tx_status]),2);

	$c[]=combo_bd('Forma','forma_pg',$a_bol[bole_nb_forma],3,'forma',''," order by form_tx_nome DESC");
	$c[]=campo('Data Pagamento','data_pg',data($a_bol[bole_tx_data]),2,MASCARA_DATA);
	$c[]=campo('Valor Pago','valor_pg',valor($a_bol[bole_tx_valordoc]-$a_soma[0]),2,MASCARA_VALOR);
	$c[]=campo('Observação','obs','',5);

	$botao[] = botao('Gravar','cadastra_fobo','id',$_POST[id]);
	$botao[] = botao('Finalizar','cadastra_finaliza','id',$_POST[id]);
	$botao[] = botao('Voltar','index');

	abre_form('Dados do Boleto');
	linha_form($t);

	if($a_bol[bole_tx_vencimento]<date('Y-m-d') && $a_bol[bole_tx_status] == 'ativo'){
		$data1 = new DateTime ($a_bol[bole_tx_vencimento]);
		$data2 = new DateTime (date("Y-m-d"));

		$intervalo = $data1 -> diff($data2);
		$dias = $intervalo->days;

		$c3[]=texto('Dias Atraso:',$dias,2);

		$a_form = carregar('forma',$a_mov[movi_nb_forma]);

		$multa = ($a_form[form_tx_multa]*$a_bol[bole_tx_valordoc])/100;
		$juros = $dias*($a_form[form_tx_juros]*$a_bol[bole_tx_valordoc])/100;

		$c3[]=texto('Forma de Pagamento',$a_form[form_tx_nome],2);
		$c3[]=texto("Multa (".valor($a_form[form_tx_multa])."%)",valor($multa),2);
		$c3[]=texto("Juros/dia (".valor($a_form[form_tx_juros])."%)",valor($juros),2);
		$c3[]=texto("<b>VALOR FINAL</b>",'<b>'.valor($a_bol[bole_tx_valordoc]+$juros+$multa).'</b>',2);
		fieldset('BOLETO EM ATRASO');
		linha_form($c3);
	}


	fieldset('Dados do Pagamento');
	linha_form($c);
	fecha_form($botao);


	$sql="SELECT * FROM fobo,forma,user WHERE fobo_nb_boleto = '$a_bol[bole_nb_id]' AND user_nb_id = fobo_nb_user AND fobo_nb_forma = form_nb_id AND fobo_tx_status != 'inativo'";
	
	$cab=array('CÓD.','DATA','FORMA','VALOR','OBSERVAÇÃO','');
	$val=array('fobo_nb_id','data(fobo_tx_data,1)','form_tx_nome','valor(fobo_tx_valor)','fobo_tx_obs','icone_excluir(fobo_nb_id,excluir_fobo)');

	grid($sql,$cab,$val);

	rodape();

}


function layout_imprimir(){

	cabecalho('Contas a Receber','',1);

//	print_r($_POST);

	if($_POST[busca_codigo])
		$extra.=" AND bole_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_entidade]){
		$extra.=" AND movi_nb_entidade = '$_POST[busca_entidade]'";
		$a_ent=carregar('entidade',$_POST[busca_entidade]);
	}
	if($_POST[busca_descricao])
		$extra.=" AND bole_tx_obs LIKE '%$_POST[busca_descricao]%'";
	if($_POST[busca_plano]){
		$extra.=" AND movi_nb_planoconta = '$_POST[busca_plano]'";
		$a_pla=carregar('planoconta',$_POST[busca_plano]);
	}
	if($_POST[busca_tipo])
		$extra.=" AND movi_tx_tipo = '$_POST[busca_tipo]'";
	if($_POST[busca_situacao])
		$extra.=" AND bole_tx_status = '$_POST[busca_situacao]'";

	if($_POST[busca_filtro]=='Pagamento'){
		if($_POST[busca_data1])
			$extra .= " AND bole_tx_vencimento >= '".data($_POST[busca_data1])."'";
		if($_POST[busca_data2])
			$extra .= " AND bole_tx_vencimento <= '".data($_POST[busca_data2])."'";
	}else{
		if($_POST[busca_data1])
			$extra .= " AND bole_tx_data >= '".data($_POST[busca_data1])."'";
		if($_POST[busca_data2])
			$extra .= " AND bole_tx_data <= '".data($_POST[busca_data2])."'";

	}


	$c[]=texto('Código:',$_POST[busca_codigo],2);
	$c[]=texto('Entidade:',$_POST[busca_entidade],3);
	$c[]=texto('Descrição:',$_POST[busca_descricao],3);
	$c[]=texto('Plano Conta:',$_POST[busca_plano],2);
	$c[]=texto('Tipo:',$_POST[busca_tipo],2);
	$c2[]=texto('Situação:',$_POST[busca_situacao],2);
	$c2[]=texto('Data Início:',$_POST[busca_data1],2);
	$c2[]=texto('Data Fim:',$_POST[busca_data2],2);
	$c2[]=texto('Filtro Data:',$_POST[busca_filtro],2);



	

	$sql=mysql_query("SELECT * FROM boleto,entidade,movimento,planoconta WHERE movi_nb_planoconta = plan_nb_id AND  bole_nb_movimento = movi_nb_id AND movi_nb_entidade = enti_nb_id AND bole_tx_status != 'inativo' $extra ORDER BY bole_nb_id DESC") or die(mysql_error());

	$cabecalho[]=array('CÓDIGO','ENTIDADE','DESCRIÇÃO','PLANO&nbsp;CONTA','VENCIMENTO','VALOR','DATA&nbsp;PG.','VALOR&nbsp;PG');
	$cabecalho[]=array(7,'',20,10,10,8,8,8);


	while($a=carrega_array($sql)){
		
		if($a[movi_tx_tipo]=='Despesa')
			$cor='<font color="red">';
		else
			$cor='<font color="blue">';


		$valores[]=array($cor.$a[bole_nb_id],$cor.$a[enti_tx_nome],$cor.$a[bole_tx_obs],$cor.$a[plan_tx_nome],$cor.data($a[bole_tx_vencimento]),$cor.
				valor($a[bole_tx_valordoc]),$cor.data($a[bole_tx_data]),$cor.valor($a[bole_tx_valor]));


		if($a[plan_tx_tipo]=='Despesa'){
			$a[bole_tx_valor]=$a[bole_tx_valor]*(-1);
			$a[bole_tx_valordoc]=$a[bole_tx_valordoc]*(-1);
		}

		$total_valor_pg+=$a[bole_tx_valor];
		$total_valor+=$a[bole_tx_valordoc];

	}

	$valores[]=array('','','','','<b>TOTAL:</b>',"<b>".valor($total_valor)."</b>",'',"<b>".valor($total_valor_pg)."</b>");

	abre_form('Filtro de Busca');
	linha_form($c);
	linha_form($c2);
	fecha_form();

	grid('',$cabecalho,$valores,0,0);

	rodape();

	
}



function index(){
	if($_GET[t]=='Receita')
		cabecalho('Contas a Receber');
	elseif($_GET[t]=='Despesa')
		cabecalho("Contas a Pagar");

//	print_r($_POST);

	if($_GET[t])
		$extra.=" AND movi_tx_tipo = '$_GET[t]'";

	if($_POST[busca_codigo])
		$extra.=" AND bole_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_entidade])
		$extra.=" AND enti_tx_nome LIKE '%$_POST[busca_entidade]%'";
	if($_POST[busca_descricao])
		$extra.=" AND bole_tx_obs LIKE '%$_POST[busca_descricao]%'";
	if($_POST[busca_plano])
		$extra.=" AND movi_nb_planoconta = '$_POST[busca_plano]'";
	if($_POST[busca_tipo])
		$extra.=" AND movi_tx_tipo = '$_POST[busca_tipo]'";
	if($_POST[busca_situacao])
		$extra.=" AND bole_tx_status = '$_POST[busca_situacao]'";

	if($_POST[busca_filtro]=='Pagamento'){
		if($_POST[busca_data1])
			$extra .= " AND bole_tx_data >= '".data($_POST[busca_data1])."'";
		if($_POST[busca_data2])
			$extra .= " AND bole_tx_data <= '".data($_POST[busca_data2])."'";
	}else{
		if($_POST[busca_data1])
			$extra .= " AND bole_tx_vencimento >= '".data($_POST[busca_data1])."'";
		if($_POST[busca_data2])
			$extra .= " AND bole_tx_vencimento <= '".data($_POST[busca_data2])."'";

	}


	$c[]=campo('Código:','busca_codigo',$_POST[busca_codigo],2);
	$c[]=campo('Entidade:','busca_entidade',$_POST[busca_entidade],3);
	$c[]=campo('Descrição:','busca_descricao',$_POST[busca_descricao],3);
	$c[]=combo_bd('!Plano Conta:','busca_plano',$_POST[busca_plano],2,'planoconta');
	$c[]=combo('Situação:','busca_situacao',$_POST[busca_situacao],2,array('','Ativo','Encerrado'));
	$c[]=campo('Data Início:','busca_data1',$_POST[busca_data1],2,MASCARA_DATA);
	$c[]=campo('Data Fim:','busca_data2',$_POST[busca_data2],2,MASCARA_DATA);
	$c[]=combo('Filtro Data:','busca_filtro',$_POST[busca_filtro],2,array('Vencimento','Pagamento'));


	$botao[] = botao('Buscar','index');
	if($_GET[t]=='Despesa')
		$botao[] = botao('Inserir Pagamento','layout_pagamento');
	if($_GET[t]=='Receita')
		$botao[] = botao('Inserir Recebimento','layout_receita');


	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);


	$sql="SELECT bole_nb_id,enti_tx_nome,bole_tx_obs,plan_tx_nome,bole_tx_vencimento,bole_tx_valordoc,bole_tx_data,bole_tx_valor 
	FROM boleto,entidade,movimento,planoconta 
	WHERE movi_nb_planoconta = plan_nb_id AND bole_nb_movimento = movi_nb_id AND movi_nb_entidade = enti_nb_id AND bole_tx_status != 'inativo' $extra";
	
	$cab=array('CÓD.','ENTIDADE','DESCRIÇÃO','PLANO CONTA','VENCIMENTO','VALOR','DATA PG.','VALOR PG','','','');
	$val=array('bole_nb_id','enti_tx_nome','bole_tx_obs','plan_tx_nome','data(bole_tx_vencimento)','valor(bole_tx_valordoc)','data(bole_tx_data)','valor(bole_tx_valor)','icone_modificar(bole_nb_id,layout_baixa,,,,glyphicon glyphicon-usd)','icone_modificar(bole_nb_id,modifica_boleto)','icone_excluir(bole_nb_id,excluir_boleto)');

	grid($sql,$cab,$val);

	rodape();
	

}

?>

