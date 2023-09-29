<?php
include_once $_SERVER['DOCUMENT_ROOT']."/contex20/funcoes_vendas.php";
include "../conecta.php";





function buscar_venda(){

	// print_r($_POST);

	if (!isset($_POST[busca_data]))
		$_POST[busca_data] = date('d/m/Y');

	if($_POST[busca_codigo])
		$extra .=" AND enti_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_caixa])
		$extra .=" AND enti_tx_nome LIKE '%$_POST[busca_caixa]%'";
	if($_POST[busca_cpf])
		$extra .=" AND enti_tx_cpf LIKE '%$_POST[busca_cpf]%'";
	if($_POST[busca_data])
		$extra .=" AND orde_tx_data = '".data($_POST[busca_data])."' ";
	

	$c[]=campo('Código','busca_codigo',$_POST[busca_codigo],2);
	$c[]=campo('Nome','busca_caixa',$_POST[busca_caixa],7);
	$c[]=campo('CPF','busca_cpf',$_POST[busca_cpf],3,''," maxlength='14' onkeyup=mascara_cpf_venda(this); ");
	$c[]=campo('Data','busca_data',$_POST[busca_data],3,''," maxlength='10' onkeyup=mascara_data_venda(this); ");

	$b[]='<div class="col-sm-2" style="width: 12%"><button onclick="descarta_venda();" type="submit" class="btn btn-primary">Descartar</button></div>';
	$b[]='<div class="col-sm-1"><button onclick="cancelar_venda();" type="submit" class="btn btn-primary">Cancelar</button></div>';


	?><form id="form_busca_venda">
		<input type="submit" id="submitButtonBuscaVenda"  name="submitButtonBuscaVenda" value="Submit" style="display: none">
		<input type="hidden" name="id_venda" value="<?=$_POST[id_venda]?>">
		<input type="hidden" name="acao" value="buscar_venda"><?php

		linha_form($c);
		echo "<br>";
		linha_form($b);

	?></form>
	<br>

	<table class="table table-bordered">
		<thead>
		<tr>
			<th scope="col">Código</th>
			<th scope="col">Nome</th>
			<th scope="col">Valor Bruto</th>
			<th scope="col">Desconto</th>
			<th scope="col">Valor Líquido</th>
			<th scope="col">Data</th>
			<th scope="col"></th>
		</tr>
		</thead>
		<tbody><?php

			if ($_POST[id_venda]>0)
				$extra_venda = " AND orde_nb_id != '$_POST[id_venda]' ";

			$result = query("SELECT * FROM ordem,entidade WHERE orde_tx_status!='inativo' $extra_venda AND orde_tx_situacao!='finalizado' AND orde_tx_valorBruto>0 AND orde_nb_entidade=enti_nb_id $extra ORDER BY orde_nb_id DESC ");
			while($row = $result->fetch_assoc()){

				$icone_selecionar = '<center><a style="color:gray;" href="javascript:seleciona_venda('.$row['orde_nb_id'].');" ><spam class="glyphicon glyphicon-search"></spam></a></center>';

				?><tr>
					<th><?=$row['orde_nb_id']?></th>
					<td><?=$row['enti_tx_nome']?></td>
					<td><?=valor($row['orde_tx_valorBruto'],1)?></td>
					<td><?=valor($row['orde_tx_descontoReais'],1)?></td>
					<td><?=valor($row['orde_tx_valorBruto'],1)?></td>
					<td><?=data($row['orde_tx_data'])?></td>
					<td><?=$icone_selecionar?></td>
				</tr><?php
			}
		?></tbody>
	</table>


	<script type='text/javascript'>		
		$('#form_busca_venda').submit(function(){
			submit_busca_venda();
			return false;
		});

		$("#bt_buscar_venda").click(function() {
			submit_busca_venda();
			return false;
		});

		function submit_busca_venda(){
			var dados = $('#form_busca_venda').serialize();

			jQuery.ajax({
				type: "POST",
				url: "index.php",
				data: dados,
				beforeSend: function(){
					console.log('ENVIANDO...');
				},
				success: function( data ){
					$('#lista_vendas').html(data);
				}
			});
		}
	</script>

<?php
}





function buscar_cliente(){

	// print_r($_POST);

	if($_POST[busca_codigo])
		$extra .=" AND enti_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_nome])
		$extra .=" AND enti_tx_nome LIKE '%$_POST[busca_nome]%'";
	if($_POST[busca_cpf])
		$extra .=" AND enti_tx_cpf LIKE '%$_POST[busca_cpf]%'";
	

	$c[]=campo('Código','busca_codigo',$_POST[busca_codigo],2);
	$c[]=campo('Nome','busca_nome',$_POST[busca_nome],7);
	$c[]=campo('CPF','busca_cpf',$_POST[busca_cpf],3,''," maxlength='14' onkeyup=mascara_cpf_venda(this); ");

	
	?><form id="form_busca_cliente">
		<input type="submit" id="submitButtonBuscaCliente"  name="submitButtonBuscaCliente" value="Submit" style="display: none">
		<input type="hidden" name="acao" value="buscar_cliente"><?php

		linha_form($c);

	?></form>
	<br>

	<table class="table table-bordered">
		<thead>
		<tr>
			<th scope="col">Código</th>
			<th scope="col">Nome</th>
			<th scope="col">CPF</th>
			<th scope="col"></th>
		</tr>
		</thead>
		<tbody><?php
			$sql_cliente = "SELECT * FROM entidade WHERE enti_tx_status != 'inativo' AND enti_tx_tipo = 'Cliente' $extra LIMIT 10";
			$result = query($sql_cliente);
			while($row = $result->fetch_assoc()){

				$icone_selecionar = '<center><a title="" style="color:gray" onclick="javascript:seleciona_cliente('.$row['enti_nb_id'].');"><spam class="glyphicon glyphicon-search"></spam></a></center>';

				?><tr>
					<th><?=$row['enti_nb_id']?></th>
					<td><?=$row['enti_tx_nome']?></td>
					<td><?=$row['enti_tx_cpf']?></td>
					<td><?=$icone_selecionar?></td>
				</tr><?php
			}
		?></tbody>
	</table>


	<script type='text/javascript'>		
		$('#form_busca_cliente').submit(function(){
			submit_busca_clientes();
			return false;
		});

		$("#bt_buscar_clientes").click(function() {
			submit_busca_clientes();
			return false;
		});

		function submit_busca_clientes(){
			var dados = $('#form_busca_cliente').serialize();

			jQuery.ajax({
				type: "POST",
				url: "index.php",
				data: dados,
				beforeSend: function(){
					console.log('ENVIANDO...');
				},
				success: function( data ){
					$('#lista_clientes').html(data);
				}
			});
		}
	</script>

<?php
}





function listar_pagamentos(){

	$id_venda = intval($_POST[id_venda]);
	$valor_venda = valor($_POST[valor_venda],1);

	?><table class="table table-striped table-bordered table-condensed">
	<thead>
		<tr>
			<th class="text-center col-md-1">ID</th>
			<th>FORMA</th>
			<th class="text-center col-md-1">VALOR</th>
			<th class="col-md-1">&nbsp;</th>
		</tr>
	</thead>
	<tbody><?php

	if ( $id_venda>0 ) {
		$valor_total_pago = 0;
		$sql = "SELECT * FROM pagamento,forma WHERE paga_nb_ordem = '$id_venda' AND paga_nb_forma=form_nb_id ORDER BY paga_nb_id ASC";

		$result = query($sql);
		while($row = $result->fetch_assoc()){

			if ( $row['paga_tx_status']!='inativo' ) {
				?><tr>
					<td class="text-center"><?=$row['paga_nb_id']?></td>
					<td><?=$row['form_tx_nome']?></td>
					<td class="text-center"><?=valor($row['paga_tx_valor'],1)?></td>
					<td class="text-center" onclick="remover_pagamento('<?=$row['form_tx_nome']?>',<?=$row['paga_nb_id']?>);"><img src="img/icon002.png" alt="img/icon002.png"/></td>
				</tr><?php
				$valor_total_pago += $row['paga_tx_valor'];

			} else {
				// SE O PAGAMENTO ESTIVER SIDO REMOVIDO
				?><tr>
					<td class="text-center"><strike><?=$row['paga_nb_id']?></strike></td>
					<td><strike><?=$row['form_tx_nome']?></strike></td>
					<td class="text-center"><strike><?=valor($row['paga_tx_valor'],1)?></strike></td>
					<td class="text-center">&nbsp;</td>
				</tr><?php
			}
		}

		if ( $valor_total_pago>0 ) {
			?><tr>
				<td class="text-center"></td>
				<td><b>TOTAL RECEBIDO:</b></td>
				<td class="text-center"><b><?=valor($valor_total_pago,1)?></b></td>
				<td class="text-center"></td>
			</tr><?php
		}

		$saldo = $valor_venda-$valor_total_pago;

		if ( $saldo>0 ) {
			// SE FALTAR RECEBER ALGUM VALOR
			?><tr>
				<td class="text-center"></td>
				<td><b>FALTA RECEBER:</b></td>
				<td class="text-center"><b><?=valor($saldo,1)?></b></td>
				<td class="text-center"></td>
			</tr><?php

		} else {
			// MOSTRA O VALOR DO TROCO
			?><tr>
				<td class="text-center"></td>
				<td><b>TROCO:</b></td>
				<td class="text-center"><b><?=valor($saldo*(-1),1)?></b></td>
				<td class="text-center"></td>
			</tr><?php
		}
	}

	?></tbody>
	</table><?php


	if ( $saldo>0 ) {// SE AINDA TIVER VALOR A RECEBER
		?><script type="text/javascript">
			$('#valor_pagamento').val("<?=valor($valor_venda-$valor_total_pago,1)?>");
			$('#valor_pagamento').select();
		</script><?php
	} else {		
		?><script type="text/javascript">
			$('#valor_pagamento').val("0,00");
			$('#valor_pagamento').select();
		</script><?php
	}

}




function buscar_produto(){

	$json = array();
	
	if($_GET['q'] != ''){
		$_GET['q'] = addslashes($_GET['q']);
		$extra = " AND (prod_tx_nome LIKE '%".$_GET['q']."%' OR prod_tx_codigoBarras LIKE '%".$_GET['q']."%' OR prod_nb_id = '".$_GET['q']."') ";

		$sql = "SELECT prod_nb_id,prod_tx_nome,prod_tx_codigoBarras,prod_tx_preco FROM produto 
				WHERE prod_tx_status != 'inativo' $extra
				ORDER BY prod_tx_nome ASC LIMIT 10";

		$result = query($sql);
		while($row = $result->fetch_assoc()){

			if ( $row['prod_tx_codigoBarras']!='' ) {
				$nome_exibir = '['.$row['prod_tx_codigoBarras'].'] '.$row['prod_tx_nome'];
			} else {
				$nome_exibir = $row['prod_tx_nome'];
			}

			$nome_exibir .= ' =>  R$ '.valor($row['prod_tx_preco'],1);

			$json[] = array('id'=>$row['prod_nb_id'], 'text'=>$nome_exibir);
		}
	}

	echo json_encode($json);
}





function gera_grid_produtos(){

	$id_venda = intval($_POST[id_venda]);


	// INICIO DA TABELA
	?><table class="table table-striped table-bordered table-condensed">
	<thead>
		<tr>
			<th>&nbsp;</th>
			<th class="text-center col-md-1">ID</th>
			<th>PRODUTO</th>
			<th class="text-center col-md-1">QTDE</th>
			<th class="text-center col-md-1">UNITÁRIO</th>
			<th class="text-center col-md-1">DESCONTO</th>
			<th class="text-center col-md-1">TOTAL</th>
			<th class="col-md-1">&nbsp;</th>
		</tr>
	</thead>
	<tbody><?php

	if ( $id_venda>0 ) {
		$sql = "SELECT * FROM orpr,produto WHERE orpr_nb_ordem = '$id_venda' AND orpr_nb_produto=prod_nb_id ORDER BY orpr_nb_id ASC";

		$result = query($sql);
		while($row = $result->fetch_assoc()){
			$j++;

			if ($row['orpr_tx_descontoReais']>0)
				$string_descontos = valor($row['orpr_tx_descontoReais'],1).'&nbsp;('.valor($row['orpr_tx_descontoPorcentagem']).'%)';
			else
				$string_descontos = '';


			if ( $row['orpr_tx_status']=='inativo' ) {
				$riscar  = '<strike>';
				$riscar2 = '</strike>';
				$js_exclusao = '';
				$bt_excluir = '';
			} else {				
				$riscar  = '';
				$riscar2 = '';
				$js_exclusao = 'onclick="remover_produto('.$j.','.$row['orpr_nb_id'].')"';
				$bt_excluir = '<img src="img/icon002.png" alt="img/icon002.png"/>';
			}

			?><tr>
				<td class="text-center"><?=$riscar.$j.$riscar2?></td>
				<td class="text-center"><?=$riscar.$row['prod_nb_id'].$riscar2?></td>
				<td><?=$riscar.$row['prod_tx_nome'].$riscar2?></td>
				<td class="text-center"><?=$riscar.valor_3_casas($row['orpr_tx_quantidade'],1).$riscar2?></td>
				<td class="text-center"><?=$riscar.valor($row['orpr_tx_valorUnitario'],1).$riscar2?></td>
				<td class="text-center"><?=$riscar.$string_descontos.$riscar2?></td>
				<td class="text-center"><?=$riscar.valor($row['orpr_tx_valor'],1).$riscar2?></td>
				<td class="text-center" <?=$js_exclusao?> ><?=$bt_excluir?></td>
			</tr><?php

			if ( $row['orpr_tx_status']!='inativo' ) {
				// SOMA APENAS SE ESTIVER ATIVO
				$qtde_itens++;
				$valor_total_venda += $row['orpr_tx_valor'];
			}
		}

		?><script type="text/javascript">
			document.getElementById('tag_codigo_venda').innerHTML = "<?=str_pad($id_venda,4,0,STR_PAD_LEFT)?>";
			document.getElementById('tag_qtde_itens').innerHTML = "<?=intval($qtde_itens)?>";
			document.getElementById('tag_valor_total_venda').innerHTML = "R$ <?=valor($valor_total_venda,1)?>";
			document.getElementById('ModalPagamentoLabel').innerHTML = "<b>VALOR TOTAL: R$ <?=valor($valor_total_venda,1)?></b>";
			document.getElementById('valor_venda').value = "<?=valor($valor_total_venda,1)?>";
		</script><?php
	}

	?></tbody>
	</table><?php
}






function index(){
	global $CONTEX;



	if ( intval($_SESSION[id_caixa])==0 || intval($_SESSION[id_pdv])==0 ) {
		// VERIFICA SE O USUÁRIO ESTÁ COM O CAIXA ABERTO
		$result  = query("SELECT * FROM caixa,pdv WHERE caix_nb_pdv=pdv_nb_id AND caix_tx_status='ativo' AND caix_nb_user='$_SESSION[user_nb_id]' LIMIT 1 ");
		$a_pdv = $result->fetch_assoc();

		if ( $a_pdv[caix_nb_id]>0 ) {
			$_SESSION[id_caixa] = $a_pdv[caix_nb_id];
			$_SESSION[id_pdv]   = $a_pdv[caix_nb_pdv];
			$nome_caixa = $a_pdv[pdv_tx_nome];

		} else {
			// SE NÃO TIVER, LIMPA A SESSÃO PARA EVITAR PROBLEMAS
			unset($_SESSION[id_caixa]);
			unset($_SESSION[id_pdv]);
			$nome_caixa = 'CAIXA FECHADO';
		}

	} else {
		$result  = query("SELECT pdv_tx_nome FROM pdv WHERE pdv_nb_id = '$_SESSION[id_pdv]' LIMIT 1 ");
		$a_pdv = $result->fetch_assoc();
		$nome_caixa = $a_pdv[pdv_tx_nome];
	}


	?><!DOCTYPE html>
	<html lang="en">
	<head>
		<title>CONTAINER Sistemas</title>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>

		<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />
		<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
		<script src="/contex20/assets/global/plugins/select2/js/i18n/pt-BR.js" type="text/javascript"></script>
		<link href="/contex20/assets/global/plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css" />
		<link href="/contex20/assets/global/plugins/select2/css/select2-bootstrap.min.css" rel="stylesheet" type="text/css" />
		<script src="/contex20/assets/global/plugins/jquery-inputmask/maskMoney.js" type="text/javascript"></script>

		<script type="text/javascript">
			function mascara_cpf_venda(z) {
				v = z.value;
				v=v.replace(/\D/g,"")                    //Remove tudo o que não é dígito
				v=v.replace(/(\d{3})(\d)/,"$1.$2")       //Coloca um ponto entre o terceiro e o quarto dígitos
				v=v.replace(/(\d{3})(\d)/,"$1.$2")       //Coloca um ponto entre o terceiro e o quarto dígitos
				                                         //de novo (para o segundo bloco de números)
				v=v.replace(/(\d{3})(\d{1,2})$/,"$1-$2") //Coloca um hífen entre o terceiro e o quarto dígitos
				z.value = v;
			}

			function mascara_data_venda(z) {
				v = z.value;
				v=v.replace(/\D/g,"");                    //Remove tudo o que não é dígito
			    v=v.replace(/(\d{2})(\d)/,"$1/$2");       
			    v=v.replace(/(\d{2})(\d)/,"$1/$2");       
			                                             
			    v=v.replace(/(\d{2})(\d{2})$/,"$1$2");
			    z.value = v;
			}
		</script>

		<style>
		/* Set height of the grid so .sidenav can be 100% (adjust if needed) */
		/*.row.content {height: 100%;}*/
		header{
			padding: 0.5% 0 0 0;
			width: 100%;
			float: left;
			background-color: #f1f1f1;
			border: 2px solid #e5e6e7;      
		}
		header p{
			width: 50%;
			float: left;
		}        
		header h1{
			margin: 0;
			padding: 0.76% 0;      
		}
		header p.text-right{
			padding: 0.73% 0;      
		}
		/* Set gray background color and 100% height */
		.sidenav {
			width: 30%;
			background-color: #f1f1f1;
			height: 72%;
		}
		.box_right{
			width: 70%;
			padding: 0.5%;
			height: 72%;      
		}
		.sidenav button{
			margin: 0 4%;
		}		
		/* Set black background color, white text and some padding */
		footer {
			position: fixed;
			width: 100%;
			background-color: #555;
			color: white;
			bottom: 0;
			padding: 0.5% 0 0 0;
		}
		.botoes{
			width: 100%;
			float: left;
			margin: 0 0 0 0;
			padding: 5% 0;      
		}
		.botoes button{
			margin: 1% 1% 1% 1%;
			text-align: left;
			line-height: 30px;
		}
		button.col-md-5{
			width: 48%;
		}
		.well{
			width: 92%;
			float: left;
			margin: 2% 0 0 4%;
			/*font-size: 8pt;*/
			text-align: center;
		}

		.box_itens{
			position: relative;
			padding: 0;
			height: 77.5%;
			overflow-y: auto;
		}

		.panel.panel-default{
			margin: 0.45% 0 0 0;
			padding: 1% 0 0 0;
		}
		.panel.panel-default h1{
			margin: 0;
		}
		.select2-container--bootstrap .select2-selection--single{
			height: 42px;
			padding: 0.5% 0 0.5% 1.5%;
			font-size: 16pt;
			border-radius: 4px 0 0 4px;
		}
		/* On small screens, set height to 'auto' for sidenav and grid */
		@media screen and (max-width: 767px) {
			.sidenav {
			height: auto;
			padding: 15px;
			}
			.row.content {height: auto;} 
		}
		.select2-dropdown {
			z-index: 100;
		}
		</style>
		<script type="text/javascript">
			function atualiza_lista_pagamento(){
				var a_dados = {
					acao: "listar_pagamentos",
					id_venda: document.getElementById('id_venda').value,
					valor_venda: document.getElementById('valor_venda').value
				};

				$.post("index.php", a_dados, function(result){
					$("#lista_recebimentos").html(result);// ATUALIZA A LISTAGEM DE PAGAMENTOS
				});
			}

			function atualiza_exibicao_venda(){

				var dados_grid = {
					acao: "gera_grid_produtos",
					id_venda: document.getElementById('id_venda').value
				};

				$.post("index.php", dados_grid, function(result){
					$("#grid_produtos").html(result);// ATUALIZA A LISTA DE PRODUTOS

					// REINICIA OS CAMPOS
					document.getElementById('qtde_produto').value = '1,000';
					document.getElementById('desconto_porcentagem').value = '0,00';
					document.getElementById('desconto_reais').value = '0,00';

					$('#campo_produto').val(null).trigger('change');// LIMPA O CAMPO DE BUSPAS POR PRODUTO
					$('#campo_produto').select2('open');// ADICIONA O FOCO NO CAMPO
				});
			}

			function remover_produto(item,id_orpr){

				if ( confirm('Deseja Remover o item '+item+'?') ) {
					var a_dados = {
						acao: "remove_produto",
						id_orpr: id_orpr
					};

					$.post("funcoes_pdv.php", a_dados, function(result){
						$("#operacoes").html(result);

						var dados_grid = {
							acao: "gera_grid_produtos",
							id_venda: document.getElementById('id_venda').value
						};
						$.post("index.php", dados_grid, function(result){
							$("#grid_produtos").html(result);// ATUALIZA A LISTA DE PRODUTOS
						});
					});
				}
			}

			function remover_pagamento(forma,id_pagamento){

				if ( confirm('Deseja Remover o pagamento em '+forma+'?') ) {
					var a_dados = {
						acao: "remove_pagamento",
						id_pagamento: id_pagamento
					};

					$.post("funcoes_pdv.php", a_dados, function(result){
						$("#operacoes").html(result);

						atualiza_lista_pagamento()
					});
				}
			}

			function seleciona_cliente(id_entidade){
				var a_dados = {
					acao: "seleciona_cliente",
					id_entidade: id_entidade,
					id_venda: document.getElementById('id_venda').value,
				};

				$.post("funcoes_pdv.php", a_dados, function(result){
					$("#operacoes").html(result);
					$('#modalCliente').modal('hide');
				});
			}

			function seleciona_venda(id_ordem){
				var id_venda = document.getElementById('id_venda').value;
				if ( id_venda>0 ) {
					// SE JÁ ESTIVER COM UMA VENDA ABERTA, PERGUNDA SE QUER DESCARTAR A ATUAL, PARA CARREGAR A SELECIONADA
					if ( confirm("Deseja descartar a venda atual, para carregar a selecionada?") ) {
						document.form_abre_venda.id.value = id_ordem;
						document.getElementById("form_abre_venda").submit();
					}
				} else {
					// SE NÃO ESTIVER COM UMA VENDA ABERTA, CARREGA A VENDA SELECIONADA
					document.form_abre_venda.id.value = id_ordem;
					document.getElementById("form_abre_venda").submit();
				}
			}

			function descarta_venda(){
				if ( confirm('Deseja descartar a Venda?') ) {
					window.location.href = "index.php";
				}
			}

			function cancelar_venda(){

				if ( confirm('Deseja CANCELAR a Venda?') ) {
					var a_dados = {
						acao: "cancela_venda",
						id_venda: document.getElementById('id_venda').value,
					};

					$.post("funcoes_pdv.php", a_dados, function(result){
						$("#operacoes").html(result);
					});
				}
			}

			function imprimir_venda(){
				var id_venda = document.getElementById('id_venda').value;
				if ( id_venda>0 ) {
					document.form_imprime_venda.id.value = id_venda;
					document.getElementById("form_imprime_venda").submit();
				}
			}

			function imprimir_fechamento(){
				document.form_imprime_caixa.id.value = "<?=$_SESSION[id_caixa]?>";
				document.getElementById("form_imprime_caixa").submit();
			}
		</script>
	</head>
	<body>
	<header class="container-fluid">
		<p class="text-left col-md-4">
			<img src="img/logo.png" alt="img/logo.png"/>
		</p>
		<h1 class="col-md-4 text-center"><b><?=$nome_caixa?></b></h1>  
		<p class="text-right col-md-4">
		<span><b>Operador:</b> </span><?=$_SESSION[user_tx_login]?><br>
		<?=date("d/m/Y H:i");?>  
		</p>
	</header>  
	<div class="container-fluid">
		<div class="row content" id="div_principal">
		<div class="col-sm-12 col-md-12" style="background-color: #F1F1F1; padding: 1% 0 1% 0;">
			<div class="col-sm-10 col-md-10">
				<label for="email">Produto:</label>
				<div class="input-group col-sm-12 col-md-12">
					<select type="text" class="form-control" id="campo_produto"></select>
					<span class="input-group-btn">
					<button class="btn btn-default" type="button" style="height: 42px;">
						<span class="glyphicon glyphicon-search"></span>
					</button>
					</span>
				</div>
			</div>
			<div class="col-sm-2 col-md-2">
				<label class="control-label col-sm-6" for="qtde_produto">Quantidade:</label>			
				<div class="input-group col-sm-12 col-md-12">			
					<input type="text" class="form-control" id="qtde_produto" onfocus="this.select()" placeholder="1,000" style="height: 42px;border-radius: 4px;" />				
					<script>$("#qtde_produto").maskMoney({ allowNegative: true, thousands:".", decimal:",", affixesStay: false, precision: 3});</script>
				</div>							
			</div>
		</div>
		<div class="col-sm-3 sidenav">
			<br>
			<!-- <label for="email">Produto:</label>
			<div class="input-group">
				<select type="text" class="form-control" id="campo_produto"></select>
				<span class="input-group-btn">
				<button class="btn btn-default" type="button">
					<span class="glyphicon glyphicon-search"></span>
				</button>
				</span>
			</div> -->
			<div class="form-group">
			<label for="email">Cliente:</label>
			<div class="input-group">
				<span id="div_nome_cliente">Cliente Padrão</span>
			</div>
			<br>			
			<?php


			if ( $_POST[id]>0 ) {// SE ESTIVER CARREGANDO OS DADOS DE UMA VENDA ESPECÍFICA
				$a_venda   = carregar('ordem',$_POST[id]);
				$a_cliente = carregar('entidade',$a_venda[orde_nb_entidade]);

			} else {
				// SE ESTIVER INICIANDO UMA NOVA VENDA, BUSCA PELO CLIENTE PADRÃO
				$result = query("SELECT enti_nb_id FROM entidade WHERE enti_tx_clientePadrao = 'sim' AND enti_tx_status != 'inativo' LIMIT 1 ");
				$a_cliente = $result->fetch_assoc();

				if ( $a_cliente[enti_nb_id]==0 ) {
					?><script type="text/javascript">alert('ATENÇÃO: O cliente padrão não foi configurado!');</script><?php
				}
			}

			?><input type="hidden" id="id_venda" value="<?=$_POST[id]?>">
			<input type="hidden" id="id_entidade" value="<?=$a_cliente[enti_nb_id]?>">
			<input type="hidden" id="valor_venda" value="">
			<input type="hidden" id="operacoes" value="">

			<form action="impressao.php" id="form_imprime_venda" name="form_imprime_venda" method="post" target="_blank">
				<input type="hidden" name="acao" value="imprimir_venda">
				<input type="hidden" name="id" value="">
			</form>
			<form action="impressao.php" id="form_imprime_caixa" name="form_imprime_caixa" method="post" target="_blank">
				<input type="hidden" name="acao" value="imprimir_caixa">
				<input type="hidden" name="id" value="">
			</form>
			<form action="index.php" id="form_abre_venda" name="form_abre_venda" method="post">
				<input type="hidden" name="acao" value="index">
				<input type="hidden" name="id" value="">
			</form>
			

			<script type="text/javascript">
				$.fn.select2.defaults.set("theme", "bootstrap");
				$(window).bind("load", function() {
					$('#campo_produto').select2({
						language: 'pt-BR',
						placeholder: 'Selecione um item',
						allowClear: true,
						ajax: {
							url: 'index.php?acao=buscar_produto',
							dataType: 'json',
							delay: 250,
							data: function (params) {
								var query = {
									q: processa_digitacao(params.term),
								}
								// Query parameters will be ?search=[term]&type=public
								return query;
							},
							processResults: function (data) {
								return {
									results: data
								};
							},
							cache: true
						}
					});
				});


				function processa_digitacao(termo){
					if (termo.indexOf("*") != -1) {// VERIFICA SE DIGITOU *
						var qtde_produto = valor_reais_3(parseInt(termo));
						document.getElementById('qtde_produto').value = qtde_produto;
						$('.select2-search__field').val("");
						termo = "";
					}
					return termo;
				}


				$('#campo_produto').on('select2:select', function (e) {
					var data = e.params.data;
					// alert(data.id);
					var dados_venda = {
						acao : "adiciona_produto",
						id_produto : data.id,
						id_venda: document.getElementById('id_venda').value,
						id_entidade: document.getElementById('id_entidade').value,
						quantidade: document.getElementById('qtde_produto').value,
						desconto_porcentagem: document.getElementById('desconto_porcentagem').value,
						desconto_reais: document.getElementById('desconto_reais').value
					};

					$.post("funcoes_pdv.php", dados_venda, function(msg){
						$("#operacoes").html(msg);
						atualiza_exibicao_venda();
					})
				});
			</script>



			<script type="text/javascript">

				function valor_reais_3(valor){
					// CONVERTE DO FORMATO DA BASE DE DADOS PARA O NOSSO

					valor = valor.toFixed(3); //Senpre deixa com três casas decimais

					if(valor < 0)	//Verifica se é um numero negativo
						negativo = 'sim';
					else
						negativo = 'nao';

					var tmp = valor+''; //Converte para string
					tmp = tmp.replace(".", "");
					tmp = tmp.replace("-", "");
					tmp = tmp.replace(/([0-9]{3})$/g, ",$1");

					if( tmp.length > 6 )
						tmp = tmp.replace(/([0-9]{3}),([0-9]{3}$)/g, ".$1,$2");		//Mil

					if( tmp.length > 9 )  
						tmp = tmp.replace(/([0-9]{3}).([0-9]{3}),([0-9]{3})$/g,'.$1.$2,$3');	//Milhões

					if( tmp.length > 11 )  
						tmp = tmp.replace(/([0-9]{3}).([0-9]{3}).([0-9]{3}),([0-9]{3})$/g,'.$1.$2.$3,$4');	//Bilhoes

					if( tmp[0]+'' == '.' )
						tmp = tmp.substring(1);


					if(negativo == 'sim')
						tmp = '-'+tmp;

					return tmp;
				};
			</script>



			<!-- <label class="control-label col-sm-6" for="qtde_produto">Quantidade:</label>
			<div class="col-sm-6">
				<input type="text" class="form-control" id="qtde_produto" placeholder="0">
				<script>$("#qtde_produto").maskMoney({ allowNegative: true, thousands:".", decimal:",", affixesStay: false});</script>
			</div>
			<br><br> -->
			<!-- <label class="control-label col-sm-6" for="unitario">Vlr. Unitário:</label>
			<div class="col-sm-6">
				<input type="text" class="form-control" id="unitario" placeholder="0,00" readonly="">
			</div>
			<br><br> -->
			<label class="control-label col-sm-6" for="desconto_porcentagem">Desconto (%):</label>
			<div class="col-sm-6">
				<input type="text" class="form-control" id="desconto_porcentagem" placeholder="0,00">
				<script>$("#desconto_porcentagem").maskMoney({ allowNegative: true, thousands:".", decimal:",", affixesStay: false});</script>
			</div>
			<br><br>
			<label class="control-label col-sm-6" for="desconto_reais">Desconto (R$):</label>
			<div class="col-sm-6">
				<input type="text" class="form-control" id="desconto_reais" placeholder="0,00">
				<script>$("#desconto_reais").maskMoney({ allowNegative: true, thousands:".", decimal:",", affixesStay: false});</script>
			</div>
			<br><br>
			<!-- <label class="control-label col-sm-6" for="pwd">Total:</label>
			<div class="col-sm-6">
				<input type="text" class="form-control" id="pwd" placeholder="0,00" readonly="readonly">
			</div> -->
			<div class="botoes">
				<button type="button" class="btn btn-default col-md-5" data-toggle="modal" data-target="#modalPagamento"><img src="img/icon004.png" alt="img/icon004.png"/> Pagamento (F1)</button>
				<button type="button" class="btn btn-default col-md-5" data-toggle="modal" data-target="#modalOperacoes"><img src="img/icon002.png" alt="img/icon002.png"/> Operações (F2)</button>
				<button type="button" class="btn btn-default col-md-5" data-toggle="modal" data-target="#modalCliente"><img src="img/icon003.png" alt="img/icon003.png"/> Clientes (F3)</button>
				<button type="button" class="btn btn-default col-md-5" data-toggle="modal" data-target="#modalVendas"><img src="img/icon006.png" alt="img/icon006.png"/> Vendas (F4)</button>
				<button type="button" class="btn btn-default col-md-5" onclick="imprimir_venda();"><img src="img/icon005.png" alt="img/icon005.png"/> Imprimir (F5)</button>
				<button type="button" class="btn btn-default col-md-5" data-toggle="modal" data-target="#modalFechamento"><img src="img/icon001.png" alt="img/icon001.png"/> Fechamento (F6)</button>
			</div>
			</div>
		</div>

		<div class="col-sm-9 box_right">
			<div class="panel panel-default col-md-12 box_itens">
			<div class="table-responsive" id="grid_produtos">
			</div>
			</div>
			<div class="panel panel-default col-md-12">
			<form action="/action_page.php">
				<div class="form-group col-md-4">
				<label for="email">Venda:</label>
				<h1 id="tag_codigo_venda"></h1>
				</div>
				<div class="form-group col-md-3">
				<label for="email">Itens:</label>
				<h1><b id="tag_qtde_itens">0</b></h1>
				</div>
				<div class="form-group col-md-5">
				<label for="email">Valor Total:</label>
				<h1><b id="tag_valor_total_venda">R$ 0,00</b></h1>
				</div>
			</form>
			</div>
		</div>
		</div>

		<!-- Modal Pagamento -->
		<div class="modal fade" id="modalPagamento" tabindex="-1" role="dialog" aria-labelledby="ModalPagamentoLabel" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
				<h2 class="modal-title" id="ModalPagamentoLabel"><b>PAGAMENTO</b></h2>
				<button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
					<span aria-hidden="true">&times;</span>
				</button>
				</div>
				<div class="col-sm-6 margin-bottom-5">
					<label><b>Valor:</b></label>
					<input name="valor_pagamento" id="valor_pagamento" value="" autocomplete="off" type="text" class="form-control input-sm">
					<script>$("#valor_pagamento").maskMoney({ allowNegative: true, thousands:".", decimal:",", affixesStay: false});</script>
				</div>
				<div class="col-sm-6 margin-bottom-5">
					<label><b>Parcelas:</b></label>
					<select name="parcelas_pagamento" id="parcelas_pagamento" class="form-control input-sm">
						<option value="1" selected>1</option>
						<option value="2">2</option>
						<option value="3">3</option>
						<option value="4">4</option>
						<option value="5">5</option>
						<option value="6">6</option>
						<option value="7">7</option>
						<option value="8">8</option>
						<option value="9">9</option>
						<option value="10">10</option>
						<option value="11">11</option>
						<option value="12">12</option>
					</select>
				</div>
				<div class="modal-body col-md-12"><?php
					$bt_atalho = 0;
					$string_js_atanho = '';
					$sql_forma = "SELECT * FROM forma ORDER BY FIELD (form_tx_nome,'DINHEIRO') DESC, form_tx_nome";
					$result = query($sql_forma);
					while($row = $result->fetch_assoc()){
						$bt_atalho++;
						?><button type="button" class="btn btn-default col-md-4 botao_forma" value="<?=$row['form_nb_id']?>"><?=$row['form_tx_nome']." (F$bt_atalho)"?></button><?php

						$string_js_atanho .= '
							case '.($bt_atalho+111).':
								e.preventDefault();
								adiciona_pagamento('.$row['form_nb_id'].');
							break;
						';
					}
				?></div>
				<div class="modal-body col-md-12">
					<div id="lista_recebimentos"></div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
					<button type="button" class="btn btn-primary" id="bt_adicionar_pagamento">Finalizar Venda</button>
				</div>
			</div>
			</div>
			<script type="text/javascript">
				$( "#bt_adicionar_pagamento" ).click(function() {
					if ( confirm("Deseja Finalizar a Venda?") ) {
						var id_venda = $("#id_venda").val();

						var a_dados = {
							acao: "finaliza_venda",
							id_venda: id_venda
						}

						$.post("funcoes_pdv.php", a_dados, function( data ) {
							$("#operacoes").html(data);
						});
					}
				});


				$(".botao_forma").click(function() {
					var id_forma = $(this).val();
					adiciona_pagamento(id_forma);
				});

				// FUNÇÃO UTILIZADA PARA LANÇAR O PAGAMENTO FEITO
				function adiciona_pagamento(id_forma){
					var valor_pagamento = $("#valor_pagamento").val();
					var parcelas_pagamento = $("#parcelas_pagamento").val();
					var id_venda = $("#id_venda").val();

					var a_dados = {
						acao: "adiciona_pagamento",
						id_forma: id_forma,
						valor_pagamento: valor_pagamento,
						parcelas_pagamento: parcelas_pagamento,
						id_venda: id_venda
					}

					$.post("funcoes_pdv.php", a_dados, function( data ) {
						$("#operacoes").html(data);
					});
				}


				function testa_atalhos_pagamento(keyCode,e){
					switch (keyCode) {
						<?=$string_js_atanho?>
					}
				}
			</script>
		</div>

		<script type="text/javascript">
			$('#modalPagamento').on('shown.bs.modal', function () {
				atualiza_lista_pagamento();
			})
		</script>
		<!-- Modal Pagamento -->






		<!-- Modal Clientes -->
		<div class="modal fade" id="modalCliente" tabindex="-1" role="dialog" aria-labelledby="ModalClienteLabel" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
				<h4 class="modal-title" id="ModalClienteLabel"><b>CLIENTES</b></h4>
				<button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
					<span aria-hidden="true">&times;</span>
				</button>
				</div>
				<div class="modal-body col-md-12">
					<div id="lista_clientes"></div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
					<button type="button" class="btn btn-primary" id="bt_buscar_clientes">Buscar</button>
				</div>
			</div>
			</div>
		</div>

		<script type="text/javascript">
			$('#modalCliente').on('shown.bs.modal', function () {
				$.post("index.php", {acao: 'buscar_cliente'}, function(result){
					$("#lista_clientes").html(result);// ATUALIZA A LISTAGEM DE PAGAMENTOS
				});
			});
		</script>
		<!-- Modal Clientes -->





		<!-- Modal Abertura de Caixa -->
		<div class="modal fade" id="modalAbertura" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="ModalAberturaLabel" aria-hidden="true">
			<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
				<h4 class="modal-title" id="ModalAberturaLabel"><b>ABERTURA DE CAIXA</b></h4>
				</div>
				<div class="modal-body col-md-12">
					<?php
					$c[] = combo_bd('!PDV','pdv_abertura','',7,'pdv');
					$c[] = campo('Valor&nbsp;Abertura','valor_abertura','',5,'MASCARA_VALOR');
					linha_form($c);
					?>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-primary" id="bt_cadastra_abertura">Gravar</button>
				</div>
			</div>
			</div>
		</div>

		<script type="text/javascript">

			$("#bt_cadastra_abertura").click(function() {
				var id_pdv = $("#pdv_abertura").val();
				var valor_abertura = $("#valor_abertura").val();

				var a_dados = {
					acao: "abertura_caixa",
					id_pdv: id_pdv,
					valor_abertura: valor_abertura
				}

				$.post("funcoes_pdv.php", a_dados, function( data ) {
					$("#operacoes").html(data);
				});
			});

			<?php
			if ( intval($_SESSION[id_caixa])==0 ) {
				// SE O CAIXA AINDA NÃO ESTIVER ABERTO, EXIBE A TELA DE ABERTURA DO CAIXA
				?>$("#modalAbertura").modal("show");<?php
			}
			?>			
		</script>
		<!-- Modal Abertura de Caixa -->






		<!-- Modal Operações -->
		<div class="modal fade" id="modalOperacoes" tabindex="-1" role="dialog" aria-labelledby="ModalOperacoesLabel" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
				<h4 class="modal-title" id="ModalOperacoesLabel"><b>OPERAÇÕES DE CAIXA</b></h4>
				<button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
					<span aria-hidden="true">&times;</span>
				</button>
				</div>
				<div class="modal-body col-md-12">
					<?php
					$c_op[] = combo('Operação','operacao_caixa','',3,array('','Sangria','Suprimento'),' id="operacao_caixa" ');
					$c_op[] = campo('Valor&nbsp;Operação','valor_operacao_caixa','',3,'MASCARA_VALOR');
					$c_op[] = campo('Observação','obs_operacao_caixa','',6);
					linha_form($c_op);
					?>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
					<button type="button" class="btn btn-primary" id="bt_cadastra_operacao">Gravar</button>
				</div>
			</div>
			</div>
		</div>

		<script type="text/javascript">

			$("#bt_cadastra_operacao").click(function() {
				var operacao_caixa = $("#operacao_caixa").val();
				var valor_operacao_caixa = $("#valor_operacao_caixa").val();
				var obs_operacao_caixa = $("#obs_operacao_caixa").val();

				var a_dados = {
					acao: "operacao_caixa",
					operacao_caixa: operacao_caixa,
					valor_operacao_caixa: valor_operacao_caixa,
					obs_operacao_caixa: obs_operacao_caixa
				}

				$.post("funcoes_pdv.php", a_dados, function( data ) {
					$("#operacoes").html(data);
				});
			});
		</script>
		<!-- Modal Operações -->






		<!-- Modal Fechamento de Caixa -->
		<div class="modal fade" id="modalFechamento" tabindex="-1" role="dialog" aria-labelledby="ModalFechamentoLabel" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
				<h4 class="modal-title" id="ModalFechamentoLabel"><b>FECHAMENTO DE CAIXA</b></h4>
				<button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
					<span aria-hidden="true">&times;</span>
				</button>
				</div>
				<div class="modal-body col-md-12">
					<?php
					$sql_forma = query("SELECT * FROM forma WHERE form_tx_status!='inativo' ORDER BY form_tx_nome ASC");
					while($a_forma = carrega_array($sql_forma)){
						$c_fech[] = campo($a_forma[form_tx_nome].' (R$)','valor_fechamento['.$a_forma[form_nb_id].']','',3,'MASCARA_VALOR');
					}
					?><form id="form_fechamento_caixa" onsubmit="return false;">
						<input type="submit" id="submitButtonFechamentoCaixa"  name="submitButtonFechamentoCaixa" value="Submit" style="display: none">
						<input type="hidden" name="acao" value="fechamento_caixa"><?php
						linha_form($c_fech);
					?></form>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
					<button type="button" class="btn btn-primary" id="bt_cadastra_fechamento">Gravar</button>
				</div>
			</div>
			</div>
		</div>

		<script type="text/javascript">

			$("#form_fechamento_caixa").submit(function(event) {

				var dados = $('#form_fechamento_caixa').serialize();

				jQuery.ajax({
					type: "POST",
					url: "funcoes_pdv.php",
					data: dados,
					beforeSend: function(){
						console.log('ENVIANDO...');
					},
					success: function( data ){
						$("#operacoes").html(data);
					}
				});
			});

			$("#bt_cadastra_fechamento").click(function() {
				$('#form_fechamento_caixa').submit();
			});

		</script>
		<!-- Modal Fechamento de Caixa -->






		<!-- Modal Vendas -->
		<div class="modal fade" id="modalVendas" tabindex="-1" role="dialog" aria-labelledby="ModalVendasLabel" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
				<h4 class="modal-title" id="ModalVendasLabel"><b>VENDAS</b></h4>
				<button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
					<span aria-hidden="true">&times;</span>
				</button>
				</div>
				<div class="modal-body col-md-12">
					<div id="lista_vendas"></div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
					<button type="button" class="btn btn-primary" id="bt_buscar_venda">Buscar</button>
				</div>
			</div>
			</div>
		</div>

		<script type="text/javascript">
			$('#modalVendas').on('shown.bs.modal', function () {
				var id_venda = document.getElementById('id_venda').value;
				$.post("index.php", {acao: 'buscar_venda', id_venda: id_venda}, function(result){
					$("#lista_vendas").html(result);// ATUALIZA A LISTAGEM DE VENDAS
				});
			});
			$('#modalVendas').on('hide.bs.modal', function () {
				$("#lista_vendas").html('');// APAGA A LISTAGEM DE VENDAS, AO FECHAR O MODAL
			});
		</script>
		<!-- Modal Vendas -->



		<?php
			if ( $_POST[id]>0 ) {
				// SE ESTIVER ABRINDO UMA VENDA ESPECÍFICA, ACIONA OS MECANISMOS PARA ATUALIZAR A VENDA
				?><script type="text/javascript">
					atualiza_exibicao_venda();
				</script><?php
			}
		?>


	</div>

	<footer class="container-fluid">
		<p class="text-left" style="width: 50%;float: left;"><b>Licenciado a:</b> Pet Shop Reino Animal</p>
		<p class="text-right" style="width: 50%;float: left;"><b>Desenvolvido por:</b> <a href="http://www.containerti.com.br">ContainerTI</a></p>
	</footer>
	<script type="text/javascript">
		if (window.screenTop && window.screenY) {
		window.document.getElementById("div_principal").style.height=screen.height+"px";
		}else{
		window.document.getElementById("div_principal").style.height=window.innerHeight+"px";
		}
	</script>

	<script type="text/javascript">
		$(function(){
			// PROCESSA OS ATALHOS DA TELA INICIAL DO PDV
			$("html").keydown(function(e){
				// e.preventDefault();
				var keyCode = e.keyCode || e.which;

				// AQUI VERIFICA SE DEVE VALIDAR OS ATALHOS DE PAGAMENTO OU DO CAIXA
				if ( ($("#modalPagamento").data('bs.modal') || {}).isShown ) {
					testa_atalhos_pagamento(keyCode,e);
				} else {
					testa_atalhos_pdv(keyCode,e);
				}
			});
		});


		function testa_atalhos_pdv(keyCode,e){
			switch (keyCode) {
				case 112:
					// ABRE O MODAL DE PAGAMENTOS (F1)
					e.preventDefault();//DESATIVA A FUNCIONALIDADE DA TECLA PRESCIONADA
					$("#modalPagamento").modal("show");
				break;

				case 113:
					// ABRE O MODAL DE OPERAÇÕES (F2)
					e.preventDefault();//DESATIVA A FUNCIONALIDADE DA TECLA PRESCIONADA
					$("#modalOperacoes").modal("show");
				break;

				case 114:
					// ABRE O MODAL DE CLIENTES (F3)
					e.preventDefault();//DESATIVA A FUNCIONALIDADE DA TECLA PRESCIONADA
					$("#modalCliente").modal("show");
				break;

				case 115:
					// ABRE O MODAL DE VENDAS (F4)
					e.preventDefault();//DESATIVA A FUNCIONALIDADE DA TECLA PRESCIONADA
					$("#modalVendas").modal("show");
				break;

				case 116:
					// IMPRIME A VENDA ATUAL (F5)
					e.preventDefault();//DESATIVA A FUNCIONALIDADE DA TECLA PRESCIONADA
					imprimir_venda();
				break;

				case 117:
					// ABRE O MODAL DE FECHAMENTO DE CAIXA (F6)
					e.preventDefault();//DESATIVA A FUNCIONALIDADE DA TECLA PRESCIONADA
					$("#modalFechamento").modal("show");
				break;

				default:
					// IMPRIME O CÓDIGO, CASO NÃO SEJA NENHUMA DAS OPÇÕES
					console.log(keyCode);
			}
		}

	</script>

	</body>
	</html><?php
}