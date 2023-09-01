<?php
include "conecta.php";


function exclui_profissional(){
	remover('profissional',$_POST[id]);

	index();
	exit;
}


function excluir_arquivo_profissional(){
	unlink("arquivos/profissional/$_POST[id_arquivo]/$_POST[nome_arquivo]");
	$_POST[id]=$_POST[id_arquivo];
	modifica_profissional();
	exit;
}

function modifica_profissional(){
	global $a_mod;

	$a_mod = carregar('profissional',$_POST[id]);

	layout_profissional();
	exit;
}


function cadastra_profissional(){
	global $a_mod;

	$a_dent = carregar('profissional',$_POST[id]);

	if($_POST[senha]!='' && $_POST[senha2]==''){
		set_status("ATENÇÃO: Preecha o campo senha e confirme-a!");
		layout_profissional();
		exit;
	}
	if($_POST[senha]!=$_POST[senha2]){
		set_status("ATENÇÃO: As senhas informadas não conferem!");
		layout_profissional();
		exit;
	}

	$campos  = array(prof_tx_nome);
	$valores = array($_POST[nome]);
	
	if(!$_POST[id]){
		array_push($campos, prof_nb_userCadastro,prof_tx_dataCadastro,prof_tx_status);
		array_push($valores, $_SESSION[user_nb_id],date("Y-m-d"),'ativo');

		$id=inserir('profissional',$campos,$valores);

		$campos2 = array(user_tx_nome,user_tx_login,user_tx_nivel,user_tx_status);
		$valores2 = array($_POST[nome],$_POST[login],$_POST[nivel],'ativo');
		$id_user=inserir('user',$campos2,$valores2);

		$campos3 = array(enti_tx_nome,enti_tx_tipo,enti_nb_userCadastro,enti_tx_dataCadastro,enti_tx_status);
		$valores3 = array($_POST[nome],'Profissional',$_SESSION[user_nb_id],date('Y-m-d'),'ativo');
		$id_entidade=inserir('entidade',$campos3,$valores3);

	}else{
		atualizar('profissional',$campos,$valores,$_POST[id]);
		$id=$_POST[id];

		$campos2 = array(user_tx_nome,user_tx_login,user_tx_nivel,user_tx_status);
		$valores2 = array($_POST[nome],$_POST[login],$_POST[nivel],'ativo');
		atualizar('user',$campos2,$valores2,$a_dent[prof_nb_user]);

		$campos3 = array(enti_tx_nome,enti_tx_tipo,enti_tx_status);
		$valores3 = array($_POST[nome],'Profissional','ativo');
		atualizar('entidade',$campos3,$valores3,$a_dent[prof_nb_entidade]);

		$id_user = $a_dent[prof_nb_user];
		$id_entidade = $a_dent[prof_nb_entidade];
	}

	atualizar('profissional',array(prof_nb_user,prof_nb_entidade),array($id_user,$id_entidade),$id);

	if($_POST[senha]!='' && $_POST[senha2]!=''){
		atualizar('user',array(user_tx_senha),array(md5($_POST[senha])),$id_user);
	}


	$_POST[id]=$id;
	index();
	exit;

}

function layout_profissional(){
	global $a_mod;
	cabecalho("Cadastro de Profissional");

	if($a_mod)
		$a_mod2 = carregar('user',$a_mod[prof_nb_user]);
	
	$c[]=campo('Nome','nome',$a_mod[prof_tx_nome],6);	

	$c1[]=campo('Login','login',$a_mod2[user_tx_login],3);
	$c1[]=combo('Nível','nivel',$a_mod2[user_tx_nível],3,array("Profissional"));
	$c1[]=campo_senha('Senha','senha',"",2);
	$c1[]=campo_senha('Confirmar Senha','senha2',"",2);

	$b[]=botao('Gravar','cadastra_profissional','id',$_POST[id]);
	$b[]=botao('Voltar','index');

	abre_form('Dados do Profissional');

	linha_form($c);
	echo "<br>";
	fieldset("Dado do Usuário");
	linha_form($c1);

	fecha_form($b);


	rodape();

}

function icone_excluir2($id,$acao,$campos='',$valores='',$target='',$icone='glyphicon glyphicon-remove',$msg='Deseja excluir o registro?'){
	$icone='class="'.$icone.'"';
	
	return "<a style='color:gray' onclick='javascript:remover_arquivo(\"$id\",\"$acao\",\"$campos\",\"$valores\",\"$target\",\"$msg\");' ><spam $icone></spam></a>";
	
}

function index(){
	cabecalho("Cadastro de Profissional");

	if($_POST[busca_codigo])
		$extra .=" AND prof_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_nome])
		$extra .=" AND prof_tx_nome LIKE '%$_POST[busca_nome]%'";
		
	$c[]=campo('Código','busca_codigo',$_POST[busca_codigo],1);
	$c[]=campo('Nome','busca_nome',$_POST[busca_nome],11);
		
	$b[]=botao('Buscar','index');
	$b[]=botao('Inserir','layout_profissional');

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT * FROM profissional WHERE prof_tx_status != 'inativo' $extra";
	$cab = array('CÓDIGO','NOME','','');
	$val = array('prof_nb_id','prof_tx_nome','icone_modificar(prof_nb_id,modifica_profissional)',
		'icone_excluir(prof_nb_id,exclui_profissional)');

	grid($sql,$cab,$val);

	rodape();

}


?>