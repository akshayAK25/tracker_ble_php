<!DOCTYPE html>
<html>

<head>

    <title>Tracker Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<script>

// const API_URL ="http://localhost/api/devices.php";
const API_URL ="/api/devices.php";

async function loadTrackers()
{
    const res = await fetch(API_URL);

    const json = await res.json();

    const tbody =
    document.getElementById("trackers");

    tbody.innerHTML = "";

    json.data.forEach(tracker=>{

        tbody.innerHTML += `

        <tr>

            <td>${tracker.device_name}</td>

            <td>${tracker.current_location}</td>

            <td>

                ${formatTime(tracker.entry_time)}

            </td>

            <td>

                ${tracker.exit_time
                    ? formatTime(tracker.exit_time)
                    : "<span class='text-success'>Current</span>"}

            </td>

            <td>

                ${tracker.status}

            </td>

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

function formatTime(time)
{
    if(!time)
        return "-";

    return new Date(time)
        .toLocaleString();
}

loadTrackers();

setInterval(
    loadTrackers,
    2000
);

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

                <th>Asset</th>

                <th>Location</th>

                <th>Entry Time</th>

                <th>Exit Time</th>

                <th>Status</th>

                <th></th>

            </tr>

        </thead>

        <tbody id="trackers">

        </tbody>

    </table>

</div>