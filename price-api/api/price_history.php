<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/utilities.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/validator.php';
require_once dirname(__DIR__) . '/includes/rate_limiter.php';

// Inizializzazione
enableCORS();

// Solo richieste GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error_log("Method not allowed in price_history.php: " . $_SERVER['REQUEST_METHOD']);
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Verifica autenticazione
if (!Auth::isAuthenticated()) {
    error_log("Unauthorized access attempt in price_history.php");
    jsonResponse(['error' => 'Unauthorized'], 401);
}

// Ottieni client ID dal token
$token = Auth::getTokenFromHeader();
$clientId = Auth::getClientIdFromToken($token);

// Verifica rate limiting
$rateLimiter = new RateLimiter($clientId);
if ($rateLimiter->isLimited()) {
    error_log("Rate limit exceeded for client: " . $clientId);
    jsonResponse(['error' => 'Rate limit exceeded'], 429);
}

// Registra questa richiesta per rate limiting
$rateLimiter->logRequest();

// Ottieni parametri
$asin = isset($_GET['asin']) ? $_GET['asin'] : null;
$country = isset($_GET['country']) ? $_GET['country'] : null;
$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;

// Validazione
$validator = new Validator();
if (!$validator->validateAsin($asin)) {
    error_log("Invalid ASIN format: " . $asin);
    jsonResponse(['error' => 'Invalid ASIN format'], 400);
}

if (!$validator->validateCountry($country)) {
    error_log("Invalid country code: " . $country);
    jsonResponse(['error' => 'Invalid country code'], 400);
}

// Limita il numero di giorni
if ($days > 90) $days = 90;
if ($days < 1) $days = 1;

try {
    $conn = Database::getConnection();
    setCacheHeaders();
    
    // Query per ottenere la cronologia prezzi
    $sql = "SELECT price, source, timestamp 
            FROM price_history 
            WHERE asin = ? AND country = ? 
            AND timestamp > DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY timestamp";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssi', $asin, $country, $days);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    
    while ($row = $result->fetch_assoc()) {
        $history[] = [
            'price' => (float)$row['price'],
            'source' => $row['source'],
            'timestamp' => $row['timestamp']
        ];
    }
    
    // Log di successo
    error_log("Price history retrieved successfully for " . $asin . " in " . $country . ", " . count($history) . " records");
    
    // Restituisci risultati
    jsonResponse($history);
    
} catch (Exception $e) {
    error_log("Error in price_history.php: " . $e->getMessage());
    jsonResponse(['error' => 'Database error'], 500);
} finally {
    Database::closeConnection();
}
?>