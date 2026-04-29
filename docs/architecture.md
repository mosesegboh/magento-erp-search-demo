# Architecture Overview

This project is a Magento Open Source portfolio implementation focused on product discovery, ERP/PIM integration, asynchronous processing, observability, and local production-like infrastructure.

## System Diagram

```mermaid
flowchart LR
    Browser[Storefront browser]
    Admin[Magento admin]
    Nginx[Nginx]
    Magento[Magento 2.4.8-p4 PHP-FPM]
    MySQL[(MySQL 8.4)]
    Redis[(Valkey cache/session)]
    OpenSearch[(OpenSearch 3)]
    RabbitMQ[(RabbitMQ)]
    MailHog[MailHog]
    MockERP[Mock ERP/PIM API]

    Browser --> Nginx
    Admin --> Nginx
    Nginx --> Magento
    Magento --> MySQL
    Magento --> Redis
    Magento --> OpenSearch
    Magento --> RabbitMQ
    Magento --> MailHog
    Magento --> MockERP
    RabbitMQ --> Magento
```

## Custom Modules

```text
Portfolio_ErpSync
Portfolio_OrderExport
Portfolio_SearchEnhancer
```

`Portfolio_ErpSync` imports product and stock updates from the mock ERP/PIM API. It supports synchronous CLI execution, scheduled queue publishing, RabbitMQ consumers, retry scheduling, and admin log visibility.

`Portfolio_OrderExport` exports Magento orders to the mock ERP API. It uses idempotency keys, queue publishing from order placement, RabbitMQ consumers, retry scheduling, and admin export logs.

`Portfolio_SearchEnhancer` adds a React autocomplete widget backed by a Magento JSON endpoint and OpenSearch-powered product collections. It tracks zero-result queries for merchandising insight.

## Product Sync Flow

```mermaid
sequenceDiagram
    participant CLI as CLI/Cron
    participant Publisher as ProductSyncPublisher
    participant MQ as RabbitMQ
    participant Consumer as ProductSyncConsumer
    participant Service as ProductSyncService
    participant ERP as Mock ERP/PIM API
    participant Catalog as Magento Catalog
    participant Log as ERP Sync Log

    CLI->>Publisher: enqueue sync request
    Publisher->>Log: create queued log
    Publisher->>MQ: publish portfolio.erp.product_sync
    Consumer->>Service: process message
    Service->>Log: mark running
    Service->>ERP: fetch paginated updates
    Service->>Catalog: upsert products and stock
    Service->>Log: mark success
```

## Order Export Flow

```mermaid
sequenceDiagram
    participant Checkout as Checkout/Admin Order
    participant Observer as Magento Observer
    participant Publisher as OrderExportPublisher
    participant MQ as RabbitMQ
    participant Consumer as OrderExportConsumer
    participant Service as OrderExportService
    participant ERP as Mock ERP API
    participant Log as Order Export Log

    Checkout->>Observer: order placed
    Observer->>Publisher: enqueue export
    Publisher->>Log: create/update queued log
    Publisher->>MQ: publish portfolio.order.export
    Consumer->>Service: process message
    Service->>ERP: POST order with idempotency key
    ERP-->>Service: external ERP ID
    Service->>Log: mark success
```

## Retry And Dead Letter Flow

```mermaid
stateDiagram-v2
    [*] --> queued
    queued --> running
    running --> success
    running --> retry_scheduled: transient failure
    retry_scheduled --> queued: retry-due CLI/cron
    running --> dead_lettered: max attempts reached
```

Retries are application-level and visible in Magento admin. A failed consumer schedules the next attempt with exponential backoff and acknowledges the original message only after durable retry state is saved.

## Search Autocomplete Flow

```mermaid
sequenceDiagram
    participant User as Storefront user
    participant React as React widget
    participant Endpoint as Magento suggest endpoint
    participant Search as Product collection/OpenSearch
    participant Log as Search Query Log

    User->>React: type query
    React->>React: debounce and cancel stale request
    React->>Endpoint: GET /searchenhancer/suggest
    Endpoint->>Search: query product collection
    Endpoint->>Log: log zero-result query when needed
    Endpoint-->>React: JSON items and meta
    React-->>User: keyboard-accessible suggestions
```

## Infrastructure Choices

- Docker Compose gives reviewers a repeatable local environment with Magento, MySQL, OpenSearch, RabbitMQ, Valkey, nginx, MailHog, and the mock ERP API.
- OpenSearch is used because Magento 2.4.8 supports it out of the box and it demonstrates production search infrastructure.
- RabbitMQ is used for integration workloads that should not block checkout, admin actions, or cron.
- Valkey provides Redis-compatible cache/session infrastructure.
- MailHog captures local email safely without external SMTP dependencies.
- GitHub Actions validates custom PHP syntax, Magento XML, mock API syntax, React widget build output, and Docker Compose configuration.

## Security And Operational Notes

- ERP API tokens are stored in encrypted Magento configuration fields.
- Order exports use idempotency keys to prevent duplicate external orders.
- Admin grids expose integration status without requiring database access.
- Retry and dead-letter states make failures recoverable and auditable.
- The mock ERP API supports bearer auth, request IDs, pagination, simulated failures, and idempotent order export.

## Intentional Scope

This is not intended to be a full production commerce build. The goal is to demonstrate senior Magento engineering judgment: clean module boundaries, integration patterns, async processing, observability, frontend production concerns, and a reproducible local stack.
