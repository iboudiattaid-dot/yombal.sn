# Environnement Staging Local — Yombal.sn

## Stack Docker

| Service | Image | Port local | Usage |
|---------|-------|------------|-------|
| WordPress | wordpress:6.7-php8.2-apache | 8080 | Site principal |
| MySQL | mysql:8.0 | 3306 | Base de données |
| phpMyAdmin | phpmyadmin:5 | 8081 | Interface BDD |
| MailHog | mailhog/mailhog | 8025 (UI), 1025 (SMTP) | Capture emails |
| WP-CLI | wordpress:cli-php8.2 | — | Commandes WP |

## Prérequis

- Docker Desktop installé et démarré
- Fichier `.env` configuré à partir de `.env.example`
- Variables obligatoires dans `.env` :
  - `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`
  - `WP_ADMIN_PASSWORD` (requis par `scripts/setup-wp.sh`)

## Démarrage

```bash
# 1. Copier et remplir le .env
cp .env.example .env
# Remplir WP_ADMIN_PASSWORD et autres valeurs

# 2. Démarrer les containers
docker compose up -d

# 3. Attendre ~15 secondes que WordPress et MySQL soient prêts

# 4. Initialiser WordPress via WP-CLI
bash scripts/setup-wp.sh

# 5. Accéder au site
# WordPress : http://localhost:8080
# Admin WP  : http://localhost:8080/wp-admin
# phpMyAdmin: http://localhost:8081
# MailHog   : http://localhost:8025
```

## Makefile

Des raccourcis sont disponibles via le Makefile :

```bash
make up        # docker compose up -d
make down      # docker compose down
make logs      # docker compose logs -f wordpress
make wp        # docker compose exec wpcli wp --allow-root [commande]
make setup     # bash scripts/setup-wp.sh
```

## Volumes Docker

| Volume | Contenu |
|--------|---------|
| `db_data` | Données MySQL (persisté entre redémarrages) |
| `wp_uploads` | Fichiers médias WordPress |

## Montages en temps réel

Les dossiers suivants sont montés directement depuis le dépôt local :
- `wp-content/mu-plugins/` → modifications immédiatement actives
- `wp-content/plugins/` → modifications immédiatement actives
- `wp-content/themes/` → modifications immédiatement actives

## Réinitialisation complète

```bash
docker compose down -v    # supprime les volumes (repart de zéro)
docker compose up -d
bash scripts/setup-wp.sh
```

## Sécurité

- `.env` est exclu de Git par `.gitignore`.
- Ne jamais mettre les mots de passe de production dans `.env` local.
- Les credentials de prod (FTP, BDD) ne doivent être renseignés que si nécessaire, en local uniquement.

## Environnement virtualisé (VM)

Si ce serveur tourne déjà dans une VM (VPS OVH, AWS EC2, etc.), Docker Desktop ne peut pas démarrer son propre moteur virtuel (pas de virtualisation imbriquée).

**Solution : Docker Engine directement dans WSL2**

```bash
# Dans WSL2 Ubuntu
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y ca-certificates curl gnupg
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
echo 'deb [arch=amd64 signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu noble stable' > /etc/apt/sources.list.d/docker.list
apt-get update -qq
apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
service docker start
```

Puis configurer les paths pour que `docker compose` soit disponible depuis Windows/bash :

```bash
# Dans ~/.bashrc de WSL2
export DOCKER_HOST=unix:///var/run/docker.sock
```

## Troubleshooting

| Problème | Solution |
|----------|---------|
| WordPress inaccessible | `docker compose logs wordpress` — attendre MySQL |
| Erreur connexion BDD | Vérifier `DB_*` dans `.env` |
| Plugin non actif | `docker compose exec wpcli wp plugin activate nom-plugin --allow-root` |
| Email non reçu | Vérifier MailHog sur http://localhost:8025 |
| Port 8080 occupé | Changer `WP_PORT` dans `.env` |
