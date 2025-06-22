# Makefile for AWE Academy Management System

# Variables
DOCKER_COMPOSE = docker-compose
DOCKER_PHP = $(DOCKER_COMPOSE) exec app
DOCKER_NODE = $(DOCKER_COMPOSE) exec node
DOCKER_DB = $(DOCKER_COMPOSE) exec db

# Colors
GREEN = \033[0;32m
NC = \033[0m # No Color

# Help
.PHONY: help
help:
	@echo "AWE Academy Management System Commands:"
	@echo "------------------------------------"
	@echo "${GREEN}Setup Commands:${NC}"
	@echo "  make setup         - Initial project setup"
	@echo "  make install      - Install dependencies"
	@echo "  make fresh        - Fresh installation with demo data"
	@echo ""
	@echo "${GREEN}Docker Commands:${NC}"
	@echo "  make up           - Start Docker containers"
	@echo "  make down         - Stop Docker containers"
	@echo "  make restart      - Restart Docker containers"
	@echo "  make logs         - View Docker logs"
	@echo ""
	@echo "${GREEN}Development Commands:${NC}"
	@echo "  make watch        - Watch for asset changes"
	@echo "  make build        - Build assets"
	@echo "  make test         - Run tests"
	@echo "  make lint         - Run code linting"
	@echo "  make format       - Format code"
	@echo ""
	@echo "${GREEN}Database Commands:${NC}"
	@echo "  make db-fresh     - Fresh database migration"
	@echo "  make db-seed      - Seed database with demo data"
	@echo "  make db-backup    - Create database backup"
	@echo "  make db-restore   - Restore database from backup"
	@echo ""
	@echo "${GREEN}Cache Commands:${NC}"
	@echo "  make cache-clear  - Clear application cache"
	@echo "  make config-clear - Clear config cache"
	@echo ""
	@echo "${GREEN}Maintenance Commands:${NC}"
	@echo "  make maintain     - Enable maintenance mode"
	@echo "  make unmaintain   - Disable maintenance mode"

# Setup commands
.PHONY: setup
setup: up install db-fresh build

.PHONY: install
install:
	$(DOCKER_PHP) composer install
	$(DOCKER_NODE) npm install

.PHONY: fresh
fresh: down up install db-fresh db-seed build

# Docker commands
.PHONY: up
up:
	$(DOCKER_COMPOSE) up -d

.PHONY: down
down:
	$(DOCKER_COMPOSE) down

.PHONY: restart
restart: down up

.PHONY: logs
logs:
	$(DOCKER_COMPOSE) logs -f

# Development commands
.PHONY: watch
watch:
	$(DOCKER_NODE) npm run watch

.PHONY: build
build:
	$(DOCKER_NODE) npm run production

.PHONY: test
test:
	$(DOCKER_PHP) ./vendor/bin/phpunit

.PHONY: lint
lint:
	$(DOCKER_PHP) ./vendor/bin/phpcs
	$(DOCKER_NODE) npm run lint

.PHONY: format
format:
	$(DOCKER_PHP) ./vendor/bin/phpcbf
	$(DOCKER_NODE) npm run format

# Database commands
.PHONY: db-fresh
db-fresh:
	$(DOCKER_DB) mysql -u root -p${DB_ROOT_PASSWORD} ${DB_DATABASE} < database/schema.sql

.PHONY: db-seed
db-seed:
	$(DOCKER_PHP) php database/seed.php

.PHONY: db-backup
db-backup:
	@mkdir -p backups
	$(DOCKER_DB) mysqldump -u root -p${DB_ROOT_PASSWORD} ${DB_DATABASE} > backups/backup-$(shell date +%Y%m%d-%H%M%S).sql

.PHONY: db-restore
db-restore:
	@echo "Available backups:"
	@ls -1 backups/*.sql
	@read -p "Enter backup file name: " file; \
	$(DOCKER_DB) mysql -u root -p${DB_ROOT_PASSWORD} ${DB_DATABASE} < backups/$$file

# Cache commands
.PHONY: cache-clear
cache-clear:
	$(DOCKER_PHP) rm -rf storage/cache/*
	$(DOCKER_PHP) rm -rf storage/views/*

.PHONY: config-clear
config-clear:
	$(DOCKER_PHP) rm -f storage/cache/config.php

# Maintenance commands
.PHONY: maintain
maintain:
	$(DOCKER_PHP) touch storage/framework/down

.PHONY: unmaintain
unmaintain:
	$(DOCKER_PHP) rm -f storage/framework/down

# Cleanup command
.PHONY: clean
clean:
	rm -rf vendor
	rm -rf node_modules
	rm -rf public/build
	rm -rf storage/cache/*
	rm -rf storage/logs/*
	rm -rf storage/views/*
