<?php
require_once __DIR__ . '/../lib/admin_bootstrap.php';
$id = $_POST['id'];
$name = $_POST['name'];
$name_e = $_POST['name_e'];
//error_log("setship time=".$time.",ship=".$ship.",date=".strftime('%Y-%m-%d %H:%M:%S',time())."\r\n", 3, 'debug.log');
try{
    $sst = $db->prepare('UPDATE ship 
        SET name = :name, name_e = :name_e WHERE ship_id = :id');
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
