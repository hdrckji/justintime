# Configuration ESP32 + RFID pour JustInTime

Ce projet est déjà prêt côté serveur : `app.py` expose l'API `POST /api/attendance/rfid` et attend un JSON de la forme :

```json
{ "badge_id": "A4-7B-2C-91" }
```

## 1. Matériel supposé

Ce guide part du cas le plus courant :
- `ESP32 DevKit`
- lecteur RFID `RC522 / MFRC522`
- cartes ou badges `13.56 MHz`
- câbles Dupont

> Si ton lecteur est un `PN532`, le câblage et le code changent. Dans ce cas, dis-moi juste le modèle exact.

## 2. Câblage ESP32 ↔ RC522

| RC522 | ESP32 |
|---|---|
| `3.3V` | `3V3` |
| `GND` | `GND` |
| `SDA / SS` | `GPIO 5` |
| `SCK` | `GPIO 18` |
| `MOSI` | `GPIO 23` |
| `MISO` | `GPIO 19` |
| `RST` | `GPIO 22` |
| `IRQ` | non connecté |

## 3. Préparer le serveur du projet

Depuis le dossier du projet :

```bash
python3 -m pip install -r requirements.txt
python3 app.py
```

Le serveur écoute déjà sur :
- `http://localhost:5001`
- et sur le réseau local via `0.0.0.0:5001`

Pour récupérer l'IP locale de ton Mac :

```bash
ipconfig getifaddr en0
```

Exemple : `192.168.1.50`

## 4. Flash du code ESP32

Un sketch prêt à l'emploi a été ajouté ici :

- `esp32/justintime_rfid_reader.ino`

À modifier avant upload :
1. `WIFI_SSID`
2. `WIFI_PASSWORD`
3. `SERVER_URL`

Exemple :

```cpp
const char* SERVER_URL = "http://192.168.1.50:5001/api/attendance/rfid";
```

## 5. Comment lier une vraie carte au projet

Le backend compare simplement la valeur reçue avec le champ `badge_id` de l'employé.

Donc le plus simple est :
1. ouvrir le moniteur série de l'ESP32,
2. scanner une carte,
3. récupérer son UID (ex: `A4-7B-2C-91`),
4. mettre cette valeur comme `badge_id` pour l'employé concerné.

Tu peux soit :
- remplacer les badges de démo `RFID-1001`, `RFID-1002`, etc.,
- soit garder ces codes et faire une table de correspondance dans l'ESP32.

## 6. Test rapide

1. Lance `app.py`
2. Mets ton Mac et l'ESP32 sur le même Wi-Fi
3. Ouvre le dashboard dans le navigateur
4. Passe une carte devant le lecteur
5. Vérifie dans le moniteur série :
   - l'UID lu,
   - le code HTTP (`200` attendu),
   - la réponse JSON du serveur

## 7. Dépannage

### Rien ne se lit
- vérifie le **3.3V**, pas le `5V`
- vérifie `GPIO 5 / 18 / 19 / 22 / 23`
- rapproche bien la carte du lecteur

### Lecture OK mais pas d'envoi HTTP
- vérifie le Wi-Fi
- vérifie `SERVER_URL`
- vérifie que `app.py` tourne encore sur le Mac
- vérifie le pare-feu macOS si besoin

### Réponse `Badge inconnu`
- le scan fonctionne, mais le `badge_id` n'existe pas encore dans la base
- il faut enregistrer ce UID pour un employé

---

Si tu veux, je peux maintenant t'aider à faire la **prochaine étape** :
1. retrouver le **modèle exact** de ton lecteur,
2. adapter le câblage si besoin,
3. ou préparer une version avec **association UID → employé** automatiquement.
