# Composants UI — Yombal.sn

## Objectif

Liste des composants réutilisables à designer pour Claude Design.
Chaque composant doit être décliné en version mobile et desktop.

---

## 1. Carte Tailleur

Usage : Catalogue tailleurs, section homepage, résultats de recherche.

Contenu :
- Photo de profil (format carré ou rond)
- Nom du tailleur
- Ville
- Spécialité (ex. "Tenue femme, Boubou")
- Note étoiles (1-5)
- Nombre d'avis
- Tarif indicatif (ex. "À partir de 5 000 FCFA")
- Badge disponibilité (optionnel)
- CTA [Voir le profil]

États : normal, hover (desktop), sélectionné.

---

## 2. Carte Tissu

Usage : Catalogue tissus, section homepage.

Contenu :
- Photo principale (gros plan motif)
- Nom du tissu
- Type (Wax, Bazin, Coton...)
- Prix (ex. "3 500 FCFA / mètre")
- Vendeur (nom)
- Badge stock
- CTA [Voir le tissu] + icône panier rapide

États : normal, hover, rupture de stock (grisé).

---

## 3. Carte Modèle

Usage : Catalogue modèles, section homepage.

Contenu :
- Photo du modèle (pleine hauteur)
- Catégorie (Femme / Homme / Enfant)
- Style (ex. "Boubou cérémonie")
- Fourchette prix confection
- CTA [Choisir ce modèle]

États : normal, hover, sélectionné.

---

## 4. Barre de navigation (Header)

Desktop :
- Logo à gauche
- Liens : Tailleurs | Tissus | Modèles | Comment ça marche
- À droite : Recherche | Connexion | Panier

Mobile :
- Logo centré ou à gauche
- Icône hamburger à droite
- Menu drawer en slide depuis la gauche
- Icône panier toujours visible

---

## 5. Bouton CTA Principal

Variantes :
- Primaire : fond couleur principale, texte blanc, arrondi
- Secondaire : bordure couleur principale, fond transparent
- Désactivé : grisé

Tailles : SM (mobile), MD (défaut), LG (hero)

---

## 6. Formulaire de mesures

Champs :
- Tour de poitrine (cm)
- Tour de taille (cm)
- Tour de hanches (cm)
- Hauteur (cm)
- Longueur souhaitée (cm)
- Notes spéciales (textarea)

Design :
- Labels clairs au-dessus de chaque champ
- Unité (cm) affichée dans le champ ou à droite
- Champs numériques (clavier numérique sur mobile)
- Validation en temps réel

---

## 7. Badge statut commande

Variantes :
- En attente de confirmation (jaune)
- Confirmée (bleu)
- En cours de confection (orange)
- Expédiée (violet)
- Livrée (vert)
- Annulée (rouge)

Usage : espace client, emails, notifications.

---

## 8. Barre de progression (tunnel commande)

Format : 4 étapes numérotées avec indicateur visuel de l'étape en cours.

Étapes : Panier → Confection → Livraison → Paiement

Mobile : icônes + numéros, texte court ou absent.

---

## 9. Bloc "Comment ça marche"

4 étapes visuelles :
- Icône + numéro + titre + description courte
- Connectées par une flèche ou une ligne
- Mobile : empilées verticalement

---

## 10. Footer

Colonnes :
- À propos de Yombal
- Liens utiles (CGU, CGV, Contact)
- Devenir partenaire
- Réseaux sociaux
- Logo PayTech sécurisé

Mobile : colonnes empilées, accordéon optionnel.

---

## Priorité de design

| Composant | Priorité |
|-----------|----------|
| Carte Tailleur | Haute |
| Carte Tissu | Haute |
| Bouton CTA | Haute |
| Header navigation | Haute |
| Barre progression checkout | Haute |
| Carte Modèle | Moyenne |
| Formulaire mesures | Moyenne |
| Badge statut commande | Moyenne |
| Bloc comment ça marche | Moyenne |
| Footer | Basse |
