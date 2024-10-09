<link rel="stylesheet" href="../css/paineis.css">
<div id="printTitulo">
	<img style="width: 150px" src="<?=$logoEmpresa?>" alt="Logo Empresa Esquerda">
	<h3>Relatorio de Jornada Aberta</h3>
	<div class="right-logo">
		<img style="width: 150px" src="<?=$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]?>/imagens/logo_topo_cliente.png" alt="Logo Empresa Direita">
	</div>
</div>
<div class="col-md-12 col-sm-12" id="pdf2htmldiv">
	<div class="portlet light ">
		<div class="table-responsive">
				<div class='emissao' style="display: block !important;">
					<?=$dataEmissao."<br>"
					."<b>Período do relatório:</b> ".$periodoRelatorio["dataInicio"]." a ".$periodoRelatorio["dataFim"]?>
				</div>
			</div>
			<div class="portlet-body form">
				<table id="tabela-empresas" class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact">
					<thead>
					<?=$rowTitulos?>
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
	<b>Impressão Doc.:</b> <?= date("d/m/Y \T H:i:s")." (UTC-3)"?>
</div>
<link rel="stylesheet" href="../css/paineis.css">
<div id="printTitulo">
	<img style="width: 150px" src="<?= $logoEmpresa ?>" alt="Logo Empresa Esquerda">
	<h3>Relatorio <?= $titulo ?></h3>
	<div class="right-logo">
		<img style="width: 150px" src="<?= $_ENV["APP_PATH"] . $_ENV["CONTEX_PATH"] ?>/imagens/logo_topo_cliente.png" alt="Logo Empresa Direita">
	</div>
</div>
<div class="col-md-12 col-sm-12" id="pdf2htmldiv">
	<div class="portlet light ">
		<div class="table-responsive">
			<div class='emissao' style="display: block !important;">
				<?= $dataEmissao . "<br>"
					. "<b>Período do relatório:</b> " . $periodoRelatorio["dataInicio"] . " a " . $periodoRelatorio["dataFim"] ?>
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
	<b>Impressão Doc.:</b> <?= date("d/m/Y \T H:i:s") . " (UTC-3)" ?>
</div>