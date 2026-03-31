<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/content_library.php';
require_once __DIR__ . '/../lib/admin_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$station_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($station_id <= 0) {
    echo json_encode(
        array(
            'items' => array(),
            'swap_interval_seconds' => CONTENT_DISPLAY_SWAP_INTERVAL_DEFAULT,
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

try {
    $rows = content_fetch_published_rows($db, $station_id);
    $settings = content_fetch_display_settings($db, $station_id);
    $items = array();

    foreach ($rows as $row) {
        $content_value = trim((string)($row['content_value'] ?? ''));
        if ($content_value === '') {
            continue;
        }

        $items[] = array(
            'id' => (int)$row['id'],
            'sort_order' => (int)$row['sort_order'],
            'station_id' => (int)$row['station_id'],
            'slot_no' => isset($row['slot_no']) ? (int)$row['slot_no'] : 0,
            'title' => (string)($row['title'] ?? ''),
            'content_type' => (string)($row['content_type'] ?? 'image'),
            'content_value' => $content_value,
            'updated_at' => (string)($row['updated_at'] ?? ''),
        );
    }

    echo json_encode(
        array(
            'items' => $items,
            'swap_interval_seconds' => (int)$settings['swap_interval_seconds'],
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(
        array(
            'items' => array(),
            'swap_interval_seconds' => CONTENT_DISPLAY_SWAP_INTERVAL_DEFAULT,
            'error' => $e->getMessage(),
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}
