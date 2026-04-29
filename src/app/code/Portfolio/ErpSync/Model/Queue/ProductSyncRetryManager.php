<?php

declare(strict_types=1);

namespace Portfolio\ErpSync\Model\Queue;

use Portfolio\ErpSync\Model\Config;
use Portfolio\ErpSync\Model\ResourceModel\SyncLog as SyncLogResource;
use Portfolio\ErpSync\Model\ResourceModel\SyncLog\CollectionFactory as SyncLogCollectionFactory;
use Portfolio\ErpSync\Model\SyncLog;
use Portfolio\ErpSync\Model\SyncLogFactory;
use Psr\Log\LoggerInterface;

class ProductSyncRetryManager
{
    private const MAX_BACKOFF_SECONDS = 86400;

    public function __construct(
        private readonly Config $config,
        private readonly ProductSyncPublisher $publisher,
        private readonly SyncLogFactory $syncLogFactory,
        private readonly SyncLogResource $syncLogResource,
        private readonly SyncLogCollectionFactory $collectionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function scheduleAfterFailure(
        string $since,
        int $pageSize,
        bool $dryRun,
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
            $this->markDeadLettered($log, $since, $pageSize, $dryRun, $attempt, $maxAttempts, $exception);
            return true;
        }

        $delaySeconds = $this->calculateDelaySeconds($attempt);
        $nextAttempt = $attempt + 1;
        $nextRetryAt = $this->getNextRetryAt($delaySeconds);

        $log->setData('status', SyncLog::STATUS_RETRY_SCHEDULED);
        $log->setData('attempts', $attempt);
        $log->setData('message', sprintf(
            'Attempt %d failed. Retry attempt %d scheduled at %s UTC.',
            $attempt,
            $nextAttempt,
            $nextRetryAt
        ));
        $log->setData('context', $this->encodeContext([
            'since' => $since !== '' ? $since : null,
            'page_size' => $pageSize > 0 ? $pageSize : null,
            'dry_run' => $dryRun,
            'attempt' => $attempt,
            'next_attempt' => $nextAttempt,
            'max_attempts' => $maxAttempts,
            'backoff_seconds' => $delaySeconds,
            'next_retry_at' => $nextRetryAt,
            'last_error' => $exception->getMessage(),
            'queue_topic' => ProductSyncPublisher::TOPIC_NAME,
        ]));
        $log->setData('next_retry_at', $nextRetryAt);
        $log->setData('finished_at', null);
        $this->syncLogResource->save($log);

        return true;
    }

    public function retryDue(int $limit = 50): int
    {
        if (!$this->config->isEnabled()) {
            return 0;
        }

        $queued = 0;
        $now = $this->formatUtcDateTime($this->getCurrentUtcDateTime());
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('status', SyncLog::STATUS_RETRY_SCHEDULED);
        $collection->addFieldToFilter('next_retry_at', ['notnull' => true]);
        $collection->addFieldToFilter('next_retry_at', ['lteq' => $now]);
        $collection->setOrder('next_retry_at', 'ASC');
        $collection->setPageSize(max($limit, 1));

        foreach ($collection as $log) {
            $context = $this->decodeContext((string)$log->getData('context'));
            $attempt = max((int)($context['next_attempt'] ?? ((int)$log->getData('attempts') + 1)), 1);
            $since = isset($context['since']) && is_string($context['since']) ? $context['since'] : null;
            $pageSize = isset($context['page_size']) ? (int)$context['page_size'] : null;
            $dryRun = (bool)($context['dry_run'] ?? false);

            try {
                $this->publisher->publishRetry(
                    $log,
                    $since !== '' ? $since : null,
                    $pageSize !== null && $pageSize > 0 ? $pageSize : null,
                    $dryRun,
                    $attempt
                );
            } catch (\Throwable $exception) {
                $this->logger->critical('ERP product sync retry publish failed.', [
                    'log_id' => $log->getId(),
                    'exception' => $exception,
                ]);

                continue;
            }

            $log->setData('status', SyncLog::STATUS_QUEUED);
            $log->setData('message', sprintf('Retry attempt %d queued for asynchronous processing.', $attempt));
            $log->setData('context', $this->encodeContext(array_merge($context, [
                'next_attempt' => $attempt,
                'next_retry_at' => null,
            ])));
            $log->setData('next_retry_at', null);
            $log->setData('finished_at', null);
            $this->syncLogResource->save($log);
            $queued++;
        }

        return $queued;
    }

    private function loadLog(int $logId): ?SyncLog
    {
        if ($logId <= 0) {
            return null;
        }

        $log = $this->syncLogFactory->create();
        $this->syncLogResource->load($log, $logId);

        return $log->getId() ? $log : null;
    }

    private function markDeadLettered(
        SyncLog $log,
        string $since,
        int $pageSize,
        bool $dryRun,
        int $attempt,
        int $maxAttempts,
        \Throwable $exception
    ): void {
        $log->setData('status', SyncLog::STATUS_DEAD_LETTERED);
        $log->setData('attempts', $attempt);
        $log->setData('message', sprintf(
            'Dead-lettered after %d/%d attempts: %s',
            $attempt,
            $maxAttempts,
            $exception->getMessage()
        ));
        $log->setData('context', $this->encodeContext([
            'since' => $since !== '' ? $since : null,
            'page_size' => $pageSize > 0 ? $pageSize : null,
            'dry_run' => $dryRun,
            'attempt' => $attempt,
            'max_attempts' => $maxAttempts,
            'last_error' => $exception->getMessage(),
            'queue_topic' => ProductSyncPublisher::TOPIC_NAME,
        ]));
        $log->setData('next_retry_at', null);
        $log->setData('finished_at', $this->formatUtcDateTime($this->getCurrentUtcDateTime()));
        $this->syncLogResource->save($log);
    }

    private function calculateDelaySeconds(int $attempt): int
    {
        $baseDelay = $this->config->getRetryBaseDelaySeconds();

        if ($baseDelay === 0) {
            return 0;
        }

        return (int)min($baseDelay * (2 ** min(max($attempt - 1, 0), 8)), self::MAX_BACKOFF_SECONDS);
    }

    private function getNextRetryAt(int $delaySeconds): string
    {
        $nextRetryDateTime = $this->getCurrentUtcDateTime()->add(new \DateInterval(sprintf('PT%dS', $delaySeconds)));
        return $this->formatUtcDateTime($nextRetryDateTime);
    }

    private function getCurrentUtcDateTime(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private function formatUtcDateTime(\DateTimeImmutable $dateTime): string
    {
        return $dateTime->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
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
