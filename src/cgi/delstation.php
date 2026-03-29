<?php
require_once __DIR__ . '/../lib/admin_bootstrap.php';
$id = $_POST['id'];
try{
    $db->beginTransaction();
    $stt = $db->prepare('DELETE FROM destination WHERE destination_id = :id');
    $stt->bindValue(':id', $id);
    $stt->execute();

    $sql= 'SELECT * FROM timetable WHERE destination_id = :id';
    $stt = $db->prepare($sql);
    $stt->bindValue(':id', $id);
    $stt->execute();
    while ($row = $stt->fetch()) {
        $station_id = $row['station_id'];
        $time = $row['time'];
        $st1 = $row['destination_id'];
        $st2 = $row['destination2_id'];

        error_log("delstation time=".$time.",st1=".$st1.",st2=".$st2.",date=".strftime('%Y-%m-%d %H:%M:%S',time())."\r\n", 3, 'debug.log');
        $sst2 = $db->prepare('UPDATE timetable
            SET destination_id = 0 WHERE station_id = :station_id AND time = :time AND destination_id = :station1 AND destination2_id = :station2');
        $sst2->bindValue(':station_id', $station_id);
        $sst2->bindValue(':time', $time);
        $sst2->bindValue(':station1', $st1);
        $sst2->bindValue(':station2', $st2);
        $sst2->execute();
    }

    $sql= 'SELECT * FROM timetable WHERE destination2_id = :id';
    $stt = $db->prepare($sql);
    $stt->bindValue(':id', $id);
    $stt->execute();
    while ($row = $stt->fetch()) {
        $station_id = $row['station_id'];
        $time = $row['time'];
        $st1 = $row['destination_id'];
        $st2 = $row['destination2_id'];

        $sst2 = $db->prepare('UPDATE timetable
            SET destination2_id = 0 WHERE station_id = :station_id AND time = :time AND destination_id = :station1 AND destination2_id = :station2');
        $sst2->bindValue(':station_id', $station_id);
        $sst2->bindValue(':time', $time);
        $sst2->bindValue(':station1', $st1);
        $sst2->bindValue(':station2', $st2);
        $sst2->execute();
    }
    $db->commit();

    header('Content-type: application/json; charset=utf-8 ');
    echo json_encode("OK", JSON_UNESCAPED_UNICODE);

} catch(PDOException $e){
    $db->rollBack();
    die('error'.$e->getMessage());
}
