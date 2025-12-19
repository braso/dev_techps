<?php

class DeviceService
{
    private array $databases;

    public function __construct(array $databases)
    {
        $this->databases = $databases;
    }

    public function sync(
        string $empresa,
        string $deviceId,
        ?string $matricula,
        ?string $marcaModelo,
        ?string $versaoAndroid
    ): array {

        Logger::info('Iniciando sync', [
            'empresa' => $empresa,
            'device_id' => $deviceId
        ]);

        if (!isset($this->databases[$empresa])) {
            throw new Exception('Empresa inválida');
        }

        $db = $this->databases[$empresa];

        $pdo = Database::connect(
            $db['dsn'],
            $db['user'],
            $db['pass']
        );

        // 🔍 Verifica se já existe
        $stmt = $pdo->prepare("
            SELECT celu_nb_id
            FROM celular
            WHERE celu_tx_device_id = :device_id
            LIMIT 1
        ");
        $stmt->execute(['device_id' => $deviceId]);

        if ($stmt->fetch()) {
            return [
                'status' => 'exists'
            ];
        }

        // 🔍 Buscar a entidade por matricula
        $entidade = $pdo->prepare("
            SELECT enti_nb_id, enti_tx_nome
            FROM entidade
            WHERE enti_tx_matricula = :matricula
            LIMIT 1
        ");
        $entidade->execute(['matricula' => $matricula]);

        $entidade = $entidade->fetch(PDO::FETCH_ASSOC);

        // ➕ Insere com dados extras
        $insert = $pdo->prepare("
            INSERT INTO celular (
                celu_tx_device_id,
                celu_tx_nome,
                celu_tx_marcaModelo,
                celu_tx_sistemaOperacional,
                celu_tx_imei,
                celu_tx_numero,
                celu_tx_operadora,
                celu_tx_cimie,
                celu_nb_entidade
            ) VALUES (
                :device_id,
                :nome_dispositivo,
                :marca_modelo,
                :versao_android,
                '',
                '',
                '',
                '',
                :entidade_id
            )
        ");

        $insert->execute([
            'device_id'        => $deviceId,
            'nome_dispositivo' => $entidade['enti_tx_nome'], // 🔗 vem da entidade
            'marca_modelo'     => $marcaModelo,
            'versao_android'   => $versaoAndroid,
            'entidade_id'      => $entidade['enti_nb_id']    // 🔗 vem da entidade
        ]);

        Logger::info('Novo device criado', [
            'device_id'        => $deviceId,
            'nome_dispositivo' => $entidade['enti_tx_nome'], // 🔗 vem da entidade
            'marca_modelo'     => $marcaModelo,
            'versao_android'   => $versaoAndroid,
            'entidade_id'      => $entidade['enti_nb_id']    // 🔗 vem da entidade
        ]);

        return [
            'status' => 'created'
        ];
    }
}
