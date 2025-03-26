<?php
// Imposta header CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestisci richieste OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    exit;
}

// Solo richieste GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Crea un file di log per il debug
$log_file = dirname(__DIR__) . '/api_debug.log';
try {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Richiesta a get_prices.php\n", FILE_APPEND);
} catch (Exception $e) {
    // Ignora errori di scrittura log
}

// Ottieni parametri
$asin = isset($_GET['asin']) ? $_GET['asin'] : '';
$countries = isset($_GET['countries']) ? explode(',', $_GET['countries']) : ['it', 'de', 'fr', 'es'];

try {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Parametri: ASIN=$asin, Countries=" . implode(',', $countries) . "\n", FILE_APPEND);
} catch (Exception $e) {
    // Ignora errori di scrittura log
}

// Valida ASIN
if (empty($asin) || !preg_match('/^[A-Z0-9]{10}$/', $asin)) {
    http_response_code(200); // Usa 200 anche per errori
    echo json_encode([
        'status' => 'success',
        'prices' => [] // Array vuoto in caso di errore
    ]);
    exit;
}

try {
    // Includi le dipendenze
    require_once dirname(__DIR__) . '/config.php';
    require_once dirname(__DIR__) . '/includes/database.php';
    
    try {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - File inclusi con successo\n", FILE_APPEND);
    } catch (Exception $e) {
        // Ignora errori di scrittura log
    }
    
    // Ottieni connessione al database
    $conn = Database::getConnection();
    
    try {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Connessione al database stabilita\n", FILE_APPEND);
    } catch (Exception $e) {
        // Ignora errori di scrittura log
    }
    
    // Prepara array paesi
    $validCountries = ['it', 'de', 'fr', 'es'];
    $filteredCountries = array_intersect($countries, $validCountries);
    
    // Risultati
    $prices = [];
    
    // Recupera prezzi dal database
    if (!empty($filteredCountries)) {
        $placeholders = implode(',', array_fill(0, count($filteredCountries), '?'));
        $sql = "SELECT asin, country, price, source, timestamp FROM prices 
                WHERE asin = ? AND country IN ($placeholders)";
        
        $types = 's' . str_repeat('s', count($filteredCountries));
        $params = array_merge([$asin], $filteredCountries);
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $prices[$row['country']] = [
                    'price' => (float)$row['price'],
                    'source' => $row['source'],
                    'timestamp' => $row['timestamp']
                ];
            }
            
            $stmt->close();
            
            try {
                file_put_contents($log_file, date('Y-m-d H:i:s') . " - Prezzi recuperati con successo: " . count($prices) . " risultati\n", FILE_APPEND);
            } catch (Exception $e) {
                // Ignora errori di scrittura log
            }
        }
    }
    
    // Rispondi con i prezzi
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'prices' => $prices,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    try {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - ERRORE: " . $e->getMessage() . "\n", FILE_APPEND);
    } catch (Exception $logError) {
        // Ignora errori di scrittura log
    }
    
    // In caso di errore, restituisci un array vuoto
    http_response_code(200); // Usa 200 anche per errori
    echo json_encode([
        'status' => 'success',
        'prices' => [],
        'message' => 'Nessun prezzo disponibile',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}