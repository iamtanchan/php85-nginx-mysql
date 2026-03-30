<?php
require_once __DIR__ . '/lib/admin_bootstrap.php';

$mode = isset($_GET['m']) ? $_GET['m'] : 0;
$station_id = isset($_GET['id']) ? $_GET['id'] : 1;
$login = isset($_SESSION['login']) ? $_SESSION['login'] : '';
$row_count = 2;

$sql = 'SELECT ship_id AS id, name, name_e FROM ship';
$stt = $db->prepare($sql);
$stt->execute();
$ships = $stt->fetchAll();

$sql = 'SELECT station_id AS id, name, name_e FROM station';
$stt = $db->prepare($sql);
$stt->execute();
$stations = $stt->fetchAll();

$display_language_settings = fetch_station_display_language_settings($db, (int)$station_id);
$show_english = (bool)$display_language_settings['english_enabled'];
if (isset($_GET['show_english'])) {
    $show_english = (string)$_GET['show_english'] === '1';
}

$visible_messages = fetch_visible_station_message_rows($db, (int)$station_id);
$message = count($visible_messages) > 0 ? (string)$visible_messages[0]['message'] : '';

function getShip($ships, $no)
{
    $ret = '';
    foreach ($ships as $row) {
        if ($row['id'] == $no) {
            $ret = $row['name'] . '<br>' . $row['name_e'];
            break;
        }
    }
    return $ret;
}

function getStation($stations, $no)
{
    $ret = '';
    foreach ($stations as $row) {
        if ($row['id'] == $no) {
            $ret = $row['name'] . '<br>' . $row['name_e'];
            break;
        }
    }
    return $ret;
}

function getStationNames($stations, $no)
{
    $ret = array(
        'jp' => '',
        'en' => '',
    );
    foreach ($stations as $row) {
        if ($row['id'] == $no) {
            $ret['jp'] = (string)$row['name'];
            $ret['en'] = (string)$row['name_e'];
            break;
        }
    }
    return $ret;
}

$station_names = getStationNames($stations, $station_id);
$station_name_jp = $station_names['jp'];
$station_name_en = $station_names['en'];
?>
<!doctype html>
<html lang="ja">

<head>
    <?php render_app_head('時刻表表示システム', array(
        'jquery' => true,
    )); ?>
</head>

<body class="<?php print(app_body_classes('display')); ?>">
    <div id="user" hidden><?php print($login); ?></div>
    <div id="station" hidden><?php print($station_id); ?></div>
    <div id="mode" hidden><?php print($mode); ?></div>
    <div id="english_swap_enabled" hidden><?php print($show_english ? '1' : '0'); ?></div>

    <div id="displayShell" class="display-shell mx-auto flex min-h-screen w-full max-w-[1920px] flex-col gap-5 px-5 py-5 transition-opacity duration-300 lg:px-8 lg:py-8">
        <div class="display-header flex flex-col gap-4 rounded-[32px] border border-white/12 bg-white/8 px-6 py-6 shadow-[0_24px_80px_rgba(15,23,42,0.24)] backdrop-blur-xl lg:flex-row lg:items-end lg:justify-between">
            <div
                id="displayTitle"
                class="display-title text-[clamp(2rem,3.6vw,4.75rem)] font-extrabold leading-[0.95] tracking-[0.01em] text-white"
                data-station-jp="<?php print(h($station_name_jp)); ?>"
                data-station-en="<?php print(h($station_name_en)); ?>">時刻表　<?php print(h($station_name_jp)); ?>発</div>
            <div class="clock-block rounded-[28px] border border-white/12 bg-slate-950/40 px-5 py-4 text-right">
                <div id="clockLabel" class="clock-label text-xs font-semibold uppercase tracking-[0.24em] text-white/60">現在時刻</div>
                <div id="current_time" class="clock-value mt-2 text-[clamp(2.25rem,4vw,4.25rem)] font-bold tracking-[0.08em] text-white">--:--</div>
            </div>
        </div>

        <div class="display-main flex-1">
            <div class="display-panel table-panel active" id="tableStage">
                <div class="table-wrap overflow-hidden rounded-[34px] border border-white/12 bg-slate-950/45 shadow-[0_30px_90px_rgba(15,23,42,0.32)] backdrop-blur-xl">
                    <table class="timetable min-w-full table-fixed" id="timetable" style="--display-row-count: <?php print((int)$row_count); ?>;">
                        <thead>
                            <tr>
                                <th id="headerTime" class="cols bg-white/10 px-5 py-4 text-left text-sm font-semibold uppercase tracking-[0.18em] text-white/70">時刻</th>
                                <th id="headerShip" class="cols4 bg-white/10 px-5 py-4 text-left text-sm font-semibold uppercase tracking-[0.18em] text-white/70">艇名</th>
                                <th id="headerDestination" class="cols2 bg-white/10 px-5 py-4 text-left text-sm font-semibold uppercase tracking-[0.18em] text-white/70">行き先</th>
                                <th id="headerStatus" class="cols1 bg-white/10 px-5 py-4 text-left text-sm font-semibold uppercase tracking-[0.18em] text-white/70">乗船案内</th>
                            </tr>
                        </thead>
                        <tbody id="item">
                            <?php for ($cnt = 0; $cnt < $row_count; $cnt++) { ?>
                                <tr id="table_col" class="odd:bg-white/0 even:bg-white/[0.03]">
                                    <td id="col_time" class="border-b border-white/8 px-5 py-6 align-top">
                                        <p class="col_time text-[clamp(2.1rem,4vw,4rem)] font-bold tracking-[0.06em] text-white"></p>
                                    </td>
                                    <td id="col_ship" class="col_ship border-b border-white/8 px-5 py-6 text-[clamp(1.15rem,2vw,2rem)] font-semibold leading-tight text-white"></td>
                                    <td id="col_destination" class="col_destination border-b border-white/8 px-5 py-6 align-top">
                                        <div class="col_badge_float mb-3 w-fit rounded-full bg-blue-500/15 px-3 py-1 text-sm font-semibold text-blue-100" style="display:none"></div>
                                        <p class="destination text-[clamp(1.2rem,2.2vw,2.3rem)] font-semibold leading-tight text-white"></p>
                                        <p class="destinatione mt-2 text-lg leading-7 text-white/55"></p>
                                    </td>
                                    <td id="col_status" class="col_status border-b border-white/8 px-5 py-6 align-top text-[clamp(1rem,1.8vw,1.55rem)] font-semibold text-white"></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <div class="ticker-wrap overflow-hidden rounded-full border border-white/10 bg-slate-950/55 px-6 py-4 shadow-[0_18px_40px_rgba(15,23,42,0.24)]">
            <div id="dsp_message" class="whitespace-nowrap text-2xl font-semibold tracking-[0.02em] text-white"><?php print(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')); ?></div>
        </div>

        <?php if ($mode == 1) { ?>
            <div class="footbar flex justify-end">
                <button id="btnRtn" class="rounded-full border border-white/15 bg-white/8 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/12" type="button" value="">もどる</button>
            </div>
        <?php } ?>
    </div>

    <div class="display-panel content-panel fixed inset-0 z-40 hidden" id="contentStagePanel" hidden>
        <section class="content-stage relative h-full w-full overflow-hidden bg-black" id="contentStage" aria-label="放映コンテンツ">
            <div class="content-stage-empty absolute inset-0 z-10 flex items-center justify-center bg-black text-xl font-medium text-white/55" id="contentStageEmpty">表示するコンテンツはありません</div>
            <div class="content-stage-slides relative h-full w-full overflow-hidden" id="contentStageSlides"></div>
            <div class="content-stage-indicator hidden" id="contentStageIndicator"></div>
        </section>
    </div>

    <div class="display-panel notify-panel fixed inset-0 z-50 hidden" id="notifyPanel" hidden>
        <section class="notify-stage h-full w-full overflow-hidden bg-slate-950" id="notifyStage" aria-label="通知表示">
            <img id="notifyImage" class="notify-stage-image h-full w-full object-cover" src="" alt="">
        </section>
    </div>

    <div class="display-panel guidance-panel fixed inset-0 z-40 hidden" id="guidancePanel" hidden>
        <section class="guidance-stage h-full overflow-hidden bg-slate-950" id="guidanceStage" aria-label="案内表示">
            <video id="guidanceVideo" class="guidance-stage-video h-full w-full bg-black object-cover" muted playsinline preload="auto"></video>
        </section>
    </div>

    <audio id="changesound" preload="auto" muted="muted">
        <source src="img/sound.mp3" type="audio/mp3">
    </audio>
    <?php render_app_scripts(array('js/display.js?v=2.6.3')); ?>
</body>

</html>