<?php
// Encaminha /ws/app/version para o roteador do ws (necessario no servidor embutido do PHP; no Apache o .htaccess ja faz isso)
chdir(__DIR__ . '/..');
require __DIR__ . '/../index.php';
