<?php
    /* ============================================================
       Suporte — Meus Chamados
       Visão do usuário comum: os chamados que ele mesmo abriu e,
       se ele for o responsável cadastrado de algum funcionário
       (cadastro_funcionario), os chamados abertos por esses
       funcionários também. Sem ações de gestão — apenas acompanhar
       e entrar no detalhe (que permite responder).
       ============================================================ */
    include __DIR__ . "/../load_env.php";
    include_once __DIR__ . "/../conecta.php";
    include_once __DIR__ . "/_datas.php";

    $__apiUrl   = rtrim(strval($_ENV["SUPORTE_API_URL"] ?? ""), "/");
    $__adminKey = strval($_ENV["SUPORTE_ADMIN_KEY"] ?? "");
    $__empresaAtual = trim(strval($_ENV["CONTEX_PATH"] ?? ""), "/");
    $__meuUid = strval($_SESSION["user_nb_id"] ?? "");

    function suporte_meus_requisitar(string $rota, array $query = []): array {
        global $__apiUrl, $__adminKey;
        $url = $__apiUrl . $rota;
        if (!empty($query)) {
            $url .= "?" . http_build_query($query);
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ["x-api-key: " . $__adminKey],
        ]);
        $resposta = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string) $resposta, true);
        return [
            "ok"    => $httpCode >= 200 && $httpCode < 300 && is_array($json) && !empty($json["ok"]),
            "dados" => is_array($json) ? $json : [],
        ];
    }

    // ── Funcionários sob a responsabilidade do usuário logado (se houver) ──
    $__equipeUids = [];
    if ($__meuUid !== "") {
        $__minhaEntidadeResult = query("SELECT user_nb_entidade FROM user WHERE user_nb_id = ?", "i", [(int) $__meuUid]);
        $__minhaEntidade = $__minhaEntidadeResult ? mysqli_fetch_assoc($__minhaEntidadeResult) : null;
        $__minhaEntidadeId = (int) ($__minhaEntidade["user_nb_entidade"] ?? 0);

        if ($__minhaEntidadeId > 0) {
            $__subEntidadesResult = query("SELECT enti_nb_id FROM entidade WHERE enti_respFuncionario_id = ?", "i", [$__minhaEntidadeId]);
            $__subEntidades = $__subEntidadesResult ? (mysqli_fetch_all($__subEntidadesResult, MYSQLI_ASSOC) ?: []) : [];
            $__subIds = array_map(fn($r) => (int) ($r["enti_nb_id"] ?? 0), $__subEntidades);
            $__subIds = array_filter($__subIds, fn($v) => $v > 0);

            if (!empty($__subIds)) {
                $__placeholders = implode(",", array_fill(0, count($__subIds), "?"));
                $__tipos = str_repeat("i", count($__subIds));
                $__subUsersResult = query(
                    "SELECT user_nb_id FROM user WHERE user_nb_entidade IN ({$__placeholders})",
                    $__tipos,
                    array_values($__subIds)
                );
                $__subUsers = $__subUsersResult ? (mysqli_fetch_all($__subUsersResult, MYSQLI_ASSOC) ?: []) : [];
                foreach ($__subUsers as $__su) {
                    $__uid = strval($__su["user_nb_id"] ?? "");
                    if ($__uid !== "" && $__uid !== $__meuUid) { $__equipeUids[] = $__uid; }
                }
            }
        }
    }

    $__statusLabel = [
        "aberto"             => '<span class="label label-warning">Aberto</span>',
        "em_analise"         => '<span class="label label-default" style="background:#8e44ad;">Em Análise</span>',
        "em_andamento"       => '<span class="label label-info">Em Andamento</span>',
        "aguardando_cliente" => '<span class="label label-primary">Aguardando retorno</span>',
        "resolvido"          => '<span class="label label-success">Concluído</span>',
        "cancelado"          => '<span class="label label-default">Cancelado</span>',
        "reaberto"           => '<span class="label label-warning">Reaberto</span>',
        "encaminhado_ssi"    => '<span class="label label-danger">Encaminhado a SSI</span>',
        "teste_interno"      => '<span class="label label-default" style="background:#16a085;">Teste Interno</span>',
        "aguardando_atualizacao" => '<span class="label label-default" style="background:#e67e22;">Aguardando Atualização</span>',
    ];

    function suporte_meus_badge(array $labels, string $status): string {
        return $labels[$status] ?? ('<span class="label label-default">' . htmlspecialchars($status) . '</span>');
    }

    $__resMeus = $__meuUid !== ""
        ? suporte_meus_requisitar("/suporte/tickets", ["empresa" => $__empresaAtual, "user_ids" => $__meuUid, "limit" => 100])
        : ["ok" => false, "dados" => []];
    $__meusChamados = $__resMeus["ok"] ? ($__resMeus["dados"]["tickets"] ?? []) : [];

    $__equipeChamados = [];
    if (!empty($__equipeUids)) {
        $__resEquipe = suporte_meus_requisitar("/suporte/tickets", ["empresa" => $__empresaAtual, "user_ids" => implode(",", $__equipeUids), "limit" => 100]);
        $__equipeChamados = $__resEquipe["ok"] ? ($__resEquipe["dados"]["tickets"] ?? []) : [];
    }

    function suporte_meus_tabela(array $chamados, array $statusLabel): void {
        if (empty($chamados)) {
            echo '<p class="text-muted"><i class="fa fa-info-circle"></i> Nenhum chamado encontrado.</p>';
            return;
        }
        ?>
        <table class="table table-striped table-hover table-bordered">
            <thead>
                <tr>
                    <th style="width:70px;">Nº</th>
                    <th>Usuário</th>
                    <th>Descrição</th>
                    <th style="width:130px;">Status</th>
                    <th style="width:150px;">Aberto em</th>
                    <th style="width:90px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($chamados as $__t): ?>
                    <?php
                        $__desc = trim(strval($__t["descricao"] ?? ""));
                        $__descCurta = mb_strlen($__desc, "UTF-8") > 90 ? mb_substr($__desc, 0, 90, "UTF-8") . "…" : $__desc;
                    ?>
                    <tr>
                        <td>#<?= (int) ($__t["id"] ?? 0) ?></td>
                        <td>
                            <?= htmlspecialchars(strval($__t["user_nome"] ?? "")) ?>
                            <br><small class="text-muted"><?= htmlspecialchars(strval($__t["user_login"] ?? "")) ?></small>
                        </td>
                        <td><?= htmlspecialchars($__descCurta) ?></td>
                        <td><?= suporte_meus_badge($statusLabel, strval($__t["status"] ?? "aberto")) ?></td>
                        <td><?= htmlspecialchars(suporte_fmt_data(strval($__t["created_at"] ?? ""))) ?></td>
                        <td><a href="detalhe.php?id=<?= (int) ($__t["id"] ?? 0) ?>" class="btn btn-xs blue"><i class="fa fa-eye"></i> Ver</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    cabecalho("Meus Chamados de Suporte");
?>

<div class="row">
    <div class="col-md-12">
        <div class="portlet light bordered">
            <div class="portlet-title">
                <div class="caption">
                    <i class="fa fa-life-ring font-blue"></i>
                    <span class="caption-subject bold uppercase">Meus Chamados de Suporte</span>
                </div>
            </div>
            <div class="portlet-body">

                <?php if (!$__resMeus["ok"]): ?>
                    <div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> Não foi possível consultar a API de suporte.</div>
                <?php endif; ?>

                <h4><i class="fa fa-user"></i> Chamados que eu abri</h4>
                <?php suporte_meus_tabela($__meusChamados, $__statusLabel); ?>

                <?php if (!empty($__equipeUids)): ?>
                    <h4 style="margin-top:30px;"><i class="fa fa-users"></i> Chamados da minha equipe</h4>
                    <p class="text-muted" style="margin-top:-6px;font-size:12px;">Chamados abertos pelos funcionários dos quais você é o responsável cadastrado.</p>
                    <?php suporte_meus_tabela($__equipeChamados, $__statusLabel); ?>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php rodape(); ?>
