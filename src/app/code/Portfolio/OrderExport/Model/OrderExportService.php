<?php

declare(strict_types=1);

namespace Portfolio\OrderExport\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Portfolio\OrderExport\Api\OrderExportClientInterface;
use Portfolio\OrderExport\Model\ResourceModel\ExportLog as ExportLogResource;
use Portfolio\OrderExport\Model\ResourceModel\ExportLog\CollectionFactory as ExportLogCollectionFactory;

class OrderExportService
{
    public function __construct(
        private readonly Config $config,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderPayloadBuilder $payloadBuilder,
        private readonly OrderExportClientInterface $client,
        private readonly ExportLogFactory $exportLogFactory,
        private readonly ExportLogResource $exportLogResource,
        private readonly ExportLogCollectionFactory $exportLogCollectionFactory
    ) {
    }

    public function exportByOrderId(int $orderId, bool $force = false): ExportLog
    {
        if (!$this->config->isEnabled()) {
            throw new LocalizedException(__('Order export is disabled.'));
        }

        $order = $this->orderRepository->get($orderId);
        $log = $this->getOrCreateLog($orderId, (string)$order->getIncrementId());

        if (!$force && $log->getData('status') === ExportLog::STATUS_SUCCESS) {
            return $log;
        }

        $payload = $this->payloadBuilder->build($order);
        $idempotencyKey = (string)$log->getData('idempotency_key');
        $attempts = (int)$log->getData('attempts') + 1;

        $log->setData('attempts', $attempts);
        $log->setData('request_payload', json_encode($payload, JSON_THROW_ON_ERROR));
        $log->setData('status', ExportLog::STATUS_PENDING);
        $this->exportLogResource->save($log);

        try {
            $response = $this->client->exportOrder($payload, $idempotencyKey);
            $externalId = (string)($response['data']['externalId'] ?? '');

            $log->setData('status', ExportLog::STATUS_SUCCESS);
            $log->setData('external_id', $externalId !== '' ? $externalId : null);
            $log->setData('response_payload', json_encode($response, JSON_THROW_ON_ERROR));
            $log->setData('message', 'Order exported successfully.');
        } catch (\Throwable $exception) {
            $log->setData('status', ExportLog::STATUS_FAILED);
            $log->setData('message', $exception->getMessage());
            $this->exportLogResource->save($log);
            throw $exception;
        }

        $this->exportLogResource->save($log);

        return $log;
    }

    public function queueByOrderId(int $orderId, bool $force = false): ExportLog
    {
        if (!$this->config->isEnabled()) {
            throw new LocalizedException(__('Order export is disabled.'));
        }

        $order = $this->orderRepository->get($orderId);
        $log = $this->getOrCreateLog($orderId, (string)$order->getIncrementId());

        if (!$force && $log->getData('status') === ExportLog::STATUS_SUCCESS) {
            return $log;
        }

        $log->setData('status', ExportLog::STATUS_QUEUED);
        $log->setData('message', 'Order export queued for asynchronous processing.');
        $this->exportLogResource->save($log);

        return $log;
    }

    private function getOrCreateLog(int $orderId, string $incrementId): ExportLog
    {
        $collection = $this->exportLogCollectionFactory->create();
        $collection->addFieldToFilter('order_id', $orderId);
        $collection->setPageSize(1);
        $existing = $collection->getFirstItem();

        if ($existing->getId()) {
            return $existing;
        }

        $log = $this->exportLogFactory->create();
        $log->setData([
            'order_id' => $orderId,
            'increment_id' => $incrementId,
            'status' => ExportLog::STATUS_PENDING,
            'idempotency_key' => sprintf('magento-order-%s-%d', $incrementId, $orderId),
            'attempts' => 0,
        ]);
        $this->exportLogResource->save($log);

        return $log;
    }
}
