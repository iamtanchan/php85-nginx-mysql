<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/admin_bootstrap.php';
require_once __DIR__ . '/lib/timetable_sync.php';

require_login($db);
$stations = get_active_stations(get_stations($db));
$requested_station_id = isset($_REQUEST['s']) ? (int)$_REQUEST['s'] : 0;
$station_id = resolve_station_id($stations, $requested_station_id);
$station_name = get_station_name($stations, $station_id);
$requested_season_id = isset($_REQUEST['season_id']) ? (int)$_REQUEST['season_id'] : 0;

function get_season_options(PDO $db): array
{
    $stt = $db->prepare(
        'SELECT
            season_id AS id,
            name,
            CASE
                WHEN NULLIF(CAST(start_date AS CHAR(10)), \'0000-00-00\') IS NULL THEN 1
                ELSE 0
            END AS sort_start_missing,
            NULLIF(CAST(start_date AS CHAR(10)), \'0000-00-00\') AS sort_start_date
         FROM season
         ORDER BY sort_start_missing ASC, sort_start_date ASC, id ASC'
    );
    $stt->execute();
    return $stt->fetchAll(PDO::FETCH_ASSOC);
}

function resolve_selected_season(array $season_rows, int $requested_season_id): array
{
    foreach ($season_rows as $season_row) {
        $season_id = isset($season_row['id']) ? (int)$season_row['id'] : 0;
        if ($requested_season_id > 0 && $season_id === $requested_season_id) {
            return array(
                'id' => $season_id,
                'name' => trim((string)($season_row['name'] ?? '')),
            );
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

function get_ship_rows(PDO $db): array
{
    $stt = $db->prepare('SELECT ship_id AS id, name FROM ship ORDER BY ship_id');
    $stt->execute();
    return $stt->fetchAll(PDO::FETCH_ASSOC);
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

function get_destination_rows(PDO $db): array
{
    $stt = $db->prepare('SELECT destination_id AS id, name FROM destination ORDER BY destination_id');
    $stt->execute();
    return $stt->fetchAll(PDO::FETCH_ASSOC);
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

$status_type = '';
$status_message = '';
$show_create_modal = isset($_REQUEST['create']) && (string)($_REQUEST['create'] ?? '') === '1';
$modal_error_message = '';
$create_departure_time = trim((string)($_POST['departure_time'] ?? ''));
$create_ship_id = isset($_POST['ship_id']) ? (int)$_POST['ship_id'] : 0;
$create_destination_id = isset($_POST['destination_id']) ? (int)$_POST['destination_id'] : 0;

if (isset($_SESSION['schedule_flash']) && is_array($_SESSION['schedule_flash'])) {
    $status_type = trim((string)($_SESSION['schedule_flash']['type'] ?? ''));
    $status_message = trim((string)($_SESSION['schedule_flash']['message'] ?? ''));
    unset($_SESSION['schedule_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $station_id = resolve_station_id($stations, (int)($_POST['s'] ?? $station_id));
    $requested_season_id = isset($_POST['season_id']) ? (int)$_POST['season_id'] : $requested_season_id;
    $action = trim((string)($_POST['action'] ?? ''));

    try {
        if ($action === 'create_schedule_row') {
            $show_create_modal = true;
            if ($requested_season_id <= 0) {
                throw new RuntimeException('ダイヤ期間を選択してください。');
            }
            if ($create_departure_time === '') {
                throw new RuntimeException('出発時刻を入力してください。');
            }
            if (!preg_match('/^\d{2}:\d{2}$/', $create_departure_time)) {
                throw new RuntimeException('出発時刻の形式が不正です。');
            }
            if ($create_ship_id <= 0) {
                throw new RuntimeException('艇名を選択してください。');
            }
            if ($create_destination_id <= 0) {
                throw new RuntimeException('行先を選択してください。');
            }

            $priority_stt = $db->prepare(
                'SELECT COALESCE(MAX(priority), 0) + 1 AS next_priority
                 FROM schedule
                 WHERE station_id = :station_id AND season_id = :season_id'
            );
            $priority_stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
            $priority_stt->bindValue(':season_id', $requested_season_id, PDO::PARAM_INT);
            $priority_stt->execute();
            $next_priority = (int)($priority_stt->fetch(PDO::FETCH_ASSOC)['next_priority'] ?? 1);

            $ins = $db->prepare(
                'INSERT INTO schedule (station_id, season_id, departure_time, ship_id, destination_id, priority)
                 VALUES (:station_id, :season_id, :departure_time, :ship_id, :destination_id, :priority)'
            );
            $ins->bindValue(':station_id', $station_id, PDO::PARAM_INT);
            $ins->bindValue(':season_id', $requested_season_id, PDO::PARAM_INT);
            $ins->bindValue(':departure_time', $create_departure_time . ':00');
            $ins->bindValue(':ship_id', $create_ship_id, PDO::PARAM_INT);
            $ins->bindValue(':destination_id', $create_destination_id, PDO::PARAM_INT);
            $ins->bindValue(':priority', $next_priority, PDO::PARAM_INT);
            $ins->execute();

            sync_timetable_from_season($db, $station_id, $requested_season_id);

            $_SESSION['schedule_flash'] = array(
                'type' => 'success',
                'message' => '行を追加しました。',
            );
            redirect_with_params('schedule.php', array(
                's' => $station_id,
                'season_id' => $requested_season_id,
            ));
        } elseif ($action === 'delete_schedule_row') {
            $row_id = (int)($_POST['row_id'] ?? 0);
            if ($row_id <= 0 || $requested_season_id <= 0) {
                throw new RuntimeException('有効な行を選択してください。');
            }

            $stt = $db->prepare(
                'DELETE FROM schedule
                 WHERE schedule_id = :row_id AND station_id = :station_id AND season_id = :season_id'
            );
            $stt->bindValue(':row_id', $row_id, PDO::PARAM_INT);
            $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
            $stt->bindValue(':season_id', $requested_season_id, PDO::PARAM_INT);
            $stt->execute();

            sync_timetable_from_season($db, $station_id, $requested_season_id);

            $_SESSION['schedule_flash'] = array(
                'type' => 'success',
                'message' => '行を削除しました。',
            );
            redirect_with_params('schedule.php', array(
                's' => $station_id,
                'season_id' => $requested_season_id,
            ));
        }
    } catch (Throwable $e) {
        $status_type = 'error';
        $status_message = $e->getMessage();
        if ($action === 'create_schedule_row') {
            $modal_error_message = $status_message;
        }
    }
}

$season_rows = get_season_options($db);
$selected_season = resolve_selected_season($season_rows, $requested_season_id);
$selected_season_id = isset($selected_season['id']) ? (int)$selected_season['id'] : 0;
$selected_season_name = trim((string)($selected_season['name'] ?? ''));
$ship_rows = get_ship_rows($db);
$destination_rows = get_destination_rows($db);
$ship_name_map = get_ship_name_map($db);
$destination_name_map = get_destination_name_map($db);
$preview_rows = get_schedule_preview_rows($db, $station_id, $selected_season_id, $ship_name_map, $destination_name_map);
$preview_scroll_class = count($preview_rows) > 5 ? ' dial-board-scroll' : '';
$season_manage_href = 'season.php?s=' . $station_id;
if ($selected_season_name !== '') {
    $season_manage_href .= '&d=' . urlencode($selected_season_name);
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

        <?php if ($status_message !== '' && !($status_type === 'error' && $show_create_modal)) { ?>
            <div class="adm-alert <?php print(h($status_type ?: 'success')); ?> <?php print(app_alert_classes(($status_type ?: 'success') === 'error' ? 'error' : 'success')); ?>"><?php print(h($status_message)); ?></div>
        <?php } ?>

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

                <a class="dial-action-btn <?php print(app_button_classes('secondary')); ?>" href="<?php print(h($season_manage_href)); ?>">ダイヤ期間を管理</a>
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
                                            data-ajax-skip="1"
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
                <button class="dial-register-btn <?php print(app_button_classes('primary')); ?>" type="button" data-open-schedule-create>追加</button>
            <?php } else { ?>
                <button class="dial-register-btn <?php print(app_button_classes('primary')); ?>" type="button" disabled>追加</button>
            <?php } ?>
        </div>
    </div>
</div>

<div
    class="app-modal <?php print(app_modal_root_classes()); ?>"
    id="scheduleCreateModal"
    tabindex="-1"
    role="dialog"
    aria-modal="true"
    aria-labelledby="scheduleCreateTitle"
    aria-hidden="true"
    data-show-on-load="<?php print($show_create_modal ? '1' : '0'); ?>"
    hidden
>
    <div class="app-modal-dialog <?php print(app_modal_dialog_classes('md')); ?>">
        <div class="app-modal-card <?php print(app_modal_card_classes()); ?>">
            <div class="app-modal-header <?php print(app_modal_header_classes()); ?>">
                <div>
                    <h2 class="app-modal-title text-2xl font-bold text-slate-950" id="scheduleCreateTitle">スケジュール行を追加</h2>
                    <p class="app-modal-description mb-0 mt-2 text-sm text-slate-500">出発時刻、艇名、行先を登録します。</p>
                </div>
                <button class="app-modal-close <?php print(app_button_classes('ghost', 'sm', 'rounded-full px-3')); ?>" type="button" data-modal-close aria-label="閉じる">×</button>
            </div>
            <form class="<?php print(app_modal_body_classes('pt-0')); ?>" method="post" action="schedule.php" data-ajax-skip="1">
                <input type="hidden" name="s" value="<?php print($station_id); ?>">
                <input type="hidden" name="season_id" value="<?php print($selected_season_id); ?>">
                <input type="hidden" name="action" value="create_schedule_row">

                <?php if ($modal_error_message !== '') { ?>
                    <div class="adm-alert error <?php print(app_alert_classes('error')); ?>"><?php print(h($modal_error_message)); ?></div>
                <?php } ?>

                <div class="space-y-5">
                    <div class="<?php print(app_field_classes()); ?>">
                        <label class="<?php print(app_label_classes()); ?>" for="scheduleCreateDepartureTime">出発時刻</label>
                        <input
                            id="scheduleCreateDepartureTime"
                            class="<?php print(app_input_classes()); ?>"
                            name="departure_time"
                            type="time"
                            required
                            value="<?php print(h($create_departure_time)); ?>"
                        >
                    </div>

                    <div class="<?php print(app_field_classes()); ?>">
                        <label class="<?php print(app_label_classes()); ?>" for="scheduleCreateShipId">艇名</label>
                        <select id="scheduleCreateShipId" class="<?php print(app_select_classes()); ?>" name="ship_id" required>
                            <option value="">選択してください</option>
                            <?php foreach ($ship_rows as $ship_row) { ?>
                                <?php $ship_option_id = (int)($ship_row['id'] ?? 0); ?>
                                <option value="<?php print($ship_option_id); ?>" <?php if ($ship_option_id === $create_ship_id) { print('selected'); } ?>>
                                    <?php print(h((string)($ship_row['name'] ?? ''))); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="<?php print(app_field_classes()); ?>">
                        <label class="<?php print(app_label_classes()); ?>" for="scheduleCreateDestinationId">行先</label>
                        <select id="scheduleCreateDestinationId" class="<?php print(app_select_classes()); ?>" name="destination_id" required>
                            <option value="">選択してください</option>
                            <?php foreach ($destination_rows as $destination_row) { ?>
                                <?php $destination_option_id = (int)($destination_row['id'] ?? 0); ?>
                                <option value="<?php print($destination_option_id); ?>" <?php if ($destination_option_id === $create_destination_id) { print('selected'); } ?>>
                                    <?php print(h((string)($destination_row['name'] ?? ''))); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="app-modal-footer app-modal-footer-between <?php print(app_modal_footer_classes(true, 'border-0 px-0 pb-0 pt-6')); ?>">
                    <button class="adm-btn adm-btn-soft <?php print(app_button_classes('soft')); ?>" type="button" data-modal-close>キャンセル</button>
                    <button class="adm-btn adm-btn-pink <?php print(app_button_classes('primary')); ?>" type="submit">追加する</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php render_app_scripts(array('js/schedule.js?v=1.0.0')); ?>
</body>
</html>
