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

// Titolo pagina
$page_title = 'Grafici e Statistiche';

// Includi header
include 'includes/header.php';

// Ottieni statistiche prezzi per mese
$stmt = $conn->query("SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as count
FROM 
    prices
WHERE 
    created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
GROUP BY 
    DATE_FORMAT(created_at, '%Y-%m')
ORDER BY 
    month ASC");
$price_stats_by_month = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ottieni statistiche prezzi per paese
$stmt = $conn->query("SELECT 
    country,
    COUNT(*) as count
FROM 
    prices
GROUP BY 
    country
ORDER BY 
    count DESC");
$price_stats_by_country = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ottieni statistiche utenti per mese
$stmt = $conn->query("SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as count
FROM 
    users
WHERE 
    created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
GROUP BY 
    DATE_FORMAT(created_at, '%Y-%m')
ORDER BY 
    month ASC");
$user_stats_by_month = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ottieni statistiche token per stato
$stmt = $conn->query("SELECT 
    CASE 
        WHEN revoked = 1 THEN 'Revocato'
        WHEN expires_at <= NOW() AND expires_at IS NOT NULL THEN 'Scaduto'
        ELSE 'Attivo'
    END as status,
    COUNT(*) as count
FROM 
    tokens
GROUP BY 
    status");
$token_stats_by_status = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-4">
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-graph-up me-1"></i>
                    Prezzi per Mese
                </div>
                <div class="card-body">
                    <canvas id="pricesByMonthChart" width="100%" height="50"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-pie-chart me-1"></i>
                    Prezzi per Paese
                </div>
                <div class="card-body">
                    <canvas id="pricesByCountryChart" width="100%" height="50"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-people me-1"></i>
                    Nuovi Utenti per Mese
                </div>
                <div class="card-body">
                    <canvas id="usersByMonthChart" width="100%" height="50"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-key me-1"></i>
                    Token per Stato
                </div>
                <div class="card-body">
                    <canvas id="tokensByStatusChart" width="100%" height="50"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Grafico prezzi per mese
    var pricesByMonthCtx = document.getElementById('pricesByMonthChart').getContext('2d');
    var pricesByMonthData = {
        labels: [
            <?php
            $months = [];
            $counts = [];
            foreach ($price_stats_by_month as $stat) {
                $date = new DateTime($stat['month'] . '-01');
                $months[] = $date->format('M Y');
                $counts[] = $stat['count'];
            }
            echo "'" . implode("', '", $months) . "'";
            ?>
        ],
        datasets: [{
            label: 'Nuovi Prezzi',
            data: [<?php echo implode(', ', $counts); ?>],
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    };
    var pricesByMonthChart = new Chart(pricesByMonthCtx, {
        type: 'bar',
        data: pricesByMonthData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Grafico prezzi per paese
    var pricesByCountryCtx = document.getElementById('pricesByCountryChart').getContext('2d');
    var pricesByCountryData = {
        labels: [
            <?php
            $countries = [];
            $country_counts = [];
            foreach ($price_stats_by_country as $stat) {
                $countries[] = strtoupper($stat['country']);
                $country_counts[] = $stat['count'];
            }
            echo "'" . implode("', '", $countries) . "'";
            ?>
        ],
        datasets: [{
            data: [<?php echo implode(', ', $country_counts); ?>],
            backgroundColor: [
                'rgba(255, 99, 132, 0.7)',
                'rgba(54, 162, 235, 0.7)',
                'rgba(255, 206, 86, 0.7)',
                'rgba(75, 192, 192, 0.7)',
                'rgba(153, 102, 255, 0.7)',
                'rgba(255, 159, 64, 0.7)',
                'rgba(199, 199, 199, 0.7)'
            ],
            borderWidth: 1
        }]
    };
    var pricesByCountryChart = new Chart(pricesByCountryCtx, {
        type: 'pie',
        data: pricesByCountryData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
    
    // Grafico utenti per mese
    var usersByMonthCtx = document.getElementById('usersByMonthChart').getContext('2d');
    var usersByMonthData = {
        labels: [
            <?php
            $user_months = [];
            $user_counts = [];
            foreach ($user_stats_by_month as $stat) {
                $date = new DateTime($stat['month'] . '-01');
                $user_months[] = $date->format('M Y');
                $user_counts[] = $stat['count'];
            }
            echo "'" . implode("', '", $user_months) . "'";
            ?>
        ],
        datasets: [{
            label: 'Nuovi Utenti',
            data: [<?php echo implode(', ', $user_counts); ?>],
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1
        }]
    };
    var usersByMonthChart = new Chart(usersByMonthCtx, {
        type: 'line',
        data: usersByMonthData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Grafico token per stato
    var tokensByStatusCtx = document.getElementById('tokensByStatusChart').getContext('2d');
    var tokensByStatusData = {
        labels: [
            <?php
            $statuses = [];
            $status_counts = [];
            $colors = [];
            
            foreach ($token_stats_by_status as $stat) {
                $statuses[] = $stat['status'];
                $status_counts[] = $stat['count'];
                
                // Assegna colori in base allo stato
                switch ($stat['status']) {
                    case 'Attivo':
                        $colors[] = 'rgba(40, 167, 69, 0.7)'; // Verde per attivo
                        break;
                    case 'Revocato':
                        $colors[] = 'rgba(220, 53, 69, 0.7)'; // Rosso per revocato
                        break;
                    case 'Scaduto':
                        $colors[] = 'rgba(255, 193, 7, 0.7)'; // Giallo per scaduto
                        break;
                    default:
                        $colors[] = 'rgba(108, 117, 125, 0.7)'; // Grigio per altri
                }
            }
            
            echo "'" . implode("', '", $statuses) . "'";
            ?>
        ],
        datasets: [{
            data: [<?php echo implode(', ', $status_counts); ?>],
            backgroundColor: [<?php echo "'" . implode("', '", $colors) . "'"; ?>],
            borderWidth: 1
        }]
    };
    var tokensByStatusChart = new Chart(tokensByStatusCtx, {
        type: 'doughnut',
        data: tokensByStatusData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>