<?php
// File: api/get_prices.php

// Includi file necessari
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/security/api_security.php';
require_once '../includes/helpers/request_helper.php';

// Imposta gli header
set_api_headers();

// Log della richiesta
error_log("GET_PRICES: Richiesta ricevuta");
error_log("GET_PRICES: Parametri GET: " . json_encode($_GET));

// Verifica se la richiesta proviene da uno userscript
if (!is_userscript_request()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Accesso non autorizzato']);
    exit;
}

// Verifica che ci sia un ASIN
if (!isset($_GET['asin']) || empty($_GET['asin'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ASIN mancante']);
    exit;
}

// Ottieni e pulisci i parametri
$asin = sanitize_input($_GET['asin']);

// Ottieni i paesi richiesti
$countries = [];
if (isset($_GET['countries']) && !empty($_GET['countries'])) {
    $countries = explode(',', $_GET['countries']);
    $countries = array_map('sanitize_input', $countries);
}

// Se non sono specificati paesi, usa tutti quelli supportati
if (empty($countries)) {
    $countries = ['it', 'de', 'fr', 'es', 'uk'];
}

try {
    // Connessione al database
    $db = new Database();
    $conn = $db->getConnection();
    
    // Prepara la risposta
    $response = [
        'status' => 'success',
        'prices' => []
    ];
    
    // Ottieni i prezzi per ogni paese
    foreach ($countries as $country) {
        // Verifica che il paese sia valido
        if (!in_array($country, ['it', 'de', 'fr', 'es', 'uk'])) {
            continue;
        }
        
        // Query per ottenere l'ultimo prezzo
        $sql = "SELECT p.*, s.name as source_name 
                FROM prices p
                LEFT JOIN sources s ON p.source_id = s.id
                WHERE p.asin = :asin AND p.country = :country
                ORDER BY p.created_at DESC
                LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':asin', $asin);
        $stmt->bindParam(':country', $country);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $price = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Aggiungi il prezzo alla risposta
            $response['prices'][$country] = [
                'price' => (float) $price['price'],
                'source' => $price['source_name'] ?? 'community',
                'created_at' => $price['created_at'],
                'updated_at' => $price['updated_at']
            ];
            
            error_log("GET_PRICES: Trovato prezzo per {$country}: {$price['price']}");
        } else {
            error_log("GET_PRICES: Nessun prezzo trovato per {$country}");
        }
    }
    
    // Invia la risposta
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("GET_PRICES: Errore - " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Errore del server']);
}
?>