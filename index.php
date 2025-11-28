<?php
// MySQL-yhteys
require 'db.php';

// Haetaan tehtävät statuksilla
$notStarted = $conn->query("SELECT * FROM tasks WHERE status='not_started' ORDER BY id DESC");
$inProgress = $conn->query("SELECT * FROM tasks WHERE status='in_progress' ORDER BY id DESC");
$doneTasks  = $conn->query("SELECT * FROM tasks WHERE status='done' ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <title>Zombi To-Do (Prototype)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Veri-animaatio headerissa -->
<div class="blood"></div>

<div class="container">

    <img src="assets/img/header-zombie.png.png" class="hero">

    <h1>ZOMBIE TO-DO</h1>

    <div class="todo-box">

        <!-- LISÄYSTOIMINTO -->
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

</div> <!-- container -->

<script>
// Päivitä tehtävät ilman reloadia
async function refreshTasks() {
    const html = await fetch("partial-tasks.php").then(res => res.text());
    const box = document.querySelector(".todo-box");

    // Säilytä lisäyslomake
    const form = box.querySelector("form").outerHTML;

    box.innerHTML = form + html;

    attachTaskEvents();
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

// LISÄÄ TEHTÄVÄ AJAXILLA
document.querySelector(".input-area").addEventListener("submit", async (e) => {
    e.preventDefault();

    const formData = new FormData(e.target);

    await fetch("actions.php?action=add", {
        method: "POST",
        body: formData
    });

    e.target.reset();
    refreshTasks();
});

// Ensimmäinen lataus
attachTaskEvents();
</script>

</body>
</html>
