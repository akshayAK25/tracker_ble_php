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
// Ensure tracker_pending table exists for debounce confirmations
// -------------------------------------------------------------
$sqlCreatePending = "CREATE TABLE IF NOT EXISTS tracker_pending (
    tag_id VARCHAR(20) PRIMARY KEY,
    candidate_receiver VARCHAR(20) NOT NULL,
    candidate_count INT NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL
)";
@mysqli_query($conn, $sqlCreatePending);

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

// Helper function to resolve antenna location name
function getAntennaLocation($conn, $antennaId) {
    $sql = "SELECT
        am.location_id,
        CONCAT_WS(' - ', l.section_department, l.room) AS location
    FROM antenna_mapping am
    LEFT JOIN locations l
        ON am.location_id = l.location_id
    WHERE am.antenna_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $antennaId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
        if ($row && !empty($row["location"])) {
            return $row["location"];
        }
    }
    return "Unknown Location";
}

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

    // =========================================================
    // 1. SAVE RSSI FROM THIS RECEIVER
    // =========================================================
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

    // =========================================================
    // 2. FETCH ALL ACTIVE READINGS FOR THIS TAG IN LAST 20 SEC
    // =========================================================
    $sqlActiveReadings = "SELECT receiver, rssi
        FROM tracker_readings
        WHERE tag_id = ?
        AND updated_at >= DATE_SUB(NOW(), INTERVAL 20 SECOND)
        ORDER BY rssi DESC";

    $stmt = mysqli_prepare($conn, $sqlActiveReadings);
    $activeReadings = [];
    $topReceiver = $receiver;
    $topRssi = $rssi;

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $tagId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $activeReadings[$row["receiver"]] = (int)$row["rssi"];
        }
        mysqli_stmt_close($stmt);
    }

    if (!empty($activeReadings)) {
        $topReceiver = array_key_first($activeReadings);
        $topRssi = $activeReadings[$topReceiver];
    }

    // =========================================================
    // 3. FETCH CURRENT CONFIRMED TRACKER STATUS
    // =========================================================
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

    $actionTaken = "";
    $confirmedReceiver = "";
    $confirmedLocation = "";
    $confirmedRssi = $topRssi;

    // ---------------------------------------------------------
    // CASE A: NEW TRACKER (First time seen)
    // ---------------------------------------------------------
    if (!$currentTracker) {
        $confirmedReceiver = $topReceiver;
        $confirmedRssi = $topRssi;
        $confirmedLocation = getAntennaLocation($conn, $confirmedReceiver);
        $signalStatus = getSignalStatus($confirmedRssi);

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
                $confirmedLocation,
                $confirmedReceiver,
                $confirmedRssi,
                $signalStatus,
                $currentTime
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        // Insert first history record
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
                $confirmedLocation,
                $confirmedReceiver,
                $currentTime
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $actionTaken = "created";
    }
    // ---------------------------------------------------------
    // CASE B: EXISTING TRACKER
    // ---------------------------------------------------------
    else {
        $currReceiver = $currentTracker["receiver"];
        $currLocation = $currentTracker["current_location"];
        $currStatus = $currentTracker["status"];
        $currRssi = isset($activeReadings[$currReceiver]) ? $activeReadings[$currReceiver] : null;

        $targetReceiver = $currReceiver;
        $targetLocation = $currLocation;
        $targetRssi = ($currRssi !== null) ? $currRssi : $topRssi;
        $shouldSwitchLocation = false;

        // Subcase B1: Current receiver is still the strongest antenna
        if ($topReceiver === $currReceiver) {
            $targetReceiver = $currReceiver;
            $targetRssi = $topRssi;

            // Clear any pending switch
            $sqlClearPending = "DELETE FROM tracker_pending WHERE tag_id = ?";
            $stmt = mysqli_prepare($conn, $sqlClearPending);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $tagId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        // Subcase B2: A different antenna ($topReceiver) has the highest RSSI
        else {
            // Rule 1: 6 dB Hysteresis Margin
            // The candidate must beat the current receiver by at least 6 dB.
            // If the current receiver hasn't reported in 20s ($currRssi === null), margin is waived.
            $beatsByMargin = ($currRssi === null) || ($topRssi >= ($currRssi + 6));

            if (!$beatsByMargin) {
                // Not strong enough to overcome the 6 dB margin. Stay on current receiver.
                $targetReceiver = $currReceiver;
                $targetRssi = ($currRssi !== null) ? $currRssi : $topRssi;

                // Clear any candidate
                $sqlClearPending = "DELETE FROM tracker_pending WHERE tag_id = ?";
                $stmt = mysqli_prepare($conn, $sqlClearPending);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "s", $tagId);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            } else {
                // Rule 2: 2-Scan Confirmation (Debounce)
                // Check if this candidate already won on the previous scan within the last 8 seconds.
                $sqlCheckPending = "SELECT candidate_receiver, candidate_count, updated_at
                    FROM tracker_pending
                    WHERE tag_id = ?";
                $stmt = mysqli_prepare($conn, $sqlCheckPending);
                $pending = null;
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "s", $tagId);
                    mysqli_stmt_execute($stmt);
                    $res = mysqli_stmt_get_result($stmt);
                    $pending = mysqli_fetch_assoc($res);
                    mysqli_stmt_close($stmt);
                }

                $isConfirmed = false;
                if ($pending && $pending["candidate_receiver"] === $topReceiver) {
                    // Check if last scan was within 8 seconds
                    $pendingAge = strtotime($currentTime) - strtotime($pending["updated_at"]);
                    if ($pendingAge <= 8) {
                        // Confirmed! (2 consecutive scans)
                        $isConfirmed = true;
                    }
                }

                if ($isConfirmed || $currStatus === "Offline") {
                    // Confirmed switch!
                    $targetReceiver = $topReceiver;
                    $targetRssi = $topRssi;
                    $targetLocation = getAntennaLocation($conn, $targetReceiver);
                    $shouldSwitchLocation = true;

                    // Clear pending table
                    $sqlClearPending = "DELETE FROM tracker_pending WHERE tag_id = ?";
                    $stmt = mysqli_prepare($conn, $sqlClearPending);
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "s", $tagId);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                    }
                } else {
                    // Scan 1 of 2: Record candidate in tracker_pending, do NOT switch location yet
                    $sqlSetPending = "INSERT INTO tracker_pending (tag_id, candidate_receiver, candidate_count, updated_at)
                        VALUES (?, ?, 1, ?)
                        ON DUPLICATE KEY UPDATE
                        candidate_receiver = VALUES(candidate_receiver),
                        candidate_count = 1,
                        updated_at = VALUES(updated_at)";
                    $stmt = mysqli_prepare($conn, $sqlSetPending);
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "sss", $tagId, $topReceiver, $currentTime);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                    }

                    // Keep current receiver and location for this scan
                    $targetReceiver = $currReceiver;
                    $targetLocation = $currLocation;
                    $targetRssi = ($currRssi !== null) ? $currRssi : $topRssi;
                }
            }
        }

        $confirmedReceiver = $targetReceiver;
        $confirmedLocation = $targetLocation;
        $confirmedRssi = $targetRssi;
        $signalStatus = getSignalStatus($confirmedRssi);

        // Rule 3: Location Transition & Prevent Same-Location Spam
        // Only insert into tracker_history if location actually changed or returning from Offline
        if ($shouldSwitchLocation) {
            if ($currLocation !== $confirmedLocation || $currStatus === "Offline") {
                // Close previous open history record
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

                // Insert new history record for the new location
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
                        $confirmedLocation,
                        $confirmedReceiver,
                        $currentTime
                    );
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
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
                $confirmedLocation,
                $confirmedReceiver,
                $confirmedRssi,
                $signalStatus,
                $currentTime,
                $tagId
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        $actionTaken = $shouldSwitchLocation ? "switched" : "updated";
    }

    $results[] = [
        "success"        => true,
        "action"         => $actionTaken,
        "tagId"          => $tagId,
        "assetTagNumber" => $assetTagNumber,
        "deviceName"     => $deviceName,
        "receiver"       => $confirmedReceiver,
        "location"       => $confirmedLocation,
        "rssi"           => $confirmedRssi,
        "signalStatus"   => $signalStatus
    ];
}

// -------------------------------------------------------------
// Output response
// Backward compatible: if single device request, output flat result.
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
