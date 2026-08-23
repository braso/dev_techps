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
            $res = gestao_requisitar("POST", "/suporte/tickets/{$id}/aceitar", [], ["atendente" => $__gestorNome]);
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
            $statusPermitidos = ["aberto", "em_analise", "em_andamento", "aguardando_cliente", "resolvido", "cancelado", "reaberto", "encaminhado_ssi", "teste_interno"];
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

    // ── Modo detalhe / configurações ────────────────────────────────────
    $__verId = (int) ($_GET["id"] ?? 0);
    $__verConfig = $__verId === 0 && isset($_GET["config"]);

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
                    <span class="caption-subject bold uppercase"><?= $__verId > 0 ? "Chamado #" . $__verId : ($__verConfig ? "Configurações do Suporte" : "Gestão de Suporte") ?></span>
                    <span class="caption-helper"><?= $__verId > 0 ? "Domínio TechPS" : ($__verConfig ? "Regras e aviso de chamado novo" : "Chamados de todas as empresas") ?></span>
                </div>
                <div class="actions">
                    <?php if ($__verId > 0 || $__verConfig): ?>
                        <a href="gestao.php" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Voltar</a>
                    <?php else: ?>
                        <a href="dashboard.php" class="btn btn-default btn-sm"><i class="fa fa-bar-chart"></i> Dashboard</a>
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
                    <strong>Encaminhado a SSI → Em Andamento → Teste Interno → Concluído</strong>. Não existe uma "SSI" separada para fechar — o código SSI é só uma etiqueta gravada no próprio chamado, então concluir o chamado já encerra a SSI junto.
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
                    <form method="post">
                        <input type="hidden" name="sup_acao" value="status" />
                        <input type="hidden" name="id" value="<?= $__verId ?>" />
                        <input type="hidden" name="status" value="resolvido" />
                        <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Marcar como concluído?');"><i class="fa fa-check"></i> Concluído</button>
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
                        <input type="hidden" name="status" value="em_andamento" />
                        <button type="submit" class="btn btn-default btn-sm" onclick="return confirm('Reprovado no teste interno — voltar para Em Andamento?');"><i class="fa fa-undo"></i> Reprovado no teste, voltar</button>
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

    $__statusListagem = ["aberto", "em_analise", "em_andamento", "aguardando_cliente", "resolvido", "cancelado", "reaberto", "encaminhado_ssi", "teste_interno"];
    $__prioridadeListagem = ["baixa", "media", "alta", "urgente"];

    $__queryFiltro = ["pagina" => $__fPagina, "limit" => 25];
    if ($__fEmpresa !== "") $__queryFiltro["empresa"] = $__fEmpresa;
    if ($__fSetorId > 0) $__queryFiltro["setor_id"] = $__fSetorId;
    if (in_array($__fStatus, $__statusListagem, true)) $__queryFiltro["status"] = $__fStatus;
    if (in_array($__fPrioridade, $__prioridadeListagem, true)) $__queryFiltro["prioridade"] = $__fPrioridade;
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
                        <label style="margin-right:5px;">Setor</label>
                        <select name="setor_id" class="form-control">
                            <option value="">Todos</option>
                            <?php foreach ($__setoresFiltro as $__s): ?>
                                <option value="<?= (int) ($__s["id"] ?? 0) ?>" <?= ($__fSetorId === (int) ($__s["id"] ?? 0)) ? "selected" : "" ?>>
                                    <?= htmlspecialchars(strval($__s["nome"] ?? "")) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-right:10px;">
                        <label style="margin-right:5px;">Status</label>
                        <select name="status" class="form-control">
                            <option value="">Todos</option>
                            <option value="aberto" <?= ($__fStatus === "aberto") ? "selected" : "" ?>>Aberto</option>
                            <option value="em_analise" <?= ($__fStatus === "em_analise") ? "selected" : "" ?>>Em Análise</option>
                            <option value="em_andamento" <?= ($__fStatus === "em_andamento") ? "selected" : "" ?>>Em Andamento</option>
                            <option value="aguardando_cliente" <?= ($__fStatus === "aguardando_cliente") ? "selected" : "" ?>>Aguardando retorno do cliente</option>
                            <option value="resolvido" <?= ($__fStatus === "resolvido") ? "selected" : "" ?>>Concluído</option>
                            <option value="cancelado" <?= ($__fStatus === "cancelado") ? "selected" : "" ?>>Cancelado</option>
                            <option value="reaberto" <?= ($__fStatus === "reaberto") ? "selected" : "" ?>>Reaberto</option>
                            <option value="encaminhado_ssi" <?= ($__fStatus === "encaminhado_ssi") ? "selected" : "" ?>>Encaminhado a SSI</option>
                            <option value="teste_interno" <?= ($__fStatus === "teste_interno") ? "selected" : "" ?>>Teste Interno</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-right:10px;">
                        <label style="margin-right:5px;">Prioridade</label>
                        <select name="prioridade" class="form-control">
                            <option value="">Todas</option>
                            <option value="baixa" <?= ($__fPrioridade === "baixa") ? "selected" : "" ?>>Baixa</option>
                            <option value="media" <?= ($__fPrioridade === "media") ? "selected" : "" ?>>Média</option>
                            <option value="alta" <?= ($__fPrioridade === "alta") ? "selected" : "" ?>>Alta</option>
                            <option value="urgente" <?= ($__fPrioridade === "urgente") ? "selected" : "" ?>>Urgente</option>
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
                            <th>Setor</th>
                            <th>Usuário</th>
                            <th>Página</th>
                            <th>Descrição</th>
                            <th style="width:100px;">Status</th>
                            <th style="width:90px;">Prioridade</th>
                            <th style="width:150px;">Data</th>
                            <th style="width:150px;">Fechado em</th>
                            <th style="width:90px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($__tickets)): ?>
                            <tr><td colspan="11" class="text-center">Nenhum chamado encontrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($__tickets as $__t): ?>
                            <?php
                                $__desc = trim(strval($__t["descricao"] ?? ""));
                                $__descCurta = mb_strlen($__desc, "UTF-8") > 60 ? mb_substr($__desc, 0, 60, "UTF-8") . "…" : $__desc;
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
                            ?>
                            <tr>
                                <td>#<?= (int) ($__t["id"] ?? 0) ?></td>
                                <td><?= htmlspecialchars(strval($__t["empresa_key"] ?? "")) ?></td>
                                <td><?= htmlspecialchars(strval($__t["setor_nome"] ?? "") ?: "—") ?></td>
                                <td>
                                    <?= htmlspecialchars(strval($__t["user_nome"] ?? "")) ?>
                                    <br><small class="text-muted"><?= htmlspecialchars(strval($__t["user_login"] ?? "")) ?></small>
                                </td>
                                <td style="max-width:200px;word-break:break-all;"><small><?= htmlspecialchars(strval($__t["pagina_url"] ?? "")) ?></small></td>
                                <td>
                                    <?= htmlspecialchars($__descCurta) ?>
                                    <?php if ($__tipoLabel !== ""): ?><br><small><i class="fa fa-tag"></i> <?= htmlspecialchars($__tipoLabel) ?></small><?php endif; ?>
                                    <?php if ($__ssiT !== ""): ?><br><small class="label label-danger" style="font-size:10px;"><?= htmlspecialchars($__ssiT) ?></small><?php endif; ?>
                                </td>
                                <td><?= $__badgeT ?></td>
                                <td>
                                    <?= $__prioridadeBadgeT ?>
                                    <?php if ($__slaT["classe"] === "danger"): ?><br><small class="label label-danger" style="font-size:10px;margin-top:3px;display:inline-block;">SLA estourado</small><?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars(suporte_fmt_data(strval($__t["created_at"] ?? ""))) ?></td>
                                <td><?= htmlspecialchars(suporte_fmt_data(strval($__t["fechado_em"] ?? "")) ?: "—") ?></td>
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
                                    <a href="<?= gestao_manter("empresa", $__fEmpresa, ["setor_id" => $__fSetorId ?: "", "status" => $__fStatus, "prioridade" => $__fPrioridade, "data_inicio" => $__fInicio, "data_fim" => $__fFim, "pagina" => $__p]) ?>"><?= $__p ?></a>
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

