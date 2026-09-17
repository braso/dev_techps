<?php
    /* ============================================================
       Suporte — Chamados Técnicos
       Lista os chamados do banco externo (API suporte) com filtros,
       paginação e troca de status. Aberto a todos os usuários logados.
       ============================================================ */
    include __DIR__ . "/../load_env.php";
    include_once __DIR__ . "/../conecta.php";
    include_once __DIR__ . "/../check_permission.php";
    include_once __DIR__ . "/_datas.php";

    $__apiUrl   = rtrim(strval($_ENV["SUPORTE_API_URL"] ?? ""), "/");
    $__adminKey = strval($_ENV["SUPORTE_ADMIN_KEY"] ?? "");
    $__empresaAtual = trim(strval($_ENV["CONTEX_PATH"] ?? ""), "/");

    function suporte_requisitar(string $metodo, string $rota, array $query = [], array $post = []): array {
        global $__apiUrl, $__adminKey;
        $url = $__apiUrl . $rota;
        if (!empty($query)) {
            $url .= "?" . http_build_query($query);
        }
        $ch = curl_init($url);
        $headers = ["x-api-key: " . $__adminKey];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => $headers,
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
            "ok"      => $httpCode >= 200 && $httpCode < 300 && is_array($json) && !empty($json["ok"]),
            "http"    => $httpCode,
            "dados"   => is_array($json) ? $json : [],
        ];
    }

    // ── Ações ─────────────────────────────────────────────────────────
    // Campo "sup_acao" de propósito: o campo "acao" é interceptado pelo
    // dispatcher legado de contex20/funcoes.php (eval + exit).
    $__msg = "";
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $acao = $_POST["sup_acao"] ?? "";
        if ($acao === "status") {
            $id = (int) ($_POST["id"] ?? 0);
            $novoStatus = $_POST["status"] ?? "";
            if ($id > 0 && ($novoStatus === "aberto" || $novoStatus === "fechado")) {
                $res = suporte_requisitar("POST", "/suporte/tickets/{$id}/status", [], ["status" => $novoStatus]);
                $__msg = $res["ok"] ? "Status do chamado #{$id} atualizado." : "Erro ao atualizar o status. " . ($res["dados"]["msg"] ?? "");
            }
        }
    }

    // ── Filtros ───────────────────────────────────────────────────────
    // Isolamento por tenant: cada domínio vê apenas os chamados da própria empresa.
    $__fEmpresa = $__empresaAtual;
    $__fStatus  = trim(strval($_GET["status"] ?? ""));
    $__fInicio  = trim(strval($_GET["data_inicio"] ?? ""));
    $__fFim     = trim(strval($_GET["data_fim"] ?? ""));
    $__fPagina  = max((int) ($_GET["pagina"] ?? 1), 1);

    $__queryFiltro = ["empresa" => $__fEmpresa, "pagina" => $__fPagina, "limit" => 25];
    $__statusPermitidos = ["aberto", "em_analise", "em_desenvolvimento", "desenvolvimento_interno", "corrigido", "fechado"];
    if (in_array($__fStatus, $__statusPermitidos, true)) $__queryFiltro["status"] = $__fStatus;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $__fInicio)) $__queryFiltro["data_inicio"] = $__fInicio;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $__fFim)) $__queryFiltro["data_fim"] = $__fFim;

    $__res = suporte_requisitar("GET", "/suporte/tickets", $__queryFiltro);
    $__tickets = $__res["ok"] ? ($__res["dados"]["tickets"] ?? []) : [];
    $__total = $__res["ok"] ? (int) ($__res["dados"]["total"] ?? 0) : 0;
    $__paginas = max((int) ceil($__total / 25), 1);

    function suporte_manter(string $nome, string $valor, array $extra = []): string {
        $params = ["empresa" => $valor] + $extra;
        return "?" . http_build_query(array_filter($params, "strlen"));
    }

    cabecalho("Chamados de Suporte");
?>

<div class="row">
    <div class="col-md-12">
        <div class="portlet light bordered">
            <div class="portlet-title">
                <div class="caption">
                    <i class="fa fa-life-ring font-blue"></i>
                    <span class="caption-subject bold uppercase">Chamados de Suporte</span>
                    <span class="caption-helper"><?= $__total ?> chamado(s) encontrado(s)</span>
                </div>
            </div>
            <div class="portlet-body">

                <?php if ($__msg !== ""): ?>
                    <div class="alert alert-success"><i class="fa fa-check"></i> <?= htmlspecialchars($__msg) ?></div>
                <?php endif; ?>

                <?php if (!$__res["ok"]): ?>
                    <div class="alert alert-danger">
                        <i class="fa fa-exclamation-triangle"></i>
                        Não foi possível consultar a API de suporte.
                        <?php if (!empty($__res["dados"]["msg"])): ?> <?= htmlspecialchars($__res["dados"]["msg"]) ?><?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Filtros -->
                <form method="get" class="form-inline" style="margin-bottom:15px;">
                    <div class="form-group" style="margin-right:10px;">
                        <label style="margin-right:5px;">Empresa</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($__empresaAtual) ?>" readonly style="background:#f5f5f5;" />
                    </div>
                    <div class="form-group" style="margin-right:10px;">
                        <label style="margin-right:5px;">Status</label>
                        <select name="status" class="form-control">
                            <option value="">Todos</option>
                            <?php foreach (["aberto" => "Aberto", "em_analise" => "Em Análise", "em_desenvolvimento" => "Em Desenvolvimento", "desenvolvimento_interno" => "Desenvolvimento Interno", "corrigido" => "Corrigido", "fechado" => "Fechado"] as $__sk => $__sl): ?>
                                <option value="<?= $__sk ?>" <?= ($__fStatus === $__sk) ? "selected" : "" ?>><?= $__sl ?></option>
                            <?php endforeach; ?>
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
                    <a href="?" class="btn btn-default">Limpar</a>
                </form>

                <!-- Tabela -->
                <table class="table table-striped table-hover table-bordered">
                    <thead>
                        <tr>
                            <th style="width:70px;">Nº</th>
                            <th>Empresa</th>
                            <th>Setor</th>
                            <th>Usuário</th>
                            <th>Página</th>
                            <th>Descrição</th>
                            <th style="width:100px;">Status</th>
                            <th style="width:150px;">Data</th>
                            <th style="width:150px;">Fechado em</th>
                            <th style="width:180px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($__tickets)): ?>
                            <tr><td colspan="10" class="text-center">Nenhum chamado encontrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($__tickets as $__t): ?>
                            <?php
                                $__desc = trim(strval($__t["descricao"] ?? ""));
                                $__descCurta = mb_strlen($__desc, "UTF-8") > 80 ? mb_substr($__desc, 0, 80, "UTF-8") . "…" : $__desc;
                                $__status = strval($__t["status"] ?? "aberto");
                                $__badgeMap = [
                                    "aberto"             => '<span class="label" style="background:#f39c12;">Aberto</span>',
                                    "em_analise"         => '<span class="label" style="background:#8e44ad;">Em Análise</span>',
                                    "em_desenvolvimento" => '<span class="label" style="background:#2980b9;">Em Desenvolvimento</span>',
                                    "desenvolvimento_interno" => '<span class="label" style="background:#d35400;">Desenvolvimento Interno</span>',
                                    "corrigido"          => '<span class="label" style="background:#16a085;">Corrigido</span>',
                                    "fechado"            => '<span class="label" style="background:#27ae60;">Fechado</span>',
                                ];
                                $__badge = $__badgeMap[$__status] ?? '<span class="label label-default">' . htmlspecialchars($__status) . '</span>';
                            ?>
                            <tr>
                                <td>#<?= (int) ($__t["id"] ?? 0) ?></td>
                                <td><?= htmlspecialchars(strval($__t["empresa_key"] ?? "")) ?></td>
                                <td><?= htmlspecialchars(strval($__t["setor_nome"] ?? "") ?: "—") ?></td>
                                <td>
                                    <?= htmlspecialchars(strval($__t["user_nome"] ?? "")) ?>
                                    <br><small class="text-muted"><?= htmlspecialchars(strval($__t["user_login"] ?? "")) ?></small>
                                </td>
                                <td style="max-width:220px;word-break:break-all;"><small><?= htmlspecialchars(strval($__t["pagina_url"] ?? "")) ?></small></td>
                                <td><?= htmlspecialchars($__descCurta) ?></td>
                                <td><?= $__badge ?></td>
                                <td><?= htmlspecialchars(suporte_fmt_data(strval($__t["created_at"] ?? ""))) ?></td>
                                <td><?= htmlspecialchars(suporte_fmt_data(strval($__t["fechado_em"] ?? "")) ?: "—") ?></td>
                                <td>
                                    <a href="detalhe.php?id=<?= (int) ($__t["id"] ?? 0) ?>" class="btn btn-xs blue"><i class="fa fa-eye"></i> Ver</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Paginação -->
                <?php if ($__paginas > 1): ?>
                    <div class="text-center">
                        <ul class="pagination">
                            <?php for ($__p = 1; $__p <= $__paginas; $__p++): ?>
                                <li class="<?= ($__p === $__fPagina) ? "active" : "" ?>">
                                    <a href="<?= suporte_manter("empresa", $__fEmpresa, ["status" => $__fStatus, "data_inicio" => $__fInicio, "data_fim" => $__fFim, "pagina" => $__p]) ?>"><?= $__p ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php rodape(); ?>
