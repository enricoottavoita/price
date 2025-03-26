<?php
// Script per generare un hash sicuro della password
$password = 'PolloPollo5555.'; // Sostituisci con la tua password
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Password: $password\n";
echo "Hash: $hash\n";
?>