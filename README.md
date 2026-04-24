# Yombal.sn

## Présentation

Yombal.sn est une marketplace sénégalaise de couture sur mesure, de tissus africains et de modèles de tenues. Le projet vise à connecter les clients, les tailleurs et les vendeurs de tissus dans un parcours simple, moderne et sécurisé.

## Objectifs

- Créer une expérience moderne inspirée des meilleures marketplaces.
- Permettre au client de choisir un tissu, un modèle et un tailleur.
- Permettre la commande de confection sur mesure.
- Centraliser le paiement sur le site.
- Préparer une architecture plus propre que WCFM à moyen terme.
- Garder PayTech en mode test tant que le tunnel n'est pas totalement validé.

## Stack

- WordPress
- WooCommerce
- Elementor
- PayTech en mode TEST
- GitHub pour le suivi du code
- Claude Code pour le développement
- Claude Design pour les maquettes et interfaces

## Structure du dépôt

```
/README.md              — Ce fichier
/AGENTS.md              — Règles pour agents IA
/MISSION.md             — Mission en cours
/CHANGELOG.md           — Historique des changements

/docs/                  — Documentation technique et métier
/claude-design/         — Specs visuelles pour Claude Design
/src/                   — Code source (thème, plugins, mu-plugins)
/exports/               — Exports WordPress, Elementor, WooCommerce
/assets-reference/      — Références visuelles (logos, couleurs, screenshots)
/scripts/               — Scripts utilitaires
```

## Règle critique

Ne jamais modifier directement la production sans sauvegarde, sans test et sans documentation.

## Contacts & ressources

- Site : https://yombal.sn
- Dépôt : https://github.com/iboudiattaid-dot/yombal.sn
