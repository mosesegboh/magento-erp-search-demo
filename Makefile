-include .env
export

.PHONY: init up down build install shell magento composer logs ps clean-cache reindex cron

init:
	cp -n .env.example .env
	chmod +x scripts/install-magento.sh
	@echo "Edit .env, then run: make build && make up && make install"

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

install:
	docker compose exec --user www-data php bash /var/www/scripts/install-magento.sh

shell:
	docker compose exec --user www-data php bash

magento:
	docker compose exec --user www-data php bin/magento

composer:
	docker compose exec --user www-data php composer

logs:
	docker compose logs -f

ps:
	docker compose ps

clean-cache:
	docker compose exec --user www-data php bin/magento cache:clean
	docker compose exec --user www-data php bin/magento cache:flush

reindex:
	docker compose exec --user www-data php bin/magento indexer:reindex

cron:
	docker compose exec --user www-data php bin/magento cron:run
