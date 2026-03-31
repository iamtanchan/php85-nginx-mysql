<?php
require_once __DIR__ . '/../lib/admin_bootstrap.php';

$id = $_POST["id"];
//error_log("get_order user=".$user.",date=".strftime('%Y-%m-%d %H:%M:%S',time())."\r\n", 3, 'debug.log');
try{
$sql= 'SELECT * FROM display WHERE display_id = :id';
    $stt = $db->prepare($sql);
    $stt->bindValue(':id', $id);
    $stt->execute();
	$row = $stt->fetch(); 
        //error_log("get_order order=".$row['ordernum'].",date=".strftime('%Y-%m-%d %H:%M:%S',time())."\r\n", 3, 'debug.log');
        
    $status = array(
        'status' => $row['ch'],
    );
    //error_log("set_order users=".$orders.",date=".strftime('%Y-%m-%d %H:%M:%S',time())."\r\n", 3, 'debug.log');
    //jsonとして出力
    header('Content-type: application/json; charset=utf-8 ');
    echo json_encode($status,JSON_UNESCAPED_UNICODE);
} catch(PDOException $e){
		die('エラーメッセージ'.$e->getMessage());
}
