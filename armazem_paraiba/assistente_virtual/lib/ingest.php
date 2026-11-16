<?php
/**
 * Ingestão da base de conhecimento do Assistente Virtual.
 *
 * Fontes: manual do sistema, documentação .md, POPs, mapa de telas e FAQs.
 * A base fica no MySQL do tenant; nenhum conteúdo é enviado para fora.
 */

if (!function_exists('ia_mapa_manual_secao')) {
    function ia_mapa_manual_secao(string $titulo): array {
        $n = ia_normalizar_busca($titulo);
        $mapa = [
            'objetivo' => ['menu_path' => null, 'perfis' => 'todos', 'modulo' => 'geral'],
            'login de acesso' => ['menu_path' => '/index.php', 'perfis' => 'todos', 'modulo' => 'acesso'],
            'esqueseu sua senha' => ['menu_path' => '/recupera_senha.php', 'perfis' => 'todos', 'modulo' => 'acesso'],
            'tela sistema' => ['menu_path' => '/index.php', 'perfis' => 'todos', 'modulo' => 'geral'],
            'rotina de empresa filial' => ['menu_path' => '/cadastro_empresa.php', 'perfis' => 'admin,rh,gestor', 'modulo' => 'cadastros'],
            'rotina de endosso' => ['menu_path' => '/cadastro_endosso.php', 'perfis' => 'admin,rh,gestor', 'modulo' => 'ponto'],
            'rotina de feriado' => ['menu_path' => '/cadastro_feriado.php', 'perfis' => 'admin,rh,gestor', 'modulo' => 'cadastros'],
            'rotina de ferias' => ['menu_path' => '/cadastro_ferias.php', 'perfis' => 'admin,rh,gestor', 'modulo' => 'cadastros'],
            'rotina de funcionario' => ['menu_path' => '/cadastro_funcionario.php', 'perfis' => 'admin,rh,gestor', 'modulo' => 'cadastros'],
            'rotina de macro' => ['menu_path' => '/cadastro_macro.php', 'perfis' => 'admin,rh', 'modulo' => 'cadastros'],
            'rotina de motivos' => ['menu_path' => '/cadastro_motivo.php', 'perfis' => 'admin,rh', 'modulo' => 'cadastros'],
            'rontina de cargo' => ['menu_path' => '/cadastro_operacao.php', 'perfis' => 'admin,rh', 'modulo' => 'cadastros'],
            'rotina de parametro' => ['menu_path' => '/cadastro_parametro.php', 'perfis' => 'admin,rh', 'modulo' => 'cadastros'],
            'rotina de usuario' => ['menu_path' => '/cadastro_usuario.php', 'perfis' => 'admin,rh', 'modulo' => 'cadastros'],
            'rotina de placas' => ['menu_path' => '/cadastro_placa.php', 'perfis' => 'admin,rh', 'modulo' => 'logistica'],
            'rotina de celulares' => ['menu_path' => '/cadastro_celular.php', 'perfis' => 'admin,rh', 'modulo' => 'cadastros'],
            'rotina setor' => ['menu_path' => '/cadastro_setor.php', 'perfis' => 'admin,rh', 'modulo' => 'cadastros'],
            'rotina tipo de documento' => ['menu_path' => '/cadastro_tipo_doc.php', 'perfis' => 'admin,rh', 'modulo' => 'cadastros'],
            'rotina habilidades tecnicas' => ['menu_path' => '/cadastro_habilidade_tecnica.php', 'perfis' => 'admin,rh', 'modulo' => 'cadastros'],
            'rotina habilidades comportamentais' => ['menu_path' => '/cadastro_habilidade_comportamental.php', 'perfis' => 'admin,rh', 'modulo' => 'cadastros'],
            'rotina perfil de acesso' => ['menu_path' => '/cadastro_perfil_acesso.php', 'perfis' => 'admin', 'modulo' => 'seguranca'],
            'rotina permissao de usuarios' => ['menu_path' => '/cadastro_usuario_perfil.php', 'perfis' => 'admin', 'modulo' => 'seguranca'],
            'consultar endossos' => ['menu_path' => '/endosso.php', 'perfis' => 'todos', 'modulo' => 'ponto'],
            'espelho de ponto' => ['menu_path' => '/espelho_ponto.php', 'perfis' => 'todos', 'modulo' => 'ponto'],
            'integracao de pontos' => ['menu_path' => '/carregar_ponto.php', 'perfis' => 'admin,rh', 'modulo' => 'ponto'],
            'nao conformidades' => ['menu_path' => '/nao_conformidade.php', 'perfis' => 'gestor,rh,admin', 'modulo' => 'ponto'],
            'nao cadastrado' => ['menu_path' => '/nao_cadastrados.php', 'perfis' => 'gestor,rh,admin', 'modulo' => 'ponto'],
            'rotinas de paineis' => ['menu_path' => '/dashboard.php', 'perfis' => 'admin,gestor', 'modulo' => 'paineis'],
        ];
        return $mapa[$n] ?? ['menu_path' => null, 'perfis' => 'todos', 'modulo' => 'manual'];
    }
}

if (!function_exists('ia_desativar_documento')) {
    function ia_desativar_documento(int $docId): void {
        if ($docId <= 0) { return; }
        ia_db_query("UPDATE ia_documento SET doc_tx_status='inativo', doc_dt_atualizacao=NOW() WHERE doc_nb_id=?", 'i', [$docId]);
        ia_db_query("UPDATE ia_chunk SET chk_bl_ativo=0 WHERE doc_nb_id=?", 'i', [$docId]);
    }
}

if (!function_exists('ia_ingestar_secoes')) {
    /**
     * Insere/atualiza um documento e seus chunks.
     *
     * @param array $secoes Lista de ['titulo'=>string,'texto'=>string,'menu_path'=>?string,'perfis'=>?string]
     * @param array $meta   titulo, fonte, arquivo, modulo, menu_path, perfis, owner, versao
     */
    function ia_ingestar_secoes(string $titulo, array $secoes, array $meta = [], bool $gerarEmbeddings = false): array {
        $titulo = trim($titulo);
        if ($titulo === '' || empty($secoes)) {
            return ['ok' => false, 'erro' => 'Documento vazio.'];
        }
        $textoCompleto = '';
        foreach ($secoes as $s) {
            $textoCompleto .= "\n\n" . trim(strval($s['texto'] ?? ''));
        }
        $hash = ia_hash_texto($textoCompleto);
        $arquivo = trim(strval($meta['arquivo'] ?? ''));
        $fonte = trim(strval($meta['fonte'] ?? 'manual'));

        $existente = null;
        if ($arquivo !== '') {
            $existente = ia_db_one("SELECT * FROM ia_documento WHERE doc_tx_arquivo=? AND doc_tx_status='ativo' ORDER BY doc_nb_id DESC LIMIT 1", 's', [$arquivo]);
        }
        if (!$existente) {
            $existente = ia_db_one("SELECT * FROM ia_documento WHERE doc_tx_titulo=? AND doc_tx_fonte=? AND doc_tx_status='ativo' ORDER BY doc_nb_id DESC LIMIT 1", 'ss', [$titulo, $fonte]);
        }
        if ($existente && hash_equals(strval($existente['doc_tx_hash'] ?? ''), $hash)) {
            return ['ok' => true, 'skipped' => true, 'doc_id' => (int)$existente['doc_nb_id'], 'chunks' => 0];
        }

        // Desativa versões anteriores do mesmo arquivo/título.
        if ($arquivo !== '') {
            foreach (ia_db_all("SELECT doc_nb_id FROM ia_documento WHERE doc_tx_arquivo=? AND doc_tx_status='ativo'", 's', [$arquivo]) as $d) {
                ia_desativar_documento((int)$d['doc_nb_id']);
            }
        } else {
            foreach (ia_db_all("SELECT doc_nb_id FROM ia_documento WHERE doc_tx_titulo=? AND doc_tx_fonte=? AND doc_tx_status='ativo'", 'ss', [$titulo, $fonte]) as $d) {
                ia_desativar_documento((int)$d['doc_nb_id']);
            }
        }

        $stmt = ia_db_query(
            "INSERT INTO ia_documento
                (doc_tx_titulo, doc_tx_fonte, doc_tx_arquivo, doc_tx_hash, doc_tx_modulo, doc_tx_versao, doc_tx_owner, doc_tx_status, doc_dt_revisao, doc_dt_criacao, doc_dt_atualizacao)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'ativo', NOW(), NOW(), NOW())",
            'sssssss',
            [
                $titulo,
                $fonte,
                $arquivo !== '' ? $arquivo : null,
                $hash,
                trim(strval($meta['modulo'] ?? 'geral')),
                trim(strval($meta['versao'] ?? '1.0')),
                trim(strval($meta['owner'] ?? 'TechPS')),
            ]
        );
        if (!$stmt) {
            return ['ok' => false, 'erro' => 'Falha ao inserir documento.'];
        }
        $docId = ia_db_insert_id($stmt);

        $totalChunks = 0;
        $ordem = 0;
        foreach ($secoes as $secao) {
            $tituloSecao = trim(strval($secao['titulo'] ?? $titulo));
            $textoSecao = trim(strval($secao['texto'] ?? ''));
            if ($textoSecao === '') { continue; }
            $menuPath = trim(strval($secao['menu_path'] ?? ($meta['menu_path'] ?? '')));
            $perfis = trim(strval($secao['perfis'] ?? ($meta['perfis'] ?? 'todos')));
            $chunks = ia_chunkar_texto($textoSecao, 420, 60);
            foreach ($chunks as $chunk) {
                $ordem++;
                $busca = ia_normalizar_busca($tituloSecao . ' ' . $chunk);
                $embedding = null;
                if ($gerarEmbeddings && ia_embeddings_ativos()) {
                    $embedding = ia_ollama_embed($tituloSecao . "\n" . $chunk);
                }
                $ins = ia_db_query(
                    "INSERT INTO ia_chunk
                        (doc_nb_id, chk_nb_ordem, chk_tx_titulo, chk_tx_conteudo, chk_tx_busca, chk_tx_perfis, chk_tx_menu_path, chk_tx_embedding, chk_bl_ativo, chk_dt_criacao)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())",
                    'iissssss',
                    [
                        $docId,
                        $ordem,
                        $tituloSecao,
                        $chunk,
                        $busca,
                        $perfis,
                        $menuPath !== '' ? $menuPath : null,
                        ia_embedding_json($embedding),
                    ]
                );
                if ($ins) { $totalChunks++; }
            }
        }

        ia_meta_gravar($GLOBALS['conn'], 'last_ingest', date('Y-m-d H:i:s'));
        ia_meta_gravar($GLOBALS['conn'], 'last_ingest_doc', $titulo);
        return ['ok' => true, 'doc_id' => $docId, 'chunks' => $totalChunks, 'skipped' => false];
    }
}

if (!function_exists('ia_ingestar_arquivo')) {
    function ia_ingestar_arquivo(string $caminho, array $meta = [], bool $gerarEmbeddings = false): array {
        if (!is_file($caminho)) {
            return ['ok' => false, 'erro' => 'Arquivo não encontrado: ' . $caminho];
        }
        $titulo = trim(strval($meta['titulo'] ?? ia_titulo_de_arquivo($caminho)));
        $ext = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
        $secoes = [];
        if ($ext === 'docx') {
            $paras = ia_docx_para_paragrafos($caminho);
            if (empty($paras)) {
                return ['ok' => false, 'erro' => 'Não foi possível extrair o DOCX. Converta para PDF/MD ou habilite ZipArchive, PharData ou unzip.'];
            }
            $ehManual = stripos($titulo, 'manual') !== false || stripos($caminho, 'Manual') !== false;
            $secoes = $ehManual ? ia_manual_para_secoes($paras) : ia_markdown_para_secoes(implode("\n\n", $paras), $titulo);
        } else {
            $texto = ia_arquivo_para_texto($caminho);
            if ($texto === '') {
                return ['ok' => false, 'erro' => 'Não foi possível extrair texto do arquivo.'];
            }
            $secoes = ia_markdown_para_secoes($texto, $titulo);
        }
        if (empty($secoes)) {
            return ['ok' => false, 'erro' => 'Documento sem seções aproveitáveis.'];
        }
        $meta['arquivo'] = $caminho;
        $meta['titulo'] = $titulo;
        return ia_ingestar_secoes($titulo, $secoes, $meta, $gerarEmbeddings);
    }
}

if (!function_exists('ia_ingestar_manual_md')) {
    function ia_ingestar_manual_md(string $caminho, bool $gerarEmbeddings = false): array {
        if (!is_file($caminho)) {
            return ['ok' => false, 'erro' => 'Manual Markdown não encontrado: ' . $caminho];
        }
        $texto = ia_arquivo_para_texto($caminho);
        if ($texto === '') {
            return ['ok' => false, 'erro' => 'Não foi possível ler o manual Markdown.'];
        }
        $secoes = ia_markdown_para_secoes($texto, 'Manual do Sistema — Espelho de Ponto');
        foreach ($secoes as &$s) {
            $map = ia_mapa_manual_secao($s['titulo']);
            $s['menu_path'] = $map['menu_path'];
            $s['perfis'] = $map['perfis'];
            $s['modulo'] = $map['modulo'];
        }
        unset($s);
        return ia_ingestar_secoes('Manual do Sistema — Espelho de Ponto', $secoes, [
            'fonte' => 'manual',
            'arquivo' => $caminho,
            'modulo' => 'manual',
            'owner' => 'Operação/RH',
            'versao' => 'V08_30_03_2026',
        ], $gerarEmbeddings);
    }
}

if (!function_exists('ia_ingestar_manual_docx')) {
    function ia_ingestar_manual_docx(string $caminho, bool $gerarEmbeddings = false): array {
        if (!is_file($caminho)) {
            return ['ok' => false, 'erro' => 'Manual não encontrado: ' . $caminho];
        }
        $paras = ia_docx_para_paragrafos($caminho);
        if (empty($paras)) {
            return ['ok' => false, 'erro' => 'Não foi possível ler o manual DOCX.'];
        }
        $secoes = ia_manual_para_secoes($paras);
        foreach ($secoes as &$s) {
            $map = ia_mapa_manual_secao($s['titulo']);
            $s['menu_path'] = $map['menu_path'];
            $s['perfis'] = $map['perfis'];
            $s['modulo'] = $map['modulo'];
        }
        unset($s);
        return ia_ingestar_secoes('Manual do Sistema — Espelho de Ponto', $secoes, [
            'fonte' => 'manual',
            'arquivo' => $caminho,
            'modulo' => 'manual',
            'owner' => 'Operação/RH',
            'versao' => 'V08_30_03_2026',
        ], $gerarEmbeddings);
    }
}

if (!function_exists('ia_ingestar_mapa_telas')) {
    function ia_ingestar_mapa_telas(bool $gerarEmbeddings = false): array {
        if (!function_exists('menu_estrutura_do_nivel')) {
            return ['ok' => false, 'erro' => 'menu_estrutura.php não carregado.'];
        }
        $nivelAnterior = $_SESSION['user_tx_nivel'] ?? null;
        $_SESSION['user_tx_nivel'] = 'Super Administrador';
        $menu = menu_estrutura_do_nivel('Super Administrador');
        if ($nivelAnterior !== null) {
            $_SESSION['user_tx_nivel'] = $nivelAnterior;
        } else {
            unset($_SESSION['user_tx_nivel']);
        }

        $secoes = [];
        $secoes[] = [
            'titulo' => 'Como navegar no sistema',
            'texto' => "O sistema é organizado em um menu superior com seções. Cada seção abre uma lista de telas.\n"
                . "Para acessar uma rotina, clique na seção correspondente no menu e depois no nome da tela.\n"
                . "A tela inicial exibe a Torre de Comando para quem tem essa permissão; os demais perfis são direcionados para a tela de ponto ou de boas-vindas.\n"
                . "Os itens exibidos dependem do perfil de acesso configurado em Cadastros > Perfil de Acesso.",
            'menu_path' => '/index.php',
            'perfis' => 'todos',
        ];
        foreach ($menu as $no) {
            if (($no['tipo'] ?? '') === 'link') {
                $secoes[] = [
                    'titulo' => 'Atalho — ' . strval($no['label'] ?? ''),
                    'texto' => 'Atalho direto do menu: ' . strval($no['label'] ?? '') . "\nCaminho: `" . strval($no['path'] ?? '') . "`",
                    'menu_path' => ia_path_normalizado(strval($no['path'] ?? '')),
                    'perfis' => 'todos',
                ];
                continue;
            }
            $linhas = ["Seção do menu: " . strval($no['titulo'] ?? '')];
            foreach (($no['itens'] ?? []) as $item) {
                $linhas[] = '- ' . strval($item['label'] ?? '') . ': `' . strval($item['path'] ?? '') . '`';
            }
            $secoes[] = [
                'titulo' => 'Menu — ' . strval($no['titulo'] ?? ''),
                'texto' => implode("\n", $linhas),
                'menu_path' => null,
                'perfis' => 'todos',
            ];
        }
        return ia_ingestar_secoes('Mapa de Telas do Sistema', $secoes, [
            'fonte' => 'menu',
            'modulo' => 'geral',
            'owner' => 'TechPS',
            'versao' => date('Y-m-d'),
        ], $gerarEmbeddings);
    }
}

if (!function_exists('ia_listar_arquivos_md')) {
    /** Varredura recursiva com scandir (mais previsível que RecursiveDirectoryIterator em mounts Docker). */
    function ia_listar_arquivos_md(string $dir): array {
        $out = [];
        $itens = @scandir($dir);
        if (!is_array($itens)) {
            return $out;
        }
        foreach ($itens as $item) {
            if ($item === '.' || $item === '..') { continue; }
            $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $item;
            $pathNorm = str_replace('\\', '/', $path);
            if (preg_match('#/(vendor|node_modules|tcpdf|\.git|arquivos|logs|assistente_virtual)/#i', $pathNorm . '/')) {
                continue;
            }
            if (preg_match('#(PLANO_|MELHORIAS|VIABILIDADE|TESTES_REGRESSAO|diff_signature|ALTERACOES_)#i', $pathNorm)) {
                continue;
            }
            if (is_dir($path)) {
                $out = array_merge($out, ia_listar_arquivos_md($path));
            } elseif (is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'md') {
                $out[] = $path;
            }
        }
        return $out;
    }
}

if (!function_exists('ia_listar_docs_existentes')) {
    function ia_listar_docs_existentes(): array {
        $tenant = dirname(__DIR__, 2); // armazem_paraiba
        $root = dirname($tenant);
        $arquivos = [];
        foreach (ia_listar_arquivos_md($tenant) as $arquivo) {
            $path = str_replace('\\', '/', $arquivo);
            if (preg_match('#/armazem_paraiba/README\.md$#i', $path)) {
                continue;
            }
            $arquivos[] = $arquivo;
        }
        foreach ([
            $root . '/MANUAL_CORRECAO_FUNCIONARIO.md',
            $root . '/docs/pop/POP-DEV-001.txt',
        ] as $extra) {
            if (is_file($extra)) { $arquivos[] = $extra; }
        }
        $arquivos = array_values(array_unique($arquivos));
        sort($arquivos);
        return $arquivos;
    }
}

if (!function_exists('ia_meta_doc_por_caminho')) {
    /**
     * Define menu_path/perfis para documentos conhecidos. Sem isso, um README
     * administrativo entraria como conteúdo geral e poderia ser recuperado por
     * qualquer perfil.
     */
    function ia_meta_doc_por_caminho(string $arquivo, string $modulo, array $meta): array {
        $p = str_replace('\\', '/', $arquivo);
        $mapa = [
            '/MANUAL_CORRECAO_FUNCIONARIO.md' => ['/correcao_funcionario.php', 'admin'],
            '/diarias/' => ['/diarias/gestao_diarias.php', 'admin,rh,gestor'],
            '/whatsapp/' => ['/whatsapp/index.php', 'admin'],
            '/checklist/' => [null, 'admin,gestor'],
            '/documentos/' => ['/documentos/cadastro_documento.php', 'admin,rh'],
            '/saude_seguranca/' => ['/saude_seguranca/entrega_epi.php', 'admin,rh'],
            '/paineis/' => ['/dashboard.php', 'admin,gestor'],
            '/treinamento/' => ['/treinamento/treinamento_assistir.php', 'todos'],
            '/trocadeturno/' => ['/trocadeturno/solicitar_troca_turno.php', 'todos'],
            '/assinatura/' => ['/assinatura/index.php', 'admin,rh'],
            'LOGS_SYSTEM.md' => ['/logs_sistema.php', 'admin'],
            'DIAGNOSTICO.md' => [null, 'admin'],
            'TOTEM_FACIAL.md' => ['/totem_ponto.php', 'admin,rh'],
            'FICHA_FUNCIONAL_QR.md' => ['/ficha_qrcode.php', 'admin,rh'],
            'FLUXO_AJUSTE_PONTO.md' => ['/ajuste_pontofuncionario.php', 'todos'],
            'ALTERACOES_ESPELHO_IMPRIMIR.md' => ['/espelho_ponto.php', 'todos'],
            'POP-DEV-001' => [null, 'admin'],
        ];
        foreach ($mapa as $chave => $cfg) {
            if (strpos($p, $chave) !== false) {
                $meta['menu_path'] = $cfg[0];
                $meta['perfis'] = $cfg[1];
                return $meta;
            }
        }
        return $meta;
    }
}

if (!function_exists('ia_ingestar_docs_existentes')) {
    function ia_ingestar_docs_existentes(bool $gerarEmbeddings = false): array {
        $tenant = dirname(__DIR__, 2);
        $total = ['docs' => 0, 'chunks' => 0, 'skipped' => 0, 'erros' => []];
        foreach (ia_listar_docs_existentes() as $arquivo) {
            $rel = str_replace('\\', '/', $arquivo);
            $modulo = 'geral';
            if (preg_match('#/armazem_paraiba/([^/]+)/#', $rel, $m)) {
                $modulo = $m[1];
            }
            $meta = [
                'titulo' => ia_titulo_de_arquivo($arquivo),
                'fonte' => 'doc',
                'arquivo' => $arquivo,
                'modulo' => $modulo,
                'owner' => 'TechPS',
                'versao' => '1.0',
            ];
            $base = strtolower($meta['titulo']);
            if (in_array($base, ['readme', 'documentacao', 'design modulo', 'seguranca', 'guia configuracao e uso'], true) && $modulo !== 'geral') {
                $rotulos = [
                    'saude_seguranca' => 'Saúde e Segurança',
                    'trocadeturno' => 'Troca de Turno',
                    'paineis' => 'Painéis',
                    'diarias' => 'Diárias',
                    'whatsapp' => 'WhatsApp',
                    'documentos' => 'Documentos',
                    'treinamento' => 'Treinamento',
                    'checklist' => 'Checklist',
                    'assinatura' => 'Assinatura',
                ];
                $rotulo = $rotulos[$modulo] ?? ucfirst($modulo);
                $meta['titulo'] = $rotulo . ' — ' . $meta['titulo'];
            }
            $sufixos = [
                '/documentos/pop/README.md' => ' (POP)',
                '/documentos/termos/README.md' => ' (Termos)',
                '/whatsapp/docs/README.md' => ' (Docs)',
            ];
            foreach ($sufixos as $trecho => $sufixo) {
                if (strpos(str_replace('\\', '/', $arquivo), $trecho) !== false) {
                    $meta['titulo'] .= $sufixo;
                    break;
                }
            }
            $meta = ia_meta_doc_por_caminho($arquivo, $modulo, $meta);
            $r = ia_ingestar_arquivo($arquivo, $meta, $gerarEmbeddings);
            if (!empty($r['ok'])) {
                if (!empty($r['skipped'])) { $total['skipped']++; }
                else { $total['docs']++; $total['chunks'] += (int)($r['chunks'] ?? 0); }
            } else {
                $total['erros'][] = basename($arquivo) . ': ' . ($r['erro'] ?? 'erro');
            }
        }
        return $total;
    }
}

if (!function_exists('ia_faq_path_por_pergunta')) {
    function ia_faq_path_por_pergunta(string $pergunta): string {
        $n = ia_normalizar_busca($pergunta);
        $mapa = [
            'acessar o sistema' => '/index.php',
            'senha' => '/recupera_senha.php',
            'torre de comando' => '/dashboard.php',
            'limite de km' => '/diarias/parametros_diarias.php',
            'valores padrao' => '/diarias/parametros_diarias.php',
            'saldo negativo' => '/diarias/gestao_diarias.php',
            'diaria cheia' => '/diarias/gestao_diarias.php',
            'diaria' => '/diarias/gestao_diarias.php',
            'cnpj' => '/cadastro_empresa.php',
            'empresa' => '/cadastro_empresa.php',
            'adicional noturno' => '/espelho_ponto.php',
            'cor vermelha' => '/espelho_ponto.php',
            'funcionario' => '/cadastro_funcionario.php',
            'endosso' => '/cadastro_endosso.php',
            'espelho' => '/espelho_ponto.php',
            'nao conformidade' => '/nao_conformidade.php',
            'feriado' => '/cadastro_feriado.php',
            'ferias' => '/cadastro_ferias.php',
            'parametro' => '/cadastro_parametro.php',
            'usuario' => '/cadastro_usuario.php',
            'liberar o acesso' => '/cadastro_perfil_acesso.php',
            'perfil' => '/cadastro_perfil_acesso.php',
            'importar' => '/carregar_ponto.php',
            'macro' => '/cadastro_macro.php',
            'motivo' => '/cadastro_motivo.php',
            'nao cadastrado' => '/nao_cadastrados.php',
            'paineis' => '/dashboard.php',
            'chamado' => '/suporte/index.php',
        ];
        foreach ($mapa as $chave => $path) {
            if (strpos($n, $chave) !== false) {
                return $path;
            }
        }
        return '';
    }
}

if (!function_exists('ia_ingestar_faq_tabela')) {
    function ia_ingestar_faq_tabela(): array {
        $arquivo = __DIR__ . '/../knowledge/faq_seed.md';
        if (!is_file($arquivo)) {
            return ['ok' => false, 'erro' => 'faq_seed.md não encontrado.'];
        }
        $texto = ia_arquivo_para_texto($arquivo);
        $secoes = ia_markdown_para_secoes($texto, 'Perguntas Frequentes');
        $inseridas = 0;
        $atualizadas = 0;
        foreach ($secoes as $s) {
            $pergunta = trim(strval($s['titulo'] ?? ''));
            $resposta = trim(strval($s['texto'] ?? ''));
            if ($pergunta === '' || $resposta === '') { continue; }
            // Ignora o preâmbulo do documento (blockquote de apresentação).
            if (strpos(ltrim($resposta), '>') === 0) { continue; }
            $norm = ia_normalizar_busca($pergunta);
            $path = ia_faq_path_por_pergunta($pergunta);
            $existente = ia_db_one("SELECT faq_nb_id FROM ia_faq WHERE faq_tx_pergunta=? LIMIT 1", 's', [$pergunta]);
            if ($existente) {
                ia_db_query("UPDATE ia_faq SET faq_tx_resposta=?, faq_tx_menu_path=?, faq_dt_atualizacao=NOW() WHERE faq_nb_id=?", 'ssi', [$resposta, $path, (int)$existente['faq_nb_id']]);
                $atualizadas++;
            } else {
                ia_db_query("INSERT INTO ia_faq (faq_tx_pergunta, faq_tx_resposta, faq_tx_modulo, faq_tx_menu_path, faq_tx_status, faq_dt_atualizacao) VALUES (?, ?, 'geral', ?, 'ativo', NOW())", 'sss', [$pergunta, $resposta, $path]);
                $inseridas++;
            }
        }
        return ['ok' => true, 'inseridas' => $inseridas, 'atualizadas' => $atualizadas];
    }
}

if (!function_exists('ia_ingestar_faq_seed')) {
    function ia_ingestar_faq_seed(bool $gerarEmbeddings = false): array {
        $arquivo = __DIR__ . '/../knowledge/faq_seed.md';
        if (!is_file($arquivo)) {
            return ['ok' => false, 'erro' => 'faq_seed.md não encontrado.'];
        }
        $tabela = ia_ingestar_faq_tabela();
        $texto = ia_arquivo_para_texto($arquivo);
        $secoes = ia_markdown_para_secoes($texto, 'Perguntas Frequentes');
        foreach ($secoes as &$s) {
            $s['menu_path'] = ia_faq_path_por_pergunta(strval($s['titulo'] ?? ''));
            $s['perfis'] = 'todos';
        }
        unset($s);
        $doc = ia_ingestar_secoes('Perguntas Frequentes do Sistema', $secoes, [
            'fonte' => 'faq',
            'arquivo' => $arquivo,
            'modulo' => 'geral',
            'owner' => 'Suporte/TechPS',
            'versao' => date('Y-m-d'),
        ], $gerarEmbeddings);
        return ['ok' => !empty($doc['ok']), 'tabela' => $tabela, 'documento' => $doc];
    }
}

if (!function_exists('ia_reindexar_tudo')) {
    function ia_reindexar_tudo(bool $gerarEmbeddings = false): array {
        $root = dirname(__DIR__, 3); // raiz do repositório
        $manual = $root . '/docs/Detalhamento_Manual_Espelho de Ponto - V08_30_03_2026.docx';
        $manualMd = __DIR__ . '/../knowledge/manual_espelho_ponto.md';
        $resultado = ['manual' => null, 'docs' => null, 'menu' => null, 'faq' => null];

        if (is_file($manual) && ia_docx_disponivel()) {
            $resultado['manual'] = ia_ingestar_manual_docx($manual, $gerarEmbeddings);
        } elseif (is_file($manualMd)) {
            $resultado['manual'] = ia_ingestar_manual_md($manualMd, $gerarEmbeddings);
        } else {
            $resultado['manual'] = ['ok' => false, 'erro' => 'Manual DOCX/MD não encontrado.'];
        }

        $resultado['docs'] = ia_ingestar_docs_existentes($gerarEmbeddings);
        $resultado['menu'] = ia_ingestar_mapa_telas($gerarEmbeddings);
        $resultado['faq'] = ia_ingestar_faq_seed($gerarEmbeddings);
        return $resultado;
    }
}

if (!function_exists('ia_resetar_base')) {
    function ia_resetar_base(): void {
        ia_db_query("DELETE FROM ia_chunk");
        ia_db_query("DELETE FROM ia_documento");
        ia_db_query("DELETE FROM ia_faq");
        ia_meta_gravar($GLOBALS['conn'], 'last_ingest', '');
        ia_meta_gravar($GLOBALS['conn'], 'last_ingest_doc', '');
    }
}

if (!function_exists('ia_relatorio_cobertura')) {
    function ia_relatorio_cobertura(): array {
        $linhas = ia_db_all(
            "SELECT d.doc_tx_modulo AS modulo, d.doc_tx_fonte AS fonte,
                    COUNT(DISTINCT d.doc_nb_id) AS docs, COUNT(c.chk_nb_id) AS chunks
               FROM ia_documento d
               LEFT JOIN ia_chunk c ON c.doc_nb_id = d.doc_nb_id AND c.chk_bl_ativo = 1
              WHERE d.doc_tx_status = 'ativo'
              GROUP BY d.doc_tx_modulo, d.doc_tx_fonte
              ORDER BY docs DESC, chunks DESC"
        );
        $totalDocs = (int)(ia_db_val("SELECT COUNT(*) FROM ia_documento WHERE doc_tx_status='ativo'") ?? 0);
        $totalChunks = (int)(ia_db_val("SELECT COUNT(*) FROM ia_chunk WHERE chk_bl_ativo=1") ?? 0);
        $comEmbedding = (int)(ia_db_val("SELECT COUNT(*) FROM ia_chunk WHERE chk_bl_ativo=1 AND chk_tx_embedding IS NOT NULL AND chk_tx_embedding <> ''") ?? 0);
        return [
            'linhas' => $linhas,
            'total_docs' => $totalDocs,
            'total_chunks' => $totalChunks,
            'com_embedding' => $comEmbedding,
            'ultima_ingestao' => ia_meta_ler($GLOBALS['conn'], 'last_ingest'),
        ];
    }
}
