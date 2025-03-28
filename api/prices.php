<?php
// File: api/prices.php

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

// Limita il numero di elementi che possono essere richiesti contemporaneamente
if (count($data['items']) > 20) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Troppi elementi richiesti. Massimo 20 elementi per richiesta.']);
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
    
    // Prepara la risposta
    $response = [
        'success' => true,
        'data' => []
    ];
    
    // Processa ogni elemento richiesto
    foreach ($data['items'] as $item) {
        if (!isset($item['asin']) || !isset($item['country'])) {
            continue; // Salta elementi non validi
        }
        
        $asin = clean_input($item['asin']);
        $country = strtolower(clean_input($item['country']));
        
        // Validazione parametri
        if (empty($asin) || !preg_match('/^[A-Z0-9]{10}$/', $asin)) {
            continue; // Salta ASIN non validi
        }
        
		$allowed_countries = ['it', 'fr', 'de', 'es'];
        if (!in_array($country, $allowed_countries)) {
            continue; // Salta paesi non supportati
        }
        
        // Cerca il prezzo nel database
        $stmt = $conn->prepare("SELECT * FROM prices WHERE asin = :asin AND country = :country ORDER BY created_at DESC LIMIT 1");
        $stmt->bindParam(':asin', $asin);
        $stmt->bindParam(':country', $country);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $price = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $response['data'][] = [
                'asin' => $price['asin'],
                'country' => $price['country'],
                'price' => (float)$price['price'],
                'currency' => 'EUR',
                'timestamp' => strtotime($price['created_at']),
                'source' => $price['source'] ?? 'database'
            ];
        } else {
            // Aggiungi comunque l'elemento alla risposta, ma con price = null
            $response['data'][] = [
                'asin' => $asin,
                'country' => $country,
                'price' => null,
                'currency' => 'EUR',
                'timestamp' => null,
                'source' => null,
                'error' => 'Prezzo non trovato'
            ];
        }
    }
    
    // Registra l'accesso
    $stmt = $conn->prepare("INSERT INTO api_logs (user_id, endpoint, request_data, response_code, ip_address) VALUES (:user_id, 'prices', :request_data, 200, :ip_address)");
    $stmt->bindParam(':user_id', $tokenData['sub']);
    $request_data = json_encode($data);
    $stmt->bindParam(':request_data', $request_data);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt->bindParam(':ip_address', $ip_address);
    $stmt->execute();
    
    // Invia la risposta
    http_response_code(200); // OK
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log dell'errore
    error_log("Errore in prices.php: " . $e->getMessage());
    
    // Invia risposta di errore
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Errore del server']);
}
?>