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
// Haetaan tehtävät aikaleimoilla
// -------------------------
$notStarted = $conn->prepare("SELECT id, text, created_at FROM tasks WHERE user_id=? AND status='not_started' ORDER BY id DESC");
$notStarted->bind_param("i", $uid);
$notStarted->execute();
$notStartedRes = $notStarted->get_result();

$inProgress = $conn->prepare("SELECT id, text, created_at, started_at FROM tasks WHERE user_id=? AND status='in_progress' ORDER BY id DESC");
$inProgress->bind_param("i", $uid);
$inProgress->execute();
$inProgressRes = $inProgress->get_result();

$doneTasks = $conn->prepare("SELECT id, text, created_at, started_at, done_at FROM tasks WHERE user_id=? AND status='done' ORDER BY id DESC");
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

        <div class="task-info">
            <span class="task-text"><?= htmlspecialchars($task['text']) ?></span>

            <small class="timestamp">
                Lisätty: <?= date("d.m.Y H:i", strtotime($task['created_at'])) ?>
            </small>
        </div>

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

        <div class="task-info">
            <span class="task-text"><?= htmlspecialchars($task['text']) ?></span>

            <small class="timestamp">
                Lisätty: <?= date("d.m.Y H:i", strtotime($task['created_at'])) ?>
                <br>Aloitettu: <?= date("d.m.Y H:i", strtotime($task['started_at'])) ?>
            </small>
        </div>

        <div class="actions">
            <a href="#" data-action="done" data-id="<?= $task['id'] ?>">✓</a>
            <a href="#" data-action="undo_not_started" data-id="<?= $task['id'] ?>">☠️</a>
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

        <div class="task-info">
            <span class="task-text"><?= htmlspecialchars($task['text']) ?></span>

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
            <a href="#" data-action="undo_in_progress" data-id="<?= $task['id'] ?>">☠️</a>
            <a href="#" data-action="delete" data-id="<?= $task['id'] ?>">🗑</a>
        </div>

    </div>
<?php endwhile; ?>
</div>
