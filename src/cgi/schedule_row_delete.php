<?php
require_once __DIR__ . '/../lib/admin_bootstrap.php';
require_once __DIR__ . '/../lib/timetable_sync.php';
header('Content-type: application/json; charset=utf-8');

function respond($ok, $message, $extra = array())
{
    echo json_encode(array_merge(array('ok' => $ok, 'message' => $message), $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

$station_id = isset($_POST['station_id']) ? (int)$_POST['station_id'] : 0;
$season_id = isset($_POST['season_id']) ? (int)$_POST['season_id'] : 0;
$row_id = isset($_POST['row_id']) ? (int)$_POST['row_id'] : 0;

if ($station_id <= 0 || $season_id <= 0 || $row_id <= 0) {
    respond(false, 'station_id, season_id and row_id are required');
}

try {
    $sql = 'DELETE FROM schedule WHERE schedule_id = :row_id AND station_id = :station_id AND season_id = :season_id';
    $stt = $db->prepare($sql);
    $stt->bindValue(':row_id', $row_id, PDO::PARAM_INT);
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':season_id', $season_id, PDO::PARAM_INT);
    $stt->execute();
    sync_timetable_from_season($db, $station_id, $season_id);

    respond(true, 'deleted');
} catch (PDOException $e) {
    respond(false, 'DB error: ' . $e->getMessage());
}
