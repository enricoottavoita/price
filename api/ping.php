<?php
// File: api/ping.php

// Includi file necessari
require_once '../includes/config.php';
require_once '../includes/security/api_security.php';

// Imposta gli header
set_api_headers();

// Prepara la risposta
$response = [
    'status' => 'success',
    'message' => 'Pong!',
    'timestamp' => time(),
    'server_time' => date('Y-m-d H:i:s')
];

// Aggiungi informazioni sulla richiesta se è in modalità debug
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    $response['debug'] = [
        'headers' => getallheaders(),
        'is_userscript' => is_userscript_request(),
        'remote_addr' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A'
    ];
}

// Invia la risposta
echo json_encode($response);
?>