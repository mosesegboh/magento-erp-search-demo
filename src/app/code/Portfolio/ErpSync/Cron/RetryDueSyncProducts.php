<?php

declare(strict_types=1);

namespace Portfolio\ErpSync\Cron;

use Portfolio\ErpSync\Model\Queue\ProductSyncRetryManager;
use Psr\Log\LoggerInterface;

class RetryDueSyncProducts
{
    public function __construct(
        private readonly ProductSyncRetryManager $retryManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        try {
            $queued = $this->retryManager->retryDue();
        } catch (\Throwable $exception) {
            $this->logger->critical('ERP product sync retry cron failed.', ['exception' => $exception]);
            return;
        }

        if ($queued > 0) {
            $this->logger->info('ERP product sync retry cron queued due retries.', ['queued' => $queued]);
        }
    }
}
