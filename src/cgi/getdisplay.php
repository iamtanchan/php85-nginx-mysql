<?php
require_once __DIR__ . '/../lib/admin_bootstrap.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$mode = isset($_POST['mode']) ? (int)$_POST['mode'] : 0;
if ($id <= 0) {
    echo json_encode(array(), JSON_UNESCAPED_UNICODE);
    exit;
}

function getMapById(array $rows)
{
    $map = array();
    foreach ($rows as $row) {
        $map[(int)$row['id']] = $row;
    }
    return $map;
}

function getBadgeMap(PDO $db)
{
    $map = array();
    $stt = $db->prepare('SELECT badge_id AS id, label, label_e FROM badge ORDER BY badge_id');
    $stt->execute();
    foreach ($stt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int)$row['id']] = array(
            'label' => trim((string)$row['label']),
            'label_e' => trim((string)($row['label_e'] ?? '')),
        );
    }
    return $map;
}

try {
    $now = date('H:i:s');
    $day_bounds = current_day_bounds();
    $rows = array();
    $sql = 'SELECT timetable_id, departure_time, ship_id, destination_id, badge_id, ontime, offtime
            FROM timetable
            WHERE station_id = :id
              AND created_at >= :day_start
              AND created_at < :day_end
              AND departure_time >= :ntime
            ORDER BY departure_time ASC
            LIMIT 10';
    $stt = $db->prepare($sql);
    $stt->bindValue(':id', $id, PDO::PARAM_INT);
    $stt->bindValue(':day_start', $day_bounds['start']);
    $stt->bindValue(':day_end', $day_bounds['end']);
    $stt->bindValue(':ntime', $now);
    $stt->execute();
    $rows = $stt->fetchAll(PDO::FETCH_ASSOC);

    $sttShip = $db->prepare('SELECT ship_id AS id, name, name_e FROM ship');
    $sttShip->execute();
    $ship_map = getMapById($sttShip->fetchAll(PDO::FETCH_ASSOC));

    $sttDestination = $db->prepare('SELECT destination_id AS id, name, name_e FROM destination');
    $sttDestination->execute();
    $destination_map = getMapById($sttDestination->fetchAll(PDO::FETCH_ASSOC));
    $badge_map = getBadgeMap($db);

    $result = array();
    $now_ts = time();
    $today = date('Y-m-d');

    foreach ($rows as $row) {
        $time_raw = (string)$row['departure_time'];
        $departure_ts = strtotime($today . ' ' . $time_raw);
        if ($departure_ts === false) {
            continue;
        }

        $ontime = (int)$row['ontime'];
        if (!in_array($ontime, array(15, 10, 5), true)) {
            $ontime = 10;
        }

        $offtime = (int)$row['offtime'];
        if ($offtime < 0) {
            $offtime = 0;
        } elseif ($offtime > 10) {
            $offtime = 10;
        }

        $boarding_start_ts = $departure_ts - ($ontime * 60);
        $blink_start_ts = $departure_ts - ($offtime * 60);
        $show_boarding = ($now_ts >= $boarding_start_ts);
        $blink_boarding = ($now_ts >= $blink_start_ts);

        $boarding_start_hm = date('H:i', $boarding_start_ts);
        $blink_start_hm = date('H:i', $blink_start_ts);
        $minutes_to_boarding = (int)floor(($boarding_start_ts - $now_ts) / 60);
        $minutes_to_departure = (int)floor(($departure_ts - $now_ts) / 60);

        $ship_id = (int)$row['ship_id'];
        $destination_id = (int)$row['destination_id'];
        $ship = isset($ship_map[$ship_id]) ? (string)$ship_map[$ship_id]['name'] : '';
        $shipe = isset($ship_map[$ship_id]) ? (string)$ship_map[$ship_id]['name_e'] : '';
        $station = isset($destination_map[$destination_id]) ? (string)$destination_map[$destination_id]['name'] : '';
        $statione = isset($destination_map[$destination_id]) ? (string)$destination_map[$destination_id]['name_e'] : '';
        $badge_id = (int)$row['badge_id'];
        $badge_label = '';
        $badge_label_e = '';
        if ($badge_id > 0) {
            $badge_label = isset($badge_map[$badge_id]) ? (string)$badge_map[$badge_id]['label'] : ('Badge ' . $badge_id);
            $badge_label_e = isset($badge_map[$badge_id]) ? (string)$badge_map[$badge_id]['label_e'] : ('Badge ' . $badge_id);
        }

        $result[] = array(
            'time' => $time_raw,
            'ship' => $ship,
            'shipe' => $shipe,
            'station' => $station,
            'statione' => $statione,
            'soldout' => '',
            'status' => '',
            'detail' => '',
            'badge_id' => (string)$badge_id,
            'badge_label' => $badge_label,
            'badge_label_e' => $badge_label_e,
            'boarding_text' => $show_boarding ? '乗船案内中' : '',
            'boarding_blink' => $blink_boarding ? '1' : '0',
            'boarding_start_hm' => $boarding_start_hm,
            'blink_start_hm' => $blink_start_hm,
            'minutes_to_boarding' => (string)$minutes_to_boarding,
            'minutes_to_departure' => (string)$minutes_to_departure
        );
    }

    if (count($result) === 0) {
        $result[] = array(
            'time' => '',
            'ship' => '',
            'shipe' => '',
            'station' => '',
            'statione' => '',
            'soldout' => '',
            'status' => '',
            'detail' => '',
            'badge_id' => '',
            'badge_label' => '',
            'badge_label_e' => '',
            'boarding_text' => '',
            'boarding_blink' => '0',
            'boarding_start_hm' => '',
            'blink_start_hm' => '',
            'minutes_to_boarding' => '',
            'minutes_to_departure' => ''
        );
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    die('error' . $e->getMessage());
}
