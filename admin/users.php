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

// Gestione eliminazione utente
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Verifica token CSRF
    if (isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
        try {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $_SESSION['message'] = "Utente eliminato con successo.";
            $_SESSION['message_type'] = "success";
        } catch (PDOException $e) {
            $_SESSION['message'] = "Errore nell'eliminazione dell'utente: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Token di sicurezza non valido.";
        $_SESSION['message_type'] = "danger";
    }
    
    header('Location: users.php');
    exit;
}

// Gestione aggiunta/modifica utente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica token CSRF
    if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
        $client_id = isset($_POST['client_id']) ? trim($_POST['client_id']) : '';
        $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        try {
            if ($id > 0) {
                // Aggiorna utente esistente
                $stmt = $conn->prepare("UPDATE users SET status = :status, notes = :notes WHERE id = :id");
                $stmt->bindParam(':status', $status, PDO::PARAM_INT);
                $stmt->bindParam(':notes', $notes);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                
                $_SESSION['message'] = "Utente aggiornato con successo.";
                $_SESSION['message_type'] = "success";
            } else {
                // Verifica se client_id esiste già
                $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE client_id = :client_id");
                $stmt->bindParam(':client_id', $client_id);
                $stmt->execute();
                if ($stmt->fetchColumn() > 0) {
                    $_SESSION['message'] = "L'ID client esiste già.";
                    $_SESSION['message_type'] = "danger";
                } else {
                    // Crea nuovo utente
                    $stmt = $conn->prepare("INSERT INTO users (client_id, status, notes, created_at) VALUES (:client_id, :status, :notes, NOW())");
                    $stmt->bindParam(':client_id', $client_id);
                    $stmt->bindParam(':status', $status, PDO::PARAM_INT);
                    $stmt->bindParam(':notes', $notes);
                    $stmt->execute();
                    
                    $_SESSION['message'] = "Nuovo utente creato con successo.";
                    $_SESSION['message_type'] = "success";
                }
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Errore nel salvataggio dell'utente: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Token di sicurezza non valido.";
        $_SESSION['message_type'] = "danger";
    }
    
    header('Location: users.php');
    exit;
}

// Carica utente per modifica
$user = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = $_GET['edit'];
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['message'] = "Utente non trovato.";
        $_SESSION['message_type'] = "danger";
        header('Location: users.php');
        exit;
    }
}

// Impostazioni paginazione
$records_per_page = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Filtri
$client_id_filter = isset($_GET['client_id']) ? trim($_GET['client_id']) : '';
$status_filter = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;

// Costruisci la query di base
$query = "SELECT * FROM users WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM users WHERE 1=1";
$params = [];

// Aggiungi filtri se presenti
if (!empty($client_id_filter)) {
    $query .= " AND client_id LIKE :client_id";
    $count_query .= " AND client_id LIKE :client_id";
    $params[':client_id'] = "%$client_id_filter%";
}

if ($status_filter !== null) {
    $query .= " AND status = :status";
    $count_query .= " AND status = :status";
    $params[':status'] = $status_filter;
}

// Aggiungi ordinamento e limiti
$query .= " ORDER BY created_at DESC LIMIT :offset, :limit";

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
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Titolo pagina
$page_title = 'Gestione Utenti';

// Includi header
include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-search me-1"></i>
                            Filtra Utenti
                        </div>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
                            <i class="bi bi-plus-circle"></i> Nuovo Utente
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form method="get" action="users.php" class="row g-3">
                        <div class="col-md-6">
                            <label for="client_id" class="form-label">ID Client</label>
                            <input type="text" class="form-control" id="client_id" name="client_id" value="<?php echo htmlspecialchars($client_id_filter); ?>" placeholder="Inserisci ID client">
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Stato</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Tutti gli stati</option>
                                <option value="1" <?php echo $status_filter === 1 ? 'selected' : ''; ?>>Attivo</option>
                                <option value="0" <?php echo $status_filter === 0 ? 'selected' : ''; ?>>Disattivato</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="bi bi-search"></i> Filtra
                            </button>
                            <a href="users.php" class="btn btn-secondary">
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
                    Statistiche Utenti
                </div>
                <div class="card-body">
                    <?php
                    // Ottieni statistiche utenti
                    $stmt = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active FROM users");
                    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Ottieni utenti recenti
                    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                    $recent = $stmt->fetch(PDO::FETCH_ASSOC);
                    ?>
                    <div class="row text-center">
                        <div class="col-4">
                            <h3 class="text-primary"><?php echo number_format($stats['total']); ?></h3>
                            <p class="text-muted mb-0">Totali</p>
                        </div>
                        <div class="col-4">
                            <h3 class="text-success"><?php echo number_format($stats['active']); ?></h3>
                            <p class="text-muted mb-0">Attivi</p>
                        </div>
                        <div class="col-4">
                            <h3 class="text-info"><?php echo number_format($recent['count']); ?></h3>
                            <p class="text-muted mb-0">Nuovi (7g)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-people me-1"></i>
                    Elenco Utenti
                </div>
                <div>
                    <span class="badge bg-info"><?php echo number_format($total_records); ?> Risultati</span>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($users) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client ID</th>
                                <th>Stato</th>
                                <th>Data Registrazione</th>
                                <th>Ultimo Accesso</th>
                                <th>Note</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($u['id']); ?></td>
                                    <td><?php echo htmlspecialchars($u['client_id']); ?></td>
                                    <td>
                                        <?php if ($u['status'] == 1): ?>
                                            <span class="badge bg-success">Attivo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Disattivato</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($u['created_at'])); ?></td>
                                    <td>
                                        <?php echo $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : 'Mai'; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($u['notes'])) {
                                            echo strlen($u['notes']) > 30 
                                                ? htmlspecialchars(substr($u['notes'], 0, 30)) . '...' 
                                                : htmlspecialchars($u['notes']);
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="users.php?edit=<?php echo $u['id']; ?>" class="btn btn-sm btn-primary me-1" data-bs-toggle="tooltip" title="Modifica">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="users.php?delete=<?php echo $u['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-sm btn-danger confirm-action" data-bs-toggle="tooltip" title="Elimina">
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
                    Nessun utente trovato. <?php echo !empty($client_id_filter) || $status_filter !== null ? 'Prova a modificare i filtri di ricerca.' : ''; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal per aggiunta/modifica utente -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="users.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="id" value="<?php echo $user ? $user['id'] : ''; ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel"><?php echo $user ? 'Modifica Utente' : 'Nuovo Utente'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_client_id" class="form-label">ID Client</label>
                        <input type="text" class="form-control" id="modal_client_id" name="client_id" value="<?php echo $user ? htmlspecialchars($user['client_id']) : ''; ?>" <?php echo $user ? 'readonly' : 'required'; ?>>
                        <div class="form-text">Identificativo univoco del client.</div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_status" class="form-label">Stato</label>
                        <select class="form-select" id="modal_status" name="status">
                            <option value="1" <?php echo $user && $user['status'] == 1 ? 'selected' : ''; ?>>Attivo</option>
                            <option value="0" <?php echo $user && $user['status'] == 0 ? 'selected' : ''; ?>>Disattivato</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="modal_notes" class="form-label">Note</label>
                        <textarea class="form-control" id="modal_notes" name="notes" rows="3"><?php echo $user ? htmlspecialchars($user['notes']) : ''; ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Salva</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script per aprire automaticamente il modal in caso di modifica -->
<?php if ($user): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var userModal = new bootstrap.Modal(document.getElementById('userModal'));
    userModal.show();
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>