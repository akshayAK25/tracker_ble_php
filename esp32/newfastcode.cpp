
#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <NimBLEDevice.h>
#include <vector>

const char* WIFI_SSID = "Shekar";
const char* WIFI_PASSWORD = "shekar00000?";
// const char* API_URL = "https://onrender.com";

// const char* WIFI_SSID = "bala";
// const char* WIFI_PASSWORD = "Babi@9133";
const char* API_URL = "https://tracker-ble-php.onrender.com/api/rssi.php";


const char* RECEIVER_NAME = "A3";

const char* TARGET_MACS[] = {
  "ea:26:73:f0:00:01",
  "ea:26:73:f0:00:02",
  "ea:26:73:f0:00:03",
  "ea:26:81:f0:00:17",
  "ea:26:81:f0:00:06",
  "ea:26:81:f0:00:0f"
};
const int TARGET_COUNT = sizeof(TARGET_MACS) / sizeof(TARGET_MACS[0]);

struct DetectedBLE {
  String address;
  int rssi;
};

String latestJsonPayload = "";
SemaphoreHandle_t payloadMutex;

WiFiClientSecure client;
HTTPClient http;
NimBLEScan* pBLEScan;

bool isTargetDevice(const String& address) {
  for (int i = 0; i < TARGET_COUNT; i++) {
    if (address == TARGET_MACS[i]) return true;
  }
  return false;
}

void httpSenderTask(void* parameter) {
  client.setInsecure();
  http.setReuse(true);

  for (;;) {
    String payloadToSend = "";

    if (xSemaphoreTake(payloadMutex, portMAX_DELAY)) {
      if (latestJsonPayload.length() > 0) {
        payloadToSend = latestJsonPayload;
        latestJsonPayload = "";
      }
      xSemaphoreGive(payloadMutex);
    }

    if (payloadToSend.length() > 0 && WiFi.status() == WL_CONNECTED) {
      unsigned long postStart = millis();

      http.begin(client, API_URL);
      http.addHeader("Content-Type", "application/json");
      http.addHeader("Connection", "keep-alive");

      int code = http.POST(payloadToSend);
      unsigned long postTime = millis() - postStart;

      Serial.print("[HTTP] Code: ");
      Serial.print(code);
      Serial.print(" | Post time: ");
      Serial.print(postTime);
      Serial.print(" ms | Rate: ");
      Serial.print(1000.0 / postTime);
      Serial.println(" sends/sec");

      if (code <= 0) {
        http.end();
      }
    }
    vTaskDelay(5 / portTICK_PERIOD_MS);
  }
}

void connectWiFi() {
  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  while (WiFi.status() != WL_CONNECTED) {
    delay(250);
    Serial.print(".");
  }
  Serial.println("\nWiFi connected!");
}

void setup() {
  Serial.begin(115200);
  connectWiFi();

  payloadMutex = xSemaphoreCreateMutex();

  NimBLEDevice::init("");
  pBLEScan = NimBLEDevice::getScan();
  pBLEScan->setActiveScan(false);
  pBLEScan->setInterval(100);
  pBLEScan->setWindow(99);
  // REMOVED setMaxResults(0) so getResults() actually stores detected devices!

  xTaskCreatePinnedToCore(httpSenderTask, "HTTPSender", 8192, NULL, 1, NULL, 0);
}

void loop() {
  // Scan for 600 milliseconds (gives beacons with 500ms intervals time to broadcast)
  NimBLEScanResults foundDevices = pBLEScan->getResults(600, false);
  int deviceCount = foundDevices.getCount();

  std::vector<DetectedBLE> detectedTargets;

  for (int i = 0; i < deviceCount; i++) {
    const NimBLEAdvertisedDevice* device = foundDevices.getDevice(i);
    String address = device->getAddress().toString().c_str();
    address.toLowerCase();
    int rssi = device->getRSSI();

    // Print every device seen so we can verify MAC format
    Serial.print("Seen: ");
    Serial.print(address);
    Serial.print(" | RSSI: ");
    Serial.println(rssi);

    if (isTargetDevice(address)) {
      Serial.println("  --> TARGET MATCHED!");
      bool alreadyAdded = false;
      for (size_t j = 0; j < detectedTargets.size(); j++) {
        if (detectedTargets[j].address == address) {
          if (rssi > detectedTargets[j].rssi) detectedTargets[j].rssi = rssi;
          alreadyAdded = true;
          break;
        }
      }
      if (!alreadyAdded) {
        detectedTargets.push_back({address, rssi});
      }
    }
  }

  if (!detectedTargets.empty()) {
    String jsonData = "{\"receiver\":\"" + String(RECEIVER_NAME) + "\",\"devices\":[";
    for (size_t i = 0; i < detectedTargets.size(); i++) {
      if (i > 0) jsonData += ",";
      jsonData += "{\"device\":\"" + detectedTargets[i].address + "\",\"rssi\":" + String(detectedTargets[i].rssi) + "}";
    }
    jsonData += "]}";

    if (xSemaphoreTake(payloadMutex, portMAX_DELAY)) {
      latestJsonPayload = jsonData;
      xSemaphoreGive(payloadMutex);
    }
  }

  pBLEScan->clearResults();
}
