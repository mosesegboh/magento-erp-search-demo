<?php

declare(strict_types=1);

namespace Portfolio\OrderExport\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Portfolio\OrderExport\Model\OrderExportService;
use Psr\Log\LoggerInterface;

class ExportOrderAfterCheckout implements ObserverInterface
{
    public function __construct(
        private readonly OrderExportService $orderExportService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();

        if (!$order instanceof OrderInterface || !$order->getEntityId()) {
            return;
        }

        try {
            $this->orderExportService->exportByOrderId((int)$order->getEntityId());
        } catch (\Throwable $exception) {
            $this->logger->critical('Order export after checkout failed.', [
                'order_id' => $order->getEntityId(),
                'exception' => $exception,
            ]);
        }
    }
}
