<?php
/* ============================================================
   Torre de Comando — motor de dados + render
   Extraído para função compartilhada porque agora é exibido em
   dois lugares: como tela própria (dashboard.php) e embutido
   direto na tela de boas-vindas pós-login (index.php::showWelcome()),
   sem exigir navegação nenhuma.
   Pressupõe que quem inclui este arquivo já rodou conecta.php antes.
   ============================================================ */

// ── Helpers de tempo/valor ──────────────────────────────────────────

function torre_hhmm_para_horas(string $valor): float {
    $valor = trim($valor);
    if ($valor === "") return 0.0;
    $neg = false;
    if ($valor[0] === "-") { $neg = true; $valor = substr($valor, 1); }
    $partes = explode(":", $valor);
    $h = intval($partes[0] ?? 0);
    $m = intval($partes[1] ?? 0);
    $total = $h + ($m / 60);
    return $neg ? -$total : $total;
}

function torre_horas_fmt(float $horas, int $casas = 0): string {
    $sinal = $horas < 0 ? "-" : "";
    return $sinal . number_format(abs($horas), $casas, ",", ".");
}

function torre_moeda_fmt(float $valor): string {
    return "R$ " . number_format($valor, 0, ",", ".");
}

function torre_mes_label(string $mesIso): string {
    $meses = [1=>"Janeiro",2=>"Fevereiro",3=>"Março",4=>"Abril",5=>"Maio",6=>"Junho",7=>"Julho",8=>"Agosto",9=>"Setembro",10=>"Outubro",11=>"Novembro",12=>"Dezembro"];
    $partes = explode("-", $mesIso);
    $ano = $partes[0] ?? "";
    $mes = intval($partes[1] ?? 0);
    return ($meses[$mes] ?? $mesIso) . "/" . $ano;
}

function torre_data_fmt(string $data): string {
    $t = strtotime($data);
    return $t ? date("d/m/Y", $t) : $data;
}

function torre_meses_disponiveis(string $baseDir): array {
    if (!is_dir($baseDir)) return [];
    $meses = array_values(array_filter(scandir($baseDir), fn($d) => preg_match('/^\d{4}-\d{2}$/', $d)));
    return $meses;
}

// ── Preferência de visibilidade por usuário ─────────────────────────

function torre_manifesto(): array {
    return [
        "operacao" => [
            "titulo" => "Operação agora",
            "itens" => [
                "tile_ativos"          => "Cartão: Ativos",
                "tile_atividade"       => "Cartão: Em atividade",
                "tile_pausa"           => "Cartão: Em pausa",
                "tile_fora"            => "Cartão: Fora de jornada",
                "tile_criticas"        => "Cartão: Jornadas críticas",
                "tile_frota_rastreada" => "Cartão: Frota rastreada",
                "painel_pausa"         => "Gráfico: visão geral do status agora",
                "painel_ocupacao"      => "Gráfico: status agora por ocupação",
            ],
        ],
        "frota" => [
            "titulo" => "Frota & localização",
            "itens" => [
                "painel_disponibilidade" => "Gráfico: disponibilidade de frota",
                "painel_mapa"             => "Mapa ao vivo",
            ],
        ],
        "pessoas" => [
            "titulo" => "Pessoas & risco",
            "itens" => [
                "tile_ferias_hoje"     => "Cartão: de férias hoje",
                "tile_afastamentos"    => "Cartão: afastamentos hoje",
                "tile_abonos_mes"      => "Cartão: abonos no mês",
                "tile_cnh"             => "Cartão: CNH vencendo",
                "tile_advertencias"    => "Cartão: advertências candidatas",
                "tile_nc_alta"         => "Cartão: não conformidade alta",
                "lista_ferias_hoje"    => "Lista: quem está de férias hoje",
                "lista_ferias_proximas"=> "Lista: quem entra de férias",
                "lista_cnh"            => "Lista: CNH vencendo",
                "painel_nc"            => "Gráfico: não conformidade por gravidade",
                "painel_abonos"        => "Gráfico: abonos por motivo",
                "painel_ranking_nc"    => "Ranking: quem mais precisa de atenção",
                "painel_tendencia_nc"  => "Gráfico: tendência de não conformidade",
                "painel_tendencia_abonos" => "Gráfico: tendência de abonos",
            ],
        ],
        "custo" => [
            "titulo" => "Custo da jornada",
            "itens" => [
                "tile_he50"       => "Cartão: HE 50%",
                "tile_he100"      => "Cartão: HE 100%",
                "tile_noturno"    => "Cartão: adicional noturno",
                "tile_espera"     => "Cartão: espera indenizada",
                "tile_custo"      => "Cartão: custo estimado",
                "tile_saldo"      => "Cartão: saldo final",
                "painel_tendencia"=> "Gráfico: tendência de horas extras",
            ],
        ],
        "cadastros" => [
            "titulo" => "Cadastros",
            "itens" => [
                "tile_c_placas"      => "Cartão: placas cadastradas",
                "tile_c_setores"     => "Cartão: setores cadastrados",
                "tile_c_subsetores"  => "Cartão: subsetores cadastrados",
                "tile_c_facial"      => "Cartão: cadastro facial concluído",
                "tile_c_feriados"    => "Cartão: feriados neste mês",
                "tile_c_perfilacesso"=> "Cartão: perfis de acesso cadastrados",
                "tile_c_tipodoc"     => "Cartão: tipos de documento cadastrados",
                "tile_c_motivos"     => "Cartão: motivos cadastrados",
                "tile_c_epi"         => "Cartão: EPI cadastrados (catálogo)",
                "tile_c_epientregue" => "Cartão: EPI entregues",
                "tile_c_ajustes"     => "Cartão: ajustes de ponto solicitados",
                "tile_c_escalas"     => "Cartão: escalas cadastradas",
                "tile_c_diarias"     => "Cartão: diárias cadastradas",
                "tile_c_assinenv"    => "Cartão: assinaturas enviadas",
                "tile_c_assinpend"   => "Cartão: assinaturas pendentes",
                "tile_c_assinconc"   => "Cartão: assinaturas concluídas",
            ],
        ],
    ];
}

function torre_visivel(string $chave, array $ocultos): bool {
    return !in_array($chave, $ocultos, true);
}

// Legenda explicativa — sempre visível (não é tooltip/hover), usada em todo cartão e painel.
function torre_info(string $texto): string {
    return '<span class="tc-info-tip">'.htmlspecialchars($texto).'</span>';
}

// Link "ver tela completa" — o dashboard só mostra e aponta pra tela real; nenhuma ação/gravação acontece aqui.
function torre_link(string $href, string $texto = "Ver tela completa"): string {
    return '<a href="'.htmlspecialchars($href).'" class="tc-tile-link">'.htmlspecialchars($texto).' <i data-lucide="arrow-right" style="width:11px;height:11px;"></i></a>';
}

// Ordem padrão das seções (usada quando o usuário nunca reordenou nada).
function torre_ordem_secoes_padrao(): array {
    return ["operacao", "frota", "pessoas", "custo", "cadastros"];
}

// Decodifica o JSON salvo em torr_tx_ocultos — aceita tanto o formato antigo (lista simples de
// chaves ocultas) quanto o formato atual (objeto com ocultos + ordem de seções + ordem de cartões).
function torre_decodificar_config(?string $json): array {
    $padrao = ["ocultos" => [], "ordemSecoes" => torre_ordem_secoes_padrao(), "ordemItens" => []];
    if (empty($json)) return $padrao;
    $dados = json_decode($json, true);
    if (!is_array($dados)) return $padrao;

    if (!array_key_exists("ocultos", $dados) && !array_key_exists("ordemSecoes", $dados)) {
        // Formato antigo: o JSON inteiro era a lista de chaves ocultas.
        $padrao["ocultos"] = array_values(array_map("strval", $dados));
        return $padrao;
    }

    $padrao["ocultos"] = is_array($dados["ocultos"] ?? null) ? array_values(array_map("strval", $dados["ocultos"])) : [];

    $ordemSecoes = is_array($dados["ordemSecoes"] ?? null) ? array_values(array_map("strval", $dados["ordemSecoes"])) : [];
    foreach (torre_ordem_secoes_padrao() as $s) { if (!in_array($s, $ordemSecoes, true)) $ordemSecoes[] = $s; }
    $ordemSecoes = array_values(array_intersect($ordemSecoes, torre_ordem_secoes_padrao()));
    $padrao["ordemSecoes"] = !empty($ordemSecoes) ? $ordemSecoes : torre_ordem_secoes_padrao();

    $ordemItens = $dados["ordemItens"] ?? null;
    $padrao["ordemItens"] = is_array($ordemItens) ? $ordemItens : [];

    return $padrao;
}

// Retorna o CSS "order:N" (via style inline) de uma seção ou cartão, a partir da lista salva pelo usuário.
// Itens não encontrados na lista salva (ex.: recém-adicionados ao sistema) vão para o fim, na ordem natural.
function torre_estilo_ordem(array $lista, string $chave, int $posicaoNatural): string {
    $idx = array_search($chave, $lista, true);
    $ordem = $idx === false ? ($posicaoNatural + 1000) : $idx;
    return ' style="order:' . intval($ordem) . ';"';
}

// Cria a tabela (ou migra uma versão antiga, sem nome de perfil, para o formato atual).
function torre_ensure_preferencia_schema(): void {
    $dbRow = mysqli_fetch_assoc(query("SELECT DATABASE() AS db"));
    $db = strval($dbRow["db"] ?? "");
    if ($db === "") return;

    $exists = mysqli_fetch_assoc(query(
        "SELECT 1 AS ok FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'torre_preferencia' LIMIT 1",
        "s", [$db]
    ));
    if (empty($exists)) {
        query(
            "CREATE TABLE IF NOT EXISTS torre_preferencia (
                torr_nb_id INT AUTO_INCREMENT PRIMARY KEY,
                torr_nb_usuario INT NOT NULL,
                torr_tx_nome VARCHAR(100) NOT NULL DEFAULT 'Padrão',
                torr_tx_ocultos TEXT NOT NULL,
                torr_tx_dataCadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                torr_tx_dataAtualiza DATETIME NOT NULL,
                UNIQUE KEY uniq_usuario_nome (torr_nb_usuario, torr_tx_nome)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        return;
    }

    // Migração idempotente: bancos que já tinham a versão antiga (1 preferência por usuário, sem nome).
    $cols = mysqli_fetch_all(query(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'torre_preferencia'",
        "s", [$db]
    ), MYSQLI_ASSOC) ?: [];
    $colNames = array_map(fn($r) => strval($r["COLUMN_NAME"] ?? ""), $cols);
    $has = array_flip($colNames);

    if (!isset($has["torr_tx_nome"])) {
        query("ALTER TABLE torre_preferencia ADD COLUMN torr_tx_nome VARCHAR(100) NOT NULL DEFAULT 'Padrão'");
    }
    if (!isset($has["torr_tx_dataCadastro"])) {
        query("ALTER TABLE torre_preferencia ADD COLUMN torr_tx_dataCadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }

    $idx = mysqli_fetch_all(query(
        "SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'torre_preferencia'",
        "s", [$db]
    ), MYSQLI_ASSOC) ?: [];
    $idxNames = array_map(fn($r) => strval($r["INDEX_NAME"] ?? ""), $idx);
    $idxHas = array_flip($idxNames);

    if (isset($idxHas["uniq_usuario"]) && !isset($idxHas["uniq_usuario_nome"])) {
        @query("ALTER TABLE torre_preferencia DROP INDEX uniq_usuario");
    }
    if (!isset($idxHas["uniq_usuario_nome"])) {
        @query("ALTER TABLE torre_preferencia ADD UNIQUE KEY uniq_usuario_nome (torr_nb_usuario, torr_tx_nome)");
    }
}

// Lista as visualizações salvas por este usuário (nome + lista de itens ocultos de cada uma).
function torre_listar_perfis(int $usuarioId): array {
    if ($usuarioId <= 0) return [];
    torre_ensure_preferencia_schema();
    return mysqli_fetch_all(query(
        "SELECT torr_tx_nome AS nome, torr_tx_ocultos AS ocultos FROM torre_preferencia WHERE torr_nb_usuario = ? ORDER BY torr_tx_nome ASC",
        "i", [$usuarioId]
    ), MYSQLI_ASSOC) ?: [];
}

// ── Caches de saldo (paineis/saldo.php) ─────────────────────────────

function torre_mes_mais_recente_saldo(array $idsAlvo): ?array {
    $base = __DIR__ . "/paineis/arquivos/saldos";
    $meses = torre_meses_disponiveis($base);
    rsort($meses);
    foreach ($meses as $mes) {
        $achados = [];
        foreach ($idsAlvo as $id) {
            if (file_exists("{$base}/{$mes}/{$id}/empresa_{$id}.json")) $achados[] = $id;
        }
        if (!empty($achados)) return ["mes" => $mes, "empresas" => $achados];
    }
    return null;
}

function torre_saldo_totais(array $empresasAlvo, string $mes): array {
    $campos = ["jornadaPrevista","jornadaEfetiva","HESemanal","HESabado","adicionalNoturno","esperaIndenizada","saldoAnterior","saldoPeriodo","saldoFinal"];
    $out = array_fill_keys($campos, 0.0);
    $periodoInicio = null;
    $periodoFim = null;
    $qtdMotoristas = 0;
    foreach ($empresasAlvo as $id) {
        $arq = __DIR__ . "/paineis/arquivos/saldos/{$mes}/{$id}/empresa_{$id}.json";
        if (!file_exists($arq)) continue;
        $d = json_decode(file_get_contents($arq), true);
        $totais = $d["totais"] ?? [];
        foreach ($campos as $c) {
            if (isset($totais[$c])) $out[$c] += torre_hhmm_para_horas(strval($totais[$c]));
        }
        if (!empty($d["dataInicio"]) && ($periodoInicio === null || $d["dataInicio"] < $periodoInicio)) $periodoInicio = $d["dataInicio"];
        if (!empty($d["dataFim"]) && ($periodoFim === null || $d["dataFim"] > $periodoFim)) $periodoFim = $d["dataFim"];
        $qtdMotoristas += intval($d["qtdMotoristas"] ?? 0);
    }
    $out["_periodoInicio"] = $periodoInicio;
    $out["_periodoFim"] = $periodoFim;
    $out["_qtdMotoristas"] = $qtdMotoristas;
    return $out;
}

function torre_custo_he(array $empresasAlvo, string $mes): array {
    $custo = 0.0; $comSalario = 0; $semSalario = 0; $horasForaDaConta = 0.0;
    foreach ($empresasAlvo as $empId) {
        $dir = __DIR__ . "/paineis/arquivos/saldos/{$mes}/{$empId}";
        if (!is_dir($dir)) continue;
        foreach (scandir($dir) as $arq) {
            if (!preg_match('/^(\d+)\.json$/', $arq)) continue; // ignora empresa_*.json
            $dados = json_decode(file_get_contents("{$dir}/{$arq}"), true);
            if (!is_array($dados)) continue;
            $idMotorista = intval($dados["idMotorista"] ?? 0);
            $h50 = torre_hhmm_para_horas(strval($dados["HESemanal"] ?? "00:00"));
            $h100 = torre_hhmm_para_horas(strval($dados["HESabado"] ?? "00:00"));
            if ($idMotorista <= 0 || ($h50 <= 0 && $h100 <= 0)) continue;
            $salRow = mysqli_fetch_assoc(query("SELECT enti_nb_salario FROM entidade WHERE enti_nb_id = ?", "i", [$idMotorista]));
            $salario = floatval($salRow["enti_nb_salario"] ?? 0);
            if ($salario > 0) {
                $valorHora = $salario / 220;
                $custo += ($h50 * $valorHora * 1.5) + ($h100 * $valorHora * 2);
                $comSalario++;
            } else {
                $semSalario++;
                $horasForaDaConta += ($h50 + $h100);
            }
        }
    }
    return ["custo" => $custo, "comSalario" => $comSalario, "semSalario" => $semSalario, "horasForaDaConta" => $horasForaDaConta];
}

function torre_tendencia_he(array $empresasAlvo): array {
    $base = __DIR__ . "/paineis/arquivos/saldos";
    $meses = torre_meses_disponiveis($base);
    sort($meses);
    $meses = array_slice($meses, -6);
    $serie = [];
    foreach ($meses as $mes) {
        $total = 0.0; $tem = false;
        foreach ($empresasAlvo as $id) {
            $arq = "{$base}/{$mes}/{$id}/empresa_{$id}.json";
            if (!file_exists($arq)) continue;
            $tem = true;
            $d = json_decode(file_get_contents($arq), true);
            $totais = $d["totais"] ?? [];
            $total += torre_hhmm_para_horas(strval($totais["HESemanal"] ?? "00:00"));
            $total += torre_hhmm_para_horas(strval($totais["HESabado"] ?? "00:00"));
        }
        if ($tem) $serie[$mes] = $total;
    }
    return $serie;
}

// ── Caches de não conformidade jurídica (paineis/nc_juridica.php) ──

function torre_mes_mais_recente_nc(array $idsAlvo): ?array {
    $base = __DIR__ . "/paineis/arquivos/nao_conformidade_juridica";
    $meses = torre_meses_disponiveis($base);
    rsort($meses);
    foreach ($meses as $mes) {
        $achados = [];
        foreach ($idsAlvo as $id) {
            if (file_exists("{$base}/{$mes}/{$id}/nao_endossado/empresa_{$id}.json")
                || file_exists("{$base}/{$mes}/{$id}/endossado/empresa_{$id}.json")) {
                $achados[] = $id;
            }
        }
        if (!empty($achados)) return ["mes" => $mes, "empresas" => $achados];
    }
    return null;
}

function torre_nc_totais(array $empresasAlvo, string $mes): array {
    $campos = ["falta","jornadaEfetiva","refeicao","espera","descanso","repouso","jornada","mdc","intersticioInferior","intersticioSuperior","diasConformidade"];
    $out = array_fill_keys($campos, 0);
    foreach ($empresasAlvo as $id) {
        foreach (["nao_endossado", "endossado"] as $pasta) {
            $arq = __DIR__ . "/paineis/arquivos/nao_conformidade_juridica/{$mes}/{$id}/{$pasta}/empresa_{$id}.json";
            if (!file_exists($arq)) continue;
            $d = json_decode(file_get_contents($arq), true);
            if (!is_array($d)) continue;
            foreach ($campos as $c) {
                if (isset($d[$c])) $out[$c] += floatval($d[$c]);
            }
        }
    }
    return $out;
}

function torre_gravidade(array $totais): array {
    $alta = floatval($totais["refeicao"] ?? 0) + floatval($totais["intersticioInferior"] ?? 0) + floatval($totais["intersticioSuperior"] ?? 0);
    $media = floatval($totais["jornadaEfetiva"] ?? 0) + floatval($totais["mdc"] ?? 0);
    $baixa = floatval($totais["falta"] ?? 0) + floatval($totais["espera"] ?? 0) + floatval($totais["descanso"] ?? 0)
        + floatval($totais["repouso"] ?? 0) + floatval($totais["jornada"] ?? 0);
    return ["alta" => $alta, "media" => $media, "baixa" => $baixa];
}

// Tendência de não conformidade (total de ocorrências por mês) — mesma fórmula de gravidade do painel jurídico.
function torre_tendencia_nc(array $empresasAlvo): array {
    $base = __DIR__ . "/paineis/arquivos/nao_conformidade_juridica";
    $meses = torre_meses_disponiveis($base);
    sort($meses);
    $meses = array_slice($meses, -6);
    $serie = [];
    foreach ($meses as $mes) {
        $temDados = false;
        foreach ($empresasAlvo as $id) {
            if (is_dir("{$base}/{$mes}/{$id}")) { $temDados = true; break; }
        }
        if (!$temDados) continue;
        $totais = torre_nc_totais($empresasAlvo, $mes);
        $grav = torre_gravidade($totais);
        $serie[$mes] = $grav["alta"] + $grav["media"] + $grav["baixa"];
    }
    return $serie;
}

// Ranking de risco por pessoa — mesma ponderação de paineis/nc_juridica.php (Alta×0.1 + Média×0.05 + Baixa×0.01).
function torre_ranking_nc(array $empresasAlvo, string $mes, int $limite = 6): array {
    $ranking = [];
    foreach ($empresasAlvo as $id) {
        foreach (["nao_endossado", "endossado"] as $pasta) {
            $dir = __DIR__ . "/paineis/arquivos/nao_conformidade_juridica/{$mes}/{$id}/{$pasta}";
            if (!is_dir($dir)) continue;
            foreach (scandir($dir) as $arq) {
                if (!preg_match('/^(\d+)\.json$/', $arq, $m)) continue; // ignora empresa_*.json
                $entidadeId = intval($m[1]);
                $d = json_decode(file_get_contents("{$dir}/{$arq}"), true);
                if (!is_array($d)) continue;
                $grav = torre_gravidade($d);
                if ($grav["alta"] <= 0 && $grav["media"] <= 0 && $grav["baixa"] <= 0) continue;
                if (!isset($ranking[$entidadeId])) $ranking[$entidadeId] = ["id" => $entidadeId, "alta" => 0.0, "media" => 0.0, "baixa" => 0.0];
                $ranking[$entidadeId]["alta"] += $grav["alta"];
                $ranking[$entidadeId]["media"] += $grav["media"];
                $ranking[$entidadeId]["baixa"] += $grav["baixa"];
            }
        }
    }
    foreach ($ranking as &$r) {
        $r["penalidade"] = $r["alta"] * 0.1 + $r["media"] * 0.05 + $r["baixa"] * 0.01;
    }
    unset($r);
    usort($ranking, fn($a, $b) => $b["penalidade"] <=> $a["penalidade"]);
    $top = array_slice(array_values($ranking), 0, $limite);
    if (!empty($top)) {
        $ids = array_column($top, "id");
        $placeholders = implode(",", array_fill(0, count($ids), "?"));
        $tipos = str_repeat("i", count($ids));
        $nomes = mysqli_fetch_all(query("SELECT enti_nb_id, enti_tx_nome FROM entidade WHERE enti_nb_id IN ({$placeholders})", $tipos, $ids), MYSQLI_ASSOC) ?: [];
        $mapaNomes = [];
        foreach ($nomes as $n) { $mapaNomes[intval($n["enti_nb_id"])] = strval($n["enti_tx_nome"]); }
        foreach ($top as &$t) { $t["nome"] = $mapaNomes[$t["id"]] ?? ("Matrícula interna ".$t["id"]); }
        unset($t);
    }
    return $top;
}

// Tendência de abonos (contagem por mês) — consulta direta, não depende de cache.
function torre_tendencia_abonos(int $empresaFiltro): array {
    $condEmpresa = $empresaFiltro > 0 ? " AND e.enti_nb_empresa = {$empresaFiltro} " : "";
    $rows = mysqli_fetch_all(query(
        "SELECT DATE_FORMAT(a.abon_tx_data, '%Y-%m') AS mes, COUNT(*) AS total
         FROM abono a
         JOIN entidade e ON e.enti_tx_matricula = a.abon_tx_matricula
         WHERE a.abon_tx_status = 'ativo' AND e.enti_tx_status = 'ativo' {$condEmpresa}
           AND a.abon_tx_data >= (CURDATE() - INTERVAL 6 MONTH)
         GROUP BY mes ORDER BY mes ASC"
    ), MYSQLI_ASSOC) ?: [];
    $serie = [];
    foreach ($rows as $r) { $serie[strval($r["mes"])] = intval($r["total"]); }
    return $serie;
}

// ── Nota de qualidade de gestão (0-100 → 5 estrelas) ───────────────────
// Pesa uso real do sistema: cadastro completo, gente batendo ponto, ajustes e abonos
// registrados perto da data do evento, endosso de banco de horas em dia (motoristas) e
// jornadas fechadas sem virar "crítica" (>24h sem bater ponto). Sem tabela de "prazo"
// oficial no sistema para ajuste/abono/endosso — os prazos usados (5 dias para ajuste e
// abono, 45 dias de tolerância para o endosso do mês anterior) são um critério definido
// aqui, documentado nas legendas, não um valor vindo de configuração do sistema.
function torre_calcular_nota_gestao(int $empresaFiltro, string $condEmpresa, int $ativos, int $jornadasCriticas, int $emAtividade, int $emPausa, float $gravAlta, float $gravMedia, float $gravBaixa): array {
    $itens = [];

    // 1) Cadastro completo (foto, salário, endereço, telefone, e-mail)
    $cad = mysqli_fetch_assoc(query(
        "SELECT COUNT(*) AS total,
            SUM(enti_tx_foto IS NOT NULL AND enti_tx_foto <> '') AS tem_foto,
            SUM(enti_nb_salario IS NOT NULL AND enti_nb_salario > 0) AS tem_salario,
            SUM(enti_tx_endereco IS NOT NULL AND enti_tx_endereco <> '') AS tem_endereco,
            SUM(enti_tx_fone1 IS NOT NULL AND enti_tx_fone1 <> '') AS tem_fone,
            SUM(enti_tx_email IS NOT NULL AND enti_tx_email <> '') AS tem_email
         FROM entidade e WHERE e.enti_tx_status = 'ativo' {$condEmpresa}"
    )) ?: [];
    $totalCad = (int) ($cad["total"] ?? 0);
    $pctCad = $totalCad > 0 ? (
        (intval($cad["tem_foto"]) + intval($cad["tem_salario"]) + intval($cad["tem_endereco"]) + intval($cad["tem_fone"]) + intval($cad["tem_email"]))
        / ($totalCad * 5) * 100
    ) : 100.0;
    $itens[] = [
        "label" => "Cadastro completo da equipe",
        "peso" => 15, "pct" => $pctCad,
        "explicacao" => "Foto, salário, endereço, telefone e e-mail preenchidos para quem está ativo. {$totalCad} pessoa(s) ativa(s) consideradas.",
    ];

    // 2) Participação no ponto (30 dias)
    $part = mysqli_fetch_assoc(query(
        "SELECT COUNT(DISTINCT e.enti_nb_id) AS total_ativos, COUNT(DISTINCT p.pont_tx_matricula) AS com_ponto
         FROM entidade e
         LEFT JOIN ponto p ON p.pont_tx_matricula = e.enti_tx_matricula AND p.pont_tx_status = 'ativo' AND p.pont_tx_data >= (CURDATE() - INTERVAL 30 DAY)
         WHERE e.enti_tx_status = 'ativo' {$condEmpresa}"
    )) ?: [];
    $totalPart = (int) ($part["total_ativos"] ?? 0);
    $pctPart = $totalPart > 0 ? (intval($part["com_ponto"]) / $totalPart * 100) : 100.0;
    $itens[] = [
        "label" => "Equipe batendo ponto",
        "peso" => 20, "pct" => $pctPart,
        "explicacao" => "% de quem está ativo com ao menos 1 batida de ponto nos últimos 30 dias.",
    ];

    // 3) Ajustes de ponto pedidos perto da data do evento (até 5 dias, últimos 90 dias)
    $aju = mysqli_fetch_assoc(query(
        "SELECT COUNT(*) AS total, SUM(DATEDIFF(sa.data_solicitacao, sa.data_ajuste) <= 5) AS em_dia
         FROM solicitacoes_ajuste sa JOIN entidade e ON e.enti_nb_id = sa.id_motorista
         WHERE e.enti_tx_status = 'ativo' {$condEmpresa} AND sa.data_solicitacao >= (CURDATE() - INTERVAL 90 DAY)"
    )) ?: [];
    $totalAju = (int) ($aju["total"] ?? 0);
    $pctAju = $totalAju > 0 ? (intval($aju["em_dia"]) / $totalAju * 100) : 100.0;
    $itens[] = [
        "label" => "Ajustes de ponto pedidos em até 5 dias",
        "peso" => 10, "pct" => $pctAju,
        "explicacao" => "% dos ajustes de ponto solicitados nos últimos 90 dias, pedidos em até 5 dias após a data do evento ajustado.",
    ];

    // 4) Abonos lançados perto da data do evento (até 5 dias, últimos 90 dias)
    $abo = mysqli_fetch_assoc(query(
        "SELECT COUNT(*) AS total, SUM(DATEDIFF(a.abon_tx_dataCadastro, a.abon_tx_data) <= 5) AS em_dia
         FROM abono a JOIN entidade e ON e.enti_tx_matricula = a.abon_tx_matricula
         WHERE a.abon_tx_status = 'ativo' AND e.enti_tx_status = 'ativo' {$condEmpresa}
           AND a.abon_tx_data >= (CURDATE() - INTERVAL 90 DAY)"
    )) ?: [];
    $totalAbo = (int) ($abo["total"] ?? 0);
    $pctAbo = $totalAbo > 0 ? (intval($abo["em_dia"]) / $totalAbo * 100) : 100.0;
    $itens[] = [
        "label" => "Abonos lançados em até 5 dias",
        "peso" => 10, "pct" => $pctAbo,
        "explicacao" => "% dos abonos com data nos últimos 90 dias, cadastrados no sistema em até 5 dias após a data do evento.",
    ];

    // 5) Endosso de banco de horas em dia (motoristas, tolerância de 45 dias — fechamento do mês anterior)
    $endoRows = mysqli_fetch_all(query(
        "SELECT DATEDIFF(CURDATE(), MAX(en.endo_tx_ate)) AS dias_sem_cobertura
         FROM entidade e
         LEFT JOIN endosso en ON en.endo_nb_entidade = e.enti_nb_id AND en.endo_tx_status = 'ativo'
         WHERE e.enti_tx_status = 'ativo' AND e.enti_tx_ocupacao = 'Motorista' {$condEmpresa}
         GROUP BY e.enti_nb_id"
    ), MYSQLI_ASSOC) ?: [];
    $totalMotEndo = count($endoRows);
    $emDiaEndo = 0;
    foreach ($endoRows as $r) {
        $dias = $r["dias_sem_cobertura"] ?? null;
        if ($dias !== null && intval($dias) <= 45) $emDiaEndo++;
    }
    $pctEndo = $totalMotEndo > 0 ? ($emDiaEndo / $totalMotEndo * 100) : 100.0;
    $itens[] = [
        "label" => "Endosso de banco de horas em dia",
        "peso" => 15, "pct" => $pctEndo,
        "explicacao" => "% dos motoristas ativos com endosso cobrindo até 45 dias atrás (dá margem para fechar o mês anterior). {$totalMotEndo} motorista(s) considerado(s).",
    ];

    // 6) Jornadas fechadas sem virar "crítica" (>24h sem bater ponto)
    $totalAberta = $emAtividade + $emPausa;
    $pctJornada = $totalAberta > 0 ? ((1 - ($jornadasCriticas / $totalAberta)) * 100) : 100.0;
    $itens[] = [
        "label" => "Jornadas fechadas sem ficar críticas",
        "peso" => 20, "pct" => max(0, $pctJornada),
        "explicacao" => "% de quem está com jornada aberta agora que não passou de 24h sem bater ponto (jornada crítica).",
    ];

    // 7) Não conformidade jurídica sob controle (por cabeça, gravidade ponderada)
    $penalidadeNC = ($gravAlta * 3) + ($gravMedia * 1) + ($gravBaixa * 0.3);
    $baseNC = max(1, $ativos * 2);
    $pctNC = max(0, 100 - min(100, ($penalidadeNC / $baseNC) * 100));
    $itens[] = [
        "label" => "Não conformidade jurídica sob controle",
        "peso" => 10, "pct" => $pctNC,
        "explicacao" => "Quanto menos não conformidade de alta/média/baixa gravidade por pessoa ativa, maior a nota.",
    ];

    $notaFinal = 0.0;
    foreach ($itens as &$it) {
        $it["pontos"] = $it["pct"] / 100 * $it["peso"];
        $notaFinal += $it["pontos"];
    }
    unset($it);

    $stars = round(($notaFinal / 100 * 5) * 2) / 2; // arredonda para meia estrela
    if ($notaFinal >= 95) $titulo = "Excelência operacional";
    elseif ($notaFinal >= 80) $titulo = "Gestão muito bem estruturada";
    elseif ($notaFinal >= 60) $titulo = "Boa gestão, com pontos de atenção";
    elseif ($notaFinal >= 40) $titulo = "Gestão precisa de atenção";
    else $titulo = "Gestão exige ação imediata";

    return ["nota" => $notaFinal, "stars" => $stars, "titulo" => $titulo, "itens" => $itens];
}

// ── Render principal ─────────────────────────────────────────────────
// Não chama cabecalho()/rodape() nem verificaPermissao() — quem inclui
// este arquivo decide isso (dashboard.php é uma tela própria com
// permissão de menu; index.php::showWelcome() já está dentro do shell
// da página de boas-vindas pós-login).

function renderTorreDeComando(): void {

$usuarioId = intval($_SESSION["user_nb_id"] ?? 0);
$perfis = torre_listar_perfis($usuarioId);
$perfilAtual = trim(strval($_GET["perfil_visualizacao"] ?? ""));
$perfilExiste = $perfilAtual !== "" && in_array($perfilAtual, array_column($perfis, "nome"), true);
$config = torre_decodificar_config(null);
foreach ($perfis as $p) {
    if ($p["nome"] === $perfilAtual) {
        $config = torre_decodificar_config(strval($p["ocultos"] ?? ""));
        break;
    }
}
if (!$perfilExiste && $perfilAtual !== "") {
    $perfilAtual = ""; // perfil inexistente (ex.: excluído) — cai no padrão "Todos"
}
$ocultos = $config["ocultos"];
$ordemSecoes = $config["ordemSecoes"];
$ordemItens = $config["ordemItens"];
$manifesto = torre_manifesto();

// Preferência de notificações do sino — o botão "Notificações" aqui no dashboard
// abre o mesmo cadastro usado no sino do cabeçalho (mesma tabela, mesmo endpoint).
include_once __DIR__ . "/notificacoes.php";
$notifCategoriasDisp = notificacao_categorias_disponiveis();
$notifPref = notificacao_carregar_preferencia($usuarioId);

// ── Filtro de empresa (filial) ───────────────────────────────────────

$empresas = mysqli_fetch_all(query(
    "SELECT empr_nb_id, empr_tx_nome, empr_tx_Ehmatriz FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY (empr_tx_Ehmatriz = 'sim') DESC, empr_tx_nome ASC"
), MYSQLI_ASSOC) ?: [];
$idsEmpresas = array_map("intval", array_column($empresas, "empr_nb_id"));

$empresaFiltro = intval($_GET["empresa_id"] ?? 0);
if ($empresaFiltro > 0 && !in_array($empresaFiltro, $idsEmpresas, true)) {
    $empresaFiltro = 0;
}
$empresasAlvo = $empresaFiltro > 0 ? [$empresaFiltro] : $idsEmpresas;
$condEmpresa = $empresaFiltro > 0 ? " AND e.enti_nb_empresa = {$empresaFiltro} " : "";

// ── Ativos / inativos / filiais ──────────────────────────────────────

$ativos = (int) (mysqli_fetch_assoc(query("SELECT COUNT(*) AS c FROM entidade e WHERE e.enti_tx_status = 'ativo' {$condEmpresa}"))["c"] ?? 0);
$inativos = (int) (mysqli_fetch_assoc(query("SELECT COUNT(*) AS c FROM entidade e WHERE e.enti_tx_status = 'inativo' {$condEmpresa}"))["c"] ?? 0);
$totalFiliais = (int) (mysqli_fetch_assoc(query("SELECT COUNT(*) AS c FROM empresa WHERE empr_tx_Ehmatriz != 'sim' AND empr_tx_status='ativo'"))["c"] ?? 0);

// ── Totais por ocupação (base para o comparativo de status por ocupação) ─

$ocupacaoRows = mysqli_fetch_all(query(
    "SELECT e.enti_tx_ocupacao AS ocupacao, COUNT(*) AS total FROM entidade e
     WHERE e.enti_tx_status = 'ativo' {$condEmpresa}
     GROUP BY e.enti_tx_ocupacao ORDER BY total DESC"
), MYSQLI_ASSOC) ?: [];

// ── Status ao vivo (última batida ativa por matrícula, últimos 7 dias) ─

$statusRows = mysqli_fetch_all(query(
    "SELECT p.pont_tx_tipo AS tipo, p.pont_tx_data AS ultima_data, e.enti_tx_ocupacao AS ocupacao
     FROM ponto p
     INNER JOIN (
        SELECT pont_tx_matricula, MAX(pont_tx_data) AS max_data
        FROM ponto
        WHERE pont_tx_status = 'ativo' AND pont_tx_data >= (NOW() - INTERVAL 7 DAY)
        GROUP BY pont_tx_matricula
     ) ult ON ult.pont_tx_matricula = p.pont_tx_matricula AND ult.max_data = p.pont_tx_data
     JOIN entidade e ON e.enti_tx_matricula = p.pont_tx_matricula
     WHERE p.pont_tx_status = 'ativo' AND e.enti_tx_status = 'ativo' {$condEmpresa}"
), MYSQLI_ASSOC) ?: [];

$estado = ["trabalhando"=>0, "refeicao"=>0, "espera"=>0, "descanso"=>0, "repouso"=>0, "repousoEmbarcado"=>0];
$jornadasCriticas = 0;
$agora = time();
$statusPorOcupacao = [];
foreach ($statusRows as $r) {
    $tipo = intval($r["tipo"]);
    $ocup = strval($r["ocupacao"] ?? "Outros");
    if (!isset($statusPorOcupacao[$ocup])) $statusPorOcupacao[$ocup] = ["trabalhando"=>0, "pausa"=>0];
    if (in_array($tipo, [1,4,6,8,10,12], true)) { $estado["trabalhando"]++; $statusPorOcupacao[$ocup]["trabalhando"]++; }
    elseif ($tipo === 3) { $estado["refeicao"]++; $statusPorOcupacao[$ocup]["pausa"]++; }
    elseif ($tipo === 5) { $estado["espera"]++; $statusPorOcupacao[$ocup]["pausa"]++; }
    elseif ($tipo === 7) { $estado["descanso"]++; $statusPorOcupacao[$ocup]["pausa"]++; }
    elseif ($tipo === 9) { $estado["repouso"]++; $statusPorOcupacao[$ocup]["pausa"]++; }
    elseif ($tipo === 11) { $estado["repousoEmbarcado"]++; $statusPorOcupacao[$ocup]["pausa"]++; }
    else continue; // tipo 2 = fim de jornada, não conta como "em atividade"

    $ultima = strtotime(strval($r["ultima_data"]));
    if ($ultima && (($agora - $ultima) / 3600) > 24) $jornadasCriticas++;
}
$emPausa = $estado["refeicao"] + $estado["espera"] + $estado["descanso"] + $estado["repouso"] + $estado["repousoEmbarcado"];
$emAtividade = $estado["trabalhando"];
$foraDeJornada = max(0, $ativos - $emAtividade - $emPausa);

// monta o comparativo por ocupação (trabalhando / pausa / fora), usando o total ativo de cada ocupação como base
$ocupacaoStatusComparativo = [];
foreach ($ocupacaoRows as $row) {
    $ocup = strval($row["ocupacao"] ?? "Outros");
    $totalOcup = (int) $row["total"];
    $trab = $statusPorOcupacao[$ocup]["trabalhando"] ?? 0;
    $pausa = $statusPorOcupacao[$ocup]["pausa"] ?? 0;
    $fora = max(0, $totalOcup - $trab - $pausa);
    $ocupacaoStatusComparativo[] = ["ocupacao"=>$ocup, "trabalhando"=>$trab, "pausa"=>$pausa, "fora"=>$fora];
}

// ── Disponibilidade de frota (motoristas, simplificado — sem regra ADI 5322) ─

$motoristasRows = mysqli_fetch_all(query(
    "SELECT e.enti_tx_matricula AS matricula,
        (SELECT MAX(p2.pont_tx_data) FROM ponto p2 WHERE p2.pont_tx_matricula = e.enti_tx_matricula AND p2.pont_tx_tipo = 2 AND p2.pont_tx_status='ativo') AS ultimo_fim,
        (SELECT MAX(p3.pont_tx_data) FROM ponto p3 WHERE p3.pont_tx_matricula = e.enti_tx_matricula AND p3.pont_tx_status='ativo') AS ultima_batida
     FROM entidade e
     WHERE e.enti_tx_status = 'ativo' AND e.enti_tx_ocupacao = 'Motorista' {$condEmpresa}"
), MYSQLI_ASSOC) ?: [];

$disponibilidade = ["disponivel"=>0, "parcial"=>0, "indisponivel"=>0, "emJornada"=>0];
foreach ($motoristasRows as $m) {
    if (empty($m["ultimo_fim"]) || $m["ultimo_fim"] !== $m["ultima_batida"]) {
        $disponibilidade["emJornada"]++;
        continue;
    }
    $horas = (time() - strtotime($m["ultimo_fim"])) / 3600;
    if ($horas >= 11) $disponibilidade["disponivel"]++;
    elseif ($horas >= 8) $disponibilidade["parcial"]++;
    else $disponibilidade["indisponivel"]++;
}
$totalMotoristas = array_sum($disponibilidade);

// ── Mapa ao vivo (última posição conhecida, últimos 2 dias) ─────────

$mapaRows = mysqli_fetch_all(query(
    "SELECT e.enti_tx_nome AS nome, e.enti_tx_ocupacao AS ocupacao,
            p.pont_tx_latitude AS lat, p.pont_tx_longitude AS lng,
            p.pont_tx_data AS quando, p.pont_tx_tipo AS tipo
     FROM entidade e
     INNER JOIN (
        SELECT pont_tx_matricula, MAX(pont_tx_data) AS max_data
        FROM ponto
        WHERE pont_tx_status = 'ativo'
          AND pont_tx_latitude IS NOT NULL AND pont_tx_latitude <> ''
          AND pont_tx_data >= (NOW() - INTERVAL 2 DAY)
        GROUP BY pont_tx_matricula
     ) ult ON ult.pont_tx_matricula = e.enti_tx_matricula
     INNER JOIN ponto p ON p.pont_tx_matricula = ult.pont_tx_matricula AND p.pont_tx_data = ult.max_data
     WHERE e.enti_tx_status = 'ativo' {$condEmpresa}
     LIMIT 300"
), MYSQLI_ASSOC) ?: [];

$pontosMapa = [];
foreach ($mapaRows as $r) {
    $lat = floatval($r["lat"]);
    $lng = floatval($r["lng"]);
    if ($lat === 0.0 && $lng === 0.0) continue;
    $tipo = intval($r["tipo"]);
    if (in_array($tipo, [1,4,6,8,10,12], true)) { $status = "trabalhando"; }
    elseif (in_array($tipo, [3,5,7,9,11], true)) { $status = "pausa"; }
    else { $status = "fora"; }
    $pontosMapa[] = [
        "nome" => $r["nome"],
        "ocupacao" => $r["ocupacao"],
        "lat" => $lat,
        "lng" => $lng,
        "quando" => $r["quando"],
        "status" => $status
    ];
}

// ── Férias hoje / próximos 7 dias ────────────────────────────────────

$feriasHoje = mysqli_fetch_all(query(
    "SELECT e.enti_tx_nome AS nome, f.feri_tx_dataInicio AS inicio, f.feri_tx_dataFim AS fim
     FROM ferias f JOIN entidade e ON e.enti_nb_id = f.feri_nb_entidade
     WHERE f.feri_tx_status = 'ativo' AND e.enti_tx_status = 'ativo' {$condEmpresa}
       AND CURDATE() BETWEEN f.feri_tx_dataInicio AND f.feri_tx_dataFim
     ORDER BY f.feri_tx_dataFim ASC"
), MYSQLI_ASSOC) ?: [];

$feriasProximas = mysqli_fetch_all(query(
    "SELECT e.enti_tx_nome AS nome, f.feri_tx_dataInicio AS inicio
     FROM ferias f JOIN entidade e ON e.enti_nb_id = f.feri_nb_entidade
     WHERE f.feri_tx_status = 'ativo' AND e.enti_tx_status = 'ativo' {$condEmpresa}
       AND f.feri_tx_dataInicio BETWEEN (CURDATE() + INTERVAL 1 DAY) AND (CURDATE() + INTERVAL 7 DAY)
     ORDER BY f.feri_tx_dataInicio ASC"
), MYSQLI_ASSOC) ?: [];

// ── Afastamentos ativos hoje ──────────────────────────────────────────

$afastamentosHoje = (int) (mysqli_fetch_assoc(query(
    "SELECT COUNT(*) AS c FROM abono a
     JOIN motivo m ON m.moti_nb_id = a.abon_nb_motivo
     JOIN entidade e ON e.enti_tx_matricula = a.abon_tx_matricula
     WHERE a.abon_tx_status = 'ativo' AND m.moti_tx_tipo = 'Afastamento'
       AND a.abon_tx_data = CURDATE() AND e.enti_tx_status = 'ativo' {$condEmpresa}"
))["c"] ?? 0);

// ── Abonos do mês, por motivo ─────────────────────────────────────────

$abonosPorMotivo = mysqli_fetch_all(query(
    "SELECT m.moti_tx_nome AS motivo, COUNT(*) AS total
     FROM abono a
     JOIN motivo m ON m.moti_nb_id = a.abon_nb_motivo
     JOIN entidade e ON e.enti_tx_matricula = a.abon_tx_matricula
     WHERE a.abon_tx_status = 'ativo' AND e.enti_tx_status = 'ativo' {$condEmpresa}
       AND a.abon_tx_data BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND CURDATE()
     GROUP BY m.moti_tx_nome ORDER BY total DESC LIMIT 8"
), MYSQLI_ASSOC) ?: [];
$totalAbonosMes = array_sum(array_column($abonosPorMotivo, "total"));

// ── Advertências candidatas (abono cujo motivo é passível de advertência) ─

$advertencias = (int) (mysqli_fetch_assoc(query(
    "SELECT COUNT(*) AS c FROM abono a
     JOIN motivo m ON m.moti_nb_id = a.abon_nb_motivo
     JOIN entidade e ON e.enti_tx_matricula = a.abon_tx_matricula
     WHERE a.abon_tx_status = 'ativo' AND m.moti_tx_advertencia = 'sim' AND e.enti_tx_status = 'ativo' {$condEmpresa}
       AND a.abon_tx_data BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND CURDATE()"
))["c"] ?? 0);

// ── CNH vencendo em 30 dias ────────────────────────────────────────────

$cnhVencendo = mysqli_fetch_all(query(
    "SELECT e.enti_tx_nome AS nome, e.enti_tx_cnhValidade AS validade
     FROM entidade e
     WHERE e.enti_tx_status = 'ativo' AND e.enti_tx_ocupacao = 'Motorista' {$condEmpresa}
       AND e.enti_tx_cnhValidade IS NOT NULL AND e.enti_tx_cnhValidade NOT IN ('', '0000-00-00')
       AND e.enti_tx_cnhValidade BETWEEN CURDATE() AND (CURDATE() + INTERVAL 30 DAY)
     ORDER BY e.enti_tx_cnhValidade ASC"
), MYSQLI_ASSOC) ?: [];

// ── Não conformidade jurídica (cache mais recente disponível) ─────────

$ncRef = torre_mes_mais_recente_nc($empresasAlvo);
$ncTotais = $ncRef ? torre_nc_totais($ncRef["empresas"], $ncRef["mes"]) : null;
if ($ncTotais) {
    $grav = torre_gravidade($ncTotais);
    $gravAlta = $grav["alta"];
    $gravMedia = $grav["media"];
    $gravBaixa = $grav["baixa"];
} else {
    $gravAlta = $gravMedia = $gravBaixa = 0;
}
$tendenciaNC = torre_tendencia_nc($empresasAlvo);
$rankingNC = $ncRef ? torre_ranking_nc($ncRef["empresas"], $ncRef["mes"]) : [];
$tendenciaAbonos = torre_tendencia_abonos($empresaFiltro);

// ── Custo da jornada (cache mais recente de saldo.php) ────────────────

$saldoRef = torre_mes_mais_recente_saldo($empresasAlvo);
$saldoTotais = $saldoRef ? torre_saldo_totais($saldoRef["empresas"], $saldoRef["mes"]) : null;
$custoRef = $saldoRef ? torre_custo_he($saldoRef["empresas"], $saldoRef["mes"]) : null;
$tendenciaHE = torre_tendencia_he($empresasAlvo);
$temMovimentoNoPeriodo = $saldoTotais && (
    $saldoTotais["HESemanal"] > 0 || $saldoTotais["HESabado"] > 0 ||
    $saldoTotais["adicionalNoturno"] > 0 || $saldoTotais["esperaIndenizada"] > 0
);

// ── Cadastros (contagens gerais do que já está configurado no sistema) ─
// Algumas tabelas são catálogos globais (setores, subsetores, feriados, perfis de acesso,
// tipos de documento, motivos, habilidades, EPI, escalas) — sem coluna de empresa no banco —
// então não é possível filtrar essas por filial; as demais respeitam o filtro de empresa selecionado.

$condEmpresaPlaca = $empresaFiltro > 0 ? " AND plac_nb_empresa = {$empresaFiltro} " : "";
$condEmpresaUser = $empresaFiltro > 0 ? " AND user_nb_empresa = {$empresaFiltro} " : "";
$condEmpresaEpiEntrega = $empresaFiltro > 0 ? " AND ss_e_nb_empresa_id = {$empresaFiltro} " : "";
$condEmpresaAssinatura = $empresaFiltro > 0 ? " AND empresa_id = {$empresaFiltro} " : "";

$qtdPlacas = (int) (mysqli_fetch_assoc(query("SELECT COUNT(*) AS c FROM placa WHERE 1=1 {$condEmpresaPlaca}"))["c"] ?? 0);
$qtdSetores = (int) (mysqli_fetch_assoc(query("SELECT COUNT(*) AS c FROM grupos_documentos WHERE grup_tx_status = 'ativo'"))["c"] ?? 0);
$qtdSubsetores = (int) (mysqli_fetch_assoc(query("SELECT COUNT(*) AS c FROM sbgrupos_documentos WHERE sbgr_tx_status = 'ativo'"))["c"] ?? 0);
$qtdFacial = (int) (mysqli_fetch_assoc(query("SELECT COUNT(*) AS c FROM user WHERE user_tx_status = 'ativo' AND user_tx_face_descriptor IS NOT NULL AND user_tx_face_descriptor != '' {$condEmpresaUser}"))["c"] ?? 0);
$qtdFeriadosMes = (int) (mysqli_fetch_assoc(query("SELECT COUNT(*) AS c FROM feriado WHERE feri_tx_status = 'ativo' AND MONTH(feri_tx_data) = MONTH(CURDATE()) AND YEAR(feri_tx_data) = YEAR(CURDATE())"))["c"] ?? 0);
$qtdPerfilAcesso = (int) (mysqli_fetch_assoc(query("SELECT COUNT(*) AS c FROM perfil_acesso WHERE perfil_tx_status = 'ativo'"))["c"] ?? 0);
$qtdTipoDocumento = (int) (mysqli_fetch_assoc(query("SELECT COUNT(*) AS c FROM tipos_documentos WHERE tipo_tx_status = 'ativo'"))["c"] ?? 0);
$qtdMotivos = (int) (mysqli_fetch_assoc(query("SELECT COUNT(*) AS c FROM motivo WHERE moti_tx_status = 'ativo'"))["c"] ?? 0);
$qtdEpiCatalogo = (int) (mysqli_fetch_assoc(query("SELECT COUNT(*) AS c FROM ss_epi WHERE ss_e_tx_status = 'ativo'"))["c"] ?? 0);
$qtdEpiEntregues = (int) (mysqli_fetch_assoc(query("SELECT COUNT(*) AS c FROM ss_epi_entrega WHERE ss_e_tx_status = 'ativo' {$condEmpresaEpiEntrega}"))["c"] ?? 0);
$qtdAjustes = (int) (mysqli_fetch_assoc(query("SELECT COUNT(*) AS c FROM solicitacoes_ajuste sa JOIN entidade e ON e.enti_nb_id = sa.id_motorista WHERE e.enti_tx_status = 'ativo' {$condEmpresa}"))["c"] ?? 0);
$qtdEscalas = (int) (mysqli_fetch_assoc(query("SELECT COUNT(*) AS c FROM escala"))["c"] ?? 0);
$qtdDiarias = (int) (mysqli_fetch_assoc(query("SELECT COUNT(*) AS c FROM diaria_deposito dd JOIN entidade e ON e.enti_nb_id = dd.depr_nb_entidade WHERE e.enti_tx_status = 'ativo' {$condEmpresa}"))["c"] ?? 0);
$qtdAssinEnviadas = (int) (mysqli_fetch_assoc(query("SELECT COUNT(*) AS c FROM solicitacoes_assinatura WHERE 1=1 {$condEmpresaAssinatura}"))["c"] ?? 0);
$qtdAssinPendentes = (int) (mysqli_fetch_assoc(query("SELECT COUNT(*) AS c FROM solicitacoes_assinatura WHERE status IN ('pendente','em_progresso') {$condEmpresaAssinatura}"))["c"] ?? 0);
$qtdAssinConcluidas = (int) (mysqli_fetch_assoc(query("SELECT COUNT(*) AS c FROM solicitacoes_assinatura WHERE status IN ('concluido','assinado') {$condEmpresaAssinatura}"))["c"] ?? 0);

// ── Nota de qualidade de gestão ────────────────────────────────────────
$notaGestao = torre_calcular_nota_gestao($empresaFiltro, $condEmpresa, $ativos, $jornadasCriticas, $emAtividade, $emPausa, floatval($gravAlta), floatval($gravMedia), floatval($gravBaixa));
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<style>
  /* Variáveis no :root (não em .tc-root) — o modal de Personalizar é irmão de .tc-root no HTML,
     então precisa herdar essas cores também, senão renderiza com fundo/texto padrão do navegador. */
  :root{
    --tc-bg:#f4f6f9; --tc-card:#ffffff; --tc-line:#e6e9ee; --tc-ink:#1c2733; --tc-ink-dim:#5b6672;
    --tc-ink-mute:#8993a1; --tc-accent:#2f6fa3; --tc-accent-soft:#eaf2f9; --tc-good:#1f9d64;
    --tc-good-soft:#e7f7ef; --tc-warn:#c98a1c; --tc-warn-soft:#fbf1de; --tc-bad:#c5432f; --tc-bad-soft:#fbeae6;
    --tc-neutral:#8993a1; --tc-shadow:0 1px 2px rgba(28,39,51,.04), 0 8px 20px -12px rgba(28,39,51,.12);
  }
  .tc-root{ font-family:"Segoe UI", Roboto, Arial, sans-serif; color:var(--tc-ink); max-width:100%; overflow-x:hidden; }
  .tc-root *{ box-sizing:border-box; }
  .tc-root{ background:var(--tc-bg); margin:-25px -25px 0; padding:22px 25px 50px; }

  .tc-top{ display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px; margin-bottom:22px; }
  .tc-title{ display:flex; align-items:center; gap:12px; }
  .tc-title h1{ font-size:22px; font-weight:700; margin:0; letter-spacing:-.01em; }
  .tc-title .tc-icon-badge{ width:38px; height:38px; border-radius:10px; background:var(--tc-accent); color:#fff;
             display:flex; align-items:center; justify-content:center; box-shadow:var(--tc-shadow); }
  .tc-sub{ font-size:12.5px; color:var(--tc-ink-mute); margin-top:2px; }
  .tc-filters{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
  .tc-filters form{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin:0; }
  .tc-filters select{ max-width:100%; height:36px; line-height:20px; vertical-align:middle; box-sizing:border-box; }
  .tc-filters select{ border:1px solid var(--tc-line); background:#fff; border-radius:8px; padding:7px 12px; font-size:13px; color:var(--tc-ink); }
  .tc-btn-personalizar{ height:36px; box-sizing:border-box; }
  .tc-clock{ font-size:12px; color:var(--tc-ink-mute); display:flex; align-items:center; gap:6px; height:36px; }
  @media (max-width:700px){
    .tc-top{ flex-direction:column; align-items:stretch; }
    .tc-filters{ flex-direction:column; align-items:stretch; width:100%; }
    .tc-filters form{ flex-direction:column; align-items:stretch; width:100%; }
    .tc-filters select, .tc-btn-personalizar{ width:100%; justify-content:center; }
    .tc-clock{ justify-content:center; }
  }

  .tc-nota-gestao{ background:linear-gradient(135deg,#0f172a,#1e2a45); color:#fff; border-radius:14px; padding:20px 24px;
             margin-bottom:22px; box-shadow:var(--tc-shadow); min-width:0; }
  .tc-nota-principal{ display:flex; align-items:center; gap:20px; flex-wrap:wrap; }
  .tc-estrelas-wrap{ position:relative; font-size:26px; line-height:1; letter-spacing:3px; white-space:nowrap; flex:none; }
  .tc-estrelas-fundo{ color:rgba(255,255,255,.2); }
  .tc-estrelas-preenchidas{ position:absolute; top:0; left:0; overflow:hidden; width:var(--pct); color:#fbbf24; }
  .tc-nota-numeros{ display:flex; flex-direction:column; gap:2px; min-width:0; }
  .tc-nota-numero{ font-size:20px; font-weight:800; }
  .tc-nota-titulo{ font-size:12.5px; color:rgba(255,255,255,.75); font-weight:600; }
  .tc-nota-toggle{ margin-left:auto; display:inline-flex; align-items:center; gap:6px; border:1px solid rgba(255,255,255,.25);
             background:rgba(255,255,255,.08); color:#fff; border-radius:8px; padding:8px 14px; font-size:12.5px; font-weight:600; cursor:pointer; }
  .tc-nota-toggle:hover{ background:rgba(255,255,255,.16); }
  .tc-nota-toggle.aberto i{ transform:rotate(180deg); }
  .tc-nota-toggle i{ transition:transform .15s ease; }
  .tc-nota-detalhe{ display:none; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px 22px; margin-top:18px;
             padding-top:18px; border-top:1px solid rgba(255,255,255,.14); }
  .tc-nota-detalhe.aberto{ display:grid; }
  .tc-nota-item{ min-width:0; }
  .tc-nota-item-topo{ display:flex; justify-content:space-between; gap:8px; font-size:12.5px; font-weight:600; margin-bottom:6px; }
  .tc-nota-barra{ height:6px; border-radius:4px; background:rgba(255,255,255,.14); overflow:hidden; }
  .tc-nota-barra-fill{ height:100%; border-radius:4px; }
  .tc-nota-item-desc{ font-size:11.5px; color:rgba(255,255,255,.62); margin-top:6px; line-height:1.4; }
  @media (max-width:700px){ .tc-nota-toggle{ margin-left:0; width:100%; justify-content:center; } }

  .tc-btn-personalizar{ display:inline-flex; align-items:center; gap:7px; border:1px solid var(--tc-line); background:#fff;
             color:var(--tc-ink); border-radius:8px; padding:8px 14px; font-size:13px; font-weight:600; cursor:pointer; }
  .tc-btn-personalizar:hover{ border-color:var(--tc-accent); color:var(--tc-accent); }

  .tc-secoes{ display:flex; flex-direction:column; min-width:0; }
  .tc-section{ margin-bottom:30px; }
  .tc-section-head{ display:flex; align-items:center; gap:10px; margin-bottom:14px; }
  .tc-section-head .tc-dot{ width:8px; height:8px; border-radius:50%; background:var(--tc-accent); }
  .tc-section-head h2{ font-size:15px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; margin:0; color:var(--tc-ink); }
  .tc-section-periodo{ font-size:12px; color:var(--tc-ink-mute); font-weight:500; text-transform:none; letter-spacing:normal; }
  .tc-section-desc{ font-size:12.5px; color:var(--tc-ink-mute); margin:2px 0 0 18px; max-width:78ch; }

  .tc-grid{ display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:14px; }
  .tc-grid-compacta{ grid-template-columns:repeat(auto-fit, minmax(158px, 1fr)); gap:12px; }
  @media (min-width: 1240px){ .tc-grid-compacta{ grid-template-columns:repeat(8, 1fr); } }
  .tc-card{ background:var(--tc-card); border:1px solid var(--tc-line); border-radius:12px; box-shadow:var(--tc-shadow); padding:16px 18px; min-width:0; }
  .tc-tile{ display:flex; flex-direction:column; gap:10px; min-height:108px; }
  .tc-tile-top{ display:flex; align-items:center; justify-content:space-between; }
  .tc-tile-label{ font-size:12px; color:var(--tc-ink-mute); font-weight:600; text-transform:uppercase; letter-spacing:.02em; }
  .tc-tile-icon{ width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex:none; }
  .tc-tile-icon.acc{ background:var(--tc-accent-soft); color:var(--tc-accent); }
  .tc-tile-icon.good{ background:var(--tc-good-soft); color:var(--tc-good); }
  .tc-tile-icon.warn{ background:var(--tc-warn-soft); color:var(--tc-warn); }
  .tc-tile-icon.bad{ background:var(--tc-bad-soft); color:var(--tc-bad); }
  .tc-tile-value{ font-size:28px; font-weight:700; line-height:1; font-variant-numeric:tabular-nums; }
  .tc-tile-value.good{ color:var(--tc-good); }
  .tc-tile-value.warn{ color:var(--tc-warn); }
  .tc-tile-value.bad{ color:var(--tc-bad); }
  .tc-tile-foot{ font-size:11.5px; color:var(--tc-ink-mute); margin-top:auto; }
  .tc-tile-link{ font-size:11px; font-weight:600; color:var(--tc-accent); text-decoration:none; display:inline-flex; align-items:center; gap:4px; margin-top:2px; }
  .tc-tile-link:hover{ text-decoration:underline; }
  .tc-panel-link{ font-size:11.5px; font-weight:600; color:var(--tc-accent); text-decoration:none; display:inline-flex; align-items:center; gap:4px; margin-top:12px; }
  .tc-panel-link:hover{ text-decoration:underline; }

  .tc-panel-row{ display:grid; grid-template-columns:1.1fr .9fr; gap:14px; }
  .tc-panel-row.tc-map-row{ grid-template-columns:.85fr 1.15fr; }
  @media (max-width:900px){ .tc-panel-row, .tc-panel-row.tc-map-row{ grid-template-columns:1fr; } }
  .tc-panel{ background:var(--tc-card); border:1px solid var(--tc-line); border-radius:12px; box-shadow:var(--tc-shadow); padding:18px 20px; min-width:0; }
  .tc-panel h3{ font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:var(--tc-ink-dim); margin:0 0 14px; display:flex; align-items:center; gap:8px; }
  .tc-panel h3 svg{ color:var(--tc-accent); }
  .tc-chart-wrap{ position:relative; height:220px; width:100%; max-width:100%; }
  .tc-chart-wrap canvas{ max-width:100% !important; }
  @media (max-width:600px){ .tc-chart-wrap{ height:190px; } }
  .tc-legend{ display:flex; flex-wrap:wrap; gap:12px; margin-top:12px; font-size:12px; color:var(--tc-ink-dim); }
  .tc-legend-item{ display:flex; align-items:center; gap:6px; }
  .tc-swatch{ width:10px; height:10px; border-radius:3px; flex:none; }

  /* Visão geral do status agora — donut grande + legenda com números, lado a lado. */
  .tc-panel-hero{ padding:24px 26px; }
  .tc-status-geral{ display:flex; align-items:center; gap:32px; flex-wrap:wrap; }
  .tc-chart-hero{ height:260px; width:260px; max-width:100%; flex:none; }
  .tc-status-legenda{ display:flex; flex-direction:column; gap:12px; flex:1 1 240px; min-width:0; }
  .tc-status-item{ display:flex; align-items:center; gap:10px; padding-bottom:10px; border-bottom:1px solid var(--tc-line); }
  .tc-status-item:last-child{ border-bottom:none; padding-bottom:0; }
  .tc-status-item .tc-swatch{ width:12px; height:12px; border-radius:4px; }
  .tc-status-label{ font-size:13.5px; color:var(--tc-ink-dim); flex:1 1 auto; min-width:0; }
  .tc-status-valor{ font-size:16px; font-weight:700; color:var(--tc-ink); font-variant-numeric:tabular-nums; flex:none; }
  @media (max-width:700px){
    .tc-status-geral{ flex-direction:column; align-items:stretch; gap:20px; }
    .tc-chart-hero{ width:100%; height:220px; margin:0 auto; }
  }

  .tc-map{ height:340px; width:100%; border-radius:10px; overflow:hidden; border:1px solid var(--tc-line); }
  @media (max-width:600px){ .tc-map{ height:260px; } }
  .tc-map-empty{ height:340px; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:8px;
                  color:var(--tc-ink-mute); font-size:13px; text-align:center; }

  .tc-list{ display:flex; flex-direction:column; gap:8px; max-height:230px; overflow-y:auto; }
  .tc-list-row{ display:flex; align-items:center; justify-content:space-between; gap:10px; padding:8px 10px; border-radius:8px; background:#fafbfc; border:1px solid var(--tc-line); min-width:0; }
  .tc-list-name{ font-size:13px; font-weight:600; color:var(--tc-ink); min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .tc-list-meta{ flex:none; }
  .tc-list-meta{ font-size:11.5px; color:var(--tc-ink-mute); }
  .tc-list-empty{ font-size:12.5px; color:var(--tc-ink-mute); padding:14px; text-align:center; }

  .tc-trio{ display:grid; grid-template-columns:repeat(3, 1fr); gap:14px; margin-bottom:14px; }
  @media (max-width:900px){ .tc-trio{ grid-template-columns:1fr; } }

  .tc-empty-state{ display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; padding:34px 10px;
                    color:var(--tc-ink-mute); text-align:center; }
  .tc-empty-state a{ font-size:12.5px; font-weight:600; color:var(--tc-accent); text-decoration:none; }
  .tc-empty-state a:hover{ text-decoration:underline; }

  .tc-ref{ font-size:11px; color:var(--tc-ink-mute); font-weight:500; }

  /* Legenda explicativa: sempre visível, embaixo do título — não é hover/tooltip. */
  .tc-info-tip{ display:block; width:100%; font-size:11px; font-weight:400; text-transform:none; letter-spacing:normal;
             line-height:1.4; color:var(--tc-ink-mute); margin-top:4px; }
  .tc-panel h3{ flex-wrap:wrap; row-gap:4px; }
  .tc-panel h3 .tc-info-tip{ flex-basis:100%; margin-top:2px; font-size:12px; }
  .tc-atualizar-link{ font-size:11.5px; font-weight:600; color:var(--tc-accent); text-decoration:none; margin-left:auto; }
  .tc-atualizar-link:hover{ text-decoration:underline; }

  .tc-modal-overlay{ position:fixed; inset:0; background:rgba(20,26,33,.55); z-index:2000; display:none; align-items:center; justify-content:center; padding:20px; }
  .tc-modal{ background:#fff; border-radius:14px; width:100%; max-width:640px; max-height:86vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.25); }
  .tc-modal-head{ display:flex; align-items:center; justify-content:space-between; padding:18px 22px; border-bottom:1px solid var(--tc-line); flex:none; }
  .tc-modal-head h3{ margin:0; font-size:16px; font-weight:700; color:var(--tc-ink); }
  .tc-modal-close{ border:none; background:none; cursor:pointer; color:var(--tc-ink-mute); padding:4px; display:flex; }
  .tc-modal-close:hover{ color:var(--tc-ink); }
  .tc-modal-body{ padding:18px 22px; overflow-y:auto; overflow-x:hidden; flex:1 1 auto; min-height:0; min-width:0; }
  .tc-modal-hint{ font-size:12.5px; color:var(--tc-ink-mute); margin:0 0 16px; }
  .tc-modal-nome{ margin-bottom:18px; padding-bottom:16px; border-bottom:1px solid var(--tc-line); }
  .tc-modal-nome label{ display:block; font-size:12px; font-weight:600; color:var(--tc-ink); margin-bottom:6px; }
  .tc-modal-nome input{ width:100%; border:1px solid var(--tc-line); border-radius:8px; padding:9px 12px; font-size:13.5px; color:var(--tc-ink); }
  .tc-modal-nome .tc-modal-hint{ margin:6px 0 0; }
  .tc-modal-secao{ margin-bottom:18px; }
  .tc-modal-secao h4{ font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--tc-accent); margin:0 0 10px; }
  .tc-modal-itens{ display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:8px 16px; min-width:0; }
  .tc-modal-item{ display:flex; align-items:center; justify-content:space-between; gap:6px; min-width:0; }
  .tc-modal-item-label{ display:flex; align-items:flex-start; gap:9px; font-size:13.5px; color:var(--tc-ink); cursor:pointer; min-width:0; flex:1 1 auto; }
  .tc-modal-item-label input{ width:16px; height:16px; margin-top:2px; accent-color:var(--tc-accent); flex:none; }
  .tc-modal-item-label span{ min-width:0; overflow-wrap:break-word; word-break:break-word; }
  .tc-ordem-secoes{ list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:6px; }
  .tc-ordem-secoes li{ display:flex; align-items:center; justify-content:space-between; padding:8px 12px; border:1px solid var(--tc-line); border-radius:8px; font-size:13.5px; font-weight:600; color:var(--tc-ink); background:#fbfcfd; }
  .tc-modal-arrows{ display:flex; flex-direction:column; flex:none; }
  .tc-ordem-btn{ border:none; background:transparent; color:var(--tc-ink-mute); cursor:pointer; padding:1px; line-height:0; border-radius:4px; }
  .tc-ordem-btn:hover{ color:var(--tc-accent); background:var(--tc-accent-soft); }
  .tc-modal-foot{ display:flex; justify-content:flex-end; gap:10px; padding:16px 22px; border-top:1px solid var(--tc-line); flex:none; }
  .tc-btn-secundario{ border:1px solid var(--tc-line); background:#fff; color:var(--tc-ink-dim); border-radius:8px; padding:9px 16px; font-size:13px; font-weight:600; cursor:pointer; }
  .tc-btn-primario{ border:none; background:var(--tc-accent); color:#fff; border-radius:8px; padding:9px 18px; font-size:13px; font-weight:600; cursor:pointer; }
  .tc-btn-primario:disabled{ opacity:.6; cursor:default; }
</style>

<div class="tc-root">

  <div class="tc-top">
    <div class="tc-title">
      <div class="tc-icon-badge"><i data-lucide="radar" style="width:20px;height:20px;"></i></div>
      <div>
        <h1>Torre de Comando</h1>
        <div class="tc-sub">Visão consolidada de jornada, pessoas e risco jurídico</div>
      </div>
    </div>
    <div class="tc-filters">
      <form method="get" id="tcFiltroForm">
        <select name="empresa_id" onchange="document.getElementById('tcFiltroForm').submit()">
          <option value="0" <?= $empresaFiltro === 0 ? "selected" : "" ?>>Todas as empresas</option>
          <?php foreach ($empresas as $emp): ?>
            <option value="<?= (int)$emp["empr_nb_id"] ?>" <?= $empresaFiltro === (int)$emp["empr_nb_id"] ? "selected" : "" ?>>
              <?= htmlspecialchars($emp["empr_tx_nome"]) ?><?= $emp["empr_tx_Ehmatriz"] === "sim" ? " (matriz)" : "" ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select name="perfil_visualizacao" onchange="document.getElementById('tcFiltroForm').submit()">
          <option value="">Todos (padrão)</option>
          <?php foreach ($perfis as $p): ?>
            <option value="<?= htmlspecialchars($p["nome"]) ?>" <?= $perfilAtual === $p["nome"] ? "selected" : "" ?>><?= htmlspecialchars($p["nome"]) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <button type="button" class="tc-btn-personalizar" id="tcBtnPersonalizar">
        <i data-lucide="sliders-horizontal" style="width:14px;height:14px;"></i> Personalizar
      </button>
      <button type="button" class="tc-btn-personalizar" id="tcBtnNotificacoes">
        <i data-lucide="bell" style="width:14px;height:14px;"></i> Notificações
      </button>
      <div class="tc-clock"><i data-lucide="clock-3" style="width:14px;height:14px;"></i> <span id="tcClock">--:--:--</span></div>
    </div>
  </div>

  <div class="tc-nota-gestao">
    <div class="tc-nota-principal">
      <div class="tc-estrelas-wrap" style="--pct:<?= round($notaGestao["stars"] / 5 * 100, 1) ?>%;">
        <div class="tc-estrelas-fundo">★★★★★</div>
        <div class="tc-estrelas-preenchidas">★★★★★</div>
      </div>
      <div class="tc-nota-numeros">
        <span class="tc-nota-numero"><?= number_format($notaGestao["nota"], 0) ?>/100</span>
        <span class="tc-nota-titulo"><?= htmlspecialchars($notaGestao["titulo"]) ?></span>
      </div>
      <button type="button" id="tcBtnNotaDetalhe" class="tc-nota-toggle">
        Por que essa nota? <i data-lucide="chevron-down" style="width:14px;height:14px;"></i>
      </button>
    </div>
    <div class="tc-nota-detalhe" id="tcNotaDetalhe">
      <?php foreach ($notaGestao["itens"] as $it): ?>
      <div class="tc-nota-item">
        <div class="tc-nota-item-topo">
          <span><?= htmlspecialchars($it["label"]) ?></span>
          <span><?= number_format($it["pontos"], 1) ?>/<?= $it["peso"] ?> pts</span>
        </div>
        <div class="tc-nota-barra"><div class="tc-nota-barra-fill" style="width:<?= round(min(100, max(0, $it["pct"])), 1) ?>%; background:<?= $it["pct"] >= 70 ? "#16a34a" : ($it["pct"] >= 40 ? "#f59e0b" : "#dc2626") ?>;"></div></div>
        <div class="tc-nota-item-desc"><?= htmlspecialchars($it["explicacao"]) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="tc-secoes">

  <!-- ══ OPERAÇÃO AGORA ═══════════════════════════════════════════ -->
  <div class="tc-section"<?= torre_estilo_ordem($ordemSecoes, "operacao", 0) ?>>
    <div class="tc-section-head"><span class="tc-dot"></span><h2>Operação agora</h2></div>

    <div class="tc-grid" style="margin-bottom:14px;">
      <?php if (torre_visivel("tile_ativos", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["operacao"] ?? [], "tile_ativos", 0) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Ativos<?= torre_info("Pessoas com cadastro ativo no sistema, de todas as ocupações, no recorte de empresa selecionado.") ?></span><div class="tc-tile-icon acc"><i data-lucide="users" style="width:16px;height:16px;"></i></div></div>
        <span class="tc-tile-value"><?= number_format($ativos, 0, ",", ".") ?></span>
        <span class="tc-tile-foot"><?= $inativos ?> inativos · <?= $totalFiliais ?> filiais</span>
        <?= torre_link("cadastro_funcionario.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_atividade", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["operacao"] ?? [], "tile_atividade", 1) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Em atividade<?= torre_info("Pessoas cuja última batida de ponto (nos últimos 7 dias) mostra jornada em andamento, fora de qualquer pausa.") ?></span><div class="tc-tile-icon good"><i data-lucide="activity" style="width:16px;height:16px;"></i></div></div>
        <span class="tc-tile-value good"><?= number_format($emAtividade, 0, ",", ".") ?></span>
        <span class="tc-tile-foot">jornada aberta, fora de pausa</span>
        <?= torre_link("paineis/jornada.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_pausa", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["operacao"] ?? [], "tile_pausa", 2) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Em pausa<?= torre_info("Pessoas cuja última batida foi um início de refeição, espera, descanso ou repouso, ainda sem o fim registrado.") ?></span><div class="tc-tile-icon warn"><i data-lucide="coffee" style="width:16px;height:16px;"></i></div></div>
        <span class="tc-tile-value warn"><?= number_format($emPausa, 0, ",", ".") ?></span>
        <span class="tc-tile-foot">refeição, espera, descanso ou repouso</span>
        <?= torre_link("paineis/jornada.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_fora", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["operacao"] ?? [], "tile_fora", 3) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Fora de jornada<?= torre_info("Pessoas ativas que, neste momento, não têm nenhuma jornada aberta.") ?></span><div class="tc-tile-icon"><i data-lucide="log-out" style="width:16px;height:16px;color:var(--tc-ink-mute);"></i></div></div>
        <span class="tc-tile-value"><?= number_format($foraDeJornada, 0, ",", ".") ?></span>
        <span class="tc-tile-foot">sem jornada aberta agora</span>
        <?= torre_link("espelho_ponto.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_criticas", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["operacao"] ?? [], "tile_criticas", 4) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Jornadas críticas<?= torre_info("Pessoas com jornada em aberto (ou em pausa) sem nenhuma batida de ponto há mais de 24 horas — sinal de possível esquecimento de bater o fim da jornada.") ?></span><div class="tc-tile-icon bad"><i data-lucide="alert-triangle" style="width:16px;height:16px;"></i></div></div>
        <span class="tc-tile-value bad"><?= number_format($jornadasCriticas, 0, ",", ".") ?></span>
        <span class="tc-tile-foot">sem nenhuma batida há mais de 24h</span>
        <?= torre_link("paineis/jornada.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_frota_rastreada", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["operacao"] ?? [], "tile_frota_rastreada", 5) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Frota rastreada<?= torre_info("Pessoas com pelo menos uma batida de ponto com localização enviada pelo celular/dispositivo nas últimas 48 horas.") ?></span><div class="tc-tile-icon acc"><i data-lucide="map-pin" style="width:16px;height:16px;"></i></div></div>
        <span class="tc-tile-value"><?= count($pontosMapa) ?></span>
        <span class="tc-tile-foot">com posição nos últimos 2 dias</span>
        <?= torre_link("espelho_ponto.php") ?>
      </div>
      <?php endif; ?>
    </div>

    <?php
      $mostrarPainelPausa = torre_visivel("painel_pausa", $ocultos);
      $mostrarPainelOcupacao = torre_visivel("painel_ocupacao", $ocultos);
    ?>
    <?php if ($mostrarPainelPausa || $mostrarPainelOcupacao): ?>
    <div class="tc-panel-row" style="grid-template-columns:<?= ($mostrarPainelPausa && $mostrarPainelOcupacao) ? "1.15fr .85fr" : "1fr" ?>;">
      <?php if ($mostrarPainelPausa): ?>
      <div class="tc-panel tc-panel-hero">
        <h3><i data-lucide="pie-chart" style="width:15px;height:15px;"></i> Visão geral do status agora<?= torre_info("Todo mundo ativo agora, agrupado pelo tipo da última batida de ponto: trabalhando, em cada tipo de pausa, ou fora de jornada.") ?></h3>
        <div class="tc-status-geral">
          <div class="tc-chart-wrap tc-chart-hero"><canvas id="chartStatusGeral"></canvas></div>
          <div class="tc-status-legenda">
            <div class="tc-status-item"><span class="tc-swatch" style="background:#16a34a;"></span><span class="tc-status-label">Trabalhando</span><span class="tc-status-valor"><?= number_format($emAtividade,0,",",".") ?></span></div>
            <div class="tc-status-item"><span class="tc-swatch" style="background:#2563eb;"></span><span class="tc-status-label">Em refeição</span><span class="tc-status-valor"><?= number_format($estado["refeicao"],0,",",".") ?></span></div>
            <div class="tc-status-item"><span class="tc-swatch" style="background:#0ea5e9;"></span><span class="tc-status-label">Em espera</span><span class="tc-status-valor"><?= number_format($estado["espera"],0,",",".") ?></span></div>
            <div class="tc-status-item"><span class="tc-swatch" style="background:#f59e0b;"></span><span class="tc-status-label">Em descanso</span><span class="tc-status-valor"><?= number_format($estado["descanso"],0,",",".") ?></span></div>
            <div class="tc-status-item"><span class="tc-swatch" style="background:#eab308;"></span><span class="tc-status-label">Em repouso</span><span class="tc-status-valor"><?= number_format($estado["repouso"],0,",",".") ?></span></div>
            <div class="tc-status-item"><span class="tc-swatch" style="background:#9333ea;"></span><span class="tc-status-label">Repouso embarcado</span><span class="tc-status-valor"><?= number_format($estado["repousoEmbarcado"],0,",",".") ?></span></div>
            <div class="tc-status-item"><span class="tc-swatch" style="background:#64748b;"></span><span class="tc-status-label">Fora de jornada</span><span class="tc-status-valor"><?= number_format($foraDeJornada,0,",",".") ?></span></div>
          </div>
        </div>
        <?= torre_link("paineis/jornada.php") ?>
      </div>
      <?php endif; ?>
      <?php if ($mostrarPainelOcupacao): ?>
      <div class="tc-panel">
        <h3><i data-lucide="bar-chart-3" style="width:15px;height:15px;"></i> Status agora, por ocupação<?= torre_info("Para cada tipo de função (motorista, ajudante, funcionário, terceirizado), quantos estão trabalhando, em pausa ou fora de jornada neste momento.") ?></h3>
        <div class="tc-chart-wrap"><canvas id="chartOcupacao"></canvas></div>
        <?= torre_link("cadastro_funcionario.php") ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ══ FROTA & MAPA ═════════════════════════════════════════════ -->
  <?php if (torre_visivel("painel_disponibilidade", $ocultos) || torre_visivel("painel_mapa", $ocultos)): ?>
  <div class="tc-section"<?= torre_estilo_ordem($ordemSecoes, "frota", 1) ?>>
    <div class="tc-section-head"><span class="tc-dot"></span><h2>Frota &amp; localização</h2></div>

    <div class="tc-panel-row tc-map-row">
      <?php if (torre_visivel("painel_disponibilidade", $ocultos)): ?>
      <div class="tc-panel">
        <h3><i data-lucide="truck" style="width:15px;height:15px;"></i> Disponibilidade de frota<?= torre_info("Motoristas classificados pelo tempo de descanso desde o fim da última jornada registrada no ponto: 11h ou mais = disponível, entre 8h e 11h = parcial, menos de 8h = indisponível. Quem está com jornada em aberto entra em 'Em jornada'. Não considera acordos especiais de descanso.") ?></h3>
        <?php if ($totalMotoristas > 0): ?>
          <div class="tc-chart-wrap"><canvas id="chartDisponibilidade"></canvas></div>
        <?php else: ?>
          <div class="tc-empty-state"><i data-lucide="truck" style="width:26px;height:26px;"></i>Nenhum motorista ativo encontrado.</div>
        <?php endif; ?>
        <?= torre_link("paineis/disponibilidade.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("painel_mapa", $ocultos)): ?>
      <div class="tc-panel">
        <h3><i data-lucide="map" style="width:15px;height:15px;"></i> Mapa ao vivo<?= torre_info("Mostra a última localização enviada pelo celular/dispositivo de cada pessoa junto com uma batida de ponto, nas últimas 48 horas. A cor do marcador é o status daquela pessoa no momento da última batida.") ?></h3>
        <?php if (!empty($pontosMapa)): ?>
          <div id="tcMapa" class="tc-map"></div>
          <div class="tc-legend">
            <div class="tc-legend-item"><span class="tc-swatch" style="background:#16a34a;"></span>Trabalhando</div>
            <div class="tc-legend-item"><span class="tc-swatch" style="background:#f59e0b;"></span>Em pausa</div>
            <div class="tc-legend-item"><span class="tc-swatch" style="background:#64748b;"></span>Fora de jornada</div>
          </div>
          <div class="tc-section-desc" style="margin:8px 0 0;">Posição registrada junto com a batida de ponto — não é rastreamento contínuo, só atualiza quando a pessoa bate ponto.</div>
          <?= torre_link("espelho_ponto.php") ?>
        <?php else: ?>
          <div class="tc-map-empty"><i data-lucide="map-pin-off" style="width:26px;height:26px;"></i>Nenhuma batida com localização nos últimos 2 dias.</div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ══ PESSOAS & RISCO ══════════════════════════════════════════ -->
  <div class="tc-section"<?= torre_estilo_ordem($ordemSecoes, "pessoas", 2) ?>>
    <div class="tc-section-head"><span class="tc-dot"></span><h2>Pessoas &amp; risco</h2></div>

    <div class="tc-grid" style="margin-bottom:14px;">
      <?php if (torre_visivel("tile_ferias_hoje", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["pessoas"] ?? [], "tile_ferias_hoje", 0) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">De férias hoje<?= torre_info("Pessoas com um período de férias ativo que cobre a data de hoje.") ?></span><div class="tc-tile-icon acc"><i data-lucide="umbrella" style="width:16px;height:16px;"></i></div></div>
        <span class="tc-tile-value"><?= count($feriasHoje) ?></span>
        <span class="tc-tile-foot"><?= count($feriasProximas) ?> entram nos próximos 7 dias</span>
        <?= torre_link("cadastro_ferias.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_afastamentos", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["pessoas"] ?? [], "tile_afastamentos", 1) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Afastamentos hoje<?= torre_info("Pessoas com um abono registrado hoje cujo motivo está classificado como afastamento.") ?></span><div class="tc-tile-icon warn"><i data-lucide="user-x" style="width:16px;height:16px;"></i></div></div>
        <span class="tc-tile-value warn"><?= $afastamentosHoje ?></span>
        <span class="tc-tile-foot">ausência justificada como afastamento</span>
        <?= torre_link("cadastro_abono.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_abonos_mes", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["pessoas"] ?? [], "tile_abonos_mes", 2) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Abonos no mês<?= torre_info("Total de abonos (faltas justificadas, atestados, afastamentos etc.) lançados desde o início do mês corrente.") ?></span><div class="tc-tile-icon"><i data-lucide="file-check-2" style="width:16px;height:16px;color:var(--tc-ink-mute);"></i></div></div>
        <span class="tc-tile-value"><?= $totalAbonosMes ?></span>
        <span class="tc-tile-foot">total de abonos lançados este mês</span>
        <?= torre_link("cadastro_abono.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_cnh", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["pessoas"] ?? [], "tile_cnh", 3) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">CNH vencendo (30d)<?= torre_info("Motoristas cuja validade da CNH cadastrada vence dentro dos próximos 30 dias.") ?></span><div class="tc-tile-icon bad"><i data-lucide="id-card" style="width:16px;height:16px;"></i></div></div>
        <span class="tc-tile-value bad"><?= count($cnhVencendo) ?></span>
        <span class="tc-tile-foot">motoristas com CNH a vencer</span>
        <?= torre_link("cadastro_funcionario.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_advertencias", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["pessoas"] ?? [], "tile_advertencias", 4) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Advertências candidatas<?= torre_info("Abonos lançados este mês cujo motivo já está marcado, no cadastro, como passível de advertência disciplinar.") ?></span><div class="tc-tile-icon warn"><i data-lucide="megaphone" style="width:16px;height:16px;"></i></div></div>
        <span class="tc-tile-value warn"><?= $advertencias ?></span>
        <span class="tc-tile-foot">abonos com motivo sinalizado para advertência</span>
        <?= torre_link("cadastro_abono.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_nc_alta", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["pessoas"] ?? [], "tile_nc_alta", 5) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Não conformidade — Alta<?= torre_info("Ocorrências de maior gravidade jurídica (intervalos de descanso não respeitados), da última apuração de conformidade gerada.") ?></span><div class="tc-tile-icon bad"><i data-lucide="scale" style="width:16px;height:16px;"></i></div></div>
        <span class="tc-tile-value bad"><?= number_format($gravAlta, 0, ",", ".") ?></span>
        <span class="tc-tile-foot"><?= $ncRef ? "referente a ".torre_mes_label($ncRef["mes"]) : "sem apuração gerada ainda" ?></span>
        <?= torre_link("paineis/nc_juridica.php") ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if (torre_visivel("lista_ferias_hoje", $ocultos) || torre_visivel("lista_ferias_proximas", $ocultos) || torre_visivel("lista_cnh", $ocultos)): ?>
    <div class="tc-trio">
      <?php if (torre_visivel("lista_ferias_hoje", $ocultos)): ?>
      <div class="tc-panel">
        <h3><i data-lucide="umbrella" style="width:15px;height:15px;"></i> De férias hoje<?= torre_info("Lista nominal de quem tem férias ativas cobrindo a data de hoje.") ?></h3>
        <div class="tc-list">
          <?php if (!empty($feriasHoje)): foreach ($feriasHoje as $f): ?>
            <div class="tc-list-row">
              <span class="tc-list-name"><?= htmlspecialchars($f["nome"]) ?></span>
              <span class="tc-list-meta">até <?= date("d/m", strtotime($f["fim"])) ?></span>
            </div>
          <?php endforeach; else: ?>
            <div class="tc-list-empty">Ninguém de férias hoje.</div>
          <?php endif; ?>
        </div>
        <?= torre_link("cadastro_ferias.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("lista_ferias_proximas", $ocultos)): ?>
      <div class="tc-panel">
        <h3><i data-lucide="calendar-clock" style="width:15px;height:15px;"></i> Entram de férias (7 dias)<?= torre_info("Lista nominal de quem começa um período de férias já cadastrado nos próximos 7 dias.") ?></h3>
        <div class="tc-list">
          <?php if (!empty($feriasProximas)): foreach ($feriasProximas as $f): ?>
            <div class="tc-list-row">
              <span class="tc-list-name"><?= htmlspecialchars($f["nome"]) ?></span>
              <span class="tc-list-meta">a partir de <?= date("d/m", strtotime($f["inicio"])) ?></span>
            </div>
          <?php endforeach; else: ?>
            <div class="tc-list-empty">Nenhuma férias programada para os próximos 7 dias.</div>
          <?php endif; ?>
        </div>
        <?= torre_link("cadastro_ferias.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("lista_cnh", $ocultos)): ?>
      <div class="tc-panel">
        <h3><i data-lucide="id-card" style="width:15px;height:15px;"></i> CNH vencendo (30 dias)<?= torre_info("Lista nominal dos motoristas com validade de CNH cadastrada vencendo nos próximos 30 dias.") ?></h3>
        <div class="tc-list">
          <?php if (!empty($cnhVencendo)): foreach ($cnhVencendo as $c): ?>
            <div class="tc-list-row">
              <span class="tc-list-name"><?= htmlspecialchars($c["nome"]) ?></span>
              <span class="tc-list-meta">vence <?= date("d/m", strtotime($c["validade"])) ?></span>
            </div>
          <?php endforeach; else: ?>
            <div class="tc-list-empty">Nenhuma CNH vencendo nos próximos 30 dias.</div>
          <?php endif; ?>
        </div>
        <?= torre_link("cadastro_funcionario.php") ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (torre_visivel("painel_nc", $ocultos) || torre_visivel("painel_abonos", $ocultos)): ?>
    <div class="tc-panel-row">
      <?php if (torre_visivel("painel_nc", $ocultos)): ?>
      <div class="tc-panel">
        <h3><i data-lucide="scale" style="width:15px;height:15px;"></i> Não conformidade por gravidade<?= torre_info("Total de ocorrências identificadas na última apuração de conformidade trabalhista gerada, agrupadas por gravidade jurídica.") ?>
          <?php if ($ncRef): ?><span class="tc-ref">· <?= torre_mes_label($ncRef["mes"]) ?></span><?php endif; ?>
        </h3>
        <?php if ($ncRef): ?>
          <div class="tc-chart-wrap"><canvas id="chartNC"></canvas></div>
          <div class="tc-legend">
            <div class="tc-legend-item"><span class="tc-swatch" style="background:#dc2626;"></span>Alta — intervalos de descanso não respeitados</div>
            <div class="tc-legend-item"><span class="tc-swatch" style="background:#f59e0b;"></span>Média — jornada acima do previsto</div>
            <div class="tc-legend-item"><span class="tc-swatch" style="background:#64748b;"></span>Baixa — faltas e pequenos desvios</div>
          </div>
          <?= torre_link("paineis/nc_juridica.php") ?>
        <?php else: ?>
          <div class="tc-empty-state">
            <i data-lucide="scale" style="width:26px;height:26px;"></i>
            Nenhuma apuração de conformidade gerada ainda para este recorte.
            <?= torre_link("paineis/nc_juridica.php", "Gerar apuração agora") ?>
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("painel_abonos", $ocultos)): ?>
      <div class="tc-panel">
        <h3><i data-lucide="file-text" style="width:15px;height:15px;"></i> Abonos do mês, por motivo<?= torre_info("Abonos lançados neste mês, agrupados pelo motivo cadastrado: atestado médico, falta justificada, afastamento etc.") ?></h3>
        <?php if (!empty($abonosPorMotivo)): ?>
          <div class="tc-chart-wrap"><canvas id="chartAbonos"></canvas></div>
          <?= torre_link("cadastro_abono.php") ?>
        <?php else: ?>
          <div class="tc-empty-state">
            <i data-lucide="file-text" style="width:26px;height:26px;"></i>
            Nenhum abono lançado neste mês para este recorte.
            <?= torre_link("cadastro_abono.php", "Lançar abono") ?>
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (torre_visivel("painel_ranking_nc", $ocultos)): ?>
    <div class="tc-panel-row" style="grid-template-columns:1fr; margin-top:14px;">
      <div class="tc-panel">
        <h3><i data-lucide="award" style="width:15px;height:15px;"></i> Quem mais precisa de atenção<?= torre_info("Ranking das pessoas com maior penalidade de risco jurídico na última apuração de conformidade — mesmo peso usado no painel de conformidade: Alta pesa 10x mais que Baixa, Média pesa 5x mais.") ?>
          <?php if ($ncRef): ?><span class="tc-ref">· <?= torre_mes_label($ncRef["mes"]) ?></span><?php endif; ?>
        </h3>
        <?php if (!empty($rankingNC)): ?>
          <div class="tc-list" style="max-height:none;">
            <?php foreach ($rankingNC as $posicao => $r): ?>
              <div class="tc-list-row">
                <span class="tc-list-name">#<?= $posicao + 1 ?> · <?= htmlspecialchars($r["nome"]) ?></span>
                <span class="tc-list-meta">
                  <?php if ($r["alta"] > 0): ?><span style="color:var(--tc-bad); font-weight:600;">Alta <?= number_format($r["alta"],0,",",".") ?></span><?php endif; ?>
                  <?php if ($r["media"] > 0): ?> · <span style="color:var(--tc-warn); font-weight:600;">Média <?= number_format($r["media"],0,",",".") ?></span><?php endif; ?>
                  <?php if ($r["baixa"] > 0): ?> · Baixa <?= number_format($r["baixa"],0,",",".") ?><?php endif; ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
          <?= torre_link("paineis/nc_juridica.php", "Ver ranking completo") ?>
        <?php else: ?>
          <div class="tc-empty-state">
            <i data-lucide="award" style="width:26px;height:26px;"></i>
            Nenhuma apuração de conformidade gerada ainda para este recorte.
            <?= torre_link("paineis/nc_juridica.php", "Gerar apuração agora") ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (torre_visivel("painel_tendencia_nc", $ocultos) || torre_visivel("painel_tendencia_abonos", $ocultos)): ?>
    <div class="tc-panel-row" style="margin-top:14px;">
      <?php if (torre_visivel("painel_tendencia_nc", $ocultos)): ?>
      <div class="tc-panel">
        <h3><i data-lucide="trending-up" style="width:15px;height:15px;"></i> Tendência de não conformidade<?= torre_info("Total de ocorrências (todas as gravidades somadas) por mês, com base nas apurações de conformidade já geradas.") ?></h3>
        <?php if (count($tendenciaNC) >= 2): ?>
          <div class="tc-chart-wrap" style="height:190px;"><canvas id="chartTendenciaNC"></canvas></div>
        <?php else: ?>
          <div class="tc-empty-state"><i data-lucide="trending-up" style="width:26px;height:26px;"></i>Ainda não há histórico suficiente (mín. 2 meses apurados).</div>
        <?php endif; ?>
        <?= torre_link("paineis/nc_juridica.php", "Ver conformidades") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("painel_tendencia_abonos", $ocultos)): ?>
      <div class="tc-panel">
        <h3><i data-lucide="trending-up" style="width:15px;height:15px;"></i> Tendência de abonos<?= torre_info("Quantidade de abonos lançados por mês, direto da base de dados (não depende de apuração gerada).") ?></h3>
        <?php if (count($tendenciaAbonos) >= 2): ?>
          <div class="tc-chart-wrap" style="height:190px;"><canvas id="chartTendenciaAbonos"></canvas></div>
        <?php else: ?>
          <div class="tc-empty-state"><i data-lucide="trending-up" style="width:26px;height:26px;"></i>Ainda não há histórico suficiente (mín. 2 meses com abono lançado).</div>
        <?php endif; ?>
        <?= torre_link("cadastro_abono.php", "Ver abonos") ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ══ CUSTO DA JORNADA ═════════════════════════════════════════ -->
  <?php
    $mostrarSecaoCusto =
        torre_visivel("tile_he50", $ocultos) || torre_visivel("tile_he100", $ocultos) ||
        torre_visivel("tile_noturno", $ocultos) || torre_visivel("tile_espera", $ocultos) ||
        torre_visivel("tile_custo", $ocultos) || torre_visivel("tile_saldo", $ocultos) ||
        torre_visivel("painel_tendencia", $ocultos);
  ?>
  <?php if ($mostrarSecaoCusto): ?>
  <div class="tc-section"<?= torre_estilo_ordem($ordemSecoes, "custo", 3) ?>>
    <div class="tc-section-head">
      <span class="tc-dot"></span><h2>Custo da jornada</h2>
      <?php if ($saldoTotais && !empty($saldoTotais["_periodoInicio"])): ?>
        <span class="tc-section-periodo">Período apurado: <?= torre_data_fmt($saldoTotais["_periodoInicio"]) ?> a <?= torre_data_fmt($saldoTotais["_periodoFim"]) ?><?= $saldoTotais["_qtdMotoristas"] > 0 ? " · ".$saldoTotais["_qtdMotoristas"]." pessoas apuradas" : "" ?></span>
      <?php endif; ?>
    </div>

    <?php if ($saldoTotais): ?>
      <?php if (!$temMovimentoNoPeriodo): ?>
        <div class="tc-empty-state" style="padding:14px; margin-bottom:14px; background:#fbf1de; border-radius:10px; text-align:left; align-items:flex-start;">
          <div style="display:flex; align-items:center; gap:8px; color:var(--tc-warn); font-weight:600; font-size:13px;">
            <i data-lucide="info" style="width:16px;height:16px;"></i> Este período apurado está zerado em horas extras.
          </div>
          <div style="font-size:12.5px; color:var(--tc-ink-dim); margin-top:2px;">
            Pode ser um período recém-aberto e ainda sem apuração completa.
            <a href="paineis/saldo.php" style="color:var(--tc-accent); font-weight:600;">Gerar uma apuração mais recente →</a>
          </div>
        </div>
      <?php endif; ?>
      <div class="tc-grid" style="margin-bottom:14px;">
        <?php if (torre_visivel("tile_he50", $ocultos)): ?>
        <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["custo"] ?? [], "tile_he50", 0) ?>>
          <div class="tc-tile-top"><span class="tc-tile-label">HE 50% no período<?= torre_info("Total de horas extras pagas com adicional de 50%, da última apuração de fechamento gerada.") ?></span><div class="tc-tile-icon acc"><i data-lucide="timer" style="width:16px;height:16px;"></i></div></div>
          <span class="tc-tile-value"><?= torre_horas_fmt($saldoTotais["HESemanal"]) ?>h</span>
          <span class="tc-tile-foot">total do período apurado</span>
          <?= torre_link("paineis/saldo.php") ?>
        </div>
        <?php endif; ?>
        <?php if (torre_visivel("tile_he100", $ocultos)): ?>
        <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["custo"] ?? [], "tile_he100", 1) ?>>
          <div class="tc-tile-top"><span class="tc-tile-label">HE 100% no período<?= torre_info("Total de horas extras pagas com adicional de 100% (geralmente domingos, feriados ou excedente diário), da última apuração de fechamento gerada.") ?></span><div class="tc-tile-icon warn"><i data-lucide="timer" style="width:16px;height:16px;"></i></div></div>
          <span class="tc-tile-value warn"><?= torre_horas_fmt($saldoTotais["HESabado"]) ?>h</span>
          <span class="tc-tile-foot">total do período apurado</span>
          <?= torre_link("paineis/saldo.php") ?>
        </div>
        <?php endif; ?>
        <?php if (torre_visivel("tile_noturno", $ocultos)): ?>
        <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["custo"] ?? [], "tile_noturno", 2) ?>>
          <div class="tc-tile-top"><span class="tc-tile-label">Adicional noturno<?= torre_info("Total de horas trabalhadas dentro do período noturno (22h às 5h), com direito a adicional.") ?></span><div class="tc-tile-icon"><i data-lucide="moon" style="width:16px;height:16px;color:var(--tc-ink-mute);"></i></div></div>
          <span class="tc-tile-value"><?= torre_horas_fmt($saldoTotais["adicionalNoturno"]) ?>h</span>
          <span class="tc-tile-foot">trabalho no período noturno</span>
          <?= torre_link("paineis/saldo.php") ?>
        </div>
        <?php endif; ?>
        <?php if (torre_visivel("tile_espera", $ocultos)): ?>
        <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["custo"] ?? [], "tile_espera", 3) ?>>
          <div class="tc-tile-top"><span class="tc-tile-label">Espera indenizada<?= torre_info("Tempo de espera do motorista pago como se fosse hora trabalhada, quando não coberto por acordo específico de compensação.") ?></span><div class="tc-tile-icon"><i data-lucide="hourglass" style="width:16px;height:16px;color:var(--tc-ink-mute);"></i></div></div>
          <span class="tc-tile-value"><?= torre_horas_fmt($saldoTotais["esperaIndenizada"]) ?>h</span>
          <span class="tc-tile-foot">tempo de espera pago como hora trabalhada</span>
          <?= torre_link("paineis/saldo.php") ?>
        </div>
        <?php endif; ?>
        <?php if (torre_visivel("tile_custo", $ocultos)): ?>
        <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["custo"] ?? [], "tile_custo", 4) ?>>
          <div class="tc-tile-top"><span class="tc-tile-label">Custo estimado de HE<?= torre_info("Estimativa em reais: horas extras × (salário ÷ 220h), aplicando 1,5x para HE 50% e 2x para HE 100%. Só entra no cálculo quem tem salário cadastrado.") ?></span><div class="tc-tile-icon bad"><i data-lucide="banknote" style="width:16px;height:16px;"></i></div></div>
          <span class="tc-tile-value bad"><?= torre_moeda_fmt($custoRef["custo"] ?? 0) ?></span>
          <span class="tc-tile-foot">
            <?= $custoRef["semSalario"] ?? 0 ?> pessoas sem salário cadastrado
            <?php if (($custoRef["horasForaDaConta"] ?? 0) > 0): ?>
              — <?= torre_horas_fmt($custoRef["horasForaDaConta"]) ?>h de HE dessas pessoas não entraram nesse custo
            <?php endif; ?>
          </span>
          <?= torre_link("cadastro_funcionario.php", "Cadastrar salários") ?>
        </div>
        <?php endif; ?>
        <?php if (torre_visivel("tile_saldo", $ocultos)): ?>
        <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["custo"] ?? [], "tile_saldo", 5) ?>>
          <div class="tc-tile-top"><span class="tc-tile-label">Saldo final do período<?= torre_info("Saldo acumulado do banco de horas até o fim do período apurado: saldo que já vinha de antes + resultado deste período.") ?></span><div class="tc-tile-icon <?= $saldoTotais["saldoFinal"] >= 0 ? "good" : "bad" ?>"><i data-lucide="landmark" style="width:16px;height:16px;"></i></div></div>
          <span class="tc-tile-value <?= $saldoTotais["saldoFinal"] >= 0 ? "good" : "bad" ?>"><?= torre_horas_fmt($saldoTotais["saldoFinal"]) ?>h</span>
          <span class="tc-tile-foot">banco de horas acumulado</span>
          <?= torre_link("paineis/saldo.php") ?>
        </div>
        <?php endif; ?>
      </div>

      <?php if (torre_visivel("painel_tendencia", $ocultos)): ?>
      <div class="tc-panel-row" style="grid-template-columns:1fr;">
        <div class="tc-panel">
          <h3><i data-lucide="trending-up" style="width:15px;height:15px;"></i> Horas extras acumuladas (50% + 100%) — últimos meses fechados<?= torre_info("Evolução do total de horas extras (50% + 100%) mês a mês, com base nas apurações de fechamento já geradas.") ?>
            <a href="paineis/saldo.php" class="tc-atualizar-link">Atualizar dados →</a>
          </h3>
          <?php if (count($tendenciaHE) >= 2): ?>
            <div class="tc-chart-wrap" style="height:200px;"><canvas id="chartTendencia"></canvas></div>
          <?php else: ?>
            <div class="tc-empty-state"><i data-lucide="trending-up" style="width:26px;height:26px;"></i>Ainda não há histórico suficiente (mín. 2 meses fechados) para uma tendência.</div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="tc-panel">
        <div class="tc-empty-state">
          <i data-lucide="banknote" style="width:26px;height:26px;"></i>
          Nenhuma apuração de horas gerada ainda para este recorte.
          <a href="paineis/saldo.php">Gerar apuração agora →</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- ══ CADASTROS ════════════════════════════════════════════════ -->
  <?php
    $itensCadastros = $manifesto["cadastros"]["itens"];
    $mostrarSecaoCadastros = false;
    foreach (array_keys($itensCadastros) as $__chaveCad) { if (torre_visivel($__chaveCad, $ocultos)) { $mostrarSecaoCadastros = true; break; } }
  ?>
  <?php if ($mostrarSecaoCadastros): ?>
  <div class="tc-section"<?= torre_estilo_ordem($ordemSecoes, "cadastros", 4) ?>>
    <div class="tc-section-head"><span class="tc-dot"></span><h2>Cadastros</h2></div>

    <div class="tc-grid tc-grid-compacta">
      <?php $__ordC = 0; ?>
      <?php if (torre_visivel("tile_c_placas", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["cadastros"] ?? [], "tile_c_placas", $__ordC++) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Placas cadastradas<?= torre_info("Total de veículos/placas cadastrados na empresa selecionada.") ?></span><div class="tc-tile-icon acc"><i data-lucide="truck" style="width:16px;height:16px;"></i></div></div>
        <span class="tc-tile-value"><?= number_format($qtdPlacas,0,",",".") ?></span>
        <?= torre_link("cadastro_placa.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_c_setores", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["cadastros"] ?? [], "tile_c_setores", $__ordC++) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Setores cadastrados<?= torre_info("Total de setores ativos cadastrados no sistema (cadastro compartilhado entre empresas).") ?></span><div class="tc-tile-icon"><i data-lucide="building-2" style="width:16px;height:16px;color:var(--tc-ink-mute);"></i></div></div>
        <span class="tc-tile-value"><?= number_format($qtdSetores,0,",",".") ?></span>
        <?= torre_link("cadastro_setor.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_c_subsetores", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["cadastros"] ?? [], "tile_c_subsetores", $__ordC++) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Subsetores cadastrados<?= torre_info("Total de subsetores ativos, vinculados aos setores cadastrados.") ?></span><div class="tc-tile-icon"><i data-lucide="building" style="width:16px;height:16px;color:var(--tc-ink-mute);"></i></div></div>
        <span class="tc-tile-value"><?= number_format($qtdSubsetores,0,",",".") ?></span>
        <?= torre_link("cadastro_setor.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_c_facial", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["cadastros"] ?? [], "tile_c_facial", $__ordC++) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Cadastro facial concluído<?= torre_info("Pessoas ativas que já têm o reconhecimento facial cadastrado, na empresa selecionada.") ?></span><div class="tc-tile-icon acc"><i data-lucide="scan-face" style="width:16px;height:16px;"></i></div></div>
        <span class="tc-tile-value"><?= number_format($qtdFacial,0,",",".") ?></span>
        <?= torre_link("cadastro_facial.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_c_feriados", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["cadastros"] ?? [], "tile_c_feriados", $__ordC++) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Feriados neste mês<?= torre_info("Feriados ativos cadastrados com data dentro do mês atual (cadastro compartilhado entre empresas).") ?></span><div class="tc-tile-icon"><i data-lucide="calendar-days" style="width:16px;height:16px;color:var(--tc-ink-mute);"></i></div></div>
        <span class="tc-tile-value"><?= number_format($qtdFeriadosMes,0,",",".") ?></span>
        <?= torre_link("cadastro_feriado.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_c_perfilacesso", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["cadastros"] ?? [], "tile_c_perfilacesso", $__ordC++) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Perfis de acesso<?= torre_info("Total de perfis de acesso ativos cadastrados no sistema.") ?></span><div class="tc-tile-icon"><i data-lucide="shield" style="width:16px;height:16px;color:var(--tc-ink-mute);"></i></div></div>
        <span class="tc-tile-value"><?= number_format($qtdPerfilAcesso,0,",",".") ?></span>
        <?= torre_link("cadastro_perfil_acesso.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_c_tipodoc", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["cadastros"] ?? [], "tile_c_tipodoc", $__ordC++) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Tipos de documento<?= torre_info("Total de tipos de documento ativos cadastrados no sistema.") ?></span><div class="tc-tile-icon"><i data-lucide="file-text" style="width:16px;height:16px;color:var(--tc-ink-mute);"></i></div></div>
        <span class="tc-tile-value"><?= number_format($qtdTipoDocumento,0,",",".") ?></span>
        <?= torre_link("cadastro_tipo_doc.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_c_motivos", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["cadastros"] ?? [], "tile_c_motivos", $__ordC++) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Motivos cadastrados<?= torre_info("Total de motivos ativos cadastrados (usados em abonos e ajustes).") ?></span><div class="tc-tile-icon"><i data-lucide="list-checks" style="width:16px;height:16px;color:var(--tc-ink-mute);"></i></div></div>
        <span class="tc-tile-value"><?= number_format($qtdMotivos,0,",",".") ?></span>
        <?= torre_link("cadastro_motivo.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_c_epi", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["cadastros"] ?? [], "tile_c_epi", $__ordC++) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">EPI cadastrados<?= torre_info("Total de tipos de EPI ativos no catálogo (não é a quantidade em estoque).") ?></span><div class="tc-tile-icon"><i data-lucide="hard-hat" style="width:16px;height:16px;color:var(--tc-ink-mute);"></i></div></div>
        <span class="tc-tile-value"><?= number_format($qtdEpiCatalogo,0,",",".") ?></span>
        <?= torre_link("saude_seguranca/cadastro_epi.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_c_epientregue", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["cadastros"] ?? [], "tile_c_epientregue", $__ordC++) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">EPI entregues<?= torre_info("Entregas de EPI com status ativo (em uso), na empresa selecionada.") ?></span><div class="tc-tile-icon good"><i data-lucide="package-check" style="width:16px;height:16px;"></i></div></div>
        <span class="tc-tile-value good"><?= number_format($qtdEpiEntregues,0,",",".") ?></span>
        <?= torre_link("saude_seguranca/entrega_epi.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_c_ajustes", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["cadastros"] ?? [], "tile_c_ajustes", $__ordC++) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Ajustes de ponto solicitados<?= torre_info("Total de solicitações de ajuste de ponto já registradas para pessoas ativas, em qualquer situação (enviada, visualizada, aceita ou não aceita).") ?></span><div class="tc-tile-icon"><i data-lucide="pencil-line" style="width:16px;height:16px;color:var(--tc-ink-mute);"></i></div></div>
        <span class="tc-tile-value"><?= number_format($qtdAjustes,0,",",".") ?></span>
        <?= torre_link("gerenciar_solicitacoes_ajuste.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_c_escalas", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["cadastros"] ?? [], "tile_c_escalas", $__ordC++) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Escalas cadastradas<?= torre_info("Total de escalas cadastradas no sistema (cadastro geral, sem filtro por empresa).") ?></span><div class="tc-tile-icon"><i data-lucide="calendar-clock" style="width:16px;height:16px;color:var(--tc-ink-mute);"></i></div></div>
        <span class="tc-tile-value"><?= number_format($qtdEscalas,0,",",".") ?></span>
        <?= torre_link("paineis/escala_parametro.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_c_diarias", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["cadastros"] ?? [], "tile_c_diarias", $__ordC++) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Diárias cadastradas<?= torre_info("Total de lançamentos de depósito de diária registrados para pessoas ativas, na empresa selecionada.") ?></span><div class="tc-tile-icon"><i data-lucide="wallet" style="width:16px;height:16px;color:var(--tc-ink-mute);"></i></div></div>
        <span class="tc-tile-value"><?= number_format($qtdDiarias,0,",",".") ?></span>
        <?= torre_link("diarias/gestao_diarias.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_c_assinenv", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["cadastros"] ?? [], "tile_c_assinenv", $__ordC++) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Assinaturas enviadas<?= torre_info("Total de documentos enviados para assinatura eletrônica, na empresa selecionada.") ?></span><div class="tc-tile-icon"><i data-lucide="send" style="width:16px;height:16px;color:var(--tc-ink-mute);"></i></div></div>
        <span class="tc-tile-value"><?= number_format($qtdAssinEnviadas,0,",",".") ?></span>
        <?= torre_link("assinatura/documentos.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_c_assinpend", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["cadastros"] ?? [], "tile_c_assinpend", $__ordC++) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Assinaturas pendentes<?= torre_info("Documentos ainda pendentes ou em progresso de assinatura, na empresa selecionada.") ?></span><div class="tc-tile-icon warn"><i data-lucide="clock" style="width:16px;height:16px;"></i></div></div>
        <span class="tc-tile-value warn"><?= number_format($qtdAssinPendentes,0,",",".") ?></span>
        <?= torre_link("assinatura/pendentes.php") ?>
      </div>
      <?php endif; ?>
      <?php if (torre_visivel("tile_c_assinconc", $ocultos)): ?>
      <div class="tc-card tc-tile"<?= torre_estilo_ordem($ordemItens["cadastros"] ?? [], "tile_c_assinconc", $__ordC++) ?>>
        <div class="tc-tile-top"><span class="tc-tile-label">Assinaturas concluídas<?= torre_info("Documentos já concluídos/assinados por todos os signatários, na empresa selecionada.") ?></span><div class="tc-tile-icon good"><i data-lucide="badge-check" style="width:16px;height:16px;"></i></div></div>
        <span class="tc-tile-value good"><?= number_format($qtdAssinConcluidas,0,",",".") ?></span>
        <?= torre_link("assinatura/documentos.php") ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  </div><!-- /tc-secoes -->

</div>

<!-- ══ MODAL: PERSONALIZAR ═════════════════════════════════════════ -->
<div id="tcModalPersonalizar" class="tc-modal-overlay">
  <div class="tc-modal">
    <div class="tc-modal-head">
      <h3>Personalizar painel</h3>
      <button type="button" id="tcModalFechar" class="tc-modal-close"><i data-lucide="x" style="width:20px;height:20px;"></i></button>
    </div>
    <div class="tc-modal-body">
      <div class="tc-modal-nome">
        <label for="tcNomePerfil">Nome desta visualização</label>
        <input type="text" id="tcNomePerfil" maxlength="100" placeholder="Ex.: Visão do Gerente Comercial" value="<?= htmlspecialchars($perfilAtual) ?>">
        <p class="tc-modal-hint">Salve com um nome novo para criar outra visualização, ou mantenha o nome de uma já existente para atualizá-la.</p>
      </div>
      <p class="tc-modal-hint">Desmarque o que não quiser ver nesta visualização.</p>

      <div class="tc-modal-secao">
        <h4>Ordem das seções</h4>
        <p class="tc-modal-hint" style="margin:-4px 0 10px;">Use as setas para decidir a ordem em que as seções aparecem na tela, de cima para baixo.</p>
        <ul class="tc-ordem-secoes" id="tcOrdemSecoes">
          <?php foreach ($ordemSecoes as $chaveSecao): if (!isset($manifesto[$chaveSecao])) continue; ?>
            <li data-secao="<?= htmlspecialchars($chaveSecao) ?>">
              <span><?= htmlspecialchars($manifesto[$chaveSecao]["titulo"]) ?></span>
              <span class="tc-modal-arrows">
                <button type="button" class="tc-ordem-btn" data-dir="up" title="Mover seção para cima"><i data-lucide="chevron-up" style="width:14px;height:14px;"></i></button>
                <button type="button" class="tc-ordem-btn" data-dir="down" title="Mover seção para baixo"><i data-lucide="chevron-down" style="width:14px;height:14px;"></i></button>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <?php foreach ($manifesto as $chaveSecaoManifesto => $secao): ?>
        <div class="tc-modal-secao" data-secao-manifesto="<?= htmlspecialchars($chaveSecaoManifesto) ?>">
          <h4><?= htmlspecialchars($secao["titulo"]) ?></h4>
          <p class="tc-modal-hint" style="margin:-4px 0 10px;">As setas ao lado dos cartões (não disponível em listas e gráficos) definem a ordem deles nesta seção.</p>
          <?php
            $__savedOrder = $ordemItens[$chaveSecaoManifesto] ?? [];
            $__cartoes = array_keys(array_filter($secao["itens"], fn($r, $k) => substr($k, 0, 5) === "tile_", ARRAY_FILTER_USE_BOTH));
            usort($__cartoes, function ($a, $b) use ($__savedOrder) {
                $ia = array_search($a, $__savedOrder, true); $ia = $ia === false ? PHP_INT_MAX : $ia;
                $ib = array_search($b, $__savedOrder, true); $ib = $ib === false ? PHP_INT_MAX : $ib;
                return $ia <=> $ib;
            });
            $__outros = array_keys(array_filter($secao["itens"], fn($r, $k) => substr($k, 0, 5) !== "tile_", ARRAY_FILTER_USE_BOTH));
            $__itensOrdenados = array_merge($__cartoes, $__outros);
          ?>
          <div class="tc-modal-itens">
            <?php foreach ($__itensOrdenados as $chave): $rotulo = $secao["itens"][$chave]; $ehCartao = substr($chave, 0, 5) === "tile_"; ?>
              <div class="tc-modal-item" data-chave="<?= htmlspecialchars($chave) ?>">
                <label class="tc-modal-item-label">
                  <input type="checkbox" value="<?= htmlspecialchars($chave) ?>" <?= torre_visivel($chave, $ocultos) ? "checked" : "" ?>>
                  <span><?= htmlspecialchars($rotulo) ?></span>
                </label>
                <?php if ($ehCartao): ?>
                <span class="tc-modal-arrows">
                  <button type="button" class="tc-ordem-btn" data-dir="up" title="Mover cartão para cima"><i data-lucide="chevron-up" style="width:13px;height:13px;"></i></button>
                  <button type="button" class="tc-ordem-btn" data-dir="down" title="Mover cartão para baixo"><i data-lucide="chevron-down" style="width:13px;height:13px;"></i></button>
                </span>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="tc-modal-foot">
      <button type="button" id="tcModalCancelar" class="tc-btn-secundario">Cancelar</button>
      <button type="button" id="tcModalSalvar" class="tc-btn-primario">Salvar</button>
    </div>
  </div>
</div>

<!-- ══ MODAL: NOTIFICAÇÕES ══════════════════════════════════════════ -->
<div id="tcModalNotificacoes" class="tc-modal-overlay">
  <div class="tc-modal">
    <div class="tc-modal-head">
      <h3>Configurar notificações</h3>
      <button type="button" id="tcModalNotifFechar" class="tc-modal-close"><i data-lucide="x" style="width:20px;height:20px;"></i></button>
    </div>
    <div class="tc-modal-body">
      <p class="tc-modal-hint">Escolha o que deve aparecer no sino de notificações do sistema (topo da tela, em qualquer página).</p>
      <div class="tc-modal-itens">
        <?php foreach ($notifCategoriasDisp as $chave => $rotulo): ?>
          <label class="tc-modal-item">
            <input type="checkbox" class="tc-notif-categoria" value="<?= htmlspecialchars($chave) ?>" <?= in_array($chave, $notifPref["categorias"], true) ? "checked" : "" ?>>
            <span><?= htmlspecialchars($rotulo) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="tc-modal-nome" style="margin-top:18px;">
        <label class="tc-modal-item" style="margin-bottom:10px;">
          <input type="checkbox" id="tcNotifEmailAtivo" <?= $notifPref["email_ativo"] ? "checked" : "" ?>>
          <span>Também quero receber por e-mail</span>
        </label>
        <label for="tcNotifEmail">E-mail para receber as notificações</label>
        <input type="text" id="tcNotifEmail" maxlength="190" placeholder="seuemail@empresa.com.br" value="<?= htmlspecialchars($notifPref["email"]) ?>">
      </div>
    </div>
    <div class="tc-modal-foot">
      <button type="button" id="tcModalNotifCancelar" class="tc-btn-secundario">Cancelar</button>
      <button type="button" id="tcModalNotifSalvar" class="tc-btn-primario">Salvar</button>
    </div>
  </div>
</div>

<script>
(function(){
  if (window.lucide) { lucide.createIcons(); }

  function tick(){
    var d = new Date();
    var el = document.getElementById('tcClock');
    if (el) el.textContent = String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0') + ':' + String(d.getSeconds()).padStart(2,'0');
  }
  tick(); setInterval(tick, 1000);

  // ── Nota de qualidade de gestão ──
  var btnNotaDetalhe = document.getElementById('tcBtnNotaDetalhe');
  var notaDetalhe = document.getElementById('tcNotaDetalhe');
  if (btnNotaDetalhe && notaDetalhe) {
    btnNotaDetalhe.addEventListener('click', function(){
      var aberto = notaDetalhe.classList.toggle('aberto');
      btnNotaDetalhe.classList.toggle('aberto', aberto);
      btnNotaDetalhe.firstChild.textContent = aberto ? 'Ocultar detalhes ' : 'Por que essa nota? ';
    });
  }

  // ── Modal Personalizar ──
  var btnPersonalizar = document.getElementById('tcBtnPersonalizar');
  var modalPersonalizar = document.getElementById('tcModalPersonalizar');
  var btnFecharModal = document.getElementById('tcModalFechar');
  var btnCancelarModal = document.getElementById('tcModalCancelar');
  var btnSalvarModal = document.getElementById('tcModalSalvar');

  function abrirModal(){ if (modalPersonalizar) modalPersonalizar.style.display = 'flex'; }
  function fecharModal(){ if (modalPersonalizar) modalPersonalizar.style.display = 'none'; }

  if (btnPersonalizar) btnPersonalizar.addEventListener('click', abrirModal);
  if (btnFecharModal) btnFecharModal.addEventListener('click', fecharModal);
  if (btnCancelarModal) btnCancelarModal.addEventListener('click', fecharModal);
  if (modalPersonalizar) modalPersonalizar.addEventListener('click', function(e){ if (e.target === modalPersonalizar) fecharModal(); });

  if (modalPersonalizar) {
    modalPersonalizar.querySelectorAll('.tc-ordem-btn').forEach(function(btn){
      btn.addEventListener('click', function(){
        var dir = btn.getAttribute('data-dir');
        var item = btn.closest('li') || btn.closest('.tc-modal-item');
        if (!item || !item.parentNode) return;
        if (dir === 'up') {
          var prev = item.previousElementSibling;
          if (prev) item.parentNode.insertBefore(item, prev);
        } else {
          var next = item.nextElementSibling;
          if (next) item.parentNode.insertBefore(next, item);
        }
      });
    });
  }

  if (btnSalvarModal) {
    btnSalvarModal.addEventListener('click', function(){
      var nomeInput = document.getElementById('tcNomePerfil');
      var nome = nomeInput ? nomeInput.value.trim() : '';
      if (!nome) {
        alert('Dê um nome para essa visualização antes de salvar.');
        if (nomeInput) nomeInput.focus();
        return;
      }
      var ocultos = [];
      modalPersonalizar.querySelectorAll('input[type="checkbox"]').forEach(function(chk){
        if (!chk.checked) ocultos.push(chk.value);
      });
      var ordemSecoes = [];
      modalPersonalizar.querySelectorAll('#tcOrdemSecoes li').forEach(function(li){
        ordemSecoes.push(li.getAttribute('data-secao'));
      });
      var ordemItens = {};
      modalPersonalizar.querySelectorAll('.tc-modal-secao').forEach(function(bloco){
        var lista = bloco.querySelector('.tc-modal-itens');
        var chaveSecaoManifesto = bloco.getAttribute('data-secao-manifesto');
        if (!lista || !chaveSecaoManifesto) return;
        var chavesSecao = [];
        lista.querySelectorAll('.tc-modal-item').forEach(function(item){
          var chave = item.getAttribute('data-chave') || '';
          if (chave.indexOf('tile_') === 0) chavesSecao.push(chave);
        });
        if (chavesSecao.length) ordemItens[chaveSecaoManifesto] = chavesSecao;
      });
      btnSalvarModal.disabled = true;
      btnSalvarModal.textContent = 'Salvando...';
      fetch('torre_preferencia_salvar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nome: nome, ocultos: ocultos, ordemSecoes: ordemSecoes, ordemItens: ordemItens })
      }).then(function(r){ return r.json(); }).then(function(json){
        if (json && json.ok) {
          var url = new URL(window.location.href);
          url.searchParams.set('perfil_visualizacao', nome);
          window.location.href = url.toString();
        } else {
          alert((json && json.msg) ? json.msg : 'Não foi possível salvar. Tente novamente.');
          btnSalvarModal.disabled = false;
          btnSalvarModal.textContent = 'Salvar';
        }
      }).catch(function(){
        alert('Sem comunicação com o servidor.');
        btnSalvarModal.disabled = false;
        btnSalvarModal.textContent = 'Salvar';
      });
    });
  }

  // ── Modal Notificações (mesma tabela/endpoint do sino no cabeçalho) ──
  var btnNotificacoes = document.getElementById('tcBtnNotificacoes');
  var modalNotificacoes = document.getElementById('tcModalNotificacoes');
  var btnFecharModalNotif = document.getElementById('tcModalNotifFechar');
  var btnCancelarModalNotif = document.getElementById('tcModalNotifCancelar');
  var btnSalvarModalNotif = document.getElementById('tcModalNotifSalvar');

  function abrirModalNotif(){ if (modalNotificacoes) modalNotificacoes.style.display = 'flex'; }
  function fecharModalNotif(){ if (modalNotificacoes) modalNotificacoes.style.display = 'none'; }

  if (btnNotificacoes) btnNotificacoes.addEventListener('click', abrirModalNotif);
  if (btnFecharModalNotif) btnFecharModalNotif.addEventListener('click', fecharModalNotif);
  if (btnCancelarModalNotif) btnCancelarModalNotif.addEventListener('click', fecharModalNotif);
  if (modalNotificacoes) modalNotificacoes.addEventListener('click', function(e){ if (e.target === modalNotificacoes) fecharModalNotif(); });

  if (btnSalvarModalNotif) {
    btnSalvarModalNotif.addEventListener('click', function(){
      var categorias = [];
      modalNotificacoes.querySelectorAll('.tc-notif-categoria:checked').forEach(function(chk){ categorias.push(chk.value); });
      var emailAtivo = document.getElementById('tcNotifEmailAtivo').checked;
      var email = document.getElementById('tcNotifEmail').value.trim();
      if (emailAtivo && !email) {
        alert('Informe um e-mail para ativar o envio por e-mail.');
        return;
      }
      btnSalvarModalNotif.disabled = true;
      btnSalvarModalNotif.textContent = 'Salvando...';
      fetch('notificacao_preferencia_salvar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ categorias: categorias, email_ativo: emailAtivo, email: email })
      }).then(function(r){ return r.json(); }).then(function(json){
        if (json && json.ok) {
          window.location.reload();
        } else {
          alert((json && json.msg) ? json.msg : 'Não foi possível salvar. Tente novamente.');
          btnSalvarModalNotif.disabled = false;
          btnSalvarModalNotif.textContent = 'Salvar';
        }
      }).catch(function(){
        alert('Sem comunicação com o servidor.');
        btnSalvarModalNotif.disabled = false;
        btnSalvarModalNotif.textContent = 'Salvar';
      });
    });
  }

  var INK_MUTE = '#8993a1';
  Chart.defaults.font.family = "'Segoe UI', Roboto, Arial, sans-serif";
  Chart.defaults.color = INK_MUTE;
  Chart.defaults.plugins.legend.display = false;

  var estado = <?= json_encode($estado, JSON_UNESCAPED_UNICODE) ?>;
  var ctxStatusGeral = document.getElementById('chartStatusGeral');
  if (ctxStatusGeral) {
    new Chart(ctxStatusGeral, {
      type: 'doughnut',
      data: {
        labels: ['Trabalhando', 'Em refeição', 'Em espera', 'Em descanso', 'Em repouso', 'Repouso embarcado', 'Fora de jornada'],
        datasets: [{
          data: [<?= (int)$emAtividade ?>, estado.refeicao, estado.espera, estado.descanso, estado.repouso, estado.repousoEmbarcado, <?= (int)$foraDeJornada ?>],
          backgroundColor: ['#16a34a', '#2563eb', '#0ea5e9', '#f59e0b', '#eab308', '#9333ea', '#64748b'],
          borderColor: '#fff', borderWidth: 2
        }]
      },
      options: { responsive:true, maintainAspectRatio:false, cutout:'68%',
        plugins:{ legend:{ display:false },
          tooltip:{ callbacks:{ label:function(ctx){ return ctx.label + ': ' + ctx.parsed; } } } } }
    });
  }

  var ocup = <?= json_encode($ocupacaoStatusComparativo, JSON_UNESCAPED_UNICODE) ?>;
  var ctxOcup = document.getElementById('chartOcupacao');
  if (ctxOcup) {
    new Chart(ctxOcup, {
      type: 'bar',
      data: {
        labels: ocup.map(function(o){ return o.ocupacao || 'Não definido'; }),
        datasets: [
          { label: 'Trabalhando', data: ocup.map(function(o){ return o.trabalhando; }), backgroundColor: '#16a34a', borderRadius: 4, maxBarThickness: 26 },
          { label: 'Em pausa', data: ocup.map(function(o){ return o.pausa; }), backgroundColor: '#f59e0b', borderRadius: 4, maxBarThickness: 26 },
          { label: 'Fora de jornada', data: ocup.map(function(o){ return o.fora; }), backgroundColor: '#64748b', borderRadius: 4, maxBarThickness: 26 }
        ]
      },
      options: { indexAxis:'y', responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ display:true, position:'bottom', labels:{ boxWidth:10, boxHeight:10, padding:12, font:{size:11} } } },
        scales:{ x:{ stacked:true, grid:{ color:'#eef1f5' }, ticks:{ precision:0 } }, y:{ stacked:true, grid:{ display:false } } } }
    });
  }

  var disp = <?= json_encode($disponibilidade, JSON_UNESCAPED_UNICODE) ?>;
  var ctxDisp = document.getElementById('chartDisponibilidade');
  if (ctxDisp) {
    new Chart(ctxDisp, {
      type: 'doughnut',
      data: {
        labels: ['Disponível (≥11h)', 'Parcial (8–11h)', 'Indisponível (<8h)', 'Em jornada'],
        datasets: [{ data: [disp.disponivel, disp.parcial, disp.indisponivel, disp.emJornada],
          backgroundColor: ['#16a34a', '#f59e0b', '#dc2626', '#64748b'], borderColor:'#fff', borderWidth:2 }]
      },
      options: { responsive:true, maintainAspectRatio:false, cutout:'62%',
        plugins:{ legend:{ display:true, position:'bottom', labels:{ boxWidth:10, boxHeight:10, padding:12, font:{size:11} } } } }
    });
  }

  <?php if ($ncRef): ?>
  var ctxNC = document.getElementById('chartNC');
  if (ctxNC) {
    new Chart(ctxNC, {
      type: 'bar',
      data: {
        labels: ['Alta', 'Média', 'Baixa'],
        datasets: [{ data: [<?= (int)$gravAlta ?>, <?= (int)$gravMedia ?>, <?= (int)$gravBaixa ?>],
          backgroundColor: ['#dc2626', '#f59e0b', '#64748b'], borderRadius:6, maxBarThickness:64 }]
      },
      options: { responsive:true, maintainAspectRatio:false,
        scales:{ y:{ beginAtZero:true, grid:{ color:'#eef1f5' }, ticks:{ precision:0 } }, x:{ grid:{ display:false } } } }
    });
  }
  <?php endif; ?>

  <?php if (!empty($abonosPorMotivo)): ?>
  var abonos = <?= json_encode($abonosPorMotivo, JSON_UNESCAPED_UNICODE) ?>;
  var ctxAb = document.getElementById('chartAbonos');
  if (ctxAb) {
    new Chart(ctxAb, {
      type: 'bar',
      data: {
        labels: abonos.map(function(a){ return a.motivo; }),
        datasets: [{ data: abonos.map(function(a){ return parseInt(a.total,10); }),
          backgroundColor: '#2563eb', borderRadius:5, maxBarThickness:34 }]
      },
      options: { indexAxis:'y', responsive:true, maintainAspectRatio:false,
        scales:{ x:{ grid:{ color:'#eef1f5' }, ticks:{ precision:0 } }, y:{ grid:{ display:false } } } }
    });
  }
  <?php endif; ?>

  function mesLabel(m){
    var p = m.split('-'); var nomes=['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    return nomes[parseInt(p[1],10)] + '/' + p[0].slice(2);
  }
  function criarGraficoTendencia(elId, serie, cor){
    var el = document.getElementById(elId);
    if (!el) return;
    var labels = Object.keys(serie).map(mesLabel);
    var valores = Object.values(serie);
    new Chart(el, {
      type: 'line',
      data: { labels: labels, datasets: [{ data: valores, borderColor: cor, backgroundColor: cor + '1a',
        fill:true, tension:0.35, pointRadius:4, pointBackgroundColor: cor, pointBorderColor:'#fff', pointBorderWidth:2, borderWidth:2.5 }] },
      options: { responsive:true, maintainAspectRatio:false,
        scales:{ y:{ beginAtZero:true, grid:{ color:'#eef1f5' }, ticks:{ precision:0 } }, x:{ grid:{ display:false } } } }
    });
  }

  <?php if (count($tendenciaHE) >= 2): ?>
  criarGraficoTendencia('chartTendencia', <?= json_encode($tendenciaHE, JSON_UNESCAPED_UNICODE) ?>, '#2563eb');
  <?php endif; ?>

  <?php if (count($tendenciaNC) >= 2): ?>
  criarGraficoTendencia('chartTendenciaNC', <?= json_encode($tendenciaNC, JSON_UNESCAPED_UNICODE) ?>, '#dc2626');
  <?php endif; ?>

  <?php if (count($tendenciaAbonos) >= 2): ?>
  criarGraficoTendencia('chartTendenciaAbonos', <?= json_encode($tendenciaAbonos, JSON_UNESCAPED_UNICODE) ?>, '#f59e0b');
  <?php endif; ?>

  <?php if (!empty($pontosMapa)): ?>
  var pontos = <?= json_encode($pontosMapa, JSON_UNESCAPED_UNICODE) ?>;
  var mapEl = document.getElementById('tcMapa');
  if (mapEl && window.L) {
    var mapa = L.map('tcMapa', { scrollWheelZoom:false });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap', maxZoom: 18
    }).addTo(mapa);

    var cores = { trabalhando:'#16a34a', pausa:'#f59e0b', fora:'#64748b' };
    var bounds = [];
    pontos.forEach(function(p){
      var cor = cores[p.status] || '#64748b';
      var marker = L.circleMarker([p.lat, p.lng], { radius:7, color:'#fff', weight:2, fillColor:cor, fillOpacity:0.95 }).addTo(mapa);
      marker.bindPopup('<b>' + p.nome + '</b><br>' + (p.ocupacao || '') + '<br><small>' + p.quando + '</small>');
      bounds.push([p.lat, p.lng]);
    });
    if (bounds.length) { mapa.fitBounds(bounds, { padding:[24,24], maxZoom:12 }); }
  }
  <?php endif; ?>
})();
</script>

<?php
} // fim renderTorreDeComando()
