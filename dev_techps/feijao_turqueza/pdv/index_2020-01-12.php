<?php
include "../conecta.php";




function listar_pagamentos(){

	$id_venda = intval($_POST[id_venda]);

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
		$sql = "SELECT * FROM pagamento,forma WHERE paga_nb_ordem = '$id_venda' AND paga_tx_status != 'inativo' AND paga_nb_forma=form_nb_id ORDER BY paga_nb_id ASC";

		$result = query($sql);
		while($row = $result->fetch_assoc()){

			if ($row['orpr_tx_descontoReais']>0)
				$string_descontos = valor($row['orpr_tx_descontoReais'],1).'&nbsp;('.valor($row['orpr_tx_descontoPorcentagem']).'%)';
			else
				$string_descontos = '';

			?><tr>
				<td class="text-center"><?=$row['paga_nb_id']?></td>
				<td><?=$row['form_tx_nome']?></td>
				<td class="text-center"><?=valor($row['paga_tx_valor'],1)?></td>
				<td class="text-center"><img src="img/icon002.png" alt="img/icon002.png"/></td>
			</tr><?php

			$valor_total_pago += $row['paga_tx_valor'];
		}

		?><tr>
			<td class="text-center"></td>
			<td><b>TOTAL:</b></td>
			<td class="text-center"><b><?=valor($valor_total_pago,1)?></b></td>
			<td class="text-center"></td>
		</tr><?php
	}

	?></tbody>
	</table><?php
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
		<script type="text/javascript">
			function atualiza_lista_pagamento(){
				var a_dados = {
					acao: "listar_pagamentos",
					id_venda: document.getElementById('id_venda').value
				};

				$.post("index.php", a_dados, function(result){
					$("#lista_recebimentos").html(result);// ATUALIZA A LISTAGEM DE PAGAMENTOS
				});
			}
		</script>
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
			<label class="control-label col-sm-6" for="unitario">Vlr. Unitário:</label>
			<div class="col-sm-6">
				<input type="text" class="form-control" id="unitario" placeholder="0,00" readonly="">
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
				<button type="button" class="btn btn-default col-md-5" data-toggle="modal" data-target="#modalPagamento"><img src="img/icon004.png" alt="img/icon004.png"/> Pagamento (F2)</button>
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

		<!-- Modal -->
		<div class="modal fade" id="modalPagamento" tabindex="-1" role="dialog" aria-labelledby="ModalPagamentoLabel" aria-hidden="true">
			<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
				<h5 class="modal-title" id="ModalPagamentoLabel"><b>PAGAMENTO</b></h5>
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
					$sql_forma = "SELECT * FROM forma ORDER BY form_tx_nome ASC";
					$result = query($sql_forma);
					while($row = $result->fetch_assoc()){
						?><button type="button" class="btn btn-default col-md-4 botao_forma" value="<?=$row['form_nb_id']?>"><?=$row['form_tx_nome']?></button><?php
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
				});
			</script>
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