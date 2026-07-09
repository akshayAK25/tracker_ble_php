<?php

include "../config/db.php";

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

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$history = [];

while($row = mysqli_fetch_assoc($result))
{
    $history[] = $row;
}

echo json_encode([
    "success" => true,
    "data" => $history
]);

?>