# Boitier prototype semi-precis JustInTime

Ce dossier contient un prototype mural plus precis pour la pointeuse :
- ecran ILI9341 en facade, en haut
- RC522 sous l'ecran, lecture a travers une facade amincie
- capot arriere visse
- acces USB generique par le bas
- fixation ESP32 sur la face interne du capot, dans la zone haute
- volume interne volontairement generique en attendant le PCB final

## Hypotheses utilisees

La facade est maintenant basee sur des dimensions confirmees par l'utilisateur.
L'interieur reste volontairement tolerant car la carte electronique finale n'est pas encore arretee.

- Ecran ILI9341 : PCB 44 x 73 mm
- Fenetre visible ecran : ouverture reduite a 40 x 53 mm
- RC522 : PCB 39 x 60 mm
- RC522 : trous hauts a 7 mm du haut et 7 mm des bords lateraux
- RC522 : trous bas a 15 mm du bas et 3 mm des bords lateraux
- Profondeur externe provisoire : 40 mm
- Fixation RC522 : plots avec trou oblong leger pour absorber la tolerance et l'impression 3D
- Fixation capot : plots de vissage deplaces dans les coins et attaches a la coque pour eviter tout demarrage dans le vide a l'impression
- Maintien ecran : rails lateraux + butee basse, a completer si besoin selon l'epaisseur reelle du module
- ESP32 : support parametrique ajoute sur le capot pour une carte env. 51 x 28 mm, 4 trous proches des coins, tournee a 90 degres, avec sortie USB sur le petit cote haut du capot

## Fichiers

- justintime_wall_box.scad : modele parametrique OpenSCAD

## Strategie de conception

Cette version cherche a figer surtout la facade :
- dimensions exterieures du boitier
- fenetre ecran
- zone RFID
- LEDs et buzzer

Le capot integre maintenant :
- 4 plots de fixation pour ESP32 sur sa face interne
- un rebord arriere de 6 mm pour creer un volume supplementaire
- une encoche sur le petit cote haut de ce rebord pour laisser depasser legerement le port USB
- un petit support d'impression sacrificiel dans l'encoche USB pour eviter un pont dans le vide
- des cotes parametriques si le module ESP32 reel differe du DevKit suppose

Parametrage actuel ESP32 retenu :
- carte env. 51 x 28 mm
- 4 trous de fixation proches des coins
- interpretation du "1 mm de chaque bord" comme un retrait de 1 mm entre le bord de la carte et le bord du trou
- carte tournee a 90 degres sur le capot
- rebord de capot de 6 mm pour embarquer l'ESP32 dans le volume du capot

L'interieur n'est pas encore le design final. Il est prevu pour :
- laisser de la marge pendant le prototypage
- accepter un cablage encore evolutif
- eviter de devoir reimprimer toute la coque quand le PCB final arrivera

## Export STL

OpenSCAD n'etait pas installe dans cet environnement, donc je n'ai pas pu exporter le STL ici.

Pour l'export :
1. ouvrir justintime_wall_box.scad dans OpenSCAD
2. verifier les dimensions en haut du fichier
3. si besoin, ajuster les parametres `esp32_*` selon la carte exacte
4. choisir la vue assemblee ou la vue impression
5. exporter la coque avant en STL
6. exporter le capot arriere en STL

## Conseils d'impression

- Matiere : PLA+ ou PETG
- Hauteur de couche : 0.2 mm
- Perimetres : 4
- Infill : 20 a 30%
- Support : normalement inutile si imprime a plat
- Commencer par imprimer uniquement la facade/coque pour valider les ouvertures avant le capot complet

## Avant impression definitive

Verifier au pied a coulisse :
- epaisseur reelle du module ecran, composants compris
- epaisseur reelle de la RC522
- position exacte du port USB de la future carte
- hauteur totale avec connecteurs soudes
- position souhaitee des LEDs et du buzzer si tu veux les deplacer sur la facade
- entraxe reel des 4 trous de fixation de l'ESP32 si ton DevKit n'est pas au standard suppose

Le modele reste volontairement parametrique pour corriger rapidement les supports internes quand le PCB final arrivera.
