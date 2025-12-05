<?php
// ================================
// actions.php
//
// Käsittelee kaikki kirjautumis-, rekisteröinti- ja tehtäväpyynnöt
//
// Turvallisuus:
// - session_config.php asettaa turvalliset session asetukset
// - session_start() aloittaa istunnon
// - validateSessionTimeout() uloskirjaa passiiviset käyttäjät
// - Kaikki SQL-kyselyt käyttävät prepared statements (estää SQL-injektion)
// - CSRF-token tarkistetaan kaikissa POST/GET-pyynnöissä
// - session_regenerate_id() estää session fixation -hyökkäykset
// - Logout tyhjentää ja tuhoaa session turvallisesti
// ================================

require __DIR__ . '/session-config.php';
session_start();

// Oikea polku uuteen kansiorakenteeseen
require __DIR__ . '/db.php';

// Validoi session timeout
if (!validateSessionTimeout() && $_GET['action'] !== 'login' && $_GET['action'] !== 'register') {
    $_SESSION['error'] = 'Istunto on vanhentunut. Kirjaudu uudelleen.';
    header('Location: ../index.php');
    exit;
}

$action = $_GET['action'] ?? null;

/* ============================================================
   Turvallinen tulostus
============================================================ */
function clean($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/* ============================================================
   1. REKISTERÖITYMINEN
============================================================ */
if ($action === "register") {

    // Validoi CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Turvallisuusvirhe. Yritä uudelleen.';
        header('Location: ../index.php');
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $terms    = isset($_POST['terms']);

    $_SESSION['old_username'] = $username;
    $_SESSION['old_email']    = $email;

    if (!$username || !$email || !$password) {
        $_SESSION['error'] = "Kaikki kentät ovat pakollisia!";
        header("Location: ../index.php");
        exit;
    }

    if (!$terms) {
        $_SESSION['error'] = "Sinun täytyy hyväksyä käyttöehdot ja tietosuojaseloste!";
        header("Location: ../index.php");
        exit;
    }

    if (
        strlen($password) < 10 ||
        !preg_match('/[A-ZÅÄÖ]/', $password) ||
        !preg_match('/[a-zåäö]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[!@#$%^&*()_\-+=\[\]{};:,.?]/', $password)
    ) {
        $_SESSION['error'] = "Salasanan tulee olla vähintään 10 merkkiä ja sisältää isoja ja pieniä kirjaimia, numeron ja erikoismerkin.";
        header("Location: ../index.php");
        exit;
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $_SESSION['error'] = "Sähköposti on jo käytössä!";
        header("Location: ../index.php");
        exit;
    }
    $stmt->close();

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $hash);
    $stmt->execute();
    $newUserId = $stmt->insert_id;
    $stmt->close();

    unset($_SESSION['old_username'], $_SESSION['old_email']);

    // Turvallinen session uudelleenluonti (session fixation -suoja)
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $newUserId;
    $_SESSION['username'] = $username;
    $_SESSION['last_activity'] = time();
    $_SESSION['success'] = "Tervetuloa, $username!";
    header("Location: ../index.php");
    exit;
}

/* ============================================================
   2. KIRJAUTUMINEN
============================================================ */
if ($action === "login") {

    // Validoi CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Turvallisuusvirhe. Yritä uudelleen.';
        header('Location: ../index.php');
        exit;
    }

    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $_SESSION['old_login_email'] = $email;

    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $_SESSION['error'] = "Väärä sähköposti tai salasana!";
        header("Location: ../index.php");
        exit;
    }

    $stmt->bind_result($uid, $username, $hash);
    $stmt->fetch();

    if (!password_verify($password, $hash)) {
        $_SESSION['error'] = "Väärä sähköposti tai salasana!";
        header("Location: ../index.php");
        exit;
    }

    unset($_SESSION['old_login_email']);

    // Turvallinen session uudelleenluonti (session fixation -suoja)
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $uid;
    $_SESSION['username'] = $username;
    $_SESSION['last_activity'] = time();
    $_SESSION['success'] = "Tervetuloa, $username!";
    header("Location: ../index.php");
    exit;
}

/* ============================================================
   3. LOGOUT
============================================================ */
if ($action === "logout") {
    // Turvallinen logout
    $_SESSION = array();
    if (ini_get('session.use_cookies') && isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 42000, '/', '', 
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? true : false), true);
    }
    session_destroy();
    header("Location: ../index.php");
    exit;
}

/* ============================================================
   4. TASK ACTIONS – vain kirjautuneena
============================================================ */
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "NOT LOGGED IN"]);
    exit;
}

// Validoi CSRF token tehtävätoiminnoille
if (in_array($action, ['add', 'start', 'done', 'undo_start', 'undo_done', 'delete'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "CSRF TOKEN INVALID"]);
        exit;
    }
}

$user_id = intval($_SESSION['user_id']);
$id = intval($_GET['id'] ?? 0);

/* ============================================================
   ADD TASK
============================================================ */
if ($action === "add") {
    $task = trim($_POST['task'] ?? '');

    if ($task === '') {
        echo json_encode(['success' => false]);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO tasks (user_id, text, status, created_at)
        VALUES (?, ?, 'not_started', NOW())
    ");
    $stmt->bind_param("is", $user_id, $task);
    $stmt->execute();

    echo json_encode(['success' => true]);
    exit;
}

/* ============================================================
   MERKITSE ALOITETUKSI
============================================================ */
if ($action === "start" && $id > 0) {
    $stmt = $conn->prepare("
        UPDATE tasks
        SET status='in_progress', started_at = NOW()
        WHERE id=? AND user_id=?
    ");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();

    echo json_encode(['success' => true]);
    exit;
}

/* ============================================================
   MERKITSE VALMIIKSI
============================================================ */
if ($action === "done" && $id > 0) {
    $stmt = $conn->prepare("
        UPDATE tasks
        SET status='done', done_at = NOW()
        WHERE id=? AND user_id=?
    ");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();

    echo json_encode(['success' => true]);
    exit;
}

/* ============================================================
   UNDO: Käynnissä → Ei aloitettu
============================================================ */
if ($action === "undo_start" && $id > 0) {
    $stmt = $conn->prepare("
        UPDATE tasks
        SET status='not_started', started_at = NULL
        WHERE id=? AND user_id=?
    ");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();

    echo json_encode(['success' => true]);
    exit;
}

/* ============================================================
   UNDO: Valmis → Käynnissä
============================================================ */
if ($action === "undo_done" && $id > 0) {
    $stmt = $conn->prepare("
        UPDATE tasks
        SET status='in_progress', done_at = NULL
        WHERE id=? AND user_id=?
    ");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();

    echo json_encode(['success' => true]);
    exit;
}

/* ============================================================
   DELETE TASK
============================================================ */
if ($action === "delete" && $id > 0) {
    $stmt = $conn->prepare("DELETE FROM tasks WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();

    echo json_encode(['success' => true]);
    exit;
}

/* ============================================================
   FALLBACK
============================================================ */
echo json_encode(['success' => false, 'error' => 'Unknown action']);
exit;
