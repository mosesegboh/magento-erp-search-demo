# ERP Sync Module

`Portfolio_ErpSync` is the Magento-side integration module for the mock ERP/PIM API. It demonstrates a production-style integration boundary: admin configuration, authenticated API calls, paginated product import, stock updates, product metadata attributes, cron support, and persistent sync logs.

## Configuration

Admin path:

```text
Stores > Configuration > Portfolio Integrations > ERP Sync
```

Default Docker values:

```text
Base URL: http://mock-erp-api:3001
Token: dev-erp-token
Page size: 25
```

## Install Or Upgrade

```bash
docker compose exec --user www-data php bin/magento module:enable Portfolio_ErpSync
docker compose exec --user www-data php bin/magento setup:upgrade
docker compose exec --user www-data php bin/magento cache:flush
```

## Manual Product Sync

Dry run:

```bash
docker compose exec --user www-data php bin/magento portfolio:erp:sync-products --dry-run
```

Import products:

```bash
docker compose exec --user www-data php bin/magento portfolio:erp:sync-products
```

Import products changed after a timestamp:

```bash
docker compose exec --user www-data php bin/magento portfolio:erp:sync-products --since=2026-04-24T00:00:00Z
```

## Data Written

The module creates:

```text
portfolio_erp_sync_log
```

The module also adds product attributes:

```text
erp_brand
erp_source_updated_at
```

## Senior-Level Talking Points

- The external API boundary is isolated behind `ErpClientInterface`.
- API credentials are configurable from admin and stored through Magento's encrypted config backend.
- Product sync is paginated and logs success/failure state for operational visibility.
- The CLI command supports dry runs for safer testing.
- Cron support is present but disabled by default to avoid unexpected imports in local demos.
