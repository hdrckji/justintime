# JustInTime - Gestion de presence PME

Application web de pointage des employes (RFID + manuel) pour une equipe d'environ 20 personnes.

## Fonctionnalites

- Pointage par badge RFID (scan -> Entree/Sortie automatique)
- Pointage manuel via boutons Entree/Sortie
- Tableau de bord en temps reel
- Statut de chaque employe (present/absent)
- Historique recent des pointages
- Persistance locale via SQLite

## Demarrage

1. Installer les dependances:

```bash
python3 -m pip install -r requirements.txt
```

2. Lancer le serveur:

```bash
python3 app.py
```

3. Ouvrir dans le navigateur:

- http://localhost:8080

## Donnees initiales

La base est creee automatiquement dans `data/attendance.db` avec 20 employes de demo:

- Badge format: `RFID-1001` a `RFID-1020`

## Notes de production

- Mettre `debug=False` dans `app.py`
- Passer derriere un reverse proxy (Nginx/Caddy)
- Sauvegarder regulierement `data/attendance.db`
