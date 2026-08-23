<?php
    /* ============================================================
       Suporte — Detalhe do Chamado
       ============================================================ */
    include __DIR__ . "/../load_env.php";
    include_once __DIR__ . "/../conecta.php";
    include_once __DIR__ . "/../check_permission.php";
    include_once __DIR__ . "/_timeline.php";
    include_once __DIR__ . "/_anexos.php";

    $__id = (int) ($_GET["id"] ?? 0);
    if ($__id < 1) {
        echo "<script>window.location.href='index.php';</script>";
        exit;
    }

    $__apiUrl   = rtrim(strval($_ENV["SUPORTE_API_URL"] ?? ""), "/");
    $__adminKey = strval($_ENV["SUPORTE_ADMIN_KEY"] ?? "");
    $__empresaAtual = trim(strval($_ENV["CONTEX_PATH"] ?? ""), "/");

    // ── Ação: empresa responde o chamado (mesmo token assinado do widget) ──
    // Campo "sup_acao" de propósito: o campo "acao" é interceptado pelo
    // dispatcher legado de contex20/funcoes.php (eval + exit).
    $__msg = "";
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $acao = $_POST["sup_acao"] ?? "";
        if ($acao === "resposta") {
            $idResp = (int) ($_POST["id"] ?? 0);
            $texto = trim(strval($_POST["texto"] ?? ""));
            if ($idResp === $__id && $texto !== "") {
                $__supKey = strval($_ENV["SUPORTE_API_KEY"] ?? "");
                $__uid = strval($_SESSION["user_nb_id"] ?? "");
                $__unome = trim(strval($_SESSION["user_tx_nome"] ?? ""));
                $__ulogin = trim(strval($_SESSION["user_tx_login"] ?? ""));
                $__exp = time() + 300;
                $__json = json_encode([
                    "empresa"      => $__empresaAtual,
                    "empresa_nome" => $__empresaAtual,
                    "uid"          => $__uid,
                    "ulogin"       => $__ulogin,
                    "unome"        => $__unome,
                    "exp"          => $__exp,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $__b64p = rtrim(strtr(base64_encode($__json), "+/", "-_"), "=");
                $__keyD = hash_hmac("sha256", "techps_suporte|" . $__empresaAtual, $__supKey, true);
                $__sig  = rtrim(strtr(base64_encode(hash_hmac("sha256", $__b64p, $__keyD, true)), "+/", "-_"), "=");

                $__ch = curl_init($__apiUrl . "/suporte/tickets/{$idResp}/comentarios");
                curl_setopt_array($__ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 20,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => http_build_query(["texto" => $texto]),
                    CURLOPT_HTTPHEADER     => ["Authorization: Bearer " . $__b64p . "." . $__sig],
                ]);
                $__resp = json_decode((string) curl_exec($__ch), true);
                curl_close($__ch);
                $__msg = !empty($__resp["ok"]) ? "Resposta enviada ao chamado." : ("Erro ao enviar resposta. " . ($__resp["msg"] ?? ""));
            }
            echo "<script>alert(" . json_encode($__msg) . "); window.location.href='detalhe.php?id=" . $__id . "';</script>";
            exit;
        }
    }

    $__ch = curl_init($__apiUrl . "/suporte/tickets/" . $__id);
    curl_setopt_array($__ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ["x-api-key: " . $__adminKey],
    ]);
    $__resposta = curl_exec($__ch);
    $__httpCode = (int) curl_getinfo($__ch, CURLINFO_HTTP_CODE);
    curl_close($__ch);

    $__dados = json_decode((string) $__resposta, true);
    $__ticket = [];
    $__arquivos = [];
    $__comentarios = [];
    $__eventos = [];
    if ($__httpCode >= 200 && $__httpCode < 300 && is_array($__dados) && !empty($__dados["ticket"])) {
        // Isolamento por tenant: só mostra chamado da própria empresa.
        if (strval($__dados["ticket"]["empresa_key"] ?? "") === $__empresaAtual) {
            $__ticket = $__dados["ticket"];
            $__arquivos = is_array($__dados["arquivos"] ?? null) ? $__dados["arquivos"] : [];
            $__comentarios = is_array($__dados["comentarios"] ?? null) ? $__dados["comentarios"] : [];
            $__eventos = is_array($__dados["eventos"] ?? null) ? $__dados["eventos"] : [];
        }
    }

    $__status = $__ticket["status"] ?? "";
    $__statusMap = [
        "aberto"             => '<span class="label label-warning">Aberto</span>',
        "em_analise"         => '<span class="label label-default" style="background:#8e44ad;">Em Análise</span>',
        "em_andamento"       => '<span class="label label-info">Em Andamento</span>',
        "aguardando_cliente" => '<span class="label label-primary">Aguardando retorno do cliente</span>',
        "resolvido"          => '<span class="label label-success">Concluído</span>',
        "cancelado"          => '<span class="label label-default">Cancelado</span>',
        "reaberto"           => '<span class="label label-warning">Reaberto</span>',
        "encaminhado_ssi"    => '<span class="label label-danger">Encaminhado a SSI</span>',
    ];
    $__badge = $__statusMap[$__status] ?? '<span class="label label-default">' . htmlspecialchars($__status) . '</span>';
    $__tipoMap = ["duvida" => "Dúvida operacional", "sugestao" => "Sugestão", "bug" => "Bug de sistema"];
    $__tipo = strval($__ticket["tipo"] ?? "");
    $__ssiCodigo = strval($__ticket["ssi_codigo"] ?? "");
    $__ssiPrioridade = strval($__ticket["ssi_prioridade"] ?? "");

    cabecalho("Chamado #" . $__id);
?>

<div class="row">
    <div class="col-md-12">
        <div class="portlet light bordered">
            <div class="portlet-title">
                <div class="caption">
                    <i class="fa fa-life-ring font-blue"></i>
                    <span class="caption-subject bold uppercase">Chamado #<?= $__id ?></span>
                    <span class="caption-helper"><?= $__badge ?></span>
                </div>
                <div class="actions">
                    <a href="index.php" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Voltar</a>
                </div>
            </div>
            <div class="portlet-body">

                <?php if (empty($__ticket)): ?>
                    <div class="alert alert-danger">Chamado não encontrado ou sem acesso à API de suporte.</div>
                    <?php rodape(); exit; ?>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-striped table-bordered">
                            <tr><th style="width:140px;">Empresa</th><td><?= htmlspecialchars(strval($__ticket["empresa_key"] ?? "")) ?> — <?= htmlspecialchars(strval($__ticket["empresa_nome"] ?? "")) ?></td></tr>
                            <tr><th>Setor</th><td><?= htmlspecialchars(strval($__ticket["setor_nome"] ?? "") ?: "—") ?></td></tr>
                            <tr><th>Usuário</th><td><?= htmlspecialchars(strval($__ticket["user_nome"] ?? "")) ?> (<?= htmlspecialchars(strval($__ticket["user_login"] ?? "")) ?>)</td></tr>
                            <tr><th>Data de abertura</th><td><?= htmlspecialchars(strval($__ticket["created_at"] ?? "")) ?></td></tr>
                            <tr><th>Status</th><td><?= $__badge ?></td></tr>
                            <tr><th>Tipo</th><td><?= isset($__tipoMap[$__tipo]) ? htmlspecialchars($__tipoMap[$__tipo]) : '<span class="text-muted">Em análise</span>' ?></td></tr>
                            <?php if ($__ssiCodigo !== ""): ?>
                                <tr><th>SSI</th><td><span class="label label-danger"><?= htmlspecialchars($__ssiCodigo) ?></span> — <?= $__ssiPrioridade === "urgente" ? "Prioritária (urgente em produção)" : "Próxima atualização" ?></td></tr>
                            <?php endif; ?>
                            <tr><th>Página</th><td style="word-break:break-all;"><small><?= htmlspecialchars(strval($__ticket["pagina_url"] ?? "")) ?></small></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <div class="well">
                            <h4 style="margin-top:0;">Descrição do problema</h4>
                            <p style="white-space:pre-wrap;"><?= htmlspecialchars(strval($__ticket["descricao"] ?? "")) ?></p>
                        </div>
                    </div>
                </div>

                <h4><i class="fa fa-paperclip"></i> Anexos (<?= count($__arquivos) ?>)</h4>
                <?= suporte_render_anexos($__arquivos, $__id, "imagem.php") ?>

                <!-- Comentários do gestor -->
                <h4 style="margin-top:25px;"><i class="fa fa-comments"></i> Comentários (<?= count($__comentarios) ?>)</h4>
                <?php if (empty($__comentarios)): ?>
                    <p class="text-muted"><i class="fa fa-info-circle"></i> Nenhum comentário ainda. Acompanhe este espaço para respostas da equipe TechPS.</p>
                <?php endif; ?>
                <?php foreach ($__comentarios as $__c): ?>
                    <?php
                        $__ehEmpresa = (strval($__c["autor_tipo"] ?? "") === "empresa");
                        $__bg = $__ehEmpresa ? "#e8f0fe" : "#f9f9f9";
                        $__label = $__ehEmpresa
                            ? '<span class="label label-primary" style="margin-left:6px;">Empresa</span>'
                            : '<span class="label label-info" style="margin-left:6px;">TechPS</span>';
                    ?>
                    <div style="border:1px solid #ddd;border-radius:6px;padding:10px 12px;margin-bottom:8px;background:<?= $__bg ?>;">
                        <div style="font-size:12px;color:#888;margin-bottom:4px;">
                            <i class="fa fa-user-circle"></i> <strong><?= htmlspecialchars(strval($__c["autor"] ?? "")) ?></strong>
                            <?= $__label ?>
                            <span style="margin-left:8px;"><?= htmlspecialchars(strval($__c["created_at"] ?? "")) ?></span>
                        </div>
                        <div style="white-space:pre-wrap;"><?= htmlspecialchars(strval($__c["texto"] ?? "")) ?></div>
                    </div>
                <?php endforeach; ?>

                <!-- Responder (empresa) -->
                <form method="post" style="margin-top:15px;border-top:1px solid #eee;padding-top:12px;">
                    <input type="hidden" name="sup_acao" value="resposta" />
                    <input type="hidden" name="id" value="<?= $__id ?>" />
                    <textarea name="texto" rows="3" maxlength="1000" required placeholder="Escreva sua resposta para a equipe TechPS..." style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;resize:vertical;box-sizing:border-box;"></textarea>
                    <div style="margin-top:8px;text-align:right;">
                        <button type="submit" class="btn blue"><i class="fa fa-reply"></i> Responder chamado</button>
                    </div>
                </form>

                <!-- Timeline -->
                <h4 style="margin-top:28px;"><i class="fa fa-history"></i> Linha do tempo do chamado</h4>
                <?= suporte_render_timeline($__eventos) ?>

            </div>
        </div>
    </div>
</div>

<?php rodape(); ?>
