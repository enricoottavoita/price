<?php
// File: api/price_history.php

// Includi file necessari
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/security/api_security.php';
require_once '../includes/helpers/request_helper.php';

// Imposta gli header
set_api_headers();

// Log della richiesta
error_log("PRICE_HISTORY: Richiesta ricevuta");
error_log("PRICE_HISTORY: Parametri GET: " . json_encode($_GET));

// Verifica se la richiesta proviene da uno userscript
if (!is_userscript_request()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Accesso non autorizzato']);
    exit;
}

// Verifica che ci siano ASIN e paese
if (!isset($_GET['asin']) || empty($_GET['asin']) || !isset($_GET['country']) || empty($_GET['country'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ASIN o paese mancante']);
    exit;
}

// Pulisci e valida i dati
$asin = sanitize_input($_GET['asin']);
$country = sanitize_input($_GET['country']);

// Verifica che il paese sia valido
if (!in_array($country, ['it', 'de', 'fr', 'es', 'uk'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Paese non valido']);
    exit;
}

// Ottieni il numero di giorni per la cronologia (default: 30)
$days = isset($_GET['days']) ? (int) $_GET['days'] : 30;
if ($days <= 0 || $days > 365) {
    $days = 30;
}

try {
    // Connessione al database
    $db = new Database();
    $conn = $db->getConnection();
    
    // Query per ottenere la cronologia dei prezzi
    $sql = "SELECT p.*, s.name as source_name 
            FROM prices p
            LEFT JOIN sources s ON p.source_id = s.id
            WHERE p.asin = :asin AND p.country = :country
            AND p.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ORDER BY p.created_at ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':asin', $asin);
    $stmt->bindParam(':country', $country);
    $stmt->bindParam(':days', $days);
    $stmt->execute();
    
    $history = [];
    while ($price = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $history[] = [
            'price' => (float) $price['price'],
            'source' => $price['source_name'] ?? 'community',
            'date' => $price['created_at']
        ];
    }
    
    // Log del risultato
    error_log("PRICE_HISTORY: Trovati " . count($history) . " record per ASIN {$asin}, Paese {$country}");
    
    // Invia la risposta
    echo json_encode([
        'status' => 'success',
        'asin' => $asin,
        'country' => $country,
        'history' => $history
    ]);
    
} catch (Exception $e) {
    error_log("PRICE_HISTORY: Errore - " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Errore del server']);
}
?>