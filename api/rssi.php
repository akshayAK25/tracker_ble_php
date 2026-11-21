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
// Ensure tracker_pending table exists with first_seen dwell tracking
// -------------------------------------------------------------
$sqlCreatePending = "CREATE TABLE IF NOT EXISTS tracker_pending (
    tag_id VARCHAR(20) PRIMARY KEY,
    candidate_receiver VARCHAR(20) NOT NULL,
    first_seen DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
)";
@mysqli_query($conn, $sqlCreatePending);

// Ensure first_seen column exists if table was created with older schema
$resCol = @mysqli_query($conn, "SHOW COLUMNS FROM tracker_pending LIKE 'first_seen'");
if ($resCol && mysqli_num_rows($resCol) == 0) {
    @mysqli_query($conn, "DROP TABLE IF EXISTS tracker_pending");
    @mysqli_query($conn, $sqlCreatePending);
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
        l.room AS location
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

        // Insert first history record (guarded against duplicates)
        $sqlCheckOpen = "SELECT id FROM tracker_history WHERE tag_id = ? AND exit_time IS NULL LIMIT 1";
        $stmtCheck = mysqli_prepare($conn, $sqlCheckOpen);
        $hasOpen = false;
        if ($stmtCheck) {
            mysqli_stmt_bind_param($stmtCheck, "s", $tagId);
            mysqli_stmt_execute($stmtCheck);
            $resOpen = mysqli_stmt_get_result($stmtCheck);
            if (mysqli_fetch_assoc($resOpen)) {
                $hasOpen = true;
            }
            mysqli_stmt_close($stmtCheck);
        }

        if (!$hasOpen) {
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

            // Clear any pending candidate since current receiver is winning
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
            // Rule 1: 8 dB Hysteresis Margin
            // The candidate must beat the current receiver by at least 8 dBm.
            // If the current receiver has timed out (> 20s silent), the margin is waived.
            $beatsByMargin = ($currRssi === null) || ($topRssi >= ($currRssi + 8));

            if (!$beatsByMargin) {
                // Not strong enough to overcome the 8 dB margin. Stay on current receiver.
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
                // Rule 2: 5-Second Sustained Dwell Time
                // The candidate must sustain the lead continuously for >= 5 seconds.
                $sqlCheckPending = "SELECT candidate_receiver, first_seen, updated_at
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
                $currentTimestamp = strtotime($currentTime);

                if ($pending && $pending["candidate_receiver"] === $topReceiver) {
                    $firstSeenTime = strtotime($pending["first_seen"]);
                    $lastUpdateTime = strtotime($pending["updated_at"]);
                    $gapSinceLastPacket = $currentTimestamp - $lastUpdateTime;

                    // If gap is <= 6 seconds, this candidate held the lead continuously
                    if ($gapSinceLastPacket <= 6) {
                        $totalDwellDuration = $currentTimestamp - $firstSeenTime;
                        if ($totalDwellDuration >= 5) {
                            // Sustained for at least 5 seconds! CONFIRMED!
                            $isConfirmed = true;
                        } else {
                            // Still within the 5-second dwell window: update timestamp
                            $sqlUpdatePending = "UPDATE tracker_pending SET updated_at = ? WHERE tag_id = ?";
                            $stmtUpd = mysqli_prepare($conn, $sqlUpdatePending);
                            if ($stmtUpd) {
                                mysqli_stmt_bind_param($stmtUpd, "ss", $currentTime, $tagId);
                                mysqli_stmt_execute($stmtUpd);
                                mysqli_stmt_close($stmtUpd);
                            }
                        }
                    } else {
                        // Candidate went silent for > 6 seconds; reset dwell timer
                        $sqlResetPending = "UPDATE tracker_pending SET first_seen = ?, updated_at = ? WHERE tag_id = ?";
                        $stmtReset = mysqli_prepare($conn, $sqlResetPending);
                        if ($stmtReset) {
                            mysqli_stmt_bind_param($stmtReset, "sss", $currentTime, $currentTime, $tagId);
                            mysqli_stmt_execute($stmtReset);
                            mysqli_stmt_close($stmtReset);
                        }
                    }
                } else {
                    // Start fresh 5-second dwell timer for this candidate
                    $sqlInsertPending = "INSERT INTO tracker_pending (tag_id, candidate_receiver, first_seen, updated_at)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        candidate_receiver = VALUES(candidate_receiver),
                        first_seen = VALUES(first_seen),
                        updated_at = VALUES(updated_at)";
                    $stmtIns = mysqli_prepare($conn, $sqlInsertPending);
                    if ($stmtIns) {
                        mysqli_stmt_bind_param($stmtIns, "ssss", $tagId, $topReceiver, $currentTime, $currentTime);
                        mysqli_stmt_execute($stmtIns);
                        mysqli_stmt_close($stmtIns);
                    }
                }

                if ($isConfirmed || $currStatus === "Offline") {
                    // Confirmed switch!
                    $targetReceiver = $topReceiver;
                    $targetRssi = $topRssi;
                    $targetLocation = getAntennaLocation($conn, $targetReceiver);
                    $shouldSwitchLocation = true;

                    // Clear pending table once confirmed
                    $sqlClearPending = "DELETE FROM tracker_pending WHERE tag_id = ?";
                    $stmt = mysqli_prepare($conn, $sqlClearPending);
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "s", $tagId);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                    }
                } else {
                    // Dwell duration (< 5s) not yet reached: hold current location
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

        // =====================================================
        // Rule 3: Anti-Duplicate History Guard
        // =====================================================
        if ($shouldSwitchLocation || $currStatus === "Offline") {
            // Check current open records in tracker_history for this tag
            $sqlOpenRows = "SELECT id, location, entry_time FROM tracker_history WHERE tag_id = ? AND exit_time IS NULL ORDER BY id DESC";
            $stmtOpen = mysqli_prepare($conn, $sqlOpenRows);
            $openRows = [];
            if ($stmtOpen) {
                mysqli_stmt_bind_param($stmtOpen, "s", $tagId);
                mysqli_stmt_execute($stmtOpen);
                $resOpen = mysqli_stmt_get_result($stmtOpen);
                while ($r = mysqli_fetch_assoc($resOpen)) {
                    $openRows[] = $r;
                }
                mysqli_stmt_close($stmtOpen);
            }

            $alreadyOpenInTargetLocation = false;
            foreach ($openRows as $idx => $openRow) {
                if ($openRow["location"] === $confirmedLocation && !$alreadyOpenInTargetLocation) {
                    // Already open in this exact target location!
                    $alreadyOpenInTargetLocation = true;
                } else {
                    // Close this record (different location or duplicate open record)
                    $sqlClose = "UPDATE tracker_history SET exit_time = ? WHERE id = ?";
                    $stmtClose = mysqli_prepare($conn, $sqlClose);
                    if ($stmtClose) {
                        mysqli_stmt_bind_param($stmtClose, "si", $currentTime, $openRow["id"]);
                        mysqli_stmt_execute($stmtClose);
                        mysqli_stmt_close($stmtClose);
                    }
                }
            }

            // Only insert a new history record if NOT already open in this location
            if (!$alreadyOpenInTargetLocation) {
                // Check if the most recently closed record was for the SAME location within 5s
                $sqlLastClosed = "SELECT id, location, exit_time FROM tracker_history WHERE tag_id = ? ORDER BY id DESC LIMIT 1";
                $stmtLast = mysqli_prepare($conn, $sqlLastClosed);
                $lastClosed = null;
                if ($stmtLast) {
                    mysqli_stmt_bind_param($stmtLast, "s", $tagId);
                    mysqli_stmt_execute($stmtLast);
                    $resLast = mysqli_stmt_get_result($stmtLast);
                    $lastClosed = mysqli_fetch_assoc($resLast);
                    mysqli_stmt_close($stmtLast);
                }

                $canReopen = false;
                if ($lastClosed && $lastClosed["location"] === $confirmedLocation && !empty($lastClosed["exit_time"])) {
                    $secondsSinceExit = strtotime($currentTime) - strtotime($lastClosed["exit_time"]);
                    if ($secondsSinceExit <= 5) {
                        // Re-open previous row instead of creating duplicate back-to-back row
                        $sqlReopen = "UPDATE tracker_history SET exit_time = NULL WHERE id = ?";
                        $stmtReopen = mysqli_prepare($conn, $sqlReopen);
                        if ($stmtReopen) {
                            mysqli_stmt_bind_param($stmtReopen, "i", $lastClosed["id"]);
                            mysqli_stmt_execute($stmtReopen);
                            mysqli_stmt_close($stmtReopen);
                            $canReopen = true;
                        }
                    }
                }

                if (!$canReopen) {
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
