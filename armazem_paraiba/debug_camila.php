<?php
define('NO_CONNECTION', true);
define('MYSQLI_ASSOC', 1);

// Mock environment
$_SERVER['DOCUMENT_ROOT'] = 'c:/Users/braso/Desktop/TECHPS WORKFLOW/REFATORAMENTO E ATUAL/TECHPS ATUAL/dev_techps';
$_SERVER['REQUEST_SCHEME'] = 'http';
$_SERVER['HTTP_HOST'] = 'localhost';

// Mock MySQLi functions
if (!function_exists('mysqli_connect')) {
    function mysqli_connect() { return true; }
    function mysqli_set_charset() { return true; }
    function mysqli_connect_error() { return false; }
    function mysqli_close() { return true; }
    function mysqli_query($conn, $sql) { return new stdClass(); }
    function mysqli_fetch_assoc($res) { return null; }
    function mysqli_fetch_all($res, $mode=0) { return []; }
    function mysqli_error($conn) { return ""; }
}

// Mock query function
function query($sql) {
    return new stdClass();
}

// Mock data function
function data($date, $type=0) {
    if($type==1) return date("d/m/Y H:i:s", strtotime($date));
    return date("d/m/Y", strtotime($date));
}

include "funcoes_ponto.php";

// Define mock data for Camila
$motorista = [
    'enti_tx_matricula' => '61499',
    'para_tx_tipo' => 'escala',
    'esca_tx_dataInicio' => '2024-06-01', // Example start date
    'esca_nb_periodicidade' => 2,
    'diasEscala' => [
        // Day 1 is empty/off
        // Day 2 is working
        [
            'esca_nb_numeroDia' => 2,
            'esca_tx_horaInicio' => '19:00',
            'esca_tx_horaFim' => '07:00',
            'esca_tx_intervaloInterno' => '02:00'
        ]
    ]
];

// Simulate for a range of dates
$startDate = new DateTime('2025-01-10');
$endDate = new DateTime('2025-01-16');

echo "\nSimulacao diaDetalhePonto:\n";

for ($date = clone $startDate; $date <= $endDate; $date->modify('+1 day')) {
    $dataStr = $date->format('Y-m-d');
    
    // Manually calc diaDoCiclo to verify
    $diferenca = (new DateTime($motorista["esca_tx_dataInicio"]))->diff(new DateTime($dataStr));
    $diferenca = $diferenca->days*($diferenca->invert? -1: 1);
    $diaDoCiclo = round($motorista["esca_nb_periodicidade"]*(($diferenca)/($motorista["esca_nb_periodicidade"])-floor($diferenca/$motorista["esca_nb_periodicidade"]))+1);

    // Call diaDetalhePonto
    // Note: It calls query() internally, which returns empty results, so it assumes no punches.
    // This is fine for checking scale generation.
    $resultado = diaDetalhePonto($motorista, $dataStr);
    
    echo "Data: $dataStr | Ciclo Calc: $diaDoCiclo | InicioEscala: " . $resultado['inicioEscala'] . " | FimEscala: " . $resultado['fimEscala'] . "\n";
}
?>