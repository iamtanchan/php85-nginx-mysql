<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/admin_bootstrap.php';
require_once __DIR__ . '/lib/content_library.php';

/** @var PDO $db */
if (!isset($db) || !($db instanceof PDO)) {
    $db = app_create_database_connection();
}

$login = require_login($db);

$stations = get_active_stations(get_stations($db));
$requested_station_id = isset($_REQUEST['s']) ? (int)$_REQUEST['s'] : 0;
$station_id = resolve_station_id($stations, $requested_station_id);
$station_name = get_station_name($stations, $station_id);

function content_type_label(string $content_type): string
{
    return $content_type === 'movie' ? '動画' : '画像';
}

function content_normalize_ids($raw): array
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

function redirect_content_page(int $station_id, array $flash = array()): void
{
    if (count($flash) > 0) {
        $_SESSION['content_flash'] = $flash;
    } else {
        unset($_SESSION['content_flash']);
    }

    redirect_with_params('content.php', array('s' => $station_id));
}

$status_type = '';
$status_message = '';
$show_create_modal = false;
$create_target = (string)$station_id;
$create_slot_no = 1;
$create_title = '';
$create_content_type = 'movie';

if (isset($_SESSION['content_flash']) && is_array($_SESSION['content_flash'])) {
    $content_flash = $_SESSION['content_flash'];
    unset($_SESSION['content_flash']);

    $status_type = trim((string)($content_flash['type'] ?? ''));
    $status_message = trim((string)($content_flash['message'] ?? ''));
    $show_create_modal = !empty($content_flash['show_modal']);
    $create_target = trim((string)($content_flash['create_target'] ?? $create_target));
    $create_slot_no = (int)($content_flash['create_slot_no'] ?? $create_slot_no);
    $create_title = trim((string)($content_flash['create_title'] ?? $create_title));
    $create_content_type = content_normalize_type((string)($content_flash['create_content_type'] ?? $create_content_type));
}

if (isset($_GET['status']) && (string)$_GET['status'] === 'created') {
    $status_type = 'success';
    $status_message = 'コンテンツを保存しました。';
}

$common_slot_map = content_fetch_common_slots($db);
$image_size_label = content_format_bytes(content_effective_max_bytes('image'));
$video_size_label = content_format_bytes(content_effective_max_bytes('movie'));
$image_help_text = '画像: jpg / jpeg / png / gif / webp、最大 ' . $image_size_label;
$video_help_text = '動画: mp4 / webm / ogv / mov / m4v、最大 ' . $video_size_label;
$file_accept = $create_content_type === 'movie'
    ? '.mp4,.webm,.ogv,.mov,.m4v,video/mp4,video/webm,video/ogg,video/quicktime'
    : '.jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp';
$file_help_text = $create_content_type === 'movie' ? $video_help_text : $image_help_text;
$content_page_action = 'content.php?s=' . $station_id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $station_id = resolve_station_id($stations, (int)($_POST['s'] ?? $station_id));
    $station_name = get_station_name($stations, $station_id);
    $action = trim((string)($_POST['action'] ?? ''));
    $create_target = trim((string)($_POST['item_station_id'] ?? $create_target));
    $create_slot_no = (int)($_POST['slot_no'] ?? $create_slot_no);
    $create_title = trim((string)($_POST['title'] ?? $create_title));
    $create_content_type = content_normalize_type((string)($_POST['content_type'] ?? $create_content_type));
    $upload_file = isset($_FILES['content_file']) && is_array($_FILES['content_file']) ? $_FILES['content_file'] : array();

    try {
        if ($action === 'bulk_delete') {
            $delete_ids = content_normalize_ids($_POST['delete_ids'] ?? array());
            if (count($delete_ids) === 0) {
                throw new RuntimeException('削除するコンテンツを選択してください。');
            }

            $delete_rows = array();
            foreach ($delete_ids as $content_id) {
                $row = content_fetch_item($db, $content_id);
                if (!$row) {
                    throw new RuntimeException('削除対象のコンテンツが見つかりません。');
                }
                if ((int)($row['station_id'] ?? 0) === CONTENT_COMMON_STATION_ID) {
                    throw new RuntimeException('共通コンテンツは削除不可。上書き登録');
                }
                if ((int)($row['station_id'] ?? 0) !== $station_id) {
                    throw new RuntimeException('他の停留所のコンテンツは削除できません。');
                }
                if (content_is_selected($db, $content_id)) {
                    throw new RuntimeException('表示中のコンテンツは削除できません。');
                }
                $delete_rows[] = $row;
            }

            $db->beginTransaction();
            /** @noinspection SqlNoDataSourceInspection */
            $delete = $db->prepare('DELETE FROM content_item WHERE content_item_id = :id');
            foreach ($delete_rows as $row) {
                $delete->bindValue(':id', (int)$row['id'], PDO::PARAM_INT);
                $delete->execute();
            }
            $db->commit();

            foreach ($delete_rows as $row) {
                content_delete_media_file((string)$row['content_value']);
            }

            redirect_content_page($station_id, array(
                'type' => 'success',
                'message' => count($delete_rows) . '件のコンテンツを削除しました。',
            ));
        }

        if ($action === 'save') {
            content_save_item(
                $db,
                $stations,
                $station_id,
                $create_target,
                $create_slot_no,
                $create_title,
                $create_content_type,
                $upload_file
            );

            redirect_content_page($station_id, array(
                'type' => 'success',
                'message' => 'コンテンツを保存しました。',
            ));
        }

        throw new RuntimeException('不正な操作です。');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $flash = array(
            'type' => 'error',
            'message' => $e->getMessage(),
        );
        if ($action === 'save') {
            $flash['show_modal'] = true;
            $flash['create_target'] = $create_target;
            $flash['create_slot_no'] = $create_slot_no;
            $flash['create_title'] = $create_title;
            $flash['create_content_type'] = $create_content_type;
        }
        redirect_content_page($station_id, $flash);
    }
}

$all_selected_lookup = array_fill_keys(content_fetch_published_ids($db, $station_id), true);
$content_rows = content_fetch_rows($db, $station_id);
$station_rows = array();
foreach ($content_rows as $row) {
    if ((int)($row['station_id'] ?? 0) === $station_id) {
        $station_rows[] = $row;
    }
}
?>
<!doctype html>
<html lang="ja">
<!--suppress HtmlRequiredTitleElement -->
<head>
    <?php render_app_head('コンテンツ管理'); ?>
</head>
<body class="<?php print(app_body_classes()); ?>">
<div class="adm-page">
    <div class="<?php print(app_page_shell_classes()); ?>">
        <?php
        $shared_header_station_name = $station_name;
        $shared_header_page_title = 'コンテンツ管理';
        $shared_header_station_id = $station_id;
        require __DIR__ . '/header.php';
        ?>
        <?php if ($status_message !== '' && !$show_create_modal) { ?>
            <div class="adm-alert <?php print(h($status_type)); ?> <?php print(app_alert_classes($status_type === 'error' ? 'error' : 'success')); ?>"><?php print(h($status_message)); ?></div>
        <?php } ?>

        <div class="content-manage-actions mb-6 flex flex-wrap gap-2">
            <a class="adm-btn adm-btn-soft <?php print(app_button_classes('soft')); ?>" href="content_setting.php?s=<?php print($station_id); ?>">コンテンツ設定</a>
            <a
                class="adm-btn <?php print(app_button_classes('primary')); ?>"
                href="content_add.php?s=<?php print($station_id); ?>"
                data-open-content-modal
                data-target="<?php print($station_id); ?>"
            >新規登録</a>
        </div>

        <section class="adm-panel content-manage-section <?php print(app_panel_classes('space-y-5')); ?>">
            <div class="content-section-head flex items-center justify-between gap-3">
                <div>
                    <h2 class="adm-card-title text-3xl font-bold tracking-[0.01em] text-slate-950">共通コンテンツ</h2>
                    <p class="adm-card-desc mt-2 text-sm leading-7 text-slate-500">共通コンテンツは削除不可。上書き登録</p>
                </div>
                <div class="content-section-count <?php print(app_badge_classes('brand')); ?>"><?php print(count($common_slot_map)); ?> / <?php print(CONTENT_COMMON_SLOT_MAX); ?></div>
            </div>
            <div class="content-card-grid content-card-grid-common grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <?php for ($slot_no = 1; $slot_no <= CONTENT_COMMON_SLOT_MAX; $slot_no++) { ?>
                    <?php if (isset($common_slot_map[$slot_no])) { $row = $common_slot_map[$slot_no]; ?>
                        <article class="content-card overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_12px_30px_rgba(15,23,42,0.06)]">
                            <div class="content-card-delete flex justify-end px-5 pt-5">
                                <a
                                    class="adm-btn adm-btn-soft <?php print(app_button_classes('soft', 'sm')); ?>"
                                    href="content_add.php?s=<?php print($station_id); ?>&target=0&slot=<?php print($slot_no); ?>"
                                    data-open-content-modal
                                    data-target="0"
                                    data-slot="<?php print($slot_no); ?>"
                                >上書き登録</a>
                            </div>
                            <div
                                class="content-card-thumb<?php if ((string)$row['content_type'] === 'movie') { print(' js-content-preview'); } ?> relative aspect-[16/9] overflow-hidden bg-slate-100"
                                <?php if ((string)$row['content_type'] === 'movie') { ?>
                                    data-preview-type="movie"
                                    data-preview-src="<?php print(h((string)$row['content_value'])); ?>"
                                    data-preview-title="<?php print(h((string)$row['title'])); ?>"
                                <?php } ?>
                            >
                                <?php if ((string)$row['content_type'] === 'movie') { ?>
                                    <video class="h-full w-full object-cover" src="<?php print(h((string)$row['content_value'])); ?>" muted playsinline preload="metadata"></video>
                                    <span class="content-card-play absolute inset-x-0 bottom-4 mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white/90 text-slate-950 shadow-lg">▶</span>
                                <?php } else { ?>
                                    <img class="h-full w-full object-cover" src="<?php print(h((string)$row['content_value'])); ?>" alt="<?php print(h((string)$row['title'])); ?>">
                                <?php } ?>
                            </div>
                            <div class="content-card-body space-y-3 px-5 py-5">
                                <div class="content-card-tags flex flex-wrap gap-2">
                                    <span class="content-card-tag <?php print(app_badge_classes('neutral')); ?>">共通-<?php print($slot_no); ?></span>
                                    <span class="content-card-tag <?php print(app_badge_classes('brand')); ?>"><?php print(h(content_type_label((string)$row['content_type']))); ?></span>
                                </div>
                                <div class="content-card-name text-base font-semibold text-slate-900"><?php print(h((string)$row['title'])); ?></div>
                            </div>
                        </article>
                    <?php } else { ?>
                        <a
                            class="content-card content-card-empty flex aspect-[16/9] flex-col items-center justify-center rounded-[28px] border border-dashed border-slate-200 bg-slate-50 px-6 text-center transition hover:border-blue-200 hover:bg-blue-50"
                            href="content_add.php?s=<?php print($station_id); ?>&target=0&slot=<?php print($slot_no); ?>"
                            data-open-content-modal
                            data-target="0"
                            data-slot="<?php print($slot_no); ?>"
                        >
                            <span class="content-card-empty-thumb <?php print(app_badge_classes('neutral')); ?>">共通-<?php print($slot_no); ?></span>
                            <span class="content-card-empty-name mt-4 text-sm font-medium text-slate-500">新規追加</span>
                        </a>
                    <?php } ?>
                <?php } ?>
            </div>
        </section>

        <section class="adm-panel content-manage-section <?php print(app_panel_classes('space-y-5')); ?>">
            <div class="content-section-head flex items-center justify-between gap-3">
                <div>
                    <h2 class="adm-card-title text-3xl font-bold tracking-[0.01em] text-slate-950"><?php print(h($station_name)); ?></h2>
                    <p class="adm-card-desc mt-2 text-sm leading-7 text-slate-500">停留所ごとに最大<?php print(CONTENT_STATION_MAX); ?>件まで登録できます。</p>
                </div>
                <div class="content-section-count <?php print(app_badge_classes('brand')); ?>"><?php print(count($station_rows)); ?> / <?php print(CONTENT_STATION_MAX); ?></div>
            </div>
            <div class="content-card-grid grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($station_rows as $row) { $row_id = (int)$row['id']; $locked = isset($all_selected_lookup[$row_id]); ?>
                    <article class="content-card <?php if ($locked) { print('is-locked'); } ?> overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_12px_30px_rgba(15,23,42,0.06)] <?php if ($locked) { print('ring-2 ring-blue-200'); } ?>">
                        <form
                            class="content-card-delete flex justify-end px-5 pt-5"
                            method="post"
                            action="<?php print(h($content_page_action)); ?>"
                            data-confirm-message="このコンテンツを削除しますか？"
                            data-confirm-button="削除する"
                            data-confirm-button-class="adm-btn adm-btn-danger"
                        >
                            <input type="hidden" name="s" value="<?php print($station_id); ?>">
                            <input type="hidden" name="action" value="bulk_delete">
                            <input type="hidden" name="delete_ids[]" value="<?php print($row_id); ?>">
                            <button class="content-card-delete-btn <?php print(app_button_classes('danger', 'sm')); ?>" type="submit" <?php if ($locked) { print('disabled'); } ?>>削除</button>
                        </form>
                        <div
                            class="content-card-thumb<?php if ((string)$row['content_type'] === 'movie') { print(' js-content-preview'); } ?> relative aspect-[16/9] overflow-hidden bg-slate-100"
                            <?php if ((string)$row['content_type'] === 'movie') { ?>
                                data-preview-type="movie"
                                data-preview-src="<?php print(h((string)$row['content_value'])); ?>"
                                data-preview-title="<?php print(h((string)$row['title'])); ?>"
                            <?php } ?>
                        >
                            <?php if ((string)$row['content_type'] === 'movie') { ?>
                                <video class="h-full w-full object-cover" src="<?php print(h((string)$row['content_value'])); ?>" muted playsinline preload="metadata"></video>
                                <span class="content-card-play absolute inset-x-0 bottom-4 mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white/90 text-slate-950 shadow-lg">▶</span>
                            <?php } else { ?>
                                <img class="h-full w-full object-cover" src="<?php print(h((string)$row['content_value'])); ?>" alt="<?php print(h((string)$row['title'])); ?>">
                            <?php } ?>
                        </div>
                        <div class="content-card-body space-y-3 px-5 py-5">
                            <div class="content-card-tags flex flex-wrap gap-2">
                                <span class="content-card-tag <?php print(app_badge_classes('neutral')); ?>"><?php print(h(content_type_label((string)$row['content_type']))); ?></span>
                                <?php if ($locked) { ?><span class="content-card-tag active <?php print(app_badge_classes('brand')); ?>">表示中</span><?php } ?>
                            </div>
                            <div class="content-card-name text-base font-semibold text-slate-900"><?php print(h((string)$row['title'])); ?></div>
                        </div>
                    </article>
                <?php } ?>
                <?php for ($i = count($station_rows); $i < CONTENT_STATION_MAX; $i++) { ?>
                    <a
                        class="content-card content-card-empty flex aspect-[16/9] flex-col items-center justify-center rounded-[28px] border border-dashed border-slate-200 bg-slate-50 px-6 text-center transition hover:border-blue-200 hover:bg-blue-50"
                        href="content_add.php?s=<?php print($station_id); ?>&target=<?php print($station_id); ?>"
                        data-open-content-modal
                        data-target="<?php print($station_id); ?>"
                    >
                        <span class="content-card-empty-thumb <?php print(app_badge_classes('neutral')); ?>"><?php print(h($station_name)); ?></span>
                        <span class="content-card-empty-name mt-4 text-sm font-medium text-slate-500">新規追加</span>
                    </a>
                <?php } ?>
            </div>
        </section>

        <div
            class="app-modal <?php print(app_modal_root_classes()); ?>"
            id="contentCreateModal"
            tabindex="-1"
            role="dialog"
            aria-modal="true"
            aria-labelledby="contentCreateModalTitle"
            aria-hidden="true"
            data-show-on-load="<?php print($show_create_modal ? '1' : '0'); ?>"
            hidden
        >
            <div class="app-modal-dialog app-modal-dialog-lg <?php print(app_modal_dialog_classes('lg')); ?>">
                <div class="app-modal-card content-create-modal-card <?php print(app_modal_card_classes()); ?>">
                    <form
                        id="contentCreateForm"
                        class="content-add-form"
                        method="post"
                        action="<?php print(h($content_page_action)); ?>"
                        enctype="multipart/form-data"
                    >
                        <div class="app-modal-header <?php print(app_modal_header_classes()); ?>">
                            <div>
                                <h2 class="app-modal-title text-2xl font-bold text-slate-950" id="contentCreateModalTitle">コンテンツ登録</h2>
                            </div>
                            <button type="button" class="app-modal-close <?php print(app_button_classes('ghost', 'sm', 'rounded-full px-3')); ?>" data-modal-close aria-label="閉じる">×</button>
                        </div>

                        <div class="app-modal-body content-create-modal-body <?php print(app_modal_body_classes()); ?>">
                            <div id="contentCreateStatus">
                                <?php if ($show_create_modal && $status_message !== '') { ?>
                                    <div class="adm-alert <?php print(h($status_type)); ?> <?php print(app_alert_classes($status_type === 'error' ? 'error' : 'success')); ?>"><?php print(h($status_message)); ?></div>
                                <?php } ?>
                            </div>

                            <input type="hidden" name="s" value="<?php print($station_id); ?>">
                            <input type="hidden" name="action" value="save">

                            <div class="content-create-grid grid gap-4 md:grid-cols-2">
                                <div class="adm-field <?php print(app_field_classes()); ?>">
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

                                <div class="adm-field content-create-grid-wide <?php print(app_field_classes('md:col-span-2')); ?>">
                                    <label class="<?php print(app_label_classes()); ?>" for="title">タイトル</label>
                                    <input id="title" class="<?php print(app_input_classes()); ?>" name="title" type="text" maxlength="120" required value="<?php print(h($create_title)); ?>">
                                </div>

                                <div class="adm-field <?php print(app_field_classes()); ?>">
                                    <label class="<?php print(app_label_classes()); ?>" for="contentType">種別</label>
                                    <select id="contentType" class="<?php print(app_select_classes()); ?>" name="content_type">
                                        <option value="image" <?php if ($create_content_type === 'image') { print('selected'); } ?>>画像</option>
                                        <option value="movie" <?php if ($create_content_type === 'movie') { print('selected'); } ?>>動画</option>
                                    </select>
                                </div>

                                <div class="adm-field content-create-grid-wide <?php print(app_field_classes('md:col-span-2')); ?>">
                                    <label class="<?php print(app_label_classes()); ?>" for="contentFile">ファイル</label>
                                    <input id="contentFile" class="<?php print(app_input_classes()); ?>" name="content_file" type="file" accept="<?php print(h($file_accept)); ?>" required>
                                    <div
                                        class="<?php print(app_help_classes()); ?>"
                                        id="fileHelp"
                                        data-image-help="<?php print(h($image_help_text)); ?>"
                                        data-video-help="<?php print(h($video_help_text)); ?>"
                                    ><?php print(h($file_help_text)); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="app-modal-footer app-modal-footer-between <?php print(app_modal_footer_classes(true)); ?>">
                            <button class="adm-btn adm-btn-soft <?php print(app_button_classes('soft')); ?>" type="button" data-modal-close>閉じる</button>
                            <button class="adm-btn adm-btn-pink <?php print(app_button_classes('primary')); ?>" type="submit">保存</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php render_app_scripts(array('js/content_preview.js?v=1.0.0', 'js/content_add.js?v=1.1.0', 'js/content_manage.js?v=1.0.1')); ?>
</body>
</html>
