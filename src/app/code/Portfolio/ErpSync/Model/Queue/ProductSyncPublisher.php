<?php

declare(strict_types=1);

namespace Portfolio\ErpSync\Model\Queue;

use Magento\Framework\MessageQueue\PublisherInterface;
use Portfolio\ErpSync\Model\ProductSyncService;
use Portfolio\ErpSync\Model\ResourceModel\SyncLog as SyncLogResource;
use Portfolio\ErpSync\Model\SyncLog;
use Psr\Log\LoggerInterface;

class ProductSyncPublisher
{
    public const TOPIC_NAME = 'portfolio.erp.product_sync';

    public function __construct(
        private readonly PublisherInterface $publisher,
        private readonly ProductSyncService $productSyncService,
        private readonly SyncLogResource $syncLogResource,
        private readonly LoggerInterface $logger
    ) {
    }

    public function publish(?string $since = null, ?int $pageSize = null, bool $dryRun = false): int
    {
        $log = $this->productSyncService->queueSync($since, $pageSize, $dryRun);

        try {
            $this->publisher->publish(self::TOPIC_NAME, [
                'since' => $since ?? '',
                'pageSize' => $pageSize ?? 0,
                'dryRun' => $dryRun,
                'logId' => (int)$log->getId(),
            ]);
        } catch (\Throwable $exception) {
            $log->setData('status', SyncLog::STATUS_FAILED);
            $log->setData('message', sprintf('Queue publish failed: %s', $exception->getMessage()));
            $log->setData('finished_at', gmdate('Y-m-d H:i:s'));
            $this->syncLogResource->save($log);

            $this->logger->critical('ERP product sync queue publish failed.', [
                'log_id' => $log->getId(),
                'exception' => $exception,
            ]);

            throw $exception;
        }

        return (int)$log->getId();
    }
}
