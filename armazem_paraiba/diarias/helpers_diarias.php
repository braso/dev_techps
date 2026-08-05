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

    diar_semearParametros();
}

// Valores padrao da Clausula Decima Quarta (diarias para viagens).
function diar_parametrosPadrao() {
    return array(
        'valor_pernoite' => array('107.00', 'Diaria com pernoite (R$) - intermunicipais e/ou interestaduais'),
        'valor_sem_pernoite' => array('55.00', 'Diaria sem pernoite (R$)'),
        'valor_almoco' => array('40.00', 'Diaria de almoco (R$) - percursos ate 80 km que retornam a base'),
        'limite_km_almoco' => array('80', 'Limite de km (ida) para direito a diaria de almoco'),
        'valor_diaria_cheia' => array('107.00', 'Valor padrao da diaria cheia usado no lancamento diario do gestor')
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
        'cheia' => 'Diaria cheia',
        'outra' => 'Outro valor'
    );
    return isset($labels[$tipo]) ? $labels[$tipo] : ucfirst((string)$tipo);
}

// Identifica super admin usando flag dedicada e nivel textual da sessao.
function diar_isSuperAdmin() {
    if (intval(diar_val($_SESSION, 'user_nb_superadmin', 0)) === 1) {
        return true;
    }
    $nivel = trim(strval(diar_val($_SESSION, 'user_tx_nivel', '')));
    return (bool)preg_match('/super\s+administrador/i', $nivel);
}
