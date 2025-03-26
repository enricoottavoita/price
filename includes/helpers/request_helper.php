<?php
// File: includes/helpers/request_helper.php

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
 * Pulisce un input per prevenire XSS e SQL injection
 * 
 * @param string $data I dati da pulire
 * @return string I dati puliti
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Verifica se un parametro è presente e non vuoto
 * 
 * @param array $data Array di dati (ad es. $_GET, $_POST)
 * @param string $key Chiave da verificare
 * @return bool True se il parametro è presente e non vuoto
 */
function has_parameter($data, $key) {
    return isset($data[$key]) && !empty($data[$key]);
}

/**
 * Ottiene un parametro da un array, con un valore predefinito se non esiste
 * 
 * @param array $data Array di dati (ad es. $_GET, $_POST)
 * @param string $key Chiave da ottenere
 * @param mixed $default Valore predefinito
 * @return mixed Il valore del parametro o il valore predefinito
 */
function get_parameter($data, $key, $default = null) {
    if (has_parameter($data, $key)) {
        return sanitize_input($data[$key]);
    }
    return $default;
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
 * Invia una risposta JSON e termina lo script
 * 
 * @param array $data I dati da inviare
 * @param int $status_code Il codice di stato HTTP
 */
function send_json_response($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

/**
 * Invia una risposta di errore JSON e termina lo script
 * 
 * @param string $message Il messaggio di errore
 * @param int $status_code Il codice di stato HTTP
 */
function send_error_response($message, $status_code = 400) {
    send_json_response([
        'status' => 'error',
        'message' => $message
    ], $status_code);
}

/**
 * Invia una risposta di successo JSON e termina lo script
 * 
 * @param array $data I dati da inviare
 */
function send_success_response($data = []) {
    $response = array_merge(['status' => 'success'], $data);
    send_json_response($response);
}

/**
 * Ottiene i dati JSON inviati nella richiesta
 * 
 * @return array I dati JSON decodificati
 */
function get_json_data() {
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        send_error_response('Invalid JSON data: ' . json_last_error_msg());
    }
    
    return $data;
}
?>