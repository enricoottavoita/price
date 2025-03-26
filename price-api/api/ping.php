<?php
/**
 * API Ping Endpoint
 * 
 * Questo endpoint fornisce informazioni sullo stato del server API
 * e verifica la connessione al database.
 */

// Imposta l'header per la risposta JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestisci le richieste OPTIONS per CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Carica la configurazione (adatta il percorso in base alla tua struttura)
$configFile = __DIR__ . '/../config.php';
$configExists = file_exists($configFile);

if ($configExists) {
    require_once $configFile;
}

// Informazioni di base sulla risposta
$response = [
    'status' => 'success',
    'message' => 'API is running',
    'version' => defined('API_VERSION') ? API_VERSION : '1.0',
    'timestamp' => time(),
    'server_time' => date('Y-m-d H:i:s'),
    'config_loaded' => $configExists
];

// Verifica connessione al database
$dbOk = false;
$dbError = '';

if ($configExists && defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
    try {
        // Tenta la connessione al database
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $db = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        // Esegui una query semplice per verificare la connessione
        $stmt = $db->query('SELECT 1');
        $result = $stmt->fetch();
        
        $dbOk = true;
        
        // Verifica tabelle necessarie
        $tablesOk = true;
        $missingTables = [];
        
        $requiredTables = ['prices', 'tokens'];
        
        foreach ($requiredTables as $table) {
            $stmt = $db->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() === 0) {
                $tablesOk = false;
                $missingTables[] = $table;
            }
        }
        
        $response['database'] = [
            'connected' => true,
            'tables_ok' => $tablesOk
        ];
        
        if (!$tablesOk) {
            $response['database']['missing_tables'] = $missingTables;
        }
        
        // Controlla il numero di record in prices
        $stmt = $db->query("SELECT COUNT(*) as count FROM prices");
        $countResult = $stmt->fetch();
        $response['database']['prices_count'] = (int)$countResult['count'];
        
    } catch (PDOException $e) {
        $dbOk = false;
        $dbError = $e->getMessage();
        
        // Rimuovi informazioni sensibili dall'errore
        $dbError = preg_replace('/SQLSTATE\[\w+\] \[\d+\] /', '', $dbError);
        $dbError = str_replace(DB_PASS, '********', $dbError);
        $dbError = str_replace(DB_USER, '[USER]', $dbError);
        
        $response['database'] = [
            'connected' => false,
            'error' => $dbError
        ];
    }
} else {
    $response['database'] = [
        'connected' => false,
        'error' => 'Configurazione database mancante'
    ];
}

// Verifica permessi file
$response['permissions'] = [
    'api_dir_writable' => is_writable(__DIR__),
    'parent_dir_writable' => is_writable(dirname(__DIR__))
];

// Informazioni sul server
$response['server_info'] = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time') . 's'
];

// Controllo spazio su disco
if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
    $freeSpace = disk_free_space('/');
    $totalSpace = disk_total_space('/');
    
    if ($freeSpace !== false && $totalSpace !== false) {
        $response['disk_space'] = [
            'free' => formatBytes($freeSpace),
            'total' => formatBytes($totalSpace),
            'free_percent' => round(($freeSpace / $totalSpace) * 100, 1) . '%'
        ];
    }
}

// Funzione per formattare i byte in unità leggibili
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Invia la risposta JSON
echo json_encode($response, JSON_PRETTY_PRINT);