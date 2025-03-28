<?php
// Abilita CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json");

// Gestisci le richieste OPTIONS (pre-flight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Includi il file di configurazione
require_once '../includes/config.php';

// Includi funzioni di utilità
require_once '../includes/functions.php';

// Ottieni l'Authorization header
$authHeader = getAuthorizationHeader();
$token = null;

// Estrai il token dal header (formato "Bearer [token]")
if (!empty($authHeader)) {
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
    }
}

// Se non c'è token, restituisci errore
if (!$token) {
    echo json_encode(['status' => 'error', 'message' => 'Token mancante']);
    exit;
}

// Ricevi il client_id dal body della richiesta
$data = json_decode(file_get_contents('php://input'), true);
$client_id = isset($data['client_id']) ? $data['client_id'] : null;

if (!$client_id) {
    echo json_encode(['status' => 'error', 'message' => 'Client ID mancante']);
    exit;
}

try {
    // Connessione al database usando i parametri da config.php
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verifica se il token esiste e non è scaduto
    $stmt = $pdo->prepare("SELECT * FROM auth_tokens WHERE token = ? AND client_id = ? AND expires_at > NOW()");
    $stmt->execute([$token, $client_id]);
    $tokenRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($tokenRecord) {
        // Token valido
        echo json_encode(['status' => 'success', 'valid' => true]);
    } else {
        // Token non valido o scaduto
        echo json_encode(['status' => 'success', 'valid' => false]);
    }
} catch (PDOException $e) {
    // Errore di connessione al database
    echo json_encode(['status' => 'error', 'message' => 'Errore del database: ' . $e->getMessage()]);
}

// Funzione per ottenere l'header di autorizzazione
function getAuthorizationHeader() {
    $headers = null;
    
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER['Authorization']);
    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
    } else if (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(
            array_map('ucwords', array_keys($requestHeaders)),
            array_values($requestHeaders)
        );
        
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    
    return $headers;
}
?>