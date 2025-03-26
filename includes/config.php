<?php
// File: includes/config.php

// Configurazione Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'price_api');
define('DB_USER', 'root');
define('DB_PASS', '');

// Configurazione API
define('JWT_SECRET', ''); // Questa verrà sostituita con il valore dal database in futuro
define('JWT_EXPIRY', 86400); // 24 ore in secondi
define('AUTH_KEY', ''); // Chiave di autenticazione per le API

// Impostazioni Timezone
date_default_timezone_set('Europe/Rome');

// Gestione errori
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disattivato in produzione
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/errors.log');

// Directory di log
$log_dir = __DIR__ . '/../logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Funzioni helper
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Headers CORS per le API
function set_api_headers() {
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    
    // Gestione richieste OPTIONS (preflight)
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        header('HTTP/1.1 200 OK');
        exit;
    }
}
?>
