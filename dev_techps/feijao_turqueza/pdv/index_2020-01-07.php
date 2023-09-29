<?php
include "../conecta.php";





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
		$sql = "SELECT * FROM orpr,produto WHERE orpr_nb_ordem = '$id_venda' AND orpr_tx_status != 'inativo' AND orpr_nb_produto=prod_nb_id ORDER BY orpr_nb_id ASC";

		$result = query($sql);
		while($row = $result->fetch_assoc()){

			if ($row['orpr_tx_descontoReais']>0)
				$string_descontos = valor($row['orpr_tx_descontoReais'],1).'&nbsp;('.valor($row['orpr_tx_descontoPorcentagem']).'%)';
			else
				$string_descontos = '';

			?><tr>
				<td class="text-center"><?=++$j?></td>
				<td class="text-center"><?=$row['prod_nb_id']?></td>
				<td><?=$row['prod_tx_nome']?></td>
				<td class="text-center"><?=valor($row['orpr_tx_quantidade'],1)?></td>
				<td class="text-center"><?=valor($row['orpr_tx_valorUnitario'],1)?></td>
				<td class="text-center"><?=$string_descontos?></td>
				<td class="text-center"><?=valor($row['orpr_tx_valor'],1)?></td>
				<td class="text-center"><img src="img/icon002.png" alt="img/icon002.png"/></td>
			</tr><?php

			$qtde_itens++;
			$valor_total_venda += $row['orpr_tx_valor'];
		}

		?><script type="text/javascript">
			document.getElementById('tag_codigo_venda').innerHTML = "<?=str_pad($id_venda,4,0,STR_PAD_LEFT)?>";
			document.getElementById('tag_qtde_itens').innerHTML = "<?=intval($qtde_itens)?>";
			document.getElementById('tag_valor_total_venda').innerHTML = "R$ <?=valor($valor_total_venda,1)?>";
		</script><?php
	}

	?></tbody>
	</table><?php
}






function index(){
	global $CONTEX;

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
			height: 87%;
		}
		.box_right{
			width: 70%;
			padding: 0.5%;
			height: 87%;      
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
		/* On small screens, set height to 'auto' for sidenav and grid */
		@media screen and (max-width: 767px) {
			.sidenav {
			height: auto;
			padding: 15px;
			}
			.row.content {height: auto;} 
		}
		</style>
	</head>
	<body>
	<header class="container-fluid">
		<p class="text-left col-md-4">
			<img src="img/logo.png" alt="img/logo.png"/>
		</p>
		<h1 class="col-md-4 text-center"><b>Caixa 001</b></h1>  
		<p class="text-right col-md-4">
		<span><b>Operador:</b> </span>Colaborador<br>
		<?=date("d/m/Y H:i");?>  
		</p>
	</header>  
	<div class="container-fluid">
		<div class="row content" id="div_principal">
		<div class="col-sm-3 sidenav">
			<br>
			<div class="form-group">
			<label for="email">Cliente:</label>
			<div class="input-group">
				<span>Cliente Padrão</span>
			</div>
			<br>
			<label for="email">Produto:</label>
			<div class="input-group">
				<select type="text" class="form-control" id="campo_produto"></select>
				<span class="input-group-btn">
				<button class="btn btn-default" type="button">
					<span class="glyphicon glyphicon-search"></span>
				</button>
				</span>
			</div>
			<br>

			<input type="hidden" id="id_venda" value="">
			<input type="hidden" id="operacoes" value="">
			

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
							processResults: function (data) {
								return {
									results: data
								};
							},
							cache: true
						}
					});
				});

				$('#campo_produto').on('select2:select', function (e) {
					var data = e.params.data;
					// alert(data.id);
					var dados_venda = {
						acao : "adiciona_produto",
						id_produto : data.id,
						id_venda: document.getElementById('id_venda').value,
						quantidade: document.getElementById('qtde_produto').value,
						desconto_porcentagem: document.getElementById('desconto_porcentagem').value,
						desconto_reais: document.getElementById('desconto_reais').value
					};

					$.post("funcoes_pdv.php", dados_venda, function(msg){
						$("#operacoes").html(msg);
						// alert(document.getElementById('id_venda').value);
						// alert(msg);
						
						var dados_grid = {
							acao: "gera_grid_produtos",
							id_venda: document.getElementById('id_venda').value
						};

						$.post("index.php", dados_grid, function(result){
							$("#grid_produtos").html(result);// ATUALIZA A LISTA DE PRODUTOS							
							$('#campo_produto').val(null).trigger('change');// LIMPA O CAMPO DE BUSPAS POR PRODUTO
							$('#campo_produto').select2('open');// ADICIONA O FOCO NO CAMPO

							// REINICIA OS CAMPOS
							document.getElementById('qtde_produto').value = '1,00';
							document.getElementById('desconto_porcentagem').value = '0,00';
							document.getElementById('desconto_reais').value = '0,00';
						});
					})
				});
			</script>



			<label class="control-label col-sm-6" for="qtde_produto">Quantidade:</label>
			<div class="col-sm-6">
				<input type="text" class="form-control" id="qtde_produto" placeholder="0">
				<script>$("#qtde_produto").maskMoney({ allowNegative: true, thousands:".", decimal:",", affixesStay: false});</script>
			</div>
			<br><br>
			<label class="control-label col-sm-6" for="pwd">Vlr. Unitário:</label>
			<div class="col-sm-6">
				<input type="text" class="form-control" id="pwd" placeholder="0,00" readonly="">
			</div>
			<br><br>
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
			<label class="control-label col-sm-6" for="pwd">Total:</label>
			<div class="col-sm-6">
				<input type="text" class="form-control" id="pwd" placeholder="0,00" readonly="readonly">
			</div>
			<div class="botoes">
				<button type="button" class="btn btn-default col-md-5"><img src="img/icon004.png" alt="img/icon004.png"/> Pagamento (F2)</button>
				<button type="button" class="btn btn-default col-md-5"><img src="img/icon002.png" alt="img/icon002.png"/> Cancelar (F4)</button>
				<button type="button" class="btn btn-default col-md-5"><img src="img/icon003.png" alt="img/icon003.png"/> Clientes (F5)</button>
				<button type="button" class="btn btn-default col-md-5"><img src="img/icon006.png" alt="img/icon006.png"/> Vendas (F6)</button>
				<button type="button" class="btn btn-default col-md-5"><img src="img/icon005.png" alt="img/icon005.png"/> Recebimentos (F7)</button>
				<button type="button" class="btn btn-default col-md-5"><img src="img/icon001.png" alt="img/icon001.png"/> Consulta (F8)</button>
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
	</body>
	</html><?php
}