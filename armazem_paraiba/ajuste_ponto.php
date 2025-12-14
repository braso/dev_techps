<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Expires: 01 Jan 2001 00:00:00 GMT");
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Cache-Control: post-check=0, pre-check=0", FALSE);
	//*/

	include_once "funcoes_ponto.php";

	function cadastrarAjuste(){
		try{
			$matricula = mysqli_fetch_assoc(query(
				"SELECT enti_tx_matricula FROM entidade
					WHERE enti_tx_status = 'ativo'
						AND enti_nb_id = {$_POST["idMotorista"]}
					LIMIT 1;"
			))["enti_tx_matricula"];
			$newPonto = conferirErroPonto($matricula, new DateTime("{$_POST["data"]} {$_POST["hora"]}"), $_POST["idMacro"], $_POST["motivo"], $_POST["justificativa"]);
		}catch(Exception $e){
			set_status($e->getMessage());
			index();
			exit;
		}

		//Conferir se já existe um ponto naquele segundo para adicionar o próximo 1 segundo após{
			$temPonto = mysqli_fetch_assoc(query(
				"SELECT pont_nb_id, pont_tx_data FROM ponto
					WHERE pont_tx_status = 'ativo'
						AND pont_tx_matricula = '{$matricula}'
						AND STR_TO_DATE(pont_tx_data, '%Y-%m-%d %H:%i') = STR_TO_DATE('".($_POST["data"]." ".$_POST["hora"])."', '%Y-%m-%d %H:%i')
					ORDER BY pont_tx_data DESC
					LIMIT 1;"
			));
	
			if(!empty($temPonto["pont_tx_data"])){
				$seg = explode(":", $temPonto["pont_tx_data"])[2];
				$seg = intval($seg)+1;
				$newPonto["pont_tx_data"] = "{$_POST["data"]} {$_POST["hora"]}:".str_pad(strval($seg), 2, "0", STR_PAD_LEFT);
			}
		//}

		inserir("ponto", array_keys($newPonto), array_values($newPonto));
		index();
		exit;
	}

    function excluirPonto(){

        $ids = [];
        if(!empty($_POST["idPonto"])){
            $ids = is_array($_POST["idPonto"]) ? $_POST["idPonto"] : [$_POST["idPonto"]];
        }

        if(empty($ids) || empty($_POST["justificativa"])){
            set_status("ERRO: Não foi possível inativar o ponto.");
            index();
            exit;
        }

        if(empty($_POST["dataAtualiza"])){
            $_POST["dataAtualiza"] = date("Y-m-d H:i:s");
            $_POST["userAtualiza"] = $_SESSION["user_nb_id"];
        }

        $dataRef = null;
        foreach($ids as $id){
            $ponto = mysqli_fetch_assoc(query(
                "SELECT * FROM ponto 
                    WHERE pont_nb_id = {$id} 
                    LIMIT 1;"
            ));
            if(empty($ponto)){ continue; }
            if($ponto["pont_tx_status"] == "inativo"){ continue; }
            $ponto["pont_tx_status"] = "inativo";
            $ponto["pont_tx_justificativa"] = $_POST["justificativa"];
            $ponto["pont_tx_dataAtualiza"] = $_POST["dataAtualiza"];
            $ponto["pont_nb_userAtualiza"] = $_POST["userAtualiza"];
            atualizar("ponto", array_keys($ponto), array_values($ponto), $ponto["pont_nb_id"]);
            if(empty($dataRef)){
                $dataRef = explode(" ", $ponto["pont_tx_data"])[0];
            }
        }

        if(!empty($dataRef)){
            $_POST["data"] = $dataRef;
        }

        index();
        exit;
    }

	function status() {
		return  
			"<style>
				#statusDiv{
					display: inline-flex;
				}
				#status-label{
					margin-right: 10px; 
				}
				#status {
					margin-top: -5px;
					width: 93px;
				}
				</style>
				<div id='statusDiv'>
					<label id='status-label'>Status:</label>
					<select name='status' id='status' class='form-control input-sm campo-fit-content' onchange='atualizar_form({$_POST["idMotorista"]}, \"{$_POST["data"]}\", this.value)'>
						<option value='ativo'>Ativos</option>
						<option value='inativo' ".((!empty($_POST["status"]) && $_POST["status"] == "inativo")? "selected": "").">Inativos</option>
					</select>
				</div>"
		;
	}

	// Função para carregar os CNPJs formatados da tabela "empresa"
	// function carregarCNPJsFormatados() {
	// 	global $conn;
	// 	// Consulta SQL para buscar os CNPJs
	// 	$sql = "SELECT empr_tx_cnpj FROM empresa";

	// 	$result = mysqli_query($conn, $sql);

	// 	if (!$result) {
	// 		die("Erro ao consultar CNPJs: ".mysqli_error($conn));
	// 	}

	// 	$cnpjs_formatados = [];
	// 	while ($row = mysqli_fetch_assoc($result)) {
	// 		// Remove pontos, traços e barras do CNPJ
	// 		$cnpj_formatado = preg_replace("/[^0-9]/", "", $row["empr_tx_cnpj"]);
	// 		$cnpjs_formatados[] = $cnpj_formatado;
	// 	}
	// 	return $cnpjs_formatados;
	// }

	function carregarJS(){

		$postValues = $_POST;
		$postValues["acao"] = '';
		$postValues["idPonto"] = '';
		unset($postValues["id"]);
		unset($postValues["errorFields"]);
		unset($postValues["msg_status"]);
		$postValues = json_encode($postValues);
		echo 
			"<script>
				function imprimir() {
					// Abrir a caixa de diálogo de impressão
					window.print();
				}

				function addPostValuesToForm(form, postValues){
					input = '';
					for(key in postValues){
						input = document.createElement('input');
						input.type = 'hidden';
						input.value = postValues[key];
						input.name = key;
						if(Array.isArray(postValues[key])){
							input.name += '[]';
							for(f2 in postValues[key]){
								newInput = document.createElement('input');
								newInput.type = input.type;
								newInput.name = input.name;
								newInput.value = postValues[key][f2];
								form.append(newInput);
							}
						}else{
							form.append(input);
						}
					}
				}

				valorDataInicial = document.getElementById('data').value;
				valorStatusInicial = document.getElementById('status').value;

				function atualizar_form(motorista, data, status){
					if(data == null){
						data = document.getElementById('data').value;
					}
					if(status == null){
						status = document.getElementById('status').value;
					}

					if(valorDataInicial != data || valorStatusInicial != status){
						var form = document.form_ajuste_status;
						addPostValuesToForm(form, {$postValues});
						form.acao.value = 'index';
						form.data.value = data;
						form.status.value = status;
						form.submit();
					}
				}

                function excluirPontoJS(idPonto){
                    var selecionados = [];
                    var checks = document.querySelectorAll('input.bulk-excluir:checked');
                    for(var i=0;i<checks.length;i++){ selecionados.push(checks[i].getAttribute('data-id')); }
                    if(selecionados.length === 0 && idPonto){ selecionados.push(String(idPonto)); }
                    if(selecionados.length === 0){ return; }

                    Swal.fire({
                        icon: 'warning',
                        title: 'Excluir ponto(s)',
                        html: '<p>Ao excluir o ponto, a recuperação torna-se irreversível.</p>'+
                              '<label style=\'display:block;text-align:left;margin-top:10px;\'>Justificativa</label>'+
                              '<textarea id=\'swal-justificativa\' class=\'swal2-textarea\' placeholder=\'Descreva o motivo\'></textarea>',
                        showCancelButton: true,
                        confirmButtonText: 'Excluir',
                        cancelButtonText: 'Cancelar',
                        focusConfirm: false,
                        preConfirm: function(){
                            var v = document.getElementById('swal-justificativa').value.trim();
                            if(!v){ Swal.showValidationMessage('Informe a justificativa'); }
                            return v;
                        }
                    }).then(function(res){
                        if(!res.isConfirmed || !res.value){ return; }
                        var form = document.form_ajuste_status;
                        addPostValuesToForm(form, {$postValues});
                        form.acao.value = 'excluirPonto';
                        for(var j=0;j<selecionados.length;j++){
                            var inp = document.createElement('input');
                            inp.type = 'hidden';
                            inp.name = 'idPonto[]';
                            inp.value = selecionados[j];
                            form.append(inp);
                        }
                        var justificativa = document.createElement('input');
                        justificativa.type = 'hidden';
                        justificativa.name = 'justificativa';
                        justificativa.value = res.value;
                        form.append(justificativa);
                        form.submit();
                    });
                }
            </script>"
        ;
	}




		 function index() {

		//ARQUIVO QUE VALIDA A PERMISSAO VIA PERFIL DE USUARIO VINCULADO
		include "check_permission.php";
		// APATH QUE O USER ESTA TENTANDO ACESSAR PARA VERIFICAR NO PERFIL SE TEM ACESSO2
		verificaPermissao('/ajuste_ponto.php');
		
		
		global $CONTEX;

		//Conferir se os campos de $_POST estão vazios{
			if(empty($_POST["idMotorista"]) || empty($_POST["data"])){
				echo "<script>alert('ERRO: Deve ser selecionado um funcionário e uma data para ajustar.')</script>";
				
				$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/espelho_ponto.php";
				if(empty($_POST["returnValues"])){
					$_POST["returnValues"] = json_encode($_POST);
				}
				voltar();
				exit;
			}
	
			if (empty($_POST["status"])) {
				$_POST["status"] = "ativo";
			}
		//}

		cabecalho("Ajuste de Ponto");


		$motorista = mysqli_fetch_assoc(query(
			"SELECT enti_nb_id, enti_tx_matricula, enti_tx_nome, enti_tx_ocupacao, enti_tx_cpf, enti_nb_empresa FROM entidade
				WHERE enti_tx_status = 'ativo'
					AND enti_nb_id = {$_POST["idMotorista"]}
				LIMIT 1;"
		));

		$endosso = mysqli_fetch_array(query(
			"SELECT user_tx_login as endo_nb_userCadastro, endo_tx_dataCadastro FROM endosso
				JOIN user ON endo_nb_userCadastro = user_nb_id
				WHERE endo_tx_status = 'ativo'
					AND '{$_POST["data"]}' BETWEEN endo_tx_de AND endo_tx_ate
					AND endo_nb_entidade = '{$motorista["enti_nb_id"]}'
				LIMIT 1;"
		), MYSQLI_BOTH);

		$botao_imprimir = "<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>";

		$cnpjs = mysqli_fetch_all(query("SELECT empr_tx_cnpj FROM empresa"), MYSQLI_ASSOC);

		// Assumindo que $motorista já tenha os valores definidos
		// Construir o botão com o código JavaScript embutido
		$botaoConsLog = 
			"<button class='btn default' type='button' onclick='consultarLogistica()'>Consultar Logística</button>
			<script>
			function consultarLogistica() {
				// Obter valores do PHP e HTML
				var matricula = '{$motorista["enti_tx_matricula"]}';
				var motorista = '{$motorista["enti_tx_nome"]}';
				var data = document.getElementById('data').value;

				// Obter todos os CNPJs da variável PHP
				var cnpjs = ".json_encode($cnpjs).";

				// Verificar o conteúdo de cnpjs no console
				// console.log('CNPJs:', cnpjs);

				if (!Array.isArray(cnpjs)) {
					console.error('CNPJs não é um array:', cnpjs);
					return;
				}

				if (cnpjs.length === 0) {
					console.error('A lista de CNPJs está vazia.');
					return;
				}

				// Converte a lista de CNPJs para uma string separada por vírgulas
				var cnpjString = cnpjs.map(String).join(',');

				// Construir a URL com os parâmetros dinâmicos
				var url = '".$_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/logistica.php';
				url += '?motorista='+encodeURIComponent(motorista)+
					'&matricula='+encodeURIComponent(matricula)+
					'&data='+encodeURIComponent(data) +
					'&cnpj='+encodeURIComponent(cnpjString);  // Adicionando todos os CNPJs

				// Abrir a nova página em uma nova aba
				window.open(url, '_blank');
			}
			</script>"
		;
		$botaoLocEventos = 
			"<button class='btn default' type='button' onclick='abrirLocalizacoesEventos()'>Localização Eventos</button>
			<link rel='stylesheet' href='https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'>
			<link rel='stylesheet' href='https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css'>
			<link rel='stylesheet' href='https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css'>
			<script src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'></script>
			<script src='https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js'></script>
			<div id='mapModal' style='display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:10000; align-items:center; justify-content:center;'>
				<div style='background:#fff; width:90%; height:80%; border-radius:8px; position:relative; padding:8px; display:flex; flex-direction:column;'>
					<div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:6px'>
						<div style='font-weight:bold'>Localização de eventos</div>
						<button type='button' id='closeMap' class='btn btn-default'>Fechar</button>
					</div>
					<div id='leafletMap' style='width:100%; height:100%; border:1px solid #eee; border-radius:6px'></div>
				</div>
			</div>
			<script>
			var __ajusteMap=null;
			var __ajusteCluster=null;
			var __ajusteMarkers=[];
			var __ajusteLinkLayer=null;
			var __ajusteLegendDiv=null;
			var __ajusteSelectedIdx=-1;
			function __ajusteHighlightLegendItem(idx){
				try{
					if(!__ajusteLegendDiv) return;
					var items=__ajusteLegendDiv.querySelectorAll('[data-idx]');
					for(var i=0;i<items.length;i++){
						items[i].style.background='';
						items[i].style.border='';
					}
					var el=__ajusteLegendDiv.querySelector('[data-idx=\"'+idx+'\"]');
					if(el){
						el.style.background='#d4edda';
						el.style.border='1px solid #28a745';
						__ajusteSelectedIdx=idx;
					}
				}catch(e){}
			}
			function __ajusteComputeDistanceMeters(lat1,lng1,lat2,lng2){
				var R=6371000;
				var toRad=function(v){ return v*Math.PI/180; };
				var dLat=toRad(lat2-lat1);
				var dLng=toRad(lng2-lng1);
				var a=Math.sin(dLat/2)*Math.sin(dLat/2)+Math.cos(toRad(lat1))*Math.cos(toRad(lat2))*Math.sin(dLng/2)*Math.sin(dLng/2);
				var c=2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));
				return R*c;
			}
			function __ajusteIsStart(tipoUpper){
				return (tipoUpper.indexOf('INICIO')>=0);
			}
			function __ajusteBaseKey(tipoUpper){
				var key=tipoUpper.replace('INICIO DE ','').replace('FIM DE ','').trim();
				return key;
			}
			function __ajusteFindPairIndex(coords, idx){
				var c=coords[idx];
				var tUpper=(c.tipo||'').toUpperCase();
				var base=__ajusteBaseKey(tUpper);
				var isStart=__ajusteIsStart(tUpper);
				if(!base) return -1;
				if(isStart){
					for(var i=idx+1;i<coords.length;i++){
						var ti=(coords[i].tipo||'').toUpperCase();
						if(ti.indexOf('FIM')>=0 && __ajusteBaseKey(ti)===base){ return i; }
					}
				}else{
					for(var j=idx-1;j>=0;j--){
						var tj=(coords[j].tipo||'').toUpperCase();
						if(tj.indexOf('INICIO')>=0 && __ajusteBaseKey(tj)===base){ return j; }
					}
				}
				return -1;
			}
			function __ajusteShowPair(coords, idx){
				if(!__ajusteMap) return;
				var pairIdx=__ajusteFindPairIndex(coords, idx);
				if(pairIdx<0) return;
				var a=coords[idx];
				var b=coords[pairIdx];
				var start=a, end=b;
				var aIsStart=__ajusteIsStart((a.tipo||'').toUpperCase());
				if(!aIsStart){ start=b; end=a; }
				if(__ajusteLinkLayer){ __ajusteMap.removeLayer(__ajusteLinkLayer); __ajusteLinkLayer=null; }
				var dist=__ajusteComputeDistanceMeters(start.lat,start.lng,end.lat,end.lng);
				var severityColor='#FFA500';
				var base=__ajusteBaseKey((a.tipo||'').toUpperCase());
				if(dist>500){
					var bUpper=base.toUpperCase();
					if(bUpper.indexOf('ESPERA')>=0 || bUpper.indexOf('DESCANSO')>=0){ severityColor='#FF0000'; }
					else if(bUpper.indexOf('REFEI')>=0){ severityColor='#FF7F00'; }
				}
				__ajusteLinkLayer=L.polyline([[start.lat,start.lng],[end.lat,end.lng]],{color:severityColor,weight:4,dashArray:'6 6'}).addTo(__ajusteMap);
				var midLat=(start.lat+end.lat)/2;
				var midLng=(start.lng+end.lng)/2;
				var warnText='';
				if(dist>500){
					var bUpper2=base.toUpperCase();
					var nivel=(bUpper2.indexOf('ESPERA')>=0 || bUpper2.indexOf('DESCANSO')>=0)? 'Warning Grave' : 'Warning';
					warnText='<div style=\"color:'+severityColor+'; font-weight:bold\">'+nivel+' - Houve deslocamento maior que o esperado</div>';
				}
				var html='<div style=\"font-size:16px; line-height:1.5\">'
					+'<div style=\"font-weight:bold; font-size:17px\">'+(a.tipo||'')+'</div>'
					+'<div>Início: '+(start.data||'')+(start.hora? ' '+start.hora : '')+'</div>'
					+'<div>Fim: '+(end.data||'')+(end.hora? ' '+end.hora : '')+'</div>'
					+'<div>Distância aprox.: '+(dist/1000).toFixed(2)+' km</div>'
					+warnText
					+'</div>';
				L.popup({maxWidth:480}).setLatLng([midLat,midLng]).setContent(html).openOn(__ajusteMap);
				__ajusteMap.fitBounds([[start.lat,start.lng],[end.lat,end.lng]],{padding:[40,40]});
			}
			function __ajusteZoomToEvent(i){
				if(!__ajusteMap || !__ajusteCluster) return;
				var m=__ajusteMarkers[i];
				if(!m) return;
				__ajusteCluster.zoomToShowLayer(m,function(){
					m.openPopup();
					__ajusteHighlightLegendItem(i);
					__ajusteShowPair(window.__ajusteCoordsRef||[], i);
				});
			}
			function abrirLocalizacoesEventos(){
				var t=document.querySelector('[id^=\"contex-grid-\"]');
				if(!t) return;
				var ths=t.querySelectorAll('thead tr th');
				var idxTipo=-1, idxLoc=-1, idxLeg=-1, idxData=-1, idxHora=-1;
				for(var i=0;i<ths.length;i++){
					var txt=(ths[i].textContent||'').trim().toUpperCase();
					if(txt==='TIPO') idxTipo=i;
					if(txt.indexOf('LOCALIZA')>=0) idxLoc=i;
					if(txt==='LEGENDA') idxLeg=i;
					if(idxData<0 && txt==='DATA') idxData=i;
					if(idxHora<0 && txt==='HORA') idxHora=i;
				}
				if(idxLoc<0) return;
				var rows=t.querySelectorAll('tbody tr');
				var coords=[];
				for(var r=0;r<rows.length;r++){
					var tds=rows[r].children;
					if(!tds || tds.length===0) continue;
					var tipo=(idxTipo>=0 && tds[idxTipo])? (tds[idxTipo].textContent||'').trim() : '';
					var legenda=(idxLeg>=0 && tds[idxLeg])? (tds[idxLeg].textContent||'').trim() : '';
					var dataRaw=(idxData>=0 && tds[idxData])? (tds[idxData].textContent||'').trim() : '';
					var mDate=dataRaw? dataRaw.match(/(\d{2}\/\d{2}\/\d{4})/) : null;
					var mTime=dataRaw? dataRaw.match(/(\d{2}:\d{2}:\d{2})/) : null;
					var dataVal=mDate? mDate[1] : '';
					var horaVal=mTime? mTime[1] : '';
					if(!horaVal && idxHora>=0 && tds[idxHora]){ horaVal=(tds[idxHora].textContent||'').trim(); }
					var a=tds[idxLoc]? tds[idxLoc].querySelector('a[href*=\"google.com/maps?q\"]') : null;
					if(!a) continue;
					var href=a.getAttribute('href')||'';
					var qIndex=href.indexOf('q=');
					if(qIndex<0) continue;
					var qStr=href.substring(qIndex+2);
					var parts=qStr.split(',');
					if(parts.length<2) continue;
					var lat=parseFloat(parts[0]);
					var lng=parseFloat(parts[1]);
					if(isNaN(lat)||isNaN(lng)) continue;
					coords.push({lat:lat,lng:lng,tipo:tipo,legenda:legenda,data:dataVal,hora:horaVal});
				}
				var modal=document.getElementById('mapModal');
				modal.style.display='flex';
				var mapDiv=document.getElementById('leafletMap');
				if(__ajusteMap){ __ajusteMap.remove(); __ajusteMap=null; }
				__ajusteMap=L.map(mapDiv).setView(coords.length? [coords[0].lat, coords[0].lng] : [-14.235,-51.925], 5);
				var baseMapa=L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19});
				var googleHybrid=L.tileLayer('https://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}',{maxZoom:20, subdomains:['mt0','mt1','mt2','mt3']});
				baseMapa.addTo(__ajusteMap);
				L.control.layers({'Mapa':baseMapa,'Satélite (Híbrido)':googleHybrid},null,{position:'topright'}).addTo(__ajusteMap);
				var greenIcon=new L.Icon({iconUrl:'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png',shadowUrl:'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',iconSize:[25,41],iconAnchor:[12,41],popupAnchor:[1,-34],shadowSize:[41,41]});
				var redIcon=new L.Icon({iconUrl:'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',shadowUrl:'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',iconSize:[25,41],iconAnchor:[12,41],popupAnchor:[1,-34],shadowSize:[41,41]});
				var yellowIcon=new L.Icon({iconUrl:'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-yellow.png',shadowUrl:'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',iconSize:[25,41],iconAnchor:[12,41],popupAnchor:[1,-34],shadowSize:[41,41]});
				var cluster=L.markerClusterGroup({spiderfyOnEveryZoom:true, disableClusteringAtZoom:18});
				__ajusteCluster=cluster;
				__ajusteMarkers=[];
				cluster.addTo(__ajusteMap);
				var bounds=[];
				for(var k=0;k<coords.length;k++){
					var c=coords[k];
					var icon=yellowIcon;
					var tUpper=(c.tipo||'').toUpperCase();
					if(tUpper.indexOf('INICIO DE JORNADA')>=0) icon=greenIcon;
					else if(tUpper.indexOf('FIM DE JORNADA')>=0) icon=redIcon;
					var popupHtml='<div style=\"font-size:16px; line-height:1.5\">'
						+(c.tipo? ('<div style=\"font-weight:bold; font-size:17px\">'+c.tipo+'</div>') : '')
						+(c.legenda? ('<div>'+c.legenda+'</div>') : '')
						+(c.data? ('<div>'+c.data+(c.hora? ' '+c.hora : '')+'</div>') : '')
						+'</div>';
					var m=L.marker([c.lat,c.lng],{icon:icon}).bindPopup(popupHtml,{maxWidth:420});
					(function(idx){
						m.on('click', function(){ __ajusteHighlightLegendItem(idx); __ajusteShowPair(coords, idx); });
					})(k);
					__ajusteMarkers.push(m);
					cluster.addLayer(m);
					bounds.push([c.lat,c.lng]);
				}
				if(bounds.length>0){ __ajusteMap.fitBounds(cluster.getBounds()); }
				var Legend=L.Control.extend({
					onAdd:function(){
						var div=L.DomUtil.create('div','info legend');
						window.__ajusteLegendDiv=div;
						div.style.background='#fff'; div.style.padding='8px'; div.style.border='1px solid #ddd'; div.style.borderRadius='6px'; div.style.fontSize='15px';
						var html='<div style=\"font-weight:bold; margin-bottom:6px; font-size:18px\">Legenda</div>'
							+'<div style=\"display:flex; align-items:center; gap:6px; font-size:15px\"><img src=\"https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png\" style=\"width:16px;height:26px\"> <span>Inicio de Jornada</span></div>'
							+'<div style=\"display:flex; align-items:center; gap:6px; font-size:15px\"><img src=\"https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png\" style=\"width:16px;height:26px\"> <span>Fim de Jornada</span></div>'
							+'<div style=\"display:flex; align-items:center; gap:6px; font-size:15px\"><img src=\"https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-yellow.png\" style=\"width:16px;height:26px\"> <span>Eventos</span></div>'
							+'<div style=\"margin-top:6px; font-weight:bold; font-size:16px\">Eventos plotados</div>';
						html+= '<div style=\"max-height:160px; overflow:auto; margin-top:4px\">';
						for(var i=0;i<coords.length;i++){

							var c=coords[i];
							var tUpper=(c.tipo||'').toUpperCase();
							var iconSrc='https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-yellow.png';
							if(tUpper.indexOf('INICIO DE JORNADA')>=0) iconSrc='https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png';
							else if(tUpper.indexOf('FIM DE JORNADA')>=0) iconSrc='https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png';
							var dtTxt=(c.data? c.data : '');
							if(c.hora){ dtTxt = dtTxt? (dtTxt+' '+c.hora) : c.hora; }
							var line='<div data-idx=\"'+i+'\" id=\"ajuste-legend-item-'+i+'\" style=\"display:flex; align-items:center; gap:6px; font-size:15px; cursor:pointer; border-radius:4px; padding:4px\" onclick=\"__ajusteZoomToEvent('+i+')\"><img src=\"'+iconSrc+'\" style=\"width:12px;height:20px\"> <span>'+ (dtTxt? (dtTxt+' - ') : '') + (c.tipo||'') +'</span></div>';
							html+= line;
						}
						html+='</div>';
						div.innerHTML=html;
						return div;
					}
				});
				__ajusteMap.addControl(new Legend({position:'bottomright'}));
				window.__ajusteCoordsRef=coords;
				var btn=document.getElementById('closeMap');
				btn.onclick=function(){ modal.style.display='none'; if(__ajusteMap){ __ajusteMap.remove(); __ajusteMap=null; } __ajusteLegendDiv=null; __ajusteSelectedIdx=-1; };
			}
			</script>"
		;

		$textFields[] = texto("Matrícula", $motorista["enti_tx_matricula"], 2);
		$textFields[] = texto($motorista["enti_tx_ocupacao"], $motorista["enti_tx_nome"], 5);
		$textFields[] = texto("CPF", $motorista["enti_tx_cpf"], 3);

		$_POST["status"] = (!empty($_POST["status"]) && $_POST["status"] != "undefined"? $_POST["status"]: "ativo");

		$variableFields = [];
		$campoJust = [];

		$afastamento = mysqli_fetch_assoc(query(
			"SELECT abono.*, motivo.* FROM abono
				JOIN entidade ON abon_tx_matricula = enti_tx_matricula
				JOIN motivo ON abon_nb_motivo = moti_nb_id
				WHERE abon_tx_status = 'ativo' AND enti_tx_status = 'ativo' AND moti_tx_status = 'ativo'
					AND enti_nb_id = {$_POST["idMotorista"]}
					AND abon_tx_data = '{$_POST["data"]}'
					AND moti_tx_tipo = 'Afastamento'
			LIMIT 1;"
		));

		$ferias = mysqli_fetch_assoc(query(
			"SELECT * FROM ferias
				WHERE feri_tx_status = 'ativo'
					AND feri_nb_entidade = '{$motorista["enti_nb_id"]}'
					AND '".date("Y-m-d")."' BETWEEN feri_tx_dataInicio AND feri_tx_dataFim
				LIMIT 1;"
		));

		$iconeExcluir = "";
		$variableFields = [
			campo_data("Data*", "data", ($_POST["data"]?? ""), 2, "onfocusout='atualizar_form({$_POST["idMotorista"]}, this.value, \"{$_POST["status"]}\")'")
		];
		if(!empty($endosso)){
			$variableFields = array_merge($variableFields, [texto("Endosso", "Endossado por ".$endosso["endo_nb_userCadastro"]." em ".data($endosso["endo_tx_dataCadastro"], 1), 8)]);
		}elseif(!empty($afastamento)){
			$variableFields = array_merge($variableFields, [texto("Afastamento", "Afastado por motivo de {$afastamento["moti_tx_nome"]}", 8)]);
		}elseif(!empty($ferias)){
			$variableFields = array_merge($variableFields, [texto("Férias:", "Férias de ({$ferias["feri_tx_dataInicio"]} a {$ferias["feri_tx_dataFim"]})", 6)]);
		}else{
			$botoes[] = botao("Gravar", "cadastrarAjuste");

			//Precisa ser uma função para que o server-side chame e substitua os nomes dos campos pelos valores
			$iconeExcluir = "pont_nb_id";
			
			
			$variableFields = array_merge($variableFields, [
				campo_hora("Hora*", "hora", ($_POST["hora"]?? ""), 2, ""),
				combo_bd("Tipo de Registro*", "idMacro", ($_POST["idMacro"]?? ""), 4, "macroponto", "", "ORDER BY macr_nb_id"),
				combo_bd("Motivo*", "motivo", ($_POST["motivo"]?? ""), 4, "motivo", "", " AND moti_tx_tipo = 'Ajuste' ORDER BY moti_tx_nome")
			]);
			$campoJust[] = textarea("Justificativa", "justificativa", ($_POST["justificativa"]?? ""), 12, 'maxlength=680');
		}

		$botoes[] = $botao_imprimir;
		$botoes[] = criarBotaoVoltar("espelho_ponto.php");
		$botoes[] = $botaoConsLog; //BOTÃO CONSULTAR LOGISTICA
		$botoes[] = $botaoLocEventos;
		$botoes[] = status();

		
		echo abre_form("Dados do Ajuste de Ponto");
		echo linha_form($textFields);
		
		echo campo_hidden("idMotorista", $_POST["idMotorista"]);
		//Campos para retornar para a pesquisa do espelho de ponto ou após um registro de ponto{
			echo campo_hidden("busca_empresa", 		empty($_POST["busca_empresa"])? "": $_POST["busca_empresa"]);
			echo campo_hidden("busca_motorista", 	$_POST["idMotorista"]);
			echo campo_hidden("busca_data", 		$_POST["data"]);
			echo campo_hidden("busca_periodo[]",	$_POST["busca_periodo"][0]);
			echo campo_hidden("busca_periodo[]",	$_POST["busca_periodo"][1]);
		//}
		
		echo linha_form($variableFields);
		echo linha_form($campoJust);
		echo fecha_form($botoes);

		$iconeExcluir = criarSQLIconeTabela("pont_nb_id", "excluirPonto", "Excluir", "glyphicon glyphicon-remove", "Deseja inativar o registro?", "excluirPontoJS(',pont_nb_id,')");
		$checkboxBulk = "CONCAT('<input type=\"checkbox\" class=\"bulk-excluir\" data-id=\"', pont_nb_id, '\"/>')";


		$sql = pegarSqlDia(
			$motorista["enti_tx_matricula"], 
			new DateTime($_POST["data"]." 00:00:00"),
			[
				"pont_tx_data", 
				"endo_tx_status",
				"macr_tx_nome", 
				"moti_tx_nome", 
				"moti_tx_legenda", 
				"pont_tx_justificativa", 
				"(SELECT user_tx_nome FROM user WHERE user.user_nb_id = pont_nb_userCadastro LIMIT 1) as userCadastro", 
				"pont_nb_userCadastro",
				"pont_tx_dataCadastro", 
				"pont_tx_placa", 
				"pont_tx_latitude", 
				"pont_tx_longitude",
				"pont_tx_dataAtualiza",
				"IF(pont_tx_status = 'ativo' AND endo_tx_status IS NULL, {$checkboxBulk}, NULL) as bulkExcluir",
				"IF(pont_tx_status = 'ativo' AND endo_tx_status IS NULL, {$iconeExcluir}, NULL) as iconeExcluir"
			]
		);

		$gridFields = [
            "CÓD"										=> "pont_nb_id",
			"DATA"										=> "data(pont_tx_data,1)",
			"PLACA"									=> "pont_tx_placa",
			"TIPO"										=> "destacarJornadas(macr_tx_nome)",
			"MOTIVO"									=> "moti_tx_nome",
			"LEGENDA"									=> "moti_tx_legenda",
			"JUSTIFICATIVA"								=> "pont_tx_justificativa",
			"USUÁRIO CADASTRO"							=> "userCadastro(pont_nb_userCadastro)",
			"DATA CADASTRO"								=> "data(pont_tx_dataCadastro,1)",
			"DATA EXCLUSÃO"								=> "data(pont_tx_dataAtualiza,1)",
            "LOCALIZAÇÃO"								=> "map(pont_nb_id)",
            "Excluir Vários"								=> "bulkExcluir",
            "<spam class='glyphicon glyphicon-remove'></spam>"	=> "iconeExcluir"
        ];

		grid($sql, array_keys($gridFields), array_values($gridFields), "", "12", 1, "desc", -1);

		$logoEmpresa = mysqli_fetch_assoc(query(
            "SELECT empr_tx_logo FROM empresa
                    WHERE empr_tx_status = 'ativo'
                        AND empr_tx_Ehmatriz = 'sim'
                    LIMIT 1;"
        ))["empr_tx_logo"];

		echo
			"<div id='tituloRelatorio'>
                    <img style='width: 190px; height: 40px;' src='./imagens/logo_topo_cliente.png' alt='Logo Empresa Esquerda'>
                    <img style='width: 180px; height: 80px; margin-left: 850px;' src='./$logoEmpresa' alt='Logo Empresa Direita'>
            </div>
			<form name='form_ajuste_status' action='".$_SERVER["HTTP_ORIGIN"].$CONTEX["path"]."/ajuste_ponto.php' method='post'>
			</form>
			<div class='comentario-impressao'>
				<strong>Observações:</strong>
				<div class='linha-comentario'></div>
				<div class='linha-comentario'></div>
				<div class='linha-comentario'></div>
			</div>
			<style>
				.comentario-impressao {
					display: none;
				}
				@media print {
					@page {
						size: A4 landscape;
						margin: 1cm;
					}
					
					#tituloRelatorio {
						display: flex !important;
						align-items: center;       /* Alinha verticalmente */
						justify-content: space-between; /* Espaça os elementos nas extremidades */
						gap: 1em;                  /* Espaço entre elementos, se quiser */
					}

					#tituloRelatorio h1 {
						margin: 0;
						font-size: 1.5em;          /* Ajuste o tamanho conforme necessário */
						flex-grow: 1;
						text-align: center;
					}

					#tituloRelatorio img {
						display: block;
					}
					.comentario-impressao {
						display: block;
						margin-top: 30px;
						font-size: 14px;
						color: #000;
					}

					.linha-comentario {
						border-bottom: 1px solid #000;
						margin-bottom: 20px;
						height: 30px;
						width: 100%;
					}
					body {
						margin: 1cm;
						margin-right: 0cm; /* Ajuste o valor conforme necessário para afastar do lado direito */
						transform: scale(1.0);
						transform-origin: top left;
					}
					#tituloRelatorio{
						display: flex !important;
						position: absolute;
						top: 5px;
					}
						
					form > .row
					{
						display: none;
					}
					
					form > div:nth-child(1) {
						flex-wrap: nowrap !important;
					}

					.row {
						margin: 0px 0px 0px 0px !important;
					}
					
                    [id^=\"contex-grid-\"] > thead > tr > th:nth-child(12),
                    [id^=\"contex-grid-\"] > tbody > tr > td:nth-child(12),
                    [id^=\"contex-grid-\"] > thead > tr > th:nth-child(13),
                    [id^=\"contex-grid-\"] > tbody > tr > td:nth-child(13),
                    .scroll-to-top {
                        display: none !important;
                    }

					.portlet>.portlet-body p {
						margin-top: 0 !important;
					}
					div.page-content > div > div > div > div
					{
						padding-top: 9em;
					}
					.portlet.light {
						padding: 0px 10px !important; /* Reduzindo o padding */
						font-size: 10px !important; /* Reduzindo o tamanho da fonte */
						margin-bottom: 0px !important;
					}

					.row div {
						min-width: min-content !important;
					}

					form > div:nth-child(1){
						display: flex;
						flex-wrap: wrap;
					}
					.col-sm-2,
					.col-sm-5,
					.col-sm-3 {
						width: 40% !important;
						padding-left: 0px;
					}
				}
				#tituloRelatorio{
					display: none;
				}
			</style>"
		;

		carregarJS();

		rodape();
	}
