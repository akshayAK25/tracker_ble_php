<?php


include "../config/db.php";
include "../includes/badge_functions.php";

header("Content-Type: application/json");

if(!isset($_GET["tagId"]))
{
    echo json_encode([
        "success" => false,
        "message" => "tagId is required"
    ]);
    exit();
}

$tagId = strtoupper(trim($_GET["tagId"]));
$asset = getDisplayNameForBadge($conn, $tagId);

// startDate / endDate are optional - if the page sends them, only
// history that overlaps that date range is returned
$startDate = isset($_GET["startDate"]) ? trim($_GET["startDate"]) : "";
$endDate = isset($_GET["endDate"]) ? trim($_GET["endDate"]) : "";
$startAt = isset($_GET["startAt"]) ? trim($_GET["startAt"]) : "";
$endAt = isset($_GET["endAt"]) ? trim($_GET["endAt"]) : "";

$hasDateFilter = ($startDate !== "" && $endDate !== "");

if($hasDateFilter)
{
    // overlap logic: a row counts as "in range" if it started before
    // the range ended, AND it either hasn't exited yet (still ongoing)
    // or it exited after the range began. This is what correctly keeps
    // a visit that started days earlier and is still open right now.
    $sql = "SELECT
                location,
                receiver,
                entry_time,
                exit_time
            FROM tracker_history
            WHERE tag_id = ?
                AND entry_time <= ?
                AND (exit_time IS NULL OR exit_time >= ?)
            ORDER BY entry_time DESC";

    if(
        $startAt !== "" &&
        $endAt !== "" &&
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
        $rangeEnd = $endDate . " 23:59:59";
    }

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "sss",
        $tagId,
        $rangeEnd,
        $rangeStart
    );
}
else
{
    $sql = "SELECT
                location,
                receiver,
                entry_time,
                exit_time
            FROM tracker_history
            WHERE tag_id = ?
            ORDER BY entry_time DESC";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $tagId
    );
}

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$history = [];

while($row = mysqli_fetch_assoc($result))
{
    $history[] = $row;
}

echo json_encode([
    "success" => true,
    "tag_id" => $tagId,
    "asset_tag_number" => $asset ? $asset["asset_tag_number"] : null,
    "device_name" => $asset ? $asset["device_name"] : $tagId,
    "data" => $history
]);

   ?> 



