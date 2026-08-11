<!-- <!DOCTYPE html>
<html>

<head>
    <title>Asset Timeline</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>

   body{
    background:#f5f5f5;
}

.card{
    border-radius:4px;
}




.location-cell{

    width:180px;

    padding-left:12px !important;

    font-weight:600;

    background:#fff;

}


.location-header{

    width:180px;

    padding:14px;

    border-right:1px solid #dee2e6;

    background:#fafafa;

}


.location-name{

    width:180px;

    padding:12px;

    border-right:1px solid #dee2e6;

    font-weight:500;

}

.timeline-container{

    display:flex;

    border:1px solid #dcdcdc;

    background:#fff;

}

/* LEFT */

.timeline-left{

    width:180px;

    flex-shrink:0;

    border-right:1px solid #dcdcdc;

}

.left-header{

    height:48px;

    display:flex;

    align-items:center;

    padding-left:14px;

    font-weight:600;

    border-bottom:1px solid #dcdcdc;

    background:#fafafa;

}

.location-row{

    height:42px;

    display:flex;

    align-items:center;

    padding-left:14px;

    border-bottom:1px solid #ececec;

}

/* RIGHT */

.timeline-right{

    flex:1;

    overflow-x:auto;

    overflow-y:hidden;

}

.timeline-scroll{

    width:3000px;

}

.time-header{

    height:48px;

    display:flex;

    align-items:center;

    padding-left:14px;

    font-weight:600;

    border-bottom:1px solid #dcdcdc;

    background:#fafafa;

}

.timeline-row{

    position:relative;

    height:42px;

    border-bottom:1px solid #ececec;

    background:#fff;

}


.visit-bar{

    position:absolute;

    top:6px;

    height:30px;

    background:#4b6cb7;

    border-radius:2px;

}


    </style>

</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <a href="index.php" class="btn btn-secondary">
                Dashboard
            </a>

            <a id="historyBtn" class="btn btn-outline-secondary">
                History
            </a>
        </div>

        <h3 id="title" class="mb-0">
            Asset Timeline
        </h3>

    </div>

    <div class="card">

        <div class="card-body">

            <div id="timeline">

            </div>

        </div>

    </div>

</div>

<script>

const tagId =
new URLSearchParams(window.location.search)
.get("tagId");

const BASE_URL =
window.location.pathname.includes("/tracker_php/")
?
"/tracker_php"
:
"";

document.getElementById("title").innerHTML =
`${tagId} Timeline`;

document.getElementById("historyBtn").href =
`${BASE_URL}/history.php?tagId=${tagId}`;

const API_URL =
`${BASE_URL}/api/graph.php?tagId=${tagId}`;


async function loadTimeline()
{
    try
    {
        const res = await fetch(API_URL);

        const json = await res.json();

        console.log(json);

        createTimeline(json.data);
    }
    catch(err)
    {
        console.error(err);
    }
}

loadTimeline();

function toTime(date)
{
    return new Date(date).getTime();
}

function map(value,min,max,width)
{
    return ((value-min)/(max-min))*width;
}



function createTimeline(data)
{
    if(data.length===0)
    {
        document.getElementById("timeline").innerHTML =
        "<div class='text-center p-5'>No data available</div>";
        return;
    }


    data.forEach(item=>{

const row =
document.getElementById(
    "row-"+item.location.replace(/\s/g,'')
);

if(!row) return;

const left =
map(
    toTime(item.entry_time),
    start,
    end,
    timelineWidth
);

const width =
map(
    toTime(item.exit_time),
    start,
    end,
    timelineWidth
)-left;

const bar =
document.createElement("div");

bar.className="visit-bar";

bar.style.left=left+"px";

bar.style.width=Math.max(width,3)+"px";

row.appendChild(bar);

});

    const locations =
    [...new Set(data.map(x=>x.location))];

    const start =
    Math.min(...data.map(x=>toTime(x.entry_time)));

    const end =
    Math.max(...data.map(x=>toTime(x.exit_time)));

    const timelineWidth = 1800;


    let left="";
    let rightHeader="";
    let rightBody="";

    left += `
        <div class="left-header">
            Location
        </div>
    `;

    rightHeader += `
        <div class="time-header">
            Timeline
        </div>
    `;

    locations.forEach(location=>{

        left += `
            <div class="location-row">
                ${location}
            </div>
        `;

        rightBody += `
            <div
                class="timeline-row"
                id="row-${location.replace(/\s/g,'')}">
            </div>
        `;
    });

    document.getElementById("timeline").innerHTML = `

<div class="timeline-container">

    <div class="timeline-left">

        ${left}

    </div>

    <div class="timeline-right">

        <div class="timeline-scroll">

            ${rightHeader}

            ${rightBody}

        </div>

    </div>

</div>

`;

}

</script>

</body>

</html> -->


<!--  part  -------------------------------------------------------------- -->


<!--  part 2 ---------------------------------------- -->


<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Asset Timeline</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    background:#f3f4f6;
    font-family:"Segoe UI",Tahoma,Geneva,Verdana,sans-serif;
}

.wrapper{
    max-width:1500px;
    margin:auto;
    padding:25px;
}

/* ---------- toolbar ---------- */

.toolbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:12px;
    margin-bottom:16px;
}

.title{
    font-size:26px;
    font-weight:600;
    color:#222;
}

.controls{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    align-items:center;
}

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

input[type=date]:focus{
    outline:none;
}

.time-select{
    border:none;
    background:transparent;
    font-size:14px;
    padding:2px;
}

.time-select:focus{
    outline:none;
}

.btn{
    border-radius:4px;
}

.zoom-group .btn{
    padding:4px 10px;
    font-weight:600;
}

#zoomLevel{
    font-size:13px;
    color:#444;
    min-width:42px;
    text-align:center;
}

/* ---------- legend ---------- */

.legend{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    margin-bottom:14px;
    align-items:center;
}

.legend-chip{
    display:flex;
    align-items:center;
    gap:6px;
    padding:4px 10px;
    border-radius:20px;
    background:white;
    border:1px solid #ddd;
    font-size:12px;
    cursor:pointer;
    user-select:none;
    transition:.15s;
}

.legend-chip.selected{
    border-color:#2563eb;
    box-shadow:0 0 0 2px rgba(37,99,235,.14);
}

.legend-chip.inactive{
    opacity:.45;
}

.legend-dot{
    width:10px;
    height:10px;
    border-radius:50%;
    flex-shrink:0;
}

/* ---------- timeline card ---------- */

.card{
    border:none;
    border-radius:8px;
    box-shadow:0 2px 8px rgba(0,0,0,.08);
}

.card-body{
    padding:0;
}

.timeline-wrapper{
    display:flex;
    border-top:1px solid #ddd;
}

.left-panel{
    width:220px;
    flex-shrink:0;
    border-right:1px solid #d9d9d9;
    background:white;
}

.left-header{
    height:55px;
    display:flex;
    align-items:center;
    padding-left:18px;
    font-weight:600;
    background:#fafafa;
    border-bottom:1px solid #ddd;
}

.location{
    height:52px;
    display:flex;
    align-items:center;
    gap:8px;
    padding-left:18px;
    border-bottom:1px solid #ececec;
    font-weight:500;
}

.location .legend-dot{
    width:9px;
    height:9px;
}

.right-panel{
    overflow:auto;
    flex:1;
    background:white;
}

.timeline{
    position:relative;
}

.time-header{
    position:relative;
    height:68px;
    border-bottom:1px solid #ddd;
    background:#fafafa;
}

.grid{
    position:absolute;
    top:0;
    bottom:0;
    width:1px;
    background:#e6e6e6;
}

.grid.major{
    background:#cfcfcf;
}

.time-label{
    position:absolute;
    top:38px;
    font-size:12px;
    color:#555;
    transform:translateX(-50%);
    white-space:nowrap;
}

.date-label{
    position:absolute;
    top:10px;
    font-size:12px;
    font-weight:600;
    color:#222;
    transform:translateX(8px);
    white-space:nowrap;
}

.date-label.edge-end{
    transform:translateX(calc(-100% - 8px));
}

.row{
    position:relative;
    height:52px;
    border-bottom:1px solid #ececec;
}

.visit{
    position:absolute;
    height:32px;
    top:10px;
    border-radius:4px;
    cursor:pointer;
    transition:.2s;
}

.visit:hover{
    transform:scaleY(1.08);
    filter:brightness(.85);
}

.visit.no-signal{
    background:#9ca3af !important;
}

.visit.hidden{
    display:none;
}

.tooltip-box{
    position:absolute;
    background:#222;
    color:white;
    font-size:12px;
    padding:8px 10px;
    border-radius:5px;
    pointer-events:none;
    opacity:0;
    transition:.15s;
    z-index:1000;
    white-space:nowrap;
}

.empty-state{
    padding:60px 20px;
    text-align:center;
    color:#666;
}

</style>

</head>

<body>

<div class="wrapper">

<div class="toolbar">

<div class="controls">

<a class="btn btn-secondary" id="dashboardBtn">Dashboard</a>
<a class="btn btn-outline-secondary" id="historyBtn">History</a>

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

<div class="control-group zoom-group">
<button class="btn btn-outline-secondary" id="zoomOutBtn">&minus;</button>
<span id="zoomLevel">100%</span>
<button class="btn btn-outline-secondary" id="zoomInBtn">+</button>
<button class="btn btn-outline-secondary" id="zoomResetBtn">Reset</button>
</div>

</div>

<div class="title" id="pageTitle">Timeline</div>

</div>

<div class="legend" id="legend"></div>

<div class="card">
<div class="card-body">
<div id="timelineRoot"></div>
</div>
</div>

</div>

<div class="tooltip-box" id="tooltip"></div>

<script>

/* =========================================================
   SETUP - page links, inputs, constants
   ========================================================= */

const tagId = new URLSearchParams(window.location.search).get("tagId");

const BASE_URL = window.location.pathname.includes("/tracker_php/")
    ? "/tracker_php"
    : "";

document.getElementById("dashboardBtn").href = `${BASE_URL}/index.php`;
document.getElementById("historyBtn").href = `${BASE_URL}/history.php?tagId=${tagId}`;
document.getElementById("pageTitle").innerHTML = "Timeline";

const startDateInput = document.getElementById("startDateInput");
const endDateInput = document.getElementById("endDateInput");
const tooltip = document.getElementById("tooltip");
const legendBox = document.getElementById("legend");

const startHour = document.getElementById("startHour");
const startMinute = document.getElementById("startMinute");
const startAmPm = document.getElementById("startAmPm");
const endHour = document.getElementById("endHour");
const endMinute = document.getElementById("endMinute");
const endAmPm = document.getElementById("endAmPm");

const today = new Date().toISOString().split("T")[0];
startDateInput.value = today;
endDateInput.value = today;

// fills the From/To dropdowns with 12-hour options (1-12, 00-55, AM/PM)
// so the filters read the same way the timeline itself displays time
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

// base pixel width of the timeline before zoom is applied
const BASE_TIMELINE_WIDTH = 2400;

// how zoomed in we currently are (1 = 100%, 2 = 200% ...)
let zoomFactor = 1;

// the full dataset currently loaded from the API, so zoom/legend
// clicks can redraw instantly without asking the server again
let currentData = [];

// selected locations work like graph toggles: no selection means all
// locations are visible, then each chip click builds a focused view.
let selectedLocations = new Set();

// start/end of the visible window, in milliseconds since epoch
let timelineStart = 0;
let timelineEnd = 0;

// keeps each row's DOM element so visits can be dropped into it
let locationRows = {};

// a fixed, pleasant color palette. Locations are matched to a color
// by name, so the same location always gets the same color.
const COLOR_PALETTE = [
    "#2d6cdf","#e0673f","#2fa15a","#a855c7",
    "#d4a72c","#0aa8a8","#d6497f","#6b7fd7",
    "#c2703d","#3f9142","#8a5fd6","#4098c9"
];


/* =========================================================
   EVENTS
   ========================================================= */

document.getElementById("loadBtn").addEventListener("click", loadTimeline);
document.getElementById("zoomInBtn").addEventListener("click", () => changeZoom(0.5));
document.getElementById("zoomOutBtn").addEventListener("click", () => changeZoom(-0.5));
document.getElementById("zoomResetBtn").addEventListener("click", () => setZoom(1));

document.addEventListener("keydown", e => {
    if (e.key === "r") loadTimeline();
});

document.addEventListener("scroll", hideTooltip, true);
window.addEventListener("resize", hideTooltip);


/* =========================================================
   DATA LOADING
   ========================================================= */

function buildApiUrl()
{
    const selectedWindow = getSelectedTimeWindowForApi();

    return `${BASE_URL}/api/graph.php`
        + `?tagId=${tagId}`
        + `&startDate=${startDateInput.value}`
        + `&endDate=${endDateInput.value}`
        + `&startAt=${encodeURIComponent(selectedWindow.startAt)}`
        + `&endAt=${encodeURIComponent(selectedWindow.endAt)}`;
}

async function loadTimeline()
{
    const root = document.getElementById("timelineRoot");

    try
    {
        const response = await fetch(buildApiUrl());
        const json = await response.json();

        if (!json.success)
        {
            root.innerHTML = "<div class='empty-state'>Unable to load data</div>";
            return;
        }

        const displayName = json.device_name || json.asset_tag_number || tagId;
        document.getElementById("pageTitle").innerHTML = `${displayName} Timeline`;

        currentData = json.data;
        selectedLocations = new Set();
        renderTimeline();
    }
    catch (err)
    {
        console.error(err);
        root.innerHTML = "<div class='empty-state text-danger'>Server error</div>";
    }
}


/* =========================================================
   TIME HELPERS
   ========================================================= */

function toMillis(time)
{
    return new Date(time).getTime();
}

// turns a real timestamp into an x-pixel position on the timeline
function mapTimeToPixels(value)
{
    const width = BASE_TIMELINE_WIDTH * zoomFactor;
    return ((value - timelineStart) / (timelineEnd - timelineStart)) * width;
}

function secondsToText(seconds)
{
    seconds = Math.floor(seconds);
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;

    let txt = "";
    if (h) txt += `${h}h `;
    if (m) txt += `${m}m `;
    txt += `${s}s`;
    return txt;
}

function formatDate(time)
{
    return new Date(time).toLocaleString([], { hour12: true });
}

function formatAxisDate(time)
{
    return new Date(time).toLocaleDateString([], {
        month: "short",
        day: "numeric",
        year: "numeric"
    });
}

// combines the selected date with a HH:MM input into a real timestamp
function combineDateAndTime(dateValue, timeValue)
{
    return new Date(`${dateValue}T${timeValue}:00`).getTime();
}

function combineDateAndTimeForApi(dateValue, timeValue)
{
    return `${dateValue} ${timeValue}:00`;
}


/* =========================================================
   FILTERING - the Start/End date pick the days, the From/To
   time dropdowns pick where within those days the window
   begins and ends - together they let the user "zoom" into
   any fixed slice of time
   ========================================================= */

// reads the date + From/To dropdowns and returns the chosen window
// as real timestamps (milliseconds): Start Date+From .. End Date+To
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

// a visit still in progress has no exit_time yet - treat it as
// ongoing until this very moment, instead of an invalid/epoch time
function getVisitExitMillis(item)
{
    return item.exit_time ? toMillis(item.exit_time) : Date.now();
}

function getFilteredData()
{
    const window = getSelectedTimeWindow();

    return currentData.filter(item => {
        const entry = toMillis(item.entry_time);
        const exit = getVisitExitMillis(item);
        // keep any visit that overlaps the chosen time window at all
        return exit >= window.start
            && entry <= window.end
            && isLocationVisible(item.location);
    });
}


/* =========================================================
   COLORS
   ========================================================= */

function getColorForLocation(location)
{
    if (location.toLowerCase().includes("no signal"))
    {
        return "#9ca3af";
    }

    // simple, stable hash so a location always maps to the same color
    let hash = 0;
    for (let i = 0; i < location.length; i++)
    {
        hash = (hash * 31 + location.charCodeAt(i)) % COLOR_PALETTE.length;
    }
    return COLOR_PALETTE[hash];
}

// keeps normal locations in their natural order, but always pushes
// "No Signal" to the very end of the list/rows
function sortLocationsNoSignalLast(locations)
{
    return [...locations].sort((a, b) => {
        const aIsNoSignal = a.toLowerCase().includes("no signal") ? 1 : 0;
        const bIsNoSignal = b.toLowerCase().includes("no signal") ? 1 : 0;
        return aIsNoSignal - bIsNoSignal;
    });
}


/* =========================================================
   ZOOM
   ========================================================= */

function changeZoom(delta)
{
    setZoom(zoomFactor + delta);
}

function setZoom(newZoom)
{
    zoomFactor = Math.min(Math.max(newZoom, 1), 6);
    document.getElementById("zoomLevel").innerHTML = Math.round(zoomFactor * 100) + "%";
    renderTimeline();
}


/* =========================================================
   LEGEND - also doubles as a location selector: clicking a
   chip focuses that graph, then more chips can be added.
   Reset returns the chart to all locations.
   ========================================================= */

function renderLegend(locations)
{
    legendBox.innerHTML = "";

    const reset = document.createElement("button");
    reset.type = "button";
    reset.className = "btn btn-sm btn-outline-secondary";
    reset.innerHTML = "Reset";
    reset.disabled = selectedLocations.size === 0;
    reset.addEventListener("click", () => {
        selectedLocations = new Set();
        renderTimeline();
    });
    legendBox.appendChild(reset);

    locations.forEach(location => {

        const chip = document.createElement("div");
        chip.className = "legend-chip";
        chip.setAttribute("role", "button");

        if (selectedLocations.size > 0)
        {
            chip.classList.add(
                selectedLocations.has(location) ? "selected" : "inactive"
            );
        }

        const dot = document.createElement("span");
        dot.className = "legend-dot";
        dot.style.background = getColorForLocation(location);

        chip.appendChild(dot);
        chip.append(location);

        chip.addEventListener("click", () => toggleLocation(location));

        legendBox.appendChild(chip);
    });
}

function toggleLocation(location)
{
    if (selectedLocations.size === 0)
    {
        selectedLocations.add(location);
    }
    else if (selectedLocations.has(location))
    {
        selectedLocations.delete(location);
    }
    else
    {
        selectedLocations.add(location);
    }

    renderTimeline();
}

function isLocationVisible(location)
{
    return selectedLocations.size === 0 || selectedLocations.has(location);
}


/* =========================================================
   RENDERING
   ========================================================= */

function renderTimeline()
{
    const root = document.getElementById("timelineRoot");
    root.innerHTML = "";
    locationRows = {};

    if (currentData.length === 0)
    {
        root.innerHTML = "<div class='empty-state'>No history available</div>";
        legendBox.innerHTML = "";
        return;
    }

    const visibleData = getFilteredData();

    const allLocations = sortLocationsNoSignalLast([...new Set(currentData.map(x => x.location))]);
    renderLegend(allLocations);

    if (visibleData.length === 0)
    {
        root.innerHTML = "<div class='empty-state'>No visits in this time range</div>";
        return;
    }

    // the axis is fixed to exactly the window the user picked - this is
    // what makes "From 9:00 AM to 11:00 AM" actually zoom into that
    // slice, instead of the chart rescaling itself around the data
    const window = getSelectedTimeWindow();
    timelineStart = window.start;
    timelineEnd = window.end;

    if (timelineEnd <= timelineStart)
    {
        timelineEnd = timelineStart + 60000;
    }

    const locations = sortLocationsNoSignalLast([...new Set(visibleData.map(x => x.location))]);
    const timelineWidth = BASE_TIMELINE_WIDTH * zoomFactor;

    const wrapper = buildLayoutSkeleton(locations, timelineWidth);
    root.appendChild(wrapper);

    renderVisits(visibleData);

    setTimeout(scrollToFirstVisit, 50);
}

// creates the left location column + right scrollable timeline area
function buildLayoutSkeleton(locations, timelineWidth)
{
    const wrapper = document.createElement("div");
    wrapper.className = "timeline-wrapper";

    const left = buildLeftPanel(locations);
    const right = buildRightPanel(locations, timelineWidth);

    wrapper.appendChild(left);
    wrapper.appendChild(right);

    return wrapper;
}

function buildLeftPanel(locations)
{
    const left = document.createElement("div");
    left.className = "left-panel";

    const leftHeader = document.createElement("div");
    leftHeader.className = "left-header";
    leftHeader.innerHTML = "Location";
    left.appendChild(leftHeader);

    locations.forEach(location => {

        const row = document.createElement("div");
        row.className = "location";

        const dot = document.createElement("span");
        dot.className = "legend-dot";
        dot.style.background = getColorForLocation(location);

        row.appendChild(dot);
        row.append(location);

        left.appendChild(row);
    });

    return left;
}

function buildRightPanel(locations, timelineWidth)
{
    const right = document.createElement("div");
    right.className = "right-panel";

    const timeline = document.createElement("div");
    timeline.className = "timeline";
    timeline.style.width = timelineWidth + "px";

    timeline.appendChild(buildTimeHeader(timelineWidth));

    locations.forEach(location => {
        const row = document.createElement("div");
        row.className = "row";
        buildGridLines(row, timelineWidth);
        timeline.appendChild(row);
        locationRows[location] = row;
    });

    right.appendChild(timeline);
    return right;
}

function buildTimeHeader(timelineWidth)
{
    const header = document.createElement("div");
    header.className = "time-header";

    const total = timelineEnd - timelineStart;

    buildDateLabels(header, timelineWidth);

    for (let i = 0; i <= 24; i++)
    {
        const x = (i / 24) * timelineWidth;

        const line = document.createElement("div");
        line.className = "grid major";
        line.style.left = x + "px";
        header.appendChild(line);

        if (i < 24)
        {
            const label = document.createElement("div");
            label.className = "time-label";
            label.style.left = x + "px";

            // the first label sits right at the edge, so it should not be
            // centered like the rest or its left half gets clipped off
            if (i === 0) label.style.transform = "translateX(0)";

            const time = new Date(timelineStart + (total * i / 24));
            label.innerHTML = time.toLocaleTimeString([], { hour: "numeric", minute: "2-digit", hour12: true });

            header.appendChild(label);
        }
    }

    return header;
}

function buildGridLines(row, timelineWidth)
{
    for (let i = 0; i <= 24; i++)
    {
        const grid = document.createElement("div");
        grid.className = "grid";
        grid.style.left = (i / 24) * timelineWidth + "px";
        row.appendChild(grid);
    }

    buildDateGridLines(row, timelineWidth);
}

function buildDateLabels(header, timelineWidth)
{
    getDateBoundaries().forEach((boundary, index, boundaries) => {
        const label = document.createElement("div");
        label.className = "date-label";
        label.style.left = mapTimeToPixels(boundary) + "px";
        label.innerHTML = formatAxisDate(boundary);

        if (index === boundaries.length - 1 && boundary === timelineEnd)
        {
            label.classList.add("edge-end");
        }

        header.appendChild(label);
    });
}

function buildDateGridLines(row, timelineWidth)
{
    getDateBoundaries()
        .filter(boundary => boundary > timelineStart && boundary < timelineEnd)
        .forEach(boundary => {
            const grid = document.createElement("div");
            grid.className = "grid major";
            grid.style.left = mapTimeToPixels(boundary) + "px";
            row.appendChild(grid);
        });
}

function getDateBoundaries()
{
    const boundaries = [timelineStart];
    const cursor = new Date(timelineStart);
    cursor.setHours(0, 0, 0, 0);

    if (cursor.getTime() <= timelineStart)
    {
        cursor.setDate(cursor.getDate() + 1);
    }

    while (cursor.getTime() < timelineEnd)
    {
        boundaries.push(cursor.getTime());
        cursor.setDate(cursor.getDate() + 1);
    }

    if (
        timelineEnd - boundaries[boundaries.length - 1] > 3600000
        && formatAxisDate(timelineEnd) !== formatAxisDate(boundaries[boundaries.length - 1])
    )
    {
        boundaries.push(timelineEnd);
    }

    return boundaries;
}

function renderVisits(data)
{
    const timelineWidth = BASE_TIMELINE_WIDTH * zoomFactor;

    data.forEach(item => {

        const row = locationRows[item.location];
        if (!row) return;

        // clamp to the visible frame - a visit can start earlier or end
        // later than the chosen window (that's why it still shows up),
        // but it should visually stop right at the frame's edges
        const rawStart = mapTimeToPixels(toMillis(item.entry_time));
        const rawEnd = mapTimeToPixels(getVisitExitMillis(item));
        const start = Math.max(rawStart, 0);
        const end = Math.min(rawEnd, timelineWidth);
        const width = Math.max(end - start, 4);

        const visit = document.createElement("div");
        visit.className = "visit";
        visit.style.background = getColorForLocation(item.location);

        if (item.location.toLowerCase().includes("no signal"))
        {
            visit.classList.add("no-signal");
        }

        visit.style.left = start + "px";
        visit.style.width = width + "px";

        visit.dataset.location = item.location;
        visit.dataset.entry = item.entry_time;
        visit.dataset.exit = item.exit_time || "";
        visit.dataset.duration = item.duration || Math.floor((getVisitExitMillis(item) - toMillis(item.entry_time)) / 1000);

        visit.addEventListener("mousemove", showTooltip);
        visit.addEventListener("mouseleave", hideTooltip);

        row.appendChild(visit);
    });
}


/* =========================================================
   TOOLTIP
   ========================================================= */

function showTooltip(e)
{
    const bar = e.target;

    const exitText = bar.dataset.exit
        ? formatDate(bar.dataset.exit)
        : "Current";

    tooltip.innerHTML = `
        <b>${bar.dataset.location}</b><br>
        Entry : ${formatDate(bar.dataset.entry)}<br>
        Exit : ${exitText}<br>
        Duration : ${secondsToText(bar.dataset.duration)}
    `;

    tooltip.style.left = (e.pageX + 15) + "px";
    tooltip.style.top = (e.pageY - 15) + "px";
    tooltip.style.opacity = 1;
}

function hideTooltip()
{
    tooltip.style.opacity = 0;
}


/* =========================================================
   SCROLL TO FIRST VISIT
   ========================================================= */

function scrollToFirstVisit()
{
    const panel = document.querySelector(".right-panel");
    const first = document.querySelector(".visit");
    if (!panel || !first) return;

    const left = parseFloat(first.style.left);
    panel.scrollLeft = Math.max(left - 150, 0);
}


/* =========================================================
   INITIAL LOAD
   ========================================================= */

loadTimeline();

</script>

</body>

</html>
