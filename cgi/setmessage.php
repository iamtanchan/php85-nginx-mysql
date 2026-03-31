<?php
require_once __DIR__ . '/../lib/admin_bootstrap.php';
header('Content-type: application/json; charset=utf-8');

function normalize_setmessage_text(string $message): string
{
    $message = str_replace(array("\r\n", "\r"), "\n", trim($message));
    if (function_exists('mb_substr')) {
        return mb_substr($message, 0, 200, 'UTF-8');
    }
    return substr($message, 0, 200);
}

$station_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
$message = normalize_setmessage_text((string)($_POST['text'] ?? ''));
$has_drag_speed = isset($_POST['drag_speed']);
$drag_speed = $has_drag_speed
    ? normalize_message_drag_speed((int)($_POST['drag_speed'] ?? MESSAGE_DRAG_SPEED_DEFAULT))
    : (int)fetch_station_message_settings($db, $station_id)['drag_speed'];

if ($station_id <= 0 || ($message === '' && !$has_drag_speed)) {
    echo json_encode(array('ok' => false, 'message' => 'id and text or drag_speed are required'), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($message !== '' && $message_id > 0) {
        $stt = $db->prepare(
            'UPDATE message
             SET message = :message
             WHERE station_id = :station_id AND message_id = :message_id'
        );
        $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
        $stt->bindValue(':message_id', $message_id, PDO::PARAM_INT);
        $stt->bindValue(':message', $message);
        $stt->execute();
    } elseif ($message !== '') {
        $next_sort_order = 1;
        $sort_stt = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM message WHERE station_id = :station_id');
        $sort_stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
        $sort_stt->execute();
        $next_sort_order = (int)$sort_stt->fetchColumn();

        $stt = $db->prepare(
            'INSERT INTO message (station_id, message, sort_order, is_visible)
             VALUES (:station_id, :message, :sort_order, :is_visible)'
        );
        $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
        $stt->bindValue(':message', $message);
        $stt->bindValue(':sort_order', $next_sort_order, PDO::PARAM_INT);
        $stt->bindValue(':is_visible', 1, PDO::PARAM_INT);
        $stt->execute();
        $message_id = (int)$db->lastInsertId();
    }

    if ($has_drag_speed) {
        update_station_message_settings($db, $station_id, $drag_speed);
    }

    echo json_encode(array('ok' => true, 'message_id' => $message_id, 'drag_speed' => $drag_speed), JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(array('ok' => false, 'message' => 'error:' . $e->getMessage()), JSON_UNESCAPED_UNICODE);
}
