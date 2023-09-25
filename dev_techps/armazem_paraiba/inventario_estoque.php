<?php
include "conecta.php";




function exibir_icone_modificar($cod){

	$a_inv = carregar('inventario',$cod);

	if ( strtolower($a_inv[inve_tx_situacao])!='finalizado' )
		$retorno = icone_modificar($cod,'modifica_inventario');
	else
		$retorno = '';

	return $retorno;
}


function exibir_icone_contagem($cod){

	$a_inv = carregar('inventario',$cod);

	if ( strtolower($a_inv[inve_tx_situacao])=='lançado' )
		$retorno = icone_modificar($cod,'modifica_contagem','','','','glyphicon glyphicon-edit');
	else
		$retorno = '';

	return $retorno;
}


function exibir_icone_excluir($cod){

	$a_inv = carregar('inventario',$cod);

	if ( strtolower($a_inv[inve_tx_situacao])!='finalizado' )
		$retorno = icone_excluir($cod,'exclui_inventario');
	else
		$retorno = '';

	return $retorno;
}



function cabeca_planilha(){
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<title>Inventário de Estoque</title>	
		<style>
			body{
				font-family: arial;
			}
			header{
				position: relative;
				margin: 0 0 0.3% 0;
				padding: 0;
				width: 100%;
				float: left;
				/*background-color: red;*/
			}
			header aside{
				position: relative;
				margin: 0;
				padding: 0;
				width: 20%;
				float: left;
				/*background-color: yellow;*/
			}
			header aside figure{
				position: relative;
				margin: 0;
				padding: 2%;
				width: 96%;
				float: left;
				/*background-color: lightblue;*/
			}
			header aside figure img{
				position: relative;
				margin: 0;
				padding: 0;
				width: 100%;
				float: left;			
			}
			header aside p.left{
				position: relative;
				margin: 0;
				padding: 1% 0;
				width: 100%;
				text-align: center;
				float: left;
				font-weight: bold;
				/*background-color: brown;*/
			}
			header aside p.right{
				position: relative;
				margin: 0;
				padding: 12.7% 0;
				width: 100%;
				text-align: center;
				float: left;
				font-weight: bold;
				/*background-color: brown;*/
			}
			header center{
				position: relative;
				margin: 0;
				padding: 1.9% 0;
				width: 60%;
				float: left;
				/*background-color: orange;*/
			}
			section{
				position: relative;
				margin: 0;
				padding: 0 1% 0.8% 1%;
				width: 98%;
				float: left;
				/*background-color: blue;*/
			}		
			table {
				position: relative;
				margin: 0;
				padding: 0;
			    width:100%;
			    float: left;
			    /*background-color: purple*/
			}
			table, th, td {
			    border: 1px solid black;
			    border-collapse: collapse;
			}
			th, td {			
			    padding: 0;		    
			}
			table#t01 tr:nth-child(even) {
			    background-color: #eee;
			}
			table#t01 tr:nth-child(odd) {
			   background-color: #fff;
			}
			table#t01 th {
			    background-color: silver;
			    color: #000;
			}					
			table#t01 th.total {
			    background-color: gray;
			    color: #fff;
			}		
			table#t01 td.dt{
				text-align: center;
				font-weight: bold;
			}
			footer{
				position: relative;
				margin: 0;
				padding: 0;
				width: 100%;
				float: left;
				/*background-color: green;*/
			}
			footer aside{
				position: relative;
				margin: 0;
				padding: 0;
				width: 50%;
				float: left;
				font-weight: bold;
				font-size: 10pt;
				/*background-color: pink;*/
			}
			footer aside p{
				position: relative;
				margin: 0;
				padding: 0 0 0 2%;
				width: 98%;
				float: left;
				/*background-color: purple;*/
			}
			footer aside.aright{			
				padding: 1.35% 0;
			}
			footer aside.aright p.ass{
				padding: 0.6% 0 0.6% 35%;
				width: 65%;
				font-weight: bold;
				font-size: 8pt;
			}
			@media print{				
				header aside p.left{
					font-size: 8pt;
				}
				header center{
					padding: 1.7% 0;					
				}
				header h1{
					font-size: 12pt;
				}
				header center h3{
					font-size: 10pt;
				}
				header aside p.right{
					padding: 9% 0;
					font-size: 10pt;
				}
				table#t01 th{
					font-size: 12pt;
				}
				td{
					font-size: 10pt;
				}
				footer aside p{
					font-size: 8pt;
				}
			}
		</style>
	</head>
	<body>
	<?
}






function exclui_inventario(){

	remover('inventario',$_POST[id]);
	index();
	exit;
}




function carrega_categoria($id){
	if($id>0){
		$a_categoria = carregar('categoria',$id);
		
		return $a_categoria[cate_tx_nome];
	} else {
		return '';
	}
}


function carrega_marca($id){
	if($id>0){
		$a_marca = carregar('marca',$id);
		
		return $a_marca[marc_tx_nome];
	} else {
		return '';
	}
}


function exibe_check_produtos($id,$id_inventario){

	$check = '';

	$a_item = carregar('iteminventario','','item_nb_produto,item_nb_inventario,item_tx_status',"$id,$id_inventario,ativo");
	if ( $a_item[item_nb_id]>0 ) {
		// SE O PRODUTO ESTIVER NA LISTA, SELECIONA O CHECK E ADICIONA O PRODUTO NO ARRAY
		$selecionado = 'checked';
		$check .= '<script type="text/javascript">
			adiciona_produto('.$id.');
		</script>';
	}

	$check .= '<input type="checkbox" id="produto_'.$id.'" onclick="adiciona_produto('.$id.')" name="produto_'.$id.'" '.$selecionado.'>';

	$check .= '<script type="text/javascript">
		a_listados.push('.$id.');
		document.getElementById("ids_listados").value = a_listados.join(",");
	</script>';

	return $check;
}



function exibe_campo_quantidade($id_item){

	$a_item = carregar('iteminventario',$id_item);

	$script =  "<script>$('#item_$id_item').maskMoney({ allowNegative: true, thousands:\".\", decimal:\",\", affixesStay: false})</script>";

	$botao = '<input name="item[]" id="item_'.$id_item.'" onblur="atualiza_quantidade(this.value,'.$id_item.')" onfocus="desativa_finalizar()" value="'.valor($a_item[item_tx_qtde]).'" autocomplete="off" type="text" class="form-control input-sm">'.$script;
	return $botao;
}






function finaliza_lancamento(){

	$campos = array(inve_tx_situacao);
	$valores = array('Lançado');
	atualizar('inventario',$campos,$valores,$_POST[id]);


	set_status('Lançamento concluído com sucesso!');

	index();
	exit;
}



function imprime_lista_inventario(){

	if ( intval($_POST[id])==0 ) {
		echo "ERRO: Código em falta!";
		exit;
	}

	
	cabeca_planilha();


	$a_inventario = carregar('inventario',$_POST[id]);

		?>
		<header>
			<aside>
				<figure>
					<img src="imagens/logo_cliente.png" alt="imagens/logo_cliente.png"/>
				</figure>
				<p class="left">Mossoró/RN</p>
			</aside>
			<center>
				<h1>INVENTÁRIO</h1>
				<!-- <h3>(HRTM)</h3> -->
			</center>			
			<aside>
				<p class="right">Emissão: <span><br><?=date("d/m/Y")?></span></p>
			</aside>
		</header>
		<hr>
		<section>
			<table id="t01">
				<tr>
					<th>&nbsp;</th>
					<th>CÓDIGO</th>
					<th>PRODUTO</th>
					<th>MARCA</th>	
					<th>CATEGORIA</th>
					<th>VALOR</th>	
					<th>QUANTIDADE</th>				
				</tr>
				<?
				$sql = query("SELECT * FROM iteminventario,produto WHERE item_nb_inventario='$_POST[id]' AND prod_tx_status!='inativo' AND item_tx_status!='inativo' AND item_nb_produto=prod_nb_id ORDER BY prod_nb_id DESC ");
				while($a = carrega_array($sql)){					
					$a_marca = carregar('marca',$a[prod_nb_marca]);
					$a_categoria = carregar('categoria',$a[prod_nb_categoria]);
					

					?>
					<tr>
						<td style="padding-left: 0.5%;"><?=++$j?></td>
						<td style="padding-left: 0.5%;"><?=$a[prod_nb_id]?></td>
						<td style="padding-left: 0.5%;"><?=$a[prod_tx_nome]?></td>
						<td style="padding-left: 0.5%;"><?=$a_marca[marc_tx_nome]?></td>
						<td style="padding-left: 0.5%;"><?=$a_categoria[cate_tx_nome]?></td>
						<td style="padding-left: 0.5%;">R$ <?=valor($a[prod_tx_preco],1)?></td><?php
						if( $a_inventario[inve_tx_situacao]=='Finalizado' ){
							// SE ESTIVER FINALIZADO, IMPRIME COM A QUANTIDADE FORNECIDA
							?><td style="padding-left: 0.5%;"><?=valor($a[item_tx_qtde],1)?></td><?php
						} else {
							?><td style="padding-left: 0.5%;">&nbsp;</td><?php
						}
					?></tr>										
					<?					
				}
				?>
			</table>
		</section>
		<!-- <footer>
			<aside>
				<p>M - MANHÃ - 07 às 13 hr</p>
				<p>T - TARDE - 13 às 19 hr</p>
				<p>D - DIURNO - 07 às 19 hr</p>
				<p>N - NOITE - 19 às 07 hr</p>				
			</aside>
			<aside class="aright">
				<p>Mossoró, <span>___ / ___ / _____</span> <span>________________________________________</span></p>
				<p class="ass">COORDENADOR(A) DE FISIOTERAPIA</p>
			</aside>
		</footer> -->
	</body>
	</html>
	<?

	exit;
}




function layout_confirma_produtos(){

	if ( intval($_POST[id])==0 ) {
		set_status('ERRO: Falha ao carregar os dados da movimentação!');
		index();
		exit;
	}


	cabecalho("Inventário");


	$a_movi = carregar('inventario',$_POST[id]);

	$c[] = texto('Código',$a_movi[inve_nb_id],2);
	$c[] = texto('Descrição',$a_movi[inve_tx_nome],10);


	$botao[] = botao('Finalizar','finaliza_lancamento','id',"$_POST[id]");
	$botao[] = botao('Imprimir','imprime_lista_inventario','id',"$_POST[id]",'formtarget="_blank"');
	$botao[] = botao('Voltar','layout_seleciona_produtos','id',"$_POST[id]");
	
	abre_form('Dados do Inventário');
	linha_form($c);
	fecha_form($botao);



	// LISTA OS PRODUTOS QUE FORAM LANÇADOS NA MOVIMENTAÇÃO
	$sql = "SELECT * FROM iteminventario,produto WHERE item_nb_inventario='$_POST[id]' AND prod_tx_status!='inativo' AND item_tx_status!='inativo' AND item_nb_produto=prod_nb_id ";
	$cab = array('CÓDIGO','PRODUTO','MARCA','CATEGORIA','VALOR');
	$val = array('prod_nb_id','prod_tx_nome','carrega_marca(prod_nb_marca)','carrega_categoria(prod_nb_categoria)','valor(prod_tx_preco,1)');

	grid($sql,$cab,$val,'','','0','desc','-1');


	rodape();
}





function busca_produto(){

	// print_r($_POST);

	$a_selecionados = @explode(',', $_POST[ids_selecionados]);
	$a_listados = @explode(',', $_POST[ids_listados]);

	// PERCORRE TODOS OS ELEMENTOS LISTADOS, VERIFICANDO SE ALGUM FOI SELECIONADO
	$qtde_listados = count($a_listados);
	for ($i=0; $i<$qtde_listados; $i++) { 
		$id_produto = $a_listados[$i];

		if (in_array($id_produto, $a_selecionados)) { 
			// SE O PRODUTO LISTADO ESTIVER SELECIONADO
			$a_item = carregar('iteminventario','','item_nb_produto,item_nb_inventario,item_tx_status',"$id_produto,$_POST[id],ativo");
			if ( $a_item[item_nb_id]==0 && $id_produto>0 ) {
				// CASO NÃO ENCONTRE UM ITEM ATIVO, DEVE ADICIONAR NA BASE DE DADOS
				$campos = array(item_nb_inventario,item_nb_produto,item_nb_userCadastro,item_tx_dataCadastro,item_tx_status);
				$valores= array($_POST[id],$id_produto,$_SESSION[user_nb_id],date('Y-m-d H:i:s'),'ativo');
				inserir('iteminventario',$campos,$valores);
			}
		} else {
			// SE O PRODUTO LISTADO NÃO ESTIVER SELECIONADO
			query("UPDATE iteminventario SET item_tx_status='inativo' WHERE item_nb_inventario='$_POST[id]' AND item_nb_produto='$id_produto' AND item_tx_status='ativo' ");
		}
	}

	if ($_POST[avancar]=='sim')
		layout_confirma_produtos();// SE ESTIVER INDO PARA A TELA DE CONFIRMAÇÃO
	else
		layout_seleciona_produtos();// SE ESTIVER APENAS FILTRANDO
	exit;
}




function layout_seleciona_produtos(){

	if ( intval($_POST[id])==0 ) {
		set_status('ERRO: Falha ao carregar os dados da movimentação!');
		index();
		exit;
	}

	if ( $_POST[busca_produto]!='' )
		$extra .= " AND prod_tx_nome LIKE '%$_POST[busca_produto]%' ";
	if ( $_POST[busca_categoria]>0 )
		$extra .= " AND prod_nb_categoria = '$_POST[busca_categoria]' ";
	if ( $_POST[busca_marca]>0 )
		$extra .= " AND prod_nb_marca = '$_POST[busca_marca]' ";


	cabecalho("Inventário");


	$a_movi = carregar('inventario',$_POST[id]);


	$c[] = texto('Descrição',$a_movi[inve_tx_nome],12);

	$c[] = campo('Produto','busca_produto',$_POST[busca_produto],4);
	$c[] = combo_net('Marca','busca_marca',$_POST[busca_marca],3,'marca');
	$c[] = combo_bd('!Categoria','busca_categoria',$_POST[busca_categoria],3,'categoria');
	$check_todos = '<input type="checkbox" id="check_todos" onclick="verifica_todos()" name="check_todos" >';
	$c[] = texto('Selecionar&nbsp;Todos',$check_todos,2);


	$botao[] = botao('Buscar','busca_produto','id',"$_POST[id]");
	$botao[] = botao('Avançar','busca_produto','id,avancar',"$_POST[id],sim");
	$botao[] = botao('Voltar','modifica_inventario','id',"$_POST[id]");



	
	abre_form('Dados do Inventário');
	linha_form($c);
	// SERVE PARA ARMAZENAR OS IDS QUE FORAM SELECIONADOS E OS QUE FORAM LISTADOS
	campo_hidden('ids_selecionados','');
	campo_hidden('ids_listados','');
	fecha_form($botao);


	?><script type="text/javascript">
		var a_selecionados = [];
		var a_listados = [];
	</script><?php




	// LISTA OS PRODUTOS QUE FORAM LANÇADOS NA MOVIMENTAÇÃO
	$sql = "SELECT * FROM produto WHERE prod_tx_status != 'inativo' AND (prod_tx_servico IS NULL OR prod_tx_servico = '') $extra ";
	$cab = array('CÓDIGO','PRODUTO','MARCA','CATEGORIA','VALOR','');
	$val = array('prod_nb_id','prod_tx_nome','carrega_marca(prod_nb_marca)','carrega_categoria(prod_nb_categoria)','valor(prod_tx_preco,1)','exibe_check_produtos(prod_nb_id,'.$_POST[id].')');

	grid($sql,$cab,$val,'','','0','desc','50');



	?><script type="text/javascript">
		function adiciona_produto(id_produto){

			var index_elemento = a_selecionados.indexOf(id_produto);
			if(index_elemento > -1) {
				// SE O PRODUTO JÁ ESTIVER SELECIONADO, REMOVE DO ARRRAY
				a_selecionados.splice(index_elemento, 1);
			} else {
				// SE NÃO, ADICIONA
				a_selecionados.push(id_produto);
			}
			document.getElementById('ids_selecionados').value = a_selecionados.join(',');
		}


		function verifica_todos(){
			var situacao = document.getElementById('check_todos').checked;

			if ( situacao==true ) {// MARCANDO TODOS
				a_selecionados = a_listados;
			} else {// DESMARCANDO TODOS
				a_selecionados = [];
			}

			for (var i = 0, l = a_listados.length; i < l; i++) {
				if ( situacao==true ) {// MARCA TODOS OS CHEKS LISTADOS
					document.getElementById('produto_'+a_listados[i]).checked = true;
				} else {
					document.getElementById('produto_'+a_listados[i]).checked = false;
				}
			}

			document.getElementById('ids_selecionados').value = a_selecionados.join(',');
		}

	</script><?php

	rodape();
}




function cadastra_inventario(){
	global $a_mod;


	$_POST[descricao] = trim($_POST[descricao]);
	
	$campos  = array(inve_tx_nome);
	$valores = array(addslashes($_POST[descricao]));

	$a_mod = array_combine($campos,$valores);


	if ( $_POST[descricao]=='' ) {
		set_status('ATENÇÃO: Informe uma descrição para o inventário!');
		layout_inventario();
		exit;
	}


	if ( $_POST[id]>0 ) {
		atualizar('inventario',$campos,$valores,$_POST[id]);

	} else {
		array_push($campos, inve_nb_userCadastro,inve_tx_dataCadastro,inve_tx_situacao,inve_tx_status);
		array_push($valores, $_SESSION[user_nb_id],date('Y-m-d H:i:s'),'Pendente','ativo');

		inserir('inventario',$campos,$valores);
		$_POST[id] = ultimo_reg('inventario');
	}



	layout_seleciona_produtos();
	exit;
}





function modifica_inventario(){
	global $a_mod;

	$a_mod = carregar('inventario',$_POST[id]);

	layout_inventario();
	exit;
}



function modifica_contagem(){
	global $a_mod;

	$a_mod = carregar('inventario',$_POST[id]);

	layout_contagem();
	exit;
}



function finaliza_contagem(){
	global $conn;


	if ( intval($_POST[id])==0 ) {
		set_status('ERRO: Código do inventário em falta!');
		index();
		exit;
	}


	$sql = "SELECT * FROM iteminventario,produto WHERE item_nb_inventario='$_POST[id]' AND prod_tx_status!='inativo' AND item_tx_status!='inativo' AND item_nb_produto=prod_nb_id ";

	$query=mysqli_query($conn, $sql) or die(mysql_error());
	while( $a=mysqli_fetch_array($query) ) {

		$nova_qtde = $a[item_tx_qtde];

		atualizar('produto',array(prod_tx_qtde),array($nova_qtde),$a[prod_nb_id]);

		$campos  = array(esto_nb_iteminventario,esto_nb_produto,esto_nb_userCadastro,esto_tx_dataCadastro,esto_tx_tipo,
						esto_tx_qtdeDepois,esto_tx_qtdeAntes,esto_tx_qtde,esto_tx_status);
		$valores = array($a[item_nb_id],$a[prod_nb_id],$_SESSION[user_nb_id],date('Y-m-d H:i:s'),'Inventário',
						$nova_qtde,$a[prod_tx_qtde],($nova_qtde-$a[prod_tx_qtde]),'ativo');

		inserir('estoque',$campos,$valores);
	}

	atualizar('inventario',array(inve_tx_situacao),array('Finalizado'),$_POST[id]);


	set_status('Inventário finalizado com sucesso!');
	index();
	exit;
}



function atualiza_quantidade(){

	$id_item = intval($_POST[item]);
	$qtde    = valor($_POST[qtde]);

	atualizar('iteminventario',array(item_tx_qtde),array($qtde),$id_item);

	exit;
}




function layout_contagem(){

	if ( intval($_POST[id])==0 ) {
		set_status('ERRO: Falha ao carregar os dados da movimentação!');
		index();
		exit;
	}


	cabecalho("Inventário");

	?><script src="jquery.blockUI.js" type="text/javascript"></script><?php


	$a_movi = carregar('inventario',$_POST[id]);

	$c[] = texto('Código',$a_movi[inve_nb_id],2);
	$c[] = texto('Descrição',$a_movi[inve_tx_nome],10);


	// $botao[] = botao('Finalizar','finaliza_contagem','id',"$_POST[id]");
	$botao[] = '<button name="acao" id="bt_finalizar" value="finaliza_contagem"  type="submit" class="btn default">Finalizar 2</button>';
	$botao[] = botao('Voltar','index');
	
	abre_form('Dados do Inventário');
	linha_form($c);
	campo_hidden('id',$_POST[id]);
	fecha_form($botao);



	// LISTA OS PRODUTOS QUE FORAM LANÇADOS NA MOVIMENTAÇÃO
	$sql = "SELECT * FROM iteminventario,produto WHERE item_nb_inventario='$_POST[id]' AND prod_tx_status!='inativo' AND item_tx_status!='inativo' AND item_nb_produto=prod_nb_id ";
	$cab = array('CÓDIGO','PRODUTO','MARCA','CATEGORIA','VALOR','QUANTIDADE');
	$val = array('prod_nb_id','prod_tx_nome','carrega_marca(prod_nb_marca)','carrega_categoria(prod_nb_categoria)','valor(prod_tx_preco,1)','exibe_campo_quantidade(item_nb_id)');

	grid($sql,$cab,$val,'','','0','desc','-1');


	?><script type="text/javascript">

		function desativa_finalizar(){
			document.getElementById("bt_finalizar").disabled = true;
		}

		function atualiza_quantidade(quantidade,id_item){
			// alert('QTDE: |'+quantidade+'| ITEM: |'+id_item+'|');

			$.ajax({
				url : "inventario_estoque.php",
				type : 'post',
				data : {
					acao : "atualiza_quantidade",
					qtde : quantidade,
					item : id_item,
				},
				beforeSend : function(){
					document.getElementById("bt_finalizar").disabled = true;
					$.blockUI({
						message: '<h1><img src="https://media.giphy.com/media/N256GFy1u6M6Y/giphy.gif" width=\'30%\' />Processando...</h1>',
						css: {
							border: 'none', 
							padding: '15px', 
							backgroundColor: '#000', 
							'-webkit-border-radius': '10px', 
							'-moz-border-radius': '10px', 
							opacity: .5, 
							color: '#fff', 
						}
					});
				}
			})
			.done(function(msg){
				$.unblockUI();
				document.getElementById("bt_finalizar").disabled = false; //LIBERA O BOTÃO FINALIZAR
			})
			.fail(function(jqXHR, textStatus, msg){
				$.unblockUI();
				document.getElementById("bt_finalizar").disabled = false; //LIBERA O BOTÃO FINALIZAR
				alert(msg);
			}); 


		}
	</script><?php


	rodape();

	
}




function layout_inventario(){
	global $a_mod;

	cabecalho("Inventário");

	$c[] = campo('* Descrição','descricao',$a_mod[inve_tx_nome],12);

	$botao[] = botao('Avançar','cadastra_inventario','id',$_POST[id]);
	$botao[] = botao('Voltar','index');
	
	abre_form('Dados do Inventário');
	linha_form($c);
	fecha_form($botao);

	rodape();
}




function index(){

	cabecalho("Inventário");

	if($_POST[busca_codigo])
		$extra .= " AND inve_nb_id = '$_POST[busca_codigo]'";

	if($_POST[busca_nome])
		$extra .= " AND inve_tx_nome LIKE '%$_POST[busca_nome]%'";

	if($_POST[busca_usuario])
		$extra .= " AND user_tx_nome LIKE '%$_POST[busca_usuario]%'";

	$c[] = campo('Código','busca_codigo',$_POST[busca_codigo],2,'MASCARA_NUMERO');
	$c[] = campo('Descrição','busca_nome',$_POST[busca_nome],6);
	$c[] = campo('Usuário','busca_usuario',$_POST[busca_usuario],4);

	$botao[] = botao('Buscar','index');
	$botao[] = botao('Inserir','layout_inventario');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM inventario,user WHERE inve_tx_status != 'inativo' AND inve_nb_userCadastro=user_nb_id $extra";
	$cab = array('CÓDIGO','DESCRIÇÃO','SITUAÇÃO','USUÁRIO','DATA','','','','');

	// $modificar = 'icone_modificar(inve_nb_id,modifica_inventario)';
	// $modificar = 'exibir_icone_modificar(inve_nb_id)';
	$modificar = 'exibir_icone_modificar(inve_nb_id)';
	$imprimir  = 'icone_modificar(inve_nb_id,imprime_lista_inventario,,,_blank,glyphicon glyphicon-print)';
	$contar    = 'exibir_icone_contagem(inve_nb_id)';
	$excluir   = 'exibir_icone_excluir(inve_nb_id)';

	$val = array('inve_nb_id','inve_tx_nome','inve_tx_situacao','user_tx_nome','data(inve_tx_dataCadastro,1)',$modificar,$imprimir,$contar,$excluir);

	grid($sql,$cab,$val,'','','0','desc');

	rodape();

}