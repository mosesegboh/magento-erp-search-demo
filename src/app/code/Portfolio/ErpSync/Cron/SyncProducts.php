<?php

declare(strict_types=1);

namespace Portfolio\ErpSync\Cron;

use Portfolio\ErpSync\Model\Config;
use Portfolio\ErpSync\Model\Queue\ProductSyncPublisher;
use Psr\Log\LoggerInterface;

class SyncProducts
{
    public function __construct(
        private readonly Config $config,
        private readonly ProductSyncPublisher $productSyncPublisher,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->isCronEnabled()) {
            return;
        }

        try {
            $this->productSyncPublisher->publish();
        } catch (\Throwable $exception) {
            $this->logger->critical('Scheduled ERP product sync queue publish failed.', [
                'exception' => $exception,
            ]);
        }
    }
}
