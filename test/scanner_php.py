
import asyncio
import requests
from bleak import BleakScanner

TARGET_DEVICES = [ 
    "md1",
    "md2",
    "md3",
    "md4",
    "md5",
] 

# API_URL = "https://tracker-backend-1djh.onrender.com/rssi"

API_URL = "http://localhost/tracker_php/api/rssi.php"


# API_URL = "http://localhost:8080/api/rssi.php"

# API_URL = "https://tracker-ble-php.onrender.com/api/rssi.php"


RECEIVER_NAME = "A1"
# LOCATION = "Hall"

async def scan():

    while True:

        devices = await BleakScanner.discover(
            return_adv=True,
            timeout=2.0
        )

        print("\n--- TRACKERS FOUND ---")

        for address, (device, adv) in devices.items():

            if device.name and device.name.lower() in TARGET_DEVICES:

                data = {
                    "receiver": RECEIVER_NAME,
                    "device": device.name,
                    "rssi": adv.rssi,                
                }

                print(data)

                try:

                    response = requests.post(
                        API_URL,
                        json=data
                    )

                    print("Sent:", response.status_code)
                    print(response.status_code)
                    print(response.text)
                except Exception as e:

                    print("API Error:", e)

        await asyncio.sleep(2)

asyncio.run(scan())




