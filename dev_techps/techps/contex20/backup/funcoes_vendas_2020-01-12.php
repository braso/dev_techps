<?php



function inicia_ordem($id_entidade=0,$tipo='PDV'){

	// CRIA UM ARRAY DE RETORNO COM UMA MENSAGEM E UM STATUS PADRÃO
	$a_retorno['status'] = 'ERRO';
	$a_retorno['mensagem'] = 'FALHA AO INICIAR A VENDA!';


	$campos  = array(orde_nb_entidade,orde_nb_caixa,orde_nb_vendedor,orde_tx_tipo,orde_tx_situacao,
					orde_nb_userCadastro,orde_tx_dataCadastro,orde_tx_status);
	$valores = array($id_entidade,$id_caixa,$id_entidade_vendedor,strtoupper($tipo),'andamento',
					$_SESSION[user_nb_id],date('Y-m-d H:i:s'),'ativo');

	$id_ordem = inserir('ordem',$campos,$valores);
	if ( $id_ordem>0 ) {
		$a_retorno['status'] = 'OK';
		$a_retorno['mensagem'] = 'Venda iniciada com sucesso!';
		$a_retorno['id_ordem'] = $id_ordem;
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
			$a_retorno['status'] = 'OK';
			$a_retorno['mensagem'] = 'Item adicionado com sucesso!';
			$a_retorno['id_orpr'] = $id_orpr;
		}

	} else {
		$a_retorno['mensagem'] = 'PRODUTO NÃO ENCONTRADO!';
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

				$a_retorno['status'] = 'OK';
				$a_retorno['mensagem'] = 'Desconto aplicado com sucesso!';
			}
		} else {
			$a_retorno['mensagem'] = 'ITEM NÃO ENCONTRADO!';
		}
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




function finaliza_ordem($id_ordem=0){

	// CRIA UM ARRAY DE RETORNO COM UMA MENSAGEM E UM STATUS PADRÃO
	$a_retorno['status'] = 'ERRO';
	$a_retorno['mensagem'] = 'FALHA AO INICIAR A VENDA!';


	$id_ordem = intval($id_ordem);
	if ( $id_ordem<=0 ) {
		$a_retorno['mensagem'] = 'CÓDIGO DA VENDA NÃO INFORMADO!';

	} else {

		// SOMA O VALOR DA VENDA
		$sql_orpr = "SELECT SUM(orpr_tx_valor) AS total, SUM(orpr_tx_valorBruto) AS total_bruto FROM orpr WHERE orpr_nb_ordem = '$id_ordem' AND orpr_tx_status!='inativo' ";
		$result = query($sql_orpr);
		$a_orpr = $result->fetch_assoc();

		// SOMA O VALOR PAGO
		$sql_pagamento = "SELECT SUM(paga_tx_valor) AS total_pagamento FROM pagamento WHERE paga_nb_ordem = '$id_ordem' AND paga_tx_status!='inativo' ";
		$result = query($sql_pagamento);
		$a_pagamento = $result->fetch_assoc();

		if ( $a_pagamento[total_pagamento]>=$a_orpr[total] ) {
			// SE O PAGAMENTO ESTIVER OK
			$campos  = array(orde_tx_situacao,orde_tx_valor,orde_tx_valorBruto);
			$valores = array('finalizado',$a_orpr[total],$a_orpr[total_bruto]);
			atualizar('ordem',$campos,$valores,$id_ordem);

			$a_retorno['status'] = 'OK';
			$a_retorno['mensagem'] = 'Venda iniciada com sucesso!';

		} else {
			$a_retorno['mensagem'] = 'O VALOR PAGA É MENOR QUE O DA VENDA!';
		}
	}

	return $a_retorno;
}




?>