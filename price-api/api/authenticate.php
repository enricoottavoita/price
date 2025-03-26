<?php
// File: api/authenticate.php

// Includi file necessari
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/helpers/jwt_helper.php';

// Imposta headers per la risposta
set_api_headers();

// Verifica il metodo della richiesta
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Metodo non consentito']);
    exit;
}

// Ottieni i dati inviati
$data = json_decode(file_get_contents('php://input'), true);

// Verifica che i dati siano stati ricevuti correttamente
if (!$data || !isset($data['client_id']) || !isset($data['auth_key'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Dati mancanti o non validi']);
    exit;
}

// Pulisci gli input
$client_id = clean_input($data['client_id']);
$auth_key = clean_input($data['auth_key']);

// Verifica auth_key
if ($auth_key !== AUTH_KEY) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Chiave di autenticazione non valida']);
    exit;
}

try {
    // Connessione al database
    $db = new Database();
    $conn = $db->getConnection();
    
    // Verifica se l'utente esiste e è attivo
    $stmt = $conn->prepare("SELECT id, client_id, status FROM users WHERE client_id = :client_id");
    $stmt->bindParam(':client_id', $client_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Client ID non valido']);
        exit;
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verifica se l'utente è attivo
    if ($user['status'] !== 1) {
        http_response_code(403); // Forbidden
        echo json_encode(['error' => 'Account disattivato']);
        exit;
    }
    
    // Genera un nuovo token
    $token = JWTHelper::generateToken($user['id'], $user['client_id']);
    
    // Salva il token nel database
    $stmt = $conn->prepare("INSERT INTO tokens (user_id, token, expires_at) VALUES (:user_id, :token, DATE_ADD(NOW(), INTERVAL :expiry SECOND))");
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->bindParam(':token', $token);
    $expiry = JWT_EXPIRY;
    $stmt->bindParam(':expiry', $expiry);
    $stmt->execute();
    
    // Aggiorna ultimo accesso utente
    $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
    $stmt->bindParam(':id', $user['id']);
    $stmt->execute();
    
    // Prepara la risposta
    $response = [
        'success' => true,
        'message' => 'Autenticazione riuscita',
        'token' => $token,
        'expires_in' => JWT_EXPIRY
    ];
    
    // Invia la risposta
    http_response_code(200); // OK
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log dell'errore
    error_log("Errore in authenticate.php: " . $e->getMessage());
    
    // Invia risposta di errore
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Errore del server']);
}
?>