<?php
// File: api/store_price.php

// Includi file necessari
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/security/api_security.php';
require_once '../includes/helpers/request_helper.php';
require_once '../includes/helpers/jwt_helper.php';

// Imposta gli header
set_api_headers();

// Log della richiesta
error_log("STORE_PRICE: Richiesta ricevuta");
error_log("STORE_PRICE: Dati POST: " . file_get_contents('php://input'));

// Verifica il metodo della richiesta
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metodo non consentito']);
    exit;
}

// Verifica se la richiesta proviene da uno userscript
if (!is_userscript_request()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Accesso non autorizzato']);
    exit;
}

// Ottieni e verifica il token
$token = get_bearer_token();
if (!$token) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Token mancante']);
    exit;
}

try {
    // Valida il token
    $payload = JWTHelper::validateToken($token);
    $user_id = $payload->user_id;
    
    // Ottieni i dati dal body della richiesta
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Verifica che i dati necessari siano presenti
    if (!isset($data['asin']) || !isset($data['country']) || !isset($data['price'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Dati mancanti']);
        exit;
    }
    
    // Pulisci e valida i dati
    $asin = sanitize_input($data['asin']);
    $country = sanitize_input($data['country']);
    $price = (float) $data['price'];
    $source = isset($data['source']) ? sanitize_input($data['source']) : 'userscript';
    
    // Verifica che il prezzo sia valido
    if ($price <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Prezzo non valido']);
        exit;
    }
    
    // Verifica che il paese sia valido
    if (!in_array($country, ['it', 'de', 'fr', 'es', 'uk'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Paese non valido']);
        exit;
    }
    
    // Connessione al database
    $db = new Database();
    $conn = $db->getConnection();
    
    // Ottieni o crea l'ID della fonte
    $stmt = $conn->prepare("SELECT id FROM sources WHERE name = :name");
    $stmt->bindParam(':name', $source);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $source_row = $stmt->fetch(PDO::FETCH_ASSOC);
        $source_id = $source_row['id'];
    } else {
        $stmt = $conn->prepare("INSERT INTO sources (name, created_at) VALUES (:name, NOW())");
        $stmt->bindParam(':name', $source);
        $stmt->execute();
        $source_id = $conn->lastInsertId();
    }
    
    // Inserisci il prezzo nel database
    $stmt = $conn->prepare("INSERT INTO prices (asin, country, price, user_id, source_id, created_at, updated_at) 
                           VALUES (:asin, :country, :price, :user_id, :source_id, NOW(), NOW())");
    $stmt->bindParam(':asin', $asin);
    $stmt->bindParam(':country', $country);
    $stmt->bindParam(':price', $price);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':source_id', $source_id);
    $stmt->execute();
    
    // Log del successo
    error_log("STORE_PRICE: Prezzo salvato - ASIN: {$asin}, Paese: {$country}, Prezzo: {$price}");
    
    // Invia risposta di successo
    echo json_encode([
        'status' => 'success',
        'message' => 'Prezzo salvato con successo'
    ]);
    
} catch (Exception $e) {
    error_log("STORE_PRICE: Errore - " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Errore del server']);
}
?>