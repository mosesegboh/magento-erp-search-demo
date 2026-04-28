<?php

declare(strict_types=1);

namespace Portfolio\OrderExport\Model\Queue;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Portfolio\OrderExport\Api\QueueOrderExportInterface;
use Portfolio\OrderExport\Model\OrderExportService;
use Psr\Log\LoggerInterface;

class OrderExportConsumer implements QueueOrderExportInterface
{
    public function __construct(
        private readonly OrderExportService $orderExportService,
        private readonly OrderExportRetryManager $retryManager,
        private readonly State $appState,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Process an asynchronous ERP order export request.
     *
     * @param int $orderId Magento sales order entity ID.
     * @param bool $force Whether to export even if a successful export log already exists.
     * @param int $logId Existing export log ID created when the message was published.
     * @param int $attempt Current queue attempt number.
     * @return void
     */
    public function execute(int $orderId, bool $force = false, int $logId = 0, int $attempt = 1): void
    {
        $this->process($orderId, $force, $logId, $attempt);
    }

    /**
     * Process an asynchronous ERP order export request.
     *
     * @param int $orderId Magento sales order entity ID.
     * @param bool $force Whether to export even if a successful export log already exists.
     * @param int $logId Existing export log ID created when the message was published.
     * @param int $attempt Current queue attempt number.
     * @return void
     */
    public function process(int $orderId, bool $force = false, int $logId = 0, int $attempt = 1): void
    {
        $this->setAreaCode();

        try {
            $this->orderExportService->exportByOrderId($orderId, $force);
        } catch (\Throwable $exception) {
            $scheduled = $this->retryManager->scheduleAfterFailure(
                $orderId,
                $force,
                $logId,
                $attempt,
                $exception
            );

            $logContext = [
                'order_id' => $orderId,
                'force' => $force,
                'log_id' => $logId,
                'attempt' => $attempt,
                'retry_scheduled_or_dead_lettered' => $scheduled,
                'exception' => $exception,
            ];

            if ($scheduled) {
                $this->logger->warning('Order export queue consumer failed; retry state updated.', $logContext);
            } else {
                $this->logger->critical('Order export queue consumer failed.', $logContext);
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
