<?php
session_start();
require 'db.php';

// -------------------------
// Varmista kirjautuminen
// -------------------------
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color:red;'>Et ole kirjautunut.</p>";
    exit;
}

$uid = intval($_SESSION['user_id']);

// -------------------------
// Haetaan käyttäjäkohtaiset taskit
// -------------------------
$notStarted = $conn->prepare("SELECT id, text FROM tasks WHERE user_id=? AND status='not_started' ORDER BY id DESC");
$notStarted->bind_param("i", $uid);
$notStarted->execute();
$notStartedRes = $notStarted->get_result();

$inProgress = $conn->prepare("SELECT id, text FROM tasks WHERE user_id=? AND status='in_progress' ORDER BY id DESC");
$inProgress->bind_param("i", $uid);
$inProgress->execute();
$inProgressRes = $inProgress->get_result();

$doneTasks = $conn->prepare("SELECT id, text FROM tasks WHERE user_id=? AND status='done' ORDER BY id DESC");
$doneTasks->bind_param("i", $uid);
$doneTasks->execute();
$doneTasksRes = $doneTasks->get_result();
?>

<!-- ============================= -->
<!--      EI ALOITETUT            -->
<!-- ============================= -->
<h2 class="section-title not-started">🧠 Ei aloitetut</h2>
<div class="task-list">
<?php while ($task = $notStartedRes->fetch_assoc()): ?>
    <div class="task">
        <span><?= htmlspecialchars($task['text']) ?></span>
        <div class="actions">
            <a href="#" data-action="start" data-id="<?= $task['id'] ?>">⚔️</a>
            <a href="#" data-action="delete" data-id="<?= $task['id'] ?>">🗑</a>
        </div>
    </div>
<?php endwhile; ?>
</div>

<!-- ============================= -->
<!--         KÄYNNISSÄ             -->
<!-- ============================= -->
<h2 class="section-title in-progress">🪓 Käynnissä</h2>
<div class="task-list">
<?php while ($task = $inProgressRes->fetch_assoc()): ?>
    <div class="task">
        <span><?= htmlspecialchars($task['text']) ?></span>
        <div class="actions">
            <a href="#" data-action="done" data-id="<?= $task['id'] ?>">✓</a>
            <a href="#" data-action="delete" data-id="<?= $task['id'] ?>">🗑</a>
        </div>
    </div>
<?php endwhile; ?>
</div>

<!-- ============================= -->
<!--           VALMIIT            -->
<!-- ============================= -->
<h2 class="section-title done-title">🪦 Valmiit</h2>
<div class="task-list">
<?php while ($task = $doneTasksRes->fetch_assoc()): ?>
    <div class="task done">
        <span><?= htmlspecialchars($task['text']) ?></span>
        <div class="actions">
            <a href="#" data-action="delete" data-id="<?= $task['id'] ?>">🗑</a>
        </div>
    </div>
<?php endwhile; ?>
</div>
