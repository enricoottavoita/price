<?php
// File: includes/security/api_security.php

/**
 * Imposta gli header standard per le risposte API
 */
function set_api_headers() {
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
}

/**
 * Verifica se la richiesta proviene da uno userscript
 * 
 * @return bool True se la richiesta è valida, false altrimenti
 */
function is_userscript_request() {
    // Per ora accettiamo tutte le richieste per debug
    return true;
    
    /*
    // Verifica l'header X-Requested-With
    $headers = getallheaders();
    if (isset($headers['X-Requested-With']) && $headers['X-Requested-With'] === 'userscript') {
        return true;
    }
    
    // Verifica il referer (potrebbe provenire da Amazon)
    if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'amazon.') !== false) {
        return true;
    }
    
    return false;
    */
}

/**
 * Gestisce le richieste preflight CORS
 */
function handle_preflight() {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}
?>