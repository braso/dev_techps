<?php
    /* ============================================================
       Suporte — Detalhe do Chamado
       ============================================================ */
    include __DIR__ . "/../load_env.php";
    include_once __DIR__ . "/../conecta.php";
    include_once __DIR__ . "/../check_permission.php";

    $__id = (int) ($_GET["id"] ?? 0);
    if ($__id < 1) {
        echo "<script>window.location.href='index.php';</script>";
        exit;
    }

    $__apiUrl   = rtrim(strval($_ENV["SUPORTE_API_URL"] ?? ""), "/");
    $__adminKey = strval($_ENV["SUPORTE_ADMIN_KEY"] ?? "");
    $__empresaAtual = trim(strval($_ENV["CONTEX_PATH"] ?? ""), "/");

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
    if ($__httpCode >= 200 && $__httpCode < 300 && is_array($__dados) && !empty($__dados["ticket"])) {
        // Isolamento por tenant: só mostra chamado da própria empresa.
        if (strval($__dados["ticket"]["empresa_key"] ?? "") === $__empresaAtual) {
            $__ticket = $__dados["ticket"];
            $__arquivos = is_array($__dados["arquivos"] ?? null) ? $__dados["arquivos"] : [];
        }
    }

    $__status = $__ticket["status"] ?? "";
    $__badge = $__status === "resolvido"
        ? '<span class="label label-success">Resolvido</span>'
        : '<span class="label label-warning">Aberto</span>';

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
                            <tr><th>Usuário</th><td><?= htmlspecialchars(strval($__ticket["user_nome"] ?? "")) ?> (<?= htmlspecialchars(strval($__ticket["user_login"] ?? "")) ?>)</td></tr>
                            <tr><th>Data de abertura</th><td><?= htmlspecialchars(strval($__ticket["created_at"] ?? "")) ?></td></tr>
                            <tr><th>Status</th><td><?= $__badge ?></td></tr>
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

                <?php if (!empty($__arquivos)): ?>
                    <h4><i class="fa fa-image"></i> Imagens anexadas (<?= count($__arquivos) ?>)</h4>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;">
                        <?php foreach ($__arquivos as $__a): ?>
                            <a href="imagem.php?id=<?= $__id ?>&arquivo=<?= (int) ($__a["id"] ?? 0) ?>" target="_blank" title="<?= htmlspecialchars(strval($__a["nome_original"] ?? "")) ?>">
                                <div style="border:1px solid #ddd;border-radius:6px;overflow:hidden;width:150px;text-align:center;">
                                    <img src="imagem.php?id=<?= $__id ?>&arquivo=<?= (int) ($__a["id"] ?? 0) ?>"
                                         style="width:150px;height:110px;object-fit:cover;display:block;"
                                         onerror="this.parentElement.innerHTML='<div style=\'padding:30px 8px;color:#999;\'>Sem prévia<br><small><?= htmlspecialchars(strval($__a["nome_original"] ?? ""), ENT_QUOTES) ?></small></div>';" />
                                    <div style="padding:4px;font-size:11px;color:#555;word-break:break-all;">
                                        <?= htmlspecialchars(strval($__a["nome_original"] ?? "")) ?>
                                        <br><small><?= number_format((int) ($__a["tamanho_bytes"] ?? 0) / 1024, 1, ",", ".") ?> KB</small>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted"><i class="fa fa-info-circle"></i> Este chamado não possui imagens anexadas.</p>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php rodape(); ?>
