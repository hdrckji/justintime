# Configuration ESP32 + RFID pour JustInTime

Le site JustInTime peut maintenant dialoguer **directement** avec l'ESP32 :

- **remontée** des scans RFID vers le site via `POST /api/attendance/rfid`
- **descente** de la configuration vers l'ESP32 via `GET /api/attendance/rfid?action=config&device_id=...`

Exemple de JSON envoyé par l'ESP32 :

```json
{
  "badge_id": "A4-7B-2C-91",
  "device_id": "ESP32-RFID-01",
  "device_label": "Entree",
  "firmware": "jit-esp32-2.0"
}
```

## 1. Matériel supposé

Ce guide part du cas le plus courant :
- `ESP32 DevKit`
- lecteur RFID `RC522 / MFRC522`
- écran OLED `SSD1306` (optionnel)
- cartes ou badges `13.56 MHz`
- câbles Dupont

> Si ton lecteur est un `PN532`, le câblage et le sketch changent.

## 2. Câblage ESP32 ↔ RC522 / OLED

### RC522

| RC522 | ESP32 |
|---|---|
| `3.3V` | `3V3` |
| `GND` | `GND` |
| `SDA / SS` | `GPIO 5` |
| `SCK` | `GPIO 18` |
| `MOSI` | `GPIO 23` |
| `MISO` | `GPIO 19` |
| `RST` | `GPIO 4` |
| `IRQ` | non connecté |

### OLED SSD1306 (I2C)

| OLED | ESP32 |
|---|---|
| `VCC` | `3V3` |
| `GND` | `GND` |
| `SDA` | `GPIO 21` |
| `SCL` | `GPIO 22` |

## 3. URL du site utilisée par l'ESP32

Le sketch pointe directement vers le site :

```cpp
const char* SITE_BASE_URL = "https://diligent-embrace-production.up.railway.app";
const char* RFID_ENDPOINT = "/api/attendance/rfid";
```

## 4. Avant de flasher l'ESP32

Dans `esp32/justintime_rfid_reader.ino`, complète :

1. `WIFI_SSID`
2. `WIFI_PASSWORD`
3. `DEVICE_ID`
4. `DEVICE_LABEL`

Exemple :

```cpp
const char* WIFI_SSID = "MonWifi";
const char* WIFI_PASSWORD = "MonMotDePasse";
const char* DEVICE_ID = "ESP32-RFID-ACCUEIL";
const char* DEVICE_LABEL = "Accueil";
```

## 5. Ce que fait le sketch

Au démarrage, l'ESP32 :
1. se connecte au Wi-Fi,
2. synchronise l'heure NTP,
3. récupère la configuration distante du site,
4. attend un badge,
5. envoie l'UID du badge au site en HTTPS,
6. affiche le retour `ENTREE` / `SORTIE` sur l'écran OLED.

## 6. Associer une vraie carte à un employé

Le site compare la valeur reçue avec le champ `badge_id` de l'employé.

Donc :
1. ouvre le moniteur série,
2. scanne une carte,
3. récupère son UID,
4. colle cet UID dans le badge RFID du collaborateur dans l'admin JustInTime.

## 7. Test rapide

1. flashe `esp32/justintime_rfid_reader.ino`
2. ouvre le moniteur série (`115200` bauds)
3. vérifie que l'ESP32 obtient une IP Wi-Fi
4. vérifie qu'il récupère la config du site
5. passe un badge
6. vérifie la réponse HTTP (`200` attendu)

## 8. Dépannage

### Le badge se lit mais rien n'arrive sur le site
- vérifie le Wi-Fi
- vérifie `SITE_BASE_URL`
- vérifie que Railway est bien en ligne
- regarde le code HTTP dans le moniteur série

### Réponse `Badge inconnu`
- le scan fonctionne, mais le `badge_id` n'existe pas encore dans la base
- il faut enregistrer cet UID pour l'employé concerné

### L'écran OLED reste noir
- vérifie `GPIO 21/22`
- vérifie l'adresse I2C (`0x3C` dans le sketch)
- vérifie l'alimentation en `3.3V`

---

Si tu veux, l'étape suivante peut être :
- ajouter un **bouton de resynchronisation** sur l'ESP32,
- ou afficher sur le site le **dernier ESP32 connecté** avec son état réseau.
