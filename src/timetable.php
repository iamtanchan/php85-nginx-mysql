<?php
require_once __DIR__ . '/lib/admin_bootstrap.php';
require_once __DIR__ . '/lib/timetable_sync.php';

/** @var PDO $db */
if (!isset($db) || !($db instanceof PDO)) {
    $db = app_create_database_connection();
}

function getBadgeOptions(PDO $db)
{
    /** @noinspection SqlNoDataSourceInspection */
    $stt = $db->prepare('SELECT badge_id AS id, label FROM badge ORDER BY badge_id');
    $stt->execute();
    $rows = $stt->fetchAll(PDO::FETCH_ASSOC);
    $result = array();
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $label = trim((string)$row['label']);
        if ($label !== '') {
            $result[$id] = $label;
        }
    }
    if (count($result) === 0) {
        $result[0] = '未選択';
    }
    return $result;
}

function getSeasonOptions(PDO $db): array
{
    /** @noinspection SqlNoDataSourceInspection */
    $stt = $db->prepare(
        'SELECT
             season_id AS id,
             name,
             NULLIF(CAST(start_date AS CHAR(10)), \'0000-00-00\') AS start_date,
             NULLIF(CAST(end_date AS CHAR(10)), \'0000-00-00\') AS end_date
         FROM season
         ORDER BY
             CASE
                 WHEN NULLIF(CAST(start_date AS CHAR(10)), \'0000-00-00\') IS NULL THEN 1
                 ELSE 0
             END ASC,
             NULLIF(CAST(start_date AS CHAR(10)), \'0000-00-00\') ASC,
             season_id ASC'
    );
    $stt->execute();

    $season_options = array();
    foreach ($stt->fetchAll(PDO::FETCH_ASSOC) as $season_row) {
        $season_name = trim((string)$season_row['name']);
        if ($season_name === '') {
            continue;
        }
        $season_options[] = array(
            'id' => (int)$season_row['id'],
            'name' => $season_name,
            'start_date' => trim((string)($season_row['start_date'] ?? '')),
            'end_date' => trim((string)($season_row['end_date'] ?? '')),
        );
    }

    return $season_options;
}

function getSchedulePreviewRows(PDO $db, int $station_id, int $season_id): array
{
    if ($station_id <= 0 || $season_id <= 0) {
        return array();
    }

    /** @noinspection SqlNoDataSourceInspection */
    $stt = $db->prepare(
        'SELECT schedule_id AS id, departure_time AS time, ship_id, destination_id
         FROM schedule
         WHERE station_id = :station_id AND season_id = :season_id
         ORDER BY priority ASC, departure_time ASC, schedule_id ASC'
    );
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':season_id', $season_id, PDO::PARAM_INT);
    $stt->execute();

    $rows = array();
    foreach ($stt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = array(
            'id' => (int)($row['id'] ?? 0),
            'time' => (string)($row['time'] ?? ''),
            'ship_id' => (int)($row['ship_id'] ?? 0),
            'destination_id' => (int)($row['destination_id'] ?? 0),
            'badge_id' => 0,
            'ontime' => 10,
            'offtime' => 5,
        );
    }

    return $rows;
}

function sortTimetableRowsByDepartureTime(array &$rows): void
{
    usort($rows, function ($left, $right) {
        $left_time = trim((string)($left['time'] ?? ''));
        $right_time = trim((string)($right['time'] ?? ''));

        if ($left_time === $right_time) {
            return (int)($left['id'] ?? 0) <=> (int)($right['id'] ?? 0);
        }

        if ($left_time === '') {
            return 1;
        }
        if ($right_time === '') {
            return -1;
        }

        return strcmp($left_time, $right_time);
    });
}

$station_id = isset($_REQUEST['s']) ? (int)$_REQUEST['s'] : 1;
$has_requested_season = array_key_exists('season_id', $_REQUEST);
$requested_season_id = $has_requested_season ? (int)$_REQUEST['season_id'] : 0;
$dial_code = isset($_REQUEST['d']) ? trim((string)$_REQUEST['d']) : '';
$requested_action = trim((string)($_POST['action'] ?? ''));
$status_type = '';
$status_message = '';
$is_preview_mode = false;

if (isset($_SESSION['timetable_flash']) && is_array($_SESSION['timetable_flash'])) {
    $status_type = trim((string)($_SESSION['timetable_flash']['type'] ?? ''));
    $status_message = trim((string)($_SESSION['timetable_flash']['message'] ?? ''));
    unset($_SESSION['timetable_flash']);
}

try {
    require_login($db);
    $day_bounds = current_day_bounds();

    $stations = get_stations($db);

    $station_name = '';
    foreach ($stations as $station) {
        if ((int)$station['id'] === $station_id) {
            $station_name = trim((string)$station['name']);
            break;
        }
    }

    if ($station_name === '' && $station_id > 0) {
        /** @noinspection SqlNoDataSourceInspection */
        $stt = $db->prepare('SELECT 1 FROM timetable WHERE station_id = :station_id LIMIT 1');
        $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
        $stt->execute();
        $has_station_rows = (bool)$stt->fetch(PDO::FETCH_ASSOC);
        if ($has_station_rows) {
            $station_name = 'Station ' . $station_id;
            array_unshift($stations, array(
                'id' => $station_id,
                'name' => $station_name,
                'name_e' => '',
            ));
        }
    }

    if ($station_name === '' && count($stations) > 0) {
        $station_id = (int)$stations[0]['id'];
        $station_name = trim((string)$stations[0]['name']);
    }
    if ($station_name === '') {
        $station_name = $station_id > 0 ? ('Station ' . $station_id) : 'Station';
    }

    /** @noinspection SqlNoDataSourceInspection */
    $stt = $db->prepare('SELECT ship_id AS id, name, name_e FROM ship ORDER BY ship_id');
    $stt->execute();
    $ships = $stt->fetchAll(PDO::FETCH_ASSOC);

    /** @noinspection SqlNoDataSourceInspection */
    $stt = $db->prepare('SELECT destination_id AS id, name, name_e FROM destination ORDER BY destination_id');
    $stt->execute();
    $destinations = $stt->fetchAll(PDO::FETCH_ASSOC);

    $season_options = getSeasonOptions($db);

    $selected_season_id = 0;
    if ($requested_season_id > 0) {
        foreach ($season_options as $season_option) {
            if ((int)$season_option['id'] === $requested_season_id) {
                $selected_season_id = $requested_season_id;
                $dial_code = (string)$season_option['name'];
                break;
            }
        }
    }

    if ($selected_season_id === 0 && $dial_code !== '') {
        foreach ($season_options as $season_option) {
            if ((string)$season_option['name'] === $dial_code) {
                $selected_season_id = (int)$season_option['id'];
                $dial_code = (string)$season_option['name'];
                break;
            }
        }
    }

    $is_preview_mode = ($has_requested_season || $dial_code !== '') && $selected_season_id > 0;

    if ($requested_action === 'save_from_schedule') {
        if ($selected_season_id <= 0) {
            $status_type = 'error';
            $status_message = 'ダイヤ期間を選択してください。';
        } else {
            try {
                $registered_count = sync_timetable_from_season($db, $station_id, $selected_season_id);
                if ($registered_count > 0) {
                    $status_type = 'success';
                    $status_message = $dial_code . ' のデータを時刻表へ登録しました。件数: ' . $registered_count;
                } else {
                    $status_type = 'success';
                    $status_message = $dial_code . ' に登録対象のダイヤデータがないため、時刻表を空で保存しました。';
                }
            } catch (Throwable $e) {
                $status_type = 'error';
                $status_message = '登録に失敗しました: ' . $e->getMessage();
            }
        }

        $_SESSION['timetable_flash'] = array(
            'type' => $status_type,
            'message' => $status_message,
        );
        redirect_with_params('timetable.php', array(
            's' => $station_id,
            'season_id' => $selected_season_id,
        ));
    }

    if ($is_preview_mode) {
        $rows = getSchedulePreviewRows($db, $station_id, $selected_season_id);
    } else {
        /** @noinspection SqlNoDataSourceInspection */
        $stt = $db->prepare(
            'SELECT timetable_id AS id, departure_time AS time, ship_id, destination_id, badge_id, ontime, offtime
             FROM timetable
             WHERE station_id = :station_id
               AND created_at >= :day_start
               AND created_at < :day_end
             ORDER BY departure_time ASC'
        );
        $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
        $stt->bindValue(':day_start', $day_bounds['start']);
        $stt->bindValue(':day_end', $day_bounds['end']);
        $stt->execute();
        $rows = $stt->fetchAll(PDO::FETCH_ASSOC);
    }
    sortTimetableRowsByDepartureTime($rows);
    $badge_options = getBadgeOptions($db);
} catch (PDOException $e) {
    die('error:' . $e->getMessage());
}

$boarding_options = array(15, 10, 5);
$blink_options = array(10, 9, 8, 7, 6, 5, 4, 3, 2, 1, 0);
?>
<!doctype html>
<html lang="ja">

<!--suppress HtmlRequiredTitleElement -->
<head>
    <?php render_app_head('時刻表管理画面', array('jquery' => true)); ?>
</head>

<body class="<?php print(app_body_classes()); ?>">
    <div id="station" hidden><?php print($station_id); ?></div>

    <div class="tt-page">
        <div class="<?php print(app_page_shell_classes()); ?>">
            <?php
            $shared_header_station_name = $station_name;
            $shared_header_page_title = '時刻表管理';
            $shared_header_station_id = $station_id;
            require __DIR__ . '/header.php';
            ?>
            <div id="timetableStatus">
                <?php if ($status_message !== '') { ?>
                    <div class="adm-alert <?php print(h($status_type)); ?> <?php print(app_alert_classes($status_type === 'error' ? 'error' : 'success')); ?>" role="alert"><?php print(h($status_message)); ?></div>
                <?php } ?>
            </div>
            <div class="tt-time-row mb-4 <?php print(app_panel_classes('flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between')); ?>">
                <div class="<?php print(app_field_classes('max-w-md')); ?>">
                <label class="tt-time-label <?php print(app_label_classes()); ?>" for="dialSelect">ダイヤ期間</label>
                <div class="flex flex-wrap items-center gap-2">
                    <select id="dialSelect" class="field-select <?php print(app_select_classes()); ?>">
                        <option value="" <?php if ($selected_season_id <= 0) { print('selected'); } ?>></option>
                        <?php foreach ($season_options as $season_option) { ?>
                            <option
                                value="<?php print((int)$season_option['id']); ?>"
                                <?php if ((int)$season_option['id'] === $selected_season_id) {
                                    print('selected');
                                } ?>
                            >
                                <?php print(h($season_option['name'])); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="tt-time-row flex items-center gap-3 rounded-full bg-slate-100 px-4 py-3">
                        <span class="tt-time-label text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">現在時刻</span>
                        <div id="timenow" class="tt-time-box text-2xl font-bold tracking-[0.04em] text-slate-950">--:--</div>
                    </div>
                    <div class="tt-time-row">
                        <button id="btnAddRow" class="adm-btn adm-btn-pink <?php print(app_button_classes('primary')); ?>" type="button">新規登録</button>
                    </div>
                </div>
            </div>

            <div class="tt-table-wrap <?php print(app_panel_classes()); ?>">
                <div class="<?php print(app_table_frame_classes('overflow-x-auto')); ?>">
                    <table class="tt-table <?php print(app_table_classes()); ?>">
                        <thead>
                            <tr>
                                <th class="col-action bg-slate-950/95 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">操作</th>
                                <th class="col-time bg-slate-950/95 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">出発時刻</th>
                                <th class="col-ship bg-slate-950/95 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">艇名</th>
                                <th class="col-destination bg-slate-950/95 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">行先</th>
                                <th class="col-badge bg-slate-950/95 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">注意喚起バッヂ</th>
                                <th class="col-boarding bg-slate-950/95 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">乗船案内</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($rows) === 0) { ?>
                                <tr class="tt-row tt-row-empty bg-white" data-row-id="0">
                                    <td class="tt-actions border-b border-slate-200/70 px-4 py-4" data-label="操作">
                                        <button class="row-btn btn-delete <?php print(app_button_classes('danger', 'sm')); ?>" type="button" disabled>削除</button>
                                    </td>
                                    <td class="border-b border-slate-200/70 px-4 py-4" data-label="出発時刻"><input class="field-time <?php print(app_input_classes()); ?>" type="time" step="60" value="" aria-label="出発時刻" disabled></td>
                                    <td class="border-b border-slate-200/70 px-4 py-4" data-label="艇名"><select class="field-select field-ship <?php print(app_select_classes()); ?>" aria-label="艇名" disabled>
                                            <option> </option>
                                        </select></td>
                                    <td class="border-b border-slate-200/70 px-4 py-4" data-label="行先"><select class="field-select field-destination <?php print(app_select_classes()); ?>" aria-label="行先" disabled>
                                            <option> </option>
                                        </select></td>
                                    <td class="border-b border-slate-200/70 px-4 py-4" data-label="注意喚起バッヂ"><select class="field-select field-badge <?php print(app_select_classes()); ?>" aria-label="注意喚起バッヂ" disabled>
                                            <option> </option>
                                        </select></td>
                                    <td class="border-b border-slate-200/70 px-4 py-4" data-label="乗船案内">
                                        <div class="boarding-group mb-3 space-y-2">
                                            <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">乗船</span>
                                            <select class="field-select field-boarding <?php print(app_select_classes()); ?>" aria-label="乗船案内 乗船" disabled>
                                                <option> </option>
                                            </select>
                                        </div>
                                        <div class="boarding-group space-y-2">
                                            <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">点灯</span>
                                            <select class="field-select field-blink <?php print(app_select_classes()); ?>" aria-label="乗船案内 点灯" disabled>
                                                <option> </option>
                                            </select>
                                        </div>
                                    </td>
                                </tr>
                            <?php } else { ?>
                                <?php foreach ($rows as $row) { ?>
                                    <?php
                                    $row_id = (int)$row['id'];
                                    $time = substr((string)$row['time'], 0, 5);
                                    $ship_id = (int)$row['ship_id'];
                                    $destination_id = (int)$row['destination_id'];
                                    $badge_id = (int)$row['badge_id'];
                                    $boarding_min = (int)$row['ontime'];
                                    $blink_min = (int)$row['offtime'];
                                    if (!in_array($boarding_min, $boarding_options, true)) {
                                        $boarding_min = 10;
                                    }
                                    if ($blink_min < 0 || $blink_min > 10) {
                                        $blink_min = 0;
                                    }
                                    ?>
                                    <tr class="tt-row odd:bg-white even:bg-slate-50/60" data-row-id="<?php print($row_id); ?>">
                                        <td class="tt-actions border-b border-slate-200/70 px-4 py-4" data-label="操作">
                                            <button class="row-btn btn-delete <?php print(app_button_classes('danger', 'sm')); ?>" type="button">削除</button>
                                        </td>
                                        <td class="border-b border-slate-200/70 px-4 py-4" data-label="出発時刻">
                                            <input class="field-time <?php print(app_input_classes()); ?>" type="time" step="60" value="<?php print(h($time)); ?>" aria-label="出発時刻">
                                        </td>
                                        <td class="border-b border-slate-200/70 px-4 py-4" data-label="艇名">
                                            <select class="field-select field-ship <?php print(app_select_classes()); ?>" aria-label="艇名">
                                                <option value="0">未選択</option>
                                                <?php foreach ($ships as $ship) { ?>
                                                    <option value="<?php print((int)$ship['id']); ?>" <?php if ((int)$ship['id'] === $ship_id) {
                                                                                                            print('selected');
                                                                                                        } ?>>
                                                        <?php print(h($ship['name'])); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <td class="border-b border-slate-200/70 px-4 py-4" data-label="行先">
                                            <select class="field-select field-destination <?php print(app_select_classes()); ?>" aria-label="行先">
                                                <option value="0">未選択</option>
                                                <?php foreach ($destinations as $destination) { ?>
                                                    <option value="<?php print((int)$destination['id']); ?>" <?php if ((int)$destination['id'] === $destination_id) {
                                                                                                                    print('selected');
                                                                                                                } ?>>
                                                        <?php print(h($destination['name'])); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <td class="border-b border-slate-200/70 px-4 py-4" data-label="注意喚起バッヂ">
                                            <select class="field-select field-badge <?php print(app_select_classes()); ?>" aria-label="注意喚起バッヂ">
                                                <?php foreach ($badge_options as $badge_value => $badge_label) { ?>
                                                    <option value="<?php print((int)$badge_value); ?>" <?php if ((int)$badge_value === $badge_id) {
                                                                                                            print('selected');
                                                                                                        } ?>>
                                                        <?php print(h($badge_label)); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <td class="border-b border-slate-200/70 px-4 py-4" data-label="乗船案内">
                                            <div class="boarding-group mb-3 space-y-2">
                                                <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">乗船</span>
                                                <select class="field-select field-boarding <?php print(app_select_classes()); ?>" aria-label="乗船案内 乗船">
                                                    <?php foreach ($boarding_options as $minute) { ?>
                                                        <option value="<?php print($minute); ?>" <?php if ($minute === $boarding_min) {
                                                                                                        print('selected');
                                                                                                    } ?>>
                                                            乗船 <?php print($minute); ?>分前
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                            <div class="boarding-group space-y-2">
                                                <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">点灯</span>
                                                <select class="field-select field-blink <?php print(app_select_classes()); ?>" aria-label="乗船案内 点灯">
                                                    <?php foreach ($blink_options as $minute) { ?>
                                                        <option value="<?php print($minute); ?>" <?php if ($minute === $blink_min) {
                                                                                                        print('selected');
                                                                                                    } ?>>
                                                            <?php print($minute); ?>分前
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                class="app-modal <?php print(app_modal_root_classes()); ?>"
                id="ttCreateModal"
                tabindex="-1"
                role="dialog"
                aria-modal="true"
                aria-labelledby="ttCreateModalTitle"
                aria-hidden="true"
                hidden
            >
                <div class="app-modal-dialog app-modal-dialog-lg <?php print(app_modal_dialog_classes('lg')); ?>">
                    <div class="app-modal-card timetable-create-modal-card <?php print(app_modal_card_classes()); ?>">
                        <form id="ttCreateForm">
                            <div class="app-modal-header <?php print(app_modal_header_classes()); ?>">
                                <div>
                                    <h2 class="app-modal-title text-2xl font-bold text-slate-950" id="ttCreateModalTitle">時刻表を追加</h2>
                                </div>
                                <button type="button" class="app-modal-close <?php print(app_button_classes('ghost', 'sm', 'rounded-full px-3')); ?>" data-modal-close aria-label="閉じる">×</button>
                            </div>

                            <div class="app-modal-body timetable-create-modal-body <?php print(app_modal_body_classes()); ?>">
                                <div id="ttCreateStatus"></div>

                                <div class="timetable-create-grid grid gap-4 md:grid-cols-2">
                                    <div class="<?php print(app_field_classes()); ?>">
                                        <label class="<?php print(app_label_classes()); ?>" for="ttCreateTime">出発時刻</label>
                                        <input id="ttCreateTime" class="field-time <?php print(app_input_classes()); ?>" type="time" step="60" value="">
                                    </div>

                                    <div class="<?php print(app_field_classes()); ?>">
                                        <label class="<?php print(app_label_classes()); ?>" for="ttCreateBadge">注意喚起バッヂ</label>
                                        <select id="ttCreateBadge" class="field-select field-badge <?php print(app_select_classes()); ?>">
                                            <?php foreach ($badge_options as $badge_option_id => $badge_label) { ?>
                                                <option value="<?php print((int)$badge_option_id); ?>">
                                                    <?php print(h($badge_label)); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>

                                    <div class="<?php print(app_field_classes()); ?>">
                                        <label class="<?php print(app_label_classes()); ?>" for="ttCreateShip">艇名</label>
                                        <select id="ttCreateShip" class="field-select field-ship <?php print(app_select_classes()); ?>">
                                            <option value="0">艇名を選択</option>
                                            <?php foreach ($ships as $ship) { ?>
                                                <option value="<?php print((int)$ship['id']); ?>">
                                                    <?php print(h($ship['name'])); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>

                                    <div class="<?php print(app_field_classes()); ?>">
                                        <label class="<?php print(app_label_classes()); ?>" for="ttCreateDestination">行先</label>
                                        <select id="ttCreateDestination" class="field-select field-destination <?php print(app_select_classes()); ?>">
                                            <option value="0">行先を選択</option>
                                            <?php foreach ($destinations as $destination) { ?>
                                                <option value="<?php print((int)$destination['id']); ?>">
                                                    <?php print(h($destination['name'])); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>

                                    <div class="timetable-create-card timetable-create-card-wide rounded-[26px] border border-slate-200 bg-slate-50 p-5 md:col-span-2">
                                        <div class="timetable-create-card-title text-xl font-bold text-slate-950">乗船案内</div>
                                        <div class="timetable-create-inline-grid mt-4 grid gap-4 md:grid-cols-2">
                                            <div class="<?php print(app_field_classes()); ?>">
                                                <label class="<?php print(app_label_classes()); ?>" for="ttCreateBoarding">乗船</label>
                                                <select id="ttCreateBoarding" class="field-select field-boarding <?php print(app_select_classes()); ?>">
                                                    <?php foreach ($boarding_options as $minute) { ?>
                                                        <option value="<?php print($minute); ?>" <?php if ($minute === 10) { print('selected'); } ?>>
                                                            乗船 <?php print($minute); ?>分前
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>

                                            <div class="<?php print(app_field_classes()); ?>">
                                                <label class="<?php print(app_label_classes()); ?>" for="ttCreateBlink">点灯</label>
                                                <select id="ttCreateBlink" class="field-select field-blink <?php print(app_select_classes()); ?>">
                                                    <?php foreach ($blink_options as $minute) { ?>
                                                        <option value="<?php print($minute); ?>" <?php if ($minute === 5) { print('selected'); } ?>>
                                                            <?php print($minute); ?>分前
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="app-modal-footer app-modal-footer-between <?php print(app_modal_footer_classes(true)); ?>">
                                <button class="adm-btn adm-btn-soft <?php print(app_button_classes('soft')); ?>" type="button" data-modal-close>閉じる</button>
                                <button id="ttCreateSubmit" class="adm-btn adm-btn-pink <?php print(app_button_classes('primary')); ?>" type="submit">追加</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="tt-footer mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
                <form id="saveForm" method="post" action="timetable.php">
                    <input type="hidden" name="s" value="<?php print($station_id); ?>">
                    <input id="saveSeasonId" type="hidden" name="season_id" value="<?php print($selected_season_id); ?>">
                    <button
                        id="saveButton"
                        class="adm-btn adm-btn-soft <?php print(app_button_classes('soft')); ?>"
                        type="submit"
                        name="action"
                        value="save_from_schedule"
                        disabled
                    >保存</button>
                </form>
                <a
                    id="btnDisplayPage"
                    class="adm-btn adm-btn-pink <?php print(app_button_classes('primary')); ?>"
                    href="<?php print('display.php?id=' . $station_id); ?>"
                >表示ページへ反映</a>
            </div>
        </div>
    </div>
    <?php render_app_scripts(array('js/timetable.js?v=2.3.0')); ?>
</body>

</html>
