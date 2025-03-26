<?php
// File: api/revoke.php

// Includi file necessari
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/jwt_helper.php';

// Imposta headers per la risposta
set_api_headers();

// Verifica il metodo della richiesta
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Metodo non consentito']);
    exit;
}

// Ottieni il token Bearer dall'header Authorization
$token = JWTHelper::getBearerToken();

// Verifica che il token sia presente
if (!$token) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Token di accesso mancante']);
    exit;
}

// Valida il token
$tokenData = JWTHelper::validateToken($token);
if (!$tokenData) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Token di accesso non valido o scaduto']);
    exit;
}

try {
    // Connessione al database
    $db = new Database();
    $conn = $db->getConnection();
    
    // Verifica se l'utente esiste e è attivo
    $stmt = $conn->prepare("SELECT id, status FROM users WHERE id = :id");
    $stmt->bindParam(':id', $tokenData['sub']);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Utente non trovato']);
        exit;
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verifica se l'utente è attivo
    if ($user['status'] !== 1) {
        http_response_code(403); // Forbidden
        echo json_encode(['error' => 'Account disattivato']);
        exit;
    }
    
    // Verifica se il token esiste
    $stmt = $conn->prepare("SELECT id FROM tokens WHERE token = :token");
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Token non trovato']);
        exit;
    }
    
    // Revoca il token
    $stmt = $conn->prepare("UPDATE tokens SET revoked = 1, revoked_at = NOW() WHERE token = :token");
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    
    // Registra l'accesso
    $stmt = $conn->prepare("INSERT INTO api_logs (user_id, endpoint, request_data, response_code, ip_address) VALUES (:user_id, 'revoke', :request_data, 200, :ip_address)");
    $stmt->bindParam(':user_id', $tokenData['sub']);
    $request_data = json_encode(['token' => substr($token, 0, 10) . '...']);
    $stmt->bindParam(':request_data', $request_data);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt->bindParam(':ip_address', $ip_address);
    $stmt->execute();
    
    // Invia la risposta
    http_response_code(200); // OK
    echo json_encode([
        'success' => true,
        'message' => 'Token revocato con successo'
    ]);
    
} catch (Exception $e) {
    // Log dell'errore
    error_log("Errore in revoke.php: " . $e->getMessage());
    
    // Invia risposta di errore
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Errore del server']);
}
?>