#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <SPI.h>
#include <MFRC522.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
#include <time.h>

const char* WIFI_SSID = "YOUR_WIFI_SSID";
const char* WIFI_PASSWORD = "YOUR_WIFI_PASSWORD";

const char* SITE_BASE_URL = "https://diligent-embrace-production.up.railway.app";
const char* RFID_ENDPOINT = "/api/attendance/rfid";
const char* DEVICE_ID = "ESP32-RFID-01";
const char* DEVICE_LABEL = "Entree";
const char* FIRMWARE_VERSION = "jit-esp32-2.0";

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
String idleMessage = "Passe un badge";
String siteName = "JustInTime";
unsigned long lastScanAt = 0;
unsigned long scanCooldownMs = 2000;
unsigned long lastClockRefreshAt = 0;
unsigned long clockRefreshMs = 1000;
unsigned long lastConfigSyncAt = 0;
unsigned long configRefreshMs = 300000;

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

String escapeJson(const String& input) {
  String output = input;
  output.replace("\\", "\\\\");
  output.replace("\"", "\\\"");
  return output;
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

long extractJsonLongValue(const String& body, const String& key, long fallbackValue) {
  const String needle = "\"" + key + "\"";
  const int keyPos = body.indexOf(needle);
  if (keyPos < 0) {
    return fallbackValue;
  }

  const int colonPos = body.indexOf(':', keyPos + needle.length());
  if (colonPos < 0) {
    return fallbackValue;
  }

  int valueStart = colonPos + 1;
  while (valueStart < body.length() && (body[valueStart] == ' ' || body[valueStart] == '\n' || body[valueStart] == '\r' || body[valueStart] == '\t')) {
    valueStart++;
  }

  int valueEnd = valueStart;
  while (valueEnd < body.length() && (isDigit(body[valueEnd]) || body[valueEnd] == '-')) {
    valueEnd++;
  }

  if (valueEnd == valueStart) {
    return fallbackValue;
  }

  return body.substring(valueStart, valueEnd).toInt();
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

bool beginHttpClient(HTTPClient& http, const String& url) {
  if (url.startsWith("https://")) {
    static WiFiClientSecure secureClient;
    secureClient.setInsecure();
    return http.begin(secureClient, url);
  }

  return http.begin(url);
}

String buildApiUrl(const String& query = "") {
  String url = String(SITE_BASE_URL) + String(RFID_ENDPOINT);
  if (query.length() > 0) {
    url += query;
  }
  return url;
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

  drawCenteredText(siteName, 0, 1);
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

  drawCenteredText(siteName, 0, 1);
  drawCenteredText(getCurrentTimeString(), 18, 2);
  drawCenteredText(idleMessage, 50, 1);

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
  Serial.print("Wi-Fi connecte, IP ESP32 = ");
  Serial.println(espIp);
  Serial.print("Site cible = ");
  Serial.println(SITE_BASE_URL);

  syncClock();
  showDisplayMessage("Wi-Fi connecte", espIp, "Sync site...");
}

void syncRemoteConfig(bool showFeedback = false) {
  if (WiFi.status() != WL_CONNECTED) {
    return;
  }

  HTTPClient http;
  const String url = buildApiUrl(String("?action=config&device_id=") + DEVICE_ID);

  if (!beginHttpClient(http, url)) {
    Serial.println("Impossible d'ouvrir la connexion HTTP pour la config.");
    return;
  }

  http.addHeader("Accept", "application/json");
  http.setTimeout(10000);

  if (showFeedback) {
    showDisplayMessage("Connexion site", "Recuperation", "configuration...");
  }

  const int httpCode = http.GET();
  const String body = http.getString();

  Serial.println("==== CONFIG SITE ====");
  Serial.print("GET ");
  Serial.println(url);
  Serial.print("HTTP code: ");
  Serial.println(httpCode);
  Serial.print("Reponse  : ");
  Serial.println(body);
  Serial.println("=====================");

  if (httpCode == 200) {
    const String remoteSiteName = extractJsonValue(body, "site_name");
    const String remoteMessage = extractJsonValue(body, "display_message");
    const long remoteCooldown = extractJsonLongValue(body, "cooldown_ms", static_cast<long>(scanCooldownMs));
    const long remoteClockRefresh = extractJsonLongValue(body, "clock_refresh_ms", static_cast<long>(clockRefreshMs));
    const long remoteConfigRefresh = extractJsonLongValue(body, "config_refresh_ms", static_cast<long>(configRefreshMs));

    if (remoteSiteName.length() > 0) {
      siteName = remoteSiteName;
    }
    if (remoteMessage.length() > 0) {
      idleMessage = remoteMessage;
    }
    if (remoteCooldown >= 500 && remoteCooldown <= 15000) {
      scanCooldownMs = static_cast<unsigned long>(remoteCooldown);
    }
    if (remoteClockRefresh >= 250 && remoteClockRefresh <= 10000) {
      clockRefreshMs = static_cast<unsigned long>(remoteClockRefresh);
    }
    if (remoteConfigRefresh >= 10000) {
      configRefreshMs = static_cast<unsigned long>(remoteConfigRefresh);
    }

    lastConfigSyncAt = millis();

    if (showFeedback) {
      showDisplayMessage("Site synchronise", String(DEVICE_ID), idleMessage);
      delay(1200);
    }
  } else if (showFeedback) {
    showDisplayMessage("Site indisponible", "HTTP " + String(httpCode), "Mode local OK");
    delay(1200);
  }

  http.end();
}

void sendBadge(const String& badgeId) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Wi-Fi non connecte.");
    showDisplayMessage("Wi-Fi non connecte", "Reconnexion...");
    return;
  }

  showDisplayMessage("Badge detecte", "Identification...", "Envoi au site...");

  HTTPClient http;
  const String url = buildApiUrl();
  const String payload = String("{") +
    "\"badge_id\":\"" + escapeJson(badgeId) + "\"," +
    "\"device_id\":\"" + escapeJson(String(DEVICE_ID)) + "\"," +
    "\"device_label\":\"" + escapeJson(String(DEVICE_LABEL)) + "\"," +
    "\"firmware\":\"" + escapeJson(String(FIRMWARE_VERSION)) + "\"," +
    "\"ip\":\"" + escapeJson(WiFi.localIP().toString()) + "\"," +
    "\"rssi\":" + String(WiFi.RSSI()) +
    "}";

  if (!beginHttpClient(http, url)) {
    Serial.println("Impossible d'ouvrir la connexion HTTP pour le badge.");
    showDisplayMessage("Erreur connexion", "HTTP init KO", "Reessaie");
    return;
  }

  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("User-Agent", String("JustInTime-ESP32/") + FIRMWARE_VERSION);
  http.setTimeout(12000);

  const int httpCode = http.POST(payload);
  const String body = http.getString();

  Serial.println("------------------------------");
  Serial.print("Badge lu : ");
  Serial.println(badgeId);
  Serial.print("POST URL : ");
  Serial.println(url);
  Serial.print("HTTP code: ");
  Serial.println(httpCode);
  Serial.print("Reponse  : ");
  Serial.println(body);
  Serial.println("------------------------------");

  String personName = extractJsonValue(body, "name");
  const String serverMessage = extractJsonValue(body, "message");
  const String errorMessage = extractJsonValue(body, "error");
  const String eventType = extractJsonValue(body, "event_type");
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

    if (eventType == "out" || serverMessage.indexOf("sortie") >= 0) {
      actionLine = "SORTIE";
    } else if (eventType == "in" || serverMessage.indexOf("entree") >= 0) {
      actionLine = "ENTREE";
    }

    showScanResult(
      personName.length() > 0 ? personName : "Badge reconnu",
      actionLine,
      scanTime
    );
  } else if (httpCode == 404) {
    showDisplayMessage("Badge inconnu", badgeId, errorMessage.length() > 0 ? errorMessage : "Ajoute-le au site");
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
  syncRemoteConfig(true);

  SPI.begin(SCK_PIN, MISO_PIN, MOSI_PIN, SS_PIN);
  mfrc522.PCD_Init();

  Serial.println("Lecteur RFID pret. Passe une carte devant le RC522...");
  showIdleScreen();
  lastClockRefreshAt = millis();
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    connectWifi();
    syncRemoteConfig(true);
  }

  if (millis() - lastConfigSyncAt >= configRefreshMs) {
    syncRemoteConfig(false);
  }

  if (!mfrc522.PICC_IsNewCardPresent()) {
    if (millis() - lastClockRefreshAt >= clockRefreshMs) {
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

  if (uid == lastUid && millis() - lastScanAt < scanCooldownMs) {
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
