
# import asyncio
# import requests
# from bleak import BleakScanner

# TARGET_DEVICES = [ 
#     "md1",
#     "md2",
#     "md3",
#     "md4",
#     "md5",
# ] 

# # API_URL = "https://tracker-backend-1djh.onrender.com/rssi"

# API_URL = "http://localhost/tracker_php/api/rssi.php"


# # API_URL = "http://localhost:8080/api/rssi.php"

# # API_URL = "https://tracker-ble-php.onrender.com/api/rssi.php"


# RECEIVER_NAME = "A1"
# # LOCATION = "Hall"

# async def scan():

#     while True:

#         devices = await BleakScanner.discover(
#             return_adv=True,
#             timeout=2.0
#         )

#         print("\n--- TRACKERS FOUND ---")

#         for address, (device, adv) in devices.items():

#             if device.name and device.name.lower() in TARGET_DEVICES:

#                 data = {
#                     "receiver": RECEIVER_NAME,
#                     "device": device.name,
#                     "rssi": adv.rssi,                
#                 }

#                 print(data)

#                 try:

#                     response = requests.post(
#                         API_URL,
#                         json=data
#                     )

#                     print("Sent:", response.status_code)
#                     print(response.status_code)
#                     print(response.text)
#                 except Exception as e:

#                     print("API Error:", e)

#         await asyncio.sleep(2)

# asyncio.run(scan())

# test  for mac ----------------------------------------------------------------------------------------------------------

# import asyncio
# from bleak import BleakScanner

# TARGET_MAC = "EA:26:73:F0:00:01".lower()

# def detection_callback(device, advertisement_data):
#     if device.address.lower() == TARGET_MAC:
#         print("=" * 50)
#         print("Beacon Found")
#         print("MAC :", device.address)
#         print("Name:", device.name)
#         print("RSSI:", advertisement_data.rssi)

#         print("\nService UUIDs:")
#         print(advertisement_data.service_uuids)

#         print("\nManufacturer Data:")
#         for company, data in advertisement_data.manufacturer_data.items():
#             print(f"Company: {company}")
#             print("Data:", data.hex())

#         print("\nService Data:")
#         for uuid, data in advertisement_data.service_data.items():
#             print(uuid, data.hex())

# async def main():
#     scanner = BleakScanner(detection_callback)
#     await scanner.start()

#     print("Scanning... Press Ctrl+C to stop.")

#     while True:
#         await asyncio.sleep(1)

# asyncio.run(main())

# with mac ------------------------------------------------------------------------------------------------



import asyncio
import requests
from bleak import BleakScanner

TARGET_MACS = [
    "ea:26:73:f0:00:01",
]

API_URL = "http://localhost:8080/api/rssi.php"

RECEIVER_NAME = "A1"


async def scan():

    while True:

        devices = await BleakScanner.discover(
            return_adv=True,
            timeout=2.0
        )

        print("\n--- BEACONS FOUND ---")

        for address, (device, adv) in devices.items():

            if address.lower() in TARGET_MACS:

                data = {
                    "receiver": RECEIVER_NAME,
                    "device": address,
                    "rssi": adv.rssi
                }

                print(data)

                try:

                    response = requests.post(
                        API_URL,
                        json=data
                    )

                    print(
                        "Sent:",
                        response.status_code,
                        response.text
                    )

                except Exception as e:

                    print("API Error:", e)

        await asyncio.sleep(2)


asyncio.run(scan())