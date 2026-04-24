# Changelog

Toutes les modifications importantes du projet Yombal.sn sont documentées ici.
Format : [YYYY-MM-DD] — Code mission — Description

---

## 2026-04-24 — YOMBAL-GITHUB-DESIGN-SETUP-01

- Initialisation du dépôt GitHub yombal.sn.
- Création de la structure documentaire complète.
- Ajout du fichier .gitignore sécurisé pour WordPress.
- Ajout du README.md avec présentation du projet.
- Ajout du fichier AGENTS.md avec règles pour agents IA.
- Ajout du fichier MISSION.md avec suivi de la mission en cours.
- Création du dossier docs/ avec documentation technique et métier.
- Création du dossier claude-design/ avec brief et spécifications de pages.
- Création du dossier src/ (thème, plugins, mu-plugins).
- Création du dossier exports/ (wordpress, elementor, forminator, woocommerce).
- Création du dossier assets-reference/.
- Création du dossier scripts/.
- Premier commit et push sur la branche main.

## 2026-04-24 — YOMBAL-EXPORT-01

- Export du plugin personnalisé `yombal-core` vers `src/plugins/yombal-core/`.
- Export des 15 mu-plugins Yombal vers `src/mu-plugins/`.
- Correction sécurité : suppression du mot de passe fixture hardcodé dans `class-journey-lab.php` → remplacé par constante `YOMBAL_FIXTURE_PASSWORD` (à définir dans wp-config.php).
- Correction sécurité : suppression du mot de passe admin hardcodé dans `scripts/setup-wp.sh` → remplacé par variable d'environnement `WP_ADMIN_PASSWORD`.
- Export de l'inventaire des URLs WordPress vers `exports/wordpress/pages-urls-export.csv`.
- Export de tous les scripts utilitaires vers `scripts/`.
