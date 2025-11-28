<?php
session_start();
require 'db.php';

$action = $_GET['action'] ?? null;

/* ============================================================
   1. REKISTERÖITYMINEN
============================================================ */
if ($action === "register") {

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$username || !$email || !$password) {
        $_SESSION['error'] = "Kaikki kentät ovat pakollisia!";
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

    // Kirjautuminen onnistui
    $_SESSION['user_id'] = $uid;
    $_SESSION['success'] = "Tervetuloa, $username!";
    header("Location: index.php");
    exit;
}



/* ============================================================
   3. ULOSKIRJAUTUMINEN
============================================================ */
if ($action === "logout") {
    session_destroy();
    header("Location: index.php");
    exit;
}


/* ============================================================
   4. TEHTÄVÄTOIMINNOT (VAIN KIRJAUTUNEILLE)
============================================================ */
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "NOT LOGGED IN";
    exit;
}

$user_id = intval($_SESSION['user_id']);
$id = intval($_GET['id'] ?? 0);


/* ============================================================
   LISÄÄ TEHTÄVÄ
============================================================ */
if ($action === "add") {

    $task = trim($_POST['task'] ?? '');

    if ($task === '') {
        $_SESSION['error'] = "Tehtävä ei voi olla tyhjä!";
        header("Location: index.php");
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO tasks (user_id, text, status) VALUES (?, ?, 'not_started')");
    $stmt->bind_param("is", $user_id, $task);
    $stmt->execute();
    $stmt->close();

    $_SESSION['success'];
    header("Location: index.php");
    exit;
}


/* ============================================================
   MERKITSE ALOITETUKSI
============================================================ */
if ($action === "start" && $id > 0) {

    $stmt = $conn->prepare("UPDATE tasks SET status='in_progress' WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
    exit;
}


/* ============================================================
   MERKITSE VALMIIKSI
============================================================ */
if ($action === "done" && $id > 0) {

    $stmt = $conn->prepare("UPDATE tasks SET status='done' WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
    exit;
}


/* ============================================================
   POISTA TEHTÄVÄ
============================================================ */
if ($action === "delete" && $id > 0) {

    $stmt = $conn->prepare("DELETE FROM tasks WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false]);
exit;
