<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin_bootstrap.php';
require_once __DIR__ . '/../lib/guidance_library.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $state = guidance_load_state();
    echo json_encode(guidance_export_payload($state), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(
        array(
            'ready' => false,
            'lead_minutes' => GUIDANCE_LEAD_MINUTES_DEFAULT,
            'items' => array(),
            'updated_at' => '',
            'error' => $e->getMessage(),
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}
