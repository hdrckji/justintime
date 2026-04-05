# 🚀 Guide de Déploiement IONOS

Version finale du projet JustInTime convertie en **PHP pur** compatible hébergement mutualisé IONOS.

## ✅ Checklist avant upload

- [ ] Tous les fichiers PHP sont présents
- [ ] Fichiers CSS et JS sont dans `static/`
- [ ] Aucun fichier Python (Flask) inclusl'app.py)
- [ ] Fichier `config.php` complété avec les identifiants IONOS

## 📦 Fichiers à uploader

Via le gestionnaire de fichiers IONOS ou un client FTP (FileZilla, WinSCP, etc.) :

```
/public_html/  (ou dossier racine du site)
├── index.php
├── login.php
├── logout.php
├── admin.php
├── reporting.php
├── config.php
├── db.php
├── auth.php
├── setup.php              ← À SUPPRIMER APRES STEP 4
├── .htaccess
├── api/
│   ├── dashboard.php
│   ├── rfid.php
│   ├── manual.php
│   ├── employees.php
│   ├── absences.php
│   └── scheduled_hours.php
└── static/
    ├── css/styles.css
    └── js/app.js
```

## 🎯 Procédure pas à pas

### Step 1 — Copier `config.php` avec tes identifiants

**Édite localement** le fichier `config.php` avec :

```php
define('DB_HOST', 'db5020112680.hosting-data.io');
define('DB_PORT', 3306);
define('DB_NAME', 'dbs15493157');      // ← TON nom de base
define('DB_USER', 'dbu5557192');       // ← TON utilisateur
define('DB_PASS', 'Megdrive15');       // ← TON mot de passe
```

Sauvegarde.

### Step 2 — Upload tous les fichiers

Via FTP ou le gestionnaire IONOS → `/public_html/` ou `/`

⚠️ Assure-toi que le fichier `config.php` est bien uploadé avec tes identifiants.

### Step 3 — Initialiser la base

1. Ouvre dans le navigateur :
   ```
   https://votredomaine.com/setup.php
   ```

2. Attends que tu voies :
   ```
   ✅ Base initialisee avec succes.
   ✅ 20 employes crees (badges RFID-1001 à RFID-1020).
   ✅ Horaires par defaut (8h/jour, lundi-vendredi).
   ⚠️  SUPPRIMEZ CE FICHIER (setup.php) maintenant !
   ```

3. Si erreur `Access denied` :
   - Vérifie que `DB_NAME` est correcte (regarde IONOS panneau MySQL)
   - Relance setup.php

### Step 4 — Supprimer setup.php

Via le gestionnaire de fichiers IONOS, **supprime** le fichier `setup.php`.

⚠️ C'est CRITIQUE pour la sécurité.

### Step 5 — Se connecter

1. Ouvre : `https://votredomaine.com/`
2. Login : `admin`
3. Mot de passe : `admin`

**CHANGE LE MOT DE PASSE** (gestion users en admin — à développer pour fullaccess)

## 🎮 Utilisation de base

### Pointage employé
1. `https://votredomaine.com/` → scan badge ou sélectionner + pointage manuel
2. Voir le tableau de bord en temps réel

### Admin — Gestion collaborateurs
1. Menu admin → Onglet "Collaborateurs"
2. Ajouter/modifier/supprimer + badge RFID

### Admin — Absences (Certificats)
1. Menu admin → Onglet "Absences"
2. Sélectionner employé + type (sick = certificat) + dates

### Admin — Horaires
1. Menu admin → Onglet "Horaires"
2. Sélectionner employé → Définir heures par jour (0-12h)

### Reporting — Heures par semaine
1. `https://votredomaine.com/reporting.php`
2. Choisir employé + semaine
3. Voir tableau : horaire prévu vs travaillé + solde

## 🐛 Dépannage

**Page erreur 500 sur setup.php**
- Vérifier identifiants DB dans config.php
- Vérifier que l'utilisateur MySQL a permission `CREATE TABLE`
- Regarder les logs PHP IONOS

**Login ne fonctionne pas**
- Vérifier que setup.php a été lancé une fois
- La table `users` doit exister avec utilisateur `admin`

**Badge scanner ne fonctionne pas**
- Le badge doit commencer par `RFID-` (ex: `RFID-1001`)
- Vérifier l'employé est `active = 1`

**Absences pas visibles au pointage**
- Les absences existent dans la table `absences`
- À développer : masquer employés absents en pointage

## 🔐 Sécurité

- `.htaccess` protège directement les fichiers sensibles (`config.php`, `db.php`)
- Mots de passe en `bcrypt` (PASSWORD_DEFAULT PHP)
- Sessions sécurisées PHP
- **Supprimer `setup.php` après initialisation** (CRITIQUE)

## 📝 Prochaines étapes

- [ ] Interface de changement de mot de passe (admin uniquement pour maintenant)
- [ ] Gestion de rôles HR (accès absences + reporting, pas admin)
- [ ] Export Excel heures par mois
- [ ] Alertes sur absences impayées (certificat expiré)
- [ ] Intégration lecteur RFID USB (JS natif)

## 📞 Support

Pour toute erreur, envoie une capture d'écran du message d'erreur + le contenu exact de `config.php`.
