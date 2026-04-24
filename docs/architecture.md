# Architecture technique — Yombal.sn

## Vue d'ensemble

```
Client (navigateur)
    │
    ▼
WordPress + WooCommerce (production)
    │
    ├── Elementor (page builder / UI)
    ├── WCFM (gestion multi-vendeurs — à remplacer)
    ├── WooCommerce (catalogue, panier, commandes)
    ├── PayTech (paiement — mode TEST)
    ├── Forminator (formulaires personnalisés)
    └── Plugins personnalisés Yombal (src/plugins/)
```

## Flux de commande actuel

1. Client navigue sur le catalogue (tissus / tailleurs / modèles).
2. Client ajoute un tissu au panier.
3. Client choisit une option de confection (oui/non).
4. Si oui → sélection tailleur + modèle + mesures.
5. Récapitulatif commande.
6. Paiement via PayTech.
7. Notification tailleur + vendeur tissu.
8. Suivi commande par le client.

## Thème

- Thème actuel : à documenter via export.
- Recommandation : utiliser un thème enfant pour toutes modifications CSS/PHP.
- Chemin cible : `src/theme/`

## Plugins clés

| Plugin | Rôle | Statut |
|--------|------|--------|
| woocommerce | Boutique e-commerce | Actif, core |
| wcfm | Marketplace multi-vendeurs | Actif, à remplacer |
| elementor | Page builder | Actif |
| paytech_woocommerce | Paiement PayTech | Actif, mode TEST |
| forminator | Formulaires | Actif |
| advanced-custom-fields | Champs personnalisés | Actif |
| uncanny-automator | Automatisation | Actif |
| litespeed-cache | Cache | Actif |
| wordfence | Sécurité | Actif |

## Base de données

- MySQL géré par WordPress.
- Ne jamais versionner la BDD complète dans GitHub.
- Pour les migrations de données : utiliser des scripts SQL ciblés dans `/scripts/`.

## Environnements

| Env | Statut | Notes |
|-----|--------|-------|
| Production | Actif | yombal.sn — ne pas toucher sans backup |
| Staging | À créer | Recommandé avant toute refonte |
| Local | Docker disponible | docker-compose.yml dans le projet |

## Priorités d'évolution architecture

1. Remplacer WCFM par une solution plus maîtrisée.
2. Centraliser la logique métier dans des mu-plugins (`src/mu-plugins/`).
3. Éviter Code Snippets pour le code critique.
4. Mettre en place un staging avant production.
