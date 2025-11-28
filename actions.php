<?php
// Ladataan MongoDB-yhteys
require 'mongo.php';

// Luetaan toiminto ja id
$action = $_GET['action'] ?? ($_POST['action'] ?? null);
$id     = $_GET['id']     ?? null;

// Varmistetaan ID-muoto tarvittaessa
function mongoId($id) {
    return new MongoDB\BSON\ObjectId($id);
}

// ----------------------------------------
// 1) LISÄÄ TEHTÄVÄ
// ----------------------------------------
if ($action === 'add') {

    if (!empty($_POST['task'])) {

        $collection->insertOne([
            'text'       => trim($_POST['task']),
            'status'     => 'not_started',   // OIKEA status
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ]);
    }

    header("Location: index.php");
    exit;
}

// ----------------------------------------
// 2) MERKITSE ALOITETUKSI (not_started → in_progress)
// ----------------------------------------
if ($action === 'start' && $id) {

    $collection->updateOne(
        ['_id' => mongoId($id)],
        ['$set' => ['status' => 'in_progress']]
    );

    header("Location: index.php");
    exit;
}

// ----------------------------------------
// 3) MERKITSE VALMIIKSI (in_progress → done)
// ----------------------------------------
if ($action === 'done' && $id) {

    $collection->updateOne(
        ['_id' => mongoId($id)],
        ['$set' => ['status' => 'done']]
    );

    header("Location: index.php");
    exit;
}

// ----------------------------------------
// 4) POISTA TEHTÄVÄ
// ----------------------------------------
if ($action === 'delete' && $id) {

    $collection->deleteOne([
        '_id' => mongoId($id)
    ]);

    header("Location: index.php");
    exit;
}

// Jos ei täsmää → paluu etusivulle
header("Location: index.php");
exit;

?>
