<?php

include "../config/db.php";

header("Content-Type: application/json");

$sql = "SELECT
            id,
            location_id,
            lecode,
            section_department,
            region,
            country,
            state,
            city,
            room,
            office_address,
            created_by_role,
            room AS location_name,
            room AS location
        FROM locations
        ORDER BY location_id";

$result = mysqli_query($conn, $sql);

if(!$result)
{
    echo json_encode([
        "success" => false,
        "message" => "Locations unavailable. Run setup.php once to create locations.",
        "error" => mysqli_error($conn)
    ]);
    exit();
}

$locations = [];

while($row = mysqli_fetch_assoc($result))
{
    $locations[] = $row;
}

echo json_encode([
    "success" => true,
    "data" => $locations
]);

?>
