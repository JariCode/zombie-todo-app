<?php
require 'vendor/autoload.php';

// Ladataan .env jos se löytyy projektista
$envFile = __DIR__ . '/.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        putenv($line);
    }
}

// Haetaan MongoDB-yhteys osoite
$mongoUri = getenv("MONGO_URI");

if (!$mongoUri) {
    die("Virhe: MONGO_URI puuttuu. Lisää se .env-tiedostoon tai Renderin environment-muuttujiin.");
}

// Luo MongoDB-clientti
$client = new MongoDB\Client($mongoUri);

// Valitse tietokanta ja kokoelma
$db = $client->selectDatabase("zombie_todo");
$collection = $db->selectCollection("tasks");
?>
