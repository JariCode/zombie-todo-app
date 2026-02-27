<?php
// ================================
// actions.php
//
// Korjaukset:
// - CSRF-token luetaan vain POST-bodysta tai headerista (ei enää URL:sta)
// - CSRF-token uusitaan session_regenerate_id():n jälkeen
// - Rate limiting kirjautumiseen (5 yritystä / 5 min)
// - Referer-tarkistus poistettu profile.php-ohjauksesta (ei luotettava)
// ================================

require __DIR__ . '/session-config.php';
session_start();
require __DIR__ . '/db.php';

if (!validateSessionTimeout() && $_GET['action'] !== 'login' && $_GET['action'] !== 'register') {
    $_SESSION['error'] = 'Istunto on vanhentunut. Kirjaudu uudelleen.';
    header('Location: ../index.php');
    exit;
}

$action = $_GET['action'] ?? null;

/* ============================================================
   Lokitusfunktio
============================================================ */
function logEvent($userId, $event) {
    global $conn;
    if (!$userId) return;

    $stmt = $conn->prepare("INSERT INTO logs (user_id, event) VALUES (?, ?)");
    $stmt->bind_param("is", $userId, $event);
    $stmt->execute();
    $stmt->close();
}

/* ============================================================
   Turvallinen tulostus
============================================================ */
function clean($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/* ============================================================
   CSRF: lue vain POST-bodysta tai X-CSRF-Token-headerista
   EI ENÄÄ GET-parametristä (estää token-vuodon logeissa)
============================================================ */
function getSubmittedCSRF() {
    return $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';
}

/* ============================================================
   1. REKISTERÖITYMINEN
============================================================ */
if ($action === "register") {

    if (!verifyCSRFToken(getSubmittedCSRF())) {
        $_SESSION['error'] = 'Turvallisuusvirhe. Yritä uudelleen.';
        header('Location: ../index.php');
        exit;
    }

    $username         = trim($_POST['username'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = trim($_POST['password'] ?? '');
    $password_confirm = trim($_POST['password_confirm'] ?? '');
    $terms            = isset($_POST['terms']);

    $_SESSION['old_username'] = $username;
    $_SESSION['old_email']    = $email;

    if (!$username || !$email || !$password || !$password_confirm) {
        $_SESSION['error'] = "Kaikki kentät ovat pakollisia!";
        header("Location: ../index.php");
        exit;
    }

    if ($password !== $password_confirm) {
        $_SESSION['error'] = "Salasanat eivät täsmää!";
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

    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')");
    $stmt->bind_param("sss", $username, $email, $hash);
    $stmt->execute();
    $newUserId = $stmt->insert_id;
    $stmt->close();

    unset($_SESSION['old_username'], $_SESSION['old_email']);

    session_regenerate_id(true);

    // Korjaus: uusi CSRF-token session uusinnan jälkeen
    generateCSRFToken(true);

    $_SESSION['user_id']       = $newUserId;
    $_SESSION['username']      = $username;
    $_SESSION['last_activity'] = time();

    logEvent($newUserId, "register");

    header("Location: ../index.php");
    exit;
}

/* ============================================================
   2. KIRJAUTUMINEN
============================================================ */
if ($action === "login") {

    if (!verifyCSRFToken(getSubmittedCSRF())) {
        $_SESSION['error'] = 'Turvallisuusvirhe. Yritä uudelleen.';
        header('Location: ../index.php');
        exit;
    }

    // --- Rate limiting: max 5 yritystä per 5 minuuttia ---
    $now = time();
    $attempts  = $_SESSION['login_attempts'] ?? 0;
    $lastTry   = $_SESSION['login_last_attempt'] ?? 0;
    $lockoutDuration = 300; // 5 min
    $maxAttempts     = 5;

    if ($attempts >= $maxAttempts) {
        $elapsed = $now - $lastTry;
        if ($elapsed < $lockoutDuration) {
            $wait = ceil(($lockoutDuration - $elapsed) / 60);
            $_SESSION['error'] = "Liian monta epäonnistunutta yritystä. Odota {$wait} minuuttia.";
            header('Location: ../index.php');
            exit;
        }
        // Lukitusaika on kulunut — nollaa laskuri
        $_SESSION['login_attempts']      = 0;
        $_SESSION['login_last_attempt']  = 0;
    }
    // --- Rate limiting loppu ---

    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $_SESSION['old_login_email'] = $email;

    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $_SESSION['login_attempts']     = ($attempts + 1);
        $_SESSION['login_last_attempt'] = $now;
        $_SESSION['error'] = "Väärä sähköposti tai salasana!";
        header("Location: ../index.php");
        exit;
    }

    $stmt->bind_result($uid, $username, $hash);
    $stmt->fetch();
    $stmt->close();

    if (!password_verify($password, $hash)) {
        $_SESSION['login_attempts']     = ($attempts + 1);
        $_SESSION['login_last_attempt'] = $now;
        $_SESSION['error'] = "Väärä sähköposti tai salasana!";
        header("Location: ../index.php");
        exit;
    }

    // Kirjautuminen onnistui — nollaa yritykset
    unset(
        $_SESSION['login_attempts'],
        $_SESSION['login_last_attempt'],
        $_SESSION['old_login_email']
    );

    session_regenerate_id(true);

    // Korjaus: uusi CSRF-token session uusinnan jälkeen
    generateCSRFToken(true);

    $_SESSION['user_id']       = $uid;
    $_SESSION['username']      = $username;
    $_SESSION['last_activity'] = $now;

    logEvent($uid, "login");

    header("Location: ../index.php");
    exit;
}

/* ============================================================
   3. LOGOUT
============================================================ */
if ($action === "logout") {

    logEvent($_SESSION['user_id'] ?? 0, "logout");

    $_SESSION = array();
    if (ini_get('session.use_cookies') && isset($_COOKIE[session_name()])) {
        setcookie(
            session_name(), '', time() - 42000, '/', '',
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
            true
        );
    }
    session_destroy();

    header("Location: ../index.php");
    exit;
}

/* ============================================================
   4. UPDATE PROFILE
============================================================ */
if ($action === "update_profile") {

    if (!verifyCSRFToken(getSubmittedCSRF())) {
        $_SESSION['error'] = "Turvallisuusvirhe.";
        header("Location: ../profile.php");
        exit;
    }

    $user_id  = intval($_SESSION['user_id']);
    $username = trim($_POST["username"] ?? '');
    $email    = trim($_POST["email"] ?? '');

    if (!$username || !$email) {
        $_SESSION['error'] = "Nimi ja sähköposti ovat pakollisia!";
        header("Location: ../profile.php");
        exit;
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=? LIMIT 1");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $_SESSION['error'] = "Sähköposti on jo käytössä!";
        header("Location: ../profile.php");
        exit;
    }
    $stmt->close();

    $stmt = $conn->prepare("UPDATE users SET username=?, email=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("ssi", $username, $email, $user_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['username'] = $username;

    logEvent($user_id, "account_updated");

    $_SESSION['success'] = "Tiedot päivitetty onnistuneesti! 🧠";
    header("Location: ../profile.php");
    exit;
}

/* ============================================================
   5. CHANGE PASSWORD
============================================================ */
if ($action === "change_password") {

    if (!verifyCSRFToken(getSubmittedCSRF())) {
        $_SESSION['error'] = "Turvallisuusvirhe.";
        header("Location: ../profile.php");
        exit;
    }

    $user_id = intval($_SESSION['user_id']);
    $old     = trim($_POST["old_password"] ?? '');
    $new     = trim($_POST["new_password"] ?? '');
    $new2    = trim($_POST["new_password2"] ?? '');

    if (!$old || !$new || !$new2) {
        $_SESSION['error'] = "Kaikki salasanakentät ovat pakollisia!";
        header("Location: ../profile.php");
        exit;
    }

    $stmt = $conn->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($current_hash);
    $stmt->fetch();
    $stmt->close();

    if (!password_verify($old, $current_hash)) {
        $_SESSION['error'] = "Vanha salasana on väärä!";
        header("Location: ../profile.php");
        exit;
    }

    if ($new !== $new2) {
        $_SESSION['error'] = "Uudet salasanat eivät täsmää!";
        header("Location: ../profile.php");
        exit;
    }

    if (
        strlen($new) < 10 ||
        !preg_match('/[A-ZÅÄÖ]/', $new) ||
        !preg_match('/[a-zåäö]/', $new) ||
        !preg_match('/[0-9]/', $new) ||
        !preg_match('/[!@#$%^&*()_\-+=\[\]{};:,.?]/', $new)
    ) {
        $_SESSION['error'] = "Uusi salasana ei täytä vaatimuksia!";
        header("Location: ../profile.php");
        exit;
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("si", $hash, $user_id);
    $stmt->execute();
    $stmt->close();

    logEvent($user_id, "account_updated");

    $_SESSION['success'] = "Salasana vaihdettu onnistuneesti! 🔒";
    header("Location: ../profile.php");
    exit;
}

/* ============================================================
   6. DELETE ACCOUNT
============================================================ */
if ($action === "delete_account") {

    if (!verifyCSRFToken(getSubmittedCSRF())) {
        $_SESSION['error'] = "Turvallisuusvirhe.";
        header("Location: ../profile.php");
        exit;
    }

    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = "Kirjaudu sisään.";
        header("Location: ../index.php");
        exit;
    }

    $user_id          = intval($_SESSION['user_id']);
    $confirm_username = trim($_POST['confirm_username'] ?? '');
    $confirm_email    = trim($_POST['confirm_email'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (!$confirm_username || !$confirm_email || !$confirm_password) {
        $_SESSION['error'] = "Kaikki kentät ovat pakollisia.";
        header("Location: ../profile.php");
        exit;
    }

    $stmt = $conn->prepare("SELECT username, email, password FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($db_username, $db_email, $db_hash);
    $stmt->fetch();
    $stmt->close();

    if (!$db_username) {
        $_SESSION['error'] = "Käyttäjää ei löydy.";
        header("Location: ../profile.php");
        exit;
    }

    if (strcasecmp($confirm_username, $db_username) !== 0 || strcasecmp($confirm_email, $db_email) !== 0) {
        $_SESSION['error'] = "Käyttäjänimi tai sähköposti ei täsmää.";
        header("Location: ../profile.php");
        exit;
    }

    if (!password_verify($confirm_password, $db_hash)) {
        $_SESSION['error'] = "Salasana on väärä.";
        header("Location: ../profile.php");
        exit;
    }

    // Poistetaan data transaktiossa (ON DELETE CASCADE hoitaa tasks ja logs,
    // mutta eksplisiittinen poisto on selkeämpää)
    $conn->begin_transaction();
    $ok = true;

    foreach (["DELETE FROM tasks WHERE user_id=?", "DELETE FROM logs WHERE user_id=?", "DELETE FROM users WHERE id=?"] as $sql) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $ok = $ok && $stmt->execute();
            $stmt->close();
        } else {
            $ok = false;
        }
    }

    if ($ok) {
        $conn->commit();
    } else {
        $conn->rollback();
        $_SESSION['error'] = "Tilin poistossa tapahtui virhe.";
        header("Location: ../profile.php");
        exit;
    }

    $_SESSION = array();
    if (ini_get('session.use_cookies') && isset($_COOKIE[session_name()])) {
        setcookie(
            session_name(), '', time() - 42000, '/', '',
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
            true
        );
    }
    session_destroy();

    header("Location: ../index.php?deleted=1");
    exit;
}

/* ============================================================
   7. TASK ACTIONS — vain kirjautuneena
============================================================ */
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "NOT LOGGED IN"]);
    exit;
}

// Korjaus: CSRF luetaan vain POST-bodysta tai headerista
if (in_array($action, ['add', 'start', 'done', 'undo_start', 'undo_done', 'delete'])) {
    if (!verifyCSRFToken(getSubmittedCSRF())) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "CSRF TOKEN INVALID"]);
        exit;
    }
}

$user_id = intval($_SESSION['user_id']);
$id      = intval($_GET['id'] ?? 0);

/* ---- 7.1 ADD TASK ---- */
if ($action === "add") {
    $task = trim($_POST['task'] ?? '');
    if ($task === '') { echo json_encode(['success' => false]); exit; }

    $stmt = $conn->prepare("INSERT INTO tasks (user_id, text, status, created_at) VALUES (?, ?, 'not_started', NOW())");
    $stmt->bind_param("is", $user_id, $task);
    $stmt->execute();

    echo json_encode(['success' => true]);
    exit;
}

/* ---- 7.2 ALOITA ---- */
if ($action === "start" && $id > 0) {
    $stmt = $conn->prepare("UPDATE tasks SET status='in_progress', started_at=NOW() WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

/* ---- 7.3 VALMIS ---- */
if ($action === "done" && $id > 0) {
    $stmt = $conn->prepare("UPDATE tasks SET status='done', done_at=NOW() WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

/* ---- 7.4 UNDO START ---- */
if ($action === "undo_start" && $id > 0) {
    $stmt = $conn->prepare("UPDATE tasks SET status='not_started', started_at=NULL WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

/* ---- 7.5 UNDO DONE ---- */
if ($action === "undo_done" && $id > 0) {
    $stmt = $conn->prepare("UPDATE tasks SET status='in_progress', done_at=NULL WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

/* ---- 7.6 DELETE ---- */
if ($action === "delete" && $id > 0) {
    $stmt = $conn->prepare("DELETE FROM tasks WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

/* ---- 7.7 FALLBACK ---- */
echo json_encode(['success' => false, 'error' => 'Unknown action']);
exit;
