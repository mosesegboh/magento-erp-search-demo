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
use Portfolio\ErpSync\Model\ResourceModel\SyncLog as SyncLogResource;

class ProductSyncService
{
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
    public function sync(?string $since = null, ?int $pageSize = null, bool $dryRun = false, ?int $logId = null): array
    {
        if (!$this->config->isEnabled()) {
            throw new LocalizedException(__('ERP sync is disabled.'));
        }

        $pageSize = $pageSize !== null ? min(max($pageSize, 1), 100) : $this->config->getPageSize();
        $log = $this->startLog($since, $pageSize, $dryRun, $logId);
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

                    $this->upsertProduct($item, $dryRun);
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
            'items_processed' => 0,
            'message' => 'Product sync queued for asynchronous processing.',
            'context' => json_encode([
                'since' => $since,
                'page_size' => $pageSize,
                'dry_run' => $dryRun,
                'queue_topic' => 'portfolio.erp.product_sync',
            ], JSON_THROW_ON_ERROR),
            'started_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->syncLogResource->save($log);

        return $log;
    }

    /**
     * @param array<string, mixed> $payload
     * @throws LocalizedException
     */
    private function upsertProduct(array $payload, bool $dryRun): void
    {
        $sku = trim((string)($payload['sku'] ?? ''));

        if ($sku === '') {
            throw new LocalizedException(__('ERP product payload is missing sku.'));
        }

        if ($dryRun) {
            return;
        }

        try {
            $product = $this->productRepository->get($sku, false, null, true);
        } catch (NoSuchEntityException) {
            $product = $this->productFactory->create();
            $product->setSku($sku);
            $product->setTypeId(Type::TYPE_SIMPLE);
            $product->setAttributeSetId($this->getDefaultAttributeSetId());
        }

        $name = (string)($payload['name'] ?? $sku);
        $stockQty = (float)($payload['stockQty'] ?? 0);
        $isInStock = (bool)($payload['isInStock'] ?? $stockQty > 0);

        $product->setName($name);
        $product->setPrice((float)($payload['price'] ?? 0));
        $product->setStatus($this->mapStatus((string)($payload['status'] ?? 'enabled')));
        $product->setVisibility($this->mapVisibility((string)($payload['visibility'] ?? 'catalog_search')));
        $product->setWebsiteIds([$this->storeManager->getDefaultStoreView()->getWebsiteId()]);
        $product->setTaxClassId(0);
        $product->setWeight(1);
        $product->setUrlKey($this->buildUrlKey($name, $sku));
        $product->setDescription($this->buildDescription($payload));
        $product->setCustomAttribute('erp_brand', (string)($payload['brand'] ?? ''));
        $product->setCustomAttribute('erp_source_updated_at', (string)($payload['updatedAt'] ?? ''));

        $this->productRepository->save($product);
        $this->updateStock($sku, $stockQty, $isInStock);
    }

    private function updateStock(string $sku, float $stockQty, bool $isInStock): void
    {
        $stockItem = $this->stockRegistry->getStockItemBySku($sku);
        $stockItem->setQty($stockQty);
        $stockItem->setIsInStock($isInStock);
        $stockItem->setManageStock(true);
        $this->stockRegistry->updateStockItemBySku($sku, $stockItem);
    }

    private function mapStatus(string $status): int
    {
        return strtolower($status) === 'enabled' ? Status::STATUS_ENABLED : Status::STATUS_DISABLED;
    }

    private function mapVisibility(string $visibility): int
    {
        return match (strtolower($visibility)) {
            'catalog' => Visibility::VISIBILITY_IN_CATALOG,
            'search' => Visibility::VISIBILITY_IN_SEARCH,
            'not_visible', 'hidden' => Visibility::VISIBILITY_NOT_VISIBLE,
            default => Visibility::VISIBILITY_BOTH,
        };
    }

    private function getDefaultAttributeSetId(): int
    {
        return (int)$this->eavConfig->getEntityType(Product::ENTITY)->getDefaultAttributeSetId();
    }

    private function buildUrlKey(string $name, string $sku): string
    {
        $urlKey = strtolower((string)preg_replace('/[^a-zA-Z0-9]+/', '-', $name . '-' . $sku));
        return trim($urlKey, '-');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildDescription(array $payload): string
    {
        $categories = $payload['categories'] ?? [];

        if (!is_array($categories)) {
            $categories = [];
        }

        $tags = $payload['attributes']['tags'] ?? [];

        if (!is_array($tags)) {
            $tags = [];
        }

        return sprintf(
            'Imported from ERP. Categories: %s. Tags: %s.',
            implode(', ', $categories),
            implode(', ', $tags)
        );
    }

    private function startLog(?string $since, int $pageSize, bool $dryRun, ?int $logId): SyncLog
    {
        $log = $this->syncLogFactory->create();

        if ($logId !== null && $logId > 0) {
            $this->syncLogResource->load($log, $logId);
        }

        $data = [
            'sync_type' => 'product_updates',
            'status' => SyncLog::STATUS_RUNNING,
            'items_processed' => 0,
            'message' => 'Product sync started.',
            'context' => json_encode([
                'since' => $since,
                'page_size' => $pageSize,
                'dry_run' => $dryRun,
            ], JSON_THROW_ON_ERROR),
            'started_at' => gmdate('Y-m-d H:i:s'),
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
        $log->setData('finished_at', gmdate('Y-m-d H:i:s'));
        $this->syncLogResource->save($log);
    }
}
