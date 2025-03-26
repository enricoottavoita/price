<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/utilities.php';

// Inizializzazione
enableCORS();

try {
    // Verifica connessione database
    $conn = Database::getConnection();
    
    // Conta record per verificare che ci siano dati
    $sql = "SELECT COUNT(*) as count FROM prices LIMIT 1";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $count = $row['count'];
    
    // Ottieni statistiche
    $sql = "SELECT COUNT(*) as total_prices, 
            COUNT(DISTINCT asin) as total_products,
            COUNT(DISTINCT country) as total_countries,
            COUNT(DISTINCT client_id) as total_contributors,
            MAX(timestamp) as last_update
            FROM prices";
    
    $result = $conn->query($sql);
    $stats = $result->fetch_assoc();
    
    // Ottieni statistiche giornaliere
    $sql = "SELECT COUNT(*) as today_contributions
            FROM price_history
            WHERE timestamp >= CURDATE()";
    
    $result = $conn->query($sql);
    $dailyStats = $result->fetch_assoc();
    
    $stats['today_contributions'] = $dailyStats['today_contributions'];
    
    // Log di successo
    error_log("Status check successful, " . $count . " records in database");
    
    // Restituisci stato
    jsonResponse([
        'status' => 'ok',
        'version' => '1.0.0',
        'timestamp' => date('Y-m-d H:i:s'),
        'database' => [
            'connected' => true,
            'hasData' => $count > 0,
            'stats' => $stats
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in status.php: " . $e->getMessage());
    jsonResponse([
        'status' => 'error',
        'version' => '1.0.0',
        'timestamp' => date('Y-m-d H:i:s'),
        'database' => [
            'connected' => false,
            'error' => $e->getMessage()
        ]
    ], 500);
} finally {
    Database::closeConnection();
}
?>