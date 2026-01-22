<?php
/**
 * Weer App - Zonder CSS en emoji's
 * Met optioneel plaatsnaam veld en Nominatim geocoding
 */

$result = null;
$error = null;

if (isset($_GET['city']) || isset($_GET['location'])) {
    $city = $_GET['city'] ?? '';
    $location = $_GET['location'] ?? '';
    
    $lat = null;
    $lon = null;
    $name = null;

    // Als er een plaatsnaam is opgegeven, gebruik Nominatim
    if (!empty($location)) {
        $url_nominatim = 'https://nominatim.openstreetmap.org/search?q=' . urlencode($location) . '&format=json&limit=1';
        
        $ch_nom = curl_init($url_nominatim);
        curl_setopt_array($ch_nom, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'WeerApp/1.0'
        ]);
        
        $response_nom = curl_exec($ch_nom);
        $err_nom = curl_error($ch_nom);
        $code_nom = curl_getinfo($ch_nom, CURLINFO_HTTP_CODE);
        curl_close($ch_nom);
        
        if ($err_nom) {
            $error = "cURL-fout bij Nominatim: " . $err_nom;
        } elseif ($code_nom !== 200) {
            $error = "HTTP-fout bij Nominatim: Code " . $code_nom;
        } else {
            $data_nom = json_decode($response_nom, true);
            
            if (empty($data_nom)) {
                $error = "Plaatsnaam '$location' niet gevonden via Nominatim!";
            } else {
                $lat = $data_nom[0]['lat'];
                $lon = $data_nom[0]['lon'];
                $name = $data_nom[0]['display_name'];
            }
        }
    }
    
    // Als er geen plaatsnaam is, gebruik Open-Meteo geocoding
    if (empty($location) && !empty($city) && !$error) {
        // Stap 1: coördinaten zoeken
        $url1 = 'https://geocoding-api.open-meteo.com/v1/search?name=' . urlencode($city) . '&count=1&language=nl&format=json';

        $ch1 = curl_init($url1);
        curl_setopt_array($ch1, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response1 = curl_exec($ch1);
        $err1 = curl_error($ch1);
        $code1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
        curl_close($ch1);

        if ($err1) {
            $error = "cURL-fout bij zoeken: " . $err1;
        } elseif ($code1 !== 200) {
            $error = "HTTP-fout bij zoeken stad: Code " . $code1;
        } else {
            $data1 = json_decode($response1, true);

            if (!isset($data1['results'][0])) {
                $error = "Stad '$city' niet gevonden!";
            } else {
                $name = $data1['results'][0]['name'];
                $lat = $data1['results'][0]['latitude'];
                $lon = $data1['results'][0]['longitude'];
            }
        }
    }

    // Als we coördinaten hebben, haal het weer op
    if ($lat && $lon && !$error) {
        // Stap 2: weer ophalen
        $url2 = "https://api.open-meteo.com/v1/forecast"
            . "?latitude=$lat"
            . "&longitude=$lon"
            . "&current_weather=true";

        $ch2 = curl_init($url2);
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response2 = curl_exec($ch2);
        $err2 = curl_error($ch2);
        $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);

        if ($err2) {
            $error = "cURL-fout bij ophalen weer: " . $err2;
        } elseif ($code2 !== 200) {
            $error = "HTTP-fout bij ophalen weer: Code " . $code2;
        } else {
            $data2 = json_decode($response2, true);

            if (!isset($data2['current_weather'])) {
                $error = "Geen weerdata beschikbaar!";
            } else {
                $result = [
                    'city' => $name,
                    'lat' => $lat,
                    'lon' => $lon,
                    'temp' => $data2['current_weather']['temperature'],
                    'wind' => $data2['current_weather']['windspeed'],
                    'weathercode' => $data2['current_weather']['weathercode']
                ];
            }
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Weer App</title>
</head>
<body>

<h1>Weer App</h1>

<form method="GET">
    <p>
        <input type="text" name="city" placeholder="Stad naam" value="<?= htmlspecialchars($_GET['city'] ?? '') ?>">
    </p>
    <p>
        <input type="text" name="location" placeholder="Plaatsnaam (optioneel, gebruikt Nominatim)" value="<?= htmlspecialchars($_GET['location'] ?? '') ?>">
    </p>
    <p>
        <button type="submit">Zoek</button>
    </p>
    <p><small>Vul één van de twee velden in. Plaatsnaam heeft voorrang.</small></p>
</form>

<hr>

<?php if ($error): ?>
    <p><?= htmlspecialchars($error) ?></p>

<?php elseif ($result): ?>
    <h2><?= htmlspecialchars($result['city']) ?></h2>

    <p>Temperatuur: <?= htmlspecialchars($result['temp']) ?> °C</p>
    <p>Wind: <?= htmlspecialchars($result['wind']) ?> km/u</p>
    <p>Coördinaten: <?= round($result['lat'], 2) ?>, <?= round($result['lon'], 2) ?></p>

    <hr>

    <h3>Waarschuwingen</h3>

    <?php
    $hasWarning = false;

    if ($result['temp'] < 10):
        $hasWarning = true; ?>
        <p>Koud weer. Trek een warme jas aan.</p>
    <?php endif; ?>

    <?php
    if ($result['wind'] > 20):
        $hasWarning = true; ?>
        <p>Harde wind. Pas op met fietsen.</p>
    <?php endif; ?>

    <?php
    if ($result['weathercode'] >= 51 && $result['weathercode'] <= 65):
        $hasWarning = true; ?>
        <p>Regen. Neem een paraplu mee.</p>
    <?php endif; ?>

    <?php
    if ($result['weathercode'] >= 95):
        $hasWarning = true; ?>
        <p>Onweer. Blijf binnen als het kan.</p>
    <?php endif; ?>

    <?php if (!$hasWarning): ?>
        <p>Geen waarschuwingen. Het weer is goed.</p>
    <?php endif; ?>

<?php endif; ?>

</body>
</html>