<?php

include "../config/db.php";

header("Content-Type: application/json");

$sql = "SELECT
            am.id,
            am.antenna_id,
            am.location_id,
            CASE
                WHEN l.location_id IS NOT NULL THEN CONCAT_WS(' - ', l.section_department, l.room)
                ELSE NULL
            END AS location_name,
            CASE
                WHEN l.location_id IS NOT NULL THEN CONCAT_WS(' - ', l.section_department, l.room)
                ELSE NULL
            END AS location,
            l.lecode,
            l.section_department,
            l.region,
            l.country,
            l.state,
            l.city,
            l.room,
            l.office_address,
            l.created_by_role
        FROM antenna_mapping am
        LEFT JOIN locations l
            ON am.location_id = l.location_id
        ORDER BY am.antenna_id";

$result = mysqli_query($conn, $sql);

if(!$result)
{
    echo json_encode([
        "success" => false,
        "message" => "Antenna mapping unavailable. Run setup.php once to add locations.",
        "error" => mysqli_error($conn)
    ]);
    exit();
}

$antennas = [];

while ($row = mysqli_fetch_assoc($result)) {

    $antennas[] = $row;

}

echo json_encode([
    "success" => true,
    "data" => $antennas
]);

?>
