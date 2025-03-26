<?php
// Classe per gestire l'autenticazione dell'admin
class AdminAuth {
    // Username e password per admin (idealmente da spostare in un file di configurazione separato)
    private static $adminUsername = 'admin';
    private static $adminPassword = '$2y$10$FqtUHWagbTyDy7HYd66Z0e3mZVIYGGMRTsPRMKVf9D6cmPThQ/8Tq'; // Usa password_hash() per generare
    
    // Verifica login
    public static function login($username, $password) {
        if ($username === self::$adminUsername) {
            // Verifica password (usa password_verify se hai usato password_hash)
            if (password_verify($password, self::$adminPassword)) {
                // Salva sessione
                self::startSession();
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $username;
                $_SESSION['admin_login_time'] = time();
                
                return true;
            }
        }
        
        return false;
    }
    
    // Verifica se l'admin è loggato
    public static function isLoggedIn() {
        self::startSession();
        
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            // Verifica se la sessione è scaduta (opzionale)
            $sessionTimeout = 3600; // 1 ora
            if (time() - $_SESSION['admin_login_time'] > $sessionTimeout) {
                self::logout();
                return false;
            }
            
            // Aggiorna timestamp
            $_SESSION['admin_login_time'] = time();
            
            return true;
        }
        
        return false;
    }
    
    // Logout
    public static function logout() {
        self::startSession();
        
        // Distruggi sessione
        unset($_SESSION['admin_logged_in']);
        unset($_SESSION['admin_username']);
        unset($_SESSION['admin_login_time']);
        
        // Distruggi completamente la sessione
        session_destroy();
    }
    
    // Proteggi pagina
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: index.php');
            exit;
        }
    }
    
    // Inizializza sessione se non attiva
    private static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

// Genera hash password (esegui una volta per generare l'hash)
// Decommentare per generare un nuovo hash, poi commentare di nuovo
/*
if (isset($_GET['generate_hash']) && $_GET['generate_hash'] === 'true') {
    $password = 'your_admin_password_here';
    echo password_hash($password, PASSWORD_DEFAULT);
    exit;
}
*/
?>