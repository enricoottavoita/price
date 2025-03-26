<?php
// File: includes/jwt_helper.php

class JWTHelper {
    private static function base64UrlEncode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
    
    private static function base64UrlDecode($data) {
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }
    
    public static function generateToken($user_id, $client_id, $expiry = null) {
        // Se non viene specificata una scadenza, usa quella predefinita
        if ($expiry === null) {
            $expiry = time() + JWT_EXPIRY;
        }
        
        // Header del token
        $header = json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT'
        ]);
        
        // Payload del token
        $payload = json_encode([
            'sub' => $user_id,
            'client_id' => $client_id,
            'iat' => time(),
            'exp' => $expiry
        ]);
        
        // Codifica header e payload
        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);
        
        // Crea la firma
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
        $base64UrlSignature = self::base64UrlEncode($signature);
        
        // Crea il token completo
        $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
        
        return $jwt;
    }
    
    public static function validateToken($token) {
        // Suddivide il token nelle sue parti
        $tokenParts = explode('.', $token);
        
        // Verifica che il token abbia tre parti
        if (count($tokenParts) != 3) {
            return false;
        }
        
        // Estrae header, payload e firma
        $header = $tokenParts[0];
        $payload = $tokenParts[1];
        $signature = $tokenParts[2];
        
        // Ricrea la firma per confrontarla
        $recreatedSignature = self::base64UrlEncode(
            hash_hmac('sha256', $header . "." . $payload, JWT_SECRET, true)
        );
        
        // Verifica la firma
        if ($recreatedSignature !== $signature) {
            return false;
        }
        
        // Decodifica il payload
        $decodedPayload = json_decode(self::base64UrlDecode($payload), true);
        
        // Verifica la scadenza del token
        if (isset($decodedPayload['exp']) && $decodedPayload['exp'] < time()) {
            return false;
        }
        
        return $decodedPayload;
    }
    
    public static function getAuthorizationHeader() {
        $headers = null;
        
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } else if (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        
        return $headers;
    }
    
    public static function getBearerToken() {
        $headers = self::getAuthorizationHeader();
        
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
}
?>