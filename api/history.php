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

// Clean up any historical duplicate open rows in tracker_history
@mysqli_query($conn, "
    UPDATE tracker_history th1
    JOIN tracker_history th2
      ON th1.tag_id = th2.tag_id
     AND th1.exit_time IS NULL
     AND th2.exit_time IS NULL
     AND th1.id < th2.id
    SET th1.exit_time = th1.entry_time
");

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
            ORDER BY entry_time DESC, id DESC";

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
            ORDER BY entry_time DESC, id DESC";

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
$prevKey = null;

while($row = mysqli_fetch_assoc($result))
{
    // Deduplicate identical consecutive history entries
    $exitStr = $row["exit_time"] ?: "OPEN";
    $key = $row["location"] . "|" . $row["entry_time"] . "|" . $exitStr;
    if ($key === $prevKey) {
        continue;
    }
    $prevKey = $key;

    $history[] = $row;
}

mysqli_stmt_close($stmt);

echo json_encode([
    "success" => true,
    "tag_id" => $tagId,
    "asset_tag_number" => $asset ? $asset["asset_tag_number"] : null,
    "device_name" => $asset ? $asset["device_name"] : $tagId,
    "data" => $history
]);

?>
