<?php
// File: api/price.php

// Includi file necessari
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/jwt_helper.php';

// Imposta headers per la risposta
set_api_headers();

// Verifica il metodo della richiesta
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

// Parametri richiesti
if (!isset($_GET['asin']) || !isset($_GET['country'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Parametri mancanti']);
    exit;
}

// Pulisci gli input
$asin = clean_input($_GET['asin']);
$country = strtolower(clean_input($_GET['country']));

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
    
    // Cerca il prezzo nel database
    $stmt = $conn->prepare("SELECT * FROM prices WHERE asin = :asin AND country = :country ORDER BY created_at DESC LIMIT 1");
    $stmt->bindParam(':asin', $asin);
    $stmt->bindParam(':country', $country);
    $stmt->execute();
    
    // Prepara la risposta
    if ($stmt->rowCount() > 0) {
        $price = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $response = [
            'success' => true,
            'data' => [
                'asin' => $price['asin'],
                'country' => $price['country'],
                'price' => (float)$price['price'],
                'currency' => 'EUR',
                'timestamp' => strtotime($price['created_at']),
                'source' => $price['source'] ?? 'database'
            ]
        ];
    } else {
        // Qui potresti implementare lo scraping in tempo reale se necessario
        // Per ora rispondiamo con un errore
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Prezzo non trovato']);
        exit;
    }
    
    // Registra l'accesso
    $stmt = $conn->prepare("INSERT INTO api_logs (user_id, endpoint, request_data, response_code, ip_address) VALUES (:user_id, 'price', :request_data, 200, :ip_address)");
    $stmt->bindParam(':user_id', $tokenData['sub']);
    $request_data = json_encode(['asin' => $asin, 'country' => $country]);
    $stmt->bindParam(':request_data', $request_data);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt->bindParam(':ip_address', $ip_address);
    $stmt->execute();
    
    // Invia la risposta
    http_response_code(200); // OK
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log dell'errore
    error_log("Errore in price.php: " . $e->getMessage());
    
    // Invia risposta di errore
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Errore del server']);
}
?>