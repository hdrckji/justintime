#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <WebServer.h>
#include <DNSServer.h>
#include <Preferences.h>
#include <SPI.h>
#include <MFRC522.h>
#include <Adafruit_GFX.h>
#include <Adafruit_ILI9341.h>
#include <time.h>

/*
  JustInTime - ESP32 RFID + ILI9341 + 2 LEDs

  Cablage recommande:
  RC522
    SDA/SS -> GPIO 5
    RST    -> GPIO 4
    SCK    -> GPIO 18
    MISO   -> GPIO 19
    MOSI   -> GPIO 23
    VCC    -> 3V3
    GND    -> GND

  ILI9341 (SPI)
    CS     -> GPIO 22
    DC     -> GPIO 21
    RES    -> GPIO 25
    SCK    -> GPIO 18
    MOSI   -> GPIO 23
    MISO   -> laisser non branche si le RC522 partage le meme SPI
    BLK    -> 3V3 (ou un GPIO si tu veux piloter le retroeclairage)
    VCC    -> 3V3
    GND    -> GND

  LEDs
    Verte  -> GPIO 26 (avec resistance 220 ohms)
    Rouge  -> GPIO 27 (avec resistance 220 ohms)

  Buzzer passif
    +      -> GPIO 33
    -      -> GND
*/

const char* WIFI_SSID = "Freebox-661B9B";
const char* WIFI_PASSWORD = "2hwtxq5brsqcswdmqdnk4m";
const char* WIFI_PORTAL_AP_SSID = "JIT-Setup";
const char* WIFI_PORTAL_AP_PASSWORD = "justintime";
const char* WIFI_PORTAL_HOSTNAME = "jit.setup";
constexpr unsigned long WIFI_CONNECT_TIMEOUT_MS = 20000;
constexpr unsigned long WIFI_PORTAL_TIMEOUT_MS = 600000;
constexpr unsigned long WIFI_SCAN_CACHE_TTL_MS = 30000;
constexpr int WIFI_SCAN_MAX_RESULTS = 24;

const char* SITE_BASE_URL = "https://diligent-embrace-production.up.railway.app";
const char* RFID_ENDPOINT = "/api/attendance/rfid";
const char* DEVICE_ID = "ESP32-RFID-01";
const char* DEVICE_LABEL = "Entree";
const char* FIRMWARE_VERSION = "jit-esp32-ili9341-3.2";

constexpr uint8_t RC522_SS_PIN = 5;
constexpr uint8_t RC522_RST_PIN = 4;
constexpr uint8_t SPI_SCK_PIN = 18;
constexpr uint8_t SPI_MISO_PIN = 19;
constexpr uint8_t SPI_MOSI_PIN = 23;

constexpr uint8_t TFT_CS_PIN = 22;
constexpr uint8_t TFT_DC_PIN = 21;
constexpr uint8_t TFT_RST_PIN = 25;   // ton RES est branche sur GPIO 25
constexpr int8_t TFT_BL_PIN = -1;     // laisse -1 si BLK est relie directement au 3V3

constexpr uint8_t GREEN_LED_PIN = 26;
constexpr uint8_t RED_LED_PIN = 27;
constexpr uint8_t BUZZER_PIN = 33;
constexpr uint8_t BUZZER_DUTY = 220;  // 0-255, plus haut = plus fort
#if !defined(ESP_ARDUINO_VERSION_MAJOR) || (ESP_ARDUINO_VERSION_MAJOR < 3)
constexpr uint8_t BUZZER_PWM_CHANNEL = 0;
#endif

const char* TZ_INFO = "CET-1CEST,M3.5.0/2,M10.5.0/3";
const char* NTP_SERVER_1 = "pool.ntp.org";
const char* NTP_SERVER_2 = "time.nist.gov";

const uint16_t COLOR_BG = ILI9341_BLACK;
const uint16_t COLOR_TEXT = ILI9341_WHITE;
const uint16_t COLOR_INFO = ILI9341_BLUE;
const uint16_t COLOR_ACCENT = ILI9341_CYAN;
const uint16_t COLOR_OK = ILI9341_GREEN;
const uint16_t COLOR_ERROR = ILI9341_RED;
const uint16_t COLOR_WARN = ILI9341_YELLOW;
const uint16_t COLOR_DIM = 0x8410;
const uint16_t COLOR_PANEL = 0x1082;
const uint16_t COLOR_OUT = 0xFD20; // orange
constexpr uint32_t TFT_SPI_FREQUENCY = 10000000;
constexpr unsigned long RFID_HEALTH_CHECK_MS = 3000;

MFRC522 mfrc522(RC522_SS_PIN, RC522_RST_PIN);
Adafruit_ILI9341 tft(TFT_CS_PIN, TFT_DC_PIN, TFT_RST_PIN);
DNSServer dnsServer;
WebServer wifiPortalServer(80);
Preferences wifiPrefs;

String configuredWifiSsid = "";
String configuredWifiPassword = "";
bool wifiPortalActive = false;
bool wifiCredentialsJustSaved = false;
unsigned long wifiPortalStartedAt = 0;
unsigned long wifiCredentialsSavedAt = 0;
String wifiScanSsids[WIFI_SCAN_MAX_RESULTS];
int wifiScanRssi[WIFI_SCAN_MAX_RESULTS];
int wifiScanCount = 0;
unsigned long wifiScanLastAt = 0;

bool displayReady = false;
bool idleScreenInitialized = false;
String lastRenderedClock = "";
String lastRenderedWifiLine = "";
String lastRenderedIdleMessage = "";
String lastRenderedSiteName = "";
bool lastRenderedWifiConnected = false;

String lastUid = "";
String idleMessage = "Passe un badge";
String siteName = "JustInTime";
String successMessage = "Pointage enregistre";
bool ledsEnabled = true;
bool buzzerEnabled = true;
unsigned long lastScanAt = 0;
unsigned long scanCooldownMs = 2000;
unsigned long lastClockRefreshAt = 0;
unsigned long clockRefreshMs = 1000;
unsigned long lastConfigSyncAt = 0;
unsigned long configRefreshMs = 300000;
unsigned long lastRfidHealthCheckAt = 0;

// Declarations anticipees pour les fonctions utilisees par le portail Wi-Fi.
void setStatusLeds(bool greenOn, bool redOn);
void updateConnectionLeds();
void signalError();
void showDisplayMessage(const String& line1, const String& line2, const String& line3, uint16_t accentColor);
void refreshWifiScanCache(bool force = false);

void clearWifiScanCache() {
  for (int i = 0; i < WIFI_SCAN_MAX_RESULTS; i++) {
    wifiScanSsids[i] = "";
    wifiScanRssi[i] = -127;
  }
  wifiScanCount = 0;
}

void refreshWifiScanCache(bool force) {
  if (!force && wifiScanLastAt != 0 && millis() - wifiScanLastAt < WIFI_SCAN_CACHE_TTL_MS) {
    return;
  }

  clearWifiScanCache();

  // Scanner en mode STA uniquement est plus fiable; ensuite on repasse en AP+STA pour le portail.
  WiFi.mode(WIFI_STA);
  WiFi.disconnect(false, true);
  delay(120);

  const int count = WiFi.scanNetworks(false, true);
  if (count <= 0) {
    Serial.print("Scan Wi-Fi: aucun reseau (code ");
    Serial.print(count);
    Serial.println(").");
    WiFi.scanDelete();
    wifiScanLastAt = millis();
    return;
  }

  int stored = 0;
  for (int i = 0; i < count && stored < WIFI_SCAN_MAX_RESULTS; i++) {
    const String ssid = WiFi.SSID(i);
    if (ssid.length() == 0) {
      continue;
    }

    bool duplicate = false;
    for (int j = 0; j < stored; j++) {
      if (wifiScanSsids[j] == ssid) {
        duplicate = true;
        break;
      }
    }
    if (duplicate) {
      continue;
    }

    wifiScanSsids[stored] = ssid;
    wifiScanRssi[stored] = WiFi.RSSI(i);
    stored++;
  }

  wifiScanCount = stored;
  wifiScanLastAt = millis();

  WiFi.scanDelete();

  Serial.print("Scan Wi-Fi: ");
  Serial.print(wifiScanCount);
  Serial.println(" reseau(x) en cache.");
}

String htmlEscape(const String& input) {
  String output = input;
  output.replace("&", "&amp;");
  output.replace("<", "&lt;");
  output.replace(">", "&gt;");
  output.replace("\"", "&quot;");
  output.replace("'", "&#39;");
  return output;
}

String jsonEscape(const String& input) {
  String output = input;
  output.replace("\\", "\\\\");
  output.replace("\"", "\\\"");
  output.replace("\n", " ");
  output.replace("\r", " ");
  return output;
}

String urlDecode(const String& input) {
  String output;
  output.reserve(input.length());

  for (size_t i = 0; i < input.length(); i++) {
    const char c = input[i];

    if (c == '+') {
      output += ' ';
      continue;
    }

    if (c == '%' && i + 2 < input.length()) {
      char hex[3] = {input[i + 1], input[i + 2], '\0'};
      output += static_cast<char>(strtol(hex, nullptr, 16));
      i += 2;
      continue;
    }

    output += c;
  }

  return output;
}

void loadWifiCredentials() {
  wifiPrefs.begin("jitwifi", true);
  configuredWifiSsid = wifiPrefs.getString("ssid", "");
  configuredWifiPassword = wifiPrefs.getString("pass", "");
  wifiPrefs.end();
}

void saveWifiCredentials(const String& ssid, const String& password) {
  wifiPrefs.begin("jitwifi", false);
  wifiPrefs.putString("ssid", ssid);
  wifiPrefs.putString("pass", password);
  wifiPrefs.end();

  configuredWifiSsid = ssid;
  configuredWifiPassword = password;
}

void stopWifiPortal() {
  if (!wifiPortalActive) {
    return;
  }

  dnsServer.stop();
  wifiPortalServer.stop();
  WiFi.softAPdisconnect(true);
  wifiPortalActive = false;
}

String wifiPortalPage(const String& message = "") {
  const String escapedMessage = htmlEscape(message);

  return String("<!doctype html><html><head><meta charset='utf-8'>") +
    "<meta name='viewport' content='width=device-width,initial-scale=1'>"
    "<title>JIT Wi-Fi Setup</title>"
    "<style>body{font-family:Arial,sans-serif;background:#111;color:#eee;padding:18px;}"
    "h1{font-size:1.3rem;margin-bottom:10px;}"
    "label{display:block;margin-top:12px;font-weight:700;}"
    "select,"
    "input{width:100%;padding:10px;margin-top:6px;border-radius:8px;border:1px solid #333;background:#1c1c1c;color:#fff;}"
    "button{margin-top:14px;padding:10px 14px;border:0;border-radius:8px;background:#1e88e5;color:#fff;font-weight:700;}"
    ".row{display:flex;gap:8px;align-items:center;}"
    ".row button{margin-top:6px;white-space:nowrap;}"
    ".msg{margin-top:12px;padding:10px;border-radius:8px;background:#1f2f29;color:#8ff0b3;}"
    ".hint{font-size:0.9rem;color:#b0b7c3;margin-top:8px;}"
    "</style></head><body>"
    "<h1>Configuration Wi-Fi</h1>"
    "<p class='hint'>Tu peux aussi ouvrir <strong>http://" + String(WIFI_PORTAL_HOSTNAME) + "</strong></p>"
    "<p>Entrez le reseau Wi-Fi du site puis validez.</p>"
    "<form method='POST' action='/save'>"
    "<label>Reseaux detectes</label>"
    "<div class='row'>"
    "<select id='ssid-list'><option value=''>Chargement...</option></select>"
    "<button type='button' onclick='loadNetworks()'>Rafraichir</button>"
    "</div>"
    "<label>SSID</label><input name='ssid' required maxlength='64'>"
    "<label>Mot de passe</label><input name='password' type='password' maxlength='64'>"
    "<button type='submit'>Enregistrer</button>"
    "</form>" +
    (escapedMessage.length() > 0 ? String("<div class='msg'>") + escapedMessage + "</div>" : "") +
    "<script>"
    "const ssidInput=document.querySelector(\"input[name='ssid']\");"
    "const ssidList=document.getElementById('ssid-list');"
    "ssidList.addEventListener('change',()=>{if(ssidList.value){ssidInput.value=ssidList.value;}});"
    "async function loadNetworks(){"
    "ssidList.innerHTML='<option value=\"\">Scan en cours...</option>';"
    "try{"
    "const res=await fetch('/networks',{cache:'no-store'});"
    "const data=await res.json();"
    "ssidList.innerHTML='<option value=\"\">Choisir un reseau</option>';"
    "(data.networks||[]).forEach((n)=>{"
    "const opt=document.createElement('option');opt.value=n.ssid;"
    "opt.textContent=n.ssid+' ('+n.rssi+' dBm)';ssidList.appendChild(opt);"
    "});"
    "if((data.networks||[]).length===0){ssidList.innerHTML='<option value=\"\">Aucun reseau trouve</option>'; }"
    "}catch(e){ssidList.innerHTML='<option value=\"\">Erreur de scan</option>'; }"
    "}"
    "loadNetworks();"
    "</script>"
    "</body></html>";
}

void handleWifiPortalNetworks() {
  refreshWifiScanCache(false);

  // Tentative de rafraichissement supplementaire en AP+STA; si echec on conserve le cache existant.
  const int count = WiFi.scanNetworks(false, true);
  if (count > 0) {
    clearWifiScanCache();
    int stored = 0;
    for (int i = 0; i < count && stored < WIFI_SCAN_MAX_RESULTS; i++) {
      const String ssid = WiFi.SSID(i);
      if (ssid.length() == 0) {
        continue;
      }

      bool duplicate = false;
      for (int j = 0; j < stored; j++) {
        if (wifiScanSsids[j] == ssid) {
          duplicate = true;
          break;
        }
      }
      if (duplicate) {
        continue;
      }

      wifiScanSsids[stored] = ssid;
      wifiScanRssi[stored] = WiFi.RSSI(i);
      stored++;
    }
    wifiScanCount = stored;
    wifiScanLastAt = millis();
  }

  WiFi.scanDelete();

  String body = "{\"networks\":[";
  bool first = true;

  for (int i = 0; i < wifiScanCount; i++) {
    if (!first) {
      body += ",";
    }
    first = false;

    body += "{\"ssid\":\"" + jsonEscape(wifiScanSsids[i]) + "\",\"rssi\":" + String(wifiScanRssi[i]) + "}";
  }

  body += "],\"count\":" + String(wifiScanCount) + "}";
  wifiPortalServer.send(200, "application/json; charset=utf-8", body);
}

void handleWifiPortalRoot() {
  wifiPortalServer.send(200, "text/html; charset=utf-8", wifiPortalPage());
}

void handleWifiPortalSave() {
  if (!wifiPortalServer.hasArg("ssid")) {
    wifiPortalServer.send(400, "text/html; charset=utf-8", wifiPortalPage("SSID manquant."));
    return;
  }

  const String ssid = urlDecode(wifiPortalServer.arg("ssid"));
  const String password = urlDecode(wifiPortalServer.arg("password"));

  if (ssid.length() == 0) {
    wifiPortalServer.send(400, "text/html; charset=utf-8", wifiPortalPage("SSID vide."));
    return;
  }

  saveWifiCredentials(ssid, password);
  wifiCredentialsJustSaved = true;
  wifiCredentialsSavedAt = millis();

  Serial.print("Wi-Fi sauvegarde: ");
  Serial.println(ssid);

  wifiPortalServer.send(200, "text/html; charset=utf-8", wifiPortalPage("Configuration enregistree. Le boitier redemarre..."));
}

void startWifiPortal() {
  if (wifiPortalActive) {
    return;
  }

  // On fait d'abord un scan en mode STA pur pour peupler la liste des SSID.
  refreshWifiScanCache(true);

  WiFi.mode(WIFI_AP_STA);
  WiFi.softAP(WIFI_PORTAL_AP_SSID, WIFI_PORTAL_AP_PASSWORD);

  const IPAddress apIp = WiFi.softAPIP();
  dnsServer.start(53, "*", apIp);

  wifiPortalServer.on("/", HTTP_GET, handleWifiPortalRoot);
  wifiPortalServer.on("/save", HTTP_POST, handleWifiPortalSave);
  wifiPortalServer.on("/networks", HTTP_GET, handleWifiPortalNetworks);
  wifiPortalServer.on("/generate_204", HTTP_GET, handleWifiPortalRoot);
  wifiPortalServer.on("/gen_204", HTTP_GET, handleWifiPortalRoot);
  wifiPortalServer.on("/hotspot-detect.html", HTTP_GET, handleWifiPortalRoot);
  wifiPortalServer.on("/connecttest.txt", HTTP_GET, handleWifiPortalRoot);
  wifiPortalServer.on("/ncsi.txt", HTTP_GET, handleWifiPortalRoot);
  wifiPortalServer.onNotFound(handleWifiPortalRoot);
  wifiPortalServer.begin();

  wifiPortalActive = true;
  wifiPortalStartedAt = millis();

  Serial.print("Portail Wi-Fi actif sur AP: ");
  Serial.print(WIFI_PORTAL_AP_SSID);
  Serial.print(" IP: ");
  Serial.println(apIp);

  signalError();
  showDisplayMessage("Mode config Wi-Fi", String("AP: ") + WIFI_PORTAL_AP_SSID, apIp.toString(), COLOR_WARN);
}

bool tryConnectWifi(const String& ssid, const String& password, bool showFeedback) {
  if (ssid.length() == 0) {
    return false;
  }

  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  WiFi.begin(ssid.c_str(), password.c_str());

  updateConnectionLeds();
  if (showFeedback) {
    showDisplayMessage("Connexion Wi-Fi", ssid, "Patiente...", COLOR_INFO);
  }

  Serial.print("Connexion Wi-Fi vers ");
  Serial.print(ssid);
  Serial.print(" ");

  const unsigned long wifiConnectStartedAt = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - wifiConnectStartedAt < WIFI_CONNECT_TIMEOUT_MS) {
    updateConnectionLeds();
    delay(500);
    Serial.print(".");
  }

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println(" echec");
    return false;
  }

  Serial.println(" OK");
  return true;
}

String clipText(const String& text, size_t maxLen) {
  if (text.length() <= maxLen) {
    return text;
  }
  if (maxLen <= 3) {
    return text.substring(0, maxLen);
  }
  return text.substring(0, maxLen - 3) + "...";
}

void deselectSpiDevices() {
  digitalWrite(RC522_SS_PIN, HIGH);
  digitalWrite(TFT_CS_PIN, HIGH);
}

void setStatusLeds(bool greenOn, bool redOn) {
  if (!ledsEnabled) {
    digitalWrite(GREEN_LED_PIN, LOW);
    digitalWrite(RED_LED_PIN, LOW);
    return;
  }

  digitalWrite(GREEN_LED_PIN, greenOn ? HIGH : LOW);
  digitalWrite(RED_LED_PIN, redOn ? HIGH : LOW);
}

void updateConnectionLeds() {
  if (WiFi.status() == WL_CONNECTED) {
    setStatusLeds(false, false);
  } else {
    const bool blinkOn = ((millis() / 350) % 2) == 0;
    setStatusLeds(false, blinkOn);
  }
}

void initBuzzer() {
  pinMode(BUZZER_PIN, OUTPUT);
  digitalWrite(BUZZER_PIN, LOW);

#if defined(ESP_ARDUINO_VERSION_MAJOR) && (ESP_ARDUINO_VERSION_MAJOR >= 3)
  ledcAttach(BUZZER_PIN, 2400, 8);
  ledcWrite(BUZZER_PIN, 0);
#else
  ledcSetup(BUZZER_PWM_CHANNEL, 2400, 8);
  ledcAttachPin(BUZZER_PIN, BUZZER_PWM_CHANNEL);
  ledcWrite(BUZZER_PWM_CHANNEL, 0);
#endif
}

void playBuzzerTone(uint16_t frequency, unsigned long durationMs) {
#if defined(ESP_ARDUINO_VERSION_MAJOR) && (ESP_ARDUINO_VERSION_MAJOR >= 3)
  ledcWrite(BUZZER_PIN, BUZZER_DUTY);
  ledcWriteTone(BUZZER_PIN, frequency);
#else
  ledcWrite(BUZZER_PWM_CHANNEL, BUZZER_DUTY);
  ledcWriteTone(BUZZER_PWM_CHANNEL, frequency);
#endif
  delay(durationMs);
#if defined(ESP_ARDUINO_VERSION_MAJOR) && (ESP_ARDUINO_VERSION_MAJOR >= 3)
  ledcWriteTone(BUZZER_PIN, 0);
  ledcWrite(BUZZER_PIN, 0);
#else
  ledcWriteTone(BUZZER_PWM_CHANNEL, 0);
  ledcWrite(BUZZER_PWM_CHANNEL, 0);
#endif
}

void blinkPin(uint8_t pin, uint8_t times, unsigned long onMs, unsigned long offMs) {
  for (uint8_t i = 0; i < times; i++) {
    digitalWrite(pin, HIGH);
    delay(onMs);
    digitalWrite(pin, LOW);
    if (i + 1 < times) {
      delay(offMs);
    }
  }
  updateConnectionLeds();
}

void signalSuccess(const String& eventType = "") {
  setStatusLeds(true, false);

  if (!buzzerEnabled) {
    delay(300);
    updateConnectionLeds();
    return;
  }

  if (eventType == "out") {
    playBuzzerTone(2400, 110);
    delay(25);
    playBuzzerTone(1800, 140);
    delay(20);
    playBuzzerTone(1400, 180);
  } else {
    playBuzzerTone(1400, 100);
    delay(25);
    playBuzzerTone(2000, 130);
    delay(20);
    playBuzzerTone(2600, 170);
  }

  delay(300);
  updateConnectionLeds();
}

void signalError() {
  setStatusLeds(false, true);

  if (!buzzerEnabled) {
    delay(350);
    updateConnectionLeds();
    return;
  }

  playBuzzerTone(700, 180);
  delay(40);
  playBuzzerTone(520, 220);
  delay(40);
  playBuzzerTone(380, 260);

  delay(350);
  updateConnectionLeds();
}

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

bool extractJsonBoolValue(const String& body, const String& key, bool fallbackValue) {
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

  if (body.startsWith("true", valueStart)) {
    return true;
  }

  if (body.startsWith("false", valueStart)) {
    return false;
  }

  if (valueStart < body.length() && body[valueStart] == '"') {
    const int endQuote = body.indexOf('"', valueStart + 1);
    if (endQuote > valueStart) {
      String raw = body.substring(valueStart + 1, endQuote);
      raw.toLowerCase();
      if (raw == "1" || raw == "true" || raw == "yes" || raw == "on") {
        return true;
      }
      if (raw == "0" || raw == "false" || raw == "no" || raw == "off") {
        return false;
      }
    }
  }

  if (valueStart < body.length() && isDigit(body[valueStart])) {
    return body[valueStart] != '0';
  }

  return fallbackValue;
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

bool beginHttpClient(HTTPClient& http, const String& url, WiFiClientSecure& secureClient, WiFiClient& plainClient) {
  http.setReuse(false);
  http.useHTTP10(true);
  http.setFollowRedirects(HTTPC_STRICT_FOLLOW_REDIRECTS);

  if (url.startsWith("https://")) {
    secureClient.stop();
    secureClient.setInsecure();
    secureClient.setTimeout(15000);
    return http.begin(secureClient, url);
  }

  plainClient.stop();
  return http.begin(plainClient, url);
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

void drawCenteredText(const String& text, int y, uint8_t textSize, uint16_t color = COLOR_TEXT, uint16_t bgColor = COLOR_BG) {
  if (!displayReady || text.length() == 0) {
    return;
  }

  int16_t x1, y1;
  uint16_t w, h;
  tft.setTextSize(textSize);
  tft.getTextBounds(text, 0, 0, &x1, &y1, &w, &h);

  int x = (tft.width() - static_cast<int>(w)) / 2;
  if (x < 0) {
    x = 0;
  }

  tft.setTextColor(color, bgColor);
  tft.setCursor(x, y);
  tft.print(text);
}

void invalidateIdleScreen() {
  idleScreenInitialized = false;
  lastRenderedClock = "";
  lastRenderedWifiLine = "";
  lastRenderedIdleMessage = "";
  lastRenderedSiteName = "";
  lastRenderedWifiConnected = false;
}

void showDisplayMessage(const String& line1, const String& line2 = "", const String& line3 = "", uint16_t accentColor = COLOR_INFO) {
  if (!displayReady) {
    return;
  }

  invalidateIdleScreen();
  deselectSpiDevices();

  tft.fillScreen(COLOR_BG);
  tft.fillRect(0, 0, tft.width(), 36, accentColor);
  tft.drawFastHLine(0, 36, tft.width(), COLOR_TEXT);
  tft.fillRoundRect(12, 52, tft.width() - 24, 126, 10, COLOR_PANEL);
  tft.drawRoundRect(12, 52, tft.width() - 24, 126, 10, accentColor);

  drawCenteredText(clipText(siteName, 22), 8, 2, COLOR_TEXT, accentColor);
  drawCenteredText(clipText(line1, 22), 70, 3, COLOR_TEXT, COLOR_PANEL);
  drawCenteredText(clipText(line2, 28), 116, 2, COLOR_ACCENT, COLOR_PANEL);
  drawCenteredText(clipText(line3, 30), 150, 2, COLOR_DIM, COLOR_PANEL);
  drawCenteredText(String("Lecteur: ") + DEVICE_LABEL, 212, 1, COLOR_DIM, COLOR_BG);

  digitalWrite(TFT_CS_PIN, HIGH);
}

void initDisplay() {
  pinMode(TFT_DC_PIN, OUTPUT);
  pinMode(TFT_CS_PIN, OUTPUT);

  if (TFT_RST_PIN >= 0) {
    pinMode(TFT_RST_PIN, OUTPUT);
    digitalWrite(TFT_RST_PIN, HIGH);
    delay(5);
    digitalWrite(TFT_RST_PIN, LOW);
    delay(20);
    digitalWrite(TFT_RST_PIN, HIGH);
    delay(150);
  }

  if (TFT_BL_PIN >= 0) {
    pinMode(TFT_BL_PIN, OUTPUT);
    digitalWrite(TFT_BL_PIN, HIGH);
  }

  deselectSpiDevices();
  tft.begin(TFT_SPI_FREQUENCY);
  tft.setRotation(1); // paysage 320x240
  tft.fillScreen(COLOR_BG);
  displayReady = true;
  invalidateIdleScreen();

  showDisplayMessage("Demarrage...", "Initialisation TFT", "ILI9341 OK", COLOR_ACCENT);
}

void showIdleScreen() {
  if (!displayReady) {
    return;
  }

  const bool wifiConnected = WiFi.status() == WL_CONNECTED;
  const String currentTime = getCurrentTimeString();
  const String wifiLine = wifiConnected
    ? String("WiFi OK - ") + WiFi.localIP().toString()
    : "WiFi non connecte";

  deselectSpiDevices();

  if (!idleScreenInitialized) {
    tft.fillScreen(COLOR_BG);
    tft.fillRect(0, 0, tft.width(), 36, COLOR_INFO);
    tft.drawFastHLine(0, 36, tft.width(), COLOR_ACCENT);

    tft.fillRoundRect(18, 52, tft.width() - 36, 58, 10, COLOR_PANEL);
    tft.drawRoundRect(18, 52, tft.width() - 36, 58, 10, COLOR_ACCENT);

    tft.fillRoundRect(24, 126, tft.width() - 48, 42, 8, COLOR_PANEL);
    tft.drawRoundRect(24, 126, tft.width() - 48, 42, 8, COLOR_DIM);

    tft.fillRoundRect(18, 186, tft.width() - 36, 24, 8, COLOR_PANEL);
    tft.drawRoundRect(18, 186, tft.width() - 36, 24, 8, COLOR_DIM);

    drawCenteredText(String("Lecteur: ") + DEVICE_LABEL, 220, 1, COLOR_DIM, COLOR_BG);
    idleScreenInitialized = true;
  }

  if (lastRenderedSiteName != siteName) {
    tft.fillRect(0, 6, tft.width(), 24, COLOR_INFO);
    drawCenteredText(clipText(siteName, 22), 8, 2, COLOR_TEXT, COLOR_INFO);
    lastRenderedSiteName = siteName;
  }

  if (lastRenderedClock != currentTime) {
    tft.fillRect(26, 62, tft.width() - 52, 36, COLOR_PANEL);
    drawCenteredText(currentTime, 64, 4, COLOR_ACCENT, COLOR_PANEL);
    lastRenderedClock = currentTime;
  }

  if (lastRenderedIdleMessage != idleMessage) {
    tft.fillRect(32, 136, tft.width() - 64, 22, COLOR_PANEL);
    drawCenteredText(clipText(idleMessage, 26), 138, 2, COLOR_TEXT, COLOR_PANEL);
    lastRenderedIdleMessage = idleMessage;
  }

  if (lastRenderedWifiLine != wifiLine || lastRenderedWifiConnected != wifiConnected) {
    tft.fillRect(24, 191, tft.width() - 48, 14, COLOR_PANEL);
    drawCenteredText(clipText(wifiLine, 30), 196, 1, wifiConnected ? COLOR_OK : COLOR_ERROR, COLOR_PANEL);
    lastRenderedWifiLine = wifiLine;
    lastRenderedWifiConnected = wifiConnected;
  }

  digitalWrite(TFT_CS_PIN, HIGH);
}

void showScanResult(const String& personName, const String& actionLine, const String& scanTime, uint16_t accentColor) {
  if (!displayReady) {
    return;
  }

  invalidateIdleScreen();
  deselectSpiDevices();

  tft.fillScreen(COLOR_BG);
  tft.fillRect(0, 0, tft.width(), 42, accentColor);
  tft.drawFastHLine(0, 42, tft.width(), COLOR_TEXT);
  tft.fillRoundRect(14, 58, tft.width() - 28, 112, 10, COLOR_PANEL);
  tft.drawRoundRect(14, 58, tft.width() - 28, 112, 10, accentColor);

  drawCenteredText(actionLine, 11, 2, COLOR_BG, accentColor);
  drawCenteredText(clipText(personName, 20), 76, 3, COLOR_TEXT, COLOR_PANEL);
  drawCenteredText(scanTime, 126, 3, COLOR_WARN, COLOR_PANEL);
  drawCenteredText(clipText(successMessage, 26), 166, 2, COLOR_DIM, COLOR_PANEL);
  drawCenteredText(String("Lecteur: ") + DEVICE_LABEL, 212, 1, COLOR_DIM, COLOR_BG);

  digitalWrite(TFT_CS_PIN, HIGH);
}

bool isRfidReaderReady() {
  deselectSpiDevices();
  const byte version = mfrc522.PCD_ReadRegister(MFRC522::VersionReg);
  return version != 0x00 && version != 0xFF;
}

bool initRfidReader(bool showFeedback = false) {
  pinMode(RC522_RST_PIN, OUTPUT);
  digitalWrite(RC522_RST_PIN, HIGH);
  delay(5);

  deselectSpiDevices();
  mfrc522.PCD_Init();
  delay(10);
  mfrc522.PCD_AntennaOn();
  delay(5);

  deselectSpiDevices();
  const byte version = mfrc522.PCD_ReadRegister(MFRC522::VersionReg);

  Serial.print("RC522 version: 0x");
  Serial.println(version, HEX);

  const bool ready = version != 0x00 && version != 0xFF;
  if (!ready) {
    Serial.println("RC522 non detecte. Verifie SDA/SS, RST, SCK, MOSI et surtout debranche le MISO du TFT si partage SPI.");
    if (showFeedback) {
      signalError();
      showDisplayMessage("RFID indisponible", "Verifie le cablage", "debranche MISO TFT", COLOR_ERROR);
      delay(1500);
    }
  }

  return ready;
}

void connectWifi() {
  loadWifiCredentials();

  bool connected = false;
  String usedSsid = "";

  if (configuredWifiSsid.length() > 0) {
    connected = tryConnectWifi(configuredWifiSsid, configuredWifiPassword, true);
    if (connected) {
      usedSsid = configuredWifiSsid;
    }
  }

  if (!connected) {
    const String fallbackSsid = String(WIFI_SSID);
    const String fallbackPassword = String(WIFI_PASSWORD);

    if (fallbackSsid.length() > 0) {
      connected = tryConnectWifi(fallbackSsid, fallbackPassword, true);
      if (connected) {
        usedSsid = fallbackSsid;
        saveWifiCredentials(fallbackSsid, fallbackPassword);
      }
    }
  }

  if (!connected) {
    Serial.println("Connexion Wi-Fi en echec. Passage en mode configuration.");
    updateConnectionLeds();
    startWifiPortal();
    return;
  }

  stopWifiPortal();

  const String espIp = WiFi.localIP().toString();

  Serial.println();
  Serial.print("Wi-Fi connecte, IP ESP32 = ");
  Serial.println(espIp);
  Serial.print("SSID utilise = ");
  Serial.println(usedSsid);
  Serial.print("Site cible = ");
  Serial.println(SITE_BASE_URL);

  syncClock();
  updateConnectionLeds();
  showDisplayMessage("Wi-Fi connecte", espIp, "Sync site...", COLOR_OK);
}

void syncRemoteConfig(bool showFeedback = false) {
  if (WiFi.status() != WL_CONNECTED) {
    return;
  }

  HTTPClient http;
  WiFiClientSecure secureClient;
  WiFiClient plainClient;
  const String url = buildApiUrl(String("?action=config&device_id=") + DEVICE_ID);

  if (!beginHttpClient(http, url, secureClient, plainClient)) {
    Serial.println("Impossible d'ouvrir la connexion HTTP pour la config.");
    return;
  }

  http.addHeader("Accept", "application/json");
  http.setTimeout(10000);

  if (showFeedback) {
    showDisplayMessage("Connexion site", "Recuperation", "configuration...", COLOR_ACCENT);
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

  if (httpCode < 0) {
    const String httpError = http.errorToString(httpCode);
    Serial.print("Erreur HTTP config: ");
    Serial.println(httpError);
    if (showFeedback) {
      signalError();
      showDisplayMessage("Config impossible", "HTTP " + String(httpCode), httpError, COLOR_ERROR);
      delay(1200);
    }
  } else if (httpCode == 200) {
    const String remoteSiteName = extractJsonValue(body, "site_name");
    const String remoteMessage = extractJsonValue(body, "display_message");
    const String remoteSuccessMessage = extractJsonValue(body, "success_message");
    const long remoteCooldown = extractJsonLongValue(body, "cooldown_ms", static_cast<long>(scanCooldownMs));
    const long remoteClockRefresh = extractJsonLongValue(body, "clock_refresh_ms", static_cast<long>(clockRefreshMs));
    const long remoteConfigRefresh = extractJsonLongValue(body, "config_refresh_ms", static_cast<long>(configRefreshMs));
    const bool remoteLedsEnabled = extractJsonBoolValue(body, "led_enabled", ledsEnabled);
    const bool remoteBuzzerEnabled = extractJsonBoolValue(body, "buzzer_enabled", buzzerEnabled);

    if (remoteSiteName.length() > 0) {
      siteName = remoteSiteName;
    }
    if (remoteMessage.length() > 0) {
      idleMessage = remoteMessage;
    }
    if (remoteSuccessMessage.length() > 0) {
      successMessage = remoteSuccessMessage;
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
    ledsEnabled = remoteLedsEnabled;
    buzzerEnabled = remoteBuzzerEnabled;
    updateConnectionLeds();

    lastConfigSyncAt = millis();

    if (showFeedback) {
      showDisplayMessage("Site synchronise", String(DEVICE_ID), idleMessage, COLOR_OK);
      delay(1200);
    }
  } else if (showFeedback) {
    signalError();
    showDisplayMessage("Site indisponible", "HTTP " + String(httpCode), "Mode local OK", COLOR_WARN);
    delay(1200);
  }

  http.end();
}

void sendBadge(const String& badgeId) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Wi-Fi non connecte.");
    signalError();
    showDisplayMessage("Wi-Fi non connecte", "Reconnexion...", "Reessaie", COLOR_ERROR);
    return;
  }

  setStatusLeds(false, false);
  showDisplayMessage("Badge detecte", badgeId, "Envoi au site...", COLOR_ACCENT);

  HTTPClient http;
  WiFiClientSecure secureClient;
  WiFiClient plainClient;
  const String url = buildApiUrl();
  const String payload = String("{") +
    "\"badge_id\":\"" + escapeJson(badgeId) + "\"," +
    "\"device_id\":\"" + escapeJson(String(DEVICE_ID)) + "\"," +
    "\"device_label\":\"" + escapeJson(String(DEVICE_LABEL)) + "\"," +
    "\"firmware\":\"" + escapeJson(String(FIRMWARE_VERSION)) + "\"," +
    "\"ip\":\"" + escapeJson(WiFi.localIP().toString()) + "\"," +
    "\"rssi\":" + String(WiFi.RSSI()) +
    "}";

  if (!beginHttpClient(http, url, secureClient, plainClient)) {
    Serial.println("Impossible d'ouvrir la connexion HTTP pour le badge.");
    signalError();
    showDisplayMessage("Erreur connexion", "HTTP init KO", "Reessaie", COLOR_ERROR);
    return;
  }

  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("User-Agent", String("JustInTime-ESP32/") + FIRMWARE_VERSION);
  http.setTimeout(12000);

  const int httpCode = http.POST(payload);
  const String body = http.getString();

  if (httpCode < 0) {
    const String httpError = http.errorToString(httpCode);
    Serial.println("------------------------------");
    Serial.print("Badge lu : ");
    Serial.println(badgeId);
    Serial.print("POST URL : ");
    Serial.println(url);
    Serial.print("HTTP erreur: ");
    Serial.print(httpCode);
    Serial.print(" -> ");
    Serial.println(httpError);
    Serial.println("------------------------------");
    http.end();
    signalError();
    showDisplayMessage("Connexion site KO", "HTTP " + String(httpCode), httpError, COLOR_ERROR);
    delay(2500);
    showIdleScreen();
    lastClockRefreshAt = millis();
    return;
  }

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
  const String originalEventType = extractJsonValue(body, "original_event_type");
  const bool alreadyProcessed = eventType == "duplicate" || serverMessage.indexOf("deja pris en compte") >= 0;
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
    if (alreadyProcessed) {
      const String duplicateType = originalEventType.length() > 0 ? originalEventType : "in";
      signalSuccess(duplicateType);
      showDisplayMessage(
        "Deja pointe",
        personName.length() > 0 ? personName : "Badge reconnu",
        serverMessage.length() > 0 ? serverMessage : "Pointage deja pris en compte",
        COLOR_WARN
      );
    } else {
      String actionLine = "OK";
      uint16_t actionColor = COLOR_OK;

      if (eventType == "out" || serverMessage.indexOf("sortie") >= 0) {
        actionLine = "SORTIE";
        actionColor = COLOR_OUT;
      } else if (eventType == "in" || serverMessage.indexOf("entree") >= 0) {
        actionLine = "ENTREE";
        actionColor = COLOR_OK;
      }

      signalSuccess(eventType.length() > 0 ? eventType : (actionLine == "SORTIE" ? "out" : "in"));
      showScanResult(
        personName.length() > 0 ? personName : "Badge reconnu",
        actionLine,
        scanTime,
        actionColor
      );
    }
  } else if (httpCode == 404) {
    signalError();
    showDisplayMessage("Badge inconnu", badgeId, errorMessage.length() > 0 ? errorMessage : "Ajoute-le au site", COLOR_ERROR);
  } else {
    signalError();
    showDisplayMessage("Erreur serveur", "HTTP " + String(httpCode), errorMessage.length() > 0 ? errorMessage : "Reessaie", COLOR_WARN);
  }

  http.end();
  delay(3000);
  updateConnectionLeds();
  showIdleScreen();
  lastClockRefreshAt = millis();
}

void setup() {
  Serial.begin(115200);
  delay(1000);

  pinMode(GREEN_LED_PIN, OUTPUT);
  pinMode(RED_LED_PIN, OUTPUT);
  pinMode(RC522_SS_PIN, OUTPUT);
  pinMode(RC522_RST_PIN, OUTPUT);
  pinMode(TFT_CS_PIN, OUTPUT);
  pinMode(TFT_DC_PIN, OUTPUT);

  initBuzzer();
  setStatusLeds(false, false);
  deselectSpiDevices();
  digitalWrite(RC522_RST_PIN, HIGH);

  SPI.begin(SPI_SCK_PIN, SPI_MISO_PIN, SPI_MOSI_PIN, RC522_SS_PIN);

  initDisplay();
  connectWifi();
  if (WiFi.status() == WL_CONNECTED) {
    syncRemoteConfig(true);
  }

  const bool rfidReady = initRfidReader(true);
  Serial.println(rfidReady ? "Lecteur RFID pret. Passe une carte devant le RC522..." : "Lecteur RFID non detecte au demarrage.");

  showIdleScreen();
  lastClockRefreshAt = millis();
  lastRfidHealthCheckAt = millis();
}

void loop() {
  if (wifiPortalActive) {
    dnsServer.processNextRequest();
    wifiPortalServer.handleClient();
    updateConnectionLeds();

    if (wifiCredentialsJustSaved && millis() - wifiCredentialsSavedAt > 1500) {
      showDisplayMessage("Wi-Fi sauvegarde", "Redemarrage...", "Patiente", COLOR_OK);
      delay(500);
      ESP.restart();
    }

    if (millis() - wifiPortalStartedAt > WIFI_PORTAL_TIMEOUT_MS) {
      showDisplayMessage("Config expiree", "Redemarrage...", "Refais la procedure", COLOR_WARN);
      delay(800);
      ESP.restart();
    }

    delay(10);
    return;
  }

  updateConnectionLeds();

  if (WiFi.status() != WL_CONNECTED) {
    connectWifi();
    if (WiFi.status() == WL_CONNECTED) {
      syncRemoteConfig(true);
    }
    if (wifiPortalActive) {
      return;
    }
  }

  if (millis() - lastConfigSyncAt >= configRefreshMs) {
    syncRemoteConfig(false);
  }

  if (millis() - lastRfidHealthCheckAt >= RFID_HEALTH_CHECK_MS) {
    lastRfidHealthCheckAt = millis();
    if (!isRfidReaderReady()) {
      Serial.println("RC522 ne repond plus, tentative de reinitialisation...");
      initRfidReader(false);
      showIdleScreen();
      lastClockRefreshAt = millis();
    }
  }

  deselectSpiDevices();

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
