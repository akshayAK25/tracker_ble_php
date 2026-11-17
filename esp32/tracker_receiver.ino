#include <WiFi.h>
#include <HTTPClient.h>
#include <BLEDevice.h>
#include <BLEScan.h>
#include <BLEAdvertisedDevice.h>
#include <vector>

// =====================================================
// WIFI SETTINGS
// =====================================================

const char* WIFI_SSID     = "bala";
const char* WIFI_PASSWORD = "Babi@9133";

// =====================================================
// PHP API
// =====================================================

const char* API_URL = "https://tracker-ble-php.onrender.com/api/rssi.php";

// =====================================================
// RECEIVER NAME
// =====================================================

const char* RECEIVER_NAME = "A3";

// =====================================================
// TARGET BLE MAC ADDRESSES
// =====================================================

const char* TARGET_MACS[] = {
  "ea:26:73:f0:00:01",
  "ea:26:73:f0:00:02",
  "ea:26:73:f0:00:03",
  "ea:26:81:f0:00:06",
  "ea:26:81:f0:00:0f"
};

// Automatically calculate number of MAC addresses
const int TARGET_COUNT = sizeof(TARGET_MACS) / sizeof(TARGET_MACS[0]);

// =====================================================
// DATA STRUCTURE FOR DETECTED DEVICES
// =====================================================

struct DetectedBLE {
  String address;
  int rssi;
};

// =====================================================
// BLE
// =====================================================

BLEScan* pBLEScan;

// =====================================================
// CHECK IF MAC ADDRESS IS IN TARGET LIST
// =====================================================

bool isTargetDevice(String address)
{
  address.toLowerCase();

  for (int i = 0; i < TARGET_COUNT; i++)
  {
    if (address == TARGET_MACS[i])
    {
      return true;
    }
  }

  return false;
}

// =====================================================
// SEND BATCH RSSI DATA TO PHP API (ONE HTTP REQUEST)
// =====================================================

void sendBatchRSSI(const std::vector<DetectedBLE>& devices)
{
  if (devices.empty())
  {
    return;
  }

  // Check Wi-Fi
  if (WiFi.status() != WL_CONNECTED)
  {
    Serial.println("WiFi disconnected.");
    return;
  }

  HTTPClient http;

  Serial.println();
  Serial.print("Sending batch of ");
  Serial.print(devices.size());
  Serial.println(" device(s) to server...");

  // Start HTTP connection
  http.begin(API_URL);

  // Tell server that we are sending JSON
  http.addHeader("Content-Type", "application/json");

  // Build JSON:
  // {
  //   "receiver": "A3",
  //   "devices": [
  //     {"device": "ea:26:73:f0:00:01", "rssi": -52},
  //     {"device": "ea:26:73:f0:00:02", "rssi": -64}
  //   ]
  // }
  String jsonData = "{\"receiver\":\"" + String(RECEIVER_NAME) + "\",\"devices\":[";
  for (size_t i = 0; i < devices.size(); i++)
  {
    if (i > 0)
    {
      jsonData += ",";
    }
    jsonData += "{\"device\":\"" + devices[i].address + "\",\"rssi\":" + String(devices[i].rssi) + "}";
  }
  jsonData += "]}";

  // Print JSON payload
  Serial.println("JSON Payload:");
  Serial.println(jsonData);

  // Send single POST request
  int responseCode = http.POST(jsonData);

  // Print HTTP response
  Serial.print("HTTP Response Code: ");
  Serial.println(responseCode);

  if (responseCode > 0)
  {
    String response = http.getString();
    Serial.println("Server Response:");
    Serial.println(response);
  }
  else
  {
    Serial.print("HTTP Error: ");
    Serial.println(http.errorToString(responseCode));
  }

  // Close HTTP connection
  http.end();
}

// =====================================================
// CONNECT TO WIFI
// =====================================================

void connectWiFi()
{
  Serial.println();
  Serial.println("==============================");
  Serial.println("Connecting to WiFi");
  Serial.println("==============================");

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  while (WiFi.status() != WL_CONNECTED)
  {
    delay(500);
    Serial.print(".");
  }

  Serial.println();
  Serial.println("WiFi connected!");
  Serial.print("IP Address: ");
  Serial.println(WiFi.localIP());
}

// =====================================================
// SETUP
// =====================================================

void setup()
{
  Serial.begin(115200);

  delay(1000);

  Serial.println();
  Serial.println();
  Serial.println("================================");
  Serial.println("ESP32 BLE TRACKER RECEIVER (BATCH)");
  Serial.println("================================");

  // ---------------------------------------------------
  // WIFI
  // ---------------------------------------------------
  connectWiFi();

  // ---------------------------------------------------
  // PRINT TARGET MAC ADDRESSES
  // ---------------------------------------------------
  Serial.println();
  Serial.println("Target BLE devices:");
  for (int i = 0; i < TARGET_COUNT; i++)
  {
    Serial.print(i + 1);
    Serial.print(": ");
    Serial.println(TARGET_MACS[i]);
  }

  // ---------------------------------------------------
  // START BLE
  // ---------------------------------------------------
  Serial.println();
  Serial.println("Starting BLE scanner...");

  BLEDevice::init("");
  pBLEScan = BLEDevice::getScan();

  // Active scan gives more advertising information
  pBLEScan->setActiveScan(true);

  // BLE scan interval and window
  pBLEScan->setInterval(100);
  pBLEScan->setWindow(80);

  Serial.println("BLE scanner ready.");
}

// =====================================================
// MAIN LOOP
// =====================================================

void loop()
{
  Serial.println();
  Serial.println();
  Serial.println("--------------------------------");
  Serial.println("Starting BLE scan...");
  Serial.println("--------------------------------");

  // ---------------------------------------------------
  // SCAN FOR 2 SECONDS
  // ---------------------------------------------------
  BLEScanResults* foundDevices = pBLEScan->start(2, false);

  int deviceCount = foundDevices->getCount();
  Serial.print("BLE devices found: ");
  Serial.println(deviceCount);

  // Container to collect all detected targets in this single scan period
  std::vector<DetectedBLE> detectedTargets;

  // ---------------------------------------------------
  // CHECK ALL DEVICES
  // ---------------------------------------------------
  for (int i = 0; i < deviceCount; i++)
  {
    BLEAdvertisedDevice device = foundDevices->getDevice(i);

    // Get MAC address in lowercase
    String address = device.getAddress().toString().c_str();
    address.toLowerCase();

    // Get RSSI
    int rssi = device.getRSSI();

    Serial.print("Device: ");
    Serial.print(address);
    Serial.print(" | RSSI: ");
    Serial.println(rssi);

    // -------------------------------------------------
    // CHECK IF TARGET MAC
    // -------------------------------------------------
    if (isTargetDevice(address))
    {
      // Deduplicate: if already seen in this scan, keep the strongest RSSI
      bool alreadyAdded = false;
      for (size_t j = 0; j < detectedTargets.size(); j++)
      {
        if (detectedTargets[j].address == address)
        {
          if (rssi > detectedTargets[j].rssi)
          {
            detectedTargets[j].rssi = rssi;
          }
          alreadyAdded = true;
          break;
        }
      }

      if (!alreadyAdded)
      {
        detectedTargets.push_back({address, rssi});
      }
    }
  }

  // ---------------------------------------------------
  // SEND BATCH IF TARGETS FOUND
  // ---------------------------------------------------
  if (!detectedTargets.empty())
  {
    Serial.println();
    Serial.println("******** TARGET(S) FOUND ********");
    Serial.print("Receiver: ");
    Serial.println(RECEIVER_NAME);
    Serial.print("Count: ");
    Serial.println(detectedTargets.size());

    for (size_t i = 0; i < detectedTargets.size(); i++)
    {
      Serial.print("  [");
      Serial.print(i + 1);
      Serial.print("] Device: ");
      Serial.print(detectedTargets[i].address);
      Serial.print(" | RSSI: ");
      Serial.println(detectedTargets[i].rssi);
    }
    Serial.println("*********************************");

    // Send all target devices in a single HTTP POST request
    sendBatchRSSI(detectedTargets);
  }
  else
  {
    Serial.println();
    Serial.println("No target BLE devices found.");
  }

  // ---------------------------------------------------
  // CLEAR BLE RESULTS
  // ---------------------------------------------------
  pBLEScan->clearResults();

  // ---------------------------------------------------
  // WAIT BEFORE NEXT SCAN
  // ---------------------------------------------------
  delay(600);
}
