<link rel="stylesheet" href="../css/paineis.css">
<div id="printTitulo">
	<img style='width: 150px' src="<?= $aEmpresa[0]['empr_tx_logo']?>" alt="Logo Empresa Esquerda">
	<h3>Relatorio Geral de saldo</h3>
	<div class="right-logo">
		<img style='width: 150px' src="<?= $CONTEX['path']?>/imagens/logo_topo_cliente.png" alt="Logo Empresa Direita">
	</div>
</div>
<div class="col-md-12 col-sm-12" id="pdf2htmldiv">
	<div class="portlet light ">
		<div class="table-responsive">
			<div>
				<table class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact' id='tabela1'>
                    <thead>
                        <tr>
                            <th colspan='1'></th>
                            <th colspan='1'>QUANT</th>
                            <th colspan='1'>%</th>
                        </tr>
                    </thead>
                    <tbody>
						<tr>
							<td>NÃO ENDOSSADO</td>
							<td></td>
							<td class='porcentagemNaEndo'></td>
						</tr>
						<tr>
							<td>ENDOSSO PARCIAL</td>
							<td></td>
							<td class='porcentagemEndoPc'></td>
						</tr>
						<tr>
							<td>ENDOSSADO</td>
							<td></td>
							<td class='porcentagemEndo'></td>
						</tr>
                    </tbody>
                </table>
                <table class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact' id='tabela2'>
                    <thead>
                        <tr>
                            <th colspan='1'>SALDO FINAL</th>
                            <th colspan='1'>QUANT</th>
                            <th colspan='1'>%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class='porcentagemMeta'>
                            <td>META</td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr class='porcentagemPosi'>
                            <td>POSITIVO</td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr class='porcentagemNega'>
                            <td>NEGATIVO</td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
				<div class='emissao'>
					<?=(!empty($dataEmissao)? "<b>Atualizado em:</b> ".$dataEmissao."<br>": "")
					."<b>Período do relatório:</b> ".$periodoRelatorio["dataInicio"]." a ".$periodoRelatorio["dataFim"]?>
				</div>
			</div>
			<div class="portlet-body form">
				<table id="tabela-empresas" class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact">
					<thead>
						<tr class="totais">
							<th colspan="1">Período: De <?=$periodoRelatorio["dataInicio"].' até '.$periodoRelatorio["dataFim"]?></th>
							<th></th>
							<?php
							if (!empty($_POST['empresa'])){
								echo "<th colspan='1'>".$totais['empresaNome']."</th>"
									."<th colspan='1'>".$totais['jornadaPrevista']."</th>"
									."<th colspan='1'>".$totais['jornadaEfetiva']."</th>"
									."<th colspan='1'>".$totais['HESemanal']."</th>"
									."<th colspan='1'>".$totais['HESabado']."</th>"
									."<th colspan='1'>".$totais['adicionalNoturno']."</th>"
									."<th colspan='1'>".$totais['esperaIndenizada']."</th>"
									."<th colspan='1'>".$totais['saldoAnterior']."</th>"
									."<th colspan='1'>".$totais['saldoPeriodo']."</th>"
									."<th colspan='1'>".$totais['saldoFinal']."</th>";
							} else {
								echo "<th colspan='1'>".$totais["jornadaPrevista"]."</th>"
									."<th colspan='1'>".$totais["jornadaEfetiva"]."</th>"
									."<th colspan='1'> ".(($totais['HESemanal'] == '00:00')? '': $totais['HESemanal'])."</th>"
									."<th colspan='1'> ".(($totais['HESabado'] == '00:00')? '': $totais['HESabado'])."</th>"
									."<th colspan='1'> ".(($totais['adicionalNoturno'] == '00:00')? '': $totais['adicionalNoturno'])."</th>"
									."<th colspan='1'> ".(($totais['esperaIndenizada'] == '00:00')? '': $totais['esperaIndenizada'])."</th>"
									."<th colspan='1'> ".(($totais['saldoAnterior'] == '00:00')? '': $totais['saldoAnterior'])."</th>"
									."<th colspan='1'> ".(($totais['saldoPeriodo'] == '00:00')? '': $totais['saldoPeriodo'])."</th>"
									."<th colspan='1'> ".(($totais['saldoFinal'] == '00:00')? '': $totais['saldoFinal'])."</th>";
							}
							?>
						</tr>
						<tr id='titulos' class="titulos">
							<?=((!empty($_POST['empresa']))?
								 	"<th class='matricula'></th>"
									."<th class='nome'></th>"
									."<th class='status'></th>"
									."<th class='jornadaPrevista'></th>"
									."<th class='jornadaEfetiva'></th>"
									."<th class='HESemanal'></th>"
									."<th class='HESabado'></th>"
									."<th class='adicionalNoturno'></th>"
									."<th class='esperaIndenizada'></th>"
									."<th class='saldoAnterior'></th>"
									."<th class='saldoPeriodo'></th>"
									."<th class='saldoFinal'></th>"

									:"<th data-column='nome' data-order='asc'>Todos os CNPJ</th>"
									."<th data-column='qtdMotoristas' data-order='asc'>Quant. Motoristas</th>"
									."<th data-column='jornadaPrevista' data-order='asc'>Jornada Prevista</th>"
									."<th data-column='JornadaEfetiva' data-order='asc'>Jornada Efetiva</th>"
									."<th data-column='HESemanal' data-order='asc'>HE 50%</th>"
									."<th data-column='HESabado' data-order='asc'>HE 100%</th>"
									."<th data-column='adicionalNoturno' data-order='asc'>Adicional Noturno</th>"
									."<th data-column='esperaIndenizada' data-order='asc'>ESPERA INDENIZADA</th>"
									."<th data-column='saldoAnterior' data-order='asc'>Saldo Anterior</th>"
									."<th data-column='saldoPeriodo' data-order='asc'>Saldo Periodo</th>"
									."<th data-column='saldoFinal' data-order='asc'>Saldo Final</th>"
							)?>
						</tr>
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