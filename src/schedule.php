<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/admin_bootstrap.php';

require_login($db);
$stations = get_active_stations(get_stations($db));
$requested_station_id = isset($_REQUEST['s']) ? (int)$_REQUEST['s'] : 0;
$station_id = resolve_station_id($stations, $requested_station_id);
$station_name = get_station_name($stations, $station_id);
$requested_dial = trim((string)($_REQUEST['d'] ?? ''));
$requested_season_id = isset($_REQUEST['season_id']) ? (int)$_REQUEST['season_id'] : 0;
$requested_view = trim((string)($_REQUEST['view'] ?? ''));
$current_view = $requested_view === 'manage' ? 'manage' : 'main';
$show_create_panel = isset($_REQUEST['create']) && (string)$_REQUEST['create'] === '1';

if ($current_view === 'manage' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $params = array('s' => $station_id);
    if ($requested_dial !== '') {
        $params['d'] = $requested_dial;
    }
    if ($show_create_panel) {
        $params['create'] = '1';
    }
    redirect_with_params('season.php', $params);
}

function get_dial_codes(PDO $db, int $station_id): array
{
    $stt = $db->prepare(
        'SELECT
             s.name AS dial_code,
             COUNT(sc.schedule_id) AS row_count,
             MIN(sc.departure_time) AS min_time,
             MAX(sc.departure_time) AS max_time
         FROM season s
         LEFT JOIN schedule sc
           ON sc.season_id = s.season_id
          AND sc.station_id = :station_id
         GROUP BY s.season_id, s.name, s.start_date
         ORDER BY
             CASE
                 WHEN NULLIF(CAST(s.start_date AS CHAR(10)), \'0000-00-00\') IS NULL THEN 1
                 ELSE 0
             END ASC,
             NULLIF(CAST(s.start_date AS CHAR(10)), \'0000-00-00\') ASC,
             s.season_id ASC'
    );
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->execute();
    return $stt->fetchAll(PDO::FETCH_ASSOC);
}

function get_season_options(PDO $db, int $station_id): array
{
    $season_select = 's.season_id AS id,
        s.name,
        CASE
            WHEN NULLIF(CAST(s.start_date AS CHAR(10)), \'0000-00-00\') IS NULL THEN 1
            ELSE 0
        END AS sort_start_missing,
        NULLIF(CAST(s.start_date AS CHAR(10)), \'0000-00-00\') AS sort_start_date';

    $stt = $db->prepare(
        'SELECT ' . $season_select . '
         FROM season s
         ORDER BY sort_start_missing ASC, sort_start_date ASC, id ASC'
    );
    $stt->execute();
    return $stt->fetchAll(PDO::FETCH_ASSOC);
}

function normalize_dial_code(string $raw): string
{
    $value = trim($raw);
    return preg_replace('/[^A-Za-z0-9_-]/', '', $value) ?? '';
}

function dial_exists(PDO $db, int $station_id, string $dial_code): bool
{
    $stt = $db->prepare(
        'SELECT 1 FROM timetable WHERE station_id = :station_id AND dial_code = :dial_code LIMIT 1'
    );
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':dial_code', $dial_code);
    $stt->execute();
    return (bool)$stt->fetch(PDO::FETCH_ASSOC);
}

function clone_dial_rows(PDO $db, int $station_id, string $from_dial, string $to_dial): int
{
    $sql = 'INSERT INTO timetable (
                station_id,
                dial_code,
                time,
                ship_id,
                destination_id,
                destination2_id,
                soldout,
                status,
                detail,
                ontime,
                offtime,
                badge_id
            )
            SELECT
                station_id,
                :to_dial AS dial_code,
                time,
                ship_id,
                destination_id,
                destination2_id,
                soldout,
                status,
                detail,
                ontime,
                offtime,
                badge_id
            FROM timetable
            WHERE station_id = :station_id AND dial_code = :from_dial';
    $stt = $db->prepare($sql);
    $stt->bindValue(':to_dial', $to_dial);
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':from_dial', $from_dial);
    $stt->execute();
    return $stt->rowCount();
}

function create_default_dial_rows(PDO $db, int $station_id, string $dial_code): int
{
    $times = array('09:00:00', '12:00:00', '15:00:00');
    $inserted = 0;
    foreach ($times as $time) {
        $sql = 'INSERT INTO timetable (
                    station_id,
                    dial_code,
                    time,
                    ship_id,
                    destination_id,
                    destination2_id,
                    soldout,
                    status,
                    detail,
                    ontime,
                    offtime,
                    badge_id
                )
                VALUES (
                    :station_id,
                    :dial_code,
                    :time,
                    :ship_id,
                    :destination_id,
                    :destination2_id,
                    :soldout,
                    :status,
                    :detail,
                    :ontime,
                    :offtime,
                    :badge_id
                )';
        $stt = $db->prepare($sql);
        $stt->execute(array(
            ':station_id' => $station_id,
            ':dial_code' => $dial_code,
            ':time' => $time,
            ':ship_id' => 0,
            ':destination_id' => 0,
            ':destination2_id' => 0,
            ':soldout' => 0,
            ':status' => 0,
            ':detail' => 0,
            ':ontime' => 15,
            ':offtime' => 10,
            ':badge_id' => 0,
        ));
        $inserted += 1;
    }
    return $inserted;
}

function get_ship_name_map(PDO $db): array
{
    $stt = $db->prepare('SELECT ship_id AS id, name FROM ship ORDER BY ship_id');
    $stt->execute();

    $map = array();
    foreach ($stt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int)$row['id']] = (string)($row['name'] ?? '');
    }
    return $map;
}

function get_destination_name_map(PDO $db): array
{
    $stt = $db->prepare('SELECT destination_id AS id, name FROM destination ORDER BY destination_id');
    $stt->execute();

    $map = array();
    foreach ($stt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int)$row['id']] = (string)($row['name'] ?? '');
    }
    return $map;
}

function resolve_selected_dial(array $dial_rows, string $requested_dial): string
{
    foreach ($dial_rows as $dial_row) {
        if ((string)$dial_row['dial_code'] === $requested_dial) {
            return $requested_dial;
        }
    }
    if (count($dial_rows) > 0) {
        return (string)$dial_rows[0]['dial_code'];
    }
    return '';
}

function resolve_selected_season(array $season_rows, int $requested_season_id, string $requested_dial): array
{
    foreach ($season_rows as $season_row) {
        $season_id = isset($season_row['id']) ? (int)$season_row['id'] : 0;
        $season_name = trim((string)($season_row['name'] ?? ''));
        if ($requested_season_id > 0 && $season_id === $requested_season_id) {
            return array(
                'id' => $season_id,
                'name' => $season_name,
            );
        }
    }

    if ($requested_dial !== '') {
        foreach ($season_rows as $season_row) {
            $season_id = isset($season_row['id']) ? (int)$season_row['id'] : 0;
            $season_name = trim((string)($season_row['name'] ?? ''));
            if ($season_name !== '' && $season_name === $requested_dial) {
                return array(
                    'id' => $season_id,
                    'name' => $season_name,
                );
            }
        }
    }

    if (count($season_rows) > 0) {
        return array(
            'id' => isset($season_rows[0]['id']) ? (int)$season_rows[0]['id'] : 0,
            'name' => trim((string)($season_rows[0]['name'] ?? '')),
        );
    }

    return array();
}

function get_schedule_preview_rows(PDO $db, int $station_id, int $season_id, array $ship_name_map, array $destination_name_map): array
{
    if ($season_id <= 0) {
        return array();
    }

    $stt = $db->prepare(
        'SELECT schedule_id AS id, departure_time, ship_id, destination_id, priority
         FROM schedule
         WHERE station_id = :station_id AND season_id = :season_id
         ORDER BY priority ASC, departure_time ASC, schedule_id ASC'
    );
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':season_id', $season_id, PDO::PARAM_INT);
    $stt->execute();

    $rows = array();
    foreach ($stt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ship_id = isset($row['ship_id']) ? (int)$row['ship_id'] : 0;
        $destination_id = isset($row['destination_id']) ? (int)$row['destination_id'] : 0;
        $rows[] = array(
            'row_id' => isset($row['id']) ? (int)$row['id'] : 0,
            'time' => substr((string)($row['departure_time'] ?? ''), 0, 5),
            'ship_name' => (string)($ship_name_map[$ship_id] ?? ''),
            'destination_name' => (string)($destination_name_map[$destination_id] ?? ''),
        );
    }

    return $rows;
}

function get_dial_preview_rows(PDO $db, int $station_id, string $dial_code, array $ship_name_map, array $destination_name_map): array
{
    if ($dial_code === '') {
        return array();
    }

    $stt = $db->prepare(
        'SELECT timetable_id AS id, time, ship_id, destination_id
         FROM timetable
         WHERE station_id = :station_id AND dial_code = :dial_code
         ORDER BY time ASC'
    );
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':dial_code', $dial_code);
    $stt->execute();

    $rows = array();
    foreach ($stt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ship_id = isset($row['ship_id']) ? (int)$row['ship_id'] : 0;
        $destination_id = isset($row['destination_id']) ? (int)$row['destination_id'] : 0;
        $rows[] = array(
            'row_id' => isset($row['id']) ? (int)$row['id'] : 0,
            'time' => substr((string)($row['time'] ?? ''), 0, 5),
            'ship_name' => (string)($ship_name_map[$ship_id] ?? ''),
            'destination_name' => (string)($destination_name_map[$destination_id] ?? ''),
        );
    }

    return $rows;
}

function pad_preview_rows(array $rows, int $target_count): array
{
    while (count($rows) < $target_count) {
        $rows[] = array(
            'row_id' => 0,
            'time' => '',
            'ship_name' => '',
            'destination_name' => '',
        );
    }
    return $rows;
}

function pad_dial_rows(array $rows, int $target_count): array
{
    while (count($rows) < $target_count) {
        $rows[] = array(
            'dial_code' => '',
            'row_count' => 0,
            'min_time' => null,
            'max_time' => null,
        );
    }
    return $rows;
}

$status_type = '';
$status_message = '';
$new_dial_value = '';
$copy_from_value = $requested_dial;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $station_id = resolve_station_id($stations, (int)($_POST['s'] ?? $station_id));
    $requested_dial = trim((string)($_POST['d'] ?? $requested_dial));
    $requested_season_id = isset($_POST['season_id']) ? (int)$_POST['season_id'] : $requested_season_id;
    $posted_view = trim((string)($_POST['view'] ?? $current_view));
    $current_view = $posted_view === 'manage' ? 'manage' : 'main';
    $show_create_panel = isset($_POST['show_create']) && (string)($_POST['show_create'] ?? '') === '1';
    $action = trim((string)($_POST['action'] ?? ''));

    try {
        if ($action === 'delete_schedule_row') {
            $row_id = (int)($_POST['row_id'] ?? 0);
            if ($row_id <= 0 || $requested_season_id <= 0) {
                throw new RuntimeException('有効な行を選択してください。');
            }

            $stt = $db->prepare(
                'DELETE FROM schedule WHERE schedule_id = :row_id AND station_id = :station_id AND season_id = :season_id'
            );
            $stt->bindValue(':row_id', $row_id, PDO::PARAM_INT);
            $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
            $stt->bindValue(':season_id', $requested_season_id, PDO::PARAM_INT);
            $stt->execute();

            $status_type = 'success';
            $status_message = '行を削除しました。';
        } elseif ($action === 'create') {
            $show_create_panel = true;
            $new_dial_value = trim((string)($_POST['new_dial'] ?? ''));
            $copy_from_value = trim((string)($_POST['copy_from'] ?? ''));
            $new_dial = normalize_dial_code($new_dial_value);
            if ($new_dial === '') {
                throw new RuntimeException('ダイヤコードを入力してください。');
            }
            if (dial_exists($db, $station_id, $new_dial)) {
                throw new RuntimeException('そのダイヤコードは既に存在します。');
            }

            $db->beginTransaction();
            $inserted = 0;
            if ($copy_from_value !== '' && dial_exists($db, $station_id, $copy_from_value)) {
                $inserted = clone_dial_rows($db, $station_id, $copy_from_value, $new_dial);
            }
            if ($inserted === 0) {
                $inserted = create_default_dial_rows($db, $station_id, $new_dial);
            }
            $db->commit();

            $requested_dial = $new_dial;
            $show_create_panel = false;
            $new_dial_value = '';
            $copy_from_value = '';
            $status_type = 'success';
            $status_message = 'ダイヤを追加しました。作成行数: ' . $inserted;
        } elseif ($action === 'delete') {
            $dial_code = trim((string)($_POST['dial_code'] ?? ''));
            if ($dial_code === '' || $dial_code === 'default') {
                throw new RuntimeException('既定のダイヤ（default）は削除できません。');
            }

            $db->beginTransaction();
            $stt = $db->prepare('DELETE FROM timetable WHERE station_id = :station_id AND dial_code = :dial_code');
            $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
            $stt->bindValue(':dial_code', $dial_code);
            $stt->execute();

            $del = $db->prepare(
                'DELETE sc
                 FROM schedule sc
                 JOIN season s ON s.season_id = sc.season_id
                 WHERE sc.station_id = :station_id AND s.name = :dial_code'
            );
            $del->bindValue(':station_id', $station_id, PDO::PARAM_INT);
            $del->bindValue(':dial_code', $dial_code);
            $del->execute();
            $db->commit();

            if ($requested_dial === $dial_code) {
                $requested_dial = '';
            }
            $status_type = 'success';
            $status_message = 'ダイヤを削除しました。';
        } elseif ($action === 'delete_row') {
            $row_id = (int)($_POST['row_id'] ?? 0);
            $dial_code = trim((string)($_POST['dial_code'] ?? $requested_dial));
            if ($row_id <= 0 || $dial_code === '') {
                throw new RuntimeException('有効な行を選択してください。');
            }

            $stt = $db->prepare(
                'DELETE FROM timetable WHERE timetable_id = :row_id AND station_id = :station_id AND dial_code = :dial_code'
            );
            $stt->bindValue(':row_id', $row_id, PDO::PARAM_INT);
            $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
            $stt->bindValue(':dial_code', $dial_code);
            $stt->execute();

            $status_type = 'success';
            $status_message = '行を削除しました。';
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $status_type = 'error';
        $status_message = $e->getMessage();
    }
}

$dial_rows = get_dial_codes($db, $station_id);
$season_rows = get_season_options($db, $station_id);
$selected_season = resolve_selected_season($season_rows, $requested_season_id, $requested_dial);
$selected_season_id = isset($selected_season['id']) ? (int)$selected_season['id'] : 0;
$selected_dial = resolve_selected_dial($dial_rows, $requested_dial);
if ($selected_season_id > 0) {
    $selected_dial = (string)($selected_season['name'] ?? '');
}
$manage_rows = pad_dial_rows($dial_rows, 4);
$ship_name_map = get_ship_name_map($db);
$destination_name_map = get_destination_name_map($db);
$preview_rows = ($selected_season_id > 0)
    ? get_schedule_preview_rows($db, $station_id, $selected_season_id, $ship_name_map, $destination_name_map)
    : array();
$preview_scroll_class = count($preview_rows) > 5 ? ' dial-board-scroll' : '';

$manage_href = 'season.php?s=' . $station_id;
$main_href = 'schedule.php?s=' . $station_id;
if ($selected_season_id > 0) {
    $main_href .= '&season_id=' . $selected_season_id;
}
?>
<!doctype html>
<html lang="ja">
<head>
    <?php render_app_head('ダイヤ設定'); ?>
</head>
<body class="<?php print(app_body_classes()); ?>">
<div class="adm-page dial-page">
    <div class="<?php print(app_page_shell_classes()); ?>">
        <?php
        $shared_header_station_name = $station_name;
        $shared_header_page_title = 'ダイヤ設定';
        $shared_header_station_id = $station_id;
        require __DIR__ . '/header.php';
        ?>
        <?php if ($status_message !== '') { ?>
            <div class="adm-alert <?php print(h($status_type)); ?> <?php print(app_alert_classes($status_type === 'error' ? 'error' : 'success')); ?>"><?php print(h($status_message)); ?></div>
        <?php } ?>

        <?php if ($current_view === 'manage') { ?>
            <div class="dial-manage-toolbar mb-6 flex justify-end">
                <a class="dial-action-btn <?php print(app_button_classes('primary')); ?>" href="season.php?s=<?php print($station_id); ?>&d=<?php print(urlencode($selected_dial)); ?>&create=1" data-open-create>ダイヤ期間を追加</a>
            </div>

            <section class="dial-board dial-manage-board <?php print(app_panel_classes()); ?>">
                <div class="<?php print(app_table_frame_classes('overflow-x-auto')); ?>">
                <table class="dial-board-table dial-manage-table <?php print(app_table_classes()); ?>">
                    <thead>
                    <tr>
                        <th class="dial-manage-col-name bg-slate-950/95 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">ダイヤ</th>
                        <th class="dial-manage-col-action bg-slate-950/95 px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($manage_rows as $dial_row) { ?>
                        <?php $dial_code = (string)($dial_row['dial_code'] ?? ''); ?>
                        <tr class="odd:bg-white even:bg-slate-50/60">
                            <td class="dial-manage-name border-b border-slate-200/70 px-4 py-4 font-semibold text-slate-800"><?php if ($dial_code !== '') { print(h($dial_code)); } ?></td>
                            <td class="dial-cell-actions border-b border-slate-200/70 px-4 py-4">
                                <div class="dial-row-actions flex flex-wrap justify-end gap-2">
                                    <?php if ($dial_code !== '') { ?>
                                        <a class="dial-row-btn <?php print(app_button_classes('secondary', 'sm')); ?>" href="timetable.php?s=<?php print($station_id); ?>&d=<?php print(urlencode($dial_code)); ?>">編集</a>
                                        <?php if ($dial_code !== 'default') { ?>
                                            <form
                                                class="dial-row-form"
                                                method="post"
                                                action="schedule.php"
                                                data-confirm-message="このダイヤを削除しますか？"
                                                data-confirm-button="削除する"
                                                data-confirm-button-class="adm-btn adm-btn-danger"
                                            >
                                                <input type="hidden" name="s" value="<?php print($station_id); ?>">
                                                <input type="hidden" name="d" value="<?php print(h($selected_dial)); ?>">
                                                <input type="hidden" name="view" value="manage">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="dial_code" value="<?php print(h($dial_code)); ?>">
                                                <button class="dial-row-btn <?php print(app_button_classes('danger', 'sm')); ?>" type="submit">削除</button>
                                            </form>
                                        <?php } else { ?>
                                            <button class="dial-row-btn <?php print(app_button_classes('danger', 'sm')); ?>" type="button" disabled>削除</button>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <button class="dial-row-btn <?php print(app_button_classes('secondary', 'sm')); ?>" type="button" disabled>編集</button>
                                        <button class="dial-row-btn <?php print(app_button_classes('danger', 'sm')); ?>" type="button" disabled>削除</button>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
                </div>
            </section>

            <div class="dial-footer-row dial-manage-footer mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a class="dial-back-btn <?php print(app_button_classes('secondary')); ?>" href="<?php print(h($main_href)); ?>">戻る</a>
                <a class="dial-register-btn <?php print(app_button_classes('primary')); ?>" href="season.php?s=<?php print($station_id); ?>&d=<?php print(urlencode($selected_dial)); ?>&create=1" data-open-create>追加</a>
            </div>
        <?php } else { ?>
            <div class="dial-toolbar mb-6 <?php print(app_panel_classes()); ?>">
                <div class="dial-action-row flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <form class="dial-season-form" method="get" action="schedule.php">
                        <input type="hidden" name="s" value="<?php print($station_id); ?>">
                        <div class="<?php print(app_field_classes('min-w-[280px]')); ?>">
                        <label class="<?php print(app_label_classes()); ?>" for="scheduleSeasonSelect">ダイヤ期間</label>
                        <select id="scheduleSeasonSelect" class="dial-action-select dial-season-select <?php print(app_select_classes()); ?>" name="season_id" onchange="this.form.submit()" <?php if (count($season_rows) === 0) { print('disabled'); } ?>>
                            <?php if (count($season_rows) === 0) { ?>
                                <option value="">ダイヤ期間がありません</option>
                            <?php } ?>
                            <?php foreach ($season_rows as $season_row) { ?>
                                <?php $season_option_id = isset($season_row['id']) ? (int)$season_row['id'] : 0; ?>
                                <?php $season_option_name = trim((string)($season_row['name'] ?? '')); ?>
                                <option value="<?php print($season_option_id); ?>" <?php if ($season_option_id === $selected_season_id) { print('selected'); } ?>>
                                    <?php print(h($season_option_name)); ?>
                                </option>
                            <?php } ?>
                        </select>
                        </div>
                    </form>

                    <a class="dial-action-btn <?php print(app_button_classes('secondary')); ?>" href="<?php print(h($manage_href)); ?>">ダイヤ期間を管理</a>
                </div>

            </div>

            <section class="dial-board<?php print($preview_scroll_class); ?> <?php print(app_panel_classes()); ?>">
                <div class="<?php print(app_table_frame_classes('overflow-x-auto')); ?>">
                <table class="dial-board-table <?php print(app_table_classes()); ?>">
                    <thead>
                    <tr>
                        <th class="dial-col-time bg-slate-950/95 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">出発時刻</th>
                        <th class="dial-col-ship bg-slate-950/95 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">艇名</th>
                        <th class="dial-col-destination bg-slate-950/95 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">行先</th>
                        <th class="dial-col-action bg-slate-950/95 px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (count($preview_rows) === 0) { ?>
                        <tr>
                            <td colspan="4" class="dial-empty-cell border-b border-slate-200/70 px-4 py-10 text-center text-sm text-slate-500">該当するスケジュールがありません。</td>
                        </tr>
                    <?php } ?>
                    <?php foreach ($preview_rows as $row) { ?>
                        <tr class="odd:bg-white even:bg-slate-50/60">
                            <td class="dial-cell-time border-b border-slate-200/70 px-4 py-4 font-semibold text-slate-800"><?php print(h($row['time'])); ?></td>
                            <td class="border-b border-slate-200/70 px-4 py-4"><?php if ($row['time'] !== '') { ?><div class="dial-selectlike rounded-2xl bg-slate-100 px-4 py-3 text-sm font-medium text-slate-700"><?php print(h($row['ship_name'])); ?></div><?php } ?></td>
                            <td class="border-b border-slate-200/70 px-4 py-4"><?php if ($row['time'] !== '') { ?><div class="dial-selectlike rounded-2xl bg-slate-100 px-4 py-3 text-sm font-medium text-slate-700"><?php print(h($row['destination_name'])); ?></div><?php } ?></td>
                            <td class="dial-cell-actions border-b border-slate-200/70 px-4 py-4">
                                <div class="dial-row-actions flex flex-wrap justify-end gap-2">
                                    <a
                                        class="dial-row-btn<?php if ((int)$row['row_id'] <= 0) { print(' is-disabled'); } ?> <?php print(app_button_classes('secondary', 'sm')); ?>"
                                        href="<?php print((int)$row['row_id'] > 0 ? 'schedule_row_add.php?s=' . $station_id . '&season_id=' . $selected_season_id . '&row_id=' . (int)$row['row_id'] : '#'); ?>"
                                        <?php if ((int)$row['row_id'] <= 0) { ?>aria-disabled="true" tabindex="-1"<?php } ?>
                                    >編集</a>
                                    <?php if ((int)$row['row_id'] > 0) { ?>
                                        <form
                                            class="dial-row-form"
                                            method="post"
                                            action="schedule.php"
                                            data-confirm-message="この行を削除しますか？"
                                            data-confirm-button="削除する"
                                            data-confirm-button-class="adm-btn adm-btn-danger"
                                        >
                                            <input type="hidden" name="s" value="<?php print($station_id); ?>">
                                            <input type="hidden" name="season_id" value="<?php print($selected_season_id); ?>">
                                            <input type="hidden" name="action" value="delete_schedule_row">
                                            <input type="hidden" name="row_id" value="<?php print((int)$row['row_id']); ?>">
                                            <button class="dial-row-btn <?php print(app_button_classes('danger', 'sm')); ?>" type="submit">削除</button>
                                        </form>
                                    <?php } else { ?>
                                        <button class="dial-row-btn <?php print(app_button_classes('danger', 'sm')); ?>" type="button" disabled>削除</button>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
                </div>
            </section>

            <div class="dial-footer-row mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a class="dial-back-btn <?php print(app_button_classes('secondary')); ?>" href="master.php?s=<?php print($station_id); ?>">戻る</a>
                <?php if ($selected_season_id > 0) { ?>
                <a class="dial-register-btn <?php print(app_button_classes('primary')); ?>" href="schedule_row_add.php?s=<?php print($station_id); ?>&season_id=<?php print($selected_season_id); ?>">追加</a>
                <?php } else { ?>
                <button class="dial-register-btn <?php print(app_button_classes('primary')); ?>" type="button" disabled>追加</button>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</div>

<div
    class="app-modal <?php print(app_modal_root_classes()); ?>"
    id="createDialModal"
    tabindex="-1"
    role="dialog"
    aria-modal="true"
    aria-labelledby="createDialTitle"
    aria-hidden="true"
    data-show-on-load="<?php print($show_create_panel ? '1' : '0'); ?>"
    hidden
>
    <div class="app-modal-dialog <?php print(app_modal_dialog_classes('md')); ?>">
        <div class="app-modal-card <?php print(app_modal_card_classes()); ?>">
            <div class="app-modal-header <?php print(app_modal_header_classes()); ?>">
                <div>
                    <h2 class="app-modal-title text-2xl font-bold text-slate-950" id="createDialTitle">ダイヤを追加</h2>
                    <p class="app-modal-description mb-0 mt-2 text-sm text-slate-500">新しいダイヤコードを作成し、必要に応じて既存ダイヤを複製します。</p>
                </div>
                <button class="app-modal-close <?php print(app_button_classes('ghost', 'sm', 'rounded-full px-3')); ?>" type="button" data-close-create aria-label="閉じる">×</button>
            </div>
            <form class="dial-create-form <?php print(app_modal_body_classes('pt-0')); ?>" method="post" action="schedule.php">
            <input type="hidden" name="s" value="<?php print($station_id); ?>">
            <input type="hidden" name="d" value="<?php print(h($selected_dial)); ?>">
            <input type="hidden" name="view" value="<?php print(h($current_view)); ?>">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="show_create" value="1">

            <div class="<?php print(app_field_classes()); ?>">
                <label class="<?php print(app_label_classes()); ?>" for="newDial">新しいダイヤコード</label>
                <input
                    id="newDial"
                    class="<?php print(app_input_classes('font-mono')); ?>"
                    name="new_dial"
                    type="text"
                    maxlength="64"
                    required
                    placeholder="例: summer_2026"
                    value="<?php print(h($new_dial_value)); ?>"
                >
            </div>

            <div class="<?php print(app_field_classes()); ?>">
                <label class="<?php print(app_label_classes()); ?>" for="copyFrom">コピー元</label>
                <select id="copyFrom" class="<?php print(app_select_classes('font-mono')); ?>" name="copy_from">
                    <option value="">空のダイヤを作成</option>
                    <?php foreach ($dial_rows as $dial_row) { ?>
                        <?php $dial_code = (string)$dial_row['dial_code']; ?>
                        <option value="<?php print(h($dial_code)); ?>" <?php if ($dial_code === $copy_from_value) { print('selected'); } ?>>
                            <?php print(h($dial_code)); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

                <p class="dial-create-help mt-3 <?php print(app_help_classes()); ?>">コピー元を選択しない場合は、09:00、12:00、15:00 の初期行を作成します。</p>

                <div class="app-modal-footer app-modal-footer-between <?php print(app_modal_footer_classes(true, 'border-0 px-0 pb-0')); ?>">
                    <button class="adm-btn adm-btn-soft <?php print(app_button_classes('soft')); ?>" type="button" data-close-create>キャンセル</button>
                    <button class="adm-btn adm-btn-pink <?php print(app_button_classes('primary')); ?>" type="submit">追加する</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php render_app_scripts(array('js/dial-admin.js')); ?>
</body>
</html>
