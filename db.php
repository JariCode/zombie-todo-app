<?php
// Lataa .env muuttujat
$envFile = __DIR__ . "/.env";

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (str_starts_with(trim($line), "#")) continue;
        list($name, $value) = explode("=", $line, 2);
        $_ENV[$name] = trim($value);
    }
}

// Haetaan ENV-arvot
$host = $_ENV["DB_HOST"] ?? "localhost";
$user = $_ENV["DB_USER"] ?? "root";
$pass = $_ENV["DB_PASS"] ?? "";
$dbname = $_ENV["DB_NAME"] ?? "zombie_todo";

// 🔥 1: Yhdistetään ilman tietokantaa
$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("Tietokantavirhe (yhteys): " . $conn->connect_error);
}

// 🔥 2: Luodaan tietokanta jos ei ole olemassa
$conn->query("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

// 🔥 3: Valitaan tietokanta
$conn->select_db($dbname);

// 🔥 4: Luodaan taulu jos sitä ei ole
$createTableSQL = "
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    text VARCHAR(255) NOT NULL,
    status ENUM('not_started', 'in_progress', 'done') NOT NULL DEFAULT 'not_started',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
";

$conn->query($createTableSQL);
?>
