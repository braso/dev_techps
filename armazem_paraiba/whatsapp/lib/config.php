<?php
/**
 * Configuração do provedor WhatsApp.
 * Tokens e segredos são criptografados no banco e nunca voltam para o HTML.
 */

if (!function_exists('wa_config_linha')) {
    function wa_config_linha($conn): ?array {
        $rs = query("SELECT * FROM wa_config ORDER BY cfg_nb_id ASC LIMIT 1");
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        return $row ?: null;
    }
}

if (!function_exists('wa_config')) {
    function wa_config(bool $recarregar = false): array {
        static $cache = null;
        if ($cache !== null && !$recarregar) {
            return $cache;
        }

        $row = wa_config_linha($GLOBALS['conn'] ?? null);
        if (!$row) {
            $cache = [
                'cfg_nb_id' => 0,
                'cfg_tx_provider' => 'evolution',
                'cfg_tx_status' => 'inativo',
                'cfg_tx_base_url' => '',
                'cfg_tx_instance' => '',
                'cfg_tx_token' => '',
                'cfg_tx_phone_number_id' => '',
                'cfg_tx_waba_id' => '',
                'cfg_tx_verify_token' => '',
                'cfg_tx_app_secret' => '',
                'cfg_tx_webhook_url' => wa_webhook_url(),
                'cfg_tx_test_number' => '',
                'cfg_nb_retencao_dias' => 0,
                'cfg_tx_call_status' => 'inativo',
            ];
            return $cache;
        }

        $row['cfg_tx_token'] = wa_decrypt(strval($row['cfg_tx_token'] ?? ''));
        $row['cfg_tx_app_secret'] = wa_decrypt(strval($row['cfg_tx_app_secret'] ?? ''));
        $row['cfg_nb_retencao_dias'] = max(0, (int)($row['cfg_nb_retencao_dias'] ?? 0));
        if (trim(strval($row['cfg_tx_webhook_url'] ?? '')) === '') {
            $row['cfg_tx_webhook_url'] = wa_webhook_url();
        }
        $cache = $row;
        return $cache;
    }
}

if (!function_exists('wa_config_ativa')) {
    function wa_config_ativa(): bool {
        $cfg = wa_config();
        return strval($cfg['cfg_tx_status'] ?? '') === 'ativo';
    }
}

if (!function_exists('wa_calling_ativa')) {
    function wa_calling_ativa(): bool {
        $cfg = wa_config();
        return strval($cfg['cfg_tx_provider'] ?? '') === 'cloud'
            && strval($cfg['cfg_tx_status'] ?? '') === 'ativo'
            && strval($cfg['cfg_tx_call_status'] ?? 'inativo') === 'ativo';
    }
}

if (!function_exists('wa_config_salvar')) {
    function wa_config_salvar(array $dados, int $usuarioId = 0): array {
        global $conn;

        $cfgAtual = wa_config(true);
        $provider = strval($dados['provider'] ?? 'evolution');
        if (!in_array($provider, ['cloud', 'evolution'], true)) {
            return ['success' => false, 'message' => 'Provedor inválido.'];
        }

        $status = strval($dados['status'] ?? 'inativo');
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            $status = 'inativo';
        }

        $baseUrlValidar = array_key_exists('base_url', $dados)
            ? trim(strval($dados['base_url']))
            : trim(strval($cfgAtual['cfg_tx_base_url'] ?? ''));
        if ($baseUrlValidar !== '' && !wa_url_http_valida($baseUrlValidar, $provider === 'cloud')) {
            return [
                'success' => false,
                'message' => $provider === 'cloud'
                    ? 'A URL da Cloud API deve ser HTTPS e válida.'
                    : 'A URL da Evolution deve ser HTTP/HTTPS e válida.',
            ];
        }

        $token = trim(strval($dados['token'] ?? ''));
        if ($token === '') {
            $token = strval($cfgAtual['cfg_tx_token'] ?? '');
        }

        $appSecret = trim(strval($dados['app_secret'] ?? ''));
        if ($appSecret === '') {
            $appSecret = strval($cfgAtual['cfg_tx_app_secret'] ?? '');
        }

        $testNumber = array_key_exists('test_number', $dados)
            ? trim(strval($dados['test_number']))
            : trim(strval($cfgAtual['cfg_tx_test_number'] ?? ''));
        $retencao = max(0, intval($dados['retencao_dias'] ?? 0));
        $callStatus = strval($dados['call_status'] ?? ($cfgAtual['cfg_tx_call_status'] ?? 'inativo')) === 'ativo' ? 'ativo' : 'inativo';
        $webhook = wa_webhook_url();

        $tokenEnc = $token !== '' ? wa_encrypt($token) : '';
        $appSecretEnc = $appSecret !== '' ? wa_encrypt($appSecret) : '';

        $sql = "UPDATE wa_config SET
                    cfg_tx_provider = ?,
                    cfg_tx_status = ?,
                    cfg_tx_base_url = ?,
                    cfg_tx_instance = ?,
                    cfg_tx_token = ?,
                    cfg_tx_phone_number_id = ?,
                    cfg_tx_waba_id = ?,
                    cfg_tx_verify_token = ?,
                    cfg_tx_app_secret = ?,
                    cfg_tx_webhook_url = ?,
                    cfg_tx_test_number = ?,
                    cfg_nb_retencao_dias = ?,
                    cfg_tx_call_status = ?,
                    cfg_nb_userAtualiza = ?,
                    cfg_tx_dataAtualiza = NOW()
                WHERE cfg_nb_id = ?";

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['success' => false, 'message' => 'Não foi possível salvar a configuração.'];
        }

        $id = (int)($cfgAtual['cfg_nb_id'] ?? 0);
        $baseUrl = array_key_exists('base_url', $dados)
            ? trim(strval($dados['base_url']))
            : trim(strval($cfgAtual['cfg_tx_base_url'] ?? ''));
        $instance = array_key_exists('instance', $dados)
            ? trim(strval($dados['instance']))
            : trim(strval($cfgAtual['cfg_tx_instance'] ?? ''));
        $phoneNumberId = array_key_exists('phone_number_id', $dados)
            ? trim(strval($dados['phone_number_id']))
            : trim(strval($cfgAtual['cfg_tx_phone_number_id'] ?? ''));
        $wabaId = array_key_exists('waba_id', $dados)
            ? trim(strval($dados['waba_id']))
            : trim(strval($cfgAtual['cfg_tx_waba_id'] ?? ''));
        $verifyToken = array_key_exists('verify_token', $dados)
            ? trim(strval($dados['verify_token']))
            : trim(strval($cfgAtual['cfg_tx_verify_token'] ?? ''));

        mysqli_stmt_bind_param(
            $stmt,
            'sssssssssssisii',
            $provider,
            $status,
            $baseUrl,
            $instance,
            $tokenEnc,
            $phoneNumberId,
            $wabaId,
            $verifyToken,
            $appSecretEnc,
            $webhook,
            $testNumber,
            $retencao,
            $callStatus,
            $usuarioId,
            $id
        );
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return ['success' => false, 'message' => 'Não foi possível salvar a configuração.'];
        }

        wa_config(true);
        return ['success' => true, 'message' => 'Configuração salva.'];
    }
}

if (!function_exists('wa_config_provider')) {
    function wa_config_provider(): array {
        $cfg = wa_config(true);
        $provider = ProviderFactory::make($cfg);
        return [$provider, $cfg];
    }
}
