<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/database.php';

/**
 * Classe per la gestione del rate limiting
 */
class RateLimiter {
    private $clientId;
    
    /**
     * Costruttore
     * 
     * @param string $clientId ID client da controllare
     */
    public function __construct($clientId) {
        $this->clientId = $clientId;
    }
    
    /**
     * Verifica se il client ha superato il limite di richieste
     * 
     * @return bool True se il client è limitato, false altrimenti
     */
    public function isLimited() {
        // Implementazione semplificata per test
        return false;
        
        // Implementazione reale (decommentare in produzione)
        /*
        try {
            $conn = Database::getConnection();
            
            // Elimina richieste vecchie
            $sql = "DELETE FROM api_requests WHERE client_id = ? AND request_time < DATE_SUB(NOW(), INTERVAL ? SECOND)";
            $stmt = $conn->prepare($sql);
            $windowSeconds = RATE_LIMIT_WINDOW;
            $stmt->bind_param('si', $this->clientId, $windowSeconds);
            $stmt->execute();
            
            // Conta richieste recenti
            $sql = "SELECT COUNT(*) as count FROM api_requests WHERE client_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $this->clientId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            return $row['count'] >= RATE_LIMIT_REQUESTS;
        } catch (Exception $e) {
            error_log("Errore rate limiter: " . $e->getMessage());
            return false; // In caso di errore, non limitiamo
        }
        */
    }
    
    /**
     * Registra una nuova richiesta
     */
    public function logRequest() {
        // Implementazione semplificata per test
        return;
        
        // Implementazione reale (decommentare in produzione)
        /*
        try {
            $conn = Database::getConnection();
            
            // Crea tabella se non esiste
            $conn->query("CREATE TABLE IF NOT EXISTS api_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id VARCHAR(100) NOT NULL,
                request_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (client_id, request_time)
            )");
            
            // Inserisci nuova richiesta
            $sql = "INSERT INTO api_requests (client_id) VALUES (?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $this->clientId);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Errore nel logging della richiesta: " . $e->getMessage());
        }
        */
    }
}