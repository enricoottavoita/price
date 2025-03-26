<?php
require_once 'includes/header.php';

// Ottieni parametri
$asin = isset($_GET['asin']) ? $_GET['asin'] : '';
$country = isset($_GET['country']) ? $_GET['country'] : '';

// Validazione
if (empty($asin) || empty($country)) {
    header('Location: products.php');
    exit;
}

// Ottieni dati prodotto
$product = Stats::getProductDetails($asin, $country);
if (!$product) {
    header('Location: products.php');
    exit;
}

// Ottieni cronologia prezzi
$history = Stats::getProductHistory($asin, $country);
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="card-title">Storico Prezzi</h2>
                        <p class="text-muted mb-0">
                            ASIN: <a href="https://amazon.<?php echo h($country); ?>/dp/<?php echo h($asin); ?>" target="_blank"><?php echo h($asin); ?></a> 
                            | Paese: <?php echo strtoupper(h($country)); ?>
                        </p>
                    </div>
                    <a href="products.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Torna ai prodotti
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dettagli prodotto -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">Dettagli Prodotto</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <tr>
                            <th>ASIN:</th>
                            <td><?php echo h($product['asin']); ?></td>
                        </tr>
                        <tr>
                            <th>Paese:</th>
                            <td><?php echo strtoupper(h($product['country'])); ?></td>
                        </tr>
                        <tr>
                            <th>Prezzo attuale:</th>
                            <td>€<?php echo number_format($product['price'], 2); ?></td>
                        </tr>
                        <tr>
                            <th>Fonte:</th>
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
                        </tr>
                        <tr>
                            <th>Confermato:</th>
                            <td>
                                <?php if ($product['confirmed']): ?>
                                <span class="badge bg-success">Sì</span>
                                <?php else: ?>
                                <span class="badge bg-danger">No</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Ultimo aggiornamento:</th>
                            <td><?php echo h($product['timestamp']); ?></td>
                        </tr>
                        <tr>
                            <th>Ultimo contributore:</th>
                            <td><?php echo h($product['client_id']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">Statistiche Prezzo</h5>
            </div>
            <div class="card-body">
                <?php
                // Calcola statistiche
                $prices = array_column($history, 'price');
                $count = count($prices);
                
                if ($count > 0) {
                    $min = min($prices);
                    $max = max($prices);
                    $avg = array_sum($prices) / $count;
                    
                    // Calcola mediana
                    sort($prices);
                    $median = ($count % 2) ? $prices[floor($count / 2)] : 
                              ($prices[$count / 2 - 1] + $prices[$count / 2]) / 2;
                    
                    // Calcola deviazione standard
                    $variance = 0;
                    foreach ($prices as $price) {
                        $variance += pow($price - $avg, 2);
                    }
                    $stdDev = sqrt($variance / $count);
                ?>
                <div class="row text-center">
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-3">
                            <h6>Prezzo minimo</h6>
                            <h4 class="text-success">€<?php echo number_format($min, 2); ?></h4>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-3">
                            <h6>Prezzo medio</h6>
                            <h4>€<?php echo number_format($avg, 2); ?></h4>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-3">
                            <h6>Prezzo massimo</h6>
                            <h4 class="text-danger">€<?php echo number_format($max, 2); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="row text-center">
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-3">
                            <h6>Mediana</h6>
                            <h4>€<?php echo number_format($median, 2); ?></h4>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-3">
                            <h6>Dev. Standard</h6>
                            <h4>€<?php echo number_format($stdDev, 2); ?></h4>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-3">
                            <h6>Contributi</h6>
                            <h4><?php echo number_format($count); ?></h4>
                        </div>
                    </div>
                </div>
                <?php } else { ?>
                <div class="alert alert-info">Nessun dato storico disponibile per questo prodotto.</div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<!-- Grafico storico prezzi -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">Grafico Storico Prezzi</h5>
            </div>
            <div class="card-body">
                <?php if ($count > 0): ?>
                <canvas id="priceHistoryChart" height="300"></canvas>
                <?php else: ?>
                <div class="alert alert-info">Nessun dato storico disponibile per questo prodotto.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Tabella storico prezzi -->
<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">Cronologia Prezzi</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Prezzo</th>
                                <th>Fonte</th>
                                <th>Contributore</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="4" class="text-center">Nessun dato storico disponibile</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($history as $entry): ?>
                                <tr>
                                    <td><?php echo h($entry['timestamp']); ?></td>
                                    <td>€<?php echo number_format($entry['price'], 2); ?></td>
                                    <td>
                                        <?php
                                        switch ($entry['source']) {
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
                                                echo h($entry['source']);
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo h($entry['client_id']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($count > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Grafico storico prezzi
    const priceCtx = document.getElementById('priceHistoryChart').getContext('2d');
    
    // Prepara i dati
    const dates = <?php echo json_encode(array_column($history, 'timestamp')); ?>;
    const prices = <?php echo json_encode(array_column($history, 'price')); ?>;
    const sources = <?php echo json_encode(array_column($history, 'source')); ?>;
    
    // Mappa colori per fonte
    const sourceColors = {
        'amazon': 'rgba(255, 159, 64, 1)',
        'keepa': 'rgba(54, 162, 235, 1)',
        'both': 'rgba(75, 192, 192, 1)',
        'manual': 'rgba(153, 102, 255, 1)'
    };
    
    // Crea array di colori basati sulla fonte
    const pointColors = sources.map(source => sourceColors[source] || 'rgba(201, 203, 207, 1)');
    
    new Chart(priceCtx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Prezzo (€)',
                data: prices,
                borderColor: 'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderWidth: 2,
                tension: 0.1,
                pointBackgroundColor: pointColors,
                pointBorderColor: pointColors,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: false,
                    title: {
                        display: true,
                        text: 'Prezzo (€)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Data'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const index = context.dataIndex;
                            const price = context.raw;
                            const source = sources[index];
                            return [
                                `Prezzo: €${price.toFixed(2)}`,
                                `Fonte: ${source}`
                            ];
                        }
                    }
                },
                legend: {
                    display: false
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>