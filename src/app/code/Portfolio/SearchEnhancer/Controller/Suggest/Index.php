<?php

declare(strict_types=1);

namespace Portfolio\SearchEnhancer\Controller\Suggest;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Portfolio\SearchEnhancer\Model\Config;
use Portfolio\SearchEnhancer\Model\SearchQueryLogger;
use Portfolio\SearchEnhancer\Model\SuggestionService;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly RawFactory $rawFactory,
        private readonly Json $json,
        private readonly SuggestionService $suggestionService,
        private readonly SearchQueryLogger $searchQueryLogger
    ) {
    }

    public function execute()
    {
        $query = $this->normalizeQuery((string)$this->request->getParam('q', ''));
        $limit = $this->normalizeLimit((int)$this->request->getParam('limit', $this->config->getSuggestionLimit()));

        if (!$this->config->isEnabled()) {
            return $this->buildJsonResponse(['error' => 'disabled'], [], 0, 503);
        }

        if (mb_strlen($query) < $this->config->getMinQueryLength()) {
            return $this->buildJsonResponse([
                'query' => $query,
                'items' => [],
                'meta' => [
                    'total' => 0,
                    'limit' => $limit,
                    'minQueryLength' => $this->config->getMinQueryLength(),
                    'reason' => 'query_too_short',
                ],
            ], [], $this->config->getCacheTtl());
        }

        $suggestions = $this->suggestionService->getSuggestions($query, $limit);
        $resultCount = (int)$suggestions['total'];
        $this->searchQueryLogger->log($query, $resultCount);

        $payload = [
            'query' => $query,
            'items' => $suggestions['items'],
            'meta' => [
                'total' => $resultCount,
                'limit' => $limit,
                'minQueryLength' => $this->config->getMinQueryLength(),
            ],
        ];

        return $this->buildJsonResponse($payload, $suggestions['tags'], $this->config->getCacheTtl());
    }

    /**
     * @param array<string, mixed> $payload
     * @param string[] $cacheTags
     */
    private function buildJsonResponse(array $payload, array $cacheTags, int $ttl, int $statusCode = 200)
    {
        $body = $this->json->serialize($payload);
        $etag = '"' . hash('sha256', $body) . '"';

        if ((string)$this->request->getHeader('If-None-Match') === $etag && $statusCode === 200) {
            $result = $this->rawFactory->create();
            $result->setHttpResponseCode(304);
            $result->setHeader('ETag', $etag, true);
            $this->applyCacheHeaders($result, $cacheTags, $ttl);
            return $result;
        }

        $result = $this->jsonFactory->create();
        $result->setHttpResponseCode($statusCode);
        $result->setData($payload);
        $result->setHeader('ETag', $etag, true);
        $this->applyCacheHeaders($result, $cacheTags, $ttl);

        return $result;
    }

    /**
     * @param string[] $cacheTags
     */
    private function applyCacheHeaders(mixed $result, array $cacheTags, int $ttl): void
    {
        if ($ttl > 0) {
            $result->setHeader('Cache-Control', sprintf('public, max-age=%d, stale-while-revalidate=30', $ttl), true);
        } else {
            $result->setHeader('Cache-Control', 'no-store', true);
        }

        if ($cacheTags !== []) {
            $result->setHeader('X-Magento-Tags', implode(',', $cacheTags), true);
        }

        $result->setHeader('X-Content-Type-Options', 'nosniff', true);
        $result->setHeader('Content-Type', 'application/json; charset=utf-8', true);
    }

    private function normalizeQuery(string $query): string
    {
        $query = trim((string)preg_replace('/\s+/', ' ', strip_tags($query)));
        return mb_substr($query, 0, $this->config->getMaxQueryLength());
    }

    private function normalizeLimit(int $limit): int
    {
        return min(max($limit, 1), $this->config->getSuggestionLimit());
    }
}
