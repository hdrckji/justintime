# JustInTime — Gestion de Presence PME

Application web PHP/MySQL professionnelle de pointage des employés (RFID + manuel) pour une PME.

## 🚀 Fonctionnalites

**Pointage** (public — authentification requise)
- Badge RFID : Scanner automatique → entrée/sortie
- Pointage manuel : Sélectionner collaborateur + action
- Tableau de bord temps réel : Présents/absents + historique

**Admin** (protégé — rôle "admin" uniquement)
- Gestion collaborateurs : CRUD + prénom + badge RFID
- Gestion absences : Certificats, congés, autres + dates
- Horaires : Définir horaire prévu par collaborateur/jour

**Reporting** (tous les utilisateurs)
- Heures par semaine : Solde travaillées vs prévu
- Détail par jour : Tableau comparatif avec differences

## 🔐 Authentification

**Identifiants par défaut** (après `setup.php`)
```
Login    : admin
Password : admin
```

⚠️ CHANGEZ IMMEDIATEMENT APRES INSTALLATION

**Rôles**
- `admin` : Accès complet (à développer : gestion utilisateurs)
- `hr` : Reporting + absences (futur)
- `viewer` : Pointage + reporting

## 🚀 Installation IONOS (Mutualisé PHP)

1. **Upload via FTP** : Tous les fichiers PHP dans `public_html/`
2. **Initialiser** : Ouvre `https://votredomaine.com/setup.php`
3. **Supprimer setup.php** du serveur
4. **Accéder** : `https://votredomaine.com/` → login: admin/admin

## ⚙️ Configuration (config.php)

```php
define('DB_HOST', 'db5020112680.hosting-data.io');
define('DB_PORT', 3306);
define('DB_NAME', 'dbs15493157');      // Votre base IONOS
define('DB_USER', 'dbu5557192');       // Votre utilisateur MySQL
define('DB_PASS', 'Megdrive15');       // Votre mot de passe
```

## 📄 Pages principales

| Page | Accès | Description |
|------|-------|-------------|
| `index.php` | Login requis | Pointage + tableau de bord |
| `admin.php` | Admin uniquement | Collaborateurs, absences, horaires |
| `reporting.php` | Login requis | Heures par semaine + détail |
| `login.php` | Public | Authentification |

## 🛠️ Support

- `.htaccess` protège `config.php` (pas d'accès direct)
- Supprimer `setup.php` après initialisation
- Sauvegarder régulièrement base MySQL IONOS
