<?php

declare(strict_types=1);

namespace Portfolio\OrderExport\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

class OrderReferenceResolver
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderCollectionFactory $orderCollectionFactory
    ) {
    }

    /**
     * Resolve either a Magento order entity ID or increment ID to an entity ID.
     *
     * @throws LocalizedException
     */
    public function resolve(string $orderReference): int
    {
        $normalizedOrderReference = trim($orderReference);

        if ($normalizedOrderReference === '') {
            throw new LocalizedException(__('Order reference is required.'));
        }

        if (ctype_digit($normalizedOrderReference)) {
            try {
                $order = $this->orderRepository->get((int)$normalizedOrderReference);
                return (int)$order->getEntityId();
            } catch (\Throwable) {
                // Numeric increment IDs are common, so fall back to increment lookup.
            }
        }

        $orderCollection = $this->orderCollectionFactory->create();
        $orderCollection->addFieldToFilter('increment_id', $normalizedOrderReference);
        $orderCollection->setPageSize(1);
        $order = $orderCollection->getFirstItem();

        if (!$order->getId()) {
            throw new LocalizedException(__('Order %1 was not found.', $normalizedOrderReference));
        }

        return (int)$order->getId();
    }
}
