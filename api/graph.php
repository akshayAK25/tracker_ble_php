<?php

include "../config/db.php";
include "../includes/badge_functions.php";

header("Content-Type: application/json");

if(!isset($_GET["tagId"]))
{
    echo json_encode([
        "success" => false,
        "message" => "Tag ID required"
    ]);
    exit();
}

$tagId = strtoupper(trim($_GET["tagId"]));
$asset = getDisplayNameForBadge($conn, $tagId);

$startDate = $_GET["startDate"] ?? date("Y-m-d");
$endDate   = $_GET["endDate"] ?? date("Y-m-d");
$startAt   = $_GET["startAt"] ?? null;
$endAt     = $_GET["endAt"] ?? null;

if(
    $startAt &&
    $endAt &&
    preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $startAt) &&
    preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $endAt)
)
{
    $rangeStart = $startAt;
    $rangeEnd = $endAt;
}
else
{
    $rangeStart = $startDate . " 00:00:00";
    $rangeEnd   = $endDate . " 23:59:59";
}

// overlap logic: a row counts as "in range" if it started before the
// range ended, AND it either hasn't exited yet (still ongoing) or it
// exited after the range began. This keeps a visit that started days
// earlier and is still open right now (e.g. a long "No Signal" spell).
$sql = "SELECT
            tag_id,
            device_name,
            location,
            receiver,
            entry_time,
            exit_time
        FROM tracker_history
        WHERE tag_id = ?
        AND entry_time <= ?
        AND (exit_time IS NULL OR exit_time >= ?)
        ORDER BY entry_time ASC";


$stmt = mysqli_prepare($conn,$sql);

mysqli_stmt_bind_param(
    $stmt,
    "sss",
    $tagId,
    $rangeEnd,
    $rangeStart
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);


$data = [];

$previous = null;

while($row = mysqli_fetch_assoc($result))
{
    if(
        $row["exit_time"] &&
        $row["entry_time"] == $row["exit_time"]
    )
    {
        continue;
    }

    $entry = strtotime($row["entry_time"]);

    $exit = $row["exit_time"]
        ? strtotime($row["exit_time"])
        : time();

    $row["duration"] = $exit - $entry;
    $row["type"] = "location";

    if($previous)
    {
        $previousExit = strtotime($previous["exit_time"]);

        $currentEntry = strtotime($row["entry_time"]);

        $gap = $currentEntry - $previousExit;

        // Ignore tiny gaps caused by processing delays
        if($gap > 30)
        {
            $data[] = [
                "location" => "No Signal",
                "receiver" => null,
                "entry_time" => $previous["exit_time"],
                "exit_time" => $row["entry_time"],
                "duration" => $gap,
                "type" => "gap"
            ];
        }
    }

    $data[] = $row;

    $previous = $row;
}

echo json_encode([
    "success" => true,
    "tag_id" => $tagId,
    "asset_tag_number" => $asset ? $asset["asset_tag_number"] : null,
    "device_name" => $asset ? $asset["device_name"] : $tagId,
    "data" => $data
]);
