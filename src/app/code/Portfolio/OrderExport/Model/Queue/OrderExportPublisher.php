<?php

declare(strict_types=1);

namespace Portfolio\OrderExport\Model\Queue;

use Magento\Framework\MessageQueue\PublisherInterface;
use Portfolio\OrderExport\Model\ExportLog;
use Portfolio\OrderExport\Model\OrderExportService;
use Portfolio\OrderExport\Model\ResourceModel\ExportLog as ExportLogResource;
use Psr\Log\LoggerInterface;

class OrderExportPublisher
{
    public const TOPIC_NAME = 'portfolio.order.export';

    public function __construct(
        private readonly PublisherInterface $publisher,
        private readonly OrderExportService $orderExportService,
        private readonly ExportLogResource $exportLogResource,
        private readonly LoggerInterface $logger
    ) {
    }

    public function publish(int $orderId, bool $force = false): int
    {
        $log = $this->orderExportService->queueByOrderId($orderId, $force);

        if (!$force && $log->getData('status') === ExportLog::STATUS_SUCCESS) {
            return (int)$log->getId();
        }

        try {
            $this->publisher->publish(self::TOPIC_NAME, [
                'orderId' => $orderId,
                'force' => $force,
                'logId' => (int)$log->getId(),
            ]);
        } catch (\Throwable $exception) {
            $log->setData('status', ExportLog::STATUS_FAILED);
            $log->setData('message', sprintf('Queue publish failed: %s', $exception->getMessage()));
            $this->exportLogResource->save($log);

            $this->logger->critical('Order export queue publish failed.', [
                'order_id' => $orderId,
                'log_id' => $log->getId(),
                'exception' => $exception,
            ]);

            throw $exception;
        }

        return (int)$log->getId();
    }
}
