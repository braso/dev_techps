<?php
// armazem_paraiba/gerar_espelho_assinatura.php
ini_set("display_errors", 1);
error_reporting(E_ALL);

include "conecta.php";
include "funcoes_ponto.php";

if (empty($_GET["id_motorista"])) {
    die("ID do funcionário não fornecido.");
}

$id_motorista = (int)$_GET["id_motorista"];
$data = !empty($_GET["data"]) ? $_GET["data"] : date("Y-m");

// Fetch Employee
$sql = "SELECT entidade.*, parametro.para_tx_pagarHEExComPerNeg, parametro.para_tx_tolerancia 
        FROM entidade 
        LEFT JOIN parametro ON enti_nb_parametro = para_nb_id
        WHERE enti_nb_id = $id_motorista";
$motorista = mysqli_fetch_assoc(query($sql));

if (!$motorista) {
    die("Funcionário não encontrado.");
}

$id_empresa = $motorista['enti_nb_empresa'];

// Fetch Company
$aEmpresa = mysqli_fetch_array(query(
    "SELECT empresa.*, cidade.cida_tx_nome, cidade.cida_tx_uf 
     FROM empresa 
     JOIN cidade ON empresa.empr_nb_cidade = cidade.cida_nb_id
     WHERE empr_nb_id = $id_empresa"
), MYSQLI_BOTH);

$enderecoEmpresa = implode(", ", array_filter([
    $aEmpresa["empr_tx_endereco"], 
    $aEmpresa["empr_tx_numero"], 
    $aEmpresa["empr_tx_bairro"], 
    $aEmpresa["empr_tx_complemento"], 
    $aEmpresa["empr_tx_referencia"]
]));

// Logic from endosso.php loop
$date = new DateTime($data."-01");
$endossoCompleto = montarEndossoMes($date, $motorista);

if (empty($endossoCompleto)) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Espelho de Ponto - Não encontrado</title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    </head>
    <body class="bg-gray-100 h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md max-w-md w-full text-center">
            <div class="mb-4 text-red-500">
                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <h1 class="text-xl font-bold text-gray-800 mb-2">Espelho de Ponto não encontrado</h1>
            <p class="text-gray-600 mb-6">Não foi encontrado um espelho de ponto endossado para o funcionário <strong><?= $motorista['enti_tx_nome'] ?></strong> no mês de <strong><?= $date->format("m/Y") ?></strong>.</p>
            
            <form method="GET" action="" class="bg-gray-50 p-4 rounded-lg border border-gray-200 text-left">
                <input type="hidden" name="id_motorista" value="<?= $id_motorista ?>">
                
                <label class="block text-sm font-medium text-gray-700 mb-1">Selecione outro mês:</label>
                <div class="flex gap-2">
                    <input type="month" name="data" value="<?= $data ?>" class="flex-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 border p-2">
                    <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition">
                        Buscar
                    </button>
                </div>
            </form>
            
            <div class="mt-6">
                <a href="javascript:window.close()" class="text-sm text-gray-500 hover:text-gray-700 underline">Fechar Janela</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$totalResumo = $endossoCompleto["totalResumo"];

if(empty($totalResumo["HESemanalAPagar"])){
    $totalResumo["HESemanalAPagar"] = $totalResumo["he50APagar"];
}
if(empty($totalResumo["HEExAPagar"])){
    $totalResumo["HEExAPagar"] = $totalResumo["he100APagar"];
}

$rows = [];
for ($f2 = 0; $f2 < count($endossoCompleto["endo_tx_pontos"]); $f2++) {
    $aDetalhado = $endossoCompleto["endo_tx_pontos"][$f2];

    // INICIO Lógica de Tolerância
    if(!empty($motorista["para_tx_tolerancia"]) && isset($aDetalhado["diffSaldo"])){
        $tolerancia = intval($motorista["para_tx_tolerancia"]);
        $saldoDiarioStr = strip_tags($aDetalhado["diffSaldo"]);
        $parts = explode(":", $saldoDiarioStr);
        
        if(count($parts) == 2){
            $minutos = intval($parts[0]) * 60 + ($parts[0][0] == '-' ? -1 : 1) * intval($parts[1]);
            if(abs($minutos) <= $tolerancia){
                $aDetalhado["diffSaldo"] = "00:00";
            }
        }
    }
    // FIM Lógica de Tolerância

    array_shift($aDetalhado);
    array_splice($aDetalhado, 10, 1); //Retira a coluna de "Jornada"
    $rows[] = $aDetalhado;
}

// Inserir coluna de motivos
$legendas = [
    "" => "",
    "I" => "(I - Incluída Manualmente)",
    "P" => "(P - Pré-Assinalada)",
    "T" => "(T - Outras fontes de marcação)",
    "DSR" => "(DSR - Descanso Semanal Remunerado e Abono)"
];
for($f2 = 0; $f2 < count($rows); $f2++){
    $dataRow = implode("-", array_reverse(explode("/", $rows[$f2]["data"])));
    
    $bdMotivos = mysqli_fetch_all(
        query(
            "SELECT moti_tx_legenda, moti_tx_nome FROM ponto
                JOIN entidade ON pont_tx_matricula = enti_tx_matricula
                JOIN motivo ON pont_nb_motivo = moti_nb_id
                WHERE pont_tx_status = 'ativo'
                    AND enti_nb_id = '{$endossoCompleto["endo_nb_entidade"]}' 
                    AND pont_tx_data LIKE '{$dataRow}%'
                    AND pont_tx_tipo IN (1,2,3,4);"
        ), 
        MYSQLI_ASSOC
    );

    $bdAbonos = mysqli_fetch_all(query(
        "SELECT motivo.moti_tx_nome FROM abono
            JOIN entidade ON abon_tx_matricula = enti_tx_matricula
            JOIN motivo ON abon_nb_motivo = moti_nb_id
            WHERE abon_tx_status = 'ativo' 
                AND enti_nb_id = '{$endossoCompleto["endo_nb_entidade"]}' 
                AND abon_tx_data LIKE '{$dataRow}%' 
            LIMIT 1;"
        ),MYSQLI_ASSOC
    );

    $motivos = "";
    if(!empty($bdAbonos[0]["moti_tx_nome"])){
        $motivos .= $bdAbonos[0]["moti_tx_nome"]."<br>";
    }

    for($f3 = 0; $f3 < count($bdMotivos); $f3++){
        $motivo = isset($legendas[$bdMotivos[$f3]["moti_tx_legenda"]])? $bdMotivos[$f3]["moti_tx_nome"]: "";
        if(!empty($motivo) && is_bool(strpos($motivos, $motivo))){
            $motivos .= $motivo."<br>";
        }
    }

    array_splice($rows[$f2], 18, 0, $motivos); 
}

$qtdDiasEndossados = count($rows);
$aDia = $rows;

$botoes = [
    "<button onclick='imprimir()' class='btn btn-primary' style='margin-right: 10px;'>Imprimir</button>",
    "<button onclick='downloadCSV({$motorista['enti_nb_id']}, \"{$motorista['enti_tx_nome']}\")' class='btn btn-secondary'>Baixar CSV</button>"
];

$colspanTitulos = [2,4,2,2,4,2]; 
$cabecalho = [
    "data" => "DATA",
    "diaSemana" => "DIA",
    "inicioJornada" => "INÍCIO",
    "inicioRefeicao" => "INÍCIO REF.",
    "fimRefeicao" => "FIM REF.",
    "fimJornada" => "FIM",
    "diffRefeicao" => "REFEIÇÃO",
    "diffDescanso" => "DESCANSO",
    "jornadaPrevista" => "PREVISTA",
    "diffJornadaEfetiva" => "EFETIVA",
    "intersticio" => "INTERSTÍCIO",
    "he50" => "HE {$motorista["enti_tx_percHESemanal"]}%",
    "he100" => "HE&nbsp;{$motorista["enti_tx_percHEEx"]}%",
    "adicionalNoturno" => "ADICIONAL NOT.",
    "0" => "MOTIVO",
    "diffSaldo" => "SALDO",
];

if(in_array($motorista["enti_tx_ocupacao"], ["Ajudante", "Motorista"])){
    $colspanTitulos = [2,4,4,3,5,2]; 
    $cabecalho = array_merge(
        array_slice($cabecalho, 0, 7),
        ["diffEspera" => "ESPERA"],
        array_slice($cabecalho, 7, 1),
        ["diffRepouso" => "REPOUSO"],
        array_slice($cabecalho, 8, 2),
        ["maximoDirecaoContinua" => "MDC"],
        array_slice($cabecalho, 10, 4),
        ["esperaIndenizada" => "ESPERA INDENIZADA"],
        array_slice($cabecalho, 14, count($cabecalho))
    );
}

$extraHeader = '
<div class="noprint" style="position: fixed; top: 10px; right: 10px; z-index: 9999; background: white; padding: 10px; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
    <form method="GET" style="display: flex; gap: 10px; align-items: center; margin: 0;">
        <input type="hidden" name="id_motorista" value="'.$id_motorista.'">
        <label style="font-size: 0.875rem; font-weight: 500; color: #374151;">Mês:</label>
        <input type="month" name="data" value="'.$data.'" onchange="this.form.submit()" style="border: 1px solid #d1d5db; border-radius: 4px; padding: 4px 8px; font-size: 0.875rem;">
    </form>
</div>
<style>@media print { .noprint { display: none !important; } }</style>
';

include "./relatorio_espelho.php";
?>