<?php
// File: /var/www/price-api/api/verify_token.php

// Impostazioni di debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestione richiesta OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo richieste POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Solo richieste POST sono supportate'
    ]);
    exit;
}

// Ottieni il token dall'header Authorization
$headers = getallheaders();
$auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';
$token = '';

if (preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
    $token = $matches[1];
}

// Leggi i dati inviati
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Verifica dati
if (!$data || !isset($data['client_id']) || empty($token)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Dati mancanti o non validi',
        'valid' => false
    ]);
    exit;
}

// Connessione al database (sostituisci con i tuoi parametri)
$db_host = 'localhost';
$db_name = 'price_radar';
$db_user = 'price_radar_user';
$db_pass = 'your_password';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verifica il token nel database
    $stmt = $pdo->prepare("SELECT * FROM tokens WHERE token = ? AND client_id = ? AND expires_at > NOW()");
    $stmt->execute([$token, $data['client_id']]);
    
    $isValid = $stmt->rowCount() > 0;
    
    echo json_encode([
        'status' => 'success',
        'valid' => $isValid,
        'message' => $isValid ? 'Token valido' : 'Token non valido o scaduto'
    ]);
} catch (PDOException $e) {
    // In caso di errore del database, consideriamo il token non valido
    echo json_encode([
        'status' => 'error',
        'message' => 'Errore di verifica del token',
        'valid' => false
    ]);
}
?>