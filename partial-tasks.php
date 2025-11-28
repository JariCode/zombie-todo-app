<?php
require 'db.php';

// Haetaan tehtävät MySQL-tietokannasta
$notStarted = $conn->query("SELECT * FROM tasks WHERE status='not_started' ORDER BY id DESC");
$inProgress = $conn->query("SELECT * FROM tasks WHERE status='in_progress' ORDER BY id DESC");
$doneTasks  = $conn->query("SELECT * FROM tasks WHERE status='done' ORDER BY id DESC");
?>

<!-- EI ALOITETUT -->
<h2 class="section-title not-started">🧠 Ei aloitetut</h2>
<div class="task-list">
<?php while ($task = $notStarted->fetch_assoc()): ?>
    <div class="task">
        <span><?= htmlspecialchars($task['text']) ?></span>
        <div class="actions">
            <a data-action="start" data-id="<?= $task['id'] ?>">⚔️</a>
            <a data-action="delete" data-id="<?= $task['id'] ?>">🗑</a>
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
            <a data-action="done" data-id="<?= $task['id'] ?>">✓</a>
            <a data-action="delete" data-id="<?= $task['id'] ?>">🗑</a>
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
            <a data-action="delete" data-id="<?= $task['id'] ?>">🗑</a>
        </div>
    </div>
<?php endwhile; ?>
</div>
