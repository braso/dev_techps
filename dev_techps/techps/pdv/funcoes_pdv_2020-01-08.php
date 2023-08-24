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
	$id_entidade          = 1;



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




?>