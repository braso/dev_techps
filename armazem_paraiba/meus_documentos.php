<?php
	/* ============================================================
	   "Meus documentos" — atalho no menu do usuário (canto superior).
	   Mostra apenas os documentos anexados ao próprio funcionário e
	   marcados como visíveis. Só leitura: ver e baixar, sem anexar
	   nem excluir.
	   O arquivo é entregue por este próprio arquivo (ação "baixar"),
	   que confere o dono antes de abrir — o caminho nunca vem do
	   navegador.
	   ============================================================ */

	include "conecta.php";

	function meusDocumentosEntidade(): int {
		return intval($_SESSION["user_nb_entidade"] ?? 0);
	}

	/** Documentos do funcionário logado que ele pode ver. */
	function meusDocumentosLista(): array {
		$entidade = meusDocumentosEntidade();
		if($entidade <= 0){
			return [];
		}

		$rs = query(
			"SELECT d.docu_nb_id, d.docu_tx_nome, d.docu_tx_descricao, d.docu_tx_caminho,
			        d.docu_tx_dataCadastro, d.docu_tx_dataVencimento, d.docu_tx_assinado,
			        t.tipo_tx_nome, g.grup_tx_nome
			   FROM documento_funcionario d
			   LEFT JOIN tipos_documentos t ON t.tipo_nb_id = d.docu_tx_tipo
			   LEFT JOIN grupos_documentos g ON g.grup_nb_id = t.tipo_nb_grupo
			  WHERE d.docu_nb_entidade = ?
			    AND LOWER(TRIM(COALESCE(d.docu_tx_visivel, 'nao'))) = 'sim'
			  ORDER BY d.docu_tx_dataCadastro DESC, d.docu_nb_id DESC",
			"i",
			[$entidade]
		);

		$documentos = [];
		while($rs && ($linha = mysqli_fetch_assoc($rs))){
			$documentos[] = $linha;
		}
		return $documentos;
	}

	/** Só devolve o documento se ele for do funcionário logado e estiver visível. */
	function meusDocumentosBuscar(int $idDocumento): ?array {
		$entidade = meusDocumentosEntidade();
		if($entidade <= 0 || $idDocumento <= 0){
			return null;
		}

		$rs = query(
			"SELECT docu_nb_id, docu_tx_nome, docu_tx_caminho
			   FROM documento_funcionario
			  WHERE docu_nb_id = ?
			    AND docu_nb_entidade = ?
			    AND LOWER(TRIM(COALESCE(docu_tx_visivel, 'nao'))) = 'sim'
			  LIMIT 1",
			"ii",
			[$idDocumento, $entidade]
		);
		$documento = $rs ? mysqli_fetch_assoc($rs) : null;
		return $documento ?: null;
	}

	function meusDocumentosData(?string $valor, bool $comHora = false): string {
		$valor = trim(strval($valor ?? ""));
		if($valor === "" || strpos($valor, "0000-00-00") === 0){
			return "—";
		}
		$tempo = strtotime($valor);
		if($tempo === false){
			return "—";
		}
		return date($comHora ? "d/m/Y H:i" : "d/m/Y", $tempo);
	}

	function meusDocumentosTipoMime(string $caminho): string {
		$extensao = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
		$mapa = [
			"pdf"  => "application/pdf",
			"jpg"  => "image/jpeg",
			"jpeg" => "image/jpeg",
			"png"  => "image/png",
			"gif"  => "image/gif",
			"webp" => "image/webp",
			"txt"  => "text/plain"
		];
		return $mapa[$extensao] ?? "application/octet-stream";
	}

	/** Entrega o arquivo: ?acao=baixar&id=12 (&modo=ver abre no navegador). */
	function baixar(){
		$documento = meusDocumentosBuscar(intval($_REQUEST["id"] ?? 0));
		if(!$documento){
			http_response_code(404);
			echo "Documento não encontrado.";
			return;
		}

		$caminhoRelativo = strval($documento["docu_tx_caminho"] ?? "");
		$caminhoCompleto = realpath(__DIR__."/".$caminhoRelativo);
		$raizPermitida   = realpath(__DIR__);

		// O arquivo tem de estar dentro da pasta do domínio.
		if($caminhoCompleto === false || $raizPermitida === false || strpos($caminhoCompleto, $raizPermitida) !== 0 || !is_file($caminhoCompleto)){
			http_response_code(404);
			echo "Arquivo não encontrado no servidor.";
			return;
		}

		$nomeArquivo = basename($caminhoRelativo);
		$disposicao = (strval($_REQUEST["modo"] ?? "") === "ver") ? "inline" : "attachment";

		header("Content-Type: ".meusDocumentosTipoMime($caminhoRelativo));
		header("Content-Length: ".filesize($caminhoCompleto));
		header("Content-Disposition: {$disposicao}; filename=\"".$nomeArquivo."\"");
		header("X-Content-Type-Options: nosniff");
		readfile($caminhoCompleto);
	}

	function index(){
		global $CONTEX;

		cabecalho("Meus documentos");

		$entidade = meusDocumentosEntidade();
		$documentos = meusDocumentosLista();

		echo "<style>
			.md-tabela td, .md-tabela th{ vertical-align: middle !important; font-size: 13px; }
			.md-tabela th{ font-size: 12px; text-transform: uppercase; color: #6b7684; }
			.md-acoes{ white-space: nowrap; }
			.md-vazio{ padding: 40px 16px; text-align: center; color: #98a1ac; }
			@media(max-width:768px){
				.md-esconde-mobile{ display: none; }
				.md-tabela td, .md-tabela th{ font-size: 12px; padding: 6px 4px !important; }
			}
		</style>";

		echo "<div class='portlet light bordered'><div class='portlet-body'>";

		if($entidade <= 0){
			echo "<div class='md-vazio'><i class='fa fa-user-times' style='font-size:34px; display:block; margin-bottom:10px;'></i>"
				."Seu usuário não está vinculado a um cadastro de funcionário, então não há documentos para mostrar.<br>"
				."Fale com o RH para fazer esse vínculo.</div>";
		}elseif(empty($documentos)){
			echo "<div class='md-vazio'><i class='fa fa-folder-open-o' style='font-size:34px; display:block; margin-bottom:10px;'></i>"
				."Você ainda não tem documentos disponíveis.</div>";
		}else{
			echo "<table class='table table-striped table-hover md-tabela'>"
				."<thead><tr>"
				."<th>Documento</th>"
				."<th class='md-esconde-mobile'>Tipo</th>"
				."<th>Anexado em</th>"
				."<th class='md-esconde-mobile'>Vencimento</th>"
				."<th class='md-esconde-mobile'>Assinado</th>"
				."<th>Ações</th>"
				."</tr></thead><tbody>";

			foreach($documentos as $documento){
				$id = intval($documento["docu_nb_id"]);
				$nome = htmlspecialchars(strval($documento["docu_tx_nome"] ?? ""));
				$descricao = trim(strval($documento["docu_tx_descricao"] ?? ""));
				$tipo = trim(strval($documento["tipo_tx_nome"] ?? ""));
				$grupo = trim(strval($documento["grup_tx_nome"] ?? ""));
				$tipoTexto = htmlspecialchars($tipo !== "" ? ($grupo !== "" ? $grupo." / ".$tipo : $tipo) : "—");
				$assinado = strtolower(trim(strval($documento["docu_tx_assinado"] ?? ""))) === "sim"
					? "<span class='label label-success'>Sim</span>"
					: "<span class='label label-default'>Não</span>";

				echo "<tr>"
					."<td><strong>{$nome}</strong>"
					.($descricao !== "" ? "<br><small style='color:#8a94a0;'>".htmlspecialchars($descricao)."</small>" : "")
					."</td>"
					."<td class='md-esconde-mobile'>{$tipoTexto}</td>"
					."<td>".meusDocumentosData($documento["docu_tx_dataCadastro"] ?? "", true)."</td>"
					."<td class='md-esconde-mobile'>".meusDocumentosData($documento["docu_tx_dataVencimento"] ?? "")."</td>"
					."<td class='md-esconde-mobile'>{$assinado}</td>"
					."<td class='md-acoes'>"
					."<a class='btn btn-xs btn-default' target='_blank' rel='noopener' href='{$CONTEX["path"]}/meus_documentos.php?acao=baixar&modo=ver&id={$id}' title='Abrir'><i class='fa fa-eye'></i> Ver</a> "
					."<a class='btn btn-xs btn-default' href='{$CONTEX["path"]}/meus_documentos.php?acao=baixar&id={$id}' title='Baixar'><i class='fa fa-download'></i></a>"
					."</td>"
					."</tr>";
			}

			echo "</tbody></table>";
			echo "<small style='color:#98a1ac;'>Aparecem aqui só os documentos que a empresa liberou para você.</small>";
		}

		echo "</div></div>";

		rodape();
	}
