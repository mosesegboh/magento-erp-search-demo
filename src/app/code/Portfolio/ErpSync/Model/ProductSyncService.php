<?php

declare(strict_types=1);

namespace Portfolio\ErpSync\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Portfolio\ErpSync\Api\ErpClientInterface;
use Portfolio\ErpSync\Model\Dto\ErpProductData;
use Portfolio\ErpSync\Model\ResourceModel\SyncLog as SyncLogResource;

class ProductSyncService
{
    private const STATUS_MAP = [
        'enabled' => Status::STATUS_ENABLED,
        'disabled' => Status::STATUS_DISABLED,
    ];

    private const VISIBILITY_MAP = [
        'catalog' => Visibility::VISIBILITY_IN_CATALOG,
        'search' => Visibility::VISIBILITY_IN_SEARCH,
        'not_visible' => Visibility::VISIBILITY_NOT_VISIBLE,
        'hidden' => Visibility::VISIBILITY_NOT_VISIBLE,
        'catalog_search' => Visibility::VISIBILITY_BOTH,
    ];

    public function __construct(
        private readonly Config $config,
        private readonly ErpClientInterface $erpClient,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductFactory $productFactory,
        private readonly EavConfig $eavConfig,
        private readonly StockRegistryInterface $stockRegistry,
        private readonly StoreManagerInterface $storeManager,
        private readonly SyncLogFactory $syncLogFactory,
        private readonly SyncLogResource $syncLogResource
    ) {
    }

    /**
     * @return array{items_processed:int, pages_processed:int, dry_run:bool}
     * @throws LocalizedException
     */
    public function sync(
        ?string $since = null,
        ?int $pageSize = null,
        bool $dryRun = false,
        ?int $logId = null,
        int $attempt = 1
    ): array
    {
        if (!$this->config->isEnabled()) {
            throw new LocalizedException(__('ERP sync is disabled.'));
        }

        $pageSize = $pageSize !== null ? min(max($pageSize, 1), 100) : $this->config->getPageSize();
        $attempt = max($attempt, 1);
        $log = $this->startLog($since, $pageSize, $dryRun, $logId, $attempt);
        $itemsProcessed = 0;
        $pagesProcessed = 0;

        try {
            for ($page = 1; ; $page++) {
                $response = $this->erpClient->getProductUpdates($page, $pageSize, $since);
                $items = $response['data'] ?? [];
                $meta = $response['meta'] ?? [];

                if (!is_array($items) || $items === []) {
                    break;
                }

                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $this->upsertProduct(ErpProductData::fromPayload($item), $dryRun);
                    $itemsProcessed++;
                }

                $pagesProcessed++;
                $totalPages = isset($meta['totalPages']) ? (int)$meta['totalPages'] : $page;

                if ($page >= $totalPages) {
                    break;
                }
            }

            $this->completeLog(
                $log,
                SyncLog::STATUS_SUCCESS,
                $itemsProcessed,
                sprintf('Product sync completed. Processed %d item(s).', $itemsProcessed),
                ['pages_processed' => $pagesProcessed]
            );
        } catch (\Throwable $exception) {
            $this->completeLog(
                $log,
                SyncLog::STATUS_FAILED,
                $itemsProcessed,
                $exception->getMessage(),
                ['pages_processed' => $pagesProcessed]
            );

            throw $exception;
        }

        return [
            'items_processed' => $itemsProcessed,
            'pages_processed' => $pagesProcessed,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * @throws LocalizedException
     */
    public function queueSync(?string $since = null, ?int $pageSize = null, bool $dryRun = false): SyncLog
    {
        if (!$this->config->isEnabled()) {
            throw new LocalizedException(__('ERP sync is disabled.'));
        }

        $pageSize = $pageSize !== null ? min(max($pageSize, 1), 100) : $this->config->getPageSize();
        $log = $this->syncLogFactory->create();
        $log->setData([
            'sync_type' => 'product_updates',
            'status' => SyncLog::STATUS_QUEUED,
            'attempts' => 0,
            'items_processed' => 0,
            'message' => 'Product sync queued for asynchronous processing.',
            'context' => json_encode([
                'since' => $since,
                'page_size' => $pageSize,
                'dry_run' => $dryRun,
                'queue_topic' => 'portfolio.erp.product_sync',
                'next_attempt' => 1,
            ], JSON_THROW_ON_ERROR),
            'next_retry_at' => null,
            'started_at' => $this->getCurrentUtcTimestamp(),
        ]);
        $this->syncLogResource->save($log);

        return $log;
    }

    /**
     * @throws LocalizedException
     */
    private function upsertProduct(ErpProductData $productData, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        $productSku = $productData->getProductSku();

        try {
            $product = $this->productRepository->get($productSku, false, null, true);
        } catch (NoSuchEntityException) {
            $product = $this->productFactory->create();
            $product->setSku($productSku);
            $product->setTypeId(Type::TYPE_SIMPLE);
            $product->setAttributeSetId($this->getDefaultAttributeSetId());
        }

        $product->setName($productData->getName());
        $product->setPrice($productData->getPrice());
        $product->setStatus($this->mapStatus($productData->getStatus()));
        $product->setVisibility($this->mapVisibility($productData->getVisibility()));
        $product->setWebsiteIds([$this->storeManager->getDefaultStoreView()->getWebsiteId()]);
        $product->setTaxClassId(0);
        $product->setWeight(1);
        $product->setUrlKey($this->buildUrlKey($productData->getName(), $productSku));
        $product->setDescription($this->buildDescription($productData));
        $product->setCustomAttribute('erp_brand', $productData->getBrand());
        $product->setCustomAttribute('erp_source_updated_at', $productData->getSourceUpdatedAt());

        $this->productRepository->save($product);
        $this->updateStock($productSku, $productData->getStockQuantity(), $productData->isInStock());
    }

    private function updateStock(string $productSku, float $stockQuantity, bool $isInStock): void
    {
        $stockItem = $this->stockRegistry->getStockItemBySku($productSku);
        $stockItem->setQty($stockQuantity);
        $stockItem->setIsInStock($isInStock);
        $stockItem->setManageStock(true);
        $this->stockRegistry->updateStockItemBySku($productSku, $stockItem);
    }

    private function mapStatus(string $status): int
    {
        return self::STATUS_MAP[strtolower($status)] ?? Status::STATUS_DISABLED;
    }

    private function mapVisibility(string $visibility): int
    {
        return self::VISIBILITY_MAP[strtolower($visibility)] ?? Visibility::VISIBILITY_BOTH;
    }

    private function getDefaultAttributeSetId(): int
    {
        return (int)$this->eavConfig->getEntityType(Product::ENTITY)->getDefaultAttributeSetId();
    }

    private function buildUrlKey(string $name, string $productSku): string
    {
        $urlKey = strtolower((string)preg_replace('/[^a-zA-Z0-9]+/', '-', $name . '-' . $productSku));
        return trim($urlKey, '-');
    }

    private function buildDescription(ErpProductData $productData): string
    {
        return sprintf(
            'Imported from ERP. Categories: %s. Tags: %s.',
            implode(', ', $productData->getCategories()),
            implode(', ', $productData->getTags())
        );
    }

    private function startLog(?string $since, int $pageSize, bool $dryRun, ?int $logId, int $attempt): SyncLog
    {
        $log = $this->syncLogFactory->create();

        if ($logId !== null && $logId > 0) {
            $this->syncLogResource->load($log, $logId);
        }

        $data = [
            'sync_type' => 'product_updates',
            'status' => SyncLog::STATUS_RUNNING,
            'attempts' => $attempt,
            'items_processed' => 0,
            'message' => sprintf('Product sync attempt %d started.', $attempt),
            'context' => json_encode([
                'since' => $since,
                'page_size' => $pageSize,
                'dry_run' => $dryRun,
                'attempt' => $attempt,
            ], JSON_THROW_ON_ERROR),
            'next_retry_at' => null,
            'started_at' => $this->getCurrentUtcTimestamp(),
        ];

        if ($log->getId()) {
            $data['log_id'] = (int)$log->getId();
        }

        $log->setData($data);
        $this->syncLogResource->save($log);

        return $log;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function completeLog(SyncLog $log, string $status, int $itemsProcessed, string $message, array $context): void
    {
        $log->setData('status', $status);
        $log->setData('items_processed', $itemsProcessed);
        $log->setData('message', $message);
        $log->setData('context', json_encode($context, JSON_THROW_ON_ERROR));
        $log->setData('next_retry_at', null);
        $log->setData('finished_at', $this->getCurrentUtcTimestamp());
        $this->syncLogResource->save($log);
    }

    private function getCurrentUtcTimestamp(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
