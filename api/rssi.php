<?php
include "../config/db.php";
include "../includes/badge_functions.php";

function getSignalStatus($rssi)
{
    if($rssi >= -50) return "Very Near";
    
    if($rssi >= -65) return "Near";
    
    if($rssi >= -80) return "Medium";
    
    return "Far";
    }
    
header("Content-Type: application/json");

if($_SERVER["REQUEST_METHOD"] != "POST")
{
    echo json_encode([
        "success" => false,
        "message" => "POST request required"
    ]);
    exit();
}

$data = json_decode(
    file_get_contents("php://input"),
    true
);

if(!$data)
{
    echo json_encode([
        "success" => false,
        "message" => "No JSON Data"
    ]);
    exit();
}

$tagId = strtoupper(trim($data["device"]));
$receiver = strtoupper(trim($data["receiver"]));
$rssi = (int)$data["rssi"];

$asset = getDisplayNameForBadge($conn, $tagId);

if(!$asset)
{
    echo json_encode([
        "success"=>false,
        "message"=>"Unknown Badge"
    ]);

    exit();
}

$assetTagNumber = $asset["asset_tag_number"];
$deviceName = $asset["device_name"];

// ======================================
// SAVE RSSI FROM THIS RECEIVER
// ======================================

date_default_timezone_set("Asia/Kolkata");
$currentTime = date("Y-m-d H:i:s");

$sql = "INSERT INTO tracker_readings
(tag_id, receiver, rssi, updated_at)
VALUES(?,?,?,?)
ON DUPLICATE KEY UPDATE
rssi = VALUES(rssi),
updated_at = VALUES(updated_at)";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "ssis",
    $tagId,
    $receiver,
    $rssi,
    $currentTime
);

mysqli_stmt_execute($stmt);

// ======================================
// FIND STRONGEST RECEIVER
// ======================================

$sql = "SELECT receiver, rssi
FROM tracker_readings
WHERE tag_id = ?
ORDER BY rssi DESC
LIMIT 1";

$stmt = mysqli_prepare($conn,$sql);

mysqli_stmt_bind_param(
$stmt,
"s",
$tagId
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$bestReading = mysqli_fetch_assoc($result);

// var_dump($bestReading);
// exit();

if(!$bestReading)
{
    echo json_encode([
        "success"=>false,
        "message"=>"No RSSI readings found"
    ]);
    exit();
}


// Replace current values
$receiver = $bestReading["receiver"];
$rssi = $bestReading["rssi"];

// ------------------------------------------------


date_default_timezone_set("Asia/Kolkata");
$currentTime = date("Y-m-d H:i:s");

// ------------------------------------------------

$sql =
"SELECT
    am.location_id,
    CONCAT_WS(' - ', l.section_department, l.room) AS location
FROM antenna_mapping am
LEFT JOIN locations l
    ON am.location_id = l.location_id
WHERE am.antenna_id = ?";

$stmt =
mysqli_prepare(
$conn,
$sql
);

mysqli_stmt_bind_param(
$stmt,
"s",
$receiver
);

mysqli_stmt_execute($stmt);

$result =
mysqli_stmt_get_result($stmt);

$antenna =
mysqli_fetch_assoc($result);

if(!$antenna)
{
    echo json_encode([
        "success"=>false,
        "message"=>"Unknown Antenna"
    ]);

    exit();
}

$location =
$antenna["location"];



// -----------------------------------------------------

echo json_encode([

    "tagId"=>$tagId,

    "assetTagNumber"=>$assetTagNumber,

    "deviceName"=>$deviceName,

    "receiver"=>$receiver,

    "location"=>$location,

    "rssi"=>$rssi

]);


// tracker status ------------------------------------------------

$sql = "SELECT * FROM tracker_status WHERE tag_id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "s",
    $tagId
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$currentTracker = mysqli_fetch_assoc($result);


// status and history updates --------------------------------------------------------------

// ==========================================================
// CHECK IF THIS IS A NEW TRACKER
// ==========================================================

if(!$currentTracker)
{
    echo "NEW TRACKER<br>";
    // Calculate signal status from RSSI
    $status = getSignalStatus($rssi);

    // ------------------------------------------------------
    // INSERT CURRENT STATUS
    // ------------------------------------------------------

    $sql = "INSERT INTO tracker_status
    (
        tag_id,
        device_name,
        current_location,
        receiver,
        best_rssi,
        status,
        updated_at
    )
   VALUES
(
    ?,?,?,?,?,?,?
)";

    $stmt = mysqli_prepare($conn,$sql);

    mysqli_stmt_bind_param(
    $stmt,
    "ssssiss",
    $tagId,
    $deviceName,
    $location,
    $receiver,
    $rssi,
    $status,
    $currentTime
);

    mysqli_stmt_execute($stmt);

    // ------------------------------------------------------
    // CREATE FIRST HISTORY RECORD
    // Entry Time = NOW()
    // Exit Time = NULL
    // ------------------------------------------------------

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
        ?,?,?,?,?,NULL
    )";

    $stmt = mysqli_prepare($conn,$sql);

    mysqli_stmt_bind_param(
        $stmt,
        "sssss",
        $tagId,
        $deviceName,
        $location,
        $receiver,
        $currentTime
    );

    mysqli_stmt_execute($stmt);

    echo "Tracker Added";
}

else
{
    // ======================================================
    // TRACKER ALREADY EXISTS
    // ======================================================

    $status = getSignalStatus($rssi);

    $oldLocation = $currentTracker["current_location"];
    $oldStatus = $currentTracker["status"];
    // ------------------------------------------------------
    // IF LOCATION CHANGED
    // ------------------------------------------------------

    // if($oldLocation != $location)
    if($oldLocation != $location || $oldStatus == "Offline")
    {
        // Close previous history record
        // Set exit_time for the current location

        $sql = "UPDATE tracker_history
                SET exit_time = ?
                WHERE tag_id = ?
                AND exit_time IS NULL";

        $stmt = mysqli_prepare($conn,$sql);

        mysqli_stmt_bind_param(
                $stmt,
                "ss",
                $currentTime,
                $tagId
                );

        mysqli_stmt_execute($stmt);

        // -----------------------------------------------
        // Insert new history record
        // Entry = NOW()
        // Exit = NULL
        // -----------------------------------------------

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
    ?,?,?,?,?,NULL
)";

$stmt = mysqli_prepare($conn,$sql);

mysqli_stmt_bind_param(
    $stmt,
    "sssss",
    $tagId,
    $deviceName,
    $location,
    $receiver,
    $currentTime
);

mysqli_stmt_execute($stmt);
    }
    
    // ======================================================
    // ALWAYS UPDATE CURRENT STATUS
    // Whether location changed or not
    // ======================================================

    $sql = "UPDATE tracker_status
            SET
                current_location = ?,
                receiver = ?,
                best_rssi = ?,
                status = ?,
                updated_at = ?
            WHERE tag_id = ?";

    $stmt = mysqli_prepare($conn,$sql);


mysqli_stmt_bind_param(
        $stmt,
        "ssisss",
        $location,
        $receiver,
        $rssi,
        $status,
        $currentTime,
        $tagId,

    );

    mysqli_stmt_execute($stmt);
    echo mysqli_affected_rows($conn);
    echo "Tracker Updated";
}




// old --- check new or existing status and adding data to taracker history -------------------------------------------------

// if(!$currentTracker)
// {
//     $status = getSignalStatus($rssi);

//     $sql = "INSERT INTO tracker_status
//     (
//         tag_id,
//         device_name,
//         current_location,
//         receiver,
//         best_rssi,
//         status,
//         updated_at
//     )
//     VALUES
//     (
//         ?,?,?,?,?,?,NOW()
//     )";

//     $stmt = mysqli_prepare($conn, $sql);

//     mysqli_stmt_bind_param(
//         $stmt,
//         "ssssis",
//         $tagId,
//         $deviceName,
//         $location,
//         $receiver,
//         $rssi,
//         $status
//     );

//     mysqli_stmt_execute($stmt);

//     echo "Tracker Added";

// // adding to history ---------------------------------------

//     $sql = "INSERT INTO tracker_history
// (
//     tag_id,
//     device_name,
//     location,
//     receiver,
//     entry_time,
//     exit_time
// )
// VALUES
// (
//     ?,?,?,?,NOW(),NULL
// )";

// $stmt = mysqli_prepare($conn,$sql);

// mysqli_stmt_bind_param(
//     $stmt,
//     "ssss",
//     $tagId,
//     $deviceName,
//     $location,
//     $receiver
// );

// mysqli_stmt_execute($stmt);


// }



// else
// {

// // updating the status ------------------------------------------------------------------------------

//     $status = getSignalStatus($rssi);

//     $sql = "UPDATE tracker_status
//             SET
//                 current_location = ?,
//                 receiver = ?,
//                 best_rssi = ?,
//                 status = ?,
//                 updated_at = NOW()
//             WHERE tag_id = ?";

//     $stmt = mysqli_prepare($conn, $sql);

//     mysqli_stmt_bind_param(
//         $stmt,
//         "ssiss",
//         $location,
//         $receiver,
//         $rssi,
//         $status,
//         $tagId
//     );

//     mysqli_stmt_execute($stmt);

//     echo "Tracker Updated";

// //  updating the history ----------------------------------------------------------------------------------------

// $oldLocation = $currentTracker["current_location"];

// if($oldLocation != $location)
// {
//  // Tracker moved--------------------------------------------------------------

// $sql = "UPDATE tracker_history
// SET exit_time = NOW()
// WHERE tag_id = ?
// AND exit_time IS NULL";

// $stmt = mysqli_prepare($conn,$sql);

// mysqli_stmt_bind_param(
// $stmt,
// "s",
// $tagId
// );

// mysqli_stmt_execute($stmt);



// }
// else
// {
//     // Same location ----------------------------
//     $status = getSignalStatus($rssi);

// $sql = "UPDATE tracker_status
// SET
// current_location=?,
// receiver=?,
// best_rssi=?,
// status=?,
// updated_at=NOW()
// WHERE tag_id=?";

// $stmt = mysqli_prepare($conn,$sql);

// mysqli_stmt_bind_param(
// $stmt,
// "ssiss",
// $location,
// $receiver,
// $rssi,
// $status,
// $tagId
// );

// mysqli_stmt_execute($stmt);
// }




// }













