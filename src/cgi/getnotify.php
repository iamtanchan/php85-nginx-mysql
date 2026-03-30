<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin_bootstrap.php';
require_once __DIR__ . '/../lib/notify_library.php';

header('Content-Type: application/json; charset=utf-8');

$station_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($station_id <= 0) {
    echo json_encode(
        array(
            'active' => false,
            'mode' => '',
            'key' => '',
            'label' => '',
            'title' => '',
            'image_path' => '',
            'updated_at' => '',
            'last_departure' => '',
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

try {
    $notice = notify_resolve_active_notice($db, $station_id);
    echo json_encode($notice, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(
        array(
            'active' => false,
            'mode' => '',
            'key' => '',
            'label' => '',
            'title' => '',
            'image_path' => '',
            'updated_at' => '',
            'last_departure' => '',
            'error' => $e->getMessage(),
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}
