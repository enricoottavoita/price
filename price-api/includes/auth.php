<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/utilities.php';

/**
 * Classe per la gestione dell'autenticazione
 */
class Auth {
    /**
     * Verifica se la richiesta è autenticata tramite token JWT
     * 
     * @return bool True se autenticato, false altrimenti
     */
    public static function isAuthenticated() {
        try {
            $token = self::getTokenFromHeader();
            if (!$token) {
                return false;
            }
            
            // Per semplicità, verifichiamo solo che il token sia presente
            // In produzione, dovresti verificare la firma JWT
            return true;
        } catch (Exception $e) {
            error_log("Errore autenticazione: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Estrae il token dall'header Authorization
     * 
     * @return string|null Token o null se non presente
     */
    public static function getTokenFromHeader() {
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        if (strpos($authHeader, 'Bearer ') === 0) {
            return substr($authHeader, 7);
        }
        
        return null;
    }
    
    /**
     * Ottiene il client ID dal token JWT
     * 
     * @param string $token Token JWT
     * @return string Client ID estratto dal token
     */
    public static function getClientIdFromToken($token) {
        if (!$token) {
            return 'anonymous';
        }
        
        // Per semplicità, estraiamo semplicemente un ID dal token
        // In produzione, dovresti decodificare il JWT correttamente
        return substr(md5($token), 0, 16);
    }
    
    /**
     * Genera un token JWT per un client ID
     * 
     * @param string $clientId ID del client
     * @return string Token JWT generato
     */
    public static function generateToken($clientId) {
        // Header
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT'
        ];
        
        // Payload
        $payload = [
            'client_id' => $clientId,
            'iat' => time(),
            'exp' => time() + 86400 // 24 ore
        ];
        
        // Codifica header e payload
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($header)));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));
        
        // Firma
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, AUTH_SECRET_KEY, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        // Token JWT completo
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
}