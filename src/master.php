<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/admin_bootstrap.php';

$login = require_login($db);
$stations = get_active_stations(get_stations($db));
$requested_station_id = isset($_REQUEST['s']) ? (int)$_REQUEST['s'] : 0;
$station_id = resolve_station_id($stations, $requested_station_id);
$station_name = get_station_name($stations, $station_id);
$message = isset($_GET['msg']) ? trim((string)$_GET['msg']) : '';
$action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'toggle_display_english') {
    $current_setting = fetch_station_display_language_settings($db, $station_id);
    $next_enabled = !((bool)$current_setting['english_enabled']);
    update_station_display_language_settings($db, $station_id, $next_enabled);

    redirect_with_params('master.php', array(
        's' => $station_id,
        'msg' => $next_enabled ? '英語表示を ON にしました。' : '英語表示を OFF にしました。',
    ));
}

$display_language_settings = fetch_station_display_language_settings($db, $station_id);
$display_english_enabled = (bool)$display_language_settings['english_enabled'];

function build_station_link(string $path, int $station_id): string
{
    return $path . '?s=' . $station_id;
}

$dashboard_cards = array(
    array(
        'title' => '時刻表設定',
        'description' => '運航行の追加、出発時刻、艇名、行先、案内バッヂを更新します。',
        'href' => build_station_link('timetable.php', $station_id),
        'accent' => true,
    ),
    array(
        'title' => '行先・艇名管理',
        'description' => '行先マスタと艇名マスタの追加・編集・削除を行います。',
        'href' => 'list.php?s=' . $station_id,
        'accent' => false,
    ),
    array(
        'title' => 'メッセージ設定',
        'description' => 'テロップ表示するメッセージと流れる速度を管理します。',
        'href' => build_station_link('message.php', $station_id),
        'accent' => false,
    ),
    array(
        'title' => 'ダイヤ設定',
        'description' => '停留所ごとのダイヤ一覧、行編集、ダイヤ追加を管理します。',
        'href' => build_station_link('schedule.php', $station_id),
        'accent' => false,
    ),
    array(
        'title' => 'ダイヤ期間',
        'description' => '季節運航や特別運航にあわせた期間設定を登録します。',
        'href' => build_station_link('season.php', $station_id),
        'accent' => false,
    ),
    array(
        'title' => 'コンテンツ管理',
        'description' => '待機画面・表示画面で利用する画像や動画を管理します。',
        'href' => build_station_link('content.php', $station_id),
        'accent' => false,
    ),
    array(
        'title' => '通知表示設定',
        'description' => '運休・ダイヤ検討中・営業終了の共通静止画と表示切替を管理します。',
        'href' => build_station_link('notify.php', $station_id),
        'accent' => false,
    ),
    array(
        'title' => '案内動画設定',
        'description' => '出港前に自動再生する「乗船前の注意事項」「ルート案内」の動画と再生タイミングを設定します。',
        'href' => build_station_link('guidance.php', $station_id),
        'accent' => false,
    ),
);
?>
<!doctype html>
<html lang="ja">
<head>
    <?php render_app_head('マスタ画面'); ?>
</head>

<body class="<?php print(app_body_classes()); ?>">
    <div class="adm-page">
        <div class="<?php print(app_page_shell_classes()); ?>">
            <div class="adm-header <?php print(app_header_classes()); ?>">
                <div class="adm-title-wrap relative z-[1] space-y-3">
                    <div class="<?php print(app_badge_classes('brand')); ?>">Operations Hub</div>
                    <h1 class="adm-title text-[clamp(2.1rem,3vw,3.4rem)] font-extrabold tracking-[0.01em] text-slate-950">マスタ画面</h1>
                </div>
                <a class="adm-pill-link <?php print(app_button_classes('secondary')); ?>" href="logout.php">ログアウト</a>
            </div>

            <?php if ($message !== '') { ?>
                <div class="adm-alert success <?php print(app_alert_classes('success')); ?>" role="alert"><?php print(h($message)); ?></div>
            <?php } ?>

            <section class="adm-panel mb-4 <?php print(app_panel_classes()); ?>">
                <form class="adm-toolbar flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between" method="get" action="master.php">
                    <div class="<?php print(app_field_classes('max-w-xl')); ?>">
                        <label class="<?php print(app_label_classes()); ?>" for="stationSelect">停留所ターミナル</label>
                        <select id="stationSelect" class="<?php print(app_select_classes()); ?>" name="s">
                            <?php foreach ($stations as $station) { ?>
                                <option value="<?php print((int)$station['id']); ?>" <?php if ((int)$station['id'] === $station_id) {
                                                                                            print('selected');
                                                                                        } ?>>
                                    <?php print(h((string)$station['name'])); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <button class="adm-btn adm-btn-soft <?php print(app_button_classes('soft')); ?>" type="submit">停留所を切替</button>
                </form>
            </section>

            <section class="adm-panel mb-4 <?php print(app_panel_classes()); ?>">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="adm-card-title mb-1 text-2xl font-bold tracking-[0.01em] text-slate-950">英語表示切替</h2>
                        <p class="adm-card-desc mb-0 max-w-2xl text-sm leading-7 text-slate-500">
                            ON にすると、表示ページは 1 分ごとに日本語と英語を切り替えます。
                        </p>
                    </div>
                    <div class="message-visible-cell">
                        <form method="post" action="master.php">
                            <input type="hidden" name="s" value="<?php print($station_id); ?>">
                            <button
                                class="message-toggle <?php print($display_english_enabled ? 'is-on' : 'is-off'); ?> <?php print(app_toggle_button_classes($display_english_enabled)); ?>"
                                type="submit"
                                name="action"
                                value="toggle_display_english"
                                aria-label="<?php print($display_english_enabled ? 'OFF に切り替え' : 'ON に切り替え'); ?>"
                            >
                                <span class="message-toggle-switch <?php print(app_toggle_switch_classes($display_english_enabled)); ?>" aria-hidden="true">
                                    <span class="block <?php print(app_toggle_knob_classes($display_english_enabled)); ?>"></span>
                                </span>
                                <span class="message-toggle-label text-[11px]"><?php print($display_english_enabled ? 'ON' : 'OFF'); ?></span>
                            </button>
                        </form>
                    </div>
                </div>
            </section>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($dashboard_cards as $card) { ?>
                    <div>
                        <section class="adm-card h-full <?php print(app_panel_classes('flex h-full flex-col gap-5')); ?>">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h2 class="adm-card-title text-2xl font-bold tracking-[0.01em] text-slate-950"><?php print(h($card['title'])); ?></h2>
                                    <p class="adm-card-desc mt-3 text-sm leading-7 text-slate-500"><?php print(h($card['description'])); ?></p>
                                </div>
                            </div>
                            <div class="adm-card-actions mt-auto">
                                <a class="adm-link-btn<?php if ($card['accent']) { print(' accent'); } ?> <?php print(app_button_classes($card['accent'] ? 'primary' : 'secondary')); ?>" href="<?php print(h($card['href'])); ?>">開く</a>
                            </div>
                        </section>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php render_app_scripts(); ?>
</body>
</html>
