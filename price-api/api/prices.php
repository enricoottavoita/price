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
    error_log("Method not allowed in prices.php: " . $_SERVER['REQUEST_METHOD']);
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Verifica autenticazione
if (!Auth::isAuthenticated()) {
    error_log("Unauthorized access attempt in prices.php");
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
$countries = isset($_GET['countries']) ? $_GET['countries'] : null;

// Validazione input
$validator = new Validator();
if (!$validator->validateAsin($asin)) {
    error_log("Invalid ASIN format: " . $asin);
    jsonResponse(['error' => 'Invalid ASIN format'], 400);
}

try {
    $conn = Database::getConnection();
    setCacheHeaders();
    $results = [];
    
    // Elabora paesi richiesti
    if ($countries) {
        $countryList = explode(',', $countries);
        
        // Limita il numero di paesi
        if (count($countryList) > 10) {
            $countryList = array_slice($countryList, 0, 10);
        }
        
        // Valida ogni paese
        foreach ($countryList as $country) {
            if (!$validator->validateCountry($country)) {
                error_log("Invalid country code: " . $country);
                jsonResponse(['error' => 'Invalid country code: ' . h($country)], 400);
            }
        }
        
        // Usa QueryBuilder per prepared statements
        $qb = new QueryBuilder($conn);
        
        // Prepara query parametrizzata
        $placeholders = str_repeat('?,', count($countryList) - 1) . '?';
        $sql = "SELECT asin, country, price, source, confirmed, timestamp 
                FROM prices 
                WHERE asin = ? AND country IN ($placeholders)
                LIMIT " . MAX_RECORDS_PER_REQUEST;
        
        // Crea array di parametri
        $params = array_merge([$asin], $countryList);
        $types = str_repeat('s', count($params));
        
        $result = $qb->select($sql, $types, $params);
        
        while ($row = $result->fetch_assoc()) {
            $results[] = [
                'asin' => $row['asin'],
                'country' => $row['country'],
                'price' => (float)$row['price'],
                'source' => $row['source'],
                'confirmed' => (bool)$row['confirmed'],
                'timestamp' => $row['timestamp']
            ];
        }
    } else {
        // Se non ci sono paesi specificati, recupera tutti i paesi per l'ASIN
        $qb = new QueryBuilder($conn);
        $sql = "SELECT asin, country, price, source, confirmed, timestamp 
                FROM prices 
                WHERE asin = ?
                LIMIT " . MAX_RECORDS_PER_REQUEST;
        
        $result = $qb->select($sql, 's', [$asin]);
        
        while ($row = $result->fetch_assoc()) {
            $results[] = [
                'asin' => $row['asin'],
                'country' => $row['country'],
                'price' => (float)$row['price'],
                'source' => $row['source'],
                'confirmed' => (bool)$row['confirmed'],
                'timestamp' => $row['timestamp']
            ];
        }
    }
    
    // Log di successo
    error_log("Prices retrieved successfully for ASIN: " . $asin . ", count: " . count($results));
    
    // Restituisci risultati
    jsonResponse($results);
    
} catch (Exception $e) {
    error_log("Error in prices.php: " . $e->getMessage());
    jsonResponse(['error' => 'Database error'], 500);
} finally {
    if (isset($qb)) {
        $qb->close();
    }
    Database::closeConnection();
}
?>