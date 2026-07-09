<!DOCTYPE html>
<html>

<head> 
    <title>Tracker</title>
</head>

<body>

<h2>Latest Tracker Data</h2>

<p><strong>Device:</strong> <span id="device"></span></p>

<p><strong>Receiver:</strong> <span id="receiver"></span></p>

<p><strong>RSSI:</strong> <span id="rssi"></span></p>

<script>

async function loadData() {

    try {

        const response = await fetch("latest_data.json");

        const data = await response.json();

        document.getElementById("device").innerText = data.device;

        document.getElementById("receiver").innerText = data.receiver;

        document.getElementById("rssi").innerText = data.rssi;

    } catch (e) {

        console.log("Waiting for data...");
    }

}

loadData();

setInterval(loadData, 1000);

</script>

</body>

</html>