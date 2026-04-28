<?php

declare(strict_types=1);

namespace Portfolio\ErpSync\Model\Queue;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Portfolio\ErpSync\Api\QueueProductSyncInterface;
use Portfolio\ErpSync\Model\ProductSyncService;
use Psr\Log\LoggerInterface;

class ProductSyncConsumer implements QueueProductSyncInterface
{
    public function __construct(
        private readonly ProductSyncService $productSyncService,
        private readonly ProductSyncRetryManager $retryManager,
        private readonly State $appState,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Process an asynchronous ERP product sync request.
     *
     * @param string $since ISO-8601 timestamp filter, or empty string for all updates.
     * @param int $pageSize Optional ERP API page-size override.
     * @param bool $dryRun Whether to fetch and log products without writing catalog changes.
     * @param int $logId Existing sync log ID created when the message was published.
     * @param int $attempt Current queue attempt number.
     * @return void
     */
    public function execute(
        string $since = '',
        int $pageSize = 0,
        bool $dryRun = false,
        int $logId = 0,
        int $attempt = 1
    ): void
    {
        $this->process($since, $pageSize, $dryRun, $logId, $attempt);
    }

    /**
     * Process an asynchronous ERP product sync request.
     *
     * @param string $since ISO-8601 timestamp filter, or empty string for all updates.
     * @param int $pageSize Optional ERP API page-size override.
     * @param bool $dryRun Whether to fetch and log products without writing catalog changes.
     * @param int $logId Existing sync log ID created when the message was published.
     * @param int $attempt Current queue attempt number.
     * @return void
     */
    public function process(
        string $since = '',
        int $pageSize = 0,
        bool $dryRun = false,
        int $logId = 0,
        int $attempt = 1
    ): void
    {
        $this->setAreaCode();

        try {
            $this->productSyncService->sync(
                $since !== '' ? $since : null,
                $pageSize > 0 ? $pageSize : null,
                $dryRun,
                $logId > 0 ? $logId : null,
                $attempt
            );
        } catch (\Throwable $exception) {
            $scheduled = $this->retryManager->scheduleAfterFailure(
                $since,
                $pageSize,
                $dryRun,
                $logId,
                $attempt,
                $exception
            );

            $logContext = [
                'since' => $since,
                'page_size' => $pageSize,
                'dry_run' => $dryRun,
                'log_id' => $logId,
                'attempt' => $attempt,
                'retry_scheduled_or_dead_lettered' => $scheduled,
                'exception' => $exception,
            ];

            if ($scheduled) {
                $this->logger->warning('ERP product sync queue consumer failed; retry state updated.', $logContext);
            } else {
                $this->logger->critical('ERP product sync queue consumer failed.', $logContext);
            }

            if (!$scheduled) {
                throw $exception;
            }
        }
    }

    private function setAreaCode(): void
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (LocalizedException) {
            // Area code may already be set by another command/bootstrap path.
        }
    }
}
