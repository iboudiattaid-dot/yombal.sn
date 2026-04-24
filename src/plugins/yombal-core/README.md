# Yombal Core

Socle custom de migration progressive pour Yombal.

## Objectif

Ce plugin pose la base du remplacement progressif de WCFM sans casser le site existant.

Principes:
- WooCommerce reste le moteur catalogue / panier / commande / paiement
- WCFM n'est pas desactive par ce plugin
- la nouvelle logique Yombal est introduite progressivement
- les nouvelles structures de donnees sont separees, explicites et versionnables

## Ce que contient cette premiere base

- installation des tables custom Yombal:
  - `wp_yombal_partner_profiles`
  - `wp_yombal_mesures`
  - `wp_yombal_couture_requests`
  - `wp_yombal_couture_request_events`
  - `wp_yombal_notifications`
- roles partenaires Yombal:
  - `yombal_tailor`
  - `yombal_fabric_vendor`
  - `yombal_hybrid_partner`
  - `yombal_ops_manager`
- menu admin `Yombal Ops`
- provisionneur de pages custom depuis `Yombal Ops > Pages custom`
- import manuel des vendeurs `WCFM` vers les profils partenaires custom
- shortcode partenaire `[yombal_partner_dashboard]`
- shortcode portail partenaire `[yombal_partner_workspace]`
- shortcodes candidature partenaire:
  - `[yombal_partner_registration]`
  - `[yombal_partner_application]`
- shortcodes mesures:
  - `[yombal_measurements]`
  - `[yombal_mes_mesures]` pour compatibilite
- shortcode client couture:
  - `[yombal_couture_request_form]`
- shortcode couturier:
  - `[yombal_tailor_requests]`
- shortcode notifications:
  - `[yombal_notifications]`
- base persistante du workflow `tissu + couture`
- garde-fou checkout si une demande couture en session n'est pas encore validee
- notifications internes liees aux demandes couture et candidatures partenaires
- transaction groupee WooCommerce:
  - ajout du montant couture dans le total checkout
  - liaison demande couture <-> commande WooCommerce
  - marquage apres paiement confirme
- bascule progressive:
  - liens custom injectes dans `Mon compte`
  - entree vers l etape couture depuis le panier
  - redirections legacy activables depuis `Yombal Ops > Bascule progressive`

## Ce que ce plugin ne fait pas encore

- import automatique des vendeurs WCFM vers les profils partenaires custom
- mapping automatique complet des boutiques et reglages WCFM
- workflow complet de moderation partenaire
- pieces jointes multiples / galerie produit cote partenaire
- messagerie temps reel custom
- page de paiement alternative pour reprise d une demande deja liee a une commande non reglee

## Ordre de migration recommande

1. profils partenaires custom
2. dashboard partenaire custom
3. edition produit partenaire custom
4. messages / litiges custom
5. workflow tissu + couture
6. retrait progressif des ecrans WCFM

## Notes de securite

- ne pas desactiver WCFM tant que les parcours critiques ne sont pas couverts
- ne pas activer simultanement plusieurs logiques concurrentes de blocage checkout sans recette
- conserver un export des metas WCFM avant migration des profils et boutiques
