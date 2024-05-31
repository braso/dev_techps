<?php
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/

	include "funcoes_ponto.php";
	
	function index(){
		cabecalho('Cadastro Abono');

		$c[] = combo_net('Motorista*:','motorista',$_POST['busca_motorista']?? '',4,'entidade','',' AND enti_tx_ocupacao = "Motorista"','enti_tx_matricula');
		$c[] = campo('Data(s)*:','daterange', ($_POST['daterange']?? ''),3);
		$c[] = campo_hora('Abono*: (hh:mm)','abono', ($_POST['abono']?? ''),3);
		$c2[] = combo_bd('Motivo*:','motivo', ($_POST['motivo']?? ''),4,'motivo','',' AND moti_tx_tipo = "Abono"');
		$c2[] = textarea('Justificativa:','descricao', ($_POST['descricao']?? ''),12);
		
		//BOTOES
		$b[] = botao(
			'Voltar', 
			'voltar', 
			implode(",",array_keys($_POST)),
			implode(",",array_values($_POST))
		);
		$b[] = botao("Gravar",'cadastra_abono','','','','','btn btn-success');
		
		abre_form('Filtro de Busca');
		linha_form($c);
		linha_form($c2);
		fecha_form($b);

		rodape();

		?>
		<script type="text/javascript" src="js/moment.min.js"></script>
		<script type="text/javascript" src="js/daterangepicker.min.js"></script>
		<link rel="stylesheet" type="text/css" href="js/daterangepicker.css" />

		<script>
			$(function() {
				$('input[name="daterange"]').daterangepicker({
					opens: 'left',
					"locale": {
						"format": "DD/MM/YYYY",
						"separator": " - ",
						"applyLabel": "Aplicar",
						"cancelLabel": "Cancelar",
						"fromLabel": "From",
						"toLabel": "To",
						"customRangeLabel": "Custom",
						"weekLabel": "W",
						"daysOfWeek": ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sab"],
						"monthNames": ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"],
						"firstDay": 1
					},
				}, function(start, end, label) {
					// console.log("A new date selection was made: " + start.format('YYYY-MM-DD') + ' to ' + end.format('YYYY-MM-DD'));
				});
			});
		</script>
		<?php
	}