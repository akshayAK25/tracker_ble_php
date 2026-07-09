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

$location = trim(
    $data["location"]
);

$sql =

"INSERT INTO antenna_mapping
(antenna_id, location)

VALUES (?,?)

ON DUPLICATE KEY UPDATE

location = VALUES(location)";

$stmt = mysqli_prepare(
    $conn,
    $sql
);

mysqli_stmt_bind_param(
    $stmt,
    "ss",
    $antennaId,
    $location
);

mysqli_stmt_execute($stmt);

echo json_encode([
    "success"=>true,
    "message"=>"Antenna Saved"
]);

?>