<?php

declare(strict_types=1);

namespace Portfolio\SearchEnhancer\Model;

use Magento\Store\Model\StoreManagerInterface;
use Portfolio\SearchEnhancer\Model\ResourceModel\SearchQueryLog as SearchQueryLogResource;
use Psr\Log\LoggerInterface;

class SearchQueryLogger
{
    public function __construct(
        private readonly Config $config,
        private readonly SearchQueryLogFactory $searchQueryLogFactory,
        private readonly SearchQueryLogResource $searchQueryLogResource,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function log(string $query, int $resultCount, string $source = 'suggest'): void
    {
        if (!$this->config->shouldLogZeroResults() || $resultCount > 0) {
            return;
        }

        try {
            $log = $this->searchQueryLogFactory->create();
            $log->setData([
                'query_text' => mb_substr($query, 0, 255),
                'query_hash' => hash('sha256', mb_strtolower($query)),
                'result_count' => $resultCount,
                'source' => $source,
                'store_id' => (int)$this->storeManager->getStore()->getId(),
                'created_at' => $this->getCurrentUtcTimestamp(),
            ]);
            $this->searchQueryLogResource->save($log);
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to write search query log.', [
                'query' => $query,
                'result_count' => $resultCount,
                'exception' => $exception,
            ]);
        }
    }

    private function getCurrentUtcTimestamp(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
