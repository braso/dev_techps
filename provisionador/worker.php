<?php
/* ==========================================================================
   Provisionador de empresas — executor (cron / linha de comando)

   Uso:
     php worker.php processar                 processa os pedidos pendentes (para o cron)
     php worker.php status                    lista os últimos pedidos
     php worker.php ver <id>                  mostra o log de um pedido
     php worker.php desfazer <id>             remove o que o pedido criou (banco, pasta, registro)
     php worker.php recopiar <id>             recopia os arquivos do sistema para a empresa do pedido
     php worker.php criar-pedido --nome="..." --sigla=ABC --pasta=abc --admin-login=... [--admin-nome=...] [--admin-senha=...] [--cnpj=...] [--email=...]
     php worker.php checar                    confere a configuração sem alterar nada

   Cron sugerido (a cada minuto):
     * * * * * /usr/local/bin/php /home/USUARIO/public_html/gestaodeponto/provisionador/worker.php processar >/dev/null 2>&1
   ========================================================================== */

require __DIR__ . '/lib.php';

$cmd = $argv[1] ?? 'ajuda';
$opts = [];
foreach (array_slice($argv, 2) as $a) {
    if (preg_match('/^--([a-z-]+)=(.*)$/s', $a, $m)) $opts[$m[1]] = $m[2];
    elseif (!isset($opts['_'])) $opts['_'] = $a;
}

function sair(string $msg, int $code = 1): void { fwrite($code ? STDERR : STDOUT, $msg . PHP_EOL); exit($code); }

try {
    switch ($cmd) {
        case 'processar':
            $lock = fopen(PROV_DIR . '/logs/.lock', 'c') ?: null;
            if (!is_dir(PROV_DIR . '/logs')) { @mkdir(PROV_DIR . '/logs', 0750, true); $lock = fopen(PROV_DIR . '/logs/.lock', 'c'); }
            if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) sair("Já existe um provisionamento em execução.", 0);
            // 1) Remoções pedidas pela tela (status 'desfazer')
            $rsD = prov_master()->query("SELECT prov_nb_id FROM provisionamento WHERE prov_tx_status = 'desfazer' ORDER BY prov_nb_id ASC LIMIT 3");
            $desfeitos = 0;
            while ($d = $rsD->fetch_assoc()) {
                $idD = (int)$d['prov_nb_id'];
                try {
                    prov_etapa($idD, 'DESFAZENDO', 'removendo banco, pasta e acesso da empresa');
                    $feito = prov_desfazer_criados($idD);
                    $resta = prov_criados($idD);
                    prov_atualizar($idD, ['prov_tx_status' => $resta ? 'erro' : 'desfeito', 'prov_tx_dataFim' => date('Y-m-d H:i:s')]);
                    prov_etapa($idD, 'DESFEITO', $feito ? implode(', ', $feito) : 'nada a remover');
                    $desfeitos++;
                } catch (Throwable $e) {
                    prov_atualizar($idD, ['prov_tx_status' => 'erro']);
                    prov_etapa($idD, 'ERRO', 'falha ao desfazer: ' . $e->getMessage());
                }
            }
            // 2) Criações pendentes
            $rs = prov_master()->query("SELECT prov_nb_id FROM provisionamento WHERE prov_tx_status = 'pendente' ORDER BY prov_nb_id ASC LIMIT 3");
            $ids = [];
            while ($r = $rs->fetch_assoc()) $ids[] = (int)$r['prov_nb_id'];
            if (!$ids) { flock($lock, LOCK_UN); sair($desfeitos ? "Remoções executadas: {$desfeitos}." : "Nenhum pedido pendente.", 0); }
            $ok = 0;
            foreach ($ids as $id) { if (prov_processar_pedido($id)) $ok++; }
            flock($lock, LOCK_UN);
            sair("Processados: " . count($ids) . " | concluídos: {$ok} | com erro: " . (count($ids) - $ok), $ok === count($ids) ? 0 : 2);

        case 'status':
            $rs = prov_master()->query("SELECT prov_nb_id, prov_tx_sigla, prov_tx_pasta, prov_tx_status, prov_tx_etapa, prov_tx_dataCadastro, prov_tx_dataFim FROM provisionamento ORDER BY prov_nb_id DESC LIMIT 20");
            printf("%-4s %-14s %-24s %-12s %-16s %-19s\n", 'ID', 'SIGLA', 'PASTA', 'STATUS', 'ETAPA', 'PEDIDO EM');
            while ($r = $rs->fetch_assoc()) {
                printf("%-4s %-14s %-24s %-12s %-16s %-19s\n", $r['prov_nb_id'], $r['prov_tx_sigla'], $r['prov_tx_pasta'], $r['prov_tx_status'], (string)$r['prov_tx_etapa'], $r['prov_tx_dataCadastro']);
            }
            exit(0);

        case 'ver':
            $p = prov_pedido(intval($opts['_'] ?? 0));
            if (!$p) sair("Pedido não encontrado.");
            echo "Pedido #{$p['prov_nb_id']} — {$p['prov_tx_nome']} ({$p['prov_tx_sigla']} / {$p['prov_tx_pasta']}) — {$p['prov_tx_status']}\n";
            echo "Criados: " . ($p['prov_tx_criados'] ?: '{}') . "\n\n" . $p['prov_tx_log'];
            exit(0);

        case 'desfazer':
            $id = intval($opts['_'] ?? 0);
            $p = prov_pedido($id);
            if (!$p) sair("Pedido não encontrado.");
            if ($p['prov_tx_status'] === 'processando') sair("Pedido em processamento; aguarde terminar.");
            $feito = prov_desfazer_criados($id);
            prov_atualizar($id, ['prov_tx_status' => 'desfeito', 'prov_tx_dataFim' => date('Y-m-d H:i:s')]);
            prov_etapa($id, 'DESFEITO', $feito ? implode(', ', $feito) : 'nada a remover');
            sair("Pedido {$id} desfeito: " . ($feito ? implode(', ', $feito) : 'nada a remover'), 0);

        case 'recopiar':
            // Recopia os arquivos do sistema para uma empresa já criada (não mexe em .env, arquivos/ nem uploads)
            $id = intval($opts['_'] ?? 0);
            $p = prov_pedido($id);
            if (!$p) sair("Pedido não encontrado.");
            if ($p['prov_tx_status'] !== 'concluido') sair("Só é possível recopiar em pedido concluído.");
            $cfg = prov_config();
            $destino = $cfg['target_root'] . '/' . $p['prov_tx_pasta'];
            if (!is_file($destino . '/.env')) sair("A pasta da empresa não tem .env; não parece uma empresa válida.");
            $n = prov_copiar_arquivos($cfg['source_dir'], $destino, $cfg['exclude_paths']);
            $esperado = prov_contar_arquivos($cfg['source_dir'], $cfg['exclude_paths']);
            $copiados = prov_contar_arquivos($destino, array_merge($cfg['exclude_paths'], ['.provisionado']));
            prov_etapa($id, 'RECOPIA', "{$n} arquivos copiados (conferido: {$copiados} de {$esperado})");
            sair("Pedido {$id}: {$n} arquivos recopiados para /{$p['prov_tx_pasta']} (conferido: {$copiados} de {$esperado}).", $copiados >= $esperado ? 0 : 2);

        case 'criar-pedido':
            foreach (['nome', 'sigla', 'pasta', 'admin-login'] as $obr) if (empty($opts[$obr])) sair("Faltou --{$obr}");
            $senha = $opts['admin-senha'] ?? prov_senha(10);
            $p = [
                'prov_tx_nome' => $opts['nome'], 'prov_tx_sigla' => strtoupper($opts['sigla']), 'prov_tx_pasta' => strtolower($opts['pasta']),
                'prov_tx_cnpj' => $opts['cnpj'] ?? null, 'prov_tx_email' => $opts['email'] ?? null,
                'prov_tx_adminNome' => $opts['admin-nome'] ?? $opts['admin-login'], 'prov_tx_adminLogin' => $opts['admin-login'],
                'prov_tx_adminSenhaHash' => md5($senha),
            ];
            prov_validar($p);
            $cols = array_keys($p);
            $st = prov_master()->prepare("INSERT INTO provisionamento (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")");
            $vals = array_values($p);
            $st->bind_param(str_repeat('s', count($vals)), ...$vals);
            $st->execute();
            sair("Pedido #" . prov_master()->insert_id . " criado. Senha inicial do administrador '{$p['prov_tx_adminLogin']}': {$senha}", 0);

        case 'checar':
            $cfg = prov_config();
            echo "driver............: {$cfg['driver']}\n";
            echo "prefixo do banco..: '{$cfg['db_prefix']}'" . ($cfg['driver'] === 'uapi' && $cfg['db_prefix'] === '' ? " (vazio: usa o prefixo informado pelo cPanel)" : "") . "\n";
            $desat = trim((string)ini_get('disable_functions'));
            echo "php...............: " . PHP_VERSION . " | proc_open " . (function_exists('proc_open') ? 'ok' : 'BLOQUEADO') . ($desat !== '' ? " | bloqueadas: {$desat}" : "") . "\n";
            echo "empresa modelo....: {$cfg['template_tenant']}\n";
            echo "empresa mestre....: {$cfg['master_tenant']}\n";
            echo "origem arquivos...: {$cfg['source_dir']} " . (is_dir($cfg['source_dir']) ? '[ok]' : '[NAO ENCONTRADA]') . "\n";
            echo "raiz das empresas.: {$cfg['target_root']} " . (is_writable($cfg['target_root']) ? '[gravável]' : '[SEM PERMISSAO DE ESCRITA]') . "\n";
            echo "registro..........: {$cfg['registry_file']}\n";
            $tpl = prov_tenant_env($cfg['template_tenant']);
            $c = prov_conectar($tpl['DB_HOST'], $tpl['DB_USER'], $tpl['DB_PASSWORD'], $tpl['DB_NAME']);
            $n = $c->query("SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'")->fetch_assoc()['n'];
            echo "banco modelo......: {$tpl['DB_NAME']} ({$n} tabelas) [ok]\n";
            prov_master();
            echo "fila de pedidos...: [ok]\n";
            if ($cfg['driver'] === 'uapi') {
                $r = prov_restricoes_banco();
                $n = count(prov_uapi('Mysql', 'list_databases')['result']['data'] ?? []);
                echo "uapi..............: [ok] {$n} bancos na conta\n";
                echo "prefixo (cPanel)..: '{$r['prefix']}' | banco até {$r['max_db']} caracteres | usuário até {$r['max_user']}\n";
                [$dbEx, $userEx] = prov_nomes_banco('empresa_exemplo');
                echo "exemplo de nomes..: banco '{$dbEx}', usuário '{$userEx}'\n";
            } else {
                prov_admin_conn();
                echo "usuário admin.....: {$cfg['admin_user']} [ok]\n";
            }
            exit(0);

        default:
            sair("Uso: php worker.php processar | status | ver <id> | desfazer <id> | criar-pedido --nome= --sigla= --pasta= --admin-login= | checar", 0);
    }
} catch (ProvErro $e) {
    sair("ERRO: " . $e->getMessage());
} catch (Throwable $e) {
    sair("ERRO inesperado: " . $e->getMessage() . " (" . basename($e->getFile()) . ":" . $e->getLine() . ")");
}
