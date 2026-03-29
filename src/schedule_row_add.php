<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/admin_bootstrap.php';

require_login($db);
$stations = get_active_stations(get_stations($db));
$requested_station_id = isset($_REQUEST['s']) ? (int)$_REQUEST['s'] : 0;
$station_id = resolve_station_id($stations, $requested_station_id);
$station_name = get_station_name($stations, $station_id);
$season_id = isset($_REQUEST['season_id']) ? (int)$_REQUEST['season_id'] : 0;
$row_id = isset($_REQUEST['row_id']) ? (int)$_REQUEST['row_id'] : 0;

$status_type = '';
$status_message = '';
$departure_time = trim((string)($_POST['departure_time'] ?? ''));
$ship_id = isset($_POST['ship_id']) ? (int)$_POST['ship_id'] : 0;
$destination_id = isset($_POST['destination_id']) ? (int)$_POST['destination_id'] : 0;

$editing_row = null;
if ($row_id > 0) {
    $row_stt = $db->prepare(
        'SELECT schedule_id, station_id, season_id, departure_time, ship_id, destination_id
         FROM schedule
         WHERE schedule_id = :row_id AND station_id = :station_id
         LIMIT 1'
    );
    $row_stt->bindValue(':row_id', $row_id, PDO::PARAM_INT);
    $row_stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $row_stt->execute();
    $editing_row = $row_stt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($editing_row === null) {
        $status_type = 'error';
        $status_message = '編集対象の行が見つかりません。';
        $row_id = 0;
    } else {
        $season_id = isset($editing_row['season_id']) ? (int)$editing_row['season_id'] : $season_id;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $departure_time = substr((string)($editing_row['departure_time'] ?? ''), 0, 5);
            $ship_id = isset($editing_row['ship_id']) ? (int)$editing_row['ship_id'] : 0;
            $destination_id = isset($editing_row['destination_id']) ? (int)$editing_row['destination_id'] : 0;
        }
    }
}

$ship_rows = array();
$ship_stt = $db->prepare('SELECT ship_id AS id, name FROM ship ORDER BY ship_id');
$ship_stt->execute();
$ship_rows = $ship_stt->fetchAll(PDO::FETCH_ASSOC);

$destination_rows = array();
$dest_stt = $db->prepare('SELECT destination_id AS id, name FROM destination ORDER BY destination_id');
$dest_stt->execute();
$destination_rows = $dest_stt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $status_message === '') {
    try {
        if ($season_id <= 0) {
            throw new RuntimeException('ダイヤ期間を選択してください。');
        }
        if ($departure_time === '') {
            throw new RuntimeException('出発時刻を入力してください。');
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $departure_time)) {
            throw new RuntimeException('出発時刻の形式が不正です。');
        }
        if ($ship_id <= 0) {
            throw new RuntimeException('艇名を選択してください。');
        }
        if ($destination_id <= 0) {
            throw new RuntimeException('行先を選択してください。');
        }

        $priority_stt = $db->prepare(
            'SELECT COALESCE(MAX(priority), 0) + 1 AS next_priority
             FROM schedule
             WHERE station_id = :station_id AND season_id = :season_id'
        );
        $priority_stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
        $priority_stt->bindValue(':season_id', $season_id, PDO::PARAM_INT);
        $priority_stt->execute();
        $next_priority = (int)($priority_stt->fetch(PDO::FETCH_ASSOC)['next_priority'] ?? 1);

        if ($row_id > 0) {
            $upd = $db->prepare(
                'UPDATE schedule
                 SET departure_time = :departure_time, ship_id = :ship_id, destination_id = :destination_id
                 WHERE schedule_id = :row_id AND station_id = :station_id'
            );
            $upd->bindValue(':departure_time', $departure_time . ':00');
            $upd->bindValue(':ship_id', $ship_id, PDO::PARAM_INT);
            $upd->bindValue(':destination_id', $destination_id, PDO::PARAM_INT);
            $upd->bindValue(':row_id', $row_id, PDO::PARAM_INT);
            $upd->bindValue(':station_id', $station_id, PDO::PARAM_INT);
            $upd->execute();
        } else {
            $ins = $db->prepare(
                'INSERT INTO schedule (station_id, season_id, departure_time, ship_id, destination_id, priority, is_active, note)
                 VALUES (:station_id, :season_id, :departure_time, :ship_id, :destination_id, :priority, 1, "")'
            );
            $ins->bindValue(':station_id', $station_id, PDO::PARAM_INT);
            $ins->bindValue(':season_id', $season_id, PDO::PARAM_INT);
            $ins->bindValue(':departure_time', $departure_time . ':00');
            $ins->bindValue(':ship_id', $ship_id, PDO::PARAM_INT);
            $ins->bindValue(':destination_id', $destination_id, PDO::PARAM_INT);
            $ins->bindValue(':priority', $next_priority, PDO::PARAM_INT);
            $ins->execute();
        }

        redirect_with_params('schedule.php', array(
            's' => $station_id,
            'season_id' => $season_id,
        ));
    } catch (Throwable $e) {
        $status_type = 'error';
        $status_message = $e->getMessage();
    }
}

$back_href = 'schedule.php?s=' . $station_id;
if ($season_id > 0) {
    $back_href .= '&season_id=' . $season_id;
}

$page_title = $row_id > 0 ? 'スケジュール行編集' : 'スケジュール行追加';
$submit_label = $row_id > 0 ? '更新する' : '追加する';
?>
<!doctype html>
<html lang="ja">
<head>
    <?php render_app_head($page_title); ?>
</head>
<body class="<?php print(app_body_classes()); ?>">
<div class="adm-page dial-page">
    <div class="<?php print(app_page_shell_classes()); ?>">
        <?php
        $shared_header_station_name = $station_name;
        $shared_header_page_title = $page_title;
        $shared_header_station_id = $station_id;
        require __DIR__ . '/header.php';
        ?>

        <?php if ($status_message !== '') { ?>
            <div class="adm-alert <?php print(h($status_type)); ?> <?php print(app_alert_classes($status_type === 'error' ? 'error' : 'success')); ?>"><?php print(h($status_message)); ?></div>
        <?php } ?>

        <section class="dial-board mx-auto <?php print(app_panel_classes('max-w-3xl')); ?>">
            <form class="dial-create-form space-y-5" method="post" action="schedule_row_add.php">
                <input type="hidden" name="s" value="<?php print($station_id); ?>">
                <input type="hidden" name="season_id" value="<?php print($season_id); ?>">
                <input type="hidden" name="row_id" value="<?php print($row_id); ?>">

                <div class="<?php print(app_field_classes()); ?>">
                    <label class="<?php print(app_label_classes()); ?>" for="departureTime">出発時刻</label>
                    <input
                        id="departureTime"
                        class="<?php print(app_input_classes()); ?>"
                        name="departure_time"
                        type="time"
                        required
                        value="<?php print(h($departure_time)); ?>"
                    >
                </div>

                <div class="<?php print(app_field_classes()); ?>">
                    <label class="<?php print(app_label_classes()); ?>" for="shipId">艇名</label>
                    <select id="shipId" class="<?php print(app_select_classes()); ?>" name="ship_id" required>
                        <option value="">選択してください</option>
                        <?php foreach ($ship_rows as $row) { ?>
                            <?php $id = (int)($row['id'] ?? 0); ?>
                            <option value="<?php print($id); ?>" <?php if ($id === $ship_id) { print('selected'); } ?>>
                                <?php print(h((string)($row['name'] ?? ''))); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="<?php print(app_field_classes()); ?>">
                    <label class="<?php print(app_label_classes()); ?>" for="destinationId">行先</label>
                    <select id="destinationId" class="<?php print(app_select_classes()); ?>" name="destination_id" required>
                        <option value="">選択してください</option>
                        <?php foreach ($destination_rows as $row) { ?>
                            <?php $id = (int)($row['id'] ?? 0); ?>
                            <option value="<?php print($id); ?>" <?php if ($id === $destination_id) { print('selected'); } ?>>
                                <?php print(h((string)($row['name'] ?? ''))); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="dial-create-actions flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
                    <a class="adm-btn <?php print(app_button_classes('secondary')); ?>" href="<?php print(h($back_href)); ?>">キャンセル</a>
                    <button class="adm-btn adm-btn-pink <?php print(app_button_classes('primary')); ?>" type="submit"><?php print($submit_label); ?></button>
                </div>
            </form>
        </section>
    </div>
</div>
<?php render_app_scripts(); ?>
</body>
</html>
