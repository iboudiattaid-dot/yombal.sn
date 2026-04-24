# ─── Yombal Dev — Makefile ─────────────────────────────────────
# Usage : make <commande>
# Prérequis : Docker Desktop installé et en cours d'exécution

.PHONY: up down restart logs shell db-shell setup pull-prod push-mu pull-mu db-pull status clean help

# Démarrer tous les services
up:
	docker compose up -d
	@echo ""
	@echo "✅ Environnement démarré :"
	@echo "   🌐 WordPress  → http://localhost:8080"
	@echo "   🗄️  phpMyAdmin → http://localhost:8081"
	@echo "   📬 MailHog    → http://localhost:8025"

# Arrêter les services (données conservées)
down:
	docker compose down

# Redémarrer
restart:
	docker compose restart

# Voir les logs en temps réel
logs:
	docker compose logs -f wordpress

# Ouvrir un shell dans le container WordPress
shell:
	docker compose exec wordpress bash

# Ouvrir un shell MySQL
db-shell:
	docker compose exec db mysql -u $${DB_USER} -p$${DB_PASSWORD} $${DB_NAME}

# Configurer WordPress local après premier démarrage
setup:
	@bash scripts/setup-wp.sh

# Importer la BDD de production (avec remplacement des URLs)
db-pull:
	@bash scripts/db-pull-prod.sh

# Récupérer les mu-plugins depuis la prod
pull-mu:
	@bash scripts/pull-prod.sh

# Envoyer les mu-plugins locaux vers la prod
push-mu:
	@bash scripts/push-mu-plugins.sh

# Envoyer un seul mu-plugin : make push-mu-file FILE=yombal-tissu.php
push-mu-file:
	@bash scripts/push-mu-plugins.sh $(FILE)

# Statut des containers
status:
	docker compose ps

# Nettoyage complet (⚠️ supprime les volumes)
clean:
	@echo "⚠️  Cela va supprimer tous les volumes (BDD + uploads). Continuer ? [y/N]"
	@read ans && [ "$$ans" = "y" ] && docker compose down -v || echo "Annulé."

# Commandes WP-CLI rapides
wp:
	docker compose run --rm wpcli wp --allow-root $(filter-out $@,$(MAKECMDGOALS))

# Aide
help:
	@echo ""
	@echo "  Yombal Dev — Commandes disponibles"
	@echo "  ────────────────────────────────────"
	@echo "  make up          Démarrer l'environnement Docker"
	@echo "  make down        Arrêter les containers"
	@echo "  make restart     Redémarrer"
	@echo "  make logs        Voir les logs WordPress"
	@echo "  make shell       Shell dans WordPress"
	@echo "  make db-shell    Shell MySQL"
	@echo "  make setup       Configurer WP après premier démarrage"
	@echo "  make db-pull     Importer la BDD de production"
	@echo "  make pull-mu     Récupérer mu-plugins depuis prod"
	@echo "  make push-mu     Envoyer tous les mu-plugins vers prod"
	@echo "  make push-mu-file FILE=yombal-tissu.php   Envoyer un seul fichier"
	@echo "  make status      État des containers"
	@echo "  make clean       Tout supprimer (volumes inclus)"
	@echo ""
