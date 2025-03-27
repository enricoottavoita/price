<?php
// Impostazioni di base e eventuali include futuri
$page_title = "Priceradarcloud - Home";
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #f5f5f5;
        }

        .logo-container {
            text-align: center;
            padding: 20px;
        }

        .logo {
            max-width: 600px;
            width: 90%;
            height: auto;
            image-rendering: optimizeQuality;
        }
    </style>
</head>
<body>
    <div class="logo-container">
        <img src="logo.jpeg" alt="Priceradarcloud Price Comparison Platform" class="logo">
    </div>
</body>
</html>