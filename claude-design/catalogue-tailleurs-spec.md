# Spec Catalogue Tailleurs — Yombal.sn

## Objectif

Permettre au client de trouver rapidement le tailleur qui correspond à ses besoins (ville, spécialité, budget, disponibilité).

## Sections attendues

### 1. Header de page
- Titre : "Nos tailleurs"
- Sous-titre : "Trouvez le tailleur idéal près de chez vous"
- Barre de recherche (recherche par nom, ville, spécialité)

### 2. Filtres
- Ville / région
- Spécialité (tenue homme, femme, enfant, boubou, costume, etc.)
- Fourchette de prix
- Disponibilité (disponible maintenant)
- Note minimale
- Sur mobile : filtres en tiroir (drawer) accessible via bouton [Filtrer]

### 3. Grille de résultats
- 2 colonnes sur mobile, 3 sur tablette, 4 sur desktop
- Chaque carte tailleur :
  - Photo de profil
  - Nom du tailleur
  - Ville
  - Spécialité principale
  - Note (étoiles)
  - Nombre d'avis
  - Tarif indicatif (ex. "À partir de 5 000 FCFA")
  - Badge "Disponible" si applicable
  - CTA [Voir le profil]

### 4. Pagination ou chargement infini
- Préférer la pagination pour le SEO
- Afficher le nombre total de résultats

## CTA principaux

| CTA | Action |
|-----|--------|
| Voir le profil | Ouvre la fiche tailleur |
| Filtrer | Ouvre le tiroir de filtres (mobile) |
| Rechercher | Lance la recherche |

## Données affichées

- Nom, photo, ville, spécialité, note, nb avis, tarif indicatif

## Contraintes mobile

- Filtres en tiroir, pas en sidebar
- Cartes : 2 par ligne minimum
- CTA toujours visible sur chaque carte
- Barre de recherche fixe en haut

## Points à éviter

- Liste sans images (ne pas afficher les tailleurs sans photo)
- Filtres trop nombreux affichés en permanence
- Manque d'information sur le tailleur dans la carte
- Absence d'indication de disponibilité
