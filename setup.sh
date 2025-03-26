#!/bin/bash

# Script di installazione per Price API
# Autore: Assistant AI
# Data: 2023

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funzione per stampare messaggi di stato
print_status() {
    echo -e "${BLUE}[*] $1${NC}"
}

# Funzione per stampare messaggi di successo
print_success() {
    echo -e "${GREEN}[+] $1${NC}"
}

# Funzione per stampare messaggi di errore
print_error() {
    echo -e "${RED}[-] $1${NC}"
}

# Funzione per stampare messaggi di avviso
print_warning() {
    echo -e "${YELLOW}[!] $1${NC}"
}

# Funzione per controllare se un comando è disponibile
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Funzione per chiedere conferma
confirm() {
    read -r -p "${1:-Continuare? [s/N]} " response
    case "$response" in
        [sS][iI]|[sS]) 
            true
            ;;
        *)
            false
            ;;
    esac
}

# Funzione per generare una password hash PHP (per l'admin)
generate_password_hash() {
    local password="$1"
    php -r "echo password_hash('$password', PASSWORD_BCRYPT);"
}

# Controllo se l'utente è root
if [ "$(id -u)" != "0" ]; then
   print_error "Questo script deve essere eseguito come root" 1>&2
   exit 1
fi

# Banner
echo -e "${GREEN}"
echo "  _____      _            _    ____ ___ "
echo " |  __ \    (_)          | |  |  _ \_ _|"
echo " | |__) | __ _  ___ ___  | |  | |_) | | "
echo " |  ___/ '__| |/ __/ _ \ | |  |  __/| | "
echo " | |   | |  | | (_|  __/ |_|  |_|  |_| "
echo " |_|   |_|  |_|\___\___| (_)            "
echo -e "${NC}"
echo "Script di installazione automatica"
echo "--------------------------------"
echo ""

# Directory di installazione
INSTALL_DIR="/var/www/price-api"
TEMP_DIR=$(mktemp -d)
REPO_URL="https://github.com/enricoottavoita/price.git"

# Chiedi conferma per iniziare l'installazione
if ! confirm "Questo script installerà Price API e tutte le dipendenze necessarie. Continuare? [s/N]"; then
    print_warning "Installazione annullata dall'utente."
    exit 0
fi

# Step 1: Controllo dei prerequisiti
print_status "Controllo dei prerequisiti..."

# Controlla se git è installato
if ! command_exists git; then
    print_warning "Git non è installato. Installazione in corso..."
    apt-get update && apt-get install -y git || { 
        print_error "Impossibile installare git."; 
        exit 1; 
    }
fi

# Step 2: Aggiornamento del sistema
print_status "Aggiornamento dei repository del sistema..."
apt-get update || { print_error "Impossibile aggiornare i repository."; exit 1; }
print_success "Repository aggiornati."

# Step 3: Installazione delle dipendenze
print_status "Installazione delle dipendenze necessarie..."
apt-get install -y apache2 mariadb-server php php-mysql php-curl php-mbstring php-xml php-gd unzip || { 
    print_error "Impossibile installare le dipendenze."; 
    exit 1; 
}
print_success "Dipendenze installate correttamente."

# Step 4: Abilitazione dei moduli Apache necessari
print_status "Abilitazione dei moduli Apache necessari..."
a2enmod rewrite headers || { print_error "Impossibile abilitare i moduli Apache."; exit 1; }
print_success "Moduli Apache abilitati."

# Step 5: Avvio dei servizi
print_status "Avvio dei servizi..."
systemctl restart apache2 || { print_error "Impossibile avviare Apache."; exit 1; }
systemctl restart mysql || { print_error "Impossibile avviare MySQL/MariaDB."; exit 1; }
print_success "Servizi avviati correttamente."

# Step 6: Configurazione del database
print_status "Configurazione del database..."

# Chiedi i dettagli del database
read -p "Nome del database [price_api]: " DB_NAME
DB_NAME=${DB_NAME:-price_api}

read -p "Nome utente del database [price_user]: " DB_USER
DB_USER=${DB_USER:-price_user}

read -s -p "Password del database [generata automaticamente]: " DB_PASS
echo ""
if [ -z "$DB_PASS" ]; then
    DB_PASS=$(tr -dc 'A-Za-z0-9!#$%&()*+,-./:;<=>?@[\]^_`{|}~' </dev/urandom | head -c 16)
    print_warning "Password generata automaticamente: $DB_PASS"
    echo "Per favore, annota questa password."
fi

# Creazione del database e dell'utente
print_status "Creazione del database e dell'utente..."
mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
mysql -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"
print_success "Database e utente creati correttamente."

# Step 7: Configurazione delle credenziali dell'API e dell'admin
print_status "Configurazione delle credenziali dell'API e dell'admin..."

# Generazione delle chiavi di sicurezza
JWT_SECRET=$(tr -dc 'A-Za-z0-9!#$%&()*+,-./:;<=>?@[\]^_`{|}~' </dev/urandom | head -c 32)
AUTH_KEY=$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 16)

# Chiedi le credenziali admin o genera automaticamente
read -p "Nome utente admin [admin]: " ADMIN_USERNAME
ADMIN_USERNAME=${ADMIN_USERNAME:-admin}

read -s -p "Password admin [generata automaticamente]: " ADMIN_PASSWORD
echo ""
if [ -z "$ADMIN_PASSWORD" ]; then
    ADMIN_PASSWORD=$(tr -dc 'A-Za-z0-9!#$%&()*+,-./:;<=>?@[\]^_`{|}~' </dev/urandom | head -c 12)
    print_warning "Password admin generata automaticamente: $ADMIN_PASSWORD"
    echo "Per favore, annota questa password."
fi

read -p "Email admin [admin@example.com]: " ADMIN_EMAIL
ADMIN_EMAIL=${ADMIN_EMAIL:-admin@example.com}

# Genera l'hash della password per l'admin
ADMIN_PASSWORD_HASH=$(generate_password_hash "$ADMIN_PASSWORD")

print_success "Credenziali configurate correttamente."

# Step 8: Scaricamento del codice sorgente
print_status "Scaricamento del codice sorgente..."

# Chiedi se scaricare da GitHub o usare una directory locale
echo "Da dove vuoi ottenere i file del progetto?"
echo "1) Scarica da GitHub (pubblico)"
echo "2) Usa una directory locale"
read -p "Seleziona un'opzione [1]: " source_option
source_option=${source_option:-1}

if [ "$source_option" = "1" ]; then
    # Scarica da GitHub
    git clone "$REPO_URL" "$TEMP_DIR/price" || { 
        print_error "Impossibile scaricare il repository."; 
        rm -rf "$TEMP_DIR"; 
        exit 1; 
    }
    SOURCE_DIR="$TEMP_DIR/price"
    print_success "Repository scaricato correttamente."
else
    # Usa una directory locale
    read -p "Inserisci il percorso completo della directory del progetto: " SOURCE_DIR
    if [ ! -d "$SOURCE_DIR" ]; then
        print_error "Directory non trovata: $SOURCE_DIR"
        rm -rf "$TEMP_DIR"; 
        exit 1;
    fi
    print_success "Directory locale selezionata: $SOURCE_DIR"
fi

# Step 9: Creazione delle directory di installazione
print_status "Creazione delle directory di installazione..."
mkdir -p "$INSTALL_DIR" || { 
    print_error "Impossibile creare la directory di installazione."; 
    rm -rf "$TEMP_DIR"; 
    exit 1; 
}
print_success "Directory di installazione create."

# Step 10: Copia dei file
print_status "Copia dei file del progetto..."
cp -r "$SOURCE_DIR/price-api/"* "$INSTALL_DIR/" || { 
    print_error "Impossibile copiare i file del progetto."; 
    rm -rf "$TEMP_DIR"; 
    exit 1; 
}
print_success "File copiati correttamente."

# Step 11: Creazione della struttura del database
print_status "Creazione della struttura del database..."

# File SQL per la creazione delle tabelle
cat > "$TEMP_DIR/schema.sql" << 'EOL'
-- Tabella users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` varchar(50) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella tokens
CREATE TABLE IF NOT EXISTS `tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` text NOT NULL,
  `revoked` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `revoked_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella prices
CREATE TABLE IF NOT EXISTS `prices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asin` varchar(10) NOT NULL,
  `country` varchar(2) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `source` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `asin` (`asin`),
  KEY `country` (`country`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella api_logs
CREATE TABLE IF NOT EXISTS `api_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `endpoint` varchar(50) NOT NULL,
  `request_data` text DEFAULT NULL,
  `response_code` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `endpoint` (`endpoint`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella admin_users
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'admin',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella admin_logs
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella settings
CREATE TABLE IF NOT EXISTS `settings` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella rate_limits
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `endpoint` varchar(50) NOT NULL,
  `request_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ip_address` (`ip_address`),
  KEY `user_id` (`user_id`),
  KEY `request_time` (`request_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dati iniziali per la tabella settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_group`, `setting_type`, `setting_label`, `setting_description`, `is_public`) VALUES
('jwt_secret', 'CHANGEME_JWT_SECRET', 'api', 'password', 'JWT Secret Key', 'Chiave segreta per la generazione dei token JWT', 0),
('jwt_expiry', '86400', 'api', 'number', 'JWT Expiry Time', 'Tempo di scadenza dei token JWT in secondi', 0),
('auth_key', 'CHANGEME_AUTH_KEY', 'api', 'text', 'Auth Key', 'Chiave di autenticazione per le API', 0),
('site_name', 'Price API', 'general', 'text', 'Nome Sito', 'Nome del sito visualizzato nelle email e nell\'interfaccia', 1),
('admin_email', 'admin@example.com', 'general', 'email', 'Email Amministratore', 'Indirizzo email per le notifiche amministrative', 0),
('prices_per_page', '20', 'general', 'number', 'Prezzi per Pagina', 'Numero di prezzi da visualizzare per pagina nell\'admin', 0),
('max_login_attempts', '5', 'security', 'number', 'Tentativi Login Massimi', 'Numero massimo di tentativi di login falliti prima del blocco', 0),
('login_timeout', '15', 'security', 'number', 'Timeout Login (minuti)', 'Tempo di attesa dopo troppi tentativi falliti', 0),
('session_timeout', '30', 'security', 'number', 'Timeout Sessione (minuti)', 'Tempo di inattività prima che la sessione scada', 0),
('rate_limit_max', '1000', 'security', 'number', 'Limite Richieste API', 'Numero massimo di richieste API per ora per utente/IP', 0);
EOL

# Importazione del database
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$TEMP_DIR/schema.sql" || { 
    print_error "Impossibile importare lo schema del database."; 
    rm -rf "$TEMP_DIR"; 
    exit 1; 
}

# Aggiornamento delle impostazioni nel database
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "UPDATE settings SET setting_value = '$JWT_SECRET' WHERE setting_key = 'jwt_secret';"
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "UPDATE settings SET setting_value = '$AUTH_KEY' WHERE setting_key = 'auth_key';"
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "UPDATE settings SET setting_value = '$ADMIN_EMAIL' WHERE setting_key = 'admin_email';"

# Inserimento dell'utente admin
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "INSERT INTO admin_users (username, password, email, name, role, status) VALUES ('$ADMIN_USERNAME', '$ADMIN_PASSWORD_HASH', '$ADMIN_EMAIL', 'Administrator', 'admin', 1);"

print_success "Struttura del database creata correttamente."

# Step 12: Configurazione del file config.php
print_status "Configurazione del file config.php..."

# Ottieni l'URL base
read -p "URL base dell'applicazione (es. https://example.com/price-api): " BASE_URL
BASE_URL=${BASE_URL:-"http://localhost/price-api"}

# Crea il file config.php
cat > "$INSTALL_DIR/includes/config.php" << EOL
<?php
// File: includes/config.php

// Configurazione Database
define('DB_HOST', 'localhost');
define('DB_NAME', '$DB_NAME');
define('DB_USER', '$DB_USER');
define('DB_PASS', '$DB_PASS');

// Configurazione API
define('JWT_SECRET', '$JWT_SECRET'); // Generato automaticamente
define('JWT_EXPIRY', 86400); // 24 ore in secondi
define('AUTH_KEY', '$AUTH_KEY'); // Chiave di autenticazione per le API

// URL Base
define('BASE_URL', '$BASE_URL');

// Impostazioni Timezone
date_default_timezone_set('Europe/Rome');

// Gestione errori
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disattivato in produzione
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/errors.log');

// Directory di log
\$log_dir = __DIR__ . '/../logs';
if (!file_exists(\$log_dir)) {
    mkdir(\$log_dir, 0755, true);
}

// Funzioni helper
function clean_input(\$data) {
    \$data = trim(\$data);
    \$data = stripslashes(\$data);
    \$data = htmlspecialchars(\$data);
    return \$data;
}

// Headers CORS per le API
function set_api_headers() {
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    
    // Gestione richieste OPTIONS (preflight)
    if (\$_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        header('HTTP/1.1 200 OK');
        exit;
    }
}
?>
EOL

print_success "File config.php configurato correttamente."

# Step 13: Configurazione di Apache
print_status "Configurazione di Apache..."

# Chiedi se configurare un virtual host
read -p "Vuoi configurare un virtual host per l'applicazione? [s/N]: " configure_vhost
configure_vhost=${configure_vhost:-n}

if [[ "$configure_vhost" =~ ^[Ss]$ ]]; then
    # Chiedi il nome del dominio
    read -p "Inserisci il nome del dominio (es. price-api.example.com): " domain_name
    
    # Creazione del file di configurazione di Apache
    cat > "/etc/apache2/sites-available/price-api.conf" << EOL
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    ServerName $domain_name
    DocumentRoot $INSTALL_DIR
    
    <Directory $INSTALL_DIR>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/price-api_error.log
    CustomLog \${APACHE_LOG_DIR}/price-api_access.log combined
</VirtualHost>
EOL

    # Abilitazione del sito
    a2ensite price-api.conf || { 
        print_error "Impossibile abilitare il sito Apache."; 
        rm -rf "$TEMP_DIR"; 
        exit 1; 
    }
    
    print_success "Virtual host configurato per $domain_name."
    
    # Aggiorna l'URL base se necessario
    if [[ "$BASE_URL" == "http://localhost/price-api" ]]; then
        BASE_URL="http://$domain_name"
        # Aggiorna config.php
        sed -i "s|define('BASE_URL', 'http://localhost/price-api');|define('BASE_URL', '$BASE_URL');|" "$INSTALL_DIR/includes/config.php"
    fi
else
    # Configura l'applicazione in una sottodirectory
    print_status "Configurazione dell'applicazione in una sottodirectory..."
    
    # Verifica se la directory esiste già
    if [ ! -d "/var/www/html/price-api" ]; then
        # Crea un link simbolico
        ln -s "$INSTALL_DIR" "/var/www/html/price-api" || {
            print_error "Impossibile creare il link simbolico.";
            print_warning "Potresti dover copiare manualmente i file in /var/www/html/price-api"
        }
    else
        print_warning "La directory /var/www/html/price-api esiste già. Potresti dover copiare manualmente i file."
    fi
fi

# Riavvio di Apache
systemctl restart apache2 || { 
    print_error "Impossibile riavviare Apache."; 
    rm -rf "$TEMP_DIR"; 
    exit 1; 
}

print_success "Apache configurato correttamente."

# Step 14: Configurazione del firewall
print_status "Configurazione del firewall..."

# Verifica se ufw è installato
if ! command_exists ufw; then
    print_warning "UFW (Uncomplicated Firewall) non è installato. Installazione in corso..."
    apt-get update && apt-get install -y ufw || { 
        print_error "Impossibile installare UFW."; 
        exit 1; 
    }
fi

# Configura il firewall
print_status "Configurazione delle regole del firewall..."

# Abilita SSH per evitare di essere bloccati fuori
ufw allow ssh

# Abilita HTTP e HTTPS
ufw allow http
ufw allow https

# Abilita il firewall senza conferma
echo "y" | ufw enable

print_success "Firewall configurato correttamente."

# Step 15: Configurazione della sicurezza
print_status "Configurazione delle misure di sicurezza..."

# Crea il file api_security.php
mkdir -p "$INSTALL_DIR/includes/security" || { 
    print_error "Impossibile creare la directory di sicurezza."; 
    rm -rf "$TEMP_DIR"; 
    exit 1; 
}

cat > "$INSTALL_DIR/includes/security/api_security.php" << 'EOL'
<?php
/**
 * File per la sicurezza delle API che mantiene l'accesso aperto per gli userscript
 */

// Funzione per verificare se la richiesta proviene da un userscript
function is_userscript_request() {
    // Controlla l'User-Agent (molti userscript includono "Greasemonkey", "Tampermonkey", o "userscript" nell'header)
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    if (stripos($user_agent, 'Greasemonkey') !== false || 
        stripos($user_agent, 'Tampermonkey') !== false || 
        stripos($user_agent, 'userscript') !== false) {
        return true;
    }
    
    // Controlla un header personalizzato che lo userscript potrebbe inviare
    $headers = getallheaders();
    if (isset($headers['X-Requested-With']) && $headers['X-Requested-With'] == 'userscript') {
        return true;
    }
    
    // Nota: questa non è una verifica sicura al 100%, ma è un compromesso per mantenere l'API accessibile
    return false;
}

// Registra le richieste API per il monitoraggio
function log_api_request($user_id, $endpoint, $response_code, $request_data = null) {
    global $conn;
    
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        $stmt = $conn->prepare("INSERT INTO api_logs (user_id, endpoint, response_code, ip_address, user_agent, request_data) 
                               VALUES (:user_id, :endpoint, :response_code, :ip_address, :user_agent, :request_data)");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':endpoint', $endpoint);
        $stmt->bindParam(':response_code', $response_code);
        $stmt->bindParam(':ip_address', $ip_address);
        $stmt->bindParam(':user_agent', $user_agent);
        $stmt->bindParam(':request_data', $request_data);
        $stmt->execute();
    } catch (Exception $e) {
        // Ignora errori di logging per non bloccare le API
        error_log("Errore nel logging API: " . $e->getMessage());
    }
}

// Implementa un rate limiting base per prevenire abusi
function check_rate_limit($user_id, $endpoint) {
    global $conn;
    
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $time_ago = date('Y-m-d H:i:s', time() - 3600); // Ultima ora
        
        // Ottieni il limite configurato
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'rate_limit_max'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $max_requests = isset($result['setting_value']) ? intval($result['setting_value']) : 1000;
        
        // Conta le richieste recenti
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM rate_limits 
                               WHERE (ip_address = :ip OR user_id = :user_id) 
                               AND endpoint = :endpoint 
                               AND request_time > :time_ago");
        $stmt->bindParam(':ip', $ip_address);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':endpoint', $endpoint);
        $stmt->bindParam(':time_ago', $time_ago);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $count = $result['count'];
        
        // Se il limite non è superato, registra la richiesta
        if ($count < $max_requests) {
            $stmt = $conn->prepare("INSERT INTO rate_limits (ip_address, user_id, endpoint) 
                                   VALUES (:ip, :user_id, :endpoint)");
            $stmt->bindParam(':ip', $ip_address);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':endpoint', $endpoint);
            $stmt->execute();
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        // In caso di errore, consenti la richiesta
        error_log("Errore nel rate limiting: " . $e->getMessage());
        return true;
    }
}
?>
EOL

# Proteggi la directory includes con .htaccess
cat > "$INSTALL_DIR/includes/.htaccess" << 'EOL'
# Blocca tutti gli accessi diretti a questa directory
Deny from all
EOL

# Proteggi la directory security con .htaccess
cat > "$INSTALL_DIR/includes/security/.htaccess" << 'EOL'
# Blocca tutti gli accessi diretti a questa directory
Deny from all
EOL

# Proteggi la directory logs con .htaccess
mkdir -p "$INSTALL_DIR/logs" || { 
    print_error "Impossibile creare la directory logs."; 
    rm -rf "$TEMP_DIR"; 
    exit 1; 
}

cat > "$INSTALL_DIR/logs/.htaccess" << 'EOL'
# Blocca tutti gli accessi diretti a questa directory
Deny from all
EOL

# Aggiorna i file API per includere il controllo di sicurezza
for api_file in "$INSTALL_DIR/api/"*.php; do
    if [ -f "$api_file" ] && [ "$(basename "$api_file")" != "docs.php" ] && [ "$(basename "$api_file")" != "index.php" ]; then
        # Aggiungi l'inclusione del file di sicurezza dopo config.php
        if ! grep -q "security/api_security.php" "$api_file"; then
            sed -i '/require_once ..\\/includes\\/config.php/a require_once "../includes/security/api_security.php";' "$api_file"
        fi
    fi
done

# Aggiorna il file htaccess principale
cat > "$INSTALL_DIR/.htaccess" << 'EOL'
# Proteggi file e directory sensibili
<FilesMatch "^(\.htaccess|\.git|config\.php|database\.php)">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Proteggi le directory sensibili
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^includes/ - [F,L]
    RewriteRule ^logs/ - [F,L]
</IfModule>

# Imposta intestazioni di sicurezza
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-XSS-Protection "1; mode=block"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Imposta cache per file statici
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/pdf "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType application/x-javascript "access plus 1 month"
    ExpiresByType text/javascript "access plus 1 month"
    ExpiresByType text/x-javascript "access plus 1 month"
    ExpiresByType application/x-shockwave-flash "access plus 1 month"
    ExpiresByType image/x-icon "access plus 1 year"
    ExpiresDefault "access plus 2 days"
</IfModule>
EOL

# File .htaccess per la directory api
mkdir -p "$INSTALL_DIR/api" || { 
    print_error "Impossibile creare la directory api."; 
    rm -rf "$TEMP_DIR"; 
    exit 1; 
}

cat > "$INSTALL_DIR/api/.htaccess" << 'EOL'
# Abilita il motore di riscrittura
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Imposta le intestazioni CORS per le richieste OPTIONS (preflight)
    RewriteCond %{REQUEST_METHOD} OPTIONS
    RewriteRule ^(.*)$ $1 [R=200,L]
</IfModule>

# Imposta intestazioni CORS
<IfModule mod_headers.c>
    Header always set Access-Control-Allow-Origin "*"
    Header always set Access-Control-Allow-Methods "GET, POST, OPTIONS"
    Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"
    
    # Controlla se la richiesta è OPTIONS
    <If "%{REQUEST_METHOD} == 'OPTIONS'">
        # Invia 200 OK per le richieste OPTIONS
        Header always set Access-Control-Max-Age "86400"
        Header always set Content-Length "0"
        Header always set Content-Type "text/plain"
    </If>
</IfModule>

# Imposta il tipo di contenuto predefinito per le API
<IfModule mod_mime.c>
    AddType application/json .json
    AddDefaultCharset UTF-8
</IfModule>

# Disabilita l'elenco dei file
Options -Indexes
EOL

print_success "Sicurezza configurata correttamente."

# Step 16: Hardening PHP
print_status "Configurazione delle impostazioni di sicurezza PHP..."

# Trova il file php.ini
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
PHP_INI_PATHS=(
    "/etc/php/$PHP_VERSION/apache2/php.ini"
    "/etc/php/$PHP_VERSION/fpm/php.ini"
    "/etc/php/$PHP_VERSION/cli/php.ini"
    "/etc/php.ini"
)

PHP_INI=""
for path in "${PHP_INI_PATHS[@]}"; do
    if [ -f "$path" ]; then
        PHP_INI="$path"
        break
    fi
done

if [ -n "$PHP_INI" ]; then
    print_status "Modificando il file PHP.ini: $PHP_INI"
    
    # Crea un backup del file originale
    cp "$PHP_INI" "$PHP_INI.bak"
    
    # Disabilita funzioni pericolose
    if grep -q "^disable_functions" "$PHP_INI"; then
        sed -i 's/^disable_functions.*/disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source,eval/' "$PHP_INI"
    else
        echo "disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source,eval" >> "$PHP_INI"
    fi
    
    # Disabilita l'esposizione della versione PHP
    if grep -q "^expose_php" "$PHP_INI"; then
        sed -i 's/^expose_php.*/expose_php = Off/' "$PHP_INI"
    else
        echo "expose_php = Off" >> "$PHP_INI"
    fi
    
    # Riavvia Apache per applicare le modifiche
    systemctl restart apache2
    
    print_success "Configurazione PHP completata."
else
    print_warning "Impossibile trovare il file php.ini. Le impostazioni di sicurezza PHP non sono state configurate."
fi

# Step 17: Configurazione dei permessi
print_status "Configurazione dei permessi..."

# Imposta i permessi corretti
chown -R www-data:www-data "$INSTALL_DIR" || { 
    print_error "Impossibile impostare i permessi."; 
    rm -rf "$TEMP_DIR"; 
    exit 1; 
}
chmod -R 755 "$INSTALL_DIR" || { 
    print_error "Impossibile impostare i permessi."; 
    rm -rf "$TEMP_DIR"; 
    exit 1; 
}

# Permessi speciali per directory sensibili
chmod -R 750 "$INSTALL_DIR/includes" || { 
    print_warning "Impossibile impostare permessi speciali per la directory includes."; 
}
chmod -R 750 "$INSTALL_DIR/logs" || { 
    print_warning "Impossibile impostare permessi speciali per la directory logs."; 
}

print_success "Permessi configurati correttamente."

# Step 18: Creazione di un utente API di prova
print_status "Creazione di un utente API di prova..."

# Genera un client ID casuale
CLIENT_ID=$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 10)

# Inserisci l'utente di prova nel database
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "INSERT INTO users (client_id, status, notes) VALUES ('$CLIENT_ID', 1, 'Utente di prova creato durante l\'installazione');"

print_success "Utente API di prova creato con Client ID: $CLIENT_ID"

# Step 19: Pulizia e completamento
print_status "Pulizia e completamento dell'installazione..."

# Rimuovi la directory temporanea
rm -rf "$TEMP_DIR" || { 
    print_warning "Impossibile rimuovere la directory temporanea."; 
}

# Informazioni finali
print_success "Installazione completata con successo!"
echo ""
echo "=================================================================="
echo "                    INFORMAZIONI IMPORTANTI                        "
echo "=================================================================="
echo "URL dell'applicazione: $BASE_URL"
echo "Interfaccia admin: $BASE_URL/admin/"
echo "Documentazione API: $BASE_URL/api/docs.php"
echo ""
echo "Credenziali database:"
echo "  Nome database: $DB_NAME"
echo "  Utente database: $DB_USER"
echo "  Password database: $DB_PASS"
echo ""
echo "Credenziali admin:"
echo "  Username: $ADMIN_USERNAME"
echo "  Password: $ADMIN_PASSWORD"
echo ""
echo "Credenziali API:"
echo "  Client ID: $CLIENT_ID"
echo "  Auth Key: $AUTH_KEY"
echo ""
echo "IMPORTANTE: Conserva queste informazioni in un luogo sicuro!"
echo "=================================================================="
echo ""
echo "Per utilizzare l'API con lo userscript, aggiorna le credenziali nel tuo script"
echo "con il Client ID e l'Auth Key generati."
echo ""
echo "Il firewall è stato configurato per consentire solo traffico SSH, HTTP e HTTPS."
echo "L'API è accessibile a tutti gli userscript, mentre i file sensibili sono protetti."
echo ""
echo "Grazie per aver installato Price API!"

# Fine dello script
exit 0