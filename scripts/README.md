# Scripts

Ce dossier contient les scripts utilitaires pour le projet Yombal.sn.

## Usage prévu

- Scripts de déploiement (staging → production)
- Scripts de migration de données ciblés (SQL)
- Scripts de nettoyage de base de données
- Scripts de synchronisation d'environnement
- Scripts de vérification de santé du site

## Règles

- Ne jamais stocker de credentials dans les scripts.
- Utiliser des variables d'environnement pour les identifiants.
- Documenter chaque script avec un commentaire d'en-tête.
- Tester en local avant d'exécuter en production.

## Exemple de structure à venir

```
scripts/
├── deploy-staging.sh
├── sync-db-staging.sh
├── clean-cache.sh
└── health-check.sh
```
