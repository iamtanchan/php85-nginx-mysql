<?php
require_once __DIR__ . '/../lib/admin_bootstrap.php';

header('Content-type: application/json; charset=utf-8');

$name = trim((string)($_POST['name'] ?? ''));
$name_e = trim((string)($_POST['name_e'] ?? ''));

if ($name === '') {
    http_response_code(400);
    echo json_encode(array('status' => 'error', 'message' => 'name is required'), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!isset($db) || !($db instanceof PDO)) {
        throw new RuntimeException('Database connection is not available');
    }

    /** @noinspection SqlNoDataSourceInspection */
    $sql = 'SELECT destination_id FROM destination ORDER BY destination_id DESC LIMIT 1';
    $stt = $db->prepare($sql);
    $stt->execute();
    $row = $stt->fetch();
    $id = isset($row['destination_id']) ? ((int)$row['destination_id'] + 1) : 1;
    error_log("addstations id=".$id.",date=".strftime('%Y-%m-%d %H:%M:%S',time())."\r\n", 3, 'debug.log');

    /** @noinspection SqlNoDataSourceInspection */
    $sst = $db->prepare('INSERT INTO destination
            (destination_id, name, name_e)
        VALUES  (:id, :name, :name_e)');
    $sst->bindValue(':id', $id);
    $sst->bindValue(':name', $name);
    $sst->bindValue(':name_e', $name_e);
    $sst->execute();

    echo json_encode(array('status' => 'ok'), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('addstations error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(array('status' => 'error', 'message' => 'failed to add station'), JSON_UNESCAPED_UNICODE);
}
