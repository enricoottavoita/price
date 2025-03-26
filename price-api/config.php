<?php
// Configurazione database
define('DB_HOST', 'localhost');
define('DB_USER', 'betaserv_prices');  // Sostituisci con il tuo username DB
define('DB_PASS', 'LMEbLCYAzguCgxZGkub9');  // Sostituisci con la tua password DB
define('DB_NAME', 'betaserv_prices');      // Sostituisci con il nome del tuo DB

// Configurazione generale
define('MAX_HISTORY_RECORDS', 50);
define('CACHE_TIME', 300);
define('MAX_RECORDS_PER_REQUEST', 20);
define('DEBUG_MODE', false);

// Chiavi di sicurezza - CAMBIA QUESTI VALORI CON STRINGHE CASUALI!
define('AUTH_KEY', 'FQGGHSTdxc');
define('AUTH_SECRET_KEY', 'ISwvz7pz0uTwOgJwfBox24uAcvgp4QpZ');


// Impostazioni rate limiting
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 3600);
?>