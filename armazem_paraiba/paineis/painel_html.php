<link rel="stylesheet" href="../css/paineis.css">
<div id="printTitulo">
	<img style="width: 150px" src="<?= $logoEmpresa ?>" alt="Logo Empresa Esquerda">
	<h2>Relatorio <?= $titulo ?></h2>
	<div class="right-logo">
		<img style="width: 150px" src="<?= $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"] ?>/imagens/logo_topo_cliente.png" alt="Logo Empresa Direita">
	</div>
</div>
<div class="col-md-12 col-sm-12" id="pdf2htmldiv">
	<div class="portlet light ">
		<div class="table-responsive">
			<div>
				<table class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact' id='tabela1'>
					<thead>
						<tr>
							<th style='cursor: pointer;' colspan='1'>STATUS</th>
							<th colspan='1'>TOTAL</th>
							<th colspan='1'>%</th>
						</tr>
					</thead>
					<tbody>
						<tr class='porcentagemNaEndo'>
							<td>Não Endossado</td>
							<td></td>
							<td></td>
						</tr>
						<tr class='porcentagemEndoPc'>
							<td>Endo. Parcialmente</td>
							<td></td>
							<td></td>
						</tr>
						<tr class='porcentagemEndo'>
							<td>Endossado</td>
							<td></td>
							<td></td>
						</tr>
					</tbody>
				</table>
				<table class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact' id='tabela2'>
					<thead>
						<tr>
							<th style='cursor: pointer;' colspan='1'>SALDO FINAL</th>
							<th colspan='1'>TOTAL</th>
							<th colspan='1'>%</th>
						</tr>
					</thead>
					<tbody>
						<tr class='porcentagemMeta'>
							<td>Meta</td>
							<td></td>
							<td></td>
						</tr>
						<tr class='porcentagemPosi'>
							<td>Positivo</td>
							<td></td>
							<td></td>
						</tr>
						<tr class='porcentagemNega'>
							<td>Negativo</td>
							<td></td>
							<td></td>
						</tr>
					</tbody>
				</table>
				<div class='emissao'>
					<?= $dataEmissao."<br>"
						. "<b>Período do relatório:</b> ".$periodoRelatorio["dataInicio"]." a ".$periodoRelatorio["dataFim"] ?>
				</div>
			</div>
			<div class="portlet-body form">
				<table id="tabela-empresas" class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact">
					<thead>
						<?= $rowTotais ?>
						<?= $rowTitulos ?>
					</thead>
					<tbody>
						<!-- Conteúdo do json empresas será inserido aqui -->
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
<div id="impressao">
	<b>Impressão Doc.:</b> <?= date("d/m/Y \T H:i:s")." (UTC-3)" ?>
</div>