#include <WiFi.h>
#include <HTTPClient.h>
#include <SPI.h>
#include <MFRC522.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
#include <time.h>

const char* WIFI_SSID = "Freebox-661B9B";
const char* WIFI_PASSWORD = "2hwtxq5brsqcswdmqdnk4m";

const char* SERVER_HOST = "192.168.1.47";
const uint16_t SERVER_PORT = 8080;
const char* SERVER_PATH = "/api/attendance/rfid";

constexpr uint8_t SS_PIN = 5;
constexpr uint8_t RST_PIN = 4;
constexpr uint8_t SCK_PIN = 18;
constexpr uint8_t MISO_PIN = 19;
constexpr uint8_t MOSI_PIN = 23;

constexpr uint8_t OLED_SDA_PIN = 21;
constexpr uint8_t OLED_SCL_PIN = 22;
constexpr uint8_t SCREEN_WIDTH = 128;
constexpr uint8_t SCREEN_HEIGHT = 64;
constexpr int OLED_RESET = -1;

const char* TZ_INFO = "CET-1CEST,M3.5.0/2,M10.5.0/3";
const char* NTP_SERVER_1 = "pool.ntp.org";
const char* NTP_SERVER_2 = "time.nist.gov";

MFRC522 mfrc522(SS_PIN, RST_PIN);
Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, OLED_RESET);
bool oledReady = false;

String lastUid = "";
unsigned long lastScanAt = 0;
const unsigned long SCAN_COOLDOWN_MS = 2000;
unsigned long lastClockRefreshAt = 0;
const unsigned long CLOCK_REFRESH_MS = 1000;

String formatUid(const MFRC522::Uid& uid) {
  String result;

  for (byte i = 0; i < uid.size; i++) {
    if (i > 0) {
      result += "-";
    }

    if (uid.uidByte[i] < 0x10) {
      result += "0";
    }

    result += String(uid.uidByte[i], HEX);
  }

  result.toUpperCase();
  return result;
}

String extractJsonValue(const String& body, const String& key) {
  const String needle = "\"" + key + "\"";
  const int keyPos = body.indexOf(needle);
  if (keyPos < 0) {
    return "";
  }

  const int colonPos = body.indexOf(':', keyPos + needle.length());
  if (colonPos < 0) {
    return "";
  }

  const int firstQuote = body.indexOf('"', colonPos + 1);
  if (firstQuote < 0) {
    return "";
  }

  const int secondQuote = body.indexOf('"', firstQuote + 1);
  if (secondQuote < 0) {
    return "";
  }

  return body.substring(firstQuote + 1, secondQuote);
}

String getCurrentTimeString() {
  struct tm timeinfo;
  if (!getLocalTime(&timeinfo, 100)) {
    return "--:--:--";
  }

  char buffer[9];
  strftime(buffer, sizeof(buffer), "%H:%M:%S", &timeinfo);
  return String(buffer);
}

String formatTimestampForDisplay(const String& isoTimestamp) {
  const int timePos = isoTimestamp.indexOf('T');
  if (timePos >= 0 && isoTimestamp.length() >= timePos + 9) {
    return isoTimestamp.substring(timePos + 1, timePos + 9);
  }

  return "";
}

void syncClock() {
  configTzTime(TZ_INFO, NTP_SERVER_1, NTP_SERVER_2);

  struct tm timeinfo;
  if (getLocalTime(&timeinfo, 10000)) {
    Serial.print("Heure synchronisee: ");
    Serial.println(getCurrentTimeString());
  } else {
    Serial.println("Synchronisation NTP impossible.");
  }
}

void drawCenteredText(const String& text, int y, uint8_t textSize) {
  if (text.length() == 0) {
    return;
  }

  int16_t x1, y1;
  uint16_t w, h;
  display.setTextSize(textSize);
  display.getTextBounds(text, 0, y, &x1, &y1, &w, &h);

  int x = (SCREEN_WIDTH - static_cast<int>(w)) / 2;
  if (x < 0) {
    x = 0;
  }

  display.setCursor(x, y);
  display.print(text);
}

void showDisplayMessage(const String& line1, const String& line2 = "", const String& line3 = "") {
  if (!oledReady) {
    return;
  }

  display.clearDisplay();
  display.setTextColor(SSD1306_WHITE);

  drawCenteredText("JustInTime", 0, 1);
  drawCenteredText(line1, 18, 1);
  drawCenteredText(line2, 34, 1);
  drawCenteredText(line3, 50, 1);

  display.display();
}

void initDisplay() {
  Wire.begin(OLED_SDA_PIN, OLED_SCL_PIN);

  oledReady = display.begin(SSD1306_SWITCHCAPVCC, 0x3C);
  if (!oledReady) {
    Serial.println("Ecran OLED non detecte.");
    return;
  }

  showDisplayMessage("Demarrage...", "Initialisation");
}

void showIdleScreen() {
  if (!oledReady) {
    return;
  }

  display.clearDisplay();
  display.setTextColor(SSD1306_WHITE);

  drawCenteredText("JustInTime", 0, 1);
  drawCenteredText(getCurrentTimeString(), 18, 2);
  drawCenteredText("Passe un badge", 50, 1);

  display.display();
}

void showScanResult(const String& personName, const String& actionLine, const String& scanTime) {
  if (!oledReady) {
    return;
  }

  String displayName = personName;
  uint8_t nameSize = displayName.length() <= 10 ? 2 : 1;

  if (displayName.length() > 18) {
    displayName = displayName.substring(0, 18);
  }

  display.clearDisplay();
  display.setTextColor(SSD1306_WHITE);

  drawCenteredText(displayName, 0, nameSize);
  drawCenteredText(actionLine, nameSize == 2 ? 24 : 18, 2);
  drawCenteredText(scanTime, 46, 2);

  display.display();
}

void connectWifi() {
  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  showDisplayMessage("Connexion Wi-Fi", WIFI_SSID);

  Serial.print("Connexion Wi-Fi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  const String espIp = WiFi.localIP().toString();

  Serial.println();
  Serial.print("Wi-Fi connecté, IP ESP32 = ");
  Serial.println(espIp);
  Serial.print("Serveur cible = ");
  Serial.print(SERVER_HOST);
  Serial.print(":");
  Serial.print(SERVER_PORT);
  Serial.println(SERVER_PATH);

  syncClock();
  showDisplayMessage("Wi-Fi connecte", espIp, "Pret pour badge");
}

void testServerConnection() {
  WiFiClient client;
  Serial.print("Test TCP vers serveur... ");

  if (client.connect(SERVER_HOST, SERVER_PORT)) {
    Serial.println("OK");
    client.stop();
    showDisplayMessage("Serveur joignable", String(SERVER_HOST) + ":" + String(SERVER_PORT), "Pret pour badge");
  } else {
    Serial.println("ECHEC");
    showDisplayMessage("Serveur indisponible", String(SERVER_HOST) + ":" + String(SERVER_PORT), "Verifie le Mac");
  }
}

void sendBadge(const String& badgeId) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Wi-Fi non connecté.");
    showDisplayMessage("Wi-Fi non connecte", "Reconnexion...");
    return;
  }

  showDisplayMessage("Badge detecte", "Identification...", "Envoi...");

  HTTPClient http;
  const String url = "http://" + String(SERVER_HOST) + ":" + String(SERVER_PORT) + String(SERVER_PATH);
  const String payload = "{\"badge_id\":\"" + badgeId + "\"}";

  http.begin(url);
  http.addHeader("Content-Type", "application/json");

  const int httpCode = http.POST(payload);
  const String body = http.getString();

  Serial.println("------------------------------");
  Serial.print("Badge lu : ");
  Serial.println(badgeId);
  Serial.print("HTTP code: ");
  Serial.println(httpCode);
  Serial.print("Réponse  : ");
  Serial.println(body);
  Serial.println("------------------------------");

  String personName = extractJsonValue(body, "name");
  const String serverMessage = extractJsonValue(body, "message");
  const String errorMessage = extractJsonValue(body, "error");
  String scanTime = formatTimestampForDisplay(extractJsonValue(body, "timestamp"));

  if (scanTime.length() == 0) {
    scanTime = getCurrentTimeString();
  }

  if (personName.length() == 0 && serverMessage.length() > 0) {
    const int separatorPos = serverMessage.indexOf(" enregistre");
    if (separatorPos > 0) {
      personName = serverMessage.substring(0, separatorPos);
    }
  }

  if (httpCode == 200) {
    String actionLine = "OK";

    if (serverMessage.indexOf("sortie") >= 0) {
      actionLine = "SORTIE";
    } else if (serverMessage.indexOf("entree") >= 0) {
      actionLine = "ENTREE";
    }

    showScanResult(
      personName.length() > 0 ? personName : "Badge reconnu",
      actionLine,
      scanTime
    );
  } else if (httpCode == 404) {
    showDisplayMessage("Badge inconnu", badgeId, errorMessage);
  } else {
    showDisplayMessage("Erreur serveur", "HTTP " + String(httpCode), errorMessage.length() > 0 ? errorMessage : "Reessaie");
  }

  http.end();
  delay(3000);
  showIdleScreen();
  lastClockRefreshAt = millis();
}

void setup() {
  Serial.begin(115200);
  delay(1000);

  initDisplay();
  connectWifi();
  testServerConnection();

  SPI.begin(SCK_PIN, MISO_PIN, MOSI_PIN, SS_PIN);
  mfrc522.PCD_Init();

  Serial.println("Lecteur RFID prêt. Passe une carte devant le RC522...");
  showIdleScreen();
  lastClockRefreshAt = millis();
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    connectWifi();
  }

  if (!mfrc522.PICC_IsNewCardPresent()) {
    if (millis() - lastClockRefreshAt >= CLOCK_REFRESH_MS) {
      showIdleScreen();
      lastClockRefreshAt = millis();
    }
    delay(50);
    return;
  }

  if (!mfrc522.PICC_ReadCardSerial()) {
    delay(50);
    return;
  }

  const String uid = formatUid(mfrc522.uid);

  if (uid == lastUid && millis() - lastScanAt < SCAN_COOLDOWN_MS) {
    mfrc522.PICC_HaltA();
    mfrc522.PCD_StopCrypto1();
    delay(250);
    return;
  }

  sendBadge(uid);
  lastUid = uid;
  lastScanAt = millis();

  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();
  delay(300);
}
