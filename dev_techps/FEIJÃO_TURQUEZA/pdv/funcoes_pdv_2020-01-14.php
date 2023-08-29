<?php
include $_SERVER['DOCUMENT_ROOT']."/contex20/funcoes_vendas.php";
include "../conecta.php";





function adiciona_produto(){

	// print_r($_POST);

	$id_venda             = intval($_POST[id_venda]);
	$id_produto           = intval($_POST[id_produto]);
	$quantidade           = valor($_POST[quantidade]);
	$desconto_reais       = valor($_POST[desconto_reais]);
	$desconto_porcentagem = valor($_POST[desconto_porcentagem]);
	$id_entidade          = intval($_POST[id_entidade]);



	if ( $quantidade<=0 ) {
		$quantidade = 1;
	}


	if ( $desconto_reais>0 && $desconto_porcentagem>0 ) {
		?><script type="text/javascript">
			parent.alert("ATENÇÃO: O desconto deve ser aplicado em Reais ou em Porcentagem!");
		</script><?php
		exit;
	}


	if ( $id_venda==0 ) {
		// SE ESTIVER LANÇANDO O PRIMEIRO PRODUTO, INICIA A VENDA
		$a_retorno = inicia_ordem($id_entidade);
		if ( $a_retorno[status]!='OK' ) {
			?><script type="text/javascript">
				parent.alert("<?=$a_retorno[mensagem]?>");
			</script><?php
			exit;
		}

		$id_venda = $a_retorno['id_ordem'];
	}

	// CRIA O JAVASCRIPT SETANDO O ID DA VENDA
	?><script type="text/javascript">
		parent.document.getElementById('id_venda').value = "<?=$id_venda?>";
	</script><?php


	$a_retorno = adiciona_item($id_venda,$id_produto,$quantidade);
	if ( $a_retorno[status]!='OK' ) {
		?><script type="text/javascript">
			parent.alert("<?=$a_retorno[mensagem]?>");
		</script><?php
		exit;
	}


	// SE ESTIVER APLICANDO O DESCONTO
	if ( $desconto_reais>0 || $desconto_porcentagem>0 ) {
		$a_retorno = aplica_desconto($a_retorno['id_orpr'],$desconto_reais,$desconto_porcentagem);
		if ( $a_retorno[status]!='OK' ) {
			?><script type="text/javascript">
				parent.alert("<?=$a_retorno[mensagem]?>");
			</script><?php
			exit;
		}
	}

	exit;
}






function remove_produto(){

	$id_orpr = intval($_POST[id_orpr]);


	if ( $id_orpr==0 ) {
		?><script type="text/javascript">
			alert("ATENÇÃO: Dados do item não informados!");
		</script><?php
		exit;
	}


	$a_retorno = remover_item($id_orpr);
	if ( $a_retorno[status]!='OK' ) {
		?><script type="text/javascript">
			parent.alert("<?=$a_retorno[mensagem]?>");
		</script><?php
		exit;
	}

	exit;
}





function adiciona_pagamento(){

	$a_retorno = adiciona_item_pagamento($_POST[id_venda],$_POST[id_forma],valor($_POST[valor_pagamento],1),$_POST[parcelas_pagamento]);
	if ( $a_retorno[status]!='OK' ) {
		?><script type="text/javascript">
			alert("<?=$a_retorno[mensagem]?>");
			$("#valor_pagamento").focus();
		</script><?php
		exit;
	} else {
		// SE DER CERTO, CHAMA A FUNÇÃO PARA ATUALIZAR A LISTAGEM DAS FORMAS DE PAGAMENTO RECEBIDA
		?><script type="text/javascript">
			atualiza_lista_pagamento();
			$("#valor_pagamento").val('');
			$("#valor_pagamento").focus();
		</script><?php
	}
}




function remove_pagamento(){

	$id_pagamento = intval($_POST[id_pagamento]);


	if ( $id_pagamento==0 ) {
		?><script type="text/javascript">
			alert("ATENÇÃO: Dados do item não informados!");
		</script><?php
		exit;
	}


	$a_retorno = remover_item_pagamento($id_pagamento);
	if ( $a_retorno[status]!='OK' ) {
		?><script type="text/javascript">
			parent.alert("<?=$a_retorno[mensagem]?>");
		</script><?php
		exit;
	}

	exit;
}





function finaliza_venda(){

	$a_retorno = finaliza_ordem($_POST[id_venda]);
	if ( $a_retorno[status]!='OK' ) {
		?><script type="text/javascript">
			alert("<?=$a_retorno[mensagem]?>");
		</script><?php
		exit;
	} else {
		// SE DER CERTO, ATUALIZA A TELA PARA REALIZAR UMA NOVA VENDA
		?><script type="text/javascript">
			location.reload();
		</script><?php
	}
}





function seleciona_cliente(){

	$id_venda    = intval($_POST[id_venda]);
	$id_entidade = intval($_POST[id_entidade]);


	if ( $id_venda==0 ) {
		// SE ESTIVER LANÇANDO O PRIMEIRO PRODUTO, INICIA A VENDA
		$a_retorno = inicia_ordem($id_entidade);
		if ( $a_retorno[status]!='OK' ) {
			?><script type="text/javascript">
				parent.alert("<?=$a_retorno[mensagem]?>");
			</script><?php
			exit;
		}

		$id_venda = $a_retorno['id_ordem'];

		// CRIA O JAVASCRIPT SETANDO O ID DA VENDA
		?><script type="text/javascript">
			parent.document.getElementById('id_venda').value = "<?=$id_venda?>";
		</script><?php
	}


	$a_retorno = altera_cliente($id_venda,$id_entidade);
	if ( $a_retorno[status]!='OK' ) {
		?><script type="text/javascript">
			alert("<?=$a_retorno[mensagem]?>");
		</script><?php
		exit;
	} else {
		// SE DER CERTO, ATUALIZA A EXIBIÇÃO DOS DADOS DO CLIENTE
		?><script type="text/javascript">
			$('#div_nome_cliente').html("<?=$a_retorno[nome_entidade]?>");
			$('#id_entidade').val("<?=$a_retorno[id_entidade]?>");
		</script><?php
	}
}





?>