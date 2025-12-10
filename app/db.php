<?php
// ================================
// db.php
//
// Yhdistää tietokantaan ja luo taulut tarvittaessa
//
// Turvallisuus:
// - Ei koskaan tallenna salasanoja selkokielisenä
// - Kaikki yhteydet ja kyselyt käyttävät prepared statements
// - Ei session_start():ia tässä tiedostossa
// ================================

// Vältetään turhat mysqli-varoitukset (esim. IF NOT EXISTS)
mysqli_report(MYSQLI_REPORT_OFF);

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
// 6) LUODAAN USERS-TAULU
// ===========================================================
$conn->query("
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user','admin') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ===========================================================
// 7) LUODAAN TASKS-TAULU
// ===========================================================
$conn->query("
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    text VARCHAR(255) NOT NULL,
    status ENUM('not_started', 'in_progress', 'done') 
           NOT NULL DEFAULT 'not_started',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    done_at DATETIME NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ===========================================================
// 8) LUODAAN LOGS-TAULU
// ===========================================================
$conn->query("
CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event ENUM(
        'register',
        'login',
        'logout',
        'account_updated',
        'account_deleted_user',
        'password_reset_requested',
        'password_reset_completed',
        'account_deleted_admin',
        'role_changed'
    ) NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ===========================================================
// 9) LUODAAN PASSWORD_RESET -TAULU
// ===========================================================
$conn->query("
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ===========================================================
// 10) INDEKSIT SUORITUSKYKYYN
// ===========================================================
$conn->query("CREATE INDEX idx_user_status ON tasks (user_id, status)");
$conn->query("CREATE INDEX idx_created ON tasks (created_at)");
$conn->query("CREATE INDEX idx_logs_user ON logs (user_id)");
$conn->query("CREATE INDEX idx_password_resets_user ON password_resets (user_id)");

// ===========================================================
// 11) SESSION TIMEOUT VALIDAATIO
// ===========================================================
function validateSessionTimeout() {
    $timeout = 3600; // 1 tunti
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

// ===========================================================
// 12) CSRF TOKEN GENERAATTORI
// ===========================================================
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

?>
