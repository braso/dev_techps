<?php
/*
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
*/

	include "funcoes_ponto.php";

	function cadastra_abono(){
		// Conferir se os campos obrigatórios estão preenchidos{

			$motivo_anexo = mysqli_fetch_all(query(
				"SELECT moti_tx_anexo, moti_tx_nome FROM motivo WHERE moti_nb_id = '{$_POST["motivo"]}';"
			), MYSQLI_ASSOC);

			if(!empty($motivo_anexo) && $motivo_anexo[0]["moti_tx_anexo"] == "sim"){
				if(empty($_FILES["file"]) || $_FILES["file"]["error"] != 0){
					set_status("ERRO: O motivo selecionado exige o anexo de um documento.");
					layout_abono();
					exit;
				}
			}

			// Tipos de arquivo permitidos
			$formatos = [
				"image/jpeg" => "jpg",
				"image/png" => "png",
				"application/msword" => "doc",
				"application/vnd.openxmlformats-officedocument.wordprocessingml.document" => "docx",
				"application/pdf" => "pdf"
			];

			$arquivoEnviado = !empty($_FILES["file"]) && !empty($_FILES["file"]["tmp_name"]);
			if ($arquivoEnviado) {
				$tipo = mime_content_type($_FILES["file"]["tmp_name"]);
				if (!array_key_exists($tipo, $formatos)) {
					set_status("ERRO: Tipo de arquivo não permitido.");
					layout_abono();
					exit;
				}
			}

			$camposObrig = [
				"motorista" => "Funcionário",
				"periodo_abono" => "Data",
				"abono" => "Abono",
				"motivo" => "Motivo"
			];

			$errorMsg = conferirCamposObrig($camposObrig, $_POST);

			if(!empty($errorMsg)){
				set_status("ERRO: ".$errorMsg);
				layout_abono();
				exit;
			}
		// }

		$_POST["busca_motorista"] = $_POST["motorista"];

		$aData[0] = $_POST["periodo_abono"][0];
		$aData[1] = $_POST["periodo_abono"][1];
		//Conferir se há um período entrelaçado com essa data{
			$endosso = mysqli_fetch_assoc(
				query(
					"SELECT endo_tx_de, endo_tx_ate FROM endosso
						WHERE endo_tx_status = 'ativo'
							AND endo_nb_entidade = ".$_POST["motorista"]."
							AND (
								'".$aData[0]."' BETWEEN endo_tx_de AND endo_tx_ate
								OR '".$aData[1]."' BETWEEN endo_tx_de AND endo_tx_ate
							)
						LIMIT 1;"
				)
			);

			if(!empty($endosso)){
				$endosso["endo_tx_de"] = explode("-", $endosso["endo_tx_de"]);
				$endosso["endo_tx_de"] = $endosso["endo_tx_de"][2]."/".$endosso["endo_tx_de"][1]."/".$endosso["endo_tx_de"][0];

				$endosso["endo_tx_ate"] = explode("-", $endosso["endo_tx_ate"]);
				$endosso["endo_tx_ate"] = $endosso["endo_tx_ate"][2]."/".$endosso["endo_tx_ate"][1]."/".$endosso["endo_tx_ate"][0];

				$_POST["errorFields"][] = "periodo_abono";
				set_status("ERRO: Possui um endosso de ".$endosso["endo_tx_de"]." até ".$endosso["endo_tx_ate"].".");
				layout_abono();
				exit;
			}
		//}

		$begin = new DateTime($aData[0]);
		$end = new DateTime($aData[1]);

		$motorista = mysqli_fetch_assoc(query(
			"SELECT * FROM entidade
			 LEFT JOIN empresa ON entidade.enti_nb_empresa = empresa.empr_nb_id
			 LEFT JOIN cidade  ON empresa.empr_nb_cidade = cidade.cida_nb_id
			 LEFT JOIN parametro ON enti_nb_parametro = para_nb_id
			 WHERE enti_tx_status = 'ativo'
				 AND enti_nb_id = '{$_POST["motorista"]}'
			 LIMIT 1;"
		));

		for ($i = $begin; $i <= $end; $i->modify("+1 day")){
			$sqlRemover = query(
				"SELECT * FROM abono 
					WHERE abon_tx_status = 'ativo'
						AND abon_tx_matricula = '{$motorista["enti_tx_matricula"]}'
						AND abon_tx_data = '".$i->format("Y-m-d")."';"
			);

			while ($aRemover = mysqli_fetch_array($sqlRemover, MYSQLI_BOTH)) {
				remover("abono", $aRemover["abon_nb_id"]);
			}
			
			$aDetalhado = diaDetalhePonto($motorista, $i->format("Y-m-d"));
			$aDetalhado["diffSaldo"] = str_replace(["<b>", "</b>"], ["", ""], $aDetalhado["diffSaldo"]);
			$abono = calcularAbono($aDetalhado["diffSaldo"], $_POST["abono"]);

			$novoAbono = [
				"abon_tx_data" 			=> $i->format("Y-m-d"),
				"abon_tx_matricula" 	=> $motorista["enti_tx_matricula"],
				"abon_tx_abono" 		=> $abono,
				"abon_nb_motivo" 		=> $_POST["motivo"],
				"abon_tx_descricao" 	=> $_POST["descricao"],
				"abon_nb_userCadastro" 	=> $_SESSION["user_nb_id"],
				"abon_tx_dataCadastro" 	=> date("Y-m-d H:i:s"),
				"abon_tx_status" 		=> "ativo"
			];
			inserir("abono", array_keys($novoAbono), array_values($novoAbono));
		}
		$begin2 = new DateTime($aData[0]);

		if ($arquivoEnviado) {
			$novoArquivo = [
				"docu_nb_entidade" => (int) $motorista["enti_nb_id"],
				"docu_tx_nome" => $motivo_anexo[0]["moti_tx_nome"]."_".$begin2->format('d/m/Y') ."_".$end->format('d/m/Y'),
				"docu_tx_descricao" => $_POST["descricao"],
				"docu_tx_dataCadastro" => date("Y-m-d H:i:s"),
				"docu_tx_usuarioCadastro" => (int) $_SESSION["user_nb_id"],
				"docu_tx_assinado" => "nao",
				"docu_tx_visivel" => 'sim'
			];

			$nomeOriginal = basename($_FILES["file"]["name"]);
			$nomeSeguro = preg_replace('/[^\p{L}\p{N}\s\.\-\_]/u', '_', $nomeOriginal);

			$pasta_funcionario = "arquivos/Funcionarios/" . $novoArquivo["docu_nb_entidade"] . "/";
			if (!is_dir($pasta_funcionario)) {
				mkdir($pasta_funcionario, 0777, true);
			}

			$novoArquivo["docu_tx_caminho"] = $pasta_funcionario . $nomeSeguro;

			if (file_exists($novoArquivo["docu_tx_caminho"])) {
				$info = pathinfo($nomeSeguro);
				$base = $info["filename"];
				$ext = isset($info["extension"]) ? '.' . $info["extension"] : '';
				$nomeSeguro = $base . '_' . time() . $ext;
				$novoArquivo["docu_tx_caminho"] = $pasta_funcionario . $nomeSeguro;
			}

			if (move_uploaded_file($_FILES["file"]["tmp_name"], $novoArquivo["docu_tx_caminho"])) {
				inserir("documento_funcionario", array_keys($novoArquivo), array_values($novoArquivo));
			} else {
				set_status("Falha ao mover o arquivo para o diretório de destino.");
			}
		}


		set_status("Registro inserido com sucesso.");
		index();
		exit;
	}

	function layout_abono(){
    	unset($_POST["acao"]);
		
		index();
		exit;
	}
	
	function index(){
			//ARQUIVO QUE VALIDA A PERMISSAO VIA PERFIL DE USUARIO VINCULADO
		include "check_permission.php";
		// APATH QUE O USER ESTA TENTANDO ACESSAR PARA VERIFICAR NO PERFIL SE TEM ACESSO2
		verificaPermissao('/cadastro_abono.php');
		cabecalho("Cadastro Abono");

		$motivo_anexo = mysqli_fetch_all(query(
				"SELECT moti_nb_id, moti_tx_anexo FROM motivo"
			), MYSQLI_ASSOC);

		$campo_upload = "<style>
				.dropzone-upload {
					position: relative;
					/* Cores mais agradáveis */
					border: 2px dashed #3c8dbc; /* Borda azul clara */
					background-color: #f9f9f9; /* Fundo levemente cinza */
					border-radius: 8px; /* Cantos mais arredondados */
					padding: 30px 20px; /* Mais padding para visual melhor */
					text-align: center;
					cursor: pointer;
					transition: all 0.2s ease-in-out;
					min-height: 120px; /* Altura mínima para não encolher */
				}

				.dropzone-upload:hover {
					background-color: #f0f8ff; /* Fundo mais claro ao passar o mouse */
					border-color: #31708f; /* Borda um pouco mais escura no hover */
				}

				.dropzone-upload.dragover {
					background-color: #e0f0ff; /* Cor clara para indicar que o arquivo pode ser solto */
					border-color: #2a6496;
					box-shadow: 0 0 10px rgba(60, 141, 188, 0.4); /* Sombra suave */
				}

				.dropzone-upload input {
					position: absolute;
					top: -9999px;
					left: -9999px;
					opacity: 0;
				}
				
				.dropzone-icon {
					display: block;
					font-size: 2.5em; /* Tamanho grande para o ícone */
					color: #3c8dbc; /* Cor primária */
					line-height: 1; /* Alinhamento vertical */
					margin-bottom: 5px;
				}

				.dropzone-text {
					margin: 0;
					font-size: 1.1em;
					color: #333;
					font-weight: 500;
				}

				.dropzone-info {
					margin-top: 5px;
					font-size: 0.85em;
					color: #777;
				}
			</style>
			<div class='col-sm-4 margin-bottom-5 campo-fit-content' id='dropzone-container' style='display: none;'>
				<label for='fileInput'>Arquivo:</label>
				<div class='dropzone-upload' id='dropzone'>
					<span class='dropzone-icon'>&#x25B2;</span> 
					<p class='dropzone-text'>Arraste e solte ou **clique para procurar**</p>
					<p class='dropzone-info'>Nenhum arquivo selecionado.</p>
					<input type='file' name='file' id='fileInput'>
				</div>
			</div>
			<script>
				const todosMotivos = ".json_encode($motivo_anexo).";
				// --- VARIÁVEIS GLOBAIS ---
				const motivoSelect = document.getElementById('motivo');
				// A dropzone está dentro deste container que controlamos a visibilidade
				const dropzoneContainer = document.getElementById('dropzone-container'); 

				// --- FUNÇÕES DE VISIBILIDADE DO ANEXO ---

				/**
				 * @returns {boolean} True se o motivo selecionado exige moti_tx_anexo = 'sim'.
				 */
				function exigeAnexo(id) {
					if (!Array.isArray(todosMotivos)) {
						console.error('ERRO: todosMotivos não é um array válido. Verifique a injeção PHP.');
						return false;
					}
					
					const motivoEncontrado = todosMotivos.find(motivo => 
						String(motivo.moti_nb_id) === String(id)
					);
					
					if (!motivoEncontrado) {
						// Se o motivo não for encontrado (ex: valor padrão '32' que não está no array), assume-se 'nao'.
						return false;
					}

					// Retorna TRUE se for 'sim' (case insensitive).
					return String(motivoEncontrado.moti_tx_anexo).toLowerCase() === 'sim';
				}

				/**
				 * Função que define se a dropzone deve ser visível ou oculta.
				 * Esta função é o core da mudança imediata.
				 */
				function atualizarDropzoneVisibilidade() {
					console.log('>>> Executando atualizarDropzoneVisibilidade()');

					if (!motivoSelect) {
						console.warn('motivoSelect está NULL ou undefined!');
						return;
					}

					if (!dropzoneContainer) {
						console.warn('dropzoneContainer está NULL ou undefined!');
						return;
					}

					const selectedId = motivoSelect.value;
					console.log('ID selecionado:', selectedId);

					const anexoNecessario = exigeAnexo(selectedId);
					console.log('Resultado de exigeAnexo(', selectedId, '):', anexoNecessario);

					if (anexoNecessario) {
						console.log('Exibindo dropzone (anexo obrigatório).');
						dropzoneContainer.style.display = 'block';
					} else {
						console.log('Ocultando dropzone (anexo NÃO obrigatório).');
						dropzoneContainer.style.display = 'none';
					}

					console.log('<<< Finalizou atualizarDropzoneVisibilidade()');
				}
				
				// --- INICIALIZAÇÃO E LISTENERS ---

				if (motivoSelect && dropzoneContainer) {
					// 1. Visibilidade Imediata (ao carregar ou após o DOM estar pronto)
					// O elemento já está 'display: none' via HTML, mas esta função revalida para o valor inicial.
					atualizarDropzoneVisibilidade();
					
					// 2. Mudança Imediata (ao mudar o select)
					motivoSelect.addEventListener('change', atualizarDropzoneVisibilidade);
				}


				// --- LÓGICA DE DRAG AND DROP (Mantida, isolada aqui) ---

				const dropzone = document.getElementById('dropzone');
				const fileInput = document.getElementById('fileInput');

				if (dropzone && fileInput) {
					// O seu código completo de Drag and Drop/Clique/Change do input vai aqui. 
					// Não foi alterado, apenas movido para esta parte do script.
					
					const message = dropzone.querySelector('.dropzone-text'); 
					const infoMessage = dropzone.querySelector('.dropzone-info'); 

					dropzone.addEventListener('click', () => { fileInput.click(); });

					fileInput.addEventListener('change', () => {
						// Lógica de atualização de mensagem
						if (fileInput.files.length > 0) {
							message.textContent = fileInput.files[0].name;
							if (infoMessage) { infoMessage.textContent = 'Clique para trocar ou arraste outro arquivo.'; }
						} else {
							message.textContent = 'Arraste e solte ou clique para procurar';
							if (infoMessage) { infoMessage.textContent = 'Nenhum arquivo selecionado.'; }
						}
					});

					dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
					dropzone.addEventListener('dragleave', () => { dropzone.classList.remove('dragover'); });
					dropzone.addEventListener('drop', (e) => {
						e.preventDefault();
						dropzone.classList.remove('dragover');
						if (e.dataTransfer.files.length > 0) {
							fileInput.files = e.dataTransfer.files;
							fileInput.dispatchEvent(new Event('change'));
						}
					});
				}
			</script>";

		$campos[0][] = combo_net(
			"Funcionário*",
			"motorista",
			(!empty($_POST["motorista"])? $_POST["motorista"]: $_POST["busca_motorista"]?? ""),
			4,
			"entidade",
			"",
			" AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')",
			"enti_tx_matricula"
		);
		$campos[0][] = campo("Data(s)*", "periodo_abono", ($_POST["periodo_abono"]?? $_POST["busca_periodo"]?? ""),3, "MASCARA_PERIODO");
		$campos[0][] = campo("Tempo a abonar (p/dia)*", "abono", ($_POST["abono"]?? ""), 3, "MASCARA_HORAS");
		$campos[1][] = combo_bd("Motivo*","motivo", ($_POST["motivo"]?? ""),4,"motivo",""," AND moti_tx_tipo IN ('Abono', 'Afastamento') ORDER BY moti_tx_nome;");
		$campos[1][] = textarea("Justificativa","descricao", ($_POST["descricao"]?? ""), 12);
		$campos[1][] = $campo_upload;

		echo abre_form();
		echo linha_form($campos[0]);
		echo linha_form($campos[1]);


		//BOTOES{
    		$b[] = botao("Gravar", "cadastra_abono", "", "", "", "", "btn btn-success");
			unset($_POST["errorFields"]);
			$_POST["busca_periodo"] = !empty($_POST["busca_periodo"])? implode(" - ", $_POST["busca_periodo"]): null;
			$b[] = criarBotaoVoltar("espelho_ponto.php");
		//}

		echo fecha_form($b);

		
		rodape();
	}
