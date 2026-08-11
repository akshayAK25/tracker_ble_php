<!DOCTYPE html>
<html>

<head>

    <title>Tracker Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<script>

// const API_URL ="http://localhost/tracker_php/api/devices.php";
// const API_URL ="/api/devices.php";

// auto update url

const BASE_URL =
window.location.pathname.includes("/tracker_php/")
    ? "/tracker_php"
    : "";

const API_URL = `${BASE_URL}/api/devices.php`;



async function loadTrackers()
{
    const tbody = document.getElementById("trackers");

    if(!tbody)
    {
        console.error("Trackers table not found");
        return;
    }

    try
    {
        const res = await fetch(API_URL);
        const json = await res.json();

        tbody.innerHTML = "";

        if(!json.success)
        {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-danger">
                        ${json.message || "Unable to load trackers"}
                    </td>
                </tr>
            `;
            return;
        }

        json.data.forEach(tracker=>{

            const lastSeenTime = tracker.last_seen_time || tracker.entry_time || tracker.updated_at;
            const statusHtml = tracker.online
                ? "<span class='text-success'>🟢 Online</span>"
                : "<span class='text-danger'>🔴 Offline</span>";

            tbody.innerHTML += `

        <tr>

            <td>${tracker.asset_name || tracker.device_name || "-"}</td>

            <td>${tracker.asset_tag_no || tracker.asset_tag_number || "-"}</td>

            <td>${tracker.last_seen_location || tracker.current_location || "-"}</td>

            <td>${formatTime(lastSeenTime)}</td>

            <td>${statusHtml}</td>

            <td>

                <a
                href="history.php?tagId=${tracker.tag_id}"
                class="btn btn-primary btn-sm">

                History

                </a>

            </td>

        </tr>

        `;
        });
    }
    catch(err)
    {
        console.error(err);
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-danger">
                    Unable to load trackers
                </td>
            </tr>
        `;
    }
}

function formatTime(time)
{
    if(!time)
        return "-";

    return new Date(time.replace(" ", "T"))
        .toLocaleString("en-IN");
}

document.addEventListener("DOMContentLoaded", () => {
    loadTrackers();

    setInterval(
        loadTrackers,
        2000
    );
});

</script>

</body>

</html>


<body class="bg-light">

<div class="container mt-4">

    <h3 class="mb-4">
        Asset Tracker Dashboard
    </h3>

    <table class="table table-bordered table-hover bg-white">

        <thead class="table-dark">

            <tr>

                <th>Asset Name</th>

                <th>Asset Tag No</th>

                <th>Last Seen Location</th>

                <th>Last Seen Time</th>

                <th>Status</th>

                <th>History</th>

            </tr>

        </thead>

        <tbody id="trackers">

        </tbody>

    </table>

</div>
