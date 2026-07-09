<?php

include "../config/db.php";

header("Content-Type: application/json");

$sql = "SELECT * FROM device_mapping ORDER BY tag_id";

$result = mysqli_query($conn, $sql);

$devices = [];

while ($row = mysqli_fetch_assoc($result)) {

    $devices[] = $row;

}

echo json_encode([
    "success" => true,
    "data" => $devices
]);

?>