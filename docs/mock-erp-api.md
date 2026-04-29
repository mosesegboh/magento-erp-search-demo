# Mock ERP/PIM API

The mock ERP/PIM API provides a local external system for Magento integration work. It is intentionally simple to run, but it includes real integration concerns: authentication, pagination, idempotency, retryable failures, and latency simulation.

## URLs

From the host machine:

```text
http://localhost:3001
```

From Magento/PHP containers:

```text
http://mock-erp-api:3001
```

## Authentication

Protected endpoints require a bearer token:

```bash
curl -H "Authorization: Bearer dev-erp-token" http://localhost:3001/api/products/updates
```

The token is configured in `.env`:

```env
MOCK_ERP_API_TOKEN=dev-erp-token
```

If port `3001` is already in use, set a different host port in `.env`:

```env
MOCK_ERP_API_HOST_PORT=3002
```

Keep `MOCK_ERP_API_URL=http://mock-erp-api:3001` because Magento uses Docker's internal service port.

## Endpoints

```text
GET  /health
GET  /docs
GET  /api/products/updates?page=1&pageSize=25&since=2026-04-20T00:00:00Z
GET  /api/products/{sku}
GET  /api/inventory/{sku}
POST /api/orders
GET  /api/orders/{externalId}
```

## Order Export

Order exports must include an `Idempotency-Key` header. Reusing the same key returns the original ERP order response instead of creating a duplicate.

```bash
curl -fsS \
  -H "Authorization: Bearer dev-erp-token" \
  -H "Idempotency-Key: demo-order-100000001" \
  -H "Content-Type: application/json" \
  -d '{
    "incrementId": "100000001",
    "currency": "USD",
    "customer": {"email": "customer@example.com"},
    "items": [
      {"sku": "24-MB01", "qty": 1, "price": 74.5}
    ]
  }' \
  http://localhost:3001/api/orders
```

## Failure Simulation

Use headers or query parameters to simulate integration failures:

```bash
curl -H "Authorization: Bearer dev-erp-token" \
  -H "x-mock-failure: 500" \
  http://localhost:3001/api/products/updates
```

Supported failures:

```text
x-mock-failure: 500
x-mock-failure: 429
x-mock-failure: timeout
x-mock-delay-ms: 2500
```

These will be used by the Magento modules to demonstrate retry logic, error logging, and manual recovery flows.
