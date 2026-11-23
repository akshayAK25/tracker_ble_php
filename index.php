<!DOCTYPE html>
<html>

<head>
    <title>Asset Tracker Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table th, .table td {
            vertical-align: middle;
        }
        .rssi-meter-box {
            display: flex;
            flex-direction: column;
            gap: 2px;
            width: 20px;
        }
        .rssi-bar {
            height: 3.5px;
            border-radius: 1px;
            transition: background 0.3s ease;
        }
    </style>
</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Asset Tracker Dashboard</h3>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered bg-white mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Asset Name</th>
                            <th>Asset Tag No</th>
                            <th>Last Seen Location</th>
                            <th>Last Seen Time</th>
                            <th>RSSI Signal Proximity</th>
                            <th>Status</th>
                            <th>History</th>
                        </tr>
                    </thead>
                    <tbody id="trackers">
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                Loading trackers...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>

const BASE_URL = window.location.pathname.includes("/tracker_php/")
    ? "/tracker_php"
    : "";

const API_URL = `${BASE_URL}/api/devices.php`;

function renderRssiIndicator(rssi, isOnline)
{
    const inactiveColor = "#e2e8f0";

    if (!isOnline || rssi === null || rssi === undefined || rssi === "" || isNaN(rssi)) {
        return `
            <div class="d-inline-flex align-items-center gap-2" title="No signal">
                <div class="rssi-meter-box">
                    <div class="rssi-bar" style="background: ${inactiveColor};"></div>
                    <div class="rssi-bar" style="background: ${inactiveColor};"></div>
                    <div class="rssi-bar" style="background: ${inactiveColor};"></div>
                    <div class="rssi-bar" style="background: ${inactiveColor};"></div>
                    <div class="rssi-bar" style="background: ${inactiveColor};"></div>
                    <div class="rssi-bar" style="background: ${inactiveColor};"></div>
                </div>
                <span class="text-muted" style="font-size: 13px; font-weight: 500;">-</span>
            </div>
        `;
    }

    const val = parseInt(rssi, 10);

    // 6-bar scale matching the reference image from top (green) to bottom (red):
    // >= -50 dBm: 6 bars (Green, Yellow, Amber, Orange, Deep Orange, Red)
    // >= -60 dBm: 5 bars
    // >= -70 dBm: 4 bars
    // >= -78 dBm: 3 bars
    // >= -86 dBm: 2 bars
    // <  -86 dBm: 1 bar (Red only)
    let activeBars = 1;
    if (val >= -50) activeBars = 6;
    else if (val >= -60) activeBars = 5;
    else if (val >= -70) activeBars = 4;
    else if (val >= -78) activeBars = 3;
    else if (val >= -86) activeBars = 2;
    else activeBars = 1;

    const colors = [
        activeBars >= 6 ? "#22c55e" : inactiveColor, // Top (Bar 6): Green
        activeBars >= 5 ? "#eab308" : inactiveColor, // Bar 5: Yellow
        activeBars >= 4 ? "#f59e0b" : inactiveColor, // Bar 4: Amber
        activeBars >= 3 ? "#f97316" : inactiveColor, // Bar 3: Orange
        activeBars >= 2 ? "#ea580c" : inactiveColor, // Bar 2: Deep Orange
        activeBars >= 1 ? "#ef4444" : inactiveColor  // Bottom (Bar 1): Red
    ];

    return `
        <div class="d-inline-flex align-items-center gap-2" title="Signal: ${val} dBm">
            <div class="rssi-meter-box">
                <div class="rssi-bar" style="background: ${colors[0]};"></div>
                <div class="rssi-bar" style="background: ${colors[1]};"></div>
                <div class="rssi-bar" style="background: ${colors[2]};"></div>
                <div class="rssi-bar" style="background: ${colors[3]};"></div>
                <div class="rssi-bar" style="background: ${colors[4]};"></div>
                <div class="rssi-bar" style="background: ${colors[5]};"></div>
            </div>
            <span style="font-size: 13px; font-weight: 600; color: #1e293b; font-family: monospace;">${val} dBm</span>
        </div>
    `;
}

async function loadTrackers()
{
    const tbody = document.getElementById("trackers");
    if(!tbody) return;

    try
    {
        const res = await fetch(API_URL);
        const json = await res.json();

        if(!json.success)
        {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4 text-danger">
                        ${json.message || "Unable to load trackers"}
                    </td>
                </tr>
            `;
            return;
        }

        if(!json.data || json.data.length === 0)
        {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        No active trackers found.
                    </td>
                </tr>
            `;
            return;
        }

        let rowsHtml = "";

        json.data.forEach(tracker => {
            const lastSeenTime = tracker.last_seen_time || tracker.entry_time || tracker.updated_at;
            const statusHtml = tracker.online
                ? "<span class='text-success fw-semibold'>🟢 Online</span>"
                : "<span class='text-danger fw-semibold'>🔴 Offline</span>";

            const rssiHtml = renderRssiIndicator(tracker.best_rssi, tracker.online);

            rowsHtml += `
                <tr>
                    <td class="fw-medium">${tracker.asset_name || tracker.device_name || "-"}</td>
                    <td><code>${tracker.asset_tag_no || tracker.asset_tag_number || "-"}</code></td>
                    <td>${tracker.last_seen_location || tracker.current_location || "-"}</td>
                    <td>${formatTime(lastSeenTime)}</td>
                    <td>${rssiHtml}</td>
                    <td>${statusHtml}</td>
                    <td>
                        <a href="history.php?tagId=${tracker.tag_id}" class="btn btn-outline-primary btn-sm">
                            History
                        </a>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = rowsHtml;
    }
    catch(err)
    {
        console.error("Dashboard error:", err);
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4 text-danger">
                    Error loading trackers from server
                </td>
            </tr>
        `;
    }
}

function formatTime(time)
{
    if(!time) return "-";
    return new Date(time.replace(" ", "T")).toLocaleString("en-IN");
}

document.addEventListener("DOMContentLoaded", () => {
    loadTrackers();
    setInterval(loadTrackers, 2000);
});

</script>

</body>
</html>
