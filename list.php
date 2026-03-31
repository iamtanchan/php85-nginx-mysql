<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/admin_bootstrap.php';

$login = require_login($db);

$sql = 'SELECT ship_id AS id, name, name_e FROM ship ORDER BY ship_id';
$stt = $db->prepare($sql);
$stt->execute();
$ships = $stt->fetchAll(PDO::FETCH_ASSOC);

$sql = 'SELECT station_id AS id, name, name_e FROM station ORDER BY station_id';
$stt = $db->prepare($sql);
$stt->execute();
$stations = $stt->fetchAll(PDO::FETCH_ASSOC);

$sql = 'SELECT destination_id AS id, name, name_e FROM destination ORDER BY destination_id';
$stt = $db->prepare($sql);
$stt->execute();
$destinations = $stt->fetchAll(PDO::FETCH_ASSOC);

$requested_station_id = isset($_GET['s']) ? (int)$_GET['s'] : 0;
$station_id = 0;
$station_name = '';
foreach ($stations as $station) {
    $id = (int)$station['id'];
    $name = trim((string)$station['name']);
    if ($station_id === 0 && $id > 0 && $name !== '' && $name !== '　') {
        $station_id = $id;
        $station_name = $name;
    }
    if ($requested_station_id > 0 && $id === $requested_station_id) {
        $station_id = $id;
        $station_name = $name;
        break;
    }
}

$destination_rows = array_values(array_filter($destinations, static function (array $row): bool {
    $name = trim((string)($row['name'] ?? ''));
    return $name !== '' && $name !== '　';
}));

$ship_rows = array_values(array_filter($ships, static function (array $row): bool {
    $name = trim((string)($row['name'] ?? ''));
    return $name !== '' && $name !== '　';
}));
?>
<!doctype html>
<html lang="ja">

<head>
    <?php render_app_head('行先・艇名管理', array('jquery' => true)); ?>
</head>

<body class="<?php print(app_body_classes()); ?>">
    <div id="user" hidden><?php print(h($login)); ?></div>

    <div class="adm-page">
        <div class="<?php print(app_page_shell_classes()); ?>">
            <?php
            $shared_header_station_name = $station_name;
            $shared_header_page_title = '行先・艇名管理';
            $shared_header_station_id = $station_id;
            require __DIR__ . '/header.php';
            ?>

            <div class="list-toolbar mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="list-toolbar-copy space-y-2">
                    <h2 class="text-3xl font-bold tracking-[0.01em] text-slate-950">マスタ一覧</h2>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button id="btnNew" class="btnNew <?php print(app_button_classes('secondary')); ?>" type="button">+ 行先を追加</button>
                    <button id="btnShipNew" class="btnNew <?php print(app_button_classes('primary')); ?>" type="button">+ 艇名を追加</button>
                </div>
            </div>

            <div class="list-page-grid grid gap-5 xl:grid-cols-2">
                <section class="list-panel <?php print(app_panel_classes('space-y-5')); ?>">
                    <div class="list-panel-head flex items-center justify-between gap-3">
                        <div>
                            <h3 class="list-table-title text-2xl font-bold tracking-[0.01em] text-slate-950">行先管理</h3>
                        </div>
                        <span class="list-summary-badge <?php print(app_badge_classes('brand')); ?>"><?php print(count($destination_rows)); ?>件</span>
                    </div>

                    <?php if (count($destination_rows) === 0) { ?>
                        <div class="list-empty rounded-[24px] border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">行先はまだ登録されていません。</div>
                    <?php } else { ?>
                        <div class="<?php print(app_table_frame_classes('overflow-x-auto')); ?>">
                            <table class="list-table <?php print(app_table_classes()); ?>">
                                <thead>
                                    <tr>
                                        <th class="bg-slate-950/95 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">行先 / 日本語</th>
                                        <th class="bg-slate-950/95 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">English</th>
                                        <th class="bg-slate-950/95 px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($destination_rows as $row) { ?>
                                        <tr class="odd:bg-white even:bg-slate-50/60">
                                            <td class="station_name border-b border-slate-200/70 px-4 py-4 font-semibold text-slate-800"><?php print(h(trim((string)$row['name']))); ?></td>
                                            <td class="station_name_e border-b border-slate-200/70 px-4 py-4 text-slate-500"><?php print(h(trim((string)$row['name_e']))); ?></td>
                                            <td class="border-b border-slate-200/70 px-4 py-4 text-right">
                                                <div class="flex flex-wrap justify-end gap-2">
                                                    <button class="btnEdit <?php print(app_button_classes('secondary', 'sm')); ?>" type="button" value="<?php print((int)$row['id']); ?>">編集</button>
                                                    <button class="btnDel <?php print(app_button_classes('danger', 'sm')); ?>" type="button" value="<?php print((int)$row['id']); ?>">削除</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                </section>

                <section class="list-panel <?php print(app_panel_classes('space-y-5')); ?>">
                    <div class="list-panel-head flex items-center justify-between gap-3">
                        <div>
                            <h3 class="list-table-title text-2xl font-bold tracking-[0.01em] text-slate-950">艇名管理</h3>
                        </div>
                        <span class="list-summary-badge <?php print(app_badge_classes('brand')); ?>"><?php print(count($ship_rows)); ?>件</span>
                    </div>

                    <?php if (count($ship_rows) === 0) { ?>
                        <div class="list-empty rounded-[24px] border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">艇名はまだ登録されていません。</div>
                    <?php } else { ?>
                        <div class="<?php print(app_table_frame_classes('overflow-x-auto')); ?>">
                            <table class="list-table <?php print(app_table_classes()); ?>">
                                <thead>
                                    <tr>
                                        <th class="bg-slate-950/95 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">艇名 / 日本語</th>
                                        <th class="bg-slate-950/95 px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">English</th>
                                        <th class="bg-slate-950/95 px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em] text-slate-200">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ship_rows as $row) { ?>
                                        <tr class="odd:bg-white even:bg-slate-50/60">
                                            <td class="ship_name border-b border-slate-200/70 px-4 py-4 font-semibold text-slate-800"><?php print(h((string)$row['name'])); ?></td>
                                            <td class="ship_name_e border-b border-slate-200/70 px-4 py-4 text-slate-500"><?php print(h((string)$row['name_e'])); ?></td>
                                            <td class="border-b border-slate-200/70 px-4 py-4 text-right">
                                                <div class="flex flex-wrap justify-end gap-2">
                                                    <button class="btnShipEdit <?php print(app_button_classes('secondary', 'sm')); ?>" type="button" value="<?php print((int)$row['id']); ?>">編集</button>
                                                    <button class="btnShipDel <?php print(app_button_classes('danger', 'sm')); ?>" type="button" value="<?php print((int)$row['id']); ?>">削除</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                </section>
            </div>

            <div class="app-credit mt-6 text-sm text-slate-500">ログイン中: <?php print(h($login)); ?></div>
        </div>
    </div>

    <div class="app-modal <?php print(app_modal_root_classes()); ?>" id="destinationModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="destinationModalTitle" aria-hidden="true" hidden>
        <div class="app-modal-dialog <?php print(app_modal_dialog_classes('md')); ?>">
            <div class="app-modal-card <?php print(app_modal_card_classes()); ?>">
                <div class="app-modal-header <?php print(app_modal_header_classes()); ?>">
                    <div>
                        <h2 class="app-modal-title text-2xl font-bold text-slate-950" id="destinationModalTitle">行先を追加</h2>
                        <p class="app-modal-description mb-0 mt-2 text-sm text-slate-500">行先名の日本語と英語を入力します。</p>
                    </div>
                    <button type="button" class="app-modal-close <?php print(app_button_classes('ghost', 'sm', 'rounded-full px-3')); ?>" data-modal-close aria-label="閉じる">×</button>
                </div>
                <div class="app-modal-body <?php print(app_modal_body_classes()); ?>">
                    <div class="grid gap-3">
                        <div class="<?php print(app_field_classes()); ?>">
                            <label class="<?php print(app_label_classes()); ?>" for="inp_name">日本語</label>
                            <input type="text" id="inp_name" class="<?php print(app_input_classes('min-h-[3.25rem]')); ?>">
                        </div>
                        <div class="<?php print(app_field_classes()); ?>">
                            <label class="<?php print(app_label_classes()); ?>" for="inp_name_e">英語</label>
                            <input type="text" id="inp_name_e" class="<?php print(app_input_classes('min-h-[3.25rem]')); ?>">
                        </div>
                    </div>
                </div>
                <div class="app-modal-footer app-modal-footer-between <?php print(app_modal_footer_classes(true)); ?>">
                    <button class="adm-btn adm-btn-soft <?php print(app_button_classes('soft')); ?>" type="button" id="btnCancel" data-modal-close>キャンセル</button>
                    <button class="adm-btn adm-btn-pink <?php print(app_button_classes('primary')); ?>" type="button" id="btnReg">保存</button>
                </div>
            </div>
        </div>
    </div>

    <div class="app-modal <?php print(app_modal_root_classes()); ?>" id="shipModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="shipModalTitle" aria-hidden="true" hidden>
        <div class="app-modal-dialog <?php print(app_modal_dialog_classes('md')); ?>">
            <div class="app-modal-card <?php print(app_modal_card_classes()); ?>">
                <div class="app-modal-header <?php print(app_modal_header_classes()); ?>">
                    <div>
                        <h2 class="app-modal-title text-2xl font-bold text-slate-950" id="shipModalTitle">艇名を追加</h2>
                        <p class="app-modal-description mb-0 mt-2 text-sm text-slate-500">艇名の日本語と英語を入力します。</p>
                    </div>
                    <button type="button" class="app-modal-close <?php print(app_button_classes('ghost', 'sm', 'rounded-full px-3')); ?>" data-modal-close aria-label="閉じる">×</button>
                </div>
                <div class="app-modal-body <?php print(app_modal_body_classes()); ?>">
                    <div class="grid gap-3">
                        <div class="<?php print(app_field_classes()); ?>">
                            <label class="<?php print(app_label_classes()); ?>" for="inp_ship_name">日本語</label>
                            <input type="text" id="inp_ship_name" class="<?php print(app_input_classes('min-h-[3.25rem]')); ?>">
                        </div>
                        <div class="<?php print(app_field_classes()); ?>">
                            <label class="<?php print(app_label_classes()); ?>" for="inp_ship_name_e">英語</label>
                            <input type="text" id="inp_ship_name_e" class="<?php print(app_input_classes('min-h-[3.25rem]')); ?>">
                        </div>
                    </div>
                </div>
                <div class="app-modal-footer app-modal-footer-between <?php print(app_modal_footer_classes(true)); ?>">
                    <button class="adm-btn adm-btn-soft <?php print(app_button_classes('soft')); ?>" type="button" id="btnShipCancel" data-modal-close>キャンセル</button>
                    <button class="adm-btn adm-btn-pink <?php print(app_button_classes('primary')); ?>" type="button" id="btnShipReg">保存</button>
                </div>
            </div>
        </div>
    </div>

    <?php render_app_scripts(array('js/list.js?v=2.0.0')); ?>
</body>

</html>