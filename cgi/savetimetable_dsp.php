<?php
require_once __DIR__ . '/../lib/admin_bootstrap.php';
header('Content-type: application/json; charset=utf-8');

$station_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($station_id <= 0) {
    echo json_encode(array('ok' => false, 'message' => 'id is required'), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stt = $db->prepare('SELECT ch FROM display WHERE display_id = :id LIMIT 1');
    $stt->bindValue(':id', $station_id, PDO::PARAM_INT);
    $stt->execute();
    $display_row = $stt->fetch(PDO::FETCH_ASSOC);

    if ($display_row) {
        $next = (((int)$display_row['ch']) + 1) % 100;
        $stt = $db->prepare('UPDATE display SET ch = :ch WHERE display_id = :id');
        $stt->bindValue(':id', $station_id, PDO::PARAM_INT);
        $stt->bindValue(':ch', $next, PDO::PARAM_INT);
        $stt->execute();
    } else {
        $stt = $db->prepare('INSERT INTO display (display_id, reset, ch) VALUES (:id, :reset, :ch)');
        $stt->bindValue(':id', $station_id, PDO::PARAM_INT);
        $stt->bindValue(':reset', date('Y-m-d H:i:s'));
        $stt->bindValue(':ch', 1, PDO::PARAM_INT);
        $stt->execute();
    }

    echo json_encode(array('ok' => true, 'message' => 'OK'), JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(array('ok' => false, 'message' => 'error:' . $e->getMessage()), JSON_UNESCAPED_UNICODE);
}
