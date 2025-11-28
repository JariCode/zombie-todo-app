<?php
require 'db.php';

$action = $_GET['action'] ?? null;
$id = intval($_GET['id'] ?? 0);

// LISÄÄ TEHTÄVÄ
if ($action === "add" && !empty($_POST['task'])) {
    $stmt = $conn->prepare("INSERT INTO tasks (text, status) VALUES (?, 'not_started')");
    $stmt->bind_param("s", $_POST['task']);
    $stmt->execute();
    $stmt->close();

    header("Location: index.php");
    exit;
}

// ALOITUS (not_started → in_progress)
if ($action === "start" && $id > 0) {
    $stmt = $conn->prepare("UPDATE tasks SET status='in_progress' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: index.php");
    exit;
}

// VALMIIKSI MERKITSE (in_progress → done)
if ($action === "done" && $id > 0) {
    $stmt = $conn->prepare("UPDATE tasks SET status='done' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: index.php");
    exit;
}

// POISTA TASK
if ($action === "delete" && $id > 0) {
    $stmt = $conn->prepare("DELETE FROM tasks WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: index.php");
    exit;
}

header("Location: index.php");
exit;
