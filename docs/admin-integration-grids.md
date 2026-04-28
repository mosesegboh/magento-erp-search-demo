# Admin Integration Grids

The admin grids expose integration observability inside Magento instead of relying on direct database access. This is useful for support, QA, and non-technical stakeholders reviewing integration state.

## Admin Menu

After upgrade and cache flush, log in to the Magento admin and open:

```text
Portfolio > ERP Sync Logs
Portfolio > Order Export Logs
Portfolio > Search Query Logs
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

## Generate Demo Data

ERP sync logs:

```bash
docker compose exec --user www-data php bin/magento portfolio:erp:sync-products --dry-run
```

Order export logs:

```bash
docker compose exec --user www-data php bin/magento portfolio:order-export:export 000000001 --force
```

Retry/dead-letter logs:

```bash
docker compose exec --user www-data php bin/magento portfolio:erp:sync-products:retry-due
docker compose exec --user www-data php bin/magento portfolio:order-export:retry-due
```

Search query logs:

```bash
curl -fsS "http://localhost:8080/searchenhancer/suggest?q=zzznomatch"
```

## Senior-Level Talking Points

- Operational logs are visible from Magento admin with ACL-protected menu entries.
- UI component grids support sorting, filtering, pagination, bookmarks, and column controls.
- Each grid uses Magento data provider collection mapping instead of custom ad-hoc controllers.
- Queue retry state is visible through status, attempts, message, and next retry timestamp columns.
- The implementation keeps read-only observability separate from write/retry workflows.
