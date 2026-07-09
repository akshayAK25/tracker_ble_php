<?php

include "../config/db.php";

header("Content-Type: application/json");

$data = json_decode(
    file_get_contents("php://input"),
    true
);

if(!$data){

    echo json_encode([
        "success"=>false,
        "message"=>"No Data"
    ]);

    exit();
}

$tagId = strtoupper(
    trim($data["tagId"])
);

$deviceName = trim(
    $data["deviceName"]
);

$sql =

"INSERT INTO device_mapping
(tag_id,device_name)

VALUES (?,?)

ON DUPLICATE KEY UPDATE

device_name = VALUES(device_name)";

$stmt = mysqli_prepare(
    $conn,
    $sql
);

mysqli_stmt_bind_param(

    $stmt,

    "ss",

    $tagId,

    $deviceName

);

mysqli_stmt_execute($stmt);

echo json_encode([

    "success"=>true,

    "message"=>"Device Saved"

]);

?>