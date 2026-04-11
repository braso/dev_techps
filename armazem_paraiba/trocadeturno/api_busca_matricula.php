<?php
$interno = true;
include_once "../conecta.php";
include_once __DIR__."/helpers_troca_turno.php";

error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Retorna sempre JSON limpo e encerra a execucao do endpoint.
function tt_json_out($arr) {
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($arr);
    exit;
}

$matricula = trim(strval(isset($_GET['matricula']) ? $_GET['matricula'] : ''));
if ($matricula === '') {
    tt_json_out(array('sucesso' => false, 'msg' => 'Matricula nao informada.'));
}

$dados = tt_buscarPorMatricula($matricula);
if (empty($dados)) {
    tt_json_out(array('sucesso' => false, 'msg' => 'Matricula nao encontrada.'));
}

list($setor, $subsetor) = tt_buscarSetorSubsetor(intval(isset($dados['enti_nb_id']) ? $dados['enti_nb_id'] : 0));

tt_json_out(array(
    'sucesso' => true,
    'nome' => strval(isset($dados['user_tx_nome']) ? $dados['user_tx_nome'] : ''),
    'setor' => $setor,
    'subsetor' => $subsetor
));

