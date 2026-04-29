import http from 'node:http';
import { createHash, randomUUID } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
import { fileURLToPath } from 'node:url';

const port = Number(process.env.PORT || 3001);
const apiToken = process.env.MOCK_ERP_API_TOKEN || 'dev-erp-token';
const serviceName = 'mock-erp-api';
const dataDirectory = join(dirname(fileURLToPath(import.meta.url)), '..', 'data');
const products = JSON.parse(await readFile(join(dataDirectory, 'products.json'), 'utf8'));

const ordersByExternalId = new Map();
const idempotencyIndex = new Map();

const server = http.createServer(async (request, response) => {
  const requestId = request.headers['x-request-id'] || randomUUID();
  response.setHeader('x-request-id', requestId);
  response.setHeader('access-control-allow-origin', '*');
  response.setHeader('access-control-allow-headers', 'authorization,content-type,idempotency-key,x-mock-delay-ms,x-mock-failure,x-request-id');
  response.setHeader('access-control-allow-methods', 'GET,POST,OPTIONS');

  if (request.method === 'OPTIONS') {
    response.writeHead(204);
    response.end();
    return;
  }

  try {
    const url = new URL(request.url, `http://${request.headers.host}`);

    if (await applyMockControls(request, response, url)) {
      return;
    }

    if (url.pathname === '/health' && request.method === 'GET') {
      sendJson(response, 200, {
        status: 'ok',
        service: serviceName,
        productCount: products.length,
        orderCount: ordersByExternalId.size
      });
      return;
    }

    if (url.pathname === '/docs' && request.method === 'GET') {
      sendHtml(response, buildDocsHtml());
      return;
    }

    if (url.pathname.startsWith('/api/') && !isAuthorized(request)) {
      sendJson(response, 401, {
        error: 'unauthorized',
        message: 'Missing or invalid bearer token.'
      });
      return;
    }

    if (url.pathname === '/api/products/updates' && request.method === 'GET') {
      handleProductUpdates(url, response);
      return;
    }

    const productMatch = url.pathname.match(/^\/api\/products\/([^/]+)$/);
    if (productMatch && request.method === 'GET') {
      handleProduct(productMatch[1], response);
      return;
    }

    const inventoryMatch = url.pathname.match(/^\/api\/inventory\/([^/]+)$/);
    if (inventoryMatch && request.method === 'GET') {
      handleInventory(inventoryMatch[1], response);
      return;
    }

    if (url.pathname === '/api/orders' && request.method === 'POST') {
      await handleOrderExport(request, response);
      return;
    }

    const orderMatch = url.pathname.match(/^\/api\/orders\/([^/]+)$/);
    if (orderMatch && request.method === 'GET') {
      handleOrder(orderMatch[1], response);
      return;
    }

    sendJson(response, 404, {
      error: 'not_found',
      message: `No route found for ${request.method} ${url.pathname}.`
    });
  } catch (error) {
    sendJson(response, 500, {
      error: 'internal_error',
      message: error.message
    });
  }
});

server.listen(port, '0.0.0.0', () => {
  console.log(`${serviceName} listening on port ${port}`);
});

function handleProductUpdates(url, response) {
  const page = positiveInt(url.searchParams.get('page'), 1);
  const pageSize = Math.min(positiveInt(url.searchParams.get('pageSize'), 25), 100);
  const since = url.searchParams.get('since');

  const filteredProducts = since
    ? products.filter((product) => Date.parse(product.updatedAt) > Date.parse(since))
    : products;

  const start = (page - 1) * pageSize;
  const items = filteredProducts.slice(start, start + pageSize);
  const total = filteredProducts.length;

  sendJson(response, 200, {
    data: items,
    meta: {
      page,
      pageSize,
      total,
      totalPages: Math.ceil(total / pageSize),
      since: since || null
    }
  });
}

function handleProduct(encodedSku, response) {
  const sku = decodeURIComponent(encodedSku);
  const product = findProduct(sku);

  if (!product) {
    sendJson(response, 404, {
      error: 'product_not_found',
      message: `Product ${sku} does not exist in the ERP catalog.`
    });
    return;
  }

  sendJson(response, 200, { data: product });
}

function handleInventory(encodedSku, response) {
  const sku = decodeURIComponent(encodedSku);
  const product = findProduct(sku);

  if (!product) {
    sendJson(response, 404, {
      error: 'inventory_not_found',
      message: `Inventory for ${sku} does not exist.`
    });
    return;
  }

  sendJson(response, 200, {
    data: {
      sku: product.sku,
      stockQty: product.stockQty,
      isInStock: product.isInStock,
      warehouseStock: product.warehouseStock,
      updatedAt: product.updatedAt
    }
  });
}

async function handleOrderExport(request, response) {
  const idempotencyKey = request.headers['idempotency-key'];

  if (!idempotencyKey) {
    sendJson(response, 400, {
      error: 'missing_idempotency_key',
      message: 'Order exports must include an Idempotency-Key header.'
    });
    return;
  }

  if (idempotencyIndex.has(idempotencyKey)) {
    const externalId = idempotencyIndex.get(idempotencyKey);
    sendJson(response, 200, {
      data: ordersByExternalId.get(externalId),
      meta: { replayed: true }
    });
    return;
  }

  const payload = await readJson(request);
  const validationError = validateOrderPayload(payload);

  if (validationError) {
    sendJson(response, 422, {
      error: 'invalid_order_payload',
      message: validationError
    });
    return;
  }

  const externalId = buildExternalOrderId(payload, idempotencyKey);
  const exportedOrder = {
    externalId,
    status: 'accepted',
    receivedAt: new Date().toISOString(),
    source: 'magento',
    payload
  };

  ordersByExternalId.set(externalId, exportedOrder);
  idempotencyIndex.set(idempotencyKey, externalId);

  sendJson(response, 201, {
    data: exportedOrder,
    meta: { replayed: false }
  });
}

function handleOrder(encodedExternalId, response) {
  const externalId = decodeURIComponent(encodedExternalId);
  const order = ordersByExternalId.get(externalId);

  if (!order) {
    sendJson(response, 404, {
      error: 'order_not_found',
      message: `Order ${externalId} does not exist in the ERP.`
    });
    return;
  }

  sendJson(response, 200, { data: order });
}

async function applyMockControls(request, response, url) {
  const delayMs = Math.min(positiveInt(request.headers['x-mock-delay-ms'] || url.searchParams.get('mockDelayMs'), 0), 10000);

  if (delayMs > 0) {
    await delay(delayMs);
  }

  const failure = request.headers['x-mock-failure'] || url.searchParams.get('mockFailure');

  if (!failure) {
    return false;
  }

  if (failure === '500') {
    sendJson(response, 500, {
      error: 'mock_internal_error',
      message: 'Simulated ERP server failure.'
    });
    return true;
  }

  if (failure === '429') {
    response.setHeader('retry-after', '30');
    sendJson(response, 429, {
      error: 'mock_rate_limited',
      message: 'Simulated ERP rate limit.'
    });
    return true;
  }

  if (failure === 'timeout') {
    await delay(15000);
    sendJson(response, 504, {
      error: 'mock_timeout',
      message: 'Simulated upstream timeout.'
    });
    return true;
  }

  return false;
}

function findProduct(sku) {
  return products.find((product) => product.sku.toLowerCase() === sku.toLowerCase());
}

function isAuthorized(request) {
  return request.headers.authorization === `Bearer ${apiToken}`;
}

function positiveInt(value, fallback) {
  const parsed = Number.parseInt(value, 10);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

function readJson(request) {
  return new Promise((resolve, reject) => {
    let body = '';

    request.on('data', (chunk) => {
      body += chunk;

      if (body.length > 1024 * 1024) {
        request.destroy();
        reject(new Error('Request body is too large.'));
      }
    });

    request.on('end', () => {
      try {
        resolve(body ? JSON.parse(body) : {});
      } catch {
        reject(new Error('Request body must be valid JSON.'));
      }
    });

    request.on('error', reject);
  });
}

function validateOrderPayload(payload) {
  if (!payload || typeof payload !== 'object') {
    return 'Order payload must be a JSON object.';
  }

  if (!payload.incrementId && !payload.magentoOrderId) {
    return 'Order payload must include incrementId or magentoOrderId.';
  }

  if (!Array.isArray(payload.items) || payload.items.length === 0) {
    return 'Order payload must include at least one item.';
  }

  for (const item of payload.items) {
    if (!item.sku || Number(item.qty) <= 0) {
      return 'Each order item must include sku and a positive qty.';
    }
  }

  return null;
}

function buildExternalOrderId(payload, idempotencyKey) {
  const sourceId = payload.incrementId || payload.magentoOrderId || idempotencyKey;
  const hash = createHash('sha1').update(`${sourceId}:${idempotencyKey}`).digest('hex').slice(0, 8).toUpperCase();
  return `ERP-${hash}`;
}

function sendJson(response, statusCode, payload) {
  response.writeHead(statusCode, { 'content-type': 'application/json; charset=utf-8' });
  response.end(`${JSON.stringify(payload, null, 2)}\n`);
}

function sendHtml(response, html) {
  response.writeHead(200, { 'content-type': 'text/html; charset=utf-8' });
  response.end(html);
}

function buildDocsHtml() {
  return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Mock ERP API</title>
  <style>
    body { font-family: ui-sans-serif, system-ui, sans-serif; margin: 2rem; line-height: 1.5; color: #172033; }
    code { background: #eef2f7; padding: .15rem .3rem; border-radius: .25rem; }
    li { margin: .35rem 0; }
  </style>
</head>
<body>
  <h1>Mock ERP/PIM API</h1>
  <p>Use <code>Authorization: Bearer ${apiToken}</code> for <code>/api/*</code> endpoints.</p>
  <ul>
    <li><code>GET /health</code></li>
    <li><code>GET /api/products/updates?page=1&amp;pageSize=25&amp;since=2026-04-20T00:00:00Z</code></li>
    <li><code>GET /api/products/24-MB01</code></li>
    <li><code>GET /api/inventory/24-MB01</code></li>
    <li><code>POST /api/orders</code> with an <code>Idempotency-Key</code> header</li>
    <li><code>GET /api/orders/{externalId}</code></li>
  </ul>
  <p>Failure simulation: add <code>x-mock-failure: 500</code>, <code>429</code>, or <code>timeout</code>. Add <code>x-mock-delay-ms</code> to simulate latency.</p>
</body>
</html>`;
}
