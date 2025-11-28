<?php
// Ladataan MongoDB-yhteys
require 'mongo.php';

// Haetaan tehtävät statuksilla
$notStarted = $collection->find(['status' => 'not_started']);
$inProgress = $collection->find(['status' => 'in_progress']);
$doneTasks  = $collection->find(['status' => 'done']);
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
            <?php foreach ($notStarted as $task): ?>
                <div class="task">
                    <span><?= htmlspecialchars($task['text']) ?></span>
                    <div class="actions">
                        <a href="actions.php?action=start&id=<?= $task['_id'] ?>">⚔️</a>
                        <a href="actions.php?action=delete&id=<?= $task['_id'] ?>">🗑</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- KÄYNNISSÄ -->
        <h2 class="section-title in-progress">🪓 Käynnissä</h2>
        <div class="task-list">
            <?php foreach ($inProgress as $task): ?>
                <div class="task">
                    <span><?= htmlspecialchars($task['text']) ?></span>
                    <div class="actions">
                        <a href="actions.php?action=done&id=<?= $task['_id'] ?>">✓</a>
                        <a href="actions.php?action=delete&id=<?= $task['_id'] ?>">🗑</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- VALMIIT -->
        <h2 class="section-title done-title">🪦 Valmiit</h2>
        <div class="task-list">
            <?php foreach ($doneTasks as $task): ?>
                <div class="task done">
                    <span><?= htmlspecialchars($task['text']) ?></span>
                    <div class="actions">
                        <a href="actions.php?action=delete&id=<?= $task['_id'] ?>">🗑</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div> <!-- todo-box -->

</div> <!-- container -->

<script>
// Lataa tehtävät sivulle ilman reloadia
async function refreshTasks() {
    const html = await fetch("partial-tasks.php").then(res => res.text());
    document.querySelector(".todo-box").innerHTML = 
        document.querySelector(".todo-box").querySelector("form").outerHTML + html;

    attachTaskEvents(); // lisää klikkikuuntelijat uudelleen
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

// LISÄÄ TEHTÄVÄ AJAXINA
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
