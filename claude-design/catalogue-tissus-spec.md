# Spec Catalogue Tissus — Yombal.sn

## Objectif

Permettre au client de parcourir et choisir un tissu africain avec clarté : composition, prix au mètre, vendeur, disponibilité.

## Sections attendues

### 1. Header de page
- Titre : "Nos tissus"
- Sous-titre : "Wax, bazin, soie, coton... choisissez votre tissu"
- Barre de recherche

### 2. Filtres
- Type de tissu (Wax, Bazin, Coton, Soie, Kente, Bogolan, etc.)
- Couleur (palette visuelle de sélection)
- Vendeur
- Fourchette de prix (par mètre ou par lot)
- Disponibilité en stock
- Sur mobile : tiroir de filtres

### 3. Grille de résultats
- 2 colonnes mobile, 3 tablette, 4 desktop
- Chaque carte tissu :
  - Photo principale (gros plan tissu)
  - Nom du tissu
  - Type (ex. "Wax hollandais")
  - Prix (ex. "3 500 FCFA / mètre")
  - Vendeur (nom + ville)
  - Badge stock : "En stock" / "Stock limité" / "Rupture"
  - CTA [Voir le tissu] + bouton rapide [Ajouter au panier]

### 4. Option "Faire confectionner"
- Banner ou bloc proéminent : "Ce tissu vous plaît ? Faites-le confectionner par un de nos tailleurs."
- CTA [Choisir un tailleur]

## CTA principaux

| CTA | Action |
|-----|--------|
| Voir le tissu | Ouvre la fiche tissu |
| Ajouter au panier | Ajout direct sans ouvrir la fiche |
| Choisir un tailleur | Lance le flux de confection |

## Données affichées

- Nom, photo, type, prix, vendeur, disponibilité

## Contraintes mobile

- Photo tissu : format carré ou 4:3, gros plan pour voir le motif
- Prix bien visible en FCFA
- Bouton panier accessible sans ouvrir la fiche
- Filtres en tiroir

## Points à éviter

- Photos floues ou trop petites
- Prix ambigu (préciser si c'est par mètre, par lot, ou prix total)
- Absence d'information sur le vendeur
- Pas de chemin vers la confection depuis le catalogue
