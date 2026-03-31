<?php
require_once __DIR__ . '/../lib/admin_bootstrap.php';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

try{
    $rows = $id > 0 ? fetch_visible_station_message_rows($db, $id) : array();
    $settings = $id > 0 ? fetch_station_message_settings($db, $id) : array('drag_speed' => MESSAGE_DRAG_SPEED_DEFAULT);
    $messages = array();
    foreach ($rows as $row) {
        $text = trim((string)($row['message'] ?? ''));
        if ($text === '') {
            continue;
        }
        $messages[] = array(
            'message_id' => (int)$row['message_id'],
            'message' => $text,
            'message_e' => trim((string)($row['message_e'] ?? '')),
            'sort_order' => (int)$row['sort_order'],
            'is_visible' => (int)$row['is_visible'],
        );
    }

    $res = array(
        'message' => count($messages) > 0 ? (string)$messages[0]['message'] : '',
        'messages' => $messages,
        'drag_speed' => (int)$settings['drag_speed'],
    );
	//jsonとして出力
	header('Content-type: application/json; charset=utf-8 ');
	echo json_encode($res,JSON_UNESCAPED_UNICODE);

} catch(PDOException $e){
	die('error'.$e->getMessage());
}
