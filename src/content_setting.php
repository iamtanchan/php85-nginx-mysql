<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/admin_bootstrap.php';
require_once __DIR__ . '/lib/content_library.php';

require_login($db);

$stations = get_active_stations(get_stations($db));
$requested_station_id = isset($_REQUEST['s']) ? (int)$_REQUEST['s'] : 0;
$station_id = resolve_station_id($stations, $requested_station_id);
$station_name = get_station_name($stations, $station_id);

$station_name_map = array();
foreach ($stations as $station) {
    $station_name_map[(int)$station['id']] = (string)$station['name'];
}

function content_setting_station_label(array $station_name_map, int $item_station_id): string
{
    return $item_station_id === CONTENT_COMMON_STATION_ID
        ? '共通'
        : (string)($station_name_map[$item_station_id] ?? ('停留所ID ' . $item_station_id));
}

function content_setting_normalize_ids($raw): array
{
    if (!is_array($raw)) {
        $raw = array($raw);
    }

    $ids = array();
    foreach ($raw as $value) {
        $id = (int)$value;
        if ($id > 0 && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }

    return $ids;
}

$status_type = '';
$status_message = '';
$swap_interval_seconds = CONTENT_DISPLAY_SWAP_INTERVAL_DEFAULT;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $station_id = resolve_station_id($stations, (int)($_POST['s'] ?? $station_id));
    $action = trim((string)($_POST['action'] ?? ''));

    try {
        if ($action === 'publish') {
            $publish_ids = content_setting_normalize_ids($_POST['publish_ids'] ?? array());
            $swap_interval_input = trim((string)($_POST['swap_interval_seconds'] ?? (string)CONTENT_DISPLAY_SWAP_INTERVAL_DEFAULT));

            if ($swap_interval_input === '' || preg_match('/^\d+$/', $swap_interval_input) !== 1) {
                throw new RuntimeException('切替秒数は0以上の整数で入力してください。');
            }

            $swap_interval_seconds = (int)$swap_interval_input;
            if ($swap_interval_seconds > CONTENT_DISPLAY_SWAP_INTERVAL_MAX) {
                throw new RuntimeException('切替秒数は' . CONTENT_DISPLAY_SWAP_INTERVAL_MAX . '秒以下で入力してください。');
            }
            if (count($publish_ids) > CONTENT_DISPLAY_MAX) {
                throw new RuntimeException('ディスプレイに表示できるコンテンツは最大' . CONTENT_DISPLAY_MAX . '件です。');
            }

            foreach ($publish_ids as $content_id) {
                $row = content_fetch_item($db, $content_id);
                if (!$row || !content_is_visible_for_station($row, $station_id)) {
                    throw new RuntimeException('選択した停留所では表示できないコンテンツが含まれています。');
                }
            }

            $db->beginTransaction();
            content_replace_display_items($db, $station_id, $publish_ids);
            content_update_display_settings($db, $station_id, $swap_interval_seconds);
            content_bump_display_counter($db, $station_id);
            $db->commit();

            $status_type = 'success';
            $status_message = 'ディスプレイを更新しました。';
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $status_type = 'error';
        $status_message = $e->getMessage();
    }
}

$all_selected_lookup = array_fill_keys(content_fetch_selected_ids($db), true);
$display_settings = content_fetch_display_settings($db, $station_id);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $status_type === 'success') {
    $swap_interval_seconds = (int)$display_settings['swap_interval_seconds'];
}

$setting_rows = content_fetch_rows($db, $station_id);
$published_ids = content_fetch_published_ids($db, $station_id);
$setting_items = array();

foreach ($setting_rows as $row) {
    if (trim((string)($row['content_value'] ?? '')) === '') {
        continue;
    }

    $row_station_id = (int)$row['station_id'];
    $row_id = (int)$row['id'];
    $setting_items[] = array(
        'id' => $row_id,
        'title' => (string)$row['title'],
        'type' => (string)$row['content_type'],
        'value' => (string)$row['content_value'],
        'station' => content_setting_station_label($station_name_map, $row_station_id),
    );
}

$json_flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!doctype html>
<html lang="ja">
<head>
    <?php render_app_head('コンテンツ設定'); ?>
</head>
<body class="<?php print(app_body_classes()); ?>">
<div class="adm-page">
    <div class="<?php print(app_page_shell_classes()); ?>">
        <?php
        $shared_header_station_name = $station_name;
        $shared_header_page_title = 'コンテンツ設定';
        $shared_header_station_id = $station_id;
        require __DIR__ . '/header.php';
        ?>

        <?php if ($status_message !== '') { ?>
            <div class="adm-alert <?php print(h($status_type)); ?> <?php print(app_alert_classes($status_type === 'error' ? 'error' : 'success')); ?>"><?php print(h($status_message)); ?></div>
        <?php } ?>

        <section class="adm-panel content-setting-panel <?php print(app_panel_classes()); ?>">
            <form class="content-setting-toolbar flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between" method="get" action="content_setting.php">
                <div class="<?php print(app_field_classes('max-w-xl')); ?>">
                    <label class="<?php print(app_label_classes()); ?>" for="settingStation">停留所選択</label>
                    <select id="settingStation" class="<?php print(app_select_classes()); ?>" name="s">
                        <?php foreach ($stations as $station) { ?>
                            <option value="<?php print((int)$station['id']); ?>" <?php if ((int)$station['id'] === $station_id) { print('selected'); } ?>><?php print(h((string)$station['name'])); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <button class="adm-btn adm-btn-soft <?php print(app_button_classes('soft')); ?>" type="submit">切替</button>
            </form>

            <p class="content-setting-description mt-5 text-sm leading-7 text-slate-500">選択したコンテンツが表示画面と待機画面に表示されます。ドラッグ&ドロップで追加と並び替えができます。</p>

            <form id="publishForm" class="mt-6 space-y-6" method="post" action="content_setting.php">
                <input type="hidden" name="s" value="<?php print($station_id); ?>">
                <input type="hidden" name="action" value="publish">

                <div class="content-setting-controls">
                    <div class="adm-field content-interval-field <?php print(app_field_classes()); ?>">
                        <label class="<?php print(app_label_classes()); ?>" for="swapIntervalSeconds">切替秒数(秒)</label>
                        <div class="content-interval-stepper flex flex-col gap-3 sm:flex-row sm:items-center">
                            <button class="adm-btn content-interval-button <?php print(app_button_classes('secondary')); ?>" type="button" data-interval-step="-1" aria-label="1秒減らす">-</button>
                            <input
                                id="swapIntervalSeconds"
                                class="<?php print(app_input_classes('max-w-[10rem]')); ?>"
                                name="swap_interval_seconds"
                                type="number"
                                min="0"
                                max="<?php print(CONTENT_DISPLAY_SWAP_INTERVAL_MAX); ?>"
                                step="1"
                                required
                                value="<?php print((int)$swap_interval_seconds); ?>"
                            >
                            <button class="adm-btn content-interval-button <?php print(app_button_classes('secondary')); ?>" type="button" data-interval-step="1" aria-label="1秒増やす">+</button>
                            <button class="adm-btn adm-btn-pink content-interval-submit <?php print(app_button_classes('primary')); ?>" type="submit">更新</button>
                        </div>
                        <div class="<?php print(app_help_classes()); ?>">0秒にすると待機画面のみ表示します。秒数を設定するとコンテンツと待機画面を切り替えます。</div>
                    </div>
                </div>

                <div class="content-dropzone rounded-[28px] border border-dashed border-slate-300 bg-slate-50 px-5 py-5" id="contentDropzone">
                    <div class="content-dropzone-head flex items-center justify-between gap-3">
                        <strong class="text-2xl font-bold text-slate-950"><?php print(h(content_setting_station_label($station_name_map, $station_id))); ?></strong>
                        <span class="content-selected-count <?php print(app_badge_classes('brand')); ?>" id="selectedCount"><?php print(count($published_ids)); ?> / <?php print(CONTENT_DISPLAY_MAX); ?></span>
                    </div>
                    <div class="content-dropzone-empty mt-4 rounded-[22px] border border-dashed border-slate-300 bg-white px-5 py-8 text-center text-sm text-slate-500" id="dropzoneEmpty">コンテンツをドラッグ&ドロップして追加</div>
                    <div class="content-selected-grid mt-4 grid gap-3" id="selectedList"></div>
                </div>

                <div id="publishInputs"></div>

                <?php if (count($setting_items) > 0) { ?>
                    <div class="content-gallery-grid grid gap-4 md:grid-cols-2 xl:grid-cols-3" id="galleryGrid">
                        <?php foreach ($setting_items as $item) { ?>
                            <div class="content-gallery-item">
                                <button type="button" class="content-gallery-card js-setting-item group w-full overflow-hidden rounded-[28px] border border-slate-200 bg-white text-left shadow-[0_12px_30px_rgba(15,23,42,0.06)] transition hover:-translate-y-0.5 hover:shadow-[0_18px_35px_rgba(15,23,42,0.08)]" draggable="true" data-id="<?php print((int)$item['id']); ?>">
                                    <span class="content-gallery-marker sr-only"></span>
                                    <div
                                        class="content-card-thumb<?php if ($item['type'] === 'movie') { print(' js-content-preview'); } ?> relative aspect-[16/9] overflow-hidden bg-slate-100"
                                        <?php if ($item['type'] === 'movie') { ?>
                                            data-preview-type="movie"
                                            data-preview-src="<?php print(h($item['value'])); ?>"
                                            data-preview-title="<?php print(h($item['title'])); ?>"
                                        <?php } ?>
                                    >
                                        <?php if ($item['type'] === 'movie') { ?>
                                            <video class="h-full w-full object-cover" src="<?php print(h($item['value'])); ?>" muted playsinline preload="metadata"></video>
                                            <span class="content-card-play absolute inset-x-0 bottom-4 mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white/90 text-slate-950 shadow-lg">▶</span>
                                        <?php } else { ?>
                                            <img class="h-full w-full object-cover" src="<?php print(h($item['value'])); ?>" alt="<?php print(h($item['title'])); ?>">
                                        <?php } ?>
                                    </div>
                                    <div class="content-card-body space-y-3 px-5 py-5">
                                        <div class="content-card-tags"><span class="content-card-tag <?php print(app_badge_classes('neutral')); ?>"><?php print(h($item['station'])); ?></span></div>
                                        <div class="content-card-name text-base font-semibold text-slate-900"><?php print(h($item['title'])); ?></div>
                                    </div>
                                </button>
                            </div>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="content-setting-empty rounded-[24px] border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">選択した停留所で利用できるコンテンツはありません。</div>
                <?php } ?>

                <div class="content-setting-footer flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
                    <a class="adm-btn <?php print(app_button_classes('secondary')); ?>" href="content.php?s=<?php print($station_id); ?>">戻る</a>
                    <button class="adm-btn adm-btn-pink <?php print(app_button_classes('primary')); ?>" type="submit">ディスプレイ更新</button>
                </div>
            </form>
        </section>
    </div>
</div>
<script id="content-setting-data" type="application/json"><?php print(json_encode(array('items' => $setting_items, 'selected' => array_values($published_ids), 'limit' => CONTENT_DISPLAY_MAX), $json_flags)); ?></script>
<?php render_app_scripts(array('js/content_preview.js?v=1.0.0', 'js/content_setting.js?v=1.0.1')); ?>
</body>
</html>
