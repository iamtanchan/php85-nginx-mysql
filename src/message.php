<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/admin_bootstrap.php';

/** @var PDO $db */
if (!isset($db) || !($db instanceof PDO)) {
    $db = app_create_database_connection();
}

$login = require_login($db);
$all_stations = get_stations($db);
$active_stations = get_active_stations($all_stations);
$requested_station_id = isset($_REQUEST['s']) ? (int)$_REQUEST['s'] : 0;
$station_id = resolve_station_id($active_stations, $requested_station_id);

function get_station_label(array $stations, int $station_id): string
{
    foreach ($stations as $station) {
        if ((int)$station['id'] === $station_id) {
            return trim((string)$station['name']);
        }
    }
    return $station_id > 0 ? ('Station ' . $station_id) : '未設定';
}

function normalize_message_text(string $message): string
{
    $message = str_replace(array("\r\n", "\r"), "\n", trim($message));
    if (function_exists('mb_substr')) {
        return mb_substr($message, 0, 100, 'UTF-8');
    }
    return substr($message, 0, 100);
}

function fetch_message_row(PDO $db, int $station_id, int $message_id): ?array
{
    if ($message_id <= 0) {
        return null;
    }

    /** @noinspection SqlNoDataSourceInspection */
    $stt = $db->prepare(
        'SELECT message_id, station_id, message, message_e, sort_order, is_visible, created_at, updated_at
         FROM message
         WHERE station_id = :station_id AND message_id = :message_id
         LIMIT 1'
    );
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':message_id', $message_id, PDO::PARAM_INT);
    $stt->execute();
    $row = $stt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function normalize_message_english_text(string $message): string
{
    $message = str_replace(array("\r\n", "\r"), "\n", trim($message));
    if (function_exists('mb_substr')) {
        return mb_substr($message, 0, 100, 'UTF-8');
    }
    return substr($message, 0, 100);
}

function get_next_sort_order(PDO $db, int $station_id): int
{
    /** @noinspection SqlNoDataSourceInspection */
    $stt = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM message WHERE station_id = :station_id');
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->execute();
    return (int)$stt->fetchColumn();
}

function insert_message(PDO $db, int $station_id, string $message, string $message_e, bool $is_visible): int
{
    /** @noinspection SqlNoDataSourceInspection */
    $stt = $db->prepare(
        'INSERT INTO message (station_id, message, message_e, sort_order, is_visible)
         VALUES (:station_id, :message, :message_e, :sort_order, :is_visible)'
    );
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':message', $message);
    $stt->bindValue(':message_e', $message_e !== '' ? $message_e : null, $message_e !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stt->bindValue(':sort_order', get_next_sort_order($db, $station_id), PDO::PARAM_INT);
    $stt->bindValue(':is_visible', $is_visible ? 1 : 0, PDO::PARAM_INT);
    $stt->execute();
    return (int)$db->lastInsertId();
}

function update_message(PDO $db, int $station_id, int $message_id, string $message, string $message_e, bool $is_visible): void
{
    /** @noinspection SqlNoDataSourceInspection */
    $stt = $db->prepare(
        'UPDATE message
         SET message = :message,
             message_e = :message_e,
             is_visible = :is_visible
         WHERE station_id = :station_id AND message_id = :message_id'
    );
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':message_id', $message_id, PDO::PARAM_INT);
    $stt->bindValue(':message', $message);
    $stt->bindValue(':message_e', $message_e !== '' ? $message_e : null, $message_e !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stt->bindValue(':is_visible', $is_visible ? 1 : 0, PDO::PARAM_INT);
    $stt->execute();
}

function delete_message(PDO $db, int $station_id, int $message_id): void
{
    /** @noinspection SqlNoDataSourceInspection */
    $stt = $db->prepare('DELETE FROM message WHERE station_id = :station_id AND message_id = :message_id');
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':message_id', $message_id, PDO::PARAM_INT);
    $stt->execute();
}

function toggle_message_visibility(PDO $db, int $station_id, int $message_id): void
{
    /** @noinspection SqlNoDataSourceInspection */
    $stt = $db->prepare(
        'UPDATE message
         SET is_visible = CASE WHEN is_visible = 1 THEN 0 ELSE 1 END
         WHERE station_id = :station_id AND message_id = :message_id'
    );
    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':message_id', $message_id, PDO::PARAM_INT);
    $stt->execute();
}

function swap_message_sort_order(PDO $db, int $station_id, int $current_message_id, int $target_message_id): void
{
    $current = fetch_message_row($db, $station_id, $current_message_id);
    $target = fetch_message_row($db, $station_id, $target_message_id);
    if (!$current || !$target) {
        return;
    }

    $db->beginTransaction();
    try {
        $tmp_order = -1;

        /** @noinspection SqlNoDataSourceInspection */
        $stt = $db->prepare('UPDATE message SET sort_order = :sort_order WHERE station_id = :station_id AND message_id = :message_id');
        $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);

        $stt->bindValue(':sort_order', $tmp_order, PDO::PARAM_INT);
        $stt->bindValue(':message_id', $current_message_id, PDO::PARAM_INT);
        $stt->execute();

        $stt->bindValue(':sort_order', (int)$current['sort_order'], PDO::PARAM_INT);
        $stt->bindValue(':message_id', $target_message_id, PDO::PARAM_INT);
        $stt->execute();

        $stt->bindValue(':sort_order', (int)$target['sort_order'], PDO::PARAM_INT);
        $stt->bindValue(':message_id', $current_message_id, PDO::PARAM_INT);
        $stt->execute();

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function move_message(PDO $db, int $station_id, int $message_id, string $direction): void
{
    $current = fetch_message_row($db, $station_id, $message_id);
    if (!$current) {
        return;
    }

    if ($direction === 'up') {
        /** @noinspection SqlNoDataSourceInspection */
        $stt = $db->prepare(
            'SELECT message_id, sort_order
             FROM message
             WHERE station_id = :station_id
               AND (sort_order < :sort_order OR (sort_order = :sort_order AND message_id < :message_id))
             ORDER BY sort_order DESC, message_id DESC
             LIMIT 1'
        );
    } else {
        /** @noinspection SqlNoDataSourceInspection */
        $stt = $db->prepare(
            'SELECT message_id, sort_order
             FROM message
             WHERE station_id = :station_id
               AND (sort_order > :sort_order OR (sort_order = :sort_order AND message_id > :message_id))
             ORDER BY sort_order ASC, message_id ASC
             LIMIT 1'
        );
    }

    $stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $stt->bindValue(':sort_order', (int)$current['sort_order'], PDO::PARAM_INT);
    $stt->bindValue(':message_id', $message_id, PDO::PARAM_INT);
    $stt->execute();
    $target = $stt->fetch(PDO::FETCH_ASSOC);

    if ($target) {
        swap_message_sort_order($db, $station_id, $message_id, (int)$target['message_id']);
    }
}

function redirect_message_page(int $station_id, array $flash = array(), array $params = array()): void
{
    if (count($flash) > 0) {
        $_SESSION['message_flash'] = $flash;
    } else {
        unset($_SESSION['message_flash']);
    }

    $params['s'] = $station_id;
    redirect_with_params('message.php', $params);
}

$station_name = get_station_label($all_stations, $station_id);
$status_type = '';
$status_message = '';
$form_mode = 'add';
$form_message_id = 0;
$form_message = '';
$form_message_e = '';
$form_is_visible = true;
$show_message_modal = false;
$station_drag_speed = (int)fetch_station_message_settings($db, $station_id)['drag_speed'];

if (isset($_SESSION['message_flash']) && is_array($_SESSION['message_flash'])) {
    $message_flash = $_SESSION['message_flash'];
    unset($_SESSION['message_flash']);

    $status_type = trim((string)($message_flash['type'] ?? ''));
    $status_message = trim((string)($message_flash['message'] ?? ''));
    $form_mode_candidate = trim((string)($message_flash['form_mode'] ?? ''));
    if ($form_mode_candidate === 'edit' || $form_mode_candidate === 'add') {
        $form_mode = $form_mode_candidate;
    }
    $form_message_id = (int)($message_flash['form_message_id'] ?? 0);
    $form_message = (string)($message_flash['form_message'] ?? '');
    $form_message_e = (string)($message_flash['form_message_e'] ?? '');
    $form_is_visible = !array_key_exists('form_is_visible', $message_flash) || (bool)$message_flash['form_is_visible'];
    $show_message_modal = !empty($message_flash['show_modal']);
}

if ($status_type === '') {
    $status_type = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
    $status_message = isset($_GET['msg']) ? trim((string)$_GET['msg']) : '';
    if ($status_type !== 'success' && $status_type !== 'error') {
        $status_type = '';
        $status_message = '';
    }
}

$force_new = isset($_GET['new']) && (string)$_GET['new'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $station_id = resolve_station_id($active_stations, (int)($_POST['s'] ?? $station_id));
    $station_name = get_station_label($all_stations, $station_id);
    $station_drag_speed = (int)fetch_station_message_settings($db, $station_id)['drag_speed'];
    $action = trim((string)($_POST['action'] ?? ''));

    try {
        if ($action === 'save_speed') {
            $station_drag_speed = normalize_message_drag_speed((int)($_POST['drag_speed'] ?? MESSAGE_DRAG_SPEED_DEFAULT));
            update_station_message_settings($db, $station_id, $station_drag_speed);
            redirect_message_page($station_id, array(
                'type' => 'success',
                'message' => 'テロップ速度を更新しました。',
            ));
        } elseif ($action === 'save') {
            $form_message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
            $form_message = normalize_message_text((string)($_POST['message'] ?? ''));
            $form_message_e = normalize_message_english_text((string)($_POST['message_e'] ?? ''));
            $form_is_visible = isset($_POST['is_visible']);

            if ($form_message === '') {
                throw new RuntimeException('メッセージを入力してください。');
            }

            if ($form_message_id > 0) {
                if (!fetch_message_row($db, $station_id, $form_message_id)) {
                    throw new RuntimeException('更新対象のメッセージが見つかりません。');
                }
                update_message($db, $station_id, $form_message_id, $form_message, $form_message_e, $form_is_visible);
                $status_message = 'メッセージを更新しました。';
            } else {
                insert_message($db, $station_id, $form_message, $form_message_e, $form_is_visible);
                $status_message = 'メッセージを追加しました。';
            }

            redirect_message_page($station_id, array(
                'type' => 'success',
                'message' => $status_message,
            ));
        } elseif ($action === 'delete') {
            $target_message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
            if (!fetch_message_row($db, $station_id, $target_message_id)) {
                throw new RuntimeException('削除対象のメッセージが見つかりません。');
            }

            delete_message($db, $station_id, $target_message_id);
            redirect_message_page($station_id, array(
                'type' => 'success',
                'message' => 'メッセージを削除しました。',
            ));
        } elseif ($action === 'toggle_visibility') {
            $target_message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
            if (!fetch_message_row($db, $station_id, $target_message_id)) {
                throw new RuntimeException('表示設定の対象メッセージが見つかりません。');
            }

            toggle_message_visibility($db, $station_id, $target_message_id);
            redirect_message_page($station_id, array(
                'type' => 'success',
                'message' => '表示設定を更新しました。',
            ));
        } elseif ($action === 'move_up' || $action === 'move_down') {
            $target_message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
            if (!fetch_message_row($db, $station_id, $target_message_id)) {
                throw new RuntimeException('並び替え対象のメッセージが見つかりません。');
            }

            move_message($db, $station_id, $target_message_id, $action === 'move_up' ? 'up' : 'down');
            redirect_message_page($station_id, array(
                'type' => 'success',
                'message' => '表示順を更新しました。',
            ));
        } else {
            throw new RuntimeException('不正な操作です。');
        }
    } catch (Throwable $e) {
        $flash = array(
            'type' => 'error',
            'message' => $e->getMessage(),
        );
        if ($action === 'save') {
            $flash['show_modal'] = true;
            $flash['form_mode'] = $form_message_id > 0 ? 'edit' : 'add';
            $flash['form_message_id'] = $form_message_id;
            $flash['form_message'] = $form_message;
            $flash['form_message_e'] = $form_message_e;
            $flash['form_is_visible'] = $form_is_visible;
        }
        redirect_message_page($station_id, $flash);
    }
}

$edit_message_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
if (!$force_new && $edit_message_id > 0) {
    $edit_row = fetch_message_row($db, $station_id, $edit_message_id);
    if ($edit_row) {
        $form_mode = 'edit';
        $form_message_id = (int)$edit_row['message_id'];
        $form_message = (string)$edit_row['message'];
        $form_message_e = (string)($edit_row['message_e'] ?? '');
        $form_is_visible = (int)$edit_row['is_visible'] === 1;
        $show_message_modal = true;
    } elseif ($status_message === '') {
        $status_type = 'error';
        $status_message = '更新対象のメッセージが見つかりません。';
    }
}

$message_rows = fetch_station_message_rows($db, $station_id);
$show_message_modal = $show_message_modal || $force_new || $form_mode === 'edit';
$message_page_action = 'message.php?s=' . $station_id;
?>
<!doctype html>
<html lang="ja">
<!--suppress HtmlRequiredTitleElement -->
<head>
    <?php render_app_head('メッセージ設定画面'); ?>
</head>
<body class="<?php print(app_body_classes()); ?>">
<div class="adm-page">
    <div class="<?php print(app_page_shell_classes()); ?>">
        <?php
        $shared_header_station_name = $station_name;
        $shared_header_page_title = 'メッセージ設定';
        $shared_header_station_id = $station_id;
        require __DIR__ . '/header.php';
        ?>

        <?php if ($status_message !== '') { ?>
            <div class="adm-alert <?php print(h($status_type ?: 'error')); ?> <?php print(app_alert_classes(($status_type ?: 'error') === 'error' ? 'error' : 'success')); ?>"><?php print(h($status_message)); ?></div>
        <?php } ?>

        <section class="adm-panel <?php print(app_panel_classes()); ?>">
            <div class="message-table-head mb-5 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="adm-card-title text-3xl font-bold tracking-[0.01em] text-slate-950">メッセージ一覧</h2>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="message-table-meta <?php print(app_badge_classes('brand')); ?>"><?php print(count($message_rows)); ?>件</div>
                    <button class="adm-btn adm-btn-pink <?php print(app_button_classes('primary')); ?>" type="button" id="btnMessageNew">新規登録</button>
                </div>
            </div>

            <?php if (count($message_rows) === 0) { ?>
                <div class="message-empty rounded-[24px] border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">保存されているメッセージはありません。</div>
            <?php } else { ?>
                <div class="<?php print(app_table_frame_classes('overflow-x-auto')); ?>">
                    <table class="adm-table message-table <?php print(app_table_classes()); ?>">
                        <thead>
                        <tr>
                            <th class="col-order bg-slate-950/95 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">順番</th>
                            <th class="col-message bg-slate-950/95 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">メッセージ</th>
                            <th class="col-visible bg-slate-950/95 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">表示</th>
                            <th class="col-actions bg-slate-950/95 px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($message_rows as $display_index => $row) { ?>
                            <?php
                            $row_message_id = (int)$row['message_id'];
                            $is_visible = (int)$row['is_visible'] === 1;
                            ?>
                            <tr class="odd:bg-white even:bg-slate-50/60">
                                <td class="border-b border-slate-200/70 px-4 py-4 font-semibold text-slate-800"><?php print($display_index + 1); ?></td>
                                <td class="border-b border-slate-200/70 px-4 py-4">
                                    <div class="message-cell">
                                        <div class="message-cell-primary text-sm font-semibold leading-7 text-slate-800"><?php print(nl2br(h((string)$row['message']))); ?></div>
                                        <?php $message_english = trim((string)($row['message_e'] ?? '')); ?>
                                        <?php if ($message_english !== '') { ?>
                                            <div class="message-cell-secondary mt-2 text-sm leading-7 text-slate-500"><?php print(nl2br(h($message_english))); ?></div>
                                        <?php } ?>
                                    </div>
                                </td>
                                <td class="border-b border-slate-200/70 px-4 py-4">
                                    <div class="message-visible-cell">
                                        <form class="message-inline-form" method="post" action="<?php print(h($message_page_action)); ?>">
                                            <input type="hidden" name="s" value="<?php print($station_id); ?>">
                                            <input type="hidden" name="message_id" value="<?php print($row_message_id); ?>">
                                            <button
                                                class="message-toggle <?php print($is_visible ? 'is-on' : 'is-off'); ?> <?php print(app_toggle_button_classes($is_visible)); ?>"
                                                type="submit"
                                                name="action"
                                                value="toggle_visibility"
                                                aria-label="<?php print($is_visible ? 'OFF に切り替え' : 'ON に切り替え'); ?>"
                                            >
                                                <span class="message-toggle-switch <?php print(app_toggle_switch_classes($is_visible)); ?>" aria-hidden="true">
                                                    <span class="block <?php print(app_toggle_knob_classes($is_visible)); ?>"></span>
                                                </span>
                                                <span class="message-toggle-label text-[11px]"><?php print($is_visible ? 'ON' : 'OFF'); ?></span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                                <td class="border-b border-slate-200/70 px-4 py-4">
                                    <div class="message-table-actions flex flex-wrap justify-end gap-2">
                                        <button
                                            class="adm-btn <?php print(app_button_classes('secondary', 'sm')); ?>"
                                            type="button"
                                            data-open-message-edit
                                            data-message-id="<?php print($row_message_id); ?>"
                                            data-message="<?php print(h((string)$row['message'])); ?>"
                                            data-message-e="<?php print(h((string)($row['message_e'] ?? ''))); ?>"
                                            data-is-visible="<?php print($is_visible ? '1' : '0'); ?>"
                                        >更新</button>

                                        <form class="message-inline-form" method="post" action="<?php print(h($message_page_action)); ?>">
                                            <input type="hidden" name="s" value="<?php print($station_id); ?>">
                                            <input type="hidden" name="message_id" value="<?php print($row_message_id); ?>">
                                            <button class="adm-btn <?php print(app_button_classes('secondary', 'sm')); ?>" type="submit" name="action" value="move_up">↑</button>
                                        </form>

                                        <form class="message-inline-form" method="post" action="<?php print(h($message_page_action)); ?>">
                                            <input type="hidden" name="s" value="<?php print($station_id); ?>">
                                            <input type="hidden" name="message_id" value="<?php print($row_message_id); ?>">
                                            <button class="adm-btn <?php print(app_button_classes('secondary', 'sm')); ?>" type="submit" name="action" value="move_down">↓</button>
                                        </form>

                                        <form
                                            class="message-inline-form"
                                            method="post"
                                            action="<?php print(h($message_page_action)); ?>"
                                            data-confirm-message="このメッセージを削除しますか?"
                                            data-confirm-button="削除する"
                                            data-confirm-button-class="adm-btn adm-btn-danger"
                                        >
                                            <input type="hidden" name="s" value="<?php print($station_id); ?>">
                                            <input type="hidden" name="message_id" value="<?php print($row_message_id); ?>">
                                            <button class="adm-btn adm-btn-danger <?php print(app_button_classes('danger', 'sm')); ?>" type="submit" name="action" value="delete">削除</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </section>

        <div
            class="app-modal <?php print(app_modal_root_classes()); ?>"
            id="messageModal"
            tabindex="-1"
            role="dialog"
            aria-modal="true"
            aria-labelledby="messageModalTitle"
            aria-hidden="true"
            data-show-on-load="<?php print($show_message_modal ? '1' : '0'); ?>"
            hidden
        >
            <div class="app-modal-dialog app-modal-dialog-lg <?php print(app_modal_dialog_classes('lg')); ?>">
                <div class="app-modal-card message-modal-card <?php print(app_modal_card_classes()); ?>">
                    <form id="messageForm" class="message-form" method="post" action="<?php print(h($message_page_action)); ?>">
                        <div class="app-modal-header <?php print(app_modal_header_classes()); ?>">
                            <div>
                                <h2 class="app-modal-title text-2xl font-bold text-slate-950" id="messageModalTitle"><?php print($form_mode === 'edit' ? 'メッセージ更新' : 'メッセージ追加'); ?></h2>
                            </div>
                            <button type="button" class="app-modal-close <?php print(app_button_classes('ghost', 'sm', 'rounded-full px-3')); ?>" data-modal-close aria-label="閉じる">×</button>
                        </div>

                        <div class="app-modal-body message-modal-body <?php print(app_modal_body_classes()); ?>">
                            <input type="hidden" name="s" value="<?php print($station_id); ?>">
                            <input type="hidden" name="message_id" value="<?php print($form_message_id); ?>">

                            <div class="message-editor-grid grid gap-4">
                                <div class="adm-field message-field-wide <?php print(app_field_classes()); ?>">
                                    <label class="<?php print(app_label_classes()); ?>" for="messageBody">メッセージ</label>
                                    <textarea id="messageBody" class="adm-textarea message-textarea <?php print(app_textarea_classes()); ?>" name="message" maxlength="100" placeholder="100文字まで入力してください"><?php print(h($form_message)); ?></textarea>
                                </div>

                                <div class="adm-field message-field-wide <?php print(app_field_classes()); ?>">
                                    <label class="<?php print(app_label_classes()); ?>" for="messageBodyEn">英語メッセージ</label>
                                    <textarea id="messageBodyEn" class="adm-textarea message-textarea <?php print(app_textarea_classes()); ?>" name="message_e" maxlength="100" placeholder="100文字まで入力してください"><?php print(h($form_message_e)); ?></textarea>
                                </div>

                                <div class="message-visibility-card rounded-[26px] border border-slate-200 bg-slate-50 px-5 py-4">
                                    <label class="message-check inline-flex items-center gap-3 text-sm font-semibold text-slate-700">
                                        <input type="checkbox" name="is_visible" value="1" <?php if ($form_is_visible) { print('checked'); } ?>>
                                        <span>表示</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="app-modal-footer app-modal-footer-between <?php print(app_modal_footer_classes(true)); ?>">
                            <button class="adm-btn adm-btn-soft <?php print(app_button_classes('soft')); ?>" type="button" data-modal-close>閉じる</button>
                            <button class="adm-btn adm-btn-pink <?php print(app_button_classes('primary')); ?>" type="submit" name="action" value="save" data-message-submit><?php print($form_mode === 'edit' ? '更新' : '追加'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <section class="adm-panel <?php print(app_panel_classes()); ?>">
            <h2 class="adm-card-title text-3xl font-bold tracking-[0.01em] text-slate-950">テロップ共通設定</h2>
            <form id="messageSpeedForm" class="message-form" method="post" action="<?php print(h($message_page_action)); ?>">
                <input type="hidden" name="s" value="<?php print($station_id); ?>">

                <div class="message-form-grid mt-6 grid gap-6 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
                    <div class="message-speed-block rounded-[26px] border border-slate-200 bg-slate-50 px-5 py-5">
                        <div class="message-speed-title text-2xl font-bold text-slate-950">テロップの速度</div>
                        <div class="message-speed-scale mt-4">
                            <div class="message-speed-labels mb-3 flex items-center justify-between text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                <span>遅い</span>
                                <span>速い</span>
                            </div>
                            <input id="dragSpeed" class="message-speed-slider h-3 w-full cursor-pointer appearance-none rounded-full bg-slate-200 accent-blue-600" type="range" name="drag_speed" min="1" max="10" step="1" value="<?php print($station_drag_speed); ?>" aria-label="テロップ速度">
                            <div class="message-speed-ticks mt-3 flex items-center justify-between text-xs font-medium text-slate-400" aria-hidden="true">
                                <?php for ($tick = 1; $tick <= 10; $tick++) { ?>
                                    <span><?php print($tick); ?></span>
                                <?php } ?>
                            </div>
                            <div class="message-speed-readout mt-4 text-sm text-slate-600">現在: <strong id="dragSpeedValue" class="text-xl text-slate-950"><?php print($station_drag_speed); ?></strong></div>
                        </div>
                    </div>

                    <div class="message-preview-block rounded-[26px] border border-slate-200 bg-slate-950 px-5 py-5 text-white">
                        <div class="message-preview-title text-2xl font-bold">プレビュー</div>
                        <div class="message-preview-stage relative mt-4 h-28 overflow-hidden rounded-[20px] bg-white/8" id="messagePreviewStage">
                            <div class="message-preview-text absolute left-0 top-1/2 whitespace-nowrap text-lg font-semibold tracking-[0.02em] text-white" id="messagePreviewText"><?php print(h($form_message)); ?></div>
                        </div>
                    </div>
                </div>

                <div class="message-form-actions mt-6 flex justify-end">
                    <button class="adm-btn adm-btn-pink <?php print(app_button_classes('primary')); ?>" type="submit" name="action" value="save_speed">速度を保存</button>
                </div>
            </form>
        </section>
    </div>
</div>
<?php render_app_scripts(array('js/message.js')); ?>
</body>
</html>
