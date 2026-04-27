<?php

declare(strict_types=1);

namespace Portfolio\OrderExport\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Phrase;
use Portfolio\OrderExport\Api\OrderExportClientInterface;
use Portfolio\OrderExport\Model\Config;

class OrderExportClient implements OrderExportClientInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly CurlFactory $curlFactory
    ) {
    }

    public function exportOrder(array $payload, string $idempotencyKey): array
    {
        $baseUrl = $this->config->getApiBaseUrl();

        if ($baseUrl === '') {
            throw new LocalizedException(new Phrase('Order export API base URL is not configured.'));
        }

        $token = $this->config->getApiToken();

        if ($token === '') {
            throw new LocalizedException(new Phrase('Order export API token is not configured.'));
        }

        $client = $this->curlFactory->create();
        $client->setTimeout($this->config->getApiTimeout());
        $client->addHeader('Authorization', 'Bearer ' . $token);
        $client->addHeader('Accept', 'application/json');
        $client->addHeader('Content-Type', 'application/json');
        $client->addHeader('Idempotency-Key', $idempotencyKey);
        $client->post($baseUrl . '/api/orders', json_encode($payload, JSON_THROW_ON_ERROR));

        $status = $client->getStatus();
        $body = (string)$client->getBody();

        if ($status < 200 || $status >= 300) {
            throw new LocalizedException(
                new Phrase('Order export API failed with HTTP %1: %2', [$status, $body])
            );
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new LocalizedException(
                new Phrase('Order export API returned invalid JSON: %1', [$exception->getMessage()])
            );
        }

        if (!is_array($decoded)) {
            throw new LocalizedException(new Phrase('Order export API returned an unexpected response.'));
        }

        return $decoded;
    }
}
