<?php
    /* ============================================================
       Suporte — Gestão Central (apenas domínio TechPS)
       Lista os chamados de TODAS as empresas, com filtros, troca de
       status e comentários do gestor. Tudo via API externa.
       ============================================================ */
    include __DIR__ . "/../load_env.php";
    include_once __DIR__ . "/../conecta.php";
    include_once __DIR__ . "/../check_permission.php";

    $__empresaAtual = trim(strval($_ENV["CONTEX_PATH"] ?? ""), "/");
    if (strpos($__empresaAtual, "demo") === false) {
        echo "<script>alert('Acesso restrito ao domínio TechPS.'); window.location.href='" . ($_ENV["CONTEX_PATH"] ?? "") . "/batida_ponto.php';</script>";
        exit;
    }

    $__apiUrl   = rtrim(strval($_ENV["SUPORTE_API_URL"] ?? ""), "/");
    $__adminKey = strval($_ENV["SUPORTE_ADMIN_KEY"] ?? "");
    $__gestorNome  = trim(strval($_SESSION["user_tx_nome"] ?? "Gestor TechPS"));
    $__gestorLogin = trim(strval($_SESSION["user_tx_login"] ?? ""));

    if (!function_exists("gestao_requisitar")) {
        function gestao_requisitar(string $metodo, string $rota, array $query = [], array $post = []): array {
            global $__apiUrl, $__adminKey;
            $url = $__apiUrl . $rota;
            if (!empty($query)) {
                $url .= "?" . http_build_query($query);
            }
            $ch = curl_init($url);
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER     => ["x-api-key: " . $__adminKey],
            ];
            if ($metodo === "POST") {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = http_build_query($post);
            }
            curl_setopt_array($ch, $options);
            $resposta = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $json = json_decode((string) $resposta, true);
            return [
                "ok"    => $httpCode >= 200 && $httpCode < 300 && is_array($json) && !empty($json["ok"]),
                "http"  => $httpCode,
                "dados" => is_array($json) ? $json : [],
            ];
        }
    }

    // ── Ações (status / comentário) ────────────────────────────────────
    $__msg = "";
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $acao = $_POST["acao"] ?? "";
        if ($acao === "status") {
            $id = (int) ($_POST["id"] ?? 0);
            $novoStatus = $_POST["status"] ?? "";
            if ($id > 0 && ($novoStatus === "aberto" || $novoStatus === "resolvido")) {
                $res = gestao_requisitar("POST", "/suporte/tickets/{$id}/status", [], ["status" => $novoStatus]);
                $__msg = $res["ok"] ? "Status do chamado #{$id} atualizado." : "Erro ao atualizar o status. " . ($res["dados"]["msg"] ?? "");
            }
        } elseif ($acao === "comentario") {
            $id = (int) ($_POST["id"] ?? 0);
            $texto = trim(strval($_POST["texto"] ?? ""));
            if ($id > 0 && $texto !== "") {
                $res = gestao_requisitar("POST", "/suporte/tickets/{$id}/comentarios", [], [
                    "texto"       => $texto,
                    "autor"       => $__gestorNome,
                    "autor_login" => $__gestorLogin,
                ]);
                $__msg = $res["ok"] ? "Comentário adicionado ao chamado #{$id}." : "Erro ao adicionar comentário. " . ($res["dados"]["msg"] ?? "");
            }
        }
        if ($__msg !== "") {
            $__url = $_SERVER["REQUEST_URI"] ?? "";
            echo "<script>alert(" . json_encode($__msg) . "); window.location.href='" . htmlspecialchars($__url, ENT_QUOTES) . "';</script>";
            exit;
        }
    }

    // ── Empresas para o filtro (via API) ───────────────────────────────
    $__resEmpresas = gestao_requisitar("GET", "/suporte/empresas");
    $__empresas = $__resEmpresas["ok"] ? ($__resEmpresas["dados"]["empresas"] ?? []) : [];

    // ── Modo detalhe ────────────────────────────────────────────────────
    $__verId = (int) ($_GET["id"] ?? 0);

    cabecalho("Gestão de Suporte");
?>

<div class="row">
    <div class="col-md-12">
        <div class="portlet light bordered">
            <div class="portlet-title">
                <div class="caption">
                    <i class="fa fa-life-ring font-blue"></i>
                    <span class="caption-subject bold uppercase"><?= $__verId > 0 ? "Chamado #" . $__verId : "Gestão de Suporte" ?></span>
                    <span class="caption-helper"><?= $__verId > 0 ? "Domínio TechPS" : "Chamados de todas as empresas" ?></span>
                </div>
                <?php if ($__verId > 0): ?>
                    <div class="actions"><a href="gestao.php" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Voltar</a></div>
                <?php endif; ?>
            </div>
            <div class="portlet-body">

<?php if ($__verId > 0): ?>
<?php
    $__res = gestao_requisitar("GET", "/suporte/tickets/{$__verId}");
    $__ticket = $__res["ok"] ? ($__res["dados"]["ticket"] ?? []) : [];
    $__arquivos = $__res["ok"] ? ($__res["dados"]["arquivos"] ?? []) : [];
    $__comentarios = $__res["ok"] ? ($__res["dados"]["comentarios"] ?? []) : [];

    if (empty($__ticket)): ?>
                <div class="alert alert-danger">Chamado não encontrado ou falha na API de suporte.</div>
    <?php else: ?>
        <?php
            $__status = $__ticket["status"] ?? "aberto";
            $__badge = $__status === "resolvido"
                ? '<span class="label label-success">Resolvido</span>'
                : '<span class="label label-warning">Aberto</span>';
        ?>
        <table class="table table-striped table-bordered">
            <tr><th style="width:140px;">Empresa</th><td><?= htmlspecialchars(strval($__ticket["empresa_key"] ?? "")) ?> — <?= htmlspecialchars(strval($__ticket["empresa_nome"] ?? "")) ?></td></tr>
            <tr><th>Usuário</th><td><?= htmlspecialchars(strval($__ticket["user_nome"] ?? "")) ?> (<?= htmlspecialchars(strval($__ticket["user_login"] ?? "")) ?>)</td></tr>
            <tr><th>Data de abertura</th><td><?= htmlspecialchars(strval($__ticket["created_at"] ?? "")) ?></td></tr>
            <tr><th>Status</th><td><?= $__badge ?></td></tr>
            <tr><th>Página</th><td style="word-break:break-all;"><?= htmlspecialchars(strval($__ticket["pagina_url"] ?? "")) ?></td></tr>
        </table>

        <div class="well">
            <h4 style="margin-top:0;">Descrição do problema</h4>
            <p style="white-space:pre-wrap;"><?= htmlspecialchars(strval($__ticket["descricao"] ?? "")) ?></p>
        </div>

        <?php if (!empty($__arquivos)): ?>
            <h4><i class="fa fa-image"></i> Imagens anexadas (<?= count($__arquivos) ?>)</h4>
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                <?php foreach ($__arquivos as $__a): ?>
                    <a href="imagem_gestao.php?id=<?= $__verId ?>&arquivo=<?= (int) ($__a["id"] ?? 0) ?>" target="_blank">
                        <div style="border:1px solid #ddd;border-radius:6px;overflow:hidden;width:150px;text-align:center;">
                            <img src="imagem_gestao.php?id=<?= $__verId ?>&arquivo=<?= (int) ($__a["id"] ?? 0) ?>"
                                 style="width:150px;height:110px;object-fit:cover;display:block;"
                                 onerror="this.parentElement.innerHTML='<div style=\'padding:30px 8px;color:#999;\'>Sem prévia<br><small><?= htmlspecialchars(strval($__a["nome_original"] ?? ""), ENT_QUOTES) ?></small></div>';" />
                            <div style="padding:4px;font-size:11px;color:#555;word-break:break-all;">
                                <?= htmlspecialchars(strval($__a["nome_original"] ?? "")) ?><br>
                                <small><?= number_format((int) ($__a["tamanho_bytes"] ?? 0) / 1024, 1, ",", ".") ?> KB</small>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Comentários -->
        <h4 style="margin-top:25px;"><i class="fa fa-comments"></i> Comentários (<?= count($__comentarios) ?>)</h4>
        <?php if (empty($__comentarios)): ?>
            <p class="text-muted"><i class="fa fa-info-circle"></i> Nenhum comentário ainda.</p>
        <?php endif; ?>
        <?php foreach ($__comentarios as $__c): ?>
            <?php
                $__ehEmpresa = (strval($__c["autor_tipo"] ?? "") === "empresa");
                $__bg = $__ehEmpresa ? "#e8f0fe" : "#f9f9f9";
            ?>
            <div style="border:1px solid #ddd;border-radius:6px;padding:10px 12px;margin-bottom:8px;background:<?= $__bg ?>;">
                <div style="font-size:12px;color:#888;margin-bottom:4px;">
                    <i class="fa fa-user-circle"></i> <strong><?= htmlspecialchars(strval($__c["autor"] ?? "")) ?></strong>
                    <span class="label label-info" style="margin-left:6px;"><?= htmlspecialchars(strval($__c["autor_tipo"] ?? "")) ?></span>
                    <span style="margin-left:8px;"><?= htmlspecialchars(strval($__c["created_at"] ?? "")) ?></span>
                </div>
                <div style="white-space:pre-wrap;"><?= htmlspecialchars(strval($__c["texto"] ?? "")) ?></div>
            </div>
        <?php endforeach; ?>

        <!-- Formulário de comentário (gestor) -->
        <form method="post" style="margin-top:15px;">
            <input type="hidden" name="acao" value="comentario" />
            <input type="hidden" name="id" value="<?= $__verId ?>" />
            <textarea name="texto" rows="3" maxlength="1000" required placeholder="Escreva um comentário para a empresa..." style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;resize:vertical;box-sizing:border-box;"></textarea>
            <div style="margin-top:8px;display:flex;gap:8px;justify-content:flex-end;">
                <button type="submit" class="btn blue"><i class="fa fa-comment"></i> Adicionar comentário</button>
            </div>
        </form>

        <!-- Troca de status -->
        <form method="post" style="margin-top:10px;">
            <input type="hidden" name="acao" value="status" />
            <input type="hidden" name="id" value="<?= $__verId ?>" />
            <?php if ($__status === "aberto"): ?>
                <input type="hidden" name="status" value="resolvido" />
                <button type="submit" class="btn btn-success" onclick="return confirm('Marcar como resolvido?');"><i class="fa fa-check"></i> Marcar como resolvido</button>
            <?php else: ?>
                <input type="hidden" name="status" value="aberto" />
                <button type="submit" class="btn btn-warning" onclick="return confirm('Reabrir chamado?');"><i class="fa fa-undo"></i> Reabrir chamado</button>
            <?php endif; ?>
        </form>
    <?php endif; ?>

<?php else: ?>
<?php
    // ── Listagem (todas as empresas) ────────────────────────────────
    $__fEmpresa = trim(strval($_GET["empresa"] ?? ""));
    $__fStatus  = trim(strval($_GET["status"] ?? ""));
    $__fInicio  = trim(strval($_GET["data_inicio"] ?? ""));
    $__fFim     = trim(strval($_GET["data_fim"] ?? ""));
    $__fPagina  = max((int) ($_GET["pagina"] ?? 1), 1);

    $__queryFiltro = ["pagina" => $__fPagina, "limit" => 25];
    if ($__fEmpresa !== "") $__queryFiltro["empresa"] = $__fEmpresa;
    if ($__fStatus === "aberto" || $__fStatus === "resolvido") $__queryFiltro["status"] = $__fStatus;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $__fInicio)) $__queryFiltro["data_inicio"] = $__fInicio;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $__fFim)) $__queryFiltro["data_fim"] = $__fFim;

    $__res = gestao_requisitar("GET", "/suporte/tickets", $__queryFiltro);
    $__tickets = $__res["ok"] ? ($__res["dados"]["tickets"] ?? []) : [];
    $__total = $__res["ok"] ? (int) ($__res["dados"]["total"] ?? 0) : 0;
    $__paginas = max((int) ceil($__total / 25), 1);

    function gestao_manter(string $nome, string $valor, array $extra = []): string {
        $params = ["empresa" => $valor] + $extra;
        return "?" . http_build_query(array_filter($params, "strlen"));
    }
?>
                <?php if (!$__res["ok"]): ?>
                    <div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> Não foi possível consultar a API de suporte.</div>
                <?php endif; ?>

                <form method="get" class="form-inline" style="margin-bottom:15px;">
                    <div class="form-group" style="margin-right:10px;">
                        <label style="margin-right:5px;">Empresa</label>
                        <select name="empresa" class="form-control">
                            <option value="">Todas</option>
                            <?php foreach ($__empresas as $__e): ?>
                                <option value="<?= htmlspecialchars(strval($__e["empresa_key"] ?? "")) ?>" <?= ($__fEmpresa === strval($__e["empresa_key"] ?? "")) ? "selected" : "" ?>>
                                    <?= htmlspecialchars(strval($__e["empresa_nome"] ?? $__e["empresa_key"] ?? "")) ?> (<?= htmlspecialchars(strval($__e["empresa_key"] ?? "")) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-right:10px;">
                        <label style="margin-right:5px;">Status</label>
                        <select name="status" class="form-control">
                            <option value="">Todos</option>
                            <option value="aberto" <?= ($__fStatus === "aberto") ? "selected" : "" ?>>Aberto</option>
                            <option value="resolvido" <?= ($__fStatus === "resolvido") ? "selected" : "" ?>>Resolvido</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-right:10px;">
                        <label style="margin-right:5px;">De</label>
                        <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($__fInicio) ?>" />
                    </div>
                    <div class="form-group" style="margin-right:10px;">
                        <label style="margin-right:5px;">Até</label>
                        <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($__fFim) ?>" />
                    </div>
                    <button type="submit" class="btn blue"><i class="fa fa-search"></i> Filtrar</button>
                    <a href="gestao.php" class="btn btn-default">Limpar</a>
                    <span class="pull-right" style="padding-top:7px;"><strong><?= $__total ?></strong> chamado(s)</span>
                </form>

                <table class="table table-striped table-hover table-bordered">
                    <thead>
                        <tr>
                            <th style="width:70px;">Nº</th>
                            <th>Empresa</th>
                            <th>Usuário</th>
                            <th>Página</th>
                            <th>Descrição</th>
                            <th style="width:100px;">Status</th>
                            <th style="width:150px;">Data</th>
                            <th style="width:90px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($__tickets)): ?>
                            <tr><td colspan="8" class="text-center">Nenhum chamado encontrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($__tickets as $__t): ?>
                            <?php
                                $__desc = trim(strval($__t["descricao"] ?? ""));
                                $__descCurta = mb_strlen($__desc, "UTF-8") > 60 ? mb_substr($__desc, 0, 60, "UTF-8") . "…" : $__desc;
                                $__statusT = $__t["status"] ?? "aberto";
                                $__badgeT = $__statusT === "resolvido"
                                    ? '<span class="label label-success">Resolvido</span>'
                                    : '<span class="label label-warning">Aberto</span>';
                            ?>
                            <tr>
                                <td>#<?= (int) ($__t["id"] ?? 0) ?></td>
                                <td><?= htmlspecialchars(strval($__t["empresa_key"] ?? "")) ?></td>
                                <td>
                                    <?= htmlspecialchars(strval($__t["user_nome"] ?? "")) ?>
                                    <br><small class="text-muted"><?= htmlspecialchars(strval($__t["user_login"] ?? "")) ?></small>
                                </td>
                                <td style="max-width:200px;word-break:break-all;"><small><?= htmlspecialchars(strval($__t["pagina_url"] ?? "")) ?></small></td>
                                <td><?= htmlspecialchars($__descCurta) ?></td>
                                <td><?= $__badgeT ?></td>
                                <td><?= htmlspecialchars(strval($__t["created_at"] ?? "")) ?></td>
                                <td><a href="gestao.php?id=<?= (int) ($__t["id"] ?? 0) ?>" class="btn btn-xs blue"><i class="fa fa-cog"></i> Gerir</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($__paginas > 1): ?>
                    <div class="text-center">
                        <ul class="pagination">
                            <?php for ($__p = 1; $__p <= $__paginas; $__p++): ?>
                                <li class="<?= ($__p === $__fPagina) ? "active" : "" ?>">
                                    <a href="<?= gestao_manter("empresa", $__fEmpresa, ["status" => $__fStatus, "data_inicio" => $__fInicio, "data_fim" => $__fFim, "pagina" => $__p]) ?>"><?= $__p ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </div>
                <?php endif; ?>
<?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php rodape(); ?>
