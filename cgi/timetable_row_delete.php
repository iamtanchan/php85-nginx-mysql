<?php
require_once __DIR__ . '/../lib/admin_bootstrap.php';
header('Content-type: application/json; charset=utf-8');

function respond($ok, $message, $extra = array())
{
    echo json_encode(array_merge(array('ok' => $ok, 'message' => $message), $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_POST['station_id']) || !isset($_POST['row_id'])) {
    respond(false, 'station_id and row_id are required');
}

$station_id = (int)$_POST['station_id'];
$row_id = (int)$_POST['row_id'];
if ($station_id <= 0 || $row_id <= 0) {
    respond(false, 'invalid station_id or row_id');
}

try {
    $day_bounds = current_day_bounds();
    $sql = 'DELETE FROM timetable
            WHERE timetable_id = :row_id
              AND station_id = :station_id
              AND created_at >= :day_start
              AND created_at < :day_end';
    $stt = $db->prepare($sql);
    $stt->bindValue(':row_id', $row_id, PDO::PARAM_INT);
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':day_start', $day_bounds['start']);
    $stt->bindValue(':day_end', $day_bounds['end']);
    $stt->execute();
    respond(true, 'deleted');
} catch (PDOException $e) {
    respond(false, 'DB error: ' . $e->getMessage());
}
