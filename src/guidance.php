<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/admin_bootstrap.php';
require_once __DIR__ . '/lib/guidance_library.php';

/** @var PDO $db */
if (!isset($db) || !($db instanceof PDO)) {
    $db = app_create_database_connection();
}

$login = require_login($db);
$stations = get_active_stations(get_stations($db));
$requested_station_id = isset($_REQUEST['s']) ? (int)$_REQUEST['s'] : 0;
$station_id = resolve_station_id($stations, $requested_station_id);
$station_name = get_station_name($stations, $station_id);
$definitions = guidance_definitions();

function guidance_self_path(): string
{
    $self = trim((string)($_SERVER['PHP_SELF'] ?? 'guidance.php'));
    return $self !== '' ? $self : 'guidance.php';
}

$status_type = '';
$status_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $station_id = resolve_station_id($stations, (int)($_POST['s'] ?? $station_id));
    $action = trim((string)($_POST['action'] ?? ''));

    try {
        if ($action === 'settings') {
            $lead_minutes = guidance_normalize_lead_minutes((int)($_POST['lead_minutes'] ?? GUIDANCE_LEAD_MINUTES_DEFAULT));
            $state = guidance_load_state();
            $state['lead_minutes'] = $lead_minutes;
            guidance_save_state($state);

            $status_type = 'success';
            $status_message = '案内動画の再生タイミングを更新しました。';
        } elseif ($action === 'upload') {
            $guidance_key = trim((string)($_POST['guidance_key'] ?? ''));
            if (!isset($definitions[$guidance_key])) {
                throw new RuntimeException('案内動画種別を選択してください。');
            }

            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '') {
                $title = (string)$definitions[$guidance_key]['label'];
            }
            $title = function_exists('mb_substr') ? mb_substr($title, 0, 120, 'UTF-8') : substr($title, 0, 120);

            $state = guidance_load_state();
            $old_path = trim((string)($state['items'][$guidance_key]['video_path'] ?? ''));
            $video_path = guidance_store_uploaded_video(
                isset($_FILES['guidance_video']) && is_array($_FILES['guidance_video']) ? $_FILES['guidance_video'] : array(),
                $guidance_key
            );

            try {
                $state['items'][$guidance_key]['title'] = $title;
                $state['items'][$guidance_key]['video_path'] = $video_path;
                $state['items'][$guidance_key]['updated_at'] = date('c');
                guidance_save_state($state);
            } catch (Throwable $inner) {
                guidance_delete_video_file($video_path);
                throw $inner;
            }

            if ($old_path !== '' && $old_path !== $video_path) {
                guidance_delete_video_file($old_path);
            }

            $status_type = 'success';
            $status_message = '案内動画を保存しました。';
        } else {
            throw new RuntimeException('不正な操作です。');
        }
    } catch (Throwable $e) {
        $status_type = 'error';
        $status_message = $e->getMessage();
    }
}

$guidance_page_base = guidance_self_path();
$guidance_page_action = $guidance_page_base . '?s=' . $station_id;
$state = guidance_load_state();
$payload = guidance_export_payload($state);
?>
<!doctype html>
<html lang="ja">
<!--suppress HtmlRequiredTitleElement -->

<head>
    <?php render_app_head('案内動画設定'); ?>
</head>

<body class="<?php print(app_body_classes()); ?>">
    <div class="adm-page">
        <div class="<?php print(app_page_shell_classes()); ?>">
            <?php
            $shared_header_station_name = $station_name;
            $shared_header_page_title = '案内動画設定';
            $shared_header_station_id = $station_id;
            require __DIR__ . '/header.php';
            ?>

            <?php if ($status_message !== '') { ?>
                <div class="adm-alert <?php print(h($status_type)); ?> <?php print(app_alert_classes($status_type === 'error' ? 'error' : 'success')); ?>"><?php print(h($status_message)); ?></div>
            <?php } ?>

            <section class="adm-panel mb-4 <?php print(app_panel_classes()); ?>">
                <form class="adm-toolbar flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between" method="get" action="<?php print(h($guidance_page_base)); ?>">
                    <div class="<?php print(app_field_classes('max-w-xl')); ?>">
                        <label class="<?php print(app_label_classes()); ?>" for="guidanceStation">停留所確認</label>
                        <select id="guidanceStation" class="<?php print(app_select_classes()); ?>" name="s">
                            <?php foreach ($stations as $station) { ?>
                                <option value="<?php print((int)$station['id']); ?>" <?php if ((int)$station['id'] === $station_id) {
                                                                                            print('selected');
                                                                                        } ?>>
                                    <?php print(h((string)$station['name'])); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <button class="adm-btn adm-btn-soft <?php print(app_button_classes('soft')); ?>" type="submit">切替</button>
                </form>

                <div class="mt-4 rounded-[24px] border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-600">
                    <div><strong class="text-slate-950">現在の状態:</strong> <?php if (!empty($payload['ready'])) { ?>自動再生可能<?php } else { ?>未設定<?php } ?></div>
                </div>

                <form class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end" method="post" action="<?php print(h($guidance_page_action)); ?>" data-ajax-skip="1">
                    <input type="hidden" name="s" value="<?php print($station_id); ?>">
                    <input type="hidden" name="action" value="settings">
                    <div class="<?php print(app_field_classes('max-w-xs')); ?>">
                        <label class="<?php print(app_label_classes()); ?>" for="leadMinutes">再生開始タイミング(分前)</label>
                        <input
                            id="leadMinutes"
                            class="<?php print(app_input_classes()); ?>"
                            name="lead_minutes"
                            type="number"
                            min="0"
                            max="<?php print(GUIDANCE_LEAD_MINUTES_MAX); ?>"
                            step="1"
                            required
                            value="<?php print((int)$payload['lead_minutes']); ?>">
                    </div>
                    <button class="adm-btn adm-btn-pink <?php print(app_button_classes('primary')); ?>" type="submit">タイミング更新</button>
                </form>
            </section>

            <div class="grid gap-4 xl:grid-cols-2">
                <?php foreach ($definitions as $key => $definition) { ?>
                    <?php
                    $item = isset($state['items'][$key]) && is_array($state['items'][$key]) ? $state['items'][$key] : array();
                    $title = trim((string)($item['title'] ?? $definition['label']));
                    $video_path = trim((string)($item['video_path'] ?? ''));
                    ?>
                    <div>
                        <section class="adm-panel h-full <?php print(app_panel_classes('flex h-full flex-col')); ?>">
                            <div class="content-card-grid mt-3">
                                <?php if ($video_path !== '') { ?>
                                    <article class="content-card overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_12px_30px_rgba(15,23,42,0.06)]">
                                        <div class="content-card-thumb js-content-preview relative aspect-[16/9] overflow-hidden bg-slate-950" data-preview-type="movie" data-preview-src="<?php print(h($video_path)); ?>" data-preview-title="<?php print(h($title)); ?>">
                                            <video class="h-full w-full object-cover" src="<?php print(h($video_path)); ?>" muted playsinline preload="metadata"></video>
                                            <span class="content-card-play absolute inset-0 m-auto flex h-12 w-12 items-center justify-center rounded-full bg-white/90 text-slate-950 shadow-lg">▶</span>
                                        </div>
                                        <div class="content-card-body space-y-3 px-5 py-5">
                                            <div class="content-card-name text-base font-semibold text-slate-900"><?php print(h($title)); ?></div>
                                        </div>
                                    </article>
                                <?php } else { ?>
                                    <div class="content-card content-card-empty flex aspect-[16/9] flex-col items-center justify-center rounded-[28px] border border-dashed border-slate-200 bg-slate-50 px-6 text-center">
                                        <span class="content-card-empty-thumb <?php print(app_badge_classes('neutral')); ?>"><?php print(h((string)$definition['label'])); ?></span>
                                        <span class="content-card-empty-name mt-4 text-sm font-medium text-slate-500">未登録</span>
                                    </div>
                                <?php } ?>
                            </div>

                            <form class="mt-5 space-y-4" method="post" action="<?php print(h($guidance_page_action)); ?>" enctype="multipart/form-data" data-ajax-skip="1">
                                <input type="hidden" name="s" value="<?php print($station_id); ?>">
                                <input type="hidden" name="action" value="upload">
                                <input type="hidden" name="guidance_key" value="<?php print(h($key)); ?>">

                                <div class="<?php print(app_field_classes()); ?>">
                                    <label class="<?php print(app_label_classes()); ?>" for="guidanceTitle_<?php print(h($key)); ?>">タイトル</label>
                                    <input
                                        id="guidanceTitle_<?php print(h($key)); ?>"
                                        class="<?php print(app_input_classes()); ?>"
                                        name="title"
                                        type="text"
                                        maxlength="120"
                                        value="<?php print(h($title)); ?>">
                                </div>
                                <div class="<?php print(app_field_classes()); ?>">
                                    <label class="<?php print(app_label_classes()); ?>" for="guidanceFile_<?php print(h($key)); ?>">動画ファイル</label>
                                    <input
                                        id="guidanceFile_<?php print(h($key)); ?>"
                                        class="<?php print(app_input_classes()); ?>"
                                        name="guidance_video"
                                        type="file"
                                        accept=".mp4,.webm,.ogv,.mov,.m4v,video/mp4,video/webm,video/ogg,video/quicktime"
                                        required>
                                    <div class="<?php print(app_help_classes()); ?>">登録済み動画は新しい動画で上書きされます。</div>
                                </div>
                                <button class="adm-btn adm-btn-pink <?php print(app_button_classes('primary')); ?>" type="submit">保存</button>
                            </form>
                        </section>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php render_app_scripts(array('js/content_preview.js?v=1.0.0')); ?>
</body>

</html>