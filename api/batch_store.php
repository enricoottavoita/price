<?php
// File: api/batch_store.php

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
if (!$data || !isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Dati mancanti o non validi']);
    exit;
}

// Limita il numero di elementi che possono essere inviati contemporaneamente
if (count($data['items']) > 50) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Troppi elementi. Massimo 50 elementi per richiesta.']);
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
    
    // Prepara lo statement per l'inserimento dei prezzi
    $insert_stmt = $conn->prepare("INSERT INTO prices (asin, country, price, source, created_at) VALUES (:asin, :country, :price, :source, NOW())");
    
    // Prepara la risposta
    $response = [
        'success' => true,
        'message' => 'Prezzi elaborati',
        'data' => [
            'total' => count($data['items']),
            'successful' => 0,
            'failed' => 0,
            'items' => []
        ]
    ];
    
    // Processa ogni elemento
    foreach ($data['items'] as $item) {
        if (!isset($item['asin']) || !isset($item['country']) || !isset($item['price'])) {
            $response['data']['failed']++;
            $response['data']['items'][] = [
                'asin' => $item['asin'] ?? 'unknown',
                'country' => $item['country'] ?? 'unknown',
                'status' => 'error',
                'message' => 'Dati mancanti'
            ];
            continue;
        }
        
        // Pulisci gli input
        $asin = clean_input($item['asin']);
        $country = strtolower(clean_input($item['country']));
        $price = (float)$item['price'];
        $source = isset($item['source']) ? clean_input($item['source']) : 'api';
        
        // Validazione parametri
        if (empty($asin) || !preg_match('/^[A-Z0-9]{10}$/', $asin)) {
            $response['data']['failed']++;
            $response['data']['items'][] = [
                'asin' => $asin,
                'country' => $country,
                'status' => 'error',
                'message' => 'ASIN non valido'
            ];
            continue;
        }
        
        $allowed_countries = ['it', 'fr', 'de', 'es'];
        if (!in_array($country, $allowed_countries)) {
            $response['data']['failed']++;
            $response['data']['items'][] = [
                'asin' => $asin,
                'country' => $country,
                'status' => 'error',
                'message' => 'Paese non supportato'
            ];
            continue;
        }
        
        if ($price <= 0) {
            $response['data']['failed']++;
            $response['data']['items'][] = [
                'asin' => $asin,
                'country' => $country,
                'status' => 'error',
                'message' => 'Prezzo non valido'
            ];
            continue;
        }
        
        // Inserisci il prezzo
        try {
            $insert_stmt->bindParam(':asin', $asin);
            $insert_stmt->bindParam(':country', $country);
            $insert_stmt->bindParam(':price', $price);
            $insert_stmt->bindParam(':source', $source);
            $insert_stmt->execute();
            
            $response['data']['successful']++;
            $response['data']['items'][] = [
                'asin' => $asin,
                'country' => $country,
                'price' => $price,
                'status' => 'success',
                'id' => $conn->lastInsertId()
            ];
        } catch (PDOException $e) {
            $response['data']['failed']++;
            $response['data']['items'][] = [
                'asin' => $asin,
                'country' => $country,
                'status' => 'error',
                'message' => 'Errore di database'
            ];
            error_log("Errore nell'inserimento del prezzo: " . $e->getMessage());
        }
    }
    
    // Registra l'accesso
    $stmt = $conn->prepare("INSERT INTO api_logs (user_id, endpoint, request_data, response_code, ip_address) VALUES (:user_id, 'batch_store', :request_data, 200, :ip_address)");
    $stmt->bindParam(':user_id', $tokenData['sub']);
    $request_data = json_encode(['items_count' => count($data['items'])]);
    $stmt->bindParam(':request_data', $request_data);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt->bindParam(':ip_address', $ip_address);
    $stmt->execute();
    
    // Invia la risposta
    http_response_code(200); // OK
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log dell'errore
    error_log("Errore in batch_store.php: " . $e->getMessage());
    
    // Invia risposta di errore
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Errore del server']);
}
?>