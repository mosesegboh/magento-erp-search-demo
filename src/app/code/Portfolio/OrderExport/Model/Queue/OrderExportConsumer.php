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
     * @return void
     */
    public function execute(int $orderId, bool $force = false, int $logId = 0): void
    {
        $this->process($orderId, $force, $logId);
    }

    /**
     * Process an asynchronous ERP order export request.
     *
     * @param int $orderId Magento sales order entity ID.
     * @param bool $force Whether to export even if a successful export log already exists.
     * @param int $logId Existing export log ID created when the message was published.
     * @return void
     */
    public function process(int $orderId, bool $force = false, int $logId = 0): void
    {
        $this->setAreaCode();

        try {
            $this->orderExportService->exportByOrderId($orderId, $force);
        } catch (\Throwable $exception) {
            $this->logger->critical('Order export queue consumer failed.', [
                'order_id' => $orderId,
                'force' => $force,
                'log_id' => $logId,
                'exception' => $exception,
            ]);

            throw $exception;
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
