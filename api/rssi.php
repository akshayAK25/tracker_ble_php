<?php
include "../config/db.php";
include "../includes/badge_functions.php";

function getSignalStatus($rssi)
{
    if ($rssi >= -50) return "Very Near";
    if ($rssi >= -65) return "Near";
    if ($rssi >= -80) return "Medium";
    return "Far";
}

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    echo json_encode([
        "success" => false,
        "message" => "POST request required"
    ]);
    exit();
}

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

if (!$data) {
    echo json_encode([
        "success" => false,
        "message" => "No JSON Data"
    ]);
    exit();
}

// -------------------------------------------------------------
// Parse input payload:
// Supports:
// 1. Batch format: {"receiver": "A3", "devices": [{"device": "...", "rssi": -52}, ...]}
// 2. Single format: {"receiver": "A3", "device": "...", "rssi": -52}
// 3. Array format: [{"receiver": "A3", "device": "...", "rssi": -52}, ...]
// -------------------------------------------------------------

$defaultReceiver = isset($data["receiver"]) ? strtoupper(trim($data["receiver"])) : "";
$readings = [];
$isBatch = false;

if (isset($data["devices"]) && is_array($data["devices"])) {
    $isBatch = true;
    foreach ($data["devices"] as $item) {
        if (!isset($item["device"]) || !isset($item["rssi"])) continue;
        $readings[] = [
            "receiver" => (!empty($item["receiver"])) ? strtoupper(trim($item["receiver"])) : $defaultReceiver,
            "device"   => strtoupper(trim($item["device"])),
            "rssi"     => (int)$item["rssi"]
        ];
    }
} elseif (isset($data["device"]) && isset($data["rssi"])) {
    $readings[] = [
        "receiver" => $defaultReceiver,
        "device"   => strtoupper(trim($data["device"])),
        "rssi"     => (int)$data["rssi"]
    ];
} elseif (is_array($data) && isset($data[0]) && is_array($data[0])) {
    $isBatch = true;
    foreach ($data as $item) {
        if (isset($item["device"]) && isset($item["rssi"])) {
            $readings[] = [
                "receiver" => (!empty($item["receiver"])) ? strtoupper(trim($item["receiver"])) : $defaultReceiver,
                "device"   => strtoupper(trim($item["device"])),
                "rssi"     => (int)$item["rssi"]
            ];
        }
    }
}

if (empty($readings)) {
    echo json_encode([
        "success" => false,
        "message" => "No valid device readings provided"
    ]);
    exit();
}

date_default_timezone_set("Asia/Kolkata");
$currentTime = date("Y-m-d H:i:s");

$results = [];

foreach ($readings as $reading) {
    $tagId = $reading["device"];
    $receiver = $reading["receiver"];
    $rssi = $reading["rssi"];

    if (empty($tagId) || empty($receiver)) {
        $results[] = [
            "device"   => $tagId,
            "receiver" => $receiver,
            "success"  => false,
            "message"  => "Missing device MAC or receiver ID"
        ];
        continue;
    }

    $asset = getDisplayNameForBadge($conn, $tagId);
    if (!$asset) {
        $results[] = [
            "device"   => $tagId,
            "receiver" => $receiver,
            "rssi"     => $rssi,
            "success"  => false,
            "message"  => "Unknown Badge"
        ];
        continue;
    }

    $assetTagNumber = $asset["asset_tag_number"];
    $deviceName = $asset["device_name"];

    // ======================================
    // 1. SAVE RSSI FROM THIS RECEIVER
    // ======================================
    $sqlInsertReading = "INSERT INTO tracker_readings
        (tag_id, receiver, rssi, updated_at)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        rssi = VALUES(rssi),
        updated_at = VALUES(updated_at)";

    $stmt = mysqli_prepare($conn, $sqlInsertReading);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssis", $tagId, $receiver, $rssi, $currentTime);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    // ======================================
    // 2. FIND STRONGEST RECEIVER IN LAST 20 SECONDS
    // ======================================
    $sqlBestReading = "SELECT receiver, rssi
        FROM tracker_readings
        WHERE tag_id = ?
        AND updated_at >= DATE_SUB(NOW(), INTERVAL 20 SECOND)
        ORDER BY rssi DESC
        LIMIT 1";

    $stmt = mysqli_prepare($conn, $sqlBestReading);
    $bestReading = null;
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $tagId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $bestReading = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }

    if (!$bestReading) {
        $bestReceiver = $receiver;
        $bestRssi = $rssi;
    } else {
        $bestReceiver = $bestReading["receiver"];
        $bestRssi = (int)$bestReading["rssi"];
    }

    // ======================================
    // 3. GET LOCATION OF STRONGEST RECEIVER
    // ======================================
    $sqlAntenna = "SELECT
        am.location_id,
        CONCAT_WS(' - ', l.section_department, l.room) AS location
    FROM antenna_mapping am
    LEFT JOIN locations l
        ON am.location_id = l.location_id
    WHERE am.antenna_id = ?";

    $stmt = mysqli_prepare($conn, $sqlAntenna);
    $antenna = null;
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $bestReceiver);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $antenna = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }

    $location = ($antenna && !empty($antenna["location"])) ? $antenna["location"] : "Unknown Location";

    // ======================================
    // 4. CHECK TRACKER STATUS & HISTORY
    // ======================================
    $sqlStatus = "SELECT * FROM tracker_status WHERE tag_id = ?";
    $stmt = mysqli_prepare($conn, $sqlStatus);
    $currentTracker = null;
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $tagId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $currentTracker = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }

    $signalStatus = getSignalStatus($bestRssi);
    $actionTaken = "";

    if (!$currentTracker) {
        // Insert new tracker_status
        $sqlInsertStatus = "INSERT INTO tracker_status
        (
            tag_id,
            device_name,
            current_location,
            receiver,
            best_rssi,
            status,
            updated_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sqlInsertStatus);
        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                "ssssiss",
                $tagId,
                $deviceName,
                $location,
                $bestReceiver,
                $bestRssi,
                $signalStatus,
                $currentTime
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        // Create first history record
        $sqlInsertHist = "INSERT INTO tracker_history
        (
            tag_id,
            device_name,
            location,
            receiver,
            entry_time,
            exit_time
        )
        VALUES (?, ?, ?, ?, ?, NULL)";

        $stmt = mysqli_prepare($conn, $sqlInsertHist);
        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                "sssss",
                $tagId,
                $deviceName,
                $location,
                $bestReceiver,
                $currentTime
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        $actionTaken = "created";
    } else {
        $oldLocation = $currentTracker["current_location"];
        $oldStatus = $currentTracker["status"];

        if ($oldLocation != $location || $oldStatus == "Offline") {
            // Close previous history record
            $sqlUpdateHist = "UPDATE tracker_history
                SET exit_time = ?
                WHERE tag_id = ?
                AND exit_time IS NULL";

            $stmt = mysqli_prepare($conn, $sqlUpdateHist);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ss", $currentTime, $tagId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            // Insert new history record
            $sqlInsertHist = "INSERT INTO tracker_history
            (
                tag_id,
                device_name,
                location,
                receiver,
                entry_time,
                exit_time
            )
            VALUES (?, ?, ?, ?, ?, NULL)";

            $stmt = mysqli_prepare($conn, $sqlInsertHist);
            if ($stmt) {
                mysqli_stmt_bind_param(
                    $stmt,
                    "sssss",
                    $tagId,
                    $deviceName,
                    $location,
                    $bestReceiver,
                    $currentTime
                );
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }

        // Always update tracker_status
        $sqlUpdateStatus = "UPDATE tracker_status
            SET
                current_location = ?,
                receiver = ?,
                best_rssi = ?,
                status = ?,
                updated_at = ?
            WHERE tag_id = ?";

        $stmt = mysqli_prepare($conn, $sqlUpdateStatus);
        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                "ssisss",
                $location,
                $bestReceiver,
                $bestRssi,
                $signalStatus,
                $currentTime,
                $tagId
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        $actionTaken = "updated";
    }

    $results[] = [
        "success"        => true,
        "action"         => $actionTaken,
        "tagId"          => $tagId,
        "assetTagNumber" => $assetTagNumber,
        "deviceName"     => $deviceName,
        "receiver"       => $bestReceiver,
        "location"       => $location,
        "rssi"           => $bestRssi,
        "signalStatus"   => $signalStatus
    ];
}

// -------------------------------------------------------------
// Output response
// Backward compatible: if single device request, output flat result with extra compatibility fields.
// If batch request, output results array.
// -------------------------------------------------------------

if (!$isBatch && count($results) === 1) {
    echo json_encode($results[0]);
} else {
    echo json_encode([
        "success" => true,
        "count"   => count($results),
        "results" => $results
    ]);
}
