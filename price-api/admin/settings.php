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

// Connessione al database
$db = new Database();
$conn = $db->getConnection();

// Genera un token CSRF se non esiste
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Crea tabella settings se non esiste
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `setting_key` varchar(255) NOT NULL,
        `setting_value` text NOT NULL,
        `setting_group` varchar(100) NOT NULL DEFAULT 'general',
        `setting_type` varchar(50) NOT NULL DEFAULT 'text',
        `setting_label` varchar(255) NOT NULL,
        `setting_description` text DEFAULT NULL,
        `is_public` tinyint(1) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Verifica se ci sono impostazioni, altrimenti inserisci quelle predefinite
    $stmt = $conn->query("SELECT COUNT(*) FROM settings");
    if ($stmt->fetchColumn() == 0) {
        // Inserisci impostazioni predefinite
        $default_settings = [
            // Gruppo API
            ['jwt_secret', defined('JWT_SECRET') ? JWT_SECRET : bin2hex(random_bytes(32)), 'api', 'password', 'JWT Secret Key', 'Chiave segreta per la generazione dei token JWT', 0],
            ['jwt_expiry', defined('JWT_EXPIRY') ? JWT_EXPIRY : 86400, 'api', 'number', 'JWT Expiry Time', 'Tempo di scadenza dei token JWT in secondi', 0],
            ['auth_key', defined('AUTH_KEY') ? AUTH_KEY : 'FQGGHSTdxc', 'api', 'text', 'Auth Key', 'Chiave di autenticazione per le API', 0],
            
            // Gruppo Generale
            ['site_name', 'Price API', 'general', 'text', 'Nome Sito', 'Nome del sito visualizzato nelle email e nell\'interfaccia', 1],
            ['admin_email', 'admin@example.com', 'general', 'email', 'Email Amministratore', 'Indirizzo email per le notifiche amministrative', 0],
            ['prices_per_page', '20', 'general', 'number', 'Prezzi per Pagina', 'Numero di prezzi da visualizzare per pagina nell\'admin', 0],
            
            // Gruppo Sicurezza
            ['max_login_attempts', '5', 'security', 'number', 'Tentativi Login Massimi', 'Numero massimo di tentativi di login falliti prima del blocco', 0],
            ['login_timeout', '15', 'security', 'number', 'Timeout Login (minuti)', 'Tempo di attesa dopo troppi tentativi falliti', 0],
            ['session_timeout', '30', 'security', 'number', 'Timeout Sessione (minuti)', 'Tempo di inattività prima che la sessione scada', 0]
        ];
        
        $insert_stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_group, setting_type, setting_label, setting_description, is_public) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($default_settings as $setting) {
            $insert_stmt->execute($setting);
        }
    }
} catch (PDOException $e) {
    $_SESSION['message'] = "Errore nella creazione della tabella settings: " . $e->getMessage();
    $_SESSION['message_type'] = "danger";
}

// Gestione salvataggio impostazioni
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    // Verifica token CSRF
    if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
        try {
            // Gruppo attivo
            $active_group = isset($_POST['active_group']) ? $_POST['active_group'] : 'general';
            
            // Ottieni tutte le impostazioni del gruppo attivo
            $stmt = $conn->prepare("SELECT setting_key, setting_type FROM settings WHERE setting_group = :group");
            $stmt->bindParam(':group', $active_group);
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Aggiorna ciascuna impostazione
            $update_stmt = $conn->prepare("UPDATE settings SET setting_value = :value WHERE setting_key = :key");
            
            foreach ($settings as $setting) {
                $key = $setting['setting_key'];
                if (isset($_POST[$key])) {
                    $value = $_POST[$key];
                    
                    // Validazione in base al tipo
                    switch ($setting['setting_type']) {
                        case 'number':
                            if (!is_numeric($value)) {
                                throw new Exception("Il valore per '{$key}' deve essere un numero.");
                            }
                            break;
                        case 'email':
                            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                throw new Exception("Il valore per '{$key}' deve essere un'email valida.");
                            }
                            break;
                        case 'url':
                            if (!filter_var($value, FILTER_VALIDATE_URL)) {
                                throw new Exception("Il valore per '{$key}' deve essere un URL valido.");
                            }
                            break;
                    }
                    
                    // Aggiorna il valore
                    $update_stmt->bindParam(':value', $value);
                    $update_stmt->bindParam(':key', $key);
                    $update_stmt->execute();
                }
            }
            
            $_SESSION['message'] = "Impostazioni aggiornate con successo.";
            $_SESSION['message_type'] = "success";
        } catch (Exception $e) {
            $_SESSION['message'] = "Errore nell'aggiornamento delle impostazioni: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Token di sicurezza non valido.";
        $_SESSION['message_type'] = "danger";
    }
    
    // Reindirizza per evitare riinvio del form
    header("Location: settings.php?group=" . urlencode($active_group));
    exit;
}

// Gruppo attivo
$active_group = isset($_GET['group']) ? $_GET['group'] : 'general';

// Ottieni tutti i gruppi disponibili
$stmt = $conn->query("SELECT DISTINCT setting_group FROM settings");
$groups = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Ottieni le impostazioni del gruppo attivo
$stmt = $conn->prepare("SELECT * FROM settings WHERE setting_group = :group ORDER BY id");
$stmt->bindParam(':group', $active_group);
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Titolo pagina
$page_title = 'Impostazioni';

// Includi header
include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row">
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-list me-1"></i>
                    Categorie
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($groups as $group): ?>
                            <a href="settings.php?group=<?php echo urlencode($group); ?>" class="list-group-item list-group-item-action <?php echo $group === $active_group ? 'active' : ''; ?>">
                                <?php 
                                $icon = 'gear';
                                switch ($group) {
                                    case 'api':
                                        $icon = 'cloud';
                                        $group_name = 'API';
                                        break;
                                    case 'security':
                                        $icon = 'shield-lock';
                                        $group_name = 'Sicurezza';
                                        break;
                                    case 'general':
                                        $icon = 'sliders';
                                        $group_name = 'Generale';
                                        break;
                                    default:
                                        $group_name = ucfirst($group);
                                }
                                ?>
                                <i class="bi bi-<?php echo $icon; ?> me-2"></i>
                                <?php echo $group_name; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-9">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-gear me-1"></i>
                    <?php 
                    switch ($active_group) {
                        case 'api':
                            echo 'Impostazioni API';
                            break;
                        case 'security':
                            echo 'Impostazioni di Sicurezza';
                            break;
                        case 'general':
                            echo 'Impostazioni Generali';
                            break;
                        default:
                            echo 'Impostazioni ' . ucfirst($active_group);
                    }
                    ?>
                </div>
                <div class="card-body">
                    <?php if (count($settings) > 0): ?>
                        <form method="post" action="settings.php" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="active_group" value="<?php echo htmlspecialchars($active_group); ?>">
                            <input type="hidden" name="save_settings" value="1">
                            
                            <?php foreach ($settings as $setting): ?>
                                <div class="mb-3">
                                    <label for="<?php echo htmlspecialchars($setting['setting_key']); ?>" class="form-label">
                                        <?php echo htmlspecialchars($setting['setting_label']); ?>
                                    </label>
                                    
                                    <?php switch ($setting['setting_type']): 
                                        case 'textarea': ?>
                                            <textarea class="form-control" id="<?php echo htmlspecialchars($setting['setting_key']); ?>" name="<?php echo htmlspecialchars($setting['setting_key']); ?>" rows="3"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                        <?php break; ?>
                                        
                                        <?php case 'boolean': ?>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" id="<?php echo htmlspecialchars($setting['setting_key']); ?>" name="<?php echo htmlspecialchars($setting['setting_key']); ?>" value="1" <?php echo $setting['setting_value'] == '1' ? 'checked' : ''; ?>>
                                            </div>
                                        <?php break; ?>
                                        
                                        <?php case 'password': ?>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="<?php echo htmlspecialchars($setting['setting_key']); ?>" name="<?php echo htmlspecialchars($setting['setting_key']); ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="<?php echo htmlspecialchars($setting['setting_key']); ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        <?php break; ?>
                                        
                                        <?php case 'select': 
                                            $options = explode(',', $setting['setting_options'] ?? ''); ?>
                                            <select class="form-select" id="<?php echo htmlspecialchars($setting['setting_key']); ?>" name="<?php echo htmlspecialchars($setting['setting_key']); ?>">
                                                <?php foreach ($options as $option): 
                                                    $option = trim($option); ?>
                                                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $setting['setting_value'] == $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php break; ?>
                                        
                                        <?php default: ?>
                                            <input type="<?php echo $setting['setting_type'] == 'number' ? 'number' : 'text'; ?>" class="form-control" id="<?php echo htmlspecialchars($setting['setting_key']); ?>" name="<?php echo htmlspecialchars($setting['setting_key']); ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>" <?php echo $setting['setting_type'] == 'number' ? 'step="any"' : ''; ?>>
                                    <?php endswitch; ?>
                                    
                                    <?php if (!empty($setting['setting_description'])): ?>
                                        <div class="form-text"><?php echo htmlspecialchars($setting['setting_description']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i> Salva Impostazioni
                                </button>
                                <a href="settings.php?group=<?php echo urlencode($active_group); ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                                </a>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info" role="alert">
                            <i class="bi bi-info-circle me-2"></i>
                            Nessuna impostazione trovata per questa categoria.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(function(button) {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            
            if (input.type === 'password') {
                input.type = 'text';
                this.innerHTML = '<i class="bi bi-eye-slash"></i>';
            } else {
                input.type = 'password';
                this.innerHTML = '<i class="bi bi-eye"></i>';
            }
        });
    });
    
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});
</script>

<?php include 'includes/footer.php'; ?>