<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/admin_bootstrap.php';

require_login($db);
$stations = get_active_stations(get_stations($db));
$requested_station_id = isset($_REQUEST['s']) ? (int)$_REQUEST['s'] : 0;
$station_id = resolve_station_id($stations, $requested_station_id);
$station_name = get_station_name($stations, $station_id);
$requested_dial = trim((string)($_REQUEST['d'] ?? ''));
$show_modal = isset($_REQUEST['create']) && (string)($_REQUEST['create'] ?? '') === '1';

function get_season_rows(PDO $db): array
{
    $stt = $db->prepare(
        'SELECT season_id AS id, name, start_date, end_date
         FROM season
         ORDER BY
             CASE
                 WHEN NULLIF(CAST(start_date AS CHAR(10)), \'0000-00-00\') IS NULL THEN 1
                 ELSE 0
             END,
             NULLIF(CAST(start_date AS CHAR(10)), \'0000-00-00\') ASC,
             season_id ASC'
    );
    $stt->execute();
    return $stt->fetchAll(PDO::FETCH_ASSOC);
}

function normalize_season_name(string $raw): string
{
    $value = trim($raw);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, 255, 'UTF-8');
    }
    return substr($value, 0, 255);
}

function season_name_exists(PDO $db, string $name, int $exclude_id = 0): bool
{
    $sql = 'SELECT 1 FROM season WHERE name = :name';
    if ($exclude_id > 0) {
        $sql .= ' AND season_id <> :exclude_id';
    }
    $sql .= ' LIMIT 1';

    $stt = $db->prepare($sql);
    $stt->bindValue(':name', $name);
    if ($exclude_id > 0) {
        $stt->bindValue(':exclude_id', $exclude_id, PDO::PARAM_INT);
    }
    $stt->execute();
    return (bool)$stt->fetch(PDO::FETCH_ASSOC);
}

function season_period_overlaps(PDO $db, string $start_date, string $end_date, int $exclude_id = 0): bool
{
    $sql = 'SELECT 1
            FROM season
            WHERE start_date <= :end_date
              AND end_date >= :start_date';
    if ($exclude_id > 0) {
        $sql .= ' AND season_id <> :exclude_id';
    }
    $sql .= ' LIMIT 1';

    $stt = $db->prepare($sql);
    $stt->bindValue(':start_date', $start_date);
    $stt->bindValue(':end_date', $end_date);
    if ($exclude_id > 0) {
        $stt->bindValue(':exclude_id', $exclude_id, PDO::PARAM_INT);
    }
    $stt->execute();
    return (bool)$stt->fetch(PDO::FETCH_ASSOC);
}

function season_row_exists(PDO $db, int $row_id): bool
{
    $stt = $db->prepare('SELECT 1 FROM season WHERE season_id = :id LIMIT 1');
    $stt->bindValue(':id', $row_id, PDO::PARAM_INT);
    $stt->execute();
    return (bool)$stt->fetch(PDO::FETCH_ASSOC);
}

function season_format_date(?string $value): string
{
    $raw = trim((string)$value);
    if ($raw === '' || $raw === '0000-00-00') {
        return '';
    }
    return $raw;
}

$status_type = '';
$status_message = '';
$name_value = '';
$start_date_value = '';
$end_date_value = '';
$modal_mode = 'create';
$editing_id = 0;
$modal_error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $station_id = resolve_station_id($stations, (int)($_POST['s'] ?? $station_id));
    $requested_dial = trim((string)($_POST['d'] ?? $requested_dial));
    $action = trim((string)($_POST['action'] ?? ''));

    try {
        if ($action === 'create' || $action === 'update') {
            $show_modal = true;
            $modal_mode = $action === 'update' ? 'edit' : 'create';
            $editing_id = (int)($_POST['row_id'] ?? 0);
            $name_value = normalize_season_name((string)($_POST['name'] ?? ''));
            $start_date_value = trim((string)($_POST['start_date'] ?? ''));
            $end_date_value = trim((string)($_POST['end_date'] ?? ''));

            if ($name_value === '') {
                throw new RuntimeException('ダイヤ期間名を入力してください。');
            }
            if ($start_date_value === '' || $end_date_value === '') {
                throw new RuntimeException('開始日と終了日を入力してください。');
            }
            if ($start_date_value > $end_date_value) {
                throw new RuntimeException('開始日は終了日以前の日付を指定してください。');
            }

            if ($action === 'create') {
                if (season_name_exists($db, $name_value)) {
                    throw new RuntimeException('そのダイヤ期間名は既に存在します。');
                }
                if (season_period_overlaps($db, $start_date_value, $end_date_value)) {
                    throw new RuntimeException('ダイヤ期間は重複登録できません。');
                }

                $stt = $db->prepare(
                    'INSERT INTO season (name, start_date, end_date)
                     VALUES (:name, :start_date, :end_date)'
                );
                $stt->bindValue(':name', $name_value);
                $stt->bindValue(':start_date', $start_date_value);
                $stt->bindValue(':end_date', $end_date_value);
                $stt->execute();

                $show_modal = false;
                $name_value = '';
                $start_date_value = '';
                $end_date_value = '';
                $status_type = 'success';
                $status_message = 'ダイヤ期間を追加しました。';
            } else {
                if ($editing_id <= 0 || !season_row_exists($db, $editing_id)) {
                    throw new RuntimeException('選択したダイヤ期間が見つかりません。');
                }
                if (season_name_exists($db, $name_value, $editing_id)) {
                    throw new RuntimeException('そのダイヤ期間名は既に存在します。');
                }
                if (season_period_overlaps($db, $start_date_value, $end_date_value, $editing_id)) {
                    throw new RuntimeException('ダイヤ期間は重複登録できません。');
                }

                $stt = $db->prepare(
                    'UPDATE season
                     SET name = :name, start_date = :start_date, end_date = :end_date
                     WHERE season_id = :id'
                );
                $stt->bindValue(':name', $name_value);
                $stt->bindValue(':start_date', $start_date_value);
                $stt->bindValue(':end_date', $end_date_value);
                $stt->bindValue(':id', $editing_id, PDO::PARAM_INT);
                $stt->execute();

                $show_modal = false;
                $name_value = '';
                $start_date_value = '';
                $end_date_value = '';
                $modal_mode = 'create';
                $editing_id = 0;
                $status_type = 'success';
                $status_message = 'ダイヤ期間を更新しました。';
            }
        } elseif ($action === 'delete') {
            $row_id = (int)($_POST['row_id'] ?? 0);
            if ($row_id <= 0 || !season_row_exists($db, $row_id)) {
                throw new RuntimeException('選択したダイヤ期間が見つかりません。');
            }

            $stt = $db->prepare('DELETE FROM season WHERE season_id = :id');
            $stt->bindValue(':id', $row_id, PDO::PARAM_INT);
            $stt->execute();

            $status_type = 'success';
            $status_message = 'ダイヤ期間を削除しました。';
        }
    } catch (Throwable $e) {
        $status_type = 'error';
        $status_message = $e->getMessage();
        if ($show_modal) {
            $modal_error_message = $status_message;
        }
    }
}

$rows = get_season_rows($db);
$back_href = 'schedule.php?s=' . $station_id;
if ($requested_dial !== '') {
    $back_href .= '&d=' . urlencode($requested_dial);
}
?>
<!doctype html>
<html lang="ja">
<head>
    <?php render_app_head('ダイヤ期間'); ?>
</head>
<body class="<?php print(app_body_classes()); ?>">
<div class="adm-page dial-page">
    <div class="<?php print(app_page_shell_classes()); ?>">
        <?php
        $shared_header_station_name = $station_name;
        $shared_header_page_title = 'ダイヤ期間';
        $shared_header_station_id = $station_id;
        require __DIR__ . '/header.php';
        ?>

        <?php if ($status_message !== '' && !($status_type === 'error' && $show_modal)) { ?>
            <div class="adm-alert <?php print(h($status_type)); ?> <?php print(app_alert_classes($status_type === 'error' ? 'error' : 'success')); ?>"><?php print(h($status_message)); ?></div>
        <?php } ?>

        <div class="season-toolbar mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="season-toolbar-copy space-y-2">
                <div class="<?php print(app_badge_classes('neutral')); ?>">Season Library</div>
                <h1 class="season-page-title text-3xl font-bold tracking-[0.01em] text-slate-950">ダイヤ期間一覧</h1>
                <p class="season-page-note text-sm leading-7 text-slate-500">ダイヤ期間名と開始日・終了日を管理します。</p>
            </div>
        </div>

        <section class="dial-board season-board <?php print(app_panel_classes()); ?>">
            <div class="<?php print(app_table_frame_classes('overflow-x-auto')); ?>">
            <table class="dial-board-table season-table <?php print(app_table_classes()); ?>">
                <thead>
                <tr>
                    <th class="season-col-name bg-slate-950/95 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">ダイヤ期間</th>
                    <th class="season-col-date bg-slate-950/95 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">開始日</th>
                    <th class="season-col-date bg-slate-950/95 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">終了日</th>
                    <th class="season-col-action bg-slate-950/95 px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">操作</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($rows) === 0) { ?>
                    <tr>
                        <td class="season-empty border-b border-slate-200/70 px-4 py-10 text-center text-sm text-slate-500" colspan="4">ダイヤ期間はまだ登録されていません。</td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($rows as $row) { ?>
                        <?php $row_id = (int)($row['id'] ?? 0); ?>
                        <?php $row_name = (string)($row['name'] ?? ''); ?>
                        <?php $row_start_date = season_format_date((string)($row['start_date'] ?? '')); ?>
                        <?php $row_end_date = season_format_date((string)($row['end_date'] ?? '')); ?>
                        <tr class="odd:bg-white even:bg-slate-50/60">
                            <td class="dial-manage-name season-name-cell border-b border-slate-200/70 px-4 py-4 font-semibold text-slate-800"><?php print(h($row_name)); ?></td>
                            <td class="dial-manage-name season-date-cell border-b border-slate-200/70 px-4 py-4 text-slate-500"><?php print(h($row_start_date)); ?></td>
                            <td class="dial-manage-name season-date-cell border-b border-slate-200/70 px-4 py-4 text-slate-500"><?php print(h($row_end_date)); ?></td>
                            <td class="dial-cell-actions season-action-cell border-b border-slate-200/70 px-4 py-4">
                                <div class="dial-row-actions season-row-actions flex flex-wrap justify-end gap-2">
                                    <button
                                        class="dial-row-btn <?php print(app_button_classes('secondary', 'sm')); ?>"
                                        type="button"
                                        data-open-edit
                                        data-row-id="<?php print($row_id); ?>"
                                        data-row-name="<?php print(h($row_name)); ?>"
                                        data-row-start-date="<?php print(h($row_start_date)); ?>"
                                        data-row-end-date="<?php print(h($row_end_date)); ?>"
                                    >編集</button>
                                    <form
                                        class="dial-row-form"
                                        method="post"
                                        action="season.php"
                                        data-confirm-message="このダイヤ期間を削除しますか？"
                                        data-confirm-button="削除する"
                                        data-confirm-button-class="adm-btn adm-btn-danger"
                                    >
                                        <input type="hidden" name="s" value="<?php print($station_id); ?>">
                                        <input type="hidden" name="d" value="<?php print(h($requested_dial)); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="row_id" value="<?php print($row_id); ?>">
                                        <button class="dial-row-btn <?php print(app_button_classes('danger', 'sm')); ?>" type="submit">削除</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
                </tbody>
            </table>
            </div>
        </section>

        <div class="dial-footer-row season-footer mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a class="dial-back-btn <?php print(app_button_classes('secondary')); ?>" href="<?php print(h($back_href)); ?>">戻る</a>
            <a class="dial-register-btn <?php print(app_button_classes('primary')); ?>" href="season.php?s=<?php print($station_id); ?>&d=<?php print(urlencode($requested_dial)); ?>&create=1" data-open-create>追加</a>
        </div>
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
    data-create-title="ダイヤ期間を追加"
    data-edit-title="ダイヤ期間を編集"
    data-create-submit="追加する"
    data-edit-submit="更新する"
    data-clear-error-on-leave="1"
    data-show-on-load="<?php print($show_modal ? '1' : '0'); ?>"
    hidden
>
    <div class="app-modal-dialog <?php print(app_modal_dialog_classes('md')); ?>">
        <div class="app-modal-card <?php print(app_modal_card_classes()); ?>">
            <div class="app-modal-header <?php print(app_modal_header_classes()); ?>">
                <div>
                    <h2 class="app-modal-title text-2xl font-bold text-slate-950" id="createDialTitle"><?php print($modal_mode === 'edit' ? 'ダイヤ期間を編集' : 'ダイヤ期間を追加'); ?></h2>
                    <p class="app-modal-description mb-0 mt-2 text-sm text-slate-500">名称と開始日・終了日を登録します。</p>
                </div>
                <button class="app-modal-close <?php print(app_button_classes('ghost', 'sm', 'rounded-full px-3')); ?>" type="button" data-close-create aria-label="閉じる">×</button>
            </div>
            <form class="dial-create-form <?php print(app_modal_body_classes('pt-0')); ?>" method="post" action="season.php">
            <input type="hidden" name="s" value="<?php print($station_id); ?>">
            <input type="hidden" name="d" value="<?php print(h($requested_dial)); ?>">
            <input type="hidden" id="scheduleNameModalAction" name="action" value="<?php print($modal_mode === 'edit' ? 'update' : 'create'); ?>">
            <input type="hidden" id="scheduleNameRowId" name="row_id" value="<?php print($editing_id); ?>">
            <input type="hidden" name="show_create" value="1">

            <?php if ($modal_error_message !== '') { ?>
                <div class="adm-alert error <?php print(app_alert_classes('error')); ?>" data-modal-error><?php print(h($modal_error_message)); ?></div>
            <?php } ?>

            <div class="<?php print(app_field_classes()); ?>">
                <label class="<?php print(app_label_classes()); ?>" for="newDial">ダイヤ期間名</label>
                <input
                    id="newDial"
                    class="<?php print(app_input_classes()); ?>"
                    name="name"
                    type="text"
                    maxlength="255"
                    required
                    value="<?php print(h($name_value)); ?>"
                >
                <p class="dial-create-help <?php print(app_help_classes()); ?>">例: 春ダイヤ、夏ダイヤ、年末年始ダイヤ</p>
            </div>

            <div class="season-date-fields grid gap-4 sm:grid-cols-2">
                <div class="<?php print(app_field_classes()); ?>">
                    <label class="<?php print(app_label_classes()); ?>" for="seasonStartDate">開始日</label>
                    <input
                        id="seasonStartDate"
                        class="<?php print(app_input_classes()); ?>"
                        name="start_date"
                        type="date"
                        required
                        value="<?php print(h($start_date_value)); ?>"
                    >
                </div>

                <div class="<?php print(app_field_classes()); ?>">
                    <label class="<?php print(app_label_classes()); ?>" for="seasonEndDate">終了日</label>
                    <input
                        id="seasonEndDate"
                        class="<?php print(app_input_classes()); ?>"
                        name="end_date"
                        type="date"
                        required
                        value="<?php print(h($end_date_value)); ?>"
                    >
                </div>
            </div>

                <div class="app-modal-footer app-modal-footer-between <?php print(app_modal_footer_classes(true, 'border-0 px-0 pb-0')); ?>">
                    <button class="adm-btn adm-btn-soft <?php print(app_button_classes('soft')); ?>" type="button" data-close-create>キャンセル</button>
                    <button class="adm-btn adm-btn-pink <?php print(app_button_classes('primary')); ?>" type="submit" data-modal-submit><?php print($modal_mode === 'edit' ? '更新する' : '追加する'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php render_app_scripts(array('js/dial-admin.js')); ?>
</body>
</html>
