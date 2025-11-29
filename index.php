<?php
session_start();
require 'db.php';

// Jos kirjautunut, haetaan tehtävät
if (isset($_SESSION['user_id'])) {
    $uid = intval($_SESSION['user_id']);

    $notStarted = $conn->query("SELECT * FROM tasks WHERE user_id=$uid AND status='not_started' ORDER BY id DESC");
    $inProgress = $conn->query("SELECT * FROM tasks WHERE user_id=$uid AND status='in_progress' ORDER BY id DESC");
    $doneTasks  = $conn->query("SELECT * FROM tasks WHERE user_id=$uid AND status='done' ORDER BY id DESC");
}
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <title>Zombi To-Do</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Veri -->
<div class="blood"></div>

<div class="container">

    <img src="assets/img/header-zombie.png.png" class="hero">

    <!-- VIRHE- JA ONNISTUMISVIESTIT -->
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="auth-error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="auth-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if (!isset($_SESSION['user_id'])): ?>

    <!-- =========================== -->
    <!--          LOGIN BOX         -->
    <!-- =========================== -->

    <h1>ZOMBIE LOGIN</h1>

    <div class="auth-box">
        <h2 class="auth-title">Kirjaudu sisään</h2>

        <form method="POST" action="actions.php?action=login" autocomplete="off">

            <label>Sähköposti</label>
            <input type="email" name="email" placeholder="example@domain.com" required autocomplete="off">

            <label>Salasana</label>
            <input type="password" name="password" placeholder="********" required autocomplete="off">

            <button type="submit">Kirjaudu sisään 🔑</button>
        </form>
    </div>

    <div class="auth-separator">TAI LUO TILI</div>

    <!-- =========================== -->
    <!--        REGISTER BOX         -->
    <!-- =========================== -->

    <div class="auth-box">
        <h2 class="auth-title">Rekisteröidy</h2>

        <form method="POST" action="actions.php?action=register" autocomplete="off">

            <label>Käyttäjänimi</label>
            <input type="text" name="username" placeholder="ZombieMaster91" required autocomplete="off">

            <label>Sähköposti</label>
            <input type="email" name="email" placeholder="example@domain.com" required autocomplete="off">

            <label>Salasana</label>
            <input type="password" name="password" placeholder="********" required autocomplete="off">

                <!-- Ruksi tietosuojaselosteen hyväksymiseen -->
        <div class="checkbox-wrapper">
            <label for="acceptPrivacyPolicy" class="checkbox-label">
                <input type="checkbox" id="acceptPrivacyPolicy" name="terms" required>
                Hyväksyn 
                <a href="assets/doc/Kayttoehdot.pdf" target="_blank">käyttöehdot</a> ja 
                <a href="assets/doc/Tietosuojaseloste.pdf" target="_blank">tietosuojaselosteen</a>.
            </label>
        </div>




            <button type="submit">Rekisteröidy 🧟‍♂️</button>
        </form>
    </div>


    <?php else: ?>

        <!-- =========================== -->
        <!--        TODO-APPLICATION     -->
        <!-- =========================== -->

        <a href="actions.php?action=logout" class="logout-link">Kirjaudu ulos ❌</a>

        <h1>ZOMBIE TO-DO</h1>

        <div class="todo-box">

            <!-- LISÄÄ TEHTÄVÄ -->
            <form class="input-area" action="actions.php?action=add" method="POST">
                <input type="text" name="task" placeholder="Lisää tehtävä... ennen kuin kuolleet nousevat!" required>
                <button type="submit">Lisää</button>
            </form>

            <!-- EI ALOITETUT -->
            <h2 class="section-title not-started">🧠 Ei aloitetut</h2>
            <div class="task-list">
                <?php while ($task = $notStarted->fetch_assoc()): ?>
                    <div class="task">
                        <span><?= htmlspecialchars($task['text']) ?></span>
                        <div class="actions">
                            <a href="#" data-action="start" data-id="<?= $task['id'] ?>">⚔️</a>
                            <a href="#" data-action="delete" data-id="<?= $task['id'] ?>">🗑</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- KÄYNNISSÄ -->
            <h2 class="section-title in-progress">🪓 Käynnissä</h2>
            <div class="task-list">
                <?php while ($task = $inProgress->fetch_assoc()): ?>
                    <div class="task">
                        <span><?= htmlspecialchars($task['text']) ?></span>
                        <div class="actions">
                            <a href="#" data-action="done" data-id="<?= $task['id'] ?>">✓</a>
                            <a href="#" data-action="delete" data-id="<?= $task['id'] ?>">🗑</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- VALMIIT -->
            <h2 class="section-title done-title">🪦 Valmiit</h2>
            <div class="task-list">
                <?php while ($task = $doneTasks->fetch_assoc()): ?>
                    <div class="task done">
                        <span><?= htmlspecialchars($task['text']) ?></span>
                        <div class="actions">
                            <a href="#" data-action="delete" data-id="<?= $task['id'] ?>">🗑</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

        </div> <!-- todo-box -->

    <?php endif; ?>

</div> <!-- container -->

<?php if (isset($_SESSION['user_id'])): ?>
<script>
// Päivitä tehtävät ilman reloadia
async function refreshTasks() {
    const html = await fetch("partial-tasks.php").then(res => res.text());
    const box = document.querySelector(".todo-box");
    const form = box.querySelector("form").outerHTML;

    box.innerHTML = form + html;
    attachTaskEvents();
    setupEnterKey();
    setupFormSubmit();

    // Aseta kursori input-kenttään
    focusInput();
}

function attachTaskEvents() {
    document.querySelectorAll(".actions a").forEach(a => {
        a.addEventListener("click", async (e) => {
            e.preventDefault();
            const action = a.dataset.action;
            const id     = a.dataset.id;
            await fetch(`actions.php?action=${action}&id=${id}`);
            refreshTasks();
        });
    });
}

function setupEnterKey() {
    const input = document.querySelector(".input-area input");
    if (!input) return;

    input.addEventListener("keydown", function (e) {
        if (e.key === "Enter") {
            e.preventDefault();
            document.querySelector(".input-area").requestSubmit();
        }
    });
}

function setupFormSubmit() {
    const form = document.querySelector(".input-area");
    if (!form) return;

    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        await fetch("actions.php?action=add", { method: "POST", body: formData });
        e.target.reset();
        refreshTasks();
    });
}

// Uusi apufunktio: fokusoi input-kenttä
function focusInput() {
    const input = document.querySelector(".input-area input");
    if (input) input.focus();
}

// Alustetaan kun sivu latautuu
attachTaskEvents();
setupEnterKey();
setupFormSubmit();
focusInput(); // kursori heti input-kenttään

</script>

<?php endif; ?>

</body>
</html>
