<?php

// Wrapper seguro para query; evita quebrar o fluxo em erro de banco.
function diar_query($sql, $types = '', $vars = array()) {
    try {
        return query($sql, $types, $vars);
    } catch (Exception $e) {
        return false;
    }
}

// Faz fetch_assoc somente quando o retorno da query e valido.
function diar_fetch_assoc_safe($res) {
    if (!($res instanceof mysqli_result)) {
        return array();
    }
    $row = mysqli_fetch_assoc($res);
    return is_array($row) ? $row : array();
}

// Retorna valor de array com fallback padrao.
function diar_val($arr, $key, $default = null) {
    return (is_array($arr) && array_key_exists($key, $arr)) ? $arr[$key] : $default;
}

// Acesso seguro a chaves de array sem avisos de indice indefinido.
function diar_s($arr, $k, $d = '') {
    return (is_array($arr) && isset($arr[$k])) ? $arr[$k] : $d;
}

// Acesso seguro a sessao (funciona inclusive fora do contexto web/CLI).
function diar_sessao($k, $d = null) {
    return (isset($_SESSION) && is_array($_SESSION) && isset($_SESSION[$k])) ? $_SESSION[$k] : $d;
}

// Escreve trilha tecnica no arquivo de debug do modulo de diarias.
function diar_log_runtime($mensagem) {
    $linha = date('Y-m-d H:i:s')." | ".$mensagem.PHP_EOL;
    @file_put_contents(dirname(__DIR__)."/debug_log_diarias.txt", $linha, FILE_APPEND);
}

// Verifica se uma coluna existe em uma tabela.
function diar_colunaExiste($tabela, $coluna) {
    $tabela = preg_replace('/[^a-zA-Z0-9_]/', '', $tabela);
    $coluna = preg_replace('/[^a-zA-Z0-9_]/', '', $coluna);
    if ($tabela === '' || $coluna === '') {
        return false;
    }
    $res = diar_query("SHOW COLUMNS FROM {$tabela} LIKE '{$coluna}'");
    return ($res instanceof mysqli_result) && mysqli_num_rows($res) > 0;
}

// Garante estruturas minimas do modulo (deposito, consumo e parametros).
function diar_ensureSchema() {
    // Deposito: valor pago ao motorista e a quantos dias aquele valor e referente.
    diar_query("CREATE TABLE IF NOT EXISTS diaria_deposito (
        depr_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        depr_nb_entidade INT NOT NULL,
        depr_tx_data DATE NOT NULL,
        depr_nb_dias INT NOT NULL DEFAULT 1,
        depr_tx_valor_total DECIMAL(10,2) NOT NULL DEFAULT 0,
        depr_tx_valor_dia DECIMAL(10,2) NOT NULL DEFAULT 0,
        depr_tx_observacao TEXT,
        depr_nb_user INT DEFAULT NULL,
        depr_tx_dataCadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_entidade (depr_nb_entidade),
        INDEX idx_data (depr_tx_data)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // Consumo: lancamento dia a dia da diaria consumida pelo motorista.
    diar_query("CREATE TABLE IF NOT EXISTS diaria_consumo (
        dcon_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        dcon_nb_entidade INT NOT NULL,
        dcon_tx_data DATE NOT NULL,
        dcon_tx_tipo ENUM('cheia','outra') NOT NULL DEFAULT 'cheia',
        dcon_tx_valor DECIMAL(10,2) NOT NULL DEFAULT 0,
        dcon_tx_observacao TEXT,
        dcon_nb_user INT DEFAULT NULL,
        dcon_tx_dataCadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_entidade (dcon_nb_entidade),
        INDEX idx_data (dcon_tx_data)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    diar_query("CREATE TABLE IF NOT EXISTS diaria_parametro (
        diar_pa_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        diar_pa_tx_chave VARCHAR(60) NOT NULL,
        diar_pa_tx_valor VARCHAR(60) NOT NULL,
        diar_pa_tx_descricao VARCHAR(255),
        diar_pa_tx_status ENUM('ativo','inativo') DEFAULT 'ativo',
        diar_pa_tx_dataCadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_chave (diar_pa_tx_chave)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // Base (matriz/filial) com raio GPS para detectar "pernoite fora da base".
    diar_query("CREATE TABLE IF NOT EXISTS diaria_base (
        diba_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        diba_nb_empresa INT NOT NULL,
        diba_tx_nome VARCHAR(150) NOT NULL,
        diba_tx_latitude DECIMAL(10,7) DEFAULT NULL,
        diba_tx_longitude DECIMAL(10,7) DEFAULT NULL,
        diba_nb_raio INT NOT NULL DEFAULT 1000,
        diba_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
        diba_nb_user INT DEFAULT NULL,
        diba_tx_dataCadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_empresa (diba_nb_empresa)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // Colunas novas no consumo: rastro automatico, placa, km, pernoite, jornada e alertas.
    if (diar_colunaExiste('diaria_consumo', 'dcon_tx_placa')) {} else {
        diar_query("ALTER TABLE diaria_consumo ADD COLUMN dcon_tx_placa VARCHAR(12) DEFAULT NULL AFTER dcon_nb_entidade");
    }
    if (!diar_colunaExiste('diaria_consumo', 'dcon_tx_origem')) {
        diar_query("ALTER TABLE diaria_consumo ADD COLUMN dcon_tx_origem ENUM('manual','auto') NOT NULL DEFAULT 'manual' AFTER dcon_tx_tipo");
    }
    if (!diar_colunaExiste('diaria_consumo', 'dcon_tx_km')) {
        diar_query("ALTER TABLE diaria_consumo ADD COLUMN dcon_tx_km DECIMAL(10,2) DEFAULT NULL AFTER dcon_tx_valor");
    }
    if (!diar_colunaExiste('diaria_consumo', 'dcon_tx_pernoite')) {
        diar_query("ALTER TABLE diaria_consumo ADD COLUMN dcon_tx_pernoite ENUM('sim','nao') DEFAULT NULL AFTER dcon_tx_km");
    }
    if (!diar_colunaExiste('diaria_consumo', 'dcon_tx_jornada_inicio')) {
        diar_query("ALTER TABLE diaria_consumo ADD COLUMN dcon_tx_jornada_inicio DATETIME DEFAULT NULL AFTER dcon_tx_pernoite");
        diar_query("ALTER TABLE diaria_consumo ADD COLUMN dcon_tx_jornada_fim DATETIME DEFAULT NULL AFTER dcon_tx_jornada_inicio");
    }
    if (!diar_colunaExiste('diaria_consumo', 'dcon_tx_detalhes')) {
        diar_query("ALTER TABLE diaria_consumo ADD COLUMN dcon_tx_detalhes TEXT AFTER dcon_tx_observacao");
    }

    // Amplia o ENUM de tipo para comportar os tipos automaticos da Clausula Decima Quarta.
    $tipoAtual = diar_fetch_assoc_safe(diar_query("SHOW COLUMNS FROM diaria_consumo LIKE 'dcon_tx_tipo'"));
    if (!empty($tipoAtual) && strpos((string)diar_val($tipoAtual, 'Type', ''), 'almoco') === false) {
        diar_query("ALTER TABLE diaria_consumo MODIFY COLUMN dcon_tx_tipo ENUM('cheia','sem_pernoite','almoco','outra') NOT NULL DEFAULT 'cheia'");
    }

    // Coluna para marcar POI como base de referencia (Lei do Motorista / CCT RN).
    if (!diar_colunaExiste('poi', 'poi_tx_ehbase')) {
        diar_query("ALTER TABLE poi ADD COLUMN poi_tx_ehbase ENUM('sim','nao') NOT NULL DEFAULT 'nao' AFTER poi_tx_status");
    }

    diar_semearParametros();
}

// Valores padrao da Clausula Decima Quarta (diarias para viagens).
function diar_parametrosPadrao() {
    return array(
        'valor_pernoite' => array('107.00', 'Diaria com pernoite (R$) - intermunicipais e/ou interestaduais'),
        'valor_sem_pernoite' => array('55.00', 'Diaria sem pernoite (R$)'),
        'valor_almoco' => array('40.00', 'Diaria de almoco (R$) - percursos ate 80 km que retornam a base'),
        'limite_km_almoco' => array('80', 'Limite de km (ida): ate = almoco R$40, acima = sem pernoite R$55'),
        'valor_diaria_cheia' => array('107.00', 'Valor padrao da diaria cheia usado no lancamento manual do gestor'),
        'distancia_pernoite_metros' => array('1000', 'Distancia (em metros) alem do raio da base para considerar pernoite. Ex.: raio 110 + 1000 = 1110m do centro = pernoite'),
        'url_api_logistica' => array('https://logistica.integracao.techpsgj.com.br', 'URL base da API de logistica (GPS) para consultar posicoes quando as batidas nao tem coordenadas'),
        'autogerar_consumo' => array('sim', 'Gera automaticamente o consumo do dia anterior ao abrir a gestao (sim/nao)'),
        'limite_dias_autogeracao' => array('0', 'Quantidade maxima de dias anteriores para gerar automaticamente (0 = desativado)')
    );
}

// Insere valores padrao apenas para chaves ainda inexistentes.
function diar_semearParametros() {
    $padrao = diar_parametrosPadrao();
    foreach ($padrao as $chave => $dados) {
        $res = diar_fetch_assoc_safe(diar_query(
            "SELECT diar_pa_nb_id FROM diaria_parametro WHERE diar_pa_tx_chave = ? LIMIT 1",
            "s",
            array($chave)
        ));
        if (empty($res)) {
            diar_query(
                "INSERT INTO diaria_parametro (diar_pa_tx_chave, diar_pa_tx_valor, diar_pa_tx_descricao)
                 VALUES (?, ?, ?)",
                "sss",
                array($chave, $dados[0], $dados[1])
            );
        }
    }
}

// Busca todos os parametros ativos do modulo em formato chave => valor.
function diar_buscarParametros() {
    $res = diar_query(
        "SELECT diar_pa_tx_chave, diar_pa_tx_valor
         FROM diaria_parametro
         WHERE diar_pa_tx_status = 'ativo'"
    );
    $out = array();
    if ($res instanceof mysqli_result) {
        while ($r = mysqli_fetch_assoc($res)) {
            $out[$r['diar_pa_tx_chave']] = $r['diar_pa_tx_valor'];
        }
    }
    return $out;
}

// Atualiza ou cria um parametro do modulo.
function diar_salvarParametro($chave, $valor, $descricao = '') {
    $chave = trim((string)$chave);
    $valor = trim((string)$valor);
    if ($chave === '' || $valor === '') {
        return false;
    }
    $atual = diar_fetch_assoc_safe(diar_query(
        "SELECT diar_pa_nb_id FROM diaria_parametro WHERE diar_pa_tx_chave = ? LIMIT 1",
        "s",
        array($chave)
    ));
    if (!empty($atual)) {
        diar_query(
            "UPDATE diaria_parametro SET diar_pa_tx_valor = ? WHERE diar_pa_tx_chave = ?",
            "ss",
            array($valor, $chave)
        );
    } else {
        diar_query(
            "INSERT INTO diaria_parametro (diar_pa_tx_chave, diar_pa_tx_valor, diar_pa_tx_descricao)
             VALUES (?, ?, ?)",
            "sss",
            array($chave, $valor, $descricao)
        );
    }
    return true;
}

// Converte valores monetarios (R$ 1.234,56 / 1234,56 / 1234.56) em float.
function diar_parseValorMonetario($valor) {
    $valor = trim((string)$valor);
    if ($valor === '') {
        return 0.0;
    }
    $valor = preg_replace('/[^\d.,\-]/', '', $valor);
    $negativo = (strpos($valor, '-') === 0);
    $valor = ltrim($valor, '-');
    if (substr_count($valor, ',') > 0) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }
    $num = floatval($valor);
    return $negativo ? -$num : $num;
}

// Formata float para exibicao em reais.
function diar_formatarValor($valor) {
    return 'R$ '.number_format(floatval($valor), 2, ',', '.');
}

// Normaliza datas vindas da tela para formato SQL (Y-m-d).
function diar_dataParaSql($valor) {
    $valor = trim(strval($valor));
    if ($valor === '') {
        return '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
        return $valor;
    }
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $valor, $m)) {
        return $m[3].'-'.$m[2].'-'.$m[1];
    }
    return $valor;
}

// Carrega dados do usuario logado a partir da entidade da sessao.
function diar_buscarUsuarioAtual() {
    $entidadeId = intval(diar_val($_SESSION, 'user_nb_entidade', 0));
    if ($entidadeId <= 0) {
        return array();
    }

    $r = diar_fetch_assoc_safe(diar_query(
        "SELECT e.*, u.user_nb_id, u.user_tx_nome, u.user_tx_cpf
         FROM entidade e
         JOIN user u ON u.user_nb_entidade = e.enti_nb_id
         WHERE e.enti_nb_id = ? LIMIT 1",
        "i",
        array($entidadeId)
    ));

    return is_array($r) ? $r : array();
}

// Lista empresas ativas: matriz primeiro, filiais em seguida.
function diar_buscarEmpresas() {
    $res = diar_query(
        "SELECT empr_nb_id, empr_tx_nome, empr_tx_fantasia, empr_tx_cnpj, empr_tx_Ehmatriz, empr_tx_cnpj_matriz
         FROM empresa
         WHERE empr_tx_status = 'ativo'
         ORDER BY (empr_tx_Ehmatriz = 'sim') DESC, empr_tx_nome ASC"
    );
    return ($res instanceof mysqli_result) ? mysqli_fetch_all($res, MYSQLI_ASSOC) : array();
}

// ID da empresa matriz (fallback para a primeira empresa ativa).
function diar_empresaMatrizId() {
    $r = diar_fetch_assoc_safe(diar_query(
        "SELECT empr_nb_id FROM empresa
         WHERE empr_tx_status = 'ativo' AND empr_tx_Ehmatriz = 'sim'
         ORDER BY empr_nb_id ASC LIMIT 1"
    ));
    $id = intval(diar_val($r, 'empr_nb_id', 0));
    if ($id <= 0) {
        $r2 = diar_fetch_assoc_safe(diar_query(
            "SELECT empr_nb_id FROM empresa
             WHERE empr_tx_status = 'ativo'
             ORDER BY empr_nb_id ASC LIMIT 1"
        ));
        $id = intval(diar_val($r2, 'empr_nb_id', 0));
    }
    return $id;
}

// Dados de uma empresa pelo ID.
function diar_buscarEmpresa($empresaId) {
    $empresaId = intval($empresaId);
    if ($empresaId <= 0) {
        return array();
    }
    $r = diar_fetch_assoc_safe(diar_query(
        "SELECT empr_nb_id, empr_tx_nome, empr_tx_fantasia, empr_tx_Ehmatriz
         FROM empresa WHERE empr_nb_id = ? LIMIT 1",
        "i",
        array($empresaId)
    ));
    return is_array($r) ? $r : array();
}

// Funcionarios ativos da empresa selecionada (nome e matricula para o combo).
function diar_buscarFuncionariosEmpresa($empresaId) {
    $empresaId = intval($empresaId);
    if ($empresaId <= 0) {
        return array();
    }
    $res = diar_query(
        "SELECT e.enti_nb_id, e.enti_tx_nome, e.enti_tx_matricula,
                COALESCE(MAX(u.user_tx_nome), e.enti_tx_nome) AS user_nome
         FROM entidade e
         LEFT JOIN user u ON u.user_nb_entidade = e.enti_nb_id
         WHERE e.enti_nb_empresa = ? AND e.enti_tx_status = 'ativo'
         GROUP BY e.enti_nb_id, e.enti_tx_nome, e.enti_tx_matricula
         ORDER BY user_nome ASC",
        "i",
        array($empresaId)
    );
    return ($res instanceof mysqli_result) ? mysqli_fetch_all($res, MYSQLI_ASSOC) : array();
}

// Busca dados basicos de uma entidade pelo ID (nome/matricula).
function diar_buscarPorEntidade($entidadeId) {
    $entidadeId = intval($entidadeId);
    if ($entidadeId <= 0) {
        return array();
    }

    $r = diar_fetch_assoc_safe(diar_query(
        "SELECT e.*, u.user_nb_id, u.user_tx_nome
         FROM entidade e
         LEFT JOIN user u ON u.user_nb_entidade = e.enti_nb_id
         WHERE e.enti_nb_id = ? LIMIT 1",
        "i",
        array($entidadeId)
    ));

    return is_array($r) ? $r : array();
}

// Busca nomes de setor/subsetor e retorna tambem seus IDs.
function diar_buscarSetorSubsetor($entidadeId) {
    $row = diar_fetch_assoc_safe(diar_query(
        "SELECT enti_setor_id, enti_subSetor_id FROM entidade WHERE enti_nb_id = ? LIMIT 1",
        "i",
        array(intval($entidadeId))
    ));

    $setor = 'N/A';
    $subsetor = '';
    $setorId = intval(diar_val($row, 'enti_setor_id', 0));
    $subsetorId = intval(diar_val($row, 'enti_subSetor_id', 0));

    if ($setorId > 0) {
        $r = diar_fetch_assoc_safe(diar_query("SELECT grup_tx_nome FROM grupos_documentos WHERE grup_nb_id = ? LIMIT 1", "i", array($setorId)));
        $setor = trim((string)diar_val($r, 'grup_tx_nome', 'N/A'));
    }

    if ($subsetorId > 0) {
        $r = diar_fetch_assoc_safe(diar_query("SELECT sbgr_tx_nome FROM sbgrupos_documentos WHERE sbgr_nb_id = ? LIMIT 1", "i", array($subsetorId)));
        $subsetor = trim((string)diar_val($r, 'sbgr_tx_nome', ''));
    }

    return array($setor, $subsetor, $setorId, $subsetorId);
}

// Saldo do motorista: total depositado, consumido, saldo em reais e em dias.
// $periodo no formato YYYY-MM limita o calculo ao mes (vazio = todos).
function diar_saldoMotorista($entidadeId, $periodo = '') {
    $entidadeId = intval($entidadeId);
    $out = array(
        'depositado' => 0.0,
        'consumido' => 0.0,
        'saldo' => 0.0,
        'dias_depositados' => 0,
        'dias_consumidos' => 0,
        'saldo_dias' => 0
    );

    if ($entidadeId <= 0) {
        return $out;
    }

    $filtroDep = '';
    $filtroCon = '';
    if (preg_match('/^\d{4}-\d{2}$/', $periodo)) {
        $filtroDep = " AND depr_tx_data LIKE '".$periodo."-%'";
        $filtroCon = " AND dcon_tx_data LIKE '".$periodo."-%'";
    }

    $dep = diar_fetch_assoc_safe(diar_query(
        "SELECT IFNULL(SUM(depr_tx_valor_total),0) AS total, IFNULL(SUM(depr_nb_dias),0) AS dias
         FROM diaria_deposito WHERE depr_nb_entidade = ? {$filtroDep}",
        "i",
        array($entidadeId)
    ));

    $con = diar_fetch_assoc_safe(diar_query(
        "SELECT IFNULL(SUM(dcon_tx_valor),0) AS total, COUNT(*) AS dias
         FROM diaria_consumo WHERE dcon_nb_entidade = ? {$filtroCon}",
        "i",
        array($entidadeId)
    ));

    $out['depositado'] = round(floatval(diar_val($dep, 'total', 0)), 2);
    $out['dias_depositados'] = intval(diar_val($dep, 'dias', 0));
    $out['consumido'] = round(floatval(diar_val($con, 'total', 0)), 2);
    $out['dias_consumidos'] = intval(diar_val($con, 'dias', 0));
    $out['saldo'] = round($out['depositado'] - $out['consumido'], 2);
    $out['saldo_dias'] = $out['dias_depositados'] - $out['dias_consumidos'];

    return $out;
}

// Lista os lancamentos de consumo de um motorista (dia a dia).
function diar_buscarConsumos($entidadeId, $periodo = '') {
    $entidadeId = intval($entidadeId);
    if ($entidadeId <= 0) {
        return array();
    }

    $filtro = '';
    if (preg_match('/^\d{4}-\d{2}$/', $periodo)) {
        $filtro = " AND c.dcon_tx_data LIKE '".$periodo."-%'";
    }

    $res = diar_query(
        "SELECT c.*, u.user_tx_nome AS gestor_nome
         FROM diaria_consumo c
         LEFT JOIN user u ON u.user_nb_id = c.dcon_nb_user
         WHERE c.dcon_nb_entidade = ? {$filtro}
         ORDER BY c.dcon_tx_data DESC, c.dcon_nb_id DESC",
        "i",
        array($entidadeId)
    );

    return ($res instanceof mysqli_result) ? mysqli_fetch_all($res, MYSQLI_ASSOC) : array();
}

// Lista os depositos de um motorista.
function diar_buscarDepositos($entidadeId, $periodo = '') {
    $entidadeId = intval($entidadeId);
    if ($entidadeId <= 0) {
        return array();
    }

    $filtro = '';
    if (preg_match('/^\d{4}-\d{2}$/', $periodo)) {
        $filtro = " AND d.depr_tx_data LIKE '".$periodo."-%'";
    }

    $res = diar_query(
        "SELECT d.*, u.user_tx_nome AS gestor_nome
         FROM diaria_deposito d
         LEFT JOIN user u ON u.user_nb_id = d.depr_nb_user
         WHERE d.depr_nb_entidade = ? {$filtro}
         ORDER BY d.depr_tx_data DESC, d.depr_nb_id DESC",
        "i",
        array($entidadeId)
    );

    return ($res instanceof mysqli_result) ? mysqli_fetch_all($res, MYSQLI_ASSOC) : array();
}

// Motoristas que ja possuem qualquer lancamento, com nome para o combo rapido.
function diar_buscarMotoristasLancamento() {
    $res = diar_query(
        "SELECT e.enti_nb_id, e.enti_tx_nome, e.enti_tx_matricula,
                COALESCE(MAX(u.user_tx_nome), e.enti_tx_nome) AS user_nome
         FROM (
            SELECT depr_nb_entidade AS id FROM diaria_deposito
            UNION
            SELECT dcon_nb_entidade AS id FROM diaria_consumo
         ) t
         JOIN entidade e ON e.enti_nb_id = t.id
         LEFT JOIN user u ON u.user_nb_entidade = e.enti_nb_id
         GROUP BY e.enti_nb_id, e.enti_tx_nome, e.enti_tx_matricula
         ORDER BY e.enti_tx_nome ASC"
    );

    return ($res instanceof mysqli_result) ? mysqli_fetch_all($res, MYSQLI_ASSOC) : array();
}

// Nome amigavel do tipo de consumo diario.
function diar_consumoTipoLabel($tipo) {
    $labels = array(
        'cheia' => 'Diaria cheia (pernoite)',
        'sem_pernoite' => 'Diaria sem pernoite',
        'almoco' => 'Diaria de almoco',
        'outra' => 'Outro valor'
    );
    return isset($labels[$tipo]) ? $labels[$tipo] : ucfirst((string)$tipo);
}

// Nome amigavel da origem do lancamento.
function diar_consumoOrigemLabel($origem) {
    if ($origem === 'auto') {
        return "<span class='label label-info' title='Gerado automaticamente pelo sistema'>Auto</span>";
    }
    return "<span class='label label-default'>Manual</span>";
}

// Identifica super admin usando flag dedicada e nivel textual da sessao.
function diar_isSuperAdmin() {
    if (intval(diar_val($_SESSION, 'user_nb_superadmin', 0)) === 1) {
        return true;
    }
    $nivel = trim(strval(diar_val($_SESSION, 'user_tx_nivel', '')));
    return (bool)preg_match('/super\s+administrador/i', $nivel);
}

/* ==========================================================================
   CONTROLE AUTOMATICO DE DIARIAS
   Motor de regras baseado na Clausula Decima Quarta da CCT RN:
   1) pernoite fora da base  -> diaria cheia (com pernoite)
   2) km do dia > limite     -> diaria sem pernoite
   3) km <= limite e retorno -> diaria de almoco
   Fonte de km/pernoite: API de logistica (server.js) ou SQL no mesmo banco.
   ========================================================================== */

// Distancia em metros entre dois pontos (Haversine).
function diar_distanciaMeters($lat1, $lon1, $lat2, $lon2) {
    $lat1 = floatval($lat1); $lon1 = floatval($lon1);
    $lat2 = floatval($lat2); $lon2 = floatval($lon2);
    if ($lat1 == 0 && $lon1 == 0) return PHP_FLOAT_MAX;
    if ($lat2 == 0 && $lon2 == 0) return PHP_FLOAT_MAX;
    $raio = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $raio * $c;
}

// Bases ativas de uma empresa.
function diar_buscarBases($empresaId) {
    $empresaId = intval($empresaId);
    if ($empresaId <= 0) {
        return array();
    }
    $res = diar_query(
        "SELECT * FROM diaria_base
         WHERE diba_tx_status = 'ativo' AND diba_nb_empresa = ?
         ORDER BY diba_tx_nome ASC",
        "i",
        array($empresaId)
    );
    return ($res instanceof mysqli_result) ? mysqli_fetch_all($res, MYSQLI_ASSOC) : array();
}

// POIs ativos com coordenadas, para aproveitar no cadastro de base.
function diar_buscarPois() {
    $res = diar_query(
        "SELECT poi_nb_id, poi_tx_nome, poi_tx_latitude, poi_tx_longitude, poi_nb_raio, poi_tx_endereco,
                poi_tx_ehbase
         FROM poi
         WHERE poi_tx_status = 'ativo'
           AND poi_tx_latitude IS NOT NULL AND poi_tx_longitude IS NOT NULL
           AND poi_tx_latitude <> 0 AND poi_tx_longitude <> 0
         ORDER BY poi_tx_ehbase DESC, poi_tx_nome ASC"
    );
    return ($res instanceof mysqli_result) ? mysqli_fetch_all($res, MYSQLI_ASSOC) : array();
}

// Retorna o POI marcado como base (ehbase=sim). Null se nao houver.
function diar_buscarPoiBase() {
    $r = diar_fetch_assoc_safe(diar_query(
        "SELECT poi_nb_id, poi_tx_nome, poi_tx_latitude, poi_tx_longitude, poi_nb_raio
         FROM poi WHERE poi_tx_status = 'ativo' AND poi_tx_ehbase = 'sim' LIMIT 1"
    ));
    return (!empty($r['poi_nb_id'])) ? $r : null;
}

// Marca um POI como base e desliga todos os outros.
function diar_marcarPoiBase($poiId) {
    $poiId = intval($poiId);
    if ($poiId <= 0) { return false; }
    diar_query("UPDATE poi SET poi_tx_ehbase = 'nao' WHERE poi_tx_ehbase = 'sim'");
    diar_query("UPDATE poi SET poi_tx_ehbase = 'sim' WHERE poi_nb_id = ?", "i", array($poiId));
    diar_log_runtime("POI {$poiId} marcado como base");
    return true;
}

// Base mais proxima do ponto informado (retorna a base e a distancia em metros).
function diar_baseMaisProxima($lat, $lon, $empresaId) {
    $melhor = null;
    $melhorDist = PHP_FLOAT_MAX;
    foreach (diar_buscarBases($empresaId) as $base) {
        $dist = diar_distanciaMeters($lat, $lon, diar_val($base, 'diba_tx_latitude', 0), diar_val($base, 'diba_tx_longitude', 0));
        if ($dist < $melhorDist) {
            $melhorDist = $dist;
            $melhor = $base;
        }
    }
    return array($melhor, $melhorDist);
}

// Placa usada pelo funcionario no dia: veiculo vinculado no cadastro ou placa do inicio de jornada.
function diar_placaDoDia($entidadeId, $data) {
    $entidadeId = intval($entidadeId);
    $data = diar_dataParaSql($data);
    if ($entidadeId <= 0 || $data === '') {
        return '';
    }
    $r = diar_fetch_assoc_safe(diar_query(
        "SELECT plac_tx_placa FROM placa
         WHERE plac_nb_entidade = ? AND plac_tx_placa <> '' AND plac_tx_placa IS NOT NULL
         ORDER BY plac_nb_id DESC LIMIT 1",
        "i",
        array($entidadeId)
    ));
    if (!empty($r['plac_tx_placa'])) {
        return $r['plac_tx_placa'];
    }
    // Fallback: placa registrada no inicio de jornada do dia.
    $p = diar_fetch_assoc_safe(diar_query(
        "SELECT p.pont_tx_placa
         FROM ponto p
         WHERE p.pont_tx_status = 'ativo'
           AND p.pont_tx_matricula = (
                SELECT enti_tx_matricula FROM entidade WHERE enti_nb_id = ? LIMIT 1
           )
           AND p.pont_tx_tipo = 1
           AND p.pont_tx_placa IS NOT NULL AND p.pont_tx_placa <> ''
           AND p.pont_tx_data BETWEEN ? AND ?
         ORDER BY p.pont_tx_data ASC LIMIT 1",
        "iss",
        array($entidadeId, $data.' 00:00:00', $data.' 23:59:59')
    ));
    return strval(diar_val($p, 'pont_tx_placa', ''));
}

// Inicio/fim de jornada do dia a partir da tabela ponto (macroponto 1 = inicio, 2 = fim).
function diar_jornadaDoDia($entidadeId, $data) {
    $entidadeId = intval($entidadeId);
    $data = diar_dataParaSql($data);
    $out = array('inicio' => '', 'fim' => '', 'ativo' => false);
    if ($entidadeId <= 0 || $data === '') {
        return $out;
    }
    $r = diar_fetch_assoc_safe(diar_query(
        "SELECT enti_tx_matricula FROM entidade WHERE enti_nb_id = ? LIMIT 1",
        "i",
        array($entidadeId)
    ));
    $matricula = strval(diar_val($r, 'enti_tx_matricula', ''));
    if ($matricula === '') {
        return $out;
    }
    $res = diar_query(
        "SELECT p.pont_tx_data, p.pont_tx_tipo
         FROM ponto p
         WHERE p.pont_tx_status = 'ativo'
           AND p.pont_tx_matricula = ?
           AND p.pont_tx_tipo IN (1, 2)
           AND p.pont_tx_data BETWEEN ? AND ?
         ORDER BY p.pont_tx_data ASC, p.pont_nb_id ASC",
        "sss",
        array($matricula, $data.' 00:00:00', $data.' 23:59:59')
    );
    if ($res instanceof mysqli_result) {
        while ($row = mysqli_fetch_assoc($res)) {
            if (intval($row['pont_tx_tipo']) === 1 && $out['inicio'] === '') {
                $out['inicio'] = $row['pont_tx_data'];
            } elseif (intval($row['pont_tx_tipo']) === 2) {
                $out['fim'] = $row['pont_tx_data'];
            }
        }
    }
    $out['ativo'] = ($out['inicio'] !== '');
    return $out;
}
// Pernoite via ultima batida com coordenadas na tabela ponto.

function diar_pernoiteViaPonto($entidadeId, $data, $empresaId, $parametros = array()) {
    $parametros = empty($parametros) ? diar_buscarParametros() : $parametros;
    $out = array('pernoite' => null, 'pico_km' => null);

    $entidadeId = intval($entidadeId);
    $data = diar_dataParaSql($data);
    if ($entidadeId <= 0 || $data === '') {
        return $out;
    }

    // Busca a matricula.
    $rMat = diar_fetch_assoc_safe(diar_query(
        "SELECT enti_tx_matricula FROM entidade WHERE enti_nb_id = ? LIMIT 1",
        "i", array($entidadeId)
    ));
    $matricula = strval(diar_val($rMat, 'enti_tx_matricula', ''));
    if ($matricula === '') {
        return $out;
    }

    // Todas as batidas do dia com coordenadas.
    $res = diar_query(
        "SELECT pont_tx_data, pont_tx_latitude, pont_tx_longitude
         FROM ponto
         WHERE pont_tx_status = 'ativo'
           AND pont_tx_matricula = ?
           AND pont_tx_latitude IS NOT NULL AND pont_tx_latitude <> '' AND pont_tx_latitude <> '0'
           AND pont_tx_longitude IS NOT NULL AND pont_tx_longitude <> ''
           AND pont_tx_data BETWEEN ? AND ?
         ORDER BY pont_tx_data ASC",
        "sss",
        array($matricula, $data.' 00:00:00', $data.' 23:59:59')
    );
    if (!($res instanceof mysqli_result)) {
        return $out;
    }
    $batidas = array();
    while ($row = mysqli_fetch_assoc($res)) {
        $batidas[] = $row;
    }
    if (empty($batidas)) {
        // Sem batidas com coordenadas: tentar GPS como fallback (api/sql).
        return diar_pernoiteViaGPSFallback($entidadeId, $data, $empresaId, $parametros);
    }

    // Buscar POI base (ehbase=sim) — referencia unica. O raio vem do cadastro do POI.
    $poiBase = diar_buscarPoiBase();
    if ($poiBase === null) {
        // Fallback: diaria_base (se existir).
        $bases = diar_buscarBases(intval($empresaId));
        if (empty($bases)) {
            return $out;
        }
        $base = $bases[0];
        $latBase = floatval(diar_val($base, 'diba_tx_latitude', 0));
        $lonBase = floatval(diar_val($base, 'diba_tx_longitude', 0));
        $raioBase = intval(diar_val($base, 'diba_nb_raio', 1000));
    } else {
        $latBase = floatval($poiBase['poi_tx_latitude']);
        $lonBase = floatval($poiBase['poi_tx_longitude']);
        $raioBase = intval($poiBase['poi_nb_raio']);
    }
    if ($latBase == 0 && $lonBase == 0) {
        return $out;
    }

    // Pico = maior distancia de qualquer batida ate a base (km de ida, sem retorno).
    $picoMetros = 0.0;
    foreach ($batidas as $b) {
        $dist = diar_distanciaMeters($latBase, $lonBase, floatval($b['pont_tx_latitude']), floatval($b['pont_tx_longitude']));
        if ($dist > $picoMetros) {
            $picoMetros = $dist;
        }
    }
    $out['pico_km'] = round($picoMetros / 1000, 2);

    // Regra CCT RN: fim da jornada fora da base = pernoite (qualquer hora).
    // Limiar = raio da base + distancia_pernoite_metros (configuravel).
    $distanciaPernoite = intval(diar_val($parametros, 'distancia_pernoite_metros', 1000));
    $limiarPernoite = $raioBase + $distanciaPernoite;
    $ultima = end($batidas);
    $distUltima = diar_distanciaMeters($latBase, $lonBase, floatval($ultima['pont_tx_latitude']), floatval($ultima['pont_tx_longitude']));
    $out['pernoite'] = ($distUltima > $limiarPernoite) ? 'sim' : 'nao';

    return $out;
}

// Fallback GPS: quando as batidas nao tem coordenadas, tenta usar posicoes GPS do veiculo.
// Retorna array('pernoite' => ..., 'pico_km' => ...).
function diar_pernoiteViaGPSFallback($entidadeId, $data, $empresaId, $parametros = array()) {
    $out = array('pernoite' => null, 'pico_km' => null);
    $parametros = empty($parametros) ? diar_buscarParametros() : $parametros;

    $placa = diar_placaDoDia($entidadeId, $data);
    if ($placa === '') {
        return $out;
    }

    $posicoes = array();

    // 1) Tentar SQL direto (TECHPS_LOGISTICA_POS no mesmo banco).
    if (diar_colunaExiste('TECHPS_LOGISTICA_POS', 'hodometro')) {
        $res = diar_query(
            "SELECT latitude, longitude, hodometro, moduleTime
             FROM TECHPS_LOGISTICA_POS
             WHERE vehicle_plate = ? AND DATE(moduleTime) = ?
               AND latitude IS NOT NULL AND longitude IS NOT NULL
             ORDER BY moduleTime ASC",
            "ss",
            array($placa, $data)
        );
        if ($res instanceof mysqli_result && mysqli_num_rows($res) > 0) {
            while ($row = mysqli_fetch_assoc($res)) {
                $posicoes[] = array(
                    'lat' => floatval($row['latitude']),
                    'lon' => floatval($row['longitude']),
                    'hodometro' => floatval($row['hodometro'])
                );
            }
        }
    }

    // 2) Tentar API externa de logistica (POST /data1 — mesmo fluxo do logistica.js).
    if (empty($posicoes)) {
        $apiUrl = rtrim(strval(diar_val($parametros, 'url_api_logistica', '')), '/');
        if ($apiUrl !== '') {
            $posicoes = diar_buscarPosicoesApi($apiUrl, $placa, $data);
        }
    }

    if (empty($posicoes)) {
        return $out;
    }

    // Buscar base de referencia (POI base ou diaria_base).
    $latBase = 0; $lonBase = 0; $raioBase = 1000;
    $poiBase = diar_buscarPoiBase();
    if ($poiBase !== null) {
        $latBase = floatval($poiBase['poi_tx_latitude']);
        $lonBase = floatval($poiBase['poi_tx_longitude']);
        $raioBase = intval($poiBase['poi_nb_raio']);
    } else {
        $bases = diar_buscarBases(intval($empresaId));
        if (!empty($bases)) {
            $base = $bases[0];
            $latBase = floatval(diar_val($base, 'diba_tx_latitude', 0));
            $lonBase = floatval(diar_val($base, 'diba_tx_longitude', 0));
            $raioBase = intval(diar_val($base, 'diba_nb_raio', 1000));
        }
    }
    if ($latBase == 0 && $lonBase == 0) {
        return $out;
    }

    // Calcular pico (km ida) e pernoite a partir das posicoes.
    $picoMetros = 0.0;
    $ultimaLat = 0; $ultimaLon = 0;
    $hPrev = null;
    $kmHodometro = null;
    foreach ($posicoes as $p) {
        $lat = floatval(diar_val($p, 'lat', 0));
        $lon = floatval(diar_val($p, 'lon', 0));
        if ($lat == 0 && $lon == 0) { continue; }
        $dist = diar_distanciaMeters($latBase, $lonBase, $lat, $lon);
        if ($dist > $picoMetros) { $picoMetros = $dist; }
        $ultimaLat = $lat; $ultimaLon = $lon;
        // Deltas de hodometro para km. O hodometro da API vem em metros (ex.: 132558925 = 132558 km).
        $h = floatval(diar_val($p, 'hodometro', 0));
        if ($h > 100000) { $h = $h / 1000; }
        if ($h > 0 && $hPrev !== null && $h >= $hPrev) {
            $d = $h - $hPrev;
            if ($d <= 5000) {
                $kmHodometro = ($kmHodometro === null ? 0 : $kmHodometro) + $d;
            }
        }
        $hPrev = $h;
    }

    // Se nao acumulou km por hodometro, usar pico de distancia.
    $out['pico_km'] = ($kmHodometro !== null)
        ? round($kmHodometro, 2)
        : round($picoMetros / 1000, 2);

    // Pernoite: ultima posicao alem do limiar (raio + distancia configurada).
    $distanciaPernoite = intval(diar_val($parametros, 'distancia_pernoite_metros', 1000));
    $limiarPernoite = $raioBase + $distanciaPernoite;
    $distUltima = diar_distanciaMeters($latBase, $lonBase, $ultimaLat, $ultimaLon);
    $out['pernoite'] = ($distUltima > $limiarPernoite) ? 'sim' : 'nao';

    return $out;
}

// Busca posicoes da placa no dia via API externa de logistica (POST /data1).
function diar_buscarPosicoesApi($apiUrl, $placa, $data) {
    $placa = trim(strtoupper(strval($placa)));
    $data = diar_dataParaSql($data);
    if ($placa === '' || $data === '' || $apiUrl === '') {
        return array();
    }

    $payload = json_encode(array(
        'plate' => $placa,
        'date_start' => $data.' 00:00:00',
        'date_end' => $data.' 23:59:59',
        'speed' => 99
    ));

    $resposta = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($apiUrl.'/data1');
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => array('Content-Type: application/json')
        ));
        $resposta = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 20
            )
        ));
        $resposta = @file_get_contents($apiUrl.'/data1', false, $ctx);
    }

    if ($resposta === false || $resposta === '') {
        return array();
    }
    $dados = json_decode($resposta, true);
    if (!is_array($dados)) {
        return array();
    }
    // Se a API retornou {ok:false,...}, nao ha dados utilizaveis.
    if (isset($dados['ok']) && $dados['ok'] === false) {
        return array();
    }

    $posicoes = array();
    foreach ($dados as $row) {
        if (!is_array($row)) { continue; }
        $lat = floatval(diar_val($row, 'latitude', 0));
        $lon = floatval(diar_val($row, 'longitude', 0));
        if ($lat == 0 && $lon == 0) { continue; }
        $posicoes[] = array(
            'lat' => $lat,
            'lon' => $lon,
            'hodometro' => floatval(diar_val($row, 'hodometro', 0))
        );
    }
    return $posicoes;
}
function diar_distanciaBatidasDia($entidadeId, $data) {
    $entidadeId = intval($entidadeId);
    $data = diar_dataParaSql($data);
    if ($entidadeId <= 0 || $data === '') {
        return null;
    }
    $rMat = diar_fetch_assoc_safe(diar_query(
        "SELECT enti_tx_matricula FROM entidade WHERE enti_nb_id = ? LIMIT 1",
        "i", array($entidadeId)
    ));
    $matricula = strval(diar_val($rMat, 'enti_tx_matricula', ''));
    if ($matricula === '') {
        return null;
    }
    $res = diar_query(
        "SELECT pont_tx_latitude, pont_tx_longitude
         FROM ponto
         WHERE pont_tx_status = 'ativo'
           AND pont_tx_matricula = ?
           AND pont_tx_latitude IS NOT NULL AND pont_tx_latitude <> '' AND pont_tx_latitude <> '0'
           AND pont_tx_longitude IS NOT NULL AND pont_tx_longitude <> ''
           AND pont_tx_data BETWEEN ? AND ?
         ORDER BY pont_tx_data ASC",
        "sss",
        array($matricula, $data.' 00:00:00', $data.' 23:59:59')
    );
    $primeiro = null;
    $ultimo = null;
    if ($res instanceof mysqli_result) {
        while ($row = mysqli_fetch_assoc($res)) {
            if ($primeiro === null) { $primeiro = $row; }
            $ultimo = $row;
        }
    }
    if ($primeiro === null || $ultimo === null || $primeiro === $ultimo) {
        return null;
    }
    return diar_distanciaMeters(
        floatval($primeiro['pont_tx_latitude']), floatval($primeiro['pont_tx_longitude']),
        floatval($ultimo['pont_tx_latitude']), floatval($ultimo['pont_tx_longitude'])
    );
}

// KM por hodometro via GPS: busca posicoes da placa no dia e calcula
// fim_hodometro - inicio_hodometro (proximo ao horario da jornada).

function diar_alertasLeiMotorista($entidadeId, $data, $jornada) {
    $alertas = array();
    $inicio = strval(diar_val($jornada, 'inicio', ''));
    $fim = strval(diar_val($jornada, 'fim', ''));
    if ($inicio !== '' && $fim !== '') {
        $tsIni = strtotime($inicio);
        $tsFim = strtotime($fim);
        if ($tsFim >= $tsIni) {
            $horas = ($tsFim - $tsIni) / 3600;
            if ($horas > 8) {
                $alertas[] = 'Jornada acima de 8h ('.number_format($horas, 1, ',', '.').'h)';
            }
            if ($horas > 12) {
                $alertas[] = 'Jornada acima de 12h';
            }
        }
    }
    // Intersticio: fim de jornada do dia anterior ate o inicio de hoje.
    $entidadeId = intval($entidadeId);
    $data = diar_dataParaSql($data);
    if ($entidadeId > 0 && $data !== '' && $inicio !== '') {
        $diaAnterior = date('Y-m-d', strtotime($data.' -1 day'));
        $res = diar_query(
            "SELECT p.pont_tx_data FROM ponto p
             WHERE p.pont_tx_status = 'ativo'
               AND p.pont_tx_matricula = (SELECT enti_tx_matricula FROM entidade WHERE enti_nb_id = ? LIMIT 1)
               AND p.pont_tx_tipo = 2
               AND p.pont_tx_data BETWEEN ? AND ?
             ORDER BY p.pont_tx_data DESC LIMIT 1",
            "iss",
            array($entidadeId, $diaAnterior.' 00:00:00', $diaAnterior.' 23:59:59')
        );
        $fimAnterior = diar_fetch_assoc_safe($res);
        if (!empty($fimAnterior['pont_tx_data'])) {
            $intersticio = (strtotime($inicio) - strtotime($fimAnterior['pont_tx_data'])) / 3600;
            if ($intersticio > 0 && $intersticio < 11) {
                $alertas[] = 'Intersticio menor que 11h ('.number_format($intersticio, 1, ',', '.').'h)';
            }
        }
    }
    return $alertas;
}

// Motor de regras do dia. Retorna a decisao calculada para um funcionario em uma data.
function diar_calcularDia($entidadeId, $data) {
    $entidadeId = intval($entidadeId);
    $data = diar_dataParaSql($data);
    $out = array(
        'ativo' => false,
        'placa' => '',
        'km' => null,
        'pernoite' => null,
        'tipo' => 'sem_direito',
        'valor' => 0.0,
        'jornada_inicio' => '',
        'jornada_fim' => '',
        'alertas' => array(),
        'motivo' => '',
        'gerar' => false
    );
    if ($entidadeId <= 0 || $data === '') {
        $out['motivo'] = 'Parametros invalidos.';
        return $out;
    }

    $entidade = diar_buscarPorEntidade($entidadeId);
    $empresaId = intval(diar_val($entidade, 'enti_nb_empresa', 0));
    $parametros = diar_buscarParametros();

    $jornada = diar_jornadaDoDia($entidadeId, $data);
    $out['ativo'] = $jornada['ativo'];
    $out['jornada_inicio'] = $jornada['inicio'];
    $out['jornada_fim'] = $jornada['fim'];
    $out['alertas'] = diar_alertasLeiMotorista($entidadeId, $data, $jornada);

    if (!$jornada['ativo']) {
        $out['motivo'] = 'Sem jornada registrada no dia.';
        return $out;
    }

    $out['placa'] = diar_placaDoDia($entidadeId, $data);

    // Detectar pernoite e pico (km de ida) via batidas do ponto + POI base.
    $resPernoite = diar_pernoiteViaPonto($entidadeId, $data, $empresaId, $parametros);
    $out['pernoite'] = diar_val($resPernoite, 'pernoite', null);
    $out['km'] = diar_val($resPernoite, 'pico_km', null);

    // Regra Lei do Motorista / CCT RN:
    //   1) Fim da jornada FORA da base → pernoite = cheia R$107 (qualquer hora/km).
    //   2) Retornou a base + km ida > 80 → sem pernoite R$55.
    //   3) Retornou a base + km ida <= 80 → almoco R$40.
    //   4) Sem jornada/dados → sem direito.
    $valorPernoite = diar_parseValorMonetario(diar_val($parametros, 'valor_pernoite', '107.00'));
    $valorSemPernoite = diar_parseValorMonetario(diar_val($parametros, 'valor_sem_pernoite', '55.00'));
    $valorAlmoco = diar_parseValorMonetario(diar_val($parametros, 'valor_almoco', '40.00'));
    $limiteKm = diar_parseValorMonetario(diar_val($parametros, 'limite_km_almoco', '80'));

    if ($out['pernoite'] === 'sim') {
        $out['tipo'] = 'cheia';
        $out['valor'] = $valorPernoite;
        $out['motivo'] = 'Fim da jornada fora da base (pernoite).';
        $out['gerar'] = true;
    } elseif ($out['km'] !== null && $out['km'] > $limiteKm) {
        $out['tipo'] = 'sem_pernoite';
        $out['valor'] = $valorSemPernoite;
        $out['motivo'] = 'Retornou a base, km ida > '.$limiteKm.' km.';
        $out['gerar'] = true;
    } elseif ($out['km'] !== null && $out['km'] > 0) {
        $out['tipo'] = 'almoco';
        $out['valor'] = $valorAlmoco;
        $out['motivo'] = 'Retornou a base, km ida <= '.$limiteKm.' km.';
        $out['gerar'] = true;
    } else {
        $out['tipo'] = 'sem_direito';
        $out['valor'] = 0.0;
        $out['gerar'] = false;
        // Montar motivo detalhado.
        $motivos = array();
        if ($out['pernoite'] === null && $out['km'] === null) {
            $motivos[] = 'Sem dados de GPS (posicoes/coordenadas) para calcular pernoite e km.';
        } elseif ($out['pernoite'] === null) {
            $motivos[] = 'Sem dados de GPS para detectar pernoite.';
        } elseif ($out['km'] === null) {
            $motivos[] = 'Sem dados de GPS para calcular km.';
        }
        $out['motivo'] = implode(' ', $motivos);
    }

    return $out;
}

// Verifica se ja existe consumo (auto) para o funcionario na data.
function diar_consumoExiste($entidadeId, $data) {
    $r = diar_fetch_assoc_safe(diar_query(
        "SELECT dcon_nb_id FROM diaria_consumo
         WHERE dcon_nb_entidade = ? AND dcon_tx_data = ? LIMIT 1",
        "is",
        array(intval($entidadeId), diar_dataParaSql($data))
    ));
    return !empty($r['dcon_nb_id']);
}

// Gera os consumos automaticos pendentes de um funcionario ate a data informada (padrao: ontem).
// $diasRetroativos indica quantos dias anteriores a $dataFim serao analisados.
function diar_gerarConsumosPendentes($entidadeId, $dataFim = '', $diasRetroativos = 0) {
    $entidadeId = intval($entidadeId);
    if ($entidadeId <= 0) {
        return array('gerados' => 0, 'pulados' => 0, 'motivos' => array());
    }
    $parametros = diar_buscarParametros();
    if (strtolower(trim(strval(diar_val($parametros, 'autogerar_consumo', 'sim')))) !== 'sim') {
        return array('gerados' => 0, 'pulados' => 0, 'motivos' => array());
    }
    // Se nao especificado, usar o limite configurado nos parametros (0 = desativado).
    if ($diasRetroativos <= 0) {
        $diasRetroativos = intval(diar_val($parametros, 'limite_dias_autogeracao', '0'));
    }
    if ($diasRetroativos <= 0) {
        return array('gerados' => 0, 'pulados' => 0, 'motivos' => array());
    }
    $idUser = intval(diar_sessao('user_nb_id', 0));
    $dataFim = ($dataFim === '') ? date('Y-m-d') : diar_dataParaSql($dataFim);
    $diasRetroativos = max(1, intval($diasRetroativos));
    $dataIni = date('Y-m-d', strtotime($dataFim.' - '.$diasRetroativos.' days'));

    $gerados = 0;
    $pulados = 0;
    $motivos = array();
    for ($d = strtotime($dataIni); $d <= strtotime($dataFim) - 86400; $d += 86400) {
        $data = date('Y-m-d', $d);
        if (diar_consumoExiste($entidadeId, $data)) {
            continue;
        }
        $decisao = diar_calcularDia($entidadeId, $data);
        if (empty($decisao['gerar']) || $decisao['valor'] <= 0) {
            $pulados++;
            $mot = strval(diar_val($decisao, 'motivo', 'Sem dados'));
            $motivos[] = "{$data}: {$mot}";
            continue;
        }
        $detalhes = json_encode(array(
            'alertas' => $decisao['alertas'],
            'km' => $decisao['km'],
            'pernoite' => $decisao['pernoite']
        ), JSON_UNESCAPED_UNICODE);
        diar_query(
            "INSERT INTO diaria_consumo
                (dcon_nb_entidade, dcon_tx_placa, dcon_tx_data, dcon_tx_tipo, dcon_tx_origem, dcon_tx_valor, dcon_tx_km, dcon_tx_pernoite, dcon_tx_jornada_inicio, dcon_tx_jornada_fim, dcon_tx_observacao, dcon_tx_detalhes, dcon_nb_user)
             VALUES (?, ?, ?, ?, 'auto', ?, ?, ?, ?, ?, ?, ?, ?)",
            "isssdsssssii",
            array(
                $entidadeId,
                $decisao['placa'],
                $data,
                $decisao['tipo'],
                $decisao['valor'],
                $decisao['km'],
                $decisao['pernoite'],
                $decisao['jornada_inicio'],
                $decisao['jornada_fim'],
                'Gerado automaticamente',
                $detalhes,
                $idUser
            )
        );
        $gerados++;
    }
    if ($gerados > 0) {
        diar_log_runtime("Consumos automaticos gerados para entidade {$entidadeId}: {$gerados}");
    }
    return array('gerados' => $gerados, 'pulados' => $pulados, 'motivos' => $motivos);
}

// Limpa registros invalidos: apenas registros FUTUROS (depois de hoje) — dados claramente errados.
// Registros antigos NAO sao deletados — o limite so controla de quantos dias para tras preenche lacunas.
function diar_limparConsumosInvalidos($entidadeId = 0) {
    $hoje = date('Y-m-d');

    $whereEntidade = '';
    $vars = array();
    if (intval($entidadeId) > 0) {
        $whereEntidade = ' AND dcon_nb_entidade = ?';
        $vars[] = intval($entidadeId);
    }

    $varsFuturo = array_merge(array($hoje), $vars);
    diar_query(
        "DELETE FROM diaria_consumo
         WHERE dcon_tx_data > ?
           {$whereEntidade}",
        ($vars ? 'si' : 's'),
        $varsFuturo
    );

    diar_log_runtime("Limpeza de registros futuros (>{}) removidos, entidade={$entidadeId}", $hoje);
    return $hoje;
}

// Resumo semanal (segunda a domingo) de um funcionario em relacao a uma data de referencia.
function diar_resumoSemana($entidadeId, $dataRef = '') {
    $entidadeId = intval($entidadeId);
    $dataRef = ($dataRef === '') ? date('Y-m-d') : diar_dataParaSql($dataRef);
    $diaSemana = intval(date('N', strtotime($dataRef))); // 1 = segunda, 7 = domingo
    $dataIni = date('Y-m-d', strtotime($dataRef.' -'.($diaSemana - 1).' days'));
    $dataFim = date('Y-m-d', strtotime($dataIni.' +6 days'));

    $out = array(
        'data_inicio' => $dataIni,
        'data_fim' => $dataFim,
        'depositado' => 0.0,
        'consumido' => 0.0,
        'saldo' => 0.0,
        'dias_consumidos' => 0,
        'complemento_sugerido' => 0.0
    );
    if ($entidadeId <= 0) {
        return $out;
    }

    $dep = diar_fetch_assoc_safe(diar_query(
        "SELECT IFNULL(SUM(depr_tx_valor_total),0) AS total FROM diaria_deposito
         WHERE depr_nb_entidade = ? AND depr_tx_data BETWEEN ? AND ?",
        "iss",
        array($entidadeId, $dataIni, $dataFim)
    ));
    $con = diar_fetch_assoc_safe(diar_query(
        "SELECT IFNULL(SUM(dcon_tx_valor),0) AS total, COUNT(*) AS dias FROM diaria_consumo
         WHERE dcon_nb_entidade = ? AND dcon_tx_data BETWEEN ? AND ?",
        "iss",
        array($entidadeId, $dataIni, $dataFim)
    ));

    $out['depositado'] = round(floatval(diar_val($dep, 'total', 0)), 2);
    $out['consumido'] = round(floatval(diar_val($con, 'total', 0)), 2);
    $out['dias_consumidos'] = intval(diar_val($con, 'dias', 0));
    $out['saldo'] = round($out['depositado'] - $out['consumido'], 2);
    // Complementa apenas o que falta para cobrir o consumo da semana.
    $out['complemento_sugerido'] = round(max(0, $out['consumido'] - $out['depositado']), 2);

    return $out;
}

// Lista funcionarios candidatos a diarias (Motorista/Ajudante/Terceirizado) de uma empresa (0 = todas).
function diar_listarMotoristas($empresaId = 0) {
    $filtro = '';
    $vars = array();
    if (intval($empresaId) > 0) {
        $filtro = " AND e.enti_nb_empresa = ?";
        $vars = array(intval($empresaId));
    }
    $res = diar_query(
        "SELECT e.enti_nb_id, e.enti_tx_nome, e.enti_tx_matricula, e.enti_nb_empresa, e.enti_tx_ocupacao,
                e.enti_setor_id, e.enti_subSetor_id,
                emp.empr_tx_nome AS empresa_nome,
                (SELECT u.user_tx_nome FROM user u WHERE u.user_nb_entidade = e.enti_nb_id ORDER BY u.user_nb_id LIMIT 1) AS user_nome
         FROM entidade e
         LEFT JOIN empresa emp ON emp.empr_nb_id = e.enti_nb_empresa
         WHERE e.enti_tx_status = 'ativo'
           AND e.enti_tx_ocupacao IN ('Motorista','Ajudante','Motorista Terceirizado','Terceirizado','Tercerizado')
           {$filtro}
         ORDER BY user_nome ASC",
        ($vars ? "i" : ""),
        $vars
    );
    return ($res instanceof mysqli_result) ? mysqli_fetch_all($res, MYSQLI_ASSOC) : array();
}

// Resumo mensal em lote (depositado, consumido, saldo, dias, ultima diaria e alertas) por motorista.
function diar_resumoMotoristas($empresaId = 0, $periodo = '') {
    if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) {
        $periodo = date('Y-m');
    }
    $filtro = '';
    $vars = array();
    if (intval($empresaId) > 0) {
        $filtro = " AND e.enti_nb_empresa = ?";
        $vars = array(intval($empresaId));
    }
    $sql = "SELECT
                e.enti_nb_id,
                COALESCE(dep.depositado, 0) AS depositado,
                COALESCE(dep.dias_depositados, 0) AS dias_depositados,
                COALESCE(con.consumido, 0) AS consumido,
                COALESCE(con.dias_consumidos, 0) AS dias_consumidos,
                con.ultima_data,
                COALESCE(con.total_auto, 0) AS total_auto,
                COALESCE(con.tem_alerta, 0) AS tem_alerta
            FROM entidade e
            LEFT JOIN (
                SELECT depr_nb_entidade AS eid, SUM(depr_tx_valor_total) AS depositado, SUM(depr_nb_dias) AS dias_depositados
                FROM diaria_deposito WHERE depr_tx_data LIKE '".$periodo."-%'
                GROUP BY depr_nb_entidade
            ) dep ON dep.eid = e.enti_nb_id
            LEFT JOIN (
                SELECT dcon_nb_entidade AS eid,
                       SUM(dcon_tx_valor) AS consumido,
                       COUNT(*) AS dias_consumidos,
                       MAX(dcon_tx_data) AS ultima_data,
                       SUM(CASE WHEN dcon_tx_origem = 'auto' THEN 1 ELSE 0 END) AS total_auto,
                       MAX(CASE WHEN dcon_tx_detalhes LIKE '%\"alertas\":%' THEN 1 ELSE 0 END) AS tem_alerta
                FROM diaria_consumo WHERE dcon_tx_data LIKE '".$periodo."-%'
                GROUP BY dcon_nb_entidade
            ) con ON con.eid = e.enti_nb_id
            WHERE e.enti_tx_status = 'ativo'
              AND e.enti_tx_ocupacao IN ('Motorista','Ajudante','Motorista Terceirizado','Terceirizado','Tercerizado')
              {$filtro}
            ORDER BY COALESCE(con.ultima_data, '1900-01-01') DESC, e.enti_tx_nome ASC";
    $res = diar_query($sql, ($vars ? "i" : ""), $vars);
    return ($res instanceof mysqli_result) ? mysqli_fetch_all($res, MYSQLI_ASSOC) : array();
}

// Processa automaticamente os dias pendentes de TODOS os motoristas (janela pequena por padrao).
// Retorna a quantidade total de consumos gerados.
function diar_gerarConsumosPendentesTodos($dataFim = '', $diasRetroativos = 0) {
    $parametros = diar_buscarParametros();

    // Sempre limpar registros futuros (dados claramente errados).
    diar_limparConsumosInvalidos(0);

    if (strtolower(trim(strval(diar_val($parametros, 'autogerar_consumo', 'sim')))) !== 'sim') {
        return array('gerados' => 0, 'pulados' => 0, 'alertas' => array());
    }
    $res = diar_query(
        "SELECT enti_nb_id, enti_tx_nome, enti_tx_matricula FROM entidade
         WHERE enti_tx_status = 'ativo'
           AND enti_tx_ocupacao IN ('Motorista','Ajudante','Motorista Terceirizado','Terceirizado','Tercerizado')"
    );
    $totalGerados = 0;
    $totalPulados = 0;
    $alertas = array();
    if ($res instanceof mysqli_result) {
        while ($row = mysqli_fetch_assoc($res)) {
            $eid = intval($row['enti_nb_id']);
            $resultado = diar_gerarConsumosPendentes($eid, $dataFim, $diasRetroativos);
            $totalGerados += $resultado['gerados'];
            $totalPulados += $resultado['pulados'];
            if ($resultado['pulados'] > 0 && !empty($resultado['motivos'])) {
                $nome = strval(diar_val($row, 'enti_tx_nome', ''));
                $matr = strval(diar_val($row, 'enti_tx_matricula', ''));
                $label = ($nome !== '') ? $nome.($matr !== '' ? ' ('.$matr.')' : '') : 'ID '.$eid;
                // Pegar o motivo unico mais comum (sempre sera "sem dados GPS").
                $motivoUnico = strval(reset($resultado['motivos']));
                $motivoLimpo = preg_replace('/^\d{4}-\d{2}-\d{2}: /', '', $motivoUnico);
                $alertas[] = $label.': '.$resultado['pulados'].' dia(s) — '.$motivoLimpo;
            }
        }
    }
    if ($totalGerados > 0) {
        diar_log_runtime("Processamento automatico na abertura: {$totalGerados} consumo(s) gerados");
    }
    return array('gerados' => $totalGerados, 'pulados' => $totalPulados, 'alertas' => $alertas);
}
