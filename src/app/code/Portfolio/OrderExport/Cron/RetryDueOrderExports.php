<?php

declare(strict_types=1);

namespace Portfolio\OrderExport\Cron;

use Portfolio\OrderExport\Model\Queue\OrderExportRetryManager;
use Psr\Log\LoggerInterface;

class RetryDueOrderExports
{
    public function __construct(
        private readonly OrderExportRetryManager $retryManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        try {
            $queued = $this->retryManager->retryDue();
        } catch (\Throwable $exception) {
            $this->logger->critical('Order export retry cron failed.', ['exception' => $exception]);
            return;
        }

        if ($queued > 0) {
            $this->logger->info('Order export retry cron queued due retries.', ['queued' => $queued]);
        }
    }
}
