<!-- <!DOCTYPE html>
<html>

<head>

    <title>Tracker History</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex gap-2 mb-3">

    <a href="index.php" class="btn btn-secondary">
        ← Dashboard
    </a>

    <a id="graphBtn" class="btn btn-primary">
        📊 View Timeline
    </a>

</div>

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
    const BASE_URL =
    window.location.pathname.includes("/tracker_php/")
        ? "/tracker_php"
        : "";
        
console.log(document.getElementById("title"));
const tagId =
new URLSearchParams(window.location.search)
.get("tagId");

document.getElementById("graphBtn").href =
`${BASE_URL}/graph.php?tagId=${tagId}`;

document.getElementById("title").innerHTML =
`${tagId} History`;

// const API_URL =`http://localhost/tracker_php/api/history.php?tagId=${tagId}`;
// const API_URL =`/api/history.php?tagId=${tagId}`;

// auto update url


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

</html> -->



<!-- part -2 filtiring --------------------------------------- -->

<!-- <!DOCTYPE html>
<html>

<head>

    <title>Tracker History</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>

        .control-group{
            display:flex;
            align-items:center;
            gap:6px;
            background:white;
            border:1px solid #ddd;
            border-radius:6px;
            padding:6px 10px;
        }

        .control-group label{
            font-size:12px;
            color:#666;
            font-weight:600;
            white-space:nowrap;
        }

        .datetime-group{
            gap:8px;
        }

        .datetime-fields{
            display:flex;
            align-items:center;
            gap:4px;
        }

        input[type=date]{
            border:none;
            padding:2px 4px;
            font-size:14px;
        }

        .time-select{
            border:none;
            background:transparent;
            font-size:14px;
            padding:2px;
        }

        input[type=date]:focus,
        .time-select:focus{
            outline:none;
        }

    </style>

</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex gap-2 mb-3 flex-wrap align-items-center">

        <a href="index.php" class="btn btn-secondary">← Dashboard</a>
        <a id="graphBtn" class="btn btn-primary">📊 View Timeline</a>

        <div class="control-group">
            <label>Date</label>
            <input type="date" id="dateInput">
        </div>

        <div class="control-group">
            <label>From</label>
            <select id="startHour" class="time-select"></select>
            <select id="startMinute" class="time-select"></select>
            <select id="startAmPm" class="time-select"></select>
        </div>

        <div class="control-group">
            <label>To</label>
            <select id="endHour" class="time-select"></select>
            <select id="endMinute" class="time-select"></select>
            <select id="endAmPm" class="time-select"></select>
        </div>

        <button class="btn btn-primary" id="loadBtn">Load</button>

    </div>

    <h3 id="title">Tracker History</h3>

    <table class="table table-bordered bg-white">

        <thead class="table-dark">
            <tr>
                <th>Location</th>
                <th>Entry Time</th>
                <th>Exit Time</th>
            </tr>
        </thead>

        <tbody id="history"></tbody>

    </table>

</div>

<script>

/* =========================================================
   SETUP - page links and constants
   ========================================================= */

const BASE_URL = window.location.pathname.includes("/tracker_php/")
    ? "/tracker_php"
    : "";

const tagId = new URLSearchParams(window.location.search).get("tagId");

document.getElementById("graphBtn").href = `${BASE_URL}/graph.php?tagId=${tagId}`;
document.getElementById("title").innerHTML = "History";

const dateInput = document.getElementById("dateInput");
const startHour = document.getElementById("startHour");
const startMinute = document.getElementById("startMinute");
const startAmPm = document.getElementById("startAmPm");
const endHour = document.getElementById("endHour");
const endMinute = document.getElementById("endMinute");
const endAmPm = document.getElementById("endAmPm");

dateInput.value = new Date().toISOString().split("T")[0];

document.getElementById("loadBtn").addEventListener("click", loadHistory);


/* =========================================================
   TIME FILTER DROPDOWNS - same 12-hour style as the timeline
   page, so "From/To" always means the same thing everywhere
   ========================================================= */

function populateTimeDropdowns(hourSelect, minuteSelect, ampmSelect, defaultHour, defaultMinute, defaultAmPm)
{
    // standard 12-hour clock order: 12 comes first, then 1-11
    const hourOrder = [12, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];

    hourOrder.forEach(h => {
        hourSelect.add(new Option(String(h).padStart(2, "0"), h));
    });

    for (let m = 0; m < 60; m += 5)
    {
        minuteSelect.add(new Option(String(m).padStart(2, "0"), m));
    }

    ampmSelect.add(new Option("AM", "AM"));
    ampmSelect.add(new Option("PM", "PM"));

    hourSelect.value = defaultHour;
    minuteSelect.value = defaultMinute;
    ampmSelect.value = defaultAmPm;
}

populateTimeDropdowns(startHour, startMinute, startAmPm, 12, 0, "AM");
populateTimeDropdowns(endHour, endMinute, endAmPm, 11, 55, "PM");

// converts a 12-hour selection (e.g. 12, 30, "AM") into 24-hour "HH:MM"
function to24HourString(hour12, minute, ampm)
{
    let hour = Number(hour12) % 12;
    if (ampm === "PM") hour += 12;
    return `${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}`;
}

// combines the selected date with a HH:MM string into a real timestamp
function combineDateAndTime(dateValue, timeValue)
{
    return new Date(`${dateValue}T${timeValue}:00`).getTime();
}

function combineDateAndTimeForApi(dateValue, timeValue)
{
    return `${dateValue} ${timeValue}:00`;
}

// reads the From/To dropdowns and returns the chosen window as
// real timestamps, combined with the selected date
function getSelectedTimeWindow()
{
    const startTime = to24HourString(startHour.value, startMinute.value, startAmPm.value);
    const endTime = to24HourString(endHour.value, endMinute.value, endAmPm.value);

    return {
        start: combineDateAndTime(dateInput.value, startTime),
        end: combineDateAndTime(dateInput.value, endTime)
    };
}


/* =========================================================
   DATA LOADING
   ========================================================= */

function buildApiUrl()
{
    return `${BASE_URL}/api/history.php`
        + `?tagId=${tagId}`
        + `&startDate=${dateInput.value}`
        + `&endDate=${dateInput.value}`;
}

async function loadHistory()
{
    const tbody = document.getElementById("history");

    try
    {
        const res = await fetch(buildApiUrl());
        const json = await res.json();

        if (!json.success)
        {
            tbody.innerHTML = "<tr><td colspan='3' class='text-center'>Unable to load data</td></tr>";
            return;
        }

        const displayName = json.device_name || json.asset_tag_number || tagId;
        document.getElementById("title").innerHTML = `${displayName} History`;

        const visibleRows = filterByTimeOfDay(json.data);
        renderRows(visibleRows);
    }
    catch (err)
    {
        console.error(err);
        tbody.innerHTML = "<tr><td colspan='3' class='text-center text-danger'>Server error</td></tr>";
    }
}

// narrows the day's rows down to the chosen From/To time window.
// a row with no exit_time is still "open" (ongoing), so it's kept
// as long as it started before the window ends
function filterByTimeOfDay(rows)
{
    const window = getSelectedTimeWindow();

    return rows.filter(row => {
        const entry = new Date(row.entry_time).getTime();
        const exit = row.exit_time ? new Date(row.exit_time).getTime() : Infinity;

        return entry <= window.end && exit >= window.start;
    });
}


/* =========================================================
   RENDERING
   ========================================================= */

function renderRows(rows)
{
    const tbody = document.getElementById("history");
    tbody.innerHTML = "";

    if (rows.length === 0)
    {
        tbody.innerHTML = "<tr><td colspan='3' class='text-center'>No history in this time range</td></tr>";
        return;
    }

    rows.forEach(item => {

        const exitCell = item.exit_time
            ? formatTime(item.exit_time)
            : "<span class='text-success'>Current</span>";

        tbody.innerHTML += `
            <tr>
                <td>${item.location}</td>
                <td>${formatTime(item.entry_time)}</td>
                <td>${exitCell}</td>
            </tr>
        `;
    });
}

function formatTime(time)
{
    if (!time) return "-";
    return new Date(time).toLocaleString([], { hour12: true });
}


/* =========================================================
   INITIAL LOAD
   ========================================================= */

loadHistory();

</script>

</body>

</html> -->

<!-- part 3 end date --------------------------------------------------  -->


<!DOCTYPE html>
<html>

<head>

    <title>Tracker History</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>

        .control-group{
            display:flex;
            align-items:center;
            gap:6px;
            background:white;
            border:1px solid #ddd;
            border-radius:6px;
            padding:6px 10px;
        }

        .control-group label{
            font-size:12px;
            color:#666;
            font-weight:600;
            white-space:nowrap;
        }

        input[type=date]{
            border:none;
            padding:2px 4px;
            font-size:14px;
        }

        .time-select{
            border:none;
            background:transparent;
            font-size:14px;
            padding:2px;
        }

        input[type=date]:focus,
        .time-select:focus{
            outline:none;
        }

    </style>

</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex gap-2 mb-3 flex-wrap align-items-center">

        <a href="index.php" class="btn btn-secondary">← Dashboard</a>
        <a id="graphBtn" class="btn btn-primary">📊 View Timeline</a>

        <div class="control-group datetime-group">
            <label>From</label>
            <div class="datetime-fields">
            <input type="date" id="startDateInput">
            <select id="startHour" class="time-select"></select>
            <select id="startMinute" class="time-select"></select>
            <select id="startAmPm" class="time-select"></select>
            </div>
        </div>

        <div class="control-group datetime-group">
            <label>To</label>
            <div class="datetime-fields">
            <input type="date" id="endDateInput">
            <select id="endHour" class="time-select"></select>
            <select id="endMinute" class="time-select"></select>
            <select id="endAmPm" class="time-select"></select>
            </div>
        </div>

        <button class="btn btn-primary" id="loadBtn">Load</button>

    </div>

    <h3 id="title">Tracker History</h3>

    <table class="table table-bordered bg-white">

        <thead class="table-dark">
            <tr>
                <th>Location</th>
                <th>Entry Time</th>
                <th>Exit Time</th>
            </tr>
        </thead>

        <tbody id="history"></tbody>

    </table>

</div>

<script>

/* =========================================================
   SETUP - page links and constants
   ========================================================= */

const BASE_URL = window.location.pathname.includes("/tracker_php/")
    ? "/tracker_php"
    : "";

const tagId = new URLSearchParams(window.location.search).get("tagId");

document.getElementById("graphBtn").href = `${BASE_URL}/graph.php?tagId=${tagId}`;
document.getElementById("title").innerHTML = "History";

const startDateInput = document.getElementById("startDateInput");
const endDateInput = document.getElementById("endDateInput");
const startHour = document.getElementById("startHour");
const startMinute = document.getElementById("startMinute");
const startAmPm = document.getElementById("startAmPm");
const endHour = document.getElementById("endHour");
const endMinute = document.getElementById("endMinute");
const endAmPm = document.getElementById("endAmPm");

const today = new Date().toISOString().split("T")[0];
startDateInput.value = today;
endDateInput.value = today;

document.getElementById("loadBtn").addEventListener("click", loadHistory);


/* =========================================================
   TIME FILTER DROPDOWNS - same 12-hour style as the timeline
   page, so "From/To" always means the same thing everywhere
   ========================================================= */

function populateTimeDropdowns(hourSelect, minuteSelect, ampmSelect, defaultHour, defaultMinute, defaultAmPm)
{
    // standard 12-hour clock order: 12 comes first, then 1-11
    const hourOrder = [12, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];

    hourOrder.forEach(h => {
        hourSelect.add(new Option(String(h).padStart(2, "0"), h));
    });

    for (let m = 0; m < 60; m += 5)
    {
        minuteSelect.add(new Option(String(m).padStart(2, "0"), m));
    }

    ampmSelect.add(new Option("AM", "AM"));
    ampmSelect.add(new Option("PM", "PM"));

    hourSelect.value = defaultHour;
    minuteSelect.value = defaultMinute;
    ampmSelect.value = defaultAmPm;
}

populateTimeDropdowns(startHour, startMinute, startAmPm, 12, 0, "AM");
populateTimeDropdowns(endHour, endMinute, endAmPm, 11, 55, "PM");

// converts a 12-hour selection (e.g. 12, 30, "AM") into 24-hour "HH:MM"
function to24HourString(hour12, minute, ampm)
{
    let hour = Number(hour12) % 12;
    if (ampm === "PM") hour += 12;
    return `${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}`;
}

// combines the selected date with a HH:MM string into a real timestamp
function combineDateAndTime(dateValue, timeValue)
{
    return new Date(`${dateValue}T${timeValue}:00`).getTime();
}

function combineDateAndTimeForApi(dateValue, timeValue)
{
    return `${dateValue} ${timeValue}:00`;
}

// reads the dates + From/To dropdowns and returns the chosen window
// as real timestamps: Start Date+From .. End Date+To
function getSelectedTimeWindow()
{
    const startTime = to24HourString(startHour.value, startMinute.value, startAmPm.value);
    const endTime = to24HourString(endHour.value, endMinute.value, endAmPm.value);

    return {
        start: combineDateAndTime(startDateInput.value, startTime),
        end: combineDateAndTime(endDateInput.value, endTime)
    };
}

function getSelectedTimeWindowForApi()
{
    const startTime = to24HourString(startHour.value, startMinute.value, startAmPm.value);
    const endTime = to24HourString(endHour.value, endMinute.value, endAmPm.value);

    return {
        startAt: combineDateAndTimeForApi(startDateInput.value, startTime),
        endAt: combineDateAndTimeForApi(endDateInput.value, endTime)
    };
}


/* =========================================================
   DATA LOADING
   ========================================================= */

function buildApiUrl()
{
    const selectedWindow = getSelectedTimeWindowForApi();

    return `${BASE_URL}/api/history.php`
        + `?tagId=${tagId}`
        + `&startDate=${startDateInput.value}`
        + `&endDate=${endDateInput.value}`
        + `&startAt=${encodeURIComponent(selectedWindow.startAt)}`
        + `&endAt=${encodeURIComponent(selectedWindow.endAt)}`;
}

async function loadHistory()
{
    const tbody = document.getElementById("history");

    try
    {
        const res = await fetch(buildApiUrl());
        const json = await res.json();

        if (!json.success)
        {
            tbody.innerHTML = "<tr><td colspan='3' class='text-center'>Unable to load data</td></tr>";
            return;
        }

        const displayName = json.device_name || json.asset_tag_number || tagId;
        document.getElementById("title").innerHTML = `${displayName} History`;

        const visibleRows = filterByTimeOfDay(json.data);
        renderRows(visibleRows);
    }
    catch (err)
    {
        console.error(err);
        tbody.innerHTML = "<tr><td colspan='3' class='text-center text-danger'>Server error</td></tr>";
    }
}

// narrows the day's rows down to the chosen From/To time window.
// a row with no exit_time is still "open" (ongoing), so it's kept
// as long as it started before the window ends
function filterByTimeOfDay(rows)
{
    const window = getSelectedTimeWindow();

    return rows.filter(row => {
        const entry = new Date(row.entry_time).getTime();
        const exit = row.exit_time ? new Date(row.exit_time).getTime() : Infinity;

        return entry <= window.end && exit >= window.start;
    });
}


/* =========================================================
   RENDERING
   ========================================================= */

function renderRows(rows)
{
    const tbody = document.getElementById("history");
    tbody.innerHTML = "";

    if (rows.length === 0)
    {
        tbody.innerHTML = "<tr><td colspan='3' class='text-center'>No history in this time range</td></tr>";
        return;
    }

    rows.forEach(item => {

        const exitCell = item.exit_time
            ? formatTime(item.exit_time)
            : "<span class='text-success'>Current</span>";

        tbody.innerHTML += `
            <tr>
                <td>${item.location}</td>
                <td>${formatTime(item.entry_time)}</td>
                <td>${exitCell}</td>
            </tr>
        `;
    });
}

function formatTime(time)
{
    if (!time) return "-";
    return new Date(time).toLocaleString([], { hour12: true });
}


/* =========================================================
   INITIAL LOAD
   ========================================================= */

loadHistory();

</script>

</body>

</html>
