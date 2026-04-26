<?php

declare(strict_types=1);

namespace Portfolio\ErpSync\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Phrase;
use Portfolio\ErpSync\Api\ErpClientInterface;
use Portfolio\ErpSync\Model\Config;

class ErpClient implements ErpClientInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly CurlFactory $curlFactory
    ) {
    }

    public function getProductUpdates(int $page, int $pageSize, ?string $since = null): array
    {
        $query = [
            'page' => $page,
            'pageSize' => $pageSize,
        ];

        if ($since !== null && $since !== '') {
            $query['since'] = $since;
        }

        return $this->request('GET', '/api/products/updates', $query);
    }

    public function getProduct(string $sku): array
    {
        return $this->request('GET', '/api/products/' . rawurlencode($sku));
    }

    public function getInventory(string $sku): array
    {
        return $this->request('GET', '/api/inventory/' . rawurlencode($sku));
    }

    /**
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function request(string $method, string $path, array $query = []): array
    {
        $baseUrl = $this->config->getApiBaseUrl();

        if ($baseUrl === '') {
            throw new LocalizedException(new Phrase('ERP API base URL is not configured.'));
        }

        $token = $this->config->getApiToken();

        if ($token === '') {
            throw new LocalizedException(new Phrase('ERP API token is not configured.'));
        }

        $url = $baseUrl . $path;

        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $client = $this->curlFactory->create();
        $client->setTimeout($this->config->getApiTimeout());
        $client->addHeader('Authorization', 'Bearer ' . $token);
        $client->addHeader('Accept', 'application/json');

        if ($method === 'GET') {
            $client->get($url);
        } else {
            throw new LocalizedException(new Phrase('Unsupported ERP API method: %1', [$method]));
        }

        $status = $client->getStatus();
        $body = (string)$client->getBody();

        if ($status < 200 || $status >= 300) {
            throw new LocalizedException(
                new Phrase('ERP API request failed with HTTP %1 for %2: %3', [$status, $path, $body])
            );
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new LocalizedException(
                new Phrase('ERP API returned invalid JSON for %1: %2', [$path, $exception->getMessage()])
            );
        }

        if (!is_array($decoded)) {
            throw new LocalizedException(new Phrase('ERP API returned an unexpected response for %1.', [$path]));
        }

        return $decoded;
    }
}
