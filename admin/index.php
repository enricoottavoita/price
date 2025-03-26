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

// Conta utenti
$stmt = $conn->prepare("SELECT COUNT(*) as total_users FROM users");
$stmt->execute();
$users_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

// Conta prezzi
$stmt = $conn->prepare("SELECT COUNT(*) as total_prices FROM prices");
$stmt->execute();
$prices_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_prices'];

// Conta prezzi ultimi 7 giorni
$stmt = $conn->prepare("SELECT COUNT(*) as recent_prices FROM prices WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute();
$recent_prices = $stmt->fetch(PDO::FETCH_ASSOC)['recent_prices'];

// Ottieni statistiche per paese
$stmt = $conn->prepare("SELECT country, COUNT(*) as count FROM prices GROUP BY country");
$stmt->execute();
$country_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepara dati per il grafico
$countries = [];
$counts = [];
foreach ($country_stats as $stat) {
    $countries[] = strtoupper($stat['country']);
    $counts[] = $stat['count'];
}

// Titolo pagina
$page_title = 'Dashboard';

// Includi header
include 'includes/header.php';
?>

<!-- Contenuto dashboard -->
<div class="container-fluid px-4">
    <!-- Statistiche principali -->
    <div class="row g-3 my-3">
        <div class="col-md-4">
            <div class="card text-white bg-primary card-stats">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Utenti Totali</h6>
                            <h2 class="my-2"><?php echo number_format($users_count); ?></h2>
                        </div>
                        <div class="fs-1 text-white-50">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a href="users.php" class="small text-white stretched-link">Visualizza Dettagli</a>
                    <div class="small text-white"><i class="bi bi-chevron-right"></i></div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card text-white bg-success card-stats">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Prezzi Totali</h6>
                            <h2 class="my-2"><?php echo number_format($prices_count); ?></h2>
                        </div>
                        <div class="fs-1 text-white-50">
                            <i class="bi bi-currency-euro"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a href="prices.php" class="small text-white stretched-link">Visualizza Dettagli</a>
                    <div class="small text-white"><i class="bi bi-chevron-right"></i></div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card text-white bg-info card-stats">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Nuovi Prezzi (7 giorni)</h6>
                            <h2 class="my-2"><?php echo number_format($recent_prices); ?></h2>
                        </div>
                        <div class="fs-1 text-white-50">
                            <i class="bi bi-graph-up"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a href="prices.php" class="small text-white stretched-link">Visualizza Dettagli</a>
                    <div class="small text-white"><i class="bi bi-chevron-right"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Grafici e tabelle -->
    <div class="row">
        <!-- Grafico distribuzione per paese -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-pie-chart me-1"></i>
                    Distribuzione Prezzi per Paese
                </div>
                <div class="card-body">
                    <canvas id="countryChart" width="100%" height="50"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Ultimi prezzi aggiunti -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-table me-1"></i>
                    Ultimi Prezzi Aggiunti
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>ASIN</th>
                                    <th>Paese</th>
                                    <th>Prezzo</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Ottieni ultimi 5 prezzi
                                $stmt = $conn->prepare("SELECT * FROM prices ORDER BY created_at DESC LIMIT 5");
                                $stmt->execute();
                                $latest_prices = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($latest_prices as $price) {
                                    echo '<tr>';
                                    echo '<td>' . htmlspecialchars($price['asin']) . '</td>';
                                    echo '<td>' . strtoupper(htmlspecialchars($price['country'])) . '</td>';
                                    echo '<td>€' . number_format($price['price'], 2) . '</td>';
                                    echo '<td>' . date('d/m/Y H:i', strtotime($price['created_at'])) . '</td>';
                                    echo '</tr>';
                                }
                                
                                if (count($latest_prices) === 0) {
                                    echo '<tr><td colspan="4" class="text-center">Nessun prezzo trovato</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <a href="prices.php" class="btn btn-sm btn-primary">Visualizza Tutti</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script per il grafico -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Grafico distribuzione per paese
    var ctx = document.getElementById('countryChart').getContext('2d');
    var countryChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($countries); ?>,
            datasets: [{
                data: <?php echo json_encode($counts); ?>,
                backgroundColor: [
                    '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>