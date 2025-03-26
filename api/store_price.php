<?php
// File: api/store_price.php

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

// Ottieni i dati inviati
$data = json_decode(file_get_contents('php://input'), true);

// Verifica che i dati siano stati ricevuti correttamente
if (!$data || !isset($data['asin']) || !isset($data['country']) || !isset($data['price'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Dati mancanti o non validi']);
    exit;
}

// Pulisci gli input
$asin = clean_input($data['asin']);
$country = strtolower(clean_input($data['country']));
$price = (float)$data['price'];
$source = isset($data['source']) ? clean_input($data['source']) : 'api';

// Validazione parametri
if (empty($asin) || !preg_match('/^[A-Z0-9]{10}$/', $asin)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'ASIN non valido']);
    exit;
}

$allowed_countries = ['it', 'fr', 'de', 'es'];
if (!in_array($country, $allowed_countries)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Paese non supportato']);
    exit;
}

if ($price <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Prezzo non valido']);
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
    
    // Verifica se il token è stato revocato
    $stmt = $conn->prepare("SELECT id FROM tokens WHERE token = :token AND revoked = 0 AND (expires_at > NOW() OR expires_at IS NULL)");
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Token revocato o scaduto']);
        exit;
    }
    
    // Inserisci il nuovo prezzo
    $stmt = $conn->prepare("INSERT INTO prices (asin, country, price, source, created_at) VALUES (:asin, :country, :price, :source, NOW())");
    $stmt->bindParam(':asin', $asin);
    $stmt->bindParam(':country', $country);
    $stmt->bindParam(':price', $price);
    $stmt->bindParam(':source', $source);
    $stmt->execute();
    
    $price_id = $conn->lastInsertId();
    
    // Registra l'accesso
    $stmt = $conn->prepare("INSERT INTO api_logs (user_id, endpoint, request_data, response_code, ip_address) VALUES (:user_id, 'store_price', :request_data, 201, :ip_address)");
    $stmt->bindParam(':user_id', $tokenData['sub']);
    $request_data = json_encode($data);
    $stmt->bindParam(':request_data', $request_data);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt->bindParam(':ip_address', $ip_address);
    $stmt->execute();
    
    // Invia la risposta
    http_response_code(201); // Created
    echo json_encode([
        'success' => true,
        'message' => 'Prezzo aggiunto con successo',
        'data' => [
            'id' => $price_id,
            'asin' => $asin,
            'country' => $country,
            'price' => $price,
            'source' => $source,
            'timestamp' => time()
        ]
    ]);
    
} catch (Exception $e) {
    // Log dell'errore
    error_log("Errore in store_price.php: " . $e->getMessage());
    
    // Invia risposta di errore
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Errore del server']);
}
?>