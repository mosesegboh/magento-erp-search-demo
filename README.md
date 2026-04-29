# Magento Product Discovery + ERP Integration Platform

This repository is prepared for a Magento 2 portfolio project using Magento Open Source `2.4.8-p4`, Docker, OpenSearch, RabbitMQ, Redis, MySQL, and nginx.

## Current Status

Magento Open Source `2.4.8-p4` is installed through Docker with OpenSearch, RabbitMQ, Valkey, MySQL, MailHog, nginx, PHP-FPM, and a local mock ERP/PIM API.

## Project Walkthrough

- [Architecture overview](docs/architecture.md)
- [Reviewer checklist](docs/reviewer-checklist.md)

## Prerequisites

- Docker Engine or Docker Desktop
- Docker Compose plugin
- Git
- Make
- Magento Marketplace authentication keys for `repo.magento.com`

Recommended Docker resources:

```text
CPUs: 4+
Memory: 8GB+
Disk: 20GB+ free
```

### macOS

Install and start Docker Desktop. If `git` or `make` are missing, install the Xcode command line tools:

```bash
xcode-select --install
```

### Linux

Install Docker Engine and the Docker Compose plugin using your distribution's package manager. On Ubuntu, also add your user to the `docker` group so Docker commands work without `sudo`:

```bash
sudo groupadd docker 2>/dev/null || true
sudo usermod -aG docker "$USER"
newgrp docker
docker run hello-world
```

Install `git`, `make`, and `curl` if they are missing:

```bash
sudo apt update
sudo apt install -y git make curl
```

### Windows

Use Windows 10/11 with WSL2. Install Docker Desktop, enable the WSL2 backend, and run the project from an Ubuntu WSL terminal, not from `cmd.exe` or PowerShell.

Inside WSL:

```bash
sudo apt update
sudo apt install -y git make curl
```

Clone the project inside the Linux filesystem, for example under `~/projects`, not under `/mnt/c`, for better Docker volume performance.

## How to Run the Project

For a fresh machine, clone the repository and create your local environment file:

```bash
git clone https://github.com/mosesegboh/magento-erp-search-demo.git
cd magento-erp-search-demo
cp .env.example .env
```

If you want to use the configured hostname, add it to your hosts file:

macOS/Linux:

```bash
sudo sh -c 'echo "127.0.0.1 magento.test" >> /etc/hosts'
```

Windows with WSL2:

```powershell
notepad C:\Windows\System32\drivers\etc\hosts
```

Add this line:

```text
127.0.0.1 magento.test
```

Build and start the Docker services:

```bash
make build
make up
docker compose ps
```

Configure Magento Marketplace credentials inside the PHP container before first install:

```bash
docker compose exec --user www-data php composer config --global http-basic.repo.magento.com PUBLIC_KEY PRIVATE_KEY
```

Install Magento:

```bash
make install
```

Magento will be available at:

```text
http://magento.test:8080/
```

You can also use:

```text
http://localhost:8080/
```

Admin defaults are defined in `.env.example`. The default admin path is:

```text
http://magento.test:8080/admin
```

For daily use after the first install, start the project with:

```bash
make up
docker compose ps
```

Stop the project with:

```bash
make down
```

Run a quick health check:

```bash
curl -fsS http://localhost:3010/health
curl -i "http://localhost:8080/searchenhancer/suggest?q=backpack"
docker compose exec --user www-data php bin/magento module:status Portfolio_ErpSync Portfolio_OrderExport Portfolio_SearchEnhancer
```

### Possible Errors and Solutions

#### MySQL port `3306` is already in use

Symptom:

```text
Bind for 0.0.0.0:3306 failed: port is already allocated
```

Cause: another local MySQL/MariaDB service is already using host port `3306`.

Solution: stop the local database service or change the host port mapping in `docker-compose.yml`. On Linux, you can identify the process with:

```bash
sudo lsof -i :3306
```

If the process is a local MySQL service and you do not need it running:

```bash
sudo systemctl stop mysql
```

Then restart the project:

```bash
make up
```

#### Storefront loads without CSS or assets use `http://magento.test/static` without `:8080`

Symptom: the page looks collapsed or unstyled, and the browser console shows static assets, fonts, or scripts loading from `http://magento.test/static/...` while the storefront is opened at `http://magento.test:8080/`.

Cause: Magento's stored base URL does not include the exposed Docker port. The page origin becomes `http://magento.test:8080`, but generated static asset URLs point to `http://magento.test`, which is a different origin.

Solution: update Magento's base URLs to include `:8080`, clear static generated files, and flush cache:

```bash
docker compose exec --user www-data php bin/magento config:set web/unsecure/base_url "http://magento.test:8080/"
docker compose exec --user www-data php bin/magento config:set web/secure/base_url "http://magento.test:8080/"
docker compose exec --user www-data php bin/magento config:set web/unsecure/base_static_url ""
docker compose exec --user www-data php bin/magento config:set web/secure/base_static_url ""
docker compose exec --user www-data php bin/magento config:set web/unsecure/base_media_url ""
docker compose exec --user www-data php bin/magento config:set web/secure/base_media_url ""
docker compose exec --user www-data php rm -rf pub/static/frontend pub/static/_cache var/view_preprocessed
docker compose exec --user www-data php bin/magento cache:flush
```

Open:

```text
http://magento.test:8080/
```

The CSP messages in the browser console are report-only warnings in local development. They are not the root cause of the missing CSS in this case.

#### Admin asks for Two-Factor Authentication during local testing

Symptom: after logging into the Magento admin, you see:

```text
You need to configure Two-Factor Authorization in order to proceed to your store's admin area
```

Cause: Magento enables admin 2FA modules by default. For this local portfolio/demo project, 2FA can be disabled to make reviewer testing easier.

Solution:

```bash
docker compose exec --user www-data php bin/magento module:disable Magento_AdminAdobeImsTwoFactorAuth Magento_TwoFactorAuth
docker compose exec --user www-data php bin/magento setup:upgrade
docker compose exec --user www-data php bin/magento cache:flush
```

If the admin page was already open, log out, clear the browser tab, and log in again.

#### ERP sync says it processed products, but products are not visible in admin

Symptom:

```text
ERP product sync completed. Items: 8. Pages: 1. Dry run: yes.
```

Cause: the command was run with `--dry-run`. Dry run validates the ERP API connection, payload parsing, and sync logging, but it does not create or update Magento products.

Solution: run the command without `--dry-run`, then reindex and flush cache:

```bash
docker compose exec --user www-data php bin/magento portfolio:erp:sync-products
docker compose exec --user www-data php bin/magento indexer:reindex
docker compose exec --user www-data php bin/magento cache:flush
```

Verify imported products in admin:

```text
Catalog > Products
```

#### Search suggest endpoint returns `302 Found`

Symptom:

```text
HTTP/1.1 302 Found
Location: http://magento.test:8080/searchenhancer/suggest
```

Cause: Magento is configured with `http://magento.test:8080/` as the base URL, but the request was made through `http://localhost:8080`. Magento redirects to the configured host before the controller returns JSON.

Solution: call the endpoint through the configured base URL:

```bash
curl -i "http://magento.test:8080/searchenhancer/suggest?q=backpack"
```

Expected result:

```text
HTTP/1.1 200 OK
```

The response body should contain `items` and `meta`.

#### Enhanced search returns no products

Symptom: the enhanced search widget or suggest endpoint returns no matching products after a fresh install.

Cause: products may not have been imported yet, or the catalog search index has not been rebuilt after import.

Solution:

```bash
docker compose exec --user www-data php bin/magento portfolio:erp:sync-products
docker compose exec --user www-data php bin/magento indexer:reindex catalogsearch_fulltext
docker compose exec --user www-data php bin/magento cache:flush
curl -i "http://magento.test:8080/searchenhancer/suggest?q=backpack"
```

Expected result: HTTP `200 OK` with matching product suggestions.

#### Suggest endpoint returns products, but the storefront widget still says no results

Symptom: this command returns products:

```bash
curl -i "http://magento.test:8080/searchenhancer/suggest?q=backpack"
```

But the storefront widget shows:

```text
No matching products found.
```

Cause: the browser may still be using an old page state, an earlier zero-result widget cache, or a different host/query than the one tested from the terminal.

Solution:

1. Open the storefront through the configured base URL:

```text
http://magento.test:8080/
```

2. Hard refresh the browser:

```text
macOS: Cmd + Shift + R
Windows/Linux: Ctrl + Shift + R
```

3. In browser DevTools, open the Network tab, filter by `suggest`, and confirm the widget request is:

```text
GET http://magento.test:8080/searchenhancer/suggest?q=backpack
```

4. Expected response: HTTP `200 OK` with a non-empty `items` array.

## Local Hostname

The default base URL is configured as `http://magento.test:8080/`. If the hostname does not resolve, confirm this entry exists in your hosts file:

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
docker compose exec --user www-data php bin/magento portfolio:erp:sync-products
docker compose exec --user www-data php bin/magento indexer:reindex
docker compose exec --user www-data php bin/magento cache:flush
```

Use `--dry-run` to validate the integration without writing products. Run the command without `--dry-run` when you want the mock ERP/PIM products to appear in `Catalog > Products`.

See [docs/erp-sync-module.md](docs/erp-sync-module.md).

Enable and test the order export module:

```bash
docker compose exec --user www-data php bin/magento module:enable Portfolio_OrderExport
docker compose exec --user www-data php bin/magento setup:upgrade
docker compose exec --user www-data php bin/magento portfolio:order-export:export 100000001
```

Use a real order increment ID from your store. On a fresh install, create a test order in admin first from `Sales > Orders > Create New Order`, then use that order's increment ID in the export command.

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
docker compose exec --user www-data php bin/magento portfolio:erp:sync-products:retry-due
docker compose exec --user www-data php bin/magento portfolio:order-export:enqueue 000000001 --force
docker compose exec --user www-data php bin/magento queue:consumers:start portfolio.order.export --max-messages=1
docker compose exec --user www-data php bin/magento portfolio:order-export:retry-due
```

See [docs/async-integration-queues.md](docs/async-integration-queues.md).

Retry/backoff and dead-letter handling is documented in [docs/retry-backoff-dlq.md](docs/retry-backoff-dlq.md).

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

1. Add automated PHPUnit integration tests.
2. Add production deployment notes.
