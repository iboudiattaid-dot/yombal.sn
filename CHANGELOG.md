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

## 2026-04-24 — YOMBAL-PAYTECH-01

- Audit complet du plugin PayTech v6.0.3 → rapport dans `docs/paytech-audit.md`.
- Correction critique : activation SSL (`CURLOPT_SSL_VERIFYPEER=1`, `CURLOPT_SSL_VERIFYHOST=2`).
- Correction critique : logique email IPN corrigée (emails admin et client maintenant envoyés).
- Correction critique : vérification du statut commande sur `?_success=1` avant redirection.
- Correction : typo clé session `paytech_wc_oder_id` → `paytech_wc_order_id`.
- Correction : propriétés WooCommerce dépréciées remplacées (`$order->id` → `$order->get_id()`, etc.).
- Plugin PayTech corrigé exporté vers `src/plugins/paytech_woocommerce/`.

## 2026-04-24 — YOMBAL-STAGING-01

- Installation Ubuntu WSL2 pour Docker Desktop Linux engine.
- Documentation staging dans `docs/staging-environment.md`.
- Ajout `.env.example` avec variable `WP_ADMIN_PASSWORD`.
- Ajout `scripts/Makefile` (corrigé : `db-shell` utilise les variables `.env`).
- Correction `.gitignore` : `.env.example` désormais versionné, `.env` exclu.
