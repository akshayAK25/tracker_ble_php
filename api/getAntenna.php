<?php

include "../config/db.php";

header("Content-Type: application/json");

$sql = "SELECT * FROM antenna_mapping ORDER BY antenna_id";

$result = mysqli_query($conn, $sql);

$antennas = [];

while ($row = mysqli_fetch_assoc($result)) {

    $antennas[] = $row;

}

echo json_encode([
    "success" => true,
    "data" => $antennas
]);

?>