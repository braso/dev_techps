<?php

require_once __DIR__ . '/ProviderInterface.php';
require_once __DIR__ . '/HttpClient.php';
require_once __DIR__ . '/CloudApiProvider.php';
require_once __DIR__ . '/EvolutionProvider.php';

class ProviderFactory {
    public static function make(array $config): ProviderInterface {
        $provider = strtolower(trim(strval($config['cfg_tx_provider'] ?? 'evolution')));
        if ($provider === 'cloud') {
            return new CloudApiProvider($config);
        }
        return new EvolutionProvider($config);
    }
}
