<?php

include "../config/db.php";
require_once "../includes/tracker_functions.php";

header("Content-Type: application/json");

// Check for trackers that have gone silent
checkOfflineTrackers($conn);

$sql = "SELECT
            ts.tag_id,
            COALESCE(rb.asset_tag_number, ts.tag_id) AS asset_tag_number,
            COALESCE(dm.device_name, ts.device_name) AS asset_name,
            COALESCE(th.location, ts.current_location) AS last_seen_location,
            COALESCE(th.entry_time, ts.updated_at) AS last_seen_time,
            ts.current_location,
            ts.receiver,
            ts.best_rssi,
            CASE
                WHEN UPPER(COALESCE(ts.status, '')) = 'OFFLINE' THEN 'Offline'
                ELSE 'Online'
            END AS status,
            ts.updated_at,
            th.entry_time,
            th.exit_time

        FROM tracker_status ts

        LEFT JOIN rtls_badge rb

        ON UPPER(ts.tag_id) IN (
            UPPER(rb.badge_id_1),
            UPPER(rb.badge_id_2),
            UPPER(rb.badge_id_3)
        )

        LEFT JOIN device_mapping dm

        ON rb.asset_tag_number = dm.asset_tag_number

        LEFT JOIN tracker_history th

        ON ts.tag_id = th.tag_id

        AND th.exit_time IS NULL

        ORDER BY asset_name";

$result = mysqli_query($conn, $sql);

if(!$result)
{
    echo json_encode([
        "success" => false,
        "message" => "Device data unavailable. Run setup.php once to create rtls_badge and asset_tag_number.",
        "error" => mysqli_error($conn)
    ]);
    exit();
}

$trackers = [];

while($row = mysqli_fetch_assoc($result))
{
    $row["online"] = (strtoupper($row["status"]) !== "OFFLINE");
    $row["last_seen_time"] = $row["last_seen_time"] ?: $row["updated_at"];
    $row["last_seen_location"] = $row["last_seen_location"] ?: $row["current_location"];

    if (strtoupper(trim((string)$row["last_seen_location"])) === "NO SIGNAL")
    {
        $prevSql = "SELECT location, exit_time
                    FROM tracker_history
                    WHERE tag_id = ?
                    AND LOWER(location) <> 'no signal'
                    AND exit_time IS NOT NULL
                    ORDER BY exit_time DESC, entry_time DESC
                    LIMIT 1";

        $prevStmt = mysqli_prepare($conn, $prevSql);

        if ($prevStmt)
        {
            mysqli_stmt_bind_param($prevStmt, "s", $row["tag_id"]);
            mysqli_stmt_execute($prevStmt);
            $prevResult = mysqli_stmt_get_result($prevStmt);
            $prevRow = mysqli_fetch_assoc($prevResult);

            if ($prevRow)
            {
                $row["last_seen_location"] = $prevRow["location"] ?: $row["current_location"];
                $row["last_seen_time"] = $prevRow["exit_time"] ?: $row["last_seen_time"];
            }
            else
            {
                $row["last_seen_location"] = $row["current_location"];
                $row["last_seen_time"] = $row["updated_at"];
            }
        }
    }

    $row["asset_tag_no"] = $row["asset_tag_number"];
    $row["asset_name"] = $row["asset_name"] ?: $row["tag_id"];

    $trackers[] = $row;
}

echo json_encode([
    "success" => true,
    "data" => $trackers
]);

?>
