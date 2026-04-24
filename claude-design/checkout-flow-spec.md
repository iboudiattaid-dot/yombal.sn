# Spec Tunnel de Commande — Yombal.sn

## Objectif

Guider le client du panier jusqu'au paiement en maximum 4 étapes claires, avec récapitulatif complet avant confirmation.

## Étapes du tunnel

### Étape 1 — Panier

Contenu affiché :
- Liste des articles (tissu, confection, etc.)
- Photo, nom, quantité, prix unitaire, prix total
- Option confection : "Faire confectionner ce tissu ?" (Oui / Non)
- Si oui → bouton [Choisir un tailleur]
- Sous-total
- CTA [Passer la commande]

Sur mobile :
- Chaque article affiché en bloc vertical clair
- Total toujours visible en bas (sticky)

### Étape 2 — Détails de confection (si applicable)

Affiché uniquement si le client veut une confection.

Contenu :
- Tailleur sélectionné (photo, nom, ville, tarif)
- Modèle sélectionné (photo ou "votre propre modèle")
- Formulaire de mesures :
  - Tour de poitrine
  - Tour de taille
  - Tour de hanches
  - Hauteur
  - Longueur souhaitée
  - Notes spéciales (champ texte libre)
- CTA [Continuer]

Sur mobile :
- Formulaire aéré, champs bien espacés
- Clavier numérique pour les mesures

### Étape 3 — Informations de livraison

Contenu :
- Prénom, Nom
- Téléphone
- Adresse de livraison (ville, quartier, rue ou description)
- Option : "Livraison à domicile" ou "Retrait chez le tailleur"
- CTA [Continuer]

Sur mobile :
- Formulaire court, champs essentiels uniquement

### Étape 4 — Récapitulatif et paiement

Contenu :
- Récapitulatif complet :
  - Articles commandés
  - Détails confection (si applicable)
  - Adresse de livraison
  - Mode de livraison
- Détail des prix :
  - Sous-total produits
  - Frais de confection (si applicable)
  - Frais de livraison
  - **Total en FCFA (en gros et visible)**
- Paiement PayTech (bouton de paiement sécurisé)
- Mention : "Paiement 100% sécurisé via PayTech"
- CTA [Payer maintenant]

Sur mobile :
- Total bien en vue avant le bouton de paiement
- Bouton paiement grand et accessible

### Page de confirmation

Après paiement réussi :
- Message de succès clair
- Numéro de commande
- Résumé de la commande
- Prochaines étapes (ex. "Votre tailleur a été notifié. Vous recevrez un SMS de confirmation.")
- CTA [Suivre ma commande] + [Retour à l'accueil]

## CTA principaux

| Étape | CTA principal |
|-------|--------------|
| Panier | Passer la commande |
| Confection | Continuer |
| Livraison | Continuer |
| Paiement | Payer maintenant |
| Confirmation | Suivre ma commande |

## Données affichées

- Photo produit, nom, prix, quantité
- Tailleur : nom, photo, ville
- Modèle : photo ou indication "votre modèle"
- Mesures : liste complète
- Adresse livraison
- Total FCFA détaillé

## Contraintes mobile

- Étapes clairement numérotées (barre de progression en haut)
- Un seul CTA principal par étape
- Pas de distraction (header minimal, footer masqué)
- Total toujours visible (sticky en bas si possible)

## Points à éviter

- Trop d'étapes (max 4)
- Informations demandées plusieurs fois
- Total caché ou peu visible avant paiement
- Manque de confirmation claire après paiement
- Redirection vers un site externe sans retour possible
- Formulaire de mesures avec trop de champs obligatoires
