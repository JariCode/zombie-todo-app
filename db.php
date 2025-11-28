<?php
// ===========================================================
// 1) LATAA .ENV TIEDOSTO TURVALLISESTI
// ===========================================================
$envFile = __DIR__ . "/.env";

if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {

        $line = trim($line);

        if ($line === "" || str_starts_with($line, "#")) continue;
        if (!str_contains($line, "=")) continue;

        list($key, $value) = explode("=", $line, 2);
        $value = trim($value, "\"' ");

        $_ENV[$key] = $value;
    }
}

// ===========================================================
// 2) TIETOKANTA-ASETUKSET (.ENV TAI OLETUKSET)
// ===========================================================
$host   = $_ENV["DB_HOST"] ?? "localhost";
$user   = $_ENV["DB_USER"] ?? "root";
$pass   = $_ENV["DB_PASS"] ?? "";
$dbname = $_ENV["DB_NAME"] ?? "zombie_todo";

// ===========================================================
// 3) YHDISTYS ILMAN TIETOKANTAA
// ===========================================================
$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("Tietokantavirhe: " . $conn->connect_error);
}

// ===========================================================
// 4) LUODAAN TIETOKANTA JOS PUUTTUU
// ===========================================================
$conn->query("
    CREATE DATABASE IF NOT EXISTS `$dbname`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci
");

// ===========================================================
// 5) VALITAAN TIETOKANTA
// ===========================================================
$conn->select_db($dbname);

// ===========================================================
// 6) LUODAAN USERS -TAULU (UUTENA JA PUHTAANA)
// ===========================================================
$conn->query("
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ===========================================================
// 7) LUODAAN TASKS -TAULU (KÄYTTÄJÄKOHTAINEN, VIITEAVAIN)
// ===========================================================
$conn->query("
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    text VARCHAR(255) NOT NULL,
    status ENUM('not_started', 'in_progress', 'done') NOT NULL DEFAULT 'not_started',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ===========================================================
// 8) INDEKSIT SUORITUSKYVYN PARANTAMISEEN
// ===========================================================
$conn->query("CREATE INDEX IF NOT EXISTS idx_user_status ON tasks (user_id, status)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_created ON tasks (created_at)");

?>
