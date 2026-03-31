<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/admin_bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

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

    $station_id = isset($_POST['station_id']) ? (int)$_POST['station_id'] : 0;
    if ($station_id <= 0) {
        http_response_code(400);
        echo json_encode(array(
            'ok' => false,
            'message' => 'station_id is required.',
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $current_setting = fetch_station_display_language_settings($db, $station_id);
    $enabled = !((bool)$current_setting['english_enabled']);
    update_station_display_language_settings($db, $station_id, $enabled);

    echo json_encode(array(
        'ok' => true,
        'enabled' => $enabled,
        'message' => $enabled ? '英語表示を ON にしました。' : '英語表示を OFF にしました。',
    ), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(array(
        'ok' => false,
        'message' => '設定の更新に失敗しました。',
    ), JSON_UNESCAPED_UNICODE);
}
