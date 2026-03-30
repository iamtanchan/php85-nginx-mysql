<?php
require_once __DIR__ . '/../lib/admin_bootstrap.php';
require_once __DIR__ . '/../lib/timetable_sync.php';

header('Content-type: application/json; charset=utf-8');

function respond($ok, $message, $extra = array())
{
    echo json_encode(array_merge(array('ok' => $ok, 'message' => $message), $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

$station_id = isset($_POST['station_id']) ? (int)$_POST['station_id'] : 0;
$season_id = isset($_POST['season_id']) ? (int)$_POST['season_id'] : 0;

if ($station_id <= 0) {
    respond(false, 'station_id is required');
}
if ($season_id <= 0) {
    respond(false, 'season_id is required');
}

try {
    $season_stt = $db->prepare('SELECT name FROM season WHERE season_id = :season_id LIMIT 1');
    $season_stt->bindValue(':season_id', $season_id, PDO::PARAM_INT);
    $season_stt->execute();
    $season_row = $season_stt->fetch(PDO::FETCH_ASSOC);
    if (!$season_row) {
        respond(false, '選択したダイヤ期間が見つかりません。');
    }

    $season_name = trim((string)($season_row['name'] ?? ''));
    $registered_count = sync_timetable_from_season($db, $station_id, $season_id);

    if ($registered_count > 0) {
        respond(true, $season_name . ' のデータを時刻表へ登録しました。件数: ' . $registered_count, array(
            'status_type' => 'success',
            'registered_count' => $registered_count,
        ));
    }

    respond(true, $season_name . ' に登録対象のダイヤデータがないため、時刻表を空で保存しました。', array(
        'status_type' => 'success',
        'registered_count' => 0,
    ));
} catch (Throwable $e) {
    respond(false, '登録に失敗しました: ' . $e->getMessage(), array(
        'status_type' => 'error',
    ));
}
