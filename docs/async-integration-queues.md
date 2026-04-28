# Async Integration Queues

This feature moves long-running integration work behind Magento message queues backed by RabbitMQ. It keeps the existing synchronous CLI commands for support/debugging, while adding queue publishers and consumers for production-style processing.

## Queue Topics And Consumers

```text
Topic:    portfolio.erp.product_sync
Queue:    portfolio.erp.product_sync
Consumer: portfolio.erp.product_sync

Topic:    portfolio.order.export
Queue:    portfolio.order.export
Consumer: portfolio.order.export
```

## Install Or Upgrade

```bash
docker compose exec --user www-data php bin/magento setup:upgrade
docker compose exec --user www-data php bin/magento cache:flush
```

If generated code is stale:

```bash
docker compose exec --user www-data php rm -rf generated/code/Portfolio generated/metadata/*
docker compose exec --user www-data php bin/magento setup:di:compile
docker compose exec --user www-data php bin/magento cache:flush
```

## Verify Consumers

```bash
docker compose exec --user www-data php bin/magento queue:consumers:list | grep portfolio
```

Expected consumers:

```text
portfolio.erp.product_sync
portfolio.order.export
```

## Queue ERP Product Sync

Publish a dry-run sync message:

```bash
docker compose exec --user www-data php bin/magento portfolio:erp:sync-products:enqueue --dry-run
```

Consume one message:

```bash
docker compose exec --user www-data php bin/magento queue:consumers:start portfolio.erp.product_sync --max-messages=1
```

The ERP sync admin grid should move the log from `queued` to `success` or `failed`:

```text
Portfolio > ERP Sync Logs
```

## Queue Order Export

Publish an order export message:

```bash
docker compose exec --user www-data php bin/magento portfolio:order-export:enqueue 000000001 --force
```

Consume one message:

```bash
docker compose exec --user www-data php bin/magento queue:consumers:start portfolio.order.export --max-messages=1
```

The order export admin grid should move the log from `queued` to `success` or `failed`:

```text
Portfolio > Order Export Logs
```

## Automatic Order Export

Order exports are asynchronous by default.

Admin path:

```text
Stores > Configuration > Portfolio Integrations > Order Export > Export New Orders Asynchronously
```

When enabled, storefront/admin order placement publishes a queue message. The consumer performs the external ERP API call outside the checkout/admin request.

## RabbitMQ UI

RabbitMQ management UI:

```text
http://localhost:15672
```

Default local credentials:

```text
magento / magento
```

## Senior-Level Talking Points

- Checkout and scheduled sync flows no longer block on external ERP latency.
- Queue messages are typed through Magento `communication.xml` service method schemas.
- Publishers create operational log rows before publishing, so queued work is visible in admin.
- Consumers set the Magento area code, call the existing domain services, and rethrow failures so queue processing marks failed messages correctly.
- The original synchronous CLI commands remain available for support, debugging, and forced retries.
- RabbitMQ is already part of the Docker stack, matching the production integration architecture.
