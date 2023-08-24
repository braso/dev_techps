<?php




function get_soma_venda($id_ordem=0){

	// CRIA UM ARRAY DE RETORNO COM UMA MENSAGEM E UM STATUS PADRÃO
	$a_retorno['status']   = 'ERRO';
	$a_retorno['mensagem'] = 'FALHA AO TOTALIZAR OS VALORES DA VENDA!';

	if ( $id_ordem>0 ) {
		$a_ordem  = carregar('ordem',$id_ordem);// CARREGA A ORDEM PARA OBTER INFORMAÇÕES ESPECÍFICAS

		$sql_soma = query(" SELECT SUM(orpr_tx_valor) AS valor, SUM(orpr_tx_valorBruto) AS valorBruto, SUM(orpr_tx_descontoReais) AS descontoReais FROM orpr WHERE orpr_nb_ordem = '$id_ordem' AND orpr_tx_status!='inativo' ");
		$a_soma   = carrega_array($sql_soma);

		// TRANSFERE OS DADOS DO SOMATÓRIO PARA A ORDEM
		$a_retorno['valorFrete']    = $a_ordem['orde_tx_valorFrete'];//ADICIONA O VALOR DO FRETE NO RETORNO
		$a_retorno['valor']         = $a_soma['valor'] + $a_ordem['orde_tx_valorFrete'];
		$a_retorno['valorBruto']    = $a_soma['valorBruto'] + $a_ordem['orde_tx_valorFrete'];
		$a_retorno['descontoReais'] = $a_soma['descontoReais'];

		$a_retorno['status']   = 'OK';
		$a_retorno['mensagem'] = 'Valores somados com sucesso!';

	} else {
		$a_retorno['mensagem'] = 'CÓDIGO DA VENDA NÃO INFORMADO!';
	}

	return $a_retorno;
}




function inicia_ordem($id_entidade=0,$tipo='PDV'){

	// CRIA UM ARRAY DE RETORNO COM UMA MENSAGEM E UM STATUS PADRÃO
	$a_retorno['status'] = 'ERRO';
	$a_retorno['mensagem'] = 'FALHA AO INICIAR A VENDA!';


	if ( $id_entidade==0 ) {
		$sql_cliente = "SELECT enti_nb_id FROM entidade WHERE enti_tx_clientePadrao = 'sim' AND enti_tx_status != 'inativo' LIMIT 1 ";
		$result = query($sql_cliente);
		$a_cliente = $result->fetch_assoc();

		if ( $a_cliente[enti_nb_id]==0 ) {
			$a_retorno['mensagem'] = 'CLIENTE PADRÃO NÃO ENCONTRADO!';
		} else {
			$id_entidade = $a_cliente[enti_nb_id];
		}
	}


	if ( $id_entidade>0 ) {
		// SÓ INICIA A VENDA SE EXISTIR UM CLIENTE ASSOCIADO
		$campos  = array(orde_nb_entidade,orde_nb_caixa,orde_nb_vendedor,orde_tx_tipo,orde_tx_situacao,
						orde_tx_data,orde_nb_userCadastro,orde_tx_dataCadastro,orde_tx_status);
		$valores = array($id_entidade,$_SESSION[id_caixa],$id_entidade_vendedor,strtoupper($tipo),'andamento',
						date('Y-m-d'),$_SESSION[user_nb_id],date('Y-m-d H:i:s'),'ativo');

		// ADICIONA ESSAS COLUNAS ZERADAS PARA PODER SOMAR VALORES USANDO SQL (SE ESTIVER NULO A SOMA NÃO FUNCIONA)
		array_push($campos, orde_tx_valor,orde_tx_valorBruto,orde_tx_descontoReais);
		array_push($valores, 0,0,0);


		$id_ordem = inserir('ordem',$campos,$valores);
		if ( $id_ordem>0 ) {
			$a_retorno['status'] = 'OK';
			$a_retorno['mensagem'] = 'Venda iniciada com sucesso!';
			$a_retorno['id_ordem'] = $id_ordem;
		}
	}

	return $a_retorno;
}





function adiciona_item($id_ordem=0,$id_produto=0,$quantidade=0){

	// CRIA UM ARRAY DE RETORNO COM UMA MENSAGEM E UM STATUS PADRÃO
	$a_retorno['status'] = 'ERRO';
	$a_retorno['mensagem'] = 'FALHA AO ADICONAR O ITEM!';


	$a_produto = carregar('produto',$id_produto);


	if ( $a_produto[prod_nb_id]>0 ) {

		$valorUnitario = $a_produto[prod_tx_preco];
		$valorBruto = $quantidade * $valorUnitario;
		$valor = $valorBruto;

		$campos  = array(orpr_nb_ordem,orpr_nb_produto,orpr_tx_valor,orpr_tx_valorBruto,orpr_tx_valorUnitario,
						orpr_tx_quantidade,orpr_nb_userCadastro,orpr_tx_dataCadastro,orpr_tx_status);
		$valores = array($id_ordem,$id_produto,$valor,$valorBruto,$valorUnitario,
						$quantidade,$_SESSION[user_nb_id],date('Y-m-d H:i:s'),'ativo');

		$id_orpr = inserir('orpr',$campos,$valores);
		if ( $id_orpr>0 ) {
			// SE CONSEGUIU ADICIONAR O ITEM, ATUALIZA O VALOR DA ORDEM
			query(" UPDATE ordem SET orde_tx_valor=orde_tx_valor + '$valor', orde_tx_valorBruto=orde_tx_valorBruto + '$valorBruto' WHERE orde_nb_id='$id_ordem' ");

			$a_retorno['status'] = 'OK';
			$a_retorno['mensagem'] = 'Item adicionado com sucesso!';
			$a_retorno['id_orpr'] = $id_orpr;
		}

	} else {
		$a_retorno['mensagem'] = 'PRODUTO NÃO ENCONTRADO!';
	}

	return $a_retorno;
}





function remover_item($id_orpr=0){

	// CRIA UM ARRAY DE RETORNO COM UMA MENSAGEM E UM STATUS PADRÃO
	$a_retorno['status'] = 'ERRO';
	$a_retorno['mensagem'] = 'FALHA AO REMOVER O ITEM!';


	$a_orpr = carregar('orpr',$id_orpr);

	if ( $a_orpr[orpr_nb_ordem]==0 ) {
		$a_retorno['mensagem'] = 'O ITEM NÃO ESTÁ ASSOCIADO A UMA VENDA!';

	} elseif ( $a_orpr[orpr_nb_id]>0 ) {

		remover('orpr',$a_orpr[orpr_nb_id]);

		// SE CONSEGUIU ADICIONAR O ITEM, ATUALIZA O VALOR DA ORDEM
		query(" UPDATE ordem SET orde_tx_valor=orde_tx_valor - '$a_orpr[orpr_tx_valor]', orde_tx_valorBruto=orde_tx_valorBruto - '$a_orpr[orpr_tx_valorBruto]', orde_tx_descontoReais=orde_tx_descontoReais - '$a_orpr[orpr_tx_descontoReais]' WHERE orde_nb_id='$a_orpr[orpr_nb_ordem]' ");
		$a_retorno['status'] = 'OK';
		$a_retorno['mensagem'] = 'Item removido com sucesso!';
		$a_retorno['id_orpr'] = $id_orpr;

	} else {
		$a_retorno['mensagem'] = 'ITEM NÃO ENCONTRADO!';
	}

	return $a_retorno;
}




function aplica_desconto($id_orpr=0,$desconto_reais=0,$desconto_porcentagem=0){

	// CRIA UM ARRAY DE RETORNO COM UMA MENSAGEM E UM STATUS PADRÃO
	$a_retorno['status'] = 'ERRO';
	$a_retorno['mensagem'] = 'FALHA AO APLICAR O DESCONTO!';


	if ( $desconto_reais>0 && $desconto_porcentagem>0 ) {
		$a_retorno['mensagem'] = 'ATENÇÃO: O desconto deve ser aplicado em reais ou em porcentagem!';
	
	} elseif ( $desconto_reais==0 && $desconto_porcentagem==0 ) {
		$a_retorno['mensagem'] = 'ATENÇÃO: Nenhum tipo de desconto foi informado!';

	} else {
		$a_orpr = carregar('orpr',$id_orpr);
		if ( $a_orpr[orpr_nb_id]>0 ) {			
			if ( $desconto_reais>$a_orpr[orpr_tx_valorBruto] ) {
				$a_retorno['mensagem'] = 'ATENÇÃO: O valor de desconto não pode ser maior que o valor total do produto!';

			} else {

				// AQUI VERIFICA SE ESTÁ APLICANDO O DESCONTO EM REAIS OU EM PORCENTAGEM PARA CALCULAR O QUE NÃO FOI INFORMADO (OBS: O USUÁRIO NÃO PODE INFORMAR OS DOIS, APENAS UM)
				if ($desconto_reais>0) {
					// SE FORNECER O DESCONTO EM REAIS, CALCULA A PORCENTAGEM
					@$desconto_porcentagem = ($desconto_reais*100)/$a_orpr[orpr_tx_valorBruto];
					@$desconto_porcentagem = number_format($desconto_porcentagem,2, '.','');// FORMATA O VALOR EM DUAS CASAS DECIMAIS

				} else {
					// SE FORNECER O DESCONTO EM PORCENTAGEM, CALCULA O VALOR EM REAIS
					@$desconto_reais = ($a_orpr[orpr_tx_valorBruto]*$desconto_porcentagem)/100;
					@$desconto_reais = number_format($desconto_reais,2, '.','');// FORMATA O VALOR EM DUAS CASAS DECIMAIS
				}

				$valor_liquido = $a_orpr[orpr_tx_valorBruto]-$desconto_reais;
				
				$campos  = array(orpr_tx_valor,orpr_tx_descontoReais,orpr_tx_descontoPorcentagem);
				$valores = array($valor_liquido,$desconto_reais,$desconto_porcentagem);
				atualizar('orpr',$campos,$valores,$id_orpr);


				// APÓS ADICIONAR O DESCONTO, TEM QUE ATUALIZAR A TABELA VENDA COM OS NOVOS VALORES
				// $result = query(" SELECT SUM(orpr_tx_valor) AS valor, SUM(orpr_tx_valorBruto) AS valorBruto, SUM(orpr_tx_descontoReais) AS descontoReais FROM orpr WHERE orpr_nb_ordem = '$a_orpr[orpr_nb_ordem]' AND orpr_tx_status!='inativo' ");
				// $a_soma = $result->fetch_assoc();
				$a_soma = get_soma_venda($a_orpr[orpr_nb_ordem]);

				$campos  = array(orde_tx_valor,orde_tx_valorBruto,orde_tx_descontoReais);
				$valores = array($a_soma[valor],$a_soma[valorBruto],$a_soma[descontoReais]);
				atualizar('ordem',$campos,$valores,$a_orpr[orpr_nb_ordem]);


				$a_retorno['status'] = 'OK';
				$a_retorno['mensagem'] = 'Desconto aplicado com sucesso!';
			}
		} else {
			$a_retorno['mensagem'] = 'ITEM NÃO ENCONTRADO!';
		}
	}

	return $a_retorno;
}




function remove_desconto($id_orpr=0){

	// CRIA UM ARRAY DE RETORNO COM UMA MENSAGEM E UM STATUS PADRÃO
	$a_retorno['status'] = 'ERRO';
	$a_retorno['mensagem'] = 'FALHA AO APLICAR O DESCONTO!';


	$a_orpr = carregar('orpr',$id_orpr);
	if ( $a_orpr[orpr_nb_id]>0 ) {

		$campos  = array(orpr_tx_valor,orpr_tx_descontoReais,orpr_tx_descontoPorcentagem);
		$valores = array($a_orpr[orpr_tx_valorBruto],0,0);
		atualizar('orpr',$campos,$valores,$id_orpr);

		// APÓS ADICIONAR O DESCONTO, TEM QUE ATUALIZAR A TABELA VENDA COM OS NOVOS VALORES
		// $result = query(" SELECT SUM(orpr_tx_valor) AS valor, SUM(orpr_tx_valorBruto) AS valorBruto, SUM(orpr_tx_descontoReais) AS descontoReais FROM orpr WHERE orpr_nb_ordem = '$a_orpr[orpr_nb_ordem]' AND orpr_tx_status!='inativo' ");
		// $a_soma = $result->fetch_assoc();
		$a_soma = get_soma_venda($a_orpr[orpr_nb_ordem]);

		$campos  = array(orde_tx_valor,orde_tx_valorBruto,orde_tx_descontoReais);
		$valores = array($a_soma[valor],$a_soma[valorBruto],$a_soma[descontoReais]);
		atualizar('ordem',$campos,$valores,$a_orpr[orpr_nb_ordem]);

		$a_retorno['status'] = 'OK';
		$a_retorno['mensagem'] = 'Desconto aplicado com sucesso!';

	} else {
		$a_retorno['mensagem'] = 'ITEM NÃO ENCONTRADO!';
	}

	return $a_retorno;
}





function adiciona_item_pagamento($id_ordem=0,$id_forma=0,$valor=0,$parcelas=0){

	// CRIA UM ARRAY DE RETORNO COM UMA MENSAGEM E UM STATUS PADRÃO
	$a_retorno['status'] = 'ERRO';
	$a_retorno['mensagem'] = 'FALHA AO ADICONAR O PAGAMENTO!';

	$a_forma = carregar('forma',$id_forma);

	if ( $id_ordem==0 ) {
		$a_retorno['mensagem'] = 'CÓDIGO DA ORDEM NÃO INFORMADO!';

	} elseif ( $valor<=0 ) {
		$a_retorno['mensagem'] = 'VALOR DO PAGAMENTO NÃO INFORMADO!';

	} elseif ( $a_forma[form_nb_id]>0 ) {

		$campos  = array(paga_nb_ordem,paga_nb_forma,paga_tx_valor,paga_tx_parcelas,
						 paga_nb_userCadastro,paga_tx_dataCadastro,paga_tx_status);
		$valores = array(intval($id_ordem),intval($id_forma),$valor,intval($parcelas),
						 $_SESSION[user_nb_id],date('Y-m-d H:i:s'),'ativo');

		$id_pagamento = inserir('pagamento',$campos,$valores);
		if ( $id_pagamento>0 ) {
			$a_retorno['status'] = 'OK';
			$a_retorno['mensagem'] = 'Pagamento adicionado com sucesso!';
			$a_retorno['id_pagamento'] = $id_pagamento;
		}

	} else {
		$a_retorno['mensagem'] = 'FORMA DE PAGAMENTO NÃO ENCONTRADA!';
	}

	return $a_retorno;
}





function remover_item_pagamento($id_pagamento=0){

	// CRIA UM ARRAY DE RETORNO COM UMA MENSAGEM E UM STATUS PADRÃO
	$a_retorno['status'] = 'ERRO';
	$a_retorno['mensagem'] = 'FALHA AO REMOVER O ITEM!';


	$a_pagamento = carregar('pagamento',$id_pagamento);

	if ( $a_pagamento[paga_nb_ordem]==0 ) {
		$a_retorno['mensagem'] = 'O PAGAMENTO NÃO ESTÁ ASSOCIADO A UMA VENDA!';

	} elseif ( $a_pagamento[paga_nb_id]>0 ) {

		remover('pagamento',$a_pagamento[paga_nb_id]);

		$a_retorno['status'] = 'OK';
		$a_retorno['mensagem'] = 'Item removido com sucesso!';
		$a_retorno['id_pagamento'] = $id_pagamento;

	} else {
		$a_retorno['mensagem'] = 'ITEM NÃO ENCONTRADO!';
	}

	return $a_retorno;
}




function finaliza_ordem($id_ordem=0){

	// CRIA UM ARRAY DE RETORNO COM UMA MENSAGEM E UM STATUS PADRÃO
	$a_retorno['status'] = 'ERRO';
	$a_retorno['mensagem'] = 'FALHA AO INICIAR A VENDA!';


	$id_ordem = intval($id_ordem);
	if ( $id_ordem<=0 ) {
		$a_retorno['mensagem'] = 'CÓDIGO DA VENDA NÃO INFORMADO!';

	} else {

		$orprValor         = 0;
		$orprValorBruto    = 0;
		$orprDescontoReais = 0;

		// SOMA OS VALORES E REALIZA AS OPERAÇÕES NECESSÁRIAS
		$sql_orpr = "SELECT * FROM orpr,produto WHERE orpr_nb_ordem = '$id_ordem' AND orpr_tx_status != 'inativo' AND orpr_nb_produto=prod_nb_id ";
		$result = query($sql_orpr);
		while($row = $result->fetch_assoc()){
			// CONTABILIZA OS VALORES DA VENDA
			$orprValor         += $row[orpr_tx_valor];
			$orprValorBruto    += $row[orpr_tx_valorBruto];
			$orprDescontoReais += $row[orpr_tx_descontoReais];


			// VERIFICA SE O ESTOQUE DO PRODUTO JÁ FOI ABATIDO E SE NÃO É UM SERVIÇO
			if ( strtolower($row[orpr_tx_estoqueAbatido])!='sim' && strtolower($row[prod_tx_servico])!='sim' ) {
				$a_produto = carregar('produto',$row[orpr_nb_produto]);

				$estoqueAnterior   = $a_produto[prod_tx_qtde];
				$estoqueQuantidade = $row[orpr_tx_quantidade];
				$estoqueNovo       = $estoqueAnterior-$estoqueQuantidade;
				// ATUALIZA O ESTOQUE DO PRODUTO
				atualizar('produto',array(prod_tx_qtde),array($estoqueNovo),$row[orpr_nb_produto]);

				// MARCA A ORPR PARA CONFIRMAR QUE A BAIXA DO ESTOQUE FOI FEITA
				atualizar('orpr',array(orpr_tx_estoqueAbatido),array('sim'),$row[orpr_nb_id]);

				// REGISTRA A MOVIMENTAÇÃO DO ESTOQUE
				$campos  = array(esto_nb_produto,esto_tx_qtdeDepois,
								esto_nb_orpr,esto_nb_ordem,esto_tx_qtde,esto_tx_qtdeAntes,
								esto_nb_userCadastro,esto_tx_dataCadastro,esto_tx_tipo,esto_tx_status);
				$valores = array($row[orpr_nb_produto],$estoqueNovo,
								$row[orpr_nb_id],$id_ordem,$estoqueQuantidade,$estoqueAnterior,
								$_SESSION[user_nb_id],date('Y-m-d H:i:s'),'Venda','ativo');
				inserir('estoque',$campos,$valores);
			}
		}



		$valor_pago   = 0;
		$valor_troco  = 0;
		$a_pagamentos = array();

		$sql = query("SELECT * FROM pagamento WHERE paga_nb_ordem = '$id_ordem' AND paga_tx_status!='inativo' ");
		while($row = carrega_array($sql)){
			// ARMAZENA EM UM ARRAY, PARA DEPOIS GERAR OS REGISTROS DO FOBO
			$a_pagamentos[] = $row;
			$valor_pago += $row[paga_tx_valor];
		}
		// VERIFICA SE GEROU TROCO
		if ( $valor_pago>$orprValor ) {
			$valor_troco = $valor_pago-$orprValor;
		}

		// VERIFICA SE A VENDA FOI PAGA POR COMPLETO
		if ( $valor_pago>=$orprValor ) {

			$campos  = array(orde_tx_situacao,orde_tx_valor,orde_tx_valorBruto,orde_tx_descontoReais);
			$valores = array('finalizado',$orprValor,$orprValorBruto,$orprDescontoReais);
			atualizar('ordem',$campos,$valores,$id_ordem);

			$a_ordem = carregar('ordem',$id_ordem);

			// GERA O FINANCEIRO APÓS CONTABILIZAR OS RECEBIMENTOS
			$campos  = array(movi_nb_ordem,movi_nb_entidade,movi_nb_planoconta,movi_tx_tipo,movi_tx_data,movi_tx_parcelas,movi_tx_status);
			$valores = array($id_ordem,$a_ordem[orde_nb_entidade],1,'Receita',date('Y-m-d'),1,'ativo');
			inserir('movimento',$campos,$valores);
			$id_mov  = ultimo_reg('movimento');

			$campos  = array(bole_nb_movimento,bole_tx_vencimento,bole_tx_valordoc,bole_tx_data,bole_tx_valor,bole_tx_parcela,bole_tx_status,bole_tx_obs,bole_tx_dataCadastro,bole_nb_userCadastro,bole_tx_previsao);
			$valores = array($id_mov,date('Y-m-d'),$orprValor,date('Y-m-d'),($valor_pago-$valor_troco),1,'encerrado',"Venda Código: $id_ordem",date("Y-m-d H:i:s"),$_SESSION[user_nb_id],date('Y-m-d'));
			inserir('boleto',$campos,$valores);
			$id_bole = ultimo_reg('boleto');

			// DEPOIS DO SOMAR OS PAGAMENTOS E VERIFICAR SE GEROU TROCO, PERCORRE OS PAGAMENTOS NOVAMENTE, PARA GERAR O FOBO
			foreach ($a_pagamentos as $row) {

				$valor_pago_fobo = $row[paga_tx_valor];
				if ( $valor_troco>0 && $row[paga_nb_forma]==1 ) {// SE TEM TROCO E A FORME É DINEIRO...
					if ( $valor_troco<=$row[paga_tx_valor] ) {// SE DER PARA ABATER O VALOR DO TROCO DO PAGAMENTO FEITO EM DINHEIRO
						$valor_pago_fobo -= $valor_troco;// ABATE O VALOR DE TROCO DO PAGAMENTO
						$valor_troco = 0;//ZERA O VALOR DO TROCO, PARA EVITAR QUE ELE SEJA CONTABILIZADO NOVAMENTE
					}
				}

				$campos  = array(fobo_nb_boleto,fobo_tx_valor,fobo_nb_forma,fobo_tx_status,fobo_tx_data,fobo_nb_user);
				$valores = array($id_bole,$valor_pago_fobo,$row[paga_nb_forma],'ativo',date("Y-m-d H:i:s"),$_SESSION[user_nb_id]);
				inserir('fobo',$campos,$valores);
			}

			// AO FINAL, CONFIRMA QUE TODO O PROCESSO OCORREU NORMALMENTE
			$a_retorno['status'] = 'OK';
			$a_retorno['mensagem'] = 'Venda finalizada com sucesso!';

		} else {
			$a_retorno['mensagem'] = 'O VALOR PAGO É MENOR QUE O DA VENDA!';
		}
	}

	return $a_retorno;
}





function altera_cliente($id_venda=0,$id_entidade=0){

	// CRIA UM ARRAY DE RETORNO COM UMA MENSAGEM E UM STATUS PADRÃO
	$a_retorno['status'] = 'ERRO';
	$a_retorno['mensagem'] = 'FALHA AO ALTERAR O CLIENTE!';


	$a_entidade = carregar('entidade',$id_entidade);

	if ( $id_venda==0 || $id_entidade==0 ) {
		$a_retorno['mensagem'] = 'OS DADOS DA ALTERAÇÃO NÃO FORAM INFORMADOS!';

	} elseif ($a_entidade[enti_nb_id]==0) {
		$a_retorno['mensagem'] = 'O CLIENTE NÃO FOI LOCALIZADO!';

	} else {

		atualizar('ordem',array(orde_nb_entidade),array($id_entidade),$id_venda);

		$a_retorno['status'] = 'OK';
		$a_retorno['mensagem'] = 'Cliente alterado com sucesso!';
		$a_retorno['id_entidade'] = $id_entidade;
		$a_retorno['nome_entidade'] = $a_entidade[enti_tx_nome];
	}

	return $a_retorno;
}





function abrir_caixa($id_pdv=0,$valor_abertura=0){

	// CRIA UM ARRAY DE RETORNO COM UMA MENSAGEM E UM STATUS PADRÃO
	$a_retorno['status'] = 'ERRO';
	$a_retorno['mensagem'] = 'FALHA AO ABRIR O CAIXA!';

	if ( intval($id_pdv)==0 ) {
		$a_retorno['mensagem'] = 'NENHUM PDV FOI SELECIONADO!';

	} else {
		$campos  = array(caix_nb_pdv,caix_nb_user,caix_tx_data,caix_tx_status);
		$valores = array($id_pdv,$_SESSION[user_nb_id],date("Y-m-d H:i:s"),'ativo');

		$id_caixa = inserir('caixa',$campos,$valores);
		if ( $id_caixa>0 ) {

			$campos  = array(oper_nb_caixa,oper_tx_tipo,oper_tx_valor,oper_nb_userCadastro,oper_tx_dataCadastro,oper_tx_status);
			$valores = array($id_caixa,'Abertura',$valor_abertura,$_SESSION[user_nb_id],date("Y-m-d H:i:s"),'ativo');
			$id_aber = inserir('operacaocaixa',$campos,$valores);

			$a_retorno['status']      = 'OK';
			$a_retorno['mensagem']    = 'Caixa aberto com sucesso!';
			$a_retorno['id_caixa']    = $id_caixa;
			$a_retorno['id_abertura'] = $id_aber;
		}
	}

	return $a_retorno;
}




function get_valor_pagamento($id_forma=0,$id_caixa=0){

	$a_sistema = array();

	if ( $id_forma>0 && $id_caixa>0 ) {

		$extra = " AND fobo_nb_forma='$id_forma' AND orde_nb_caixa='$id_caixa' AND bole_tx_status!='inativo' AND fobo_tx_status!='inativo' AND orde_tx_status!='inativo' AND orde_tx_situacao='finalizado' ";

		$result = query(" SELECT SUM(fobo_tx_valor) AS total FROM fobo,boleto,movimento,ordem WHERE fobo_nb_boleto=bole_nb_id AND bole_nb_movimento=movi_nb_id AND movi_nb_ordem=orde_nb_id $extra ");
		$a_sistema = $result->fetch_assoc();

		if ( $id_forma==1 ) {// SE FOR DINHEIRO, TEM QUE CONTABILIZAR AS SANGRIAS E SUPRIMENTOS
			$result = query(" SELECT SUM(oper_tx_valor) AS total FROM operacaocaixa WHERE oper_nb_caixa='$id_caixa' AND oper_tx_tipo IN ('Abertura','Suprimento') AND oper_tx_status!='inativo' ");
			$a_entradas = $result->fetch_assoc();

			$result = query(" SELECT SUM(oper_tx_valor) AS total FROM operacaocaixa WHERE oper_nb_caixa='$id_caixa' AND oper_tx_tipo = 'Sangria' AND oper_tx_status!='inativo' ");
			$a_saidas = $result->fetch_assoc();

			// CONTABILIZA OS VALORES DAS OPERAÇÕES DE CAIXA
			$a_sistema[total] = $a_sistema[total]+$a_entradas[total]-$a_saidas[total];
		}
	}

	return $a_sistema;
}





function valor_3_casas($valor,$mostrar=0){

	if(floatval(@str_replace(array(','), array('.'), $valor)) ){
		$mostrar = 1;//SEMPRE VAI EXIBIR
	}

	if($mostrar == 1 || $valor > 0 ) {
		// nosso formato
		if (substr($valor, -4, 1) == ',')
			return @str_replace(array('.', ','), array('', '.'), $valor); // retorna 100000.500
		else
			return @number_format($valor, 3, ',', '.'); // retorna 100.000,500
	}else
		return '';
}




?>