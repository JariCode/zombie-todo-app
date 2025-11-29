<?php
session_start();
require 'db.php';

$action = $_GET['action'] ?? null;

/* ============================================================
   Puhdistusfunktio (turvallinen tulostukseen)
============================================================ */
function clean($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/* ============================================================
   1. REKISTERÖITYMINEN
============================================================ */
if ($action === "register") {

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $terms    = isset($_POST['terms']);

    // Tallenna vanhat tiedot SESSIONiin virhettä varten
    $_SESSION['old_username'] = $username;
    $_SESSION['old_email']    = $email;

    // --- Kenttätyhjät? ---
    if (!$username || !$email || !$password) {
        $_SESSION['error'] = "Kaikki kentät ovat pakollisia!";
        header("Location: index.php");
        exit;
    }

    // --- Terms-checkbox ---
    if (!$terms) {
        $_SESSION['error'] = "Sinun täytyy hyväksyä käyttöehdot ja tietosuojaseloste!";
        header("Location: index.php");
        exit;
    }

    // --- Salasana: väh. 10 merkkiä, iso/pieni kirjain, numero ja erikoismerkki ---
    if (
        strlen($password) < 10 ||
        !preg_match('/[A-ZÅÄÖ]/', $password) ||
        !preg_match('/[a-zåäö]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[!@#$%^&*()_\-+=\[\]{};:,.?]/', $password)
    ) {
        $_SESSION['error'] = "Salasanan tulee olla vähintään 10 merkkiä ja sisältää isoja ja pieniä kirjaimia, numeron ja erikoismerkin.";
        header("Location: index.php");
        exit;
    }

    // Tarkista että email ei ole käytössä
    $stmt = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $_SESSION['error'] = "Sähköposti on jo käytössä!";
        header("Location: index.php");
        exit;
    }
    $stmt->close();

    // Luo käyttäjä
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $hash);
    $stmt->execute();
    $newUserId = $stmt->insert_id;
    $stmt->close();

    // Puhdista session "old_"
    unset($_SESSION['old_username'], $_SESSION['old_email']);

    $_SESSION['user_id'] = $newUserId;
    $_SESSION['success'] = "Tervetuloa, $username!";
    header("Location: index.php");
    exit;
}


/* ============================================================
   2. KIRJAUTUMINEN
============================================================ */
if ($action === "login") {

    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Säilytä sähköposti virhetilanteessa
    $_SESSION['old_login_email'] = $email;

    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $_SESSION['error'] = "Väärä sähköposti tai salasana!";
        header("Location: index.php");
        exit;
    }

    $stmt->bind_result($uid, $username, $hash);
    $stmt->fetch();

    if (!password_verify($password, $hash)) {
        $_SESSION['error'] = "Väärä sähköposti tai salasana!";
        header("Location: index.php");
        exit;
    }

    unset($_SESSION['old_login_email']);

    $_SESSION['user_id'] = $uid;
    $_SESSION['success'] = "Tervetuloa, $username!";
    header("Location: index.php");
    exit;
}


/* ============================================================
   3. LOGOUT
============================================================ */
if ($action === "logout") {
    session_destroy();
    header("Location: index.php");
    exit;
}


/* ============================================================
   4. TASK ACTIONS (vain kirjautunut)
============================================================ */
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "NOT LOGGED IN";
    exit;
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

    $stmt = $conn->prepare("INSERT INTO tasks (user_id, text, status) VALUES (?, ?, 'not_started')");
    $stmt->bind_param("is", $user_id, $task);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
    exit;
}


/* ============================================================
   TASK STATUS CHANGES
============================================================ */
if ($action === "start" && $id > 0) {
    $stmt = $conn->prepare("UPDATE tasks SET status='in_progress' WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === "done" && $id > 0) {
    $stmt = $conn->prepare("UPDATE tasks SET status='done' WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === "delete" && $id > 0) {
    $stmt = $conn->prepare("DELETE FROM tasks WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false]);
exit;
