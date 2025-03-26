<?php
// Imposta header CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestisci richieste OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    exit;
}

// Solo richieste POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Ottieni i dati JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Verifica API key
$apiKey = isset($data['apiKey']) ? $data['apiKey'] : '';
$validApiKey = 'AqPxVF2JsT7yK8wC'; // Dovrebbe corrispondere alla chiave nel tuo script

if ($apiKey === $validApiKey) {
    // Genera token (semplice per ora)
    $token = bin2hex(random_bytes(16));
    $expires = time() + 3600; // 1 ora
    
    // Risposta di successo
    echo json_encode([
        'status' => 'success',
        'token' => $token,
        'expires' => $expires
    ]);
} else {
    // Errore API key non valida
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid API key'
    ]);
}