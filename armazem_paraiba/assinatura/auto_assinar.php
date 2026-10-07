<?php
/* ============================================================
   Assinatura em sequência de quem assina muitos documentos
   (responsável de setor/cargo, gerente etc.).

   Depois de assinar um documento, a tela pergunta a esta rotina quantos
   outros estão esperando a assinatura DA MESMA PESSOA e devolve os tokens
   para que a própria tela de assinatura assine um por um.

   Regras de segurança:
   - a identidade vem do token que a pessoa acabou de usar, nunca do
     navegador;
   - só entram pendências do mesmo signatário (mesmo cadastro ou, sem
     cadastro, o mesmo e-mail);
   - só entram documentos em que já é a vez dela (nenhuma ordem anterior
     em aberto) e cuja solicitação não está expirada;
   - só entram documentos em que ela NÃO é o primeiro signatário, ou seja,
     onde ela assina como responsável/testemunha, nunca o documento de
     outra pessoa no lugar dela.
   ============================================================ */

$interno = true;
include __DIR__ . "/../conecta.php";

header("Content-Type: application/json; charset=utf-8");

function autoAssinarResponder(array $dados, int $codigo = 200): void {
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

$token = trim(strval($_REQUEST["token"] ?? ""));
if ($token === "") {
    autoAssinarResponder(["ok" => false, "erro" => "Token não informado."], 400);
}

// Quem é a pessoa do token (ela pode já ter assinado este documento agora).
$atual = mysqli_fetch_assoc(query(
    "SELECT a.id, a.id_solicitacao, a.enti_nb_id, a.email, a.nome, a.funcao
       FROM assinantes a
      WHERE a.token = ?
      LIMIT 1",
    "s",
    [$token]
));

if (empty($atual)) {
    autoAssinarResponder(["ok" => false, "erro" => "Assinante não encontrado."], 404);
}

$entidadeId = intval($atual["enti_nb_id"] ?? 0);
$emailAtual = strtolower(trim(strval($atual["email"] ?? "")));
$solicitacaoAtual = intval($atual["id_solicitacao"] ?? 0);

if ($entidadeId <= 0 && $emailAtual === "") {
    autoAssinarResponder(["ok" => true, "total" => 0, "itens" => []]);
}

// Identifica a pessoa pelo cadastro quando existe; senão, pelo e-mail.
$filtroPessoa = $entidadeId > 0
    ? "a.enti_nb_id = ?"
    : "LOWER(TRIM(a.email)) = ?";
$tipoPessoa = $entidadeId > 0 ? "i" : "s";
$valorPessoa = $entidadeId > 0 ? $entidadeId : $emailAtual;

$sql =
    "SELECT a.token, a.funcao, a.ordem,
            s.id AS solicitacao_id,
            s.nome_arquivo_original,
            s.data_solicitacao,
            s.expires_at,
            t.tipo_tx_nome
       FROM assinantes a
       JOIN solicitacoes_assinatura s ON s.id = a.id_solicitacao
       LEFT JOIN tipos_documentos t ON t.tipo_nb_id = s.tipo_documento_id
      WHERE {$filtroPessoa}
        AND LOWER(TRIM(a.status)) = 'pendente'
        AND a.ordem > 1
        AND a.id_solicitacao <> ?
        AND LOWER(TRIM(s.status)) NOT IN ('concluido', 'assinado', 'expirado', 'cancelado')
        AND (s.expires_at IS NULL OR s.expires_at = '0000-00-00 00:00:00' OR s.expires_at > UTC_TIMESTAMP())
        AND NOT EXISTS (
            SELECT 1 FROM assinantes anterior
             WHERE anterior.id_solicitacao = a.id_solicitacao
               AND anterior.ordem < a.ordem
               AND LOWER(TRIM(anterior.status)) NOT IN ('assinado', 'dispensado')
        )
      ORDER BY s.data_solicitacao ASC, a.id ASC";

$rs = query($sql, $tipoPessoa . "i", [$valorPessoa, $solicitacaoAtual]);

$itens = [];
while ($rs && ($linha = mysqli_fetch_assoc($rs))) {
    $itens[] = [
        "token"      => strval($linha["token"] ?? ""),
        "documento"  => strval($linha["nome_arquivo_original"] ?? ""),
        "tipo"       => strval($linha["tipo_tx_nome"] ?? ""),
        "funcao"     => strval($linha["funcao"] ?? ""),
        "enviado_em" => strval($linha["data_solicitacao"] ?? ""),
    ];
}

autoAssinarResponder([
    "ok"      => true,
    "pessoa"  => strval($atual["nome"] ?? ""),
    "total"   => count($itens),
    "itens"   => $itens,
]);
