<?php
require 'mongo.php';

$notStarted = $collection->find(['status' => 'not_started']);
$inProgress = $collection->find(['status' => 'in_progress']);
$doneTasks  = $collection->find(['status' => 'done']);
?>

<!-- EI ALOITETUT -->
<h2 class="section-title not-started">🧠 Ei aloitetut</h2>
<div class="task-list">
<?php foreach ($notStarted as $task): ?>
    <div class="task">
        <span><?= htmlspecialchars($task['text']) ?></span>
        <div class="actions login">
            <a data-action="start" data-id="<?= $task['_id'] ?>">⚔️</a>
            <a data-action="delete" data-id="<?= $task['_id'] ?>">🗑</a>
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
            <a data-action="done" data-id="<?= $task['_id'] ?>">✓</a>
            <a data-action="delete" data-id="<?= $task['_id'] ?>">🗑</a>
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
            <a data-action="delete" data-id="<?= $task['_id'] ?>">🗑</a>
        </div>
    </div>
<?php endforeach; ?>
</div>
