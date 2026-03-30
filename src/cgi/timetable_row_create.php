<?php
require_once __DIR__ . '/../lib/admin_bootstrap.php';
header('Content-type: application/json; charset=utf-8');

function respond($ok, $message, $extra = array())
{
    echo json_encode(array_merge(array('ok' => $ok, 'message' => $message), $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

$station_id = isset($_POST['station_id']) ? (int)$_POST['station_id'] : 0;
$time = isset($_POST['time']) ? trim((string)$_POST['time']) : '';
$ship_id = isset($_POST['ship_id']) ? (int)$_POST['ship_id'] : 0;
$destination_id = isset($_POST['destination_id']) ? (int)$_POST['destination_id'] : 0;
$badge_id = isset($_POST['badge_id']) ? (int)$_POST['badge_id'] : 0;
$boarding_minutes = isset($_POST['boarding_minutes']) ? (int)$_POST['boarding_minutes'] : 10;
$blink_minutes = isset($_POST['blink_minutes']) ? (int)$_POST['blink_minutes'] : 5;

if ($station_id <= 0) {
    respond(false, 'station_id is required');
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
    $sql = 'INSERT INTO timetable (
                station_id,
                departure_time,
                ship_id,
                destination_id,
                badge_id,
                ontime,
                offtime
            ) VALUES (
                :station_id,
                :time,
                :ship_id,
                :destination_id,
                :badge_id,
                :ontime,
                :offtime
            )';
    $stt = $db->prepare($sql);
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':time', $time . ':00');
    $stt->bindValue(':ship_id', $ship_id, PDO::PARAM_INT);
    $stt->bindValue(':destination_id', $destination_id, PDO::PARAM_INT);
    $stt->bindValue(':badge_id', $badge_id, PDO::PARAM_INT);
    $stt->bindValue(':ontime', $boarding_minutes, PDO::PARAM_INT);
    $stt->bindValue(':offtime', $blink_minutes, PDO::PARAM_INT);
    $stt->execute();

    respond(true, 'created');
} catch (PDOException $e) {
    respond(false, 'DB error: ' . $e->getMessage());
}
