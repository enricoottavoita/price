<?php
require_once 'includes/header.php';

// Gestione filtri e paginazione
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$country = isset($_GET['country']) ? $_GET['country'] : '';
$orderBy = isset($_GET['order_by']) ? $_GET['order_by'] : 'timestamp';
$orderDir = isset($_GET['order_dir']) ? $_GET['order_dir'] : 'DESC';

// Ottieni prodotti
$products = Stats::getProducts($page, $perPage, $search, $country, $orderBy, $orderDir);
$totalProducts = Stats::getTotalProductsCount($search, $country);
$totalPages = ceil($totalProducts / $perPage);

// Gestione eliminazione prodotto
if (isset($_POST['delete_asin']) && isset($_POST['delete_country'])) {
    $deleteAsin = $_POST['delete_asin'];
    $deleteCountry = $_POST['delete_country'];
    
    if (Stats::deleteProduct($deleteAsin, $deleteCountry)) {
        $successMessage = "Prodotto eliminato con successo.";
    } else {
        $errorMessage = "Errore nell'eliminazione del prodotto.";
    }
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="card-title">Gestione Prodotti</h2>
                <p class="text-muted">Visualizza, cerca e gestisci i prodotti nel database</p>
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
            <div class="col-md-4">
                <label for="search" class="form-label">Cerca ASIN</label>
                <input type="text" class="form-control" id="search" name="search" value="<?php echo h($search); ?>">
            </div>
            <div class="col-md-2">
                <label for="country" class="form-label">Paese</label>
               <select class="form-select" id="country" name="country">
    <option value="">Tutti</option>
    <option value="it" <?php echo $country === 'it' ? 'selected' : ''; ?>>Italia</option>
    <option value="de" <?php echo $country === 'de' ? 'selected' : ''; ?>>Germania</option>
    <option value="fr" <?php echo $country === 'fr' ? 'selected' : ''; ?>>Francia</option>
    <option value="es" <?php echo $country === 'es' ? 'selected' : ''; ?>>Spagna</option>
</select>
            </div>
            <div class="col-md-2">
                <label for="order_by" class="form-label">Ordina per</label>
                <select class="form-select" id="order_by" name="order_by">
                    <option value="timestamp" <?php echo $orderBy === 'timestamp' ? 'selected' : ''; ?>>Data</option>
                    <option value="price" <?php echo $orderBy === 'price' ? 'selected' : ''; ?>>Prezzo</option>
                    <option value="asin" <?php echo $orderBy === 'asin' ? 'selected' : ''; ?>>ASIN</option>
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
                <a href="products.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Lista prodotti -->
<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ASIN</th>
                        <th>Paese</th>
                        <th>Prezzo</th>
                        <th>Fonte</th>
                        <th>Confermato</th>
                        <th>Ultimo Aggiornamento</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="7" class="text-center">Nessun prodotto trovato</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <a href="https://amazon.<?php echo h($product['country']); ?>/dp/<?php echo h($product['asin']); ?>" target="_blank">
                                    <?php echo h($product['asin']); ?>
                                </a>
                            </td>
                            <td><?php echo strtoupper(h($product['country'])); ?></td>
                            <td>€<?php echo number_format($product['price'], 2); ?></td>
                            <td>
                                <?php
                                switch ($product['source']) {
                                    case 'amazon':
                                        echo '<span class="badge bg-warning">Amazon</span>';
                                        break;
                                    case 'keepa':
                                        echo '<span class="badge bg-info">Keepa</span>';
                                        break;
                                    case 'both':
                                        echo '<span class="badge bg-success">Entrambi</span>';
                                        break;
                                    case 'manual':
                                        echo '<span class="badge bg-secondary">Manuale</span>';
                                        break;
                                    default:
                                        echo h($product['source']);
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($product['confirmed']): ?>
                                <span class="badge bg-success">Sì</span>
                                <?php else: ?>
                                <span class="badge bg-danger">No</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo h($product['timestamp']); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deleteModal"
                                        data-asin="<?php echo h($product['asin']); ?>"
                                        data-country="<?php echo h($product['country']); ?>">
                                                                        <i class="bi bi-trash"></i>
                                </button>
                                <a href="product_history.php?asin=<?php echo h($product['asin']); ?>&country=<?php echo h($product['country']); ?>" class="btn btn-sm btn-info">
                                    <i class="bi bi-graph-up"></i>
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
                    <a class="page-link" href="?page=<?php echo $page-1; ?>&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&country=<?php echo urlencode($country); ?>&order_by=<?php echo urlencode($orderBy); ?>&order_dir=<?php echo urlencode($orderDir); ?>">Precedente</a>
                </li>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&country=<?php echo urlencode($country); ?>&order_by=<?php echo urlencode($orderBy); ?>&order_dir=<?php echo urlencode($orderDir); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                
                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page+1; ?>&per_page=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&country=<?php echo urlencode($country); ?>&order_by=<?php echo urlencode($orderBy); ?>&order_dir=<?php echo urlencode($orderDir); ?>">Successiva</a>
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
                <p>Sei sicuro di voler eliminare questo prodotto?</p>
                <p><strong>ASIN:</strong> <span id="delete-asin"></span></p>
                <p><strong>Paese:</strong> <span id="delete-country"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <form method="post" action="">
                    <input type="hidden" id="delete_asin" name="delete_asin" value="">
                    <input type="hidden" id="delete_country" name="delete_country" value="">
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
            const asin = button.getAttribute('data-asin');
            const country = button.getAttribute('data-country');
            
            document.getElementById('delete-asin').textContent = asin;
            document.getElementById('delete-country').textContent = country.toUpperCase();
            document.getElementById('delete_asin').value = asin;
            document.getElementById('delete_country').value = country;
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>