<?php
include_once $_SERVER['DOCUMENT_ROOT']."/contex20/funcoes_vendas.php";
include "../conecta.php";





function adiciona_produto(){

	// print_r($_POST);

	$id_venda             = intval($_POST[id_venda]);
	$id_produto           = intval($_POST[id_produto]);
	$quantidade           = valor_3_casas($_POST[quantidade]);
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





function cancela_venda(){

	$id_venda = intval($_POST[id_venda]);

	if ( $id_venda==0 ) {
		?><script type="text/javascript">
			alert("ATENÇÃO: Dados da venda não informados!");
		</script><?php
		exit;
	}

	$sql_orpr = query(" SELECT * FROM orpr WHERE orpr_nb_ordem = '$id_venda' AND orpr_tx_status!='inativo' ");
	while ( $a_orpr = $sql_orpr->fetch_assoc() ) {
		remover('orpr',$a_orpr[orpr_nb_id]);
	}

	$sql_pagamento = query(" SELECT * FROM pagamento WHERE paga_nb_ordem = '$id_venda' AND paga_tx_status!='inativo' ");
	while ( $a_paga = $sql_pagamento->fetch_assoc() ) {
		remover('pagamento',$a_paga[paga_nb_id]);
	}

	remover('ordem',$id_venda);


	// SE DER CERTO, REDIRECIONA PARA ATUALIZAR O SESSION NO PHP
	?><script type="text/javascript">
		window.location.href = "index.php"; 
	</script><?php
	exit;
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
	$planoconta           = intval($_POST[planoconta]);
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
	if ( strtolower($operacao_caixa)=='sangria' && $planoconta==0 ) {
		?><script type="text/javascript">
			alert("ATENÇÃO: O plano de contas deve ser selecionado!");
		</script><?php
		exit;
	}


	$campos  = array(oper_nb_caixa,oper_tx_tipo,oper_tx_valor,oper_tx_obs,oper_nb_userCadastro,oper_tx_dataCadastro,oper_tx_status);
	$valores = array($_SESSION[id_caixa],$operacao_caixa,$valor_operacao_caixa,addslashes($obs_operacao_caixa),$_SESSION[user_nb_id],date("Y-m-d H:i:s"),'ativo');
	$id_ope  = inserir('operacaocaixa',$campos,$valores);


	if ( strtolower($operacao_caixa)=='sangria' && $planoconta>0 ) {
		// SE FOR UMA SANGRIA E TIVER DEFINIDO UM PLANO DE CONTAS

		$sql_entidade = query("SELECT * FROM entidade WHERE enti_tx_sangriaCaixa = 'sim' AND enti_tx_status != 'inativo' LIMIT 1 ");
		$a_entidade = carrega_array($sql_entidade);

		if ( $a_entidade[enti_nb_id]==0 ) {
			// SE NÃO LOCALIZAR A ENTIDADE PADRÃO DA SANGRIA, REMOVE A OPERAÇÃO E EXIBE UM ALERTA
			remover('operacaocaixa',$id_ope);
			?><script type="text/javascript">
				alert("ATENÇÃO: A entidade de Sangria não foi configurada!");
			</script><?php
			exit;
		}


		$id_entidade = $a_entidade[enti_nb_id];
		$id_planoconta = $planoconta;
		$id_forma_pag = 1;// DINHEIRO

		$data     = date("Y-m-d");
		$data_cad = date("Y-m-d H:i:s");
		$user_cad = $_SESSION[user_nb_id];


		// INSERE O MOVIMENTO
		$campos  = array(movi_nb_entidade,movi_nb_operacaocaixa,movi_nb_planoconta,movi_tx_tipo,movi_tx_data,movi_tx_parcelas,movi_tx_status,movi_nb_forma);
		$valores = array($id_entidade,$id_ope,$id_planoconta,'Despesa',$data ,1,'ativo',$id_forma_pag);
		$id_mov  = inserir('movimento',$campos,$valores);


		// INSERE O BOLETO
		$campos  = array(bole_nb_movimento,bole_tx_vencimento,bole_tx_valordoc,bole_tx_parcela,bole_tx_status,bole_tx_obs,bole_tx_dataCadastro,bole_nb_userCadastro,bole_nb_forma,bole_tx_previsao);
		$valores = array($id_mov,$data,$valor_operacao_caixa,1,'encerrado',addslashes($obs_operacao_caixa),$data_cad ,$user_cad,$id_forma_pag,$data);

		array_push($campos,bole_tx_data,bole_tx_valor,bole_tx_dataAtualiza,bole_nb_userAtualiza);
		array_push($valores,$data,$valor_operacao_caixa,$data_cad,$user_cad);

		$id_bole = inserir('boleto',$campos,$valores);


		// INSERE O FOBO
		$campos  = array(fobo_nb_boleto,fobo_tx_valor,fobo_nb_forma,fobo_tx_status,fobo_tx_data,fobo_nb_user);
		$valores = array($id_bole,$valor_operacao_caixa,$id_forma_pag,'ativo',$data_cad,$user_cad);
		inserir('fobo',$campos,$valores);
	}


	if ( $id_ope>0 ) {
		// SE DER CERTO
		?><script type="text/javascript">
			alert("Operação cadastrada com sucesso!");

			$("#operacao_caixa").val('');
			$("#valor_operacao_caixa").val('');
			$("#obs_operacao_caixa").val('');
			$("#cp_planoconta").css("display", "none"); 
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

		if ( $id_forma==0 ) {
			?><script type="text/javascript">
				alert("ERRO: Forma de pagamento do fechamento não identificada!");
			</script><?php
			exit;
		}

		$a_sistema = get_valor_pagamento($id_forma,$_SESSION[id_caixa]);

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





function aplica_desconto_multiplo(){

	$qtde = intval($_POST[final_contador]);

	for ($i=0; $i<$qtde; $i++) { 
		$id_orpr        = intval($_POST['id_orpr_'.$i]);
		$desconto_reais = valor($_POST['desconto_orpr_reais_'.$i]);
		
		if ($desconto_reais>0)
			aplica_desconto($id_orpr,$desconto_reais);
		else
			remove_desconto($id_orpr);
	}

	?><script type="text/javascript">
		atualiza_exibicao_venda();
		$('#modalDesconto').modal('hide');
	</script><?php
	exit;
}






function aplica_taxi_dog(){

	$id_venda       = intval($_POST[id_venda]);
	$valor_taxi_dog = valor($_POST[valor_taxi_dog]);


	// VERIFICA SE A VENDA FOI INICIADA
	if ( $id_venda==0 ) {
		?><script type="text/javascript">
			alert("ATENÇÃO: A venda não foi informada!");
		</script><?php
		exit;
	}


	// VERIFICA SE ESTÁ TENTANDO ADICIONAR UM PRODUTO NO RECEBIMENTO DE CARTEIRA
	$a_ordem = carregar('ordem',$id_venda);
	if ( strtolower($a_ordem[orde_tx_tipo])=='recebimento carteira' ) {
		?><script type="text/javascript">
			alert("ATENÇÃO: ESTA AÇÃO NÃO É PERMITIDA EM UM RECEBIMENTO DE CARTEIRA!!");
		</script><?php
		exit;
	}


	// ATUALIZA O VALOR DO TAXIDOG NA TABELA ORDEM
	$campos  = array(orde_tx_valorFrete);
	$valores = array($valor_taxi_dog);
	atualizar('ordem',$campos,$valores,$id_venda);

	// SOMA OS VALORES DA ORDEM APÓS A ATUALIZAÇÃO DO TAXI DOG
	$a_soma = get_soma_venda($id_venda);

	// ATUALIZA A ORDEM APÓS ATUALIZAR O VALOR DO TAXI DOG
	$campos  = array(orde_tx_valor,orde_tx_valorBruto,orde_tx_descontoReais);
	$valores = array($a_soma[valor],$a_soma[valorBruto],$a_soma[descontoReais]);
	atualizar('ordem',$campos,$valores,$id_venda);


	?><script type="text/javascript">
		alert("Valor lançado com sucesso!");
		atualiza_exibicao_venda();
		$('#modalTaxiDog').modal('hide');
	</script><?php
	exit;
}






function gera_pagamento_carteira(){

	$id_entidade = intval($_POST[id_cliente]);
	$valor_pg    = valor($_POST[valor]);

	if ( $id_entidade==0 ) {
		?><script type="text/javascript">
			alert("ERRO: Código do cliente não informado!");
		</script><?php
		exit;
	}
	if ( $valor_pg==0 ) {
		?><script type="text/javascript">
			alert("ERRO: Valor do recebimento não informado!");
		</script><?php
		exit;
	}

	$a_retorno = inicia_ordem($id_entidade,'Recebimento Carteira');
	if ( $a_retorno[status]!='OK' ) {
		?><script type="text/javascript">
			alert("<?=$a_retorno[mensagem]?>");
		</script><?php
		exit;
	}

	// ATUALIZA A ORDEM COM O VALOR A RECEBER DA CARTEIRA
	$campos  = array(orde_tx_valor,orde_tx_valorBruto);
	$valores = array($valor_pg,$valor_pg);
	atualizar('ordem',$campos,$valores,$a_retorno['id_ordem']);


	?><script type="text/javascript">
		seleciona_venda(<?=$a_retorno['id_ordem']?>);
		$('#modalCliente').modal('hide');
	</script><?php
	exit;
}






function ativa_alerta_venda(){

	$qtde_dias_alerta = intval($_POST[qtde_dias_alerta]);
	$id_orpr          = intval($_POST[id_orpr]);

	if ( $id_orpr==0 ) {
		?><script type="text/javascript">
			alert("ERRO: Parâmetros em falta!");
		</script><?php
		exit;
	}

	if ( $qtde_dias_alerta<=0 ) {
		?><script type="text/javascript">
			alert("ATENÇÃO: Informe a quantidade de dias!");
		</script><?php
		exit;
	}

	$a_orpr    = carregar('orpr',$id_orpr);
	$a_ordem   = carregar('ordem',$a_orpr[orpr_nb_ordem]);
	$a_cliente = carregar('entidade',$a_ordem[orde_nb_entidade]);

	if ( strtolower($a_cliente[enti_tx_clientePadrao])=='sim' ) {
		// OBRIGA A IDENTIFICAR O CLIENTE PARA PODER GERAR O ALERTA
		?><script type="text/javascript">
			alert("ATENÇÃO: O CLIENTE PRECISA SER IDENTIFICADO PARA CRIAR O ALERTA!");
		</script><?php
		exit;
	}


	// CALCULA PARA QUAL DATA O ALERTA DEVE SER GERADO
	$data_alerta = date('Y-m-d', strtotime('+'.$qtde_dias_alerta.' days'));


	$sql_alerta = query("SELECT * FROM alertavenda WHERE aler_nb_entidade='$a_cliente[enti_nb_id]' AND aler_nb_produto='$a_orpr[orpr_nb_produto]' AND aler_tx_status='ativo' LIMIT 1 ");

	if ( num_linhas($sql_alerta)>0 ) {
		// SE O CLIENTE JÁ POSSUIR UM ALERTA PARA O PRODUTO, ATUALIZA O ALERTA CRIADO
		$a_alerta = carrega_array($sql_alerta);
		
		$campos  = array(aler_tx_qtdeDias,aler_tx_data,aler_nb_userAtualiza,aler_tx_dataAtualiza);
		$valores = array($qtde_dias_alerta,$data_alerta,$_SESSION[user_nb_id],date('Y-m-d H:i:s'));
		atualizar('alertavenda',$campos,$valores,$a_alerta[aler_nb_id]);

	} else {
		// CRIA UM ALERTA PARA O PRODUTO
		$campos  = array(aler_nb_entidade,aler_nb_produto,aler_nb_orpr,aler_tx_qtdeDias,aler_tx_data,aler_nb_userCadastro,aler_tx_dataCadastro,aler_tx_status);
		$valores = array($a_cliente[enti_nb_id],$a_orpr[orpr_nb_produto],$a_orpr[orpr_nb_id],$qtde_dias_alerta,$data_alerta,$_SESSION[user_nb_id],date('Y-m-d H:i:s'),'ativo');
		inserir('alertavenda',$campos,$valores);
	}





	?><script type="text/javascript">
		atualiza_exibicao_venda();
		$('#modalAlertaVenda').modal('hide');
	</script><?php
	exit;
}




?>