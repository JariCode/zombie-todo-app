<?php
// ================================
// index.php
//
// Korjaukset:
// - AJAX-kutsuissa CSRF lähetetään X-CSRF-Token-headerina (ei enää URL:ssa)
// ================================

require __DIR__ . '/app/session-config.php';
session_start();
require __DIR__ . '/app/db.php';

validateSessionTimeout();
generateCSRFToken();

function clean($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

if (isset($_SESSION['user_id'])) {
    $uid = intval($_SESSION['user_id']);

    $notStarted = $conn->prepare("SELECT * FROM tasks WHERE user_id=? AND status='not_started' ORDER BY id DESC");
    $notStarted->bind_param("i", $uid);
    $notStarted->execute();
    $notStarted = $notStarted->get_result();

    $inProgress = $conn->prepare("SELECT * FROM tasks WHERE user_id=? AND status='in_progress' ORDER BY id DESC");
    $inProgress->bind_param("i", $uid);
    $inProgress->execute();
    $inProgress = $inProgress->get_result();

    $doneTasks = $conn->prepare("SELECT * FROM tasks WHERE user_id=? AND status='done' ORDER BY id DESC");
    $doneTasks->bind_param("i", $uid);
    $doneTasks->execute();
    $doneTasks = $doneTasks->get_result();
}
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zombie To-Do</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="blood"></div>

<div class="container">

    <img src="assets/img/Herokuva.webp" class="hero">

    <div class="wip-banner">🧠 WORK IN PROGRESS… BRAINS LOADING 🩸</div>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="auth-error"><?= clean($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="auth-success" id="deleted-msg">Tilisi on poistettu pysyvästi. Vaella rauhassa, zombi. 🪦🩸</div>
        <script>
            setTimeout(() => {
                const msg = document.getElementById('deleted-msg');
                if (msg) {
                    msg.style.transition = 'opacity 1s';
                    msg.style.opacity = '0';
                    setTimeout(() => { if (msg) msg.remove(); }, 1000);
                }
            }, 4000);
        </script>
    <?php endif; ?>

    <?php if (!isset($_SESSION['user_id'])): ?>

    <h1>ZOMBIE LOGIN</h1>

    <div class="auth-box">
        <h2 class="auth-title">Kirjaudu sisään</h2>

        <form method="POST" action="app/actions.php?action=login" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= clean(generateCSRFToken()) ?>">

            <label>Sähköposti</label>
            <input type="email"
                   name="email"
                   placeholder="example@domain.com"
                   required
                   value="<?= clean($_SESSION['old_login_email'] ?? '') ?>"
                   autocomplete="off">

            <label>Salasana</label>
            <div class="password-field">
                <input type="password" name="password" placeholder="********" required autocomplete="off">
                <button type="button" class="password-eye" aria-label="Näytä salasana">👁️</button>
            </div>

            <button type="submit">Kirjaudu sisään 🔑</button>
        </form>
    </div>

    <div class="auth-separator">TAI LUO TILI</div>

    <div class="auth-box">
        <h2 class="auth-title">Rekisteröidy</h2>

        <form method="POST" action="app/actions.php?action=register" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= clean(generateCSRFToken()) ?>">

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
            <div class="password-field">
                <input type="password" name="password" placeholder="********" required autocomplete="off">
                <button type="button" class="password-eye" aria-label="Näytä salasana">👁️</button>
            </div>

            <label>Toista salasana</label>
            <div class="password-field">
                <input type="password" name="password_confirm" placeholder="********" required autocomplete="off">
                <button type="button" class="password-eye" aria-label="Näytä salasana">👁️</button>
            </div>

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

        <div class="header-bar">
            <span class="welcome-text">Tervetuloa, <?= clean($_SESSION['username'] ?? '') ?>!</span>
            <div class="header-links">
                <a href="profile.php" class="header-link">Muokkaa&nbsp;tietoja&nbsp;🧟‍♀️</a>
                <a href="app/actions.php?action=logout" class="header-link">Kirjaudu&nbsp;ulos&nbsp;❌</a>
            </div>
        </div>

        <h1>ZOMBIE TO-DO</h1>

<div class="todo-box">

    <form class="input-area" action="app/actions.php?action=add" method="POST">
        <input type="hidden" name="csrf_token" value="<?= clean(generateCSRFToken()) ?>">
        <input type="text" name="task" placeholder="Lisää tehtävä... ennen kuin kuolleet nousevat!" required autocomplete="off">
        <button type="submit">Lisää</button>
    </form>

    <h2 class="section-title not-started">🧠 Ei aloitetut</h2>
    <div class="task-list">
    <?php while ($task = $notStarted->fetch_assoc()): ?>
        <div class="task">
            <div class="task-info">
                <span><?= clean($task['text']) ?></span>
                <small class="timestamp">Lisätty: <?= date("d.m.Y H:i", strtotime($task['created_at'])) ?></small>
            </div>
            <div class="actions">
                <button type="button" data-action="start" data-id="<?= $task['id'] ?>">⚔️</button>
                <button type="button" data-action="delete" data-id="<?= $task['id'] ?>">🗑</button>
            </div>
        </div>
    <?php endwhile; ?>
    </div>

    <h2 class="section-title in-progress">🪓 Käynnissä</h2>
    <div class="task-list">
    <?php while ($task = $inProgress->fetch_assoc()): ?>
        <div class="task">
            <div class="task-info">
                <span><?= clean($task['text']) ?></span>
                <small class="timestamp">
                    Lisätty: <?= date("d.m.Y H:i", strtotime($task['created_at'])) ?>
                    <br>Aloitettu: <?= date("d.m.Y H:i", strtotime($task['started_at'])) ?>
                </small>
            </div>
            <div class="actions">
                <button type="button" data-action="done" data-id="<?= $task['id'] ?>">✓</button>
                <button type="button" data-action="undo_start" data-id="<?= $task['id'] ?>">☠️</button>
                <button type="button" data-action="delete" data-id="<?= $task['id'] ?>">🗑</button>
            </div>
        </div>
    <?php endwhile; ?>
    </div>

    <h2 class="section-title done-title">🪦 Valmiit</h2>
    <div class="task-list">
    <?php while ($task = $doneTasks->fetch_assoc()): ?>
        <div class="task done">
            <div class="task-info">
                <span class="task-text"><?= clean($task['text']) ?></span>
                <small class="timestamp">
                    Lisätty: <?= date("d.m.Y H:i", strtotime($task['created_at'])) ?>
                    <?php if (!empty($task['started_at'])): ?>
                        <br>Aloitettu: <?= date("d.m.Y H:i", strtotime($task['started_at'])) ?>
                    <?php endif; ?>
                    <?php if (!empty($task['done_at'])): ?>
                        <br>Valmis: <?= date("d.m.Y H:i", strtotime($task['done_at'])) ?>
                    <?php endif; ?>
                </small>
            </div>
            <div class="actions">
                <button type="button" data-action="undo_done" data-id="<?= $task['id'] ?>">☠️</button>
                <button type="button" data-action="delete" data-id="<?= $task['id'] ?>">🗑</button>
            </div>
        </div>
    <?php endwhile; ?>
    </div>

</div>

<?php endif; ?>

</div>

<script>
document.querySelectorAll('.password-field .password-eye').forEach((btn) => {
    btn.addEventListener('click', () => {
        const input = btn.parentElement?.querySelector('input');
        if (!input) return;
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        btn.setAttribute('aria-label', isHidden ? 'Piilota salasana' : 'Näytä salasana');
    });
});
</script>

<?php if (isset($_SESSION['user_id'])): ?>
<script>
// Korjaus: CSRF lähetetään X-CSRF-Token-headerina, ei URL-parametrina
function getCSRF() {
    return document.querySelector('input[name="csrf_token"]')?.value || '';
}

async function refreshTasks() {
    const prevScroll = window.scrollY;
    const box = document.querySelector(".todo-box");

    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
    if (isSafari) {
        box.style.height = box.offsetHeight + "px";
        box.style.overflow = "hidden";
    }

    // Korjaus: CSRF headerissa
    const html = await fetch("app/partial-tasks.php", {
        headers: { 'X-CSRF-Token': getCSRF() }
    }).then(res => res.text());

    const form = box.querySelector("form").outerHTML;
    box.innerHTML = form + html;

    attachTaskEvents();
    setupEnterKey();
    setupFormSubmit();

    setTimeout(() => {
        const input = document.querySelector('.input-area input[name="task"]');
        if (input) {
            try { input.focus({ preventScroll: true }); } catch (e) { input.focus(); }
        }
    }, 0);

    if (isSafari) {
        box.style.height = '';
        box.style.overflow = '';
    }

    requestAnimationFrame(() => requestAnimationFrame(() => window.scrollTo(0, prevScroll)));
}

function attachTaskEvents() {
    document.querySelectorAll('.actions button').forEach(el => {
        el.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            const action = el.dataset.action;
            const id     = el.dataset.id;

            // Korjaus: CSRF headerissa POST-bodyn sijaan, ID URL:ssa (ei arkaluonteinen)
            await fetch(`app/actions.php?action=${action}&id=${id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': getCSRF()
                },
                body: ''
            });
            refreshTasks();
        });
    });
}

function setupEnterKey() {
    const input = document.querySelector(".input-area input");
    if (!input) return;
    input.addEventListener("keydown", (e) => {
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
        await fetch("app/actions.php?action=add", {
            method: "POST",
            body: formData
            // Huom: FormData sisältää csrf_token-kentän lomakkeesta
        });
        e.target.reset();
        refreshTasks();
    });
}

function focusInput() {
    const input = document.querySelector(".input-area input");
    if (!input) return;
    try { input.focus({ preventScroll: true }); } catch (e) { input.focus(); }
}

attachTaskEvents();
setupEnterKey();
setupFormSubmit();
focusInput();
</script>
<?php endif; ?>

</body>
</html>
