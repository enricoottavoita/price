<?php
// File: includes/api_helper.php

/**
 * Classe per gestire le richieste API in modo centralizzato
 */
class APIHelper {
    /**
     * Verifica l'autenticazione dell'utente
     *
     * @param PDO $conn Connessione al database
     * @param array $tokenData Dati del token JWT
     * @return array Dati dell'utente o null in caso di errore
     */
    public static function authenticateUser($conn, $tokenData) {
        if (!$tokenData || !isset($tokenData['sub'])) {
            return null;
        }
        
        try {
            // Verifica se l'utente esiste e è attivo
            $stmt = $conn->prepare("SELECT id, client_id, status FROM users WHERE id = :id");
            $stmt->bindParam(':id', $tokenData['sub']);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                return null;
            }
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verifica se l'utente è attivo
            if ($user['status'] !== 1) {
                return null;
            }
            
            return $user;
        } catch (PDOException $e) {
            error_log("Errore nella verifica dell'utente: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Verifica se il token è valido
     *
     * @param PDO $conn Connessione al database
     * @param string $token Token JWT
     * @return bool True se il token è valido, false altrimenti
     */
    public static function verifyToken($conn, $token) {
        try {
            // Verifica se il token è stato revocato
            $stmt = $conn->prepare("SELECT id FROM tokens WHERE token = :token AND revoked = 0 AND (expires_at > NOW() OR expires_at IS NULL)");
            $stmt->bindParam(':token', $token);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Errore nella verifica del token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registra una richiesta API nel log
     *
     * @param PDO $conn Connessione al database
     * @param int $user_id ID dell'utente
     * @param string $endpoint Endpoint API chiamato
     * @param mixed $request_data Dati della richiesta
     * @param int $response_code Codice di risposta HTTP
     * @return bool True se il log è stato creato, false altrimenti
     */
    public static function logRequest($conn, $user_id, $endpoint, $request_data, $response_code = 200) {
        try {
            // Verifica se esiste la tabella api_logs
            $conn->exec("CREATE TABLE IF NOT EXISTS `api_logs` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `endpoint` varchar(50) NOT NULL,
                `request_data` text DEFAULT NULL,
                `response_code` int(11) NOT NULL,
                `ip_address` varchar(45) NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                KEY `endpoint` (`endpoint`),
                KEY `created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            
            // Converti i dati della richiesta in JSON se necessario
            if (is_array($request_data) || is_object($request_data)) {
                $request_data = json_encode($request_data);
            }
            
            // Limita la dimensione dei dati della richiesta
            if (strlen($request_data) > 1000) {
                $request_data = substr($request_data, 0, 997) . '...';
            }
            
            // Registra la richiesta
            $stmt = $conn->prepare("INSERT INTO api_logs (user_id, endpoint, request_data, response_code, ip_address) VALUES (:user_id, :endpoint, :request_data, :response_code, :ip_address)");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':endpoint', $endpoint);
            $stmt->bindParam(':request_data', $request_data);
            $stmt->bindParam(':response_code', $response_code, PDO::PARAM_INT);
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $stmt->bindParam(':ip_address', $ip_address);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Errore nella registrazione del log: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Invia una risposta JSON
     *
     * @param mixed $data Dati da inviare
     * @param int $status_code Codice di stato HTTP
     */
    public static function sendResponse($data, $status_code = 200) {
        http_response_code($status_code);
        echo json_encode($data);
        exit;
    }
    
    /**
     * Invia una risposta di errore
     *
     * @param string $message Messaggio di errore
     * @param int $status_code Codice di stato HTTP
     */
    public static function sendError($message, $status_code = 400) {
        self::sendResponse(['error' => $message], $status_code);
    }
    
    /**
     * Controlla se un ASIN è valido
     *
     * @param string $asin ASIN da verificare
     * @return bool True se l'ASIN è valido, false altrimenti
     */
    public static function isValidAsin($asin) {
        return !empty($asin) && preg_match('/^[A-Z0-9]{10}$/', $asin);
    }
    
    /**
     * Controlla se un paese è supportato
     *
     * @param string $country Codice paese da verificare
     * @return bool True se il paese è supportato, false altrimenti
     */
    public static function isValidCountry($country) {
        $allowed_countries = ['it', 'fr', 'de', 'es', 'uk'];
        return in_array(strtolower($country), $allowed_countries);
    }
}
?>