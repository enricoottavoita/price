<?php
/**
 * Utility functions for the API
 */

/**
 * Enables CORS headers for API requests
 */
function enableCORS() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * Sends a JSON response
 * 
 * @param array $data Data to send as JSON
 * @param int $statusCode HTTP status code
 */
function jsonResponse($data, $statusCode = 200) {
    // Pulisci eventuali output precedenti
    if (ob_get_length()) ob_clean();
    
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Gets the current date and time in the specified format
 * 
 * @param string $format Date format
 * @return string Formatted date and time
 */
function getCurrentDateTime($format = 'Y-m-d H:i:s') {
    return date($format);
}

/**
 * Logs a message to the error log
 * 
 * @param string $message Message to log
 * @param string $level Log level (info, warning, error)
 */
function logMessage($message, $level = 'info') {
    $prefix = "[" . strtoupper($level) . "] ";
    error_log($prefix . $message);
}

/**
 * Implementazione di getallheaders() per ambienti dove non è disponibile
 */
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            } elseif ($name === 'CONTENT_TYPE' || $name === 'CONTENT_LENGTH') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $name))))] = $value;
            }
        }
        return $headers;
    }
}