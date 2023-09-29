<?php
include_once $_SERVER['DOCUMENT_ROOT']."/contex20/funcoes_vendas.php";
include "conecta.php";




function anula_agendamento(){

	$_POST[id] = $_POST[id_atendimento];
	
	atualizar('atendimento',array(aten_tx_situacao),array('Não Compareceu'),$_POST[id]);

	index();
	exit;
}




function exclui_agendamento(){

	remover('atendimento',$_POST[id]);

	index();
	exit;
}



function modifica_agendamento(){
	global $a_mod;

	$a_mod=carregar('atendimento',$_POST[id]);

	layout_agendamento();
	exit;
}



function cadastra_agendamento(){
	
	$dataCadastro = date("Y-m-d H:i:s");

	if($_POST[id]>0){
		$sql_ordem = query(" SELECT * FROM ordem WHERE orde_nb_atendimento = '$_POST[id]' AND orde_tx_status != 'inativo' LIMIT 1 ");
		$a_ordem = carrega_array($sql_ordem);
	}

	if ( $a_ordem[orde_tx_situacao]=='finalizado' ) {
		// VERIFICA SE O FINANCEIRO JÁ FOI ENCERRADO, PARA EVITAR PROBLEMAS
		set_status('ATENÇÃO: O financeiro do atendimento já está finalizado');
		modifica_agendamento();
		exit;
	}


	$dataAgenda = $_POST['data_agenda'];

	$campos  = array(aten_nb_pet,aten_tx_dataAgenda,aten_tx_turno,aten_nb_tipo,aten_tx_obs,aten_nb_profissional);
	$valores = array($_POST[pet],$dataAgenda,$_POST[turno],$_POST[tipo],$_POST[obs],$_POST[profissional]);



	$qtd_servico=0;
	for($i=1;$i<=10;$i++){
		// PERCORRE OS SERVIÇOS UMA VEZ PARA CONFIRMAR SE ALGUM FOI SELECIONADO
		if($_POST['servico'.$i]>0){
			$qtd_servico++;
			break;
		}
	}


	if ( $qtd_servico>0 ) {// SÓ CADASTRA/ATUALIZA CASO TENHA ALGUM SERVIÇO ADICIONADO
		
		if($_POST[id]>0){
			// SE ESTIVER ATUALIZANDO O AGENDAMENTO
			atualizar('atendimento',$campos,$valores,$_POST[id]);
			$id_aten=$_POST[id];

		} else {
			// ANTES DE INSERIR UM AGENDAMENTO, VERIFICA SE O MESO JÁ FOI INSERIDO
			$sql = query("SELECT aten_nb_id FROM atendimento WHERE aten_nb_pet = '$_POST[pet]' AND aten_tx_turno = '$_POST[turno]' AND aten_tx_dataAgenda='$dataAgenda' AND aten_tx_situacao = 'Agendado' AND aten_tx_status != 'inativo' LIMIT 1");
			if(num_linhas($sql)>0){
				$a=carrega_array($sql);
				$id_aten=$a[0];
				atualizar('atendimento',$campos,$valores,$id_aten);

			} else {
				// SE FOR CADASTRAR, ADICIONA OS DADOS
				array_push($campos, aten_tx_situacao,aten_nb_userCadastro,aten_tx_dataCadastro,aten_tx_status);
				array_push($valores, 'Agendado',$_SESSION[user_nb_id],$dataCadastro,'ativo');
			
				$id_aten=inserir('atendimento',$campos,$valores);
			}
		}


		if ( $a_ordem[orde_nb_id]==0 ) {
			// SE A ORDEM AINDA NÃO ESTIVER SIDO GERADA, CRIA UMA NOVA

			$a_pet = carregar('pet',$_POST[pet]);

			$a_retorno = inicia_ordem($a_pet[pet_nb_entidade],'AGENDAMENTO');
			if ( $a_retorno[status]!='OK' ) {
				// SE DER ERRO VOLTA PARA A TELA DE AGENDAMENTO
				$_POST[id] = $id_aten;
				set_status($a_retorno[mensagem]);
				modifica_agendamento();
				exit;
			}
			$id_ordem = $a_retorno['id_ordem'];
			// ASSOSIA A ORDEM GERADA COM O ATENDIMENTO
			atualizar('ordem',array(orde_nb_atendimento),array($id_aten),$id_ordem);

		} else {
			$id_ordem = $a_ordem[orde_nb_id];
		}



		// TRATA OS SERVIÇOS DO AGENDAMENTO
		for($i=1;$i<=10;$i++){
			$id_servico = intval($_POST['servico'.$i]);
			$id_atse    = intval($_POST['id_atse'.$i]);

			if( $id_servico>0 ){
				// SE TIVER UM SERVIÇO SELECIONADO, VERIFICA SE ESTÁ CADASTRANDO OU ATUALIZANDO
				$campos2  = array(atse_nb_atendimento,atse_nb_produto,atse_tx_valor,atse_tx_status);
				$valores2 = array($id_aten,$id_servico,valor($_POST[valor][$i]),'ativo');

				if ($id_atse>0) {
					atualizar('atse',$campos2,$valores2,$id_atse);
				} else {
					inserir('atse',$campos2,$valores2);
				}

			} elseif ( $id_atse>0 ) {
				// CASO ESTEJA REMOVENDO UM ATSE
				remover('atse',$id_atse);
			}
		}


		// APÓS TRATAR OS SERVIÇOS DO AGENDAMENTO, PERCORRE A TABELA ATSE PARA TRATAR A ORPR
		$sql_atse = query("SELECT * FROM atse,atendimento WHERE atse_nb_atendimento = aten_nb_id AND atse_nb_atendimento = '$id_aten' ");
		while($a_atse = carrega_array($sql_atse)){
			
			if ( $a_atse[atse_tx_status]!='inativo' && $a_atse[atse_nb_orpr]==0 ) {// SE ESTIVER ATIVO, MAS AINDA NÃO ESTIVER COM UM ORPR ASSOCIADO
				
				$campos  = array(orpr_nb_ordem,orpr_nb_produto,orpr_tx_valor,orpr_tx_valorBruto,orpr_tx_valorUnitario,
								orpr_tx_quantidade,orpr_nb_userCadastro,orpr_tx_dataCadastro,orpr_tx_status);
				$valores = array($id_ordem,$a_atse[atse_nb_produto],$a_atse[atse_tx_valor],$a_atse[atse_tx_valor],$a_atse[atse_tx_valor],
								1,$_SESSION[user_nb_id],date('Y-m-d H:i:s'),'ativo');
				$id_orpr = inserir('orpr',$campos,$valores);// CRIA UM ORPR PARA O ATSE

				atualizar('atse',array(atse_nb_orpr),array($id_orpr),$a_atse[atse_nb_id]);// ASSOCIA O ATSE COM O ORPR

			} elseif ( $a_atse[atse_tx_status]!='inativo' ) {
				$campos  = array(orpr_nb_ordem,orpr_nb_produto,orpr_tx_valor,orpr_tx_valorBruto,orpr_tx_valorUnitario,orpr_tx_quantidade,orpr_tx_status);
				$valores = array($id_ordem,$a_atse[atse_nb_produto],$a_atse[atse_tx_valor],$a_atse[atse_tx_valor],$a_atse[atse_tx_valor],1,'ativo');
				atualizar('orpr',$campos,$valores,$a_atse[atse_nb_orpr]);// SE O ATSE ESTIVER ATIVO, ATUALIZA O ORPR

			} elseif ( $a_atse[atse_tx_status]=='inativo' ) {
				// SE O ATSE ESTIVER SIDO EXCLUÍDO, REMOVE A ORPR TAMBÉM
				remover('orpr',$a_atse[atse_nb_orpr]);
			}
		}

		// UTILIZEI UMA SQL PARA TOTALIZAR PARA GARANTIR QUE SERÁ OBTIDO O TOTAL CORRETO, MESMO QUE A ORDEM TENHA SIDO ALTERADA NO PDV
		// $sql_orpr = query("SELECT SUM(orpr_tx_valor) AS total, SUM(orpr_tx_valorBruto) AS total_bruto, SUM(orpr_tx_descontoReais) AS total_desconto_reais FROM orpr WHERE orpr_nb_ordem = '$id_ordem' AND orpr_tx_status!='inativo' ");
		// $a_orpr   = carrega_array($sql_orpr);
		$a_soma = get_soma_venda($id_ordem);

		
		// ATUALIZA OS VALORES DA ORDEM APOŚ O AJUSTE DO AGENDAMENTO
		$campos  = array(orde_tx_valor,orde_tx_valorBruto,orde_tx_descontoReais,orde_tx_data);
		$valores = array($a_soma[valor],$a_soma[valorBruto],$a_soma[descontoReais],$dataAgenda);
		atualizar('ordem',$campos,$valores,$id_ordem);
	}


	index();
	exit;
}




function cadastra_evolucao(){

	atualizar('atendimento',array(aten_tx_evolucao),array(addslashes($_POST[evolucao])),$_POST[id]);

	if ( $_POST[finalizar]=='sim' ) {
		atualizar('atendimento',array(aten_tx_situacao),array('Atendido'),$_POST[id]);
		set_status('Atendimento finalizado com sucesso!');
		index();
	} else {
		set_status('Evolução cadastrada com sucesso!');
		layout_atendimento();
	}	
	exit;
}




function layout_atendimento(){

	$a_mod = carregar('atendimento,pet',$_POST[id]);

	$a_clie = carregar('entidade',$a_mod[pet_nb_entidade]);
	$a_tipo = carregar('tipoatendimento',$a_mod[aten_nb_tipo]);
	$a_prof = carregar('profissional',$a_mod[aten_nb_profissional]);

	cabecalho("Agendamento");
		
	if( !$_POST[paciente] )
		$extra .=" AND enti_tx_nome LIKE '%$a_mod[aten_tx_entidade]%'";
	else
		$extra .=" AND enti_tx_nome LIKE '%$_POST[paciente]%'";

	
	$c[]=texto('Atendimento',$a_mod[aten_nb_id],2);
	$c[]=texto('PET',$a_mod[pet_tx_nome],5);
	$c[]=texto('Cliente',$a_clie[enti_tx_nome],5);

	$c[]=texto('Data',data($a_mod[aten_tx_dataAgenda]),2);
	$c[]=texto('Turno',$a_mod[aten_tx_turno],2);
	$c[]=texto('Tipo',$a_tipo[tipo_tx_nome],2);
	$c[]=texto('Profissional',$a_prof[prof_tx_nome],6);



	
	$sql2 = query("SELECT * FROM atse,produto WHERE atse_tx_status != 'inativo' AND atse_nb_atendimento = '$_POST[id]' AND atse_nb_produto = prod_nb_id");
	// $sql2 = query("SELECT * FROM atse,atendimento WHERE atse_tx_status != 'inativo' AND atse_nb_atendimento = '$_POST[id]' AND atse_nb_atendimento = aten_nb_id GROUP BY atse_nb_produto ORDER BY atse_nb_id");
	while($a2=carrega_array($sql2)){
		++$j;

		$c[]=texto('Serviço '.$j,"$a2[prod_tx_nome]",4);
		$c[]=texto('Valor '.$j,valor($a2[atse_tx_valor]),2);
		$total += $a2[atse_tx_valor];
		
	}
	if($total > 0 )
		$c[]=texto('Total','R$'.valor($total),6);

	$c2[]=texto('Observação',$a_mod[aten_tx_obs],6);


	$c3[]=textarea('Evolução','evolucao',$a_mod[aten_tx_evolucao],12);


	$b[]=botao('Gravar','cadastra_evolucao','id',$a_mod[aten_nb_id]);
	$b[]=botao('Finalizar','cadastra_evolucao','id,finalizar',"$_POST[id],sim");
	$b[]=botao('Não Compareceu','anula_agendamento','id_atendimento',$_POST[id]);
	$b[]=botao('Voltar','index','busca_data',data(data($a_mod[aten_tx_dataAgenda])));

	abre_form('Dados do Agendamento');
	linha_form($c);
	linha_form($c2);
	linha_form($c3);
	fecha_form($b);

	rodape();
}





function layout_agendamento(){
	global $a_mod;

	$a_aten = carregar('atendimento',$_POST[id]);
	

	cabecalho("Agendamento");
	
	$a_tipo = carregar('tipoatendimento',$a_mod[aten_nb_tipo]);
	$a_prof = carregar('profissional',$a_mod[aten_nb_profissional]);

	
	$c[]=combo_net('Pet','pet',$a_mod[aten_nb_pet],5,'pet');
	$c[]=combo('Turno','turno',$a_mod[aten_tx_turno],2,array('Manhã','Tarde','Noite'));
	$c[]=combo_bd('!Profissional','profissional',$a_mod[aten_nb_profissional],3,'profissional');
	$c[]=combo_bd('Tipo','tipo',$a_mod[aten_nb_tipo],2,'tipoatendimento');
	
	$c[]=campo('Observação','obs',$a_mod[aten_tx_obs],7);



	abre_form('Dados do Agendamento');
	linha_form($c);
	echo"<br>";
	fieldset('SERVIÇOS');



	$sql2 = query("SELECT atse_nb_id,atse_nb_produto,atse_tx_valor FROM atse,atendimento WHERE atse_tx_status != 'inativo' AND atse_nb_atendimento = '$_POST[id]' AND atse_nb_atendimento = aten_nb_id GROUP BY atse_nb_produto ORDER BY atse_nb_id");
	for($i=1;$i<=10;$i++){
		
		$a_mod2 = carrega_array($sql2);
		$c2[]=combo_net("Serviço $i","servico$i",$a_mod2[atse_nb_produto],5,'produto',"onfocus='' onchange='carrega_servicojs(this.value,$i);abre_proximo(".($i+1).")'",' AND prod_tx_servico="Sim" ');
		$c2[]=campo("Valor $i","valor[$i]",valor($a_mod2[atse_tx_valor]),2,MASCARA_VALOR,"onfocus='abre_proximo(".($i+1).")' readonly='true' ");
		
		if($a_mod2!='' || $i==1)
			$display = 'inline';
		else
			$display = 'none';

		echo "<div id=div_$i style='display:$display;'>";
			linha_form($c2);
			campo_hidden("id_atse$i",$a_mod2[atse_nb_id]);
		echo "</div>";
		unset($c2);
	}


	if ( $a_aten[aten_nb_id]>0 && $_POST[data_agenda]=='' ) {
		// SE ESTIVER ATUALIZANDO, PASSA A DATA PARA O POST
		$_POST[data_agenda] = $a_aten[aten_tx_dataAgenda];
	}


	$b[]=botao('Gravar','cadastra_agendamento','id,data_agenda',"$_POST[id],$_POST[data_agenda]");
	$b[]=botao('Voltar','index','busca_data',data(data($a_mod[aten_tx_dataAgenda])));
	echo "<script type=\"text/javascript\">
	
	
	</script>
	";

	?>
	<iframe id="frame_carrega_valor" style="display: none;"></iframe>
	<script type="text/javascript">
		function abre_proximo(id_div){
			document.getElementById('div_'+id_div).style.display='inline';
		}

		function carrega_servicojs(id_proc,id_campo){
			document.getElementById('frame_carrega_valor').src='agendamento.php?acao=carrega_servico&id_proc='+id_proc+'&id_campo=valor['+id_campo+']';
		}

		
	</script>
	<?

	fecha_form($b);
	rodape();

}

function carrega_servico(){
	$sql=query("SELECT prod_tx_preco FROM produto WHERE prod_nb_id='$_GET[id_proc]' LIMIT 1");
	$a=carrega_array($sql);
	?>
	<script type="text/javascript">
		parent.document.getElementById('<?=$_GET[id_campo]?>').value='<?=valor($a[0])?>';
	</script>
	<?
}


function carrega_cliente($id_cliente){
	if ( $id_cliente>0 ) {
		$a_clie = carregar('entidade',$id_cliente);
		return $a_clie[enti_tx_nome];
	} else {
		return '';
	}
}





function index(){
	cabecalho("Agendamento");

	// NO IF ABAIXO, SE O USUÁRIO FOR DENTISTA, AO ACESSAR A ROTINA DE AGENDAMENTO SÓ VERÁ OS QUE JÁ ESTIVEREM MARCADOS FOREM PARA ELE
	if($_SESSION[user_tx_nivel] == 'Profissional'){
		if(!$_POST[busca_situacao])
			$_POST[busca_situacao] = 'Marcado';

		$sql = query("SELECT * FROM profissional WHERE prof_tx_status != 'inativo' AND prof_nb_user = '$_SESSION[user_nb_id]'");
		$a_prof = carrega_array($sql);
		$extra .= " AND aten_nb_profissional = $a_prof[prof_nb_id]";		
	}

	if(!$_POST[busca_situacao])
		$_POST[busca_situacao] = 'Agendado';

	if($_POST[busca_codigo] != '')
		$extra .=" AND aten_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_pet] != '')
		$extra .=" AND pet_tx_nome LIKE '%$_POST[busca_pet]%'";
	if($_POST[busca_turno] != '')
		$extra .=" AND aten_tx_turno = '$_POST[busca_turno]'";
	
	if($_POST[busca_data] != ''){
		$extra .=" AND aten_tx_dataAgenda LIKE '".($_POST[busca_data])."%'";
	}else{
		$_POST[busca_data] = date("Y-m-d");
		$extra .=" AND aten_tx_dataAgenda LIKE '".($_POST[busca_data])."%'";
	}

	if($_POST[busca_situacao] != '')
		$extra .=" AND aten_tx_situacao = '$_POST[busca_situacao]'";

	$c[]=campo('Código','busca_codigo',$_POST[busca_codigo],1);
	$c[]=campo('Pet','busca_pet',$_POST[busca_pet],3);
	$c[]=combo('Turno','busca_turno',$_POST[busca_turno],2,array('','Manhã','Tarde','Noite'));
	// $c[]=datepick('Data','busca_data',$_POST[busca_data],2);
	$c[]=campo_data('Data','busca_data',$_POST[busca_data],2);
	$c[]=combo('Situação','busca_situacao',$_POST[busca_situacao],2,array('Agendado','Marcado','Atendido','Cancelado','Não Compareceu'));
	
	$b[]=botao('Buscar','index');
	$b[]=botao('Inserir','layout_agendamento','data_agenda',$_POST[busca_data]);

	abre_form('Filtro de Busca');
	linha_form($c);


	fecha_form($b);

	$sql = "SELECT * FROM atendimento,pet WHERE aten_tx_status != 'inativo' AND aten_nb_pet=pet_nb_id $extra";
	$cab = array('CÓDIGO','DATA','PET','CLIENTE','TURNO','SITUAÇÃO','','','');
	
	if($_POST[busca_situacao] == 'Agendado')
		$atender = 'icone_modificar(aten_nb_id,layout_atendimento,,,,glyphicon glyphicon-check)';
	else
		$atender = '';
	
	$modificar = 'icone_modificar(aten_nb_id,modifica_agendamento)';
	$excluir   = 'icone_excluir(aten_nb_id,exclui_agendamento)';
	
	$val = array('aten_nb_id','data(aten_tx_dataAgenda)','pet_tx_nome','carrega_cliente(pet_nb_entidade)','aten_tx_turno','aten_tx_situacao',$atender,$modificar,$excluir);
	grid($sql,$cab,$val);

	rodape();
}

?>