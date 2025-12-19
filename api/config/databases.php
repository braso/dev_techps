<?php

$databases = [];
$total = (int) Env::get('DB_TOTAL', 0);

for ($i = 1; $i <= $total; $i++) {
    $key = Env::get("DB_{$i}_KEY");

    if (!$key) {
        continue;
    }

    $databases[$key] = [
        'dsn'  => Env::get("DB_{$i}_DSN"),
        'user' => Env::get("DB_{$i}_USER"),
        'pass' => Env::get("DB_{$i}_PASS"),
        'service_url' => Env::get("DB_{$i}_SERVICE_URL"),
    ];
}

return $databases;
