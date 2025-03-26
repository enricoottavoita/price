<?php
require_once 'includes/header.php';

// Carica impostazioni attuali
$settings = getSystemSettings();

// Gestione salvataggio impostazioni
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    // Valida e salva le impostazioni
    $result = saveSystemSettings($_POST);
    
    if ($result) {
        $successMessage = "Impostazioni salvate con successo.";
        // Ricarica impostazioni
        $settings = getSystemSettings();
    } else {
        $errorMessage = "Errore nel salvataggio delle impostazioni.";
    }
}

// Gestione azioni di manutenzione
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['maintenance_action'])) {
    $action = $_POST['maintenance_action'];
    
    switch ($action) {
        case 'cleanup_old_data':
            $result = performCleanup();
            $actionMessage = $result ? "Pulizia completata con successo." : "Errore durante la pulizia.";
            break;
            
        case 'optimize_tables':
            $result = optimizeTables();
            $actionMessage = $result ? "Ottimizzazione tabelle completata." : "Errore durante l'ottimizzazione.";
            break;
            
        case 'export_data':
            $exportUrl = generateDataExport();
            $actionMessage = $exportUrl ? "Esportazione completata. <a href='$exportUrl'>Scarica</a>" : "Errore durante l'esportazione.";
            break;
    }
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="card-title">Impostazioni Sistema</h2>
                <p class="text-muted">Gestione delle impostazioni del sistema di condivisione prezzi</p>
            </div>
        </div>
    </div>
</div>

<!-- Messaggi -->
<?php if (isset($successMessage)): ?>
<div class="alert alert-success"><?php echo h($successMessage); ?></div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
<div class="alert alert-danger"><?php echo h($errorMessage); ?></div>
<?php endif; ?>

<?php if (isset($actionMessage)): ?>
<div class="alert alert-info"><?php echo $actionMessage; ?></div>
<?php endif; ?>

<!-- Tabs per le diverse sezioni di impostazioni -->
<ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">
            <i class="bi bi-gear"></i> Generali
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab" aria-controls="security" aria-selected="false">
            <i class="bi bi-shield-lock"></i> Sicurezza
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance" type="button" role="tab" aria-controls="maintenance" aria-selected="false">
            <i class="bi bi-tools"></i> Manutenzione
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="custom-tab" data-bs-toggle="tab" data-bs-target="#custom" type="button" role="tab" aria-controls="custom" aria-selected="false">
            <i class="bi bi-sliders"></i> Personalizzazione
        </button>
    </li>
</ul>

<form method="post" action="">
    <div class="tab-content" id="settingsTabsContent">
        <!-- Impostazioni generali -->
        <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Impostazioni Generali</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="system_enabled" name="system_enabled" value="1" <?php echo isset($settings['system_enabled']) && $settings['system_enabled'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="system_enabled">Sistema Attivo</label>
                        </div>
                        <div class="form-text">Abilita o disabilita l'intero sistema di condivisione prezzi.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="max_history_records" class="form-label">Limite Cronologia Prezzi</label>
                        <input type="number" class="form-control" id="max_history_records" name="max_history_records" value="<?php echo h($settings['max_history_records'] ?? 50); ?>" min="10" max="500">
                        <div class="form-text">Numero massimo di record da conservare nella cronologia prezzi per ogni prodotto.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cache_time" class="form-label">Tempo Cache (secondi)</label>
                        <input type="number" class="form-control" id="cache_time" name="cache_time" value="<?php echo h($settings['cache_time'] ?? 300); ?>" min="60" max="86400">
                        <div class="form-text">Durata della cache per le risposte API in secondi.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="outlier_threshold" class="form-label">Soglia Outlier (%)</label>
                        <input type="number" class="form-control" id="outlier_threshold" name="outlier_threshold" value="<?php echo h($settings['outlier_threshold'] ?? 25); ?>" min="5" max="100">
                        <div class="form-text">Percentuale di deviazione oltre la quale un prezzo è considerato anomalo.</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Impostazioni sicurezza -->
        <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Impostazioni Sicurezza</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="rate_limit_requests" class="form-label">Limite Richieste</label>
                        <input type="number" class="form-control" id="rate_limit_requests" name="rate_limit_requests" value="<?php echo h($settings['rate_limit_requests'] ?? 100); ?>" min="10" max="1000">
                        <div class="form-text">Numero massimo di richieste consentite nel periodo di tempo.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="rate_limit_window" class="form-label">Periodo Rate Limit (secondi)</label>
                        <input type="number" class="form-control" id="rate_limit_window" name="rate_limit_window" value="<?php echo h($settings['rate_limit_window'] ?? 3600); ?>" min="60" max="86400">
                        <div class="form-text">Finestra temporale per il rate limiting in secondi.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="token_expiry" class="form-label">Scadenza Token (giorni)</label>
                        <input type="number" class="form-control" id="token_expiry" name="token_expiry" value="<?php echo h($settings['token_expiry'] ?? 30); ?>" min="1" max="90">
                        <div class="form-text">Durata di validità dei token di autenticazione in giorni.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="auth_key" class="form-label">Chiave di Autenticazione</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="auth_key" name="auth_key" value="<?php echo h($settings['auth_key'] ?? ''); ?>">
                            <button class="btn btn-outline-secondary" type="button" id="generateAuthKey">Genera Nuova</button>
                        </div>
                        <div class="form-text">Chiave utilizzata dagli script per autenticarsi al sistema.</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Manutenzione -->
        <div class="tab-pane fade" id="maintenance" role="tabpanel" aria-labelledby="maintenance-tab">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Manutenzione Database</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Pulizia Dati</h5>
                                    <p class="card-text">Rimuovi dati vecchi e non necessari dal database.</p>
                                    <button type="submit" name="maintenance_action" value="cleanup_old_data" class="btn btn-warning" onclick="return confirm('Sei sicuro di voler eseguire la pulizia dei dati?');">
                                        <i class="bi bi-trash"></i> Esegui Pulizia
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Ottimizzazione</h5>
                                    <p class="card-text">Ottimizza le tabelle del database per migliorare le prestazioni.</p>
                                    <button type="submit" name="maintenance_action" value="optimize_tables" class="btn btn-info" onclick="return confirm('Sei sicuro di voler ottimizzare le tabelle?');">
                                        <i class="bi bi-speedometer"></i> Ottimizza Tabelle
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Esportazione</h5>
                                    <p class="card-text">Esporta tutti i dati in formato CSV per backup.</p>
                                    <button type="submit" name="maintenance_action" value="export_data" class="btn btn-success">
                                        <i class="bi bi-download"></i> Esporta Dati
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cleanup_days" class="form-label">Giorni di Conservazione</label>
                        <input type="number" class="form-control" id="cleanup_days" name="cleanup_days" value="<?php echo h($settings['cleanup_days'] ?? 90); ?>" min="30" max="365">
                        <div class="form-text">Numero di giorni per cui conservare i dati nella cronologia prezzi.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="inactive_user_days" class="form-label">Giorni Inattività Utente</label>
                        <input type="number" class="form-control" id="inactive_user_days" name="inactive_user_days" value="<?php echo h($settings['inactive_user_days'] ?? 90); ?>" min="30" max="365">
                        <div class="form-text">Giorni dopo i quali considerare un utente inattivo.</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Personalizzazione -->
        <div class="tab-pane fade" id="custom" role="tabpanel" aria-labelledby="custom-tab">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Personalizzazione</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
    <label for="supported_countries" class="form-label">Paesi Supportati</label>
    <select class="form-select" id="supported_countries" name="supported_countries[]" multiple size="4">
        <option value="it" <?php echo in_array('it', $settings['supported_countries'] ?? []) ? 'selected' : ''; ?>>Italia (it)</option>
        <option value="de" <?php echo in_array('de', $settings['supported_countries'] ?? []) ? 'selected' : ''; ?>>Germania (de)</option>
        <option value="fr" <?php echo in_array('fr', $settings['supported_countries'] ?? []) ? 'selected' : ''; ?>>Francia (fr)</option>
        <option value="es" <?php echo in_array('es', $settings['supported_countries'] ?? []) ? 'selected' : ''; ?>>Spagna (es)</option>
    </select>
    <div class="form-text">Seleziona i paesi supportati dal sistema (tieni premuto CTRL per selezionare più paesi).</div>
</div>
                    
                    <div class="mb-3">
                        <label for="max_price_threshold" class="form-label">Soglia Massima Prezzo (€)</label>
                        <input type="number" class="form-control" id="max_price_threshold" name="max_price_threshold" value="<?php echo h($settings['max_price_threshold'] ?? 10000); ?>" min="100" max="100000">
                        <div class="form-text">Prezzo massimo accettato dal sistema.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="min_price_threshold" class="form-label">Soglia Minima Prezzo (€)</label>
                        <input type="number" class="form-control" id="min_price_threshold" name="min_price_threshold" value="<?php echo h($settings['min_price_threshold'] ?? 1); ?>" min="0.01" max="100" step="0.01">
                        <div class="form-text">Prezzo minimo accettato dal sistema.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pulsanti di salvataggio -->
    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
        <button type="reset" class="btn btn-secondary me-md-2">Ripristina</button>
        <button type="submit" name="save_settings" value="1" class="btn btn-primary">Salva Impostazioni</button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Genera chiave di autenticazione casuale
    document.getElementById('generateAuthKey').addEventListener('click', function() {
        const randomKey = generateRandomString(12);
        document.getElementById('auth_key').value = randomKey;
    });
    
    // Funzione per generare stringa casuale
    function generateRandomString(length) {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let result = '';
        for (let i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>