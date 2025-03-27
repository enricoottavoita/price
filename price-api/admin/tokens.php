<?php
session_start();

// Verifica se l'utente è loggato
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Includi file di configurazione e database con percorsi assoluti
require_once '/var/www/price-api/includes/config.php';
require_once '/var/www/price-api/includes/database.php';

// Connessione al database
$db = new Database();
$conn = $db->getConnection();

// Conta i client con token duplicati
$stmt = $conn->query("
    SELECT COUNT(DISTINCT u.client_id) as duplicate_count
    FROM users u
    JOIN tokens t ON u.id = t.user_id
    WHERE t.revoked = 0 AND t.expires_at > NOW()
    GROUP BY u.client_id
    HAVING COUNT(t.id) > 1
");

$duplicate_count = $stmt->fetchColumn() ?: 0;

// Genera un token CSRF se non esiste
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Gestione revoca token
if (isset($_GET['revoke']) && is_numeric($_GET['revoke'])) {
    $id = $_GET['revoke'];
    
    // Verifica token CSRF
    if (isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
        try {
            $stmt = $conn->prepare("UPDATE tokens SET revoked = 1, revoked_at = NOW() WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $_SESSION['message'] = "Token revocato con successo.";
            $_SESSION['message_type'] = "success";
        } catch (PDOException $e) {
            $_SESSION['message'] = "Errore nella revoca del token: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Token di sicurezza non valido.";
        $_SESSION['message_type'] = "danger";
    }
    
    header('Location: tokens.php');
    exit;
}

// Gestione eliminazione token
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Verifica token CSRF
    if (isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
        try {
            $stmt = $conn->prepare("DELETE FROM tokens WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $_SESSION['message'] = "Token eliminato con successo.";
            $_SESSION['message_type'] = "success";
        } catch (PDOException $e) {
            $_SESSION['message'] = "Errore nell'eliminazione del token: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Token di sicurezza non valido.";
        $_SESSION['message_type'] = "danger";
    }
    
    header('Location: tokens.php');
    exit;
}

// Impostazioni paginazione
$records_per_page = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Filtri
$client_id_filter = isset($_GET['client_id']) ? trim($_GET['client_id']) : '';
$status_filter = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;

// Costruisci la query di base
$query = "SELECT t.*, u.client_id FROM tokens t LEFT JOIN users u ON t.user_id = u.id WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM tokens t LEFT JOIN users u ON t.user_id = u.id WHERE 1=1";
$params = [];

// Aggiungi filtri se presenti
if (!empty($client_id_filter)) {
    $query .= " AND u.client_id LIKE :client_id";
    $count_query .= " AND u.client_id LIKE :client_id";
    $params[':client_id'] = "%$client_id_filter%";
}

if ($status_filter !== null) {
    if ($status_filter == 1) {
        // Attivi (non revocati e non scaduti)
        $query .= " AND t.revoked = 0 AND (t.expires_at > NOW() OR t.expires_at IS NULL)";
        $count_query .= " AND t.revoked = 0 AND (t.expires_at > NOW() OR t.expires_at IS NULL)";
    } else {
        // Inattivi (revocati o scaduti)
        $query .= " AND (t.revoked = 1 OR t.expires_at <= NOW())";
        $count_query .= " AND (t.revoked = 1 OR t.expires_at <= NOW())";
    }
}

// Aggiungi ordinamento e limiti
$query .= " ORDER BY t.created_at DESC LIMIT :offset, :limit";

// Esegui query per conteggio totale
$stmt = $conn->prepare($count_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Esegui query principale
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
$stmt->execute();
$tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Titolo pagina
$page_title = 'Gestione Token';

// Includi header con percorso assoluto
include '/var/www/price-api/admin/includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">
        Gestione Token
        <?php if ($duplicate_count > 0): ?>
            <a href="cleanup_tokens.php" class="badge bg-warning text-decoration-none ms-2">
                <?php echo $duplicate_count; ?> client con token duplicati
            </a>
        <?php endif; ?>
    </h1>

    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-search me-1"></i>
                    Filtra Token
                </div>
                <div class="card-body">
                    <form method="get" action="tokens.php" class="row g-3">
                        <div class="col-md-6">
                            <label for="client_id" class="form-label">ID Client</label>
                            <input type="text" class="form-control" id="client_id" name="client_id" value="<?php echo htmlspecialchars($client_id_filter); ?>" placeholder="Inserisci ID client">
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Stato</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Tutti gli stati</option>
                                <option value="1" <?php echo $status_filter === 1 ? 'selected' : ''; ?>>Attivo</option>
                                <option value="0" <?php echo $status_filter === 0 ? 'selected' : ''; ?>>Inattivo</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="bi bi-search"></i> Filtra
                            </button>
                            <a href="tokens.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-info-circle me-1"></i>
                    Statistiche Token
                </div>
                <div class="card-body">
                    <?php
                    // Ottieni statistiche token
                    $stmt = $conn->query("SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN revoked = 0 AND (expires_at > NOW() OR expires_at IS NULL) THEN 1 ELSE 0 END) as active,
                        SUM(CASE WHEN revoked = 1 THEN 1 ELSE 0 END) as revoked,
                        SUM(CASE WHEN revoked = 0 AND expires_at <= NOW() AND expires_at IS NOT NULL THEN 1 ELSE 0 END) as expired
                    FROM tokens");
                    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                    ?>
                    <div class="row text-center">
                        <div class="col-3">
                            <h3 class="text-primary"><?php echo number_format($stats['total']); ?></h3>
                            <p class="text-muted mb-0">Totali</p>
                        </div>
                        <div class="col-3">
                            <h3 class="text-success"><?php echo number_format($stats['active']); ?></h3>
                            <p class="text-muted mb-0">Attivi</p>
                        </div>
                        <div class="col-3">
                            <h3 class="text-danger"><?php echo number_format($stats['revoked']); ?></h3>
                            <p class="text-muted mb-0">Revocati</p>
                        </div>
                        <div class="col-3">
                            <h3 class="text-warning"><?php echo number_format($stats['expired']); ?></h3>
                            <p class="text-muted mb-0">Scaduti</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-3 text-end">
        <a href="cleanup_tokens.php" class="btn btn-warning btn-sm">
            <i class="fas fa-broom me-1"></i> Pulizia Token Duplicati
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-key me-1"></i>
                    Elenco Token
                </div>
                <div>
                    <span class="badge bg-info"><?php echo number_format($total_records); ?> Risultati</span>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($tokens) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client ID</th>
                                <th>Stato</th>
                                <th>Data Creazione</th>
                                <th>Scadenza</th>
                                <th>Data Revoca</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tokens as $token): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($token['id']); ?></td>
                                    <td><?php echo htmlspecialchars($token['client_id'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($token['revoked'] == 1): ?>
                                            <span class="badge bg-danger">Revocato</span>
                                        <?php elseif ($token['expires_at'] && strtotime($token['expires_at']) < time()): ?>
                                            <span class="badge bg-warning text-dark">Scaduto</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Attivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($token['created_at'])); ?></td>
                                    <td>
                                        <?php 
                                        if ($token['expires_at']) {
                                            echo date('d/m/Y H:i', strtotime($token['expires_at']));
                                            
                                            // Calcola tempo rimanente per token attivi
                                            if ($token['revoked'] == 0 && strtotime($token['expires_at']) > time()) {
                                                $remaining = strtotime($token['expires_at']) - time();
                                                $days = floor($remaining / 86400);
                                                $hours = floor(($remaining % 86400) / 3600);
                                                
                                                echo ' <span class="text-muted">(' . $days . 'g ' . $hours . 'h)</span>';
                                            }
                                        } else {
                                            echo 'Mai';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo $token['revoked_at'] ? date('d/m/Y H:i', strtotime($token['revoked_at'])) : '-'; ?>
                                    </td>
                                    <td>
                                        <?php if ($token['revoked'] == 0 && (!$token['expires_at'] || strtotime($token['expires_at']) > time())): ?>
                                            <a href="tokens.php?revoke=<?php echo $token['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-sm btn-warning me-1 confirm-action" data-bs-toggle="tooltip" title="Revoca">
                                                <i class="bi bi-slash-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="tokens.php?delete=<?php echo $token['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-sm btn-danger confirm-action" data-bs-toggle="tooltip" title="Elimina">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1<?php echo !empty($client_id_filter) ? '&client_id='.urlencode($client_id_filter) : ''; ?><?php echo $status_filter !== null ? '&status='.$status_filter : ''; ?>" aria-label="First">
                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($client_id_filter) ? '&client_id='.urlencode($client_id_filter) : ''; ?><?php echo $status_filter !== null ? '&status='.$status_filter : ''; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            // Calcola l'intervallo di pagine da mostrare
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            // Assicurati di mostrare sempre 5 pagine se possibile
                            if ($end_page - $start_page < 4) {
                                if ($start_page == 1) {
                                    $end_page = min($total_pages, $start_page + 4);
                                } elseif ($end_page == $total_pages) {
                                    $start_page = max(1, $end_page - 4);
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($client_id_filter) ? '&client_id='.urlencode($client_id_filter) : ''; ?><?php echo $status_filter !== null ? '&status='.$status_filter : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($client_id_filter) ? '&client_id='.urlencode($client_id_filter) : ''; ?><?php echo $status_filter !== null ? '&status='.$status_filter : ''; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($client_id_filter) ? '&client_id='.urlencode($client_id_filter) : ''; ?><?php echo $status_filter !== null ? '&status='.$status_filter : ''; ?>" aria-label="Last">
                                        <span aria-hidden="true">&raquo;&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle me-2"></i>
                    Nessun token trovato. <?php echo !empty($client_id_filter) || $status_filter !== null ? 'Prova a modificare i filtri di ricerca.' : ''; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '/var/www/price-api/admin/includes/footer.php'; ?>