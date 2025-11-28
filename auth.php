<?php
session_start();
require 'db.php';

$action = $_GET['action'] ?? null;

// Jos action ei ole olemassa → takaisin
if (!$action) {
    header("Location: index.php");
    exit;
}

/* ================================
   1) REKISTERÖITYMINEN
================================ */
if ($action === "register") {

    if (!isset($_POST['username'], $_POST['email'], $_POST['password'])) {
        header("Location: index.php");
        exit;
    }

    $username = htmlspecialchars(trim($_POST['username']));
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    // Tarkista että email ei ole käytössä
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        // TODO: lisää tyylikäs virheilmoitus UI:hin
        header("Location: index.php?error=email_used");
        exit;
    }
    $check->close();

    // Salasanan hash
    $hashed = password_hash($password, PASSWORD_DEFAULT);

    // Lisää käyttäjä
    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $hashed);
    $stmt->execute();

    // Uusi käyttäjän ID
    $uid = $stmt->insert_id;
    $stmt->close();

    // Automaattinen turvallinen sisäänkirjautuminen
    session_regenerate_id(true);
    $_SESSION['user_id'] = $uid;

    header("Location: index.php");
    exit;
}

/* ================================
   2) KIRJAUTUMINEN
================================ */
if ($action === "login") {

    if (!isset($_POST['email'], $_POST['password'])) {
        header("Location: index.php");
        exit;
    }

    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($uid, $hash);

    if ($stmt->num_rows === 1) {
        $stmt->fetch();

        if (password_verify($password, $hash)) {

            session_regenerate_id(true);
            $_SESSION['user_id'] = $uid;

            $stmt->close();
            header("Location: index.php");
            exit;
        }
    }

    $stmt->close();
    header("Location: index.php?error=login_failed");
    exit;
}

/* ================================
   3) ULOSKIRJAUTUMINEN
================================ */
if ($action === "logout") {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Jos mikään yllä ei osunut → takaisin
header("Location: index.php");
exit;
