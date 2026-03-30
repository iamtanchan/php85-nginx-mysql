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
$time = isset($_POST['time']) ? trim((string)$_POST['time']) : '';
$ship_id = isset($_POST['ship_id']) ? (int)$_POST['ship_id'] : 0;
$destination_id = isset($_POST['destination_id']) ? (int)$_POST['destination_id'] : 0;
$badge_id = isset($_POST['badge_id']) ? (int)$_POST['badge_id'] : 0;
$boarding_minutes = isset($_POST['boarding_minutes']) ? (int)$_POST['boarding_minutes'] : 10;
$blink_minutes = isset($_POST['blink_minutes']) ? (int)$_POST['blink_minutes'] : 5;

if ($station_id <= 0 || $season_id <= 0 || $row_id <= 0) {
    respond(false, 'station_id, season_id and row_id are required');
}
if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
    respond(false, 'invalid time format');
}
if ($ship_id <= 0) {
    respond(false, 'ship_id is required');
}
if ($destination_id <= 0) {
    respond(false, 'destination_id is required');
}
if (!in_array($boarding_minutes, array(15, 10, 5), true)) {
    respond(false, 'boarding_minutes must be one of 15, 10, 5');
}
if ($blink_minutes < 0 || $blink_minutes > 10) {
    respond(false, 'blink_minutes must be between 0 and 10');
}

try {
    $sql = 'UPDATE schedule
            SET departure_time = :time,
                ship_id = :ship_id,
                destination_id = :destination_id
            WHERE schedule_id = :row_id AND station_id = :station_id AND season_id = :season_id';
    $stt = $db->prepare($sql);
    $stt->bindValue(':time', $time . ':00');
    $stt->bindValue(':ship_id', $ship_id, PDO::PARAM_INT);
    $stt->bindValue(':destination_id', $destination_id, PDO::PARAM_INT);
    $stt->bindValue(':row_id', $row_id, PDO::PARAM_INT);
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':season_id', $season_id, PDO::PARAM_INT);
    $stt->execute();
    sync_timetable_from_season($db, $station_id, $season_id, array(
        $row_id => array(
            'badge_id' => $badge_id,
            'ontime' => $boarding_minutes,
            'offtime' => $blink_minutes,
        ),
    ));

    respond(true, 'updated');
} catch (PDOException $e) {
    respond(false, 'DB error: ' . $e->getMessage());
}
