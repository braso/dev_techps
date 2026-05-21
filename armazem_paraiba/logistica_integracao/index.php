<?php
/*
    Dashboard de Monitoramento - Integração Logística
    Monitora as integrações com rastreadores (SASCAR, etc.)
*/

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

include_once "../funcoes_ponto.php";
include_once "../check_permission.php";

// Função para buscar dados de integração da API real
function buscarDadosIntegracao() {
    $url = "https://logistica.integracao.techpsgj.com.br/empresas-posicoes-hoje";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error || $httpCode !== 200) {
        return ["erro" => "Falha ao conectar com a API. HTTP: {$httpCode}. Erro: {$error}"];
    }
    
    $dados = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ["erro" => "Resposta inválida da API."];
    }
    
    // A API pode retornar um objeto com chave "empresas" ou diretamente um array
    if (isset($dados["empresas"])) {
        return $dados["empresas"];
    }
    
    // Se for array direto
    if (is_array($dados) && isset($dados[0]["empresaApi"])) {
        return $dados;
    }
    
    // Se for objeto único
    if (isset($dados["empresaApi"])) {
        return [$dados];
    }
    
    return $dados;
}

// Buscar dados
$integracoes = buscarDadosIntegracao();
$erroApi = null;

// Verificar se houve erro
if (isset($integracoes["erro"])) {
    $erroApi = $integracoes["erro"];
    $integracoes = [];
}

// Calcular estatísticas gerais
$totalVeiculos = 0;
$totalIntegracoes = count($integracoes);

foreach ($integracoes as $integracao) {
    $totalVeiculos += $integracao["totalVeiculos"] ?? count($integracao["veiculos"] ?? []);
}

cabecalho("Dashboard - Integração Logística");
?>

<style>
    .dashboard-container {
        padding: 20px;
    }
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }
    .dashboard-header h2 {
        margin: 0;
        color: #2c3e50;
        font-weight: 600;
    }
    .dashboard-header .ultima-atualizacao {
        color: #7f8c8d;
        font-size: 13px;
    }
    
    /* Cards de Resumo */
    .cards-resumo {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .card-resumo {
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        display: flex;
        align-items: center;
        gap: 16px;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .card-resumo:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(0,0,0,0.12);
    }
    .card-resumo .icone {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: #fff;
    }
    .card-resumo .info h3 {
        margin: 0;
        font-size: 28px;
        font-weight: 700;
        color: #2c3e50;
    }
    .card-resumo .info p {
        margin: 4px 0 0;
        font-size: 13px;
        color: #7f8c8d;
        font-weight: 500;
    }
    .bg-primary { background: #3498db; }
    .bg-success { background: #28a745; }
    .bg-info { background: #17a2b8; }
    
    /* Seção de Integrações */
    .secao-integracoes {
        margin-bottom: 30px;
    }
    .secao-integracoes h3 {
        color: #2c3e50;
        font-weight: 600;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #ecf0f1;
    }
    
    /* Card de Integração */
    .integracao-card {
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }
    .integracao-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 15px;
    }
    .integracao-header:hover {
        opacity: 0.85;
    }
    .integracao-header .empresa-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .integracao-header .empresa-logo {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-weight: 700;
        font-size: 16px;
    }
    .integracao-header .empresa-nome {
        font-size: 18px;
        font-weight: 600;
        color: #2c3e50;
    }
    .integracao-header .empresa-cnpj {
        font-size: 12px;
        color: #95a5a6;
        margin-top: 2px;
    }
    .integracao-header .badge-veiculos {
        background: #ecf0f1;
        color: #2c3e50;
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 14px;
    }
    
    /* Tabela de Veículos */
    .tabela-veiculos {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    .tabela-veiculos thead th {
        background: #f8f9fa;
        padding: 12px 16px;
        text-align: left;
        font-size: 12px;
        font-weight: 600;
        color: #7f8c8d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #ecf0f1;
    }
    .tabela-veiculos tbody td {
        padding: 14px 16px;
        border-bottom: 1px solid #f1f3f5;
        font-size: 14px;
        color: #2c3e50;
    }
    .tabela-veiculos tbody tr:hover {
        background: #f8f9fa;
    }
    .tabela-veiculos tbody tr:last-child td {
        border-bottom: none;
    }
    .placa-badge {
        background: #2c3e50;
        color: #fff;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 13px;
        letter-spacing: 0.5px;
        font-family: 'Courier New', monospace;
    }
    
    /* Botão Atualizar */
    .btn-atualizar {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: opacity 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-atualizar:hover {
        opacity: 0.9;
    }
    .btn-atualizar.girando i {
        animation: girar 1s linear infinite;
    }
    @keyframes girar {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    /* Responsivo */
    @media (max-width: 768px) {
        .cards-resumo {
            grid-template-columns: repeat(2, 1fr);
        }
        .integracao-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
    }
</style>

<div class="dashboard-container">
    <!-- Header -->
    <div class="dashboard-header">
        <div>
            <h2><i class="fa fa-satellite-dish" style="margin-right: 10px; color: #667eea;"></i>Monitoramento de Integrações</h2>
            <span class="ultima-atualizacao">
                <i class="fa fa-clock-o"></i> Última atualização: <span id="ultimaAtualizacao"><?= date("d/m/Y H:i:s") ?></span>
            </span>
        </div>
        <button class="btn-atualizar" onclick="atualizarDashboard(this)">
            <i class="fa fa-refresh"></i> Atualizar
        </button>
    </div>
    
    <!-- Erro da API -->
    <?php if ($erroApi): ?>
    <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 16px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <i class="fa fa-exclamation-circle" style="font-size: 20px;"></i>
        <div>
            <strong>Erro ao conectar com a API:</strong> <?= htmlspecialchars($erroApi) ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Cards de Resumo -->
    <div class="cards-resumo">
        <div class="card-resumo">
            <div class="icone bg-info">
                <i class="fa fa-plug"></i>
            </div>
            <div class="info">
                <h3><?= $totalIntegracoes ?></h3>
                <p>Integrações Ativas</p>
            </div>
        </div>
        <div class="card-resumo">
            <div class="icone bg-primary">
                <i class="fa fa-truck"></i>
            </div>
            <div class="info">
                <h3><?= $totalVeiculos ?></h3>
                <p>Total de Veículos</p>
            </div>
        </div>
    </div>
    
    <!-- Integrações -->
    <div class="secao-integracoes">
        <h3><i class="fa fa-server" style="margin-right: 8px;"></i>Detalhamento por Integração</h3>
        
        <?php foreach ($integracoes as $idx => $integracao): ?>
        <div class="integracao-card">
            <div class="integracao-header" onclick="toggleIntegracao(<?= $idx ?>)" style="cursor: pointer; margin-bottom: 0;">
                <div class="empresa-info">
                    <div class="empresa-logo">
                        <?= strtoupper(substr($integracao["empresaApi"], 0, 2)) ?>
                    </div>
                    <div>
                        <div class="empresa-nome"><?= htmlspecialchars($integracao["empresaApi"]) ?></div>
                        <div class="empresa-cnpj">CNPJ: <?= preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "$1.$2.$3/$4-$5", $integracao["cnpj"]) ?></div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div class="badge-veiculos">
                        <i class="fa fa-truck"></i> <?= $integracao["totalVeiculos"] ?? count($integracao["veiculos"] ?? []) ?> veículos
                    </div>
                    <i class="fa fa-chevron-down toggle-icon" id="icon-<?= $idx ?>" style="color: #95a5a6; transition: transform 0.3s;"></i>
                </div>
            </div>
            
            <div class="integracao-body" id="body-<?= $idx ?>" style="display: none; padding-top: 15px; margin-top: 15px; border-top: 1px solid #ecf0f1;">
                <table class="tabela-veiculos">
                    <thead>
                        <tr>
                            <th>Placa</th>
                            <th>Última Posição</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($integracao["veiculos"] ?? []) as $veiculo): 
                            $ultimaData = new DateTime($veiculo["ultimaPosicao"]);
                        ?>
                        <tr>
                            <td><span class="placa-badge"><?= htmlspecialchars($veiculo["placa"]) ?></span></td>
                            <td><?= $ultimaData->format("d/m/Y H:i:s") ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function toggleIntegracao(idx) {
    var body = document.getElementById('body-' + idx);
    var icon = document.getElementById('icon-' + idx);
    
    if (body.style.display === 'none') {
        body.style.display = 'block';
        icon.style.transform = 'rotate(180deg)';
    } else {
        body.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
    }
}

function atualizarDashboard(btn) {
    btn.classList.add('girando');
    setTimeout(function() {
        window.location.reload();
    }, 500);
}

// Auto-refresh a cada 60 segundos
setInterval(function() {
    document.getElementById('ultimaAtualizacao').textContent = new Date().toLocaleString('pt-BR');
}, 60000);
</script>

<?php
rodape();
?>
