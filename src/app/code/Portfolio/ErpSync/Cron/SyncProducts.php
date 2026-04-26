<?php

declare(strict_types=1);

namespace Portfolio\ErpSync\Cron;

use Portfolio\ErpSync\Model\Config;
use Portfolio\ErpSync\Model\ProductSyncService;
use Psr\Log\LoggerInterface;

class SyncProducts
{
    public function __construct(
        private readonly Config $config,
        private readonly ProductSyncService $productSyncService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->isCronEnabled()) {
            return;
        }

        try {
            $this->productSyncService->sync();
        } catch (\Throwable $exception) {
            $this->logger->critical('Scheduled ERP product sync failed.', [
                'exception' => $exception,
            ]);
        }
    }
}
