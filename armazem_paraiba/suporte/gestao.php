<?php
    /* ============================================================
       Suporte — Gestão Central (domínios TechPS e Demo)
       Lista os chamados de todas as empresas. Quem abre escolhe o
       tipo; cada tipo aponta para um setor, e os funcionários desse
       setor recebem. Quem clicar em Assumir vira o responsável.
       Fluxo: Aberto → Em análise → Em desenvolvimento → Corrigido → Fechado.
       Tudo via API do servidor central de suporte.
       ============================================================ */
    include __DIR__ . "/../load_env.php";
    include_once __DIR__ . "/../conecta.php";
    include_once __DIR__ . "/../check_permission.php";
    include_once __DIR__ . "/_timeline.php";
    include_once __DIR__ . "/_anexos.php";
    include_once __DIR__ . "/_datas.php";
    include_once __DIR__ . "/_membros_sync.php";

    $__empresaAtual = trim(strval($_ENV["CONTEX_PATH"] ?? ""), "/");
    // Gestão central: domínios TechPS (produção) e Demo (desenvolvimento).
    if (strpos($__empresaAtual, "techps") === false && strpos($__empresaAtual, "demo") === false) {
        echo "<script>alert('Acesso restrito ao domínio TechPS.'); window.location.href='" . ($_ENV["CONTEX_PATH"] ?? "") . "/batida_ponto.php';</script>";
        exit;
    }

    $__apiUrl      = rtrim(strval($_ENV["SUPORTE_API_URL"] ?? ""), "/");
    $__adminKey    = strval($_ENV["SUPORTE_ADMIN_KEY"] ?? "");
    $__gestorNome  = trim(strval($_SESSION["user_tx_nome"] ?? "Gestor TechPS"));
    $__gestorLogin = trim(strval($_SESSION["user_tx_login"] ?? ""));
    $__gestorEmail = strtolower(trim(strval($_SESSION["user_tx_email"] ?? "")));

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

    // ── Chat interno (AJAX) ──────────────────────────────────────────────
    // Responde JSON e sai antes das demais chamadas à API da página: a tela consulta a cada poucos segundos.
    // Só existe aqui na gestão (domínio TechPS/Demo); detalhe.php da empresa nunca chama estas rotas.
    $__chatAcao = strval($_POST["sup_acao"] ?? ($_GET["chat_interno"] ?? ""));
    if ($__chatAcao === "chat_interno_listar" || ($__chatAcao === "chat_interno_enviar" && $_SERVER["REQUEST_METHOD"] === "POST")) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header("Content-Type: application/json; charset=utf-8");
        $__chatId = (int) ($_POST["id"] ?? ($_GET["id"] ?? 0));
        if ($__chatId < 1) {
            echo json_encode(["ok" => false, "msg" => "Chamado inválido."], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($__chatAcao === "chat_interno_enviar") {
            $res = gestao_requisitar("POST", "/suporte/tickets/{$__chatId}/chat-interno", [], [
                "texto"       => trim(strval($_POST["texto"] ?? "")),
                "autor"       => $__gestorNome,
                "autor_login" => $__gestorLogin,
            ]);
        } else {
            $res = gestao_requisitar("GET", "/suporte/tickets/{$__chatId}/chat-interno", ["depois" => max((int) ($_GET["depois"] ?? 0), 0)]);
        }
        if (!$res["ok"]) {
            echo json_encode(["ok" => false, "msg" => strval($res["dados"]["msg"] ?? "Falha na API de suporte.")], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $__saida = ["ok" => true];
        foreach ($res["dados"]["mensagens"] ?? [] as $__m) {
            $__saida["mensagens"][] = [
                "id"     => (int) ($__m["id"] ?? 0),
                "autor"  => strval($__m["autor"] ?? ""),
                "eu"     => $__gestorLogin !== "" && strval($__m["autor_login"] ?? "") === $__gestorLogin,
                "texto"  => strval($__m["texto"] ?? ""),
                "quando" => suporte_fmt_data(strval($__m["created_at"] ?? "")),
            ];
        }
        echo json_encode($__saida, JSON_UNESCAPED_UNICODE);
        exit;
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

    // ── Fluxo do chamado (um lugar só para detalhe, listagem e filtros) ─────
    $__fluxo = [
        "aberto"                  => ["Aberto",                  "#f39c12", "fa-folder-open"],
        "em_analise"              => ["Em Análise",              "#8e44ad", "fa-search"],
        "em_desenvolvimento"      => ["Em Desenvolvimento",      "#2980b9", "fa-code"],
        "desenvolvimento_interno" => ["Desenvolvimento Interno", "#d35400", "fa-cogs"],
        "corrigido"               => ["Corrigido",               "#16a085", "fa-wrench"],
        "fechado"                 => ["Fechado",                 "#27ae60", "fa-check"],
    ];
    // Próximo passo natural de cada status: destacado entre os botões de status no detalhe.
    $__proximoPasso = [
        "aberto"                  => "em_analise",
        "em_analise"              => "em_desenvolvimento",
        "em_desenvolvimento"      => "corrigido",
        "desenvolvimento_interno" => "corrigido",
        "corrigido"               => "fechado",
        "fechado"                 => "aberto",
    ];
    $__prioridades = [
        "baixa"   => ["Baixa",   "label-default"],
        "media"   => ["Média",   "label-info"],
        "alta"    => ["Alta",    "label-warning"],
        "urgente" => ["Urgente", "label-danger"],
    ];

    if (!function_exists("gestao_badge_status")) {
        function gestao_badge_status(array $fluxo, string $status): string {
            [$rotulo, $cor] = $fluxo[$status] ?? [$status, "#95a5a6"];
            return '<span class="label" style="background:' . $cor . ';">' . htmlspecialchars($rotulo) . '</span>';
        }
    }

    // ── Ações ────────────────────────────────────────────────────────────
    // Campo "sup_acao" de propósito: o campo "acao" é interceptado pelo
    // dispatcher legado de contex20/funcoes.php (eval + exit).
    $__msg = "";
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $acao = $_POST["sup_acao"] ?? "";
        $id = (int) ($_POST["id"] ?? 0);
        if ($acao === "assumir" && $id > 0) {
            $res = gestao_requisitar("POST", "/suporte/tickets/{$id}/aceitar", [], [
                "atendente"       => $__gestorNome,
                "atendente_login" => $__gestorLogin,
            ]);
            $__msg = $res["ok"] ? "Você assumiu o chamado #{$id}." : "Erro ao assumir o chamado. " . ($res["dados"]["msg"] ?? "");
        } elseif ($acao === "status" && $id > 0) {
            $novoStatus = strval($_POST["status"] ?? "");
            if (isset($__fluxo[$novoStatus])) {
                $res = gestao_requisitar("POST", "/suporte/tickets/{$id}/status", [], ["status" => $novoStatus, "autor" => $__gestorNome]);
                $__msg = $res["ok"] ? strval($res["dados"]["msg"] ?? "Status atualizado.") : "Erro ao atualizar o status. " . ($res["dados"]["msg"] ?? "");
            }
        } elseif ($acao === "tipo" && $id > 0) {
            $res = gestao_requisitar("POST", "/suporte/tickets/{$id}/tipo", [], [
                "tipo_id" => (int) ($_POST["tipo_id"] ?? 0),
                "autor"   => $__gestorNome,
            ]);
            $__msg = $res["ok"] ? strval($res["dados"]["msg"] ?? "Tipo do chamado atualizado.") : "Erro ao alterar o tipo. " . ($res["dados"]["msg"] ?? "");
        } elseif ($acao === "prioridade" && $id > 0) {
            // Independente do status/tipo — pode ser trocada a qualquer momento do fluxo.
            $prioridade = $_POST["prioridade"] ?? "";
            if (isset($__prioridades[$prioridade])) {
                $res = gestao_requisitar("POST", "/suporte/tickets/{$id}/prioridade", [], ["prioridade" => $prioridade]);
                $__msg = $res["ok"] ? "Prioridade do chamado #{$id} atualizada." : "Erro ao alterar prioridade. " . ($res["dados"]["msg"] ?? "");
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
        } elseif ($acao === "tipo_salvar") {
            $res = gestao_requisitar("POST", "/suporte/tipos", [], [
                "id"       => (int) ($_POST["tipo_id"] ?? 0),
                "nome"     => trim(strval($_POST["tipo_nome"] ?? "")),
                "setor_id" => (int) ($_POST["tipo_setor_id"] ?? 0),
                "status"   => (($_POST["tipo_status"] ?? "ativo") === "inativo") ? "inativo" : "ativo",
            ]);
            $__msg = $res["ok"] ? strval($res["dados"]["msg"] ?? "Tipo de chamado salvo.") : "Erro ao salvar o tipo de chamado. " . ($res["dados"]["msg"] ?? "");
        } elseif ($acao === "sincronizar_funcionarios") {
            $__sync = suporte_sincronizar_membros(20);
            $__msg = $__sync["ok"] ? $__sync["msg"] : "Erro ao sincronizar os funcionários. " . $__sync["msg"];
        } elseif ($acao === "config") {
            $post = [
                "emails_notificacao" => trim(strval($_POST["emails_notificacao"] ?? "")),
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

    // ── Dados comuns ─────────────────────────────────────────────────────
    $__verId = (int) ($_GET["id"] ?? 0);
    $__verConfig = $__verId === 0 && isset($_GET["config"]);

    $__resEmpresas = gestao_requisitar("GET", "/suporte/empresas");
    $__empresas = $__resEmpresas["ok"] ? ($__resEmpresas["dados"]["empresas"] ?? []) : [];

    $__resSetores = gestao_requisitar("GET", "/suporte/setores");
    $__setores = $__resSetores["ok"] ? ($__resSetores["dados"]["setores"] ?? []) : [];

    // E-mails de aviso + SLA por prioridade (badge de SLA na lista/detalhe e a própria tela de configurações).
    $__resConfig = gestao_requisitar("GET", "/suporte/config");
    $__configAtual = $__resConfig["ok"] ? ($__resConfig["dados"]["config"] ?? []) : [];

    // Tipos de chamado: nas Configurações aparecem também os inativos, para poder reativar.
    $__resTipos = gestao_requisitar("GET", "/suporte/tipos", $__verConfig ? ["todos" => 1] : []);
    $__tipos = $__resTipos["ok"] ? ($__resTipos["dados"]["tipos"] ?? []) : [];

    // Funcionários que recebem (espelho do cadastro do Demo): Configurações, filtro e "Meus atendimentos".
    $__resAtendentes = gestao_requisitar("GET", "/suporte/atendentes");
    $__atendentes = $__resAtendentes["ok"] ? ($__resAtendentes["dados"]["atendentes"] ?? []) : [];
    $__euAtendenteId = 0;
    foreach ($__atendentes as $__a) {
        $__mesmoLogin = $__gestorLogin !== "" && strval($__a["login"] ?? "") === $__gestorLogin;
        $__mesmoEmail = $__gestorEmail !== "" && strtolower(strval($__a["email"] ?? "")) === $__gestorEmail;
        $__mesmoNome  = strval($__a["nome"] ?? "") === $__gestorNome;
        if ($__mesmoLogin || $__mesmoEmail || $__mesmoNome) {
            $__euAtendenteId = (int) ($__a["id"] ?? 0);
            break;
        }
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
                    <span class="caption-helper"><?= $__verId > 0 ? "Domínio TechPS" : ($__verConfig ? "Tipos de chamado, setores e avisos" : "Chamados de todas as empresas") ?></span>
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
<?php
    // Quem recebe, por setor.
    $__recebemPorSetor = [];
    foreach ($__atendentes as $__a) {
        $__recebemPorSetor[(int) ($__a["setor_id"] ?? 0)][] = $__a;
    }
    $__setoresAtivosIds = array_map(fn($st) => (int) ($st["id"] ?? 0), $__setores);
    $__ehDemo = suporte_dominio_mestre();
    $__emailsAtuais = strval($__configAtual["emails_notificacao"] ?? "");
    $__slaAtual = [
        "baixa"   => strval($__configAtual["sla_baixa_horas"] ?? ""),
        "media"   => strval($__configAtual["sla_media_horas"] ?? ""),
        "alta"    => strval($__configAtual["sla_alta_horas"] ?? ""),
        "urgente" => strval($__configAtual["sla_urgente_horas"] ?? ""),
    ];
?>
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> <strong>Como funciona:</strong> quem abre o chamado escolhe o <strong>tipo</strong>.
                    Cada tipo está ligado a um <strong>setor</strong>, e os funcionários desse setor recebem o chamado por e-mail e na lista.
                    Quem clicar em <strong>Assumir</strong> vira o responsável.
                    <br>Fluxo: <strong>Aberto → Em análise → Em desenvolvimento → Corrigido → Fechado</strong>.
                </div>

                <!-- Tipos de chamado -->
                <h4 style="margin-top:0;"><i class="fa fa-tags"></i> Tipos de chamado</h4>
                <p class="help-block" style="margin-top:-4px;">Escolha qual setor recebe cada tipo. O setor é o mesmo do cadastro de funcionários: quem está no setor, recebe.</p>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" style="margin-bottom:6px;">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th style="width:240px;">Setor que recebe</th>
                                <th style="width:120px;">Situação</th>
                                <th>Quem recebe hoje</th>
                                <th style="width:100px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($__tipos)): ?>
                            <tr><td colspan="5" class="text-center text-muted">Nenhum tipo de chamado cadastrado ainda.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($__tipos as $__tp):
                            $__formTipo = "form-tipo-" . (int) ($__tp["id"] ?? 0);
                            $__tpSetor = (int) ($__tp["setor_id"] ?? 0);
                            $__tpRecebem = $__recebemPorSetor[$__tpSetor] ?? [];
                        ?>
                            <tr>
                                <td><input type="text" name="tipo_nome" form="<?= $__formTipo ?>" class="form-control input-sm" maxlength="150" required value="<?= htmlspecialchars(strval($__tp["nome"] ?? ""), ENT_QUOTES) ?>"></td>
                                <td>
                                    <select name="tipo_setor_id" form="<?= $__formTipo ?>" class="form-control input-sm">
                                        <option value="0">— sem setor —</option>
                                        <?php foreach ($__setores as $__st): ?>
                                            <option value="<?= (int) ($__st["id"] ?? 0) ?>" <?= $__tpSetor === (int) ($__st["id"] ?? 0) ? "selected" : "" ?>><?= htmlspecialchars(strval($__st["nome"] ?? "")) ?></option>
                                        <?php endforeach; ?>
                                        <?php if ($__tpSetor > 0 && !in_array($__tpSetor, $__setoresAtivosIds, true)): ?>
                                            <option value="<?= $__tpSetor ?>" selected><?= htmlspecialchars(strval($__tp["setor_nome"] ?? "Setor")) ?> (inativo)</option>
                                        <?php endif; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="tipo_status" form="<?= $__formTipo ?>" class="form-control input-sm">
                                        <option value="ativo" <?= strval($__tp["status"] ?? "ativo") === "ativo" ? "selected" : "" ?>>Ativo</option>
                                        <option value="inativo" <?= strval($__tp["status"] ?? "") === "inativo" ? "selected" : "" ?>>Inativo</option>
                                    </select>
                                </td>
                                <td>
                                    <?php if ($__tpSetor < 1): ?>
                                        <span class="text-muted">Sem setor: só os e-mails gerais abaixo recebem.</span>
                                    <?php elseif (empty($__tpRecebem)): ?>
                                        <span class="text-warning"><i class="fa fa-exclamation-triangle"></i> Ninguém nesse setor ainda.</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars(implode(", ", array_map(fn($a) => strval($a["nome"] ?? ""), $__tpRecebem))) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" id="<?= $__formTipo ?>" style="margin:0;">
                                        <input type="hidden" name="sup_acao" value="tipo_salvar">
                                        <input type="hidden" name="tipo_id" value="<?= (int) ($__tp["id"] ?? 0) ?>">
                                        <button type="submit" class="btn btn-sm blue"><i class="fa fa-save"></i> Salvar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                            <tr>
                                <td><input type="text" name="tipo_nome" form="form-tipo-novo" class="form-control input-sm" maxlength="150" required placeholder="Novo tipo (ex.: Financeiro)"></td>
                                <td>
                                    <select name="tipo_setor_id" form="form-tipo-novo" class="form-control input-sm">
                                        <option value="0">— sem setor —</option>
                                        <?php foreach ($__setores as $__st): ?>
                                            <option value="<?= (int) ($__st["id"] ?? 0) ?>"><?= htmlspecialchars(strval($__st["nome"] ?? "")) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="hidden" name="tipo_status" value="ativo" form="form-tipo-novo"><span class="text-muted">Ativo</span></td>
                                <td></td>
                                <td>
                                    <form method="post" id="form-tipo-novo" style="margin:0;">
                                        <input type="hidden" name="sup_acao" value="tipo_salvar">
                                        <input type="hidden" name="tipo_id" value="0">
                                        <button type="submit" class="btn btn-sm btn-default"><i class="fa fa-plus"></i> Adicionar</button>
                                    </form>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <?php if (empty($__setores)): ?>
                    <p class="text-warning"><i class="fa fa-exclamation-triangle"></i> Nenhum setor disponível. Marque "Disponibilizar no módulo de suporte" no <strong>Cadastro de Setor</strong> do domínio Demo.</p>
                <?php endif; ?>

                <!-- Funcionários que recebem -->
                <hr style="margin:24px 0 18px;">
                <h4 style="margin-top:0;"><i class="fa fa-users"></i> Funcionários que recebem</h4>
                <p class="help-block" style="margin-top:-4px;">
                    Vem do <strong>cadastro de funcionários do domínio Demo</strong>: funcionário ativo, em setor marcado como
                    "Disponibilizar no módulo de suporte". A lista se atualiza sozinha ao salvar um funcionário ou um setor no Demo.
                </p>
                <?php if ($__ehDemo): ?>
                    <form method="post" style="margin-bottom:12px;">
                        <input type="hidden" name="sup_acao" value="sincronizar_funcionarios">
                        <button type="submit" class="btn btn-default btn-sm"><i class="fa fa-refresh"></i> Sincronizar agora</button>
                    </form>
                <?php else: ?>
                    <p class="text-muted"><i class="fa fa-info-circle"></i> Para sincronizar manualmente, use esta mesma tela no domínio Demo.</p>
                <?php endif; ?>
                <?php if (empty($__atendentes)): ?>
                    <p class="text-muted">Nenhum funcionário recebendo chamados ainda.</p>
                <?php else: ?>
                    <table class="table table-bordered table-condensed" style="max-width:820px;">
                        <thead><tr><th style="width:240px;">Setor</th><th>Funcionários</th></tr></thead>
                        <tbody>
                        <?php foreach ($__recebemPorSetor as $__stId => $__lista): ?>
                            <tr>
                                <td><?= htmlspecialchars(strval($__lista[0]["setor_nome"] ?? "") ?: "Setor fora do suporte") ?></td>
                                <td>
                                    <?php foreach ($__lista as $__i => $__a): ?>
                                        <?= $__i > 0 ? ", " : "" ?><?= htmlspecialchars(strval($__a["nome"] ?? "")) ?><?php if (trim(strval($__a["email"] ?? "")) === ""): ?> <small class="text-warning" title="Sem e-mail no cadastro: vê o chamado na lista, mas não recebe e-mail.">(sem e-mail)</small><?php endif; ?>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- Avisos gerais e SLA -->
                <hr style="margin:24px 0 18px;">
                <form method="post">
                    <input type="hidden" name="sup_acao" value="config" />
                    <div class="form-group">
                        <label><i class="fa fa-envelope"></i> E-mail(s) que recebem todo chamado novo</label>
                        <input type="text" name="emails_notificacao" class="form-control" style="max-width:520px;"
                               value="<?= htmlspecialchars($__emailsAtuais) ?>"
                               placeholder="suporte@techps.com.br, outro@techps.com.br" />
                        <span class="help-block">Separe vários e-mails por vírgula. Recebem todos os chamados, de qualquer tipo — além dos funcionários do setor.</span>
                    </div>

                    <h4 style="margin-top:20px;"><i class="fa fa-clock-o"></i> SLA por prioridade</h4>
                    <p class="help-block" style="margin-top:-6px;">
                        Prazo máximo, em horas corridas, da abertura até o chamado ser <strong>Fechado</strong>. Deixe em branco para não cobrar SLA nesse nível.
                        Chamado ainda aberto é comparado com agora, para já sinalizar quem está estourando o prazo.
                    </p>
                    <div class="row">
                        <?php foreach (["baixa" => ["Baixa", "label-default", "72"], "media" => ["Média", "label-info", "48"], "alta" => ["Alta", "label-warning", "24"], "urgente" => ["Urgente", "label-danger", "4"]] as $__nivel => [$__rotuloNivel, $__classeNivel, $__exemplo]): ?>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label><span class="label <?= $__classeNivel ?>"><?= $__rotuloNivel ?></span></label>
                                <div class="input-group">
                                    <input type="number" min="1" step="1" name="sla_<?= $__nivel ?>_horas" class="form-control" value="<?= htmlspecialchars($__slaAtual[$__nivel]) ?>" placeholder="Ex.: <?= $__exemplo ?>" />
                                    <span class="input-group-addon">horas</span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" class="btn blue"><i class="fa fa-save"></i> Salvar configurações</button>
                </form>

<?php elseif ($__verId > 0): ?>
<?php
    $__res = gestao_requisitar("GET", "/suporte/tickets/{$__verId}");
    $__ticket = $__res["ok"] ? ($__res["dados"]["ticket"] ?? []) : [];
    $__arquivos = $__res["ok"] ? ($__res["dados"]["arquivos"] ?? []) : [];
    $__comentarios = $__res["ok"] ? ($__res["dados"]["comentarios"] ?? []) : [];
    $__eventos = $__res["ok"] ? ($__res["dados"]["eventos"] ?? []) : [];
    $__equipeSetor = $__res["ok"] ? ($__res["dados"]["equipe_setor"] ?? []) : [];
?>
    <?php if (empty($__ticket)): ?>
                <div class="alert alert-danger">Chamado não encontrado ou falha na API de suporte.</div>
    <?php else: ?>
        <?php
            $__status = strval($__ticket["status"] ?? "aberto");
            $__prioridade = strval($__ticket["prioridade"] ?? "media");
            [$__prioridadeLabel, $__prioridadeClasse] = $__prioridades[$__prioridade] ?? $__prioridades["media"];
            $__sla = suporte_sla_status($__prioridade, strval($__ticket["created_at"] ?? ""), strval($__ticket["fechado_em"] ?? ""), $__configAtual);
            $__slaBadge = '<span class="label label-' . $__sla["classe"] . '">SLA: ' . htmlspecialchars($__sla["label"]) . ($__sla["horas"] !== null ? " ({$__sla['horas']}h)" : "") . '</span>';
            $__responsavel = trim(strval($__ticket["atendente_nome"] ?? ""));
            $__tipoAtual = (int) ($__ticket["tipo_id"] ?? 0);
            $__tipoNome = trim(strval($__ticket["tipo_nome"] ?? ""));
            $__setorNome = trim(strval($__ticket["setor_nome"] ?? ""));
            $__proxStatus = $__proximoPasso[$__status] ?? "em_analise";
            $__tiposAtivosIds = array_map(fn($t) => (int) ($t["id"] ?? 0), $__tipos);
        ?>
        <table class="table table-striped table-bordered">
            <tr><th style="width:150px;">Empresa</th><td><?= htmlspecialchars(strval($__ticket["empresa_key"] ?? "")) ?> — <?= htmlspecialchars(strval($__ticket["empresa_nome"] ?? "")) ?></td></tr>
            <tr><th>Tipo</th><td><?= $__tipoNome !== "" ? htmlspecialchars($__tipoNome) : '<span class="text-muted">Não classificado</span>' ?></td></tr>
            <tr><th>Setor</th><td><?= $__setorNome !== "" ? htmlspecialchars($__setorNome) : '<span class="text-muted">Sem setor</span>' ?></td></tr>
            <tr><th>Solicitante</th><td><?= htmlspecialchars(strval($__ticket["user_nome"] ?? "")) ?> (<?= htmlspecialchars(strval($__ticket["user_login"] ?? "")) ?>)</td></tr>
            <tr><th>E-mail</th><td><?= htmlspecialchars(strval($__ticket["user_email"] ?? "") ?: "—") ?></td></tr>
            <tr><th>Data de abertura</th><td><?= htmlspecialchars(suporte_fmt_data(strval($__ticket["created_at"] ?? ""))) ?></td></tr>
            <tr><th>Status</th><td><?= gestao_badge_status($__fluxo, $__status) ?></td></tr>
            <tr><th>Prioridade</th><td><span class="label <?= $__prioridadeClasse ?>"><?= htmlspecialchars($__prioridadeLabel) ?></span> <?= $__slaBadge ?></td></tr>
            <tr><th>Responsável</th><td><?= $__responsavel !== "" ? htmlspecialchars($__responsavel) : '<span class="text-muted">Ninguém assumiu ainda</span>' ?></td></tr>
            <tr><th>Página</th><td style="word-break:break-all;"><?= htmlspecialchars(strval($__ticket["pagina_url"] ?? "")) ?></td></tr>
        </table>

        <div class="well">
            <h4 style="margin-top:0;">Descrição do problema</h4>
            <p style="white-space:pre-wrap;"><?= htmlspecialchars(strval($__ticket["descricao"] ?? "")) ?></p>
        </div>

        <!-- Atendimento: responsável, status (um botão por etapa) e classificação -->
        <style>
            .sup-atend { border:1px solid #e5e5e5; border-radius:6px; background:#fcfcfc; margin-bottom:18px; }
            .sup-atend-secao { padding:12px 14px; }
            .sup-atend-secao + .sup-atend-secao { border-top:1px solid #eee; }
            .sup-atend-titulo { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#888; margin:0 0 8px; }
            .sup-atend-topo { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
            .sup-atend-topo h4 { margin:0; }
            .sup-atend-topo .sup-resp { margin-left:auto; display:flex; align-items:center; gap:10px; }
            .sup-status-form { display:flex; flex-wrap:wrap; gap:6px; margin:0; }
            .sup-status-btn { background:#fff; border:1px solid; border-radius:16px; padding:5px 12px; font-size:12px; line-height:1.4; cursor:pointer; transition:background .15s; }
            .sup-status-btn:hover { background:#f3f3f3; }
            .sup-status-btn.atual { color:#fff !important; cursor:default; }
            .sup-status-btn.sugerido { border-width:2px; font-weight:700; padding:4px 11px; }
            .sup-classif .form-group { margin-bottom:0; }
            .sup-classif label { font-size:12px; color:#666; margin-bottom:4px; }
            @media (max-width: 767px) { .sup-classif .col-sm-6 + .col-sm-6 { margin-top:10px; } .sup-atend-topo .sup-resp { margin-left:0; } }
        </style>
        <div class="sup-atend">
            <div class="sup-atend-secao">
                <div class="sup-atend-topo">
                    <h4><i class="fa fa-tasks"></i> Atendimento</h4>
                    <div class="sup-resp">
                        <span>
                            <i class="fa fa-user"></i> Responsável:
                            <?= $__responsavel !== "" ? "<strong>" . htmlspecialchars($__responsavel) . "</strong>" : '<span class="text-muted">ninguém assumiu</span>' ?>
                        </span>
                        <?php if ($__responsavel === "" && $__status !== "fechado"): ?>
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="sup_acao" value="assumir" />
                                <input type="hidden" name="id" value="<?= $__verId ?>" />
                                <button type="submit" class="btn blue btn-sm"><i class="fa fa-hand-paper-o"></i> Assumir chamado</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <p style="margin:8px 0 0;color:#777;font-size:12px;">
                    <i class="fa fa-users"></i> Quem recebe:
                    <?php if ($__setorNome === ""): ?>
                        <span class="text-muted">chamado sem setor — só os e-mails gerais das Configurações.</span>
                    <?php elseif (empty($__equipeSetor)): ?>
                        setor <strong><?= htmlspecialchars($__setorNome) ?></strong> — <span class="text-warning">ninguém nesse setor ainda.</span>
                    <?php else: ?>
                        setor <strong><?= htmlspecialchars($__setorNome) ?></strong> —
                        <?= htmlspecialchars(implode(", ", array_map(fn($m) => strval($m["nome"] ?? ""), $__equipeSetor))) ?>
                    <?php endif; ?>
                </p>
            </div>

            <div class="sup-atend-secao">
                <p class="sup-atend-titulo">Status do chamado <span style="font-weight:400;text-transform:none;letter-spacing:0;">— clique para mudar; em destaque, o próximo passo sugerido</span></p>
                <form method="post" class="sup-status-form">
                    <input type="hidden" name="sup_acao" value="status" />
                    <input type="hidden" name="id" value="<?= $__verId ?>" />
                    <?php foreach ($__fluxo as $__chaveStatus => [$__rotuloStatus, $__corStatus, $__iconeStatus]):
                        $__ehAtual = $__chaveStatus === $__status;
                        $__ehSugerido = !$__ehAtual && $__chaveStatus === $__proxStatus;
                        $__reabrir = $__status === "fechado" && $__chaveStatus === "aberto";
                        $__confirmar = $__chaveStatus === "fechado" ? "Fechar o chamado?" : ($__reabrir ? "Reabrir o chamado?" : "");
                    ?>
                        <?php if ($__ehAtual): ?>
                            <span class="sup-status-btn atual" style="background:<?= $__corStatus ?>;border-color:<?= $__corStatus ?>;" title="Status atual">
                                <i class="fa <?= $__iconeStatus ?>"></i> <?= htmlspecialchars($__rotuloStatus) ?>
                            </span>
                        <?php else: ?>
                            <button type="submit" name="status" value="<?= $__chaveStatus ?>"
                                    class="sup-status-btn<?= $__ehSugerido ? " sugerido" : "" ?>"
                                    style="border-color:<?= $__corStatus ?>;color:<?= $__corStatus ?>;"
                                    title="<?= $__ehSugerido ? "Próximo passo sugerido" : "Mudar para " . htmlspecialchars($__rotuloStatus, ENT_QUOTES) ?>"
                                    <?= $__confirmar !== "" ? "onclick=\"return confirm('" . $__confirmar . "');\"" : "" ?>>
                                <i class="fa <?= $__reabrir ? "fa-undo" : $__iconeStatus ?>"></i> <?= htmlspecialchars($__reabrir ? "Reabrir" : $__rotuloStatus) ?>
                            </button>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </form>
            </div>

            <div class="sup-atend-secao sup-classif">
                <div class="row">
                    <div class="col-sm-6">
                        <form method="post" class="form-group">
                            <input type="hidden" name="sup_acao" value="tipo" />
                            <input type="hidden" name="id" value="<?= $__verId ?>" />
                            <label><i class="fa fa-tag"></i> Tipo <small class="text-muted">(trocar encaminha ao setor do tipo)</small></label>
                            <div class="input-group input-group-sm">
                                <select name="tipo_id" class="form-control" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($__tipos as $__tp): ?>
                                        <option value="<?= (int) ($__tp["id"] ?? 0) ?>" <?= $__tipoAtual === (int) ($__tp["id"] ?? 0) ? "selected" : "" ?>><?= htmlspecialchars(strval($__tp["nome"] ?? "")) ?></option>
                                    <?php endforeach; ?>
                                    <?php if ($__tipoAtual > 0 && !in_array($__tipoAtual, $__tiposAtivosIds, true)): ?>
                                        <option value="<?= $__tipoAtual ?>" selected><?= htmlspecialchars($__tipoNome) ?> (inativo)</option>
                                    <?php endif; ?>
                                </select>
                                <span class="input-group-btn"><button type="submit" class="btn btn-default"><i class="fa fa-save"></i> Salvar</button></span>
                            </div>
                        </form>
                    </div>
                    <div class="col-sm-6">
                        <!-- Prioridade: independente de status/tipo — pode ser trocada em qualquer ponto do fluxo. -->
                        <form method="post" class="form-group">
                            <input type="hidden" name="sup_acao" value="prioridade" />
                            <input type="hidden" name="id" value="<?= $__verId ?>" />
                            <label><i class="fa fa-flag"></i> Prioridade <?= $__slaBadge ?></label>
                            <div class="input-group input-group-sm">
                                <select name="prioridade" class="form-control" required>
                                    <?php foreach ($__prioridades as $__chavePrioridade => [$__rotuloPrioridade]): ?>
                                        <option value="<?= $__chavePrioridade ?>" <?= $__prioridade === $__chavePrioridade ? "selected" : "" ?>><?= htmlspecialchars($__rotuloPrioridade) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="input-group-btn"><button type="submit" class="btn btn-default"><i class="fa fa-save"></i> Salvar</button></span>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat interno da equipe (a empresa não vê) -->
        <style>
            #chatInterno { border:1px solid #f0d58c; border-radius:6px; background:#fffdf5; margin-bottom:18px; }
            #chatInterno .chat-topo { padding:10px 14px; border-bottom:1px solid #f0e2b6; display:flex; align-items:center; gap:8px; }
            #chatInterno .chat-lista { height:280px; overflow-y:auto; padding:12px 14px; }
            #chatInterno .chat-msg { max-width:75%; margin-bottom:10px; }
            #chatInterno .chat-msg.eu { margin-left:auto; text-align:right; }
            #chatInterno .chat-meta { font-size:11px; color:#999; margin-bottom:2px; }
            #chatInterno .chat-balao { display:inline-block; text-align:left; white-space:pre-wrap; word-break:break-word; padding:7px 11px; border-radius:10px; background:#fff; border:1px solid #e6e6e6; }
            #chatInterno .chat-msg.eu .chat-balao { background:#dcecfb; border-color:#c3dcf3; }
            #chatInterno .chat-form { display:flex; gap:8px; padding:10px 14px; border-top:1px solid #f0e2b6; }
            #chatInterno textarea { flex:1; resize:none; padding:7px 10px; border:1px solid #ddd; border-radius:4px; font-size:13px; }
        </style>
        <div id="chatInterno" data-id="<?= $__verId ?>">
            <div class="chat-topo">
                <i class="fa fa-lock text-warning"></i>
                <strong>Chat interno da equipe</strong>
                <small class="text-muted">— visível só para os atendentes, a empresa não vê.</small>
            </div>
            <div class="chat-lista" id="chatInternoLista">
                <p class="text-muted chat-vazio" style="margin:0;"><i class="fa fa-spinner fa-spin"></i> Carregando...</p>
            </div>
            <form class="chat-form" id="chatInternoForm">
                <textarea id="chatInternoTexto" rows="2" maxlength="2000" placeholder="Mensagem para a equipe (Enter envia, Shift+Enter quebra linha)"></textarea>
                <button type="submit" class="btn yellow-gold" id="chatInternoEnviar"><i class="fa fa-paper-plane"></i> Enviar</button>
            </form>
        </div>
        <script>
        (function () {
            var caixa = document.getElementById('chatInterno');
            var lista = document.getElementById('chatInternoLista');
            var form = document.getElementById('chatInternoForm');
            var campo = document.getElementById('chatInternoTexto');
            var botao = document.getElementById('chatInternoEnviar');
            var ticketId = caixa.getAttribute('data-id');
            var ultimoId = 0;
            var buscando = false;

            function adicionar(m) {
                var vazio = lista.querySelector('.chat-vazio');
                if (vazio) vazio.remove();
                var item = document.createElement('div');
                item.className = 'chat-msg' + (m.eu ? ' eu' : '');
                var meta = document.createElement('div');
                meta.className = 'chat-meta';
                meta.textContent = (m.eu ? 'Você' : m.autor) + ' · ' + m.quando;
                var balao = document.createElement('div');
                balao.className = 'chat-balao';
                balao.textContent = m.texto;
                item.appendChild(meta);
                item.appendChild(balao);
                lista.appendChild(item);
            }

            function buscar() {
                if (buscando) return;
                buscando = true;
                fetch('gestao.php?chat_interno=chat_interno_listar&id=' + ticketId + '&depois=' + ultimoId, { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        var vazio = lista.querySelector('.chat-vazio');
                        if (!d.ok) {
                            if (vazio) vazio.textContent = 'Não foi possível carregar o chat interno.';
                            return;
                        }
                        var novas = d.mensagens || [];
                        // Rola para o fim na primeira carga ou se o atendente já estava no fim (não atrapalha quem lê o histórico).
                        var rolar = ultimoId === 0 || lista.scrollHeight - lista.scrollTop - lista.clientHeight < 40;
                        novas.forEach(function (m) {
                            if (m.id > ultimoId) { adicionar(m); ultimoId = m.id; }
                        });
                        if (ultimoId === 0 && vazio) vazio.textContent = 'Nenhuma mensagem ainda. Combine aqui o atendimento com a equipe.';
                        if (novas.length && rolar) lista.scrollTop = lista.scrollHeight;
                    })
                    .catch(function () {})
                    .then(function () { buscando = false; });
            }

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var texto = campo.value.trim();
                if (!texto) return;
                botao.disabled = true;
                var corpo = new URLSearchParams();
                corpo.append('sup_acao', 'chat_interno_enviar');
                corpo.append('id', ticketId);
                corpo.append('texto', texto);
                fetch('gestao.php', { method: 'POST', credentials: 'same-origin', body: corpo })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (!d.ok) { alert(d.msg || 'Erro ao enviar a mensagem.'); return; }
                        campo.value = '';
                        lista.scrollTop = lista.scrollHeight;
                        // Se a consulta automática estiver em andamento, tenta de novo logo depois dela.
                        buscando ? setTimeout(buscar, 700) : buscar();
                    })
                    .catch(function () { alert('Sem comunicação com o servidor de suporte.'); })
                    .then(function () { botao.disabled = false; campo.focus(); });
            });

            campo.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    form.requestSubmit ? form.requestSubmit() : botao.click();
                }
            });

            buscar();
            // Só consulta com a aba visível, para não martelar a API com abas esquecidas abertas.
            setInterval(function () { if (!document.hidden) buscar(); }, 5000);
            document.addEventListener('visibilitychange', function () { if (!document.hidden) buscar(); });
        })();
        </script>

        <h4><i class="fa fa-paperclip"></i> Anexos (<?= count($__arquivos) ?>)</h4>
        <?= suporte_render_anexos($__arquivos, $__verId, "imagem_gestao.php") ?>

        <!-- Comentários -->
        <h4 style="margin-top:25px;"><i class="fa fa-comments"></i> Comentários (<?= count($__comentarios) ?>)</h4>
        <?php if (empty($__comentarios)): ?>
            <p class="text-muted"><i class="fa fa-info-circle"></i> Nenhum comentário ainda.</p>
        <?php endif; ?>
        <?php foreach ($__comentarios as $__c): ?>
            <?php $__bg = (strval($__c["autor_tipo"] ?? "") === "empresa") ? "#e8f0fe" : "#f9f9f9"; ?>
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
    // ── Listagem (todas as empresas) ─────────────────────────────────────
    $__fEmpresa    = trim(strval($_GET["empresa"] ?? ""));
    $__fTipoId     = (int) ($_GET["tipo_id"] ?? 0);
    $__fSetorId    = (int) ($_GET["setor_id"] ?? 0);
    $__fStatus     = trim(strval($_GET["status"] ?? ""));
    $__fPrioridade = trim(strval($_GET["prioridade"] ?? ""));
    $__fInicio     = trim(strval($_GET["data_inicio"] ?? ""));
    $__fFim        = trim(strval($_GET["data_fim"] ?? ""));
    $__fPagina     = max((int) ($_GET["pagina"] ?? 1), 1);
    // "" = todos | "sem" = sem responsável | número = id do funcionário
    $__fAtendente  = trim(strval($_GET["atendente_id"] ?? ""));

    $__queryFiltro = ["pagina" => $__fPagina, "limit" => 25];
    if ($__fEmpresa !== "") $__queryFiltro["empresa"] = $__fEmpresa;
    if ($__fTipoId > 0) $__queryFiltro["tipo_id"] = $__fTipoId;
    if ($__fSetorId > 0) $__queryFiltro["setor_id"] = $__fSetorId;
    if (isset($__fluxo[$__fStatus])) $__queryFiltro["status"] = $__fStatus;
    if (isset($__prioridades[$__fPrioridade])) $__queryFiltro["prioridade"] = $__fPrioridade;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $__fInicio)) $__queryFiltro["data_inicio"] = $__fInicio;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $__fFim)) $__queryFiltro["data_fim"] = $__fFim;
    if ($__fAtendente === "sem" || (int) $__fAtendente > 0) $__queryFiltro["atendente_id"] = $__fAtendente;

    $__res = gestao_requisitar("GET", "/suporte/tickets", $__queryFiltro);
    $__tickets = $__res["ok"] ? ($__res["dados"]["tickets"] ?? []) : [];
    $__total = $__res["ok"] ? (int) ($__res["dados"]["total"] ?? 0) : 0;
    $__paginas = max((int) ceil($__total / 25), 1);

    function gestao_manter(array $params): string {
        return "?" . http_build_query(array_filter($params, fn($v) => strval($v) !== "" && strval($v) !== "0"));
    }

    // O painel de filtros só nasce aberto quando há filtro aplicado.
    $__temFiltro = $__fEmpresa !== "" || $__fTipoId > 0 || $__fSetorId > 0 || $__fAtendente !== ""
        || $__fStatus !== "" || $__fPrioridade !== "" || $__fInicio !== "" || $__fFim !== "";
?>
                <?php if (!$__res["ok"]): ?>
                    <div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> Não foi possível consultar a API de suporte.</div>
                <?php endif; ?>

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
                                    <label>Tipo</label>
                                    <select name="tipo_id" class="form-control">
                                        <option value="">Todos</option>
                                        <?php foreach ($__tipos as $__tp): ?>
                                            <option value="<?= (int) ($__tp["id"] ?? 0) ?>" <?= ($__fTipoId === (int) ($__tp["id"] ?? 0)) ? "selected" : "" ?>><?= htmlspecialchars(strval($__tp["nome"] ?? "")) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label>Setor</label>
                                    <select name="setor_id" class="form-control">
                                        <option value="">Todos</option>
                                        <?php foreach ($__setores as $__st): ?>
                                            <option value="<?= (int) ($__st["id"] ?? 0) ?>" <?= ($__fSetorId === (int) ($__st["id"] ?? 0)) ? "selected" : "" ?>><?= htmlspecialchars(strval($__st["nome"] ?? "")) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status" class="form-control">
                                        <option value="">Todos</option>
                                        <?php foreach ($__fluxo as $__chaveStatus => [$__rotuloStatus]): ?>
                                            <option value="<?= $__chaveStatus ?>" <?= ($__fStatus === $__chaveStatus) ? "selected" : "" ?>><?= htmlspecialchars($__rotuloStatus) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label>Responsável</label>
                                    <select name="atendente_id" class="form-control">
                                        <option value="">Todos</option>
                                        <option value="sem" <?= ($__fAtendente === "sem") ? "selected" : "" ?>>— sem responsável —</option>
                                        <?php foreach ($__atendentes as $__a): ?>
                                            <option value="<?= (int) ($__a["id"] ?? 0) ?>" <?= ((int) $__fAtendente === (int) ($__a["id"] ?? 0)) ? "selected" : "" ?>><?= htmlspecialchars(strval($__a["nome"] ?? "")) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label>Prioridade</label>
                                    <select name="prioridade" class="form-control">
                                        <option value="">Todas</option>
                                        <?php foreach ($__prioridades as $__chavePrioridade => [$__rotuloPrioridade]): ?>
                                            <option value="<?= $__chavePrioridade ?>" <?= ($__fPrioridade === $__chavePrioridade) ? "selected" : "" ?>><?= htmlspecialchars($__rotuloPrioridade) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <div class="form-group">
                                    <label>Aberto de</label>
                                    <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($__fInicio) ?>" />
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <div class="form-group">
                                    <label>até</label>
                                    <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($__fFim) ?>" />
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-12">
                                <div class="form-group">
                                    <label style="display:block;">&nbsp;</label>
                                    <button type="submit" class="btn blue"><i class="fa fa-search"></i> Filtrar</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <style>
                    /* .label do Bootstrap é inline: empilhado, não reserva espaço vertical e os
                       badges saem colados. inline-block + margem resolve para toda a listagem. */
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
                            <th style="width:140px;">Responsável</th>
                            <th style="width:130px;">Situação</th>
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
                                $__prioridadeT = strval($__t["prioridade"] ?? "media");
                                [$__prioridadeLabelT, $__prioridadeClasseT] = $__prioridades[$__prioridadeT] ?? $__prioridades["media"];
                                $__slaT = suporte_sla_status($__prioridadeT, strval($__t["created_at"] ?? ""), strval($__t["fechado_em"] ?? ""), $__configAtual);
                                // A URL inteira poluía a linha: fica só o nome do arquivo, com a URL completa no title e no destino.
                                $__pag = trim(strval($__t["pagina_url"] ?? ""));
                                $__pagCurta = $__pag !== "" ? basename(strval(parse_url($__pag, PHP_URL_PATH) ?: "")) : "";
                                $__atNome = trim(strval($__t["atendente_nome"] ?? ""));
                                $__tipoNomeT = trim(strval($__t["tipo_nome"] ?? ""));
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
                                        <?php if ($__tipoNomeT !== ""): ?>
                                            <small class="text-muted"><i class="fa fa-tag"></i> <?= htmlspecialchars($__tipoNomeT) ?></small>
                                        <?php endif; ?>
                                        <?php if ($__pagCurta !== ""): ?>
                                            <small><a href="<?= htmlspecialchars($__pag, ENT_QUOTES) ?>" target="_blank" class="text-muted" title="<?= htmlspecialchars($__pag, ENT_QUOTES) ?>"><i class="fa fa-external-link"></i> <?= htmlspecialchars($__pagCurta) ?></a></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($__atNome !== ""): ?>
                                        <?= htmlspecialchars($__atNome) ?>
                                    <?php else: ?>
                                        <small class="text-muted">— sem responsável —</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= gestao_badge_status($__fluxo, strval($__t["status"] ?? "aberto")) ?>
                                    <div class="linha-meta">
                                        <span class="label <?= $__prioridadeClasseT ?>"><?= htmlspecialchars($__prioridadeLabelT) ?></span>
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
                                    <a href="<?= gestao_manter(["empresa" => $__fEmpresa, "tipo_id" => $__fTipoId, "setor_id" => $__fSetorId, "atendente_id" => $__fAtendente, "status" => $__fStatus, "prioridade" => $__fPrioridade, "data_inicio" => $__fInicio, "data_fim" => $__fFim, "pagina" => $__p]) ?>"><?= $__p ?></a>
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
