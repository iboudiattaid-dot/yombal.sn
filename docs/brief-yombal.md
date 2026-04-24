# Brief Yombal.sn

## Présentation

Yombal.sn est une marketplace sénégalaise dédiée à la couture sur mesure, aux tailleurs, aux modèles de tenues sénégalaises et aux tissus africains.

## Marché cible

- Sénégal et diaspora africaine.
- Clients qui cherchent des tenues sur mesure sans intermédiaire compliqué.
- Tailleurs cherchant à digitaliser leur activité.
- Vendeurs de tissus cherchant un canal de vente en ligne.

## Proposition de valeur

Yombal.sn permet de :
1. Trouver un tailleur de confiance.
2. Choisir un modèle de tenue parmi un catalogue.
3. Acheter un tissu directement sur la plateforme.
4. Commander une confection sur mesure (tissu + modèle + tailleur).
5. Payer en ligne de manière sécurisée (PayTech).
6. Suivre l'avancement de sa commande.
7. Recevoir le produit livré.

## Positionnement

- Inspiré de la structure de ComeUp (marketplace de services).
- Adapté au marché sénégalais : prix en FCFA, mobile-first, logique de confiance locale.
- Différenciation : combinaison unique tissu + tailleur + modèle dans un seul parcours.

## Acteurs de la plateforme

| Acteur | Rôle |
|--------|------|
| Client | Commande, paie, suit sa commande |
| Tailleur | Reçoit les commandes de confection, livre |
| Vendeur tissu | Vend les tissus sur la plateforme |
| Administrateur | Gère la plateforme, valide les comptes partenaires |

## Stack technique actuelle

| Composant | Outil |
|-----------|-------|
| CMS | WordPress |
| E-commerce | WooCommerce |
| Page builder | Elementor |
| Marketplace | WCFM (à remplacer progressivement) |
| Paiement | PayTech (mode TEST) |
| Formulaires | Forminator |
| Cache | LiteSpeed Cache |
| SEO | Yoast SEO |
| Sécurité | Wordfence |
| Automatisation | Uncanny Automator |

## Contraintes actuelles

- PayTech uniquement en mode TEST — tunnel non encore validé en production.
- WCFM présent mais identifié comme dépendance à remplacer.
- Ne pas modifier la production WordPress directement.
- Aucune donnée sensible dans GitHub.
