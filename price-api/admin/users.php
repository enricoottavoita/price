<?php
require_once 'includes/header.php';

// Gestione filtri e paginazione
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$orderBy = isset($_GET['order_by']) ? $_GET['order_by'] : 'last_contribution';
$orderDir = isset($_GET['order_dir']) ? $_GET['order_dir'] : 'DESC';

// Ottieni utenti
$users = Stats::getUsers($page, $perPage, $search, $orderBy, $orderDir);
$totalUsers = Stats::getTotalUsersCount($search);
$totalPages = ceil($totalUsers / $perPage);

// Gestione eliminazione utente
if (isset($_POST['delete_user_id'])) {
    $deleteUserId = $_POST['delete_user_id'];
    
    if (Stats::deleteUser($deleteUserId)) {
        $successMessage = "Utente eliminato con successo.";
    } else {
        $errorMessage = "Errore nell'eliminazione dell'utente.";
    }
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="card-title">Gestione Utenti</h2>
                <p class="text-muted">Visualizza e gestisci gli utenti che contribuiscono al sistema</p>
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

<!-- Filtri -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="" class="row g-3">
            <div class="col-md-6">
                <label for="search" class="form-label">Cerca ID Cliente</label>
                <input type="text" class="form-control" id="search" name="search" value="<?php echo h($search); ?>">
            </div>
            <div class="col-md-2">
                <label for="order_by" class="form-label">Ordina per</label>
                <select class="form-select" id="order_by" name="order_by">
                    <option value="last_contribution" <?php echo $orderBy === 'last_contribution' ? 'selected' : ''; ?>>Ultimo contributo</option>
                    <option value="total_contributions" <?php echo $orderBy === 'total_contributions' ? 'selected' : ''; ?>>Totale contributi</option>
                    <option value="client_id" <?php echo $orderBy === 'client_id' ? 'selected' : ''; ?>>ID Cliente</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="order_dir" class="form-label">Direzione</label>
                <select class="form-select" id="order_dir" name="order_dir">
                    <option value="DESC" <?php echo $orderDir === 'DESC' ? 'selected' : ''; ?>>Decrescente</option>
                    <option value="ASC" <?php echo $orderDir === 'ASC' ? 'selected' : ''; ?>>Crescente</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="per_page" class="form-label">Per pagina</label>
                <select class="form-select" id="per_page" name="per_page">
                    <option value="10" <?php echo $perPage === 10 ? 'selected' : ''; ?>>10</option>
                    <option value="20" <?php echo $perPage === 20 ? 'selected' : ''; ?>>20</option>
                    <option value="50" <?php echo $perPage === 50 ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $perPage === 100 ? 'selected' : ''; ?>>100</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Filtra</button>
                <a href="users.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Lista utenti -->
<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID Cliente</th>
                        <th>Totale Contributi</th>
                        <th>Ultimo Contributo</th>
                        <th>Ultimo Login</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="5" class="text-center">Nessun utente trovato</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo h($user['client_id']); ?></td>
                            <td><?php echo number_format($user['total_contributions']); ?></td>
                            <td><?php echo h($user['last_contribution']); ?></td>
                            <td><?php echo h($user['last_login']); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deleteModal"
                                        data-user-id="<?php echo h($user['client_id']); ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <a href="user_activity.php?client_id=<?php echo urlencode($user['client_id']); ?>" class="btn btn-sm btn-info">
                                    <i class="bi bi-activity"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginazione -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Navigazione pagine">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page-1; ?>&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&order_by=<?php echo urlencode($orderBy); ?>&order_dir=<?php echo urlencode($orderDir); ?>">Precedente</a>
                </li>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&order_by=<?php echo urlencode($orderBy); ?>&order_dir=<?php echo urlencode($orderDir); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                
                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page+1; ?>&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&order_by=<?php echo urlencode($orderBy); ?>&order_dir=<?php echo urlencode($orderDir); ?>">Successiva</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Modal di conferma eliminazione -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Conferma eliminazione</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Sei sicuro di voler eliminare questo utente?</p>
                <p><strong>ID Cliente:</strong> <span id="delete-user-id"></span></p>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> Attenzione: questa azione eliminerà l'utente e tutti i suoi contributi.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <form method="post" action="">
                    <input type="hidden" id="delete_user_id" name="delete_user_id" value="">
                    <button type="submit" class="btn btn-danger">Elimina</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestione modal eliminazione
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            
            document.getElementById('delete-user-id').textContent = userId;
            document.getElementById('delete_user_id').value = userId;
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>