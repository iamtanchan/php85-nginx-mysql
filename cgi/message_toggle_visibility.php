<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    require_login($db);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(array(
            'ok' => false,
            'message' => 'Method not allowed.',
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $station_id = isset($_POST['s']) ? (int)$_POST['s'] : 0;
    $message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
    if ($station_id <= 0 || $message_id <= 0) {
        http_response_code(400);
        echo json_encode(array(
            'ok' => false,
            'message' => 'station_id and message_id are required.',
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stt = $db->prepare(
        'SELECT is_visible
         FROM message
         WHERE station_id = :station_id AND message_id = :message_id
         LIMIT 1'
    );
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':message_id', $message_id, PDO::PARAM_INT);
    $stt->execute();
    $row = $stt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(array(
            'ok' => false,
            'message' => '表示設定の対象メッセージが見つかりません。',
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $enabled = (int)$row['is_visible'] !== 1;

    $update = $db->prepare(
        'UPDATE message
         SET is_visible = :is_visible
         WHERE station_id = :station_id AND message_id = :message_id'
    );
    $update->bindValue(':is_visible', $enabled ? 1 : 0, PDO::PARAM_INT);
    $update->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $update->bindValue(':message_id', $message_id, PDO::PARAM_INT);
    $update->execute();

    echo json_encode(array(
        'ok' => true,
        'message_id' => $message_id,
        'enabled' => $enabled,
        'message' => '表示設定を更新しました。',
    ), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(array(
        'ok' => false,
        'message' => '表示設定の更新に失敗しました。',
    ), JSON_UNESCAPED_UNICODE);
}
