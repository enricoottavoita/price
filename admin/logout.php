<?php
session_start();

// Registra il logout nei log se l'utente era loggato
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && isset($_SESSION['admin_id'])) {
    require_once '../includes/config.php';
    require_once '../includes/database.php';
    
    // Connessione al database
    $db = new Database();
    $conn = $db->getConnection();
    
    // Log del logout
    $admin_id = $_SESSION['admin_id'];
    $ip = $_SERVER['REMOTE_ADDR'];
    $agent = $_SERVER['HTTP_USER_AGENT'];
    
    // Verifica se esiste la tabella admin_logs
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS `admin_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `admin_id` int(11) NOT NULL,
            `action` varchar(50) NOT NULL,
            `ip_address` varchar(45) NOT NULL,
            `user_agent` text NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `admin_id` (`admin_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, ip_address, user_agent) VALUES (:admin_id, 'logout', :ip, :agent)");
        $stmt->bindParam(':admin_id', $admin_id);
        $stmt->bindParam(':ip', $ip);
        $stmt->bindParam(':agent', $agent);
        $stmt->execute();
    } catch (PDOException $e) {
        // Ignora errori, il logout deve procedere comunque
    }
}

// Distruggi la sessione
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Reindirizza alla pagina di login
header("Location: login.php");
exit;
?>