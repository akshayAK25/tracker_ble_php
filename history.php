<!DOCTYPE html>
<html>

<head>

    <title>Tracker History</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

<div class="container mt-4">

    <a href="index.php" class="btn btn-secondary mb-3">
        ← Dashboard
    </a>

    <h3 id="title">
        Tracker History
    </h3>

    <table class="table table-bordered bg-white">

        <thead class="table-dark">

            <tr>

                <th>Location</th>

                <th>Entry Time</th>

                <th>Exit Time</th>

            </tr>

        </thead>

        <tbody id="history">

        </tbody>

    </table>

</div>

<script>
console.log(document.getElementById("title"));
const tagId =
new URLSearchParams(window.location.search)
.get("tagId");

document.getElementById("title").innerHTML =
`${tagId} History`;

// const API_URL =`http://localhost/tracker_php/api/history.php?tagId=${tagId}`;
// const API_URL =`/api/history.php?tagId=${tagId}`;

// auto update url

const BASE_URL =
window.location.pathname.includes("/tracker_php/")
    ? "/tracker_php"
    : "";

const API_URL =
`${BASE_URL}/api/history.php?tagId=${tagId}`;



async function loadHistory()
{
    const res = await fetch(API_URL);

    const json = await res.json();

    const tbody =
    document.getElementById("history");

    tbody.innerHTML = "";

    json.data.forEach(item=>{

        tbody.innerHTML += `

        <tr>

            <td>

                ${item.location}

            </td>

            <td>

                ${formatTime(item.entry_time)}

            </td>

            <td>

                ${
                    item.exit_time
                    ?
                    formatTime(item.exit_time)
                    :
                    "<span class='text-success'>Current</span>"
                }

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

loadHistory();

</script>

</body>

</html>