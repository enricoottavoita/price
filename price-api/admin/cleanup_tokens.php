<?php
// File: /var/www/price-api/admin/cleanup_tokens.php
session_start();

// Verifica se l'utente è loggato come amministratore
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Titolo pagina
$page_title = 'Pulizia Token Duplicati';

// Includi file di configurazione e database con percorsi assoluti
require_once '/var/www/price-api/includes/config.php';
require_once '/var/www/price-api/includes/database.php';

// Connessione al database
$db = new Database();
$conn = $db->getConnection();

// Include header
include '/var/www/price-api/admin/includes/header.php';

// Trova gli utenti con più token attivi
$stmt = $conn->query("
    SELECT u.id, u.client_id, COUNT(t.id) as token_count
    FROM users u
    JOIN tokens t ON u.id = t.user_id
    WHERE t.revoked = 0 AND t.expires_at > NOW()
    GROUP BY u.id, u.client_id
    HAVING COUNT(t.id) > 1
    ORDER BY token_count DESC
");

$users_with_duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_tokens_revoked = 0;
?>

<div class="container-fluid px-4">
    <h1 class="mt-4"><?php echo $page_title; ?></h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="tokens.php">Gestione Token</a></li>
        <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
    </ol>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-broom me-1"></i>
            <?php echo $page_title; ?>
        </div>
        <div class="card-body">
            <?php if (count($users_with_duplicates) === 0): ?>
                <div class="alert alert-success">
                    <strong>Ottimo!</strong> Nessun utente con token duplicati trovato.
                </div>
            <?php else: ?>
                <p>Trovati <strong><?php echo count($users_with_duplicates); ?></strong> utenti con token duplicati.</p>
                
                <?php 
                // Se è stata richiesta la pulizia
                if (isset($_GET['cleanup']) && $_GET['cleanup'] === '1'): 
                ?>
                    <h3>Risultati pulizia</h3>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Client ID</th>
                                    <th>Token totali</th>
                                    <th>Token revocati</th>
                                    <th>Token mantenuto</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            foreach ($users_with_duplicates as $user) {
                                $user_id = $user['id'];
                                $client_id = $user['client_id'];
                                $token_count = $user['token_count'];
                                
                                // Ottieni tutti i token attivi per questo utente
                                $stmt = $conn->prepare("
                                    SELECT id, token, created_at, expires_at
                                    FROM tokens
                                    WHERE user_id = :user_id AND revoked = 0 AND expires_at > NOW()
                                    ORDER BY created_at DESC
                                ");
                                $stmt->bindParam(':user_id', $user_id);
                                $stmt->execute();
                                $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Mantieni solo il token più recente
                                $kept_token = array_shift($tokens);
                                $revoked_count = 0;
                                
                                // Revoca tutti gli altri token
                                foreach ($tokens as $token) {
                                    $stmt = $conn->prepare("UPDATE tokens SET revoked = 1 WHERE id = :id");
                                    $stmt->bindParam(':id', $token['id']);
                                    $stmt->execute();
                                    $revoked_count++;
                                    $total_tokens_revoked++;
                                }
                                
                                echo '<tr>
                                    <td>' . htmlspecialchars($client_id) . '</td>
                                    <td>' . $token_count . '</td>
                                    <td>' . $revoked_count . '</td>
                                    <td>' . substr($kept_token['token'], 0, 20) . '... (scade: ' . $kept_token['expires_at'] . ')</td>
                                </tr>';
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-success">
                        <strong>Pulizia completata!</strong> Totale token revocati: <?php echo $total_tokens_revoked; ?>
                    </div>
                    <a href="cleanup_tokens.php" class="btn btn-primary">Torna alla pagina di verifica</a>
                
                <?php else: ?>
                    <!-- Mostra solo la lista degli utenti con token duplicati -->
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Client ID</th>
                                    <th>Numero token attivi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users_with_duplicates as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['client_id']); ?></td>
                                    <td><?php echo $user['token_count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="cleanup_tokens.php?cleanup=1" class="btn btn-warning" onclick="return confirm('Sei sicuro di voler revocare i token duplicati?');">
                        <i class="fas fa-broom me-1"></i> Esegui pulizia dei token duplicati
                    </a>
                <?php endif; ?>
                
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '/var/www/price-api/admin/includes/footer.php'; ?>