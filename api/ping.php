<?php
// Includi il file di configurazione
require_once "../includes/config.php";

// Imposta gli header CORS e JSON
set_api_headers();

// Semplice risposta di ping
$response = [
    "status" => "success",
    "message" => "API is working",
    "time" => date("Y-m-d H:i:s"),
    "version" => "1.0"
];

// Invia la risposta
echo json_encode($response);
exit;
?>