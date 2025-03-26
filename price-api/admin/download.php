<?php
session_start();

// Verifica se l'utente è loggato
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Includi file di configurazione e database
require_once '../includes/config.php';
require_once '../includes/database.php';

// Tipo di download
$type = isset($_GET['type']) ? $_GET['type'] : '';

// Genera un token CSRF se non esiste
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Verifica token CSRF
if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Token di sicurezza non valido.");
}

// Connessione al database
$db = new Database();
$conn = $db->getConnection();

// Imposta intestazioni per il download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $type . '_' . date('Y-m-d') . '.csv"');

// Crea un file handle di output
$output = fopen('php://output', 'w');

// Aggiungi BOM UTF-8 per Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

switch ($type) {
    case 'prices':
        // Filtri
        $asin_filter = isset($_GET['asin']) ? trim($_GET['asin']) : '';
        $country_filter = isset($_GET['country']) ? trim($_GET['country']) : '';
        
        // Costruisci la query di base
        $query = "SELECT * FROM prices WHERE 1=1";
        $params = [];
        
        // Aggiungi filtri se presenti
        if (!empty($asin_filter)) {
            $query .= " AND asin LIKE :asin";
            $params[':asin'] = "%$asin_filter%";
        }
        
        if (!empty($country_filter)) {
            $query .= " AND country = :country";
            $params[':country'] = $country_filter;
        }
        
        // Aggiungi ordinamento
        $query .= " ORDER BY created_at DESC";
        
        // Esegui query
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        // Intestazioni CSV
        fputcsv($output, ['ID', 'ASIN', 'Paese', 'Prezzo', 'Fonte', 'Data Creazione']);
        
        // Dati CSV
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['asin'],
                strtoupper($row['country']),
                $row['price'],
                $row['source'] ?? 'N/A',
                $row['created_at']
            ]);
        }
        break;
    
    case 'users':
        // Filtri
        $client_id_filter = isset($_GET['client_id']) ? trim($_GET['client_id']) : '';
        $status_filter = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;
        
        // Costruisci la query di base
        $query = "SELECT * FROM users WHERE 1=1";
        $params = [];
        
        // Aggiungi filtri se presenti
        if (!empty($client_id_filter)) {
            $query .= " AND client_id LIKE :client_id";
            $params[':client_id'] = "%$client_id_filter%";
        }
        
        if ($status_filter !== null) {
            $query .= " AND status = :status";
            $params[':status'] = $status_filter;
        }
        
        // Aggiungi ordinamento
        $query .= " ORDER BY created_at DESC";
        
        // Esegui query
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        // Intestazioni CSV
        fputcsv($output, ['ID', 'Client ID', 'Stato', 'Note', 'Data Registrazione', 'Ultimo Accesso']);
        
        // Dati CSV
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['client_id'],
                $row['status'] == 1 ? 'Attivo' : 'Disattivato',
                $row['notes'] ?? '',
                $row['created_at'],
                $row['last_login'] ?? 'Mai'
            ]);
        }
        break;
    
    case 'tokens':
        // Filtri
        $client_id_filter = isset($_GET['client_id']) ? trim($_GET['client_id']) : '';
        $status_filter = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;
        
        // Costruisci la query di base
        $query = "SELECT t.*, u.client_id FROM tokens t LEFT JOIN users u ON t.user_id = u.id WHERE 1=1";
        $params = [];
        
        // Aggiungi filtri se presenti
        if (!empty($client_id_filter)) {
            $query .= " AND u.client_id LIKE :client_id";
            $params[':client_id'] = "%$client_id_filter%";
        }
        
        if ($status_filter !== null) {
            if ($status_filter == 1) {
                // Attivi (non revocati e non scaduti)
                $query .= " AND t.revoked = 0 AND (t.expires_at > NOW() OR t.expires_at IS NULL)";
            } else {
                // Inattivi (revocati o scaduti)
                $query .= " AND (t.revoked = 1 OR t.expires_at <= NOW())";
            }
        }
        
        // Aggiungi ordinamento
        $query .= " ORDER BY t.created_at DESC";
        
        // Esegui query
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        // Intestazioni CSV
        fputcsv($output, ['ID', 'Client ID', 'Token', 'Stato', 'Data Creazione', 'Scadenza', 'Data Revoca']);
        
        // Dati CSV
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Determina lo stato
            $status = 'Attivo';
            if ($row['revoked'] == 1) {
                $status = 'Revocato';
            } elseif ($row['expires_at'] && strtotime($row['expires_at']) < time()) {
                $status = 'Scaduto';
            }
            
            fputcsv($output, [
                $row['id'],
                $row['client_id'] ?? 'N/A',
                substr($row['token'], 0, 20) . '...',  // Tronca il token per sicurezza
                $status,
                $row['created_at'],
                $row['expires_at'] ?? 'Mai',
                $row['revoked_at'] ?? '-'
            ]);
        }
        break;
    
    default:
        fputcsv($output, ['Errore', 'Tipo di download non valido']);
}

fclose($output);
exit;
?>