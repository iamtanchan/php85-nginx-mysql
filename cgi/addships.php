<?php
require_once __DIR__ . '/../lib/admin_bootstrap.php';
$name = $_POST['name'];
$name_e = $_POST['name_e'];
try{
    $sql= 'SELECT ship_id FROM ship ORDER BY ship_id DESC LIMIT 1';
    $stt = $db->prepare($sql);
    $stt->execute();
    $row = $stt->fetch();
    $id = $row['ship_id'];
    $id = $id + 1;
    //error_log("addstations id=".$id.",date=".strftime('%Y-%m-%d %H:%M:%S',time())."\r\n", 3, 'debug.log');

    $sst = $db->prepare('INSERT INTO ship
            (ship_id, name, name_e)
        VALUES  (:id, :name, :name_e)');
    $sst->bindValue(':id', $id);
    $sst->bindValue(':name', $name);
    $sst->bindValue(':name_e', $name_e);
    $sst->execute();

    //jsonとして出力
	header('Content-type: application/json; charset=utf-8 ');
	echo json_encode("OK",JSON_UNESCAPED_UNICODE);

} catch(PDOException $e){
	die('error'.$e->getMessage());
}
