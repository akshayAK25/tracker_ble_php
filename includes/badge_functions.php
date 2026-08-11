<?php

function findBadgeAsset($conn, $badgeId)
{
    $badgeId = strtoupper(trim($badgeId));

    $sql = "SELECT
                rb.badge_id_1,
                rb.badge_id_2,
                rb.badge_id_3,
                rb.asset_tag_number,
                dm.device_name
            FROM rtls_badge rb
            LEFT JOIN device_mapping dm
                ON rb.asset_tag_number = dm.asset_tag_number
            WHERE UPPER(rb.badge_id_1) = ?
                OR UPPER(rb.badge_id_2) = ?
                OR UPPER(rb.badge_id_3) = ?
            LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);

    if(!$stmt)
    {
        return null;
    }

    mysqli_stmt_bind_param(
        $stmt,
        "sss",
        $badgeId,
        $badgeId,
        $badgeId
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    return mysqli_fetch_assoc($result);
}

function getDisplayNameForBadge($conn, $badgeId)
{
    $asset = findBadgeAsset($conn, $badgeId);

    if(!$asset)
    {
        return null;
    }

    return [
        "badge_id" => strtoupper(trim($badgeId)),
        "asset_tag_number" => $asset["asset_tag_number"],
        "device_name" => $asset["device_name"] ?: $asset["asset_tag_number"]
    ];
}

?>
