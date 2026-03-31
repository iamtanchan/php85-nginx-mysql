<?php
require_once __DIR__ . '/../lib/admin_bootstrap.php';
$id = $_POST['id'];
//error_log("setship time=".$time.",ship=".$ship.",date=".strftime('%Y-%m-%d %H:%M:%S',time())."\r\n", 3, 'debug.log');
try{
    $stt = $db->prepare('DELETE FROM ship WHERE ship_id = :id');
    $stt->bindValue(':id', $id);
    $stt->execute();

    //消去船を登録していた時刻表は定義なし=0にする
    $sql= 'SELECT * FROM timetable WHERE ship_id = :id';
    $stt = $db->prepare($sql);
    $stt->bindValue(':id', $id);
    $stt->execute();
    while ($row = $stt->fetch()) {
        $station_id = $row['station_id'];
        $time = $row['time'];
        $st1 = $row['destination_id'];

        //error_log("delship time=".$time.",st1=".$st1.",date=".strftime('%Y-%m-%d %H:%M:%S',time())."\r\n", 3, 'debug.log');
        $sst2 = $db->prepare('UPDATE timetable 
            SET ship_id = 0 WHERE station_id = :station_id AND time = :time AND destination_id = :station1');
        $sst2->bindValue(':station_id', $station_id);
        $sst2->bindValue(':time', $time);
        $sst2->bindValue(':station1', $st1);
        $sst2->execute();
    }
   
    //jsonとして出力
	header('Content-type: application/json; charset=utf-8 ');
	echo json_encode("OK",JSON_UNESCAPED_UNICODE);

} catch(PDOException $e){
	die('error'.$e->getMessage());
}
