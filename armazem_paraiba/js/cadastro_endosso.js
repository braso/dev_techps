if(typeof(appPath) == typeof(undefined)){
	alert('Erro');
	console.log('appPath indefinido');
	exit();
}
if(typeof(contexPath) == typeof(undefined)){
	alert('Erro');
	console.log('contexPath indefinido');
	exit();
}

const radioSim = document.getElementById('sim');
const radioNao = document.getElementById('nao');
$('[name=\"quantHoras\"]').inputmask({mask: ['99:99', '999:99']});
const campo = document.getElementById('max50APagar');
if (radioSim.checked) {
	campo.style.display = 'flex'; // Exibe o campo quando 'Mostrar Campo' é selecionado
}

// Adicionando um ouvinte de eventos aos elementos de rádio
radioSim.addEventListener('change', function() {
	if (radioSim.checked) {
		campo.style.display = 'flex'; // Exibe o campo quando 'Mostrar Campo' é selecionado
	}
});

radioNao.addEventListener('change', function() {
if (radioNao.checked) {
	campo.style.display = 'none'; // Oculta o campo quando 'Não Mostrar Campo' é selecionado
}
});
function carregarMotorista() {
	alert('carregarMotorista()');
}
function selecionaMotorista(idEmpresa) {
	let buscaExtra = encodeURI('AND enti_tx_ocupacao IN (\"Motorista\", \"Ajudante\", \"Funcionário\")'+
		(idEmpresa > 0? ' AND enti_nb_empresa = \"'+idEmpresa+'\"': '')
	);

	if ($('.busca_motorista').data('select2')) {// Verifica se o elemento está usando Select2 antes de destruí-lo
		$('.busca_motorista').select2('destroy');
		$('.busca_motorista').html('');
		$('.busca_motorista').val('');
	}
	
	$.fn.select2.defaults.set('theme', 'bootstrap');
	$('.busca_motorista').select2({
		language: 'pt-BR',
		placeholder: 'Selecione um item',
		allowClear: true,
		ajax: {
			url: appPath+'/contex20/select2.php?path='+contexPath+'&tabela=entidade&extra_ordem=&extra_limite=15&extra_bd='+buscaExtra+'&extra_busca=enti_tx_matricula',
			dataType: 'json',
			delay: 250,
			processResults: function(data){
				return {results: data};
			},
			cache: true,
			success: function(result){
			},
			error: function(jqxhr, status, exception) {
				alert('Exception:', exception);
			}
		}
	});
}

function pegarSaldoTotal(){
	if(Array.isArray(document.forms[0].acao)){
		button = document.forms[0].acao[0];
	}else{
		button = document.forms[0].acao;
	}
	button.value = 'pegarSaldoTotal';
	button.click();
}