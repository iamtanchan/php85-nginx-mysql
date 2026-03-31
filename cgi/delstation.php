<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(array(
        'status' => 'error',
        'message' => 'invalid destination id',
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    /** @noinspection SqlNoDataSourceInspection */
    $check = $db->prepare('SELECT destination_id FROM destination WHERE destination_id = :id LIMIT 1');
    $check->bindValue(':id', $id, PDO::PARAM_INT);
    $check->execute();
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode(array(
            'status' => 'error',
            'message' => 'destination not found',
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db->beginTransaction();

    /** @noinspection SqlNoDataSourceInspection */
    $stt = $db->prepare('DELETE FROM destination WHERE destination_id = :id');
    $stt->bindValue(':id', $id, PDO::PARAM_INT);
    $stt->execute();

    $db->commit();

    echo json_encode(array(
        'status' => 'ok',
    ), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('delstation error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(array(
        'status' => 'error',
        'message' => 'failed to delete destination',
    ), JSON_UNESCAPED_UNICODE);
}
