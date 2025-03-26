<?php
// Includi file di configurazione e database
require_once 'includes/config.php';
require_once 'includes/database.php';

// Connessione al database
$db = new Database();
$conn = $db->getConnection();

try {
    // Crea la tabella settings
    $conn->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `setting_key` varchar(255) NOT NULL,
        `setting_value` text NOT NULL,
        `setting_group` varchar(100) NOT NULL DEFAULT 'general',
        `setting_type` varchar(50) NOT NULL DEFAULT 'text',
        `setting_label` varchar(255) NOT NULL,
        `setting_description` text DEFAULT NULL,
        `setting_options` text DEFAULT NULL,
        `is_public` tinyint(1) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    echo "Tabella 'settings' creata con successo.<br>";
    
    // Verifica se ci sono già impostazioni
    $stmt = $conn->query("SELECT COUNT(*) FROM settings");
    if ($stmt->fetchColumn() > 0) {
        echo "Le impostazioni esistono già nella tabella.<br>";
    } else {
        // Genera una chiave JWT segreta casuale
        $jwt_secret = bin2hex(random_bytes(32));
        
        // Inserisci impostazioni predefinite - Gruppo API
        $api_settings = [
            ['jwt_secret', $jwt_secret, 'api', 'password', 'JWT Secret Key', 'Chiave segreta per la generazione dei token JWT', 0],
            ['jwt_expiry', '86400', 'api', 'number', 'JWT Expiry Time', 'Tempo di scadenza dei token JWT in secondi', 0],
            ['auth_key', 'FQGGHSTdxc', 'api', 'text', 'Auth Key', 'Chiave di autenticazione per le API', 0]
        ];
        
        // Inserisci impostazioni predefinite - Gruppo Generale
        $general_settings = [
            ['site_name', 'Price API', 'general', 'text', 'Nome Sito', 'Nome del sito visualizzato nelle email e nell\'interfaccia', 1],
            ['admin_email', 'admin@example.com', 'general', 'email', 'Email Amministratore', 'Indirizzo email per le notifiche amministrative', 0],
            ['prices_per_page', '20', 'general', 'number', 'Prezzi per Pagina', 'Numero di prezzi da visualizzare per pagina nell\'admin', 0]
        ];
        
        // Inserisci impostazioni predefinite - Gruppo Sicurezza
        $security_settings = [
            ['max_login_attempts', '5', 'security', 'number', 'Tentativi Login Massimi', 'Numero massimo di tentativi di login falliti prima del blocco', 0],
            ['login_timeout', '15', 'security', 'number', 'Timeout Login (minuti)', 'Tempo di attesa dopo troppi tentativi falliti', 0],
            ['session_timeout', '30', 'security', 'number', 'Timeout Sessione (minuti)', 'Tempo di inattività prima che la sessione scada', 0]
        ];
        
        $all_settings = array_merge($api_settings, $general_settings, $security_settings);
        
        $insert_stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_group, setting_type, setting_label, setting_description, is_public) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($all_settings as $setting) {
            $insert_stmt->execute($setting);
        }
        
        echo "Impostazioni predefinite inserite con successo.<br>";
    }
    
    echo "Setup completato!";
} catch (PDOException $e) {
    echo "Errore: " . $e->getMessage();
}
?>