# Search Enhancer Module

`Portfolio_SearchEnhancer` adds a production-oriented product suggestion endpoint and React autocomplete source for Magento catalog search. It demonstrates Magento search integration, OpenSearch health diagnostics, frontend resilience, and zero-result search analytics.

## Backend Features

- JSON endpoint: `GET /searchenhancer/suggest?q=backpack`
- Magento fulltext search collection, backed by the configured search engine.
- Query length and result limit guards.
- Short-lived public cache headers.
- `ETag` support for unchanged responses.
- Product cache tags through `X-Magento-Tags`.
- Zero-result query logging in `portfolio_search_query_log`.
- OpenSearch health command.

## Frontend Features

React source lives in:

```text
src/app/code/Portfolio/SearchEnhancer/frontend/src/main.jsx
```

The component handles:

- Debounce to avoid request storms.
- `AbortController` cancellation for superseded requests.
- Request sequence checks to prevent stale responses from winning race conditions.
- Request timeout handling.
- Bounded in-memory cache with TTL and LRU-style eviction.
- Keyboard navigation with `ArrowUp`, `ArrowDown`, `Enter`, and `Escape`.
- ARIA combobox/listbox states.
- Graceful loading, error, and empty states.
- No-JavaScript fallback form.

## Build React Asset

```bash
make search-widget-install
make search-widget-build
```

The build writes:

```text
src/app/code/Portfolio/SearchEnhancer/view/frontend/web/js/search-autocomplete.bundle.js
```

## Install Or Upgrade

```bash
docker compose exec --user www-data php bin/magento module:enable Portfolio_SearchEnhancer
docker compose exec --user www-data php bin/magento setup:upgrade
docker compose exec --user www-data php bin/magento indexer:reindex catalogsearch_fulltext
docker compose exec --user www-data php bin/magento cache:flush
```

If generated code is stale:

```bash
docker compose exec --user www-data php rm -rf generated/code/Portfolio/SearchEnhancer generated/metadata/*
docker compose exec --user www-data php bin/magento setup:di:compile
```

## Test Backend Endpoint

```bash
curl -i "http://localhost:8080/searchenhancer/suggest?q=backpack"
```

Expected:

```text
HTTP/1.1 200 OK
Cache-Control: public, max-age=60, stale-while-revalidate=30
ETag: "..."
X-Magento-Tags: cat_p_...
```

## Test OpenSearch Health

```bash
docker compose exec --user www-data php bin/magento portfolio:search:opensearch-health
```

## Check Zero-Result Logs

Run a query that should not match anything:

```bash
curl -fsS "http://localhost:8080/searchenhancer/suggest?q=zzznomatch"
```

Then inspect logs:

```bash
docker compose exec mysql mysql -umagento -pmagento magento -e "SELECT log_id, query_text, result_count, source, created_at FROM portfolio_search_query_log ORDER BY log_id DESC LIMIT 10;"
```

## Production Notes

- Keep the backend endpoint anonymous-safe; do not include customer-specific prices unless cache varies by customer group/currency.
- Put CDN/WAF rate limits in front of the endpoint in production.
- Keep query length and result limits low to protect OpenSearch.
- Use short TTLs because suggestions change with catalog updates.
- Use cache tags so downstream caches can invalidate product-related responses.
- For high traffic, aggregate zero-result logs asynchronously rather than writing every request synchronously.
