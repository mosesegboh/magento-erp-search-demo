# Magento Product Discovery + ERP Integration Platform

This repository is prepared for a Magento 2 portfolio project using Magento Open Source `2.4.8-p4`, Docker, OpenSearch, RabbitMQ, Redis, MySQL, and nginx.

## Current Status

Magento Open Source `2.4.8-p4` is installed through Docker with OpenSearch, RabbitMQ, Valkey, MySQL, MailHog, nginx, PHP-FPM, and a local mock ERP/PIM API.

## Prerequisites

- Docker Engine
- Docker Compose plugin
- Magento Marketplace authentication keys for `repo.magento.com`

Configure Composer authentication before running the Magento install:

```bash
docker compose exec --user www-data php composer config --global http-basic.repo.magento.com PUBLIC_KEY PRIVATE_KEY
```

## Setup

```bash
make init
make build
make up
make install
```

Magento will be available at:

```text
http://localhost:8080/
```

Admin defaults are defined in `.env.example`.

## Local Hostname

If you want to use the configured base URL, add this to your hosts file:

```text
127.0.0.1 magento.test
```

Then visit:

```text
http://magento.test:8080/
```

## Services

- Magento/PHP-FPM: `php:8.3-fpm`
- Web server: `nginx:1.28-alpine`
- Database: `mysql:8.4`
- Search: `opensearchproject/opensearch:3`
- Queue: `rabbitmq:4.1-management`
- Cache/session: `valkey/valkey:8-alpine`
- Mail testing: `mailhog/mailhog`
- Mock ERP/PIM API: `node:22-alpine`

## Useful Commands

```bash
make shell
make magento
make composer
make reindex
make clean-cache
make logs
make mock-erp-health
make mock-erp-products
```

## Magento Modules

Current custom modules:

```text
Portfolio_ErpSync
Portfolio_OrderExport
Portfolio_SearchEnhancer
```

Enable and run the ERP product sync module:

```bash
docker compose exec --user www-data php bin/magento module:enable Portfolio_ErpSync
docker compose exec --user www-data php bin/magento setup:upgrade
docker compose exec --user www-data php bin/magento portfolio:erp:sync-products --dry-run
```

See [docs/erp-sync-module.md](docs/erp-sync-module.md).

Enable and test the order export module:

```bash
docker compose exec --user www-data php bin/magento module:enable Portfolio_OrderExport
docker compose exec --user www-data php bin/magento setup:upgrade
docker compose exec --user www-data php bin/magento portfolio:order-export:export 100000001
```

See [docs/order-export-module.md](docs/order-export-module.md).

Enable and test the search enhancer module:

```bash
docker compose exec --user www-data php bin/magento module:enable Portfolio_SearchEnhancer
docker compose exec --user www-data php bin/magento setup:upgrade
make search-widget-install
make search-widget-build
docker compose exec --user www-data php bin/magento portfolio:search:opensearch-health
curl -i "http://localhost:8080/searchenhancer/suggest?q=backpack"
```

See [docs/search-enhancer-module.md](docs/search-enhancer-module.md).

Queue integration work through RabbitMQ:

```bash
docker compose exec --user www-data php bin/magento queue:consumers:list | grep portfolio
docker compose exec --user www-data php bin/magento portfolio:erp:sync-products:enqueue --dry-run
docker compose exec --user www-data php bin/magento queue:consumers:start portfolio.erp.product_sync --max-messages=1
docker compose exec --user www-data php bin/magento portfolio:order-export:enqueue 000000001 --force
docker compose exec --user www-data php bin/magento queue:consumers:start portfolio.order.export --max-messages=1
```

See [docs/async-integration-queues.md](docs/async-integration-queues.md).

View integration logs in Magento admin:

```text
Portfolio > ERP Sync Logs
Portfolio > Order Export Logs
Portfolio > Search Query Logs
```

See [docs/admin-integration-grids.md](docs/admin-integration-grids.md).

## CI Quality Checks

This repository includes GitHub Actions checks for custom PHP syntax, Magento XML configuration, mock ERP API JavaScript syntax, React widget build output, and Docker Compose configuration.

See [docs/ci-quality-checks.md](docs/ci-quality-checks.md).

## Mock ERP/PIM API

The mock ERP/PIM API is available locally at:

```text
http://localhost:3001
```

Magento containers should call it through the internal Docker URL:

```text
http://mock-erp-api:3001
```

Protected endpoints require:

```text
Authorization: Bearer dev-erp-token
```

Useful endpoints:

```text
GET  /health
GET  /docs
GET  /openapi.json
GET  /api/products/updates?page=1&pageSize=25
GET  /api/products/{sku}
GET  /api/inventory/{sku}
POST /api/orders
GET  /api/orders/{externalId}
```

The API supports pagination, bearer-token auth, idempotent order export, request IDs, artificial delay, and simulated `500`, `429`, and timeout responses. See [docs/mock-erp-api.md](docs/mock-erp-api.md).

If port `3001` is already in use on your machine, change only the host port in `.env`:

```env
MOCK_ERP_API_HOST_PORT=3002
```

Do not change `MOCK_ERP_API_URL`; Magento uses the internal Docker service URL.

## Next Build Phases

After Magento is installed:

1. Add retry/backoff policies and dead-letter queue documentation.
2. Add architecture diagrams and final presentation notes.
