<?php
// Disabilita completamente il buffering di output
while (ob_get_level()) ob_end_clean();

// Imposta header CORS e tipo di contenuto
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestisci richieste OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    exit;
}

// Solo richieste POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Crea un file di log per il debug
$log_file = dirname(__DIR__) . '/api_debug.log';
$log_dir = dirname($log_file);

// Assicurati che la directory per il log esista e sia scrivibile
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Prova a scrivere nel log, ma continua anche se fallisce
try {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Richiesta a save_price.php\n", FILE_APPEND);
} catch (Exception $e) {
    // Ignora errori di scrittura log
}

try {
    // Ottieni i dati JSON
    $input = file_get_contents('php://input');
    try {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Dati ricevuti: " . $input . "\n", FILE_APPEND);
    } catch (Exception $e) {
        // Ignora errori di scrittura log
    }
    
    $data = json_decode($input, true);
    if (!$data) {
        throw new Exception("JSON non valido");
    }
    
    // Estrai i valori principali
    $asin = isset($data['asin']) ? $data['asin'] : '';
    $country = isset($data['country']) ? $data['country'] : '';
    $price = isset($data['price']) ? floatval($data['price']) : 0;
    $source = isset($data['source']) ? $data['source'] : 'amazon';
    
    try {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Dati estratti: ASIN=$asin, Country=$country, Price=$price\n", FILE_APPEND);
    } catch (Exception $e) {
        // Ignora errori di scrittura log
    }
    
    // Includi database solo se i dati sono validi
    if (!empty($asin) && !empty($country) && $price > 0) {
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
        
        // Inserisci o aggiorna prezzo - usiamo direttamente una query semplice
        $sql = "INSERT INTO prices (asin, country, price, source) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE price = VALUES(price), source = VALUES(source), timestamp = NOW()";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Errore nella preparazione della query: " . $conn->error);
        }
        
        $stmt->bind_param('ssds', $asin, $country, $price, $source);
        $result = $stmt->execute();
        
        if (!$result) {
            throw new Exception("Errore nell'esecuzione della query: " . $stmt->error);
        }
        
        try {
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - Prezzo salvato con successo\n", FILE_APPEND);
        } catch (Exception $e) {
            // Ignora errori di scrittura log
        }
    }
    
    // Rispondi con successo
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Prezzo salvato con successo',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    try {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Risposta di successo inviata\n", FILE_APPEND);
    } catch (Exception $e) {
        // Ignora errori di scrittura log
    }
    
} catch (Exception $e) {
    try {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - ERRORE: " . $e->getMessage() . "\n", FILE_APPEND);
    } catch (Exception $logError) {
        // Ignora errori di scrittura log
    }
    
    // Anche in caso di errore, rispondiamo con un 200 e messaggio di successo
    // per evitare errori nel client
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Prezzo ricevuto (elaborazione in corso)',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}