<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/admin_bootstrap.php';
require_once __DIR__ . '/lib/content_library.php';

$login = require_login($db);

$stations = get_active_stations(get_stations($db));
$requested_station_id = isset($_REQUEST['s']) ? (int)$_REQUEST['s'] : 0;
$station_id = resolve_station_id($stations, $requested_station_id);

$common_slot_map = content_fetch_common_slots($db);
$create_target = isset($_REQUEST['target']) ? trim((string)$_REQUEST['target']) : (string)$station_id;
$create_slot_no = isset($_REQUEST['slot']) ? (int)$_REQUEST['slot'] : 1;
$create_title = '';
$create_content_type = 'movie';
$status_type = '';
$status_message = '';
$image_size_label = content_format_bytes(content_effective_max_bytes('image'));
$video_size_label = content_format_bytes(content_effective_max_bytes('movie'));
$image_help_text = '画像: jpg / jpeg / png / gif / webp、最大 ' . $image_size_label;
$video_help_text = '動画: mp4 / webm / ogv / mov / m4v、最大 ' . $video_size_label;
$file_accept = $create_content_type === 'movie'
    ? '.mp4,.webm,.ogv,.mov,.m4v,video/mp4,video/webm,video/ogg,video/quicktime'
    : '.jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp';
$file_help_text = $create_content_type === 'movie' ? $video_help_text : $image_help_text;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $station_id = resolve_station_id($stations, (int)($_POST['s'] ?? $station_id));
    $create_target = trim((string)($_POST['item_station_id'] ?? (string)$station_id));
    $create_slot_no = (int)($_POST['slot_no'] ?? 1);
    $create_title = trim((string)($_POST['title'] ?? ''));
    $create_content_type = content_normalize_type((string)($_POST['content_type'] ?? 'movie'));
    $upload_file = isset($_FILES['content_file']) && is_array($_FILES['content_file']) ? $_FILES['content_file'] : array();

    try {
        content_save_item($db, $stations, $station_id, $create_target, $create_slot_no, $create_title, $create_content_type, $upload_file);
        redirect_with_params('content.php', array('s' => $station_id, 'status' => 'created'));
    } catch (Throwable $e) {
        $status_type = 'error';
        $status_message = $e->getMessage();
    }
}

$station_name = get_station_name($stations, $station_id);
?>
<!doctype html>
<html lang="ja">
<head>
    <?php render_app_head('コンテンツ追加'); ?>
</head>
<body class="<?php print(app_body_classes()); ?>">
<div class="adm-page">
    <div class="<?php print(app_page_shell_classes()); ?>">
        <?php
        $shared_header_station_name = $station_name;
        $shared_header_page_title = 'コンテンツ追加';
        $shared_header_station_id = $station_id;
        require __DIR__ . '/header.php';
        ?>

        <div class="content-manage-actions mb-6 flex flex-wrap gap-2">
            <a class="adm-btn adm-btn-soft <?php print(app_button_classes('soft')); ?>" href="content.php?s=<?php print($station_id); ?>">コンテンツ管理へ</a>
            <a class="adm-btn <?php print(app_button_classes('secondary')); ?>" href="content_setting.php?s=<?php print($station_id); ?>">コンテンツ設定へ</a>
        </div>

        <?php if ($status_message !== '') { ?>
            <div class="adm-alert <?php print(h($status_type)); ?> <?php print(app_alert_classes($status_type === 'error' ? 'error' : 'success')); ?>"><?php print(h($status_message)); ?></div>
        <?php } ?>

        <section class="adm-panel content-add-panel <?php print(app_panel_classes('max-w-4xl')); ?>">
            <form class="content-add-form space-y-5" method="post" action="content_add.php" enctype="multipart/form-data">
                <input type="hidden" name="s" value="<?php print($station_id); ?>">
                <div class="<?php print(app_field_classes()); ?>">
                    <label class="<?php print(app_label_classes()); ?>" for="itemStation">対象</label>
                    <select id="itemStation" class="<?php print(app_select_classes()); ?>" name="item_station_id">
                        <?php foreach ($stations as $station) { ?>
                            <option value="<?php print((int)$station['id']); ?>" <?php if ($create_target === (string)$station['id']) { print('selected'); } ?>><?php print(h((string)$station['name'])); ?></option>
                        <?php } ?>
                        <option value="0" <?php if ($create_target === '0') { print('selected'); } ?>>共通</option>
                    </select>
                </div>
                <div class="adm-field content-add-slot-field <?php print(app_field_classes()); ?>" id="slotField">
                    <label class="<?php print(app_label_classes()); ?>" for="slotNo">共通枠</label>
                    <select id="slotNo" class="<?php print(app_select_classes()); ?>" name="slot_no">
                        <?php for ($slot_no = 1; $slot_no <= CONTENT_COMMON_SLOT_MAX; $slot_no++) { ?>
                            <option value="<?php print($slot_no); ?>" <?php if ($slot_no === $create_slot_no) { print('selected'); } ?>><?php print($slot_no); ?>: <?php print(h(content_slot_title($common_slot_map, $slot_no))); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="adm-field content-add-title-field <?php print(app_field_classes()); ?>">
                    <label class="<?php print(app_label_classes()); ?>" for="title">タイトル</label>
                    <input id="title" class="<?php print(app_input_classes()); ?>" name="title" type="text" maxlength="120" required value="<?php print(h($create_title)); ?>">
                </div>
                <div class="<?php print(app_field_classes()); ?>">
                    <label class="<?php print(app_label_classes()); ?>" for="contentType">種別</label>
                    <select id="contentType" class="<?php print(app_select_classes()); ?>" name="content_type">
                        <option value="image" <?php if ($create_content_type === 'image') { print('selected'); } ?>>画像</option>
                        <option value="movie" <?php if ($create_content_type === 'movie') { print('selected'); } ?>>動画</option>
                    </select>
                </div>
                <div class="adm-field content-add-file-field <?php print(app_field_classes()); ?>">
                    <label class="<?php print(app_label_classes()); ?>" for="contentFile">ファイル</label>
                    <input id="contentFile" class="<?php print(app_input_classes()); ?>" name="content_file" type="file" accept="<?php print(h($file_accept)); ?>" required>
                    <div
                        class="<?php print(app_help_classes()); ?>"
                        id="fileHelp"
                        data-image-help="<?php print(h($image_help_text)); ?>"
                        data-video-help="<?php print(h($video_help_text)); ?>"
                    ><?php print(h($file_help_text)); ?></div>
                </div>
                <div class="content-add-actions flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
                    <a class="adm-btn <?php print(app_button_classes('secondary')); ?>" href="content.php?s=<?php print($station_id); ?>">戻る</a>
                    <button class="adm-btn adm-btn-pink <?php print(app_button_classes('primary')); ?>" type="submit">保存</button>
                </div>
            </form>
        </section>
    </div>
</div>
<?php render_app_scripts(array('js/content_add.js')); ?>
</body>
</html>
