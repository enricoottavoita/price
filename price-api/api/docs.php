<?php
// File: api/docs.php

// Includi file di configurazione
require_once '../includes/config.php';

// Imposta il tipo di contenuto
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Price API - Documentazione</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            padding-top: 20px;
            padding-bottom: 40px;
        }
        .endpoint {
            margin-bottom: 30px;
            border-left: 4px solid #dee2e6;
            padding-left: 20px;
        }
        .endpoint.get {
            border-left-color: #0d6efd;
        }
        .endpoint.post {
            border-left-color: #198754;
        }
        .method {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
            color: white;
            margin-right: 10px;
        }
        .method.get {
            background-color: #0d6efd;
        }
        .method.post {
            background-color: #198754;
        }
        pre {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .nav-pills .nav-link.active {
            background-color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-md-3">
                <div class="position-sticky" style="top: 20px;">
                    <h4>Contenuti</h4>
                    <nav class="nav flex-column nav-pills">
                        <a class="nav-link active" href="#intro">Introduzione</a>
                        <a class="nav-link" href="#auth">Autenticazione</a>
                        <a class="nav-link" href="#endpoints">Endpoints</a>
                        <a class="nav-link" href="#errors">Gestione Errori</a>
                        <a class="nav-link" href="#examples">Esempi</a>
                    </nav>
                </div>
            </div>
            <div class="col-md-9">
                <h1>Price API - Documentazione</h1>
                <p class="lead">Questa documentazione descrive come utilizzare le API per accedere ai dati sui prezzi dei prodotti Amazon.</p>
                
                <section id="intro" class="mb-5">
                    <h2>Introduzione</h2>
                    <p>Le Price API ti permettono di ottenere i prezzi dei prodotti Amazon da diversi paesi europei. Per utilizzare le API, avrai bisogno di un ID client e di una chiave di autenticazione, che puoi ottenere contattando l'amministratore del sistema.</p>
                    <p>Base URL: <code><?php echo 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']); ?></code></p>
                </section>
                
                <section id="auth" class="mb-5">
                    <h2>Autenticazione</h2>
                    <p>Le API utilizzano un sistema di autenticazione basato su token JWT (JSON Web Token). Per ottenere un token, devi prima autenticarti con il tuo ID client e la chiave di autenticazione.</p>
                    
                    <div class="endpoint post">
                        <h3><span class="method post">POST</span> /authenticate.php</h3>
                        <p>Ottieni un token di accesso che puoi utilizzare per le successive richieste API.</p>
                        
                        <h4>Parametri della richiesta:</h4>
                        <pre><code>{
  "client_id": "il_tuo_client_id",
  "auth_key": "la_tua_auth_key"
}</code></pre>
                        
                        <h4>Risposta di successo:</h4>
                        <pre><code>{
  "success": true,
  "message": "Autenticazione riuscita",
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "expires_in": 86400
}</code></pre>
                        
                        <p>Il token ottenuto deve essere incluso nell'header <code>Authorization</code> di tutte le richieste successive, utilizzando lo schema <code>Bearer</code>:</p>
                        <pre><code>Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...</code></pre>
                    </div>
                </section>
                
                <section id="endpoints" class="mb-5">
                    <h2>Endpoints</h2>
                    
                    <div class="endpoint get">
                        <h3><span class="method get">GET</span> /price.php</h3>
                        <p>Ottieni il prezzo di un singolo prodotto Amazon.</p>
                        
                        <h4>Parametri della richiesta:</h4>
                        <ul>
                            <li><code>asin</code> (obbligatorio) - L'ASIN del prodotto Amazon (10 caratteri alfanumerici)</li>
                            <li><code>country</code> (obbligatorio) - Il codice del paese (it, fr, de, es)</li>
                        </ul>
                        
                        <h4>Esempio di richiesta:</h4>
                        <pre><code>GET /price.php?asin=B07PVCVBN7&country=it</code></pre>
                        
                        <h4>Risposta di successo:</h4>
                        <pre><code>{
  "success": true,
  "data": {
    "asin": "B07PVCVBN7",
    "country": "it",
    "price": 29.99,
    "currency": "EUR",
    "timestamp": 1623456789,
    "source": "database"
  }
}</code></pre>
                    </div>
                    
                    <div class="endpoint post">
                        <h3><span class="method post">POST</span> /prices.php</h3>
                        <p>Ottieni i prezzi di più prodotti Amazon contemporaneamente.</p>
                        
                        <h4>Parametri della richiesta:</h4>
                        <pre><code>{
  "items": [
    {
      "asin": "B07PVCVBN7",
      "country": "it"
    },
    {
      "asin": "B08F9VSGNB",
      "country": "fr"
    }
  ]
}</code></pre>
                        
                        <h4>Risposta di successo:</h4>
                        <pre><code>{
  "success": true,
  "data": [
    {
      "asin": "B07PVCVBN7",
      "country": "it",
      "price": 29.99,
      "currency": "EUR",
      "timestamp": 1623456789,
      "source": "database"
    },
    {
      "asin": "B08F9VSGNB",
      "country": "fr",
      "price": 39.99,
      "currency": "EUR",
      "timestamp": 1623456123,
      "source": "database"
    }
  ]
}</code></pre>
                    </div>
                    
                    <div class="endpoint post">
                        <h3><span class="method post">POST</span> /store_price.php</h3>
                        <p>Aggiungi un nuovo prezzo al database.</p>
                        
                        <h4>Parametri della richiesta:</h4>
                        <pre><code>{
  "asin": "B07PVCVBN7",
  "country": "it",
  "price": 29.99,
  "source": "scraper" // opzionale
}</code></pre>
                        
                        <h4>Risposta di successo:</h4>
                        <pre><code>{
  "success": true,
  "message": "Prezzo aggiunto con successo",
  "data": {
    "id": 123,
    "asin": "B07PVCVBN7",
    "country": "it",
    "price": 29.99,
    "source": "scraper",
    "timestamp": 1623456789
  }
}</code></pre>
                    </div>
                    
                    <div class="endpoint post">
                        <h3><span class="method post">POST</span> /batch_store.php</h3>
                        <p>Aggiungi più prezzi contemporaneamente al database.</p>
                        
                        <h4>Parametri della richiesta:</h4>
                        <pre><code>{
  "items": [
    {
      "asin": "B07PVCVBN7",
      "country": "it",
      "price": 29.99,
      "source": "scraper"
    },
    {
      "asin": "B08F9VSGNB",
      "country": "fr",
      "price": 39.99,
      "source": "scraper"
    }
  ]
}</code></pre>
                        
                        <h4>Risposta di successo:</h4>
                        <pre><code>{
  "success": true,
  "message": "Prezzi elaborati",
  "data": {
    "total": 2,
    "successful": 2,
    "failed": 0,
    "items": [
      {
        "asin": "B07PVCVBN7",
        "country": "it",
        "price": 29.99,
        "status": "success",
        "id": 123
      },
      {
        "asin": "B08F9VSGNB",
        "country": "fr",
        "price": 39.99,
        "status": "success",
        "id": 124
      }
    ]
  }
}</code></pre>
                    </div>
                    
                    <div class="endpoint post">
                        <h3><span class="method post">POST</span> /revoke.php</h3>
                        <p>Revoca il token di accesso corrente.</p>
                        
                        <h4>Parametri della richiesta:</h4>
                        <p>Nessun parametro richiesto. Il token viene identificato dall'header <code>Authorization</code>.</p>
                        
                        <h4>Risposta di successo:</h4>
                        <pre><code>{
  "success": true,
  "message": "Token revocato con successo"
}</code></pre>
                    </div>
                    
                    <div class="endpoint get">
                        <h3><span class="method get">GET</span> /status.php</h3>
                        <p>Verifica lo stato dell'API e ottieni statistiche generali.</p>
                        
                        <h4>Parametri della richiesta:</h4>
                        <p>Nessun parametro richiesto.</p>
                        
                        <h4>Risposta di successo:</h4>
                        <pre><code>{
  "status": "online",
  "version": "1.1.0",
  "timestamp": 1623456789,
  "database": "ok",
  "stats": {
    "prices": 10000,
    "users": 50,
    "active_tokens": 25
  },
  "server": {
    "php_version": "8.0.13",
    "memory_usage": "2.5 MB",
    "server_software": "Apache/2.4.51"
  }
}</code></pre>
                    </div>
                </section>
                
                <section id="errors" class="mb-5">
                    <h2>Gestione Errori</h2>
                    <p>In caso di errore, le API restituiscono un codice di stato HTTP appropriato e un oggetto JSON con informazioni sull'errore:</p>
                    
                    <pre><code>{
  "error": "Descrizione dell'errore"
}</code></pre>
                    
                    <h4>Codici di stato comuni:</h4>
                    <ul>
                        <li><code>400 Bad Request</code> - La richiesta contiene parametri mancanti o non validi</li>
                        <li><code>401 Unauthorized</code> - Autenticazione mancante o non valida</li>
                        <li><code>403 Forbidden</code> - L'utente non ha i permessi necessari</li>
                        <li><code>404 Not Found</code> - La risorsa richiesta non è stata trovata</li>
                        <li><code>405 Method Not Allowed</code> - Il metodo HTTP utilizzato non è supportato</li>
                        <li><code>429 Too Many Requests</code> - Troppe richieste in un breve periodo di tempo</li>
                        <li><code>500 Internal Server Error</code> - Errore interno del server</li>
                    </ul>
                </section>
                
                <section id="examples" class="mb-5">
                    <h2>Esempi</h2>
                    
                    <h4>Esempio di autenticazione con cURL:</h4>
                    <pre><code>curl -X POST <?php echo 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']); ?>/authenticate.php \
  -H "Content-Type: application/json" \
  -d '{"client_id": "il_tuo_client_id", "auth_key": "la_tua_auth_key"}'</code></pre>
                    
                    <h4>Esempio di richiesta prezzo con cURL:</h4>
                    // Continuo da api/docs.php
                    <pre><code>curl -X GET "<?php echo 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']); ?>/price.php?asin=B07PVCVBN7&country=it" \
  -H "Authorization: Bearer il_tuo_token"</code></pre>
                    
                    <h4>Esempio di aggiunta prezzo con cURL:</h4>
                    <pre><code>curl -X POST <?php echo 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']); ?>/store_price.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer il_tuo_token" \
  -d '{"asin": "B07PVCVBN7", "country": "it", "price": 29.99, "source": "scraper"}'</code></pre>
                    
                    <h4>Esempio di richiesta multipla con JavaScript:</h4>
                    <pre><code>// Autenticazione
async function authenticate() {
  const response = await fetch('<?php echo 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']); ?>/authenticate.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      client_id: 'il_tuo_client_id',
      auth_key: 'la_tua_auth_key'
    })
  });
  
  const data = await response.json();
  return data.token;
}

// Richiesta prezzi multipli
async function getPrices(token) {
  const response = await fetch('<?php echo 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']); ?>/prices.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({
      items: [
        { asin: 'B07PVCVBN7', country: 'it' },
        { asin: 'B08F9VSGNB', country: 'fr' }
      ]
    })
  });
  
  return await response.json();
}

// Utilizzo
authenticate()
  .then(token => getPrices(token))
  .then(data => console.log(data))
  .catch(error => console.error(error));</code></pre>
                </section>
                
                <footer class="mt-5 pt-5 text-muted border-top">
                    <p>&copy; <?php echo date('Y'); ?> Price API. Tutti i diritti riservati.</p>
                </footer>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Evidenziazione della sezione attiva durante lo scroll
        document.addEventListener('DOMContentLoaded', function() {
            const sections = document.querySelectorAll('section');
            const navLinks = document.querySelectorAll('.nav-link');
            
            window.addEventListener('scroll', function() {
                let current = '';
                
                sections.forEach(section => {
                    const sectionTop = section.offsetTop;
                    const sectionHeight = section.clientHeight;
                    if (pageYOffset >= (sectionTop - 100)) {
                        current = section.getAttribute('id');
                    }
                });
                
                navLinks.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === '#' + current) {
                        link.classList.add('active');
                    }
                });
            });
        });
    </script>
</body>
</html>