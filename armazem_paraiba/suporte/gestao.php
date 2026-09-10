<?php
    /* ============================================================
       Suporte — Gestão Central (apenas domínio TechPS)
       Lista os chamados de TODAS as empresas, com filtros, troca de
       status e comentários do gestor. Tudo via API externa.
       ============================================================ */
    include __DIR__ . "/../load_env.php";
    include_once __DIR__ . "/../conecta.php";
    include_once __DIR__ . "/../check_permission.php";
    include_once __DIR__ . "/_timeline.php";
    include_once __DIR__ . "/_anexos.php";
    include_once __DIR__ . "/_datas.php";

    $__empresaAtual = trim(strval($_ENV["CONTEX_PATH"] ?? ""), "/");
    // Gestão central: domínios TechPS (produção) e Demo (desenvolvimento).
    if (strpos($__empresaAtual, "techps") === false && strpos($__empresaAtual, "demo") === false) {
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

    // SLA do chamado, a partir da prioridade + config (sla_<prioridade>_horas). Sem SLA
    // configurado para a prioridade, ou created_at inválido, cai em "Sem SLA".
    if (!function_exists("suporte_sla_status")) {
        function suporte_sla_status(string $prioridade, string $createdAt, string $fechadoEm, array $slaConfig): array {
            $bruto = trim(strval($slaConfig["sla_" . $prioridade . "_horas"] ?? ""));
            $criadoTs = $createdAt !== "" ? strtotime($createdAt) : false;
            if ($bruto === "" || !ctype_digit($bruto) || $criadoTs === false) {
                return ["label" => "Sem SLA", "classe" => "default", "horas" => null];
            }
            $slaHoras = (int) $bruto;
            $referenciaTs = $fechadoEm !== "" ? (strtotime($fechadoEm) ?: time()) : time();
            $horasDecorridas = ($referenciaTs - $criadoTs) / 3600;
            return $horasDecorridas <= $slaHoras
                ? ["label" => "Dentro do prazo", "classe" => "success", "horas" => $slaHoras]
                : ["label" => "Atrasado", "classe" => "danger", "horas" => $slaHoras];
        }
    }

    // ── Ações (aceitar / tipo / status / comentário) ───────────────────
    // Campo "sup_acao" de propósito: o campo "acao" é interceptado pelo
    // dispatcher legado de contex20/funcoes.php (eval + exit).
    $__msg = "";
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $acao = $_POST["sup_acao"] ?? "";
        $id = (int) ($_POST["id"] ?? 0);
        if ($acao === "aceitar" && $id > 0) {
            $res = gestao_requisitar("POST", "/suporte/tickets/{$id}/aceitar", [], [
                "atendente"       => $__gestorNome,
                "atendente_login" => $__gestorLogin,
            ]);
            $__msg = $res["ok"] ? "Chamado #{$id} aceito — em atendimento." : "Erro ao aceitar o chamado. " . ($res["dados"]["msg"] ?? "");
        } elseif ($acao === "tipo" && $id > 0) {
            $tipo = $_POST["tipo"] ?? "";
            if (in_array($tipo, ["duvida", "sugestao", "bug"], true)) {
                $res = gestao_requisitar("POST", "/suporte/tickets/{$id}/tipo", [], ["tipo" => $tipo]);
                $__msg = $res["ok"] ? "Tipo do chamado #{$id} atualizado." : "Erro ao classificar. " . ($res["dados"]["msg"] ?? "");
            }
        } elseif ($acao === "prioridade" && $id > 0) {
            // Independente do status/tipo — pode ser trocada a qualquer momento do fluxo.
            $prioridade = $_POST["prioridade"] ?? "";
            if (in_array($prioridade, ["baixa", "media", "alta", "urgente"], true)) {
                $res = gestao_requisitar("POST", "/suporte/tickets/{$id}/prioridade", [], ["prioridade" => $prioridade]);
                $__msg = $res["ok"] ? "Prioridade do chamado #{$id} atualizada." : "Erro ao alterar prioridade. " . ($res["dados"]["msg"] ?? "");
            }
        } elseif ($acao === "status" && $id > 0) {
            $novoStatus = $_POST["status"] ?? "";
            $statusPermitidos = ["aberto", "em_analise", "em_andamento", "aguardando_cliente", "resolvido", "cancelado", "reaberto", "encaminhado_ssi", "teste_interno", "aguardando_atualizacao"];
            if (in_array($novoStatus, $statusPermitidos, true)) {
                $post = ["status" => $novoStatus];
                if ($novoStatus === "encaminhado_ssi") {
                    $post["ssi_prioridade"] = ($_POST["ssi_prioridade"] ?? "") === "urgente" ? "urgente" : "proxima_atualizacao";
                }
                $res = gestao_requisitar("POST", "/suporte/tickets/{$id}/status", [], $post);
                $__msg = $res["ok"] ? "Status do chamado #{$id} atualizado." : "Erro ao atualizar o status. " . ($res["dados"]["msg"] ?? "");
            }
        } elseif ($acao === "comentario" && $id > 0) {
            $texto = trim(strval($_POST["texto"] ?? ""));
            if ($texto !== "") {
                $res = gestao_requisitar("POST", "/suporte/tickets/{$id}/comentarios", [], [
                    "texto"       => $texto,
                    "autor"       => $__gestorNome,
                    "autor_login" => $__gestorLogin,
                ]);
                $__msg = $res["ok"] ? "Comentário adicionado ao chamado #{$id}." : "Erro ao adicionar comentário. " . ($res["dados"]["msg"] ?? "");
            }
        } elseif ($acao === "atribuir" && $id > 0) {
            $res = gestao_requisitar("POST", "/suporte/tickets/{$id}/atribuir", [], [
                "atendente_id" => (int) ($_POST["atendente_id"] ?? 0),
                "autor"        => $__gestorNome,
            ]);
            $__msg = $res["ok"]
                ? strval($res["dados"]["msg"] ?? "Atendente do chamado #{$id} atualizado.")
                : "Erro ao atribuir o chamado. " . ($res["dados"]["msg"] ?? "");
        } elseif ($acao === "atendente_salvar") {
            // Os vinculos vao como lista separada por virgula — a API regrava os dois
            // escopos a partir do que chegar aqui (o que nao vier, e desvinculado).
            $__paraLista = function ($valor): string {
                return implode(",", array_map("intval", array_filter((array) $valor)));
            };
            $res = gestao_requisitar("POST", "/suporte/atendentes", [], [
                "nome"                => trim(strval($_POST["at_nome"] ?? "")),
                "email"               => trim(strval($_POST["at_email"] ?? "")),
                "login"               => trim(strval($_POST["at_login"] ?? "")),
                "origem_empresa"      => $__empresaAtual,
                "status"              => (($_POST["at_status"] ?? "ativo") === "inativo") ? "inativo" : "ativo",
                "setores_externo"     => $__paraLista($_POST["setores_externo"] ?? []),
                "setores_interno_ssi" => $__paraLista($_POST["setores_interno_ssi"] ?? []),
            ]);
            $__msg = $res["ok"] ? "Atendente salvo." : "Erro ao salvar atendente. " . ($res["dados"]["msg"] ?? "");
        } elseif ($acao === "atendente_remover") {
            $__atId = (int) ($_POST["atendente_id"] ?? 0);
            if ($__atId > 0) {
                $res = gestao_requisitar("POST", "/suporte/atendentes/{$__atId}/remover");
                $__msg = $res["ok"] ? "Atendente removido da equipe." : "Erro ao remover atendente. " . ($res["dados"]["msg"] ?? "");
            }
        } elseif ($acao === "config") {
            $emails = trim(strval($_POST["emails_notificacao"] ?? ""));
            $post = [
                "emails_notificacao" => $emails,
                "atualizado_por"     => $__gestorNome,
            ];
            foreach (["sla_baixa_horas", "sla_media_horas", "sla_alta_horas", "sla_urgente_horas"] as $__campoSla) {
                $post[$__campoSla] = trim(strval($_POST[$__campoSla] ?? ""));
            }
            $res = gestao_requisitar("POST", "/suporte/config", [], $post);
            $__msg = $res["ok"] ? "Configurações de suporte atualizadas." : "Erro ao salvar configurações. " . ($res["dados"]["msg"] ?? "");
        }
        if ($__msg !== "") {
            $__url = $_SERVER["REQUEST_URI"] ?? "";
            echo "<script>alert(" . json_encode($__msg) . "); window.location.href='" . htmlspecialchars($__url, ENT_QUOTES) . "';</script>";
            exit;
        }
    }

    // ── Empresas e setores para o filtro (via API) ─────────────────────
    $__resEmpresas = gestao_requisitar("GET", "/suporte/empresas");
    $__empresas = $__resEmpresas["ok"] ? ($__resEmpresas["dados"]["empresas"] ?? []) : [];

    $__resSetores = gestao_requisitar("GET", "/suporte/setores");
    $__setoresFiltro = $__resSetores["ok"] ? ($__resSetores["dados"]["setores"] ?? []) : [];

    // ── Configurações (e-mails de aviso + SLA por prioridade) — usadas na listagem e no
    // detalhe (badge de SLA), além da própria tela de configurações. Uma requisição só.
    $__resConfig = gestao_requisitar("GET", "/suporte/config");
    $__configAtual = $__resConfig["ok"] ? ($__resConfig["dados"]["config"] ?? []) : [];

    // ── Equipe de atendimento (atendentes vinculados aos setores) ───────
    // Atendentes ativos vinculados a um setor num escopo ("externo" | "interno_ssi").
    if (!function_exists("suporte_equipe_do_setor")) {
        function suporte_equipe_do_setor(array $atendentes, int $setorId, string $escopo): array {
            if ($setorId < 1) {
                return [];
            }
            $chave = $escopo === "interno_ssi" ? "setores_interno_ssi" : "setores_externo";
            $equipe = [];
            foreach ($atendentes as $a) {
                if (strval($a["status"] ?? "") !== "ativo") {
                    continue;
                }
                foreach ((array) ($a[$chave] ?? []) as $v) {
                    if ((int) ($v["setor_id"] ?? 0) === $setorId) {
                        $equipe[] = $a;
                        break;
                    }
                }
            }
            return $equipe;
        }
    }

    // ── Modo detalhe / configurações / equipe ───────────────────────────
    $__verId = (int) ($_GET["id"] ?? 0);
    $__verConfig = $__verId === 0 && isset($_GET["config"]);
    $__verEquipe = $__verId === 0 && !$__verConfig && isset($_GET["equipe"]);

    // A equipe alimenta o combo de responsavel (detalhe), o cadastro (tela da equipe)
    // e o filtro por atendente (listagem) — ou seja, todas as telas menos a de config.
    $__atendentes = [];
    $__euAtendenteId = 0;
    if (!$__verConfig) {
        $__resAtendentes = gestao_requisitar("GET", "/suporte/atendentes");
        $__atendentes = $__resAtendentes["ok"] ? ($__resAtendentes["dados"]["atendentes"] ?? []) : [];

        // Quem esta logado faz parte da equipe? Casa por login, e-mail ou nome — nessa
        // ordem — para o atalho "Meus atendimentos" saber qual fila e a dele.
        $__meuEmail = strtolower(trim(strval($_SESSION["user_tx_email"] ?? "")));
        foreach ($__atendentes as $__a) {
            if (strval($__a["status"] ?? "") !== "ativo") {
                continue;
            }
            $__mesmoLogin = $__gestorLogin !== "" && strval($__a["login"] ?? "") === $__gestorLogin;
            $__mesmoEmail = $__meuEmail !== "" && strtolower(strval($__a["email"] ?? "")) === $__meuEmail;
            $__mesmoNome  = strval($__a["nome"] ?? "") === $__gestorNome;
            if ($__mesmoLogin || $__mesmoEmail || $__mesmoNome) {
                $__euAtendenteId = (int) ($__a["id"] ?? 0);
                break;
            }
        }
    }

    if ($__verEquipe) {
        // Fonte de nome/e-mail: usuarios ativos do proprio dominio TechPS. O vinculo
        // com o setor mora no banco central do suporte, nao no cadastro da empresa.
        $__usuariosDominio = [];
        $__rsUsuarios = query(
            "SELECT user_tx_nome, user_tx_email, user_tx_login
             FROM user
             WHERE user_tx_status = 'ativo' AND COALESCE(user_tx_email, '') <> ''
             ORDER BY user_tx_nome ASC"
        );
        while ($__rsUsuarios && ($__u = mysqli_fetch_assoc($__rsUsuarios))) {
            $__usuariosDominio[] = $__u;
        }
    }

    if ($__verConfig) {
        $__emailsAtuais = strval($__configAtual["emails_notificacao"] ?? "");
        $__slaAtual = [
            "baixa"   => strval($__configAtual["sla_baixa_horas"] ?? ""),
            "media"   => strval($__configAtual["sla_media_horas"] ?? ""),
            "alta"    => strval($__configAtual["sla_alta_horas"] ?? ""),
            "urgente" => strval($__configAtual["sla_urgente_horas"] ?? ""),
        ];
    }

    cabecalho("Gestão de Suporte");
?>

<div class="row">
    <div class="col-md-12">
        <div class="portlet light bordered">
            <div class="portlet-title">
                <div class="caption">
                    <i class="fa fa-life-ring font-blue"></i>
                    <span class="caption-subject bold uppercase"><?= $__verId > 0 ? "Chamado #" . $__verId : ($__verConfig ? "Configurações do Suporte" : ($__verEquipe ? "Equipe de Atendimento" : "Gestão de Suporte")) ?></span>
                    <span class="caption-helper"><?= $__verId > 0 ? "Domínio TechPS" : ($__verConfig ? "Regras e aviso de chamado novo" : ($__verEquipe ? "Atendentes vinculados aos setores" : "Chamados de todas as empresas")) ?></span>
                </div>
                <div class="actions">
                    <?php if ($__verId > 0 || $__verConfig || $__verEquipe): ?>
                        <a href="gestao.php" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Voltar</a>
                    <?php else: ?>
                        <a href="dashboard.php" class="btn btn-default btn-sm"><i class="fa fa-bar-chart"></i> Dashboard</a>
                        <a href="gestao.php?equipe=1" class="btn btn-default btn-sm"><i class="fa fa-users"></i> Equipe de atendimento</a>
                        <a href="gestao.php?config=1" class="btn btn-default btn-sm"><i class="fa fa-cog"></i> Configurações</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="portlet-body">

<?php if ($__verConfig): ?>
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> Todo chamado novo chega automaticamente com status <strong>Aberto</strong>. A partir daí o fluxo recomendado é
                    <strong>Aberto → Em Análise → Em Andamento → Concluído</strong> (os status especiais — Aguardando cliente, Reaberto e Cancelado — continuam disponíveis para os casos que precisarem).
                    <br><br>
                    Chamados classificados como <strong>Bug de sistema</strong> e encaminhados à SSI seguem um fluxo próprio:
                    <strong>Encaminhado a SSI → Em Andamento → Teste Interno → Aguardando Atualização → Concluído</strong>. Não existe uma "SSI" separada para fechar — o código SSI é só uma etiqueta gravada no próprio chamado, então concluir o chamado já encerra a SSI junto.
                    <br><br>
                    <strong>Aguardando Atualização</strong> indica que a correção/melhoria já foi desenvolvida, testada e aprovada, e está apenas esperando a próxima atualização do sistema subir para produção — os envolvidos são avisados por e-mail automaticamente.
                    Procedimento de fechamento: quem finaliza o atendimento deve conferir tudo que está subindo na atualização de sistema e verificar se as correções/melhorias deste chamado realmente subiram, antes de marcar como <strong>Concluído</strong>.
                </div>

                <form method="post">
                    <input type="hidden" name="sup_acao" value="config" />
                    <div class="form-group">
                        <label><i class="fa fa-envelope"></i> E-mail(s) de aviso de chamado novo</label>
                        <input type="text" name="emails_notificacao" class="form-control" style="max-width:520px;"
                               value="<?= htmlspecialchars($__emailsAtuais) ?>"
                               placeholder="suporte@techps.com.br, outro@techps.com.br" />
                        <span class="help-block">Separe vários e-mails por vírgula. Toda vez que um chamado novo chegar, um aviso é enviado automaticamente para esses endereços.</span>
                    </div>

                    <hr style="margin:24px 0 18px;">
                    <h4 style="margin-top:0;"><i class="fa fa-clock-o"></i> SLA por prioridade</h4>
                    <p class="help-block" style="margin-top:-6px;">
                        Prazo máximo, em horas corridas, contado da abertura até a conclusão do chamado, para cada nível de prioridade.
                        Deixe em branco para não cobrar SLA nesse nível. O prazo é comparado com o momento em que o chamado foi
                        <strong>Concluído</strong> — se ainda estiver em aberto, compara com agora, pra já sinalizar quem está estourando o prazo.
                    </p>
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label><span class="label label-default">Baixa</span></label>
                                <div class="input-group">
                                    <input type="number" min="1" step="1" name="sla_baixa_horas" class="form-control" value="<?= htmlspecialchars($__slaAtual["baixa"]) ?>" placeholder="Ex.: 72" />
                                    <span class="input-group-addon">horas</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label><span class="label label-info">Média</span></label>
                                <div class="input-group">
                                    <input type="number" min="1" step="1" name="sla_media_horas" class="form-control" value="<?= htmlspecialchars($__slaAtual["media"]) ?>" placeholder="Ex.: 48" />
                                    <span class="input-group-addon">horas</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label><span class="label label-warning">Alta</span></label>
                                <div class="input-group">
                                    <input type="number" min="1" step="1" name="sla_alta_horas" class="form-control" value="<?= htmlspecialchars($__slaAtual["alta"]) ?>" placeholder="Ex.: 24" />
                                    <span class="input-group-addon">horas</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label><span class="label label-danger">Urgente</span></label>
                                <div class="input-group">
                                    <input type="number" min="1" step="1" name="sla_urgente_horas" class="form-control" value="<?= htmlspecialchars($__slaAtual["urgente"]) ?>" placeholder="Ex.: 4" />
                                    <span class="input-group-addon">horas</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn blue"><i class="fa fa-save"></i> Salvar configurações</button>
                </form>
<?php elseif ($__verEquipe): ?>
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> Cada atendente e vinculado aos setores que atende, em dois escopos:
                    <br><strong>Atendimento externo</strong> — recebe o aviso assim que um chamado novo chega naquele setor. E quem fala com o cliente.
                    <br><strong>Atendimento interno (SSI)</strong> — recebe o aviso quando o chamado do setor e classificado como bug e encaminhado a SSI. E quem desenvolve a correcao.
                    <br><br>
                    A mesma pessoa pode estar nos dois escopos, e em setores diferentes em cada um. Setor sem ninguem vinculado
                    continua caindo apenas na lista geral de e-mails de <a href="gestao.php?config=1">Configurações</a>.
                    <br>A distribuicao do chamado e manual: o gestor abre o chamado e escolhe o responsavel entre os atendentes daquele setor.
                    <br><strong>Excecao:</strong> ao encaminhar um chamado a SSI, se o setor tiver <strong>exatamente um</strong>
                    atendente interno, ele ja e atribuido automaticamente. Com dois ou mais, a escolha continua sendo do gestor.
                </div>

                <?php if (empty($__setoresFiltro)): ?>
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i> Nenhum setor disponivel no modulo de suporte ainda.
                        Marque "Disponibilizar no modulo de suporte" em <strong>Cadastro de Setor</strong> (dominio Demo) antes de montar a equipe.
                    </div>
                <?php endif; ?>

                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Atendente</th>
                            <th>E-mail</th>
                            <th>Setores — atendimento externo</th>
                            <th>Setores — atendimento interno (SSI)</th>
                            <th style="width:80px;">Status</th>
                            <th style="width:150px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($__atendentes)): ?>
                        <tr><td colspan="6" class="text-center text-muted">Nenhum atendente cadastrado. Use o formulário abaixo para montar a equipe.</td></tr>
                    <?php else: foreach ($__atendentes as $__a):
                        $__aExt = array_map(fn($v) => strval($v["setor_nome"] ?? ""), (array) ($__a["setores_externo"] ?? []));
                        $__aSsi = array_map(fn($v) => strval($v["setor_nome"] ?? ""), (array) ($__a["setores_interno_ssi"] ?? []));
                        $__aExtIds = implode(",", array_map(fn($v) => (int) ($v["setor_id"] ?? 0), (array) ($__a["setores_externo"] ?? [])));
                        $__aSsiIds = implode(",", array_map(fn($v) => (int) ($v["setor_id"] ?? 0), (array) ($__a["setores_interno_ssi"] ?? [])));
                        $__aAtivo = strval($__a["status"] ?? "ativo") === "ativo";
                    ?>
                        <tr<?= $__aAtivo ? "" : ' class="text-muted"' ?>>
                            <td><?= htmlspecialchars(strval($__a["nome"] ?? "")) ?></td>
                            <td><?= htmlspecialchars(strval($__a["email"] ?? "")) ?></td>
                            <td><?= $__aExt ? htmlspecialchars(implode(", ", $__aExt)) : '<span class="text-muted">—</span>' ?></td>
                            <td><?= $__aSsi ? htmlspecialchars(implode(", ", $__aSsi)) : '<span class="text-muted">—</span>' ?></td>
                            <td><?= $__aAtivo ? '<span class="label label-success">Ativo</span>' : '<span class="label label-default">Inativo</span>' ?></td>
                            <td>
                                <button type="button" class="btn btn-default btn-xs"
                                        onclick="editarAtendente(this)"
                                        data-nome="<?= htmlspecialchars(strval($__a["nome"] ?? ""), ENT_QUOTES) ?>"
                                        data-email="<?= htmlspecialchars(strval($__a["email"] ?? ""), ENT_QUOTES) ?>"
                                        data-login="<?= htmlspecialchars(strval($__a["login"] ?? ""), ENT_QUOTES) ?>"
                                        data-status="<?= $__aAtivo ? "ativo" : "inativo" ?>"
                                        data-externo="<?= htmlspecialchars($__aExtIds, ENT_QUOTES) ?>"
                                        data-ssi="<?= htmlspecialchars($__aSsiIds, ENT_QUOTES) ?>">
                                    <i class="fa fa-pencil"></i> Editar
                                </button>
                                <form method="post" style="display:inline-block;">
                                    <input type="hidden" name="sup_acao" value="atendente_remover" />
                                    <input type="hidden" name="atendente_id" value="<?= (int) ($__a["id"] ?? 0) ?>" />
                                    <button type="submit" class="btn btn-danger btn-xs"
                                            onclick="return confirm('Remover este atendente da equipe de suporte?');">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <hr style="margin:24px 0 18px;">
                <h4 style="margin-top:0;" id="form-atendente"><i class="fa fa-user-plus"></i> Adicionar / editar atendente</h4>
                <p class="help-block" style="margin-top:-6px;">
                    O e-mail identifica o atendente: salvar com um e-mail que ja existe atualiza o cadastro e regrava os vinculos.
                </p>

                <form method="post">
                    <input type="hidden" name="sup_acao" value="atendente_salvar" />
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Usuário do domínio TechPS</label>
                                <select class="form-control" id="at_usuario" onchange="preencherAtendente(this)">
                                    <option value="">— preencher manualmente —</option>
                                    <?php foreach ($__usuariosDominio as $__u): ?>
                                        <option value="<?= htmlspecialchars(strval($__u["user_tx_email"] ?? ""), ENT_QUOTES) ?>"
                                                data-nome="<?= htmlspecialchars(strval($__u["user_tx_nome"] ?? ""), ENT_QUOTES) ?>"
                                                data-login="<?= htmlspecialchars(strval($__u["user_tx_login"] ?? ""), ENT_QUOTES) ?>">
                                            <?= htmlspecialchars(strval($__u["user_tx_nome"] ?? "")) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="help-block">Atalho: puxa nome, e-mail e login do cadastro deste domínio.</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Nome <span style="color:#e74c3c;">*</span></label>
                                <input type="text" name="at_nome" id="at_nome" class="form-control" maxlength="150" required />
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>E-mail <span style="color:#e74c3c;">*</span></label>
                                <input type="email" name="at_email" id="at_email" class="form-control" maxlength="190" required />
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="at_status" id="at_status" class="form-control">
                                    <option value="ativo">Ativo</option>
                                    <option value="inativo">Inativo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="at_login" id="at_login" value="" />

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><i class="fa fa-headphones"></i> Setores — atendimento externo</label>
                                <div style="border:1px solid #e5e5e5;border-radius:4px;padding:10px;max-height:220px;overflow-y:auto;">
                                    <?php if (empty($__setoresFiltro)): ?>
                                        <span class="text-muted">Nenhum setor disponível.</span>
                                    <?php else: foreach ($__setoresFiltro as $__s): ?>
                                        <label style="display:block;font-weight:400;">
                                            <input type="checkbox" class="chk-externo" name="setores_externo[]" value="<?= (int) ($__s["id"] ?? 0) ?>" />
                                            <?= htmlspecialchars(strval($__s["nome"] ?? "")) ?>
                                        </label>
                                    <?php endforeach; endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><i class="fa fa-code"></i> Setores — atendimento interno (SSI)</label>
                                <div style="border:1px solid #e5e5e5;border-radius:4px;padding:10px;max-height:220px;overflow-y:auto;">
                                    <?php if (empty($__setoresFiltro)): ?>
                                        <span class="text-muted">Nenhum setor disponível.</span>
                                    <?php else: foreach ($__setoresFiltro as $__s): ?>
                                        <label style="display:block;font-weight:400;">
                                            <input type="checkbox" class="chk-ssi" name="setores_interno_ssi[]" value="<?= (int) ($__s["id"] ?? 0) ?>" />
                                            <?= htmlspecialchars(strval($__s["nome"] ?? "")) ?>
                                        </label>
                                    <?php endforeach; endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn blue"><i class="fa fa-save"></i> Salvar atendente</button>
                    <button type="reset" class="btn btn-default" onclick="limparVinculos()">Limpar</button>
                </form>

                <script>
                    // Preenche o formulario a partir do usuario escolhido no combo do dominio.
                    function preencherAtendente(sel) {
                        var op = sel.options[sel.selectedIndex];
                        if (!op || !op.value) { return; }
                        document.getElementById('at_nome').value  = op.getAttribute('data-nome') || '';
                        document.getElementById('at_email').value = op.value;
                        document.getElementById('at_login').value = op.getAttribute('data-login') || '';
                    }

                    // Traz um atendente ja cadastrado para o formulario, com os vinculos marcados.
                    function editarAtendente(btn) {
                        document.getElementById('at_usuario').value = '';
                        document.getElementById('at_nome').value   = btn.getAttribute('data-nome') || '';
                        document.getElementById('at_email').value  = btn.getAttribute('data-email') || '';
                        document.getElementById('at_login').value  = btn.getAttribute('data-login') || '';
                        document.getElementById('at_status').value = btn.getAttribute('data-status') || 'ativo';
                        marcar('chk-externo', (btn.getAttribute('data-externo') || '').split(','));
                        marcar('chk-ssi', (btn.getAttribute('data-ssi') || '').split(','));
                        document.getElementById('form-atendente').scrollIntoView({ behavior: 'smooth' });
                    }

                    function marcar(classe, ids) {
                        var alvos = ids.filter(function (v) { return v !== ''; });
                        Array.prototype.forEach.call(document.getElementsByClassName(classe), function (chk) {
                            chk.checked = alvos.indexOf(chk.value) !== -1;
                        });
                    }

                    function limparVinculos() {
                        marcar('chk-externo', []);
                        marcar('chk-ssi', []);
                    }
                </script>
<?php else: ?>

<?php if ($__verId > 0): ?>
<?php
    $__res = gestao_requisitar("GET", "/suporte/tickets/{$__verId}");
    $__ticket = $__res["ok"] ? ($__res["dados"]["ticket"] ?? []) : [];
    $__arquivos = $__res["ok"] ? ($__res["dados"]["arquivos"] ?? []) : [];
    $__comentarios = $__res["ok"] ? ($__res["dados"]["comentarios"] ?? []) : [];
    $__eventos = $__res["ok"] ? ($__res["dados"]["eventos"] ?? []) : [];

    if (empty($__ticket)): ?>
                <div class="alert alert-danger">Chamado não encontrado ou falha na API de suporte.</div>
    <?php else: ?>
        <?php
            $__status = strval($__ticket["status"] ?? "aberto");
            $__statusMap = [
                "aberto"             => ['<span class="label label-warning">Aberto</span>'],
                "em_analise"         => ['<span class="label label-default" style="background:#8e44ad;">Em Análise</span>'],
                "em_andamento"       => ['<span class="label label-info">Em Andamento</span>'],
                "aguardando_cliente" => ['<span class="label label-primary">Aguardando retorno do cliente</span>'],
                "resolvido"          => ['<span class="label label-success">Concluído</span>'],
                "cancelado"          => ['<span class="label label-default">Cancelado</span>'],
                "reaberto"           => ['<span class="label label-warning">Reaberto</span>'],
                "encaminhado_ssi"    => ['<span class="label label-danger">Encaminhado a SSI</span>'],
                "teste_interno"      => ['<span class="label label-default" style="background:#16a085;">Teste Interno</span>'],
                "aguardando_atualizacao" => ['<span class="label label-default" style="background:#e67e22;">Aguardando Atualização</span>'],
            ];
            $__badge = $__statusMap[$__status][0] ?? '<span class="label label-default">' . htmlspecialchars($__status) . '</span>';
            $__tipoMap = ["duvida" => "Dúvida operacional", "sugestao" => "Sugestão", "bug" => "Bug de sistema"];
            $__tipo = strval($__ticket["tipo"] ?? "");
            $__ssiCodigo = strval($__ticket["ssi_codigo"] ?? "");
            $__ssiPrioridade = strval($__ticket["ssi_prioridade"] ?? "");

            $__prioridade = strval($__ticket["prioridade"] ?? "media");
            $__prioridadeMap = [
                "baixa"   => ["Baixa", "label-default"],
                "media"   => ["Média", "label-info"],
                "alta"    => ["Alta", "label-warning"],
                "urgente" => ["Urgente", "label-danger"],
            ];
            [$__prioridadeLabel, $__prioridadeClasse] = $__prioridadeMap[$__prioridade] ?? ["Média", "label-info"];
            $__prioridadeBadge = '<span class="label ' . $__prioridadeClasse . '">' . htmlspecialchars($__prioridadeLabel) . '</span>';

            $__sla = suporte_sla_status($__prioridade, strval($__ticket["created_at"] ?? ""), strval($__ticket["fechado_em"] ?? ""), $__configAtual);
            $__slaBadge = '<span class="label label-' . $__sla["classe"] . '">SLA: ' . htmlspecialchars($__sla["label"]) . ($__sla["horas"] !== null ? " ({$__sla['horas']}h)" : "") . '</span>';
        ?>
        <table class="table table-striped table-bordered">
            <tr><th style="width:140px;">Empresa</th><td><?= htmlspecialchars(strval($__ticket["empresa_key"] ?? "")) ?> — <?= htmlspecialchars(strval($__ticket["empresa_nome"] ?? "")) ?></td></tr>
            <tr><th>Setor</th><td><?= htmlspecialchars(strval($__ticket["setor_nome"] ?? "") ?: "—") ?></td></tr>
            <tr><th>Usuário</th><td><?= htmlspecialchars(strval($__ticket["user_nome"] ?? "")) ?> (<?= htmlspecialchars(strval($__ticket["user_login"] ?? "")) ?>)</td></tr>
            <tr><th>E-mail</th><td><?= htmlspecialchars(strval($__ticket["user_email"] ?? "") ?: "—") ?></td></tr>
            <tr><th>Data de abertura</th><td><?= htmlspecialchars(suporte_fmt_data(strval($__ticket["created_at"] ?? ""))) ?></td></tr>
            <tr><th>Status</th><td><?= $__badge ?></td></tr>
            <tr><th>Prioridade</th><td><?= $__prioridadeBadge ?> <?= $__slaBadge ?></td></tr>
            <tr><th>Tipo</th><td><?= isset($__tipoMap[$__tipo]) ? htmlspecialchars($__tipoMap[$__tipo]) : '<span class="text-muted">Não classificado</span>' ?></td></tr>
            <tr><th>Atendente</th><td><?= htmlspecialchars(strval($__ticket["atendente_nome"] ?? "") ?: "—") ?></td></tr>
            <?php if ($__ssiCodigo !== ""): ?>
                <tr><th>SSI</th><td><span class="label label-danger"><?= htmlspecialchars($__ssiCodigo) ?></span> — <?= $__ssiPrioridade === "urgente" ? "Prioritária (urgente em produção)" : "Próxima atualização" ?></td></tr>
            <?php endif; ?>
            <tr><th>Página</th><td style="word-break:break-all;"><?= htmlspecialchars(strval($__ticket["pagina_url"] ?? "")) ?></td></tr>
        </table>

        <div class="well">
            <h4 style="margin-top:0;">Descrição do problema</h4>
            <p style="white-space:pre-wrap;"><?= htmlspecialchars(strval($__ticket["descricao"] ?? "")) ?></p>
        </div>

        <?php
            // Responsavel pelo chamado: escolhido a dedo entre os atendentes vinculados
            // ao setor. Depois de encaminhado a SSI, a lista passa a ser a do time interno.
            $__setorTicket     = (int) ($__ticket["setor_id"] ?? 0);
            $__escopoAtual     = $__status === "encaminhado_ssi" ? "interno_ssi" : "externo";
            $__equipeSetor     = suporte_equipe_do_setor($__atendentes, $__setorTicket, $__escopoAtual);
            $__atendenteAtual  = (int) ($__ticket["atendente_id"] ?? 0);
        ?>
        <div style="border:1px solid #eee;border-radius:6px;padding:14px;margin-bottom:18px;background:#fcfcfc;">
            <h4 style="margin-top:0;">
                <i class="fa fa-user-circle-o"></i> Atendente responsável
                <small class="text-muted"><?= $__escopoAtual === "interno_ssi" ? "atendimento interno (SSI)" : "atendimento externo" ?></small>
            </h4>

            <?php if ($__setorTicket < 1): ?>
                <p class="text-muted" style="margin:0;">
                    Este chamado foi aberto sem setor, então não há equipe vinculada para sugerir. Use o botão
                    <strong>Iniciar atendimento</strong> abaixo para assumi-lo.
                </p>
            <?php elseif (empty($__equipeSetor)): ?>
                <p class="text-muted" style="margin:0;">
                    Nenhum atendente vinculado ao setor <strong><?= htmlspecialchars(strval($__ticket["setor_nome"] ?? "")) ?></strong>
                    no escopo <strong><?= $__escopoAtual === "interno_ssi" ? "atendimento interno (SSI)" : "atendimento externo" ?></strong>.
                    <a href="gestao.php?equipe=1">Montar a equipe deste setor</a>.
                </p>
            <?php else: ?>
                <form method="post" class="form-inline">
                    <input type="hidden" name="sup_acao" value="atribuir" />
                    <input type="hidden" name="id" value="<?= $__verId ?>" />
                    <div class="form-group" style="margin-right:8px;">
                        <select name="atendente_id" class="form-control">
                            <option value="0">— sem responsável —</option>
                            <?php foreach ($__equipeSetor as $__at): ?>
                                <option value="<?= (int) ($__at["id"] ?? 0) ?>" <?= $__atendenteAtual === (int) ($__at["id"] ?? 0) ? "selected" : "" ?>>
                                    <?= htmlspecialchars(strval($__at["nome"] ?? "")) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-share-square-o"></i> Atribuir</button>
                    <span class="help-block" style="margin-top:6px;">
                        O atendente escolhido recebe um e-mail com o chamado. Trocar o responsável não altera o status.
                    </span>
                </form>
            <?php endif; ?>
        </div>

        <!-- Fluxo de atendimento -->
        <div style="border:1px solid #eee;border-radius:6px;padding:14px;margin-bottom:18px;background:#fcfcfc;">
            <h4 style="margin-top:0;"><i class="fa fa-tasks"></i> Fluxo de atendimento</h4>

            <?php if ($__status === "aberto" || $__status === "reaberto"): ?>
                <form method="post" style="display:inline-block;margin-right:8px;margin-bottom:6px;">
                    <input type="hidden" name="sup_acao" value="status" />
                    <input type="hidden" name="id" value="<?= $__verId ?>" />
                    <input type="hidden" name="status" value="em_analise" />
                    <button type="submit" class="btn btn-default btn-sm" style="border-color:#8e44ad;color:#8e44ad;"><i class="fa fa-search"></i> Iniciar análise</button>
                </form>
            <?php endif; ?>
            <?php if ($__status === "aberto" || $__status === "reaberto" || $__status === "em_analise"): ?>
                <form method="post" style="display:inline-block;margin-right:8px;margin-bottom:6px;">
                    <input type="hidden" name="sup_acao" value="aceitar" />
                    <input type="hidden" name="id" value="<?= $__verId ?>" />
                    <button type="submit" class="btn blue"><i class="fa fa-handshake-o"></i> Iniciar atendimento</button>
                </form>
            <?php endif; ?>

            <form method="post" style="display:inline-block;margin-bottom:6px;">
                <input type="hidden" name="sup_acao" value="tipo" />
                <input type="hidden" name="id" value="<?= $__verId ?>" />
                <select name="tipo" class="form-control input-sm" style="display:inline-block;width:auto;" required>
                    <option value="">Classificar tipo...</option>
                    <option value="duvida" <?= ($__tipo === "duvida") ? "selected" : "" ?>>Dúvida operacional</option>
                    <option value="sugestao" <?= ($__tipo === "sugestao") ? "selected" : "" ?>>Sugestão</option>
                    <option value="bug" <?= ($__tipo === "bug") ? "selected" : "" ?>>Bug de sistema</option>
                </select>
                <button type="submit" class="btn btn-default btn-sm"><i class="fa fa-tag"></i> Salvar tipo</button>
            </form>

            <!-- Prioridade: independente de status/tipo — pode ser trocada em qualquer ponto do fluxo. -->
            <form method="post" style="display:inline-block;margin-bottom:6px;margin-left:8px;padding-left:8px;border-left:1px solid #ddd;">
                <input type="hidden" name="sup_acao" value="prioridade" />
                <input type="hidden" name="id" value="<?= $__verId ?>" />
                <select name="prioridade" class="form-control input-sm" style="display:inline-block;width:auto;" required>
                    <option value="baixa" <?= ($__prioridade === "baixa") ? "selected" : "" ?>>Prioridade: Baixa</option>
                    <option value="media" <?= ($__prioridade === "media") ? "selected" : "" ?>>Prioridade: Média</option>
                    <option value="alta" <?= ($__prioridade === "alta") ? "selected" : "" ?>>Prioridade: Alta</option>
                    <option value="urgente" <?= ($__prioridade === "urgente") ? "selected" : "" ?>>Prioridade: Urgente</option>
                </select>
                <button type="submit" class="btn btn-default btn-sm"><i class="fa fa-flag"></i> Salvar prioridade</button>
            </form>

            <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
                <?php if ($__status !== "resolvido" && $__status !== "cancelado"): ?>
                    <form method="post">
                        <input type="hidden" name="sup_acao" value="status" />
                        <input type="hidden" name="id" value="<?= $__verId ?>" />
                        <input type="hidden" name="status" value="aguardando_cliente" />
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-hourglass-half"></i> Aguardar retorno do cliente</button>
                    </form>
                <?php endif; ?>
                <?php if ($__status !== "resolvido" && $__status !== "cancelado"): ?>
                    <?php
                        // Procedimento de fechamento: saindo de "Aguardando Atualização", quem finaliza
                        // deve conferir o que subiu na atualização antes de concluir o chamado.
                        $__confirmConcluir = $__status === "aguardando_atualizacao"
                            ? "A atualização já subiu para produção? Confira tudo que subiu na atualização de sistema e confirme que a correção/melhoria deste chamado está no ar antes de concluir."
                            : "Marcar como concluído?";
                    ?>
                    <form method="post">
                        <input type="hidden" name="sup_acao" value="status" />
                        <input type="hidden" name="id" value="<?= $__verId ?>" />
                        <input type="hidden" name="status" value="resolvido" />
                        <button type="submit" class="btn btn-success btn-sm" onclick="return confirm(<?= htmlspecialchars(json_encode($__confirmConcluir), ENT_QUOTES) ?>);"><i class="fa fa-check"></i> Concluído</button>
                    </form>
                <?php endif; ?>
                <?php if ($__status !== "resolvido" && $__status !== "cancelado"): ?>
                    <form method="post">
                        <input type="hidden" name="sup_acao" value="status" />
                        <input type="hidden" name="id" value="<?= $__verId ?>" />
                        <input type="hidden" name="status" value="cancelado" />
                        <button type="submit" class="btn btn-default btn-sm" onclick="return confirm('Cancelar o chamado?');"><i class="fa fa-times"></i> Cancelar</button>
                    </form>
                <?php endif; ?>
                <?php if ($__status !== "aberto" && $__status !== "reaberto"): ?>
                    <form method="post">
                        <input type="hidden" name="sup_acao" value="status" />
                        <input type="hidden" name="id" value="<?= $__verId ?>" />
                        <input type="hidden" name="status" value="reaberto" />
                        <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Reabrir o chamado?');"><i class="fa fa-undo"></i> Reabrir</button>
                    </form>
                <?php endif; ?>
                <?php if ($__tipo === "bug" && $__status !== "encaminhado_ssi" && $__status !== "em_andamento" && $__status !== "teste_interno" && $__status !== "resolvido" && $__status !== "cancelado"): ?>
                    <form method="post" style="border-left:1px solid #ddd;padding-left:12px;">
                        <input type="hidden" name="sup_acao" value="status" />
                        <input type="hidden" name="id" value="<?= $__verId ?>" />
                        <input type="hidden" name="status" value="encaminhado_ssi" />
                        <label style="font-weight:400;margin-right:8px;"><input type="radio" name="ssi_prioridade" value="urgente" /> Urgente — produção</label>
                        <label style="font-weight:400;margin-right:8px;"><input type="radio" name="ssi_prioridade" value="proxima_atualizacao" checked /> Próxima atualização</label>
                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Encaminhar o chamado para a SSI?');"><i class="fa fa-bug"></i> Encaminhar a SSI</button>
                    </form>
                <?php endif; ?>
                <?php if ($__status === "encaminhado_ssi"): ?>
                    <form method="post" style="border-left:1px solid #ddd;padding-left:12px;">
                        <input type="hidden" name="sup_acao" value="status" />
                        <input type="hidden" name="id" value="<?= $__verId ?>" />
                        <input type="hidden" name="status" value="em_andamento" />
                        <button type="submit" class="btn btn-info btn-sm"><i class="fa fa-code"></i> Iniciar desenvolvimento (SSI)</button>
                    </form>
                <?php endif; ?>
                <?php if ($__status === "em_andamento" && $__ssiCodigo !== ""): ?>
                    <form method="post" style="border-left:1px solid #ddd;padding-left:12px;">
                        <input type="hidden" name="sup_acao" value="status" />
                        <input type="hidden" name="id" value="<?= $__verId ?>" />
                        <input type="hidden" name="status" value="teste_interno" />
                        <button type="submit" class="btn btn-default btn-sm" style="border-color:#16a085;color:#16a085;"><i class="fa fa-flask"></i> Enviar para teste interno</button>
                    </form>
                <?php endif; ?>
                <?php if ($__status === "teste_interno"): ?>
                    <form method="post" style="border-left:1px solid #ddd;padding-left:12px;">
                        <input type="hidden" name="sup_acao" value="status" />
                        <input type="hidden" name="id" value="<?= $__verId ?>" />
                        <input type="hidden" name="status" value="aguardando_atualizacao" />
                        <button type="submit" class="btn btn-default btn-sm" style="border-color:#e67e22;color:#e67e22;" onclick="return confirm('Aprovado no teste interno — marcar como Aguardando Atualização? Os envolvidos serão avisados de que a correção espera a próxima atualização em produção.');"><i class="fa fa-cloud-upload"></i> Aprovado — aguardar atualização</button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="sup_acao" value="status" />
                        <input type="hidden" name="id" value="<?= $__verId ?>" />
                        <input type="hidden" name="status" value="em_andamento" />
                        <button type="submit" class="btn btn-default btn-sm" onclick="return confirm('Reprovado no teste interno — voltar para Em Andamento?');"><i class="fa fa-undo"></i> Reprovado no teste, voltar</button>
                    </form>
                <?php endif; ?>
                <?php if ($__status === "aguardando_atualizacao"): ?>
                    <form method="post" style="border-left:1px solid #ddd;padding-left:12px;">
                        <input type="hidden" name="sup_acao" value="status" />
                        <input type="hidden" name="id" value="<?= $__verId ?>" />
                        <input type="hidden" name="status" value="em_andamento" />
                        <button type="submit" class="btn btn-default btn-sm" onclick="return confirm('A correção não subiu na atualização — voltar para Em Andamento?');"><i class="fa fa-undo"></i> Não subiu, voltar</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <h4><i class="fa fa-paperclip"></i> Anexos (<?= count($__arquivos) ?>)</h4>
        <?= suporte_render_anexos($__arquivos, $__verId, "imagem_gestao.php") ?>

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
                    <span style="margin-left:8px;"><?= htmlspecialchars(suporte_fmt_data(strval($__c["created_at"] ?? ""))) ?></span>
                </div>
                <div style="white-space:pre-wrap;"><?= htmlspecialchars(strval($__c["texto"] ?? "")) ?></div>
            </div>
        <?php endforeach; ?>

        <!-- Formulário de comentário (gestor) -->
        <form method="post" style="margin-top:15px;">
            <input type="hidden" name="sup_acao" value="comentario" />
            <input type="hidden" name="id" value="<?= $__verId ?>" />
            <textarea name="texto" rows="3" maxlength="1000" required placeholder="Escreva um comentário para a empresa..." style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;resize:vertical;box-sizing:border-box;"></textarea>
            <div style="margin-top:8px;display:flex;gap:8px;justify-content:flex-end;">
                <button type="submit" class="btn blue"><i class="fa fa-comment"></i> Adicionar comentário</button>
            </div>
        </form>

        <!-- Timeline -->
        <h4 style="margin-top:28px;"><i class="fa fa-history"></i> Linha do tempo</h4>
        <?= suporte_render_timeline($__eventos) ?>
    <?php endif; ?>

<?php else: ?>
<?php
    // ── Listagem (todas as empresas) ────────────────────────────────
    $__fEmpresa = trim(strval($_GET["empresa"] ?? ""));
    $__fSetorId = (int) ($_GET["setor_id"] ?? 0);
    $__fStatus  = trim(strval($_GET["status"] ?? ""));
    $__fPrioridade = trim(strval($_GET["prioridade"] ?? ""));
    $__fInicio  = trim(strval($_GET["data_inicio"] ?? ""));
    $__fFim     = trim(strval($_GET["data_fim"] ?? ""));
    $__fPagina  = max((int) ($_GET["pagina"] ?? 1), 1);
    // "" = todos | "sem" = sem responsavel | numero = id do atendente
    $__fAtendente = trim(strval($_GET["atendente_id"] ?? ""));

    $__statusListagem = ["aberto", "em_analise", "em_andamento", "aguardando_cliente", "resolvido", "cancelado", "reaberto", "encaminhado_ssi", "teste_interno", "aguardando_atualizacao"];
    $__prioridadeListagem = ["baixa", "media", "alta", "urgente"];

    $__queryFiltro = ["pagina" => $__fPagina, "limit" => 25];
    if ($__fEmpresa !== "") $__queryFiltro["empresa"] = $__fEmpresa;
    if ($__fSetorId > 0) $__queryFiltro["setor_id"] = $__fSetorId;
    if (in_array($__fStatus, $__statusListagem, true)) $__queryFiltro["status"] = $__fStatus;
    if (in_array($__fPrioridade, $__prioridadeListagem, true)) $__queryFiltro["prioridade"] = $__fPrioridade;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $__fInicio)) $__queryFiltro["data_inicio"] = $__fInicio;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $__fFim)) $__queryFiltro["data_fim"] = $__fFim;
    if ($__fAtendente === "sem" || (int) $__fAtendente > 0) $__queryFiltro["atendente_id"] = $__fAtendente;

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

                <?php
                    // O painel de filtros nasce aberto so quando ha filtro aplicado: sem filtro,
                    // a tela abre limpa e a lista fica com a tela inteira.
                    $__temFiltro = $__fEmpresa !== "" || $__fSetorId > 0 || $__fAtendente !== ""
                        || $__fStatus !== "" || $__fPrioridade !== "" || $__fInicio !== "" || $__fFim !== "";
                ?>

                <div class="row" style="margin-bottom:12px;">
                    <div class="col-sm-7">
                        <?php if ($__euAtendenteId > 0): ?>
                            <a href="gestao.php?atendente_id=<?= $__euAtendenteId ?>"
                               class="btn btn-sm <?= ((int) $__fAtendente === $__euAtendenteId) ? "blue" : "btn-default" ?>">
                                <i class="fa fa-user"></i> Meus atendimentos
                            </a>
                            <a href="gestao.php?atendente_id=sem"
                               class="btn btn-sm <?= ($__fAtendente === "sem") ? "blue" : "btn-default" ?>">
                                <i class="fa fa-inbox"></i> Sem responsável
                            </a>
                        <?php endif; ?>
                        <?php if ($__temFiltro): ?>
                            <a href="gestao.php" class="btn btn-sm btn-default"><i class="fa fa-times"></i> Limpar filtros</a>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-5 text-right">
                        <span class="text-muted" style="line-height:30px;margin-right:10px;">
                            <strong><?= $__total ?></strong> chamado<?= $__total === 1 ? "" : "s" ?>
                        </span>
                        <button type="button" class="btn btn-sm btn-default" data-toggle="collapse" data-target="#painelFiltros">
                            <i class="fa fa-filter"></i> Filtros
                            <?php if ($__temFiltro): ?><span class="badge" style="background:#337ab7;">ativos</span><?php endif; ?>
                        </button>
                    </div>
                </div>

                <div class="collapse <?= $__temFiltro ? "in" : "" ?>" id="painelFiltros">
                    <form method="get" style="background:#fbfbfb;border:1px solid #eee;border-radius:4px;padding:14px 14px 4px;margin-bottom:15px;">
                        <div class="row">
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label>Empresa</label>
                                    <select name="empresa" class="form-control">
                                        <option value="">Todas</option>
                                        <?php foreach ($__empresas as $__e): ?>
                                            <option value="<?= htmlspecialchars(strval($__e["empresa_key"] ?? "")) ?>" <?= ($__fEmpresa === strval($__e["empresa_key"] ?? "")) ? "selected" : "" ?>>
                                                <?= htmlspecialchars(strval($__e["empresa_nome"] ?? $__e["empresa_key"] ?? "")) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label>Setor</label>
                                    <select name="setor_id" class="form-control">
                                        <option value="">Todos</option>
                                        <?php foreach ($__setoresFiltro as $__s): ?>
                                            <option value="<?= (int) ($__s["id"] ?? 0) ?>" <?= ($__fSetorId === (int) ($__s["id"] ?? 0)) ? "selected" : "" ?>>
                                                <?= htmlspecialchars(strval($__s["nome"] ?? "")) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label>Atendente</label>
                                    <select name="atendente_id" class="form-control">
                                        <option value="">Todos</option>
                                        <option value="sem" <?= ($__fAtendente === "sem") ? "selected" : "" ?>>— sem responsável —</option>
                                        <?php foreach ($__atendentes as $__a): ?>
                                            <option value="<?= (int) ($__a["id"] ?? 0) ?>" <?= ((int) $__fAtendente === (int) ($__a["id"] ?? 0)) ? "selected" : "" ?>>
                                                <?= htmlspecialchars(strval($__a["nome"] ?? "")) ?><?= strval($__a["status"] ?? "") !== "ativo" ? " (inativo)" : "" ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status" class="form-control">
                                        <option value="">Todos</option>
                                        <?php
                                            $__statusOpcoes = [
                                                "aberto"                 => "Aberto",
                                                "em_analise"             => "Em Análise",
                                                "em_andamento"           => "Em Andamento",
                                                "aguardando_cliente"     => "Aguardando retorno do cliente",
                                                "reaberto"               => "Reaberto",
                                                "encaminhado_ssi"        => "Encaminhado a SSI",
                                                "teste_interno"          => "Teste Interno",
                                                "aguardando_atualizacao" => "Aguardando Atualização",
                                                "resolvido"              => "Concluído",
                                                "cancelado"              => "Cancelado",
                                            ];
                                        ?>
                                        <?php foreach ($__statusOpcoes as $__k => $__v): ?>
                                            <option value="<?= $__k ?>" <?= ($__fStatus === $__k) ? "selected" : "" ?>><?= htmlspecialchars($__v) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label>Prioridade</label>
                                    <select name="prioridade" class="form-control">
                                        <option value="">Todas</option>
                                        <option value="baixa" <?= ($__fPrioridade === "baixa") ? "selected" : "" ?>>Baixa</option>
                                        <option value="media" <?= ($__fPrioridade === "media") ? "selected" : "" ?>>Média</option>
                                        <option value="alta" <?= ($__fPrioridade === "alta") ? "selected" : "" ?>>Alta</option>
                                        <option value="urgente" <?= ($__fPrioridade === "urgente") ? "selected" : "" ?>>Urgente</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label>Aberto de</label>
                                    <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($__fInicio) ?>" />
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label>até</label>
                                    <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($__fFim) ?>" />
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label style="display:block;">&nbsp;</label>
                                    <button type="submit" class="btn blue"><i class="fa fa-search"></i> Filtrar</button>
                                    <a href="gestao.php" class="btn btn-default">Limpar</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <style>
                    /* .label do Bootstrap e inline: empilhado, nao reserva espaco vertical
                       nenhum e os badges saem colados. inline-block + margem resolve, e
                       vale para toda a listagem (situacao, tipo, SSI). */
                    .suporte-lista td { vertical-align: middle; line-height: 1.6; }
                    .suporte-lista .label { display: inline-block; margin: 0 4px 3px 0; }
                    .suporte-lista .linha-meta { margin-top: 6px; }
                    .suporte-lista .linha-meta > * { margin-right: 8px; }
                    .suporte-lista small { display: inline-block; }
                </style>

                <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered suporte-lista">
                    <thead>
                        <tr>
                            <th style="width:60px;">Nº</th>
                            <th style="width:160px;">Empresa / Setor</th>
                            <th style="width:150px;">Solicitante</th>
                            <th>Descrição</th>
                            <th style="width:140px;">Atendente</th>
                            <th style="width:120px;">Situação</th>
                            <th style="width:110px;">Datas</th>
                            <th style="width:70px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($__tickets)): ?>
                            <tr><td colspan="8" class="text-center text-muted" style="padding:24px;">Nenhum chamado encontrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($__tickets as $__t): ?>
                            <?php
                                $__desc = trim(strval($__t["descricao"] ?? ""));
                                $__descCurta = mb_strlen($__desc, "UTF-8") > 110 ? mb_substr($__desc, 0, 110, "UTF-8") . "…" : $__desc;
                                $__statusT = strval($__t["status"] ?? "aberto");
                                $__badgeMap = [
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
                                $__badgeT = $__badgeMap[$__statusT] ?? '<span class="label label-default">' . htmlspecialchars($__statusT) . '</span>';
                                $__tipoLabel = ["duvida" => "Dúvida", "sugestao" => "Sugestão", "bug" => "Bug"][strval($__t["tipo"] ?? "")] ?? "";
                                $__ssiT = strval($__t["ssi_codigo"] ?? "");

                                $__prioridadeT = strval($__t["prioridade"] ?? "media");
                                $__prioridadeBadgeMap = [
                                    "baixa"   => '<span class="label label-default">Baixa</span>',
                                    "media"   => '<span class="label label-info">Média</span>',
                                    "alta"    => '<span class="label label-warning">Alta</span>',
                                    "urgente" => '<span class="label label-danger">Urgente</span>',
                                ];
                                $__prioridadeBadgeT = $__prioridadeBadgeMap[$__prioridadeT] ?? $__prioridadeBadgeMap["media"];
                                $__slaT = suporte_sla_status($__prioridadeT, strval($__t["created_at"] ?? ""), strval($__t["fechado_em"] ?? ""), $__configAtual);

                                // A URL inteira era o que mais poluia a linha: fica so o nome do
                                // arquivo, com a URL completa no title e no destino do link.
                                $__pag = trim(strval($__t["pagina_url"] ?? ""));
                                $__pagCurta = $__pag !== "" ? basename(strval(parse_url($__pag, PHP_URL_PATH) ?: "")) : "";

                                $__atNome = trim(strval($__t["atendente_nome"] ?? ""));
                                // Nome gravado sem vinculo com a equipe: aparece na coluna, mas nao
                                // entra em "Meus atendimentos" de ninguem. Sinalizado para nao parecer
                                // que o filtro esta perdendo chamado.
                                $__atSolto = $__atNome !== "" && (int) ($__t["atendente_id"] ?? 0) < 1;

                                $__fechadoT = suporte_fmt_data(strval($__t["fechado_em"] ?? ""));
                            ?>
                            <tr>
                                <td><strong>#<?= (int) ($__t["id"] ?? 0) ?></strong></td>
                                <td>
                                    <?= htmlspecialchars(strval($__t["empresa_key"] ?? "")) ?>
                                    <br><small class="text-muted"><?= htmlspecialchars(strval($__t["setor_nome"] ?? "") ?: "sem setor") ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars(strval($__t["user_nome"] ?? "")) ?>
                                    <br><small class="text-muted"><?= htmlspecialchars(strval($__t["user_login"] ?? "")) ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($__descCurta) ?>
                                    <div class="linha-meta">
                                        <?php if ($__tipoLabel !== ""): ?>
                                            <small class="text-muted"><i class="fa fa-tag"></i> <?= htmlspecialchars($__tipoLabel) ?></small>
                                        <?php endif; ?>
                                        <?php if ($__ssiT !== ""): ?>
                                            <small class="label label-danger" style="font-size:10px;"><?= htmlspecialchars($__ssiT) ?></small>
                                        <?php endif; ?>
                                        <?php if ($__pagCurta !== ""): ?>
                                            <small><a href="<?= htmlspecialchars($__pag, ENT_QUOTES) ?>" target="_blank" class="text-muted" title="<?= htmlspecialchars($__pag, ENT_QUOTES) ?>"><i class="fa fa-external-link"></i> <?= htmlspecialchars($__pagCurta) ?></a></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($__atNome !== ""): ?>
                                        <?= htmlspecialchars($__atNome) ?>
                                        <?php if ($__atSolto): ?>
                                            <br><small class="text-muted" title="Este nome não está vinculado a nenhum atendente da equipe, então o chamado não aparece em Meus atendimentos."><i class="fa fa-unlink"></i> fora da equipe</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <small class="text-muted">— sem responsável —</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $__badgeT ?>
                                    <div class="linha-meta">
                                        <?= $__prioridadeBadgeT ?>
                                        <?php if ($__slaT["classe"] === "danger"): ?>
                                            <span class="label label-danger" style="font-size:10px;">SLA</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <small><?= htmlspecialchars(suporte_fmt_data(strval($__t["created_at"] ?? ""))) ?></small>
                                    <?php if ($__fechadoT !== ""): ?>
                                        <br><small class="text-muted" title="Fechado em"><i class="fa fa-check"></i> <?= htmlspecialchars($__fechadoT) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><a href="gestao.php?id=<?= (int) ($__t["id"] ?? 0) ?>" class="btn btn-xs blue"><i class="fa fa-cog"></i> Gerir</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <?php if ($__paginas > 1): ?>
                    <div class="text-center">
                        <ul class="pagination">
                            <?php for ($__p = 1; $__p <= $__paginas; $__p++): ?>
                                <li class="<?= ($__p === $__fPagina) ? "active" : "" ?>">
                                    <a href="<?= gestao_manter("empresa", $__fEmpresa, ["setor_id" => $__fSetorId ?: "", "atendente_id" => $__fAtendente, "status" => $__fStatus, "prioridade" => $__fPrioridade, "data_inicio" => $__fInicio, "data_fim" => $__fFim, "pagina" => $__p]) ?>"><?= $__p ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </div>
                <?php endif; ?>
<?php endif; ?>
<?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php rodape(); ?>

