<?php

function tt_query($sql, $types = '', $vars = array()) {
    try {
        return query($sql, $types, $vars);
    } catch (Exception $e) {
        return false;
    }
}

function tt_fetch_assoc_safe($res) {
    if (!($res instanceof mysqli_result)) {
        return array();
    }
    $row = mysqli_fetch_assoc($res);
    return is_array($row) ? $row : array();
}

function tt_val($arr, $key, $default = null) {
    return (is_array($arr) && array_key_exists($key, $arr)) ? $arr[$key] : $default;
}

function tt_colunaExiste($tabela, $coluna) {
    $tabela = preg_replace('/[^a-zA-Z0-9_]/', '', $tabela);
    $coluna = preg_replace('/[^a-zA-Z0-9_]/', '', $coluna);
    if ($tabela === '' || $coluna === '') {
        return false;
    }

    $res = tt_query("SHOW COLUMNS FROM {$tabela} LIKE '{$coluna}'");
    return ($res instanceof mysqli_result) && mysqli_num_rows($res) > 0;
}

function tt_ensureSchema() {
    tt_query("CREATE TABLE IF NOT EXISTS solicitacao_troca_horario (
        soli_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        soli_tx_status VARCHAR(20) DEFAULT 'ativo',
        soli_tx_aceite_status ENUM('pendente', 'aceito', 'rejeitado') DEFAULT 'pendente',
        soli_nb_entidade INT NOT NULL,
        soli_nb_entidade_destino INT DEFAULT NULL,
        soli_tx_matricula_solicitante VARCHAR(50),
        soli_tx_nome_solicitante VARCHAR(255),
        soli_tx_setor_solicitante VARCHAR(255),
        soli_tx_subsetor_solicitante VARCHAR(255),
        soli_tx_cpf_solicitante VARCHAR(20),
        soli_tx_data_solicitacao DATE,
        soli_tx_matricula_trabalhara VARCHAR(50),
        soli_tx_nome_trabalhara VARCHAR(255),
        soli_tx_setor_trabalhara VARCHAR(255),
        soli_tx_subsetor_trabalhara VARCHAR(255),
        soli_tx_data_troca DATE,
        soli_tx_turno_troca VARCHAR(100),
        soli_tx_data_pagara DATE,
        soli_tx_turno_pagara VARCHAR(100),
        soli_tx_complemento TEXT,
        soli_nb_user_visto INT DEFAULT NULL,
        soli_tx_data_visto DATETIME DEFAULT NULL,
        soli_tx_data_decisao DATETIME DEFAULT NULL,
        soli_tx_status_gestor ENUM('pendente', 'aprovado', 'rejeitado') DEFAULT 'pendente',
        soli_nb_id_instancia INT DEFAULT NULL,
        soli_tx_dataCadastro DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    if (!tt_colunaExiste('solicitacao_troca_horario', 'soli_nb_entidade_destino')) {
        tt_query("ALTER TABLE solicitacao_troca_horario ADD COLUMN soli_nb_entidade_destino INT DEFAULT NULL AFTER soli_nb_entidade");
    }

    tt_query("CREATE TABLE IF NOT EXISTS solicitacao_troca_horario_aprovadores (
        apro_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        apro_nb_solicitacao INT NOT NULL,
        apro_nb_entidade INT NOT NULL,
        apro_tx_tipo ENUM('setor','cargo') NOT NULL,
        apro_tx_status ENUM('pendente','aceito','rejeitado') NOT NULL DEFAULT 'pendente',
        apro_nb_user_decisao INT DEFAULT NULL,
        apro_tx_data_decisao DATETIME DEFAULT NULL,
        apro_tx_data_visto DATETIME DEFAULT NULL,
        apro_dt_created DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_solicitacao_entidade (apro_nb_solicitacao, apro_nb_entidade),
        KEY idx_entidade_status (apro_nb_entidade, apro_tx_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    tt_query("CREATE TABLE IF NOT EXISTS notificacao_troca_turno (
        noti_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        noti_nb_solicitacao INT NOT NULL,
        noti_nb_entidade_destino INT NOT NULL,
        noti_tx_tipo VARCHAR(30) NOT NULL,
        noti_tx_mensagem TEXT NOT NULL,
        noti_tx_status ENUM('nao_lida','lida') NOT NULL DEFAULT 'nao_lida',
        noti_tx_dataCadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        noti_tx_dataLeitura DATETIME DEFAULT NULL,
        KEY idx_destino_status (noti_nb_entidade_destino, noti_tx_status),
        KEY idx_solicitacao (noti_nb_solicitacao)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
}

function tt_parseIdsCsv($idsCsv) {
    $idsCsv = trim((string)$idsCsv);
    if ($idsCsv === '') {
        return array();
    }

    $ids = preg_split('/\s*,\s*/', $idsCsv);
    if (!is_array($ids)) {
        return array();
    }

    return array_values(array_unique(array_filter(array_map('intval', $ids), function ($v) {
        return $v > 0;
    })));
}

function tt_buscarSetorSubsetor($entidadeId) {
    $row = tt_fetch_assoc_safe(tt_query(
        "SELECT enti_setor_id, enti_subSetor_id FROM entidade WHERE enti_nb_id = ? LIMIT 1",
        "i",
        array(intval($entidadeId))
    ));

    $setor = 'N/A';
    $subsetor = '';
    $setorId = intval(tt_val($row, 'enti_setor_id', 0));
    $subsetorId = intval(tt_val($row, 'enti_subSetor_id', 0));

    if ($setorId > 0) {
        $r = tt_fetch_assoc_safe(tt_query("SELECT grup_tx_nome FROM grupos_documentos WHERE grup_nb_id = ? LIMIT 1", "i", array($setorId)));
        $setor = trim((string)tt_val($r, 'grup_tx_nome', 'N/A'));
    }

    if ($subsetorId > 0) {
        $r = tt_fetch_assoc_safe(tt_query("SELECT sbgr_tx_nome FROM sbgrupos_documentos WHERE sbgr_nb_id = ? LIMIT 1", "i", array($subsetorId)));
        $subsetor = trim((string)tt_val($r, 'sbgr_tx_nome', ''));
    }

    return array($setor, $subsetor, $setorId, $subsetorId);
}

function tt_buscarUsuarioAtual() {
    $entidadeId = intval(tt_val($_SESSION, 'user_nb_entidade', 0));
    if ($entidadeId <= 0) {
        return array();
    }

    $r = tt_fetch_assoc_safe(tt_query(
        "SELECT e.*, u.user_nb_id, u.user_tx_nome, u.user_tx_cpf
         FROM entidade e
         JOIN user u ON u.user_nb_entidade = e.enti_nb_id
         WHERE e.enti_nb_id = ? LIMIT 1",
        "i",
        array($entidadeId)
    ));

    return is_array($r) ? $r : array();
}

function tt_buscarPorMatricula($matricula) {
    $matricula = preg_replace('/[^A-Za-z0-9\-_]/', '', (string)$matricula);
    if ($matricula === '') {
        return array();
    }

    $r = tt_fetch_assoc_safe(tt_query(
        "SELECT e.*, u.user_nb_id, u.user_tx_nome, u.user_tx_cpf
         FROM entidade e
         JOIN user u ON u.user_nb_entidade = e.enti_nb_id
         WHERE e.enti_tx_matricula = ? AND e.enti_tx_status = 'ativo' LIMIT 1",
        "s",
        array($matricula)
    ));

    return is_array($r) ? $r : array();
}

function tt_buscaEntidadesPorIds($ids, $tipo) {
    if (!is_array($ids)) {
        return array();
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($v) {
        return $v > 0;
    })));

    if (empty($ids)) {
        return array();
    }

    $sql = "SELECT enti_nb_id, enti_tx_nome, enti_tx_email FROM entidade WHERE enti_nb_id IN (" . implode(',', $ids) . ")";
    $res = tt_query($sql);
    $out = array();

    if ($res instanceof mysqli_result) {
        while ($r = mysqli_fetch_assoc($res)) {
            $out[] = array(
                'id' => intval(tt_val($r, 'enti_nb_id', 0)),
                'nome' => trim((string)tt_val($r, 'enti_tx_nome', '')),
                'email' => trim((string)tt_val($r, 'enti_tx_email', '')),
                'tipo' => $tipo
            );
        }
    }

    return $out;
}

function tt_buscaResponsaveisSetor($setorId) {
    $setorId = intval($setorId);
    if ($setorId <= 0) {
        return array();
    }

    $res = tt_query(
        "SELECT sr.sres_nb_entidade_id AS id, e.enti_tx_nome AS nome, e.enti_tx_email AS email
         FROM setor_responsavel sr
         LEFT JOIN entidade e ON e.enti_nb_id = sr.sres_nb_entidade_id
         WHERE sr.sres_nb_setor_id = ? AND sr.sres_tx_status = 'ativo'",
        "i",
        array($setorId)
    );

    return ($res instanceof mysqli_result) ? mysqli_fetch_all($res, MYSQLI_ASSOC) : array();
}

function tt_buscaResponsaveisCargo($cargoId) {
    $cargoId = intval($cargoId);
    if ($cargoId <= 0) {
        return array();
    }

    $res = tt_query(
        "SELECT orv.opre_nb_entidade_id AS id, e.enti_tx_nome AS nome, e.enti_tx_email AS email
         FROM operacao_responsavel orv
         LEFT JOIN entidade e ON e.enti_nb_id = orv.opre_nb_entidade_id
         WHERE orv.opre_nb_operacao_id = ? AND orv.opre_tx_status = 'ativo'",
        "i",
        array($cargoId)
    );

    return ($res instanceof mysqli_result) ? mysqli_fetch_all($res, MYSQLI_ASSOC) : array();
}

function tt_consolidarGestores($a, $b) {
    $a = is_array($a) ? $a : array();
    $b = is_array($b) ? $b : array();
    $map = array();

    foreach (array_merge($a, $b) as $r) {
        $id = intval(tt_val($r, 'id', 0));
        if ($id <= 0) {
            continue;
        }

        if (!isset($map[$id])) {
            $tipo = (tt_val($r, 'tipo', 'setor') === 'cargo') ? 'cargo' : 'setor';
            $map[$id] = array(
                'id' => $id,
                'nome' => trim((string)tt_val($r, 'nome', '')),
                'email' => trim((string)tt_val($r, 'email', '')),
                'tipo' => $tipo
            );
        }
    }

    return array_values($map);
}

function tt_obterGestoresPorEntidade($entidadeId) {
    $entidadeId = intval($entidadeId);
    $row = tt_fetch_assoc_safe(tt_query(
        "SELECT enti_setor_id, enti_tx_tipoOperacao,
                enti_respSetor_id, enti_respCargo_id, enti_respSetor_ids, enti_respCargo_ids
         FROM entidade WHERE enti_nb_id = ? LIMIT 1",
        "i",
        array($entidadeId)
    ));

    if (empty($row)) {
        return array();
    }

    $setorIds = tt_parseIdsCsv(tt_val($row, 'enti_respSetor_ids', ''));
    $cargoIds = tt_parseIdsCsv(tt_val($row, 'enti_respCargo_ids', ''));

    if (empty($setorIds) && !empty($row['enti_respSetor_id'])) {
        $setorIds[] = intval($row['enti_respSetor_id']);
    }
    if (empty($cargoIds) && !empty($row['enti_respCargo_id'])) {
        $cargoIds[] = intval($row['enti_respCargo_id']);
    }

    $gestoresDiretos = tt_consolidarGestores(
        tt_buscaEntidadesPorIds($setorIds, 'setor'),
        tt_buscaEntidadesPorIds($cargoIds, 'cargo')
    );

    $setorId = intval(tt_val($row, 'enti_setor_id', 0));
    $cargoId = intval(tt_val($row, 'enti_tx_tipoOperacao', 0));

    $gestoresEstrutura = tt_consolidarGestores(
        tt_buscaResponsaveisSetor($setorId),
        tt_buscaResponsaveisCargo($cargoId)
    );

    return tt_consolidarGestores($gestoresDiretos, $gestoresEstrutura);
}

function tt_criarNotificacao($idSolicitacao, $idEntidadeDestino, $tipo, $mensagem) {
    $idSolicitacao = intval($idSolicitacao);
    $idEntidadeDestino = intval($idEntidadeDestino);
    $tipo = (string)$tipo;
    $mensagem = (string)$mensagem;

    if ($idSolicitacao <= 0 || $idEntidadeDestino <= 0 || trim($mensagem) === '') {
        return;
    }

    tt_query(
        "INSERT INTO notificacao_troca_turno
            (noti_nb_solicitacao, noti_nb_entidade_destino, noti_tx_tipo, noti_tx_mensagem)
         VALUES (?, ?, ?, ?)",
        "iiss",
        array($idSolicitacao, $idEntidadeDestino, $tipo, $mensagem)
    );
}

function tt_buscarNotificacoes($idEntidade, $limite) {
    $idEntidade = intval($idEntidade);
    $limite = intval($limite);

    if ($idEntidade <= 0) {
        return array();
    }

    if ($limite <= 0) {
        $limite = 8;
    }

    $limite = max(1, min(50, $limite));

    $res = tt_query(
        "SELECT n.*, s.soli_tx_status_gestor
         FROM notificacao_troca_turno n
         LEFT JOIN solicitacao_troca_horario s ON s.soli_nb_id = n.noti_nb_solicitacao
         WHERE n.noti_nb_entidade_destino = ?
         ORDER BY n.noti_tx_dataCadastro DESC
         LIMIT {$limite}",
        "i",
        array($idEntidade)
    );

    return ($res instanceof mysqli_result) ? mysqli_fetch_all($res, MYSQLI_ASSOC) : array();
}

function tt_normalizarTextoDocumento($texto) {
    $texto = trim((string)$texto);
    if ($texto === '') {
        return '';
    }

    $texto = html_entity_decode($texto, ENT_QUOTES, 'UTF-8');
    $texto = strtr($texto, array(
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'Á' => 'a', 'À' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'É' => 'e', 'È' => 'e', 'Ê' => 'e', 'Ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'Í' => 'i', 'Ì' => 'i', 'Î' => 'i', 'Ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'Ó' => 'o', 'Ò' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'Ú' => 'u', 'Ù' => 'u', 'Û' => 'u', 'Ü' => 'u',
        'ç' => 'c', 'Ç' => 'c', 'ñ' => 'n', 'Ñ' => 'n'
    ));

    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto);
    return trim($texto);
}

function tt_formatarDataDocumento($valor) {
    $valor = trim((string)$valor);
    if ($valor === '') {
        return '';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
        $ts = strtotime($valor);
        return $ts ? date('d/m/Y', $ts) : $valor;
    }

    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $valor)) {
        return $valor;
    }

    $ts = strtotime($valor);
    return $ts ? date('d/m/Y', $ts) : $valor;
}

function tt_buscarTipoDocumentoTrocaHorario() {
    $res = tt_query(
        "SELECT tipo_nb_id, tipo_tx_nome, tipo_tx_logo, tipo_tx_cabecalho, tipo_tx_rodape
         FROM tipos_documentos
         WHERE tipo_tx_status = 'ativo'
           AND (
                tipo_tx_nome = ?
                OR tipo_tx_nome = ?
                OR tipo_tx_nome LIKE ?
           )
         ORDER BY tipo_nb_id ASC
         LIMIT 1",
        "sss",
        array('Troca de Horário', 'Troca de Horario', 'Troca de Hor%')
    );

    return tt_fetch_assoc_safe($res);
}

function tt_buscarCamposDocumentoTrocaHorario($idTipo) {
    $idTipo = intval($idTipo);
    if ($idTipo <= 0) {
        return array();
    }

    $res = tt_query(
        "SELECT *
         FROM camp_documento_modulo
         WHERE camp_nb_tipo_doc = ? AND camp_tx_status = 'ativo'
         ORDER BY camp_nb_ordem ASC, camp_nb_id ASC",
        "i",
        array($idTipo)
    );

    return ($res instanceof mysqli_result) ? mysqli_fetch_all($res, MYSQLI_ASSOC) : array();
}

function tt_buscarSolicitacaoTrocaHorario($idSolicitacao) {
    $idSolicitacao = intval($idSolicitacao);
    if ($idSolicitacao <= 0) {
        return array();
    }

    $res = tt_query(
        "SELECT s.*, 
                us.user_tx_nome AS solicitante_user_nome,
                ud.user_tx_nome AS destino_user_nome,
                ug.user_tx_nome AS decisor_user_nome
         FROM solicitacao_troca_horario s
         LEFT JOIN user us ON us.user_nb_entidade = s.soli_nb_entidade
         LEFT JOIN user ud ON ud.user_nb_entidade = s.soli_nb_entidade_destino
         LEFT JOIN user ug ON ug.user_nb_id = s.soli_nb_user_visto
         WHERE s.soli_nb_id = ?
         LIMIT 1",
        "i",
        array($idSolicitacao)
    );

    return tt_fetch_assoc_safe($res);
}

function tt_buscarUserIdPorEntidade($entidadeId) {
    $entidadeId = intval($entidadeId);
    if ($entidadeId <= 0) {
        return 0;
    }

    $res = tt_query(
        "SELECT user_nb_id
         FROM user
         WHERE user_nb_entidade = ?
         ORDER BY user_nb_id ASC
         LIMIT 1",
        "i",
        array($entidadeId)
    );
    $r = tt_fetch_assoc_safe($res);
    return intval(tt_val($r, 'user_nb_id', 0));
}

function tt_resolverValorCampoTrocaHorario($campo, $solicitacao, $idUsuarioCriador) {
    $label = tt_normalizarTextoDocumento(tt_val($campo, 'camp_tx_label', ''));
    $tipoCampo = tt_normalizarTextoDocumento(tt_val($campo, 'camp_tx_tipo', ''));

    $nomeSolicitante = trim((string)tt_val($solicitacao, 'soli_tx_nome_solicitante', ''));
    $matriculaSolicitante = trim((string)tt_val($solicitacao, 'soli_tx_matricula_solicitante', ''));
    $setorSolicitante = trim((string)tt_val($solicitacao, 'soli_tx_setor_solicitante', ''));
    $subsetorSolicitante = trim((string)tt_val($solicitacao, 'soli_tx_subsetor_solicitante', ''));
    $cpfSolicitante = trim((string)tt_val($solicitacao, 'soli_tx_cpf_solicitante', ''));
    $nomeDestino = trim((string)tt_val($solicitacao, 'soli_tx_nome_trabalhara', ''));
    $setorDestino = trim((string)tt_val($solicitacao, 'soli_tx_setor_trabalhara', ''));
    $subsetorDestino = trim((string)tt_val($solicitacao, 'soli_tx_subsetor_trabalhara', ''));
    $dataSolicitacao = tt_formatarDataDocumento(tt_val($solicitacao, 'soli_tx_data_solicitacao', ''));
    $dataTroca = tt_formatarDataDocumento(tt_val($solicitacao, 'soli_tx_data_troca', ''));
    $dataPagara = tt_formatarDataDocumento(tt_val($solicitacao, 'soli_tx_data_pagara', ''));
    $turnoTroca = trim((string)tt_val($solicitacao, 'soli_tx_turno_troca', ''));
    $turnoPagara = trim((string)tt_val($solicitacao, 'soli_tx_turno_pagara', ''));
    $complemento = trim((string)tt_val($solicitacao, 'soli_tx_complemento', ''));
    $statusGestor = trim((string)tt_val($solicitacao, 'soli_tx_status_gestor', ''));
    $decisorNome = trim((string)tt_val($solicitacao, 'decisor_user_nome', ''));
    $criadorNome = '';

    if (intval($idUsuarioCriador) > 0) {
        $u = tt_fetch_assoc_safe(tt_query(
            "SELECT user_tx_nome, user_tx_login
             FROM user
             WHERE user_nb_id = ?
             LIMIT 1",
            "i",
            array(intval($idUsuarioCriador))
        ));
        $criadorNome = trim((string)tt_val($u, 'user_tx_nome', ''));
        if ($criadorNome === '') {
            $criadorNome = trim((string)tt_val($u, 'user_tx_login', ''));
        }
    }

    if ($label === '') {
        return '';
    }

    if (strpos($label, 'cpf') !== false) {
        return $cpfSolicitante;
    }

    if (strpos($label, 'emitido') !== false || strpos($label, 'criador') !== false) {
        return ($criadorNome !== '' ? $criadorNome : $nomeSolicitante);
    }

    if (strpos($label, 'aprovador') !== false || strpos($label, 'aprovado por') !== false || strpos($label, 'decisor') !== false) {
        return $decisorNome;
    }

    if (strpos($label, 'trabalh') !== false && strpos($label, 'turno') === false) {
        return $nomeDestino;
    }

    if (strpos($label, 'matricula') !== false || strpos($label, 'matr cula') !== false) {
        if (strpos($label, 'dest') !== false || strpos($label, 'trabalh') !== false) {
            return trim((string)tt_val($solicitacao, 'soli_tx_matricula_trabalhara', ''));
        }
        return $matriculaSolicitante;
    }

    if (strpos($label, 'subsetor') !== false) {
        if (strpos($label, 'dest') !== false || strpos($label, 'trabalh') !== false) {
            return $subsetorDestino;
        }
        return $subsetorSolicitante;
    }

    if (strpos($label, 'setor') !== false) {
        if (strpos($label, 'dest') !== false || strpos($label, 'trabalh') !== false) {
            return $setorDestino;
        }
        return $setorSolicitante;
    }

    if (strpos($label, 'turno') !== false) {
        if (strpos($label, 'pag') !== false) {
            return $turnoPagara;
        }
        return $turnoTroca;
    }

    if (strpos($label, 'data') !== false) {
        if (strpos($label, 'pag') !== false) {
            return $dataPagara;
        }
        if (strpos($label, 'troca') !== false) {
            return $dataTroca;
        }
        if (strpos($label, 'solicit') !== false || strpos($label, 'geracao') !== false || strpos($label, 'cadastro') !== false) {
            return $dataSolicitacao;
        }
        return $dataSolicitacao;
    }

    if (strpos($label, 'complement') !== false || strpos($label, 'observ') !== false || strpos($label, 'justific') !== false) {
        return $complemento;
    }

    if (strpos($label, 'nome') !== false) {
        if (strpos($label, 'dest') !== false || strpos($label, 'trabalh') !== false) {
            return $nomeDestino;
        }
        if (strpos($label, 'decisor') !== false || strpos($label, 'gestor') !== false || strpos($label, 'aprov') !== false) {
            return $decisorNome;
        }
        if (strpos($label, 'criador') !== false || strpos($label, 'emit') !== false) {
            return $criadorNome;
        }
        return $nomeSolicitante;
    }

    if ($tipoCampo === 'usuario') {
        return $nomeSolicitante;
    }

    if ($tipoCampo === 'setor') {
        return $setorSolicitante;
    }

    if ($tipoCampo === 'data') {
        return $dataSolicitacao;
    }

    if ($tipoCampo === 'number') {
        return '';
    }

    if ($tipoCampo === 'selecao') {
        if (strpos($label, 'status') !== false || strpos($label, 'decis') !== false || strpos($label, 'aprov') !== false) {
            return ucfirst($statusGestor);
        }
    }

    return $complemento;
}

function tt_gerarDocumentoTrocaHorario($idSolicitacao, $idUsuarioCriador) {
    $idSolicitacao = intval($idSolicitacao);
    $idUsuarioCriador = intval($idUsuarioCriador);

    if ($idSolicitacao <= 0) {
        return 0;
    }

    $solicitacao = tt_buscarSolicitacaoTrocaHorario($idSolicitacao);
    if (empty($solicitacao)) {
        return 0;
    }

    $idUserSolicitante = tt_buscarUserIdPorEntidade(intval(tt_val($solicitacao, 'soli_nb_entidade', 0)));
    if ($idUserSolicitante > 0) {
        $idUsuarioCriador = $idUserSolicitante;
    }

    $idInstanciaJa = intval(tt_val($solicitacao, 'soli_nb_id_instancia', 0));
    if ($idInstanciaJa > 0) {
        return $idInstanciaJa;
    }

    $tipo = tt_buscarTipoDocumentoTrocaHorario();
    if (empty($tipo)) {
        return 0;
    }

    $idTipo = intval(tt_val($tipo, 'tipo_nb_id', 0));
    if ($idTipo <= 0) {
        return 0;
    }

    $campos = tt_buscarCamposDocumentoTrocaHorario($idTipo);
    if (empty($campos)) {
        return 0;
    }

    $dadosInst = array(
        'inst_nb_tipo_doc' => $idTipo,
        'inst_nb_user' => ($idUsuarioCriador > 0 ? $idUsuarioCriador : intval(tt_val($solicitacao, 'soli_nb_user_visto', 0))),
        'inst_tx_status' => 'ativo'
    );

    $resInst = inserir('inst_documento_modulo', array_keys($dadosInst), array_values($dadosInst));
    $idInstancia = 0;
    if (is_array($resInst) && isset($resInst[0]) && is_numeric($resInst[0])) {
        $idInstancia = intval($resInst[0]);
    }

    if ($idInstancia <= 0) {
        return 0;
    }

    foreach ($campos as $campo) {
        $valor = tt_resolverValorCampoTrocaHorario($campo, $solicitacao, $idUsuarioCriador);

        $dadosValor = array(
            'valo_nb_instancia' => $idInstancia,
            'valo_nb_campo' => intval(tt_val($campo, 'camp_nb_id', 0)),
            'valo_tx_valor' => strval($valor),
            'valo_tx_status' => 'ativo'
        );

        inserir('valo_documento_modulo', array_keys($dadosValor), array_values($dadosValor));
    }

    tt_query(
        "UPDATE solicitacao_troca_horario SET soli_nb_id_instancia = ? WHERE soli_nb_id = ?",
        "ii",
        array($idInstancia, $idSolicitacao)
    );

    return $idInstancia;
}
