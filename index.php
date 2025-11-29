<?php
session_start();
require 'db.php';

// Turvallinen tulostus
function clean($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        <div class="auth-error"><?= clean($_SESSION['error']); unset($_SESSION['error']); ?></div>
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
            <input type="email"
                   name="email"
                   placeholder="example@domain.com"
                   required
                   value="<?= clean($_SESSION['old_login_email'] ?? '') ?>"
                   autocomplete="off">

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
            <input type="text"
                   name="username"
                   placeholder="ZombieMaster91"
                   required
                   value="<?= clean($_SESSION['old_username'] ?? '') ?>"
                   autocomplete="off">

            <label>Sähköposti</label>
            <input type="email"
                   name="email"
                   placeholder="example@domain.com"
                   required
                   value="<?= clean($_SESSION['old_email'] ?? '') ?>"
                   autocomplete="off">

            <label>Salasana</label>
            <input type="password" name="password" placeholder="********" required autocomplete="off">

            <!-- Hyväksyntäruksi -->
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

    <?php
        unset($_SESSION['old_username'], $_SESSION['old_email'], $_SESSION['old_login_email']);
    ?>

    <?php else: ?>

        <!-- =========================== -->
        <!--        HEADER + LOGOUT      -->
        <!-- =========================== -->

        <div class="header-bar">
            <span class="welcome-text">Tervetuloa, <?= clean($_SESSION['username'] ?? '') ?>!</span>
            <a href="actions.php?action=logout" class="logout-link">Kirjaudu ulos ❌</a>
        </div>
        <h1>ZOMBIE TO-DO</h1>
<!-- =========================== -->
<!--        TODO-APPLICATION     -->
<!-- =========================== -->

<div class="todo-box">

    <form class="input-area" action="actions.php?action=add" method="POST">
        <input type="text" name="task" placeholder="Lisää tehtävä... ennen kuin kuolleet nousevat!" required>
        <button type="submit">Lisää</button>
    </form>

    <!-- EI ALOITETUT -->
    <h2 class="section-title not-started">🧠 Ei aloitetut</h2>
    <div class="task-list">
        <?php while ($task = $notStarted->fetch_assoc()): ?>
            <div class="task">
                <span><?= clean($task['text']) ?></span>

                <small class="timestamp">
                    Lisätty: <?= date("d.m.Y H:i", strtotime($task['created_at'])) ?>
                </small>

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
                <span><?= clean($task['text']) ?></span>

                <small class="timestamp">
                    Lisätty: <?= date("d.m.Y H:i", strtotime($task['created_at'])) ?>
                    <br>Aloitettu: <?= date("d.m.Y H:i", strtotime($task['started_at'])) ?>
                </small>

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

                <!-- TEHTÄVÄN TEKSTI, JOLLE TULEE YLIVIVAUS -->
                <span class="task-text"><?= clean($task['text']) ?></span>

                <!-- AIKALEIMAT (EI YLIVIVAUSTA) -->
                <small class="timestamp">
                    Lisätty: <?= date("d.m.Y H:i", strtotime($task['created_at'])) ?>
                    <?php if (!empty($task['started_at'])): ?>
                        <br>Aloitettu: <?= date("d.m.Y H:i", strtotime($task['started_at'])) ?>
                    <?php endif; ?>
                    <?php if (!empty($task['done_at'])): ?>
                        <br>Valmis: <?= date("d.m.Y H:i", strtotime($task['done_at'])) ?>
                    <?php endif; ?>
                </small>

                <div class="actions">
                    <a href="#" data-action="delete" data-id="<?= $task['id'] ?>">🗑</a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

</div>


    <?php endif; ?>

</div>

<?php if (isset($_SESSION['user_id'])): ?>
<script>
async function refreshTasks() {
    const html = await fetch("partial-tasks.php").then(res => res.text());
    const box = document.querySelector(".todo-box");
    const form = box.querySelector("form").outerHTML;

    box.innerHTML = form + html;
    attachTaskEvents();
    setupEnterKey();
    setupFormSubmit();
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

function focusInput() {
    const input = document.querySelector(".input-area input");
    if (input) input.focus();
}

attachTaskEvents();
setupEnterKey();
setupFormSubmit();
focusInput();
</script>
<?php endif; ?>

</body>
</html>
