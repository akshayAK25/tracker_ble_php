<?php

include "../config/db.php";

header("Content-Type: application/json");

$sql = "SELECT
            id,
            asset_tag_number,
            device_name
        FROM device_mapping
        ORDER BY asset_tag_number";

$result = mysqli_query($conn, $sql);

if(!$result)
{
    echo json_encode([
        "success" => false,
        "message" => "Device mapping unavailable. Run setup.php once to add asset_tag_number.",
        "error" => mysqli_error($conn)
    ]);
    exit();
}

$devices = [];

while ($row = mysqli_fetch_assoc($result)) {

    $devices[] = $row;

}

echo json_encode([
    "success" => true,
    "data" => $devices
]);

?>
