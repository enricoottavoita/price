<?php
require_once 'includes/header.php';

// Ottieni statistiche generali
$stats = Stats::getGeneralStats();

// Ottieni dati per i grafici
$dailyContributions = Stats::getDailyContributions();
$topProducts = Stats::getTopProducts();
$topContributors = Stats::getTopContributors();
$countryDistribution = Stats::getCountryDistribution();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="card-title">Dashboard</h2>
                <p class="text-muted">Panoramica del sistema di condivisione prezzi</p>
            </div>
        </div>
    </div>
</div>

<!-- Statistiche principali -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Totale Prodotti</h6>
                        <h2 class="mb-0"><?php echo number_format($stats['total_products']); ?></h2>
                    </div>
                    <i class="bi bi-box fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Totale Prezzi</h6>
                        <h2 class="mb-0"><?php echo number_format($stats['total_prices']); ?></h2>
                    </div>
                    <i class="bi bi-tag fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Totale Utenti</h6>
                        <h2 class="mb-0"><?php echo number_format($stats['total_contributors']); ?></h2>
                    </div>
                    <i class="bi bi-people fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Contributi Oggi</h6>
                        <h2 class="mb-0"><?php echo number_format($stats['today_contributions']); ?></h2>
                    </div>
                    <i class="bi bi-calendar-check fs-1"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Grafico contribuzioni giornaliere -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">Contribuzioni Giornaliere</h5>
            </div>
            <div class="card-body">
                <canvas id="dailyContributionsChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Statistiche dettagliate -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0">Prodotti Più Popolari</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ASIN</th>
                                <th>Contributi</th>
                                <th>Ultimo Prezzo</th>
                                <th>Ultimo Aggiornamento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProducts as $product): ?>
                            <tr>
                                <td>
                                    <a href="https://amazon.it/dp/<?php echo h($product['asin']); ?>" target="_blank">
                                        <?php echo h($product['asin']); ?>
                                    </a>
                                </td>
                                <td><?php echo number_format($product['contributions']); ?></td>
                                <td>€<?php echo number_format($product['last_price'], 2); ?></td>
                                <td><?php echo h($product['last_update']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0">Utenti Più Attivi</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID Cliente</th>
                                <th>Contributi</th>
                                <th>Ultimo Contributo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topContributors as $contributor): ?>
                            <tr>
                                <td><?php echo h($contributor['client_id']); ?></td>
                                <td><?php echo number_format($contributor['total_contributions']); ?></td>
                                <td><?php echo h($contributor['last_contribution']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Distribuzione per paese -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">Distribuzione per Paese</h5>
            </div>
            <div class="card-body">
                <canvas id="countryDistributionChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">Distribuzione Fonti Dati</h5>
            </div>
            <div class="card-body">
                <canvas id="sourceDistributionChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript per i grafici -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Grafico contribuzioni giornaliere
    const dailyCtx = document.getElementById('dailyContributionsChart').getContext('2d');
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($dailyContributions, 'date')); ?>,
            datasets: [{
                label: 'Contribuzioni',
                data: <?php echo json_encode(array_column($dailyContributions, 'count')); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Grafico distribuzione paesi
    const countryCtx = document.getElementById('countryDistributionChart').getContext('2d');
    new Chart(countryCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode(array_column($countryDistribution, 'country')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($countryDistribution, 'count')); ?>,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(199, 199, 199, 0.7)',
                    'rgba(83, 102, 255, 0.7)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        }
    });
    
    // Grafico distribuzione fonti
    const sourceCtx = document.getElementById('sourceDistributionChart').getContext('2d');
    new Chart(sourceCtx, {
        type: 'doughnut',
        data: {
            labels: ['Amazon', 'Keepa', 'Entrambi', 'Manuale'],
            datasets: [{
                data: [
                    <?php echo $stats['source_amazon']; ?>,
                    <?php echo $stats['source_keepa']; ?>,
                    <?php echo $stats['source_both']; ?>,
                    <?php echo $stats['source_manual']; ?>
                ],
                backgroundColor: [
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>