<?php

declare(strict_types=1);

namespace Portfolio\SearchEnhancer\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Escaper;
use Magento\Store\Model\StoreManagerInterface;

class SuggestionService
{
    public function __construct(
        private readonly CollectionFactory $searchCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Escaper $escaper
    ) {
    }

    /**
     * @return array{items:array<int,array<string,mixed>>, total:int, tags:string[]}
     */
    public function getSuggestions(string $query, int $limit): array
    {
        $collection = $this->searchCollectionFactory->create();
        $collection->addSearchFilter($query);
        $collection->addAttributeToSelect(['name', 'small_image', 'url_key', 'price', 'erp_brand']);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->addAttributeToFilter('visibility', ['in' => [
            Visibility::VISIBILITY_IN_SEARCH,
            Visibility::VISIBILITY_BOTH,
        ]]);
        $collection->setStoreId((int)$this->storeManager->getStore()->getId());
        $collection->setPageSize($limit);
        $collection->setCurPage(1);

        $total = (int)$collection->getSize();
        $items = [];
        $tags = [];

        foreach ($collection as $product) {
            $productId = (int)$product->getId();
            $tags[] = Product::CACHE_TAG . '_' . $productId;
            $items[] = [
                'id' => $productId,
                'sku' => (string)$product->getSku(),
                'name' => (string)$product->getName(),
                'brand' => (string)$product->getData('erp_brand'),
                'url' => $this->escaper->escapeUrl($product->getProductUrl()),
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
            'tags' => array_values(array_unique($tags)),
        ];
    }
}
