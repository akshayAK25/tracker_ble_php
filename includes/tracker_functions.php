<?php

function checkOfflineTrackers($conn)
{
    date_default_timezone_set("Asia/Kolkata");

    $currentTime = date("Y-m-d H:i:s");

    // 20 seconds ago
    $offlineTime = date(
        "Y-m-d H:i:s",
        strtotime($currentTime) - 20
    );

    $sql = "
    SELECT *
    FROM tracker_status
    WHERE status != ?
    AND updated_at < ?
    ";

    $stmt = mysqli_prepare($conn, $sql);

    $offlineStatus = "Offline";

    mysqli_stmt_bind_param(
        $stmt,
        "ss",
        $offlineStatus,
        $offlineTime
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    while($tracker = mysqli_fetch_assoc($result))
    {
        $tagId = $tracker["tag_id"];
        $deviceName = $tracker["device_name"];

        // ==========================================
        // Close current history record
        // ==========================================

        $sql = "UPDATE tracker_history
                SET exit_time = ?
                WHERE tag_id = ?
                AND exit_time IS NULL";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "ss",
            $currentTime,
            $tagId
        );

        mysqli_stmt_execute($stmt);


        // ==========================================
        // Insert No Signal history
        // ==========================================

        $sql = "INSERT INTO tracker_history
        (
            tag_id,
            device_name,
            location,
            receiver,
            entry_time,
            exit_time
        )
        VALUES
        (
            ?,?,?,?, ?, NULL
        )";

        $stmt = mysqli_prepare($conn, $sql);

        $noSignal = "No Signal";
        $receiverNull = NULL;

        mysqli_stmt_bind_param(
            $stmt,
            "sssss",
            $tagId,
            $deviceName,
            $noSignal,
            $receiverNull,
            $currentTime
        );

        mysqli_stmt_execute($stmt);


        // ==========================================
        // Mark tracker Offline
        // ==========================================

        $offline = "Offline";

        $sql = "UPDATE tracker_status
                SET status = ?
                WHERE tag_id = ?";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "ss",
            $offline,
            $tagId
        );

        mysqli_stmt_execute($stmt);
    }
}