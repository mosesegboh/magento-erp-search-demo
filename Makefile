-include .env
export

.PHONY: init up down build install shell magento composer logs ps clean-cache reindex cron mock-erp-health mock-erp-products mock-erp-logs search-widget-install search-widget-build search-widget-build-docker

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

mock-erp-logs:
	docker compose logs -f mock-erp-api

ps:
	docker compose ps

clean-cache:
	docker compose exec --user www-data php bin/magento cache:clean
	docker compose exec --user www-data php bin/magento cache:flush

reindex:
	docker compose exec --user www-data php bin/magento indexer:reindex

cron:
	docker compose exec --user www-data php bin/magento cron:run

mock-erp-health:
	curl -fsS http://localhost:$(MOCK_ERP_API_HOST_PORT)/health

mock-erp-products:
	curl -fsS -H "Authorization: Bearer $(MOCK_ERP_API_TOKEN)" "http://localhost:$(MOCK_ERP_API_HOST_PORT)/api/products/updates?page=1&pageSize=5"

search-widget-install:
	cd src/app/code/Portfolio/SearchEnhancer/frontend && npm install

search-widget-build:
	cd src/app/code/Portfolio/SearchEnhancer/frontend && npm run build

search-widget-build-docker:
	docker run --rm \
		--user "$$(id -u):$$(id -g)" \
		-v "$(PWD)":/app \
		-w /app/src/app/code/Portfolio/SearchEnhancer/frontend \
		node:22-alpine npm run build
