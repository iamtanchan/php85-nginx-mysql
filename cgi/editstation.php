<?php
require_once __DIR__ . '/../lib/admin_bootstrap.php';
$id = $_POST['id'];
$name = $_POST['name'];
$name_e = $_POST['name_e'];
try{
    $sst = $db->prepare('UPDATE destination
        SET name = :name, name_e = :name_e WHERE destination_id = :id');
    $sst->bindValue(':id', $id);
    $sst->bindValue(':name', $name);
    $sst->bindValue(':name_e', $name_e);
    $sst->execute();

    header('Content-type: application/json; charset=utf-8 ');
    echo json_encode("OK", JSON_UNESCAPED_UNICODE);

} catch(PDOException $e){
    die('error'.$e->getMessage());
}
