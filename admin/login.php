<?php
session_start();

// Se l'utente è già loggato, reindirizza alla dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

// Includi file di configurazione e database
require_once '../includes/config.php';
require_once '../includes/database.php';

// Inizializza variabili
$username = $password = "";
$username_err = $password_err = $login_err = "";

// Processa il form quando viene inviato
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Valida username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Inserisci il nome utente.";
    } else {
        $username = trim($_POST["username"]);
    }
    
    // Valida password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Inserisci la password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Verifica credenziali
    if (empty($username_err) && empty($password_err)) {
        // Connessione al database
        $db = new Database();
        $conn = $db->getConnection();
        
        // Prepara query
        $sql = "SELECT id, username, password FROM admin_users WHERE username = :username";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind parametri
            $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);
            
            // Set parametri
            $param_username = $username;
            
            // Esegui query
            if ($stmt->execute()) {
                // Verifica se l'username esiste
                if ($stmt->rowCount() == 1) {
                    if ($row = $stmt->fetch()) {
                        $id = $row["id"];
                        $username = $row["username"];
                        $hashed_password = $row["password"];
                        
                        // Verifica password
                        if (password_verify($password, $hashed_password)) {
                            // Password corretta, inizia sessione
                            session_regenerate_id(true); // Previene session fixation
                            
                            // Salva dati in variabili di sessione
                            $_SESSION["admin_logged_in"] = true;
                            $_SESSION["admin_id"] = $id;
                            $_SESSION["admin_username"] = $username;
                            
                            // Reindirizza alla dashboard
                            header("location: index.php");
                        } else {
                            // Password sbagliata
                            $login_err = "Username o password non validi.";
                        }
                    }
                } else {
                    // Username non trovato
                    $login_err = "Username o password non validi.";
                }
            } else {
                $login_err = "Errore di database. Riprova più tardi.";
            }
            
            // Chiudi statement
            unset($stmt);
        }
        
        // Chiudi connessione
        unset($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Price API Admin</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
        }
        body {
            display: flex;
            align-items: center;
            padding-top: 40px;
            padding-bottom: 40px;
            background-color: #f5f5f5;
        }
        .form-signin {
            width: 100%;
            max-width: 330px;
            padding: 15px;
            margin: auto;
        }
        .form-signin .form-floating:focus-within {
            z-index: 2;
        }
        .form-signin input[type="text"] {
            margin-bottom: -1px;
            border-bottom-right-radius: 0;
            border-bottom-left-radius: 0;
        }
        .form-signin input[type="password"] {
            margin-bottom: 10px;
            border-top-left-radius: 0;
            border-top-right-radius: 0;
        }
        .login-logo {
            width: autopx;
            height: autopx;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="text-center">
    <main class="form-signin">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <img class="login-logo" src="assets/img/logo.png" alt="Logo" onerror="this.src='https://via.placeholder.com/72x72?text=API'">
            <h1 class="h3 mb-3 fw-normal">Price API Admin</h1>
            
            <?php if (!empty($login_err)) : ?>
                <div class="alert alert-danger"><?php echo $login_err; ?></div>
            <?php endif; ?>
            
            <div class="form-floating">
                <input type="text" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" id="username" name="username" placeholder="Nome utente" value="<?php echo $username; ?>">
                <label for="username">Nome utente</label>
                <div class="invalid-feedback"><?php echo $username_err; ?></div>
            </div>
            
            <div class="form-floating">
                <input type="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" id="password" name="password" placeholder="Password">
                <label for="password">Password</label>
                <div class="invalid-feedback"><?php echo $password_err; ?></div>
            </div>
            
            <button class="w-100 btn btn-lg btn-primary" type="submit">Accedi</button>
            <p class="mt-5 mb-3 text-muted">&copy; <?php echo date('Y'); ?> Price API</p>
        </form>
    </main>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
