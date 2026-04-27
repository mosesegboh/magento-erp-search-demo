# Order Export Module

`Portfolio_OrderExport` exports Magento orders to the mock ERP API. It demonstrates Magento order events, payload mapping, idempotent API calls, persistent export status, retry-safe command-line operations, and admin-configurable integration settings.

## Configuration

Admin path:

```text
Stores > Configuration > Portfolio Integrations > Order Export
```

Default Docker values:

```text
Base URL: http://mock-erp-api:3001
Token: dev-erp-token
```

## Install Or Upgrade

```bash
docker compose exec --user www-data php bin/magento module:enable Portfolio_OrderExport
docker compose exec --user www-data php bin/magento setup:upgrade
docker compose exec --user www-data php bin/magento cache:flush
```

If generated code is stale:

```bash
docker compose exec --user www-data php rm -rf generated/code/Portfolio/OrderExport generated/metadata/*
docker compose exec --user www-data php bin/magento setup:di:compile
```

## Automatic Export

The module observes:

```text
checkout_submit_all_after
sales_order_place_after
```

When an order is placed, the module builds an ERP payload and posts it to:

```text
POST /api/orders
```

The request includes an `Idempotency-Key` header so retries do not create duplicate external orders.

## Manual Export Or Retry

Export by increment ID:

```bash
docker compose exec --user www-data php bin/magento portfolio:order-export:export 100000001
```

Force retry even if already exported:

```bash
docker compose exec --user www-data php bin/magento portfolio:order-export:export 100000001 --force
```

## Data Written

The module creates:

```text
portfolio_order_export_log
```

Useful check:

```bash
docker compose exec mysql mysql -umagento -pmagento magento -e "SELECT log_id, increment_id, status, external_id, attempts, message FROM portfolio_order_export_log ORDER BY log_id DESC LIMIT 10;"
```

## Senior-Level Talking Points

- Order export is idempotent and tracks attempts, request payload, response payload, and external ERP ID.
- API access is isolated behind `OrderExportClientInterface`.
- Manual CLI export enables support and retry workflows.
- Observers catch both storefront checkout and admin-created orders.
- The first version exports synchronously; the next production hardening step is moving export execution behind RabbitMQ consumers.
