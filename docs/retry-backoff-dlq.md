# Retry, Backoff, And Dead Letter Handling

The queue consumers use application-level retry scheduling instead of repeatedly throwing the same failed RabbitMQ message. This makes failures visible in Magento admin and gives operators a safe recovery path.

## What Happens On Failure

1. The consumer catches the ERP/API exception.
2. The integration log is updated with the failed attempt count.
3. If attempts remain, the log moves to `retry_scheduled` with `next_retry_at`.
4. Cron or CLI requeues due retries.
5. If max attempts are exhausted, the log moves to `dead_lettered`.

## Retry Policy

Default settings:

```text
Max attempts: 3
Base delay:   60 seconds
Backoff:      60s, 120s, 240s, capped at 24 hours
```

Admin paths:

```text
Stores > Configuration > Portfolio Integrations > ERP Sync > Retry Policy
Stores > Configuration > Portfolio Integrations > Order Export > Retry Policy
```

## CLI Commands

Queue due ERP product sync retries:

```bash
docker compose exec --user www-data php bin/magento portfolio:erp:sync-products:retry-due
```

Queue due order export retries:

```bash
docker compose exec --user www-data php bin/magento portfolio:order-export:retry-due
```

Limit each run:

```bash
docker compose exec --user www-data php bin/magento portfolio:erp:sync-products:retry-due --limit=10
docker compose exec --user www-data php bin/magento portfolio:order-export:retry-due --limit=10
```

## Cron

Both retry schedulers run every five minutes:

```text
portfolio_erp_sync_retry_due
portfolio_order_export_retry_due
```

## Admin Visibility

Review retry and dead-letter state in:

```text
Portfolio > ERP Sync Logs
Portfolio > Order Export Logs
```

Important columns:

```text
Status
Attempts
Next Retry At
Message
```

Expected statuses:

```text
queued
running / pending
retry_scheduled
success
failed
dead_lettered
```

## Testing Failure Handling

Temporarily break the ERP token or base URL in Magento admin, then queue and consume a message.

For fast local testing, temporarily remove the retry delay:

```bash
docker compose exec --user www-data php bin/magento config:set portfolio_erp_sync/retry/base_delay_seconds 0
docker compose exec --user www-data php bin/magento config:set portfolio_order_export/retry/base_delay_seconds 0
docker compose exec --user www-data php bin/magento cache:flush
```

ERP sync:

```bash
docker compose exec --user www-data php bin/magento portfolio:erp:sync-products:enqueue --dry-run
docker compose exec --user www-data php bin/magento queue:consumers:start portfolio.erp.product_sync --max-messages=1 --area-code=adminhtml
```

Order export:

```bash
docker compose exec --user www-data php bin/magento portfolio:order-export:enqueue 000000001 --force
docker compose exec --user www-data php bin/magento queue:consumers:start portfolio.order.export --max-messages=1 --area-code=adminhtml
```

The latest log should move to `retry_scheduled`. After `next_retry_at`, run the matching `retry-due` command and consume the queued retry.

## Production Notes

- Failed messages are acknowledged only after retry state is safely written to the database.
- Retry scheduling avoids blocking queue workers with long sleeps.
- Dead-lettered logs preserve the original context and last error for manual investigation.
- Operators can fix the root cause, then requeue by running the original enqueue command again.
