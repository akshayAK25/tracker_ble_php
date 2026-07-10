<?php

include "../config/db.php";

header("Content-Type: application/json");

$sql = "SELECT
            ts.tag_id,
            ts.device_name,
            ts.current_location,
            ts.receiver,
            ts.best_rssi,
            ts.status,
            ts.updated_at,
            th.entry_time,
            th.exit_time

        FROM tracker_status ts

        LEFT JOIN tracker_history th

        ON ts.tag_id = th.tag_id

        AND th.exit_time IS NULL

        ORDER BY ts.device_name";

$result = mysqli_query($conn, $sql);

$trackers = [];

while($row = mysqli_fetch_assoc($result))
{
    $ONLINE_TIMEOUT = 20;   // seconds

    $lastUpdate = strtotime($row["updated_at"]);

    $ONLINE_TIMEOUT = 20;

    $lastUpdate = strtotime($row["updated_at"]);
    $currentTime = time();
    $row["online"] = (($currentTime - $lastUpdate) <= $ONLINE_TIMEOUT);   


$trackers[] = $row;

}

echo json_encode([
    "success" => true,
    "data" => $trackers
]);

?>