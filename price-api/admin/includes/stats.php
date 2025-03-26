<?php
class Stats {
    // Ottieni statistiche generali
    public static function getGeneralStats() {
        try {
            $conn = Database::getConnection();
            
            // Statistiche principali
            $sql = "SELECT 
                    (SELECT COUNT(*) FROM prices) as total_prices,
                    (SELECT COUNT(DISTINCT asin) FROM prices) as total_products,
                    (SELECT COUNT(DISTINCT country) FROM prices) as total_countries,
                    (SELECT COUNT(DISTINCT client_id) FROM contributor_stats) as total_contributors,
                    (SELECT COUNT(*) FROM price_history WHERE DATE(timestamp) = CURDATE()) as today_contributions,
                    (SELECT COUNT(*) FROM prices WHERE source = 'amazon') as source_amazon,
                    (SELECT COUNT(*) FROM prices WHERE source = 'keepa') as source_keepa,
                    (SELECT COUNT(*) FROM prices WHERE source = 'both') as source_both,
                    (SELECT COUNT(*) FROM prices WHERE source = 'manual') as source_manual";
            
            $result = $conn->query($sql);
            $stats = $result->fetch_assoc();
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error in getGeneralStats: " . $e->getMessage());
            return [
                'total_prices' => 0,
                'total_products' => 0,
                'total_countries' => 0,
                'total_contributors' => 0,
                'today_contributions' => 0,
                'source_amazon' => 0,
                'source_keepa' => 0,
                'source_both' => 0,
                'source_manual' => 0
            ];
        }
    }
    
    // Ottieni contribuzioni giornaliere
    public static function getDailyContributions($days = 30) {
        try {
            $conn = Database::getConnection();
            
            $sql = "SELECT 
                    DATE(timestamp) as date, 
                    COUNT(*) as count 
                    FROM price_history 
                    WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL ? DAY) 
                    GROUP BY DATE(timestamp) 
                    ORDER BY date";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $days);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            
            return $data;
        } catch (Exception $e) {
            error_log("Error in getDailyContributions: " . $e->getMessage());
            return [];
        }
    }
    
    // Ottieni i prodotti più popolari
    public static function getTopProducts($limit = 10) {
        try {
            $conn = Database::getConnection();
            
            $sql = "SELECT 
                    p.asin,
                    p.country,
                    p.price as last_price,
                    p.timestamp as last_update,
                    COUNT(ph.id) as contributions
                    FROM prices p
                    JOIN price_history ph ON p.asin = ph.asin AND p.country = ph.country
                    GROUP BY p.asin, p.country
                    ORDER BY contributions DESC
                    LIMIT ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            
            return $data;
        } catch (Exception $e) {
            error_log("Error in getTopProducts: " . $e->getMessage());
            return [];
        }
    }
    
    // Ottieni i contributori più attivi
    public static function getTopContributors($limit = 10) {
        try {
            $conn = Database::getConnection();
            
            $sql = "SELECT 
                    client_id,
                    total_contributions,
                    last_contribution
                    FROM contributor_stats
                    ORDER BY total_contributions DESC
                    LIMIT ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            
            return $data;
        } catch (Exception $e) {
            error_log("Error in getTopContributors: " . $e->getMessage());
            return [];
        }
    }
    
    // Ottieni distribuzione per paese
    public static function getCountryDistribution() {
        try {
            $conn = Database::getConnection();
            
            $sql = "SELECT 
                    country,
                    COUNT(*) as count
                    FROM prices
                    GROUP BY country
                    ORDER BY count DESC";
            
            $result = $conn->query($sql);
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                // Converti il codice paese in nome leggibile
                // Converti il codice paese in nome leggibile
switch (strtolower($row['country'])) {
    case 'it': $row['country'] = 'Italia'; break;
    case 'de': $row['country'] = 'Germania'; break;
    case 'fr': $row['country'] = 'Francia'; break;
    case 'es': $row['country'] = 'Spagna'; break;
    default: $row['country'] = strtoupper($row['country']);
}
                $data[] = $row;
            }
            
            return $data;
        } catch (Exception $e) {
            error_log("Error in getCountryDistribution: " . $e->getMessage());
            return [];
        }
    }
    
    // Ottieni lista prodotti con paginazione e filtri
    public static function getProducts($page = 1, $perPage = 20, $search = '', $country = '', $orderBy = 'timestamp', $orderDir = 'DESC') {
        try {
            $conn = Database::getConnection();
            
            // Costruisci query con filtri
            $sql = "SELECT * FROM prices WHERE 1=1";
            $params = [];
            $types = "";
            
            if (!empty($search)) {
                $sql .= " AND asin LIKE ?";
                $searchParam = "%$search%";
                $params[] = $searchParam;
                $types .= "s";
            }
            
            if (!empty($country)) {
                $sql .= " AND country = ?";
                $params[] = $country;
                $types .= "s";
            }
            
            // Validazione campi di ordinamento
            $validOrderBy = ['asin', 'country', 'price', 'source', 'timestamp'];
            $validOrderDir = ['ASC', 'DESC'];
            
            if (!in_array($orderBy, $validOrderBy)) {
                $orderBy = 'timestamp';
            }
            
            if (!in_array(strtoupper($orderDir), $validOrderDir)) {
                $orderDir = 'DESC';
            }
            
            $sql .= " ORDER BY $orderBy $orderDir";
            
            // Aggiungi paginazione
            $offset = ($page - 1) * $perPage;
            $sql .= " LIMIT ?, ?";
            $params[] = $offset;
            $params[] = $perPage;
            $types .= "ii";
            
            // Esegui query
            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            
            return $data;
        } catch (Exception $e) {
            error_log("Error in getProducts: " . $e->getMessage());
            return [];
        }
    }
    
    // Ottieni conteggio totale prodotti per paginazione
    public static function getTotalProductsCount($search = '', $country = '') {
        try {
            $conn = Database::getConnection();
            
            // Costruisci query con filtri
            $sql = "SELECT COUNT(*) as total FROM prices WHERE 1=1";
            $params = [];
            $types = "";
            
            if (!empty($search)) {
                $sql .= " AND asin LIKE ?";
                $searchParam = "%$search%";
                $params[] = $searchParam;
                $types .= "s";
            }
            
            if (!empty($country)) {
                $sql .= " AND country = ?";
                $params[] = $country;
                $types .= "s";
            }
            
            // Esegui query
            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            return (int)$row['total'];
        } catch (Exception $e) {
            error_log("Error in getTotalProductsCount: " . $e->getMessage());
            return 0;
        }
    }
    
    // Elimina un prodotto
    public static function deleteProduct($asin, $country) {
        try {
            $conn = Database::getConnection();
            
            // Inizia transazione
            $conn->begin_transaction();
            
            // Elimina dalla tabella principale
            $sql = "DELETE FROM prices WHERE asin = ? AND country = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $asin, $country);
            $stmt->execute();
            
            // Elimina dalla cronologia
            $sql = "DELETE FROM price_history WHERE asin = ? AND country = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $asin, $country);
            $stmt->execute();
            
            // Commit transazione
            $conn->commit();
            
            return true;
        } catch (Exception $e) {
            // Rollback in caso di errore
            $conn->rollback();
            error_log("Error in deleteProduct: " . $e->getMessage());
            return false;
        }
    }
    
    // Ottieni dettagli di un prodotto specifico
    public static function getProductDetails($asin, $country) {
        try {
            $conn = Database::getConnection();
            
            $sql = "SELECT * FROM prices WHERE asin = ? AND country = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $asin, $country);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return null;
            }
            
            return $result->fetch_assoc();
        } catch (Exception $e) {
            error_log("Error in getProductDetails: " . $e->getMessage());
            return null;
        }
    }
    
    // Ottieni cronologia prezzi di un prodotto
    public static function getProductHistory($asin, $country, $limit = 100) {
        try {
            $conn = Database::getConnection();
            
            $sql = "SELECT * FROM price_history 
                    WHERE asin = ? AND country = ? 
                    ORDER BY timestamp DESC 
                    LIMIT ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssi', $asin, $country, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            
            return $data;
        } catch (Exception $e) {
            error_log("Error in getProductHistory: " . $e->getMessage());
            return [];
        }
    }
    
    // Ottieni lista utenti con paginazione e filtri
    public static function getUsers($page = 1, $perPage = 20, $search = '', $orderBy = 'last_contribution', $orderDir = 'DESC') {
        try {
            $conn = Database::getConnection();
            
            // Costruisci query con filtri
            $sql = "SELECT cs.*, u.last_login 
                    FROM contributor_stats cs
                    LEFT JOIN users u ON cs.client_id = u.client_id
                    WHERE 1=1";
            
            $params = [];
            $types = "";
            
            if (!empty($search)) {
                $sql .= " AND cs.client_id LIKE ?";
                $searchParam = "%$search%";
                $params[] = $searchParam;
                $types .= "s";
            }
            
            // Validazione campi di ordinamento
            $validOrderBy = ['client_id', 'total_contributions', 'last_contribution'];
            $validOrderDir = ['ASC', 'DESC'];
            
            if (!in_array($orderBy, $validOrderBy)) {
                $orderBy = 'last_contribution';
            }
            
            if (!in_array(strtoupper($orderDir), $validOrderDir)) {
                $orderDir = 'DESC';
            }
            
            $sql .= " ORDER BY cs.$orderBy $orderDir";
            
            // Aggiungi paginazione
            $offset = ($page - 1) * $perPage;
            $sql .= " LIMIT ?, ?";
            $params[] = $offset;
            $params[] = $perPage;
            $types .= "ii";
            
            // Esegui query
            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            
            return $data;
        } catch (Exception $e) {
            error_log("Error in getUsers: " . $e->getMessage());
            return [];
        }
    }
    
    // Ottieni conteggio totale utenti per paginazione
    public static function getTotalUsersCount($search = '') {
        try {
            $conn = Database::getConnection();
            
            // Costruisci query con filtri
            $sql = "SELECT COUNT(*) as total FROM contributor_stats WHERE 1=1";
            $params = [];
            $types = "";
            
            if (!empty($search)) {
                $sql .= " AND client_id LIKE ?";
                $searchParam = "%$search%";
                $params[] = $searchParam;
                $types .= "s";
            }
            
            // Esegui query
            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            return (int)$row['total'];
        } catch (Exception $e) {
            error_log("Error in getTotalUsersCount: " . $e->getMessage());
            return 0;
        }
    }
    
    // Elimina un utente
    public static function deleteUser($clientId) {
        try {
            $conn = Database::getConnection();
            
            // Inizia transazione
            $conn->begin_transaction();
            
            // Elimina dalla tabella contributor_stats
            $sql = "DELETE FROM contributor_stats WHERE client_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $clientId);
            $stmt->execute();
            
            // Elimina dalla tabella users
            $sql = "DELETE FROM users WHERE client_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $clientId);
            $stmt->execute();
            
            // Commit transazione
            $conn->commit();
            
            return true;
        } catch (Exception $e) {
            // Rollback in caso di errore
            $conn->rollback();
            error_log("Error in deleteUser: " . $e->getMessage());
            return false;
        }
    }
    
    // Ottieni i contributi di un utente specifico
    public static function getUserContributions($clientId, $limit = 100) {
        try {
            $conn = Database::getConnection();
            
            $sql = "SELECT * FROM price_history 
                    WHERE client_id = ? 
                    ORDER BY timestamp DESC 
                    LIMIT ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $clientId, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            
            return $data;
        } catch (Exception $e) {
            error_log("Error in getUserContributions: " . $e->getMessage());
            return [];
        }
    }
    
    // Ottieni dettagli di un utente specifico
    public static function getUserDetails($clientId) {
        try {
            $conn = Database::getConnection();
            
            $sql = "SELECT cs.*, u.last_login, u.created_at 
                    FROM contributor_stats cs
                    LEFT JOIN users u ON cs.client_id = u.client_id
                    WHERE cs.client_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $clientId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return null;
            }
            
            return $result->fetch_assoc();
        } catch (Exception $e) {
            error_log("Error in getUserDetails: " . $e->getMessage());
            return null;
        }
    }
}

// Ottieni impostazioni di sistema
function getSystemSettings() {
    try {
        $conn = Database::getConnection();
        
        $sql = "SELECT * FROM system_settings";
        $result = $conn->query($sql);
        
        if ($result->num_rows === 0) {
            // Restituisci impostazioni predefinite
            return [
                'system_enabled' => true,
                'max_history_records' => 50,
                'cache_time' => 300,
                'outlier_threshold' => 25,
                'rate_limit_requests' => 100,
                'rate_limit_window' => 3600,
                'token_expiry' => 30,
                'auth_key' => AUTH_KEY,
                'cleanup_days' => 90,
                'inactive_user_days' => 90,
                'supported_countries' => ['it', 'de', 'fr', 'es'],  // Solo i 4 paesi supportati
                'max_price_threshold' => 10000,
                'min_price_threshold' => 1
            ];
        }
        
        $settings = $result->fetch_assoc();
        
        // Decodifica array serializzati
        if (isset($settings['supported_countries'])) {
            $settings['supported_countries'] = json_decode($settings['supported_countries'], true);
        }
        
        return $settings;
    } catch (Exception $e) {
        error_log("Error in getSystemSettings: " . $e->getMessage());
        return [];
    }
}

// Salva impostazioni di sistema
function saveSystemSettings($data) {
    try {
        $conn = Database::getConnection();
        
        // Verifica se la tabella impostazioni esiste
        $checkTable = $conn->query("SHOW TABLES LIKE 'system_settings'");
        if ($checkTable->num_rows === 0) {
            // Crea tabella se non esiste
            $createTable = "CREATE TABLE system_settings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                system_enabled TINYINT(1) DEFAULT 1,
                max_history_records INT DEFAULT 50,
                cache_time INT DEFAULT 300,
                outlier_threshold INT DEFAULT 25,
                rate_limit_requests INT DEFAULT 100,
                rate_limit_window INT DEFAULT 3600,
                token_expiry INT DEFAULT 30,
                auth_key VARCHAR(255),
                cleanup_days INT DEFAULT 90,
                inactive_user_days INT DEFAULT 90,
                supported_countries TEXT,
                max_price_threshold DECIMAL(10,2) DEFAULT 10000,
                min_price_threshold DECIMAL(10,2) DEFAULT 1,
                last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $conn->query($createTable);
        }
        
        // Prepara i dati
        $systemEnabled = isset($data['system_enabled']) ? 1 : 0;
        $maxHistoryRecords = (int)$data['max_history_records'];
        $cacheTime = (int)$data['cache_time'];
        $outlierThreshold = (int)$data['outlier_threshold'];
        $rateLimitRequests = (int)$data['rate_limit_requests'];
        $rateLimitWindow = (int)$data['rate_limit_window'];
        $tokenExpiry = (int)$data['token_expiry'];
        $authKey = $data['auth_key'];
        $cleanupDays = (int)$data['cleanup_days'];
        $inactiveUserDays = (int)$data['inactive_user_days'];
        $supportedCountries = isset($data['supported_countries']) ? json_encode($data['supported_countries']) : json_encode(['it', 'de', 'fr', 'es', 'uk', 'us']);
        $maxPriceThreshold = (float)$data['max_price_threshold'];
        $minPriceThreshold = (float)$data['min_price_threshold'];
        
        // Controlla se ci sono già impostazioni
        $checkSettings = $conn->query("SELECT id FROM system_settings LIMIT 1");
        
        if ($checkSettings->num_rows === 0) {
            // Inserisci nuove impostazioni
            $sql = "INSERT INTO system_settings (
                system_enabled, max_history_records, cache_time, outlier_threshold, 
                rate_limit_requests, rate_limit_window, token_expiry, auth_key, 
                cleanup_days, inactive_user_days, supported_countries,
                max_price_threshold, min_price_threshold
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                'iiiiiissiisd', 
                $systemEnabled, $maxHistoryRecords, $cacheTime, $outlierThreshold,
                $rateLimitRequests, $rateLimitWindow, $tokenExpiry, $authKey,
                $cleanupDays, $inactiveUserDays, $supportedCountries,
                $maxPriceThreshold, $minPriceThreshold
            );
        } else {
            // Aggiorna impostazioni esistenti
            $sql = "UPDATE system_settings SET 
                system_enabled = ?, 
                max_history_records = ?, 
                cache_time = ?, 
                outlier_threshold = ?, 
                rate_limit_requests = ?, 
                rate_limit_window = ?, 
                token_expiry = ?, 
                auth_key = ?, 
                cleanup_days = ?, 
                inactive_user_days = ?, 
                supported_countries = ?,
                max_price_threshold = ?,
                min_price_threshold = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                'iiiiiissiisd', 
                $systemEnabled, $maxHistoryRecords, $cacheTime, $outlierThreshold,
                $rateLimitRequests, $rateLimitWindow, $tokenExpiry, $authKey,
                $cleanupDays, $inactiveUserDays, $supportedCountries,
                $maxPriceThreshold, $minPriceThreshold
            );
        }
        
        $result = $stmt->execute();
        
        // Aggiorna costanti in config.php se necessario
        // Questa è una funzionalità avanzata che richiederebbe permessi di scrittura sul file config.php
        
        return $result;
    } catch (Exception $e) {
        error_log("Error in saveSystemSettings: " . $e->getMessage());
        return false;
    }
}

// Funzioni di manutenzione

// Pulizia dati vecchi
function performCleanup() {
    try {
        $conn = Database::getConnection();
        $settings = getSystemSettings();
        $cleanupDays = $settings['cleanup_days'] ?? 90;
        $inactiveUserDays = $settings['inactive_user_days'] ?? 90;
        
        // Inizia transazione
        $conn->begin_transaction();
        
        // Elimina cronologia prezzi vecchia
        $sql = "DELETE FROM price_history WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $cleanupDays);
        $stmt->execute();
        $historyDeleted = $stmt->affected_rows;
        
        // Elimina utenti inattivi
        $sql = "DELETE FROM users WHERE last_login < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $inactiveUserDays);
        $stmt->execute();
        $usersDeleted = $stmt->affected_rows;
        
        // Elimina dati rate limiting vecchi
        $sql = "DELETE FROM rate_limits WHERE request_time < DATE_SUB(NOW(), INTERVAL 1 DAY)";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        
        // Commit transazione
        $conn->commit();
        
        // Log operazione
        error_log("Cleanup performed: $historyDeleted history records and $usersDeleted users deleted");
        
        return true;
    } catch (Exception $e) {
        // Rollback in caso di errore
        $conn->rollback();
        error_log("Error in performCleanup: " . $e->getMessage());
        return false;
    }
}

// Ottimizza tabelle
function optimizeTables() {
    try {
        $conn = Database::getConnection();
        
        $tables = [
            'prices', 'price_history', 'users', 'contributor_stats', 
            'rate_limits', 'system_settings'
        ];
        
        foreach ($tables as $table) {
            $sql = "OPTIMIZE TABLE $table";
            $conn->query($sql);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error in optimizeTables: " . $e->getMessage());
        return false;
    }
}

// Esporta dati
function generateDataExport() {
    try {
        $conn = Database::getConnection();
        
        $exportDir = __DIR__ . '/../exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "price_data_export_$timestamp.csv";
        $filepath = "$exportDir/$filename";
        
        // Apri file per scrittura
        $file = fopen($filepath, 'w');
        
        // Scrivi intestazione
        fputcsv($file, ['ASIN', 'Country', 'Price', 'Source', 'Confirmed', 'Timestamp', 'Client ID']);
        
        // Recupera dati
        $sql = "SELECT * FROM prices ORDER BY timestamp DESC";
        $result = $conn->query($sql);
        
        // Scrivi dati
        while ($row = $result->fetch_assoc()) {
            fputcsv($file, [
                $row['asin'],
                $row['country'],
                $row['price'],
                $row['source'],
                $row['confirmed'],
                $row['timestamp'],
                $row['client_id']
            ]);
        }
        
        fclose($file);
        
        // Restituisci URL per il download
        return "exports/$filename";
    } catch (Exception $e) {
        error_log("Error in generateDataExport: " . $e->getMessage());
        return false;
    }
}
?>