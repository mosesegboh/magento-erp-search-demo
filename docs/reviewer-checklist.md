# Reviewer Checklist

This checklist helps reviewers validate the project quickly without reading every file first.

## Environment

```bash
docker info
docker compose version
cp .env.example .env
docker compose config --quiet
```

Expected result: Docker and Compose are available, and Compose configuration validates.

## Startup

```bash
make build
make up
docker compose ps
```

Expected services:

```text
nginx
php
mysql
opensearch
rabbitmq
redis
mailhog
mock-erp-api
```

## Magento Install

Magento Marketplace credentials are required for first install:

```bash
docker compose exec --user www-data php composer config --global http-basic.repo.magento.com PUBLIC_KEY PRIVATE_KEY
make install
```

Expected result:

```text
Magento installation complete.
Magento Admin URI: /admin
```

## Module Health

```bash
docker compose exec --user www-data php bin/magento module:status Portfolio_ErpSync
docker compose exec --user www-data php bin/magento module:status Portfolio_OrderExport
docker compose exec --user www-data php bin/magento module:status Portfolio_SearchEnhancer
docker compose exec --user www-data php bin/magento queue:consumers:list | grep portfolio
docker compose exec --user www-data php bin/magento list | grep portfolio:
```

Expected result: custom modules are enabled, queue consumers are listed, and portfolio CLI commands are registered.

## Mock ERP API

```bash
curl -fsS http://localhost:3001/health
curl -fsS -H "Authorization: Bearer dev-erp-token" "http://localhost:3001/api/products/updates?page=1&pageSize=2"
```

Expected result: health returns OK and product updates return JSON data.

## ERP Product Sync

```bash
docker compose exec --user www-data php bin/magento portfolio:erp:sync-products --dry-run
docker compose exec --user www-data php bin/magento portfolio:erp:sync-products
docker compose exec --user www-data php bin/magento indexer:reindex
docker compose exec --user www-data php bin/magento cache:flush
docker compose exec --user www-data php bin/magento portfolio:erp:sync-products:enqueue --dry-run
docker compose exec --user www-data php bin/magento queue:consumers:start portfolio.erp.product_sync --max-messages=1 --area-code=adminhtml
```

Expected result: `--dry-run` validates the integration without writing products. The command without `--dry-run` imports products that can be viewed in `Catalog > Products`.

Verify:

```bash
docker compose exec mysql mysql -umagento -pmagento magento -e "SELECT log_id, status, attempts, next_retry_at, message FROM portfolio_erp_sync_log ORDER BY log_id DESC LIMIT 5;"
```

Expected result: latest log is `success`.

## Order Export

Use a real order increment ID from your store:

On a fresh install, create a test order in admin first:

```text
Sales > Orders > Create New Order
```

Then use the created order's increment ID in the export commands.

```bash
docker compose exec --user www-data php bin/magento portfolio:order-export:enqueue 000000001 --force
docker compose exec --user www-data php bin/magento queue:consumers:start portfolio.order.export --max-messages=1 --area-code=adminhtml
```

Verify:

```bash
docker compose exec mysql mysql -umagento -pmagento magento -e "SELECT log_id, increment_id, status, external_id, attempts, next_retry_at, message FROM portfolio_order_export_log ORDER BY log_id DESC LIMIT 5;"
```

Expected result: latest log is `success` with an ERP external ID.

## Retry Flow

Set retry delay to zero for a fast local test:

```bash
docker compose exec --user www-data php bin/magento config:set portfolio_erp_sync/retry/base_delay_seconds 0
docker compose exec --user www-data php bin/magento config:set portfolio_erp_sync/api/token wrong-token
docker compose exec --user www-data php bin/magento cache:flush
```

Queue and consume one failing sync:

```bash
docker compose exec --user www-data php bin/magento portfolio:erp:sync-products:enqueue --dry-run
docker compose exec --user www-data php bin/magento queue:consumers:start portfolio.erp.product_sync --max-messages=1 --area-code=adminhtml
```

Expected result: latest ERP sync log becomes `retry_scheduled`.

Restore token and process retry:

```bash
docker compose exec --user www-data php bin/magento config:set portfolio_erp_sync/api/token dev-erp-token
docker compose exec --user www-data php bin/magento cache:flush
docker compose exec --user www-data php bin/magento portfolio:erp:sync-products:retry-due
docker compose exec --user www-data php bin/magento queue:consumers:start portfolio.erp.product_sync --max-messages=1 --area-code=adminhtml
docker compose exec --user www-data php bin/magento config:set portfolio_erp_sync/retry/base_delay_seconds 60
docker compose exec --user www-data php bin/magento cache:flush
```

Expected result: same log moves to `success` with a higher attempt count.

## Search Enhancer

```bash
curl -i "http://localhost:8080/searchenhancer/suggest?q=backpack"
```

Expected result: HTTP 200 with `items` and `meta`.

Open the storefront and test the enhanced product search widget in the header.

## Admin Observability

Open:

```text
Portfolio > ERP Sync Logs
Portfolio > Order Export Logs
Portfolio > Search Query Logs
```

Expected result: grids load with sorting, filtering, pagination, and integration statuses.

## CI Checks

```bash
composer validate --working-dir=src --no-check-lock --no-check-publish
bash scripts/ci/lint-php.sh
python3 scripts/ci/validate-xml.py
bash scripts/ci/check-mock-api.sh
make search-widget-build-docker
docker compose --env-file .env.example config --quiet
git diff --check
```

Expected result: all checks pass and frontend assets are unchanged after build.
