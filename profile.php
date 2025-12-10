<?php
require __DIR__ . '/app/session-config.php';
session_start();
require __DIR__ . '/app/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Tarkista että käyttäjä tulee sovelluksen sisältä
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$allowDirectAccess = isset($_SESSION['allow_profile_access']) && $_SESSION['allow_profile_access'] === true;

if (!$allowDirectAccess && (empty($referer) || strpos($referer, $_SERVER['HTTP_HOST']) === false)) {
    header("Location: index.php");
    exit;
}

// Salli tulevat pyynnöt tältä sessiolta
$_SESSION['allow_profile_access'] = true;

$uid = $_SESSION['user_id'];

// Hae käyttäjätiedot
$stmt = $conn->prepare("SELECT username, email FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $uid);
$stmt->execute();
$stmt->bind_result($username, $email);
$stmt->fetch();
$stmt->close();

function clean($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zombie Profile</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Veri -->
<div class="blood"></div>

<div class="container">

    <img src="assets/img/header-zombie.png.png" class="hero">

    <h1>ZOMBIE UPDATE</h1>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="auth-error"><?= clean($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="auth-success"><?= clean($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <!-- ======================== -->
    <!--  OMIEN TIETOJEN MUUTOS   -->
    <!-- ======================== -->
    <div class="auth-box">
        <h2 class="auth-title">Zombie Profiili</h2>

        <form method="POST" action="app/actions.php?action=update_profile" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= clean(generateCSRFToken()) ?>">

            <label>Käyttäjänimi (nykyinen: <?= clean($username) ?>)</label>
            <input type="text" name="username" value="" placeholder="Uusi käyttäjänimi" required autocomplete="off">

            <label>Sähköposti (nykyinen: <?= clean($email) ?>)</label>
            <input type="email" name="email" value="" placeholder="Uusi sähköposti" required autocomplete="off">

            <button type="submit">Tallenna muutokset 🧠</button>
        </form>

        <a href="index.php" class="header-link" style="display: inline-block; margin-top: 15px;">Takaisin&nbsp;⚰️</a>
    </div>

    <!-- ======================== -->
    <!--     SALASANAN VAIHTO     -->
    <!-- ======================== -->
    <div class="auth-box">
        <h2 class="auth-title">Vaihda salasana 🔒</h2>

        <form method="POST" action="app/actions.php?action=change_password">
            <input type="hidden" name="csrf_token" value="<?= clean(generateCSRFToken()) ?>">

            <label>Vanha salasana</label>
            <input type="password" name="old_password" required>

            <label>Uusi salasana</label>
            <input type="password" name="new_password" required>

            <label>Uusi salasana uudelleen</label>
            <input type="password" name="new_password2" required>

            <button type="submit">Vaihda salasana 🔒</button>
        </form>
    </div>

</div>

</body>
</html>
