<?php
// File: api/status.php

// Includi file necessari
require_once '../includes/config.php';
require_once '../includes/database.php';

// Imposta headers per la risposta
set_api_headers();

try {
    // Connessione al database
    $db = new Database();
    $conn = $db->getConnection();
    
    // Verifica la connessione al database
    $stmt = $conn->query("SELECT 1");
    $db_status = $stmt->fetchColumn() === 1 ? 'ok' : 'error';
    
    // Ottieni statistiche
    $prices_count = 0;
    $users_count = 0;
    $tokens_count = 0;
    
    // Continuo dal file api/status.php
    if ($db_status === 'ok') {
        $stmt = $conn->query("SELECT COUNT(*) FROM prices");
        $prices_count = $stmt->fetchColumn();
        
        $stmt = $conn->query("SELECT COUNT(*) FROM users");
        $users_count = $stmt->fetchColumn();
        
        $stmt = $conn->query("SELECT COUNT(*) FROM tokens WHERE revoked = 0 AND (expires_at > NOW() OR expires_at IS NULL)");
        $tokens_count = $stmt->fetchColumn();
    }
    
    // Prepara la risposta
    $response = [
        'status' => 'online',
        'version' => '1.1.0',
        'timestamp' => time(),
        'database' => $db_status,
        'stats' => [
            'prices' => (int)$prices_count,
            'users' => (int)$users_count,
            'active_tokens' => (int)$tokens_count
        ],
        'server' => [
            'php_version' => phpversion(),
            'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
        ]
    ];
    
    // Invia la risposta
    http_response_code(200); // OK
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log dell'errore
    error_log("Errore in status.php: " . $e->getMessage());
    
    // Invia risposta di errore
    http_response_code(503); // Service Unavailable
    echo json_encode([
        'status' => 'error',
        'message' => 'Servizio non disponibile',
        'timestamp' => time()
    ]);
}
?>