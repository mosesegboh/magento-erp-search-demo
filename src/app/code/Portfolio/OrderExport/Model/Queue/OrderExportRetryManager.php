<?php

declare(strict_types=1);

namespace Portfolio\OrderExport\Model\Queue;

use Portfolio\OrderExport\Model\Config;
use Portfolio\OrderExport\Model\ExportLog;
use Portfolio\OrderExport\Model\ExportLogFactory;
use Portfolio\OrderExport\Model\ResourceModel\ExportLog as ExportLogResource;
use Portfolio\OrderExport\Model\ResourceModel\ExportLog\CollectionFactory as ExportLogCollectionFactory;
use Psr\Log\LoggerInterface;

class OrderExportRetryManager
{
    private const MAX_BACKOFF_SECONDS = 86400;

    public function __construct(
        private readonly Config $config,
        private readonly OrderExportPublisher $publisher,
        private readonly ExportLogFactory $exportLogFactory,
        private readonly ExportLogResource $exportLogResource,
        private readonly ExportLogCollectionFactory $collectionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function scheduleAfterFailure(
        int $orderId,
        bool $force,
        int $logId,
        int $attempt,
        \Throwable $exception
    ): bool {
        $log = $this->loadLog($logId);

        if (!$log) {
            return false;
        }

        $attempt = max($attempt, (int)$log->getData('attempts'), 1);
        $maxAttempts = $this->config->getMaxRetryAttempts();

        if ($attempt >= $maxAttempts) {
            $this->markDeadLettered($log, $orderId, $force, $attempt, $maxAttempts, $exception);
            return true;
        }

        $delaySeconds = $this->calculateDelaySeconds($attempt);
        $nextAttempt = $attempt + 1;
        $nextRetryAt = gmdate('Y-m-d H:i:s', time() + $delaySeconds);

        $log->setData('status', ExportLog::STATUS_RETRY_SCHEDULED);
        $log->setData('attempts', $attempt);
        $log->setData('message', sprintf(
            'Attempt %d failed. Retry attempt %d scheduled at %s UTC.',
            $attempt,
            $nextAttempt,
            $nextRetryAt
        ));
        $log->setData('context', $this->encodeContext([
            'order_id' => $orderId,
            'force' => $force,
            'attempt' => $attempt,
            'next_attempt' => $nextAttempt,
            'max_attempts' => $maxAttempts,
            'backoff_seconds' => $delaySeconds,
            'next_retry_at' => $nextRetryAt,
            'last_error' => $exception->getMessage(),
            'queue_topic' => OrderExportPublisher::TOPIC_NAME,
        ]));
        $log->setData('next_retry_at', $nextRetryAt);
        $this->exportLogResource->save($log);

        return true;
    }

    public function retryDue(int $limit = 50): int
    {
        if (!$this->config->isEnabled()) {
            return 0;
        }

        $queued = 0;
        $now = gmdate('Y-m-d H:i:s');
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('status', ExportLog::STATUS_RETRY_SCHEDULED);
        $collection->addFieldToFilter('next_retry_at', ['notnull' => true]);
        $collection->addFieldToFilter('next_retry_at', ['lteq' => $now]);
        $collection->setOrder('next_retry_at', 'ASC');
        $collection->setPageSize(max($limit, 1));

        foreach ($collection as $log) {
            $context = $this->decodeContext((string)$log->getData('context'));
            $orderId = (int)($context['order_id'] ?? $log->getData('order_id'));
            $force = (bool)($context['force'] ?? false);
            $attempt = max((int)($context['next_attempt'] ?? ((int)$log->getData('attempts') + 1)), 1);

            if ($orderId <= 0) {
                continue;
            }

            try {
                $this->publisher->publishRetry($orderId, $force, (int)$log->getId(), $attempt);
            } catch (\Throwable $exception) {
                $this->logger->critical('Order export retry publish failed.', [
                    'log_id' => $log->getId(),
                    'order_id' => $orderId,
                    'exception' => $exception,
                ]);

                continue;
            }

            $log->setData('status', ExportLog::STATUS_QUEUED);
            $log->setData('message', sprintf('Retry attempt %d queued for asynchronous processing.', $attempt));
            $log->setData('context', $this->encodeContext(array_merge($context, [
                'next_attempt' => $attempt,
                'next_retry_at' => null,
            ])));
            $log->setData('next_retry_at', null);
            $this->exportLogResource->save($log);
            $queued++;
        }

        return $queued;
    }

    private function loadLog(int $logId): ?ExportLog
    {
        if ($logId <= 0) {
            return null;
        }

        $log = $this->exportLogFactory->create();
        $this->exportLogResource->load($log, $logId);

        return $log->getId() ? $log : null;
    }

    private function markDeadLettered(
        ExportLog $log,
        int $orderId,
        bool $force,
        int $attempt,
        int $maxAttempts,
        \Throwable $exception
    ): void {
        $log->setData('status', ExportLog::STATUS_DEAD_LETTERED);
        $log->setData('attempts', $attempt);
        $log->setData('message', sprintf(
            'Dead-lettered after %d/%d attempts: %s',
            $attempt,
            $maxAttempts,
            $exception->getMessage()
        ));
        $log->setData('context', $this->encodeContext([
            'order_id' => $orderId,
            'force' => $force,
            'attempt' => $attempt,
            'max_attempts' => $maxAttempts,
            'last_error' => $exception->getMessage(),
            'queue_topic' => OrderExportPublisher::TOPIC_NAME,
        ]));
        $log->setData('next_retry_at', null);
        $this->exportLogResource->save($log);
    }

    private function calculateDelaySeconds(int $attempt): int
    {
        $baseDelay = $this->config->getRetryBaseDelaySeconds();

        if ($baseDelay === 0) {
            return 0;
        }

        return (int)min($baseDelay * (2 ** min(max($attempt - 1, 0), 8)), self::MAX_BACKOFF_SECONDS);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function encodeContext(array $context): string
    {
        return json_encode($context, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeContext(string $context): array
    {
        if ($context === '') {
            return [];
        }

        try {
            $decoded = json_decode($context, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
