<?php

include "../config/db.php";

header("Content-Type: application/json");

if($_SERVER["REQUEST_METHOD"] != "POST"){

    echo json_encode([
        "success"=>false,
        "message"=>"POST request required"
    ]);

    exit();
}

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

$antennaId = strtoupper(
    trim($data["antennaId"])
);

$locationId = strtoupper(
    trim($data["locationId"] ?? "")
);

$sql =
"INSERT INTO antenna_mapping
(antenna_id, location_id)

VALUES (?,?)

ON DUPLICATE KEY UPDATE

location_id = VALUES(location_id)";

$stmt = mysqli_prepare(
    $conn,
    $sql
);

mysqli_stmt_bind_param(
    $stmt,
    "ss",
    $antennaId,
    $locationId
);

mysqli_stmt_execute($stmt);

echo json_encode([
    "success"=>true,
    "message"=>"Antenna Saved"
]);

?>
