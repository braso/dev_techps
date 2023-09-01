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


	// VERIFICA SE O CAIXA ESTÁ ABERTO
	if ( $_SESSION[id_caixa]==0 || $_SESSION[id_pdv]==0 ) {
		?><script type="text/javascript">
			alert("ATENÇÃO: O caixa não esta aberto!");
			$("#modalAbertura").modal("show");
		</script><?php
		exit;
	}


	if ( $desconto_reais>0 && $desconto_porcentagem>0 ) {
		?><script type="text/javascript">
			alert("ATENÇÃO: O desconto deve ser aplicado em Reais ou em Porcentagem!");
		</script><?php
		exit;
	}


	if ( $id_venda==0 ) {
		// SE ESTIVER LANÇANDO O PRIMEIRO PRODUTO, INICIA A VENDA
		$a_retorno = inicia_ordem($id_entidade);
		if ( $a_retorno[status]!='OK' ) {
			?><script type="text/javascript">
				alert("<?=$a_retorno[mensagem]?>");
			</script><?php
			exit;
		}

		$id_venda = $a_retorno['id_ordem'];
	}

	// CRIA O JAVASCRIPT SETANDO O ID DA VENDA
	?><script type="text/javascript">
		document.getElementById('id_venda').value = "<?=$id_venda?>";
	</script><?php


	$a_retorno = adiciona_item($id_venda,$id_produto,$quantidade);
	if ( $a_retorno[status]!='OK' ) {
		?><script type="text/javascript">
			alert("<?=$a_retorno[mensagem]?>");
		</script><?php
		exit;
	}


	// SE ESTIVER APLICANDO O DESCONTO
	if ( $desconto_reais>0 || $desconto_porcentagem>0 ) {
		$a_retorno = aplica_desconto($a_retorno['id_orpr'],$desconto_reais,$desconto_porcentagem);
		if ( $a_retorno[status]!='OK' ) {
			?><script type="text/javascript">
				alert("<?=$a_retorno[mensagem]?>");
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
			alert("<?=$a_retorno[mensagem]?>");
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
			alert("<?=$a_retorno[mensagem]?>");
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
			imprimir_venda();
			window.location.href = "index.php";
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
				alert("<?=$a_retorno[mensagem]?>");
			</script><?php
			exit;
		}

		$id_venda = $a_retorno['id_ordem'];

		// CRIA O JAVASCRIPT SETANDO O ID DA VENDA
		?><script type="text/javascript">
			document.getElementById('id_venda').value = "<?=$id_venda?>";
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





function abertura_caixa(){

	$_POST[id_pdv] = intval($_POST[id_pdv]);

	$result  = query("SELECT caix_nb_id FROM caixa WHERE caix_nb_pdv = '$_POST[id_pdv]' AND caix_tx_status = 'ativo' LIMIT 1 ");
	$a_caixa = $result->fetch_assoc();

	if ( $a_caixa[caix_nb_id]>0 ) {
		$id_caixa = $a_caixa[caix_nb_id];

	} else {
		// SE O CAIXA NÃO ESTIVER ABERTO, ABRE O MESMO
		$a_retorno = abrir_caixa($_POST[id_pdv],valor($_POST[valor_abertura]));
		if ( $a_retorno[status]!='OK' ) {
			?><script type="text/javascript">
				alert("<?=$a_retorno[mensagem]?>");
			</script><?php
			exit;
		}

		$id_caixa = $a_retorno['id_caixa'];
	}

	$_SESSION[id_caixa] = $id_caixa;
	$_SESSION[id_pdv]   = $_POST[id_pdv];

	// SE DER CERTO, REDIRECIONA PARA ATUALIZAR O SESSION NO PHP
	?><script type="text/javascript">
		window.location.href = "index.php"; 
	</script><?php
	exit;
}





function operacao_caixa(){

	$operacao_caixa       = trim($_POST[operacao_caixa]);
	$valor_operacao_caixa = valor($_POST[valor_operacao_caixa]);
	$obs_operacao_caixa   = trim($_POST[obs_operacao_caixa]);


	// VERIFICA SE O CAIXA ESTÁ ABERTO
	if ( $_SESSION[id_caixa]==0 || $_SESSION[id_pdv]==0 ) {
		?><script type="text/javascript">
			alert("ATENÇÃO: O caixa não esta aberto!");
			$("#modalAbertura").modal("show");
		</script><?php
		exit;
	}

	if ( $operacao_caixa=='' ) {
		?><script type="text/javascript">
			alert("Selecione uma operação!");
		</script><?php
		exit;
	}
	if ( $valor_operacao_caixa<=0 ) {
		?><script type="text/javascript">
			alert("Informe o valor da operação!");
		</script><?php
		exit;
	}


	$campos  = array(oper_nb_caixa,oper_tx_tipo,oper_tx_valor,oper_tx_obs,oper_nb_userCadastro,oper_tx_dataCadastro,oper_tx_status);
	$valores = array($_SESSION[id_caixa],$operacao_caixa,$valor_operacao_caixa,addslashes($obs_operacao_caixa),$_SESSION[user_nb_id],date("Y-m-d H:i:s"),'ativo');
	$id_ope  = inserir('operacaocaixa',$campos,$valores);

	if ( $id_ope>0 ) {
		// SE DER CERTO
		?><script type="text/javascript">
			alert("Operação cadastrada com sucesso!");

			$("#operacao_caixa").val('');
			$("#valor_operacao_caixa").val('');
			$("#obs_operacao_caixa").val('');
			$('#modalOperacoes').modal('hide');
		</script><?php
		exit;

	} else {
		?><script type="text/javascript">
			alert("ERRO: Falha ao cadastrar a operação!");
		</script><?php
		exit;
	}
}






function fechamento_caixa(){


	// VERIFICA SE O CAIXA ESTÁ ABERTO
	if ( $_SESSION[id_caixa]==0 || $_SESSION[id_pdv]==0 ) {
		?><script type="text/javascript">
			alert("ATENÇÃO: O caixa não esta aberto!");
			$("#modalAbertura").modal("show");
		</script><?php
		exit;
	}

	
	foreach ($_POST[valor_fechamento] as $id_forma => $valor) {

		$extra = " AND fobo_nb_forma='$id_forma' AND orde_nb_caixa='$_SESSION[id_caixa]' AND bole_tx_status!='inativo' AND fobo_tx_status!='inativo' AND orde_tx_status!='inativo' AND orde_tx_situacao='finalizado' ";

		$result = query(" SELECT SUM(fobo_tx_valor) AS total FROM fobo,boleto,movimento,ordem WHERE fobo_nb_boleto=bole_nb_id AND bole_nb_movimento=movi_nb_id AND movi_nb_ordem=orde_nb_id $extra ");
		$a_sistema = $result->fetch_assoc();

		if ( $id_forma==1 ) {// SE FOR DINHEIRO, TEM QUE CONTABILIZAR AS SANGRIAS E SUPRIMENTOS
			$result = query(" SELECT SUM(oper_tx_valor) AS total FROM operacaocaixa WHERE oper_nb_caixa='$_SESSION[id_caixa]' AND oper_tx_tipo IN ('Abertura','Suprimento') AND oper_tx_status!='inativo' ");
			$a_entradas = $result->fetch_assoc();

			$result = query(" SELECT SUM(oper_tx_valor) AS total FROM operacaocaixa WHERE oper_nb_caixa='$_SESSION[id_caixa]' AND oper_tx_tipo = 'Sangria' AND oper_tx_status!='inativo' ");
			$a_saidas = $result->fetch_assoc();

			// CONTABILIZA OS VALORES DAS OPERAÇÕES DE CAIXA
			$a_sistema[total] = $a_sistema[total]+$a_entradas[total]-$a_saidas[total];
		}

		$valor_informado = valor($valor);
		$valor_sistema   = $a_sistema[total];
		$valor_diferenca = $valor_informado-$valor_sistema;

		$campos  = array(fech_nb_caixa,fech_nb_forma,fech_tx_valor,fech_tx_valorSistema,fech_tx_diferenca,fech_nb_userCadastro,fech_tx_dataCadastro,fech_tx_status);
		$valores = array($_SESSION[id_caixa],$id_forma,$valor_informado,$valor_sistema,$valor_diferenca,$_SESSION[user_nb_id],date("Y-m-d H:i:s"),'ativo');
		$id_ope  = inserir('fechamento',$campos,$valores);

		if ( $id_ope==0 ) {
			?><script type="text/javascript">
				alert("ERRO: Falha ao fechar o caixa!");
			</script><?php
			exit;
		}
	}


	// FECHA O REGISTRO DO CAIXA
	$campos  = array(caix_nb_userFechamento,caix_tx_dataFechamento,caix_tx_status);
	$valores = array($_SESSION[user_nb_id],date("Y-m-d H:i:s"),'fechado');
	atualizar('caixa',$campos,$valores,$_SESSION[id_caixa]);


	unset($_SESSION[id_caixa]);
	unset($_SESSION[id_pdv]);


	// SE DER CERTO, REDIRECIONA PARA ATUALIZAR O SESSION NO PHP
	?><script type="text/javascript">
		alert("Caixa Fechado com sucesso!");
		imprimir_fechamento();
		window.location.href = "index.php"; 
	</script><?php
	exit;
}





?>