<?php
// File: admin/cleanup_tokens.php

// Includi file necessari
require_once '../includes/config.php';
require_once '../includes/database.php';

// Verifica autenticazione admin (implementa secondo il tuo sistema)
// ...

// Connessione al database
$db = new Database();
$conn = $db->getConnection();

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

// Output HTML
echo '<!DOCTYPE html>
<html>
<head>
    <title>Pulizia Token Duplicati</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        .success { color: green; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <h1>Pulizia Token Duplicati</h1>';

if (count($users_with_duplicates) === 0) {
    echo '<p class="success">Nessun utente con token duplicati trovato.</p>';
} else {
    echo '<p>Trovati ' . count($users_with_duplicates) . ' utenti con token duplicati.</p>';
    
    // Se è stata richiesta la pulizia
    if (isset($_GET['cleanup']) && $_GET['cleanup'] === '1') {
        echo '<h2>Risultati pulizia</h2>';
        echo '<table>
            <tr>
                <th>Client ID</th>
                <th>Token totali</th>
                <th>Token revocati</th>
                <th>Token mantenuto</th>
            </tr>';
        
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
        
        echo '</table>';
        echo '<p class="success">Totale token revocati: ' . $total_tokens_revoked . '</p>';
        echo '<p><a href="cleanup_tokens.php">Torna alla pagina di verifica</a></p>';
    } else {
        // Mostra solo la lista degli utenti con token duplicati
        echo '<table>
            <tr>
                <th>Client ID</th>
                <th>Numero token attivi</th>
            </tr>';
        
        foreach ($users_with_duplicates as $user) {
            echo '<tr>
                <td>' . htmlspecialchars($user['client_id']) . '</td>
                <td>' . $user['token_count'] . '</td>
            </tr>';
        }
        
        echo '</table>';
        echo '<p><a href="cleanup_tokens.php?cleanup=1" onclick="return confirm(\'Sei sicuro di voler revocare i token duplicati?\');">Esegui pulizia dei token duplicati</a></p>';
    }
}

echo '<p><a href="index.php">Torna alla dashboard</a></p>
</body>
</html>';
?>