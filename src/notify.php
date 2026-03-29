<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/admin_bootstrap.php';
require_once __DIR__ . '/lib/notify_library.php';

/** @var PDO $db */
if (!isset($db) || !($db instanceof PDO)) {
    $db = app_create_database_connection();
}

$login = require_login($db);
$stations = get_active_stations(get_stations($db));
$requested_station_id = isset($_REQUEST['s']) ? (int)$_REQUEST['s'] : 0;
$station_id = resolve_station_id($stations, $requested_station_id);
$station_name = get_station_name($stations, $station_id);
$definitions = notify_definitions();

function notify_redirect_page(int $station_id, array $flash = array()): void
{
    if (count($flash) > 0) {
        $_SESSION['notify_flash'] = $flash;
    } else {
        unset($_SESSION['notify_flash']);
    }

    redirect_with_params('notify.php', array('s' => $station_id));
}

function notify_normalize_title(string $title, string $fallback): string
{
    $title = trim($title);
    if ($title === '') {
        $title = $fallback;
    }

    return function_exists('mb_substr') ? mb_substr($title, 0, 120, 'UTF-8') : substr($title, 0, 120);
}

function notify_active_status_text(array $active_notice): string
{
    if (empty($active_notice['active'])) {
        return '通常表示';
    }

    $label = trim((string)($active_notice['label'] ?? ''));
    $mode = (string)($active_notice['mode'] ?? '');
    $suffix = $mode === 'manual' ? '(手動)' : '(自動)';

    return $label !== '' ? ($label . $suffix) : $suffix;
}

function notify_build_cards(array $definitions, array $state, string $selected_key): array
{
    $cards = array();

    foreach ($definitions as $key => $definition) {
        $item = $state['items'][$key] ?? array();
        $title = trim((string)($item['title'] ?? $definition['label']));
        if ($title === '') {
            $title = (string)$definition['label'];
        }

        $cards[] = array(
            'key' => $key,
            'label' => (string)$definition['label'],
            'image_path' => trim((string)($item['image_path'] ?? '')),
            'title' => $title,
            'is_selected' => $selected_key === $key,
        );
    }

    return $cards;
}

$status_type = '';
$status_message = '';
if (isset($_SESSION['notify_flash']) && is_array($_SESSION['notify_flash'])) {
    $status_type = trim((string)($_SESSION['notify_flash']['type'] ?? ''));
    $status_message = trim((string)($_SESSION['notify_flash']['message'] ?? ''));
    unset($_SESSION['notify_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $station_id = resolve_station_id($stations, (int)($_POST['s'] ?? $station_id));
    $action = trim((string)($_POST['action'] ?? ''));

    try {
        if ($action === 'upload') {
            $notify_key = trim((string)($_POST['notify_key'] ?? ''));
            if (!isset($definitions[$notify_key])) {
                throw new RuntimeException('通知種別を選択してください。');
            }

            $title = notify_normalize_title((string)($_POST['title'] ?? ''), (string)$definitions[$notify_key]['label']);
            $state = notify_load_state();
            $old_path = trim((string)($state['items'][$notify_key]['image_path'] ?? ''));
            $image_path = notify_store_uploaded_image(
                isset($_FILES['notify_image']) && is_array($_FILES['notify_image']) ? $_FILES['notify_image'] : array(),
                $notify_key
            );

            try {
                $state['items'][$notify_key]['title'] = $title;
                $state['items'][$notify_key]['image_path'] = $image_path;
                $state['items'][$notify_key]['updated_at'] = date('c');
                notify_save_state($state);
            } catch (Throwable $inner) {
                notify_delete_image_file($image_path);
                throw $inner;
            }

            if ($old_path !== '' && $old_path !== $image_path) {
                notify_delete_image_file($old_path);
            }

            notify_redirect_page($station_id, array(
                'type' => 'success',
                'message' => '通知画像を保存しました。',
            ));
        }

        if ($action === 'select') {
            $selected_key = trim((string)($_POST['selected_key'] ?? ''));
            $state = notify_load_state();

            if ($selected_key !== '') {
                if (!isset($definitions[$selected_key])) {
                    throw new RuntimeException('通知種別を選択してください。');
                }
                $image_path = trim((string)($state['items'][$selected_key]['image_path'] ?? ''));
                if ($image_path === '') {
                    throw new RuntimeException('選択した通知画像が未登録です。');
                }
            }

            $state = notify_set_selected_key_for_station($state, $station_id, $selected_key);
            notify_save_state($state);

            notify_redirect_page($station_id, array(
                'type' => 'success',
                'message' => $selected_key === '' ? '通常表示に戻しました。' : '通知表示を更新しました。',
            ));
        }

        throw new RuntimeException('不正な操作です。');
    } catch (Throwable $e) {
        notify_redirect_page($station_id, array(
            'type' => 'error',
            'message' => $e->getMessage(),
        ));
    }
}

$state = notify_load_state();
$active_notice = notify_resolve_active_notice($db, $station_id);
$selected_key = notify_get_selected_key_for_station($state, $station_id);
$notify_page_action = 'notify.php?s=' . $station_id;
$active_notice_text = notify_active_status_text($active_notice);
$auto_notice_help = '';
if ((string)($active_notice['mode'] ?? '') === 'auto' && (string)($active_notice['last_departure'] ?? '') !== '') {
    $auto_notice_help = '営業終了は ' . $station_name . '港の最終便 ' . (string)$active_notice['last_departure'] . ' 出港後に自動表示されます。';
}
$notice_cards = notify_build_cards($definitions, $state, $selected_key);
?>
<!doctype html>
<html lang="ja">
<!--suppress HtmlRequiredTitleElement -->
<head>
    <?php render_app_head('通知表示設定'); ?>
</head>
<body class="<?php print(app_body_classes()); ?>">
<div class="adm-page">
    <div class="<?php print(app_page_shell_classes()); ?>">
        <?php
        $shared_header_station_name = $station_name;
        $shared_header_page_title = '通知表示設定';
        $shared_header_station_id = $station_id;
        require __DIR__ . '/header.php';
        ?>

        <?php if ($status_message !== '') { ?>
            <div class="adm-alert <?php print(h($status_type)); ?> <?php print(app_alert_classes($status_type === 'error' ? 'error' : 'success')); ?>"><?php print(h($status_message)); ?></div>
        <?php } ?>

        <section class="adm-panel mb-4 <?php print(app_panel_classes()); ?>">
            <form class="adm-toolbar flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between" method="get" action="notify.php">
                <div class="<?php print(app_field_classes('max-w-xl')); ?>">
                    <label class="<?php print(app_label_classes()); ?>" for="notifyStation">停留所確認</label>
                    <select id="notifyStation" class="<?php print(app_select_classes()); ?>" name="s">
                        <?php foreach ($stations as $station) { ?>
                            <option value="<?php print((int)$station['id']); ?>" <?php if ((int)$station['id'] === $station_id) { print('selected'); } ?>>
                                <?php print(h((string)$station['name'])); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <button class="adm-btn adm-btn-soft <?php print(app_button_classes('soft')); ?>" type="submit">切替</button>
            </form>

            <div class="mt-4 rounded-[24px] border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-600">
                <div><strong class="text-slate-950">現在の放映状態:</strong> <?php print(h($active_notice_text)); ?></div>
                <?php if ($auto_notice_help !== '') { ?>
                    <div class="<?php print(app_help_classes('mt-2')); ?>"><?php print(h($auto_notice_help)); ?></div>
                <?php } ?>
            </div>

            <form class="mt-4 space-y-4" method="post" action="<?php print(h($notify_page_action)); ?>">
                <input type="hidden" name="s" value="<?php print($station_id); ?>">
                <input type="hidden" name="action" value="select">
                <div class="<?php print(app_field_classes('max-w-xl')); ?>">
                    <label class="<?php print(app_label_classes()); ?>" for="selectedNotice">表示する通知</label>
                    <select id="selectedNotice" class="<?php print(app_select_classes()); ?>" name="selected_key">
                        <option value="" <?php if ($selected_key === '') { print('selected'); } ?>>通常表示</option>
                        <?php foreach ($definitions as $key => $definition) { ?>
                            <?php $has_image = trim((string)($state['items'][$key]['image_path'] ?? '')) !== ''; ?>
                            <option value="<?php print(h($key)); ?>" <?php if ($selected_key === $key) { print('selected'); } ?> <?php if (!$has_image) { print('disabled'); } ?>>
                                <?php print(h((string)$definition['label'])); ?><?php if (!$has_image) { ?> (未登録)<?php } ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button class="adm-btn adm-btn-pink <?php print(app_button_classes('primary')); ?>" type="submit">表示切替</button>
                    <button class="adm-btn <?php print(app_button_classes('secondary')); ?>" type="submit" name="selected_key" value="">通常表示に戻す</button>
                </div>
            </form>
        </section>

        <div class="grid gap-4 xl:grid-cols-3">
            <?php foreach ($notice_cards as $card) { ?>
                <div>
                    <section class="adm-panel h-full <?php print(app_panel_classes('flex h-full flex-col')); ?>">
                        <h2 class="adm-card-title text-2xl font-bold tracking-[0.01em] text-slate-950"><?php print(h($card['label'])); ?></h2>
                        <p class="adm-card-desc mt-2 text-sm leading-7 text-slate-500">形式は jpg/png、サイズは 1920x1080px です。</p>

                        <div class="content-card-grid mt-3">
                            <?php if ($card['image_path'] !== '') { ?>
                                <article class="content-card overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_12px_30px_rgba(15,23,42,0.06)]">
                                    <div class="content-card-thumb aspect-[16/9] overflow-hidden bg-slate-100">
                                        <img class="h-full w-full object-cover" src="<?php print(h($card['image_path'])); ?>" alt="<?php print(h($card['title'])); ?>">
                                    </div>
                                    <div class="content-card-body space-y-3 px-5 py-5">
                                        <div class="content-card-tags flex flex-wrap gap-2">
                                            <span class="content-card-tag <?php print(app_badge_classes('neutral')); ?>"><?php print(h($card['label'])); ?></span>
                                            <?php if ($card['is_selected']) { ?><span class="content-card-tag active <?php print(app_badge_classes('brand')); ?>">手動選択中</span><?php } ?>
                                        </div>
                                        <div class="content-card-name text-base font-semibold text-slate-900"><?php print(h($card['title'])); ?></div>
                                    </div>
                                </article>
                            <?php } else { ?>
                                <div class="content-card content-card-empty flex aspect-[16/9] flex-col items-center justify-center rounded-[28px] border border-dashed border-slate-200 bg-slate-50 px-6 text-center">
                                    <span class="content-card-empty-thumb <?php print(app_badge_classes('neutral')); ?>"><?php print(h($card['label'])); ?></span>
                                    <span class="content-card-empty-name mt-4 text-sm font-medium text-slate-500">未登録</span>
                                </div>
                            <?php } ?>
                        </div>

                        <form class="mt-5 space-y-4" method="post" action="<?php print(h($notify_page_action)); ?>" enctype="multipart/form-data">
                            <input type="hidden" name="s" value="<?php print($station_id); ?>">
                            <input type="hidden" name="action" value="upload">
                            <input type="hidden" name="notify_key" value="<?php print(h($card['key'])); ?>">

                            <div class="<?php print(app_field_classes()); ?>">
                                <label class="<?php print(app_label_classes()); ?>" for="notifyTitle_<?php print(h($card['key'])); ?>">タイトル</label>
                                <input
                                    id="notifyTitle_<?php print(h($card['key'])); ?>"
                                    class="<?php print(app_input_classes()); ?>"
                                    name="title"
                                    type="text"
                                    maxlength="120"
                                    value="<?php print(h($card['title'])); ?>"
                                >
                            </div>
                            <div class="<?php print(app_field_classes()); ?>">
                                <label class="<?php print(app_label_classes()); ?>" for="notifyFile_<?php print(h($card['key'])); ?>">画像ファイル</label>
                                <input
                                    id="notifyFile_<?php print(h($card['key'])); ?>"
                                    class="<?php print(app_input_classes()); ?>"
                                    name="notify_image"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                    required
                                >
                            </div>
                            <button class="adm-btn adm-btn-pink <?php print(app_button_classes('primary')); ?>" type="submit">保存</button>
                        </form>
                    </section>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<?php render_app_scripts(); ?>
</body>
</html>
