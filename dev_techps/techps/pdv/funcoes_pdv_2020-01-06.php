<?php
include $_SERVER['DOCUMENT_ROOT']."/contex20/funcoes_vendas.php";
include "../conecta.php";





function adiciona_produto(){

	// print_r($_POST);

	$id_venda = intval($_POST[id_venda]);
	$id_produto = intval($_POST[id_produto]);
	$quantidade = 5.66;
	$id_entidade = 1;

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

	$a_retorno = adiciona_item($id_venda,$id_produto,$quantidade);

	if ( $a_retorno[status]!='OK' ) {
		?><script type="text/javascript">
			parent.alert("<?=$a_retorno[mensagem]?>");
		</script><?php
		exit;
	}


	?><script type="text/javascript">
		parent.document.getElementById('id_venda').value = "<?=$id_venda?>";
	</script><?php
}




?>