<?php
include "conecta.php";

function index(){
    cabecalho("Auditoria de Ponto");

    $dataInicio = date("Y-m-01");
    $dataFim = date("Y-m-d");
    
    if(!empty($_SESSION['user_nb_empresa'])){
        $empresaId = $_SESSION['user_nb_empresa'];
    } else {
        $empresaId = '';
    }

    // Busca empresas ativas
    $sqlEmpresa = "SELECT empr_nb_id, empr_tx_nome FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_tx_nome";
    $queryEmpresa = query($sqlEmpresa);
    $empresas = [];
    while($row = mysqli_fetch_assoc($queryEmpresa)){
        $empresas[] = $row;
    }

    ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Exportar Arquivo Fonte de Dados (AFD)</h3>
                    </div>
                    <div class="panel-body">
                        <form action="export_afd.php" method="POST" target="_blank" class="form-horizontal" enctype="multipart/form-data">
                            
                            <div class="form-group">
                                <label for="empresa" class="col-sm-2 control-label">Empresa</label>
                                <div class="col-sm-4">
                                    <select class="form-control" id="empresa" name="empresa" required>
                                        <?php if(empty($empresaId)): ?>
                                            <option value="">Selecione uma empresa</option>
                                        <?php endif; ?>
                                        <?php foreach($empresas as $empresa): ?>
                                            <option value="<?=$empresa['empr_nb_id']?>" <?=($empresaId == $empresa['empr_nb_id']) ? 'selected' : ''?>>
                                                <?=$empresa['empr_tx_nome']?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="data_inicio" class="col-sm-2 control-label">Data Início</label>
                                <div class="col-sm-2">
                                    <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?=$dataInicio?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="data_fim" class="col-sm-2 control-label">Data Fim</label>
                                <div class="col-sm-2">
                                    <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?=$dataFim?>" required>
                                </div>
                            </div>

                            <hr>

                            <div class="text-center">
                                <h4>Assinatura Digital (Opcional)</h4>
                                <p class="text-muted">Selecione seu certificado A1 (arquivo .pfx) e informe a senha para gerar o arquivo assinado (.p7s).</p>
                            </div>
                            <br>

                            <div class="row">
                                <div class="col-sm-8 col-sm-offset-2">
                                    <div class="form-group">
                                        <label for="certificado_pfx" class="col-sm-4 control-label">Certificado ICP (.pfx)</label>
                                        <div class="col-sm-6">
                                            <input type="file" class="form-control" id="certificado_pfx" name="certificado_pfx" accept=".pfx">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="certificado_senha" class="col-sm-4 control-label">Senha do Certificado</label>
                                        <div class="col-sm-6">
                                            <input type="password" class="form-control" id="certificado_senha" name="certificado_senha">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <div class="form-group">
                                <div class="col-sm-12 text-center">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="glyphicon glyphicon-download-alt"></i> Exportar AFD
                                    </button>
                                </div>
                            </div>
                            
                            <div class="alert alert-info" style="margin-top: 20px;">
                                <p><strong>Nota:</strong> O arquivo será gerado conforme as especificações da Portaria 671/2021 (REP-P).</p>
                                <p>Certifique-se de que o cadastro da empresa (CNPJ/CPF, Razão Social) e dos funcionários esteja completo.</p>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php

    rodape();
}

index();
?>
