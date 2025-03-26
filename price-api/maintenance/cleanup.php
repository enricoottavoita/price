<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/database.php';

// Script da eseguire tramite cron job (es. giornalmente)
// Imposta come: 0 3 * * * php /path/to/cleanup.php

try {
    $conn = Database::getConnection();
    
    // Elimina prezzi vecchi di più di 30 giorni dalla cronologia
    $sql = "DELETE FROM price_history WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    $deletedHistory = $conn->affected_rows;
    echo "Deleted $deletedHistory old price history records\n";
    
    // Rimuovi statistiche contributori inattivi
    $sql = "DELETE FROM contributor_stats WHERE last_contribution < DATE_SUB(NOW(), INTERVAL 90 DAY)";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    $deletedContributors = $conn->affected_rows;
    echo "Deleted $deletedContributors inactive contributor records\n";
    
    // Pulisci vecchi record di rate limiting
    $sql = "DELETE FROM rate_limits WHERE request_time < DATE_SUB(NOW(), INTERVAL 1 DAY)";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    $deletedRateLimits = $conn->affected_rows;
    echo "Deleted $deletedRateLimits old rate limit records\n";
    
    // Ottimizza tabelle
    $tables = ['prices', 'price_history', 'contributor_stats', 'rate_limits', 'users'];
    foreach ($tables as $table) {
        $conn->query("OPTIMIZE TABLE $table");
    }
    
    echo "Database optimization complete\n";
    
} catch (Exception $e) {
    echo "Error during cleanup: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    Database::closeConnection();
}
?>