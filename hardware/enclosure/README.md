# Boitier prototype standard JustInTime

Ce dossier contient un prototype standard de boitier mural pour la pointeuse :
- ecran ILI9341 en facade, en haut
- RC522 sous l'ecran, lecture a travers une facade amincie
- capot arriere visse
- acces USB par le bas
- logement interne generique pour ESP32 type DevKit 30 broches

## Hypotheses utilisees

Ces dimensions sont des hypotheses standard, a verifier sur ton materiel reel avant impression finale.

- Ecran ILI9341 SPI breakout : PCB 86 x 50 mm
- Fenetre visible ecran : 58 x 44 mm
- RC522 standard : PCB 60 x 40 mm
- RC522 trous approx. : 3.2 mm, entraxes approx. 53 x 33 mm
- ESP32 DevKit / Elegoo 30 broches : carte approx. 55 x 28 mm
- Profondeur externe visee : 34 mm

## Fichiers

- justintime_wall_box.scad : modele parametrique OpenSCAD

## Export STL

OpenSCAD n'etait pas installe dans cet environnement, donc je n'ai pas pu exporter le STL ici.

Pour l'export :
1. ouvrir justintime_wall_box.scad dans OpenSCAD
2. verifier les dimensions en haut du fichier
3. choisir la vue assemblee ou la vue impression
4. exporter la coque avant en STL
5. exporter le capot arriere en STL

## Conseils d'impression

- Matiere : PLA+ ou PETG
- Hauteur de couche : 0.2 mm
- Perimetres : 4
- Infill : 20 a 30%
- Support : normalement inutile si imprime a plat

## Avant impression definitive

Verifier au pied a coulisse :
- largeur / hauteur du PCB ecran
- dimensions visibles exactes de l'afficheur
- dimensions et trous du RC522
- position du port USB de l'ESP32
- hauteur totale avec connecteurs soudes

Le modele est volontairement parametrique pour corriger ces points rapidement.
