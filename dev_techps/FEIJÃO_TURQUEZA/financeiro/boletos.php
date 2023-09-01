<?php
include "../conecta.php";


function exclui_aluno(){
	remover('entidade',$_POST[id]);

	index();
	exit;
}

function modifica_aluno(){
	global $a_mod;

	$a_mod = carregar('entidade',$_POST[id]);

	layout_aluno();
	exit;
}


function cadastra_aluno(){

	$campos = array(enti_tx_nome,enti_nb_turma,enti_tx_cpf,enti_tx_nascimento,enti_tx_cel,enti_tx_fone,enti_tx_email,enti_tx_endereco,enti_tx_numero,
		enti_tx_bairro,enti_nb_cidade,enti_tx_nomeResponsavel,enti_tx_cpfResponsavel,enti_tx_tipo,enti_tx_status);
	$valores = array($_POST[nome],$_POST[turma],$_POST[cpf],data($_POST[nascimento]),$_POST[celular],$_POST[telefone],$_POST[email],$_POST[endereco],$_POST[numero],
		$_POST[bairro],$_POST[cidade],$_POST[nomeResponsavel],$_POST[cpfResponsavel],'Aluno','ativo');
	
	if(!$_POST[id]){
		inserir('entidade',$campos,$valores);
		global $a_mod;
		$a_turma=carregar('turma',$_POST[turma]);
		$a_mod[enti_nb_turma] = $_POST[turma];
		set_status("$_POST[nome] cadastrado na turma $a_turma[turm_tx_nome]");
		layout_aluno();
	}else{
		atualizar('entidade',$campos,$valores,$_POST[id]);
		index();
	}
		exit;

}

function layout_atualiza(){
	global $a_mod;

	$a_bol = carregar('boleto',$_POST[id]);
	$a_form = carregar('forma',$a_bol[bole_nb_forma]);
	$a_mov = carregar('movimento',$a_bol[bole_nb_movimento]);
	$a_ent = carregar('entidade',$a_mov[movi_nb_entidade]);
	$a_turm = carregar('turma',$a_ent[enti_nb_turma]);
	$a_cida = carregar('cidade',$a_ent[enti_nb_cidade]);
	
	$data2 = new DateTime ($a_bol[enti_tx_nascimento]);
	$data1 = new DateTime (date("Y-m-d"));

	$intervalo = $data1 -> diff($data2);

	$atraso = "{$intervalo->y} anos, {$intervalo->m} meses e {$intervalo->d} dias";

	cabecalho("Boletos");

	$c1[]=texto('Turma',$a_turm[turm_tx_nome],4);
	$c1[]=texto('Nome',$a_ent[enti_tx_nome],4);
	$c1[]=texto('CPF',$a_ent[enti_tx_cpf],2);
	$c1[]=texto('Nascimento',data($a_ent[enti_tx_nascimento]),2);

	$c1[]=texto('Código',$a_bol[bole_tx_nossoNum],2);
	$c1[]=texto('Nosso Núm.',$a_bol[bole_tx_nossoNum],2);
	$c1[]=texto('Valor',$a_bol[bole_tx_nossoNum],2);
	$c1[]=texto('Vencimento',$a_bol[bole_tx_nossoNum],2);
	$c1[]=texto('Dias em Atraso',$atraso,2);
	$c1[]=texto('Forma',$a_form[form_tx_nome],2);
	$c1[]=texto('Forma',$a_form[form_tx_nome],2);

	
	$b[]=botao('Imprimir Todos','','id',$a_mod[enti_nb_id],' formtarget=_blank formaction=../boletophp/imprime_carne_santander.php');
	$b[]=botao('Voltar','modifica_aluno','id',$a_ent[0]);

	abre_form('Dados do Aluno');
	linha_form($c1);
	linha_form($c2);
	linha_form($c3);
	fecha_form($b);
	
	$sql = "SELECT * FROM boleto, movimento, entidade WHERE bole_tx_status !=  'inativo' AND bole_nb_forma > '1' AND movi_nb_planoconta = '1' AND movi_nb_entidade = enti_nb_id  AND bole_nb_movimento = movi_nb_id AND enti_nb_id = '$a_mod[enti_nb_id]' AND movi_nb_entidade = '$a_mod[enti_nb_id]'";
	$cab = array('CÓDIGO','NOSSO NÚM.','OBSERVAÇÃO','PARCELA','VENCIMENTO','VALOR','SITUAÇÃO','','');
	$val = array('bole_nb_id','bole_tx_nossoNum','bole_tx_obs','bole_tx_parcela','data(bole_tx_vencimento)',
		'valor(bole_tx_valordoc)','ucfirst(bole_tx_status)',
		// 'icone_modificar(bole_nb_id,layout_atualiza)',
		'icone_modificar(bole_nb_id,imprime_boleto_individual,,,_blank,glyphicon glyphicon-list-alt,../boletophp/boleto_santander.php)');

	grid($sql,$cab,$val,'','',0);

	rodape();

}

function abre_boleto_individual(){

	$a=carregar('boleto',$_POST[id]);

	if($a[bole_nb_forma] == 3)
		$acao = '../boletophp/boleto_santander.php';
	elseif($a[bole_nb_forma] == 4)
		$acao = '../boletophp/boleto_bnb.php';

	?>
	<form method="post" name="form_boleto" action="<?=$acao?>">
		<input type="hidden" name="id" value="<?=$_POST[id]?>" />
		<input type="hidden" name="acao" value="imprime_boleto_individual" />
	</form>
	<script type="text/javascript">
		document.form_boleto.submit();
	</script>
	<?

	exit;
}

function layout_aluno(){
	global $a_mod;

	$a_turm = carregar('turma',$a_mod[enti_nb_turma]);
	$a_cida = carregar('cidade',$a_mod[enti_nb_cidade]);

	cabecalho("Boletos");

	$c1[]=texto('Turma',$a_turm[turm_tx_nome],4);
	$c1[]=texto('Nome',$a_mod[enti_tx_nome],4);
	$c1[]=texto('CPF',$a_mod[enti_tx_cpf],2);
	$c1[]=texto('Nascimento',data($a_mod[enti_tx_nascimento]),2);
	$c2[]=texto('Celular',$a_mod[enti_tx_cel],2);
	$c2[]=texto('Telefone',$a_mod[enti_tx_fone],2);
	$c2[]=texto('E-mail',$a_mod[enti_tx_email],3);
	$c2[]=texto('Endereço',$a_mod[enti_tx_endereco],5);
	$c3[]=texto('Número',$a_mod[enti_tx_numero],1);
	$c3[]=texto('Bairro',$a_mod[enti_tx_bairro],2);
	$c3[]=texto('Cidade',$a_cida[cida_tx_nome],2);
	$c3[]=texto('Nome Responsável',$a_mod[enti_tx_nomeResponsavel],3);
	$c3[]=texto('CPF Responsável',$a_mod[enti_tx_cpfResponsavel],2);
	
	// $b[]=botao('Imprimir Remessa','','id',$a_mod[enti_nb_id],' formtarget=_blank formaction=../boletophp/imprime_carne_santander.php');
	
	$sql_forma = query("SELECT bole_nb_forma FROM boleto, movimento, entidade WHERE bole_tx_status !=  'inativo' AND bole_nb_forma > '1' AND movi_nb_planoconta = '1' AND movi_nb_entidade = enti_nb_id  AND bole_nb_movimento = movi_nb_id AND enti_nb_id = '$a_mod[enti_nb_id]' AND movi_nb_entidade = '$a_mod[enti_nb_id]' LIMIT 1");
	$a_forma = carrega_array($sql_forma);
	
	if($a_forma[0] == 3)
		$b[] = botao('Imprimir Carnê','','id',$a_mod[enti_nb_id],' formtarget=_blank formaction=../boletophp/imprime_carne_santander.php');
	else
		$b[] = botao('Imprimir Carnê','','id',$a_mod[enti_nb_id],' formtarget=_blank formaction=../boletophp/imprime_carne_bnb.php');

	$b[]=botao('Voltar','index');

	abre_form('Dados do Aluno');
	linha_form($c1);
	linha_form($c2);
	linha_form($c3);
	fecha_form($b);
	

	$sql = "SELECT * FROM boleto, movimento, entidade WHERE bole_tx_status !=  'inativo' AND bole_nb_forma > '1' AND movi_nb_planoconta = '1' AND movi_nb_entidade = enti_nb_id  AND bole_nb_movimento = movi_nb_id AND enti_nb_id = '$a_mod[enti_nb_id]' AND movi_nb_entidade = '$a_mod[enti_nb_id]'";
	$cab = array('CÓDIGO','NOSSO NÚM.','OBSERVAÇÃO','PARCELA','VENCIMENTO','VALOR','SITUAÇÃO','');
	$val = array('bole_nb_id','bole_tx_nossoNum','bole_tx_obs','bole_tx_parcela','data(bole_tx_vencimento)',
		'valor(bole_tx_valordoc)','ucfirst(bole_tx_status)',
		// 'icone_modificar(bole_nb_id,layout_atualiza)',
		// 'icone_modificar(bole_nb_id,imprime_boleto_individual,,,_blank,glyphicon glyphicon-list-alt,../boletophp/boleto_santander.php)');
		'icone_modificar(bole_nb_id,abre_boleto_individual,,,_blank,glyphicon glyphicon-list-alt)');

	grid($sql,$cab,$val,0,'',0);

	rodape();

}


function index(){
	cabecalho("Boletos");

	if($_POST[busca_codigo])
		$extra .=" AND enti_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_nome])
		$extra .=" AND enti_tx_nome LIKE '%$_POST[busca_nome]%'";
	if($_POST[busca_tipo])
		$extra .=" AND enti_tx_tipo = '$_POST[busca_tipo]'";
	if($_POST[busca_turma])
		$extra .=" AND enti_nb_turma = '$_POST[busca_turma]'";

	$c[]=campo('Código','busca_codigo',$_POST[busca_codigo],1);
	$c[]=campo('Nome','busca_nome',$_POST[busca_nome],4);
	$c[]=combo_bd('!Turma','busca_turma',$_POST[busca_turma],4,'turma');
	$c[]=campo('CPF','busca_cpf',$_POST[busca_cpf],3,'MASCARA_CPF');

	$b[]=botao('Buscar','index');
	// $b[]=botao('Inserir','layout_aluno');

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT enti_nb_id,enti_tx_nome,turm_tx_nome FROM boleto,movimento,entidade,turma WHERE enti_tx_status != 'inativo' AND movi_nb_id = bole_nb_movimento 
	AND movi_nb_forma > '1' AND bole_nb_forma > '1' AND movi_nb_planoconta='1' AND movi_nb_entidade = enti_nb_id AND turm_nb_id = enti_nb_turma 
	AND enti_tx_tipo = 'Aluno' $extra GROUP BY movi_nb_entidade";
	$cab = array('CÓDIGO','NOME','TURMA','');
	$val = array('enti_nb_id','enti_tx_nome','turm_tx_nome','icone_modificar(enti_nb_id,modifica_aluno)');
	grid($sql,$cab,$val,'','',0);

	rodape();

}


?>

