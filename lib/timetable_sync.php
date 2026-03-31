<?php

function timetable_sync_signature(array $row): string
{
    return implode('|', array(
        (string)($row['departure_time'] ?? ''),
        (int)($row['ship_id'] ?? 0),
        (int)($row['destination_id'] ?? 0),
    ));
}

function sync_timetable_from_season(PDO $db, int $station_id, int $season_id, array $overrides = array()): int
{
    if ($station_id <= 0 || $season_id <= 0) {
        return 0;
    }

    $day_bounds = current_day_bounds();

    $schedule_stt = $db->prepare(
        'SELECT schedule_id, departure_time, ship_id, destination_id
         FROM schedule
         WHERE station_id = :station_id AND season_id = :season_id
         ORDER BY priority ASC, departure_time ASC, schedule_id ASC'
    );
    $schedule_stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $schedule_stt->bindValue(':season_id', $season_id, PDO::PARAM_INT);
    $schedule_stt->execute();
    $schedule_rows = $schedule_stt->fetchAll(PDO::FETCH_ASSOC);

    $existing_stt = $db->prepare(
        'SELECT departure_time, ship_id, destination_id, badge_id, ontime, offtime
         FROM timetable
         WHERE station_id = :station_id
           AND created_at >= :day_start
           AND created_at < :day_end
         ORDER BY departure_time ASC, timetable_id ASC'
    );
    $existing_stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
    $existing_stt->bindValue(':day_start', $day_bounds['start']);
    $existing_stt->bindValue(':day_end', $day_bounds['end']);
    $existing_stt->execute();
    $existing_rows = $existing_stt->fetchAll(PDO::FETCH_ASSOC);

    $preserved_values = array();
    foreach ($existing_rows as $existing_row) {
        $signature = timetable_sync_signature($existing_row);
        if (!isset($preserved_values[$signature])) {
            $preserved_values[$signature] = array();
        }
        $preserved_values[$signature][] = array(
            'badge_id' => (int)($existing_row['badge_id'] ?? 0),
            'ontime' => (int)($existing_row['ontime'] ?? 10),
            'offtime' => (int)($existing_row['offtime'] ?? 5),
        );
    }

    $db->beginTransaction();
    try {
        $delete_stt = $db->prepare(
            'DELETE FROM timetable
             WHERE station_id = :station_id
               AND created_at >= :day_start
               AND created_at < :day_end'
        );
        $delete_stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
        $delete_stt->bindValue(':day_start', $day_bounds['start']);
        $delete_stt->bindValue(':day_end', $day_bounds['end']);
        $delete_stt->execute();

        if (count($schedule_rows) > 0) {
            $insert_stt = $db->prepare(
                'INSERT INTO timetable (
                    station_id,
                    departure_time,
                    ship_id,
                    destination_id,
                    badge_id,
                    ontime,
                    offtime
                ) VALUES (
                    :station_id,
                    :departure_time,
                    :ship_id,
                    :destination_id,
                    :badge_id,
                    :ontime,
                    :offtime
                )'
            );

            foreach ($schedule_rows as $schedule_row) {
                $schedule_id = (int)($schedule_row['schedule_id'] ?? 0);
                $signature = timetable_sync_signature($schedule_row);
                $values = array(
                    'badge_id' => 0,
                    'ontime' => 10,
                    'offtime' => 5,
                );

                if (isset($preserved_values[$signature]) && count($preserved_values[$signature]) > 0) {
                    $values = array_shift($preserved_values[$signature]);
                }
                if ($schedule_id > 0 && isset($overrides[$schedule_id]) && is_array($overrides[$schedule_id])) {
                    $values = array_merge($values, $overrides[$schedule_id]);
                }

                $insert_stt->bindValue(':station_id', $station_id, PDO::PARAM_INT);
                $insert_stt->bindValue(':departure_time', (string)$schedule_row['departure_time']);
                $insert_stt->bindValue(':ship_id', (int)$schedule_row['ship_id'], PDO::PARAM_INT);
                $insert_stt->bindValue(':destination_id', (int)$schedule_row['destination_id'], PDO::PARAM_INT);
                $insert_stt->bindValue(':badge_id', (int)$values['badge_id'], PDO::PARAM_INT);
                $insert_stt->bindValue(':ontime', (int)$values['ontime'], PDO::PARAM_INT);
                $insert_stt->bindValue(':offtime', (int)$values['offtime'], PDO::PARAM_INT);
                $insert_stt->execute();
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    return count($schedule_rows);
}
