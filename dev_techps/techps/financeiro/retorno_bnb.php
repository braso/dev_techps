<?php
include "../conecta.php";

function finaliza_retorno(){

	$sql=" SELECT *	FROM baixaretorno,boleto,movimento,entidade
		WHERE bole_nb_movimento = movi_nb_id AND movi_nb_entidade = enti_nb_id AND baix_nb_boleto = bole_nb_id 
		AND baix_tx_situacao != 'Duplicado' AND baix_tx_situacao != 'Já baixado' AND baix_tx_status = 'ativo' AND baix_nb_retornobnb = '$_POST[id]' ";
	while($a=carrega_array($sql)){
		atualizar('boleto',
			array(bole_tx_valor,bole_tx_data),
			array($a[baix_tx_valor],$a[baix_tx_dataPagamento]),
			$a[bole_nb_id]);
	}

	atualizar('retornobnb',array('reto_tx_status'),array('encerrado'),$_POST[id]);

	index();
	exit;



}

function layout_confirma(){
	$a=carregar('retornobnb',$_POST[id]);
	$a_use = carregar('user',$a[reto_nb_user]);
	cabecalho('Retorno BNB');

	$c[] = texto('Código',$a[reto_nb_id],1);
	$c[] = texto('Arquivo',$a[reto_tx_nome],3);
	$c[] = texto('Data do Arquivo',data($a[reto_tx_dataArquivo]),3);
	$c[] = texto('Data do Cadastro',data($a[reto_tx_data]),3);
	$c[] = texto('Usuário',$a_use[user_tx_login],2);

	if($a[reto_tx_status]!='encerrado')
		$b[] = botao('Finalizar','finaliza_retorno','id',$_POST[id]);

	$b[] = botao('Voltar','index');

	abre_form('Dados do Retorno');
	linha_form($c);
	fecha_form($b);

	//boletos encontrados
	$sql=" SELECT *	FROM baixaretorno,boleto,movimento,entidade WHERE bole_nb_movimento = movi_nb_id AND movi_nb_entidade = enti_nb_id AND baix_nb_boleto = bole_nb_id AND baix_tx_situacao = '' AND baix_tx_status = 'ativo' AND baix_nb_retornobnb = '$_POST[id]' ";
	$cab = array('CÓD','NOSSO NUM.','ALUNO','VENCIMENTO','VALOR','DATA PG.','VALOR PG.');
	$val = array('baix_nb_id','baix_tx_nossoNum','enti_tx_nome','data(bole_tx_vencimento)','valor(bole_tx_valordoc)',
		'data(baix_tx_dataPagamento)','valor(baix_tx_valor)');
	grid($sql,$cab,$val,'Boletos Encontrados');

	//Valor inferior
	$sql=" SELECT *	FROM baixaretorno,boleto,movimento,entidade WHERE bole_nb_movimento = movi_nb_id AND movi_nb_entidade = enti_nb_id AND baix_nb_boleto = bole_nb_id AND baix_tx_situacao = 'Valor inferior' AND baix_tx_status = 'ativo' AND baix_nb_retornobnb = '$_POST[id]' ";
	if(num_linhas(query($sql." LIMIT 1"))>0){
		$cab = array('CÓD','NOSSO NUM.','ALUNO','VENCIMENTO','VALOR','DATA PG.','VALOR PG.');
		$val = array('baix_nb_id','baix_tx_nossoNum','enti_tx_nome','data(bole_tx_vencimento)','valor(bole_tx_valordoc)',
			'data(baix_tx_dataPagamento)','valor(baix_tx_valor)');
		grid($sql,$cab,$val,'Valor Inferior');
	}

	//Valor superior
	$sql=" SELECT *	FROM baixaretorno,boleto,movimento,entidade WHERE bole_nb_movimento = movi_nb_id AND movi_nb_entidade = enti_nb_id AND baix_nb_boleto = bole_nb_id AND baix_tx_situacao = 'Valor superior' AND baix_tx_status = 'ativo' AND baix_nb_retornobnb = '$_POST[id]' ";
	if(num_linhas(query($sql." LIMIT 1"))>0){
		$cab = array('CÓD','NOSSO NUM.','ALUNO','VENCIMENTO','VALOR','DATA PG.','VALOR PG.');
		$val = array('baix_nb_id','baix_tx_nossoNum','enti_tx_nome','data(bole_tx_vencimento)','valor(bole_tx_valordoc)',
			'data(baix_tx_dataPagamento)','valor(baix_tx_valor)');
		grid($sql,$cab,$val,'Valor Superior');
	}

	//Já baixado
	$sql=" SELECT *	FROM baixaretorno,boleto,movimento,entidade WHERE bole_nb_movimento = movi_nb_id AND movi_nb_entidade = enti_nb_id AND baix_nb_boleto = bole_nb_id AND baix_tx_situacao = 'Já baixado' AND baix_tx_status = 'ativo' AND baix_nb_retornobnb = '$_POST[id]' ";
	if(num_linhas(query($sql." LIMIT 1"))>0){
		$cab = array('CÓD','NOSSO NUM.','ALUNO','VENCIMENTO','VALOR','DATA PG.','VALOR PG.');
		$val = array('baix_nb_id','baix_tx_nossoNum','enti_tx_nome','data(bole_tx_vencimento)','valor(bole_tx_valordoc)',
			'data(baix_tx_dataPagamento)','valor(baix_tx_valor)');
		grid($sql,$cab,$val,'Já Baixado');
	}

	//Boleto Excluído
	$sql=" SELECT *	FROM baixaretorno,boleto,movimento,entidade WHERE bole_nb_movimento = movi_nb_id AND movi_nb_entidade = enti_nb_id AND baix_nb_boleto = bole_nb_id AND baix_tx_situacao = 'Boleto Excluído' AND baix_tx_status = 'ativo' AND baix_nb_retornobnb = '$_POST[id]' ";
	if(num_linhas(query($sql." LIMIT 1"))>0){
		$cab = array('CÓD','NOSSO NUM.','ALUNO','VENCIMENTO','VALOR','DATA PG.','VALOR PG.');
		$val = array('baix_nb_id','baix_tx_nossoNum','enti_tx_nome','data(bole_tx_vencimento)','valor(bole_tx_valordoc)',
			'data(baix_tx_dataPagamento)','valor(baix_tx_valor)');
		grid($sql,$cab,$val,'Boleto Excluído');
	}

	//Duplicado
	$sql=" SELECT *	FROM baixaretorno,boleto,movimento,entidade WHERE bole_nb_movimento = movi_nb_id AND movi_nb_entidade = enti_nb_id AND baix_nb_boleto = bole_nb_id AND baix_tx_situacao = 'Duplicado' AND baix_tx_status = 'ativo' AND baix_nb_retornobnb = '$_POST[id]' ";
	if(num_linhas(query($sql." LIMIT 1"))>0){
		$cab = array('CÓD','NOSSO NUM.','ALUNO','VENCIMENTO','VALOR','DATA PG.','VALOR PG.');
		$val = array('baix_nb_id','baix_tx_nossoNum','enti_tx_nome','data(bole_tx_vencimento)','valor(bole_tx_valordoc)',
			'data(baix_tx_dataPagamento)','valor(baix_tx_valor)');
		grid($sql,$cab,$val,'Duplicado');
	}

	//Boleto não encontrado
	$sql=" SELECT *	FROM baixaretorno WHERE baix_tx_situacao = 'Boleto não encontrado' AND baix_tx_status = 'ativo' AND baix_nb_retornobnb = '$_POST[id]' ";
	if(num_linhas(query($sql." LIMIT 1"))>0){
		$cab = array('CÓD','NOSSO NUM.','ALUNO','VENCIMENTO','VALOR','DATA PG.','VALOR PG.');
		$val = array('baix_nb_id','baix_tx_nossoNum','','','',
			'data(baix_tx_dataPagamento)','valor(baix_tx_valor)');
		grid($sql,$cab,$val,'Boleto Não Encontrado');
	}

	rodape();
}




function cadastra_retornobnb(){
	
	$arquivo=$_FILES[arquivo];
	
	if( $arquivo[name] != ''){

		
		$cam = array(reto_tx_data,reto_tx_nome,reto_nb_user,reto_tx_status);
		$val = array(date('Y-m-d'),$arquivo[name],$_SESSION[user_nb_id],'ativo');
		$id=inserir('retornobnb',$cam,$val);	
		
		enviar('arquivo',"retornobnb/");
		atualizar('retornobnb',array(reto_tx_arquivo),array($arquivo[name]),$id);

	}else{

		set_status('ERRO: Envie o arquivo de retornobnb!');
		layout_ordem();
		exit;

	}

	$file=file('retornobnb/'.$arquivo[name]);
	$t_file = count($file);



	for($i=1;$i<$t_file-2;$i++){ // CONTADOR FOR COMECA DO 2 POIS AS 2 PRIMEIRAS LINHAS ( O E 1 ) SAO HEADERS DO ARQUIVO
		
		$linha=$file[$i];

		$nosso_num=(int)substr($linha, 62,7);//LINHA T removido os 2 primeiros digitos verificadores (24) e os 3 ultimos q sao gerados pelo sistema
					
		$valor_pag=substr($linha, 253,13);
		$valor_pag=$valor_pag/100;

		$data_pag=substr($linha, 110,8); // DA LINHA U data da arrecadacao
 		$dia=substr($data_pag, 0,2);
 		$mes=substr($data_pag, 2,2);
 		$ano="20".substr($data_pag, 4,2);
 		$data_pag="$ano-$mes-$dia";


		$sql_bole=query("SELECT bole_nb_id,bole_tx_status,bole_tx_valordoc FROM boleto WHERE bole_tx_nossoNum='$nosso_num' LIMIT 2");
	
		if(num_linhas($sql_bole)==1){
			$a_bol = carrega_array($sql_bole);

			if($a_bol[bole_tx_status] == 'ativo'){
				$erro = '';
				
				if($valor_pag < $a_bol[bole_tx_valordoc]){
					$erro = 'Valor inferior';
				}
				if($valor_pag > $a_bol[bole_tx_valordoc]){
					$erro = 'Valor superior';
				}
			}elseif($a_bol[bole_tx_status] == 'encerrado'){
				$erro = 'Já baixado';
			}elseif($a_bol[bole_tx_status] == 'inativo'){
				$erro = 'Boleto Excluído';
			}

			$id_boleto = $a_bol[bole_nb_id];


		}elseif(num_linhas($sql_bole)>1){
			$erro = 'Duplicado';
		}elseif(num_linhas($sql_bole) == 0){
			$erro = 'Boleto não encontrado';
		}

		


 		$campos = array(baix_nb_retornobnb,baix_nb_boleto,baix_tx_nossoNum,baix_tx_valor,baix_tx_dataPagamento,baix_nb_user,baix_tx_dataCadastro,baix_tx_situacao,baix_tx_status);
		$valores = array($id,$id_boleto,$nosso_num,$valor_pag,$data_pag,$_SESSION[user_nb_id],date("Y-m-d"),$erro,'ativo');
		inserir("baixaretorno",$campos,$valores);
		unset($erro,$id_boleto);


	}


	$linha=$file[0];
	$dia_arq = substr($linha,94,2);
	$mes_arq = substr($linha,96,2);
	$ano_arq = substr($linha,98,2);
	$data_pag = "20".$ano_arq."-".$mes_arq."-".$dia_arq;
	// echo"!$data_pag";
	
	atualizar('retornobnb',array('reto_tx_dataArquivo'),array($data_pag),$id);

	$_POST[id] = $id;
	layout_confirma();
	exit;

}


function layout_retornobnb(){

	cabecalho('Retorno Bancário BNB');


	//$c[] = campo('Data do Arquivo:','data',date("d/m/Y"),2,MASCARA_DATA);
	$c[] = arquivo('Arquivo de retorno Bancário:','arquivo','',5);

	$b[] = botao("Enviar",'cadastra_retornobnb');
	$b[] = botao("Voltar",'index');

	abre_form('Arquivo de Retorno');
	linha_form($c);
	fecha_form($b);

	rodape();
}

function index() {
	global $CACTUX_CONF;

	cabecalho('Retorno Bancário BNB',1);

	if($_POST[busca_inicio])
		$extra .= " AND reto_tx_dataArquivo >= '".data($_POST[busca_inicio],1)."'";
	if($_POST[busca_fim])
		$extra .= " AND reto_tx_dataArquivo <= '".data($_POST[busca_fim],1)."'";

	
	
	//CONSULTA
	$c[] = campo('Código:','busca_codigo',$_POST[busca_codigo],2);
	$c[] = campo('Data Início:','busca_inicio',$_POST[busca_inicio],2,MASCARA_DATA);
	$c[] = campo('Data Fim:','busca_fim',$_POST[busca_fim],2,MASCARA_DATA);
		
	
	//BOTOES
	$b[] = botao("Buscar",'index');
	$b[] = botao("Inserir",'layout_retornobnb');
	
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);
	
	
	$sql=" SELECT *	FROM retornobnb,user WHERE  reto_nb_user = user_nb_id AND  reto_tx_status != 'inativo' $extra ";
	$cab = array('CÓD','ARQUIVO','USUÁRIO','DATA','SITUAÇÃO','');

	$ver2 = "icone_modificar(reto_nb_id,layout_confirma)";
	$val = array(reto_nb_id,reto_tx_nome,user_tx_nome,'data(reto_tx_dataArquivo)','ucfirst(reto_tx_status)',$ver2);
	grid($sql,$cab,$val,'','',0,'desc');

	
	rodape();

}
