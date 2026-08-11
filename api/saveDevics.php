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

$assetTagNumber = strtoupper(
    trim($data["assetTagNumber"] ?? $data["tagId"])
);

$deviceName = trim(
    $data["deviceName"]
);

$sql =

"INSERT INTO device_mapping
(asset_tag_number,device_name)

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

    $assetTagNumber,

    $deviceName

);

mysqli_stmt_execute($stmt);

echo json_encode([

    "success"=>true,

    "message"=>"Device Saved"

]);

?>
