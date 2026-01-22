<?php
// API basis URL
$apiUrl = "https://st1738846851.splsites.nl/api.php";

// Foutmelding
$error = "";

   //PRODUCT TOEVOEGEN (POST)
   
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $data = [
        "naam" => $_POST["naam"],
        "prijs" => (float) $_POST["prijs"]
    ];

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    // API foutmodel controleren
    if (isset($result["error"])) {
        $error = $result["error"];
    }
}

 //  PRODUCT VERWIJDEREN (DELETE)
   
if (isset($_GET["delete"])) {

    $id = intval($_GET["delete"]);

    $ch = curl_init("$apiUrl/$id");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_exec($ch);
    curl_close($ch);
}


  // PRODUCTEN OPHALEN / ZOEKEN (GET)
   

// Zoekterm ophalen
$zoek = $_GET["zoek"] ?? "";

// API URL opbouwen
$url = $apiUrl;
if (!empty($zoek)) {
    // Je mag 'naam' of 'zoek' gebruiken → beide zijn toegestaan
    $url .= "?zoek=" . urlencode($zoek);
}

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

// Resultaat verwerken
$producten = json_decode($response, true);

// Foutmodel checken
if (isset($producten["error"])) {
    $error = $producten["error"];
    $producten = [];
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Producten</title>
    <style>
        table { border-collapse: collapse; width: 60%; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        th { background: #eee; }
        form { margin-bottom: 15px; }
    </style>
</head>
<body>

<h2>Product toevoegen</h2>

<!-- Foutmelding -->
<?php if ($error): ?>
    <p style="color:red;"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<!-- POST formulier -->
<form method="post">
    Naam:
    <input type="text" name="naam" required><br><br>

    Prijs:
    <input type="number" name="prijs" step="0.01" required><br><br>

    <button type="submit">Toevoegen</button>
</form>

<h2>Producten</h2>

<!-- Zoekformulier (GET) -->
<form method="get">
    <input type="text" name="zoek"
           placeholder="Zoek op naam..."
           value="<?= htmlspecialchars($zoek) ?>">
    <button type="submit">Zoeken</button>
    <a href="index.php">Reset</a>
</form>

<table>
    <tr>
        <th>ID</th>
        <th>Naam</th>
        <th>Prijs</th>
        <th>Actie</th>
    </tr>

    <?php if (!empty($producten)): ?>
        <?php foreach ($producten as $product): ?>
            <tr>
                <td><?= $product["id"] ?></td>
                <td><?= htmlspecialchars($product["naam"]) ?></td>
                <td>€<?= number_format($product["prijs"], 2, ',', '.') ?></td>
                <td>
                    <a href="?delete=<?= $product["id"] ?>"
                       onclick="return confirm('Product verwijderen?')">
                        Verwijderen
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="4">Geen producten gevonden</td>
        </tr>
    <?php endif; ?>
</table>

</body>
</html>

