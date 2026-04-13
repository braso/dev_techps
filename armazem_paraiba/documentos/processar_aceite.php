<?php
// Ativa exibição de erros para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once "../conecta.php";
include_once "../check_permission.php";
include_once __DIR__ . "/solicitacao_troca_horario_helpers.php";

/**
 * Remove absolutamente tudo que não for letra ou número para comparação blindada
 */
function limpar_para_comparar($texto) {
    if (empty($texto)) return "";
    
    // Converte para minusculo e remove acentos
    $texto = mb_strtolower($texto, 'UTF-8');
    $mapa = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ü'=>'u','ç'=>'c'];
    $texto = strtr($texto, $mapa);
    
    // Remove qualquer caractere que não seja a-z ou 0-9
    return preg_replace('/[^a-z0-9]/', '', $texto);
}

/**
 * Formata data de Y-m-d para d/m/Y (Fallback para função data() do sistema)
 */
function formatar_data_local($data) {
    if (empty($data) || $data == '0000-00-00') return "";
    if (function_exists('data')) return data($data);
    return date("d/m/Y", strtotime($data));
}

function ensureSolicitacaoTrocaHorarioAprovadoresSchema(): void {
    global $conn;
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS solicitacao_troca_horario_aprovadores (
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
        KEY idx_solicitacao (apro_nb_solicitacao),
        KEY idx_entidade (apro_nb_entidade)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
}

function salvarAprovadoresSolicitacao(int $solicitacaoId, int $solicitanteEntidadeId, string $matriculaTrabalhara): bool {
    global $conn;
    if ($solicitacaoId <= 0 || $solicitanteEntidadeId <= 0) {
        return false;
    }

    ensureSolicitacaoTrocaHorarioAprovadoresSchema();

    $solicitanteResponsaveis = obterResponsaveisParaUsuario($solicitanteEntidadeId);
    $trabalharaResponsaveis = [];
    $trabalharaEntidadeId = buscarEntidadeIdPorMatricula($matriculaTrabalhara);
    if ($trabalharaEntidadeId > 0) {
        $trabalharaResponsaveis = obterResponsaveisParaUsuario($trabalharaEntidadeId);
    }

    $todos = consolidarResponsaveis($solicitanteResponsaveis, $trabalharaResponsaveis);
    if (empty($todos)) {
        error_log("TrocaHorario: nenhum responsável encontrado para solicitacao {$solicitacaoId} (solicitante {$solicitanteEntidadeId}, matricula '{$matriculaTrabalhara}').");
        return false;
    }

    $salvos = 0;
    foreach ($todos as $responsavel) {
        $id = intval($responsavel['id']);
        if ($id <= 0) {
            continue;
        }
        $tipo = ($responsavel['tipo'] === 'cargo') ? 'cargo' : 'setor';
        $sql = "INSERT INTO solicitacao_troca_horario_aprovadores
                (apro_nb_solicitacao, apro_nb_entidade, apro_tx_tipo, apro_tx_status, apro_nb_user_decisao, apro_tx_data_decisao, apro_tx_data_visto)
                VALUES (" . intval($solicitacaoId) . ", " . intval($id) . ", '" . $tipo . "', 'pendente', NULL, NULL, NULL)
                ON DUPLICATE KEY UPDATE
                    apro_tx_tipo = VALUES(apro_tx_tipo),
                    apro_tx_status = 'pendente',
                    apro_nb_user_decisao = NULL,
                    apro_tx_data_decisao = NULL,
                    apro_tx_data_visto = NULL";
        if (query($sql)) {
            $salvos++;
        }
    }

    if ($salvos <= 0) {
        error_log("TrocaHorario: falha ao inserir aprovadores para solicitacao {$solicitacaoId}.");
        return false;
    }

    $check = mysqli_fetch_assoc(query(
        "SELECT COUNT(*) AS total
         FROM solicitacao_troca_horario_aprovadores
         WHERE apro_nb_solicitacao = " . intval($solicitacaoId)
    ));

    return intval($check['total'] ?? 0) > 0;
}

/**
 * Função chamada pelo despachante global quando acao=aceitar
 * Mirroring logic of ajuste_pontofuncionario.php for maximum compatibility
 */
function aceitar() {
    global $conn;
    $id = intval($_POST["id_solicitacao"] ?? 0);
    $userId = $_SESSION["user_nb_id"] ?? 0;

    if ($id <= 0) {
        echo "<script>alert('ID da solicitação inválido.'); window.location.href='solicitar_troca_horario.php';</script>";
        exit;
    }

    $sql = "UPDATE solicitacao_troca_horario 
            SET soli_tx_aceite_status = 'aceito', 
                soli_nb_user_visto = $userId,
                soli_tx_data_visto = NOW(),
                soli_tx_data_decisao = NOW() 
            WHERE soli_nb_id = $id";
    
    if (!query($sql)) {
        die("ERRO ao atualizar status da solicitação: " . mysqli_error($conn));
    }

    // ── GERAÇÃO AUTOMÁTICA DE DOCUMENTO VIA LAYOUT ──────────────────
    try {
        // 1. Busca dados da solicitação
        $dados = mysqli_fetch_assoc(query("SELECT * FROM solicitacao_troca_horario WHERE soli_nb_id = $id LIMIT 1"));
        if (!$dados) return;

        // 2. Busca o Layout de Troca de Horário EXECUTANDO PRIORIDADE AO ID 209 (Confirmado pelo usuário)
        $sql_tipo = "SELECT t.tipo_nb_id, t.tipo_tx_nome, COUNT(c.camp_nb_id) as total_campos
                     FROM tipos_documentos t
                     LEFT JOIN camp_documento_modulo c ON c.camp_nb_tipo_doc = t.tipo_nb_id AND c.camp_tx_status = 'ativo'
                     WHERE (t.tipo_nb_id = 209 OR LOWER(t.tipo_tx_nome) LIKE '%troca%hor%rio%')
                       AND t.tipo_tx_status = 'ativo'
                     GROUP BY t.tipo_nb_id
                     ORDER BY (CASE WHEN t.tipo_nb_id = 209 THEN 1 ELSE 2 END) ASC, total_campos DESC
                     LIMIT 1";
        
        $tipoDoc = mysqli_fetch_assoc(query($sql_tipo));
        
        if (!$tipoDoc) {
             error_log("ERRO: Nenhum layout de 'Troca de Horário' encontrado.");
             return;
        }
        $idTipo = $tipoDoc['tipo_nb_id'];

        // 3. Cria Instância (Utilizando APENAS colunas garantidas para evitar erro de 'Unknown Column')
        $sql_inst = "INSERT INTO inst_documento_modulo (inst_nb_tipo_doc, inst_nb_user, inst_dt_criacao, inst_tx_status) 
                     VALUES ($idTipo, $userId, NOW(), 'ativo')";
        
        if (query($sql_inst)) {
            $idInstancia = mysqli_insert_id($conn);

            // 5. Preencher campos do layout 209 (Foco total nos 10 campos em ordem)
            $campos = mysqli_fetch_all(query("SELECT * FROM camp_documento_modulo WHERE camp_nb_tipo_doc = $idTipo AND camp_tx_status = 'ativo' ORDER BY camp_nb_ordem ASC"), MYSQLI_ASSOC);
            
            $campos_preenchidos = 0;
            $idx = 1; // Contador de posição para mapeamento infalível (1 a 10)
            foreach ($campos as $c) {
                $valor = '';

                // Mapeamento Posicional (Configuração Final para SÃO LUCAS - Layout 209)
                switch($idx) {
                    case 1: $valor = $dados['soli_tx_nome_solicitante']; break;
                    case 2: $valor = $dados['soli_tx_matricula_solicitante']; break;
                    case 3: $valor = $dados['soli_tx_setor_solicitante']; break;
                    case 4: $valor = $dados['soli_tx_cpf_solicitante']; break;
                    case 5: $valor = formatar_data_local($dados['soli_tx_data_troca']); break; // Data da Troca
                    case 6: $valor = $dados['soli_tx_turno_troca']; break;
                    case 7: $valor = formatar_data_local($dados['soli_tx_data_pagara']); break; // Data que Pagará
                    case 8: $valor = $dados['soli_tx_turno_pagara']; break;
                    case 9: $valor = $dados['soli_tx_complemento']; break;
                    case 10: $valor = $dados['soli_tx_nome_trabalhara']; break; // Nome (não matrícula)
                }
                
                if (!empty($valor)) {
                    $dados_valor = [
                        'valo_nb_instancia' => $idInstancia,
                        'valo_nb_campo'     => $c['camp_nb_id'],
                        'valo_tx_valor'     => (string)$valor,
                        'valo_tx_status'    => 'ativo'
                    ];
                    inserir('valo_documento_modulo', array_keys($dados_valor), array_values($dados_valor));
                    $campos_preenchidos++;
                }
                $idx++;
            }

            query("UPDATE solicitacao_troca_horario
                   SET soli_nb_id_instancia = " . intval($idInstancia) . "
                   WHERE soli_nb_id = " . $id);

            $solicitanteEntidadeId = intval($dados['soli_nb_entidade'] ?? 0);
            $matriculaTrabalhara = trim($dados['soli_tx_matricula_trabalhara'] ?? '');
            $responsaveisCriados = salvarAprovadoresSolicitacao($id, $solicitanteEntidadeId, $matriculaTrabalhara);

            if (!$responsaveisCriados) {
                query("UPDATE solicitacao_troca_horario
                       SET soli_tx_status_gestor = 'aprovado',
                           soli_tx_data_decisao = NOW()
                       WHERE soli_nb_id = " . $id);
                echo "<script>alert('Solicitação ACEITA! Documento ID: $idInstancia gerado com $campos_preenchidos campos preenchidos de 10. Nenhum responsável foi encontrado, status final aprovado.'); window.location.href='solicitar_troca_horario.php';</script>";
                exit;
            }

            echo "<script>alert('Solicitação ACEITA! Documento ID: $idInstancia gerado com $campos_preenchidos campos preenchidos de 10. Agora ela seguirá para os responsáveis do setor/cargo.'); window.location.href='solicitar_troca_horario.php';</script>";
            exit;
        } else {
            echo "<script>alert('ERRO: Falha ao inserir instância do documento no banco.'); window.location.href='solicitar_troca_horario.php';</script>";
            exit;
        }
    } catch (Exception $e) {
        error_log("ERRO Geração Documento: " . $e->getMessage());
        echo "<script>alert('ERRO FATAL: " . addslashes($e->getMessage()) . "'); window.location.href='solicitar_troca_horario.php';</script>";
        exit;
    }
}

/**
 * Função chamada pelo despachante global quando acao=rejeitar
 */
function rejeitar() {
    global $conn;
    $id = intval($_POST["id_solicitacao"] ?? 0);
    $userId = $_SESSION["user_nb_id"] ?? 0;

    $sql = "UPDATE solicitacao_troca_horario 
            SET soli_tx_aceite_status = 'rejeitado', 
                soli_nb_user_visto = $userId,
                soli_tx_data_visto = NOW(),
                soli_tx_data_decisao = NOW() 
            WHERE soli_nb_id = $id";
    
    if (query($sql)) {
        echo "<script>alert('Solicitação REJEITADA.'); window.location.href='solicitar_troca_horario.php';</script>";
    } else {
        echo "<script>alert('Erro ao processar solicitação.'); window.location.href='solicitar_troca_horario.php';</script>";
    }
    exit;
}
?>
