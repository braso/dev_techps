<?php
//* Modo debug {
ini_set("display_errors", 1);
error_reporting(E_ALL);
// } */

require_once __DIR__ . "/tcpdf/tcpdf.php";
require __DIR__ . "/funcoes_ponto.php";

$motorista = carregar("entidade", $_POST["id"]);

dd($motorista);

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistema');
$pdf->SetTitle('Ficha de Cadastro de Funcionário');

$pdf->SetMargins(15, 20, 15);
$pdf->AddPage();

$fotoPath = $motorista["enti_tx_foto"] ?? "";
$larguraFoto = 30;
$alturaFoto = 40;
$posX = 165;
$posY = 12;

if (file_exists($fotoPath)) {
    $pdf->Image($fotoPath, $posX, $posY, $larguraFoto, $alturaFoto, '', '', '', true);
} else {
    $pdf->Rect($posX, $posY, $larguraFoto, $alturaFoto);
    $pdf->SetXY($posX, $posY + ($alturaFoto / 2) - 2.5);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell($larguraFoto, 5, 'FOTO', 0, 0, 'C');
}

$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetXY(15, 25);
$pdf->Cell(140, 10, 'FICHA DE CADASTRO DE FUNCIONÁRIO', 0, 1, 'L');
$pdf->Ln(10);

function tituloSecaoFicha($pdf, $texto) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 7, $texto, 'B', 1, 'L');
    $pdf->Ln(1);
    $pdf->SetFont('helvetica', '', 11);
}

function campoFicha($pdf, $label, $valor, $labelWidth = 45) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->MultiCell($labelWidth, 6, $label . ':', 0, 'L', false, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->MultiCell(0, 6, $valor, 0, 'L', false, 1);
}

// DADOS PESSOAIS
tituloSecaoFicha($pdf, 'DADOS PESSOAIS');
campoFicha($pdf, 'Nome', $motorista['enti_tx_nome']);
campoFicha($pdf, 'E-mail', $motorista['enti_tx_email']);
campoFicha($pdf, 'Telefone 1', $motorista['enti_tx_fone1']);
campoFicha($pdf, 'Telefone 2', $motorista['enti_tx_fone2']);
campoFicha($pdf, 'Login', $motorista['enti_tx_login']);
campoFicha($pdf, 'Status', $motorista['enti_tx_status']);
campoFicha($pdf, 'Matrícula', $motorista["enti_tx_matricula"]);
campoFicha($pdf, 'Nascimento', date("d/m/Y", strtotime($motorista["enti_tx_nascimento"] ?? '')));
campoFicha($pdf, 'Naturalidade', $motorista["enti_tx_naturalidade"]);
campoFicha($pdf, 'Nacionalidade', $motorista["enti_tx_nacionalidade"]);

$cpf = preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "$1.$2.$3-$4", $motorista["enti_tx_cpf"]);
$rg = preg_replace("/(\d{2})(\d{3})(\d{3})(\d{1})/", "$1.$2.$3-$4", $motorista["enti_tx_rg"]);

campoFicha($pdf, 'CPF', $cpf);
campoFicha($pdf, 'RG', $rg);
campoFicha($pdf, 'Data de Emissão do RG', date("d/m/Y", strtotime($motorista["enti_tx_emissao_rg"] ?? '')));
campoFicha($pdf, 'Órgão Emissor', $motorista["enti_tx_orgao_rg"]);
campoFicha($pdf, 'Estado Civil', $motorista["enti_tx_civil"]);
campoFicha($pdf, 'Sexo', $motorista["enti_tx_tipo"]);
campoFicha($pdf, 'Endereço', $motorista["enti_tx_endereco"]);
campoFicha($pdf, 'Bairro', $motorista["enti_tx_bairro"]);
campoFicha($pdf, 'CEP', $motorista["enti_tx_cep"]);
campoFicha($pdf, 'Complemento', $motorista["enti_tx_complemento"]);
campoFicha($pdf, 'UF', $motorista["enti_tx_uf"]);
campoFicha($pdf, 'Cidade', $motorista["enti_tx_cidade"]);
campoFicha($pdf, 'Número', $motorista["enti_tx_numero"]);
campoFicha($pdf, 'Nome do Cônjuge', $motorista["enti_tx_conjuge"]);
campoFicha($pdf, 'Filiação Pai', $motorista["enti_tx_pai"]);
campoFicha($pdf, 'Filiação Mãe', $motorista["enti_tx_mae"]);
campoFicha($pdf, 'Observações', $motorista["enti_tx_obs"]);

$pdf->Ln(3);

// DADOS CONTRATUAIS
tituloSecaoFicha($pdf, 'DADOS CONTRATUAIS');
$empresa = mysqli_fetch_assoc(query("SELECT empr_tx_nome FROM empresa WHERE empr_nb_id = {$motorista['enti_nb_empresa']}"));
campoFicha($pdf, 'Empresa', $empresa["empr_tx_nome"] ?? '');
campoFicha($pdf, 'Salário', 'R$ ' . number_format($motorista["enti_tx_salario"], 2, ',', '.'));
campoFicha($pdf, 'Ocupação', $motorista["enti_tx_ocupacao"]);
campoFicha($pdf, 'Data de Admissão', date("d/m/Y", strtotime($motorista["enti_tx_admissao"] ?? '')));
campoFicha($pdf, 'Subcontratado', $motorista["enti_tx_subcontratado"]);

// JORNADA E SINDICATO
tituloSecaoFicha($pdf, 'CONVENÇÃO SINDICAL - JORNADA PADRÃO DO FUNCIONÁRIO');
$parametro = mysqli_fetch_assoc(query("SELECT para_tx_nome FROM parametro WHERE para_nb_id = {$motorista['enti_nb_parametro']}"));
campoFicha($pdf, 'Parâmetros da Jornada', $parametro);
campoFicha($pdf, 'Jornada Semanal', $motorista["enti_tx_jornadaSemanal"].' horas/dia');
campoFicha($pdf, 'Jornada Sábado', $motorista["enti_tx_jornadaSabado"].' horas/dia');
campoFicha($pdf, 'H.E. Semanal (%)', $motorista["enti_tx_percHESemanal"].'%');
campoFicha($pdf, 'H.E. Extraordinária (%)', $motorista["enti_tx_percHEEx"].'%');
if(!empty($motorista["enti_nb_empresa"])){
    $aEmpresa = carregar("empresa",  $motorista["enti_nb_empresa"]);
    $aParametro = carregar("parametro", $aEmpresa["empr_nb_parametro"]);

    $padronizado = (
         $motorista["enti_tx_jornadaSemanal"] 		== $aParametro["para_tx_jornadaSemanal"] &&
         $motorista["enti_tx_jornadaSabado"] 		== $aParametro["para_tx_jornadaSabado"] &&
         $motorista["enti_tx_percHESemanal"] 		== $aParametro["para_tx_percHESemanal"] &&
         $motorista["enti_tx_percHEEx"] 	== $aParametro["para_tx_percHEEx"]
    );
    dd($padronizado);
    // ($padronizado? "Sim": "Não");
}
campoFicha($pdf, 'Convenção Padrão', 'Sim');

// CNH
tituloSecaoFicha($pdf, 'CARTEIRA NACIONAL DE HABILITAÇÃO');
campoFicha($pdf, 'Nº Registro', $motorista["enti_tx_cnhRegistro"]);
campoFicha($pdf, 'Categoria', $motorista["enti_tx_cnhCategoria"]);
campoFicha($pdf, 'Emissão', date("d/m/Y", strtotime($motorista["enti_tx_cnhEmissao"] ?? '')));
campoFicha($pdf, 'Validade', date("d/m/Y", strtotime($motorista["enti_tx_cnhValidade"] ?? '')));
campoFicha($pdf, '1ª Habilitação', date("d/m/Y", strtotime($motorista["enti_tx_cnhPrimeiraHabilitacao"] ?? '')));
campoFicha($pdf, 'Atividade Remunerada', $motorista["enti_tx_cnhAtividadeRemunerada"]);
$cidade = mysqli_fetch_assoc(query("SELECT cida_tx_nome FROM cidade WHERE cida_nb_id = {$motorista['enti_nb_cnhCidade']}"));
campoFicha($pdf, 'Cidade/UF de Emissão', $cidade["cida_tx_nome"] ?? '');
campoFicha($pdf, 'Observação', $motorista["enti_tx_cnhObservacao"]);

$pdf->Ln(3);

// HISTÓRICO
tituloSecaoFicha($pdf, 'HISTÓRICO');
campoFicha($pdf, 'Data de Cadastro', date("d/m/Y", strtotime($motorista["enti_tx_cadastro"] ?? 'now')));
campoFicha($pdf, 'Última Atualização', date("d/m/Y \\u00e0s H:i:s", strtotime($motorista["enti_tx_atualizacao"] ?? 'now')));

$pdf->Output('ficha_cadastro.pdf', 'I');
