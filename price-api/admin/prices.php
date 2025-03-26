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

// Gestione eliminazione prezzo
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Genera un token CSRF se non esiste
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    // Verifica token CSRF
    if (isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
        try {
            $stmt = $conn->prepare("DELETE FROM prices WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $_SESSION['message'] = "Prezzo eliminato con successo.";
            $_SESSION['message_type'] = "success";
        } catch (PDOException $e) {
            $_SESSION['message'] = "Errore nell'eliminazione del prezzo: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Token di sicurezza non valido.";
        $_SESSION['message_type'] = "danger";
    }
    
    header('Location: prices.php');
    exit;
}

// Impostazioni paginazione
$records_per_page = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Filtri
$asin_filter = isset($_GET['asin']) ? trim($_GET['asin']) : '';
$country_filter = isset($_GET['country']) ? trim($_GET['country']) : '';

// Costruisci la query di base
$query = "SELECT * FROM prices WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM prices WHERE 1=1";
$params = [];

// Aggiungi filtri se presenti
if (!empty($asin_filter)) {
    $query .= " AND asin LIKE :asin";
    $count_query .= " AND asin LIKE :asin";
    $params[':asin'] = "%$asin_filter%";
}

if (!empty($country_filter)) {
    $query .= " AND country = :country";
    $count_query .= " AND country = :country";
    $params[':country'] = $country_filter;
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
$prices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Titolo pagina
$page_title = 'Gestione Prezzi';

// Includi header
include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-search me-1"></i>
            Filtra Prezzi
        </div>
        <div class="card-body">
            <form method="get" action="prices.php" class="row g-3">
                <div class="col-md-4">
                    <label for="asin" class="form-label">ASIN</label>
                    <input type="text" class="form-control" id="asin" name="asin" value="<?php echo htmlspecialchars($asin_filter); ?>" placeholder="Inserisci ASIN">
                </div>
                <div class="col-md-4">
                    <label for="country" class="form-label">Paese</label>
                    <select class="form-select" id="country" name="country">
                        <option value="">Tutti i paesi</option>
                        <option value="it" <?php echo $country_filter === 'it' ? 'selected' : ''; ?>>Italia (IT)</option>
                        <option value="fr" <?php echo $country_filter === 'fr' ? 'selected' : ''; ?>>Francia (FR)</option>
                        <option value="de" <?php echo $country_filter === 'de' ? 'selected' : ''; ?>>Germania (DE)</option>
                        <option value="es" <?php echo $country_filter === 'es' ? 'selected' : ''; ?>>Spagna (ES)</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search"></i> Filtra
                    </button>
                    <a href="prices.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-currency-euro me-1"></i>
                    Elenco Prezzi
                </div>
                <div>
                    <span class="badge bg-info"><?php echo number_format($total_records); ?> Risultati</span>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($prices) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ASIN</th>
                                <th>Paese</th>
                                <th>Prezzo</th>
                                <th>Fonte</th>
                                <th>Data Creazione</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prices as $price): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($price['id']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($price['asin']); ?>
                                        <a href="https://www.amazon.<?php echo htmlspecialchars($price['country']); ?>/dp/<?php echo htmlspecialchars($price['asin']); ?>" target="_blank" class="ms-1" data-bs-toggle="tooltip" title="Visualizza su Amazon">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>
                                    </td>
                                    <td><?php echo strtoupper(htmlspecialchars($price['country'])); ?></td>
                                    <td>€<?php echo number_format($price['price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($price['source'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($price['created_at'])); ?></td>
                                    <td>
                                        <a href="prices.php?delete=<?php echo $price['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-sm btn-danger confirm-action" data-bs-toggle="tooltip" title="Elimina">
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
                                    <a class="page-link" href="?page=1<?php echo !empty($asin_filter) ? '&asin='.urlencode($asin_filter) : ''; ?><?php echo !empty($country_filter) ? '&country='.urlencode($country_filter) : ''; ?>" aria-label="First">
                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($asin_filter) ? '&asin='.urlencode($asin_filter) : ''; ?><?php echo !empty($country_filter) ? '&country='.urlencode($country_filter) : ''; ?>" aria-label="Previous">
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
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($asin_filter) ? '&asin='.urlencode($asin_filter) : ''; ?><?php echo !empty($country_filter) ? '&country='.urlencode($country_filter) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($asin_filter) ? '&asin='.urlencode($asin_filter) : ''; ?><?php echo !empty($country_filter) ? '&country='.urlencode($country_filter) : ''; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($asin_filter) ? '&asin='.urlencode($asin_filter) : ''; ?><?php echo !empty($country_filter) ? '&country='.urlencode($country_filter) : ''; ?>" aria-label="Last">
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
                    Nessun prezzo trovato. <?php echo !empty($asin_filter) || !empty($country_filter) ? 'Prova a modificare i filtri di ricerca.' : ''; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>