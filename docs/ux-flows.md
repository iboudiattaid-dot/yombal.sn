# Flux UX — Yombal.sn

## Flux principal : commande de confection sur mesure

```
Homepage
    │
    ▼
[Choix entrée]
    ├── Je cherche un tailleur → Catalogue tailleurs
    ├── Je cherche un tissu   → Catalogue tissus
    └── Je cherche un modèle  → Catalogue modèles
    │
    ▼
Fiche tissu / Fiche tailleur / Fiche modèle
    │
    ▼
Ajout au panier
    │
    ▼
"Voulez-vous faire confectionner ce tissu ?"
    ├── NON → Achat tissu seul → Paiement → Livraison tissu
    └── OUI
        │
        ▼
    Choisir un tailleur
        │
        ▼
    Choisir un modèle (catalogue ou photo uploadée)
        │
        ▼
    Renseigner ses mesures
        │
        ▼
    Récapitulatif complet (tissu + tailleur + modèle + prix)
        │
        ▼
    Paiement PayTech
        │
        ▼
    Confirmation commande
        │
        ▼
    Suivi commande (espace client)
        │
        ▼
    Livraison
```

## Flux tailleur (partenaire)

```
Inscription tailleur
    │
    ▼
Validation admin
    │
    ▼
Activation compte partenaire
    │
    ▼
Profil tailleur (ville, spécialité, tarifs, portfolio)
    │
    ▼
Réception commandes
    │
    ▼
Mise à jour statut commande
    │
    ▼
Livraison + confirmation
```

## Flux vendeur tissu (partenaire)

```
Inscription vendeur
    │
    ▼
Validation admin
    │
    ▼
Création boutique (WCFM ou solution de remplacement)
    │
    ▼
Ajout produits tissus (photos, prix, stock)
    │
    ▼
Réception commandes tissu
    │
    ▼
Expédition tissu au tailleur ou au client
```

## Points de friction identifiés

- La sélection tailleur + modèle + mesures en une seule session doit rester courte.
- Le client ne doit pas avoir à créer un compte avant de voir les produits.
- Le prix total (tissu + confection) doit être visible avant paiement.
- Mobile-first : tous les flux doivent fonctionner sur smartphone.

## Pages à créer ou améliorer

| Page | Priorité |
|------|----------|
| Homepage | Haute |
| Catalogue tailleurs | Haute |
| Catalogue tissus | Haute |
| Catalogue modèles | Haute |
| Fiche tailleur | Haute |
| Fiche tissu | Haute |
| Fiche modèle | Haute |
| Tunnel de commande | Haute |
| Espace client | Moyenne |
| Espace partenaire | Moyenne |
| Page paiement | Haute |
| Confirmation commande | Haute |
